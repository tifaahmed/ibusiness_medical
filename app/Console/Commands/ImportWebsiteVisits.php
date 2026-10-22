<?php

namespace App\Console\Commands;

use App\Models\Facility;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Copies the website's old `page_visits` rows into this system's `visitors` /
 * `page_visits`, so the history lives next to the new app and web tracking.
 *
 * Safe to re-run: every copied row keeps the old id in `legacy_id` (unique) and
 * is skipped the second time. The old rows only carried a day-salted hash, so
 * visitors are rebuilt from ip + user agent (`legacy-<sha1>`), which also joins
 * the same person across days. Nothing is written to the website's database.
 *
 *   php artisan visits:import-website --dry
 *   php artisan visits:import-website --connection=website --chunk=2000
 *   php artisan visits:import-website --since=2026-10-22 --until="2026-10-22 14:44:12"
 */
class ImportWebsiteVisits extends Command
{
    protected $signature = 'visits:import-website
        {--connection=website : database connection holding the website\'s page_visits}
        {--table=page_visits : table name on that connection}
        {--chunk=1000 : rows per batch}
        {--since= : only rows visited on/after this date (Y-m-d)}
        {--until= : only rows visited BEFORE this moment (Y-m-d H:i:s) — set it to when the website started relaying, so a relayed visit is not copied twice}
        {--timezone=UTC : the website\'s app timezone — its visited_at is converted from this into ours}
        {--dry : read and count, write nothing}';

    protected $description = 'Import the website\'s old page_visits into visitors / page_visits (re-runnable)';

    public function handle(): int
    {
        $conn = $this->option('connection');
        $table = $this->option('table');
        $dry = (bool) $this->option('dry');

        try {
            $source = DB::connection($conn);
            $source->getPdo();
            $total = $source->table($table)->when($this->option('since'), fn ($q, $d) => $q->where('visited_at', '>=', $d))->when($this->option('until'), fn ($q, $d) => $q->where('visited_at', '<', $d))->count();
        } catch (Throwable $e) {
            Log::error('visits:import-website could not read the source', ['connection' => $conn, 'exception' => $e->getMessage()]);
            $this->error("Cannot read {$conn}.{$table}: ".$e->getMessage());

            return self::FAILURE;
        }

        $this->info(($dry ? '[dry] ' : '')."{$total} rows to look at in {$conn}.{$table}");
        $bar = $this->output->createProgressBar($total);
        $imported = $skipped = 0;
        $productIds = [];
        $facilityIds = [];

        $query = $source->table($table)->when($this->option('since'), fn ($q, $d) => $q->where('visited_at', '>=', $d))->when($this->option('until'), fn ($q, $d) => $q->where('visited_at', '<', $d))->orderBy('id');

        try {
            $query->chunkById((int) $this->option('chunk'), function ($rows) use ($dry, &$imported, &$skipped, &$productIds, &$facilityIds, $bar) {
                $existing = DB::table('page_visits')->whereIn('legacy_id', $rows->pluck('id'))->pluck('legacy_id')->flip();
                $fresh = $rows->reject(fn ($r) => $existing->has($r->id));
                $skipped += $rows->count() - $fresh->count();

                if (! $dry && $fresh->isNotEmpty()) {
                    $this->write($fresh, $productIds, $facilityIds);
                }
                $imported += $fresh->count();
                $bar->advance($rows->count());
            });
        } catch (Throwable $e) {
            Log::error('visits:import-website failed', ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            $this->newLine();
            $this->error('Import stopped: '.$e->getMessage().' — fix and re-run, finished rows are skipped.');

            return self::FAILURE;
        }

        $bar->finish();
        $this->newLine();

        if (! $dry) {
            $this->recount();
        }

        $this->info(($dry ? 'Would import' : 'Imported')." {$imported} visits, skipped {$skipped} already imported.");

        return self::SUCCESS;
    }

    private function write($rows, array &$productIds, array &$facilityIds): void
    {
        DB::transaction(function () use ($rows, &$productIds, &$facilityIds) {
            $keyOf = fn ($r) => 'legacy-'.sha1(($r->ip_address ?? 'no-ip').'|'.($r->user_agent ?? 'no-agent'));
            $keys = $rows->map($keyOf)->unique()->values();
            $known = DB::table('visitors')->whereIn('visitor_key', $keys)->pluck('id', 'visitor_key');

            $now = now();
            foreach ($rows->groupBy($keyOf) as $key => $group) {
                if ($known->has($key)) {
                    continue;
                }
                $first = $group->first();
                $device = $first->device ?? null;
                $known[$key] = DB::table('visitors')->insertGetId([
                    'visitor_key' => $key,
                    'source' => 'web',
                    'ip_address' => $first->ip_address,
                    'user_agent' => $first->user_agent,
                    'device_type' => $device,
                    'locale' => $first->locale,
                    'first_referrer' => $first->referrer,
                    'is_bot' => (bool) $first->is_bot,
                    'visits_count' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $insert = $rows->map(function ($r) use ($keyOf, $known, $now, &$productIds, &$facilityIds) {
                $url = (string) $r->url;

                return [
                    'visitor_id' => $known[$keyOf($r)],
                    'source' => 'web',
                    'page' => $r->page,
                    'url' => $url,
                    'locale' => $r->locale,
                    'referrer' => $r->referrer,
                    'product_id' => $this->lookup(Product::class, $url, '#/products/([^/?\#]+)#', $productIds),
                    'facility_id' => $this->lookup(Facility::class, $url, '#/facility/([^/?\#]+)#', $facilityIds),
                    'ip_address' => $r->ip_address,
                    'user_agent' => $r->user_agent,
                    'is_bot' => (bool) $r->is_bot,
                    'visited_at' => Carbon::parse($r->visited_at, $this->option('timezone'))->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s'),
                    'legacy_id' => $r->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })->all();

            foreach (array_chunk($insert, 500) as $part) {
                DB::table('page_visits')->insertOrIgnore($part);
            }
        });
    }

    /** @param  class-string  $model */
    private function lookup(string $model, string $url, string $pattern, array &$cache): ?int
    {
        if (! preg_match($pattern, $url, $m)) {
            return null;
        }
        $slug = urldecode($m[1]);
        if (! array_key_exists($slug, $cache)) {
            $cache[$slug] = $model::where('slug', $slug)->value('id');
        }

        return $cache[$slug];
    }

    /** Visit counts and first/last seen, recomputed from the visits themselves. */
    private function recount(): void
    {
        DB::statement('UPDATE visitors SET
            visits_count = (SELECT COUNT(*) FROM page_visits WHERE page_visits.visitor_id = visitors.id),
            first_seen_at = (SELECT MIN(visited_at) FROM page_visits WHERE page_visits.visitor_id = visitors.id),
            last_seen_at = (SELECT MAX(visited_at) FROM page_visits WHERE page_visits.visitor_id = visitors.id)
            WHERE visitor_key LIKE \'legacy-%\'');
    }
}

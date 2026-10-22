<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The website runs on UTC, this app on Africa/Cairo. Until RecordVisitAction and
 * visits:import-website converted, every web visit was stored with its UTC wall
 * time and read here as Cairo time — three hours early. On the live database
 * that is exactly page_visits 1..69682 (the import plus the first relayed
 * visits, all created before the fix); later rows are already right.
 *
 * Fingerprinted to that database: anywhere row 69682 is not the visit relayed
 * at 15:18:31 UTC, this does nothing, so a copy or a fresh install is never
 * shifted. Every row in the range sits in Egyptian summer time (+03:00), which
 * is checked rather than assumed.
 */
return new class extends Migration
{
    private const LAST_ID = 69682;

    private const LAST_UTC = '2026-10-22 15:18:31';

    public function up(): void
    {
        $last = DB::table('page_visits')->where('id', self::LAST_ID)->value('visited_at');
        if ($last === null || (string) $last !== self::LAST_UTC) {
            return;
        }

        $range = DB::table('page_visits')->where('id', '<=', self::LAST_ID)->selectRaw('min(visited_at) a, max(visited_at) b')->first();
        $hours = collect([$range->a, $range->b])->map(fn ($t) => Carbon::parse($t, 'UTC')->setTimezone(config('app.timezone'))->getOffset() / 3600)->unique();
        if ($hours->count() !== 1) {
            throw new RuntimeException('Visit range crosses a DST change; shift it row by row instead.');
        }
        $h = (int) $hours->first();

        DB::transaction(function () use ($h) {
            $n = DB::update('UPDATE page_visits SET visited_at = visited_at + INTERVAL ? HOUR WHERE id <= ?', [$h, self::LAST_ID]);
            $this->recount();
            Log::info('Shifted web visit times into the app timezone', ['rows' => $n, 'hours' => $h]);
        });
    }

    public function down(): void
    {
        $last = DB::table('page_visits')->where('id', self::LAST_ID)->value('visited_at');
        if ($last === null || (string) $last === self::LAST_UTC) {
            return;
        }

        DB::transaction(function () {
            DB::update('UPDATE page_visits SET visited_at = visited_at - INTERVAL 3 HOUR WHERE id <= ?', [self::LAST_ID]);
            $this->recount();
        });
    }

    /** First/last seen are derived from the visits, so they follow them. */
    private function recount(): void
    {
        DB::statement('UPDATE visitors SET
            visits_count = (SELECT COUNT(*) FROM page_visits WHERE page_visits.visitor_id = visitors.id),
            first_seen_at = (SELECT MIN(visited_at) FROM page_visits WHERE page_visits.visitor_id = visitors.id),
            last_seen_at = (SELECT MAX(visited_at) FROM page_visits WHERE page_visits.visitor_id = visitors.id)
            WHERE EXISTS (SELECT 1 FROM page_visits WHERE page_visits.visitor_id = visitors.id AND page_visits.id <= '.self::LAST_ID.')');
    }
};

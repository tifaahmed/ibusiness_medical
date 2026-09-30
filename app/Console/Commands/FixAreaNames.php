<?php

namespace App\Console\Commands;

use App\Models\Area;
use App\Services\Ai\RateLimitException;
use App\Services\AreaNameFixer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Re-spaces the census's run-together Arabic area names and fills each area's
 * English name with Gemini. Resumable: it only takes areas with NO English name
 * yet, so an interrupted run is simply started again, and a name an admin has
 * already edited (English filled) is never touched. `--dry` writes nothing.
 */
class FixAreaNames extends Command
{
    protected $signature = 'areas:fix-names
        {--limit=0 : Stop after this many areas (0 = all)}
        {--chunk=40 : Areas per AI request}
        {--dry : Show what would change and write nothing}';

    protected $description = 'Fix run-together Arabic area names and add English names (Gemini)';

    public function handle(AreaNameFixer $fixer): int
    {
        if (! AreaNameFixer::isConfigured()) {
            $this->error('GEMINI_API_KEY is not set.');

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry');
        $chunk = max(5, (int) $this->option('chunk'));
        $limit = (int) $this->option('limit');

        $query = Area::query()
            ->with('city.governorate')
            ->where(fn ($q) => $q->whereNull('name->en')->orWhere('name->en', ''))
            ->orderBy('id');

        $total = $limit > 0 ? min($limit, (clone $query)->count()) : (clone $query)->count();
        $this->info("{$total} areas without an English name".($dry ? ' (dry run)' : '').'.');

        $done = $spaced = $rejected = $noAnswer = 0;

        foreach ($query->when($limit > 0, fn ($q) => $q->limit($limit))->get()->chunk($chunk) as $batch) {
            $proposals = $this->propose($fixer, $batch);
            if ($proposals === null) {
                $this->error('Gave up on a batch after repeated failures; run the command again to resume.');

                return self::FAILURE;
            }

            foreach ($batch as $area) {
                $p = $proposals[$area->id] ?? null;
                if ($p === null) {
                    $noAnswer++;

                    continue;
                }

                $rejected += $p['ar_rejected'] ? 1 : 0;
                $spaced += $p['ar_changed'] ? 1 : 0;

                if ($dry) {
                    $this->line(sprintf('%s | %s => %s | %s', $area->pcode, $area->getTranslation('name', 'ar'), $p['ar'], $p['en'] ?? '—').($p['ar_rejected'] ? '  [Arabic rewrite rejected]' : ''));
                } else {
                    $done += $fixer->apply($area, $p) ? 1 : 0;
                }
            }

            $this->info(sprintf('… %d / %d', $done + $noAnswer, $total));
            sleep(1);
        }

        Log::info('areas:fix-names finished', compact('dry', 'done', 'spaced', 'rejected', 'noAnswer'));
        $this->info("Saved {$done}; Arabic re-spaced {$spaced}; Arabic rewrites rejected {$rejected}; no usable answer {$noAnswer}.");

        return self::SUCCESS;
    }

    /**
     * One batch, waiting out Gemini's per-minute limit. Null when it keeps failing.
     */
    private function propose(AreaNameFixer $fixer, $batch): ?array
    {
        for ($attempt = 1; $attempt <= 8; $attempt++) {
            try {
                return $fixer->propose($batch);
            } catch (RateLimitException) {
                $this->warn("Rate limited, waiting 15s (attempt {$attempt})…");
                sleep(15);
            } catch (\Throwable $e) {
                Log::error('areas:fix-names batch failed', ['attempt' => $attempt, 'error' => $e->getMessage()]);
                $this->warn("Batch failed ({$e->getMessage()}), retrying…");
                sleep(5);
            }
        }

        return null;
    }
}

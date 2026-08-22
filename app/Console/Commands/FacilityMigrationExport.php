<?php

namespace App\Console\Commands;

use App\Services\FacilityMigration\FacilityMigrationExporter;
use Illuminate\Console\Command;

class FacilityMigrationExport extends Command
{
    protected $signature = 'facility:migration-export
        {--output= : Where to write the .zip (default storage/app/facility-migration)}
        {--no-media : Export data only, leaving the image files out}
        {--no-offers : Skip offers attached to facilities and branches}
        {--no-branches : Leave the branch rows out of the package}
        {--no-managers : Leave the facility managers out of the package}
        {--offset= : Skip this many facilities — use with --limit to export in parts}
        {--limit= : Export at most this many facilities}
        {--part= : 1-based part number; with --per-part, works out offset/limit for you}
        {--per-part= : Facilities per part}
        {--search= : Only facilities matching this name/slug}
        {--slug= : Export a single facility by slug}
        {--facility-type= : Filter by facility_type_id}
        {--governorate= : Filter by governorate_id}
        {--sales= : Filter by sales_id}';

    protected $description = 'Build a portable migration package (facilities + branches + managers + tags + offers + images).';

    public function handle(FacilityMigrationExporter $exporter): int
    {
        $includeMedia = ! $this->option('no-media');
        $includeBranches = ! $this->option('no-branches');
        $includeManagers = ! $this->option('no-managers');

        $filters = array_filter([
            'search' => $this->option('search'),
            'slug' => $this->option('slug'),
            'facility_type_id' => $this->option('facility-type'),
            'governorate_id' => $this->option('governorate'),
            'sales_id' => $this->option('sales'),
        ], fn ($v) => $v !== null && $v !== '');

        // --part/--per-part is the friendly way to say --offset/--limit.
        $perPart = $this->option('per-part') !== null ? max(1, (int) $this->option('per-part')) : null;
        $part = $this->option('part') !== null ? max(1, (int) $this->option('part')) : null;

        $offset = $this->option('offset') !== null ? max(0, (int) $this->option('offset')) : null;
        $limit = $this->option('limit') !== null ? max(1, (int) $this->option('limit')) : null;

        $totalParts = null;
        if ($perPart !== null && $part !== null) {
            $offset = ($part - 1) * $perPart;
            $limit = $perPart;
            $total = $exporter->countMatching($filters);
            $totalParts = $total > 0 ? (int) ceil($total / $perPart) : 1;

            if ($part > $totalParts) {
                $this->warn("There is no part {$part} — only {$totalParts} part(s) match.");

                return self::SUCCESS;
            }
            $this->info("Building part {$part} of {$totalParts} ({$total} facilities match)…");
        } else {
            $this->info('Building migration package…');
        }

        $destination = $this->option('output')
            ?: storage_path('app/facility-migration/'.$exporter->filename(
                $includeMedia,
                $part,
                $totalParts,
                $includeBranches,
                $includeManagers
            ));

        $path = $exporter->build([
            'include_media_files' => $includeMedia,
            'include_offers' => ! $this->option('no-offers'),
            'include_branches' => $includeBranches,
            'include_managers' => $includeManagers,
            'destination' => $destination,
            'filters' => $filters,
            'offset' => $offset,
            'limit' => $limit,
        ]);

        $this->newLine();
        $this->info('Package written to: '.$path);
        $this->line('Size: '.$this->humanSize(filesize($path)));

        if ($totalParts !== null && $part < $totalParts) {
            $this->newLine();
            $this->line('Next part:');
            $this->line('  php artisan facility:migration-export --part='.($part + 1)." --per-part={$perPart}");
        }

        $this->newLine();
        $this->line('Restore it on the target site with:');
        $this->line('  php artisan facility:migration-import '.$path.' --mode=merge --dry-run');

        return self::SUCCESS;
    }

    private function humanSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2).' '.$units[$i];
    }
}

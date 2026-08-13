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
        {--search= : Only facilities matching this name/slug}
        {--slug= : Export a single facility by slug}
        {--facility-type= : Filter by facility_type_id}
        {--governorate= : Filter by governorate_id}
        {--sales= : Filter by sales_id}';

    protected $description = 'Build a portable migration package (facilities + branches + tags + offers + images).';

    public function handle(FacilityMigrationExporter $exporter): int
    {
        $includeMedia = ! $this->option('no-media');

        $destination = $this->option('output')
            ?: storage_path('app/facility-migration/'.$exporter->filename($includeMedia));

        $this->info('Building migration package…');

        $path = $exporter->build([
            'include_media_files' => $includeMedia,
            'include_offers' => ! $this->option('no-offers'),
            'destination' => $destination,
            'filters' => array_filter([
                'search' => $this->option('search'),
                'slug' => $this->option('slug'),
                'facility_type_id' => $this->option('facility-type'),
                'governorate_id' => $this->option('governorate'),
                'sales_id' => $this->option('sales'),
            ], fn ($v) => $v !== null && $v !== ''),
        ]);

        $this->newLine();
        $this->info('Package written to: '.$path);
        $this->line('Size: '.$this->humanSize(filesize($path)));
        $this->newLine();
        $this->line('Restore it on the target site with:');
        $this->line('  php artisan facility:migration-import '.$path.' --mode=fresh --dry-run');

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

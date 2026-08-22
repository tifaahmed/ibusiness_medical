<?php

namespace App\Console\Commands;

use App\Services\FacilityMigration\FacilityMigrationImporter;
use Illuminate\Console\Command;

class FacilityMigrationImport extends Command
{
    protected $signature = 'facility:migration-import
        {package : Path to the .zip package (or a bare facilities.json)}
        {--mode=merge : "merge" upserts by slug, "fresh" wipes existing facilities first}
        {--dry-run : Parse and report without writing anything}
        {--inspect : Only print what the package contains, then stop}
        {--skip-media : Restore rows but do not attach any image files}
        {--prune-missing : On a merge, delete branches/managers an updated facility holds that the package does not name}
        {--chunk=25 : Facilities to commit per step}
        {--media= : Path to an unzipped storage/app/public when images are supplied separately}';

    protected $description = 'Restore a facility migration package produced by the old site.';

    public function handle(FacilityMigrationImporter $importer): int
    {
        $package = $this->argument('package');
        $mode = $this->option('mode');
        $dryRun = (bool) $this->option('dry-run');

        try {
            if ($this->option('inspect')) {
                $this->renderInspection($importer->inspect($package));

                return self::SUCCESS;
            }

            if ($mode === FacilityMigrationImporter::MODE_FRESH && ! $dryRun) {
                $this->warn('Fresh mode DELETES every existing facility, branch and their image files.');
                $this->warn('This cannot be undone by a rollback — the image files go too.');
                if (! $this->confirm('Continue?', false)) {
                    $this->line('Aborted.');

                    return self::SUCCESS;
                }
            }

            $this->info($dryRun ? 'Dry run — nothing will be written.' : 'Importing…');

            $bar = null;
            $result = $importer->import($package, [
                'mode' => $mode,
                'dry_run' => $dryRun,
                'skip_media' => (bool) $this->option('skip-media'),
                'prune_missing' => (bool) $this->option('prune-missing'),
                'media_path' => $this->option('media'),
                'chunk_size' => (int) $this->option('chunk'),
                'on_progress' => function (array $progress) use (&$bar) {
                    $bar ??= $this->output->createProgressBar($progress['total']);
                    $bar->setProgress($progress['processed']);
                    if ($progress['done']) {
                        $bar->finish();
                        $this->newLine();
                    }
                },
            ]);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->line('Source site: '.($result['source']['app_url'] ?? 'unknown'));
        $this->line('Package built: '.($result['generated_at'] ?? 'unknown'));
        $this->newLine();

        $rows = [];
        foreach ($result['stats'] as $key => $value) {
            $rows[] = [str_replace('_', ' ', $key), $value];
        }
        $this->table(['What', 'Count'], $rows ?: [['nothing', 0]]);

        if (! empty($result['warnings'])) {
            $this->newLine();
            $this->warn(count($result['warnings']).' warning(s):');
            foreach (array_slice($result['warnings'], 0, 30) as $warning) {
                $this->line('  - '.$warning);
            }
            if (count($result['warnings']) > 30) {
                $this->line('  … and '.(count($result['warnings']) - 30).' more.');
            }
        }

        if ($dryRun) {
            $this->newLine();
            $this->info('Dry run complete — re-run without --dry-run to apply.');
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $info
     */
    private function renderInspection(array $info): void
    {
        $this->line('Format:       '.$info['format'].' v'.$info['format_version']);
        $this->line('Generated:    '.$info['generated_at']);
        $this->line('Source site:  '.($info['source']['app_url'] ?? 'unknown'));
        $this->line('Images bundled: '.(($info['options']['include_media_files'] ?? false) ? 'yes' : 'NO — supply --media'));
        $this->newLine();

        $rows = [];
        foreach ($info['counts'] as $key => $value) {
            $rows[] = [str_replace('_', ' ', $key), $value];
        }
        $this->table(['What', 'Count'], $rows);

        if (! empty($info['sample'])) {
            $this->newLine();
            $this->line('First facilities in the package:');
            $this->table(
                ['Slug', 'Name', 'Branches', 'Managers', 'Media', 'Offers'],
                array_map(
                    fn ($s) => [$s['slug'], $s['name'], $s['branches'], $s['managers'] ?? 0, $s['media'], $s['offers']],
                    $info['sample']
                )
            );
        }
    }
}

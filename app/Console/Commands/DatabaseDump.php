<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Symfony\Component\Process\Process;

class DatabaseDump extends Command
{
    protected $signature = 'db:dump
        {--output= : Directory for the dump, or a full *.sql.gz path (default storage/app/backups)}
        {--connection= : Database connection to dump (default: config("database.default"))}
        {--keep=10 : Keep this many newest dumps in the target directory, prune the rest (0 = keep all)}
        {--web : Also publish the dump under public/ behind a random token and print a download URL}
        {--purge-web : Delete every previously published web download, then exit}';

    protected $description = 'Dump the MySQL database to a gzipped .sql.gz file you can pull with scp.';

    /**
     * Directory, relative to public/, where --web drops published dumps.
     */
    private const WEB_DIR = 'downloads/db';

    public function handle(): int
    {
        if ($this->option('purge-web')) {
            return $this->purgeWebDownloads();
        }

        $connection = $this->option('connection') ?: config('database.default');
        $config = config("database.connections.{$connection}");

        if (! $config || ($config['driver'] ?? null) !== 'mysql') {
            $this->error("Connection [{$connection}] is not a mysql connection.");

            return self::FAILURE;
        }

        $mysqldump = trim((string) shell_exec('command -v mysqldump 2>/dev/null')) ?: 'mysqldump';
        $gzip = trim((string) shell_exec('command -v gzip 2>/dev/null')) ?: 'gzip';

        $output = $this->option('output');
        $filename = sprintf('%s-%s.sql.gz', $config['database'], Carbon::now()->format('Ymd-His'));

        if ($output && str_ends_with($output, '.gz')) {
            $destination = $output;
        } else {
            $destination = rtrim($output ?: storage_path('app/backups'), '/').'/'.$filename;
        }

        $dir = dirname($destination);
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            $this->error("Could not create directory: {$dir}");

            return self::FAILURE;
        }

        // Credentials go in a 0600 temp file so they never show up in the process list.
        $defaultsFile = tempnam(sys_get_temp_dir(), 'dbdump');
        chmod($defaultsFile, 0600);
        file_put_contents($defaultsFile, sprintf(
            "[client]\nhost=\"%s\"\nport=%d\nuser=\"%s\"\npassword=\"%s\"\n",
            $config['host'] ?? '127.0.0.1',
            (int) ($config['port'] ?? 3306),
            addcslashes((string) ($config['username'] ?? ''), '"\\'),
            addcslashes((string) ($config['password'] ?? ''), '"\\'),
        ));

        $command = sprintf(
            'set -o pipefail; %s --defaults-extra-file=%s --single-transaction --quick --no-tablespaces '
            .'--routines --events --default-character-set=utf8mb4 %s | %s -9 > %s',
            escapeshellarg($mysqldump),
            escapeshellarg($defaultsFile),
            escapeshellarg((string) $config['database']),
            escapeshellarg($gzip),
            escapeshellarg($destination),
        );

        $this->info("Dumping [{$config['database']}] → {$destination}");

        try {
            $process = new Process(['bash', '-c', $command], base_path(), null, null, 3600);
            $process->run();

            if (! $process->isSuccessful()) {
                @unlink($destination);
                $this->error('mysqldump failed: '.trim($process->getErrorOutput() ?: $process->getOutput()));

                return self::FAILURE;
            }
        } finally {
            @unlink($defaultsFile);
        }

        if (! is_file($destination) || filesize($destination) === 0) {
            @unlink($destination);
            $this->error('Dump produced an empty file.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Dump written: '.$destination);
        $this->line('Size: '.$this->humanSize(filesize($destination)));

        $this->pruneOldDumps($dir, (int) $this->option('keep'));

        if ($this->option('web')) {
            $this->publishToWeb($destination);
        } else {
            $this->newLine();
            $this->line('Pull it down with scp:');
            $this->line('  scp '.get_current_user().'@card.deilar.com:'.$destination.' .');
        }

        $this->newLine();
        $this->line('Restore elsewhere with:');
        $this->line('  gunzip < '.basename($destination).' | mysql <database>');

        return self::SUCCESS;
    }

    private function publishToWeb(string $dump): void
    {
        $token = bin2hex(random_bytes(16));
        $dir = public_path(self::WEB_DIR.'/'.$token);

        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            $this->error("Could not create web directory: {$dir}");

            return;
        }

        $target = $dir.'/'.basename($dump);

        if (! copy($dump, $target)) {
            $this->error('Could not copy the dump into public/.');

            return;
        }
        @chmod($target, 0644);

        $url = rtrim((string) config('app.url'), '/').'/'.self::WEB_DIR.'/'.$token.'/'.rawurlencode(basename($dump));

        $this->newLine();
        $this->info('Download link (anyone with the URL can fetch the full database):');
        $this->line('  '.$url);
        $this->newLine();
        $this->line('Delete it again when done:');
        $this->line('  bin/artisan db:dump --purge-web');
    }

    private function purgeWebDownloads(): int
    {
        $base = public_path(self::WEB_DIR);

        if (! is_dir($base)) {
            $this->info('Nothing published — '.$base.' does not exist.');

            return self::SUCCESS;
        }

        $removed = 0;
        foreach (glob($base.'/*') ?: [] as $entry) {
            if (is_dir($entry)) {
                foreach (glob($entry.'/*') ?: [] as $file) {
                    @unlink($file);
                }
                @rmdir($entry);
                $removed++;
            } else {
                @unlink($entry);
                $removed++;
            }
        }

        $this->info("Removed {$removed} published download(s) from {$base}.");

        return self::SUCCESS;
    }

    private function pruneOldDumps(string $dir, int $keep): void
    {
        if ($keep <= 0) {
            return;
        }

        $dumps = glob(rtrim($dir, '/').'/*.sql.gz') ?: [];
        if (count($dumps) <= $keep) {
            return;
        }

        usort($dumps, fn ($a, $b) => filemtime($b) <=> filemtime($a));

        foreach (array_slice($dumps, $keep) as $old) {
            @unlink($old);
            $this->line('Pruned old dump: '.basename($old));
        }
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

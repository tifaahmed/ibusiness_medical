<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Reads the permanent `storage/logs/failures.log` back as a grouped summary:
 * one row per kind of failure (same status + route + message), with how often
 * it happened and when it was last seen. Read-only — it never edits the file.
 *
 * By default it shows what happened since the last `--mark-reviewed` (or the
 * last 7 days if nothing was ever marked), so a weekly look shows only the new.
 */
class ShowFailures extends Command
{
    protected $signature = 'logs:failures
        {--days= : Only the last N days (ignores the review mark)}
        {--all : Everything in the file}
        {--status= : Only this HTTP status, e.g. 500 or 422}
        {--limit=30 : Groups to show}
        {--examples : Print the latest full entry of each group}
        {--mark-reviewed : Remember now as reviewed; the next run starts from here}';

    protected $description = 'Summarise the permanent failure log (API 4xx/5xx, app crash reports, server errors)';

    public function handle(): int
    {
        $path = storage_path('logs/failures.log');
        $markPath = storage_path('logs/failures.reviewed');

        if ($this->option('all')) {
            $since = null;
        } elseif ($this->option('days')) {
            $since = now()->subDays((int) $this->option('days'));
        } elseif (is_file($markPath)) {
            $since = Carbon::parse(trim((string) file_get_contents($markPath)));
        } else {
            $since = now()->subDays(7);
        }

        $groups = [];
        $total = 0;

        if (is_file($path)) {
            $handle = fopen($path, 'r');
            while (($line = fgets($handle)) !== false) {
                $entry = json_decode($line, true);
                if (! is_array($entry) || ! isset($entry['datetime'])) {
                    continue;
                }
                $at = Carbon::parse($entry['datetime'])->setTimezone(config('app.timezone'));
                if ($since && $at->lt($since)) {
                    continue;
                }
                $ctx = $entry['context'] ?? [];
                $status = $ctx['status'] ?? null;
                if ($this->option('status') && (string) $status !== (string) $this->option('status')) {
                    continue;
                }

                $kind = $ctx['kind'] ?? 'server';
                $where = $ctx['route'] ?? $ctx['path'] ?? '';
                $why = $this->reason($entry, $ctx);
                $key = md5($kind.'|'.$status.'|'.($ctx['method'] ?? '').'|'.$where.'|'.$why);

                $groups[$key] ??= [
                    'kind' => $kind,
                    'status' => $status ?? $entry['level_name'] ?? '',
                    'where' => trim(($ctx['method'] ?? '').' '.$where),
                    'why' => $why,
                    'count' => 0,
                    'first' => $at,
                ];
                $groups[$key]['count']++;
                $groups[$key]['last'] = $at;
                $groups[$key]['example'] = $entry;
                $total++;
            }
            fclose($handle);
        }

        $this->line('Failures '.($since ? 'since '.$since->format('Y-m-d H:i') : '(all time)').": {$total} in ".count($groups).' groups');

        if ($groups) {
            uasort($groups, fn ($a, $b) => [$this->rank($b['status']), $b['count']] <=> [$this->rank($a['status']), $a['count']]);
            $shown = array_slice($groups, 0, (int) $this->option('limit'));

            $this->table(['Kind', 'Status', 'Where', 'Why', 'Count', 'Last seen'], array_map(fn ($g) => [
                $g['kind'], $g['status'], mb_strimwidth($g['where'], 0, 50, '…'), mb_strimwidth($g['why'], 0, 70, '…'), $g['count'], $g['last']->format('m-d H:i'),
            ], $shown));

            if ($this->option('examples')) {
                foreach ($shown as $g) {
                    $this->line(json_encode($g['example'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
                }
            }
        }

        if ($this->option('mark-reviewed')) {
            file_put_contents($markPath, now()->toIso8601String());
            $this->info('Marked as reviewed: the next run starts from now.');
        }

        return self::SUCCESS;
    }

    private function reason(array $entry, array $ctx): string
    {
        if (! empty($ctx['errors']) && is_array($ctx['errors'])) {
            return collect($ctx['errors'])->map(fn ($m, $field) => $field.': '.(is_array($m) ? ($m[0] ?? '') : $m))->implode('; ');
        }

        return (string) ($ctx['reason'] ?? $ctx['exception_message'] ?? $ctx['message'] ?? $entry['message'] ?? '');
    }

    // 5xx and server errors first, then the rest.
    private function rank(mixed $status): int
    {
        return is_numeric($status) ? ((int) $status >= 500 ? 2 : 1) : 3;
    }
}

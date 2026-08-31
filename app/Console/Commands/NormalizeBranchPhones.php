<?php

namespace App\Console\Commands;

use App\Models\FacilityBranch;
use App\Support\PhoneNumbers;
use Illuminate\Console\Command;

class NormalizeBranchPhones extends Command
{
    /**
     * @var string
     */
    protected $signature = 'facilities:normalize-branch-phones {--dry-run : Show what would change without saving}';

    /**
     * @var string
     */
    protected $description = 'Split combined branch phone strings (e.g. "011.../022...") into one number per entry';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $changed = 0;
        $stillTooLong = [];

        FacilityBranch::query()
            ->select(['id', 'facility_id', 'name', 'phone'])
            ->whereNotNull('phone')
            ->chunkById(200, function ($branches) use (&$changed, &$stillTooLong, $dryRun) {
                foreach ($branches as $branch) {
                    $current = is_array($branch->phone) ? $branch->phone : [(string) $branch->phone];
                    $normalized = PhoneNumbers::split($current);

                    if ($normalized === array_values($current)) {
                        continue;
                    }

                    $label = "#{$branch->id} (facility {$branch->facility_id})";
                    $this->line("  {$label}");
                    $this->line('    from: '.json_encode($current, JSON_UNESCAPED_UNICODE));
                    $this->line('    to:   '.json_encode($normalized, JSON_UNESCAPED_UNICODE));

                    foreach ($normalized as $number) {
                        if (mb_strlen($number) > PhoneNumbers::MAX_LENGTH) {
                            $stillTooLong[] = "{$label}: \"{$number}\"";
                        }
                    }

                    if (! $dryRun) {
                        $branch->phone = $normalized ?: null;
                        $branch->save();
                    }

                    $changed++;
                }
            });

        $this->newLine();
        $this->info(($dryRun ? '[dry run] ' : '')."{$changed} branch(es) ".($dryRun ? 'would be' : 'were').' updated.');

        if ($stillTooLong !== []) {
            $this->warn('These numbers are still over '.PhoneNumbers::MAX_LENGTH.' chars and need a manual fix in the admin form:');
            foreach ($stillTooLong as $entry) {
                $this->warn("  - {$entry}");
            }
        }

        return self::SUCCESS;
    }
}

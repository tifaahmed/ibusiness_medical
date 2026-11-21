<?php

namespace App\Console\Commands;

use App\Models\LoyaltyTransaction;
use App\Support\LoyaltyExpiry;
use Illuminate\Console\Command;

class ExpireLoyaltyPoints extends Command
{
    protected $signature = 'loyalty:expire-points
        {--force : run even if the admin switch is off}
        {--dry-run : only count, book nothing}
        {--chunk=200 : members handled per group}
        {--pause=2 : seconds to rest between groups, so the server is not worn out}';

    protected $description = 'Expire loyalty points older than the admin-set number of months (oldest points are spent first), in small groups with a rest between them';

    public function handle(): int
    {
        if (! LoyaltyExpiry::enabled() && ! $this->option('force')) {
            $this->info('Loyalty points expiry is switched off in /admin/setting.');

            return self::SUCCESS;
        }

        $chunk = max(1, (int) $this->option('chunk'));
        $pause = max(0, (int) $this->option('pause'));
        $dry = (bool) $this->option('dry-run');
        $cutoff = now()->subMonthsNoOverflow(LoyaltyExpiry::months());
        $members = 0;
        $points = 0;
        $groups = 0;

        // Only members with an earning old enough to matter are looked at (the ledger's own index does the narrowing), by id so a
        // group never repeats or skips a member while the table changes.
        $ids = fn () => LoyaltyTransaction::query()->where('points', '>', 0)
            ->whereRaw('COALESCE(answered_at, created_at) <= ?', [$cutoff])->select('user_id')->distinct();

        $ids()->orderBy('user_id')->pluck('user_id')->chunk($chunk)->each(function ($group) use (&$members, &$points, &$groups, $dry, $cutoff, $pause): void {
            if ($groups++ > 0 && $pause > 0) {
                sleep($pause); // rest between groups
            }

            foreach ($group as $userId) {
                $old = $dry
                    ? (int) collect(LoyaltyExpiry::lots((int) $userId))->filter(fn ($lot) => $lot['earned_at']->lessThanOrEqualTo($cutoff))->sum('remaining')
                    : LoyaltyExpiry::expireFor((int) $userId);
                if ($old > 0) {
                    $members++;
                    $points += $old;
                }
            }
            $this->line("group {$groups} done ({$members} member(s) so far)");
        });

        $this->info(($dry ? 'Would expire ' : 'Expired ')."{$points} point(s) of {$members} member(s) in {$groups} group(s).");

        return self::SUCCESS;
    }
}

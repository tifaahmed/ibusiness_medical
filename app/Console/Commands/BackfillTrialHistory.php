<?php

namespace App\Console\Commands;

use App\Models\Membership;
use App\Models\MembershipTrial;
use Illuminate\Console\Command;

/**
 * One-off: every card that is on a trial but has no history yet gets one `started` row (its trial as it stands now).
 * Re-runnable (skips cards that already have rows) and add-only. `--dry` first.
 */
class BackfillTrialHistory extends Command
{
    protected $signature = 'members:backfill-trial-history {--dry : Show what would be written, write nothing}';

    protected $description = 'Give every card already on a trial a first row in its trial history';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry');
        $n = 0;

        Membership::query()->where('plan', Membership::PLAN_TRIAL)
            ->whereNotIn('id', MembershipTrial::query()->select('membership_id'))
            ->orderBy('id')
            ->chunkById(500, function ($cards) use ($dry, &$n) {
                foreach ($cards as $m) {
                    $start = ($m->trial_started_at ?? $m->registration_date ?? $m->created_at)?->copy()->startOfDay();
                    $end = $m->trialEndsOn()?->copy()->startOfDay();
                    $n++;
                    if ($dry) {
                        continue;
                    }
                    MembershipTrial::query()->create([
                        'membership_id' => $m->id,
                        'event' => MembershipTrial::EVENT_STARTED,
                        'starts_on' => $start?->toDateString(),
                        'ends_on' => $end?->toDateString(),
                        'expires_on' => $m->expiration_date?->toDateString(),
                        'days' => ($start && $end) ? (int) max(0, $start->diffInDays($end)) : null,
                        'note' => 'backfill',
                    ]);
                }
            });

        $this->info(($dry ? '[DRY] ' : '')."Trial history rows ".($dry ? 'to write' : 'written').": {$n}");

        return self::SUCCESS;
    }
}

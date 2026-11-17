<?php

namespace App\Console\Commands;

use App\Models\MemberLog;
use App\Models\Membership;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * One-off, owner's request 2026-11-17: every COMPANY primary card, and every relative of a company primary, whose own
 * expiry is less than a month away (and not yet past) takes that expiry date as its trial end date. A company primary
 * also becomes a trial account (plan = trial); a relative keeps plan = relative and follows its primary's card.
 *
 * Re-runnable and non-destructive: a card that already has a trial_ends_at is skipped, nothing is deleted, each change
 * writes a member_logs row with the old values. Always `--dry` first, and take a mysqldump before a real run.
 */
class BackfillCompanyTrialDates extends Command
{
    protected $signature = 'members:backfill-company-trial {--dry : Show what would change, write nothing}';

    protected $description = 'Company cards (and their relatives) expiring within a month: copy the expiry into trial_ends_at, primaries become trial';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry');
        $today = now()->toDateString();
        $limit = now()->addMonthNoOverflow()->toDateString();

        $window = fn ($q) => $q->whereNull('trial_ends_at')
            ->where('membership_number', 'not like', 'REG-%')
            ->where('expiration_date', '>', $today)
            ->where('expiration_date', '<', $limit);

        $primaries = Membership::query()->where('member_kind', Membership::KIND_COMPANY)
            ->where('member_type', '!=', Membership::TYPE_RELATIVE)
            ->tap($window)->orderBy('id')->get(['id', 'membership_number', 'plan', 'expiration_date']);

        $relatives = Membership::query()->where('member_type', Membership::TYPE_RELATIVE)
            ->whereHas('parent', fn ($p) => $p->where('member_kind', Membership::KIND_COMPANY))
            ->tap($window)->orderBy('id')->get(['id', 'membership_number', 'plan', 'expiration_date']);

        $this->info(($dry ? '[DRY] ' : '').'Company primaries: '.$primaries->count().' · relatives of company primaries: '.$relatives->count().' · window: '.$today.' < expiry < '.$limit);

        foreach ($primaries->concat($relatives) as $m) {
            $isRelative = $relatives->contains('id', $m->id);
            $date = $m->expiration_date->toDateString();
            $this->line(sprintf('  #%d %s %s plan=%s -> %s trial_ends_at=%s', $m->id, $m->membership_number, $isRelative ? '(relative)' : '(primary)', $m->plan, $isRelative ? $m->plan : Membership::PLAN_TRIAL, $date));

            if ($dry) {
                continue;
            }

            try {
                $new = ['trial_ends_at' => $date];
                if (! $isRelative) {
                    $new['plan'] = Membership::PLAN_TRIAL;
                }
                // Query-builder update on purpose: only these columns move, no model guards or events run.
                Membership::query()->whereKey($m->id)->update($new);
                MemberLog::record($m->id, null, MemberLog::ACTION_UPDATED, ['plan' => $m->plan, 'trial_ends_at' => null], $new + ['reason' => 'backfill_company_trial']);
            } catch (\Throwable $e) {
                Log::error('members:backfill-company-trial failed for a card', ['membership_id' => $m->id, 'message' => $e->getMessage()]);
                $this->error('  failed for #'.$m->id.': '.$e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}

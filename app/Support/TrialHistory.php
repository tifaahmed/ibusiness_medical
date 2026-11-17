<?php

namespace App\Support;

use App\Models\Membership;
use App\Models\MembershipTrial;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Writes the trial history. Called from the Membership `saved` hook, so every path that starts, extends or ends a
 * trial (admin form, app, website, payment) is recorded without each one remembering to. A failed write never fails the save.
 */
final class TrialHistory
{
    public static function afterSave(Membership $m): void
    {
        try {
            $wasTrial = $m->wasRecentlyCreated ? false : self::originalPlan($m) === Membership::PLAN_TRIAL;
            $isTrial = $m->plan === Membership::PLAN_TRIAL;
            $oldEnd = $wasTrial ? ($m->getOriginal('trial_ends_at') ?? $m->getOriginal('expiration_date')) : null;
            $newEnd = $m->trialEndsOn();

            if ($isTrial && ! $wasTrial) {
                self::write($m, MembershipTrial::EVENT_STARTED, null, null);

                return;
            }

            if ($wasTrial && ! $isTrial) {
                self::write($m, MembershipTrial::EVENT_ENDED, $oldEnd, $m->getOriginal('expiration_date'), $oldEnd);

                return;
            }

            if ($isTrial && $wasTrial && ($m->wasChanged('trial_ends_at') || $m->wasChanged('expiration_date')) && $newEnd !== null) {
                $oldDate = $oldEnd?->toDateString();
                $newDate = $newEnd->toDateString();
                $expiryMoved = $m->wasChanged('expiration_date');
                if ($oldDate === $newDate && ! $expiryMoved) {
                    return;
                }
                $event = $oldDate !== null && $newDate < $oldDate ? MembershipTrial::EVENT_SHORTENED : MembershipTrial::EVENT_EXTENDED;
                self::write($m, $event, $oldEnd, $m->getOriginal('expiration_date'));
            }
        } catch (\Throwable $e) {
            Log::warning('Could not write the trial history.', ['membership_id' => $m->id, 'message' => $e->getMessage()]);
        }
    }

    private static function originalPlan(Membership $m): ?string
    {
        return $m->wasChanged('plan') ? $m->getOriginal('plan') : $m->plan;
    }

    private static function write(Membership $m, string $event, $prevEnd, $prevExpiry, $endOverride = null): void
    {
        $start = ($m->trial_started_at ?? $m->registration_date ?? $m->created_at)?->copy()->startOfDay();
        $end = $endOverride ?? $m->trialEndsOn();
        $end = $end?->copy()->startOfDay();

        MembershipTrial::query()->create([
            'membership_id' => $m->id,
            'event' => $event,
            'starts_on' => $start?->toDateString(),
            'ends_on' => $end?->toDateString(),
            'previous_ends_on' => $prevEnd?->toDateString(),
            'expires_on' => $m->expiration_date?->toDateString(),
            'previous_expires_on' => $prevExpiry?->toDateString(),
            'days' => ($start && $end) ? (int) max(0, $start->diffInDays($end)) : null,
            'admin_id' => Auth::id(),
        ]);
    }
}

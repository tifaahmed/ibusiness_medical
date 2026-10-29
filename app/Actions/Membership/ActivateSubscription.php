<?php

namespace App\Actions\Membership;

use App\Mail\SubscriptionReceiptMail;
use App\Models\MemberLog;
use App\Models\MemberPayment;
use App\Models\Membership;
use App\Models\MembershipSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Turns an accepted payment into card time.
 *
 * The ONLY place a subscription extends a card — called by a verified Paymob
 * callback, or by an admin confirming a receipt. Idempotent: an accepted
 * subscription is never applied twice, so a repeated callback cannot grant two
 * periods for one payment.
 *
 * A trial card becomes a full one (and is paid for from today, not from the end
 * of the trial: the unused trial days are the member's gain, not extra). A
 * renewal before expiry stacks on the remaining time.
 */
class ActivateSubscription
{
    public function handle(MembershipSubscription $subscription, ?int $adminId = null, ?Request $request = null): bool
    {
        $activated = DB::transaction(function () use ($subscription, $adminId) {
            $locked = MembershipSubscription::query()->lockForUpdate()->findOrFail($subscription->id);

            if ($locked->status === MembershipSubscription::STATUS_ACCEPTED) {
                return false;
            }

            $membership = Membership::withTrashed()->lockForUpdate()->findOrFail($locked->membership_id);
            $wasTrial = $membership->isTrial();
            $wasRelative = $membership->isRelative();

            $from = ! $wasTrial && $membership->expiration_date?->isFuture()
                ? $membership->expiration_date->copy()
                : now();
            $until = $from->copy()->addMonthsNoOverflow($locked->months);

            /*
             * A relative who pays becomes a PRIMARY member: no longer on somebody else's card, and free to add their own
             * family. The card they left is remembered (`former_parent_membership_id`) so they keep seeing it.
             */
            if ($wasRelative) {
                $membership->forceFill([
                    'member_type' => Membership::TYPE_PRIMARY,
                    'former_parent_membership_id' => $membership->parent_membership_id,
                    'parent_membership_id' => null,
                ]);
            }

            $membership->forceFill([
                'plan' => Membership::PLAN_FULL,
                'is_active' => true,
                'is_visible' => true,
                'is_paid' => true,
                'payment_type' => $locked->method,
                'expiration_date' => $until,
                'completed_at' => $membership->completed_at ?? now(),
            ])->save();

            $locked->forceFill([
                'status' => MembershipSubscription::STATUS_ACCEPTED,
                'paid_at' => $locked->paid_at ?? now(),
                'confirmed_by' => $adminId,
                'confirmed_at' => $adminId ? now() : null,
                'rejection_reason' => null,
            ])->save();

            MemberPayment::query()->create([
                'membership_id' => $membership->id,
                'amount' => $locked->amount,
                'months_paid' => $locked->months,
                'from_date' => $from->toDateString(),
                'to_date' => $until->toDateString(),
                'notes' => 'Subscription #'.$locked->id.' ('.$locked->method.')'.($adminId ? ' confirmed by admin #'.$adminId : ''),
                'type' => $locked->method,
            ]);

            MemberLog::record(
                membershipId: $membership->id,
                adminId: $adminId,
                action: MemberLog::ACTION_UPDATED,
                oldValues: ['plan' => $wasTrial ? Membership::PLAN_TRIAL : ($wasRelative ? Membership::PLAN_RELATIVE : Membership::PLAN_FULL)],
                newValues: [
                    'plan' => Membership::PLAN_FULL,
                    'subscription_id' => $locked->id,
                    'method' => $locked->method,
                    'amount' => (float) $locked->amount,
                    'expiration_date' => $until->toDateString(),
                ],
                request: null,
            );

            $subscription->setRawAttributes($locked->getAttributes(), true);

            return true;
        });

        if ($activated) {
            $this->emailReceipt($subscription->fresh('membership.user'), $request);
        }

        return $activated;
    }

    private function emailReceipt(MembershipSubscription $subscription, ?Request $request): void
    {
        $to = filled($subscription->email) ? $subscription->email : $subscription->membership?->user?->email;

        if (! filled($to) || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        try {
            Mail::to($to)->send(new SubscriptionReceiptMail($subscription));
            $subscription->forceFill(['receipt_emailed_at' => now()])->save();
        } catch (Throwable $exception) {
            Log::error('Subscription receipt email could not be sent.', [
                'subscription_id' => $subscription->id,
                'to' => $to,
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);
        }
    }
}

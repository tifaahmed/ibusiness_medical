<?php

namespace App\Support;

use App\Models\Membership;
use App\Models\MembershipSubscription;
use App\Models\Referral;
use App\Models\ReferralReward;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Read-only referral picture for the admin member page: the code this member ENTERED (whose card, when, the discount it gave,
 * the subscription it was used on, what the referrer earned from it), the referrer's own details, and the members this
 * member brought in themselves. Nothing here writes.
 */
class AdminMemberReferral
{
    public static function summary(User $user): ?array
    {
        try {
            $referral = Referral::query()->with(['rewards'])->where('referred_user_id', $user->id)->first();
            $brought = Referral::query()->where('referrer_user_id', $user->id)->count();

            if (! $referral && $brought === 0) {
                return null;
            }

            return [
                'brought_by' => $referral ? self::broughtBy($referral) : null,
                'referrer' => $referral ? self::referrer($referral) : null,
                'my_referrals' => [
                    'count' => $brought,
                    'earned' => round((float) ReferralReward::query()->where('referrer_user_id', $user->id)->where('status', 'paid')->sum('amount'), 2),
                ],
            ];
        } catch (Throwable $e) {
            Log::error('Admin member referral summary failed', ['user_id' => $user->id, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return null;
        }
    }

    private static function broughtBy(Referral $r): array
    {
        $sub = $r->subscription_id ? MembershipSubscription::query()->find($r->subscription_id) : null;

        return [
            'code' => $r->referrer_number,
            'source' => $r->source,
            'entered_at' => $r->created_at?->format('Y-m-d H:i'),
            'discount' => (float) $r->discount_amount,
            'trial_started_at' => $r->trial_started_at?->format('Y-m-d H:i'),
            'subscribed_at' => $r->subscribed_at?->format('Y-m-d H:i'),
            'subscription' => $sub ? [
                'id' => $sub->id,
                'amount' => (float) $sub->amount,
                'months' => (int) $sub->months,
                'method' => $sub->method,
                'channel' => $sub->channel,
                'status' => $sub->status,
                'paid_at' => $sub->paid_at?->format('Y-m-d H:i'),
            ] : null,
            'rewards' => $r->rewards->sortBy('id')->values()->map(fn (ReferralReward $w) => [
                'id' => $w->id,
                'kind' => $w->kind,
                'plan' => $w->plan,
                'base_amount' => (float) $w->base_amount,
                'amount' => (float) $w->amount,
                'status' => $w->status,
                'note' => $w->note,
                'at' => $w->created_at?->format('Y-m-d H:i'),
            ])->all(),
            'referrer_earned' => round((float) $r->rewards->where('status', 'paid')->sum('amount'), 2),
        ];
    }

    private static function referrer(Referral $r): ?array
    {
        // A referrer deleted after the code was used still shows (flagged), so the trail is never lost.
        $owner = User::withTrashed()->find($r->referrer_user_id);
        $card = $r->referrer_membership_id ? Membership::withTrashed()->find($r->referrer_membership_id) : null;

        if (! $owner) {
            return ['missing' => true, 'number' => $r->referrer_number];
        }

        return [
            'missing' => false,
            'deleted' => $owner->trashed() || (bool) $card?->trashed(),
            'deleted_at' => ($owner->deleted_at ?? $card?->deleted_at)?->format('Y-m-d H:i'),
            'id' => $owner->id,
            'slug' => $owner->slug,
            'name' => $owner->name,
            'phone' => $owner->phone,
            'email' => $owner->email,
            'number' => $card?->membership_number ?? $r->referrer_number,
            'card_state' => $card?->state(),
            'plan' => $card?->plan,
            'member_type' => $card?->member_type,
            'expiration_date' => $card?->expiration_date ? \Illuminate\Support\Carbon::parse($card->expiration_date)->format('Y-m-d') : null,
            'joined_at' => $owner->created_at?->format('Y-m-d'),
            'referred_count' => Referral::query()->where('referrer_user_id', $owner->id)->count(),
            'total_earned' => round((float) ReferralReward::query()->where('referrer_user_id', $owner->id)->where('status', 'paid')->sum('amount'), 2),
            'wallet_balance' => WalletTransaction::balanceOf($owner->id),
        ];
    }
}

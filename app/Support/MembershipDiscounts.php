<?php

namespace App\Support;

use App\Models\LoyaltyTransaction;
use App\Models\Membership;
use App\Models\MembershipSubscription;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

/**
 * Loyalty points and wallet money as a discount on a MEMBERSHIP payment (any plan, any method).
 *
 * Neither is ever spent unless the member presses for it (`use_points`, `use_wallet`). Points come first, in whole pounds, then the
 * wallet pays what is left. The subscription's `amount` becomes what is still due in REAL money (that is what Paymob charges and
 * what a receipt must cover); `points_used`, `points_amount` and `wallet_amount` keep what was taken off. A payment that is
 * rejected, cancelled or refunded gives both back once ({@see self::giveBack()}). The backend decides every figure; clients only draw them.
 * Unlike shop orders, points may be spent here on a lapsed card too: renewing is exactly what they are for.
 */
final class MembershipDiscounts
{
    /** The plan's price for this card after the referral discount, or null when the plan is not on sale. @return ?array{key: string, price: float, months: int} */
    public static function plan(Membership $membership, ?string $planKey): ?array
    {
        $plan = MembershipPlan::find($planKey, $membership->isRelative() ? Membership::KIND_INDIVIDUAL : $membership->kind());
        if ($plan === null) {
            return null;
        }

        $plan['price'] = Referrals::apply($plan['price'], $membership, $plan['key']);

        return $plan;
    }

    /**
     * What a press would take off and what is left to pay, without writing anything.
     *
     * @return array{price: float, points_used: int, points_amount: float, wallet_amount: float, due: float, points_per_egp: float, points_available: float, wallet_balance: float}
     */
    public static function quote(User $user, float $price, bool $usePoints, bool $useWallet): array
    {
        $balance = LoyaltyTransaction::balanceOf($user->id);
        $pointsQuote = PointsPayments::quote($balance, PointsPayments::eligible([['amount' => $price, 'cap' => null]]));
        $walletBalance = max(0.0, (float) WalletTransaction::balanceOf($user->id));

        $pointsEgp = $usePoints ? (float) $pointsQuote['egp'] : 0.0;
        $pointsUsed = $usePoints ? (int) $pointsQuote['points'] : 0;
        $walletEgp = $useWallet ? round(min($walletBalance, max(0.0, $price - $pointsEgp)), 2) : 0.0;

        return [
            'price' => round($price, 2),
            'points_used' => $pointsUsed,
            'points_amount' => $pointsEgp,
            'wallet_amount' => $walletEgp,
            'due' => round(max(0.0, $price - $pointsEgp - $walletEgp), 2),
            'points_per_egp' => (float) LoyaltyPoints::pointsPerEgp(),
            'points_available' => (float) $pointsQuote['egp'],
            'wallet_balance' => round($walletBalance, 2),
        ];
    }

    /**
     * Writes the pending subscription and takes the points / wallet in ONE transaction (the member row is the lock, so two
     * payments at once cannot spend the same points). The figures are re-counted inside the lock.
     *
     * @param  array{key: string, price: float, months: int}  $plan
     */
    public static function open(Membership $membership, User $user, array $plan, string $method, bool $usePoints, bool $useWallet, array $extra = []): MembershipSubscription
    {
        return DB::transaction(function () use ($membership, $user, $plan, $method, $usePoints, $useWallet, $extra) {
            User::query()->whereKey($user->id)->lockForUpdate()->first();

            $q = self::quote($user, (float) $plan['price'], $usePoints, $useWallet);

            $subscription = $membership->subscriptions()->create($extra + [
                'method' => $q['due'] <= 0 ? MembershipSubscription::METHOD_WALLET : $method,
                'status' => MembershipSubscription::STATUS_PENDING,
                'amount' => $q['due'],
                'months' => $plan['months'],
                'points_used' => $q['points_used'],
                'points_amount' => $q['points_amount'],
                'wallet_amount' => $q['wallet_amount'],
            ]);

            if ($q['points_used'] > 0) {
                LoyaltyTransaction::query()->create([
                    'user_id' => $user->id, 'source' => LoyaltyTransaction::SOURCE_ORDER, 'client_id' => 'subscription:'.$subscription->id,
                    'points' => -$q['points_used'], 'note' => 'membership '.$membership->membership_number, 'answered_at' => now(),
                ]);
            }

            if ($q['wallet_amount'] > 0) {
                WalletTransaction::query()->create([
                    'user_id' => $user->id, 'source' => WalletTransaction::SOURCE_MEMBERSHIP, 'client_id' => 'subscription:'.$subscription->id,
                    'amount' => -$q['wallet_amount'], 'note' => 'membership '.$membership->membership_number,
                ]);
            }

            return $subscription;
        });
    }

    /** Gives the points and the wallet money of a payment that did not go through back, once. Returns whether anything was returned. */
    public static function giveBack(MembershipSubscription $subscription): bool
    {
        if ((int) $subscription->points_used <= 0 && (float) $subscription->wallet_amount <= 0) {
            return false;
        }

        return DB::transaction(function () use ($subscription) {
            $fresh = MembershipSubscription::query()->lockForUpdate()->find($subscription->id);
            $userId = $fresh?->membership?->user_id;
            if ($fresh === null || $userId === null || $fresh->discounts_returned_at !== null) {
                return false;
            }

            if ((int) $fresh->points_used > 0) {
                LoyaltyTransaction::query()->firstOrCreate(
                    ['user_id' => $userId, 'client_id' => 'subscription-refund:'.$fresh->id],
                    ['source' => LoyaltyTransaction::SOURCE_ORDER, 'points' => (int) $fresh->points_used, 'note' => 'refund of membership payment #'.$fresh->id, 'answered_at' => now()],
                );
            }

            if ((float) $fresh->wallet_amount > 0) {
                WalletTransaction::query()->firstOrCreate(
                    ['user_id' => $userId, 'client_id' => 'subscription-refund:'.$fresh->id],
                    ['source' => WalletTransaction::SOURCE_REFUND, 'amount' => (float) $fresh->wallet_amount, 'note' => 'refund of membership payment #'.$fresh->id],
                );
            }

            $fresh->forceFill(['discounts_returned_at' => now()])->saveQuietly();

            return true;
        });
    }
}

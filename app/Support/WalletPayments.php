<?php

namespace App\Support;

use App\Enums\Order\PaymentStatusEnum;
use App\Models\Membership;
use App\Models\MembershipSubscription;
use App\Models\Order;
use App\Models\OrderLog;
use App\Models\Setting;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

/**
 * Paying with the wallet.
 *
 *  - ORDERS: when the buyer is signed in, the wallet is used AUTOMATICALLY to cut what is left to pay. It can only cover the lines of
 *    products the admin ticked `wallet_payable`; delivery and the cash-on-delivery fee are always paid in real money. The order keeps
 *    `wallet_amount` (what the wallet paid) and `amountDue()` (the rest). When nothing is left the order is paid at once.
 *  - MEMBERSHIP: the yearly plan can be bought with the wallet when the whole price is there (switch `wallet_membership_enabled`, default ON).
 *  - REFUNDS: an order that is cancelled or fails after it was sold sends its money back to the WALLET (see `refundOrder`) — the wallet part
 *    and whatever was paid in real money — once only.
 *
 *   wallet_min_topup            the smallest top-up from the app (EGP, default 20)
 *   wallet_membership_enabled   may the wallet buy the yearly membership (default ON)
 */
final class WalletPayments
{
    public const MIN_TOPUP = 'wallet_min_topup';

    public const MEMBERSHIP_ENABLED = 'wallet_membership_enabled';

    public static function minTopup(): float
    {
        return max(1.0, round((float) SiteSettings::get(self::MIN_TOPUP, 20), 2));
    }

    public static function membershipEnabled(): bool
    {
        return (bool) SiteSettings::get(self::MEMBERSHIP_ENABLED, true);
    }

    /**
     * Uses the buyer's wallet on a just-created order (call it inside the order's transaction). Returns what the wallet paid.
     *
     * @param  float  $eligible  the sum of the lines of products the wallet may pay for
     */
    public static function applyToOrder(Order $order, ?User $buyer, float $eligible): float
    {
        if ($buyer === null || $eligible <= 0) {
            return 0.0;
        }

        // The buyer's row is the lock: two orders placed at once cannot spend the same money.
        User::query()->whereKey($buyer->id)->lockForUpdate()->first();

        $amount = round(min(WalletTransaction::balanceOf($buyer->id), $eligible, (float) $order->total_amount), 2);
        if ($amount <= 0) {
            return 0.0;
        }

        WalletTransaction::query()->create([
            'user_id' => $buyer->id, 'source' => WalletTransaction::SOURCE_ORDER, 'client_id' => 'order:'.$order->id,
            'amount' => -$amount, 'note' => 'order '.$order->order_code,
        ]);

        $attributes = ['wallet_amount' => $amount];
        if (round((float) $order->total_amount - $amount, 2) <= 0) {
            // The wallet paid for all of it.
            $attributes += ['payment_status' => PaymentStatusEnum::ACCEPTED, 'total_paid' => $order->total_amount, 'paid_at' => now()];
        }
        $order->forceFill($attributes)->save();

        return $amount;
    }

    /**
     * Sends an order's money back to the wallet: what the wallet paid, plus what was paid in real money and not yet returned.
     * Idempotent (`wallet_refunded_at`), and refuses nothing silently: it returns the pounds credited (0 = nothing to return or done before).
     */
    public static function refundOrder(Order $order, ?string $reason = null): float
    {
        if ($order->user_id === null) {
            return 0.0;
        }

        return DB::transaction(function () use ($order, $reason) {
            $fresh = Order::query()->lockForUpdate()->find($order->id);
            if ($fresh === null || $fresh->wallet_refunded_at !== null) {
                return 0.0;
            }

            $walletPart = (float) $fresh->wallet_amount;
            $realPaid = max(0.0, round((float) $fresh->total_paid - $walletPart - (float) $fresh->refunded_amount, 2));
            $credit = round($walletPart + $realPaid, 2);

            if ($credit <= 0) {
                return 0.0;
            }

            WalletTransaction::query()->create([
                'user_id' => $fresh->user_id, 'source' => WalletTransaction::SOURCE_REFUND, 'client_id' => 'order-refund:'.$fresh->id,
                'amount' => $credit, 'note' => 'refund of order '.$fresh->order_code.($reason ? ' ('.$reason.')' : ''),
            ]);
            $fresh->forceFill(['wallet_refunded_at' => now()])->save();
            OrderLog::record($fresh->id, null, OrderLog::ACTION_PAYMENT_REFUNDED, null, ['to' => 'wallet', 'amount' => $credit, 'wallet_part' => $walletPart, 'real_money_part' => $realPaid, 'reason' => $reason]);

            return $credit;
        });
    }

    /**
     * Buys the yearly membership with the wallet. Needs the whole price in the wallet. Returns the (now accepted) subscription.
     *
     * @return array{ok: bool, reason: ?string, subscription?: MembershipSubscription}  reason: disabled | no_plan | not_enough
     */
    public static function buyMembership(Membership $membership, User $buyer): array
    {
        if (! self::membershipEnabled()) {
            return ['ok' => false, 'reason' => 'disabled'];
        }

        $plan = MembershipPlan::find('year');
        if ($plan === null) {
            return ['ok' => false, 'reason' => 'no_plan'];
        }

        $subscription = DB::transaction(function () use ($membership, $buyer, $plan) {
            User::query()->whereKey($buyer->id)->lockForUpdate()->first();

            if (WalletTransaction::balanceOf($buyer->id) < $plan['price']) {
                return null;
            }

            $subscription = $membership->subscriptions()->create([
                'method' => MembershipSubscription::METHOD_WALLET, 'status' => MembershipSubscription::STATUS_PENDING,
                'amount' => $plan['price'], 'months' => $plan['months'],
            ]);
            WalletTransaction::query()->create([
                'user_id' => $buyer->id, 'source' => WalletTransaction::SOURCE_MEMBERSHIP, 'client_id' => 'subscription:'.$subscription->id,
                'amount' => -$plan['price'], 'note' => 'membership '.$membership->membership_number,
            ]);

            return $subscription;
        });

        if ($subscription === null) {
            return ['ok' => false, 'reason' => 'not_enough'];
        }

        app(\App\Actions\Membership\ActivateSubscription::class)->handle($subscription);

        return ['ok' => true, 'reason' => null, 'subscription' => $subscription->fresh()];
    }

    /** @return array<int, array{slug: string, name: array<string, string>, value: string, value_type: string}> */
    public static function seedRows(): array
    {
        return [
            ['slug' => self::MIN_TOPUP, 'name' => ['en' => 'Smallest wallet top-up from the app (EGP)', 'ar' => 'أقل مبلغ لشحن المحفظة من التطبيق (ج.م)'], 'value' => '20', 'value_type' => Setting::TYPE_NUMBER],
            ['slug' => self::MEMBERSHIP_ENABLED, 'name' => ['en' => 'Let members buy the yearly membership with their wallet', 'ar' => 'السماح بشراء العضوية السنوية من المحفظة'], 'value' => '1', 'value_type' => Setting::TYPE_BOOLEAN],
        ];
    }
}

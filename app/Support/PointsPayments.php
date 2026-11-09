<?php

namespace App\Support;

use App\Models\LoyaltyTransaction;
use App\Models\Order;
use App\Models\OrderLog;
use App\Models\User;

/**
 * Loyalty points as a discount at checkout.
 *
 * A signed-in buyer's points cut the price of the products the admin ticked `points_payable` (never delivery or the COD fee).
 * They are worth `loyalty_points_per_egp` points to the pound and are spent in WHOLE pounds. The order keeps `points_used` (points)
 * and `points_amount` (pounds, taken off what is left to pay like `wallet_amount`). The spend is one negative, append-only
 * `loyalty_transactions` row (`source = order`); an order cancelled or failed after the sale gives the points back as a positive row,
 * once (see {@see WalletPayments::refundOrder()}). The backend decides every figure — clients only draw them.
 */
final class PointsPayments
{
    /**
     * The pounds of an order that points may discount, after the caps: each product line is limited to its product's cap
     * (`Product::pointsCap()`: the product's own or the system's), then the whole order to `loyalty_points_max_per_order`.
     * The caps are in POINTS; they turn into pounds at the admin's rate.
     *
     * @param  list<array{amount: float, cap: ?int}>  $lines  one per product that accepts points
     */
    public static function eligible(array $lines): float
    {
        $perEgp = LoyaltyPoints::pointsPerEgp();
        $sum = 0.0;

        foreach ($lines as $line) {
            $amount = max(0.0, (float) $line['amount']);
            $sum += $line['cap'] !== null && $perEgp > 0 ? min($amount, $line['cap'] / $perEgp) : $amount;
        }

        $orderCap = LoyaltyPoints::maxPerOrder();

        return $orderCap !== null && $perEgp > 0 ? min($sum, $orderCap / $perEgp) : $sum;
    }

    /** Whole pounds the buyer's points could take off `$eligible` pounds, and the points that costs. */
    public static function quote(int $balance, float $eligible): array
    {
        $perEgp = LoyaltyPoints::pointsPerEgp();
        if ($perEgp <= 0 || $balance <= 0 || $eligible < 1) {
            return ['egp' => 0, 'points' => 0];
        }

        $egp = (int) floor(min($balance / $perEgp, $eligible));

        return ['egp' => $egp, 'points' => (int) round($egp * $perEgp)];
    }

    /**
     * Spends the buyer's points on a just-created order (call it inside the order's transaction). Returns the pounds taken off.
     *
     * @param  float  $eligible  the sum of the lines of products that accept points
     */
    public static function applyToOrder(Order $order, ?User $buyer, float $eligible): float
    {
        if ($buyer === null || $eligible <= 0) {
            return 0.0;
        }

        // The buyer's row is the lock: two orders placed at once cannot spend the same points.
        User::query()->whereKey($buyer->id)->lockForUpdate()->first();

        $quote = self::quote(LoyaltyTransaction::balanceOf($buyer->id), min($eligible, (float) $order->total_amount));
        if ($quote['egp'] <= 0 || $quote['points'] <= 0) {
            return 0.0;
        }

        LoyaltyTransaction::query()->create([
            'user_id' => $buyer->id, 'source' => LoyaltyTransaction::SOURCE_ORDER, 'client_id' => 'order:'.$order->id,
            'points' => -$quote['points'], 'note' => 'order '.$order->order_code, 'answered_at' => now(),
        ]);

        $attributes = ['points_used' => $quote['points'], 'points_amount' => $quote['egp']];
        if (round((float) $order->total_amount - $quote['egp'], 2) <= 0) {
            $attributes += ['payment_status' => \App\Enums\Order\PaymentStatusEnum::ACCEPTED, 'total_paid' => $order->total_amount, 'paid_at' => now()];
        }
        $order->forceFill($attributes)->save();

        return (float) $quote['egp'];
    }

    /** Gives an order's points back (a positive row). Called from inside {@see WalletPayments::refundOrder()}'s lock, so it runs once. */
    public static function refundOrder(Order $order): int
    {
        $points = (int) $order->points_used;
        if ($points <= 0 || $order->user_id === null) {
            return 0;
        }

        LoyaltyTransaction::query()->firstOrCreate(
            ['user_id' => $order->user_id, 'client_id' => 'order-refund:'.$order->id],
            ['source' => LoyaltyTransaction::SOURCE_ORDER, 'points' => $points, 'note' => 'refund of order '.$order->order_code, 'answered_at' => now()],
        );
        OrderLog::record($order->id, null, OrderLog::ACTION_PAYMENT_REFUNDED, null, ['to' => 'points', 'points' => $points]);

        return $points;
    }
}

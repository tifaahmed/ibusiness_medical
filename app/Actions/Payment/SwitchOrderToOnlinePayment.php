<?php

namespace App\Actions\Payment;

use App\Enums\Order\PaymentTypeEnum;
use App\Models\Order;
use App\Models\OrderLog;
use App\Services\Paymob\PaymobClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The buyer changes their mind: cash on delivery or a wallet transfer becomes
 * paying online, from their own order page.
 *
 * The cash-on-delivery fee only exists for cash on delivery, so it comes off
 * BOTH totals as it goes — the gap between them stays exactly the membership
 * discount, as it was when the order was placed. Nothing else about the money
 * moves. Re-checked under a row lock, so two clicks (or a click racing an
 * admin) cannot switch an order that has meanwhile been paid or shipped.
 *
 * Opening the payment itself is the caller's next step (`StartPaymobPayment`):
 * if the gateway is down the order is simply an unpaid online order, and the
 * ordinary "pay now" retries it.
 */
class SwitchOrderToOnlinePayment
{
    /**
     * @return bool false when the order may not switch (any more)
     */
    public function handle(Order $order, ?Request $request = null): bool
    {
        if (! \App\Support\PaymentMethods::online()) {
            return false;
        }

        return DB::transaction(function () use ($order, $request) {
            /** @var Order $locked */
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            if (! $locked->canSwitchToOnline()) {
                return false;
            }

            $old = [
                'payment_type' => $locked->payment_type->value,
                'cod_fee' => (float) $locked->cod_fee,
                'total_amount' => (float) $locked->total_amount,
                'total_amount_before_discount' => $locked->total_amount_before_discount === null ? null : (float) $locked->total_amount_before_discount,
            ];

            $codFee = (float) $locked->cod_fee;

            $locked->forceFill([
                'payment_type' => PaymentTypeEnum::PAYMOB,
                'cod_fee' => 0,
                'total_amount' => round((float) $locked->total_amount - $codFee, 2),
                'total_amount_before_discount' => $locked->total_amount_before_discount === null
                    ? null
                    : round((float) $locked->total_amount_before_discount - $codFee, 2),
            ])->save();

            OrderLog::record($locked->id, null, OrderLog::ACTION_PAYMENT_TYPE_CHANGED, $old, [
                'payment_type' => PaymentTypeEnum::PAYMOB->value,
                'cod_fee' => 0.0,
                'total_amount' => (float) $locked->total_amount,
                'total_amount_before_discount' => $locked->total_amount_before_discount === null ? null : (float) $locked->total_amount_before_discount,
                'by' => 'buyer',
            ], $request);

            $order->setRawAttributes($locked->getAttributes(), true);

            return true;
        });
    }
}

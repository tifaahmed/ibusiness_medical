<?php

namespace App\Http\Resources\Api\V1\Partner;

use App\Enums\Order\PaymentStatusEnum;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Services\Paymob\PaymobClient;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One order as the buyer's own tracking page shows it.
 *
 * Every line reads out of `order_products` — the snapshot taken when the order
 * was placed — never back through the catalogue. A price that moved since, or
 * a product since deleted, must not change what a placed order says.
 *
 * Deliberately absent: `cost_price` and `profit_price`, and the delivery cost
 * and profit beside them. They are archived for the shop's own reporting, and
 * this resource is read by a storefront that shows it to the buyer — who is
 * owed what they were charged for delivery, not what it cost us.
 *
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'order_code' => $this->order_code,
            'placed_at' => $this->created_at?->toAtomString(),
            'updated_at' => $this->updated_at?->toAtomString(),

            'customer_full_name' => $this->customer_full_name,
            'customer_phone' => $this->customer_phone,
            'customer_address' => $this->customer_address,
            'customer_address_type' => $this->customer_address_type?->value,
            'customer_street' => $this->customer_street,
            'customer_governorate' => $this->customer_governorate,
            'customer_city' => $this->customer_city,
            'customer_building_number' => $this->customer_building_number,
            'customer_apartment_number' => $this->customer_apartment_number,
            'customer_floor_number' => $this->customer_floor_number,
            'customer_special_mark' => $this->customer_special_mark,
            'membership_number' => $this->membership_number,
            'notes' => $this->notes,

            'total_amount' => (float) $this->total_amount,
            'total_amount_before_discount' => $this->total_amount_before_discount === null
                ? null
                : (float) $this->total_amount_before_discount,
            'total_paid' => (float) $this->total_paid,
            /* How the bill was paid: what the wallet covered, and what is (or was) paid in real money. Delivery is always real money. */
            'wallet_amount' => (float) $this->wallet_amount,
            'amount_due' => $this->amountDue(),
            'wallet_refunded' => $this->wallet_refunded_at !== null,
            /* The cash-on-delivery fee already included in `total_amount`; 0 means none, and clients hide it. */
            'cod_fee' => (float) $this->cod_fee,
            'refunded_amount' => (float) $this->refunded_amount,
            /* A paid order that has not started shipping can ask for its money back; an admin decides (never automatic). */
            'can_request_refund' => $this->payment_status === PaymentStatusEnum::ACCEPTED
                && $this->delivery_status === \App\Enums\Order\DeliveryStatusEnum::PENDING
                && ! $this->refundRequested(),
            'refund_requested' => $this->refundRequested(),
            'paid_at' => $this->paid_at?->toAtomString(),
            /* Whether the buyer can still be sent to the gateway for this order. */
            'can_pay_online' => $this->isOnlinePayment()
                && PaymobClient::isConfigured()
                && in_array($this->payment_status, [PaymentStatusEnum::PENDING, PaymentStatusEnum::REJECTED], true),
            /* Whether the buyer may swap cash on delivery / a transfer for paying online — see `Order::canSwitchToOnline()`. */
            'can_switch_to_online' => \App\Support\PaymentMethods::online(\App\Support\PaymentMethods::contextOfOrder($this->resource)) && $this->canSwitchToOnline(),
            /* What delivery was charged at on this order, already included in
               `total_amount`. The buyer's half of the three columns. */
            'delivery_price' => (float) $this->delivery_price,
            /*
             * Why delivery was not charged, when it was not. Sent to the
             * storefront rather than kept for the admin, because the buyer is
             * the person most owed the explanation — a zero delivery line with
             * no reason beside it reads as a mistake somebody might ring up
             * about.
             *
             * Null on every order that was charged for delivery normally, and
             * on every order placed before free delivery existed.
             */
            'delivery_free_reason' => $this->delivery_free_reason,
            /*
             * The basket total that earned it — the figure set in the
             * storefront's own settings, archived here when the order was
             * placed. Present even when the line was NOT crossed, so an order
             * still says what the line was that day.
             */
            'free_delivery_threshold' => $this->free_delivery_threshold === null
                ? null
                : (float) $this->free_delivery_threshold,

            'payment_type' => [
                'value' => $this->payment_type->value,
                'label' => $this->payment_type->label(),
            ],
            'payment_status' => [
                'value' => $this->payment_status->value,
                'label' => $this->payment_status->label(),
            ],
            'delivery_status' => [
                'value' => $this->delivery_status->value,
                'label' => $this->delivery_status->label(),
            ],
            'cancel_reason' => $this->cancel_reason,
            /* Only on the one-order screens: how the payment and delivery statuses moved, with the date of each move. */
            'status_history' => $this->relationLoaded('statusLogs') ? $this->statusLogs->map(function ($log) {
                $isPayment = $log->action === \App\Models\OrderLog::ACTION_PAYMENT_STATUS_CHANGED;
                $key = $isPayment ? 'payment_status' : 'delivery_status';
                $enum = $isPayment ? PaymentStatusEnum::class : \App\Enums\Order\DeliveryStatusEnum::class;
                $state = fn (?string $value) => $value === null ? null : ['value' => $value, 'label' => $enum::tryFrom($value)?->label() ?? $value];

                return [
                    'kind' => $isPayment ? 'payment' : 'delivery',
                    'from' => $state($log->old_values[$key] ?? null),
                    'to' => $state($log->new_values[$key] ?? null),
                    'at' => $log->created_at?->toAtomString(),
                ];
            })->values() : [],

            /*
             * Whether the buyer still owes us proof of a transfer. Answered
             * here rather than left for a storefront to work out from the
             * payment type and an empty list — two consumers deriving it
             * separately is two chances to ask for a receipt twice.
             */
            'awaiting_receipt' => $this->awaitingReceipt(),
            /*
             * Whether MORE may be sent, which stays true for the life of a
             * wallet-transfer order however many it already holds. The
             * storefront keeps its upload box open on this, not on the line
             * above — that one goes false the moment the first file lands.
             */
            'accepts_receipts' => $this->acceptsReceipts(),
            'receipts' => $this->receiptFiles(),

            'items' => $this->products->map(fn (OrderProduct $line) => [
                'id' => $line->id,
                'slug' => $line->slug,
                'name' => $line->name,
                'image' => $line->image,
                'quantity' => $line->quantity,
                'old_price' => $line->old_price === null ? null : (float) $line->old_price,
                'new_price' => $line->new_price === null ? null : (float) $line->new_price,
                'line_total' => (float) $line->line_total,
            ])->all(),
        ];
    }
}

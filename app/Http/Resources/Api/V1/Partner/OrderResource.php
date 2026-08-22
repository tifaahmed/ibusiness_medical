<?php

namespace App\Http\Resources\Api\V1\Partner;

use App\Models\Order;
use App\Models\OrderProduct;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One order as the buyer's own tracking page shows it.
 *
 * Every line reads out of `order_products` — the snapshot taken when the order
 * was placed — never back through the catalogue. A price that moved since, or
 * a product since deleted, must not change what a placed order says.
 *
 * Deliberately absent: `cost_price` and `profit_price`. They are archived on
 * the line for the shop's own reporting, and this resource is read by a
 * storefront that shows it to the buyer.
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
            'membership_number' => $this->membership_number,
            'notes' => $this->notes,

            'total_amount' => (float) $this->total_amount,
            'total_amount_before_discount' => $this->total_amount_before_discount === null
                ? null
                : (float) $this->total_amount_before_discount,
            'total_paid' => (float) $this->total_paid,

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

            /*
             * Whether the buyer still owes us proof of a transfer. Answered
             * here rather than left for a storefront to work out from the
             * payment type and an empty list — two consumers deriving it
             * separately is two chances to ask for a receipt twice.
             */
            'awaiting_receipt' => $this->awaitingReceipt(),
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

<?php

namespace App\Http\Resources\Admin\Order\List;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminOrderListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_code' => $this->order_code,
            'total_paid' => $this->total_paid ? (float) $this->total_paid : null,
            'total_amount' => $this->total_amount !== null ? (float) $this->total_amount : null,
            'total_amount_before_discount' => $this->total_amount_before_discount !== null ? (float) $this->total_amount_before_discount : null,
            /*
             * What the customer is charged for delivery — not `delivery_cost`,
             * which is what the courier charges us. The list breaks the order
             * cost down as "lines + delivery", and the half a customer would
             * recognise is the one they were billed.
             *
             * `total_amount` already carries it, so the lines subtotal is the
             * difference rather than a second sum over `order_products`: it
             * cannot disagree with the total the row shows above it, and the
             * list stays one query.
             */
            'delivery_price' => (float) $this->delivery_price,
            'products_total' => round((float) $this->total_amount - (float) $this->delivery_price, 2),
            'customer_full_name' => $this->customer_full_name,
            'customer_phone' => $this->customer_phone,
            'membership_number' => $this->membership_number,
            'payment_status' => [
                'value' => $this->payment_status->value,
                'label' => $this->payment_status->label(),
            ],
            'delivery_status' => [
                'value' => $this->delivery_status->value,
                'label' => $this->delivery_status->label(),
            ],
            'order_status' => [
                'value' => $this->order_status?->value,
                'label' => $this->order_status?->label(),
            ],
            'payment_type' => [
                'value' => $this->payment_type->value,
                'label' => $this->payment_type->label(),
            ],
            'cancel_reason' => $this->cancel_reason,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            /* Null for every live order; the trash page is the one screen that
               reads it, and it costs nothing to carry on the list. */
            'deleted_at' => $this->deleted_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}

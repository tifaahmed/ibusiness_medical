<?php

namespace App\Http\Resources\Admin\Order\Edit;

use App\Http\Resources\Admin\Order\Show\AdminOrderProductResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The order as the edit form needs it: raw values, not rendered ones.
 *
 * Statuses come back as bare strings rather than the `{value,label}` pairs the
 * list and show pages use — a select binds to the value, and handing it an
 * object is how a form silently posts back `[object Object]`.
 */
class AdminOrderEditResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $lines = $this->whenLoaded('products');

        return [
            'id' => $this->id,
            /* Read-only on the form: it is the buyer's only credential and the
               only thing they can track the order with. */
            'order_code' => $this->order_code,

            'total_paid' => $this->total_paid === null ? null : (float) $this->total_paid,
            'total_amount' => $this->total_amount === null ? null : (float) $this->total_amount,
            'total_amount_before_discount' => $this->total_amount_before_discount === null
                ? null
                : (float) $this->total_amount_before_discount,

            /* Correctable: a courier's charge is renegotiated, and an order
               taken over the phone is sometimes delivered on other terms. */
            'delivery_cost' => (float) $this->delivery_cost,
            'delivery_price' => (float) $this->delivery_price,
            'delivery_profit' => (float) $this->delivery_profit,

            'customer_full_name' => $this->customer_full_name,
            'customer_phone' => $this->customer_phone,
            'customer_address' => $this->customer_address,
            /* Bare values — the form's selects bind to values, not pairs. */
            'customer_address_type' => $this->customer_address_type?->value,
            'customer_street' => $this->customer_street,
            'customer_governorate' => $this->customer_governorate,
            'customer_city' => $this->customer_city,
            'customer_building_number' => $this->customer_building_number,
            'customer_apartment_number' => $this->customer_apartment_number,
            'customer_floor_number' => $this->customer_floor_number,
            'customer_special_mark' => $this->customer_special_mark,
            'notes' => $this->notes,
            'membership_number' => $this->membership_number,

            'payment_status' => $this->payment_status?->value,
            'delivery_status' => $this->delivery_status?->value,
            'payment_type' => $this->payment_type?->value,
            'cancel_reason' => $this->cancel_reason,
            'source' => $this->source,

            /* Shown beside the form but never posted back — see the show
               resource for why provenance is not editable. */
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,

            'products' => $lines instanceof \Illuminate\Support\Collection
                ? AdminOrderProductResource::collection($lines)->resolve($request)
                : [],

            'receipts' => $this->receiptFiles(),

            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}

<?php

namespace App\Http\Resources\Admin\Order\Show;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Everything an admin needs to read one order: what was bought, what was
 * charged, who bought it, where it came from and everything that has since
 * been done to it.
 */
class AdminOrderShowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $lines = $this->whenLoaded('products');

        return [
            'id' => $this->id,
            'order_code' => $this->order_code,

            'total_paid' => $this->total_paid === null ? null : (float) $this->total_paid,
            'total_amount' => $this->total_amount === null ? null : (float) $this->total_amount,
            'total_amount_before_discount' => $this->total_amount_before_discount === null
                ? null
                : (float) $this->total_amount_before_discount,

            'customer_full_name' => $this->customer_full_name,
            'customer_phone' => $this->customer_phone,
            'customer_address' => $this->customer_address,
            /* The delivery address in detail — where the courier is actually
               being sent, as the buyer described it when ordering. */
            'customer_address_type' => [
                'value' => $this->customer_address_type?->value,
                'label' => $this->customer_address_type?->label(),
            ],
            'customer_street' => $this->customer_street,
            'customer_governorate' => $this->customer_governorate,
            'customer_city' => $this->customer_city,
            'customer_building_number' => $this->customer_building_number,
            'customer_apartment_number' => $this->customer_apartment_number,
            'customer_floor_number' => $this->customer_floor_number,
            'customer_special_mark' => $this->customer_special_mark,
            'notes' => $this->notes,
            'membership_number' => $this->membership_number,

            'payment_status' => [
                'value' => $this->payment_status?->value,
                'label' => $this->payment_status?->label(),
            ],
            'delivery_status' => [
                'value' => $this->delivery_status?->value,
                'label' => $this->delivery_status?->label(),
            ],
            'payment_type' => [
                'value' => $this->payment_type?->value,
                'label' => $this->payment_type?->label(),
            ],
            'cancel_reason' => $this->cancel_reason,

            /*
             * Provenance, recorded once when the order arrived: the BUYER's
             * address and browser as the storefront forwarded them, not the
             * caller's. Read-only everywhere — rewriting it would erase the
             * only record of where the order actually came from.
             */
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'source' => $this->source,

            'products' => $lines instanceof \Illuminate\Support\Collection
                ? AdminOrderProductResource::collection($lines)->resolve($request)
                : [],

            /** The receipts a buyer sent against a wallet transfer. */
            'receipts' => $this->receiptFiles(),
            'awaiting_receipt' => $this->awaitingReceipt(),

            'logs' => $this->relationLoaded('logs')
                ? AdminOrderLogResource::collection($this->logs)->resolve($request)
                : [],

            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}

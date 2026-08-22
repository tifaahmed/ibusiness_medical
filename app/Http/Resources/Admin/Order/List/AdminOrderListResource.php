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
            'payment_type' => [
                'value' => $this->payment_type->value,
                'label' => $this->payment_type->label(),
            ],
            'cancel_reason' => $this->cancel_reason,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}

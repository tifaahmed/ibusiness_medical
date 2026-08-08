<?php

namespace App\Http\Resources\Admin\MemberPayment\Show;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminMemberPaymentShowResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $membership = $this->whenLoaded('membership', fn () => $this->membership);

        return [
            'id' => $this->id,
            'membership_id' => $this->membership_id,
            'membership_number' => $membership?->membership_number,
            'user_name' => $membership?->user?->name,
            'user_phone' => $membership?->user?->phone,
            'user_email' => $membership?->user?->email,
            'user_avatar' => $membership?->user?->avatar_url,
            'partner_name' => $membership?->partner?->title,
            'payment_type' => $membership?->payment_type,
            'is_paid' => $membership?->is_paid,
            'registration_date' => $membership?->registration_date?->format('Y-m-d'),
            'expiration_date' => $membership?->expiration_date?->format('Y-m-d'),
            'amount' => $this->amount,
            'type' => $this->type ?? 'commission',
            'months_paid' => $this->months_paid,
            'from_date' => $this->from_date?->format('Y-m-d'),
            'to_date' => $this->to_date?->format('Y-m-d'),
            'notes' => $this->notes,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}

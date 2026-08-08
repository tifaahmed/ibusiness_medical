<?php

namespace App\Http\Resources\Admin\MemberPayment\Edit;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminMemberPaymentEditResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'membership_id' => $this->membership_id,
            'amount' => $this->amount,
            'type' => $this->type ?? 'commission',
            'months_paid' => $this->months_paid,
            'from_date' => $this->from_date?->format('Y-m-d'),
            'to_date' => $this->to_date?->format('Y-m-d'),
            'notes' => $this->notes,
        ];
    }
}

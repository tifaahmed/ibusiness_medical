<?php

namespace App\Http\Resources\Admin\MembershipCard\List;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminMembershipCardListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $ids = $this->membership_ids ?: [];

        return [
            'id' => $this->id,
            'batch_name' => $this->batch_name,
            'prefix' => $this->prefix,
            'display_prefix' => $this->display_prefix,
            'quantity' => (int) $this->quantity,
            'start_number' => (int) $this->start_number,
            'end_number' => (int) $this->start_number + max(0, (int) $this->quantity - 1),
            'membership_ids_count' => count($ids),
            'completed_count' => (int) ($this->completed_count ?? 0),
            'completed_percentage' => count($ids) > 0
                ? round(((int) ($this->completed_count ?? 0)) / count($ids) * 100)
                : 0,
            'pdf_url' => $this->getFirstMediaUrl('pdf') ?: null,
            'has_pdf' => $this->getFirstMedia('pdf') !== null,
            'creator' => $this->relationLoaded('creator') && $this->creator ? [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
                'email' => $this->creator->email,
            ] : null,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}

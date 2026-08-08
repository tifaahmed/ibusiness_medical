<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MembershipResource extends JsonResource
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
            'membership_number' => $this->membership_number,
            'slug' => $this->slug,
            'registration_date' => $this->registration_date?->format('Y-m-d H:i:s'),
            'expiration_date' => $this->expiration_date?->format('Y-m-d H:i:s'),
            'registration_date_formatted' => $this->registration_date?->format('Y-m-d\TH:i'),
            'expiration_date_formatted' => $this->expiration_date?->format('Y-m-d\TH:i'),
            'is_active' => $this->is_active ?? false,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}


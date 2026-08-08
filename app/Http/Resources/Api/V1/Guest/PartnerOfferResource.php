<?php

namespace App\Http\Resources\Api\V1\Guest;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PartnerOfferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'partner_id' => $this->partner_id,
            'partner_name' => $this->whenLoaded('partner', fn() => $this->partner->title, ''),
            'title' => $this->title,
            'short_description' => $this->short_description,
            'description' => $this->description,
            'old_price' => $this->old_price,
            'new_price' => $this->new_price,
            'phone_number' => $this->phone_number,
            'operator' => $this->operator?->value,
            'header_image' => $this->mobile_header_image,
            'small_image' => $this->mobile_small_image,
            'created_at' => $this->created_at,
        ];
    }
}

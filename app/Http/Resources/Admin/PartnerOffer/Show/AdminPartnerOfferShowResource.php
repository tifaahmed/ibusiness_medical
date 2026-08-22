<?php

namespace App\Http\Resources\Admin\PartnerOffer\Show;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminPartnerOfferShowResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'partner_id' => $this->partner_id,
            'partner' => $this->whenLoaded('partner', fn () => [
                'id' => $this->partner?->id,
                'title' => $this->partner?->title,
            ]),
            'title' => $this->title,
            'short_description' => $this->short_description,
            'description' => $this->description,
            'old_price' => $this->old_price,
            'new_price' => $this->new_price,
            'phone_number' => $this->phone_number,
            'operator' => $this->operator?->value,
            'operator_title' => $this->operator?->title(),
            'operator_name' => $this->operator?->name(),
            'operator_logo' => $this->operator?->logo(),
            'header_image' => $this->header_image,
            'mobile_header_image' => $this->mobile_header_image,
            'small_image' => $this->small_image,
            'mobile_small_image' => $this->mobile_small_image,
            'gallery' => $this->gallery,
            'requests_count' => $this->requests_count,
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator?->id,
                'name' => $this->creator?->name,
                'email' => $this->creator?->email,
            ]),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}

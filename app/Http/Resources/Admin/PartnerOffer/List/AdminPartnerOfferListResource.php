<?php

namespace App\Http\Resources\Admin\PartnerOffer\List;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminPartnerOfferListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'partner_id' => $this->partner_id,
            'partner' => $this->whenLoaded('partner', fn() => [
                'id' => $this->partner->id,
                'title' => $this->partner->title,
            ]),
            'header_image' => $this->header_image,
            'mobile_header_image' => $this->mobile_header_image,
            'small_image' => $this->small_image,
            'mobile_small_image' => $this->mobile_small_image,
            'new_price' => $this->new_price,
            'old_price' => $this->old_price,
            'phone_number' => $this->phone_number,
            'operator' => $this->operator?->value,
            'operator_title' => $this->operator?->title(),
            'operator_name' => $this->operator?->name(),
            'operator_logo' => $this->operator?->logo(),
            'created_by' => $this->created_by,
            'creator' => $this->whenLoaded('creator', fn() => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
                'email' => $this->creator->email,
            ]),
            'requests_count' => $this->whenCounted('requests', fn() => $this->requests_count),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}

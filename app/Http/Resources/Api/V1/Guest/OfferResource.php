<?php

namespace App\Http\Resources\Api\V1\Guest;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OfferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'short_description' => $this->short_description,
            'full_description' => $this->full_description,
            'phone' => $this->phone,
            'price' => $this->price,
            'old_price' => $this->old_price,
            'discount_percentage' => $this->discount_percentage,
            'has_discount' => $this->hasDiscount(),
            'image' => $this->mobile_image,
            'thumbnail' => $this->mobile_thumbnail,
            'offerable_type' => $this->offerable_type,
            'offerable_name' => $this->whenLoaded('offerable', fn() => $this->offerable->name),
        ];
    }
}

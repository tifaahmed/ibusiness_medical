<?php

namespace App\Http\Resources\Api\V1\Guest;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'short_subject' => $this->short_subject,
            'subject' => $this->subject,
            'old_price' => $this->old_price,
            'new_price' => $this->new_price,
            'discount_percent' => $this->discount_percent,
            'governorate' => $this->whenLoaded('governorate', fn() => [
                'name' => $this->governorate->name,
            ]),
            'city' => $this->whenLoaded('city', fn() => [
                'name' => $this->city->name,
            ]),
            'category' => $this->whenLoaded('serviceType', fn() => [
                'name' => $this->serviceType->name,
                'icon' => $this->serviceType->icon,
                'color' => $this->serviceType->color,
            ]),
            'image' => $this->mobile_image,
            'gallery' => $this->gallery,
            'tags' => $this->whenLoaded('tags', fn() => $this->tags->map(fn($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'icon' => $t->icon,
                'color' => $t->color,
            ])),
            'iframe_location' => $this->iframe_location,
            'lat' => $this->lat,
            'long' => $this->long,
        ];
    }
}

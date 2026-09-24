<?php

namespace App\Http\Resources\Guest;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreResource extends JsonResource
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
            'title' => $this->title,
            'slug' => $this->slug,
            'short_description' => $this->short_description,
            'description' => $this->description,
            'youtube_link' => $this->youtube_link,
            'logo' => $this->logo,
            'mobile_logo' => $this->mobile_logo,
            'image' => $this->image,
            'mobile_image' => $this->mobile_image,
            'header' => $this->header,
            'offer_percent_from' => $this->offer_percent_from,
            'offer_percent_to' => $this->offer_percent_to,
            'gallery' => $this->gallery,
            /*
             * Distance (km) to this store's nearest branch from the point
             * `StoreListController` was asked about — present only when the
             * request carried `lat`/`lng`, absent (not merely null) otherwise,
             * because `distance_km` is never a real column and only exists on
             * the model when that request joined it in. Mirrors
             * `Guest\FacilityResource`'s own treatment exactly.
             */
            'distance_km' => $this->when(
                array_key_exists('distance_km', $this->getAttributes()),
                fn () => $this->distance_km !== null ? round((float) $this->distance_km, 1) : null,
            ),
            'branches' => $this->whenLoaded('branches', function () {
                return $this->branches->map(function ($branch) {
                    return [
                        'id' => $branch->id,
                        'name' => $branch->name,
                        'slug' => $branch->slug,
                        'address' => $branch->address,
                        'area' => $branch->area,
                        // Flat numbers, unchanged: the marketing site reads this.
                        'phone' => $branch->phoneNumbers(),
                        // The same numbers with the kind of line each one is,
                        // for consumers ready to show a WhatsApp button.
                        'phones' => $branch->phone,
                        'governorate' => $branch->relationLoaded('governorate') && $branch->governorate ? [
                            'id' => $branch->governorate->id,
                            'name' => $branch->governorate->name,
                        ] : null,
                        'city' => $branch->relationLoaded('city') && $branch->city ? [
                            'id' => $branch->city->id,
                            'name' => $branch->city->name,
                        ] : null,
                        'latitude' => $branch->latitude !== null ? (float) $branch->latitude : null,
                        'longitude' => $branch->longitude !== null ? (float) $branch->longitude : null,
                        'google_location_url' => $branch->google_location_url,
                    ];
                });
            }),
        ];
    }
}

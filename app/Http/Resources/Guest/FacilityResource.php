<?php

namespace App\Http\Resources\Guest;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FacilityResource extends JsonResource
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
            'name' => $this->name,
            'slug' => $this->slug,
            'logo'         => $this->logo,
            'mobile_logo'  => $this->mobile_logo,
            'image'        => $this->image,
            'mobile_image' => $this->mobile_image,
            'facility_type' => $this->whenLoaded('facilityType', function () {
                return $this->facilityType ? [
                    'id' => $this->facilityType->id,
                    'name' => $this->facilityType->name,
                    'slug' => $this->facilityType->slug,
                ] : null;
            }),
            'branches' => $this->whenLoaded('branches', function () {
                return $this->branches->map(function ($branch) {
                    return [
                        'id' => $branch->id,
                        'name' => $branch->name,
                        'slug' => $branch->slug,
                        'address' => $branch->address,
                        'phone' => $branch->phone,
                        'governorate' => $branch->relationLoaded('governorate') && $branch->governorate ? [
                            'id' => $branch->governorate->id,
                            'name' => $branch->governorate->name,
                        ] : null,
                        'city' => $branch->relationLoaded('city') && $branch->city ? [
                            'id' => $branch->city->id,
                            'name' => $branch->city->name,
                        ] : null,
                    ];
                });
            }),
        ];
    }
}



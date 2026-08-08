<?php

namespace App\Http\Resources\Admin\Facility\List;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminFacilityListResource extends JsonResource
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
            'description' => $this->description,
            'slug' => $this->slug,
            'discount_percent' => $this->discount_percent,
            'facility_type' => $this->whenLoaded('facilityType', function () {
                return $this->facilityType ? [
                    'id' => $this->facilityType->id,
                    'name' => $this->facilityType->name,
                    'slug' => $this->facilityType->slug,
                ] : null;
            }),
            'logo'         => $this->logo,
            'mobile_logo'  => $this->mobile_logo,
            'image'        => $this->image,
            'mobile_image' => $this->mobile_image,
            'branches_count' => $this->whenCounted('branches', $this->branches_count ?? $this->branches()->count()),
            // Full branch details for the list popup
            'branches' => $this->whenLoaded('branches', function () {
                return $this->branches->map(fn ($branch) => [
                    'id' => $branch->id,
                    'name' => $branch->getTranslations('name'),
                    'address' => $branch->getTranslations('address'),
                    'phone' => $branch->phone,
                    'latitude' => $branch->latitude,
                    'longitude' => $branch->longitude,
                    'governorate' => $branch->governorate ? [
                        'id' => $branch->governorate->id,
                        'name' => $branch->governorate->getTranslations('name'),
                    ] : null,
                    'city' => $branch->city ? [
                        'id' => $branch->city->id,
                        'name' => $branch->city->getTranslations('name'),
                    ] : null,
                ])->values();
            }),
            // Unique governorates across all of this facility's branches
            'governorates' => $this->whenLoaded('branches', function () {
                return $this->branches
                    ->map(fn ($branch) => $branch->governorate)
                    ->filter()
                    ->unique('id')
                    ->map(fn ($governorate) => [
                        'id' => $governorate->id,
                        'name' => $governorate->getTranslations('name'),
                    ])
                    ->values();
            }),
            // Unique cities across all of this facility's branches
            'cities' => $this->whenLoaded('branches', function () {
                return $this->branches
                    ->map(fn ($branch) => $branch->city)
                    ->filter()
                    ->unique('id')
                    ->map(fn ($city) => [
                        'id' => $city->id,
                        'name' => $city->getTranslations('name'),
                    ])
                    ->values();
            }),
            'tags' => $this->whenLoaded('tags', function () {
                return $this->tags->map(function ($tag) {
                    return [
                        'id' => $tag->id,
                        'name' => $tag->name,
                        'icon' => $tag->icon,
                        'color' => $tag->color,
                    ];
                });
            }),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}



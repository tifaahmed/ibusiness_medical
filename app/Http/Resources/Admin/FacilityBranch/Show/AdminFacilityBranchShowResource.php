<?php

namespace App\Http\Resources\Admin\FacilityBranch\Show;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminFacilityBranchShowResource extends JsonResource
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
            'address' => $this->address,
            'phone' => $this->phone,
            'facility' => $this->whenLoaded('facility', function () {
                return $this->facility ? [
                    'id' => $this->facility->id,
                    'name' => $this->facility->name,
                    'slug' => $this->facility->slug,
                    // `whenLoaded` belongs to resources, not models: called on the facility
                    // it threw, and this page answered 500.
                    'facility_type' => $this->facility->relationLoaded('facilityType') && $this->facility->facilityType ? [
                        'id' => $this->facility->facilityType->id,
                        'name' => $this->facility->facilityType->name,
                        'slug' => $this->facility->facilityType->slug,
                    ] : null,
                ] : null;
            }),
            'governorate' => $this->whenLoaded('governorate', function () {
                return $this->governorate ? [
                    'id' => $this->governorate->id,
                    'name' => $this->governorate->name,
                    'slug' => $this->governorate->slug,
                ] : null;
            }),
            'city' => $this->whenLoaded('city', function () {
                return $this->city ? [
                    'id' => $this->city->id,
                    'name' => $this->city->name,
                ] : null;
            }),
            'area' => $this->whenLoaded('area', function () {
                return $this->area ? [
                    'id' => $this->area->id,
                    'name' => $this->area->getTranslations('name'),
                ] : null;
            }),
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}




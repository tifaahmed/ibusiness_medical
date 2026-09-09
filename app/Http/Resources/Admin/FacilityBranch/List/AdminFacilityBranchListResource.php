<?php

namespace App\Http\Resources\Admin\FacilityBranch\List;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminFacilityBranchListResource extends JsonResource
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
            // Every translation, so the card can show both and name the one
            // that is missing — which is what the "No address" filter selects.
            'address' => $this->resource->getTranslations('address'),
            'phone' => $this->phone,
            'facility' => $this->whenLoaded('facility', function () {
                return $this->facility ? [
                    'id' => $this->facility->id,
                    'name' => $this->facility->name,
                    'slug' => $this->facility->slug,
                    'facility_type' => $this->facility->facilityType ? [
                        'id' => $this->facility->facilityType->id,
                        'name' => $this->facility->facilityType->name,
                    ] : null,
                ] : null;
            }),
            // Every translation, not just the active locale's: the list shows
            // the Arabic and the English name side by side, so a reader can
            // spot a place whose second language was never filled in.
            'governorate' => $this->whenLoaded('governorate', function () {
                return $this->governorate ? [
                    'id' => $this->governorate->id,
                    'name' => $this->governorate->getTranslations('name'),
                ] : null;
            }),
            'city' => $this->whenLoaded('city', function () {
                return $this->city ? [
                    'id' => $this->city->id,
                    'name' => $this->city->getTranslations('name'),
                ] : null;
            }),
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}




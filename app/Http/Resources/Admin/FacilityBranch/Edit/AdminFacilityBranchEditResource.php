<?php

namespace App\Http\Resources\Admin\FacilityBranch\Edit;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminFacilityBranchEditResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Get all translations for translatable fields (not just current locale)
        $nameTranslations = $this->getTranslations('name');
        $addressTranslations = $this->getTranslations('address');
        
        return [
            'id' => $this->id,
            'name' => $nameTranslations, // Return all translations as an object/array
            'slug' => $this->slug,
            'address' => $addressTranslations, // Return all translations as an object/array
            'phone' => $this->phone,
            'facility_id' => $this->facility_id,
            'governorate_id' => $this->governorate_id,
            'city_id' => $this->city_id,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'google_location_url' => $this->google_location_url,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}


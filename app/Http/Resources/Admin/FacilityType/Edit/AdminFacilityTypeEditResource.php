<?php

namespace App\Http\Resources\Admin\FacilityType\Edit;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminFacilityTypeEditResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Get all translations for the name field (not just current locale)
        $nameTranslations = $this->getTranslations('name');
        
        return [
            'id' => $this->id,
            'name' => $nameTranslations, // Return all translations as an object/array
            'slug' => $this->slug,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}


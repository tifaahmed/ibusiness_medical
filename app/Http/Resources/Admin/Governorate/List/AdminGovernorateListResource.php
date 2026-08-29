<?php

namespace App\Http\Resources\Admin\Governorate\List;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminGovernorateListResource extends JsonResource
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
            'facilities_count' => $this->facilities_count ?? $this->facilities()->count(),
            'cities_count' => $this->whenCounted('cities', $this->cities_count ?? $this->cities()->count()),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}




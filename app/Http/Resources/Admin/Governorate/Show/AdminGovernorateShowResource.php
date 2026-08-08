<?php

namespace App\Http\Resources\Admin\Governorate\Show;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminGovernorateShowResource extends JsonResource
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
            'facilities_count' => $this->whenCounted('facilities', $this->facilities_count ?? $this->facilities()->count()),
            'facilities' => $this->whenLoaded('facilities', function () use ($request) {
                return $this->facilities->map(function ($facility) {
                    return [
                        'id' => $facility->id,
                        'name' => $facility->name,
                        'slug' => $facility->slug,
                        'facility_type' => $facility->whenLoaded('facilityType', function () use ($facility) {
                            return $facility->facilityType ? [
                                'id' => $facility->facilityType->id,
                                'name' => $facility->facilityType->name,
                                'slug' => $facility->facilityType->slug,
                            ] : null;
                        }),
                        'phone' => $facility->phone,
                        'created_at' => $facility->created_at?->format('Y-m-d H:i:s'),
                        'updated_at' => $facility->updated_at?->format('Y-m-d H:i:s'),
                    ];
                })->toArray();
            }),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}




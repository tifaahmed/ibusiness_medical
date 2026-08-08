<?php

namespace App\Http\Resources\Admin\Facility\Show;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminFacilityShowResource extends JsonResource
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
            'branches' => $this->whenLoaded('branches', function () use ($request) {
                return $this->branches->map(function ($branch) {
                    return [
                        'id' => $branch->id,
                        'name' => $branch->name,
                        'slug' => $branch->slug,
                        'address' => $branch->address,
                        'phone' => $branch->phone,
                        'created_at' => $branch->created_at?->format('Y-m-d H:i:s'),
                        'updated_at' => $branch->updated_at?->format('Y-m-d H:i:s'),
                    ];
                })->toArray();
            }),
            'branches_count' => $this->whenCounted('branches', $this->branches_count ?? $this->branches()->count()),
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



<?php

namespace App\Http\Resources\Admin\Offer\Show;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminOfferShowResource extends JsonResource
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
            'full_description' => $this->full_description,
            'phone' => $this->phone,
            'price' => $this->price,
            'old_price' => $this->old_price,
            'discount_percentage' => $this->discount_percentage,
            'has_discount' => $this->hasDiscount(),
            'image'            => $this->image,
            'mobile_image'     => $this->mobile_image,
            'thumbnail'        => $this->thumbnail,
            'mobile_thumbnail' => $this->mobile_thumbnail,
            'offerable_type' => $this->offerable_type,
            'offerable_id' => $this->offerable_id,
            'offerable' => $this->whenLoaded('offerable', function () {
                if (!$this->offerable) {
                    return null;
                }

                $data = [
                    'id' => $this->offerable->id,
                    'name' => $this->offerable->name ?? null,
                    'slug' => $this->offerable->slug ?? null,
                    'type' => get_class($this->offerable),
                ];

                // If offerable is a Facility
                if ($this->offerable_type === 'App\\Models\\Facility') {
                    if (method_exists($this->offerable, 'facilityType') && $this->offerable->relationLoaded('facilityType')) {
                        $data['facility_type'] = $this->offerable->facilityType ? [
                            'id' => $this->offerable->facilityType->id,
                            'name' => $this->offerable->facilityType->name,
                            'slug' => $this->offerable->facilityType->slug,
                        ] : null;
                    }

                    if (method_exists($this->offerable, 'governorate') && $this->offerable->relationLoaded('governorate')) {
                        $data['governorate'] = $this->offerable->governorate ? [
                            'id' => $this->offerable->governorate->id,
                            'name' => $this->offerable->governorate->name,
                            'slug' => $this->offerable->governorate->slug,
                        ] : null;
                    }
                }

                // If offerable is a FacilityBranch
                if ($this->offerable_type === 'App\\Models\\FacilityBranch') {
                    if (method_exists($this->offerable, 'facility') && $this->offerable->relationLoaded('facility') && $this->offerable->facility) {
                        $facility = $this->offerable->facility;
                        $data['facility'] = [
                            'id' => $facility->id,
                            'name' => $facility->name,
                            'slug' => $facility->slug,
                        ];

                        if ($facility->relationLoaded('facilityType')) {
                            $data['facility']['facility_type'] = $facility->facilityType ? [
                                'id' => $facility->facilityType->id,
                                'name' => $facility->facilityType->name,
                                'slug' => $facility->facilityType->slug,
                            ] : null;
                        }

                        if ($facility->relationLoaded('governorate')) {
                            $data['facility']['governorate'] = $facility->governorate ? [
                                'id' => $facility->governorate->id,
                                'name' => $facility->governorate->name,
                                'slug' => $facility->governorate->slug,
                            ] : null;
                        }
                    }
                }

                return $data;
            }),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}

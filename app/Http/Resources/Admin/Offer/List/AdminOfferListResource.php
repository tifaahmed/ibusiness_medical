<?php

namespace App\Http\Resources\Admin\Offer\List;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminOfferListResource extends JsonResource
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

                // If offerable is a FacilityBranch, include facility data
                if ($this->offerable_type === 'App\\Models\\FacilityBranch' && $this->offerable->facility) {
                    $data['facility'] = [
                        'id' => $this->offerable->facility->id,
                        'name' => $this->offerable->facility->name,
                        'slug' => $this->offerable->facility->slug,
                    ];
                }

                return $data;
            }),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}

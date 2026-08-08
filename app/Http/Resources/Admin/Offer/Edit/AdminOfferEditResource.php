<?php

namespace App\Http\Resources\Admin\Offer\Edit;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminOfferEditResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Get all translations for translatable fields (not just current locale)
        $titleTranslations = $this->getTranslations('title');
        $shortDescriptionTranslations = $this->getTranslations('short_description');
        $fullDescriptionTranslations = $this->getTranslations('full_description');

        return [
            'id' => $this->id,
            'title' => $titleTranslations, // Return all translations as an object/array
            'slug' => $this->slug,
            'short_description' => $shortDescriptionTranslations, // Return all translations as an object/array
            'full_description' => $fullDescriptionTranslations, // Return all translations as an object/array
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
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}

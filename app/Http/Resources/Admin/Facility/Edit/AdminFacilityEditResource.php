<?php

namespace App\Http\Resources\Admin\Facility\Edit;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminFacilityEditResource extends JsonResource
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
        $descriptionTranslations = $this->getTranslations('description');

        return [
            'id' => $this->id,
            'name' => $nameTranslations,
            'description' => $descriptionTranslations,
            'slug' => $this->slug,
            'meta_title' => $this->getTranslations('meta_title'),
            'meta_description' => $this->getTranslations('meta_description'),
            'meta_keywords' => $this->getTranslations('meta_keywords'),
            'canonical_url' => $this->canonical_url,
            'og_image' => $this->og_image,
            'facility_type_id' => $this->facility_type_id,
            'sales_id' => $this->sales_id,
            'discount_percent' => $this->discount_percent,
            'banner_config' => $this->banner_config,
            'branches' => $this->whenLoaded('branches', function () {
                return $this->branches->map(function ($branch) {
                    return [
                        'id' => $branch->id,
                        'name' => $branch->getTranslations('name'),
                        'address' => $branch->getTranslations('address'),
                        'phone' => $branch->phone,
                        'governorate_id' => $branch->governorate_id,
                        'city_id' => $branch->city_id,
                        'latitude' => $branch->latitude,
                        'longitude' => $branch->longitude,
                        'slug' => $branch->slug,
                    ];
                });
            }),
            'managers' => $this->whenLoaded('managers', function () {
                return $this->managers->map(function ($manager) {
                    return [
                        'id' => $manager->id,
                        'name' => $manager->name,
                        'position' => $manager->position,
                        'phones' => $manager->phones,
                    ];
                });
            }),
            'logo' => $this->logo,
            'mobile_logo' => $this->mobile_logo,
            'image' => $this->image,
            'mobile_image' => $this->mobile_image,
            'gallery' => $this->gallery,
            'contract' => $this->contract,
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

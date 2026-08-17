<?php

namespace App\Http\Resources\Api\V1\Guest;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FacilityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'discount_percent' => $this->discount_percent,
            'banner_config' => $this->resolvedBannerConfig(),
            'facility_type' => $this->whenLoaded('facilityType', fn () => [
                'name' => $this->facilityType->name,
            ]),
            'governorate' => $this->whenLoaded('governorate', fn () => [
                'name' => $this->governorate->name,
            ]),
            'city' => $this->whenLoaded('city', fn () => [
                'name' => $this->city->name,
            ]),
            'logo' => $this->mobile_logo,
            'image' => $this->mobile_image,
            'gallery' => $this->gallery,
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'icon' => $t->icon,
                'color' => $t->color,
            ])),
        ];
    }

    private function resolvedBannerConfig(): ?array
    {
        $config = $this->banner_config;

        if (! is_array($config) || empty($config['enabled'])) {
            return null;
        }

        $days = $config['days'] ?? null;

        if ($days === null) {
            return $config;
        }

        $endDate = Carbon::parse($this->created_at)->addDays((int) $days);

        if (Carbon::now()->gt($endDate)) {
            return null;
        }

        return $config;
    }
}

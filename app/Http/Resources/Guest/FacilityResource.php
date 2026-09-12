<?php

namespace App\Http\Resources\Guest;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FacilityResource extends JsonResource
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
            'logo' => $this->logo,
            'mobile_logo' => $this->mobile_logo,
            'image' => $this->image,
            'mobile_image' => $this->mobile_image,
            'discount_percent' => $this->discount_percent,
            'banner_config' => $this->resolvedBannerConfig(),
            // The head office's own place — plain columns, not a relation, so
            // they ride along unconditionally. Consumers that only show a
            // branch's place (the storefront's card) can ignore these; the
            // storefront's own 24h facility cache needs them to match "itself
            // or a branch" the same way this endpoint's own filters do.
            'governorate_id' => $this->governorate_id,
            'city_id' => $this->city_id,
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->map(fn ($tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
                'color' => $tag->color,
            ])),
            'facility_type' => $this->whenLoaded('facilityType', function () {
                return $this->facilityType ? [
                    'id' => $this->facilityType->id,
                    'name' => $this->facilityType->name,
                    'slug' => $this->facilityType->slug,
                ] : null;
            }),
            'branches' => $this->whenLoaded('branches', function () {
                return $this->branches->map(function ($branch) {
                    return [
                        'id' => $branch->id,
                        'name' => $branch->name,
                        'slug' => $branch->slug,
                        'address' => $branch->address,
                        // Flat numbers, unchanged: the marketing site reads this.
                        'phone' => $branch->phoneNumbers(),
                        // The same numbers with the kind of line each one is,
                        // for consumers ready to show a WhatsApp button.
                        'phones' => $branch->phone,
                        'governorate' => $branch->relationLoaded('governorate') && $branch->governorate ? [
                            'id' => $branch->governorate->id,
                            'name' => $branch->governorate->name,
                        ] : null,
                        'city' => $branch->relationLoaded('city') && $branch->city ? [
                            'id' => $branch->city->id,
                            'name' => $branch->city->name,
                        ] : null,
                    ];
                });
            }),
        ];
    }

    /**
     * Return the banner config only when it is enabled and not yet expired.
     */
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

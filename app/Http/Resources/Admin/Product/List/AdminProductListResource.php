<?php

namespace App\Http\Resources\Admin\Product\List;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminProductListResource extends JsonResource
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
            // All translations, so the table can show the Arabic and English names side by side.
            'name' => $this->getTranslations('name'),
            'short_subject' => $this->getTranslations('short_subject'),
            'slug' => $this->slug,
            'old_price' => $this->old_price,
            'new_price' => $this->new_price,
            'large_image' => $this->getFirstMediaUrl('large_image'),
            'small_image' => $this->getFirstMediaUrl('small_image'),
            'product_type' => $this->whenLoaded('productType', fn () => [
                'id' => $this->productType->id,
                'name' => $this->productType->getTranslations('name'),
            ]),
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
                'email' => $this->creator->email,
            ]),
            'tags' => $this->whenLoaded('tags', function () {
                return $this->tags->map(fn ($tag) => [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'icon' => $tag->icon,
                    'color' => $tag->color,
                ]);
            }),
            'banner_config' => $this->banner_config,
            'banner_end_date' => $this->computedBannerEndDate(),
            'banner_days_left' => $this->computedBannerDaysLeft(),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * When the banner stops showing, counted from the product's creation date.
     */
    private function computedBannerEndDate(): ?string
    {
        $config = $this->banner_config;

        if (! is_array($config) || empty($config['enabled']) || empty($config['days'])) {
            return null;
        }

        return Carbon::parse($this->created_at)->addDays((int) $config['days'])->toDateTimeString();
    }

    private function computedBannerDaysLeft(): ?int
    {
        $config = $this->banner_config;

        if (! is_array($config) || empty($config['enabled']) || empty($config['days'])) {
            return null;
        }

        $endDate = Carbon::parse($this->created_at)->addDays((int) $config['days']);

        if (Carbon::now()->gt($endDate)) {
            return 0;
        }

        return (int) Carbon::now()->diffInDays($endDate, false);
    }
}

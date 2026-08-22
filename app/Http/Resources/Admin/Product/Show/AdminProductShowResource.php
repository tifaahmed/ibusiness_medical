<?php

namespace App\Http\Resources\Admin\Product\Show;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminProductShowResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Rows written before the translatable inputs hold a bare string, not
        // a locale map — getTranslations() then answers an empty array, which
        // would blank the field out. Fall back to showing the raw value in
        // both slots so nothing disappears.
        $translations = function (string $field): array {
            $stored = $this->getTranslations($field);
            if ($stored !== []) {
                return $stored;
            }

            $raw = trim((string) $this->getRawOriginal($field));

            return $raw !== '' ? ['ar' => $raw, 'en' => $raw] : [];
        };

        return [
            'id' => $this->id,
            'name' => $translations('name'),
            'short_subject' => $translations('short_subject'),
            'description' => $translations('description'),
            'slug' => $this->slug,
            'old_price' => $this->old_price,
            'new_price' => $this->new_price,
            'cost_price' => $this->cost_price,
            'profit_price' => $this->profit_price,
            'admin_note' => $this->admin_note,
            'banner_config' => $this->banner_config,
            'large_image' => $this->getFirstMediaUrl('large_image'),
            'small_image' => $this->getFirstMediaUrl('small_image'),
            'gallery' => $this->gallery,
            'product_type' => $this->whenLoaded('productType', fn () => [
                'id' => $this->productType->id,
                'name' => $this->productType->name,
                'slug' => $this->productType->slug,
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
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}

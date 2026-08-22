<?php

namespace App\Http\Resources\Api\V1\Guest;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One product as its own page shows it: the listing card plus the description
 * and every photograph of it.
 *
 * @mixin Product
 */
class ProductDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'short_subject' => $this->short_subject,
            'description' => $this->description,
            'old_price' => $this->old_price,
            'new_price' => $this->new_price,
            'price' => ProductResource::sellingPrice($this->resource),
            'discount_percent' => ProductResource::discountPercent($this->resource),
            'image' => $this->getFirstMediaUrl('large_image') ?: $this->getFirstMediaUrl('small_image'),
            'thumbnail' => $this->getFirstMediaUrl('small_image') ?: $this->getFirstMediaUrl('large_image'),
            /*
             * The gallery leads with the main photograph, so a viewer opens on
             * the picture the card was showing rather than jumping to a
             * detail shot.
             */
            'gallery' => $this->galleryUrls(),
            'product_type' => $this->whenLoaded('productType', fn () => [
                'id' => $this->productType->id,
                'slug' => $this->productType->slug,
                'name' => $this->productType->name,
            ]),
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->map(fn ($tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
                'icon' => $tag->icon,
                'color' => $tag->color,
            ])),
            'banner_config' => ProductResource::resolvedBannerConfig($this->resource),
            'created_at' => $this->created_at?->toAtomString(),
            'updated_at' => $this->updated_at?->toAtomString(),
        ];
    }

    /**
     * Every picture of this product, largest first and without repeats.
     *
     * @return list<string>
     */
    private function galleryUrls(): array
    {
        $urls = [
            $this->getFirstMediaUrl('large_image'),
            $this->getFirstMediaUrl('small_image'),
            ...array_column($this->gallery, 'url'),
        ];

        return array_values(array_unique(array_filter(
            $urls,
            fn ($url) => is_string($url) && $url !== '',
        )));
    }
}

<?php

namespace App\Http\Resources\Api\V1\Guest;

use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One product as a listing card shows it.
 *
 * Deliberately narrower than the admin resource: `cost_price`, `profit_price`
 * and `admin_note` are what the shop pays and what it makes, and this endpoint
 * is read by a public storefront.
 *
 * @mixin Product
 */
class ProductResource extends JsonResource
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
            'old_price' => $this->old_price,
            'new_price' => $this->new_price,
            /*
             * What a buyer actually pays, worked out here rather than left for
             * every consumer to guess: `new_price` is the selling price and
             * `old_price` the one it is struck through against, but a product
             * that was never discounted only carries one of them.
             */
            'price' => self::sellingPrice($this->resource),
            'discount_percent' => self::discountPercent($this->resource),
            'image' => $this->getFirstMediaUrl('small_image') ?: $this->getFirstMediaUrl('large_image'),
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
            'banner_config' => self::resolvedBannerConfig($this->resource),
            'created_at' => $this->created_at?->toAtomString(),
        ];
    }

    /**
     * The banner only when it is switched on and still inside its run of days,
     * counted from the product's creation date — the same rule the facility
     * endpoints use, so an expired ribbon never reaches the storefront.
     */
    public static function resolvedBannerConfig(Product $product): ?array
    {
        $config = $product->banner_config;

        if (! is_array($config) || empty($config['enabled'])) {
            return null;
        }

        $days = $config['days'] ?? null;

        if ($days === null) {
            return $config;
        }

        $endDate = Carbon::parse($product->created_at)->addDays((int) $days);

        return Carbon::now()->gt($endDate) ? null : $config;
    }

    /**
     * The price to charge: the new one when there is one, the old one when the
     * product has never been discounted.
     */
    public static function sellingPrice(Product $product): ?string
    {
        $price = $product->new_price ?? $product->old_price;

        return $price === null ? null : (string) $price;
    }

    /**
     * How much is off, as a whole number, or nothing.
     *
     * Only a real markdown counts — an `old_price` at or below the selling
     * price is a data entry slip, and rendering "0% off" would put a badge on
     * every product nobody has discounted.
     */
    public static function discountPercent(Product $product): ?int
    {
        $old = $product->old_price === null ? null : (float) $product->old_price;
        $new = $product->new_price === null ? null : (float) $product->new_price;

        if ($old === null || $new === null || $old <= 0.0 || $new <= 0.0 || $new >= $old) {
            return null;
        }

        return (int) round((($old - $new) / $old) * 100);
    }
}

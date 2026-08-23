<?php

namespace App\Http\Resources\Admin\Order\Show;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One archived line of an order.
 *
 * `name` is sent as the whole translation map, not the request's locale: the
 * admin reading the order back may be working in the other language, and the
 * edit form has to round-trip both.
 */
class AdminOrderProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'name' => $this->getTranslations('name'),
            'slug' => $this->slug,
            'image' => $this->image,
            'quantity' => (int) $this->quantity,
            'old_price' => $this->old_price === null ? null : (float) $this->old_price,
            'new_price' => $this->new_price === null ? null : (float) $this->new_price,
            'line_total' => $this->line_total === null ? null : (float) $this->line_total,
            'cost_price' => $this->cost_price === null ? null : (float) $this->cost_price,
            'profit_price' => $this->profit_price === null ? null : (float) $this->profit_price,
            /*
             * Whether the catalogue row this line came from is still there.
             * The line stands on its own either way — this only decides
             * whether the admin gets a link back to the product.
             */
            'product_exists' => $this->product_id !== null && $this->relationLoaded('product') && $this->product !== null,
            'product_slug' => $this->relationLoaded('product') ? $this->product?->slug : null,
        ];
    }
}

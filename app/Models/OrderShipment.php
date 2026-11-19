<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** The part of an order that one store ships, with what its delivery was charged at. An archive. */
class OrderShipment extends Model
{
    protected $fillable = [
        'order_id', 'store_id', 'store_name', 'store_ids', 'shipping_group_id', 'subtotal', 'delivery_cost', 'delivery_price',
        'delivery_profit', 'free_delivery_threshold', 'delivery_free_reason', 'price_source',
    ];

    protected function casts(): array
    {
        return [
            'store_name' => 'array',
            'store_ids' => 'array',
            'subtotal' => 'decimal:2',
            'delivery_cost' => 'decimal:2',
            'delivery_price' => 'decimal:2',
            'delivery_profit' => 'decimal:2',
            'free_delivery_threshold' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** The store's name in one language, falling back to any it has. */
    public function storeLabel(?string $locale = null): string
    {
        $name = (array) $this->store_name;
        $locale ??= app()->getLocale();

        return (string) ($name[$locale] ?? $name['ar'] ?? $name['en'] ?? reset($name) ?: '');
    }
}

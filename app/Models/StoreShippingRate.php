<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One governorate's own shipping price / free-shipping threshold for a store (NULL = follow the store's figure). */
class StoreShippingRate extends Model
{
    protected $fillable = ['store_id', 'governorate_id', 'price', 'free_threshold'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'free_threshold' => 'decimal:2'];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class);
    }
}

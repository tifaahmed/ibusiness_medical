<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Stores that share a warehouse: their lines in one order travel as ONE parcel at this price (NULL = not set, 0 = free). */
class ShippingGroup extends Model
{
    protected $fillable = ['name', 'price', 'free_threshold'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'free_threshold' => 'decimal:2'];
    }

    public function stores(): HasMany
    {
        return $this->hasMany(Store::class);
    }
}

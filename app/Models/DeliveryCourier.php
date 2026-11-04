<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A courier who hands membership cards to members. */
class DeliveryCourier extends Model
{
    protected $fillable = ['name', 'phone', 'notes', 'is_active', 'created_by'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(CardDelivery::class, 'courier_id');
    }
}

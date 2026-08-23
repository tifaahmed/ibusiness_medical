<?php

namespace App\Models;

use App\Enums\Address\AddressTypeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Address extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'membership_id',
        'type',
        'address',
        'street',
        'governorate_id',
        'city_id',
        'building_number',
        'apartment_number',
        'floor_number',
        'special_mark',
    ];

    protected function casts(): array
    {
        return [
            'type' => AddressTypeEnum::class,
        ];
    }

    /**
     * Get the membership that owns the address.
     */
    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }

    /**
     * Get the governorate of the address.
     */
    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class);
    }

    /**
     * Get the city of the address.
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}

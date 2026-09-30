<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

/**
 * A neighbourhood / village unit (shiakha) inside a city: the third
 * administrative level under governorate → city. Reference data imported from
 * OCHA COD-AB Egypt (admin 3), edited from the city page (`/admin/city/{id}`).
 */
class Area extends Model
{
    use HasTranslations;

    public $translatable = [
        'name',
    ];

    protected $fillable = [
        'governorate_id',
        'city_id',
        'name',
        'pcode',
        'slug',
    ];

    /**
     * The border is a large GeoJSON blob; keep it out of every list/JSON
     * payload. The map fetches it on its own.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'boundary',
    ];

    protected function casts(): array
    {
        return [
            'boundary' => 'array',
        ];
    }

    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class);
    }

    /**
     * Facility branches that named this area.
     */
    public function branches(): HasMany
    {
        return $this->hasMany(FacilityBranch::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}

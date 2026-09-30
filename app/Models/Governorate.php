<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Spatie\Translatable\HasTranslations;

class Governorate extends Model
{
    use HasFactory;
    use HasSlug;
    use HasTranslations;

    /**
     * The attributes that are translatable.
     *
     * @var array<int, string>
     */
    public $translatable = [
        'name',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'created_by',
    ];

    /**
     * The border is a large GeoJSON blob; keep it out of every list/JSON
     * payload. The branch map fetches it on its own.
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the options for generating the slug.
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }

    /**
     * Get the facilities for the governorate.
     */
    public function facilities(): HasMany
    {
        return $this->hasMany(Facility::class);
    }

    /**
     * Branches located in this governorate.
     *
     * A facility belongs to a place twice over — its own record sits somewhere
     * and so does every branch — so "is there anything here" cannot be answered
     * from `facilities()` alone. Mirrors `City::branches()`.
     */
    public function branches(): HasMany
    {
        return $this->hasMany(FacilityBranch::class);
    }

    /**
     * Store branches located in this governorate — mirrors {@see branches()},
     * the same either-counts rule stores use in place of a head-office
     * address (Store has none of its own; see `StoreBranch`).
     */
    public function storeBranches(): HasMany
    {
        return $this->hasMany(StoreBranch::class);
    }

    /**
     * Get the cities for the governorate.
     */
    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }

    /**
     * The areas (admin level 3) of every city in this governorate.
     */
    public function areas(): HasMany
    {
        return $this->hasMany(Area::class);
    }
}

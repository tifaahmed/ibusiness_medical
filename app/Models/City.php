<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Spatie\Translatable\HasTranslations;

class City extends Model
{
    use HasFactory;
    use HasSlug;
    use HasTranslations;

    /**
     * The city that holds a governorate's ground no other city border covers.
     * See UnmarkedCitySeeder.
     */
    public const UNMARKED_NAME = ['ar' => 'مدينة غير محددة', 'en' => 'Unmarked City'];

    public $translatable = [
        'name',
    ];

    protected $fillable = [
        'governorate_id',
        'name',
        'slug',
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

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(fn ($model) => $model->getTranslation('name', 'en'))
            ->saveSlugsTo('slug');
    }

    public function isUnmarked(): bool
    {
        return $this->getTranslation('name', 'en', false) === self::UNMARKED_NAME['en'];
    }

    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class);
    }

    /**
     * The neighbourhood / village units (admin level 3) inside this city.
     */
    public function areas(): HasMany
    {
        return $this->hasMany(Area::class);
    }

    /**
     * Facilities whose head office sits in this city.
     */
    public function facilities(): HasMany
    {
        return $this->hasMany(Facility::class);
    }

    /**
     * Branches located in this city.
     */
    public function branches(): HasMany
    {
        return $this->hasMany(FacilityBranch::class);
    }
}

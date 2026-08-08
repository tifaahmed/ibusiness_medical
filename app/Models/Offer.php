<?php

namespace App\Models;

use App\Traits\MediaImageTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Spatie\Translatable\HasTranslations;

class Offer extends Model implements HasMedia
{
    use HasFactory;
    use HasSlug;
    use HasTranslations;
    use InteractsWithMedia;
    use MediaImageTrait;

    /**
     * The attributes that are translatable.
     *
     * @var array<int, string>
     */
    public $translatable = [
        'title',
        'short_description',
        'full_description',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'slug',
        'offerable_id',
        'offerable_type',
        'title',
        'short_description',
        'full_description',
        'phone',
        'price',
        'old_price',
        'created_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'old_price' => 'decimal:2',
        ];
    }

    /**
     * Get the options for generating the slug.
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('title')
            ->saveSlugsTo('slug');
    }

    /**
     * Get the parent offerable model (Facility, FacilityBranch, etc.).
     */
    public function offerable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Admin who created this offer.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the discount percentage.
     *
     * @return float|null
     */
    public function getDiscountPercentageAttribute(): ?float
    {
        if ($this->old_price && $this->price && $this->old_price > 0) {
            return round((($this->old_price - $this->price) / $this->old_price) * 100, 2);
        }

        return null;
    }

    /**
     * Check if the offer has a discount.
     *
     * @return bool
     */
    public function hasDiscount(): bool
    {
        return $this->old_price && $this->price && $this->old_price > $this->price;
    }

    /**
     * Scope a query to only include offers with discounts.
     */
    public function scopeWithDiscount($query)
    {
        return $query->whereNotNull('old_price')
            ->whereNotNull('price')
            ->whereColumn('old_price', '>', 'price');
    }

    /**
     * Scope a query to order offers by creation date descending.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('created_at', 'desc');
    }
}

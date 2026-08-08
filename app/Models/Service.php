<?php

namespace App\Models;

use App\Traits\MediaImageTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Service extends Model implements HasMedia
{
    use HasFactory;
    use HasSlug;
    use SoftDeletes;
    use InteractsWithMedia;
    use MediaImageTrait;

    protected $fillable = [
        'slug',
        'category_id',
        'title',
        'short_subject',
        'subject',
        'old_price',
        'new_price',
        'discount_percent',
        'governorate_id',
        'city_id',
        'iframe_location',
        'lat',
        'long',
        'tag',
        'created_by',
    ];

    protected $casts = [
        'old_price' => 'decimal:2',
        'new_price' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'category_id' => 'integer',
        'governorate_id' => 'integer',
        'city_id' => 'integer',
        'lat' => 'decimal:7',
        'long' => 'decimal:7',
    ];

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
     * Get the category for the service.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceType::class, 'category_id');
    }

    /**
     * Get the governorate for the service.
     */
    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class, 'governorate_id');
    }

    /**
     * Get the city for the service.
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    /**
     * Get the admin who created this service.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the service type for the service.
     */
    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(ServiceType::class, 'category_id');
    }

    /**
     * Get the tags attached to the service.
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'service_tag');
    }

    /**
     * Check if the service has a discount.
     *
     * @return bool
     */
    public function hasDiscount(): bool
    {
        return $this->old_price && $this->new_price && $this->old_price > $this->new_price;
    }

    /**
     * Scope a query to only include services with discounts.
     */
    public function scopeWithDiscount($query)
    {
        return $query->whereNotNull('old_price')
            ->whereNotNull('new_price')
            ->whereColumn('old_price', '>', 'new_price');
    }

    /**
     * Scope a query to order services by new price ascending.
     */
    public function scopeOrderedByPrice($query)
    {
        return $query->orderBy('new_price', 'asc');
    }

    /**
     * Scope a query to order services by creation date descending.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('image')
            ->singleFile();

        $this->addMediaCollection('gallery');
    }
}

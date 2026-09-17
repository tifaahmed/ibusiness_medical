<?php

namespace App\Models;

use App\Traits\MediaImageTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Spatie\Translatable\HasTranslations;

class Store extends Model implements HasMedia
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
        'description',
        'short_description',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'description',
        'short_description',
        'slug',
        'youtube_link',
        'offer_percent_from',
        'offer_percent_to',
        'created_by',
    ];

    protected $casts = [
        'offer_percent_from' => 'decimal:2',
        'offer_percent_to' => 'decimal:2',
    ];

    /**
     * The banner shown at the top of the store's page — its own collection
     * since it is a different shape than the square logo.
     */
    public function getHeaderAttribute(): string
    {
        $media = $this->getFirstMedia('header');

        return $media ? $media->getUrl() : '';
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('title')
            ->saveSlugsTo('slug');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Branches this store is reachable at.
     */
    public function branches(): HasMany
    {
        return $this->hasMany(StoreBranch::class);
    }

    /**
     * Products catalogued under this store.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * The gallery images/videos, in the order the admin arranged them.
     */
    public function galleries(): HasMany
    {
        return $this->hasMany(StoreGallery::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Gallery items shaped for the show page and the edit form: one list
     * mixing images and videos, each already carrying the type that decides
     * how it renders.
     */
    public function getGalleryAttribute(): array
    {
        return $this->galleries
            ->map(fn (StoreGallery $item) => [
                'id' => $item->id,
                'url' => $item->url,
                'type' => $item->type,
            ])
            ->toArray();
    }
}

<?php

namespace App\Models;

use App\Traits\MediaImageTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
    use \App\Models\Concerns\RelativisesEditorHtml;

    /** Rich-text attributes whose images are kept as host-less URLs. */
    public array $editorHtmlAttributes = ['description'];
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
        'meta_title',
        'meta_description',
        'meta_keywords',
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
        'meta_title',
        'meta_description',
        'meta_keywords',
        'slug',
        'youtube_link',
        'websites',
        'app_store_url',
        'google_play_url',
        'social_links',
        'coupons',
        'supports_shipping',
        'ships_everywhere',
        'offer_percent_from',
        'offer_percent_to',
        'online_only',
        'created_by',
    ];

    protected $casts = [
        'online_only' => 'boolean',
        'websites' => 'array',
        'social_links' => 'array',
        'coupons' => 'array',
        'supports_shipping' => 'boolean',
        'ships_everywhere' => 'boolean',
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

    /**
     * The picture shown when the store's page is shared / listed by search
     * engines. A separate media row, so changing the logo later never touches it.
     */
    public function getSeoImageAttribute(): string
    {
        $media = $this->getFirstMedia('seo_image');

        return $media ? $media->getUrl() : '';
    }

    /**
     * No SEO image yet? File a separate copy of the logo as one.
     */
    public function ensureSeoImage(): void
    {
        if ($this->getFirstMedia('seo_image')) {
            return;
        }

        $this->getFirstMedia('logo')?->copy($this, 'seo_image');
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('title')
            ->saveSlugsTo('slug');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'store_tag');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(StoreCategory::class, 'store_category_store');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Governorates this store ships to when `ships_everywhere` is false.
     */
    public function shippingGovernorates(): BelongsToMany
    {
        return $this->belongsToMany(Governorate::class, 'store_governorate');
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
     * Tie description-editor uploads to the store as hidden gallery rows. The
     * files are already on disk; only the link is created, once per path.
     *
     * @param  array<int, string>  $paths
     */
    public function attachEditorImages(array $paths): void
    {
        $disk = \Illuminate\Support\Facades\Storage::disk('public');
        $sort = (int) $this->galleries()->max('sort_order');

        foreach (array_unique(array_filter(array_map('strval', $paths))) as $path) {
            if (! \App\Support\EditorImages::isEditorPath($path, 'store') || ! $disk->exists($path)
                || $this->galleries()->where('media_path', $path)->exists()) {
                continue;
            }

            StoreGallery::create([
                'store_id' => $this->id,
                'media_path' => $path,
                'type' => StoreGallery::TYPE_IMAGE,
                'sort_order' => ++$sort,
            ]);
        }
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
        // Description images share the table but stay out of the gallery.
        return $this->galleries
            ->reject(fn (StoreGallery $item) => \App\Support\EditorImages::isAnyEditorPath($item->media_path))
            ->values()
            ->map(fn (StoreGallery $item) => [
                'id' => $item->id,
                'url' => $item->url,
                'type' => $item->type,
            ])
            ->toArray();
    }
}

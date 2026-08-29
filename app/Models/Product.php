<?php

namespace App\Models;

use App\Traits\MediaImageTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Spatie\Translatable\HasTranslations;

class Product extends Model implements HasMedia
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
        'name',
        'short_subject',
        'description',
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
        'name',
        'short_subject',
        'description',
        'slug',
        'old_price',
        'new_price',
        'cost_price',
        'profit_price',
        'product_type_id',
        'is_visible',
        'is_accessible',
        'is_purchasable',
        'admin_note',
        'banner_config',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'canonical_url',
        'created_by',
    ];

    protected $casts = [
        'banner_config' => 'array',
        'is_visible' => 'boolean',
        'is_accessible' => 'boolean',
        'is_purchasable' => 'boolean',
        'old_price' => 'decimal:2',
        'new_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'profit_price' => 'decimal:2',
    ];

    /**
     * Only the products the shop's listing may show.
     *
     * Table-qualified because the same constraint runs inside the `withCount`
     * subqueries the catalogue's sidebar counts with, where `products` is
     * joined alongside `tags` and `product_types`.
     *
     * @param  Builder<Product>  $query
     */
    public function scopeVisibleInShop(Builder $query): void
    {
        $query->where('products.is_visible', true);
    }

    /**
     * One of the three storefront switches, as the form actually submits it.
     *
     * A form carrying an image is sent as multipart, which turns every value
     * into a string — an unticked switch arrives as "0", and "0" is truthy
     * everywhere that does not check. A key that is absent altogether means
     * the form never asked, so the fallback stands.
     */
    public static function normalizeFlag(array $validated, string $key, bool $default): bool
    {
        return array_key_exists($key, $validated) && $validated[$key] !== null
            ? filter_var($validated[$key], FILTER_VALIDATE_BOOLEAN)
            : $default;
    }

    /**
     * Coerce a submitted banner config into real types.
     *
     * A form that also carries an image is sent as multipart, which turns every
     * value into a string — `enabled` would land as "0", and "0" is truthy in
     * JavaScript, so a switched-off banner would still render in the admin.
     *
     * @param  array<string, mixed>|null  $config
     * @return array<string, mixed>|null
     */
    public static function normalizeBannerConfig(?array $config): ?array
    {
        if ($config === null) {
            return null;
        }

        $number = static fn (mixed $value, ?int $default) => $value === null || $value === ''
            ? $default
            : (int) $value;

        return [
            'enabled' => filter_var($config['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'message_ar' => $config['message_ar'] ?? null,
            'message_en' => $config['message_en'] ?? null,
            'text_color' => $config['text_color'] ?? null,
            'bg_color' => $config['bg_color'] ?? null,
            'shadow_color' => $config['shadow_color'] ?? null,
            'font_size' => $number($config['font_size'] ?? null, null),
            'angle' => $number($config['angle'] ?? null, null),
            'days' => $number($config['days'] ?? null, null),
        ];
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
     * Get the product type for the product.
     */
    public function productType(): BelongsTo
    {
        return $this->belongsTo(ProductType::class);
    }

    /**
     * Get the admin who created this product.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the tags attached to the product.
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'product_tag');
    }

    /**
     * Get the gallery images for the product.
     */
    public function galleries(): HasMany
    {
        return $this->hasMany(ProductGallery::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Gallery images from the product_gallery table.
     */
    public function getGalleryAttribute(): array
    {
        return $this->galleries
            ->map(fn (ProductGallery $item) => [
                'id' => $item->id,
                'url' => Storage::disk('public')->url($item->image_path),
            ])
            ->toArray();
    }

    /**
     * Open Graph share image, in its own collection so it can be swapped (or
     * seeded from the large image) without touching the catalogue photos.
     */
    public function getOgImageAttribute(): string
    {
        $media = $this->getFirstMedia('og_image');

        if ($media) {
            return $media->getUrl();
        }

        // "Empty share image" falls back to the product's own picture, so the
        // storefront never has to reach for the site logo.
        return $this->getFirstMediaUrl('large_image')
            ?: $this->getFirstMediaUrl('small_image');
    }

    /**
     * Register the media collections for the product.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('large_image')
            ->singleFile();

        $this->addMediaCollection('small_image')
            ->singleFile();

        $this->addMediaCollection('og_image')
            ->singleFile();
    }
}

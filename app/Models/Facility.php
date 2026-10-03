<?php

namespace App\Models;

use App\Traits\MediaImageTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Spatie\Translatable\HasTranslations;

class Facility extends Model implements HasMedia
{
    use HasFactory;
    use HasSlug;
    use HasTranslations;
    use \App\Models\Concerns\RelativisesEditorHtml;

    /** Rich-text attributes whose images are kept as host-less URLs. */
    public array $editorHtmlAttributes = ['description'];
    use InteractsWithMedia;
    use MediaImageTrait;

    /*
     * A deleted facility keeps its branches, managers and audit trail, and an
     * admin can put it back. See the `deleted_at` migration for why a
     * facility is never dropped outright by the admin screens.
     */
    use SoftDeletes;

    /**
     * The attributes that are translatable.
     *
     * @var array<int, string>
     */
    public $translatable = [
        'name',
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
        'description',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'canonical_url',
        'slug',
        'facility_type_id',
        'sales_id',
        'discount_percent',
        'banner_config',
        'created_by',
    ];

    protected $casts = [
        'discount_percent' => 'decimal:2',
        'banner_config' => 'array',
    ];

    /**
     * Open Graph share image, kept in its own media collection so it can be
     * swapped without touching the logo/cover images.
     */
    public function getOgImageAttribute(): string
    {
        $media = $this->getFirstMedia('og_image');

        return $media ? $media->getUrl() : '';
    }

    /**
     * Signed contract document (PDF or image), kept in its own media collection.
     */
    public function getContractAttribute(): ?array
    {
        $media = $this->getFirstMedia('contract');

        if (! $media) {
            return null;
        }

        return [
            'url' => $media->getUrl(),
            'file_name' => $media->file_name,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
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
     * Get the facility type that owns the facility.
     */
    public function facilityType(): BelongsTo
    {
        return $this->belongsTo(FacilityType::class);
    }

    /**
     * Get the sales representative that owns the facility.
     */
    public function sales(): BelongsTo
    {
        return $this->belongsTo(Sales::class);
    }

    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * Get the branches for the facility.
     */
    public function branches(): HasMany
    {
        return $this->hasMany(FacilityBranch::class);
    }

    /**
     * Get the managers for the facility.
     */
    public function managers(): HasMany
    {
        return $this->hasMany(FacilityManager::class);
    }

    /**
     * Get all of the facility's offers.
     */
    public function offers(): MorphMany
    {
        return $this->morphMany(Offer::class, 'offerable');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'facility_tag');
    }

    /**
     * Tie description-editor uploads to the facility. They go into their own
     * media collection, so they are recorded against the facility but never
     * show up in its gallery. The original file stays where it is — the
     * description already points at it.
     *
     * @param  array<int, string>  $paths
     */
    public function attachEditorImages(array $paths): void
    {
        $disk = \Illuminate\Support\Facades\Storage::disk('public');

        foreach (array_unique(array_filter(array_map('strval', $paths))) as $path) {
            if (! \App\Support\EditorImages::isEditorPath($path, 'facility') || ! $disk->exists($path)) {
                continue;
            }

            $already = $this->getMedia('description_images')
                ->contains(fn ($media) => ($media->getCustomProperty('source_path') === $path));
            if ($already) {
                continue;
            }

            $this->addMedia($disk->path($path))
                ->preservingOriginal()
                ->withCustomProperties(['source_path' => $path])
                ->toMediaCollection('description_images');
        }
    }
}

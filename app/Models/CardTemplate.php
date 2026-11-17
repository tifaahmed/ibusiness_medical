<?php

namespace App\Models;

use App\Enums\CardTemplate\CardTemplateStatusEnum;
use App\Services\CardGenerationService;
use App\Support\CardTemplateLayoutDefaults;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Spatie\Translatable\HasTranslations;

/**
 * A reusable card design: the blank artwork plus where every generated field
 * lands on it. Cards created from a template start from `layout`; a member's
 * card can then be customised without touching the template.
 *
 * `layout` is a map of field key => box, e.g.
 *
 *   "phone": {
 *     "x": 0.4744, "y": 0.7962, "width": 0.5003, "height": 0.0382,
 *     "font_family": "Tajawal", "direction": "ltr",
 *     "color": "#000000", "font_size": 16.8
 *   }
 *
 * `sample_data` is a map of the same keys => their values — the contact lines
 * fixed for the whole template, plus placeholders for the per-member fields
 * (membership_number, qrcode, barcode) so a preview renders before any member
 * exists. See {@see CardTemplateLayoutDefaults} for the units and the
 * shipped defaults.
 */
class CardTemplate extends Model
{
    use HasFactory;
    use HasSlug;
    use HasTranslations;
    use SoftDeletes;

    public $translatable = ['name'];

    /** The printed card (every template made before kinds existed). */
    public const KIND_PHYSICAL = 'physical';

    /** The card shown on screen to the member; it also carries the full name and the membership expiry date. */
    public const KIND_SOFT = 'soft';

    /** Layout fields only a soft card draws. */
    public const SOFT_ONLY_FIELDS = ['full_name', 'expiry_date'];

    public static function kinds(): array
    {
        return [self::KIND_PHYSICAL, self::KIND_SOFT];
    }

    public function isSoft(): bool
    {
        return $this->kind === self::KIND_SOFT;
    }

    public function scopePhysical(Builder $query): Builder
    {
        return $query->where(fn ($q) => $q->where('kind', self::KIND_PHYSICAL)->orWhereNull('kind'));
    }

    public function scopeSoft(Builder $query): Builder
    {
        return $query->where('kind', self::KIND_SOFT);
    }

    /** The soft design for a member (with / without partner), else any soft design, else null (the physical one is used). */
    public static function softFor(Membership $membership): ?self
    {
        $status = $membership->partner_id ? CardTemplateStatusEnum::WITH_PARTNER : CardTemplateStatusEnum::NO_PARTNER;

        return static::query()->soft()->where('status', $status)->orderBy('id')->first()
            ?? static::query()->soft()->orderBy('id')->first();
    }

    protected $fillable = [
        'name',
        'slug',
        'status',
        'kind',
        'card_empty',
        'sample_card',
        'back_image',
        'back_logo',
        'back_settings',
        'layout',
        'sample_data',
    ];

    protected $appends = ['card_empty_url', 'sample_card_url', 'hidden_fields', 'back_url', 'back_config', 'back_image_url', 'back_logo_url'];

    /**
     * What the back shows until an admin says otherwise. Every piece can be
     * hidden; the logo and background are uploads on the template.
     */
    public const BACK_DEFAULTS = [
        'enabled' => false,
        // x/y/width/height are fractions of the card, like the front layout;
        // font_size is pixels on a 700px-wide card (CardTemplateLayoutDefaults::EDITOR_WIDTH).
        'logo' => ['visible' => true, 'x' => 0.30, 'y' => 0.08, 'width' => 0.40, 'height' => 0.46],
        'slogan' => ['visible' => true, 'text' => '', 'color' => '#1b2a4e', 'direction' => 'center', 'font_size' => 18.7, 'x' => 0.20, 'y' => 0.555, 'width' => 0.60, 'height' => 0.07],
        'title' => ['visible' => true, 'text' => 'Family Card', 'color' => '#14213d', 'direction' => 'center', 'font_size' => 31.7, 'x' => 0.20, 'y' => 0.65, 'width' => 0.60, 'height' => 0.10],
        'website' => ['visible' => true, 'text' => 'deilar.com', 'color' => '#9a6a1f', 'direction' => 'center', 'font_size' => 28, 'x' => 0.20, 'y' => 0.76, 'width' => 0.60, 'height' => 0.08],
        'qrcode' => ['visible' => false, 'mode' => 'url', 'image' => null, 'value' => 'https://deilar.com', 'x' => 0.834, 'y' => 0.74, 'width' => 0.126, 'height' => 0.20],
    ];

    protected function casts(): array
    {
        return [
            'status' => CardTemplateStatusEnum::class,
            'layout' => 'array',
            'sample_data' => 'array',
            'back_settings' => 'array',
        ];
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }

    /**
     * Every card generated from this design has to stop looking frozen the
     * moment the design changes — see
     * {@see \App\Services\CardGenerationService::invalidateCachesFor()}.
     */
    protected static function booted(): void
    {
        static::updated(function (self $template) {
            if ($template->wasChanged(['layout', 'sample_data', 'card_empty', 'status', 'kind'])) {
                app(CardGenerationService::class)->invalidateCachesFor($template);
            }
        });
    }

    /**
     * The back's settings with every default filled in.
     *
     * @return array<string, mixed>
     */
    public function resolvedBackConfig(): array
    {
        return array_replace_recursive(self::BACK_DEFAULTS, $this->back_settings ?? []);
    }

    public function hasCustomBack(): bool
    {
        return (bool) ($this->resolvedBackConfig()['enabled'] ?? false);
    }

    protected function backConfig(): Attribute
    {
        return Attribute::make(get: fn () => $this->resolvedBackConfig());
    }

    /**
     * Where every consumer (guest page, admin membership page, downloads) loads
     * the back from. The version changes whenever the settings or the uploads
     * do, so a browser never keeps showing a stale back. Null = no custom back,
     * and callers fall back to the shipped artwork.
     */
    protected function backUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->hasCustomBack() && $this->exists
                ? route('card-template.back', ['cardTemplate' => $this->id, 'v' => app(\App\Services\CardBackRenderer::class)->signature($this)], false)
                : null,
        );
    }

    protected function backImageUrl(): Attribute
    {
        return Attribute::make(get: fn () => $this->back_image ? '/'.ltrim($this->back_image, '/') : null);
    }

    protected function backLogoUrl(): Attribute
    {
        return Attribute::make(get: fn () => $this->back_logo ? '/'.ltrim($this->back_logo, '/') : null);
    }

    public function scopeWithPartner(Builder $query): Builder
    {
        return $query->where('status', CardTemplateStatusEnum::WITH_PARTNER);
    }

    public function scopeWithoutPartner(Builder $query): Builder
    {
        return $query->where('status', CardTemplateStatusEnum::NO_PARTNER);
    }

    protected function cardEmptyUrl(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => ! empty($attributes['card_empty'])
                ? '/'.ltrim($attributes['card_empty'], '/')
                : null,
        );
    }

    protected function sampleCardUrl(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => ! empty($attributes['sample_card'])
                ? '/'.ltrim($attributes['sample_card'], '/')
                : null,
        );
    }

    /**
     * layout/sample_data field keys the admin form should hide, driven by
     * CardTemplateStatusEnum::hiddenFields() so this stays a single source of
     * truth.
     */
    protected function hiddenFields(): Attribute
    {
        return Attribute::make(
            get: fn () => array_values(array_unique(array_merge(
                $this->status?->hiddenFields() ?? [],
                $this->isSoft() ? [] : self::SOFT_ONLY_FIELDS,
            ))),
        );
    }

    /**
     * The layout with any field the status hides stripped out — what a renderer
     * should actually draw. Falls back to the shipped defaults for a template
     * whose layout was never filled in.
     *
     * @return array<string, array<string, mixed>>
     */
    public function effectiveLayout(): array
    {
        $layout = $this->layout ?: CardTemplateLayoutDefaults::layout();

        return array_diff_key($layout, array_flip($this->hidden_fields));
    }

    /**
     * Restore `layout` to the shipped defaults.
     */
    public function resetLayoutToDefault(): bool
    {
        return $this->update(['layout' => CardTemplateLayoutDefaults::layout()]);
    }
}

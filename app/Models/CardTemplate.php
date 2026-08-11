<?php

namespace App\Models;

use App\Enums\CardTemplate\CardTemplateStatusEnum;
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

    protected $fillable = [
        'name',
        'slug',
        'status',
        'card_empty',
        'sample_card',
        'layout',
        'sample_data',
    ];

    protected $appends = ['card_empty_url', 'sample_card_url', 'hidden_fields'];

    protected function casts(): array
    {
        return [
            'status' => CardTemplateStatusEnum::class,
            'layout' => 'array',
            'sample_data' => 'array',
        ];
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
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
            get: fn () => $this->status?->hiddenFields() ?? [],
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

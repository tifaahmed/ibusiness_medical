<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

class Tag extends Model
{
    use HasFactory;
    use HasTranslations;
    use SoftDeletes;

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
        'icon',
        'color',
        'created_by',
    ];

    /**
     * Get the admin who created this tag.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the services that have this tag.
     */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'service_tag');
    }

    public function facilities(): BelongsToMany
    {
        return $this->belongsToMany(Facility::class, 'facility_tag');
    }

    /**
     * All tags formatted for form pickers: ordered by the current locale's
     * name and carrying both translations, so admins can read EN and AR
     * side by side while choosing.
     */
    public static function forPicker(): array
    {
        return static::query()
            ->orderBy('name->'.app()->getLocale())
            ->get(['id', 'name', 'icon', 'color'])
            ->map(fn (self $tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
                // Both languages; the plain `name` above stays for older consumers.
                'name_translations' => $tag->getTranslations('name'),
                'icon' => $tag->icon,
                'color' => $tag->color,
            ])
            ->all();
    }

    /**
     * Icons already used by other tags, each with the color and name it was
     * given, so forms can show what every previously used icon looked like.
     * One entry per icon — the most recently updated tag wins.
     */
    public static function iconUsages(?int $creatorId = null, ?int $excludeId = null): array
    {
        return static::query()
            ->whereNotNull('icon')
            ->when($creatorId !== null, fn ($q) => $q->where('created_by', $creatorId))
            ->when($excludeId !== null, fn ($q) => $q->whereKeyNot($excludeId))
            ->orderByDesc('updated_at')
            ->get(['id', 'name', 'icon', 'color'])
            ->unique('icon')
            ->map(fn (self $tag) => [
                'icon' => $tag->icon,
                'color' => $tag->color,
                // Both translations, so the form can label it per locale.
                'name' => $tag->getTranslations('name'),
            ])
            ->values()
            ->all();
    }

    /**
     * Get the products that have this tag.
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_tag');
    }
}

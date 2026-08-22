<?php

namespace App\Models;

use App\Traits\MediaImageTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Translatable\HasTranslations;

class Sales extends Model implements HasMedia
{
    use HasFactory;
    use HasTranslations;
    use InteractsWithMedia;
    use MediaImageTrait;

    public $translatable = ['name'];

    protected $fillable = [
        'name',
        'created_by',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function facilities(): HasMany
    {
        return $this->hasMany(Facility::class);
    }

    /**
     * Every locale this row actually carries.
     *
     * `name` is a plain varchar that this model nonetheless declares
     * translatable: rows written through the admin hold a `{"en": …, "ar": …}`
     * blob, while rows written by an older import hold the bare name. Reading
     * the column directly gives one of those two shapes; this gives the same
     * locale map either way.
     *
     * @return array<string, string>
     */
    public function nameTranslations(): array
    {
        $translations = array_filter(
            $this->getTranslations('name'),
            fn ($value) => trim((string) $value) !== ''
        );

        if ($translations !== []) {
            return array_map(fn ($value) => (string) $value, $translations);
        }

        $raw = trim((string) $this->getRawOriginal('name'));

        return $raw === '' ? [] : ['en' => $raw, 'ar' => $raw];
    }

    /**
     * The name to show a reader, in their locale when the row carries it.
     * Never the raw JSON blob, and never blank.
     */
    public function displayName(): string
    {
        $names = $this->nameTranslations();

        if ($names === []) {
            return "#{$this->getKey()}";
        }

        return $names[app()->getLocale()] ?? $names['ar'] ?? $names['en'] ?? reset($names);
    }
}

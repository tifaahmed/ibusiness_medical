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
        'admin_user_id',
    ];

    /**
     * Every rep as a select option; `name` carries both languages, `label` is
     * the single-language fallback.
     *
     * @return array<int, array{value: int, label: string, name: array<string, string>}>
     */
    public static function pickerOptions(): array
    {
        return static::query()->orderBy('id')->get()
            ->map(fn (Sales $sale) => [
                'value' => $sale->id,
                'label' => $sale->displayName(),
                'name' => $sale->nameTranslations(),
            ])->all();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** The admin account this rep is tied to (one-to-one). */
    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }

    /**
     * Admin accounts that may be linked to a rep: non-member accounts not
     * already tied to ANOTHER rep (the one being edited keeps its own).
     *
     * @return array<int, array{value: int, label: string}>
     */
    public static function adminOptions(?int $exceptSalesId = null): array
    {
        $taken = static::query()
            ->whereNotNull('admin_user_id')
            ->when($exceptSalesId, fn ($q) => $q->where('id', '!=', $exceptSalesId))
            ->pluck('admin_user_id');

        return User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', '!=', \App\Enums\User\UserRoleEnum::MEMBER))
            ->whereNotIn('id', $taken)
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $u) => ['value' => $u->id, 'label' => trim($u->name.' — '.($u->email ?? ''), ' —')])
            ->all();
    }

    /**
     * Reps an admin may be linked to: not tied to ANOTHER admin (this admin's own stays).
     *
     * @return array<int, array{value: int, label: string}>
     */
    public static function linkableOptions(?int $adminId = null): array
    {
        return static::query()
            ->where(fn ($q) => $q->whereNull('admin_user_id')->when($adminId, fn ($q) => $q->orWhere('admin_user_id', $adminId)))
            ->orderBy('id')->get()
            ->map(fn (Sales $s) => ['value' => $s->id, 'label' => $s->displayName()])->all();
    }

    /** Point exactly one rep (or none) at this admin; frees any other rep he held. */
    public static function linkAdmin(int $adminId, ?int $salesId): void
    {
        static::query()->where('admin_user_id', $adminId)
            ->when($salesId, fn ($q) => $q->where('id', '!=', $salesId))
            ->update(['admin_user_id' => null]);

        if ($salesId) {
            static::query()->whereKey($salesId)->update(['admin_user_id' => $adminId]);
        }
    }

    public function members(): HasMany
    {
        return $this->hasMany(Membership::class, 'sales_id');
    }

    public function stores(): HasMany
    {
        return $this->hasMany(Store::class, 'sales_id');
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

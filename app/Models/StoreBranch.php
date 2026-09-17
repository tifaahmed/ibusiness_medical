<?php

namespace App\Models;

use App\Support\PhoneNumbers;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Spatie\Translatable\HasTranslations;

class StoreBranch extends Model
{
    use HasFactory;
    use HasSlug;
    use HasTranslations;

    /**
     * What kind of line a stored phone number is — mirrors
     * {@see \App\Models\FacilityBranch}'s constants exactly, so the shared
     * `PhoneNumbers` support class and `BranchPhonesInput` component work
     * unchanged for a store branch's numbers.
     */
    public const PHONE_LANDLINE = 'landline';

    public const PHONE_MOBILE = 'phone';

    public const PHONE_WHATSAPP = 'whatsapp';

    public const PHONE_MOBILE_WHATSAPP = 'phone_whatsapp';

    public const PHONE_HOTLINE = 'hotline';

    /**
     * @var array<int, string>
     */
    public const PHONE_TYPES = [
        self::PHONE_LANDLINE,
        self::PHONE_MOBILE,
        self::PHONE_WHATSAPP,
        self::PHONE_MOBILE_WHATSAPP,
        self::PHONE_HOTLINE,
    ];

    /**
     * @var array<int, string>
     */
    public $translatable = [
        'name',
        'address',
        'area',
    ];

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'store_id',
        'governorate_id',
        'city_id',
        'latitude',
        'longitude',
        'google_location_url',
        'name',
        'slug',
        'address',
        'area',
        'phone',
        'created_by',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Phones, always read and written as typed entries — see
     * {@see \App\Models\FacilityBranch::phone()} for why this is an accessor
     * rather than an `array` cast.
     */
    protected function phone(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => PhoneNumbers::entries(
                is_array($value) ? $value : (json_decode((string) $value, true) ?: [])
            ),
            set: function ($value) {
                $entries = PhoneNumbers::entries(is_array($value) || is_string($value) ? $value : null);

                return ['phone' => $entries === [] ? null : json_encode($entries, JSON_UNESCAPED_UNICODE)];
            },
        );
    }

    /**
     * @return list<string>
     */
    public function phoneNumbers(): array
    {
        return array_map(fn (array $entry) => $entry['number'], $this->phone ?? []);
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(function ($model) {
                $store = $model->store ?? Store::find($model->store_id);
                $storeName = $store ? $store->title : '';

                if ($model->name) {
                    return $storeName.' '.$model->name;
                }

                return $storeName.' '.($model->address ?? 'branch');
            })
            ->saveSlugsTo('slug');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}

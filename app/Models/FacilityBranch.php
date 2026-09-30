<?php

namespace App\Models;

use App\Support\PhoneNumbers;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Spatie\Translatable\HasTranslations;

class FacilityBranch extends Model
{
    use HasFactory;
    use HasSlug;
    use HasTranslations;

    /*
     * A branch removed from its facility's form (or cascaded with the whole
     * facility) is soft-deleted, not dropped — see the `deleted_at` migration.
     */
    use SoftDeletes;

    /**
     * What kind of line a stored phone number is. A branch keeps one entry per
     * number — {"number": "0663400006", "type": "landline"} — so a patient can
     * be told which numbers take a call and which take a WhatsApp message.
     */
    public const PHONE_LANDLINE = 'landline';

    public const PHONE_MOBILE = 'phone';

    public const PHONE_WHATSAPP = 'whatsapp';

    /** One number that is both a phone line and a WhatsApp account. */
    public const PHONE_MOBILE_WHATSAPP = 'phone_whatsapp';

    /**
     * A short national number — 16064, 19011 — that belongs to no area code and
     * is dialled as it stands. Shorter than a landline, which is what tells the
     * two apart.
     */
    public const PHONE_HOTLINE = 'hotline';

    /**
     * The types a phone entry may declare, in the order the form offers them.
     *
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
     * The attributes that are translatable.
     *
     * @var array<int, string>
     */
    public $translatable = [
        'name',
        'address',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'facility_id',
        'governorate_id',
        'city_id',
        'area_id',
        'latitude',
        'longitude',
        'google_location_url',
        'name',
        'slug',
        'address',
        'phone',
        'created_by',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    /**
     * Phones, always read and written as typed entries.
     *
     * Deliberately an accessor rather than an `array` cast: rows written before
     * types existed hold a flat list of strings, spreadsheet imports still
     * arrive that way, and both have to keep working. Normalising here means
     * every reader — the admin screens, the public API, the migration exporter
     * — sees one shape without each of them having to cope with the old one.
     */
    protected function phone(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => PhoneNumbers::entries(
                is_array($value) ? $value : (json_decode((string) $value, true) ?: [])
            ),
            // An empty list is stored as NULL rather than "[]", which is what
            // "this branch has no phone" has always looked like in this column.
            set: function ($value) {
                $entries = PhoneNumbers::entries(is_array($value) || is_string($value) ? $value : null);

                return ['phone' => $entries === [] ? null : json_encode($entries, JSON_UNESCAPED_UNICODE)];
            },
        );
    }

    /**
     * Just the numbers this branch can be reached on, without their types.
     *
     * @return list<string>
     */
    public function phoneNumbers(): array
    {
        return array_map(fn (array $entry) => $entry['number'], $this->phone ?? []);
    }

    /**
     * Get the options for generating the slug.
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(function ($model) {
                // Generate slug from name if available, otherwise combine facility name with address
                $facility = $model->facility ?? Facility::find($model->facility_id);
                $facilityName = $facility ? $facility->name : '';

                if ($model->name) {
                    return $facilityName.' '.$model->name;
                }

                return $facilityName.' '.($model->address ?? 'branch');
            })
            ->saveSlugsTo('slug');
    }

    /**
     * Get the facility that owns the branch.
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    private static ?bool $areaColumn = null;

    private static function hasAreaColumn(): bool
    {
        return self::$areaColumn ??= Schema::hasColumn('facility_branches', 'area_id');
    }

    /**
     * The neighbourhood / village unit inside the city, when known. Optional.
     */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    /**
     * An area only means something inside its own city. Whoever moves a branch to
     * another city — the form, an import, the AI place sweep — would otherwise
     * leave the old city's area behind, so it is dropped here, once, for all of them.
     */
    protected static function booted(): void
    {
        static::saving(function (self $branch) {
            // Code ships before its migration on this host: until `area_id` exists,
            // leave it out of the write rather than fail every branch save.
            if (! static::hasAreaColumn()) {
                $branch->offsetUnset('area_id');

                return;
            }

            if ($branch->area_id === null || ! $branch->isDirty(['city_id', 'area_id'])) {
                return;
            }

            $areaCity = Area::query()->whereKey($branch->area_id)->value('city_id');
            if ($areaCity === null || (int) $areaCity !== (int) $branch->city_id) {
                $branch->area_id = null;
            }
        });
    }

    /**
     * Get all of the branch's offers.
     */
    public function offers(): MorphMany
    {
        return $this->morphMany(Offer::class, 'offerable');
    }
}

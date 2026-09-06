<?php

namespace App\Models;

use App\Support\SiteSettings;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Spatie\Translatable\HasTranslations;

/**
 * A single row of site-wide configuration, keyed by `slug`.
 *
 * This is the editable half of the application's configuration: values that
 * belong to whoever runs the site (phone number, address, logo) rather than to
 * whoever deploys it (database credentials, API keys — those stay in .env).
 * Adding a new one is adding a row; nothing here has to be edited to support a
 * key it has never seen.
 *
 * Every value is stored as text and read back through `value_type`, so a row
 * can hold a number, a flag or a JSON blob without the schema changing. Read
 * them through App\Support\SiteSettings, which caches the whole table.
 */
class Setting extends Model
{
    use HasFactory;
    use HasTranslations;

    public const TYPE_STRING = 'string';

    public const TYPE_TEXT = 'text';

    public const TYPE_NUMBER = 'number';

    public const TYPE_BOOLEAN = 'boolean';

    public const TYPE_URL = 'url';

    public const TYPE_EMAIL = 'email';

    public const TYPE_PHONE = 'phone';

    /**
     * A stored file path, not a link: the upload lives on the public disk and
     * resolves through url(). Nothing here fetches a remote image.
     */
    public const TYPE_IMAGE = 'image';

    public const TYPE_JSON = 'json';

    /**
     * The value types a row may declare, for validation and admin selects.
     *
     * @var array<int, string>
     */
    public const VALUE_TYPES = [
        self::TYPE_STRING,
        self::TYPE_TEXT,
        self::TYPE_NUMBER,
        self::TYPE_BOOLEAN,
        self::TYPE_URL,
        self::TYPE_EMAIL,
        self::TYPE_PHONE,
        self::TYPE_IMAGE,
        self::TYPE_JSON,
    ];

    /**
     * Where an uploaded setting image is stored on the public disk.
     */
    public const IMAGE_DIRECTORY = 'settings';

    /**
     * The largest image this application accepts, in kilobytes.
     */
    public const IMAGE_MAX_KILOBYTES = 5120;

    /**
     * What an upload may actually weigh here, in kilobytes.
     *
     * PHP drops an oversized upload before the framework ever sees it: the
     * request arrives with an empty body and the admin is told the key and the
     * name are missing, which says nothing about the real problem. So the limit
     * this application enforces is never larger than the one the server will
     * honour — the form shows it and validation rejects against it, both with a
     * message that names the size.
     */
    public static function maxUploadKilobytes(): int
    {
        $limits = [self::IMAGE_MAX_KILOBYTES];

        foreach (['upload_max_filesize', 'post_max_size'] as $directive) {
            $bytes = self::iniBytes((string) ini_get($directive));

            // 0 means unlimited (post_max_size accepts it); nothing to cap by.
            if ($bytes > 0) {
                $limits[] = intdiv($bytes, 1024);
            }
        }

        return max(1, min($limits));
    }

    /**
     * A php.ini size ('5M', '512K', '2G', '5242880') as a count of bytes.
     */
    private static function iniBytes(string $value): int
    {
        $value = trim($value);

        if ($value === '') {
            return 0;
        }

        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }

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
        'slug',
        'name',
        'value',
        'value_type',
    ];

    /**
     * Keep the cached map honest: any write through the model drops it, so the
     * next read rebuilds from the database.
     */
    protected static function booted(): void
    {
        static::saved(fn () => SiteSettings::forget());
        static::deleted(fn () => SiteSettings::forget());
    }

    /**
     * This row's stored text read through its `value_type`.
     */
    public function castValue(): mixed
    {
        $value = $this->value;

        if ($value === null || $value === '') {
            return $this->value_type === self::TYPE_BOOLEAN ? false : null;
        }

        return match ($this->value_type) {
            self::TYPE_BOOLEAN => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            self::TYPE_NUMBER => str_contains($value, '.') ? (float) $value : (int) $value,
            self::TYPE_JSON => json_decode($value, true),
            self::TYPE_IMAGE => $this->url(),
            default => $value,
        };
    }

    /**
     * A browsable address for an `image` row.
     *
     * Uploads live on the public disk and are stored as a disk-relative path;
     * assets shipped with the application (the bundled logo, for one) sit under
     * public/ instead. Checking the disk first tells the two apart, so a row
     * seeded with a bundled path keeps working once someone replaces it by
     * uploading a file.
     */
    public function url(): ?string
    {
        $path = trim((string) $this->value);

        if ($path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $disk = Storage::disk('public');

        if ($disk->exists($path)) {
            return $disk->url($path);
        }

        return asset($path);
    }
}

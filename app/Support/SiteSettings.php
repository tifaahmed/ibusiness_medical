<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Reads and writes the `settings` table — the site details an administrator
 * owns: the company name, phone, email, address, logo and the URLs printed on
 * cards and invoices.
 *
 * The whole table is small and read on nearly every page, so it is cached as
 * one map keyed by slug and dropped whenever a Setting is saved or deleted.
 * Callers get a value already cast to its declared type:
 *
 *     SiteSettings::get('deilar_phone');            // '01020709993'
 *     SiteSettings::get('deilar_logo');             // '/images/logo/dielar.png'
 *     SiteSettings::put('deilar_email', $address);  // creates the row if new
 *
 * Nothing here throws on a missing key — an unset setting is a missing detail,
 * not a broken install, so it returns the default and the page still renders.
 */
class SiteSettings
{
    /**
     * The cache key holding every setting, slug => model.
     */
    private const CACHE_KEY = 'settings.all';

    /**
     * Every setting keyed by slug.
     *
     * @return Collection<string, Setting>
     */
    public static function all(): Collection
    {
        try {
            $cached = Cache::get(self::CACHE_KEY);

            if ($cached instanceof Collection) {
                return $cached;
            }

            $settings = Setting::query()->get()->keyBy('slug');

            Cache::forever(self::CACHE_KEY, $settings);

            return $settings;
        } catch (QueryException) {
            // A table this needs is not there yet — `settings` itself, or the
            // `cache` table behind the default store — which is what a deploy
            // looks like in the moments before its migrations run. Every page
            // reads a setting through here, so throwing would take the whole
            // site down over details every caller has a default for. Nothing is
            // cached on this path, so the rows appear as soon as they exist.
            return new Collection;
        }
    }

    /**
     * The cast value behind a slug, or `$default` when the row is missing or
     * has never been filled in.
     */
    public static function get(string $slug, mixed $default = null): mixed
    {
        $setting = self::all()->get($slug);

        if (! $setting instanceof Setting) {
            return $default;
        }

        $value = $setting->castValue();

        return $value === null ? $default : $value;
    }

    /**
     * Whether a slug has a row at all, filled in or not.
     */
    public static function has(string $slug): bool
    {
        return self::all()->has($slug);
    }

    /**
     * Write a setting, creating the row when the slug is new.
     *
     * `$valueType` and `$name` describe a row being created; an existing row
     * keeps the type and label whoever set it up chose, so a plain value write
     * cannot silently change how the rest of the site reads it.
     *
     * @param  array<string, string>|string|null  $name
     */
    public static function put(string $slug, mixed $value, string $valueType = Setting::TYPE_STRING, array|string|null $name = null): Setting
    {
        $setting = Setting::query()->firstOrNew(['slug' => $slug]);

        if (! $setting->exists) {
            $setting->name = $name ?? $slug;
            $setting->value_type = $valueType;
        }

        $setting->value = self::encode($value, $setting->value_type);
        $setting->save();

        return $setting;
    }

    /**
     * Drop the cached map. Only needed when the table has been changed behind
     * the model's back — a seeder writing through the query builder, say.
     */
    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Flatten a value to the text the column stores.
     */
    protected static function encode(mixed $value, string $valueType): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($valueType) {
            Setting::TYPE_BOOLEAN => $value ? '1' : '0',
            Setting::TYPE_JSON => is_string($value) ? $value : json_encode($value),
            default => (string) $value,
        };
    }
}

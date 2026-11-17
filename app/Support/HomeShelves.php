<?php

namespace App\Support;

use App\Http\Controllers\Api\V1\Mobile\FacilityController;
use App\Http\Controllers\Api\V1\Mobile\StoreController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;

/**
 * The six strips of the app's home page (best / best offers / newest, for stores and then for medical places), twelve cards each.
 * Built once a day by `php artisan home:shelves-refresh` and kept in the cache, so opening the home page never runs these queries:
 * `GET /mobile/home-shelves` only reads the saved copy (and builds it on the very first call if the command has not run yet).
 */
class HomeShelves
{
    public const LOCALES = ['ar', 'en'];

    public const COUNT = 12;

    /** key => [controller, sort] */
    private const SHELVES = [
        'store_visits' => ['store', 'visits'],
        'store_discount' => ['store', 'discount'],
        'store_newest' => ['store', 'newest'],
        'medical_visits' => ['medical', 'visits'],
        'medical_discount' => ['medical', 'discount'],
        'medical_newest' => ['medical', 'newest'],
    ];

    private static function key(string $locale): string
    {
        return 'mobile:home-shelves:'.$locale;
    }

    /** The saved copy for a locale; built (and saved) when there is none yet. */
    public static function get(string $locale): array
    {
        $locale = in_array($locale, self::LOCALES, true) ? $locale : 'ar';

        return Cache::rememberForever(self::key($locale), fn () => self::build($locale));
    }

    /** Rebuild every locale and replace the saved copies (a failed build keeps the old copy). */
    public static function refresh(): array
    {
        $done = [];

        foreach (self::LOCALES as $locale) {
            Cache::forever(self::key($locale), self::build($locale));
            $done[] = $locale;
        }

        return $done;
    }

    public static function build(string $locale): array
    {
        $previous = App::getLocale();
        App::setLocale($locale);

        try {
            $shelves = [];

            foreach (self::SHELVES as $name => [$kind, $sort]) {
                $request = Request::create('/', 'GET', ['sort' => $sort, 'per_page' => self::COUNT, 'lang' => $locale]);
                $controller = $kind === 'store' ? app(StoreController::class) : app(FacilityController::class);
                $shelves[$name] = array_slice($controller->index($request)->getData(true)['data'] ?? [], 0, self::COUNT);
            }

            return ['shelves' => $shelves, 'built_at' => now()->toIso8601String()];
        } finally {
            App::setLocale($previous);
        }
    }
}

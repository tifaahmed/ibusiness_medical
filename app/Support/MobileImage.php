<?php

namespace App\Support;

/**
 * The picture a mobile client is told to draw. It never comes back empty: the first url that exists wins, and when
 * none does, the app's own logo stands in, so a card in the app always has something to show.
 */
class MobileImage
{
    /** The brand logo as an absolute url (the same logo the dashboard and the favicons use). */
    public static function logo(): string
    {
        $setting = SiteSettings::get('deilar_logo');

        return is_string($setting) && $setting !== '' ? $setting : asset(config('app.logo'));
    }

    /** First non-empty url of the list, else the app logo. */
    public static function pick(?string ...$urls): string
    {
        foreach ($urls as $url) {
            if (is_string($url) && $url !== '') {
                return $url;
            }
        }

        return static::logo();
    }

    /** The same, for a list of picture urls (a gallery): empty becomes the logo alone. */
    public static function list(array $urls): array
    {
        $urls = array_values(array_unique(array_filter($urls, fn ($u) => is_string($u) && $u !== '')));

        return $urls !== [] ? $urls : [static::logo()];
    }
}

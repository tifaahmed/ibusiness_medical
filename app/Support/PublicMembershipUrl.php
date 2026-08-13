<?php

namespace App\Support;

/**
 * The web address a member card's QR code points at.
 *
 * Cards are printed, so whatever is encoded here outlives every deploy that
 * follows it — treat a change to this class as a change to paper already in
 * people's wallets. It resolves to the Deilar marketing site, which is the
 * property members are told to visit; this application only stands in for it
 * when `services.deilar.url` is unset, so an install with no marketing site
 * still produces a scannable card.
 *
 * Both applications accept a slug *or* a membership number in the last
 * segment, which is why nothing here has to care which one it was handed.
 */
class PublicMembershipUrl
{
    /**
     * The origin every public membership URL hangs off, with no trailing slash.
     */
    public static function base(): string
    {
        $deilar = config('services.deilar.url');

        $base = is_string($deilar) && trim($deilar) !== ''
            ? trim($deilar)
            : (string) config('app.url');

        return rtrim($base, '/');
    }

    /**
     * The public card page for a slug, or the lookup form when there is none.
     *
     * An empty slug is not an error worth failing a card render over: a QR code
     * that lands on the lookup form still gets the member where they are going,
     * one typed number later.
     */
    public static function forSlug(?string $slug): string
    {
        $slug = trim((string) $slug);

        if ($slug === '') {
            return self::base().'/membership';
        }

        return self::base().'/membership/'.rawurlencode($slug);
    }

    /**
     * What a card's QR code encodes: the same public page, with the slug
     * repeated as a `?slug=` query so whatever handles the scan can read the
     * membership straight off the address instead of parsing the path.
     *
     * Kept in step with membershipQrUrl() in
     * resources/js/composables/usePublicMembershipUrl.js, which is what the
     * browser-side renderers encode.
     */
    public static function qrForSlug(?string $slug): string
    {
        $slug = trim((string) $slug);
        $url = self::forSlug($slug);

        return $slug === '' ? $url : $url.'?slug='.rawurlencode($slug);
    }
}

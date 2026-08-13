import { usePage } from '@inertiajs/vue3';

/**
 * Where a member card's QR code points — the Deilar marketing site, not this
 * admin application.
 *
 * The value is shared from HandleInertiaRequests so every canvas renderer here
 * encodes the same address the server-rendered PNG does; a preview that
 * disagrees with the printed card is worse than no preview at all. See
 * App\Support\PublicMembershipUrl for the server side of this.
 */
export function publicMembershipBaseUrl() {
    const shared = usePage().props?.publicMembershipBaseUrl;

    return (typeof shared === 'string' && shared !== '' ? shared : window.location.origin).replace(/\/+$/, '');
}

/**
 * The public card page for a slug (or membership number — both applications
 * resolve either), or the lookup form when there is nothing to link to.
 */
export function publicMembershipUrl(slug) {
    const base = publicMembershipBaseUrl();
    const trimmed = String(slug ?? '').trim();

    return trimmed === '' ? `${base}/membership` : `${base}/membership/${encodeURIComponent(trimmed)}`;
}

/**
 * Tag a public membership address with `?slug=`, leaving one that already
 * carries the query untouched — printed cards outlive the links that made
 * them, so re-tagging must never double up.
 */
export function withSlugQuery(url, slug) {
    const trimmed = String(slug ?? '').trim();

    if (url === '' || trimmed === '' || /[?&]slug=/.test(url)) {
        return url;
    }

    return `${url}${url.includes('?') ? '&' : '?'}slug=${encodeURIComponent(trimmed)}`;
}

/**
 * What a card's QR code encodes: the public card page with the slug repeated
 * as a query, so whatever handles the scan can read the membership straight
 * off the address instead of parsing the path.
 *
 * Every renderer — this page's canvas, the batch generator, and the PHP one in
 * App\Support\PublicMembershipUrl::qrForSlug — goes through the same shape, so
 * a card printed today and one printed last year scan the same.
 */
export function membershipQrUrl(slug) {
    return withSlugQuery(publicMembershipUrl(slug), slug);
}

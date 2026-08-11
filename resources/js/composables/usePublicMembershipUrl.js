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

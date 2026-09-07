/**
 * The shape a branch keeps its phone numbers in, mirrored on the client.
 *
 * Kept in step with App\Models\FacilityBranch::PHONE_TYPES and
 * App\Support\PhoneNumbers::entries() — every screen that shows or edits a
 * branch phone reads it through here, so the flat strings older rows still
 * hold render the same as the typed entries the form now writes.
 */
export const PHONE_LANDLINE = 'landline';
export const PHONE_MOBILE = 'phone';
export const PHONE_WHATSAPP = 'whatsapp';
export const PHONE_MOBILE_WHATSAPP = 'phone_whatsapp';
// A short national number — 16064, 19011 — dialled as it stands.
export const PHONE_HOTLINE = 'hotline';

export const PHONE_TYPES = [PHONE_LANDLINE, PHONE_MOBILE, PHONE_WHATSAPP, PHONE_MOBILE_WHATSAPP, PHONE_HOTLINE];

export const DEFAULT_PHONE_TYPE = PHONE_MOBILE;

const FALLBACK_LABELS = {
    [PHONE_LANDLINE]: 'Landline',
    [PHONE_MOBILE]: 'Phone',
    [PHONE_WHATSAPP]: 'WhatsApp',
    [PHONE_MOBILE_WHATSAPP]: 'Phone & WhatsApp',
    [PHONE_HOTLINE]: 'Hotline',
};

/**
 * The English label for a type, used when the translation file has no entry.
 */
export function phoneTypeLabel(type) {
    return FALLBACK_LABELS[type] || FALLBACK_LABELS[DEFAULT_PHONE_TYPE];
}

/**
 * Whether a type means the number is reachable on WhatsApp — what a "message
 * on WhatsApp" link should key off, rather than testing two strings by hand.
 */
export function isWhatsapp(type) {
    return type === PHONE_WHATSAPP || type === PHONE_MOBILE_WHATSAPP;
}

/**
 * Anything a branch's `phone` may arrive as, read into { number, type } rows.
 *
 * Accepts the typed entries the API now returns, the flat strings rows written
 * before types held, and a single string. Blank numbers are dropped; an
 * unrecognised type falls back to the default rather than rendering an empty
 * select.
 */
export function normalizePhoneEntries(raw) {
    if (!raw) return [];

    const list = Array.isArray(raw) ? raw : [raw];

    return list
        .map((item) => {
            if (item && typeof item === 'object') {
                return {
                    number: String(item.number ?? '').trim(),
                    type: PHONE_TYPES.includes(item.type) ? item.type : DEFAULT_PHONE_TYPE,
                };
            }

            return { number: String(item ?? '').trim(), type: DEFAULT_PHONE_TYPE };
        })
        .filter((entry) => entry.number !== '');
}

/**
 * Just the numbers, for a place that only has room for the digits.
 */
export function phoneNumbersOf(raw) {
    return normalizePhoneEntries(raw).map((entry) => entry.number);
}

/**
 * Shared building blocks behind the "Advanced Error Track" tab that sits next
 * to every admin submit button's error badge (see
 * Components/ui/ErrorTrackButton.vue and ValidationErrorsDialog.vue).
 *
 * A store calls buildDebugLog() right before a submit fires, and
 * recordResponse() once the server (or a client-side check that stopped the
 * request before it went out) answers — giving a full "what was sent / what
 * came back" trace that can be copied and handed to a programmer, instead of
 * a screenshot of just the field-level message.
 */

// A File isn't worth (or safe) to serialize whole — swap it for a
// description. Passwords are masked the same way: whether one was typed
// matters for debugging, the characters typed do not.
const describeValue = (key, value) => {
    if (value instanceof File) return `<file: ${value.name}, ${value.size} bytes, ${value.type || 'unknown type'}>`;
    if (Array.isArray(value)) return value.map((item) => describeValue(key, item));
    if (value && typeof value === 'object' && !(value instanceof Date)) {
        return Object.fromEntries(Object.entries(value).map(([k, v]) => [k, describeValue(k, v)]));
    }
    if (/password/i.test(key) && value) return `<hidden, ${String(value).length} chars>`;
    return value;
};

/** A plain, JSON-safe copy of a form's fields, safe to hand to a programmer. */
export const sanitizeFields = (data) => {
    const out = {};
    Object.entries(data || {}).forEach(([key, value]) => {
        out[key] = describeValue(key, value);
    });
    return out;
};

/**
 * Build a debug log. Call with just request details right before a submit
 * fires (response stays null until something answers); or pass
 * `responseErrors` too, for a request that was blocked client-side and never
 * actually sent.
 */
export const buildDebugLog = ({ method, url, fields, note = null, responseErrors = undefined, responseNote = null }) => {
    const log = {
        request: { method, url, at: new Date().toISOString(), fields: sanitizeFields(fields), note },
        response: null,
    };

    if (responseErrors !== undefined) {
        log.response = { at: new Date().toISOString(), errors: responseErrors, note: responseNote };
    }

    return log;
};

/** Fill in the response half of an existing log once the server answers. */
export const recordResponse = (debugLog, errors, note = null) => {
    if (!debugLog) return debugLog;
    debugLog.response = { at: new Date().toISOString(), errors, note };
    return debugLog;
};

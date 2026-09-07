<?php

return [
    // Shown when a signed-in account has no area it can be sent to (no admin
    // role and no membership page), so the session is ended and the visitor is
    // returned to the login screen.
    'no_landing_page' => 'Your account has no page to open yet. Please sign in again or contact support.',

    /*
     * The storefront's phone login, answered to the Deilar site over the
     * key-gated partner API. Every one of these travels back as `message`
     * beside a machine-readable `reason`, so the partner can show its own
     * wording and fall back to these when it has none.
     */
    'otp' => [
        'invalid_phone' => 'That does not look like a phone number.',
        'unknown_phone' => 'We could not find a membership with that phone number.',
        'cooldown' => 'A code has already been sent. Please wait before asking for another.',
        'not_configured' => 'Phone sign-in is switched off at the moment.',
        'send_failed' => 'The code could not be sent. Please try again shortly.',
        'sent' => 'A verification code has been sent.',
        'sent_fixed' => 'Enter your verification code to continue.',
        'invalid_code' => 'That code is not correct.',
        'expired_code' => 'That code has expired. Please ask for a new one.',
        'too_many_attempts' => 'Too many incorrect codes. Please ask for a new one.',
        'verified' => 'Signed in successfully.',
    ],
];

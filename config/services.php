<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
     * The shared key for /api/v1/partner/*, which other properties (the Deilar
     * marketing site) call server-to-server to read member data. Leaving it
     * unset closes those endpoints rather than opening them.
     */
    'partner_api' => [
        'key' => env('PARTNER_API_KEY'),
    ],

    /*
     * The Deilar marketing site — the public face members are pointed at. The
     * QR code printed on a card encodes {url}/membership/{slug}, so this value
     * ends up on physical cards: see App\Support\PublicMembershipUrl.
     */
    'deilar' => [
        'url' => env('DEILAR_URL'),
    ],

    /*
     * Google Gemini, used by every admin AI helper: "Generate SEO with AI" on
     * the facility and product forms, the list-wide "Fill SEO with AI" sweeps,
     * and the "Fix English with AI" tools. Leaving the key unset disables those
     * server-side rather than failing mid-request — nothing else depends on it.
     */
    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-3.5-flash-lite'),
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        'timeout' => (int) env('GEMINI_TIMEOUT', 45),
    ],

    /*
     * ABS Courier & Freight Systems — the courier orders are handed to from
     * the admin's order page. `POST /api/v1/create-shipment` books the delivery
     * to the customer and returns its AWB; passing `location_id` on that same
     * call auto-creates the pickup, so one request both ships the parcel and
     * asks for it to be collected. Leaving the key unset hides the ship button
     * rather than failing mid-request, the way `gemini` above does.
     *
     * `base_url` defaults to STAGING deliberately. A half-configured install
     * must not be able to book a real courier by accident: pointing it at
     * https://core.absegy.com is a decision somebody makes in .env, on purpose.
     */
    'abs' => [
        'key' => env('ABS_API_KEY'),
        'base_url' => env('ABS_BASE_URL', 'https://dev.core.absegy.com'),
        /* Optional when authenticating with an API key — the key already
           identifies the sub-account it was issued for. */
        'sub_account' => env('ABS_SUB_ACCOUNT_ID'),
        /* The warehouse the courier collects from. Set it and every shipment
           starts as AWAITING_PICKUP; leave it unset and it starts as NEW, with
           the pickup arranged separately in the ABS portal. */
        'location_id' => env('ABS_LOCATION_ID'),
        /* Optional service/product id, which drives ABS's own fee calculation. */
        'product_id' => env('ABS_PRODUCT_ID'),
        'timeout' => (int) env('ABS_TIMEOUT', 30),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
     * The SMS gateway behind the storefront's phone login — Advance Messaging
     * Systems' GWE2S HTTP API (see "API GWE2S Secure"). One GET per message:
     * `websms?user=&pass=&sid=&mno=&type=&text=`, with `accesskey` accepted in
     * place of the user/password pair.
     *
     * `sender` is the SID printed as the message's origin and has to be one the
     * gateway has registered for this account — an unregistered one comes back
     * as HTTP06. Leaving the credentials unset turns SMS delivery OFF rather
     * than failing mid-request: `OtpSettings` then falls back to the fixed
     * code, which is exactly the state a fresh install should be in.
     */
    'sms' => [
        'url' => env('SMS_API_URL', 'https://gwe2s.broadnet.me:8443/websmpp/websms'),
        'user' => env('SMS_API_USER'),
        'password' => env('SMS_API_PASSWORD'),
        /* Usable instead of user + password; the gateway accepts either. */
        'access_key' => env('SMS_API_ACCESS_KEY'),
        'sender' => env('SMS_API_SENDER'),
        /*
         * Every Egyptian number this sends to is stored as 01xxxxxxxxx, and the
         * gateway wants the country code on the front. Configurable because the
         * same code has to work if the member list ever reaches past Egypt.
         */
        'country_code' => env('SMS_API_COUNTRY_CODE', '20'),
        'timeout' => (int) env('SMS_API_TIMEOUT', 15),
        /*
         * The gateway is served from a non-standard port with a certificate
         * chain some hosts do not carry. Verification stays ON by default —
         * turning it off is a decision somebody makes in .env, knowing it.
         */
        'verify' => filter_var(env('SMS_API_VERIFY_SSL', true), FILTER_VALIDATE_BOOLEAN),
    ],

    /*
     * Paymob (Accept) — online card / wallet payment for storefront and app orders.
     * Leave `secret_key` blank and the "pay online" choice is hidden everywhere.
     * `api_key` is only needed for the admin's "check with Paymob" inquiry.
     * `integration_ids` is a comma-separated list of the payment-method ids to offer.
     */
    'paymob' => [
        'base_url' => env('PAYMOB_BASE_URL', 'https://accept.paymob.com'),
        'secret_key' => env('PAYMOB_SECRET_KEY'),
        'public_key' => env('PAYMOB_PUBLIC_KEY'),
        'api_key' => env('PAYMOB_API_KEY'),
        'hmac' => env('PAYMOB_HMAC_SECRET'),
        /* PAYMOB_INTEGRATION_IDS wins; PAYMOB_CARD_INTEGRATION_ID (one card integration) is the shorter way to say the same for a single method. */
        'integration_ids' => array_values(array_filter(array_map('trim', explode(',', (string) (env('PAYMOB_INTEGRATION_IDS') ?: trim(env('PAYMOB_CARD_INTEGRATION_ID', '').','.env('PAYMOB_WALLET_INTEGRATION_ID', ''), ',')))))),
        /* The two ways of paying online, each its own Paymob integration, so a client can open the card page or the wallet page directly.
           Card falls back to every integration above (the old single-list setup); wallet has no fallback — no id, no wallet button. */
        'card_integration_ids' => array_values(array_filter(array_map('trim', explode(',', (string) (env('PAYMOB_CARD_INTEGRATION_ID') ?: env('PAYMOB_CARD_INTEGRATION_IDS', '')))))),
        'wallet_integration_ids' => array_values(array_filter(array_map('trim', explode(',', (string) (env('PAYMOB_WALLET_INTEGRATION_ID') ?: env('PAYMOB_WALLET_INTEGRATION_IDS', '')))))),
        /* Hosted-iframe id from the old flow. Not used by the Unified Checkout we open; kept so the value has a home. */
        'iframe_id' => env('PAYMOB_IFRAME_ID'),
        'currency' => env('PAYMOB_CURRENCY', 'EGP'),
        'timeout' => (int) env('PAYMOB_TIMEOUT', 30),
        /* Where the buyer's browser lands after paying; `{code}` is the order code. */
        'return_url' => env('PAYMOB_RETURN_URL'),
        /* Same, for a membership subscription; `{code}` is `SUB<id>`. */
        'subscription_return_url' => env('PAYMOB_SUBSCRIPTION_RETURN_URL'),
        /* Comma-separated prefixes a client may ask to be returned to (the app's scheme, the storefront). */
        'allowed_return_urls' => array_values(array_filter(array_map('trim', explode(',', (string) env('PAYMOB_ALLOWED_RETURN_URLS', ''))))),
    ],

];

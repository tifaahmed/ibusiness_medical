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
     * OpenAI, used by the admin "Generate SEO with AI" button on the facility
     * form. Leaving the key unset disables the button server-side rather than
     * failing mid-request — nothing else in the app depends on it.
     */
    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'timeout' => (int) env('OPENAI_TIMEOUT', 45),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];

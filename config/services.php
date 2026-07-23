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

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // ── Firebase Cloud Messaging (push notifications mobiles) ────────────────
    // Le Service Account est lu depuis storage/app/firebase/immopro.json
    // Aucune clé à configurer ici.

    // ── Semoa CashPay API V2.0 ────────────────────────────────────────────────
    'semoa' => [
        'env'           => env('SEMOA_ENV', 'sandbox'),
        'base_url'      => env('SEMOA_BASE_URL', 'https://sandbox.semoa-payments.com/api'),
        'username'      => env('SEMOA_USERNAME'),
        'password'      => env('SEMOA_PASSWORD'),
        'client_id'     => env('SEMOA_CLIENT_ID'),
        'client_secret' => env('SEMOA_CLIENT_SECRET'),
        'ledger'        => env('SEMOA_LEDGER', 'de7a9b8e-74be-4ced-a263-7323e242cf19'),
        'simulate'      => env('SEMOA_SIMULATE', false),
    ],

];

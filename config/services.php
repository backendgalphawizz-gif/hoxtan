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

    /*
    |--------------------------------------------------------------------------
    | Metals-API (metals-api.com) — live gold & silver rates
    |--------------------------------------------------------------------------
    |
    | Get your API key from https://metals-api.com/
    | Gold uses the India endpoint (INR per gram). Silver is converted to INR/g.
    |
    */
    'metals_api' => [
        'key' => env('METALS_API_KEY'),
        'base_url' => env('METALS_API_BASE_URL', 'https://metals-api.com/api'),
        'gold_symbol' => env('METALS_API_GOLD_SYMBOL', 'VISA-24k'),
        'currency' => env('METALS_API_CURRENCY', 'INR'),
    ],

];

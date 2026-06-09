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

    'ultramsg' => [
        'base_url' => env('ULTRAMSG_BASE_URL', 'https://api.ultramsg.com'),
        'instance_id' => env('ULTRAMSG_INSTANCE_ID'),
        'token' => env('ULTRAMSG_TOKEN'),
        'timeout' => (int) env('ULTRAMSG_TIMEOUT', 30),
    ],

    'frontend' => [
        'url' => env('FRONTEND_URL', 'http://localhost:5173'),
    ],

    'stripe' => [
        'secret_key' => env('STRIPE_SECRET_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'currency' => env('STRIPE_CURRENCY', 'mxn'),
        'success_url' => env('STRIPE_SUCCESS_URL', env('FRONTEND_URL', 'http://localhost:5173') . '/checkout/success?session_id={CHECKOUT_SESSION_ID}'),
        'cancel_url' => env('STRIPE_CANCEL_URL', env('FRONTEND_URL', 'http://localhost:5173') . '/checkout/cancel'),
    ],

];

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

    'testing_recipients' => [
        'abandoned_cart_email' => env('TEST_ABANDONED_CART_EMAIL', env('APP_ENV') === 'local' ? 'alekzleon03.aa@gmail.com' : null),
    ],

    'frontend' => [
        'url' => env('FRONTEND_URL', 'http://localhost:5173'),
    ],

    'backend' => [
        'url' => env('BACKEND_URL', env('APP_URL', 'http://localhost:8000')),
    ],

    'stripe' => [
        'secret_key' => env('STRIPE_SECRET_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'currency' => env('STRIPE_CURRENCY', 'mxn'),
        'success_url' => env('STRIPE_SUCCESS_URL', env('FRONTEND_URL', 'http://localhost:5173') . '/checkout/success?session_id={CHECKOUT_SESSION_ID}'),
        'cancel_url' => env('STRIPE_CANCEL_URL', env('FRONTEND_URL', 'http://localhost:5173') . '/checkout/cancel'),
        'subscription_success_url' => env('STRIPE_SUBSCRIPTION_SUCCESS_URL', env('FRONTEND_URL', 'http://localhost:5173') . '/billing/success?session_id={CHECKOUT_SESSION_ID}'),
        'subscription_cancel_url' => env('STRIPE_SUBSCRIPTION_CANCEL_URL', env('FRONTEND_URL', 'http://localhost:5173') . '/billing/cancel'),
        'connect' => [
            'account_type' => env('STRIPE_CONNECT_ACCOUNT_TYPE', 'standard'),
            'webhook_secret' => env('STRIPE_CONNECT_WEBHOOK_SECRET'),
            'require_for_store_checkout' => (bool) env('STRIPE_CONNECT_REQUIRE_FOR_STORE_CHECKOUT', true),
            'return_path' => env('STRIPE_CONNECT_RETURN_PATH', '/admin/payments/stripe/return'),
            'refresh_path' => env('STRIPE_CONNECT_REFRESH_PATH', '/admin/payments/stripe/refresh'),
        ],
    ],

    'mercadopago' => [
        'client_id' => env('MERCADOPAGO_CLIENT_ID'),
        'client_secret' => env('MERCADOPAGO_CLIENT_SECRET'),
        'redirect_uri' => env('MERCADOPAGO_REDIRECT_URI'),
        'oauth_success_url' => env('MERCADOPAGO_OAUTH_SUCCESS_URL', env('FRONTEND_URL', 'http://localhost:5173').'/settings/payments'),
        'oauth_error_url' => env('MERCADOPAGO_OAUTH_ERROR_URL', env('FRONTEND_URL', 'http://localhost:5173').'/settings/payments'),
        'oauth_state_ttl_seconds' => (int) env('MERCADOPAGO_OAUTH_STATE_TTL_SECONDS', 600),
        'connect_timeout' => (int) env('MERCADOPAGO_CONNECT_TIMEOUT', 10),
        'timeout' => (int) env('MERCADOPAGO_TIMEOUT', 20),
        'notification_url' => env('MERCADOPAGO_NOTIFICATION_URL'),
        'webhook_secret' => env('MERCADOPAGO_WEBHOOK_SECRET'),
    ],

    'cloudflare_for_saas' => [
        'api_token' => env('CLOUDFLARE_API_TOKEN'),
        'zone_id' => env('CLOUDFLARE_ZONE_ID'),
        'platform_zone' => env('CLOUDFLARE_PLATFORM_ZONE', 'cloudishop.mx'),
        'cname_target' => env('CLOUDFLARE_SAAS_CNAME_TARGET', 'domains.cloudishop.mx'),
        'fallback_origin' => env('CLOUDFLARE_SAAS_FALLBACK_ORIGIN', 'origin.cloudishop.mx'),
        'ssl_method' => env('CLOUDFLARE_SAAS_SSL_METHOD', 'http'),
        'ssl_type' => env('CLOUDFLARE_SAAS_SSL_TYPE', 'dv'),
    ],

];

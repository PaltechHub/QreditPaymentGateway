<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Qredit API Key
    |--------------------------------------------------------------------------
    |
    | Your Qredit API key. You can obtain this from your Qredit dashboard.
    | This is required for authenticating with the Qredit API.
    |
    */
    'api_key' => env('QREDIT_API_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Sandbox Mode
    |--------------------------------------------------------------------------
    |
    | When enabled, the package will use Qredit's sandbox environment for
    | testing. Set this to false in production.
    |
    */
    'sandbox' => env('QREDIT_SANDBOX', true),

    /*
    |--------------------------------------------------------------------------
    | Production URL
    |--------------------------------------------------------------------------
    |
    | The production API URL for Qredit. This will be used when sandbox
    | mode is disabled.
    |
    */
    'production_url' => env('QREDIT_PRODUCTION_URL', 'https://api.qredit.com/gw-checkout/api/v1'),

    /*
    |--------------------------------------------------------------------------
    | Language
    |--------------------------------------------------------------------------
    |
    | The default language for API responses. Supported: 'en', 'ar'
    |
    */
    'language' => env('QREDIT_LANGUAGE', 'ar'),

    /*
    |--------------------------------------------------------------------------
    | Debug Mode
    |--------------------------------------------------------------------------
    |
    | When enabled, all API requests and responses will be logged for
    | debugging purposes. Disable this in production.
    |
    */
    'debug' => env('QREDIT_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Token Caching
    |--------------------------------------------------------------------------
    |
    | Whether to cache authentication tokens to reduce API calls. The tokens
    | will be cached for their validity period minus 60 seconds.
    |
    */
    'cache_token' => env('QREDIT_CACHE_TOKEN', true),

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    |
    | Configure webhook handling for receiving real-time updates from Qredit.
    |
    */
    'webhook' => [
        'enabled' => env('QREDIT_WEBHOOK_ENABLED', true),
        'path' => env('QREDIT_WEBHOOK_PATH', '/qredit/webhook'),
        'prefix' => env('QREDIT_WEBHOOK_PREFIX', ''),
        'middleware' => [
            // Add any middleware you want to apply to webhook routes
        ],
        'secret' => env('QREDIT_WEBHOOK_SECRET', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Signature Verification
    |--------------------------------------------------------------------------
    |
    | Whether to verify webhook signatures. It's highly recommended to keep
    | this enabled in production for security.
    |
    */
    'verify_webhook_signature' => env('QREDIT_VERIFY_WEBHOOK_SIGNATURE', true),

    /*
    |--------------------------------------------------------------------------
    | Timeout Configuration
    |--------------------------------------------------------------------------
    |
    | Configure connection and request timeouts in seconds.
    |
    */
    'timeout' => [
        'connect' => env('QREDIT_CONNECT_TIMEOUT', 30),
        'request' => env('QREDIT_REQUEST_TIMEOUT', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Retry Configuration
    |--------------------------------------------------------------------------
    |
    | Configure automatic retry behavior for failed requests.
    |
    */
    'retry' => [
        'enabled' => env('QREDIT_RETRY_ENABLED', true),
        'max_attempts' => env('QREDIT_RETRY_MAX_ATTEMPTS', 3),
        'delay' => env('QREDIT_RETRY_DELAY', 1000), // milliseconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Currency
    |--------------------------------------------------------------------------
    |
    | The default currency code to use for payments when not specified.
    |
    */
    'default_currency' => env('QREDIT_DEFAULT_CURRENCY', 'ILS'),

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Configure logging for the package.
    |
    */
    'logging' => [
        'channel' => env('QREDIT_LOG_CHANNEL', 'stack'),
        'level' => env('QREDIT_LOG_LEVEL', 'debug'),
    ],
];
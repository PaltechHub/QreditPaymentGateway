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
    | Sandbox URL
    |--------------------------------------------------------------------------
    |
    | The sandbox/testing API URL for Qredit. This will be used when sandbox
    | mode is enabled.
    |
    */
    'sandbox_url' => env('QREDIT_SANDBOX_URL', 'http://185.57.122.58:2030/gw-checkout/api/v1'),

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
    | The default language for API responses. Supported: 'EN', 'AR'
    |
    */
    'language' => env('QREDIT_LANGUAGE', 'EN'),

    /*
    |--------------------------------------------------------------------------
    | Client Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for client identification in API requests.
    | These headers are included in every API request.
    |
    */
    'client' => [
        'type' => env('QREDIT_CLIENT_TYPE', 'MP'),
        'version' => env('QREDIT_CLIENT_VERSION', '1.0.0'),
        'authorization' => env('QREDIT_CLIENT_AUTHORIZATION', 'HmacSHA512_O'),
    ],

    /*
    |--------------------------------------------------------------------------
    | SDK Mode Configuration
    |--------------------------------------------------------------------------
    |
    | When sdk_enabled is false, adds Authorization header to all requests
    |
    */
    'sdk_enabled' => env('QREDIT_SDK_ENABLED', true),

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
    | Token Management
    |--------------------------------------------------------------------------
    |
    | Configure how authentication tokens are stored and managed.
    | Strategy options:
    | - 'cache': Use Laravel's cache (Redis/Memcached) - Best for single server
    | - 'database': Store in database - Best for multi-server setups
    | - 'hybrid': Cache with database fallback - Best of both worlds
    |
    | WHY TOKEN CACHING IS ESSENTIAL:
    | 1. Reduces API calls (most APIs have rate limits)
    | 2. Improves performance (no auth request for every API call)
    | 3. Reduces latency (cached token vs network request: 0.001s vs 0.5s)
    | 4. Cost efficiency (some APIs charge per request)
    | 5. Better UX (faster response times)
    |
    */
    'token_storage' => [
        'enabled' => env('QREDIT_TOKEN_CACHE_ENABLED', true),
        'strategy' => env('QREDIT_TOKEN_STRATEGY', 'cache'), // cache, database, hybrid
        'ttl_buffer' => env('QREDIT_TOKEN_TTL_BUFFER', 300), // Refresh 5 min before expiry
    ],

    // Legacy config (kept for backward compatibility)
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
        'enabled' => env('QREDIT_WEBHOOK_ENABLED', false),
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

    /*
    |--------------------------------------------------------------------------
    | Payment Channels Configuration
    |--------------------------------------------------------------------------
    |
    | Configure which payment channels are enabled for your integration.
    | Available channels: qr, card, wallet
    |
    */
    'payment_channels' => [
        'qr' => env('QREDIT_PAYMENT_CHANNEL_QR', true),
        'card' => env('QREDIT_PAYMENT_CHANNEL_CARD', false),
        'wallet' => env('QREDIT_PAYMENT_CHANNEL_WALLET', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Shipping Provider Codes
    |--------------------------------------------------------------------------
    |
    | Map your shipping provider names to Qredit shipping codes.
    |
    */
    'shipping_providers' => [
        'standard' => 'standard',
        'express' => 'express',
        'optimus' => 'optimus',
        'aramex' => 'aramex',
        'dhl' => 'dhl',
    ],

    /*
    |--------------------------------------------------------------------------
    | Order Configuration
    |--------------------------------------------------------------------------
    |
    | Configure order-related settings.
    |
    */
    'order' => [
        'lock_when_paid' => env('QREDIT_LOCK_ORDER_WHEN_PAID', false),
        'payment_expiration_minutes' => env('QREDIT_PAYMENT_EXPIRATION_MINUTES', 1440), // 24 hours
    ],
];
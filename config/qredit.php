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
    | Secret API Key
    |--------------------------------------------------------------------------
    |
    | Server-side secret used to compute the HMAC SHA512 signature that goes
    | into the Authorization header of every request (merchant guide §7).
    | Never expose this to browsers or client-side code.
    |
    */
    'secret_key' => env('QREDIT_SECRET_KEY', ''),

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
    | UAT base URL per merchant guide + Jira story.
    |
    */
    'sandbox_url' => env('QREDIT_SANDBOX_URL', 'https://apitest.qredit.tech/gw-checkout/api/v1'),

    /*
    |--------------------------------------------------------------------------
    | Production URL
    |--------------------------------------------------------------------------
    |
    | Production base URL per merchant guide + Jira story.
    |
    */
    'production_url' => env('QREDIT_PRODUCTION_URL', 'https://api.qredit.tech/gw-checkout/api/v1'),

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
    | Supported Locales
    |--------------------------------------------------------------------------
    |
    | Locale definitions used by the SDK's checkout views (payment-method
    | component, redirect page). Each entry declares its text direction so
    | the views render RTL/LTR automatically without hardcoding locale codes.
    |
    | Add your own locales here; the SDK checks `direction` to set `dir=`.
    |
    */
    'locales' => [
        ['code' => 'en', 'direction' => 'ltr', 'native' => 'English'],
        ['code' => 'ar', 'direction' => 'rtl', 'native' => 'العربية'],
        ['code' => 'he', 'direction' => 'rtl', 'native' => 'עברית'],
        ['code' => 'fa', 'direction' => 'rtl', 'native' => 'فارسی'],
        ['code' => 'ur', 'direction' => 'rtl', 'native' => 'اردو'],
        ['code' => 'fr', 'direction' => 'ltr', 'native' => 'Français'],
        ['code' => 'tr', 'direction' => 'ltr', 'native' => 'Türkçe'],
    ],

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
        /*
        | Gateway-side client-type / version handshake.
        |
        | - `type` is fixed to 'TP' — the tenant-platform flavor the gateway
        |   expects. Don't override; other values lock you out of /auth/token.
        | - `version` is REQUIRED and must be set per tenant. Qredit issues a
        |   unique Client-Version string per merchant account (e.g. 'ccc1.0',
        |   'abc2.3'). In single-tenant deployments, set QREDIT_CLIENT_VERSION
        |   in .env. In multi-tenant deployments, bind a custom
        |   CredentialProvider that supplies it per tenant — there is NO
        |   package-level default.
        */
        'type' => 'TP',
        'version' => env('QREDIT_CLIENT_VERSION'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Signing Configuration
    |--------------------------------------------------------------------------
    |
    | Controls the Authorization header format and HMAC SHA512 output case.
    | The Angular reference implementation the gateway ships uppercases the
    | output (see docs/SIGNING.md). Default to 'upper'; flip to 'lower' via
    | QREDIT_SIGNATURE_CASE=lower only if a specific deployment demands it.
    |
    */
    'signing' => [
        'scheme' => env('QREDIT_AUTH_SCHEME', 'HmacSHA512_O'),
        'case' => env('QREDIT_SIGNATURE_CASE', 'upper'), // 'upper' (default) | 'lower'
    ],

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
    | API Explorer (development tool)
    |--------------------------------------------------------------------------
    |
    | An in-browser Postman replacement for testing signed Qredit requests.
    | Guarded by super-admin auth + this toggle. NEVER enable in production —
    | it exposes raw API responses and accepts arbitrary payloads.
    |
    | `path` controls the URL slug so you can obscure it from scanners.
    |
    */
    'explorer' => [
        'enabled' => env('QREDIT_API_EXPLORER', false),
        'path' => env('QREDIT_API_EXPLORER_PATH', 'qredit-explorer'),
    ],

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
    'verify_webhook_signature' => env('QREDIT_VERIFY_WEBHOOK_SIGNATURE', false),

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

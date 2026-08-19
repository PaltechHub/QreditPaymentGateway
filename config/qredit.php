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
    | Order Configuration
    |--------------------------------------------------------------------------
    |
    | Defaults applied when generating outbound payment requests:
    |
    | - lock_when_paid: Sent as `lockOrderWhenPaid` on createPayment. When true,
    |   the gateway refuses additional payment attempts once any payment settles
    |   against the order. Safe default `true` — a single order shouldn't be
    |   charged twice. Override via QREDIT_LOCK_ORDER_WHEN_PAID=false for flows
    |   that intentionally split an order across multiple payments.
    |
    | - payment_expiration_minutes: Lifetime in minutes for hosted-checkout
    |   links (createPayment `expiration` field). Qredit's portal 404s once the
    |   window closes. 30 days (43200) is the default — generous enough for
    |   asynchronous bill-pay flows, short enough that regenerating supersedes
    |   stale links.
    |
    */
    'order' => [
        'lock_when_paid' => (bool) env('QREDIT_LOCK_ORDER_WHEN_PAID', true),
        'payment_expiration_minutes' => (int) env('QREDIT_PAYMENT_EXPIRATION_MINUTES', 43200),
    ],

    'redirect_urls' => [
        'store' => env('QREDIT_REDIRECT_STORE', 'hybrid'),
        'ttl_minutes' => (int) env('QREDIT_REDIRECT_TTL_MINUTES', 60),
        'blocked_schemes' => ['javascript', 'data', 'file', 'vbscript', 'blob'],
    ],

    /*
    | Path the `Route::qreditSign()` macro is registered on. Only change this if
    | you pass a custom path to the macro — the provider uses it to whitelist the
    | endpoint for CORS, which the widget's cross-origin POST depends on.
    */
    'sign_path' => env('QREDIT_SIGN_PATH', 'qredit/sign'),

    /*
    |--------------------------------------------------------------------------
    | Checkout widget
    |--------------------------------------------------------------------------
    |
    | - script_url: Where the BlockBuilders loader is fetched from. Their own
    |   nginx, on an unversioned path they replace in place — by design, so there
    |   is nothing to pin: no matching git tag, no per-version copy on the host,
    |   and jsdelivr stops at v1.0.9. It went 1.1.0 -> 1.2.0 in a day under us.
    |   Everything we rely on is asserted in the widget contract check; run it
    |   against a fresh download before blaming checkout on our own code.
    |
    |   Two live problems: the host is `portaltest` (UAT), and a third party can
    |   change the script on our checkout page whenever they like. Mirroring the
    |   file is the fix for both — it is ~4KB and only iframes `gateway_url`, so
    |   a copy forks nothing of their payment logic.
    |
    | - gateway_url: Origin the widget iframes for the payment form itself. Sent
    |   as `serverUrl` on init. MUST be set explicitly: 1.0.9 hardcoded the
    |   production gateway, while 1.1.0+ defaults to BlockBuilders' UAT portal —
    |   so leaving it unset silently points live checkout at UAT. Follows the
    |   `sandbox` flag unless QREDIT_WIDGET_GATEWAY_URL overrides it.
    |
    |   1.2.0 dropped `serverUrl` from their merchant docs while leaving it in
    |   the code (upstream calls it a development-only concern). We depend on it
    |   in production, so the contract check asserts it still overrides.
    |
    | - webview_signed_ttl_minutes: Lifetime of the signed /qredit/webview/show
    |   URL handed to a mobile app. Long enough for the customer to finish paying.
    |
    | - default_height / default_width: Dimensions of the widget iframe on a
    |   normal web page. The widget does NOT auto-resize and the loader sets
    |   `scrolling="no"`, so anything taller than this scrolls inside the iframe
    |   (the Flutter app handles its own wheel events). Measured content runs to
    |   roughly 1250px on desktop and taller on mobile, where the payment-method
    |   cards stack — so some inner scroll is unavoidable. 1000px matches the
    |   loader's own default and keeps most of the flow visible without handing
    |   a full viewport to the iframe.
    |
    | - webview_height: Height used by the /qredit/webview/show page only. That
    |   page is a full-bleed shell for a native WebView, where filling the screen
    |   is the point, so it stays `100vh`.
    |
    */
    'widget' => [
        'script_url' => env(
            'QREDIT_WIDGET_SCRIPT_URL',
            'https://portaltest.qredit.tech/sdk/payment-widget.min.js',
        ),
        'gateway_url' => env('QREDIT_WIDGET_GATEWAY_URL', env('QREDIT_SANDBOX', true)
            ? 'https://portaltest.qredit.tech/plugin/'
            : 'https://pay.blockbuilders.ps/plugin/'),
        'webview_signed_ttl_minutes' => (int) env('QREDIT_WEBVIEW_SIGNED_TTL_MINUTES', 120),
        'default_height' => env('QREDIT_WIDGET_HEIGHT', '1000px'),
        'default_width' => env('QREDIT_WIDGET_WIDTH', '100%'),
        'webview_height' => env('QREDIT_WEBVIEW_HEIGHT', '100vh'),
    ],
];

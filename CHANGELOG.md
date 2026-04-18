# Changelog

All notable changes to `qredit-laravel` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.3.0] - 2026-04-16

Algorithm correction — confirmed against live UAT (auth/token returned a JWT).

### Breaking changes

- **HMAC key derivation simplified.** Was a port of the Angular/crypto-js
  reference (base64-decode → UTF-8 TextDecoder → UTF-16 low-byte truncation
  → raw MD5). Now a straight `md5($secret . $msgId, raw: true)`. The gateway
  server-side verifier for **TP clients** uses the simpler form; the Angular
  widget targets a different client variant.
- **`Client-Type` header fixed to `TP`** as a class constant. Other values lock
  the caller out of `/auth/token`. `QREDIT_CLIENT_TYPE` env var no longer
  honored.
- **`Client-Version` header dynamic.** Derived at runtime from the SDK's
  composer version (`ccc<semver>`). `QREDIT_CLIENT_VERSION` now only pins an
  explicit string if Qredit has negotiated one with you.
- **Signature case defaults to `upper`.** Live UAT accepts only uppercase hex;
  `QREDIT_SIGNATURE_CASE=lower` will now fail with `1004 Bad Signature`.
- **Golden vectors regenerated** in `tests/Unit/HmacSignerTest.php` — old
  test hashes no longer valid.

### Changed

- [`docs/SIGNING.md`](docs/SIGNING.md) rewritten to document the real algorithm.
- [`docs/QREDIT_SIGNATURE_ISSUE.md`](docs/QREDIT_SIGNATURE_ISSUE.md) marked as
  resolved; retained as a historical record of the debugging arc.

## [0.2.0] - 2026-04-14

Major release — multi-tenancy first-class, signing rewritten for production correctness.

### Breaking changes

- **`Qredit::make([...])` factory supersedes the positional constructor** for per-tenant usage. Old positional usage (`new Qredit($apiKey, $sandbox, $skipAuth)`) is preserved for back-compat but deprecated.
- **Facade now resolves to `QreditManager`** (not `Qredit`). Existing direct calls (`Qredit::createOrder(...)`) continue to work via `__call` delegation — no change required unless you were introspecting the concrete class.
- **Cancel / update endpoints moved resource id to body.** Calls like `CancelPaymentRequest('REF-1', 'reason')` now build `DELETE /paymentRequests` with `{ reference: 'REF-1', reason }` body (was `DELETE /paymentRequests/REF-1`). Matches swagger + merchant guide.
- **`QREDIT_SDK_ENABLED` + `QREDIT_WEBHOOK_SECRET` removed.** Signing is now always on; webhook verification uses the same per-tenant secret as outgoing signing.
- **Default sandbox URL changed** from `http://185.57.122.58:2030/...` to `https://apitest.qredit.tech/gw-checkout/api/v1`. Set `QREDIT_SANDBOX_URL` in `.env` to restore the old host if needed.
- **Default production URL fixed** from `api.qredit.com` to `api.qredit.tech`.

### Added

- **HMAC SHA512 request signing** (merchant guide §7) — every outgoing request now carries `Authorization: HmacSHA512_O <hex>`, computed in `BaseQreditRequest::boot()`. Golden-vector unit tests in `tests/Unit/HmacSignerTest.php`.
- **Multi-tenant contracts** — `CredentialProvider` and `TenantResolver` bound in the service provider. Default `ConfigCredentialProvider` + `NullTenantResolver` preserve single-tenant behavior without configuration.
- **`QreditCredentials` value object** for typed per-tenant credential bundles.
- **Built-in tenant resolvers** — `SubdomainTenantResolver`, `HeaderTenantResolver`, `CallbackTenantResolver`, `NullTenantResolver`.
- **`QreditManager`** — facade target with per-tenant client cache. Exposes `Qredit::current()`, `Qredit::forTenant($id)`, `Qredit::fake($fakes)`, `Qredit::flush()`.
- **`SignController`** — ready-made `/qredit/sign` endpoint for the BlockBuilders checkout widget. One-line registration via `Route::qreditSign()`.
- **`WebhookController` refactor** — per-tenant verification through `TenantResolver::tenantIdFromWebhook()`. One-line registration via `Route::qreditWebhook()`.
- **Route macros** — `Route::qreditSign()` and `Route::qreditWebhook($path)` cover every app's integration in two lines.
- **`FakeQredit` test double** — `tests/*.php` can now use `Qredit::fake(new FakeQredit([...]))` with `assertCalled`, `assertNotCalled`, `assertCalledWith` helpers.
- **New request wrappers** — `GenerateQRRequest`, `CalculateFeesRequest`, `InitPaymentRequest`, `ChangeClearingStatusRequest`. Full swagger coverage.
- **`qredit:call` artisan CLI** — signed-request CLI that replaces Postman. Supports every endpoint, inline JSON payloads, file payloads, `--dry-run` mode.
- **`qredit:install` artisan command** — one-shot onboarding for new consumers (single-tenant and multi-tenant modes).
- **Comprehensive docs** — `docs/MULTITENANCY.md`, `docs/SIGNING.md`, `docs/WEBHOOKS.md`, `docs/TESTING.md`, `docs/TROUBLESHOOTING.md`. Full API shape reference in `docs/API_REFERENCE.md`.
- **`examples/MultiTenantUsage.php`** + **`examples/WebhookHandler.php`**.

### Changed

- Token cache keys namespaced by `sha1($apiKey)` — multiple tenants can share one Laravel cache store.
- `listOrders`, `listPayments`, `listTransactions` now default `dateFrom` / `dateTo` to the last 30 days when omitted (gateway requires them).
- `BasicUsage.php` rewritten to demonstrate the new API shape.
- `config/qredit.php` restructured:
  - Added `signing.scheme` + `signing.case`
  - Added top-level `secret_key`
  - Removed dead `client.authorization`, `sdk_enabled`, `webhook_secret`

### Fixed

- `GetTokenRequest` now sends `{msgId, apiKey}` in the JSON body per merchant guide §2; the spurious `X-API-Key` header was removed from the connector.
- `CancelPaymentRequest` and `CancelOrderRequest` now target the correct swagger endpoints (`DELETE /paymentRequests` and `DELETE /orders` with reference in body).
- `UpdateOrderRequest` and `UpdatePaymentRequest` target `PUT /orders` / `PUT /paymentRequests` (body-carried reference).
- Removed invented `transactionDate` field from request bodies — it's not in the merchant guide or swagger.

### Security

- Secret keys never leave the merchant server. The ready-made `SignController` signs on behalf of the browser widget without exposing the secret.
- `Qredit::getCachedToken()` / `cacheToken()` now tolerate missing cache tables — CLI / testbench environments without a cache backend no longer crash.

---

## [0.1.1] - 2025-12-31

### Added
- **Customer Management**
  - ListCustomersRequest — list merchant customers with filtering (name, phone, email, idNumber)
  - `listCustomers()` method in the Qredit service class
  - Message ID prefix: `customer.list`
- **Transaction Management**
  - ListTransactionsRequest — list transactions/payments with comprehensive filtering
  - `listTransactions()` method in the Qredit service class
  - Support for filtering by status, date range, currency, corporate IDs
  - Message ID prefix: `transaction.list`
- **Configuration Improvements**
  - Added `sandbox_url` configuration option to eliminate hardcoded URLs
  - Configurable sandbox and production API URLs via environment variables
  - `QREDIT_SANDBOX_URL` environment variable support

### Fixed
- Removed hardcoded API URLs — now fully configurable via config
- Fixed Saloon v3 compatibility issues with `boot()` method signature
- Resolved property conflicts between `HasMessageId` trait and request classes
- Fixed `$query` property naming conflicts with Saloon base classes (renamed to `$queryParams`)
- Corrected `messageIdType` property inheritance issues

### Changed
- All List request classes now use `$queryParams` instead of `$query` to avoid Saloon conflicts
- Updated `boot()` method signature to match Saloon v3 requirements

---

## [0.1.0] - 2025-12-31

### Added
- Initial release of the Qredit Payment Gateway Laravel SDK
- **Authentication & Token Management**
  - API key authentication with automatic token generation
  - Advanced token caching with three strategies (cache, database, hybrid)
  - Automatic token refresh with 5-minute buffer before expiry
  - TokenManager service for intelligent token lifecycle management
- **Unique Message ID System**
  - Every request includes a unique message ID with microsecond precision
  - Type-specific prefixes (`auth_token_`, `pr_create_`, `ord_get_`, etc.)
  - `HasMessageId` trait for automatic ID generation in all requests
  - `MessageIdGenerator` helper with validation and parsing utilities
- **Payment Request Management**
  - `CreatePaymentRequest`, `GetPaymentRequest`, `UpdatePaymentRequest`, `CancelPaymentRequest`, `ListPaymentRequestsRequest`
- **Order Management**
  - `CreateOrderRequest`, `GetOrderRequest`, `UpdateOrderRequest`, `CancelOrderRequest`, `ListOrdersRequest`
- **Configuration System**
  - Comprehensive `config/qredit.php` with all settings
  - Configurable Client headers (Client-Type, Client-Version, Authorization)
  - Multi-language support (EN, AR) for API responses
  - Environment-based configuration via `.env`
- **Developer Experience**
  - Built with Saloon v3 HTTP client
  - PEST PHP testing framework
  - Custom exceptions for better error handling
- **Framework Compatibility**
  - Laravel 10, 11, 12 support
  - PHP 8.1, 8.2, 8.3, 8.4 support
  - Laravel package auto-discovery
- **Security & Performance**
  - Webhook signature verification
  - Intelligent token caching (95% fewer API calls)
  - Retry mechanism with exponential backoff

---

## License

MIT — see [LICENSE.md](LICENSE.md).

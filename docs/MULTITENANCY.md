# Multi-tenant integration

The SDK treats "one set of Qredit credentials per tenant" as the baseline. Single-tenant usage is just the degenerate case where the bound `CredentialProvider` returns the same credentials every time.

## Mental model

```
              ┌──────────────────────────────────────────────────────┐
              │                    Your Laravel app                    │
              │                                                        │
Request ─────▶│  TenantResolver.currentTenantId($request)  ──▶  "shop-b"
              │                                                        │
              │  CredentialProvider.credentialsFor("shop-b")           │
              │              │                                         │
              │              ▼                                         │
              │  QreditCredentials { apiKey, secretKey, sandbox, … }   │
              │              │                                         │
              │              ▼                                         │
              │  QreditManager.forTenant("shop-b")                     │
              │              │                                         │
              │              ▼                                         │
              │  Qredit client (connector + token cache)  ────────────┐│
              │                                                        ││
              └────────────────────────────────────────────────────────┘│
                                                                        ▼
                                                          Qredit gateway
```

**The two contracts — `TenantResolver` and `CredentialProvider` — are the entire integration surface.** Everything else (signing, routes, webhook verification, token cache) is automatic once those are bound.

## The two contracts in detail

### `CredentialProvider`

```php
namespace Qredit\LaravelQredit\Contracts;

use Qredit\LaravelQredit\Tenancy\QreditCredentials;

interface CredentialProvider
{
    public function credentialsFor(?string $tenantId = null): QreditCredentials;
    public function isConfiguredFor(?string $tenantId = null): bool;
}
```

Rules:

- **Accept a nullable `$tenantId`.** When null, fall back to the current request's tenant. When non-null (queue / console), DO NOT read request state — use the argument.
- **Never cache beyond the request.** Tenant config can change while the worker is warm; re-read every call. The SDK caches the resulting *client* per-request, not your credentials.
- **Throw `QreditException`** when the tenant exists but has no Qredit config. The facade layer catches this and surfaces a meaningful error to the caller.
- **`isConfiguredFor()` must be cheap.** Payment-method "is available?" checks call it on every page load.

### `TenantResolver`

```php
namespace Qredit\LaravelQredit\Contracts;

use Illuminate\Http\Request;

interface TenantResolver
{
    public function currentTenantId(?Request $request = null): ?string;
    public function tenantIdFromWebhook(Request $request): ?string;
}
```

Rules:

- `currentTenantId()` runs on every HTTP request that hits a signed endpoint. Keep it O(1) — it should NOT hit the database unless cached.
- `tenantIdFromWebhook()` is separate because webhooks often hit a shared domain (so subdomain/hostname logic fails) and need to pick the tenant from a route parameter or custom header.
- **Returning `null` is valid.** The SDK treats it as "use the global default credentials" — which is exactly what single-tenant apps want.

## Built-in resolvers

| Class | Tenant source | Best fit |
|---|---|---|
| `NullTenantResolver` | Always `null` | Single-tenant apps (default binding) |
| `SubdomainTenantResolver` | Leftmost subdomain | Stancl Tenancy, Laravel Octane + subdomain tenancy |
| `HeaderTenantResolver` | `X-Tenant-Id` header (configurable) | API-first apps, mobile backends |
| `CallbackTenantResolver` | Any closure | Bespoke logic — Bagisto hostname lookup, RBAC scopes, etc. |

All live in `src/Tenancy/`.

## Integration recipes

### Bagisto SAAS (channel-scoped credentials)

Bagisto stores per-channel config in the `core_config` table. The integration is:

```php
// packages/Webkul/Qredit/src/Services/BagistoChannelCredentialProvider.php
namespace Webkul\Qredit\Services;

use Qredit\LaravelQredit\Contracts\CredentialProvider;
use Qredit\LaravelQredit\Tenancy\QreditCredentials;
use Qredit\LaravelQredit\Exceptions\QreditException;

class BagistoChannelCredentialProvider implements CredentialProvider
{
    public function credentialsFor(?string $tenantId = null): QreditCredentials
    {
        $channelCode = $tenantId ?? core()->getRequestedChannelCode();

        $apiKey    = core()->getConfigData("sales.payment_methods.qredit.public_api_key", $channelCode);
        $secretKey = core()->getConfigData("sales.payment_methods.qredit.secret_api_key", $channelCode);

        if (! $apiKey || ! $secretKey) {
            throw new QreditException("Qredit not configured for channel [{$channelCode}].");
        }

        return new QreditCredentials(
            apiKey:        $apiKey,
            secretKey:     $secretKey,
            sandbox:       (bool) core()->getConfigData("sales.payment_methods.qredit.sandbox", $channelCode),
            signatureCase: 'upper',   // Live UAT accepts only uppercase — don't expose this as a per-tenant toggle.
            tenantId:      (string) $channelCode,
        );
    }

    public function isConfiguredFor(?string $tenantId = null): bool
    {
        $channelCode = $tenantId ?? core()->getRequestedChannelCode();

        return filled(core()->getConfigData("sales.payment_methods.qredit.public_api_key", $channelCode))
            && filled(core()->getConfigData("sales.payment_methods.qredit.secret_api_key", $channelCode));
    }
}
```

```php
// packages/Webkul/Qredit/src/Services/BagistoChannelTenantResolver.php
namespace Webkul\Qredit\Services;

use Illuminate\Http\Request;
use Qredit\LaravelQredit\Contracts\TenantResolver;

class BagistoChannelTenantResolver implements TenantResolver
{
    public function currentTenantId(?Request $request = null): ?string
    {
        return core()->getRequestedChannelCode();
    }

    public function tenantIdFromWebhook(Request $request): ?string
    {
        // Route: Route::qreditWebhook('/qredit/webhook/{tenant}');
        return $request->route('tenant');
    }
}
```

```php
// packages/Webkul/Qredit/src/Providers/QreditServiceProvider.php
use Qredit\LaravelQredit\Contracts\CredentialProvider;
use Qredit\LaravelQredit\Contracts\TenantResolver;

public function register(): void
{
    $this->app->bind(CredentialProvider::class, \Webkul\Qredit\Services\BagistoChannelCredentialProvider::class);
    $this->app->bind(TenantResolver::class,     \Webkul\Qredit\Services\BagistoChannelTenantResolver::class);
}
```

Done. Every `Qredit::createOrder(...)` call now uses the current channel's credentials. The webhook controller (via the route macro) uses the `{tenant}` URL segment to pick the right secret for signature verification.

### Stancl Tenancy (`stancl/tenancy`)

```php
use Stancl\Tenancy\Tenancy;
use Qredit\LaravelQredit\Contracts\CredentialProvider;
use Qredit\LaravelQredit\Tenancy\QreditCredentials;

class StanclCredentialProvider implements CredentialProvider
{
    public function credentialsFor(?string $tenantId = null): QreditCredentials
    {
        $tenant = $tenantId
            ? app(Tenancy::class)->find($tenantId)
            : app(Tenancy::class)->tenant;

        return new QreditCredentials(
            apiKey:    $tenant->qredit_api_key,
            secretKey: decrypt($tenant->qredit_secret_key),
            sandbox:   $tenant->qredit_sandbox,
            tenantId:  (string) $tenant->id,
        );
    }

    public function isConfiguredFor(?string $tenantId = null): bool
    {
        $tenant = $tenantId
            ? app(Tenancy::class)->find($tenantId)
            : app(Tenancy::class)->tenant;

        return $tenant && filled($tenant?->qredit_api_key);
    }
}
```

Pair it with `SubdomainTenantResolver`:

```php
$this->app->bind(CredentialProvider::class, StanclCredentialProvider::class);
$this->app->bind(TenantResolver::class, fn () => new SubdomainTenantResolver(config('tenancy.central_domains')[0]));
```

### Spatie Multitenancy (`spatie/laravel-multitenancy`)

```php
use Spatie\Multitenancy\Models\Tenant;

class SpatieCredentialProvider implements CredentialProvider
{
    public function credentialsFor(?string $tenantId = null): QreditCredentials
    {
        $tenant = $tenantId ? Tenant::find($tenantId) : Tenant::current();

        return new QreditCredentials(
            apiKey:    $tenant->qredit_api_key,
            secretKey: $tenant->qredit_secret_key,
            sandbox:   $tenant->qredit_sandbox,
            tenantId:  (string) $tenant->id,
        );
    }

    public function isConfiguredFor(?string $tenantId = null): bool
    {
        $tenant = $tenantId ? Tenant::find($tenantId) : Tenant::current();

        return $tenant && filled($tenant?->qredit_api_key);
    }
}
```

### Bespoke — just want it bound to one of your models

```php
use Qredit\LaravelQredit\Tenancy\CallbackTenantResolver;

$this->app->bind(TenantResolver::class, fn () => new CallbackTenantResolver(
    currentCallback: fn ($req) => optional(auth()->user())->account_id,
    webhookCallback: fn ($req) => $req->route('tenant') ?? $req->header('X-Account-Id'),
));
```

## Rules that are non-negotiable

### Never read request state in a queue job

```php
// ❌ WRONG — will resolve to the "current" tenant, which in a job means
//    the server's default (usually the SAAS admin), not the tenant that
//    enqueued the job.
public function handle(): void
{
    Qredit::createOrder([...]);
}

// ✅ RIGHT — capture tenant id at dispatch time, pass explicitly.
public function __construct(public string $tenantId) {}

public function handle(): void
{
    Qredit::forTenant($this->tenantId)->createOrder([...]);
}
```

This is the single most common multi-tenancy bug in the wild. The SDK will help you — when a queue handler calls `Qredit::createOrder(...)` the bound resolver returns `null`, which falls through to `ConfigCredentialProvider`, which throws if you haven't set global `.env` credentials. So the failure is loud, but only if you test queue paths.

### Encrypt secret keys at rest

Store `secret_key` encrypted in your tenant table. Decrypt inside `CredentialProvider::credentialsFor()`. Never write it to logs — the SDK already redacts it in `qredit:call`'s credential summary.

### Rotate tokens per tenant

The SDK namespaces the token cache by `sha1($apiKey)`, so different tenants naturally get different cache entries. Don't fight it — never hardcode the cache key.

## Testing multi-tenant code

```php
use Qredit\LaravelQredit\Facades\Qredit;
use Qredit\LaravelQredit\Testing\FakeQredit;

it('charges the correct tenant in a queue job', function () {
    $fakeA = new FakeQredit(['createOrder' => ['status' => true, 'records' => [['orderReference' => 'A-1']]]]);
    $fakeB = new FakeQredit(['createOrder' => ['status' => true, 'records' => [['orderReference' => 'B-1']]]]);

    Qredit::fake(['tenant-a' => $fakeA, 'tenant-b' => $fakeB]);

    SettleCartJob::dispatchSync('tenant-b', 3200);

    $fakeA->assertNotCalled('createOrder');
    $fakeB->assertCalled('createOrder', times: 1);
});
```

## Debugging tips

- Enable `QREDIT_DEBUG=true` in .env — every request/response is logged including the resolved tenant id.
- `php artisan qredit:call create-order --api-key=... --secret-key=... --dry-run` signs a specific tenant's credentials without sending — use it to verify the provider returns what you expect.
- When webhook signature verification fails, check `tenantIdFromWebhook()` first — a wrong route parameter means the wrong secret is used to verify the signature.

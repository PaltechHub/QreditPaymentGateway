# LLM / AI Agent Implementation Guide

A structured reference for automated coding agents (Claude, Copilot, Cursor) working on host apps that consume this SDK.

## Axioms

1. **Signing is automatic.** Never compute HMAC-SHA512 manually in consumer code. `BaseQreditRequest::boot()` does it.
2. **Credentials come from `CredentialProvider`, nothing else.** Don't hardcode `config('qredit.api_key')` in business logic — bind a provider.
3. **In queue jobs, always pass `$tenantId` explicitly** — `Qredit::forTenant($this->tenantId)`. Never `Qredit::createOrder([...])` in a job.
4. **The facade resolves to `QreditManager`, not `Qredit`.** Magic `__call` delegation sends method calls to `current()`.
5. **Webhooks use the same secret as outbound signing.** No separate webhook secret.
6. **UAT at `apitest.qredit.tech`, prod at `api.qredit.tech` (not `.com`).** The `/gw-checkout/api/v1` path prefix is required.

---

## Directory map

```
src/
├── Commands/
│   ├── CallApiCommand.php        # `qredit:call` — signed-request CLI
│   ├── InstallCommand.php        # `qredit:install`
│   └── QreditTestCommand.php     # `qredit:test` — smoke auth
├── Connectors/
│   └── QreditConnector.php       # Saloon connector, per-tenant credentials
├── Contracts/
│   ├── CredentialProvider.php    # ← multi-tenant integration point #1
│   └── TenantResolver.php        # ← multi-tenant integration point #2
├── Controllers/
│   ├── SignController.php        # ready-made /qredit/sign
│   └── WebhookController.php     # ready-made /qredit/webhook
├── Events/
│   ├── OrderCancelled.php
│   ├── PaymentCompleted.php
│   ├── PaymentFailed.php
│   └── WebhookReceived.php
├── Exceptions/
│   ├── QreditApiException.php
│   ├── QreditAuthenticationException.php
│   └── QreditException.php
├── Facades/
│   └── Qredit.php                # resolves to QreditManager
├── Helpers/
│   └── MessageIdGenerator.php    # per-request msgId
├── Requests/
│   ├── Auth/GetTokenRequest.php
│   ├── BaseQreditRequest.php     # attaches the HMAC signature in boot()
│   ├── Customers/ListCustomersRequest.php
│   ├── Orders/*.php               # CRUD + list
│   ├── Payments/ChangeClearingStatusRequest.php
│   ├── PaymentRequests/*.php      # CRUD + list + generateQR + fees + init
│   └── Transactions/ListTransactionsRequest.php
├── Routing/
│   └── RouteMacros.php            # Route::qreditSign() / qreditWebhook()
├── Security/
│   ├── HmacSigner.php             # merchant guide §7
│   └── ValueFlattener.php
├── Services/
│   └── TokenManager.php           # cache / database / hybrid
├── Tenancy/
│   ├── CallbackTenantResolver.php
│   ├── ConfigCredentialProvider.php
│   ├── HeaderTenantResolver.php
│   ├── NullTenantResolver.php
│   ├── QreditCredentials.php      # value object
│   └── SubdomainTenantResolver.php
├── Testing/
│   └── FakeQredit.php             # test double
├── Traits/
│   └── HasMessageId.php
├── Qredit.php                     # raw client (per-tenant)
├── QreditManager.php              # facade target, owns client pool
└── QreditServiceProvider.php      # default bindings + macros
```

---

## Single-tenant recipe

```php
// .env
QREDIT_API_KEY=...
QREDIT_SECRET_KEY=...
QREDIT_SANDBOX=true
```

```php
// routes/web.php
Route::qreditSign();
Route::qreditWebhook();
```

```php
// anywhere
use Qredit\LaravelQredit\Facades\Qredit;

Qredit::createOrder([...]);
Qredit::createPayment([...]);
```

No other setup.

---

## Multi-tenant recipe

```php
// app/Qredit/MyCredentialProvider.php
namespace App\Qredit;

use Qredit\LaravelQredit\Contracts\CredentialProvider;
use Qredit\LaravelQredit\Tenancy\QreditCredentials;
use Qredit\LaravelQredit\Exceptions\QreditException;

class MyCredentialProvider implements CredentialProvider
{
    public function credentialsFor(?string $tenantId = null): QreditCredentials
    {
        $tenantId = $tenantId ?? app('current.tenant.id');
        $tenant   = \App\Models\Tenant::findOrFail($tenantId);

        if (! $tenant->qredit_api_key) {
            throw new QreditException("Qredit not configured for tenant [{$tenantId}]");
        }

        return new QreditCredentials(
            apiKey:    $tenant->qredit_api_key,
            secretKey: decrypt($tenant->qredit_secret_key),
            sandbox:   $tenant->qredit_sandbox,
            tenantId:  (string) $tenantId,
        );
    }

    public function isConfiguredFor(?string $tenantId = null): bool
    {
        return \App\Models\Tenant::find($tenantId ?? app('current.tenant.id'))?->qredit_api_key !== null;
    }
}
```

```php
// app/Providers/AppServiceProvider.php
use Qredit\LaravelQredit\Contracts\CredentialProvider;
use Qredit\LaravelQredit\Contracts\TenantResolver;
use Qredit\LaravelQredit\Tenancy\SubdomainTenantResolver;

public function register(): void
{
    $this->app->bind(CredentialProvider::class, \App\Qredit\MyCredentialProvider::class);
    $this->app->bind(TenantResolver::class, fn () => new SubdomainTenantResolver('example.com'));
}
```

```php
// HTTP — current tenant
Qredit::createOrder([...]);

// Queue / console — explicit tenant
class SettleCartJob implements ShouldQueue
{
    public function __construct(public string $tenantId) {}

    public function handle(): void
    {
        Qredit::forTenant($this->tenantId)->createOrder([...]);
    }
}
```

---

## Common tasks

### Task: add a new endpoint wrapper

1. Create a request class under `src/Requests/{Category}/{Name}Request.php`:

   ```php
   namespace Qredit\LaravelQredit\Requests\PaymentRequests;

   use Qredit\LaravelQredit\Requests\BaseQreditRequest;
   use Qredit\LaravelQredit\Traits\HasMessageId;
   use Saloon\Contracts\Body\HasBody;
   use Saloon\Enums\Method;
   use Saloon\Traits\Body\HasJsonBody;

   class NewFeatureRequest extends BaseQreditRequest implements HasBody
   {
       use HasJsonBody;
       use HasMessageId;

       protected Method $method = Method::POST;
       protected array $data;

       public function __construct(array $data)
       {
           $this->data = $data;
           $this->messageIdType = 'payment.newfeature';
       }

       public function resolveEndpoint(): string
       {
           return '/paymentRequests/newFeature';
       }

       protected function defaultBody(): array
       {
           return array_merge(['msgId' => $this->generateMessageId()], $this->data);
       }
   }
   ```

2. Add the facade method in `src/Qredit.php`:

   ```php
   public function newFeature(array $data): array
   {
       return $this->sendWithRetry(new NewFeatureRequest($data))->json();
   }
   ```

3. Add a row to the facade PHPDoc in `src/Facades/Qredit.php`:

   ```php
   * @method static array newFeature(array $data)
   ```

4. Add a mapping entry in `src/Commands/CallApiCommand.php::METHODS`.

5. Write a test in `tests/Feature/NewFeatureTest.php`.

6. Update this table in `docs/API_REFERENCE.md`.

Signing is inherited automatically — no crypto code in your request class.

### Task: swap the credential provider at runtime (testing)

```php
use Qredit\LaravelQredit\Contracts\CredentialProvider;

$this->app->bind(CredentialProvider::class, new class implements CredentialProvider {
    public function credentialsFor(?string $tenantId = null): QreditCredentials { /* ... */ }
    public function isConfiguredFor(?string $tenantId = null): bool { return true; }
});
```

Or simpler — use `Qredit::fake()`:

```php
Qredit::fake(new FakeQredit([...]));
```

### Task: verify a webhook manually (custom controller)

```php
use Qredit\LaravelQredit\Facades\Qredit;

$tenantId = $request->route('tenant');
$valid = Qredit::forTenant($tenantId)->verifyWebhookSignature(
    $request->all(),
    $request->header('Authorization'),
);
```

### Task: log everything

```env
QREDIT_DEBUG=true
QREDIT_LOG_CHANNEL=qredit
```

```php
// config/logging.php
'channels' => [
    'qredit' => [
        'driver' => 'daily',
        'path'   => storage_path('logs/qredit.log'),
        'level'  => 'debug',
    ],
],
```

---

## Forbidden patterns

### Never re-implement signing

```php
// ❌ don't — you'll drift from the spec and the base class
class MyCustomRequest extends Request {
    protected function defaultHeaders(): array {
        return ['Authorization' => 'HmacSHA512_O '.hash_hmac(...)];
    }
}

// ✅ extend BaseQreditRequest and let boot() do it
class MyCustomRequest extends BaseQreditRequest { /* ... */ }
```

### Never read request state from a queue job

```php
// ❌ Core() helpers return wrong context in a worker — see project CLAUDE.md
public function handle() {
    $code = core()->getRequestedChannelCode();  // null or wrong
    Qredit::createOrder([...]);
}

// ✅ Pass explicit tenant
public function __construct(public string $channelCode) {}

public function handle() {
    Qredit::forTenant($this->channelCode)->createOrder([...]);
}
```

### Never cache credentials beyond the request

```php
// ❌ A stale secret survives a rotation
class BadProvider implements CredentialProvider {
    private static ?QreditCredentials $cached = null;
    public function credentialsFor(?string $tenantId = null): QreditCredentials {
        return self::$cached ??= new QreditCredentials(/*...*/);
    }
}

// ✅ Fetch every call; SDK caches the Qredit *client* per-request
```

### Never log the secret key

```php
// ❌
Log::info('Resolved creds', ['secret' => $creds->secretKey]);

// ✅ Redact
Log::info('Resolved creds', [
    'api_key' => substr($creds->apiKey, 0, 8).'...',
    'tenant'  => $creds->tenantId,
]);
```

---

## Checklist before shipping a consumer

- [ ] `QREDIT_API_KEY` and `QREDIT_SECRET_KEY` in `.env` (or bound `CredentialProvider`)
- [ ] `Route::qreditSign()` + `Route::qreditWebhook()` in `routes/web.php`
- [ ] `PaymentWidget.init({ url: route('qredit.sign') })` in checkout view
- [ ] Webhook URL registered with Qredit (or passed in `createPayment`'s `callbackUrl`)
- [ ] Event listeners bound in `EventServiceProvider::$listen`
- [ ] For multi-tenant: all queue jobs accept `$tenantId` in constructor
- [ ] Ran `php artisan qredit:call auth --sandbox` — got a token back
- [ ] `QREDIT_DEBUG=false` in production

---

## Reference artifacts

- [`src/Security/HmacSigner.php`](../src/Security/HmacSigner.php) — the 30-line signer, auditable top-to-bottom
- [`src/QreditManager.php`](../src/QreditManager.php) — facade target
- [`tests/Unit/HmacSignerTest.php`](../tests/Unit/HmacSignerTest.php) — golden-vector tests
- [`examples/BasicUsage.php`](../examples/BasicUsage.php) — copy-paste recipes
- [`examples/MultiTenantUsage.php`](../examples/MultiTenantUsage.php) — full SAAS wiring
- [`examples/WebhookHandler.php`](../examples/WebhookHandler.php) — inbound callback

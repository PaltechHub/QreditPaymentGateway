# Qredit Laravel SDK

[![Latest Version on Packagist](https://img.shields.io/packagist/v/qredit/laravel-qredit.svg?style=flat-square)](https://packagist.org/packages/qredit/laravel-qredit)
[![Tests](https://img.shields.io/github/actions/workflow/status/qredit/laravel-qredit/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/qredit/laravel-qredit/actions/workflows/tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/qredit/laravel-qredit.svg?style=flat-square)](https://packagist.org/packages/qredit/laravel-qredit)
[![License](https://img.shields.io/packagist/l/qredit/laravel-qredit.svg?style=flat-square)](LICENSE.md)

Production-ready Laravel SDK for the **Qredit / BlockBuilders payment gateway**. Built with multi-tenant SAAS deployments as a first-class concern.

- ✅ Every Qredit API endpoint wrapped (auth, orders, payment requests, QR, fees, init, customers, transactions, clearing)
- ✅ Automatic HMAC-SHA512 signing — you never compute a signature yourself
- ✅ Per-tenant credentials — swap API keys per-request via a pluggable `CredentialProvider`
- ✅ Ready-made `/sign` and `/webhook` endpoints (one-line route macros)
- ✅ Per-tenant token cache (95% fewer auth calls), transparent refresh on 401
- ✅ `FakeQredit` test double + `qredit:call` CLI (the Postman replacement)
- ✅ Built on [Saloon](https://docs.saloon.dev/) v3 — full middleware / mock-client support

> **Status:** verified live against Qredit UAT — `auth/token` returns a valid JWT end-to-end through the SDK. See [CHANGELOG.md](CHANGELOG.md) for the latest release notes.

---

## Table of contents

- [Installation](#installation)
- [Quick start — single tenant](#quick-start--single-tenant)
- [Multi-tenant usage](#multi-tenant-usage)
- [API surface](#api-surface)
- [Webhook handling](#webhook-handling)
- [The checkout widget's `/sign` endpoint](#the-checkout-widgets-sign-endpoint)
- [Testing with `FakeQredit`](#testing-with-fakeqredit)
- [CLI — `qredit:call`](#cli--qreditcall)
- [Configuration reference](#configuration-reference)
- [Troubleshooting](#troubleshooting)
- [Documentation](#documentation)

---

## Installation

```bash
composer require qredit/laravel-qredit
php artisan qredit:install
```

The installer publishes `config/qredit.php` and prints the next-step checklist for your topology (single-tenant by default; pass `--tenancy` for multi-tenant instructions).

**Requirements**
- PHP 8.1 / 8.2 / 8.3 / 8.4
- Laravel 10 / 11 / 12 / 13
- Saloon v3

---

## Quick start — single tenant

Add your credentials to `.env`:

```env
QREDIT_API_KEY=EdVfej9D...
QREDIT_SECRET_KEY=B9E0236B...
QREDIT_SANDBOX=true
QREDIT_SANDBOX_URL=https://apitest.qredit.tech/gw-checkout/api/v1
QREDIT_PRODUCTION_URL=https://api.qredit.tech/gw-checkout/api/v1
QREDIT_SIGNATURE_CASE=upper     # live UAT accepts only uppercase
```

Register the ready-made endpoints in `routes/web.php`:

```php
use Illuminate\Support\Facades\Route;

Route::qreditSign();        // POST /qredit/sign
Route::qreditWebhook();     // POST /qredit/webhook
```

Use the facade anywhere:

```php
use Qredit\LaravelQredit\Facades\Qredit;

$order = Qredit::createOrder([
    'amountCents'         => 3200,
    'currencyCode'        => 'ILS',
    'deliveryNeeded'      => 'true',
    'deliveryCostCents'   => 200,
    'shippingProviderCode'=> 'DELV2',
    'clientReference'     => 'ORDER-2026-001',
    'customerInfo'        => [
        'name'  => 'Jane Doe',
        'phone' => '+970599785833',
        'email' => 'jane@example.com',
    ],
    'shippingData' => [
        'countryCode' => 'PSE',
        'cityCode'    => '50',
        'areaCode'    => '50',
        'street'      => "Jemma'in",
        'postalCode'  => '970',
    ],
    'items' => [
        ['name' => 'Widget', 'amountCents' => 2000, 'quantity' => 1, 'sku' => 'W-001'],
        ['name' => 'Gadget', 'amountCents' => 1200, 'quantity' => 1, 'sku' => 'G-002'],
    ],
]);

$orderReference = $order['records'][0]['orderReference'];

$payment = Qredit::createPayment([
    'orderReference'    => $orderReference,
    'amountCents'       => 3200,
    'currencyCode'      => 'ILS',
    'lockOrderWhenPaid' => true,
    'paymentChannels'   => [['code' => 'CSAB'], ['code' => 'NC-QR']],
    'customerInfo'      => [/* ... */],
    'billingData'       => [/* ... */],
]);

$checkoutUrl = $payment['records'][0]['url'];
return redirect($checkoutUrl);
```

Every outgoing request is automatically signed with the correct `Authorization: HmacSHA512_O <hex>` header per merchant guide §7. You don't compute anything by hand.

---

## Multi-tenant usage

The SDK ships with two contracts you bind to your app's tenancy layer:

| Contract | Responsibility |
|---|---|
| [`CredentialProvider`](src/Contracts/CredentialProvider.php) | Given a tenant id, return that tenant's Qredit credentials. |
| [`TenantResolver`](src/Contracts/TenantResolver.php) | Given an HTTP request, return the current tenant id. |

### 1. Implement `CredentialProvider`

```php
namespace App\Qredit;

use Qredit\LaravelQredit\Contracts\CredentialProvider;
use Qredit\LaravelQredit\Tenancy\QreditCredentials;
use App\Models\Tenant;

class DbCredentialProvider implements CredentialProvider
{
    public function credentialsFor(?string $tenantId = null): QreditCredentials
    {
        $tenantId = $tenantId ?? app('current.tenant.id');
        $tenant   = Tenant::findOrFail($tenantId);

        return new QreditCredentials(
            apiKey:    $tenant->qredit_api_key,
            secretKey: decrypt($tenant->qredit_secret_key),
            sandbox:   $tenant->qredit_sandbox,
            language:  $tenant->language_code,
            tenantId:  (string) $tenantId,
        );
    }

    public function isConfiguredFor(?string $tenantId = null): bool
    {
        $tenant = Tenant::find($tenantId ?? app('current.tenant.id'));

        return $tenant && filled($tenant->qredit_api_key) && filled($tenant->qredit_secret_key);
    }
}
```

### 2. Pick (or write) a `TenantResolver`

Built-in resolvers cover the common cases:

```php
use Qredit\LaravelQredit\Tenancy\SubdomainTenantResolver;  // "shop-b.example.com" → "shop-b"
use Qredit\LaravelQredit\Tenancy\HeaderTenantResolver;      // X-Tenant-Id header
use Qredit\LaravelQredit\Tenancy\CallbackTenantResolver;    // closure escape hatch
```

### 3. Bind both in a service provider

```php
use Qredit\LaravelQredit\Contracts\CredentialProvider;
use Qredit\LaravelQredit\Contracts\TenantResolver;
use Qredit\LaravelQredit\Tenancy\SubdomainTenantResolver;

public function register(): void
{
    $this->app->bind(CredentialProvider::class, \App\Qredit\DbCredentialProvider::class);
    $this->app->bind(TenantResolver::class, fn () => new SubdomainTenantResolver('example.com'));
}
```

### 4. Use the facade — tenancy is transparent

```php
// HTTP request — uses the bound TenantResolver automatically.
Qredit::createOrder([...]);

// Queue job — ALWAYS pass the tenant id explicitly.
class SettleCartJob implements ShouldQueue
{
    public function __construct(public string $tenantId, public string $orderReference) {}

    public function handle(): void
    {
        Qredit::forTenant($this->tenantId)->calculateFees([...]);
    }
}
```

See [`docs/MULTITENANCY.md`](docs/MULTITENANCY.md) for deep-dive examples including Bagisto, Stancl Tenancy, and Spatie Multitenancy.

---

## API surface

| Group | Method | Qredit endpoint |
|---|---|---|
| Auth | `authenticate()` | `POST /auth/token` |
| Orders | `createOrder($data)` | `POST /orders` |
| Orders | `getOrder($ref)` | `GET /orders?orderReference=…` |
| Orders | `updateOrder($ref, $data)` | `PUT /orders` |
| Orders | `cancelOrder($ref, $reason)` | `DELETE /orders` |
| Orders | `listOrders($query)` | `GET /orders` |
| Payment req. | `createPayment($data)` | `POST /paymentRequests` |
| Payment req. | `getPayment($ref)` | `GET /paymentRequests?reference=…` |
| Payment req. | `updatePayment($ref, $data)` | `PUT /paymentRequests` |
| Payment req. | `deletePayment($ref, $reason)` | `DELETE /paymentRequests` |
| Payment req. | `listPayments($query)` | `GET /paymentRequests` |
| Payment req. | `generateQR($query)` | `GET /paymentRequests/generateQR` |
| Payment req. | `calculateFees($data)` | `POST /paymentRequests/calculateFees` |
| Payment req. | `initPayment($data)` | `POST /paymentRequests/initPayment` |
| Customers | `listCustomers($filters)` | `GET /customers` |
| Transactions | `listTransactions($filters)` | `GET /payments` |
| Transactions | `changeClearingStatus($data)` | `POST /payments/changeClearingStatus` |
| Webhook | `verifyWebhookSignature($p, $a)` | — |
| Webhook | `processWebhook($p, $a)` | — |

Full request/response shapes are in [`docs/API_REFERENCE.md`](docs/API_REFERENCE.md).

---

## Webhook handling

The SDK ships a ready-made webhook controller. Register it with the route macro:

```php
Route::qreditWebhook('/qredit/webhook');                  // single-tenant
Route::qreditWebhook('/qredit/webhook/{tenant}');         // multi-tenant (tenant id in URL)
```

Listen for typed events in your `EventServiceProvider`:

```php
use Qredit\LaravelQredit\Events\PaymentCompleted;
use Qredit\LaravelQredit\Events\PaymentFailed;
use Qredit\LaravelQredit\Events\OrderCancelled;
use Qredit\LaravelQredit\Events\WebhookReceived;

protected $listen = [
    PaymentCompleted::class => [\App\Listeners\FulfillOrder::class],
    PaymentFailed::class    => [\App\Listeners\NotifyCustomerOfFailure::class],
    OrderCancelled::class   => [\App\Listeners\ReleaseStock::class],
];
```

Each event's `$data` payload carries `_tenant_id` so listeners can scope their work correctly in background jobs.

Signature verification uses the per-tenant `secret_key` — no separate webhook secret needed, matching merchant guide §6/§7.

See [`docs/WEBHOOKS.md`](docs/WEBHOOKS.md) for payload shapes and full event documentation.

---

## The checkout widget's `/sign` endpoint

BlockBuilders' hosted checkout widget runs in the customer's browser and needs signed gateway calls — but the secret key must **never** be shipped to the browser. The widget solves this by POSTing `{ body: "..." }` to a merchant-owned `/sign` endpoint and receiving `{ signature: "..." }` back.

The SDK ships that endpoint as `SignController`. Wire it with one line:

```php
Route::qreditSign();   // POST /qredit/sign
```

Pass that URL to `PaymentWidget.init`:

```html
<script>
  PaymentWidget.init({
    containerId: 'payment-widget',
    reference:   '{{ $paymentReference }}',
    token:       '{{ $accessToken }}',
    url:         '{{ route("qredit.sign") }}',
    lang:        app()->getLocale(),
  });
</script>
```

The controller pulls the current tenant's secret via your `CredentialProvider`, signs the payload with `HmacSigner`, and returns the hex. The secret never leaves your server.

---

## Testing with `FakeQredit`

```php
use Qredit\LaravelQredit\Facades\Qredit;
use Qredit\LaravelQredit\Testing\FakeQredit;

it('creates an order when checkout starts', function () {
    $fake = new FakeQredit([
        'createOrder' => [
            'status'  => true,
            'code'    => '00',
            'records' => [['orderReference' => 'ORDER-1']],
        ],
    ]);

    Qredit::fake($fake);

    $this->post('/checkout/place-order', [/* ... */])->assertOk();

    $fake->assertCalled('createOrder');
    $fake->assertCalledWith('createOrder', fn ($args) => $args[0]['amountCents'] === 3200);
});
```

Full testing guide: [`docs/TESTING.md`](docs/TESTING.md).

---

## CLI — `qredit:call`

Because every request needs an HMAC signature, Postman / Insomnia aren't practical. The SDK ships a signed-request CLI:

```bash
# Every supported endpoint
php artisan qredit:call --list

# Live auth call
php artisan qredit:call auth \
  --api-key=... --secret-key=... --sandbox

# Create order from an inline payload
php artisan qredit:call create-order \
  --payload='{"amountCents":3200,"currencyCode":"ILS",...}'

# From a JSON file
php artisan qredit:call create-payment \
  --payload-file=./tests/fixtures/payment.json

# Dry-run — prints signature + payload without sending
php artisan qredit:call create-order --dry-run \
  --secret-key=... --payload='{...}'

# Flip signature hex case (gateway is strict)
php artisan qredit:call auth --case=upper ...
```

---

## Configuration reference

Full [`config/qredit.php`](config/qredit.php) options:

| Key | Env | Default | Purpose |
|---|---|---|---|
| `api_key` | `QREDIT_API_KEY` | `''` | Public API key (single-tenant default) |
| `secret_key` | `QREDIT_SECRET_KEY` | `''` | HMAC secret (single-tenant default) |
| `sandbox` | `QREDIT_SANDBOX` | `true` | UAT vs production |
| `sandbox_url` | `QREDIT_SANDBOX_URL` | `https://apitest.qredit.tech/gw-checkout/api/v1` | |
| `production_url` | `QREDIT_PRODUCTION_URL` | `https://api.qredit.tech/gw-checkout/api/v1` | |
| `language` | `QREDIT_LANGUAGE` | `EN` | `Accept-Language` header |
| `client.type` | — (hardcoded) | `TP` | `Client-Type` header — fixed; don't override |
| `client.version` | `QREDIT_CLIENT_VERSION` | `ccc<semver>` | `Client-Version` header — derived from SDK version at runtime; set only to pin |
| `signing.scheme` | `QREDIT_AUTH_SCHEME` | `HmacSHA512_O` | Authorization prefix |
| `signing.case` | `QREDIT_SIGNATURE_CASE` | `upper` | Hex case — live UAT accepts only `upper` |
| `token_storage.strategy` | `QREDIT_TOKEN_STRATEGY` | `cache` | `cache`, `database`, or `hybrid` |
| `debug` | `QREDIT_DEBUG` | `false` | Log every request + response |

---

## Troubleshooting

### `code 1004 "Bad Signature"`

Signature compared against a stored secret but mismatched. Most common causes:
1. **Credentials not provisioned** — apiKey not in gateway's user database. Verify via a second host: if one returns `1004` and another returns `1705 "User Not Found"`, the account isn't set up.
2. **Signature case** — live UAT rejects lowercase; leave `QREDIT_SIGNATURE_CASE=upper`.

### `code 1005 "Bad Signature"`

The Authorization header is missing or the scheme prefix is wrong. Send `HmacSHA512_O <hex>` (not `HMAC-SHA512` or `Bearer`).

### `code 1012 "Bad Signature"`

Usually a `Client-Type` / `Client-Version` header mismatch — the SDK hardcodes `TP` and derives the version dynamically, so this only fires if something upstream (proxy, CDN, WAF) rewrites those headers.

### `code 1904 "Operation not allowed"`

**Signature validated** but the apiKey lacks permission for the endpoint. Ask Qredit to grant the relevant role (e.g. `ROLE_ORDER_MANAGEMENT`).

### `QreditException: Qredit credentials missing`

The default `ConfigCredentialProvider` couldn't find `QREDIT_API_KEY` / `QREDIT_SECRET_KEY` in config. Either set them in `.env`, or bind a custom `CredentialProvider` for multi-tenant use.

See [`docs/TROUBLESHOOTING.md`](docs/TROUBLESHOOTING.md) for the full diagnostic playbook.

---

## Documentation

| Doc | Topic |
|---|---|
| [`docs/API_REFERENCE.md`](docs/API_REFERENCE.md) | Every wrapped endpoint — full request / response shapes |
| [`docs/MULTITENANCY.md`](docs/MULTITENANCY.md) | Deep-dive with Bagisto / Stancl / Spatie examples |
| [`docs/SIGNING.md`](docs/SIGNING.md) | HMAC SHA512 algorithm — step-by-step with the §7 worked example |
| [`docs/WEBHOOKS.md`](docs/WEBHOOKS.md) | Event payloads + listener patterns |
| [`docs/TESTING.md`](docs/TESTING.md) | `FakeQredit`, Saloon mock clients, feature tests |
| [`docs/TROUBLESHOOTING.md`](docs/TROUBLESHOOTING.md) | Every error code, every diagnostic step |
| [`docs/LLM_IMPLEMENTATION_GUIDE.md`](docs/LLM_IMPLEMENTATION_GUIDE.md) | Structured reference for AI agents |
| [`docs/QREDIT_SIGNATURE_ISSUE.md`](docs/QREDIT_SIGNATURE_ISSUE.md) | Current known issue with UAT credentials |
| [`examples/BasicUsage.php`](examples/BasicUsage.php) | Copy-paste recipes for every endpoint |
| [`examples/MultiTenantUsage.php`](examples/MultiTenantUsage.php) | Full multi-tenant integration |
| [`examples/WebhookHandler.php`](examples/WebhookHandler.php) | Signed-callback handler |

---

## Contributing

Contributions are welcome — bug fixes, new endpoint wrappers, tenant resolvers, docs, the lot. See [CONTRIBUTING.md](CONTRIBUTING.md) for the full workflow (fork → branch → test → pint → PR). For security issues, please don't open a public issue; follow [SECURITY.md](SECURITY.md) instead.

Good first issues are labeled [`good first issue`](https://github.com/qredit/laravel-qredit/labels/good%20first%20issue) on the tracker. Questions? Open a [GitHub Discussion](https://github.com/qredit/laravel-qredit/discussions).

## Community

- **Bug reports / feature requests:** [GitHub Issues](https://github.com/qredit/laravel-qredit/issues)
- **Open-ended questions, show-and-tell:** [GitHub Discussions](https://github.com/qredit/laravel-qredit/discussions)
- **Security vulnerabilities:** email `shakerawad@paltechhub.com` — see [SECURITY.md](SECURITY.md)
- **Changelog:** [CHANGELOG.md](CHANGELOG.md)
- **Code of Conduct:** [CODE_OF_CONDUCT.md](.github/CODE_OF_CONDUCT.md)

## Credits

- Built on [Saloon](https://docs.saloon.dev/) by Sam Carré
- HMAC signing algorithm confirmed against Qredit UAT (2026-04-16)
- All [contributors](https://github.com/qredit/laravel-qredit/graphs/contributors)

## License

MIT — see [LICENSE.md](LICENSE.md).

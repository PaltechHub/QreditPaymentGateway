<?php

/**
 * Multi-tenant usage example.
 *
 * Demonstrates how to integrate the Qredit SDK into a SAAS app where each
 * tenant carries its own Qredit credentials. The SDK's two contracts —
 * CredentialProvider and TenantResolver — are the entire integration surface.
 */

use Qredit\LaravelQredit\Contracts\CredentialProvider;
use Qredit\LaravelQredit\Contracts\TenantResolver;
use Qredit\LaravelQredit\Exceptions\QreditException;
use Qredit\LaravelQredit\Facades\Qredit;
use Qredit\LaravelQredit\Tenancy\QreditCredentials;
use Qredit\LaravelQredit\Tenancy\SubdomainTenantResolver;

// ===========================================================================
// 1. IMPLEMENT CredentialProvider
// ===========================================================================

/**
 * Reads Qredit credentials from your tenants table. Decrypts the secret on
 * every call — never cache it in a static, rotations would be invisible.
 */
class DbCredentialProvider implements CredentialProvider
{
    public function credentialsFor(?string $tenantId = null): QreditCredentials
    {
        // In HTTP context, fall back to the bound TenantResolver.
        // In queue jobs, the caller MUST pass $tenantId explicitly.
        $tenantId = $tenantId ?? app(TenantResolver::class)->currentTenantId();

        if (! $tenantId) {
            throw new QreditException('No tenant context to resolve Qredit credentials.');
        }

        $tenant = \App\Models\Tenant::find($tenantId);

        if (! $tenant || ! $tenant->qredit_api_key) {
            throw new QreditException("Qredit not configured for tenant [{$tenantId}].");
        }

        return new QreditCredentials(
            apiKey: $tenant->qredit_api_key,
            secretKey: decrypt($tenant->qredit_secret_key),
            sandbox: (bool) $tenant->qredit_sandbox,
            language: $tenant->language_code ?? 'EN',
            signatureCase: $tenant->qredit_signature_case ?? 'lower',
            tenantId: (string) $tenantId,
        );
    }

    public function isConfiguredFor(?string $tenantId = null): bool
    {
        $tenantId = $tenantId ?? app(TenantResolver::class)->currentTenantId();

        if (! $tenantId) {
            return false;
        }

        $tenant = \App\Models\Tenant::find($tenantId);

        return $tenant && filled($tenant->qredit_api_key) && filled($tenant->qredit_secret_key);
    }
}

// ===========================================================================
// 2. BIND IN AN APPLICATION SERVICE PROVIDER
// ===========================================================================

/**
 * In app/Providers/AppServiceProvider.php (or a dedicated QreditServiceProvider).
 */
class MyAppServiceProvider extends \Illuminate\Support\ServiceProvider
{
    public function register(): void
    {
        // Tell the SDK how to find credentials.
        $this->app->bind(CredentialProvider::class, DbCredentialProvider::class);

        // Tell the SDK how to find the current tenant from a request.
        $this->app->bind(TenantResolver::class, function () {
            return new SubdomainTenantResolver(config('app.root_domain'));
        });
    }
}

// ===========================================================================
// 3. USE THE FACADE IN CONTROLLERS
// ===========================================================================

/**
 * HTTP context — the facade automatically resolves the current tenant via
 * the bound TenantResolver. No explicit tenant id needed.
 */
class CheckoutController
{
    public function startPayment()
    {
        // Implicit "current tenant" lookup — reads the subdomain via the
        // bound SubdomainTenantResolver → returns "shop-a" on shop-a.example.com.
        $order = Qredit::createOrder([
            'amountCents' => 3200,
            'currencyCode' => 'ILS',
            // ...
        ]);

        $payment = Qredit::createPayment([
            'orderReference' => $order['records'][0]['orderReference'],
            'amountCents' => 3200,
            'currencyCode' => 'ILS',
            // ...
        ]);

        return redirect($payment['records'][0]['url']);
    }
}

// ===========================================================================
// 4. QUEUE JOBS — ALWAYS PASS TENANT EXPLICITLY
// ===========================================================================

/**
 * CRITICAL: in queue jobs, NEVER rely on the bound TenantResolver. The
 * request context doesn't exist when the worker picks up the job.
 */
class SettleCartJob implements \Illuminate\Contracts\Queue\ShouldQueue
{
    public function __construct(
        public string $tenantId,      // capture at dispatch time
        public int $amountCents,
        public string $clientReference,
    ) {}

    public function handle(): void
    {
        // Explicit tenant → correct credentials every time.
        $order = Qredit::forTenant($this->tenantId)->createOrder([
            'amountCents' => $this->amountCents,
            'currencyCode' => 'ILS',
            'clientReference' => $this->clientReference,
        ]);

        // ... fulfillment ...
    }
}

// Dispatcher side:
SettleCartJob::dispatch(
    tenantId: $tenant->id,
    amountCents: 3200,
    clientReference: 'ORDER-2026-001',
);

// ===========================================================================
// 5. ROUTES — ONE LINE EACH
// ===========================================================================

/**
 * In routes/web.php:
 *
 *   Route::qreditSign();                                  // POST /qredit/sign
 *   Route::qreditWebhook('/qredit/webhook/{tenant}');     // per-tenant webhook
 *
 * The {tenant} path param feeds TenantResolver::tenantIdFromWebhook() so the
 * webhook controller picks the right secret for signature verification.
 */

// ===========================================================================
// 6. WEBHOOK LISTENERS — DEFERRED WORK GOES TO QUEUES
// ===========================================================================

use Qredit\LaravelQredit\Events\PaymentCompleted;

class FulfillOrderOnPayment
{
    public function handle(PaymentCompleted $event): void
    {
        // The event carries the tenant id set by the webhook controller.
        $tenantId = $event->data['_tenant_id'];
        $reference = $event->data['reference'];

        // Dispatch with explicit tenant — never rely on context in the job.
        FulfillOrderJob::dispatch($tenantId, $reference);
    }
}

// ===========================================================================
// 7. TESTING WITH PER-TENANT FAKES
// ===========================================================================

use Qredit\LaravelQredit\Testing\FakeQredit;

function testMultiTenantCheckout()
{
    $fakeA = new FakeQredit([
        'createOrder' => ['status' => true, 'records' => [['orderReference' => 'A-1']]],
    ]);
    $fakeB = new FakeQredit([
        'createOrder' => ['status' => true, 'records' => [['orderReference' => 'B-1']]],
    ]);

    Qredit::fake([
        'tenant-a' => $fakeA,
        'tenant-b' => $fakeB,
    ]);

    // simulate two tenants placing orders
    Qredit::forTenant('tenant-a')->createOrder(['amountCents' => 1000]);
    Qredit::forTenant('tenant-b')->createOrder(['amountCents' => 2000]);

    $fakeA->assertCalled('createOrder', times: 1);
    $fakeB->assertCalled('createOrder', times: 1);
    $fakeA->assertCalledWith('createOrder', fn ($args) => $args[0]['amountCents'] === 1000);
}

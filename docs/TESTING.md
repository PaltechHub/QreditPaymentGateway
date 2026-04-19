# Testing

## `FakeQredit` — the zero-setup test double

```php
use Qredit\LaravelQredit\Facades\Qredit;
use Qredit\LaravelQredit\Testing\FakeQredit;

it('charges the customer on checkout', function () {
    $fake = new FakeQredit([
        'createOrder' => [
            'status'  => true,
            'code'    => '00',
            'records' => [['orderReference' => 'ORDER-1']],
        ],
        'createPayment' => [
            'status'  => true,
            'records' => [['reference' => 'PAY-1', 'url' => 'https://fake/pay/1']],
        ],
    ]);

    Qredit::fake($fake);

    $this->postJson('/checkout/place-order', [/* ... */])
         ->assertRedirect('https://fake/pay/1');

    $fake->assertCalled('createOrder', times: 1);
    $fake->assertCalledWith('createPayment', fn ($args) => $args[0]['amountCents'] === 3200);
    $fake->assertNotCalled('listCustomers');
});
```

### Dynamic responses

Pass a closure for methods that need to respond based on input:

```php
$fake = new FakeQredit([
    'getPayment' => fn ($ref) => [
        'status'  => true,
        'records' => [[
            'reference' => $ref,
            'paymentRequestStatus' => str_starts_with($ref, 'PAID-') ? 'PAID' : 'PENDING_PAYMENT',
        ]],
    ],
]);
```

### Multiple tenants

```php
Qredit::fake([
    'tenant-a' => new FakeQredit(['createOrder' => [/* A's response */]]),
    'tenant-b' => new FakeQredit(['createOrder' => [/* B's response */]]),
]);

Qredit::forTenant('tenant-a')->createOrder([...]);
Qredit::forTenant('tenant-b')->createOrder([...]);
```

### Assertion reference

| Assertion | Purpose |
|---|---|
| `assertCalled('method')` | Verify a method ran at least once |
| `assertCalled('method', times: N)` | Verify exact call count |
| `assertNotCalled('method')` | Verify a method never ran |
| `assertCalledWith('method', $predicate)` | Verify at least one call passed args matching the closure |

## Integration testing with Saloon's `MockClient`

When you want to test the request/response path *including* HMAC signing and HTTP layer behavior without hitting the gateway:

```php
use Qredit\LaravelQredit\Qredit;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

it('parses a successful auth response', function () {
    $mock = new MockClient([
        MockResponse::make([
            'status'       => true,
            'access_token' => 'fake-token',
            'expires_in'   => 3600,
        ], 200),
    ]);

    $qredit = Qredit::make([
        'api_key'    => 'k',
        'secret_key' => 's',
        'skip_auth'  => true,
    ]);

    $qredit->getConnector()->withMockClient($mock);

    expect($qredit->authenticate())->toBe('fake-token');
});
```

Use `MockClient` when you need to assert on specific request headers (including the computed `Authorization` signature) — `FakeQredit` bypasses HTTP entirely and won't exercise the signing pipeline.

## Testing webhook listeners

```php
use Qredit\LaravelQredit\Events\PaymentCompleted;
use Illuminate\Support\Facades\Event;

it('fulfills an order when payment completes', function () {
    Event::fake();

    // Build a valid signed webhook payload
    $payload = [
        'status'  => true,
        'code'    => '00',
        'records' => [['reference' => 'PAY-1', 'msgId' => 'm-1', 'amount' => 32.0]],
    ];
    $signature = // compute with HmacSigner::sign(...) using your test tenant's secret

    $this->postJson('/qredit/webhook/tenant-a', $payload, [
        'Authorization' => 'HmacSHA512_O '.$signature,
    ])->assertOk();

    Event::assertDispatched(PaymentCompleted::class, fn ($e) => $e->data['reference'] === 'PAY-1');
});
```

## Pest testing tips

The SDK uses [Pest](https://pestphp.com/) internally. Run tests with:

```bash
vendor/bin/pest                       # everything
vendor/bin/pest tests/Unit            # unit suite only
vendor/bin/pest --filter=HmacSigner   # by name
vendor/bin/pest --parallel            # multi-core
```

## Test environment setup

The package's test config sets sensible defaults in `tests/TestCase.php`:

```php
protected function getEnvironmentSetUp($app): void
{
    $app['config']->set('qredit.api_key',    'test-api-key');
    $app['config']->set('qredit.secret_key', 'test-secret-key');
    $app['config']->set('qredit.sandbox',    true);
}
```

When writing feature tests for your host app, set these same values in your `phpunit.xml` or `CreatesApplication.php`.

## Common gotchas

### Forgetting to `skip_auth` in tests

`Qredit::make()` without `skip_auth: true` will immediately hit the gateway's auth endpoint. Your test will fail with a network error (or hit the real gateway, which is worse).

```php
$qredit = Qredit::make([
    'api_key'    => 'k',
    'secret_key' => 's',
    'skip_auth'  => true,  // ← always set in tests
]);
```

### Mockery facade conflicts with `Qredit::fake()`

If you use `Qredit::swap(...)` or Mockery to replace the facade, `FakeQredit` setup will conflict. Pick one approach. `Qredit::fake()` is preferred — it's idempotent and doesn't require Mockery.

### Queue job tests — always pass tenant explicitly

```php
// ❌ — will use the bound TenantResolver, which returns null in a CLI/job context
SettleCartJob::dispatch()

// ✅
SettleCartJob::dispatch(tenantId: 'tenant-a')
```

Otherwise your test's `FakeQredit` instance for `__default__` runs instead of the tenant-specific fake.

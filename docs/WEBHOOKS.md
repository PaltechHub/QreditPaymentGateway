# Webhooks

Qredit sends signed HTTP callbacks to your merchant server when a payment succeeds, fails, or a transaction's clearing status changes. The SDK ships a ready-made receiver.

## Wire it up

```php
// routes/web.php — single tenant
Route::qreditWebhook('/qredit/webhook');

// routes/web.php — multi-tenant
Route::qreditWebhook('/qredit/webhook/{tenant}');
```

The macro registers `WebhookController::handle` on the given path, disables CSRF for that route, and names it `qredit.webhook`.

Then configure the callback URL in your Qredit merchant dashboard or pass it into `createPayment`:

```php
Qredit::createPayment([
    // ... other fields ...
    'callbackUrl' => route('qredit.webhook'),
]);
```

## What happens when a webhook arrives

```
POST /qredit/webhook/tenant-b
Authorization: HmacSHA512_O <hex>
Content-Type: application/json

{"status": true, "code": "00", ...}
```

1. `TenantResolver::tenantIdFromWebhook($request)` extracts `"tenant-b"` (usually from the `{tenant}` route parameter).
2. `QreditManager::forTenant("tenant-b")` returns that tenant's client, carrying their `secretKey`.
3. The client calls `verifyWebhookSignature($payload, $authorizationHeader)` — HMAC SHA512 over the sorted scalar values, using `md5(secretKey + msgId)` as the key. Matches the outbound signing algorithm exactly (merchant guide §6).
4. On success, a `WebhookReceived` event fires, plus a typed event based on the payload type.
5. Controller returns `200 { "status": "RECEIVED" }`. Qredit will retry on any other response.

## Typed events

Listen in your `App\Providers\EventServiceProvider`:

```php
use Qredit\LaravelQredit\Events\WebhookReceived;
use Qredit\LaravelQredit\Events\PaymentCompleted;
use Qredit\LaravelQredit\Events\PaymentFailed;
use Qredit\LaravelQredit\Events\OrderCancelled;

protected $listen = [
    WebhookReceived::class  => [\App\Listeners\LogWebhook::class],
    PaymentCompleted::class => [\App\Listeners\FulfillOrder::class],
    PaymentFailed::class    => [\App\Listeners\NotifyCustomerOfFailure::class],
    OrderCancelled::class   => [\App\Listeners\ReleaseStock::class],
];
```

### Event payloads

All typed events carry a `$data` array that always includes `_tenant_id` so listeners can scope their work.

```php
namespace App\Listeners;

use Qredit\LaravelQredit\Events\PaymentCompleted;
use Qredit\LaravelQredit\Facades\Qredit;

class FulfillOrder
{
    public function handle(PaymentCompleted $event): void
    {
        $tenantId = $event->data['_tenant_id'];
        $orderRef = $event->data['paymentRequest']['encodedId'] ?? null;

        // Queue a background job, passing tenant explicitly
        FulfillOrderJob::dispatch($tenantId, $orderRef);
    }
}
```

### Event mapping

The controller maps gateway event strings to SDK events:

| Gateway payload `event` field | SDK event class |
|---|---|
| `payment.completed` / `payment.success` / `transaction` | `PaymentCompleted` |
| `payment.failed` / `payment.declined` | `PaymentFailed` |
| `order.cancelled` / `order.canceled` | `OrderCancelled` |

For payloads without an `event` field (Qredit's default callback shape per merchant guide §6 just includes `status`, `records[].transactionStatus`), the controller synthesizes `event: "transaction"` and dispatches `PaymentCompleted`. Customize the mapping by extending `WebhookController`.

## Inbound payload shape (merchant guide §6)

```json
{
  "status": true,
  "code": "00",
  "message": "Success",
  "reference": "1775133836693",
  "totalCount": "1",
  "offset": "0",
  "records": [
    {
      "amount": 1000.00,
      "currency": "ILS",
      "operation": "ONLINE_QR_PURCHASE",
      "reference": "123456789",
      "clientReference": "CLI-987",
      "providerReference": "PROV-456",
      "transactionStatus": "SUCCESS",
      "paymentRequest": { "encodedId": "pay_req_888", "amount": 1000.00 },
      "senderAccount": { "encodedId": "acc_001", "accountNumber": "12345678", "currencyCode": "ILS" },
      "receiverAccount": { "encodedId": "acc_002", "accountNumber": "87654321", "currencyCode": "ILS" },
      "transDateTimeText": "03/04/2026 15:20:59"
    }
  ]
}
```

`processWebhook()` normalizes this to:

```php
[
    'event'         => 'transaction',       // or whatever $payload['event'] is
    'data'          => $payload['records'][0] + ['_tenant_id' => 'tenant-b'],
    'raw'           => $payload,
    'tenant_id'     => 'tenant-b',
    'processed_at'  => '2026-04-14T18:06:06+00:00',
]
```

## Signature verification details

The SDK verifies both lowercase and uppercase hex signatures (since the gateway's case handling isn't 100% documented). Both comparisons use `hash_equals` to prevent timing attacks.

If verification fails, the controller returns `400 { "status": "rejected", "message": "Invalid webhook signature" }` and logs the event with `tenant_id`. Qredit will retry; your server won't act on an unverified payload.

To disable signature verification (NOT recommended — dev/local only):

```env
QREDIT_VERIFY_WEBHOOK_SIGNATURE=false
```

## Handling retries + idempotency

Qredit retries failed webhook deliveries. Your listener must be idempotent:

```php
public function handle(PaymentCompleted $event): void
{
    $reference = $event->data['reference'];

    DB::transaction(function () use ($reference, $event) {
        $order = Order::where('qredit_reference', $reference)->lockForUpdate()->first();

        // Skip if already processed — idempotency
        if (! $order || $order->status === 'paid') {
            return;
        }

        $order->update(['status' => 'paid']);
        $this->dispatchFulfillment($order);
    });
}
```

Use the `reference` field as your idempotency key — Qredit guarantees it's unique per transaction.

## Queue-safe handling

Webhook listeners should offload heavy work to queues. Always pass `_tenant_id` explicitly:

```php
class FulfillOrder implements ShouldQueue
{
    public function handle(PaymentCompleted $event): void
    {
        FulfillOrderJob::dispatch(
            tenantId:  $event->data['_tenant_id'],
            reference: $event->data['reference'],
        );
    }
}

class FulfillOrderJob implements ShouldQueue
{
    public function __construct(public string $tenantId, public string $reference) {}

    public function handle(): void
    {
        // Fresh SDK call scoped to the correct tenant — NEVER use core() helpers here
        $details = Qredit::forTenant($this->tenantId)->listTransactions([
            'reference' => $this->reference,
        ]);

        // ... fulfill ...
    }
}
```

## Custom webhook controllers

The default `WebhookController` covers the common case. For custom routing, subclass it:

```php
namespace App\Http\Controllers;

use Qredit\LaravelQredit\Controllers\WebhookController as Base;

class MyWebhookController extends Base
{
    protected function dispatchSpecificEvent(array $processed): void
    {
        match ($processed['event']) {
            'refund' => event(new \App\Events\QreditRefund($processed['data'])),
            default  => parent::dispatchSpecificEvent($processed),
        };
    }
}
```

Then route manually:

```php
Route::post('/qredit/webhook/{tenant}', [\App\Http\Controllers\MyWebhookController::class, 'handle']);
```

## Troubleshooting

### Webhooks return 400 but the payload looks correct

Verify the `tenant` route parameter matches what `TenantResolver::tenantIdFromWebhook()` expects. A mismatched tenant id → wrong secret → signature fails.

### Webhooks return 200 but nothing happens downstream

Event listeners are queued in Laravel's event dispatcher — make sure your queue worker is running if your listeners implement `ShouldQueue`. Also verify the events are registered in `EventServiceProvider::$listen`.

### Signature verification fails in local dev but passes in production

Qredit's gateway can only reach public URLs. Use ngrok / Herd Share to expose your local webhook endpoint, and register the public URL with Qredit.

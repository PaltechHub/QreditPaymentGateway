<?php

/**
 * Webhook handler example.
 *
 * The SDK ships a ready-made receiver — this file shows how to wire it up,
 * bind listeners, and write a custom controller for advanced cases.
 */

use Qredit\LaravelQredit\Events\OrderCancelled;
use Qredit\LaravelQredit\Events\PaymentCompleted;
use Qredit\LaravelQredit\Events\PaymentFailed;
use Qredit\LaravelQredit\Events\WebhookReceived;
use Qredit\LaravelQredit\Facades\Qredit;

// ===========================================================================
// 1. REGISTER THE ROUTE
// ===========================================================================

/**
 * In routes/web.php:
 *
 *   // single-tenant
 *   Route::qreditWebhook('/qredit/webhook');
 *
 *   // multi-tenant — the {tenant} parameter feeds TenantResolver::tenantIdFromWebhook()
 *   Route::qreditWebhook('/qredit/webhook/{tenant}');
 *
 * The macro:
 *   - POSTs to the given path
 *   - disables CSRF
 *   - names the route `qredit.webhook`
 */

// ===========================================================================
// 2. BIND LISTENERS
// ===========================================================================

/**
 * In app/Providers/EventServiceProvider.php:
 */
class MyEventServiceProvider extends \Illuminate\Foundation\Support\Providers\EventServiceProvider
{
    protected $listen = [
        // Every webhook that passes signature verification
        WebhookReceived::class => [
            \App\Listeners\LogWebhook::class,
        ],

        // Payment completed — customer successfully paid
        PaymentCompleted::class => [
            \App\Listeners\FulfillOrder::class,
            \App\Listeners\SendReceiptEmail::class,
        ],

        // Payment failed — customer tried but payment was declined
        PaymentFailed::class => [
            \App\Listeners\NotifyCustomerOfFailure::class,
            \App\Listeners\FlagOrderForReview::class,
        ],

        // Order cancelled (from gateway side)
        OrderCancelled::class => [
            \App\Listeners\ReleaseStock::class,
        ],
    ];
}

// ===========================================================================
// 3. LISTENER — IDEMPOTENT + DEFERS TO QUEUE
// ===========================================================================

class FulfillOrder
{
    public function handle(PaymentCompleted $event): void
    {
        $tenantId = $event->data['_tenant_id'] ?? null;
        $reference = $event->data['reference'] ?? null;

        if (! $reference) {
            \Log::warning('PaymentCompleted event missing reference', $event->data);
            return;
        }

        // Defer heavy work. Always pass tenant explicitly.
        FulfillOrderJob::dispatch($tenantId, $reference);
    }
}

class FulfillOrderJob implements \Illuminate\Contracts\Queue\ShouldQueue
{
    public function __construct(
        public ?string $tenantId,
        public string $reference,
    ) {}

    public function handle(): void
    {
        // Idempotency check — webhooks retry, listener must be safe to run twice.
        \DB::transaction(function () {
            $order = \App\Models\Order::where('qredit_reference', $this->reference)
                ->lockForUpdate()
                ->first();

            if (! $order || $order->status === 'paid') {
                return;
            }

            $order->update(['status' => 'paid', 'paid_at' => now()]);

            // Confirm with the gateway before fulfilling — double-check the transaction.
            $txns = Qredit::forTenant($this->tenantId)->listTransactions([
                'reference' => $this->reference,
            ]);

            if (($txns['records'][0]['transactionStatus'] ?? null) === 'SUCCESS') {
                $this->triggerFulfillment($order);
            }
        });
    }

    protected function triggerFulfillment($order): void
    {
        // your shipment logic here
    }
}

// ===========================================================================
// 4. MANUAL VERIFICATION (IF YOU NEED A CUSTOM CONTROLLER)
// ===========================================================================

/**
 * If the SDK's WebhookController doesn't fit your use case, verify manually:
 */
class CustomWebhookController
{
    public function handle(\Illuminate\Http\Request $request)
    {
        $tenantId = $request->route('tenant');

        $valid = Qredit::forTenant($tenantId)->verifyWebhookSignature(
            $request->all(),
            $request->header('Authorization'),
        );

        if (! $valid) {
            abort(400, 'Invalid signature');
        }

        // your custom event dispatch
        event(new \App\Events\QreditCustomEvent($request->all() + ['tenant_id' => $tenantId]));

        return response()->json(['status' => 'RECEIVED']);
    }
}

// ===========================================================================
// 5. EXTEND THE DEFAULT CONTROLLER
// ===========================================================================

/**
 * Add custom event mapping without rewriting the whole controller.
 */
class MyWebhookController extends \Qredit\LaravelQredit\Controllers\WebhookController
{
    protected function dispatchSpecificEvent(array $processed): void
    {
        $event = $processed['event'] ?? null;
        $data = $processed['data'] ?? [];

        if ($event === 'refund') {
            event(new \App\Events\QreditRefund($data));

            return;
        }

        // Fall through to the SDK's default mapping for standard events.
        parent::dispatchSpecificEvent($processed);
    }
}

/**
 * Then route it manually (overriding the macro):
 *
 *   Route::post('/qredit/webhook/{tenant}', [MyWebhookController::class, 'handle'])
 *       ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
 *       ->name('qredit.webhook');
 */

// ===========================================================================
// 6. TESTING A WEBHOOK
// ===========================================================================

use Qredit\LaravelQredit\Security\HmacSigner;
use Qredit\LaravelQredit\Security\ValueFlattener;

function testWebhookFulfillsOrder()
{
    \Illuminate\Support\Facades\Event::fake();

    $payload = [
        'status' => true,
        'code' => '00',
        'records' => [[
            'msgId' => 'hook-test-1',
            'reference' => 'PAY-1',
            'transactionStatus' => 'SUCCESS',
            'amount' => 32.0,
        ]],
        'msgId' => 'hook-test-1',
    ];

    // Compute the signature the same way the gateway would
    $signature = HmacSigner::sign(
        'test-tenant-secret',
        'hook-test-1',
        ValueFlattener::flatten($payload),
    );

    // POST it to our webhook endpoint
    test()->postJson('/qredit/webhook/tenant-a', $payload, [
        'Authorization' => "HmacSHA512_O {$signature}",
    ])->assertOk();

    \Illuminate\Support\Facades\Event::assertDispatched(
        PaymentCompleted::class,
        fn ($e) => $e->data['reference'] === 'PAY-1'
    );
}

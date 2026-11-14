<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Qredit\LaravelQredit\Events\OrderCancelled;
use Qredit\LaravelQredit\Events\PaymentCompleted;
use Qredit\LaravelQredit\Events\PaymentFailed;
use Qredit\LaravelQredit\Events\WebhookReceived;
use Qredit\LaravelQredit\Qredit;
use Qredit\LaravelQredit\QreditManager;
use Qredit\LaravelQredit\Security\HmacSigner;
use Qredit\LaravelQredit\Security\ValueFlattener;
use Qredit\LaravelQredit\Testing\FakeQredit;

/*
 * The gateway posts a flat transaction record with no `event` field (same shape
 * as a real callback, synthetic values). transactionStatus is one of
 * PENDING | SUCCESS | FAILED | CANCELLED | WAITING_APPROVAL.
 */
function webhookRecord(array $overrides = []): array
{
    return array_replace([
        'amount' => 100.0,
        'currency' => 'ILS',
        'isReversed' => false,
        'operation' => 'ONLINE_CARD_PURCHASE',
        'paymentRequest' => [
            'amountCents' => 10000,
            'orderReference' => '1111111111',
            'paymentRequestStatus' => 'PAYMENT_IN_PROGRESS',
            'reference' => '22222222',
        ],
        'providerReference' => '3333333333333333333333',
        'reference' => '4444444444444444',
        'transactionStatus' => 'SUCCESS',
        'msgId' => 'sproxy_5555555555555555555555',
    ], $overrides);
}

/** The gateway's answer to getPayment() for the webhook's payment request. */
function gatewaySays(string $status, string $reference = '22222222', array $extra = []): FakeQredit
{
    $fake = new FakeQredit([
        'getPayment' => fn () => ['status' => true, 'records' => [
            ['reference' => $reference, 'paymentRequestStatus' => $status] + $extra,
        ]],
    ]);
    app(QreditManager::class)->fake($fake);

    return $fake;
}

beforeEach(function () {
    config([
        'qredit.verify_webhook_signature' => false,
        'qredit.api_key' => 'k',
        'qredit.secret_key' => 's',
        'qredit.client.version' => 'ccc1.0',
    ]);
});

it('derives the event from transactionStatus', function (array $record, string $event) {
    $q = Qredit::make(['api_key' => 'k', 'secret_key' => 's', 'client_version' => 'ccc1.0', 'skip_auth' => true]);

    expect($q->processWebhook($record)['event'])->toBe($event);
})->with([
    'SUCCESS' => [webhookRecord(), 'payment.completed'],
    'lower-case success' => [webhookRecord(['transactionStatus' => 'success']), 'payment.completed'],
    'FAILED' => [webhookRecord(['transactionStatus' => 'FAILED']), 'payment.failed'],
    'CANCELLED' => [webhookRecord(['transactionStatus' => 'CANCELLED']), 'order.cancelled'],
    'PENDING' => [webhookRecord(['transactionStatus' => 'PENDING']), 'payment.pending'],
    'WAITING_APPROVAL' => [webhookRecord(['transactionStatus' => 'WAITING_APPROVAL']), 'payment.pending'],
    'reversed SUCCESS' => [webhookRecord(['isReversed' => true]), 'payment.reversed'],
    'unknown status' => [webhookRecord(['transactionStatus' => 'SOMETHING_NEW']), 'payment.unknown'],
    'missing status' => [array_diff_key(webhookRecord(), ['transactionStatus' => true]), 'payment.unknown'],
    'event field cannot override PENDING' => [webhookRecord(['transactionStatus' => 'PENDING', 'event' => 'payment.completed']), 'payment.pending'],
    'type field cannot override FAILED' => [webhookRecord(['transactionStatus' => 'FAILED', 'type' => 'payment.completed']), 'payment.failed'],
    'event field used without a status' => [['event' => 'refund.completed'], 'refund.completed'],
]);

it('fires PaymentCompleted only for a successful, non-reversed payment', function (array $record, ?string $expected) {
    Route::qreditWebhook();
    Event::fake();
    gatewaySays('PAID');

    $this->postJson('/qredit/webhook', $record)->assertOk();

    Event::assertDispatched(WebhookReceived::class);

    foreach ([PaymentCompleted::class, PaymentFailed::class, OrderCancelled::class] as $class) {
        $class === $expected
            ? Event::assertDispatched($class)
            : Event::assertNotDispatched($class);
    }
})->with([
    'SUCCESS' => [webhookRecord(), PaymentCompleted::class],
    'FAILED' => [webhookRecord(['transactionStatus' => 'FAILED']), PaymentFailed::class],
    'CANCELLED' => [webhookRecord(['transactionStatus' => 'CANCELLED']), OrderCancelled::class],
    'PENDING' => [webhookRecord(['transactionStatus' => 'PENDING']), null],
    'WAITING_APPROVAL' => [webhookRecord(['transactionStatus' => 'WAITING_APPROVAL']), null],
    'reversed SUCCESS' => [webhookRecord(['isReversed' => true]), null],
    'unknown status' => [webhookRecord(['transactionStatus' => 'SOMETHING_NEW']), null],
    'missing status' => [array_diff_key(webhookRecord(), ['transactionStatus' => true]), null],
    'event field on a PENDING record' => [webhookRecord(['transactionStatus' => 'PENDING', 'event' => 'payment.completed']), null],
]);

it('verifies webhook signatures unless told not to', function () {
    $key = 'QREDIT_VERIFY_WEBHOOK_SIGNATURE';
    $saved = [$_ENV[$key] ?? null, $_SERVER[$key] ?? null, getenv($key)];
    unset($_ENV[$key], $_SERVER[$key]);
    putenv($key);

    try {
        $config = require __DIR__.'/../../config/qredit.php';
    } finally {
        [$env, $server, $put] = $saved;
        $env !== null && $_ENV[$key] = $env;
        $server !== null && $_SERVER[$key] = $server;
        $put !== false && putenv("{$key}={$put}");
    }

    expect($config['verify_webhook_signature'])->toBeTrue();
});

it('takes the payment request from the gateway once it confirms PAID', function () {
    Route::qreditWebhook();
    Event::fake();
    $fake = gatewaySays('PAID', extra: ['orderReference' => 'FROM-GATEWAY']);

    $this->postJson('/qredit/webhook', webhookRecord())->assertOk();

    $fake->assertCalledWith('getPayment', fn ($args) => $args[0] === '22222222');
    Event::assertDispatched(PaymentCompleted::class, fn ($e) => $e->payment['paymentRequest']['orderReference'] === 'FROM-GATEWAY'
        && $e->payment['paymentRequest']['paymentRequestStatus'] === 'PAID');
});

it('asks Qredit to redeliver a SUCCESS webhook the gateway does not confirm as PAID', function (array $records, array $record) {
    Route::qreditWebhook();
    Event::fake();
    app(QreditManager::class)->fake(new FakeQredit(['getPayment' => ['status' => true, 'records' => $records]]));

    $this->postJson('/qredit/webhook', $record)->assertStatus(503);

    Event::assertNotDispatched(PaymentCompleted::class);
    Event::assertNotDispatched(WebhookReceived::class);
})->with([
    'still in progress' => [[['reference' => '22222222', 'paymentRequestStatus' => 'PAYMENT_IN_PROGRESS']], webhookRecord()],
    'cancelled' => [[['reference' => '22222222', 'paymentRequestStatus' => 'CANCELLED']], webhookRecord()],
    'gateway answers for another request' => [[['reference' => 'OTHER', 'paymentRequestStatus' => 'PAID']], webhookRecord()],
    'gateway knows no such request' => [[], webhookRecord()],
    'no payment request reference' => [[['reference' => '22222222', 'paymentRequestStatus' => 'PAID']], array_diff_key(webhookRecord(), ['paymentRequest' => true])],
]);

/** Sign the way the gateway's inbound signer may: top-level scalars only. */
function signTopLevel(array $payload): string
{
    return 'HmacSHA512_O '.HmacSigner::sign('s', $payload['msgId'], ValueFlattener::flattenTopLevel($payload));
}

it('ignores a records wrapper that a top-level-only signature does not cover', function () {
    config(['qredit.verify_webhook_signature' => true]);
    $signed = webhookRecord(['transactionStatus' => 'PENDING']);
    $tampered = $signed + ['records' => [webhookRecord()]];

    $processed = Qredit::make(['api_key' => 'k', 'secret_key' => 's', 'client_version' => 'ccc1.0', 'skip_auth' => true])
        ->processWebhook($tampered, signTopLevel($signed), json_encode($tampered));

    expect($processed['signature_coverage'])->toBe(Qredit::SIGNATURE_COVERS_TOP_LEVEL)
        ->and($processed['event'])->toBe('payment.pending');
});

it('reads a records wrapper the signature covers in full', function () {
    config(['qredit.verify_webhook_signature' => true]);
    $payload = ['status' => true, 'msgId' => 'm1', 'records' => [webhookRecord()]];
    $header = 'HmacSHA512_O '.HmacSigner::sign('s', 'm1', ValueFlattener::flatten($payload));

    $processed = Qredit::make(['api_key' => 'k', 'secret_key' => 's', 'client_version' => 'ccc1.0', 'skip_auth' => true])
        ->processWebhook($payload, $header);

    expect($processed['signature_coverage'])->toBe(Qredit::SIGNATURE_COVERS_BODY)
        ->and($processed['event'])->toBe('payment.completed');
});

<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Qredit\LaravelQredit\Events\OrderCancelled;
use Qredit\LaravelQredit\Events\PaymentCompleted;
use Qredit\LaravelQredit\Events\PaymentFailed;
use Qredit\LaravelQredit\Events\WebhookReceived;
use Qredit\LaravelQredit\Qredit;

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
]);

it('fires PaymentCompleted only for a successful, non-reversed payment', function (array $record, ?string $expected) {
    Route::qreditWebhook();
    Event::fake();

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
]);

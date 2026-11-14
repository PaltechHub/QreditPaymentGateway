<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Qredit\LaravelQredit\Events\PaymentCompleted;
use Qredit\LaravelQredit\Events\WebhookReceived;

beforeEach(function () {
    config(['qredit.api_key' => 'k', 'qredit.secret_key' => 's', 'qredit.verify_webhook_signature' => false]);
    Route::qreditWebhook();
});

it('dispatches PaymentCompleted for a successful transaction', function () {
    Event::fake();

    $this->postJson('/qredit/webhook', ['msgId' => 'h1', 'transactionStatus' => 'SUCCESS'])->assertOk();

    Event::assertDispatched(PaymentCompleted::class);
});

it('does not dispatch PaymentCompleted for an unknown or missing status', function (array $payload) {
    Event::fake();

    $this->postJson('/qredit/webhook', $payload)->assertOk();

    Event::assertDispatched(WebhookReceived::class);
    Event::assertNotDispatched(PaymentCompleted::class);
})->with([
    'pending' => [['msgId' => 'h2', 'transactionStatus' => 'PENDING']],
    'missing' => [['msgId' => 'h3']],
]);

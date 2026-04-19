<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Testing;

use PHPUnit\Framework\Assert;
use Qredit\LaravelQredit\Connectors\QreditConnector;
use Qredit\LaravelQredit\Qredit;

/**
 * Drop-in test double.
 *
 *   $fake = new FakeQredit(['createOrder' => ['status' => true, 'records' => [...]]]);
 *   Qredit::fake($fake);
 *
 *   // ...run your code...
 *
 *   $fake->assertCalled('createOrder');
 *   $fake->assertCalledWith('createOrder', fn ($args) => $args[0]['amountCents'] === 3200);
 *
 * Every real Qredit method is overridden to route through record() — no HTTP,
 * no token, no signing. Provide canned responses per method via the constructor.
 */
class FakeQredit extends Qredit
{
    /** @var array<int, array{method: string, args: array}> */
    public array $calls = [];

    /** @var array<string, mixed> */
    protected array $responses;

    /**
     * @param  array<string, mixed>  $responses  Map of method-name → return value or closure.
     */
    public function __construct(array $responses = [])
    {
        $this->responses = $responses;
        // Intentionally skip parent::__construct() — no connector, no HTTP.
    }

    public function getConnector(): QreditConnector
    {
        throw new \BadMethodCallException('FakeQredit has no connector. Use Saloon MockClient if you need request-level assertions.');
    }

    public function authenticate(bool $force = false): string
    {
        return $this->record('authenticate', [$force], 'fake-token');
    }

    public function getCachedToken(): ?string
    {
        return $this->record('getCachedToken', [], 'fake-token');
    }

    public function cacheToken(string $token, int $ttl = 3600): void
    {
        $this->record('cacheToken', [$token, $ttl], null);
    }

    public function clearCachedToken(): void
    {
        $this->record('clearCachedToken', [], null);
    }

    public function isSandbox(): bool
    {
        return $this->record('isSandbox', [], true);
    }

    public function getApiUrl(): string
    {
        return $this->record('getApiUrl', [], 'https://fake.qredit.test');
    }

    // Payment requests
    public function createPayment(array $data): array
    { return $this->record('createPayment', [$data], $this->defaultEnvelope()); }

    public function getPayment(string $paymentRequestReference): array
    { return $this->record('getPayment', [$paymentRequestReference], $this->defaultEnvelope()); }

    public function updatePayment(string $paymentRequestReference, array $data): array
    { return $this->record('updatePayment', [$paymentRequestReference, $data], $this->defaultEnvelope()); }

    public function deletePayment(string $paymentRequestReference, ?string $reason = null): array
    { return $this->record('deletePayment', [$paymentRequestReference, $reason], $this->defaultEnvelope()); }

    public function listPayments(array $query = []): array
    { return $this->record('listPayments', [$query], $this->defaultEnvelope()); }

    public function generateQR(array $query): array
    { return $this->record('generateQR', [$query], $this->defaultEnvelope()); }

    public function calculateFees(array $data): array
    { return $this->record('calculateFees', [$data], $this->defaultEnvelope()); }

    public function initPayment(array $data): array
    { return $this->record('initPayment', [$data], $this->defaultEnvelope()); }

    // Orders
    public function createOrder(array $data): array
    { return $this->record('createOrder', [$data], $this->defaultEnvelope()); }

    public function registerOrder(array $data): array
    { return $this->record('registerOrder', [$data], $this->defaultEnvelope()); }

    public function getOrder(string $orderReference): array
    { return $this->record('getOrder', [$orderReference], $this->defaultEnvelope()); }

    public function updateOrder(string $orderReference, array $data): array
    { return $this->record('updateOrder', [$orderReference, $data], $this->defaultEnvelope()); }

    public function cancelOrder(string $orderReference, ?string $reason = null): array
    { return $this->record('cancelOrder', [$orderReference, $reason], $this->defaultEnvelope()); }

    public function listOrders(array $query = []): array
    { return $this->record('listOrders', [$query], $this->defaultEnvelope()); }

    // Customers + transactions
    public function listCustomers(array $filters = []): array
    { return $this->record('listCustomers', [$filters], $this->defaultEnvelope()); }

    public function listTransactions(array $filters = []): array
    { return $this->record('listTransactions', [$filters], $this->defaultEnvelope()); }

    public function changeClearingStatus(array $data): array
    { return $this->record('changeClearingStatus', [$data], $this->defaultEnvelope()); }

    // Webhook
    public function verifyWebhookSignature(array $payload, string $authorizationHeader): bool
    { return $this->record('verifyWebhookSignature', [$payload, $authorizationHeader], true); }

    public function processWebhook(array $payload, ?string $authorizationHeader = null): array
    {
        return $this->record('processWebhook', [$payload, $authorizationHeader], [
            'event' => 'transaction',
            'data' => $payload['records'][0] ?? $payload,
            'raw' => $payload,
            'processed_at' => date('c'),
        ]);
    }

    // ---- Internals ------------------------------------------------------

    protected function record(string $method, array $args, mixed $default): mixed
    {
        $this->calls[] = ['method' => $method, 'args' => $args];

        if (array_key_exists($method, $this->responses)) {
            $response = $this->responses[$method];

            return is_callable($response) ? $response(...$args) : $response;
        }

        return $default;
    }

    protected function defaultEnvelope(): array
    {
        return ['status' => true, 'code' => '00', 'message' => 'OK', 'records' => []];
    }

    // ---- Assertions -----------------------------------------------------

    public function assertCalled(string $method, ?int $times = null): void
    {
        $count = count(array_filter($this->calls, fn ($c) => $c['method'] === $method));

        if ($times === null) {
            Assert::assertGreaterThan(0, $count, "Expected Qredit::{$method}() to be called, but it wasn't.");
        } else {
            Assert::assertSame($times, $count, "Expected Qredit::{$method}() to be called {$times} time(s), got {$count}.");
        }
    }

    public function assertNotCalled(string $method): void
    {
        $count = count(array_filter($this->calls, fn ($c) => $c['method'] === $method));
        Assert::assertSame(0, $count, "Expected Qredit::{$method}() NOT to be called, but it was called {$count} time(s).");
    }

    /**
     * @param  callable(array):bool  $predicate
     */
    public function assertCalledWith(string $method, callable $predicate): void
    {
        foreach ($this->calls as $call) {
            if ($call['method'] === $method && $predicate($call['args'])) {
                Assert::assertTrue(true);

                return;
            }
        }

        Assert::fail("No Qredit::{$method}() call matched the given predicate.");
    }
}

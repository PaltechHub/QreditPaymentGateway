<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Qredit\LaravelQredit\QreditServiceProvider;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

abstract class TestCase extends Orchestra
{
    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Clean up the testing environment before the next test.
     */
    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }

    /**
     * Get package providers.
     */
    protected function getPackageProviders($app): array
    {
        return [
            QreditServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     */
    protected function getEnvironmentSetUp($app): void
    {
        // Setup default config values
        $app['config']->set('qredit.api_key', 'test-api-key');
        $app['config']->set('qredit.sandbox', true);
        $app['config']->set('qredit.webhook.secret', 'test-webhook-secret');
        $app['config']->set('qredit.cache_token', true);
        $app['config']->set('qredit.debug', false);
    }

    /**
     * Create a mock client with a successful authentication response.
     */
    protected function mockSuccessfulAuth(): MockClient
    {
        return new MockClient([
            MockResponse::make([
                'token' => 'test-token-12345',
                'expires_in' => 3600,
            ], 200),
        ]);
    }

    /**
     * Create a mock client with a failed authentication response.
     */
    protected function mockFailedAuth(): MockClient
    {
        return new MockClient([
            MockResponse::make([
                'error' => 'Invalid API key',
                'message' => 'The provided API key is invalid',
            ], 401),
        ]);
    }

    /**
     * Mock a successful payment creation response.
     */
    protected function mockSuccessfulPaymentCreation(): MockClient
    {
        return new MockClient([
            // First auth request
            MockResponse::make([
                'token' => 'test-token-12345',
                'expires_in' => 3600,
            ], 200),
            // Payment creation request
            MockResponse::make([
                'id' => 'pay_123456',
                'amount' => 10000,
                'currency' => 'ILS',
                'status' => 'pending',
                'redirect_url' => 'https://checkout.qredit.com/pay/123456',
                'created_at' => now()->toIso8601String(),
            ], 201),
        ]);
    }

    /**
     * Mock a webhook payload.
     */
    protected function mockWebhookPayload(string $event = 'payment.completed'): array
    {
        return [
            'event' => $event,
            'data' => [
                'id' => 'pay_123456',
                'amount' => 10000,
                'currency' => 'ILS',
                'status' => 'completed',
                'order_reference' => 'ORD-123',
                'customer' => [
                    'email' => 'customer@example.com',
                    'name' => 'John Doe',
                ],
                'completed_at' => now()->toIso8601String(),
            ],
            'timestamp' => now()->timestamp,
        ];
    }

    /**
     * Generate a webhook signature.
     */
    protected function generateWebhookSignature(array $payload, string $secret): string
    {
        return hash_hmac('sha512', json_encode($payload), $secret);
    }

    /**
     * Get a test connector instance.
     */
    protected function getTestConnector(): \Qredit\LaravelQredit\Connectors\QreditConnector
    {
        return new \Qredit\LaravelQredit\Connectors\QreditConnector(
            apiKey: 'test-api-key',
            sandbox: true
        );
    }
}
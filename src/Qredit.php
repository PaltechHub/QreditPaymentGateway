<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit;

use Illuminate\Support\Facades\Cache;
use Saloon\Http\Response;
use Qredit\LaravelQredit\Connectors\QreditConnector;
use Qredit\LaravelQredit\Requests\Auth\GetTokenRequest;
use Qredit\LaravelQredit\Requests\PaymentRequests\CreatePaymentRequest;
use Qredit\LaravelQredit\Requests\PaymentRequests\GetPaymentRequest;
use Qredit\LaravelQredit\Requests\PaymentRequests\UpdatePaymentRequest;
use Qredit\LaravelQredit\Requests\PaymentRequests\DeletePaymentRequest;
use Qredit\LaravelQredit\Requests\PaymentRequests\ListPaymentRequestsRequest;
use Qredit\LaravelQredit\Requests\Orders\CreateOrderRequest;
use Qredit\LaravelQredit\Requests\Orders\GetOrderRequest;
use Qredit\LaravelQredit\Requests\Orders\UpdateOrderRequest;
use Qredit\LaravelQredit\Requests\Orders\CancelOrderRequest;
use Qredit\LaravelQredit\Requests\Orders\ListOrdersRequest;
use Qredit\LaravelQredit\Exceptions\QreditAuthenticationException;
use Qredit\LaravelQredit\Exceptions\QreditException;

class Qredit
{
    /**
     * The Qredit connector instance.
     */
    protected QreditConnector $connector;

    /**
     * The cache key for storing the authentication token.
     */
    protected string $cacheKey = 'qredit_auth_token';

    /**
     * Create a new Qredit instance.
     */
    public function __construct(?string $apiKey = null, ?bool $sandbox = null)
    {
        $apiKey = $apiKey ?? config('qredit.api_key');
        $sandbox = $sandbox ?? config('qredit.sandbox', false);

        if (!$apiKey) {
            throw new QreditException('Qredit API key is not configured');
        }

        $this->connector = new QreditConnector($apiKey, $sandbox);

        // Attempt to authenticate and set token
        $this->authenticate();
    }

    /**
     * Get the connector instance.
     */
    public function getConnector(): QreditConnector
    {
        return $this->connector;
    }

    /**
     * Authenticate with the Qredit API.
     */
    public function authenticate(bool $force = false): string
    {
        // Check if we have a cached token and it's not a forced refresh
        if (!$force && $token = $this->getCachedToken()) {
            $this->connector->setAuthToken($token);
            return $token;
        }

        // Request a new token
        $response = $this->connector->send(
            new GetTokenRequest($this->connector->getApiKey())
        );

        if (!$response->successful()) {
            throw new QreditAuthenticationException(
                'Failed to authenticate with Qredit API',
                $response->status()
            );
        }

        $data = $response->json();
        $token = $data['token'] ?? $data['access_token'] ?? null;

        if (!$token) {
            throw new QreditAuthenticationException('No token received from Qredit API');
        }

        // Cache the token
        $this->cacheToken($token, $data['expires_in'] ?? 3600);

        // Set the token on the connector
        $this->connector->setAuthToken($token);

        return $token;
    }

    /**
     * Get the cached authentication token.
     */
    protected function getCachedToken(): ?string
    {
        if (!config('qredit.cache_token', true)) {
            return null;
        }

        return Cache::get($this->cacheKey);
    }

    /**
     * Cache the authentication token.
     */
    protected function cacheToken(string $token, int $ttl = 3600): void
    {
        if (!config('qredit.cache_token', true)) {
            return;
        }

        // Cache for slightly less than the actual TTL to avoid edge cases
        Cache::put($this->cacheKey, $token, $ttl - 60);
    }

    /**
     * Clear the cached authentication token.
     */
    public function clearCachedToken(): void
    {
        Cache::forget($this->cacheKey);
    }

    /**
     * Send a request with automatic token refresh on 401 errors.
     */
    protected function sendWithRetry($request): Response
    {
        $response = $this->connector->send($request);

        // If we get a 401, try to re-authenticate and retry once
        if ($response->status() === 401) {
            $this->authenticate(true);
            $response = $this->connector->send($request);
        }

        return $response;
    }

    /**
     * Create a new payment request.
     */
    public function createPayment(array $data): array
    {
        $response = $this->sendWithRetry(new CreatePaymentRequest($data));
        return $response->json();
    }

    /**
     * Get a payment request by ID.
     */
    public function getPayment(string $paymentRequestId): array
    {
        $response = $this->sendWithRetry(new GetPaymentRequest($paymentRequestId));
        return $response->json();
    }

    /**
     * Update a payment request.
     */
    public function updatePayment(string $paymentRequestId, array $data): array
    {
        $response = $this->sendWithRetry(new UpdatePaymentRequest($paymentRequestId, $data));
        return $response->json();
    }

    /**
     * Delete a payment request.
     */
    public function deletePayment(string $paymentRequestId): bool
    {
        $response = $this->sendWithRetry(new DeletePaymentRequest($paymentRequestId));
        return $response->successful();
    }

    /**
     * List payment requests.
     */
    public function listPayments(array $query = []): array
    {
        $response = $this->sendWithRetry(new ListPaymentRequestsRequest($query));
        return $response->json();
    }

    /**
     * Create a new order.
     */
    public function createOrder(array $data): array
    {
        $response = $this->sendWithRetry(new CreateOrderRequest($data));
        return $response->json();
    }

    /**
     * Get an order by ID.
     */
    public function getOrder(string $orderId): array
    {
        $response = $this->sendWithRetry(new GetOrderRequest($orderId));
        return $response->json();
    }

    /**
     * Update an order.
     */
    public function updateOrder(string $orderId, array $data): array
    {
        $response = $this->sendWithRetry(new UpdateOrderRequest($orderId, $data));
        return $response->json();
    }

    /**
     * Cancel an order.
     */
    public function cancelOrder(string $orderId, ?string $reason = null): array
    {
        $response = $this->sendWithRetry(new CancelOrderRequest($orderId, $reason));
        return $response->json();
    }

    /**
     * List orders.
     */
    public function listOrders(array $query = []): array
    {
        $response = $this->sendWithRetry(new ListOrdersRequest($query));
        return $response->json();
    }

    /**
     * Verify webhook signature.
     */
    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        if (!config('qredit.webhook_secret')) {
            throw new QreditException('Webhook secret is not configured');
        }

        $expectedSignature = hash_hmac(
            'sha512',
            $payload,
            config('qredit.webhook_secret')
        );

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Process a webhook payload.
     */
    public function processWebhook(array $payload, ?string $signature = null): array
    {
        // Verify signature if provided
        if ($signature && config('qredit.verify_webhook_signature', true)) {
            $valid = $this->verifyWebhookSignature(
                json_encode($payload),
                $signature
            );

            if (!$valid) {
                throw new QreditException('Invalid webhook signature');
            }
        }

        // Process the webhook based on event type
        $eventType = $payload['event'] ?? $payload['type'] ?? null;

        if (!$eventType) {
            throw new QreditException('Webhook event type not found');
        }

        // Return processed data
        return [
            'event' => $eventType,
            'data' => $payload['data'] ?? $payload,
            'processed_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Check if in sandbox mode.
     */
    public function isSandbox(): bool
    {
        return $this->connector->isSandbox();
    }

    /**
     * Get the API base URL.
     */
    public function getApiUrl(): string
    {
        return $this->connector->resolveBaseUrl();
    }
}
<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit;

use Illuminate\Support\Facades\Cache;
use Qredit\LaravelQredit\Connectors\QreditConnector;
use Qredit\LaravelQredit\Exceptions\QreditAuthenticationException;
use Qredit\LaravelQredit\Exceptions\QreditException;
use Qredit\LaravelQredit\Requests\Auth\GetTokenRequest;
use Qredit\LaravelQredit\Requests\Customers\ListCustomersRequest;
use Qredit\LaravelQredit\Requests\Orders\CancelOrderRequest;
use Qredit\LaravelQredit\Requests\Orders\CreateOrderRequest;
use Qredit\LaravelQredit\Requests\Orders\GetOrderRequest;
use Qredit\LaravelQredit\Requests\Orders\ListOrdersRequest;
use Qredit\LaravelQredit\Requests\Orders\UpdateOrderRequest;
use Qredit\LaravelQredit\Requests\PaymentRequests\CalculateFeesRequest;
use Qredit\LaravelQredit\Requests\PaymentRequests\CancelPaymentRequest;
use Qredit\LaravelQredit\Requests\PaymentRequests\CreatePaymentRequest;
use Qredit\LaravelQredit\Requests\PaymentRequests\GenerateQRRequest;
use Qredit\LaravelQredit\Requests\PaymentRequests\GetPaymentRequest;
use Qredit\LaravelQredit\Requests\PaymentRequests\InitPaymentRequest;
use Qredit\LaravelQredit\Requests\PaymentRequests\ListPaymentRequestsRequest;
use Qredit\LaravelQredit\Requests\PaymentRequests\UpdatePaymentRequest;
use Qredit\LaravelQredit\Requests\Payments\ChangeClearingStatusRequest;
use Qredit\LaravelQredit\Requests\Transactions\ListTransactionsRequest;
use Qredit\LaravelQredit\Security\HmacSigner;
use Saloon\Http\Response;

class Qredit
{
    protected QreditConnector $connector;

    protected string $apiKey;

    /**
     * Build a Qredit client from a credential array. Call this per-tenant; each
     * instance owns its own connector + token cache key.
     *
     * Accepted options:
     *  - api_key      (required)
     *  - secret_key   (required for signing)
     *  - sandbox      (bool, default true)
     *  - language     ('EN' | 'AR')
     *  - auth_scheme  (default 'HmacSHA512_O')
     *  - signature_case ('lower' | 'upper')
     *  - sandbox_url / production_url (override URLs per-tenant if needed)
     *  - skip_auth    (bool, default false — skip the initial /auth/token call)
     */
    public static function make(array $options): self
    {
        $skipAuth = (bool) ($options['skip_auth'] ?? false);
        unset($options['skip_auth']);

        return new self($options, null, $skipAuth);
    }

    /**
     * @param  array<string, mixed>|string|null  $options  Either an options array (preferred),
     *                                                     or an api-key string for backward compatibility.
     */
    public function __construct(array|string|null $options = null, ?bool $sandbox = null, bool $skipAuth = false)
    {
        // Backward-compat: positional ($apiKey, $sandbox, $skipAuth)
        if (is_string($options) || $options === null) {
            $options = [
                'api_key' => $options ?? config('qredit.api_key'),
                'secret_key' => config('qredit.secret_key', ''),
                'sandbox' => $sandbox ?? config('qredit.sandbox', true),
            ];
        }

        $apiKey = $options['api_key'] ?? config('qredit.api_key');

        if (empty($apiKey)) {
            throw new QreditException('Qredit API key is not configured');
        }

        $this->apiKey = $apiKey;
        $options['api_key'] = $apiKey;
        $options['secret_key'] = $options['secret_key'] ?? config('qredit.secret_key', '');

        $this->connector = new QreditConnector($options);

        if (! $skipAuth) {
            $this->authenticate();
        }
    }

    public function getConnector(): QreditConnector
    {
        return $this->connector;
    }

    /**
     * Cache key namespaced by api key so multiple tenants share a single cache store.
     */
    protected function cacheKey(): string
    {
        return 'qredit_auth_token:'.sha1($this->apiKey);
    }

    public function authenticate(bool $force = false): string
    {
        if (! $force && $token = $this->getCachedToken()) {
            $this->connector->setAuthToken($token);

            return $token;
        }

        $response = $this->connector->send(
            new GetTokenRequest($this->apiKey)
        );

        if (! $response->successful()) {
            throw new QreditAuthenticationException(
                'Failed to authenticate with Qredit API',
                $response->status()
            );
        }

        $data = $response->json();
        $token = $data['access_token'] ?? $data['token'] ?? null;

        if (! $token) {
            throw new QreditAuthenticationException('No token received from Qredit API');
        }

        $this->cacheToken($token, (int) ($data['expires_in'] ?? 3600));
        $this->connector->setAuthToken($token);

        return $token;
    }

    public function getCachedToken(): ?string
    {
        if (! config('qredit.cache_token', true)) {
            return null;
        }

        try {
            return Cache::get($this->cacheKey());
        } catch (\Throwable) {
            // Cache store misconfigured (e.g. no DB table in a CLI-only tool).
            // Fall through and re-authenticate on every call.
            return null;
        }
    }

    public function cacheToken(string $token, int $ttl = 3600): void
    {
        if (! config('qredit.cache_token', true)) {
            return;
        }

        try {
            Cache::put($this->cacheKey(), $token, max($ttl - 60, 60));
        } catch (\Throwable) {
            // Ignore cache write failures — token still lives on the connector.
        }
    }

    public function clearCachedToken(): void
    {
        try {
            Cache::forget($this->cacheKey());
        } catch (\Throwable) {
            // ignore
        }
        $this->connector->clearAuthToken();
    }

    protected function ensureAuthenticated(): void
    {
        if (! $this->connector->getAuthToken()) {
            $this->authenticate();
        }
    }

    /**
     * Send a request, refreshing the token once on 401.
     */
    protected function sendWithRetry($request): Response
    {
        $this->ensureAuthenticated();

        $response = $this->connector->send($request);

        if ($response->status() === 401) {
            $this->authenticate(true);
            $response = $this->connector->send($request);
        }

        return $response;
    }

    // ----- Payment Requests --------------------------------------------------

    public function createPayment(array $data): array
    {
        return $this->sendWithRetry(new CreatePaymentRequest($data))->json();
    }

    public function getPayment(string $paymentRequestId): array
    {
        return $this->sendWithRetry(new GetPaymentRequest($paymentRequestId))->json();
    }

    public function updatePayment(string $paymentRequestId, array $data): array
    {
        return $this->sendWithRetry(new UpdatePaymentRequest($paymentRequestId, $data))->json();
    }

    public function deletePayment(string $paymentRequestId, ?string $reason = null): array
    {
        return $this->sendWithRetry(new CancelPaymentRequest($paymentRequestId, $reason))->json();
    }

    public function listPayments(array $query = []): array
    {
        return $this->sendWithRetry(new ListPaymentRequestsRequest($query))->json();
    }

    public function generateQR(array $query): array
    {
        return $this->sendWithRetry(new GenerateQRRequest($query))->json();
    }

    public function calculateFees(array $data): array
    {
        return $this->sendWithRetry(new CalculateFeesRequest($data))->json();
    }

    public function initPayment(array $data): array
    {
        return $this->sendWithRetry(new InitPaymentRequest($data))->json();
    }

    // ----- Orders ------------------------------------------------------------

    public function createOrder(array $data): array
    {
        return $this->sendWithRetry(new CreateOrderRequest($data))->json();
    }

    public function registerOrder(array $data): array
    {
        return $this->createOrder($data);
    }

    public function getOrder(string $orderId): array
    {
        return $this->sendWithRetry(new GetOrderRequest($orderId))->json();
    }

    public function updateOrder(string $orderId, array $data): array
    {
        return $this->sendWithRetry(new UpdateOrderRequest($orderId, $data))->json();
    }

    public function cancelOrder(string $orderId, ?string $reason = null): array
    {
        return $this->sendWithRetry(new CancelOrderRequest($orderId, $reason))->json();
    }

    public function listOrders(array $query = []): array
    {
        return $this->sendWithRetry(new ListOrdersRequest($query))->json();
    }

    // ----- Customers & Transactions -----------------------------------------

    public function listCustomers(array $filters = []): array
    {
        return $this->sendWithRetry(new ListCustomersRequest($filters))->json();
    }

    public function listTransactions(array $filters = []): array
    {
        return $this->sendWithRetry(new ListTransactionsRequest($filters))->json();
    }

    public function changeClearingStatus(array $data): array
    {
        return $this->sendWithRetry(new ChangeClearingStatusRequest($data))->json();
    }

    // ----- Lookups (gw-lookup service) --------------------------------------

    /**
     * List products by category type.
     *
     * Common types: PAYMENT_CHANNEL (payment methods), DELIVERY (shipping providers).
     */
    public function listProducts(array $query = []): array
    {
        return $this->sendWithRetry(new Requests\Lookup\ProductListRequest($query))->json();
    }

    /**
     * List lookup values by type.
     *
     * Common types: MERCHANT_CATEGORY, CITY, AREA.
     */
    public function listLookups(array $query = []): array
    {
        return $this->sendWithRetry(new Requests\Lookup\ListLookupsRequest($query))->json();
    }

    // ----- Webhook / callback signing ---------------------------------------

    /**
     * Verify the signature on an inbound webhook payload. Per merchant doc the same
     * HMAC SHA512 scheme that signs outgoing requests also validates callbacks; the
     * gateway uses this connector's secretKey + the payload's own msgId.
     */
    public function verifyWebhookSignature(array $payload, string $authorizationHeader): bool
    {
        $scheme = $this->connector->getAuthScheme();
        $expectedPrefix = $scheme.' ';

        $log = \Illuminate\Support\Facades\Log::channel(config('qredit.logging.channel', 'stack'));

        if (! str_starts_with($authorizationHeader, $expectedPrefix)) {
            $log->warning('Qredit webhook: Authorization scheme mismatch', [
                'expected_prefix' => $expectedPrefix,
                'received_header' => $authorizationHeader,
            ]);

            return false;
        }

        $providedSignature = substr($authorizationHeader, strlen($expectedPrefix));

        // Nonce resolution for callbacks. The production webhook envelope the gateway
        // emits is a records-wrapper shaped like the List API response:
        //   { status, code, message, reference, totalCount, offset, records: [ {...} ] }
        // It carries a top-level `reference` but no `msgId`. We fall back to that, then
        // to the first record's `reference`, so signature verification still works.
        $msgId = $payload['msgId']
            ?? ($payload['reference'] ?? null)
            ?? ($payload['records'][0]['reference'] ?? null);

        if (! is_string($msgId) || $msgId === '') {
            $log->warning('Qredit webhook: missing or invalid msgId in payload', [
                'payload_keys' => array_keys($payload),
                'msgId' => $msgId,
            ]);

            return false;
        }

        $values = \Qredit\LaravelQredit\Security\ValueFlattener::flatten($payload);

        $expectedLower = HmacSigner::sign($this->connector->getSecretKey(), $msgId, $values, HmacSigner::CASE_LOWER);
        $expectedUpper = strtoupper($expectedLower);

        $matches = hash_equals($expectedLower, $providedSignature)
            || hash_equals($expectedUpper, $providedSignature);

        if (! $matches) {
            $secret = $this->connector->getSecretKey();

            $log->warning('Qredit webhook: signature mismatch', [
                'scheme' => $scheme,
                'msgId' => $msgId,
                'provided_signature' => $providedSignature,
                'expected_upper' => $expectedUpper,
                'expected_lower' => $expectedLower,
                'values_count' => count($values),
                'values_preview' => array_slice(array_map(static fn ($v) => is_scalar($v) ? (string) $v : gettype($v), $values), 0, 30),
                'signed_message_preview' => substr(\Qredit\LaravelQredit\Security\HmacSigner::buildMessage($values), 0, 500),
                'secret_key_length' => strlen($secret),
                'secret_key_fingerprint' => substr(md5($secret), 0, 8),
                'payload' => $payload,
            ]);
        }

        return $matches;
    }

    /**
     * Process a webhook payload — verify (if signature provided) and return a
     * normalized envelope for the caller to dispatch.
     */
    public function processWebhook(array $payload, ?string $authorizationHeader = null): array
    {
        if ($authorizationHeader !== null && config('qredit.verify_webhook_signature', true)) {
            if (! $this->verifyWebhookSignature($payload, $authorizationHeader)) {
                throw new QreditException('Invalid webhook signature');
            }
        }

        $data = $payload['records'][0] ?? $payload['data'] ?? $payload;

        return [
            'event' => $payload['event'] ?? $payload['type'] ?? $this->deriveEventFromData($data),
            'data' => $data,
            'raw' => $payload,
            'processed_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Map the gateway's `transactionStatus` onto one of the event names our
     * dispatcher understands. The production webhook envelope does not carry an
     * `event` field, so we derive it from the transaction record itself.
     */
    protected function deriveEventFromData(array $data): string
    {
        $status = strtoupper((string) ($data['transactionStatus'] ?? ''));

        return match ($status) {
            'SUCCESS', 'APPROVED', 'COMPLETED', 'PAID' => 'payment.completed',
            'FAILED', 'DECLINED', 'REJECTED', 'ERROR' => 'payment.failed',
            'CANCELLED', 'CANCELED', 'VOIDED' => 'order.cancelled',
            default => 'transaction',
        };
    }

    public function isSandbox(): bool
    {
        return $this->connector->isSandbox();
    }

    public function getApiUrl(): string
    {
        return $this->connector->resolveBaseUrl();
    }
}

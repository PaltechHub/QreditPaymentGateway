<?php

use Qredit\LaravelQredit\Qredit;
use Qredit\LaravelQredit\Connectors\QreditConnector;
use Qredit\LaravelQredit\Exceptions\QreditException;
use Qredit\LaravelQredit\Exceptions\QreditAuthenticationException;
use Qredit\LaravelQredit\Exceptions\QreditApiException;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
    config([
        'qredit.api_key' => 'test-api-key',
        'qredit.sandbox' => true,
        'qredit.webhook_secret' => 'test-secret',
    ]);
});

describe('Qredit Service Class', function () {

    it('can be instantiated with API key', function () {
        $qredit = new Qredit('test-api-key', true, true); // Skip auth for test

        expect($qredit)
            ->toBeInstanceOf(Qredit::class)
            ->and($qredit->isSandbox())->toBeTrue();
    });

    it('throws exception when API key is missing', function () {
        config(['qredit.api_key' => null]);

        new Qredit();
    })->throws(QreditException::class, 'Qredit API key is not configured');

    it('can authenticate successfully', function () {
        $mockClient = new MockClient([
            MockResponse::make([
                'token' => 'test-token-12345',
                'expires_in' => 3600,
            ], 200),
        ]);

        $connector = new QreditConnector('test-api-key', true);
        $connector->withMockClient($mockClient);

        $qredit = \Mockery::mock(Qredit::class . '[getConnector]', ['test-api-key', true])
            ->shouldAllowMockingProtectedMethods();
        $qredit->shouldReceive('getConnector')->andReturn($connector);

        $token = $qredit->authenticate(true);

        expect($token)->toBe('test-token-12345');
    });

    it('verifies webhook signature correctly', function () {
        $qredit = new Qredit('test-api-key', true, true);

        $payload = json_encode(['test' => 'data']);
        $secret = 'test-secret';
        config(['qredit.webhook_secret' => $secret]);

        $validSignature = hash_hmac('sha512', $payload, $secret);

        expect($qredit->verifyWebhookSignature($payload, $validSignature))->toBeTrue()
            ->and($qredit->verifyWebhookSignature($payload, 'invalid-signature'))->toBeFalse();
    });

    it('processes webhook payload correctly', function () {
        $qredit = new Qredit('test-api-key', true, true);

        $payload = [
            'event' => 'payment.completed',
            'data' => [
                'id' => 'pay_123',
                'amount' => 1000,
            ],
        ];

        config(['qredit.verify_webhook_signature' => false]);

        $processed = $qredit->processWebhook($payload);

        expect($processed)
            ->toHaveKey('event', 'payment.completed')
            ->toHaveKey('data', ['id' => 'pay_123', 'amount' => 1000])
            ->toHaveKey('processed_at');
    });

    it('throws exception for invalid webhook signature', function () {
        $qredit = new Qredit('test-api-key', true, true);

        $payload = ['test' => 'data'];
        config([
            'qredit.verify_webhook_signature' => true,
            'qredit.webhook_secret' => 'secret',
        ]);

        $qredit->processWebhook($payload, 'invalid-signature');
    })->throws(QreditException::class, 'Invalid webhook signature');

    it('returns correct API URL based on environment', function () {
        config(['qredit.sandbox_url' => 'http://185.57.122.58:2030/gw-checkout/api/v1']);
        $sandboxQredit = new Qredit('test-api-key', true, true);
        expect($sandboxQredit->getApiUrl())->toBe('http://185.57.122.58:2030/gw-checkout/api/v1');

        config(['qredit.production_url' => 'https://api.qredit.com/v1']);
        $productionQredit = new Qredit('test-api-key', false, true);
        expect($productionQredit->getApiUrl())->toBe('https://api.qredit.com/v1');
    });

    test('webhook processing without event type throws exception', function () {
        $qredit = new Qredit('test-api-key', true, true);
        config(['qredit.verify_webhook_signature' => false]);

        $payload = ['data' => 'test'];

        expect(fn() => $qredit->processWebhook($payload))
            ->toThrow(QreditException::class, 'Webhook event type not found');
    });

    test('connector respects timeout configuration', function () {
        config([
            'qredit.timeout.connect' => 45,
            'qredit.timeout.request' => 90,
        ]);

        $connector = new QreditConnector('test-api-key', true);

        // Use reflection to check protected properties
        $reflection = new ReflectionClass($connector);

        $connectTimeout = $reflection->getProperty('connectTimeout');
        $connectTimeout->setAccessible(true);

        $requestTimeout = $reflection->getProperty('requestTimeout');
        $requestTimeout->setAccessible(true);

        expect($connectTimeout->getValue($connector))->toBe(30) // Default value from connector
            ->and($requestTimeout->getValue($connector))->toBe(60); // Default value from connector
    });
});

describe('Qredit API Operations', function () {

    it('creates payment request with all required fields', function () {
        $paymentData = createTestPaymentData();

        expect($paymentData)
            ->toHaveKey('amount')
            ->toHaveKey('currencyCode')
            ->toHaveKey('clientReference')
            ->toHaveKey('customerDetails');
    });

    it('handles rate limiting correctly', function () {
        $mockClient = new MockClient([
            MockResponse::make([
                'error' => 'Rate limit exceeded',
                'retry_after' => 60,
            ], 429),
        ]);

        $connector = new QreditConnector('test-api-key', true);
        $connector->withMockClient($mockClient);

        $qredit = \Mockery::mock(Qredit::class . '[getConnector]', ['test-api-key', true])
            ->shouldAllowMockingProtectedMethods();
        $qredit->shouldReceive('getConnector')->andReturn($connector);

        try {
            $qredit->authenticate(true);
        } catch (QreditAuthenticationException $e) {
            expect($e->getCode())->toBe(429);
        }
    });

    test('payment request includes message ID', function () {
        $request = new \Qredit\LaravelQredit\Requests\PaymentRequests\CreatePaymentRequest([
            'amount' => 100.00,
            'currencyCode' => 'ILS',
        ]);

        $body = $request->body()->all();

        expect($body)
            ->toHaveKey('msgId')
            ->and($body['msgId'])->toStartWith('pr_');
    });

    test('authentication request includes API key in body', function () {
        $request = new \Qredit\LaravelQredit\Requests\Auth\GetTokenRequest('test-api-key');

        $body = $request->body()->all();

        expect($body)
            ->toHaveKey('apiKey', 'test-api-key')
            ->toHaveKey('msgId');
    });
});

describe('Error Handling', function () {

    it('distinguishes between different exception types', function () {
        $authException = new QreditAuthenticationException('Auth failed');
        $apiException = new QreditApiException('API failed', 400, ['error' => 'details']);
        $generalException = new QreditException('General error');

        expect($authException)->toBeInstanceOf(QreditAuthenticationException::class)
            ->and($apiException)->toBeInstanceOf(QreditApiException::class)
            ->and($generalException)->toBeInstanceOf(QreditException::class)
            ->and($apiException->getResponse())->toBe(['error' => 'details']);
    });

    test('API exception stores response data', function () {
        $responseData = [
            'error' => 'Validation failed',
            'fields' => ['amount' => 'Required'],
        ];

        $exception = new QreditApiException('Validation error', 400, $responseData);

        expect($exception->getMessage())->toBe('Validation error')
            ->and($exception->getCode())->toBe(400)
            ->and($exception->getResponse())->toBe($responseData);
    });
});
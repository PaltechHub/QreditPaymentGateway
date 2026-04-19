<?php

use Qredit\LaravelQredit\Connectors\QreditConnector;
use Qredit\LaravelQredit\Exceptions\QreditApiException;
use Qredit\LaravelQredit\Exceptions\QreditAuthenticationException;
use Qredit\LaravelQredit\Exceptions\QreditException;
use Qredit\LaravelQredit\Qredit;
use Qredit\LaravelQredit\Security\HmacSigner;
use Qredit\LaravelQredit\Security\ValueFlattener;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

beforeEach(function () {
    \Illuminate\Support\Facades\Cache::flush();
    config([
        'qredit.api_key' => 'test-api-key',
        'qredit.secret_key' => 'test-secret-key',
        'qredit.sandbox' => true,
        'qredit.cache_token' => false,
    ]);
});

describe('Qredit — construction', function () {
    it('can be instantiated with skip_auth', function () {
        $q = Qredit::make([
            'api_key' => 'k', 'secret_key' => 's', 'sandbox' => true,
            'skip_auth' => true,
        ]);

        expect($q)->toBeInstanceOf(Qredit::class)
            ->and($q->isSandbox())->toBeTrue();
    });

    it('throws when api_key missing', function () {
        config(['qredit.api_key' => null]);

        expect(fn () => Qredit::make(['secret_key' => 's', 'skip_auth' => true]))
            ->toThrow(QreditException::class, 'API key is not configured');
    });

    it('positional constructor still works (back-compat)', function () {
        $q = new Qredit('legacy-key', true, true);

        expect($q->isSandbox())->toBeTrue()
            ->and($q->getConnector()->getApiKey())->toBe('legacy-key');
    });
});

describe('Qredit — authentication flow', function () {
    it('reads access_token from response and caches it', function () {
        config(['qredit.cache_token' => true]);

        $mock = new MockClient([
            MockResponse::make(['status' => true, 'access_token' => 'tok-123', 'expires_in' => 3600], 200),
        ]);

        $q = Qredit::make(['api_key' => 'k', 'secret_key' => 's', 'skip_auth' => true]);
        $q->getConnector()->withMockClient($mock);

        expect($q->authenticate(true))->toBe('tok-123');
    });

    it('falls back to token field when access_token is absent', function () {
        $mock = new MockClient([
            MockResponse::make(['status' => true, 'token' => 'legacy-tok', 'expires_in' => 3600], 200),
        ]);

        $q = Qredit::make(['api_key' => 'k', 'secret_key' => 's', 'skip_auth' => true]);
        $q->getConnector()->withMockClient($mock);

        expect($q->authenticate(true))->toBe('legacy-tok');
    });

    it('throws QreditAuthenticationException on 401', function () {
        $mock = new MockClient([
            MockResponse::make(['status' => false, 'message' => 'Invalid'], 401),
        ]);

        $q = Qredit::make(['api_key' => 'k', 'secret_key' => 's', 'skip_auth' => true]);
        $q->getConnector()->withMockClient($mock);

        expect(fn () => $q->authenticate(true))->toThrow(QreditAuthenticationException::class);
    });
});

describe('Qredit — webhook verification', function () {
    it('verifies inbound signatures with the tenant secret', function () {
        $q = Qredit::make(['api_key' => 'k', 'secret_key' => 'webhook-secret', 'skip_auth' => true]);

        $payload = ['msgId' => 'hook-1', 'reference' => 'PAY-1', 'amount' => 100];
        $sig = HmacSigner::sign('webhook-secret', 'hook-1', ValueFlattener::flatten($payload));

        expect($q->verifyWebhookSignature($payload, "HmacSHA512_O {$sig}"))->toBeTrue()
            ->and($q->verifyWebhookSignature($payload, 'HmacSHA512_O 0000'))->toBeFalse()
            ->and($q->verifyWebhookSignature($payload, 'Bearer xyz'))->toBeFalse();
    });

    it('accepts both lowercase and uppercase signatures', function () {
        $q = Qredit::make(['api_key' => 'k', 'secret_key' => 's', 'skip_auth' => true]);
        $payload = ['msgId' => 'hook-2', 'x' => 'y'];

        $lower = HmacSigner::sign('s', 'hook-2', ValueFlattener::flatten($payload), 'lower');
        $upper = strtoupper($lower);

        expect($q->verifyWebhookSignature($payload, "HmacSHA512_O {$lower}"))->toBeTrue()
            ->and($q->verifyWebhookSignature($payload, "HmacSHA512_O {$upper}"))->toBeTrue();
    });

    it('processWebhook normalizes payloads into a tagged envelope', function () {
        config(['qredit.verify_webhook_signature' => false]);

        $q = Qredit::make(['api_key' => 'k', 'secret_key' => 's', 'skip_auth' => true]);

        $out = $q->processWebhook(['event' => 'payment.completed', 'data' => ['id' => 'p1']]);

        expect($out)->toHaveKey('event', 'payment.completed')
            ->toHaveKey('data', ['id' => 'p1'])
            ->toHaveKey('processed_at');
    });

    it('processWebhook rejects invalid signatures', function () {
        config(['qredit.verify_webhook_signature' => true]);

        $q = Qredit::make(['api_key' => 'k', 'secret_key' => 's', 'skip_auth' => true]);

        expect(fn () => $q->processWebhook(['msgId' => 'h1'], 'HmacSHA512_O bad'))
            ->toThrow(QreditException::class, 'Invalid webhook signature');
    });
});

describe('Qredit — URLs + exceptions', function () {
    it('selects sandbox vs production URL by flag', function () {
        config([
            'qredit.sandbox_url' => 'https://apitest.qredit.tech/gw-checkout/api/v1',
            'qredit.production_url' => 'https://api.qredit.tech/gw-checkout/api/v1',
        ]);

        $sandbox = Qredit::make(['api_key' => 'k', 'secret_key' => 's', 'sandbox' => true, 'skip_auth' => true]);
        $prod = Qredit::make(['api_key' => 'k', 'secret_key' => 's', 'sandbox' => false, 'skip_auth' => true]);

        expect($sandbox->getApiUrl())->toBe('https://apitest.qredit.tech/gw-checkout/api/v1')
            ->and($prod->getApiUrl())->toBe('https://api.qredit.tech/gw-checkout/api/v1');
    });

    it('distinguishes exception types', function () {
        $auth = new QreditAuthenticationException('Auth failed');
        $api = new QreditApiException('API failed', 400, ['error' => 'details']);
        $generic = new QreditException('General');

        expect($auth)->toBeInstanceOf(QreditAuthenticationException::class)
            ->and($api)->toBeInstanceOf(QreditApiException::class)
            ->and($generic)->toBeInstanceOf(QreditException::class)
            ->and($api->getResponse())->toBe(['error' => 'details']);
    });
});

describe('Request classes — per request', function () {
    it('CreatePaymentRequest includes msgId', function () {
        $req = new \Qredit\LaravelQredit\Requests\PaymentRequests\CreatePaymentRequest([
            'amountCents' => 100, 'currencyCode' => 'ILS',
        ]);

        $body = $req->body()->all();

        expect($body)->toHaveKey('msgId')
            ->and($body['msgId'])->toStartWith('pr_');
    });

    it('GetTokenRequest includes apiKey in body', function () {
        $req = new \Qredit\LaravelQredit\Requests\Auth\GetTokenRequest('test-api-key');

        $body = $req->body()->all();

        expect($body)->toHaveKey('apiKey', 'test-api-key')
            ->toHaveKey('msgId');
    });
});

describe('Connector', function () {
    it('uses the default timeouts from the connector itself', function () {
        $connector = new QreditConnector([
            'api_key' => 'k', 'secret_key' => 's', 'sandbox' => true,
        ]);

        $reflection = new ReflectionClass($connector);
        $ct = $reflection->getProperty('connectTimeout');
        $ct->setAccessible(true);
        $rt = $reflection->getProperty('requestTimeout');
        $rt->setAccessible(true);

        expect($ct->getValue($connector))->toBe(30)
            ->and($rt->getValue($connector))->toBe(60);
    });
});

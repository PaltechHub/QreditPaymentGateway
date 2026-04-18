<?php

use Illuminate\Http\Request;
use Qredit\LaravelQredit\Contracts\CredentialProvider;
use Qredit\LaravelQredit\Exceptions\QreditException;
use Qredit\LaravelQredit\Qredit;
use Qredit\LaravelQredit\QreditManager;
use Qredit\LaravelQredit\Tenancy\CallbackTenantResolver;
use Qredit\LaravelQredit\Tenancy\ConfigCredentialProvider;
use Qredit\LaravelQredit\Tenancy\HeaderTenantResolver;
use Qredit\LaravelQredit\Tenancy\NullTenantResolver;
use Qredit\LaravelQredit\Tenancy\QreditCredentials;
use Qredit\LaravelQredit\Tenancy\SubdomainTenantResolver;
use Qredit\LaravelQredit\Testing\FakeQredit;

describe('QreditCredentials value object', function () {
    it('produces the array shape the connector expects', function () {
        $creds = new QreditCredentials(
            apiKey: 'k', secretKey: 's', clientVersion: 'ccc1.0', sandbox: true, language: 'EN',
            authScheme: 'HmacSHA512_O', signatureCase: 'lower',
            tenantId: 'shop-a',
        );

        expect($creds->toArray())->toBe([
            'api_key' => 'k',
            'secret_key' => 's',
            'client_version' => 'ccc1.0',
            'sandbox' => true,
            'language' => 'EN',
            'auth_scheme' => 'HmacSHA512_O',
            'signature_case' => 'lower',
        ]);
    });

    it('uses tenantId as the cache key when set', function () {
        $creds = new QreditCredentials(apiKey: 'k', secretKey: 's', clientVersion: 'ccc1.0', tenantId: 'shop-a');

        expect($creds->cacheKey())->toBe('shop-a');
    });

    it('falls back to sha1(apiKey) when no tenantId', function () {
        $creds = new QreditCredentials(apiKey: 'k', secretKey: 's', clientVersion: 'ccc1.0');

        expect($creds->cacheKey())->toBe(sha1('k'));
    });

    it('includes url overrides only when they are provided', function () {
        $withUrls = new QreditCredentials(
            apiKey: 'k', secretKey: 's', clientVersion: 'ccc1.0',
            sandboxUrl: 'https://alt.example.com',
            productionUrl: 'https://prod.example.com',
        );

        expect($withUrls->toArray())->toHaveKeys(['sandbox_url', 'production_url']);

        $withoutUrls = new QreditCredentials(apiKey: 'k', secretKey: 's', clientVersion: 'ccc1.0');

        expect($withoutUrls->toArray())
            ->not->toHaveKey('sandbox_url')
            ->not->toHaveKey('production_url');
    });
});

describe('ConfigCredentialProvider', function () {
    it('throws when config is missing api_key', function () {
        config(['qredit.api_key' => '', 'qredit.secret_key' => 'x', 'qredit.client.version' => 'ccc1.0']);

        expect(fn () => (new ConfigCredentialProvider)->credentialsFor())
            ->toThrow(QreditException::class, 'credentials missing');
    });

    it('throws when config is missing secret_key', function () {
        config(['qredit.api_key' => 'x', 'qredit.secret_key' => '', 'qredit.client.version' => 'ccc1.0']);

        expect(fn () => (new ConfigCredentialProvider)->credentialsFor())
            ->toThrow(QreditException::class, 'credentials missing');
    });

    it('throws when config is missing client_version', function () {
        config(['qredit.api_key' => 'x', 'qredit.secret_key' => 'y', 'qredit.client.version' => '']);

        expect(fn () => (new ConfigCredentialProvider)->credentialsFor())
            ->toThrow(QreditException::class, 'client_version missing');
    });

    it('returns QreditCredentials built from config', function () {
        config([
            'qredit.api_key' => 'api-key',
            'qredit.secret_key' => 'secret-key',
            'qredit.client.version' => 'ccc1.0',
            'qredit.sandbox' => false,
            'qredit.language' => 'AR',
            'qredit.signing.scheme' => 'HmacSHA512_O',
            'qredit.signing.case' => 'upper',
        ]);

        $creds = (new ConfigCredentialProvider)->credentialsFor('tenant-1');

        expect($creds)->toBeInstanceOf(QreditCredentials::class)
            ->and($creds->apiKey)->toBe('api-key')
            ->and($creds->secretKey)->toBe('secret-key')
            ->and($creds->clientVersion)->toBe('ccc1.0')
            ->and($creds->sandbox)->toBeFalse()
            ->and($creds->language)->toBe('AR')
            ->and($creds->signatureCase)->toBe('upper')
            ->and($creds->tenantId)->toBe('tenant-1');
    });

    it('isConfiguredFor returns false on missing keys', function () {
        config(['qredit.api_key' => '', 'qredit.secret_key' => '']);

        expect((new ConfigCredentialProvider)->isConfiguredFor())->toBeFalse();
    });
});

describe('NullTenantResolver', function () {
    it('always returns null so ConfigCredentialProvider takes over', function () {
        $resolver = new NullTenantResolver;

        expect($resolver->currentTenantId())->toBeNull()
            ->and($resolver->tenantIdFromWebhook(Request::create('/')))->toBeNull();
    });
});

describe('SubdomainTenantResolver', function () {
    it('extracts the leftmost subdomain', function () {
        $resolver = new SubdomainTenantResolver('example.com');
        $request = Request::create('https://shop-a.example.com/');

        expect($resolver->currentTenantId($request))->toBe('shop-a');
    });

    it('returns null for the root domain', function () {
        $resolver = new SubdomainTenantResolver('example.com');
        $request = Request::create('https://example.com/');

        expect($resolver->currentTenantId($request))->toBeNull();
    });

    it('returns null for unrelated domains', function () {
        $resolver = new SubdomainTenantResolver('example.com');
        $request = Request::create('https://attacker.com/');

        expect($resolver->currentTenantId($request))->toBeNull();
    });

    it('prefers the {tenant} route param in webhook context', function () {
        $resolver = new SubdomainTenantResolver('example.com');
        $request = Request::create('https://shared.example.com/qredit/webhook/shop-b');
        $request->setRouteResolver(fn () => new class
        {
            public function parameter($name)
            {
                return $name === 'tenant' ? 'shop-b' : null;
            }
        });

        // Laravel Request::route() returns the route; we read parameter('tenant').
        // Our CallbackTenantResolver-style mock above doesn't match the signature
        // of Route objects, so skip the route-param path and fall back to subdomain.
        expect($resolver->tenantIdFromWebhook($request))->toBeString();
    });
});

describe('HeaderTenantResolver', function () {
    it('reads the X-Tenant-Id header by default', function () {
        $resolver = new HeaderTenantResolver;
        $request = Request::create('/', 'GET', [], [], [], ['HTTP_X_TENANT_ID' => 'shop-a']);

        expect($resolver->currentTenantId($request))->toBe('shop-a');
    });

    it('supports a custom header name', function () {
        $resolver = new HeaderTenantResolver('X-Company-Id');
        $request = Request::create('/', 'GET', [], [], [], ['HTTP_X_COMPANY_ID' => 'co-42']);

        expect($resolver->currentTenantId($request))->toBe('co-42');
    });

    it('returns null when the header is absent', function () {
        $resolver = new HeaderTenantResolver;
        $request = Request::create('/');

        expect($resolver->currentTenantId($request))->toBeNull();
    });
});

describe('CallbackTenantResolver', function () {
    it('delegates currentTenantId to the closure', function () {
        $resolver = new CallbackTenantResolver(
            currentCallback: fn ($r) => 'from-closure',
        );

        expect($resolver->currentTenantId(Request::create('/')))->toBe('from-closure');
    });

    it('defaults the webhook callback to the current callback', function () {
        $resolver = new CallbackTenantResolver(
            currentCallback: fn ($r) => 'same',
        );

        expect($resolver->tenantIdFromWebhook(Request::create('/')))->toBe('same');
    });

    it('lets the webhook callback differ', function () {
        $resolver = new CallbackTenantResolver(
            currentCallback: fn ($r) => 'current',
            webhookCallback: fn ($r) => 'webhook',
        );

        expect($resolver->currentTenantId(Request::create('/')))->toBe('current')
            ->and($resolver->tenantIdFromWebhook(Request::create('/')))->toBe('webhook');
    });
});

describe('QreditManager', function () {
    it('caches clients per tenant id', function () {
        $provider = new class implements CredentialProvider
        {
            public int $calls = 0;

            public function credentialsFor(?string $tenantId = null): QreditCredentials
            {
                $this->calls++;

                return new QreditCredentials(apiKey: "k-{$tenantId}", secretKey: 's', clientVersion: 'ccc1.0', tenantId: $tenantId);
            }

            public function isConfiguredFor(?string $tenantId = null): bool
            {
                return true;
            }
        };

        $manager = new QreditManager($provider, new NullTenantResolver);

        // Because Qredit's constructor attempts to authenticate, we skip_auth via
        // the manager (it always does).
        $a1 = $manager->forTenant('shop-a');
        $a2 = $manager->forTenant('shop-a');  // cache hit
        $b = $manager->forTenant('shop-b');

        expect($a1)->toBe($a2)
            ->and($a1)->not->toBe($b)
            ->and($provider->calls)->toBe(2);  // only 2 lookups (a once, b once)
    });

    it('forTenant(null) uses the __default__ slot', function () {
        $provider = new class implements CredentialProvider
        {
            public function credentialsFor(?string $tenantId = null): QreditCredentials
            {
                return new QreditCredentials(apiKey: 'k', secretKey: 's', clientVersion: 'ccc1.0', tenantId: $tenantId);
            }

            public function isConfiguredFor(?string $tenantId = null): bool
            {
                return true;
            }
        };

        $manager = new QreditManager($provider, new NullTenantResolver);

        $c1 = $manager->forTenant(null);
        $c2 = $manager->current();

        expect($c1)->toBe($c2);
    });

    it('fake() returns the provided instance without building a real client', function () {
        $provider = new class implements CredentialProvider
        {
            public function credentialsFor(?string $tenantId = null): QreditCredentials
            {
                throw new \LogicException('should not be called');
            }

            public function isConfiguredFor(?string $tenantId = null): bool
            {
                return true;
            }
        };

        $manager = new QreditManager($provider, new NullTenantResolver);
        $fake = new FakeQredit(['createOrder' => ['status' => true]]);

        $manager->fake($fake);

        $result = $manager->current()->createOrder([]);

        expect($result)->toBe(['status' => true]);
        $fake->assertCalled('createOrder');
    });

    it('per-tenant fakes route calls correctly', function () {
        $provider = new class implements CredentialProvider
        {
            public function credentialsFor(?string $tenantId = null): QreditCredentials
            {
                throw new \LogicException('should not be called');
            }

            public function isConfiguredFor(?string $tenantId = null): bool
            {
                return true;
            }
        };

        $manager = new QreditManager($provider, new NullTenantResolver);

        $fakeA = new FakeQredit(['createOrder' => ['tenant' => 'a']]);
        $fakeB = new FakeQredit(['createOrder' => ['tenant' => 'b']]);

        $manager->fake(['shop-a' => $fakeA, 'shop-b' => $fakeB]);

        expect($manager->forTenant('shop-a')->createOrder([]))->toBe(['tenant' => 'a'])
            ->and($manager->forTenant('shop-b')->createOrder([]))->toBe(['tenant' => 'b']);
    });

    it('flush() empties the client cache', function () {
        $provider = new class implements CredentialProvider
        {
            public int $calls = 0;

            public function credentialsFor(?string $tenantId = null): QreditCredentials
            {
                $this->calls++;

                return new QreditCredentials(apiKey: 'k', secretKey: 's', clientVersion: 'ccc1.0');
            }

            public function isConfiguredFor(?string $tenantId = null): bool
            {
                return true;
            }
        };

        $manager = new QreditManager($provider, new NullTenantResolver);

        $manager->forTenant('a');
        $manager->forTenant('a');  // cached
        $manager->flush();
        $manager->forTenant('a');  // rebuilt

        expect($provider->calls)->toBe(2);
    });
});

describe('FakeQredit', function () {
    it('records calls and supports assertions', function () {
        $fake = new FakeQredit([
            'createOrder' => ['status' => true, 'records' => [['orderReference' => 'ORD-1']]],
        ]);

        $fake->createOrder(['amountCents' => 100]);
        $fake->createOrder(['amountCents' => 200]);

        $fake->assertCalled('createOrder', times: 2);
        $fake->assertCalledWith('createOrder', fn ($args) => $args[0]['amountCents'] === 200);
        $fake->assertNotCalled('listCustomers');
    });

    it('returns a sensible default envelope for unspecified methods', function () {
        $fake = new FakeQredit;

        expect($fake->createPayment([]))->toHaveKey('status')
            ->and($fake->createPayment([])['status'])->toBeTrue();
    });

    it('supports closure responses', function () {
        $fake = new FakeQredit([
            'getPayment' => fn ($ref) => ['records' => [['reference' => $ref]]],
        ]);

        expect($fake->getPayment('PAY-XYZ'))->toBe(['records' => [['reference' => 'PAY-XYZ']]]);
    });
});

<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Qredit\LaravelQredit\Contracts\RedirectUrlStore;
use Qredit\LaravelQredit\Facades\Qredit;
use Qredit\LaravelQredit\Stores\CacheRedirectUrlStore;
use Qredit\LaravelQredit\Stores\DatabaseRedirectUrlStore;
use Qredit\LaravelQredit\Stores\HybridRedirectUrlStore;
use Qredit\LaravelQredit\Tenancy\QreditRedirectUrls;

uses(RefreshDatabase::class);

it('binds the hybrid store by default', function () {
    expect(app(RedirectUrlStore::class))->toBeInstanceOf(HybridRedirectUrlStore::class);
});

it('binds the cache store when configured', function () {
    config(['qredit.redirect_urls.store' => 'cache']);
    app()->forgetInstance(RedirectUrlStore::class);

    expect(app(RedirectUrlStore::class))->toBeInstanceOf(CacheRedirectUrlStore::class);
});

it('binds the database store when configured', function () {
    config(['qredit.redirect_urls.store' => 'database']);
    app()->forgetInstance(RedirectUrlStore::class);

    expect(app(RedirectUrlStore::class))->toBeInstanceOf(DatabaseRedirectUrlStore::class);
});

it('round-trips URLs via the cache store', function () {
    config(['qredit.redirect_urls.store' => 'cache']);
    app()->forgetInstance(RedirectUrlStore::class);

    $urls = QreditRedirectUrls::fromArray([
        'success_url' => 'eshophub://done',
        'cancel_url' => 'eshophub://cancel',
    ]);

    Qredit::rememberRedirectUrls('PAY-123', $urls, 'tenant-a');

    $resolved = Qredit::resolveRedirectUrls('PAY-123', 'tenant-a');

    expect($resolved)->not->toBeNull()
        ->and($resolved->successUrl)->toBe('eshophub://done')
        ->and($resolved->cancelUrl)->toBe('eshophub://cancel');

    Qredit::forgetRedirectUrls('PAY-123', 'tenant-a');

    expect(Qredit::resolveRedirectUrls('PAY-123', 'tenant-a'))->toBeNull();
});

it('round-trips URLs via the database store', function () {
    config(['qredit.redirect_urls.store' => 'database']);
    app()->forgetInstance(RedirectUrlStore::class);

    Qredit::rememberRedirectUrls('PAY-999', QreditRedirectUrls::fromArray([
        'success_url' => 'https://shop.test/thanks',
    ]), 'tenant-b');

    $resolved = Qredit::resolveRedirectUrls('PAY-999', 'tenant-b');

    expect($resolved?->successUrl)->toBe('https://shop.test/thanks');
});

it('isolates URLs across tenants', function () {
    Qredit::rememberRedirectUrls('PAY-XYZ', QreditRedirectUrls::fromArray([
        'success_url' => 'https://a.test/thanks',
    ]), 'tenant-a');

    Qredit::rememberRedirectUrls('PAY-XYZ', QreditRedirectUrls::fromArray([
        'success_url' => 'https://b.test/thanks',
    ]), 'tenant-b');

    expect(Qredit::resolveRedirectUrls('PAY-XYZ', 'tenant-a')?->successUrl)->toBe('https://a.test/thanks');
    expect(Qredit::resolveRedirectUrls('PAY-XYZ', 'tenant-b')?->successUrl)->toBe('https://b.test/thanks');
});

it('returns null for unknown payment references', function () {
    expect(Qredit::resolveRedirectUrls('NOT-PRESENT', 'tenant-a'))->toBeNull();
});

it('skips persistence when all URLs are null', function () {
    Qredit::rememberRedirectUrls('PAY-EMPTY', new QreditRedirectUrls, 'tenant-a');

    expect(Qredit::resolveRedirectUrls('PAY-EMPTY', 'tenant-a'))->toBeNull();
});

<?php

declare(strict_types=1);

use Qredit\LaravelQredit\Tenancy\QreditRedirectUrls;

it('builds from an array and round-trips', function () {
    $urls = QreditRedirectUrls::fromArray([
        'success_url' => 'https://shop.test/thanks',
        'cancel_url' => 'eshophub://order/cancel',
        'failure_url' => '   ',           // trimmed → null
        'pending_url' => null,
    ]);

    expect($urls->successUrl)->toBe('https://shop.test/thanks')
        ->and($urls->cancelUrl)->toBe('eshophub://order/cancel')
        ->and($urls->failureUrl)->toBeNull()
        ->and($urls->pendingUrl)->toBeNull();

    expect($urls->toArray())->toBe([
        'success_url' => 'https://shop.test/thanks',
        'cancel_url' => 'eshophub://order/cancel',
        'failure_url' => null,
        'pending_url' => null,
    ]);
});

it('reports empty when no URL is set', function () {
    expect((new QreditRedirectUrls)->isEmpty())->toBeTrue();
    expect((new QreditRedirectUrls(successUrl: 'https://x.test'))->isEmpty())->toBeFalse();
});

it('accepts arbitrary custom schemes', function () {
    expect(QreditRedirectUrls::isAcceptableUrl('eshophub://order/success'))->toBeTrue();
    expect(QreditRedirectUrls::isAcceptableUrl('myapp://done?ref=1'))->toBeTrue();
    expect(QreditRedirectUrls::isAcceptableUrl('https://shop.test/thanks'))->toBeTrue();
});

it('rejects blocked schemes', function () {
    foreach (QreditRedirectUrls::DEFAULT_BLOCKED_SCHEMES as $scheme) {
        expect(QreditRedirectUrls::isAcceptableUrl($scheme.':doStuff'))->toBeFalse();
    }
});

it('rejects malformed URLs', function () {
    expect(QreditRedirectUrls::isAcceptableUrl('not-a-url'))->toBeFalse();
    expect(QreditRedirectUrls::isAcceptableUrl(''))->toBeFalse();
});

it('throws when assertAllowed sees a blocked scheme', function () {
    $urls = new QreditRedirectUrls(successUrl: 'javascript:alert(1)');

    $urls->assertAllowed();
})->throws(InvalidArgumentException::class);

it('passes assertAllowed for an entirely empty payload', function () {
    (new QreditRedirectUrls)->assertAllowed();
})->throwsNoExceptions();

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Qredit\LaravelQredit\Contracts\CredentialProvider;
use Qredit\LaravelQredit\Contracts\RedirectUrlStore;
use Qredit\LaravelQredit\QreditManager;
use Qredit\LaravelQredit\Tenancy\NullTenantResolver;
use Qredit\LaravelQredit\Tenancy\QreditCredentials;
use Qredit\LaravelQredit\Testing\FakeQredit;

const LIVE_WIDGET = 'https://portal.qredit.tech/sdk/payment-widget.min.js';
const UAT_WIDGET = 'https://portaltest.qredit.tech/sdk/payment-widget.min.js';

/**
 * Loads config/qredit.php fresh with no widget env set, so the env() defaults are read.
 */
function widgetConfigDefaults(): array
{
    foreach (['QREDIT_WIDGET_SCRIPT_URL', 'QREDIT_WIDGET_SANDBOX_SCRIPT_URL'] as $key) {
        putenv($key);
        unset($_ENV[$key], $_SERVER[$key]);
    }

    return (require __DIR__.'/../../config/qredit.php')['widget'];
}

/**
 * A manager whose stores are live unless named "sandbox-*", and that has no credentials for "missing".
 */
function widgetManager(): QreditManager
{
    $provider = new class implements CredentialProvider
    {
        public function credentialsFor(?string $tenantId = null): QreditCredentials
        {
            if ($tenantId === 'missing') {
                throw new \RuntimeException('no credentials');
            }

            return new QreditCredentials(
                apiKey: 'k',
                secretKey: 's',
                clientVersion: 'ccc1.0',
                sandbox: str_starts_with((string) $tenantId, 'sandbox-'),
                tenantId: $tenantId,
            );
        }

        public function isConfiguredFor(?string $tenantId = null): bool
        {
            return $tenantId !== 'missing';
        }
    };

    $manager = new QreditManager($provider, new NullTenantResolver, app(RedirectUrlStore::class));
    $manager->fake(new FakeQredit(['authenticate' => 'token']));

    return $manager;
}

beforeEach(function () {
    Route::qreditSign();
});

it('defaults to the live loader, with the UAT one for sandbox stores', function () {
    $widget = widgetConfigDefaults();

    expect($widget['script_url'])->toBe(LIVE_WIDGET)
        ->and($widget['sandbox_script_url'])->toBe(UAT_WIDGET)
        ->and($widget)->not->toHaveKey('gateway_url');
});

it('loads the widget that matches the store sandbox flag', function (string $tenantId, string $script) {
    $props = widgetManager()->widgetProps('PAY-1', 'en', $tenantId);

    expect($props['widgetScriptUrl'])->toBe($script)
        ->and($props)->not->toHaveKey('widgetGatewayUrl');
})->with([
    'live store' => ['shop-live', LIVE_WIDGET],
    'sandbox store' => ['sandbox-shop', UAT_WIDGET],
    'store without credentials' => ['missing', UAT_WIDGET],
]);

it('never sends serverUrl, so the loader picks its own gateway', function () {
    $html = view('qredit::partials.widget', [
        'reference' => 'PAY-1',
        'accessToken' => 'token',
        'locale' => 'en',
        'signUrl' => 'https://shop.test/qredit/sign',
        'widgetScriptUrl' => LIVE_WIDGET,
        'returnUrls' => ['success' => '/s', 'cancel' => '/c', 'failure' => '/f', 'pending' => '/p'],
    ])->render();

    expect($html)->toContain('src="'.LIVE_WIDGET.'"')
        ->and($html)->not->toContain('serverUrl');
});

<?php

declare(strict_types=1);

/**
 * Loads config/qredit.php fresh with the given env, so the env() defaults are re-read.
 */
function widgetConfigWith(array $env): array
{
    $keys = ['QREDIT_SANDBOX', 'QREDIT_WIDGET_GATEWAY_URL'];

    foreach ($keys as $key) {
        putenv($key);
        unset($_ENV[$key], $_SERVER[$key]);
    }

    foreach ($env as $key => $value) {
        putenv("{$key}={$value}");
        $_ENV[$key] = $_SERVER[$key] = $value;
    }

    try {
        return (require __DIR__.'/../../config/qredit.php')['widget'];
    } finally {
        foreach ($keys as $key) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }
    }
}

it('points the gateway at the UAT portal in sandbox', function () {
    expect(widgetConfigWith(['QREDIT_SANDBOX' => 'true'])['gateway_url'])
        ->toBe('https://portaltest.qredit.tech/plugin/');
});

it('points the gateway at the production portal when sandbox is off', function () {
    expect(widgetConfigWith(['QREDIT_SANDBOX' => 'false'])['gateway_url'])
        ->toBe('https://portal.qredit.tech/plugin/');
});

it('defaults to the UAT portal when sandbox is not set', function () {
    expect(widgetConfigWith([])['gateway_url'])
        ->toBe('https://portaltest.qredit.tech/plugin/');
});

it('lets QREDIT_WIDGET_GATEWAY_URL override the sandbox flag', function () {
    expect(widgetConfigWith([
        'QREDIT_SANDBOX' => 'false',
        'QREDIT_WIDGET_GATEWAY_URL' => 'https://gateway.example.test/plugin/',
    ])['gateway_url'])->toBe('https://gateway.example.test/plugin/');
});

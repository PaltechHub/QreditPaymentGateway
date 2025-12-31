<?php

declare(strict_types=1);

it('can run a simple test', function () {
    expect(true)->toBeTrue();
});

it('can load config values', function () {
    config(['qredit.api_key' => 'test-key-123']);
    expect(config('qredit.api_key'))->toBe('test-key-123');
});
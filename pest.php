<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

use Qredit\LaravelQredit\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class)
    ->in('Feature', 'Unit');

uses(RefreshDatabase::class)
    ->in('Feature/Database');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

expect()->extend('toBeSuccess', function () {
    return $this->toBeTrue()
        ->and($this->value)->toBe('success');
});

expect()->extend('toHaveRequiredPaymentFields', function () {
    return $this->toHaveKeys([
        'msgId',
        'amount',
        'currencyCode',
        'clientReference',
    ]);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function mockQreditResponse(array $data = [], int $status = 200): array
{
    return [
        'status' => $status,
        'data' => array_merge([
            'msgId' => uniqid('msg_'),
            'status' => 'SUCCESS',
            'timestamp' => now()->toIso8601String(),
        ], $data),
    ];
}

function generateTestApiKey(): string
{
    return 'test_' . bin2hex(random_bytes(20));
}

function createTestPaymentData(array $overrides = []): array
{
    return array_merge([
        'amount' => 100.00,
        'currencyCode' => 'ILS',
        'clientReference' => 'TEST-' . uniqid(),
        'customerDetails' => [
            'customerName' => 'Test User',
            'customerEmail' => 'test@example.com',
            'customerPhone' => '+972501234567',
        ],
    ], $overrides);
}
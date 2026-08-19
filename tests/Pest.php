<?php

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

uses(
    Qredit\LaravelQredit\Tests\TestCase::class,
    // Illuminate\Foundation\Testing\RefreshDatabase::class,
)->in('Feature', 'Unit');

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

function getTestConnector(): \Qredit\LaravelQredit\Connectors\QreditConnector
{
    return new \Qredit\LaravelQredit\Connectors\QreditConnector(
        apiKey: 'test-api-key',
        sandbox: true
    );
}

function createTestPaymentData(): array
{
    return [
        'amount' => 100.00,
        'currencyCode' => 'ILS',
        'description' => 'Test payment',
        'reference' => 'TEST-' . uniqid(),
        'successUrl' => 'https://example.com/success',
        'failureUrl' => 'https://example.com/failure',
        'cancelUrl' => 'https://example.com/cancel',
        'customer' => [
            'email' => 'test@example.com',
            'name' => 'Test Customer',
            'phone' => '+972501234567',
        ],
    ];
}

function mockQreditResponse(array $data = []): array
{
    return [
        'success' => true,
        'data' => $data,
        'message' => 'Operation successful',
        'timestamp' => time(),
    ];
}
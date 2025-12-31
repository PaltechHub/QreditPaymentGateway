<?php

declare(strict_types=1);

use Qredit\LaravelQredit\Qredit;
use Qredit\LaravelQredit\Requests\Auth\GetTokenRequest;
use Qredit\LaravelQredit\Requests\Customers\ListCustomersRequest;
use Qredit\LaravelQredit\Requests\Transactions\ListTransactionsRequest;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

beforeEach(function () {
    config(['qredit.api_key' => 'test-api-key']);
    config(['qredit.sandbox' => true]);
    config(['qredit.token.cache_enabled' => false]); // Disable caching for tests
});

it('can list customers through the Qredit service', function () {
    $mockClient = new MockClient([
        GetTokenRequest::class => MockResponse::make([
            'access_token' => 'test-token-123',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ], 200),
        ListCustomersRequest::class => MockResponse::make([
            'status' => 'success',
            'customers' => [
                [
                    'id' => 'CUST_001',
                    'name' => 'John Doe',
                    'email' => 'john@example.com',
                    'phone' => '+972501234567',
                ],
                [
                    'id' => 'CUST_002',
                    'name' => 'Jane Smith',
                    'email' => 'jane@example.com',
                    'phone' => '+972509876543',
                ],
            ],
            'total' => 2,
        ], 200),
    ]);

    $qredit = new Qredit('test-api-key', true, true); // Skip initial auth
    $qredit->getConnector()->withMockClient($mockClient);

    $result = $qredit->listCustomers();

    expect($result)->toBeArray();
    expect($result)->toHaveKey('customers');
    expect($result['customers'])->toHaveCount(2);
    expect($result['customers'][0]['name'])->toBe('John Doe');
});

it('can filter customers with parameters', function () {
    $mockClient = new MockClient([
        GetTokenRequest::class => MockResponse::make([
            'access_token' => 'test-token-123',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ], 200),
        ListCustomersRequest::class => MockResponse::make([
            'status' => 'success',
            'customers' => [
                [
                    'id' => 'CUST_001',
                    'name' => 'John Doe',
                    'email' => 'john@example.com',
                ],
            ],
            'total' => 1,
        ], 200),
    ]);

    $qredit = new Qredit('test-api-key', true, true); // Skip initial auth
    $qredit->getConnector()->withMockClient($mockClient);

    $result = $qredit->listCustomers([
        'name' => 'John',
        'max' => 10,
        'offset' => 0,
    ]);

    expect($result)->toBeArray();
    expect($result['customers'])->toHaveCount(1);
    expect($result['customers'][0]['name'])->toBe('John Doe');
});

it('can list transactions through the Qredit service', function () {
    $mockClient = new MockClient([
        GetTokenRequest::class => MockResponse::make([
            'access_token' => 'test-token-123',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ], 200),
        ListTransactionsRequest::class => MockResponse::make([
            'status' => 'success',
            'transactions' => [
                [
                    'id' => 'TXN_001',
                    'reference' => 'REF-2024-001',
                    'amount' => 100.00,
                    'currency' => 'ILS',
                    'status' => 'completed',
                ],
                [
                    'id' => 'TXN_002',
                    'reference' => 'REF-2024-002',
                    'amount' => 250.50,
                    'currency' => 'ILS',
                    'status' => 'pending',
                ],
            ],
            'total' => 2,
        ], 200),
    ]);

    $qredit = new Qredit('test-api-key', true, true); // Skip initial auth
    $qredit->getConnector()->withMockClient($mockClient);

    $result = $qredit->listTransactions();

    expect($result)->toBeArray();
    expect($result)->toHaveKey('transactions');
    expect($result['transactions'])->toHaveCount(2);
    expect($result['transactions'][0]['status'])->toBe('completed');
});

it('can filter transactions with parameters', function () {
    $mockClient = new MockClient([
        GetTokenRequest::class => MockResponse::make([
            'access_token' => 'test-token-123',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ], 200),
        ListTransactionsRequest::class => MockResponse::make([
            'status' => 'success',
            'transactions' => [
                [
                    'id' => 'TXN_003',
                    'reference' => 'ORDER-123',
                    'amount' => 500.00,
                    'currency' => 'USD',
                    'status' => 'completed',
                    'date' => '2024-12-15',
                ],
            ],
            'total' => 1,
        ], 200),
    ]);

    $qredit = new Qredit('test-api-key', true, true); // Skip initial auth
    $qredit->getConnector()->withMockClient($mockClient);

    $result = $qredit->listTransactions([
        'reference' => 'ORDER-123',
        'currencyCode' => 'USD',
        'transactionStatus' => 'completed',
        'dateFrom' => '2024-12-01',
        'dateTo' => '2024-12-31',
    ]);

    expect($result)->toBeArray();
    expect($result['transactions'])->toHaveCount(1);
    expect($result['transactions'][0]['reference'])->toBe('ORDER-123');
    expect($result['transactions'][0]['currency'])->toBe('USD');
});

it('handles authentication automatically before listing customers', function () {
    $mockClient = new MockClient([
        GetTokenRequest::class => MockResponse::make([
            'access_token' => 'test-token-123',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ], 200),
        ListCustomersRequest::class => MockResponse::make([
            'status' => 'success',
            'customers' => [],
            'total' => 0,
        ], 200),
    ]);

    $qredit = new Qredit('test-api-key', true, true); // Skip initial auth
    $qredit->getConnector()->withMockClient($mockClient);

    $result = $qredit->listCustomers();

    expect($result)->toBeArray();
    expect($result)->toHaveKey('status', 'success');
});

it('handles authentication automatically before listing transactions', function () {
    $mockClient = new MockClient([
        GetTokenRequest::class => MockResponse::make([
            'access_token' => 'test-token-123',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ], 200),
        ListTransactionsRequest::class => MockResponse::make([
            'status' => 'success',
            'transactions' => [],
            'total' => 0,
        ], 200),
    ]);

    $qredit = new Qredit('test-api-key', true, true); // Skip initial auth
    $qredit->getConnector()->withMockClient($mockClient);

    $result = $qredit->listTransactions();

    expect($result)->toBeArray();
    expect($result)->toHaveKey('status', 'success');
});
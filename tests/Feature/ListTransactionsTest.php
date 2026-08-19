<?php

declare(strict_types=1);

use Qredit\LaravelQredit\Requests\Transactions\ListTransactionsRequest;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

it('can list transactions with default parameters', function () {
    $mockClient = new MockClient([
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

    $connector = getTestConnector();
    $connector->withMockClient($mockClient);

    $request = new ListTransactionsRequest();
    $response = $connector->send($request);

    expect($response->status())->toBe(200);
    expect($response->json())->toHaveKey('transactions');
    expect($response->json()['transactions'])->toHaveCount(2);
});

it('can filter transactions by status', function () {
    $mockClient = new MockClient([
        ListTransactionsRequest::class => MockResponse::make([
            'status' => 'success',
            'transactions' => [
                [
                    'id' => 'TXN_001',
                    'status' => 'completed',
                    'amount' => 100.00,
                ],
            ],
            'total' => 1,
        ], 200),
    ]);

    $connector = getTestConnector();
    $connector->withMockClient($mockClient);

    $request = new ListTransactionsRequest(['transactionStatus' => 'completed']);
    $response = $connector->send($request);

    expect($response->status())->toBe(200);
    expect($response->json()['transactions'])->toHaveCount(1);
    expect($response->json()['transactions'][0]['status'])->toBe('completed');
});

it('can filter transactions by date range', function () {
    $mockClient = new MockClient([
        ListTransactionsRequest::class => MockResponse::make([
            'status' => 'success',
            'transactions' => [
                [
                    'id' => 'TXN_003',
                    'date' => '2024-12-01',
                    'amount' => 500.00,
                ],
                [
                    'id' => 'TXN_004',
                    'date' => '2024-12-15',
                    'amount' => 750.00,
                ],
            ],
            'total' => 2,
        ], 200),
    ]);

    $connector = getTestConnector();
    $connector->withMockClient($mockClient);

    $request = new ListTransactionsRequest([
        'dateFrom' => '2024-12-01',
        'dateTo' => '2024-12-31',
    ]);
    $response = $connector->send($request);

    expect($response->status())->toBe(200);
    expect($response->json()['transactions'])->toHaveCount(2);
});

it('can filter transactions by currency', function () {
    $mockClient = new MockClient([
        ListTransactionsRequest::class => MockResponse::make([
            'status' => 'success',
            'transactions' => [
                [
                    'id' => 'TXN_005',
                    'currency' => 'USD',
                    'amount' => 100.00,
                ],
            ],
            'total' => 1,
        ], 200),
    ]);

    $connector = getTestConnector();
    $connector->withMockClient($mockClient);

    $request = new ListTransactionsRequest(['currencyCode' => 'USD']);
    $response = $connector->send($request);

    expect($response->status())->toBe(200);
    expect($response->json()['transactions'][0]['currency'])->toBe('USD');
});

it('can filter transactions by reference', function () {
    $mockClient = new MockClient([
        ListTransactionsRequest::class => MockResponse::make([
            'status' => 'success',
            'transactions' => [
                [
                    'id' => 'TXN_006',
                    'reference' => 'ORDER-123',
                    'amount' => 300.00,
                ],
            ],
            'total' => 1,
        ], 200),
    ]);

    $connector = getTestConnector();
    $connector->withMockClient($mockClient);

    $request = new ListTransactionsRequest(['reference' => 'ORDER-123']);
    $response = $connector->send($request);

    expect($response->status())->toBe(200);
    expect($response->json()['transactions'][0]['reference'])->toBe('ORDER-123');
});

it('can paginate transaction results', function () {
    $mockClient = new MockClient([
        ListTransactionsRequest::class => MockResponse::make([
            'status' => 'success',
            'transactions' => [
                [
                    'id' => 'TXN_050',
                    'amount' => 100.00,
                ],
                [
                    'id' => 'TXN_051',
                    'amount' => 200.00,
                ],
            ],
            'total' => 100,
            'offset' => 50,
            'max' => 2,
        ], 200),
    ]);

    $connector = getTestConnector();
    $connector->withMockClient($mockClient);

    $request = new ListTransactionsRequest([
        'max' => 2,
        'offset' => 50,
    ]);
    $response = $connector->send($request);

    expect($response->status())->toBe(200);
    expect($response->json()['offset'])->toBe(50);
    expect($response->json()['max'])->toBe(2);
});

it('generates unique message ID for transaction list requests', function () {
    $request1 = new ListTransactionsRequest();
    $request2 = new ListTransactionsRequest();

    $query1 = $request1->query()->all();
    $query2 = $request2->query()->all();

    expect($query1)->toHaveKey('msgId');
    expect($query2)->toHaveKey('msgId');
    expect($query1['msgId'])->not->toBe($query2['msgId']);
    expect($query1['msgId'])->toStartWith('txn_list_');
    expect($query2['msgId'])->toStartWith('txn_list_');
});

it('includes all optional filter parameters when provided', function () {
    $filters = [
        'reference' => 'REF-123',
        'clientReference' => 'CLIENT-456',
        'providerReference' => 'PROV-789',
        'paymentRequestReference' => 'PR-001',
        'orderReference' => 'ORD-002',
        'corporateId' => 'CORP-100',
        'subCorporateId' => 'SUB-200',
        'subCorporateAccountId' => 'ACC-300',
        'dateFrom' => '2024-01-01',
        'dateTo' => '2024-12-31',
        'currencyCode' => 'ILS',
        'operation' => 'payment',
        'onlyBalanceTransactions' => true,
        'transactionStatus' => 'completed',
        'sSearch' => 'search term',
        'orderColumnName' => 'date',
        'orderDirection' => 'desc',
    ];

    $request = new ListTransactionsRequest($filters);
    $query = $request->query()->all();

    foreach ($filters as $key => $value) {
        expect($query)->toHaveKey($key, $value);
    }
});

it('uses correct endpoint for transactions', function () {
    $request = new ListTransactionsRequest();
    expect($request->resolveEndpoint())->toBe('/payments');
});
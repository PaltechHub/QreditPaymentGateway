<?php

declare(strict_types=1);

use Qredit\LaravelQredit\Requests\Customers\ListCustomersRequest;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

it('can list customers with default parameters', function () {
    $mockClient = new MockClient([
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

    $connector = getTestConnector();
    $connector->withMockClient($mockClient);

    $request = new ListCustomersRequest();
    $response = $connector->send($request);

    expect($response->status())->toBe(200);
    expect($response->json())->toHaveKey('customers');
    expect($response->json()['customers'])->toHaveCount(2);
});

it('can filter customers by name', function () {
    $mockClient = new MockClient([
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

    $connector = getTestConnector();
    $connector->withMockClient($mockClient);

    $request = new ListCustomersRequest(['name' => 'John']);
    $response = $connector->send($request);

    expect($response->status())->toBe(200);
    expect($response->json()['customers'])->toHaveCount(1);
    expect($response->json()['customers'][0]['name'])->toBe('John Doe');
});

it('can filter customers by email', function () {
    $mockClient = new MockClient([
        ListCustomersRequest::class => MockResponse::make([
            'status' => 'success',
            'customers' => [
                [
                    'id' => 'CUST_002',
                    'name' => 'Jane Smith',
                    'email' => 'jane@example.com',
                ],
            ],
            'total' => 1,
        ], 200),
    ]);

    $connector = getTestConnector();
    $connector->withMockClient($mockClient);

    $request = new ListCustomersRequest(['email' => 'jane@example.com']);
    $response = $connector->send($request);

    expect($response->status())->toBe(200);
    expect($response->json()['customers'][0]['email'])->toBe('jane@example.com');
});

it('can paginate customer results', function () {
    $mockClient = new MockClient([
        ListCustomersRequest::class => MockResponse::make([
            'status' => 'success',
            'customers' => [
                [
                    'id' => 'CUST_010',
                    'name' => 'Customer 10',
                ],
                [
                    'id' => 'CUST_011',
                    'name' => 'Customer 11',
                ],
            ],
            'total' => 50,
            'offset' => 10,
            'max' => 2,
        ], 200),
    ]);

    $connector = getTestConnector();
    $connector->withMockClient($mockClient);

    $request = new ListCustomersRequest([
        'max' => 2,
        'offset' => 10,
    ]);
    $response = $connector->send($request);

    expect($response->status())->toBe(200);
    expect($response->json()['offset'])->toBe(10);
    expect($response->json()['max'])->toBe(2);
});

it('generates unique message ID for customer list requests', function () {
    $request1 = new ListCustomersRequest();
    $request2 = new ListCustomersRequest();

    $query1 = $request1->query()->all();
    $query2 = $request2->query()->all();

    expect($query1)->toHaveKey('msgId');
    expect($query2)->toHaveKey('msgId');
    expect($query1['msgId'])->not->toBe($query2['msgId']);
    expect($query1['msgId'])->toStartWith('cust_list_');
    expect($query2['msgId'])->toStartWith('cust_list_');
});

it('includes all optional filter parameters when provided', function () {
    $filters = [
        'name' => 'John',
        'phone' => '+972501234567',
        'email' => 'john@example.com',
        'idNumber' => '123456789',
        'sSearch' => 'search term',
        'orderColumnName' => 'name',
        'orderDirection' => 'asc',
    ];

    $request = new ListCustomersRequest($filters);
    $query = $request->query()->all();

    foreach ($filters as $key => $value) {
        expect($query)->toHaveKey($key, $value);
    }
});
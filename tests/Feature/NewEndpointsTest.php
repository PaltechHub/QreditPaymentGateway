<?php

declare(strict_types=1);

use Qredit\LaravelQredit\Requests\CorporateLimits\ListCorporateLimitPeriodsRequest;
use Qredit\LaravelQredit\Requests\CorporateLimits\SyncCorporateBranchLimitRequest;
use Qredit\LaravelQredit\Requests\PaymentRequests\CalculateFeesRequest;
use Qredit\LaravelQredit\Requests\Reports\ReconciliationReportRequest;
use Qredit\LaravelQredit\Requests\Transactions\ChangeClearingStatusRequest;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;

function sendMocked(\Saloon\Http\Request $request): PendingRequest
{
    $mockClient = new MockClient([MockResponse::make(['status' => true, 'records' => []], 200)]);

    $connector = new \Qredit\LaravelQredit\Connectors\QreditConnector([
        'api_key' => 'test-api-key',
        'secret_key' => 'test-secret-key',
        'client_version' => '1.0.0',
        'sandbox' => true,
        'sandbox_url' => 'https://apitest.qredit.tech/gw-checkout/api/v1',
        'auth_scheme' => 'HmacSHA512_O',
    ]);
    $connector->setAuthToken('test-token');
    $connector->withMockClient($mockClient);

    return $connector->send($request)->getPendingRequest();
}

it('posts reference and productCode to paymentRequests/calculateFees', function () {
    $pending = sendMocked(new CalculateFeesRequest('PR-1', 'CSAB'));

    expect($pending->getMethod()->value)->toBe('POST');
    expect($pending->getUrl())->toEndWith('/paymentRequests/calculateFees');
    expect($pending->body()->all())
        ->toHaveKey('msgId')
        ->toMatchArray(['reference' => 'PR-1', 'productCode' => 'CSAB']);
});

it('posts the clearing change to payments/changeClearingStatus as BP', function () {
    $pending = sendMocked(new ChangeClearingStatusRequest('enc-tx-id', 'on_hold', 'Disputed', 'ops-user'));

    expect($pending->getMethod()->value)->toBe('POST');
    expect($pending->getUrl())->toEndWith('/payments/changeClearingStatus');
    expect($pending->headers()->get('Client-Type'))->toBe('BP');
    expect($pending->body()->all())
        ->toHaveKey('msgId')
        ->toMatchArray([
            'encodedId' => 'enc-tx-id',
            'clearingStatus' => 'ON_HOLD',
            'statusReason' => 'Disputed',
            'username' => 'ops-user',
        ]);
});

it('omits username from changeClearingStatus when not given', function () {
    $pending = sendMocked(new ChangeClearingStatusRequest('enc-tx-id', 'CLEARED', 'Settled'));

    expect($pending->body()->all())->not->toHaveKey('username');
});

it('rejects a clearing status the endpoint does not accept', function () {
    new ChangeClearingStatusRequest('enc-tx-id', 'REVERSED', 'x');
})->throws(InvalidArgumentException::class);

it('sends documented filters to reports/reconciliation with a default date window', function () {
    $pending = sendMocked(new ReconciliationReportRequest([
        'transactionStatus' => 'SUCCESS',
        'settlementReference' => 'SET-1',
        'clientReference' => 'not-supported-here',
    ]));

    expect($pending->getMethod()->value)->toBe('GET');
    expect($pending->getUrl())->toEndWith('/reports/reconciliation');
    expect($pending->query()->all())
        ->toHaveKeys(['msgId', 'dateFrom', 'dateTo'])
        ->toMatchArray(['transactionStatus' => 'SUCCESS', 'settlementReference' => 'SET-1'])
        ->not->toHaveKey('clientReference');
});

it('sends only documented filters to admin/corporateLimitPeriods', function () {
    $pending = sendMocked(new ListCorporateLimitPeriodsRequest([
        'corporateCode' => 'CORP1',
        'periodType' => 'DAILY',
        'interval' => '2026-07-18',
        'dir' => 'desc',
        'unknown' => 'x',
    ]));

    expect($pending->getMethod()->value)->toBe('GET');
    expect($pending->getUrl())->toEndWith('/admin/corporateLimitPeriods');
    expect($pending->query()->all())
        ->toMatchArray([
            'max' => 10,
            'offset' => 0,
            'corporateCode' => 'CORP1',
            'periodType' => 'DAILY',
            'interval' => '2026-07-18',
            'dir' => 'desc',
        ])
        ->not->toHaveKey('unknown');
});

it('syncs a corporate branch limit with the SYS client type', function () {
    $pending = sendMocked(new SyncCorporateBranchLimitRequest('enc-corp-id', 'create'));

    expect($pending->getMethod()->value)->toBe('POST');
    expect($pending->getUrl())->toEndWith('/admin/admin/corporateBranchLimit');
    expect($pending->headers()->get('Client-Type'))->toBe('SYS');
    expect($pending->body()->all())
        ->toHaveKey('msgId')
        ->toMatchArray(['corporateId' => 'enc-corp-id', 'operation' => 'CREATE']);
});

it('rejects an unknown corporate branch limit operation', function () {
    new SyncCorporateBranchLimitRequest('enc-corp-id', 'UPDATE');
})->throws(InvalidArgumentException::class);

it('sends documented query fields to paymentRequests/generateQR', function () {
    $pending = sendMocked(new \Qredit\LaravelQredit\Requests\PaymentRequests\GenerateQRRequest([
        'reference' => 'PR-1',
        'productCode' => 'NC-QR',
        'expiryTimeLimit' => 60,
        'merchantChannelMedia' => 'screen_electronic_app',
        'unknown' => 'x',
    ]));

    expect($pending->getMethod()->value)->toBe('GET');
    expect($pending->getUrl())->toEndWith('/paymentRequests/generateQR');
    expect($pending->query()->all())
        ->toHaveKey('msgId')
        ->toMatchArray([
            'reference' => 'PR-1',
            'productCode' => 'NC-QR',
            'expiryTimeLimit' => 60,
            'merchantChannelMedia' => 'SCREEN_ELECTRONIC_APP',
        ])
        ->not->toHaveKey('unknown');
});

it('rejects an unknown merchantChannelMedia', function () {
    new \Qredit\LaravelQredit\Requests\PaymentRequests\GenerateQRRequest(['merchantChannelMedia' => 'BILLBOARD']);
})->throws(InvalidArgumentException::class);

# API Reference

Every method exposed by the Qredit facade, with request/response shapes. All responses are the gateway's native envelope (not re-wrapped by the SDK).

## Envelope format

All responses share one of two shapes:

### Singular (`ResponseModel`)

```json
{
  "status": true,
  "code": "00",
  "message": "Success",
  "reference": "1775133836693",
  "errors": { ... }
}
```

### List (`LookupsResponseModel`)

```json
{
  "status": true,
  "code": "00",
  "message": "Success",
  "reference": "1775133836693",
  "totalCount": "1",
  "offset": "0",
  "records": [ { ... }, { ... } ],
  "errors": { ... }
}
```

Check `$response['status'] === true` to detect success. On failure, `code` carries a gateway-specific error code — see [TROUBLESHOOTING.md](TROUBLESHOOTING.md).

---

## Authentication

### `authenticate(bool $force = false): string`

Exchanges the API key for a bearer token. Cached in Laravel's cache by default (per-tenant namespaced). The SDK calls this automatically on first use and after 401 responses.

```php
$token = Qredit::authenticate();
$token = Qredit::authenticate(force: true);  // force refresh
```

**Gateway endpoint:** `POST /auth/token`

**Gateway response:**

```json
{
  "status": true,
  "access_token": "eyJhbGciOi...",
  "message": "logged in successfully"
}
```

---

## Orders

### `createOrder(array $data): array`

```php
$response = Qredit::createOrder([
    'msgId'                => 'optional, auto-generated if omitted',
    'amountCents'          => 3200,             // required — 100 ILS = 10000
    'currencyCode'         => 'ILS',            // required
    'clientReference'      => 'your-order-id',  // optional
    'deliveryNeeded'       => 'true',           // string 'true'/'false'
    'deliveryCostCents'    => 200,
    'shippingProviderCode' => 'DELV2',          // carrier code at the gateway
    'items' => [
        [
            'name'        => 'Widget',
            'amountCents' => 2000,
            'description' => 'One widget',
            'quantity'    => 1,
            'sku'         => 'W-001',
            'imageUrl'    => 'https://cdn.example.com/w-001.jpg',
        ],
    ],
    'shippingData' => [
        'countryCode' => 'PSE',
        'postalCode'  => '970',
        'state'       => 'West Bank',
        'cityCode'    => '50',
        'areaCode'    => '50',
        'street'      => "Jemma'in",
        'building'    => 'Bab wad',
        'apartment'   => '01',
        'floor'       => '07',
    ],
    'customerInfo' => [
        'name'     => 'Jane Doe',
        'phone'    => '+970599785833',
        'email'    => 'jane@example.com',
        'idNumber' => '408573939',
    ],
]);
```

**Gateway endpoint:** `POST /orders`

**Response shape:** `LookupsResponseModel`. `$response['records'][0]['orderReference']` is the id you'll pass to every later order call.

---

### `registerOrder(array $data): array`

Alias for `createOrder()`. Kept for semantic clarity (merchant doc §3 uses "register").

---

### `getOrder(string $orderReference): array`

Fetches a single order by reference. Implemented as a filtered list call against `GET /orders?orderReference=…` — the swagger only exposes list, not get-by-id.

```php
$response = Qredit::getOrder('ORD-123');
$order = $response['records'][0] ?? null;
```

---

### `updateOrder(string $orderReference, array $data): array`

```php
Qredit::updateOrder('ORD-123', [
    'deliveryNeeded'    => 'false',
    'deliveryCostCents' => 0,
]);
```

**Gateway endpoint:** `PUT /orders` (reference lives in the body, not the URL).

---

### `cancelOrder(string $orderReference, ?string $reason = null): array`

```php
Qredit::cancelOrder('ORD-123', 'Customer requested cancellation');
```

**Gateway endpoint:** `DELETE /orders` with `{ msgId, orderReference, reason }` body.

---

### `listOrders(array $query = []): array`

```php
Qredit::listOrders([
    'dateFrom'        => '01/01/2026',   // defaults to last 30 days if omitted
    'dateTo'          => '31/12/2026',
    'orderStatus'     => 'PAID',         // NEW | CONFIRMED | CANCELLED | PAID | COMPLETED
    'customerEmail'   => 'jane@',
    'clientReference' => 'ORDER-2026-',
    'max'             => 50,
    'offset'          => 0,
]);
```

All filters are optional. `dateFrom` / `dateTo` are required by the gateway but the SDK supplies a 30-day default if you omit them.

---

## Payment Requests

### `createPayment(array $data): array`

```php
$response = Qredit::createPayment([
    'orderReference'    => 'ORD-123',      // from createOrder
    'amountCents'       => 3200,
    'currencyCode'      => 'ILS',
    'lockOrderWhenPaid' => true,           // disallow further payments once one succeeds
    'expiration'        => 1440,           // minutes until the payment request expires
    'paymentChannels'   => [
        ['code' => 'CSAB'],                // Card
        ['code' => 'esadad_biller'],       // Palestinian SADAD
        ['code' => 'NC-QR'],               // QR
    ],
    'billingData' => [
        'countryCode' => 'PSE',
        'city'        => '50',
        'area'        => '50',
        'street'      => "Jemma'in",
        'postalCode'  => '970',
        'state'       => 'West Bank',
        'building'    => 'Bab wad',
        'apartment'   => '01',
        'floor'       => '07',
    ],
    'customerInfo' => [
        'name'        => 'Jane Doe',
        'phoneNumber' => '+970599785833',
        'email'       => 'jane@example.com',
        'idNumber'    => '408573939',
    ],
]);
```

**Gateway endpoint:** `POST /paymentRequests`

**Response:** `LookupsResponseModel`. Critical fields in `$response['records'][0]`:

- `reference` — the payment-request id (used by the widget)
- `url` — the hosted checkout URL (redirect the customer here, or embed in an iframe)
- `paymentRequestStatus` — `PENDING_PAYMENT` initially

---

### `getPayment(string $paymentRequestReference): array`

Fetch by reference. Implemented via the list endpoint (swagger has no get-by-id).

```php
$response = Qredit::getPayment('66573792');
$payment = $response['records'][0] ?? null;
```

---

### `updatePayment(string $paymentRequestReference, array $data): array`

```php
Qredit::updatePayment('66573792', [
    'amountCents'  => 4000,
    'currencyCode' => 'ILS',
    'billingData'  => [/* updated */],
]);
```

**Gateway endpoint:** `PUT /paymentRequests` (reference in body).

---

### `deletePayment(string $paymentRequestReference, ?string $reason = null): array`

```php
Qredit::deletePayment('66573792', 'Customer abandoned checkout');
```

**Gateway endpoint:** `DELETE /paymentRequests` with `{ msgId, reference, reason }` body.

---

### `listPayments(array $query = []): array`

```php
Qredit::listPayments([
    'dateFrom'       => '01/01/2026',
    'dateTo'         => '31/12/2026',
    'status'         => 'PAID',          // PENDING_PAYMENT | PAID | CANCELLED | EXPIRED
    'orderReference' => 'ORD-123',
    'max'            => 50,
    'offset'         => 0,
]);
```

---

### `generateQR(array $query): array`

```php
$qr = Qredit::generateQR([
    'reference'            => '66573792',
    'productCode'          => 'NC-QR',
    'expiryTimeLimit'      => 1440,
    'merchantChannelMedia' => 'SCREEN_ELECTRONIC_WEBSITE',
]);
```

**Gateway endpoint:** `GET /paymentRequests/generateQR`

Returns the QR image payload (base64 or URL, depending on account config).

---

### `calculateFees(array $data): array`

Preview gateway fees before creating a payment request.

```php
$fees = Qredit::calculateFees([
    'reference'   => '66573792',
    'productCode' => 'CSAB',
]);
```

**Gateway endpoint:** `POST /paymentRequests/calculateFees`

---

### `initPayment(array $data): array`

Initiates payment on a specific channel for an existing payment request.

```php
Qredit::initPayment([
    'reference'   => '66573792',
    'productCode' => 'CSAB',
]);
```

**Gateway endpoint:** `POST /paymentRequests/initPayment`

---

## Customers

### `listCustomers(array $filters = []): array`

```php
Qredit::listCustomers([
    'name'     => 'Jane',
    'email'    => 'jane@',
    'phone'    => '+970',
    'idNumber' => '408573939',
    'sSearch'  => 'general search term',
    'max'      => 50,
    'offset'   => 0,
]);
```

**Gateway endpoint:** `GET /customers`

---

## Transactions

### `listTransactions(array $filters = []): array`

```php
Qredit::listTransactions([
    'dateFrom'                => '01/01/2026',
    'dateTo'                  => '31/12/2026',
    'transactionStatus'       => 'SUCCESS',    // PENDING | SUCCESS | FAILED | CANCELLED | WAITING_APPROVAL
    'clearingStatus'          => 'CLEARED',    // NOT_APPLICABLE | NOT_CLEARED | IN_CLEARING | CLEARED | ON_HOLD | REVERSED
    'paymentRequestReference' => '66573792',
    'orderReference'          => 'ORD-123',
    'currencyCode'            => 'ILS',
    'max'                     => 50,
    'offset'                  => 0,
]);
```

**Gateway endpoint:** `GET /payments`

Each record carries:

```json
{
  "reference":         "transaction-uuid",
  "clientReference":   "CLI-987",
  "providerReference": "PROV-456",
  "amount":            1000.00,
  "currency":          "ILS",
  "transactionStatus": "SUCCESS",
  "paymentRequest":    { "encodedId": "...", "amount": 1000.00 },
  "sender":            { "latinName": "Jane Doe", ... },
  "receiver":          { "latinName": "Merchant", ... }
}
```

---

### `changeClearingStatus(array $data): array`

```php
Qredit::changeClearingStatus([
    'encodedId'      => 'txn-encoded-id',
    'clearingStatus' => 'CLEARED',       // NOT_CLEARED | CLEARED | ON_HOLD
    'statusReason'   => 'Settled with provider',
    'username'       => 'settlement-bot',   // optional
]);
```

**Gateway endpoint:** `POST /payments/changeClearingStatus`

---

## Webhook verification

### `verifyWebhookSignature(array $payload, string $authorizationHeader): bool`

Verify an inbound webhook. The built-in `WebhookController` calls this automatically — you only need it if you're writing a custom controller.

```php
if (! Qredit::verifyWebhookSignature($request->all(), $request->header('Authorization'))) {
    abort(400, 'Invalid webhook signature');
}
```

---

### `processWebhook(array $payload, ?string $authorizationHeader = null): array`

Verify + normalize a payload into an SDK-shape envelope for event dispatch.

```php
$processed = Qredit::processWebhook($request->all(), $request->header('Authorization'));

// $processed = [
//     'event'         => 'transaction',
//     'data'          => $payload['records'][0],
//     'raw'           => $payload,
//     'tenant_id'     => 'tenant-b',
//     'processed_at'  => '2026-04-14T18:06:06+00:00',
// ]
```

---

## Multi-tenant methods

### `Qredit::current(): Qredit`

Returns the client for the tenant bound to the current HTTP request.

### `Qredit::forTenant(?string $tenantId): Qredit`

Returns the client for an explicit tenant. **Always use this in queue jobs**, never rely on `current()` outside HTTP context.

### `Qredit::credentials(): CredentialProvider`

Access the bound credential provider (for advanced introspection / testing).

### `Qredit::tenants(): TenantResolver`

Access the bound tenant resolver.

---

## Error handling

Every call can throw:

```php
use Qredit\LaravelQredit\Exceptions\QreditException;
use Qredit\LaravelQredit\Exceptions\QreditAuthenticationException;  // 401 — token failed twice
use Qredit\LaravelQredit\Exceptions\QreditApiException;             // non-2xx with error body

try {
    Qredit::createOrder([...]);
} catch (QreditApiException $e) {
    Log::error('Qredit rejected create-order', [
        'http_code' => $e->getCode(),
        'body' => $e->getResponse(),  // decoded JSON
    ]);
} catch (QreditAuthenticationException $e) {
    Log::error('Qredit auth failure', ['message' => $e->getMessage()]);
} catch (QreditException $e) {
    Log::error('Qredit SDK error', ['message' => $e->getMessage()]);
}
```

---

## Request / response object internals

Every method delegates to a Saloon request class. If you need raw access (custom headers, Saloon middleware), use:

```php
use Qredit\LaravelQredit\Requests\Orders\CreateOrderRequest;

$response = Qredit::current()->getConnector()->send(
    (new CreateOrderRequest($data))->withMessageId('custom-msg-id'),
);
```

Request classes live under `src/Requests/{Category}/` — pair them with the facade methods in the [README](../README.md#api-surface) table.

---

## HTTP headers on every request

These are attached by `QreditConnector::defaultHeaders()` + `BaseQreditRequest::boot()`:

| Header | Source |
|---|---|
| `Accept: application/json` | always |
| `Content-Type: application/json` | for POST / PUT / PATCH / DELETE |
| `Accept-Language: EN` or `AR` | config or per-tenant language |
| `Client-Type: MP` | `config('qredit.client.type')` |
| `Client-Version: 1.0.0` | `config('qredit.client.version')` |
| `X-Auth-Token: <jwt>` | after first successful auth |
| `Authorization: HmacSHA512_O <hex>` | computed per-request |

You never set these manually unless adding a custom header via a Saloon middleware.

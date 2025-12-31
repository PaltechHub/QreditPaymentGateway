# Qredit Laravel SDK API Reference

## Table of Contents

- [Installation](#installation)
- [Configuration](#configuration)
- [Authentication](#authentication)
- [Payment Requests](#payment-requests)
- [Orders](#orders)
- [Customers](#customers)
- [Transactions](#transactions)
- [Webhooks](#webhooks)
- [Error Handling](#error-handling)
- [Testing](#testing)
- [Advanced Usage](#advanced-usage)

## Installation

```bash
composer require qredit/laravel-qredit
```

### Publish Configuration

```bash
php artisan vendor:publish --provider="Qredit\LaravelQredit\QreditServiceProvider" --tag="config"
```

## Configuration

### Environment Variables

Add these to your `.env` file:

```env
# Required
QREDIT_API_KEY=your-api-key-here

# Environment Settings
QREDIT_SANDBOX=true
QREDIT_SANDBOX_URL=http://185.57.122.58:2030/gw-checkout/api/v1
QREDIT_PRODUCTION_URL=https://api.qredit.com/gw-checkout/api/v1

# Language (EN or AR)
QREDIT_LANGUAGE=EN

# Client Headers
QREDIT_CLIENT_TYPE=MP
QREDIT_CLIENT_VERSION=1.0.0
QREDIT_CLIENT_AUTHORIZATION=HmacSHA512_O

# SDK Mode
QREDIT_SDK_ENABLED=false

# Token Storage
QREDIT_TOKEN_CACHE_ENABLED=true
QREDIT_TOKEN_STRATEGY=cache  # cache, database, or hybrid
QREDIT_TOKEN_TTL_BUFFER=300

# Webhook
QREDIT_WEBHOOK_ENABLED=true
QREDIT_WEBHOOK_PATH=/qredit/webhook
QREDIT_WEBHOOK_SECRET=your-webhook-secret

# Debug
QREDIT_DEBUG=false
```

## Authentication

Authentication is handled automatically by the SDK. The token is cached and refreshed as needed.

### Manual Authentication

```php
use Qredit\LaravelQredit\Facades\Qredit;

// Force authentication refresh
$token = Qredit::authenticate(true);
```

### Token Caching Strategies

```php
// In config/qredit.php
'token_storage' => [
    'strategy' => 'cache',    // Single server (Redis/Memcached)
    'strategy' => 'database', // Multi-server setup
    'strategy' => 'hybrid',   // Cache with database fallback
]
```

## Payment Requests

### Create Payment Request

```php
use Qredit\LaravelQredit\Facades\Qredit;

$payment = Qredit::createPayment([
    'amount' => 100.00,
    'currencyCode' => 'ILS',
    'description' => 'Order #123 Payment',
    'clientReference' => 'ORDER-123',
    'successUrl' => 'https://yoursite.com/payment/success',
    'failureUrl' => 'https://yoursite.com/payment/failure',
    'cancelUrl' => 'https://yoursite.com/payment/cancel',
    'callbackUrl' => 'https://yoursite.com/qredit/webhook',
    'customer' => [
        'email' => 'customer@example.com',
        'name' => 'John Doe',
        'phone' => '+972501234567',
        'address' => [
            'line1' => '123 Main St',
            'city' => 'Tel Aviv',
            'country' => 'IL',
            'postalCode' => '12345',
        ],
    ],
    'items' => [
        [
            'name' => 'Product 1',
            'quantity' => 2,
            'unitPrice' => 40.00,
            'totalPrice' => 80.00,
        ],
        [
            'name' => 'Shipping',
            'quantity' => 1,
            'unitPrice' => 20.00,
            'totalPrice' => 20.00,
        ],
    ],
    'metadata' => [
        'order_id' => '123',
        'customer_id' => '456',
        'campaign' => 'summer2024',
    ],
]);

// Response
[
    'reference' => 'PR_123456',
    'checkoutUrl' => 'https://checkout.qredit.com/session/123',
    'status' => 'PENDING',
    'expiresAt' => '2024-01-01T12:00:00Z',
]
```

### Get Payment Request

```php
$payment = Qredit::getPayment('PR_123456');

// Response
[
    'reference' => 'PR_123456',
    'amount' => 100.00,
    'currencyCode' => 'ILS',
    'status' => 'COMPLETED',
    'customer' => [...],
    'createdAt' => '2024-01-01T10:00:00Z',
    'completedAt' => '2024-01-01T10:05:00Z',
]
```

### Update Payment Request

```php
$payment = Qredit::updatePayment('PR_123456', [
    'amount' => 150.00,
    'description' => 'Updated order amount',
    'items' => [
        [
            'name' => 'Product 1',
            'quantity' => 3,
            'unitPrice' => 40.00,
            'totalPrice' => 120.00,
        ],
        [
            'name' => 'Shipping',
            'quantity' => 1,
            'unitPrice' => 30.00,
            'totalPrice' => 30.00,
        ],
    ],
]);
```

### Delete/Cancel Payment Request

```php
$result = Qredit::deletePayment('PR_123456');

// Returns true if successful
```

### List Payment Requests

```php
$payments = Qredit::listPayments([
    'max' => 50,
    'offset' => 0,
    'status' => 'COMPLETED',
    'dateFrom' => '2024-01-01',
    'dateTo' => '2024-12-31',
    'clientReference' => 'ORDER-123',
]);

// Response
[
    'payments' => [
        ['reference' => 'PR_123456', ...],
        ['reference' => 'PR_123457', ...],
    ],
    'total' => 100,
    'offset' => 0,
    'max' => 50,
]
```

## Orders

### Create Order

```php
$order = Qredit::createOrder([
    'orderNumber' => 'ORD-2024-001',
    'amount' => 250.00,
    'currencyCode' => 'ILS',
    'customer' => [
        'email' => 'customer@example.com',
        'name' => 'John Doe',
        'phone' => '+972501234567',
    ],
    'items' => [
        [
            'sku' => 'PROD-001',
            'name' => 'Product Name',
            'quantity' => 2,
            'price' => 100.00,
        ],
        [
            'sku' => 'SHIP-001',
            'name' => 'Express Shipping',
            'quantity' => 1,
            'price' => 50.00,
        ],
    ],
    'shippingAddress' => [
        'line1' => '123 Delivery St',
        'city' => 'Tel Aviv',
        'country' => 'IL',
        'postalCode' => '12345',
    ],
    'billingAddress' => [
        'line1' => '456 Billing Ave',
        'city' => 'Jerusalem',
        'country' => 'IL',
        'postalCode' => '54321',
    ],
]);
```

### Get Order

```php
$order = Qredit::getOrder('ORD_123456');
```

### Update Order

```php
$order = Qredit::updateOrder('ORD_123456', [
    'status' => 'PROCESSING',
    'trackingNumber' => 'TRACK123456',
    'notes' => 'Order is being prepared for shipment',
]);
```

### Cancel Order

```php
$order = Qredit::cancelOrder('ORD_123456', 'Customer requested cancellation');
```

### List Orders

```php
$orders = Qredit::listOrders([
    'max' => 20,
    'offset' => 0,
    'status' => 'PENDING',
    'customerEmail' => 'customer@example.com',
    'dateFrom' => '2024-01-01',
    'dateTo' => '2024-12-31',
]);
```

## Customers

### List Customers

```php
$customers = Qredit::listCustomers([
    'max' => 50,
    'offset' => 0,
    'name' => 'John',           // Filter by name
    'email' => 'john@',         // Filter by email (partial match)
    'phone' => '+9725',         // Filter by phone
    'idNumber' => '123456789',  // Filter by ID number
    'sSearch' => 'search term', // General search
    'orderColumnName' => 'name',
    'orderDirection' => 'ASC',
]);

// Response
[
    'customers' => [
        [
            'id' => 'CUST_001',
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '+972501234567',
            'idNumber' => '123456789',
            'createdAt' => '2024-01-01T00:00:00Z',
            'totalOrders' => 5,
            'totalSpent' => 1500.00,
        ],
        // ...
    ],
    'total' => 100,
    'offset' => 0,
    'max' => 50,
]
```

## Transactions

### List Transactions

```php
$transactions = Qredit::listTransactions([
    'max' => 50,
    'offset' => 0,
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
    'onlyBalanceTransactions' => false,
    'transactionStatus' => 'completed',
    'sSearch' => 'search term',
    'orderColumnName' => 'date',
    'orderDirection' => 'DESC',
]);

// Response
[
    'transactions' => [
        [
            'id' => 'TXN_001',
            'reference' => 'REF-2024-001',
            'amount' => 100.00,
            'currency' => 'ILS',
            'status' => 'completed',
            'type' => 'payment',
            'customer' => [
                'name' => 'John Doe',
                'email' => 'john@example.com',
            ],
            'createdAt' => '2024-01-01T10:00:00Z',
            'completedAt' => '2024-01-01T10:05:00Z',
            'fees' => 2.50,
            'netAmount' => 97.50,
        ],
        // ...
    ],
    'total' => 500,
    'offset' => 0,
    'max' => 50,
]
```

## Webhooks

### Webhook Configuration

Webhooks are automatically registered at `/qredit/webhook` (configurable).

### Verify Webhook Signature

```php
use Illuminate\Http\Request;
use Qredit\LaravelQredit\Facades\Qredit;

public function handleWebhook(Request $request)
{
    $payload = $request->getContent();
    $signature = $request->header('X-Qredit-Signature');

    // Verify signature
    if (!Qredit::verifyWebhookSignature($payload, $signature)) {
        return response()->json(['error' => 'Invalid signature'], 401);
    }

    // Process webhook
    $data = json_decode($payload, true);
    $result = Qredit::processWebhook($data, $signature);

    return response()->json(['status' => 'success']);
}
```

### Webhook Events

Listen for webhook events using Laravel's event system:

```php
// In EventServiceProvider.php
use Qredit\LaravelQredit\Events\PaymentSucceeded;
use Qredit\LaravelQredit\Events\PaymentFailed;
use Qredit\LaravelQredit\Events\RefundProcessed;

protected $listen = [
    PaymentSucceeded::class => [
        UpdateOrderStatusListener::class,
        SendPaymentConfirmationListener::class,
    ],
    PaymentFailed::class => [
        NotifyCustomerOfFailureListener::class,
        RetryPaymentListener::class,
    ],
    RefundProcessed::class => [
        ProcessRefundInSystemListener::class,
        NotifyCustomerOfRefundListener::class,
    ],
];
```

### Example Webhook Listener

```php
namespace App\Listeners;

use Qredit\LaravelQredit\Events\PaymentSucceeded;

class UpdateOrderStatusListener
{
    public function handle(PaymentSucceeded $event)
    {
        $payment = $event->payment;

        // Update your order
        Order::where('reference', $payment['clientReference'])
            ->update([
                'status' => 'paid',
                'paid_at' => now(),
                'transaction_id' => $payment['reference'],
            ]);

        // Log the payment
        Log::info('Payment successful', [
            'reference' => $payment['reference'],
            'amount' => $payment['amount'],
            'customer' => $payment['customer']['email'],
        ]);
    }
}
```

## Error Handling

### Exception Types

```php
use Qredit\LaravelQredit\Exceptions\QreditException;
use Qredit\LaravelQredit\Exceptions\QreditAuthenticationException;
use Qredit\LaravelQredit\Exceptions\QreditApiException;

try {
    $payment = Qredit::createPayment($data);
} catch (QreditAuthenticationException $e) {
    // Authentication failed - check API key
    Log::error('Authentication failed: ' . $e->getMessage());
    // Maybe retry with new credentials
} catch (QreditApiException $e) {
    // API error - validation, rate limits, etc.
    Log::error('API error: ' . $e->getMessage());
    $errorCode = $e->getCode();
    $errorResponse = $e->getResponse();

    if ($errorCode === 422) {
        // Validation error
        $validationErrors = $errorResponse['errors'] ?? [];
    } elseif ($errorCode === 429) {
        // Rate limit exceeded
        $retryAfter = $errorResponse['retry_after'] ?? 60;
    }
} catch (QreditException $e) {
    // General SDK error
    Log::error('Qredit SDK error: ' . $e->getMessage());
}
```

### Handling Validation Errors

```php
try {
    $payment = Qredit::createPayment([
        'amount' => -10, // Invalid amount
    ]);
} catch (QreditApiException $e) {
    if ($e->getCode() === 422) {
        $errors = $e->getResponse()['errors'] ?? [];

        foreach ($errors as $field => $messages) {
            Log::error("Validation error for {$field}: " . implode(', ', $messages));
        }

        // Return to user
        return back()->withErrors($errors)->withInput();
    }
}
```

## Testing

### Unit Testing

```php
use Qredit\LaravelQredit\Facades\Qredit;
use Mockery;

public function test_can_create_payment()
{
    // Mock the Qredit facade
    Qredit::shouldReceive('createPayment')
        ->once()
        ->with(Mockery::type('array'))
        ->andReturn([
            'reference' => 'PR_TEST_123',
            'checkoutUrl' => 'https://checkout.qredit.com/test',
            'status' => 'PENDING',
        ]);

    // Your test code
    $result = $this->paymentService->processPayment($orderData);

    $this->assertEquals('PR_TEST_123', $result['reference']);
}
```

### Integration Testing

```php
use Qredit\LaravelQredit\Qredit;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

public function test_payment_creation_integration()
{
    $mockClient = new MockClient([
        MockResponse::make([
            'reference' => 'PR_123',
            'checkoutUrl' => 'https://checkout.test',
            'status' => 'PENDING',
        ], 201),
    ]);

    $qredit = new Qredit('test-api-key', true, true); // Skip auth
    $qredit->getConnector()->withMockClient($mockClient);

    $result = $qredit->createPayment([
        'amount' => 100.00,
        'currencyCode' => 'ILS',
    ]);

    $this->assertEquals('PR_123', $result['reference']);
}
```

## Advanced Usage

### Custom Request Timeout

```php
// Set custom timeout for specific operations
config(['qredit.timeout.request' => 120]); // 120 seconds

$payment = Qredit::createPayment($largeDataSet);
```

### Retry Configuration

```php
// Configure retry attempts
config([
    'qredit.retry.max_attempts' => 5,
    'qredit.retry.delay' => 1000, // milliseconds
]);
```

### Using Different Environments

```php
// Force sandbox mode
$qredit = new Qredit('api-key', true);

// Force production mode
$qredit = new Qredit('api-key', false);

// Use different API key
$qredit = new Qredit('different-api-key');
```

### Direct Connector Usage

```php
use Qredit\LaravelQredit\Connectors\QreditConnector;
use Qredit\LaravelQredit\Requests\PaymentRequests\CreatePaymentRequest;

$connector = new QreditConnector('api-key', true);
$request = new CreatePaymentRequest($paymentData);
$response = $connector->send($request);

if ($response->successful()) {
    $data = $response->json();
}
```

### Custom Message IDs

```php
use Qredit\LaravelQredit\Helpers\MessageIdGenerator;

// Generate custom message ID
$messageId = MessageIdGenerator::generate('payment.create', [
    'ref' => 'ORDER-123'
]);

// Use in request
$request = new CreatePaymentRequest($data);
$request->withMessageId($messageId);
```

### Batch Operations

```php
// Process multiple payments
$payments = collect($orders)->map(function ($order) {
    return Qredit::createPayment([
        'amount' => $order->total,
        'currencyCode' => 'ILS',
        'clientReference' => $order->reference,
        // ...
    ]);
});

// Process with rate limiting
$payments = collect($orders)->map(function ($order) {
    sleep(1); // Rate limit: 1 request per second
    return Qredit::createPayment([...]);
});
```

### Logging and Debugging

```php
// Enable debug mode
config(['qredit.debug' => true]);

// Custom log channel
config(['qredit.logging.channel' => 'payments']);

// Log all requests
Event::listen('qredit.request.*', function ($event, $data) {
    Log::channel('payments')->info('Qredit Request', $data);
});
```

## Response Formats

### Successful Response

```json
{
    "success": true,
    "data": {
        "reference": "PR_123456",
        "status": "COMPLETED",
        "amount": 100.00,
        "currency": "ILS"
    },
    "message": "Payment processed successfully",
    "timestamp": 1704067200
}
```

### Error Response

```json
{
    "success": false,
    "error": {
        "code": "VALIDATION_ERROR",
        "message": "The given data was invalid",
        "errors": {
            "amount": ["The amount must be greater than 0"],
            "currency": ["The selected currency is invalid"]
        }
    },
    "timestamp": 1704067200
}
```

## Rate Limiting

The API has the following rate limits:

- **Sandbox**: 100 requests per minute
- **Production**: 1000 requests per minute

Handle rate limiting gracefully:

```php
try {
    $result = Qredit::createPayment($data);
} catch (QreditApiException $e) {
    if ($e->getCode() === 429) {
        $retryAfter = $e->getResponse()['retry_after'] ?? 60;

        // Queue for retry
        ProcessPayment::dispatch($data)
            ->delay(now()->addSeconds($retryAfter));
    }
}
```

## Support

For support and questions:

- **GitHub Issues**: [github.com/PaltechHub/qredit-laravel/issues](https://github.com/PaltechHub/qredit-laravel/issues)
- **Email**: support@qredit.com
- **Documentation**: [docs.qredit.com](https://docs.qredit.com)

## License

The Qredit Laravel SDK is open-source software licensed under the [MIT license](LICENSE.md).
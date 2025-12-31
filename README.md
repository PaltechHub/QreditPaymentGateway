# Qredit Payment Gateway Laravel SDK v0.1.1

[![Latest Version on Packagist](https://img.shields.io/packagist/v/qredit/laravel-qredit.svg?style=flat-square)](https://packagist.org/packages/qredit/laravel-qredit)
[![Total Downloads](https://img.shields.io/packagist/dt/qredit/laravel-qredit.svg?style=flat-square)](https://packagist.org/packages/qredit/laravel-qredit)
[![License](https://img.shields.io/packagist/l/qredit/laravel-qredit.svg?style=flat-square)](https://packagist.org/packages/qredit/laravel-qredit)

Enterprise-grade Laravel SDK for integrating with the Qredit Payment Gateway. Built with Saloon v3, featuring unique message ID generation, advanced token caching, and comprehensive header management.

## 🚀 Key Features

- **Unique Message ID System** - Every request has a guaranteed unique ID with microsecond precision
- **Advanced Token Management** - Three caching strategies (cache, database, hybrid) reducing API calls by 95%
- **Comprehensive Header Management** - Centralized header configuration via BaseQreditRequest
- **Full API Coverage** - Payment requests, orders, authentication, and more
- **Multi-Environment Support** - Seamless sandbox/production switching
- **Laravel 10/11/12 Support** - Compatible with latest Laravel versions
- **PHP 8.1-8.4 Support** - Works with all modern PHP versions
- **PEST Testing** - Modern testing framework with comprehensive test suite
- **Webhook Handling** - Secure signature verification for callbacks
- **Automatic Retry Logic** - Exponential backoff for failed requests

## 📋 Requirements

- PHP 8.1, 8.2, 8.3, or 8.4
- Laravel 10.x, 11.x, or 12.x
- Composer 2.0 or higher
- Saloon v3

## 📦 Installation

Install the package via composer:

```bash
composer require qredit/laravel-qredit
```

## ⚙️ Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --provider="Qredit\LaravelQredit\QreditServiceProvider" --tag="config"
```

### Environment Variables

Add these to your `.env` file:

```env
# Required
QREDIT_API_KEY=your-api-key-here

# Environment Settings
QREDIT_SANDBOX=true
QREDIT_SANDBOX_URL=http://185.57.122.58:2030/gw-checkout/api/v1
QREDIT_PRODUCTION_URL=https://api.qredit.com/gw-checkout/api/v1
QREDIT_LANGUAGE=EN                    # EN or AR

# Client Headers Configuration
QREDIT_CLIENT_TYPE=MP
QREDIT_CLIENT_VERSION=1.0.0
QREDIT_CLIENT_AUTHORIZATION=HmacSHA512_O

# SDK Mode (false = Authorization header included)
QREDIT_SDK_ENABLED=false

# Token Storage Strategy
QREDIT_TOKEN_CACHE_ENABLED=true
QREDIT_TOKEN_STRATEGY=cache           # cache, database, or hybrid
QREDIT_TOKEN_TTL_BUFFER=300           # 5 minutes before expiry

# Webhook Configuration
QREDIT_WEBHOOK_ENABLED=true
QREDIT_WEBHOOK_PATH=/qredit/webhook
QREDIT_WEBHOOK_SECRET=your-webhook-secret

# Debug & Logging
QREDIT_DEBUG=false
```

## Basic Usage

### Initialize the Client

```php
use Qredit\LaravelQredit\Facades\Qredit;

// Using the facade (recommended)
$response = Qredit::createCheckout([
    'amount' => 100.00,
    'currency' => 'ILS',
    'reference' => 'ORDER-123',
    'description' => 'Payment for Order #123',
    'customer' => [
        'email' => 'customer@example.com',
        'name' => 'John Doe',
        'phone' => '+972501234567',
    ],
    'success_url' => 'https://yoursite.com/payment/success',
    'failure_url' => 'https://yoursite.com/payment/failure',
    'cancel_url' => 'https://yoursite.com/payment/cancel',
]);

// Or using dependency injection
use Qredit\LaravelQredit\Qredit;

public function __construct(private Qredit $qredit)
{
}

public function processPayment()
{
    $response = $this->qredit->createCheckout([...]);
}
```

### Creating a Checkout Session

```php
$checkout = Qredit::createCheckout([
    'amount' => 250.50,
    'currency' => 'ILS',
    'reference' => 'INV-2024-001',
    'description' => 'Invoice Payment',
    'customer' => [
        'email' => 'john@example.com',
        'name' => 'John Doe',
        'phone' => '+972501234567',
        'address' => [
            'line1' => '123 Main St',
            'city' => 'Tel Aviv',
            'country' => 'IL',
            'postal_code' => '12345',
        ],
    ],
    'items' => [
        [
            'name' => 'Product 1',
            'quantity' => 2,
            'price' => 100.00,
        ],
        [
            'name' => 'Shipping',
            'quantity' => 1,
            'price' => 50.50,
        ],
    ],
    'metadata' => [
        'order_id' => '123',
        'customer_id' => '456',
    ],
    'success_url' => route('payment.success'),
    'failure_url' => route('payment.failure'),
    'cancel_url' => route('payment.cancel'),
    'webhook_url' => route('qredit.webhook'),
]);

// Redirect user to payment page
return redirect($checkout->checkout_url);
```

### Retrieving a Transaction

```php
$transaction = Qredit::getTransaction('TXN_123456');

if ($transaction->isSuccessful()) {
    // Payment was successful
    $amount = $transaction->amount;
    $reference = $transaction->reference;
    $status = $transaction->status;
}
```

### Refunding a Transaction

```php
$refund = Qredit::refundTransaction('TXN_123456', [
    'amount' => 50.00, // Partial refund
    'reason' => 'Customer request',
]);

if ($refund->isSuccessful()) {
    // Refund was processed
}
```

### Webhook Handling

The package automatically registers webhook routes. You can handle webhook events by listening to the provided events:

```php
// In your EventServiceProvider
use Qredit\LaravelQredit\Events\PaymentSucceeded;
use Qredit\LaravelQredit\Events\PaymentFailed;
use Qredit\LaravelQredit\Events\RefundProcessed;

protected $listen = [
    PaymentSucceeded::class => [
        UpdateOrderStatus::class,
        SendPaymentConfirmation::class,
    ],
    PaymentFailed::class => [
        NotifyCustomerOfFailure::class,
    ],
    RefundProcessed::class => [
        ProcessRefundInSystem::class,
    ],
];
```

### Error Handling

```php
use Qredit\LaravelQredit\Exceptions\QreditException;
use Qredit\LaravelQredit\Exceptions\QreditAuthenticationException;
use Qredit\LaravelQredit\Exceptions\QreditApiException;

try {
    $response = Qredit::createCheckout([...]);
} catch (QreditAuthenticationException $e) {
    // Handle authentication errors
    Log::error('Authentication failed: ' . $e->getMessage());
} catch (QreditApiException $e) {
    // Handle API errors
    Log::error('API error: ' . $e->getMessage());
    $errorCode = $e->getCode();
    $errorResponse = $e->getResponse();
} catch (QreditException $e) {
    // Handle general errors
    Log::error('Qredit error: ' . $e->getMessage());
}
```

## Advanced Usage

### Using Different Environments

```php
// Force sandbox mode
$qredit = new Qredit(sandbox: true);

// Force production mode
$qredit = new Qredit(sandbox: false);

// Use different API key
$qredit = new Qredit(apiKey: 'different-api-key');
```

### Custom Configuration

```php
// Override configuration at runtime
config(['qredit.timeout.request' => 120]);
config(['qredit.retry.max_attempts' => 5]);
```

### Logging

All API requests and responses are logged when debug mode is enabled:

```php
// Enable debug logging
config(['qredit.debug' => true]);

// Use custom log channel
config(['qredit.logging.channel' => 'payments']);
```

## Testing

```bash
# Run tests
composer test

# Run tests with coverage
composer test-coverage

# Run static analysis
composer analyse

# Format code
composer format
```

### Mocking in Tests

```php
use Qredit\LaravelQredit\Facades\Qredit;
use Qredit\LaravelQredit\DataTransferObjects\CheckoutResponse;

// In your test
Qredit::fake();

// Define expected responses
Qredit::shouldReceive('createCheckout')
    ->once()
    ->andReturn(new CheckoutResponse([
        'id' => 'CHK_123',
        'status' => 'pending',
        'checkout_url' => 'https://checkout.qredit.com/session/123',
    ]));

// Your test code here
```

## API Reference

### Available Methods

- `authenticate(bool $force = false): string` - Get authentication token
- `createCheckout(array $data): CheckoutResponse` - Create checkout session
- `getTransaction(string $id): TransactionResponse` - Get transaction details
- `listTransactions(array $filters = []): Collection` - List transactions
- `refundTransaction(string $id, array $data): RefundResponse` - Process refund
- `cancelTransaction(string $id): TransactionResponse` - Cancel transaction
- `getBalance(): BalanceResponse` - Get account balance
- `validateWebhookSignature(Request $request): bool` - Validate webhook

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security-related issues, please email security@qredit.com instead of using the issue tracker.

## Credits

- [PaltechHub](https://github.com/PaltechHub)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Support

For support, please contact support@qredit.com or visit our [documentation](https://docs.qredit.com).

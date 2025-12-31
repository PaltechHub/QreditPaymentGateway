<?php

/**
 * Qredit Laravel SDK - Basic Usage Examples
 *
 * This file demonstrates how to use the Qredit payment gateway SDK
 * in your Laravel application.
 */

use Qredit\LaravelQredit\Facades\Qredit;
use Qredit\LaravelQredit\Exceptions\QreditException;
use Qredit\LaravelQredit\Exceptions\QreditAuthenticationException;
use Qredit\LaravelQredit\Exceptions\QreditApiException;

// ===================================================================
// 1. AUTHENTICATION
// ===================================================================

/**
 * The SDK automatically handles authentication when you make API calls.
 * However, you can manually authenticate if needed:
 */
try {
    $token = Qredit::authenticate();
    echo "Authentication successful. Token: " . $token;
} catch (QreditAuthenticationException $e) {
    echo "Authentication failed: " . $e->getMessage();
}

// Force re-authentication (useful if token expires)
$token = Qredit::authenticate(force: true);

// ===================================================================
// 2. CREATING PAYMENT REQUESTS
// ===================================================================

/**
 * Create a simple payment request
 */
try {
    $paymentRequest = Qredit::createPaymentRequest([
        'amount' => 150.00,
        'currencyCode' => 'ILS',
        'clientReference' => 'ORDER-' . uniqid(),
        'description' => 'Payment for Order #12345',
        'customerDetails' => [
            'customerName' => 'John Doe',
            'customerEmail' => 'john@example.com',
            'customerPhone' => '+972501234567',
            'customerAddress' => '123 Main St, Tel Aviv',
        ],
        'callbackUrls' => [
            'successUrl' => 'https://yoursite.com/payment/success',
            'failureUrl' => 'https://yoursite.com/payment/failure',
            'cancelUrl' => 'https://yoursite.com/payment/cancel',
        ],
        'items' => [
            [
                'name' => 'Product A',
                'quantity' => 2,
                'price' => 50.00,
                'description' => 'Premium subscription'
            ],
            [
                'name' => 'Shipping',
                'quantity' => 1,
                'price' => 50.00,
                'description' => 'Express delivery'
            ]
        ],
    ]);

    echo "Payment request created successfully!\n";
    echo "Reference: " . $paymentRequest['reference'] . "\n";
    echo "Checkout URL: " . $paymentRequest['checkoutUrl'] . "\n";

    // Redirect user to payment page
    // return redirect($paymentRequest['checkoutUrl']);

} catch (QreditApiException $e) {
    echo "API Error: " . $e->getMessage() . "\n";
    echo "Error Code: " . $e->getCode() . "\n";
    print_r($e->getResponse());
}

// ===================================================================
// 3. LISTING PAYMENT REQUESTS
// ===================================================================

/**
 * List payment requests with filters
 */
$paymentRequests = Qredit::listPaymentRequests([
    'dateFrom' => '01/12/2024',
    'dateTo' => '31/12/2024',
    'status' => 'SUCCESS',
    'currencyCode' => 'ILS',
    'max' => 50,
    'offset' => 0,
]);

foreach ($paymentRequests['data'] as $request) {
    echo "Payment: {$request['reference']} - Status: {$request['status']} - Amount: {$request['amount']}\n";
}

// ===================================================================
// 4. GETTING PAYMENT REQUEST DETAILS
// ===================================================================

/**
 * Get details of a specific payment request
 */
$paymentId = 'PR_123456789';

try {
    $payment = Qredit::getPaymentRequest($paymentId);

    echo "Payment Details:\n";
    echo "Reference: " . $payment['reference'] . "\n";
    echo "Status: " . $payment['status'] . "\n";
    echo "Amount: " . $payment['amount'] . " " . $payment['currencyCode'] . "\n";
    echo "Customer: " . $payment['customerName'] . "\n";
    echo "Created: " . $payment['createdDate'] . "\n";

    // Check if payment is successful
    if ($payment['status'] === 'SUCCESS') {
        echo "Payment completed successfully!\n";
        // Update your order status in database
    }

} catch (QreditApiException $e) {
    if ($e->getCode() === 404) {
        echo "Payment request not found\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}

// ===================================================================
// 5. UPDATING PAYMENT REQUESTS
// ===================================================================

/**
 * Update an existing payment request
 */
try {
    $updatedPayment = Qredit::updatePaymentRequest($paymentId, [
        'description' => 'Updated payment description',
        'amount' => 200.00,
        'customerDetails' => [
            'customerEmail' => 'newemail@example.com',
        ],
    ]);

    echo "Payment request updated successfully\n";

} catch (QreditApiException $e) {
    echo "Failed to update: " . $e->getMessage() . "\n";
}

// ===================================================================
// 6. CANCELING PAYMENT REQUESTS
// ===================================================================

/**
 * Cancel a payment request
 */
try {
    $result = Qredit::deletePaymentRequest($paymentId);
    echo "Payment request canceled successfully\n";
} catch (QreditApiException $e) {
    echo "Failed to cancel: " . $e->getMessage() . "\n";
}

// ===================================================================
// 7. CREATING ORDERS
// ===================================================================

/**
 * Create an order (alternative to payment request)
 */
$order = Qredit::createOrder([
    'clientReference' => 'ORDER-' . time(),
    'amount' => 300.00,
    'currencyCode' => 'ILS',
    'description' => 'Purchase Order #54321',
    'customerDetails' => [
        'name' => 'Jane Smith',
        'email' => 'jane@example.com',
        'phone' => '+972509876543',
    ],
    'billingAddress' => [
        'line1' => '456 King St',
        'city' => 'Jerusalem',
        'countryCode' => 'IL',
        'postalCode' => '91000',
    ],
    'shippingAddress' => [
        'line1' => '789 Queen St',
        'city' => 'Haifa',
        'countryCode' => 'IL',
        'postalCode' => '31000',
    ],
    'metadata' => [
        'internal_order_id' => '12345',
        'customer_type' => 'premium',
    ],
]);

echo "Order created: " . $order['orderReference'] . "\n";

// ===================================================================
// 8. WEBHOOK HANDLING
// ===================================================================

/**
 * Handle incoming webhooks from Qredit
 */

// In your webhook controller:
class QreditWebhookController extends Controller
{
    public function handle(Request $request)
    {
        try {
            // Get the signature from headers
            $signature = $request->header('X-Qredit-Signature');

            // Process the webhook
            $result = Qredit::processWebhook(
                $request->all(),
                $signature
            );

            // Handle different event types
            switch ($result['event']) {
                case 'payment.success':
                    $this->handlePaymentSuccess($result['data']);
                    break;

                case 'payment.failed':
                    $this->handlePaymentFailed($result['data']);
                    break;

                case 'payment.refunded':
                    $this->handlePaymentRefunded($result['data']);
                    break;

                case 'order.completed':
                    $this->handleOrderCompleted($result['data']);
                    break;

                default:
                    Log::info('Unknown webhook event', $result);
            }

            return response()->json(['status' => 'success']);

        } catch (QreditException $e) {
            Log::error('Webhook processing failed', [
                'error' => $e->getMessage(),
                'payload' => $request->all(),
            ]);

            return response()->json(['error' => 'Invalid webhook'], 400);
        }
    }

    private function handlePaymentSuccess($data)
    {
        // Update order status in database
        Order::where('reference', $data['clientReference'])
            ->update(['status' => 'paid']);

        // Send confirmation email
        Mail::to($data['customerEmail'])
            ->send(new PaymentConfirmation($data));
    }

    private function handlePaymentFailed($data)
    {
        // Log failed payment
        Log::warning('Payment failed', $data);

        // Notify customer
        Mail::to($data['customerEmail'])
            ->send(new PaymentFailed($data));
    }
}

// ===================================================================
// 9. ERROR HANDLING
// ===================================================================

/**
 * Comprehensive error handling example
 */
try {
    $payment = Qredit::createPaymentRequest([
        'amount' => 100.00,
        'currencyCode' => 'ILS',
        // ... other data
    ]);

} catch (QreditAuthenticationException $e) {
    // Authentication issues
    Log::error('Authentication failed: ' . $e->getMessage());
    // Try to re-authenticate
    Qredit::authenticate(force: true);

} catch (QreditApiException $e) {
    // API errors
    $errorCode = $e->getCode();
    $errorResponse = $e->getResponse();

    switch ($errorCode) {
        case 400:
            // Bad request - validation error
            Log::error('Validation error', $errorResponse);
            break;

        case 404:
            // Resource not found
            Log::error('Resource not found');
            break;

        case 429:
            // Rate limit exceeded
            Log::warning('Rate limit exceeded, retrying in 60 seconds');
            sleep(60);
            break;

        case 500:
            // Server error
            Log::critical('Qredit server error', $errorResponse);
            break;
    }

} catch (QreditException $e) {
    // General Qredit errors
    Log::error('Qredit error: ' . $e->getMessage());

} catch (\Exception $e) {
    // Unexpected errors
    Log::critical('Unexpected error: ' . $e->getMessage());
}

// ===================================================================
// 10. TESTING IN SANDBOX
// ===================================================================

/**
 * Testing with sandbox environment
 */

// Force sandbox mode for testing
$qreditSandbox = new \Qredit\LaravelQredit\Qredit(
    apiKey: config('qredit.api_key'),
    sandbox: true
);

// Test card numbers for sandbox
$testCards = [
    'success' => '4111111111111111',
    'declined' => '4000000000000002',
    'insufficient_funds' => '4000000000000069',
];

// Create test payment
$testPayment = $qreditSandbox->createPaymentRequest([
    'amount' => 10.00,
    'currencyCode' => 'ILS',
    'clientReference' => 'TEST-' . uniqid(),
    'customerDetails' => [
        'customerName' => 'Test User',
        'customerEmail' => 'test@example.com',
    ],
    'testMode' => true,
]);

echo "Test payment created: " . $testPayment['checkoutUrl'] . "\n";

// ===================================================================
// 11. BATCH OPERATIONS
// ===================================================================

/**
 * Process multiple payments in batch
 */
$orders = [
    ['id' => '001', 'amount' => 100.00, 'email' => 'user1@example.com'],
    ['id' => '002', 'amount' => 200.00, 'email' => 'user2@example.com'],
    ['id' => '003', 'amount' => 300.00, 'email' => 'user3@example.com'],
];

$results = [];

foreach ($orders as $order) {
    try {
        $payment = Qredit::createPaymentRequest([
            'amount' => $order['amount'],
            'currencyCode' => 'ILS',
            'clientReference' => 'BATCH-' . $order['id'],
            'customerDetails' => [
                'customerEmail' => $order['email'],
            ],
        ]);

        $results[] = [
            'order_id' => $order['id'],
            'status' => 'success',
            'payment_url' => $payment['checkoutUrl'],
        ];

    } catch (QreditException $e) {
        $results[] = [
            'order_id' => $order['id'],
            'status' => 'failed',
            'error' => $e->getMessage(),
        ];
    }
}

// Process results
foreach ($results as $result) {
    echo "Order {$result['order_id']}: {$result['status']}\n";
}

// ===================================================================
// 12. CONFIGURATION AT RUNTIME
// ===================================================================

/**
 * Override configuration dynamically
 */

// Change language for specific request
config(['qredit.language' => 'AR']);
$arabicPayment = Qredit::createPaymentRequest([/* ... */]);

// Change back to English
config(['qredit.language' => 'EN']);

// Increase timeout for large operations
config(['qredit.timeout.request' => 120]);

// Enable debug logging temporarily
config(['qredit.debug' => true]);
$debugPayment = Qredit::createPaymentRequest([/* ... */]);
config(['qredit.debug' => false]);
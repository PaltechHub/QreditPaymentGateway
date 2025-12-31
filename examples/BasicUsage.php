<?php

/**
 * Qredit Laravel SDK v0.1.0 - Basic Usage Examples
 *
 * This file demonstrates how to use the Qredit payment gateway SDK
 * in your Laravel application with all v0.1.0 features.
 *
 * Key Features Demonstrated:
 * - Unique Message ID system with microsecond precision
 * - Token management with caching strategies
 * - Comprehensive header management via configuration
 * - Payment requests and orders management
 * - Authorization header handling (HmacSHA512_O)
 */

use Qredit\LaravelQredit\Facades\Qredit;
use Qredit\LaravelQredit\Exceptions\QreditException;
use Qredit\LaravelQredit\Exceptions\QreditAuthenticationException;
use Qredit\LaravelQredit\Exceptions\QreditApiException;

// ===================================================================
// CONFIGURATION OVERVIEW
// ===================================================================

/**
 * The SDK uses these key configuration values from config/qredit.php:
 *
 * 'client' => [
 *     'type' => 'MP',                    // Client-Type header
 *     'version' => '1.0.0',               // Client-Version header
 *     'authorization' => 'HmacSHA512_O',  // Authorization header
 * ],
 * 'sdk_enabled' => false,  // When false, Authorization header is included
 * 'token_storage' => [
 *     'strategy' => 'cache',  // Options: cache, database, hybrid
 * ]
 *
 * Every request automatically includes a unique message ID with format:
 * {type_prefix}_{microseconds}{random} (e.g., pr_create_123456789abc)
 */

// ===================================================================
// 1. AUTHENTICATION & TOKEN MANAGEMENT
// ===================================================================

/**
 * The SDK automatically handles authentication with intelligent token caching.
 * Token caching strategies reduce API calls by 95%.
 */
try {
    // Automatic authentication happens on first API call
    // Token is cached based on your configured strategy:
    // - 'cache': Uses Laravel's cache (Redis/Memcached)
    // - 'database': Stores in database
    // - 'hybrid': Cache with database fallback

    $token = Qredit::authenticate();
    echo "Authentication successful. Token cached based on strategy: "
         . config('qredit.token_storage.strategy') . "\n";

    // Force re-authentication (bypasses cache)
    $token = Qredit::authenticate(force: true);

} catch (QreditAuthenticationException $e) {
    echo "Authentication failed: " . $e->getMessage() . "\n";
    // Check your API key and Authorization header configuration
}

// ===================================================================
// 2. CREATING PAYMENT REQUESTS (with Unique Message IDs)
// ===================================================================

/**
 * Create a payment request with automatic unique message ID generation
 * Message ID format: pr_create_{microseconds}{random}
 */
try {
    $paymentRequest = Qredit::createPaymentRequest([
        'amount' => 150.00,
        'currencyCode' => config('qredit.default_currency', 'ILS'),
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
            'webhookUrl' => 'https://yoursite.com/qredit/webhook',
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
        'metadata' => [
            'order_id' => '12345',
            'customer_type' => 'premium',
        ],
    ]);

    echo "Payment request created successfully!\n";
    echo "Reference: " . $paymentRequest['reference'] . "\n";
    echo "Message ID (unique): " . $paymentRequest['msgId'] . "\n";
    echo "Checkout URL: " . $paymentRequest['checkoutUrl'] . "\n";

    // The request included these headers automatically:
    // - Client-Type: MP
    // - Client-Version: 1.0.0
    // - Authorization: HmacSHA512_O (if sdk_enabled = false)
    // - Accept-Language: EN

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
 * Message ID format: pr_list_{microseconds}{random}
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
    echo "Payment: {$request['reference']} - ";
    echo "Status: {$request['status']} - ";
    echo "Amount: {$request['amount']} - ";
    echo "MsgId: {$request['msgId']}\n";  // Each has unique message ID
}

// ===================================================================
// 4. GETTING PAYMENT REQUEST DETAILS
// ===================================================================

/**
 * Get details of a specific payment request
 * Message ID format: pr_get_{microseconds}{random}
 */
$paymentId = 'PR_123456789';

try {
    $payment = Qredit::getPaymentRequest($paymentId);

    echo "Payment Details:\n";
    echo "Reference: " . $payment['reference'] . "\n";
    echo "Message ID: " . $payment['msgId'] . "\n";  // Unique for this request
    echo "Status: " . $payment['status'] . "\n";
    echo "Amount: " . $payment['amount'] . " " . $payment['currencyCode'] . "\n";
    echo "Customer: " . $payment['customerName'] . "\n";
    echo "Created: " . $payment['createdDate'] . "\n";

    if ($payment['status'] === 'SUCCESS') {
        echo "Payment completed successfully!\n";
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
 * Message ID format: pr_update_{microseconds}{random}
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
    echo "New Message ID: " . $updatedPayment['msgId'] . "\n";

} catch (QreditApiException $e) {
    echo "Failed to update: " . $e->getMessage() . "\n";
}

// ===================================================================
// 6. CANCELING PAYMENT REQUESTS
// ===================================================================

/**
 * Cancel a payment request with optional reason
 * Message ID format: pr_cancel_{microseconds}{random}
 */
try {
    // Cancel without reason
    $result = Qredit::cancelPaymentRequest($paymentId);

    // Or cancel with reason
    $result = Qredit::cancelPaymentRequest($paymentId, 'Customer request');

    echo "Payment request canceled successfully\n";
    echo "Cancellation Message ID: " . $result['msgId'] . "\n";

} catch (QreditApiException $e) {
    echo "Failed to cancel: " . $e->getMessage() . "\n";
}

// ===================================================================
// 7. CREATING ORDERS
// ===================================================================

/**
 * Create an order (alternative to payment request)
 * Message ID format: ord_create_{microseconds}{random}
 */
$order = Qredit::createOrder([
    'orderReference' => 'ORDER-' . time(),
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
echo "Order Message ID: " . $order['msgId'] . "\n";

// ===================================================================
// 8. ORDER MANAGEMENT
// ===================================================================

/**
 * List orders with filters
 * Message ID format: ord_list_{microseconds}{random}
 */
$orders = Qredit::listOrders([
    'dateFrom' => '01/12/2024',
    'dateTo' => '31/12/2024',
    'status' => 'PENDING',
    'max' => 20,
]);

foreach ($orders['data'] as $order) {
    echo "Order: {$order['orderReference']} - MsgId: {$order['msgId']}\n";
}

/**
 * Get order details
 * Message ID format: ord_get_{microseconds}{random}
 */
$orderId = 'ORD_123456789';
$orderDetails = Qredit::getOrder($orderId);
echo "Order Status: " . $orderDetails['status'] . "\n";

/**
 * Update order
 * Message ID format: ord_update_{microseconds}{random}
 */
$updatedOrder = Qredit::updateOrder($orderId, [
    'description' => 'Updated order description',
    'amount' => 350.00,
]);

/**
 * Cancel order with reason
 * Message ID format: ord_cancel_{microseconds}{random}
 */
$cancelResult = Qredit::cancelOrder($orderId, 'Out of stock');
echo "Order canceled with MsgId: " . $cancelResult['msgId'] . "\n";

// ===================================================================
// 9. WEBHOOK HANDLING WITH SIGNATURE VERIFICATION
// ===================================================================

/**
 * Handle incoming webhooks from Qredit
 * Webhooks include signature verification for security
 */

// In your webhook controller:
class QreditWebhookController extends Controller
{
    public function handle(Request $request)
    {
        try {
            // Get the signature from headers
            $signature = $request->header('X-Qredit-Signature');

            // Process the webhook with signature verification
            $result = Qredit::processWebhook(
                $request->all(),
                $signature
            );

            // Each webhook has a unique message ID
            Log::info('Webhook received', [
                'msgId' => $result['msgId'],
                'event' => $result['event'],
            ]);

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

        // Each action has unique message ID in logs
        Log::info('Payment success processed', [
            'msgId' => $data['msgId'],
            'reference' => $data['clientReference'],
        ]);
    }
}

// ===================================================================
// 10. TOKEN CACHING STRATEGIES
// ===================================================================

/**
 * The SDK intelligently caches tokens based on your configuration
 */

// Strategy 1: Cache (default) - Best for single server
config(['qredit.token_storage.strategy' => 'cache']);
// Uses Laravel's cache driver (Redis, Memcached, etc.)
// Fastest performance for single-server deployments

// Strategy 2: Database - Best for multi-server
config(['qredit.token_storage.strategy' => 'database']);
// Stores tokens in database for shared access across servers
// Ensures consistency in distributed environments

// Strategy 3: Hybrid - Best of both worlds
config(['qredit.token_storage.strategy' => 'hybrid']);
// Primary: Cache for speed
// Fallback: Database for reliability
// Ideal for high-performance distributed systems

// Token TTL buffer (default 300 seconds)
config(['qredit.token_storage.ttl_buffer' => 300]);
// Refreshes token 5 minutes before expiry
// Prevents authentication failures during requests

// ===================================================================
// 11. HEADER CONFIGURATION
// ===================================================================

/**
 * Headers are configured in config/qredit.php and automatically
 * included in every request via BaseQreditRequest
 */

// Client headers (always included)
echo "Client-Type: " . config('qredit.client.type') . "\n";        // MP
echo "Client-Version: " . config('qredit.client.version') . "\n";  // 1.0.0

// Authorization header (conditional)
if (!config('qredit.sdk_enabled')) {
    echo "Authorization: " . config('qredit.client.authorization') . "\n"; // HmacSHA512_O
    echo "Authorization header is included in all requests\n";
} else {
    echo "SDK mode enabled - Authorization header not included\n";
}

// Language header
echo "Accept-Language: " . config('qredit.language') . "\n";  // EN or AR

// ===================================================================
// 12. ERROR HANDLING WITH RETRY LOGIC
// ===================================================================

/**
 * The SDK includes automatic retry with exponential backoff
 */
try {
    // Automatic retry is configured in config/qredit.php
    // 'retry' => [
    //     'enabled' => true,
    //     'max_attempts' => 3,
    //     'delay' => 1000,  // milliseconds
    // ]

    $payment = Qredit::createPaymentRequest([
        'amount' => 100.00,
        'currencyCode' => 'ILS',
        'clientReference' => 'RETRY-TEST-' . uniqid(),
        // ... other data
    ]);

    // If request fails, SDK automatically retries up to 3 times
    // with exponential backoff (1s, 2s, 4s)

} catch (QreditAuthenticationException $e) {
    // Authentication issues - token expired or invalid
    Log::error('Authentication failed: ' . $e->getMessage());

    // Force re-authentication to get new token
    Qredit::authenticate(force: true);

} catch (QreditApiException $e) {
    // API errors after retry attempts exhausted
    $errorCode = $e->getCode();

    switch ($errorCode) {
        case 400:
            Log::error('Validation error after retries', $e->getResponse());
            break;

        case 429:
            Log::warning('Rate limit exceeded even after retries');
            break;

        case 500:
            Log::critical('Server error persisted through retries');
            break;
    }
}

// ===================================================================
// 13. TESTING IN SANDBOX ENVIRONMENT
// ===================================================================

/**
 * Testing with sandbox environment
 */

// Ensure sandbox mode is enabled
config(['qredit.sandbox' => true]);

// Create test payment with unique message ID
$testPayment = Qredit::createPaymentRequest([
    'amount' => 10.00,
    'currencyCode' => 'ILS',
    'clientReference' => 'TEST-' . uniqid(),
    'description' => 'Sandbox test payment',
    'customerDetails' => [
        'customerName' => 'Test User',
        'customerEmail' => 'test@example.com',
    ],
    'testMode' => true,
]);

echo "Test payment created in sandbox\n";
echo "Message ID: " . $testPayment['msgId'] . "\n";
echo "Checkout URL: " . $testPayment['checkoutUrl'] . "\n";

// Test webhook signature verification
$testWebhookPayload = [
    'event' => 'payment.success',
    'msgId' => 'webhook_' . microtime(true),
    'data' => [
        'paymentId' => $testPayment['id'],
        'status' => 'SUCCESS',
    ],
];

$webhookSecret = config('qredit.webhook.secret');
$testSignature = hash_hmac('sha512', json_encode($testWebhookPayload), $webhookSecret);

// Verify signature
$isValid = Qredit::verifyWebhookSignature($testWebhookPayload, $testSignature);
echo "Webhook signature valid: " . ($isValid ? 'Yes' : 'No') . "\n";

// ===================================================================
// 14. MONITORING & DEBUGGING
// ===================================================================

/**
 * Enable debug mode to log all API requests/responses
 */

// Enable debug logging
config(['qredit.debug' => true]);
config(['qredit.logging.level' => 'debug']);

// All requests will now be logged with full details
$debugPayment = Qredit::createPaymentRequest([
    'amount' => 50.00,
    'currencyCode' => 'ILS',
    'clientReference' => 'DEBUG-' . uniqid(),
    // ... other data
]);

// Check logs for:
// - Request headers (including Client-Type, Client-Version, Authorization)
// - Request body with unique message ID
// - Response data
// - Token caching operations
// - Retry attempts

// Disable debug mode for production
config(['qredit.debug' => false]);

// ===================================================================
// 15. UNIQUE MESSAGE ID TRACKING
// ===================================================================

/**
 * Every API request has a unique message ID for tracking
 * Format: {type}_{microseconds}{random}
 */

// Message ID prefixes by request type:
$messageIdPrefixes = [
    'Authentication' => 'auth_token_',
    'Create Payment' => 'pr_create_',
    'Get Payment' => 'pr_get_',
    'Update Payment' => 'pr_update_',
    'Cancel Payment' => 'pr_cancel_',
    'List Payments' => 'pr_list_',
    'Create Order' => 'ord_create_',
    'Get Order' => 'ord_get_',
    'Update Order' => 'ord_update_',
    'Cancel Order' => 'ord_cancel_',
    'List Orders' => 'ord_list_',
];

// Track requests using message IDs
$payment = Qredit::createPaymentRequest([
    'amount' => 100.00,
    'currencyCode' => 'ILS',
    'clientReference' => 'TRACK-' . uniqid(),
]);

// Log with message ID for tracking
Log::info('Payment request created', [
    'msgId' => $payment['msgId'],  // e.g., pr_create_173567890abc
    'reference' => $payment['clientReference'],
    'amount' => $payment['amount'],
]);

// Use message ID to track request through entire lifecycle
// - API logs
// - Webhook callbacks
// - Error tracking
// - Customer support

echo "Payment tracking ID: " . $payment['msgId'] . "\n";
echo "Use this ID to track the request in all systems\n";
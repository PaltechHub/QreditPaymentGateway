<?php

/**
 * Qredit Laravel SDK — Basic Usage Examples
 *
 * Demonstrates the SDK's new, per-tenant API with live HMAC SHA512 signing
 * (merchant guide §7). Every outgoing request is signed automatically in
 * BaseQreditRequest::boot() — you never need to compute signatures yourself.
 *
 * Key concepts:
 *  - Qredit::make([...]) builds a per-tenant client (api_key + secret_key).
 *    Call it once per HTTP request / per queue job so credentials follow the
 *    current channel / company.
 *  - The literal "HmacSHA512_O" is the gateway's required Authorization scheme
 *    prefix (doc §7 step 6). It lives in config/qredit.php ('signing.scheme').
 *  - Signature case (lower vs upper hex) is configurable per-tenant via
 *    'signature_case' — the gateway is strict.
 */

use Qredit\LaravelQredit\Exceptions\QreditApiException;
use Qredit\LaravelQredit\Exceptions\QreditAuthenticationException;
use Qredit\LaravelQredit\Exceptions\QreditException;
use Qredit\LaravelQredit\Facades\Qredit as QreditFacade;
use Qredit\LaravelQredit\Qredit;

// ===========================================================================
// 1. BUILDING A CLIENT — SINGLE TENANT
// ===========================================================================

/**
 * Simple case: one set of credentials in config/qredit.php.
 * The facade resolves to a singleton built from config.
 */
$token = QreditFacade::authenticate();
echo "Token (first 20 chars): ".substr($token, 0, 20)."...\n";

// ===========================================================================
// 2. BUILDING A CLIENT — PER-TENANT (SAAS / MULTI-CHANNEL)
// ===========================================================================

/**
 * In a multi-tenant app (Bagisto SAAS, multi-channel, etc.) credentials live
 * per tenant — not in .env. Build a fresh client per request using Qredit::make().
 *
 * Options:
 *   api_key         (required)  Public API key.
 *   secret_key      (required)  Secret API key — used for HMAC SHA512.
 *   sandbox         (bool)      true → UAT, false → production.
 *   language        (string)    'EN' | 'AR' for Accept-Language.
 *   auth_scheme     (string)    Override the Authorization prefix (default from config).
 *   signature_case  (string)    'lower' | 'upper' — gateway is case-sensitive.
 *   skip_auth       (bool)      true → don't auto-authenticate on construct.
 */
$client = Qredit::make([
    'api_key' => 'EdVfej9DvSSHBCtn0DDUviHxmXMj3t0bodQqjeNXF0',
    'secret_key' => 'B9E0236B77E5C16B1F3540265920C7E0C541622E66C4F76FBC53BC990F11E496',
    'sandbox' => true,
    'language' => 'EN',
    'signature_case' => 'lower',
]);

// ===========================================================================
// 3. REGISTER AN ORDER (merchant doc §3)
// ===========================================================================

try {
    $order = $client->createOrder([
        'amountCents' => 3200,
        'currencyCode' => 'ILS',
        'deliveryNeeded' => 'true',
        'deliveryCostCents' => 200,
        'shippingProviderCode' => 'DELV2',
        'clientReference' => 'ORDER-'.uniqid(),
        'customerInfo' => [
            'name' => 'Mahmood Ali',
            'phone' => '+970599882288',
            'email' => 'mahmood.ali@example.com',
            'idNumber' => '8923982392',
        ],
        'shippingData' => [
            'countryCode' => 'PSE',
            'cityCode' => '50',
            'areaCode' => '50',
            'street' => "Jemma'in",
            'postalCode' => '970',
            'building' => 'Bab wad',
            'apartment' => '01',
            'floor' => '07',
        ],
        'items' => [
            ['name' => 'xbox', 'amountCents' => 2000, 'quantity' => 1, 'sku' => '21111'],
            ['name' => 'playstation', 'amountCents' => 1200, 'quantity' => 1, 'sku' => '1221'],
        ],
    ]);

    $orderReference = $order['records'][0]['orderReference'] ?? null;
    echo "Order created: {$orderReference}\n";
} catch (QreditApiException $e) {
    echo "createOrder failed: ".$e->getMessage()." (code {$e->getCode()})\n";
    print_r($e->getResponse());
}

// ===========================================================================
// 4. CREATE A PAYMENT REQUEST (merchant doc §4)
// ===========================================================================

try {
    $payment = $client->createPayment([
        'orderReference' => $orderReference,
        'amountCents' => 3200,
        'currencyCode' => 'ILS',
        'lockOrderWhenPaid' => true,
        'paymentChannels' => [
            ['code' => 'CSAB'],
            ['code' => 'esadad_biller'],
            ['code' => 'NC-QR'],
        ],
        'customerInfo' => [
            'name' => 'Mahmood Ali',
            'phoneNumber' => '+970599882288',
            'email' => 'mahmood.ali@example.com',
            'idNumber' => '8923982392',
        ],
        'billingData' => [
            'countryCode' => 'PSE',
            'city' => '50',
            'area' => '50',
            'street' => "Jemma'in",
            'postalCode' => '970',
            'state' => 'West Bank',
            'building' => 'Bab wad',
            'apartment' => '01',
            'floor' => '07',
        ],
    ]);

    $paymentReference = $payment['records'][0]['reference'] ?? null;
    $checkoutUrl = $payment['records'][0]['url'] ?? null;
    echo "Payment request: {$paymentReference}\n";
    echo "Checkout URL:    {$checkoutUrl}\n";
} catch (QreditApiException $e) {
    echo "createPayment failed: ".$e->getMessage()."\n";
}

// ===========================================================================
// 5. LIST / GET / UPDATE / CANCEL
// ===========================================================================

// List payments — dateFrom/dateTo default to last 30 days if you omit them.
$list = $client->listPayments([
    'max' => 20,
    'offset' => 0,
    'status' => 'PENDING_PAYMENT',
]);

foreach ($list['records'] ?? [] as $pr) {
    echo "Payment: {$pr['reference']} status={$pr['paymentRequestStatus']}\n";
}

// Get a single payment by reference (implemented via list + filter).
$single = $client->getPayment($paymentReference);

// Update a payment.
$client->updatePayment($paymentReference, [
    'amountCents' => 4000,
    'currencyCode' => 'ILS',
]);

// Cancel.
$client->deletePayment($paymentReference, 'Customer cancelled');

// Same shape for orders:
$client->listOrders(['orderStatus' => 'NEW']);
$client->getOrder($orderReference);
$client->updateOrder($orderReference, ['deliveryNeeded' => 'false']);
$client->cancelOrder($orderReference, 'Out of stock');

// ===========================================================================
// 6. SPECIALIZED PAYMENT-REQUEST CALLS
// ===========================================================================

// Generate a QR for a payment request.
$qr = $client->generateQR([
    'reference' => $paymentReference,
    'productCode' => 'NC-QR',
    'merchantChannelMedia' => 'SCREEN_ELECTRONIC_WEBSITE',
]);

// Calculate fees before charging.
$fees = $client->calculateFees([
    'reference' => $paymentReference,
    'productCode' => 'CSAB',
]);

// Initiate a payment for a specific channel.
$init = $client->initPayment([
    'reference' => $paymentReference,
    'productCode' => 'CSAB',
]);

// ===========================================================================
// 7. TRANSACTIONS + CLEARING
// ===========================================================================

$txns = $client->listTransactions([
    'transactionStatus' => 'SUCCESS',
    'currencyCode' => 'ILS',
]);

// Change the clearing status of a payment.
$client->changeClearingStatus([
    'encodedId' => 'txn_encoded_id',
    'clearingStatus' => 'CLEARED',
    'statusReason' => 'Settled with provider',
]);

// ===========================================================================
// 8. CUSTOMERS
// ===========================================================================

$customers = $client->listCustomers([
    'email' => 'mahmood.ali@',
    'max' => 10,
]);

// ===========================================================================
// 9. WEBHOOK HANDLING
// ===========================================================================

/**
 * In your webhook controller, verify the inbound signature against the SAME
 * secret you used to sign outgoing requests. Per merchant doc the callback is
 * signed identically to requests (HMAC SHA512 over sorted payload values,
 * key = md5(secret + msgId)).
 */
class QreditWebhookController
{
    public function handle(\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse
    {
        $client = Qredit::make([
            'api_key' => config('qredit.api_key'),
            'secret_key' => config('qredit.secret_key'),
            'sandbox' => config('qredit.sandbox'),
            'skip_auth' => true,
        ]);

        try {
            $result = $client->processWebhook(
                $request->all(),
                $request->header('Authorization'),
            );
        } catch (QreditException $e) {
            \Log::warning('Qredit webhook rejected', ['error' => $e->getMessage()]);

            return response()->json(['status' => 'invalid'], 401);
        }

        match ($result['event']) {
            'payment.succeeded', 'transaction' => $this->markPaid($result['data']),
            'payment.failed' => $this->markFailed($result['data']),
            default => \Log::info('Unhandled Qredit event', $result),
        };

        return response()->json(['status' => 'RECEIVED']);
    }

    protected function markPaid(array $data): void { /* your logic */ }

    protected function markFailed(array $data): void { /* your logic */ }
}

// ===========================================================================
// 10. ERROR HANDLING
// ===========================================================================

try {
    $client->createPayment(['amountCents' => -10]); // Invalid
} catch (QreditAuthenticationException $e) {
    // 401 — token expired or invalid. The client auto-refreshes once on 401
    // internally (sendWithRetry), so if you see this the second attempt also failed.
} catch (QreditApiException $e) {
    // Every non-2xx response. $e->getResponse() carries the decoded body.
    $body = $e->getResponse();
    echo "Gateway said: {$body['message']} (code {$body['code']})\n";
} catch (QreditException $e) {
    // SDK-level errors (missing credentials, etc).
}

// ===========================================================================
// 11. CLI TESTER (qredit:call) — THE POSTMAN REPLACEMENT
// ===========================================================================

/**
 * Because every request needs an HMAC signature, Postman / Insomnia aren't
 * practical. The SDK ships an Artisan command that signs + sends any endpoint:
 *
 *   # List supported methods:
 *   php artisan qredit:call --list
 *
 *   # Live call:
 *   php artisan qredit:call auth \
 *       --api-key=$QREDIT_API_KEY --secret-key=$QREDIT_SECRET_KEY --sandbox
 *
 *   # Dry run (prints signature + request without sending):
 *   php artisan qredit:call create-order --dry-run \
 *       --secret-key=... \
 *       --payload='{"amountCents":3200,"currencyCode":"ILS"}'
 *
 *   # Payload from file:
 *   php artisan qredit:call create-payment \
 *       --payload-file=./tests/fixtures/payment.json
 *
 *   # Flip signature hex case (gateway is strict):
 *   php artisan qredit:call auth --case=upper --api-key=... --secret-key=...
 */

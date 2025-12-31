<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array createPayment(array $data)
 * @method static array getPayment(string $paymentRequestId)
 * @method static array updatePayment(string $paymentRequestId, array $data)
 * @method static bool deletePayment(string $paymentRequestId)
 * @method static array listPayments(array $query = [])
 * @method static array createOrder(array $data)
 * @method static array registerOrder(array $data)
 * @method static array getOrder(string $orderId)
 * @method static array updateOrder(string $orderId, array $data)
 * @method static array cancelOrder(string $orderId, ?string $reason = null)
 * @method static array listOrders(array $query = [])
 * @method static string authenticate(bool $force = false)
 * @method static void clearCachedToken()
 * @method static bool verifyWebhookSignature(string $payload, string $signature)
 * @method static array processWebhook(array $payload, ?string $signature = null)
 * @method static bool isSandbox()
 * @method static string getApiUrl()
 * @method static \Qredit\LaravelQredit\Connectors\QreditConnector getConnector()
 *
 * @see \Qredit\LaravelQredit\Qredit
 */
class Qredit extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return \Qredit\LaravelQredit\Qredit::class;
    }
}
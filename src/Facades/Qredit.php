<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Facade resolves to QreditManager — which routes every call through the current
 * tenant's client, and exposes forTenant() / fake() for explicit cases.
 *
 * Single-tenant usage:
 *   Qredit::createOrder([...]);
 *
 * Multi-tenant / queue usage (always pass explicit tenant):
 *   Qredit::forTenant('shop-b')->createOrder([...]);
 *
 * Tenancy contracts (bind your own in a service provider):
 *   - Qredit\LaravelQredit\Contracts\CredentialProvider
 *   - Qredit\LaravelQredit\Contracts\TenantResolver
 *
 * @method static \Qredit\LaravelQredit\Qredit current()
 * @method static \Qredit\LaravelQredit\Qredit forTenant(?string $tenantId)
 * @method static \Qredit\LaravelQredit\Contracts\CredentialProvider credentials()
 * @method static \Qredit\LaravelQredit\Contracts\TenantResolver tenants()
 * @method static \Qredit\LaravelQredit\QreditManager fake(\Qredit\LaravelQredit\Qredit|array $fakes)
 * @method static void clearFakes()
 * @method static void flush()
 *
 * Direct delegations to the current tenant's client (via __call):
 * @method static string authenticate(bool $force = false)
 * @method static void clearCachedToken()
 * @method static array createPayment(array $data)
 * @method static array getPayment(string $paymentRequestReference)
 * @method static array updatePayment(string $paymentRequestReference, array $data)
 * @method static array deletePayment(string $paymentRequestReference, ?string $reason = null)
 * @method static array listPayments(array $query = [])
 * @method static array generateQR(array $query)
 * @method static array calculateFees(array $data)
 * @method static array initPayment(array $data)
 * @method static array createOrder(array $data)
 * @method static array registerOrder(array $data)
 * @method static array getOrder(string $orderReference)
 * @method static array updateOrder(string $orderReference, array $data)
 * @method static array cancelOrder(string $orderReference, ?string $reason = null)
 * @method static array listOrders(array $query = [])
 * @method static array listCustomers(array $filters = [])
 * @method static array listTransactions(array $filters = [])
 * @method static array changeClearingStatus(array $data)
 * @method static array listProducts(array $query = [])
 * @method static array listLookups(array $query = [])
 * @method static bool verifyWebhookSignature(array $payload, string $authorizationHeader)
 * @method static array processWebhook(array $payload, ?string $authorizationHeader = null)
 * @method static bool isSandbox()
 * @method static string getApiUrl()
 * @method static \Qredit\LaravelQredit\Connectors\QreditConnector getConnector()
 *
 * @see \Qredit\LaravelQredit\QreditManager
 */
class Qredit extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Qredit\LaravelQredit\QreditManager::class;
    }
}

<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Contracts;

use Qredit\LaravelQredit\Tenancy\QreditRedirectUrls;

/**
 * Persists where to send the customer after the Qredit payment widget finishes.
 *
 * Keyed by Qredit's payment_reference (the value returned by createPayment).
 * Implementations can use cache, DB, or any combination. The package ships:
 *
 *   - HybridRedirectUrlStore   (default — DB primary + cache read-through)
 *   - CacheRedirectUrlStore    (cache-only — for lightweight host apps)
 *   - DatabaseRedirectUrlStore (DB-only — no cache layer)
 *
 * Host apps swap in their own implementation by binding this contract:
 *
 *     $this->app->bind(RedirectUrlStore::class, MyStore::class);
 */
interface RedirectUrlStore
{
    /**
     * Save the URLs for the given payment reference. Idempotent — calling
     * twice with the same reference overwrites.
     */
    public function remember(string $paymentReference, QreditRedirectUrls $urls, ?string $tenantId = null): void;

    /**
     * Look up previously-stored URLs. Returns null when nothing is stored
     * or the entry has expired.
     */
    public function resolve(string $paymentReference, ?string $tenantId = null): ?QreditRedirectUrls;

    /**
     * Drop the stored URLs (eg. after the customer lands on the success page).
     */
    public function forget(string $paymentReference, ?string $tenantId = null): void;
}

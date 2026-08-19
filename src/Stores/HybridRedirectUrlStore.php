<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Stores;

use Qredit\LaravelQredit\Contracts\RedirectUrlStore;
use Qredit\LaravelQredit\Tenancy\QreditRedirectUrls;

/**
 * Default store. Writes through to both database (persistent) and cache (hot).
 * Reads hit cache first; on miss, falls through to DB and warms the cache.
 *
 * Pick this when you want both crash safety (DB) and low-latency lookups
 * (cache). The two sub-stores are injected so tests can swap them.
 */
class HybridRedirectUrlStore implements RedirectUrlStore
{
    public function __construct(
        protected CacheRedirectUrlStore $cache,
        protected DatabaseRedirectUrlStore $database,
    ) {}

    public function remember(string $paymentReference, QreditRedirectUrls $urls, ?string $tenantId = null): void
    {
        $this->database->remember($paymentReference, $urls, $tenantId);
        $this->cache->remember($paymentReference, $urls, $tenantId);
    }

    public function resolve(string $paymentReference, ?string $tenantId = null): ?QreditRedirectUrls
    {
        $urls = $this->cache->resolve($paymentReference, $tenantId);

        if ($urls !== null) {
            return $urls;
        }

        $urls = $this->database->resolve($paymentReference, $tenantId);

        if ($urls !== null) {
            $this->cache->remember($paymentReference, $urls, $tenantId);
        }

        return $urls;
    }

    public function forget(string $paymentReference, ?string $tenantId = null): void
    {
        $this->cache->forget($paymentReference, $tenantId);
        $this->database->forget($paymentReference, $tenantId);
    }
}

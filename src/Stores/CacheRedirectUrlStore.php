<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Stores;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Qredit\LaravelQredit\Contracts\RedirectUrlStore;
use Qredit\LaravelQredit\Tenancy\QreditRedirectUrls;

/**
 * Cache-only redirect-URL store. Keyed by tenant + payment_reference. TTL
 * defaults to the payment-request expiration window.
 *
 * Fine for stateless apps that don't want a migration. The default binding
 * is HybridRedirectUrlStore — use this directly only when a DB write-path
 * is undesirable.
 */
class CacheRedirectUrlStore implements RedirectUrlStore
{
    public function __construct(
        protected CacheRepository $cache,
        protected int $ttlMinutes = 60,
    ) {}

    public function remember(string $paymentReference, QreditRedirectUrls $urls, ?string $tenantId = null): void
    {
        if ($urls->isEmpty()) {
            return;
        }

        $this->cache->put(
            self::cacheKey($paymentReference, $tenantId),
            $urls->toArray(),
            now()->addMinutes($this->ttlMinutes),
        );
    }

    public function resolve(string $paymentReference, ?string $tenantId = null): ?QreditRedirectUrls
    {
        $payload = $this->cache->get(self::cacheKey($paymentReference, $tenantId));

        if (! is_array($payload)) {
            return null;
        }

        $urls = QreditRedirectUrls::fromArray($payload);

        return $urls->isEmpty() ? null : $urls;
    }

    public function forget(string $paymentReference, ?string $tenantId = null): void
    {
        $this->cache->forget(self::cacheKey($paymentReference, $tenantId));
    }

    public static function cacheKey(string $paymentReference, ?string $tenantId): string
    {
        return 'qredit:redirect_urls:'.($tenantId ?? '_default').':'.$paymentReference;
    }
}

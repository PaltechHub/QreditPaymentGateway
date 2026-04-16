<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Tenancy;

use Illuminate\Http\Request;
use Qredit\LaravelQredit\Contracts\TenantResolver;

/**
 * Tenant = leftmost subdomain. E.g. "shop-b.example.com" → "shop-b".
 * Works for Stancl-Tenancy-style deployments.
 */
class SubdomainTenantResolver implements TenantResolver
{
    public function __construct(protected string $rootDomain) {}

    public function currentTenantId(?Request $request = null): ?string
    {
        $request ??= request();
        $host = $request->getHost();

        if ($host === $this->rootDomain || ! str_ends_with($host, '.'.$this->rootDomain)) {
            return null;
        }

        $subdomain = substr($host, 0, -strlen($this->rootDomain) - 1);

        return $subdomain !== '' ? $subdomain : null;
    }

    public function tenantIdFromWebhook(Request $request): ?string
    {
        // Webhooks typically hit a shared domain; fall back to the `tenant`
        // path param the route macro provides.
        return $request->route('tenant') ?? $this->currentTenantId($request);
    }
}

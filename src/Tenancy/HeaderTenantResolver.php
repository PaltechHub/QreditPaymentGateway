<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Tenancy;

use Illuminate\Http\Request;
use Qredit\LaravelQredit\Contracts\TenantResolver;

/**
 * Tenant = value of a specific request header. Useful for API-first apps and
 * mobile apps that pass `X-Tenant-Id` on every request.
 */
class HeaderTenantResolver implements TenantResolver
{
    public function __construct(protected string $header = 'X-Tenant-Id') {}

    public function currentTenantId(?Request $request = null): ?string
    {
        return ($request ?? request())->header($this->header) ?: null;
    }

    public function tenantIdFromWebhook(Request $request): ?string
    {
        return $request->header($this->header)
            ?: $request->route('tenant');
    }
}

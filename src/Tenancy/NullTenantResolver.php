<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Tenancy;

use Illuminate\Http\Request;
use Qredit\LaravelQredit\Contracts\TenantResolver;

/**
 * Default resolver for single-tenant apps — always returns null so the
 * ConfigCredentialProvider falls back to the global .env values.
 */
class NullTenantResolver implements TenantResolver
{
    public function currentTenantId(?Request $request = null): ?string
    {
        return null;
    }

    public function tenantIdFromWebhook(Request $request): ?string
    {
        return null;
    }
}

<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Contracts;

use Illuminate\Http\Request;

/**
 * Teaches the SDK how to derive the current tenant id from a request.
 *
 * Every multi-tenant framework locates the tenant differently:
 *   - Stancl Tenancy:  subdomain
 *   - Spatie Multitenancy: domain lookup
 *   - Bagisto SAAS: channel hostname
 *   - bespoke: header / path parameter
 *
 * Bind one implementation per host app. The SDK ships with a handful of drop-in
 * resolvers (SubdomainTenantResolver, DomainTenantResolver, HeaderTenantResolver,
 * CallbackTenantResolver) for the common cases.
 */
interface TenantResolver
{
    /**
     * Return the current tenant id for the active HTTP request, or null when
     * no tenant context can be determined (e.g. CLI / queue without explicit id).
     */
    public function currentTenantId(?Request $request = null): ?string;

    /**
     * Return the tenant id for an inbound webhook. Separate from current() because
     * webhooks often encode the tenant in a URL parameter or custom header rather
     * than the host — and the SDK's built-in webhook controller needs this to
     * pick the right secret for signature verification.
     */
    public function tenantIdFromWebhook(Request $request): ?string;
}

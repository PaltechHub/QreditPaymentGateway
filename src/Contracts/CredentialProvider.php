<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Contracts;

use Qredit\LaravelQredit\Tenancy\QreditCredentials;

/**
 * Single integration point for multi-tenant consumers.
 *
 * Implement this contract in your host app (Bagisto / Stancl Tenancy / Spatie
 * Multitenancy / bespoke) and bind it in a service provider:
 *
 *     $this->app->bind(CredentialProvider::class, MyChannelCredentialProvider::class);
 *
 * The SDK asks for credentials every time a Qredit client is resolved. Pass the
 * tenant id explicitly in queue jobs; leave it null in HTTP handlers to use the
 * bound TenantResolver.
 */
interface CredentialProvider
{
    /**
     * Return the credentials for the specified tenant (or the current one if
     * $tenantId is null). Implementations MUST NOT read request state when a
     * non-null tenantId is given — that breaks queue-job usage.
     *
     * @throws \Qredit\LaravelQredit\Exceptions\QreditException when no credentials
     *         are configured for the resolved tenant.
     */
    public function credentialsFor(?string $tenantId = null): QreditCredentials;

    /**
     * Quick existence check. Used by "is the payment method available on this
     * tenant" decisions without triggering an exception path.
     */
    public function isConfiguredFor(?string $tenantId = null): bool;
}

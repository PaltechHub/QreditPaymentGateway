<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit;

use Qredit\LaravelQredit\Contracts\CredentialProvider;
use Qredit\LaravelQredit\Contracts\TenantResolver;
use Qredit\LaravelQredit\Exceptions\QreditException;

/**
 * Multi-tenant entry point for the SDK.
 *
 * The Qredit facade resolves to this manager. It owns:
 *   - a CredentialProvider (where do creds live for this tenant?)
 *   - a TenantResolver     (which tenant is "current" for this request?)
 *   - a per-tenant client cache (one `Qredit` instance per tenant per request)
 *
 * For single-tenant apps, default bindings make this transparent —
 * `Qredit::createOrder(...)` still "just works" reading from .env.
 *
 * For multi-tenant apps, `Qredit::forTenant('shop-b')->createOrder(...)`
 * explicitly targets a specific tenant (mandatory in queue jobs — never rely
 * on the resolver in background context).
 */
class QreditManager
{
    /** @var array<string, Qredit> */
    protected array $clients = [];

    /** @var array<string, Qredit>|null  Test faker slot. */
    protected ?array $fakeClients = null;

    public function __construct(
        protected CredentialProvider $credentials,
        protected TenantResolver $tenants,
    ) {}

    /**
     * Get (or build + cache) a client for the current HTTP-context tenant.
     */
    public function current(): Qredit
    {
        $tenantId = $this->tenants->currentTenantId();

        return $this->forTenant($tenantId);
    }

    /**
     * Get (or build + cache) a client for an explicit tenant id. Pass null to
     * use the global default credentials (the ConfigCredentialProvider behavior).
     *
     * Use this in queue jobs, console commands, and any code that might run
     * outside an HTTP request.
     */
    public function forTenant(?string $tenantId): Qredit
    {
        $cacheKey = $tenantId ?? '__default__';

        if ($this->fakeClients !== null) {
            return $this->fakeClients[$cacheKey] ?? $this->fakeClients['__default__'];
        }

        if (isset($this->clients[$cacheKey])) {
            return $this->clients[$cacheKey];
        }

        $creds = $this->credentials->credentialsFor($tenantId);

        $this->clients[$cacheKey] = Qredit::make($creds->toArray() + [
            'skip_auth' => true, // Lazy auth — don't hit /auth/token until first API call.
        ]);

        return $this->clients[$cacheKey];
    }

    public function credentials(): CredentialProvider
    {
        return $this->credentials;
    }

    public function tenants(): TenantResolver
    {
        return $this->tenants;
    }

    /**
     * Replace the cached client pool with fakes for testing. Accepts either a
     * single Qredit instance (applied to every tenant) or a map keyed by tenant id.
     *
     * @param  Qredit|array<string, Qredit>  $fakes
     */
    public function fake(Qredit|array $fakes): self
    {
        $this->fakeClients = is_array($fakes)
            ? array_merge(['__default__' => reset($fakes)], $fakes)
            : ['__default__' => $fakes];

        return $this;
    }

    public function clearFakes(): void
    {
        $this->fakeClients = null;
    }

    /**
     * Reset the client cache (useful between queue jobs in a long-running worker).
     */
    public function flush(): void
    {
        $this->clients = [];
    }

    /**
     * Delegate any unknown call to the current tenant's client. Lets existing
     * code keep writing `Qredit::createOrder(...)` without thinking about tenancy.
     *
     * @param  array<int, mixed>  $args
     */
    public function __call(string $method, array $args): mixed
    {
        $client = $this->current();

        if (! method_exists($client, $method)) {
            throw new QreditException("Method [{$method}] does not exist on Qredit client.");
        }

        return $client->{$method}(...$args);
    }
}

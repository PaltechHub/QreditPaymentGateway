<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Tenancy;

use Closure;
use Illuminate\Http\Request;
use Qredit\LaravelQredit\Contracts\TenantResolver;

/**
 * Escape hatch — wrap a closure for any resolution logic that doesn't fit the
 * built-in resolvers. Example: looking up a Bagisto channel by hostname.
 */
class CallbackTenantResolver implements TenantResolver
{
    /**
     * @param  Closure(?Request):?string  $currentCallback
     * @param  Closure(Request):?string|null  $webhookCallback  Defaults to $currentCallback.
     */
    public function __construct(
        protected Closure $currentCallback,
        protected ?Closure $webhookCallback = null,
    ) {}

    public function currentTenantId(?Request $request = null): ?string
    {
        return ($this->currentCallback)($request);
    }

    public function tenantIdFromWebhook(Request $request): ?string
    {
        return ($this->webhookCallback ?? $this->currentCallback)($request);
    }
}

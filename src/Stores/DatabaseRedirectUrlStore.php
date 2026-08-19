<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Stores;

use Qredit\LaravelQredit\Contracts\RedirectUrlStore;
use Qredit\LaravelQredit\Models\QreditPaymentRedirectUrl;
use Qredit\LaravelQredit\Tenancy\QreditRedirectUrls;

/**
 * Persistent redirect-URL store backed by `qredit_payment_redirect_urls`.
 * Survives cache flushes. URLs are stored encrypted (model cast).
 */
class DatabaseRedirectUrlStore implements RedirectUrlStore
{
    public function __construct(
        protected int $ttlMinutes = 60,
    ) {}

    public function remember(string $paymentReference, QreditRedirectUrls $urls, ?string $tenantId = null): void
    {
        if ($urls->isEmpty()) {
            return;
        }

        QreditPaymentRedirectUrl::query()->updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'payment_reference' => $paymentReference,
            ],
            [
                'success_url' => $urls->successUrl,
                'cancel_url' => $urls->cancelUrl,
                'failure_url' => $urls->failureUrl,
                'pending_url' => $urls->pendingUrl,
                'expires_at' => now()->addMinutes($this->ttlMinutes),
            ],
        );
    }

    public function resolve(string $paymentReference, ?string $tenantId = null): ?QreditRedirectUrls
    {
        $record = QreditPaymentRedirectUrl::query()
            ->where('tenant_id', $tenantId)
            ->where('payment_reference', $paymentReference)
            ->first();

        if ($record === null) {
            return null;
        }

        if ($record->expires_at !== null && $record->expires_at->isPast()) {
            $record->delete();

            return null;
        }

        $urls = new QreditRedirectUrls(
            successUrl: $record->success_url,
            cancelUrl: $record->cancel_url,
            failureUrl: $record->failure_url,
            pendingUrl: $record->pending_url,
        );

        return $urls->isEmpty() ? null : $urls;
    }

    public function forget(string $paymentReference, ?string $tenantId = null): void
    {
        QreditPaymentRedirectUrl::query()
            ->where('tenant_id', $tenantId)
            ->where('payment_reference', $paymentReference)
            ->delete();
    }
}

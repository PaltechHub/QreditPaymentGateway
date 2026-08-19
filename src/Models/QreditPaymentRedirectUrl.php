<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent model behind the `qredit_payment_redirect_urls` table used by the
 * Database and Hybrid redirect-URL stores. URLs are encrypted at rest.
 *
 * @property int $id
 * @property ?string $tenant_id
 * @property string $payment_reference
 * @property ?string $success_url
 * @property ?string $cancel_url
 * @property ?string $failure_url
 * @property ?string $pending_url
 * @property ?\Illuminate\Support\Carbon $expires_at
 */
class QreditPaymentRedirectUrl extends Model
{
    protected $table = 'qredit_payment_redirect_urls';

    protected $guarded = [];

    protected $casts = [
        'success_url' => 'encrypted',
        'cancel_url' => 'encrypted',
        'failure_url' => 'encrypted',
        'pending_url' => 'encrypted',
        'expires_at' => 'datetime',
    ];
}

<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The payment data.
     */
    public array $payment;

    /**
     * Create a new event instance.
     */
    public function __construct(array $payment)
    {
        $this->payment = $payment;
    }
}
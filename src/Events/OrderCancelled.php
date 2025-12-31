<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderCancelled
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The order data.
     */
    public array $order;

    /**
     * Create a new event instance.
     */
    public function __construct(array $order)
    {
        $this->order = $order;
    }
}
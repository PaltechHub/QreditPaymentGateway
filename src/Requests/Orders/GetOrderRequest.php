<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Requests\Orders;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetOrderRequest extends Request
{
    /**
     * The HTTP method of the request.
     */
    protected Method $method = Method::GET;

    /**
     * The order ID.
     */
    protected string $orderId;

    /**
     * Create a new get order request.
     */
    public function __construct(string $orderId)
    {
        $this->orderId = $orderId;
    }

    /**
     * Resolve the endpoint for the request.
     */
    public function resolveEndpoint(): string
    {
        return '/orders/' . $this->orderId;
    }
}
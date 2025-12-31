<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Requests\Orders;

use Saloon\Enums\Method;
use Qredit\LaravelQredit\Requests\BaseQreditRequest;
use Qredit\LaravelQredit\Traits\HasMessageId;

class GetOrderRequest extends BaseQreditRequest
{
    use HasMessageId;

    /**
     * The HTTP method of the request.
     */
    protected Method $method = Method::GET;

    /**

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

    /**
     * Default query parameters for the request.
     */
    protected function defaultQuery(): array
    {
        return [
            'msgId' => $this->generateMessageId(),
        ];
    }
}
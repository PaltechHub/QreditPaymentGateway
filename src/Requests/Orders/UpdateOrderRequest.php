<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Requests\Orders;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Contracts\Body\HasBody;
use Saloon\Traits\Body\HasJsonBody;

class UpdateOrderRequest extends Request implements HasBody
{
    use HasJsonBody;

    /**
     * The HTTP method of the request.
     */
    protected Method $method = Method::PUT;

    /**
     * The order ID.
     */
    protected string $orderId;

    /**
     * The update data.
     */
    protected array $data;

    /**
     * Create a new update order request.
     */
    public function __construct(string $orderId, array $data)
    {
        $this->orderId = $orderId;
        $this->data = $data;
    }

    /**
     * Resolve the endpoint for the request.
     */
    public function resolveEndpoint(): string
    {
        return '/orders/' . $this->orderId;
    }

    /**
     * Default body for the request.
     */
    protected function defaultBody(): array
    {
        return $this->data;
    }
}
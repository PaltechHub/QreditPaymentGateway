<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Requests\Orders;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Contracts\Body\HasBody;
use Saloon\Traits\Body\HasJsonBody;

class CreateOrderRequest extends Request implements HasBody
{
    use HasJsonBody;

    /**
     * The HTTP method of the request.
     */
    protected Method $method = Method::POST;

    /**
     * The order data.
     */
    protected array $data;

    /**
     * Create a new order request.
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Resolve the endpoint for the request.
     */
    public function resolveEndpoint(): string
    {
        return '/orders';
    }

    /**
     * Default body for the request.
     */
    protected function defaultBody(): array
    {
        return $this->data;
    }
}
<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Requests\Orders;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class ListOrdersRequest extends Request
{
    /**
     * The HTTP method of the request.
     */
    protected Method $method = Method::GET;

    /**
     * Query parameters.
     */
    protected array $query;

    /**
     * Create a new list orders request.
     */
    public function __construct(array $query = [])
    {
        $this->query = $query;
    }

    /**
     * Resolve the endpoint for the request.
     */
    public function resolveEndpoint(): string
    {
        return '/orders';
    }

    /**
     * Default query parameters.
     */
    protected function defaultQuery(): array
    {
        return $this->query;
    }
}
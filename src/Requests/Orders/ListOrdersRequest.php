<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Requests\Orders;

use Saloon\Enums\Method;
use Qredit\LaravelQredit\Requests\BaseQreditRequest;

class ListOrdersRequest extends BaseQreditRequest
{
    /**
     * The HTTP method of the request.
     */
    protected Method $method = Method::GET;

    /**
     * Query parameters.
     */
    protected array $queryParams;

    /**
     * Create a new list orders request.
     */
    public function __construct(array $query = [])
    {
        $this->queryParams = $query;
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
        return $this->queryParams;
    }
}
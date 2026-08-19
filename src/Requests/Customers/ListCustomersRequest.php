<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Requests\Customers;

use Saloon\Enums\Method;
use Qredit\LaravelQredit\Requests\BaseQreditRequest;
use Qredit\LaravelQredit\Traits\HasMessageId;

class ListCustomersRequest extends BaseQreditRequest
{
    use HasMessageId;

    /**
     * The HTTP method of the request.
     */
    protected Method $method = Method::GET;

    /**
     * Query parameters for filtering.
     */
    protected array $queryParams;

    /**
     * Create a new list customers request.
     */
    public function __construct(array $query = [])
    {
        $this->queryParams = $query;
        $this->messageIdType = 'customer.list';
    }

    /**
     * Resolve the endpoint for the request.
     */
    public function resolveEndpoint(): string
    {
        return '/customers';
    }

    /**
     * Default query parameters for the request.
     */
    protected function defaultQuery(): array
    {
        $defaults = [
            'msgId' => $this->generateMessageId(),
            'max' => $this->queryParams['max'] ?? 50,
            'offset' => $this->queryParams['offset'] ?? 0,
        ];

        // Add optional filters
        $optionalFields = [
            'name',
            'phone',
            'email',
            'idNumber',
            'sSearch',
            'orderColumnName',
            'orderDirection',
        ];

        foreach ($optionalFields as $field) {
            if (isset($this->queryParams[$field])) {
                $defaults[$field] = $this->queryParams[$field];
            }
        }

        return $defaults;
    }
}
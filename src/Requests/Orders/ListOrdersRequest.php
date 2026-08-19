<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Requests\Orders;

use Saloon\Enums\Method;
use Qredit\LaravelQredit\Requests\BaseQreditRequest;
use Qredit\LaravelQredit\Traits\HasMessageId;

class ListOrdersRequest extends BaseQreditRequest
{
    use HasMessageId;

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
        $this->messageIdType = 'order.list';
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
     *
     * dateFrom / dateTo are required by the gateway (swagger). We default to a
     * 30-day window in dd/MM/yyyy if the caller didn't supply them.
     */
    protected function defaultQuery(): array
    {
        $defaults = [
            'msgId' => $this->generateMessageId(),
            'dateFrom' => $this->queryParams['dateFrom'] ?? date('d/m/Y', strtotime('-30 days')),
            'dateTo' => $this->queryParams['dateTo'] ?? date('d/m/Y'),
            'max' => $this->queryParams['max'] ?? 50,
            'offset' => $this->queryParams['offset'] ?? 0,
        ];

        $optional = [
            'orderReference', 'clientReference', 'subCorporateId',
            'currencyCode', 'cityCode', 'areaCode',
            'customerName', 'customerPhone', 'customerEmail',
            'orderStatus', 'sSearch', 'orderColumnName', 'orderDirection',
        ];

        foreach ($optional as $field) {
            if (isset($this->queryParams[$field])) {
                $defaults[$field] = $this->queryParams[$field];
            }
        }

        return array_filter($defaults, fn ($v) => $v !== null);
    }
}
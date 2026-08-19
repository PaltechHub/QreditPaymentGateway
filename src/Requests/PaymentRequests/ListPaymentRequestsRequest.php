<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Requests\PaymentRequests;

use Saloon\Enums\Method;
use Qredit\LaravelQredit\Requests\BaseQreditRequest;
use Qredit\LaravelQredit\Traits\HasMessageId;

class ListPaymentRequestsRequest extends BaseQreditRequest
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
     * Create a new list payment requests request.
     */
    public function __construct(array $query = [])
    {
        $this->queryParams = $query;
        $this->messageIdType = 'payment.list';
    }

    /**
     * Resolve the endpoint for the request.
     */
    public function resolveEndpoint(): string
    {
        return '/paymentRequests';
    }

    /**
     * Default query parameters for the request.
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

        $optionalFields = [
            'reference',
            'initPayment',
            'orderReference',
            'clientReference',
            'subCorporateId',
            'currencyCode',
            'cityCode',
            'areaCode',
            'customerName',
            'customerPhone',
            'customerEmail',
            'status',
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
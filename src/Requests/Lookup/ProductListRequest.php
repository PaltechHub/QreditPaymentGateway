<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Requests\Lookup;

use Qredit\LaravelQredit\Requests\BaseQreditRequest;
use Qredit\LaravelQredit\Traits\HasMessageId;
use Saloon\Enums\Method;

/**
 * GET /gw-lookup/api/v1/productList
 *
 * Lists products by category type. Common category types:
 *   - DELIVERY         → shipping providers (Optimus, etc.)
 *   - PAYMENT_CHANNEL  → available payment channels (CSAB, NC-QR, NC-MPGS, etc.)
 */
class ProductListRequest extends BaseQreditRequest
{
    use HasMessageId;

    protected Method $method = Method::GET;

    protected array $queryParams;

    public function __construct(array $query = [])
    {
        $this->queryParams = $query;
        $this->messageIdType = 'lookup.productList';
    }

    public function resolveEndpoint(): string
    {
        return '/../../../gw-lookup/api/v1/productList';
    }

    protected function defaultQuery(): array
    {
        return array_filter([
            'msgId' => $this->generateMessageId(),
            'categoryType' => $this->queryParams['categoryType'] ?? 'PAYMENT_CHANNEL',
            'max' => $this->queryParams['max'] ?? 200,
            'offset' => $this->queryParams['offset'] ?? 0,
        ], fn ($v) => $v !== null);
    }
}

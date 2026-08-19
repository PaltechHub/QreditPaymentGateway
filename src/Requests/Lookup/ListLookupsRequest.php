<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Requests\Lookup;

use Qredit\LaravelQredit\Requests\BaseQreditRequest;
use Qredit\LaravelQredit\Traits\HasMessageId;
use Saloon\Enums\Method;

/**
 * GET /gw-lookup/api/v1/listLookups
 *
 * Lists lookup values by type. Common types:
 *   - MERCHANT_CATEGORY  → merchant classification codes
 *   - CITY / AREA        → geographic codes
 */
class ListLookupsRequest extends BaseQreditRequest
{
    use HasMessageId;

    protected Method $method = Method::GET;

    protected array $queryParams;

    public function __construct(array $query = [])
    {
        $this->queryParams = $query;
        $this->messageIdType = 'lookup.listLookups';
    }

    public function resolveEndpoint(): string
    {
        return '/../../../gw-lookup/api/v1/listLookups';
    }

    protected function defaultQuery(): array
    {
        return array_filter([
            'msgId' => $this->generateMessageId(),
            'type' => $this->queryParams['type'] ?? 'MERCHANT_CATEGORY',
            'max' => $this->queryParams['max'] ?? 200,
            'offset' => $this->queryParams['offset'] ?? 0,
        ], fn ($v) => $v !== null);
    }
}

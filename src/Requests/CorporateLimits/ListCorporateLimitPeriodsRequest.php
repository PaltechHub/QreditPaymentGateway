<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Requests\CorporateLimits;

use Qredit\LaravelQredit\Requests\BaseQreditRequest;
use Qredit\LaravelQredit\Traits\HasMessageId;
use Saloon\Enums\Method;

/**
 * GET /admin/corporateLimitPeriods — swagger. Lists corporate limit period usage.
 *
 * Optional: corporateId, corporateCode, limitSchemeId, limitSchemeCode,
 * periodType (DAILY | MONTHLY), interval (e.g. 2026-07-18 or 2026-07),
 * recordStatus (default ACTIVE), max (default 10), offset (default 0),
 * sort (id, interval, totalOrdersAmount, totalOrdersCount, recordStatus,
 * periodType, limitSchemeCode), dir (asc | desc).
 */
class ListCorporateLimitPeriodsRequest extends BaseQreditRequest
{
    use HasMessageId;

    protected Method $method = Method::GET;

    protected array $queryParams;

    public function __construct(array $query = [])
    {
        $this->queryParams = $query;
        $this->messageIdType = 'corporate.limitPeriods';
    }

    public function resolveEndpoint(): string
    {
        return '/admin/corporateLimitPeriods';
    }

    protected function defaultQuery(): array
    {
        $defaults = [
            'msgId' => $this->generateMessageId(),
            'max' => $this->queryParams['max'] ?? 10,
            'offset' => $this->queryParams['offset'] ?? 0,
        ];

        $optional = [
            'corporateId',
            'corporateCode',
            'limitSchemeId',
            'limitSchemeCode',
            'periodType',
            'interval',
            'recordStatus',
            'sort',
            'dir',
        ];

        foreach ($optional as $field) {
            if (isset($this->queryParams[$field])) {
                $defaults[$field] = $this->queryParams[$field];
            }
        }

        return $defaults;
    }
}

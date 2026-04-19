<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Requests\Orders;

use Qredit\LaravelQredit\Requests\BaseQreditRequest;
use Qredit\LaravelQredit\Traits\HasMessageId;
use Saloon\Enums\Method;

/**
 * GET /orders filtered by reference — emulates a "get one" against a swagger
 * that only exposes list. The gateway returns the matching record under `records[0]`.
 */
class GetOrderRequest extends BaseQreditRequest
{
    use HasMessageId;

    protected Method $method = Method::GET;

    protected string $orderReference;

    public function __construct(string $orderReference)
    {
        $this->orderReference = $orderReference;
        $this->messageIdType = 'order.get';
    }

    public function resolveEndpoint(): string
    {
        return '/orders';
    }

    protected function defaultQuery(): array
    {
        return [
            'msgId' => $this->generateMessageId(),
            'orderReference' => $this->orderReference,
            'dateFrom' => date('d/m/Y', strtotime('-1 year')),
            'dateTo' => date('d/m/Y', strtotime('+1 day')),
            'max' => 1,
            'offset' => 0,
        ];
    }
}

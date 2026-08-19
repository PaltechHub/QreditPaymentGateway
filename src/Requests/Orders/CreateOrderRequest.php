<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Requests\Orders;

use Qredit\LaravelQredit\Requests\BaseQreditRequest;
use Qredit\LaravelQredit\Traits\HasMessageId;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Traits\Body\HasJsonBody;

/**
 * POST /orders — merchant doc §3.
 */
class CreateOrderRequest extends BaseQreditRequest implements HasBody
{
    use HasJsonBody;
    use HasMessageId;

    protected Method $method = Method::POST;

    protected array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
        $this->messageIdType = 'order.create';
    }

    public function resolveEndpoint(): string
    {
        return '/orders';
    }

    protected function defaultBody(): array
    {
        return array_merge(
            ['msgId' => $this->generateMessageId()],
            $this->data,
        );
    }

    protected function getMessageIdContext(): array
    {
        if (isset($this->data['clientReference'])) {
            return ['ref' => $this->data['clientReference']];
        }

        return [];
    }
}

<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Requests\Orders;

use Qredit\LaravelQredit\Requests\BaseQreditRequest;
use Qredit\LaravelQredit\Traits\HasMessageId;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Traits\Body\HasJsonBody;

/**
 * PUT /orders — swagger.
 *
 * Body carries `orderReference`; no resource id in the URL.
 */
class UpdateOrderRequest extends BaseQreditRequest implements HasBody
{
    use HasJsonBody;
    use HasMessageId;

    protected Method $method = Method::PUT;

    protected string $orderReference;

    protected array $data;

    public function __construct(string $orderReference, array $data)
    {
        $this->orderReference = $orderReference;
        $this->data = $data;
        $this->messageIdType = 'order.update';
    }

    public function resolveEndpoint(): string
    {
        return '/orders';
    }

    protected function defaultBody(): array
    {
        return array_merge(
            [
                'msgId' => $this->generateMessageId(),
                'orderReference' => $this->orderReference,
            ],
            $this->data,
        );
    }
}

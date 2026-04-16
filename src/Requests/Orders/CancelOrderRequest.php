<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Requests\Orders;

use Qredit\LaravelQredit\Requests\BaseQreditRequest;
use Qredit\LaravelQredit\Traits\HasMessageId;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Traits\Body\HasJsonBody;

/**
 * DELETE /orders — swagger.
 *
 * Body: { msgId, orderReference, reason }.
 */
class CancelOrderRequest extends BaseQreditRequest implements HasBody
{
    use HasJsonBody;
    use HasMessageId;

    protected Method $method = Method::DELETE;

    protected string $orderReference;

    protected ?string $reason;

    public function __construct(string $orderReference, ?string $reason = null)
    {
        $this->orderReference = $orderReference;
        $this->reason = $reason;
        $this->messageIdType = 'order.cancel';
    }

    public function resolveEndpoint(): string
    {
        return '/orders';
    }

    protected function defaultBody(): array
    {
        $body = [
            'msgId' => $this->generateMessageId(),
            'orderReference' => $this->orderReference,
        ];

        if ($this->reason !== null) {
            $body['reason'] = $this->reason;
        }

        return $body;
    }
}

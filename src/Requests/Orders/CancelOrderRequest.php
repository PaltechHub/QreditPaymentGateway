<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Requests\Orders;

use Saloon\Enums\Method;
use Qredit\LaravelQredit\Requests\BaseQreditRequest;
use Saloon\Contracts\Body\HasBody;
use Saloon\Traits\Body\HasJsonBody;
use Qredit\LaravelQredit\Traits\HasMessageId;

class CancelOrderRequest extends BaseQreditRequest implements HasBody
{
    use HasJsonBody;
    use HasMessageId;

    /**
     * The HTTP method of the request.
     */
    protected Method $method = Method::DELETE;

    /**

    /**
     * The order ID.
     */
    protected string $orderId;

    /**
     * The cancellation reason.
     */
    protected ?string $reason;

    /**
     * Create a new cancel order request.
     */
    public function __construct(string $orderId, ?string $reason = null)
    {
        $this->orderId = $orderId;
        $this->reason = $reason;
    }

    /**
     * Resolve the endpoint for the request.
     */
    public function resolveEndpoint(): string
    {
        return '/orders/' . $this->orderId;
    }

    /**
     * Default body for the request.
     */
    protected function defaultBody(): array
    {
        $body = [
            'msgId' => $this->generateMessageId(),
            'transactionDate' => date('d/m/Y'),
        ];

        if ($this->reason !== null) {
            $body['reason'] = $this->reason;
        }

        return $body;
    }

}
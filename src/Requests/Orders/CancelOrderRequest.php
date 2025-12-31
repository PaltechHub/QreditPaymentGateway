<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Requests\Orders;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Contracts\Body\HasBody;
use Saloon\Traits\Body\HasJsonBody;

class CancelOrderRequest extends Request implements HasBody
{
    use HasJsonBody;

    /**
     * The HTTP method of the request.
     */
    protected Method $method = Method::POST;

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
        return '/orders/' . $this->orderId . '/cancel';
    }

    /**
     * Default body for the request.
     */
    protected function defaultBody(): array
    {
        $body = [];

        if ($this->reason !== null) {
            $body['reason'] = $this->reason;
        }

        return $body;
    }
}
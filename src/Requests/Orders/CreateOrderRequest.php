<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Requests\Orders;

use Saloon\Enums\Method;
use Qredit\LaravelQredit\Requests\BaseQreditRequest;
use Saloon\Contracts\Body\HasBody;
use Saloon\Traits\Body\HasJsonBody;
use Qredit\LaravelQredit\Traits\HasMessageId;

class CreateOrderRequest extends BaseQreditRequest implements HasBody
{
    use HasJsonBody;
    use HasMessageId;

    /**
     * The HTTP method of the request.
     */
    protected Method $method = Method::POST;

    /**

    /**
     * The order data.
     */
    protected array $data;

    /**
     * Create a new order request.
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Resolve the endpoint for the request.
     */
    public function resolveEndpoint(): string
    {
        return '/orders';
    }

    /**
     * Default body for the request.
     */
    protected function defaultBody(): array
    {
        $defaultData = [
            'msgId' => $this->generateMessageId(),
            'transactionDate' => date('d/m/Y'),
        ];

        return array_merge($defaultData, $this->data);
    }

    /**
     * Get context for message ID generation.
     */
    protected function getMessageIdContext(): array
    {
        // Include order reference if available for better tracking
        if (isset($this->data['orderReference'])) {
            return ['ref' => $this->data['orderReference']];
        }

        return [];
    }
}
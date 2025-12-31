<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Requests\PaymentRequests;

use Saloon\Enums\Method;
use Qredit\LaravelQredit\Requests\BaseQreditRequest;
use Saloon\Contracts\Body\HasBody;
use Saloon\Traits\Body\HasJsonBody;
use Qredit\LaravelQredit\Traits\HasMessageId;

class UpdatePaymentRequest extends BaseQreditRequest implements HasBody
{
    use HasJsonBody;
    use HasMessageId;

    /**
     * The HTTP method of the request.
     */
    protected Method $method = Method::PUT;

    /**

    /**
     * The payment request ID.
     */
    protected string $paymentRequestId;

    /**
     * The update data.
     */
    protected array $data;

    /**
     * Create a new update payment request.
     */
    public function __construct(string $paymentRequestId, array $data)
    {
        $this->paymentRequestId = $paymentRequestId;
        $this->data = $data;
    }

    /**
     * Resolve the endpoint for the request.
     */
    public function resolveEndpoint(): string
    {
        return '/paymentRequests/' . $this->paymentRequestId;
    }

    /**
     * Default body for the request.
     */
    protected function defaultBody(): array
    {
        return array_merge([
            'msgId' => $this->generateMessageId(),
            'transactionDate' => date('d/m/Y'),
        ], $this->data);
    }

}
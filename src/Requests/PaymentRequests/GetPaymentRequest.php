<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Requests\PaymentRequests;

use Saloon\Enums\Method;
use Qredit\LaravelQredit\Requests\BaseQreditRequest;
use Qredit\LaravelQredit\Traits\HasMessageId;

class GetPaymentRequest extends BaseQreditRequest
{
    use HasMessageId;

    /**
     * The HTTP method of the request.
     */
    protected Method $method = Method::GET;

    /**

    /**
     * The payment request ID.
     */
    protected string $paymentRequestId;

    /**
     * Create a new get payment request.
     */
    public function __construct(string $paymentRequestId)
    {
        $this->paymentRequestId = $paymentRequestId;
    }

    /**
     * Resolve the endpoint for the request.
     */
    public function resolveEndpoint(): string
    {
        return '/paymentRequests/' . $this->paymentRequestId;
    }

    /**
     * Default query parameters for the request.
     */
    protected function defaultQuery(): array
    {
        return [
            'msgId' => $this->generateMessageId(),
        ];
    }

}
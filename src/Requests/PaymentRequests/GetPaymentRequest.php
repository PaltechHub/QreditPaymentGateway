<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Requests\PaymentRequests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetPaymentRequest extends Request
{
    /**
     * The HTTP method of the request.
     */
    protected Method $method = Method::GET;

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
        return '/payment-requests/' . $this->paymentRequestId;
    }
}
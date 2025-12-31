<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Requests\PaymentRequests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class DeletePaymentRequest extends Request
{
    /**
     * The HTTP method of the request.
     */
    protected Method $method = Method::DELETE;

    /**
     * The payment request ID.
     */
    protected string $paymentRequestId;

    /**
     * Create a new delete payment request.
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
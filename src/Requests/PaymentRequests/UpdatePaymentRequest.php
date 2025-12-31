<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Requests\PaymentRequests;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Contracts\Body\HasBody;
use Saloon\Traits\Body\HasJsonBody;

class UpdatePaymentRequest extends Request implements HasBody
{
    use HasJsonBody;

    /**
     * The HTTP method of the request.
     */
    protected Method $method = Method::PUT;

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
        return '/payment-requests/' . $this->paymentRequestId;
    }

    /**
     * Default body for the request.
     */
    protected function defaultBody(): array
    {
        return $this->data;
    }
}
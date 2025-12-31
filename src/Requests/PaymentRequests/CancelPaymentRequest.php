<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Requests\PaymentRequests;

use Saloon\Enums\Method;
use Qredit\LaravelQredit\Requests\BaseQreditRequest;
use Saloon\Contracts\Body\HasBody;
use Saloon\Traits\Body\HasJsonBody;
use Qredit\LaravelQredit\Traits\HasMessageId;

class CancelPaymentRequest extends BaseQreditRequest implements HasBody
{
    use HasJsonBody;
    use HasMessageId;

    /**
     * The HTTP method of the request.
     */
    protected Method $method = Method::DELETE;

    /**

    /**
     * The payment request ID.
     */
    protected string $paymentRequestId;

    /**
     * Optional cancellation reason.
     */
    protected ?string $reason;

    /**
     * Create a new cancel payment request.
     */
    public function __construct(string $paymentRequestId, ?string $reason = null)
    {
        $this->paymentRequestId = $paymentRequestId;
        $this->reason = $reason;
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
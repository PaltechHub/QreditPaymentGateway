<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Requests\PaymentRequests;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Contracts\Body\HasBody;
use Saloon\Traits\Body\HasJsonBody;

class CreatePaymentRequest extends Request implements HasBody
{
    use HasJsonBody;

    /**
     * The HTTP method of the request.
     */
    protected Method $method = Method::POST;

    /**
     * The payment data.
     */
    protected array $data;

    /**
     * Create a new payment request.
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
        return '/paymentRequests';
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
     * Default headers for the request.
     */
    protected function defaultHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Client-Type' => 'MP',
            'Client-Version' => '1.0.0',
            'Accept-Language' => config('qredit.language', 'EN'),
        ];
    }

    /**
     * Generate a unique message ID for the request.
     */
    protected function generateMessageId(): string
    {
        return uniqid('pr_', true) . '_' . time();
    }
}
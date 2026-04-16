<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Requests\PaymentRequests;

use Qredit\LaravelQredit\Requests\BaseQreditRequest;
use Qredit\LaravelQredit\Traits\HasMessageId;
use Saloon\Enums\Method;

/**
 * GET /paymentRequests filtered by reference — emulates "get one" via list,
 * per the swagger (which exposes only list + filters).
 */
class GetPaymentRequest extends BaseQreditRequest
{
    use HasMessageId;

    protected Method $method = Method::GET;

    protected string $paymentRequestReference;

    public function __construct(string $paymentRequestReference)
    {
        $this->paymentRequestReference = $paymentRequestReference;
        $this->messageIdType = 'payment.get';
    }

    public function resolveEndpoint(): string
    {
        return '/paymentRequests';
    }

    protected function defaultQuery(): array
    {
        return [
            'msgId' => $this->generateMessageId(),
            'reference' => $this->paymentRequestReference,
            'dateFrom' => date('d/m/Y', strtotime('-1 year')),
            'dateTo' => date('d/m/Y', strtotime('+1 day')),
            'max' => 1,
            'offset' => 0,
        ];
    }
}

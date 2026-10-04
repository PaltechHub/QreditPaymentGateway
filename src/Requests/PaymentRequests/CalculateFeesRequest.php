<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Requests\PaymentRequests;

use Qredit\LaravelQredit\Requests\BaseQreditRequest;
use Qredit\LaravelQredit\Traits\HasMessageId;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Traits\Body\HasJsonBody;

/**
 * POST /paymentRequests/calculateFees — swagger.
 *
 * Calculates the fees a payment request incurs on the given channel.
 *
 * Body: { msgId, reference, productCode } — productCode is the payment
 * channel code (e.g. CSAB, NC-QR).
 */
class CalculateFeesRequest extends BaseQreditRequest implements HasBody
{
    use HasJsonBody;
    use HasMessageId;

    protected Method $method = Method::POST;

    protected string $paymentRequestReference;

    protected string $productCode;

    public function __construct(string $paymentRequestReference, string $productCode)
    {
        $this->paymentRequestReference = $paymentRequestReference;
        $this->productCode = $productCode;
        $this->messageIdType = 'payment.fees';
    }

    public function resolveEndpoint(): string
    {
        return '/paymentRequests/calculateFees';
    }

    protected function defaultBody(): array
    {
        return [
            'msgId' => $this->generateMessageId(),
            'reference' => $this->paymentRequestReference,
            'productCode' => $this->productCode,
        ];
    }
}

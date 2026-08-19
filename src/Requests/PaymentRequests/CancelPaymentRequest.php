<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Requests\PaymentRequests;

use Qredit\LaravelQredit\Requests\BaseQreditRequest;
use Qredit\LaravelQredit\Traits\HasMessageId;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Traits\Body\HasJsonBody;

/**
 * DELETE /paymentRequests — swagger + merchant doc.
 *
 * Body: { msgId, reference, reason }. The reference lives in the body, not the URL.
 */
class CancelPaymentRequest extends BaseQreditRequest implements HasBody
{
    use HasJsonBody;
    use HasMessageId;

    protected Method $method = Method::DELETE;

    protected string $paymentRequestReference;

    protected ?string $reason;

    public function __construct(string $paymentRequestReference, ?string $reason = null)
    {
        $this->paymentRequestReference = $paymentRequestReference;
        $this->reason = $reason;
        $this->messageIdType = 'payment.cancel';
    }

    public function resolveEndpoint(): string
    {
        return '/paymentRequests';
    }

    protected function defaultBody(): array
    {
        $body = [
            'msgId' => $this->generateMessageId(),
            'reference' => $this->paymentRequestReference,
        ];

        if ($this->reason !== null) {
            $body['reason'] = $this->reason;
        }

        return $body;
    }
}

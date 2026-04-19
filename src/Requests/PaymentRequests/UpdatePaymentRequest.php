<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Requests\PaymentRequests;

use Qredit\LaravelQredit\Requests\BaseQreditRequest;
use Qredit\LaravelQredit\Traits\HasMessageId;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Traits\Body\HasJsonBody;

/**
 * PUT /paymentRequests — merchant doc / swagger.
 *
 * The gateway dispatches on the `reference` field in the body; there's no
 * resource id in the path.
 */
class UpdatePaymentRequest extends BaseQreditRequest implements HasBody
{
    use HasJsonBody;
    use HasMessageId;

    protected Method $method = Method::PUT;

    protected string $paymentRequestReference;

    protected array $data;

    public function __construct(string $paymentRequestReference, array $data)
    {
        $this->paymentRequestReference = $paymentRequestReference;
        $this->data = $data;
        $this->messageIdType = 'payment.update';
    }

    public function resolveEndpoint(): string
    {
        return '/paymentRequests';
    }

    protected function defaultBody(): array
    {
        return array_merge(
            [
                'msgId' => $this->generateMessageId(),
                'reference' => $this->paymentRequestReference,
            ],
            $this->data,
        );
    }
}

<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Requests\PaymentRequests;

use Qredit\LaravelQredit\Requests\BaseQreditRequest;
use Qredit\LaravelQredit\Traits\HasMessageId;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Traits\Body\HasJsonBody;

/**
 * POST /paymentRequests — merchant doc §4.
 *
 * Caller provides the full body (amountCents, currencyCode, orderReference,
 * customerInfo, billingData, paymentChannels, ...). We inject msgId.
 */
class CreatePaymentRequest extends BaseQreditRequest implements HasBody
{
    use HasJsonBody;
    use HasMessageId;

    protected Method $method = Method::POST;

    protected array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
        $this->messageIdType = 'payment.create';
    }

    public function resolveEndpoint(): string
    {
        return '/paymentRequests';
    }

    protected function defaultBody(): array
    {
        return array_merge(
            ['msgId' => $this->generateMessageId()],
            $this->data,
        );
    }

    protected function getMessageIdContext(): array
    {
        if (isset($this->data['clientReference'])) {
            return ['ref' => $this->data['clientReference']];
        }

        if (isset($this->data['orderReference'])) {
            return ['ref' => $this->data['orderReference']];
        }

        return [];
    }
}

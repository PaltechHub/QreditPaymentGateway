<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Requests\PaymentRequests;

use Qredit\LaravelQredit\Requests\BaseQreditRequest;
use Qredit\LaravelQredit\Traits\HasMessageId;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Traits\Body\HasJsonBody;

/**
 * POST /paymentRequests/initPayment — swagger.
 *
 * Body: { msgId, reference, productCode }.
 */
class InitPaymentRequest extends BaseQreditRequest implements HasBody
{
    use HasJsonBody;
    use HasMessageId;

    protected Method $method = Method::POST;

    protected array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
        $this->messageIdType = 'payment.init';
    }

    public function resolveEndpoint(): string
    {
        return '/paymentRequests/initPayment';
    }

    protected function defaultBody(): array
    {
        return array_merge(
            ['msgId' => $this->generateMessageId()],
            $this->data,
        );
    }
}

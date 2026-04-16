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
 * Body: { msgId, reference, productCode }.
 */
class CalculateFeesRequest extends BaseQreditRequest implements HasBody
{
    use HasJsonBody;
    use HasMessageId;

    protected Method $method = Method::POST;

    protected array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
        $this->messageIdType = 'payment.fees';
    }

    public function resolveEndpoint(): string
    {
        return '/paymentRequests/calculateFees';
    }

    protected function defaultBody(): array
    {
        return array_merge(
            ['msgId' => $this->generateMessageId()],
            $this->data,
        );
    }
}

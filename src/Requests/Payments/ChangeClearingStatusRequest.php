<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Requests\Payments;

use Qredit\LaravelQredit\Requests\BaseQreditRequest;
use Qredit\LaravelQredit\Traits\HasMessageId;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Traits\Body\HasJsonBody;

/**
 * POST /payments/changeClearingStatus — swagger.
 *
 * Body: { msgId, encodedId, clearingStatus, statusReason, username? }.
 */
class ChangeClearingStatusRequest extends BaseQreditRequest implements HasBody
{
    use HasJsonBody;
    use HasMessageId;

    protected Method $method = Method::POST;

    protected array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
        $this->messageIdType = 'payment.clearing';
    }

    public function resolveEndpoint(): string
    {
        return '/payments/changeClearingStatus';
    }

    protected function defaultBody(): array
    {
        return array_merge(
            ['msgId' => $this->generateMessageId()],
            $this->data,
        );
    }
}

<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Requests\PaymentRequests;

use Qredit\LaravelQredit\Requests\BaseQreditRequest;
use Qredit\LaravelQredit\Traits\HasMessageId;
use Saloon\Enums\Method;

/**
 * GET /paymentRequests/generateQR — swagger.
 *
 * Required: msgId. Optional: reference, productCode, expiryTimeLimit,
 * merchantChannelMedia.
 */
class GenerateQRRequest extends BaseQreditRequest
{
    use HasMessageId;

    protected Method $method = Method::GET;

    protected array $queryParams;

    public function __construct(array $query = [])
    {
        $this->queryParams = $query;
        $this->messageIdType = 'payment.qr';
    }

    public function resolveEndpoint(): string
    {
        return '/paymentRequests/generateQR';
    }

    protected function defaultQuery(): array
    {
        $defaults = [
            'msgId' => $this->generateMessageId(),
        ];

        $optional = ['reference', 'productCode', 'expiryTimeLimit', 'merchantChannelMedia'];

        foreach ($optional as $field) {
            if (isset($this->queryParams[$field])) {
                $defaults[$field] = $this->queryParams[$field];
            }
        }

        return $defaults;
    }
}

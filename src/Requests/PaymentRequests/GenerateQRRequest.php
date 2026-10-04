<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Requests\PaymentRequests;

use Qredit\LaravelQredit\Requests\BaseQreditRequest;
use Qredit\LaravelQredit\Traits\HasMessageId;
use Saloon\Enums\Method;

/**
 * GET /paymentRequests/generateQR — swagger.
 *
 * Required: msgId. Optional: reference (payment request reference),
 * productCode (QR payment channel code, e.g. NC-QR), expiryTimeLimit
 * (minutes, gateway default 1440), merchantChannelMedia (see MEDIA).
 */
class GenerateQRRequest extends BaseQreditRequest
{
    use HasMessageId;

    public const MEDIA = [
        'PRINT_BILL_INVOICE',
        'SCREEN_ELECTRONIC_MERCHANT_POS_POI',
        'SCREEN_ELECTRONIC_WEBSITE',
        'SCREEN_ELECTRONIC_APP',
        'SCREEN_ELECTRONIC_OTHER',
    ];

    protected Method $method = Method::GET;

    protected array $queryParams;

    public function __construct(array $query = [])
    {
        if (isset($query['merchantChannelMedia'])) {
            $query['merchantChannelMedia'] = strtoupper((string) $query['merchantChannelMedia']);

            if (! in_array($query['merchantChannelMedia'], self::MEDIA, true)) {
                throw new \InvalidArgumentException("Invalid merchantChannelMedia [{$query['merchantChannelMedia']}]; expected one of ".implode(', ', self::MEDIA).'.');
            }
        }

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

<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Requests\Transactions;

use Qredit\LaravelQredit\Requests\BaseQreditRequest;
use Qredit\LaravelQredit\Traits\HasMessageId;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Traits\Body\HasJsonBody;

/**
 * POST /payments/changeClearingStatus — swagger.
 *
 * Body: { msgId, encodedId, clearingStatus, statusReason, username? }.
 * clearingStatus is NOT_CLEARED | CLEARED | ON_HOLD. The gateway documents
 * `Client-Type: BP` for this call, so it overrides the connector's default TP.
 */
class ChangeClearingStatusRequest extends BaseQreditRequest implements HasBody
{
    use HasJsonBody;
    use HasMessageId;

    public const STATUSES = ['NOT_CLEARED', 'CLEARED', 'ON_HOLD'];

    protected Method $method = Method::POST;

    protected string $encodedId;

    protected string $clearingStatus;

    protected string $statusReason;

    protected ?string $username;

    public function __construct(string $encodedId, string $clearingStatus, string $statusReason, ?string $username = null)
    {
        $clearingStatus = strtoupper($clearingStatus);

        if (! in_array($clearingStatus, self::STATUSES, true)) {
            throw new \InvalidArgumentException("Invalid clearing status [{$clearingStatus}]; expected one of ".implode(', ', self::STATUSES).'.');
        }

        $this->encodedId = $encodedId;
        $this->clearingStatus = $clearingStatus;
        $this->statusReason = $statusReason;
        $this->username = $username;
        $this->messageIdType = 'transaction.clearing';
    }

    public function resolveEndpoint(): string
    {
        return '/payments/changeClearingStatus';
    }

    protected function defaultHeaders(): array
    {
        return array_merge(parent::defaultHeaders(), [
            'Client-Type' => 'BP',
        ]);
    }

    protected function defaultBody(): array
    {
        $body = [
            'msgId' => $this->generateMessageId(),
            'encodedId' => $this->encodedId,
            'clearingStatus' => $this->clearingStatus,
            'statusReason' => $this->statusReason,
        ];

        if ($this->username !== null) {
            $body['username'] = $this->username;
        }

        return $body;
    }
}

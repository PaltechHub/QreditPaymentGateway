<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Requests\CorporateLimits;

use Qredit\LaravelQredit\Requests\BaseQreditRequest;
use Qredit\LaravelQredit\Traits\HasMessageId;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Traits\Body\HasJsonBody;

/**
 * POST /admin/admin/corporateBranchLimit — swagger (the doubled "admin" is
 * the documented path). Validates and syncs corporate limits on branch
 * creation or deletion.
 *
 * Body: { msgId, corporateId, operation: CREATE | DELETE }.
 * The gateway only accepts `Client-Type: SYS` here, so it overrides the
 * connector's default TP.
 */
class SyncCorporateBranchLimitRequest extends BaseQreditRequest implements HasBody
{
    use HasJsonBody;
    use HasMessageId;

    public const OPERATION_CREATE = 'CREATE';

    public const OPERATION_DELETE = 'DELETE';

    protected Method $method = Method::POST;

    protected string $corporateId;

    protected string $operation;

    public function __construct(string $corporateId, string $operation)
    {
        $operation = strtoupper($operation);

        if (! in_array($operation, [self::OPERATION_CREATE, self::OPERATION_DELETE], true)) {
            throw new \InvalidArgumentException("Invalid corporate branch limit operation [{$operation}]; expected CREATE or DELETE.");
        }

        $this->corporateId = $corporateId;
        $this->operation = $operation;
        $this->messageIdType = 'corporate.branchLimit';
    }

    public function resolveEndpoint(): string
    {
        return '/admin/admin/corporateBranchLimit';
    }

    protected function defaultHeaders(): array
    {
        return array_merge(parent::defaultHeaders(), [
            'Client-Type' => 'SYS',
        ]);
    }

    protected function defaultBody(): array
    {
        return [
            'msgId' => $this->generateMessageId(),
            'corporateId' => $this->corporateId,
            'operation' => $this->operation,
        ];
    }
}

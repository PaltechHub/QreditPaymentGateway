<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Requests\Reports;

use Qredit\LaravelQredit\Requests\BaseQreditRequest;
use Qredit\LaravelQredit\Traits\HasMessageId;
use Saloon\Enums\Method;

/**
 * GET /reports/reconciliation — swagger.
 *
 * Required: msgId, dateFrom, dateTo (dd/MM/yyyy — default to the last 30
 * days). Same filters as GET /payments, minus clientReference.
 */
class ReconciliationReportRequest extends BaseQreditRequest
{
    use HasMessageId;

    protected Method $method = Method::GET;

    protected array $queryParams;

    public function __construct(array $query = [])
    {
        $this->queryParams = $query;
        $this->messageIdType = 'report.reconciliation';
    }

    public function resolveEndpoint(): string
    {
        return '/reports/reconciliation';
    }

    protected function defaultQuery(): array
    {
        $defaults = [
            'msgId' => $this->generateMessageId(),
            'dateFrom' => $this->queryParams['dateFrom'] ?? date('d/m/Y', strtotime('-30 days')),
            'dateTo' => $this->queryParams['dateTo'] ?? date('d/m/Y'),
            'max' => $this->queryParams['max'] ?? 50,
            'offset' => $this->queryParams['offset'] ?? 0,
        ];

        $optionalFields = [
            'reference',
            'providerReference',
            'paymentRequestReference',
            'settlementReference',
            'orderReference',
            'corporateId',
            'subCorporateId',
            'subCorporateAccountId',
            'currencyCode',
            'operation',
            'onlyBalanceTransactions',
            'transactionStatus',
            'clearingStatus',
            'sSearch',
            'orderColumnName',
            'orderDirection',
        ];

        foreach ($optionalFields as $field) {
            if (isset($this->queryParams[$field])) {
                $defaults[$field] = $this->queryParams[$field];
            }
        }

        return $defaults;
    }
}

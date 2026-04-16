<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Requests;

use Qredit\LaravelQredit\Connectors\QreditConnector;
use Qredit\LaravelQredit\Security\HmacSigner;
use Qredit\LaravelQredit\Security\ValueFlattener;
use Saloon\Http\PendingRequest;
use Saloon\Http\Request;

abstract class BaseQreditRequest extends Request
{
    /**
     * Attach the per-request HMAC signature after Saloon has materialized the body
     * and query. The connector carries the tenant's secret + case preference.
     */
    public function boot(PendingRequest $pendingRequest): void
    {
        $connector = $pendingRequest->getConnector();

        if (! $connector instanceof QreditConnector) {
            return;
        }

        $query = $pendingRequest->query()->all() ?: [];
        $bodyArray = [];

        $body = $pendingRequest->body();
        if ($body !== null) {
            $raw = $body->all();
            if (is_array($raw)) {
                $bodyArray = $raw;
            }
        }

        $msgId = $this->findMsgId($bodyArray) ?? $this->findMsgId($query);

        if ($msgId === null) {
            // Signing requires msgId per merchant doc §7. Skip — the gateway will
            // reject the unsigned request, which is the correct failure mode.
            return;
        }

        $values = array_merge(
            ValueFlattener::flatten($query),
            ValueFlattener::flatten($bodyArray),
        );

        $authToken = $pendingRequest->headers()->get('X-Auth-Token');
        if (is_string($authToken) && $authToken !== '') {
            $values[] = $authToken;
        }

        $signature = HmacSigner::authorizationHeader(
            $connector->getAuthScheme(),
            $connector->getSecretKey(),
            $msgId,
            $values,
            $connector->getSignatureCase(),
        );

        $pendingRequest->headers()->add('Authorization', $signature);
    }

    /**
     * Recursively search for an "msgId" key.
     */
    private function findMsgId(array $data): ?string
    {
        if (array_key_exists('msgId', $data) && is_string($data['msgId'])) {
            return $data['msgId'];
        }

        foreach ($data as $value) {
            if (is_array($value)) {
                $nested = $this->findMsgId($value);
                if ($nested !== null) {
                    return $nested;
                }
            }
        }

        return null;
    }

    /**
     * Default headers common to every Qredit request.
     *
     * The Authorization header is set later in boot() once the signature is known.
     */
    protected function defaultHeaders(): array
    {
        $headers = [
            'Accept' => 'application/json',
        ];

        if (in_array($this->method->value, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $headers['Content-Type'] = 'application/json';
        }

        return $headers;
    }
}

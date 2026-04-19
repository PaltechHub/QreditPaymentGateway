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

        // Inject X-Auth-Token from the connector into the PendingRequest headers.
        // Saloon evaluates defaultHeaders() at connector construction — before
        // authenticate() sets the token. So we inject it here at boot-time, which
        // runs after the connector is fully initialized but before the request fires.
        $authToken = $connector->getAuthToken();
        if (is_string($authToken) && $authToken !== '') {
            $pendingRequest->headers()->add('X-Auth-Token', $authToken);
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
            return;
        }

        $values = array_merge(
            ValueFlattener::flatten($query),
            ValueFlattener::flatten($bodyArray),
        );

        // Include the auth token in the signed values (per merchant doc §7 —
        // the X-Auth-Token header value participates in the HMAC).
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

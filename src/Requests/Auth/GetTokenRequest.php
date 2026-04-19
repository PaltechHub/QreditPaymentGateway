<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Requests\Auth;

use Saloon\Enums\Method;
use Qredit\LaravelQredit\Requests\BaseQreditRequest;
use Saloon\Contracts\Body\HasBody;
use Saloon\Traits\Body\HasJsonBody;
use Qredit\LaravelQredit\Traits\HasMessageId;

class GetTokenRequest extends BaseQreditRequest implements HasBody
{
    use HasJsonBody;
    use HasMessageId;

    /**
     * The HTTP method of the request.
     */
    protected Method $method = Method::POST;

    /**
     * The API key for authentication.
     */
    protected string $apiKey;

    /**
     * Create a new authentication request.
     */
    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
        $this->messageIdType = 'auth.token';
    }

    /**
     * Resolve the endpoint for the request.
     */
    public function resolveEndpoint(): string
    {
        return '/auth/token';
    }

    /**
     * Default body for the request.
     */
    protected function defaultBody(): array
    {
        return [
            'msgId' => $this->generateMessageId(),
            'apiKey' => $this->apiKey,
        ];
    }

}
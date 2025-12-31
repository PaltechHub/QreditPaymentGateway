<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Requests\Auth;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Contracts\Body\HasBody;
use Saloon\Traits\Body\HasJsonBody;

class GetTokenRequest extends Request implements HasBody
{
    use HasJsonBody;

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

    /**
     * Generate a unique message ID for the request.
     */
    protected function generateMessageId(): string
    {
        return uniqid('msg_', true) . '_' . time();
    }

    /**
     * Default headers for the request.
     */
    protected function defaultHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }
}
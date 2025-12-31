<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Connectors;

use Saloon\Http\Connector;
use Saloon\Traits\Plugins\HasTimeout;
use Saloon\Traits\Plugins\AcceptsJson;
use Saloon\Contracts\Authenticator;
use Saloon\Http\Response;
use Saloon\Http\PendingRequest;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Qredit\LaravelQredit\Exceptions\QreditApiException;
use Qredit\LaravelQredit\Exceptions\QreditAuthenticationException;

class QreditConnector extends Connector
{
    use HasTimeout;
    use AcceptsJson;

    /**
     * Connection timeout in seconds.
     */
    protected int $connectTimeout = 30;

    /**
     * Request timeout in seconds.
     */
    protected int $requestTimeout = 60;

    /**
     * The authentication token.
     */
    protected ?string $authToken = null;

    /**
     * The API key for authentication.
     */
    protected string $apiKey;

    /**
     * Whether to use sandbox environment.
     */
    protected bool $sandbox;

    /**
     * Create a new Qredit connector instance.
     */
    public function __construct(string $apiKey, bool $sandbox = false)
    {
        $this->apiKey = $apiKey;
        $this->sandbox = $sandbox;
    }

    /**
     * Resolve the base URL of the API.
     */
    public function resolveBaseUrl(): string
    {
        return $this->sandbox
            ? config('qredit.sandbox_url', 'http://185.57.122.58:2030/gw-checkout/api/v1')
            : config('qredit.production_url', 'https://api.qredit.com/gw-checkout/api/v1');
    }

    /**
     * Default headers for every request.
     */
    protected function defaultHeaders(): array
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-API-Key' => $this->apiKey,
        ];

        // Add authentication token as X-Auth-Token header if available
        if ($this->authToken) {
            $headers['X-Auth-Token'] = $this->authToken;
        }

        // Add language header if configured
        if ($language = config('qredit.language')) {
            $headers['Accept-Language'] = $language;
        }

        return $headers;
    }

    /**
     * Default authentication for requests.
     * We don't use Saloon's built-in auth since Qredit uses X-Auth-Token header
     */
    protected function defaultAuth(): ?Authenticator
    {
        // We handle auth token in defaultHeaders() as X-Auth-Token
        return null;
    }

    /**
     * Set the authentication token.
     */
    public function setAuthToken(string $token): self
    {
        $this->authToken = $token;
        return $this;
    }

    /**
     * Get the authentication token.
     */
    public function getAuthToken(): ?string
    {
        return $this->authToken;
    }

    /**
     * Get the API key.
     */
    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    /**
     * Check if using sandbox mode.
     */
    public function isSandbox(): bool
    {
        return $this->sandbox;
    }

    /**
     * Handle failed requests.
     */
    public function handleRequestFailed(Response $response, FatalRequestException|RequestException $exception): void
    {
        $body = $response->json();
        $message = $body['message'] ?? $body['error'] ?? 'Unknown API error';
        $code = $response->status();

        if ($code === 401) {
            throw new QreditAuthenticationException($message, $code);
        }

        throw new QreditApiException(
            message: $message,
            code: $code,
            response: $body
        );
    }

    /**
     * Boot the connector (Saloon v3 compatibility).
     */
    public function boot(PendingRequest $pendingRequest): void
    {
        // Add debugging if enabled
        if (config('qredit.debug', false)) {
            $pendingRequest->middleware()->onRequest(function (PendingRequest $request) {
                \Illuminate\Support\Facades\Log::debug('Qredit API Request', [
                    'method' => $request->getMethod(),
                    'url' => $request->getUrl(),
                    'headers' => $request->headers()->all(),
                    'body' => $request->body()?->all(),
                ]);
            });

            $pendingRequest->middleware()->onResponse(function (Response $response) {
                \Illuminate\Support\Facades\Log::debug('Qredit API Response', [
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);
            });
        }
    }
}
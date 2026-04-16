<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Connectors;

use Illuminate\Support\Facades\Log;
use Qredit\LaravelQredit\Exceptions\QreditApiException;
use Qredit\LaravelQredit\Exceptions\QreditAuthenticationException;
use Qredit\LaravelQredit\Security\HmacSigner;
use Saloon\Contracts\Authenticator;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Connector;
use Saloon\Http\PendingRequest;
use Saloon\Http\Response;
use Saloon\Traits\Plugins\AcceptsJson;
use Saloon\Traits\Plugins\HasTimeout;

/**
 * Per-tenant Qredit connector.
 *
 * Holds one tenant's credentials (apiKey, secretKey, environment) plus signing options.
 * Multiple instances can coexist in the same Laravel process — this is what enables
 * SAAS deployments where each channel has its own Qredit account.
 */
class QreditConnector extends Connector
{
    use AcceptsJson;
    use HasTimeout;

    protected int $connectTimeout = 30;

    protected int $requestTimeout = 60;

    protected ?string $authToken = null;

    protected string $apiKey;

    protected string $secretKey;

    protected bool $sandbox;

    protected string $sandboxUrl;

    protected string $productionUrl;

    protected string $authScheme;

    protected string $signatureCase;

    protected string $language;

    /**
     * Accepts either the new array options shape OR the legacy positional
     * signature ($apiKey, $sandbox) for back-compat with pre-0.2 tests.
     *
     * @param  array<string, mixed>|string  $options  Full credential set — see Qredit::make().
     */
    public function __construct(array|string $options, ?bool $sandbox = null)
    {
        // Legacy positional constructor.
        if (is_string($options)) {
            $options = [
                'api_key' => $options,
                'secret_key' => config('qredit.secret_key', ''),
                'sandbox' => $sandbox ?? true,
            ];
        }

        $this->apiKey = $options['api_key'] ?? '';
        $this->secretKey = $options['secret_key'] ?? '';
        $this->sandbox = (bool) ($options['sandbox'] ?? true);
        $this->sandboxUrl = $options['sandbox_url'] ?? config('qredit.sandbox_url');
        $this->productionUrl = $options['production_url'] ?? config('qredit.production_url');
        $this->authScheme = $options['auth_scheme'] ?? config('qredit.signing.scheme');
        $this->signatureCase = $options['signature_case'] ?? config('qredit.signing.case', HmacSigner::CASE_LOWER);
        $this->language = $options['language'] ?? config('qredit.language', 'EN');
    }

    public function resolveBaseUrl(): string
    {
        return $this->sandbox ? $this->sandboxUrl : $this->productionUrl;
    }

    /**
     * Headers attached to every outbound request by Saloon.
     *
     * NOTE: the signed `Authorization` header is NOT set here — it's computed per
     * request in BaseQreditRequest::boot(), once the body/query are materialized.
     */
    protected function defaultHeaders(): array
    {
        $headers = [
            'Accept' => 'application/json',
            'Accept-Language' => $this->language,
            'Client-Type' => config('qredit.client.type', 'MP'),
            'Client-Version' => config('qredit.client.version', '1.0.0'),
        ];

        if ($this->authToken !== null) {
            $headers['X-Auth-Token'] = $this->authToken;
        }

        return $headers;
    }

    /**
     * Qredit auth is body-based (POST /auth/token with {apiKey}) + X-Auth-Token on subsequent
     * calls. Saloon's built-in authenticators don't fit, so we return null and manage it manually.
     */
    protected function defaultAuth(): ?Authenticator
    {
        return null;
    }

    public function setAuthToken(string $token): self
    {
        $this->authToken = $token;

        return $this;
    }

    public function getAuthToken(): ?string
    {
        return $this->authToken;
    }

    public function clearAuthToken(): void
    {
        $this->authToken = null;
    }

    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    public function getSecretKey(): string
    {
        return $this->secretKey;
    }

    public function getAuthScheme(): string
    {
        return $this->authScheme;
    }

    public function getSignatureCase(): string
    {
        return $this->signatureCase;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function isSandbox(): bool
    {
        return $this->sandbox;
    }

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
            response: $body ?? []
        );
    }

    public function boot(PendingRequest $pendingRequest): void
    {
        if (config('qredit.debug', false)) {
            $pendingRequest->middleware()->onRequest(function (PendingRequest $request) {
                Log::debug('Qredit API Request', [
                    'method' => $request->getMethod(),
                    'url' => $request->getUrl(),
                    'headers' => $request->headers()->all(),
                    'body' => $request->body()?->all(),
                ]);
            });

            $pendingRequest->middleware()->onResponse(function (Response $response) {
                Log::debug('Qredit API Response', [
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);
            });
        }
    }
}

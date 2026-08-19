<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Tenancy;

/**
 * Immutable credential bundle for a single Qredit account / tenant.
 *
 * One of these is returned per-tenant by a CredentialProvider. Hand it to
 * Qredit::make() to build a client scoped to exactly that tenant.
 */
final class QreditCredentials
{
    public function __construct(
        public readonly string $apiKey,
        public readonly string $secretKey,
        /** Gateway-negotiated Client-Version handshake — issued per tenant by Qredit. */
        public readonly string $clientVersion,
        public readonly bool $sandbox = true,
        public readonly string $language = 'EN',
        public readonly string $authScheme = 'HmacSHA512_O',
        public readonly string $signatureCase = 'lower',
        public readonly ?string $sandboxUrl = null,
        public readonly ?string $productionUrl = null,
        /** Free-form identifier so downstream code (cache, logs) can disambiguate tenants. */
        public readonly ?string $tenantId = null,
    ) {}

    /**
     * Shape required by the underlying connector / Qredit::make().
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $array = [
            'api_key' => $this->apiKey,
            'secret_key' => $this->secretKey,
            'client_version' => $this->clientVersion,
            'sandbox' => $this->sandbox,
            'language' => $this->language,
            'auth_scheme' => $this->authScheme,
            'signature_case' => $this->signatureCase,
        ];

        if ($this->sandboxUrl !== null) {
            $array['sandbox_url'] = $this->sandboxUrl;
        }

        if ($this->productionUrl !== null) {
            $array['production_url'] = $this->productionUrl;
        }

        return $array;
    }

    /**
     * Stable identifier used to namespace the token cache + client pool.
     */
    public function cacheKey(): string
    {
        return $this->tenantId ?? sha1($this->apiKey);
    }
}

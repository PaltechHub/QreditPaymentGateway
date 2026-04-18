<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Tenancy;

use Qredit\LaravelQredit\Contracts\CredentialProvider;
use Qredit\LaravelQredit\Exceptions\QreditException;

/**
 * Default provider — pulls credentials from config/qredit.php.
 *
 * Used automatically in single-tenant deployments. Multi-tenant apps should bind
 * their own implementation of CredentialProvider in a service provider to
 * override this.
 */
class ConfigCredentialProvider implements CredentialProvider
{
    public function credentialsFor(?string $tenantId = null): QreditCredentials
    {
        $apiKey = (string) config('qredit.api_key', '');
        $secretKey = (string) config('qredit.secret_key', '');
        $clientVersion = (string) config('qredit.client.version', '');

        if ($apiKey === '' || $secretKey === '') {
            throw new QreditException('Qredit credentials missing. Either set QREDIT_API_KEY and QREDIT_SECRET_KEY in .env, or bind a custom CredentialProvider for multi-tenant use.');
        }

        if ($clientVersion === '') {
            throw new QreditException('Qredit client_version missing. Set QREDIT_CLIENT_VERSION in .env, or bind a custom CredentialProvider that supplies it per tenant.');
        }

        return new QreditCredentials(
            apiKey: $apiKey,
            secretKey: $secretKey,
            clientVersion: $clientVersion,
            sandbox: (bool) config('qredit.sandbox', true),
            language: (string) config('qredit.language', 'EN'),
            authScheme: (string) config('qredit.signing.scheme', 'HmacSHA512_O'),
            signatureCase: (string) config('qredit.signing.case', 'lower'),
            tenantId: $tenantId,
        );
    }

    public function isConfiguredFor(?string $tenantId = null): bool
    {
        return ! empty(config('qredit.api_key')) && ! empty(config('qredit.secret_key'));
    }
}

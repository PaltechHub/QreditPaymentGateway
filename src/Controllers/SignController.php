<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Qredit\LaravelQredit\Contracts\CredentialProvider;
use Qredit\LaravelQredit\Contracts\TenantResolver;
use Qredit\LaravelQredit\Exceptions\QreditException;
use Qredit\LaravelQredit\Security\HmacSigner;
use Qredit\LaravelQredit\Security\ValueFlattener;

/**
 * Ready-made signing proxy for the BlockBuilders payment widget.
 *
 * The widget (loaded in the customer's browser) POSTs { "body": "<raw JSON>" }
 * and expects { "signature": "<hex>" } back. We must never expose the secret to
 * the browser — so this controller sits on the merchant's server, pulls the
 * tenant-specific secret from the bound CredentialProvider, signs the body with
 * HmacSigner, and returns only the hex.
 *
 * Wire it via `Route::qreditSign()` in your routes file (see RouteMacros).
 */
class SignController extends Controller
{
    public function __construct(
        protected CredentialProvider $credentials,
        protected TenantResolver $tenants,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $body = $request->input('body');

        if (! is_string($body) || $body === '') {
            return response()->json(['error' => 'Missing "body" field in request.'], 400);
        }

        $decoded = json_decode($body, true);

        if (! is_array($decoded)) {
            return response()->json(['error' => 'Body is not valid JSON.'], 400);
        }

        $msgId = $decoded['msgId'] ?? null;
        if (! is_string($msgId) || $msgId === '') {
            return response()->json(['error' => 'Payload missing msgId.'], 400);
        }

        try {
            $tenantId = $this->tenants->currentTenantId($request);
            $creds = $this->credentials->credentialsFor($tenantId);
        } catch (QreditException $e) {
            Log::warning('Qredit sign rejected — no credentials for tenant', [
                'tenant' => $tenantId ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Qredit is not configured for this tenant.'], 400);
        }

        $signature = HmacSigner::sign(
            $creds->secretKey,
            $msgId,
            ValueFlattener::flatten($decoded),
            $creds->signatureCase,
        );

        return response()->json(['signature' => $signature]);
    }
}

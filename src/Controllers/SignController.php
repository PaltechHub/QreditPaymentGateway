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

        if (is_array($decoded)) {
            $msgId = $decoded['msgId'] ?? null;
            $message = null;
        } else {
            // Widget >= 1.0.9 does the flattening client-side: `body` arrives as
            // the already-sorted, concatenated value string rather than JSON, and
            // carries no separate msgId field — the only copy of it is the one
            // embedded in that string.
            // ponytail: msgId recovered by pattern; if the gateway ever changes
            // its msgId format, have the widget send msgId as its own field.
            $msgId = preg_match('/pp-\d{10,}-[A-Za-z0-9]+/', $body, $m) === 1 ? $m[0] : null;
            $message = $body;
        }

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

        // A single-element list survives sort()+implode() untouched, so passing
        // the pre-built message signs it verbatim.
        $signature = HmacSigner::sign(
            $creds->secretKey,
            $msgId,
            $message === null ? ValueFlattener::flatten($decoded) : [$message],
            $creds->signatureCase,
        );

        return response()->json(['signature' => $signature]);
    }
}

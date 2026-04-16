# Troubleshooting

## Error codes

| Gateway response | Meaning | Where to look |
|---|---|---|
| `HTTP 200 { code: "1004", message: "Bad Signature" }` | Wire format accepted, hash mismatched | [Signature mismatch](#signature-mismatch) |
| `HTTP 200 { code: "1005", message: "Bad Signature" }` | Authorization header missing or scheme wrong | [Authorization header missing](#authorization-header-missing) |
| `HTTP 401 { code: "1705", message: "User Not Found" }` | apiKey isn't in this host's user DB | [User not found](#user-not-found) |
| `HTTP 401 { code: "401", message: "Not Authorized" }` | Any signed request after a 1705 on the same host | [User not found](#user-not-found) |
| `HTTP 200 { status: false, code: "1904" }` | Operation not allowed for this apiKey | [Insufficient permissions](#insufficient-permissions) |
| `HTTP 403 Forbidden` | Calling a protected endpoint without `X-Auth-Token` | [Missing auth token](#missing-auth-token) |
| `HTTP 404 Not Found` | Endpoint doesn't exist on this host | [Wrong host](#wrong-host) |

## Signature mismatch (`code 1004`)

The gateway accepts your `Authorization: HmacSHA512_O <hex>` format but the hash doesn't verify. Diagnose in order:

### 1. Credentials are actually provisioned on this host

Different UAT hosts (`apitest.qredit.tech` vs `185.57.122.58:2030`) can have different user tables. Call both with the same credentials:

```bash
php artisan qredit:call auth --api-key=... --secret-key=... --sandbox
# edit config/qredit.php → sandbox_url to 185.57.122.58:2030 and rerun
```

If one host returns `1705 User Not Found` and another returns `1004 Bad Signature`, the apiKey isn't fully provisioned. `1004` is often a masked "user not found" — no user → no secret → hash compare fails.

### 2. Signature case

Merchant guide §7 step 4 says uppercase hex; step 5 says lowercase. The gateway may accept only one. Toggle it:

```env
QREDIT_SIGNATURE_CASE=upper   # try this if lower fails
```

Or per-call via CLI:

```bash
php artisan qredit:call auth --case=upper ...
```

### 3. Verify the signer against a known-good output

Run the unit test:

```bash
vendor/bin/pest tests/Unit/HmacSignerTest.php
```

All tests pass → the signer is deterministic. The issue is credentials, not algorithm.

### 4. Dry-run to inspect the exact bytes

```bash
php artisan qredit:call auth --dry-run --api-key=... --secret-key=...
```

Prints:

```
msgId:     probe-...
sorted:    <exact concatenated string>
scheme:    HmacSHA512_O
signature: <hex>
header:    HmacSHA512_O <hex>
```

Paste that `sorted` string into an independent HMAC calculator (openssl, CyberChef) and verify the signature matches. If it does, your algorithm is right — the gateway's expected inputs differ from what we're sending.

## Authorization header missing (`code 1005`)

The gateway got a request with no `Authorization` header, or a header with a scheme it doesn't recognize. Check:

1. `BaseQreditRequest::boot()` runs — it's called automatically by Saloon. Confirm by enabling `QREDIT_DEBUG=true` and looking for the `Authorization` key in the logged request headers.
2. The scheme prefix is `HmacSHA512_O` (capital H, capital S, underscore capital O). Other variants (`HMAC-SHA512`, `HMAC_SHA512_O`, etc.) return 1005.
3. You're not sending a *different* `Authorization` header (Bearer token, Basic auth). If your HTTP middleware overwrites the Authorization header, Qredit won't see the signature.

## User not found (`code 1705`)

```
HTTP 401 { "code": "1705", "message": "User Not Found" }
```

Your apiKey isn't provisioned on this gateway. This is the cleanest error to get — it means the signer worked, the gateway verified your signature, then looked up your user and found nothing.

Actions:

- Ask your Qredit contact to provision the apiKey on the specific host you're calling.
- Confirm you're hitting the right host (`QREDIT_SANDBOX_URL`). UAT lives at `https://apitest.qredit.tech/gw-checkout/api/v1`; production at `https://api.qredit.tech/gw-checkout/api/v1`.
- If you're on VPN, verify the VPN host's user table is separate from the public UAT's. Some deployments require the account to be created on both.

## Insufficient permissions (`code 1904`)

```
HTTP 200 { "code": "1904", "message": "Operation not allowed" }
```

The apiKey exists but isn't allowed to call this endpoint. Ask your Qredit contact to grant the missing role — e.g. `ROLE_ORDER_MANAGEMENT`, `ROLE_PRODUCT_VIEW`.

## Missing auth token (`HTTP 403`)

Every endpoint except `/auth/token` requires `X-Auth-Token`. The SDK attaches it automatically after the first call, but in edge cases (corrupted cache, manual connector use) it may be missing.

Force a fresh token:

```php
Qredit::clearCachedToken();
Qredit::authenticate(force: true);
```

## Wrong host (`HTTP 404`)

If `POST /auth/token` returns 404 with a Spring Boot error shape (`{timestamp, path, status:404, error:"Not Found"}`), you're hitting a different app on the same IP. Verify:

- Port is correct (`:2030` for the primary Grails app, not `:2777`).
- Path is `/gw-checkout/api/v1/auth/token` — missing `/gw-checkout` prefix → 404.

## DNS resolution fails

```
curl: Could not resolve host: api.qredit.tech
```

As of writing, `api.qredit.tech` (production) is not active. Use `apitest.qredit.tech` (UAT) for testing. Once production goes live, update `QREDIT_PRODUCTION_URL`.

## Token cache issues

### Getting 401 after deploy

Your cached token survived a code deploy but your secret key changed. Clear the cache:

```bash
php artisan cache:forget qredit_auth_token:<sha1-of-apikey>
# or just flush all cache
php artisan cache:clear
```

### Token cache keys collide between tenants

The SDK namespaces cache keys by `sha1($apiKey)` — this shouldn't happen. If it does, verify your `CredentialProvider` returns a different `apiKey` for each tenant (not the same key with different metadata).

## Webhook issues

### "Invalid webhook signature" in logs

Check:

1. `TenantResolver::tenantIdFromWebhook($request)` returns the correct tenant. The SDK picks the *wrong* secret if this is wrong, and every signature fails.
2. The gateway is POSTing to the URL registered with the callback — not a cached older URL.
3. Lowercase vs uppercase hex — verify both are checked (the SDK does this by default via `hash_equals` against both).

### Webhooks don't arrive at all

- Your webhook URL must be public-facing (use ngrok / Herd Share in dev).
- Your server must return 200 within a few seconds, or Qredit treats it as failed and retries.

## Multi-tenant gotchas

### Queue jobs use the wrong tenant

```php
// ❌ uses whoever is "current" when the job runs, which is never the intended tenant
Qredit::createOrder([...]);

// ✅ always pass explicit tenant captured at dispatch
Qredit::forTenant($this->tenantId)->createOrder([...]);
```

### `CredentialProvider` reads from the wrong source in a job

The bound provider MUST NOT read request state (session, route params, `core()` helpers in Bagisto) when a `$tenantId` argument is passed. Always prefer the argument.

### Cross-tenant cache pollution

You see Tenant A's token being used for Tenant B. Run:

```bash
php artisan tinker
>>> Cache::forget('qredit_auth_token:' . sha1($tenantA_apiKey));
>>> Cache::forget('qredit_auth_token:' . sha1($tenantB_apiKey));
```

Then verify your `CredentialProvider` returns **different `apiKey`s** for different tenants. If not, the cache keys collide by design.

## Debug logging

Turn it on for a single deploy:

```env
QREDIT_DEBUG=true
QREDIT_LOG_CHANNEL=daily
QREDIT_LOG_LEVEL=debug
```

Every request and response is dumped to `storage/logs/laravel-YYYY-MM-DD.log` with the full Authorization header, body, and response JSON. Turn it off in production — the logs contain tokens.

## When to open a Qredit support ticket

The SDK is deterministic — if the unit tests pass, the algorithm is correct. Escalate to Qredit when:

- You get `1004 Bad Signature` on all hosts despite a working signer (see [QREDIT_SIGNATURE_ISSUE.md](QREDIT_SIGNATURE_ISSUE.md)).
- A specific endpoint returns `1904 Operation not allowed` and the role isn't documented.
- Production DNS (`api.qredit.tech`) is still not resolving.

Attach: the output of `php artisan qredit:call auth --dry-run`, and the gateway's response body (`reference` included — Qredit support searches by it).

# Qredit signature integration — resolved (2026-04-16)

> **Status: RESOLVED.** This document is retained as a historical record of the
> debugging arc. The current algorithm is documented in [SIGNING.md](SIGNING.md);
> do not copy code from this file verbatim.

## Resolution

After extensive probing against `apitest.qredit.tech`, we confirmed the signing
algorithm that the gateway's server-side verifier actually uses for **TP
clients**:

```php
$key = md5($secretKey . $msgId, true);   // 16 raw bytes
sort($values, SORT_STRING);
$sig = strtoupper(hash_hmac('sha512', implode('', $values), $key));
```

Headers that must accompany every request:

```
Client-Type: TP
Client-Version: ccc<semver>          // SDK composer version, prefixed
Accept-Language: EN
Authorization: HmacSHA512_O <UPPER_HEX>
```

A live auth call with these values returned a real JWT including
`ROLE_ORDER_MANAGEMENT`, `ROLE_PAYMENT_REQUESTS_MANAGEMENT`, and related roles —
confirming signature acceptance end-to-end.

## Why the arc was long

The Qredit checkout widget (which BlockBuilders distributes) signs requests
from the browser using a CryptoJS-based Angular implementation. That
implementation does:

1. `atob(secret)` → UTF-8 `TextDecoder()`
2. Append `msgId`
3. Iterate UTF-16 code units, take the low byte (`cu & 0xFF`) — matches
   `CryptoJS.enc.Latin1.parse`
4. `CryptoJS.MD5(bytes)` → 16 raw bytes used as the HMAC key
5. HMAC-SHA512, uppercase hex

We byte-verified our PHP port against that Angular reference across 5 distinct
golden vectors and got an exact match — yet **every signed request still
returned `1004 Bad Signature`** against `apitest.qredit.tech`.

The breakthrough came from trying a simpler `md5($secret.$msgId, raw: true)`
key derivation (no base64 decode, no UTF-16 dance) with `Client-Type: TP` +
`Client-Version: ccc1.0` headers. That combination produced `1904 Operation
not allowed` — a new error code that only fires when the hash *validates* but
the apiKey lacks permission. Once Qredit enabled the permission, the same
request returned a JWT.

**Takeaway:** the Angular widget and the server-side verifier use *different*
algorithms. The widget's algorithm is for a different client variant that our
SDK no longer targets.

## What to trust

- [SIGNING.md](SIGNING.md) — the current algorithm, with golden vectors pinned
  in [HmacSignerTest.php](../tests/Unit/HmacSignerTest.php).
- [TROUBLESHOOTING.md](TROUBLESHOOTING.md) — error code cheat sheet and the
  diagnostic playbook.

## What NOT to trust

- Older copies of this document that claimed "our signer is correct" alongside
  the Angular-reference walkthrough. Those were true about byte-identity to the
  widget — but irrelevant, because the gateway expects the TP-client algorithm.
- The merchant guide's §7 worked example. We were never able to reproduce it,
  likely because its X-Auth-Token JWT is truncated mid-payload.

## Reproducer

```bash
php artisan qredit:call auth \
  --api-key=EdVfej9DvSSHBCtn0DDUviHxmXMj3t0bodQqjeNXF0 \
  --secret-key=B9E0236B77E5C16B1F3540265920C7E0C541622E66C4F76FBC53BC990F11E496 \
  --sandbox
```

Expected: JSON with `access_token` (JWT) and `status: true`.

Signer source: [src/Security/HmacSigner.php](../src/Security/HmacSigner.php).
Connector headers: [src/Connectors/QreditConnector.php](../src/Connectors/QreditConnector.php).

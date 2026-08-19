# HMAC SHA512 signing

Every outgoing request to Qredit must carry an `Authorization` header containing a per-request HMAC-SHA512 signature. This document explains exactly how the SDK computes it. The algorithm here has been **verified against live UAT** — we got a real JWT token back.

## TL;DR

```
Authorization: HmacSHA512_O <UPPER_HEX>

where UPPER_HEX = strtoupper(hash_hmac(
    'sha512',
    sort_asc(all scalar values).join(''),
    md5(secretKey . msgId, raw: true)   // 16 raw bytes
)).
```

You never compute this by hand — `BaseQreditRequest::boot()` does it for every request. The walkthrough below exists so you can reason about it when debugging.

## Gateway client handshake

The gateway gates on two request headers before it'll even look at the signature:

| Header | Value | Notes |
|---|---|---|
| `Client-Type` | `TP` | Fixed. Other values lock you out of `/auth/token`. |
| `Client-Version` | `ccc<semver>` | Dynamic — derived from the SDK's installed composer version at runtime. Override via `QREDIT_CLIENT_VERSION` only if Qredit has negotiated a specific string for your integration. |
| `Accept-Language` | `EN` / `AR` | Per tenant. |
| `Authorization` | `HmacSHA512_O <hex>` | Uppercase hex. The `HmacSHA512_O` prefix is a protocol literal; lives in `config/qredit.php` under `signing.scheme`. |

Get the headers wrong and you'll see `1012 Bad Signature` regardless of what you hash.

## Step-by-step

### Step 1 — concatenate secret and msgId

```php
$apiSecretKey = $secretKey.$msgId;
```

No base64 decoding. No UTF-8 normalization. No UTF-16 tricks. Just raw UTF-8 string concatenation — this is what the gateway's server-side code does for TP clients.

### Step 2 — raw MD5 as the HMAC key

```php
$hmacKey = md5($apiSecretKey, true);   // 16 raw bytes — NOT hex
```

The `true` flag is load-bearing. If you MD5-hex and feed that as the key, the hash differs.

### Step 3 — collect scalar values

Every scalar value in the request body, query string, plus the `X-Auth-Token` header value if present. Nulls and empty strings are dropped upstream by `ValueFlattener`. Booleans serialize as `'true'` / `'false'`.

### Step 4 — sort + concatenate

```php
$strings = array_map('strval', $values);
sort($strings, SORT_STRING);            // ASCII byte order
$message = implode('', $strings);       // no separator
```

### Step 5 — HMAC-SHA512, uppercase hex

```php
$signature = strtoupper(hash_hmac('sha512', $message, $hmacKey));
```

Always uppercase. Lowercase rejects as `1004 Bad Signature`.

### Step 6 — Authorization header

```
Authorization: HmacSHA512_O <signature>
```

## Golden vectors

Every vector below was produced by the SDK's signer against the live UAT algorithm and is pinned as a unit test in [tests/Unit/HmacSignerTest.php](../tests/Unit/HmacSignerTest.php).

| secret | msgId | values | Signature (upper hex) |
|---|---|---|---|
| `B9E0236B77E5C16B1F3540265920C7E0C541622E66C4F76FBC53BC990F11E496` | `probe-abc123` | `[msgId, apiKey]` | `BDDCA9E14E3BF18F…52965A7D` |
| `CF63DBB1ADCEEBD3451985746B7D619998CB8E8AAC00715660D0CC911484B335` | `01062571545OiZoS` | `["10","ILS","NDI=","test","false","123456789","MjIx",msgId]` | `1C6122C3F47B02C3…3CDF05F5` |
| `QWxhZGRpbjpvcGVuIHNlc2FtZQ==` | `hello` | `["hello","world","42","true"]` | `11C4175C3B5CBB40…0930F2CA` |
| `AAAA` | `x` | `["a","b","c"]` | `790802F88384D07C…A525C04D` |
| `MjAwMA==` | `1` | `["x"]` | `88815672776EA54C…AC72DC5E` |

## How the SDK wires signing into every request

[`BaseQreditRequest::boot(PendingRequest)`](../src/Requests/BaseQreditRequest.php) runs after Saloon materializes the body + query. It:

1. Walks `body + query` with `ValueFlattener`.
2. Adds the `X-Auth-Token` header value if present.
3. Computes the signature with `HmacSigner`.
4. Attaches `Authorization: HmacSHA512_O <UPPER_HEX>` to the request.

Every request class (`CreateOrderRequest`, `GetPaymentRequest`, …) inherits this behavior. You never implement signing per endpoint.

## Debugging a signature mismatch

### 1. Enable debug logging

```env
QREDIT_DEBUG=true
```

The final `Authorization` header lands in your Laravel log on every request.

### 2. Use `qredit:call --dry-run`

```bash
php artisan qredit:call auth --secret-key=... --dry-run
```

Prints: the sorted-values string, the scheme, the signature, and the full header — without sending.

### 3. Reproduce the signature in a one-liner

```php
<?php
$secret = 'YOUR_SECRET';
$msgId  = 'YOUR_MSGID';
$values = ['sorted', 'values', 'here'];

$key = md5($secret.$msgId, true);         // 16 raw bytes
sort($values, SORT_STRING);
$sig = strtoupper(hash_hmac('sha512', implode('', $values), $key));

echo "Authorization: HmacSHA512_O $sig\n";
```

If your PHP output matches the SDK's but UAT still rejects, the problem isn't signing — see [TROUBLESHOOTING.md](TROUBLESHOOTING.md).

## Error codes cheat sheet

| Code | Meaning | Likely cause |
|---|---|---|
| `1004 Bad Signature` | Header accepted, hash mismatched | Wrong secret, wrong signature case, or the apiKey's user isn't provisioned on this host. |
| `1005 Bad Signature` | Authorization missing / wrong scheme | `HmacSHA512_O` prefix missing or header dropped by middleware. |
| `1012 Bad Signature` | Hash rejected upstream of the main verify step | Usually `Client-Type` / `Client-Version` header mismatch. Check they're `TP` / `ccc…` respectively. |
| `1705 User Not Found` | apiKey doesn't exist on this host's user table | Credentials not provisioned here (useful diagnostic: try the VPN host). |
| `1904 Operation not allowed` | **Signature accepted.** apiKey recognized, but not permitted to call this endpoint. | Ask Qredit to grant the required role to this apiKey. |

See [TROUBLESHOOTING.md](TROUBLESHOOTING.md) for the full diagnostic playbook.

## Why not the Angular reference?

The Qredit checkout widget ships an Angular-based signer that uses base64 decode + UTF-8 TextDecoder + UTF-16 low-byte extraction + raw MD5. Earlier versions of this SDK mirrored that algorithm byte-for-byte. It does not match what the gateway server-side verifier expects for **TP clients** — that variant is simpler and is what this doc describes. Both algorithms are byte-deterministic; only the TP-client one is accepted by `/auth/token`.

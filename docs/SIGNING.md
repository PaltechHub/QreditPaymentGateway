# HMAC SHA512 signing

Every outgoing request to Qredit must carry a per-request HMAC-SHA512 signature in the `Authorization` header. This document explains exactly how the SDK computes it.

## TL;DR

```
Authorization: HmacSHA512_O <UPPER_HEX>

where UPPER_HEX = hash_hmac(
    'sha512',
    sort(all scalar values, lexicographic).join(''),
    md5_raw( latin1_truncate( utf8_text_decode( base64_decode(secretKey) ) + msgId ) )
).toUpperCase()
```

You never call this by hand — `BaseQreditRequest::boot()` does it for every request. But you may need to reason about it when debugging.

The SDK's implementation is a **faithful port of the Angular reference** (the `crypto-js`-based signer the gateway ships). It's byte-identical on every tested input. If something rejects, the bug is not in the signer — see [TROUBLESHOOTING.md](TROUBLESHOOTING.md).

## The reference implementation

The gateway's own Angular client signs like this:

```typescript
calculateSignature(payload, xAuthToken) {
    this.nonce         = payload.msgId;
    const apiSecretKey = this.decodeFromBase64(this.secretKey) + this.nonce;
    const apiSecret    = this.md5Encode(this.stringToAsciiArray(apiSecretKey));
    const values       = this.getPayloadValues(payload, xAuthToken);
    const ordered      = this.lexicographicalOrder(values);
    return 'HmacSHA512_O ' + this.calculateHmacSHA512(apiSecret, ordered);
}
```

Seven quirks matter here; the SDK preserves all of them.

## Step-by-step

### Step 1 — base64-decode the secret

The merchant secret is **base64-encoded on paper**. Before any math, decode it.

```php
$binary = base64_decode($secretKey);   // 64-char secret → up to 48 raw bytes
```

The gateway's Angular code then runs it through a UTF-8 `TextDecoder`:

```javascript
const utf8Bytes     = new Uint8Array([...atob(secret)].map(c => c.charCodeAt(0)));
const decodedString = new TextDecoder().decode(utf8Bytes);  // invalid bytes → U+FFFD
```

The PHP SDK replicates this:

```php
mb_substitute_character(0xFFFD);
$decodedString = mb_convert_encoding($binary, 'UTF-8', 'UTF-8');
```

Invalid-UTF-8 byte sequences become U+FFFD (REPLACEMENT CHARACTER). Valid multi-byte sequences become single JS characters whose code point is the Unicode scalar value. This is **lossy** — don't expect the output to round-trip.

### Step 2 — append the msgId

```php
$apiSecretKey = $decodedString . $msgId;
```

### Step 3 — take UTF-16 code units, then truncate to one byte each

Angular iterates the JS string via `charCodeAt(i)` — that returns the **UTF-16 code unit** at index `i` (not the Unicode codepoint). The result is fed to `CryptoJS.enc.Latin1.parse()` which keeps only the low byte of each code unit (`cu & 0xFF`).

PHP equivalent: encode to UTF-16LE, take every other byte (the low byte of each little-endian code unit).

```php
$utf16le   = mb_convert_encoding($apiSecretKey, 'UTF-16LE', 'UTF-8');
$byteString = '';
for ($i = 0, $n = strlen($utf16le); $i < $n; $i += 2) {
    $byteString .= $utf16le[$i];   // low byte only
}
```

### Step 4 — MD5 the bytes, raw

```php
$keyBytes = md5($byteString, true);   // 16 raw bytes
```

These 16 bytes (not their hex representation) become the HMAC-SHA512 key.

### Step 5 — flatten + sort the payload values

Every scalar value in the request body, query string, plus the `X-Auth-Token` header value if present, goes into one list. Then:

```php
$strings = array_map('strval', $values);
sort($strings, SORT_STRING);            // ASCII byte order
$message = implode('', $strings);       // no separator
```

Booleans serialize as `'true'` / `'false'`; nulls and empty strings are dropped upstream by `ValueFlattener`.

### Step 6 — HMAC-SHA512

```php
$signature = hash_hmac('sha512', $message, $keyBytes);
```

### Step 7 — uppercase hex

```php
$signature = strtoupper($signature);
```

The reference uppercases. The SDK defaults to upper; a `QREDIT_SIGNATURE_CASE=lower` env var flips it for deployments that demand lowercase.

### Step 8 — Authorization header

```
Authorization: HmacSHA512_O <signature>
```

The `HmacSHA512_O` prefix is a protocol literal the gateway's HTTP layer dispatches on. Lives in `config/qredit.php` under `signing.scheme`.

## Golden vectors

Every vector below was produced by the **Angular reference** running in Node with `crypto-js` v4.2.0. Every vector is also pinned as a unit test in [tests/Unit/HmacSignerTest.php](../tests/Unit/HmacSignerTest.php).

| secret (base64) | msgId | values | Signature (upper hex) |
|---|---|---|---|
| `B9E0236B77E5C16B1F3540265920C7E0C541622E66C4F76FBC53BC990F11E496` | `probe-abc123` | `[msgId, "EdVfej9DvSSHBCtn0DDUviHxmXMj3t0bodQqjeNXF0"]` | `06EE49899C99BE6C…CC8BB381` |
| `CF63DBB1ADCEEBD3451985746B7D619998CB8E8AAC00715660D0CC911484B335` | `01062571545OiZoS` | `["10", "ILS", "NDI=", "test", "false", "123456789", "MjIx", msgId]` | `657273D7F55DE4DC…A949A06E` |
| `QWxhZGRpbjpvcGVuIHNlc2FtZQ==` | `hello` | `["hello", "world", "42", "true"]` | `992BFA976D766C85…046ACEED1` |
| `AAAA` | `x` | `["a", "b", "c"]` | `4A2C1ECAF16543A7…D1C430FF` |
| `MjAwMA==` | `1` | `["x"]` | `AD5FC9689AE223F3…C8D4A337` |

## The merchant-doc §7 worked example is unreproducible

Merchant guide §7 prints an `(secret, msgId, values) → signature` worked example. We **cannot reproduce** it even with the correct algorithm, because the X-Auth-Token JWT in the example is truncated mid-payload (`…mhPY0` has no trailing `.signature` segment). The real signature was computed over the full JWT but only a prefix is shown.

If you're reasoning about signing, trust the Angular-reference golden vectors in [HmacSignerTest.php](../tests/Unit/HmacSignerTest.php) — not the doc's worked example.

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

### 3. Cross-check against the Node reference

If you suspect the signer, run the reference implementation directly:

```bash
npm install crypto-js
node <<'JS'
const CryptoJS = require('crypto-js');
function decode(b){const s=Buffer.from(b,'base64').toString('binary');const u=new Uint8Array([...s].map(c=>c.charCodeAt(0)));return new TextDecoder().decode(u);}
function ascii(s){const a=[];for(let i=0;i<s.length;i++)a.push(s.charCodeAt(i));return a;}
function md5e(bytes){const bs=bytes.map(b=>String.fromCharCode(b)).join('');const h=CryptoJS.MD5(CryptoJS.enc.Latin1.parse(bs));const hx=h.toString(CryptoJS.enc.Hex);const o=[];for(let i=0;i<hx.length;i+=2){const b=parseInt(hx.substring(i,i+2),16);o.push(b>127?b-256:b);}return o;}
function b2w(b){const w=[];for(let i=0;i<b.length;i++)w[i>>>2]|=(b[i]&0xff)<<(24-(i%4)*8);return CryptoJS.lib.WordArray.create(w,b.length);}

const secret = 'YOUR_SECRET';
const msgId  = 'YOUR_MSGID';
const values = ['sorted','values','here'];

const key = md5e(ascii(decode(secret) + msgId));
const sig = CryptoJS.HmacSHA512(values.sort().join(''), b2w(key))
                    .toString(CryptoJS.enc.Hex).toUpperCase();
console.log('HmacSHA512_O ' + sig);
JS
```

If the PHP and Node outputs agree (they should — 74 unit tests enforce it), the gateway's rejection isn't about the algorithm.

## Error codes cheat sheet

| Code | Meaning | Likely cause |
|---|---|---|
| `1004 Bad Signature` | Header accepted, hash mismatched | Wrong secret, OR the apiKey's user isn't provisioned on this host (no server-side secret → can't verify) |
| `1005 Bad Signature` | Authorization missing / wrong scheme | `HmacSHA512_O` prefix missing, or header dropped by middleware |
| `1705 User Not Found` | apiKey doesn't exist on this host's user table | Credentials not provisioned here (useful diagnostic: try the other UAT host) |

See [TROUBLESHOOTING.md](TROUBLESHOOTING.md) for the full diagnostic playbook.

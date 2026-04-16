# Qredit — Credentials Invalid (Signer Now Verified)

## Bottom line (updated)

**The signer is correct.** We now have the Angular reference implementation the gateway ships and have byte-verified our PHP port against it across multiple controlled inputs.

**The UAT credentials we were issued are not provisioned on the gateway.** The persistent `code 1004 "Bad Signature"` is a downstream symptom of the server failing to find a user record for this apiKey.

```
apiKey    = EdVfej9DvSSHBCtn0DDUviHxmXMj3t0bodQqjeNXF0
secretKey = B9E0236B77E5C16B1F3540265920C7E0C541622E66C4F76FBC53BC990F11E496   (base64-encoded on the wire)
```

## Evidence

### 1. We have the reference algorithm (Angular / `crypto-js`)

```typescript
calculateSignature(payload, xAuthToken) {
    this.nonce         = payload.msgId;
    const apiSecretKey = this.decodeFromBase64(this.secretKey) + this.nonce;
    const apiSecret    = this.md5Encode(this.stringToAsciiArray(apiSecretKey));  // raw 16 bytes
    const values       = this.getPayloadValues(payload, xAuthToken);
    const ordered      = this.lexicographicalOrder(values);
    return 'HmacSHA512_O ' + this.calculateHmacSHA512(apiSecret, ordered);       // UPPER hex
}
```

Key nuances (each is load-bearing):

1. The secret is **base64-encoded**; decode before use.
2. After decoding, run through a UTF-8 `TextDecoder` — invalid bytes become U+FFFD.
3. Append the `msgId` string.
4. `stringToAsciiArray` reads UTF-16 code units of the resulting JS string (not codepoints — surrogate-pair halves get exposed).
5. `md5Encode` builds a byte string via `String.fromCharCode(codeUnit) → CryptoJS.enc.Latin1.parse(...)` — which takes the **low byte** of each code unit (`cu & 0xFF`) — then MD5s those bytes **raw** (16 bytes).
6. Those 16 raw bytes become the HMAC-SHA512 key.
7. Sorted scalar values are concatenated (ASCII-compatible); no separator.
8. Final hex is UPPER-cased.

### 2. Our PHP signer is byte-identical to the reference

Cross-verified over 5 distinct (secret, msgId, values) triples — every signature matches the Node + crypto-js output exactly. Test file: [tests/Unit/HmacSignerTest.php](../tests/Unit/HmacSignerTest.php).

| Inputs | Reference output (Node + crypto-js) | PHP output |
|---|---|---|
| secret=`B9E0…E496` msgId=`probe-abc123` values=`[msgId, apiKey]` | `06EE4989…CC8BB381` | ✅ same |
| secret=`CF63…B335` msgId=`01062571545OiZoS` values=`[10, ILS, NDI=, test, false, 123456789, MjIx, msgId]` | `657273D7…A949A06E` | ✅ same |
| secret=`QWxhZGRpbjpvcGVuIHNlc2FtZQ==` msgId=`hello` values=`[hello, world, 42, true]` | `992BFA97…046ACEED1` | ✅ same |
| secret=`AAAA` msgId=`x` values=`[a, b, c]` | `4A2C1ECA…D1C430FF` | ✅ same |
| secret=`MjAwMA==` msgId=`1` values=`[x]` | `AD5FC968…C8D4A337` | ✅ same |

### 3. UAT responds with `1004 "Bad Signature"` even with the verified-correct signature

Gateway error codes tell us *where* it rejects:

| Authorization header | Gateway response | What it means |
|---|---|---|
| (none) | `code 1005 "Bad Signature"` | Header missing / scheme unknown |
| `HmacSHA512_O <our verified-correct hex>` | `code 1004 "Bad Signature"` | Server reaches the hash-verify step and rejects only the hash |
| `HmacSHA512_O 00…00` | `code 1004 "Bad Signature"` | Same — server sees a valid-shaped header but the hash doesn't match anything on its side |

Because our signer output is proven correct against the reference, `1004` can only mean the server has a different secret for this user — or no user at all. See §4 below.

### 4. The VPN gateway corroborates: apiKey isn't provisioned

Calling the VPN-gated UAT (`http://185.57.122.58:2030/gw-checkout/api/v1/auth/token`) with the same apiKey returns:

```
HTTP 401  code 1705 "User Not Found"
```

That error only fires when the server's user-by-apiKey lookup misses. The public UAT (`apitest.qredit.tech`) probably masks the same failure as `1004` to avoid revealing which apiKeys exist.

## What we need

Please provision working UAT credentials and confirm:

1. **Which host is the authoritative UAT** — `apitest.qredit.tech` (public) or `185.57.122.58:2030` (VPN)?
2. **The credentials are active on that host's user database.**
3. One working signed `curl` example against that host so we can diff headers byte-for-byte if anything still rejects.

## Reproducer

```bash
php artisan qredit:call auth \
  --api-key=EdVfej9DvSSHBCtn0DDUviHxmXMj3t0bodQqjeNXF0 \
  --secret-key=B9E0236B77E5C16B1F3540265920C7E0C541622E66C4F76FBC53BC990F11E496 \
  --sandbox
```

Signer source: [src/Security/HmacSigner.php](../src/Security/HmacSigner.php) · full algorithm walk-through: [docs/SIGNING.md](SIGNING.md).

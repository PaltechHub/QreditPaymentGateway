<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Security;

/**
 * Qredit-gateway HMAC SHA512 signer.
 *
 * This is a faithful port of the Angular reference implementation the gateway
 * ships (the same one the BlockBuilders checkout widget uses under the hood).
 * It deliberately preserves several quirks so that signatures produced here
 * are byte-identical to the reference.
 *
 * Pipeline:
 *   1. The merchant's *secret* is base64-encoded on paper; base64-decode it.
 *   2. Feed the decoded bytes through a UTF-8 TextDecoder (invalid bytes become
 *      U+FFFD). The result is a JS-shaped string of UTF-16 code units.
 *   3. Append the msgId to that string.
 *   4. For every JS char in the combined string, take its UTF-16 code unit and
 *      truncate it to one byte (`cu & 0xFF`) — this matches CryptoJS's
 *      `enc.Latin1.parse` behavior that Angular relies on.
 *   5. MD5 those bytes (raw, not hex). The resulting 16 bytes are the HMAC key.
 *   6. Sort the body/query/header scalar values lexicographically and join.
 *   7. HMAC-SHA512 the sorted string using the key from step 5.
 *   8. Emit the signature in UPPER hex (the reference uppercases the output).
 *
 * See docs/SIGNING.md for the full walkthrough.
 */
final class HmacSigner
{
    public const CASE_LOWER = 'lower';

    public const CASE_UPPER = 'upper';

    /**
     * Compute the signature hex for a request.
     *
     * @param  string  $secretKey  The base64-encoded merchant secret as issued by Qredit.
     * @param  string  $msgId      The request msgId; also used as the nonce.
     * @param  array<mixed>  $values  Scalar values (body + query + X-Auth-Token) to sign.
     * @param  string  $case       'upper' (reference default) or 'lower'.
     */
    public static function sign(string $secretKey, string $msgId, array $values, string $case = self::CASE_UPPER): string
    {
        $keyBytes = self::deriveKey($secretKey, $msgId);
        $message = self::buildMessage($values);

        $signature = hash_hmac('sha512', $message, $keyBytes);

        return $case === self::CASE_LOWER ? strtolower($signature) : strtoupper($signature);
    }

    /**
     * Build the full Authorization header value (scheme + space + signature).
     */
    public static function authorizationHeader(string $scheme, string $secretKey, string $msgId, array $values, string $case = self::CASE_UPPER): string
    {
        return $scheme.' '.self::sign($secretKey, $msgId, $values, $case);
    }

    /**
     * Derive the 16-byte HMAC key from (secret, msgId) using the exact algorithm
     * the Angular reference implementation uses. Exposed for tests + debugging.
     */
    public static function deriveKey(string $secretKey, string $msgId): string
    {
        // Step 1 — base64 decode the secret.
        $decodedBinary = (string) base64_decode($secretKey, false);

        // Step 2 — run it through UTF-8 TextDecoder semantics. Invalid sequences
        // become U+FFFD. PHP's default mb_convert_encoding substitutes 0x3F ('?');
        // force U+FFFD so we match the Angular behaviour exactly.
        $previous = mb_substitute_character();
        mb_substitute_character(0xFFFD);
        $decodedString = mb_convert_encoding($decodedBinary, 'UTF-8', 'UTF-8');
        mb_substitute_character(is_int($previous) ? $previous : 0xFFFD);

        if ($decodedString === false) {
            $decodedString = '';
        }

        // Step 3 — append the msgId.
        $apiSecretKey = $decodedString.$msgId;

        // Step 4 — for each JS char (UTF-16 code unit), take the low byte only.
        // PHP has no native UTF-16 char iterator; convert to UTF-16LE and read
        // two bytes at a time. For each 16-bit code unit we keep the LOW byte,
        // which is exactly what `String.fromCharCode(cu) -> Latin1.parse` does
        // in CryptoJS.
        $utf16le = (string) mb_convert_encoding($apiSecretKey, 'UTF-16LE', 'UTF-8');
        $byteString = '';
        for ($i = 0, $len = strlen($utf16le); $i < $len; $i += 2) {
            $byteString .= $utf16le[$i];
        }

        // Step 5 — MD5 raw (16 bytes) = the HMAC key.
        return md5($byteString, true);
    }

    /**
     * Sort scalar values lexicographically (byte order) and concatenate.
     *
     * @param  array<mixed>  $values
     */
    public static function buildMessage(array $values): string
    {
        $strings = array_map(static fn ($v) => self::stringify($v), $values);

        sort($strings, SORT_STRING);

        return implode('', $strings);
    }

    /**
     * Cast a scalar to the string that gets signed over.
     */
    private static function stringify(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return '';
        }

        return (string) $value;
    }
}

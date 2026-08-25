<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Security;

/**
 * Extract scalar values from a (possibly nested) payload for HMAC signing.
 *
 * The gateway signs over the set of scalar values (booleans/strings/numbers) present
 * anywhere in the request. The Java reference walks keys in insertion order and drops
 * null/empty values. We match that here so our signature lines up with the server's
 * expected signature.
 */
final class ValueFlattener
{
    /**
     * Flatten an array into a list of scalars. Null / empty-string values are dropped,
     * matching the gateway's Java collector (it skips empty fields before sorting).
     *
     * @param  array<mixed>  $data
     * @return array<int, scalar>
     */
    public static function flatten(array $data): array
    {
        $out = [];

        self::walk($data, $out);

        return $out;
    }

    /**
     * Flatten only top-level scalars — skip any key whose value is an array. This
     * mirrors what the inbound webhook signer on the gateway side does for its
     * records envelope: nested sub-objects (paymentRequest, sender, receiver,
     * senderAccount, receiverAccount, extraDetails, trackingInfo, ...) are not
     * part of the signed set, only the flat scalar fields of the record itself.
     *
     * @param  array<mixed>  $data
     * @return array<int, scalar>
     */
    public static function flattenTopLevel(array $data): array
    {
        $out = [];

        foreach ($data as $value) {
            if (is_array($value) || $value === null) {
                continue;
            }

            if (is_string($value) && $value === '') {
                continue;
            }

            $out[] = $value;
        }

        return $out;
    }

    /**
     * Flatten straight from the raw JSON body, preserving each number exactly as it
     * appears on the wire.
     *
     * The gateway (Java) signs over the literal it emitted — `10.0`, `1.0`, `100.00`.
     * PHP's json_decode turns those into floats, and `(string) 10.0` is `"10"`, which
     * silently breaks every inbound signature. Quoting the numeric literals before
     * decoding keeps them as strings all the way into the signed message.
     *
     * @return array<int, scalar>|null null when $json is not a JSON object/array.
     */
    public static function flattenRawJson(string $json): ?array
    {
        $decoded = json_decode(self::quoteNumericLiterals($json), true);

        return is_array($decoded) ? self::flatten($decoded) : null;
    }

    /**
     * Wrap every bare JSON number literal in quotes.
     *
     * Outside a string, `-` or a digit can only begin a number in valid JSON, so a
     * single pass that tracks in-string state (and backslash escapes) is enough.
     * Booleans and nulls are left alone.
     */
    public static function quoteNumericLiterals(string $json): string
    {
        $out = '';
        $length = strlen($json);
        $inString = false;
        $escaped = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $json[$i];

            if ($inString) {
                $out .= $char;

                if ($escaped) {
                    $escaped = false;
                } elseif ($char === '\\') {
                    $escaped = true;
                } elseif ($char === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($char === '"') {
                $inString = true;
                $out .= $char;

                continue;
            }

            if ($char === '-' || ($char >= '0' && $char <= '9')) {
                $end = $i;

                while ($end < $length && strpos('-+.eE0123456789', $json[$end]) !== false) {
                    $end++;
                }

                $out .= '"'.substr($json, $i, $end - $i).'"';
                $i = $end - 1;

                continue;
            }

            $out .= $char;
        }

        return $out;
    }

    private static function walk(array $data, array &$out): void
    {
        foreach ($data as $value) {
            if (is_array($value)) {
                self::walk($value, $out);

                continue;
            }

            if ($value === null) {
                continue;
            }

            if (is_string($value) && $value === '') {
                continue;
            }

            $out[] = $value;
        }
    }
}

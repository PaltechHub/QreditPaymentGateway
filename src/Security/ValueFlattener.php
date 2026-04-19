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

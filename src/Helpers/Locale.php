<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Helpers;

/**
 * Locale utilities for the SDK's checkout views. Resolves text direction
 * (RTL/LTR) from the locale code using the `qredit.locales` config array,
 * so adding a new RTL language is a one-line config entry — not a code change.
 */
final class Locale
{
    /**
     * Get text direction for a locale code.
     *
     * Looks up `qredit.locales` first; falls back to a built-in RTL list
     * so it works even if config isn't published.
     */
    public static function direction(string $locale): string
    {
        $code = strtolower(substr(trim($locale), 0, 2));

        foreach (config('qredit.locales', []) as $entry) {
            if (($entry['code'] ?? '') === $code) {
                return $entry['direction'] ?? 'ltr';
            }
        }

        // Fallback: known RTL scripts.
        return in_array($code, ['ar', 'he', 'fa', 'ur', 'ku', 'ps', 'sd', 'yi'], true) ? 'rtl' : 'ltr';
    }

    /**
     * Whether a locale is right-to-left.
     */
    public static function isRtl(string $locale): bool
    {
        return self::direction($locale) === 'rtl';
    }
}

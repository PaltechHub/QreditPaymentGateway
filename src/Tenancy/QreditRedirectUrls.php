<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Tenancy;

use InvalidArgumentException;

/**
 * Where to send the customer after the payment widget reports an outcome.
 *
 * Carries the four widget callback URLs:
 *   - successUrl  → onSuccess
 *   - cancelUrl   → onCancel
 *   - failureUrl  → onFail
 *   - pendingUrl  → onPending (async settlement)
 *
 * Persisted in a RedirectUrlStore keyed by Qredit payment_reference, then read
 * back by the WebView host / web return controllers to navigate the customer.
 *
 * Mobile apps pass app deep-link URLs here ("eshophub://order/success?ref=...")
 * — they are intercepted by the WebView's shouldOverrideUrlLoading hook and
 * never actually navigated to as HTTP requests.
 */
final class QreditRedirectUrls
{
    /**
     * Schemes that must never be accepted as redirect targets. Anything not
     * on this list passes — so any custom app deep-link scheme is allowed.
     *
     * @var list<string>
     */
    public const DEFAULT_BLOCKED_SCHEMES = ['javascript', 'data', 'file', 'vbscript', 'blob'];

    public function __construct(
        public readonly ?string $successUrl = null,
        public readonly ?string $cancelUrl = null,
        public readonly ?string $failureUrl = null,
        public readonly ?string $pendingUrl = null,
    ) {}

    /**
     * Build from an associative array (keys: success_url / cancel_url /
     * failure_url / pending_url — snake_case to match the HTTP API).
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            successUrl: self::stringOrNull($data['success_url'] ?? null),
            cancelUrl: self::stringOrNull($data['cancel_url'] ?? null),
            failureUrl: self::stringOrNull($data['failure_url'] ?? null),
            pendingUrl: self::stringOrNull($data['pending_url'] ?? null),
        );
    }

    /**
     * @return array<string, ?string>
     */
    public function toArray(): array
    {
        return [
            'success_url' => $this->successUrl,
            'cancel_url' => $this->cancelUrl,
            'failure_url' => $this->failureUrl,
            'pending_url' => $this->pendingUrl,
        ];
    }

    public function isEmpty(): bool
    {
        return $this->successUrl === null
            && $this->cancelUrl === null
            && $this->failureUrl === null
            && $this->pendingUrl === null;
    }

    /**
     * Validate every non-null URL against the blocked-scheme denylist.
     * Throws on first violation.
     *
     * @param  list<string>  $blockedSchemes
     */
    public function assertAllowed(array $blockedSchemes = self::DEFAULT_BLOCKED_SCHEMES): void
    {
        foreach ($this->toArray() as $field => $url) {
            if ($url === null) {
                continue;
            }

            if (! self::isAcceptableUrl($url, $blockedSchemes)) {
                throw new InvalidArgumentException(
                    "Redirect URL for [{$field}] uses a disallowed scheme."
                );
            }
        }
    }

    /**
     * True if the URL is syntactically valid and its scheme is NOT on the denylist.
     *
     * @param  list<string>  $blockedSchemes
     */
    public static function isAcceptableUrl(string $url, array $blockedSchemes = self::DEFAULT_BLOCKED_SCHEMES): bool
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);

        if (! is_string($scheme) || $scheme === '') {
            return false;
        }

        if (in_array(strtolower($scheme), array_map('strtolower', $blockedSchemes), true)) {
            return false;
        }

        // filter_var(FILTER_VALIDATE_URL) is RFC-2396 only — it rejects custom
        // schemes like myapp://. Fall back to a permissive shape check.
        if (filter_var($url, FILTER_VALIDATE_URL) !== false) {
            return true;
        }

        return (bool) preg_match('#^[a-z][a-z0-9+.\-]*://#i', $url);
    }

    private static function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}

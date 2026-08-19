<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Helpers;

/**
 * Generate unique message IDs for Qredit API requests.
 *
 * Each request to the Qredit API requires a unique message ID
 * for tracking and idempotency purposes.
 */
class MessageIdGenerator
{
    /**
     * Predefined prefixes for different request types.
     */
    private const PREFIXES = [
        // Authentication
        'auth.token' => 'auth_token',
        'auth.refresh' => 'auth_refresh',
        'auth.revoke' => 'auth_revoke',

        // Payment Requests
        'payment.create' => 'pr_create',
        'payment.get' => 'pr_get',
        'payment.update' => 'pr_update',
        'payment.delete' => 'pr_delete',
        'payment.list' => 'pr_list',
        'payment.refund' => 'pr_refund',
        'payment.capture' => 'pr_capture',
        'payment.void' => 'pr_void',

        // Orders
        'order.create' => 'ord_create',
        'order.get' => 'ord_get',
        'order.update' => 'ord_update',
        'order.cancel' => 'ord_cancel',
        'order.list' => 'ord_list',
        'order.ship' => 'ord_ship',
        'order.complete' => 'ord_complete',

        // Transactions
        'transaction.list' => 'txn_list',
        'transaction.get' => 'txn_get',

        // Customers
        'customer.create' => 'cust_create',
        'customer.get' => 'cust_get',
        'customer.update' => 'cust_update',
        'customer.delete' => 'cust_delete',
        'customer.list' => 'cust_list',

        // Cards/Tokens
        'card.tokenize' => 'card_token',
        'card.get' => 'card_get',
        'card.delete' => 'card_delete',
        'card.list' => 'card_list',

        // Webhooks
        'webhook.create' => 'wh_create',
        'webhook.get' => 'wh_get',
        'webhook.update' => 'wh_update',
        'webhook.delete' => 'wh_delete',
        'webhook.list' => 'wh_list',
        'webhook.verify' => 'wh_verify',

        // Reports
        'report.transactions' => 'rpt_trans',
        'report.settlements' => 'rpt_settle',
        'report.disputes' => 'rpt_dispute',
        'report.summary' => 'rpt_summary',

        // Subscriptions
        'subscription.create' => 'sub_create',
        'subscription.get' => 'sub_get',
        'subscription.update' => 'sub_update',
        'subscription.cancel' => 'sub_cancel',
        'subscription.list' => 'sub_list',
        'subscription.pause' => 'sub_pause',
        'subscription.resume' => 'sub_resume',

        // Invoices
        'invoice.create' => 'inv_create',
        'invoice.get' => 'inv_get',
        'invoice.update' => 'inv_update',
        'invoice.send' => 'inv_send',
        'invoice.list' => 'inv_list',
        'invoice.pay' => 'inv_pay',

        // Generic/Fallback
        'generic' => 'req',
    ];

    /**
     * Generate a unique message ID with the specified type prefix.
     *
     * @param string $type The request type (e.g., 'payment.create', 'order.get')
     * @param array $context Optional context data to include in the ID
     * @return string The generated unique message ID
     */
    public static function generate(string $type, array $context = []): string
    {
        $prefix = self::getPrefix($type);
        $uniqueId = self::generateUniqueId();
        $timestamp = time();

        // If context is provided, include relevant data
        $contextPart = '';
        if (!empty($context)) {
            $contextPart = '_' . self::generateContextHash($context);
        }

        return sprintf('%s_%s_%d%s', $prefix, $uniqueId, $timestamp, $contextPart);
    }

    /**
     * Generate a simple unique message ID with custom prefix.
     *
     * @param string $prefix Custom prefix for the message ID
     * @return string The generated unique message ID
     */
    public static function generateSimple(string $prefix): string
    {
        return sprintf('%s_%s_%d', $prefix, self::generateUniqueId(), time());
    }

    /**
     * Generate a unique message ID for idempotency.
     * This ensures the same request data always generates the same ID.
     *
     * @param string $type The request type
     * @param array $data The request data
     * @return string The generated idempotent message ID
     */
    public static function generateIdempotent(string $type, array $data): string
    {
        $prefix = self::getPrefix($type);

        // Sort data to ensure consistent hashing
        ksort($data);
        $dataHash = hash('xxh3', json_encode($data));

        return sprintf('%s_%s_%d', $prefix, $dataHash, time());
    }

    /**
     * Generate a unique message ID for batch operations.
     *
     * @param string $type The request type
     * @param string $batchId The batch identifier
     * @param int $index The item index in the batch
     * @return string The generated batch message ID
     */
    public static function generateBatch(string $type, string $batchId, int $index): string
    {
        $prefix = self::getPrefix($type);

        return sprintf('%s_batch_%s_%d_%d', $prefix, $batchId, $index, time());
    }

    /**
     * Validate if a message ID follows the expected format.
     *
     * @param string $messageId The message ID to validate
     * @return bool True if valid, false otherwise
     */
    public static function validate(string $messageId): bool
    {
        // Pattern: prefix_uniqueId_timestamp[_optional]
        $pattern = '/^[a-z]+(_[a-z]+)?_[a-f0-9\.]+_\d{10}(_[a-z0-9]+)?$/i';

        return (bool) preg_match($pattern, $messageId);
    }

    /**
     * Extract components from a message ID.
     *
     * @param string $messageId The message ID to parse
     * @return array|null Array with components or null if invalid
     */
    public static function parse(string $messageId): ?array
    {
        if (!self::validate($messageId)) {
            return null;
        }

        $parts = explode('_', $messageId);

        // Handle different formats
        if (count($parts) >= 3) {
            $timestamp = (int) $parts[count($parts) - 1];

            // Check if last part is timestamp or context
            if ($timestamp < 1000000000) {
                // Last part is context, timestamp is second to last
                $timestamp = (int) $parts[count($parts) - 2];
                $context = $parts[count($parts) - 1];
            } else {
                $context = null;
            }

            return [
                'prefix' => implode('_', array_slice($parts, 0, -2)),
                'unique_id' => $parts[count($parts) - 2],
                'timestamp' => $timestamp,
                'context' => $context,
                'datetime' => date('Y-m-d H:i:s', $timestamp),
            ];
        }

        return null;
    }

    /**
     * Get the prefix for a given request type.
     *
     * @param string $type The request type
     * @return string The corresponding prefix
     */
    private static function getPrefix(string $type): string
    {
        return self::PREFIXES[$type] ?? self::PREFIXES['generic'];
    }

    /**
     * Generate a unique identifier.
     *
     * @return string A unique identifier
     */
    private static function generateUniqueId(): string
    {
        // Use more_entropy for better uniqueness
        $uniqid = uniqid('', true);

        // Add random bytes for extra entropy
        $randomBytes = bin2hex(random_bytes(4));

        return $uniqid . $randomBytes;
    }

    /**
     * Generate a hash from context data.
     *
     * @param array $context The context data
     * @return string A short hash of the context
     */
    private static function generateContextHash(array $context): string
    {
        ksort($context);
        $hash = hash('xxh3', json_encode($context));

        // Return first 8 characters of hash
        return substr($hash, 0, 8);
    }

    /**
     * Check if a message ID is expired based on TTL.
     *
     * @param string $messageId The message ID to check
     * @param int $ttlSeconds Time to live in seconds (default: 3600)
     * @return bool True if expired, false otherwise
     */
    public static function isExpired(string $messageId, int $ttlSeconds = 3600): bool
    {
        $parsed = self::parse($messageId);

        if ($parsed === null) {
            return true;
        }

        $expiryTime = $parsed['timestamp'] + $ttlSeconds;

        return time() > $expiryTime;
    }

    /**
     * Generate a message ID for testing purposes.
     *
     * @param string $type The request type
     * @return string A test message ID
     */
    public static function generateTest(string $type): string
    {
        $prefix = self::getPrefix($type);

        return sprintf('%s_test_%s_%d', $prefix, uniqid(), time());
    }
}
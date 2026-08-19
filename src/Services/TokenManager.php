<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Qredit\LaravelQredit\Exceptions\QreditAuthenticationException;

/**
 * Token Manager for Qredit API Authentication
 *
 * WHY TOKEN CACHING IS ESSENTIAL:
 * 1. Reduces API calls (most APIs have rate limits)
 * 2. Improves performance (no auth request for every API call)
 * 3. Reduces latency (cached token vs network request)
 * 4. Cost efficiency (some APIs charge per request)
 * 5. Better user experience (faster response times)
 *
 * This manager supports multiple storage strategies:
 * - Cache (Redis/Memcached) - Best for single server
 * - Database - Best for multi-server environments
 * - Hybrid - Best of both worlds
 */
class TokenManager
{
    /**
     * Token storage strategies
     */
    public const STRATEGY_CACHE = 'cache';
    public const STRATEGY_DATABASE = 'database';
    public const STRATEGY_HYBRID = 'hybrid'; // Cache with DB fallback

    /**
     * Default TTL buffer (seconds before expiry to refresh)
     */
    private const TTL_BUFFER = 300; // 5 minutes

    /**
     * Storage strategy
     */
    private string $strategy;

    /**
     * Cache key prefix
     */
    private string $cachePrefix;

    /**
     * Environment (sandbox/production)
     */
    private bool $sandbox;

    public function __construct(?string $strategy = null, bool $sandbox = false)
    {
        $this->strategy = $strategy ?? config('qredit.token_storage.strategy', self::STRATEGY_CACHE);
        $this->sandbox = $sandbox;
        $this->cachePrefix = 'qredit_token_' . ($sandbox ? 'sandbox_' : 'prod_');
    }

    /**
     * Get stored token if valid
     *
     * @param string $apiKey The API key to get token for
     * @return string|null The token if valid, null otherwise
     */
    public function getToken(string $apiKey): ?string
    {
        $key = $this->generateKey($apiKey);

        switch ($this->strategy) {
            case self::STRATEGY_CACHE:
                return $this->getFromCache($key);

            case self::STRATEGY_DATABASE:
                return $this->getFromDatabase($key);

            case self::STRATEGY_HYBRID:
                // Try cache first
                $token = $this->getFromCache($key);
                if ($token) {
                    return $token;
                }

                // Fallback to database
                $token = $this->getFromDatabase($key);
                if ($token) {
                    // Restore to cache
                    $this->storeInCache($key, $token, 3600);
                    return $token;
                }

                return null;

            default:
                return $this->getFromCache($key);
        }
    }

    /**
     * Store token with expiration
     *
     * @param string $apiKey The API key
     * @param string $token The token to store
     * @param int $expiresIn Expiration time in seconds
     * @param array $metadata Additional metadata (optional)
     */
    public function storeToken(string $apiKey, string $token, int $expiresIn, array $metadata = []): void
    {
        $key = $this->generateKey($apiKey);

        // Apply buffer to expire token before actual expiry
        $effectiveExpiry = max($expiresIn - self::TTL_BUFFER, 60);

        switch ($this->strategy) {
            case self::STRATEGY_CACHE:
                $this->storeInCache($key, $token, $effectiveExpiry, $metadata);
                break;

            case self::STRATEGY_DATABASE:
                $this->storeInDatabase($key, $token, $effectiveExpiry, $metadata);
                break;

            case self::STRATEGY_HYBRID:
                // Store in both
                $this->storeInCache($key, $token, $effectiveExpiry, $metadata);
                $this->storeInDatabase($key, $token, $effectiveExpiry, $metadata);
                break;
        }
    }

    /**
     * Clear stored token
     *
     * @param string $apiKey The API key
     */
    public function clearToken(string $apiKey): void
    {
        $key = $this->generateKey($apiKey);

        switch ($this->strategy) {
            case self::STRATEGY_CACHE:
                Cache::forget($key);
                break;

            case self::STRATEGY_DATABASE:
                $this->deleteFromDatabase($key);
                break;

            case self::STRATEGY_HYBRID:
                Cache::forget($key);
                $this->deleteFromDatabase($key);
                break;
        }
    }

    /**
     * Check if token needs refresh
     *
     * @param string $apiKey The API key
     * @return bool True if token needs refresh
     */
    public function needsRefresh(string $apiKey): bool
    {
        $key = $this->generateKey($apiKey);

        // Check if token exists and is near expiry
        $data = $this->getTokenData($key);

        if (!$data) {
            return true;
        }

        $expiresAt = Carbon::parse($data['expires_at'] ?? 0);
        $bufferTime = Carbon::now()->addSeconds(self::TTL_BUFFER);

        // Refresh if token expires within buffer time
        return $expiresAt->lessThanOrEqualTo($bufferTime);
    }

    /**
     * Get token with automatic refresh
     *
     * @param string $apiKey The API key
     * @param callable $refreshCallback Callback to refresh token
     * @return string The valid token
     */
    public function getOrRefresh(string $apiKey, callable $refreshCallback): string
    {
        // Check if we have a valid token
        $token = $this->getToken($apiKey);

        if ($token && !$this->needsRefresh($apiKey)) {
            return $token;
        }

        // Refresh token
        $refreshData = $refreshCallback($apiKey);

        if (!isset($refreshData['token']) || !isset($refreshData['expires_in'])) {
            throw new QreditAuthenticationException('Invalid refresh response');
        }

        // Store new token
        $this->storeToken(
            $apiKey,
            $refreshData['token'],
            $refreshData['expires_in'],
            $refreshData['metadata'] ?? []
        );

        return $refreshData['token'];
    }

    /**
     * Get from cache storage
     */
    private function getFromCache(string $key): ?string
    {
        $data = Cache::get($key);

        if (!$data) {
            return null;
        }

        // Check expiration
        if (isset($data['expires_at']) && Carbon::parse($data['expires_at'])->isPast()) {
            Cache::forget($key);
            return null;
        }

        return $data['token'] ?? null;
    }

    /**
     * Store in cache
     */
    private function storeInCache(string $key, string $token, int $expiresIn, array $metadata = []): void
    {
        $data = [
            'token' => $token,
            'expires_at' => Carbon::now()->addSeconds($expiresIn)->toIso8601String(),
            'created_at' => Carbon::now()->toIso8601String(),
            'metadata' => $metadata,
        ];

        Cache::put($key, $data, $expiresIn);
    }

    /**
     * Get from database storage
     */
    private function getFromDatabase(string $key): ?string
    {
        // Check if tokens table exists
        if (!$this->tokensTableExists()) {
            return null;
        }

        $record = DB::table('qredit_tokens')
            ->where('key', $key)
            ->where('expires_at', '>', Carbon::now())
            ->first();

        return $record ? $record->token : null;
    }

    /**
     * Store in database
     */
    private function storeInDatabase(string $key, string $token, int $expiresIn, array $metadata = []): void
    {
        // Ensure table exists
        $this->ensureTokensTable();

        DB::table('qredit_tokens')->updateOrInsert(
            ['key' => $key],
            [
                'token' => $token,
                'expires_at' => Carbon::now()->addSeconds($expiresIn),
                'metadata' => json_encode($metadata),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]
        );
    }

    /**
     * Delete from database
     */
    private function deleteFromDatabase(string $key): void
    {
        if ($this->tokensTableExists()) {
            DB::table('qredit_tokens')->where('key', $key)->delete();
        }
    }

    /**
     * Get full token data
     */
    private function getTokenData(string $key): ?array
    {
        switch ($this->strategy) {
            case self::STRATEGY_CACHE:
                return Cache::get($key);

            case self::STRATEGY_DATABASE:
                $record = DB::table('qredit_tokens')
                    ->where('key', $key)
                    ->first();

                return $record ? [
                    'token' => $record->token,
                    'expires_at' => $record->expires_at,
                    'metadata' => json_decode($record->metadata ?? '{}', true),
                ] : null;

            case self::STRATEGY_HYBRID:
                $data = Cache::get($key);
                if ($data) {
                    return $data;
                }

                return $this->getTokenData($key);

            default:
                return null;
        }
    }

    /**
     * Generate cache key
     */
    private function generateKey(string $apiKey): string
    {
        // Hash API key for security (don't store raw API keys)
        return $this->cachePrefix . hash('sha256', $apiKey);
    }

    /**
     * Check if tokens table exists
     */
    private function tokensTableExists(): bool
    {
        return Cache::remember('qredit_tokens_table_exists', 3600, function () {
            return DB::getSchemaBuilder()->hasTable('qredit_tokens');
        });
    }

    /**
     * Ensure tokens table exists
     */
    private function ensureTokensTable(): void
    {
        if (!$this->tokensTableExists()) {
            DB::getSchemaBuilder()->create('qredit_tokens', function ($table) {
                $table->string('key')->primary();
                $table->text('token');
                $table->timestamp('expires_at');
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index('expires_at');
            });

            Cache::put('qredit_tokens_table_exists', true, 3600);
        }
    }

    /**
     * Clean expired tokens (maintenance task)
     */
    public function cleanExpiredTokens(): int
    {
        if ($this->strategy === self::STRATEGY_DATABASE || $this->strategy === self::STRATEGY_HYBRID) {
            if ($this->tokensTableExists()) {
                return DB::table('qredit_tokens')
                    ->where('expires_at', '<', Carbon::now())
                    ->delete();
            }
        }

        return 0;
    }
}
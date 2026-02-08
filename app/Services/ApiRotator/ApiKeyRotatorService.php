<?php

namespace App\Services\ApiRotator;

use App\Events\ApiKeyExhausted;
use App\Events\ApiKeyRotated;
use App\Models\ApiKey;
use App\Models\ApiKeyCooldown;
use App\Models\ApiKeyUsageLog;
use App\Services\ApiRotator\Contracts\ProviderAdapterInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ApiKeyRotatorService
{
    private ProviderAdapterFactory $adapterFactory;
    private array $config;

    public function __construct(ProviderAdapterFactory $adapterFactory)
    {
        $this->adapterFactory = $adapterFactory;
        $this->config = [
            'proactive_threshold' => config('api_rotator.proactive_threshold', 0.8),
            'max_retries' => config('api_rotator.max_retries', 3),
            'base_retry_delay' => config('api_rotator.base_retry_delay', 5),
        ];
    }

    /**
     * Get the next available API key for a given task type
     */
    public function getAvailableKey(string $taskType = 'general', ?string $preferredProvider = null): ?ApiKey
    {
        $query = ApiKey::active()
            ->available()
            ->byPriority();

        // Filter by task requirements
        if ($taskType === 'pdf') {
            $query->supportsPdf();
        }

        // Prefer specific provider if requested
        if ($preferredProvider) {
            $preferred = (clone $query)->forProvider($preferredProvider)->first();
            if ($preferred && $preferred->hasAvailableQuota()) {
                return $preferred;
            }
        }

        // Get all available keys and find best option
        $keys = $query->get();

        foreach ($keys as $key) {
            // Check proactive threshold AND available quota (RPM, RPD, TPM)
            $quotaUsed = $key->getQuotaPercentageUsed();
            if ($quotaUsed['rpd'] < ($this->config['proactive_threshold'] * 100) && $key->hasAvailableQuota()) {
                return $key;
            }
        }

        // If all keys are above threshold, return the one with most remaining quota
        // Filter out keys with no remaining quota
        return $keys->filter(fn ($k) => $k->hasAvailableQuota())
            ->sortByDesc(fn ($k) => $k->getRemainingRpd())
            ->first();
    }

    /**
     * Execute a document analysis with automatic key rotation
     */
    public function analyzeDocument(
        string $pdfPath,
        string $systemPrompt,
        string $userPrompt,
        string $taskType = 'pdf',
        ?string $documentId = null,
        ?string $batchId = null
    ): array {
        $attempts = 0;
        $maxAttempts = $this->config['max_retries'] * 3; // Allow cycling through multiple keys
        $triedKeys = [];
        $lastError = null;
        $previousKeyId = null;

        while ($attempts < $maxAttempts) {
            $attempts++;

            // Get next available key (excluding already tried) with pessimistic locking
            $key = DB::transaction(function () use ($taskType, $triedKeys) {
                return $this->getNextKeyWithLock($taskType, $triedKeys);
            });

            if (! $key) {
                Log::warning('ApiKeyRotator: No available keys', [
                    'task_type' => $taskType,
                    'tried_keys' => count($triedKeys),
                ]);
                break;
            }

            // Dispatch rotation event when switching from one key to another
            if ($previousKeyId !== null) {
                ApiKeyRotated::dispatch($previousKeyId, $key->id, 'rate_limit_or_error', $key->provider);
                Log::info('ApiKeyRotator: Rotated key', [
                    'from' => $previousKeyId,
                    'to' => $key->id,
                    'attempt' => $attempts,
                    'provider' => $key->provider,
                ]);
            }

            $triedKeys[] = $key->id;
            $previousKeyId = $key->id;
            $adapter = $this->adapterFactory->make($key->provider);

            try {
                $startTime = microtime(true);
                $result = $adapter->analyzeDocument($key, $pdfPath, $systemPrompt, $userPrompt);
                $responseTime = (int) ((microtime(true) - $startTime) * 1000);

                $response = $result['response'];

                if ($response->successful() && $result['parsed']) {
                    // Success - log and update counters
                    $this->recordSuccess($key, $adapter, $result, $responseTime, $documentId, $batchId, $taskType);

                    return [
                        'success' => true,
                        'data' => $result['parsed'],
                        'provider' => $key->provider,
                        'model' => $key->model,
                        'key_id' => $key->id,
                        'attempts' => $attempts,
                    ];
                }

                // Handle errors
                $this->handleError($key, $adapter, $response, $responseTime, $documentId, $batchId, $taskType);
                $lastError = $response->body();

                // If recoverable, continue to next key
                if (! $adapter->isRecoverableError($response)) {
                    // Permanent error - stop trying
                    break;
                }

            } catch (\Throwable $e) {
                Log::error('ApiKeyRotator: Exception', [
                    'key_id' => $key->id,
                    'provider' => $key->provider,
                    'error' => $e->getMessage(),
                ]);
                $lastError = $e->getMessage();

                // Create short cooldown for this key
                $this->createCooldown($key, 'error', 30, 'Exception: '.$e->getMessage());
            }

            // Apply exponential backoff before next attempt
            if ($attempts < $maxAttempts) {
                $delay = $this->config['base_retry_delay'] * pow(2, min($attempts - 1, 4));
                usleep((int) ($delay * 1000000)); // Convert to microseconds
            }
        }

        // Dispatch exhaustion event when all keys have been tried or none available
        ApiKeyExhausted::dispatch($taskType, count($triedKeys));
        Log::warning('ApiKeyRotator: All keys exhausted', [
            'task_type' => $taskType,
            'keys_attempted' => count($triedKeys),
        ]);

        return [
            'success' => false,
            'error' => $lastError ?? 'All keys exhausted',
            'attempts' => $attempts,
            'tried_keys' => count($triedKeys),
        ];
    }

    /**
     * Get next available key, excluding already tried ones
     */
    private function getNextKey(string $taskType, array $excludeIds): ?ApiKey
    {
        $query = ApiKey::active()
            ->available()
            ->byPriority();

        if ($taskType === 'pdf') {
            $query->supportsPdf();
        }

        if (! empty($excludeIds)) {
            $query->whereNotIn('id', $excludeIds);
        }

        return $query->get()
            ->filter(fn ($k) => $k->hasAvailableQuota())
            ->first();
    }

    /**
     * Get next available key with pessimistic locking
     */
    public function getNextKeyWithLock(string $taskType, array $excludeIds): ?ApiKey
    {
        $query = ApiKey::active()
            ->whereDoesntHave('cooldowns', function ($q) {
                $q->where('ends_at', '>', now());
            })
            ->lockForUpdate() // Pessimistic lock
            ->orderByDesc('priority');

        if ($taskType === 'pdf') {
            $query->where('supports_pdf', true);
        }

        if (! empty($excludeIds)) {
            $query->whereNotIn('id', $excludeIds);
        }

        return $query->get()
            ->filter(fn ($k) => $k->hasAvailableQuota())
            ->first();
    }

    /**
     * Record successful API call
     */
    private function recordSuccess(
        ApiKey $key,
        ProviderAdapterInterface $adapter,
        array $result,
        int $responseTime,
        ?string $documentId,
        ?string $batchId,
        string $taskType = 'general'
    ): void {
        $usage = $result['parsed']['usage'] ?? [];

        DB::transaction(function () use ($key, $usage, $responseTime, $documentId, $batchId, $result, $taskType) {
            // Update usage counters
            $key->increment('rpm_used');
            $key->increment('rpd_used');

            if ($totalTokens = ($usage['total_tokens'] ?? 0)) {
                $key->increment('tpm_used', $totalTokens);
            }

            // Ensure reset timestamps are set
            if (! $key->rpm_reset_at || $key->rpm_reset_at->isPast()) {
                $key->rpm_reset_at = now()->addMinute();
            }

            $key->save();

            // Log the usage
            ApiKeyUsageLog::create([
                'api_key_id' => $key->id,
                'model_used' => $key->model,
                'task_type' => $taskType,
                'prompt_tokens' => $usage['prompt_tokens'] ?? 0,
                'completion_tokens' => $usage['completion_tokens'] ?? 0,
                'total_tokens' => $usage['total_tokens'] ?? 0,
                'http_status' => 200,
                'response_time_ms' => $responseTime,
                'was_successful' => true,
                'rate_limit_headers' => $result['rate_limit_info']['raw_headers'] ?? null,
                'document_id' => $documentId,
                'batch_id' => $batchId,
            ]);
        });
    }

    /**
     * Handle API error response
     */
    private function handleError(
        ApiKey $key,
        ProviderAdapterInterface $adapter,
        $response,
        int $responseTime,
        ?string $documentId,
        ?string $batchId,
        string $taskType = 'general'
    ): void {
        $status = $response->status();
        $isRateLimited = $status === 429;

        DB::transaction(function () use ($key, $adapter, $response, $responseTime, $documentId, $batchId, $status, $isRateLimited, $taskType) {
            // Log the failed attempt
            ApiKeyUsageLog::create([
                'api_key_id' => $key->id,
                'model_used' => $key->model,
                'task_type' => $taskType,
                'http_status' => $status,
                'response_time_ms' => $responseTime,
                'was_successful' => false,
                'was_rate_limited' => $isRateLimited,
                'error_code' => (string) $status,
                'error_message' => substr($response->body(), 0, 1000),
                'document_id' => $documentId,
                'batch_id' => $batchId,
            ]);

            // Handle rate limiting
            if ($isRateLimited) {
                $rateLimitInfo = $adapter->parseRateLimitError($response);

                $cooldownType = ($rateLimitInfo['is_daily_limit'] ?? false) ? 'rpd' : 'rpm';
                $cooldownSeconds = $rateLimitInfo['retry_after'] ?? 60;
                $endsAt = $rateLimitInfo['reset_at'] ?? now()->addSeconds($cooldownSeconds);

                $this->createCooldown(
                    $key,
                    $cooldownType,
                    now()->diffInSeconds($endsAt),
                    $rateLimitInfo['error_message'] ?? 'Rate limited',
                    'header',
                    $cooldownSeconds,
                    $rateLimitInfo
                );
            }
        });
    }

    /**
     * Create a cooldown period for a key
     */
    public function createCooldown(
        ApiKey $key,
        string $type,
        int $seconds,
        ?string $reason = null,
        string $source = 'client',
        ?int $retryAfter = null,
        ?array $metadata = null
    ): ApiKeyCooldown {
        return ApiKeyCooldown::create([
            'api_key_id' => $key->id,
            'cooldown_type' => $type,
            'started_at' => now(),
            'ends_at' => now()->addSeconds($seconds),
            'source' => $source,
            'retry_after_seconds' => $retryAfter,
            'reason' => $reason,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Reset minute-based counters for all keys
     */
    public function resetMinuteCounters(): int
    {
        return ApiKey::where('rpm_reset_at', '<=', now())
            ->update([
                'rpm_used' => 0,
                'tpm_used' => 0,
                'rpm_reset_at' => now()->addMinute(),
            ]);
    }

    /**
     * Reset daily counters for keys based on their provider's reset time
     */
    public function resetDailyCounters(): int
    {
        $count = 0;

        foreach ($this->adapterFactory->all() as $providerName => $adapter) {
            $resetTime = $adapter->getDailyResetTime();

            // Reset if we've passed the reset time
            $updated = ApiKey::forProvider($providerName)
                ->where(function ($q) use ($resetTime) {
                    $q->whereNull('rpd_reset_at')
                      ->orWhere('rpd_reset_at', '<=', now());
                })
                ->update([
                    'rpd_used' => 0,
                    'tpd_used' => 0,
                    'rpd_reset_at' => $resetTime,
                ]);

            $count += $updated;
        }

        // Also clean up expired cooldowns
        ApiKeyCooldown::where('ends_at', '<=', now())->delete();

        return $count;
    }

    /**
     * Get status of all API keys
     */
    public function getStatus(): array
    {
        return ApiKey::active()
            ->with('activeCooldowns')
            ->get()
            ->map(function ($key) {
                $quotaUsed = $key->getQuotaPercentageUsed();

                return [
                    'id' => $key->id,
                    'name' => $key->name,
                    'provider' => $key->provider,
                    'model' => $key->model,
                    'rpm' => "{$key->rpm_used}/{$key->rpm_limit} ({$quotaUsed['rpm']}%)",
                    'rpd' => "{$key->rpd_used}/{$key->rpd_limit} ({$quotaUsed['rpd']}%)",
                    'in_cooldown' => $key->isInCooldown(),
                    'cooldown_ends' => $key->activeCooldowns->first()?->ends_at?->diffForHumans(),
                    'available' => ! $key->isInCooldown() && $key->hasAvailableQuota(),
                ];
            })
            ->toArray();
    }
}

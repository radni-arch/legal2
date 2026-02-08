<?php

namespace App\Services\ApiRotator\Contracts;

use App\Models\ApiKey;
use Illuminate\Http\Client\Response;

interface ProviderAdapterInterface
{
    /**
     * Get the provider identifier
     */
    public function getProviderName(): string;

    /**
     * Make an API request for document analysis
     */
    public function analyzeDocument(ApiKey $apiKey, string $pdfPath, string $systemPrompt, string $userPrompt): array;

    /**
     * Parse rate limit information from response
     * Returns: ['rpm_remaining', 'rpd_remaining', 'retry_after', 'reset_at', 'raw_headers']
     */
    public function parseRateLimitInfo(Response $response): array;

    /**
     * Parse rate limit info from error response (429)
     */
    public function parseRateLimitError(Response $response): array;

    /**
     * Determine if error is recoverable (should retry) or permanent
     */
    public function isRecoverableError(Response $response): bool;

    /**
     * Get default rate limits for this provider
     */
    public function getDefaultLimits(): array;

    /**
     * Calculate token usage from response
     */
    public function extractTokenUsage(array $responseData): array;

    /**
     * Get the daily reset time for this provider
     */
    public function getDailyResetTime(): \DateTimeInterface;
}

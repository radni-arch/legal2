<?php

namespace App\Services\Analysis\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class ClaudeAnalysisService
{
    private string $model;
    private string $apiKey;
    private int $maxTokens;
    private int $maxRequestsPerMinute;

    public function __construct()
    {
        $this->model = config('services.claude.model', 'claude-sonnet-4-5-20250929');
        $this->apiKey = config('services.claude.api_key');
        $this->maxTokens = config('services.claude.max_tokens', 4096);
        $this->maxRequestsPerMinute = config('services.claude.rate_limit', 50);
    }

    /**
     * Send a structured analysis prompt to Claude.
     *
     * @return array{content: string, usage: array, cost: float, processing_time: float, model: string}
     * @throws \RuntimeException If rate limit exceeded or API error
     */
    public function analyze(string $systemPrompt, string $userContent): array
    {
        // Rate limiting - wait if limit exceeded
        $this->enforceRateLimit();

        $startTime = microtime(true);

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])
            ->timeout(120)
            ->retry(3, 5000, throw: false)
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => $this->model,
                'max_tokens' => $this->maxTokens,
                'system' => $systemPrompt,
                'messages' => [
                    ['role' => 'user', 'content' => $userContent],
                ],
            ]);

        if (!$response->successful()) {
            Log::error('Claude API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException("Claude API error: {$response->status()} — {$response->body()}");
        }

        $data = $response->json();
        $usage = $data['usage'] ?? [];

        // Calculate cost (Sonnet 4.5 pricing as of 2025)
        $inputCost = ($usage['input_tokens'] ?? 0) / 1_000_000 * 3.0;
        $outputCost = ($usage['output_tokens'] ?? 0) / 1_000_000 * 15.0;

        return [
            'content' => collect($data['content'] ?? [])
                ->where('type', 'text')
                ->pluck('text')
                ->implode("\n"),
            'usage' => $usage,
            'cost' => round($inputCost + $outputCost, 6),
            'processing_time' => round(microtime(true) - $startTime, 4),
            'model' => $this->model,
        ];
    }

    /**
     * Analyze with JSON response expected.
     *
     * @return array{content: string, usage: array, cost: float, processing_time: float, model: string, parsed: array}
     */
    public function analyzeJson(string $systemPrompt, string $userContent): array
    {
        $systemPrompt .= "\n\nIMPORTANT: Respond ONLY with valid JSON. No markdown fences, no preamble.";

        $result = $this->analyze($systemPrompt, $userContent);

        // Clean and parse JSON
        $jsonStr = trim($result['content']);
        $jsonStr = preg_replace('/^```(?:json)?\s*/i', '', $jsonStr);
        $jsonStr = preg_replace('/\s*```$/', '', $jsonStr);

        $parsed = json_decode($jsonStr, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("Failed to parse Claude JSON response: " . json_last_error_msg());
        }

        $result['parsed'] = $parsed;
        return $result;
    }

    /**
     * Enforce rate limiting for Claude API calls.
     *
     * Uses a sliding window rate limiter to prevent exceeding API limits.
     * Will wait (with exponential backoff) if rate limit is temporarily exceeded.
     *
     * @throws \RuntimeException If rate limit exceeded after max retries
     */
    private function enforceRateLimit(): void
    {
        $key = 'claude-api-requests';
        $maxAttempts = 3;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            $executed = RateLimiter::attempt(
                key: $key,
                maxAttempts: $this->maxRequestsPerMinute,
                callback: fn() => true,
                decaySeconds: 60
            );

            if ($executed) {
                return;
            }

            // Rate limit hit - wait with exponential backoff
            $attempt++;
            $waitSeconds = pow(2, $attempt); // 2, 4, 8 seconds

            Log::warning("Claude API rate limit reached, waiting {$waitSeconds}s", [
                'attempt' => $attempt,
                'max_attempts' => $maxAttempts,
                'available_in' => RateLimiter::availableIn($key),
            ]);

            if ($attempt < $maxAttempts) {
                sleep($waitSeconds);
            }
        }

        $availableIn = RateLimiter::availableIn($key);
        throw new \RuntimeException(
            "Claude API rate limit exceeded. Try again in {$availableIn} seconds."
        );
    }

    /**
     * Get current rate limit status.
     *
     * @return array{remaining: int, limit: int, reset_in: int}
     */
    public function getRateLimitStatus(): array
    {
        $key = 'claude-api-requests';

        return [
            'remaining' => RateLimiter::remaining($key, $this->maxRequestsPerMinute),
            'limit' => $this->maxRequestsPerMinute,
            'reset_in' => RateLimiter::availableIn($key),
        ];
    }
}

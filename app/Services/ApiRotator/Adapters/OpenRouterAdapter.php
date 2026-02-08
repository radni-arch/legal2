<?php

namespace App\Services\ApiRotator\Adapters;

use App\Models\ApiKey;
use App\Services\ApiRotator\Contracts\ProviderAdapterInterface;
use Carbon\Carbon;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class OpenRouterAdapter implements ProviderAdapterInterface
{
    private string $baseUrl = 'https://openrouter.ai/api/v1/responses';

    public function getProviderName(): string
    {
        return 'openrouter';
    }

    public function analyzeDocument(ApiKey $apiKey, string $pdfPath, string $systemPrompt, string $userPrompt): array
    {
        $pdfData = File::get($pdfPath);
        $base64Pdf = base64_encode($pdfData);
        $model = $apiKey->model ?? 'google/gemini-2.0-flash-exp:free';
        $docId = basename($pdfPath, '.pdf');

        $requestBody = [
            'model' => $model,
            'input' => [
                [
                    'role' => 'system',
                    'content' => $systemPrompt,
                ],
                [
                    'role' => 'user',
                    'type' => 'message',
                    'content' => [
                        [
                            'type' => 'input_text',
                            'text' => $userPrompt,
                        ],
                        [
                            'type' => 'input_file',
                            'file_data' => "data:application/pdf;base64,{$base64Pdf}",
                            'filename' => "{$docId}.pdf",
                        ],
                    ],
                ],
            ],
            'plugins' => [
                [
                    'id' => 'file-parser',
                    'pdf' => ['engine' => 'pdf-text'],
                ],
            ],
            'stream' => false,
            'max_output_tokens' => $this->computeSafeMaxOutputTokens($base64Pdf),
        ];

        $response = Http::timeout(300)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => "Bearer {$apiKey->api_key}",
                'X-Title' => 'Court Decision Analyzer',
            ])
            ->post($this->baseUrl, $requestBody);

        return [
            'response' => $response,
            'parsed' => $response->successful() ? $this->parseSuccessResponse($response->json()) : null,
            'rate_limit_info' => $this->parseRateLimitInfo($response),
        ];
    }

    public function parseRateLimitInfo(Response $response): array
    {
        // OpenRouter doesn't provide proactive headers on success
        return [
            'rpm_remaining' => null,
            'rpd_remaining' => null,
            'retry_after' => null,
            'reset_at' => null,
            'raw_headers' => [],
            'requires_client_tracking' => true,
        ];
    }

    public function parseRateLimitError(Response $response): array
    {
        $body = $response->json() ?? [];
        $metadata = $body['error']['metadata']['headers'] ?? [];

        // OpenRouter provides detailed info in error metadata
        $resetMs = $metadata['X-RateLimit-Reset'] ?? null;
        $resetAt = $resetMs ? Carbon::createFromTimestampMs((int) $resetMs) : null;

        // Parse the error message to determine limit type
        $errorMessage = $body['error']['message'] ?? '';
        $isDaily = str_contains($errorMessage, 'limit_rpd');

        $retryAfter = $resetAt ? max(0, now()->diffInSeconds($resetAt, false)) : 60;

        return [
            'retry_after' => $retryAfter,
            'is_daily_limit' => $isDaily,
            'rpm_remaining' => $metadata['X-RateLimit-Remaining'] ?? null,
            'rpm_limit' => $metadata['X-RateLimit-Limit'] ?? null,
            'reset_at' => $resetAt ?? now()->addMinutes(1),
            'error_message' => $errorMessage,
            'raw_metadata' => $metadata,
        ];
    }

    public function isRecoverableError(Response $response): bool
    {
        $status = $response->status();

        if (in_array($status, [500, 502, 503])) {
            return true;
        }

        if ($status === 429) {
            // Check if it's daily limit (not recoverable today)
            $body = $response->json() ?? [];
            $errorMessage = $body['error']['message'] ?? '';

            return ! str_contains($errorMessage, 'limit_rpd');
        }

        return false;
    }

    public function getDefaultLimits(): array
    {
        return [
            // Free tier (< $10 lifetime spend)
            'free' => ['rpm' => 20, 'rpd' => 50, 'tpm' => 0],
            // With $10+ credits
            'paid' => ['rpm' => 20, 'rpd' => 1000, 'tpm' => 0],
        ];
    }

    public function extractTokenUsage(array $responseData): array
    {
        $usage = $responseData['usage'] ?? [];

        return [
            'prompt_tokens' => $usage['prompt_tokens'] ?? 0,
            'completion_tokens' => $usage['completion_tokens'] ?? 0,
            'total_tokens' => $usage['total_tokens'] ?? 0,
        ];
    }

    public function getDailyResetTime(): \DateTimeInterface
    {
        // OpenRouter resets at midnight UTC
        return Carbon::now('UTC')
            ->addDay()
            ->startOfDay()
            ->setTimezone(config('app.timezone', 'UTC'));
    }

    /**
     * Calculate safe maximum output tokens based on PDF size and context limit
     */
    public function computeSafeMaxOutputTokens(string $base64Pdf): int
    {
        $approxInputTokens = (int) ceil(strlen($base64Pdf) / 4);
        $contextLimit = 163840;
        $reservedForNonPdf = 8000;
        $remaining = $contextLimit - $reservedForNonPdf - $approxInputTokens;

        if ($remaining <= 0) {
            return 256;
        }

        return max(256, min(4096, $remaining));
    }

    private function parseSuccessResponse(array $data): ?array
    {
        $outputText = null;
        $reasoning = null;

        foreach ($data['output'] ?? [] as $output) {
            if ($output['type'] === 'reasoning') {
                foreach ($output['content'] ?? [] as $content) {
                    if ($content['type'] === 'reasoning_text') {
                        $reasoning = $content['text'];
                    }
                }
            }

            if (($output['type'] ?? null) === 'message' && ($output['role'] ?? null) === 'assistant') {
                foreach ($output['content'] ?? [] as $content) {
                    if ($content['type'] === 'output_text') {
                        $outputText = $content['text'];
                    }
                }
            }
        }

        if (! $outputText) {
            return null;
        }

        return [
            'content' => $outputText,
            'reasoning' => $reasoning,
            'finish_reason' => 'stop',
            'usage' => $this->extractTokenUsage($data),
        ];
    }
}

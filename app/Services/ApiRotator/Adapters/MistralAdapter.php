<?php

namespace App\Services\ApiRotator\Adapters;

use App\Models\ApiKey;
use App\Services\ApiRotator\Contracts\ProviderAdapterInterface;
use Carbon\Carbon;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class MistralAdapter implements ProviderAdapterInterface
{
    private string $baseUrl = 'https://api.mistral.ai/v1/chat/completions';

    public function getProviderName(): string
    {
        return 'mistral';
    }

    public function analyzeDocument(ApiKey $apiKey, string $pdfPath, string $systemPrompt, string $userPrompt): array
    {
        $pdfData = File::get($pdfPath);
        $base64Pdf = base64_encode($pdfData);
        $model = $apiKey->model ?? 'mistral-small-latest';

        $requestBody = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $systemPrompt,
                ],
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'document_url',
                            'document_url' => "data:application/pdf;base64,{$base64Pdf}",
                        ],
                        [
                            'type' => 'text',
                            'text' => $userPrompt,
                        ],
                    ],
                ],
            ],
            'temperature' => 0.1,
            'top_p' => 0.95,
            'response_format' => ['type' => 'json_object'],
        ];

        $response = Http::timeout(300)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => "Bearer {$apiKey->api_key}",
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
        // Mistral uses standard X-RateLimit headers
        $rpmRemaining = $response->header('X-RateLimit-Remaining');
        $retryAfter = $response->header('Retry-After');
        $resetHeader = $response->header('X-RateLimit-Reset');

        return [
            'rpm_remaining' => $rpmRemaining ?: null,
            'rpd_remaining' => null, // Mistral doesn't distinguish daily
            'retry_after' => $retryAfter ?: null,
            'reset_at' => $resetHeader ? $this->parseResetHeader($resetHeader) : null,
            'raw_headers' => $this->extractRateLimitHeaders($response),
            'requires_client_tracking' => false,
        ];
    }

    public function parseRateLimitError(Response $response): array
    {
        $retryAfterHeader = $response->header('Retry-After');
        $retryAfter = $retryAfterHeader ? (int) $retryAfterHeader : 60;
        $body = $response->json() ?? [];

        return [
            'retry_after' => $retryAfter,
            'is_daily_limit' => false, // Mistral primarily uses per-minute limits
            'error_message' => $body['message'] ?? 'Rate limited',
            'reset_at' => now()->addSeconds($retryAfter),
        ];
    }

    public function isRecoverableError(Response $response): bool
    {
        return in_array($response->status(), [429, 500, 502, 503]);
    }

    public function getDefaultLimits(): array
    {
        return [
            'mistral-small-latest' => ['rpm' => 60, 'rpd' => 1000, 'tpm' => 500000],
            'pixtral-12b-latest' => ['rpm' => 60, 'rpd' => 1000, 'tpm' => 500000],
            'mistral-nemo' => ['rpm' => 60, 'rpd' => 1000, 'tpm' => 500000],
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
        // Mistral - assuming UTC midnight reset
        return Carbon::now('UTC')
            ->addDay()
            ->startOfDay()
            ->setTimezone(config('app.timezone', 'UTC'));
    }

    private function parseSuccessResponse(array $data): ?array
    {
        $choices = $data['choices'] ?? [];
        if (empty($choices)) {
            return null;
        }

        return [
            'content' => $choices[0]['message']['content'] ?? null,
            'finish_reason' => $choices[0]['finish_reason'] ?? null,
            'usage' => $this->extractTokenUsage($data),
        ];
    }

    private function parseResetHeader(?string $reset): ?\DateTimeInterface
    {
        if (! $reset) {
            return null;
        }

        // Could be Unix timestamp or ISO date
        if (is_numeric($reset)) {
            return Carbon::createFromTimestamp((int) $reset);
        }

        return Carbon::parse($reset);
    }

    private function extractRateLimitHeaders(Response $response): array
    {
        return array_filter([
            'X-RateLimit-Limit' => $response->header('X-RateLimit-Limit'),
            'X-RateLimit-Remaining' => $response->header('X-RateLimit-Remaining'),
            'X-RateLimit-Reset' => $response->header('X-RateLimit-Reset'),
            'Retry-After' => $response->header('Retry-After'),
        ]);
    }
}

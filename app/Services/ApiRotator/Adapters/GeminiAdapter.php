<?php

namespace App\Services\ApiRotator\Adapters;

use App\Models\ApiKey;
use App\Services\ApiRotator\Contracts\ProviderAdapterInterface;
use Carbon\Carbon;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class GeminiAdapter implements ProviderAdapterInterface
{
    private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models';

    public function getProviderName(): string
    {
        return 'gemini';
    }

    public function analyzeDocument(ApiKey $apiKey, string $pdfPath, string $systemPrompt, string $userPrompt): array
    {
        $pdfData = File::get($pdfPath);
        $base64Pdf = base64_encode($pdfData);
        $model = $apiKey->model ?? 'gemini-2.5-flash-preview-05-20';

        $requestBody = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        [
                            'inline_data' => [
                                'mime_type' => 'application/pdf',
                                'data' => $base64Pdf,
                            ],
                        ],
                        ['text' => $userPrompt],
                    ],
                ],
            ],
            'systemInstruction' => [
                'parts' => [['text' => $systemPrompt]],
            ],
            'generationConfig' => [
                'temperature' => 0.1,
                'topP' => 0.95,
                'maxOutputTokens' => 65536,
                'responseMimeType' => 'application/json',
            ],
        ];

        $url = "{$this->baseUrl}/{$model}:generateContent?key={$apiKey->api_key}";

        $response = Http::timeout(300)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($url, $requestBody);

        return [
            'response' => $response,
            'parsed' => $response->successful() ? $this->parseSuccessResponse($response->json()) : null,
            'rate_limit_info' => $this->parseRateLimitInfo($response),
        ];
    }

    public function parseRateLimitInfo(Response $response): array
    {
        // Gemini does NOT provide proactive rate limit headers
        // We must track usage client-side
        return [
            'rpm_remaining' => null, // Unknown - must track locally
            'rpd_remaining' => null, // Unknown - must track locally
            'retry_after' => null,
            'reset_at' => null,
            'raw_headers' => $response->headers(),
            'requires_client_tracking' => true,
        ];
    }

    public function parseRateLimitError(Response $response): array
    {
        $retryAfter = $response->header('Retry-After');
        $body = $response->json() ?? [];

        // Determine if it's per-minute or per-day exhaustion
        $quotaId = $body['error']['details'][0]['quotaId'] ?? '';
        $isDaily = str_contains($quotaId, 'PerDay') || str_contains($quotaId, 'Daily');

        $retryAfterSeconds = $retryAfter ? (int) ceil((float) $retryAfter) : 60;

        return [
            'retry_after' => $retryAfterSeconds,
            'is_daily_limit' => $isDaily,
            'quota_id' => $quotaId,
            'error_message' => $body['error']['message'] ?? 'Rate limited',
            'reset_at' => $isDaily ? $this->getDailyResetTime() : now()->addSeconds($retryAfterSeconds),
        ];
    }

    public function isRecoverableError(Response $response): bool
    {
        $status = $response->status();

        // 429 = rate limited (recoverable with backoff)
        // 500-503 = server errors (recoverable with retry)
        if (in_array($status, [429, 500, 502, 503])) {
            return true;
        }

        // 403 with RESOURCE_EXHAUSTED = daily quota (not recoverable today)
        if ($status === 403) {
            $body = $response->json() ?? [];
            if (str_contains(json_encode($body), 'RESOURCE_EXHAUSTED')) {
                return false; // Daily limit - don't retry with this key
            }
        }

        return false;
    }

    public function getDefaultLimits(): array
    {
        return [
            'gemini-2.5-flash-preview-05-20' => ['rpm' => 10, 'rpd' => 250, 'tpm' => 250000],
            'gemini-2.5-pro' => ['rpm' => 5, 'rpd' => 50, 'tpm' => 250000],
            'gemini-2.0-flash-exp' => ['rpm' => 10, 'rpd' => 100, 'tpm' => 250000],
        ];
    }

    public function extractTokenUsage(array $responseData): array
    {
        $usage = $responseData['usageMetadata'] ?? [];

        return [
            'prompt_tokens' => $usage['promptTokenCount'] ?? 0,
            'completion_tokens' => $usage['candidatesTokenCount'] ?? 0,
            'total_tokens' => $usage['totalTokenCount'] ?? 0,
        ];
    }

    public function getDailyResetTime(): \DateTimeInterface
    {
        // Gemini resets at midnight Pacific Time
        return Carbon::now('America/Los_Angeles')
            ->addDay()
            ->startOfDay()
            ->setTimezone(config('app.timezone', 'UTC'));
    }

    private function parseSuccessResponse(array $data): ?array
    {
        $candidates = $data['candidates'] ?? [];
        if (empty($candidates)) {
            return null;
        }

        $content = $candidates[0]['content']['parts'][0]['text'] ?? null;

        return [
            'content' => $content,
            'finish_reason' => $candidates[0]['finishReason'] ?? null,
            'usage' => $this->extractTokenUsage($data),
        ];
    }
}

# Smart API Key Rotation System - Implementation Plan

## Overview

A reusable Laravel service for intelligent API key rotation across Google Gemini, Mistral AI, and OpenRouter providers. The system tracks rate limits, manages cooling periods, and automatically rotates to available keys when limits are reached.

---

## Architecture Summary

```
┌─────────────────────────────────────────────────────────────────┐
│                      Laravel Application                         │
├─────────────────────────────────────────────────────────────────┤
│  ┌─────────────────┐    ┌──────────────────────────────────┐   │
│  │ Artisan Command │───▶│      ApiKeyRotatorService        │   │
│  │ (court:analyze) │    │  ┌────────────────────────────┐  │   │
│  └─────────────────┘    │  │   ProviderAdapterFactory   │  │   │
│                         │  └────────────────────────────┘  │   │
│                         │         │         │         │     │   │
│                         │    ┌────┴────┐────┴────┐────┴──┐ │   │
│                         │    │ Gemini  │ Mistral │OpenRtr│ │   │
│                         │    │ Adapter │ Adapter │Adapter│ │   │
│                         │    └─────────┴─────────┴───────┘ │   │
│                         └──────────────────────────────────┘   │
│                                        │                        │
│  ┌─────────────────┐    ┌──────────────┴───────────────┐       │
│  │  Scheduled Job  │───▶│     RateLimitTracker         │       │
│  │ (reset quotas)  │    │  (Redis + Database hybrid)   │       │
│  └─────────────────┘    └──────────────────────────────┘       │
│                                        │                        │
├────────────────────────────────────────┼────────────────────────┤
│                              Database                           │
│  ┌──────────────┐  ┌───────────────┐  ┌────────────────────┐   │
│  │   api_keys   │  │ api_key_usage │  │ api_key_cooldowns  │   │
│  └──────────────┘  └───────────────┘  └────────────────────┘   │
└─────────────────────────────────────────────────────────────────┘
```

---

## Sprint 1: Database Foundation (2-3 hours)

### Task 1.1: Create `api_keys` Migration

**File:** `database/migrations/2025_01_12_000001_create_api_keys_table.php`

```php
Schema::create('api_keys', function (Blueprint $table) {
    $table->id();
    $table->string('name')->comment('Human-readable identifier');
    $table->string('provider')->index()->comment('gemini|mistral|openrouter');
    $table->text('api_key')->comment('Encrypted API key');
    $table->string('model')->nullable()->comment('Preferred model for this key');
    
    // Rate limit configuration (provider defaults, can override)
    $table->unsignedInteger('rpm_limit')->default(10)->comment('Requests per minute');
    $table->unsignedInteger('rpd_limit')->default(100)->comment('Requests per day');
    $table->unsignedBigInteger('tpm_limit')->default(250000)->comment('Tokens per minute');
    $table->unsignedBigInteger('tpd_limit')->default(0)->comment('Tokens per day, 0=unlimited');
    
    // Current usage counters (updated in real-time)
    $table->unsignedInteger('rpm_used')->default(0);
    $table->unsignedInteger('rpd_used')->default(0);
    $table->unsignedBigInteger('tpm_used')->default(0);
    $table->unsignedBigInteger('tpd_used')->default(0);
    
    // Timestamps for counter resets
    $table->timestamp('rpm_reset_at')->nullable()->comment('When minute counter resets');
    $table->timestamp('rpd_reset_at')->nullable()->comment('When daily counter resets');
    
    // Status
    $table->boolean('is_active')->default(true);
    $table->boolean('supports_pdf')->default(true)->comment('Can process PDF documents');
    $table->boolean('supports_vision')->default(true)->comment('Can process images');
    $table->unsignedTinyInteger('priority')->default(50)->comment('1-100, higher = preferred');
    
    // Metadata
    $table->json('capabilities')->nullable()->comment('Model-specific capabilities');
    $table->json('metadata')->nullable()->comment('Additional provider-specific data');
    $table->text('notes')->nullable();
    
    $table->timestamps();
    $table->softDeletes();
    
    // Indexes
    $table->index(['provider', 'is_active']);
    $table->index(['is_active', 'priority']);
});
```

**Acceptance Criteria:**
- [ ] Migration runs without errors
- [ ] All columns have appropriate types and defaults
- [ ] Indexes created for common query patterns

---

### Task 1.2: Create `api_key_usage_logs` Migration

**File:** `database/migrations/2025_01_12_000002_create_api_key_usage_logs_table.php`

```php
Schema::create('api_key_usage_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('api_key_id')->constrained()->onDelete('cascade');
    
    // Request details
    $table->string('model_used')->nullable();
    $table->string('endpoint')->nullable()->comment('chat/completions, generateContent, etc');
    $table->string('task_type')->default('general')->comment('pdf|vision|text|general');
    
    // Token usage
    $table->unsignedInteger('prompt_tokens')->default(0);
    $table->unsignedInteger('completion_tokens')->default(0);
    $table->unsignedInteger('total_tokens')->default(0);
    
    // Response info
    $table->unsignedSmallInteger('http_status')->nullable();
    $table->unsignedInteger('response_time_ms')->nullable();
    $table->boolean('was_successful')->default(true);
    $table->boolean('was_rate_limited')->default(false);
    $table->boolean('was_fallback')->default(false)->comment('Used as fallback from another key');
    
    // Rate limit headers captured (for debugging/analysis)
    $table->json('rate_limit_headers')->nullable();
    
    // Error info
    $table->string('error_code')->nullable();
    $table->text('error_message')->nullable();
    
    // Context
    $table->string('document_id')->nullable()->index()->comment('For tracing specific document processing');
    $table->string('batch_id')->nullable()->index()->comment('For batch processing correlation');
    
    $table->timestamp('created_at')->useCurrent();
    
    // Indexes for analytics
    $table->index(['api_key_id', 'created_at']);
    $table->index(['was_rate_limited', 'created_at']);
    $table->index('created_at');
});
```

**Acceptance Criteria:**
- [ ] Migration runs without errors
- [ ] Foreign key properly references api_keys
- [ ] Indexes support common analytics queries

---

### Task 1.3: Create `api_key_cooldowns` Migration

**File:** `database/migrations/2025_01_12_000003_create_api_key_cooldowns_table.php`

```php
Schema::create('api_key_cooldowns', function (Blueprint $table) {
    $table->id();
    $table->foreignId('api_key_id')->constrained()->onDelete('cascade');
    
    // Cooldown type and timing
    $table->string('cooldown_type')->comment('rpm|rpd|tpm|tpd|error|manual');
    $table->timestamp('started_at');
    $table->timestamp('ends_at')->index();
    
    // Source of cooldown info
    $table->string('source')->default('client')->comment('client|header|error_response');
    $table->unsignedInteger('retry_after_seconds')->nullable()->comment('From Retry-After header');
    
    // Additional context
    $table->json('metadata')->nullable()->comment('Raw headers, error details');
    $table->text('reason')->nullable();
    
    $table->timestamps();
    
    // Compound index for active cooldowns query
    $table->index(['api_key_id', 'ends_at']);
});
```

**Acceptance Criteria:**
- [ ] Migration runs without errors
- [ ] Can track multiple simultaneous cooldown types per key
- [ ] ends_at indexed for efficient "active cooldowns" queries

---

### Task 1.4: Create ApiKey Model

**File:** `app/Models/ApiKey.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Crypt;

class ApiKey extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'provider', 'api_key', 'model',
        'rpm_limit', 'rpd_limit', 'tpm_limit', 'tpd_limit',
        'is_active', 'supports_pdf', 'supports_vision', 'priority',
        'capabilities', 'metadata', 'notes',
    ];

    protected $casts = [
        'rpm_limit' => 'integer',
        'rpd_limit' => 'integer',
        'tpm_limit' => 'integer',
        'tpd_limit' => 'integer',
        'rpm_used' => 'integer',
        'rpd_used' => 'integer',
        'tpm_used' => 'integer',
        'tpd_used' => 'integer',
        'is_active' => 'boolean',
        'supports_pdf' => 'boolean',
        'supports_vision' => 'boolean',
        'priority' => 'integer',
        'capabilities' => 'array',
        'metadata' => 'array',
        'rpm_reset_at' => 'datetime',
        'rpd_reset_at' => 'datetime',
    ];

    protected $hidden = ['api_key'];

    // Encrypt API key on set
    protected function apiKey(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Crypt::decryptString($value) : null,
            set: fn ($value) => $value ? Crypt::encryptString($value) : null,
        );
    }

    // Relationships
    public function usageLogs(): HasMany
    {
        return $this->hasMany(ApiKeyUsageLog::class);
    }

    public function cooldowns(): HasMany
    {
        return $this->hasMany(ApiKeyCooldown::class);
    }

    public function activeCooldowns(): HasMany
    {
        return $this->cooldowns()->where('ends_at', '>', now());
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    public function scopeSupportsPdf($query)
    {
        return $query->where('supports_pdf', true);
    }

    public function scopeAvailable($query)
    {
        return $query->active()
            ->whereDoesntHave('cooldowns', function ($q) {
                $q->where('ends_at', '>', now());
            });
    }

    public function scopeByPriority($query)
    {
        return $query->orderByDesc('priority');
    }

    // Helper methods
    public function isInCooldown(): bool
    {
        return $this->activeCooldowns()->exists();
    }

    public function getRemainingRpm(): int
    {
        return max(0, $this->rpm_limit - $this->rpm_used);
    }

    public function getRemainingRpd(): int
    {
        return max(0, $this->rpd_limit - $this->rpd_used);
    }

    public function hasAvailableQuota(): bool
    {
        return $this->getRemainingRpm() > 0 && $this->getRemainingRpd() > 0;
    }

    public function getQuotaPercentageUsed(): array
    {
        return [
            'rpm' => $this->rpm_limit > 0 ? round(($this->rpm_used / $this->rpm_limit) * 100, 1) : 0,
            'rpd' => $this->rpd_limit > 0 ? round(($this->rpd_used / $this->rpd_limit) * 100, 1) : 0,
        ];
    }
}
```

**Acceptance Criteria:**
- [ ] API key encryption/decryption works correctly
- [ ] All scopes filter data as expected
- [ ] Quota calculation methods return accurate values
- [ ] Cooldown detection works properly

---

### Task 1.5: Create ApiKeyCooldown Model

**File:** `app/Models/ApiKeyCooldown.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiKeyCooldown extends Model
{
    protected $fillable = [
        'api_key_id', 'cooldown_type', 'started_at', 'ends_at',
        'source', 'retry_after_seconds', 'metadata', 'reason',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ends_at' => 'datetime',
        'retry_after_seconds' => 'integer',
        'metadata' => 'array',
    ];

    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class);
    }

    public function scopeActive($query)
    {
        return $query->where('ends_at', '>', now());
    }

    public function isActive(): bool
    {
        return $this->ends_at->isFuture();
    }

    public function getRemainingSeconds(): int
    {
        return max(0, now()->diffInSeconds($this->ends_at, false));
    }
}
```

---

### Task 1.6: Create ApiKeyUsageLog Model

**File:** `app/Models/ApiKeyUsageLog.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiKeyUsageLog extends Model
{
    public $timestamps = false;
    
    protected $fillable = [
        'api_key_id', 'model_used', 'endpoint', 'task_type',
        'prompt_tokens', 'completion_tokens', 'total_tokens',
        'http_status', 'response_time_ms', 'was_successful',
        'was_rate_limited', 'was_fallback', 'rate_limit_headers',
        'error_code', 'error_message', 'document_id', 'batch_id',
    ];

    protected $casts = [
        'prompt_tokens' => 'integer',
        'completion_tokens' => 'integer',
        'total_tokens' => 'integer',
        'http_status' => 'integer',
        'response_time_ms' => 'integer',
        'was_successful' => 'boolean',
        'was_rate_limited' => 'boolean',
        'was_fallback' => 'boolean',
        'rate_limit_headers' => 'array',
        'created_at' => 'datetime',
    ];

    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class);
    }
}
```

---

### Task 1.7: Create Database Seeder for Default Provider Configurations

**File:** `database/seeders/ApiKeyProviderDefaultsSeeder.php`

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ApiKeyProviderDefaultsSeeder extends Seeder
{
    /**
     * Provider default configurations based on free tier research.
     * These are templates - actual keys added via command or manually.
     */
    public function run(): void
    {
        // This seeder documents the defaults but doesn't create keys
        // Keys should be added via: php artisan apikey:add
        
        $this->command->info('Provider Default Configurations:');
        $this->command->table(
            ['Provider', 'Model', 'RPM', 'RPD', 'TPM', 'Context', 'PDF Support'],
            [
                ['gemini', 'gemini-2.5-flash-preview-05-20', 10, 250, 250000, '1M', 'Yes'],
                ['gemini', 'gemini-2.5-pro', 5, 50, 250000, '1M', 'Yes'],
                ['gemini', 'gemini-2.0-flash-exp', 10, 100, 250000, '1M', 'Yes'],
                ['mistral', 'mistral-small-latest', 60, 1000, 500000, '128K', 'Paid OCR'],
                ['mistral', 'pixtral-12b-latest', 60, 1000, 500000, '128K', 'Vision only'],
                ['openrouter', 'google/gemini-2.0-flash-exp:free', 20, 50, 0, '1M', 'Yes'],
                ['openrouter', 'deepseek/deepseek-r1:free', 20, 50, 0, '164K', 'No'],
                ['openrouter', 'meta-llama/llama-3.3-70b-instruct:free', 20, 1000, 0, '131K', 'No'],
            ]
        );
        
        $this->command->info('Add keys using: php artisan apikey:add {provider} {key}');
    }
}
```

---

## Sprint 2: Core Rotation Service (4-5 hours)

### Task 2.1: Create Provider Adapter Interface

**File:** `app/Services/ApiRotator/Contracts/ProviderAdapterInterface.php`

```php
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
```

---

### Task 2.2: Create Gemini Provider Adapter

**File:** `app/Services/ApiRotator/Adapters/GeminiAdapter.php`

```php
<?php

namespace App\Services\ApiRotator\Adapters;

use App\Models\ApiKey;
use App\Services\ApiRotator\Contracts\ProviderAdapterInterface;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

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
        $body = $response->json();
        
        // Determine if it's per-minute or per-day exhaustion
        $quotaId = $body['error']['details'][0]['quotaId'] ?? '';
        $isDaily = str_contains($quotaId, 'PerDay') || str_contains($quotaId, 'Daily');

        return [
            'retry_after' => $retryAfter ? (int) ceil((float) $retryAfter) : 60,
            'is_daily_limit' => $isDaily,
            'quota_id' => $quotaId,
            'error_message' => $body['error']['message'] ?? 'Rate limited',
            'reset_at' => $isDaily ? $this->getDailyResetTime() : now()->addSeconds((int) ($retryAfter ?? 60)),
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
            $body = $response->json();
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
            ->setTimezone(config('app.timezone'));
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
```

---

### Task 2.3: Create Mistral Provider Adapter

**File:** `app/Services/ApiRotator/Adapters/MistralAdapter.php`

```php
<?php

namespace App\Services\ApiRotator\Adapters;

use App\Models\ApiKey;
use App\Services\ApiRotator\Contracts\ProviderAdapterInterface;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

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
        return [
            'rpm_remaining' => $response->header('X-RateLimit-Remaining'),
            'rpd_remaining' => null, // Mistral doesn't distinguish daily
            'retry_after' => $response->header('Retry-After'),
            'reset_at' => $this->parseResetHeader($response->header('X-RateLimit-Reset')),
            'raw_headers' => $this->extractRateLimitHeaders($response),
            'requires_client_tracking' => false,
        ];
    }

    public function parseRateLimitError(Response $response): array
    {
        $retryAfter = $response->header('Retry-After') ?? 60;
        $body = $response->json();

        return [
            'retry_after' => (int) $retryAfter,
            'is_daily_limit' => false, // Mistral primarily uses per-minute limits
            'error_message' => $body['message'] ?? 'Rate limited',
            'reset_at' => now()->addSeconds((int) $retryAfter),
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
            ->setTimezone(config('app.timezone'));
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
        if (!$reset) return null;
        
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
```

---

### Task 2.4: Create OpenRouter Provider Adapter

**File:** `app/Services/ApiRotator/Adapters/OpenRouterAdapter.php`

```php
<?php

namespace App\Services\ApiRotator\Adapters;

use App\Models\ApiKey;
use App\Services\ApiRotator\Contracts\ProviderAdapterInterface;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

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
        $body = $response->json();
        $metadata = $body['error']['metadata']['headers'] ?? [];

        // OpenRouter provides detailed info in error metadata
        $resetMs = $metadata['X-RateLimit-Reset'] ?? null;
        $resetAt = $resetMs ? Carbon::createFromTimestampMs((int) $resetMs) : null;

        // Parse the error message to determine limit type
        $errorMessage = $body['error']['message'] ?? '';
        $isDaily = str_contains($errorMessage, 'limit_rpd');

        return [
            'retry_after' => $resetAt ? now()->diffInSeconds($resetAt, false) : 60,
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
            $body = $response->json();
            $errorMessage = $body['error']['message'] ?? '';
            return !str_contains($errorMessage, 'limit_rpd');
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
            ->setTimezone(config('app.timezone'));
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

        if (!$outputText) {
            return null;
        }

        return [
            'content' => $outputText,
            'reasoning' => $reasoning,
            'finish_reason' => 'stop',
            'usage' => $this->extractTokenUsage($data),
        ];
    }

    private function computeSafeMaxOutputTokens(string $base64Pdf): int
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
}
```

---

### Task 2.5: Create Provider Adapter Factory

**File:** `app/Services/ApiRotator/ProviderAdapterFactory.php`

```php
<?php

namespace App\Services\ApiRotator;

use App\Services\ApiRotator\Contracts\ProviderAdapterInterface;
use App\Services\ApiRotator\Adapters\GeminiAdapter;
use App\Services\ApiRotator\Adapters\MistralAdapter;
use App\Services\ApiRotator\Adapters\OpenRouterAdapter;
use InvalidArgumentException;

class ProviderAdapterFactory
{
    private array $adapters = [];

    public function __construct()
    {
        $this->adapters = [
            'gemini' => new GeminiAdapter(),
            'mistral' => new MistralAdapter(),
            'openrouter' => new OpenRouterAdapter(),
        ];
    }

    public function make(string $provider): ProviderAdapterInterface
    {
        if (!isset($this->adapters[$provider])) {
            throw new InvalidArgumentException("Unknown provider: {$provider}");
        }

        return $this->adapters[$provider];
    }

    public function all(): array
    {
        return $this->adapters;
    }

    public function providers(): array
    {
        return array_keys($this->adapters);
    }
}
```

---

### Task 2.6: Create Main ApiKeyRotator Service

**File:** `app/Services/ApiRotator/ApiKeyRotatorService.php`

```php
<?php

namespace App\Services\ApiRotator;

use App\Models\ApiKey;
use App\Models\ApiKeyCooldown;
use App\Models\ApiKeyUsageLog;
use App\Services\ApiRotator\Contracts\ProviderAdapterInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ApiKeyRotatorService
{
    private ProviderAdapterFactory $adapterFactory;
    private array $config;

    public function __construct(ProviderAdapterFactory $adapterFactory)
    {
        $this->adapterFactory = $adapterFactory;
        $this->config = [
            'proactive_threshold' => 0.8, // Switch at 80% usage
            'max_retries' => 3,
            'base_retry_delay' => 5,
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
            // Check proactive threshold
            $quotaUsed = $key->getQuotaPercentageUsed();
            if ($quotaUsed['rpd'] < ($this->config['proactive_threshold'] * 100)) {
                return $key;
            }
        }

        // If all keys are above threshold, return the one with most remaining quota
        return $keys->sortByDesc(fn($k) => $k->getRemainingRpd())->first();
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

        while ($attempts < $maxAttempts) {
            $attempts++;

            // Get next available key (excluding already tried)
            $key = $this->getNextKey($taskType, $triedKeys);

            if (!$key) {
                Log::warning('ApiKeyRotator: No available keys', [
                    'task_type' => $taskType,
                    'tried_keys' => count($triedKeys),
                ]);
                break;
            }

            $triedKeys[] = $key->id;
            $adapter = $this->adapterFactory->make($key->provider);

            try {
                $startTime = microtime(true);
                $result = $adapter->analyzeDocument($key, $pdfPath, $systemPrompt, $userPrompt);
                $responseTime = (int) ((microtime(true) - $startTime) * 1000);

                $response = $result['response'];

                if ($response->successful() && $result['parsed']) {
                    // Success - log and update counters
                    $this->recordSuccess($key, $adapter, $result, $responseTime, $documentId, $batchId);

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
                $this->handleError($key, $adapter, $response, $responseTime, $documentId, $batchId);
                $lastError = $response->body();

                // If recoverable, continue to next key
                if (!$adapter->isRecoverableError($response)) {
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
                $this->createCooldown($key, 'error', 30, 'Exception: ' . $e->getMessage());
            }
        }

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

        if (!empty($excludeIds)) {
            $query->whereNotIn('id', $excludeIds);
        }

        return $query->get()
            ->filter(fn($k) => $k->hasAvailableQuota())
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
        ?string $batchId
    ): void {
        $usage = $result['parsed']['usage'] ?? [];

        DB::transaction(function () use ($key, $usage, $responseTime, $documentId, $batchId, $result) {
            // Update usage counters
            $key->increment('rpm_used');
            $key->increment('rpd_used');
            
            if ($totalTokens = ($usage['total_tokens'] ?? 0)) {
                $key->increment('tpm_used', $totalTokens);
            }

            // Ensure reset timestamps are set
            if (!$key->rpm_reset_at || $key->rpm_reset_at->isPast()) {
                $key->rpm_reset_at = now()->addMinute();
            }

            $key->save();

            // Log the usage
            ApiKeyUsageLog::create([
                'api_key_id' => $key->id,
                'model_used' => $key->model,
                'task_type' => 'pdf',
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
        ?string $batchId
    ): void {
        $status = $response->status();
        $isRateLimited = $status === 429;

        DB::transaction(function () use ($key, $adapter, $response, $responseTime, $documentId, $batchId, $status, $isRateLimited) {
            // Log the failed attempt
            ApiKeyUsageLog::create([
                'api_key_id' => $key->id,
                'model_used' => $key->model,
                'task_type' => 'pdf',
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
                
                $cooldownType = $rateLimitInfo['is_daily_limit'] ?? false ? 'rpd' : 'rpm';
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
                    'available' => !$key->isInCooldown() && $key->hasAvailableQuota(),
                ];
            })
            ->toArray();
    }
}
```

---

## Sprint 3: Service Provider & Configuration (1-2 hours)

### Task 3.1: Create Service Provider

**File:** `app/Providers/ApiRotatorServiceProvider.php`

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\ApiRotator\ApiKeyRotatorService;
use App\Services\ApiRotator\ProviderAdapterFactory;

class ApiRotatorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ProviderAdapterFactory::class, function ($app) {
            return new ProviderAdapterFactory();
        });

        $this->app->singleton(ApiKeyRotatorService::class, function ($app) {
            return new ApiKeyRotatorService(
                $app->make(ProviderAdapterFactory::class)
            );
        });

        // Alias for easier access
        $this->app->alias(ApiKeyRotatorService::class, 'api-rotator');
    }

    public function boot(): void
    {
        // Publish config if needed
        // $this->publishes([...], 'api-rotator-config');
    }
}
```

**Register in `config/app.php`:**
```php
'providers' => [
    // ...
    App\Providers\ApiRotatorServiceProvider::class,
],
```

---

### Task 3.2: Create Configuration File

**File:** `config/api_rotator.php`

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Proactive Rotation Threshold
    |--------------------------------------------------------------------------
    |
    | When a key reaches this percentage of its daily quota, the rotator will
    | start preferring other keys to spread the load.
    |
    */
    'proactive_threshold' => env('API_ROTATOR_THRESHOLD', 0.8),

    /*
    |--------------------------------------------------------------------------
    | Retry Configuration
    |--------------------------------------------------------------------------
    */
    'max_retries' => env('API_ROTATOR_MAX_RETRIES', 3),
    'base_retry_delay' => env('API_ROTATOR_RETRY_DELAY', 5),

    /*
    |--------------------------------------------------------------------------
    | Provider Defaults
    |--------------------------------------------------------------------------
    |
    | Default rate limits for each provider's free tier.
    | These are used when adding new keys.
    |
    */
    'providers' => [
        'gemini' => [
            'models' => [
                'gemini-2.5-flash-preview-05-20' => [
                    'rpm' => 10,
                    'rpd' => 250,
                    'tpm' => 250000,
                    'supports_pdf' => true,
                    'supports_vision' => true,
                ],
                'gemini-2.0-flash-exp' => [
                    'rpm' => 10,
                    'rpd' => 100,
                    'tpm' => 250000,
                    'supports_pdf' => true,
                    'supports_vision' => true,
                ],
            ],
            'reset_timezone' => 'America/Los_Angeles',
        ],
        
        'mistral' => [
            'models' => [
                'mistral-small-latest' => [
                    'rpm' => 60,
                    'rpd' => 1000,
                    'tpm' => 500000,
                    'supports_pdf' => false, // OCR is paid
                    'supports_vision' => true,
                ],
            ],
            'reset_timezone' => 'UTC',
        ],
        
        'openrouter' => [
            'models' => [
                'google/gemini-2.0-flash-exp:free' => [
                    'rpm' => 20,
                    'rpd' => 50, // 1000 with $10+ credits
                    'tpm' => 0,
                    'supports_pdf' => true,
                    'supports_vision' => true,
                ],
            ],
            'reset_timezone' => 'UTC',
        ],
    ],
];
```

---

## Sprint 4: Artisan Commands (2-3 hours)

### Task 4.1: Create API Key Management Command

**File:** `app/Console/Commands/ApiKeyManage.php`

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ApiKey;
use App\Services\ApiRotator\ApiKeyRotatorService;

class ApiKeyManage extends Command
{
    protected $signature = 'apikey:manage
                            {action : list|add|remove|disable|enable|status}
                            {--provider= : Provider name (gemini|mistral|openrouter)}
                            {--key= : API key value (for add action)}
                            {--name= : Human-readable name}
                            {--model= : Model to use with this key}
                            {--id= : Key ID (for remove/disable/enable)}';

    protected $description = 'Manage API keys for the rotation system';

    public function handle(ApiKeyRotatorService $rotator): int
    {
        $action = $this->argument('action');

        return match ($action) {
            'list' => $this->listKeys(),
            'add' => $this->addKey(),
            'remove' => $this->removeKey(),
            'disable' => $this->toggleKey(false),
            'enable' => $this->toggleKey(true),
            'status' => $this->showStatus($rotator),
            default => $this->error("Unknown action: {$action}") ?? 1,
        };
    }

    private function listKeys(): int
    {
        $keys = ApiKey::withTrashed()->get();

        if ($keys->isEmpty()) {
            $this->warn('No API keys configured.');
            return 0;
        }

        $this->table(
            ['ID', 'Name', 'Provider', 'Model', 'RPD Limit', 'Active', 'PDF'],
            $keys->map(fn($k) => [
                $k->id,
                $k->name,
                $k->provider,
                $k->model ?? 'default',
                $k->rpd_limit,
                $k->is_active ? '✓' : '✗',
                $k->supports_pdf ? '✓' : '✗',
            ])
        );

        return 0;
    }

    private function addKey(): int
    {
        $provider = $this->option('provider') ?? $this->choice(
            'Select provider',
            ['gemini', 'mistral', 'openrouter']
        );

        $key = $this->option('key') ?? $this->secret('Enter API key');

        if (!$key) {
            $this->error('API key is required');
            return 1;
        }

        $name = $this->option('name') ?? $this->ask('Name for this key', "{$provider}-" . substr(md5($key), 0, 6));

        $defaults = config("api_rotator.providers.{$provider}.models", []);
        $modelChoices = array_keys($defaults);
        
        $model = $this->option('model');
        if (!$model && !empty($modelChoices)) {
            $model = $this->choice('Select model', $modelChoices, 0);
        }

        $modelDefaults = $defaults[$model] ?? [];

        $apiKey = ApiKey::create([
            'name' => $name,
            'provider' => $provider,
            'api_key' => $key,
            'model' => $model,
            'rpm_limit' => $modelDefaults['rpm'] ?? 10,
            'rpd_limit' => $modelDefaults['rpd'] ?? 100,
            'tpm_limit' => $modelDefaults['tpm'] ?? 250000,
            'supports_pdf' => $modelDefaults['supports_pdf'] ?? true,
            'supports_vision' => $modelDefaults['supports_vision'] ?? true,
            'priority' => 50,
        ]);

        $this->info("API key added successfully! ID: {$apiKey->id}");
        return 0;
    }

    private function removeKey(): int
    {
        $id = $this->option('id') ?? $this->ask('Enter key ID to remove');

        $key = ApiKey::find($id);
        if (!$key) {
            $this->error("Key not found: {$id}");
            return 1;
        }

        if ($this->confirm("Remove key '{$key->name}' ({$key->provider})?")) {
            $key->delete();
            $this->info('Key removed.');
        }

        return 0;
    }

    private function toggleKey(bool $enable): int
    {
        $id = $this->option('id') ?? $this->ask('Enter key ID');

        $key = ApiKey::find($id);
        if (!$key) {
            $this->error("Key not found: {$id}");
            return 1;
        }

        $key->update(['is_active' => $enable]);
        $this->info($enable ? 'Key enabled.' : 'Key disabled.');

        return 0;
    }

    private function showStatus(ApiKeyRotatorService $rotator): int
    {
        $status = $rotator->getStatus();

        if (empty($status)) {
            $this->warn('No API keys configured.');
            return 0;
        }

        $this->table(
            ['ID', 'Name', 'Provider', 'RPM', 'RPD', 'Cooldown', 'Available'],
            collect($status)->map(fn($s) => [
                $s['id'],
                $s['name'],
                $s['provider'],
                $s['rpm'],
                $s['rpd'],
                $s['cooldown_ends'] ?? '-',
                $s['available'] ? '✓' : '✗',
            ])
        );

        return 0;
    }
}
```

---

### Task 4.2: Create Quota Reset Scheduled Command

**File:** `app/Console/Commands/ApiKeyResetQuotas.php`

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ApiRotator\ApiKeyRotatorService;

class ApiKeyResetQuotas extends Command
{
    protected $signature = 'apikey:reset-quotas
                            {--type=all : minute|daily|all}';

    protected $description = 'Reset API key usage quotas';

    public function handle(ApiKeyRotatorService $rotator): int
    {
        $type = $this->option('type');

        if (in_array($type, ['minute', 'all'])) {
            $count = $rotator->resetMinuteCounters();
            $this->info("Reset minute counters for {$count} keys.");
        }

        if (in_array($type, ['daily', 'all'])) {
            $count = $rotator->resetDailyCounters();
            $this->info("Reset daily counters for {$count} keys.");
        }

        return 0;
    }
}
```

---

### Task 4.3: Register Scheduled Tasks

**File:** `app/Console/Kernel.php` (add to `schedule` method)

```php
protected function schedule(Schedule $schedule): void
{
    // Reset minute counters every minute
    $schedule->command('apikey:reset-quotas --type=minute')
        ->everyMinute()
        ->withoutOverlapping();

    // Reset daily counters at appropriate times for each timezone
    // Gemini: midnight PT (8 AM UTC)
    $schedule->command('apikey:reset-quotas --type=daily')
        ->dailyAt('08:00')
        ->timezone('UTC');

    // Also run at midnight UTC for Mistral/OpenRouter
    $schedule->command('apikey:reset-quotas --type=daily')
        ->dailyAt('00:00')
        ->timezone('UTC');
}
```

---

## Sprint 5: Integration with Existing Command (2-3 hours)

### Task 5.1: Update AnalyzeCourtDecisionsBatch to Use Rotator

**File:** `app/Console/Commands/AnalyzeCourtDecisionsBatch.php`

Replace the manual API handling with the rotator service:

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use App\Services\ApiRotator\ApiKeyRotatorService;
use Carbon\Carbon;

class AnalyzeCourtDecisionsBatch extends Command
{
    protected $signature = 'court:analyze-batch
                            {--input=court_decision_links.txt : Input file with PDF URLs}
                            {--output=court_analysis_results : Output directory for results}
                            {--download-concurrency=10 : Number of concurrent PDF downloads}
                            {--skip-download : Skip PDF download, use cached files}
                            {--dry-run : Only download PDFs without API calls}
                            {--limit= : Limit number of documents to process}';

    protected $description = 'Batch analyze Croatian court decisions with smart API key rotation';

    private ApiKeyRotatorService $rotator;
    private string $pdfCacheDir;
    
    private array $stats = [
        'total' => 0,
        'downloaded' => 0,
        'analyzed' => 0,
        'cached' => 0,
        'failed_download' => 0,
        'failed_analysis' => 0,
    ];

    private string $systemPrompt = '...'; // Keep existing prompt
    private string $analysisPrompt = '...'; // Keep existing prompt

    public function __construct(ApiKeyRotatorService $rotator)
    {
        parent::__construct();
        $this->rotator = $rotator;
    }

    public function handle(): int
    {
        $outputDir = $this->option('output');
        $this->pdfCacheDir = "{$outputDir}/pdf_cache";

        // Create directories
        foreach ([$outputDir, $this->pdfCacheDir] as $dir) {
            if (!File::isDirectory($dir)) {
                File::makeDirectory($dir, 0755, true);
            }
        }

        // Check available keys
        $status = $this->rotator->getStatus();
        $availableKeys = collect($status)->filter(fn($s) => $s['available'])->count();
        
        if ($availableKeys === 0 && !$this->option('dry-run')) {
            $this->error('No API keys available. Add keys with: php artisan apikey:manage add');
            return Command::FAILURE;
        }

        $this->info("Available API keys: {$availableKeys}");
        $this->newLine();

        // Load and process URLs
        $urls = $this->loadUrls($this->option('input'));
        
        if ($urls->isEmpty()) {
            $this->warn('No valid URLs found');
            return Command::SUCCESS;
        }

        if ($limit = $this->option('limit')) {
            $urls = $urls->take((int) $limit);
        }

        $this->stats['total'] = $urls->count();
        $this->info("Processing {$this->stats['total']} documents");

        // Phase 1: Download PDFs
        $downloadedDocs = $this->downloadAllPdfs($urls);

        if ($this->option('dry-run')) {
            $this->printSummary();
            return Command::SUCCESS;
        }

        // Phase 2: Analyze with rotation
        $results = $this->analyzeAllDocuments($downloadedDocs, $outputDir);

        // Phase 3: Aggregate
        $this->aggregateResults($results, $outputDir);
        $this->printSummary();

        return $this->stats['failed_analysis'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function analyzeAllDocuments(Collection $documents, string $outputDir): array
    {
        $results = [];
        $batchId = Str::uuid()->toString();

        $progressBar = $this->output->createProgressBar($documents->count());
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% -- %message%');
        $progressBar->start();

        foreach ($documents as $doc) {
            $outputFile = "{$outputDir}/{$doc['id']}.json";

            // Check cache
            if (File::exists($outputFile)) {
                $result = json_decode(File::get($outputFile), true);
                if ($result && !empty($result['analysis'])) {
                    $results[] = $result;
                    $progressBar->setMessage("Cached: {$doc['id']}");
                    $progressBar->advance();
                    continue;
                }
            }

            $progressBar->setMessage("Analyzing: {$doc['id']}");

            // Use rotator service
            $response = $this->rotator->analyzeDocument(
                pdfPath: $doc['path'],
                systemPrompt: $this->systemPrompt,
                userPrompt: $this->analysisPrompt,
                taskType: 'pdf',
                documentId: $doc['id'],
                batchId: $batchId
            );

            if ($response['success']) {
                $result = [
                    'document_id' => $doc['id'],
                    'source_url' => $doc['url'],
                    'processed_at' => Carbon::now()->toIso8601String(),
                    'api_provider' => $response['provider'],
                    'api_model' => $response['model'],
                    'analysis' => $this->extractJsonFromText($response['data']['content'] ?? ''),
                    'usage' => $response['data']['usage'] ?? null,
                ];

                File::put($outputFile, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                $results[] = $result;
                $this->stats['analyzed']++;
            } else {
                $this->warn("   Failed: {$response['error']}");
                $this->stats['failed_analysis']++;
            }

            $progressBar->advance();

            // Small delay between requests
            usleep(500000);
        }

        $progressBar->finish();
        $this->newLine();

        return $results;
    }

    // ... keep existing helper methods (loadUrls, downloadAllPdfs, extractJsonFromText, etc.)
}
```

---

## Sprint 6: Testing & Documentation (2-3 hours)

### Task 6.1: Create Feature Tests

**File:** `tests/Feature/ApiKeyRotatorTest.php`

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\ApiKey;
use App\Models\ApiKeyCooldown;
use App\Services\ApiRotator\ApiKeyRotatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ApiKeyRotatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_available_key_returns_highest_priority(): void
    {
        ApiKey::factory()->create(['provider' => 'gemini', 'priority' => 50, 'is_active' => true]);
        ApiKey::factory()->create(['provider' => 'gemini', 'priority' => 80, 'is_active' => true]);

        $rotator = app(ApiKeyRotatorService::class);
        $key = $rotator->getAvailableKey();

        $this->assertEquals(80, $key->priority);
    }

    public function test_key_in_cooldown_is_not_returned(): void
    {
        $key = ApiKey::factory()->create(['is_active' => true]);
        
        ApiKeyCooldown::create([
            'api_key_id' => $key->id,
            'cooldown_type' => 'rpm',
            'started_at' => now(),
            'ends_at' => now()->addMinutes(5),
        ]);

        $rotator = app(ApiKeyRotatorService::class);
        $available = $rotator->getAvailableKey();

        $this->assertNull($available);
    }

    public function test_reset_minute_counters(): void
    {
        $key = ApiKey::factory()->create([
            'rpm_used' => 10,
            'rpm_reset_at' => now()->subMinutes(2),
        ]);

        $rotator = app(ApiKeyRotatorService::class);
        $rotator->resetMinuteCounters();

        $key->refresh();
        $this->assertEquals(0, $key->rpm_used);
    }

    // ... more tests
}
```

---

### Task 6.2: Create API Key Factory

**File:** `database/factories/ApiKeyFactory.php`

```php
<?php

namespace Database\Factories;

use App\Models\ApiKey;
use Illuminate\Database\Eloquent\Factories\Factory;

class ApiKeyFactory extends Factory
{
    protected $model = ApiKey::class;

    public function definition(): array
    {
        $providers = ['gemini', 'mistral', 'openrouter'];
        $provider = $this->faker->randomElement($providers);

        return [
            'name' => $this->faker->words(2, true) . '-key',
            'provider' => $provider,
            'api_key' => 'test_' . $this->faker->sha256(),
            'model' => null,
            'rpm_limit' => 10,
            'rpd_limit' => 100,
            'tpm_limit' => 250000,
            'rpm_used' => 0,
            'rpd_used' => 0,
            'is_active' => true,
            'supports_pdf' => true,
            'supports_vision' => true,
            'priority' => $this->faker->numberBetween(1, 100),
        ];
    }

    public function gemini(): self
    {
        return $this->state(['provider' => 'gemini', 'model' => 'gemini-2.5-flash-preview-05-20']);
    }

    public function mistral(): self
    {
        return $this->state(['provider' => 'mistral', 'model' => 'mistral-small-latest']);
    }

    public function openrouter(): self
    {
        return $this->state(['provider' => 'openrouter', 'model' => 'google/gemini-2.0-flash-exp:free']);
    }

    public function exhausted(): self
    {
        return $this->state(['rpd_used' => 100, 'rpd_limit' => 100]);
    }
}
```

---

## Summary: Sprint Timeline

| Sprint | Description | Estimated Time | Dependencies |
|--------|-------------|----------------|--------------|
| 1 | Database Foundation | 2-3 hours | None |
| 2 | Core Rotation Service | 4-5 hours | Sprint 1 |
| 3 | Service Provider & Config | 1-2 hours | Sprint 2 |
| 4 | Artisan Commands | 2-3 hours | Sprint 3 |
| 5 | Command Integration | 2-3 hours | Sprint 4 |
| 6 | Testing & Documentation | 2-3 hours | Sprint 5 |

**Total: 13-19 hours**

---

## Post-Implementation Checklist

- [ ] Run all migrations
- [ ] Register service provider
- [ ] Add at least one API key per provider
- [ ] Test key rotation with `apikey:manage status`
- [ ] Run batch analysis with a small test set
- [ ] Verify usage logging in database
- [ ] Confirm cooldowns work correctly
- [ ] Set up scheduled tasks for quota resets
- [ ] Monitor production behavior and adjust thresholds

---

## Future Enhancements

1. **Redis-based counters** for distributed workers
2. **Admin dashboard** for real-time monitoring
3. **Cost tracking** for paid tier usage
4. **Automatic key provisioning** via provider APIs
5. **Health checks** that pre-validate keys daily
6. **Usage alerts** when approaching quotas

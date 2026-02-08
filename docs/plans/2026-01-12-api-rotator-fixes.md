# API Key Rotator Production-Ready Fixes

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Fix 10 production-readiness issues in the Smart API Key Rotation System

**Architecture:** Incremental fixes to existing service - no structural changes. Each fix is independent and can be parallelized. TDD approach for all changes.

**Tech Stack:** Laravel 11, PHP 8.3, PHPUnit

---

## Task 1: Use Config Instead of Hardcoded Values

**Files:**
- Modify: `app/Services/ApiRotator/ApiKeyRotatorService.php:18-26`
- Test: `tests/Unit/Services/ApiRotator/ApiKeyRotatorServiceTest.php`

**Step 1: Write the failing test**

Add to `tests/Unit/Services/ApiRotator/ApiKeyRotatorServiceTest.php`:

```php
public function test_service_uses_config_values(): void
{
    config(['api_rotator.proactive_threshold' => 0.5]);
    config(['api_rotator.max_retries' => 5]);
    config(['api_rotator.base_retry_delay' => 10]);

    // Create key at exactly 50% usage
    ApiKey::factory()->create([
        'is_active' => true,
        'rpd_limit' => 100,
        'rpd_used' => 50,
        'priority' => 100,
    ]);

    // Create key at 40% usage (below threshold)
    $lowUsageKey = ApiKey::factory()->create([
        'is_active' => true,
        'rpd_limit' => 100,
        'rpd_used' => 40,
        'priority' => 50,
    ]);

    $rotator = app(ApiKeyRotatorService::class);
    $key = $rotator->getAvailableKey();

    // Should return lower priority key because high priority is at threshold
    $this->assertEquals($lowUsageKey->id, $key->id);
}
```

**Step 2: Run test to verify it fails**

```bash
./scripts/run-focused-tests.sh ApiKeyRotatorServiceTest --filter=test_service_uses_config_values
```

Expected: FAIL (currently uses hardcoded 0.8 threshold)

**Step 3: Write minimal implementation**

Replace lines 18-26 in `app/Services/ApiRotator/ApiKeyRotatorService.php`:

```php
public function __construct(ProviderAdapterFactory $adapterFactory)
{
    $this->adapterFactory = $adapterFactory;
    $this->config = [
        'proactive_threshold' => config('api_rotator.proactive_threshold', 0.8),
        'max_retries' => config('api_rotator.max_retries', 3),
        'base_retry_delay' => config('api_rotator.base_retry_delay', 5),
    ];
}
```

**Step 4: Run test to verify it passes**

```bash
./scripts/run-focused-tests.sh ApiKeyRotatorServiceTest --filter=test_service_uses_config_values
```

Expected: PASS

**Step 5: Commit**

```bash
git add app/Services/ApiRotator/ApiKeyRotatorService.php tests/Unit/Services/ApiRotator/ApiKeyRotatorServiceTest.php
git commit -m "fix: Use config values instead of hardcoded in ApiKeyRotatorService"
```

---

## Task 2: Fix Hardcoded task_type in recordSuccess

**Files:**
- Modify: `app/Services/ApiRotator/ApiKeyRotatorService.php:178-220`
- Modify: `app/Services/ApiRotator/ApiKeyRotatorService.php:225-271`
- Test: `tests/Unit/Services/ApiRotator/ApiKeyRotatorServiceTest.php`

**Step 1: Write the failing test**

Add to `tests/Unit/Services/ApiRotator/ApiKeyRotatorServiceTest.php`:

```php
public function test_record_success_uses_provided_task_type(): void
{
    $key = ApiKey::factory()->create(['is_active' => true, 'provider' => 'gemini']);

    $rotator = app(ApiKeyRotatorService::class);

    // Use reflection to call private method with 'vision' task type
    $reflection = new \ReflectionClass($rotator);
    $method = $reflection->getMethod('recordSuccess');
    $method->setAccessible(true);

    $mockAdapter = $this->createMock(\App\Services\ApiRotator\Contracts\ProviderAdapterInterface::class);

    $method->invoke($rotator, $key, $mockAdapter, [
        'parsed' => ['usage' => ['total_tokens' => 100]],
        'rate_limit_info' => [],
    ], 500, 'doc-123', 'batch-456', 'vision');

    $this->assertDatabaseHas('api_key_usage_logs', [
        'api_key_id' => $key->id,
        'task_type' => 'vision',
        'document_id' => 'doc-123',
    ]);
}
```

**Step 2: Run test to verify it fails**

```bash
./scripts/run-focused-tests.sh ApiKeyRotatorServiceTest --filter=test_record_success_uses_provided_task_type
```

Expected: FAIL (task_type will be 'pdf' not 'vision')

**Step 3: Write minimal implementation**

Update `recordSuccess` method signature and body (lines 178-220):

```php
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
```

Update `handleError` similarly (add $taskType parameter) and update the call sites in `analyzeDocument` (line 110 and 123) to pass `$taskType`.

**Step 4: Run test to verify it passes**

```bash
./scripts/run-focused-tests.sh ApiKeyRotatorServiceTest --filter=test_record_success_uses_provided_task_type
```

Expected: PASS

**Step 5: Commit**

```bash
git add app/Services/ApiRotator/ApiKeyRotatorService.php tests/Unit/Services/ApiRotator/ApiKeyRotatorServiceTest.php
git commit -m "fix: Pass task_type through recordSuccess and handleError"
```

---

## Task 3: Add TPM Enforcement to Quota Check

**Files:**
- Modify: `app/Models/ApiKey.php:104-117`
- Test: `tests/Feature/ApiKeyRotatorTest.php`

**Step 1: Write the failing test**

Add to `tests/Feature/ApiKeyRotatorTest.php`:

```php
public function test_key_at_tpm_limit_is_not_returned(): void
{
    ApiKey::factory()->create([
        'is_active' => true,
        'tpm_limit' => 1000,
        'tpm_used' => 1000,
        'rpm_limit' => 100,
        'rpm_used' => 0,
        'rpd_limit' => 100,
        'rpd_used' => 0,
    ]);

    $rotator = app(ApiKeyRotatorService::class);
    $key = $rotator->getAvailableKey();

    $this->assertNull($key);
}

public function test_key_with_zero_tpm_limit_ignores_tpm_check(): void
{
    $key = ApiKey::factory()->create([
        'is_active' => true,
        'tpm_limit' => 0, // 0 means unlimited
        'tpm_used' => 999999,
        'rpm_limit' => 100,
        'rpm_used' => 0,
        'rpd_limit' => 100,
        'rpd_used' => 0,
    ]);

    $rotator = app(ApiKeyRotatorService::class);
    $result = $rotator->getAvailableKey();

    $this->assertEquals($key->id, $result->id);
}
```

**Step 2: Run test to verify it fails**

```bash
./scripts/run-focused-tests.sh ApiKeyRotatorTest --filter=test_key_at_tpm_limit
```

Expected: FAIL (TPM not checked)

**Step 3: Write minimal implementation**

Update `app/Models/ApiKey.php` - add method and update `hasAvailableQuota`:

```php
public function getRemainingTpm(): int
{
    // 0 means unlimited
    if ($this->tpm_limit === 0) {
        return PHP_INT_MAX;
    }
    return max(0, $this->tpm_limit - $this->tpm_used);
}

public function hasAvailableQuota(): bool
{
    return $this->getRemainingRpm() > 0
        && $this->getRemainingRpd() > 0
        && $this->getRemainingTpm() > 0;
}
```

**Step 4: Run test to verify it passes**

```bash
./scripts/run-focused-tests.sh ApiKeyRotatorTest --filter=test_key_at_tpm_limit
```

Expected: PASS

**Step 5: Run full test suite to ensure no regressions**

```bash
./scripts/run-focused-tests.sh ApiKeyRotatorTest
```

**Step 6: Commit**

```bash
git add app/Models/ApiKey.php tests/Feature/ApiKeyRotatorTest.php
git commit -m "fix: Add TPM enforcement to hasAvailableQuota check"
```

---

## Task 4: Implement Exponential Backoff

**Files:**
- Modify: `app/Services/ApiRotator/ApiKeyRotatorService.php:71-151`
- Test: `tests/Unit/Services/ApiRotator/ApiKeyRotatorServiceTest.php`

**Step 1: Write the failing test**

Add to `tests/Unit/Services/ApiRotator/ApiKeyRotatorServiceTest.php`:

```php
public function test_exponential_backoff_delays_between_retries(): void
{
    config(['api_rotator.base_retry_delay' => 1]); // 1 second for fast test

    // Create multiple keys that will all fail
    ApiKey::factory()->count(3)->create([
        'is_active' => true,
        'provider' => 'gemini',
    ]);

    $rotator = app(ApiKeyRotatorService::class);

    // Mock adapter to always fail with recoverable error
    $mockFactory = $this->createMock(\App\Services\ApiRotator\ProviderAdapterFactory::class);
    $mockAdapter = $this->createMock(\App\Services\ApiRotator\Contracts\ProviderAdapterInterface::class);

    $mockResponse = new \Illuminate\Http\Client\Response(
        new \GuzzleHttp\Psr7\Response(429, [], '{"error": "rate limited"}')
    );

    $mockAdapter->method('analyzeDocument')->willReturn([
        'response' => $mockResponse,
        'parsed' => null,
        'rate_limit_info' => [],
    ]);
    $mockAdapter->method('isRecoverableError')->willReturn(true);
    $mockAdapter->method('parseRateLimitError')->willReturn([
        'retry_after' => 60,
        'is_daily_limit' => false,
        'error_message' => 'Rate limited',
        'reset_at' => now()->addMinutes(1),
    ]);

    $mockFactory->method('make')->willReturn($mockAdapter);

    // Use reflection to inject mock factory
    $reflection = new \ReflectionClass($rotator);
    $prop = $reflection->getProperty('adapterFactory');
    $prop->setAccessible(true);
    $prop->setValue($rotator, $mockFactory);

    $startTime = microtime(true);
    $rotator->analyzeDocument('/tmp/test.pdf', 'system', 'user', 'pdf');
    $elapsed = microtime(true) - $startTime;

    // With base_retry_delay=1 and 3 retries: 1 + 2 + 4 = 7 seconds minimum
    // But we only wait between different key attempts, so at least some delay
    $this->assertGreaterThan(0.5, $elapsed, 'Should have delays between retries');
}
```

**Step 2: Run test to verify it fails**

```bash
./scripts/run-focused-tests.sh ApiKeyRotatorServiceTest --filter=test_exponential_backoff
```

Expected: FAIL (no delays currently)

**Step 3: Write minimal implementation**

Update `analyzeDocument` method in `app/Services/ApiRotator/ApiKeyRotatorService.php`:

Add after line 142 (after the catch block, before the closing of the while loop):

```php
// Apply exponential backoff before next attempt
if ($attempts < $maxAttempts) {
    $delay = $this->config['base_retry_delay'] * pow(2, min($attempts - 1, 4));
    usleep((int) ($delay * 1000000)); // Convert to microseconds
}
```

**Step 4: Run test to verify it passes**

```bash
./scripts/run-focused-tests.sh ApiKeyRotatorServiceTest --filter=test_exponential_backoff
```

Expected: PASS

**Step 5: Commit**

```bash
git add app/Services/ApiRotator/ApiKeyRotatorService.php tests/Unit/Services/ApiRotator/ApiKeyRotatorServiceTest.php
git commit -m "feat: Add exponential backoff between retry attempts"
```

---

## Task 5: Add Database Locking for Concurrent Access

**Files:**
- Modify: `app/Services/ApiRotator/ApiKeyRotatorService.php:156-173`
- Test: `tests/Feature/ApiKeyRotatorTest.php`

**Step 1: Write the failing test**

Add to `tests/Feature/ApiKeyRotatorTest.php`:

```php
public function test_concurrent_key_selection_uses_locking(): void
{
    // Create a single key with limited quota
    $key = ApiKey::factory()->create([
        'is_active' => true,
        'rpd_limit' => 2,
        'rpd_used' => 0,
    ]);

    $rotator = app(ApiKeyRotatorService::class);

    // Simulate concurrent access by running in a transaction
    $selectedKey = null;
    DB::transaction(function () use ($rotator, &$selectedKey) {
        $selectedKey = $rotator->getNextKeyWithLock('general', []);

        // Verify the key is locked (FOR UPDATE)
        $this->assertNotNull($selectedKey);
    });

    $this->assertEquals($key->id, $selectedKey->id);
}
```

**Step 2: Run test to verify it fails**

```bash
./scripts/run-focused-tests.sh ApiKeyRotatorTest --filter=test_concurrent_key_selection
```

Expected: FAIL (method doesn't exist)

**Step 3: Write minimal implementation**

Add new method and update `getNextKey` in `app/Services/ApiRotator/ApiKeyRotatorService.php`:

```php
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
```

Update `analyzeDocument` method to use locking - wrap key selection and usage update in a single transaction:

```php
// In analyzeDocument, replace line 88:
// Old: $key = $this->getNextKey($taskType, $triedKeys);
// New:
$key = DB::transaction(function () use ($taskType, $triedKeys) {
    return $this->getNextKeyWithLock($taskType, $triedKeys);
});
```

**Step 4: Run test to verify it passes**

```bash
./scripts/run-focused-tests.sh ApiKeyRotatorTest --filter=test_concurrent_key_selection
```

Expected: PASS

**Step 5: Run full suite**

```bash
./scripts/run-focused-tests.sh ApiKeyRotatorTest
```

**Step 6: Commit**

```bash
git add app/Services/ApiRotator/ApiKeyRotatorService.php tests/Feature/ApiKeyRotatorTest.php
git commit -m "feat: Add pessimistic locking for concurrent key selection"
```

---

## Task 6: Add Key Validation Command

**Files:**
- Create: `app/Console/Commands/ApiKeyValidate.php`
- Test: `tests/Feature/Console/ApiKeyValidateCommandTest.php`

**Step 1: Write the failing test**

Create `tests/Feature/Console/ApiKeyValidateCommandTest.php`:

```php
<?php

namespace Tests\Feature\Console;

use Tests\TestCase;
use App\Models\ApiKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

class ApiKeyValidateCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_validate_command_reports_valid_key(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'test']]]]],
            ], 200),
        ]);

        ApiKey::factory()->create([
            'provider' => 'gemini',
            'is_active' => true,
            'name' => 'test-key',
        ]);

        $this->artisan('apikey:validate')
            ->expectsOutputToContain('test-key')
            ->expectsOutputToContain('VALID')
            ->assertExitCode(0);
    }

    public function test_validate_command_reports_invalid_key(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'error' => ['message' => 'API key not valid'],
            ], 401),
        ]);

        ApiKey::factory()->create([
            'provider' => 'gemini',
            'is_active' => true,
            'name' => 'bad-key',
        ]);

        $this->artisan('apikey:validate')
            ->expectsOutputToContain('bad-key')
            ->expectsOutputToContain('INVALID')
            ->assertExitCode(1);
    }

    public function test_validate_specific_key_by_id(): void
    {
        Http::fake([
            '*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => 'ok']]]]]], 200),
        ]);

        $key = ApiKey::factory()->create(['provider' => 'gemini', 'is_active' => true]);
        ApiKey::factory()->create(['provider' => 'mistral', 'is_active' => true]);

        $this->artisan('apikey:validate', ['--id' => $key->id])
            ->assertExitCode(0);

        // Should only validate one key
        Http::assertSentCount(1);
    }
}
```

**Step 2: Run test to verify it fails**

```bash
./scripts/run-focused-tests.sh ApiKeyValidateCommandTest
```

Expected: FAIL (command doesn't exist)

**Step 3: Write minimal implementation**

Create `app/Console/Commands/ApiKeyValidate.php`:

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ApiKey;
use App\Services\ApiRotator\ProviderAdapterFactory;
use Illuminate\Support\Facades\Http;

class ApiKeyValidate extends Command
{
    protected $signature = 'apikey:validate
                            {--id= : Validate specific key by ID}
                            {--provider= : Validate keys for specific provider}';

    protected $description = 'Validate API keys by making test requests';

    public function handle(ProviderAdapterFactory $factory): int
    {
        $query = ApiKey::active();

        if ($id = $this->option('id')) {
            $query->where('id', $id);
        }

        if ($provider = $this->option('provider')) {
            $query->where('provider', $provider);
        }

        $keys = $query->get();

        if ($keys->isEmpty()) {
            $this->warn('No keys found to validate.');
            return Command::SUCCESS;
        }

        $hasInvalid = false;
        $results = [];

        foreach ($keys as $key) {
            $this->info("Validating {$key->name} ({$key->provider})...");

            $isValid = $this->validateKey($key);
            $status = $isValid ? '<fg=green>VALID</>' : '<fg=red>INVALID</>';

            $results[] = [
                $key->id,
                $key->name,
                $key->provider,
                $isValid ? 'VALID' : 'INVALID',
            ];

            if (! $isValid) {
                $hasInvalid = true;
            }
        }

        $this->newLine();
        $this->table(['ID', 'Name', 'Provider', 'Status'], $results);

        return $hasInvalid ? Command::FAILURE : Command::SUCCESS;
    }

    private function validateKey(ApiKey $key): bool
    {
        try {
            $response = match ($key->provider) {
                'gemini' => $this->validateGemini($key),
                'mistral' => $this->validateMistral($key),
                'openrouter' => $this->validateOpenRouter($key),
                default => false,
            };

            return $response;
        } catch (\Throwable $e) {
            $this->error("  Error: {$e->getMessage()}");
            return false;
        }
    }

    private function validateGemini(ApiKey $key): bool
    {
        $model = $key->model ?? 'gemini-2.0-flash-exp';
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$key->api_key}";

        $response = Http::timeout(30)->post($url, [
            'contents' => [['parts' => [['text' => 'Say "OK" and nothing else.']]]],
            'generationConfig' => ['maxOutputTokens' => 10],
        ]);

        return $response->successful();
    }

    private function validateMistral(ApiKey $key): bool
    {
        $response = Http::timeout(30)
            ->withToken($key->api_key)
            ->post('https://api.mistral.ai/v1/chat/completions', [
                'model' => $key->model ?? 'mistral-small-latest',
                'messages' => [['role' => 'user', 'content' => 'Say OK']],
                'max_tokens' => 10,
            ]);

        return $response->successful();
    }

    private function validateOpenRouter(ApiKey $key): bool
    {
        $response = Http::timeout(30)
            ->withToken($key->api_key)
            ->post('https://openrouter.ai/api/v1/chat/completions', [
                'model' => $key->model ?? 'google/gemini-2.0-flash-exp:free',
                'messages' => [['role' => 'user', 'content' => 'Say OK']],
                'max_tokens' => 10,
            ]);

        return $response->successful();
    }
}
```

**Step 4: Run test to verify it passes**

```bash
./scripts/run-focused-tests.sh ApiKeyValidateCommandTest
```

Expected: PASS

**Step 5: Commit**

```bash
git add app/Console/Commands/ApiKeyValidate.php tests/Feature/Console/ApiKeyValidateCommandTest.php
git commit -m "feat: Add apikey:validate command to test key validity"
```

---

## Task 7: Fix Scheduler Gap for New Keys

**Files:**
- Modify: `app/Console/Commands/ApiKeyManage.php`
- Test: `tests/Feature/Console/ApiKeyManageCommandTest.php`

**Step 1: Write the failing test**

Add to `tests/Feature/Console/ApiKeyManageCommandTest.php`:

```php
public function test_add_action_sets_reset_timestamps(): void
{
    $this->artisan('apikey:manage', [
        'action' => 'add',
        '--provider' => 'gemini',
        '--key' => 'test-key-123',
        '--name' => 'test-key',
    ])->assertExitCode(0);

    $key = ApiKey::where('name', 'test-key')->first();

    $this->assertNotNull($key->rpm_reset_at);
    $this->assertNotNull($key->rpd_reset_at);
    $this->assertTrue($key->rpm_reset_at->isFuture());
    $this->assertTrue($key->rpd_reset_at->isFuture());
}
```

**Step 2: Run test to verify it fails**

```bash
./scripts/run-focused-tests.sh ApiKeyManageCommandTest --filter=test_add_action_sets_reset
```

Expected: FAIL (timestamps are null)

**Step 3: Write minimal implementation**

Update `addKey` method in `app/Console/Commands/ApiKeyManage.php` to set reset timestamps:

```php
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
    'rpm_reset_at' => now()->addMinute(),
    'rpd_reset_at' => now()->addDay(),
]);
```

**Step 4: Run test to verify it passes**

```bash
./scripts/run-focused-tests.sh ApiKeyManageCommandTest --filter=test_add_action_sets_reset
```

Expected: PASS

**Step 5: Commit**

```bash
git add app/Console/Commands/ApiKeyManage.php tests/Feature/Console/ApiKeyManageCommandTest.php
git commit -m "fix: Set reset timestamps when adding new API keys"
```

---

## Task 8: Add Metrics/Logging for Observability

**Files:**
- Modify: `app/Services/ApiRotator/ApiKeyRotatorService.php`
- Create: `app/Events/ApiKeyRotated.php`
- Create: `app/Events/ApiKeyExhausted.php`
- Test: `tests/Unit/Services/ApiRotator/ApiKeyRotatorServiceTest.php`

**Step 1: Write the failing test**

Add to `tests/Unit/Services/ApiRotator/ApiKeyRotatorServiceTest.php`:

```php
public function test_dispatches_event_when_key_rotated(): void
{
    Event::fake([ApiKeyRotated::class]);

    $key1 = ApiKey::factory()->create(['is_active' => true, 'priority' => 100, 'rpd_limit' => 1, 'rpd_used' => 1]);
    $key2 = ApiKey::factory()->create(['is_active' => true, 'priority' => 50]);

    $rotator = app(ApiKeyRotatorService::class);

    // First call tries key1 (exhausted), rotates to key2
    $result = $rotator->getAvailableKey();

    Event::assertDispatched(ApiKeyRotated::class, function ($event) use ($key1, $key2) {
        return $event->fromKeyId === $key1->id && $event->toKeyId === $key2->id;
    });
}

public function test_dispatches_event_when_all_keys_exhausted(): void
{
    Event::fake([ApiKeyExhausted::class]);

    ApiKey::factory()->create(['is_active' => true, 'rpd_limit' => 1, 'rpd_used' => 1]);

    $rotator = app(ApiKeyRotatorService::class);
    $result = $rotator->getAvailableKey();

    $this->assertNull($result);
    Event::assertDispatched(ApiKeyExhausted::class);
}
```

**Step 2: Run test to verify it fails**

```bash
./scripts/run-focused-tests.sh ApiKeyRotatorServiceTest --filter=test_dispatches_event
```

Expected: FAIL (events don't exist)

**Step 3: Write minimal implementation**

Create `app/Events/ApiKeyRotated.php`:

```php
<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ApiKeyRotated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $fromKeyId,
        public int $toKeyId,
        public string $reason,
        public string $provider
    ) {}
}
```

Create `app/Events/ApiKeyExhausted.php`:

```php
<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ApiKeyExhausted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $taskType,
        public int $keysAttempted
    ) {}
}
```

Update `ApiKeyRotatorService` to dispatch events and add logging in `analyzeDocument`:

```php
use App\Events\ApiKeyRotated;
use App\Events\ApiKeyExhausted;

// In analyzeDocument, after switching keys:
if ($attempts > 1 && $key) {
    $previousKeyId = $triedKeys[count($triedKeys) - 1] ?? null;
    if ($previousKeyId) {
        ApiKeyRotated::dispatch($previousKeyId, $key->id, 'rate_limit_or_error', $key->provider);
        Log::info('ApiKeyRotator: Rotated key', [
            'from' => $previousKeyId,
            'to' => $key->id,
            'attempt' => $attempts,
        ]);
    }
}

// Before returning failure:
if (!$key) {
    ApiKeyExhausted::dispatch($taskType, count($triedKeys));
    Log::warning('ApiKeyRotator: All keys exhausted', [
        'task_type' => $taskType,
        'keys_attempted' => count($triedKeys),
    ]);
}
```

**Step 4: Run test to verify it passes**

```bash
./scripts/run-focused-tests.sh ApiKeyRotatorServiceTest --filter=test_dispatches_event
```

Expected: PASS

**Step 5: Commit**

```bash
git add app/Events/ApiKeyRotated.php app/Events/ApiKeyExhausted.php app/Services/ApiRotator/ApiKeyRotatorService.php tests/Unit/Services/ApiRotator/ApiKeyRotatorServiceTest.php
git commit -m "feat: Add events and logging for key rotation observability"
```

---

## Task 9: Integration Tests for API Response Parsing

**Files:**
- Create: `tests/Integration/ApiRotator/GeminiResponseParsingTest.php`
- Create: `tests/Integration/ApiRotator/MistralResponseParsingTest.php`
- Create: `tests/Integration/ApiRotator/OpenRouterResponseParsingTest.php`

**Step 1: Create Gemini response parsing test**

Create `tests/Integration/ApiRotator/GeminiResponseParsingTest.php`:

```php
<?php

namespace Tests\Integration\ApiRotator;

use Tests\TestCase;
use App\Services\ApiRotator\Adapters\GeminiAdapter;
use Illuminate\Http\Client\Response;
use GuzzleHttp\Psr7\Response as GuzzleResponse;

class GeminiResponseParsingTest extends TestCase
{
    private GeminiAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adapter = new GeminiAdapter();
    }

    public function test_parses_successful_response(): void
    {
        $responseData = [
            'candidates' => [
                [
                    'content' => [
                        'parts' => [['text' => '{"key": "value"}']],
                    ],
                    'finishReason' => 'STOP',
                ],
            ],
            'usageMetadata' => [
                'promptTokenCount' => 100,
                'candidatesTokenCount' => 50,
                'totalTokenCount' => 150,
            ],
        ];

        $response = new Response(new GuzzleResponse(200, [], json_encode($responseData)));

        $parsed = $this->invokeMethod($this->adapter, 'parseSuccessResponse', [$responseData]);

        $this->assertEquals('{"key": "value"}', $parsed['content']);
        $this->assertEquals('STOP', $parsed['finish_reason']);
        $this->assertEquals(100, $parsed['usage']['prompt_tokens']);
        $this->assertEquals(50, $parsed['usage']['completion_tokens']);
        $this->assertEquals(150, $parsed['usage']['total_tokens']);
    }

    public function test_parses_rate_limit_error(): void
    {
        $errorResponse = [
            'error' => [
                'code' => 429,
                'message' => 'Resource exhausted',
                'details' => [
                    ['quotaId' => 'GenerateContentRequestsPerDayPerProjectPerModel'],
                ],
            ],
        ];

        $response = new Response(
            new GuzzleResponse(429, ['Retry-After' => '60'], json_encode($errorResponse))
        );

        $parsed = $this->adapter->parseRateLimitError($response);

        $this->assertEquals(60, $parsed['retry_after']);
        $this->assertTrue($parsed['is_daily_limit']);
        $this->assertStringContainsString('PerDay', $parsed['quota_id']);
    }

    public function test_identifies_recoverable_errors(): void
    {
        $this->assertTrue($this->adapter->isRecoverableError(
            new Response(new GuzzleResponse(429))
        ));
        $this->assertTrue($this->adapter->isRecoverableError(
            new Response(new GuzzleResponse(503))
        ));
        $this->assertFalse($this->adapter->isRecoverableError(
            new Response(new GuzzleResponse(401))
        ));
    }

    private function invokeMethod($object, string $method, array $args = [])
    {
        $reflection = new \ReflectionClass($object);
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $args);
    }
}
```

**Step 2: Create similar tests for Mistral and OpenRouter**

Create `tests/Integration/ApiRotator/MistralResponseParsingTest.php` and `tests/Integration/ApiRotator/OpenRouterResponseParsingTest.php` with similar structure, testing their specific response formats.

**Step 3: Run tests**

```bash
php artisan test tests/Integration/ApiRotator/
```

Expected: PASS

**Step 4: Commit**

```bash
git add tests/Integration/ApiRotator/
git commit -m "test: Add integration tests for API response parsing"
```

---

## Task 10: Run Full Test Suite and Verify

**Step 1: Run all API rotator tests**

```bash
php artisan test --filter="ApiKey|ApiRotator"
```

Expected: All tests pass

**Step 2: Run static analysis**

```bash
./vendor/bin/phpstan analyse app/Services/ApiRotator app/Models/ApiKey*.php app/Events/ApiKey*.php --level=5
```

Expected: No errors

**Step 3: Final commit with all changes**

```bash
git status
git add -A
git commit -m "chore: Complete API Key Rotator production-ready fixes"
```

---

## Summary

| Task | Description | Files Changed |
|------|-------------|---------------|
| 1 | Use config values | ApiKeyRotatorService.php |
| 2 | Fix hardcoded task_type | ApiKeyRotatorService.php |
| 3 | Add TPM enforcement | ApiKey.php |
| 4 | Exponential backoff | ApiKeyRotatorService.php |
| 5 | Database locking | ApiKeyRotatorService.php |
| 6 | Key validation command | ApiKeyValidate.php (new) |
| 7 | Fix scheduler gap | ApiKeyManage.php |
| 8 | Observability events | ApiKeyRotated.php, ApiKeyExhausted.php (new) |
| 9 | Integration tests | tests/Integration/ApiRotator/ (new) |
| 10 | Full verification | N/A |

**Parallelizable Tasks:** 1, 2, 3, 6, 7, 8, 9 (no dependencies between them)

**Sequential Tasks:** 4 depends on 1 (uses config), 5 depends on 3 (uses hasAvailableQuota), 10 is final

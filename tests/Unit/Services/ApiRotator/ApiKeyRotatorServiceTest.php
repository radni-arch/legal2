<?php

namespace Tests\Unit\Services\ApiRotator;

use App\Events\ApiKeyExhausted;
use App\Events\ApiKeyRotated;
use App\Models\ApiKey;
use App\Models\ApiKeyCooldown;
use App\Models\ApiKeyUsageLog;
use App\Services\ApiRotator\ApiKeyRotatorService;
use App\Services\ApiRotator\ProviderAdapterFactory;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ApiKeyRotatorServiceTest extends TestCase
{
    use RefreshDatabase;

    private ApiKeyRotatorService $service;
    private ProviderAdapterFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new ProviderAdapterFactory();
        $this->service = new ApiKeyRotatorService($this->factory);
    }

    // =================================================================
    // getAvailableKey Tests
    // =================================================================

    public function test_get_available_key_returns_highest_priority_key(): void
    {
        ApiKey::factory()->create([
            'provider' => 'gemini',
            'priority' => 50,
            'is_active' => true,
        ]);
        $highPriority = ApiKey::factory()->create([
            'provider' => 'gemini',
            'priority' => 80,
            'is_active' => true,
        ]);

        $key = $this->service->getAvailableKey();

        $this->assertNotNull($key);
        $this->assertEquals($highPriority->id, $key->id);
    }

    public function test_get_available_key_returns_null_when_no_keys(): void
    {
        $key = $this->service->getAvailableKey();

        $this->assertNull($key);
    }

    public function test_get_available_key_excludes_inactive_keys(): void
    {
        ApiKey::factory()->create([
            'is_active' => false,
            'priority' => 100,
        ]);

        $key = $this->service->getAvailableKey();

        $this->assertNull($key);
    }

    public function test_get_available_key_excludes_keys_in_cooldown(): void
    {
        $key = ApiKey::factory()->create(['is_active' => true]);

        ApiKeyCooldown::create([
            'api_key_id' => $key->id,
            'cooldown_type' => 'rpm',
            'started_at' => now(),
            'ends_at' => now()->addMinutes(5),
        ]);

        $result = $this->service->getAvailableKey();

        $this->assertNull($result);
    }

    public function test_get_available_key_includes_keys_with_expired_cooldown(): void
    {
        $key = ApiKey::factory()->create(['is_active' => true]);

        ApiKeyCooldown::create([
            'api_key_id' => $key->id,
            'cooldown_type' => 'rpm',
            'started_at' => now()->subMinutes(10),
            'ends_at' => now()->subMinutes(5), // Expired
        ]);

        $result = $this->service->getAvailableKey();

        $this->assertNotNull($result);
        $this->assertEquals($key->id, $result->id);
    }

    public function test_get_available_key_filters_by_pdf_support(): void
    {
        ApiKey::factory()->create([
            'supports_pdf' => false,
            'is_active' => true,
            'priority' => 100,
        ]);
        $pdfKey = ApiKey::factory()->create([
            'supports_pdf' => true,
            'is_active' => true,
            'priority' => 50,
        ]);

        $key = $this->service->getAvailableKey('pdf');

        $this->assertNotNull($key);
        $this->assertEquals($pdfKey->id, $key->id);
    }

    public function test_get_available_key_prefers_specified_provider(): void
    {
        $gemini = ApiKey::factory()->create([
            'provider' => 'gemini',
            'priority' => 50,
            'is_active' => true,
        ]);
        ApiKey::factory()->create([
            'provider' => 'mistral',
            'priority' => 80, // Higher priority
            'is_active' => true,
        ]);

        $key = $this->service->getAvailableKey('general', 'gemini');

        $this->assertEquals($gemini->id, $key->id);
    }

    public function test_get_available_key_falls_back_when_preferred_provider_unavailable(): void
    {
        $gemini = ApiKey::factory()->create([
            'provider' => 'gemini',
            'priority' => 50,
            'is_active' => true,
            'rpd_used' => 100,
            'rpd_limit' => 100, // Exhausted
        ]);
        $mistral = ApiKey::factory()->create([
            'provider' => 'mistral',
            'priority' => 40,
            'is_active' => true,
        ]);

        $key = $this->service->getAvailableKey('general', 'gemini');

        // Should fall back to mistral since gemini is exhausted
        $this->assertEquals($mistral->id, $key->id);
    }

    // =================================================================
    // createCooldown Tests
    // =================================================================

    public function test_create_cooldown_creates_record(): void
    {
        $key = ApiKey::factory()->create();

        $cooldown = $this->service->createCooldown(
            $key,
            'rpm',
            60,
            'Test cooldown',
            'client',
            60,
            ['test' => 'data']
        );

        $this->assertInstanceOf(ApiKeyCooldown::class, $cooldown);
        $this->assertEquals($key->id, $cooldown->api_key_id);
        $this->assertEquals('rpm', $cooldown->cooldown_type);
        $this->assertEquals('Test cooldown', $cooldown->reason);
        $this->assertEquals('client', $cooldown->source);
        $this->assertEquals(60, $cooldown->retry_after_seconds);
        $this->assertEquals(['test' => 'data'], $cooldown->metadata);
        $this->assertTrue($cooldown->ends_at->isFuture());
    }

    // =================================================================
    // resetMinuteCounters Tests
    // =================================================================

    public function test_reset_minute_counters_resets_expired_keys(): void
    {
        $key = ApiKey::factory()->create([
            'rpm_used' => 10,
            'tpm_used' => 50000,
            'rpm_reset_at' => now()->subMinutes(2),
        ]);

        $count = $this->service->resetMinuteCounters();

        $key->refresh();
        $this->assertEquals(1, $count);
        $this->assertEquals(0, $key->rpm_used);
        $this->assertEquals(0, $key->tpm_used);
        $this->assertTrue($key->rpm_reset_at->isFuture());
    }

    public function test_reset_minute_counters_does_not_reset_fresh_keys(): void
    {
        $key = ApiKey::factory()->create([
            'rpm_used' => 10,
            'rpm_reset_at' => now()->addMinutes(1), // Still in the future
        ]);

        $count = $this->service->resetMinuteCounters();

        $key->refresh();
        $this->assertEquals(0, $count);
        $this->assertEquals(10, $key->rpm_used);
    }

    // =================================================================
    // resetDailyCounters Tests
    // =================================================================

    public function test_reset_daily_counters_resets_expired_keys(): void
    {
        $key = ApiKey::factory()->create([
            'provider' => 'gemini',
            'rpd_used' => 50,
            'tpd_used' => 100000,
            'rpd_reset_at' => now()->subDay(),
        ]);

        $count = $this->service->resetDailyCounters();

        $key->refresh();
        $this->assertGreaterThanOrEqual(1, $count);
        $this->assertEquals(0, $key->rpd_used);
        $this->assertEquals(0, $key->tpd_used);
    }

    public function test_reset_daily_counters_deletes_expired_cooldowns(): void
    {
        $key = ApiKey::factory()->create(['provider' => 'gemini']);

        ApiKeyCooldown::create([
            'api_key_id' => $key->id,
            'cooldown_type' => 'rpm',
            'started_at' => now()->subHour(),
            'ends_at' => now()->subMinutes(30), // Expired
        ]);

        $this->service->resetDailyCounters();

        $this->assertEquals(0, ApiKeyCooldown::count());
    }

    // =================================================================
    // getStatus Tests
    // =================================================================

    public function test_get_status_returns_all_active_keys(): void
    {
        ApiKey::factory()->create([
            'name' => 'Test Key 1',
            'provider' => 'gemini',
            'is_active' => true,
            'rpm_used' => 5,
            'rpm_limit' => 10,
            'rpd_used' => 25,
            'rpd_limit' => 100,
        ]);

        $status = $this->service->getStatus();

        $this->assertCount(1, $status);
        $this->assertEquals('Test Key 1', $status[0]['name']);
        $this->assertEquals('gemini', $status[0]['provider']);
        $this->assertStringContainsString('5/10', $status[0]['rpm']);
        $this->assertStringContainsString('25/100', $status[0]['rpd']);
        $this->assertFalse($status[0]['in_cooldown']);
        $this->assertTrue($status[0]['available']);
    }

    public function test_get_status_shows_cooldown_info(): void
    {
        $key = ApiKey::factory()->create(['is_active' => true]);

        ApiKeyCooldown::create([
            'api_key_id' => $key->id,
            'cooldown_type' => 'rpm',
            'started_at' => now(),
            'ends_at' => now()->addMinutes(5),
        ]);

        $status = $this->service->getStatus();

        $this->assertTrue($status[0]['in_cooldown']);
        $this->assertFalse($status[0]['available']);
        $this->assertNotNull($status[0]['cooldown_ends']);
    }

    public function test_get_status_excludes_inactive_keys(): void
    {
        ApiKey::factory()->create(['is_active' => false]);
        ApiKey::factory()->create(['is_active' => true]);

        $status = $this->service->getStatus();

        $this->assertCount(1, $status);
    }

    // =================================================================
    // Config Values Tests
    // =================================================================

    public function test_service_uses_config_values(): void
    {
        // Set config values before resolving the service
        config(['api_rotator.proactive_threshold' => 0.5]);
        config(['api_rotator.max_retries' => 5]);
        config(['api_rotator.base_retry_delay' => 10]);

        // Clear the singleton to force re-instantiation with new config values
        $this->app->forgetInstance(ApiKeyRotatorService::class);

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

    // =================================================================
    // recordSuccess / handleError Tests
    // =================================================================

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

    // =================================================================
    // Event Dispatching Tests
    // =================================================================

    public function test_dispatches_event_when_all_keys_exhausted(): void
    {
        Event::fake([ApiKeyExhausted::class]);

        // Create a key with no remaining quota
        ApiKey::factory()->create([
            'is_active' => true,
            'rpd_limit' => 1,
            'rpd_used' => 1,
        ]);

        $rotator = app(ApiKeyRotatorService::class);
        $result = $rotator->analyzeDocument('/tmp/test.pdf', 'system', 'user', 'pdf');

        $this->assertFalse($result['success']);
        Event::assertDispatched(ApiKeyExhausted::class, function ($event) {
            return $event->taskType === 'pdf' && $event->keysAttempted === 0;
        });
    }

    public function test_dispatches_event_when_key_rotated_after_failure(): void
    {
        Event::fake([ApiKeyRotated::class]);

        // Create two keys - first will fail, second will be tried
        $key1 = ApiKey::factory()->create([
            'is_active' => true,
            'provider' => 'gemini',
            'priority' => 100,
        ]);
        $key2 = ApiKey::factory()->create([
            'is_active' => true,
            'provider' => 'gemini',
            'priority' => 50,
        ]);

        $rotator = app(ApiKeyRotatorService::class);

        // Mock adapter to fail on first key then succeed on second
        $mockFactory = $this->createMock(ProviderAdapterFactory::class);
        $mockAdapter = $this->createMock(\App\Services\ApiRotator\Contracts\ProviderAdapterInterface::class);

        $callCount = 0;
        $mockAdapter->method('analyzeDocument')->willReturnCallback(function () use (&$callCount) {
            $callCount++;
            if ($callCount === 1) {
                // First call fails with recoverable error
                $response = new \Illuminate\Http\Client\Response(
                    new \GuzzleHttp\Psr7\Response(429, [], '{"error": "rate limited"}')
                );
                return [
                    'response' => $response,
                    'parsed' => null,
                    'rate_limit_info' => [],
                ];
            }
            // Second call succeeds
            $response = new \Illuminate\Http\Client\Response(
                new \GuzzleHttp\Psr7\Response(200, [], '{"content": "test"}')
            );
            return [
                'response' => $response,
                'parsed' => ['content' => 'test', 'usage' => ['total_tokens' => 100]],
                'rate_limit_info' => [],
            ];
        });
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

        $result = $rotator->analyzeDocument('/tmp/test.pdf', 'system', 'user', 'pdf');

        $this->assertTrue($result['success']);
        Event::assertDispatched(ApiKeyRotated::class, function ($event) use ($key1, $key2) {
            return $event->fromKeyId === $key1->id
                && $event->toKeyId === $key2->id
                && $event->reason === 'rate_limit_or_error'
                && $event->provider === 'gemini';
        });
    }

    // =================================================================
    // Exponential Backoff Tests
    // =================================================================

    public function test_exponential_backoff_delays_between_retries(): void
    {
        // Use a small base delay for fast testing (0.1 seconds)
        config(['api_rotator.base_retry_delay' => 0.1]);

        // Clear singleton to pick up new config
        $this->app->forgetInstance(ApiKeyRotatorService::class);

        // Create multiple keys that will all fail
        ApiKey::factory()->count(3)->create([
            'is_active' => true,
            'provider' => 'gemini',
        ]);

        $rotator = app(ApiKeyRotatorService::class);

        // Mock adapter to always fail with recoverable error
        $mockFactory = $this->createMock(ProviderAdapterFactory::class);
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

        // With base_retry_delay=0.1 and 3 keys:
        // Attempt 1: delay = 0.1 * 2^0 = 0.1s
        // Attempt 2: delay = 0.1 * 2^1 = 0.2s
        // Attempt 3: no delay (last attempt)
        // Total minimum delay: 0.3s
        // Note: delays happen AFTER each attempt (except the last)
        $this->assertGreaterThan(0.2, $elapsed, 'Should have exponential backoff delays between retries');
    }
}

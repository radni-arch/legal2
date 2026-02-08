<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\ApiKey;
use App\Models\ApiKeyCooldown;
use App\Services\ApiRotator\ApiKeyRotatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

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

    public function test_inactive_key_is_not_returned(): void
    {
        ApiKey::factory()->create(['is_active' => false, 'priority' => 100]);
        ApiKey::factory()->create(['is_active' => true, 'priority' => 50]);

        $rotator = app(ApiKeyRotatorService::class);
        $key = $rotator->getAvailableKey();

        $this->assertEquals(50, $key->priority);
    }

    public function test_pdf_task_returns_pdf_supporting_key(): void
    {
        ApiKey::factory()->create(['supports_pdf' => false, 'priority' => 100, 'is_active' => true]);
        ApiKey::factory()->create(['supports_pdf' => true, 'priority' => 50, 'is_active' => true]);

        $rotator = app(ApiKeyRotatorService::class);
        $key = $rotator->getAvailableKey('pdf');

        $this->assertTrue($key->supports_pdf);
    }

    public function test_exhausted_key_is_not_returned(): void
    {
        ApiKey::factory()->create([
            'is_active' => true,
            'rpd_limit' => 100,
            'rpd_used' => 100,
        ]);

        $rotator = app(ApiKeyRotatorService::class);
        $key = $rotator->getAvailableKey();

        $this->assertNull($key);
    }

    public function test_create_cooldown_creates_record(): void
    {
        $key = ApiKey::factory()->create(['is_active' => true]);
        $rotator = app(ApiKeyRotatorService::class);

        $cooldown = $rotator->createCooldown($key, 'rpm', 60, 'Test cooldown');

        $this->assertDatabaseHas('api_key_cooldowns', [
            'api_key_id' => $key->id,
            'cooldown_type' => 'rpm',
            'reason' => 'Test cooldown',
        ]);
    }

    public function test_get_status_returns_all_active_keys(): void
    {
        ApiKey::factory()->count(3)->create(['is_active' => true]);
        ApiKey::factory()->create(['is_active' => false]);

        $rotator = app(ApiKeyRotatorService::class);
        $status = $rotator->getStatus();

        $this->assertCount(3, $status);
    }

    public function test_preferred_provider_is_selected_first(): void
    {
        ApiKey::factory()->create(['provider' => 'mistral', 'priority' => 100, 'is_active' => true]);
        ApiKey::factory()->create(['provider' => 'gemini', 'priority' => 50, 'is_active' => true]);

        $rotator = app(ApiKeyRotatorService::class);
        $key = $rotator->getAvailableKey('general', 'gemini');

        $this->assertEquals('gemini', $key->provider);
    }

    public function test_reset_daily_counters_cleans_expired_cooldowns(): void
    {
        $key = ApiKey::factory()->create(['is_active' => true]);

        ApiKeyCooldown::create([
            'api_key_id' => $key->id,
            'cooldown_type' => 'rpm',
            'started_at' => now()->subHours(2),
            'ends_at' => now()->subHour(),
        ]);

        $rotator = app(ApiKeyRotatorService::class);
        $rotator->resetDailyCounters();

        $this->assertDatabaseMissing('api_key_cooldowns', [
            'api_key_id' => $key->id,
        ]);
    }

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

    public function test_get_next_key_with_lock_returns_available_key(): void
    {
        $key = ApiKey::factory()->create(['is_active' => true]);

        $rotator = app(ApiKeyRotatorService::class);
        $result = $rotator->getNextKeyWithLock('general', []);

        $this->assertEquals($key->id, $result->id);
    }
}

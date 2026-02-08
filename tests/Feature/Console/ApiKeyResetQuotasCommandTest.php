<?php

namespace Tests\Feature\Console;

use App\Models\ApiKey;
use App\Services\ApiRotator\ApiKeyRotatorService;
use App\Services\ApiRotator\ProviderAdapterFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Test suite for ApiKeyResetQuotas command.
 *
 * Tests the CLI interface for resetting API key usage quotas:
 * - minute: Reset per-minute counters (rpm_used, tpm_used)
 * - daily: Reset per-day counters (rpd_used, tpd_used)
 * - all: Reset both minute and daily counters
 */
class ApiKeyResetQuotasCommandTest extends TestCase
{
    use RefreshDatabase;

    // =================================================================
    // Minute Reset Tests
    // =================================================================

    /** @test */
    public function reset_minute_type_resets_minute_counters(): void
    {
        ApiKey::factory()->create([
            'rpm_used' => 10,
            'tpm_used' => 50000,
            'rpm_reset_at' => now()->subMinutes(2),
        ]);

        $this->artisan('apikey:reset-quotas', ['--type' => 'minute'])
            ->expectsOutputToContain('Reset minute counters for')
            ->assertExitCode(0);

        $key = ApiKey::first();
        $this->assertEquals(0, $key->rpm_used);
        $this->assertEquals(0, $key->tpm_used);
    }

    /** @test */
    public function reset_minute_type_does_not_reset_daily_counters(): void
    {
        ApiKey::factory()->create([
            'rpm_used' => 10,
            'rpd_used' => 50,
            'rpm_reset_at' => now()->subMinutes(2),
        ]);

        $this->artisan('apikey:reset-quotas', ['--type' => 'minute'])
            ->assertExitCode(0);

        $key = ApiKey::first();
        $this->assertEquals(0, $key->rpm_used);
        $this->assertEquals(50, $key->rpd_used); // Daily counter unchanged
    }

    // =================================================================
    // Daily Reset Tests
    // =================================================================

    /** @test */
    public function reset_daily_type_resets_daily_counters(): void
    {
        ApiKey::factory()->create([
            'provider' => 'gemini',
            'rpd_used' => 100,
            'tpd_used' => 500000,
            'rpd_reset_at' => now()->subDay(),
        ]);

        $this->artisan('apikey:reset-quotas', ['--type' => 'daily'])
            ->expectsOutputToContain('Reset daily counters for')
            ->assertExitCode(0);

        $key = ApiKey::first();
        $this->assertEquals(0, $key->rpd_used);
        $this->assertEquals(0, $key->tpd_used);
    }

    /** @test */
    public function reset_daily_type_does_not_reset_minute_counters(): void
    {
        ApiKey::factory()->create([
            'provider' => 'gemini',
            'rpm_used' => 10,
            'rpd_used' => 50,
            'rpd_reset_at' => now()->subDay(),
        ]);

        $this->artisan('apikey:reset-quotas', ['--type' => 'daily'])
            ->assertExitCode(0);

        $key = ApiKey::first();
        $this->assertEquals(10, $key->rpm_used); // Minute counter unchanged
        $this->assertEquals(0, $key->rpd_used);
    }

    // =================================================================
    // All Reset Tests
    // =================================================================

    /** @test */
    public function reset_all_type_resets_both_counters(): void
    {
        ApiKey::factory()->create([
            'provider' => 'gemini',
            'rpm_used' => 10,
            'tpm_used' => 50000,
            'rpd_used' => 100,
            'tpd_used' => 500000,
            'rpm_reset_at' => now()->subMinutes(2),
            'rpd_reset_at' => now()->subDay(),
        ]);

        $this->artisan('apikey:reset-quotas', ['--type' => 'all'])
            ->expectsOutputToContain('Reset minute counters for')
            ->expectsOutputToContain('Reset daily counters for')
            ->assertExitCode(0);

        $key = ApiKey::first();
        $this->assertEquals(0, $key->rpm_used);
        $this->assertEquals(0, $key->tpm_used);
        $this->assertEquals(0, $key->rpd_used);
        $this->assertEquals(0, $key->tpd_used);
    }

    /** @test */
    public function default_type_is_all(): void
    {
        ApiKey::factory()->create([
            'provider' => 'gemini',
            'rpm_used' => 10,
            'rpd_used' => 50,
            'rpm_reset_at' => now()->subMinutes(2),
            'rpd_reset_at' => now()->subDay(),
        ]);

        $this->artisan('apikey:reset-quotas')
            ->expectsOutputToContain('Reset minute counters for')
            ->expectsOutputToContain('Reset daily counters for')
            ->assertExitCode(0);
    }

    // =================================================================
    // Edge Cases
    // =================================================================

    /** @test */
    public function reset_works_with_no_keys(): void
    {
        $this->artisan('apikey:reset-quotas', ['--type' => 'all'])
            ->expectsOutput('Reset minute counters for 0 keys.')
            ->expectsOutput('Reset daily counters for 0 keys.')
            ->assertExitCode(0);
    }

    /** @test */
    public function reset_reports_count_of_affected_keys(): void
    {
        ApiKey::factory()->count(3)->create([
            'provider' => 'gemini',
            'rpm_used' => 5,
            'rpm_reset_at' => now()->subMinutes(2),
        ]);

        $this->artisan('apikey:reset-quotas', ['--type' => 'minute'])
            ->expectsOutput('Reset minute counters for 3 keys.')
            ->assertExitCode(0);
    }

    /** @test */
    public function reset_only_affects_expired_counters(): void
    {
        // Key with expired minute reset (should be reset)
        ApiKey::factory()->create([
            'name' => 'Expired Key',
            'rpm_used' => 10,
            'rpm_reset_at' => now()->subMinutes(2),
        ]);

        // Key with future minute reset (should NOT be reset)
        ApiKey::factory()->create([
            'name' => 'Fresh Key',
            'rpm_used' => 5,
            'rpm_reset_at' => now()->addMinutes(1),
        ]);

        $this->artisan('apikey:reset-quotas', ['--type' => 'minute'])
            ->expectsOutput('Reset minute counters for 1 keys.')
            ->assertExitCode(0);

        $expiredKey = ApiKey::where('name', 'Expired Key')->first();
        $freshKey = ApiKey::where('name', 'Fresh Key')->first();

        $this->assertEquals(0, $expiredKey->rpm_used);
        $this->assertEquals(5, $freshKey->rpm_used);
    }
}

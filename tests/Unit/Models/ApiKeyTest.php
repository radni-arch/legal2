<?php

namespace Tests\Unit\Models;

use App\Models\ApiKey;
use PHPUnit\Framework\TestCase;

/**
 * Pure unit tests for ApiKey model methods.
 * These tests do not require database access.
 */
class ApiKeyTest extends TestCase
{
    /**
     * Create an ApiKey instance with the given attributes without saving to database.
     */
    private function makeApiKey(array $attributes = []): ApiKey
    {
        $key = new ApiKey();
        $key->forceFill(array_merge([
            'name' => 'test-key',
            'provider' => 'gemini',
            'model' => 'gemini-2.0-flash-exp',
            'is_active' => true,
            'rpm_limit' => 100,
            'rpm_used' => 0,
            'rpd_limit' => 1000,
            'rpd_used' => 0,
            'tpm_limit' => 250000,
            'tpm_used' => 0,
            'priority' => 50,
        ], $attributes));

        return $key;
    }

    // =================================================================
    // getRemainingRpm Tests
    // =================================================================

    public function test_get_remaining_rpm_calculates_correctly(): void
    {
        $key = $this->makeApiKey([
            'rpm_limit' => 100,
            'rpm_used' => 30,
        ]);

        $this->assertEquals(70, $key->getRemainingRpm());
    }

    public function test_get_remaining_rpm_returns_zero_when_exhausted(): void
    {
        $key = $this->makeApiKey([
            'rpm_limit' => 100,
            'rpm_used' => 100,
        ]);

        $this->assertEquals(0, $key->getRemainingRpm());
    }

    public function test_get_remaining_rpm_does_not_go_negative(): void
    {
        $key = $this->makeApiKey([
            'rpm_limit' => 100,
            'rpm_used' => 150, // Over limit
        ]);

        $this->assertEquals(0, $key->getRemainingRpm());
    }

    // =================================================================
    // getRemainingRpd Tests
    // =================================================================

    public function test_get_remaining_rpd_calculates_correctly(): void
    {
        $key = $this->makeApiKey([
            'rpd_limit' => 1000,
            'rpd_used' => 250,
        ]);

        $this->assertEquals(750, $key->getRemainingRpd());
    }

    public function test_get_remaining_rpd_returns_zero_when_exhausted(): void
    {
        $key = $this->makeApiKey([
            'rpd_limit' => 1000,
            'rpd_used' => 1000,
        ]);

        $this->assertEquals(0, $key->getRemainingRpd());
    }

    // =================================================================
    // getRemainingTpm Tests
    // =================================================================

    public function test_get_remaining_tpm_calculates_correctly(): void
    {
        $key = $this->makeApiKey([
            'tpm_limit' => 250000,
            'tpm_used' => 100000,
        ]);

        $this->assertEquals(150000, $key->getRemainingTpm());
    }

    public function test_get_remaining_tpm_returns_zero_when_exhausted(): void
    {
        $key = $this->makeApiKey([
            'tpm_limit' => 1000,
            'tpm_used' => 1000,
        ]);

        $this->assertEquals(0, $key->getRemainingTpm());
    }

    public function test_get_remaining_tpm_returns_max_int_when_unlimited(): void
    {
        $key = $this->makeApiKey([
            'tpm_limit' => 0, // 0 means unlimited
            'tpm_used' => 999999,
        ]);

        $this->assertEquals(PHP_INT_MAX, $key->getRemainingTpm());
    }

    public function test_get_remaining_tpm_does_not_go_negative(): void
    {
        $key = $this->makeApiKey([
            'tpm_limit' => 1000,
            'tpm_used' => 1500, // Over limit
        ]);

        $this->assertEquals(0, $key->getRemainingTpm());
    }

    // =================================================================
    // hasAvailableQuota Tests
    // =================================================================

    public function test_has_available_quota_returns_true_when_all_quotas_available(): void
    {
        $key = $this->makeApiKey([
            'rpm_limit' => 100,
            'rpm_used' => 50,
            'rpd_limit' => 1000,
            'rpd_used' => 500,
            'tpm_limit' => 250000,
            'tpm_used' => 100000,
        ]);

        $this->assertTrue($key->hasAvailableQuota());
    }

    public function test_has_available_quota_returns_false_when_rpm_exhausted(): void
    {
        $key = $this->makeApiKey([
            'rpm_limit' => 100,
            'rpm_used' => 100,
            'rpd_limit' => 1000,
            'rpd_used' => 0,
            'tpm_limit' => 250000,
            'tpm_used' => 0,
        ]);

        $this->assertFalse($key->hasAvailableQuota());
    }

    public function test_has_available_quota_returns_false_when_rpd_exhausted(): void
    {
        $key = $this->makeApiKey([
            'rpm_limit' => 100,
            'rpm_used' => 0,
            'rpd_limit' => 1000,
            'rpd_used' => 1000,
            'tpm_limit' => 250000,
            'tpm_used' => 0,
        ]);

        $this->assertFalse($key->hasAvailableQuota());
    }

    public function test_has_available_quota_returns_false_when_tpm_exhausted(): void
    {
        $key = $this->makeApiKey([
            'rpm_limit' => 100,
            'rpm_used' => 0,
            'rpd_limit' => 1000,
            'rpd_used' => 0,
            'tpm_limit' => 1000,
            'tpm_used' => 1000,
        ]);

        $this->assertFalse($key->hasAvailableQuota());
    }

    public function test_has_available_quota_ignores_tpm_when_unlimited(): void
    {
        $key = $this->makeApiKey([
            'rpm_limit' => 100,
            'rpm_used' => 0,
            'rpd_limit' => 1000,
            'rpd_used' => 0,
            'tpm_limit' => 0, // 0 means unlimited
            'tpm_used' => 999999,
        ]);

        $this->assertTrue($key->hasAvailableQuota());
    }
}

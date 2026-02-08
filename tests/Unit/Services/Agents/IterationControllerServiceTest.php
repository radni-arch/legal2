<?php

namespace Tests\Unit\Services\Agents;

use App\Services\Agents\IterationControllerService;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Unit tests for IterationControllerService
 *
 * TDD RED phase - writing tests before implementation
 */
class IterationControllerServiceTest extends TestCase
{
    protected IterationControllerService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new IterationControllerService;

        // Suppress logs in tests
        Log::shouldReceive('info')->andReturn(null);
        Log::shouldReceive('debug')->andReturn(null);
        Log::shouldReceive('warning')->andReturn(null);
    }

    /** @test */
    public function test_should_continue_when_limits_not_reached(): void
    {
        $limits = [
            'max_iterations' => 5,
            'quality_threshold' => 85,
        ];

        $result = $this->service->shouldContinue(1, 50, $limits);

        $this->assertTrue($result);
        $this->assertEquals('', $this->service->getStopReason());
    }

    /** @test */
    public function test_stops_at_max_iterations(): void
    {
        $limits = ['max_iterations' => 5];

        $result = $this->service->shouldContinue(5, 50, $limits);

        $this->assertFalse($result);
        $this->assertEquals('max_iterations', $this->service->getStopReason());
    }

    /** @test */
    public function test_stops_when_quality_threshold_met(): void
    {
        $limits = [
            'max_iterations' => 10,
            'quality_threshold' => 85,
        ];

        $result = $this->service->shouldContinue(2, 90, $limits);

        $this->assertFalse($result);
        $this->assertEquals('quality_threshold_met', $this->service->getStopReason());
    }

    /** @test */
    public function test_stops_when_quality_exactly_at_threshold(): void
    {
        $limits = ['quality_threshold' => 85];

        $result = $this->service->shouldContinue(1, 85, $limits);

        $this->assertFalse($result);
        $this->assertEquals('quality_threshold_met', $this->service->getStopReason());
    }

    /** @test */
    public function test_continues_when_quality_below_threshold(): void
    {
        $limits = [
            'max_iterations' => 10,
            'quality_threshold' => 85,
        ];

        $result = $this->service->shouldContinue(1, 84, $limits);

        $this->assertTrue($result);
    }

    /** @test */
    public function test_stops_when_token_budget_exceeded(): void
    {
        $limits = [
            'max_iterations' => 10,
            'token_budget' => 1000,
        ];

        // Track 500 tokens first
        $this->service->trackUsage(['tokens' => 500]);

        // Check should continue
        $this->assertTrue($this->service->shouldContinue(1, 50, $limits));

        // Track another 600 tokens (total 1100, exceeds budget)
        $this->service->trackUsage(['tokens' => 600]);

        $result = $this->service->shouldContinue(2, 50, $limits);

        $this->assertFalse($result);
        $this->assertEquals('token_budget_exceeded', $this->service->getStopReason());
    }

    /** @test */
    public function test_stops_when_token_budget_exactly_met(): void
    {
        $limits = ['token_budget' => 1000];

        $this->service->trackUsage(['tokens' => 1000]);

        $result = $this->service->shouldContinue(1, 50, $limits);

        $this->assertFalse($result);
        $this->assertEquals('token_budget_exceeded', $this->service->getStopReason());
    }

    /** @test */
    public function test_stops_when_time_budget_exceeded(): void
    {
        $limits = [
            'max_iterations' => 10,
            'time_budget' => 60.0, // 60 seconds
        ];

        // Track 30 seconds
        $this->service->trackUsage(['time' => 30.0]);

        $this->assertTrue($this->service->shouldContinue(1, 50, $limits));

        // Track another 40 seconds (total 70, exceeds budget)
        $this->service->trackUsage(['time' => 40.0]);

        $result = $this->service->shouldContinue(2, 50, $limits);

        $this->assertFalse($result);
        $this->assertEquals('time_budget_exceeded', $this->service->getStopReason());
    }

    /** @test */
    public function test_track_usage_accumulates_tokens(): void
    {
        $this->service->trackUsage(['tokens' => 100]);
        $this->service->trackUsage(['tokens' => 200]);
        $this->service->trackUsage(['tokens' => 150]);

        $this->assertEquals(450, $this->service->getTotalTokens());
    }

    /** @test */
    public function test_track_usage_accumulates_time(): void
    {
        $this->service->trackUsage(['time' => 10.5]);
        $this->service->trackUsage(['time' => 20.3]);
        $this->service->trackUsage(['time' => 5.2]);

        $this->assertEquals(36.0, $this->service->getTotalTime());
    }

    /** @test */
    public function test_track_usage_handles_missing_tokens(): void
    {
        $this->service->trackUsage(['time' => 10.0]);

        $this->assertEquals(0, $this->service->getTotalTokens());
        $this->assertEquals(10.0, $this->service->getTotalTime());
    }

    /** @test */
    public function test_track_usage_handles_missing_time(): void
    {
        $this->service->trackUsage(['tokens' => 100]);

        $this->assertEquals(100, $this->service->getTotalTokens());
        $this->assertEquals(0.0, $this->service->getTotalTime());
    }

    /** @test */
    public function test_reset_clears_all_state(): void
    {
        // Set some state
        $this->service->trackUsage(['tokens' => 500, 'time' => 30.0]);
        $this->service->shouldContinue(5, 50, ['max_iterations' => 5]);

        // Verify state exists
        $this->assertEquals(500, $this->service->getTotalTokens());
        $this->assertEquals(30.0, $this->service->getTotalTime());
        $this->assertEquals('max_iterations', $this->service->getStopReason());

        // Reset
        $this->service->reset();

        // Verify state cleared
        $this->assertEquals(0, $this->service->getTotalTokens());
        $this->assertEquals(0.0, $this->service->getTotalTime());
        $this->assertEquals('', $this->service->getStopReason());
    }

    /** @test */
    public function test_uses_default_limits_when_not_specified(): void
    {
        // When max_iterations is not specified, should have some default behavior
        $result = $this->service->shouldContinue(1, 50, []);

        // Should continue since no limits are enforced
        $this->assertTrue($result);
    }

    /** @test */
    public function test_multiple_limits_checked_in_priority_order(): void
    {
        $limits = [
            'max_iterations' => 3,
            'quality_threshold' => 85,
            'token_budget' => 1000,
        ];

        // Should stop at max iterations even if quality not met
        $result = $this->service->shouldContinue(3, 50, $limits);

        $this->assertFalse($result);
        $this->assertEquals('max_iterations', $this->service->getStopReason());
    }

    /** @test */
    public function test_quality_threshold_takes_precedence_over_iterations(): void
    {
        $limits = [
            'max_iterations' => 10,
            'quality_threshold' => 85,
        ];

        // Iteration 2, but quality met
        $result = $this->service->shouldContinue(2, 90, $limits);

        $this->assertFalse($result);
        $this->assertEquals('quality_threshold_met', $this->service->getStopReason());
    }

    /** @test */
    public function test_token_budget_checked_after_quality_and_iterations(): void
    {
        $limits = [
            'max_iterations' => 10,
            'quality_threshold' => 85,
            'token_budget' => 100,
        ];

        $this->service->trackUsage(['tokens' => 150]);

        $result = $this->service->shouldContinue(2, 50, $limits);

        $this->assertFalse($result);
        $this->assertEquals('token_budget_exceeded', $this->service->getStopReason());
    }

    /** @test */
    public function test_stop_reason_persists_across_calls(): void
    {
        $limits = ['max_iterations' => 3];

        $this->service->shouldContinue(3, 50, $limits);

        // Stop reason should persist
        $this->assertEquals('max_iterations', $this->service->getStopReason());
        $this->assertEquals('max_iterations', $this->service->getStopReason());
    }

    /** @test */
    public function test_handles_zero_iteration(): void
    {
        $limits = ['max_iterations' => 5];

        $result = $this->service->shouldContinue(0, 50, $limits);

        $this->assertTrue($result);
    }

    /** @test */
    public function test_handles_negative_quality_score(): void
    {
        $limits = ['quality_threshold' => 85];

        $result = $this->service->shouldContinue(1, -10, $limits);

        $this->assertTrue($result);
    }

    /** @test */
    public function test_handles_over_100_quality_score(): void
    {
        $limits = ['quality_threshold' => 85];

        $result = $this->service->shouldContinue(1, 120, $limits);

        $this->assertFalse($result);
        $this->assertEquals('quality_threshold_met', $this->service->getStopReason());
    }

    /** @test */
    public function test_no_token_budget_allows_unlimited_tokens(): void
    {
        $limits = ['max_iterations' => 10];

        $this->service->trackUsage(['tokens' => 999999]);

        $result = $this->service->shouldContinue(1, 50, $limits);

        $this->assertTrue($result);
    }

    /** @test */
    public function test_no_time_budget_allows_unlimited_time(): void
    {
        $limits = ['max_iterations' => 10];

        $this->service->trackUsage(['time' => 999999.0]);

        $result = $this->service->shouldContinue(1, 50, $limits);

        $this->assertTrue($result);
    }

    /** @test */
    public function test_fractional_time_tracking(): void
    {
        $this->service->trackUsage(['time' => 1.234]);
        $this->service->trackUsage(['time' => 2.567]);

        $this->assertEquals(3.801, $this->service->getTotalTime());
    }

    /** @test */
    public function test_large_token_numbers(): void
    {
        $this->service->trackUsage(['tokens' => 1000000]);
        $this->service->trackUsage(['tokens' => 500000]);

        $this->assertEquals(1500000, $this->service->getTotalTokens());
    }

    /** @test */
    public function test_combined_usage_tracking(): void
    {
        $this->service->trackUsage(['tokens' => 100, 'time' => 10.0]);
        $this->service->trackUsage(['tokens' => 200, 'time' => 20.0]);

        $this->assertEquals(300, $this->service->getTotalTokens());
        $this->assertEquals(30.0, $this->service->getTotalTime());
    }

    /** @test */
    public function test_realistic_research_scenario(): void
    {
        $limits = [
            'max_iterations' => 5,
            'quality_threshold' => 85,
            'token_budget' => 10000,
            'time_budget' => 120.0,
        ];

        // Iteration 1
        $this->assertTrue($this->service->shouldContinue(1, 60, $limits));
        $this->service->trackUsage(['tokens' => 1500, 'time' => 15.0]);

        // Iteration 2
        $this->assertTrue($this->service->shouldContinue(2, 70, $limits));
        $this->service->trackUsage(['tokens' => 1800, 'time' => 18.0]);

        // Iteration 3 - quality met
        $this->assertFalse($this->service->shouldContinue(3, 87, $limits));
        $this->assertEquals('quality_threshold_met', $this->service->getStopReason());

        // Verify totals
        $this->assertEquals(3300, $this->service->getTotalTokens());
        $this->assertEquals(33.0, $this->service->getTotalTime());
    }
}

<?php

namespace Tests\Feature\Commands;

use App\Services\Graph\GraphDataIntegrityService;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

/**
 * TDD Tests for graph:integrity-check command
 *
 * Phase 1 - Task 1.5: Create Artisan command for graph health checks
 * Task 4.3: Cross-Database Integrity Scheduling - Added email option tests
 */
class GraphIntegrityCheckCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test that command runs and outputs report
     *
     * @test
     */
    public function it_runs_integrity_check_and_outputs_report(): void
    {
        $mockService = Mockery::mock(GraphDataIntegrityService::class);
        $mockService->shouldReceive('generateIntegrityReport')
            ->once()
            ->andReturn([
                'orphan_nodes' => [],
                'missing_nodes' => [],
                'dangling_relationships' => [],
                'generated_at' => now()->toIso8601String(),
                'summary' => [
                    'total_orphan_nodes' => 0,
                    'total_missing_nodes' => 0,
                    'total_dangling_relationships' => 0,
                    'health_status' => 'healthy',
                ],
            ]);

        $this->app->instance(GraphDataIntegrityService::class, $mockService);

        $this->artisan('graph:integrity-check')
            ->expectsOutputToContain('Graph Integrity Check')
            ->assertExitCode(0);
    }

    /**
     * Test that command returns failure when issues found
     *
     * @test
     */
    public function it_returns_failure_exit_code_when_issues_found(): void
    {
        $mockService = Mockery::mock(GraphDataIntegrityService::class);
        $mockService->shouldReceive('generateIntegrityReport')
            ->once()
            ->andReturn([
                'orphan_nodes' => ['CourtDecisionDocument' => [['id' => 'orphan-1']]],
                'missing_nodes' => [],
                'dangling_relationships' => [],
                'generated_at' => now()->toIso8601String(),
                'summary' => [
                    'total_orphan_nodes' => 1,
                    'total_missing_nodes' => 0,
                    'total_dangling_relationships' => 0,
                    'health_status' => 'needs_attention',
                ],
            ]);

        $this->app->instance(GraphDataIntegrityService::class, $mockService);

        $this->artisan('graph:integrity-check')
            ->assertExitCode(1);
    }

    /**
     * Test that command can output JSON
     *
     * @test
     */
    public function it_outputs_json_when_json_flag_provided(): void
    {
        $mockService = Mockery::mock(GraphDataIntegrityService::class);
        $mockService->shouldReceive('generateIntegrityReport')
            ->once()
            ->andReturn([
                'orphan_nodes' => [],
                'missing_nodes' => [],
                'dangling_relationships' => [],
                'generated_at' => '2026-01-07T00:00:00+00:00',
                'summary' => [
                    'total_orphan_nodes' => 0,
                    'total_missing_nodes' => 0,
                    'total_dangling_relationships' => 0,
                    'health_status' => 'healthy',
                ],
            ]);

        $this->app->instance(GraphDataIntegrityService::class, $mockService);

        $this->artisan('graph:integrity-check', ['--json' => true])
            ->expectsOutputToContain('"health_status"')
            ->assertExitCode(0);
    }

    /**
     * Test that command has --email option
     *
     * Task 4.3: Cross-Database Integrity Scheduling
     *
     * @test
     */
    public function it_accepts_email_option(): void
    {
        Mail::fake();

        $mockService = Mockery::mock(GraphDataIntegrityService::class);
        $mockService->shouldReceive('generateIntegrityReport')
            ->once()
            ->andReturn([
                'orphan_nodes' => [],
                'missing_nodes' => [],
                'dangling_relationships' => [],
                'generated_at' => now()->toIso8601String(),
                'summary' => [
                    'total_orphan_nodes' => 0,
                    'total_missing_nodes' => 0,
                    'total_dangling_relationships' => 0,
                    'health_status' => 'healthy',
                ],
            ]);

        $this->app->instance(GraphDataIntegrityService::class, $mockService);

        // Command should run without errors with --email option
        $this->artisan('graph:integrity-check', ['--email' => 'test@example.com'])
            ->expectsOutputToContain('Graph Integrity Check')
            ->assertExitCode(0);
    }

    /**
     * Test that command sends email when issues found and email provided
     *
     * Task 4.3: Cross-Database Integrity Scheduling
     *
     * @test
     */
    public function it_sends_email_when_issues_found_and_email_provided(): void
    {
        // Mock Mail facade to capture sent emails
        Mail::shouldReceive('raw')
            ->once()
            ->withArgs(function ($content, $callback) {
                // Verify the content includes expected report data
                return str_contains($content, 'HEALTH STATUS') &&
                       str_contains($content, 'Orphan Nodes');
            })
            ->andReturnUsing(function ($content, $callback) {
                // Create a mock message
                $message = Mockery::mock(\Illuminate\Mail\Message::class);
                $message->shouldReceive('to')->with('admin@example.com')->andReturnSelf();
                $message->shouldReceive('subject')->andReturnSelf();
                $callback($message);
            });

        $mockService = Mockery::mock(GraphDataIntegrityService::class);
        $mockService->shouldReceive('generateIntegrityReport')
            ->once()
            ->andReturn([
                'orphan_nodes' => ['CourtDecisionDocument' => [['id' => 'orphan-1']]],
                'missing_nodes' => ['LawDocument' => ['missing-1']],
                'dangling_relationships' => [],
                'generated_at' => now()->toIso8601String(),
                'summary' => [
                    'total_orphan_nodes' => 1,
                    'total_missing_nodes' => 1,
                    'total_dangling_relationships' => 0,
                    'health_status' => 'needs_attention',
                ],
            ]);

        $this->app->instance(GraphDataIntegrityService::class, $mockService);

        $this->artisan('graph:integrity-check', ['--email' => 'admin@example.com'])
            ->expectsOutputToContain('Report emailed to: admin@example.com')
            ->assertExitCode(1);
    }

    /**
     * Test that command does not send email when healthy and email provided
     *
     * Task 4.3: Cross-Database Integrity Scheduling
     *
     * @test
     */
    public function it_does_not_send_email_when_healthy(): void
    {
        Mail::fake();

        $mockService = Mockery::mock(GraphDataIntegrityService::class);
        $mockService->shouldReceive('generateIntegrityReport')
            ->once()
            ->andReturn([
                'orphan_nodes' => [],
                'missing_nodes' => [],
                'dangling_relationships' => [],
                'generated_at' => now()->toIso8601String(),
                'summary' => [
                    'total_orphan_nodes' => 0,
                    'total_missing_nodes' => 0,
                    'total_dangling_relationships' => 0,
                    'health_status' => 'healthy',
                ],
            ]);

        $this->app->instance(GraphDataIntegrityService::class, $mockService);

        $this->artisan('graph:integrity-check', ['--email' => 'admin@example.com'])
            ->assertExitCode(0);

        // No email should be sent for healthy status
        Mail::assertNothingSent();
    }

    /**
     * Test that command uses config email when --email used without value
     *
     * Task 4.3: Cross-Database Integrity Scheduling
     *
     * @test
     */
    public function it_uses_config_email_when_no_email_value_provided(): void
    {
        config(['integrity.admin_email' => 'config-admin@example.com']);

        // Mock Mail facade
        Mail::shouldReceive('raw')
            ->once()
            ->andReturnUsing(function ($content, $callback) {
                $message = Mockery::mock(\Illuminate\Mail\Message::class);
                $message->shouldReceive('to')->with('config-admin@example.com')->andReturnSelf();
                $message->shouldReceive('subject')->andReturnSelf();
                $callback($message);
            });

        $mockService = Mockery::mock(GraphDataIntegrityService::class);
        $mockService->shouldReceive('generateIntegrityReport')
            ->once()
            ->andReturn([
                'orphan_nodes' => ['CourtDecisionDocument' => [['id' => 'orphan-1']]],
                'missing_nodes' => [],
                'dangling_relationships' => [],
                'generated_at' => now()->toIso8601String(),
                'summary' => [
                    'total_orphan_nodes' => 1,
                    'total_missing_nodes' => 0,
                    'total_dangling_relationships' => 0,
                    'health_status' => 'needs_attention',
                ],
            ]);

        $this->app->instance(GraphDataIntegrityService::class, $mockService);

        // Run with --email flag but no explicit email address
        $this->artisan('graph:integrity-check --email')
            ->expectsOutputToContain('Report emailed to: config-admin@example.com')
            ->assertExitCode(1);
    }
}

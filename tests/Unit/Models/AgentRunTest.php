<?php

namespace Tests\Unit\Models;

use App\Models\AgentRun;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class AgentRunTest extends TestCase
{
    use UsesTestDatabase;

    public function test_has_correct_fillable_attributes(): void
    {
        // Arrange & Act
        $run = AgentRun::create(['objective' => 'Test objective',
            'agent_name' => 'research',
            'objective' => 'Research case law',
            'status' => 'running',
            'context' => ['jurisdiction' => 'HR'],
            'topics' => ['contract', 'dispute'],
            'iterations' => [['step' => 1, 'result' => 'completed']],
            'checkpoint_state' => ['iteration' => 5],
            'can_resume' => true,
            'current_iteration' => 5,
            'max_iterations' => 10,
            'score' => 0.85,
            'threshold' => 0.8,
            'token_budget' => 10000.50,
            'tokens_used' => 5000.25,
            'cost_budget' => 5.0000,
            'cost_spent' => 2.5000,
            'started_at' => now(),
        ]);

        // Assert
        $this->assertEquals('research', $run->agent_name);
        $this->assertEquals('running', $run->status);
        $this->assertIsArray($run->context);
        $this->assertIsArray($run->topics);
        $this->assertIsArray($run->iterations);
        $this->assertTrue($run->can_resume);
        $this->assertEquals(0.85, $run->score);
    }

    public function test_casts_arrays_correctly(): void
    {
        // Arrange & Act
        $run = AgentRun::create(['objective' => 'Test objective',
            'agent_name' => 'test-agent',
            'status' => 'running',
            'context' => ['key' => 'value'],
            'topics' => ['topic1', 'topic2'],
            'iterations' => [['step' => 1]],
            'checkpoint_state' => ['state' => 'saved'],
        ]);

        // Assert
        $this->assertIsArray($run->context);
        $this->assertIsArray($run->topics);
        $this->assertIsArray($run->iterations);
        $this->assertIsArray($run->checkpoint_state);
    }

    /** @test */
    public function it_has_correct_fillable_attributes()
    {
        $run = AgentRun::create(['objective' => 'Test objective',
            'agent_name' => 'researcher',
            'job_id' => 'job-123',
            'queue' => 'default',
            'objective' => 'Research Croatian criminal law',
            'context' => ['case_id' => 'case-456'],
            'topics' => ['criminal', 'procedure'],
            'status' => 'running',
            'max_iterations' => 5,
            'threshold' => 0.8,
            'started_at' => now(),
        ]);

        $this->assertEquals('researcher', $run->agent_name);
        $this->assertEquals('running', $run->status);
        $this->assertIsArray($run->context);
        $this->assertIsArray($run->topics);
        $this->assertCount(1, $run->context);
        $this->assertCount(2, $run->topics);
    }

    /** @test */
    public function it_casts_attributes_correctly()
    {
        $run = AgentRun::factory()->create([
            'context' => ['key' => 'value'],
            'topics' => ['topic1', 'topic2'],
            'iterations' => [['step' => 1]],
            'checkpoint_state' => ['state' => 'paused'],
            'can_resume' => true,
            'score' => 0.85,
            'threshold' => 0.7,
        ]);

        $this->assertIsArray($run->context);
        $this->assertIsArray($run->topics);
        $this->assertIsArray($run->iterations);
        $this->assertIsArray($run->checkpoint_state);
        $this->assertEquals('value', $run->context['key']);
    }

    public function test_casts_boolean_correctly(): void
    {
        // Arrange & Act
        $run = AgentRun::create(['objective' => 'Test objective',
            'agent_name' => 'test-agent',
            'status' => 'running',
            'can_resume' => true,
        ]);

        // Assert
        $this->assertIsBool($run->can_resume);
        $this->assertTrue($run->can_resume);
    }

    public function test_casts_float_correctly(): void
    {
        // Arrange & Act
        $run = AgentRun::create(['objective' => 'Test objective',
            'agent_name' => 'test-agent',
            'status' => 'running',
            'score' => 0.95,
            'threshold' => 0.8,
        ]);

        // Assert
        $this->assertIsFloat($run->score);
        $this->assertIsFloat($run->threshold);
        $this->assertEquals(0.95, $run->score);
    }

    public function test_casts_datetime_correctly(): void
    {
        // Arrange
        $now = now();

        // Act
        $run = AgentRun::create(['objective' => 'Test objective',
            'agent_name' => 'test-agent',
            'status' => 'running',
            'started_at' => $now,
            'completed_at' => $now->copy()->addMinutes(10),
            'last_checkpoint_at' => $now->copy()->addMinutes(5),
        ]);

        // Assert
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $run->started_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $run->completed_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $run->last_checkpoint_at);
    }

    public function test_can_be_resumed_returns_true_when_conditions_met(): void
    {
        // Arrange
        $run = AgentRun::create(['objective' => 'Test objective',
            'agent_name' => 'test-agent',
            'status' => 'paused',
            'can_resume' => true,
            'checkpoint_state' => ['iteration' => 5, 'data' => 'saved'],
        ]);

        // Act
        $result = $run->canBeResumed();

        // Assert
        $this->assertTrue($result);
    }

    public function test_can_be_resumed_returns_false_when_not_paused(): void
    {
        // Arrange
        $run = AgentRun::create(['objective' => 'Test objective',
            'agent_name' => 'test-agent',
            'status' => 'running',
            'can_resume' => true,
            'checkpoint_state' => ['iteration' => 5],
        ]);

        // Act
        $result = $run->canBeResumed();

        // Assert
        $this->assertFalse($result);
    }

    public function test_can_be_resumed_returns_false_when_cannot_resume(): void
    {
        // Arrange
        $run = AgentRun::create(['objective' => 'Test objective',
            'agent_name' => 'test-agent',
            'status' => 'paused',
            'can_resume' => false,
            'checkpoint_state' => ['iteration' => 5],
        ]);

        // Act
        $result = $run->canBeResumed();

        // Assert
        $this->assertFalse($result);
    }

    public function test_can_be_resumed_returns_false_when_no_checkpoint_state(): void
    {
        // Arrange
        $run = AgentRun::create(['objective' => 'Test objective',
            'agent_name' => 'test-agent',
            'status' => 'paused',
            'can_resume' => true,
            'checkpoint_state' => null,
        ]);

        // Act
        $result = $run->canBeResumed();

        // Assert
        $this->assertFalse($result);
    }

    /** @test */
    public function it_casts_datetime_attributes()
    {
        $run = AgentRun::factory()->create([
            'started_at' => now()->subHour(),
            'completed_at' => now(),
            'last_checkpoint_at' => now()->subMinutes(30),
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $run->started_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $run->completed_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $run->last_checkpoint_at);
    }

    /** @test */
    public function it_calculates_progress_percentage_correctly()
    {
        $run = AgentRun::factory()->create([
            'max_iterations' => 10,
            'current_iteration' => 5,
        ]);

        $progress = $run->getProgressPercentage();

        $this->assertEquals(50.0, $progress);
    }

    /** @test */
    public function it_returns_zero_progress_when_max_iterations_is_zero()
    {
        $run = AgentRun::factory()->create([
            'max_iterations' => 0,
            'current_iteration' => 0,
        ]);

        $progress = $run->getProgressPercentage();

        $this->assertEquals(0, $progress);
    }

    /** @test */
    public function it_returns_100_percent_when_current_equals_max()
    {
        $run = AgentRun::factory()->create([
            'max_iterations' => 5,
            'current_iteration' => 5,
        ]);

        $progress = $run->getProgressPercentage();

        $this->assertEquals(100.0, $progress);
    }

    /** @test */
    public function it_determines_if_run_can_be_resumed()
    {
        $run = AgentRun::factory()->paused()->create();

        $this->assertTrue($run->canBeResumed());
    }

    /** @test */
    public function it_cannot_be_resumed_if_not_paused()
    {
        $run = AgentRun::factory()->running()->create([
            'can_resume' => true,
            'checkpoint_state' => ['data' => 'state'],
        ]);

        $this->assertFalse($run->canBeResumed());
    }

    /** @test */
    public function it_cannot_be_resumed_if_can_resume_is_false()
    {
        $run = AgentRun::factory()->create([
            'status' => 'paused',
            'can_resume' => false,
            'checkpoint_state' => ['data' => 'state'],
        ]);

        $this->assertFalse($run->canBeResumed());
    }

    /** @test */
    public function it_cannot_be_resumed_if_no_checkpoint_state()
    {
        $run = AgentRun::factory()->create([
            'status' => 'paused',
            'can_resume' => true,
            'checkpoint_state' => null,
        ]);

        // Act
        $result = $run->canBeResumed();

        // Assert
        $this->assertFalse($result);
    }

    public function test_get_progress_percentage_calculates_correctly(): void
    {
        // Arrange
        $run = AgentRun::create(['objective' => 'Test objective',
            'agent_name' => 'test-agent',
            'status' => 'running',
            'current_iteration' => 7,
            'max_iterations' => 10,
        ]);

        // Act
        $progress = $run->getProgressPercentage();

        // Assert
        $this->assertEquals(70.0, $progress);
    }

    public function test_get_progress_percentage_returns_zero_when_max_iterations_is_zero(): void
    {
        // Arrange
        $run = AgentRun::create(['objective' => 'Test objective',
            'agent_name' => 'test-agent',
            'status' => 'running',
            'current_iteration' => 5,
            'max_iterations' => 0,
        ]);

        // Act
        $progress = $run->getProgressPercentage();

        // Assert
        $this->assertEquals(0, $progress);
    }

    public function test_get_progress_percentage_rounds_to_one_decimal(): void
    {
        // Arrange
        $run = AgentRun::create(['objective' => 'Test objective',
            'agent_name' => 'test-agent',
            'status' => 'running',
            'current_iteration' => 1,
            'max_iterations' => 3,
        ]);

        // Act
        $progress = $run->getProgressPercentage();

        // Assert
        $this->assertEquals(33.3, $progress);
    }

    public function test_stores_decimal_budget_and_usage(): void
    {
        // Arrange & Act
        $run = AgentRun::create(['objective' => 'Test objective',
            'agent_name' => 'test-agent',
            'status' => 'running',
            'token_budget' => 100000.50,
            'tokens_used' => 50000.25,
            'cost_budget' => 10.5000,
            'cost_spent' => 5.2500,
        ]);

        // Assert
        $this->assertEquals('100000.50', $run->token_budget);
        $this->assertEquals('50000.25', $run->tokens_used);
        $this->assertEquals('10.5000', $run->cost_budget);
        $this->assertEquals('5.2500', $run->cost_spent);
    }

    public function test_stores_job_id_and_queue(): void
    {
        // Arrange & Act
        $run = AgentRun::create(['objective' => 'Test objective',
            'agent_name' => 'test-agent',
            'status' => 'running',
            'job_id' => 'job-uuid-12345',
            'queue' => 'agent-research',
        ]);

        // Assert
        $this->assertEquals('job-uuid-12345', $run->job_id);
        $this->assertEquals('agent-research', $run->queue);
    }

    public function test_stores_error_information(): void
    {
        // Arrange & Act
        $run = AgentRun::create(['objective' => 'Test objective',
            'agent_name' => 'test-agent',
            'status' => 'failed',
            'error' => 'API timeout after 30 seconds',
        ]);

        // Assert
        $this->assertEquals('failed', $run->status);
        $this->assertEquals('API timeout after 30 seconds', $run->error);
    }

    public function test_stores_elapsed_seconds(): void
    {
        // Arrange & Act
        $run = AgentRun::create(['objective' => 'Test objective',
            'agent_name' => 'test-agent',
            'status' => 'completed',
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
            'elapsed_seconds' => 300,
        ]);

        // Assert
        $this->assertEquals(300, $run->elapsed_seconds);
    }

    public function test_stores_final_output(): void
    {
        // Arrange & Act
        $run = AgentRun::create(['objective' => 'Test objective',
            'agent_name' => 'test-agent',
            'status' => 'completed',
            'final_output' => 'Research findings: Found 10 relevant cases...',
        ]);

        // Assert
        $this->assertStringContainsString('Research findings', $run->final_output);
        $this->assertStringContainsString('10 relevant cases', $run->final_output);
    }

    public function test_handles_null_optional_fields(): void
    {
        // Arrange & Act - explicitly set defaults for required fields, null for optional
        $run = AgentRun::create(['objective' => 'Test objective',
            'agent_name' => 'test-agent',
            'status' => 'running',
            'context' => [],  // Required, has default {}
            'iterations' => [], // Required, has default []
            'topics' => null,
            'checkpoint_state' => null,
        ]);

        // Assert - required fields have values, nullable fields can be null
        $this->assertIsArray($run->context);
        $this->assertIsArray($run->iterations);
        $this->assertNull($run->topics);
        $this->assertNull($run->checkpoint_state);
    }

    public function test_progress_percentage_handles_completion(): void
    {
        // Arrange
        $run = AgentRun::create(['objective' => 'Test objective',
            'agent_name' => 'test-agent',
            'status' => 'completed',
            'current_iteration' => 10,
            'max_iterations' => 10,
        ]);

        // Act
        $progress = $run->getProgressPercentage();

        // Assert
        $this->assertEquals(100.0, $progress);
    }

    public function test_stores_complex_checkpoint_state(): void
    {
        // Arrange & Act
        $checkpointState = [
            'iteration' => 5,
            'last_query' => 'contract disputes',
            'findings' => [
                ['case_id' => '123', 'relevance' => 0.95],
                ['case_id' => '456', 'relevance' => 0.88],
            ],
            'next_action' => 'analyze_findings',
        ];

        $run = AgentRun::create(['objective' => 'Test objective',
            'agent_name' => 'test-agent',
            'status' => 'paused',
            'can_resume' => true,
            'checkpoint_state' => $checkpointState,
        ]);

        // Assert
        $this->assertEquals(5, $run->checkpoint_state['iteration']);
        $this->assertEquals('contract disputes', $run->checkpoint_state['last_query']);
        $this->assertCount(2, $run->checkpoint_state['findings']);
        $this->assertEquals('analyze_findings', $run->checkpoint_state['next_action']);
    }

    public function test_stores_complex_iterations_array(): void
    {
        // Arrange & Act
        $iterations = [
            ['iteration' => 1, 'action' => 'search', 'result' => 'found 5 cases'],
            ['iteration' => 2, 'action' => 'analyze', 'result' => 'analyzed 5 cases'],
            ['iteration' => 3, 'action' => 'summarize', 'result' => 'generated summary'],
        ];

        $run = AgentRun::create(['objective' => 'Test objective',
            'agent_name' => 'test-agent',
            'status' => 'running',
            'current_iteration' => 3,
            'iterations' => $iterations,
        ]);

        // Assert
        $this->assertCount(3, $run->iterations);
        $this->assertEquals('search', $run->iterations[0]['action']);
        $this->assertEquals('summarize', $run->iterations[2]['action']);
        $this->assertFalse($run->canBeResumed());
    }

    /** @test */
    public function it_handles_pending_status()
    {
        $run = AgentRun::factory()->create(['status' => 'pending']);

        $this->assertEquals('pending', $run->status);
        $this->assertNull($run->started_at);
        $this->assertNull($run->completed_at);
    }

    /** @test */
    public function it_handles_running_status()
    {
        $run = AgentRun::factory()->running()->create();

        $this->assertEquals('running', $run->status);
        $this->assertNotNull($run->started_at);
        $this->assertNull($run->completed_at);
    }

    /** @test */
    public function it_handles_completed_status()
    {
        $run = AgentRun::factory()->completed()->create();

        $this->assertEquals('completed', $run->status);
        $this->assertNotNull($run->started_at);
        $this->assertNotNull($run->completed_at);
        $this->assertNotNull($run->final_output);
    }

    /** @test */
    public function it_handles_failed_status()
    {
        $run = AgentRun::factory()->failed()->create();

        $this->assertEquals('failed', $run->status);
        $this->assertNotNull($run->error);
        $this->assertNotNull($run->completed_at);
    }

    /** @test */
    public function it_handles_paused_status()
    {
        $run = AgentRun::factory()->paused()->create();

        $this->assertEquals('paused', $run->status);
        $this->assertTrue($run->can_resume);
        $this->assertNotNull($run->checkpoint_state);
        $this->assertNotNull($run->last_checkpoint_at);
    }

    /** @test */
    public function it_tracks_token_usage_and_cost()
    {
        $run = AgentRun::factory()->create([
            'token_budget' => '100000.00',
            'tokens_used' => '50000.00',
            'cost_budget' => '10.0000',
            'cost_spent' => '5.5000',
        ]);

        $this->assertEquals('100000.00', $run->token_budget);
        $this->assertEquals('50000.00', $run->tokens_used);
        $this->assertEquals('10.0000', $run->cost_budget);
        $this->assertEquals('5.5000', $run->cost_spent);
    }

    /** @test */
    public function it_stores_iterations_as_array()
    {
        $iterations = [
            ['iteration' => 1, 'result' => 'Finding A'],
            ['iteration' => 2, 'result' => 'Finding B'],
        ];

        $run = AgentRun::factory()->create(['iterations' => $iterations]);

        $this->assertIsArray($run->iterations);
        $this->assertCount(2, $run->iterations);
        $this->assertEquals('Finding A', $run->iterations[0]['result']);
    }

    /** @test */
    public function it_stores_checkpoint_state_correctly()
    {
        $checkpointState = [
            'current_step' => 'research',
            'data' => ['findings' => ['fact1', 'fact2']],
            'next_action' => 'analyze',
        ];

        $run = AgentRun::factory()->paused()->create([
            'checkpoint_state' => $checkpointState,
        ]);

        $this->assertIsArray($run->checkpoint_state);
        $this->assertEquals('research', $run->checkpoint_state['current_step']);
        $this->assertCount(2, $run->checkpoint_state['data']['findings']);
    }

    /** @test */
    public function it_handles_elapsed_time_tracking()
    {
        $run = AgentRun::factory()->completed()->create([
            'started_at' => now()->subMinutes(30),
            'completed_at' => now(),
            'elapsed_seconds' => 1800,
        ]);

        $this->assertEquals(1800, $run->elapsed_seconds);
        $this->assertEqualsWithDelta(1800, $run->started_at->diffInSeconds($run->completed_at), 5);
    }

    /** @test */
    public function it_stores_final_output_as_array()
    {
        $finalOutput = [
            'summary' => 'Research complete',
            'findings' => ['finding1', 'finding2'],
            'recommendations' => ['recommendation1'],
        ];

        $run = AgentRun::factory()->completed()->create([
            'final_output' => $finalOutput,
        ]);

        $this->assertIsArray($run->final_output);
        $this->assertEquals('Research complete', $run->final_output['summary']);
        $this->assertCount(2, $run->final_output['findings']);
    }

    /** @test */
    public function it_handles_time_limit_configuration()
    {
        $run = AgentRun::factory()->create([
            'time_limit_seconds' => 600,
        ]);

        $this->assertEquals(600, $run->time_limit_seconds);
    }

    /** @test */
    public function it_tracks_score_against_threshold()
    {
        $run = AgentRun::factory()->completed()->create([
            'threshold' => 0.7,
            'score' => 0.85,
        ]);

        $this->assertGreaterThan($run->threshold, $run->score);
    }

    /** @test */
    public function it_handles_null_optional_fields()
    {
        $run = AgentRun::factory()->create([
            'final_output' => null,
            'error' => null,
            'checkpoint_state' => null,
        ]);

        $this->assertNull($run->final_output);
        $this->assertNull($run->error);
        $this->assertNull($run->checkpoint_state);
        $this->assertEquals(0.0, $run->score); // score has default value, not null
    }
}

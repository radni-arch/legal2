<?php

namespace Tests\Unit\Models;

use App\Models\AgentCollaboration;
use App\Models\AgentExecution;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class AgentCollaborationTest extends TestCase
{
    use UsesTestDatabase;

    public function test_uses_string_primary_key(): void
    {
        // Arrange & Act
        $collaboration = AgentCollaboration::create([
            'orchestrator' => 'main-orchestrator',
            'problem_type' => 'legal-research',
            'problem_statement' => 'Research contract law precedents',
            'status' => 'pending',
        ]);

        // Assert
        $this->assertIsString($collaboration->id);
        $this->assertNotEmpty($collaboration->id);
        $this->assertFalse($collaboration->incrementing);
        $this->assertEquals('string', $collaboration->getKeyType());
    }

    public function test_auto_generates_uuid_on_creation(): void
    {
        // Arrange & Act
        $collaboration = AgentCollaboration::create([
            'orchestrator' => 'test-orchestrator',
            'problem_type' => 'analysis',
            'problem_statement' => 'Test problem',
            'status' => 'pending',
        ]);

        // Assert
        $this->assertNotEmpty($collaboration->id);
        $this->assertTrue(Str::isUuid($collaboration->id));
    }

    public function test_auto_generates_session_id_on_creation(): void
    {
        // Arrange & Act
        $collaboration = AgentCollaboration::create([
            'orchestrator' => 'test-orchestrator',
            'problem_type' => 'analysis',
            'problem_statement' => 'Test problem',
            'status' => 'pending',
        ]);

        // Assert
        $this->assertNotEmpty($collaboration->session_id);
        $this->assertStringStartsWith('collab_', $collaboration->session_id);
    }

    public function test_has_correct_fillable_attributes(): void
    {
        // Arrange & Act
        $collaboration = AgentCollaboration::create([
            'id' => Str::uuid()->toString(),
            'session_id' => 'custom_session_123',
            'orchestrator' => 'legal-orchestrator',
            'problem_type' => 'contract-analysis',
            'problem_statement' => 'Analyze employment contract for compliance',
            'context' => ['jurisdiction' => 'HR', 'industry' => 'tech'],
            'status' => 'in_progress',
            'agents_involved' => ['research-agent', 'analysis-agent'],
            'execution_plan' => ['step1' => 'research', 'step2' => 'analyze'],
            'shared_memory' => ['key1' => 'value1'],
            'agent_outputs' => ['agent1' => ['result' => 'completed']],
            'final_result' => ['conclusion' => 'compliant'],
            'synthesis' => 'Contract is compliant with HR labor laws',
            'total_steps' => 5,
            'completed_steps' => 2,
            'tokens_used' => 10000,
            'cost_spent' => 1.5000,
            'duration_seconds' => 300,
            'started_at' => now(),
        ]);

        // Assert
        $this->assertEquals('legal-orchestrator', $collaboration->orchestrator);
        $this->assertEquals('contract-analysis', $collaboration->problem_type);
        $this->assertEquals('in_progress', $collaboration->status);
        $this->assertEquals(5, $collaboration->total_steps);
        $this->assertEquals(2, $collaboration->completed_steps);
    }

    public function test_has_many_executions(): void
    {
        // Arrange
        $collaboration = AgentCollaboration::create([
            'orchestrator' => 'test-orchestrator',
            'problem_type' => 'analysis',
            'problem_statement' => 'Test problem',
            'status' => 'in_progress',
        ]);

        AgentExecution::create([
            'collaboration_id' => $collaboration->id,
            'agent_name' => 'agent-1',
            'agent_role' => 'researcher',
            'execution_order' => 1,
            'status' => 'completed',
        ]);

        AgentExecution::create([
            'collaboration_id' => $collaboration->id,
            'agent_name' => 'agent-2',
            'agent_role' => 'analyzer',
            'execution_order' => 2,
            'status' => 'running',
        ]);

        // Act
        $executions = $collaboration->executions;

        // Assert
        $this->assertCount(2, $executions);
        $this->assertInstanceOf(AgentExecution::class, $executions->first());
        $this->assertEquals(1, $executions->first()->execution_order);
    }

    public function test_has_many_completed_executions(): void
    {
        // Arrange
        $collaboration = AgentCollaboration::create([
            'orchestrator' => 'test-orchestrator',
            'problem_type' => 'analysis',
            'problem_statement' => 'Test problem',
            'status' => 'in_progress',
        ]);

        AgentExecution::create([
            'collaboration_id' => $collaboration->id,
            'agent_name' => 'agent-1',
            'execution_order' => 1,
            'status' => 'completed',
        ]);

        AgentExecution::create([
            'collaboration_id' => $collaboration->id,
            'agent_name' => 'agent-2',
            'execution_order' => 2,
            'status' => 'completed',
        ]);

        AgentExecution::create([
            'collaboration_id' => $collaboration->id,
            'agent_name' => 'agent-3',
            'execution_order' => 3,
            'status' => 'running',
        ]);

        // Act
        $completedExecutions = $collaboration->completedExecutions;

        // Assert
        $this->assertCount(2, $completedExecutions);
        $this->assertEquals('completed', $completedExecutions->first()->status);
    }

    public function test_casts_context_as_array(): void
    {
        // Arrange & Act
        $context = [
            'jurisdiction' => 'HR',
            'case_type' => 'civil',
            'priority' => 'high',
        ];

        $collaboration = AgentCollaboration::create([
            'orchestrator' => 'test-orchestrator',
            'problem_type' => 'analysis',
            'problem_statement' => 'Test problem',
            'status' => 'pending',
            'context' => $context,
        ]);

        // Assert
        $this->assertIsArray($collaboration->context);
        $this->assertEquals('HR', $collaboration->context['jurisdiction']);
        $this->assertEquals('high', $collaboration->context['priority']);
    }

    public function test_casts_agents_involved_as_array(): void
    {
        // Arrange & Act
        $collaboration = AgentCollaboration::create([
            'orchestrator' => 'test-orchestrator',
            'problem_type' => 'analysis',
            'problem_statement' => 'Test problem',
            'status' => 'pending',
            'agents_involved' => ['research-agent', 'analysis-agent', 'synthesis-agent'],
        ]);

        // Assert
        $this->assertIsArray($collaboration->agents_involved);
        $this->assertCount(3, $collaboration->agents_involved);
        $this->assertContains('research-agent', $collaboration->agents_involved);
    }

    public function test_casts_shared_memory_as_array(): void
    {
        // Arrange & Act
        $sharedMemory = [
            'findings' => ['case1', 'case2'],
            'context' => 'contract dispute',
        ];

        $collaboration = AgentCollaboration::create([
            'orchestrator' => 'test-orchestrator',
            'problem_type' => 'analysis',
            'problem_statement' => 'Test problem',
            'status' => 'pending',
            'shared_memory' => $sharedMemory,
        ]);

        // Assert
        $this->assertIsArray($collaboration->shared_memory);
        $this->assertCount(2, $collaboration->shared_memory['findings']);
    }

    public function test_casts_datetime_fields_correctly(): void
    {
        // Arrange
        $startTime = now();
        $endTime = now()->addMinutes(10);

        // Act
        $collaboration = AgentCollaboration::create([
            'orchestrator' => 'test-orchestrator',
            'problem_type' => 'analysis',
            'problem_statement' => 'Test problem',
            'status' => 'completed',
            'started_at' => $startTime,
            'completed_at' => $endTime,
        ]);

        // Assert
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $collaboration->started_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $collaboration->completed_at);
    }

    public function test_scope_in_progress(): void
    {
        // Arrange
        AgentCollaboration::create([
            'orchestrator' => 'test',
            'problem_type' => 'analysis',
            'problem_statement' => 'Problem 1',
            'status' => 'in_progress',
        ]);

        AgentCollaboration::create([
            'orchestrator' => 'test',
            'problem_type' => 'analysis',
            'problem_statement' => 'Problem 2',
            'status' => 'completed',
        ]);

        // Act
        $inProgress = AgentCollaboration::inProgress()->get();

        // Assert
        $this->assertCount(1, $inProgress);
        $this->assertEquals('in_progress', $inProgress->first()->status);
    }

    public function test_scope_completed(): void
    {
        // Arrange
        AgentCollaboration::create([
            'orchestrator' => 'test',
            'problem_type' => 'analysis',
            'problem_statement' => 'Problem 1',
            'status' => 'completed',
        ]);

        AgentCollaboration::create([
            'orchestrator' => 'test',
            'problem_type' => 'analysis',
            'problem_statement' => 'Problem 2',
            'status' => 'in_progress',
        ]);

        // Act
        $completed = AgentCollaboration::completed()->get();

        // Assert
        $this->assertCount(1, $completed);
        $this->assertEquals('completed', $completed->first()->status);
    }

    public function test_scope_failed(): void
    {
        // Arrange
        AgentCollaboration::create([
            'orchestrator' => 'test',
            'problem_type' => 'analysis',
            'problem_statement' => 'Problem 1',
            'status' => 'failed',
        ]);

        AgentCollaboration::create([
            'orchestrator' => 'test',
            'problem_type' => 'analysis',
            'problem_statement' => 'Problem 2',
            'status' => 'completed',
        ]);

        // Act
        $failed = AgentCollaboration::failed()->get();

        // Assert
        $this->assertCount(1, $failed);
        $this->assertEquals('failed', $failed->first()->status);
    }

    public function test_scope_recent(): void
    {
        // Arrange
        AgentCollaboration::create([
            'orchestrator' => 'test',
            'problem_type' => 'analysis',
            'problem_statement' => 'Recent problem',
            'status' => 'completed',
            'started_at' => now()->subDays(3),
        ]);

        AgentCollaboration::create([
            'orchestrator' => 'test',
            'problem_type' => 'analysis',
            'problem_statement' => 'Old problem',
            'status' => 'completed',
            'started_at' => now()->subDays(10),
        ]);

        // Act
        $recent = AgentCollaboration::recent(7)->get();

        // Assert
        $this->assertCount(1, $recent);
        $this->assertEquals('Recent problem', $recent->first()->problem_statement);
    }

    public function test_add_to_shared_memory(): void
    {
        // Arrange
        $collaboration = AgentCollaboration::create([
            'orchestrator' => 'test-orchestrator',
            'problem_type' => 'analysis',
            'problem_statement' => 'Test problem',
            'status' => 'in_progress',
        ]);

        // Act
        $collaboration->addToSharedMemory('findings', ['case1', 'case2']);
        $collaboration->addToSharedMemory('status', 'research_complete');

        // Assert
        $collaboration->refresh();
        $this->assertEquals(['case1', 'case2'], $collaboration->shared_memory['findings']);
        $this->assertEquals('research_complete', $collaboration->shared_memory['status']);
    }

    public function test_get_from_shared_memory(): void
    {
        // Arrange
        $collaboration = AgentCollaboration::create([
            'orchestrator' => 'test-orchestrator',
            'problem_type' => 'analysis',
            'problem_statement' => 'Test problem',
            'status' => 'in_progress',
            'shared_memory' => [
                'findings' => ['case1', 'case2'],
                'context' => 'contract law',
            ],
        ]);

        // Act
        $findings = $collaboration->getFromSharedMemory('findings');
        $context = $collaboration->getFromSharedMemory('context');
        $missing = $collaboration->getFromSharedMemory('nonexistent', 'default');

        // Assert
        $this->assertEquals(['case1', 'case2'], $findings);
        $this->assertEquals('contract law', $context);
        $this->assertEquals('default', $missing);
    }

    public function test_mark_completed(): void
    {
        // Arrange
        $collaboration = AgentCollaboration::create([
            'orchestrator' => 'test-orchestrator',
            'problem_type' => 'analysis',
            'problem_statement' => 'Test problem',
            'status' => 'in_progress',
            'started_at' => now()->subMinutes(10),
        ]);

        $finalResult = [
            'conclusion' => 'Contract is compliant',
            'confidence' => 0.95,
        ];

        // Act
        $collaboration->markCompleted($finalResult, 'Analysis complete with high confidence');

        // Assert
        $collaboration->refresh();
        $this->assertEquals('completed', $collaboration->status);
        $this->assertEquals($finalResult, $collaboration->final_result);
        $this->assertEquals('Analysis complete with high confidence', $collaboration->synthesis);
        $this->assertNotNull($collaboration->completed_at);
        $this->assertGreaterThan(0, $collaboration->duration_seconds);
    }

    public function test_mark_failed(): void
    {
        // Arrange
        $collaboration = AgentCollaboration::create([
            'orchestrator' => 'test-orchestrator',
            'problem_type' => 'analysis',
            'problem_statement' => 'Test problem',
            'status' => 'in_progress',
            'started_at' => now()->subMinutes(5),
        ]);

        // Act
        $collaboration->markFailed('API timeout exceeded');

        // Assert
        $collaboration->refresh();
        $this->assertEquals('failed', $collaboration->status);
        $this->assertStringContainsString('API timeout exceeded', $collaboration->synthesis);
        $this->assertNotNull($collaboration->completed_at);
        $this->assertGreaterThan(0, $collaboration->duration_seconds);
    }

    public function test_increment_step(): void
    {
        // Arrange
        $collaboration = AgentCollaboration::create([
            'orchestrator' => 'test-orchestrator',
            'problem_type' => 'analysis',
            'problem_statement' => 'Test problem',
            'status' => 'in_progress',
            'total_steps' => 5,
            'completed_steps' => 2,
        ]);

        // Act
        $collaboration->incrementStep();
        $collaboration->incrementStep();

        // Assert
        $collaboration->refresh();
        $this->assertEquals(4, $collaboration->completed_steps);
    }

    public function test_add_tokens_used(): void
    {
        // Arrange
        $collaboration = AgentCollaboration::create([
            'orchestrator' => 'test-orchestrator',
            'problem_type' => 'analysis',
            'problem_statement' => 'Test problem',
            'status' => 'in_progress',
            'tokens_used' => 0,
            'cost_spent' => 0,
        ]);

        // Act
        $collaboration->addTokensUsed(10000);
        $collaboration->addTokensUsed(5000);

        // Assert
        $collaboration->refresh();
        $this->assertEquals(15000, $collaboration->tokens_used);
        $this->assertGreaterThan(0, (float) $collaboration->cost_spent);
    }

    public function test_get_progress_attribute(): void
    {
        // Arrange
        $collaboration = AgentCollaboration::create([
            'orchestrator' => 'test-orchestrator',
            'problem_type' => 'analysis',
            'problem_statement' => 'Test problem',
            'status' => 'in_progress',
            'total_steps' => 10,
            'completed_steps' => 7,
        ]);

        // Act
        $progress = $collaboration->progress;

        // Assert
        $this->assertEquals(70.0, $progress);
    }

    public function test_get_progress_attribute_with_zero_total_steps(): void
    {
        // Arrange
        $collaboration = AgentCollaboration::create([
            'orchestrator' => 'test-orchestrator',
            'problem_type' => 'analysis',
            'problem_statement' => 'Test problem',
            'status' => 'in_progress',
            'total_steps' => 0,
            'completed_steps' => 0,
        ]);

        // Act
        $progress = $collaboration->progress;

        // Assert
        $this->assertEquals(0, $progress);
    }

    public function test_stores_complex_execution_plan(): void
    {
        // Arrange & Act
        $executionPlan = [
            ['agent' => 'research-agent', 'task' => 'Find relevant cases'],
            ['agent' => 'analysis-agent', 'task' => 'Analyze findings'],
            ['agent' => 'synthesis-agent', 'task' => 'Generate summary'],
        ];

        $collaboration = AgentCollaboration::create([
            'orchestrator' => 'test-orchestrator',
            'problem_type' => 'analysis',
            'problem_statement' => 'Test problem',
            'status' => 'pending',
            'execution_plan' => $executionPlan,
        ]);

        // Assert
        $this->assertIsArray($collaboration->execution_plan);
        $this->assertCount(3, $collaboration->execution_plan);
        $this->assertEquals('research-agent', $collaboration->execution_plan[0]['agent']);
    }

    public function test_stores_agent_outputs(): void
    {
        // Arrange & Act
        $agentOutputs = [
            'research-agent' => [
                'cases_found' => 15,
                'relevant_cases' => ['case1', 'case2'],
            ],
            'analysis-agent' => [
                'analysis_complete' => true,
                'confidence' => 0.92,
            ],
        ];

        $collaboration = AgentCollaboration::create([
            'orchestrator' => 'test-orchestrator',
            'problem_type' => 'analysis',
            'problem_statement' => 'Test problem',
            'status' => 'in_progress',
            'agent_outputs' => $agentOutputs,
        ]);

        // Assert
        $this->assertIsArray($collaboration->agent_outputs);
        $this->assertEquals(15, $collaboration->agent_outputs['research-agent']['cases_found']);
        $this->assertEquals(0.92, $collaboration->agent_outputs['analysis-agent']['confidence']);
    }

    public function test_handles_null_optional_fields(): void
    {
        // Arrange & Act
        $collaboration = AgentCollaboration::create([
            'orchestrator' => 'test-orchestrator',
            'problem_type' => 'analysis',
            'problem_statement' => 'Test problem',
            'status' => 'pending',
            'context' => null,
            'agents_involved' => null,
            'execution_plan' => null,
            'shared_memory' => null,
            'agent_outputs' => null,
            'final_result' => null,
            'synthesis' => null,
        ]);

        // Assert
        $this->assertNull($collaboration->context);
        $this->assertNull($collaboration->agents_involved);
        $this->assertNull($collaboration->execution_plan);
        $this->assertNull($collaboration->shared_memory);
        $this->assertNull($collaboration->agent_outputs);
        $this->assertNull($collaboration->final_result);
        $this->assertNull($collaboration->synthesis);
    }
}

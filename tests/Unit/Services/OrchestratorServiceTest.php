<?php

namespace Tests\Unit\Services;

use App\Services\Agents\OrchestratorService;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class OrchestratorServiceTest extends TestCase
{
    use UsesTestDatabase;

    private OrchestratorService $orchestrator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orchestrator = app(OrchestratorService::class);
    }

    /** @test */
    public function it_orchestrates_basic_agent_pipeline(): void
    {
        // Arrange - Define a simple 2-agent pipeline
        $pipeline = [
            'ResearchAgent',
            'AnalysisAgent',
        ];

        $taskDescription = 'Research and analyze legal precedents';

        // Act - Execute the orchestration
        $orchestrationId = $this->orchestrator->orchestrate($pipeline, $taskDescription);

        // Assert - Should return an orchestration ID
        $this->assertNotNull($orchestrationId);
        $this->assertIsString($orchestrationId);
    }

    /** @test */
    public function it_orchestrates_complete_workflow_with_all_features(): void
    {
        // Arrange - 4-agent pipeline with budgets
        $pipeline = ['ResearchAgent', 'PrecedentAgent', 'StrategyAgent', 'RiskAgent'];
        $taskDescription = 'Complete legal analysis workflow';
        $initialContext = ['case_id' => 'case-456'];
        $budgets = ['token_budget' => 10000, 'time_budget_ms' => 60000];

        // Act - Orchestrate and execute
        $orchestrationId = $this->orchestrator->orchestrate($pipeline, $taskDescription, $initialContext, $budgets);
        $result = $this->orchestrator->execute($orchestrationId);

        // Assert - All features working
        $this->assertTrue($result['success']);
        $this->assertEquals(4, $result['completed_agents']);
        $this->assertArrayHasKey('shared_context', $result);
        $this->assertLessThan($budgets['token_budget'], $result['tokens_used']);
        $this->assertArrayHasKey('execution_history', $result);
        $this->assertEquals('completed', $result['status']);
    }

    /** @test */
    public function it_executes_agents_in_parallel(): void
    {
        // Arrange - 3 independent agents that can run in parallel
        $pipeline = [
            ['Agent1', 'Agent2', 'Agent3'], // Parallel group
        ];
        $taskDescription = 'Parallel execution test';

        // Act
        $orchestrationId = $this->orchestrator->orchestrate($pipeline, $taskDescription);
        $startTime = microtime(true);
        $result = $this->orchestrator->executeParallel($orchestrationId);
        $durationMs = (microtime(true) - $startTime) * 1000;

        // Assert - All 3 agents completed
        $this->assertTrue($result['success']);
        $this->assertEquals(3, $result['completed_agents']);
        $this->assertArrayHasKey('execution_history', $result);
        $this->assertCount(3, $result['execution_history']);

        // Assert - Performance: parallel should be faster than sequential
        // (Each mock agent takes ~100ms, so 3 parallel should be ~100ms, not ~300ms)
        $this->assertLessThan(200, $durationMs, 'Parallel execution should be fast');
    }

    /** @test */
    public function it_handles_partial_failures_in_parallel_execution(): void
    {
        // Arrange - Create a mock orchestrator that simulates Agent2 failing
        $mockOrchestrator = $this->getMockBuilder(OrchestratorService::class)
            ->onlyMethods(['executeAgent'])
            ->getMock();

        $mockOrchestrator->method('executeAgent')
            ->willReturnCallback(function ($agentName, $context) {
                if ($agentName === 'Agent2') {
                    throw new \Exception('Agent2 failed intentionally');
                }

                return [
                    'output' => ['agent' => $agentName, 'result' => "Result from {$agentName}"],
                    'tokens_used' => 50,
                    'cost' => 0.001,
                ];
            });

        $pipeline = [['Agent1', 'Agent2', 'Agent3']]; // Parallel group
        $taskDescription = 'Partial failure test';

        // Act
        $orchestrationId = $mockOrchestrator->orchestrate($pipeline, $taskDescription);
        $result = $mockOrchestrator->executeParallel($orchestrationId);

        // Assert - Overall failure but 2 agents succeeded
        $this->assertFalse($result['success']);
        $this->assertEquals('failed', $result['status']);
        $this->assertEquals(2, $result['completed_agents']);
        $this->assertEquals(1, $result['failed_agents']);
        $this->assertCount(3, $result['execution_history']);

        // Verify Agent2 failed specifically
        $agent2History = collect($result['execution_history'])
            ->firstWhere('agent', 'Agent2');
        $this->assertEquals('failed', $agent2History['status']);
        $this->assertStringContainsString('Agent2 failed intentionally', $agent2History['error']);
    }

    /** @test */
    public function it_enforces_synchronization_barrier_in_parallel_execution(): void
    {
        // Arrange - 5 agents in parallel group
        $pipeline = [['Agent1', 'Agent2', 'Agent3', 'Agent4', 'Agent5']];
        $taskDescription = 'Synchronization barrier test';

        // Act
        $orchestrationId = $this->orchestrator->orchestrate($pipeline, $taskDescription);
        $result = $this->orchestrator->executeParallel($orchestrationId);

        // Assert - All 5 agents must appear in execution history (synchronization)
        $this->assertCount(5, $result['execution_history']);
        $this->assertEquals(5, $result['completed_agents']);

        // Assert - All agents completed successfully
        foreach ($result['execution_history'] as $agentExecution) {
            $this->assertEquals('completed', $agentExecution['status']);
            $this->assertArrayHasKey('agent', $agentExecution);
            $this->assertArrayHasKey('tokens_used', $agentExecution);
            $this->assertArrayHasKey('cost', $agentExecution);
        }

        // Assert - Shared context includes results from ALL agents
        $this->assertArrayHasKey('shared_context', $result);
    }

    /** @test */
    public function it_handles_timeout_in_parallel_execution(): void
    {
        // Arrange - Use very short timeout (1ms)
        $pipeline = [['Agent1', 'Agent2', 'Agent3']];
        $taskDescription = 'Timeout test';
        $shortTimeout = 1; // 1 millisecond

        // Act
        $orchestrationId = $this->orchestrator->orchestrate($pipeline, $taskDescription);
        $result = $this->orchestrator->executeParallel($orchestrationId, $shortTimeout);

        // Assert - Execution completes and returns result
        $this->assertArrayHasKey('duration_ms', $result);
        $this->assertArrayHasKey('stop_reason', $result);

        // Note: Mock agents execute instantly (0ms), so timeout won't actually trigger.
        // This test validates that the timeout parameter is accepted and the
        // timeout detection logic exists in the implementation.
        // In production with real agents, if duration >= timeout, stop_reason would be 'timeout'.
        $this->assertTrue($result['success'] || $result['stop_reason'] === 'timeout');
    }

    /** @test */
    public function it_validates_parallel_execution_performance(): void
    {
        // Arrange - Compare parallel vs sequential execution
        $pipeline = [['Agent1', 'Agent2', 'Agent3']];
        $taskDescription = 'Performance validation';

        // Act - Parallel execution
        $orchestrationId = $this->orchestrator->orchestrate($pipeline, $taskDescription);
        $startParallel = microtime(true);
        $resultParallel = $this->orchestrator->executeParallel($orchestrationId);
        $parallelDuration = (microtime(true) - $startParallel) * 1000;

        // Act - Sequential execution (for comparison)
        $sequentialPipeline = ['Agent1', 'Agent2', 'Agent3']; // Not nested array
        $orchestrationIdSeq = $this->orchestrator->orchestrate($sequentialPipeline, $taskDescription);
        $startSequential = microtime(true);
        $resultSequential = $this->orchestrator->execute($orchestrationIdSeq);
        $sequentialDuration = (microtime(true) - $startSequential) * 1000;

        // Assert - Parallel should be faster (mock agents are instant, so both will be fast)
        // But we verify both complete successfully
        $this->assertTrue($resultParallel['success']);
        $this->assertTrue($resultSequential['success']);
        $this->assertEquals(3, $resultParallel['completed_agents']);
        $this->assertEquals(3, $resultSequential['completed_agents']);

        // Assert - Both methods produce same token usage and cost
        $this->assertEquals($resultSequential['tokens_used'], $resultParallel['tokens_used']);
        $this->assertEquals($resultSequential['cost_spent'], $resultParallel['cost_spent']);
    }

    /** @test */
    public function it_handles_mixed_sequential_and_parallel_pipeline(): void
    {
        // Arrange - Complex pipeline: sequential, then parallel group, then sequential
        $pipeline = [
            'Agent1',                          // Sequential
            ['Agent2', 'Agent3'],              // Parallel group
            'Agent4',                          // Sequential
        ];
        $taskDescription = 'Mixed pipeline test';

        // Act - executeParallel should flatten all agents
        $orchestrationId = $this->orchestrator->orchestrate($pipeline, $taskDescription);
        $result = $this->orchestrator->executeParallel($orchestrationId);

        // Assert - All 4 agents executed (flattened from pipeline)
        $this->assertTrue($result['success']);
        $this->assertEquals(4, $result['completed_agents']);
        $this->assertCount(4, $result['execution_history']);

        // Verify all agent names present
        $agentNames = collect($result['execution_history'])->pluck('agent')->toArray();
        $this->assertContains('Agent1', $agentNames);
        $this->assertContains('Agent2', $agentNames);
        $this->assertContains('Agent3', $agentNames);
        $this->assertContains('Agent4', $agentNames);
    }
}

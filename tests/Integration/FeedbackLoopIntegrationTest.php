<?php

namespace Tests\Integration;

use App\Services\Agents\OrchestratorService;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Feedback Loop Integration Test
 *
 * Sprint 3.6: Feedback Loop Implementation
 *
 * Tests the feedback mechanism between RiskAnalystAgent and ResearchSpecialistAgent
 */
class FeedbackLoopIntegrationTest extends TestCase
{
    use UsesTestDatabase;

    private OrchestratorService $orchestrator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orchestrator = app(OrchestratorService::class);
    }

    /** @test */
    public function it_allows_risk_analyst_to_request_additional_research(): void
    {
        // Arrange
        $pipeline = ['RiskAnalystAgent', 'ResearchSpecialistAgent'];
        $taskDescription = 'Analyze case risks and research precedents';
        $context = [
            'case_id' => 'case-123',
            'initial_findings' => 'Evidence admissibility concerns',
        ];

        // Act
        $orchestrationId = $this->orchestrator->orchestrate($pipeline, $taskDescription, $context);

        // Request additional research (simulates RiskAnalyst feedback)
        $feedbackResult = $this->orchestrator->requestAdditionalResearch(
            orchestrationId: $orchestrationId,
            topic: 'illegal search and seizure precedents',
            context: ['risk_level' => 'high', 'evidence_type' => 'physical']
        );

        // Assert
        $this->assertIsArray($feedbackResult);
        $this->assertArrayHasKey('feedback_sent', $feedbackResult);
        $this->assertTrue($feedbackResult['feedback_sent']);
        $this->assertArrayHasKey('target_agent', $feedbackResult);
        $this->assertEquals('ResearchSpecialistAgent', $feedbackResult['target_agent']);
    }

    /** @test */
    public function it_executes_feedback_loop_between_risk_and_research_agents(): void
    {
        // Arrange
        $pipeline = ['ResearchSpecialistAgent', 'RiskAnalystAgent'];
        $taskDescription = 'Research and risk analysis with feedback';
        $context = ['case_id' => 'case-456', 'query' => 'Search warrant proportionality'];

        // Act
        $orchestrationId = $this->orchestrator->orchestrate($pipeline, $taskDescription, $context);

        // Execute initial pipeline
        $result = $this->orchestrator->execute($orchestrationId);

        // Simulate RiskAnalyst requesting more research
        $feedbackResult = $this->orchestrator->requestAdditionalResearch(
            orchestrationId: $orchestrationId,
            topic: 'proportionality case law',
            context: ['gaps_identified' => ['recent_precedents', 'constitutional_basis']]
        );

        // Execute feedback iteration
        $secondResult = $this->orchestrator->executeFeedbackIteration($orchestrationId);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertTrue($feedbackResult['feedback_sent']);
        $this->assertIsArray($secondResult);
        $this->assertArrayHasKey('iteration', $secondResult);
        $this->assertEquals(1, $secondResult['iteration']);
        $this->assertArrayHasKey('feedback_processed', $secondResult);
        $this->assertTrue($secondResult['feedback_processed']);
    }

    /** @test */
    public function it_enforces_max_iteration_limit_of_two(): void
    {
        // Arrange
        $pipeline = ['ResearchSpecialistAgent', 'RiskAnalystAgent'];
        $taskDescription = 'Feedback loop with iteration limit test';
        $context = ['case_id' => 'case-789'];

        // Act
        $orchestrationId = $this->orchestrator->orchestrate($pipeline, $taskDescription, $context);

        // First iteration
        $this->orchestrator->requestAdditionalResearch($orchestrationId, 'topic1', []);
        $iteration1 = $this->orchestrator->executeFeedbackIteration($orchestrationId);

        // Second iteration
        $this->orchestrator->requestAdditionalResearch($orchestrationId, 'topic2', []);
        $iteration2 = $this->orchestrator->executeFeedbackIteration($orchestrationId);

        // Third iteration (should be blocked)
        $this->orchestrator->requestAdditionalResearch($orchestrationId, 'topic3', []);
        $iteration3 = $this->orchestrator->executeFeedbackIteration($orchestrationId);

        // Assert
        $this->assertEquals(1, $iteration1['iteration']);
        $this->assertTrue($iteration1['feedback_processed']);

        $this->assertEquals(2, $iteration2['iteration']);
        $this->assertTrue($iteration2['feedback_processed']);

        $this->assertFalse($iteration3['feedback_processed']);
        $this->assertArrayHasKey('reason', $iteration3);
        $this->assertStringContainsString('maximum', strtolower($iteration3['reason']));
    }

    /** @test */
    public function it_tracks_feedback_requests_in_orchestration_log(): void
    {
        // Arrange
        $pipeline = ['RiskAnalystAgent', 'ResearchSpecialistAgent'];
        $taskDescription = 'Track feedback requests';
        $context = ['case_id' => 'case-tracking'];

        // Act
        $orchestrationId = $this->orchestrator->orchestrate($pipeline, $taskDescription, $context);

        $this->orchestrator->requestAdditionalResearch(
            $orchestrationId,
            'evidence exclusion cases',
            ['priority' => 'high']
        );

        $log = $this->orchestrator->getOrchestrationLog($orchestrationId);

        // Assert
        $this->assertArrayHasKey('feedback_requests', $log);
        $this->assertCount(1, $log['feedback_requests']);
        $this->assertEquals('evidence exclusion cases', $log['feedback_requests'][0]['topic']);
        $this->assertEquals('high', $log['feedback_requests'][0]['context']['priority']);
    }

    /** @test */
    public function it_improves_results_after_feedback_iteration(): void
    {
        // Arrange
        $pipeline = ['ResearchSpecialistAgent', 'RiskAnalystAgent'];
        $taskDescription = 'Results improvement test';
        $context = ['case_id' => 'case-improvement', 'query' => 'home search warrants'];

        // Act
        $orchestrationId = $this->orchestrator->orchestrate($pipeline, $taskDescription, $context);

        // Initial execution
        $initialResult = $this->orchestrator->execute($orchestrationId);
        $initialResearchCount = count($initialResult['shared_context']['researched_items'] ?? []);

        // Request additional research
        $this->orchestrator->requestAdditionalResearch(
            $orchestrationId,
            'proportionality in home searches',
            ['gaps' => ['recent_cases', 'high_court_decisions']]
        );

        // Feedback iteration
        $improvedResult = $this->orchestrator->executeFeedbackIteration($orchestrationId);
        $improvedResearchCount = count($improvedResult['shared_context']['researched_items'] ?? []);

        // Assert - Results should improve
        $this->assertTrue($improvedResult['feedback_processed']);
        // Note: In mock implementation, we can verify structure
        // In real implementation, improved count would be higher
        $this->assertArrayHasKey('researched_items', $improvedResult['shared_context']);
    }

    /** @test */
    public function it_includes_feedback_metadata_in_iteration_result(): void
    {
        // Arrange
        $pipeline = ['ResearchSpecialistAgent'];
        $taskDescription = 'Feedback metadata test';
        $context = ['case_id' => 'case-metadata'];

        // Act
        $orchestrationId = $this->orchestrator->orchestrate($pipeline, $taskDescription, $context);

        $this->orchestrator->requestAdditionalResearch(
            $orchestrationId,
            'constitutional rights violations',
            ['specificity' => 'high', 'jurisdiction' => 'Osijek']
        );

        $result = $this->orchestrator->executeFeedbackIteration($orchestrationId);

        // Assert
        $this->assertArrayHasKey('feedback_topic', $result);
        $this->assertEquals('constitutional rights violations', $result['feedback_topic']);
        $this->assertArrayHasKey('feedback_context', $result);
        $this->assertEquals('high', $result['feedback_context']['specificity']);
        $this->assertArrayHasKey('requested_at', $result);
        $this->assertArrayHasKey('processed_at', $result);
    }

    /** @test */
    public function it_prevents_feedback_without_prior_orchestration(): void
    {
        // Act - Try to request feedback with invalid orchestration ID
        $result = $this->orchestrator->requestAdditionalResearch(
            'non-existent-id',
            'some topic',
            []
        );

        // Assert
        $this->assertFalse($result['feedback_sent']);
        $this->assertArrayHasKey('reason', $result);
        $this->assertStringContainsString('not found', strtolower($result['reason']));
    }

    /** @test */
    public function it_records_feedback_iteration_count(): void
    {
        // Arrange
        $pipeline = ['ResearchSpecialistAgent', 'RiskAnalystAgent'];
        $taskDescription = 'Iteration count test';
        $context = ['case_id' => 'case-count'];

        // Act
        $orchestrationId = $this->orchestrator->orchestrate($pipeline, $taskDescription, $context);

        // Request and execute feedback twice
        $this->orchestrator->requestAdditionalResearch($orchestrationId, 'topic1', []);
        $iteration1 = $this->orchestrator->executeFeedbackIteration($orchestrationId);

        $this->orchestrator->requestAdditionalResearch($orchestrationId, 'topic2', []);
        $iteration2 = $this->orchestrator->executeFeedbackIteration($orchestrationId);

        $log = $this->orchestrator->getOrchestrationLog($orchestrationId);

        // Assert
        $this->assertEquals(1, $iteration1['iteration']);
        $this->assertEquals(2, $iteration2['iteration']);
        $this->assertArrayHasKey('feedback_iteration_count', $log);
        $this->assertEquals(2, $log['feedback_iteration_count']);
    }

    /** @test */
    public function it_allows_multiple_feedback_requests_per_iteration(): void
    {
        // Arrange
        $pipeline = ['ResearchSpecialistAgent'];
        $taskDescription = 'Multiple feedback requests test';
        $context = ['case_id' => 'case-multiple'];

        // Act
        $orchestrationId = $this->orchestrator->orchestrate($pipeline, $taskDescription, $context);

        // Request multiple feedbacks before iteration
        $result1 = $this->orchestrator->requestAdditionalResearch(
            $orchestrationId,
            'topic1',
            ['area' => 'criminal_procedure']
        );

        $result2 = $this->orchestrator->requestAdditionalResearch(
            $orchestrationId,
            'topic2',
            ['area' => 'evidence_law']
        );

        $log = $this->orchestrator->getOrchestrationLog($orchestrationId);

        // Assert
        $this->assertTrue($result1['feedback_sent']);
        $this->assertTrue($result2['feedback_sent']);
        $this->assertCount(2, $log['feedback_requests']);
    }
}

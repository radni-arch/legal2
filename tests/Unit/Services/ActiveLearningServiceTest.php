<?php

namespace Tests\Unit\Services;

use App\Models\LearningOpportunity;
use App\Services\ActiveLearningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TDD Tests for ActiveLearningService
 *
 * Sprint 5.1: Learning Opportunity Detection
 *
 * Tests the service that identifies and stores learning opportunities
 * from low-confidence AI outputs.
 */
class ActiveLearningServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ActiveLearningService $service;

    protected function setUp(): void
    {
        // Set AWS environment variables for TextractService
        putenv('AWS_DEFAULT_REGION=us-east-1');
        putenv('AWS_ACCESS_KEY_ID=test-key');
        putenv('AWS_SECRET_ACCESS_KEY=test-secret');
        putenv('AWS_BUCKET=test-bucket');

        parent::setUp();

        $this->service = app(ActiveLearningService::class);
    }

    /** @test */
    public function it_creates_learning_opportunity_for_low_confidence_output()
    {
        $result = $this->service->identifyLearningOpportunity(
            opportunityType: 'decision_discovery',
            sourceType: 'court_decision',
            sourceId: 123,
            aiOutput: ['analysis' => 'This case seems relevant'],
            confidence: 0.45
        );

        $this->assertTrue($result['flagged']);
        $this->assertArrayHasKey('opportunity_id', $result);

        $this->assertDatabaseHas('learning_opportunities', [
            'opportunity_type' => 'decision_discovery',
            'source_type' => 'court_decision',
            'source_id' => 123,
            'confidence_score' => 0.45,
            'status' => 'pending',
        ]);
    }

    /** @test */
    public function it_does_not_flag_high_confidence_output()
    {
        $result = $this->service->identifyLearningOpportunity(
            opportunityType: 'decision_discovery',
            sourceType: 'court_decision',
            sourceId: 456,
            aiOutput: ['analysis' => 'This case is highly relevant'],
            confidence: 0.85
        );

        $this->assertFalse($result['flagged']);
        $this->assertNull($result['opportunity_id']);

        $this->assertDatabaseMissing('learning_opportunities', [
            'source_type' => 'court_decision',
            'source_id' => 456,
        ]);
    }

    /** @test */
    public function it_flags_outputs_below_default_threshold_of_0_6()
    {
        // Just below threshold
        $result1 = $this->service->identifyLearningOpportunity(
            opportunityType: 'precedent_analysis',
            sourceType: 'applicability_check',
            sourceId: 789,
            aiOutput: ['applicable' => false],
            confidence: 0.59
        );

        $this->assertTrue($result1['flagged']);

        // Just above threshold
        $result2 = $this->service->identifyLearningOpportunity(
            opportunityType: 'precedent_analysis',
            sourceType: 'applicability_check',
            sourceId: 790,
            aiOutput: ['applicable' => true],
            confidence: 0.60
        );

        $this->assertFalse($result2['flagged']);
    }

    /** @test */
    public function it_accepts_custom_confidence_threshold()
    {
        $result = $this->service->identifyLearningOpportunity(
            opportunityType: 'decision_discovery',
            sourceType: 'court_decision',
            sourceId: 999,
            aiOutput: ['analysis' => 'Test'],
            confidence: 0.72,
            threshold: 0.75  // Custom threshold
        );

        $this->assertTrue($result['flagged']);

        $this->assertDatabaseHas('learning_opportunities', [
            'source_id' => 999,
            'confidence_score' => 0.72,
        ]);
    }

    /** @test */
    public function it_stores_uncertainty_reason_automatically()
    {
        $result = $this->service->identifyLearningOpportunity(
            opportunityType: 'decision_discovery',
            sourceType: 'court_decision',
            sourceId: 111,
            aiOutput: ['analysis' => 'Uncertain analysis'],
            confidence: 0.45
        );

        $opportunity = LearningOpportunity::find($result['opportunity_id']);

        $this->assertNotNull($opportunity->uncertainty_reason);
        $this->assertStringContainsString('0.45', $opportunity->uncertainty_reason);
    }

    /** @test */
    public function it_accepts_custom_uncertainty_reason()
    {
        $customReason = 'Ambiguous legal precedent with conflicting interpretations';

        $result = $this->service->identifyLearningOpportunity(
            opportunityType: 'precedent_analysis',
            sourceType: 'applicability_check',
            sourceId: 222,
            aiOutput: ['applicable' => true],
            confidence: 0.50,
            uncertaintyReason: $customReason
        );

        $opportunity = LearningOpportunity::find($result['opportunity_id']);

        $this->assertEquals($customReason, $opportunity->uncertainty_reason);
    }

    /** @test */
    public function it_returns_opportunity_id_when_flagged()
    {
        $result = $this->service->identifyLearningOpportunity(
            opportunityType: 'decision_discovery',
            sourceType: 'court_decision',
            sourceId: 333,
            aiOutput: ['test' => 'data'],
            confidence: 0.40
        );

        $this->assertArrayHasKey('opportunity_id', $result);
        $this->assertIsInt($result['opportunity_id']);

        $opportunity = LearningOpportunity::find($result['opportunity_id']);
        $this->assertNotNull($opportunity);
    }

    /** @test */
    public function it_stores_complete_ai_output()
    {
        $aiOutput = [
            'analysis' => 'Complex legal analysis',
            'score' => 0.45,
            'reasoning' => ['point1', 'point2', 'point3'],
            'metadata' => ['source' => 'court_db'],
        ];

        $result = $this->service->identifyLearningOpportunity(
            opportunityType: 'decision_discovery',
            sourceType: 'court_decision',
            sourceId: 444,
            aiOutput: $aiOutput,
            confidence: 0.45
        );

        $opportunity = LearningOpportunity::find($result['opportunity_id']);

        $this->assertEquals($aiOutput, $opportunity->ai_output);
        $this->assertEquals('Complex legal analysis', $opportunity->ai_output['analysis']);
        $this->assertIsArray($opportunity->ai_output['reasoning']);
    }

    /** @test */
    public function it_handles_zero_confidence_gracefully()
    {
        $result = $this->service->identifyLearningOpportunity(
            opportunityType: 'decision_discovery',
            sourceType: 'court_decision',
            sourceId: 555,
            aiOutput: ['analysis' => 'No confidence'],
            confidence: 0.0
        );

        $this->assertTrue($result['flagged']);

        $this->assertDatabaseHas('learning_opportunities', [
            'source_id' => 555,
            'confidence_score' => 0.0,
        ]);
    }

    /** @test */
    public function it_handles_perfect_confidence_gracefully()
    {
        $result = $this->service->identifyLearningOpportunity(
            opportunityType: 'decision_discovery',
            sourceType: 'court_decision',
            sourceId: 666,
            aiOutput: ['analysis' => 'Perfect confidence'],
            confidence: 1.0
        );

        $this->assertFalse($result['flagged']);

        $this->assertDatabaseMissing('learning_opportunities', [
            'source_id' => 666,
        ]);
    }

    /** @test */
    public function it_counts_pending_opportunities()
    {
        // Create some opportunities
        $this->service->identifyLearningOpportunity(
            opportunityType: 'decision_discovery',
            sourceType: 'court_decision',
            sourceId: 1,
            aiOutput: ['test' => 'data'],
            confidence: 0.40
        );

        $this->service->identifyLearningOpportunity(
            opportunityType: 'decision_discovery',
            sourceType: 'court_decision',
            sourceId: 2,
            aiOutput: ['test' => 'data'],
            confidence: 0.50
        );

        // Mark one as reviewed
        LearningOpportunity::where('source_id', 1)->first()->update([
            'status' => 'reviewed',
        ]);

        $count = $this->service->getPendingCount();

        $this->assertEquals(1, $count);
    }

    /** @test */
    public function it_gets_pending_opportunities_by_type()
    {
        $this->service->identifyLearningOpportunity(
            opportunityType: 'decision_discovery',
            sourceType: 'court_decision',
            sourceId: 1,
            aiOutput: ['test' => 'data'],
            confidence: 0.40
        );

        $this->service->identifyLearningOpportunity(
            opportunityType: 'precedent_analysis',
            sourceType: 'applicability_check',
            sourceId: 2,
            aiOutput: ['test' => 'data'],
            confidence: 0.50
        );

        $this->service->identifyLearningOpportunity(
            opportunityType: 'decision_discovery',
            sourceType: 'court_decision',
            sourceId: 3,
            aiOutput: ['test' => 'data'],
            confidence: 0.45
        );

        $decisionOpps = $this->service->getPendingByType('decision_discovery');
        $precedentOpps = $this->service->getPendingByType('precedent_analysis');

        $this->assertCount(2, $decisionOpps);
        $this->assertCount(1, $precedentOpps);
    }

    /** @test */
    public function it_prevents_duplicate_opportunities_for_same_source()
    {
        // Create first opportunity
        $result1 = $this->service->identifyLearningOpportunity(
            opportunityType: 'decision_discovery',
            sourceType: 'court_decision',
            sourceId: 777,
            aiOutput: ['analysis' => 'First analysis'],
            confidence: 0.40
        );

        $this->assertTrue($result1['flagged']);

        // Try to create duplicate
        $result2 = $this->service->identifyLearningOpportunity(
            opportunityType: 'decision_discovery',
            sourceType: 'court_decision',
            sourceId: 777,
            aiOutput: ['analysis' => 'Second analysis'],
            confidence: 0.45
        );

        $this->assertFalse($result2['flagged']);
        $this->assertEquals('duplicate', $result2['reason']);

        // Should only have one record
        $count = LearningOpportunity::where('source_id', 777)->count();
        $this->assertEquals(1, $count);
    }
}

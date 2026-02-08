<?php

namespace Tests\Integration;

use App\Models\LearningOpportunity;
use App\Models\User;
use App\Services\ActiveLearningService;
use App\Services\CourtDecisionVectorStoreService;

/**
 * TDD Integration Tests for Feedback Incorporation Pipeline
 *
 * Sprint 5.3: Feedback Incorporation Pipeline
 *
 * Tests the complete pipeline from human feedback to model improvement.
 *
 * NOTE: External dependencies (OpenAI, DecisionSearch) are mocked via IntegrationTestCase
 *
 * Following strict TDD: Tests written FIRST, will fail until implementation.
 */
class FeedbackIncorporationPipelineTest extends IntegrationTestCase
{
    protected ActiveLearningService $learningService;

    protected CourtDecisionVectorStoreService $vectorStore;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // External services (OpenAI, DecisionSearch) are already mocked by IntegrationTestCase

        $this->learningService = app(ActiveLearningService::class);
        $this->vectorStore = app(CourtDecisionVectorStoreService::class);
        $this->user = User::factory()->create();
    }

    /** @test */
    public function it_incorporates_feedback_successfully()
    {
        // Create a learning opportunity
        $opportunity = LearningOpportunity::create([
            'opportunity_type' => 'decision_discovery',
            'source_type' => 'decision_score',
            'source_id' => 12345,
            'ai_output' => [
                'id' => 'dec-test-001',
                'score' => 45,
                'reasoning' => 'Low relevance detected',
                'topic' => 'contract law',
            ],
            'confidence_score' => 0.45,
            'status' => 'reviewed',
            'reviewed_by' => $this->user->id,
            'reviewed_at' => now(),
            'human_label' => [
                'correct_score' => 85,
                'reasoning' => 'Actually very relevant - attorney confirmed',
                'decision' => 'should_ingest',
            ],
        ]);

        // Incorporate feedback
        $result = $this->learningService->incorporateFeedback(
            $opportunity->id,
            $opportunity->human_label
        );

        // Verify success
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('vector_store_added', $result);
        $this->assertArrayHasKey('graph_updated', $result);
        $this->assertArrayHasKey('similar_items_rescored', $result);

        // Verify opportunity marked as incorporated
        $opportunity->refresh();
        $this->assertNotNull($opportunity->incorporated_at);
    }

    /** @test */
    public function it_adds_corrected_output_to_vector_store_with_high_weight()
    {
        $opportunity = LearningOpportunity::create([
            'opportunity_type' => 'decision_discovery',
            'source_type' => 'decision_score',
            'source_id' => 67890,
            'ai_output' => [
                'id' => 'dec-test-002',
                'score' => 40,
                'reasoning' => 'Marginal relevance',
                'topic' => 'property law',
            ],
            'confidence_score' => 0.40,
            'status' => 'reviewed',
            'reviewed_by' => $this->user->id,
            'reviewed_at' => now(),
            'human_label' => [
                'correct_score' => 90,
                'reasoning' => 'Highly relevant precedent',
                'corrected_text' => 'This decision establishes key precedent for property disputes',
            ],
        ]);

        $result = $this->learningService->incorporateFeedback(
            $opportunity->id,
            $opportunity->human_label
        );

        // Verify vector store integration
        $this->assertTrue($result['vector_store_added']);
        $this->assertGreaterThanOrEqual(2.0, $result['weight_multiplier']); // Min 2.0x weight for corrected data
        $this->assertArrayHasKey('vector_id', $result);
    }

    /** @test */
    public function it_updates_graph_relationships_based_on_feedback()
    {
        $opportunity = LearningOpportunity::create([
            'opportunity_type' => 'precedent_analysis',
            'source_type' => 'applicability_check',
            'source_id' => 11111,
            'ai_output' => [
                'decision_id' => 'dec-test-003',
                'applicability_score' => 35,
                'binding_authority' => 'informative',
                'key_factors' => ['different jurisdiction'],
            ],
            'confidence_score' => 0.35,
            'status' => 'reviewed',
            'reviewed_by' => $this->user->id,
            'reviewed_at' => now(),
            'human_label' => [
                'correct_applicability' => 85,
                'binding_authority' => 'persuasive',
                'key_factors' => ['similar facts', 'strong precedent'],
                'relationships_to_add' => [
                    ['type' => 'SUPPORTS', 'target' => 'case-123'],
                    ['type' => 'CITES', 'target' => 'law-zkp-article-9'],
                ],
            ],
        ]);

        $result = $this->learningService->incorporateFeedback(
            $opportunity->id,
            $opportunity->human_label
        );

        // Verify graph updates
        $this->assertTrue($result['graph_updated']);
        $this->assertArrayHasKey('relationships_added', $result);
        $this->assertGreaterThanOrEqual(2, $result['relationships_added']);
    }

    /** @test */
    public function it_rescores_similar_items_based_on_feedback()
    {
        // Create opportunity with feedback
        $opportunity = LearningOpportunity::create([
            'opportunity_type' => 'decision_discovery',
            'source_type' => 'decision_score',
            'source_id' => 22222,
            'ai_output' => [
                'id' => 'dec-test-004',
                'score' => 50,
                'reasoning' => 'Uncertain relevance',
                'topic' => 'criminal procedure',
            ],
            'confidence_score' => 0.50,
            'status' => 'reviewed',
            'reviewed_by' => $this->user->id,
            'reviewed_at' => now(),
            'human_label' => [
                'correct_score' => 95,
                'reasoning' => 'Critical precedent',
            ],
        ]);

        // Create similar pending opportunities
        $similar1 = LearningOpportunity::create([
            'opportunity_type' => 'decision_discovery',
            'source_type' => 'decision_score',
            'source_id' => 33333,
            'ai_output' => [
                'id' => 'dec-test-005',
                'score' => 48,
                'reasoning' => 'Similar uncertain case',
                'topic' => 'criminal procedure',
            ],
            'confidence_score' => 0.48,
            'status' => 'pending',
        ]);

        $similar2 = LearningOpportunity::create([
            'opportunity_type' => 'decision_discovery',
            'source_type' => 'decision_score',
            'source_id' => 44444,
            'ai_output' => [
                'id' => 'dec-test-006',
                'score' => 52,
                'reasoning' => 'Related case',
                'topic' => 'criminal procedure',
            ],
            'confidence_score' => 0.52,
            'status' => 'pending',
        ]);

        $result = $this->learningService->incorporateFeedback(
            $opportunity->id,
            $opportunity->human_label
        );

        // Verify similar items were rescored
        $this->assertTrue($result['similar_items_rescored']);
        $this->assertArrayHasKey('items_rescored_count', $result);
        $this->assertGreaterThan(0, $result['items_rescored_count']);
        $this->assertArrayHasKey('rescored_items', $result);
    }

    /** @test */
    public function it_tracks_accuracy_improvement_before_and_after()
    {
        $opportunity = LearningOpportunity::create([
            'opportunity_type' => 'decision_discovery',
            'source_type' => 'decision_score',
            'source_id' => 55555,
            'ai_output' => [
                'id' => 'dec-test-007',
                'score' => 42,
                'reasoning' => 'Low confidence',
            ],
            'confidence_score' => 0.42,
            'status' => 'reviewed',
            'reviewed_by' => $this->user->id,
            'reviewed_at' => now(),
            'human_label' => [
                'correct_score' => 88,
                'reasoning' => 'High relevance confirmed',
            ],
        ]);

        $result = $this->learningService->incorporateFeedback(
            $opportunity->id,
            $opportunity->human_label
        );

        // Verify accuracy tracking
        $this->assertArrayHasKey('accuracy_metrics', $result);
        $this->assertArrayHasKey('before', $result['accuracy_metrics']);
        $this->assertArrayHasKey('after', $result['accuracy_metrics']);
        $this->assertArrayHasKey('improvement', $result['accuracy_metrics']);

        // Verify improvement calculated
        $metrics = $result['accuracy_metrics'];
        $this->assertEquals(0.42, $metrics['before']['ai_score']);
        $this->assertEquals(0.88, $metrics['before']['human_score']);
        $this->assertArrayHasKey('error', $metrics['before']);
        $this->assertGreaterThan(0, $metrics['improvement']);
    }

    /** @test */
    public function it_prevents_duplicate_incorporation()
    {
        $opportunity = LearningOpportunity::create([
            'opportunity_type' => 'decision_discovery',
            'source_type' => 'decision_score',
            'source_id' => 66666,
            'ai_output' => ['id' => 'dec-test-008', 'score' => 45],
            'confidence_score' => 0.45,
            'status' => 'reviewed',
            'reviewed_by' => $this->user->id,
            'reviewed_at' => now(),
            'human_label' => ['correct_score' => 80],
            'incorporated_at' => now(), // Already incorporated
        ]);

        $result = $this->learningService->incorporateFeedback(
            $opportunity->id,
            $opportunity->human_label
        );

        // Verify duplicate prevention
        $this->assertFalse($result['success']);
        $this->assertEquals('already_incorporated', $result['reason']);
    }

    /** @test */
    public function it_requires_reviewed_status_for_incorporation()
    {
        $opportunity = LearningOpportunity::create([
            'opportunity_type' => 'decision_discovery',
            'source_type' => 'decision_score',
            'source_id' => 77777,
            'ai_output' => ['id' => 'dec-test-009', 'score' => 45],
            'confidence_score' => 0.45,
            'status' => 'pending', // Not reviewed yet
        ]);

        $result = $this->learningService->incorporateFeedback(
            $opportunity->id,
            ['correct_score' => 80]
        );

        // Verify status check
        $this->assertFalse($result['success']);
        $this->assertEquals('not_reviewed', $result['reason']);
    }

    /** @test */
    public function it_handles_missing_opportunity_gracefully()
    {
        $result = $this->learningService->incorporateFeedback(
            999999, // Non-existent ID
            ['correct_score' => 80]
        );

        $this->assertFalse($result['success']);
        $this->assertEquals('opportunity_not_found', $result['reason']);
    }

    /** @test */
    public function it_calculates_weight_multiplier_based_on_confidence_gap()
    {
        // Large confidence gap (AI: 30, Human: 90) should get higher weight
        $opportunity1 = LearningOpportunity::create([
            'opportunity_type' => 'decision_discovery',
            'source_type' => 'decision_score',
            'source_id' => 88888,
            'ai_output' => ['id' => 'dec-test-010', 'score' => 30],
            'confidence_score' => 0.30,
            'status' => 'reviewed',
            'reviewed_by' => $this->user->id,
            'reviewed_at' => now(),
            'human_label' => ['correct_score' => 90],
        ]);

        $result1 = $this->learningService->incorporateFeedback(
            $opportunity1->id,
            $opportunity1->human_label
        );

        // Verify weight multiplier increases with confidence gap
        $this->assertArrayHasKey('weight_multiplier', $result1);
        $this->assertGreaterThanOrEqual(2.0, $result1['weight_multiplier']);

        // Smaller gap should get lower (but still elevated) weight
        $opportunity2 = LearningOpportunity::create([
            'opportunity_type' => 'decision_discovery',
            'source_type' => 'decision_score',
            'source_id' => 99999,
            'ai_output' => ['id' => 'dec-test-011', 'score' => 55],
            'confidence_score' => 0.55,
            'status' => 'reviewed',
            'reviewed_by' => $this->user->id,
            'reviewed_at' => now(),
            'human_label' => ['correct_score' => 65],
        ]);

        $result2 = $this->learningService->incorporateFeedback(
            $opportunity2->id,
            $opportunity2->human_label
        );

        $this->assertLessThan($result1['weight_multiplier'], $result2['weight_multiplier']);
    }

    /** @test */
    public function it_integrates_full_pipeline_end_to_end()
    {
        // Create opportunity
        $opportunity = LearningOpportunity::create([
            'opportunity_type' => 'decision_discovery',
            'source_type' => 'decision_score',
            'source_id' => 111111,
            'ai_output' => [
                'id' => 'dec-test-012',
                'score' => 38,
                'reasoning' => 'Low relevance',
                'topic' => 'contract disputes',
            ],
            'confidence_score' => 0.38,
            'status' => 'reviewed',
            'reviewed_by' => $this->user->id,
            'reviewed_at' => now(),
            'human_label' => [
                'correct_score' => 92,
                'reasoning' => 'Critical precedent for contract law',
                'corrected_text' => 'Important ruling on contract interpretation',
            ],
        ]);

        // Create similar pending opportunities for re-scoring
        LearningOpportunity::create([
            'opportunity_type' => 'decision_discovery',
            'source_type' => 'decision_score',
            'source_id' => 222222,
            'ai_output' => [
                'id' => 'dec-test-013',
                'score' => 40,
                'reasoning' => 'Similar low confidence',
                'topic' => 'contract disputes',
            ],
            'confidence_score' => 0.40,
            'status' => 'pending',
        ]);

        LearningOpportunity::create([
            'opportunity_type' => 'decision_discovery',
            'source_type' => 'decision_score',
            'source_id' => 333333,
            'ai_output' => [
                'id' => 'dec-test-014',
                'score' => 42,
                'reasoning' => 'Related contract case',
                'topic' => 'contract disputes',
            ],
            'confidence_score' => 0.42,
            'status' => 'pending',
        ]);

        // Run full pipeline
        $result = $this->learningService->incorporateFeedback(
            $opportunity->id,
            $opportunity->human_label
        );

        // Verify all pipeline stages completed
        $this->assertTrue($result['success']);
        $this->assertTrue($result['vector_store_added']);
        $this->assertTrue($result['graph_updated']);
        $this->assertTrue($result['similar_items_rescored']);
        $this->assertArrayHasKey('accuracy_metrics', $result);

        // Verify opportunity updated
        $opportunity->refresh();
        $this->assertNotNull($opportunity->incorporated_at);

        // Verify complete pipeline metrics
        $this->assertArrayHasKey('pipeline_metrics', $result);
        $this->assertArrayHasKey('total_time_ms', $result['pipeline_metrics']);
        $this->assertArrayHasKey('stages_completed', $result['pipeline_metrics']);
        $this->assertEquals(4, $result['pipeline_metrics']['stages_completed']);
    }
}

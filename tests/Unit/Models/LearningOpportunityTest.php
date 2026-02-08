<?php

namespace Tests\Unit\Models;

use App\Models\LearningOpportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TDD Tests for LearningOpportunity Model
 *
 * Sprint 5.1: Learning Opportunity Detection
 *
 * Tests the LearningOpportunity model for storing and managing
 * low-confidence AI outputs that need human review.
 */
class LearningOpportunityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        // Set AWS environment variables for TextractService
        putenv('AWS_DEFAULT_REGION=us-east-1');
        putenv('AWS_ACCESS_KEY_ID=test-key');
        putenv('AWS_SECRET_ACCESS_KEY=test-secret');
        putenv('AWS_BUCKET=test-bucket');

        parent::setUp();
    }

    /** @test */
    public function it_creates_learning_opportunity_with_required_fields()
    {
        $opportunity = LearningOpportunity::create([
            'opportunity_type' => 'low_confidence_analysis',
            'source_type' => 'decision_analysis',
            'source_id' => 123,
            'ai_output' => ['analysis' => 'Some analysis text'],
            'confidence_score' => 0.45,
            'uncertainty_reason' => 'Low confidence due to ambiguous legal precedent',
        ]);

        $this->assertDatabaseHas('learning_opportunities', [
            'opportunity_type' => 'low_confidence_analysis',
            'source_type' => 'decision_analysis',
            'source_id' => 123,
            'confidence_score' => 0.45,
        ]);

        $this->assertEquals('pending', $opportunity->status);
        $this->assertIsArray($opportunity->ai_output);
    }

    /** @test */
    public function it_casts_ai_output_to_array()
    {
        $opportunity = LearningOpportunity::create([
            'opportunity_type' => 'low_confidence_analysis',
            'source_type' => 'decision_analysis',
            'source_id' => 456,
            'ai_output' => ['key' => 'value', 'score' => 0.5],
            'confidence_score' => 0.50,
        ]);

        $this->assertIsArray($opportunity->ai_output);
        $this->assertEquals('value', $opportunity->ai_output['key']);
        $this->assertEquals(0.5, $opportunity->ai_output['score']);
    }

    /** @test */
    public function it_casts_human_label_to_array()
    {
        $opportunity = LearningOpportunity::create([
            'opportunity_type' => 'low_confidence_analysis',
            'source_type' => 'decision_analysis',
            'source_id' => 789,
            'ai_output' => ['analysis' => 'test'],
            'confidence_score' => 0.55,
            'human_label' => ['correct' => true, 'feedback' => 'Good analysis'],
        ]);

        $this->assertIsArray($opportunity->human_label);
        $this->assertTrue($opportunity->human_label['correct']);
        $this->assertEquals('Good analysis', $opportunity->human_label['feedback']);
    }

    /** @test */
    public function it_defaults_status_to_pending()
    {
        $opportunity = LearningOpportunity::create([
            'opportunity_type' => 'low_confidence_analysis',
            'source_type' => 'decision_analysis',
            'source_id' => 111,
            'ai_output' => ['test' => 'data'],
            'confidence_score' => 0.40,
        ]);

        $this->assertEquals('pending', $opportunity->status);
    }

    /** @test */
    public function it_has_scope_for_pending_opportunities()
    {
        LearningOpportunity::create([
            'opportunity_type' => 'low_confidence_analysis',
            'source_type' => 'decision_analysis',
            'source_id' => 1,
            'ai_output' => ['test' => 'data'],
            'confidence_score' => 0.50,
            'status' => 'pending',
        ]);

        LearningOpportunity::create([
            'opportunity_type' => 'low_confidence_analysis',
            'source_type' => 'decision_analysis',
            'source_id' => 2,
            'ai_output' => ['test' => 'data'],
            'confidence_score' => 0.55,
            'status' => 'reviewed',
        ]);

        $pending = LearningOpportunity::pending()->get();

        $this->assertCount(1, $pending);
        $this->assertEquals('pending', $pending->first()->status);
    }

    /** @test */
    public function it_has_scope_for_reviewed_opportunities()
    {
        LearningOpportunity::create([
            'opportunity_type' => 'low_confidence_analysis',
            'source_type' => 'decision_analysis',
            'source_id' => 1,
            'ai_output' => ['test' => 'data'],
            'confidence_score' => 0.50,
            'status' => 'pending',
        ]);

        LearningOpportunity::create([
            'opportunity_type' => 'low_confidence_analysis',
            'source_type' => 'decision_analysis',
            'source_id' => 2,
            'ai_output' => ['test' => 'data'],
            'confidence_score' => 0.55,
            'status' => 'reviewed',
        ]);

        $reviewed = LearningOpportunity::reviewed()->get();

        $this->assertCount(1, $reviewed);
        $this->assertEquals('reviewed', $reviewed->first()->status);
    }

    /** @test */
    public function it_has_scope_for_filtering_by_agent_type()
    {
        LearningOpportunity::create([
            'opportunity_type' => 'decision_discovery',
            'source_type' => 'decision_analysis',
            'source_id' => 1,
            'ai_output' => ['test' => 'data'],
            'confidence_score' => 0.50,
        ]);

        LearningOpportunity::create([
            'opportunity_type' => 'precedent_analysis',
            'source_type' => 'applicability_check',
            'source_id' => 2,
            'ai_output' => ['test' => 'data'],
            'confidence_score' => 0.55,
        ]);

        $decisionOpps = LearningOpportunity::ofType('decision_discovery')->get();
        $precedentOpps = LearningOpportunity::ofType('precedent_analysis')->get();

        $this->assertCount(1, $decisionOpps);
        $this->assertCount(1, $precedentOpps);
    }

    /** @test */
    public function it_has_scope_for_filtering_by_confidence_range()
    {
        LearningOpportunity::create([
            'opportunity_type' => 'low_confidence_analysis',
            'source_type' => 'decision_analysis',
            'source_id' => 1,
            'ai_output' => ['test' => 'data'],
            'confidence_score' => 0.30,
        ]);

        LearningOpportunity::create([
            'opportunity_type' => 'low_confidence_analysis',
            'source_type' => 'decision_analysis',
            'source_id' => 2,
            'ai_output' => ['test' => 'data'],
            'confidence_score' => 0.75,
        ]);

        $lowConfidence = LearningOpportunity::confidenceBelow(0.60)->get();
        $highConfidence = LearningOpportunity::confidenceAbove(0.60)->get();

        $this->assertCount(1, $lowConfidence);
        $this->assertEquals(0.30, $lowConfidence->first()->confidence_score);

        $this->assertCount(1, $highConfidence);
        $this->assertEquals(0.75, $highConfidence->first()->confidence_score);
    }

    /** @test */
    public function it_can_mark_opportunity_as_reviewed()
    {
        $user = User::factory()->create();

        $opportunity = LearningOpportunity::create([
            'opportunity_type' => 'low_confidence_analysis',
            'source_type' => 'decision_analysis',
            'source_id' => 123,
            'ai_output' => ['test' => 'data'],
            'confidence_score' => 0.45,
            'status' => 'pending',
        ]);

        $humanLabel = ['correct' => true, 'notes' => 'Analysis was correct'];
        $opportunity->markAsReviewed($user->id, $humanLabel);

        $this->assertEquals('reviewed', $opportunity->fresh()->status);
        $this->assertEquals($user->id, $opportunity->fresh()->reviewed_by);
        $this->assertNotNull($opportunity->fresh()->reviewed_at);
        $this->assertEquals($humanLabel, $opportunity->fresh()->human_label);
    }

    /** @test */
    public function it_belongs_to_reviewer_user()
    {
        $user = User::factory()->create();

        $opportunity = LearningOpportunity::create([
            'opportunity_type' => 'low_confidence_analysis',
            'source_type' => 'decision_analysis',
            'source_id' => 123,
            'ai_output' => ['test' => 'data'],
            'confidence_score' => 0.45,
            'reviewed_by' => $user->id,
        ]);

        $this->assertInstanceOf(User::class, $opportunity->reviewer);
        $this->assertEquals($user->id, $opportunity->reviewer->id);
    }

    /** @test */
    public function it_orders_by_confidence_ascending()
    {
        LearningOpportunity::create([
            'opportunity_type' => 'low_confidence_analysis',
            'source_type' => 'decision_analysis',
            'source_id' => 1,
            'ai_output' => ['test' => 'data'],
            'confidence_score' => 0.80,
        ]);

        LearningOpportunity::create([
            'opportunity_type' => 'low_confidence_analysis',
            'source_type' => 'decision_analysis',
            'source_id' => 2,
            'ai_output' => ['test' => 'data'],
            'confidence_score' => 0.30,
        ]);

        LearningOpportunity::create([
            'opportunity_type' => 'low_confidence_analysis',
            'source_type' => 'decision_analysis',
            'source_id' => 3,
            'ai_output' => ['test' => 'data'],
            'confidence_score' => 0.55,
        ]);

        $ordered = LearningOpportunity::lowestConfidenceFirst()->get();

        $this->assertEquals(0.30, $ordered->first()->confidence_score);
        $this->assertEquals(0.80, $ordered->last()->confidence_score);
    }
}

<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\LearningOpportunityManager;
use App\Models\LearningOpportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * TDD Tests for LearningOpportunityManager Livewire Component
 *
 * Sprint 5.2: Human Feedback Integration
 *
 * Tests the Livewire component for viewing and submitting feedback on learning opportunities.
 */
class LearningOpportunityManagerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        // Set AWS environment variables for TextractService
        putenv('AWS_DEFAULT_REGION=us-east-1');
        putenv('AWS_ACCESS_KEY_ID=test-key');
        putenv('AWS_SECRET_ACCESS_KEY=test-secret');
        putenv('AWS_BUCKET=test-bucket');

        parent::setUp();

        $this->user = User::factory()->create();
    }

    /** @test */
    public function it_renders_successfully()
    {
        $this->actingAs($this->user);

        Livewire::test(LearningOpportunityManager::class)
            ->assertStatus(200);
    }

    /** @test */
    public function it_displays_pending_learning_opportunities()
    {
        $this->actingAs($this->user);

        $pending = LearningOpportunity::create([
            'opportunity_type' => 'decision_discovery',
            'source_type' => 'decision_score',
            'source_id' => 12345,
            'ai_output' => ['id' => 'dec-1', 'score' => 45],
            'confidence_score' => 0.45,
            'status' => 'pending',
        ]);

        $reviewed = LearningOpportunity::create([
            'opportunity_type' => 'decision_discovery',
            'source_type' => 'decision_score',
            'source_id' => 67890,
            'ai_output' => ['id' => 'dec-2', 'score' => 50],
            'confidence_score' => 0.50,
            'status' => 'reviewed',
        ]);

        Livewire::test(LearningOpportunityManager::class)
            ->assertSee('dec-1')
            ->assertDontSee('dec-2'); // Should not show reviewed
    }

    /** @test */
    public function it_filters_by_opportunity_type()
    {
        $this->actingAs($this->user);

        LearningOpportunity::create([
            'opportunity_type' => 'decision_discovery',
            'source_type' => 'decision_score',
            'source_id' => 111,
            'ai_output' => ['id' => 'dec-1', 'score' => 45],
            'confidence_score' => 0.45,
            'status' => 'pending',
        ]);

        LearningOpportunity::create([
            'opportunity_type' => 'precedent_analysis',
            'source_type' => 'applicability_check',
            'source_id' => 222,
            'ai_output' => ['decision_id' => 'dec-2', 'applicability_score' => 52],
            'confidence_score' => 0.52,
            'status' => 'pending',
        ]);

        Livewire::test(LearningOpportunityManager::class)
            ->set('filterType', 'decision_discovery')
            ->assertSee('dec-1')
            ->assertDontSee('dec-2');
    }

    /** @test */
    public function it_submits_feedback_successfully()
    {
        $this->actingAs($this->user);

        $opportunity = LearningOpportunity::create([
            'opportunity_type' => 'decision_discovery',
            'source_type' => 'decision_score',
            'source_id' => 12345,
            'ai_output' => ['id' => 'dec-1', 'score' => 45, 'reasoning' => 'Low confidence'],
            'confidence_score' => 0.45,
            'status' => 'pending',
        ]);

        Livewire::test(LearningOpportunityManager::class)
            ->set('selectedOpportunityId', $opportunity->id)
            ->set('feedbackData', [
                'correct_score' => 75,
                'reasoning' => 'Actually quite relevant',
                'decision' => 'should_ingest',
            ])
            ->call('submitFeedback')
            ->assertEmitted('feedbackSubmitted')
            ->assertSet('selectedOpportunityId', null); // Clear selection after submit

        // Verify database updated
        $opportunity->refresh();
        $this->assertEquals('reviewed', $opportunity->status);
        $this->assertEquals(75, $opportunity->human_label['correct_score']);
    }

    /** @test */
    public function it_validates_feedback_data_before_submission()
    {
        $this->actingAs($this->user);

        $opportunity = LearningOpportunity::create([
            'opportunity_type' => 'decision_discovery',
            'source_type' => 'decision_score',
            'source_id' => 12345,
            'ai_output' => ['id' => 'dec-1', 'score' => 45],
            'confidence_score' => 0.45,
            'status' => 'pending',
        ]);

        Livewire::test(LearningOpportunityManager::class)
            ->set('selectedOpportunityId', $opportunity->id)
            ->set('feedbackData', []) // Empty feedback
            ->call('submitFeedback')
            ->assertHasErrors(['feedbackData']);
    }

    /** @test */
    public function it_displays_ai_output_details()
    {
        $this->actingAs($this->user);

        $opportunity = LearningOpportunity::create([
            'opportunity_type' => 'decision_discovery',
            'source_type' => 'decision_score',
            'source_id' => 12345,
            'ai_output' => [
                'id' => 'dec-unique-123',
                'score' => 45,
                'reasoning' => 'Low relevance to contract disputes',
                'topic' => 'contract law',
            ],
            'confidence_score' => 0.45,
            'uncertainty_reason' => 'Low confidence score: 0.45 (threshold: 70)',
            'status' => 'pending',
        ]);

        Livewire::test(LearningOpportunityManager::class)
            ->assertSee('dec-unique-123')
            ->assertSee('45')
            ->assertSee('Low relevance to contract disputes')
            ->assertSee('contract law');
    }

    /** @test */
    public function it_orders_by_lowest_confidence_first()
    {
        $this->actingAs($this->user);

        $high = LearningOpportunity::create([
            'opportunity_type' => 'decision_discovery',
            'source_type' => 'decision_score',
            'source_id' => 111,
            'ai_output' => ['id' => 'dec-high', 'score' => 58],
            'confidence_score' => 0.58,
            'status' => 'pending',
        ]);

        $low = LearningOpportunity::create([
            'opportunity_type' => 'decision_discovery',
            'source_type' => 'decision_score',
            'source_id' => 222,
            'ai_output' => ['id' => 'dec-low', 'score' => 35],
            'confidence_score' => 0.35,
            'status' => 'pending',
        ]);

        $component = Livewire::test(LearningOpportunityManager::class);

        // Get the opportunities from the component
        $opportunities = $component->viewData('opportunities');

        // First opportunity should be the lowest confidence
        $this->assertEquals($low->id, $opportunities->first()->id);
    }

    /** @test */
    public function it_shows_empty_state_when_no_pending_opportunities()
    {
        $this->actingAs($this->user);

        Livewire::test(LearningOpportunityManager::class)
            ->assertSee('No pending learning opportunities');
    }

    /** @test */
    public function it_cancels_feedback_form()
    {
        $this->actingAs($this->user);

        $opportunity = LearningOpportunity::create([
            'opportunity_type' => 'decision_discovery',
            'source_type' => 'decision_score',
            'source_id' => 12345,
            'ai_output' => ['id' => 'dec-1', 'score' => 45],
            'confidence_score' => 0.45,
            'status' => 'pending',
        ]);

        Livewire::test(LearningOpportunityManager::class)
            ->set('selectedOpportunityId', $opportunity->id)
            ->set('feedbackData', ['score' => 75])
            ->call('cancelFeedback')
            ->assertSet('selectedOpportunityId', null)
            ->assertSet('feedbackData', []);
    }
}

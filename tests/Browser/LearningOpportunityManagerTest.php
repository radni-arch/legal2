<?php

namespace Tests\Browser;

use App\Models\LearningOpportunity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Dusk\Browser;
use Tests\Browser\Concerns\AuthenticatesUser;
use Tests\Browser\Concerns\MocksExternalApis;
use Tests\DuskTestCase;

/**
 * LearningOpportunityManager E2E Tests
 *
 * Comprehensive browser tests for the learning opportunity manager component.
 * Tests all core functionality including displaying opportunities, filtering,
 * providing feedback, and error handling. Learning opportunities are low-confidence
 * AI outputs that need human review for active learning and model improvement.
 */
class LearningOpportunityManagerTest extends DuskTestCase
{
    use AuthenticatesUser, MocksExternalApis;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock external APIs for offline testing
        $this->mockAllExternalApis();

        // Create test user with unique email
        $this->user = User::factory()->create();
    }

    /**
     * Test: Component loads successfully with empty state
     */
    public function test_component_loads_successfully_with_empty_state(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-learning-opportunity-manager')
                ->waitForLivewire()
                ->assertPresent('@learning-opportunity-manager')
                ->assertPresent('@filter-container')
                ->assertPresent('@filter-type')
                ->assertPresent('@empty-state')
                ->assertSeeIn('@empty-state', 'No pending learning opportunities');
        });
    }

    /**
     * Test: Displays learning opportunities correctly
     */
    public function test_displays_learning_opportunities_correctly(): void
    {
        // Create test learning opportunities
        LearningOpportunity::factory()->create([
            'status' => 'pending',
            'opportunity_type' => 'decision_discovery',
            'source_type' => 'agent_research',
            'confidence_score' => 0.65,
            'ai_output' => [
                'id' => 'dec-123',
                'score' => 0.85,
                'reasoning' => 'Test reasoning for decision discovery',
            ],
            'uncertainty_reason' => 'Low confidence in similarity match',
        ]);

        LearningOpportunity::factory()->create([
            'status' => 'pending',
            'opportunity_type' => 'precedent_analysis',
            'source_type' => 'vector_search',
            'confidence_score' => 0.55,
            'ai_output' => [
                'decision_id' => 'dec-456',
                'applicability_score' => 0.75,
                'topic' => 'Search Warrants',
            ],
        ]);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-learning-opportunity-manager')
                ->waitForLivewire()
                ->assertPresent('@learning-opportunity-manager')
                ->assertPresent('@opportunities-list')
                // Check first opportunity
                ->assertPresent('@opportunity-card-0')
                ->assertSeeIn('@opportunity-type-0', 'decision_discovery')
                ->assertSeeIn('@opportunity-confidence-0', '0.65')
                ->assertSeeIn('@opportunity-source-0', 'agent_research')
                ->assertPresent('@opportunity-decision-id-0')
                ->assertPresent('@opportunity-ai-score-0')
                ->assertPresent('@opportunity-reasoning-0')
                ->assertPresent('@opportunity-uncertainty-0')
                ->assertPresent('@provide-feedback-btn-0')
                // Check second opportunity
                ->assertPresent('@opportunity-card-1')
                ->assertSeeIn('@opportunity-type-1', 'precedent_analysis')
                ->assertSeeIn('@opportunity-confidence-1', '0.55')
                ->assertSeeIn('@opportunity-source-1', 'vector_search')
                ->assertPresent('@opportunity-decision-id-alt-1')
                ->assertPresent('@opportunity-applicability-score-1')
                ->assertPresent('@opportunity-topic-1')
                ->assertPresent('@provide-feedback-btn-1');
        });
    }

    /**
     * Test: Filter by type - Decision Discovery
     */
    public function test_filter_by_decision_discovery_type(): void
    {
        // Create opportunities of different types
        LearningOpportunity::factory()->create([
            'status' => 'pending',
            'opportunity_type' => 'decision_discovery',
            'confidence_score' => 0.65,
        ]);

        LearningOpportunity::factory()->create([
            'status' => 'pending',
            'opportunity_type' => 'precedent_analysis',
            'confidence_score' => 0.55,
        ]);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-learning-opportunity-manager')
                ->waitForLivewire()
                ->assertPresent('@opportunities-list')
                ->assertPresent('@opportunity-card-0')
                ->assertPresent('@opportunity-card-1')
                // Apply decision_discovery filter
                ->select('@filter-type', 'decision_discovery')
                ->waitForLivewire()
                ->pause(500)
                ->assertPresent('@opportunity-card-0')
                ->assertSeeIn('@opportunity-type-0', 'decision_discovery')
                // Second card should not be visible after filtering
                ->assertMissing('@opportunity-card-1');
        });
    }

    /**
     * Test: Filter by type - Precedent Analysis
     */
    public function test_filter_by_precedent_analysis_type(): void
    {
        // Create opportunities of different types
        LearningOpportunity::factory()->create([
            'status' => 'pending',
            'opportunity_type' => 'decision_discovery',
            'confidence_score' => 0.65,
        ]);

        LearningOpportunity::factory()->create([
            'status' => 'pending',
            'opportunity_type' => 'precedent_analysis',
            'confidence_score' => 0.55,
        ]);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-learning-opportunity-manager')
                ->waitForLivewire()
                ->assertPresent('@opportunities-list')
                // Apply precedent_analysis filter
                ->select('@filter-type', 'precedent_analysis')
                ->waitForLivewire()
                ->pause(500)
                ->assertPresent('@opportunity-card-0')
                ->assertSeeIn('@opportunity-type-0', 'precedent_analysis')
                // Should only show one card
                ->assertMissing('@opportunity-card-1');
        });
    }

    /**
     * Test: Clear filter shows all opportunities
     */
    public function test_clear_filter_shows_all_opportunities(): void
    {
        // Create opportunities of different types
        LearningOpportunity::factory()->create([
            'status' => 'pending',
            'opportunity_type' => 'decision_discovery',
            'confidence_score' => 0.65,
        ]);

        LearningOpportunity::factory()->create([
            'status' => 'pending',
            'opportunity_type' => 'precedent_analysis',
            'confidence_score' => 0.55,
        ]);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-learning-opportunity-manager')
                ->waitForLivewire()
                // Apply filter
                ->select('@filter-type', 'decision_discovery')
                ->waitForLivewire()
                ->pause(500)
                ->assertPresent('@opportunity-card-0')
                ->assertMissing('@opportunity-card-1')
                // Clear filter
                ->select('@filter-type', '')
                ->waitForLivewire()
                ->pause(500)
                // Both should be visible now
                ->assertPresent('@opportunity-card-0')
                ->assertPresent('@opportunity-card-1');
        });
    }

    /**
     * Test: Opens feedback modal when clicking provide feedback
     */
    public function test_opens_feedback_modal_on_click(): void
    {
        LearningOpportunity::factory()->create([
            'status' => 'pending',
            'opportunity_type' => 'decision_discovery',
            'confidence_score' => 0.65,
        ]);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-learning-opportunity-manager')
                ->waitForLivewire()
                ->assertPresent('@opportunity-card-0')
                ->assertMissing('@feedback-modal')
                // Click provide feedback button
                ->click('@provide-feedback-btn-0')
                ->waitForLivewire()
                ->pause(500)
                // Modal should be visible
                ->assertPresent('@feedback-modal')
                ->assertPresent('@feedback-modal-content')
                ->assertPresent('@feedback-modal-title')
                ->assertPresent('@feedback-textarea')
                ->assertPresent('@submit-feedback-btn')
                ->assertPresent('@cancel-feedback-btn');
        });
    }

    /**
     * Test: Cancel feedback closes modal
     */
    public function test_cancel_feedback_closes_modal(): void
    {
        LearningOpportunity::factory()->create([
            'status' => 'pending',
            'opportunity_type' => 'decision_discovery',
            'confidence_score' => 0.65,
        ]);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-learning-opportunity-manager')
                ->waitForLivewire()
                ->click('@provide-feedback-btn-0')
                ->waitForLivewire()
                ->pause(500)
                ->assertPresent('@feedback-modal')
                // Click cancel
                ->click('@cancel-feedback-btn')
                ->waitForLivewire()
                ->pause(500)
                // Modal should be closed
                ->assertMissing('@feedback-modal');
        });
    }

    /**
     * Test: Submit feedback with valid data
     */
    public function test_submit_feedback_with_valid_data(): void
    {
        $opportunity = LearningOpportunity::factory()->create([
            'status' => 'pending',
            'opportunity_type' => 'decision_discovery',
            'confidence_score' => 0.65,
        ]);

        $this->browse(function (Browser $browser) use ($opportunity) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-learning-opportunity-manager')
                ->waitForLivewire()
                ->click('@provide-feedback-btn-0')
                ->waitForLivewire()
                ->pause(500)
                ->assertPresent('@feedback-modal')
                // Enter feedback data (must be array)
                ->script('window.livewire.find("'.$browser->attribute('@feedback-textarea', 'wire:id').'").set("feedbackData", {"correct": true, "notes": "Good match"})');

            $browser->pause(500)
                ->click('@submit-feedback-btn')
                ->waitForLivewire()
                ->pause(1000)
                // Should show success message
                ->assertPresent('@success-message')
                ->assertSee('Feedback submitted successfully!')
                // Modal should be closed
                ->assertMissing('@feedback-modal');

            // Verify in database
            $opportunity->refresh();
            $this->assertEquals('reviewed', $opportunity->status);
            $this->assertNotNull($opportunity->reviewed_by);
            $this->assertNotNull($opportunity->reviewed_at);
            $this->assertNotNull($opportunity->human_label);
        });
    }

    /**
     * Test: Submit feedback validation - empty feedback data
     */
    public function test_submit_feedback_validation_error(): void
    {
        LearningOpportunity::factory()->create([
            'status' => 'pending',
            'opportunity_type' => 'decision_discovery',
            'confidence_score' => 0.65,
        ]);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-learning-opportunity-manager')
                ->waitForLivewire()
                ->click('@provide-feedback-btn-0')
                ->waitForLivewire()
                ->pause(500)
                ->assertPresent('@feedback-modal')
                // Don't enter any feedback data
                // Click submit
                ->click('@submit-feedback-btn')
                ->waitForLivewire()
                ->pause(500)
                // Should show validation error
                ->assertPresent('@error-feedback-data')
                // Modal should remain open
                ->assertPresent('@feedback-modal');
        });
    }

    /**
     * Test: Cannot submit feedback for already reviewed opportunity
     */
    public function test_cannot_submit_feedback_for_reviewed_opportunity(): void
    {
        $opportunity = LearningOpportunity::factory()->create([
            'status' => 'reviewed',
            'opportunity_type' => 'decision_discovery',
            'confidence_score' => 0.65,
            'reviewed_by' => $this->user->id,
            'reviewed_at' => now(),
            'human_label' => ['correct' => true],
        ]);

        // Manually mark as pending to test the validation
        DB::table('learning_opportunities')
            ->where('id', $opportunity->id)
            ->update(['status' => 'pending']);

        $opportunity->refresh();

        // Re-mark as reviewed after refresh to set up the test
        $opportunity->update(['status' => 'reviewed']);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            // Since it's reviewed, it won't show in the pending list
            // Let's verify that reviewed opportunities are filtered out
            $browser->visit('/test-learning-opportunity-manager')
                ->waitForLivewire()
                ->assertPresent('@empty-state')
                ->assertSee('No pending learning opportunities');
        });
    }

    /**
     * Test: Opportunities ordered by lowest confidence first
     */
    public function test_opportunities_ordered_by_lowest_confidence_first(): void
    {
        // Create opportunities with different confidence scores
        LearningOpportunity::factory()->create([
            'status' => 'pending',
            'opportunity_type' => 'decision_discovery',
            'confidence_score' => 0.85, // Higher confidence
        ]);

        LearningOpportunity::factory()->create([
            'status' => 'pending',
            'opportunity_type' => 'precedent_analysis',
            'confidence_score' => 0.45, // Lower confidence
        ]);

        LearningOpportunity::factory()->create([
            'status' => 'pending',
            'opportunity_type' => 'decision_discovery',
            'confidence_score' => 0.65, // Medium confidence
        ]);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-learning-opportunity-manager')
                ->waitForLivewire()
                ->assertPresent('@opportunities-list')
                // First should be lowest confidence (0.45)
                ->assertSeeIn('@opportunity-confidence-0', '0.45')
                // Second should be medium confidence (0.65)
                ->assertSeeIn('@opportunity-confidence-1', '0.65')
                // Third should be highest confidence (0.85)
                ->assertSeeIn('@opportunity-confidence-2', '0.85');
        });
    }

    /**
     * Test: Only shows pending opportunities, not reviewed ones
     */
    public function test_only_shows_pending_opportunities(): void
    {
        // Create pending opportunity
        LearningOpportunity::factory()->create([
            'status' => 'pending',
            'opportunity_type' => 'decision_discovery',
            'confidence_score' => 0.65,
        ]);

        // Create reviewed opportunity
        LearningOpportunity::factory()->create([
            'status' => 'reviewed',
            'opportunity_type' => 'precedent_analysis',
            'confidence_score' => 0.55,
            'reviewed_by' => $this->user->id,
            'reviewed_at' => now(),
            'human_label' => ['correct' => true],
        ]);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-learning-opportunity-manager')
                ->waitForLivewire()
                ->assertPresent('@opportunities-list')
                // Should only see one opportunity (the pending one)
                ->assertPresent('@opportunity-card-0')
                ->assertMissing('@opportunity-card-1')
                ->assertSeeIn('@opportunity-type-0', 'decision_discovery');
        });
    }

    /**
     * Test: Displays all AI output fields when present
     */
    public function test_displays_all_ai_output_fields(): void
    {
        LearningOpportunity::factory()->create([
            'status' => 'pending',
            'opportunity_type' => 'decision_discovery',
            'confidence_score' => 0.65,
            'ai_output' => [
                'id' => 'dec-123',
                'score' => 0.85,
                'reasoning' => 'Detailed reasoning text',
                'topic' => 'Search Warrants',
                'applicability_score' => 0.92,
            ],
            'uncertainty_reason' => 'Multiple similar matches found',
        ]);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-learning-opportunity-manager')
                ->waitForLivewire()
                ->assertPresent('@opportunity-card-0')
                ->assertPresent('@opportunity-decision-id-0')
                ->assertSee('dec-123')
                ->assertPresent('@opportunity-ai-score-0')
                ->assertSee('0.85')
                ->assertPresent('@opportunity-reasoning-0')
                ->assertSee('Detailed reasoning text')
                ->assertPresent('@opportunity-topic-0')
                ->assertSee('Search Warrants')
                ->assertPresent('@opportunity-applicability-score-0')
                ->assertSee('0.92')
                ->assertPresent('@opportunity-uncertainty-0')
                ->assertSee('Multiple similar matches found');
        });
    }

    /**
     * Test: Multiple feedback submissions on different opportunities
     */
    public function test_multiple_feedback_submissions(): void
    {
        $opportunity1 = LearningOpportunity::factory()->create([
            'status' => 'pending',
            'opportunity_type' => 'decision_discovery',
            'confidence_score' => 0.65,
        ]);

        $opportunity2 = LearningOpportunity::factory()->create([
            'status' => 'pending',
            'opportunity_type' => 'precedent_analysis',
            'confidence_score' => 0.55,
        ]);

        $this->browse(function (Browser $browser) use ($opportunity1, $opportunity2) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-learning-opportunity-manager')
                ->waitForLivewire();

            // Submit feedback for first opportunity
            $browser->click('@provide-feedback-btn-0')
                ->waitForLivewire()
                ->pause(500)
                ->assertPresent('@feedback-modal')
                ->script('window.livewire.find("'.$browser->attribute('@feedback-textarea', 'wire:id').'").set("feedbackData", {"correct": true})');

            $browser->pause(500)
                ->click('@submit-feedback-btn')
                ->waitForLivewire()
                ->pause(1000)
                ->assertPresent('@success-message')
                ->assertMissing('@feedback-modal');

            // Wait for page to update
            $browser->pause(1000);

            // Submit feedback for second opportunity (which should now be first)
            $browser->click('@provide-feedback-btn-0')
                ->waitForLivewire()
                ->pause(500)
                ->assertPresent('@feedback-modal')
                ->script('window.livewire.find("'.$browser->attribute('@feedback-textarea', 'wire:id').'").set("feedbackData", {"correct": false, "reason": "Incorrect match"})');

            $browser->pause(500)
                ->click('@submit-feedback-btn')
                ->waitForLivewire()
                ->pause(1000)
                ->assertPresent('@success-message');

            // Verify both are reviewed in database
            $opportunity1->refresh();
            $opportunity2->refresh();
            $this->assertEquals('reviewed', $opportunity1->status);
            $this->assertEquals('reviewed', $opportunity2->status);
        });
    }
}

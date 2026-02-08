<?php

namespace Tests\Browser;

use App\Models\LearningOpportunity;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\Browser\Concerns\AuthenticatesUser;
use Tests\Browser\Concerns\MocksExternalApis;
use Tests\DuskTestCase;

/**
 * FeedbackDashboard E2E Tests
 *
 * Comprehensive browser tests for the learning opportunity feedback dashboard component.
 * Tests all core functionality including statistics display, type breakdown, recent activity,
 * and refresh button interaction. Learning opportunities are low-confidence AI outputs
 * that need human review for active learning and model improvement.
 */
class FeedbackDashboardTest extends DuskTestCase
{
    use AuthenticatesUser, MocksExternalApis;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock external APIs for offline testing
        $this->mockAllExternalApis();

        // Create test user with unique email
        $this->user = User::factory()->create([
            'email' => 'test-'.uniqid().'@example.com',
            'password' => bcrypt('password'),
        ]);
    }

    protected function tearDown(): void
    {
        // Manual cleanup - data is committed so browser can see it
        if (isset($this->user)) {
            $this->user->delete();
        }

        // Clean up test learning opportunities
        LearningOpportunity::query()->delete();

        parent::tearDown();
    }

    /**
     * Test: Component renders successfully with empty state
     */
    public function test_component_renders_with_empty_state(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-feedback-dashboard')
                ->waitForLivewire()
                ->assertPresent('@feedback-dashboard')
                // Check all stat cards are present
                ->assertPresent('@total-opportunities-card')
                ->assertPresent('@pending-opportunities-card')
                ->assertPresent('@reviewed-opportunities-card')
                ->assertPresent('@completion-rate-card')
                // Check all stats are zero
                ->assertSeeIn('@total-opportunities', '0')
                ->assertSeeIn('@pending-opportunities', '0')
                ->assertSeeIn('@reviewed-opportunities', '0')
                ->assertSeeIn('@completion-rate', '0%')
                // Check type breakdown empty state
                ->assertPresent('@type-breakdown-card')
                ->assertPresent('@no-pending-message')
                ->assertSeeIn('@no-pending-message', 'No pending opportunities')
                // Check average confidence
                ->assertPresent('@average-confidence-card')
                ->assertSeeIn('@average-confidence', '0')
                // Check recent activity empty state
                ->assertPresent('@recent-activity-card')
                ->assertPresent('@no-recent-activity-message')
                ->assertSeeIn('@no-recent-activity-message', 'No recent activity')
                // Check refresh button is present
                ->assertPresent('@refresh-button');
        });
    }

    /**
     * Test: Displays statistics correctly with learning opportunities
     */
    public function test_displays_statistics_correctly(): void
    {
        // Create test learning opportunities with different statuses
        LearningOpportunity::factory()->count(3)->create([
            'status' => 'pending',
            'confidence_score' => 0.45,
        ]);

        LearningOpportunity::factory()->count(2)->reviewed()->create([
            'reviewed_by' => $this->user->id,
            'confidence_score' => 0.55,
        ]);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-feedback-dashboard')
                ->waitForLivewire()
                ->pause(500)
                // Check total count (3 pending + 2 reviewed = 5)
                ->assertSeeIn('@total-opportunities', '5')
                // Check pending count
                ->assertSeeIn('@pending-opportunities', '3')
                // Check reviewed count
                ->assertSeeIn('@reviewed-opportunities', '2')
                // Check completion rate (2/5 = 40%)
                ->assertSeeIn('@completion-rate', '40%')
                // Check average confidence ((3*0.45 + 2*0.55) / 5 = 0.49)
                ->assertSeeIn('@average-confidence', '0.49');
        });
    }

    /**
     * Test: Displays type breakdown correctly
     */
    public function test_displays_type_breakdown(): void
    {
        // Create pending opportunities with different types
        LearningOpportunity::factory()->count(3)->decisionDiscovery()->create([
            'status' => 'pending',
        ]);

        LearningOpportunity::factory()->count(2)->precedentAnalysis()->create([
            'status' => 'pending',
        ]);

        // Create reviewed opportunities (should not appear in breakdown)
        LearningOpportunity::factory()->count(2)->reviewed()->create([
            'reviewed_by' => $this->user->id,
        ]);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-feedback-dashboard')
                ->waitForLivewire()
                ->pause(500)
                // Check type breakdown card is present
                ->assertPresent('@type-breakdown-card')
                ->assertPresent('@type-breakdown-list')
                // Check both types are listed
                ->assertPresent('@type-0')
                ->assertPresent('@type-1')
                // Check counts are correct
                ->assertSeeIn('@type-breakdown-list', 'decision_discovery')
                ->assertSeeIn('@type-breakdown-list', 'precedent_analysis')
                ->assertSeeIn('@type-breakdown-list', '3')
                ->assertSeeIn('@type-breakdown-list', '2');
        });
    }

    /**
     * Test: Displays recent activity correctly
     */
    public function test_displays_recent_activity(): void
    {
        // Create reviewed opportunities (most recent first)
        $opportunities = [];
        for ($i = 0; $i < 7; $i++) {
            $opportunities[] = LearningOpportunity::factory()->reviewed()->create([
                'reviewed_by' => $this->user->id,
                'reviewed_at' => now()->subMinutes($i),
                'opportunity_type' => $i % 2 === 0 ? 'decision_discovery' : 'precedent_analysis',
            ]);
        }

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-feedback-dashboard')
                ->waitForLivewire()
                ->pause(500)
                // Check recent activity card is present
                ->assertPresent('@recent-activity-card')
                ->assertPresent('@recent-activity-list')
                // Should show only 5 most recent (0-4, not 5-6)
                ->assertPresent('@activity-0')
                ->assertPresent('@activity-1')
                ->assertPresent('@activity-2')
                ->assertPresent('@activity-3')
                ->assertPresent('@activity-4')
                // Should not show the 6th and 7th
                ->assertMissing('@activity-5')
                ->assertMissing('@activity-6')
                // Check activity contains reviewer name
                ->assertSeeIn('@recent-activity-list', $this->user->name)
                // Check activity contains opportunity types
                ->assertSeeIn('@recent-activity-list', 'decision_discovery')
                ->assertSeeIn('@recent-activity-list', 'precedent_analysis');
        });
    }

    /**
     * Test: Refresh button updates statistics
     */
    public function test_refresh_button_updates_statistics(): void
    {
        // Create initial opportunities
        LearningOpportunity::factory()->count(2)->create(['status' => 'pending']);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-feedback-dashboard')
                ->waitForLivewire()
                ->pause(500)
                // Check initial count
                ->assertSeeIn('@total-opportunities', '2')
                ->assertSeeIn('@pending-opportunities', '2');

            // Create more opportunities while on the page
            LearningOpportunity::factory()->count(3)->create(['status' => 'pending']);

            $browser->click('@refresh-button')
                ->pause(1000)
                // Check updated count
                ->assertSeeIn('@total-opportunities', '5')
                ->assertSeeIn('@pending-opportunities', '5');
        });
    }

    /**
     * Test: Displays 100% completion rate correctly
     */
    public function test_displays_full_completion_rate(): void
    {
        // Create only reviewed opportunities
        LearningOpportunity::factory()->count(5)->reviewed()->create([
            'reviewed_by' => $this->user->id,
        ]);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-feedback-dashboard')
                ->waitForLivewire()
                ->pause(500)
                // Check 100% completion rate
                ->assertSeeIn('@total-opportunities', '5')
                ->assertSeeIn('@pending-opportunities', '0')
                ->assertSeeIn('@reviewed-opportunities', '5')
                ->assertSeeIn('@completion-rate', '100%')
                // Type breakdown should show empty state (no pending)
                ->assertPresent('@no-pending-message')
                // Recent activity should show all 5
                ->assertPresent('@recent-activity-list')
                ->assertPresent('@activity-0')
                ->assertPresent('@activity-4');
        });
    }

    /**
     * Test: Handles zero completion rate correctly
     */
    public function test_displays_zero_completion_rate(): void
    {
        // Create only pending opportunities
        LearningOpportunity::factory()->count(5)->create(['status' => 'pending']);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-feedback-dashboard')
                ->waitForLivewire()
                ->pause(500)
                // Check 0% completion rate
                ->assertSeeIn('@total-opportunities', '5')
                ->assertSeeIn('@pending-opportunities', '5')
                ->assertSeeIn('@reviewed-opportunities', '0')
                ->assertSeeIn('@completion-rate', '0%')
                // Recent activity should be empty
                ->assertPresent('@no-recent-activity-message');
        });
    }

    /**
     * Test: Displays various confidence scores correctly
     */
    public function test_displays_confidence_scores(): void
    {
        // Create opportunities with specific confidence scores
        LearningOpportunity::factory()->create(['confidence_score' => 0.30]);
        LearningOpportunity::factory()->create(['confidence_score' => 0.40]);
        LearningOpportunity::factory()->create(['confidence_score' => 0.50]);
        LearningOpportunity::factory()->create(['confidence_score' => 0.60]);
        // Average: (0.30 + 0.40 + 0.50 + 0.60) / 4 = 0.45

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-feedback-dashboard')
                ->waitForLivewire()
                ->pause(500)
                // Check average confidence is calculated correctly
                ->assertSeeIn('@average-confidence', '0.45');
        });
    }

    /**
     * Test: Handles single opportunity type in breakdown
     */
    public function test_displays_single_type_breakdown(): void
    {
        // Create pending opportunities with only one type
        LearningOpportunity::factory()->count(5)->decisionDiscovery()->create([
            'status' => 'pending',
        ]);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-feedback-dashboard')
                ->waitForLivewire()
                ->pause(500)
                // Check type breakdown shows only one type
                ->assertPresent('@type-breakdown-list')
                ->assertPresent('@type-0')
                ->assertMissing('@type-1')
                ->assertSeeIn('@type-0', 'decision_discovery')
                ->assertSeeIn('@type-0', '5');
        });
    }

    /**
     * Test: Component requires authentication
     */
    public function test_requires_authentication(): void
    {
        $this->browse(function (Browser $browser) {
            // Try to access without logging in
            $browser->visit('/test-feedback-dashboard')
                ->waitForLocation('/login', 20)
                ->assertPathIs('/login');
        });
    }

    /**
     * Test: Handles mixed reviewed and pending opportunities
     */
    public function test_handles_mixed_status_opportunities(): void
    {
        // Create mix of pending and reviewed opportunities
        LearningOpportunity::factory()->count(10)->decisionDiscovery()->create([
            'status' => 'pending',
            'confidence_score' => 0.40,
        ]);

        LearningOpportunity::factory()->count(5)->precedentAnalysis()->create([
            'status' => 'pending',
            'confidence_score' => 0.35,
        ]);

        LearningOpportunity::factory()->count(8)->reviewed()->create([
            'reviewed_by' => $this->user->id,
            'confidence_score' => 0.50,
        ]);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-feedback-dashboard')
                ->waitForLivewire()
                ->pause(500)
                // Check totals
                ->assertSeeIn('@total-opportunities', '23') // 10 + 5 + 8
                ->assertSeeIn('@pending-opportunities', '15') // 10 + 5
                ->assertSeeIn('@reviewed-opportunities', '8')
                // Check completion rate (8/23 = 34.8%)
                ->assertSeeIn('@completion-rate', '34.8%')
                // Check type breakdown (only pending)
                ->assertPresent('@type-breakdown-list')
                ->assertSeeIn('@type-breakdown-list', 'decision_discovery: 10')
                ->assertSeeIn('@type-breakdown-list', 'precedent_analysis: 5')
                // Check average confidence
                // (10*0.40 + 5*0.35 + 8*0.50) / 23 = 0.42
                ->assertSeeIn('@average-confidence', '0.42')
                // Check recent activity shows 5 most recent reviewed
                ->assertPresent('@recent-activity-list')
                ->assertPresent('@activity-0')
                ->assertPresent('@activity-4');
        });
    }

    /**
     * Test: Displays different reviewers in recent activity
     */
    public function test_displays_different_reviewers(): void
    {
        $reviewer1 = User::factory()->create(['name' => 'Alice Smith']);
        $reviewer2 = User::factory()->create(['name' => 'Bob Johnson']);

        // Create reviewed opportunities by different reviewers
        LearningOpportunity::factory()->count(2)->reviewed()->create([
            'reviewed_by' => $reviewer1->id,
            'reviewed_at' => now()->subMinutes(1),
        ]);

        LearningOpportunity::factory()->count(3)->reviewed()->create([
            'reviewed_by' => $reviewer2->id,
            'reviewed_at' => now()->subMinutes(2),
        ]);

        $this->browse(function (Browser $browser) use ($reviewer1, $reviewer2) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-feedback-dashboard')
                ->waitForLivewire()
                ->pause(500)
                // Check recent activity shows different reviewers
                ->assertPresent('@recent-activity-list')
                ->assertSeeIn('@recent-activity-list', 'Alice Smith')
                ->assertSeeIn('@recent-activity-list', 'Bob Johnson');

            // Cleanup
            $reviewer1->delete();
            $reviewer2->delete();
        });
    }

    /**
     * Test: Displays human-readable time in recent activity
     */
    public function test_displays_human_readable_time(): void
    {
        // Create reviewed opportunities at different times
        LearningOpportunity::factory()->reviewed()->create([
            'reviewed_by' => $this->user->id,
            'reviewed_at' => now()->subMinutes(5),
        ]);

        LearningOpportunity::factory()->reviewed()->create([
            'reviewed_by' => $this->user->id,
            'reviewed_at' => now()->subHours(2),
        ]);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-feedback-dashboard')
                ->waitForLivewire()
                ->pause(500)
                // Check recent activity shows human-readable time
                ->assertPresent('@recent-activity-list')
                // Should contain "ago" for human-readable time
                ->assertSeeIn('@recent-activity-list', 'ago');
        });
    }

    /**
     * Test: Complex scenario with all features
     */
    public function test_complex_dashboard_scenario(): void
    {
        // Create a comprehensive dataset
        $reviewer1 = User::factory()->create(['name' => 'Legal Expert 1']);
        $reviewer2 = User::factory()->create(['name' => 'Legal Expert 2']);

        // 15 pending decision_discovery
        LearningOpportunity::factory()->count(15)->decisionDiscovery()->create([
            'status' => 'pending',
            'confidence_score' => 0.42,
        ]);

        // 8 pending precedent_analysis
        LearningOpportunity::factory()->count(8)->precedentAnalysis()->create([
            'status' => 'pending',
            'confidence_score' => 0.38,
        ]);

        // 12 reviewed by different reviewers
        LearningOpportunity::factory()->count(7)->reviewed()->create([
            'reviewed_by' => $reviewer1->id,
            'reviewed_at' => now()->subHours(rand(1, 5)),
            'confidence_score' => 0.55,
        ]);

        LearningOpportunity::factory()->count(5)->reviewed()->create([
            'reviewed_by' => $reviewer2->id,
            'reviewed_at' => now()->subHours(rand(1, 5)),
            'confidence_score' => 0.50,
        ]);

        $this->browse(function (Browser $browser) use ($reviewer1, $reviewer2) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-feedback-dashboard')
                ->waitForLivewire()
                ->pause(500)
                // Check comprehensive statistics
                ->assertPresent('@feedback-dashboard')
                ->assertSeeIn('@total-opportunities', '35') // 15 + 8 + 7 + 5
                ->assertSeeIn('@pending-opportunities', '23') // 15 + 8
                ->assertSeeIn('@reviewed-opportunities', '12') // 7 + 5
                // Check completion rate (12/35 = 34.3%)
                ->assertSeeIn('@completion-rate', '34.3%')
                // Check type breakdown
                ->assertPresent('@type-breakdown-list')
                ->assertSeeIn('@type-breakdown-list', 'decision_discovery: 15')
                ->assertSeeIn('@type-breakdown-list', 'precedent_analysis: 8')
                // Check average confidence
                // (15*0.42 + 8*0.38 + 7*0.55 + 5*0.50) / 35 = 0.45
                ->assertSeeIn('@average-confidence', '0.45')
                // Check recent activity
                ->assertPresent('@recent-activity-list')
                ->assertPresent('@activity-0')
                ->assertPresent('@activity-4')
                // Test refresh functionality
                ->click('@refresh-button')
                ->pause(1000)
                ->assertPresent('@feedback-dashboard');

            // Cleanup
            $reviewer1->delete();
            $reviewer2->delete();
        });
    }
}

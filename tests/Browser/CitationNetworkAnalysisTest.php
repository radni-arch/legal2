<?php

namespace Tests\Browser;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Laravel\Dusk\Browser;
use Tests\Browser\Concerns\AuthenticatesUser;
use Tests\Browser\Concerns\MocksExternalApis;
use Tests\DuskTestCase;

/**
 * E2E tests for Citation Network Analysis feature
 *
 * Tests the complete user workflow for analyzing citation networks:
 * - Citation graph visualization
 * - Authority metrics (h-index, influence rank)
 * - Citation pattern analysis
 * - Influence spread visualization
 */
class CitationNetworkAnalysisTest extends DuskTestCase
{
    use AuthenticatesUser, DatabaseMigrations, MocksExternalApis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockAllExternalApis();
    }

    /**
     * Test: Can view citation graph visualization
     *
     * User should be able to:
     * 1. Navigate to graph viewer
     * 2. Search for a court decision
     * 3. Click "Citation Analysis" button
     * 4. View citation graph with nodes and edges
     */
    public function test_can_view_citation_graph_visualization(): void
    {
        $user = User::factory()->create();

        // Create test citation data
        $this->seedCitationNetworkData();

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAs($browser, $user);

            $browser->visit('/graph')
                ->waitForText('Graph', 20)

                // Search for a decision
                ->type('@search-input', 'Pp-74/2025')
                ->press('Search')
                ->waitForText('Pp-74/2025', 20)

                // Click citation analysis button
                ->press('@citation-analysis-button')
                ->waitFor('@citation-analysis-panel', 20)

                // Verify citation graph is displayed
                ->assertPresent('@citation-graph-panel')
                ->assertSee('Citation Graph')
                ->assertPresent('@citation-graph-canvas')

                // Verify graph has nodes
                ->assertSee('nodes')
                ->assertSee('edges')

                // Verify we can see citing and cited decisions
                ->assertPresent('@graph-legend');
        });
    }

    /**
     * Test: Can view authority metrics for a decision
     *
     * User should see:
     * - H-index score
     * - Influence rank (highly_influential, influential, etc.)
     * - Citation count (outgoing)
     * - Cited-by count (incoming)
     * - Authority score
     */
    public function test_can_view_authority_metrics(): void
    {
        $user = User::factory()->create();
        $this->seedCitationNetworkData();

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAs($browser, $user);

            $browser->visit('/graph')
                ->waitForText('Graph', 20)

                // Search for decision
                ->type('@search-input', 'Pp-74/2025')
                ->press('Search')
                ->waitForText('Pp-74/2025', 20)

                // Click citation analysis
                ->press('@citation-analysis-button')
                ->waitFor('@citation-analysis-panel', 20)

                // Select authority metrics operation
                ->select('@analysis-operation', 'authority')
                ->press('@run-analysis-button')
                ->waitFor('@authority-metrics-panel', 20)

                // Verify authority metrics are displayed
                ->assertSee('Authority Metrics')
                ->assertPresent('@h-index-value')
                ->assertPresent('@influence-rank-value')
                ->assertPresent('@citation-count-value')
                ->assertPresent('@cited-by-count-value')
                ->assertPresent('@authority-score-value')

                // Verify influence rank is one of the valid values
                ->assertSeeIn('@influence-rank-value', 'influential');
        });
    }

    /**
     * Test: Can analyze citation patterns
     *
     * User should see:
     * - Temporal distribution of citations
     * - Citation types (direct vs indirect)
     * - Detected patterns
     */
    public function test_can_analyze_citation_patterns(): void
    {
        $user = User::factory()->create();
        $this->seedCitationNetworkData();

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAs($browser, $user);

            $browser->visit('/graph')
                ->waitForText('Graph', 20)

                // Search for decision
                ->type('@search-input', 'Pp-74/2025')
                ->press('Search')
                ->waitForText('Pp-74/2025', 20)

                // Click citation analysis
                ->press('@citation-analysis-button')
                ->waitFor('@citation-analysis-panel', 20)

                // Select patterns operation
                ->select('@analysis-operation', 'patterns')
                ->press('@run-analysis-button')
                ->waitFor('@citation-patterns-panel', 20)

                // Verify pattern analysis is displayed
                ->assertSee('Citation Patterns')
                ->assertPresent('@temporal-distribution-chart')
                ->assertPresent('@citation-types-breakdown')

                // Verify we see pattern detection results
                ->assertSee('Detected Patterns')
                ->assertPresent('@patterns-list');
        });
    }

    /**
     * Test: Can visualize influence spread
     *
     * User should see:
     * - Direct influences (decisions this one cites)
     * - Indirect influences (transitive citations)
     * - Total reach metrics
     * - Influence spread visualization
     */
    public function test_can_visualize_influence_spread(): void
    {
        $user = User::factory()->create();
        $this->seedCitationNetworkData();

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAs($browser, $user);

            $browser->visit('/graph')
                ->waitForText('Graph', 20)

                // Search for decision
                ->type('@search-input', 'Pp-74/2025')
                ->press('Search')
                ->waitForText('Pp-74/2025', 20)

                // Click citation analysis
                ->press('@citation-analysis-button')
                ->waitFor('@citation-analysis-panel', 20)

                // Select influence operation
                ->select('@analysis-operation', 'influence')
                ->press('@run-analysis-button')
                ->waitFor('@influence-spread-panel', 20)

                // Verify influence spread is displayed
                ->assertSee('Influence Spread')
                ->assertPresent('@influence-spread-chart')

                // Verify we see direct and indirect influence counts
                ->assertSee('Direct Influences')
                ->assertSee('Indirect Influences')
                ->assertPresent('@direct-influence-count')
                ->assertPresent('@indirect-influence-count')
                ->assertPresent('@total-reach-value')

                // Verify influenced decisions list
                ->assertPresent('@influenced-decisions-list');
        });
    }

    /**
     * Test: Can switch between different citation analysis operations
     *
     * User should be able to switch between:
     * - graph
     * - authority
     * - patterns
     * - influence
     */
    public function test_can_switch_between_analysis_operations(): void
    {
        $user = User::factory()->create();
        $this->seedCitationNetworkData();

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAs($browser, $user);

            $browser->visit('/graph')
                ->waitForText('Graph', 20)

                // Search for decision
                ->type('@search-input', 'Pp-74/2025')
                ->press('Search')
                ->waitForText('Pp-74/2025', 20)

                // Open citation analysis
                ->press('@citation-analysis-button')
                ->waitFor('@citation-analysis-panel', 20)

                // Test switching to graph operation
                ->select('@analysis-operation', 'graph')
                ->press('@run-analysis-button')
                ->waitFor('@citation-graph-panel', 20)
                ->assertSee('Citation Graph')

                // Switch to authority
                ->select('@analysis-operation', 'authority')
                ->press('@run-analysis-button')
                ->waitFor('@authority-metrics-panel', 20)
                ->assertSee('Authority Metrics')

                // Switch to patterns
                ->select('@analysis-operation', 'patterns')
                ->press('@run-analysis-button')
                ->waitFor('@citation-patterns-panel', 20)
                ->assertSee('Citation Patterns')

                // Switch to influence
                ->select('@analysis-operation', 'influence')
                ->press('@run-analysis-button')
                ->waitFor('@influence-spread-panel', 20)
                ->assertSee('Influence Spread');
        });
    }

    /**
     * Test: Citation graph shows correct node relationships
     *
     * Verify the graph correctly shows:
     * - Root decision as center node
     * - Citing decisions (incoming citations)
     * - Cited decisions (outgoing citations)
     * - Proper edge directions
     */
    public function test_citation_graph_shows_correct_relationships(): void
    {
        $user = User::factory()->create();
        $this->seedCitationNetworkData();

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAs($browser, $user);

            $browser->visit('/graph')
                ->waitForText('Graph', 20)

                ->type('@search-input', 'Pp-74/2025')
                ->press('Search')
                ->waitForText('Pp-74/2025', 20)

                ->press('@citation-analysis-button')
                ->waitFor('@citation-analysis-panel', 20)

                ->select('@analysis-operation', 'graph')
                ->press('@run-analysis-button')
                ->waitFor('@citation-graph-panel', 20)

                // Verify legend shows different node types
                ->assertSee('Root Decision')
                ->assertSee('Citing Decisions')
                ->assertSee('Cited Decisions')

                // Verify we can see edge labels
                ->assertPresent('@citation-graph-edges')

                // Verify graph statistics
                ->assertPresent('@graph-stats')
                ->assertSee('Total Citations');
        });
    }

    /**
     * Test: Authority metrics show correct influence rank classification
     *
     * Verify influence rank is correctly classified based on cited-by count:
     * - 50+ citations: highly_influential
     * - 20-49: influential
     * - 10-19: moderately_influential
     * - 5-9: somewhat_influential
     * - 1-4: minimally_influential
     * - 0: not_influential
     */
    public function test_authority_metrics_show_correct_influence_classification(): void
    {
        $user = User::factory()->create();

        // Create a highly influential decision (50+ citations)
        $influentialDecisionId = $this->seedHighlyInfluentialDecision();

        $this->browse(function (Browser $browser) use ($user, $influentialDecisionId) {
            $this->loginAs($browser, $user);

            $browser->visit('/graph')
                ->waitForText('Graph', 20)

                ->type('@search-input', $influentialDecisionId)
                ->press('Search')
                ->waitForText($influentialDecisionId, 10)

                ->press('@citation-analysis-button')
                ->waitFor('@citation-analysis-panel', 20)

                ->select('@analysis-operation', 'authority')
                ->press('@run-analysis-button')
                ->waitFor('@authority-metrics-panel', 20)

                // Verify it's classified as highly influential
                ->assertSeeIn('@influence-rank-value', 'highly_influential')
                ->assertSeeIn('@cited-by-count-value', '50');
        });
    }

    /**
     * Seed citation network test data
     */
    protected function seedCitationNetworkData(): void
    {
        DB::table('citation_relationships')->insert([
            [
                'citing_decision_id' => 'dec-001',
                'cited_decision_id' => 'dec-pp-74-2025',
                'citation_type' => 'direct',
                'created_at' => now()->subMonths(3),
                'updated_at' => now()->subMonths(3),
            ],
            [
                'citing_decision_id' => 'dec-002',
                'cited_decision_id' => 'dec-pp-74-2025',
                'citation_type' => 'direct',
                'created_at' => now()->subMonths(2),
                'updated_at' => now()->subMonths(2),
            ],
            [
                'citing_decision_id' => 'dec-pp-74-2025',
                'cited_decision_id' => 'dec-003',
                'citation_type' => 'direct',
                'created_at' => now()->subMonths(1),
                'updated_at' => now()->subMonths(1),
            ],
        ]);
    }

    /**
     * Seed a highly influential decision with 50+ citations
     */
    protected function seedHighlyInfluentialDecision(): string
    {
        $decisionId = 'dec-highly-influential';

        // Create 50 incoming citations
        for ($i = 1; $i <= 50; $i++) {
            DB::table('citation_relationships')->insert([
                'citing_decision_id' => "dec-citing-{$i}",
                'cited_decision_id' => $decisionId,
                'citation_type' => 'direct',
                'created_at' => now()->subDays($i),
                'updated_at' => now()->subDays($i),
            ]);
        }

        return $decisionId;
    }
}

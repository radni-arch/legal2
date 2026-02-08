<?php

namespace Tests\Browser;

use App\Models\AgentVectorMemory;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\Browser\Concerns\AuthenticatesUser;
use Tests\Browser\Concerns\MocksExternalApis;
use Tests\DuskTestCase;

/**
 * Federated Memory Search E2E Tests
 *
 * TDD Tests for pgvector similarity search in federated memory.
 *
 * Tests the browser interface for:
 * - Semantic search across agent memories
 * - Vector similarity ordering
 * - Cross-agent memory retrieval
 * - Fallback to text search
 *
 * Run with:
 *   php artisan dusk tests/Browser/FederatedMemorySearchTest.php
 *   php artisan dusk --filter test_semantic_search_returns_relevant_results
 *
 * @group browser
 * @group federated-memory
 * @group dusk
 */
class FederatedMemorySearchTest extends DuskTestCase
{
    use AuthenticatesUser, MocksExternalApis;
    // Temporarily disabled DatabaseMigrations due to PostgreSQL type conflicts
    // use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock OpenAI API for offline testing
        $this->mockAllExternalApis();
    }

    /**
     * Test semantic search returns relevant results
     *
     * RED Phase: This test WILL FAIL - no UI exists yet!
     *
     * Verifies:
     * - Federated memory page loads
     * - Search query can be entered
     * - Results display after search
     * - Vector similarity ordering works
     *
     * @test
     */
    public function test_semantic_search_returns_relevant_results(): void
    {
        $user = User::factory()->create();

        // Create agent memories with embeddings
        // These should be found by semantic search for "proportionality home search"
        AgentVectorMemory::factory()->create([
            'agent_name' => 'decision_discovery',
            'content' => 'Proportionality test for home searches requires evidence of serious crime',
            'metadata' => ['topic' => 'proportionality', 'source' => 'case_law'],
            'embedding_vector' => array_fill(0, 1536, 0.9), // High similarity mock
            'access_count' => 0,
        ]);

        AgentVectorMemory::factory()->create([
            'agent_name' => 'research_agent',
            'content' => 'Home search warrants must specify exact locations and items',
            'metadata' => ['topic' => 'warrants', 'source' => 'law'],
            'embedding_vector' => array_fill(0, 1536, 0.8), // Medium similarity mock
            'access_count' => 0,
        ]);

        AgentVectorMemory::factory()->create([
            'agent_name' => 'precedent_analyzer',
            'content' => 'Unrelated insight about traffic violations',
            'metadata' => ['topic' => 'traffic', 'source' => 'law'],
            'embedding_vector' => array_fill(0, 1536, 0.1), // Low similarity mock
            'access_count' => 0,
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAs($browser, $user);

            $browser->visit('/federated-memory')
                ->waitForText('Federated Memory Search', 10)
                ->assertSee('Federated Memory Search')

                // Verify search interface elements present
                ->assertPresent('@search-input')
                ->assertPresent('@search-button')

                // Enter semantic search query
                ->type('@search-input', 'proportionality home search')

                // Submit search
                ->press('@search-button')
                ->waitForText('Search Results', 15)

                // Verify results displayed
                ->assertSee('Search Results')
                ->assertPresent('@result-count')
                ->assertPresent('@results-list')

                // Verify results contain expected content
                ->assertSee('Proportionality test for home searches')
                ->assertSee('Home search warrants must specify')

                // Verify irrelevant result NOT shown
                ->assertDontSee('traffic violations')

                // Verify results ordered by similarity (using dusk selectors)
                ->assertSeeIn('@result-0-content', 'Proportionality test')
                ->assertSeeIn('@result-1-content', 'Home search warrants')

                // Verify agent names displayed
                ->assertSeeIn('@result-0-agent', 'decision_discovery')
                ->assertSeeIn('@result-1-agent', 'research_agent');
        });
    }

    /**
     * Test fallback to text search works
     *
     * Verifies that when pgvector is unavailable or returns no results,
     * the system gracefully falls back to text-based search.
     *
     * @test
     */
    public function test_fallback_to_text_search_works(): void
    {
        $user = User::factory()->create();

        // Create memories without embeddings (simulate pgvector unavailable)
        AgentVectorMemory::factory()->create([
            'agent_name' => 'decision_discovery',
            'content' => 'Text search should find this proportionality insight',
            'metadata' => ['topic' => 'proportionality'],
            'embedding_vector' => null, // No embedding
            'access_count' => 0,
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAs($browser, $user);

            $browser->visit('/federated-memory')
                ->waitForText('Federated Memory Search', 10)

                // Search for content
                ->type('@search-input', 'proportionality')
                ->press('@search-button')
                ->waitForText('Search Results', 15)

                // Should still find results via text search fallback
                ->assertSee('Text search should find this proportionality insight');
        });
    }

    /**
     * Test cross-agent search functionality
     *
     * Verifies:
     * - Can search across all agents
     * - Can filter by specific agent type
     * - Results show agent names
     *
     * @test
     */
    public function test_cross_agent_search_functionality(): void
    {
        $user = User::factory()->create();

        // Create memories from different agents
        AgentVectorMemory::factory()->create([
            'agent_name' => 'decision_discovery',
            'content' => 'Decision discovery insight about proportionality',
            'embedding_vector' => array_fill(0, 1536, 0.9),
        ]);

        AgentVectorMemory::factory()->create([
            'agent_name' => 'research_agent',
            'content' => 'Research agent insight about proportionality',
            'embedding_vector' => array_fill(0, 1536, 0.9),
        ]);

        AgentVectorMemory::factory()->create([
            'agent_name' => 'precedent_analyzer',
            'content' => 'Precedent analyzer insight about proportionality',
            'embedding_vector' => array_fill(0, 1536, 0.9),
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAs($browser, $user);

            $browser->visit('/federated-memory')
                ->waitForText('Federated Memory Search', 10)

                // Search across all agents
                ->type('@search-input', 'proportionality')
                ->press('@search-button')
                ->waitForText('Search Results', 15)

                // Should see results from all 3 agents
                ->assertSee('decision_discovery')
                ->assertSee('research_agent')
                ->assertSee('precedent_analyzer')
                ->assertSeeIn('@result-count', '3 result')

                // Test filtering by specific agent
                ->select('@agent-filter', 'decision_discovery')
                ->press('@search-button')
                ->waitFor('@result-0', 20)

                // Should see only decision_discovery results
                ->assertSee('decision_discovery')
                ->assertDontSee('research_agent')
                ->assertDontSee('precedent_analyzer')
                ->assertSeeIn('@result-count', '1 result');
        });
    }

    /**
     * Test result ordering by similarity score
     *
     * Verifies that results are ordered by vector similarity distance
     * (lower distance = higher similarity = ranked first)
     *
     * @test
     */
    public function test_result_ordering_by_similarity(): void
    {
        $user = User::factory()->create();

        // Create memories with varying similarity scores
        AgentVectorMemory::factory()->create([
            'agent_name' => 'agent1',
            'content' => 'High relevance content about proportionality test',
            'embedding_vector' => array_fill(0, 1536, 0.95), // Highest similarity
            'access_count' => 0,
        ]);

        AgentVectorMemory::factory()->create([
            'agent_name' => 'agent2',
            'content' => 'Medium relevance content about proportionality',
            'embedding_vector' => array_fill(0, 1536, 0.75), // Medium similarity
            'access_count' => 0,
        ]);

        AgentVectorMemory::factory()->create([
            'agent_name' => 'agent3',
            'content' => 'Low relevance content mentioning proportionality',
            'embedding_vector' => array_fill(0, 1536, 0.50), // Low similarity
            'access_count' => 0,
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAs($browser, $user);

            $browser->visit('/federated-memory')
                ->waitForText('Federated Memory Search', 10)

                ->type('@search-input', 'proportionality test')
                ->press('@search-button')
                ->waitForText('Search Results', 15)

                // Verify ordering: highest similarity first
                ->assertSeeIn('@result-0-content', 'High relevance')
                ->assertSeeIn('@result-1-content', 'Medium relevance')
                ->assertSeeIn('@result-2-content', 'Low relevance')

                // Verify similarity scores displayed
                ->assertPresent('@result-0-similarity');
        });
    }

    /**
     * Test access count increments on retrieval
     *
     * Verifies that when memories are retrieved via search,
     * their access_count increments (tracking popularity)
     *
     * @test
     */
    public function test_access_count_increments_on_retrieval(): void
    {
        $user = User::factory()->create();

        $memory = AgentVectorMemory::factory()->create([
            'agent_name' => 'decision_discovery',
            'content' => 'Popular insight about proportionality',
            'embedding_vector' => array_fill(0, 1536, 0.9),
            'access_count' => 5, // Starting count
        ]);

        $this->browse(function (Browser $browser) use ($user, $memory) {
            $this->loginAs($browser, $user);

            $browser->visit('/federated-memory')
                ->waitForText('Federated Memory Search', 10)

                ->type('@search-input', 'proportionality')
                ->press('@search-button')
                ->waitForText('Search Results', 15)

                ->assertSee('Popular insight about proportionality')
                ->assertPresent('@result-0-access-count');

            // Verify access count incremented in database
            $memory->refresh();
            $this->assertGreaterThan(5, $memory->access_count);
        });
    }

    /**
     * Test empty search results handled gracefully
     *
     * Verifies that when no results match the query,
     * the UI shows appropriate empty state message
     *
     * @test
     */
    public function test_empty_search_results_handled_gracefully(): void
    {
        $user = User::factory()->create();

        // No memories created - empty database

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAs($browser, $user);

            $browser->visit('/federated-memory')
                ->waitForText('Federated Memory Search', 10)

                ->type('@search-input', 'nonexistent query xyz')
                ->press('@search-button')
                ->waitForText('No results found', 10)

                ->assertSee('No results found')
                ->assertPresent('@empty-state')
                ->assertMissing('@result-0');
        });
    }

    /**
     * Test search validation
     *
     * Verifies that empty searches are prevented
     * with appropriate validation message
     *
     * @test
     */
    public function test_search_validation(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAs($browser, $user);

            $browser->visit('/federated-memory')
                ->waitForText('Federated Memory Search', 10)

                // Try to search with empty query
                ->press('@search-button')
                ->pause(1000) // Wait for potential validation

                // The validation message is shown via @error directive
                ->assertPresent('@search-input-error');
        });
    }
}

<?php

namespace Tests\Browser;

use App\Models\CaseDocument;
use App\Models\CourtDecision;
use App\Models\Law;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Search Interface Browser Tests
 *
 * Tests the unified search interface with real browser interactions.
 *
 * Requirements:
 * - Laravel Dusk installed: composer require --dev laravel/dusk
 * - ChromeDriver running: php artisan dusk:chrome-driver
 * - Database configured with searchable content
 * - Vector store populated
 *
 * Run with:
 *   php artisan dusk tests/Browser/SearchTest.php
 *   php artisan dusk --filter test_search_interface_basic_search
 *
 * @group browser
 * @group search
 * @group dusk
 */
class SearchTest extends DuskTestCase
{
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Skip if Dusk not installed
        if (! class_exists(Browser::class)) {
            $this->markTestSkipped('Laravel Dusk not installed. Run: composer require --dev laravel/dusk');
        }

        // Ensure tables exist
        if (! Schema::hasTable('users')) {
            $this->markTestSkipped('Database schema not initialized');
        }

        // Create authenticated user with unique email to avoid conflicts
        $this->user = User::factory()->create([
            'email' => 'search-test-'.uniqid().'@example.com',
            'password' => bcrypt('password'),
        ]);
    }

    protected function tearDown(): void
    {
        // Clean up test user
        if (isset($this->user)) {
            $this->user->delete();
        }

        parent::tearDown();
    }

    /**
     * Test basic search interface functionality
     *
     * Verifies:
     * - Search page loads correctly
     * - Query input works
     * - Corpus selection functional
     * - Results display properly
     * - Result count accurate
     *
     * @group search
     */
    public function test_search_interface_basic_search(): void
    {
        // Create searchable content in laws corpus
        Law::factory()->create([
            'title' => 'Croatian Criminal Code',
            'content' => 'This law defines criminal liability and punishments under Croatian law.',
            'law_number' => 'NN 125/11',
            'jurisdiction' => 'Croatia',
        ]);

        Law::factory()->create([
            'title' => 'Criminal Procedure Act',
            'content' => 'This law regulates criminal procedure and defendant rights.',
            'law_number' => 'NN 152/08',
            'jurisdiction' => 'Croatia',
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/search')
                ->assertSee('Search')

                // Verify search interface loaded
                ->assertPresent('input[name="query"]')
                ->assertPresent('.corpus-selector')

                // Enter search query
                ->type('query', 'criminal liability')

                // Verify query appears in input
                ->assertInputValue('query', 'criminal liability')

                // Select corpus (laws)
                ->check('corpora[]', 'laws')

                // Verify corpus selected
                ->assertChecked('corpora[]', 'laws')

                // Submit search
                ->press('Search')

                // Wait for results to load
                ->waitForText('Results', 10)

                // Verify results page loaded
                ->assertSee('Search Results')

                // Verify results displayed
                ->assertSee('Croatian Criminal Code')
                ->assertSee('criminal liability')

                // Verify result count displayed
                ->waitFor('.result-count', 5)
                ->assertSeeIn('.result-count', '1 result')

                // Verify result metadata
                ->assertSee('NN 125/11')
                ->assertSee('Croatia')

                // Verify search query highlighted in results
                ->assertPresent('.highlight')

                // Verify result item structure
                ->assertPresent('.search-result')
                ->assertPresent('.result-title')
                ->assertPresent('.result-snippet')
                ->assertPresent('.result-metadata');
        });
    }

    /**
     * Test search with advanced filters
     *
     * Verifies:
     * - Filter options display
     * - Date range filtering
     * - Jurisdiction filtering
     * - Result type filtering
     * - Multiple filters combined
     *
     * @group search
     */
    public function test_search_with_filters(): void
    {
        // Create diverse content for filtering
        Law::factory()->create([
            'title' => 'Recent Croatian Law',
            'content' => 'Criminal procedure provisions',
            'jurisdiction' => 'Croatia',
            'promulgation_date' => now()->subDays(30),
        ]);

        Law::factory()->create([
            'title' => 'Old Croatian Law',
            'content' => 'Criminal procedure provisions',
            'jurisdiction' => 'Croatia',
            'promulgation_date' => now()->subYears(5),
        ]);

        Law::factory()->create([
            'title' => 'Serbian Law',
            'content' => 'Criminal procedure provisions',
            'jurisdiction' => 'Serbia',
            'promulgation_date' => now()->subDays(60),
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/search')

                // Enter search query
                ->type('query', 'criminal procedure')
                ->check('corpora[]', 'laws')
                ->press('Search')
                ->waitForText('Results', 10)

                // Should show all 3 results initially
                ->assertSeeIn('.result-count', '3 results')

                // Open filters panel
                ->click('.filters-toggle')
                ->waitFor('.filters-panel', 3)

                // Apply jurisdiction filter
                ->select('filter_jurisdiction', 'Croatia')
                ->press('Apply Filters')
                ->waitFor('.search-result', 5)

                // Should show only Croatian laws (2 results)
                ->assertSeeIn('.result-count', '2 results')
                ->assertSee('Recent Croatian Law')
                ->assertSee('Old Croatian Law')
                ->assertDontSee('Serbian Law')

                // Add date range filter (last 60 days)
                ->click('.filters-toggle')
                ->waitFor('.filters-panel', 3)
                ->type('date_from', now()->subDays(60)->format('Y-m-d'))
                ->type('date_to', now()->format('Y-m-d'))
                ->press('Apply Filters')
                ->waitFor('.search-result', 5)

                // Should show only recent Croatian law (1 result)
                ->assertSeeIn('.result-count', '1 result')
                ->assertSee('Recent Croatian Law')
                ->assertDontSee('Old Croatian Law')

                // Verify active filters displayed
                ->assertPresent('.active-filter-jurisdiction')
                ->assertPresent('.active-filter-date')
                ->assertSeeIn('.active-filters', 'Croatia')

                // Test clear filters
                ->click('.clear-filters')
                ->waitFor('.search-result', 5)

                // Should show all results again (3 results)
                ->assertSeeIn('.result-count', '3 results')

                // Test filter combinations
                ->click('.filters-toggle')
                ->waitFor('.filters-panel', 3)
                ->select('filter_jurisdiction', 'Croatia')
                ->check('filter_recent_only') // Last 90 days
                ->press('Apply Filters')
                ->waitFor('.search-result', 5)

                // Should show both recent Croatian laws (2 results)
                ->assertSeeIn('.result-count', '2 results');
        });
    }

    /**
     * Test search result pagination
     *
     * Verifies:
     * - Pagination controls display
     * - Page navigation works
     * - Result counts per page
     * - Page numbers accurate
     * - Next/Previous buttons
     *
     * @group search
     */
    public function test_search_pagination(): void
    {
        // Create many results to trigger pagination (25 laws)
        Law::factory()->count(25)->create([
            'content' => 'Criminal law provisions and regulations',
            'jurisdiction' => 'Croatia',
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/search')

                // Perform search
                ->type('query', 'criminal law')
                ->check('corpora[]', 'laws')
                ->press('Search')
                ->waitForText('Results', 10)

                // Verify total results
                ->assertSeeIn('.total-results', '25 results')

                // Verify pagination controls present
                ->assertPresent('.pagination')
                ->assertPresent('.page-number')

                // Default: 10 results per page
                ->assertSeeIn('.showing-count', 'Showing 1-10 of 25')

                // Verify 10 results displayed on page 1
                ->assertPresent('.search-result:nth-child(1)')
                ->assertPresent('.search-result:nth-child(10)')
                ->assertMissing('.search-result:nth-child(11)')

                // Test next page button
                ->click('.next-page')
                ->waitFor('.search-result', 5)

                // Should show results 11-20
                ->assertSeeIn('.showing-count', 'Showing 11-20 of 25')

                // Verify page 2 results displayed
                ->assertPresent('.search-result:nth-child(1)')
                ->assertPresent('.search-result:nth-child(10)')

                // Test page number link
                ->click('.page-link[data-page="3"]')
                ->waitFor('.search-result', 5)

                // Should show results 21-25
                ->assertSeeIn('.showing-count', 'Showing 21-25 of 25')

                // Only 5 results on last page
                ->assertPresent('.search-result:nth-child(5)')
                ->assertMissing('.search-result:nth-child(6)')

                // Test previous page button
                ->click('.prev-page')
                ->waitFor('.search-result', 5)

                // Back to page 2
                ->assertSeeIn('.showing-count', 'Showing 11-20 of 25')

                // Test results per page selector
                ->select('results_per_page', '25')
                ->waitFor('.search-result', 5)

                // Should show all results on one page
                ->assertSeeIn('.showing-count', 'Showing 1-25 of 25')
                ->assertPresent('.search-result:nth-child(25)')

                // Pagination should disappear with all results on one page
                ->assertMissing('.next-page')

                // Test jump to first page
                ->select('results_per_page', '10')
                ->waitFor('.search-result', 5)
                ->click('.page-link[data-page="3"]')
                ->waitFor('.search-result', 5)
                ->click('.first-page')
                ->waitFor('.search-result', 5)
                ->assertSeeIn('.showing-count', 'Showing 1-10 of 25');
        });
    }

    /**
     * Test search result preview functionality
     *
     * Verifies:
     * - Result preview modal opens
     * - Full content displayed
     * - Preview navigation works
     * - Quick actions available
     * - Preview closes correctly
     *
     * @group search
     */
    public function test_search_result_preview(): void
    {
        $law = Law::factory()->create([
            'title' => 'Croatian Criminal Code - Article 87',
            'content' => 'Kazneni zakon Republike Hrvatske. Članak 87. General provisions on criminal liability. '.
                'A person who commits a criminal offense shall be criminally liable if they were of sound mind '.
                'at the time of committing the offense and acted with intent or negligence.',
            'law_number' => 'NN 125/11',
            'jurisdiction' => 'Croatia',
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/search')

                // Perform search
                ->type('query', 'criminal liability')
                ->check('corpora[]', 'laws')
                ->press('Search')
                ->waitForText('Results', 10)

                // Verify search result displayed
                ->assertSee('Croatian Criminal Code - Article 87')

                // Click preview button on result
                ->click('.preview-result:first-child')
                ->waitFor('.result-preview-modal', 5)

                // Verify modal opened
                ->assertSee('Preview')

                // Verify full content displayed
                ->assertSee('Kazneni zakon Republike Hrvatske')
                ->assertSee('Članak 87')
                ->assertSee('General provisions on criminal liability')

                // Verify search terms highlighted in preview
                ->assertPresent('.preview-highlight')

                // Verify metadata in preview
                ->assertSee('NN 125/11')
                ->assertSee('Croatia')
                ->assertSee('Law')

                // Verify quick actions present
                ->assertPresent('.action-copy')
                ->assertPresent('.action-cite')
                ->assertPresent('.action-open-full')

                // Test copy to clipboard
                ->click('.action-copy')
                ->waitFor('.copy-success', 2)
                ->assertSee('Copied to clipboard')

                // Test cite action
                ->click('.action-cite')
                ->waitFor('.citation-modal', 3)
                ->assertSee('Citation')
                ->assertSee('NN 125/11')

                // Close citation modal
                ->click('.close-citation')
                ->waitUntilMissing('.citation-modal', 2)

                // Test navigation between results in preview
                ->assertPresent('.preview-next')

                // Close preview modal
                ->click('.close-preview')
                ->waitUntilMissing('.result-preview-modal', 3)
                ->assertMissing('.result-preview-modal')

                // Test keyboard shortcut to open preview
                ->keys('.search-result:first-child', '{enter}')
                ->waitFor('.result-preview-modal', 5)
                ->assertSee('Preview')

                // Test escape key to close preview
                ->keys('body', '{escape}')
                ->waitUntilMissing('.result-preview-modal', 3);
        });
    }

    /**
     * Test multi-corpus search functionality
     *
     * Verifies:
     * - Multiple corpora can be selected
     * - Results from different sources
     * - Corpus-specific result formatting
     * - Result grouping by corpus
     * - Cross-corpus relevance ranking
     *
     * @group search
     */
    public function test_multi_corpus_search(): void
    {
        // Create content in multiple corpora
        Law::factory()->create([
            'title' => 'Criminal Procedure Act',
            'content' => 'Regulations on home search warrants',
            'jurisdiction' => 'Croatia',
        ]);

        CourtDecision::factory()->create([
            'title' => 'Supreme Court Decision on Home Search',
            'description' => 'Analysis of home search warrant requirements and proportionality',
            'court' => 'Vrhovni sud RH',
        ]);

        CaseDocument::factory()->create([
            'title' => 'Defense Motion - Home Search Warrant',
            'content' => 'Motion challenging the validity of home search warrant',
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/search')

                // Select multiple corpora
                ->type('query', 'home search warrant')
                ->check('corpora[]', 'laws')
                ->check('corpora[]', 'court_decisions')
                ->check('corpora[]', 'case_documents')

                // Verify all corpora checked
                ->assertChecked('corpora[]', 'laws')
                ->assertChecked('corpora[]', 'court_decisions')
                ->assertChecked('corpora[]', 'case_documents')

                // Submit multi-corpus search
                ->press('Search')
                ->waitForText('Results', 10)

                // Verify results from all 3 corpora (3 results)
                ->assertSeeIn('.result-count', '3 results')

                // Verify results from different corpora displayed
                ->assertSee('Criminal Procedure Act')
                ->assertSee('Supreme Court Decision')
                ->assertSee('Defense Motion')

                // Verify corpus badges/labels on results
                ->assertPresent('.corpus-badge-law')
                ->assertPresent('.corpus-badge-decision')
                ->assertPresent('.corpus-badge-document')

                // Test grouping by corpus view
                ->click('.view-grouped')
                ->waitFor('.corpus-group', 3)

                // Verify results grouped by corpus
                ->assertPresent('.corpus-group-laws')
                ->assertPresent('.corpus-group-decisions')
                ->assertPresent('.corpus-group-documents')

                // Verify group headers with counts
                ->assertSeeIn('.corpus-group-laws .group-header', 'Laws (1)')
                ->assertSeeIn('.corpus-group-decisions .group-header', 'Court Decisions (1)')
                ->assertSeeIn('.corpus-group-documents .group-header', 'Case Documents (1)')

                // Test collapsing/expanding groups
                ->click('.corpus-group-laws .toggle-group')
                ->waitUntilMissing('.corpus-group-laws .search-result', 2)
                ->assertMissing('.corpus-group-laws .search-result')

                // Expand group again
                ->click('.corpus-group-laws .toggle-group')
                ->waitFor('.corpus-group-laws .search-result', 2)
                ->assertPresent('.corpus-group-laws .search-result')

                // Switch back to unified view
                ->click('.view-unified')
                ->waitFor('.search-result', 3)

                // Results should be interleaved by relevance
                ->assertPresent('.search-result:nth-child(1)')
                ->assertPresent('.search-result:nth-child(2)')
                ->assertPresent('.search-result:nth-child(3)')

                // Test corpus filter in results
                ->click('.filter-corpus-laws')
                ->waitFor('.search-result', 3)

                // Should show only law results (1 result)
                ->assertSeeIn('.result-count', '1 result')
                ->assertSee('Criminal Procedure Act')
                ->assertDontSee('Supreme Court Decision')

                // Clear corpus filter
                ->click('.clear-corpus-filter')
                ->waitFor('.search-result', 3)
                ->assertSeeIn('.result-count', '3 results')

                // Test "select all corpora" shortcut
                ->visit('/search')
                ->type('query', 'home search')
                ->click('.select-all-corpora')

                // All corpus checkboxes should be checked
                ->assertChecked('corpora[]', 'laws')
                ->assertChecked('corpora[]', 'court_decisions')
                ->assertChecked('corpora[]', 'case_documents');
        });
    }
}

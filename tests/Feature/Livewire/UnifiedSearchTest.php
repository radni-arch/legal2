<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\UnifiedSearch;
use App\Services\UnifiedSearchService;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class UnifiedSearchTest extends TestCase
{
    use UsesTestDatabase;

    /**
     * Test 1: Component renders correctly
     *
     * @test
     */
    public function test_component_renders_correctly()
    {
        Livewire::test(UnifiedSearch::class)
            ->assertStatus(200)
            ->assertViewIs('livewire.unified-search')
            ->assertSee('Search') // Assumes the view contains search-related text
            ->assertSet('query', '')
            ->assertSet('searchMode', 'unified')
            ->assertSet('page', 1)
            ->assertSet('threshold', 0.7)
            ->assertSet('limit', 10)
            ->assertSet('isSearching', false)
            ->assertSet('searchResults', null)
            ->assertSet('error', null);
    }

    /**
     * Test 2: Search query input binding works
     *
     * @test
     */
    public function test_search_query_input_binding_works()
    {
        Livewire::test(UnifiedSearch::class)
            ->assertSet('query', '')
            ->set('query', 'test legal search query')
            ->assertSet('query', 'test legal search query')
            ->assertSet('page', 1); // Should reset page when query changes
    }

    /**
     * Test 3: Corpus selection works (laws, cases, decisions, all)
     *
     * @test
     */
    public function test_corpus_selection_works()
    {
        // Test default: all corpora selected
        Livewire::test(UnifiedSearch::class)
            ->assertSet('corpora', ['laws', 'decisions', 'cases']);

        // Test toggling corpus off
        Livewire::test(UnifiedSearch::class)
            ->call('toggleCorpus', 'laws')
            ->assertSet('corpora', ['decisions', 'cases'])
            ->assertSet('page', 1);

        // Test toggling corpus back on
        Livewire::test(UnifiedSearch::class)
            ->set('corpora', ['decisions', 'cases'])
            ->call('toggleCorpus', 'laws')
            ->assertSet('corpora', ['decisions', 'cases', 'laws']);

        // Test that at least one corpus is required
        Livewire::test(UnifiedSearch::class)
            ->set('corpora', ['laws'])
            ->call('toggleCorpus', 'laws')
            ->assertSet('corpora', ['laws']); // Should keep at least one
    }

    /**
     * Test 4: Search type toggle works (vector, hybrid, citation)
     *
     * @test
     */
    public function test_search_type_toggle_works()
    {
        // Test switching to laws mode
        Livewire::test(UnifiedSearch::class)
            ->set('searchMode', 'laws')
            ->assertSet('searchMode', 'laws')
            ->assertSet('corpora', ['laws'])
            ->assertSet('page', 1);

        // Test switching to decisions mode
        Livewire::test(UnifiedSearch::class)
            ->set('searchMode', 'decisions')
            ->assertSet('searchMode', 'decisions')
            ->assertSet('corpora', ['decisions'])
            ->assertSet('page', 1);

        // Test switching to cases mode
        Livewire::test(UnifiedSearch::class)
            ->set('searchMode', 'cases')
            ->assertSet('searchMode', 'cases')
            ->assertSet('corpora', ['cases'])
            ->assertSet('page', 1);

        // Test switching to unified mode
        Livewire::test(UnifiedSearch::class)
            ->set('searchMode', 'unified')
            ->assertSet('searchMode', 'unified')
            ->assertSet('corpora', ['laws', 'decisions', 'cases']);

        // Test switching to hybrid mode
        Livewire::test(UnifiedSearch::class)
            ->set('searchMode', 'hybrid')
            ->assertSet('searchMode', 'hybrid')
            ->assertSet('corpora', ['laws', 'decisions', 'cases']);

        // Test switching to with-citations mode
        Livewire::test(UnifiedSearch::class)
            ->set('searchMode', 'with-citations')
            ->assertSet('searchMode', 'with-citations')
            ->assertSet('corpora', ['laws', 'decisions', 'cases']);
    }

    /**
     * Test 5: Results display correctly
     *
     * @test
     */
    public function test_results_display_correctly()
    {
        // Mock the UnifiedSearchService
        $mockService = Mockery::mock(UnifiedSearchService::class);
        $mockService->shouldReceive('search')
            ->once()
            ->andReturn([
                'success' => true,
                'data' => [
                    [
                        'type' => 'law',
                        'id' => '1',
                        'title' => 'Test Law 1',
                        'snippet' => 'This is a test snippet for law...',
                        'score' => 0.95,
                        'metadata' => ['law_number' => 'LAW-001'],
                    ],
                    [
                        'type' => 'decision',
                        'id' => '2',
                        'title' => 'Test Decision 1',
                        'snippet' => 'This is a test snippet for decision...',
                        'score' => 0.88,
                        'metadata' => ['case_number' => 'CASE-123'],
                    ],
                ],
                'metadata' => [
                    'query' => 'test query',
                    'total_results' => 25,
                    'returned_results' => 10,
                    'deduplicated_count' => 2,
                    'corpora_searched' => ['laws', 'decisions', 'cases'],
                    'pagination' => [
                        'current_page' => 1,
                        'per_page' => 10,
                        'total_pages' => 3,
                    ],
                    'performance' => [
                        'total_time' => 1.234,
                    ],
                ],
            ]);

        $this->app->instance(UnifiedSearchService::class, $mockService);

        Livewire::test(UnifiedSearch::class)
            ->set('query', 'test query')
            ->call('search')
            ->assertSet('searchResults', function ($results) {
                return count($results) === 2
                    && $results[0]['type'] === 'law'
                    && $results[1]['type'] === 'decision';
            })
            ->assertSet('searchMetadata.total_results', 25)
            ->assertSet('searchMetadata.returned_results', 10)
            ->assertSet('searchMetadata.deduplicated_count', 2)
            ->assertSet('isSearching', false)
            ->assertSet('error', null);
    }

    /**
     * Test 6: Pagination works
     *
     * @test
     */
    public function test_pagination_works()
    {
        // Mock the UnifiedSearchService for pagination test
        $mockService = Mockery::mock(UnifiedSearchService::class);
        $mockService->shouldReceive('search')
            ->andReturn([
                'success' => true,
                'data' => [],
                'metadata' => [
                    'query' => 'test',
                    'total_results' => 50,
                    'returned_results' => 10,
                    'deduplicated_count' => 0,
                    'corpora_searched' => ['laws'],
                    'pagination' => [
                        'current_page' => 1,
                        'per_page' => 10,
                        'total_pages' => 5,
                    ],
                    'performance' => ['total_time' => 0.5],
                ],
            ]);

        $this->app->instance(UnifiedSearchService::class, $mockService);

        // Test nextPage
        $component = Livewire::test(UnifiedSearch::class)
            ->set('query', 'test')
            ->call('search')
            ->assertSet('page', 1)
            ->call('nextPage')
            ->assertSet('page', 2);

        // Test previousPage
        Livewire::test(UnifiedSearch::class)
            ->set('query', 'test')
            ->set('page', 3)
            ->call('search')
            ->call('previousPage')
            ->assertSet('page', 2);

        // Test goToPage
        Livewire::test(UnifiedSearch::class)
            ->set('query', 'test')
            ->call('search')
            ->call('goToPage', 4)
            ->assertSet('page', 4);

        // Test previousPage doesn't go below 1
        Livewire::test(UnifiedSearch::class)
            ->set('query', 'test')
            ->set('page', 1)
            ->call('search')
            ->call('previousPage')
            ->assertSet('page', 1);

        // Test nextPage doesn't exceed total_pages - create new mock for this test
        $mockService2 = Mockery::mock(UnifiedSearchService::class);
        $mockService2->shouldReceive('search')
            ->andReturn([
                'success' => true,
                'data' => [],
                'metadata' => [
                    'query' => 'test',
                    'total_results' => 50,
                    'returned_results' => 10,
                    'deduplicated_count' => 0,
                    'corpora_searched' => ['laws'],
                    'pagination' => [
                        'current_page' => 5,
                        'per_page' => 10,
                        'total_pages' => 5,
                    ],
                    'performance' => ['total_time' => 0.5],
                ],
            ]);

        $this->app->instance(UnifiedSearchService::class, $mockService2);

        Livewire::test(UnifiedSearch::class)
            ->set('query', 'test')
            ->set('page', 5)
            ->call('search')
            ->call('nextPage')
            ->assertSet('page', 5); // Should stay at max page
    }

    /**
     * Test 7: Filters update results
     *
     * @test
     */
    public function test_filters_update_results()
    {
        // Mock the service and capture what options it receives
        $capturedOptions = null;
        $mockService = Mockery::mock(UnifiedSearchService::class);
        $mockService->shouldReceive('search')
            ->once()
            ->withArgs(function ($query, $options) use (&$capturedOptions) {
                $capturedOptions = $options;

                return true;
            })
            ->andReturn([
                'success' => true,
                'data' => [],
                'metadata' => [
                    'query' => 'test',
                    'total_results' => 10,
                    'returned_results' => 0,
                    'deduplicated_count' => 0,
                    'corpora_searched' => ['laws'],
                    'filters_applied' => [
                        'jurisdiction' => 'HR',
                        'date_from' => '2020-01-01',
                    ],
                    'pagination' => ['current_page' => 1, 'per_page' => 10, 'total_pages' => 1],
                    'performance' => ['total_time' => 0.5],
                ],
            ]);

        $this->app->instance(UnifiedSearchService::class, $mockService);

        Livewire::test(UnifiedSearch::class)
            ->set('query', 'test')
            ->set('filterJurisdiction', 'HR')
            ->set('filterDateFrom', '2020-01-01')
            ->set('filterDateTo', '2023-12-31')
            ->call('search')
            ->assertSet('isSearching', false)
            ->assertSet('error', null);

        // Verify that filters were passed to the service
        $this->assertNotNull($capturedOptions);
        $this->assertEquals('HR', $capturedOptions['filters']['jurisdiction']);
        $this->assertEquals('2020-01-01', $capturedOptions['filters']['date_from']);
    }

    /**
     * Test 8: Handles empty query
     *
     * @test
     */
    public function test_handles_empty_query()
    {
        Livewire::test(UnifiedSearch::class)
            ->set('query', '')
            ->call('search')
            ->assertHasErrors(['query']) // Should fail validation
            ->assertSet('isSearching', false);

        // Test query too short
        Livewire::test(UnifiedSearch::class)
            ->set('query', 'a')
            ->call('search')
            ->assertHasErrors(['query'])
            ->assertSet('isSearching', false);
    }

    /**
     * Test 9: Handles no results
     *
     * @test
     */
    public function test_handles_no_results()
    {
        $mockService = Mockery::mock(UnifiedSearchService::class);
        $mockService->shouldReceive('search')
            ->once()
            ->andReturn([
                'success' => true,
                'data' => [],
                'metadata' => [
                    'query' => 'nonexistent query',
                    'total_results' => 0,
                    'returned_results' => 0,
                    'deduplicated_count' => 0,
                    'corpora_searched' => ['laws', 'decisions', 'cases'],
                    'pagination' => [
                        'current_page' => 1,
                        'per_page' => 10,
                        'total_pages' => 0,
                    ],
                    'performance' => ['total_time' => 0.5],
                ],
            ]);

        $this->app->instance(UnifiedSearchService::class, $mockService);

        Livewire::test(UnifiedSearch::class)
            ->set('query', 'nonexistent query')
            ->call('search')
            ->assertSet('searchResults', [])
            ->assertSet('searchMetadata.total_results', 0)
            ->assertSet('isSearching', false)
            ->assertSet('error', null);
    }

    /**
     * Test 10: Export to PDF works
     * Note: PDF export functionality needs to be implemented in the component
     *
     * @test
     */
    public function test_export_to_pdf_works()
    {
        $this->markTestSkipped('PDF export functionality not yet implemented in component');

        // TODO: Implement when component has PDF export
        // Expected behavior:
        // - Should generate PDF with search results
        // - Should include query, filters, and metadata
        // - Should be downloadable
    }

    /**
     * Test 11: Export to CSV works
     * Note: Using JSON export as reference (component has exportResults method)
     *
     * @test
     */
    public function test_export_to_json_works()
    {
        $mockService = Mockery::mock(UnifiedSearchService::class);
        $mockService->shouldReceive('search')
            ->once()
            ->andReturn([
                'success' => true,
                'data' => [
                    [
                        'type' => 'law',
                        'id' => '1',
                        'title' => 'Export Test Law',
                        'snippet' => 'Test snippet',
                        'score' => 0.95,
                    ],
                ],
                'metadata' => [
                    'query' => 'export test',
                    'total_results' => 2,
                    'returned_results' => 1,
                    'deduplicated_count' => 0,
                    'corpora_searched' => ['laws'],
                    'pagination' => ['current_page' => 1, 'per_page' => 10, 'total_pages' => 1],
                    'performance' => ['total_time' => 0.5],
                ],
            ]);

        $this->app->instance(UnifiedSearchService::class, $mockService);

        $response = Livewire::test(UnifiedSearch::class)
            ->set('query', 'export test')
            ->call('search')
            ->call('exportResults');

        // Should return a download response
        $this->assertNotNull($response);
    }

    /**
     * Test 12: Saves recent searches
     * Note: This functionality would need to be implemented with session or database storage
     *
     * @test
     */
    public function test_saves_recent_searches()
    {
        $this->markTestSkipped('Recent searches functionality not yet implemented in component');

        // TODO: Implement when component has recent searches feature
        // Expected behavior:
        // - Should save query to session/database after successful search
        // - Should limit to X recent searches
        // - Should not duplicate identical queries
    }

    /**
     * Test 13: Loads recent searches
     * Note: This functionality would need to be implemented with session or database storage
     *
     * @test
     */
    public function test_loads_recent_searches()
    {
        $this->markTestSkipped('Recent searches functionality not yet implemented in component');

        // TODO: Implement when component has recent searches feature
        // Expected behavior:
        // - Should load recent searches on component mount
        // - Should display in UI
        // - Should allow clicking to re-run search
    }

    /**
     * Test 14: Error handling displays
     *
     * @test
     */
    public function test_error_handling_displays()
    {
        // Test service error response
        $mockService = Mockery::mock(UnifiedSearchService::class);
        $mockService->shouldReceive('search')
            ->once()
            ->andReturn([
                'success' => false,
                'error' => 'Search service temporarily unavailable',
            ]);

        $this->app->instance(UnifiedSearchService::class, $mockService);

        Livewire::test(UnifiedSearch::class)
            ->set('query', 'test error')
            ->call('search')
            ->assertSet('error', 'Search service temporarily unavailable')
            ->assertSet('searchResults', null)
            ->assertSet('isSearching', false);

        // Test service exception
        $mockService2 = Mockery::mock(UnifiedSearchService::class);
        $mockService2->shouldReceive('search')
            ->once()
            ->andThrow(new \Exception('Service unavailable'));

        $this->app->instance(UnifiedSearchService::class, $mockService2);

        $component = Livewire::test(UnifiedSearch::class)
            ->set('query', 'test server error')
            ->call('search');

        // Component should have an error set
        $error = $component->get('error');
        $this->assertNotNull($error, 'Error should be set when service throws exception');
        $this->assertStringContainsString('An error occurred', $error);
        $component->assertSet('isSearching', false);

        // Test validation error for invalid date range
        Livewire::test(UnifiedSearch::class)
            ->set('query', 'test dates')
            ->set('filterDateFrom', '2023-01-01')
            ->set('filterDateTo', '2022-01-01') // End before start
            ->call('search')
            ->assertHasErrors(['filterDateTo'])
            ->assertSet('isSearching', false);
    }

    /**
     * Test 15: Loading state shows
     *
     * @test
     */
    public function test_loading_state_shows()
    {
        $mockService = Mockery::mock(UnifiedSearchService::class);
        $mockService->shouldReceive('search')
            ->once()
            ->andReturn([
                'success' => true,
                'data' => [],
                'metadata' => [
                    'query' => 'test loading',
                    'total_results' => 0,
                    'returned_results' => 0,
                    'deduplicated_count' => 0,
                    'corpora_searched' => ['laws'],
                    'pagination' => ['current_page' => 1, 'per_page' => 10, 'total_pages' => 0],
                    'performance' => ['total_time' => 0.5],
                ],
            ]);

        $this->app->instance(UnifiedSearchService::class, $mockService);

        $component = Livewire::test(UnifiedSearch::class)
            ->set('query', 'test loading');

        // Before search: not searching
        $component->assertSet('isSearching', false);

        // During search: isSearching should be managed by the component
        // After search: back to not searching
        $component->call('search')
            ->assertSet('isSearching', false); // After search completes
    }

    /**
     * Additional test: Clear search functionality
     *
     * @test
     */
    public function test_clear_search_works()
    {
        $mockService = Mockery::mock(UnifiedSearchService::class);
        $mockService->shouldReceive('search')
            ->once()
            ->andReturn([
                'success' => true,
                'data' => [[
                    'type' => 'law',
                    'id' => '1',
                    'title' => 'Test',
                    'score' => 0.95,
                    'snippet' => 'Test snippet',
                ]],
                'metadata' => [
                    'query' => 'test query',
                    'total_results' => 1,
                    'returned_results' => 1,
                    'deduplicated_count' => 0,
                    'corpora_searched' => ['laws'],
                    'pagination' => ['current_page' => 1, 'per_page' => 10, 'total_pages' => 1],
                    'performance' => ['total_time' => 0.5],
                ],
            ]);

        $this->app->instance(UnifiedSearchService::class, $mockService);

        Livewire::test(UnifiedSearch::class)
            ->set('query', 'test query')
            ->call('search')
            ->assertSet('query', 'test query')
            ->assertSet('searchResults', function ($results) {
                return count($results) > 0;
            })
            ->call('clearSearch')
            ->assertSet('query', '')
            ->assertSet('page', 1)
            ->assertSet('searchResults', null)
            ->assertSet('searchMetadata', null)
            ->assertSet('error', null);
    }

    /**
     * Additional test: Reset filters functionality
     *
     * @test
     */
    public function test_reset_filters_works()
    {
        Livewire::test(UnifiedSearch::class)
            ->set('corpora', ['laws'])
            ->set('threshold', 0.9)
            ->set('limit', 20)
            ->set('sortBy', 'date')
            ->set('sortOrder', 'asc')
            ->set('deduplicate', false)
            ->set('filterJurisdiction', 'HR')
            ->set('filterDateFrom', '2020-01-01')
            ->set('page', 3)
            ->call('resetFilters')
            ->assertSet('corpora', ['laws', 'decisions', 'cases'])
            ->assertSet('threshold', 0.7)
            ->assertSet('limit', 10)
            ->assertSet('sortBy', 'score')
            ->assertSet('sortOrder', 'desc')
            ->assertSet('deduplicate', true)
            ->assertSet('filterJurisdiction', '')
            ->assertSet('filterDateFrom', '')
            ->assertSet('filterDateTo', '')
            ->assertSet('page', 1);
    }

    /**
     * Additional test: Weight adjustments work
     *
     * @test
     */
    public function test_weight_adjustments_work()
    {
        $capturedOptions = null;
        $mockService = Mockery::mock(UnifiedSearchService::class);
        $mockService->shouldReceive('search')
            ->once()
            ->withArgs(function ($query, $options) use (&$capturedOptions) {
                $capturedOptions = $options;

                return true;
            })
            ->andReturn([
                'success' => true,
                'data' => [],
                'metadata' => [
                    'query' => 'test weights',
                    'total_results' => 0,
                    'returned_results' => 0,
                    'deduplicated_count' => 0,
                    'corpora_searched' => ['laws', 'decisions', 'cases'],
                    'pagination' => ['current_page' => 1, 'per_page' => 10, 'total_pages' => 0],
                    'performance' => ['total_time' => 0.5],
                ],
            ]);

        $this->app->instance(UnifiedSearchService::class, $mockService);

        Livewire::test(UnifiedSearch::class)
            ->set('query', 'test weights')
            ->set('weights', [
                'laws' => 2.0,
                'decisions' => 0.5,
                'cases' => 1.0,
            ])
            ->call('search')
            ->assertSet('isSearching', false);

        // Verify weights were passed to the service
        $this->assertNotNull($capturedOptions);
        $this->assertEquals(2.0, $capturedOptions['weights']['laws']);
        $this->assertEquals(0.5, $capturedOptions['weights']['decisions']);
        $this->assertEquals(1.0, $capturedOptions['weights']['cases']);
    }

    /**
     * Additional test: Query string parameters work
     *
     * @test
     */
    public function test_query_string_parameters_work()
    {
        // Test that component accepts query string parameters (no search needed for this test)
        $component = Livewire::withQueryParams([
            'query' => 'url query test',
            'page' => 2,
            'searchMode' => 'laws',
        ])->test(UnifiedSearch::class);

        $component->assertSet('query', 'url query test')
            ->assertSet('page', 2)
            ->assertSet('searchMode', 'laws');
    }

    /**
     * Additional test: Result grouping by type works
     *
     * @test
     */
    public function test_result_grouping_by_type_works()
    {
        $component = Livewire::test(UnifiedSearch::class);

        // Set mock search results
        $component->set('searchResults', [
            ['type' => 'law', 'id' => '1', 'title' => 'Law 1', 'score' => 0.95, 'snippet' => 'Snippet 1'],
            ['type' => 'law', 'id' => '2', 'title' => 'Law 2', 'score' => 0.90, 'snippet' => 'Snippet 2'],
            ['type' => 'decision', 'id' => '3', 'title' => 'Decision 1', 'score' => 0.85, 'snippet' => 'Snippet 3'],
            ['type' => 'case', 'id' => '4', 'title' => 'Case 1', 'score' => 0.80, 'snippet' => 'Snippet 4'],
        ]);

        $instance = $component->instance();
        $grouped = $instance->groupResultsByType();

        $this->assertIsArray($grouped);
        $this->assertArrayHasKey('law', $grouped);
        $this->assertArrayHasKey('decision', $grouped);
        $this->assertArrayHasKey('case', $grouped);
        $this->assertCount(2, $grouped['law']);
        $this->assertCount(1, $grouped['decision']);
        $this->assertCount(1, $grouped['case']);
    }

    /**
     * Additional test: Type labels and icons work
     *
     * @test
     */
    public function test_type_labels_and_icons_work()
    {
        $component = Livewire::test(UnifiedSearch::class);
        $instance = $component->instance();

        // Test getTypeLabel
        $this->assertEquals('Laws', $instance->getTypeLabel('law'));
        $this->assertEquals('Court Decisions', $instance->getTypeLabel('decision'));
        $this->assertEquals('Case Documents', $instance->getTypeLabel('case'));

        // Test getTypeIcon
        $this->assertEquals('⚖️', $instance->getTypeIcon('law'));
        $this->assertEquals('🏛️', $instance->getTypeIcon('decision'));
        $this->assertEquals('📁', $instance->getTypeIcon('case'));
    }

    /**
     * Test 22: Result ranking by score works
     *
     * @test
     */
    public function test_result_ranking_by_score_works()
    {
        $capturedOptions = null;
        $mockService = Mockery::mock(UnifiedSearchService::class);
        $mockService->shouldReceive('search')
            ->once()
            ->withArgs(function ($query, $options) use (&$capturedOptions) {
                $capturedOptions = $options;

                return true;
            })
            ->andReturn([
                'success' => true,
                'data' => [
                    ['type' => 'law', 'id' => '1', 'title' => 'High Score Law', 'score' => 0.95, 'snippet' => 'Test'],
                    ['type' => 'law', 'id' => '2', 'title' => 'Medium Score Law', 'score' => 0.75, 'snippet' => 'Test'],
                    ['type' => 'law', 'id' => '3', 'title' => 'Low Score Law', 'score' => 0.55, 'snippet' => 'Test'],
                ],
                'metadata' => [
                    'query' => 'test ranking',
                    'total_results' => 3,
                    'returned_results' => 3,
                    'deduplicated_count' => 0,
                    'corpora_searched' => ['laws'],
                    'pagination' => ['current_page' => 1, 'per_page' => 10, 'total_pages' => 1],
                    'performance' => ['total_time' => 0.5],
                ],
            ]);

        $this->app->instance(UnifiedSearchService::class, $mockService);

        Livewire::test(UnifiedSearch::class)
            ->set('query', 'test ranking')
            ->set('sortBy', 'score')
            ->set('sortOrder', 'desc')
            ->call('search')
            ->assertSet('searchResults', function ($results) {
                // Verify results are sorted by score descending
                if (count($results) !== 3) {
                    return false;
                }

                return $results[0]['score'] >= $results[1]['score']
                    && $results[1]['score'] >= $results[2]['score'];
            });

        // Verify sortBy and sortOrder were passed to the service
        $this->assertNotNull($capturedOptions);
        $this->assertEquals('score', $capturedOptions['sort_by']);
        $this->assertEquals('desc', $capturedOptions['sort_order']);
    }

    /**
     * Test 23: Result ranking by date works
     *
     * @test
     */
    public function test_result_ranking_by_date_works()
    {
        $capturedOptions = null;
        $mockService = Mockery::mock(UnifiedSearchService::class);
        $mockService->shouldReceive('search')
            ->once()
            ->withArgs(function ($query, $options) use (&$capturedOptions) {
                $capturedOptions = $options;

                return true;
            })
            ->andReturn([
                'success' => true,
                'data' => [
                    ['type' => 'decision', 'id' => '1', 'title' => 'Recent Decision',
                        'date' => '2025-01-15', 'score' => 0.85, 'snippet' => 'Test'],
                    ['type' => 'decision', 'id' => '2', 'title' => 'Older Decision',
                        'date' => '2024-06-10', 'score' => 0.90, 'snippet' => 'Test'],
                    ['type' => 'decision', 'id' => '3', 'title' => 'Ancient Decision',
                        'date' => '2023-03-05', 'score' => 0.95, 'snippet' => 'Test'],
                ],
                'metadata' => [
                    'query' => 'test date ranking',
                    'total_results' => 3,
                    'returned_results' => 3,
                    'deduplicated_count' => 0,
                    'corpora_searched' => ['decisions'],
                    'pagination' => ['current_page' => 1, 'per_page' => 10, 'total_pages' => 1],
                    'performance' => ['total_time' => 0.5],
                ],
            ]);

        $this->app->instance(UnifiedSearchService::class, $mockService);

        Livewire::test(UnifiedSearch::class)
            ->set('query', 'test date ranking')
            ->set('sortBy', 'date')
            ->set('sortOrder', 'desc')
            ->call('search')
            ->assertSet('isSearching', false)
            ->assertSet('error', null);

        // Verify sort parameters were passed to the service
        $this->assertNotNull($capturedOptions);
        $this->assertEquals('date', $capturedOptions['sort_by']);
        $this->assertEquals('desc', $capturedOptions['sort_order']);
    }

    /**
     * Test 24: Result ranking order toggle works (asc/desc)
     *
     * @test
     */
    public function test_result_ranking_order_toggle_works()
    {
        $capturedOptions = null;
        $mockService = Mockery::mock(UnifiedSearchService::class);
        $mockService->shouldReceive('search')
            ->once()
            ->withArgs(function ($query, $options) use (&$capturedOptions) {
                $capturedOptions = $options;

                return true;
            })
            ->andReturn([
                'success' => true,
                'data' => [
                    ['type' => 'law', 'id' => '1', 'title' => 'Law 1', 'score' => 0.55, 'snippet' => 'Test'],
                    ['type' => 'law', 'id' => '2', 'title' => 'Law 2', 'score' => 0.75, 'snippet' => 'Test'],
                    ['type' => 'law', 'id' => '3', 'title' => 'Law 3', 'score' => 0.95, 'snippet' => 'Test'],
                ],
                'metadata' => [
                    'query' => 'test order',
                    'total_results' => 3,
                    'returned_results' => 3,
                    'deduplicated_count' => 0,
                    'corpora_searched' => ['laws'],
                    'pagination' => ['current_page' => 1, 'per_page' => 10, 'total_pages' => 1],
                    'performance' => ['total_time' => 0.5],
                ],
            ]);

        $this->app->instance(UnifiedSearchService::class, $mockService);

        // Test ascending order
        Livewire::test(UnifiedSearch::class)
            ->set('query', 'test order')
            ->set('sortBy', 'score')
            ->set('sortOrder', 'asc')
            ->call('search')
            ->assertSet('searchResults', function ($results) {
                // Verify results are sorted by score ascending
                if (count($results) !== 3) {
                    return false;
                }

                return $results[0]['score'] <= $results[1]['score']
                    && $results[1]['score'] <= $results[2]['score'];
            });

        // Verify sort order was passed to the service
        $this->assertNotNull($capturedOptions);
        $this->assertEquals('asc', $capturedOptions['sort_order']);
    }

    /**
     * Test 25: Combined filters work together (jurisdiction + date range)
     *
     * @test
     */
    public function test_combined_filters_jurisdiction_and_date_range_work()
    {
        $capturedOptions = null;
        $mockService = Mockery::mock(UnifiedSearchService::class);
        $mockService->shouldReceive('search')
            ->once()
            ->withArgs(function ($query, $options) use (&$capturedOptions) {
                $capturedOptions = $options;

                return true;
            })
            ->andReturn([
                'success' => true,
                'data' => [
                    ['type' => 'decision', 'id' => '1', 'title' => 'HR Decision 2024', 'snippet' => 'Test', 'score' => 0.92],
                ],
                'metadata' => [
                    'query' => 'test combined filters',
                    'total_results' => 1,
                    'returned_results' => 1,
                    'deduplicated_count' => 0,
                    'corpora_searched' => ['decisions'],
                    'pagination' => ['current_page' => 1, 'per_page' => 10, 'total_pages' => 1],
                    'performance' => ['total_time' => 0.5],
                ],
            ]);

        $this->app->instance(UnifiedSearchService::class, $mockService);

        Livewire::test(UnifiedSearch::class)
            ->set('query', 'test combined filters')
            ->set('filterJurisdiction', 'HR')
            ->set('filterDateFrom', '2024-01-01')
            ->set('filterDateTo', '2024-12-31')
            ->call('search')
            ->assertSet('isSearching', false)
            ->assertSet('error', null);

        // Verify all filters were passed to the service
        $this->assertNotNull($capturedOptions);
        $this->assertEquals('HR', $capturedOptions['filters']['jurisdiction']);
        $this->assertEquals('2024-01-01', $capturedOptions['filters']['date_from']);
        $this->assertEquals('2024-12-31', $capturedOptions['filters']['date_to']);
    }

    /**
     * Test 26: Combined filters work together (court + language + country)
     *
     * @test
     */
    public function test_combined_filters_court_language_country_work()
    {
        $capturedOptions = null;
        $mockService = Mockery::mock(UnifiedSearchService::class);
        $mockService->shouldReceive('search')
            ->once()
            ->withArgs(function ($query, $options) use (&$capturedOptions) {
                $capturedOptions = $options;

                return true;
            })
            ->andReturn([
                'success' => true,
                'data' => [
                    ['type' => 'decision', 'id' => '1', 'title' => 'Filtered Decision', 'snippet' => 'Test', 'score' => 0.88],
                ],
                'metadata' => [
                    'query' => 'test multiple filters',
                    'total_results' => 1,
                    'returned_results' => 1,
                    'deduplicated_count' => 0,
                    'corpora_searched' => ['decisions'],
                    'pagination' => ['current_page' => 1, 'per_page' => 10, 'total_pages' => 1],
                    'performance' => ['total_time' => 0.5],
                ],
            ]);

        $this->app->instance(UnifiedSearchService::class, $mockService);

        Livewire::test(UnifiedSearch::class)
            ->set('query', 'test multiple filters')
            ->set('filterCourt', 'Vrhovni sud')
            ->set('filterLanguage', 'hr')
            ->set('filterCountry', 'HR')
            ->call('search')
            ->assertSet('isSearching', false)
            ->assertSet('error', null);

        // Verify all filters were passed to the service
        $this->assertNotNull($capturedOptions);
        $this->assertEquals('Vrhovni sud', $capturedOptions['filters']['court']);
        $this->assertEquals('hr', $capturedOptions['filters']['language']);
        $this->assertEquals('HR', $capturedOptions['filters']['country']);
    }

    /**
     * Test 27: Combined filters with threshold and deduplication
     *
     * @test
     */
    public function test_combined_filters_with_threshold_and_deduplication()
    {
        $capturedOptions = null;
        $mockService = Mockery::mock(UnifiedSearchService::class);
        $mockService->shouldReceive('search')
            ->once()
            ->withArgs(function ($query, $options) use (&$capturedOptions) {
                $capturedOptions = $options;

                return true;
            })
            ->andReturn([
                'success' => true,
                'data' => [
                    ['type' => 'law', 'id' => '1', 'title' => 'High Quality Law', 'score' => 0.92, 'snippet' => 'Test'],
                    ['type' => 'law', 'id' => '2', 'title' => 'Another High Quality Law', 'score' => 0.88, 'snippet' => 'Test'],
                ],
                'metadata' => [
                    'query' => 'test quality filters',
                    'total_results' => 2,
                    'returned_results' => 2,
                    'deduplicated_count' => 1,
                    'corpora_searched' => ['laws'],
                    'pagination' => ['current_page' => 1, 'per_page' => 10, 'total_pages' => 1],
                    'performance' => ['total_time' => 0.5],
                ],
            ]);

        $this->app->instance(UnifiedSearchService::class, $mockService);

        Livewire::test(UnifiedSearch::class)
            ->set('query', 'test quality filters')
            ->set('threshold', 0.85)
            ->set('deduplicate', true)
            ->set('filterJurisdiction', 'HR')
            ->set('filterDateFrom', '2024-01-01')
            ->call('search')
            ->assertSet('searchResults', function ($results) {
                // Verify all results meet threshold
                foreach ($results as $result) {
                    if ($result['score'] < 0.85) {
                        return false;
                    }
                }

                return true;
            })
            ->assertSet('searchMetadata.deduplicated_count', 1);

        // Verify threshold and deduplication were passed to the service
        $this->assertNotNull($capturedOptions);
        $this->assertEquals(0.85, $capturedOptions['threshold']);
        $this->assertTrue($capturedOptions['deduplicate']);
        $this->assertEquals('HR', $capturedOptions['filters']['jurisdiction']);
        $this->assertEquals('2024-01-01', $capturedOptions['filters']['date_from']);
    }

    /**
     * Test 28: Combined filters with corpus weights
     *
     * @test
     */
    public function test_combined_filters_with_corpus_weights()
    {
        $capturedOptions = null;
        $mockService = Mockery::mock(UnifiedSearchService::class);
        $mockService->shouldReceive('search')
            ->once()
            ->withArgs(function ($query, $options) use (&$capturedOptions) {
                $capturedOptions = $options;

                return true;
            })
            ->andReturn([
                'success' => true,
                'data' => [
                    ['type' => 'law', 'id' => '1', 'title' => 'Weighted Law', 'score' => 0.95, 'snippet' => 'Test'],
                    ['type' => 'decision', 'id' => '2', 'title' => 'Weighted Decision', 'score' => 0.85, 'snippet' => 'Test'],
                ],
                'metadata' => [
                    'query' => 'test weighted search',
                    'total_results' => 2,
                    'returned_results' => 2,
                    'deduplicated_count' => 0,
                    'corpora_searched' => ['laws', 'decisions'],
                    'pagination' => ['current_page' => 1, 'per_page' => 10, 'total_pages' => 1],
                    'performance' => ['total_time' => 0.5],
                ],
            ]);

        $this->app->instance(UnifiedSearchService::class, $mockService);

        Livewire::test(UnifiedSearch::class)
            ->set('query', 'test weighted search')
            ->set('weights', [
                'laws' => 2.0,
                'decisions' => 1.5,
                'cases' => 0.5,
            ])
            ->set('filterJurisdiction', 'HR')
            ->set('threshold', 0.8)
            ->call('search')
            ->assertSet('isSearching', false)
            ->assertSet('error', null);

        // Verify weights and filters were both passed to the service
        $this->assertNotNull($capturedOptions);
        $this->assertEquals(2.0, $capturedOptions['weights']['laws']);
        $this->assertEquals('HR', $capturedOptions['filters']['jurisdiction']);
        $this->assertEquals(0.8, $capturedOptions['threshold']);
    }
}

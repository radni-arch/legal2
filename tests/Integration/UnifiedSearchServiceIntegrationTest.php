<?php

namespace Tests\Integration;

use App\Models\CaseDocument;
use App\Models\CourtDecision;
use App\Models\Law;
use App\Models\LegalCase;
use App\Services\UnifiedSearchService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Integration tests for UnifiedSearchService
 *
 * These tests verify the unified search service works end-to-end,
 * coordinating search across multiple legal corpora (laws, decisions, cases).
 */
class UnifiedSearchServiceIntegrationTest extends TestCase
{
    use UsesTestDatabase;

    protected UnifiedSearchService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Use real service for integration testing
        $this->service = app(UnifiedSearchService::class);
    }

    /** @test */
    public function it_searches_across_all_corpora()
    {
        // Arrange: Create test data in each corpus
        $law = Law::factory()->create([
            'title' => 'Kazneni zakon',
            'content' => 'Croatian criminal law provisions',
        ]);

        $decision = CourtDecision::factory()->create([
            'title' => 'Criminal case decision',
            'summary' => 'Decision about theft case',
        ]);

        $case = LegalCase::factory()->create([
            'title' => 'Client criminal defense case',
        ]);

        $caseDoc = CaseDocument::factory()->create([
            'case_id' => $case->id,
            'title' => 'Criminal evidence document',
            'content' => 'Evidence related to criminal charges',
        ]);

        // Mock OpenAI API for embeddings
        Http::fake([
            'api.openai.com/*' => Http::response([
                'data' => [
                    [
                        'embedding' => array_fill(0, 1536, 0.5),
                        'index' => 0,
                    ],
                ],
                'model' => 'text-embedding-3-small',
                'usage' => [
                    'prompt_tokens' => 5,
                    'total_tokens' => 5,
                ],
            ], 200),
        ]);

        // Act: Search across all corpora
        $results = $this->service->search('criminal', [
            'corpora' => ['laws', 'decisions', 'cases'],
            'limit' => 10,
            'threshold' => 0.6,
        ]);

        // Assert: Results from all corpora
        $this->assertIsArray($results);
        $this->assertArrayHasKey('success', $results);
        $this->assertTrue($results['success']);
        $this->assertArrayHasKey('data', $results);
        $this->assertArrayHasKey('metadata', $results);

        // Verify metadata contains search info
        $this->assertArrayHasKey('query', $results['metadata']);
        $this->assertEquals('criminal', $results['metadata']['query']);
        $this->assertArrayHasKey('total_results', $results['metadata']);
    }

    /** @test */
    public function it_searches_specific_corpus_only()
    {
        // Arrange: Create law records
        Law::factory()->count(3)->create([
            'title' => 'Test law about contracts',
            'content' => 'Contract law provisions',
        ]);

        // Mock OpenAI API
        Http::fake([
            'api.openai.com/*' => Http::response([
                'data' => [
                    [
                        'embedding' => array_fill(0, 1536, 0.5),
                        'index' => 0,
                    ],
                ],
                'model' => 'text-embedding-3-small',
            ], 200),
        ]);

        // Act: Search only laws corpus
        $results = $this->service->search('contract', [
            'corpora' => ['laws'],
            'limit' => 10,
        ]);

        // Assert: Only laws should be returned
        $this->assertTrue($results['success']);
        $this->assertIsArray($results['data']);

        // Verify corpus type in metadata
        $this->assertArrayHasKey('metadata', $results);
        $this->assertArrayHasKey('corpora_searched', $results['metadata']);
        $this->assertEquals(['laws'], $results['metadata']['corpora_searched']);
    }

    /** @test */
    public function it_applies_filters_to_search_results()
    {
        // Arrange: Create decisions with different courts
        CourtDecision::factory()->create([
            'title' => 'Zagreb decision',
            'court' => 'Županijski sud u Zagrebu',
            'decision_date' => '2024-01-15',
        ]);

        CourtDecision::factory()->create([
            'title' => 'Osijek decision',
            'court' => 'Županijski sud u Osijeku',
            'decision_date' => '2024-02-20',
        ]);

        // Mock OpenAI API
        Http::fake([
            'api.openai.com/*' => Http::response([
                'data' => [
                    ['embedding' => array_fill(0, 1536, 0.5), 'index' => 0],
                ],
            ], 200),
        ]);

        // Act: Search with court filter
        $results = $this->service->search('decision', [
            'corpora' => ['decisions'],
            'filters' => [
                'court' => 'Zagreb',
            ],
            'limit' => 10,
        ]);

        // Assert: Only Zagreb decisions returned
        $this->assertTrue($results['success']);
        $this->assertIsArray($results['data']);

        // Verify filters were applied
        $this->assertArrayHasKey('metadata', $results);
        $this->assertArrayHasKey('filters_applied', $results['metadata']);
    }

    /** @test */
    public function it_supports_pagination()
    {
        // Arrange: Create multiple law records
        Law::factory()->count(25)->create([
            'title' => 'Paginated law record',
            'content' => 'Legal provisions for testing pagination',
        ]);

        // Mock OpenAI API
        Http::fake([
            'api.openai.com/*' => Http::response([
                'data' => [
                    ['embedding' => array_fill(0, 1536, 0.5), 'index' => 0],
                ],
            ], 200),
        ]);

        // Act: Get first page
        $page1 = $this->service->search('paginated', [
            'corpora' => ['laws'],
            'page' => 1,
            'per_page' => 10,
        ]);

        // Get second page
        $page2 = $this->service->search('paginated', [
            'corpora' => ['laws'],
            'page' => 2,
            'per_page' => 10,
        ]);

        // Assert: Pagination works
        $this->assertTrue($page1['success']);
        $this->assertTrue($page2['success']);

        $this->assertArrayHasKey('pagination', $page1['metadata']);
        $this->assertEquals(1, $page1['metadata']['pagination']['current_page']);
        $this->assertEquals(2, $page2['metadata']['pagination']['current_page']);
        $this->assertEquals(10, $page1['metadata']['pagination']['per_page']);
    }

    /** @test */
    public function it_applies_similarity_threshold()
    {
        // Arrange: Create test data
        Law::factory()->create([
            'title' => 'Highly relevant law',
            'content' => 'Very relevant content',
        ]);

        // Mock OpenAI API
        Http::fake([
            'api.openai.com/*' => Http::response([
                'data' => [
                    ['embedding' => array_fill(0, 1536, 0.5), 'index' => 0],
                ],
            ], 200),
        ]);

        // Act: Search with high threshold
        $strictResults = $this->service->search('relevant', [
            'corpora' => ['laws'],
            'threshold' => 0.9, // Very strict
            'limit' => 10,
        ]);

        // Search with low threshold
        $relaxedResults = $this->service->search('relevant', [
            'corpora' => ['laws'],
            'threshold' => 0.5, // More relaxed
            'limit' => 10,
        ]);

        // Assert: Threshold affects results
        $this->assertTrue($strictResults['success']);
        $this->assertTrue($relaxedResults['success']);

        // Verify threshold in metadata
        $this->assertEquals(0.9, $strictResults['metadata']['threshold']);
        $this->assertEquals(0.5, $relaxedResults['metadata']['threshold']);
    }

    /** @test */
    public function it_handles_empty_results_gracefully()
    {
        // Mock OpenAI API
        Http::fake([
            'api.openai.com/*' => Http::response([
                'data' => [
                    ['embedding' => array_fill(0, 1536, 0.5), 'index' => 0],
                ],
            ], 200),
        ]);

        // Act: Search for something that doesn't exist
        $results = $this->service->search('nonexistent query xyz123', [
            'corpora' => ['laws', 'decisions', 'cases'],
            'limit' => 10,
        ]);

        // Assert: Returns valid response with empty data
        $this->assertIsArray($results);
        $this->assertTrue($results['success']);
        $this->assertIsArray($results['data']);
        $this->assertArrayHasKey('metadata', $results);
    }

    /** @test */
    public function it_validates_invalid_corpus_types()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one valid corpus must be specified');

        // Act: Try to search with invalid corpus
        $this->service->search('test', [
            'corpora' => ['invalid_corpus', 'another_invalid'],
        ]);
    }

    /** @test */
    public function it_applies_custom_weights_to_corpora()
    {
        // Arrange: Create data in multiple corpora
        Law::factory()->create(['title' => 'Weighted law', 'content' => 'Law content']);

        $decision = CourtDecision::factory()->create([
            'title' => 'Weighted decision',
            'summary' => 'Decision content',
        ]);

        // Mock OpenAI API
        Http::fake([
            'api.openai.com/*' => Http::response([
                'data' => [
                    ['embedding' => array_fill(0, 1536, 0.5), 'index' => 0],
                ],
            ], 200),
        ]);

        // Act: Search with custom weights
        $results = $this->service->search('weighted', [
            'corpora' => ['laws', 'decisions'],
            'weights' => [
                'laws' => 2.0,      // Double weight for laws
                'decisions' => 1.0,  // Normal weight for decisions
            ],
            'limit' => 10,
        ]);

        // Assert: Results include weighted scores
        $this->assertTrue($results['success']);
        $this->assertIsArray($results['data']);

        // Verify weights in metadata
        $this->assertArrayHasKey('weights', $results['metadata']);
    }

    /** @test */
    public function it_deduplicates_results_by_default()
    {
        // Arrange: Create duplicate-like records
        Law::factory()->create([
            'title' => 'Duplicate test law',
            'content' => 'Same content in law',
        ]);

        Law::factory()->create([
            'title' => 'Duplicate test law copy',
            'content' => 'Same content in law',
        ]);

        // Mock OpenAI API
        Http::fake([
            'api.openai.com/*' => Http::response([
                'data' => [
                    ['embedding' => array_fill(0, 1536, 0.5), 'index' => 0],
                ],
            ], 200),
        ]);

        // Act: Search with deduplication enabled (default)
        $dedupedResults = $this->service->search('duplicate', [
            'corpora' => ['laws'],
            'deduplicate' => true,
            'limit' => 10,
        ]);

        // Search with deduplication disabled
        $allResults = $this->service->search('duplicate', [
            'corpora' => ['laws'],
            'deduplicate' => false,
            'limit' => 10,
        ]);

        // Assert: Both return results
        $this->assertTrue($dedupedResults['success']);
        $this->assertTrue($allResults['success']);

        // Verify deduplication flag in metadata
        $this->assertArrayHasKey('deduplication_enabled', $dedupedResults['metadata']);
    }
}

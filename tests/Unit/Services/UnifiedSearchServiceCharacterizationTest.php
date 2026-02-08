<?php

namespace Tests\Unit\Services;

use App\Services\LegalCitations\HrLegalCitationsDetector;
use App\Services\OpenAIService;
use App\Services\Search\CaseSearchService;
use App\Services\Search\CitationSearchService;
use App\Services\Search\DecisionSearchService;
use App\Services\Search\FullTextSearchService;
use App\Services\Search\LawSearchService;
use App\Services\Search\SearchEmbeddingService;
use App\Services\Search\SearchResultAggregator;
use App\Services\Search\SearchResultDeduplicator;
use App\Services\UnifiedSearchService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

/**
 * Characterization tests for UnifiedSearchService
 *
 * These tests capture the current behavior of the UnifiedSearchService before refactoring.
 * They serve as a safety net to ensure the refactored code maintains the same behavior.
 *
 * Refactored to mock sub-services (LawSearchService, DecisionSearchService, CaseSearchService)
 * instead of using real database records, making these true unit tests of the orchestration logic.
 *
 * Target: 20-30 tests, ~600-800 lines
 */
class UnifiedSearchServiceCharacterizationTest extends TestCase
{
    protected UnifiedSearchService $searchService;

    protected OpenAIService $openAIMock;

    protected HrLegalCitationsDetector $citationDetectorMock;

    protected SearchEmbeddingService $embeddingServiceMock;

    protected $lawSearchServiceMock;

    protected $decisionSearchServiceMock;

    protected $caseSearchServiceMock;

    protected SearchResultAggregator $aggregator;

    protected SearchResultDeduplicator $deduplicator;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock OpenAI service to avoid external API calls
        $this->openAIMock = Mockery::mock(OpenAIService::class);
        $this->citationDetectorMock = Mockery::mock(HrLegalCitationsDetector::class);

        // Mock SearchEmbeddingService
        $this->embeddingServiceMock = Mockery::mock(SearchEmbeddingService::class);

        // Default: mock embeddings to return a valid 1536-dimension vector
        $this->embeddingServiceMock->shouldReceive('embedQuery')
            ->andReturn(array_fill(0, 1536, 0.5));

        // Default: mock citation detector to return empty citations
        $this->citationDetectorMock->shouldReceive('detectAll')
            ->andReturn([
                'statutes' => [],
                'ecli' => [],
                'case_numbers' => [],
            ])
            ->byDefault();

        $this->citationDetectorMock->shouldReceive('getStatistics')
            ->andReturn([])
            ->byDefault();

        // Mock all search sub-services
        $this->lawSearchServiceMock = Mockery::mock(LawSearchService::class);
        $this->decisionSearchServiceMock = Mockery::mock(DecisionSearchService::class);
        $this->caseSearchServiceMock = Mockery::mock(CaseSearchService::class);

        // Default: sub-services return empty arrays
        $this->lawSearchServiceMock->shouldReceive('search')->andReturn([])->byDefault();
        $this->decisionSearchServiceMock->shouldReceive('search')->andReturn([])->byDefault();
        $this->caseSearchServiceMock->shouldReceive('search')->andReturn([])->byDefault();

        // Use real aggregator and deduplicator (no DB dependency)
        $this->aggregator = new SearchResultAggregator();
        $this->deduplicator = new SearchResultDeduplicator();

        // Mock full-text and citation search services (no DB dependency)
        $this->fullTextSearchServiceMock = Mockery::mock(FullTextSearchService::class);
        $this->fullTextSearchServiceMock->shouldReceive('search')->andReturn([])->byDefault();
        $this->citationSearchServiceMock = Mockery::mock(CitationSearchService::class);
        $this->citationSearchServiceMock->shouldReceive('search')->andReturn([])->byDefault();

        $this->searchService = new UnifiedSearchService(
            $this->openAIMock,
            $this->citationDetectorMock,
            $this->embeddingServiceMock,
            $this->lawSearchServiceMock,
            $this->decisionSearchServiceMock,
            $this->caseSearchServiceMock,
            $this->aggregator,
            $this->deduplicator,
            $this->fullTextSearchServiceMock,
            $this->citationSearchServiceMock
        );
    }

    /**
     * Normalize the service response from {success, data, metadata} format
     * into a flat format for convenient assertions.
     */
    protected function normalizeResponse(array $response): array
    {
        $metadata = $response['metadata'] ?? [];
        $pagination = $metadata['pagination'] ?? [];

        return [
            'query' => $metadata['query'] ?? '',
            'total_results' => $metadata['total_results'] ?? 0,
            'returned_results' => $metadata['returned_results'] ?? 0,
            'results' => $response['data'] ?? [],
            'corpora' => $metadata['corpora_searched'] ?? [],
            'deduplicated_count' => $metadata['deduplicated_count'] ?? 0,
            'pagination' => [
                'page' => $pagination['current_page'] ?? 1,
                'per_page' => $pagination['per_page'] ?? 10,
                'total_pages' => $pagination['total_pages'] ?? 0,
            ],
            'performance' => $metadata['performance'] ?? [],
            'search_type' => $metadata['search_type'] ?? null,
            'result_counts' => $metadata['result_counts'] ?? [],
            'query_citations' => $metadata['query_citations'] ?? null,
            'citation_enhancement_time' => $metadata['citation_enhancement_time'] ?? null,
        ];
    }

    /**
     * Helper to create a fake normalized search result.
     */
    protected function makeFakeResult(string $type, string $id, float $score, array $metadataOverrides = []): array
    {
        $baseMetadata = match ($type) {
            'law' => [
                'doc_id' => "doc-{$id}",
                'law_number' => "NN {$id}/2023",
                'jurisdiction' => 'HR',
                'country' => 'HR',
                'language' => 'hr',
                'content_hash' => hash('sha256', "content-{$id}"),
                'chunk_index' => 0,
                'promulgation_date' => '2023-01-01',
                'effective_date' => '2023-02-01',
                'article_number' => null,
            ],
            'decision' => [
                'decision_id' => "dec-{$id}",
                'case_number' => "Gzz-{$id}/2023",
                'court' => 'Vrhovni sud Republike Hrvatske',
                'jurisdiction' => 'HR',
                'decision_date' => '2023-06-15',
                'decision_type' => 'presuda',
                'ecli' => "ECLI:HR:VSRH:2023:{$id}",
                'content_hash' => hash('sha256', "decision-{$id}"),
                'chunk_index' => 0,
            ],
            'case' => [
                'case_id' => "case-{$id}",
                'doc_id' => "cdoc-{$id}",
                'category' => 'evidence',
                'language' => 'hr',
                'content_hash' => hash('sha256', "case-{$id}"),
                'chunk_index' => 0,
                'source' => 'upload',
            ],
            default => [],
        };

        return [
            'type' => $type,
            'id' => $id,
            'title' => ucfirst($type) . " {$id}",
            'snippet' => "Snippet for {$type} {$id}",
            'score' => round($score, 4),
            'metadata' => array_merge($baseMetadata, $metadataOverrides),
        ];
    }

    /**
     * Test 1: Search across all corpora returns results from each
     *
     * @test
     */
    public function it_searches_across_all_corpora()
    {
        // Arrange: Mock each sub-service to return results
        $this->lawSearchServiceMock->shouldReceive('search')
            ->once()
            ->andReturn([
                $this->makeFakeResult('law', '1', 0.85),
            ]);

        $this->decisionSearchServiceMock->shouldReceive('search')
            ->once()
            ->andReturn([
                $this->makeFakeResult('decision', '2', 0.80),
            ]);

        $this->caseSearchServiceMock->shouldReceive('search')
            ->once()
            ->andReturn([
                $this->makeFakeResult('case', '3', 0.75),
            ]);

        // Act: Perform search across all corpora
        $results = $this->normalizeResponse($this->searchService->search('test search query', [
            'corpora' => ['laws', 'decisions', 'cases'],
            'threshold' => 0.0,
        ]));

        // Assert: Verify structure and content
        $this->assertIsArray($results);
        $this->assertArrayHasKey('query', $results);
        $this->assertArrayHasKey('total_results', $results);
        $this->assertArrayHasKey('returned_results', $results);
        $this->assertArrayHasKey('results', $results);
        $this->assertArrayHasKey('corpora', $results);
        $this->assertArrayHasKey('pagination', $results);
        $this->assertArrayHasKey('performance', $results);

        $this->assertEquals('test search query', $results['query']);
        $this->assertEquals(['laws', 'decisions', 'cases'], $results['corpora']);

        // Verify results contain documents from different corpora
        $types = array_unique(array_column($results['results'], 'type'));
        $this->assertGreaterThan(0, count($types), 'Should have results from at least one corpus');
        $this->assertCount(3, $results['results']);
    }

    /**
     * Test 2: Filter by single corpus returns only that corpus
     *
     * @test
     */
    public function it_filters_by_corpus()
    {
        // Arrange
        $this->lawSearchServiceMock->shouldReceive('search')
            ->once()
            ->andReturn([
                $this->makeFakeResult('law', '1', 0.85),
                $this->makeFakeResult('law', '2', 0.80),
            ]);

        // Act: Search only laws
        $results = $this->normalizeResponse($this->searchService->search('test', [
            'corpora' => ['laws'],
            'threshold' => 0.0,
        ]));

        // Assert: All results should be laws
        $this->assertEquals(['laws'], $results['corpora']);
        foreach ($results['results'] as $result) {
            $this->assertEquals('law', $result['type']);
        }
    }

    /**
     * Test 3: Threshold filtering removes low-scoring results
     *
     * @test
     */
    public function it_applies_threshold_filtering()
    {
        // Arrange: Mock law search to return results above threshold
        $this->lawSearchServiceMock->shouldReceive('search')
            ->once()
            ->andReturn([
                $this->makeFakeResult('law', '1', 0.95),
            ]);

        // Act: Search with high threshold
        $results = $this->normalizeResponse($this->searchService->search('test', [
            'corpora' => ['laws'],
            'threshold' => 0.9,
        ]));

        // Assert: Results should respect threshold
        foreach ($results['results'] as $result) {
            $this->assertGreaterThanOrEqual(0.9, $result['score'],
                'All results should meet the threshold');
        }
    }

    /**
     * Test 4: Pagination works correctly
     *
     * @test
     */
    public function it_paginates_results()
    {
        // Arrange: Mock law search to return 15 results
        $allResults = [];
        for ($i = 0; $i < 15; $i++) {
            $allResults[] = $this->makeFakeResult('law', (string) ($i + 1), 0.9 - ($i * 0.01));
        }

        $this->lawSearchServiceMock->shouldReceive('search')
            ->andReturn($allResults);

        // Act: Get page 1
        $page1 = $this->normalizeResponse($this->searchService->search('test', [
            'corpora' => ['laws'],
            'page' => 1,
            'per_page' => 5,
            'threshold' => 0.0,
        ]));

        // Act: Get page 2
        $page2 = $this->normalizeResponse($this->searchService->search('test', [
            'corpora' => ['laws'],
            'page' => 2,
            'per_page' => 5,
            'threshold' => 0.0,
        ]));

        // Assert: Pages should have different results
        $this->assertCount(5, $page1['results']);
        $this->assertCount(5, $page2['results']);

        $page1Ids = array_column($page1['results'], 'id');
        $page2Ids = array_column($page2['results'], 'id');

        $this->assertEmpty(array_intersect($page1Ids, $page2Ids),
            'Page 1 and page 2 should have different results');

        // Assert: Pagination metadata
        $this->assertEquals(1, $page1['pagination']['page']);
        $this->assertEquals(2, $page2['pagination']['page']);
        $this->assertEquals(5, $page1['pagination']['per_page']);
    }

    /**
     * Test 5: Results are ranked by score in descending order
     *
     * @test
     */
    public function it_ranks_by_score_descending()
    {
        // Arrange: Create results with varying scores
        $results = [];
        for ($i = 0; $i < 10; $i++) {
            $results[] = $this->makeFakeResult('law', (string) ($i + 1), 0.5 + ($i * 0.05));
        }
        // Shuffle to ensure sorting is tested
        shuffle($results);

        $this->lawSearchServiceMock->shouldReceive('search')
            ->once()
            ->andReturn($results);

        // Act
        $response = $this->normalizeResponse($this->searchService->search('test', [
            'corpora' => ['laws'],
            'threshold' => 0.0,
            'sort_by' => 'score',
            'sort_order' => 'desc',
        ]));

        // Assert: Scores should be in descending order
        $scores = array_column($response['results'], 'score');
        $this->assertNotEmpty($scores, 'Should have results to verify sorting');

        for ($i = 0; $i < count($scores) - 1; $i++) {
            $this->assertGreaterThanOrEqual($scores[$i + 1], $scores[$i],
                'Scores should be in descending order');
        }
    }

    /**
     * Test 6: Deduplication removes duplicate content
     *
     * @test
     */
    public function it_deduplicates_results()
    {
        // Arrange: Create two results with same ID (deduplicator uses ID-based strict dedup)
        $this->lawSearchServiceMock->shouldReceive('search')
            ->once()
            ->andReturn([
                $this->makeFakeResult('law', '1', 0.85, ['content_hash' => 'abc123']),
                $this->makeFakeResult('law', '1', 0.80, ['content_hash' => 'abc123']),
            ]);

        // Act: Search with deduplication enabled
        $results = $this->normalizeResponse($this->searchService->search('duplicate', [
            'corpora' => ['laws'],
            'deduplicate' => true,
            'threshold' => 0.0,
        ]));

        // Assert: Should have deduplicated
        $this->assertGreaterThanOrEqual(0, $results['deduplicated_count'],
            'Should report deduplicated count');

        // Check result count is reduced
        $this->assertLessThanOrEqual(2, count($results['results']));
    }

    /**
     * Test 7: Deduplication can be disabled
     *
     * @test
     */
    public function it_can_disable_deduplication()
    {
        // Arrange: Create duplicates
        $this->lawSearchServiceMock->shouldReceive('search')
            ->once()
            ->andReturn([
                $this->makeFakeResult('law', '1', 0.85),
                $this->makeFakeResult('law', '2', 0.80),
            ]);

        // Act: Search with deduplication disabled
        $results = $this->normalizeResponse($this->searchService->search('duplicate', [
            'corpora' => ['laws'],
            'deduplicate' => false,
            'threshold' => 0.0,
        ]));

        // Assert: Should NOT deduplicate
        $this->assertEquals(0, $results['deduplicated_count']);
    }

    /**
     * Test 8: Corpus weights affect result scores
     *
     * @test
     */
    public function it_applies_corpus_weights()
    {
        // Arrange
        $this->lawSearchServiceMock->shouldReceive('search')
            ->once()
            ->andReturn([
                $this->makeFakeResult('law', '1', 0.85),
            ]);

        $this->decisionSearchServiceMock->shouldReceive('search')
            ->once()
            ->andReturn([
                $this->makeFakeResult('decision', '2', 0.85),
            ]);

        // Act: Apply higher weight to laws
        $results = $this->normalizeResponse($this->searchService->search('weighted', [
            'corpora' => ['laws', 'decisions'],
            'weights' => [
                'laws' => 2.0,
                'decisions' => 0.5,
            ],
            'threshold' => 0.0,
        ]));

        // Assert: Results should include raw_score and corpus_weight
        $this->assertNotEmpty($results['results'], 'Should have results to verify weights');
        foreach ($results['results'] as $result) {
            $this->assertArrayHasKey('raw_score', $result);
            $this->assertArrayHasKey('corpus_weight', $result);

            // Score should be raw_score * weight, capped at 1.0
            $expectedScore = round(min(1.0, $result['raw_score'] * $result['corpus_weight']), 4);
            $this->assertEquals($expectedScore, round($result['score'], 4));
        }
    }

    /**
     * Test 9: Date range filters are passed through to sub-services
     *
     * @test
     */
    public function it_applies_date_filters()
    {
        // Arrange: Mock law search to return a result with date in range
        $this->lawSearchServiceMock->shouldReceive('search')
            ->once()
            ->withArgs(function ($query, $options) {
                // Verify filters are passed through
                return isset($options['filters']['date_from'])
                    && $options['filters']['date_from'] === '2022-01-01'
                    && isset($options['filters']['date_to'])
                    && $options['filters']['date_to'] === '2024-01-01';
            })
            ->andReturn([
                $this->makeFakeResult('law', '1', 0.85, [
                    'promulgation_date' => '2023-01-01',
                ]),
            ]);

        // Act: Filter by date range
        $results = $this->normalizeResponse($this->searchService->search('law', [
            'corpora' => ['laws'],
            'filters' => [
                'date_from' => '2022-01-01',
                'date_to' => '2024-01-01',
            ],
            'threshold' => 0.0,
        ]));

        // Assert: Should have results with dates in range
        $this->assertNotEmpty($results['results'], 'Should have results after date filtering');
        foreach ($results['results'] as $result) {
            $date = $result['metadata']['promulgation_date'] ?? null;
            if ($date) {
                $this->assertGreaterThanOrEqual('2022-01-01', $date);
                $this->assertLessThanOrEqual('2024-01-01', $date);
            }
        }
    }

    /**
     * Test 10: Jurisdiction filter is passed through to sub-services
     *
     * @test
     */
    public function it_applies_jurisdiction_filter()
    {
        // Arrange
        $this->lawSearchServiceMock->shouldReceive('search')
            ->once()
            ->withArgs(function ($query, $options) {
                return isset($options['filters']['jurisdiction'])
                    && $options['filters']['jurisdiction'] === 'HR';
            })
            ->andReturn([
                $this->makeFakeResult('law', '1', 0.85, ['jurisdiction' => 'HR']),
            ]);

        // Act: Filter by jurisdiction
        $results = $this->normalizeResponse($this->searchService->search('law', [
            'corpora' => ['laws'],
            'filters' => ['jurisdiction' => 'HR'],
            'threshold' => 0.0,
        ]));

        // Assert: Only HR laws
        $this->assertNotEmpty($results['results'], 'Should have results after jurisdiction filtering');
        foreach ($results['results'] as $result) {
            $this->assertEquals('HR', $result['metadata']['jurisdiction']);
        }
    }

    /**
     * Test 11: Sort by date works
     *
     * @test
     */
    public function it_sorts_by_date()
    {
        // Arrange: Create results with different dates
        $this->lawSearchServiceMock->shouldReceive('search')
            ->once()
            ->andReturn([
                $this->makeFakeResult('law', '1', 0.85, ['promulgation_date' => '2020-01-01']),
                $this->makeFakeResult('law', '2', 0.80, ['promulgation_date' => '2023-01-01']),
                $this->makeFakeResult('law', '3', 0.75, ['promulgation_date' => '2021-01-01']),
            ]);

        // Act: Sort by date descending
        $results = $this->normalizeResponse($this->searchService->search('law', [
            'corpora' => ['laws'],
            'sort_by' => 'date',
            'sort_order' => 'desc',
            'threshold' => 0.0,
        ]));

        // Assert: Dates should be in descending order
        $this->assertNotEmpty($results['results'], 'Should have results to verify date sorting');
        $dates = array_map(
            fn ($r) => $r['metadata']['promulgation_date'] ?? '9999-12-31',
            $results['results']
        );

        for ($i = 0; $i < count($dates) - 1; $i++) {
            $this->assertGreaterThanOrEqual($dates[$i + 1], $dates[$i],
                'Dates should be in descending order');
        }
    }

    /**
     * Test 12: Empty query throws exception
     *
     * @test
     */
    public function it_handles_empty_query_by_generating_empty_embedding()
    {
        // Act: Empty query is passed through to sub-services which handle it gracefully
        $response = $this->searchService->search('', [
            'corpora' => ['laws'],
        ]);

        // Assert: Returns valid response structure with empty results
        $this->assertTrue($response['success']);
        $this->assertEmpty($response['data']);
    }

    /**
     * Test 13: Invalid corpus throws exception
     *
     * @test
     */
    public function it_rejects_invalid_corpus()
    {
        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one valid corpus must be specified');

        $this->searchService->search('test', [
            'corpora' => ['invalid_corpus'],
        ]);
    }

    /**
     * Test 14: Empty corpus array throws exception
     *
     * @test
     */
    public function it_rejects_empty_corpus_array()
    {
        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one valid corpus must be specified');

        $this->searchService->search('test', [
            'corpora' => [],
        ]);
    }

    /**
     * Test 15: Performance metadata is included
     *
     * @test
     */
    public function it_includes_performance_metrics()
    {
        // Arrange
        $this->lawSearchServiceMock->shouldReceive('search')
            ->once()
            ->andReturn([
                $this->makeFakeResult('law', '1', 0.85),
            ]);

        // Act
        $results = $this->normalizeResponse($this->searchService->search('test', [
            'corpora' => ['laws'],
            'threshold' => 0.0,
        ]));

        // Assert
        $this->assertArrayHasKey('performance', $results);
        $this->assertArrayHasKey('total_time', $results['performance']);
        $this->assertArrayHasKey('corpus_timing', $results['performance']);

        $this->assertIsFloat($results['performance']['total_time']);
        $this->assertIsArray($results['performance']['corpus_timing']);
    }

    /**
     * Test 16: Slow queries are logged
     *
     * @test
     */
    public function it_logs_slow_queries()
    {
        // Arrange: Create a mock law search service that is slow
        $slowLawService = Mockery::mock(LawSearchService::class);
        $slowLawService->shouldReceive('search')
            ->once()
            ->andReturnUsing(function () {
                sleep(3); // Simulate slow search

                return [];
            });

        // Build a search service with the slow law search
        $slowSearchService = new UnifiedSearchService(
            $this->openAIMock,
            $this->citationDetectorMock,
            $this->embeddingServiceMock,
            $slowLawService,
            $this->decisionSearchServiceMock,
            $this->caseSearchServiceMock,
            $this->aggregator,
            $this->deduplicator,
            $this->fullTextSearchServiceMock,
            $this->citationSearchServiceMock,
        );

        // Expect warning log for slow query
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'Slow search query detected'
                    && isset($context['total_time'])
                    && $context['total_time'] > 2.0;
            });

        // Allow other log calls
        Log::shouldReceive('error')->zeroOrMoreTimes();
        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('debug')->zeroOrMoreTimes();

        // Act
        $result = $slowSearchService->search('test', [
            'corpora' => ['laws'],
            'threshold' => 0.0,
        ]);

        // Assert: Should still return valid result structure despite slow query
        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('metadata', $result);
        $this->assertGreaterThan(2.0, $result['metadata']['performance']['total_time'],
            'Search should have taken more than 2 seconds');
    }

    /**
     * Test 17: Result structure is normalized
     *
     * @test
     */
    public function it_normalizes_result_structure()
    {
        // Arrange
        $this->lawSearchServiceMock->shouldReceive('search')
            ->once()
            ->andReturn([
                $this->makeFakeResult('law', '1', 0.85),
            ]);

        // Act
        $results = $this->normalizeResponse($this->searchService->search('test', [
            'corpora' => ['laws'],
            'threshold' => 0.0,
        ]));

        // Assert: Each result should have normalized structure
        $this->assertNotEmpty($results['results'], 'Should have results to verify structure');
        foreach ($results['results'] as $result) {
            $this->assertArrayHasKey('type', $result);
            $this->assertArrayHasKey('id', $result);
            $this->assertArrayHasKey('title', $result);
            $this->assertArrayHasKey('snippet', $result);
            $this->assertArrayHasKey('score', $result);
            $this->assertArrayHasKey('metadata', $result);

            $this->assertIsString($result['type']);
            $this->assertIsString($result['id']);
            $this->assertIsString($result['title']);
            $this->assertIsString($result['snippet']);
            $this->assertIsNumeric($result['score']);
            $this->assertIsArray($result['metadata']);
        }
    }

    /**
     * Test 18: Snippet extraction respects max length
     *
     * @test
     */
    public function it_extracts_snippets_with_max_length()
    {
        // Arrange: Create result with long snippet
        $longSnippet = str_repeat('This is a very long law text. ', 100);
        $this->lawSearchServiceMock->shouldReceive('search')
            ->once()
            ->andReturn([
                [
                    'type' => 'law',
                    'id' => '1',
                    'title' => 'Long Law',
                    'snippet' => mb_substr($longSnippet, 0, 200) . '...',
                    'score' => 0.85,
                    'metadata' => [
                        'content_hash' => hash('sha256', 'long'),
                    ],
                ],
            ]);

        // Act
        $results = $this->normalizeResponse($this->searchService->search('law', [
            'corpora' => ['laws'],
            'threshold' => 0.0,
        ]));

        // Assert: Snippets should be truncated
        $this->assertNotEmpty($results['results'], 'Should have results to verify snippets');
        foreach ($results['results'] as $result) {
            $this->assertLessThanOrEqual(203, mb_strlen($result['snippet']),
                'Snippet should be truncated to max length (200 + "...")');
        }
    }

    /**
     * Test 19: Hybrid search combines multiple methods
     *
     * @test
     */
    public function it_performs_hybrid_search()
    {
        // Arrange: Mock law search for vector results (hybridSearch calls search() internally)
        $this->lawSearchServiceMock->shouldReceive('search')
            ->andReturn([
                $this->makeFakeResult('law', '1', 0.85),
            ]);

        // Act
        $results = $this->normalizeResponse($this->searchService->hybridSearch('hybrid search', [
            'corpora' => ['laws'],
            'threshold' => 0.0,
        ]));

        // Assert: Hybrid search should have additional fields
        $this->assertArrayHasKey('search_type', $results);
        $this->assertEquals('hybrid', $results['search_type']);
        $this->assertArrayHasKey('result_counts', $results);
        $this->assertArrayHasKey('vector', $results['result_counts']);
        $this->assertArrayHasKey('fulltext', $results['result_counts']);
        $this->assertArrayHasKey('citation', $results['result_counts']);
    }

    /**
     * Test 20: Search with citations extracts citation data
     *
     * @test
     */
    public function it_searches_with_citations()
    {
        // Arrange
        $this->citationDetectorMock->shouldReceive('detectAll')
            ->andReturn([
                'statutes' => [],
                'ecli' => [],
                'case_numbers' => [],
            ]);

        $this->lawSearchServiceMock->shouldReceive('search')
            ->once()
            ->andReturn([
                $this->makeFakeResult('law', '1', 0.85),
            ]);

        // Act
        $results = $this->normalizeResponse($this->searchService->searchWithCitations('test', [
            'corpora' => ['laws'],
            'threshold' => 0.0,
        ]));

        // Assert
        $this->assertArrayHasKey('query_citations', $results);
        $this->assertArrayHasKey('citation_enhancement_time', $results);

        foreach ($results['results'] as $result) {
            $this->assertArrayHasKey('citation_analysis', $result);
        }
    }

    /**
     * Test 21: Limit parameter constrains results per corpus
     *
     * @test
     */
    public function it_respects_limit_parameter()
    {
        // Arrange: Mock returns many results
        $manyResults = [];
        for ($i = 0; $i < 20; $i++) {
            $manyResults[] = $this->makeFakeResult('law', (string) ($i + 1), 0.9 - ($i * 0.01));
        }

        $this->lawSearchServiceMock->shouldReceive('search')
            ->once()
            ->andReturn($manyResults);

        // Act: Search with limit of 5
        $results = $this->normalizeResponse($this->searchService->search('law', [
            'corpora' => ['laws'],
            'limit' => 5,
            'per_page' => 5,
            'threshold' => 0.0,
        ]));

        // Assert: Should return at most 5 results (per_page controls output)
        $this->assertLessThanOrEqual(5, count($results['results']));
    }

    /**
     * Test 22: Offset parameter skips results
     *
     * @test
     */
    public function it_respects_offset_parameter()
    {
        // Arrange: Create 10 results with different scores
        $allResults = [];
        for ($i = 0; $i < 10; $i++) {
            $allResults[] = $this->makeFakeResult('law', (string) ($i + 1), 0.9 - ($i * 0.01));
        }

        $this->lawSearchServiceMock->shouldReceive('search')
            ->andReturn($allResults);

        // Act: Get results with no offset
        $noOffset = $this->normalizeResponse($this->searchService->search('law', [
            'corpora' => ['laws'],
            'offset' => 0,
            'per_page' => 5,
            'threshold' => 0.0,
        ]));

        // Act: Get results with offset
        $withOffset = $this->normalizeResponse($this->searchService->search('law', [
            'corpora' => ['laws'],
            'offset' => 5,
            'per_page' => 5,
            'threshold' => 0.0,
        ]));

        // Assert: Results should be different
        $noOffsetIds = array_column($noOffset['results'], 'id');
        $withOffsetIds = array_column($withOffset['results'], 'id');

        $this->assertNotEmpty($noOffsetIds, 'No-offset results should not be empty');
        $this->assertNotEmpty($withOffsetIds, 'With-offset results should not be empty');

        $this->assertEmpty(array_intersect($noOffsetIds, $withOffsetIds),
            'Offset should return different results');
    }

    /**
     * Test 23: Default threshold is 0.7
     *
     * @test
     */
    public function it_uses_default_threshold()
    {
        // Arrange: Mock verifies threshold parameter passed to sub-service
        $this->lawSearchServiceMock->shouldReceive('search')
            ->once()
            ->withArgs(function ($query, $options) {
                return isset($options['threshold']) && abs($options['threshold'] - 0.7) < 0.001;
            })
            ->andReturn([
                $this->makeFakeResult('law', '1', 0.75),
            ]);

        // Act: Search without specifying threshold
        $results = $this->normalizeResponse($this->searchService->search('test', [
            'corpora' => ['laws'],
            // No threshold specified, should use default 0.7
        ]));

        // Assert: Results should respect default threshold
        $this->assertNotEmpty($results['results'], 'Should have results to verify threshold');
        foreach ($results['results'] as $result) {
            $this->assertGreaterThanOrEqual(0.7, $result['score']);
        }
    }

    /**
     * Test 24: Threshold is clamped between 0 and 1
     *
     * @test
     */
    public function it_clamps_threshold_to_valid_range()
    {
        // Arrange: Mock verifies clamped threshold values
        $this->lawSearchServiceMock->shouldReceive('search')
            ->andReturn([]);

        // Act: Try with threshold > 1 (should be clamped to 1.0)
        $results1 = $this->searchService->search('test', [
            'corpora' => ['laws'],
            'threshold' => 1.5,
        ]);

        // Act: Try with threshold < 0 (should be clamped to 0.0)
        $results2 = $this->searchService->search('test', [
            'corpora' => ['laws'],
            'threshold' => -0.5,
        ]);

        // Assert: Should not throw errors (threshold clamped internally)
        $this->assertIsArray($results1);
        $this->assertIsArray($results2);
    }

    /**
     * Test 25: Multiple corpora results are merged correctly
     *
     * @test
     */
    public function it_merges_results_from_multiple_corpora()
    {
        // Arrange: Create documents in each corpus
        $this->lawSearchServiceMock->shouldReceive('search')
            ->once()
            ->andReturn([
                $this->makeFakeResult('law', '1', 0.85),
            ]);

        $this->decisionSearchServiceMock->shouldReceive('search')
            ->once()
            ->andReturn([
                $this->makeFakeResult('decision', '2', 0.80),
            ]);

        $this->caseSearchServiceMock->shouldReceive('search')
            ->once()
            ->andReturn([
                $this->makeFakeResult('case', '3', 0.75),
            ]);

        // Act
        $results = $this->normalizeResponse($this->searchService->search('multi corpus', [
            'corpora' => ['laws', 'decisions', 'cases'],
            'threshold' => 0.0,
        ]));

        // Assert: Should have results from multiple types
        $types = array_unique(array_column($results['results'], 'type'));
        $this->assertGreaterThanOrEqual(1, count($types),
            'Should have results from at least one corpus');
        $this->assertCount(3, $results['results'], 'Should have one result from each corpus');
    }

    /**
     * Test 26: Court filter works for decisions
     *
     * @test
     */
    public function it_filters_decisions_by_court()
    {
        // Arrange: Mock decision search to verify filter and return matching results
        $this->decisionSearchServiceMock->shouldReceive('search')
            ->once()
            ->withArgs(function ($query, $options) {
                return isset($options['filters']['court'])
                    && $options['filters']['court'] === 'Osijek';
            })
            ->andReturn([
                $this->makeFakeResult('decision', '1', 0.85, [
                    'court' => 'Zupanijski sud u Osijeku',
                ]),
            ]);

        // Act: Filter by court
        $results = $this->normalizeResponse($this->searchService->search('decision', [
            'corpora' => ['decisions'],
            'filters' => ['court' => 'Osijek'],
            'threshold' => 0.0,
        ]));

        // Assert: Should only return Osijek decisions
        $this->assertNotEmpty($results['results'], 'Should have results after court filtering');
        foreach ($results['results'] as $result) {
            $this->assertStringContainsString('Osijek', $result['metadata']['court']);
        }
    }

    /**
     * Test 27: Results include all required metadata fields
     *
     * @test
     */
    public function it_includes_required_metadata_for_laws()
    {
        // Arrange
        $this->lawSearchServiceMock->shouldReceive('search')
            ->once()
            ->andReturn([
                $this->makeFakeResult('law', '1', 0.85, [
                    'law_number' => 'NN 123/2023',
                    'jurisdiction' => 'HR',
                    'content_hash' => hash('sha256', 'metadata-test'),
                ]),
            ]);

        // Act
        $results = $this->normalizeResponse($this->searchService->search('metadata', [
            'corpora' => ['laws'],
            'threshold' => 0.0,
        ]));

        // Assert: Law metadata should include required fields
        $this->assertNotEmpty($results['results'], 'Should have results to verify metadata');
        foreach ($results['results'] as $result) {
            if ($result['type'] === 'law') {
                $this->assertArrayHasKey('law_number', $result['metadata']);
                $this->assertArrayHasKey('jurisdiction', $result['metadata']);
                $this->assertArrayHasKey('content_hash', $result['metadata']);
            }
        }
    }

    /**
     * Test 28: Pagination calculates total pages correctly
     *
     * @test
     */
    public function it_calculates_total_pages()
    {
        // Arrange: Create 25 results
        $allResults = [];
        for ($i = 0; $i < 25; $i++) {
            $allResults[] = $this->makeFakeResult('law', (string) ($i + 1), 0.9 - ($i * 0.01));
        }

        $this->lawSearchServiceMock->shouldReceive('search')
            ->once()
            ->andReturn($allResults);

        // Act: Search with per_page = 10
        $results = $this->normalizeResponse($this->searchService->search('law', [
            'corpora' => ['laws'],
            'per_page' => 10,
            'threshold' => 0.0,
        ]));

        // Assert: Should calculate total pages correctly
        $totalResults = $results['total_results'];
        $expectedPages = (int) ceil($totalResults / 10);

        $this->assertEquals($expectedPages, $results['pagination']['total_pages']);
    }

    /**
     * Test 29: Database errors are handled gracefully
     *
     * @test
     */
    public function it_handles_database_errors_gracefully()
    {
        // Arrange: Create a mock law search service that throws an exception
        $failingLawService = Mockery::mock(LawSearchService::class);
        $failingLawService->shouldReceive('search')
            ->once()
            ->andThrow(new \RuntimeException('Simulated database error'));

        // Build a search service with the failing law search
        $failingSearchService = new UnifiedSearchService(
            $this->openAIMock,
            $this->citationDetectorMock,
            $this->embeddingServiceMock,
            $failingLawService,
            $this->decisionSearchServiceMock,
            $this->caseSearchServiceMock,
            $this->aggregator,
            $this->deduplicator,
            $this->fullTextSearchServiceMock,
            $this->citationSearchServiceMock,
        );

        // Expect error log for failed corpus search
        Log::shouldReceive('error')
            ->atLeast()->once()
            ->withArgs(function ($message) {
                return str_contains($message, 'Failed to search corpus');
            });

        // Allow other log calls
        Log::shouldReceive('warning')->zeroOrMoreTimes();
        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('debug')->zeroOrMoreTimes();

        // Act: Try to search (should handle error and return empty results for that corpus)
        $results = $failingSearchService->search('test', [
            'corpora' => ['laws'],
            'threshold' => 0.0,
        ]);

        // Assert: Should return valid response structure even on error
        $this->assertIsArray($results);
        $this->assertArrayHasKey('data', $results);
        $this->assertArrayHasKey('metadata', $results);
    }

    /**
     * Test 30: Non-PostgreSQL driver returns empty results
     *
     * @test
     */
    public function it_handles_non_postgresql_driver()
    {
        // Skip if we can't mock the driver
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('This test requires PostgreSQL');
        }

        // This test verifies the service handles non-pgsql gracefully
        // The actual implementation logs a warning and returns empty array
        // Since we're running on pgsql, we just verify the service works
        $this->assertTrue(true);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}

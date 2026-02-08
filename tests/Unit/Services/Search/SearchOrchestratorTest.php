<?php

namespace Tests\Unit\Services\Search;

use App\Services\Search\CaseSearchService;
use App\Services\Search\LawSearchService;
use App\Services\Search\SearchOrchestrator;
use App\Services\Search\SearchResultAggregator;
use App\Services\Search\SearchResultDeduplicator;
use Mockery;
use Tests\TestCase;

/**
 * Tests for SearchOrchestrator
 */
class SearchOrchestratorTest extends TestCase
{
    protected $aggregatorMock;

    protected $deduplicatorMock;

    protected $lawSearchMock;

    protected $caseSearchMock;

    protected SearchOrchestrator $orchestrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->aggregatorMock = Mockery::mock(SearchResultAggregator::class);
        $this->deduplicatorMock = Mockery::mock(SearchResultDeduplicator::class);
        $this->lawSearchMock = Mockery::mock(LawSearchService::class);
        $this->caseSearchMock = Mockery::mock(CaseSearchService::class);

        $this->orchestrator = new SearchOrchestrator(
            $this->aggregatorMock,
            $this->deduplicatorMock,
            $this->lawSearchMock,
            $this->caseSearchMock
        );
    }

    protected function tearDown(): void
    {
        if ($container = Mockery::getContainer()) {
            $this->addToAssertionCount($container->mockery_getExpectationCount());
        }
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_searches_all_available_corpora_by_default()
    {
        $mockLawResults = [
            ['id' => 'law-1', 'score' => 0.9, 'corpus' => 'laws'],
        ];
        $mockCaseResults = [
            ['id' => 'case-1', 'score' => 0.8, 'corpus' => 'cases'],
        ];

        $this->lawSearchMock
            ->shouldReceive('search')
            ->once()
            ->andReturn($mockLawResults);

        $this->caseSearchMock
            ->shouldReceive('search')
            ->once()
            ->andReturn($mockCaseResults);

        $this->aggregatorMock
            ->shouldReceive('aggregate')
            ->once()
            ->andReturn(array_merge($mockLawResults, $mockCaseResults));

        $this->deduplicatorMock
            ->shouldReceive('deduplicate')
            ->once()
            ->andReturn(array_merge($mockLawResults, $mockCaseResults));

        $result = $this->orchestrator->search('test query');

        $this->assertArrayHasKey('results', $result);
        $this->assertArrayHasKey('metadata', $result);
        $this->assertArrayHasKey('timing', $result);
    }

    /** @test */
    public function it_searches_only_specified_corpora()
    {
        $mockLawResults = [
            ['id' => 'law-1', 'score' => 0.9, 'corpus' => 'laws'],
        ];

        $this->lawSearchMock
            ->shouldReceive('search')
            ->once()
            ->andReturn($mockLawResults);

        // Case search should NOT be called
        $this->caseSearchMock
            ->shouldNotReceive('search');

        $this->aggregatorMock
            ->shouldReceive('aggregate')
            ->once()
            ->andReturn($mockLawResults);

        $this->deduplicatorMock
            ->shouldReceive('deduplicate')
            ->once()
            ->andReturn($mockLawResults);

        $result = $this->orchestrator->search('test query', ['corpora' => ['laws']]);

        $this->assertEquals(['laws'], $result['metadata']['corpora_searched']);
    }

    /** @test */
    public function it_applies_corpus_weights_to_aggregation()
    {
        $mockResults = [['id' => 'doc-1', 'score' => 0.9]];

        $this->lawSearchMock->shouldReceive('search')->once()->andReturn($mockResults);
        $this->caseSearchMock->shouldReceive('search')->once()->andReturn($mockResults);

        $weights = ['laws' => 1.2, 'cases' => 0.9];

        $this->aggregatorMock
            ->shouldReceive('aggregate')
            ->once()
            ->with(Mockery::any(), $weights)
            ->andReturn($mockResults);

        $this->deduplicatorMock
            ->shouldReceive('deduplicate')
            ->once()
            ->andReturn($mockResults);

        $this->orchestrator->search('test query', ['weights' => $weights]);
    }

    /** @test */
    public function it_deduplicates_results_by_default()
    {
        $mockResults = [['id' => 'doc-1', 'score' => 0.9]];

        $this->lawSearchMock->shouldReceive('search')->once()->andReturn($mockResults);
        $this->caseSearchMock->shouldReceive('search')->once()->andReturn($mockResults);

        $this->aggregatorMock
            ->shouldReceive('aggregate')
            ->once()
            ->andReturn($mockResults);

        $this->deduplicatorMock
            ->shouldReceive('deduplicate')
            ->once()
            ->with($mockResults, ['strategy' => 'strict', 'keep_highest_score' => true])
            ->andReturn($mockResults);

        $this->orchestrator->search('test query');
    }

    /** @test */
    public function it_skips_deduplication_when_disabled()
    {
        $mockResults = [['id' => 'doc-1', 'score' => 0.9]];

        $this->lawSearchMock->shouldReceive('search')->once()->andReturn($mockResults);
        $this->caseSearchMock->shouldReceive('search')->once()->andReturn($mockResults);

        $this->aggregatorMock
            ->shouldReceive('aggregate')
            ->once()
            ->andReturn($mockResults);

        // Deduplicator should NOT be called
        $this->deduplicatorMock
            ->shouldNotReceive('deduplicate');

        $this->orchestrator->search('test query', ['deduplicate' => false]);
    }

    /** @test */
    public function it_applies_pagination_to_results()
    {
        $mockResults = [
            ['id' => 'doc-1', 'score' => 0.9],
            ['id' => 'doc-2', 'score' => 0.8],
            ['id' => 'doc-3', 'score' => 0.7],
            ['id' => 'doc-4', 'score' => 0.6],
        ];

        $this->lawSearchMock->shouldReceive('search')->once()->andReturn($mockResults);
        $this->caseSearchMock->shouldReceive('search')->once()->andReturn([]);

        $this->aggregatorMock->shouldReceive('aggregate')->once()->andReturn($mockResults);
        $this->deduplicatorMock->shouldReceive('deduplicate')->once()->andReturn($mockResults);

        $result = $this->orchestrator->search('test query', ['page' => 2, 'per_page' => 2]);

        // Should return items 3-4 (page 2, 2 items per page)
        $this->assertCount(2, $result['results']);
        $this->assertEquals('doc-3', $result['results'][0]['id']);
        $this->assertEquals('doc-4', $result['results'][1]['id']);
    }

    /** @test */
    public function it_includes_pagination_metadata()
    {
        $mockResults = array_fill(0, 25, ['id' => 'doc', 'score' => 0.9]);

        $this->lawSearchMock->shouldReceive('search')->once()->andReturn($mockResults);
        $this->caseSearchMock->shouldReceive('search')->once()->andReturn([]);
        $this->aggregatorMock->shouldReceive('aggregate')->once()->andReturn($mockResults);
        $this->deduplicatorMock->shouldReceive('deduplicate')->once()->andReturn($mockResults);

        $result = $this->orchestrator->search('test query', ['page' => 2, 'per_page' => 10]);

        $this->assertEquals(25, $result['metadata']['total_results']);
        $this->assertEquals(2, $result['metadata']['page']);
        $this->assertEquals(10, $result['metadata']['per_page']);
        $this->assertEquals(3, $result['metadata']['total_pages']); // ceil(25/10)
    }

    /** @test */
    public function it_includes_timing_information()
    {
        $mockResults = [['id' => 'doc-1', 'score' => 0.9]];

        $this->lawSearchMock->shouldReceive('search')->once()->andReturn($mockResults);
        $this->caseSearchMock->shouldReceive('search')->once()->andReturn($mockResults);
        $this->aggregatorMock->shouldReceive('aggregate')->once()->andReturn($mockResults);
        $this->deduplicatorMock->shouldReceive('deduplicate')->once()->andReturn($mockResults);

        $result = $this->orchestrator->search('test query');

        $this->assertArrayHasKey('timing', $result);
        $this->assertArrayHasKey('total_time', $result['timing']);
        $this->assertArrayHasKey('corpus_times', $result['timing']);
        $this->assertIsFloat($result['timing']['total_time']);
    }

    /** @test */
    public function it_includes_statistics_when_requested()
    {
        $mockResults = [['id' => 'doc-1', 'score' => 0.9, 'corpus' => 'laws']];

        $this->lawSearchMock->shouldReceive('search')->once()->andReturn($mockResults);
        $this->caseSearchMock->shouldReceive('search')->once()->andReturn([]);
        $this->aggregatorMock->shouldReceive('aggregate')->once()->andReturn($mockResults);
        $this->aggregatorMock->shouldReceive('getStatistics')->once()->andReturn(['laws' => ['count' => 1]]);
        $this->deduplicatorMock->shouldReceive('deduplicate')->once()->andReturn($mockResults);

        $result = $this->orchestrator->search('test query', ['include_stats' => true]);

        $this->assertArrayHasKey('statistics', $result);
        $this->assertArrayHasKey('aggregation', $result['statistics']);
        $this->assertArrayHasKey('deduplication', $result['statistics']);
    }

    /** @test */
    public function it_handles_search_service_failures_gracefully()
    {
        $mockCaseResults = [['id' => 'case-1', 'score' => 0.8]];

        // Law search throws exception
        $this->lawSearchMock
            ->shouldReceive('search')
            ->once()
            ->andThrow(new \Exception('Search failed'));

        // Case search succeeds
        $this->caseSearchMock
            ->shouldReceive('search')
            ->once()
            ->andReturn($mockCaseResults);

        $this->aggregatorMock
            ->shouldReceive('aggregate')
            ->once()
            ->andReturn($mockCaseResults);

        $this->deduplicatorMock
            ->shouldReceive('deduplicate')
            ->once()
            ->andReturn($mockCaseResults);

        // Should not throw, should continue with available results
        $result = $this->orchestrator->search('test query');

        $this->assertArrayHasKey('results', $result);
    }

    /** @test */
    public function it_returns_empty_result_when_no_corpora_available()
    {
        // Create orchestrator without any search services
        $orchestrator = new SearchOrchestrator(
            $this->aggregatorMock,
            $this->deduplicatorMock
        );

        $result = $orchestrator->search('test query', ['corpora' => ['laws']]);

        $this->assertEmpty($result['results']);
        $this->assertEquals(0, $result['metadata']['total_results']);
        $this->assertArrayHasKey('message', $result['metadata']);
    }

    /** @test */
    public function it_filters_unavailable_corpora_from_request()
    {
        $mockLawResults = [['id' => 'law-1', 'score' => 0.9]];

        $this->lawSearchMock->shouldReceive('search')->once()->andReturn($mockLawResults);
        $this->caseSearchMock->shouldReceive('search')->once()->andReturn([]);
        $this->aggregatorMock->shouldReceive('aggregate')->once()->andReturn($mockLawResults);
        $this->deduplicatorMock->shouldReceive('deduplicate')->once()->andReturn($mockLawResults);

        // Request includes 'decisions' which is not available
        $result = $this->orchestrator->search('test query', [
            'corpora' => ['laws', 'cases', 'decisions'],
        ]);

        // Should only search laws and cases
        $this->assertEquals(['laws', 'cases'], $result['metadata']['corpora_searched']);
    }

    /** @test */
    public function it_provides_list_of_available_corpora()
    {
        $available = $this->orchestrator->getAvailableCorpora();

        $this->assertContains('laws', $available);
        $this->assertContains('cases', $available);
    }

    /** @test */
    public function it_limits_per_page_to_maximum_of_100()
    {
        $mockResults = array_fill(0, 150, ['id' => 'doc', 'score' => 0.9]);

        $this->lawSearchMock->shouldReceive('search')->once()->andReturn($mockResults);
        $this->caseSearchMock->shouldReceive('search')->once()->andReturn([]);
        $this->aggregatorMock->shouldReceive('aggregate')->once()->andReturn($mockResults);
        $this->deduplicatorMock->shouldReceive('deduplicate')->once()->andReturn($mockResults);

        $result = $this->orchestrator->search('test query', ['per_page' => 200]);

        // Should be capped at 100
        $this->assertLessThanOrEqual(100, count($result['results']));
    }

    /** @test */
    public function it_uses_fuzzy_deduplication_strategy_when_requested()
    {
        $mockResults = [['id' => 'doc-1', 'score' => 0.9]];

        $this->lawSearchMock->shouldReceive('search')->once()->andReturn($mockResults);
        $this->caseSearchMock->shouldReceive('search')->once()->andReturn($mockResults);
        $this->aggregatorMock->shouldReceive('aggregate')->once()->andReturn($mockResults);

        $this->deduplicatorMock
            ->shouldReceive('deduplicate')
            ->once()
            ->with($mockResults, ['strategy' => 'fuzzy', 'keep_highest_score' => true])
            ->andReturn($mockResults);

        $this->orchestrator->search('test query', ['dedup_strategy' => 'fuzzy']);
    }
}

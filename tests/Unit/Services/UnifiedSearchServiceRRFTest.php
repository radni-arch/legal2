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
use Mockery;
use Tests\TestCase;

/**
 * Tests for Reciprocal Rank Fusion (RRF) score calculation correctness
 * in hybrid search mode.
 *
 * RRF formula: score(doc) = sum(weight / (k + rank + 1))
 * Default: k=60, vector weight=0.60, fulltext weight=0.30, citation weight=0.10
 */
class UnifiedSearchServiceRRFTest extends TestCase
{
    protected UnifiedSearchService $searchService;

    protected function setUp(): void
    {
        parent::setUp();

        $openAIMock = Mockery::mock(OpenAIService::class);
        $citationDetectorMock = Mockery::mock(HrLegalCitationsDetector::class);
        $embeddingServiceMock = Mockery::mock(SearchEmbeddingService::class);

        $this->lawSearchMock = Mockery::mock(LawSearchService::class);
        $this->decisionSearchMock = Mockery::mock(DecisionSearchService::class);
        $this->caseSearchMock = Mockery::mock(CaseSearchService::class);
        $this->fullTextSearchMock = Mockery::mock(FullTextSearchService::class);
        $this->citationSearchMock = Mockery::mock(CitationSearchService::class);

        // Defaults
        $this->lawSearchMock->shouldReceive('search')->andReturn([])->byDefault();
        $this->decisionSearchMock->shouldReceive('search')->andReturn([])->byDefault();
        $this->caseSearchMock->shouldReceive('search')->andReturn([])->byDefault();
        $this->fullTextSearchMock->shouldReceive('search')->andReturn([])->byDefault();
        $this->citationSearchMock->shouldReceive('search')->andReturn([])->byDefault();

        $this->searchService = new UnifiedSearchService(
            $openAIMock,
            $citationDetectorMock,
            $embeddingServiceMock,
            $this->lawSearchMock,
            $this->decisionSearchMock,
            $this->caseSearchMock,
            new SearchResultAggregator(),
            new SearchResultDeduplicator(),
            $this->fullTextSearchMock,
            $this->citationSearchMock,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected function makeResult(string $type, string $id, float $score): array
    {
        return [
            'type' => $type,
            'id' => $id,
            'title' => "Result {$id}",
            'snippet' => 'Test content...',
            'score' => $score,
            'metadata' => ['content_hash' => md5($id)],
        ];
    }

    /** @test */
    public function rrf_score_for_single_vector_result_at_rank_0(): void
    {
        // RRF score = 0.60 / (60 + 0 + 1) = 0.60 / 61 ≈ 0.0098
        $vectorResult = $this->makeResult('law', 'law-1', 0.95);

        $this->lawSearchMock->shouldReceive('search')->andReturn([$vectorResult]);

        $response = $this->searchService->hybridSearch('test query', [
            'corpora' => ['laws'],
        ]);

        $results = $response['data'];
        $this->assertCount(1, $results);

        $expected = round(0.60 / 61, 4);
        $this->assertEquals($expected, $results[0]['score']);
        $this->assertContains('vector', $results[0]['rrf_sources']);
    }

    /** @test */
    public function rrf_score_accumulates_across_multiple_search_methods(): void
    {
        // Same document appears in both vector and full-text search
        $vectorResult = $this->makeResult('law', 'law-1', 0.95);
        $fulltextResult = $this->makeResult('law', 'law-1', 0.80);

        $this->lawSearchMock->shouldReceive('search')->andReturn([$vectorResult]);
        $this->fullTextSearchMock->shouldReceive('search')->andReturn([$fulltextResult]);

        $response = $this->searchService->hybridSearch('test query', [
            'corpora' => ['laws'],
        ]);

        $results = $response['data'];
        $this->assertCount(1, $results); // Deduped into one result

        // RRF = vector_score + fulltext_score
        // = 0.60 / (60 + 0 + 1) + 0.30 / (60 + 0 + 1)
        // = 0.60/61 + 0.30/61 = 0.90/61 ≈ 0.0148
        $expected = round(0.60 / 61 + 0.30 / 61, 4);
        $this->assertEquals($expected, $results[0]['score']);
        $this->assertContains('vector', $results[0]['rrf_sources']);
        $this->assertContains('fulltext', $results[0]['rrf_sources']);
    }

    /** @test */
    public function rrf_score_accumulates_all_three_search_methods(): void
    {
        $result = $this->makeResult('law', 'law-1', 0.90);

        $this->lawSearchMock->shouldReceive('search')->andReturn([$result]);
        $this->fullTextSearchMock->shouldReceive('search')->andReturn([$result]);
        $this->citationSearchMock->shouldReceive('search')->andReturn([$result]);

        $response = $this->searchService->hybridSearch('test query', [
            'corpora' => ['laws'],
        ]);

        $results = $response['data'];
        $this->assertCount(1, $results);

        // RRF = 0.60/61 + 0.30/61 + 0.10/61 = 1.00/61
        $expected = round(1.00 / 61, 4);
        $this->assertEquals($expected, $results[0]['score']);
        $this->assertCount(3, $results[0]['rrf_sources']);
    }

    /** @test */
    public function rrf_ranking_penalizes_lower_ranks(): void
    {
        // Two vector results at different ranks
        $result1 = $this->makeResult('law', 'law-1', 0.95);
        $result2 = $this->makeResult('law', 'law-2', 0.85);

        $this->lawSearchMock->shouldReceive('search')->andReturn([$result1, $result2]);

        $response = $this->searchService->hybridSearch('test query', [
            'corpora' => ['laws'],
        ]);

        $results = $response['data'];
        $this->assertCount(2, $results);

        // Rank 0: 0.60 / (60 + 0 + 1) = 0.60/61
        // Rank 1: 0.60 / (60 + 1 + 1) = 0.60/62
        $score1 = round(0.60 / 61, 4);
        $score2 = round(0.60 / 62, 4);

        $this->assertEquals($score1, $results[0]['score']);
        $this->assertEquals($score2, $results[1]['score']);
        $this->assertGreaterThan($results[1]['score'], $results[0]['score']);
    }

    /** @test */
    public function rrf_document_in_vector_and_fulltext_beats_vector_only(): void
    {
        // doc-A: appears in vector rank 1 only
        // doc-B: appears in vector rank 0 AND fulltext rank 0
        $docA = $this->makeResult('law', 'law-A', 0.99);
        $docB = $this->makeResult('law', 'law-B', 0.80);

        $this->lawSearchMock->shouldReceive('search')->andReturn([$docB, $docA]); // B at rank 0, A at rank 1
        $this->fullTextSearchMock->shouldReceive('search')->andReturn([$docB]); // B at rank 0

        $response = $this->searchService->hybridSearch('test query', [
            'corpora' => ['laws'],
        ]);

        $results = $response['data'];
        $this->assertCount(2, $results);

        // doc-B: vector rank 0 + fulltext rank 0 = 0.60/61 + 0.30/61 = 0.90/61
        // doc-A: vector rank 1 only = 0.60/62
        $scoreB = round(0.60 / 61 + 0.30 / 61, 4);
        $scoreA = round(0.60 / 62, 4);

        $this->assertEquals('law-B', $results[0]['id']); // B should be first
        $this->assertEquals($scoreB, $results[0]['score']);
        $this->assertEquals('law-A', $results[1]['id']);
        $this->assertEquals($scoreA, $results[1]['score']);
    }

    /** @test */
    public function rrf_with_empty_results_returns_empty(): void
    {
        $response = $this->searchService->hybridSearch('test query', [
            'corpora' => ['laws'],
        ]);

        $this->assertEmpty($response['data']);
        $this->assertEquals(0, $response['metadata']['total_results']);
    }

    /** @test */
    public function hybrid_search_metadata_includes_result_counts(): void
    {
        $result1 = $this->makeResult('law', 'law-1', 0.90);
        $result2 = $this->makeResult('decision', 'dec-1', 0.85);

        $this->lawSearchMock->shouldReceive('search')->andReturn([$result1]);
        $this->fullTextSearchMock->shouldReceive('search')->andReturn([$result1, $result2]);
        $this->citationSearchMock->shouldReceive('search')->andReturn([$result2]);

        $response = $this->searchService->hybridSearch('test query', [
            'corpora' => ['laws', 'decisions'],
        ]);

        $counts = $response['metadata']['result_counts'];
        $this->assertEquals(1, $counts['vector']);
        $this->assertEquals(2, $counts['fulltext']);
        $this->assertEquals(1, $counts['citation']);
        $this->assertEquals('hybrid', $response['metadata']['search_type']);
    }

    /** @test */
    public function hybrid_search_applies_deduplication(): void
    {
        // Same document with same ID in vector and fulltext - should be merged by RRF
        $vectorResult = $this->makeResult('law', 'law-1', 0.95);
        $fulltextResult = $this->makeResult('law', 'law-1', 0.80);

        $this->lawSearchMock->shouldReceive('search')->andReturn([$vectorResult]);
        $this->fullTextSearchMock->shouldReceive('search')->andReturn([$fulltextResult]);

        $response = $this->searchService->hybridSearch('test query', [
            'corpora' => ['laws'],
            'deduplicate' => true,
        ]);

        // RRF already merges by unique document ID, then deduplicator runs on top
        $this->assertCount(1, $response['data']);
    }

    /** @test */
    public function hybrid_search_respects_pagination(): void
    {
        $results = [];
        for ($i = 1; $i <= 5; $i++) {
            $results[] = $this->makeResult('law', "law-{$i}", 1.0 - ($i * 0.1));
        }

        $this->lawSearchMock->shouldReceive('search')->andReturn($results);

        $response = $this->searchService->hybridSearch('test query', [
            'corpora' => ['laws'],
            'page' => 2,
            'per_page' => 2,
        ]);

        $this->assertEquals(5, $response['metadata']['total_results']);
        $this->assertCount(2, $response['data']);
        $this->assertEquals(2, $response['metadata']['pagination']['current_page']);
    }
}

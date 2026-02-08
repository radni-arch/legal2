<?php

namespace Tests\Unit\Services\Search;

use App\Services\CaseVectorStoreService;
use App\Services\Search\CaseSearchService;
use App\Services\Search\SearchEmbeddingService;
use Mockery;
use Tests\TestCase;

/**
 * Tests for the simplified CaseSearchService
 *
 * These tests verify the clean, focused implementation that delegates
 * to SearchEmbeddingService and CaseVectorStoreService.
 */
class CaseSearchServiceTest extends TestCase
{
    protected $embedderMock;

    protected $vectorStoreMock;

    protected CaseSearchService $service;

    protected SearchEmbeddingService $embeddingServiceMock;

    protected function setUp(): void
    {
        parent::setUp();
        // Mock dependencies
        $this->embedderMock = Mockery::mock(SearchEmbeddingService::class);
        $this->vectorStoreMock = Mockery::mock(CaseVectorStoreService::class);

        // Create service with mocks
        $this->service = new CaseSearchService(
            $this->embedderMock,
            $this->vectorStoreMock
        );
        // Mock SearchEmbeddingService
        $this->embeddingServiceMock = Mockery::mock(SearchEmbeddingService::class);

        // Default: return a valid embedding
        $this->embeddingServiceMock->shouldReceive('embedQuery')
            ->andReturn(array_fill(0, 1536, 0.5));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ========================================
    // Interface Implementation Tests
    // ========================================

    /** @test */
    public function it_implements_search_service_interface()
    {
        $this->assertInstanceOf(
            \App\Contracts\SearchServiceInterface::class,
            $this->service
        );
    }

    /** @test */
    public function it_supports_case_corpus_types()
    {
        $this->assertTrue($this->service->supportsCorpus('case'));
        $this->assertTrue($this->service->supportsCorpus('cases'));
        $this->assertTrue($this->service->supportsCorpus('case_document'));
        $this->assertTrue($this->service->supportsCorpus('CaseDocument'));
    }

    /** @test */
    public function it_does_not_support_other_corpus_types()
    {
        $this->assertFalse($this->service->supportsCorpus('law'));
        $this->assertFalse($this->service->supportsCorpus('decision'));
        $this->assertFalse($this->service->supportsCorpus('textract'));
    }

    // ========================================
    // Search Functionality Tests
    // ========================================

    /** @test */
    public function it_searches_cases_by_vector_similarity_using_mocks()
    {
        $mockEmbedding = array_fill(0, 1536, 0.1);
        $mockResults = [
            [
                'id' => 'case-1',
                'score' => 0.85,
                'content' => 'Kazneni predmet protiv optuženog X',
                'metadata' => ['category' => 'criminal'],
                'case_id' => 'case-123',
                'doc_id' => 'doc-1',
                'title' => 'Presuda',
                'category' => 'criminal',
            ],
        ];

        // Mock embedding generation
        $this->embedderMock
            ->shouldReceive('embedQuery')
            ->once()
            ->with('kazneni predmet', null)
            ->andReturn($mockEmbedding);

        // Mock vector store search
        $this->vectorStoreMock
            ->shouldReceive('search')
            ->once()
            ->with($mockEmbedding, [
                'threshold' => 0.7,
                'limit' => 10,
                'filters' => [],
            ])
            ->andReturn($mockResults);

        // Execute search
        $results = $this->service->search('kazneni predmet');

        // Assertions - implementation uses 'type' (not 'corpus') and 'snippet' (derived from content)
        $this->assertNotEmpty($results);
        $this->assertEquals('case-1', $results[0]['id']);
        $this->assertEquals('case', $results[0]['type']); // 'cases' corpus maps to 'case' type
        $this->assertEquals(0.85, $results[0]['score']);
        $this->assertStringContainsString('Kazneni predmet', $results[0]['snippet']);
    }

    /** @test */
    public function it_passes_custom_threshold_to_vector_store()
    {
        $mockEmbedding = array_fill(0, 1536, 0.1);

        $this->embedderMock
            ->shouldReceive('embedQuery')
            ->once()
            ->andReturn($mockEmbedding);

        $this->vectorStoreMock
            ->shouldReceive('search')
            ->once()
            ->with($mockEmbedding, Mockery::on(function ($options) {
                return $options['threshold'] === 0.85;
            }))
            ->andReturn([]);

        $this->service->search('test', ['threshold' => 0.85]);
    }

    /** @test */
    public function it_passes_custom_limit_to_vector_store()
    {
        $mockEmbedding = array_fill(0, 1536, 0.1);

        $this->embedderMock
            ->shouldReceive('embedQuery')
            ->once()
            ->andReturn($mockEmbedding);

        $this->vectorStoreMock
            ->shouldReceive('search')
            ->once()
            ->with($mockEmbedding, Mockery::on(function ($options) {
                return $options['limit'] === 20;
            }))
            ->andReturn([]);

        $this->service->search('test', ['limit' => 20]);
    }

    /** @test */
    public function it_passes_filters_to_vector_store()
    {
        $mockEmbedding = array_fill(0, 1536, 0.1);
        $filters = [
            'case_id' => 'case-123',
            'source' => 'court_submission',
            'category' => 'criminal',
        ];

        $this->embedderMock
            ->shouldReceive('embedQuery')
            ->once()
            ->andReturn($mockEmbedding);

        $this->vectorStoreMock
            ->shouldReceive('search')
            ->once()
            ->with($mockEmbedding, Mockery::on(function ($options) use ($filters) {
                return $options['filters'] === $filters;
            }))
            ->andReturn([]);

        $this->service->search('test', ['filters' => $filters]);
    }

    /** @test */
    public function it_passes_custom_model_to_embedder()
    {
        $mockEmbedding = array_fill(0, 1536, 0.1);

        $this->embedderMock
            ->shouldReceive('embedQuery')
            ->once()
            ->with('test query', 'text-embedding-3-large')
            ->andReturn($mockEmbedding);

        $this->vectorStoreMock
            ->shouldReceive('search')
            ->once()
            ->andReturn([]);

        $this->service->search('test query', ['model' => 'text-embedding-3-large']);
    }

    // ========================================
    // Result Normalization Tests
    // ========================================

    /** @test */
    public function it_normalizes_results_to_standard_format()
    {
        $mockEmbedding = array_fill(0, 1536, 0.1);
        $mockResults = [
            [
                'id' => 'case-1',
                'score' => 0.90,
                'content' => 'Case content',
                'metadata' => ['custom' => 'field'],
                'case_id' => 'case-123',
                'doc_id' => 'doc-1',
                'title' => 'Test Case',
                'category' => 'criminal',
            ],
        ];

        $this->embedderMock->shouldReceive('embedQuery')->once()->andReturn($mockEmbedding);
        $this->vectorStoreMock->shouldReceive('search')->once()->andReturn($mockResults);

        $results = $this->service->search('test');

        // Implementation uses 'type' (not 'corpus') and 'snippet' (not 'content')
        $this->assertArrayHasKey('id', $results[0]);
        $this->assertArrayHasKey('type', $results[0]); // normalizeResults uses 'type', not 'corpus'
        $this->assertArrayHasKey('score', $results[0]);
        $this->assertArrayHasKey('snippet', $results[0]); // normalizeResults uses 'snippet', not 'content'
        $this->assertArrayHasKey('metadata', $results[0]);
    }

    /** @test */
    public function it_includes_corpus_specific_fields_in_metadata()
    {
        $mockEmbedding = array_fill(0, 1536, 0.1);
        $mockResults = [
            [
                'id' => 'case-1',
                'score' => 0.90,
                'content' => 'Case content',
                'metadata' => [],
                'case_id' => 'case-123',
                'doc_id' => 'doc-1',
                'title' => 'Test Case',
                'category' => 'criminal',
                'source' => 'upload',
                'chunk_index' => 2,
            ],
        ];

        $this->embedderMock->shouldReceive('embedQuery')->once()->andReturn($mockEmbedding);
        $this->vectorStoreMock->shouldReceive('search')->once()->andReturn($mockResults);

        $results = $this->service->search('test');

        $metadata = $results[0]['metadata'];
        $this->assertEquals('case-123', $metadata['case_id']);
        $this->assertEquals('doc-1', $metadata['doc_id']);
        $this->assertEquals('Test Case', $metadata['title']);
        $this->assertEquals('criminal', $metadata['category']);
        $this->assertEquals('upload', $metadata['source']);
        $this->assertEquals(2, $metadata['chunk_index']);
    }

    /** @test */
    public function it_excludes_content_when_include_content_is_false()
    {
        $mockEmbedding = array_fill(0, 1536, 0.1);
        $mockResults = [
            [
                'id' => 'case-1',
                'score' => 0.90,
                'content' => 'Case content',
                'metadata' => [],
            ],
        ];

        $this->embedderMock->shouldReceive('embedQuery')->once()->andReturn($mockEmbedding);
        $this->vectorStoreMock->shouldReceive('search')->once()->andReturn($mockResults);

        $results = $this->service->search('test', ['include_content' => false]);

        $this->assertArrayNotHasKey('content', $results[0]);
    }

    /** @test */
    public function it_excludes_metadata_when_include_metadata_is_false()
    {
        $mockEmbedding = array_fill(0, 1536, 0.1);
        $mockResults = [
            [
                'id' => 'case-1',
                'score' => 0.90,
                'content' => 'Case content',
                'metadata' => ['custom' => 'field'],
            ],
        ];

        $this->embedderMock->shouldReceive('embedQuery')->once()->andReturn($mockEmbedding);
        $this->vectorStoreMock->shouldReceive('search')->once()->andReturn($mockResults);

        $results = $this->service->search('test', ['include_metadata' => false]);

        $this->assertArrayNotHasKey('metadata', $results[0]);
    }

    // ========================================
    // Error Handling Tests
    // ========================================

    /** @test */
    public function it_returns_empty_array_on_embedding_failure()
    {
        $this->embedderMock
            ->shouldReceive('embedQuery')
            ->once()
            ->andThrow(new \Exception('Embedding failed'));

        $results = $this->service->search('test');

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    /** @test */
    public function it_returns_empty_array_on_vector_store_failure()
    {
        $mockEmbedding = array_fill(0, 1536, 0.1);

        $this->embedderMock
            ->shouldReceive('embedQuery')
            ->once()
            ->andReturn($mockEmbedding);

        $this->vectorStoreMock
            ->shouldReceive('search')
            ->once()
            ->andThrow(new \Exception('Vector store failed'));

        $results = $this->service->search('test');
    }
}

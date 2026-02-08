<?php

namespace Tests\Unit\Services\Search;

use App\Services\LawVectorStoreService;
use App\Services\Search\LawSearchService;
use App\Services\Search\SearchEmbeddingService;
use Mockery;
use Tests\TestCase;

/**
 * Tests for the new simplified LawSearchService
 *
 * These tests verify the clean, focused implementation that delegates
 * to SearchEmbeddingService and LawVectorStoreService.
 */
class LawSearchServiceTest extends TestCase
{
    protected $embedderMock;

    protected $vectorStoreMock;

    protected LawSearchService $service;

    protected SearchEmbeddingService $embeddingServiceMock;

    protected function setUp(): void
    {
        parent::setUp();
        // Mock dependencies
        $this->embedderMock = Mockery::mock(SearchEmbeddingService::class);
        $this->vectorStoreMock = Mockery::mock(LawVectorStoreService::class);

        // Default: vector store is available
        $this->vectorStoreMock->shouldReceive('isAvailable')
            ->andReturn(true)
            ->byDefault();

        // Create service with mocks
        $this->service = new LawSearchService(
            $this->embedderMock,
            $this->vectorStoreMock
        );

        // Mock SearchEmbeddingService (used by some database-style tests)
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
    public function it_supports_law_corpus_types()
    {
        $this->assertTrue($this->service->supportsCorpus('law'));
        $this->assertTrue($this->service->supportsCorpus('laws'));
        $this->assertTrue($this->service->supportsCorpus('LawDocument'));
    }

    /** @test */
    public function it_does_not_support_other_corpus_types()
    {
        $this->assertFalse($this->service->supportsCorpus('case'));
        $this->assertFalse($this->service->supportsCorpus('decision'));
        $this->assertFalse($this->service->supportsCorpus('textract'));
    }

    // ========================================
    // Search Functionality Tests
    // ========================================

    /** @test */
    public function it_searches_laws_by_vector_similarity()
    {
        $mockEmbedding = array_fill(0, 1536, 0.1);
        $mockResults = [
            [
                'id' => 'law-1',
                'score' => 0.85,
                'content' => 'Zakon o kaznenom postupku',
                'metadata' => ['jurisdiction' => 'HR'],
                'doc_id' => 'doc-1',
                'title' => 'Kazneni zakon',
                'law_number' => 'NN 125/11',
                'jurisdiction' => 'Republika Hrvatska',
            ],
        ];

        // Mock embedding generation
        $this->embedderMock
            ->shouldReceive('embedQuery')
            ->once()
            ->with('kazneni postupak', null)
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
        $results = $this->service->search('kazneni postupak');

        // Assertions
        $this->assertNotEmpty($results);
        $this->assertEquals('law-1', $results[0]['id']);
        $this->assertEquals('law', $results[0]['type']);
        $this->assertEquals(0.85, $results[0]['score']);
        $this->assertEquals('Zakon o kaznenom postupku', $results[0]['snippet']);
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

        $results = $this->service->search('test', ['threshold' => 0.85]);

        $this->assertIsArray($results);
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

        $results = $this->service->search('test', ['limit' => 20]);

        $this->assertIsArray($results);
    }

    /** @test */
    public function it_passes_filters_to_vector_store()
    {
        $mockEmbedding = array_fill(0, 1536, 0.1);
        $filters = [
            'jurisdiction' => 'HR',
            'law_number' => 'NN 125',
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

        $results = $this->service->search('test', ['filters' => $filters]);

        $this->assertIsArray($results);
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

        $results = $this->service->search('test query', ['model' => 'text-embedding-3-large']);

        $this->assertIsArray($results);
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
                'id' => 'law-1',
                'score' => 0.90,
                'content' => 'Law content',
                'metadata' => ['custom' => 'field'],
                'doc_id' => 'doc-1',
                'title' => 'Test Law',
                'law_number' => 'NN 100/2024',
                'jurisdiction' => 'HR',
            ],
        ];

        $this->embedderMock->shouldReceive('embedQuery')->once()->andReturn($mockEmbedding);
        $this->vectorStoreMock->shouldReceive('search')->once()->andReturn($mockResults);

        $results = $this->service->search('test');

        $this->assertArrayHasKey('id', $results[0]);
        $this->assertArrayHasKey('type', $results[0]);
        $this->assertArrayHasKey('score', $results[0]);
        $this->assertArrayHasKey('snippet', $results[0]);
        $this->assertArrayHasKey('metadata', $results[0]);
    }

    /** @test */
    public function it_includes_corpus_specific_fields_in_metadata()
    {
        $mockEmbedding = array_fill(0, 1536, 0.1);
        $mockResults = [
            [
                'id' => 'law-1',
                'score' => 0.90,
                'content' => 'Law content',
                'metadata' => [],
                'doc_id' => 'doc-1',
                'title' => 'Test Law',
                'law_number' => 'NN 100/2024',
                'jurisdiction' => 'HR',
                'chunk_index' => 5,
                'effective_date' => '2024-01-01',
            ],
        ];

        $this->embedderMock->shouldReceive('embedQuery')->once()->andReturn($mockEmbedding);
        $this->vectorStoreMock->shouldReceive('search')->once()->andReturn($mockResults);

        $results = $this->service->search('test');

        $metadata = $results[0]['metadata'];
        $this->assertEquals('doc-1', $metadata['doc_id']);
        $this->assertEquals('Test Law', $metadata['title']);
        $this->assertEquals('NN 100/2024', $metadata['law_number']);
        $this->assertEquals('HR', $metadata['jurisdiction']);
        $this->assertEquals(5, $metadata['chunk_index']);
        $this->assertEquals('2024-01-01', $metadata['effective_date']);
    }

    /** @test */
    public function it_excludes_content_when_include_content_is_false()
    {
        $mockEmbedding = array_fill(0, 1536, 0.1);
        $mockResults = [
            [
                'id' => 'law-1',
                'score' => 0.90,
                'content' => 'Law content',
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
                'id' => 'law-1',
                'score' => 0.90,
                'content' => 'Law content',
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

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    /**
     * Test 2: It filters by jurisdiction
     *
     * @test
     */
    public function it_filters_by_jurisdiction()
    {
        $mockEmbedding = array_fill(0, 1536, 0.1);
        $mockResults = [
            [
                'id' => 'law-1',
                'score' => 0.90,
                'content' => 'Test content',
                'metadata' => [],
                'title' => 'Kazneni zakon',
                'jurisdiction' => 'criminal',
            ],
        ];

        $this->embedderMock->shouldReceive('embedQuery')->once()->andReturn($mockEmbedding);

        // Verify filters are passed to vector store
        $this->vectorStoreMock->shouldReceive('search')
            ->once()
            ->with($mockEmbedding, Mockery::on(function ($options) {
                return isset($options['filters']['jurisdiction'])
                    && $options['filters']['jurisdiction'] === 'criminal';
            }))
            ->andReturn($mockResults);

        $results = $this->service->search('test', [
            'threshold' => 0.0,
            'filters' => ['jurisdiction' => 'criminal'],
        ]);

        // Assert: Should return results with criminal jurisdiction in metadata
        $this->assertNotEmpty($results);
        foreach ($results as $result) {
            $this->assertEquals('criminal', $result['metadata']['jurisdiction']);
        }
    }

    /**
     * Test 3: It filters by country
     *
     * @test
     */
    public function it_filters_by_country()
    {
        $mockEmbedding = array_fill(0, 1536, 0.1);
        $mockResults = [
            [
                'id' => 'law-1',
                'score' => 0.90,
                'content' => 'Test content',
                'metadata' => [],
                'title' => 'Hrvatski zakon',
                'country' => 'HR',
            ],
        ];

        $this->embedderMock->shouldReceive('embedQuery')->once()->andReturn($mockEmbedding);

        // Verify country filter is passed to vector store
        $this->vectorStoreMock->shouldReceive('search')
            ->once()
            ->with($mockEmbedding, Mockery::on(function ($options) {
                return isset($options['filters']['country'])
                    && $options['filters']['country'] === 'HR';
            }))
            ->andReturn($mockResults);

        $results = $this->service->search('test', [
            'threshold' => 0.0,
            'filters' => ['country' => 'HR'],
        ]);

        // Assert: Should return results with HR country in metadata
        $this->assertNotEmpty($results);
        foreach ($results as $result) {
            $this->assertEquals('HR', $result['metadata']['country']);
        }
    }

    /**
     * Test 4: It filters by language
     *
     * @test
     */
    public function it_filters_by_language()
    {
        $mockEmbedding = array_fill(0, 1536, 0.1);
        $mockResults = [
            [
                'id' => 'law-1',
                'score' => 0.90,
                'content' => 'Test content',
                'metadata' => [],
                'title' => 'Hrvatski zakon',
                'language' => 'hr',
            ],
        ];

        $this->embedderMock->shouldReceive('embedQuery')->once()->andReturn($mockEmbedding);

        // Verify language filter is passed to vector store
        $this->vectorStoreMock->shouldReceive('search')
            ->once()
            ->with($mockEmbedding, Mockery::on(function ($options) {
                return isset($options['filters']['language'])
                    && $options['filters']['language'] === 'hr';
            }))
            ->andReturn($mockResults);

        $results = $this->service->search('test', [
            'threshold' => 0.0,
            'filters' => ['language' => 'hr'],
        ]);

        // Assert: Should return results with hr language in metadata
        $this->assertNotEmpty($results);
        foreach ($results as $result) {
            $this->assertEquals('hr', $result['metadata']['language']);
        }
    }

    /**
     * Test 5: It filters by date range
     *
     * @test
     */
    public function it_filters_by_date_range()
    {
        $mockEmbedding = array_fill(0, 1536, 0.1);
        $mockResults = [
            [
                'id' => 'law-1',
                'score' => 0.90,
                'content' => 'Test content',
                'metadata' => [],
                'title' => 'New Law',
                'promulgation_date' => '2023-01-01',
            ],
        ];

        $this->embedderMock->shouldReceive('embedQuery')->once()->andReturn($mockEmbedding);

        // Verify date range filters are passed to vector store
        $this->vectorStoreMock->shouldReceive('search')
            ->once()
            ->with($mockEmbedding, Mockery::on(function ($options) {
                return isset($options['filters']['date_from'])
                    && $options['filters']['date_from'] === '2020-01-01'
                    && isset($options['filters']['date_to'])
                    && $options['filters']['date_to'] === '2024-01-01';
            }))
            ->andReturn($mockResults);

        $results = $this->service->search('test', [
            'threshold' => 0.0,
            'filters' => [
                'date_from' => '2020-01-01',
                'date_to' => '2024-01-01',
            ],
        ]);

        // Assert: Should return results with valid promulgation date
        $this->assertNotEmpty($results);
        foreach ($results as $result) {
            $date = $result['metadata']['promulgation_date'];
            $this->assertGreaterThanOrEqual('2020-01-01', $date);
            $this->assertLessThanOrEqual('2024-01-01', $date);
        }
    }

    /**
     * Test 6: It respects similarity threshold
     *
     * @test
     */
    public function it_respects_similarity_threshold()
    {
        $mockEmbedding = array_fill(0, 1536, 0.1);
        $mockResults = [
            [
                'id' => 'law-1',
                'score' => 0.97,
                'content' => 'Test content',
                'metadata' => [],
                'title' => 'Test Law',
            ],
        ];

        $this->embedderMock->shouldReceive('embedQuery')->once()->andReturn($mockEmbedding);

        // Verify threshold is passed to vector store
        $this->vectorStoreMock->shouldReceive('search')
            ->once()
            ->with($mockEmbedding, Mockery::on(function ($options) {
                return $options['threshold'] === 0.95;
            }))
            ->andReturn($mockResults);

        $results = $this->service->search('test', [
            'threshold' => 0.95,
        ]);

        // Assert: Results should meet threshold
        foreach ($results as $result) {
            $this->assertGreaterThanOrEqual(0.95, $result['score']);
        }
    }

    /**
     * Test 7: It respects result limit
     *
     * @test
     */
    public function it_respects_result_limit()
    {
        $mockEmbedding = array_fill(0, 1536, 0.1);

        // Vector store returns exactly 5 results (honoring the limit)
        $mockResults = [];
        for ($i = 0; $i < 5; $i++) {
            $mockResults[] = [
                'id' => "law-{$i}",
                'score' => 0.90 - ($i * 0.01),
                'content' => "Test content {$i}",
                'metadata' => [],
                'title' => "Law {$i}",
            ];
        }

        $this->embedderMock->shouldReceive('embedQuery')->once()->andReturn($mockEmbedding);

        // Verify limit is passed to vector store
        $this->vectorStoreMock->shouldReceive('search')
            ->once()
            ->with($mockEmbedding, Mockery::on(function ($options) {
                return $options['limit'] === 5;
            }))
            ->andReturn($mockResults);

        $results = $this->service->search('test', [
            'threshold' => 0.0,
            'limit' => 5,
        ]);

        // Assert: Should return at most 5 results
        $this->assertLessThanOrEqual(5, count($results));
    }

    /**
     * Test 8: It includes all required metadata
     *
     * @test
     */
    public function it_includes_required_metadata()
    {
        $mockEmbedding = array_fill(0, 1536, 0.1);
        $mockResults = [
            [
                'id' => 'law-1',
                'score' => 0.90,
                'content' => 'Test content',
                'metadata' => [
                    'law_id' => 42,
                    'short_title' => 'ZKP',
                    'official_gazette' => 'NN 152/2008',
                ],
                'title' => 'Zakon o kaznenom postupku',
                'jurisdiction' => 'criminal',
                'country' => 'HR',
                'language' => 'hr',
                'promulgation_date' => '2008-12-23',
            ],
        ];

        $this->embedderMock->shouldReceive('embedQuery')->once()->andReturn($mockEmbedding);
        $this->vectorStoreMock->shouldReceive('search')->once()->andReturn($mockResults);

        $results = $this->service->search('test', ['threshold' => 0.0]);

        // Assert: Should include all metadata
        $this->assertNotEmpty($results);
        $metadata = $results[0]['metadata'];

        $this->assertArrayHasKey('law_id', $metadata);
        $this->assertArrayHasKey('short_title', $metadata);
        $this->assertArrayHasKey('official_gazette', $metadata);
        $this->assertArrayHasKey('jurisdiction', $metadata);
        $this->assertArrayHasKey('country', $metadata);
        $this->assertArrayHasKey('language', $metadata);
        $this->assertArrayHasKey('promulgation_date', $metadata);
    }

    /**
     * Test 9: It generates snippets from content
     *
     * @test
     */
    public function it_generates_snippets()
    {
        $mockEmbedding = array_fill(0, 1536, 0.1);
        $longContent = str_repeat('Članak 1. Ovo je vrlo dug zakonski tekst. ', 100);

        $mockResults = [
            [
                'id' => 'law-1',
                'score' => 0.90,
                'content' => $longContent,
                'metadata' => [],
                'title' => 'Test Law',
            ],
        ];

        $this->embedderMock->shouldReceive('embedQuery')->once()->andReturn($mockEmbedding);
        $this->vectorStoreMock->shouldReceive('search')->once()->andReturn($mockResults);

        $results = $this->service->search('test', ['threshold' => 0.0]);

        // Assert: Should have snippet truncated to max length
        $this->assertNotEmpty($results);
        $this->assertArrayHasKey('snippet', $results[0]);
        $this->assertLessThanOrEqual(203, mb_strlen($results[0]['snippet']));
    }

    /**
     * Test 10: It returns empty array for no matches
     *
     * @test
     */
    public function it_returns_empty_array_for_no_matches()
    {
        $mockEmbedding = array_fill(0, 1536, 0.1);

        $this->embedderMock->shouldReceive('embedQuery')->once()->andReturn($mockEmbedding);
        $this->vectorStoreMock->shouldReceive('search')->once()->andReturn([]);

        $results = $this->service->search('nonexistent', ['threshold' => 0.9]);

        // Assert: Should return empty array
        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    // ========================================
    // Integration-Style Tests
    // ========================================

    /** @test */
    public function it_handles_multiple_results()
    {
        $mockEmbedding = array_fill(0, 1536, 0.1);
        $mockResults = [
            [
                'id' => 'law-1',
                'score' => 0.90,
                'content' => 'Content 1',
                'metadata' => [],
            ],
            [
                'id' => 'law-2',
                'score' => 0.85,
                'content' => 'Content 2',
                'metadata' => [],
            ],
            [
                'id' => 'law-3',
                'score' => 0.80,
                'content' => 'Content 3',
                'metadata' => [],
            ],
        ];

        $this->embedderMock->shouldReceive('embedQuery')->once()->andReturn($mockEmbedding);
        $this->vectorStoreMock->shouldReceive('search')->once()->andReturn($mockResults);

        $results = $this->service->search('test');

        $this->assertCount(3, $results);
        $this->assertEquals('law-1', $results[0]['id']);
        $this->assertEquals('law-2', $results[1]['id']);
        $this->assertEquals('law-3', $results[2]['id']);
    }

    /** @test */
    public function it_handles_empty_results()
    {
        $mockEmbedding = array_fill(0, 1536, 0.1);

        $this->embedderMock->shouldReceive('embedQuery')->once()->andReturn($mockEmbedding);
        $this->vectorStoreMock->shouldReceive('search')->once()->andReturn([]);

        $results = $this->service->search('test');

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    /**
     * Test 11: It can be instantiated via container
     *
     * @test
     */
    public function it_can_be_instantiated_via_container()
    {
        // Act: Resolve from container
        $service = app(LawSearchService::class);

        // Assert: Should be instance of LawSearchService
        $this->assertInstanceOf(LawSearchService::class, $service);
    }

    /**
     * Test 12: It uses provided embedding model
     *
     * @test
     */
    public function it_uses_provided_embedding_model()
    {
        // Arrange: Mock embedding service with model expectation
        $embeddingService = Mockery::mock(SearchEmbeddingService::class);
        $embeddingService->shouldReceive('embedQuery')
            ->once()
            ->with('test query', 'text-embedding-3-large')
            ->andReturn(array_fill(0, 1536, 0.5));

        // Vector store mock needs isAvailable and search
        $this->vectorStoreMock->shouldReceive('search')
            ->once()
            ->andReturn([]);

        // Act: Search with custom model
        $service = new LawSearchService($embeddingService, $this->vectorStoreMock);
        $results = $service->search('test query', [
            'model' => 'text-embedding-3-large',
            'threshold' => 0.0,
        ]);

        // Assert: Should use the model (verified by Mockery expectation)
        $this->assertIsArray($results);
    }
}

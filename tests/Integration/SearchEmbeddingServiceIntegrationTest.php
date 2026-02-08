<?php

namespace Tests\Integration;

use App\Services\Search\SearchEmbeddingService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Integration tests for SearchEmbeddingService
 *
 * These tests verify the embedding service works end-to-end with real
 * OpenAI API interactions (mocked for offline testing).
 */
class SearchEmbeddingServiceIntegrationTest extends TestCase
{
    protected SearchEmbeddingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Use real service with mocked HTTP for integration testing
        $this->service = app(SearchEmbeddingService::class);
    }

    /** @test */
    public function it_generates_embedding_for_single_query()
    {
        // Mock OpenAI API for offline testing
        Http::fake([
            'api.openai.com/*' => Http::response([
                'data' => [
                    [
                        'embedding' => array_fill(0, 1536, 0.123),
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

        // Act: Generate embedding
        $embedding = $this->service->embedQuery('Croatian criminal law');

        // Assert: Embedding has correct properties
        $this->assertIsArray($embedding);
        $this->assertNotEmpty($embedding);
        $this->assertCount(1536, $embedding);
        $this->assertTrue($this->service->isValidEmbedding($embedding));
    }

    /** @test */
    public function it_generates_embeddings_for_multiple_queries()
    {
        // Mock OpenAI API for batch request
        Http::fake([
            'api.openai.com/*' => Http::response([
                'data' => [
                    [
                        'embedding' => array_fill(0, 1536, 0.111),
                        'index' => 0,
                    ],
                    [
                        'embedding' => array_fill(0, 1536, 0.222),
                        'index' => 1,
                    ],
                    [
                        'embedding' => array_fill(0, 1536, 0.333),
                        'index' => 2,
                    ],
                ],
                'model' => 'text-embedding-3-small',
                'usage' => [
                    'prompt_tokens' => 15,
                    'total_tokens' => 15,
                ],
            ], 200),
        ]);

        // Act: Generate embeddings for multiple queries
        $queries = [
            'Croatian criminal law',
            'Court decisions about theft',
            'Contract law precedents',
        ];
        $embeddings = $this->service->embedQueries($queries);

        // Assert: All embeddings are valid
        $this->assertIsArray($embeddings);
        $this->assertCount(3, $embeddings);

        foreach ($embeddings as $embedding) {
            $this->assertIsArray($embedding);
            $this->assertCount(1536, $embedding);
            $this->assertTrue($this->service->isValidEmbedding($embedding));
        }
    }

    /** @test */
    public function it_returns_empty_array_on_api_failure()
    {
        // Mock OpenAI API failure
        Http::fake([
            'api.openai.com/*' => Http::response([
                'error' => [
                    'message' => 'API rate limit exceeded',
                    'type' => 'rate_limit_error',
                ],
            ], 429),
        ]);

        // Act: Attempt to generate embedding
        $embedding = $this->service->embedQuery('test query');

        // Assert: Returns empty array on failure (graceful degradation)
        $this->assertIsArray($embedding);
        $this->assertEmpty($embedding);
    }

    /** @test */
    public function it_validates_embedding_dimensions()
    {
        // Valid embedding
        $validEmbedding = array_fill(0, 1536, 0.5);
        $this->assertTrue($this->service->isValidEmbedding($validEmbedding));

        // Invalid: wrong dimension
        $wrongDimension = array_fill(0, 100, 0.5);
        $this->assertFalse($this->service->isValidEmbedding($wrongDimension));

        // Invalid: empty
        $this->assertFalse($this->service->isValidEmbedding([]));

        // Invalid: non-numeric values
        $nonNumeric = array_merge(array_fill(0, 1535, 0.5), ['invalid']);
        $this->assertFalse($this->service->isValidEmbedding($nonNumeric));
    }

    /** @test */
    public function it_supports_different_embedding_models()
    {
        // Mock OpenAI API with large model dimensions
        Http::fake([
            'api.openai.com/*' => Http::response([
                'data' => [
                    [
                        'embedding' => array_fill(0, 3072, 0.456),
                        'index' => 0,
                    ],
                ],
                'model' => 'text-embedding-3-large',
                'usage' => [
                    'prompt_tokens' => 5,
                    'total_tokens' => 5,
                ],
            ], 200),
        ]);

        // Act: Generate embedding with large model
        $embedding = $this->service->embedQuery('test', 'text-embedding-3-large');

        // Assert: Embedding has correct dimension for large model
        $this->assertIsArray($embedding);
        $this->assertCount(3072, $embedding);
        $this->assertTrue($this->service->isValidEmbedding($embedding, 3072));

        // Verify dimension lookup
        $this->assertEquals(3072, $this->service->getModelDimension('text-embedding-3-large'));
        $this->assertEquals(1536, $this->service->getModelDimension('text-embedding-3-small'));
    }

    /** @test */
    public function it_handles_empty_query_array_gracefully()
    {
        // Act: Attempt to embed empty array
        $embeddings = $this->service->embedQueries([]);

        // Assert: Returns empty array
        $this->assertIsArray($embeddings);
        $this->assertEmpty($embeddings);
    }

    /** @test */
    public function it_uses_configured_default_model()
    {
        // Act: Get default model
        $defaultModel = $this->service->getDefaultModel();

        // Assert: Returns configured model
        $this->assertIsString($defaultModel);
        $this->assertNotEmpty($defaultModel);
        $this->assertContains($defaultModel, [
            'text-embedding-3-small',
            'text-embedding-3-large',
            'text-embedding-ada-002',
        ]);
    }

    /** @test */
    public function it_generates_consistent_embeddings_for_same_query()
    {
        // Mock OpenAI API with consistent response
        Http::fake([
            'api.openai.com/*' => Http::response([
                'data' => [
                    [
                        'embedding' => array_fill(0, 1536, 0.789),
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

        // Act: Generate embedding twice for same query
        $embedding1 = $this->service->embedQuery('same query');
        $embedding2 = $this->service->embedQuery('same query');

        // Assert: Both embeddings should be equal (OpenAI returns consistent embeddings)
        $this->assertEquals($embedding1, $embedding2);
        $this->assertCount(1536, $embedding1);
        $this->assertCount(1536, $embedding2);
    }
}

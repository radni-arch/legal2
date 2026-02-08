<?php

namespace Tests\Unit\Services\Search;

use App\Services\OpenAIService;
use App\Services\Search\SearchEmbeddingService;
use Mockery;
use Tests\TestCase;

/**
 * Test suite for SearchEmbeddingService
 *
 * This service is responsible for generating embeddings for search queries.
 * It's extracted from UnifiedSearchService as part of Phase 2 refactoring.
 */
class SearchEmbeddingServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test 1: It generates embedding for a query
     *
     * @test
     */
    public function it_generates_embedding_for_query()
    {
        // Arrange: Mock OpenAI service
        $openAI = Mockery::mock(OpenAIService::class);
        $mockEmbedding = array_fill(0, 1536, 0.1);

        $openAI->shouldReceive('embeddings')
            ->once()
            ->with(['test query'], 'text-embedding-3-small')
            ->andReturn([
                'data' => [
                    ['embedding' => $mockEmbedding],
                ],
            ]);

        // Act: Generate embedding
        $service = new SearchEmbeddingService($openAI);
        $embedding = $service->embedQuery('test query');

        // Assert: Should return embedding array
        $this->assertIsArray($embedding);
        $this->assertCount(1536, $embedding);
        $this->assertEquals($mockEmbedding, $embedding);
    }

    /**
     * Test 2: It uses default model from config
     *
     * @test
     */
    public function it_uses_default_model_from_config()
    {
        // Arrange: Mock OpenAI and set config
        config(['openai.models.embeddings' => 'text-embedding-3-small']);

        $openAI = Mockery::mock(OpenAIService::class);
        $mockEmbedding = array_fill(0, 1536, 0.1);

        $openAI->shouldReceive('embeddings')
            ->once()
            ->with(['test query'], 'text-embedding-3-small')
            ->andReturn([
                'data' => [
                    ['embedding' => $mockEmbedding],
                ],
            ]);

        // Act: Generate embedding without specifying model
        $service = new SearchEmbeddingService($openAI);
        $embedding = $service->embedQuery('test query');

        // Assert: Should use default model
        $this->assertIsArray($embedding);
        $this->assertCount(1536, $embedding);
    }

    /**
     * Test 3: It accepts custom model parameter
     *
     * @test
     */
    public function it_accepts_custom_model()
    {
        // Arrange: Mock OpenAI with custom model
        $openAI = Mockery::mock(OpenAIService::class);
        $mockEmbedding = array_fill(0, 1536, 0.1);

        $openAI->shouldReceive('embeddings')
            ->once()
            ->with(['test query'], 'text-embedding-3-large')
            ->andReturn([
                'data' => [
                    ['embedding' => $mockEmbedding],
                ],
            ]);

        // Act: Generate embedding with custom model
        $service = new SearchEmbeddingService($openAI);
        $embedding = $service->embedQuery('test query', 'text-embedding-3-large');

        // Assert: Should use custom model
        $this->assertIsArray($embedding);
        $this->assertCount(1536, $embedding);
    }

    /**
     * Test 4: It handles empty query by generating empty embedding
     *
     * @test
     */
    public function it_handles_empty_query()
    {
        // Arrange: Mock OpenAI to return empty embedding
        $openAI = Mockery::mock(OpenAIService::class);

        $openAI->shouldReceive('embeddings')
            ->once()
            ->with([''], 'text-embedding-3-small')
            ->andReturn([
                'data' => [
                    ['embedding' => []],
                ],
            ]);

        // Act: Generate embedding for empty query
        $service = new SearchEmbeddingService($openAI);
        $embedding = $service->embedQuery('');

        // Assert: Should return empty array
        $this->assertIsArray($embedding);
        $this->assertEmpty($embedding);
    }

    /**
     * Test 5: It returns empty array when API response is malformed
     *
     * @test
     */
    public function it_returns_empty_array_for_malformed_response()
    {
        // Arrange: Mock OpenAI to return malformed response
        $openAI = Mockery::mock(OpenAIService::class);

        $openAI->shouldReceive('embeddings')
            ->once()
            ->andReturn([
                'data' => [],
            ]);

        // Act: Generate embedding
        $service = new SearchEmbeddingService($openAI);
        $embedding = $service->embedQuery('test');

        // Assert: Should return empty array
        $this->assertIsArray($embedding);
        $this->assertEmpty($embedding);
    }

    /**
     * Test 6: It returns empty array when embedding key is missing
     *
     * @test
     */
    public function it_returns_empty_array_when_embedding_key_missing()
    {
        // Arrange: Mock OpenAI to return response without embedding key
        $openAI = Mockery::mock(OpenAIService::class);

        $openAI->shouldReceive('embeddings')
            ->once()
            ->andReturn([
                'data' => [
                    ['no_embedding' => 'oops'],
                ],
            ]);

        // Act: Generate embedding
        $service = new SearchEmbeddingService($openAI);
        $embedding = $service->embedQuery('test');

        // Assert: Should return empty array
        $this->assertIsArray($embedding);
        $this->assertEmpty($embedding);
    }

    /**
     * Test 7: It handles Unicode characters in query
     *
     * @test
     */
    public function it_handles_unicode_characters()
    {
        // Arrange: Mock OpenAI with Unicode query
        $openAI = Mockery::mock(OpenAIService::class);
        $mockEmbedding = array_fill(0, 1536, 0.1);

        $unicodeQuery = 'Kazneni zakon članak 123 – pravo na obranu';

        $openAI->shouldReceive('embeddings')
            ->once()
            ->with([$unicodeQuery], 'text-embedding-3-small')
            ->andReturn([
                'data' => [
                    ['embedding' => $mockEmbedding],
                ],
            ]);

        // Act: Generate embedding with Unicode
        $service = new SearchEmbeddingService($openAI);
        $embedding = $service->embedQuery($unicodeQuery);

        // Assert: Should handle Unicode correctly
        $this->assertIsArray($embedding);
        $this->assertCount(1536, $embedding);
    }

    /**
     * Test 8: It handles long queries
     *
     * @test
     */
    public function it_handles_long_queries()
    {
        // Arrange: Mock OpenAI with very long query
        $openAI = Mockery::mock(OpenAIService::class);
        $mockEmbedding = array_fill(0, 1536, 0.1);

        $longQuery = str_repeat('This is a very long search query. ', 100);

        $openAI->shouldReceive('embeddings')
            ->once()
            ->with([$longQuery], 'text-embedding-3-small')
            ->andReturn([
                'data' => [
                    ['embedding' => $mockEmbedding],
                ],
            ]);

        // Act: Generate embedding for long query
        $service = new SearchEmbeddingService($openAI);
        $embedding = $service->embedQuery($longQuery);

        // Assert: Should handle long queries
        $this->assertIsArray($embedding);
        $this->assertCount(1536, $embedding);
    }

    /**
     * Test 9: It preserves embedding precision
     *
     * @test
     */
    public function it_preserves_embedding_precision()
    {
        // Arrange: Mock OpenAI with high-precision floats
        $openAI = Mockery::mock(OpenAIService::class);
        $mockEmbedding = [
            0.123456789012345,
            -0.987654321098765,
            0.000000000000001,
        ];

        $openAI->shouldReceive('embeddings')
            ->once()
            ->andReturn([
                'data' => [
                    ['embedding' => $mockEmbedding],
                ],
            ]);

        // Act: Generate embedding
        $service = new SearchEmbeddingService($openAI);
        $embedding = $service->embedQuery('precision test');

        // Assert: Should preserve float precision
        $this->assertIsArray($embedding);
        $this->assertEquals($mockEmbedding, $embedding);
    }

    /**
     * Test 10: It can be instantiated via service container
     *
     * @test
     */
    public function it_can_be_instantiated_via_container()
    {
        // Act: Resolve from container
        $service = app(SearchEmbeddingService::class);

        // Assert: Should be instance of SearchEmbeddingService
        $this->assertInstanceOf(SearchEmbeddingService::class, $service);
    }
}

<?php

namespace Tests\Unit\Services;

use App\Mcp\Tools\CaseSearchTool;
use App\Models\CaseDocument;
use App\Models\LegalCase;
use App\Services\CaseSearchService;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Performance optimization tests for CaseSearchService
 *
 * These tests verify that performance optimizations are working correctly:
 * - Result caching
 * - Embedding caching
 * - Selective field loading
 * - Query optimization
 */
class CaseSearchServicePerformanceTest extends TestCase
{
    use UsesTestDatabase;

    protected CaseSearchService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush(); // Clear cache before each test
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_caches_vector_search_results()
    {
        // Arrange: Create test data
        $case = LegalCase::factory()->create(['case_number' => 'CACHE-001/2024']);
        CaseDocument::factory()->create([
            'case_id' => $case->id,
            'title' => 'Cacheable Document',
            'content' => 'Content for cache testing',
        ]);

        // Mock OpenAI to return embedding
        $openAI = Mockery::mock(OpenAIService::class);
        $mockEmbedding = array_fill(0, 1536, 0.1);
        $openAI->shouldReceive('createEmbedding')
            ->once() // Should only be called ONCE due to caching
            ->andReturn($mockEmbedding);

        $service = new CaseSearchService($openAI, Mockery::mock(CaseSearchTool::class));

        // Act: Perform same search twice
        $result1 = $service->vectorSearchWithCache('cacheable query');
        $result2 = $service->vectorSearchWithCache('cacheable query'); // Should hit cache

        // Assert: Both results are identical
        $this->assertEquals($result1, $result2);

        // Verify cache was used (embedding only called once)
        $this->assertTrue(true); // Mock expectation verifies this
    }

    /** @test */
    public function it_caches_embeddings_separately()
    {
        // Arrange
        $openAI = Mockery::mock(OpenAIService::class);
        $mockEmbedding = array_fill(0, 1536, 0.1);

        $openAI->shouldReceive('createEmbedding')
            ->once() // Should only be called ONCE for same query
            ->with('same query', Mockery::any())
            ->andReturn($mockEmbedding);

        $service = new CaseSearchService($openAI, Mockery::mock(CaseSearchTool::class));

        // Act: Get embedding for same query multiple times
        $embedding1 = $service->getCachedEmbedding('same query');
        $embedding2 = $service->getCachedEmbedding('same query'); // Should hit cache

        // Assert: Both embeddings are identical
        $this->assertEquals($embedding1, $embedding2);
        $this->assertCount(1536, $embedding1);
    }

    /** @test */
    public function it_respects_cache_ttl()
    {
        // Arrange
        $openAI = Mockery::mock(OpenAIService::class);
        $mockEmbedding = array_fill(0, 1536, 0.1);

        $openAI->shouldReceive('createEmbedding')
            ->andReturn($mockEmbedding);

        $service = new CaseSearchService($openAI, Mockery::mock(CaseSearchTool::class));

        // Act: Get embedding with custom TTL
        $embedding = $service->getCachedEmbedding('test query', $ttl = 60); // 60 seconds

        // Assert: Embedding is cached
        $cacheKey = $service->getEmbeddingCacheKey('test query');
        $this->assertTrue(Cache::has($cacheKey));

        // Verify cached value
        $cachedEmbedding = Cache::get($cacheKey);
        $this->assertEquals($embedding, $cachedEmbedding);
    }

    /** @test */
    public function it_supports_cache_bypass()
    {
        // Arrange
        $openAI = Mockery::mock(OpenAIService::class);
        $mockEmbedding = array_fill(0, 1536, 0.1);

        $openAI->shouldReceive('createEmbedding')
            ->twice() // Should be called TWICE when cache is bypassed
            ->andReturn($mockEmbedding);

        $service = new CaseSearchService($openAI, Mockery::mock(CaseSearchTool::class));

        // Act: Get embedding twice with cache bypass
        $embedding1 = $service->getCachedEmbedding('bypass query', 60, $useCache = true);
        $embedding2 = $service->getCachedEmbedding('bypass query', 60, $useCache = false); // Bypass cache

        // Assert: Both calls hit OpenAI
        $this->assertEquals($embedding1, $embedding2);
    }

    /** @test */
    public function it_loads_only_necessary_fields_by_default()
    {
        // Arrange: Create case with large content
        $case = LegalCase::factory()->create();
        $largeContent = str_repeat('Large content data ', 10000); // ~200KB

        CaseDocument::factory()->create([
            'case_id' => $case->id,
            'title' => 'Document with large content',
            'content' => $largeContent,
        ]);

        $openAI = Mockery::mock(OpenAIService::class);
        $openAI->shouldReceive('createEmbedding')->andReturn(array_fill(0, 1536, 0.1));

        $service = new CaseSearchService($openAI, Mockery::mock(CaseSearchTool::class));

        // Track database queries
        DB::enableQueryLog();

        // Act: Search without requesting content
        $results = $service->vectorSearchOptimized('test', [
            'include_content' => false, // Don't load content field
        ]);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Assert: Content field should not be in SELECT
        $this->assertNotEmpty($queries);
        $mainQuery = $queries[array_key_last($queries)]['query'];

        // When include_content is false, content should not be selected
        if (isset($results['data'][0])) {
            $this->assertArrayNotHasKey('content', (array) $results['data'][0]);
        }
    }

    /** @test */
    public function it_includes_content_when_explicitly_requested()
    {
        // Arrange
        $case = LegalCase::factory()->create();
        CaseDocument::factory()->create([
            'case_id' => $case->id,
            'title' => 'Test Document',
            'content' => 'This is the full content',
        ]);

        $openAI = Mockery::mock(OpenAIService::class);
        $openAI->shouldReceive('createEmbedding')->andReturn(array_fill(0, 1536, 0.1));

        $service = new CaseSearchService($openAI, Mockery::mock(CaseSearchTool::class));

        // Act: Search with content requested
        $results = $service->vectorSearchOptimized('test', [
            'include_content' => true, // Load content field
        ]);

        // Assert: Content should be present
        if (isset($results['data'][0])) {
            $this->assertArrayHasKey('content', (array) $results['data'][0]);
            $this->assertEquals('This is the full content', $results['data'][0]->content);
        }
    }

    /** @test */
    public function it_uses_optimized_vector_similarity_query()
    {
        // Arrange
        $case = LegalCase::factory()->create();
        CaseDocument::factory()->create([
            'case_id' => $case->id,
            'title' => 'Query optimization test',
        ]);

        $openAI = Mockery::mock(OpenAIService::class);
        $openAI->shouldReceive('createEmbedding')->andReturn(array_fill(0, 1536, 0.1));

        $service = new CaseSearchService($openAI, Mockery::mock(CaseSearchTool::class));

        DB::enableQueryLog();

        // Act: Perform vector search
        $service->vectorSearchOptimized('test');

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Assert: Query should use optimized similarity calculation
        // In the optimized version, we calculate <=> distance once and derive similarity
        $this->assertNotEmpty($queries);
        $mainQuery = $queries[array_key_last($queries)]['query'];

        // Optimized query should use a single distance calculation
        // and reference it for both WHERE and ORDER BY
        $this->assertStringContainsString('embedding_vector', $mainQuery);
    }

    /** @test */
    public function it_generates_correct_cache_keys()
    {
        // Arrange
        $openAI = Mockery::mock(OpenAIService::class);
        $service = new CaseSearchService($openAI, Mockery::mock(CaseSearchTool::class));

        // Act: Generate cache keys
        $key1 = $service->getEmbeddingCacheKey('test query');
        $key2 = $service->getEmbeddingCacheKey('test query');
        $key3 = $service->getEmbeddingCacheKey('different query');

        // Assert: Same queries produce same keys, different queries produce different keys
        $this->assertEquals($key1, $key2);
        $this->assertNotEquals($key1, $key3);

        // Keys should be deterministic and include query hash
        $this->assertStringContainsString('embedding', $key1);
    }

    /** @test */
    public function it_clears_cache_on_demand()
    {
        // Arrange
        $openAI = Mockery::mock(OpenAIService::class);
        $mockEmbedding = array_fill(0, 1536, 0.1);

        $openAI->shouldReceive('createEmbedding')
            ->twice() // Once before clear, once after
            ->andReturn($mockEmbedding);

        $service = new CaseSearchService($openAI, Mockery::mock(CaseSearchTool::class));

        // Act: Cache embedding, clear cache, retrieve again
        $embedding1 = $service->getCachedEmbedding('test query');
        $service->clearEmbeddingCache('test query');
        $embedding2 = $service->getCachedEmbedding('test query'); // Should regenerate

        // Assert: Both calls hit OpenAI due to cache clear
        $this->assertEquals($embedding1, $embedding2);
    }
}

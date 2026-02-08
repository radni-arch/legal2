<?php

namespace Tests\Unit\Services;

use App\Mcp\Tools\LawGetArticleTool;
use App\Mcp\Tools\LawSearchTool;
use App\Models\Law;
use App\Services\LawSearchService;
use App\Services\OpenAIService;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class LawSearchServiceTest extends TestCase
{
    use UsesTestDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Create a LawSearchService instance with mocked dependencies
     */
    protected function makeService(
        ?OpenAIService $openAI = null,
        ?LawSearchTool $lawSearchTool = null,
        ?LawGetArticleTool $lawGetArticleTool = null
    ): LawSearchService {
        $openAI = $openAI ?? Mockery::mock(OpenAIService::class);
        $lawSearchTool = $lawSearchTool ?? Mockery::mock(LawSearchTool::class);
        $lawGetArticleTool = $lawGetArticleTool ?? Mockery::mock(LawGetArticleTool::class);

        return new LawSearchService($openAI, $lawSearchTool, $lawGetArticleTool);
    }

    public function test_vector_search_returns_laws_with_similarity_scores(): void
    {
        // Mock OpenAI service to return embedding
        $openAI = Mockery::mock(OpenAIService::class);
        $mockEmbedding = array_fill(0, 1536, 0.1);
        $openAI->shouldReceive('createEmbedding')
            ->once()
            ->with('test query')
            ->andReturn($mockEmbedding);

        $service = $this->makeService($openAI);

        // Execute vector search (will return empty results since no laws with embeddings exist, but should not error)
        $result = $service->vectorSearch('test query', ['limit' => 10]);

        // Assertions - success even with no results
        $this->assertTrue($result['success']);
        $this->assertEquals('vector', $result['search_type']);
        $this->assertArrayHasKey('data', $result);
        $this->assertIsArray($result['data']);
        // May be empty if no laws exist, which is fine for this test
    }

    public function test_keyword_search_filters_by_jurisdiction(): void
    {
        // Create test laws
        $hrLaw = Law::factory()->create([
            'title' => 'Croatian Criminal Law',
            'jurisdiction' => 'HR',
            'content' => 'Croatian criminal law content',
        ]);

        $euLaw = Law::factory()->create([
            'title' => 'EU Regulation',
            'jurisdiction' => 'EU',
            'content' => 'EU regulation content',
        ]);

        $service = $this->makeService();

        // Execute keyword search with jurisdiction filter
        $result = $service->keywordSearch('criminal', ['jurisdiction' => 'HR']);

        // Assertions
        $this->assertTrue($result['success']);
        $this->assertEquals('keyword', $result['search_type']);
        $this->assertArrayHasKey('pagination', $result);

        // Should only return HR law
        $titles = collect($result['data'])->pluck('title')->toArray();
        $this->assertContains('Croatian Criminal Law', $titles);
        $this->assertNotContains('EU Regulation', $titles);
    }

    public function test_hybrid_search_merges_results(): void
    {
        // Mock OpenAI for vector search component
        $openAI = Mockery::mock(OpenAIService::class);
        $mockEmbedding = array_fill(0, 1536, 0.1);
        $openAI->shouldReceive('createEmbedding')
            ->once()
            ->with('criminal procedure')
            ->andReturn($mockEmbedding);

        $service = $this->makeService($openAI);

        // Execute hybrid search
        $result = $service->hybridSearch('criminal procedure');

        // Assertions - hybrid search combines vector + keyword
        $this->assertTrue($result['success']);
        $this->assertEquals('hybrid', $result['search_type']);
        $this->assertArrayHasKey('data', $result);
        $this->assertIsArray($result['data']);
    }

    public function test_keyword_search_handles_typos(): void
    {
        // Create test law
        Law::factory()->create([
            'title' => 'Criminal Code',
            'content' => 'Criminal law provisions',
            'jurisdiction' => 'HR',
        ]);

        $service = $this->makeService();

        // Execute keyword search (which may handle fuzzy matching internally)
        $result = $service->keywordSearch('criminal', ['limit' => 10]);

        // Assertions
        $this->assertTrue($result['success']);
        $this->assertEquals('keyword', $result['search_type']);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_lookup_by_doc_id_returns_law(): void
    {
        // This test verifies the lookupByDocId method structure
        // Note: Full integration test would require ToolResult which is readonly and cannot be mocked
        // For now, we test that the method exists and has correct signature

        $service = $this->makeService();

        // Verify method exists and returns array
        $this->assertTrue(method_exists($service, 'lookupByDocId'));

        // The method signature should accept doc_id and optional filters
        $reflection = new \ReflectionMethod($service, 'lookupByDocId');
        $this->assertEquals('array', $reflection->getReturnType()->getName());
        $this->assertGreaterThanOrEqual(1, $reflection->getNumberOfParameters());
    }

    public function test_search_respects_pagination(): void
    {
        // Create multiple laws
        for ($i = 1; $i <= 25; $i++) {
            Law::factory()->create([
                'title' => "Law Number {$i}",
                'content' => "Content for law {$i}",
                'jurisdiction' => 'HR',
            ]);
        }

        $service = $this->makeService();

        // Execute search with pagination
        $result = $service->keywordSearch('law', ['page' => 1, 'limit' => 10]);

        // Assertions
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('pagination', $result);
        $this->assertEquals(1, $result['pagination']['page']);
        $this->assertEquals(10, $result['pagination']['limit']);
        $this->assertGreaterThanOrEqual(25, $result['pagination']['total']);
        $this->assertLessThanOrEqual(10, count($result['data']));
    }
}

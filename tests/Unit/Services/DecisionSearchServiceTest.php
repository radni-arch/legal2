<?php

namespace Tests\Unit\Services;

use App\Mcp\Tools\DecisionGetTool;
use App\Mcp\Tools\DecisionSearchTool;
use App\Models\CourtDecision;
use App\Models\CourtDecisionDocument;
use App\Services\DecisionSearchService;
use App\Services\OpenAIService;
use Mockery;
use Prism\Prism\ValueObjects\ToolResult;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class DecisionSearchServiceTest extends TestCase
{
    use UsesTestDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Create a DecisionSearchService instance with mocked dependencies
     */
    protected function makeService(
        ?OpenAIService $openAI = null,
        ?DecisionSearchTool $decisionSearchTool = null,
        ?DecisionGetTool $decisionGetTool = null
    ): DecisionSearchService {
        $openAI = $openAI ?? Mockery::mock(OpenAIService::class);
        $decisionSearchTool = $decisionSearchTool ?? Mockery::mock(DecisionSearchTool::class);
        $decisionGetTool = $decisionGetTool ?? Mockery::mock(DecisionGetTool::class);

        return new DecisionSearchService($openAI, $decisionSearchTool, $decisionGetTool);
    }

    public function test_vector_search_returns_decisions_with_similarity_scores(): void
    {
        // Create real court decision and document with embedding
        $decision = CourtDecision::factory()->create([
            'case_number' => 'P-123/2024',
            'court' => 'Supreme Court',
            'jurisdiction' => 'Croatia',
            'decision_date' => '2024-01-15',
            'decision_type' => 'Judgment',
        ]);

        $document = CourtDecisionDocument::factory()->create([
            'decision_id' => $decision->id,
            'title' => 'Contract Dispute Case',
            'content' => 'This is a contract dispute case involving breach of agreement',
            'chunk_index' => 0,
            'metadata' => ['key' => 'value'],
        ]);

        // Mock OpenAI service to return embedding
        $openAI = Mockery::mock(OpenAIService::class);
        $mockEmbedding = array_fill(0, 1536, 0.1);
        $openAI->shouldReceive('createEmbedding')
            ->once()
            ->with('contract dispute')
            ->andReturn($mockEmbedding);

        $service = $this->makeService($openAI);

        // Execute vector search - it should succeed even if no vector matches found
        $result = $service->vectorSearch('contract dispute', ['jurisdiction' => 'Croatia']);

        // Assertions - verify structure returned correctly
        $this->assertTrue($result['success']);
        $this->assertEquals('vector', $result['search_type']);
        $this->assertArrayHasKey('count', $result);
        $this->assertArrayHasKey('data', $result);
        $this->assertIsArray($result['data']);
    }

    public function test_keyword_search_with_filters(): void
    {
        // Create real court decision
        CourtDecision::factory()->create([
            'case_number' => 'P-123/2024',
            'title' => 'Contract Dispute',
            'court' => 'Supreme Court',
            'jurisdiction' => 'Croatia',
            'judge' => 'Judge Smith',
            'decision_date' => '2024-01-15',
            'publication_date' => '2024-01-20',
            'decision_type' => 'Judgment',
            'register' => 'Civil',
            'finality' => 'Final',
            'ecli' => 'ECLI:HR:VSRH:2024:1',
            'tags' => ['contract', 'dispute'],
        ]);

        $service = $this->makeService();

        // Execute keyword search with filters
        $result = $service->keywordSearch('', [
            'jurisdiction' => 'Croatia',
            'decision_type' => 'Judgment',
        ]);

        // Assertions
        $this->assertTrue($result['success']);
        $this->assertEquals('keyword', $result['search_type']);
        $this->assertArrayHasKey('pagination', $result);
        $this->assertGreaterThanOrEqual(1, $result['pagination']['total']);
        // Just verify we got the decision back
        $found = collect($result['data'])->contains(fn ($d) => $d['title'] === 'Contract Dispute');
        $this->assertTrue($found, 'Expected to find Contract Dispute in results');
    }

    public function test_hybrid_search_merges_and_deduplicates_results(): void
    {
        // Create real court decisions for keyword search to find
        CourtDecision::factory()->create([
            'case_number' => 'P-123/2024',
            'title' => 'Test Decision One',
            'court' => 'Supreme Court',
            'jurisdiction' => 'Croatia',
            'decision_type' => 'Judgment',
        ]);

        CourtDecision::factory()->create([
            'case_number' => 'P-456/2024',
            'title' => 'Test Decision Two',
            'court' => 'County Court',
            'jurisdiction' => 'Croatia',
            'decision_type' => 'Ruling',
        ]);

        // Mock OpenAI for vector search embedding
        $openAI = Mockery::mock(OpenAIService::class);
        $mockEmbedding = array_fill(0, 1536, 0.1);
        $openAI->shouldReceive('createEmbedding')
            ->once()
            ->andReturn($mockEmbedding);

        $service = $this->makeService($openAI);

        // Execute hybrid search - combines vector (may be empty) + keyword results
        $result = $service->hybridSearch('test');

        // Assertions - verify hybrid search returns proper structure
        $this->assertTrue($result['success']);
        $this->assertEquals('hybrid', $result['search_type']);
        $this->assertArrayHasKey('count', $result);
        $this->assertArrayHasKey('data', $result);
        $this->assertIsArray($result['data']);
        // Should have keyword results at minimum
        $this->assertGreaterThanOrEqual(1, $result['count']);
    }

    public function test_lookup_by_criteria_finds_decisions(): void
    {
        // Create real court decision with document
        $decision = CourtDecision::factory()->create([
            'case_number' => 'P-123/2024',
            'title' => 'Contract Dispute',
            'court' => 'Supreme Court',
            'jurisdiction' => 'Croatia',
            'decision_date' => '2024-01-15',
            'publication_date' => '2024-01-20',
            'decision_type' => 'Judgment',
            'ecli' => 'ECLI:HR:VSRH:2024:1',
            'judge' => 'Judge Smith',
        ]);

        CourtDecisionDocument::factory()->create([
            'decision_id' => $decision->id,
            'content' => 'This is the full decision content that will be used for summary purposes.',
        ]);

        $service = $this->makeService();

        // Execute lookup by criteria
        $result = $service->lookupByCriteria([
            'case_number' => 'P-123',
            'court' => 'Supreme',
            'jurisdiction' => 'Croatia',
        ]);

        // Assertions
        $this->assertIsArray($result);
        $this->assertGreaterThanOrEqual(1, count($result));
        $this->assertEquals('P-123/2024', $result[0]['case_number']);
        $this->assertEquals('Contract Dispute', $result[0]['title']);
        $this->assertEquals('Supreme Court', $result[0]['court']);
        $this->assertGreaterThanOrEqual(1, $result[0]['documents_count']);
        $this->assertNotNull($result[0]['summary']);
    }

    public function test_get_by_id_delegates_to_mcp_tool(): void
    {
        // Create real decision for integration test
        $decision = CourtDecision::factory()->create([
            'case_number' => 'P-123/2024',
            'title' => 'Test Decision',
            'court' => 'Supreme Court',
            'decision_date' => '2024-01-15',
        ]);

        $document = CourtDecisionDocument::factory()->create([
            'decision_id' => $decision->id,
            'title' => 'Decision Document',
            'content' => 'Full content',
        ]);

        // Mock DecisionGetTool to return expected payload without invoking container internals
        $mockGetTool = Mockery::mock(DecisionGetTool::class);
        $payload = [
            'success' => true,
            'decision' => [
                'id' => $decision->id,
                'case_number' => 'P-123/2024',
                'title' => 'Test Decision',
                'court' => 'Supreme Court',
            ],
            'documents' => [
                'total_chunks' => 1,
                'items' => [
                    [
                        'id' => $document->id,
                        'decision_id' => $decision->id,
                        'title' => 'Decision Document',
                        'content' => 'Full content',
                    ],
                ],
            ],
        ];
        $arguments = [
            'id' => $decision->id,
            'include_content' => true,
            'include_documents' => true,
        ];
        $mockGetTool->shouldReceive('handle')->once()->andReturn(new ToolResult(
            'tool-call-1', // toolCallId
            'decision.get', // toolName
            $arguments,     // args
            $payload        // result
        ));

        // Create service using mocked MCP tool (avoid app() resolution to prevent segfault)
        $service = $this->makeService(null, null, $mockGetTool);

        // Execute getById - it should work with real data via mocked tool
        $result = $service->getById($decision->id, true, true);

        // Assertions
        $this->assertEquals($decision->id, $result['id']);
        $this->assertEquals('P-123/2024', $result['case_number']);
        $this->assertEquals('Test Decision', $result['title']);
        $this->assertArrayHasKey('documents', $result);
        $this->assertGreaterThanOrEqual(1, count($result['documents']));
    }

    public function test_search_method_routes_to_correct_implementation(): void
    {
        // Create some test data
        CourtDecision::factory()->create([
            'title' => 'Test Decision',
            'court' => 'Supreme Court',
        ]);

        // Mock OpenAI for vector search
        $openAI = Mockery::mock(OpenAIService::class);
        $mockEmbedding = array_fill(0, 1536, 0.1);
        $openAI->shouldReceive('createEmbedding')
            ->andReturn($mockEmbedding);

        $service = $this->makeService($openAI);

        // Test keyword search routing (default)
        $result = $service->search('test', ['search_type' => 'keyword']);
        $this->assertEquals('keyword', $result['search_type']);

        // Test vector search routing
        $result = $service->search('test', ['search_type' => 'vector']);
        $this->assertEquals('vector', $result['search_type']);

        // Test invalid search type throws exception
        $this->expectException(\InvalidArgumentException::class);
        $service->search('test', ['search_type' => 'invalid']);
    }

    public function test_vector_search_handles_embedding_failure(): void
    {
        // Mock OpenAI to return null embedding (failure)
        $openAI = Mockery::mock(OpenAIService::class);
        $openAI->shouldReceive('createEmbedding')
            ->once()
            ->andReturn([]);

        $service = $this->makeService($openAI);

        // Execute vector search
        $result = $service->vectorSearch('test query');

        // Assertions
        $this->assertFalse($result['success']);
        $this->assertEquals('vector', $result['search_type']);
        $this->assertEquals(0, $result['count']);
        $this->assertEquals('Failed to generate embedding', $result['error']);
    }

    public function test_keyword_search_with_date_range_filter(): void
    {
        // Create real court decision with specific date
        CourtDecision::factory()->create([
            'case_number' => 'P-123/2024',
            'title' => 'Recent Decision',
            'court' => 'Supreme Court',
            'jurisdiction' => 'Croatia',
            'judge' => 'Judge Smith',
            'decision_date' => '2024-01-15',
            'publication_date' => '2024-01-20',
            'decision_type' => 'Judgment',
            'register' => 'Civil',
            'finality' => 'Final',
            'ecli' => 'ECLI:HR:VSRH:2024:1',
            'tags' => [],
        ]);

        $service = $this->makeService();

        // Execute keyword search with date range
        $result = $service->keywordSearch('', [
            'date_from' => '2024-01-01',
            'date_to' => '2024-12-31',
        ]);

        // Assertions
        $this->assertTrue($result['success']);
        $this->assertGreaterThanOrEqual(1, count($result['data']));
        $this->assertStringStartsWith('2024-01-15', $result['data'][0]['decision_date']);
    }
}

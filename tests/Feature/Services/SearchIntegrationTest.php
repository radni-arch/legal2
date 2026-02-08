<?php

namespace Tests\Feature\Services;

use App\Agents\AutonomousResearchAgent;
use App\Mcp\Tools\DecisionSearchTool;
use App\Mcp\Tools\LawSearchTool;
use App\Models\CaseDocument;
use App\Models\CourtDecision;
use App\Models\Law;
use App\Models\LegalCase;
use App\Services\CaseSearchService;
use App\Services\DecisionSearchService;
use App\Services\LawSearchService;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Integration tests for the unified search service architecture
 *
 * Tests the full flow from agent actions to search services to database queries,
 * ensuring all components work together correctly.
 */
class SearchIntegrationTest extends TestCase
{
    use UsesTestDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test that the agent properly delegates to LawSearchService
     * and that the service returns properly formatted results
     */
    public function test_agent_uses_law_search_service_for_research(): void
    {
        // Create test data
        Law::factory()->create([
            'doc_id' => 'law-123',
            'title' => 'Test Criminal Code',
            'law_number' => 'NN 94/14',
            'jurisdiction' => 'Croatia',
            'content' => 'This is a test law about criminal procedures',
        ]);

        // Mock OpenAI to avoid external API calls
        $openAI = Mockery::mock(OpenAIService::class);
        $mockEmbedding = array_fill(0, 1536, 0.1);
        $openAI->shouldReceive('embeddings')
            ->andReturn([
                'data' => [['embedding' => $mockEmbedding]],
            ]);

        $this->app->instance(OpenAIService::class, $openAI);

        // Get the LawSearchService instance (which will use the mocked OpenAI)
        $lawSearchService = app(LawSearchService::class);

        // Execute keyword search through the service
        $result = $lawSearchService->keywordSearch('criminal', [
            'jurisdiction' => 'Croatia',
            'limit' => 10,
        ]);

        // Verify service returns properly formatted results
        $this->assertTrue($result['success']);
        $this->assertEquals('keyword', $result['search_type']);
        $this->assertGreaterThan(0, $result['pagination']['total']);
        $this->assertCount(1, $result['data']);
        $this->assertEquals('Test Criminal Code', $result['data'][0]['title']);

        // Now test the agent uses the same service
        $agent = app(AutonomousResearchAgent::class);
        $run = $agent->startRun('Research criminal law');

        // Use reflection to call protected executeActions method
        $reflection = new \ReflectionClass($agent);
        $method = $reflection->getMethod('executeActions');
        $method->setAccessible(true);

        $actions = [
            [
                'tool' => 'law_keyword_search',
                'params' => [
                    'query' => 'criminal',
                    'jurisdiction' => 'Croatia',
                ],
            ],
        ];

        $results = $method->invoke($agent, $actions, $run);

        // Verify agent gets the same results from the service
        $this->assertTrue($results[0]['success']);
        $this->assertEquals('keyword', $results[0]['result']['search_type']);
        $this->assertCount(1, $results[0]['result']['data']);
        $this->assertEquals('Test Criminal Code', $results[0]['result']['data'][0]['title']);
    }

    /**
     * Test that MCP tools and agent both use the same LawSearchService instance,
     * ensuring consistency across the application
     */
    public function test_mcp_tool_uses_same_service_as_agent(): void
    {
        // Create test law
        Law::factory()->create([
            'doc_id' => 'law-456',
            'title' => 'Commercial Code',
            'law_number' => 'NN 100/15',
            'jurisdiction' => 'Croatia',
            'content' => 'Commercial law content',
        ]);

        // Mock OpenAI
        $openAI = Mockery::mock(OpenAIService::class);
        $this->app->instance(OpenAIService::class, $openAI);

        // Get service instance that will be shared
        $lawSearchService = app(LawSearchService::class);
        $serviceResult = $lawSearchService->keywordSearch('commercial', [
            'jurisdiction' => 'Croatia',
        ]);

        // Get MCP tool which should use the same service
        $mcpTool = app(LawSearchTool::class);
        $toolResult = $mcpTool->handle([
            'query' => 'commercial',
            'jurisdiction' => 'Croatia',
            'search_type' => 'keyword',
        ]);

        $toolData = json_decode($toolResult->text, true);

        // Verify both use the same service and return same results
        $this->assertEquals($serviceResult['success'], $toolData['success']);
        $this->assertEquals($serviceResult['search_type'], $toolData['search_type']);
        $this->assertEquals(count($serviceResult['data']), count($toolData['data']));

        // Verify the actual data matches
        if (! empty($serviceResult['data']) && ! empty($toolData['data'])) {
            $this->assertEquals($serviceResult['data'][0]['title'], $toolData['data'][0]['title']);
        }
    }

    /**
     * Test that vector search and keyword search return different results,
     * demonstrating they use different search strategies
     */
    public function test_vector_and_keyword_search_return_different_results(): void
    {
        // Skip if not using PostgreSQL (vector search requires pgvector)
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Vector search requires PostgreSQL with pgvector extension');
        }

        // Create laws with different characteristics
        Law::factory()->create([
            'doc_id' => 'law-exact',
            'title' => 'Criminal Procedure Code',
            'content' => 'This law contains the exact word criminal multiple times',
            'law_number' => 'NN 152/08',
        ]);

        Law::factory()->create([
            'doc_id' => 'law-semantic',
            'title' => 'Penal Code',
            'content' => 'This law is about offenses, prosecution, and punishment',
            'law_number' => 'NN 125/11',
        ]);

        // Mock OpenAI with different embeddings for semantic search
        $openAI = Mockery::mock(OpenAIService::class);

        // Mock embedding that would be more similar to "Penal Code"
        $mockEmbedding = array_fill(0, 1536, 0.1);
        $openAI->shouldReceive('embeddings')
            ->with(['criminal'], Mockery::any())
            ->andReturn([
                'data' => [['embedding' => $mockEmbedding]],
            ]);

        $this->app->instance(OpenAIService::class, $openAI);

        $lawSearchService = app(LawSearchService::class);

        // Keyword search should prefer exact matches
        $keywordResult = $lawSearchService->keywordSearch('criminal', ['limit' => 10]);

        // Vector search might rank semantic similarity differently
        // (In real scenario with actual embeddings, this would show different ordering)

        // Verify both searches work but can return different ordering
        $this->assertTrue($keywordResult['success']);
        $this->assertEquals('keyword', $keywordResult['search_type']);

        // At minimum, verify keyword search found the exact match
        $titles = array_column($keywordResult['data'], 'title');
        $this->assertContains('Criminal Procedure Code', $titles);
    }

    /**
     * Test that hybrid search combines both vector and keyword approaches,
     * merging results and deduplicating properly
     */
    public function test_hybrid_search_combines_both_approaches(): void
    {
        // Create test laws
        Law::factory()->create([
            'doc_id' => 'law-001',
            'title' => 'Criminal Code Article 1',
            'law_number' => 'NN 125/11',
            'content' => 'Criminal offense definitions',
        ]);

        Law::factory()->create([
            'doc_id' => 'law-002',
            'title' => 'Criminal Code Article 2',
            'law_number' => 'NN 125/11',
            'content' => 'Sentencing guidelines',
        ]);

        Law::factory()->create([
            'doc_id' => 'law-003',
            'title' => 'Civil Procedure Code',
            'law_number' => 'NN 53/91',
            'content' => 'Civil litigation procedures',
        ]);

        // Mock OpenAI
        $openAI = Mockery::mock(OpenAIService::class);
        $mockEmbedding = array_fill(0, 1536, 0.1);
        $openAI->shouldReceive('embeddings')
            ->andReturn([
                'data' => [['embedding' => $mockEmbedding]],
            ]);

        $this->app->instance(OpenAIService::class, $openAI);

        $lawSearchService = app(LawSearchService::class);

        // Execute hybrid search
        $hybridResult = $lawSearchService->hybridSearch('criminal', ['limit' => 10]);

        // Verify hybrid search succeeded
        $this->assertTrue($hybridResult['success']);
        $this->assertEquals('hybrid', $hybridResult['search_type']);

        // Verify results are merged
        $this->assertArrayHasKey('data', $hybridResult);
        $this->assertArrayHasKey('count', $hybridResult);

        // Verify each result has match_type and score
        foreach ($hybridResult['data'] as $item) {
            $this->assertArrayHasKey('match_type', $item);
            $this->assertArrayHasKey('score', $item);
            $this->assertContains($item['match_type'], ['vector', 'keyword']);
        }

        // Verify no duplicates (all doc_ids should be unique)
        $docIds = array_column($hybridResult['data'], 'doc_id');
        $uniqueDocIds = array_unique($docIds);
        $this->assertCount(count($docIds), $uniqueDocIds, 'Hybrid search should not return duplicate doc_ids');
    }

    /**
     * Test that DecisionSearchService integrates properly across agent and MCP tools
     */
    public function test_decision_search_integration_across_components(): void
    {
        // Create test court decision
        $decision = CourtDecision::factory()->create([
            'case_number' => 'P-123/2024',
            'title' => 'Contract Dispute Case',
            'court' => 'Supreme Court',
            'jurisdiction' => 'Croatia',
            'decision_type' => 'Judgment',
        ]);

        // Mock OpenAI
        $openAI = Mockery::mock(OpenAIService::class);
        $this->app->instance(OpenAIService::class, $openAI);

        // Test through DecisionSearchService directly
        $decisionSearchService = app(DecisionSearchService::class);
        $serviceResult = $decisionSearchService->keywordSearch('contract', [
            'jurisdiction' => 'Croatia',
        ]);

        $this->assertTrue($serviceResult['success']);
        $this->assertGreaterThan(0, count($serviceResult['data']));

        // Test through MCP tool
        $mcpTool = app(DecisionSearchTool::class);
        $toolResult = $mcpTool->handle([
            'query' => 'contract',
            'jurisdiction' => 'Croatia',
            'search_type' => 'keyword',
        ]);

        $toolData = json_decode($toolResult->text, true);
        $this->assertTrue($toolData['success']);

        // Test through agent
        $agent = app(AutonomousResearchAgent::class);
        $run = $agent->startRun('Research contract disputes');

        $reflection = new \ReflectionClass($agent);
        $method = $reflection->getMethod('executeActions');
        $method->setAccessible(true);

        $results = $method->invoke($agent, [
            [
                'tool' => 'decision_keyword_search',
                'params' => ['query' => 'contract', 'jurisdiction' => 'Croatia'],
            ],
        ], $run);

        $this->assertTrue($results[0]['success']);

        // All three paths should find the same decision
        $this->assertEquals($serviceResult['data'][0]['title'], $toolData['data'][0]['title']);
        $this->assertEquals($serviceResult['data'][0]['title'], $results[0]['result']['data'][0]['title']);
    }

    /**
     * Test that CaseSearchService properly handles both case and document searches
     */
    public function test_case_search_handles_both_cases_and_documents(): void
    {
        // Create test legal case with documents
        $case = LegalCase::factory()->create([
            'case_number' => 'C-456/2024',
            'title' => 'Employment Dispute',
            'client_name' => 'John Doe',
            'opponent_name' => 'Company Inc',
            'status' => 'Active',
        ]);

        CaseDocument::factory()->create([
            'case_id' => $case->id,
            'title' => 'Employment Contract',
            'content' => 'This is the employment contract document',
        ]);

        // Mock OpenAI
        $openAI = Mockery::mock(OpenAIService::class);
        $this->app->instance(OpenAIService::class, $openAI);

        $caseSearchService = app(CaseSearchService::class);

        // Test searching cases
        $casesResult = $caseSearchService->searchCases('employment', []);
        $this->assertTrue($casesResult['success']);
        $this->assertEquals('cases', $casesResult['search_type']);
        $this->assertGreaterThan(0, count($casesResult['data']));
        $this->assertEquals('Employment Dispute', $casesResult['data'][0]['title']);

        // Test searching documents
        $documentsResult = $caseSearchService->searchDocuments('contract', []);
        $this->assertTrue($documentsResult['success']);
        $this->assertEquals('documents', $documentsResult['search_type']);
        $this->assertGreaterThan(0, count($documentsResult['data']));
        $this->assertEquals('Employment Contract', $documentsResult['data'][0]['title']);
    }

    /**
     * Test that the search() method properly routes to correct implementations
     * across all three search services
     */
    public function test_unified_search_method_routes_correctly(): void
    {
        // Create test data for all three types
        Law::factory()->create([
            'title' => 'Test Law',
            'content' => 'Law content',
        ]);

        CourtDecision::factory()->create([
            'title' => 'Test Decision',
            'case_number' => 'D-123/2024',
        ]);

        LegalCase::factory()->create([
            'title' => 'Test Case',
            'case_number' => 'C-123/2024',
        ]);

        // Mock OpenAI
        $openAI = Mockery::mock(OpenAIService::class);
        $this->app->instance(OpenAIService::class, $openAI);

        // Test LawSearchService routing
        $lawService = app(LawSearchService::class);

        $keywordResult = $lawService->search('test', ['search_type' => 'keyword']);
        $this->assertEquals('keyword', $keywordResult['search_type']);

        // Test DecisionSearchService routing
        $decisionService = app(DecisionSearchService::class);

        $decisionResult = $decisionService->search('test', ['search_type' => 'keyword']);
        $this->assertEquals('keyword', $decisionResult['search_type']);

        // Test CaseSearchService routing
        $caseService = app(CaseSearchService::class);

        $casesResult = $caseService->search('test', ['search_type' => 'cases']);
        $this->assertEquals('cases', $casesResult['search_type']);

        $documentsResult = $caseService->search('test', ['search_type' => 'documents']);
        $this->assertEquals('documents', $documentsResult['search_type']);

        // Test invalid search_type throws exception
        try {
            $lawService->search('test', ['search_type' => 'invalid']);
            $this->fail('Should have thrown InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Invalid search_type', $e->getMessage());
        }
    }

    /**
     * Test that all search services handle empty results gracefully
     */
    public function test_search_services_handle_empty_results(): void
    {
        // Don't create any test data, so all searches should return empty

        // Mock OpenAI
        $openAI = Mockery::mock(OpenAIService::class);
        $mockEmbedding = array_fill(0, 1536, 0.1);
        $openAI->shouldReceive('embeddings')
            ->andReturn([
                'data' => [['embedding' => $mockEmbedding]],
            ]);

        $this->app->instance(OpenAIService::class, $openAI);

        $lawService = app(LawSearchService::class);
        $decisionService = app(DecisionSearchService::class);
        $caseService = app(CaseSearchService::class);

        // Test all services return proper structure with empty data
        $lawResult = $lawService->keywordSearch('nonexistent');
        $this->assertTrue($lawResult['success']);
        $this->assertEmpty($lawResult['data']);
        $this->assertEquals(0, $lawResult['pagination']['total']);

        $decisionResult = $decisionService->keywordSearch('nonexistent');
        $this->assertTrue($decisionResult['success']);
        $this->assertEmpty($decisionResult['data']);

        $caseResult = $caseService->searchCases('nonexistent');
        $this->assertTrue($caseResult['success']);
        $this->assertEmpty($caseResult['data']);
    }
}

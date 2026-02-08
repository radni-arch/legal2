<?php

namespace Tests\Unit\Services;

use App\Mcp\Tools\CaseSearchTool;
use App\Models\CaseDocument;
use App\Models\LegalCase;
use App\Services\CaseSearchService;
use App\Services\OpenAIService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class CaseSearchServiceTest extends TestCase
{
    use UsesTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Close any existing mocks before each test
        Mockery::close();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Create a CaseSearchService instance with mocked dependencies
     */
    protected function makeService(
        ?OpenAIService $openAI = null,
        ?CaseSearchTool $caseSearchTool = null
    ): CaseSearchService {
        $openAI = $openAI ?? Mockery::mock(OpenAIService::class);
        $caseSearchTool = $caseSearchTool ?? Mockery::mock(CaseSearchTool::class);

        return new CaseSearchService($openAI, $caseSearchTool);
    }

    public function test_vector_search_returns_case_documents_with_similarity_scores(): void
    {
        // Create query embedding (all values 0.1 will be used to calculate similarity)
        $queryEmbedding = array_fill(0, 1536, 0.1);

        // Create similar embeddings for documents (higher values = higher similarity)
        // Document 1: Very similar (all 0.1) - should have high similarity
        $embedding1 = array_fill(0, 1536, 0.1);

        // Document 2: Less similar (all 0.05) - should have lower similarity
        $embedding2 = array_fill(0, 1536, 0.05);

        // Create test cases with real data
        $case1 = LegalCase::factory()->create([
            'case_number' => 'C-123/2024',
            'jurisdiction' => 'Croatia',
            'court' => 'Commercial Court',
            'status' => 'Active',
        ]);

        $case2 = LegalCase::factory()->create([
            'case_number' => 'C-456/2024',
            'jurisdiction' => 'Croatia',
            'court' => 'Labor Court',
            'status' => 'Pending',
        ]);

        // Create documents with specific embeddings
        $doc1 = CaseDocument::factory()->create([
            'case_id' => $case1->id,
            'doc_id' => 'doc-123',
            'title' => 'Contract Document',
            'content' => 'Document content about contract',
            'chunk_index' => 0,
            'metadata' => ['key' => 'value'],
            'embedding_vector' => $embedding1,
        ]);

        $doc2 = CaseDocument::factory()->create([
            'case_id' => $case2->id,
            'doc_id' => 'doc-456',
            'title' => 'Employment Agreement',
            'content' => 'Employment contract content',
            'chunk_index' => 0,
            'metadata' => [],
            'embedding_vector' => $embedding2,
        ]);

        // Mock OpenAI service to return the query embedding
        $openAI = Mockery::mock(OpenAIService::class);
        $openAI->shouldReceive('createEmbedding')
            ->once()
            ->with('contract dispute')
            ->andReturn($queryEmbedding);

        $service = $this->makeService($openAI);

        // Execute vector search
        $result = $service->vectorSearch('contract dispute', ['jurisdiction' => 'Croatia']);

        // Assertions
        $this->assertTrue($result['success']);
        $this->assertEquals('vector', $result['search_type']);
        $this->assertGreaterThanOrEqual(1, $result['count']);
        $this->assertNotEmpty($result['data']);

        // Verify the results contain expected data
        $this->assertIsArray($result['data']);
        $firstResult = (object) $result['data'][0];
        $this->assertObjectHasProperty('case_id', $firstResult);
        $this->assertObjectHasProperty('similarity', $firstResult);
        $this->assertObjectHasProperty('case_number', $firstResult);
        $this->assertObjectHasProperty('jurisdiction', $firstResult);

        // Verify jurisdiction filter worked
        foreach ($result['data'] as $item) {
            $item = (object) $item;
            $this->assertEquals('Croatia', $item->jurisdiction);
        }
    }

    public function test_search_cases_with_filters(): void
    {
        // Create actual case in test database
        $case = LegalCase::factory()->create([
            'case_number' => 'C-123/2024',
            'title' => 'Smith vs Company Inc',
            'client_name' => 'John Smith',
            'opponent_name' => 'Company Inc',
            'court' => 'Commercial Court',
            'jurisdiction' => 'Croatia',
            'judge' => 'Judge Doe',
            'filing_date' => '2024-01-10',
            'status' => 'Active',
            'tags' => ['contract', 'commercial'],
        ]);

        $service = $this->makeService();

        // Execute search cases with filters
        $result = $service->searchCases('Smith', [
            'jurisdiction' => 'Croatia',
            'court' => 'Commercial',
            'status' => 'Active',
        ]);

        // Assertions
        $this->assertTrue($result['success']);
        $this->assertEquals('cases', $result['search_type']);
        $this->assertArrayHasKey('pagination', $result);
        $this->assertGreaterThanOrEqual(1, count($result['data']));
        $this->assertEquals('Smith vs Company Inc', $result['data'][0]['title']);
        $this->assertEquals('John Smith', $result['data'][0]['client_name']);
    }

    public function test_search_documents_with_case_relationship(): void
    {
        // Create actual case and document in test database
        $case = LegalCase::factory()->create([
            'case_number' => 'C-123/2024',
            'title' => 'Test Case',
        ]);

        $document = CaseDocument::factory()->create([
            'case_id' => $case->id,
            'title' => 'Contract Document',
            'content' => 'This document contains details about the contract agreement.',
            'category' => 'Evidence',
            'author' => 'Legal Team',
            'language' => 'en',
            'tags' => ['contract'],
            'chunk_index' => 0,
            'metadata' => [],
            'source' => 'Client',
        ]);

        $service = $this->makeService();

        // Execute search documents
        $result = $service->searchDocuments('contract', [
            'case_id' => $case->id,
            'include_content' => false,
        ]);

        // Assertions
        $this->assertTrue($result['success']);
        $this->assertEquals('documents', $result['search_type']);
        $this->assertArrayHasKey('pagination', $result);
        $this->assertGreaterThanOrEqual(1, count($result['data']));
        $this->assertEquals('Contract Document', $result['data'][0]['title']);
        $this->assertEquals($case->id, $result['data'][0]['case_id']);
    }

    public function test_search_documents_with_include_content(): void
    {
        // Create actual case and document with content
        $case = LegalCase::factory()->create([
            'case_number' => 'C-123/2024',
            'title' => 'Test Case',
        ]);

        $document = CaseDocument::factory()->create([
            'case_id' => $case->id,
            'title' => 'Full Document',
            'content' => 'This is the full content of the document with all details.',
            'category' => 'Evidence',
            'author' => 'Legal Team',
            'language' => 'en',
            'tags' => ['contract'],
            'chunk_index' => 0,
            'metadata' => [],
            'source' => 'Client',
        ]);

        $service = $this->makeService();

        // Execute search documents with content
        $result = $service->searchDocuments('document', [
            'include_content' => true,
        ]);

        // Assertions
        $this->assertTrue($result['success']);
        $this->assertEquals('documents', $result['search_type']);
        $this->assertGreaterThanOrEqual(1, count($result['data']));
        $this->assertArrayHasKey('content', $result['data'][0]);
    }

    public function test_search_method_routes_to_vector(): void
    {
        // Mock OpenAI service
        $openAI = Mockery::mock(OpenAIService::class);
        $mockEmbedding = array_fill(0, 1536, 0.1);
        $openAI->shouldReceive('embeddings')
            ->andReturn([
                'data' => [
                    ['embedding' => $mockEmbedding],
                ],
            ]);

        // Mock DB for vector search
        $queryBuilder = Mockery::mock(Builder::class);
        $queryBuilder->shouldReceive('select')->andReturnSelf();
        $queryBuilder->shouldReceive('leftJoin')->andReturnSelf();
        $queryBuilder->shouldReceive('selectRaw')->andReturnSelf();
        $queryBuilder->shouldReceive('whereRaw')->andReturnSelf();
        $queryBuilder->shouldReceive('orderByRaw')->andReturnSelf();
        $queryBuilder->shouldReceive('limit')->andReturnSelf();
        $queryBuilder->shouldReceive('get')->andReturn(collect([]));

        DB::shouldReceive('connection->getDriverName')->andReturn('pgsql');
        DB::shouldReceive('table')->with('cases_documents')->andReturn($queryBuilder);

        $service = $this->makeService($openAI);

        // Test vector search routing
        $result = $service->search('test', ['search_type' => 'vector']);
        $this->assertEquals('vector', $result['search_type']);
    }

    public function test_search_method_routes_to_cases(): void
    {
        // No database records needed - just testing routing logic
        $service = $this->makeService();

        // Test cases search routing
        $result = $service->search('test', ['search_type' => 'cases']);

        // Verify routing worked correctly
        $this->assertEquals('cases', $result['search_type']);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('pagination', $result);
    }

    public function test_search_method_routes_to_documents(): void
    {
        // No database records needed - just testing routing logic
        $service = $this->makeService();

        // Test documents search routing
        $result = $service->search('test', ['search_type' => 'documents']);

        // Verify routing worked correctly
        $this->assertEquals('documents', $result['search_type']);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('pagination', $result);
    }

    public function test_search_method_throws_exception_for_invalid_type(): void
    {
        $service = $this->makeService();

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
            ->with('test query')
            ->andReturn(null);

        $service = $this->makeService($openAI);

        // Execute vector search and expect SearchException
        $this->expectException(\App\Exceptions\SearchException::class);
        $this->expectExceptionMessage('Failed to generate search embedding');

        $service->vectorSearch('test query');
    }

    public function test_search_cases_with_client_and_opponent_filters(): void
    {
        // Create actual case with client and opponent names
        $case = LegalCase::factory()->create([
            'case_number' => 'C-123/2024',
            'title' => 'Smith vs Johnson',
            'client_name' => 'John Smith',
            'opponent_name' => 'Bob Johnson',
            'court' => 'Civil Court',
            'jurisdiction' => 'Croatia',
            'judge' => 'Judge Doe',
            'filing_date' => '2024-01-10',
            'status' => 'Active',
            'tags' => ['civil'],
        ]);

        $service = $this->makeService();

        // Execute search with client and opponent filters
        $result = $service->searchCases('', [
            'client_name' => 'Smith',
            'opponent_name' => 'Johnson',
        ]);

        // Assertions
        $this->assertTrue($result['success']);
        $this->assertGreaterThanOrEqual(1, count($result['data']));
        $this->assertEquals('John Smith', $result['data'][0]['client_name']);
        $this->assertEquals('Bob Johnson', $result['data'][0]['opponent_name']);
    }

    public function test_search_documents_with_case_number_filter(): void
    {
        // Create actual case and document
        $case = LegalCase::factory()->create([
            'case_number' => 'C-123/2024',
            'title' => 'Test Case',
        ]);

        $document = CaseDocument::factory()->create([
            'case_id' => $case->id,
            'title' => 'Filtered Document',
            'category' => 'Evidence',
            'author' => 'Legal Team',
            'language' => 'en',
            'tags' => [],
            'chunk_index' => 0,
            'metadata' => [],
            'source' => 'Client',
        ]);

        $service = $this->makeService();

        // Execute search with case_number filter via relationship
        $result = $service->searchDocuments('', [
            'case_number' => 'C-123',
        ]);

        // Assertions
        $this->assertTrue($result['success']);
        $this->assertEquals('documents', $result['search_type']);
        $this->assertGreaterThanOrEqual(1, count($result['data']));
    }

    public function test_search_cases_pagination(): void
    {
        // Create 25 actual cases with searchable titles
        LegalCase::factory()->count(25)->create([
            'title' => 'Test Case for Pagination',
            'court' => 'Court',
            'jurisdiction' => 'Croatia',
            'judge' => 'Judge',
            'filing_date' => '2024-01-01',
            'status' => 'Active',
            'tags' => [],
        ]);

        $service = $this->makeService();

        // Execute search with pagination
        $result = $service->searchCases('Pagination', [
            'page' => 2,
            'limit' => 10,
        ]);

        // Assertions
        $this->assertTrue($result['success']);
        $this->assertGreaterThanOrEqual(25, $result['pagination']['total']);
        $this->assertEquals(2, $result['pagination']['page']);
        $this->assertEquals(10, $result['pagination']['limit']);
        $this->assertGreaterThanOrEqual(3, $result['pagination']['pages']);
    }

    // Task 6: Full Case Analysis Tests

    public function test_analyze_case_strength(): void
    {
        // Mock OpenAI API for offline testing
        \Illuminate\Support\Facades\Http::fake([
            'api.openai.com/*' => \Illuminate\Support\Facades\Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'strength_score' => 75,
                            'strengths' => ['Strong evidence', 'Credible witnesses'],
                            'weaknesses' => ['Missing documentation'],
                            'overall_assessment' => 'Moderate to strong case',
                        ]),
                    ],
                ]],
            ], 200),
        ]);

        // Create test case with documents
        $case = LegalCase::factory()
            ->has(CaseDocument::factory()->count(3))
            ->create([
                'case_number' => 'TEST-123/2024',
                'client_name' => 'John Doe',
                'opponent_name' => 'Jane Corp',
                'court' => 'Županijski sud u Osijeku',
                'status' => 'Active',
            ]);

        $service = app(CaseSearchService::class);

        // Execute strength analysis
        $result = $service->analyzeCase($case->id, [
            'analysis_type' => 'strength',
        ]);

        // Assertions
        $this->assertArrayHasKey('case_id', $result);
        $this->assertArrayHasKey('strength_score', $result);
        $this->assertArrayHasKey('strengths', $result);
        $this->assertArrayHasKey('weaknesses', $result);
        $this->assertArrayHasKey('overall_assessment', $result);
        $this->assertEquals($case->id, $result['case_id']);
        $this->assertIsNumeric($result['strength_score']);
        $this->assertIsArray($result['strengths']);
        $this->assertIsArray($result['weaknesses']);
    }

    public function test_analyze_case_risk(): void
    {
        // Create test case
        $case = LegalCase::factory()->create([
            'case_number' => 'TEST-456/2024',
            'status' => 'Pending',
        ]);

        $service = app(CaseSearchService::class);

        // Execute risk analysis
        $result = $service->analyzeCase($case->id, [
            'analysis_type' => 'risk',
        ]);

        // Assertions
        $this->assertArrayHasKey('case_id', $result);
        $this->assertArrayHasKey('risk_level', $result);
        $this->assertArrayHasKey('risks', $result);
        $this->assertArrayHasKey('mitigation_strategies', $result);
        $this->assertEquals($case->id, $result['case_id']);
        $this->assertIsArray($result['risks']);
        $this->assertIsArray($result['mitigation_strategies']);
    }

    public function test_analyze_case_timeline(): void
    {
        // Create test case with filing date
        $case = LegalCase::factory()
            ->has(CaseDocument::factory()->count(2))
            ->create([
                'case_number' => 'TEST-789/2024',
                'filing_date' => now()->subMonths(2),
            ]);

        $service = app(CaseSearchService::class);

        // Execute timeline analysis
        $result = $service->analyzeCase($case->id, [
            'analysis_type' => 'timeline',
        ]);

        // Assertions
        $this->assertArrayHasKey('case_id', $result);
        $this->assertArrayHasKey('events', $result);
        $this->assertArrayHasKey('gaps', $result);
        $this->assertArrayHasKey('key_dates', $result);
        $this->assertEquals($case->id, $result['case_id']);
        $this->assertIsArray($result['events']);
        $this->assertIsArray($result['gaps']);
        $this->assertIsArray($result['key_dates']);
    }

    public function test_analyze_case_evidence(): void
    {
        // Create test case with document evidence
        $case = LegalCase::factory()
            ->has(CaseDocument::factory()->count(5)->state(['category' => 'evidence']))
            ->create([
                'case_number' => 'TEST-999/2024',
            ]);

        $service = app(CaseSearchService::class);

        // Execute evidence analysis
        $result = $service->analyzeCase($case->id, [
            'analysis_type' => 'evidence',
        ]);

        // Assertions
        $this->assertArrayHasKey('case_id', $result);
        $this->assertArrayHasKey('evidence_count', $result);
        $this->assertArrayHasKey('evidence_quality', $result);
        $this->assertArrayHasKey('categories', $result);
        $this->assertArrayHasKey('admissibility_issues', $result);
        $this->assertArrayHasKey('recommendations', $result);
        $this->assertEquals($case->id, $result['case_id']);
        $this->assertIsInt($result['evidence_count']);
        $this->assertIsArray($result['categories']);
        $this->assertIsArray($result['admissibility_issues']);
        $this->assertIsArray($result['recommendations']);
    }
}

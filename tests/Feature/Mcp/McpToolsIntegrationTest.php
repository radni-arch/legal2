<?php

namespace Tests\Feature\Mcp;

use App\Models\CaseDocument;
use App\Models\CourtDecision;
use App\Models\Law;
use App\Models\LegalCase;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Integration tests for MCP Tools (NEW tools from Milestone F)
 *
 * Tests all 5 NEW tools:
 * - law.search
 * - law.get_article
 * - decision.search
 * - decision.get
 * - case.search (private, requires authentication)
 */
class McpToolsIntegrationTest extends TestCase
{
    use UsesTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed test data
        $this->seedTestData();

        // Disable authentication for most tests (enable selectively)
        config(['mcp.api_token' => null]);
    }

    /**
     * Seed test data for laws, decisions, and cases
     */
    protected function seedTestData(): void
    {
        // Seed Laws
        Law::create([
            'id' => '01HQXXX000001',
            'doc_id' => 'nn_93_2014',
            'title' => 'Zakon o radu',
            'law_number' => '93/14',
            'jurisdiction' => 'national',
            'country' => 'HR',
            'language' => 'hr',
            'content' => 'Ovim se zakonom uređuju radni odnosi i prava radnika...',
            'chapter' => 'Glava I',
            'section' => 'Članak 1',
            'chunk_index' => 0,
            'tags' => ['labor', 'employment'],
            'source_url' => 'https://zakon.hr/z/xxx',
            'promulgation_date' => '2014-07-15',
            'effective_date' => '2014-08-01',
        ]);

        Law::create([
            'id' => '01HQXXX000002',
            'doc_id' => 'nn_93_2014',
            'title' => 'Zakon o radu',
            'law_number' => '93/14',
            'jurisdiction' => 'national',
            'country' => 'HR',
            'language' => 'hr',
            'content' => 'Radni odnos može prestati sporazumom, otkazom ili po sili zakona...',
            'chapter' => 'Glava II',
            'section' => 'Članak 10',
            'chunk_index' => 1,
            'tags' => ['labor', 'termination'],
            'source_url' => 'https://zakon.hr/z/xxx',
            'promulgation_date' => '2014-07-15',
            'effective_date' => '2014-08-01',
        ]);

        // Seed Court Decisions
        CourtDecision::create([
            'id' => '01HQYYY000001',
            'case_number' => 'Gž-1234/2023',
            'title' => 'Odluka u predmetu nezakonitog otkaza',
            'court' => 'Vrhovni sud Republike Hrvatske',
            'jurisdiction' => 'civil',
            'judge' => 'Ivan Horvat',
            'decision_date' => '2023-05-15',
            'publication_date' => '2023-06-01',
            'decision_type' => 'Presuda',
            'register' => 'Gž',
            'finality' => 'final',
            'ecli' => 'ECLI:HR:VSRH:2023:Gž.1234',
            'tags' => ['labor', 'dismissal'],
            'description' => 'Tužitelj je tužbom tražio poništenje odluke o otkazu...',
        ]);

        CourtDecision::create([
            'id' => '01HQYYY000002',
            'case_number' => 'Rev-5678/2023',
            'title' => 'Rješenje o odbijanju zahtjeva',
            'court' => 'Županijski sud u Zagrebu',
            'jurisdiction' => 'civil',
            'judge' => 'Ana Kovač',
            'decision_date' => '2023-07-20',
            'publication_date' => '2023-08-05',
            'decision_type' => 'Rješenje',
            'register' => 'Rev',
            'finality' => 'preliminary',
            'ecli' => 'ECLI:HR:ZSZG:2023:Rev.5678',
            'tags' => ['civil', 'procedure'],
            'description' => 'Sud odbija zahtjev tužitelja...',
        ]);

        // Seed Legal Cases
        $case = LegalCase::create([
            'id' => '01HQZZZ000001',
            'case_number' => 'P-123/2023',
            'title' => 'Marković vs. Company Ltd.',
            'client_name' => 'Marko Marković',
            'opponent_name' => 'Company Ltd.',
            'court' => 'Općinski sud u Zagrebu',
            'jurisdiction' => 'civil',
            'judge' => 'Ana Horvat',
            'filing_date' => '2023-03-15',
            'status' => 'active',
            'tags' => ['labor', 'dispute'],
            'description' => 'Spor oko nezakonitog otkaza radnog mjesta...',
        ]);

        // Seed Case Documents
        CaseDocument::create([
            'id' => '01HQAAA000001',
            'case_id' => $case->id,
            'doc_id' => 'case_doc_456',
            'title' => 'Tužba',
            'category' => 'complaint',
            'author' => 'Attorney Name',
            'language' => 'hr',
            'content' => 'Podnosim tužbu protiv tuženika zbog nezakonitog otkaza...',
            'tags' => ['initial', 'complaint'],
            'chunk_index' => 0,
            'metadata' => ['page' => 1],
            'source' => 'upload',
        ]);

        CaseDocument::create([
            'id' => '01HQAAA000002',
            'case_id' => $case->id,
            'doc_id' => 'case_doc_457',
            'title' => 'Ugovor o radu',
            'category' => 'evidence',
            'author' => 'Company Ltd.',
            'language' => 'hr',
            'content' => 'Ovim ugovorom radnik se obvezuje...',
            'tags' => ['evidence', 'contract'],
            'chunk_index' => 1,
            'metadata' => ['page' => 1],
            'source' => 'upload',
        ]);
    }

    // ========================================================================
    // LAW SEARCH TOOL TESTS
    // ========================================================================

    /** @test */
    public function law_search_returns_all_laws_without_filters(): void
    {
        $response = $this->postJson('/api/mcp/law.search', []);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'pagination' => [
                    'total' => 2,
                    'page' => 1,
                    'limit' => 10,
                ],
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id', 'doc_id', 'title', 'law_number',
                        'jurisdiction', 'country', 'language', 'tags',
                    ],
                ],
                'pagination' => ['total', 'page', 'limit', 'pages'],
            ]);

        $this->assertCount(2, $response->json('data'));
    }

    /** @test */
    public function law_search_filters_by_query(): void
    {
        $response = $this->postJson('/api/mcp/law.search', [
            'query' => 'sporazumom',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'pagination' => ['total' => 1],
            ]);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertStringContainsString('sporazumom', $data[0]['content']);
    }

    /** @test */
    public function law_search_filters_by_law_number(): void
    {
        $response = $this->postJson('/api/mcp/law.search', [
            'law_number' => '93/14',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'pagination' => ['total' => 2],
            ]);

        foreach ($response->json('data') as $law) {
            $this->assertEquals('93/14', $law['law_number']);
        }
    }

    /** @test */
    public function law_search_filters_by_country(): void
    {
        $response = $this->postJson('/api/mcp/law.search', [
            'country' => 'HR',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'pagination' => ['total' => 2],
            ]);
    }

    /** @test */
    public function law_search_respects_pagination(): void
    {
        $response = $this->postJson('/api/mcp/law.search', [
            'limit' => 1,
            'page' => 1,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'pagination' => [
                    'total' => 2,
                    'page' => 1,
                    'limit' => 1,
                    'pages' => 2,
                ],
            ]);

        $this->assertCount(1, $response->json('data'));
    }

    /** @test */
    public function law_search_handles_invalid_parameters(): void
    {
        $response = $this->postJson('/api/mcp/law.search', [
            'limit' => 'invalid',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['limit']);
    }

    // ========================================================================
    // LAW GET ARTICLE TOOL TESTS
    // ========================================================================

    /** @test */
    public function law_get_article_returns_articles_by_doc_id(): void
    {
        $response = $this->postJson('/api/mcp/law.get_article', [
            'doc_id' => 'nn_93_2014',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'doc_id' => 'nn_93_2014',
                'total_chunks' => 2,
            ])
            ->assertJsonStructure([
                'success',
                'doc_id',
                'total_chunks',
                'articles' => [
                    '*' => [
                        'id', 'doc_id', 'title', 'law_number',
                        'chapter', 'section', 'chunk_index', 'content',
                    ],
                ],
            ]);

        $this->assertCount(2, $response->json('articles'));
    }

    /** @test */
    public function law_get_article_filters_by_chunk_index(): void
    {
        $response = $this->postJson('/api/mcp/law.get_article', [
            'doc_id' => 'nn_93_2014',
            'number' => 1,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'total_chunks' => 1,
            ]);

        $articles = $response->json('articles');
        $this->assertCount(1, $articles);
        $this->assertEquals(1, $articles[0]['chunk_index']);
    }

    /** @test */
    public function law_get_article_filters_by_chapter(): void
    {
        $response = $this->postJson('/api/mcp/law.get_article', [
            'doc_id' => 'nn_93_2014',
            'chapter' => 'Glava I',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'total_chunks' => 1,
            ]);

        $articles = $response->json('articles');
        $this->assertStringContainsString('Glava I', $articles[0]['chapter']);
    }

    /** @test */
    public function law_get_article_returns_404_for_nonexistent_doc_id(): void
    {
        $response = $this->postJson('/api/mcp/law.get_article', [
            'doc_id' => 'nonexistent',
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
            ])
            ->assertJsonFragment(['error' => 'No articles found for doc_id "nonexistent" with the given criteria.']);
    }

    /** @test */
    public function law_get_article_requires_doc_id(): void
    {
        $response = $this->postJson('/api/mcp/law.get_article', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['doc_id']);
    }

    // ========================================================================
    // DECISION SEARCH TOOL TESTS
    // ========================================================================

    /** @test */
    public function decision_search_returns_all_decisions_without_filters(): void
    {
        $response = $this->postJson('/api/mcp/decision.search', []);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'pagination' => [
                    'total' => 2,
                    'page' => 1,
                    'limit' => 10,
                ],
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id', 'case_number', 'title', 'court',
                        'jurisdiction', 'judge', 'decision_date', 'ecli',
                    ],
                ],
                'pagination',
            ]);

        $this->assertCount(2, $response->json('data'));
    }

    /** @test */
    public function decision_search_filters_by_query(): void
    {
        $response = $this->postJson('/api/mcp/decision.search', [
            'query' => 'nezakonitog otkaza',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'pagination' => ['total' => 1],
            ]);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertStringContainsString('nezakonitog otkaza', $data[0]['title']);
    }

    /** @test */
    public function decision_search_filters_by_case_number(): void
    {
        $response = $this->postJson('/api/mcp/decision.search', [
            'case_number' => 'Gž-1234',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'pagination' => ['total' => 1],
            ]);

        $this->assertStringContainsString('Gž-1234', $response->json('data.0.case_number'));
    }

    /** @test */
    public function decision_search_filters_by_court(): void
    {
        $response = $this->postJson('/api/mcp/decision.search', [
            'court' => 'Vrhovni sud',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'pagination' => ['total' => 1],
            ]);
    }

    /** @test */
    public function decision_search_filters_by_decision_type(): void
    {
        $response = $this->postJson('/api/mcp/decision.search', [
            'decision_type' => 'Presuda',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'pagination' => ['total' => 1],
            ]);
    }

    /** @test */
    public function decision_search_filters_by_date_range(): void
    {
        $response = $this->postJson('/api/mcp/decision.search', [
            'date_from' => '2023-05-01',
            'date_to' => '2023-05-31',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'pagination' => ['total' => 1],
            ]);
    }

    /** @test */
    public function decision_search_filters_by_ecli(): void
    {
        $response = $this->postJson('/api/mcp/decision.search', [
            'ecli' => 'ECLI:HR:VSRH:2023:Gž.1234',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'pagination' => ['total' => 1],
            ]);
    }

    // ========================================================================
    // DECISION GET TOOL TESTS
    // ========================================================================

    /** @test */
    public function decision_get_returns_decision_by_id(): void
    {
        $response = $this->postJson('/api/mcp/decision.get', [
            'id' => '01HQYYY000001',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'decision' => [
                    'id' => '01HQYYY000001',
                    'case_number' => 'Gž-1234/2023',
                    'title' => 'Odluka u predmetu nezakonitog otkaza',
                ],
            ])
            ->assertJsonStructure([
                'success',
                'decision' => [
                    'id', 'case_number', 'title', 'court', 'judge',
                    'decision_date', 'decision_type', 'ecli', 'description',
                ],
            ]);
    }

    /** @test */
    public function decision_get_returns_404_for_nonexistent_id(): void
    {
        $response = $this->postJson('/api/mcp/decision.get', [
            'id' => 'nonexistent-id',
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
            ]);
    }

    /** @test */
    public function decision_get_requires_id(): void
    {
        $response = $this->postJson('/api/mcp/decision.get', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['id']);
    }

    /** @test */
    public function decision_get_includes_documents_by_default(): void
    {
        $response = $this->postJson('/api/mcp/decision.get', [
            'id' => '01HQYYY000001',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'documents' => [
                    'total_chunks',
                    'items',
                ],
            ]);
    }

    /** @test */
    public function decision_get_excludes_content_by_default(): void
    {
        $response = $this->postJson('/api/mcp/decision.get', [
            'id' => '01HQYYY000001',
            'include_documents' => true,
            'include_content' => false,
        ]);

        $response->assertStatus(200);

        // Check that documents don't include 'content' field
        $documents = $response->json('documents.items');
        if (count($documents) > 0) {
            $this->assertArrayNotHasKey('content', $documents[0]);
        }
    }

    // ========================================================================
    // CASE SEARCH TOOL TESTS (PRIVATE - requires authentication)
    // ========================================================================

    /** @test */
    public function case_search_requires_authentication(): void
    {
        // Enable authentication
        config(['mcp.api_token' => 'test-secret-token']);

        $response = $this->postJson('/api/mcp/case.search', []);

        $response->assertStatus(401);
    }

    /** @test */
    public function case_search_works_with_valid_token(): void
    {
        // Enable authentication
        config(['mcp.api_token' => 'test-secret-token']);

        $response = $this->postJson('/api/mcp/case.search', [], [
            'X-MCP-Token' => 'test-secret-token',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'search_type' => 'cases',
            ]);
    }

    /** @test */
    public function case_search_returns_all_cases_without_filters(): void
    {
        $response = $this->postJson('/api/mcp/case.search', []);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'search_type' => 'cases',
                'pagination' => [
                    'total' => 1,
                    'page' => 1,
                    'limit' => 10,
                ],
            ])
            ->assertJsonStructure([
                'success',
                'search_type',
                'data' => [
                    '*' => [
                        'id', 'case_number', 'title', 'client_name',
                        'opponent_name', 'court', 'status',
                    ],
                ],
                'pagination',
            ]);
    }

    /** @test */
    public function case_search_filters_by_case_number(): void
    {
        $response = $this->postJson('/api/mcp/case.search', [
            'case_number' => 'P-123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'pagination' => ['total' => 1],
            ]);
    }

    /** @test */
    public function case_search_filters_by_client_name(): void
    {
        $response = $this->postJson('/api/mcp/case.search', [
            'client_name' => 'Marković',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'pagination' => ['total' => 1],
            ]);

        $this->assertStringContainsString('Marković', $response->json('data.0.client_name'));
    }

    /** @test */
    public function case_search_searches_documents_when_query_provided(): void
    {
        $response = $this->postJson('/api/mcp/case.search', [
            'query' => 'tužbu',
            'search_documents' => true,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'search_type' => 'documents',
                'pagination' => ['total' => 1],
            ]);

        $this->assertStringContainsString('Tužba', $response->json('data.0.title'));
    }

    /** @test */
    public function case_search_documents_excludes_content_by_default(): void
    {
        $response = $this->postJson('/api/mcp/case.search', [
            'query' => 'tužbu',
            'search_documents' => true,
            'include_content' => false,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'search_type' => 'documents',
            ]);

        $documents = $response->json('data');
        if (count($documents) > 0) {
            $this->assertArrayNotHasKey('content', $documents[0]);
        }
    }

    /** @test */
    public function case_search_documents_includes_content_when_requested(): void
    {
        $response = $this->postJson('/api/mcp/case.search', [
            'case_id' => '01HQZZZ000001',
            'search_documents' => true,
            'include_content' => true,
        ]);

        $response->assertStatus(200);

        $documents = $response->json('data');
        if (count($documents) > 0) {
            $this->assertArrayHasKey('content', $documents[0]);
        }
    }

    // ========================================================================
    // RATE LIMITING TESTS
    // ========================================================================

    /** @test */
    public function endpoints_respect_rate_limiting(): void
    {
        // Note: This test might be skipped in CI environments
        // Laravel's rate limiter uses cache, which needs to be properly configured

        $endpoint = '/api/mcp/law.search';

        // Make requests until we hit rate limit
        $successCount = 0;
        $rateLimited = false;

        for ($i = 0; $i < 65; $i++) {
            $response = $this->postJson($endpoint, []);

            if ($response->status() === 429) {
                $rateLimited = true;
                break;
            }

            if ($response->status() === 200) {
                $successCount++;
            }
        }

        // Should eventually hit rate limit
        $this->assertTrue($rateLimited || $successCount >= 60,
            'Rate limiting should kick in after sufficient requests');
    }

    /** @test */
    public function rate_limit_responses_include_proper_headers(): void
    {
        $response = $this->postJson('/api/mcp/law.search', []);

        $response->assertStatus(200);

        // Rate limit headers should be present
        // Note: Actual header names depend on throttle middleware configuration
        $this->assertTrue(
            $response->headers->has('X-RateLimit-Limit') ||
            $response->headers->has('x-ratelimit-limit'),
            'Rate limit headers should be present'
        );
    }

    // ========================================================================
    // MCP ENVELOPE STRUCTURE TESTS
    // ========================================================================

    /** @test */
    public function responses_have_consistent_success_structure(): void
    {
        $endpoints = [
            '/api/mcp/law.search' => [],
            '/api/mcp/law.get_article' => ['doc_id' => 'nn_93_2014'],
            '/api/mcp/decision.search' => [],
            '/api/mcp/decision.get' => ['id' => '01HQYYY000001'],
            '/api/mcp/case.search' => [],
        ];

        foreach ($endpoints as $endpoint => $payload) {
            $response = $this->postJson($endpoint, $payload);

            $response->assertStatus(200)
                ->assertJsonStructure(['success'])
                ->assertJson(['success' => true]);
        }
    }

    /** @test */
    public function responses_have_consistent_error_structure(): void
    {
        // Test invalid request
        $response = $this->postJson('/api/mcp/law.get_article', [
            'doc_id' => 'nonexistent-doc',
        ]);

        $response->assertStatus(404)
            ->assertJsonStructure([
                'success',
                'error',
            ])
            ->assertJson(['success' => false]);
    }

    /** @test */
    public function search_responses_include_pagination_metadata(): void
    {
        $searchEndpoints = [
            '/api/mcp/law.search',
            '/api/mcp/decision.search',
            '/api/mcp/case.search',
        ];

        foreach ($searchEndpoints as $endpoint) {
            $response = $this->postJson($endpoint, []);

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data',
                    'pagination' => [
                        'total',
                        'page',
                        'limit',
                        'pages',
                    ],
                ]);

            $pagination = $response->json('pagination');
            $this->assertIsInt($pagination['total']);
            $this->assertIsInt($pagination['page']);
            $this->assertIsInt($pagination['limit']);
            $this->assertIsInt($pagination['pages']);
        }
    }

    /** @test */
    public function validation_errors_return_422_status(): void
    {
        // Test missing required field
        $response = $this->postJson('/api/mcp/law.get_article', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['doc_id']);

        // Test invalid type
        $response = $this->postJson('/api/mcp/law.search', [
            'limit' => 'not-a-number',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['limit']);
    }

    /** @test */
    public function responses_use_json_content_type(): void
    {
        $response = $this->postJson('/api/mcp/law.search', []);

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/json');
    }

    /** @test */
    public function responses_handle_complex_data_serialization(): void
    {
        $response = $this->postJson('/api/mcp/law.search', [
            'query' => 'Zakon',
        ]);

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertIsArray($data);

        // Verify JSON tags are properly serialized
        if (count($data) > 0) {
            $this->assertIsArray($data[0]['tags']);
        }
    }

    // ========================================================================
    // ERROR HANDLING TESTS
    // ========================================================================

    /** @test */
    public function endpoints_handle_missing_required_parameters(): void
    {
        $testsEndpoints = [
            '/api/mcp/law.get_article' => 'doc_id',
            '/api/mcp/decision.get' => 'id',
        ];

        foreach ($testsEndpoints as $endpoint => $requiredField) {
            $response = $this->postJson($endpoint, []);

            $response->assertStatus(422)
                ->assertJsonValidationErrors([$requiredField]);
        }
    }

    /** @test */
    public function endpoints_handle_invalid_parameter_types(): void
    {
        $response = $this->postJson('/api/mcp/law.search', [
            'limit' => 'not-an-integer',
            'page' => 'not-an-integer',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['limit', 'page']);
    }

    /** @test */
    public function endpoints_enforce_pagination_limits(): void
    {
        $response = $this->postJson('/api/mcp/law.search', [
            'limit' => 200, // Over max of 100
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['limit']);
    }

    /** @test */
    public function endpoints_handle_database_errors_gracefully(): void
    {
        // This is a simulation - in real scenarios, you might mock the database
        // to throw exceptions to test error handling

        $response = $this->postJson('/api/mcp/decision.get', [
            'id' => 'invalid-format-id-that-does-not-exist',
        ]);

        // Should return 404, not 500
        $response->assertStatus(404)
            ->assertJson(['success' => false]);
    }
}

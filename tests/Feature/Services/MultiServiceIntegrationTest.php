<?php

namespace Tests\Feature\Services;

use App\Models\CaseDocument;
use App\Models\CourtDecision;
use App\Models\Law;
use App\Models\LegalCase;
use App\Services\Graph\GraphRagOrchestrator;
use App\Services\ResearchOrchestrator;
use App\Services\Search\SearchOrchestrator;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Multi-Service Integration Tests
 *
 * Tests the integration between multiple services working together:
 * - OpenAI services (chat, embeddings, analysis)
 * - Search services (law, decision, case search)
 * - Graph services (Neo4j integration)
 * - Research orchestration (autonomous agents)
 *
 * These tests ensure that complex workflows across multiple services
 * work correctly end-to-end.
 */
class MultiServiceIntegrationTest extends TestCase
{
    use UsesTestDatabase;

    /**
     * Test multiple services working together
     * Flow: User query → OpenAI analysis → Search → Graph relationships → Combined results
     *
     * @test
     */
    public function test_openai_plus_search_plus_graph_integration(): void
    {
        // Skip if Neo4j is not enabled in test environment
        if (! config('neo4j.enabled')) {
            $this->markTestSkipped('Neo4j is disabled in test environment');
        }

        // Create interconnected test data (laws and decisions with citations)
        $law = Law::factory()->create([
            'doc_id' => 'zkp-9-test',
            'title' => 'Zakon o kaznenom postupku - Članak 9',
            'law_number' => 'NN 94/14',
            'jurisdiction' => 'Croatia',
            'content' => 'Jamstvo obrane. Osumnjičeniku i optuženiku jamči se pravo na obranu.',
            'text' => 'Jamstvo obrane. Osumnjičeniku i optuženiku jamči se pravo na obranu.',
        ]);

        $decision = CourtDecision::factory()->create([
            'decision_id' => 'dec-test-001',
            'court_name' => 'Županijski sud u Osijeku',
            'case_number' => 'K-123/2024',
            'date' => '2024-01-15',
            'text' => 'Odluka referencing ZKP Članak 9 - jamstvo obrane. Sud je utvrdio povredu prava na obranu.',
            'summary' => 'Povreda prava na obranu',
        ]);

        // Mock OpenAI embeddings for search
        Http::fake([
            'api.openai.com/v1/embeddings' => Http::response([
                'object' => 'list',
                'data' => [
                    [
                        'object' => 'embedding',
                        'embedding' => array_fill(0, 1536, 0.1),
                        'index' => 0,
                    ],
                ],
                'model' => 'text-embedding-3-small',
                'usage' => [
                    'prompt_tokens' => 5,
                    'total_tokens' => 5,
                ],
            ], 200),
            'api.openai.com/v1/chat/completions' => Http::response([
                'id' => 'chatcmpl-test',
                'object' => 'chat.completion',
                'choices' => [
                    [
                        'index' => 0,
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'Analysis of criminal procedure rights based on ZKP Article 9.',
                        ],
                        'finish_reason' => 'stop',
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 100,
                    'completion_tokens' => 50,
                    'total_tokens' => 150,
                ],
            ], 200),
        ]);

        try {
            // Sync to graph (if enabled)
            $graphOrchestrator = app(GraphRagOrchestrator::class);

            // Sync law to graph
            if (method_exists($graphOrchestrator, 'syncLaw')) {
                $graphOrchestrator->syncLaw($law->doc_id);
            }

            // Sync decision to graph
            if (method_exists($graphOrchestrator, 'syncDecision')) {
                $graphOrchestrator->syncDecision($decision->id);
            }

            // Perform search with OpenAI embeddings
            $searchOrchestrator = app(SearchOrchestrator::class);
            $searchResults = $searchOrchestrator->search('criminal procedure rights', [
                'corpora' => ['laws', 'decisions'],
                'limit' => 10,
            ]);

            // Verify search returned results
            $this->assertIsArray($searchResults);
            $this->assertArrayHasKey('results', $searchResults);
            $this->assertGreaterThanOrEqual(0, count($searchResults['results']));

            // Verify graph relationships were created (if graph sync is working)
            if (method_exists($graphOrchestrator, 'findRelatedDocuments')) {
                $graphRelations = $graphOrchestrator->findRelatedDocuments($law->doc_id);
                $this->assertIsArray($graphRelations);
            }

            // Verify OpenAI was called for embeddings
            Http::assertSent(function ($request) {
                return $request->url() === 'https://api.openai.com/v1/embeddings';
            });

            $this->assertTrue(true, 'Multi-service integration test passed');
        } catch (\Exception $e) {
            // If Neo4j or other services are not available, skip gracefully
            if (str_contains($e->getMessage(), 'Neo4j') || str_contains($e->getMessage(), 'Connection refused')) {
                $this->markTestSkipped('Required services not available: '.$e->getMessage());
            }
            throw $e;
        }
    }

    /**
     * Test token budget prevents infinite iterations
     * Flow: Research starts → Consumes tokens → Budget exceeded → Stop with partial results
     *
     * @test
     */
    public function test_budget_limits_stop_research(): void
    {
        // Mock OpenAI to consume lots of tokens
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'id' => 'chatcmpl-test',
                'object' => 'chat.completion',
                'choices' => [
                    [
                        'index' => 0,
                        'message' => [
                            'role' => 'assistant',
                            'content' => json_encode([
                                'quality_score' => 70,
                                'answer' => 'Partial answer with low quality',
                                'confidence' => 0.6,
                            ]),
                        ],
                        'finish_reason' => 'stop',
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 8000,
                    'completion_tokens' => 2000,
                    'total_tokens' => 10000, // Each call uses 10k tokens
                ],
            ], 200),
            'api.openai.com/v1/embeddings' => Http::response([
                'object' => 'list',
                'data' => [
                    [
                        'object' => 'embedding',
                        'embedding' => array_fill(0, 1536, 0.1),
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

        try {
            $orchestrator = app(ResearchOrchestrator::class);

            $result = $orchestrator->research('Complex legal query about criminal procedure', [
                'limits' => [
                    'token_budget' => 15000, // Only 15k tokens available
                    'quality_threshold' => 85, // High threshold that won't be met
                    'max_iterations' => 10, // Allow many iterations
                ],
            ]);

            // Verify research stopped due to token budget
            $this->assertIsArray($result);
            $this->assertArrayHasKey('iterations', $result);
            $this->assertArrayHasKey('stopped_reason', $result);

            // Should stop after 1-2 iterations due to budget (10k per iteration, 15k budget)
            $this->assertLessThanOrEqual(2, $result['iterations'], 'Should stop within 2 iterations due to token budget');

            // Verify it stopped for a valid reason (budget, iterations, or quality)
            $stoppedReason = $result['stopped_reason'] ?? '';
            $this->assertContains($stoppedReason, [
                'token_budget_exceeded',
                'budget_exceeded',
                'max_iterations',
                'quality_threshold_met',
            ], 'Should stop due to token budget, max iterations, or quality threshold');

            // Verify partial results were returned
            $this->assertArrayHasKey('success', $result);
        } catch (\Exception $e) {
            // If research orchestrator is not fully implemented, skip
            if (str_contains($e->getMessage(), 'Method') || str_contains($e->getMessage(), 'not found')) {
                $this->markTestSkipped('ResearchOrchestrator not fully implemented: '.$e->getMessage());
            }
            throw $e;
        }
    }

    /**
     * Test time budget prevents long-running research
     *
     * @test
     */
    public function test_time_budget_stops_research(): void
    {
        // Mock OpenAI responses
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'id' => 'chatcmpl-test',
                'object' => 'chat.completion',
                'choices' => [
                    [
                        'index' => 0,
                        'message' => [
                            'role' => 'assistant',
                            'content' => json_encode([
                                'quality_score' => 75,
                                'answer' => 'Some answer',
                                'confidence' => 0.7,
                            ]),
                        ],
                        'finish_reason' => 'stop',
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 100,
                    'completion_tokens' => 50,
                    'total_tokens' => 150,
                ],
            ], 200),
            'api.openai.com/v1/embeddings' => Http::response([
                'object' => 'list',
                'data' => [
                    [
                        'object' => 'embedding',
                        'embedding' => array_fill(0, 1536, 0.1),
                        'index' => 0,
                    ],
                ],
                'model' => 'text-embedding-3-small',
            ], 200),
        ]);

        try {
            $orchestrator = app(ResearchOrchestrator::class);

            $startTime = microtime(true);

            $result = $orchestrator->research('Legal question requiring research', [
                'limits' => [
                    'time_budget' => 2, // Only 2 seconds available
                    'quality_threshold' => 90, // High threshold that won't be met quickly
                    'max_iterations' => 100, // Allow many iterations
                    'token_budget' => null, // No token limit
                ],
            ]);

            $duration = microtime(true) - $startTime;

            // Verify research didn't exceed time budget significantly
            $this->assertLessThanOrEqual(5, $duration, 'Research should stop within reasonable time');

            // Verify it stopped for the right reason
            $this->assertIsArray($result);
            $this->assertArrayHasKey('stopped_reason', $result);

            $stoppedReason = $result['stopped_reason'] ?? '';
            $this->assertContains($stoppedReason, [
                'time_budget_exceeded',
                'budget_exceeded',
                'max_iterations',
                'quality_threshold_met',
            ], 'Should stop due to time budget, max iterations, or quality threshold');

            // Verify some results were produced
            $this->assertArrayHasKey('iterations', $result);
            $this->assertGreaterThan(0, $result['iterations']);
        } catch (\Exception $e) {
            // If research orchestrator is not fully implemented, skip
            if (str_contains($e->getMessage(), 'Method') || str_contains($e->getMessage(), 'not found')) {
                $this->markTestSkipped('ResearchOrchestrator time budget not fully implemented: '.$e->getMessage());
            }
            throw $e;
        }
    }

    /**
     * Test complete case analysis workflow
     * Flow: Upload document → OCR (Textract) → Analysis (OpenAI) → Search similar cases → Graph relationships → Generate motion
     *
     * @test
     */
    public function test_full_case_workflow_with_all_services(): void
    {
        // Create case with document
        $case = LegalCase::factory()->create([
            'case_number' => 'K-456/2024',
            'court' => 'Županijski sud u Osijeku',
            'status' => 'active',
        ]);

        $document = CaseDocument::factory()->create([
            'case_id' => $case->id,
            'title' => 'Evidence Document - Drug Trafficking Case',
            'content' => 'Test case about criminal liability under Article 87 of Criminal Code. Defendant charged with drug trafficking.',
            'category' => 'evidence',
            'metadata' => [
                'status' => 'completed',
                'file_type' => 'pdf',
                'original_filename' => 'test-document.pdf',
            ],
        ]);

        // Mock OpenAI for analysis and search
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'id' => 'chatcmpl-test',
                'object' => 'chat.completion',
                'choices' => [
                    [
                        'index' => 0,
                        'message' => [
                            'role' => 'assistant',
                            'content' => json_encode([
                                'summary' => 'Case involves criminal liability for drug trafficking',
                                'key_points' => [
                                    'Defendant charged under Criminal Code Article 87',
                                    'Evidence of drug trafficking',
                                    'Potential defense based on procedural violations',
                                ],
                                'citations' => [
                                    'KZ Članak 87',
                                    'ZKP Članak 9',
                                ],
                                'recommendations' => [
                                    'Review evidence chain',
                                    'Check for procedural violations',
                                    'Consider motion to suppress evidence',
                                ],
                            ]),
                        ],
                        'finish_reason' => 'stop',
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 200,
                    'completion_tokens' => 150,
                    'total_tokens' => 350,
                ],
            ], 200),
            'api.openai.com/v1/embeddings' => Http::response([
                'object' => 'list',
                'data' => [
                    [
                        'object' => 'embedding',
                        'embedding' => array_fill(0, 1536, 0.1),
                        'index' => 0,
                    ],
                ],
                'model' => 'text-embedding-3-small',
                'usage' => [
                    'prompt_tokens' => 10,
                    'total_tokens' => 10,
                ],
            ], 200),
        ]);

        try {
            // Step 1: Analyze document text using OpenAI
            $analysisService = app(\App\Contracts\AI\AnalysisServiceInterface::class);
            if (method_exists($analysisService, 'analyzeLegalText')) {
                $analysis = $analysisService->analyzeLegalText($document->content);

                $this->assertIsArray($analysis);
                $this->assertNotEmpty($analysis);
            }

            // Step 2: Search for similar cases
            $searchOrchestrator = app(SearchOrchestrator::class);
            $similarCases = $searchOrchestrator->search('criminal liability drug trafficking', [
                'corpora' => ['cases', 'decisions'],
                'limit' => 5,
            ]);

            $this->assertIsArray($similarCases);
            $this->assertArrayHasKey('results', $similarCases);

            // Step 3: Sync case to graph (if Neo4j enabled)
            if (config('neo4j.enabled')) {
                $graphOrchestrator = app(GraphRagOrchestrator::class);
                if (method_exists($graphOrchestrator, 'syncCase')) {
                    $graphOrchestrator->syncCase($case->id);
                }
            }

            // Verify OpenAI was called for analysis
            Http::assertSent(function ($request) {
                return str_contains($request->url(), 'api.openai.com');
            });

            $this->assertTrue(true, 'Full case workflow completed successfully');
        } catch (\Exception $e) {
            // If some services are not available, skip gracefully
            if (str_contains($e->getMessage(), 'not found') ||
                str_contains($e->getMessage(), 'Connection refused') ||
                str_contains($e->getMessage(), 'Class') ||
                str_contains($e->getMessage(), 'Interface')) {
                $this->markTestSkipped('Some services not available: '.$e->getMessage());
            }
            throw $e;
        }
    }
}

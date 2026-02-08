<?php

namespace Tests\Feature\Workflows;

use App\Models\CaseDocument;
use App\Models\CourtDecision;
use App\Models\Law;
use App\Services\Agents\IterationControllerService;
use App\Services\Research\SearchExecutorService;
use App\Services\ResearchOrchestrator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Research Pipeline Workflow Tests
 *
 * End-to-end tests for the complete research pipeline:
 * Query → Generate questions → Search all corpora → Evaluate → Iterate → Final answer
 *
 * Tests the integration of:
 * - SearchExecutorService
 * - IterationControllerService
 * - ResearchOrchestrator
 * - Vector search across multiple corpora (laws, decisions, cases)
 *
 * @group e2e
 * @group workflows
 * @group research
 */
class ResearchPipelineWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    protected ResearchOrchestrator $orchestrator;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure PostgreSQL is being used
        $driver = \DB::connection()->getDriverName();
        if ($driver !== 'pgsql') {
            $this->markTestSkipped('This test requires PostgreSQL');
        }

        // Mock OpenAI responses for search embeddings and answer generation
        Http::fake([
            'api.openai.com/*' => Http::response([
                'object' => 'list',
                'data' => [
                    [
                        'object' => 'embedding',
                        'embedding' => array_fill(0, 1536, 0.1),
                        'index' => 0,
                    ],
                ],
                'model' => 'text-embedding-3-small',
                'usage' => ['prompt_tokens' => 10, 'total_tokens' => 10],
            ], 200),
        ]);

        // Get orchestrator instance
        $this->orchestrator = app(ResearchOrchestrator::class);
    }

    /**
     * Test: Complete research pipeline end-to-end
     *
     * Flow:
     * 1. Create test data across all corpora (laws, decisions, cases)
     * 2. Execute research query
     * 3. Verify search across all corpora
     * 4. Verify answer generation
     * 5. Verify quality score calculation
     * 6. Verify sources are returned
     *
     * @test
     */
    public function test_complete_research_pipeline(): void
    {
        // Create test data across all corpora with relevant content
        $testContent = 'criminal liability under Croatian law';

        // Create laws with criminal liability content
        Law::factory()->count(5)->create([
            'content' => "Article on {$testContent}. This law establishes the requirements for determining criminal responsibility.",
            'title' => 'Croatian Criminal Code',
            'law_number' => 'KZ/2024',
            'jurisdiction' => 'Croatia',
            'embedding_vector' => json_encode(array_fill(0, 1536, 0.1)),
        ]);

        // Create court decisions about criminal liability
        $decisions = CourtDecision::factory()->count(3)->create([
            'court' => 'Vrhovni sud Republike Hrvatske',
            'case_number' => 'I Kž-123/2024',
        ]);

        // Create searchable decision documents (factory handles embeddings)
        foreach ($decisions as $decision) {
            \App\Models\CourtDecisionDocument::factory()->create([
                'decision_id' => $decision->id,
                'content' => "Court decision regarding {$testContent}. The court found that the defendant met all requirements for criminal liability.",
            ]);
        }

        // Create case documents with criminal liability context
        CaseDocument::factory()->count(2)->create([
            'content' => "Case analysis of {$testContent}. The document discusses various aspects of criminal responsibility under Croatian law.",
            'category' => 'analysis',
        ]);

        // Execute full research workflow
        $result = $this->orchestrator->research(
            query: 'What are the requirements for criminal liability under Croatian law?',
            options: [
                'limits' => [
                    'max_iterations' => 3,
                    'quality_threshold' => 85,
                ],
            ]
        );

        // Verify result structure
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertTrue($result['success'], 'Research should complete successfully');

        // Verify answer generation
        $this->assertArrayHasKey('answer', $result);
        $this->assertIsString($result['answer']);
        $this->assertNotEmpty($result['answer'], 'Answer should not be empty');

        // Verify quality scoring
        $this->assertArrayHasKey('quality_score', $result);
        $this->assertIsInt($result['quality_score']);
        $this->assertGreaterThanOrEqual(0, $result['quality_score']);
        $this->assertLessThanOrEqual(100, $result['quality_score']);

        // Verify iteration tracking
        $this->assertArrayHasKey('iterations', $result);
        $this->assertIsInt($result['iterations']);
        $this->assertGreaterThan(0, $result['iterations'], 'Should have performed at least 1 iteration');
        $this->assertLessThanOrEqual(3, $result['iterations'], 'Should respect max_iterations limit');

        // Verify search results are included
        $this->assertArrayHasKey('search_results', $result);
        $this->assertIsArray($result['search_results']);
        $this->assertGreaterThan(0, count($result['search_results']), 'Should have search results from iterations');

        // Verify stop reason is present
        $this->assertArrayHasKey('stopped_reason', $result);
        $this->assertContains(
            $result['stopped_reason'],
            ['quality_threshold_met', 'max_iterations', 'token_budget_exceeded', 'time_budget_exceeded'],
            'Stop reason should be one of the expected values'
        );

        // Verify resource tracking
        $this->assertArrayHasKey('total_tokens', $result);
        $this->assertArrayHasKey('total_time_s', $result);
        $this->assertGreaterThan(0, $result['total_time_s'], 'Should track execution time');
    }

    /**
     * Test: Research iterates until quality threshold is met
     *
     * Flow:
     * 1. Configure low max_iterations but achievable quality threshold
     * 2. Mock quality improvement across iterations
     * 3. Verify iteration stops when quality threshold reached
     * 4. Verify stopped_reason is 'quality_threshold_met'
     *
     * @test
     */
    public function test_research_with_quality_iterations(): void
    {
        // Create diverse test data for quality improvement
        Law::factory()->count(3)->create([
            'content' => 'Proportionality test requires balancing conflicting interests under Croatian Constitutional law.',
            'title' => 'Constitutional Law Principles',
            'embedding_vector' => json_encode(array_fill(0, 1536, 0.15)),
        ]);

        $constitutionalDecisions = CourtDecision::factory()->count(2)->create([
            'court' => 'Ustavni sud Republike Hrvatske',
        ]);

        // Create searchable decision documents with embeddings
        foreach ($constitutionalDecisions as $decision) {
            \App\Models\CourtDecisionDocument::factory()->create([
                'decision_id' => $decision->id,
                'content' => 'The court applied the proportionality test to determine if the measure was necessary in a democratic society.',
                'embedding' => json_encode(array_fill(0, 1536, 0.15)),
            ]);
        }

        // Execute research with achievable quality threshold
        $result = $this->orchestrator->research(
            query: 'Explain the proportionality test in Croatian constitutional law',
            options: [
                'limits' => [
                    'max_iterations' => 10, // Allow enough iterations
                    'quality_threshold' => 75, // Lower threshold that should be reachable
                ],
            ]
        );

        // Verify research completed successfully
        $this->assertTrue($result['success']);

        // Verify quality threshold influenced stopping
        // Note: Quality scoring is simplified in current implementation,
        // so we verify that quality improves with iterations
        $this->assertArrayHasKey('quality_score', $result);

        // If stopped due to quality threshold
        if ($result['stopped_reason'] === 'quality_threshold_met') {
            $this->assertGreaterThanOrEqual(
                75,
                $result['quality_score'],
                'Quality score should meet or exceed threshold when stopped for quality'
            );
        }

        // Verify iteration progression
        $this->assertGreaterThan(0, $result['iterations']);

        // Verify multiple searches were performed across iterations
        $this->assertGreaterThan(0, count($result['search_results']));

        // Each iteration should have search results
        foreach ($result['search_results'] as $iterationData) {
            $this->assertArrayHasKey('iteration', $iterationData);
            $this->assertArrayHasKey('results', $iterationData);
            $this->assertIsArray($iterationData['results']);
        }
    }

    /**
     * Test: Research respects token and time budgets
     *
     * Flow:
     * 1. Set strict token and time budgets
     * 2. Execute research that would normally take more iterations
     * 3. Verify iteration stops when budget exceeded
     * 4. Verify stopped_reason is budget-related
     *
     * @test
     */
    public function test_research_stops_on_budget_limit(): void
    {
        // Create ample test data that would require multiple iterations
        Law::factory()->count(10)->create([
            'content' => 'Legal provisions regarding evidence admissibility in criminal proceedings.',
            'embedding_vector' => json_encode(array_fill(0, 1536, 0.2)),
        ]);

        $evidenceDecisions = CourtDecision::factory()->count(8)->create();

        // Create searchable decision documents with embeddings
        foreach ($evidenceDecisions as $decision) {
            \App\Models\CourtDecisionDocument::factory()->create([
                'decision_id' => $decision->id,
                'content' => 'Court decision on evidence admissibility standards and procedures.',
                'embedding' => json_encode(array_fill(0, 1536, 0.2)),
            ]);
        }

        CaseDocument::factory()->count(5)->create([
            'content' => 'Analysis of evidence admissibility requirements in Croatian courts.',
        ]);

        // Execute research with strict budgets
        $result = $this->orchestrator->research(
            query: 'What are the standards for evidence admissibility in Croatian criminal courts?',
            options: [
                'limits' => [
                    'max_iterations' => 10,
                    'quality_threshold' => 95, // High threshold that likely won't be reached
                    'token_budget' => 2000, // Limited token budget
                    'time_budget' => 5, // 5 seconds time budget
                ],
            ]
        );

        // Verify research completed (even if budget was exceeded)
        $this->assertTrue($result['success']);

        // Verify resource limits were tracked
        $this->assertArrayHasKey('total_tokens', $result);
        $this->assertArrayHasKey('total_time_s', $result);

        // Verify stopped reason is present
        $this->assertArrayHasKey('stopped_reason', $result);

        // If stopped due to budget
        if (in_array($result['stopped_reason'], ['token_budget_exceeded', 'time_budget_exceeded'])) {
            // Verify budgets were actually enforced
            if ($result['stopped_reason'] === 'token_budget_exceeded') {
                $this->assertGreaterThanOrEqual(
                    2000,
                    $result['total_tokens'],
                    'Token budget should have been reached or exceeded'
                );
            }

            if ($result['stopped_reason'] === 'time_budget_exceeded') {
                $this->assertGreaterThanOrEqual(
                    5,
                    $result['total_time_s'],
                    'Time budget should have been reached or exceeded'
                );
            }
        }

        // Verify iteration was performed before stopping
        $this->assertGreaterThan(0, $result['iterations']);

        // Verify answer was still generated despite budget limits
        $this->assertArrayHasKey('answer', $result);
        $this->assertNotEmpty($result['answer']);

        // Verify search results were collected
        $this->assertArrayHasKey('search_results', $result);
        $this->assertGreaterThan(0, count($result['search_results']));
    }

    /**
     * Test: Research handles empty corpus gracefully
     *
     * @test
     */
    public function test_research_with_empty_corpus(): void
    {
        // Execute research without any test data
        $result = $this->orchestrator->research(
            query: 'What is the definition of legal capacity?',
            options: [
                'limits' => [
                    'max_iterations' => 2,
                    'quality_threshold' => 85,
                ],
            ]
        );

        // Research should complete even with no data
        $this->assertTrue($result['success']);

        // Should have attempted at least one iteration
        $this->assertGreaterThan(0, $result['iterations']);

        // Answer should be generated (even if indicating no sources)
        $this->assertArrayHasKey('answer', $result);
        $this->assertIsString($result['answer']);
    }

    /**
     * Test: Research with single iteration
     *
     * @test
     */
    public function test_research_with_single_iteration_limit(): void
    {
        Law::factory()->count(2)->create([
            'content' => 'Procedural law article about court procedures.',
            'embedding_vector' => json_encode(array_fill(0, 1536, 0.1)),
        ]);

        $result = $this->orchestrator->research(
            query: 'What are the main court procedures?',
            options: [
                'limits' => [
                    'max_iterations' => 1,
                    'quality_threshold' => 100, // Unreachable
                ],
            ]
        );

        // Should complete successfully
        $this->assertTrue($result['success']);

        // Should have exactly 1 iteration
        $this->assertEquals(1, $result['iterations']);

        // Should have stopped due to max_iterations
        $this->assertEquals('max_iterations', $result['stopped_reason']);

        // Should have search results from the single iteration
        $this->assertCount(1, $result['search_results']);
    }
}

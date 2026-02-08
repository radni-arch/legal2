<?php

namespace Tests\Feature\Services;

use App\Models\Law;
use App\Services\ResearchOrchestrator;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Research Orchestrator Integration Tests
 *
 * Tests the full research orchestration pipeline including:
 * - Multi-iteration research loops
 * - Quality threshold triggering iterations
 * - Search execution across corpora
 * - Resource budget tracking
 */
class ResearchOrchestratorIntegrationTest extends TestCase
{
    use UsesTestDatabase;

    /**
     * Test full research pipeline integration
     * Flow: Query → Generate questions → Search → Evaluate → Assess quality → Return
     */
    public function test_research_orchestrator_executes_full_pipeline(): void
    {
        // Create test laws with relevant content
        Law::factory()->create([
            'title' => 'Croatian Criminal Code - Criminal Liability',
            'content' => 'Criminal liability requires intent or negligence. A person is criminally liable if they commit an act defined as a criminal offense with the required mental state.',
            'embedding_vector' => array_fill(0, 1536, 0.1),
        ]);

        Law::factory()->create([
            'title' => 'Criminal Procedure Act - Rights of the Accused',
            'content' => 'The accused has the right to remain silent, right to legal counsel, and right to be informed of charges.',
            'embedding_vector' => array_fill(0, 1536, 0.15),
        ]);

        // Mock OpenAI for any embedding calls that might occur
        Http::fake([
            'api.openai.com/*' => Http::response([
                'data' => [
                    ['embedding' => array_fill(0, 1536, 0.1)],
                ],
                'usage' => ['total_tokens' => 10],
            ]),
        ]);

        $orchestrator = app(ResearchOrchestrator::class);

        $result = $orchestrator->research('What is criminal liability?', [
            'max_iterations' => 1, // Single iteration for test speed
        ]);

        // Verify result structure
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('answer', $result);
        $this->assertArrayHasKey('quality_score', $result);
        $this->assertArrayHasKey('iterations', $result);
        $this->assertArrayHasKey('stopped_reason', $result);
        $this->assertArrayHasKey('total_tokens', $result);
        $this->assertArrayHasKey('total_time_s', $result);
        $this->assertArrayHasKey('search_results', $result);

        // Verify basic results
        $this->assertTrue($result['success']);
        $this->assertIsString($result['answer']);
        $this->assertNotEmpty($result['answer']);
        $this->assertLessThanOrEqual(2, $result['iterations']); // May iterate once or twice depending on quality

        // Verify quality score is within valid range
        $this->assertGreaterThanOrEqual(0, $result['quality_score']);
        $this->assertLessThanOrEqual(100, $result['quality_score']);

        // Verify answer contains query reference
        $this->assertStringContainsString('criminal liability', strtolower($result['answer']));
    }

    /**
     * Test that quality threshold triggers iteration
     * Flow: Query → Search → Evaluate (low score) → Iterate → Search again → Evaluate (high score) → Stop
     */
    public function test_quality_threshold_triggers_iteration(): void
    {
        // Create test data
        Law::factory()->count(3)->create([
            'content' => 'Croatian law related to criminal proceedings and liability.',
            'embedding_vector' => array_fill(0, 1536, 0.12),
        ]);

        // Mock OpenAI for embeddings
        Http::fake([
            'api.openai.com/*' => Http::response([
                'data' => [
                    ['embedding' => array_fill(0, 1536, 0.1)],
                ],
                'usage' => ['total_tokens' => 10],
            ]),
        ]);

        $orchestrator = app(ResearchOrchestrator::class);

        // Set a high quality threshold to force multiple iterations
        $result = $orchestrator->research('What is criminal liability?', [
            'quality_threshold' => 85, // High threshold
            'max_iterations' => 3,
        ]);

        // Verify multiple iterations occurred
        $this->assertGreaterThanOrEqual(2, $result['iterations']);
        $this->assertLessThanOrEqual(3, $result['iterations']);

        // Verify result structure
        $this->assertTrue($result['success']);
        $this->assertIsString($result['answer']);
        $this->assertArrayHasKey('stopped_reason', $result);

        // Verify quality score improved through iterations
        $this->assertGreaterThanOrEqual(0, $result['quality_score']);
        $this->assertLessThanOrEqual(100, $result['quality_score']);

        // Verify search results exist for all iterations
        $this->assertIsArray($result['search_results']);
        $this->assertCount($result['iterations'], $result['search_results']);

        // Verify each iteration has results
        foreach ($result['search_results'] as $iterationData) {
            $this->assertArrayHasKey('iteration', $iterationData);
            $this->assertArrayHasKey('results', $iterationData);
            $this->assertIsArray($iterationData['results']);
        }
    }

    /**
     * Test max iterations limit is respected
     */
    public function test_max_iterations_limit_is_respected(): void
    {
        // Create minimal test data to keep quality low
        Law::factory()->create([
            'content' => 'Minimal law content',
            'embedding_vector' => array_fill(0, 1536, 0.1),
        ]);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'data' => [['embedding' => array_fill(0, 1536, 0.1)]],
                'usage' => ['total_tokens' => 10],
            ]),
        ]);

        $orchestrator = app(ResearchOrchestrator::class);

        // Set very high quality threshold and max iterations
        $result = $orchestrator->research('Test query', [
            'quality_threshold' => 99, // Nearly impossible to reach
            'max_iterations' => 2,
        ]);

        // Should stop at max iterations even if quality not met
        $this->assertEquals(2, $result['iterations']);
        $this->assertContains($result['stopped_reason'], ['max_iterations', 'quality_threshold_met']);
    }

    /**
     * Test quality threshold met stops iteration early
     */
    public function test_quality_threshold_met_stops_iteration(): void
    {
        // Create many test laws to increase result count (higher quality)
        Law::factory()->count(10)->create([
            'content' => 'Relevant Croatian legal content',
            'embedding_vector' => array_fill(0, 1536, 0.1),
        ]);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'data' => [['embedding' => array_fill(0, 1536, 0.1)]],
                'usage' => ['total_tokens' => 10],
            ]),
        ]);

        $orchestrator = app(ResearchOrchestrator::class);

        // Set a low quality threshold
        $result = $orchestrator->research('Test query', [
            'quality_threshold' => 50, // Easy to reach
            'max_iterations' => 5,
        ]);

        // Should stop before max iterations when quality is met
        $this->assertLessThan(5, $result['iterations']);
        $this->assertGreaterThanOrEqual(50, $result['quality_score']);
        $this->assertEquals('quality_threshold_met', $result['stopped_reason']);
    }

    /**
     * Test resource tracking across iterations
     */
    public function test_resource_tracking_across_iterations(): void
    {
        Law::factory()->count(3)->create([
            'embedding_vector' => array_fill(0, 1536, 0.1),
        ]);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'data' => [['embedding' => array_fill(0, 1536, 0.1)]],
                'usage' => ['total_tokens' => 10],
            ]),
        ]);

        $orchestrator = app(ResearchOrchestrator::class);

        $result = $orchestrator->research('Test query', [
            'max_iterations' => 2,
        ]);

        // Verify resource tracking
        $this->assertArrayHasKey('total_tokens', $result);
        $this->assertArrayHasKey('total_time_s', $result);

        $this->assertGreaterThan(0, $result['total_tokens']);
        $this->assertGreaterThan(0, $result['total_time_s']);

        // Verify time is reasonable (less than 10 seconds for test)
        $this->assertLessThan(10, $result['total_time_s']);
    }
}

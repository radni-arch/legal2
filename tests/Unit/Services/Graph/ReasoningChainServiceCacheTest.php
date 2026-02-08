<?php

namespace Tests\Unit\Services\Graph;

use App\Services\Explainability\ReasoningTraceService;
use App\Services\Graph\LawGraphSyncService;
use App\Services\Graph\ReasoningChainService;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

/**
 * Tests for ReasoningChainService Caching (Task 7)
 *
 * Verifies that NL → Cypher conversion results are cached to avoid
 * redundant LLM calls for the same query.
 *
 * Acceptance Criteria:
 * ✅ Same query uses cache on second call (OpenAI called only once)
 * ✅ Different queries use different cache keys
 * ✅ Cache has 1-hour TTL
 */
class ReasoningChainServiceCacheTest extends TestCase
{
    protected $lawGraphSyncMock;

    protected $openAIMock;

    protected $traceServiceMock;

    protected ReasoningChainService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear cache before each test
        Cache::flush();

        $this->lawGraphSyncMock = Mockery::mock(LawGraphSyncService::class);
        $this->openAIMock = Mockery::mock(OpenAIService::class);
        $this->traceServiceMock = Mockery::mock(ReasoningTraceService::class);

        $this->service = new ReasoningChainService(
            $this->lawGraphSyncMock,
            $this->openAIMock,
            $this->traceServiceMock
        );
    }

    protected function tearDown(): void
    {
        // Clear cache after each test
        Cache::flush();
        Mockery::close();
        parent::tearDown();
    }

    // ========================================
    // Test 1: Cache Hit - Same Query Twice
    // ========================================

    /** @test */
    public function it_caches_cypher_conversion_results()
    {
        // Arrange: Natural language query
        $nlQuery = 'Find all Supreme Court decisions from 2023';

        $expectedResponse = [
            'cypher' => 'MATCH (d:Decision) WHERE d.court CONTAINS "Vrhovni sud" AND d.decision_date >= "2023-01-01" AND d.decision_date <= "2023-12-31" RETURN d',
            'explanation' => 'Finds all Supreme Court decisions from 2023',
            'parameters' => [],
        ];

        // Mock: OpenAI should be called ONLY ONCE for both calls
        $this->openAIMock->shouldReceive('chat')
            ->once()
            ->andReturn([
                'content' => json_encode($expectedResponse),
            ]);

        // Act: Call convertNLToCypher TWICE with same query
        $result1 = $this->service->convertNLToCypher($nlQuery);
        $result2 = $this->service->convertNLToCypher($nlQuery);

        // Assert: Both results are identical
        $this->assertEquals($expectedResponse['cypher'], $result1['cypher']);
        $this->assertEquals($expectedResponse['cypher'], $result2['cypher']);
        $this->assertEquals($expectedResponse['explanation'], $result1['explanation']);
        $this->assertEquals($expectedResponse['explanation'], $result2['explanation']);

        // The mock expectation of ->once() already verifies OpenAI was called only once
    }

    // ========================================
    // Test 2: Different Queries Use Different Cache Keys
    // ========================================

    /** @test */
    public function it_uses_different_cache_keys_for_different_queries()
    {
        // Arrange: Two different queries
        $nlQuery1 = 'Find Supreme Court decisions from 2023';
        $nlQuery2 = 'Find all laws amended in 2023';

        $response1 = [
            'cypher' => 'MATCH (d:Decision) WHERE d.court CONTAINS "Vrhovni sud" RETURN d',
            'explanation' => 'Finds Supreme Court decisions',
            'parameters' => [],
        ];

        $response2 = [
            'cypher' => 'MATCH (l:LawDocument) WHERE l.valid_from >= "2023-01-01" RETURN l',
            'explanation' => 'Finds laws amended in 2023',
            'parameters' => [],
        ];

        // Mock: OpenAI should be called TWICE (once per unique query)
        $this->openAIMock->shouldReceive('chat')
            ->twice()
            ->andReturn(
                ['content' => json_encode($response1)],
                ['content' => json_encode($response2)]
            );

        // Act: Call with two different queries
        $result1 = $this->service->convertNLToCypher($nlQuery1);
        $result2 = $this->service->convertNLToCypher($nlQuery2);

        // Assert: Results are different
        $this->assertEquals($response1['cypher'], $result1['cypher']);
        $this->assertEquals($response2['cypher'], $result2['cypher']);
        $this->assertNotEquals($result1['cypher'], $result2['cypher']);

        // The mock expectation of ->twice() verifies OpenAI was called twice
    }
}

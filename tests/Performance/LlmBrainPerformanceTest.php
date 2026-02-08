<?php

namespace Tests\Performance;

use App\Services\Explainability\ReasoningTraceService;
use App\Services\Graph\LawGraphSyncService;
use App\Services\Graph\ReasoningChainService;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class LlmBrainPerformanceTest extends TestCase
{
    /** @test */
    public function it_completes_reasoning_chain_within_acceptable_time()
    {
        $lawGraphSyncMock = Mockery::mock(LawGraphSyncService::class);
        $openAIMock = Mockery::mock(OpenAIService::class);
        $traceServiceMock = Mockery::mock(ReasoningTraceService::class);

        $openAIMock->shouldReceive('chat')
            ->andReturn([
                'content' => json_encode([
                    'cypher' => 'MATCH (d:Decision) RETURN d LIMIT 100',
                    'explanation' => 'Test',
                    'parameters' => [],
                ]),
            ]);

        $lawGraphSyncMock->shouldReceive('query')
            ->andReturn(array_fill(0, 100, ['id' => 'test']));

        $traceServiceMock->shouldReceive('startTrace')->andReturn('trace-1');
        $traceServiceMock->shouldReceive('endTrace')->andReturn(true);
        $traceServiceMock->shouldReceive('recordStep')->andReturn(true);

        $service = new ReasoningChainService($lawGraphSyncMock, $openAIMock, $traceServiceMock);

        $start = microtime(true);
        $result = $service->executeReasoningChain('Test query');
        $duration = (microtime(true) - $start) * 1000;

        $this->assertTrue($result['success']);
        $this->assertLessThan(500, $duration, "Query took {$duration}ms, expected < 500ms");
    }

    /** @test */
    public function it_caches_repeated_queries_for_performance()
    {
        $lawGraphSyncMock = Mockery::mock(LawGraphSyncService::class);
        $openAIMock = Mockery::mock(OpenAIService::class);
        $traceServiceMock = Mockery::mock(ReasoningTraceService::class);

        // OpenAI should only be called ONCE due to caching
        $openAIMock->shouldReceive('chat')
            ->once()
            ->andReturn([
                'content' => json_encode([
                    'cypher' => 'MATCH (d:Decision) RETURN d',
                    'explanation' => 'Test',
                    'parameters' => [],
                ]),
            ]);

        $service = new ReasoningChainService($lawGraphSyncMock, $openAIMock, $traceServiceMock);

        // First call
        $start1 = microtime(true);
        $service->convertNLToCypher('Test query performance');
        $duration1 = (microtime(true) - $start1) * 1000;

        // Second call (should be cached)
        $start2 = microtime(true);
        $service->convertNLToCypher('Test query performance');
        $duration2 = (microtime(true) - $start2) * 1000;

        // Cached call should be much faster
        $this->assertLessThan(10, $duration2, 'Cached query should be < 10ms');
    }

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Cache::flush();
        Mockery::close();
        parent::tearDown();
    }
}

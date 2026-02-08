<?php

namespace Tests\Unit\Services\Graph;

use App\Services\Explainability\ReasoningTraceService;
use App\Services\Graph\LawGraphSyncService;
use App\Services\Graph\ReasoningChainService;
use App\Services\OpenAIService;
use Mockery;
use Tests\TestCase;

/**
 * Tests for ReasoningChainService LLM Model Configuration
 *
 * Ensures that the model used for NL-to-Cypher conversion is configurable
 * via the OPENAI_REASONING_MODEL environment variable.
 */
class ReasoningChainServiceConfigTest extends TestCase
{

    protected ReasoningChainService $service;
    protected OpenAIService $mockOpenAI;
    protected LawGraphSyncService $mockGraphSync;
    protected ReasoningTraceService $mockTraceService;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock dependencies
        $this->mockOpenAI = Mockery::mock(OpenAIService::class);
        $this->mockGraphSync = Mockery::mock(LawGraphSyncService::class);
        $this->mockTraceService = Mockery::mock(ReasoningTraceService::class);

        // Create service instance with mocked dependencies
        $this->service = new ReasoningChainService(
            $this->mockGraphSync,
            $this->mockOpenAI,
            $this->mockTraceService
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_uses_model_from_config()
    {
        // Set config to use a custom model
        config(['services.openai.reasoning_model' => 'gpt-4o-mini']);

        // Mock OpenAI service to expect the custom model
        $this->mockOpenAI->shouldReceive('chat')
            ->once()
            ->with(
                Mockery::type('array'), // messages
                'gpt-4o-mini', // model from config
                Mockery::type('array') // options
            )
            ->andReturn([
                'content' => json_encode([
                    'cypher' => 'MATCH (n) RETURN n LIMIT 10',
                    'explanation' => 'Returns 10 nodes',
                    'parameters' => [],
                ]),
            ]);

        // Execute the conversion
        $result = $this->service->convertNLToCypher('Find some nodes');

        // Verify result structure
        $this->assertIsArray($result);
        $this->assertArrayHasKey('cypher', $result);
        $this->assertArrayHasKey('explanation', $result);
        $this->assertArrayHasKey('parameters', $result);
    }

    /** @test */
    public function it_falls_back_to_default_model_when_not_configured()
    {
        // Don't set any custom config - rely on default from config file
        // This tests the scenario where env var OPENAI_REASONING_MODEL is not set
        // and the config file default of 'gpt-4o' is used

        // Mock OpenAI service to expect the default model 'gpt-4o'
        $this->mockOpenAI->shouldReceive('chat')
            ->once()
            ->with(
                Mockery::type('array'), // messages
                'gpt-4o', // default fallback model from config
                Mockery::type('array') // options
            )
            ->andReturn([
                'content' => json_encode([
                    'cypher' => 'MATCH (n) RETURN n LIMIT 10',
                    'explanation' => 'Returns 10 nodes',
                    'parameters' => [],
                ]),
            ]);

        // Execute the conversion
        $result = $this->service->convertNLToCypher('Find some nodes');

        // Verify result structure
        $this->assertIsArray($result);
        $this->assertArrayHasKey('cypher', $result);
        $this->assertArrayHasKey('explanation', $result);
        $this->assertArrayHasKey('parameters', $result);
    }
}

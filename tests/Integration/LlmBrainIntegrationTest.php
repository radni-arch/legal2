<?php

namespace Tests\Integration;

use App\Http\Livewire\LlmBrainPanel;
use App\Services\Graph\ReasoningChainService;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class LlmBrainIntegrationTest extends TestCase
{
    /** @test */
    public function it_executes_full_query_flow_from_ui_to_graph()
    {
        // Mock ReasoningChainService to return successful query results
        $reasoningMock = Mockery::mock(ReasoningChainService::class);
        $reasoningMock->shouldReceive('executeReasoningChain')
            ->once()
            ->with(Mockery::type('string'))
            ->andReturn([
                'success' => true,
                'results' => [
                    ['d' => ['id' => 'dec-1', 'case_number' => 'K-100/2020'], 'l' => ['law_number' => 'NN 152/08']],
                    ['d' => ['id' => 'dec-2', 'case_number' => 'K-200/2021'], 'l' => ['law_number' => 'NN 152/08']],
                ],
                'cypher_query' => 'MATCH (d:Decision)-[:CITES]->(l:LawDocument) WHERE l.law_number = "NN 152/08" RETURN d, l',
                'explanation' => 'Finds decisions citing law NN 152/08',
                'parameters' => [],
                'trace_id' => 'trace-123',
                'result_count' => 2,
                'duration_ms' => 150,
            ]);
        $this->app->instance(ReasoningChainService::class, $reasoningMock);

        // Execute via Livewire
        Livewire::test(LlmBrainPanel::class)
            ->set('naturalQuery', 'Find decisions citing law NN 152/08')
            ->call('executeQuery')
            ->assertSet('error', null)
            ->assertSet('queryResult', [
                ['d' => ['id' => 'dec-1', 'case_number' => 'K-100/2020'], 'l' => ['law_number' => 'NN 152/08']],
                ['d' => ['id' => 'dec-2', 'case_number' => 'K-200/2021'], 'l' => ['law_number' => 'NN 152/08']],
            ])
            ->assertSet('generatedCypher', 'MATCH (d:Decision)-[:CITES]->(l:LawDocument) WHERE l.law_number = "NN 152/08" RETURN d, l')
            ->assertSet('explanation', 'Finds decisions citing law NN 152/08');
    }

    /** @test */
    public function it_handles_neo4j_connection_failure_gracefully()
    {
        // Mock ReasoningChainService to return error (simulating Neo4j connection failure)
        $reasoningMock = Mockery::mock(ReasoningChainService::class);
        $reasoningMock->shouldReceive('executeReasoningChain')
            ->once()
            ->with(Mockery::type('string'))
            ->andReturn([
                'success' => false,
                'error' => 'Neo4j connection refused',
                'trace_id' => 'trace-456',
                'duration_ms' => 50,
            ]);
        $this->app->instance(ReasoningChainService::class, $reasoningMock);

        $component = Livewire::test(LlmBrainPanel::class)
            ->set('naturalQuery', 'Test query')
            ->call('executeQuery')
            ->assertSet('queryResult', null);

        // Verify error is set and is a non-empty string (sanitized message)
        $this->assertNotNull($component->get('error'));
        $this->assertIsString($component->get('error'));
        $this->assertNotEmpty($component->get('error'));
    }

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\RateLimiter::clear('llm-brain:' . request()->ip());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}

<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\LlmBrainPanel;
use App\Services\Graph\ReasoningChainService;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class LlmBrainPanelReasoningModeTest extends TestCase
{
    protected $reasoningChainService;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock HTTP calls to OpenAI
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'cypher' => 'MATCH (d:Decision) RETURN d LIMIT 10',
                                'explanation' => 'This query returns 10 court decisions',
                                'parameters' => [],
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        // Mock the ReasoningChainService
        $this->reasoningChainService = Mockery::mock(ReasoningChainService::class);
        $this->app->instance(ReasoningChainService::class, $this->reasoningChainService);
    }

    /**
     * Test: Reasoning mode renders interface (not "Coming Soon")
     *
     * @test
     */
    public function test_it_renders_reasoning_interface()
    {
        $component = Livewire::test(LlmBrainPanel::class)
            ->set('mode', 'reasoning');

        // Assert the view is rendered
        $html = $component->get('mode');
        $this->assertEquals('reasoning', $html);

        // Assert "Coming Soon" is NOT present in the rendered output
        $component->assertDontSee('Reasoning Chains - Coming Soon');

        // Assert reasoning interface elements ARE present
        $component->assertSee('Your Question'); // Label in reasoning interface
        $component->assertSee('Execute Reasoning Chain'); // Button text
    }

    /**
     * Test: Executes reasoning chain and displays trace_id and duration_ms
     *
     * @test
     */
    public function test_it_executes_reasoning_chain_with_trace()
    {
        $mockResult = [
            'success' => true,
            'results' => [
                ['case_number' => 'K-123/2024', 'court' => 'Županijski sud u Osijeku'],
            ],
            'cypher_query' => 'MATCH (d:Decision) RETURN d LIMIT 10',
            'explanation' => 'Multi-hop reasoning: Step 1 -> Step 2 -> Step 3',
            'trace_id' => 'trace-abc123-xyz',
            'duration_ms' => 1234,
        ];

        $this->reasoningChainService
            ->shouldReceive('executeReasoningChain')
            ->once()
            ->with('Find contradicting decisions')
            ->andReturn($mockResult);

        $component = Livewire::test(LlmBrainPanel::class)
            ->set('mode', 'reasoning')
            ->set('naturalQuery', 'Find contradicting decisions')
            ->call('executeReasoningQuery')
            ->assertSet('error', null)
            ->assertSet('loading', false)
            ->assertSet('queryResult', $mockResult['results'])
            ->assertSet('generatedCypher', 'MATCH (d:Decision) RETURN d LIMIT 10')
            ->assertSet('explanation', 'Multi-hop reasoning: Step 1 -> Step 2 -> Step 3')
            ->assertSet('traceId', 'trace-abc123-xyz')
            ->assertSet('durationMs', 1234);

        // Assert trace_id and duration_ms are displayed in the view
        $component->assertSee('trace-abc123-xyz');
        $component->assertSee('1234');
    }

    /**
     * Test: Reasoning query validates empty input
     *
     * @test
     */
    public function test_reasoning_query_validates_empty_input()
    {
        Livewire::test(LlmBrainPanel::class)
            ->set('mode', 'reasoning')
            ->set('naturalQuery', '')
            ->call('executeReasoningQuery')
            ->assertSet('error', 'Please enter a query');
    }

    /**
     * Test: Reasoning query handles errors gracefully
     *
     * @test
     */
    public function test_reasoning_query_handles_errors()
    {
        $this->reasoningChainService
            ->shouldReceive('executeReasoningChain')
            ->once()
            ->andReturn([
                'success' => false,
                'error' => 'Reasoning chain execution failed',
            ]);

        Livewire::test(LlmBrainPanel::class)
            ->set('mode', 'reasoning')
            ->set('naturalQuery', 'Invalid reasoning query')
            ->call('executeReasoningQuery')
            ->assertSet('error', 'Reasoning chain execution failed')
            ->assertSet('queryResult', null)
            ->assertSet('traceId', null)
            ->assertSet('durationMs', null);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}

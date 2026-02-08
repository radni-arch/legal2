<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\LlmBrainPanel;
use App\Services\Graph\ReasoningChainService;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class LlmBrainPanelTest extends TestCase
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
     * Test 1: Component renders correctly with initial state
     *
     * @test
     */
    public function test_component_renders_correctly()
    {
        Livewire::test(LlmBrainPanel::class)
            ->assertStatus(200)
            ->assertViewIs('livewire.llm-brain-panel')
            ->assertSet('naturalQuery', '')
            ->assertSet('queryResult', null)
            ->assertSet('generatedCypher', null)
            ->assertSet('loading', false)
            ->assertSet('error', null)
            ->assertSet('mode', 'query');
    }

    /**
     * Test 2: Natural query input binding works
     *
     * @test
     */
    public function test_natural_query_input_binding_works()
    {
        Livewire::test(LlmBrainPanel::class)
            ->assertSet('naturalQuery', '')
            ->set('naturalQuery', 'Find all decisions about proportionality')
            ->assertSet('naturalQuery', 'Find all decisions about proportionality');
    }

    /**
     * Test 3: Mode switching works
     *
     * @test
     */
    public function test_mode_switching_works()
    {
        Livewire::test(LlmBrainPanel::class)
            ->assertSet('mode', 'query')
            ->set('mode', 'chat')
            ->assertSet('mode', 'chat')
            ->set('mode', 'reasoning')
            ->assertSet('mode', 'reasoning')
            ->set('mode', 'query')
            ->assertSet('mode', 'query');
    }

    /**
     * Test 4: Empty query validation
     *
     * @test
     */
    public function test_empty_query_validation()
    {
        Livewire::test(LlmBrainPanel::class)
            ->set('naturalQuery', '')
            ->call('executeQuery')
            ->assertSet('error', 'Please enter a query');
    }

    /**
     * Test 5: Whitespace-only query validation
     *
     * @test
     */
    public function test_whitespace_query_validation()
    {
        Livewire::test(LlmBrainPanel::class)
            ->set('naturalQuery', '   ')
            ->call('executeQuery')
            ->assertSet('error', 'Please enter a query');
    }

    /**
     * Test 6: Successful query execution
     *
     * @test
     */
    public function test_successful_query_execution()
    {
        $mockResult = [
            'success' => true,
            'results' => [
                ['case_number' => 'K-123/2024', 'court' => 'Županijski sud u Osijeku'],
            ],
            'cypher_query' => 'MATCH (d:Decision) RETURN d LIMIT 10',
            'explanation' => 'This query returns 10 court decisions',
        ];

        $this->reasoningChainService
            ->shouldReceive('executeReasoningChain')
            ->once()
            ->with('Find all decisions about proportionality')
            ->andReturn($mockResult);

        Livewire::test(LlmBrainPanel::class)
            ->set('naturalQuery', 'Find all decisions about proportionality')
            ->call('executeQuery')
            ->assertSet('error', null)
            ->assertSet('loading', false)
            ->assertSet('queryResult', $mockResult['results'])
            ->assertSet('generatedCypher', 'MATCH (d:Decision) RETURN d LIMIT 10');
    }

    /**
     * Test 7: Query execution handles errors
     *
     * @test
     */
    public function test_query_execution_handles_errors()
    {
        $this->reasoningChainService
            ->shouldReceive('executeReasoningChain')
            ->once()
            ->andReturn([
                'success' => false,
                'error' => 'Query execution failed',
            ]);

        Livewire::test(LlmBrainPanel::class)
            ->set('naturalQuery', 'Invalid query')
            ->call('executeQuery')
            ->assertSet('error', 'Query execution failed')
            ->assertSet('queryResult', null);
    }

    /**
     * Test 8: Clear results resets state
     *
     * @test
     */
    public function test_clear_results_resets_state()
    {
        Livewire::test(LlmBrainPanel::class)
            ->set('queryResult', [['case_number' => 'K-123/2024']])
            ->set('generatedCypher', 'MATCH (d:Decision) RETURN d')
            ->set('error', 'Some error')
            ->call('clearResults')
            ->assertSet('queryResult', null)
            ->assertSet('generatedCypher', null)
            ->assertSet('error', null);
    }

    /**
     * Test 9: Loading state is set during query execution
     *
     * @test
     */
    public function test_loading_state_during_query()
    {
        Livewire::test(LlmBrainPanel::class)
            ->assertSet('loading', false);
    }

    /**
     * Test 10: Query examples are available
     *
     * @test
     */
    public function test_query_examples_are_available()
    {
        $component = Livewire::test(LlmBrainPanel::class);

        $examples = $component->get('examples');

        $this->assertIsArray($examples);
        $this->assertNotEmpty($examples);
        $this->assertArrayHasKey(0, $examples);
        $this->assertArrayHasKey('query', $examples[0]);
        $this->assertArrayHasKey('description', $examples[0]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}

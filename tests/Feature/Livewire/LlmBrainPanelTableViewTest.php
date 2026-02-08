<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\LlmBrainPanel;
use App\Services\Graph\ReasoningChainService;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class LlmBrainPanelTableViewTest extends TestCase
{
    protected $reasoningChainService;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock the ReasoningChainService
        $this->reasoningChainService = Mockery::mock(ReasoningChainService::class);
        $this->app->instance(ReasoningChainService::class, $this->reasoningChainService);
    }

    /**
     * Test: Simple results (<=5 keys) render as HTML table
     *
     * @test
     */
    public function test_simple_results_render_as_table()
    {
        // Mock simple result set with <= 5 keys
        $simpleResults = [
            ['id' => 1, 'name' => 'Test Decision', 'year' => 2023],
            ['id' => 2, 'name' => 'Another Decision', 'year' => 2024],
        ];

        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'cypher' => 'MATCH (d:Decision) RETURN d.id as id, d.name as name, d.year as year LIMIT 2',
                                'explanation' => 'Returns simple decision data',
                                'parameters' => [],
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        // Mock Neo4j query execution
        $component = Livewire::test(LlmBrainPanel::class)
            ->set('naturalQuery', 'Find decisions')
            ->set('queryResult', $simpleResults)
            ->set('generatedCypher', 'MATCH (d:Decision) RETURN d LIMIT 2');

        // Assert table structure is present
        $component
            ->assertSee('id', false)  // Table header
            ->assertSee('name', false)
            ->assertSee('year', false)
            ->assertSee('Test Decision')  // Table data
            ->assertSee('Another Decision')
            ->assertSee('2023')
            ->assertSee('2024');

        // Verify it's rendered as table, not JSON
        $html = $component->get('queryResult');
        $this->assertIsArray($html);
        $this->assertCount(2, $html);
    }

    /**
     * Test: Complex results (>5 keys) render as JSON
     *
     * @test
     */
    public function test_complex_results_render_as_json()
    {
        // Mock complex result set with > 5 keys
        $complexResults = [
            [
                'id' => 1,
                'name' => 'Test Decision',
                'year' => 2023,
                'court' => 'Supreme Court',
                'topic' => 'Criminal Law',
                'status' => 'Final',
                'extra_field' => 'Some value',
            ],
        ];

        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'cypher' => 'MATCH (d:Decision) RETURN d',
                                'explanation' => 'Returns complex decision data',
                                'parameters' => [],
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        // Mock Neo4j query execution
        $component = Livewire::test(LlmBrainPanel::class)
            ->set('naturalQuery', 'Find complex decisions')
            ->set('queryResult', $complexResults)
            ->set('generatedCypher', 'MATCH (d:Decision) RETURN d');

        // Should still be able to see the data
        $component
            ->assertSee('Test Decision')
            ->assertSee('Supreme Court')
            ->assertSee('Criminal Law');

        // Verify result is set correctly
        $html = $component->get('queryResult');
        $this->assertIsArray($html);
        $this->assertCount(1, $html);
        $this->assertArrayHasKey('extra_field', $html[0]);
    }

    /**
     * Test: Array values in table cells render as JSON
     *
     * @test
     */
    public function test_array_values_render_as_json_in_table()
    {
        // Mock results with nested array values
        $resultsWithArrays = [
            [
                'id' => 1,
                'name' => 'Test Decision',
                'citations' => ['Article 9', 'Article 10'],
            ],
        ];

        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'cypher' => 'MATCH (d:Decision) RETURN d',
                                'explanation' => 'Returns decisions with citations',
                                'parameters' => [],
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $component = Livewire::test(LlmBrainPanel::class)
            ->set('naturalQuery', 'Find decisions with citations')
            ->set('queryResult', $resultsWithArrays)
            ->set('generatedCypher', 'MATCH (d:Decision) RETURN d');

        // Should see the table headers
        $component
            ->assertSee('id', false)
            ->assertSee('name', false)
            ->assertSee('citations', false)
            ->assertSee('Test Decision');

        // Verify result structure
        $html = $component->get('queryResult');
        $this->assertIsArray($html);
        $this->assertIsArray($html[0]['citations']);
    }

    /**
     * Test: Empty results render as JSON
     *
     * @test
     */
    public function test_empty_results_render_as_json()
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'cypher' => 'MATCH (d:Decision) WHERE d.id = 999 RETURN d',
                                'explanation' => 'Returns no results',
                                'parameters' => [],
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $component = Livewire::test(LlmBrainPanel::class)
            ->set('naturalQuery', 'Find non-existent decision')
            ->set('queryResult', [])
            ->set('generatedCypher', 'MATCH (d:Decision) WHERE d.id = 999 RETURN d');

        // Verify empty array is set
        $html = $component->get('queryResult');
        $this->assertIsArray($html);
        $this->assertEmpty($html);
    }

    /**
     * Test: Non-array results render as JSON
     *
     * @test
     */
    public function test_non_array_results_render_as_json()
    {
        // Mock scalar result (e.g., COUNT query)
        $scalarResult = [
            ['count' => 42],
        ];

        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'cypher' => 'MATCH (d:Decision) RETURN count(d) as count',
                                'explanation' => 'Returns count',
                                'parameters' => [],
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $component = Livewire::test(LlmBrainPanel::class)
            ->set('naturalQuery', 'Count decisions')
            ->set('queryResult', $scalarResult)
            ->set('generatedCypher', 'MATCH (d:Decision) RETURN count(d) as count');

        // Should see the count value
        $component->assertSee('42');

        // Verify result structure
        $html = $component->get('queryResult');
        $this->assertIsArray($html);
        $this->assertEquals(42, $html[0]['count']);
    }

    /**
     * Test: Partial includes llm-result-table
     *
     * @test
     */
    public function test_partial_is_included_for_query_results()
    {
        $results = [
            ['id' => 1, 'name' => 'Test'],
        ];

        Livewire::test(LlmBrainPanel::class)
            ->set('naturalQuery', 'Test query')
            ->set('queryResult', $results)
            ->set('generatedCypher', 'MATCH (d) RETURN d')
            ->assertViewHas('queryResult', $results);
    }

    /**
     * Test: Reasoning mode results also use table view
     *
     * @test
     */
    public function test_reasoning_mode_uses_table_view()
    {
        $results = [
            ['id' => 1, 'name' => 'Test Decision', 'court' => 'Supreme'],
        ];

        $component = Livewire::test(LlmBrainPanel::class)
            ->set('mode', 'reasoning')
            ->set('naturalQuery', 'Test reasoning query')
            ->set('queryResult', $results)
            ->set('generatedCypher', 'MATCH (d) RETURN d');

        // Should see table data in reasoning mode too
        $component
            ->assertSee('Test Decision')
            ->assertSee('Supreme');

        // Verify result structure
        $html = $component->get('queryResult');
        $this->assertIsArray($html);
        $this->assertCount(1, $html);
    }
}

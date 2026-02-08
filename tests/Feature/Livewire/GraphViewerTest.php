<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\GraphViewer;
use App\Repositories\GraphMetricsRepository;
use App\Services\GraphDatabaseService;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class GraphViewerTest extends TestCase
{
    protected $graphService;

    protected $metricsRepository;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock the GraphDatabaseService
        $this->graphService = Mockery::mock(GraphDatabaseService::class);
        $this->app->instance(GraphDatabaseService::class, $this->graphService);

        // Mock the GraphMetricsRepository
        $this->metricsRepository = Mockery::mock(GraphMetricsRepository::class);
        $this->app->instance(GraphMetricsRepository::class, $this->metricsRepository);
    }

    /**
     * Setup default mocks for component mount
     */
    protected function setupDefaultMocks(): void
    {
        // Mock statistics query (node counts)
        $statsRecord = Mockery::mock();
        $statsRecord->shouldReceive('get')->with('label')->andReturn('CourtDecisionDocument');
        $statsRecord->shouldReceive('get')->with('count')->andReturn(100);

        $this->graphService->shouldReceive('run')
            ->with(Mockery::on(function ($query) {
                return str_contains($query, 'labels(n)');
            }))
            ->andReturn(collect([$statsRecord]));

        // Mock relationship count query
        $relCountRecord = Mockery::mock();
        $relCountRecord->shouldReceive('get')->with('count')->andReturn(250);

        $relCountResult = Mockery::mock();
        $relCountResult->shouldReceive('first')->andReturn($relCountRecord);

        $this->graphService->shouldReceive('run')
            ->with(Mockery::on(function ($query) {
                return str_contains($query, 'MATCH ()-[r]->()');
            }))
            ->andReturn($relCountResult);

        // Mock recent nodes query
        $this->graphService->shouldReceive('run')
            ->with(Mockery::on(function ($query) {
                return str_contains($query, 'created_at IS NOT NULL');
            }))
            ->andReturn(collect([]));

        // Mock metrics repository
        $this->metricsRepository->shouldReceive('getInfluentialDecisions')->andReturn([]);
        $this->metricsRepository->shouldReceive('getCitationClusters')->andReturn([]);
        $this->metricsRepository->shouldReceive('getNetworkStats')->andReturn(null);
    }

    /**
     * Test 1: Component renders correctly with initial state
     *
     * @test
     */
    public function test_component_renders_correctly()
    {
        $this->setupDefaultMocks();

        Livewire::test(GraphViewer::class)
            ->assertStatus(200)
            ->assertViewIs('livewire.graph-viewer')
            ->assertSet('searchTerm', '')
            ->assertSet('selectedNodeType', 'CourtDecisionDocument')
            ->assertSet('selectedNodeId', '')
            ->assertSet('selectedNode', null)
            ->assertSet('depth', 2)
            ->assertSet('relationshipType', '')
            ->assertSet('limit', 50)
            ->assertSet('includeProperties', true)
            ->assertSet('loading', false)
            ->assertSet('error', null)
            ->assertSet('viewMode', 'graph')
            ->assertSet('showMetrics', true);
    }

    /**
     * Test 2: Search term input binding works
     *
     * @test
     */
    public function test_search_term_input_binding_works()
    {
        $this->setupDefaultMocks();

        Livewire::test(GraphViewer::class)
            ->assertSet('searchTerm', '')
            ->set('searchTerm', 'test search')
            ->assertSet('searchTerm', 'test search');
    }

    /**
     * Test 3: Node type selection works
     *
     * @test
     */
    public function test_node_type_selection_works()
    {
        $this->setupDefaultMocks();

        Livewire::test(GraphViewer::class)
            ->assertSet('selectedNodeType', 'CourtDecisionDocument')
            ->set('selectedNodeType', 'LawDocument')
            ->assertSet('selectedNodeType', 'LawDocument')
            ->set('selectedNodeType', 'Court')
            ->assertSet('selectedNodeType', 'Court');
    }

    /**
     * Test 4: Graph configuration properties work
     *
     * @test
     */
    public function test_graph_configuration_properties_work()
    {
        $this->setupDefaultMocks();

        Livewire::test(GraphViewer::class)
            ->set('depth', 3)
            ->assertSet('depth', 3)
            ->set('relationshipType', 'CITES')
            ->assertSet('relationshipType', 'CITES')
            ->set('limit', 100)
            ->assertSet('limit', 100)
            ->set('includeProperties', false)
            ->assertSet('includeProperties', false);
    }

    /**
     * Test 5: View mode toggle works
     *
     * @test
     */
    public function test_view_mode_toggle_works()
    {
        $this->setupDefaultMocks();

        Livewire::test(GraphViewer::class)
            ->assertSet('viewMode', 'graph')
            ->set('viewMode', 'table')
            ->assertSet('viewMode', 'table')
            ->set('viewMode', 'json')
            ->assertSet('viewMode', 'json')
            ->set('viewMode', 'graph')
            ->assertSet('viewMode', 'graph');
    }

    /**
     * Test 6: Search validation - empty search term shows error
     *
     * @test
     */
    public function test_search_validation_empty_term_shows_error()
    {
        $this->setupDefaultMocks();

        Livewire::test(GraphViewer::class)
            ->set('searchTerm', '')
            ->call('searchNodes')
            ->assertSet('error', 'Please enter a search term');
    }

    /**
     * Test 7: Search with no results shows appropriate message
     *
     * @test
     */
    public function test_search_with_no_results_shows_message()
    {
        $this->setupDefaultMocks();

        // Mock search query
        $this->graphService->shouldReceive('run')
            ->with(Mockery::type('string'), ['term' => 'nonexistent'])
            ->once()
            ->andReturn(collect([]));

        Livewire::test(GraphViewer::class)
            ->set('searchTerm', 'nonexistent')
            ->call('searchNodes')
            ->assertSet('error', 'No nodes found matching your search')
            ->assertSet('graphData', null);
    }

    /**
     * Test 8: Metrics panel toggle works
     *
     * @test
     */
    public function test_metrics_panel_toggle_works()
    {
        $this->setupDefaultMocks();

        Livewire::test(GraphViewer::class)
            ->assertSet('showMetrics', true)
            ->set('showMetrics', false)
            ->assertSet('showMetrics', false)
            ->set('showMetrics', true)
            ->assertSet('showMetrics', true);
    }

    /**
     * Test 9: Loading state management
     *
     * @test
     */
    public function test_loading_state_initially_false()
    {
        $this->setupDefaultMocks();

        Livewire::test(GraphViewer::class)
            ->assertSet('loading', false);
    }

    /**
     * Test 10: Node types property contains expected types
     *
     * @test
     */
    public function test_node_types_property_contains_expected_types()
    {
        $this->setupDefaultMocks();

        $component = Livewire::test(GraphViewer::class);

        $nodeTypes = $component->get('nodeTypes');

        $this->assertIsArray($nodeTypes);
        $this->assertArrayHasKey('CourtDecisionDocument', $nodeTypes);
        $this->assertArrayHasKey('LawDocument', $nodeTypes);
        $this->assertArrayHasKey('Court', $nodeTypes);
        $this->assertArrayHasKey('Keyword', $nodeTypes);
    }

    /**
     * Test 11: Relationship types property contains expected types
     *
     * @test
     */
    public function test_relationship_types_property_contains_expected_types()
    {
        $this->setupDefaultMocks();

        $component = Livewire::test(GraphViewer::class);

        $relationshipTypes = $component->get('relationshipTypes');

        $this->assertIsArray($relationshipTypes);
        $this->assertArrayHasKey('CITES', $relationshipTypes);
        $this->assertArrayHasKey('REFERENCES', $relationshipTypes);
        $this->assertArrayHasKey('OVERRULES', $relationshipTypes);
        $this->assertArrayHasKey('SIMILAR_TO', $relationshipTypes);
    }

    /**
     * Test 12: Component loads statistics on mount
     *
     * @test
     */
    public function test_component_loads_statistics_on_mount()
    {
        $this->setupDefaultMocks();

        $component = Livewire::test(GraphViewer::class);

        $statistics = $component->get('statistics');
        $this->assertNotNull($statistics);
        $this->assertIsArray($statistics);
        $this->assertArrayHasKey('nodes', $statistics);
        $this->assertArrayHasKey('totalNodes', $statistics);
        $this->assertArrayHasKey('totalRelationships', $statistics);
        $this->assertEquals(250, $statistics['totalRelationships']);
    }

    /**
     * Test 13: Component loads recent nodes on mount
     *
     * @test
     */
    public function test_component_loads_recent_nodes_on_mount()
    {
        $this->setupDefaultMocks();

        $component = Livewire::test(GraphViewer::class);

        $recentNodes = $component->get('recentNodes');
        $this->assertIsArray($recentNodes);
    }

    /**
     * Test 14: Component loads graph metrics on mount
     *
     * @test
     */
    public function test_component_loads_graph_metrics_on_mount()
    {
        $this->setupDefaultMocks();

        $component = Livewire::test(GraphViewer::class);

        $this->assertTrue($component->get('metricsLoaded'));
    }

    /**
     * Test 15: Search filters by node type correctly
     *
     * @test
     */
    public function test_search_filters_by_node_type_correctly()
    {
        $this->setupDefaultMocks();

        // Mock search for CourtDecisionDocument
        $mockNode = Mockery::mock();
        $mockNode->shouldReceive('getProperties')->andReturn(
            new \ArrayIterator([
                'id' => 'decision-123',
                'case_number' => 'K-123/2024',
            ])
        );

        $mockRecord = Mockery::mock();
        $mockRecord->shouldReceive('get')->with('n')->andReturn($mockNode);

        $searchResult = collect([$mockRecord]);

        // Mock graph data fetch
        $centerNode = Mockery::mock();
        $centerNode->shouldReceive('getProperty')->with('id')->andReturn('decision-123');
        $centerNode->shouldReceive('getLabels')->andReturn(['CourtDecisionDocument']);
        $centerNode->shouldReceive('getProperties')->andReturn(
            new \ArrayIterator([
                'id' => 'decision-123',
                'case_number' => 'K-123/2024',
            ])
        );

        $graphRecord = Mockery::mock();
        $graphRecord->shouldReceive('get')->with('center')->andReturn($centerNode);
        $graphRecord->shouldReceive('get')->with('connectedNodes')->andReturn([]);
        $graphRecord->shouldReceive('get')->with('outgoing')->andReturn([]);
        $graphRecord->shouldReceive('get')->with('incoming')->andReturn([]);

        $graphResult = collect([$graphRecord]);

        $this->graphService->shouldReceive('run')
            ->with(Mockery::on(function ($query) {
                return str_contains($query, 'CourtDecisionDocument');
            }), ['term' => 'K-123'])
            ->once()
            ->andReturn($searchResult);

        $this->graphService->shouldReceive('run')
            ->with(Mockery::on(function ($query) {
                return str_contains($query, 'MATCH (center:CourtDecisionDocument');
            }), ['id' => 'decision-123'])
            ->once()
            ->andReturn($graphResult);

        Livewire::test(GraphViewer::class)
            ->set('selectedNodeType', 'CourtDecisionDocument')
            ->set('searchTerm', 'K-123')
            ->call('searchNodes')
            ->assertSet('selectedNodeId', 'decision-123');
    }

    /**
     * Test 16: Search handles whitespace in search term
     *
     * @test
     */
    public function test_search_handles_whitespace_in_search_term()
    {
        $this->setupDefaultMocks();

        Livewire::test(GraphViewer::class)
            ->set('searchTerm', '   ')
            ->call('searchNodes')
            ->assertSet('error', 'Please enter a search term');
    }

    /**
     * Test 17: Search successfully finds and loads node
     *
     * @test
     */
    public function test_search_successfully_finds_and_loads_node()
    {
        $this->setupDefaultMocks();

        // Mock successful search
        $mockNode = Mockery::mock();
        $mockNode->shouldReceive('getProperties')->andReturn(
            new \ArrayIterator([
                'id' => 'law-doc-1',
                'law_number' => 'NN 145/2024',
                'title' => 'Zakon o kaznenom postupku',
            ])
        );

        $mockRecord = Mockery::mock();
        $mockRecord->shouldReceive('get')->with('n')->andReturn($mockNode);

        $searchResult = collect([$mockRecord]);

        // Mock graph data
        $centerNode = Mockery::mock();
        $centerNode->shouldReceive('getProperty')->with('id')->andReturn('law-doc-1');
        $centerNode->shouldReceive('getLabels')->andReturn(['LawDocument']);
        $centerNode->shouldReceive('getProperties')->andReturn(
            new \ArrayIterator([
                'id' => 'law-doc-1',
                'law_number' => 'NN 145/2024',
                'title' => 'Zakon o kaznenom postupku',
            ])
        );

        $graphRecord = Mockery::mock();
        $graphRecord->shouldReceive('get')->with('center')->andReturn($centerNode);
        $graphRecord->shouldReceive('get')->with('connectedNodes')->andReturn([]);
        $graphRecord->shouldReceive('get')->with('outgoing')->andReturn([]);
        $graphRecord->shouldReceive('get')->with('incoming')->andReturn([]);

        $graphResult = collect([$graphRecord]);

        $this->graphService->shouldReceive('run')
            ->with(Mockery::type('string'), ['term' => 'ZKP'])
            ->once()
            ->andReturn($searchResult);

        $this->graphService->shouldReceive('run')
            ->with(Mockery::type('string'), ['id' => 'law-doc-1'])
            ->once()
            ->andReturn($graphResult);

        Livewire::test(GraphViewer::class)
            ->set('selectedNodeType', 'LawDocument')
            ->set('searchTerm', 'ZKP')
            ->call('searchNodes')
            ->assertSet('selectedNodeId', 'law-doc-1')
            ->assertSet('error', null)
            ->assertSet('loading', false);
    }

    /**
     * Test 18: Reset graph clears all state
     *
     * @test
     */
    public function test_reset_graph_clears_all_state()
    {
        $this->setupDefaultMocks();

        Livewire::test(GraphViewer::class)
            ->set('selectedNodeId', 'test-node-123')
            ->set('selectedNode', ['id' => 'test-node-123'])
            ->set('graphData', [
                'nodes' => [],
                'edges' => [],
                'nodeCount' => 0,
                'edgeCount' => 0,
            ])
            ->set('error', 'Some error')
            ->set('searchTerm', 'test search')
            ->call('resetGraph')
            ->assertSet('selectedNodeId', '')
            ->assertSet('selectedNode', null)
            ->assertSet('graphData', null)
            ->assertSet('error', null)
            ->assertSet('searchTerm', '');
    }

    /**
     * Test 19: Refresh statistics reloads all data
     *
     * @test
     */
    public function test_refresh_statistics_reloads_all_data()
    {
        $this->setupDefaultMocks();

        Livewire::test(GraphViewer::class)
            ->call('refreshStatistics');

        // Just verify the method can be called without errors
        $this->assertTrue(true);
    }

    /**
     * Test 20: Select recent node loads its graph
     *
     * @test
     */
    public function test_select_recent_node_loads_its_graph()
    {
        $this->setupDefaultMocks();

        // Mock graph data fetch
        $centerNode = Mockery::mock();
        $centerNode->shouldReceive('getProperty')->with('id')->andReturn('recent-node-1');
        $centerNode->shouldReceive('getLabels')->andReturn(['Court']);
        $centerNode->shouldReceive('getProperties')->andReturn(
            new \ArrayIterator([
                'id' => 'recent-node-1',
                'name' => 'Županijski sud u Osijeku',
            ])
        );

        $graphRecord = Mockery::mock();
        $graphRecord->shouldReceive('get')->with('center')->andReturn($centerNode);
        $graphRecord->shouldReceive('get')->with('connectedNodes')->andReturn([]);
        $graphRecord->shouldReceive('get')->with('outgoing')->andReturn([]);
        $graphRecord->shouldReceive('get')->with('incoming')->andReturn([]);

        $graphResult = collect([$graphRecord]);

        $this->graphService->shouldReceive('run')
            ->with(Mockery::type('string'), ['id' => 'recent-node-1'])
            ->once()
            ->andReturn($graphResult);

        Livewire::test(GraphViewer::class)
            ->call('selectRecentNode', 'Court', 'recent-node-1')
            ->assertSet('selectedNodeType', 'Court')
            ->assertSet('selectedNodeId', 'recent-node-1');
    }

    /**
     * Test 21: getPrecedentChain returns empty array when no precedents exist
     *
     * @test
     */
    public function test_get_precedent_chain_returns_empty_when_no_precedents_exist()
    {
        $this->setupDefaultMocks();

        // Mock empty precedent chain query
        $this->graphService->shouldReceive('run')
            ->with(
                Mockery::on(function ($query) {
                    return str_contains($query, 'FOLLOWS|OVERRULES')
                        && str_contains($query, 'path');
                }),
                ['decisionId' => 'decision-no-precedents']
            )
            ->once()
            ->andReturn(collect([]));

        $component = Livewire::test(GraphViewer::class);
        $chain = $component->call('getPrecedentChain', 'decision-no-precedents')->get('precedentChain');

        $this->assertIsArray($chain);
        $this->assertEmpty($chain);
    }

    /**
     * Test 22: getPrecedentChain returns precedent chain with FOLLOWS relationships
     *
     * @test
     */
    public function test_get_precedent_chain_returns_chain_with_follows_relationships()
    {
        $this->setupDefaultMocks();

        // Mock precedent chain with FOLLOWS relationships
        $path1 = Mockery::mock();
        $path1->shouldReceive('nodes')->andReturn([
            $this->mockDecisionNode('decision-1', 'K-1/2024'),
            $this->mockDecisionNode('decision-2', 'K-2/2023'),
        ]);
        $path1->shouldReceive('relationships')->andReturn([
            $this->mockRelationship('FOLLOWS', ['created_at' => '2024-01-01']),
        ]);

        $record1 = Mockery::mock();
        $record1->shouldReceive('get')->with('path')->andReturn($path1);

        $this->graphService->shouldReceive('run')
            ->with(
                Mockery::on(function ($query) {
                    return str_contains($query, 'FOLLOWS|OVERRULES')
                        && str_contains($query, 'path');
                }),
                ['decisionId' => 'decision-1']
            )
            ->once()
            ->andReturn(collect([$record1]));

        $component = Livewire::test(GraphViewer::class);
        $chain = $component->call('getPrecedentChain', 'decision-1')->get('precedentChain');

        $this->assertIsArray($chain);
        $this->assertNotEmpty($chain);
        $this->assertCount(1, $chain);
        $this->assertEquals('FOLLOWS', $chain[0]['type']);
        $this->assertArrayHasKey('nodes', $chain[0]);
        $this->assertCount(2, $chain[0]['nodes']);
    }

    /**
     * Test 23: getPrecedentChain returns chain with OVERRULES relationships
     *
     * @test
     */
    public function test_get_precedent_chain_returns_chain_with_overrules_relationships()
    {
        $this->setupDefaultMocks();

        // Mock precedent chain with OVERRULES relationships
        $path1 = Mockery::mock();
        $path1->shouldReceive('nodes')->andReturn([
            $this->mockDecisionNode('decision-1', 'K-1/2024'),
            $this->mockDecisionNode('decision-3', 'K-3/2020'),
        ]);
        $path1->shouldReceive('relationships')->andReturn([
            $this->mockRelationship('OVERRULES', ['created_at' => '2024-01-15']),
        ]);

        $record1 = Mockery::mock();
        $record1->shouldReceive('get')->with('path')->andReturn($path1);

        $this->graphService->shouldReceive('run')
            ->with(
                Mockery::on(function ($query) {
                    return str_contains($query, 'FOLLOWS|OVERRULES')
                        && str_contains($query, 'path');
                }),
                ['decisionId' => 'decision-1']
            )
            ->once()
            ->andReturn(collect([$record1]));

        $component = Livewire::test(GraphViewer::class);
        $chain = $component->call('getPrecedentChain', 'decision-1')->get('precedentChain');

        $this->assertIsArray($chain);
        $this->assertNotEmpty($chain);
        $this->assertCount(1, $chain);
        $this->assertEquals('OVERRULES', $chain[0]['type']);
    }

    /**
     * Test 24: getPrecedentChain respects max depth of 5
     *
     * @test
     */
    public function test_get_precedent_chain_respects_max_depth()
    {
        $this->setupDefaultMocks();

        // Verify the query contains max depth of 5
        $this->graphService->shouldReceive('run')
            ->with(
                Mockery::on(function ($query) {
                    return str_contains($query, 'FOLLOWS|OVERRULES*1..5');
                }),
                ['decisionId' => 'decision-1']
            )
            ->once()
            ->andReturn(collect([]));

        $component = Livewire::test(GraphViewer::class);
        $component->call('getPrecedentChain', 'decision-1');

        // Assertion is in the shouldReceive mock
        $this->assertTrue(true);
    }

    /**
     * Test 25: Party panel renders with plaintiff and defendant
     *
     * @test
     */
    public function test_party_panel_renders_with_plaintiff_and_defendant()
    {
        $this->setupDefaultMocks();

        // Mock graph data with party information
        $centerNode = Mockery::mock();
        $centerNode->shouldReceive('getProperty')->with('id')->andReturn('decision-123');
        $centerNode->shouldReceive('getLabels')->andReturn(['CourtDecisionDocument']);
        $centerNode->shouldReceive('getProperties')->andReturn(
            new \ArrayIterator([
                'id' => 'decision-123',
                'case_number' => 'K-123/2024',
                'plaintiff' => 'John Doe',
                'defendant' => 'Jane Smith',
                'outcome' => 'plaintiff_won',
            ])
        );

        $graphRecord = Mockery::mock();
        $graphRecord->shouldReceive('get')->with('center')->andReturn($centerNode);
        $graphRecord->shouldReceive('get')->with('connectedNodes')->andReturn([]);
        $graphRecord->shouldReceive('get')->with('outgoing')->andReturn([]);
        $graphRecord->shouldReceive('get')->with('incoming')->andReturn([]);

        $graphResult = collect([$graphRecord]);

        $this->graphService->shouldReceive('run')
            ->with(Mockery::type('string'), ['id' => 'decision-123'])
            ->once()
            ->andReturn($graphResult);

        $component = Livewire::test(GraphViewer::class)
            ->call('loadNodeGraph', 'CourtDecisionDocument', 'decision-123');

        // Verify party data is available in selectedNode
        $selectedNode = $component->get('selectedNode');
        $this->assertNotNull($selectedNode);
        $this->assertEquals('John Doe', $selectedNode['plaintiff'] ?? null);
        $this->assertEquals('Jane Smith', $selectedNode['defendant'] ?? null);
        $this->assertEquals('plaintiff_won', $selectedNode['outcome'] ?? null);
    }

    /**
     * Test 26: Party panel handles missing party information
     *
     * @test
     */
    public function test_party_panel_handles_missing_party_information()
    {
        $this->setupDefaultMocks();

        // Mock graph data without party information
        $centerNode = Mockery::mock();
        $centerNode->shouldReceive('getProperty')->with('id')->andReturn('decision-456');
        $centerNode->shouldReceive('getLabels')->andReturn(['CourtDecisionDocument']);
        $centerNode->shouldReceive('getProperties')->andReturn(
            new \ArrayIterator([
                'id' => 'decision-456',
                'case_number' => 'K-456/2024',
                // No plaintiff, defendant, or outcome
            ])
        );

        $graphRecord = Mockery::mock();
        $graphRecord->shouldReceive('get')->with('center')->andReturn($centerNode);
        $graphRecord->shouldReceive('get')->with('connectedNodes')->andReturn([]);
        $graphRecord->shouldReceive('get')->with('outgoing')->andReturn([]);
        $graphRecord->shouldReceive('get')->with('incoming')->andReturn([]);

        $graphResult = collect([$graphRecord]);

        $this->graphService->shouldReceive('run')
            ->with(Mockery::type('string'), ['id' => 'decision-456'])
            ->once()
            ->andReturn($graphResult);

        $component = Livewire::test(GraphViewer::class)
            ->call('loadNodeGraph', 'CourtDecisionDocument', 'decision-456');

        // Verify selectedNode exists but party fields are null
        $selectedNode = $component->get('selectedNode');
        $this->assertNotNull($selectedNode);
        $this->assertNull($selectedNode['plaintiff'] ?? null);
        $this->assertNull($selectedNode['defendant'] ?? null);
        $this->assertNull($selectedNode['outcome'] ?? null);
    }

    /**
     * Test 27: Component has getPartyData method
     *
     * @test
     */
    public function test_component_has_get_party_data_method()
    {
        $this->setupDefaultMocks();

        $component = Livewire::test(GraphViewer::class);

        // Verify method exists
        $this->assertTrue(
            method_exists($component->instance(), 'getPartyData'),
            'GraphViewer should have getPartyData method'
        );
    }

    /**
     * Helper method to create mock decision node
     */
    protected function mockDecisionNode(string $id, string $caseNumber)
    {
        $node = Mockery::mock();
        $node->shouldReceive('getProperty')->with('id')->andReturn($id);
        $node->shouldReceive('getProperty')->with('case_number')->andReturn($caseNumber);
        $node->shouldReceive('getProperties')->andReturn(new \ArrayIterator([
            'id' => $id,
            'case_number' => $caseNumber,
            'court' => 'Vrhovni sud',
            'decision_date' => '2024-01-01',
        ]));
        $node->shouldReceive('getLabels')->andReturn(['CourtDecisionDocument']);

        return $node;
    }

    /**
     * Helper method to create mock relationship
     */
    protected function mockRelationship(string $type, array $properties = [])
    {
        $rel = Mockery::mock();
        $rel->shouldReceive('type')->andReturn($type);
        $rel->shouldReceive('getProperties')->andReturn(new \ArrayIterator($properties));

        return $rel;
    }

    /**
     * Test 28: getJudgeData returns judge statistics
     *
     * @test
     */
    public function test_get_judge_data_returns_judge_statistics()
    {
        $this->setupDefaultMocks();

        // Mock judge statistics query
        $judgeRecord = Mockery::mock();
        $judgeRecord->shouldReceive('get')->with('caseCount')->andReturn(15);
        $judgeRecord->shouldReceive('get')->with('avgCaseDuration')->andReturn(180.5);
        $judgeRecord->shouldReceive('get')->with('rulingDistribution')->andReturn([
            'plaintiff_won' => 8,
            'defendant_won' => 5,
            'settled' => 2,
        ]);

        $judgeResult = collect([$judgeRecord]);

        $this->graphService->shouldReceive('run')
            ->with(
                Mockery::on(function ($query) {
                    return str_contains($query, 'MATCH (j:Judge {id: $judgeId})')
                        && str_contains($query, 'PRESIDED_BY');
                }),
                ['judgeId' => 'judge_123']
            )
            ->once()
            ->andReturn($judgeResult);

        $component = Livewire::test(GraphViewer::class);
        $judgeData = $component->call('getJudgeData', 'judge_123')->get('judgeData');

        $this->assertIsArray($judgeData);
        $this->assertEquals(15, $judgeData['caseCount']);
        $this->assertEquals(180.5, $judgeData['avgCaseDuration']);
        $this->assertIsArray($judgeData['rulingDistribution']);
        $this->assertCount(3, $judgeData['rulingDistribution']);
    }

    /**
     * Test 29: getJudgeData returns null for non-existent judge
     *
     * @test
     */
    public function test_get_judge_data_returns_null_for_non_existent_judge()
    {
        $this->setupDefaultMocks();

        // Mock empty result for non-existent judge
        $this->graphService->shouldReceive('run')
            ->with(
                Mockery::type('string'),
                ['judgeId' => 'non_existent_judge']
            )
            ->once()
            ->andReturn(collect([]));

        $component = Livewire::test(GraphViewer::class);
        $judgeData = $component->call('getJudgeData', 'non_existent_judge')->get('judgeData');

        $this->assertNull($judgeData);
    }

    /**
     * Test 30: getJudgeData handles errors gracefully
     *
     * @test
     */
    public function test_get_judge_data_handles_errors_gracefully()
    {
        $this->setupDefaultMocks();

        // Mock exception
        $this->graphService->shouldReceive('run')
            ->with(
                Mockery::type('string'),
                ['judgeId' => 'judge_error']
            )
            ->once()
            ->andThrow(new \Exception('Database connection error'));

        $component = Livewire::test(GraphViewer::class);
        $result = $component->call('getJudgeData', 'judge_error');

        // Should not throw exception, should set error state
        $this->assertNotNull($result->get('error'));
        $this->assertNull($result->get('judgeData'));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}

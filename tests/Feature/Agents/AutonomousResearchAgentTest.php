<?php

namespace Tests\Feature\Agents;

use App\Agents\AutonomousResearchAgent;
use App\Services\AgentEvaluationService;
use App\Services\AgentToolbox;
use App\Services\CaseSearchService;
use App\Services\DecisionSearchService;
use App\Services\LawSearchService;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class AutonomousResearchAgentTest extends TestCase
{
    use UsesTestDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_agent_constructs_with_search_services(): void
    {
        // Mock services
        $lawSearch = Mockery::mock(LawSearchService::class);
        $decisionSearch = Mockery::mock(DecisionSearchService::class);
        $caseSearch = Mockery::mock(CaseSearchService::class);
        $toolbox = Mockery::mock(AgentToolbox::class);
        $evaluator = Mockery::mock(AgentEvaluationService::class);

        // Bind mocks to container
        $this->app->instance(LawSearchService::class, $lawSearch);
        $this->app->instance(DecisionSearchService::class, $decisionSearch);
        $this->app->instance(CaseSearchService::class, $caseSearch);
        $this->app->instance(AgentToolbox::class, $toolbox);
        $this->app->instance(AgentEvaluationService::class, $evaluator);

        // Create agent
        $agent = new AutonomousResearchAgent;

        // Verify agent was constructed (no assertions needed, just verify no errors)
        $this->assertInstanceOf(AutonomousResearchAgent::class, $agent);
    }

    public function test_law_vector_search_uses_law_search_service(): void
    {
        // Mock LawSearchService
        $lawSearch = Mockery::mock(LawSearchService::class);
        $lawSearch->shouldReceive('vectorSearch')
            ->once()
            ->with('criminal law', Mockery::on(function ($params) {
                return $params['query'] === 'criminal law' && $params['jurisdiction'] === 'Croatia';
            }))
            ->andReturn([
                'success' => true,
                'data' => [
                    ['doc_id' => 'law-123', 'title' => 'Criminal Code', 'similarity' => 0.9],
                ],
                'search_type' => 'vector',
                'count' => 1,
            ]);

        $this->app->instance(LawSearchService::class, $lawSearch);
        $this->bindOtherServices();

        $agent = new AutonomousResearchAgent;
        $run = $agent->startRun('Research criminal law');

        // Execute law_vector_search action
        $results = $this->callProtectedMethod($agent, 'executeActions', [
            [
                [
                    'tool' => 'law_vector_search',
                    'params' => [
                        'query' => 'criminal law',
                        'jurisdiction' => 'Croatia',
                    ],
                ],
            ],
            $run,
        ]);

        $this->assertCount(1, $results);
        $this->assertTrue($results[0]['success']);
        $this->assertEquals('law_vector_search', $results[0]['tool']);
        $this->assertEquals(1, $results[0]['result']['count']);
    }

    public function test_law_keyword_search_uses_law_search_service(): void
    {
        // Mock LawSearchService
        $lawSearch = Mockery::mock(LawSearchService::class);
        $lawSearch->shouldReceive('keywordSearch')
            ->once()
            ->with('NN 94/14', Mockery::type('array'))
            ->andReturn([
                'success' => true,
                'data' => [
                    ['doc_id' => 'law-123', 'law_number' => 'NN 94/14'],
                ],
                'search_type' => 'keyword',
                'pagination' => ['total' => 1, 'page' => 1, 'limit' => 10, 'pages' => 1],
            ]);

        $this->app->instance(LawSearchService::class, $lawSearch);
        $this->bindOtherServices();

        $agent = new AutonomousResearchAgent;
        $run = $agent->startRun('Find law NN 94/14');

        $results = $this->callProtectedMethod($agent, 'executeActions', [
            [
                [
                    'tool' => 'law_keyword_search',
                    'params' => ['query' => 'NN 94/14'],
                ],
            ],
            $run,
        ]);

        $this->assertTrue($results[0]['success']);
        $this->assertEquals('keyword', $results[0]['result']['search_type']);
    }

    public function test_decision_vector_search_uses_decision_search_service(): void
    {
        // Mock DecisionSearchService
        $decisionSearch = Mockery::mock(DecisionSearchService::class);
        $decisionSearch->shouldReceive('vectorSearch')
            ->once()
            ->with('contract dispute', Mockery::type('array'))
            ->andReturn([
                'success' => true,
                'data' => [
                    ['decision_id' => 'dec-123', 'title' => 'Contract Case', 'similarity' => 0.88],
                ],
                'search_type' => 'vector',
                'count' => 1,
            ]);

        $this->app->instance(DecisionSearchService::class, $decisionSearch);
        $this->bindOtherServices();

        $agent = new AutonomousResearchAgent;
        $run = $agent->startRun('Research contract disputes');

        $results = $this->callProtectedMethod($agent, 'executeActions', [
            [
                [
                    'tool' => 'decision_vector_search',
                    'params' => ['query' => 'contract dispute'],
                ],
            ],
            $run,
        ]);

        $this->assertTrue($results[0]['success']);
        $this->assertEquals('vector', $results[0]['result']['search_type']);
    }

    public function test_decision_lookup_uses_decision_search_service(): void
    {
        // Mock DecisionSearchService
        $decisionSearch = Mockery::mock(DecisionSearchService::class);
        $decisionSearch->shouldReceive('lookupByCriteria')
            ->once()
            ->with(Mockery::on(function ($criteria) {
                return $criteria['case_number'] === 'P-123/2024';
            }))
            ->andReturn([
                ['id' => 1, 'case_number' => 'P-123/2024', 'title' => 'Test Decision'],
            ]);

        $this->app->instance(DecisionSearchService::class, $decisionSearch);
        $this->bindOtherServices();

        $agent = new AutonomousResearchAgent;
        $run = $agent->startRun('Find decision P-123/2024');

        $results = $this->callProtectedMethod($agent, 'executeActions', [
            [
                [
                    'tool' => 'decision_lookup',
                    'params' => ['case_number' => 'P-123/2024'],
                ],
            ],
            $run,
        ]);

        $this->assertTrue($results[0]['success']);
        $this->assertCount(1, $results[0]['result']);
    }

    public function test_case_vector_search_uses_case_search_service(): void
    {
        // Mock CaseSearchService
        $caseSearch = Mockery::mock(CaseSearchService::class);
        $caseSearch->shouldReceive('vectorSearch')
            ->once()
            ->with('employment dispute', Mockery::type('array'))
            ->andReturn([
                'success' => true,
                'data' => [
                    ['case_id' => 'case-123', 'title' => 'Employment Case', 'similarity' => 0.85],
                ],
                'search_type' => 'vector',
                'count' => 1,
            ]);

        $this->app->instance(CaseSearchService::class, $caseSearch);
        $this->bindOtherServices();

        $agent = new AutonomousResearchAgent;
        $run = $agent->startRun('Research employment disputes');

        $results = $this->callProtectedMethod($agent, 'executeActions', [
            [
                [
                    'tool' => 'case_vector_search',
                    'params' => ['query' => 'employment dispute'],
                ],
            ],
            $run,
        ]);

        $this->assertTrue($results[0]['success']);
        $this->assertEquals('vector', $results[0]['result']['search_type']);
    }

    public function test_case_search_uses_case_search_service(): void
    {
        // Mock CaseSearchService
        $caseSearch = Mockery::mock(CaseSearchService::class);
        $caseSearch->shouldReceive('searchCases')
            ->once()
            ->with('Smith', Mockery::type('array'))
            ->andReturn([
                'success' => true,
                'search_type' => 'cases',
                'data' => [
                    ['case_number' => 'C-123/2024', 'client_name' => 'John Smith'],
                ],
                'pagination' => ['total' => 1, 'page' => 1, 'limit' => 10, 'pages' => 1],
            ]);

        $this->app->instance(CaseSearchService::class, $caseSearch);
        $this->bindOtherServices();

        $agent = new AutonomousResearchAgent;
        $run = $agent->startRun('Find cases for Smith');

        $results = $this->callProtectedMethod($agent, 'executeActions', [
            [
                [
                    'tool' => 'case_search',
                    'params' => ['query' => 'Smith'],
                ],
            ],
            $run,
        ]);

        $this->assertTrue($results[0]['success']);
        $this->assertEquals('cases', $results[0]['result']['search_type']);
    }

    public function test_graph_query_still_uses_toolbox(): void
    {
        // Mock AgentToolbox for graph_query
        $toolbox = Mockery::mock(AgentToolbox::class);
        $toolbox->shouldReceive('graphQuery')
            ->once()
            ->with('MATCH (n) RETURN n', [])
            ->andReturn([
                'success' => true,
                'rows' => [
                    ['n' => ['id' => 1, 'name' => 'Test Node']],
                ],
                'count' => 1,
            ]);

        $this->app->instance(AgentToolbox::class, $toolbox);
        $this->bindOtherServices();

        $agent = new AutonomousResearchAgent;
        $run = $agent->startRun('Query graph database');

        $results = $this->callProtectedMethod($agent, 'executeActions', [
            [
                [
                    'tool' => 'graph_query',
                    'params' => ['cypher' => 'MATCH (n) RETURN n', 'parameters' => []],
                ],
            ],
            $run,
        ]);

        $this->assertTrue($results[0]['success']);
        $this->assertTrue($results[0]['result']['success']);
    }

    public function test_web_fetch_still_uses_toolbox(): void
    {
        // Mock AgentToolbox for web_fetch
        $toolbox = Mockery::mock(AgentToolbox::class);
        $toolbox->shouldReceive('webFetch')
            ->once()
            ->with('https://example.com', Mockery::type('array'))
            ->andReturn([
                'success' => true,
                'status' => 200,
                'content' => '<html>Example</html>',
            ]);

        $this->app->instance(AgentToolbox::class, $toolbox);
        $this->bindOtherServices();

        $agent = new AutonomousResearchAgent;
        $run = $agent->startRun('Fetch website');

        $results = $this->callProtectedMethod($agent, 'executeActions', [
            [
                [
                    'tool' => 'web_fetch',
                    'params' => ['url' => 'https://example.com'],
                ],
            ],
            $run,
        ]);

        $this->assertTrue($results[0]['success']);
        $this->assertEquals(200, $results[0]['result']['status']);
    }

    public function test_legacy_vector_search_logs_deprecation_warning(): void
    {
        // Mock AgentToolbox for legacy vector_search
        $toolbox = Mockery::mock(AgentToolbox::class);
        $toolbox->shouldReceive('vectorSearch')
            ->once()
            ->with('test query', Mockery::type('array'))
            ->andReturn([
                'laws' => [],
                'cases' => [],
                'decisions' => [],
            ]);

        $this->app->instance(AgentToolbox::class, $toolbox);
        $this->bindOtherServices();

        // Mock Log::info() called by startRun()
        \Log::shouldReceive('info')
            ->with('Started autonomous research run', Mockery::type('array'));

        // Capture deprecation warning log
        \Log::shouldReceive('warning')
            ->once()
            ->with('Agent using deprecated vector_search tool', Mockery::on(function ($context) {
                return $context['agent'] === 'autonomous_research_agent';
            }));

        $agent = new AutonomousResearchAgent;
        $run = $agent->startRun('Legacy search');

        $results = $this->callProtectedMethod($agent, 'executeActions', [
            [
                [
                    'tool' => 'vector_search',
                    'params' => ['query' => 'test query'],
                ],
            ],
            $run,
        ]);

        $this->assertTrue($results[0]['success']);
    }

    public function test_unknown_tool_returns_error(): void
    {
        $this->bindOtherServices();

        $agent = new AutonomousResearchAgent;
        $run = $agent->startRun('Test unknown tool');

        $results = $this->callProtectedMethod($agent, 'executeActions', [
            [
                [
                    'tool' => 'unknown_tool',
                    'params' => [],
                ],
            ],
            $run,
        ]);

        $this->assertFalse($results[0]['success']);
        $this->assertStringContainsString('Unknown tool', $results[0]['result']['error']);
    }

    public function test_multiple_tools_execute_in_sequence(): void
    {
        // Mock services for multiple tools
        $lawSearch = Mockery::mock(LawSearchService::class);
        $lawSearch->shouldReceive('vectorSearch')
            ->once()
            ->andReturn(['success' => true, 'data' => [], 'search_type' => 'vector', 'count' => 0]);

        $decisionSearch = Mockery::mock(DecisionSearchService::class);
        $decisionSearch->shouldReceive('keywordSearch')
            ->once()
            ->andReturn(['success' => true, 'data' => [], 'search_type' => 'keyword', 'pagination' => []]);

        $this->app->instance(LawSearchService::class, $lawSearch);
        $this->app->instance(DecisionSearchService::class, $decisionSearch);
        $this->bindOtherServices();

        $agent = new AutonomousResearchAgent;
        $run = $agent->startRun('Multiple searches');

        $results = $this->callProtectedMethod($agent, 'executeActions', [
            [
                ['tool' => 'law_vector_search', 'params' => ['query' => 'test']],
                ['tool' => 'decision_keyword_search', 'params' => ['query' => 'test']],
            ],
            $run,
        ]);

        $this->assertCount(2, $results);
        $this->assertTrue($results[0]['success']);
        $this->assertTrue($results[1]['success']);
    }

    public function test_tool_exception_is_caught_and_logged(): void
    {
        // Mock service to throw exception
        $lawSearch = Mockery::mock(LawSearchService::class);
        $lawSearch->shouldReceive('vectorSearch')
            ->once()
            ->andThrow(new \Exception('Database connection failed'));

        $this->app->instance(LawSearchService::class, $lawSearch);
        $this->bindOtherServices();

        $agent = new AutonomousResearchAgent;
        $run = $agent->startRun('Test error handling');

        $results = $this->callProtectedMethod($agent, 'executeActions', [
            [
                [
                    'tool' => 'law_vector_search',
                    'params' => ['query' => 'test'],
                ],
            ],
            $run,
        ]);

        $this->assertFalse($results[0]['success']);
        $this->assertEquals('Database connection failed', $results[0]['error']);
    }

    /**
     * Helper method to bind other services that aren't being tested
     */
    protected function bindOtherServices(): void
    {
        if (! $this->app->bound(LawSearchService::class)) {
            $this->app->instance(LawSearchService::class, Mockery::mock(LawSearchService::class));
        }
        if (! $this->app->bound(DecisionSearchService::class)) {
            $this->app->instance(DecisionSearchService::class, Mockery::mock(DecisionSearchService::class));
        }
        if (! $this->app->bound(CaseSearchService::class)) {
            $this->app->instance(CaseSearchService::class, Mockery::mock(CaseSearchService::class));
        }
        if (! $this->app->bound(AgentToolbox::class)) {
            $toolbox = Mockery::mock(AgentToolbox::class);
            // Mock getRecentInsights() which is called by startRun()
            $toolbox->shouldReceive('getRecentInsights')
                ->andReturn(['success' => false, 'insights' => []]);
            $this->app->instance(AgentToolbox::class, $toolbox);
        } else {
            // If toolbox already bound, add getRecentInsights expectation if not already set
            $toolbox = $this->app->make(AgentToolbox::class);
            if ($toolbox instanceof \Mockery\MockInterface) {
                $toolbox->shouldReceive('getRecentInsights')
                    ->andReturn(['success' => false, 'insights' => []])
                    ->byDefault();
            }
        }
        if (! $this->app->bound(AgentEvaluationService::class)) {
            $this->app->instance(AgentEvaluationService::class, Mockery::mock(AgentEvaluationService::class));
        }
    }

    /**
     * Helper to call protected methods for testing
     */
    protected function callProtectedMethod($object, $method, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}

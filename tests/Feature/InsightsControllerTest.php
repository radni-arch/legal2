<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AgentToolbox;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class InsightsControllerTest extends TestCase
{
    use UsesTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Authenticate user for API token authentication
        $this->actingAs(User::factory()->create());
    }

    /** @test */
    public function it_retrieves_insights_successfully()
    {
        $mockToolbox = Mockery::mock(AgentToolbox::class);
        $mockToolbox->shouldReceive('getRecentInsights')
            ->once()
            ->with('autonomous_research_agent', [
                'namespace' => 'research_insights',
                'limit' => 10,
                'days' => 30,
            ])
            ->andReturn([
                'success' => true,
                'insights' => [
                    [
                        'id' => '01HQTEST123456789',
                        'content' => 'Article 93 of Croatian Labor Law requires 2 weeks notice',
                        'objective' => 'Research Croatian labor law',
                        'metadata' => ['run_id' => 'run-123'],
                        'source' => 'autonomous_research',
                        'source_id' => 'run-123',
                        'created_at' => '2025-10-15T10:00:00Z',
                    ],
                    [
                        'id' => '01HQTEST987654321',
                        'content' => 'Supreme Court ruling X-123/2024 clarified probation rules',
                        'objective' => 'Research Croatian labor law',
                        'metadata' => ['run_id' => 'run-456'],
                        'source' => 'autonomous_research',
                        'source_id' => 'run-456',
                        'created_at' => '2025-10-20T14:30:00Z',
                    ],
                ],
                'count' => 2,
                'message' => 'Successfully retrieved 2 recent insights',
            ]);

        $this->app->instance(AgentToolbox::class, $mockToolbox);

        $response = $this->getJson('/api/insights/autonomous_research_agent');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'agent_name' => 'autonomous_research_agent',
                'filters' => [
                    'namespace' => 'research_insights',
                    'limit' => 10,
                    'days' => 30,
                ],
                'count' => 2,
            ])
            ->assertJsonStructure([
                'success',
                'agent_name',
                'filters' => ['namespace', 'limit', 'days'],
                'insights' => [
                    '*' => [
                        'id',
                        'content',
                        'objective',
                        'metadata',
                        'source',
                        'source_id',
                        'created_at',
                    ],
                ],
                'count',
                'message',
            ]);

        $insights = $response->json('insights');
        $this->assertCount(2, $insights);
        $this->assertEquals('Article 93 of Croatian Labor Law requires 2 weeks notice', $insights[0]['content']);
    }

    /** @test */
    public function it_filters_insights_by_objective()
    {
        $mockToolbox = Mockery::mock(AgentToolbox::class);
        $mockToolbox->shouldReceive('getRecentInsights')
            ->once()
            ->with('autonomous_research_agent', [
                'namespace' => 'research_insights',
                'limit' => 10,
                'days' => 30,
                'objective' => 'Croatian labor law',
            ])
            ->andReturn([
                'success' => true,
                'insights' => [
                    [
                        'id' => '01HQTEST123',
                        'content' => 'Labor law insight',
                        'objective' => 'Research Croatian labor law termination',
                        'metadata' => [],
                        'source' => 'autonomous_research',
                        'source_id' => 'run-1',
                        'created_at' => '2025-10-15T10:00:00Z',
                    ],
                ],
                'count' => 1,
            ]);

        $this->app->instance(AgentToolbox::class, $mockToolbox);

        $response = $this->getJson('/api/insights/autonomous_research_agent?objective=Croatian labor law');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'filters' => [
                    'objective' => 'Croatian labor law',
                ],
                'count' => 1,
            ]);
    }

    /** @test */
    public function it_filters_insights_by_namespace()
    {
        $mockToolbox = Mockery::mock(AgentToolbox::class);
        $mockToolbox->shouldReceive('getRecentInsights')
            ->once()
            ->with('autonomous_research_agent', [
                'namespace' => 'custom_namespace',
                'limit' => 10,
                'days' => 30,
            ])
            ->andReturn([
                'success' => true,
                'insights' => [],
                'count' => 0,
            ]);

        $this->app->instance(AgentToolbox::class, $mockToolbox);

        $response = $this->getJson('/api/insights/autonomous_research_agent?namespace=custom_namespace');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'filters' => [
                    'namespace' => 'custom_namespace',
                ],
            ]);
    }

    /** @test */
    public function it_filters_insights_by_source()
    {
        $mockToolbox = Mockery::mock(AgentToolbox::class);
        $mockToolbox->shouldReceive('getRecentInsights')
            ->once()
            ->with('autonomous_research_agent', [
                'namespace' => 'research_insights',
                'limit' => 10,
                'days' => 30,
                'source' => 'manual_entry',
            ])
            ->andReturn([
                'success' => true,
                'insights' => [],
                'count' => 0,
            ]);

        $this->app->instance(AgentToolbox::class, $mockToolbox);

        $response = $this->getJson('/api/insights/autonomous_research_agent?source=manual_entry');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'filters' => [
                    'source' => 'manual_entry',
                ],
            ]);
    }

    /** @test */
    public function it_respects_custom_limit()
    {
        $mockToolbox = Mockery::mock(AgentToolbox::class);
        $mockToolbox->shouldReceive('getRecentInsights')
            ->once()
            ->with('autonomous_research_agent', [
                'namespace' => 'research_insights',
                'limit' => 5,
                'days' => 30,
            ])
            ->andReturn([
                'success' => true,
                'insights' => [],
                'count' => 0,
            ]);

        $this->app->instance(AgentToolbox::class, $mockToolbox);

        $response = $this->getJson('/api/insights/autonomous_research_agent?limit=5');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'filters' => [
                    'limit' => 5,
                ],
            ]);
    }

    /** @test */
    public function it_caps_limit_at_100()
    {
        $mockToolbox = Mockery::mock(AgentToolbox::class);
        $mockToolbox->shouldReceive('getRecentInsights')
            ->once()
            ->with('autonomous_research_agent', [
                'namespace' => 'research_insights',
                'limit' => 100, // Should be capped at 100
                'days' => 30,
            ])
            ->andReturn([
                'success' => true,
                'insights' => [],
                'count' => 0,
            ]);

        $this->app->instance(AgentToolbox::class, $mockToolbox);

        $response = $this->getJson('/api/insights/autonomous_research_agent?limit=200');

        $response->assertStatus(200);
        $this->assertEquals(100, $response->json('filters.limit'));
    }

    /** @test */
    public function it_respects_custom_days()
    {
        $mockToolbox = Mockery::mock(AgentToolbox::class);
        $mockToolbox->shouldReceive('getRecentInsights')
            ->once()
            ->with('autonomous_research_agent', [
                'namespace' => 'research_insights',
                'limit' => 10,
                'days' => 60,
            ])
            ->andReturn([
                'success' => true,
                'insights' => [],
                'count' => 0,
            ]);

        $this->app->instance(AgentToolbox::class, $mockToolbox);

        $response = $this->getJson('/api/insights/autonomous_research_agent?days=60');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'filters' => [
                    'days' => 60,
                ],
            ]);
    }

    /** @test */
    public function it_validates_objective_min_length()
    {
        $response = $this->getJson('/api/insights/autonomous_research_agent?objective=ab');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['objective']);
    }

    /** @test */
    public function it_validates_limit_is_positive_integer()
    {
        $response = $this->getJson('/api/insights/autonomous_research_agent?limit=0');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['limit']);
    }

    /** @test */
    public function it_validates_limit_max_value()
    {
        $mockToolbox = Mockery::mock(AgentToolbox::class);
        $mockToolbox->shouldReceive('getRecentInsights')
            ->once()
            ->andReturn([
                'success' => true,
                'insights' => [],
                'count' => 0,
            ]);

        $this->app->instance(AgentToolbox::class, $mockToolbox);

        $response = $this->getJson('/api/insights/autonomous_research_agent?limit=150');

        // Should succeed but cap at 100
        $response->assertStatus(200);
        $this->assertEquals(100, $response->json('filters.limit'));
    }

    /** @test */
    public function it_validates_days_max_value()
    {
        $response = $this->getJson('/api/insights/autonomous_research_agent?days=400');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['days']);
    }

    /** @test */
    public function it_rejects_invalid_agent_name_format()
    {
        $response = $this->getJson('/api/insights/Invalid-Agent-Name!');

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
                'message' => 'Invalid agent name format',
            ]);
    }

    /** @test */
    public function it_accepts_valid_agent_name_with_underscores()
    {
        $mockToolbox = Mockery::mock(AgentToolbox::class);
        $mockToolbox->shouldReceive('getRecentInsights')
            ->once()
            ->andReturn([
                'success' => true,
                'insights' => [],
                'count' => 0,
            ]);

        $this->app->instance(AgentToolbox::class, $mockToolbox);

        $response = $this->getJson('/api/insights/research_agent_v2');

        $response->assertStatus(200);
    }

    /** @test */
    public function it_returns_empty_insights_when_none_found()
    {
        $mockToolbox = Mockery::mock(AgentToolbox::class);
        $mockToolbox->shouldReceive('getRecentInsights')
            ->once()
            ->andReturn([
                'success' => true,
                'insights' => [],
                'count' => 0,
                'message' => 'No insights found',
            ]);

        $this->app->instance(AgentToolbox::class, $mockToolbox);

        $response = $this->getJson('/api/insights/autonomous_research_agent');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'insights' => [],
                'count' => 0,
            ]);
    }

    /** @test */
    public function it_handles_toolbox_failure_gracefully()
    {
        $mockToolbox = Mockery::mock(AgentToolbox::class);
        $mockToolbox->shouldReceive('getRecentInsights')
            ->once()
            ->andReturn([
                'success' => false,
                'error' => 'Database connection failed',
                'insights' => [],
                'count' => 0,
            ]);

        $this->app->instance(AgentToolbox::class, $mockToolbox);

        $response = $this->getJson('/api/insights/autonomous_research_agent');

        $response->assertStatus(500)
            ->assertJson([
                'success' => false,
                'code' => 'AGENT_EXECUTION_FAILED',
                'message' => 'Failed to retrieve insights',
            ]);
    }

    /** @test */
    public function it_handles_toolbox_exceptions_gracefully()
    {
        $mockToolbox = Mockery::mock(AgentToolbox::class);
        $mockToolbox->shouldReceive('getRecentInsights')
            ->once()
            ->andThrow(new \Exception('Unexpected error'));

        $this->app->instance(AgentToolbox::class, $mockToolbox);

        $response = $this->getJson('/api/insights/autonomous_research_agent');

        $response->assertStatus(500)
            ->assertJson([
                'success' => false,
                'code' => 'AGENT_EXECUTION_FAILED',
                'message' => 'An error occurred while retrieving insights',
            ]);
    }

    /** @test */
    public function it_combines_multiple_filters()
    {
        $mockToolbox = Mockery::mock(AgentToolbox::class);
        $mockToolbox->shouldReceive('getRecentInsights')
            ->once()
            ->with('autonomous_research_agent', [
                'namespace' => 'custom_namespace',
                'limit' => 20,
                'days' => 14,
                'objective' => 'labor law',
                'source' => 'autonomous_research',
            ])
            ->andReturn([
                'success' => true,
                'insights' => [],
                'count' => 0,
            ]);

        $this->app->instance(AgentToolbox::class, $mockToolbox);

        $response = $this->getJson(
            '/api/insights/autonomous_research_agent?'.
            'namespace=custom_namespace&'.
            'limit=20&'.
            'days=14&'.
            'objective=labor law&'.
            'source=autonomous_research'
        );

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'filters' => [
                    'namespace' => 'custom_namespace',
                    'limit' => 20,
                    'days' => 14,
                    'objective' => 'labor law',
                    'source' => 'autonomous_research',
                ],
            ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}

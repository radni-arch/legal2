<?php

namespace Tests\Feature\Api;

use App\Services\GraphDatabaseService;
use Tests\TestCase;
use Mockery;

class GraphHealthCheckTest extends TestCase
{
    /** @test */
    public function health_check_returns_healthy_when_connected(): void
    {
        $graphService = Mockery::mock(GraphDatabaseService::class);
        $graphService->shouldReceive('isHealthy')->andReturn(true);

        $this->app->instance(GraphDatabaseService::class, $graphService);

        $response = $this->getJson('/api/health/graph');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'healthy',
                'service' => 'neo4j',
            ]);
    }

    /** @test */
    public function health_check_returns_unhealthy_when_disconnected(): void
    {
        $graphService = Mockery::mock(GraphDatabaseService::class);
        $graphService->shouldReceive('isHealthy')->andReturn(false);

        $this->app->instance(GraphDatabaseService::class, $graphService);

        $response = $this->getJson('/api/health/graph');

        $response->assertStatus(503)
            ->assertJson([
                'status' => 'unhealthy',
                'service' => 'neo4j',
            ]);
    }

    /** @test */
    public function health_check_includes_latency_metric(): void
    {
        $graphService = Mockery::mock(GraphDatabaseService::class);
        $graphService->shouldReceive('isHealthy')->andReturn(true);

        $this->app->instance(GraphDatabaseService::class, $graphService);

        $response = $this->getJson('/api/health/graph');

        $response->assertStatus(200)
            ->assertJsonStructure(['latency_ms']);
    }
}

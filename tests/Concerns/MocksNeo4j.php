<?php

namespace Tests\Concerns;

use App\Services\GraphDatabaseService;
use Mockery;

trait MocksNeo4j
{
    protected function mockNeo4jUnavailable(): void
    {
        $mock = Mockery::mock(GraphDatabaseService::class);
        $mock->shouldReceive('isAvailable')->andReturn(false);
        $this->app->instance(GraphDatabaseService::class, $mock);
    }

    protected function mockNeo4jAvailable(): GraphDatabaseService
    {
        $mock = Mockery::mock(GraphDatabaseService::class);
        $mock->shouldReceive('isAvailable')->andReturn(true);
        $this->app->instance(GraphDatabaseService::class, $mock);

        return $mock;
    }

    protected function mockNeo4jQuery(string $query, array $params, $returnValue): void
    {
        $mock = $this->app->make(GraphDatabaseService::class);
        if (! ($mock instanceof \Mockery\MockInterface)) {
            $mock = $this->mockNeo4jAvailable();
        }

        $mock->shouldReceive('run')
            ->with($query, $params)
            ->andReturn(collect($returnValue));
    }
}

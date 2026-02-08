<?php

namespace Tests\Unit\Services\Graph;

use App\Services\Graph\GraphExplorerService;
use App\Services\GraphDatabaseService;
use Mockery;
use Tests\TestCase;

class GraphExplorerServiceTest extends TestCase
{
    /** @test */
    public function it_filters_connections_by_relationship_type()
    {
        $graphMock = Mockery::mock(GraphDatabaseService::class);
        $graphMock->shouldReceive('run')
            ->once()
            ->with(Mockery::on(fn($cypher) => str_contains($cypher, 'type(r) IN')), Mockery::any())
            ->andReturn(collect([
                ['node' => ['id' => 'n1'], 'rel' => ['type' => 'CITES']],
            ]));

        $service = new GraphExplorerService($graphMock);

        $result = $service->getFilteredConnections('node-123', ['CITES', 'CONTRADICTS']);

        $this->assertCount(1, $result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}

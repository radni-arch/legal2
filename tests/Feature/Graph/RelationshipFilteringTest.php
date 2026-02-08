<?php

namespace Tests\Feature\Graph;

use App\Services\Graph\GraphExplorerService;
use App\Services\GraphDatabaseService;
use Mockery;
use Tests\TestCase;

class RelationshipFilteringTest extends TestCase
{
    /** @test */
    public function it_returns_empty_when_no_relationships_selected()
    {
        $graphMock = Mockery::mock(GraphDatabaseService::class);
        $service = new GraphExplorerService($graphMock);

        $result = $service->getFilteredConnections('node-123', []);

        $this->assertEmpty($result);
    }

    /** @test */
    public function it_filters_by_multiple_relationship_types()
    {
        $graphMock = Mockery::mock(GraphDatabaseService::class);
        $graphMock->shouldReceive('run')
            ->once()
            ->with(Mockery::on(fn($cypher) => str_contains($cypher, 'type(r) IN')), Mockery::type('array'))
            ->andReturn(collect([
                ['node' => ['id' => 'n1'], 'rel' => ['type' => 'CITES']],
                ['node' => ['id' => 'n2'], 'rel' => ['type' => 'CONTRADICTS']],
            ]));

        $service = new GraphExplorerService($graphMock);

        $result = $service->getFilteredConnections('node-123', ['CITES', 'CONTRADICTS']);

        $this->assertCount(2, $result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}

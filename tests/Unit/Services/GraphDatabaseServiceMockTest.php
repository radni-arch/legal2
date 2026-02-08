<?php

namespace Tests\Unit\Services;

use App\Services\GraphDatabaseService;
use Mockery;
use Tests\TestCase;

class GraphDatabaseServiceMockTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_can_be_mocked_for_offline_testing(): void
    {
        $mock = Mockery::mock(GraphDatabaseService::class);

        $mock->shouldReceive('isAvailable')
            ->andReturn(true);

        $mock->shouldReceive('run')
            ->with('CREATE (n:TestNode {id: $id}) RETURN n', Mockery::any())
            ->andReturn(collect([
                (object) ['n' => (object) ['id' => 'test-123']],
            ]));

        $this->assertTrue($mock->isAvailable());
        $result = $mock->run('CREATE (n:TestNode {id: $id}) RETURN n', ['id' => 'test-123']);
        $this->assertNotEmpty($result);
    }
}

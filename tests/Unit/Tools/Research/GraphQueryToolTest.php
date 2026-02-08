<?php

namespace Tests\Unit\Tools\Research;

use App\Tools\Research\GraphQueryTool;
use App\Services\GraphDatabaseService;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Vizra\VizraADK\Memory\AgentMemory;
use Vizra\VizraADK\System\AgentContext;

class GraphQueryToolTest extends TestCase
{
    #[Test]
    public function it_has_correct_definition(): void
    {
        $mockGraph = Mockery::mock(GraphDatabaseService::class);
        $tool = new GraphQueryTool($mockGraph);

        $definition = $tool->definition();

        $this->assertEquals('graph_query', $definition['name']);
        $this->assertArrayHasKey('description', $definition);
        $this->assertArrayHasKey('parameters', $definition);
        $this->assertArrayHasKey('properties', $definition['parameters']);
        $this->assertArrayHasKey('cypher', $definition['parameters']['properties']);
        $this->assertContains('cypher', $definition['parameters']['required']);
    }

    #[Test]
    public function it_executes_graph_query(): void
    {
        $mockGraph = Mockery::mock(GraphDatabaseService::class);

        $mockResult = Mockery::mock();
        $mockResult->shouldReceive('count')->andReturn(2);
        $mockResult->shouldReceive('toArray')->andReturn([
            ['n' => ['id' => 'law-1', 'title' => 'Law 1']],
            ['n' => ['id' => 'law-2', 'title' => 'Law 2']],
        ]);

        $mockGraph->shouldReceive('run')
            ->once()
            ->with('MATCH (n:Law) RETURN n LIMIT 2', Mockery::type('array'))
            ->andReturn($mockResult);

        $tool = new GraphQueryTool($mockGraph);

        $context = Mockery::mock(AgentContext::class);
        $memory = Mockery::mock(AgentMemory::class);

        $result = $tool->execute(
            [
                'cypher' => 'MATCH (n:Law) RETURN n LIMIT 2',
                'parameters' => [],
            ],
            $context,
            $memory
        );

        $this->assertJson($result);
        $decoded = json_decode($result, true);
        $this->assertTrue($decoded['success']);
        $this->assertEquals(2, $decoded['count']);
        $this->assertCount(2, $decoded['rows']);
    }

    #[Test]
    public function it_handles_errors_gracefully(): void
    {
        $mockGraph = Mockery::mock(GraphDatabaseService::class);
        $mockGraph->shouldReceive('run')
            ->once()
            ->andThrow(new \Exception('Invalid Cypher syntax'));

        $tool = new GraphQueryTool($mockGraph);

        $context = Mockery::mock(AgentContext::class);
        $memory = Mockery::mock(AgentMemory::class);

        $result = $tool->execute(
            ['cypher' => 'INVALID QUERY'],
            $context,
            $memory
        );

        $this->assertJson($result);
        $decoded = json_decode($result, true);
        $this->assertFalse($decoded['success']);
        $this->assertStringContainsString('Invalid Cypher syntax', $decoded['error']);
    }
}

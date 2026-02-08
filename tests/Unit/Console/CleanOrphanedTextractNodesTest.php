<?php

namespace Tests\Unit\Console;

use App\Console\Commands\CleanOrphanedTextractNodes;
use App\Services\GraphDatabaseService;
use Illuminate\Support\Facades\DB;
use Mockery;
use Illuminate\Console\OutputStyle;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Tests\TestCase;

/**
 * Unit tests for CleanOrphanedTextractNodes command
 *
 * Tests the command logic in isolation using mocks
 */
class CleanOrphanedTextractNodesTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_detects_orphaned_nodes_when_db_record_missing()
    {
        // Mock graph service
        $graphService = Mockery::mock(GraphDatabaseService::class);
        $graphService->shouldReceive('isAvailable')->andReturn(true);

        // Mock Neo4j query returning nodes (must implement IteratorAggregate for foreach)
        $mockResult = Mockery::mock(\IteratorAggregate::class);
        $mockRecord1 = Mockery::mock();
        $mockRecord1->shouldReceive('get')->with('id')->andReturn('node-1');
        $mockRecord2 = Mockery::mock();
        $mockRecord2->shouldReceive('get')->with('id')->andReturn('node-2');

        $mockResult->shouldReceive('getIterator')->andReturn(new \ArrayIterator([
            $mockRecord1,
            $mockRecord2,
        ]));

        $graphService->shouldReceive('run')
            ->with('MATCH (n:TextractDocument) RETURN n.id as id', [])
            ->andReturn($mockResult);

        $this->app->instance(GraphDatabaseService::class, $graphService);

        // Mock DB - only node-1 exists in DB
        DB::shouldReceive('table')
            ->with('textract_documents')
            ->andReturnSelf();
        DB::shouldReceive('whereIn')
            ->with('id', ['node-1', 'node-2'])
            ->andReturnSelf();
        DB::shouldReceive('pluck')
            ->with('id')
            ->andReturn(collect(['node-1'])); // Only node-1 exists

        // Create command instance with output initialized
        $command = new CleanOrphanedTextractNodes($graphService);
        $command->setOutput(new OutputStyle(new ArrayInput([]), new NullOutput));

        // Use reflection to test protected method
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('findOrphanedNodes');
        $method->setAccessible(true);

        $orphaned = $method->invoke($command, 100);

        $this->assertCount(1, $orphaned);
        $this->assertEquals(['node-2'], $orphaned);
    }

    /** @test */
    public function it_returns_empty_array_when_no_orphans_found()
    {
        $graphService = Mockery::mock(GraphDatabaseService::class);
        $graphService->shouldReceive('isAvailable')->andReturn(true);

        // Mock Neo4j returning one node (must implement IteratorAggregate for foreach)
        $mockResult = Mockery::mock(\IteratorAggregate::class);
        $mockRecord = Mockery::mock();
        $mockRecord->shouldReceive('get')->with('id')->andReturn('node-1');

        $mockResult->shouldReceive('getIterator')->andReturn(new \ArrayIterator([$mockRecord]));

        $graphService->shouldReceive('run')
            ->with('MATCH (n:TextractDocument) RETURN n.id as id', [])
            ->andReturn($mockResult);

        $this->app->instance(GraphDatabaseService::class, $graphService);

        // Mock DB - node exists
        DB::shouldReceive('table')
            ->with('textract_documents')
            ->andReturnSelf();
        DB::shouldReceive('whereIn')
            ->with('id', ['node-1'])
            ->andReturnSelf();
        DB::shouldReceive('pluck')
            ->with('id')
            ->andReturn(collect(['node-1']));

        $command = new CleanOrphanedTextractNodes($graphService);
        $command->setOutput(new OutputStyle(new ArrayInput([]), new NullOutput));

        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('findOrphanedNodes');
        $method->setAccessible(true);

        $orphaned = $method->invoke($command, 100);

        $this->assertEmpty($orphaned);
    }

    /** @test */
    public function it_handles_neo4j_unavailable_gracefully()
    {
        $graphService = Mockery::mock(GraphDatabaseService::class);
        $graphService->shouldReceive('isAvailable')->andReturn(false);

        $this->app->instance(GraphDatabaseService::class, $graphService);

        $command = new CleanOrphanedTextractNodes($graphService);
        $command->setOutput(new OutputStyle(new ArrayInput([]), new NullOutput));

        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('findOrphanedNodes');
        $method->setAccessible(true);

        $orphaned = $method->invoke($command, 100);

        $this->assertEmpty($orphaned);
    }

    /** @test */
    public function it_processes_nodes_in_batches()
    {
        $graphService = Mockery::mock(GraphDatabaseService::class);
        $graphService->shouldReceive('isAvailable')->andReturn(true);

        // Create 250 mock nodes
        $nodes = [];
        for ($i = 1; $i <= 250; $i++) {
            $mockRecord = Mockery::mock();
            $mockRecord->shouldReceive('get')->with('id')->andReturn("node-{$i}");
            $nodes[] = $mockRecord;
        }

        $mockResult = Mockery::mock(\IteratorAggregate::class);
        $mockResult->shouldReceive('getIterator')->andReturn(new \ArrayIterator($nodes));

        $graphService->shouldReceive('run')
            ->with('MATCH (n:TextractDocument) RETURN n.id as id', [])
            ->andReturn($mockResult);

        $this->app->instance(GraphDatabaseService::class, $graphService);

        // Mock DB - only first 100 exist
        $existingIds = array_map(fn ($i) => "node-{$i}", range(1, 100));

        DB::shouldReceive('table')
            ->with('textract_documents')
            ->andReturnSelf();

        // Should be called 3 times for batch size 100
        DB::shouldReceive('whereIn')
            ->times(3)
            ->andReturnSelf();

        DB::shouldReceive('pluck')
            ->with('id')
            ->andReturn(
                collect(array_slice($existingIds, 0, 100)), // First batch
                collect([]), // Second batch - none exist
                collect([])  // Third batch - none exist
            );

        $command = new CleanOrphanedTextractNodes($graphService);
        $command->setOutput(new OutputStyle(new ArrayInput([]), new NullOutput));

        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('findOrphanedNodes');
        $method->setAccessible(true);

        $orphaned = $method->invoke($command, 100);

        // Should find 150 orphaned nodes (251-250)
        $this->assertCount(150, $orphaned);
    }
}

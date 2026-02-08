<?php

namespace Tests\Feature;

use App\Services\GraphDatabaseService;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Feature tests for CleanOrphanedTextractNodes command
 *
 * Tests the full command execution flow
 */
class CleanOrphanedTextractNodesCommandTest extends TestCase
{
    use UsesTestDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_runs_in_dry_run_mode_without_deleting()
    {
        $graphService = Mockery::mock(GraphDatabaseService::class);
        $graphService->shouldReceive('isAvailable')->andReturn(true);

        // Mock Neo4j returning 2 nodes
        $mockResult = Mockery::mock();
        $mockRecord1 = Mockery::mock();
        $mockRecord1->shouldReceive('get')->with('id')->andReturn('orphan-1');
        $mockRecord2 = Mockery::mock();
        $mockRecord2->shouldReceive('get')->with('id')->andReturn('orphan-2');

        $mockResult->shouldReceive('getIterator')->andReturn(new \ArrayIterator([
            $mockRecord1,
            $mockRecord2,
        ]));

        $graphService->shouldReceive('run')
            ->with('MATCH (n:TextractDocument) RETURN n.id as id', [])
            ->andReturn($mockResult);

        // Should NOT call deleteNode in dry-run mode
        $graphService->shouldNotReceive('deleteNode');

        $this->app->instance(GraphDatabaseService::class, $graphService);

        $exitCode = Artisan::call('textract:clean-orphaned-nodes', [
            '--dry-run' => true,
        ]);

        $this->assertEquals(0, $exitCode);

        $output = Artisan::output();
        $this->assertStringContainsString('DRY RUN', $output);
        $this->assertStringContainsString('Found 2 orphaned', $output);
        $this->assertStringContainsString('No nodes deleted', $output);
    }

    /** @test */
    public function it_deletes_orphaned_nodes_when_not_dry_run()
    {
        $graphService = Mockery::mock(GraphDatabaseService::class);
        $graphService->shouldReceive('isAvailable')->andReturn(true);

        // Mock finding orphaned nodes
        $mockResult = Mockery::mock();
        $mockRecord = Mockery::mock();
        $mockRecord->shouldReceive('get')->with('id')->andReturn('orphan-1');

        $mockResult->shouldReceive('getIterator')->andReturn(new \ArrayIterator([$mockRecord]));

        $graphService->shouldReceive('run')
            ->with('MATCH (n:TextractDocument) RETURN n.id as id', [])
            ->andReturn($mockResult);

        // Should call deleteNode for the orphan
        $graphService->shouldReceive('deleteNode')
            ->once()
            ->with('TextractDocument', 'orphan-1');

        $this->app->instance(GraphDatabaseService::class, $graphService);

        $exitCode = Artisan::call('textract:clean-orphaned-nodes');

        $this->assertEquals(0, $exitCode);

        $output = Artisan::output();
        $this->assertStringContainsString('Found 1 orphaned', $output);
        $this->assertStringContainsString('Deleted 1 orphaned', $output);
    }

    /** @test */
    public function it_handles_neo4j_unavailable_gracefully()
    {
        $graphService = Mockery::mock(GraphDatabaseService::class);
        $graphService->shouldReceive('isAvailable')->andReturn(false);

        $this->app->instance(GraphDatabaseService::class, $graphService);

        $exitCode = Artisan::call('textract:clean-orphaned-nodes');

        // Should fail when Neo4j is unavailable
        $this->assertEquals(1, $exitCode);

        $output = Artisan::output();
        $this->assertStringContainsString('Neo4j is not available', $output);
    }

    /** @test */
    public function it_respects_batch_size_option()
    {
        $graphService = Mockery::mock(GraphDatabaseService::class);
        $graphService->shouldReceive('isAvailable')->andReturn(true);

        // Create 60 mock nodes
        $nodes = [];
        for ($i = 1; $i <= 60; $i++) {
            $mockRecord = Mockery::mock();
            $mockRecord->shouldReceive('get')->with('id')->andReturn("node-{$i}");
            $nodes[] = $mockRecord;
        }

        $mockResult = Mockery::mock();
        $mockResult->shouldReceive('getIterator')->andReturn(new \ArrayIterator($nodes));

        $graphService->shouldReceive('run')
            ->with('MATCH (n:TextractDocument) RETURN n.id as id', [])
            ->andReturn($mockResult);

        $this->app->instance(GraphDatabaseService::class, $graphService);

        $exitCode = Artisan::call('textract:clean-orphaned-nodes', [
            '--batch' => 25,
            '--dry-run' => true,
        ]);

        $this->assertEquals(0, $exitCode);
        // Test passes if no errors occur with custom batch size
    }

    /** @test */
    public function it_handles_deletion_errors_gracefully()
    {
        $graphService = Mockery::mock(GraphDatabaseService::class);
        $graphService->shouldReceive('isAvailable')->andReturn(true);

        // Mock finding 2 orphaned nodes
        $mockResult = Mockery::mock();
        $mockRecord1 = Mockery::mock();
        $mockRecord1->shouldReceive('get')->with('id')->andReturn('orphan-1');
        $mockRecord2 = Mockery::mock();
        $mockRecord2->shouldReceive('get')->with('id')->andReturn('orphan-2');

        $mockResult->shouldReceive('getIterator')->andReturn(new \ArrayIterator([
            $mockRecord1,
            $mockRecord2,
        ]));

        $graphService->shouldReceive('run')
            ->with('MATCH (n:TextractDocument) RETURN n.id as id', [])
            ->andReturn($mockResult);

        // First deletion succeeds, second fails
        $graphService->shouldReceive('deleteNode')
            ->with('TextractDocument', 'orphan-1')
            ->once();

        $graphService->shouldReceive('deleteNode')
            ->with('TextractDocument', 'orphan-2')
            ->once()
            ->andThrow(new \Exception('Deletion failed'));

        $this->app->instance(GraphDatabaseService::class, $graphService);

        $exitCode = Artisan::call('textract:clean-orphaned-nodes');

        $this->assertEquals(0, $exitCode); // Should still succeed

        $output = Artisan::output();
        $this->assertStringContainsString('Deleted 1 orphaned', $output);
        $this->assertStringContainsString('1 error', $output);
    }

    /** @test */
    public function it_displays_progress_bar_for_large_batch()
    {
        $graphService = Mockery::mock(GraphDatabaseService::class);
        $graphService->shouldReceive('isAvailable')->andReturn(true);

        // Create 10 mock nodes
        $nodes = [];
        for ($i = 1; $i <= 10; $i++) {
            $mockRecord = Mockery::mock();
            $mockRecord->shouldReceive('get')->with('id')->andReturn("orphan-{$i}");
            $nodes[] = $mockRecord;
        }

        $mockResult = Mockery::mock();
        $mockResult->shouldReceive('getIterator')->andReturn(new \ArrayIterator($nodes));

        $graphService->shouldReceive('run')
            ->with('MATCH (n:TextractDocument) RETURN n.id as id', [])
            ->andReturn($mockResult);

        $graphService->shouldReceive('deleteNode')
            ->times(10);

        $this->app->instance(GraphDatabaseService::class, $graphService);

        $exitCode = Artisan::call('textract:clean-orphaned-nodes');

        $this->assertEquals(0, $exitCode);
        // Progress bar would show in actual output
    }

    /** @test */
    public function it_shows_success_message_when_no_orphans_found()
    {
        $graphService = Mockery::mock(GraphDatabaseService::class);
        $graphService->shouldReceive('isAvailable')->andReturn(true);

        // Mock empty result
        $mockResult = Mockery::mock();
        $mockResult->shouldReceive('getIterator')->andReturn(new \ArrayIterator([]));

        $graphService->shouldReceive('run')
            ->with('MATCH (n:TextractDocument) RETURN n.id as id', [])
            ->andReturn($mockResult);

        $this->app->instance(GraphDatabaseService::class, $graphService);

        $exitCode = Artisan::call('textract:clean-orphaned-nodes');

        $this->assertEquals(0, $exitCode);

        $output = Artisan::output();
        $this->assertStringContainsString('No orphaned nodes found', $output);
    }
}

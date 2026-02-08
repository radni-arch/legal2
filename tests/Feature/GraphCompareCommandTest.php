<?php

namespace Tests\Feature;

use App\Services\GraphDatabaseService;
use Illuminate\Support\Facades\File;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class GraphCompareCommandTest extends TestCase
{
    use UsesTestDatabase;

    protected string $snapshotDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->snapshotDir = storage_path('graph-snapshots');

        // Clean up snapshots before each test
        if (File::exists($this->snapshotDir)) {
            File::deleteDirectory($this->snapshotDir);
        }
        File::makeDirectory($this->snapshotDir, 0755, true);
    }

    protected function tearDown(): void
    {
        // Clean up after tests
        if (File::exists($this->snapshotDir)) {
            File::deleteDirectory($this->snapshotDir);
        }

        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_captures_current_graph_state()
    {
        $mockGraphDb = $this->mockGraphService([
            'Decision' => 100,
            'Law' => 50,
            'Article' => 200,
        ], [
            'CITES_LAW' => 150,
            'CITES_DECISION' => 75,
        ]);

        $this->artisan('graph:compare')
            ->expectsOutput('📊 Capturing current graph state...')
            ->assertExitCode(0);

        // Verify Neo4j was queried
        Mockery::close();
    }

    /** @test */
    public function it_saves_snapshot_with_save_option()
    {
        $mockGraphDb = $this->mockGraphService([
            'Decision' => 100,
            'Law' => 50,
        ], [
            'CITES_LAW' => 75,
        ]);

        $this->artisan('graph:compare', ['--save' => 'test-snapshot'])
            ->expectsOutput('✅ Snapshot saved: test-snapshot')
            ->assertExitCode(0);

        // Verify snapshot file was created
        $snapshotFile = $this->snapshotDir.'/test-snapshot.json';
        $this->assertTrue(File::exists($snapshotFile));

        // Verify snapshot contents
        $data = json_decode(File::get($snapshotFile), true);
        $this->assertArrayHasKey('timestamp', $data);
        $this->assertArrayHasKey('nodes', $data);
        $this->assertArrayHasKey('relationships', $data);
        $this->assertEquals(100, $data['nodes']['Decision']);
        $this->assertEquals(50, $data['nodes']['Law']);
        $this->assertEquals(75, $data['relationships']['CITES_LAW']);
    }

    /** @test */
    public function it_compares_with_baseline_no_changes()
    {
        // Create baseline snapshot
        $baselineData = $this->createBaselineSnapshot('baseline', [
            'nodes' => ['Decision' => 100, 'Law' => 50],
            'relationships' => ['CITES_LAW' => 75],
            'totals' => ['nodes' => 150, 'relationships' => 75],
        ]);

        // Mock current state to match baseline
        $mockGraphDb = $this->mockGraphService([
            'Decision' => 100,
            'Law' => 50,
        ], [
            'CITES_LAW' => 75,
        ]);

        $this->artisan('graph:compare', ['--baseline' => 'baseline'])
            ->expectsOutput('✅ No significant variance detected')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_detects_significant_variance_above_threshold()
    {
        // Create baseline snapshot
        $this->createBaselineSnapshot('baseline', [
            'nodes' => ['Decision' => 100, 'Law' => 50],
            'relationships' => ['CITES_LAW' => 75],
            'totals' => ['nodes' => 150, 'relationships' => 75],
        ]);

        // Mock current state with 20% increase (above 10% threshold)
        $mockGraphDb = $this->mockGraphService([
            'Decision' => 120, // +20%
            'Law' => 50,
        ], [
            'CITES_LAW' => 75,
        ]);

        $this->artisan('graph:compare', ['--baseline' => 'baseline'])
            ->expectsOutput('❌ SIGNIFICANT VARIANCE DETECTED (>10%)')
            ->assertExitCode(1);
    }

    /** @test */
    public function it_detects_variance_in_relationships()
    {
        // Create baseline snapshot
        $this->createBaselineSnapshot('baseline', [
            'nodes' => ['Decision' => 100, 'Law' => 50],
            'relationships' => ['CITES_LAW' => 100],
            'totals' => ['nodes' => 150, 'relationships' => 100],
        ]);

        // Mock current state with significant relationship change
        $mockGraphDb = $this->mockGraphService([
            'Decision' => 100,
            'Law' => 50,
        ], [
            'CITES_LAW' => 120, // +20% in relationships
        ]);

        $this->artisan('graph:compare', ['--baseline' => 'baseline'])
            ->expectsOutput('❌ SIGNIFICANT VARIANCE DETECTED (>10%)')
            ->assertExitCode(1);
    }

    /** @test */
    public function it_accepts_custom_threshold()
    {
        // Create baseline snapshot
        $this->createBaselineSnapshot('baseline', [
            'nodes' => ['Decision' => 100, 'Law' => 50],
            'relationships' => ['CITES_LAW' => 75],
            'totals' => ['nodes' => 150, 'relationships' => 75],
        ]);

        // Mock current state with 15% change
        $mockGraphDb = $this->mockGraphService([
            'Decision' => 115, // +15%
            'Law' => 50,
        ], [
            'CITES_LAW' => 75,
        ]);

        // Should pass with 20% threshold
        $this->artisan('graph:compare', [
            '--baseline' => 'baseline',
            '--threshold' => 20,
        ])
            ->expectsOutput('✅ No significant variance detected')
            ->assertExitCode(0);

        // Should fail with 10% threshold
        $mockGraphDb = $this->mockGraphService([
            'Decision' => 115,
            'Law' => 50,
        ], [
            'CITES_LAW' => 75,
        ]);

        $this->artisan('graph:compare', [
            '--baseline' => 'baseline',
            '--threshold' => 10,
        ])
            ->expectsOutput('❌ SIGNIFICANT VARIANCE DETECTED (>10%)')
            ->assertExitCode(1);
    }

    /** @test */
    public function it_lists_available_snapshots()
    {
        // Create some test snapshots
        $this->createBaselineSnapshot('snapshot1', [
            'nodes' => ['Decision' => 100],
            'relationships' => ['CITES_LAW' => 50],
            'totals' => ['nodes' => 100, 'relationships' => 50],
        ]);

        $this->createBaselineSnapshot('snapshot2', [
            'nodes' => ['Decision' => 200],
            'relationships' => ['CITES_LAW' => 100],
            'totals' => ['nodes' => 200, 'relationships' => 100],
        ]);

        $this->artisan('graph:compare', ['--list' => true])
            ->expectsOutput('📂 Available Snapshots:')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_fails_when_baseline_not_found()
    {
        $mockGraphDb = $this->mockGraphService([
            'Decision' => 100,
        ], [
            'CITES_LAW' => 50,
        ]);

        $this->artisan('graph:compare', ['--baseline' => 'nonexistent'])
            ->expectsOutput('❌ Baseline snapshot not found: nonexistent')
            ->assertExitCode(1);
    }

    /** @test */
    public function it_fails_when_neo4j_unavailable()
    {
        $mockGraphDb = Mockery::mock(GraphDatabaseService::class);
        $mockGraphDb->shouldReceive('isAvailable')
            ->once()
            ->andReturn(false);

        $this->app->instance(GraphDatabaseService::class, $mockGraphDb);

        $this->artisan('graph:compare')
            ->expectsOutput('❌ Neo4j is not available')
            ->assertExitCode(1);
    }

    /** @test */
    public function it_sanitizes_snapshot_names()
    {
        $mockGraphDb = $this->mockGraphService([
            'Decision' => 100,
        ], [
            'CITES_LAW' => 50,
        ]);

        $this->artisan('graph:compare', ['--save' => 'test/snapshot with spaces!'])
            ->assertExitCode(0);

        // Verify filename was sanitized
        $sanitized = $this->snapshotDir.'/test-snapshot-with-spaces-.json';
        $this->assertTrue(File::exists($sanitized));
    }

    /** @test */
    public function it_shows_detailed_diff_with_show_diff_option()
    {
        // Create baseline
        $this->createBaselineSnapshot('baseline', [
            'nodes' => ['Decision' => 100, 'Law' => 50],
            'relationships' => ['CITES_LAW' => 75],
            'totals' => ['nodes' => 150, 'relationships' => 75],
        ]);

        // Mock current state with changes
        $mockGraphDb = $this->mockGraphService([
            'Decision' => 105,
            'Law' => 52,
        ], [
            'CITES_LAW' => 80,
        ]);

        $this->artisan('graph:compare', [
            '--baseline' => 'baseline',
            '--show-diff' => true,
        ])
            ->expectsOutput('📦 Node Changes:')
            ->expectsOutput('🔗 Relationship Changes:')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_handles_decreases_in_counts()
    {
        // Create baseline
        $this->createBaselineSnapshot('baseline', [
            'nodes' => ['Decision' => 100, 'Law' => 50],
            'relationships' => ['CITES_LAW' => 100],
            'totals' => ['nodes' => 150, 'relationships' => 100],
        ]);

        // Mock current state with decrease
        $mockGraphDb = $this->mockGraphService([
            'Decision' => 80, // -20%
            'Law' => 50,
        ], [
            'CITES_LAW' => 100,
        ]);

        $this->artisan('graph:compare', ['--baseline' => 'baseline'])
            ->expectsOutput('❌ SIGNIFICANT VARIANCE DETECTED (>10%)')
            ->assertExitCode(1);
    }

    /** @test */
    public function it_handles_new_node_labels_not_in_baseline()
    {
        // Create baseline without Article nodes
        $this->createBaselineSnapshot('baseline', [
            'nodes' => ['Decision' => 100, 'Law' => 50],
            'relationships' => ['CITES_LAW' => 75],
            'totals' => ['nodes' => 150, 'relationships' => 75],
        ]);

        // Mock current state with new Article nodes
        $mockGraphDb = $this->mockGraphService([
            'Decision' => 100,
            'Law' => 50,
            'Article' => 200, // New label
        ], [
            'CITES_LAW' => 75,
        ]);

        // Should not fail - new labels are expected in graph growth
        $this->artisan('graph:compare', ['--baseline' => 'baseline'])
            ->assertExitCode(0);
    }

    /** @test */
    public function it_calculates_percentage_correctly_for_zero_baseline()
    {
        // Create baseline with zero relationships
        $this->createBaselineSnapshot('baseline', [
            'nodes' => ['Decision' => 100],
            'relationships' => ['CITES_LAW' => 0],
            'totals' => ['nodes' => 100, 'relationships' => 0],
        ]);

        // Mock current state with new relationships
        $mockGraphDb = $this->mockGraphService([
            'Decision' => 100,
        ], [
            'CITES_LAW' => 50,
        ]);

        // Should handle division by zero gracefully
        $this->artisan('graph:compare', ['--baseline' => 'baseline'])
            ->assertExitCode(0);
    }

    /** @test */
    public function it_shows_tip_when_no_options_provided()
    {
        $mockGraphDb = $this->mockGraphService([
            'Decision' => 100,
        ], [
            'CITES_LAW' => 50,
        ]);

        $this->artisan('graph:compare')
            ->expectsOutput('💡 Tip: Use --save to create a snapshot, or --baseline to compare')
            ->assertExitCode(0);
    }

    // Helper methods

    protected function mockGraphService(array $nodeCounts, array $relationshipCounts): GraphDatabaseService
    {
        $mockGraphDb = Mockery::mock(GraphDatabaseService::class);

        $mockGraphDb->shouldReceive('isAvailable')
            ->andReturn(true);

        // Mock countNodes for specific labels
        foreach ($nodeCounts as $label => $count) {
            $mockGraphDb->shouldReceive('countNodes')
                ->with($label)
                ->andReturn($count);
        }

        // Mock countNodes for labels not in nodeCounts
        $mockGraphDb->shouldReceive('countNodes')
            ->with(Mockery::not(Mockery::anyOf(...array_keys($nodeCounts))))
            ->andReturn(0);

        // Mock countRelationships for specific types
        foreach ($relationshipCounts as $type => $count) {
            $mockGraphDb->shouldReceive('countRelationships')
                ->with($type)
                ->andReturn($count);
        }

        // Mock countRelationships for types not in relationshipCounts
        $mockGraphDb->shouldReceive('countRelationships')
            ->with(Mockery::not(Mockery::anyOf(...array_keys($relationshipCounts))))
            ->andReturn(0);

        // Mock totals
        $mockGraphDb->shouldReceive('countNodes')
            ->with()
            ->andReturn(array_sum($nodeCounts));

        $mockGraphDb->shouldReceive('countRelationships')
            ->with()
            ->andReturn(array_sum($relationshipCounts));

        $this->app->instance(GraphDatabaseService::class, $mockGraphDb);

        return $mockGraphDb;
    }

    protected function createBaselineSnapshot(string $name, array $data): array
    {
        $snapshot = [
            'timestamp' => now()->toIso8601String(),
            'nodes' => $data['nodes'] ?? [],
            'relationships' => $data['relationships'] ?? [],
            'totals' => $data['totals'] ?? [
                'nodes' => array_sum($data['nodes'] ?? []),
                'relationships' => array_sum($data['relationships'] ?? []),
            ],
        ];

        $filename = $this->snapshotDir.'/'.$name.'.json';
        File::put($filename, json_encode($snapshot, JSON_PRETTY_PRINT));

        return $snapshot;
    }
}

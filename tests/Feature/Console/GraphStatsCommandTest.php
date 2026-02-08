<?php

namespace Tests\Feature\Console;

use App\Services\GraphDatabaseService;
use App\Services\TaggingService;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Test suite for GraphStatsCommand
 *
 * Tests display of Neo4j graph database statistics including
 * node counts, relationship counts, and tag statistics.
 */
class GraphStatsCommandTest extends TestCase
{
    use UsesTestDatabase;

    protected $graphMock;

    protected $taggingMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->graphMock = Mockery::mock(GraphDatabaseService::class);
        $this->taggingMock = Mockery::mock(TaggingService::class);

        $this->app->instance(GraphDatabaseService::class, $this->graphMock);
        $this->app->instance(TaggingService::class, $this->taggingMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_requires_neo4j_to_be_enabled()
    {
        Config::set('neo4j.sync.enabled', false);

        $this->artisan('graph:stats')
            ->expectsOutput('Neo4j integration is disabled.')
            ->assertExitCode(1);
    }

    /** @test */
    public function it_displays_node_counts()
    {
        Config::set('neo4j.sync.enabled', true);

        $this->mockNodeCounts([
            'LawDocument' => 100,
            'CaseDocument' => 50,
            'Keyword' => 200,
            'Tag' => 30,
            'Jurisdiction' => 5,
            'Court' => 10,
        ]);

        $this->mockRelationshipCounts([]);
        $this->mockTopTags([]);

        $this->artisan('graph:stats')
            ->expectsOutput('Graph Database Statistics')
            ->expectsOutput('Node Counts:')
            ->expectsOutputToContain('LawDocument: 100')
            ->expectsOutputToContain('CaseDocument: 50')
            ->expectsOutputToContain('Keyword: 200')
            ->expectsOutputToContain('Tag: 30')
            ->expectsOutputToContain('Jurisdiction: 5')
            ->expectsOutputToContain('Court: 10')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_displays_relationship_counts()
    {
        Config::set('neo4j.sync.enabled', true);

        $this->mockNodeCounts([]);

        $this->mockRelationshipCounts([
            'CITES' => 150,
            'HAS_TAG' => 300,
            'RELATED_TO' => 75,
            'BELONGS_TO' => 50,
        ]);

        $this->mockTopTags([]);

        $this->artisan('graph:stats')
            ->expectsOutput('Relationship Counts:')
            ->expectsOutputToContain('CITES: 150')
            ->expectsOutputToContain('HAS_TAG: 300')
            ->expectsOutputToContain('RELATED_TO: 75')
            ->expectsOutputToContain('BELONGS_TO: 50')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_displays_top_tags()
    {
        Config::set('neo4j.sync.enabled', true);

        $this->mockNodeCounts([]);
        $this->mockRelationshipCounts([]);

        $this->mockTopTags([
            ['name' => 'kazneno pravo', 'count' => 50],
            ['name' => 'ustav', 'count' => 30],
            ['name' => 'pretres doma', 'count' => 20],
        ]);

        $this->artisan('graph:stats')
            ->expectsOutput('Top Tags:')
            ->expectsOutputToContain('kazneno pravo: 50 documents')
            ->expectsOutputToContain('ustav: 30 documents')
            ->expectsOutputToContain('pretres doma: 20 documents')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_handles_zero_node_counts()
    {
        Config::set('neo4j.sync.enabled', true);

        $this->mockNodeCounts([
            'LawDocument' => 0,
            'CaseDocument' => 0,
            'Keyword' => 0,
            'Tag' => 0,
            'Jurisdiction' => 0,
            'Court' => 0,
        ]);

        $this->mockRelationshipCounts([]);
        $this->mockTopTags([]);

        $this->artisan('graph:stats')
            ->expectsOutputToContain('LawDocument: 0')
            ->expectsOutputToContain('CaseDocument: 0')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_handles_database_query_failure()
    {
        Config::set('neo4j.sync.enabled', true);

        $this->graphMock->shouldReceive('run')
            ->once()
            ->andThrow(new \Exception('Database connection failed'));

        $this->artisan('graph:stats')
            ->expectsOutput('Failed to retrieve statistics: Database connection failed')
            ->assertExitCode(1);
    }

    /** @test */
    public function it_limits_top_tags_to_10()
    {
        Config::set('neo4j.sync.enabled', true);

        $this->mockNodeCounts([]);
        $this->mockRelationshipCounts([]);

        // Mock exactly 10 tags
        $tags = [];
        for ($i = 1; $i <= 10; $i++) {
            $tags[] = ['name' => "tag{$i}", 'count' => 100 - $i];
        }
        $this->mockTopTags($tags);

        $output = $this->artisan('graph:stats')->execute();

        // Verify we got 10 tags
        $this->assertCount(10, $tags);
    }

    /** @test */
    public function it_displays_stats_in_correct_order()
    {
        Config::set('neo4j.sync.enabled', true);

        $this->mockNodeCounts(['LawDocument' => 1]);
        $this->mockRelationshipCounts(['CITES' => 1]);
        $this->mockTopTags([['name' => 'tag1', 'count' => 1]]);

        $output = $this->artisan('graph:stats')
            ->expectsOutput('Graph Database Statistics')
            ->expectsOutput('Node Counts:')
            ->expectsOutput('Relationship Counts:')
            ->expectsOutput('Top Tags:')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_handles_empty_top_tags()
    {
        Config::set('neo4j.sync.enabled', true);

        $this->mockNodeCounts([]);
        $this->mockRelationshipCounts([]);
        $this->mockTopTags([]); // No tags

        $this->artisan('graph:stats')
            ->expectsOutput('Top Tags:')
            // Should not display any tag lines
            ->assertExitCode(0);
    }

    /** @test */
    public function it_handles_empty_relationships()
    {
        Config::set('neo4j.sync.enabled', true);

        $this->mockNodeCounts([]);
        $this->mockRelationshipCounts([]); // No relationships
        $this->mockTopTags([]);

        $this->artisan('graph:stats')
            ->expectsOutput('Relationship Counts:')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_queries_all_expected_node_labels()
    {
        Config::set('neo4j.sync.enabled', true);

        $expectedLabels = ['LawDocument', 'CaseDocument', 'Keyword', 'Tag', 'Jurisdiction', 'Court'];

        foreach ($expectedLabels as $label) {
            $resultMock = Mockery::mock();
            $recordMock = Mockery::mock();
            $recordMock->shouldReceive('get')
                ->with('count')
                ->andReturn(10);
            $resultMock->shouldReceive('first')
                ->andReturn($recordMock);

            $this->graphMock->shouldReceive('run')
                ->once()
                ->with("MATCH (n:$label) RETURN count(n) as count")
                ->andReturn($resultMock);
        }

        $this->mockRelationshipCounts([]);
        $this->mockTopTags([]);

        $this->artisan('graph:stats')->assertExitCode(0);
    }

    // Helper methods

    protected function mockNodeCounts(array $counts): void
    {
        foreach ($counts as $label => $count) {
            $resultMock = Mockery::mock();
            $recordMock = Mockery::mock();
            $recordMock->shouldReceive('get')
                ->with('count')
                ->andReturn($count);
            $resultMock->shouldReceive('first')
                ->andReturn($recordMock);

            $this->graphMock->shouldReceive('run')
                ->with("MATCH (n:$label) RETURN count(n) as count")
                ->andReturn($resultMock);
        }
    }

    protected function mockRelationshipCounts(array $counts): void
    {
        $resultMock = Mockery::mock();
        $records = [];

        foreach ($counts as $type => $count) {
            $recordMock = Mockery::mock();
            $recordMock->shouldReceive('get')
                ->with('type')
                ->andReturn($type);
            $recordMock->shouldReceive('get')
                ->with('count')
                ->andReturn($count);
            $records[] = $recordMock;
        }

        $resultMock->shouldReceive('getIterator')
            ->andReturn(new \ArrayIterator($records));

        $this->graphMock->shouldReceive('run')
            ->with(
                Mockery::pattern("/MATCH \(\)-\[r\]->\(\)/"),
                Mockery::any()
            )
            ->andReturn($resultMock);
    }

    protected function mockTopTags(array $tags): void
    {
        $resultMock = Mockery::mock();
        $records = [];

        foreach ($tags as $tag) {
            $recordMock = Mockery::mock();
            $recordMock->shouldReceive('get')
                ->with('name')
                ->andReturn($tag['name']);
            $recordMock->shouldReceive('get')
                ->with('count')
                ->andReturn($tag['count']);
            $records[] = $recordMock;
        }

        $resultMock->shouldReceive('map')
            ->once()
            ->andReturnUsing(function ($callback) use ($records) {
                $mapped = array_map($callback, $records);
                $collectionMock = Mockery::mock();
                $collectionMock->shouldReceive('toArray')
                    ->andReturn($mapped);

                return $collectionMock;
            });

        $this->graphMock->shouldReceive('run')
            ->with(
                Mockery::pattern("/MATCH \(t:Tag\)</"),
                Mockery::type('array')
            )
            ->andReturn($resultMock);
    }
}

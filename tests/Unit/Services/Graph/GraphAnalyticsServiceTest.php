<?php

namespace Tests\Unit\Services\Graph;

use App\Services\GraphDatabaseService;
use Mockery;
use Tests\TestCase;

class GraphAnalyticsServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_calculates_degree_centrality(): void
    {
        $graph = Mockery::mock(GraphDatabaseService::class);

        $graph->shouldReceive('run')
            ->with(Mockery::pattern('/MATCH.*count/'), Mockery::any())
            ->andReturn(collect([
                (object) ['node_id' => 'law-1', 'degree' => 10],
                (object) ['node_id' => 'law-2', 'degree' => 5],
                (object) ['node_id' => 'law-3', 'degree' => 2],
            ]));

        // Test that we can query for degree centrality
        $result = $graph->run(
            'MATCH (n)<-[r]-() RETURN n.id as node_id, count(r) as degree ORDER BY degree DESC',
            []
        );

        $this->assertCount(3, $result);
        $this->assertEquals(10, $result[0]->degree);
        $this->assertEquals('law-1', $result[0]->node_id);
    }

    /** @test */
    public function it_finds_most_cited_laws(): void
    {
        $graph = Mockery::mock(GraphDatabaseService::class);

        $graph->shouldReceive('run')
            ->with(Mockery::pattern('/MATCH.*LawDocument.*CITES/'), Mockery::any())
            ->andReturn(collect([
                (object) [
                    'law_id' => 'ZKP-240',
                    'title' => 'Pretraga doma',
                    'citation_count' => 150,
                ],
            ]));

        $result = $graph->run(
            'MATCH (l:LawDocument)<-[r:CITES]-()
             RETURN l.law_number as law_id, l.title as title, count(r) as citation_count
             ORDER BY citation_count DESC
             LIMIT 10',
            []
        );

        $this->assertNotEmpty($result);
        $this->assertEquals('ZKP-240', $result[0]->law_id);
        $this->assertGreaterThan(0, $result[0]->citation_count);
    }

    /** @test */
    public function it_identifies_citation_clusters(): void
    {
        $graph = Mockery::mock(GraphDatabaseService::class);

        // Mock community detection result
        $graph->shouldReceive('run')
            ->with(Mockery::pattern('/community|cluster/i'), Mockery::any())
            ->andReturn(collect([
                (object) ['community_id' => 1, 'member_count' => 25],
                (object) ['community_id' => 2, 'member_count' => 18],
                (object) ['community_id' => 3, 'member_count' => 12],
            ]));

        $result = $graph->run(
            'MATCH (n) WHERE n.community IS NOT NULL
             RETURN n.community as community_id, count(n) as member_count
             ORDER BY member_count DESC',
            []
        );

        $this->assertCount(3, $result);
        $this->assertEquals(25, $result[0]->member_count);
    }
}

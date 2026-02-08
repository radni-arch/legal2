<?php

namespace Tests\Unit\Services;

use App\Services\GraphDatabaseService;
use App\Services\GraphQueryHelper;
use Mockery;
use Tests\TestCase;

class GraphQueryHelperTest extends TestCase
{
    protected $graphMock;

    protected GraphQueryHelper $helper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->graphMock = Mockery::mock(GraphDatabaseService::class);
        $this->helper = new GraphQueryHelper($this->graphMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_finds_citing_laws()
    {
        $mockResult = Mockery::mock();
        $mockResult->shouldReceive('map')->andReturn(collect([
            ['id' => 'law-1', 'title' => 'Citing Law 1'],
            ['id' => 'law-2', 'title' => 'Citing Law 2'],
        ]));
        $mockResult->shouldReceive('toArray')->andReturn([
            ['id' => 'law-1', 'title' => 'Citing Law 1'],
            ['id' => 'law-2', 'title' => 'Citing Law 2'],
        ]);

        $this->graphMock
            ->shouldReceive('run')
            ->once()
            ->with(
                Mockery::on(fn ($query) => str_contains($query, 'MATCH') && str_contains($query, 'CITES')),
                Mockery::on(fn ($params) => $params['lawId'] === 'law-target' && $params['limit'] === 20)
            )
            ->andReturn($mockResult);

        $results = $this->helper->findCitingLaws('law-target');

        $this->assertIsArray($results);
    }

    /** @test */
    public function it_limits_citing_laws_results()
    {
        $mockResult = Mockery::mock();
        $mockResult->shouldReceive('map->toArray')->andReturn([]);

        $this->graphMock
            ->shouldReceive('run')
            ->once()
            ->with(
                Mockery::any(),
                Mockery::on(fn ($params) => $params['limit'] === 5)
            )
            ->andReturn($mockResult);

        $this->helper->findCitingLaws('law-id', 5);

        $this->assertTrue(true);
    }

    /** @test */
    public function it_finds_cases_applying_law()
    {
        $mockResult = Mockery::mock();
        $mockResult->shouldReceive('map->toArray')->andReturn([
            ['id' => 'case-1', 'title' => 'Case 1'],
            ['id' => 'case-2', 'title' => 'Case 2'],
        ]);

        $this->graphMock
            ->shouldReceive('run')
            ->once()
            ->with(
                Mockery::on(fn ($query) => str_contains($query, 'REFERENCES')),
                Mockery::on(fn ($params) => $params['lawId'] === 'law-123')
            )
            ->andReturn($mockResult);

        $results = $this->helper->findCasesApplyingLaw('law-123');

        $this->assertIsArray($results);
        $this->assertCount(2, $results);
    }

    /** @test */
    public function it_finds_documents_by_tag_pattern()
    {
        $mockResult = Mockery::mock();
        $mockResult->shouldReceive('map->toArray')->andReturn([
            ['document' => ['id' => 'doc-1'], 'matches' => 3],
            ['document' => ['id' => 'doc-2'], 'matches' => 2],
        ]);

        $this->graphMock
            ->shouldReceive('run')
            ->once()
            ->with(
                Mockery::on(fn ($query) => str_contains($query, 'HAS_TAG') && str_contains($query, 'matches')),
                Mockery::on(function ($params) {
                    return isset($params['tagIds'])
                        && $params['minMatches'] === 2
                        && $params['limit'] === 20;
                })
            )
            ->andReturn($mockResult);

        $results = $this->helper->findByTagPattern(['criminal', 'theft'], 2);

        $this->assertIsArray($results);
        $this->assertCount(2, $results);
    }

    /** @test */
    public function it_normalizes_tag_ids()
    {
        $mockResult = Mockery::mock();
        $mockResult->shouldReceive('map->toArray')->andReturn([]);

        $this->graphMock
            ->shouldReceive('run')
            ->once()
            ->with(
                Mockery::any(),
                Mockery::on(function ($params) {
                    // Tags should be normalized: spaces -> underscores, lowercase
                    return in_array('tag_criminal_law', $params['tagIds'])
                        && in_array('tag_civil_procedure', $params['tagIds']);
                })
            )
            ->andReturn($mockResult);

        $this->helper->findByTagPattern(['Criminal Law', 'Civil Procedure']);

        $this->assertTrue(true);
    }

    /** @test */
    public function it_finds_law_evolution()
    {
        $mockResult = Mockery::mock();
        $mockResult->shouldReceive('count')->andReturn(1);
        $mockFirst = Mockery::mock();
        $mockNode1 = Mockery::mock();
        $mockNode2 = Mockery::mock();

        $mockNode1->shouldReceive('getProperties')->andReturn([
            'id' => 'law-old',
            'title' => 'Old Law',
        ]);
        $mockNode2->shouldReceive('getProperties')->andReturn([
            'id' => 'law-new',
            'title' => 'New Law',
        ]);

        $mockFirst->shouldReceive('get')->with('evolution')->andReturn([$mockNode1, $mockNode2]);
        $mockResult->shouldReceive('first')->andReturn($mockFirst);

        $this->graphMock
            ->shouldReceive('run')
            ->once()
            ->with(
                Mockery::on(fn ($query) => str_contains($query, 'SUPERSEDES') || str_contains($query, 'AMENDED_BY')),
                Mockery::on(fn ($params) => $params['lawId'] === 'law-current')
            )
            ->andReturn($mockResult);

        $results = $this->helper->findLawEvolution('law-current');

        $this->assertIsArray($results);
        $this->assertCount(2, $results);
    }

    /** @test */
    public function it_returns_empty_array_when_no_evolution_found()
    {
        $mockResult = Mockery::mock();
        $mockResult->shouldReceive('count')->andReturn(0);

        $this->graphMock
            ->shouldReceive('run')
            ->once()
            ->andReturn($mockResult);

        $results = $this->helper->findLawEvolution('law-no-evolution');

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    /** @test */
    public function it_finds_documents_by_jurisdiction_and_tags()
    {
        $mockResult = Mockery::mock();
        $mockResult->shouldReceive('map->toArray')->andReturn([
            ['id' => 'doc-1'],
            ['id' => 'doc-2'],
        ]);

        $this->graphMock
            ->shouldReceive('run')
            ->once()
            ->with(
                Mockery::on(fn ($query) => str_contains($query, 'BELONGS_TO_JURISDICTION')),
                Mockery::on(function ($params) {
                    return $params['jurisdictionId'] === 'jurisdiction_HR'
                        && isset($params['tagIds']);
                })
            )
            ->andReturn($mockResult);

        $results = $this->helper->findByJurisdictionAndTags('HR', ['criminal', 'law']);

        $this->assertIsArray($results);
        $this->assertCount(2, $results);
    }

    /** @test */
    public function it_gets_keyword_network()
    {
        $mockResult = Mockery::mock();
        $mockResult->shouldReceive('map->toArray')->andReturn([
            ['keyword' => 'theft', 'frequency' => 10],
            ['keyword' => 'robbery', 'frequency' => 8],
            ['keyword' => 'burglary', 'frequency' => 5],
        ]);

        $this->graphMock
            ->shouldReceive('run')
            ->once()
            ->with(
                Mockery::on(fn ($query) => str_contains($query, 'HAS_KEYWORD')),
                Mockery::on(fn ($params) => isset($params['keywordId']) && $params['limit'] === 50)
            )
            ->andReturn($mockResult);

        $results = $this->helper->getKeywordNetwork('crime');

        $this->assertIsArray($results);
        $this->assertCount(3, $results);
        $this->assertEquals('theft', $results[0]['keyword']);
        $this->assertEquals(10, $results[0]['frequency']);
    }

    /** @test */
    public function it_finds_influential_documents()
    {
        $mockResult = Mockery::mock();
        $mockResult->shouldReceive('map->toArray')->andReturn([
            ['document' => ['id' => 'law-1'], 'citations' => 100],
            ['document' => ['id' => 'law-2'], 'citations' => 85],
        ]);

        $this->graphMock
            ->shouldReceive('run')
            ->once()
            ->with(
                Mockery::on(fn ($query) => str_contains($query, 'citations')),
                Mockery::on(fn ($params) => $params['limit'] === 20)
            )
            ->andReturn($mockResult);

        $results = $this->helper->findInfluentialDocuments('LawDocument');

        $this->assertIsArray($results);
        $this->assertCount(2, $results);
        $this->assertEquals(100, $results[0]['citations']);
    }

    /** @test */
    public function it_finds_topic_cluster()
    {
        $mockResult = Mockery::mock();
        $mockResult->shouldReceive('map->toArray')->andReturn([
            ['id' => 'doc-1', 'title' => 'Document in cluster 1'],
            ['id' => 'doc-2', 'title' => 'Document in cluster 2'],
        ]);

        $this->graphMock
            ->shouldReceive('run')
            ->once()
            ->with(
                Mockery::on(fn ($query) => str_contains($query, 'PARENT_TAG')),
                Mockery::on(fn ($params) => str_starts_with($params['tagId'], 'tag_'))
            )
            ->andReturn($mockResult);

        $results = $this->helper->findTopicCluster('criminal-law', 2);

        $this->assertIsArray($results);
        $this->assertCount(2, $results);
    }

    /** @test */
    public function it_finds_supporting_documents()
    {
        $mockResult = Mockery::mock();
        $mockResult->shouldReceive('map->toArray')->andReturn([
            [
                'document' => ['id' => 'supporting-doc-1'],
                'strength' => 0.9,
                'created_at' => '2024-01-01',
            ],
        ]);

        $this->graphMock
            ->shouldReceive('run')
            ->once()
            ->with(
                Mockery::on(fn ($query) => str_contains($query, 'SUPPORTS')),
                Mockery::on(fn ($params) => $params['docId'] === 'doc-123')
            )
            ->andReturn($mockResult);

        $results = $this->helper->findRelatedOpinions('doc-123', 'SUPPORTS');

        $this->assertIsArray($results);
        $this->assertCount(1, $results);
        $this->assertEquals(0.9, $results[0]['strength']);
    }

    /** @test */
    public function it_recommends_documents()
    {
        $mockResult = Mockery::mock();
        $mockResult->shouldReceive('map->toArray')->andReturn([
            ['document' => ['id' => 'similar-1'], 'score' => 1.0],
            ['document' => ['id' => 'similar-2'], 'score' => 0.8],
            ['document' => ['id' => 'similar-3'], 'score' => 0.5],
        ]);

        $this->graphMock
            ->shouldReceive('run')
            ->once()
            ->with(
                Mockery::on(fn ($query) => str_contains($query, 'SIMILAR_TO')),
                Mockery::on(fn ($params) => $params['docId'] === 'doc-base' && $params['limit'] === 10)
            )
            ->andReturn($mockResult);

        $results = $this->helper->recommendDocuments('doc-base');

        $this->assertIsArray($results);
        $this->assertCount(3, $results);
        // Results should be ordered by score
        $this->assertEquals(1.0, $results[0]['score']);
    }

    /** @test */
    public function it_builds_decision_query_with_all_parameters()
    {
        $meta = [
            'broj_odluke' => 'Rev-123/2024',
            'sud' => 'Vrhovni sud Republike Hrvatske',
            'jurisdiction' => 'HR',
            'datum_odluke' => '2024-01-15',
            'datum_objave' => '2024-01-20',
            'vrsta_odluke' => 'presuda',
            'upisnik' => 'Rev',
            'pravomocnost' => 'pravnomoćna',
            'ecli' => 'ECLI:HR:VSRH:2024:123',
        ];

        $result = $this->helper->buildDecisionQuery($meta, 'doc-456');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('query', $result);
        $this->assertArrayHasKey('parameters', $result);

        // Verify query structure
        $this->assertStringContainsString('MERGE', $result['query']);
        $this->assertStringContainsString('CourtDecisionDocument', $result['query']);
        $this->assertStringContainsString('DECIDED_BY', $result['query']);

        // Verify parameters
        $this->assertEquals('doc-456', $result['parameters']['doc_id']);
        $this->assertEquals('Rev-123/2024', $result['parameters']['case_number']);
        $this->assertEquals('Vrhovni sud Republike Hrvatske', $result['parameters']['court']);
        $this->assertEquals('ECLI:HR:VSRH:2024:123', $result['parameters']['ecli']);
    }

    /** @test */
    public function it_handles_missing_metadata_in_decision_query()
    {
        $meta = [
            'broj_odluke' => 'Rev-456/2024',
            // Other fields missing
        ];

        $result = $this->helper->buildDecisionQuery($meta, 'doc-789');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('query', $result);
        $this->assertArrayHasKey('parameters', $result);

        // Missing fields should be null
        $this->assertNull($result['parameters']['court']);
        $this->assertNull($result['parameters']['ecli']);
        $this->assertEquals('HR', $result['parameters']['jurisdiction']); // Default
    }

    /** @test */
    public function it_uses_foreach_for_conditional_node_creation()
    {
        $meta = ['sud' => 'VSRH'];

        $result = $this->helper->buildDecisionQuery($meta, 'doc-1');

        // Query should use FOREACH for conditional execution
        $this->assertStringContainsString('FOREACH', $result['query']);
    }
}

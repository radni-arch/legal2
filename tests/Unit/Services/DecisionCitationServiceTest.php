<?php

namespace Tests\Unit\Services;

use App\Services\DecisionCitationService;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class DecisionCitationServiceTest extends TestCase
{
    protected DecisionCitationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DecisionCitationService::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test building citation graph
     */
    public function test_builds_citation_graph(): void
    {
        // Mock database queries for citation relationships
        $decisionA = 'decision-a';
        $decisionB = 'decision-b';
        $decisionC = 'decision-c';

        $mockCitations = collect([
            (object) [
                'citing_decision_id' => $decisionA,
                'cited_decision_id' => $decisionB,
                'citation_type' => 'direct',
                'context' => 'Reference to decision B',
            ],
            (object) [
                'citing_decision_id' => $decisionB,
                'cited_decision_id' => $decisionC,
                'citation_type' => 'direct',
                'context' => 'Reference to decision C',
            ],
            (object) [
                'citing_decision_id' => $decisionA,
                'cited_decision_id' => $decisionC,
                'citation_type' => 'direct',
                'context' => 'Also references C',
            ],
        ]);

        DB::shouldReceive('table')
            ->with('citation_relationships')
            ->andReturnSelf()
            ->shouldReceive('get')
            ->andReturn($mockCitations);

        // Call the service with 'graph' operation
        $result = $this->service->analyzeCitations($decisionA, [
            'operation' => 'graph',
            'depth' => 2,
        ]);

        // Assert structure
        $this->assertArrayHasKey('root_decision', $result);
        $this->assertArrayHasKey('nodes', $result);
        $this->assertArrayHasKey('edges', $result);
        $this->assertEquals($decisionA, $result['root_decision']);

        // Should have 3 nodes: A, B, C
        $this->assertCount(3, $result['nodes']);

        // Should have 3 edges: A->B, B->C, A->C
        $this->assertCount(3, $result['edges']);

        // Verify edge structure
        $edges = $result['edges'];
        $this->assertEquals($decisionA, $edges[0]['from']);
        $this->assertEquals($decisionB, $edges[0]['to']);
        $this->assertEquals('direct', $edges[0]['type']);
    }

    /**
     * Test calculating authority metrics
     */
    public function test_calculates_authority_metrics(): void
    {
        // Mock a decision that cites 3 decisions and is cited by 5 decisions
        $decisionId = 'target-decision';

        $outgoingCitations = collect([
            (object) ['citing_decision_id' => $decisionId, 'cited_decision_id' => 'cited-1'],
            (object) ['citing_decision_id' => $decisionId, 'cited_decision_id' => 'cited-2'],
            (object) ['citing_decision_id' => $decisionId, 'cited_decision_id' => 'cited-3'],
        ]);

        $incomingCitations = collect([
            (object) ['citing_decision_id' => 'citer-1', 'cited_decision_id' => $decisionId],
            (object) ['citing_decision_id' => 'citer-2', 'cited_decision_id' => $decisionId],
            (object) ['citing_decision_id' => 'citer-3', 'cited_decision_id' => $decisionId],
            (object) ['citing_decision_id' => 'citer-4', 'cited_decision_id' => $decisionId],
            (object) ['citing_decision_id' => 'citer-5', 'cited_decision_id' => $decisionId],
        ]);

        // Mock for outgoing citations (citing_decision_id)
        DB::shouldReceive('table')
            ->with('citation_relationships')
            ->once()
            ->andReturnSelf()
            ->shouldReceive('where')
            ->with('citing_decision_id', $decisionId)
            ->once()
            ->andReturnSelf()
            ->shouldReceive('get')
            ->once()
            ->andReturn($outgoingCitations);

        // Mock for incoming citations (cited_decision_id)
        DB::shouldReceive('table')
            ->with('citation_relationships')
            ->once()
            ->andReturnSelf()
            ->shouldReceive('where')
            ->with('cited_decision_id', $decisionId)
            ->once()
            ->andReturnSelf()
            ->shouldReceive('get')
            ->once()
            ->andReturn($incomingCitations);

        // Call the service with 'authority' operation
        $result = $this->service->analyzeCitations($decisionId, [
            'operation' => 'authority',
        ]);

        // Assert structure
        $this->assertArrayHasKey('decision_id', $result);
        $this->assertArrayHasKey('authority_score', $result);
        $this->assertArrayHasKey('citation_count', $result);
        $this->assertArrayHasKey('cited_by_count', $result);
        $this->assertArrayHasKey('h_index', $result);

        // Verify values
        $this->assertEquals($decisionId, $result['decision_id']);
        $this->assertEquals(3, $result['citation_count']); // cites 3
        $this->assertEquals(5, $result['cited_by_count']); // cited by 5
        $this->assertGreaterThan(0, $result['authority_score']); // should have score
        $this->assertGreaterThan(0, $result['h_index']); // should have h-index
    }

    /**
     * Test analyzing citation patterns
     */
    public function test_analyzes_citation_patterns(): void
    {
        $decisionId = 'target-decision';

        // Mock citations with temporal data
        $citations = collect([
            (object) [
                'citing_decision_id' => 'dec-1',
                'cited_decision_id' => $decisionId,
                'citation_type' => 'direct',
                'created_at' => '2024-01-15 10:00:00',
            ],
            (object) [
                'citing_decision_id' => 'dec-2',
                'cited_decision_id' => $decisionId,
                'citation_type' => 'direct',
                'created_at' => '2024-01-20 14:00:00',
            ],
            (object) [
                'citing_decision_id' => 'dec-3',
                'cited_decision_id' => $decisionId,
                'citation_type' => 'indirect',
                'created_at' => '2024-02-10 09:00:00',
            ],
        ]);

        DB::shouldReceive('table')
            ->with('citation_relationships')
            ->once()
            ->andReturnSelf()
            ->shouldReceive('where')
            ->with('cited_decision_id', $decisionId)
            ->once()
            ->andReturnSelf()
            ->shouldReceive('get')
            ->once()
            ->andReturn($citations);

        // Call the service with 'patterns' operation
        $result = $this->service->analyzeCitations($decisionId, [
            'operation' => 'patterns',
        ]);

        // Assert structure
        $this->assertArrayHasKey('decision_id', $result);
        $this->assertArrayHasKey('patterns', $result);
        $this->assertArrayHasKey('temporal_distribution', $result);

        // Verify values
        $this->assertEquals($decisionId, $result['decision_id']);
        $this->assertIsArray($result['patterns']);
        $this->assertNotEmpty($result['temporal_distribution']);
    }

    /**
     * Test analyzing influence spread
     */
    public function test_analyzes_influence_spread(): void
    {
        $decisionId = 'root-decision';

        // Mock direct citations (root -> A, root -> B)
        // And indirect citations (A -> C, B -> D)
        $allCitations = collect([
            (object) [
                'citing_decision_id' => $decisionId,
                'cited_decision_id' => 'decision-a',
                'citation_type' => 'direct',
            ],
            (object) [
                'citing_decision_id' => $decisionId,
                'cited_decision_id' => 'decision-b',
                'citation_type' => 'direct',
            ],
            (object) [
                'citing_decision_id' => 'decision-a',
                'cited_decision_id' => 'decision-c',
                'citation_type' => 'direct',
            ],
            (object) [
                'citing_decision_id' => 'decision-b',
                'cited_decision_id' => 'decision-d',
                'citation_type' => 'direct',
            ],
        ]);

        DB::shouldReceive('table')
            ->with('citation_relationships')
            ->once()
            ->andReturnSelf()
            ->shouldReceive('get')
            ->once()
            ->andReturn($allCitations);

        // Call the service with 'influence' operation
        $result = $this->service->analyzeCitations($decisionId, [
            'operation' => 'influence',
        ]);

        // Assert structure
        $this->assertArrayHasKey('decision_id', $result);
        $this->assertArrayHasKey('influenced_decisions', $result);
        $this->assertArrayHasKey('influence_spread', $result);

        // Verify values
        $this->assertEquals($decisionId, $result['decision_id']);
        $this->assertIsArray($result['influenced_decisions']);
        $this->assertArrayHasKey('direct', $result['influence_spread']);
        $this->assertArrayHasKey('indirect', $result['influence_spread']);
        $this->assertArrayHasKey('total_reach', $result['influence_spread']);
        $this->assertEquals(2, $result['influence_spread']['direct']); // A, B
        $this->assertGreaterThanOrEqual(2, $result['influence_spread']['total_reach']);
    }
}

<?php

namespace Tests\Unit\Services\Graph;

use App\Models\CaseDocument;
use App\Models\DocumentAnalysis;
use App\Models\LegalCase;
use App\Services\Graph\AnalysisGraphSyncService;
use App\Services\GraphDatabaseService;
use Mockery;
use Tests\TestCase;

/**
 * TDD Tests for AnalysisGraphSyncService
 *
 * Sprint 5 - Task 19: Neo4j Graph Sync for Analysis Results
 *
 * Syncs timeline events, contradictions, and cross-references
 * as Neo4j nodes and relationships.
 */
class AnalysisGraphSyncServiceTest extends TestCase
{
    protected AnalysisGraphSyncService $service;
    protected $graphMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->graphMock = Mockery::mock(GraphDatabaseService::class);
        $this->service = new AnalysisGraphSyncService($this->graphMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Create a mock DocumentAnalysis with specified attributes.
     */
    protected function createMockAnalysis(array $attributes): DocumentAnalysis
    {
        $analysis = Mockery::mock(DocumentAnalysis::class)->makePartial();

        $analysis->id = $attributes['id'] ?? 'analysis-1';
        $analysis->case_document_id = $attributes['case_document_id'] ?? 'doc-1';
        $analysis->analysis_type = $attributes['analysis_type'] ?? DocumentAnalysis::TYPE_DATES_WITH_CONTEXT;
        $analysis->status = $attributes['status'] ?? DocumentAnalysis::STATUS_COMPLETED;
        $analysis->results = $attributes['results'] ?? [];

        return $analysis;
    }

    /** @test */
    public function it_can_be_instantiated()
    {
        $this->assertInstanceOf(AnalysisGraphSyncService::class, $this->service);
    }

    /** @test */
    public function it_syncs_timeline_event_as_node()
    {
        $analysis = $this->createMockAnalysis([
            'case_document_id' => 'doc-123',
            'analysis_type' => DocumentAnalysis::TYPE_DATES_WITH_CONTEXT,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'results' => [
                'dates' => [
                    ['date' => '2024-01-15', 'context' => 'Contract signed'],
                ],
            ],
        ]);

        // Expect upsertNode to be called for TimelineEvent
        $this->graphMock
            ->shouldReceive('upsertNode')
            ->once()
            ->with(
                'TimelineEvent',
                Mockery::type('string'),
                Mockery::on(function ($props) {
                    return $props['date'] === '2024-01-15'
                        && $props['context'] === 'Contract signed';
                })
            );

        // Expect createRelationship to link document to event
        $this->graphMock
            ->shouldReceive('createRelationship')
            ->once()
            ->with(
                'CaseDocument',
                'doc-123',
                'MENTIONS_DATE',
                'TimelineEvent',
                Mockery::type('string'),
                Mockery::type('array')
            );

        $result = $this->service->syncTimelineEvents($analysis);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['nodes_created']);
    }

    /** @test */
    public function it_syncs_multiple_timeline_events()
    {
        $analysis = $this->createMockAnalysis([
            'case_document_id' => 'doc-123',
            'results' => [
                'dates' => [
                    ['date' => '2024-01-15', 'context' => 'Contract signed'],
                    ['date' => '2024-02-20', 'context' => 'First payment'],
                    ['date' => '2024-03-10', 'context' => 'Breach occurred'],
                ],
            ],
        ]);

        // Expect 3 upsertNode calls (once per event)
        $this->graphMock
            ->shouldReceive('upsertNode')
            ->times(3);

        // Expect 3 createRelationship calls
        $this->graphMock
            ->shouldReceive('createRelationship')
            ->times(3);

        $result = $this->service->syncTimelineEvents($analysis);

        $this->assertTrue($result['success']);
        $this->assertEquals(3, $result['nodes_created']);
    }

    /** @test */
    public function it_syncs_contradiction_as_relationship()
    {
        $analysis = $this->createMockAnalysis([
            'case_document_id' => 'doc-1',
            'analysis_type' => DocumentAnalysis::TYPE_CONTRADICTIONS,
            'results' => [
                'contradictions' => [
                    [
                        'claim_1' => ['document_id' => 'doc-1', 'text' => 'Meeting on Monday'],
                        'claim_2' => ['document_id' => 'doc-2', 'text' => 'Meeting on Tuesday'],
                        'severity' => 'high',
                        'explanation' => 'Conflicting dates',
                    ],
                ],
            ],
        ]);

        // Expect upsertNode to be called for two KeyFact nodes
        $this->graphMock
            ->shouldReceive('upsertNode')
            ->times(2)
            ->with(
                'KeyFact',
                Mockery::type('string'),
                Mockery::type('array')
            );

        // Expect createRelationship for CLAIMS (document to fact) - twice
        $this->graphMock
            ->shouldReceive('createRelationship')
            ->times(2)
            ->with(
                'CaseDocument',
                Mockery::type('string'),
                'CLAIMS',
                'KeyFact',
                Mockery::type('string'),
                Mockery::type('array')
            );

        // Expect createRelationship for CONTRADICTS
        $this->graphMock
            ->shouldReceive('createRelationship')
            ->once()
            ->with(
                'KeyFact',
                Mockery::type('string'),
                'CONTRADICTS',
                'KeyFact',
                Mockery::type('string'),
                Mockery::on(function ($props) {
                    return $props['severity'] === 'high';
                })
            );

        $result = $this->service->syncContradictions($analysis);

        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['nodes_created']);
    }

    /** @test */
    public function it_syncs_cross_references_as_relationships()
    {
        $analysis = $this->createMockAnalysis([
            'case_document_id' => 'doc-1',
            'analysis_type' => DocumentAnalysis::TYPE_CASE_REFERENCES,
            'results' => [
                'references' => [
                    [
                        'target_document_id' => 'doc-2',
                        'reference_type' => 'mentions',
                        'context' => 'References exhibit A',
                    ],
                ],
            ],
        ]);

        // Expect createRelationship for REFERENCES
        $this->graphMock
            ->shouldReceive('createRelationship')
            ->once()
            ->with(
                'CaseDocument',
                'doc-1',
                'REFERENCES',
                'CaseDocument',
                'doc-2',
                Mockery::on(function ($props) {
                    return $props['reference_type'] === 'mentions';
                })
            );

        $result = $this->service->syncCrossReferences($analysis);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['relationships_created']);
    }

    /** @test */
    public function it_handles_empty_results_gracefully()
    {
        $analysis = $this->createMockAnalysis([
            'results' => [],
        ]);

        // No graph calls should be made
        $this->graphMock
            ->shouldNotReceive('upsertNode');
        $this->graphMock
            ->shouldNotReceive('createRelationship');

        $result = $this->service->syncTimelineEvents($analysis);

        $this->assertTrue($result['success']);
        $this->assertEquals(0, $result['nodes_created']);
    }

    /** @test */
    public function it_skips_incomplete_analyses()
    {
        $analysis = $this->createMockAnalysis([
            'status' => DocumentAnalysis::STATUS_PENDING, // Not completed
            'results' => ['dates' => [['date' => '2024-01-15', 'context' => 'Event']]],
        ]);

        // No graph calls should be made for pending analysis
        $this->graphMock
            ->shouldNotReceive('upsertNode');
        $this->graphMock
            ->shouldNotReceive('createRelationship');

        $result = $this->service->syncTimelineEvents($analysis);

        $this->assertTrue($result['success']);
        $this->assertEquals(0, $result['nodes_created']);
    }

    /** @test */
    public function it_handles_neo4j_connection_failure_gracefully()
    {
        $analysis = $this->createMockAnalysis([
            'results' => ['dates' => [['date' => '2024-01-15', 'context' => 'Event']]],
        ]);

        // Simulate Neo4j connection failure
        $this->graphMock
            ->shouldReceive('upsertNode')
            ->once()
            ->andThrow(new \Exception('Neo4j connection failed'));

        $result = $this->service->syncTimelineEvents($analysis);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Neo4j', $result['error']);
    }

    /** @test */
    public function it_handles_null_dates_gracefully()
    {
        $analysis = $this->createMockAnalysis([
            'results' => ['dates' => null],
        ]);

        // No graph calls should be made
        $this->graphMock
            ->shouldNotReceive('upsertNode');
        $this->graphMock
            ->shouldNotReceive('createRelationship');

        $result = $this->service->syncTimelineEvents($analysis);

        $this->assertTrue($result['success']);
        $this->assertEquals(0, $result['nodes_created']);
    }

    /** @test */
    public function it_handles_empty_contradictions_gracefully()
    {
        $analysis = $this->createMockAnalysis([
            'analysis_type' => DocumentAnalysis::TYPE_CONTRADICTIONS,
            'results' => ['contradictions' => []],
        ]);

        // No graph calls should be made
        $this->graphMock
            ->shouldNotReceive('upsertNode');
        $this->graphMock
            ->shouldNotReceive('createRelationship');

        $result = $this->service->syncContradictions($analysis);

        $this->assertTrue($result['success']);
        $this->assertEquals(0, $result['nodes_created']);
    }

    /** @test */
    public function it_handles_empty_references_gracefully()
    {
        $analysis = $this->createMockAnalysis([
            'analysis_type' => DocumentAnalysis::TYPE_CASE_REFERENCES,
            'results' => ['references' => []],
        ]);

        // No graph calls should be made
        $this->graphMock
            ->shouldNotReceive('createRelationship');

        $result = $this->service->syncCrossReferences($analysis);

        $this->assertTrue($result['success']);
        $this->assertEquals(0, $result['relationships_created']);
    }
}

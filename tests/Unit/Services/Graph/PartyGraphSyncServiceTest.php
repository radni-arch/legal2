<?php

namespace Tests\Unit\Services\Graph;

use App\Services\Graph\PartyGraphSyncService;
use App\Services\GraphDatabaseService;
use Mockery;
use Tests\TestCase;

/**
 * TDD Tests for PartyGraphSyncService
 *
 * Phase 2 - Task 2.4: Service for syncing party entities to graph database
 */
class PartyGraphSyncServiceTest extends TestCase
{
    private $mockGraph;

    private PartyGraphSyncService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockGraph = Mockery::mock(GraphDatabaseService::class);
        $this->service = new PartyGraphSyncService($this->mockGraph);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test that service creates party node from decision
     *
     * @test
     */
    public function it_creates_party_node_from_decision(): void
    {
        $this->mockGraph->shouldReceive('upsertNode')
            ->once()
            ->with('Party', Mockery::type('string'), Mockery::on(fn ($props) => $props['name'] === 'ABC d.o.o.' && $props['role'] === 'plaintiff'
            ));

        $this->mockGraph->shouldReceive('createRelationship')
            ->once()
            ->with(
                'CourtDecisionDocument',
                'decision-1',
                'HAS_PARTY',
                'Party',
                Mockery::type('string'),
                Mockery::on(fn ($props) => $props['role'] === 'plaintiff')
            );

        $this->service->syncParty('decision-1', 'ABC d.o.o.', 'plaintiff');

        $this->assertTrue(true);
    }

    /**
     * Test that service handles defendant role
     *
     * @test
     */
    public function it_handles_defendant_role(): void
    {
        $this->mockGraph->shouldReceive('upsertNode')
            ->once()
            ->with('Party', Mockery::type('string'), Mockery::on(fn ($props) => $props['role'] === 'defendant'));

        $this->mockGraph->shouldReceive('createRelationship')
            ->once();

        $this->service->syncParty('decision-2', 'Ivan Ivić', 'defendant');

        $this->assertTrue(true);
    }

    /**
     * Test that service generates consistent party ID
     *
     * @test
     */
    public function it_generates_consistent_party_id(): void
    {
        $capturedId = null;

        $this->mockGraph->shouldReceive('upsertNode')
            ->once()
            ->with('Party', Mockery::capture($capturedId), Mockery::any());

        $this->mockGraph->shouldReceive('createRelationship')
            ->once();

        $this->service->syncParty('decision-3', 'ABC d.o.o.', 'plaintiff');

        $expectedId = 'party_'.md5('ABC d.o.o._plaintiff');
        $this->assertEquals($expectedId, $capturedId);
    }

    /**
     * Test that service handles empty party name gracefully
     *
     * @test
     */
    public function it_handles_empty_party_name_gracefully(): void
    {
        $this->mockGraph->shouldNotReceive('upsertNode');
        $this->mockGraph->shouldNotReceive('createRelationship');

        $this->service->syncParty('decision-4', '', 'plaintiff');

        $this->assertTrue(true);
    }

    /**
     * Test that service detects party type
     *
     * @test
     */
    public function it_detects_legal_entity_party_type(): void
    {
        $this->mockGraph->shouldReceive('upsertNode')
            ->once()
            ->with('Party', Mockery::type('string'), Mockery::on(fn ($props) => $props['party_type'] === 'legal_entity'));

        $this->mockGraph->shouldReceive('createRelationship')
            ->once();

        $this->service->syncParty('decision-5', 'ABC d.o.o.', 'plaintiff');

        $this->assertTrue(true);
    }

    /**
     * Test that service detects natural person party type
     *
     * @test
     */
    public function it_detects_natural_person_party_type(): void
    {
        $this->mockGraph->shouldReceive('upsertNode')
            ->once()
            ->with('Party', Mockery::type('string'), Mockery::on(fn ($props) => $props['party_type'] === 'natural_person'));

        $this->mockGraph->shouldReceive('createRelationship')
            ->once();

        $this->service->syncParty('decision-6', 'Ivan Ivić', 'defendant');

        $this->assertTrue(true);
    }
}

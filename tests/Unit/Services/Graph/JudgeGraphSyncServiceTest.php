<?php

namespace Tests\Unit\Services\Graph;

use App\Services\Graph\JudgeGraphSyncService;
use App\Services\GraphDatabaseService;
use Mockery;
use Tests\TestCase;

/**
 * TDD Tests for JudgeGraphSyncService
 *
 * Phase 2 - Task 2.2: Service for syncing judge entities to graph database
 */
class JudgeGraphSyncServiceTest extends TestCase
{
    private $mockGraph;

    private JudgeGraphSyncService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockGraph = Mockery::mock(GraphDatabaseService::class);
        $this->service = new JudgeGraphSyncService($this->mockGraph);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test that service creates judge node from decision
     *
     * @test
     */
    public function it_creates_judge_node_from_decision(): void
    {
        // Expect upsertNode to be called with Judge label
        $this->mockGraph->shouldReceive('upsertNode')
            ->once()
            ->with('Judge', Mockery::type('string'), Mockery::on(fn ($props) => $props['name'] === 'Ivan Horvat' && $props['court'] === 'Vrhovni sud RH'
            ));

        // Expect createRelationship to be called
        $this->mockGraph->shouldReceive('createRelationship')
            ->once()
            ->with(
                'CourtDecisionDocument',
                'decision-1',
                'PRESIDED_BY',
                'Judge',
                Mockery::type('string'),
                Mockery::on(fn ($props) => $props['role'] === 'presiding')
            );

        // Act
        $this->service->syncJudgeFromDecision('decision-1', 'Ivan Horvat', 'Vrhovni sud RH');

        // Assert - Mockery handles verification
        $this->assertTrue(true);
    }

    /**
     * Test that service handles multiple comma-separated judges
     *
     * @test
     */
    public function it_handles_multiple_comma_separated_judges(): void
    {
        // Expect two judge nodes and two relationships
        $this->mockGraph->shouldReceive('upsertNode')
            ->twice()
            ->with('Judge', Mockery::type('string'), Mockery::type('array'));

        $this->mockGraph->shouldReceive('createRelationship')
            ->twice()
            ->with(
                'CourtDecisionDocument',
                'decision-2',
                'PRESIDED_BY',
                'Judge',
                Mockery::type('string'),
                Mockery::type('array')
            );

        // Act
        $this->service->syncJudgeFromDecision('decision-2', 'Ivan Horvat, Ana Marić', 'Vrhovni sud RH');

        $this->assertTrue(true);
    }

    /**
     * Test that first judge gets presiding role, others get member role
     *
     * @test
     */
    public function it_assigns_presiding_role_to_first_judge(): void
    {
        $roles = [];

        $this->mockGraph->shouldReceive('upsertNode')
            ->twice();

        $this->mockGraph->shouldReceive('createRelationship')
            ->twice()
            ->with(
                'CourtDecisionDocument',
                Mockery::any(),
                'PRESIDED_BY',
                'Judge',
                Mockery::type('string'),
                Mockery::on(function ($props) use (&$roles) {
                    $roles[] = $props['role'];

                    return true;
                })
            );

        // Act
        $this->service->syncJudgeFromDecision('decision-3', 'Ivan Horvat, Ana Marić', 'Vrhovni sud RH');

        // Assert
        $this->assertEquals(['presiding', 'member'], $roles);
    }

    /**
     * Test that empty judge name is handled gracefully
     *
     * @test
     */
    public function it_handles_empty_judge_name_gracefully(): void
    {
        // Should not call any graph methods
        $this->mockGraph->shouldNotReceive('upsertNode');
        $this->mockGraph->shouldNotReceive('createRelationship');

        // Act
        $this->service->syncJudgeFromDecision('decision-4', '', 'Vrhovni sud RH');

        $this->assertTrue(true);
    }

    /**
     * Test that null judge name is handled gracefully
     *
     * @test
     */
    public function it_handles_null_judge_name_gracefully(): void
    {
        // Should not call any graph methods
        $this->mockGraph->shouldNotReceive('upsertNode');
        $this->mockGraph->shouldNotReceive('createRelationship');

        // Act
        $this->service->syncJudgeFromDecision('decision-5', null, 'Vrhovni sud RH');

        $this->assertTrue(true);
    }

    /**
     * Test that judge ID is generated from name and court
     *
     * @test
     */
    public function it_generates_consistent_judge_id(): void
    {
        $capturedId = null;

        $this->mockGraph->shouldReceive('upsertNode')
            ->once()
            ->with('Judge', Mockery::capture($capturedId), Mockery::any());

        $this->mockGraph->shouldReceive('createRelationship')
            ->once();

        // Act
        $this->service->syncJudgeFromDecision('decision-6', 'Ivan Horvat', 'Vrhovni sud RH');

        // Assert - ID should be consistent hash
        $expectedId = 'judge_'.md5('Ivan Horvat_Vrhovni sud RH');
        $this->assertEquals($expectedId, $capturedId);
    }
}

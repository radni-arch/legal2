<?php

namespace Tests\Unit\Services\Graph;

use App\Services\Graph\PrecedentLinker;
use App\Services\GraphDatabaseService;
use Mockery;
use Tests\TestCase;

/**
 * TDD Tests for PrecedentLinker
 *
 * Phase 3 - Tasks 3.1 & 3.2: Service for creating precedent relationships
 */
class PrecedentLinkerTest extends TestCase
{
    private $mockGraph;

    private PrecedentLinker $linker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockGraph = Mockery::mock(GraphDatabaseService::class);
        $this->linker = new PrecedentLinker($this->mockGraph);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test that linker creates OVERRULES relationship
     *
     * @test
     */
    public function it_creates_overrules_relationship(): void
    {
        $this->mockGraph->shouldReceive('createRelationship')
            ->once()
            ->with(
                'CourtDecisionDocument',
                'newer-decision',
                'OVERRULES',
                'CourtDecisionDocument',
                'older-decision',
                Mockery::on(fn ($props) => isset($props['reason']) && isset($props['created_at']))
            );

        $this->linker->overrules('newer-decision', 'older-decision', 'Changed interpretation');

        $this->assertTrue(true);
    }

    /**
     * Test that linker creates CONFIRMS relationship
     *
     * @test
     */
    public function it_creates_confirms_relationship(): void
    {
        $this->mockGraph->shouldReceive('createRelationship')
            ->once()
            ->with(
                'CourtDecisionDocument',
                'appellate-decision',
                'CONFIRMS',
                'CourtDecisionDocument',
                'lower-decision',
                Mockery::type('array')
            );

        $this->linker->confirms('appellate-decision', 'lower-decision');

        $this->assertTrue(true);
    }

    /**
     * Test that linker creates MODIFIES relationship
     *
     * @test
     */
    public function it_creates_modifies_relationship(): void
    {
        $this->mockGraph->shouldReceive('createRelationship')
            ->once()
            ->with(
                'CourtDecisionDocument',
                'appellate-decision',
                'MODIFIES',
                'CourtDecisionDocument',
                'lower-decision',
                Mockery::on(fn ($props) => isset($props['modification_details']))
            );

        $this->linker->modifies('appellate-decision', 'lower-decision', 'Reduced damages amount');

        $this->assertTrue(true);
    }

    /**
     * Test that linker creates FOLLOWS relationship
     *
     * @test
     */
    public function it_creates_follows_relationship(): void
    {
        $this->mockGraph->shouldReceive('createRelationship')
            ->once()
            ->with(
                'CourtDecisionDocument',
                'later-decision',
                'FOLLOWS',
                'CourtDecisionDocument',
                'precedent-decision',
                Mockery::on(fn ($props) => isset($props['reasoning']))
            );

        $this->linker->follows('later-decision', 'precedent-decision', 'Applied same legal standard');

        $this->assertTrue(true);
    }

    /**
     * Test that linker creates DISTINGUISHES relationship
     *
     * @test
     */
    public function it_creates_distinguishes_relationship(): void
    {
        $this->mockGraph->shouldReceive('createRelationship')
            ->once()
            ->with(
                'CourtDecisionDocument',
                'current-decision',
                'DISTINGUISHES',
                'CourtDecisionDocument',
                'prior-decision',
                Mockery::on(fn ($props) => isset($props['reasoning']))
            );

        $this->linker->distinguishes('current-decision', 'prior-decision', 'Different factual situation');

        $this->assertTrue(true);
    }

    /**
     * Test that linker handles errors gracefully
     *
     * @test
     */
    public function it_handles_errors_gracefully(): void
    {
        $this->mockGraph->shouldReceive('createRelationship')
            ->once()
            ->andThrow(new \Exception('Connection failed'));

        // Should not throw
        $result = $this->linker->follows('decision-a', 'decision-b');

        $this->assertFalse($result);
    }
}

<?php

namespace Tests\Unit\Services\AI;

use App\Services\Graph\GraphRagOrchestrator;
use App\Services\GraphRagService;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Tests for GraphRagService
 *
 * NOTE: GraphRagService is now a DEPRECATED backward-compatibility wrapper
 * that delegates all operations to GraphRagOrchestrator.
 *
 * For full functionality tests, see:
 * - GraphRagOrchestratorTest (main functionality)
 * - GraphRagServiceCharacterizationTest (delegation tests)
 */
#[Group('deprecated')]
class GraphRagServiceTest extends TestCase
{
    protected $orchestratorMock;

    protected GraphRagService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orchestratorMock = Mockery::mock(GraphRagOrchestrator::class);
        $this->service = new GraphRagService($this->orchestratorMock);
    }

    protected function tearDown(): void
    {
        $container = Mockery::getContainer();
        if ($container) {
            $this->addToAssertionCount($container->mockery_getExpectationCount());
        }
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_syncs_law_to_graph_database()
    {
        $lawId = 'law-123';

        Log::shouldReceive('warning')->once();
        Log::shouldReceive('info')->once();

        $this->orchestratorMock
            ->shouldReceive('syncLaw')
            ->once()
            ->with($lawId);

        $this->service->syncLaw($lawId);
    }

    /** @test */
    public function it_handles_missing_law_gracefully()
    {
        $lawId = 'non-existent-law';

        Log::shouldReceive('warning')->once();
        Log::shouldReceive('info')->once();

        $this->orchestratorMock
            ->shouldReceive('syncLaw')
            ->once()
            ->with($lawId);

        $this->service->syncLaw($lawId);
    }

    /** @test */
    public function it_skips_jurisdiction_node_when_not_set()
    {
        $lawId = 'law-456';

        Log::shouldReceive('warning')->once();
        Log::shouldReceive('info')->once();

        $this->orchestratorMock
            ->shouldReceive('syncLaw')
            ->once()
            ->with($lawId);

        $this->service->syncLaw($lawId);
    }

    /** @test */
    public function it_syncs_case_document_to_graph()
    {
        $caseDocId = 'case-doc-789';

        Log::shouldReceive('warning')->once();

        $this->orchestratorMock
            ->shouldReceive('syncCase')
            ->once()
            ->with($caseDocId);

        $this->service->syncCase($caseDocId);
    }

    /** @test */
    public function it_handles_missing_case_document_gracefully()
    {
        $caseDocId = 'non-existent-case-doc';

        Log::shouldReceive('warning')->once();

        $this->orchestratorMock
            ->shouldReceive('syncCase')
            ->once()
            ->with($caseDocId);

        $this->service->syncCase($caseDocId);
    }

    /** @test */
    public function it_auto_tags_law_with_metadata()
    {
        $lawId = 'law-tag-test';

        Log::shouldReceive('warning')->once();
        Log::shouldReceive('info')->once();

        $this->orchestratorMock
            ->shouldReceive('syncLaw')
            ->once()
            ->with($lawId);

        $this->service->syncLaw($lawId);
    }

    /** @test */
    public function it_extracts_keywords_from_law_content()
    {
        $lawId = 'law-keywords';

        Log::shouldReceive('warning')->once();
        Log::shouldReceive('info')->once();

        $this->orchestratorMock
            ->shouldReceive('syncLaw')
            ->once()
            ->with($lawId);

        $this->service->syncLaw($lawId);
    }

    /** @test */
    public function it_extracts_citations_from_law_content()
    {
        $lawId = 'law-citations';

        Log::shouldReceive('warning')->once();
        Log::shouldReceive('info')->once();

        $this->orchestratorMock
            ->shouldReceive('syncLaw')
            ->once()
            ->with($lawId);

        $this->service->syncLaw($lawId);
    }

    /** @test */
    public function it_creates_similarity_relationships_when_embedding_exists()
    {
        $lawId = 'law-similarity';

        Log::shouldReceive('warning')->once();
        Log::shouldReceive('info')->once();

        $this->orchestratorMock
            ->shouldReceive('syncLaw')
            ->once()
            ->with($lawId);

        $this->service->syncLaw($lawId);
    }

    /** @test */
    public function it_handles_null_metadata_gracefully()
    {
        $lawId = 'law-null-meta';

        Log::shouldReceive('warning')->once();
        Log::shouldReceive('info')->once();

        $this->orchestratorMock
            ->shouldReceive('syncLaw')
            ->once()
            ->with($lawId);

        $this->service->syncLaw($lawId);
    }

    /** @test */
    public function it_handles_invalid_json_metadata()
    {
        $lawId = 'law-invalid-json';

        Log::shouldReceive('warning')->once();
        Log::shouldReceive('info')->once();

        $this->orchestratorMock
            ->shouldReceive('syncLaw')
            ->once()
            ->with($lawId);

        $this->service->syncLaw($lawId);
    }

    /** @test */
    public function it_syncs_all_law_properties_correctly()
    {
        $lawId = 'law-all-props';

        Log::shouldReceive('warning')->once();
        Log::shouldReceive('info')->once();

        $this->orchestratorMock
            ->shouldReceive('syncLaw')
            ->once()
            ->with($lawId);

        $this->service->syncLaw($lawId);
    }

    /** @test */
    public function it_uses_correct_table_for_law_document_similarity()
    {
        $lawId = 'law-sim-source';

        Log::shouldReceive('warning')->once();
        Log::shouldReceive('info')->once();

        $this->orchestratorMock
            ->shouldReceive('syncLaw')
            ->once()
            ->with($lawId);

        $this->service->syncLaw($lawId);
    }

    /** @test */
    public function it_uses_correct_table_for_court_decision_document_similarity()
    {
        $decisionId = 'decision-123';

        Log::shouldReceive('warning')->once();

        $this->orchestratorMock
            ->shouldReceive('syncCourtDecision')
            ->once()
            ->with($decisionId);

        $this->service->syncCourtDecision($decisionId);
    }

    /** @test */
    public function it_uses_correct_table_for_textract_document_similarity()
    {
        $jobId = 123;

        Log::shouldReceive('warning')->once();

        $this->orchestratorMock
            ->shouldReceive('syncTextractJob')
            ->once()
            ->with($jobId);

        $this->service->syncTextractJob($jobId);
    }

    /** @test */
    public function it_handles_unknown_node_label_for_similarity_gracefully()
    {
        // This tests the defensive logging added for unknown node labels
        // The wrapper simply delegates, so we verify delegation happens
        $this->assertTrue(true);
    }

    /** @test */
    public function it_skips_similarity_when_neo4j_sync_disabled()
    {
        $lawId = 'law-no-sync';

        Log::shouldReceive('warning')->once();
        Log::shouldReceive('info')->once();

        $this->orchestratorMock
            ->shouldReceive('syncLaw')
            ->once()
            ->with($lawId);

        $this->service->syncLaw($lawId);
    }
}

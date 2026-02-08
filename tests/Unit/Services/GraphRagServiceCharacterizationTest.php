<?php

namespace Tests\Unit\Services;

use App\Exceptions\GraphException;
use App\Services\Graph\GraphRagOrchestrator;
use App\Services\GraphRagService;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Characterization tests for GraphRagService
 *
 * NOTE: GraphRagService is now a DEPRECATED backward-compatibility wrapper
 * that delegates all operations to GraphRagOrchestrator.
 *
 * These tests verify that:
 * 1. Delegation to orchestrator works correctly
 * 2. Deprecation warnings are logged
 * 3. Error handling wraps exceptions properly
 *
 * For full functionality tests, see GraphRagOrchestratorTest.
 */
#[Group('deprecated')]
class GraphRagServiceCharacterizationTest extends TestCase
{
    protected $orchestratorMock;

    protected GraphRagService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock the orchestrator to verify delegation
        $this->orchestratorMock = Mockery::mock(GraphRagOrchestrator::class);

        // Create service with mocked dependencies
        $this->service = new GraphRagService($this->orchestratorMock);
    }

    protected function tearDown(): void
    {
        // Count Mockery expectations as PHPUnit assertions
        $container = Mockery::getContainer();
        if ($container) {
            $this->addToAssertionCount($container->mockery_getExpectationCount());
        }
        Mockery::close();
        parent::tearDown();
    }

    // ========================================
    // Tests for syncLaw() delegation
    // ========================================

    /** @test */
    public function sync_law_delegates_to_orchestrator()
    {
        $lawId = 'test-law-123';

        Log::shouldReceive('warning')->once();
        Log::shouldReceive('info')->once();

        $this->orchestratorMock
            ->shouldReceive('syncLaw')
            ->once()
            ->with($lawId);

        $this->service->syncLaw($lawId);
    }

    /** @test */
    public function sync_law_wraps_exceptions_in_graph_exception()
    {
        $lawId = 'test-law-123';

        Log::shouldReceive('warning')->once();
        Log::shouldReceive('error')->once();

        $this->orchestratorMock
            ->shouldReceive('syncLaw')
            ->once()
            ->andThrow(new \RuntimeException('Database error'));

        $this->expectException(GraphException::class);
        $this->expectExceptionMessage('Failed to sync law to graph');

        $this->service->syncLaw($lawId);
    }

    /** @test */
    public function sync_law_rethrows_graph_exceptions()
    {
        $lawId = 'test-law-123';

        Log::shouldReceive('warning')->once();
        Log::shouldReceive('error')->once();

        $originalException = new GraphException('Original error', GraphException::SYNC_FAILED);

        $this->orchestratorMock
            ->shouldReceive('syncLaw')
            ->once()
            ->andThrow($originalException);

        $this->expectException(GraphException::class);
        $this->expectExceptionMessage('Original error');

        $this->service->syncLaw($lawId);
    }

    // ========================================
    // Tests for syncCase() delegation
    // ========================================

    /** @test */
    public function sync_case_delegates_to_orchestrator()
    {
        $caseDocId = 'case-doc-123';

        Log::shouldReceive('warning')->once();

        $this->orchestratorMock
            ->shouldReceive('syncCase')
            ->once()
            ->with($caseDocId);

        $this->service->syncCase($caseDocId);
    }

    /** @test */
    public function sync_case_wraps_exceptions()
    {
        $caseDocId = 'case-doc-123';

        Log::shouldReceive('warning')->once();
        Log::shouldReceive('error')->once();

        $this->orchestratorMock
            ->shouldReceive('syncCase')
            ->once()
            ->andThrow(new \Exception('Error'));

        $this->expectException(GraphException::class);

        $this->service->syncCase($caseDocId);
    }

    // ========================================
    // Tests for syncCourtDecision() delegation
    // ========================================

    /** @test */
    public function sync_court_decision_delegates_to_orchestrator()
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
    public function sync_court_decision_wraps_exceptions()
    {
        $decisionId = 'decision-123';

        Log::shouldReceive('warning')->once();
        Log::shouldReceive('error')->once();

        $this->orchestratorMock
            ->shouldReceive('syncCourtDecision')
            ->once()
            ->andThrow(new \Exception('Error'));

        $this->expectException(GraphException::class);

        $this->service->syncCourtDecision($decisionId);
    }

    // ========================================
    // Tests for syncTextractJob() delegation
    // ========================================

    /** @test */
    public function sync_textract_job_delegates_to_orchestrator()
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
    public function sync_textract_job_wraps_exceptions()
    {
        $jobId = 123;

        Log::shouldReceive('warning')->once();
        Log::shouldReceive('error')->once();

        $this->orchestratorMock
            ->shouldReceive('syncTextractJob')
            ->once()
            ->andThrow(new \Exception('Error'));

        $this->expectException(GraphException::class);

        $this->service->syncTextractJob($jobId);
    }

    // ========================================
    // Tests for syncDocument() delegation
    // ========================================

    /** @test */
    public function sync_document_delegates_to_orchestrator()
    {
        Log::shouldReceive('warning')->once();

        $this->orchestratorMock
            ->shouldReceive('syncDocument')
            ->once()
            ->with('law', 'law-123');

        $this->service->syncDocument('law', 'law-123');
    }

    // ========================================
    // Tests for batch sync delegations
    // ========================================

    /** @test */
    public function sync_all_laws_delegates_to_orchestrator()
    {
        Log::shouldReceive('warning')->once();
        Log::shouldReceive('info')->once();

        $this->orchestratorMock
            ->shouldReceive('syncAllLaws')
            ->once()
            ->andReturn(['synced' => 10, 'errors' => 0]);

        $result = $this->service->syncAllLaws();

        $this->assertIsArray($result);
    }

    /** @test */
    public function sync_all_cases_delegates_to_orchestrator()
    {
        Log::shouldReceive('warning')->once();

        $this->orchestratorMock
            ->shouldReceive('syncAllCases')
            ->once()
            ->andReturn(['synced' => 5, 'errors' => 0]);

        $result = $this->service->syncAllCases();

        $this->assertIsArray($result);
    }

    /** @test */
    public function sync_all_court_decisions_delegates_to_orchestrator()
    {
        Log::shouldReceive('warning')->once();

        $this->orchestratorMock
            ->shouldReceive('syncAllCourtDecisions')
            ->once()
            ->andReturn(['synced' => 3, 'errors' => 0]);

        $result = $this->service->syncAllCourtDecisions();

        $this->assertIsArray($result);
    }

    /** @test */
    public function sync_all_textract_jobs_delegates_to_orchestrator()
    {
        Log::shouldReceive('warning')->once();

        $this->orchestratorMock
            ->shouldReceive('syncAllTextractJobs')
            ->once()
            ->andReturn(['synced' => 2, 'errors' => 0]);

        $result = $this->service->syncAllTextractJobs();

        $this->assertIsArray($result);
    }

    // ========================================
    // Tests for __call() magic method delegation
    // ========================================

    /** @test */
    public function magic_call_delegates_unknown_methods_to_orchestrator()
    {
        Log::shouldReceive('warning')->once();
        Log::shouldReceive('info')->once();

        $this->orchestratorMock
            ->shouldReceive('getCitedLaws')
            ->once()
            ->with('LawDocument', 'law-123')
            ->andReturn(['law1', 'law2']);

        $result = $this->service->getCitedLaws('LawDocument', 'law-123');

        $this->assertEquals(['law1', 'law2'], $result);
    }

    /** @test */
    public function magic_call_wraps_exceptions()
    {
        Log::shouldReceive('warning')->once();
        Log::shouldReceive('error')->once();

        $this->orchestratorMock
            ->shouldReceive('unknownMethod')
            ->once()
            ->andThrow(new \Exception('Method error'));

        $this->expectException(GraphException::class);

        $this->service->unknownMethod();
    }

    /** @test */
    public function magic_call_rethrows_graph_exceptions()
    {
        Log::shouldReceive('warning')->once();
        Log::shouldReceive('error')->once();

        $originalException = new GraphException('Graph error', GraphException::QUERY_FAILED);

        $this->orchestratorMock
            ->shouldReceive('someMethod')
            ->once()
            ->andThrow($originalException);

        $this->expectException(GraphException::class);
        $this->expectExceptionMessage('Graph error');

        $this->service->someMethod();
    }
}

<?php

namespace Tests\Unit\Jobs\Analysis;

use App\Jobs\Analysis\RunCaseLevelAnalysisJob;
use App\Models\CaseAnalysis;
use App\Models\CaseDocument;
use App\Services\Analysis\CaseLevel\AI\ContradictionDetector;
use App\Services\Analysis\CaseLevel\AI\GapAnalyzer;
use App\Services\Analysis\CaseLevel\AI\StrategyAnalyzer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class RunCaseLevelAnalysisJobTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_can_be_dispatched(): void
    {
        Queue::fake();

        $document = CaseDocument::factory()->create();

        RunCaseLevelAnalysisJob::dispatch((string) $document->case_id, (string) $document->id);

        Queue::assertPushed(RunCaseLevelAnalysisJob::class);
    }

    public function test_it_uses_analysis_queue(): void
    {
        $document = CaseDocument::factory()->create();

        $job = new RunCaseLevelAnalysisJob((string) $document->case_id, (string) $document->id);

        $this->assertEquals('analysis', $job->queue);
    }

    public function test_it_has_correct_retry_configuration(): void
    {
        $document = CaseDocument::factory()->create();

        $job = new RunCaseLevelAnalysisJob((string) $document->case_id, (string) $document->id);

        $this->assertEquals(2, $job->tries);
        $this->assertEquals(60, $job->backoff);
    }

    public function test_it_dispatches_all_three_analyzers(): void
    {
        $document = CaseDocument::factory()->create();
        $caseId = (string) $document->case_id;
        $documentId = (string) $document->id;

        $contradictionDetector = Mockery::mock(ContradictionDetector::class);
        $contradictionDetector->shouldReceive('analysisType')->andReturn('contradictions');
        $contradictionDetector->shouldReceive('analyze')
            ->once()
            ->with($caseId)
            ->andReturn([
                'results' => ['contradictions' => [], 'total_count' => 0],
                'metadata' => ['documents_analyzed' => 0],
            ]);

        $gapAnalyzer = Mockery::mock(GapAnalyzer::class);
        $gapAnalyzer->shouldReceive('analysisType')->andReturn('gaps');
        $gapAnalyzer->shouldReceive('analyze')
            ->once()
            ->with($caseId)
            ->andReturn([
                'results' => ['gaps' => [], 'total_count' => 0],
                'metadata' => ['documents_analyzed' => 0],
            ]);

        $strategyAnalyzer = Mockery::mock(StrategyAnalyzer::class);
        $strategyAnalyzer->shouldReceive('analysisType')->andReturn('strategy');
        $strategyAnalyzer->shouldReceive('analyze')
            ->once()
            ->with($caseId)
            ->andReturn([
                'results' => ['strategies' => [], 'total_strategies' => 0],
                'metadata' => ['documents_analyzed' => 0],
            ]);

        $job = new RunCaseLevelAnalysisJob($caseId, $documentId);
        $job->handle($contradictionDetector, $gapAnalyzer, $strategyAnalyzer);

        // Verify all three CaseAnalysis records were created
        $this->assertDatabaseHas('case_analyses', [
            'case_id' => $caseId,
            'analysis_type' => 'contradictions',
            'status' => CaseAnalysis::STATUS_COMPLETED,
        ]);

        $this->assertDatabaseHas('case_analyses', [
            'case_id' => $caseId,
            'analysis_type' => 'gaps',
            'status' => CaseAnalysis::STATUS_COMPLETED,
        ]);

        $this->assertDatabaseHas('case_analyses', [
            'case_id' => $caseId,
            'analysis_type' => 'strategy',
            'status' => CaseAnalysis::STATUS_COMPLETED,
        ]);
    }

    public function test_it_handles_analyzer_failures_gracefully(): void
    {
        $document = CaseDocument::factory()->create();
        $caseId = (string) $document->case_id;
        $documentId = (string) $document->id;

        // ContradictionDetector throws
        $contradictionDetector = Mockery::mock(ContradictionDetector::class);
        $contradictionDetector->shouldReceive('analysisType')->andReturn('contradictions');
        $contradictionDetector->shouldReceive('analyze')
            ->once()
            ->with($caseId)
            ->andThrow(new \RuntimeException('API connection failed'));

        // GapAnalyzer succeeds
        $gapAnalyzer = Mockery::mock(GapAnalyzer::class);
        $gapAnalyzer->shouldReceive('analysisType')->andReturn('gaps');
        $gapAnalyzer->shouldReceive('analyze')
            ->once()
            ->with($caseId)
            ->andReturn([
                'results' => ['gaps' => [], 'total_count' => 0],
                'metadata' => ['documents_analyzed' => 0],
            ]);

        // StrategyAnalyzer also throws
        $strategyAnalyzer = Mockery::mock(StrategyAnalyzer::class);
        $strategyAnalyzer->shouldReceive('analysisType')->andReturn('strategy');
        $strategyAnalyzer->shouldReceive('analyze')
            ->once()
            ->with($caseId)
            ->andThrow(new \RuntimeException('Token limit exceeded'));

        $job = new RunCaseLevelAnalysisJob($caseId, $documentId);
        // Should NOT throw even though 2 of 3 analyzers fail
        $job->handle($contradictionDetector, $gapAnalyzer, $strategyAnalyzer);

        // Failed analyzers should be recorded as failed
        $this->assertDatabaseHas('case_analyses', [
            'case_id' => $caseId,
            'analysis_type' => 'contradictions',
            'status' => CaseAnalysis::STATUS_FAILED,
        ]);

        // Successful analyzer should be completed
        $this->assertDatabaseHas('case_analyses', [
            'case_id' => $caseId,
            'analysis_type' => 'gaps',
            'status' => CaseAnalysis::STATUS_COMPLETED,
        ]);

        // Failed analyzer should be recorded as failed
        $this->assertDatabaseHas('case_analyses', [
            'case_id' => $caseId,
            'analysis_type' => 'strategy',
            'status' => CaseAnalysis::STATUS_FAILED,
        ]);
    }

    public function test_it_stores_results_in_case_analyses_table(): void
    {
        $document = CaseDocument::factory()->create();
        $caseId = (string) $document->case_id;
        $documentId = (string) $document->id;

        $expectedResults = [
            'contradictions' => [
                ['fact_a' => 'X says A', 'fact_b' => 'Y says B', 'severity' => 'high'],
            ],
            'total_count' => 1,
            'summary' => 'Found 1 contradiction',
        ];
        $expectedMetadata = [
            'documents_analyzed' => 2,
            'processing_time_seconds' => 1.23,
        ];

        $contradictionDetector = Mockery::mock(ContradictionDetector::class);
        $contradictionDetector->shouldReceive('analysisType')->andReturn('contradictions');
        $contradictionDetector->shouldReceive('analyze')
            ->once()
            ->with($caseId)
            ->andReturn([
                'results' => $expectedResults,
                'metadata' => $expectedMetadata,
            ]);

        $gapAnalyzer = Mockery::mock(GapAnalyzer::class);
        $gapAnalyzer->shouldReceive('analysisType')->andReturn('gaps');
        $gapAnalyzer->shouldReceive('analyze')
            ->once()
            ->andReturn([
                'results' => ['gaps' => []],
                'metadata' => ['documents_analyzed' => 0],
            ]);

        $strategyAnalyzer = Mockery::mock(StrategyAnalyzer::class);
        $strategyAnalyzer->shouldReceive('analysisType')->andReturn('strategy');
        $strategyAnalyzer->shouldReceive('analyze')
            ->once()
            ->andReturn([
                'results' => ['strategies' => []],
                'metadata' => ['documents_analyzed' => 0],
            ]);

        $job = new RunCaseLevelAnalysisJob($caseId, $documentId);
        $job->handle($contradictionDetector, $gapAnalyzer, $strategyAnalyzer);

        // Check that results and metadata were stored correctly
        $contradictionAnalysis = CaseAnalysis::where('case_id', $caseId)
            ->where('analysis_type', 'contradictions')
            ->first();

        $this->assertNotNull($contradictionAnalysis);
        $this->assertEquals($expectedResults, $contradictionAnalysis->results);
        $this->assertEquals($expectedMetadata, $contradictionAnalysis->metadata);
        $this->assertNotNull($contradictionAnalysis->started_at);
        $this->assertNotNull($contradictionAnalysis->completed_at);
    }

    public function test_it_logs_progress_for_each_analyzer(): void
    {
        Log::shouldReceive('info')
            ->atLeast()
            ->once()
            ->withArgs(function ($message) {
                return str_contains($message, 'Starting case-level analysis')
                    || str_contains($message, 'Running analyzer')
                    || str_contains($message, 'Analyzer completed')
                    || str_contains($message, 'Case-level analysis complete');
            });

        // Allow error logs too (for graceful failure handling)
        Log::shouldReceive('error')->zeroOrMoreTimes();

        $document = CaseDocument::factory()->create();
        $caseId = (string) $document->case_id;
        $documentId = (string) $document->id;

        $contradictionDetector = Mockery::mock(ContradictionDetector::class);
        $contradictionDetector->shouldReceive('analysisType')->andReturn('contradictions');
        $contradictionDetector->shouldReceive('analyze')->andReturn([
            'results' => [], 'metadata' => [],
        ]);

        $gapAnalyzer = Mockery::mock(GapAnalyzer::class);
        $gapAnalyzer->shouldReceive('analysisType')->andReturn('gaps');
        $gapAnalyzer->shouldReceive('analyze')->andReturn([
            'results' => [], 'metadata' => [],
        ]);

        $strategyAnalyzer = Mockery::mock(StrategyAnalyzer::class);
        $strategyAnalyzer->shouldReceive('analysisType')->andReturn('strategy');
        $strategyAnalyzer->shouldReceive('analyze')->andReturn([
            'results' => [], 'metadata' => [],
        ]);

        $job = new RunCaseLevelAnalysisJob($caseId, $documentId);
        $job->handle($contradictionDetector, $gapAnalyzer, $strategyAnalyzer);

        // Mockery verifies expectations on tearDown, but add explicit assertion
        // to prevent PHPUnit from marking this test as risky
        $this->assertTrue(true);
    }

    public function test_it_stores_document_id_in_case_analysis(): void
    {
        $document = CaseDocument::factory()->create();
        $caseId = (string) $document->case_id;
        $documentId = (string) $document->id;

        $contradictionDetector = Mockery::mock(ContradictionDetector::class);
        $contradictionDetector->shouldReceive('analysisType')->andReturn('contradictions');
        $contradictionDetector->shouldReceive('analyze')->andReturn([
            'results' => [], 'metadata' => [],
        ]);

        $gapAnalyzer = Mockery::mock(GapAnalyzer::class);
        $gapAnalyzer->shouldReceive('analysisType')->andReturn('gaps');
        $gapAnalyzer->shouldReceive('analyze')->andReturn([
            'results' => [], 'metadata' => [],
        ]);

        $strategyAnalyzer = Mockery::mock(StrategyAnalyzer::class);
        $strategyAnalyzer->shouldReceive('analysisType')->andReturn('strategy');
        $strategyAnalyzer->shouldReceive('analyze')->andReturn([
            'results' => [], 'metadata' => [],
        ]);

        $job = new RunCaseLevelAnalysisJob($caseId, $documentId);
        $job->handle($contradictionDetector, $gapAnalyzer, $strategyAnalyzer);

        // Check that document_ids includes the triggering document
        $analysis = CaseAnalysis::where('case_id', $caseId)
            ->where('analysis_type', 'contradictions')
            ->first();

        $this->assertNotNull($analysis);
        $this->assertContains($documentId, $analysis->document_ids);
    }
}

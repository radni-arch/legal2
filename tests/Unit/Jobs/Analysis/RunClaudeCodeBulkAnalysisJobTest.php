<?php

namespace Tests\Unit\Jobs\Analysis;

use App\Jobs\Analysis\RunClaudeCodeBulkAnalysisJob;
use App\Models\CaseAnalysis;
use App\Services\Analysis\AI\ClaudeCodeAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class RunClaudeCodeBulkAnalysisJobTest extends TestCase
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

        $filePaths = ['/path/to/file1.pdf', '/path/to/file2.pdf'];

        RunClaudeCodeBulkAnalysisJob::dispatch('case-123', $filePaths);

        Queue::assertPushed(RunClaudeCodeBulkAnalysisJob::class);
    }

    public function test_it_uses_claude_agent_queue(): void
    {
        $job = new RunClaudeCodeBulkAnalysisJob('case-123', ['/path/to/file.pdf']);

        $this->assertEquals('claude-agent', $job->queue);
    }

    public function test_it_has_appropriate_timeout(): void
    {
        $job = new RunClaudeCodeBulkAnalysisJob('case-123', ['/path/to/file.pdf']);

        $this->assertEquals(900, $job->timeout); // 15 minutes for long sessions
    }

    public function test_it_has_retry_configuration(): void
    {
        $job = new RunClaudeCodeBulkAnalysisJob('case-123', ['/path/to/file.pdf']);

        $this->assertEquals(2, $job->tries);
    }

    public function test_it_stores_case_id_and_file_paths(): void
    {
        $caseId = 'case-456';
        $filePaths = ['/path/to/file1.pdf', '/path/to/file2.pdf'];

        $job = new RunClaudeCodeBulkAnalysisJob($caseId, $filePaths);

        $this->assertEquals($caseId, $job->caseId);
        $this->assertEquals($filePaths, $job->filePaths);
    }

    public function test_it_creates_case_analysis_record_when_starting(): void
    {
        $caseId = 'case-job-test-1';
        $filePaths = ['/tmp/test-file.pdf'];

        $mockAgent = Mockery::mock(ClaudeCodeAgent::class);
        $mockAgent->shouldReceive('bulkCaseAnalysis')
            ->once()
            ->andReturn([
                'per_document' => ['success' => true, 'elapsed_seconds' => 10],
                'cross_document' => ['success' => true, 'elapsed_seconds' => 5],
            ]);

        $job = new RunClaudeCodeBulkAnalysisJob($caseId, $filePaths);
        $job->handle($mockAgent);

        $this->assertDatabaseHas('case_analyses', [
            'case_id' => $caseId,
            'analysis_type' => 'claude_code_bulk',
        ]);
    }

    public function test_it_marks_analysis_processing_at_start(): void
    {
        $caseId = 'case-job-test-2';
        $filePaths = ['/tmp/test-file.pdf'];

        $mockAgent = Mockery::mock(ClaudeCodeAgent::class);
        $mockAgent->shouldReceive('bulkCaseAnalysis')
            ->once()
            ->andReturn([
                'per_document' => ['success' => true, 'elapsed_seconds' => 10],
            ]);

        $job = new RunClaudeCodeBulkAnalysisJob($caseId, $filePaths);
        $job->handle($mockAgent);

        // The analysis should have started_at set
        $analysis = CaseAnalysis::where('case_id', $caseId)
            ->where('analysis_type', 'claude_code_bulk')
            ->first();

        $this->assertNotNull($analysis);
        $this->assertNotNull($analysis->started_at);
    }

    public function test_it_calls_bulk_case_analysis_on_agent(): void
    {
        $caseId = 'case-job-test-3';
        $filePaths = ['/path/file1.pdf', '/path/file2.pdf'];

        $mockAgent = Mockery::mock(ClaudeCodeAgent::class);
        $mockAgent->shouldReceive('bulkCaseAnalysis')
            ->once()
            ->with($caseId, $filePaths)
            ->andReturn([
                'per_document' => ['success' => true, 'elapsed_seconds' => 10],
            ]);

        $job = new RunClaudeCodeBulkAnalysisJob($caseId, $filePaths);
        $job->handle($mockAgent);

        // Test passes if bulkCaseAnalysis was called with correct params
        $this->assertTrue(true);
    }

    public function test_it_marks_completed_when_all_phases_succeed(): void
    {
        $caseId = 'case-job-test-4';
        $filePaths = ['/tmp/test-file.pdf'];

        $mockAgent = Mockery::mock(ClaudeCodeAgent::class);
        $mockAgent->shouldReceive('bulkCaseAnalysis')
            ->once()
            ->andReturn([
                'per_document' => ['success' => true, 'elapsed_seconds' => 10],
                'cross_document' => ['success' => true, 'elapsed_seconds' => 5],
            ]);

        $job = new RunClaudeCodeBulkAnalysisJob($caseId, $filePaths);
        $job->handle($mockAgent);

        $analysis = CaseAnalysis::where('case_id', $caseId)
            ->where('analysis_type', 'claude_code_bulk')
            ->first();

        $this->assertEquals(CaseAnalysis::STATUS_COMPLETED, $analysis->status);
        $this->assertNotNull($analysis->completed_at);
    }

    public function test_it_stores_results_on_completion(): void
    {
        $caseId = 'case-job-test-5';
        $filePaths = ['/tmp/test-file.pdf'];

        $expectedResults = [
            'per_document' => ['success' => true, 'elapsed_seconds' => 10, 'results' => ['doc1' => 'data']],
            'cross_document' => ['success' => true, 'elapsed_seconds' => 5, 'results' => ['timeline' => []]],
        ];

        $mockAgent = Mockery::mock(ClaudeCodeAgent::class);
        $mockAgent->shouldReceive('bulkCaseAnalysis')
            ->once()
            ->andReturn($expectedResults);

        $job = new RunClaudeCodeBulkAnalysisJob($caseId, $filePaths);
        $job->handle($mockAgent);

        $analysis = CaseAnalysis::where('case_id', $caseId)
            ->where('analysis_type', 'claude_code_bulk')
            ->first();

        $this->assertNotNull($analysis->results);
        $this->assertArrayHasKey('per_document', $analysis->results);
    }

    public function test_it_marks_failed_when_any_phase_fails(): void
    {
        $caseId = 'case-job-test-6';
        $filePaths = ['/tmp/test-file.pdf'];

        $mockAgent = Mockery::mock(ClaudeCodeAgent::class);
        $mockAgent->shouldReceive('bulkCaseAnalysis')
            ->once()
            ->andReturn([
                'per_document' => ['success' => false, 'exit_code' => 1],
                'cross_document' => ['success' => true],
            ]);

        $job = new RunClaudeCodeBulkAnalysisJob($caseId, $filePaths);
        $job->handle($mockAgent);

        $analysis = CaseAnalysis::where('case_id', $caseId)
            ->where('analysis_type', 'claude_code_bulk')
            ->first();

        $this->assertEquals(CaseAnalysis::STATUS_FAILED, $analysis->status);
        $this->assertNotNull($analysis->error_message);
    }

    public function test_it_marks_failed_on_exception(): void
    {
        $caseId = 'case-job-test-7';
        $filePaths = ['/tmp/test-file.pdf'];

        $mockAgent = Mockery::mock(ClaudeCodeAgent::class);
        $mockAgent->shouldReceive('bulkCaseAnalysis')
            ->once()
            ->andThrow(new \RuntimeException('Process failed'));

        Log::shouldReceive('info')->once();
        Log::shouldReceive('error')->once();

        $job = new RunClaudeCodeBulkAnalysisJob($caseId, $filePaths);
        $job->handle($mockAgent);

        $analysis = CaseAnalysis::where('case_id', $caseId)
            ->where('analysis_type', 'claude_code_bulk')
            ->first();

        $this->assertEquals(CaseAnalysis::STATUS_FAILED, $analysis->status);
        $this->assertStringContainsString('Process failed', $analysis->error_message);
    }

    public function test_it_stores_metadata_with_file_count_and_elapsed_time(): void
    {
        $caseId = 'case-job-test-8';
        $filePaths = ['/tmp/file1.pdf', '/tmp/file2.pdf', '/tmp/file3.pdf'];

        $mockAgent = Mockery::mock(ClaudeCodeAgent::class);
        $mockAgent->shouldReceive('bulkCaseAnalysis')
            ->once()
            ->andReturn([
                'per_document' => ['success' => true, 'elapsed_seconds' => 10],
                'cross_document' => ['success' => true, 'elapsed_seconds' => 5],
            ]);

        $job = new RunClaudeCodeBulkAnalysisJob($caseId, $filePaths);
        $job->handle($mockAgent);

        $analysis = CaseAnalysis::where('case_id', $caseId)
            ->where('analysis_type', 'claude_code_bulk')
            ->first();

        $this->assertEquals(3, $analysis->metadata['file_count']);
        $this->assertEquals(15, $analysis->metadata['total_elapsed']);
    }
}

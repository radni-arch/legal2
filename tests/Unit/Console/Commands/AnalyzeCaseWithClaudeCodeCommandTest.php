<?php

namespace Tests\Unit\Console\Commands;

use App\Console\Commands\AnalyzeCaseWithClaudeCodeCommand;
use App\Jobs\Analysis\RunClaudeCodeBulkAnalysisJob;
use App\Models\CaseDocument;
use App\Services\Analysis\AI\ClaudeCodeAgent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

/**
 * Tests for AnalyzeCaseWithClaudeCodeCommand
 *
 * Command: case:analyze-claude-code {case_id} {--sync}
 *
 * These are unit tests that mock database interactions to avoid
 * test environment database setup issues.
 */
class AnalyzeCaseWithClaudeCodeCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_command_has_correct_signature(): void
    {
        $command = new AnalyzeCaseWithClaudeCodeCommand();

        $this->assertEquals('case:analyze-claude-code', $command->getName());
    }

    public function test_it_requires_case_id_argument(): void
    {
        $command = new AnalyzeCaseWithClaudeCodeCommand();
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasArgument('case_id'));
        $this->assertTrue($definition->getArgument('case_id')->isRequired());
    }

    public function test_it_has_sync_option(): void
    {
        $command = new AnalyzeCaseWithClaudeCodeCommand();
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('sync'));
    }

    public function test_it_has_correct_description(): void
    {
        $command = new AnalyzeCaseWithClaudeCodeCommand();

        $this->assertEquals(
            'Run Claude Code CLI agent for deep case analysis',
            $command->getDescription()
        );
    }

    public function test_it_returns_error_when_no_documents_found(): void
    {
        $caseId = 'nonexistent-case-123';

        // Mock CaseDocument query to return empty collection
        $mockBuilder = Mockery::mock('Illuminate\Database\Eloquent\Builder');
        $mockBuilder->shouldReceive('where')
            ->with('case_id', $caseId)
            ->andReturnSelf();
        $mockBuilder->shouldReceive('get')
            ->andReturn(new Collection());

        CaseDocument::shouldReceive('where')
            ->with('case_id', $caseId)
            ->andReturn($mockBuilder);

        $this->artisan('case:analyze-claude-code', ['case_id' => $caseId])
            ->expectsOutput("No documents found for case {$caseId}")
            ->assertExitCode(1);
    }

    public function test_it_dispatches_job_when_documents_exist(): void
    {
        $caseId = 'test-case-cmd-1';

        // Create mock documents
        $mockDoc1 = Mockery::mock(CaseDocument::class)->makePartial();
        $mockDoc1->shouldReceive('getAttribute')->with('metadata')->andReturn(['filename' => 'doc1.pdf']);
        $mockDoc1->shouldReceive('getAttribute')->with('upload')->andReturn(null);
        $mockDoc1->shouldReceive('getAttribute')->with('source')->andReturn(null);

        $mockDoc2 = Mockery::mock(CaseDocument::class)->makePartial();
        $mockDoc2->shouldReceive('getAttribute')->with('metadata')->andReturn(['filename' => 'doc2.pdf']);
        $mockDoc2->shouldReceive('getAttribute')->with('upload')->andReturn(null);
        $mockDoc2->shouldReceive('getAttribute')->with('source')->andReturn(null);

        $documents = new Collection([$mockDoc1, $mockDoc2]);

        // Mock CaseDocument query
        $mockBuilder = Mockery::mock('Illuminate\Database\Eloquent\Builder');
        $mockBuilder->shouldReceive('where')
            ->with('case_id', $caseId)
            ->andReturnSelf();
        $mockBuilder->shouldReceive('get')
            ->andReturn($documents);

        CaseDocument::shouldReceive('where')
            ->with('case_id', $caseId)
            ->andReturn($mockBuilder);

        $this->artisan('case:analyze-claude-code', ['case_id' => $caseId])
            ->expectsOutputToContain('Found 2 documents')
            ->expectsOutput('Job dispatched to "claude-agent" queue.')
            ->assertExitCode(0);

        Queue::assertPushed(RunClaudeCodeBulkAnalysisJob::class, function ($job) use ($caseId) {
            return $job->caseId === $caseId;
        });
    }

    public function test_sync_option_runs_synchronously(): void
    {
        $caseId = 'test-case-cmd-sync';

        // Create mock document
        $mockDoc = Mockery::mock(CaseDocument::class)->makePartial();
        $mockDoc->shouldReceive('getAttribute')->with('metadata')->andReturn(['filename' => 'doc.pdf']);
        $mockDoc->shouldReceive('getAttribute')->with('upload')->andReturn(null);
        $mockDoc->shouldReceive('getAttribute')->with('source')->andReturn(null);

        $documents = new Collection([$mockDoc]);

        // Mock CaseDocument query
        $mockBuilder = Mockery::mock('Illuminate\Database\Eloquent\Builder');
        $mockBuilder->shouldReceive('where')
            ->with('case_id', $caseId)
            ->andReturnSelf();
        $mockBuilder->shouldReceive('get')
            ->andReturn($documents);

        CaseDocument::shouldReceive('where')
            ->with('case_id', $caseId)
            ->andReturn($mockBuilder);

        // Mock the agent
        $mockAgent = Mockery::mock(ClaudeCodeAgent::class);
        $mockAgent->shouldReceive('bulkCaseAnalysis')
            ->once()
            ->andReturn([
                'per_document' => ['success' => true, 'elapsed_seconds' => 10],
            ]);

        $this->app->instance(ClaudeCodeAgent::class, $mockAgent);

        $this->artisan('case:analyze-claude-code', [
            'case_id' => $caseId,
            '--sync' => true,
        ])
            ->expectsOutput('Running synchronously...')
            ->expectsOutput('Results:')
            ->assertExitCode(0);

        // Verify job was NOT dispatched (ran sync)
        Queue::assertNotPushed(RunClaudeCodeBulkAnalysisJob::class);
    }

    public function test_sync_mode_displays_phase_results(): void
    {
        $caseId = 'test-case-cmd-results';

        // Create mock document
        $mockDoc = Mockery::mock(CaseDocument::class)->makePartial();
        $mockDoc->shouldReceive('getAttribute')->with('metadata')->andReturn(['filename' => 'doc.pdf']);
        $mockDoc->shouldReceive('getAttribute')->with('upload')->andReturn(null);
        $mockDoc->shouldReceive('getAttribute')->with('source')->andReturn(null);

        $documents = new Collection([$mockDoc]);

        // Mock CaseDocument query
        $mockBuilder = Mockery::mock('Illuminate\Database\Eloquent\Builder');
        $mockBuilder->shouldReceive('where')
            ->with('case_id', $caseId)
            ->andReturnSelf();
        $mockBuilder->shouldReceive('get')
            ->andReturn($documents);

        CaseDocument::shouldReceive('where')
            ->with('case_id', $caseId)
            ->andReturn($mockBuilder);

        $mockAgent = Mockery::mock(ClaudeCodeAgent::class);
        $mockAgent->shouldReceive('bulkCaseAnalysis')
            ->once()
            ->andReturn([
                'per_document' => ['success' => true, 'elapsed_seconds' => 12.5],
                'cross_document' => ['success' => false, 'elapsed_seconds' => 3.2],
            ]);

        $this->app->instance(ClaudeCodeAgent::class, $mockAgent);

        $this->artisan('case:analyze-claude-code', [
            'case_id' => $caseId,
            '--sync' => true,
        ])
            ->expectsOutputToContain('per_document')
            ->expectsOutputToContain('cross_document')
            ->assertExitCode(0);
    }
}

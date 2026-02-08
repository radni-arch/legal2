<?php

namespace Tests\Feature\Console;

use App\Jobs\Analysis\RunDocumentExtractionJob;
use App\Models\CaseDocument;
use App\Models\DocumentAnalysis;
use App\Models\LegalCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AnalyzeCaseDocumentsCommandTest extends TestCase
{
    use DatabaseTransactions;

    public function test_command_requires_case_id(): void
    {
        $this->expectException(\Symfony\Component\Console\Exception\RuntimeException::class);
        $this->expectExceptionMessage('Not enough arguments');

        $this->artisan('case:analyze');
    }

    public function test_command_shows_error_for_non_existent_case(): void
    {
        $this->artisan('case:analyze', ['case_id' => 'non-existent-case'])
            ->expectsOutput('Case not found: non-existent-case')
            ->assertExitCode(1);
    }

    public function test_command_dispatches_jobs_for_all_case_documents(): void
    {
        Queue::fake();

        $case = LegalCase::factory()->create();
        $document1 = CaseDocument::factory()->forCase($case)->create([
            'content' => 'Document 1 content',
        ]);
        $document2 = CaseDocument::factory()->forCase($case)->create([
            'content' => 'Document 2 content',
        ]);

        $this->artisan('case:analyze', ['case_id' => $case->id])
            ->expectsOutputToContain('Dispatching analysis for 2 document(s)')
            ->assertExitCode(0);

        Queue::assertPushed(RunDocumentExtractionJob::class, 2);
    }

    public function test_command_can_analyze_specific_document(): void
    {
        Queue::fake();

        $case = LegalCase::factory()->create();
        $document1 = CaseDocument::factory()->forCase($case)->create([
            'content' => 'Document 1 content',
        ]);
        $document2 = CaseDocument::factory()->forCase($case)->create([
            'content' => 'Document 2 content',
        ]);

        $this->artisan('case:analyze', [
            'case_id' => $case->id,
            '--document' => $document1->id,
        ])
            ->expectsOutputToContain('Dispatching analysis for 1 document(s)')
            ->assertExitCode(0);

        Queue::assertPushed(RunDocumentExtractionJob::class, 1);
    }

    public function test_command_shows_status_without_dispatching_jobs(): void
    {
        Queue::fake();

        $case = LegalCase::factory()->create();
        $document = CaseDocument::factory()->forCase($case)->create([
            'content' => 'Document content',
        ]);

        // Create some analysis records
        DocumentAnalysis::create([
            'case_document_id' => $document->id,
            'analysis_type' => DocumentAnalysis::TYPE_KEYWORDS,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'version' => 1,
            'results' => ['keywords' => ['test']],
        ]);

        $this->artisan('case:analyze', [
            'case_id' => $case->id,
            '--status' => true,
        ])
            ->expectsOutputToContain('Analysis Status')
            ->assertExitCode(0);

        // Verify no jobs were dispatched when using --status
        Queue::assertNotPushed(RunDocumentExtractionJob::class);
    }

    public function test_command_skips_already_analyzed_documents_without_rerun_flag(): void
    {
        Queue::fake();

        $case = LegalCase::factory()->create();
        $document = CaseDocument::factory()->forCase($case)->create([
            'content' => 'Document content',
        ]);

        // Mark as already analyzed
        DocumentAnalysis::create([
            'case_document_id' => $document->id,
            'analysis_type' => DocumentAnalysis::TYPE_KEYWORDS,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'version' => 1,
            'results' => ['keywords' => ['test']],
        ]);

        $this->artisan('case:analyze', ['case_id' => $case->id])
            ->expectsOutputToContain('Skipping')
            ->assertExitCode(0);

        // Should not dispatch because document already analyzed
        Queue::assertNotPushed(RunDocumentExtractionJob::class);
    }

    public function test_command_reanalyzes_with_rerun_flag(): void
    {
        Queue::fake();

        $case = LegalCase::factory()->create();
        $document = CaseDocument::factory()->forCase($case)->create([
            'content' => 'Document content',
        ]);

        // Mark as already analyzed
        DocumentAnalysis::create([
            'case_document_id' => $document->id,
            'analysis_type' => DocumentAnalysis::TYPE_KEYWORDS,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'version' => 1,
            'results' => ['keywords' => ['test']],
        ]);

        $this->artisan('case:analyze', [
            'case_id' => $case->id,
            '--rerun' => true,
        ])
            ->expectsOutputToContain('Dispatching analysis for 1 document(s)')
            ->assertExitCode(0);

        Queue::assertPushed(RunDocumentExtractionJob::class, 1);
    }

    public function test_command_handles_case_with_no_documents(): void
    {
        $case = LegalCase::factory()->create();

        $this->artisan('case:analyze', ['case_id' => $case->id])
            ->expectsOutput('No documents found for case ' . $case->id)
            ->assertExitCode(0);
    }
}

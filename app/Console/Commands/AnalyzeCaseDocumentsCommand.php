<?php

namespace App\Console\Commands;

use App\Jobs\Analysis\RunDocumentExtractionJob;
use App\Models\CaseDocument;
use App\Models\DocumentAnalysis;
use App\Models\LegalCase;
use Illuminate\Console\Command;

class AnalyzeCaseDocumentsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'case:analyze
        {case_id : The ID of the case to analyze}
        {--document= : Analyze only a specific document ID}
        {--rerun : Re-analyze even if analysis already exists}
        {--status : Show analysis status without running analysis}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Analyze case documents using the document analysis pipeline';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $caseId = $this->argument('case_id');

        // Verify case exists
        $case = LegalCase::find($caseId);
        if (!$case) {
            $this->error("Case not found: {$caseId}");
            return Command::FAILURE;
        }

        // Get documents to analyze
        $query = CaseDocument::where('case_id', $caseId);

        if ($documentId = $this->option('document')) {
            $query->where('id', $documentId);
        }

        $documents = $query->get();

        if ($documents->isEmpty()) {
            $this->info("No documents found for case {$caseId}");
            return Command::SUCCESS;
        }

        // Status mode - just show analysis status
        if ($this->option('status')) {
            return $this->showStatus($documents);
        }

        // Run mode - dispatch analysis jobs
        return $this->runAnalysis($documents, $caseId);
    }

    /**
     * Show analysis status for documents.
     */
    private function showStatus($documents): int
    {
        $this->info('Analysis Status for ' . $documents->count() . ' document(s)');
        $this->newLine();

        $headers = ['Document ID', 'Title', 'Type', 'Layer', 'Status', 'Version'];
        $rows = [];

        foreach ($documents as $document) {
            $analyses = DocumentAnalysis::where('case_document_id', $document->id)->get();

            if ($analyses->isEmpty()) {
                $rows[] = [
                    $document->id,
                    substr($document->title ?? 'Untitled', 0, 30),
                    '-',
                    '-',
                    'not analyzed',
                    '-',
                ];
            } else {
                foreach ($analyses as $analysis) {
                    $rows[] = [
                        $document->id,
                        substr($document->title ?? 'Untitled', 0, 30),
                        $analysis->analysis_type,
                        $analysis->analysis_layer,
                        $analysis->status,
                        $analysis->version,
                    ];
                }
            }
        }

        $this->table($headers, $rows);

        return Command::SUCCESS;
    }

    /**
     * Run analysis on documents.
     */
    private function runAnalysis($documents, string $caseId): int
    {
        $rerun = $this->option('rerun');
        $dispatched = 0;
        $skipped = 0;

        foreach ($documents as $document) {
            // Check if already analyzed (unless --rerun)
            if (!$rerun && $this->hasCompleteExtractionAnalysis($document)) {
                $this->line("Skipping {$document->id} - already analyzed (use --rerun to force)");
                $skipped++;
                continue;
            }

            // Dispatch extraction job
            RunDocumentExtractionJob::dispatch($document, $caseId);
            $dispatched++;
        }

        if ($dispatched > 0) {
            $this->info("Dispatching analysis for {$dispatched} document(s) to the 'analysis' queue");
        }

        if ($skipped > 0) {
            $this->line("Skipped {$skipped} document(s) with existing analysis");
        }

        return Command::SUCCESS;
    }

    /**
     * Check if a document has complete extraction layer analysis.
     */
    private function hasCompleteExtractionAnalysis(CaseDocument $document): bool
    {
        // Consider document analyzed if it has at least one completed extraction-layer analysis
        return DocumentAnalysis::where('case_document_id', $document->id)
            ->where('analysis_layer', DocumentAnalysis::LAYER_EXTRACTION)
            ->where('status', DocumentAnalysis::STATUS_COMPLETED)
            ->exists();
    }
}

<?php

namespace App\Jobs\Analysis;

use App\Events\DocumentAnalysisCompleted;
use App\Models\CaseDocument;
use App\Models\DocumentAnalysis;
use App\Services\Analysis\DocumentAnalysisPipeline;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Runs Layer 1 (deterministic extraction) analysis on a document.
 *
 * This job orchestrates the DocumentAnalysisPipeline to run all Layer 1 analyzers:
 * - DocumentStatisticsAnalyzer
 * - KeywordAnalyzer
 * - DateExtractor
 * - EntityExtractor
 */
class RunDocumentExtractionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        public CaseDocument $caseDocument,
        public string $caseId,
    ) {
        $this->queue = 'analysis';
    }

    /**
     * Execute the job.
     */
    public function handle(DocumentAnalysisPipeline $pipeline): void
    {
        Log::info("Starting Layer 1 analysis for document {$this->caseDocument->id} in case {$this->caseId}");

        // Run Layer 1: Deterministic extraction
        $results = $pipeline->runLayer($this->caseDocument, DocumentAnalysis::LAYER_EXTRACTION);

        $completedCount = collect($results)
            ->filter(fn($r) => $r->status === DocumentAnalysis::STATUS_COMPLETED)
            ->count();

        Log::info("Layer 1 complete for document {$this->caseDocument->id}: {$completedCount}/" . count($results) . " analyzers succeeded");

        // Fire event to trigger downstream processing (case-level AI analysis)
        DocumentAnalysisCompleted::dispatch(
            $this->caseId,
            (string) $this->caseDocument->id,
            DocumentAnalysis::LAYER_EXTRACTION,
        );

        // Chain: dispatch Layer 2 (pattern matching) after Layer 1 completes
        // Uncomment when Sprint 2 is ready:
        // RunPatternAnalysisJob::dispatch($this->caseDocument, $this->caseId);
    }
}

<?php

namespace App\Jobs\Analysis;

use App\Models\CaseAnalysis;
use App\Services\Analysis\CaseLevel\AI\ContradictionDetector;
use App\Services\Analysis\CaseLevel\AI\GapAnalyzer;
use App\Services\Analysis\CaseLevel\AI\StrategyAnalyzer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Runs case-level AI analysis after Layer 1 extraction completes.
 *
 * Dispatches three AI analyzers in sequence:
 * - ContradictionDetector: cross-document contradiction analysis
 * - GapAnalyzer: case file gap detection
 * - StrategyAnalyzer: legal strategy recommendations
 *
 * Each analyzer's results are stored in the case_analyses table.
 * Analyzer failures are handled gracefully -- a failure in one analyzer
 * does not prevent the remaining analyzers from running.
 */
class RunCaseLevelAnalysisJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $backoff = 60;

    public function __construct(
        public string $caseId,
        public string $documentId,
    ) {
        $this->queue = 'analysis';
    }

    /**
     * Execute the job.
     */
    public function handle(
        ContradictionDetector $contradictionDetector,
        GapAnalyzer $gapAnalyzer,
        StrategyAnalyzer $strategyAnalyzer,
    ): void {
        Log::info("Starting case-level analysis for case {$this->caseId}, triggered by document {$this->documentId}");

        $analyzers = [
            $contradictionDetector,
            $gapAnalyzer,
            $strategyAnalyzer,
        ];

        $completedCount = 0;
        $failedCount = 0;

        foreach ($analyzers as $analyzer) {
            $analysisType = $analyzer->analysisType();

            Log::info("Running analyzer: {$analysisType} for case {$this->caseId}");

            // Create or get the CaseAnalysis record
            $caseAnalysis = CaseAnalysis::updateOrCreate(
                [
                    'case_id' => $this->caseId,
                    'analysis_type' => $analysisType,
                    'version' => 1,
                ],
                [
                    'status' => CaseAnalysis::STATUS_PENDING,
                    'document_ids' => [$this->documentId],
                ]
            );

            $caseAnalysis->markProcessing();

            try {
                $result = $analyzer->analyze($this->caseId);

                $caseAnalysis->markCompleted(
                    $result['results'] ?? [],
                    $result['metadata'] ?? []
                );

                Log::info("Analyzer completed: {$analysisType} for case {$this->caseId}");
                $completedCount++;
            } catch (\Throwable $e) {
                $caseAnalysis->markFailed($e->getMessage());

                Log::error("Analyzer failed: {$analysisType} for case {$this->caseId}: {$e->getMessage()}");
                $failedCount++;
            }
        }

        Log::info("Case-level analysis complete for case {$this->caseId}: {$completedCount} succeeded, {$failedCount} failed");
    }
}

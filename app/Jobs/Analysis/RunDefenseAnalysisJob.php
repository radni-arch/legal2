<?php

namespace App\Jobs\Analysis;

use App\Services\Defense\DefenseReportBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job to run defense tactic analysis on a case.
 *
 * This should be dispatched after all document and case-level analyses
 * are complete (Layers 1-5), as the defense detectors consume those results.
 */
class RunDefenseAnalysisJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $caseId,
    ) {}

    /**
     * Get the queue the job should be sent to.
     */
    public function queue(): string
    {
        return 'analysis';
    }

    /**
     * Execute the job.
     */
    public function handle(DefenseReportBuilder $builder): void
    {
        Log::info("RunDefenseAnalysisJob: Starting defense analysis for case {$this->caseId}");

        try {
            $report = $builder->buildReport($this->caseId);

            $criticalCount = count(array_filter($report->flags, fn($f) => $f->severity === 'critical'));
            $highCount = count(array_filter($report->flags, fn($f) => $f->severity === 'high'));

            Log::info("RunDefenseAnalysisJob: Completed for case {$this->caseId}", [
                'total_flags' => count($report->flags),
                'critical' => $criticalCount,
                'high' => $highCount,
                'processing_time' => $report->processingTime,
                'detectors_run' => $report->detectorsRun,
            ]);
        } catch (\Throwable $e) {
            Log::error("RunDefenseAnalysisJob: Failed for case {$this->caseId}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return ['defense-analysis', "case:{$this->caseId}"];
    }
}

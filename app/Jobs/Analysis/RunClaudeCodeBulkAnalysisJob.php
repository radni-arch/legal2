<?php

namespace App\Jobs\Analysis;

use App\Models\CaseAnalysis;
use App\Services\Analysis\AI\ClaudeCodeAgent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job to run Claude Code CLI bulk analysis on case documents.
 *
 * This job orchestrates the ClaudeCodeAgent to perform autonomous
 * multi-phase analysis on case files, including per-document and
 * cross-document analysis.
 */
class RunClaudeCodeBulkAnalysisJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times the job may be attempted.
     */
    public int $tries = 2;

    /**
     * The number of seconds the job can run before timing out.
     * Set to 15 minutes - Claude Code sessions can be long.
     */
    public int $timeout = 900;

    /**
     * Create a new job instance.
     *
     * @param string $caseId Case identifier
     * @param array $filePaths Array of absolute file paths to analyze
     */
    public function __construct(
        public string $caseId,
        public array $filePaths,
    ) {
        $this->queue = 'claude-agent';
    }

    /**
     * Execute the job.
     *
     * @param ClaudeCodeAgent $agent
     */
    public function handle(ClaudeCodeAgent $agent): void
    {
        Log::info("Starting Claude Code bulk analysis for case {$this->caseId}");

        $analysis = CaseAnalysis::updateOrCreate(
            ['case_id' => $this->caseId, 'analysis_type' => 'claude_code_bulk'],
            ['status' => CaseAnalysis::STATUS_PROCESSING, 'started_at' => now()]
        );

        try {
            $results = $agent->bulkCaseAnalysis($this->caseId, $this->filePaths);

            $allSucceeded = collect($results)->every(fn($r) => $r['success'] ?? false);

            if ($allSucceeded) {
                $analysis->markCompleted($results, [
                    'file_count' => count($this->filePaths),
                    'total_elapsed' => collect($results)->sum('elapsed_seconds'),
                ]);
            } else {
                $failures = collect($results)
                    ->filter(fn($r) => !($r['success'] ?? false))
                    ->keys()
                    ->implode(', ');

                $analysis->markFailed("Some phases failed: {$failures}");
            }
        } catch (\Throwable $e) {
            Log::error("Claude Code bulk analysis failed: {$e->getMessage()}");
            $analysis->markFailed($e->getMessage());
        }
    }
}

<?php

namespace App\Console\Commands;

use App\Jobs\Analysis\RunClaudeCodeBulkAnalysisJob;
use App\Models\CaseDocument;
use App\Services\Analysis\AI\ClaudeCodeAgent;
use Illuminate\Console\Command;

/**
 * Artisan command to run Claude Code CLI agent for deep case analysis.
 *
 * This command can either dispatch a background job (default) or run
 * synchronously with the --sync flag.
 */
class AnalyzeCaseWithClaudeCodeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'case:analyze-claude-code
        {case_id : The case identifier}
        {--sync : Run synchronously instead of queuing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run Claude Code CLI agent for deep case analysis';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $caseId = $this->argument('case_id');

        $documents = CaseDocument::where('case_id', $caseId)->get();

        if ($documents->isEmpty()) {
            $this->error("No documents found for case {$caseId}");
            return 1;
        }

        // Collect file paths from documents
        // Try multiple approaches to find the file path:
        // 1. From metadata.filename
        // 2. From upload relationship
        // 3. From source field
        $filePaths = $documents->map(function ($doc) {
            // Try metadata.filename first
            $filename = $doc->metadata['filename'] ?? null;
            if ($filename) {
                $path = storage_path("app/case-documents/{$filename}");
                if (file_exists($path)) {
                    return $path;
                }
            }

            // Try upload relationship
            if ($doc->upload && $doc->upload->local_path) {
                $path = storage_path("app/{$doc->upload->local_path}");
                if (file_exists($path)) {
                    return $path;
                }
            }

            // Try source field as path
            if ($doc->source && file_exists($doc->source)) {
                return $doc->source;
            }

            return null;
        })->filter()->values()->toArray();

        $this->info("Found {$documents->count()} documents, " . count($filePaths) . " files accessible");

        if ($this->option('sync')) {
            $this->info('Running synchronously...');
            $agent = app(ClaudeCodeAgent::class);
            $results = $agent->bulkCaseAnalysis($caseId, $filePaths);

            $this->info('Results:');
            foreach ($results as $phase => $result) {
                $status = ($result['success'] ?? false) ? '[OK]' : '[FAIL]';
                $time = $result['elapsed_seconds'] ?? '?';
                $this->line("  {$status} {$phase}: {$time}s");
            }
        } else {
            RunClaudeCodeBulkAnalysisJob::dispatch($caseId, $filePaths);
            $this->info('Job dispatched to "claude-agent" queue.');
            $this->line('Run: php artisan queue:work --queue=claude-agent --timeout=900');
        }

        return 0;
    }
}

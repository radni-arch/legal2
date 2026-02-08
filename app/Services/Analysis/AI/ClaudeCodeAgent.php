<?php

namespace App\Services\Analysis\AI;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * ClaudeCodeAgent - Laravel service wrapping the Claude Code CLI binary.
 *
 * This service enables autonomous bulk file processing using the Claude Code CLI,
 * which can read files directly from disk, write structured output files, and
 * use bash tools as part of its analysis.
 *
 * Key advantages over the Claude API:
 * - Direct file access (no need to serialize documents into API calls)
 * - Can write structured output files (JSON) directly
 * - Can use bash tools including custom scripts
 * - Extended thinking and multi-turn reasoning
 */
class ClaudeCodeAgent
{
    private string $binary;
    private string $model;
    private int $timeout;
    private string $workDir;
    private string $outputDir;

    public function __construct()
    {
        $this->binary = config('claude-code.binary');
        $this->model = config('claude-code.model');
        $this->timeout = config('claude-code.timeout');
        $this->workDir = config('claude-code.work_dir');
        $this->outputDir = config('claude-code.output_dir');

        // Ensure directories exist
        if (!is_dir($this->workDir)) {
            mkdir($this->workDir, 0755, true);
        }
        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0755, true);
        }
    }

    /**
     * Run an autonomous analysis session on a set of files.
     *
     * @param string[] $filePaths  Absolute paths to files to analyze
     * @param string   $task       Description of what to analyze
     * @param string   $caseId     Case identifier for output naming
     * @return array{success: bool, output_file: string, results: ?array, raw_output: string, exit_code: int, session_id: string, elapsed_seconds: float}
     */
    public function analyzeFiles(array $filePaths, string $task, string $caseId): array
    {
        $sessionId = Str::uuid()->toString();
        $sessionDir = $this->workDir . '/' . $sessionId;
        $outputFile = $this->outputDir . "/{$caseId}_{$sessionId}.json";

        mkdir($sessionDir, 0755, true);

        // Symlink or copy files into session directory
        foreach ($filePaths as $path) {
            $basename = basename($path);
            if (file_exists($path)) {
                symlink($path, "{$sessionDir}/{$basename}");
            }
        }

        // Also symlink the extraction script if it exists (Task 27 integration)
        $scriptPath = base_path('scripts/extract_references.sh');
        if (file_exists($scriptPath)) {
            symlink($scriptPath, "{$sessionDir}/extract_references.sh");
            // Note: chmod on symlink affects target, not the link itself
            // The script should already be executable at the source
        }

        // Build the prompt
        $fileList = implode("\n", array_map('basename', array_filter($filePaths, 'file_exists')));
        $promptTemplate = config('claude-code.legal_analysis_prompt');
        $prompt = str_replace(
            ['{TASK_DESCRIPTION}', '{WORK_DIR}', '{OUTPUT_FILE}'],
            [$task, $sessionDir, $outputFile],
            $promptTemplate
        );
        $prompt .= "\n\nFiles available:\n{$fileList}";

        // Add script usage hint if available
        if (file_exists($scriptPath)) {
            $prompt .= "\n\nYou have access to a reference extraction script at: ./extract_references.sh";
            $prompt .= "\n\nUsage examples:";
            $prompt .= "\n  ./extract_references.sh --json document.pdf          # Full extraction as JSON";
            $prompt .= "\n  ./extract_references.sh --batch --json Documents/    # Batch mode";
            $prompt .= "\n  ./extract_references.sh --case document.pdf          # Case numbers only";
            $prompt .= "\n\nALWAYS run this script first on each document to get the structured extraction,";
            $prompt .= "\nthen use the results as a foundation for your deeper analysis.";
        }

        Log::info("ClaudeCodeAgent: Starting session {$sessionId}", [
            'case_id' => $caseId,
            'file_count' => count($filePaths),
            'task' => Str::limit($task, 200),
        ]);

        $startTime = microtime(true);

        // Run claude CLI
        $result = Process::timeout($this->timeout)
            ->path($sessionDir)
            ->env([
                'ANTHROPIC_MODEL' => $this->model,
            ])
            ->run([
                $this->binary,
                '--print',          // Non-interactive mode
                '--output-format', 'json',
                '--max-turns', (string) config('claude-code.max_turns'),
                '--allowedTools', implode(',', config('claude-code.allowed_tools')),
                '-p', $prompt,
            ]);

        $elapsed = round(microtime(true) - $startTime, 2);
        $exitCode = $result->exitCode();
        $rawOutput = $result->output();

        Log::info("ClaudeCodeAgent: Session {$sessionId} completed", [
            'exit_code' => $exitCode,
            'elapsed_seconds' => $elapsed,
            'output_length' => strlen($rawOutput),
        ]);

        // Parse output JSON if the agent wrote it
        $parsedResults = null;
        if (file_exists($outputFile)) {
            $json = file_get_contents($outputFile);
            $parsedResults = json_decode($json, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning("ClaudeCodeAgent: Failed to parse output JSON", [
                    'error' => json_last_error_msg(),
                ]);
                $parsedResults = null;
            }
        }

        // Cleanup session directory (keep output)
        $this->cleanupSession($sessionDir);

        return [
            'success' => $exitCode === 0 && $parsedResults !== null,
            'output_file' => $outputFile,
            'results' => $parsedResults,
            'raw_output' => $rawOutput,
            'exit_code' => $exitCode,
            'session_id' => $sessionId,
            'elapsed_seconds' => $elapsed,
        ];
    }

    /**
     * Run a bulk analysis across all documents in a case.
     *
     * This is the "orchestrated by Laravel" part - Laravel manages
     * the high-level workflow, Claude Code does the deep analysis.
     *
     * @param string $caseId Case identifier
     * @param array $filePaths Array of file paths to analyze
     * @return array Results from both analysis phases
     */
    public function bulkCaseAnalysis(string $caseId, array $filePaths): array
    {
        $results = [];

        // Phase 1: Individual document analysis (can be parallelized)
        $perDocTask = <<<TASK
For EACH document in the working directory:
1. Read the full text
2. Extract all dates with their surrounding context (+/-200 chars)
3. Extract all case numbers (K-, Pp Prz-, Kv-, etc.), KLASA, URBROJ
4. Identify the document type (presuda, rjesenje, zapisnik, naredba, prijava, etc.)
5. Summarize key facts (max 5 bullet points per document)
6. Note any procedural irregularities

Output a JSON file with one entry per document.
TASK;

        $results['per_document'] = $this->analyzeFiles($filePaths, $perDocTask, "{$caseId}_docs");

        // Phase 2: Cross-document analysis (uses Phase 1 output)
        if ($results['per_document']['success']) {
            $crossTask = <<<TASK
Read the per-document analysis from: {$results['per_document']['output_file']}

Now perform CROSS-DOCUMENT analysis:
1. Build a complete timeline of events across all documents
2. Identify contradictions: dates that don't match, facts that conflict
3. Map the case hierarchy (main case vs satellite cases like Pp Prz)
4. Identify missing documents: references to case numbers that have no source document
5. Flag procedural issues: was the search warrant issued BEFORE or AFTER the search?

Output comprehensive JSON with sections: timeline, contradictions, hierarchy, gaps, issues.
TASK;

            $results['cross_document'] = $this->analyzeFiles(
                [$results['per_document']['output_file']],
                $crossTask,
                "{$caseId}_cross"
            );
        }

        return $results;
    }

    /**
     * Clean up a session directory by removing symlinks and the directory.
     *
     * @param string $sessionDir Path to the session directory
     */
    private function cleanupSession(string $sessionDir): void
    {
        // Remove symlinks and temp files
        $files = glob("{$sessionDir}/*");
        foreach ($files as $file) {
            if (is_link($file)) {
                unlink($file);
            }
        }
        @rmdir($sessionDir);
    }
}

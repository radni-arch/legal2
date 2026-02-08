<?php

namespace App\Services\Agent;

use App\DTOs\Agent\AgentResult;
use App\DTOs\Agent\AgentSession;
use App\Services\Agent\Contracts\AgentInterface;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Abstract base class for AI CLI agents.
 *
 * Handles common boilerplate:
 * - Session directory creation
 * - File symlinking
 * - JSON output parsing
 * - Cleanup
 *
 * Each driver implements the CLI invocation specifics via abstract methods.
 */
abstract class BaseAgent implements AgentInterface
{
    protected string $workBaseDir;
    protected string $outputBaseDir;
    protected int $timeout;

    public function __construct()
    {
        $this->workBaseDir = config("agents.drivers.{$this->driver()}.work_dir")
            ?? config('agents.work_dir')
            ?? storage_path('app/agent-sessions');
        $this->outputBaseDir = config("agents.drivers.{$this->driver()}.output_dir")
            ?? config('agents.output_dir')
            ?? storage_path('app/agent-output');
        $this->timeout = config("agents.drivers.{$this->driver()}.timeout")
            ?? config('agents.timeout', 600);

        if (!is_dir($this->workBaseDir)) {
            mkdir($this->workBaseDir, 0755, true);
        }
        if (!is_dir($this->outputBaseDir)) {
            mkdir($this->outputBaseDir, 0755, true);
        }
    }

    /**
     * Each driver implements this: build the CLI command array.
     *
     * @param string       $prompt      Fully assembled prompt
     * @param AgentSession $session     Session with workDir, outputFile
     * @param array        $options     Driver-specific options
     * @return array       Command parts for Process::run()
     */
    abstract protected function buildCommand(string $prompt, AgentSession $session, array $options): array;

    /**
     * Each driver implements this: parse raw output into structured results.
     * Some agents write to a file, some output to stdout.
     */
    abstract protected function parseOutput(string $rawOutput, AgentSession $session): ?array;

    /**
     * Get the binary path for this driver.
     */
    abstract protected function binary(): string;

    /**
     * Environment variables for the process.
     * Override in concrete drivers to add API keys, etc.
     */
    protected function environment(array $options): array
    {
        return [];
    }

    /**
     * Check if the agent binary is available on this system.
     */
    public function isAvailable(): bool
    {
        $binary = $this->binary();
        $result = Process::run("which {$binary} 2>/dev/null");
        return $result->exitCode() === 0;
    }

    /**
     * Run a prompt with file context.
     *
     * Flow:
     * 1. Create session with createSession()
     * 2. Assemble full prompt with assemblePrompt()
     * 3. Build command with buildCommand()
     * 4. Log start
     * 5. Execute with Process::timeout()->path()->env()->run()
     * 6. Calculate elapsed time
     * 7. Try to parse output (file first, then stdout)
     * 8. Log completion
     * 9. Cleanup session
     * 10. Return AgentResult
     *
     * @param string   $prompt     The task description
     * @param string[] $filePaths  Files to make available
     * @param array    $options    Driver-specific options
     */
    public function run(string $prompt, array $filePaths = [], array $options = []): AgentResult
    {
        $session = $this->createSession($filePaths);

        // Inject standard context into prompt
        $fullPrompt = $this->assemblePrompt($prompt, $session, $options);

        $command = $this->buildCommand($fullPrompt, $session, $options);

        Log::info("Agent [{$this->driver()}] starting session {$session->sessionId}", [
            'file_count' => count($filePaths),
            'prompt_length' => strlen($fullPrompt),
        ]);

        $startTime = microtime(true);

        $result = Process::timeout($this->timeout)
            ->path($session->workDir)
            ->env($this->environment($options))
            ->run($command);

        $elapsed = round(microtime(true) - $startTime, 2);

        $rawOutput = $result->output();
        $rawError = $result->errorOutput();
        $exitCode = $result->exitCode();

        // Try to parse output
        $parsed = null;
        if ($exitCode === 0) {
            // First try: agent wrote a JSON file
            if (file_exists($session->outputFile)) {
                $json = file_get_contents($session->outputFile);
                $parsed = json_decode($json, true);
            }

            // Second try: parse from stdout
            if ($parsed === null) {
                $parsed = $this->parseOutput($rawOutput, $session);
            }
        }

        Log::info("Agent [{$this->driver()}] session {$session->sessionId} completed", [
            'exit_code' => $exitCode,
            'elapsed' => $elapsed,
            'output_parsed' => $parsed !== null,
        ]);

        $this->cleanupSession($session);

        return new AgentResult(
            success: $exitCode === 0 && $parsed !== null,
            driver: $this->driver(),
            results: $parsed,
            rawOutput: $rawOutput,
            rawError: $rawError,
            exitCode: $exitCode,
            elapsedSeconds: $elapsed,
            sessionId: $session->sessionId,
            outputFile: file_exists($session->outputFile) ? $session->outputFile : null,
            metadata: [
                'command' => implode(' ', $command),
                'binary' => $this->binary(),
            ],
        );
    }

    /**
     * Run a multi-phase analysis (per-document to cross-document).
     * Default implementation calls run() for each phase, injecting previous results.
     *
     * @param string   $caseId    Case identifier
     * @param string[] $filePaths Files to analyze
     * @param array    $phases    Phase name => prompt pairs
     * @return array<string, AgentResult>
     */
    public function bulkAnalysis(string $caseId, array $filePaths, array $phases): array
    {
        $results = [];

        foreach ($phases as $phaseName => $phasePrompt) {
            // Inject previous phase results into prompt if available
            $previousResults = collect($results)
                ->filter(fn($r) => $r->success)
                ->map(fn($r) => json_encode($r->results, JSON_UNESCAPED_UNICODE))
                ->implode("\n\n---\n\n");

            $fullPrompt = $phasePrompt;
            if ($previousResults) {
                $fullPrompt .= "\n\nPrevious analysis results:\n{$previousResults}";
            }

            $results[$phaseName] = $this->run($fullPrompt, $filePaths, [
                'case_id' => $caseId,
                'phase' => $phaseName,
            ]);
        }

        return $results;
    }

    // === Session Management ===

    /**
     * Create a session with working directory and symlinked files.
     *
     * 1. Generate UUID session ID
     * 2. Create workDir at workBaseDir/sessionId
     * 3. Symlink each file to workDir
     * 4. Also symlink extract_references.sh if exists
     *
     * @param string[] $filePaths Files to make available in the session
     */
    protected function createSession(array $filePaths): AgentSession
    {
        $sessionId = Str::uuid()->toString();
        $workDir = "{$this->workBaseDir}/{$sessionId}";
        $outputFile = "{$this->outputBaseDir}/{$sessionId}.json";

        mkdir($workDir, 0755, true);

        $symlinkMap = [];
        foreach ($filePaths as $path) {
            if (file_exists($path)) {
                $basename = basename($path);
                $target = "{$workDir}/{$basename}";
                symlink($path, $target);
                $symlinkMap[$path] = $target;
            }
        }

        // Also symlink extract_references.sh if available
        $scriptPath = base_path('scripts/extract_references.sh');
        if (file_exists($scriptPath)) {
            symlink($scriptPath, "{$workDir}/extract_references.sh");
        }

        return new AgentSession(
            sessionId: $sessionId,
            workDir: $workDir,
            outputFile: $outputFile,
            symlinkMap: $symlinkMap,
        );
    }

    /**
     * Assemble the full prompt with preamble, file list, and output instruction.
     *
     * @param string       $prompt  User-provided prompt
     * @param AgentSession $session Session with file list
     * @param array        $options Driver-specific options
     */
    protected function assemblePrompt(string $prompt, AgentSession $session, array $options): string
    {
        $systemPreamble = config("agents.drivers.{$this->driver()}.system_prompt", '');

        $fileList = array_map('basename', array_keys($session->symlinkMap));
        $fileSection = !empty($fileList)
            ? "\n\nFiles available in working directory:\n" . implode("\n", $fileList)
            : '';

        $outputInstruction = "\n\nWrite your output as JSON to: {$session->outputFile}";

        return $systemPreamble . "\n\n" . $prompt . $fileSection . $outputInstruction;
    }

    /**
     * Clean up session by removing symlinks and the work directory.
     */
    protected function cleanupSession(AgentSession $session): void
    {
        $files = glob("{$session->workDir}/*");
        if ($files !== false) {
            foreach ($files as $file) {
                if (is_link($file)) {
                    unlink($file);
                }
            }
        }
        @rmdir($session->workDir);
    }
}

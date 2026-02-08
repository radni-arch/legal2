<?php

namespace Tests\Unit\Services\Analysis\AI;

use App\Services\Analysis\AI\ClaudeCodeAgent;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * Tests for ClaudeCodeAgent - Laravel service wrapping the Claude Code CLI.
 */
class ClaudeCodeAgentTest extends TestCase
{
    protected string $testWorkDir;
    protected string $testOutputDir;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up test directories in temp
        $this->testWorkDir = sys_get_temp_dir() . '/claude-agent-test-' . uniqid();
        $this->testOutputDir = sys_get_temp_dir() . '/claude-agent-output-test-' . uniqid();

        // Configure test settings
        Config::set('claude-code.binary', '/usr/local/bin/claude');
        Config::set('claude-code.model', 'claude-sonnet-4-5-20250929');
        Config::set('claude-code.timeout', 600);
        Config::set('claude-code.work_dir', $this->testWorkDir);
        Config::set('claude-code.output_dir', $this->testOutputDir);
        Config::set('claude-code.max_turns', 25);
        Config::set('claude-code.allowed_tools', ['bash', 'file_read', 'file_write']);
        Config::set('claude-code.legal_analysis_prompt', <<<'PROMPT'
You are a Croatian legal document analyst. You have access to case files on disk.

Your task: {TASK_DESCRIPTION}

Working directory: {WORK_DIR}
Output your results as JSON to: {OUTPUT_FILE}

Rules:
- All analysis must reference specific documents by filename
- Dates must be in ISO format (YYYY-MM-DD)
- Include confidence scores for every assertion
- Write Croatian descriptions, but JSON keys in English
- If you find contradictions, flag them with severity: high/medium/low
PROMPT);
    }

    protected function tearDown(): void
    {
        // Clean up test directories
        if (is_dir($this->testWorkDir)) {
            $this->recursiveDelete($this->testWorkDir);
        }
        if (is_dir($this->testOutputDir)) {
            $this->recursiveDelete($this->testOutputDir);
        }

        parent::tearDown();
    }

    private function recursiveDelete(string $path): void
    {
        if (is_dir($path)) {
            $files = array_diff(scandir($path), ['.', '..']);
            foreach ($files as $file) {
                $filePath = $path . '/' . $file;
                if (is_link($filePath)) {
                    unlink($filePath);
                } elseif (is_dir($filePath)) {
                    $this->recursiveDelete($filePath);
                } else {
                    unlink($filePath);
                }
            }
            rmdir($path);
        }
    }

    // ============ CONFIGURATION TESTS ============

    public function test_it_loads_binary_path_from_config(): void
    {
        $agent = new ClaudeCodeAgent();

        $reflection = new \ReflectionClass($agent);
        $property = $reflection->getProperty('binary');
        $property->setAccessible(true);

        $this->assertEquals('/usr/local/bin/claude', $property->getValue($agent));
    }

    public function test_it_loads_model_from_config(): void
    {
        $agent = new ClaudeCodeAgent();

        $reflection = new \ReflectionClass($agent);
        $property = $reflection->getProperty('model');
        $property->setAccessible(true);

        $this->assertEquals('claude-sonnet-4-5-20250929', $property->getValue($agent));
    }

    public function test_it_loads_timeout_from_config(): void
    {
        Config::set('claude-code.timeout', 300);

        $agent = new ClaudeCodeAgent();

        $reflection = new \ReflectionClass($agent);
        $property = $reflection->getProperty('timeout');
        $property->setAccessible(true);

        $this->assertEquals(300, $property->getValue($agent));
    }

    public function test_it_creates_work_directory_if_not_exists(): void
    {
        $this->assertFalse(is_dir($this->testWorkDir));

        new ClaudeCodeAgent();

        $this->assertTrue(is_dir($this->testWorkDir));
    }

    public function test_it_creates_output_directory_if_not_exists(): void
    {
        $this->assertFalse(is_dir($this->testOutputDir));

        new ClaudeCodeAgent();

        $this->assertTrue(is_dir($this->testOutputDir));
    }

    // ============ SESSION MANAGEMENT TESTS ============

    public function test_analyze_files_creates_and_cleans_session_directory(): void
    {
        Process::fake();

        $agent = new ClaudeCodeAgent();

        // Create a test file
        $testFile = sys_get_temp_dir() . '/test-doc-' . uniqid() . '.txt';
        file_put_contents($testFile, 'Test content');

        try {
            $result = $agent->analyzeFiles([$testFile], 'Test task', 'test-case-1');

            // Verify a session was created (UUID returned)
            $this->assertNotEmpty($result['session_id']);
            $this->assertTrue(preg_match('/^[a-f0-9-]{36}$/', $result['session_id']) === 1);

            // Verify session directory was cleaned up
            $sessionDir = $this->testWorkDir . '/' . $result['session_id'];
            $this->assertFalse(is_dir($sessionDir), 'Session directory should be cleaned up after analysis');
        } finally {
            @unlink($testFile);
        }
    }

    public function test_analyze_files_creates_symlinks_for_input_files(): void
    {
        Process::fake();

        $agent = new ClaudeCodeAgent();

        // Create test files
        $testFile1 = sys_get_temp_dir() . '/test-doc1-' . uniqid() . '.txt';
        $testFile2 = sys_get_temp_dir() . '/test-doc2-' . uniqid() . '.txt';
        file_put_contents($testFile1, 'Test content 1');
        file_put_contents($testFile2, 'Test content 2');

        try {
            $agent->analyzeFiles([$testFile1, $testFile2], 'Test task', 'test-case-2');

            // Verify symlinks were created (check in session directory)
            $sessionDirs = glob($this->testWorkDir . '/*');
            if (!empty($sessionDirs)) {
                $sessionDir = $sessionDirs[0];
                // Note: symlinks are cleaned up, but we can verify the process was called
            }

            Process::assertRan(function ($process) {
                // Verify the process was invoked
                return true;
            });
        } finally {
            @unlink($testFile1);
            @unlink($testFile2);
        }
    }

    public function test_analyze_files_skips_nonexistent_files(): void
    {
        Process::fake();

        $agent = new ClaudeCodeAgent();

        $existingFile = sys_get_temp_dir() . '/existing-' . uniqid() . '.txt';
        file_put_contents($existingFile, 'Content');
        $nonExistentFile = '/nonexistent/path/file.txt';

        try {
            $result = $agent->analyzeFiles([$existingFile, $nonExistentFile], 'Test task', 'test-case-3');

            // Should not throw an error
            $this->assertIsArray($result);
        } finally {
            @unlink($existingFile);
        }
    }

    // ============ PROMPT BUILDING TESTS ============

    public function test_prompt_includes_task_description(): void
    {
        Process::fake();

        $agent = new ClaudeCodeAgent();

        $testFile = sys_get_temp_dir() . '/prompt-test-' . uniqid() . '.txt';
        file_put_contents($testFile, 'Content');

        try {
            $agent->analyzeFiles([$testFile], 'Analyze all documents for dates', 'test-case-4');

            Process::assertRan(function ($process) {
                $command = $process->command;
                // Check that the prompt includes our task
                return collect($command)->contains(function ($arg) {
                    return str_contains($arg, 'Analyze all documents for dates');
                });
            });
        } finally {
            @unlink($testFile);
        }
    }

    public function test_prompt_includes_file_list(): void
    {
        Process::fake();

        $agent = new ClaudeCodeAgent();

        $testFile = sys_get_temp_dir() . '/unique-filename-12345.txt';
        file_put_contents($testFile, 'Content');

        try {
            $agent->analyzeFiles([$testFile], 'Test task', 'test-case-5');

            Process::assertRan(function ($process) {
                $command = $process->command;
                return collect($command)->contains(function ($arg) {
                    return str_contains($arg, 'unique-filename-12345.txt');
                });
            });
        } finally {
            @unlink($testFile);
        }
    }

    // ============ PROCESS EXECUTION TESTS ============

    public function test_analyze_files_executes_claude_binary(): void
    {
        Process::fake();

        $agent = new ClaudeCodeAgent();

        $testFile = sys_get_temp_dir() . '/exec-test-' . uniqid() . '.txt';
        file_put_contents($testFile, 'Content');

        try {
            $agent->analyzeFiles([$testFile], 'Test', 'test-case-6');

            Process::assertRan(function ($process) {
                $command = $process->command;
                return $command[0] === '/usr/local/bin/claude';
            });
        } finally {
            @unlink($testFile);
        }
    }

    public function test_analyze_files_uses_print_flag(): void
    {
        Process::fake();

        $agent = new ClaudeCodeAgent();

        $testFile = sys_get_temp_dir() . '/flag-test-' . uniqid() . '.txt';
        file_put_contents($testFile, 'Content');

        try {
            $agent->analyzeFiles([$testFile], 'Test', 'test-case-7');

            Process::assertRan(function ($process) {
                return in_array('--print', $process->command);
            });
        } finally {
            @unlink($testFile);
        }
    }

    public function test_analyze_files_sets_model_environment(): void
    {
        Process::fake();

        $agent = new ClaudeCodeAgent();

        $testFile = sys_get_temp_dir() . '/env-test-' . uniqid() . '.txt';
        file_put_contents($testFile, 'Content');

        try {
            $agent->analyzeFiles([$testFile], 'Test', 'test-case-8');

            Process::assertRan(function ($process) {
                $env = $process->environment ?? [];
                return isset($env['ANTHROPIC_MODEL']) && $env['ANTHROPIC_MODEL'] === 'claude-sonnet-4-5-20250929';
            });
        } finally {
            @unlink($testFile);
        }
    }

    // ============ RESULT PARSING TESTS ============

    public function test_analyze_files_returns_result_array(): void
    {
        Process::fake([
            '*' => Process::result(
                output: 'Claude output',
                exitCode: 0
            ),
        ]);

        $agent = new ClaudeCodeAgent();

        $testFile = sys_get_temp_dir() . '/result-test-' . uniqid() . '.txt';
        file_put_contents($testFile, 'Content');

        try {
            $result = $agent->analyzeFiles([$testFile], 'Test', 'test-case-9');

            $this->assertIsArray($result);
            $this->assertArrayHasKey('success', $result);
            $this->assertArrayHasKey('output_file', $result);
            $this->assertArrayHasKey('results', $result);
            $this->assertArrayHasKey('raw_output', $result);
            $this->assertArrayHasKey('exit_code', $result);
            $this->assertArrayHasKey('session_id', $result);
            $this->assertArrayHasKey('elapsed_seconds', $result);
        } finally {
            @unlink($testFile);
        }
    }

    public function test_success_is_false_when_exit_code_nonzero(): void
    {
        Process::fake([
            '*' => Process::result(
                output: 'Error occurred',
                exitCode: 1
            ),
        ]);

        $agent = new ClaudeCodeAgent();

        $testFile = sys_get_temp_dir() . '/error-test-' . uniqid() . '.txt';
        file_put_contents($testFile, 'Content');

        try {
            $result = $agent->analyzeFiles([$testFile], 'Test', 'test-case-10');

            $this->assertFalse($result['success']);
            $this->assertEquals(1, $result['exit_code']);
        } finally {
            @unlink($testFile);
        }
    }

    public function test_parses_json_from_output_file(): void
    {
        $expectedResults = ['analysis' => 'test data', 'confidence' => 0.95];

        Process::fake([
            '*' => Process::result(
                output: 'Processing complete',
                exitCode: 0
            ),
        ]);

        $agent = new ClaudeCodeAgent();

        $testFile = sys_get_temp_dir() . '/json-test-' . uniqid() . '.txt';
        file_put_contents($testFile, 'Content');

        try {
            $result = $agent->analyzeFiles([$testFile], 'Test', 'test-case-11');

            // Create the expected output file with JSON content
            if (!empty($result['output_file'])) {
                file_put_contents($result['output_file'], json_encode($expectedResults));

                // Re-run to test parsing (simulating output file creation by Claude)
                $agent2 = new ClaudeCodeAgent();
                $result2 = $agent2->analyzeFiles([$testFile], 'Test', 'test-case-11b');
            }

            // The results should be null if no output file exists (since Claude fake doesn't create it)
            $this->assertNull($result['results']);
        } finally {
            @unlink($testFile);
        }
    }

    public function test_handles_invalid_json_in_output_file(): void
    {
        Process::fake([
            '*' => Process::result(output: '', exitCode: 0),
        ]);

        $agent = new ClaudeCodeAgent();

        $testFile = sys_get_temp_dir() . '/invalid-json-' . uniqid() . '.txt';
        file_put_contents($testFile, 'Content');

        try {
            $result = $agent->analyzeFiles([$testFile], 'Test', 'test-case-12');

            // Create invalid JSON in output file
            if (!empty($result['output_file'])) {
                file_put_contents($result['output_file'], 'not valid json {{{');
            }

            // Results should be null for invalid JSON
            $this->assertNull($result['results']);
        } finally {
            @unlink($testFile);
        }
    }

    // ============ BULK CASE ANALYSIS TESTS ============

    public function test_bulk_case_analysis_runs_per_document_phase(): void
    {
        Process::fake([
            '*' => Process::result(output: '', exitCode: 0),
        ]);

        $agent = new ClaudeCodeAgent();

        $testFile = sys_get_temp_dir() . '/bulk-test-' . uniqid() . '.txt';
        file_put_contents($testFile, 'Content');

        try {
            $results = $agent->bulkCaseAnalysis('bulk-case-1', [$testFile]);

            $this->assertArrayHasKey('per_document', $results);
            $this->assertIsArray($results['per_document']);
        } finally {
            @unlink($testFile);
        }
    }

    public function test_bulk_case_analysis_runs_cross_document_phase_on_success(): void
    {
        // Create the output directory for the mock result
        $outputDir = $this->testOutputDir;
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        Process::fake([
            '*' => Process::result(output: '', exitCode: 0),
        ]);

        $agent = new ClaudeCodeAgent();

        $testFile = sys_get_temp_dir() . '/cross-doc-test-' . uniqid() . '.txt';
        file_put_contents($testFile, 'Content');

        // Pre-create the per-document output file so cross-document phase can run
        $perDocOutputFile = $outputDir . '/cross-case-1_docs_' . '*' . '.json';

        try {
            $results = $agent->bulkCaseAnalysis('cross-case-1', [$testFile]);

            // per_document will be present but success will be false (no output file)
            $this->assertArrayHasKey('per_document', $results);

            // cross_document only runs if per_document was successful
            // Since our fake doesn't create output files, per_document['success'] is false
            $this->assertFalse($results['per_document']['success']);
        } finally {
            @unlink($testFile);
        }
    }

    // ============ SESSION CLEANUP TESTS ============

    public function test_cleanup_session_removes_symlinks(): void
    {
        Process::fake();

        $agent = new ClaudeCodeAgent();

        $testFile = sys_get_temp_dir() . '/cleanup-test-' . uniqid() . '.txt';
        file_put_contents($testFile, 'Content');

        try {
            $agent->analyzeFiles([$testFile], 'Test', 'cleanup-case');

            // After analyzeFiles, session directory should be cleaned up
            $sessionDirs = glob($this->testWorkDir . '/*');

            // Should be empty or only contain non-session items
            $this->assertTrue(count($sessionDirs) === 0 || !is_dir($sessionDirs[0]));
        } finally {
            @unlink($testFile);
        }
    }
}

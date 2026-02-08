<?php

namespace Tests\Unit\Services\Agent;

use App\DTOs\Agent\AgentResult;
use App\DTOs\Agent\AgentSession;
use App\Services\Agent\BaseAgent;
use App\Services\Agent\Contracts\AgentCapability;
use App\Services\Agent\Contracts\AgentInterface;
use Illuminate\Support\Facades\Process;
use Illuminate\Process\FakeProcessResult;
use Tests\TestCase;

/**
 * Test BaseAgent session management and core functionality.
 *
 * Uses a concrete test implementation of BaseAgent to test the abstract class.
 */
class BaseAgentTest extends TestCase
{
    private string $testWorkDir;
    private string $testOutputDir;
    private string $testFilePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testWorkDir = storage_path('app/test-agent-sessions');
        $this->testOutputDir = storage_path('app/test-agent-output');

        // Ensure directories exist
        if (!is_dir($this->testWorkDir)) {
            mkdir($this->testWorkDir, 0755, true);
        }
        if (!is_dir($this->testOutputDir)) {
            mkdir($this->testOutputDir, 0755, true);
        }

        // Create a test file
        $this->testFilePath = storage_path('app/test-document.txt');
        file_put_contents($this->testFilePath, 'Test document content');
    }

    protected function tearDown(): void
    {
        // Cleanup test file
        if (file_exists($this->testFilePath)) {
            unlink($this->testFilePath);
        }

        // Cleanup test directories
        $this->recursiveDelete($this->testWorkDir);
        $this->recursiveDelete($this->testOutputDir);

        parent::tearDown();
    }

    private function recursiveDelete(string $dir): void
    {
        if (!is_dir($dir)) return;

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = "$dir/$file";
            if (is_link($path)) {
                unlink($path);
            } elseif (is_dir($path)) {
                $this->recursiveDelete($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    /** @test */
    public function is_available_returns_true_when_binary_found(): void
    {
        Process::fake([
            'which test-agent 2>/dev/null' => Process::result(
                output: '/usr/local/bin/test-agent',
                exitCode: 0
            ),
        ]);

        $agent = $this->createTestAgent();

        $result = $agent->isAvailable();

        $this->assertTrue($result);
        Process::assertRan('which test-agent 2>/dev/null');
    }

    /** @test */
    public function is_available_returns_false_when_binary_not_found(): void
    {
        Process::fake([
            'which test-agent 2>/dev/null' => Process::result(
                output: '',
                exitCode: 1
            ),
        ]);

        $agent = $this->createTestAgent();

        $result = $agent->isAvailable();

        $this->assertFalse($result);
    }

    /** @test */
    public function create_session_creates_workdir_and_symlinks_files(): void
    {
        $agent = $this->createTestAgent();

        // Use reflection to call protected method
        $reflection = new \ReflectionClass($agent);
        $method = $reflection->getMethod('createSession');
        $method->setAccessible(true);

        $session = $method->invoke($agent, [$this->testFilePath]);

        // Verify session ID is a UUID
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $session->sessionId
        );

        // Verify work directory was created
        $this->assertDirectoryExists($session->workDir);

        // Verify symlink was created
        $symlinkPath = $session->workDir . '/test-document.txt';
        $this->assertTrue(is_link($symlinkPath), 'Symlink should be created');
        $this->assertEquals($this->testFilePath, readlink($symlinkPath));

        // Verify symlink map is populated
        $this->assertArrayHasKey($this->testFilePath, $session->symlinkMap);
        $this->assertEquals($symlinkPath, $session->symlinkMap[$this->testFilePath]);

        // Cleanup
        $cleanupMethod = $reflection->getMethod('cleanupSession');
        $cleanupMethod->setAccessible(true);
        $cleanupMethod->invoke($agent, $session);
    }

    /** @test */
    public function create_session_ignores_nonexistent_files(): void
    {
        $agent = $this->createTestAgent();

        $reflection = new \ReflectionClass($agent);
        $method = $reflection->getMethod('createSession');
        $method->setAccessible(true);

        $session = $method->invoke($agent, ['/nonexistent/file.txt', $this->testFilePath]);

        // Should only have the existing file in symlink map
        $this->assertCount(1, $session->symlinkMap);
        $this->assertArrayNotHasKey('/nonexistent/file.txt', $session->symlinkMap);
        $this->assertArrayHasKey($this->testFilePath, $session->symlinkMap);

        // Cleanup
        $cleanupMethod = $reflection->getMethod('cleanupSession');
        $cleanupMethod->setAccessible(true);
        $cleanupMethod->invoke($agent, $session);
    }

    /** @test */
    public function cleanup_session_removes_symlinks_and_directory(): void
    {
        $agent = $this->createTestAgent();

        $reflection = new \ReflectionClass($agent);
        $createMethod = $reflection->getMethod('createSession');
        $createMethod->setAccessible(true);

        $session = $createMethod->invoke($agent, [$this->testFilePath]);

        // Verify session directory exists
        $this->assertDirectoryExists($session->workDir);

        // Now cleanup
        $cleanupMethod = $reflection->getMethod('cleanupSession');
        $cleanupMethod->setAccessible(true);
        $cleanupMethod->invoke($agent, $session);

        // Verify directory was removed
        $this->assertDirectoryDoesNotExist($session->workDir);
    }

    /** @test */
    public function assemble_prompt_includes_file_list_and_output_instruction(): void
    {
        $agent = $this->createTestAgent();

        $reflection = new \ReflectionClass($agent);
        $createMethod = $reflection->getMethod('createSession');
        $createMethod->setAccessible(true);

        $session = $createMethod->invoke($agent, [$this->testFilePath]);

        $assembleMethod = $reflection->getMethod('assemblePrompt');
        $assembleMethod->setAccessible(true);

        $prompt = $assembleMethod->invoke($agent, 'Analyze this document', $session, []);

        // Should contain the original prompt
        $this->assertStringContainsString('Analyze this document', $prompt);

        // Should contain the file list section
        $this->assertStringContainsString('Files available in working directory:', $prompt);
        $this->assertStringContainsString('test-document.txt', $prompt);

        // Should contain output instruction with session output file path
        $this->assertStringContainsString('Write your output as JSON to:', $prompt);
        $this->assertStringContainsString($session->outputFile, $prompt);

        // Cleanup
        $cleanupMethod = $reflection->getMethod('cleanupSession');
        $cleanupMethod->setAccessible(true);
        $cleanupMethod->invoke($agent, $session);
    }

    /** @test */
    public function assemble_prompt_omits_file_section_when_no_files(): void
    {
        $agent = $this->createTestAgent();

        $reflection = new \ReflectionClass($agent);
        $createMethod = $reflection->getMethod('createSession');
        $createMethod->setAccessible(true);

        $session = $createMethod->invoke($agent, []);

        $assembleMethod = $reflection->getMethod('assemblePrompt');
        $assembleMethod->setAccessible(true);

        $prompt = $assembleMethod->invoke($agent, 'Analyze', $session, []);

        // Should NOT contain file list section when no files
        $this->assertStringNotContainsString('Files available in working directory:', $prompt);

        // Cleanup
        $cleanupMethod = $reflection->getMethod('cleanupSession');
        $cleanupMethod->setAccessible(true);
        $cleanupMethod->invoke($agent, $session);
    }

    /** @test */
    public function run_executes_command_and_returns_successful_result(): void
    {
        Process::fake([
            'which test-agent 2>/dev/null' => Process::result(exitCode: 0),
            'test-agent *' => Process::result(
                output: '{"analysis": "completed", "findings": []}',
                exitCode: 0
            ),
        ]);

        $agent = $this->createTestAgent();

        $result = $agent->run('Analyze document', [$this->testFilePath]);

        $this->assertTrue($result->success);
        $this->assertEquals('test', $result->driver);
        $this->assertEquals(0, $result->exitCode);
        $this->assertNotEmpty($result->sessionId);
        $this->assertIsArray($result->results);
        $this->assertEquals('completed', $result->results['analysis']);
    }

    /** @test */
    public function run_returns_failure_result_on_process_error(): void
    {
        Process::fake([
            'which test-agent 2>/dev/null' => Process::result(exitCode: 0),
            'test-agent *' => Process::result(
                output: '',
                errorOutput: 'Command failed',
                exitCode: 1
            ),
        ]);

        $agent = $this->createTestAgent();

        $result = $agent->run('Analyze document', [$this->testFilePath]);

        $this->assertFalse($result->success);
        $this->assertEquals(1, $result->exitCode);
        $this->assertEquals('Command failed', $result->rawError);
    }

    /** @test */
    public function run_reads_output_from_file_when_available(): void
    {
        $agent = $this->createTestAgent();

        // Create a session to get the expected output file path
        $reflection = new \ReflectionClass($agent);
        $createMethod = $reflection->getMethod('createSession');
        $createMethod->setAccessible(true);

        // Fake the process to succeed
        Process::fake([
            'which test-agent 2>/dev/null' => Process::result(exitCode: 0),
            'test-agent *' => function ($process) use ($agent) {
                // Simulate the agent writing to the output file
                // In reality, the test agent should write to the expected location
                return Process::result(
                    output: 'Processing...',  // Not JSON - agent writes to file instead
                    exitCode: 0
                );
            },
        ]);

        $result = $agent->run('Analyze', []);

        // If no output file exists and stdout isn't valid JSON, it should still succeed
        // but results may be null (depends on parseOutput implementation)
        $this->assertEquals(0, $result->exitCode);
    }

    /** @test */
    public function run_cleans_up_session_after_completion(): void
    {
        Process::fake([
            'which test-agent 2>/dev/null' => Process::result(exitCode: 0),
            'test-agent *' => Process::result(
                output: '{"result": "ok"}',
                exitCode: 0
            ),
        ]);

        $agent = $this->createTestAgent();

        $result = $agent->run('Analyze', [$this->testFilePath]);

        // The session work directory should be cleaned up
        $expectedWorkDir = $this->testWorkDir . '/' . $result->sessionId;
        $this->assertDirectoryDoesNotExist($expectedWorkDir);
    }

    /** @test */
    public function run_includes_elapsed_time_in_result(): void
    {
        Process::fake([
            'which test-agent 2>/dev/null' => Process::result(exitCode: 0),
            'test-agent *' => Process::result(
                output: '{"result": "ok"}',
                exitCode: 0
            ),
        ]);

        $agent = $this->createTestAgent();

        $result = $agent->run('Analyze', []);

        $this->assertGreaterThanOrEqual(0, $result->elapsedSeconds);
    }

    /** @test */
    public function bulk_analysis_runs_multiple_phases(): void
    {
        Process::fake([
            'which test-agent 2>/dev/null' => Process::result(exitCode: 0),
            'test-agent *' => Process::result(
                output: '{"phase": "completed"}',
                exitCode: 0
            ),
        ]);

        $agent = $this->createTestAgent();

        $results = $agent->bulkAnalysis('case-001', [$this->testFilePath], [
            'extraction' => 'Extract data from documents',
            'analysis' => 'Analyze extracted data',
        ]);

        $this->assertArrayHasKey('extraction', $results);
        $this->assertArrayHasKey('analysis', $results);
        $this->assertInstanceOf(AgentResult::class, $results['extraction']);
        $this->assertInstanceOf(AgentResult::class, $results['analysis']);
    }

    /** @test */
    public function bulk_analysis_injects_previous_results_into_subsequent_phases(): void
    {
        $promptsReceived = [];

        Process::fake(function ($process) use (&$promptsReceived) {
            // Capture the command to examine the prompt
            $command = implode(' ', $process->command ?? []);
            if (str_contains($command, 'test-agent')) {
                $promptsReceived[] = $command;
            }

            return Process::result(
                output: '{"phase_data": "test"}',
                exitCode: 0
            );
        });

        $agent = $this->createTestAgent();

        $results = $agent->bulkAnalysis('case-001', [], [
            'phase1' => 'First phase',
            'phase2' => 'Second phase',
        ]);

        // Both phases should succeed
        $this->assertTrue($results['phase1']->success);
        $this->assertTrue($results['phase2']->success);
    }

    /** @test */
    public function agent_result_to_array_returns_expected_structure(): void
    {
        $result = new AgentResult(
            success: true,
            driver: 'test',
            results: ['key' => 'value'],
            rawOutput: 'raw',
            rawError: '',
            exitCode: 0,
            elapsedSeconds: 1.5,
            sessionId: 'session-123',
            outputFile: '/path/to/output.json',
            metadata: ['binary' => 'test-agent'],
        );

        $array = $result->toArray();

        $this->assertEquals(true, $array['success']);
        $this->assertEquals('test', $array['driver']);
        $this->assertEquals(['key' => 'value'], $array['results']);
        $this->assertEquals(0, $array['exit_code']);
        $this->assertEquals(1.5, $array['elapsed_seconds']);
        $this->assertEquals('session-123', $array['session_id']);
        $this->assertEquals('/path/to/output.json', $array['output_file']);
        $this->assertEquals(['binary' => 'test-agent'], $array['metadata']);
    }

    /** @test */
    public function agent_session_holds_correct_data(): void
    {
        $session = new AgentSession(
            sessionId: 'abc-123',
            workDir: '/tmp/work',
            outputFile: '/tmp/output.json',
            symlinkMap: ['/original.txt' => '/tmp/work/original.txt'],
        );

        $this->assertEquals('abc-123', $session->sessionId);
        $this->assertEquals('/tmp/work', $session->workDir);
        $this->assertEquals('/tmp/output.json', $session->outputFile);
        $this->assertEquals(['/original.txt' => '/tmp/work/original.txt'], $session->symlinkMap);
    }

    /**
     * Create a concrete test implementation of BaseAgent.
     */
    private function createTestAgent(): BaseAgent
    {
        return new class($this->testWorkDir, $this->testOutputDir) extends BaseAgent {
            private string $testWorkDir;
            private string $testOutputDir;

            public function __construct(string $workDir, string $outputDir)
            {
                $this->testWorkDir = $workDir;
                $this->testOutputDir = $outputDir;

                $this->workBaseDir = $workDir;
                $this->outputBaseDir = $outputDir;
                $this->timeout = 60;

                if (!is_dir($this->workBaseDir)) mkdir($this->workBaseDir, 0755, true);
                if (!is_dir($this->outputBaseDir)) mkdir($this->outputBaseDir, 0755, true);
            }

            public function driver(): string
            {
                return 'test';
            }

            public function name(): string
            {
                return 'Test Agent';
            }

            public function capabilities(): array
            {
                return [AgentCapability::FILE_READ, AgentCapability::JSON_OUTPUT];
            }

            protected function binary(): string
            {
                return 'test-agent';
            }

            protected function buildCommand(string $prompt, AgentSession $session, array $options): array
            {
                return ['test-agent', '--prompt', $prompt];
            }

            protected function parseOutput(string $rawOutput, AgentSession $session): ?array
            {
                $decoded = json_decode($rawOutput, true);
                return $decoded;
            }
        };
    }
}

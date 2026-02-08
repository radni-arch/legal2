<?php

namespace Tests\Unit\Services\Agent\Drivers;

use App\DTOs\Agent\AgentSession;
use App\Services\Agent\Contracts\AgentCapability;
use App\Services\Agent\Drivers\GenericCliDriver;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * Test GenericCliDriver - the configurable base for custom CLI agents.
 */
class GenericCliDriverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Set up base config for testing
        Config::set('agents.drivers.test_driver', [
            'binary' => 'test-cli',
            'timeout' => 300,
            'work_dir' => storage_path('app/test-agent-sessions'),
            'output_dir' => storage_path('app/test-agent-output'),
            'system_prompt' => 'Test system prompt.',
            'command_template' => '{binary} --prompt {prompt}',
            'capabilities' => ['file_read', 'json_output'],
            'env' => [
                'TEST_API_KEY' => 'test-key-123',
            ],
        ]);
    }

    /** @test */
    public function driver_returns_configured_driver_name(): void
    {
        $driver = new GenericCliDriver('test_driver');

        $this->assertEquals('test_driver', $driver->driver());
    }

    /** @test */
    public function name_returns_formatted_driver_name(): void
    {
        $driver = new GenericCliDriver('test_driver');

        // Should return "Test Driver" (title case from test_driver)
        $this->assertEquals('Test Driver', $driver->name());
    }

    /** @test */
    public function capabilities_returns_configured_capabilities_as_enums(): void
    {
        $driver = new GenericCliDriver('test_driver');

        $capabilities = $driver->capabilities();

        $this->assertContains(AgentCapability::FILE_READ, $capabilities);
        $this->assertContains(AgentCapability::JSON_OUTPUT, $capabilities);
    }

    /** @test */
    public function capabilities_returns_empty_array_when_not_configured(): void
    {
        Config::set('agents.drivers.empty_driver', [
            'binary' => 'empty-cli',
            'timeout' => 60,
            'work_dir' => storage_path('app/test-sessions'),
            'output_dir' => storage_path('app/test-output'),
            'command_template' => '{binary} {prompt}',
            // No capabilities key
        ]);

        $driver = new GenericCliDriver('empty_driver');

        $this->assertIsArray($driver->capabilities());
        $this->assertEmpty($driver->capabilities());
    }

    /** @test */
    public function binary_returns_configured_binary_path(): void
    {
        $driver = new GenericCliDriver('test_driver');

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($driver);
        $method = $reflection->getMethod('binary');
        $method->setAccessible(true);

        $this->assertEquals('test-cli', $method->invoke($driver));
    }

    /** @test */
    public function environment_returns_configured_environment_variables(): void
    {
        $driver = new GenericCliDriver('test_driver');

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($driver);
        $method = $reflection->getMethod('environment');
        $method->setAccessible(true);

        $env = $method->invoke($driver, []);

        $this->assertArrayHasKey('TEST_API_KEY', $env);
        $this->assertEquals('test-key-123', $env['TEST_API_KEY']);
    }

    /** @test */
    public function build_command_uses_template_with_binary_and_prompt_placeholders(): void
    {
        $driver = new GenericCliDriver('test_driver');

        $reflection = new \ReflectionClass($driver);
        $method = $reflection->getMethod('buildCommand');
        $method->setAccessible(true);

        $session = new AgentSession(
            sessionId: 'test-session',
            workDir: '/tmp/test-work',
            outputFile: '/tmp/test-output.json',
            symlinkMap: [],
        );

        $command = $method->invoke($driver, 'Analyze this file', $session, []);

        // Command should be array with replaced placeholders
        $this->assertIsArray($command);
        $commandStr = implode(' ', $command);
        $this->assertStringContainsString('test-cli', $commandStr);
        $this->assertStringContainsString('Analyze this file', $commandStr);
    }

    /** @test */
    public function build_command_supports_prompt_file_placeholder(): void
    {
        Config::set('agents.drivers.file_prompt_driver', [
            'binary' => 'file-cli',
            'timeout' => 60,
            'work_dir' => storage_path('app/test-sessions'),
            'output_dir' => storage_path('app/test-output'),
            'command_template' => '{binary} --file {prompt_file}',
        ]);

        $driver = new GenericCliDriver('file_prompt_driver');

        $reflection = new \ReflectionClass($driver);
        $method = $reflection->getMethod('buildCommand');
        $method->setAccessible(true);

        $session = new AgentSession(
            sessionId: 'test-session',
            workDir: '/tmp/test-work',
            outputFile: '/tmp/test-output.json',
            symlinkMap: [],
        );

        $command = $method->invoke($driver, 'Long prompt content', $session, []);

        // Should have written prompt to a temp file
        $commandStr = implode(' ', $command);
        $this->assertStringContainsString('--file', $commandStr);
        // Prompt file path should be in work directory
        $this->assertStringContainsString('/tmp/test-work/', $commandStr);
    }

    /** @test */
    public function build_command_supports_output_file_placeholder(): void
    {
        Config::set('agents.drivers.output_driver', [
            'binary' => 'output-cli',
            'timeout' => 60,
            'work_dir' => storage_path('app/test-sessions'),
            'output_dir' => storage_path('app/test-output'),
            'command_template' => '{binary} --prompt {prompt} --output {output_file}',
        ]);

        $driver = new GenericCliDriver('output_driver');

        $reflection = new \ReflectionClass($driver);
        $method = $reflection->getMethod('buildCommand');
        $method->setAccessible(true);

        $session = new AgentSession(
            sessionId: 'test-session',
            workDir: '/tmp/test-work',
            outputFile: '/tmp/test-output.json',
            symlinkMap: [],
        );

        $command = $method->invoke($driver, 'Test', $session, []);

        $commandStr = implode(' ', $command);
        $this->assertStringContainsString('--output', $commandStr);
        $this->assertStringContainsString('/tmp/test-output.json', $commandStr);
    }

    /** @test */
    public function build_command_supports_work_dir_placeholder(): void
    {
        Config::set('agents.drivers.workdir_driver', [
            'binary' => 'workdir-cli',
            'timeout' => 60,
            'work_dir' => storage_path('app/test-sessions'),
            'output_dir' => storage_path('app/test-output'),
            'command_template' => '{binary} --cwd {work_dir} --prompt {prompt}',
        ]);

        $driver = new GenericCliDriver('workdir_driver');

        $reflection = new \ReflectionClass($driver);
        $method = $reflection->getMethod('buildCommand');
        $method->setAccessible(true);

        $session = new AgentSession(
            sessionId: 'test-session',
            workDir: '/tmp/test-work',
            outputFile: '/tmp/test-output.json',
            symlinkMap: [],
        );

        $command = $method->invoke($driver, 'Test', $session, []);

        $commandStr = implode(' ', $command);
        $this->assertStringContainsString('--cwd', $commandStr);
        $this->assertStringContainsString('/tmp/test-work', $commandStr);
    }

    /** @test */
    public function parse_output_decodes_json_from_raw_output(): void
    {
        $driver = new GenericCliDriver('test_driver');

        $reflection = new \ReflectionClass($driver);
        $method = $reflection->getMethod('parseOutput');
        $method->setAccessible(true);

        $session = new AgentSession(
            sessionId: 'test-session',
            workDir: '/tmp/test-work',
            outputFile: '/tmp/test-output.json',
            symlinkMap: [],
        );

        $rawOutput = '{"result": "success", "data": [1, 2, 3]}';
        $parsed = $method->invoke($driver, $rawOutput, $session);

        $this->assertIsArray($parsed);
        $this->assertEquals('success', $parsed['result']);
        $this->assertEquals([1, 2, 3], $parsed['data']);
    }

    /** @test */
    public function parse_output_returns_null_for_invalid_json(): void
    {
        $driver = new GenericCliDriver('test_driver');

        $reflection = new \ReflectionClass($driver);
        $method = $reflection->getMethod('parseOutput');
        $method->setAccessible(true);

        $session = new AgentSession(
            sessionId: 'test-session',
            workDir: '/tmp/test-work',
            outputFile: '/tmp/test-output.json',
            symlinkMap: [],
        );

        $rawOutput = 'This is not JSON';
        $parsed = $method->invoke($driver, $rawOutput, $session);

        $this->assertNull($parsed);
    }

    /** @test */
    public function parse_output_extracts_json_from_markdown_code_block(): void
    {
        $driver = new GenericCliDriver('test_driver');

        $reflection = new \ReflectionClass($driver);
        $method = $reflection->getMethod('parseOutput');
        $method->setAccessible(true);

        $session = new AgentSession(
            sessionId: 'test-session',
            workDir: '/tmp/test-work',
            outputFile: '/tmp/test-output.json',
            symlinkMap: [],
        );

        $rawOutput = "Here is the result:\n```json\n{\"result\": \"extracted\"}\n```\nEnd of output.";
        $parsed = $method->invoke($driver, $rawOutput, $session);

        $this->assertIsArray($parsed);
        $this->assertEquals('extracted', $parsed['result']);
    }

    /** @test */
    public function is_available_checks_binary_exists(): void
    {
        Process::fake([
            'which test-cli 2>/dev/null' => Process::result(
                output: '/usr/local/bin/test-cli',
                exitCode: 0
            ),
        ]);

        $driver = new GenericCliDriver('test_driver');

        $this->assertTrue($driver->isAvailable());
        Process::assertRan('which test-cli 2>/dev/null');
    }

    /** @test */
    public function run_executes_command_and_returns_result(): void
    {
        Process::fake([
            'which test-cli 2>/dev/null' => Process::result(exitCode: 0),
            '*test-cli*' => Process::result(
                output: '{"status": "completed"}',
                exitCode: 0
            ),
        ]);

        $driver = new GenericCliDriver('test_driver');

        $result = $driver->run('Test prompt');

        $this->assertTrue($result->success);
        $this->assertEquals('test_driver', $result->driver);
        $this->assertEquals('completed', $result->results['status']);
    }
}

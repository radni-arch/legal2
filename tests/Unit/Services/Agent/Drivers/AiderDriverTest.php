<?php

namespace Tests\Unit\Services\Agent\Drivers;

use App\DTOs\Agent\AgentSession;
use App\Services\Agent\Contracts\AgentCapability;
use App\Services\Agent\Drivers\AiderDriver;
use App\Services\Agent\Drivers\GenericCliDriver;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * Test AiderDriver - driver for Aider CLI that extends GenericCliDriver.
 */
class AiderDriverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Set up config for testing
        Config::set('agents.drivers.aider', [
            'binary' => 'aider',
            'timeout' => 600,
            'work_dir' => storage_path('app/test-agent-sessions'),
            'output_dir' => storage_path('app/test-agent-output'),
            'system_prompt' => '',
            'model' => 'gpt-4',
            'command_template' => '{binary} --yes --no-git --message {prompt}',
            'capabilities' => ['file_read', 'file_write', 'bash', 'json_output'],
            'env' => [
                'OPENAI_API_KEY' => 'test-openai-key',
                'ANTHROPIC_API_KEY' => 'test-anthropic-key',
            ],
        ]);
    }

    /** @test */
    public function extends_generic_cli_driver(): void
    {
        $driver = new AiderDriver();

        $this->assertInstanceOf(GenericCliDriver::class, $driver);
    }

    /** @test */
    public function driver_returns_aider(): void
    {
        $driver = new AiderDriver();

        $this->assertEquals('aider', $driver->driver());
    }

    /** @test */
    public function name_returns_aider(): void
    {
        $driver = new AiderDriver();

        $this->assertEquals('Aider', $driver->name());
    }

    /** @test */
    public function capabilities_returns_configured_capabilities(): void
    {
        $driver = new AiderDriver();

        $capabilities = $driver->capabilities();

        $this->assertContains(AgentCapability::FILE_READ, $capabilities);
        $this->assertContains(AgentCapability::FILE_WRITE, $capabilities);
        $this->assertContains(AgentCapability::BASH, $capabilities);
        $this->assertContains(AgentCapability::JSON_OUTPUT, $capabilities);
    }

    /** @test */
    public function binary_returns_configured_binary(): void
    {
        $driver = new AiderDriver();

        $reflection = new \ReflectionClass($driver);
        $method = $reflection->getMethod('binary');
        $method->setAccessible(true);

        $this->assertEquals('aider', $method->invoke($driver));
    }

    /** @test */
    public function environment_includes_api_keys(): void
    {
        $driver = new AiderDriver();

        $reflection = new \ReflectionClass($driver);
        $method = $reflection->getMethod('environment');
        $method->setAccessible(true);

        $env = $method->invoke($driver, []);

        $this->assertArrayHasKey('OPENAI_API_KEY', $env);
        $this->assertEquals('test-openai-key', $env['OPENAI_API_KEY']);
        $this->assertArrayHasKey('ANTHROPIC_API_KEY', $env);
        $this->assertEquals('test-anthropic-key', $env['ANTHROPIC_API_KEY']);
    }

    /** @test */
    public function build_command_uses_aider_command_template(): void
    {
        $driver = new AiderDriver();

        $reflection = new \ReflectionClass($driver);
        $method = $reflection->getMethod('buildCommand');
        $method->setAccessible(true);

        $session = new AgentSession(
            sessionId: 'test-session',
            workDir: '/tmp/test-work',
            outputFile: '/tmp/test-output.json',
            symlinkMap: [],
        );

        $command = $method->invoke($driver, 'Fix this bug', $session, []);

        // Should be an array
        $this->assertIsArray($command);
        
        // Should contain the binary
        $this->assertEquals('aider', $command[0]);
        
        // Should contain --yes flag
        $this->assertContains('--yes', $command);
        
        // Should contain --no-git flag
        $this->assertContains('--no-git', $command);
        
        // Should contain --message flag with prompt
        $this->assertContains('--message', $command);
        $messageIndex = array_search('--message', $command);
        $this->assertStringContainsString('Fix this bug', $command[$messageIndex + 1]);
    }

    /** @test */
    public function parse_output_decodes_json(): void
    {
        $driver = new AiderDriver();

        $reflection = new \ReflectionClass($driver);
        $method = $reflection->getMethod('parseOutput');
        $method->setAccessible(true);

        $session = new AgentSession(
            sessionId: 'test-session',
            workDir: '/tmp/test-work',
            outputFile: '/tmp/test-output.json',
            symlinkMap: [],
        );

        $rawOutput = '{"status": "fixed", "files_changed": ["src/bug.php"]}';
        $parsed = $method->invoke($driver, $rawOutput, $session);

        $this->assertIsArray($parsed);
        $this->assertEquals('fixed', $parsed['status']);
        $this->assertEquals(['src/bug.php'], $parsed['files_changed']);
    }

    /** @test */
    public function is_available_checks_aider_binary(): void
    {
        Process::fake([
            'which aider 2>/dev/null' => Process::result(
                output: '/usr/local/bin/aider',
                exitCode: 0
            ),
        ]);

        $driver = new AiderDriver();

        $this->assertTrue($driver->isAvailable());
        Process::assertRan('which aider 2>/dev/null');
    }

    /** @test */
    public function run_executes_aider_command_and_returns_result(): void
    {
        Process::fake([
            'which aider 2>/dev/null' => Process::result(exitCode: 0),
            '*aider*' => Process::result(
                output: '{"status": "completed", "edits": 5}',
                exitCode: 0
            ),
        ]);

        $driver = new AiderDriver();

        $result = $driver->run('Fix the bug');

        $this->assertTrue($result->success);
        $this->assertEquals('aider', $result->driver);
        $this->assertEquals('completed', $result->results['status']);
        $this->assertEquals(5, $result->results['edits']);
    }
}

<?php

namespace Tests\Unit\Services\Agent\Drivers;

use App\DTOs\Agent\AgentSession;
use App\Services\Agent\Contracts\AgentCapability;
use App\Services\Agent\Drivers\ClaudeCodeDriver;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * Test ClaudeCodeDriver - driver for the Claude CLI (Claude Code).
 */
class ClaudeCodeDriverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Set up config for testing
        Config::set('agents.drivers.claude', [
            'binary' => 'claude',
            'timeout' => 600,
            'work_dir' => storage_path('app/test-agent-sessions'),
            'output_dir' => storage_path('app/test-agent-output'),
            'system_prompt' => '',
            'max_turns' => 25,
            'allowed_tools' => 'bash,file_read,file_write',
            'env' => [
                'ANTHROPIC_API_KEY' => 'test-anthropic-key',
            ],
        ]);
    }

    /** @test */
    public function driver_returns_claude(): void
    {
        $driver = new ClaudeCodeDriver();

        $this->assertEquals('claude', $driver->driver());
    }

    /** @test */
    public function name_returns_claude_code(): void
    {
        $driver = new ClaudeCodeDriver();

        $this->assertEquals('Claude Code', $driver->name());
    }

    /** @test */
    public function capabilities_returns_expected_capabilities(): void
    {
        $driver = new ClaudeCodeDriver();

        $capabilities = $driver->capabilities();

        $this->assertContains(AgentCapability::FILE_READ, $capabilities);
        $this->assertContains(AgentCapability::FILE_WRITE, $capabilities);
        $this->assertContains(AgentCapability::BASH, $capabilities);
        $this->assertContains(AgentCapability::MULTI_TURN, $capabilities);
        $this->assertContains(AgentCapability::EXTENDED_THINKING, $capabilities);
        $this->assertContains(AgentCapability::JSON_OUTPUT, $capabilities);
    }

    /** @test */
    public function binary_returns_configured_binary(): void
    {
        $driver = new ClaudeCodeDriver();

        $reflection = new \ReflectionClass($driver);
        $method = $reflection->getMethod('binary');
        $method->setAccessible(true);

        $this->assertEquals('claude', $method->invoke($driver));
    }

    /** @test */
    public function binary_uses_default_when_not_configured(): void
    {
        Config::set('agents.drivers.claude.binary', null);

        $driver = new ClaudeCodeDriver();

        $reflection = new \ReflectionClass($driver);
        $method = $reflection->getMethod('binary');
        $method->setAccessible(true);

        $this->assertEquals('claude', $method->invoke($driver));
    }

    /** @test */
    public function environment_includes_anthropic_api_key(): void
    {
        $driver = new ClaudeCodeDriver();

        $reflection = new \ReflectionClass($driver);
        $method = $reflection->getMethod('environment');
        $method->setAccessible(true);

        $env = $method->invoke($driver, []);

        $this->assertArrayHasKey('ANTHROPIC_API_KEY', $env);
        $this->assertEquals('test-anthropic-key', $env['ANTHROPIC_API_KEY']);
    }

    /** @test */
    public function build_command_includes_required_flags(): void
    {
        $driver = new ClaudeCodeDriver();

        $reflection = new \ReflectionClass($driver);
        $method = $reflection->getMethod('buildCommand');
        $method->setAccessible(true);

        $session = new AgentSession(
            sessionId: 'test-session',
            workDir: '/tmp/test-work',
            outputFile: '/tmp/test-output.json',
            symlinkMap: [],
        );

        $command = $method->invoke($driver, 'Analyze this code', $session, []);

        // Should be an array
        $this->assertIsArray($command);
        
        // Should contain the binary
        $this->assertEquals('claude', $command[0]);
        
        // Should contain --print flag
        $this->assertContains('--print', $command);
        
        // Should contain --output-format json
        $this->assertContains('--output-format', $command);
        $outputFormatIndex = array_search('--output-format', $command);
        $this->assertEquals('json', $command[$outputFormatIndex + 1]);
        
        // Should contain --max-turns
        $this->assertContains('--max-turns', $command);
        $maxTurnsIndex = array_search('--max-turns', $command);
        $this->assertEquals('25', $command[$maxTurnsIndex + 1]);
        
        // Should contain --allowedTools
        $this->assertContains('--allowedTools', $command);
        $toolsIndex = array_search('--allowedTools', $command);
        $this->assertEquals('bash,file_read,file_write', $command[$toolsIndex + 1]);
        
        // Should contain -p followed by prompt
        $this->assertContains('-p', $command);
        $promptIndex = array_search('-p', $command);
        $this->assertStringContainsString('Analyze this code', $command[$promptIndex + 1]);
    }

    /** @test */
    public function build_command_uses_configured_max_turns(): void
    {
        Config::set('agents.drivers.claude.max_turns', 50);

        $driver = new ClaudeCodeDriver();

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

        $maxTurnsIndex = array_search('--max-turns', $command);
        $this->assertEquals('50', $command[$maxTurnsIndex + 1]);
    }

    /** @test */
    public function build_command_uses_configured_allowed_tools(): void
    {
        Config::set('agents.drivers.claude.allowed_tools', 'bash,file_read');

        $driver = new ClaudeCodeDriver();

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

        $toolsIndex = array_search('--allowedTools', $command);
        $this->assertEquals('bash,file_read', $command[$toolsIndex + 1]);
    }

    /** @test */
    public function parse_output_extracts_text_from_claude_json_response(): void
    {
        $driver = new ClaudeCodeDriver();

        $reflection = new \ReflectionClass($driver);
        $method = $reflection->getMethod('parseOutput');
        $method->setAccessible(true);

        $session = new AgentSession(
            sessionId: 'test-session',
            workDir: '/tmp/test-work',
            outputFile: '/tmp/test-output.json',
            symlinkMap: [],
        );

        // Claude returns JSON with content array containing text blocks
        $claudeResponse = json_encode([
            'id' => 'msg_123',
            'type' => 'message',
            'content' => [
                [
                    'type' => 'text',
                    'text' => '{"analysis": "complete", "findings": ["item1", "item2"]}',
                ],
            ],
        ]);

        $parsed = $method->invoke($driver, $claudeResponse, $session);

        $this->assertIsArray($parsed);
        $this->assertEquals('complete', $parsed['analysis']);
        $this->assertEquals(['item1', 'item2'], $parsed['findings']);
    }

    /** @test */
    public function parse_output_extracts_json_from_markdown_in_text(): void
    {
        $driver = new ClaudeCodeDriver();

        $reflection = new \ReflectionClass($driver);
        $method = $reflection->getMethod('parseOutput');
        $method->setAccessible(true);

        $session = new AgentSession(
            sessionId: 'test-session',
            workDir: '/tmp/test-work',
            outputFile: '/tmp/test-output.json',
            symlinkMap: [],
        );

        // Claude sometimes returns JSON wrapped in markdown
        $claudeResponse = json_encode([
            'type' => 'message',
            'content' => [
                [
                    'type' => 'text',
                    'text' => "Here's the analysis:\n```json\n{\"status\": \"success\"}\n```",
                ],
            ],
        ]);

        $parsed = $method->invoke($driver, $claudeResponse, $session);

        $this->assertIsArray($parsed);
        $this->assertEquals('success', $parsed['status']);
    }

    /** @test */
    public function parse_output_handles_multiple_text_blocks(): void
    {
        $driver = new ClaudeCodeDriver();

        $reflection = new \ReflectionClass($driver);
        $method = $reflection->getMethod('parseOutput');
        $method->setAccessible(true);

        $session = new AgentSession(
            sessionId: 'test-session',
            workDir: '/tmp/test-work',
            outputFile: '/tmp/test-output.json',
            symlinkMap: [],
        );

        // Claude might return multiple text blocks
        $claudeResponse = json_encode([
            'type' => 'message',
            'content' => [
                [
                    'type' => 'text',
                    'text' => 'Processing document...',
                ],
                [
                    'type' => 'text',
                    'text' => '{"result": "found"}',
                ],
            ],
        ]);

        $parsed = $method->invoke($driver, $claudeResponse, $session);

        // Should find and parse the JSON from the second block
        $this->assertIsArray($parsed);
        $this->assertEquals('found', $parsed['result']);
    }

    /** @test */
    public function parse_output_returns_null_for_no_json_content(): void
    {
        $driver = new ClaudeCodeDriver();

        $reflection = new \ReflectionClass($driver);
        $method = $reflection->getMethod('parseOutput');
        $method->setAccessible(true);

        $session = new AgentSession(
            sessionId: 'test-session',
            workDir: '/tmp/test-work',
            outputFile: '/tmp/test-output.json',
            symlinkMap: [],
        );

        $claudeResponse = json_encode([
            'type' => 'message',
            'content' => [
                [
                    'type' => 'text',
                    'text' => 'Just plain text without any JSON.',
                ],
            ],
        ]);

        $parsed = $method->invoke($driver, $claudeResponse, $session);

        $this->assertNull($parsed);
    }

    /** @test */
    public function is_available_checks_claude_binary(): void
    {
        Process::fake([
            'which claude 2>/dev/null' => Process::result(
                output: '/usr/local/bin/claude',
                exitCode: 0
            ),
        ]);

        $driver = new ClaudeCodeDriver();

        $this->assertTrue($driver->isAvailable());
        Process::assertRan('which claude 2>/dev/null');
    }

    /** @test */
    public function run_executes_claude_command_and_returns_result(): void
    {
        Process::fake([
            'which claude 2>/dev/null' => Process::result(exitCode: 0),
            '*claude*' => Process::result(
                output: json_encode([
                    'type' => 'message',
                    'content' => [['type' => 'text', 'text' => '{"status": "done"}']],
                ]),
                exitCode: 0
            ),
        ]);

        $driver = new ClaudeCodeDriver();

        $result = $driver->run('Test prompt');

        $this->assertTrue($result->success);
        $this->assertEquals('claude', $result->driver);
        $this->assertEquals('done', $result->results['status']);
    }
}

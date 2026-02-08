<?php

namespace Tests\Unit\Services\Agent\Drivers;

use App\DTOs\Agent\AgentSession;
use App\Services\Agent\Contracts\AgentCapability;
use App\Services\Agent\Drivers\GeminiCliDriver;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * Test GeminiCliDriver - driver for the Gemini CLI.
 */
class GeminiCliDriverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Set up config for testing
        Config::set('agents.drivers.gemini', [
            'binary' => 'gemini',
            'timeout' => 600,
            'work_dir' => storage_path('app/test-agent-sessions'),
            'output_dir' => storage_path('app/test-agent-output'),
            'system_prompt' => '',
            'model' => 'gemini-2.5-pro',
            'sandbox' => false,
            'env' => [
                'GOOGLE_API_KEY' => 'test-google-key',
            ],
        ]);
    }

    /** @test */
    public function driver_returns_gemini(): void
    {
        $driver = new GeminiCliDriver();

        $this->assertEquals('gemini', $driver->driver());
    }

    /** @test */
    public function name_returns_gemini_cli(): void
    {
        $driver = new GeminiCliDriver();

        $this->assertEquals('Gemini CLI', $driver->name());
    }

    /** @test */
    public function capabilities_returns_expected_capabilities(): void
    {
        $driver = new GeminiCliDriver();

        $capabilities = $driver->capabilities();

        $this->assertContains(AgentCapability::FILE_READ, $capabilities);
        $this->assertContains(AgentCapability::FILE_WRITE, $capabilities);
        $this->assertContains(AgentCapability::BASH, $capabilities);
        $this->assertContains(AgentCapability::WEB_SEARCH, $capabilities);
        $this->assertContains(AgentCapability::JSON_OUTPUT, $capabilities);
    }

    /** @test */
    public function binary_returns_configured_binary(): void
    {
        $driver = new GeminiCliDriver();

        $reflection = new \ReflectionClass($driver);
        $method = $reflection->getMethod('binary');
        $method->setAccessible(true);

        $this->assertEquals('gemini', $method->invoke($driver));
    }

    /** @test */
    public function binary_uses_default_when_not_configured(): void
    {
        Config::set('agents.drivers.gemini.binary', null);

        $driver = new GeminiCliDriver();

        $reflection = new \ReflectionClass($driver);
        $method = $reflection->getMethod('binary');
        $method->setAccessible(true);

        $this->assertEquals('gemini', $method->invoke($driver));
    }

    /** @test */
    public function environment_includes_google_api_key(): void
    {
        $driver = new GeminiCliDriver();

        $reflection = new \ReflectionClass($driver);
        $method = $reflection->getMethod('environment');
        $method->setAccessible(true);

        $env = $method->invoke($driver, []);

        $this->assertArrayHasKey('GOOGLE_API_KEY', $env);
        $this->assertEquals('test-google-key', $env['GOOGLE_API_KEY']);
    }

    /** @test */
    public function build_command_includes_model_and_prompt(): void
    {
        $driver = new GeminiCliDriver();

        $reflection = new \ReflectionClass($driver);
        $method = $reflection->getMethod('buildCommand');
        $method->setAccessible(true);

        $session = new AgentSession(
            sessionId: 'test-session',
            workDir: '/tmp/test-work',
            outputFile: '/tmp/test-output.json',
            symlinkMap: [],
        );

        $command = $method->invoke($driver, 'Analyze this', $session, []);

        // Should be an array
        $this->assertIsArray($command);
        
        // Should contain the binary
        $this->assertEquals('gemini', $command[0]);
        
        // Should contain --model
        $this->assertContains('--model', $command);
        $modelIndex = array_search('--model', $command);
        $this->assertEquals('gemini-2.5-pro', $command[$modelIndex + 1]);
        
        // Should contain --prompt
        $this->assertContains('--prompt', $command);
        $promptIndex = array_search('--prompt', $command);
        $this->assertStringContainsString('Analyze this', $command[$promptIndex + 1]);
    }

    /** @test */
    public function build_command_excludes_sandbox_when_disabled(): void
    {
        Config::set('agents.drivers.gemini.sandbox', false);

        $driver = new GeminiCliDriver();

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

        // Should NOT contain --sandbox
        $this->assertNotContains('--sandbox', $command);
    }

    /** @test */
    public function build_command_includes_sandbox_when_enabled(): void
    {
        Config::set('agents.drivers.gemini.sandbox', true);

        $driver = new GeminiCliDriver();

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

        // Should contain --sandbox
        $this->assertContains('--sandbox', $command);
    }

    /** @test */
    public function build_command_uses_configured_model(): void
    {
        Config::set('agents.drivers.gemini.model', 'gemini-1.5-flash');

        $driver = new GeminiCliDriver();

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

        $modelIndex = array_search('--model', $command);
        $this->assertEquals('gemini-1.5-flash', $command[$modelIndex + 1]);
    }

    /** @test */
    public function parse_output_decodes_json_from_raw_output(): void
    {
        $driver = new GeminiCliDriver();

        $reflection = new \ReflectionClass($driver);
        $method = $reflection->getMethod('parseOutput');
        $method->setAccessible(true);

        $session = new AgentSession(
            sessionId: 'test-session',
            workDir: '/tmp/test-work',
            outputFile: '/tmp/test-output.json',
            symlinkMap: [],
        );

        $rawOutput = '{"analysis": "complete", "score": 95}';
        $parsed = $method->invoke($driver, $rawOutput, $session);

        $this->assertIsArray($parsed);
        $this->assertEquals('complete', $parsed['analysis']);
        $this->assertEquals(95, $parsed['score']);
    }

    /** @test */
    public function parse_output_extracts_json_from_markdown(): void
    {
        $driver = new GeminiCliDriver();

        $reflection = new \ReflectionClass($driver);
        $method = $reflection->getMethod('parseOutput');
        $method->setAccessible(true);

        $session = new AgentSession(
            sessionId: 'test-session',
            workDir: '/tmp/test-work',
            outputFile: '/tmp/test-output.json',
            symlinkMap: [],
        );

        $rawOutput = "Here is the result:\n```json\n{\"status\": \"success\"}\n```";
        $parsed = $method->invoke($driver, $rawOutput, $session);

        $this->assertIsArray($parsed);
        $this->assertEquals('success', $parsed['status']);
    }

    /** @test */
    public function parse_output_returns_null_for_invalid_json(): void
    {
        $driver = new GeminiCliDriver();

        $reflection = new \ReflectionClass($driver);
        $method = $reflection->getMethod('parseOutput');
        $method->setAccessible(true);

        $session = new AgentSession(
            sessionId: 'test-session',
            workDir: '/tmp/test-work',
            outputFile: '/tmp/test-output.json',
            symlinkMap: [],
        );

        $rawOutput = 'This is not JSON at all';
        $parsed = $method->invoke($driver, $rawOutput, $session);

        $this->assertNull($parsed);
    }

    /** @test */
    public function is_available_checks_gemini_binary(): void
    {
        Process::fake([
            'which gemini 2>/dev/null' => Process::result(
                output: '/usr/local/bin/gemini',
                exitCode: 0
            ),
        ]);

        $driver = new GeminiCliDriver();

        $this->assertTrue($driver->isAvailable());
        Process::assertRan('which gemini 2>/dev/null');
    }

    /** @test */
    public function run_executes_gemini_command_and_returns_result(): void
    {
        Process::fake([
            'which gemini 2>/dev/null' => Process::result(exitCode: 0),
            '*gemini*' => Process::result(
                output: '{"status": "completed", "data": []}',
                exitCode: 0
            ),
        ]);

        $driver = new GeminiCliDriver();

        $result = $driver->run('Test prompt');

        $this->assertTrue($result->success);
        $this->assertEquals('gemini', $result->driver);
        $this->assertEquals('completed', $result->results['status']);
    }
}

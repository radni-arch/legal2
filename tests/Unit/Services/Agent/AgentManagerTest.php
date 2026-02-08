<?php

namespace Tests\Unit\Services\Agent;

use App\DTOs\Agent\AgentResult;
use App\Services\Agent\AgentManager;
use App\Services\Agent\Contracts\AgentCapability;
use App\Services\Agent\Contracts\AgentInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AgentManagerTest extends TestCase
{
    private AgentManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear config to avoid auto-registering generic drivers
        config(['agents.drivers' => []]);
        config(['agents.default' => 'claude']);
        config(['agents.fallback_chain' => ['claude', 'gemini']]);

        // Create fresh manager for each test
        $this->manager = new AgentManager();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function can_register_driver(): void
    {
        $mockDriver = $this->createMockDriver('test-driver', 'Test Driver');

        $result = $this->manager->register('test-driver', $mockDriver);

        $this->assertSame($this->manager, $result);
        $this->assertSame($mockDriver, $this->manager->driver('test-driver'));
    }

    /** @test */
    public function can_get_default_driver(): void
    {
        $mockDriver = $this->createMockDriver('claude', 'Claude Code');
        $this->manager->register('claude', $mockDriver);

        config(['agents.default' => 'claude']);

        $driver = $this->manager->driver();

        $this->assertSame($mockDriver, $driver);
    }

    /** @test */
    public function can_get_specific_driver(): void
    {
        $claudeDriver = $this->createMockDriver('claude', 'Claude Code');
        $geminiDriver = $this->createMockDriver('gemini', 'Gemini CLI');

        $this->manager->register('claude', $claudeDriver);
        $this->manager->register('gemini', $geminiDriver);

        $this->assertSame($geminiDriver, $this->manager->driver('gemini'));
        $this->assertSame($claudeDriver, $this->manager->driver('claude'));
    }

    /** @test */
    public function throws_on_unregistered_driver(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Agent driver [nonexistent] not registered.');

        $this->manager->driver('nonexistent');
    }

    /** @test */
    public function run_with_fallback_uses_first_available_driver(): void
    {
        $successResult = $this->createSuccessResult('claude');

        $claudeDriver = $this->createMockDriver('claude', 'Claude Code', true);
        $claudeDriver->shouldReceive('run')
            ->once()
            ->with('test prompt', ['file1.txt'], Mockery::type('array'))
            ->andReturn($successResult);

        $geminiDriver = $this->createMockDriver('gemini', 'Gemini CLI', true);
        $geminiDriver->shouldNotReceive('run');

        $this->manager->register('claude', $claudeDriver);
        $this->manager->register('gemini', $geminiDriver);

        config(['agents.fallback_chain' => ['claude', 'gemini']]);

        $result = $this->manager->runWithFallback('test prompt', ['file1.txt']);

        $this->assertTrue($result->success);
        $this->assertEquals('claude', $result->driver);
    }

    /** @test */
    public function run_with_fallback_skips_unavailable_drivers(): void
    {
        $successResult = $this->createSuccessResult('gemini');

        // Claude is not available
        $claudeDriver = $this->createMockDriver('claude', 'Claude Code', false);
        $claudeDriver->shouldNotReceive('run');

        // Gemini is available
        $geminiDriver = $this->createMockDriver('gemini', 'Gemini CLI', true);
        $geminiDriver->shouldReceive('run')
            ->once()
            ->andReturn($successResult);

        $this->manager->register('claude', $claudeDriver);
        $this->manager->register('gemini', $geminiDriver);

        config(['agents.fallback_chain' => ['claude', 'gemini']]);

        $result = $this->manager->runWithFallback('test prompt', ['file1.txt']);

        $this->assertTrue($result->success);
        $this->assertEquals('gemini', $result->driver);
    }

    /** @test */
    public function run_with_fallback_tries_next_on_failure(): void
    {
        $failResult = $this->createFailResult('claude');
        $successResult = $this->createSuccessResult('gemini');

        $claudeDriver = $this->createMockDriver('claude', 'Claude Code', true);
        $claudeDriver->shouldReceive('run')
            ->once()
            ->andReturn($failResult);

        $geminiDriver = $this->createMockDriver('gemini', 'Gemini CLI', true);
        $geminiDriver->shouldReceive('run')
            ->once()
            ->andReturn($successResult);

        $this->manager->register('claude', $claudeDriver);
        $this->manager->register('gemini', $geminiDriver);

        config(['agents.fallback_chain' => ['claude', 'gemini']]);

        $result = $this->manager->runWithFallback('test prompt', ['file1.txt']);

        $this->assertTrue($result->success);
        $this->assertEquals('gemini', $result->driver);
    }

    /** @test */
    public function run_with_fallback_returns_exhausted_when_all_fail(): void
    {
        $claudeFailResult = $this->createFailResult('claude');
        $geminiFailResult = $this->createFailResult('gemini');

        $claudeDriver = $this->createMockDriver('claude', 'Claude Code', true);
        $claudeDriver->shouldReceive('run')
            ->once()
            ->andReturn($claudeFailResult);

        $geminiDriver = $this->createMockDriver('gemini', 'Gemini CLI', true);
        $geminiDriver->shouldReceive('run')
            ->once()
            ->andReturn($geminiFailResult);

        $this->manager->register('claude', $claudeDriver);
        $this->manager->register('gemini', $geminiDriver);

        config(['agents.fallback_chain' => ['claude', 'gemini']]);

        $result = $this->manager->runWithFallback('test prompt', ['file1.txt']);

        $this->assertFalse($result->success);
        $this->assertEquals('fallback_exhausted', $result->driver);
        $this->assertStringContainsString('All agents in fallback chain failed', $result->rawError);
        $this->assertEquals(['claude', 'gemini'], $result->metadata['attempted_drivers']);
    }

    /** @test */
    public function best_driver_for_capabilities_returns_matching_driver(): void
    {
        $claudeDriver = $this->createMockDriverWithCapabilities('claude', true, [
            AgentCapability::FILE_READ,
            AgentCapability::FILE_WRITE,
            AgentCapability::BASH,
        ]);

        $geminiDriver = $this->createMockDriverWithCapabilities('gemini', true, [
            AgentCapability::FILE_READ,
            AgentCapability::WEB_SEARCH,
        ]);

        $this->manager->register('claude', $claudeDriver);
        $this->manager->register('gemini', $geminiDriver);

        // Should match claude (has FILE_READ, FILE_WRITE, BASH)
        $driver = $this->manager->bestDriverFor([
            AgentCapability::FILE_READ,
            AgentCapability::BASH,
        ]);

        $this->assertSame($claudeDriver, $driver);
    }

    /** @test */
    public function best_driver_for_capabilities_returns_null_when_no_match(): void
    {
        $claudeDriver = $this->createMockDriverWithCapabilities('claude', true, [
            AgentCapability::FILE_READ,
        ]);

        $this->manager->register('claude', $claudeDriver);

        // No driver has EXTENDED_THINKING capability
        $driver = $this->manager->bestDriverFor([
            AgentCapability::EXTENDED_THINKING,
        ]);

        $this->assertNull($driver);
    }

    /** @test */
    public function best_driver_for_capabilities_skips_unavailable_drivers(): void
    {
        $claudeDriver = $this->createMockDriverWithCapabilities('claude', false, [
            AgentCapability::FILE_READ,
            AgentCapability::BASH,
        ]);

        $geminiDriver = $this->createMockDriverWithCapabilities('gemini', true, [
            AgentCapability::FILE_READ,
            AgentCapability::BASH,
        ]);

        $this->manager->register('claude', $claudeDriver);
        $this->manager->register('gemini', $geminiDriver);

        // Should skip unavailable claude and return gemini
        $driver = $this->manager->bestDriverFor([AgentCapability::BASH]);

        $this->assertSame($geminiDriver, $driver);
    }

    /** @test */
    public function status_returns_all_drivers_with_info(): void
    {
        $claudeDriver = $this->createMockDriverWithCapabilities('claude', true, [
            AgentCapability::FILE_READ,
            AgentCapability::BASH,
        ]);

        $geminiDriver = $this->createMockDriverWithCapabilities('gemini', false, [
            AgentCapability::WEB_SEARCH,
        ]);

        $this->manager->register('claude', $claudeDriver);
        $this->manager->register('gemini', $geminiDriver);

        $status = $this->manager->status();

        $this->assertArrayHasKey('claude', $status);
        $this->assertArrayHasKey('gemini', $status);

        $this->assertEquals('Claude Code', $status['claude']['name']);
        $this->assertTrue($status['claude']['available']);
        $this->assertEquals(['file_read', 'bash'], $status['claude']['capabilities']);

        $this->assertEquals('Gemini CLI', $status['gemini']['name']);
        $this->assertFalse($status['gemini']['available']);
        $this->assertEquals(['web_search'], $status['gemini']['capabilities']);
    }

    /** @test */
    public function run_with_fallback_handles_empty_chain(): void
    {
        config(['agents.fallback_chain' => []]);

        $result = $this->manager->runWithFallback('test prompt', ['file1.txt']);

        $this->assertFalse($result->success);
        $this->assertEquals('fallback_exhausted', $result->driver);
    }

    /** @test */
    public function run_with_fallback_accepts_string_capabilities_in_best_driver(): void
    {
        $claudeDriver = $this->createMockDriverWithCapabilities('claude', true, [
            AgentCapability::FILE_READ,
            AgentCapability::BASH,
        ]);

        $this->manager->register('claude', $claudeDriver);

        // Test with string capability values (from config)
        $driver = $this->manager->bestDriverFor(['file_read', 'bash']);

        $this->assertSame($claudeDriver, $driver);
    }

    // === Helper Methods ===

    private function createMockDriver(
        string $driverName,
        string $name,
        bool $available = true
    ): AgentInterface {
        $mock = Mockery::mock(AgentInterface::class);
        $mock->shouldReceive('driver')->andReturn($driverName);
        $mock->shouldReceive('name')->andReturn($name);
        $mock->shouldReceive('isAvailable')->andReturn($available);
        $mock->shouldReceive('capabilities')->andReturn([]);

        return $mock;
    }

    private function createMockDriverWithCapabilities(
        string $driverName,
        bool $available,
        array $capabilities
    ): AgentInterface {
        $mock = Mockery::mock(AgentInterface::class);
        $mock->shouldReceive('driver')->andReturn($driverName);
        $mock->shouldReceive('name')->andReturn($driverName === 'claude' ? 'Claude Code' : 'Gemini CLI');
        $mock->shouldReceive('isAvailable')->andReturn($available);
        $mock->shouldReceive('capabilities')->andReturn($capabilities);

        return $mock;
    }

    private function createSuccessResult(string $driver): AgentResult
    {
        return new AgentResult(
            success: true,
            driver: $driver,
            results: ['test' => 'data'],
            rawOutput: 'test output',
            rawError: '',
            exitCode: 0,
            elapsedSeconds: 1.5,
            sessionId: 'test-session-123',
            outputFile: '/tmp/output.json',
            metadata: [],
        );
    }

    private function createFailResult(string $driver): AgentResult
    {
        return new AgentResult(
            success: false,
            driver: $driver,
            results: null,
            rawOutput: '',
            rawError: 'Agent failed',
            exitCode: 1,
            elapsedSeconds: 0.5,
            sessionId: 'test-session-456',
            outputFile: null,
            metadata: [],
        );
    }
}

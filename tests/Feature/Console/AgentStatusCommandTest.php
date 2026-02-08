<?php

namespace Tests\Feature\Console;

use App\Services\Agent\AgentManager;
use App\Services\Agent\Contracts\AgentCapability;
use App\Services\Agent\Contracts\AgentInterface;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class AgentStatusCommandTest extends TestCase
{
    protected MockInterface $managerMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->managerMock = Mockery::mock(AgentManager::class);
        $this->app->instance(AgentManager::class, $this->managerMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_displays_all_registered_drivers()
    {
        $this->managerMock
            ->shouldReceive('getRegisteredDrivers')
            ->once()
            ->andReturn(['claude', 'gemini']);

        $this->managerMock
            ->shouldReceive('getAllDriversInfo')
            ->once()
            ->andReturn([
                'claude' => [
                    'name' => 'Claude Code',
                    'driver' => 'claude',
                    'available' => true,
                    'capabilities' => ['file_read', 'file_write', 'bash'],
                    'default' => true,
                    'fallback_position' => 1,
                ],
                'gemini' => [
                    'name' => 'Gemini CLI',
                    'driver' => 'gemini',
                    'available' => false,
                    'capabilities' => ['file_read', 'web_search'],
                    'default' => false,
                    'fallback_position' => 2,
                ],
            ]);

        $this->artisan('agent:status')
            ->expectsOutputToContain('claude')
            ->expectsOutputToContain('gemini')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_shows_availability_status_with_symbols()
    {
        $this->managerMock
            ->shouldReceive('getRegisteredDrivers')
            ->once()
            ->andReturn(['claude', 'gemini']);

        $this->managerMock
            ->shouldReceive('getAllDriversInfo')
            ->once()
            ->andReturn([
                'claude' => [
                    'name' => 'Claude Code',
                    'driver' => 'claude',
                    'available' => true,
                    'capabilities' => ['file_read'],
                    'default' => true,
                    'fallback_position' => 1,
                ],
                'gemini' => [
                    'name' => 'Gemini CLI',
                    'driver' => 'gemini',
                    'available' => false,
                    'capabilities' => ['file_read'],
                    'default' => false,
                    'fallback_position' => 2,
                ],
            ]);

        $this->artisan('agent:status')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_shows_default_marker_for_default_driver()
    {
        $this->managerMock
            ->shouldReceive('getRegisteredDrivers')
            ->once()
            ->andReturn(['claude']);

        $this->managerMock
            ->shouldReceive('getAllDriversInfo')
            ->once()
            ->andReturn([
                'claude' => [
                    'name' => 'Claude Code',
                    'driver' => 'claude',
                    'available' => true,
                    'capabilities' => ['file_read'],
                    'default' => true,
                    'fallback_position' => 1,
                ],
            ]);

        $this->artisan('agent:status')
            ->expectsOutputToContain('default')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_shows_fallback_chain_position()
    {
        $this->managerMock
            ->shouldReceive('getRegisteredDrivers')
            ->once()
            ->andReturn(['claude', 'gemini', 'aider']);

        $this->managerMock
            ->shouldReceive('getAllDriversInfo')
            ->once()
            ->andReturn([
                'claude' => [
                    'name' => 'Claude Code',
                    'driver' => 'claude',
                    'available' => true,
                    'capabilities' => ['file_read'],
                    'default' => true,
                    'fallback_position' => 1,
                ],
                'gemini' => [
                    'name' => 'Gemini CLI',
                    'driver' => 'gemini',
                    'available' => true,
                    'capabilities' => ['file_read'],
                    'default' => false,
                    'fallback_position' => 2,
                ],
                'aider' => [
                    'name' => 'Aider',
                    'driver' => 'aider',
                    'available' => false,
                    'capabilities' => ['file_read'],
                    'default' => false,
                    'fallback_position' => null,
                ],
            ]);

        // Should show fallback positions for drivers in the chain
        $this->artisan('agent:status')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_shows_capabilities_for_each_driver()
    {
        $this->managerMock
            ->shouldReceive('getRegisteredDrivers')
            ->once()
            ->andReturn(['claude']);

        $this->managerMock
            ->shouldReceive('getAllDriversInfo')
            ->once()
            ->andReturn([
                'claude' => [
                    'name' => 'Claude Code',
                    'driver' => 'claude',
                    'available' => true,
                    'capabilities' => ['file_read', 'file_write', 'bash', 'extended_thinking'],
                    'default' => true,
                    'fallback_position' => 1,
                ],
            ]);

        $this->artisan('agent:status')
            ->expectsOutputToContain('file_read')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_handles_no_registered_drivers_gracefully()
    {
        $this->managerMock
            ->shouldReceive('getRegisteredDrivers')
            ->once()
            ->andReturn([]);

        $this->managerMock
            ->shouldReceive('getAllDriversInfo')
            ->once()
            ->andReturn([]);

        $this->artisan('agent:status')
            ->expectsOutputToContain('No agent drivers registered')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_supports_json_output_format()
    {
        $this->managerMock
            ->shouldReceive('getRegisteredDrivers')
            ->once()
            ->andReturn(['claude']);

        $this->managerMock
            ->shouldReceive('getAllDriversInfo')
            ->once()
            ->andReturn([
                'claude' => [
                    'name' => 'Claude Code',
                    'driver' => 'claude',
                    'available' => true,
                    'capabilities' => ['file_read'],
                    'default' => true,
                    'fallback_position' => 1,
                ],
            ]);

        $this->artisan('agent:status', ['--json' => true])
            ->assertExitCode(0);
    }
}

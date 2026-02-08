<?php

declare(strict_types=1);

namespace Tests\Feature\Agent;

use App\Services\Agent\AgentManager;
use App\Services\Agent\Contracts\AgentCapability;
use App\Services\Agent\Contracts\AgentInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Feature tests for the agent:status Artisan command.
 *
 * Tests verify the command properly:
 * - Lists all registered drivers
 * - Shows availability status for each driver
 * - Displays capabilities for each driver
 * - Identifies the default driver
 * - Shows the fallback chain
 */
class AgentStatusCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_agent_status_command_runs_successfully(): void
    {
        $this->artisan('agent:status')
            ->assertExitCode(0);
    }

    public function test_agent_status_shows_ai_agent_drivers_header(): void
    {
        $this->artisan('agent:status')
            ->expectsOutput('AI Agent Drivers:')
            ->assertExitCode(0);
    }

    public function test_agent_status_displays_driver_availability(): void
    {
        // Mock a manager with one available and one unavailable driver
        $availableDriver = Mockery::mock(AgentInterface::class);
        $availableDriver->shouldReceive('driver')->andReturn('test_available');
        $availableDriver->shouldReceive('name')->andReturn('Test Available Driver');
        $availableDriver->shouldReceive('isAvailable')->andReturn(true);
        $availableDriver->shouldReceive('capabilities')->andReturn([
            AgentCapability::FILE_READ,
        ]);

        $unavailableDriver = Mockery::mock(AgentInterface::class);
        $unavailableDriver->shouldReceive('driver')->andReturn('test_unavailable');
        $unavailableDriver->shouldReceive('name')->andReturn('Test Unavailable Driver');
        $unavailableDriver->shouldReceive('isAvailable')->andReturn(false);
        $unavailableDriver->shouldReceive('capabilities')->andReturn([
            AgentCapability::BASH,
        ]);

        $manager = Mockery::mock(AgentManager::class);
        $manager->shouldReceive('status')->andReturn([
            'test_available' => [
                'name' => 'Test Available Driver',
                'available' => true,
                'capabilities' => ['file_read'],
            ],
            'test_unavailable' => [
                'name' => 'Test Unavailable Driver',
                'available' => false,
                'capabilities' => ['bash'],
            ],
        ]);

        $this->app->instance(AgentManager::class, $manager);

        // The command should show availability indicators
        $this->artisan('agent:status')
            ->expectsOutputToContain('test_available')
            ->expectsOutputToContain('test_unavailable')
            ->assertExitCode(0);
    }

    public function test_agent_status_shows_capabilities_for_each_driver(): void
    {
        $manager = Mockery::mock(AgentManager::class);
        $manager->shouldReceive('status')->andReturn([
            'claude' => [
                'name' => 'Claude Code',
                'available' => true,
                'capabilities' => ['file_read', 'file_write', 'bash', 'multi_turn'],
            ],
        ]);

        $this->app->instance(AgentManager::class, $manager);

        $this->artisan('agent:status')
            ->expectsOutputToContain('file_read')
            ->expectsOutputToContain('file_write')
            ->expectsOutputToContain('bash')
            ->assertExitCode(0);
    }

    public function test_agent_status_identifies_default_driver(): void
    {
        config(['agents.default' => 'test_driver']);

        $manager = Mockery::mock(AgentManager::class);
        $manager->shouldReceive('status')->andReturn([
            'test_driver' => [
                'name' => 'Test Driver',
                'available' => true,
                'capabilities' => ['file_read'],
            ],
        ]);

        $this->app->instance(AgentManager::class, $manager);

        $this->artisan('agent:status')
            ->expectsOutputToContain('DEFAULT')
            ->assertExitCode(0);
    }

    public function test_agent_status_shows_fallback_chain(): void
    {
        config(['agents.fallback_chain' => ['claude', 'gemini', 'aider']]);

        $manager = Mockery::mock(AgentManager::class);
        $manager->shouldReceive('status')->andReturn([
            'claude' => [
                'name' => 'Claude Code',
                'available' => true,
                'capabilities' => ['file_read'],
            ],
        ]);

        $this->app->instance(AgentManager::class, $manager);

        $this->artisan('agent:status')
            ->expectsOutputToContain('Fallback chain:')
            ->assertExitCode(0);
    }

    public function test_agent_status_shows_drivers_in_fallback_chain(): void
    {
        config(['agents.fallback_chain' => ['driver_a', 'driver_b']]);

        $manager = Mockery::mock(AgentManager::class);
        $manager->shouldReceive('status')->andReturn([
            'driver_a' => [
                'name' => 'Driver A',
                'available' => true,
                'capabilities' => [],
            ],
            'driver_b' => [
                'name' => 'Driver B',
                'available' => false,
                'capabilities' => [],
            ],
        ]);

        $this->app->instance(AgentManager::class, $manager);

        // Should indicate which drivers are in fallback chain
        $this->artisan('agent:status')
            ->expectsOutputToContain('fallback')
            ->assertExitCode(0);
    }
}

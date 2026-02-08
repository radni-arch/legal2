<?php

namespace App\Services\Agent;

use App\DTOs\Agent\AgentResult;
use App\Services\Agent\Contracts\AgentCapability;
use App\Services\Agent\Contracts\AgentInterface;
use Illuminate\Support\Facades\Log;

/**
 * Agent Manager - Factory and orchestration for AI CLI agents.
 *
 * Responsibilities:
 * - Register and retrieve agent drivers
 * - Run prompts with automatic fallback
 * - Find best driver for required capabilities
 * - Report status of all registered drivers
 */
class AgentManager
{
    /**
     * Registered agent drivers.
     *
     * @var array<string, AgentInterface>
     */
    private array $drivers = [];

    /**
     * Create a new AgentManager instance.
     *
     * Registers built-in drivers (claude, gemini) and any generic drivers
     * from config that have a command_template defined.
     */
    public function __construct()
    {
        $this->registerBuiltInDrivers();
        $this->registerGenericDrivers();
    }

    /**
     * Register built-in drivers if their classes exist.
     */
    private function registerBuiltInDrivers(): void
    {
        // Built-in drivers are registered via concrete implementations
        // that extend BaseAgent. They will self-register when instantiated.
        // For now, this is a hook for future auto-registration.
    }

    /**
     * Register generic command-based drivers from config.
     *
     * Drivers with a 'command_template' key are treated as generic
     * command-based agents that can be executed without a dedicated class.
     */
    private function registerGenericDrivers(): void
    {
        $driversConfig = config('agents.drivers', []);

        foreach ($driversConfig as $name => $config) {
            // Skip drivers that don't have command_template (built-in drivers)
            if (empty($config['command_template'])) {
                continue;
            }

            // Generic drivers would be instantiated here using a GenericAgent class
            // For now, this is a placeholder for future implementation
        }
    }

    /**
     * Register an agent driver.
     *
     * @param string         $name   Unique driver identifier
     * @param AgentInterface $driver The driver instance
     * @return self           Fluent interface
     */
    public function register(string $name, AgentInterface $driver): self
    {
        $this->drivers[$name] = $driver;

        return $this;
    }

    /**
     * Get an agent driver by name.
     *
     * @param string|null $name Driver name, or null for default
     * @return AgentInterface
     *
     * @throws \RuntimeException If driver is not registered
     */
    public function driver(?string $name = null): AgentInterface
    {
        $name = $name ?? config('agents.default', 'claude');

        if (!isset($this->drivers[$name])) {
            throw new \RuntimeException("Agent driver [{$name}] not registered.");
        }

        return $this->drivers[$name];
    }

    /**
     * Run a prompt with automatic fallback through the chain.
     *
     * Iterates through the configured fallback_chain, trying each
     * available driver until one succeeds or all fail.
     *
     * @param string $prompt    The task/prompt to execute
     * @param array  $filePaths Files to make available to the agent
     * @param array  $options   Driver-specific options
     * @return AgentResult
     */
    public function runWithFallback(string $prompt, array $filePaths = [], array $options = []): AgentResult
    {
        $chain = config('agents.fallback_chain', ['claude', 'gemini']);
        $attemptedDrivers = [];
        $lastError = '';

        foreach ($chain as $driverName) {
            // Skip if driver not registered
            if (!isset($this->drivers[$driverName])) {
                Log::debug("Agent fallback: skipping [{$driverName}] - not registered");
                continue;
            }

            $driver = $this->drivers[$driverName];

            // Skip if driver is not available on this system
            if (!$driver->isAvailable()) {
                Log::debug("Agent fallback: skipping [{$driverName}] - not available");
                continue;
            }

            $attemptedDrivers[] = $driverName;

            Log::info("Agent fallback: trying [{$driverName}]");

            try {
                $result = $driver->run($prompt, $filePaths, $options);

                if ($result->success) {
                    Log::info("Agent fallback: [{$driverName}] succeeded");
                    return $result;
                }

                $lastError = $result->rawError ?: 'Unknown error';
                Log::warning("Agent fallback: [{$driverName}] failed", [
                    'error' => $lastError,
                    'exit_code' => $result->exitCode,
                ]);

            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
                Log::error("Agent fallback: [{$driverName}] threw exception", [
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        // All drivers failed or were unavailable
        Log::error('Agent fallback: all drivers exhausted', [
            'attempted' => $attemptedDrivers,
        ]);

        return new AgentResult(
            success: false,
            driver: 'fallback_exhausted',
            results: null,
            rawOutput: '',
            rawError: "All agents in fallback chain failed. Last error: {$lastError}",
            exitCode: -1,
            elapsedSeconds: 0,
            sessionId: 'fallback_exhausted_' . time(),
            outputFile: null,
            metadata: [
                'attempted_drivers' => $attemptedDrivers,
                'fallback_chain' => $chain,
            ],
        );
    }

    /**
     * Find the best available driver that has all required capabilities.
     *
     * @param array<AgentCapability|string> $requiredCapabilities Required capabilities
     * @return AgentInterface|null First matching available driver, or null
     */
    public function bestDriverFor(array $requiredCapabilities): ?AgentInterface
    {
        // Normalize capabilities to AgentCapability enums
        $normalizedRequired = array_map(function ($cap) {
            if ($cap instanceof AgentCapability) {
                return $cap;
            }
            // Convert string to enum
            return AgentCapability::tryFrom($cap);
        }, $requiredCapabilities);

        // Filter out any nulls from failed conversions
        $normalizedRequired = array_filter($normalizedRequired);

        foreach ($this->drivers as $driver) {
            // Skip unavailable drivers
            if (!$driver->isAvailable()) {
                continue;
            }

            $driverCapabilities = $driver->capabilities();

            // Check if driver has all required capabilities
            $hasAllCapabilities = true;
            foreach ($normalizedRequired as $required) {
                if (!in_array($required, $driverCapabilities, true)) {
                    $hasAllCapabilities = false;
                    break;
                }
            }

            if ($hasAllCapabilities) {
                return $driver;
            }
        }

        return null;
    }

    /**
     * Get status information for all registered drivers.
     *
     * @return array<string, array{name: string, available: bool, capabilities: string[]}>
     */
    public function status(): array
    {
        $status = [];

        foreach ($this->drivers as $name => $driver) {
            $capabilities = array_map(
                fn(AgentCapability $cap) => $cap->value,
                $driver->capabilities()
            );

            $status[$name] = [
                'name' => $driver->name(),
                'available' => $driver->isAvailable(),
                'capabilities' => $capabilities,
            ];
        }

        return $status;
    }

    /**
     * Check if a driver is registered.
     *
     * @param string $name Driver name
     * @return bool
     */
    public function has(string $name): bool
    {
        return isset($this->drivers[$name]);
    }

    /**
     * Get all registered driver names.
     *
     * @return string[]
     */
    public function registered(): array
    {
        return array_keys($this->drivers);
    }

    /**
     * Alias for registered() - get all registered driver names.
     *
     * @return string[]
     */
    public function getRegisteredDrivers(): array
    {
        return $this->registered();
    }

    /**
     * Get the default driver name from config.
     *
     * @return string
     */
    public function getDefaultDriver(): string
    {
        return config('agents.default', 'claude');
    }

    /**
     * Get the fallback chain from config.
     *
     * @return string[]
     */
    public function getFallbackChain(): array
    {
        return config('agents.fallback_chain', ['claude', 'gemini']);
    }

    /**
     * Get detailed info for a specific driver.
     *
     * @param string $driverName The driver identifier
     * @return array{name: string, driver: string, available: bool, capabilities: string[], default: bool, fallback_position: int|null}
     */
    public function getDriverInfo(string $driverName): array
    {
        if (!$this->has($driverName)) {
            return [
                'name' => $driverName,
                'driver' => $driverName,
                'available' => false,
                'capabilities' => [],
                'default' => $driverName === $this->getDefaultDriver(),
                'fallback_position' => null,
            ];
        }

        $driver = $this->drivers[$driverName];
        $fallbackChain = $this->getFallbackChain();
        $fallbackPos = array_search($driverName, $fallbackChain, true);

        return [
            'name' => $driver->name(),
            'driver' => $driver->driver(),
            'available' => $driver->isAvailable(),
            'capabilities' => array_map(
                fn(AgentCapability $cap) => $cap->value,
                $driver->capabilities()
            ),
            'default' => $driverName === $this->getDefaultDriver(),
            'fallback_position' => $fallbackPos !== false ? $fallbackPos + 1 : null,
        ];
    }

    /**
     * Get detailed info for all registered drivers.
     *
     * @return array<string, array{name: string, driver: string, available: bool, capabilities: string[], default: bool, fallback_position: int|null}>
     */
    public function getAllDriversInfo(): array
    {
        $info = [];
        foreach ($this->registered() as $driverName) {
            $info[$driverName] = $this->getDriverInfo($driverName);
        }
        return $info;
    }
}

<?php

namespace App\Providers;

use App\Services\Agent\AgentManager;
use App\Services\Agent\Contracts\AgentInterface;
use App\Services\Agent\Drivers\AiderDriver;
use App\Services\Agent\Drivers\ClaudeCodeDriver;
use App\Services\Agent\Drivers\GeminiCliDriver;
use Illuminate\Support\ServiceProvider;

/**
 * Agent Services Provider
 *
 * Registers agent-related services including the AgentManager
 * which handles multiple AI CLI agent drivers (Claude, Gemini, etc.).
 *
 * @see \App\Services\Agent\AgentManager
 */
class AgentServiceProvider extends ServiceProvider
{
    /**
     * Register agent services.
     */
    public function register(): void
    {
        // Register AgentManager as singleton
        $this->app->singleton(AgentManager::class);

        // Bind AgentInterface to the default driver
        $this->app->bind(
            AgentInterface::class,
            fn($app) => $app->make(AgentManager::class)->driver()
        );
    }

    /**
     * Bootstrap agent services.
     */
    public function boot(): void
    {
        // Merge agents configuration
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/agents.php',
            'agents'
        );

        // Register built-in drivers
        $this->app->booted(function () {
            $manager = $this->app->make(AgentManager::class);
            $manager->register('claude', new ClaudeCodeDriver());
            $manager->register('gemini', new GeminiCliDriver());
            $manager->register('aider', new AiderDriver());
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<class-string>
     */
    public function provides(): array
    {
        return [
            AgentManager::class,
            AgentInterface::class,
        ];
    }
}

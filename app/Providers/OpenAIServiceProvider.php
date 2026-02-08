<?php

namespace App\Providers;

use App\Contracts\AI\AnalysisServiceInterface;
use App\Contracts\AI\CacheServiceInterface;
use App\Contracts\AI\ChatServiceInterface;
use App\Contracts\AI\EmbeddingServiceInterface;
use App\Services\AI\OpenAIAnalysisService;
use App\Services\AI\OpenAICacheService;
use App\Services\AI\OpenAIChatService;
use App\Services\AI\OpenAIEmbeddingService;
use App\Services\AI\OpenAIOrchestrator;
use Illuminate\Support\ServiceProvider;

/**
 * OpenAI Services Provider
 *
 * Registers all OpenAI-related services with the Laravel service container.
 * Services are registered as singletons to maintain circuit breaker state
 * and cache across requests.
 *
 * Service Registration Order:
 * 1. CacheService - Required by other services
 * 2. ChatService - Chat completions
 * 3. EmbeddingService - Text embeddings
 * 4. AnalysisService - Text analysis (depends on ChatService)
 * 5. Orchestrator - Unified facade (depends on all above)
 *
 * All services are bound to their interfaces for easy mocking in tests.
 *
 * @see \App\Services\AI\OpenAIOrchestrator
 */
class OpenAIServiceProvider extends ServiceProvider
{
    /**
     * Register OpenAI services
     */
    public function register(): void
    {
        // Register cache service first (dependency for other services)
        $this->app->singleton(CacheServiceInterface::class, OpenAICacheService::class);

        // Register chat service
        $this->app->singleton(ChatServiceInterface::class, function ($app) {
            return new OpenAIChatService(
                $app->make(CacheServiceInterface::class)
            );
        });

        // Register embedding service
        $this->app->singleton(EmbeddingServiceInterface::class, function ($app) {
            return new OpenAIEmbeddingService(
                $app->make(CacheServiceInterface::class)
            );
        });

        // Register analysis service (depends on chat and cache services)
        $this->app->singleton(AnalysisServiceInterface::class, function ($app) {
            return new OpenAIAnalysisService(
                $app->make(ChatServiceInterface::class),
                $app->make(CacheServiceInterface::class)
            );
        });

        // Register orchestrator (unified facade)
        $this->app->singleton(OpenAIOrchestrator::class, function ($app) {
            return new OpenAIOrchestrator(
                $app->make(ChatServiceInterface::class),
                $app->make(EmbeddingServiceInterface::class),
                $app->make(AnalysisServiceInterface::class)
            );
        });

        // Alias for backward compatibility
        $this->app->alias(OpenAIOrchestrator::class, 'openai.orchestrator');
    }

    /**
     * Bootstrap services
     */
    public function boot(): void
    {
        //
    }

    /**
     * Get the services provided by the provider
     */
    public function provides(): array
    {
        return [
            CacheServiceInterface::class,
            ChatServiceInterface::class,
            EmbeddingServiceInterface::class,
            AnalysisServiceInterface::class,
            OpenAIOrchestrator::class,
            'openai.orchestrator',
        ];
    }
}

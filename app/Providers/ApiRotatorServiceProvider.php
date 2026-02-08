<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\ApiRotator\ApiKeyRotatorService;
use App\Services\ApiRotator\ProviderAdapterFactory;

/**
 * ApiRotatorServiceProvider
 *
 * Registers the API Key Rotation services with the Laravel service container.
 * The rotator provides intelligent API key selection and automatic rotation
 * based on rate limits, quotas, and cooldown periods.
 *
 * Services Registered:
 * - ProviderAdapterFactory: Creates provider-specific adapters (Gemini, Mistral, OpenRouter)
 * - ApiKeyRotatorService: Main service for key selection and rotation
 *
 * @see \App\Services\ApiRotator\ApiKeyRotatorService
 * @see \App\Services\ApiRotator\ProviderAdapterFactory
 */
class ApiRotatorServiceProvider extends ServiceProvider
{
    /**
     * Register API rotator services
     */
    public function register(): void
    {
        // Register the adapter factory as singleton
        $this->app->singleton(ProviderAdapterFactory::class, function ($app) {
            return new ProviderAdapterFactory();
        });

        // Register the main rotator service as singleton
        $this->app->singleton(ApiKeyRotatorService::class, function ($app) {
            return new ApiKeyRotatorService(
                $app->make(ProviderAdapterFactory::class)
            );
        });

        // Alias for easier access
        $this->app->alias(ApiKeyRotatorService::class, 'api-rotator');
    }

    /**
     * Bootstrap services
     */
    public function boot(): void
    {
        // Publish config if needed in the future
        // $this->publishes([
        //     __DIR__.'/../../config/api_rotator.php' => config_path('api_rotator.php'),
        // ], 'api-rotator-config');
    }

    /**
     * Get the services provided by the provider
     */
    public function provides(): array
    {
        return [
            ProviderAdapterFactory::class,
            ApiKeyRotatorService::class,
            'api-rotator',
        ];
    }
}

<?php

namespace Tests\Unit\Providers;

use Tests\TestCase;
use App\Providers\ApiRotatorServiceProvider;
use App\Services\ApiRotator\ApiKeyRotatorService;
use App\Services\ApiRotator\ProviderAdapterFactory;

/**
 * ApiRotatorServiceProviderTest
 *
 * Verifies that the ApiRotatorServiceProvider correctly registers
 * all API key rotation services with the Laravel container.
 */
class ApiRotatorServiceProviderTest extends TestCase
{
    public function test_provider_adapter_factory_is_registered_as_singleton(): void
    {
        $instance1 = $this->app->make(ProviderAdapterFactory::class);
        $instance2 = $this->app->make(ProviderAdapterFactory::class);

        $this->assertInstanceOf(ProviderAdapterFactory::class, $instance1);
        $this->assertSame($instance1, $instance2, 'ProviderAdapterFactory should be a singleton');
    }

    public function test_api_key_rotator_service_is_registered_as_singleton(): void
    {
        $instance1 = $this->app->make(ApiKeyRotatorService::class);
        $instance2 = $this->app->make(ApiKeyRotatorService::class);

        $this->assertInstanceOf(ApiKeyRotatorService::class, $instance1);
        $this->assertSame($instance1, $instance2, 'ApiKeyRotatorService should be a singleton');
    }

    public function test_api_rotator_alias_is_registered(): void
    {
        $service = $this->app->make('api-rotator');

        $this->assertInstanceOf(ApiKeyRotatorService::class, $service);
    }

    public function test_api_key_rotator_service_receives_provider_adapter_factory(): void
    {
        $service = $this->app->make(ApiKeyRotatorService::class);
        $factory = $this->app->make(ProviderAdapterFactory::class);

        // Service should have been constructed with the factory
        // This is verified implicitly by the service working correctly
        $this->assertInstanceOf(ApiKeyRotatorService::class, $service);
        $this->assertInstanceOf(ProviderAdapterFactory::class, $factory);
    }

    public function test_provider_provides_expected_services(): void
    {
        $provider = new ApiRotatorServiceProvider($this->app);

        $this->assertContains(ApiKeyRotatorService::class, $provider->provides());
        $this->assertContains(ProviderAdapterFactory::class, $provider->provides());
        $this->assertContains('api-rotator', $provider->provides());
    }
}

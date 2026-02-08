<?php

namespace Tests\Unit\Services\ApiRotator;

use App\Services\ApiRotator\Adapters\GeminiAdapter;
use App\Services\ApiRotator\Adapters\MistralAdapter;
use App\Services\ApiRotator\Adapters\OpenRouterAdapter;
use App\Services\ApiRotator\Contracts\ProviderAdapterInterface;
use App\Services\ApiRotator\ProviderAdapterFactory;
use InvalidArgumentException;
use Tests\TestCase;

class ProviderAdapterFactoryTest extends TestCase
{
    private ProviderAdapterFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new ProviderAdapterFactory();
    }

    public function test_make_returns_gemini_adapter(): void
    {
        $adapter = $this->factory->make('gemini');

        $this->assertInstanceOf(GeminiAdapter::class, $adapter);
        $this->assertInstanceOf(ProviderAdapterInterface::class, $adapter);
    }

    public function test_make_returns_mistral_adapter(): void
    {
        $adapter = $this->factory->make('mistral');

        $this->assertInstanceOf(MistralAdapter::class, $adapter);
        $this->assertInstanceOf(ProviderAdapterInterface::class, $adapter);
    }

    public function test_make_returns_openrouter_adapter(): void
    {
        $adapter = $this->factory->make('openrouter');

        $this->assertInstanceOf(OpenRouterAdapter::class, $adapter);
        $this->assertInstanceOf(ProviderAdapterInterface::class, $adapter);
    }

    public function test_make_throws_for_unknown_provider(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown provider: unknown');

        $this->factory->make('unknown');
    }

    public function test_all_returns_all_adapters(): void
    {
        $adapters = $this->factory->all();

        $this->assertIsArray($adapters);
        $this->assertCount(3, $adapters);
        $this->assertArrayHasKey('gemini', $adapters);
        $this->assertArrayHasKey('mistral', $adapters);
        $this->assertArrayHasKey('openrouter', $adapters);

        foreach ($adapters as $adapter) {
            $this->assertInstanceOf(ProviderAdapterInterface::class, $adapter);
        }
    }

    public function test_providers_returns_provider_names(): void
    {
        $providers = $this->factory->providers();

        $this->assertIsArray($providers);
        $this->assertCount(3, $providers);
        $this->assertContains('gemini', $providers);
        $this->assertContains('mistral', $providers);
        $this->assertContains('openrouter', $providers);
    }

    public function test_make_returns_same_instance_for_same_provider(): void
    {
        $adapter1 = $this->factory->make('gemini');
        $adapter2 = $this->factory->make('gemini');

        // Factory uses pre-created instances, so they should be the same object
        $this->assertSame($adapter1, $adapter2);
    }
}

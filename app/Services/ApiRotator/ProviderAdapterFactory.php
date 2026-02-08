<?php

namespace App\Services\ApiRotator;

use App\Services\ApiRotator\Adapters\GeminiAdapter;
use App\Services\ApiRotator\Adapters\MistralAdapter;
use App\Services\ApiRotator\Adapters\OpenRouterAdapter;
use App\Services\ApiRotator\Contracts\ProviderAdapterInterface;
use InvalidArgumentException;

class ProviderAdapterFactory
{
    private array $adapters = [];

    public function __construct()
    {
        $this->adapters = [
            'gemini' => new GeminiAdapter(),
            'mistral' => new MistralAdapter(),
            'openrouter' => new OpenRouterAdapter(),
        ];
    }

    public function make(string $provider): ProviderAdapterInterface
    {
        if (! isset($this->adapters[$provider])) {
            throw new InvalidArgumentException("Unknown provider: {$provider}");
        }

        return $this->adapters[$provider];
    }

    public function all(): array
    {
        return $this->adapters;
    }

    public function providers(): array
    {
        return array_keys($this->adapters);
    }
}

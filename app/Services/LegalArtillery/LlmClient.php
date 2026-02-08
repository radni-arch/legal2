<?php

namespace App\Services\LegalArtillery;

use App\Models\ApiKey;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Prism;
use Illuminate\Support\Facades\Http;

class LlmClient
{
    private int $totalTokensUsed = 0;

    private string $apiKey = '';

    private ?int $budgetLimit = null;

    public function __construct(
        private readonly string $provider,
        private readonly string $model,
        private readonly int $maxTokens = 8192,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            provider: config('legal-artillery.generation.provider', 'anthropic'),
            model: config('legal-artillery.generation.model', 'claude-sonnet-4-20250514'),
            maxTokens: (int) config('legal-artillery.generation.max_tokens', 8192),
        );
    }

    /**
     * Set a token budget for this client instance.
     */
    public function setBudget(int $maxTokens): self
    {
        $this->budgetLimit = $maxTokens;

        return $this;
    }

    /**
     * Get total tokens used in this session.
     */
    public function getTokensUsed(): int
    {
        return $this->totalTokensUsed;
    }

    /**
     * Get remaining budget (null if no budget set).
     */
    public function getRemainingBudget(): ?int
    {
        if ($this->budgetLimit === null) {
            return null;
        }

        return max(0, $this->budgetLimit - $this->totalTokensUsed);
    }

    /**
     * Check if budget is exceeded.
     */
    public function isBudgetExceeded(): bool
    {
        if ($this->budgetLimit === null) {
            return false;
        }

        return $this->totalTokensUsed >= $this->budgetLimit;
    }

    /**
     * Reset token counter.
     */
    public function resetTokenCount(): void
    {
        $this->totalTokensUsed = 0;
    }

    public function isConfigured(): bool
    {
        $configKey = "prism.providers.{$this->provider}.api_key";

        return ! empty(Config::get($configKey));
    }

    public function generate(string $systemPrompt, string $userPrompt, ?int $maxTokens = null): string
    {
        // Check budget before making API call
        if ($this->isBudgetExceeded()) {
            throw new \RuntimeException($this->buildBudgetExceededMessage());
        }

        // Ensure we have an API key available for Prism (DB > ENV)
        $this->resolveApiKey();

        if (! $this->isConfigured()) {
            throw new \RuntimeException(
                "API key is not configured for provider [{$this->provider}]. Set prism.providers.{$this->provider}.api_key (or provide an active DB key in api_keys)."
            );
        }

        $providerEnum = Provider::from($this->provider);

        $response = Prism::text()
            ->using($providerEnum, $this->model)
            ->withSystemPrompt($systemPrompt)
            ->withPrompt($userPrompt)
            ->withMaxTokens($maxTokens ?? $this->maxTokens)
            ->asText();

        // Track tokens from Prism response
        $promptTokens = (int) ($response->usage->promptTokens ?? 0);
        $completionTokens = (int) ($response->usage->completionTokens ?? 0);
        $this->totalTokensUsed += ($promptTokens + $completionTokens);

        if ($this->isBudgetExceeded()) {
            throw new \RuntimeException($this->buildBudgetExceededMessage());
        }

        Log::debug('LlmClient: generation complete', [
            'provider' => $this->provider,
            'model' => $this->model,
            'max_tokens' => $maxTokens ?? $this->maxTokens,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'total_tokens_used' => $this->totalTokensUsed,
            'finish_reason' => $response->finishReason->value ?? null,
        ]);

        return $response->text;
    }

    protected function buildBudgetExceededMessage(): string
    {
        $limit = $this->budgetLimit ?? 0;

        return "Token budget exceeded (limit: {$limit}, used: {$this->totalTokensUsed}).";
    }

    /**
     * Resolve API key from DB (api_keys table) or ENV (prism config).
     *
     * Priority: DB key (active, available, matching provider) > ENV key.
     * Injects the resolved key into Prism's runtime config.
     */
    protected function resolveApiKey(): void
    {
        $configKey = "prism.providers.{$this->provider}.api_key";
        $envKey = Config::get($configKey, '');

        // Try DB first — get an active, available key for this provider
        $dbKey = $this->getDbApiKey();

        if ($dbKey) {
            Config::set($configKey, $dbKey->api_key);

            Log::debug('LlmClient: using DB API key', [
                'provider' => $this->provider,
                'key_id' => $dbKey->id,
                'key_name' => $dbKey->name,
            ]);

            return;
        }

        // Fall back to ENV
        if (! empty($envKey)) {
            Log::debug('LlmClient: using ENV API key', [
                'provider' => $this->provider,
            ]);

            return;
        }

        Log::warning('LlmClient: no API key found for provider', [
            'provider' => $this->provider,
            'checked' => ['api_keys table', "ENV via {$configKey}"],
        ]);
    }

    /**
     * Query the api_keys table for an active, available key for this provider.
     */
    protected function getDbApiKey(): ?ApiKey
    {
        try {
            return ApiKey::active()
                ->available()
                ->forProvider($this->provider)
                ->byPriority()
                ->get()
                ->filter(fn (ApiKey $k) => $k->hasAvailableQuota())
                ->first();
        } catch (\Throwable $e) {
            // DB may not be available (testing, migrations, etc.)
            Log::debug('LlmClient: could not query api_keys table', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}

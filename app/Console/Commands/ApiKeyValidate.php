<?php

namespace App\Console\Commands;

use App\Models\ApiKey;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Validates API keys by making test requests to their respective providers.
 *
 * This command helps verify that API keys are actually working by making
 * minimal test requests to each provider's endpoint. It's useful for:
 * - Detecting expired or revoked keys
 * - Validating newly added keys
 * - Periodic health checks of the key pool
 */
class ApiKeyValidate extends Command
{
    protected $signature = 'apikey:validate
                            {--id= : Validate specific key by ID}
                            {--provider= : Validate keys for specific provider}';

    protected $description = 'Validate API keys by making test requests';

    public function handle(): int
    {
        $query = ApiKey::active();

        if ($id = $this->option('id')) {
            $query->where('id', $id);
        }

        if ($provider = $this->option('provider')) {
            $query->where('provider', $provider);
        }

        $keys = $query->get();

        if ($keys->isEmpty()) {
            $this->warn('No keys found to validate.');

            return Command::SUCCESS;
        }

        $hasInvalid = false;
        $results = [];

        foreach ($keys as $key) {
            $this->info("Validating {$key->name} ({$key->provider})...");

            $isValid = $this->validateKey($key);
            $status = $isValid ? '<fg=green>VALID</>' : '<fg=red>INVALID</>';

            $results[] = [
                $key->id,
                $key->name,
                $key->provider,
                $isValid ? 'VALID' : 'INVALID',
            ];

            if (! $isValid) {
                $hasInvalid = true;
            }
        }

        $this->newLine();
        $this->table(['ID', 'Name', 'Provider', 'Status'], $results);

        return $hasInvalid ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Validate a single API key by making a minimal test request.
     */
    private function validateKey(ApiKey $key): bool
    {
        try {
            return match ($key->provider) {
                'gemini' => $this->validateGemini($key),
                'mistral' => $this->validateMistral($key),
                'openrouter' => $this->validateOpenRouter($key),
                default => false,
            };
        } catch (\Throwable $e) {
            $this->error("  Error: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Validate a Gemini API key.
     */
    private function validateGemini(ApiKey $key): bool
    {
        $model = $key->model ?? 'gemini-2.0-flash-exp';
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$key->api_key}";

        $response = Http::timeout(30)->post($url, [
            'contents' => [['parts' => [['text' => 'Say "OK" and nothing else.']]]],
            'generationConfig' => ['maxOutputTokens' => 10],
        ]);

        return $response->successful();
    }

    /**
     * Validate a Mistral API key.
     */
    private function validateMistral(ApiKey $key): bool
    {
        $response = Http::timeout(30)
            ->withToken($key->api_key)
            ->post('https://api.mistral.ai/v1/chat/completions', [
                'model' => $key->model ?? 'mistral-small-latest',
                'messages' => [['role' => 'user', 'content' => 'Say OK']],
                'max_tokens' => 10,
            ]);

        return $response->successful();
    }

    /**
     * Validate an OpenRouter API key.
     */
    private function validateOpenRouter(ApiKey $key): bool
    {
        $response = Http::timeout(30)
            ->withToken($key->api_key)
            ->post('https://openrouter.ai/api/v1/chat/completions', [
                'model' => $key->model ?? 'google/gemini-2.0-flash-exp:free',
                'messages' => [['role' => 'user', 'content' => 'Say OK']],
                'max_tokens' => 10,
            ]);

        return $response->successful();
    }
}

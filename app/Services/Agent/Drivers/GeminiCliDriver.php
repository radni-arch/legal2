<?php

namespace App\Services\Agent\Drivers;

use App\DTOs\Agent\AgentSession;
use App\Services\Agent\BaseAgent;
use App\Services\Agent\Contracts\AgentCapability;

/**
 * Driver for Gemini CLI.
 *
 * Invokes the Gemini CLI with model selection and optional sandbox mode.
 */
class GeminiCliDriver extends BaseAgent
{
    /**
     * Get the driver identifier.
     */
    public function driver(): string
    {
        return 'gemini';
    }

    /**
     * Get human-readable name.
     */
    public function name(): string
    {
        return 'Gemini CLI';
    }

    /**
     * Get capabilities supported by Gemini CLI.
     *
     * @return AgentCapability[]
     */
    public function capabilities(): array
    {
        return [
            AgentCapability::FILE_READ,
            AgentCapability::FILE_WRITE,
            AgentCapability::BASH,
            AgentCapability::WEB_SEARCH,
            AgentCapability::JSON_OUTPUT,
        ];
    }

    /**
     * Get the binary path from config with default fallback.
     */
    protected function binary(): string
    {
        return config('agents.drivers.gemini.binary', 'gemini') ?? 'gemini';
    }

    /**
     * Get environment variables including GOOGLE_API_KEY.
     */
    protected function environment(array $options): array
    {
        // First check config-based env (useful for testing)
        $configEnv = config('agents.drivers.gemini.env', []);
        if (!empty($configEnv)) {
            return $configEnv;
        }

        // Fall back to actual env variable
        $apiKeyVar = config('agents.drivers.gemini.api_key_env', 'GOOGLE_API_KEY');
        $apiKey = env($apiKeyVar);

        if ($apiKey) {
            return [$apiKeyVar => $apiKey];
        }

        return [];
    }

    /**
     * Build Gemini CLI command.
     *
     * Format: gemini --model model --prompt prompt [--sandbox]
     *
     * @param string       $prompt  Full prompt
     * @param AgentSession $session Session info
     * @param array        $options Driver options
     * @return array Command parts
     */
    protected function buildCommand(string $prompt, AgentSession $session, array $options): array
    {
        $binary = $this->binary();
        $model = config('agents.drivers.gemini.model', 'gemini-2.5-pro');
        $sandbox = config('agents.drivers.gemini.sandbox', false);

        $command = [
            $binary,
            '--model', $model,
            '--prompt', $prompt,
        ];

        if ($sandbox) {
            $command[] = '--sandbox';
        }

        return $command;
    }

    /**
     * Parse Gemini's output format.
     *
     * Tries:
     * 1. Direct JSON decode
     * 2. Extract JSON from markdown code block
     */
    protected function parseOutput(string $rawOutput, AgentSession $session): ?array
    {
        // Try direct JSON decode
        $decoded = json_decode($rawOutput, true);
        if ($decoded !== null && json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        // Try to extract from markdown code block
        if (preg_match('/```(?:json)?\s*\n?(.*?)\n?```/s', $rawOutput, $matches)) {
            $jsonContent = trim($matches[1]);
            $decoded = json_decode($jsonContent, true);
            if ($decoded !== null && json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return null;
    }
}

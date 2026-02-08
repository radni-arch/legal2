<?php

namespace App\Services\Agent\Drivers;

use App\DTOs\Agent\AgentSession;
use App\Services\Agent\BaseAgent;
use App\Services\Agent\Contracts\AgentCapability;

/**
 * Driver for Claude CLI (Claude Code).
 *
 * Invokes the Claude CLI with specific flags for JSON output,
 * multi-turn conversations, and allowed tools.
 */
class ClaudeCodeDriver extends BaseAgent
{
    /**
     * Get the driver identifier.
     */
    public function driver(): string
    {
        return 'claude';
    }

    /**
     * Get human-readable name.
     */
    public function name(): string
    {
        return 'Claude Code';
    }

    /**
     * Get capabilities supported by Claude Code.
     *
     * @return AgentCapability[]
     */
    public function capabilities(): array
    {
        return [
            AgentCapability::FILE_READ,
            AgentCapability::FILE_WRITE,
            AgentCapability::BASH,
            AgentCapability::MULTI_TURN,
            AgentCapability::EXTENDED_THINKING,
            AgentCapability::JSON_OUTPUT,
        ];
    }

    /**
     * Get the binary path from config with default fallback.
     */
    protected function binary(): string
    {
        return config('agents.drivers.claude.binary', 'claude') ?? 'claude';
    }

    /**
     * Get environment variables including ANTHROPIC_API_KEY.
     */
    protected function environment(array $options): array
    {
        // First check config-based env (useful for testing)
        $configEnv = config('agents.drivers.claude.env', []);
        if (!empty($configEnv)) {
            return $configEnv;
        }

        // Fall back to actual env variable
        $apiKeyVar = config('agents.drivers.claude.api_key_env', 'ANTHROPIC_API_KEY');
        $apiKey = env($apiKeyVar);

        if ($apiKey) {
            return [$apiKeyVar => $apiKey];
        }

        return [];
    }

    /**
     * Build Claude CLI command with required flags.
     *
     * Format: claude --print --output-format json --max-turns N --allowedTools tools -p prompt
     *
     * @param string       $prompt  Full prompt
     * @param AgentSession $session Session info
     * @param array        $options Driver options
     * @return array Command parts
     */
    protected function buildCommand(string $prompt, AgentSession $session, array $options): array
    {
        $binary = $this->binary();
        $maxTurns = (string) config('agents.drivers.claude.max_turns', 25);
        $allowedTools = config('agents.drivers.claude.allowed_tools', 'bash,file_read,file_write');

        return [
            $binary,
            '--print',
            '--output-format', 'json',
            '--max-turns', $maxTurns,
            '--allowedTools', $allowedTools,
            '-p', $prompt,
        ];
    }

    /**
     * Parse Claude's JSON response format.
     *
     * Claude returns JSON with structure:
     * {
     *   "type": "message",
     *   "content": [
     *     {"type": "text", "text": "...content..."}
     *   ]
     * }
     *
     * This method extracts JSON from the text content, handling:
     * - Direct JSON in text
     * - JSON wrapped in markdown code blocks
     * - Multiple text blocks (tries each for JSON)
     */
    protected function parseOutput(string $rawOutput, AgentSession $session): ?array
    {
        // First, try to decode the Claude response wrapper
        $claudeResponse = json_decode($rawOutput, true);
        
        if ($claudeResponse === null || json_last_error() !== JSON_ERROR_NONE) {
            // Not valid JSON - try direct JSON decode of raw output
            return $this->tryParseJson($rawOutput);
        }

        // Extract text content from Claude's response structure
        $textContents = $this->extractTextContents($claudeResponse);

        // Try to find JSON in each text block
        foreach ($textContents as $text) {
            $parsed = $this->tryParseJson($text);
            if ($parsed !== null) {
                return $parsed;
            }
        }

        return null;
    }

    /**
     * Extract text contents from Claude's response structure.
     *
     * @param array $response Claude API response
     * @return string[] Array of text strings
     */
    private function extractTextContents(array $response): array
    {
        $texts = [];

        if (!isset($response['content']) || !is_array($response['content'])) {
            return $texts;
        }

        foreach ($response['content'] as $block) {
            if (isset($block['type']) && $block['type'] === 'text' && isset($block['text'])) {
                $texts[] = $block['text'];
            }
        }

        return $texts;
    }

    /**
     * Try to parse JSON from text content.
     *
     * Tries:
     * 1. Direct JSON decode
     * 2. Extract from markdown code block
     *
     * @param string $text Text that might contain JSON
     * @return array|null Parsed JSON or null
     */
    private function tryParseJson(string $text): ?array
    {
        // Try direct decode
        $decoded = json_decode($text, true);
        if ($decoded !== null && json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        // Try to extract from markdown code block
        if (preg_match('/```(?:json)?\s*\n?(.*?)\n?```/s', $text, $matches)) {
            $jsonContent = trim($matches[1]);
            $decoded = json_decode($jsonContent, true);
            if ($decoded !== null && json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return null;
    }
}

<?php

namespace App\Services\Agent\Drivers;

use App\DTOs\Agent\AgentSession;
use App\Services\Agent\BaseAgent;
use App\Services\Agent\Contracts\AgentCapability;
use Illuminate\Support\Str;

/**
 * Generic configurable CLI driver for custom agents.
 *
 * Reads all configuration from config('agents.drivers.{driver}'),
 * supporting placeholders in the command_template:
 * - {binary} - Path to the CLI binary
 * - {prompt} - The prompt string (shell-escaped)
 * - {prompt_file} - Path to a temp file containing the prompt
 * - {output_file} - Path where agent should write JSON output
 * - {work_dir} - Working directory for the session
 */
class GenericCliDriver extends BaseAgent
{
    protected string $driverName;

    public function __construct(string $driverName = 'generic')
    {
        $this->driverName = $driverName;
        parent::__construct();
    }

    /**
     * Get the driver identifier.
     */
    public function driver(): string
    {
        return $this->driverName;
    }

    /**
     * Get human-readable name (title case from driver name).
     */
    public function name(): string
    {
        return Str::title(str_replace('_', ' ', $this->driverName));
    }

    /**
     * Get capabilities from config as AgentCapability enums.
     *
     * @return AgentCapability[]
     */
    public function capabilities(): array
    {
        $configCapabilities = config("agents.drivers.{$this->driverName}.capabilities", []);

        $capabilities = [];
        foreach ($configCapabilities as $capability) {
            $enum = AgentCapability::tryFrom($capability);
            if ($enum !== null) {
                $capabilities[] = $enum;
            }
        }

        return $capabilities;
    }

    /**
     * Get the binary path from config.
     */
    protected function binary(): string
    {
        return config("agents.drivers.{$this->driverName}.binary", 'agent');
    }

    /**
     * Get environment variables from config.
     */
    protected function environment(array $options): array
    {
        return config("agents.drivers.{$this->driverName}.env", []);
    }

    /**
     * Build command from template with placeholder replacement.
     *
     * Uses unique markers for placeholders to preserve values with spaces.
     *
     * @param string       $prompt  Full prompt
     * @param AgentSession $session Session info
     * @param array        $options Driver options
     * @return array Command parts
     */
    protected function buildCommand(string $prompt, AgentSession $session, array $options): array
    {
        $template = config("agents.drivers.{$this->driverName}.command_template", '{binary} --prompt {prompt}');
        $binary = $this->binary();

        // Handle prompt file if template uses {prompt_file}
        $promptFile = null;
        if (str_contains($template, '{prompt_file}')) {
            // Ensure work directory exists
            if (!is_dir($session->workDir)) {
                mkdir($session->workDir, 0755, true);
            }
            $promptFile = $session->workDir . '/prompt.txt';
            file_put_contents($promptFile, $prompt);
        }

        // Use unique markers for placeholders (to preserve values with spaces)
        $markerId = uniqid('__PH_');
        $markers = [];
        $placeholderValues = [
            '{binary}' => $binary,
            '{prompt}' => $prompt,
            '{prompt_file}' => $promptFile ?? '',
            '{output_file}' => $session->outputFile,
            '{work_dir}' => $session->workDir,
        ];

        // Replace placeholders with unique markers
        $commandString = $template;
        foreach ($placeholderValues as $placeholder => $value) {
            $marker = $markerId . count($markers) . '__';
            $markers[$marker] = $value;
            $commandString = str_replace($placeholder, $marker, $commandString);
        }

        // Parse the command string (markers won't have spaces)
        $parts = $this->parseCommandString($commandString);

        // Replace markers with actual values in the parsed parts
        return array_map(function ($part) use ($markers) {
            foreach ($markers as $marker => $value) {
                if (str_contains($part, $marker)) {
                    return str_replace($marker, $value, $part);
                }
            }
            return $part;
        }, $parts);
    }

    /**
     * Parse a command string into array parts.
     *
     * Handles basic quoting but doesn't try to be a full shell parser.
     */
    protected function parseCommandString(string $commandString): array
    {
        $parts = [];
        $current = '';
        $inQuote = false;
        $quoteChar = null;

        for ($i = 0; $i < strlen($commandString); $i++) {
            $char = $commandString[$i];

            if (!$inQuote && ($char === '"' || $char === "'")) {
                $inQuote = true;
                $quoteChar = $char;
            } elseif ($inQuote && $char === $quoteChar) {
                $inQuote = false;
                $quoteChar = null;
            } elseif (!$inQuote && $char === ' ') {
                if ($current !== '') {
                    $parts[] = $current;
                    $current = '';
                }
            } else {
                $current .= $char;
            }
        }

        if ($current !== '') {
            $parts[] = $current;
        }

        return $parts;
    }

    /**
     * Parse raw output into structured array.
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

        // Try to extract JSON from markdown code block
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

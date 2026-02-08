<?php

namespace App\DTOs\Agent;

class AgentResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $driver,           // 'claude', 'gemini', etc.
        public readonly ?array $results,          // Parsed JSON output
        public readonly string $rawOutput,        // Raw stdout
        public readonly string $rawError,         // Raw stderr
        public readonly int $exitCode,
        public readonly float $elapsedSeconds,
        public readonly string $sessionId,
        public readonly ?string $outputFile,      // Path to JSON output file
        public readonly array $metadata = [],     // Driver-specific metadata
    ) {}

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'driver' => $this->driver,
            'results' => $this->results,
            'raw_output' => $this->rawOutput,
            'raw_error' => $this->rawError,
            'exit_code' => $this->exitCode,
            'elapsed_seconds' => $this->elapsedSeconds,
            'session_id' => $this->sessionId,
            'output_file' => $this->outputFile,
            'metadata' => $this->metadata,
        ];
    }
}

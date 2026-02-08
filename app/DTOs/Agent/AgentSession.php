<?php

namespace App\DTOs\Agent;

class AgentSession
{
    public function __construct(
        public readonly string $sessionId,
        public readonly string $workDir,
        public readonly string $outputFile,
        public readonly array $symlinkMap,      // original_path => session_path
    ) {}
}

<?php

namespace App\Services\Agent\Contracts;

use App\DTOs\Agent\AgentResult;

interface AgentInterface
{
    /**
     * Unique driver identifier.
     */
    public function driver(): string;

    /**
     * Human-readable name.
     */
    public function name(): string;

    /**
     * Check if the agent binary is available on this system.
     */
    public function isAvailable(): bool;

    /**
     * What capabilities this agent supports.
     *
     * @return AgentCapability[]
     */
    public function capabilities(): array;

    /**
     * Run a prompt with file context.
     *
     * @param string   $prompt     The task description
     * @param string[] $filePaths  Files to make available
     * @param array    $options    Driver-specific options
     */
    public function run(string $prompt, array $filePaths = [], array $options = []): AgentResult;

    /**
     * Run a multi-phase analysis (per-document to cross-document).
     * Default implementation calls run() twice; drivers can override.
     */
    public function bulkAnalysis(string $caseId, array $filePaths, array $phases): array;
}

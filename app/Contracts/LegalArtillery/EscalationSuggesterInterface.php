<?php

namespace App\Contracts\LegalArtillery;

use App\DTOs\CaseContext;

/**
 * Canonical interface for escalation suggestions.
 *
 * Consolidates the old App\Services\EscalationLadderSuggester and
 * App\Services\EscalationHierarchySuggester into a single contract.
 *
 * @see \App\Services\LegalArtillery\EscalationLadderSuggester (canonical implementation)
 * @covers SOT-010
 */
interface EscalationSuggesterInterface
{
    /**
     * Suggest the next escalation rung based on ladder config.
     *
     * @return array<string, mixed>
     */
    public function suggestNextRung(
        string $failedProfileKey,
        string $responseType,
        int $elapsedDays,
        string $ladderKey = 'default',
        ?CaseContext $caseContext = null,
    ): array;

    /**
     * Get the linear hierarchy of escalation steps.
     *
     * @return array<int, string>
     */
    public function hierarchy(): array;

    /**
     * Suggest the next step in the linear hierarchy.
     */
    public function suggestNext(?string $current): ?string;

    /**
     * Check if the current step is the terminal (last) step.
     */
    public function isTerminal(?string $current): bool;
}

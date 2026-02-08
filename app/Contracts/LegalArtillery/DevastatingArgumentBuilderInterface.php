<?php

namespace App\Contracts\LegalArtillery;

use App\DTOs\LegalArtillery\ArgumentChain;

/**
 * Contract for building devastating argument chains for legal documents.
 *
 * Implementations should provide access to pre-configured argument chains
 * that build logically from premises to knockout conclusions.
 */
interface DevastatingArgumentBuilderInterface
{
    /**
     * Get all argument chains applicable to a specific profile.
     *
     * @param string $profileKey The document profile key
     * @return array<ArgumentChain> Array of argument chains for the profile
     */
    public function getChainsForProfile(string $profileKey): array;

    /**
     * Build a formatted argument injection string for LLM prompts.
     *
     * @param string $profileKey The document profile key
     * @return string Formatted argument chains for prompt injection
     */
    public function buildArgumentInjection(string $profileKey): string;

    /**
     * Get killer summaries for quick reference.
     *
     * @param string $profileKey The document profile key
     * @return array Array of ['name' => string, 'summary' => string]
     */
    public function getKillerSummaries(string $profileKey): array;
}

<?php

namespace App\Contracts\LegalArtillery;

/**
 * Contract for accessing sample documents for style/structure reference.
 *
 * Implementations should provide access to reference documents that
 * guide LLM document generation for specific profile types.
 */
interface SampleDocumentStoreInterface
{
    /**
     * Get the sample document content for a profile.
     *
     * @param string $profileKey The document profile key
     * @return string|null Sample document content, or null if not available
     */
    public function getSampleForProfile(string $profileKey): ?string;

    /**
     * Check if a sample document exists for a profile.
     *
     * @param string $profileKey The document profile key
     * @return bool True if sample exists
     */
    public function hasSampleForProfile(string $profileKey): bool;

    /**
     * Get common blocks (reusable text sections) for interpolation.
     *
     * @return string|null Common blocks content
     */
    public function getCommonBlocks(): ?string;
}

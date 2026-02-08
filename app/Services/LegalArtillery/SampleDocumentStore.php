<?php

namespace App\Services\LegalArtillery;

use App\Contracts\LegalArtillery\SampleDocumentStoreInterface;
use Illuminate\Support\Facades\File;

/**
 * Provides reference document samples for LLM-based document generation.
 *
 * Samples are stored as markdown files in the samples directory and serve as
 * stylistic and structural guides for the AI agent. The agent uses these
 * samples to understand proper Croatian legal document format and tone.
 */
class SampleDocumentStore implements SampleDocumentStoreInterface
{
    private string $samplesDir;

    public function __construct(?string $samplesDir = null)
    {
        $this->samplesDir = $samplesDir ?? resource_path('legal-artillery/samples');
    }

    /**
     * Get the sample document text for a profile.
     *
     * @param string $profileKey The profile key (e.g., 'predsjednik_suda')
     * @return string|null The sample document content, or null if not found
     */
    public function getSample(string $profileKey): ?string
    {
        $path = $this->getSamplePath($profileKey);

        if ($path === null) {
            return null;
        }

        return File::get($path);
    }

    /**
     * Get the file path to a sample document.
     *
     * @param string $profileKey The profile key
     * @return string|null The full path to the sample file, or null if not found
     */
    public function getSamplePath(string $profileKey): ?string
    {
        $path = $this->buildSamplePath($profileKey);

        if (!File::exists($path)) {
            return null;
        }

        return $path;
    }

    /**
     * Check if a sample document exists for a profile.
     *
     * @param string $profileKey The profile key
     * @return bool True if a sample exists for this profile
     */
    public function hasSample(string $profileKey): bool
    {
        return File::exists($this->buildSamplePath($profileKey));
    }

    /**
     * Get all available sample documents.
     *
     * @return array<string, string> Associative array of profile_key => sample_content
     */
    public function getAllSamples(): array
    {
        if (!File::isDirectory($this->samplesDir)) {
            return [];
        }

        $samples = [];
        $files = File::glob("{$this->samplesDir}/*.md");

        foreach ($files as $file) {
            $profileKey = pathinfo($file, PATHINFO_FILENAME);
            $samples[$profileKey] = File::get($file);
        }

        return $samples;
    }

    /**
     * Get the sample document content for a profile (interface method).
     *
     * @param string $profileKey The document profile key
     * @return string|null Sample document content, or null if not available
     */
    public function getSampleForProfile(string $profileKey): ?string
    {
        return $this->getSample($profileKey);
    }

    /**
     * Check if a sample document exists for a profile (interface method).
     *
     * @param string $profileKey The document profile key
     * @return bool True if sample exists
     */
    public function hasSampleForProfile(string $profileKey): bool
    {
        return $this->hasSample($profileKey);
    }

    /**
     * Get common blocks (reusable text sections) for interpolation.
     *
     * @return string|null Common blocks content
     */
    public function getCommonBlocks(): ?string
    {
        $path = "{$this->samplesDir}/common_blocks.md";

        if (!File::exists($path)) {
            return null;
        }

        return File::get($path);
    }

    /**
     * Build the full path to a sample file.
     *
     * @param string $profileKey The profile key
     * @return string The full path (whether or not file exists)
     */
    private function buildSamplePath(string $profileKey): string
    {
        return "{$this->samplesDir}/{$profileKey}.md";
    }
}

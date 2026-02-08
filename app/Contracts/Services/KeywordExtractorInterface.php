<?php

namespace App\Contracts\Services;

/**
 * Interface for advanced keyword extraction services.
 *
 * Provides methods for extracting meaningful keywords and phrases
 * from legal text using AI-powered analysis.
 */
interface KeywordExtractorInterface
{
    /**
     * Extract keywords from content.
     *
     * @param  string  $content  The content to extract keywords from
     * @param  int|null  $maxKeywords  Maximum number of keywords to extract
     * @param  array  $options  Additional extraction options
     * @return array Array of extracted keywords with scores
     */
    public function extract(string $content, ?int $maxKeywords = null, array $options = []): array;

    /**
     * Get the current extractor configuration.
     *
     * @return array Configuration settings
     */
    public function getConfig(): array;
}

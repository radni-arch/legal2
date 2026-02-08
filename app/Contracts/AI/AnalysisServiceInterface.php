<?php

namespace App\Contracts\AI;

/**
 * Contract for AI-powered analysis services
 */
interface AnalysisServiceInterface
{
    /**
     * Analyze legal text
     *
     * @param  string  $text  Legal text to analyze
     * @param  array  $options  Analysis options
     * @return array Analysis results
     */
    public function analyzeLegalText(string $text, array $options = []): array;

    /**
     * Summarize text
     *
     * @param  string  $text  Text to summarize
     * @param  int  $maxLength  Maximum summary length
     * @return string Summary
     */
    public function summarize(string $text, int $maxLength = 500): string;

    /**
     * Extract structured information from text
     *
     * @param  array  $schema  Expected output schema
     * @return array Extracted information
     */
    public function extractStructuredData(string $text, array $schema): array;
}

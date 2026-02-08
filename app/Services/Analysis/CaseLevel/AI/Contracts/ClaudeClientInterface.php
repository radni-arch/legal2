<?php

namespace App\Services\Analysis\CaseLevel\AI\Contracts;

/**
 * Interface for Claude AI API client operations.
 *
 * Provides contract for AI-powered analysis methods used by case-level analyzers.
 * Implementations should handle API communication with Claude/Anthropic services.
 */
interface ClaudeClientInterface
{
    /**
     * Analyze key facts for contradictions between documents.
     *
     * @param array $factsPayload Array of document facts with structure:
     *                            [['document_id' => int, 'document_title' => string, 'key_facts' => array], ...]
     * @param array $options Additional options for the analysis
     * @return array Analysis results with structure:
     *               ['contradictions' => array, 'summary' => string]
     *
     * @throws \RuntimeException When API call fails
     */
    public function analyzeForContradictions(array $factsPayload, array $options = []): array;

    /**
     * Analyze case for gaps in documentation.
     *
     * @param array $caseData Case information including documents, timeline, and procedures
     * @param array $options Additional options for the analysis
     * @return array Gap analysis results with structure:
     *               ['gaps' => array, 'missing_documents' => array, 'timeline_gaps' => array, 'summary' => string]
     *
     * @throws \RuntimeException When API call fails
     */
    public function analyzeForGaps(array $caseData, array $options = []): array;

    /**
     * Generate strategic recommendations based on case analysis.
     *
     * @param array $analysisData Combined analysis data from all layers
     * @param array $options Additional options for strategy generation
     * @return array Strategy recommendations with structure:
     *               ['strategies' => array, 'priority_actions' => array, 'risk_assessment' => array, 'summary' => string]
     *
     * @throws \RuntimeException When API call fails
     */
    public function generateStrategicInsights(array $analysisData, array $options = []): array;
}

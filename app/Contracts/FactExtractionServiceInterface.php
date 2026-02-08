<?php

namespace App\Contracts;

/**
 * Fact Extraction Service Interface
 *
 * Defines the contract for services that extract and analyze facts
 * from legal documents, particularly court decisions.
 */
interface FactExtractionServiceInterface
{
    /**
     * Extract facts from a court decision
     *
     * Uses AI to identify and extract key facts from a decision,
     * including parties, dates, events, locations, and legal issues.
     *
     * @param  string  $decisionId  The unique identifier of the court decision
     * @param  array  $options  Extraction options (e.g., model, temperature, format)
     * @return array{
     *     facts: array,
     *     parties: array,
     *     dates: array,
     *     events: array,
     *     legal_issues: array,
     *     metadata: array
     * } Extracted facts and metadata
     *
     * @throws \InvalidArgumentException If decision ID is invalid
     * @throws \RuntimeException If extraction fails
     */
    public function extractFacts(string $decisionId, array $options = []): array;

    /**
     * Compare facts between two court decisions
     *
     * Analyzes two decisions and identifies similar facts, legal issues,
     * and patterns to support precedent research.
     *
     * @param  string  $decisionId1  First decision ID
     * @param  string  $decisionId2  Second decision ID
     * @return array{
     *     similarity_score: float,
     *     common_facts: array,
     *     common_issues: array,
     *     differences: array,
     *     analysis: string
     * } Comparison results
     *
     * @throws \InvalidArgumentException If decision IDs are invalid
     * @throws \RuntimeException If comparison fails
     */
    public function compareDecisionFacts(string $decisionId1, string $decisionId2): array;

    /**
     * Extract facts from multiple decisions in batch
     *
     * Efficiently processes multiple decisions in parallel or batches
     * to extract facts from all of them.
     *
     * @param  array<string>  $decisionIds  Array of decision IDs
     * @param  array  $options  Extraction options
     * @return array<string, array> Map of decision IDs to extracted facts
     *
     * @throws \InvalidArgumentException If any decision ID is invalid
     */
    public function batchExtractFacts(array $decisionIds, array $options = []): array;
}

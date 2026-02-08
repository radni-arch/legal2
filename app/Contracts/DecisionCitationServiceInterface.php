<?php

namespace App\Contracts;

/**
 * Decision Citation Service Interface
 *
 * Defines the contract for services that search, extract, and analyze
 * citations in court decisions.
 */
interface DecisionCitationServiceInterface
{
    /**
     * Search for decisions that cite a specific law or article
     *
     * Finds all court decisions that reference a given law or law article,
     * useful for finding case law interpreting specific legal provisions.
     *
     * @param  string  $lawIdentifier  The law identifier (e.g., "ZKP", "Kazneni zakon")
     * @param  string|null  $articleNumber  Optional article number (e.g., "9", "140")
     * @param  array  $options  Search options (limit, offset, court, date_range, etc.)
     * @return array{
     *     decisions: array,
     *     total: int,
     *     law: string,
     *     article: string|null
     * } Search results
     *
     * @throws \InvalidArgumentException If law identifier is invalid
     */
    public function searchByCitedLaw(string $lawIdentifier, ?string $articleNumber = null, array $options = []): array;

    /**
     * Extract all citations from a court decision
     *
     * Identifies and extracts all legal citations (laws, articles, other decisions)
     * referenced in a court decision.
     *
     * @param  string  $decisionId  The unique identifier of the court decision
     * @return array{
     *     law_citations: array,
     *     decision_citations: array,
     *     article_citations: array,
     *     total_citations: int
     * } Extracted citations
     *
     * @throws \InvalidArgumentException If decision ID is invalid
     */
    public function extractCitations(string $decisionId): array;

    /**
     * Find similar decisions based on citation patterns
     *
     * Identifies decisions that cite similar laws and legal provisions,
     * indicating potential precedent value.
     *
     * @param  string  $decisionId  The reference decision ID
     * @param  array  $options  Similarity options (threshold, limit, etc.)
     * @return array{
     *     similar_decisions: array,
     *     similarity_scores: array,
     *     common_citations: array
     * } Similar decisions
     *
     * @throws \InvalidArgumentException If decision ID is invalid
     */
    public function findSimilarDecisions(string $decisionId, array $options = []): array;

    /**
     * Search for decisions by legal concept or topic
     *
     * Finds decisions that deal with a specific legal concept,
     * using citation patterns and content analysis.
     *
     * @param  string  $concept  The legal concept or topic
     * @param  array  $options  Search options
     * @return array{
     *     decisions: array,
     *     total: int,
     *     concept: string,
     *     related_concepts: array
     * } Search results
     *
     * @throws \InvalidArgumentException If concept is empty
     */
    public function searchByLegalConcept(string $concept, array $options = []): array;

    /**
     * Analyze citation patterns in a court decision
     *
     * Provides detailed analysis of how a decision uses citations,
     * including frequency, context, and relationship to legal reasoning.
     *
     * @param  string  $decisionId  The decision ID to analyze
     * @return array{
     *     citation_count: int,
     *     most_cited_laws: array,
     *     citation_density: float,
     *     citation_contexts: array,
     *     legal_reasoning_strength: float
     * } Citation analysis
     *
     * @throws \InvalidArgumentException If decision ID is invalid
     */
    public function analyzeDecisionCitations(string $decisionId): array;
}

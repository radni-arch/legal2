<?php

namespace App\Contracts;

/**
 * RAG (Retrieval-Augmented Generation) Orchestrator Interface
 *
 * Defines the contract for services that orchestrate retrieval-augmented
 * generation workflows, combining vector search, graph traversal, and
 * LLM generation to answer legal queries.
 */
interface RagOrchestratorInterface
{
    /**
     * Retrieve relevant information using RAG pipeline
     *
     * Orchestrates the RAG workflow:
     * 1. Vector search for semantically similar documents
     * 2. Graph traversal for related legal concepts
     * 3. Ranking and reranking of results
     * 4. Optional LLM generation with retrieved context
     *
     * @param  string  $query  The search query or question
     * @param  array  $options  Configuration options for the RAG pipeline
     *                          May include: limit, threshold, include_graph,
     *                          generate_answer, temperature, etc.
     * @return array{
     *     results: array,
     *     metadata: array,
     *     answer?: string,
     *     sources?: array
     * } Retrieved results and optional generated answer
     *
     * @throws \InvalidArgumentException If query is empty or invalid
     * @throws \RuntimeException If retrieval fails
     */
    public function retrieve(string $query, array $options = []): array;
}

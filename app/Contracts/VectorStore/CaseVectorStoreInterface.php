<?php

namespace App\Contracts\VectorStore;

/**
 * Case Vector Store Interface
 *
 * Handles ingestion and search of internal case documents
 * with embeddings and metadata storage.
 */
interface CaseVectorStoreInterface
{
    /**
     * Ingest case documents into vector store
     *
     * @param  string  $caseId  Unique case identifier
     * @param  string  $docId  Document identifier for vector storage
     * @param  array  $docs  Array of document chunks with content and metadata
     * @param  array  $options  Options: model, provider, metadata
     * @return array{count: int, case_id: string, doc_id: string} Ingestion results
     *
     * @throws \RuntimeException If ingestion fails
     */
    public function ingest(string $caseId, string $docId, array $docs, array $options = []): array;

    /**
     * Search for similar case documents using embedding
     *
     * @param  array  $embedding  Query embedding vector
     * @param  array  $options  Search options: limit, threshold, filters
     * @return array Search results with similarity scores
     *
     * @throws \RuntimeException If search fails
     */
    public function search(array $embedding, array $options = []): array;
}

<?php

namespace App\Contracts\VectorStore;

/**
 * Court Decision Vector Store Interface
 *
 * Handles ingestion and search of court decisions from odluke.sudovi.hr
 * with embeddings and metadata storage.
 */
interface CourtDecisionVectorStoreInterface
{
    /**
     * Ingest court decision documents into vector store
     *
     * @param  string  $decisionId  Unique decision identifier
     * @param  string  $docId  Document identifier for vector storage
     * @param  array  $docs  Array of document chunks with content and metadata
     * @param  array  $options  Options: model, provider, metadata, sync_graph
     * @return array{count: int, decision_id: string, doc_id: string} Ingestion results
     *
     * @throws \RuntimeException If ingestion fails
     */
    public function ingest(string $decisionId, string $docId, array $docs, array $options = []): array;

    /**
     * Upsert vectors into court decision store
     *
     * @param  array  $vectors  Array of vectors with embeddings and metadata
     * @param  array  $options  Upsert options
     * @return array Upsert results
     *
     * @throws \RuntimeException If upsert fails
     */
    public function upsert(array $vectors, array $options = []): array;
}

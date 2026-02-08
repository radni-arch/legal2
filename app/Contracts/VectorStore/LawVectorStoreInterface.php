<?php

namespace App\Contracts\VectorStore;

/**
 * Law Vector Store Interface
 *
 * Handles ingestion and search of Croatian legal documents (laws, regulations)
 * with embeddings and metadata storage.
 */
interface LawVectorStoreInterface
{
    /**
     * Ingest pre-chunked law articles into vector store
     *
     * @param  string  $docId  Stable identifier grouping chunks (e.g., ELI or slug-date)
     * @param  array  $docs  Array of document chunks with content and metadata
     * @param  array  $options  Options: model, provider, base_meta, ingested_law_id
     * @return array{count: int, inserted: int, doc_id?: string} Ingestion results
     *
     * @throws \RuntimeException If ingestion fails
     */
    public function ingest(string $docId, array $docs, array $options = []): array;

    /**
     * Search for similar law documents using embedding
     *
     * @param  array  $embedding  Query embedding vector
     * @param  array  $options  Search options: limit, threshold, filters
     * @return array Search results with similarity scores
     *
     * @throws \RuntimeException If search fails
     */
    public function search(array $embedding, array $options = []): array;
}

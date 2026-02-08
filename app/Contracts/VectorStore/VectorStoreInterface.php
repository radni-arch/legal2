<?php

namespace App\Contracts\VectorStore;

/**
 * Base Vector Store Interface
 *
 * Defines the contract for all vector store implementations that handle
 * document ingestion, embedding storage, and semantic search.
 */
interface VectorStoreInterface
{
    /**
     * Ingest documents into vector store
     *
     * Processes documents, generates embeddings, and stores them in the vector database.
     *
     * @param  string  $docId  Unique document identifier
     * @param  array  $documents  Array of document chunks or content
     * @param  array  $options  Ingestion options (e.g., batch_size, sync_graph, model)
     * @return array{
     *     success: bool,
     *     doc_id: string,
     *     chunks_stored: int,
     *     metadata: array
     * } Ingestion results
     *
     * @throws \InvalidArgumentException If document ID or documents are invalid
     * @throws \RuntimeException If ingestion fails
     */
    public function ingest(string $docId, array $documents, array $options = []): array;

    /**
     * Search vector store using semantic similarity
     *
     * Performs vector similarity search using the provided embedding.
     *
     * @param  array  $embedding  Query embedding vector
     * @param  array  $options  Search options (e.g., limit, threshold, filter)
     * @return array{
     *     results: array,
     *     total: int,
     *     metadata: array
     * } Search results with similarity scores
     *
     * @throws \InvalidArgumentException If embedding is invalid
     * @throws \RuntimeException If search fails
     */
    public function search(array $embedding, array $options = []): array;

    /**
     * Delete documents from vector store
     *
     * Removes all embeddings and metadata for the specified document.
     *
     * @param  string  $docId  Document identifier to delete
     * @return bool True if deleted successfully, false if not found
     *
     * @throws \RuntimeException If deletion fails
     */
    public function delete(string $docId): bool;

    /**
     * Get embedding dimensions for this vector store
     *
     * Returns the dimensionality of embeddings used by this store
     * (e.g., 1536 for text-embedding-3-small, 3072 for text-embedding-3-large).
     *
     * @return int Embedding dimension count
     */
    public function getEmbeddingDimensions(): int;

    /**
     * Check if document exists in vector store
     *
     * Verifies whether embeddings exist for the given document ID.
     *
     * @param  string  $docId  Document identifier to check
     * @return bool True if document exists, false otherwise
     */
    public function exists(string $docId): bool;

    /**
     * Get the embedding vector for a specific document.
     *
     * Retrieves the stored embedding vector for a given document ID.
     * Used by GraphSimilarityLinker to find similar documents via pgvector.
     *
     * @param  string  $documentId  The document identifier
     * @return array|null The embedding vector or null if not found
     */
    public function getEmbedding(string $documentId): ?array;

    /**
     * Find similar documents using pgvector cosine similarity.
     *
     * Queries the vector store using PostgreSQL's pgvector extension to find
     * documents similar to the specified source document based on cosine similarity.
     *
     * @param  string  $documentId  The source document ID
     * @param  float  $threshold  Minimum similarity score (0.0-1.0)
     * @param  int  $limit  Maximum number of results
     * @return array Array of ['id' => string, 'similarity_score' => float]
     */
    public function findSimilar(string $documentId, float $threshold = 0.8, int $limit = 20): array;
}

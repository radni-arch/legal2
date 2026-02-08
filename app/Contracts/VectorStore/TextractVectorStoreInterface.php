<?php

namespace App\Contracts\VectorStore;

/**
 * Textract Vector Store Interface
 *
 * Handles ingestion and search of OCR'd documents processed by AWS Textract
 * with embeddings and metadata storage.
 */
interface TextractVectorStoreInterface
{
    /**
     * Ingest Textract OCR job results into vector store
     *
     * @param  int  $textractJobId  Textract job identifier
     * @param  array  $options  Options: model, chunk_size, overlap, regenerate
     * @return array{success: bool, job_id: int, chunks: int, embeddings: int} Ingestion results
     *
     * @throws \RuntimeException If ingestion fails
     */
    public function ingestTextractJob(int $textractJobId, array $options = []): array;

    /**
     * Chunk text into segments for embedding
     *
     * @param  string  $content  Text content to chunk
     * @param  array  $options  Chunking options: chunk_size, overlap
     * @return array Array of text chunks
     */
    public function chunkText(string $content, array $options = []): array;

    /**
     * Generate embedding with retry logic
     *
     * @param  string|array  $input  Text or array of texts to embed
     * @param  string  $model  Embedding model name
     * @param  int  $maxRetries  Maximum retry attempts
     * @return array|null Embedding result or null on failure
     */
    public function generateEmbeddingWithRetry(string|array $input, string $model, int $maxRetries = 3): ?array;

    /**
     * Search for similar documents using semantic query
     *
     * @param  string  $query  Search query text
     * @param  array  $options  Search options: limit, threshold, job_id
     * @return array Search results with similarity scores
     *
     * @throws \RuntimeException If search fails
     */
    public function searchSimilar(string $query, array $options = []): array;

    /**
     * Regenerate embeddings for a Textract job
     *
     * @param  int  $textractJobId  Textract job identifier
     * @param  array  $options  Regeneration options
     * @return array Regeneration results
     *
     * @throws \RuntimeException If regeneration fails
     */
    public function regenerateEmbeddings(int $textractJobId, array $options = []): array;

    /**
     * Get embedding statistics for a Textract job
     *
     * @param  int  $textractJobId  Textract job identifier
     * @return array Statistics including total, success, failed counts
     */
    public function getEmbeddingStats(int $textractJobId): array;
}

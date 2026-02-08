<?php

namespace App\Contracts\AI;

/**
 * Contract for embedding generation services
 */
interface EmbeddingServiceInterface
{
    /**
     * Generate embedding for text
     *
     * @param  string  $text  Text to embed
     * @param  string  $model  Embedding model
     * @return array Embedding vector
     */
    public function embed(string $text, string $model = 'text-embedding-3-small'): array;

    /**
     * Generate embeddings for multiple texts
     *
     * @param  array  $texts  Array of texts
     * @return array Array of embedding vectors
     */
    public function batchEmbed(array $texts, string $model = 'text-embedding-3-small'): array;

    /**
     * Get embedding dimensions for a model
     *
     * @return int Dimension count (e.g., 1536)
     */
    public function getEmbeddingDimensions(string $model): int;
}

<?php

namespace App\Services\Search;

use App\Services\OpenAIService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * SearchEmbeddingService
 *
 * Responsible for generating embeddings for search queries.
 * Extracted from UnifiedSearchService as part of Phase 2 refactoring.
 *
 * Target: 100-150 lines
 */
class SearchEmbeddingService
{
    /**
     * Create a new SearchEmbeddingService instance
     *
     * @param  OpenAIService  $openai  OpenAI service for generating embeddings
     */
    public function __construct(
        protected OpenAIService $openai
    ) {}

    /**
     * Generate embedding vector for a search query
     *
     * This method generates a dense vector representation of the search query
     * using OpenAI's embedding models. The resulting vector can be used for
     * semantic similarity search in vector databases.
     *
     * @param  string  $query  The search query text to embed
     * @param  string|null  $model  The embedding model to use (defaults to config value)
     * @return array The embedding vector as an array of floats, or empty array on error
     *
     * @example
     * ```php
     * $service = new SearchEmbeddingService($openai);
     * $embedding = $service->embedQuery('Croatian criminal law');
     * // Returns: [0.123, -0.456, 0.789, ...] (1536 dimensions)
     * ```
     */
    public function embedQuery(string $query, ?string $model = null): array
    {
        $correlationId = request()->header('X-Request-ID') ?? Str::uuid()->toString();
        $startTime = microtime(true);

        Log::withContext(['correlation_id' => $correlationId]);

        Log::info('SearchEmbedding embedQuery initiated', [
            'query_length' => strlen($query),
            'model' => $model ?? config('openai.models.embeddings'),
            'user_id' => auth()->id(),
        ]);

        try {
            // Use default model from config if not specified
            $model = $model ?? config('openai.models.embeddings');

            // Call OpenAI embeddings API
            // The API expects an array of texts, so we wrap the query in an array
            $result = $this->openai->embeddings([$query], $model);

            // Extract the embedding from the response
            // Response format: { "data": [{ "embedding": [...] }] }
            $embedding = $result['data'][0]['embedding'] ?? [];

            $duration = (microtime(true) - $startTime) * 1000;

            Log::info('SearchEmbedding embedQuery completed', [
                'embedding_dimension' => count($embedding),
                'has_embedding' => ! empty($embedding),
                'duration_ms' => round($duration, 2),
            ]);

            return $embedding;
        } catch (\Exception $e) {
            Log::error('SearchEmbedding embedQuery failed', [
                'query_length' => strlen($query),
                'model' => $model,
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return empty array on error to allow graceful degradation
            // The calling code should check for empty embeddings
            return [];
        }
    }

    /**
     * Generate embeddings for multiple queries in a single batch
     *
     * This is more efficient than calling embedQuery multiple times when you
     * have multiple queries to embed at once.
     *
     * @param  array  $queries  Array of query strings to embed
     * @param  string|null  $model  The embedding model to use
     * @return array Array of embeddings, where each element is an embedding vector
     *
     * @example
     * ```php
     * $embeddings = $service->embedQueries([
     *     'Croatian criminal law',
     *     'Court decisions about theft',
     * ]);
     * // Returns: [[0.123, ...], [0.456, ...]]
     * ```
     */
    public function embedQueries(array $queries, ?string $model = null): array
    {
        $correlationId = request()->header('X-Request-ID') ?? Str::uuid()->toString();
        $startTime = microtime(true);

        Log::withContext(['correlation_id' => $correlationId]);

        Log::info('SearchEmbedding embedQueries initiated', [
            'query_count' => count($queries),
            'model' => $model ?? config('openai.models.embeddings'),
            'user_id' => auth()->id(),
        ]);

        try {
            if (empty($queries)) {
                Log::debug('SearchEmbedding embedQueries skipped - no queries');

                return [];
            }

            // Use default model from config if not specified
            $model = $model ?? config('openai.models.embeddings');

            // Call OpenAI embeddings API with batch of queries
            $result = $this->openai->embeddings($queries, $model);

            // Extract embeddings from response
            $embeddings = [];
            foreach ($result['data'] ?? [] as $item) {
                $embeddings[] = $item['embedding'] ?? [];
            }

            $duration = (microtime(true) - $startTime) * 1000;

            Log::info('SearchEmbedding embedQueries completed', [
                'query_count' => count($queries),
                'embedding_count' => count($embeddings),
                'avg_dimension' => count($embeddings) > 0 ? count($embeddings[0]) : 0,
                'duration_ms' => round($duration, 2),
            ]);

            return $embeddings;
        } catch (\Exception $e) {
            Log::error('SearchEmbedding embedQueries failed', [
                'query_count' => count($queries),
                'model' => $model,
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return array of empty arrays on error
            return array_fill(0, count($queries), []);
        }
    }

    /**
     * Validate that an embedding vector is well-formed
     *
     * Checks that:
     * - The embedding is an array
     * - The embedding is not empty
     * - All elements are numeric
     * - The dimension matches expected size
     *
     * @param  array  $embedding  The embedding vector to validate
     * @param  int  $expectedDimension  Expected dimension (default: 1536 for text-embedding-3-small)
     * @return bool True if embedding is valid
     */
    public function isValidEmbedding(array $embedding, int $expectedDimension = 1536): bool
    {
        // Check not empty
        if (empty($embedding)) {
            return false;
        }

        // Check dimension
        if (count($embedding) !== $expectedDimension) {
            return false;
        }

        // Check all elements are numeric
        foreach ($embedding as $value) {
            if (! is_numeric($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the default embedding model from configuration
     *
     * @return string The configured embedding model name
     */
    public function getDefaultModel(): string
    {
        return config('openai.models.embeddings', 'text-embedding-3-small');
    }

    /**
     * Get the expected dimension for a given embedding model
     *
     * Different OpenAI models produce embeddings of different dimensions:
     * - text-embedding-3-small: 1536 dimensions
     * - text-embedding-3-large: 3072 dimensions
     * - text-embedding-ada-002: 1536 dimensions
     *
     * @param  string  $model  The embedding model name
     * @return int The expected dimension count
     */
    public function getModelDimension(string $model): int
    {
        return match ($model) {
            'text-embedding-3-large' => 3072,
            'text-embedding-3-small', 'text-embedding-ada-002' => 1536,
            default => 1536, // Default to most common dimension
        };
    }
}

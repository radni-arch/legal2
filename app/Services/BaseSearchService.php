<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

abstract class BaseSearchService
{
    /**
     * OpenAI service for generating embeddings
     */
    protected OpenAIService $openAI;

    /**
     * Constructor
     */
    public function __construct(OpenAIService $openAI)
    {
        $this->openAI = $openAI;
    }

    /**
     * Generate embedding vector for text using OpenAI
     */
    protected function generateEmbedding(string $text): ?array
    {
        try {
            $embedding = $this->openAI->createEmbedding($text);

            return ! empty($embedding) ? $embedding : null;
        } catch (\Exception $e) {
            Log::error('Failed to generate embedding', [
                'error' => $e->getMessage(),
                'text_length' => strlen($text),
            ]);

            return null;
        }
    }

    /**
     * Calculate cosine similarity between two vectors
     *
     * @param  array  $a  First vector
     * @param  array  $b  Second vector
     * @return float Similarity score between -1 and 1
     */
    protected function cosineSimilarity(array $a, array $b): float
    {
        if (count($a) !== count($b)) {
            throw new \InvalidArgumentException(sprintf(
                'Vectors must have the same dimension (got %d and %d)',
                count($a),
                count($b)
            ));
        }

        $dotProduct = 0.0;
        for ($i = 0; $i < count($a); $i++) {
            $dotProduct += $a[$i] * $b[$i];
        }

        $normA = $this->norm($a);
        $normB = $this->norm($b);

        if ($normA == 0.0 || $normB == 0.0) {
            return 0.0;
        }

        return $dotProduct / ($normA * $normB);
    }

    /**
     * Convert vector array to PostgreSQL vector literal format
     *
     * @param  array  $vec  Vector array
     * @return string PostgreSQL vector literal (e.g., '[0.1,0.2,0.3]')
     */
    protected function toPgVectorLiteral(array $vec): string
    {
        return '['.implode(',', $vec).']';
    }

    /**
     * Convert vector array to PostgreSQL vector with cast
     *
     * @param  array  $vec  Vector array
     * @return string PostgreSQL vector with cast (e.g., '[0.1,0.2,0.3]::vector')
     */
    protected function toPgVectorCastLiteral(array $vec): string
    {
        return $this->toPgVectorLiteral($vec).'::vector';
    }

    /**
     * Calculate L2 norm (Euclidean norm) of a vector
     *
     * @param  array  $vec  Vector array
     * @return float L2 norm
     */
    protected function norm(array $vec): float
    {
        $sumOfSquares = 0.0;
        foreach ($vec as $value) {
            $sumOfSquares += $value * $value;
        }

        return sqrt($sumOfSquares);
    }

    /**
     * Check if pgvector extension is available in PostgreSQL
     */
    protected function hasPgvectorExtension(): bool
    {
        static $hasExtension = null;

        if ($hasExtension !== null) {
            return $hasExtension;
        }

        try {
            $result = DB::select("SELECT 1 FROM pg_extension WHERE extname = 'vector'");
            $hasExtension = ! empty($result);
        } catch (\Exception $e) {
            $hasExtension = false;
        }

        return $hasExtension;
    }
}

<?php

namespace Tests\TestData\Generators;

/**
 * Generates vector embeddings for testing search functionality
 */
class EmbeddingTestDataGenerator
{
    /**
     * Generate random normalized embeddings
     *
     * @param  int  $count  Number of embeddings to generate
     * @param  int  $dimensions  Dimensionality of vectors (default: 1536 for OpenAI)
     * @return array Array of embedding vectors
     */
    public function generateEmbeddings(int $count, int $dimensions = 1536): array
    {
        $embeddings = [];

        for ($i = 0; $i < $count; $i++) {
            $embedding = [];

            // Generate random vector
            for ($j = 0; $j < $dimensions; $j++) {
                $embedding[] = (mt_rand(-1000, 1000) / 1000.0);
            }

            // Normalize to unit vector
            $magnitude = sqrt(array_sum(array_map(fn ($x) => $x * $x, $embedding)));
            $embedding = array_map(fn ($x) => $x / $magnitude, $embedding);

            $embeddings[] = $embedding;
        }

        return $embeddings;
    }

    /**
     * Generate embeddings similar to a base embedding
     *
     * @param  array  $base  Base embedding vector
     * @param  int  $count  Number of similar embeddings to generate
     * @param  float  $targetSimilarity  Desired cosine similarity (0-1)
     * @return array Array of similar embedding vectors
     */
    public function generateSimilarEmbeddings(array $base, int $count, float $targetSimilarity): array
    {
        $embeddings = [];
        $dimensions = count($base);

        for ($i = 0; $i < $count; $i++) {
            // Generate random vector
            $random = $this->generateEmbeddings(1, $dimensions)[0];

            // Mix with base to achieve target similarity
            // Add some randomness to the mix ratio (±5%)
            $mixRatio = $targetSimilarity + ((mt_rand(-50, 50) / 1000.0));
            $mixRatio = max(0.0, min(1.0, $mixRatio)); // Clamp to [0, 1]

            $mixed = [];
            for ($j = 0; $j < $dimensions; $j++) {
                $mixed[] = ($base[$j] * $mixRatio) + ($random[$j] * (1 - $mixRatio));
            }

            // Normalize to unit vector
            $magnitude = sqrt(array_sum(array_map(fn ($x) => $x * $x, $mixed)));
            $mixed = array_map(fn ($x) => $x / $magnitude, $mixed);

            $embeddings[] = $mixed;
        }

        return $embeddings;
    }

    /**
     * Generate a complete search test set with query and ranked documents
     *
     * @param  int  $documentCount  Number of documents to generate (default: 10)
     * @param  int  $dimensions  Dimensionality of vectors (default: 1536)
     * @return array Test set with query, documents, and expected results
     */
    public function generateSearchTestSet(int $documentCount = 10, int $dimensions = 1536): array
    {
        // Generate base query embedding
        $query = $this->generateEmbeddings(1, $dimensions)[0];

        // Generate documents with varying similarity
        $documents = [];
        $similarities = [];

        for ($i = 0; $i < $documentCount; $i++) {
            // Generate similarity scores (more relevant documents have higher similarity)
            // Create a range from high to low similarity
            $similarity = 0.5 + (0.4 * (1 - ($i / $documentCount)));

            $embedding = $this->generateSimilarEmbeddings($query, 1, $similarity)[0];

            $documents[] = [
                'id' => $i + 1,
                'embedding' => $embedding,
            ];

            $similarities[$i + 1] = $similarity;
        }

        // Expected results are document IDs ordered by similarity (descending)
        arsort($similarities);
        $expectedResults = array_keys($similarities);

        return [
            'query' => $query,
            'documents' => $documents,
            'expected_results' => array_values($expectedResults),
        ];
    }
}

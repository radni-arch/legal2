<?php

namespace Tests\Unit\TestData\Generators;

use Tests\TestCase;
use Tests\TestData\Generators\EmbeddingTestDataGenerator;

class EmbeddingTestDataGeneratorTest extends TestCase
{
    public function test_can_generate_random_embeddings(): void
    {
        $generator = new EmbeddingTestDataGenerator;

        $embeddings = $generator->generateEmbeddings(10, 1536);

        $this->assertCount(10, $embeddings);
        $this->assertCount(1536, $embeddings[0]);
        $this->assertIsFloat($embeddings[0][0]);
    }

    public function test_embeddings_are_normalized(): void
    {
        $generator = new EmbeddingTestDataGenerator;

        $embeddings = $generator->generateEmbeddings(5, 100);

        foreach ($embeddings as $embedding) {
            $magnitude = sqrt(array_sum(array_map(fn ($x) => $x * $x, $embedding)));
            $this->assertEqualsWithDelta(1.0, $magnitude, 0.0001, 'Embedding should be normalized to unit vector');
        }
    }

    public function test_can_generate_similar_embeddings(): void
    {
        $generator = new EmbeddingTestDataGenerator;

        $base = $generator->generateEmbeddings(1, 100)[0];
        $similar = $generator->generateSimilarEmbeddings($base, 5, 0.9);

        $this->assertCount(5, $similar);

        // Verify similarity (cosine similarity ~0.9, with tolerance for randomness)
        foreach ($similar as $embedding) {
            $similarity = $this->cosineSimilarity($base, $embedding);
            $this->assertGreaterThan(0.85, $similarity, 'Similarity should be reasonably high for target 0.9');
            $this->assertLessThan(1.0, $similarity, 'Similarity should not be perfect (different vectors)');
        }
    }

    public function test_similar_embeddings_have_correct_dimensions(): void
    {
        $generator = new EmbeddingTestDataGenerator;

        $base = $generator->generateEmbeddings(1, 256)[0];
        $similar = $generator->generateSimilarEmbeddings($base, 3, 0.8);

        foreach ($similar as $embedding) {
            $this->assertCount(256, $embedding);
        }
    }

    public function test_can_generate_search_test_set(): void
    {
        $generator = new EmbeddingTestDataGenerator;

        $testSet = $generator->generateSearchTestSet();

        $this->assertArrayHasKey('query', $testSet);
        $this->assertArrayHasKey('documents', $testSet);
        $this->assertArrayHasKey('expected_results', $testSet);

        // Query should be an embedding
        $this->assertIsArray($testSet['query']);
        $this->assertCount(1536, $testSet['query']);

        // Documents should have embeddings
        $this->assertGreaterThan(0, count($testSet['documents']));
        foreach ($testSet['documents'] as $doc) {
            $this->assertArrayHasKey('id', $doc);
            $this->assertArrayHasKey('embedding', $doc);
            $this->assertCount(1536, $doc['embedding']);
        }

        // Expected results should be ordered by relevance
        $this->assertIsArray($testSet['expected_results']);
    }

    public function test_default_dimensions_is_1536(): void
    {
        $generator = new EmbeddingTestDataGenerator;

        $embeddings = $generator->generateEmbeddings(1);

        $this->assertCount(1536, $embeddings[0]);
    }

    public function test_generates_different_embeddings_each_time(): void
    {
        $generator = new EmbeddingTestDataGenerator;

        $embeddings1 = $generator->generateEmbeddings(1, 10)[0];
        $embeddings2 = $generator->generateEmbeddings(1, 10)[0];

        $this->assertNotEquals($embeddings1, $embeddings2, 'Should generate different random embeddings');
    }

    private function cosineSimilarity(array $a, array $b): float
    {
        $dotProduct = array_sum(array_map(fn ($x, $y) => $x * $y, $a, $b));
        $magnitudeA = sqrt(array_sum(array_map(fn ($x) => $x * $x, $a)));
        $magnitudeB = sqrt(array_sum(array_map(fn ($x) => $x * $x, $b)));

        return $dotProduct / ($magnitudeA * $magnitudeB);
    }
}

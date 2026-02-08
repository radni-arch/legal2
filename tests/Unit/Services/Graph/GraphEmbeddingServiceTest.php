<?php

namespace Tests\Unit\Services\Graph;

use App\Services\Graph\GraphEmbeddingService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Tests for GraphEmbeddingService (Sprint 4.5)
 *
 * Tests verify graph embedding functionality:
 * - Node2Vec training via Python script
 * - Embedding storage and retrieval
 * - Similarity search using graph structure
 * - Statistics and coverage metrics
 *
 * Acceptance Criteria:
 * ✅ Can check if embeddings exist
 * ✅ Can retrieve embeddings for decisions
 * ✅ Can find similar decisions by graph structure
 * ✅ Statistics show accurate coverage
 */
class GraphEmbeddingServiceTest extends TestCase
{
    use UsesTestDatabase;

    protected GraphEmbeddingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GraphEmbeddingService;
    }

    // ========================================
    // Test 1: Check Embedding Existence
    // ========================================

    /** @test */
    public function it_checks_if_embedding_exists()
    {
        // Arrange: Create decision with embedding
        $decisionId = $this->createDecision(['case_number' => 'K-123/2020']);
        $this->createEmbedding($decisionId);

        // Act & Assert: Embedding exists
        $this->assertTrue($this->service->hasEmbedding($decisionId));
    }

    /** @test */
    public function it_returns_false_when_embedding_does_not_exist()
    {
        $decisionId = $this->createDecision(['case_number' => 'K-456/2020']);

        // Act & Assert: No embedding
        $this->assertFalse($this->service->hasEmbedding($decisionId));
    }

    // ========================================
    // Test 2: Retrieve Embeddings
    // ========================================

    /** @test */
    public function it_retrieves_embedding_for_decision()
    {
        // Arrange
        $decisionId = $this->createDecision(['case_number' => 'K-789/2020']);
        $trainedAt = '2025-11-11 10:00:00';
        $this->createEmbedding($decisionId, [
            'trained_at' => $trainedAt,
            'model_version' => 'node2vec-v1.0',
        ]);

        // Act
        $embedding = $this->service->getEmbedding($decisionId);

        // Assert
        $this->assertNotNull($embedding);
        $this->assertEquals($decisionId, $embedding['decision_id']);
        $this->assertNotNull($embedding['graph_embedding']);
        $this->assertEquals($trainedAt, $embedding['trained_at']);
        $this->assertEquals('node2vec-v1.0', $embedding['model_version']);
    }

    /** @test */
    public function it_returns_null_when_embedding_not_found()
    {
        $decisionId = $this->createDecision(['case_number' => 'K-999/2020']);

        // Act
        $embedding = $this->service->getEmbedding($decisionId);

        // Assert
        $this->assertNull($embedding);
    }

    // ========================================
    // Test 3: Statistics
    // ========================================

    /** @test */
    public function it_calculates_correct_statistics()
    {
        // Arrange: Create 5 decisions, 3 with embeddings
        $decision1 = $this->createDecision(['case_number' => 'K-1/2020']);
        $decision2 = $this->createDecision(['case_number' => 'K-2/2020']);
        $decision3 = $this->createDecision(['case_number' => 'K-3/2020']);
        $this->createDecision(['case_number' => 'K-4/2020']); // No embedding
        $this->createDecision(['case_number' => 'K-5/2020']); // No embedding

        $this->createEmbedding($decision1);
        $this->createEmbedding($decision2);
        $this->createEmbedding($decision3, ['model_version' => 'node2vec-v1.0']);

        // Act
        $stats = $this->service->getStatistics();

        // Assert
        $this->assertEquals(3, $stats['total_embeddings']);
        $this->assertEquals(5, $stats['total_decisions']);
        $this->assertEquals(60.0, $stats['coverage_percentage']); // 3/5 * 100
        $this->assertEquals('node2vec-v1.0', $stats['model_version']);
        $this->assertNotNull($stats['last_trained_at']);
    }

    /** @test */
    public function it_handles_empty_database_in_statistics()
    {
        // Act
        $stats = $this->service->getStatistics();

        // Assert
        $this->assertEquals(0, $stats['total_embeddings']);
        $this->assertEquals(0, $stats['coverage_percentage']);
        $this->assertNull($stats['model_version']);
        $this->assertNull($stats['last_trained_at']);
    }

    // ========================================
    // Test 4: Find Similar by Graph
    // ========================================

    /** @test */
    public function it_finds_similar_decisions_by_graph_structure()
    {
        // Arrange: Create decisions with similar embeddings
        $sourceId = $this->createDecision(['case_number' => 'K-SOURCE/2020']);
        $similar1Id = $this->createDecision(['case_number' => 'K-SIM1/2020']);
        $similar2Id = $this->createDecision(['case_number' => 'K-SIM2/2020']);
        $dissimilarId = $this->createDecision(['case_number' => 'K-DIFF/2020']);

        // Create embeddings (simulating structural similarity)
        $this->createEmbedding($sourceId, ['vector_values' => array_fill(0, 128, 0.5)]);
        $this->createEmbedding($similar1Id, ['vector_values' => array_fill(0, 128, 0.51)]); // Very similar
        $this->createEmbedding($similar2Id, ['vector_values' => array_fill(0, 128, 0.49)]); // Very similar
        $this->createEmbedding($dissimilarId, ['vector_values' => array_fill(0, 128, -0.5)]); // Different

        // Act
        $results = $this->service->findSimilarByGraph($sourceId, $limit = 3);

        // Assert
        $this->assertNotEmpty($results);
        $this->assertLessThanOrEqual(3, count($results));

        // Results should be ordered by similarity
        if (count($results) > 1) {
            for ($i = 0; $i < count($results) - 1; $i++) {
                $this->assertGreaterThanOrEqual(
                    $results[$i + 1]['similarity'],
                    $results[$i]['similarity'],
                    'Results should be ordered by descending similarity'
                );
            }
        }
    }

    /** @test */
    public function it_returns_empty_array_when_source_has_no_embedding()
    {
        $decisionId = $this->createDecision(['case_number' => 'K-NO-EMB/2020']);

        // Act
        $results = $this->service->findSimilarByGraph($decisionId);

        // Assert
        $this->assertEmpty($results);
    }

    // ========================================
    // Test 5: Clear Embeddings
    // ========================================

    /** @test */
    public function it_clears_all_embeddings()
    {
        // Arrange: Create embeddings
        $decision1 = $this->createDecision(['case_number' => 'K-1/2020']);
        $decision2 = $this->createDecision(['case_number' => 'K-2/2020']);

        $this->createEmbedding($decision1);
        $this->createEmbedding($decision2);

        // Act
        $count = $this->service->clearEmbeddings();

        // Assert
        $this->assertEquals(2, $count);
        $this->assertFalse($this->service->hasEmbedding($decision1));
        $this->assertFalse($this->service->hasEmbedding($decision2));
    }

    // ========================================
    // Test 6: Generate Embeddings (Python Script)
    // ========================================

    /** @test */
    public function it_handles_missing_python_script_gracefully()
    {
        // Note: This test verifies error handling when Python script is missing
        // In production, the script should exist

        // Temporarily rename script if it exists
        $scriptPath = base_path('scripts/train_graph_embeddings.py');
        $backupPath = base_path('scripts/train_graph_embeddings.py.backup');

        $scriptExists = file_exists($scriptPath);
        if ($scriptExists) {
            rename($scriptPath, $backupPath);
        }

        try {
            // Act
            $result = $this->service->generateEmbeddings();

            // Assert
            $this->assertFalse($result['success']);
            $this->assertStringContainsString('not found', $result['error']);
        } finally {
            // Restore script
            if ($scriptExists && file_exists($backupPath)) {
                rename($backupPath, $scriptPath);
            }
        }
    }

    // ========================================
    // Helper Methods
    // ========================================

    protected function createDecision(array $overrides = []): string
    {
        $defaults = [
            'id' => (string) \Illuminate\Support\Str::ulid(),
            'case_number' => 'K-'.rand(100, 999).'/2020',
            'court' => 'Županijski sud u Osijeku',
            'decision_date' => '2020-01-01',
            'summary' => 'Test decision summary',
            'title' => 'Test Decision',
        ];

        $decisionData = array_merge($defaults, $overrides);
        $decisionId = $decisionData['id'];
        unset($decisionData['id']);

        DB::table('court_decisions')->insert(array_merge(['id' => $decisionId], $decisionData));

        return $decisionId;
    }

    protected function createEmbedding(string $decisionId, array $overrides = []): void
    {
        // Generate random 128-dim vector or use provided values
        $vectorValues = $overrides['vector_values'] ?? array_map(
            fn () => (rand(-1000, 1000) / 1000), // Random values between -1 and 1
            range(1, 128)
        );

        $vectorString = '['.implode(',', $vectorValues).']';

        $defaults = [
            'decision_id' => $decisionId,
            'graph_embedding' => $vectorString, // Store as text (pgvector not available in test environment)
            'trained_at' => now(),
            'model_version' => 'node2vec-test-v1.0',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $embeddingData = array_merge($defaults, $overrides);
        unset($embeddingData['vector_values']); // Remove helper key

        DB::table('decision_graph_embeddings')->insert($embeddingData);
    }
}

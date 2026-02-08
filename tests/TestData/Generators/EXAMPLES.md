# Bulk Generators - Usage Examples

Practical examples for using the test data generators in your test suites.

## Example 1: Basic Bulk Data Generation

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use Tests\UsesTestDatabase;
use Tests\TestData\Generators\BulkTestDataGenerator;

class CaseSearchTest extends TestCase
{
    use UsesTestDatabase;

    public function test_can_search_across_large_dataset(): void
    {
        $generator = new BulkTestDataGenerator();

        // Generate 1000 test cases
        $cases = $generator->generateCases(1000);

        // Perform search
        $results = $this->searchCases('fraud');

        $this->assertNotEmpty($results);
        $this->assertLessThan(100, $results->count());

        // Cleanup automatically done by UsesTestDatabase trait
        // OR manually: $generator->cleanup();
    }
}
```

## Example 2: Type-Specific Case Generation

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use Tests\UsesTestDatabase;
use Tests\TestData\Generators\BulkTestDataGenerator;

class CriminalCaseTest extends TestCase
{
    use UsesTestDatabase;

    public function test_criminal_case_filtering(): void
    {
        $generator = new BulkTestDataGenerator();

        // Generate 100 criminal cases
        $criminalCases = $generator->generateCases(100, 'criminal');

        // Generate 100 civil cases
        $civilCases = $generator->generateCases(100, 'civil');

        // Test filtering
        $filtered = LegalCase::whereJsonContains('tags', 'criminal')->get();

        $this->assertGreaterThanOrEqual(100, $filtered->count());

        // Cleanup
        $generator->cleanup();
    }
}
```

## Example 3: Document Generation

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use Tests\UsesTestDatabase;
use Tests\TestData\Generators\BulkTestDataGenerator;

class DocumentProcessingTest extends TestCase
{
    use UsesTestDatabase;

    public function test_bulk_document_processing(): void
    {
        $generator = new BulkTestDataGenerator();

        // Generate a case with many documents
        $case = $generator->generateCases(1)->first();
        $documents = $generator->generateDocuments(500, $case->id);

        // Process all documents
        $processed = $this->processDocumentBatch($documents);

        $this->assertCount(500, $processed);

        // Cleanup
        $generator->cleanup();
    }
}
```

## Example 4: Mixed Batch Generation

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use Tests\UsesTestDatabase;
use Tests\TestData\Generators\BulkTestDataGenerator;

class SystemIntegrationTest extends TestCase
{
    use UsesTestDatabase;

    public function test_full_system_with_realistic_data(): void
    {
        $generator = new BulkTestDataGenerator();

        // Generate realistic dataset in one call
        $data = $generator->generateBatch([
            'cases' => 200,
            'documents' => 1000,
            'case_type' => 'commercial',
        ]);

        $this->assertCount(200, $data['cases']);
        $this->assertCount(1000, $data['documents']);

        // Test full system
        $results = $this->runFullSystemAnalysis($data);

        $this->assertTrue($results->successful);

        // Cleanup
        $generator->cleanup();
    }
}
```

## Example 5: Progress Tracking for Large Batches

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use Tests\UsesTestDatabase;
use Tests\TestData\Generators\BulkTestDataGenerator;

class PerformanceTest extends TestCase
{
    use UsesTestDatabase;

    public function test_performance_with_10k_cases(): void
    {
        $generator = new BulkTestDataGenerator();

        // Set up progress tracking
        $progress = 0;
        $generator->setProgressCallback(function($current, $total) use (&$progress) {
            $progress = ($current / $total) * 100;
            // Could output to console: echo "Progress: {$progress}%\n";
        });

        // Generate large dataset
        $cases = $generator->generateCases(10000);

        $this->assertEquals(100, $progress);
        $this->assertCount(10000, $cases);

        // Measure query performance
        $startTime = microtime(true);
        $results = LegalCase::where('status', 'active')->get();
        $duration = microtime(true) - $startTime;

        $this->assertLessThan(1.0, $duration, 'Query should complete in <1 second');

        // Cleanup
        $generator->cleanup();
    }
}
```

## Example 6: Vector Search Testing

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use Tests\TestData\Generators\EmbeddingTestDataGenerator;

class SemanticSearchTest extends TestCase
{
    public function test_embedding_similarity_search(): void
    {
        $generator = new EmbeddingTestDataGenerator();

        // Generate base query
        $queryEmbedding = $generator->generateEmbeddings(1, 1536)[0];

        // Generate similar documents (high relevance)
        $relevantDocs = $generator->generateSimilarEmbeddings(
            $queryEmbedding,
            count: 10,
            targetSimilarity: 0.9
        );

        // Generate dissimilar documents (low relevance)
        $irrelevantDocs = $generator->generateSimilarEmbeddings(
            $queryEmbedding,
            count: 10,
            targetSimilarity: 0.3
        );

        // Test search algorithm
        $allDocs = array_merge($relevantDocs, $irrelevantDocs);
        shuffle($allDocs);

        $results = $this->searchBySimilarity($queryEmbedding, $allDocs, limit: 10);

        // All top results should be from relevant set
        foreach ($results as $result) {
            $similarity = $this->cosineSimilarity($queryEmbedding, $result);
            $this->assertGreaterThan(0.8, $similarity);
        }
    }

    private function cosineSimilarity(array $a, array $b): float
    {
        $dotProduct = array_sum(array_map(fn($x, $y) => $x * $y, $a, $b));
        $magnitudeA = sqrt(array_sum(array_map(fn($x) => $x * $x, $a)));
        $magnitudeB = sqrt(array_sum(array_map(fn($x) => $x * $x, $b)));
        return $dotProduct / ($magnitudeA * $magnitudeB);
    }
}
```

## Example 7: Complete Search Test Set

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use Tests\TestData\Generators\EmbeddingTestDataGenerator;

class SearchAccuracyTest extends TestCase
{
    public function test_search_returns_correct_ranking(): void
    {
        $generator = new EmbeddingTestDataGenerator();

        // Generate complete test set with pre-ranked results
        $testSet = $generator->generateSearchTestSet(
            documentCount: 100,
            dimensions: 1536
        );

        $query = $testSet['query'];
        $documents = $testSet['documents'];
        $expectedOrder = $testSet['expected_results'];

        // Run search algorithm
        $actualResults = $this->performVectorSearch($query, $documents, limit: 10);

        // Check if top results match expected ranking
        $actualIds = array_map(fn($doc) => $doc['id'], $actualResults);
        $expectedTop10 = array_slice($expectedOrder, 0, 10);

        // At least 8 out of 10 should match (allowing some variance)
        $matches = count(array_intersect($actualIds, $expectedTop10));
        $this->assertGreaterThanOrEqual(8, $matches,
            'Search should return 8+ correct results in top 10');
    }
}
```

## Example 8: Combining Both Generators

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use Tests\UsesTestDatabase;
use Tests\TestData\Generators\BulkTestDataGenerator;
use Tests\TestData\Generators\EmbeddingTestDataGenerator;

class E2ESearchTest extends TestCase
{
    use UsesTestDatabase;

    public function test_end_to_end_semantic_search(): void
    {
        $bulkGen = new BulkTestDataGenerator();
        $embedGen = new EmbeddingTestDataGenerator();

        // Generate cases
        $cases = $bulkGen->generateCases(1000);

        // Generate embeddings for each case
        $embeddings = $embedGen->generateEmbeddings(1000, 1536);

        // Store embeddings (pseudo-code)
        foreach ($cases as $index => $case) {
            $this->storeEmbedding($case->id, $embeddings[$index]);
        }

        // Generate search query
        $queryEmbedding = $embedGen->generateEmbeddings(1, 1536)[0];

        // Perform search
        $results = $this->semanticSearch($queryEmbedding, limit: 10);

        $this->assertCount(10, $results);
        $this->assertInstanceOf(LegalCase::class, $results->first());

        // Cleanup
        $bulkGen->cleanup();
    }
}
```

## Example 9: Custom Dimensions for Different Models

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use Tests\TestData\Generators\EmbeddingTestDataGenerator;

class MultiModelEmbeddingTest extends TestCase
{
    public function test_different_embedding_models(): void
    {
        $generator = new EmbeddingTestDataGenerator();

        // OpenAI (1536 dimensions)
        $openaiEmbeddings = $generator->generateEmbeddings(10, 1536);
        $this->assertCount(1536, $openaiEmbeddings[0]);

        // BERT (768 dimensions)
        $bertEmbeddings = $generator->generateEmbeddings(10, 768);
        $this->assertCount(768, $bertEmbeddings[0]);

        // Sentence Transformers (384 dimensions)
        $stEmbeddings = $generator->generateEmbeddings(10, 384);
        $this->assertCount(384, $stEmbeddings[0]);

        // Verify all are normalized
        foreach ($openaiEmbeddings as $embedding) {
            $magnitude = sqrt(array_sum(array_map(fn($x) => $x * $x, $embedding)));
            $this->assertEqualsWithDelta(1.0, $magnitude, 0.0001);
        }
    }
}
```

## Example 10: Cleanup Patterns

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use Tests\UsesTestDatabase;
use Tests\TestData\Generators\BulkTestDataGenerator;

class CleanupPatternsTest extends TestCase
{
    use UsesTestDatabase;

    public function test_automatic_cleanup_with_trait(): void
    {
        // Using UsesTestDatabase trait - auto rollback
        $generator = new BulkTestDataGenerator();
        $cases = $generator->generateCases(100);

        $this->assertCount(100, $cases);

        // No manual cleanup needed - trait rolls back transaction
    }

    public function test_manual_cleanup(): void
    {
        // Without trait - manual cleanup required
        $generator = new BulkTestDataGenerator();
        $cases = $generator->generateCases(100);

        $this->assertCount(100, $cases);

        // Manual cleanup
        $generator->cleanup();

        // Verify cleanup worked
        foreach ($cases as $case) {
            $this->assertNull(LegalCase::find($case->id));
        }
    }

    public function test_incremental_cleanup(): void
    {
        $generator = new BulkTestDataGenerator();

        // Generate first batch
        $batch1 = $generator->generateCases(50);

        // Generate second batch
        $batch2 = $generator->generateCases(50);

        // Both batches tracked
        $this->assertEquals(100, $generator->getTrackedCount('cases'));

        // Cleanup removes ALL tracked data
        $generator->cleanup();

        // Verify all removed
        $this->assertEquals(0, $generator->getTrackedCount('cases'));
    }
}
```

## Best Practices

1. **Always Use `UsesTestDatabase` Trait**
   - Automatic transaction rollback
   - No manual cleanup needed
   - Faster test execution

2. **Set Progress Callbacks for Large Batches**
   - Monitor long-running operations
   - Debug performance issues
   - User feedback for CI/CD

3. **Cleanup Explicitly When Not Using Trait**
   - Call `$generator->cleanup()` in tearDown()
   - Prevents test pollution
   - Maintains database integrity

4. **Generate Realistic Datasets**
   - Use appropriate sizes (100-10,000)
   - Mix types (criminal, civil, commercial)
   - Include edge cases

5. **Verify Embeddings Are Normalized**
   - All vectors should have magnitude = 1
   - Required for accurate cosine similarity
   - Built-in to generator

6. **Use Similarity Targets Wisely**
   - High similarity (0.85-0.95): Very relevant results
   - Medium similarity (0.5-0.7): Related results
   - Low similarity (0.2-0.4): Unrelated results

7. **Batch Operations for Performance**
   - Generators use `factory()->count(N)` internally
   - Much faster than loops
   - Database-optimized

8. **Test with Multiple Dimensions**
   - Different models use different dimensions
   - Verify compatibility
   - Test performance across sizes

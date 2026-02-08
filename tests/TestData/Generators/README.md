# Bulk Test Data Generators

This directory contains efficient bulk data generators for testing the Croatian legal AI defense system.

## Generators

### 1. BulkTestDataGenerator

Generates large datasets efficiently for performance testing using Laravel factories.

**Features:**
- Batch creation using `factory()->count(N)->create()` (not loops)
- Progress tracking with optional callbacks
- Cleanup helpers to delete generated data
- Supports multiple entity types

**Usage:**

```php
use Tests\TestData\Generators\BulkTestDataGenerator;

$generator = new BulkTestDataGenerator();

// Generate 1000 legal cases
$cases = $generator->generateCases(1000);

// Generate cases with specific type (criminal/civil)
$criminalCases = $generator->generateCases(100, 'criminal');

// Generate documents
$documents = $generator->generateDocuments(500);

// Generate documents for specific case
$caseDocuments = $generator->generateDocuments(20, $caseId);

// Generate mixed batch
$result = $generator->generateBatch([
    'cases' => 50,
    'documents' => 200,
    'case_type' => 'civil',
]);

// With progress tracking (for large batches)
$generator->setProgressCallback(function($current, $total) {
    echo "Progress: {$current}/{$total}\n";
});
$cases = $generator->generateCases(10000);

// Cleanup all generated data
$generator->cleanup();
```

**Performance Notes:**
- Uses batch operations (`count(N)->create()`) for optimal performance
- Generates 1000+ records in seconds
- Automatically tracks generated IDs for cleanup
- Database transactions roll back automatically in tests using `UsesTestDatabase` trait

### 2. EmbeddingTestDataGenerator

Generates vector embeddings for testing semantic search functionality.

**Features:**
- Random normalized vector generation (default 1536 dimensions for OpenAI)
- Similarity-controlled embedding generation (cosine similarity)
- Complete search test sets with queries and expected results
- Configurable dimensions

**Usage:**

```php
use Tests\TestData\Generators\EmbeddingTestDataGenerator;

$generator = new EmbeddingTestDataGenerator();

// Generate random embeddings (default 1536 dimensions)
$embeddings = $generator->generateEmbeddings(100);

// Generate with custom dimensions
$embeddings = $generator->generateEmbeddings(50, 768); // BERT-sized

// Generate similar embeddings
$baseEmbedding = $generator->generateEmbeddings(1)[0];
$similarEmbeddings = $generator->generateSimilarEmbeddings(
    $baseEmbedding,
    count: 10,
    targetSimilarity: 0.9  // Cosine similarity ~0.9
);

// Generate complete search test set
$testSet = $generator->generateSearchTestSet(
    documentCount: 100,
    dimensions: 1536
);

// Use test set
$query = $testSet['query'];  // Query embedding
$documents = $testSet['documents'];  // Array of [id, embedding]
$expectedResults = $testSet['expected_results'];  // Expected order by relevance
```

**Search Test Set Structure:**

```php
[
    'query' => [0.123, -0.456, ...],  // Query embedding
    'documents' => [
        ['id' => 1, 'embedding' => [0.234, -0.567, ...]],
        ['id' => 2, 'embedding' => [0.345, -0.678, ...]],
        // ... more documents
    ],
    'expected_results' => [1, 3, 5, 2, 4, ...],  // IDs ordered by relevance
]
```

## Performance Testing Example

```php
use Tests\TestData\Generators\BulkTestDataGenerator;
use Tests\TestData\Generators\EmbeddingTestDataGenerator;

class PerformanceTest extends TestCase
{
    use UsesTestDatabase;

    public function test_search_performance_with_10k_documents(): void
    {
        $bulkGen = new BulkTestDataGenerator();
        $embedGen = new EmbeddingTestDataGenerator();

        // Generate 10,000 cases
        $cases = $bulkGen->generateCases(10000);

        // Generate embeddings for each case
        $embeddings = $embedGen->generateEmbeddings(10000, 1536);

        // Attach embeddings to cases (pseudo-code)
        foreach ($cases as $index => $case) {
            // Store embedding in your vector database
            $this->storeEmbedding($case->id, $embeddings[$index]);
        }

        // Generate test query
        $queryEmbedding = $embedGen->generateEmbeddings(1, 1536)[0];

        // Measure search performance
        $startTime = microtime(true);
        $results = $this->searchSimilarCases($queryEmbedding, limit: 10);
        $duration = microtime(true) - $startTime;

        // Assert performance
        $this->assertLessThan(0.1, $duration, 'Search should complete in <100ms');
        $this->assertCount(10, $results);

        // Cleanup
        $bulkGen->cleanup();
    }
}
```

## Testing Notes

### Database Requirements

The **BulkTestDataGenerator** requires a database connection:
- Tests use the `UsesTestDatabase` trait for automatic transaction rollback
- Database must be running before executing tests
- Connection configured in `.env.testing` or `.env`

### Running Tests

```bash
# Run all generator tests
php artisan test --filter=Generator

# Run specific generator tests
php artisan test --filter=BulkTestDataGeneratorTest
php artisan test --filter=EmbeddingTestDataGeneratorTest

# Run with coverage
php artisan test --filter=Generator --coverage
```

### TDD Compliance

Both generators were developed using strict Test-Driven Development:

1. **RED Phase**: Tests written first, confirmed to fail
2. **GREEN Phase**: Minimal implementation to pass tests
3. **REFACTOR Phase**: Code improved while keeping tests green

Test suite includes:
- Unit tests for all public methods
- Edge case handling
- Performance optimization verification
- Integration tests with Laravel factories

## Files

```
tests/TestData/Generators/
├── BulkTestDataGenerator.php          # Bulk data generator
├── EmbeddingTestDataGenerator.php     # Embedding generator
└── README.md                          # This file

tests/Unit/TestData/Generators/
├── BulkTestDataGeneratorTest.php      # 8 tests, 100% coverage
└── EmbeddingTestDataGeneratorTest.php # 7 tests, 100% coverage
```

## Implementation Details

### BulkTestDataGenerator

- **Batch Operations**: Uses `factory()->count(N)->create()` for efficiency
- **ID Tracking**: Automatically tracks all generated IDs for cleanup
- **Cascading Cleanup**: Deletes children before parents (documents → cases)
- **Progress Callbacks**: Optional callbacks for monitoring large batch operations
- **Factory Integration**: Leverages existing 31 Laravel factories

### EmbeddingTestDataGenerator

- **Normalization**: All vectors are normalized to unit vectors (magnitude = 1)
- **Similarity Control**: Mix ratio algorithm for controlling cosine similarity
- **Randomness**: Uses `mt_rand()` for consistent random vector generation
- **Dimensions**: Default 1536 (OpenAI), configurable for other models
- **Test Sets**: Pre-ranked documents for search accuracy testing

## Future Enhancements

Potential improvements:
- Add `generateUsers()` method to BulkTestDataGenerator
- Support for batch progress bars (using Symfony Console)
- Parallel batch generation for massive datasets
- Embedding similarity clusters (group similar documents)
- Pre-built embedding datasets for common scenarios

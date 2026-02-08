# UnifiedSearchService Characterization Tests

## Overview

This directory contains characterization tests for the `UnifiedSearchService` as part of Phase 2 of the refactoring project.

**Purpose**: These tests capture the current behavior of `UnifiedSearchService` before refactoring. They serve as a safety net to ensure the refactored code maintains the same behavior.

## Test File

- `UnifiedSearchServiceCharacterizationTest.php` - 30 comprehensive tests covering all aspects of the UnifiedSearchService

## Test Coverage

The characterization tests cover:

### Core Search Functionality (Tests 1-7)
1. **Search across all corpora** - Verifies searching across laws, decisions, and cases
2. **Corpus filtering** - Tests filtering by single corpus (laws only, decisions only, etc.)
3. **Threshold filtering** - Validates minimum similarity score filtering
4. **Pagination** - Tests page-based result pagination
5. **Score ranking** - Ensures results are ranked by similarity score
6. **Deduplication** - Verifies duplicate content removal based on content_hash
7. **Deduplication toggle** - Tests ability to disable deduplication

### Advanced Features (Tests 8-12)
8. **Corpus weights** - Tests weighted scoring for different corpora
9. **Date filters** - Validates date range filtering
10. **Jurisdiction filters** - Tests filtering by jurisdiction
11. **Date sorting** - Verifies sorting by date (ascending/descending)
12. **Empty query handling** - Tests error handling for empty queries

### Error Handling (Tests 13-14)
13. **Invalid corpus** - Tests rejection of invalid corpus names
14. **Empty corpus array** - Tests rejection of empty corpus list

### Metadata & Performance (Tests 15-18)
15. **Performance metrics** - Validates timing metrics are included
16. **Slow query logging** - Tests logging of queries exceeding 2 seconds
17. **Result normalization** - Verifies consistent result structure
18. **Snippet extraction** - Tests truncation of long content to snippets

### Hybrid & Citation Search (Tests 19-20)
19. **Hybrid search** - Tests combined vector + full-text + citation search
20. **Search with citations** - Validates citation extraction and analysis

### Query Options (Tests 21-25)
21. **Limit parameter** - Tests result limiting per corpus
22. **Offset parameter** - Validates manual offset for pagination
23. **Default threshold** - Verifies default threshold of 0.7
24. **Threshold clamping** - Tests threshold is clamped between 0 and 1
25. **Multi-corpus merging** - Validates merging results from multiple corpora

### Filter Specifics (Tests 26-27)
26. **Court filter** - Tests LIKE filtering on court name for decisions
27. **Law metadata** - Validates all required metadata fields are present

### Edge Cases (Tests 28-30)
28. **Total pages calculation** - Tests pagination metadata calculation
29. **Database error handling** - Verifies graceful handling of DB errors
30. **Non-PostgreSQL driver** - Tests behavior with unsupported DB driver

## Running the Tests

### Prerequisites

1. **PostgreSQL Database**: Tests require a PostgreSQL test database
   ```bash
   # Setup test database (copies from production)
   composer test:setup
   ```

2. **Dependencies**: Ensure all Composer dependencies are installed
   ```bash
   composer install
   ```

3. **Environment**: Tests use `.env.testing` configuration

### Running All Characterization Tests

```bash
# Using composer script (recommended)
composer test -- --filter=UnifiedSearchServiceCharacterizationTest

# Or directly with phpunit
./vendor/bin/phpunit --filter=UnifiedSearchServiceCharacterizationTest

# Or using the test script
./scripts/run-tests.sh --filter=UnifiedSearchServiceCharacterizationTest
```

### Running Individual Tests

```bash
# Run specific test method
./vendor/bin/phpunit --filter=it_searches_across_all_corpora
```

## Test Structure

Each test follows the **Arrange-Act-Assert** pattern:

```php
public function it_searches_across_all_corpora()
{
    // Arrange: Create test data
    Law::factory()->create([...]);

    // Act: Execute the behavior
    $results = $this->searchService->search('query', [...]);

    // Assert: Verify expectations
    $this->assertEquals(...);
}
```

## Mocking Strategy

The tests mock external dependencies to avoid API calls:

- **OpenAI Service**: Mocked to return fixed embedding vectors
- **Citation Detector**: Mocked to return empty citations
- **Database**: Uses real PostgreSQL with transactions (auto-rollback)

## Important Notes

1. **Database Transactions**: Tests use the `UsesTestDatabase` trait which wraps each test in a transaction that auto-rolls back. The test database remains pristine.

2. **No Migrations**: Tests assume the test database is already set up with the production schema (via `composer test:setup`).

3. **Embedding Vectors**: Tests use simple 1536-dimension vectors filled with constant values (e.g., `array_fill(0, 1536, 0.5)`).

4. **Similarity Scores**: Because we use constant embedding vectors, similarity scores are deterministic and predictable.

## Expected Output

All tests should pass:

```
PASS  Tests\Unit\Services\UnifiedSearchServiceCharacterizationTest
✓ it searches across all corpora
✓ it filters by corpus
✓ it applies threshold filtering
... (30 tests total)

Tests:    30 passed (124 assertions)
Duration: ~5s
```

## Troubleshooting

### "Database does not exist"
Run `composer test:setup` to create the test database.

### "could not find driver"
Ensure PostgreSQL PDO extension is installed:
```bash
php -m | grep pdo_pgsql
```

### "NEO4J_PASSWORD must be set"
Neo4j should be disabled in `.env.testing`:
```
NEO4J_ENABLED=false
```

### Slow test execution
Tests may be slow if:
- Database is remote (should be localhost)
- Too many records in test database (refresh with `composer test:setup --force`)
- No database indexes (migrations should create them)

## Next Steps

After these characterization tests pass:

1. **Refactor Phase 2.2**: Extract services as planned
   - SearchEmbeddingService
   - LawSearchService
   - DecisionSearchService
   - CaseSearchService
   - SearchResultAggregator
   - SearchResultDeduplicator

2. **Run tests after each refactor**: Ensure all 30 tests continue to pass

3. **Add integration tests**: Test the refactored services working together

## Related Documentation

- [CLAUDE.md](../../../CLAUDE.md) - Project overview and testing guide
- [TESTING.md](../../../TESTING.md) - Comprehensive testing documentation
- Phase 2 Refactoring Plan - See project documentation

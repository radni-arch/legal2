# Testing Documentation - AI Legal War Machine

**Central hub for all testing documentation, strategies, and test summaries**

---

## Quick Navigation

| Resource | Description | Path |
|----------|-------------|------|
| **Main Testing Guide** | Comprehensive testing approach and commands | [TESTING.md](../TESTING.md) |
| **Testing Quick Guide** | Quick reference for common testing tasks | [TESTING_GUIDE.md](../TESTING_GUIDE.md) |
| **Test Summaries** | Organized test summaries by component | [summaries/](summaries/) |
| **Coverage Analysis** | Test coverage review and improvement plan | [TEST_COVERAGE_ANALYSIS_AND_PLAN.md](../TEST_COVERAGE_ANALYSIS_AND_PLAN.md) |
| **Coverage Review** | Detailed coverage review | [TEST_COVERAGE_REVIEW.md](../TEST_COVERAGE_REVIEW.md) |

---

## Testing Approach

The AI Legal War Machine uses a comprehensive testing strategy combining:

### Database Strategy
- **Production copy approach** - Test database is a copy of production
- **DatabaseTransactions trait** - Automatic rollback after each test
- **No RefreshDatabase** - Persistent test database for speed
- **One-time setup** - `composer test:setup` creates test database

### Test Types
1. **Unit Tests** (`tests/Unit/`) - Individual component testing
2. **Feature Tests** (`tests/Feature/`) - Integration and API testing
3. **Browser Tests** (`tests/Browser/`) - Playwright browser automation

### Test Organization
Tests are organized by architectural component:
- **Vector Stores** - Embedding and similarity search
- **Commands** - CLI and Artisan commands
- **Modules** - Domain modules (Evidence, Misconduct, Topics)
- **Pipelines** - Data processing pipelines (Textract, Odluke)
- **Services** - Core services (RAG, GraphDB, OpenAI)

---

## Quick Commands

### Setup
```bash
# One-time setup: Create test database
composer test:setup

# Or manually
./scripts/setup-test-db.sh

# Force recreate test database
./scripts/setup-test-db.sh --force
```

### Running Tests
```bash
# Quick test (SQLite in-memory)
composer test

# Full integrated tests (PostgreSQL test DB)
composer test:integrated

# Setup + run tests
composer test:all

# Specific test suites
composer test:unit         # Unit tests only
composer test:feature      # Feature tests only

# With coverage (min 80%)
composer test:coverage

# Parallel execution
composer test:parallel     # Run in parallel
composer test:quick        # Parallel + stop on first failure

# Run specific test
./scripts/run-tests.sh --filter=TestClassName
```

### Common Patterns
```bash
# Test specific component category
./scripts/run-tests.sh --filter=VectorStore
./scripts/run-tests.sh --filter=CommandTest
./scripts/run-tests.sh --filter=ModuleTest
./scripts/run-tests.sh --filter=PipelineTest
./scripts/run-tests.sh --filter=ServiceTest

# Test specific service
./scripts/run-tests.sh --filter=RagOrchestratorTest
./scripts/run-tests.sh --filter=GraphDatabaseServiceTest
./scripts/run-tests.sh --filter=OpenAIServiceTest

# Test specific module
./scripts/run-tests.sh --filter=EvidenceModuleTest
./scripts/run-tests.sh --filter=MisconductModuleTest
./scripts/run-tests.sh --filter=DrugChargeAbuseDetectorTest
```

---

## Test Summaries by Category

Detailed test documentation organized by component architecture:

### [Vector Stores](summaries/vector-stores/)
Test summaries for vector similarity search and embedding services:
- Law Vector Store (ZKP, KZ, Ustav RH)
- Court Decision Vector Store (odluke.sudovi.hr)
- Case Vector Store (internal documents)
- Textract Vector Store (OCR'd PDFs)

### [Commands](summaries/commands/)
Test summaries for CLI and Artisan commands:
- EKOM sync commands (e-courts integration)
- Eoglasna monitoring (public notices)
- Graph database commands (Neo4j)
- Metadata extraction commands

### [Modules](summaries/modules/)
Test summaries for domain-specific legal defense modules:
- Home Search Abuse Detector
- Proportionality Analyzer
- Statistical Analyzer
- Odluke Search Agent
- Evidence Module
- Misconduct Module

### [Pipelines](summaries/pipelines/)
Test summaries for data processing pipelines:
- Textract OCR Pipeline (8 test summaries)
  - Full integration tests
  - Individual step tests
- Odluke Ingestion Pipeline
- Law Ingestion Pipeline
- Case Document Pipeline

### [Services](summaries/services/)
Test summaries for core application services:
- RAG Services (RagOrchestrator, LawSearchService, CaseSearchService)
- Graph Services (GraphDatabaseService)
- Integration Services (OpenAIService, OdlukeClient, EkomService)
- Monitoring Services (ApplicationMonitor)
- Fact Extraction Services

---

## Testing Best Practices

### Always Use UsesTestDatabase Trait

```php
use Tests\Concerns\UsesTestDatabase;

class MyTest extends TestCase
{
    use UsesTestDatabase;

    public function test_something()
    {
        // Test runs in transaction, auto-rolled back
        $case = Case::factory()->create();
        // ... assertions
    }
}
```

**Important Rules**:
- ✅ Always use `UsesTestDatabase` trait
- ✅ Tests use `DatabaseTransactions` for automatic rollback
- ❌ Never use `RefreshDatabase` - it will break or corrupt the test DB
- ❌ Never run migrations in tests

### Writing Good Tests

**Structure**:
```php
public function test_descriptive_name()
{
    // Arrange - Setup test data
    $case = Case::factory()->create();

    // Act - Perform the action
    $result = $service->analyzeCase($case);

    // Assert - Verify the outcome
    $this->assertNotNull($result);
    $this->assertEquals('expected', $result['value']);
}
```

**Naming Conventions**:
- Use descriptive test names: `test_drug_charge_abuse_detection`
- Or PHPUnit style: `it_detects_drug_charge_abuse()`
- Group related tests in the same test class
- Use data providers for multiple scenarios

**Test Independence**:
- Each test should be independent
- Don't rely on test execution order
- Clean up after yourself (transactions handle this automatically)
- Don't share state between tests

---

## Test Coverage Goals

### Current Coverage
- **Overall Target**: 80%+
- **Critical Services**: 90%+ (RAG, Graph, OpenAI)
- **Domain Modules**: 85%+ (Evidence, Misconduct, Topics)
- **Commands**: 75%+

### Quality Gates
Before merging:
- ✅ All tests must pass
- ✅ New features require test coverage
- ✅ Bug fixes require regression tests
- ✅ No skipped tests without documented reason
- ✅ Coverage must meet minimums

### Running Coverage Reports
```bash
# Generate coverage report
composer test:coverage

# View HTML coverage report
open coverage/index.html
```

---

## Test Database Management

### Setup Strategy
1. **Initial Setup**: `composer test:setup` copies production database to test database
2. **Persistent**: Test database stays intact between test runs
3. **Transactions**: Each test runs in a transaction that's rolled back
4. **Refresh**: Only run `composer test:setup` when you need fresh production data

### When to Refresh Test Database
- After significant production schema changes
- When test data becomes stale
- When tests start failing due to missing data
- After migration rollbacks

### Database Configuration
```env
# Test database (separate from production)
DB_TEST_CONNECTION=pgsql
DB_TEST_DATABASE=your_db_test
DB_TEST_USERNAME=your_user
DB_TEST_PASSWORD=your_password
```

---

## Continuous Integration

### GitHub Actions / CI Pipeline
Tests run automatically on:
- Pull requests
- Pushes to main branch
- Nightly builds

**CI Test Strategy**:
1. Setup test database (cached between runs)
2. Run unit tests (fast)
3. Run feature tests (slower)
4. Generate coverage report
5. Fail if coverage < 80%

---

## Troubleshooting

### Common Issues

**Tests failing with "database doesn't exist"**:
```bash
composer test:setup
```

**Tests failing with "SQLSTATE[23000]: Integrity constraint violation"**:
- Check if `UsesTestDatabase` trait is used
- Verify factories are creating valid data
- Check for missing foreign key relationships

**Tests running slowly**:
- Use `composer test:parallel` for parallel execution
- Check for N+1 query problems
- Verify database indexes exist

**Neo4j connection errors**:
```bash
# Disable Neo4j for tests
NEO4J_ENABLED=false composer test

# Or check Neo4j is running
docker ps | grep neo4j
```

**OpenAI rate limits during tests**:
- Use sequential execution: `composer test` (not parallel)
- Consider mocking OpenAI calls in unit tests
- Use test doubles for external API calls

---

## Resources

### Main Documentation
- [TESTING.md](../TESTING.md) - Comprehensive testing guide
- [TESTING_GUIDE.md](../TESTING_GUIDE.md) - Quick reference guide
- [CLAUDE.md](../CLAUDE.md) - Development patterns and conventions

### Coverage Documentation
- [TEST_COVERAGE_ANALYSIS_AND_PLAN.md](../TEST_COVERAGE_ANALYSIS_AND_PLAN.md) - Coverage analysis and improvement plan
- [TEST_COVERAGE_REVIEW.md](../TEST_COVERAGE_REVIEW.md) - Detailed coverage review

### Test Summaries
- [summaries/README.md](summaries/README.md) - Test summaries index
- [summaries/vector-stores/](summaries/vector-stores/) - Vector store tests
- [summaries/commands/](summaries/commands/) - Command tests
- [summaries/modules/](summaries/modules/) - Module tests
- [summaries/pipelines/](summaries/pipelines/) - Pipeline tests
- [summaries/services/](summaries/services/) - Service tests

### Laravel Testing Documentation
- [Laravel Testing](https://laravel.com/docs/11.x/testing)
- [Laravel Database Testing](https://laravel.com/docs/11.x/database-testing)
- [PHPUnit Documentation](https://phpunit.de/documentation.html)

---

## Contributing

### Adding New Tests

1. **Choose location**:
   - Unit tests → `tests/Unit/`
   - Integration tests → `tests/Feature/`
   - Browser tests → `tests/Browser/`

2. **Use proper trait**:
   ```php
   use Tests\Concerns\UsesTestDatabase;
   ```

3. **Write descriptive tests**:
   - Clear test names
   - Arrange-Act-Assert structure
   - Good assertions

4. **Run tests**:
   ```bash
   ./scripts/run-tests.sh --filter=YourTestClass
   ```

5. **Create test summary** (for significant components):
   - Add to appropriate category in `summaries/`
   - Update category README
   - Follow naming convention (kebab-case)

### Adding Test Summaries

When adding a new test summary:

1. **Identify category**: vector-stores, commands, modules, pipelines, or services
2. **Create summary file**: `summaries/{category}/{component-name}.md`
3. **Update category README**: Add link to new summary
4. **Follow template**: Include component overview, test coverage, running tests, known issues

---

**Last Updated**: 2025-11-09
**Document Version**: 1.0
**Status**: Active - Comprehensive testing documentation

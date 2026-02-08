# Testing Guide

## Overview

This project uses an integrated testing approach with a production database copy. Tests use database transactions to keep the test database clean, eliminating the need to refresh the database after each test.

## Documentation Navigation

- **📚 [Testing Documentation Hub](testing/README.md)** - Central hub for all testing documentation
- **📝 [Test Summaries by Category](testing/summaries/)** - Organized test documentation:
  - [Vector Stores](testing/summaries/vector-stores/) - Embedding and similarity search tests
  - [Commands](testing/summaries/commands/) - CLI and Artisan command tests
  - [Modules](testing/summaries/modules/) - Domain module tests (Evidence, Misconduct, Topics)
  - [Pipelines](testing/summaries/pipelines/) - Data processing pipeline tests (Textract, Odluke)
  - [Services](testing/summaries/services/) - Core service tests (RAG, GraphDB, OpenAI)
- **📊 [Coverage Analysis](TEST_COVERAGE_ANALYSIS_AND_PLAN.md)** - Test coverage review and improvement plan
- **🚀 [Quick Guide](TESTING_GUIDE.md)** - Quick reference for common testing tasks

## Quick Start

### 1. Setup Test Database (One-time setup)

```bash
# Setup test database by copying from production
composer test:setup

# Or manually
./scripts/setup-test-db.sh
```

### 2. Run Tests

```bash
# Run all tests
composer test:integrated

# Or with database setup
composer test:all

# Run specific test suites
composer test:unit          # Unit tests only
composer test:feature       # Feature tests only

# Run with coverage
composer test:coverage      # Minimum 80% coverage required

# Fast parallel execution
composer test:parallel      # Run tests in parallel
composer test:quick         # Parallel + stop on first failure
```

## Test Database Strategy

### Why Database Transactions?

We use **DatabaseTransactions** instead of **RefreshDatabase** for several reasons:

1. **Much Faster** - No need to rebuild database schema for each test
2. **Production-like Data** - Tests run against a copy of production database
3. **Clean Tests** - Each test runs in a transaction that's rolled back after completion
4. **No Side Effects** - Tests don't affect each other or the test database

### Database Setup

The test database is created by copying your production database:

```bash
# Interactive mode
./scripts/setup-test-db.sh

# Automatic mode
./scripts/setup-test-db.sh --auto

# Force overwrite existing test DB
./scripts/setup-test-db.sh --force

# Specify source database
./scripts/setup-test-db.sh --source-db=my_prod_db
```

**Supported Databases:**
- PostgreSQL (recommended)
- MySQL
- SQLite

### Configuration

Test environment is configured in `.env.testing`:

```env
# Test database configuration
DB_CONNECTION=pgsql
DB_DATABASE=laravel_test
DB_USERNAME=your_user
DB_PASSWORD=your_password

# Other test-specific settings
CACHE_STORE=array
QUEUE_CONNECTION=sync
MAIL_MAILER=array
```

## Writing Tests

### Using the Test Database

For tests that need database access, use the `UsesTestDatabase` trait:

```php
<?php

namespace Tests\Unit;

use Tests\TestCase;
use Tests\UsesTestDatabase;
use App\Models\User;

class UserTest extends TestCase
{
    use UsesTestDatabase;

    public function test_user_can_be_created()
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
        ]);

        // Transaction will automatically rollback after this test
    }
}
```

### When to Use RefreshDatabase

Only use `RefreshDatabase` when:
- Testing database migrations
- Testing database transactions themselves
- Need a completely fresh database state

For 99% of tests, use `UsesTestDatabase` instead.

### Test Factories

Use model factories to generate test data:

```php
use App\Models\User;
use App\Models\LegalCase;

public function test_case_belongs_to_user()
{
    $user = User::factory()->create();
    $case = LegalCase::factory()->create(['user_id' => $user->id]);

    $this->assertEquals($user->id, $case->user_id);
}
```

## Offline Testing with External APIs

### Why Offline Testing?

Testing with external APIs (OpenAI, AWS, etc.) has drawbacks:
- **Costs money** - Each test run incurs API costs
- **Slow execution** - Network requests add latency
- **Unreliable** - Network issues or rate limits cause test failures
- **Not repeatable** - External API changes can break tests
- **CI limitations** - Requires API keys in CI environment

**Solution:** Mock external HTTP requests using Laravel's `Http::fake()`.

### Mocking OpenAI API Calls

Use `Http::fake()` to intercept and mock OpenAI API requests:

```php
<?php

namespace Tests\Integration;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class SearchPipelineFlowTest extends TestCase
{
    use UsesTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Fake OpenAI API responses for offline testing
        Http::fake([
            'api.openai.com/v1/embeddings' => Http::response([
                'object' => 'list',
                'data' => [
                    [
                        'object' => 'embedding',
                        'embedding' => $this->generateMockEmbeddingVector(),
                        'index' => 0,
                    ],
                ],
                'model' => 'text-embedding-3-small',
                'usage' => [
                    'prompt_tokens' => 8,
                    'total_tokens' => 8,
                ],
            ], 200),
        ]);
    }

    /**
     * Helper: Generate mock embedding vector as array
     */
    protected function generateMockEmbeddingVector(): array
    {
        // Generate a 1536-dimensional vector (text-embedding-3-small)
        $vector = [];
        for ($i = 0; $i < 1536; $i++) {
            $vector[] = (mt_rand(-100, 100) / 100);
        }

        return $vector;
    }

    public function test_search_with_embeddings()
    {
        // Test runs completely offline - no real API calls
        $results = $this->searchService->search('test query');

        $this->assertNotEmpty($results);
    }
}
```

### Mocking Chat Completions

For GPT-4 chat completions, mock the chat API endpoint:

```php
Http::fake([
    'api.openai.com/v1/chat/completions' => Http::response([
        'id' => 'chatcmpl-test123',
        'object' => 'chat.completion',
        'created' => time(),
        'model' => 'gpt-4o',
        'choices' => [
            [
                'index' => 0,
                'message' => [
                    'role' => 'assistant',
                    'content' => 'This is a mock response for testing.',
                ],
                'finish_reason' => 'stop',
            ],
        ],
        'usage' => [
            'prompt_tokens' => 10,
            'completion_tokens' => 20,
            'total_tokens' => 30,
        ],
    ], 200),
]);
```

### Mocking Multiple Endpoints

Mock multiple API endpoints in a single test:

```php
Http::fake([
    // OpenAI embeddings
    'api.openai.com/v1/embeddings' => Http::response([
        'data' => [['embedding' => array_fill(0, 1536, 0.1)]],
    ]),

    // OpenAI chat completions
    'api.openai.com/v1/chat/completions' => Http::response([
        'choices' => [['message' => ['content' => 'Mock response']]],
    ]),

    // AWS Textract
    'textract.*.amazonaws.com/*' => Http::response([
        'JobId' => 'test-job-123',
        'JobStatus' => 'SUCCEEDED',
    ]),

    // Catch-all for other requests
    '*' => Http::response(['error' => 'Unexpected API call'], 500),
]);
```

### Benefits of Offline Testing

✅ **No API Costs** - Tests run without consuming API credits
✅ **Fast Execution** - No network latency
✅ **Reliable** - No external dependencies
✅ **CI-Friendly** - No API keys needed in CI environment
✅ **Repeatable** - Deterministic test results
✅ **Offline Development** - Work without internet connection

### When to Use Real vs. Mocked APIs

**Use Mocked APIs (Http::fake()) when:**
- Testing business logic that depends on API responses
- Running tests in CI/CD pipelines
- Testing error handling for API failures
- Need fast, repeatable test execution
- Testing with large datasets

**Use Real APIs when:**
- Integration testing actual API behavior
- Validating API response formats haven't changed
- Testing rate limiting and retry logic
- End-to-end smoke tests in staging environment

### Example: Removing API Dependencies

**Before** (skipped without API key):
```php
public function test_search_pipeline()
{
    if (empty(config('openai.api_key'))) {
        $this->markTestSkipped('OpenAI API key not configured');
    }

    // Test code that makes real API calls
}
```

**After** (runs offline):
```php
protected function setUp(): void
{
    parent::setUp();

    // Mock OpenAI API
    Http::fake([
        'api.openai.com/v1/embeddings' => Http::response([
            'data' => [['embedding' => $this->generateMockEmbeddingVector()]],
        ], 200),
    ]);
}

public function test_search_pipeline()
{
    // Test runs completely offline - no API key needed
    $results = $this->searchService->search('test query');

    $this->assertNotEmpty($results);
}
```

### Verifying HTTP Requests

Assert that specific HTTP requests were made:

```php
use Illuminate\Support\Facades\Http;

public function test_api_request_was_made()
{
    Http::fake();

    $this->service->generateEmbedding('test text');

    // Assert request was sent
    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.openai.com/v1/embeddings' &&
               $request['input'] === 'test text';
    });
}
```

### Environment Variables for Testing

**Not needed with Http::fake()** - The whole point is to avoid needing API keys in tests!

However, if you want to run some integration tests with real APIs:

```env
# .env.testing

# Set to true to use real APIs (default: false)
USE_REAL_OPENAI_API=false

# Only needed if USE_REAL_OPENAI_API=true
OPENAI_API_KEY=sk-test-key
```

Then in your test:

```php
protected function setUp(): void
{
    parent::setUp();

    // Only fake if not using real API
    if (!env('USE_REAL_OPENAI_API', false)) {
        Http::fake([
            'api.openai.com/*' => Http::response([
                // Mock response
            ]),
        ]);
    }
}
```

## Test Organization

```
tests/
├── Unit/              # Unit tests (isolated, fast)
│   ├── Models/
│   ├── Services/
│   ├── Repositories/
│   └── ...
├── Feature/           # Feature tests (integration)
│   ├── Api/
│   ├── Livewire/
│   ├── Console/
│   └── ...
├── Integration/       # End-to-end tests
├── TestCase.php       # Base test case
└── UsesTestDatabase.php  # Database transaction trait
```

## Available Commands

### Composer Scripts

```bash
composer test              # Quick test (in-memory SQLite)
composer test:integrated   # Full integrated test suite
composer test:setup        # Setup test database
composer test:all          # Setup + run all tests
composer test:unit         # Unit tests only
composer test:feature      # Feature tests only
composer test:coverage     # With coverage report (min 80%)
composer test:parallel     # Parallel execution (faster)
composer test:quick        # Fast fail (parallel + stop on failure)
```

### Script Options

The test runner script supports many options:

```bash
./scripts/run-tests.sh --help

Options:
  --setup              Setup test database before running tests
  --unit               Run only unit tests
  --feature            Run only feature tests
  --coverage           Generate code coverage report (min 80%)
  --parallel           Run tests in parallel (faster)
  --filter=NAME        Run only tests matching the given name
  --stop-on-failure    Stop on first test failure
  -v, --verbose        Verbose output

Examples:
  ./scripts/run-tests.sh --setup
  ./scripts/run-tests.sh --unit --coverage
  ./scripts/run-tests.sh --feature --parallel
  ./scripts/run-tests.sh --filter=UserTest
```

## Continuous Integration

The test suite is integrated with GitHub Actions. Tests run automatically on:
- Push to `main`, `develop`, `claude/**` branches
- Pull requests to `main`, `develop` branches

See `.github/workflows/tests.yml` for CI configuration.

## Test Data Management

### Option 1: Database Transactions (Recommended)

Tests use transactions that are automatically rolled back. No manual cleanup needed.

### Option 2: Regenerate Test Database

If you need fresh test data:

```bash
# Recreate test database from production
composer test:setup
```

### Option 3: Database Seeders

Create seeders for specific test scenarios:

```php
php artisan make:seeder TestDataSeeder
```

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\LegalCase;

class TestDataSeeder extends Seeder
{
    public function run()
    {
        User::factory(10)->create();
        LegalCase::factory(50)->create();
    }
}
```

Then seed in tests:

```php
public function test_something()
{
    $this->seed(TestDataSeeder::class);
    // Your test code
}
```

## Performance Tips

### 1. Use Parallel Testing

```bash
composer test:parallel
```

PHPUnit will automatically split tests across multiple processes.

### 2. Use Test Filters

Run only what you're working on:

```bash
./scripts/run-tests.sh --filter=UserControllerTest
./scripts/run-tests.sh --filter=test_user_can_login
```

### 3. Stop on First Failure

Save time during development:

```bash
composer test:quick
# or
./scripts/run-tests.sh --stop-on-failure
```

### 4. Skip Slow Tests

Mark slow tests to skip during development:

```php
public function test_slow_operation()
{
    if (!getenv('RUN_SLOW_TESTS')) {
        $this->markTestSkipped('Slow test - set RUN_SLOW_TESTS=1 to run');
    }

    // Slow test code
}
```

## Troubleshooting

### Test Database Not Found

```bash
# Setup test database
composer test:setup
```

### Tests Failing After Schema Changes

```bash
# Recreate test database with updated schema
./scripts/setup-test-db.sh --force
```

### Connection Issues

Check `.env.testing` has correct database credentials:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=laravel_test
DB_USERNAME=your_user
DB_PASSWORD=your_password
```

### PostgreSQL Service Not Running

If you get "Connection refused" errors or "could not open file 'global/pg_filenode.map': Permission denied":

```bash
# Check if PostgreSQL is running
pgrep -a postgres

# If not running, start it
/usr/bin/pg_ctlcluster 16 main start

# Verify connection
su - postgres -c "psql -c 'SELECT version()'"

# Create test database and user if needed
su - postgres -c "psql -c \"CREATE USER claude WITH PASSWORD 'claude'\""
su - postgres -c "psql -c \"CREATE DATABASE laravel_test OWNER claude\""
su - postgres -c "psql -c \"GRANT ALL PRIVILEGES ON DATABASE laravel_test TO claude\""

# Test connection with credentials
PGPASSWORD=claude psql -U claude -h 127.0.0.1 -d laravel_test -c "SELECT 1"
```

### Slow Test Performance

1. Use `--parallel` flag
2. Check for tests using `RefreshDatabase` (switch to `UsesTestDatabase`)
3. Optimize database queries in tests
4. Use test filters to run subset of tests

### Transaction Issues

If you need to test actual database commits:

```php
// Don't use UsesTestDatabase trait for these specific tests
use Illuminate\Foundation\Testing\RefreshDatabase;

class TransactionTest extends TestCase
{
    use RefreshDatabase;  // For this specific test only

    public function test_database_transaction()
    {
        // Test actual database transaction logic
    }
}
```

## Best Practices

### 1. Keep Tests Fast

- Use `UsesTestDatabase` instead of `RefreshDatabase`
- **Mock external API calls** using `Http::fake()` (see [Offline Testing](#offline-testing-with-external-apis))
- Use factories instead of creating records manually
- Avoid unnecessary database queries
- Run tests in parallel with `composer test:parallel`

### 2. Keep Tests Isolated

- Each test should work independently
- Don't rely on test execution order
- Use transactions to prevent test pollution
- Clean up any external resources (files, cache, etc.)

### 3. Use Descriptive Names

```php
// Good
public function test_user_can_create_legal_case_with_valid_data()

// Bad
public function test_case()
```

### 4. Test One Thing

Each test should verify one specific behavior:

```php
// Good
public function test_user_email_must_be_unique()
{
    User::factory()->create(['email' => 'test@example.com']);

    $this->expectException(ValidationException::class);
    User::factory()->create(['email' => 'test@example.com']);
}

// Bad - testing multiple things
public function test_user()
{
    // Tests creation, validation, relationships, etc.
}
```

### 5. Use Assertions Wisely

```php
// Be specific
$this->assertEquals('expected', $actual);
$this->assertDatabaseHas('users', ['email' => 'test@example.com']);
$this->assertJsonFragment(['status' => 'success']);

// Don't just check for truthy values
$this->assertTrue($user->exists()); // Good
$this->assertTrue($user); // Bad - too vague
```

### 6. Mock External APIs for Offline Testing

Always mock external API calls in tests to avoid costs, latency, and reliability issues:

```php
// Good - Runs offline, fast, free
protected function setUp(): void
{
    parent::setUp();

    Http::fake([
        'api.openai.com/*' => Http::response([
            'data' => [['embedding' => array_fill(0, 1536, 0.1)]],
        ]),
    ]);
}

// Bad - Requires API key, costs money, slow, unreliable
public function test_with_real_api()
{
    $result = $this->openaiService->generateEmbedding('test');
    // Makes real API call $$$
}
```

See [Offline Testing with External APIs](#offline-testing-with-external-apis) for comprehensive examples.

## Neo4j Graph Database Testing

The application uses Neo4j for graph-based legal knowledge representation. Tests are designed to work both **offline** (with mocks) and with **real Neo4j** (for integration testing).

### Unit Tests (Offline)

Unit tests for Neo4j services use mocking and don't require a running Neo4j instance. These tests run fast, cost nothing, and work in CI environments without external dependencies.

**Run all Neo4j unit tests:**

```bash
# Run all Neo4j unit tests
./vendor/bin/phpunit tests/Unit/Services/Neo4jServiceTest.php \
  tests/Unit/Services/GraphDatabaseServiceTest.php \
  tests/Unit/Services/GraphDatabaseServiceMockTest.php \
  tests/Unit/Services/GraphDatabaseServiceTransactionTest.php \
  tests/Unit/Services/Graph/GraphAnalyticsServiceTest.php \
  tests/Unit/Services/Graph/GraphRagOrchestratorEdgeCasesTest.php \
  --testdox

# Or run specific test file
./vendor/bin/phpunit tests/Unit/Services/GraphDatabaseServiceTest.php --testdox

# With coverage
./vendor/bin/phpunit tests/Unit/Services/GraphDatabaseServiceTest.php \
  --coverage-text --coverage-filter=app/Services
```

**Benefits of unit tests:**
- ✅ No Neo4j installation required
- ✅ Fast execution (<1 second per test)
- ✅ Works offline and in CI
- ✅ Reliable and deterministic

### Integration Tests (Requires Neo4j)

Integration tests verify actual Neo4j functionality and require a running Neo4j instance. These tests use the `@group slow` annotation and are automatically skipped if Neo4j is unavailable.

**Start Neo4j with Docker:**

```bash
# Start Neo4j container
docker run -d --name neo4j-test \
  -p 7474:7474 -p 7687:7687 \
  -e NEO4J_AUTH=neo4j/password \
  neo4j:latest

# Wait for Neo4j to start (about 10 seconds)
sleep 10

# Verify Neo4j is running
docker logs neo4j-test | grep "Started"
```

**Run integration tests:**

```bash
# Run all Neo4j integration tests
./vendor/bin/phpunit tests/Integration/Neo4jComprehensiveTest.php \
  tests/Integration/Neo4jGraphRagTest.php \
  tests/Feature/ExternalAPIs/Neo4jIntegrationTest.php \
  --testdox

# Run specific integration test
./vendor/bin/phpunit tests/Integration/Neo4jComprehensiveTest.php::test_basic_node_creation_and_retrieval

# Run with slow tests included
./vendor/bin/phpunit tests/Integration/Neo4jComprehensiveTest.php --group slow
```

**Cleanup after testing:**

```bash
# Stop and remove Neo4j container
docker stop neo4j-test
docker rm neo4j-test
```

**Configure .env.testing for Neo4j:**

```env
# Neo4j configuration for integration tests
NEO4J_ENABLED=true
NEO4J_URI=bolt://localhost:7687
NEO4J_USERNAME=neo4j
NEO4J_PASSWORD=password
NEO4J_DATABASE=neo4j

# Enable/disable automatic graph sync
NEO4J_AUTO_SYNC=true
```

### Mocking Neo4j for Tests

Use the `MocksNeo4j` trait to write tests that work offline without a real Neo4j database. This is the **recommended approach** for most tests.

**Basic usage:**

```php
<?php

namespace Tests\Unit;

use Tests\TestCase;
use Tests\Concerns\MocksNeo4j;
use App\Services\GraphDatabaseService;

class MyFeatureTest extends TestCase
{
    use MocksNeo4j;

    /** @test */
    public function it_handles_neo4j_unavailable_gracefully(): void
    {
        // Mock Neo4j as unavailable
        $this->mockNeo4jUnavailable();

        // Test code that should gracefully handle missing Neo4j
        $result = $this->myService->doSomething();

        $this->assertNotNull($result);
        // Feature should work even without Neo4j
    }

    /** @test */
    public function it_syncs_to_neo4j_when_available(): void
    {
        // Mock Neo4j as available
        $graph = $this->mockNeo4jAvailable();

        // Mock specific Cypher query
        $graph->shouldReceive('run')
            ->with('CREATE (n:Law {id: $id}) RETURN n', ['id' => 'law-123'])
            ->andReturn(collect([
                (object)['n' => (object)['id' => 'law-123']]
            ]));

        // Test code that syncs to Neo4j
        $this->myService->syncLaw('law-123');

        // Assertions...
    }

    /** @test */
    public function it_queries_graph_data(): void
    {
        // Mock Neo4j query result
        $this->mockNeo4jQuery(
            'MATCH (l:Law {id: $id}) RETURN l',
            ['id' => 'law-123'],
            [
                (object)['l' => (object)['id' => 'law-123', 'title' => 'Test Law']]
            ]
        );

        // Test code that queries Neo4j
        $result = $this->graphService->findLaw('law-123');

        $this->assertEquals('law-123', $result->id);
        $this->assertEquals('Test Law', $result->title);
    }
}
```

**MocksNeo4j trait methods:**

- `mockNeo4jUnavailable()` - Mocks Neo4j as unavailable (isAvailable() returns false)
- `mockNeo4jAvailable()` - Mocks Neo4j as available, returns mock instance for further configuration
- `mockNeo4jQuery(string $query, array $params, $returnValue)` - Mocks specific Cypher query response

**Example: Testing error handling:**

```php
/** @test */
public function it_throws_exception_when_neo4j_unavailable_for_required_operation(): void
{
    $this->mockNeo4jUnavailable();

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Neo4j client is not available');

    // Code that requires Neo4j
    $this->graphService->transaction(function () {
        return 'should not execute';
    });
}
```

**Example: Testing complex graph queries:**

```php
/** @test */
public function it_finds_most_cited_laws(): void
{
    $graph = $this->mockNeo4jAvailable();

    $graph->shouldReceive('run')
        ->with(
            Mockery::pattern('/MATCH.*LawDocument.*CITES/'),
            Mockery::any()
        )
        ->andReturn(collect([
            (object)[
                'law_id' => 'ZKP-240',
                'title' => 'Pretraga doma',
                'citation_count' => 150
            ],
            (object)[
                'law_id' => 'ZKP-9',
                'title' => 'Načelo zakonitosti',
                'citation_count' => 120
            ],
        ]));

    $results = $this->analyticsService->getMostCitedLaws(10);

    $this->assertCount(2, $results);
    $this->assertEquals('ZKP-240', $results[0]->law_id);
    $this->assertGreaterThan(100, $results[0]->citation_count);
}
```

### Test Coverage

Current Neo4j test coverage status:

| Component | Coverage | Test Type | Notes |
|-----------|----------|-----------|-------|
| **GraphDatabaseService** | 90%+ | Unit + Integration | Core graph database operations fully tested |
| **Neo4jService** | 100% | Unit | Legacy service, fully covered |
| **GraphRagOrchestrator** | 85%+ | Unit + Integration | RAG pipeline coordination |
| **Graph Analytics** | 80%+ | Unit | PageRank, centrality, clustering |
| **Graph Transactions** | 100% | Unit | Transaction handling and rollback |

**Test files:**
- `tests/Unit/Services/Neo4jServiceTest.php` - Legacy Neo4j service (100% coverage)
- `tests/Unit/Services/GraphDatabaseServiceTest.php` - Core graph operations (90%+)
- `tests/Unit/Services/GraphDatabaseServiceMockTest.php` - Offline testing utilities
- `tests/Unit/Services/GraphDatabaseServiceTransactionTest.php` - Transaction edge cases
- `tests/Unit/Services/Graph/GraphAnalyticsServiceTest.php` - Graph algorithms
- `tests/Unit/Services/Graph/GraphRagOrchestratorEdgeCasesTest.php` - Error handling
- `tests/Integration/Neo4jComprehensiveTest.php` - 21 integration tests (requires Neo4j)
- `tests/Integration/Neo4jGraphRagTest.php` - RAG pipeline integration
- `tests/Feature/ExternalAPIs/Neo4jIntegrationTest.php` - API integration

**Generate Neo4j coverage report:**

```bash
# Generate HTML coverage report for graph services
./vendor/bin/phpunit tests/Unit/Services/GraphDatabaseServiceTest.php \
  tests/Unit/Services/Graph/ \
  --coverage-html coverage/neo4j \
  --coverage-filter=app/Services/GraphDatabaseService.php \
  --coverage-filter=app/Services/Graph/

# Open in browser
open coverage/neo4j/index.html  # macOS
xdg-open coverage/neo4j/index.html  # Linux
```

**Run full Neo4j test suite:**

```bash
# All unit tests (fast, offline)
./vendor/bin/phpunit \
  tests/Unit/Services/Neo4jServiceTest.php \
  tests/Unit/Services/GraphDatabaseServiceTest.php \
  tests/Unit/Services/Graph/ \
  --testdox

# All integration tests (requires Neo4j)
./vendor/bin/phpunit \
  tests/Integration/Neo4jComprehensiveTest.php \
  tests/Integration/Neo4jGraphRagTest.php \
  --testdox

# Everything (unit + integration)
./vendor/bin/phpunit \
  tests/Unit/Services/Neo4jServiceTest.php \
  tests/Unit/Services/GraphDatabaseServiceTest.php \
  tests/Unit/Services/Graph/ \
  tests/Integration/Neo4jComprehensiveTest.php \
  tests/Integration/Neo4jGraphRagTest.php \
  --testdox
```

### Integration Test Graceful Skipping

Integration tests automatically skip when Neo4j is unavailable:

```php
protected function setUp(): void
{
    parent::setUp();

    if (!$this->isNeo4jAvailable()) {
        $this->markTestSkipped('Neo4j is not available. Start with: docker run -d -p 7687:7687 neo4j:latest');
    }

    $this->graph = app(GraphDatabaseService::class);
}

private function isNeo4jAvailable(): bool
{
    try {
        $service = app(GraphDatabaseService::class);
        return $service->isAvailable();
    } catch (\Exception $e) {
        return false;
    }
}
```

This ensures integration tests won't fail in CI environments without Neo4j - they'll simply be skipped.

### Best Practices for Neo4j Testing

1. **Prefer unit tests with mocks** - Faster, more reliable, work offline
2. **Use integration tests sparingly** - Only for critical graph operations
3. **Clean up test data** - Use test labels (`:TestNode`, `:TestLaw`) and clean up after tests
4. **Test both success and failure** - Mock unavailable Neo4j to test graceful degradation
5. **Avoid hard-coding test data** - Use factories and dynamic IDs (`uniqid()`)
6. **Test transaction rollback** - Ensure failed operations don't corrupt the graph

**Example cleanup in integration tests:**

```php
protected function tearDown(): void
{
    // Clean up all test nodes
    if ($this->graph && $this->graph->isAvailable()) {
        $this->graph->run('MATCH (n) WHERE any(label IN labels(n) WHERE label STARTS WITH "Test") DETACH DELETE n');
    }

    parent::tearDown();
}
```

## Code Coverage

Run tests with coverage report:

```bash
composer test:coverage
```

Minimum coverage requirement: **80%**

Coverage reports are generated in:
- Console output (summary)
- HTML report: `coverage/html/index.html` (if configured)

## Need Help?

- Check Laravel Testing Documentation: https://laravel.com/docs/testing
- PHPUnit Documentation: https://phpunit.de/documentation.html
- Review existing tests in `tests/` directory
- Ask the team!

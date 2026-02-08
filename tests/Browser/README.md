# Browser Tests (Laravel Dusk)

## Overview

This directory contains browser tests for the AI Legal War Machine application using Laravel Dusk.

## Database Configuration

Browser tests use a **separate test database**: `ai_legal_war_machine_dusk_test`

**Configuration:** `.env.dusk.local`
```bash
DB_DATABASE=ai_legal_war_machine_dusk_test
```

### Why a Separate Database?

- Prevents pollution of production/development database
- Allows tests to run in parallel without conflicts
- Provides complete test isolation
- Makes cleanup automatic and reliable

## Database Isolation - REQUIRED

**ALL Dusk tests MUST use the DatabaseMigrations trait:**

```php
<?php

namespace Tests\Browser;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\DuskTestCase;

class MyBrowserTest extends DuskTestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test data in setUp() or test methods
        $this->user = User::factory()->create();
    }

    // Tests...
}
```

### Why DatabaseMigrations?

- ✅ Fresh database for each test method
- ✅ Complete test isolation
- ✅ No duplicate key violations
- ✅ Can run tests in any order
- ✅ Production database never touched
- ✅ No manual cleanup needed

### What DatabaseMigrations Does

For each test method:
1. Drops all tables
2. Runs all migrations (fresh structure)
3. Runs setUp() (create your test data)
4. Executes test
5. Repeats for next test

### DO NOT Use Manual tearDown()

❌ **Bad - Manual cleanup (unreliable):**
```php
protected function tearDown(): void
{
    // Manual cleanup - can fail
    if (isset($this->user)) {
        $this->user->delete();
    }

    parent::tearDown();
}
```

✅ **Good - DatabaseMigrations (automatic):**
```php
use DatabaseMigrations;

// No tearDown needed - automatically cleaned up
```

### Creating Test Data

Use factories in setUp() or test methods:

```php
protected function setUp(): void
{
    parent::setUp();

    // Database is fresh and empty here
    $this->user = User::factory()->create([
        'email' => 'test@example.com',
    ]);

    // Or seed reference data if needed
    $this->seed(ReferenceDataSeeder::class);
}
```

### Performance Considerations

- Migrations add ~2-3 seconds per test method
- This is acceptable for reliability
- Total test suite may take longer but will be stable
- Consider reducing number of test methods or optimizing migrations if needed

## Common Errors & Solutions

### Error: `SQLSTATE[23505]: Unique violation`

**Cause:** DatabaseMigrations trait not used

**Fix:** Add `use DatabaseMigrations;` to test class

### Error: `SQLSTATE[08006]: Connection refused`

**Cause:** PostgreSQL not running

**Fix:**
```bash
# Start PostgreSQL
sudo service postgresql start

# Or check if it's already running
ps aux | grep postgres
```

### Error: `Failed to connect to localhost port 9515`

**Cause:** ChromeDriver not running

**Fix:**
```bash
# Start ChromeDriver
./vendor/laravel/dusk/bin/chromedriver-linux --port=9515 &
```

### Error: `session not created: Chrome version mismatch`

**Cause:** ChromeDriver version doesn't match Chrome version

**Fix:**
```bash
# Install matching ChromeDriver version
php artisan dusk:chrome-driver 141  # Replace with your Chrome version
```

## Test Files

### CollaborationDashboardTest.php ✅ Database Isolated
Tests the multi-agent collaboration dashboard:
- Empty state rendering
- Statistics display
- Collaboration table
- Details modal
- Agent execution display
- Pagination
- Complex scenarios

**Status:** Uses DatabaseMigrations trait for complete isolation

### EoglasnaMonitoringTest.php
Tests the Eoglasna Monitoring dashboard (`/eoglasna`):
- `test_eoglasna_monitoring_dashboard` - Tests tab switching and basic dashboard display
- `test_keyword_alerts` - Tests keyword creation, editing, and alert functionality
- `test_court_notice_export` - Tests court notice filtering and search

### OpenAILogViewerTest.php
Tests the OpenAI Log Viewer (`/openai/logs`):
- `test_log_viewer_displays_requests` - Tests log entry display and auto-refresh
- `test_log_filtering` - Tests search and event type filters
- `test_cost_metrics_display` - Tests token/cost metrics display

### VectorStoreManagerTest.php
Tests the Vector Store Manager (`/vectors/manage`):
- `test_vector_store_browsing` - Tests store selection, pagination, and document browsing
- `test_vector_search` - Tests similarity and content search functionality
- `test_re_indexing` - Tests document re-indexing and bulk operations

## Running Tests

### Prerequisites

1. **PostgreSQL must be running**:
   ```bash
   sudo service postgresql start
   ```

2. **ChromeDriver must be running**:
   ```bash
   # Install correct version
   php artisan dusk:chrome-driver --detect

   # Start ChromeDriver
   ./vendor/laravel/dusk/bin/chromedriver-linux --port=9515 &
   ```

3. **Laravel development server must be running**:
   ```bash
   php artisan serve --port=8000 &
   ```

4. **Environment Setup**:
   - `.env.dusk.local` file must exist with test database configuration
   - Test database must be created and migrated

### Initial Setup (One-Time)

```bash
# 1. Create test database
psql -U claude -h 127.0.0.1 -d postgres -c "CREATE DATABASE ai_legal_war_machine_dusk_test;"

# 2. Grant permissions
psql -U claude -h 127.0.0.1 -d postgres -c "GRANT ALL PRIVILEGES ON DATABASE ai_legal_war_machine_dusk_test TO claude;"

# 3. Run initial migrations
DB_DATABASE=ai_legal_war_machine_dusk_test php artisan migrate:fresh

# 4. Verify
psql -U claude -h 127.0.0.1 -d ai_legal_war_machine_dusk_test -c "\dt"
```

### Run All Browser Tests
```bash
php artisan dusk
```

### Run Specific Test File
```bash
php artisan dusk tests/Browser/CollaborationDashboardTest.php
```

### Run Specific Test Method
```bash
php artisan dusk --filter test_component_renders_with_empty_state
```

### Run Tests with Debugging
```bash
# Show browser output
php artisan dusk --without-tty

# Keep browser open on failure
# (Modify test to remove headless mode temporarily)
```

## Database Verification

### Check Test Database is Clean
```bash
# Should return 0
psql -U claude -h 127.0.0.1 -d ai_legal_war_machine_dusk_test -c "SELECT COUNT(*) FROM users;"
```

### Check Production Database is Safe
```bash
# Should return 0
psql -U claude -h 127.0.0.1 -d ai_legal_war_machine -c "SELECT COUNT(*) FROM users WHERE email = 'test@example.com';"
```

## Migration Pattern for Existing Tests

If you have an existing test without DatabaseMigrations:

1. **Add the import:**
   ```php
   use Illuminate\Foundation\Testing\DatabaseMigrations;
   ```

2. **Add the trait:**
   ```php
   class MyTest extends DuskTestCase
   {
       use DatabaseMigrations;  // Add this
   ```

3. **Remove tearDown():**
   ```php
   // Delete this entire method
   protected function tearDown(): void { ... }
   ```

4. **Verify test runs:**
   ```bash
   php artisan dusk tests/Browser/MyTest.php
   ```

## Best Practices

1. **Always use DatabaseMigrations** for Dusk tests
2. **Create test data in setUp()** for reusability
3. **Use factories** for consistent test data
4. **Keep tests isolated** - don't depend on other tests
5. **Use descriptive test names** that explain what's being tested
6. **Add dusk attributes** to UI elements for reliable selectors
7. **Mock external APIs** to avoid network dependencies
8. **Keep tests fast** - minimize unnecessary waits
9. **Clean up background processes** after test runs (ChromeDriver, Laravel server)

## Troubleshooting

### Tests are slow
- Check if migrations are being run multiple times
- Consider using in-memory SQLite for faster tests (if not using pgvector)
- Reduce number of test data records created

### Tests are flaky
- Add appropriate wait conditions (`->waitFor()`, `->waitUntil()`)
- Don't rely on fixed `pause()` - use dynamic waits
- Check for race conditions in JavaScript/Livewire

### Database not cleaning up
- Verify DatabaseMigrations trait is added
- Check .env.dusk.local points to test database
- Verify test database exists and is accessible

## Required View Updates

**IMPORTANT**: For these tests to pass, Livewire views need `dusk` attributes matching test selectors.
See tests for complete list of required selectors.

## Related Documentation

- [Laravel Dusk Documentation](https://laravel.com/docs/dusk)
- [Database Testing Guide](../../documentation/testing/e2e-database-isolation-implementation.md)
- [Factory Documentation](../Factories/README.md)

# Test Scripts

This directory contains scripts for managing the integrated test suite.

## Available Scripts

### 1. setup-test-db.sh

**Purpose:** Creates a test database by copying from production database.

**Usage:**
```bash
# Interactive mode
./scripts/setup-test-db.sh

# Automatic mode (no prompts)
./scripts/setup-test-db.sh --auto

# Force overwrite existing test database
./scripts/setup-test-db.sh --force

# Specify source and target databases
./scripts/setup-test-db.sh --source-db=my_prod_db --test-db=my_test_db
```

**Options:**
- `--auto` - Run without prompts (uses defaults or .env values)
- `--force` - Force overwrite if test database already exists
- `--source-db=NAME` - Specify source database name
- `--test-db=NAME` - Specify test database name (default: laravel_test)
- `--help` - Show help message

**Supported Databases:**
- PostgreSQL (uses `CREATE DATABASE WITH TEMPLATE`)
- MySQL (uses `mysqldump` and import)
- SQLite (copies database file)

**Example:**
```bash
# Copy from production to test database
./scripts/setup-test-db.sh --auto --force
```

---

### 2. run-tests.sh

**Purpose:** Runs the complete test suite with various options.

**Usage:**
```bash
# Run all tests
./scripts/run-tests.sh

# Run with test database setup first
./scripts/run-tests.sh --setup

# Run specific test suite
./scripts/run-tests.sh --unit
./scripts/run-tests.sh --feature

# Run with coverage
./scripts/run-tests.sh --coverage

# Run tests in parallel (faster)
./scripts/run-tests.sh --parallel

# Run specific test
./scripts/run-tests.sh --filter=UserTest

# Combine options
./scripts/run-tests.sh --setup --parallel --coverage
```

**Options:**
- `--setup` - Setup test database before running tests
- `--unit` - Run only unit tests
- `--feature` - Run only feature tests
- `--coverage` - Generate code coverage report (min 80%)
- `--parallel` - Run tests in parallel processes (faster)
- `--filter=NAME` - Run only tests matching the given name/pattern
- `--stop-on-failure` - Stop execution on first test failure
- `-v, --verbose` - Verbose output
- `--help` - Show help message

**Examples:**
```bash
# Quick development cycle
./scripts/run-tests.sh --parallel --stop-on-failure

# Full test suite with coverage
./scripts/run-tests.sh --coverage

# Test specific feature
./scripts/run-tests.sh --filter=AuthenticationTest --verbose

# Fresh setup and run all tests
./scripts/run-tests.sh --setup
```

---

## Composer Shortcuts

These scripts are integrated with Composer for easy access:

```bash
composer test:setup        # Setup test database
composer test:integrated   # Run integrated test suite
composer test:all          # Setup + run all tests
composer test:unit         # Unit tests only
composer test:feature      # Feature tests only
composer test:coverage     # With coverage report
composer test:parallel     # Parallel execution
composer test:quick        # Fast fail (parallel + stop on failure)
```

---

## Artisan Command

You can also use the Artisan command for test database setup:

```bash
# Setup test database
php artisan test:setup-db

# With options
php artisan test:setup-db --source=my_db --target=my_test_db --force --seed
```

---

## How It Works

### Database Strategy

1. **One-time Setup**: Copy production database to create test database
2. **Transaction Wrapping**: Each test runs in a transaction
3. **Automatic Rollback**: Transaction is rolled back after each test
4. **Clean State**: Database remains unchanged between test runs

### Benefits

- **Fast**: No schema rebuilding between tests
- **Realistic**: Tests run against production-like data
- **Clean**: No side effects between tests
- **Easy**: Single command to run entire suite

### When to Refresh Test Database

Refresh the test database when:
- Schema changes are made (migrations)
- Production data has significantly changed
- Test database gets corrupted
- You want fresh data

```bash
# Refresh test database
composer test:setup

# Or force recreate
./scripts/setup-test-db.sh --force
```

---

## Troubleshooting

### Script Not Executable

```bash
chmod +x ./scripts/*.sh
```

### Database Connection Issues

Check your `.env.testing` file has correct credentials:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=laravel_test
DB_USERNAME=your_user
DB_PASSWORD=your_password
```

### Permission Denied

On PostgreSQL, ensure your user has permission to create databases:

```sql
ALTER USER your_user CREATEDB;
```

### Script Not Found

Run scripts from project root:

```bash
cd /path/to/project
./scripts/run-tests.sh
```

---

## Best Practices

1. **Setup Once**: Run `composer test:setup` once, then reuse test database
2. **Use Transactions**: Use `UsesTestDatabase` trait in tests (not `RefreshDatabase`)
3. **Parallel Testing**: Use `--parallel` for faster execution
4. **Refresh Periodically**: Recreate test database weekly or after major schema changes
5. **CI/CD**: Scripts work in CI/CD pipelines (GitHub Actions, GitLab CI, etc.)

---

## See Also

- [TESTING.md](../TESTING.md) - Complete testing guide
- [phpunit.xml](../phpunit.xml) - PHPUnit configuration
- [.env.testing](../.env.testing) - Test environment configuration

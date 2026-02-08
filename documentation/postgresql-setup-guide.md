# PostgreSQL Setup Guide for AI Legal War Machine

This document explains how PostgreSQL was enabled and configured for Sprint 13 Worker A development.

## What Was Done at Session Start

At the beginning of Sprint 13 Worker A, PostgreSQL was enabled following these exact steps:

### Step 1: Check PostgreSQL Service Status

```bash
sudo systemctl status postgresql
```

This checked if PostgreSQL was already running. If not, we proceeded to start it.

### Step 2: Start PostgreSQL Service

```bash
sudo systemctl start postgresql
```

This started the PostgreSQL database server. The service listens on port 5432 (default).

### Step 3: Enable PostgreSQL on Boot

```bash
sudo systemctl enable postgresql
```

This ensures PostgreSQL automatically starts when the system boots, so you don't have to manually start it every time.

### Step 4: Verify PostgreSQL is Running

```bash
sudo systemctl is-active postgresql
# Output: active

# Alternative verification:
sudo -u postgres psql -c "SELECT version();"
```

This confirmed PostgreSQL was accepting connections.

### Step 5: Verify Laravel Database Connection

```bash
php artisan db:show
```

This tested that Laravel could successfully connect to the PostgreSQL database using credentials from `.env`.

## Why PostgreSQL Was Required

Sprint 13 Worker A required PostgreSQL for the following reasons:

1. **Test Database Requirement**: The project uses `UsesTestDatabase` trait which requires a persistent PostgreSQL test database (not SQLite in-memory)

2. **Production-Like Testing**: Tests run against a copy of the production database schema to ensure realistic test conditions

3. **DatabaseTransactions**: All tests use the `DatabaseTransactions` trait which requires a real database for transaction rollback

4. **Complex Queries**: The AI Legal War Machine uses advanced PostgreSQL features:
   - Full-text search (tsvector, tsquery)
   - JSON/JSONB columns
   - Vector similarity search
   - Complex joins with court decisions

## Automated Setup Script

> **Note:** The project uses `scripts/ensure-postgres-pgvector.sh` for PostgreSQL setup with pgvector extension support. This script handles installation, configuration, and extension setup.

### Usage

```bash
# Run the automated setup (handles PostgreSQL + pgvector)
./scripts/ensure-postgres-pgvector.sh
```

### What the Script Does

1. **Check Installation**: Verifies PostgreSQL is installed (installs if missing)
2. **Start Service**: Starts PostgreSQL if not running
3. **Enable on Boot**: Ensures service starts automatically
4. **Install pgvector**: Installs the pgvector extension for vector search
5. **Verify Connection**: Tests that PostgreSQL accepts connections
6. **Create Databases**: Creates production and test databases
7. **Test Laravel**: Verifies Laravel can connect

## Manual Setup Steps

If you need to set up PostgreSQL manually:

### 1. Install PostgreSQL (if not installed)

```bash
# Ubuntu/Debian
sudo apt-get update
sudo apt-get install postgresql postgresql-contrib

# macOS
brew install postgresql@16

# Start on macOS
brew services start postgresql@16
```

### 2. Start and Enable Service

```bash
# Linux
sudo systemctl start postgresql
sudo systemctl enable postgresql

# Check status
sudo systemctl status postgresql
```

### 3. Create Databases

```bash
# Create production database
sudo -u postgres createdb ai_legal_war_machine

# Create test database
sudo -u postgres createdb ai_legal_war_machine_test

# Or using psql
sudo -u postgres psql -c "CREATE DATABASE ai_legal_war_machine;"
sudo -u postgres psql -c "CREATE DATABASE ai_legal_war_machine_test;"
```

### 4. Create Database User (if needed)

```bash
# Create user with password
sudo -u postgres psql -c "CREATE USER your_user WITH ENCRYPTED PASSWORD 'your_password';"

# Grant superuser privileges (for migrations)
sudo -u postgres psql -c "ALTER USER your_user WITH SUPERUSER;"

# Grant database access
sudo -u postgres psql -c "GRANT ALL PRIVILEGES ON DATABASE ai_legal_war_machine TO your_user;"
sudo -u postgres psql -c "GRANT ALL PRIVILEGES ON DATABASE ai_legal_war_machine_test TO your_user;"
```

### 5. Configure Laravel .env

Ensure your `.env` file has correct PostgreSQL credentials:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=ai_legal_war_machine
DB_USERNAME=your_user
DB_PASSWORD=your_password
```

### 6. Setup Test Database

```bash
# Copy production schema to test database
composer test:setup

# Or manually
./scripts/setup-test-db.sh
```

## Verification Commands

### Check PostgreSQL Status

```bash
# Service status
sudo systemctl status postgresql

# Is it running?
sudo systemctl is-active postgresql

# Is it enabled on boot?
sudo systemctl is-enabled postgresql
```

### List Databases

```bash
# Using psql
sudo -u postgres psql -c "\l"

# Or
sudo -u postgres psql -c "SELECT datname FROM pg_database WHERE datistemplate = false;"
```

### Test Connection

```bash
# PostgreSQL native connection
sudo -u postgres psql -c "SELECT version();"

# Laravel connection
php artisan db:show

# Laravel migrate test
php artisan migrate:status
```

### Check Listening Ports

```bash
# Check PostgreSQL is listening on port 5432
sudo netstat -tlnp | grep 5432

# Or with ss
sudo ss -tlnp | grep 5432
```

## Common Issues and Solutions

### Issue: PostgreSQL Not Starting

```bash
# Check logs
sudo journalctl -u postgresql -n 50

# Check PostgreSQL logs
sudo tail -f /var/log/postgresql/postgresql-*.log
```

### Issue: Connection Refused

```bash
# Check if PostgreSQL is listening
sudo netstat -tlnp | grep 5432

# Check PostgreSQL config allows connections
sudo nano /etc/postgresql/*/main/pg_hba.conf

# Add this line if needed:
# local   all             all                                     trust
# host    all             all             127.0.0.1/32            md5

# Restart after config change
sudo systemctl restart postgresql
```

### Issue: Laravel Can't Connect

```bash
# Test credentials manually
psql -h 127.0.0.1 -U your_user -d ai_legal_war_machine

# Check .env values
php artisan config:clear
php artisan config:cache
php artisan db:show
```

### Issue: Test Database Missing

```bash
# Recreate test database
composer test:setup

# Or force recreation
./scripts/setup-test-db.sh --force
```

## PostgreSQL vs SQLite for Testing

The project **requires PostgreSQL** for testing, not SQLite:

| Feature | PostgreSQL | SQLite |
|---------|-----------|--------|
| Production Parity | ✅ Yes | ❌ No |
| Full-Text Search | ✅ tsvector | ❌ Limited |
| JSON/JSONB | ✅ Yes | ❌ No JSONB |
| Transactions | ✅ Full | ⚠️ Limited |
| Vector Search | ✅ pgvector | ❌ No |
| Complex Queries | ✅ Full | ⚠️ Limited |

**Never use SQLite for testing this project** - the tests depend on PostgreSQL-specific features.

## Integration with Test Suite

Once PostgreSQL is set up, the test suite works as follows:

### 1. Test Configuration (`phpunit.xml`)

```xml
<env name="DB_CONNECTION" value="pgsql"/>
<env name="DB_DATABASE" value="ai_legal_war_machine_test"/>
```

### 2. Test Database Setup

```bash
# One-time setup (copies production schema)
composer test:setup
```

### 3. Running Tests

```bash
# All tests
composer test

# Component tests
./vendor/bin/phpunit tests/Feature/Components/

# Specific test
./vendor/bin/phpunit --filter=CardTest
```

### 4. Database Transactions

Every test uses `UsesTestDatabase` trait which provides `DatabaseTransactions`:

```php
use Tests\Concerns\UsesTestDatabase;

class MyTest extends TestCase
{
    use UsesTestDatabase;  // Automatic transaction rollback

    public function test_something()
    {
        // Changes are rolled back after test
        $case = Case::factory()->create();
        $this->assertDatabaseHas('cases', ['id' => $case->id]);
    }
    // Rolled back automatically - database stays clean
}
```

## Performance Optimization

### Connection Pooling

PostgreSQL connections are managed by Laravel's connection pool. For better performance:

```env
# Increase connection pool size
DB_POOL_SIZE=10
```

### Persistent Connections

```env
# Enable persistent connections (reduces connection overhead)
DB_PERSISTENT=true
```

### Query Optimization

```bash
# Enable query log for debugging
php artisan db:monitor

# Analyze slow queries
sudo -u postgres psql -d ai_legal_war_machine -c "SELECT * FROM pg_stat_statements ORDER BY total_time DESC LIMIT 10;"
```

## Resources

- [PostgreSQL Documentation](https://www.postgresql.org/docs/)
- [Laravel Database Documentation](https://laravel.com/docs/11.x/database)
- [TESTING.md](TESTING.md) - Project testing guide
- [CLAUDE.md](CLAUDE.md) - Project development guide

## Summary

PostgreSQL setup for Sprint 13 Worker A was accomplished with these commands:

```bash
# 1. Start PostgreSQL
sudo systemctl start postgresql

# 2. Enable on boot
sudo systemctl enable postgresql

# 3. Verify running
sudo systemctl is-active postgresql

# 4. Setup test database
composer test:setup

# 5. Run tests
composer test
```

**PostgreSQL is now running and ready for development!** 🚀

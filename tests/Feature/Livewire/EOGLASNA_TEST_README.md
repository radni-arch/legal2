# EoglasnaMonitoring Test Suite

## Overview

The `EoglasnaMonitoringTest.php` contains a comprehensive test suite for the EoglasnaMonitoring Livewire component with 48 tests covering:

- Component mounting and initialization
- Tab switching (osijek, keywords, activity)
- Osijek court items search and filtering
- Keyword CRUD operations
- Keyword validation
- Pagination
- Keyword activity tracking
- Modal interactions
- Query string parameters
- Empty states
- View rendering

## Test Requirements

### Database Setup

These tests require a working database connection because the EoglasnaMonitoring component queries actual database models in its render method.

**Options:**

1. **PostgreSQL (Recommended for CI/CD)**
   ```bash
   # Start PostgreSQL service
   service postgresql start

   # Create test database
   createdb -U postgres ai_legal_test

   # Run migrations
   php artisan migrate --env=testing

   # Run tests
   vendor/bin/phpunit tests/Feature/Livewire/EoglasnaMonitoringTest.php
   ```

2. **SQLite (Requires pdo_sqlite extension)**
   ```bash
   # Install SQLite extension for PHP 8.4
   apt-get install php8.4-sqlite3

   # The phpunit.xml is already configured for :memory: SQLite
   vendor/bin/phpunit tests/Feature/Livewire/EoglasnaMonitoringTest.php
   ```

### Required Factories

The following factories have been created and are required for these tests:

- `EoglasnaKeywordFactory.php` - For keyword management
- `EoglasnaOsijekMonitoringFactory.php` - For court monitoring data
- `EoglasnaNoticeFactory.php` - For e-Oglasna notices
- `EoglasnaKeywordMatchFactory.php` - For keyword match tracking

## Test Coverage

### Component Mounting (4 tests)
- Basic component rendering
- Default property values
- View data passing

### Tab Switching (4 tests)
- Switch between osijek, keywords, and activity tabs
- Query string parameter handling

### Osijek Search (7 tests)
- Search by title, case number, court name, OIB, name
- Empty search handling
- Result ordering

### Keyword CRUD (5 tests)
- Create new keywords
- Edit existing keywords
- Delete keywords
- Modal opening/closing

### Keyword Validation (8 tests)
- Query field validation (required, min length)
- Scope validation (required, valid values)
- Boolean field validation
- Optional fields handling

### Keyword Search (3 tests)
- Search keywords by query
- Empty search handling
- Result ordering

### Pagination (3 tests)
- Osijek items pagination
- Keywords pagination
- Activity pagination

### Keyword Activity (3 tests)
- Display keyword matches
- Show keyword query and scope
- Ordering by match time

### Modal Interactions (3 tests)
- Close on cancel
- Close on save
- Reset validation errors

### Query String (2 tests)
- Search parameters persistence

### Empty States (3 tests)
- No osijek items message
- No keywords message
- No activity message

### View Rendering (3 tests)
- Osijek tab columns
- Keywords tab columns
- Activity tab columns

## Running Tests

```bash
# Run all EoglasnaMonitoring tests
vendor/bin/phpunit tests/Feature/Livewire/EoglasnaMonitoringTest.php

# Run specific test
vendor/bin/phpunit --filter=it_can_create_a_new_keyword tests/Feature/Livewire/EoglasnaMonitoringTest.php

# Run with coverage
vendor/bin/phpunit --coverage-html coverage tests/Feature/Livewire/EoglasnaMonitoringTest.php
```

## Known Issues

- Tests currently fail in environments without SQLite extension installed
- PostgreSQL must be running and accessible for tests using PostgreSQL configuration
- Tests create actual database records (use testing database, not production)

## Future Improvements

- Add support for mocking Eloquent queries to avoid database dependency
- Add tests for concurrent access scenarios
- Add performance tests for large datasets
- Add visual regression tests for the dark-themed UI

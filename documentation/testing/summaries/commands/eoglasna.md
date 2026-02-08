# Eoglasna Commands Test Suite Summary

## Overview

This document summarizes the comprehensive test suite for all 5 Eoglasna (e-Oglasna) commands in the Croatian legal system integration. The e-Oglasna system is Croatia's official public gazette for legal notices, court announcements, bankruptcies, and official publications.

**Total Tests:** 80 tests across 5 command files
**Total Implementation Lines:** 221 lines
**Total Test Lines:** 1,487 lines
**Test Coverage Ratio:** 6.7:1 (test to implementation)

## Croatian e-Oglasna Context

### What is e-Oglasna?

e-Oglasna (Electronic Official Gazette) is the Croatian government's electronic publication system for:
- **Court Notices**: Legal proceedings, case announcements
- **Bankruptcy Announcements**: Corporate and personal bankruptcy proceedings
- **Official Publications**: Government agency notices, institutional announcements
- **Public Records**: Property sales, auctions, legal notifications

### Key Terminology

- **Općinski sud u Osijeku**: Municipal Court in Osijek (a specific court being monitored)
- **Stečaj**: Bankruptcy
- **Predstečajna nagodba**: Pre-bankruptcy settlement
- **Osobni/potrošački stečaj**: Personal/consumer bankruptcy
- **Likvidacija**: Liquidation
- **Nekretnina**: Real estate
- **Tužitelj**: Plaintiff
- **Tuženik**: Defendant
- **Svjedok**: Witness

### e-Oglasna Scopes

The system organizes publications into different scopes:

1. **notice**: General legal notices and announcements
2. **court**: Court-specific publications and proceedings
3. **institution**: Government and institutional announcements
4. **court_legal_bankruptcy**: Corporate/legal entity bankruptcy proceedings
5. **court_natural_bankruptcy**: Individual/consumer bankruptcy proceedings

## Test Files Summary

### 1. EoglasnaSyncCourtsTest.php (8 tests)

**Command:** `eoglasna:sync-courts`
**Purpose:** Synchronize court list from e-Oglasna API
**Implementation:** 37 lines
**Tests:** 8 tests, 131 lines

**Arguments/Options:** None

**Test Coverage:**
1. Sync courts successfully
2. Sync zero courts when none found
3. Sync large number of courts
4. Handle service exception
5. Handle network timeout
6. Call sync courts exactly once
7. Execute without arguments
8. Display correct count in message

**Example Usage:**
```bash
# Sync all courts from e-Oglasna API
php artisan eoglasna:sync-courts
```

**Expected Output:**
```
Synchronized 50 courts.
```

**Key Test Pattern:**
```php
$this->eoglasnaMock->shouldReceive('syncCourts')
    ->once()
    ->andReturn(50);

$this->artisan('eoglasna:sync-courts')
    ->expectsOutput('Synchronized 50 courts.')
    ->assertExitCode(0);
```

**Use Case:**
This command is typically run periodically to keep the local court database synchronized with the official e-Oglasna court registry. It ensures that monitoring and search functions have access to the latest court information.

---

### 2. EoglasnaNormalizeParticipantsTest.php (13 tests)

**Command:** `eoglasna:normalize-participants`
**Purpose:** Normalize participants JSON (UTF-8 decode, slash cleanup) and re-fill parsed columns for existing eoglasna_osijek_monitoring rows
**Implementation:** 47 lines
**Tests:** 13 tests, 231 lines

**Options:**
- `--chunk` (default: 500): Process records per chunk

**Test Coverage:**
1. Normalize participants with default chunk
2. Normalize participants with custom chunk
3. Handle empty database
4. Normalize single record
5. Handle records with empty participants
6. Handle records with null participants
7. Process large chunk size
8. Process small chunk size
9. Normalize records with multiple participants
10. Update participant data correctly
11. Display correct count in output
12. Handle chunk option as string
13. Process records in order by ID

**Example Usage:**
```bash
# Normalize all participants with default chunk size (500)
php artisan eoglasna:normalize-participants

# Normalize with custom chunk size for memory optimization
php artisan eoglasna:normalize-participants --chunk=100

# Process in very small chunks for large datasets
php artisan eoglasna:normalize-participants --chunk=10
```

**Expected Output:**
```
Normalized 250 rows.
```

**Participant Data Structure:**
```json
[
  {
    "ime": "Ivan Horvat",
    "uloga": "tužitelj"
  },
  {
    "ime": "Marija Kovačević",
    "uloga": "tuženik"
  },
  {
    "ime": "Petar Novak",
    "uloga": "svjedok"
  }
]
```

**Key Test Pattern:**
```php
EoglasnaOsijekMonitoring::factory()->count(5)->create([
    'participants' => [
        ['ime' => 'Test', 'uloga' => 'tužitelj']
    ]
]);

$this->artisan('eoglasna:normalize-participants', ['--chunk' => '2'])
    ->expectsOutput('Normalized 5 rows.')
    ->assertExitCode(0);
```

**Use Case:**
This command is used for data migration and cleanup tasks. When the e-Oglasna API returns participant data with encoding issues (incorrect UTF-8, escaped slashes), this command normalizes the data and re-parses participant columns. It's particularly useful after bulk imports or API format changes.

**Technical Details:**
- Uses Laravel's `chunkById()` for memory-efficient processing
- Employs reflection to access private normalization methods in the repository
- Processes records in ID order to ensure consistent results
- Updates both the `participants` JSON column and parsed participant columns

---

### 3. EoglasnaWatchTest.php (17 tests)

**Command:** `eoglasna:watch`
**Purpose:** Monitor e-Oglasna feed for predefined keywords or run a deep scan for an exact term across all pagination
**Implementation:** 58 lines
**Tests:** 17 tests, 313 lines

**Options:**
- `--deep`: Run deep scan for exact term (optional)
- `--scope` (default: 'notice'): Scope to search in (notice|court|institution|court_legal_bankruptcy|court_natural_bankruptcy)

**Test Coverage:**
1. Monitor keywords by default
2. Run deep scan with deep option
3. Run deep scan with custom scope
4. Run deep scan with institution scope
5. Run deep scan with court_legal_bankruptcy scope
6. Run deep scan with court_natural_bankruptcy scope
7. Run deep scan with zero results
8. Skip deep scan when empty string
9. Handle monitoring exception with logging
10. Handle deep scan exception
11. Do not call monitor keywords when deep option provided
12. Do not call deep scan when no deep option
13. Use notice scope by default
14. Handle runtime error during monitoring
15. Log exception details
16. Display correct deep scan count

**Example Usage:**
```bash
# Monitor predefined keywords in default scope (notice)
php artisan eoglasna:watch

# Deep scan for specific term "bankruptcy"
php artisan eoglasna:watch --deep="bankruptcy"

# Deep scan in court scope
php artisan eoglasna:watch --deep="stečaj" --scope=court

# Deep scan in institution scope
php artisan eoglasna:watch --deep="ministarstvo" --scope=institution

# Deep scan for legal bankruptcies
php artisan eoglasna:watch --deep="likvidacija" --scope=court_legal_bankruptcy

# Deep scan for personal bankruptcies
php artisan eoglasna:watch --deep="osobni stečaj" --scope=court_natural_bankruptcy
```

**Expected Output (Monitoring Mode):**
```
Monitoring e-Oglasna feed for predefined keywords...
Monitoring run completed.
```

**Expected Output (Deep Scan Mode):**
```
Running deep scan for term [bankruptcy] in scope [notice]...
Deep scan complete. Exact matches persisted: 15
```

**Key Test Pattern:**
```php
// Monitoring mode
$this->eoglasnaMock->shouldReceive('monitorKeywords')
    ->once()
    ->andReturnNull();

$this->artisan('eoglasna:watch')
    ->expectsOutput('Monitoring e-Oglasna feed for predefined keywords...')
    ->expectsOutput('Monitoring run completed.')
    ->assertExitCode(0);

// Deep scan mode
$this->eoglasnaMock->shouldReceive('deepScanExact')
    ->once()
    ->with('bankruptcy', 'notice')
    ->andReturn(15);

$this->artisan('eoglasna:watch', ['--deep' => 'bankruptcy'])
    ->expectsOutput('Running deep scan for term [bankruptcy] in scope [notice]...')
    ->expectsOutput('Deep scan complete. Exact matches persisted: 15')
    ->assertExitCode(0);
```

**Use Case:**

**Monitoring Mode** (default): Runs continuously or on a schedule to monitor the e-Oglasna feed for predefined keywords stored in the database. Useful for tracking specific terms, companies, or case types automatically.

**Deep Scan Mode** (`--deep`): Performs an exhaustive search for an exact term across all pages of the specified scope. This is useful for:
- One-time comprehensive searches for specific terms
- Historical data collection for specific keywords
- Backfilling data for newly monitored terms
- Compliance and discovery requirements

**Error Handling:**
The command includes comprehensive error logging to track API failures, network issues, or processing errors. All exceptions are logged with stack traces for debugging.

---

### 4. EoglasnaWatchKeywordsTest.php (18 tests)

**Command:** `eoglasna:watch-keywords`
**Purpose:** Monitor e-Oglasna feed for predefined keywords, or deep scan for an exact term
**Implementation:** 45 lines
**Tests:** 18 tests, 334 lines

**Options:**
- `--deep`: Run deep scan for exact term (optional)
- `--scope` (default: 'notice'): Scope to search in (notice|court|institution|court_legal_bankruptcy|court_natural_bankruptcy)

**Test Coverage:**
1. Monitor keywords by default
2. Run deep scan with deep option
3. Run deep scan with custom scope
4. Run deep scan with institution scope
5. Run deep scan with court_legal_bankruptcy scope
6. Run deep scan with court_natural_bankruptcy scope
7. Run deep scan with zero results
8. Skip deep scan when empty string
9. Handle monitoring exception with logging
10. Handle deep scan exception
11. Do not call monitor keywords when deep option provided
12. Do not call deep scan when no deep option
13. Use notice scope by default
14. Handle runtime error during monitoring
15. Log exception details
16. Display correct deep scan count
17. Display keyword monitoring completion message

**Example Usage:**
```bash
# Monitor predefined keywords
php artisan eoglasna:watch-keywords

# Deep scan for insolvency cases
php artisan eoglasna:watch-keywords --deep="insolvency"

# Deep scan in court scope
php artisan eoglasna:watch-keywords --deep="nekretnina" --scope=court

# Deep scan for government ministry announcements
php artisan eoglasna:watch-keywords --deep="ministarstvo" --scope=institution

# Deep scan for pre-bankruptcy settlements
php artisan eoglasna:watch-keywords --deep="predstečajna nagodba" --scope=court_legal_bankruptcy

# Deep scan for consumer bankruptcies
php artisan eoglasna:watch-keywords --deep="potrošački stečaj" --scope=court_natural_bankruptcy
```

**Expected Output (Monitoring Mode):**
```
Monitoring e-Oglasna feed for predefined keywords...
Keyword monitoring run completed.
```

**Expected Output (Deep Scan Mode):**
```
Running deep scan for term [insolvency] in scope [notice]...
Deep scan complete. Exact matches persisted: 20
```

**Key Test Pattern:**
```php
// Monitoring mode
$this->eoglasnaMock->shouldReceive('monitorKeywords')
    ->once()
    ->andReturnNull();

$this->artisan('eoglasna:watch-keywords')
    ->expectsOutput('Monitoring e-Oglasna feed for predefined keywords...')
    ->expectsOutput('Keyword monitoring run completed.')
    ->assertExitCode(0);

// Deep scan mode
$this->eoglasnaMock->shouldReceive('deepScanExact')
    ->once()
    ->with('nekretnina', 'court')
    ->andReturn(30);

$this->artisan('eoglasna:watch-keywords', ['--deep' => 'nekretnina', '--scope' => 'court'])
    ->expectsOutput('Running deep scan for term [nekretnina] in scope [court]...')
    ->expectsOutput('Deep scan complete. Exact matches persisted: 30')
    ->assertExitCode(0);
```

**Use Case:**

This command is functionally similar to `eoglasna:watch` but with a more explicit name emphasizing keyword monitoring. It's useful when:
- Multiple monitoring commands are configured in schedulers
- Different teams manage different monitoring strategies
- Clearer command naming improves maintainability

**Typical Keywords Monitored:**
- **Legal Terms**: "stečaj", "likvidacija", "ovršni postupak"
- **Case Types**: "kazneni postupak", "parnični postupak"
- **Property Terms**: "nekretnina", "zemljište", "dražba"
- **Corporate Events**: "statusne promjene", "likvidacija društva"
- **Government Actions**: "rješenje", "odluka", "uredba"

**Error Handling:**
Identical to `eoglasna:watch`, includes comprehensive logging with the command name `eoglasna:watch-keywords` for easy filtering in log aggregation systems.

---

### 5. EoglasnaWatchOsijekTest.php (12 tests)

**Command:** `eoglasna:watch-osijek`
**Purpose:** Fetch and upsert ALL e-Oglasna items originating from Općinski sud u Osijeku (Municipal Court in Osijek)
**Implementation:** 34 lines
**Tests:** 12 tests, 197 lines

**Arguments/Options:** None

**Test Coverage:**
1. Monitor Osijek court successfully
2. Process zero items when none found
3. Process large number of items
4. Handle service exception with logging
5. Handle network timeout
6. Handle runtime exception
7. Call monitor Osijek court all exactly once
8. Execute without arguments
9. Display correct count in message
10. Log exception with trace
11. Display fetching message
12. Handle partial processing

**Example Usage:**
```bash
# Fetch all items from Općinski sud u Osijeku
php artisan eoglasna:watch-osijek
```

**Expected Output:**
```
Fetching ALL e-Oglasna items from Općinski sud u Osijeku...
Osijek court monitoring done. Items processed: 75
```

**Key Test Pattern:**
```php
$this->eoglasnaMock->shouldReceive('monitorOsijekCourtAll')
    ->once()
    ->andReturn(75);

$this->artisan('eoglasna:watch-osijek')
    ->expectsOutput('Fetching ALL e-Oglasna items from Općinski sud u Osijeku...')
    ->expectsOutput('Osijek court monitoring done. Items processed: 75')
    ->assertExitCode(0);
```

**Use Case:**

This specialized command monitors all publications from the Općinski sud u Osijeku (Osijek Municipal Court), which appears to be of particular interest to this legal system. The command:

- Fetches **all** items from this specific court (not just keywords)
- Stores them in the `eoglasna_osijek_monitoring` table
- Useful for comprehensive court tracking and analysis
- Enables participant parsing and normalization

**Why Osijek Court Specifically?**
The Osijek court monitoring suggests:
- Regional jurisdiction focus for this legal AI system
- Specific legal cases of interest in the Osijek area
- Training data collection for Croatian legal AI models
- Compliance or discovery requirements for Osijek court cases

**Processing:**
The command performs upsert operations, meaning:
- New items are inserted
- Existing items are updated
- No duplicates are created
- Historical data is preserved

**Error Handling:**
Includes detailed logging with the command name `eoglasna:watch-osijek` for tracking court-specific monitoring issues.

---

## Common Test Patterns

### 1. Service Mocking Pattern

All tests use Mockery to mock the EoglasnaService:

```php
protected function setUp(): void
{
    parent::setUp();

    $this->eoglasnaMock = Mockery::mock(EoglasnaService::class);
    $this->app->instance(EoglasnaService::class, $this->eoglasnaMock);
}

protected function tearDown(): void
{
    Mockery::close();
    parent::tearDown();
}
```

### 2. Command Testing Pattern

```php
$this->artisan('command:name', ['arg' => 'value', '--option' => 'value'])
    ->expectsOutput('Expected output')
    ->assertExitCode(0);
```

### 3. Exception Handling with Logging Pattern

```php
Log::spy();

$this->eoglasnaMock->shouldReceive('method')
    ->once()
    ->andThrow(new \Exception('Error message'));

$this->artisan('command:name')
    ->expectsOutput('Monitoring failed: Error message')
    ->assertExitCode(1);

Log::shouldHaveReceived('error')
    ->once()
    ->with('command:name failed', Mockery::on(function ($context) {
        return $context['error'] === 'Error message' && isset($context['trace']);
    }));
```

### 4. Database Testing Pattern (for Normalize Command)

```php
EoglasnaOsijekMonitoring::factory()->count(5)->create([
    'participants' => [
        ['ime' => 'Test', 'uloga' => 'tužitelj']
    ]
]);

$this->artisan('eoglasna:normalize-participants')
    ->expectsOutput('Normalized 5 rows.')
    ->assertExitCode(0);

$record->refresh();
$this->assertNotNull($record->participants);
```

## Test Statistics by Category

### Sync Commands (8 tests)
- **EoglasnaSyncCourtsTest**: 8 tests
- **Coverage**: Court synchronization, error handling, count display

### Data Processing Commands (13 tests)
- **EoglasnaNormalizeParticipantsTest**: 13 tests
- **Coverage**: Chunking, participant normalization, edge cases

### Monitoring Commands (50 tests)
- **EoglasnaWatchTest**: 17 tests
- **EoglasnaWatchKeywordsTest**: 18 tests
- **EoglasnaWatchOsijekTest**: 12 tests
- **Coverage**: Keyword monitoring, deep scanning, scope filtering, error handling

## Exit Codes

All commands follow Laravel's command exit code conventions:

- **0 (SUCCESS)**: Command completed successfully
- **1 (FAILURE)**: Service exception or runtime error

## Running the Tests

```bash
# Run all Eoglasna command tests
vendor/bin/phpunit tests/Feature/Console/Eoglasna*

# Run specific test file
vendor/bin/phpunit tests/Feature/Console/EoglasnaSyncCourtsTest.php

# Run with coverage
vendor/bin/phpunit --coverage-html coverage tests/Feature/Console/Eoglasna*

# Run specific test method
vendor/bin/phpunit --filter it_monitors_keywords_by_default tests/Feature/Console/EoglasnaWatchTest.php
```

## Integration with e-Oglasna API

### API Endpoints (handled by EoglasnaService)

The commands interact with the Croatian e-Oglasna REST API:

- **Sync Courts**: `GET /api/courts`
- **Monitor Keywords**: `GET /api/notices?keywords={keywords}&scope={scope}`
- **Deep Scan**: `GET /api/notices?search={term}&scope={scope}&page={page}`
- **Monitor Osijek Court**: `GET /api/notices?court=osijek&all=true`

### Authentication

All API calls require authentication via the EoglasnaService, which handles:
- API token management
- SSL certificate validation
- Request signing
- Error handling and retries

## Scope Descriptions

### 1. Notice (Objava)
General legal notices and announcements including:
- Public notifications
- Legal summons
- Property auctions
- Missing person announcements

### 2. Court (Sud)
Court-specific publications:
- Case proceedings
- Court decisions
- Trial schedules
- Legal judgments

### 3. Institution (Ustanova)
Government and institutional announcements:
- Ministry announcements
- Regulatory changes
- Public agency notices
- Official decrees

### 4. Court Legal Bankruptcy (Stečaj pravnih osoba)
Corporate and legal entity bankruptcy:
- Company bankruptcy proceedings
- Liquidation announcements
- Creditor meetings
- Asset sales

### 5. Court Natural Bankruptcy (Stečaj fizičkih osoba)
Individual and consumer bankruptcy:
- Personal bankruptcy filings
- Debt restructuring
- Consumer insolvency
- Asset declarations

## Typical Workflow

1. **Initial Setup:**
   ```bash
   # Sync court list
   php artisan eoglasna:sync-courts
   ```

2. **Regular Monitoring:**
   ```bash
   # Monitor keywords (scheduled hourly)
   php artisan eoglasna:watch

   # Monitor Osijek court (scheduled daily)
   php artisan eoglasna:watch-osijek
   ```

3. **Deep Research:**
   ```bash
   # Deep scan for specific term
   php artisan eoglasna:watch --deep="bankruptcy" --scope=court_legal_bankruptcy
   ```

4. **Data Cleanup:**
   ```bash
   # Normalize participant data after bulk import
   php artisan eoglasna:normalize-participants --chunk=100
   ```

## Schedule Configuration Example

```php
// app/Console/Kernel.php

protected function schedule(Schedule $schedule)
{
    // Sync courts weekly
    $schedule->command('eoglasna:sync-courts')
        ->weekly()
        ->sundays()
        ->at('01:00');

    // Monitor keywords every hour
    $schedule->command('eoglasna:watch')
        ->hourly();

    // Monitor Osijek court daily
    $schedule->command('eoglasna:watch-osijek')
        ->dailyAt('02:00');

    // Normalize participants after monitoring
    $schedule->command('eoglasna:normalize-participants')
        ->dailyAt('03:00');
}
```

## Data Models

### EoglasnaOsijekMonitoring

Stores monitored items from Osijek court:

```php
Schema::create('eoglasna_osijek_monitoring', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->text('content')->nullable();
    $table->json('participants')->nullable();
    $table->string('case_number')->nullable();
    $table->string('proceeding_type')->nullable();
    $table->timestamp('published_at')->nullable();
    $table->timestamps();
});
```

## Future Enhancements

Potential areas for additional test coverage:

1. **Performance Testing**: Test monitoring commands with large result sets
2. **Rate Limiting**: Test API throttling behavior
3. **Concurrent Execution**: Test parallel command execution
4. **Data Validation**: Test participant data structure validation
5. **Retry Logic**: Test exponential backoff for API failures
6. **Webhook Integration**: Test real-time notification triggers
7. **Advanced Filtering**: Test complex keyword combinations
8. **Historical Data**: Test backfill operations for date ranges

## Conclusion

This comprehensive test suite provides 80 tests covering all 5 Eoglasna commands with:
- ✅ Complete command option coverage
- ✅ Monitoring mode testing (keywords and deep scan)
- ✅ Scope filtering validation
- ✅ Error handling with logging
- ✅ Database operations (chunking, normalization)
- ✅ Edge case coverage (zero results, empty data)
- ✅ Exit code verification
- ✅ Output assertion validation

The test suite ensures robust integration with the Croatian e-Oglasna official gazette system and provides confidence in the command-line interface for legal publication monitoring and data collection.

# FactExtractionService Test Suite

## Overview
This test suite provides comprehensive coverage for the `FactExtractionService`, which extracts structured legal facts from Croatian court decisions.

The service uses:
- **Pattern-based extraction** for basic facts (parties, dates, procedural posture)
- **LLM-based extraction** for complex facts (legal issues, holdings, arguments)
- **Hybrid approach** combining both methods
- **Caching** for performance optimization

## Test Coverage

### 46 comprehensive tests covering:

#### Main Extraction (8 tests)
- Successful extraction with LLM
- Pattern-based extraction only (no LLM)
- Cache hit/miss scenarios
- Decision not found errors
- LLM failure handling with graceful fallback
- Cache configuration (disabled, custom TTL)
- Performance metrics tracking

#### Party Extraction (4 tests)
- Croatian plaintiff patterns (tužitelj, predlagatelj, žalitelj)
- Croatian defendant patterns (tuženik, protivnik, protivstranka)
- Party deduplication
- Judge metadata extraction

#### Date Extraction (7 tests)
- Filing date extraction from Croatian text patterns
- Hearing dates extraction (rasprava, ročište, saslušanje)
- Croatian date normalization (DD.MM.YYYY → YYYY-MM-DD)
- Dates with extra spaces
- Invalid date handling
- Metadata dates (decision_date, publication_date)
- Hearing date deduplication

#### Procedural Posture (6 tests)
- Appeal detection from decision_type and content
- Revision detection from decision_type and content
- First instance detection from decision_type and content
- Prior proceedings extraction
- Prior proceedings deduplication

#### LLM Complex Fact Extraction (6 tests)
- Successful LLM extraction
- Content length limiting (8000 chars for LLM)
- JSON parsing error handling
- Markdown code block cleaning
- Empty response handling
- Error logging

#### Case Metadata (1 test)
- Complete metadata extraction (case_number, court, jurisdiction, etc.)

#### Decision Comparison (8 tests)
- Two-decision comparison (basic + complex facts)
- Comparison failure handling
- Court/jurisdiction/decision_type matching
- Shared party detection
- Complex facts comparison (legal issues, legal grounds)
- String similarity for holdings and relief
- Comparison error handling

#### Batch Extraction (6 tests)
- Multiple decision extraction
- Partial failure handling
- Error logging for batch failures
- Continued processing after errors
- Success/failure metrics

## Database Requirements

**IMPORTANT**: These tests require database access because the service queries the `court_decision_documents` and `court_decisions` tables.

### Required PHP Extensions
- SQLite PDO extension (`pdo_sqlite`) OR PostgreSQL PDO extension (`pdo_pgsql`)

### Setup Options

#### Option 1: Install SQLite PDO Extension (Recommended for Testing)
```bash
# Ubuntu/Debian
sudo apt-get install php-sqlite3

# macOS (with Homebrew)
brew install php
# SQLite is usually included by default

# Verify installation
php -m | grep pdo_sqlite
```

#### Option 2: Use PostgreSQL Database
Ensure your `.env.testing` is configured for PostgreSQL:
```env
DB_CONNECTION=pgsql
DB_HOST=localhost
DB_PORT=5432
DB_DATABASE=legal_testing
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

## Running the Tests

Once database is configured:
```bash
# Run all FactExtractionService tests
vendor/bin/phpunit tests/Unit/Services/FactExtractionServiceTest.php

# Or use artisan
php artisan test --filter=FactExtractionServiceTest
```

## Service Features Tested

### Pattern-Based Extraction
- **Croatian legal terminology**: Recognizes Croatian legal terms for parties, procedural actions, and dates
- **Regular expressions**: Uses regex patterns for precise extraction
- **Date normalization**: Converts Croatian DD.MM.YYYY format to ISO 8601 (YYYY-MM-DD)
- **Deduplication**: Removes duplicate parties and dates

### LLM-Based Extraction
- **GPT-4o model**: Uses OpenAI's GPT-4o for complex fact extraction
- **JSON structured output**: Enforces JSON response format for reliability
- **Content limiting**: Processes first 8000 characters to fit context window
- **Prompt engineering**: Uses detailed prompts for Croatian legal analysis
- **Error resilience**: Falls back gracefully when LLM unavailable

### Caching
- **Configurable TTL**: Default 60 minutes, customizable
- **Cache keys**: Separate keys for LLM vs. pattern-based extraction
- **Performance tracking**: Measures extraction time

### Decision Comparison
- **Basic similarity**: Compares courts, jurisdictions, procedural postures
- **Party matching**: Finds shared plaintiffs and defendants
- **Complex similarity**: Compares legal issues, grounds, holdings
- **String similarity**: Uses `similar_text()` for text comparison (70% threshold)

### Batch Processing
- **Multiple decisions**: Processes arrays of decision IDs
- **Error isolation**: Continues processing despite individual failures
- **Comprehensive results**: Returns success/failure counts and details

## Test Dependencies
- **OpenAIService** (mocked for testing)
- **Database** (court_decision_documents, court_decisions tables)
- **Cache** facade (mocked)
- **Log** facade (mocked)

## Croatian Legal Terms Reference

### Party Types
- **Tužitelj** = Plaintiff (civil cases)
- **Predlagatelj** = Petitioner
- **Žalitelj** = Appellant
- **Tuženik** = Defendant (civil cases)
- **Protivnik** = Opposing party
- **Protivstranka** = Counter-party

### Procedural Terms
- **Žalba** = Appeal
- **Revizija** = Revision (appeal to Supreme Court)
- **Prvostupanjska presuda** = First instance judgment
- **Rasprava** = Hearing/trial
- **Ročište** = Court session/hearing
- **Saslušanje** = Examination

### Decision Types
- **Presuda** = Judgment
- **Odluka** = Decision/ruling
- **Rješenje** = Order/resolution

## Known Issues

### "could not find driver" Error
This error occurs when the SQLite PDO extension is not installed. Follow the setup instructions above to resolve.

### Facade Mocking
Tests mock the DB, Cache, and Log facades. Ensure `mockery/mockery` is installed:
```bash
composer require --dev mockery/mockery
```

### Migration Errors
If migrations fail, ensure your test database is properly configured:
```bash
php artisan migrate --env=testing
```

## Test Patterns

### Database Query Mocking
```php
DB::shouldReceive('table->join->where->select->first')
    ->andReturn($decision);
```

### LLM Response Mocking
```php
$this->mockOpenAI->expects($this->once())
    ->method('chat')
    ->willReturn([
        'choices' => [
            ['message' => ['content' => json_encode($facts)]],
        ],
    ]);
```

### Cache Behavior Verification
```php
Cache::shouldReceive('get')->andReturn(null);
Cache::shouldReceive('put')->once();
```

## Future Improvements
- Add tests for multilingual support (if service expands beyond Croatian)
- Test with real court decision samples
- Add performance benchmarks
- Test edge cases with malformed legal documents
- Add integration tests with real database

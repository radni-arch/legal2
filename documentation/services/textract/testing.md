# Textract Testing Guide

Comprehensive guide for testing the Textract OCR pipeline in the AI Legal War Machine application.

## Table of Contents

- [Overview](#overview)
- [Test Suites](#test-suites)
- [Mock Data Generator](#mock-data-generator)
- [Running Tests](#running-tests)
- [Writing Tests](#writing-tests)
- [Best Practices](#best-practices)
- [Troubleshooting](#troubleshooting)

## Overview

The Textract testing infrastructure provides comprehensive test coverage for the OCR processing pipeline, including:

- **Unit Tests**: Test individual components in isolation
- **Feature Tests**: Test complete workflows and integrations
- **Error Scenario Tests**: Test failure modes and error handling
- **Performance Tests**: Benchmark processing speed and resource usage
- **Mock Data Generator**: Create realistic test data

## Test Suites

### 1. ListDrivePdfsTest

**Location**: `tests/Feature/ListDrivePdfsTest.php`

Tests the Google Drive PDF listing functionality.

```bash
php artisan test tests/Feature/ListDrivePdfsTest.php
```

**Coverage**:
- Core functionality (listing PDFs, handling empty folders, filtering)
- Data handling (file attributes, metadata, pagination)
- Integration tests (Google Drive service interaction)
- Architecture validation (Action pattern, dependency injection)

**Test Count**: 19 tests, 44 assertions

### 2. ProcessDrivePdfTest

**Location**: `tests/Feature/ProcessDrivePdfTest.php`

Tests the complete PDF processing pipeline orchestration.

```bash
php artisan test tests/Feature/ProcessDrivePdfTest.php
```

**Coverage**:
- Pipeline orchestration (all 12 steps validated)
- Text extraction from OCR results
- Job creation and status management
- Error handling and logging
- Edge cases (empty documents, large files)

**Test Count**: 24 tests

### 3. TextractErrorScenariosTest

**Location**: `tests/Feature/TextractErrorScenariosTest.php`

Tests comprehensive error scenarios and failure modes.

```bash
php artisan test tests/Feature/TextractErrorScenariosTest.php
```

**Coverage**:
- AWS Textract API failures
- Google Drive download errors
- S3 upload/download failures
- Network timeouts and connectivity issues
- Invalid file formats and encrypted PDFs
- Rate limiting and throttling
- Insufficient permissions
- Resource exhaustion (disk, memory)
- Database failures
- Concurrent processing conflicts

**Test Count**: 25 tests

### 4. TextractPerformanceTest

**Location**: `tests/Feature/TextractPerformanceTest.php`

Benchmarks processing performance and resource usage.

```bash
php artisan test tests/Feature/TextractPerformanceTest.php
```

**Coverage**:
- Processing time benchmarks
- Memory usage monitoring
- Database query optimization
- Batch processing efficiency
- Concurrent processing safety
- Resource cleanup verification
- Scalability testing

**Test Count**: 20 tests

**Performance Benchmarks**:
- Single file processing: < 5 seconds
- Batch of 10 files: < 30 seconds
- Memory usage: < 50MB increase
- Database queries: < 20 per job
- Large document (100 pages): < 10 seconds

### 5. TextractMockDataGeneratorTest

**Location**: `tests/Unit/TextractMockDataGeneratorTest.php`

Tests the mock data generator itself.

```bash
php artisan test tests/Unit/TextractMockDataGeneratorTest.php
```

**Coverage**: Validates all generator methods produce valid, realistic data

## Mock Data Generator

The `TextractMockDataGenerator` class provides factory methods for creating realistic test data.

### Installation

The generator is located at `app/Testing/TextractMockDataGenerator.php` and is automatically available in tests.

### Basic Usage

```php
use App\Testing\TextractMockDataGenerator;

class MyTest extends TestCase
{
    protected TextractMockDataGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new TextractMockDataGenerator();
    }

    public function test_example(): void
    {
        // Create a basic job
        $job = $this->generator->createTextractJob();

        // Create a succeeded job with content
        $job = $this->generator->createSucceededJob(pageCount: 10);

        // Create OCR document
        $ocrDoc = $this->generator->createOcrDocument(pages: 5);
    }
}
```

### Available Generator Methods

#### TextractJob Models

```php
// Basic job with defaults
$job = $generator->createTextractJob();

// Job with overrides
$job = $generator->createTextractJob([
    'status' => 'processing',
    'drive_file_name' => 'custom.pdf',
]);

// Succeeded job with extracted content
$job = $generator->createSucceededJob(pageCount: 10);

// Failed job with error
$job = $generator->createFailedJob('Processing failed');

// Processing job
$job = $generator->createProcessingJob();

// Manually edited job
$job = $generator->createEditedJob(userId: 123);

// Batch of jobs
$jobs = $generator->createJobBatch(count: 10, status: 'pending');
```

#### OCR Documents

```php
// Standard OCR document
$doc = $generator->createOcrDocument(
    pageCount: 5,
    linesPerPage: 30,
    includeBlocks: true
);

// Large document for performance testing
$doc = $generator->createLargeOcrDocument(
    pageCount: 100,
    linesPerPage: 50
);

// Low confidence OCR (poor quality scan)
$doc = $generator->createLowConfidenceOcrDocument(pageCount: 5);

// Custom content
$doc = $generator->createOcrDocumentWithContent([
    ['Line 1 of page 1', 'Line 2 of page 1'],
    ['Line 1 of page 2', 'Line 2 of page 2'],
]);

// Single page
$page = $generator->createOcrPage(
    pageNumber: 1,
    lineCount: 30,
    includeBlocks: true
);
```

#### AWS Textract API Responses

```php
// Successful response
$response = $generator->createTextractApiResponse(
    status: 'SUCCEEDED',
    pageCount: 5
);

// Failed response
$response = $generator->createTextractApiResponse(
    status: 'FAILED',
    pageCount: 0
);

// Start job response
$response = $generator->createStartJobResponse();

// Get result response
$response = $generator->createGetResultResponse(pageCount: 10);

// Error response
$response = $generator->createTextractErrorResponse(
    errorCode: 'ThrottlingException',
    errorMessage: 'Rate limit exceeded'
);
```

#### Google Drive Files

```php
// List of files
$files = $generator->createDriveFileList(fileCount: 10);

// Single file
$file = $generator->createDriveFile();

// File with overrides
$file = $generator->createDriveFile([
    'name' => 'custom.pdf',
    'size' => 123456,
]);
```

#### Pipeline Data

```php
// Pipeline payload
$payload = $generator->createPipelinePayload([
    'forceTextract' => true,
]);

// Complete pipeline result
$result = $generator->createPipelineResult(pageCount: 5);
// Returns: ['job' => ..., 'ocrDocument' => ..., 's3Key' => ..., ...]
```

#### Performance Metrics

```php
$metrics = $generator->createPerformanceMetrics();
// Returns array with timing and resource usage data
```

## Running Tests

### Run All Textract Tests

```bash
php artisan test --testsuite=Feature --filter=Textract
```

### Run Specific Test Suite

```bash
# Error scenarios
php artisan test tests/Feature/TextractErrorScenariosTest.php

# Performance tests
php artisan test tests/Feature/TextractPerformanceTest.php

# List PDFs tests
php artisan test tests/Feature/ListDrivePdfsTest.php

# Process PDF tests
php artisan test tests/Feature/ProcessDrivePdfTest.php

# Mock data generator tests
php artisan test tests/Unit/TextractMockDataGeneratorTest.php
```

### Run Specific Test Method

```bash
php artisan test --filter=it_handles_pipeline_failure
```

### Run with Coverage

```bash
php artisan test --coverage --min=80
```

### Run in Parallel

```bash
php artisan test --parallel
```

## Writing Tests

### Test Structure

All Textract tests follow this structure:

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Testing\TextractMockDataGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class MyTextractTest extends TestCase
{
    use RefreshDatabase;

    protected TextractMockDataGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new TextractMockDataGenerator();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * @test
     */
    public function it_does_something(): void
    {
        // Arrange
        $job = $this->generator->createTextractJob();

        // Act
        $result = someAction($job);

        // Assert
        $this->assertSomething($result);
    }
}
```

### Mocking External Services

#### Mock Google Drive Service

```php
use App\Services\GoogleDriveService;
use Mockery;

$driveMock = Mockery::mock(GoogleDriveService::class);
$driveMock->shouldReceive('listPdfsInFolder')
    ->once()
    ->with('folder-123')
    ->andReturn($this->generator->createDriveFileList(10));

$this->app->instance(GoogleDriveService::class, $driveMock);
```

#### Mock Pipeline

```php
use Illuminate\Pipeline\Pipeline;
use Mockery;

$pipelineMock = Mockery::mock(Pipeline::class);
$pipelineMock->shouldReceive('send')->andReturnSelf();
$pipelineMock->shouldReceive('through')->andReturnSelf();
$pipelineMock->shouldReceive('thenReturn')
    ->andReturn($this->generator->createPipelineResult());

$this->app->instance(Pipeline::class, $pipelineMock);
```

### Testing Error Scenarios

```php
/**
 * @test
 */
public function it_handles_network_timeout(): void
{
    $pipelineMock = Mockery::mock(Pipeline::class);
    $pipelineMock->shouldReceive('send')->andReturnSelf();
    $pipelineMock->shouldReceive('through')->andReturnSelf();
    $pipelineMock->shouldReceive('thenReturn')
        ->andThrow(new \Exception('Connection timeout'));

    $this->app->instance(Pipeline::class, $pipelineMock);

    $action = new ProcessDrivePdf();

    try {
        $action->handle('file-id', 'file.pdf');
        $this->fail('Expected exception was not thrown');
    } catch (\Exception $e) {
        $this->assertStringContainsString('timeout', $e->getMessage());
    }

    $job = TextractJob::where('drive_file_id', 'file-id')->first();
    $this->assertEquals('failed', $job->status);
}
```

### Testing Performance

```php
/**
 * @test
 */
public function it_processes_files_efficiently(): void
{
    $pipelineMock = $this->createSuccessfulPipelineMock();
    $this->app->instance(Pipeline::class, $pipelineMock);

    $action = new ProcessDrivePdf();

    $startTime = microtime(true);
    $action->handle('file-id', 'file.pdf');
    $duration = microtime(true) - $startTime;

    $this->assertLessThan(5.0, $duration, 'Should complete in under 5 seconds');
}
```

## Best Practices

### 1. Use the Mock Data Generator

**DO**:
```php
$job = $this->generator->createSucceededJob(10);
```

**DON'T**:
```php
$job = TextractJob::create([
    'drive_file_id' => 'test-123',
    'drive_file_name' => 'test.pdf',
    // ... manually creating all fields
]);
```

### 2. Isolate External Dependencies

Always mock external services (AWS, Google Drive) to ensure tests are:
- Fast (no network calls)
- Reliable (no external dependencies)
- Deterministic (consistent results)

### 3. Use RefreshDatabase Trait

```php
class MyTest extends TestCase
{
    use RefreshDatabase;
}
```

This ensures each test starts with a clean database state.

### 4. Clean Up Mocks

```php
protected function tearDown(): void
{
    Mockery::close();
    parent::tearDown();
}
```

### 5. Test One Thing Per Test

Each test should verify a single behavior:

**GOOD**:
```php
public function it_creates_job_in_pending_status(): void
public function it_updates_job_to_succeeded_after_processing(): void
public function it_stores_extracted_content(): void
```

**BAD**:
```php
public function it_processes_everything(): void
{
    // Tests job creation, processing, content storage, etc.
}
```

### 6. Use Descriptive Test Names

Use the `it_does_something` naming convention:

```php
public function it_handles_network_timeout(): void
public function it_sanitizes_malformed_ocr_data(): void
public function it_retries_failed_jobs_up_to_three_times(): void
```

### 7. Provide Context in Assertions

```php
// Good
$this->assertLessThan(5.0, $duration, 'Processing should complete in under 5 seconds');

// Less helpful
$this->assertLessThan(5.0, $duration);
```

## Troubleshooting

### SQLite Driver Missing

**Error**: `could not find driver (Connection: sqlite)`

**Solution**: Install the SQLite PDO extension:

```bash
# Ubuntu/Debian
sudo apt-get install php-sqlite3

# macOS
brew install php@8.2
# SQLite is included by default

# Verify installation
php -m | grep pdo_sqlite
```

### Memory Exhaustion

**Error**: `Allowed memory size exhausted`

**Solution**: Increase PHP memory limit for tests:

```bash
php -d memory_limit=512M artisan test
```

Or update `phpunit.xml`:

```xml
<php>
    <ini name="memory_limit" value="512M"/>
</php>
```

### Tests Running Slowly

**Causes**:
- Not mocking external services
- Creating too much test data
- Not using database transactions

**Solutions**:
- Mock all external APIs (AWS, Google Drive)
- Use `RefreshDatabase` trait with transactions
- Create minimal test data needed for each test
- Run tests in parallel: `php artisan test --parallel`

### Mockery Expectations Not Met

**Error**: `Mockery\Exception\InvalidCountException: Method X should be called 1 times`

**Solution**: Ensure `Mockery::close()` is called in `tearDown()`:

```php
protected function tearDown(): void
{
    Mockery::close();
    parent::tearDown();
}
```

### Test Database Not Resetting

**Problem**: Tests affecting each other

**Solution**: Ensure `RefreshDatabase` trait is used:

```php
use Illuminate\Foundation\Testing\RefreshDatabase;

class MyTest extends TestCase
{
    use RefreshDatabase;
}
```

### Cannot Find TextractMockDataGenerator

**Error**: `Class 'App\Testing\TextractMockDataGenerator' not found`

**Solution**: Ensure the generator is in the correct location:

```bash
# Verify file exists
ls -la app/Testing/TextractMockDataGenerator.php

# Regenerate autoload files
composer dump-autoload
```

## Test Coverage Goals

- **Unit Tests**: 90%+ coverage
- **Feature Tests**: 80%+ coverage
- **Error Scenarios**: All critical failure modes covered
- **Performance**: All key operations benchmarked

## Continuous Integration

### GitHub Actions Example

```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v2

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: pdo_sqlite, mbstring

      - name: Install Dependencies
        run: composer install

      - name: Run Tests
        run: php artisan test --coverage --min=80
```

## Additional Resources

- [Laravel Testing Documentation](https://laravel.com/docs/testing)
- [Mockery Documentation](http://docs.mockery.io/)
- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [Pipeline Documentation](./TEXTRACT_PIPELINE.md)
- [Security Documentation](./SECURITY.md)

## Contributing

When adding new tests:

1. Follow the existing test structure
2. Use the mock data generator
3. Include descriptive test names and docblocks
4. Add performance benchmarks for new features
5. Document any new testing patterns in this guide
6. Ensure tests are isolated and deterministic

## Questions?

For questions about testing, please:

1. Check this documentation
2. Review existing test examples
3. Check the Laravel testing docs
4. Open an issue on GitHub

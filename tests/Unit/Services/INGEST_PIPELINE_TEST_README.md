# IngestPipelineService Test Suite

## Overview
This test suite provides comprehensive coverage for the `IngestPipelineService`, which handles:
- Text chunking and ingestion
- File ingestion (including PDF OCR)
- Batch document ingestion with embeddings
- Vector similarity search

## Test Coverage
- **48 tests** covering all public methods and edge cases
- Text chunking with various parameters and overlaps
- Text and file ingestion workflows
- Document batch processing with embeddings
- Vector search with PostgreSQL and fallback implementations
- Error handling and validation

## Database Requirements

**IMPORTANT**: These tests require database access because they interact with the `AgentVectorMemory` model.

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
# Run all IngestPipelineService tests
vendor/bin/phpunit tests/Unit/Services/IngestPipelineServiceTest.php

# Or use artisan
php artisan test --filter=IngestPipelineServiceTest
```

## Test Structure

### Text Chunking Tests (7 tests)
- Default parameters
- Custom chunk sizes
- Overlap handling
- Empty text handling
- Short text handling
- Whitespace trimming

### Ingest Text Tests (4 tests)
- Successful ingestion
- Empty text handling
- Custom chunk options
- Custom model selection

### Ingest File Tests (6 tests)
- Plain text file ingestion
- PDF ingestion with OCR
- OCR fallback handling
- Empty file handling
- Nonexistent file handling

### Ingest Documents Tests (11 tests)
- Batch ingestion with embeddings
- Empty document filtering
- Duplicate detection by content hash
- Embedding count mismatch handling
- Metadata JSON storage
- Embedding norm calculation
- Token count estimation
- Chunk index tracking

### Search Tests (10 tests)
- Vector similarity search
- Result limiting
- Missing embedding handling
- Zero norm query handling
- Namespace filtering
- Search without namespace
- Result metadata inclusion
- Result sorting by similarity
- Missing embeddings in search

## Known Issues

### "could not find driver" Error
This error occurs when the SQLite PDO extension is not installed. Follow the setup instructions above to resolve.

### Model Not Found
If you see "Class 'App\Models\AgentVectorMemory' not found", ensure:
1. The model file exists at `app/Models/AgentVectorMemory.php`
2. You've run composer autoload dump: `composer dump-autoload`

### Migration Errors
If migrations fail, ensure your test database is properly configured and migrations have run:
```bash
php artisan migrate --env=testing
```

## Test Dependencies
- OpenAIService (mocked)
- OcrService (mocked)
- AgentVectorMemory model
- Laravel database migrations

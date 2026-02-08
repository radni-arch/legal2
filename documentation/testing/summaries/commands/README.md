# Commands Test Summaries

Test documentation for Artisan CLI commands and background tasks.

---

## Components Covered

### EKOM Sync Commands
**Namespace**: `app/Console/Commands/Ekom/`

Commands for syncing data from Croatian e-courts system (EKOM):
- `ekom:sync-predmeti` - Sync case records (predmeti)
- `ekom:sync-podnesci` - Sync case submissions (podnesci)
- `ekom:sync-otpravci` - Sync dispatches (otpravci)

**Key Capabilities**:
- Incremental sync (only new/updated records)
- Full sync (--full flag)
- Progress indicators
- Error recovery
- Rate limiting

---

### Eoglasna Monitoring Commands
**Namespace**: `app/Console/Commands/Eoglasna/`

Commands for monitoring public court notices:
- `eoglasna:watch` - Monitor all courts
- `eoglasna:watch-osijek` - Monitor Osijek courts specifically
- `eoglasna:notify` - Send notifications for keyword matches

**Key Capabilities**:
- Keyword-based monitoring
- Court filtering
- Email notifications
- Database persistence
- Duplicate detection

---

### Textract Processing Commands
**Namespace**: `app/Console/Commands/Textract/`

Commands for AWS Textract OCR processing:
- `textract:process-drive-folder` - Process all PDFs in Google Drive folder
- `textract:retry-failed` - Retry failed Textract jobs
- `textract:check-status` - Check Textract job status

**Key Capabilities**:
- Batch PDF processing
- Job queue management
- Status monitoring
- Error retry logic
- Cost tracking

---

### Graph Database Commands
**Namespace**: `app/Console/Commands/Graph/`

Commands for Neo4j graph database operations:
- `graph:query` - Execute Cypher queries
- `graph:stats` - Show graph statistics
- `graph:sync` - Sync data to graph
- `graph:clear` - Clear graph database

**Key Capabilities**:
- Interactive Cypher execution
- Node/relationship counts
- Schema visualization
- Bulk import/export

---

### Cache Commands
**Namespace**: `app/Console/Commands/Cache/`

Commands for cache management:
- `cache:warm` - Pre-populate cache with frequently accessed data
- `cache:monitor` - Monitor cache hit/miss rates
- `cache:invalidate` - Invalidate specific cache keys

**Key Capabilities**:
- Strategic cache warming
- Real-time metrics
- Pattern-based invalidation
- Performance monitoring

---

## Test Coverage Areas

### Unit Tests

**Command Execution**:
- ✅ Command runs without errors
- ✅ Required arguments validated
- ✅ Optional flags work correctly
- ✅ Exit codes (0 for success, non-zero for errors)

**Output**:
- ✅ Progress indicators display
- ✅ Success messages
- ✅ Error messages
- ✅ Help text formatting

**Validation**:
- ✅ Invalid arguments rejected
- ✅ Missing required arguments detected
- ✅ Type validation (integers, dates, etc.)

---

### Integration Tests

**Full Workflow**:
- ✅ Command → Service → Database
- ✅ External API integration (EKOM, Textract)
- ✅ Queue job dispatch
- ✅ Transaction handling

**Error Scenarios**:
- ✅ API connection failures
- ✅ Database errors
- ✅ Invalid data handling
- ✅ Timeout handling

**Performance**:
- ✅ Large dataset processing
- ✅ Memory usage limits
- ✅ Execution time limits

---

## Running Tests

### All Command Tests
```bash
./scripts/run-tests.sh --filter=CommandTest
```

### Specific Command Category
```bash
# EKOM commands
./scripts/run-tests.sh --filter=EkomCommandTest

# Eoglasna commands
./scripts/run-tests.sh --filter=EoglasnaCommandTest

# Textract commands
./scripts/run-tests.sh --filter=TextractCommandTest

# Graph commands
./scripts/run-tests.sh --filter=GraphCommandTest

# Cache commands
./scripts/run-tests.sh --filter=CacheCommandTest
```

### Manual Testing
```bash
# Test EKOM sync
php artisan ekom:sync-predmeti --limit=5

# Test Eoglasna monitoring
php artisan eoglasna:watch-osijek --dry-run

# Test Textract processing
php artisan textract:process-drive-folder FOLDER_ID --limit=1

# Test graph query
php artisan graph:query "MATCH (n) RETURN count(n)"

# Test cache warming
php artisan cache:warm --verbose
```

---

## Test Summaries

Detailed test summaries for command categories:

- ✅ [EKOM Commands](ekom.md) - Croatian e-courts system sync commands
- ✅ [Eoglasna Commands](eoglasna.md) - Public court notices monitoring
- ✅ [Graph Commands](graph.md) - Neo4j graph database operations
- ✅ [Metadata Commands](metadata.md) - Document metadata extraction and management
- [ ] Textract commands (to be added)
- [ ] Cache commands (to be added)

---

## Known Issues

*(Document any known issues, flaky tests, or technical debt here)*

---

**Last Updated**: 2025-11-09

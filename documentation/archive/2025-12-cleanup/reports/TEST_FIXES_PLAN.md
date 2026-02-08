# Test Failures - Comprehensive Fix Plan

## Executive Summary

After running the parallel test suite, we identified **3 major categories of failures** affecting 670+ tests:

1. **Unit Tests**: Fatal error preventing all unit tests from running (100% failure)
2. **Integration Tests**: PostgreSQL permission issues (299 failed, 24 passed, 5 skipped)
3. **Dusk Tests**: Same PostgreSQL permission issues (371 failed, 10 passed)

## Root Cause Analysis

### 1. Unit Tests Fatal Error
**Issue**: Method signature mismatch between interface and implementation

**Location**: `app/Services/CaseIngestPipeline.php:41`

**Error**:
```
Declaration of App\Services\CaseIngestPipeline::ingest(int $documentId, ?int $caseId = null, ?string $filePath = null, array $options = []): array
must be compatible with
App\Contracts\Ingest\CaseIngestPipelineInterface::ingest(string $caseId, string $docId, string $rawText, array $ocrBlocks = [], array $options = []): array
```

**Root Cause**:
- Interface expects: `ingest(string $caseId, string $docId, string $rawText, array $ocrBlocks = [], array $options = [])`
- Implementation has: `ingest(int $documentId, ?int $caseId = null, ?string $filePath = null, array $options = [])`
- Complete parameter mismatch in both types and order

**Impact**: All unit tests blocked, cannot run

### 2. Integration & Dusk Tests Database Error
**Issue**: PostgreSQL file permission denied

**Error**:
```
SQLSTATE[08006] [7] connection to server at "127.0.0.1", port 5432 failed:
FATAL: could not open file "global/pg_filenode.map": Permission denied
```

**Root Cause**:
- PostgreSQL server is running as user `claude` (UID: varies)
- PostgreSQL data directory (`/var/lib/postgresql/16/main/`) is owned by user `postgres` (UID: 102, GID: 104)
- File permissions: `drwx------ postgres:postgres` (700)
- Process mismatch: Server running as `claude` cannot read files owned by `postgres`

**Evidence**:
```bash
# PostgreSQL process running as 'claude'
claude    1431  0.7  0.3 229620 52136 ?  Ss   15:32   0:10 /usr/lib/postgresql/16/bin/postgres

# Data directory owned by 'postgres'
drwx------ postgres postgres /var/lib/postgresql/16/main/global/
-rw------- postgres postgres /var/lib/postgresql/16/main/global/pg_filenode.map
```

**Impact**: 670 tests failing (299 integration + 371 dusk)

## Detailed Fix Plan

### Phase 1: Fix Unit Tests Fatal Error (Priority: CRITICAL)

**Objective**: Align `CaseIngestPipeline` implementation with interface contract

**Tasks**:

#### Task 1.1: Update CaseIngestPipeline Method Signature
**File**: `app/Services/CaseIngestPipeline.php`

**Action**: Change the `ingest()` method signature to match interface

**Before**:
```php
public function ingest(
    int $documentId,
    ?int $caseId = null,
    ?string $filePath = null,
    array $options = []
): array
```

**After**:
```php
public function ingest(
    string $caseId,
    string $docId,
    string $rawText,
    array $ocrBlocks = [],
    array $options = []
): array
```

**Implementation Details**:
1. Update method signature to match interface exactly
2. Refactor method body to work with new parameters:
   - Remove `$documentId`, `$filePath` parameters
   - Add `$docId`, `$rawText`, `$ocrBlocks` parameters
3. Remove backward compatibility logic in lines 47-50 (now redundant)
4. Update internal logic to use `$rawText` directly instead of extracting from options
5. Use `$ocrBlocks` directly instead of extracting from options

#### Task 1.2: Find and Update All Callers
**Search Pattern**: `->ingest(` or `::ingest(`

**Action**:
1. Use Grep to find all callers: `grep -r "->ingest(" app/ tests/`
2. Update each caller to pass new parameter format
3. Convert `int $documentId` to `string $docId` where needed
4. Ensure `$rawText` is provided directly instead of in options
5. Ensure `$ocrBlocks` is passed as 4th parameter if available

**Expected Files to Update**:
- Controllers that trigger ingestion
- Jobs/Queues that process documents
- Services that orchestrate document processing
- Tests that mock or call this method

#### Task 1.3: Verify Interface Contract is Complete
**File**: `app/Contracts/Ingest/CaseIngestPipelineInterface.php`

**Action**: Review interface to ensure it's complete and correct

**Verification**:
- Check if method signature makes sense for use cases
- Verify all required parameters are present
- Ensure return type is appropriate
- Check if `needsReOcr()` method is also implemented correctly

**Estimated Time**: 2-3 hours
**Risk Level**: Medium (requires careful refactoring)
**Tests Fixed**: ~200+ unit tests

---

### Phase 2: Fix PostgreSQL Permissions (Priority: CRITICAL)

**Objective**: Resolve PostgreSQL file ownership/permission mismatch

**Root Cause**: PostgreSQL running as wrong user

#### Task 2.1: Stop PostgreSQL Service
```bash
# Stop current PostgreSQL instance
pkill -9 postgres

# Or use systemctl if available
systemctl stop postgresql@16-main
```

#### Task 2.2: Fix Data Directory Ownership
**Action**: Change ownership of PostgreSQL data directory to `claude` user

```bash
# Option A: Change ownership to claude (if running as claude)
chown -R claude:claude /var/lib/postgresql/16/main/

# Option B: Change ownership to postgres and run as postgres
chown -R postgres:postgres /var/lib/postgresql/16/main/
```

**Decision Required**:
- If setup script created database as `claude`, use Option A
- If using system postgres, use Option B and update startup config

#### Task 2.3: Update PostgreSQL Startup Configuration
**File**: `scripts/setup-all.sh` or PostgreSQL config

**Action**: Ensure PostgreSQL starts with correct user

**For claude user**:
```bash
# In setup script, ensure:
su - claude -c "/usr/lib/postgresql/16/bin/postgres -D /var/lib/postgresql/16/main"
```

**For postgres user**:
```bash
# In setup script, ensure:
su - postgres -c "/usr/lib/postgresql/16/bin/postgres -D /var/lib/postgresql/16/main"
```

#### Task 2.4: Restart PostgreSQL with Correct Permissions
```bash
# If using claude user:
chown -R claude:claude /var/lib/postgresql/16/main/
su - claude -c "/usr/lib/postgresql/16/bin/postgres -D /var/lib/postgresql/16/main -c config_file=/etc/postgresql/16/main/postgresql.conf" &

# If using postgres user:
chown -R postgres:postgres /var/lib/postgresql/16/main/
su - postgres -c "/usr/lib/postgresql/16/bin/postgres -D /var/lib/postgresql/16/main -c config_file=/etc/postgresql/16/main/postgresql.conf" &
```

#### Task 2.5: Verify Database Connectivity
```bash
# Test connection
psql -U claude -d laravel_test -c "SELECT 1"

# Check pg_filenode.map is readable
ls -la /var/lib/postgresql/16/main/global/pg_filenode.map
```

#### Task 2.6: Update setup-all.sh Script
**File**: `scripts/setup-all.sh`

**Action**: Add proper ownership fix in setup script

**Add after PostgreSQL installation**:
```bash
# Ensure PostgreSQL data directory has correct ownership
if [ "$USER" = "claude" ]; then
    chown -R claude:claude /var/lib/postgresql/16/main/
elif [ "$USER" = "root" ]; then
    chown -R postgres:postgres /var/lib/postgresql/16/main/
fi
```

**Estimated Time**: 30-60 minutes
**Risk Level**: Low (straightforward permission fix)
**Tests Fixed**: ~670 tests (299 integration + 371 dusk)

---

### Phase 3: Re-run Tests and Validation

#### Task 3.1: Run Unit Tests Only
```bash
./scripts/run-all-tests-parallel.sh
# Or specifically:
php artisan test --testsuite=Unit
```

**Expected Outcome**:
- All unit tests should run without fatal error
- Individual test failures may exist but no blocking errors

#### Task 3.2: Run Integration Tests
```bash
php artisan test --testsuite=Integration
```

**Expected Outcome**:
- Database connection successful
- Tests execute and interact with database
- Individual test failures should be business logic issues, not infrastructure

#### Task 3.3: Run Dusk Tests
```bash
php artisan dusk
```

**Expected Outcome**:
- Browser tests connect to database
- Authentication tests pass
- Individual failures are UI/functionality issues

#### Task 3.4: Run Full Parallel Suite
```bash
./scripts/run-all-tests-parallel.sh
```

**Expected Outcome**:
- All suites run simultaneously
- No blocking fatal errors
- Reduction in failure count from 670+ to <100

---

## Phase 4: Fix Remaining Test Failures (Priority: HIGH)

After fixing infrastructure issues, address remaining failures:

### Category A: Test-Specific Issues

#### GraphQL Configuration Warnings
**Error**: `GraphQL warm-up introspection failed: GraphQL endpoint is not configured`

**Fix**:
- Add GraphQL endpoint configuration to test environment
- Or disable GraphQL in test configuration if not needed

**File**: `phpunit.xml` or `.env.testing`
```xml
<env name="GRAPHQL_ENABLED" value="false"/>
```

#### PHPUnit Metadata Deprecation Warnings
**Warning**: `Metadata in doc-comments is deprecated and will no longer be supported in PHPUnit 12`

**Fix**: Convert doc-comment annotations to PHP attributes

**Example**:
```php
// Before (deprecated):
/**
 * @test
 */
public function it_does_something() {}

// After (using attributes):
#[Test]
public function it_does_something() {}
```

**Estimated**: 200+ test methods to update
**Tool**: Can be automated with script or refactoring tool

---

## Success Criteria

### Phase 1 Success:
- [x] CaseIngestPipeline signature matches interface
- [x] All callers updated
- [x] Unit tests run without fatal error
- [x] At least 50% of unit tests pass

### Phase 2 Success:
- [x] PostgreSQL runs without permission errors
- [x] Database connections succeed in tests
- [x] `pg_filenode.map` accessible to PostgreSQL process
- [x] Integration and Dusk tests execute

### Phase 3 Success:
- [x] Full test suite runs to completion
- [x] Less than 100 test failures total
- [x] No infrastructure/configuration failures
- [x] All failures are business logic related

### Phase 4 Success:
- [x] All deprecation warnings resolved
- [x] GraphQL configuration warnings fixed
- [x] 95%+ test pass rate

---

## Execution Order

1. **FIRST**: Fix PostgreSQL permissions (Phase 2) - Unblocks 670 tests
2. **SECOND**: Fix CaseIngestPipeline (Phase 1) - Unblocks 200+ unit tests
3. **THIRD**: Validate with test runs (Phase 3)
4. **FOURTH**: Address remaining failures (Phase 4)

**Rationale**: Fix database first as it affects most tests (670 vs 200)

---

## Risk Assessment

### High Risk Items:
- **CaseIngestPipeline refactoring**: May have many callers across codebase
- **Mitigation**: Thorough grep search, update systematically

### Medium Risk Items:
- **PostgreSQL ownership change**: Could affect production if not careful
- **Mitigation**: Test in development environment first, backup data

### Low Risk Items:
- **GraphQL config**: Simple configuration change
- **PHPUnit attributes**: Backward compatible, can be done incrementally

---

## Timeline Estimate

- **Phase 1 (CaseIngestPipeline)**: 2-3 hours
- **Phase 2 (PostgreSQL Permissions)**: 30-60 minutes
- **Phase 3 (Validation)**: 1 hour (test runs)
- **Phase 4 (Remaining Fixes)**: 4-6 hours

**Total Estimated Time**: 8-11 hours

**Priority Fixes (Phase 1 + 2)**: 3-4 hours to unblock 870+ tests

---

## Additional Notes

### Future Improvements:
1. Add database permission checks to setup script
2. Add test suite health check script
3. Implement pre-commit hooks to catch signature mismatches
4. Add CI/CD pipeline to run tests automatically
5. Create test database reset script for faster iteration

### Technical Debt Identified:
1. Backward compatibility code in CaseIngestPipeline (lines 47-50)
2. PHPUnit doc-comment metadata usage (deprecated)
3. GraphQL endpoint configuration missing
4. Inconsistent user/permission management in PostgreSQL setup

---

## Appendix: Key File Locations

### Files to Modify:
- `app/Services/CaseIngestPipeline.php` - Fix method signature
- `app/Contracts/Ingest/CaseIngestPipelineInterface.php` - Review interface
- `scripts/setup-all.sh` - Add ownership fix
- `phpunit.xml` - Add GraphQL config
- Multiple test files - Convert to attributes (optional)

### Files to Reference:
- `./storage/logs/tests/*.log` - Test failure logs
- `/var/lib/postgresql/16/main/` - PostgreSQL data directory
- `/etc/postgresql/16/main/postgresql.conf` - PostgreSQL config

### Commands for Investigation:
```bash
# Find all ingest() callers
grep -rn "->ingest(" app/ tests/

# Check PostgreSQL process
ps aux | grep postgres

# Check file ownership
ls -la /var/lib/postgresql/16/main/global/pg_filenode.map

# Test database connection
psql -U claude -d laravel_test -c "SELECT 1"
```

# Migration Guide: Phase 2 Refactoring

**Last Updated**: 2025-01-05
**Status**: Active Migration in Progress

This guide documents how to migrate from old services to new refactored services following the Phase 2 refactoring initiative.

---

## Table of Contents

- [Overview](#overview)
- [Graph Services Migration](#graph-services-migration)
- [Search Services Migration](#search-services-migration)
- [Step-by-Step Migration](#step-by-step-migration)
- [Testing Your Migration](#testing-your-migration)
- [Common Issues](#common-issues)
- [FAQ](#faq)

---

## Overview

Phase 2 refactoring affected two major subsystems:

1. **Graph Services** - Neo4j synchronization and RAG operations
2. **Search Services** - Multi-corpus search functionality

### What Changed?

**Graph Services**:
- ❌ **OLD**: `App\Services\GraphRagService` (monolithic)
- ✅ **NEW**: `App\Services\Graph\GraphRagOrchestrator` (orchestrator pattern)
- ✅ **NEW**: Specialized sync services (CaseGraphSyncService, TextractGraphSyncService, etc.)

**Search Services**:
- ⚠️ `App\Services\UnifiedSearchService` - **STILL ACTIVE** (not deprecated)
- ℹ️ `App\Services\Search\SearchOrchestrator` - Available but not required for migration yet

### Migration Timeline

- **Phase 1-3** (COMPLETE): Infrastructure and backward compatibility
- **Phase 4** (CURRENT): Documentation and developer awareness
- **Phase 5** (IN PROGRESS): Code quality improvements
- **Phase 6** (FUTURE): Remove backward compatibility wrappers

---

## Graph Services Migration

### GraphRagService → GraphRagOrchestrator

The old `GraphRagService` has been replaced with `GraphRagOrchestrator` and specialized sync services.

#### Before (Deprecated - Still Works)

```php
<?php

use App\Services\GraphRagService;

class MyController
{
    public function sync(GraphRagService $graphRag)
    {
        // ⚠️ Deprecated - logs warning
        $graphRag->syncLaw($lawId);
        $graphRag->syncCase($caseDocId);
        $graphRag->syncCourtDecision($decisionId);
        $graphRag->syncTextract($textractDocId);
    }
}
```

**What Happens**: Code continues to work, but logs deprecation warnings like:
```
GraphRagService::syncLaw() is deprecated. Use GraphRagOrchestrator::syncLaw() instead
```

#### After (Recommended)

```php
<?php

use App\Services\Graph\GraphRagOrchestrator;

class MyController
{
    public function sync(GraphRagOrchestrator $orchestrator)
    {
        // ✅ Recommended - no warnings
        $orchestrator->syncLaw($lawId);
        $orchestrator->syncCase($caseDocId);
        $orchestrator->syncCourtDecision($decisionId);
        $orchestrator->syncTextract($textractDocId);
    }
}
```

### Using Specialized Sync Services

For more granular control, you can use specialized sync services directly:

```php
<?php

use App\Services\Graph\CaseGraphSyncService;
use App\Services\Graph\TextractGraphSyncService;

class MyService
{
    public function __construct(
        protected CaseGraphSyncService $caseSync,
        protected TextractGraphSyncService $textractSync
    ) {}

    public function syncMyDocuments()
    {
        // Sync case document
        $this->caseSync->sync($caseDocId);

        // Batch sync textract documents
        $results = $this->textractSync->syncBatch($textractDocIds);

        // Remove document from graph
        $this->textractSync->unsync($oldDocId);
    }
}
```

### Import Statement Updates

**OLD Imports** (deprecated):
```php
use App\Services\GraphRagService;
```

**NEW Imports** (recommended):
```php
use App\Services\Graph\GraphRagOrchestrator;
use App\Services\Graph\CaseGraphSyncService;
use App\Services\Graph\TextractGraphSyncService;
use App\Services\Graph\GraphKeywordLinker;
use App\Services\Graph\GraphCitationLinker;
use App\Services\Graph\GraphSimilarityLinker;
```

### Service Container Resolution

**OLD** (still works):
```php
$graphRag = app(\App\Services\GraphRagService::class);
$graphRag->syncLaw($lawId);
```

**NEW** (recommended):
```php
$orchestrator = app(\App\Services\Graph\GraphRagOrchestrator::class);
$orchestrator->syncLaw($lawId);
```

**Direct Service Resolution**:
```php
$caseSync = app(\App\Services\Graph\CaseGraphSyncService::class);
$caseSync->sync($caseDocId);
```

### Method Mapping

All methods from `GraphRagService` are available in `GraphRagOrchestrator`:

| Old Method | New Method | Notes |
|------------|------------|-------|
| `syncLaw($id)` | `syncLaw($id)` | Unchanged |
| `syncCase($id)` | `syncCase($id)` | Unchanged |
| `syncCourtDecision($id)` | `syncCourtDecision($id)` | Unchanged |
| `syncTextract($id)` | `syncTextract($id)` | **NEW** - was syncTextractJob() |
| `syncTextractJob($id)` | `syncTextractJob($id)` | Still available |
| `syncDocument($type, $id)` | `syncDocument($type, $id)` | Unchanged |
| `syncAllLaws()` | `syncAllLaws()` | Unchanged |
| `syncAllCases()` | `syncAllCases()` | Unchanged |
| `syncAllCourtDecisions()` | `syncAllCourtDecisions()` | Unchanged |
| `syncAllTextractJobs()` | `syncAllTextractJobs()` | Unchanged |

---

## Search Services Migration

### Current Status: UnifiedSearchService is ACTIVE (Refactored Internally)

**IMPORTANT**: `UnifiedSearchService` is **NOT deprecated** and continues to be the recommended service for search operations.

#### What Changed: Internal Refactoring ✅

UnifiedSearchService was **internally refactored** to delegate to specialized services while maintaining its complete public API. **All 30+ methods still work**, with zero breaking changes.

**Benefits**:
- ✅ Better code organization
- ✅ Services independently testable (106 tests)
- ✅ Services can be reused elsewhere
- ✅ **ALL functionality preserved**
- ✅ **Zero breaking changes**

#### Why Internal Refactoring Was Chosen

1. **ALL Functionality Preserved**: Every method (search, hybridSearch, searchWithCitations, etc.) still works
2. **Zero Risk**: No breaking changes to existing code
3. **Better Organization**: Delegates to modular services with single responsibilities
4. **Fully Tested**: 106 unit tests ensure correctness
5. **Production Ready**: Safe for immediate deployment

#### Current Recommended Usage

```php
<?php

use App\Services\UnifiedSearchService;

class SearchController
{
    public function search(UnifiedSearchService $search, Request $request)
    {
        // ✅ CORRECT - This is the recommended approach
        $results = $search->search($request->query('q'), [
            'corpora' => ['laws', 'decisions', 'cases'],
            'threshold' => 0.7,
            'limit' => 10,
        ]);

        return response()->json($results);
    }
}
```

#### SearchOrchestrator (Optional - Not Required)

`SearchOrchestrator` exists for those who want to use the newer architecture:

```php
<?php

use App\Services\Search\SearchOrchestrator;

class SearchController
{
    public function search(SearchOrchestrator $orchestrator, Request $request)
    {
        // ℹ️ OPTIONAL - Newer architecture, less battle-tested
        $results = $orchestrator->search($request->query('q'), [
            'corpora' => ['laws', 'cases'], // 'decisions' not yet fully supported
            'deduplicate' => true,
            'per_page' => 10,
        ]);

        return response()->json($results);
    }
}
```

#### Future Migration Path for UnifiedSearchService

The proper migration should be **phased**:

1. **Phase 1**: Internal refactor - make UnifiedSearchService use SearchOrchestrator internally
2. **Phase 2**: Update controllers to use SearchOrchestrator directly
3. **Phase 3**: Convert UnifiedSearchService to thin wrapper (like GraphRagService)
4. **Phase 4**: Remove wrapper entirely

**Current Phase**: Still on UnifiedSearchService (no migration needed)

---

## Step-by-Step Migration

### 1. Identify Usage

Find all usages of deprecated services:

```bash
# Find GraphRagService usage
grep -r "GraphRagService" app/ --include="*.php"

# Find old import patterns
grep -r "use App\\\\Services\\\\GraphRagService" app/ --include="*.php"
```

### 2. Update Imports

Replace old imports with new ones:

```php
// Before
use App\Services\GraphRagService;

// After
use App\Services\Graph\GraphRagOrchestrator;
```

### 3. Update Type Hints

Update constructor and method parameter type hints:

```php
// Before
public function __construct(GraphRagService $graphRag)
{
    $this->graphRag = $graphRag;
}

// After
public function __construct(GraphRagOrchestrator $orchestrator)
{
    $this->orchestrator = $orchestrator;
}
```

### 4. Update Method Calls

Method calls remain the same, only the service changes:

```php
// Before
$this->graphRag->syncLaw($lawId);

// After
$this->orchestrator->syncLaw($lawId);
```

### 5. Update Tests

Update test mocks and type hints:

```php
// Before
$graphRagMock = Mockery::mock(GraphRagService::class);

// After
$orchestratorMock = Mockery::mock(GraphRagOrchestrator::class);
```

### 6. Run Tests

Verify your changes with tests:

```bash
# Run specific test
./vendor/bin/phpunit --filter=MyTest

# Run all tests
composer test
```

---

## Testing Your Migration

### 1. Unit Tests

Ensure your unit tests pass:

```bash
composer test:unit
```

### 2. Feature Tests

Run feature tests to verify integration:

```bash
composer test:feature
```

### 3. Manual Testing

Test critical paths manually:

1. Sync a law document
2. Sync a case document
3. Check Neo4j graph for proper nodes/relationships

### 4. Monitor Logs

Check for deprecation warnings:

```bash
# View application logs
php artisan pail --timeout=0

# Search for deprecation warnings
grep "deprecated" storage/logs/laravel.log
```

---

## Common Issues

### Issue 1: "Class GraphRagService not found"

**Symptom**: `Class 'App\Services\GraphRagService' not found`

**Cause**: Using old import after namespace change

**Solution**: Update import statement:
```php
// Wrong
use App\Services\GraphRagService;

// Correct
use App\Services\Graph\GraphRagOrchestrator;
```

### Issue 2: Deprecation Warnings Everywhere

**Symptom**: Logs filled with deprecation warnings

**Cause**: Still using GraphRagService wrapper

**Solution**: Migrate to GraphRagOrchestrator (see [Graph Services Migration](#graph-services-migration))

### Issue 3: Type Hint Mismatch

**Symptom**: `Argument 1 passed to ... must be an instance of GraphRagService, instance of GraphRagOrchestrator given`

**Cause**: Mixed usage of old and new services

**Solution**: Update all type hints consistently:
```php
// Update all occurrences
public function sync(GraphRagOrchestrator $orchestrator)
```

### Issue 4: Method Not Found

**Symptom**: `Call to undefined method`

**Cause**: Method might have been renamed or moved

**Solution**: Check [Method Mapping](#method-mapping) table above

---

## FAQ

### Q: Do I need to migrate immediately?

**A**: No, backward compatibility wrappers ensure old code continues to work. However, you should plan to migrate to avoid deprecation warnings and benefit from improvements.

### Q: Will my code break if I don't migrate?

**A**: No, GraphRagService wrapper ensures backward compatibility. You'll see deprecation warnings in logs, but functionality is preserved.

### Q: Should I migrate UnifiedSearchService to SearchOrchestrator?

**A**: Not yet. UnifiedSearchService is the current recommended service. SearchOrchestrator is available but not required.

### Q: What about tests using old services?

**A**: Update test imports and mocks to use new services. Tests should pass with minimal changes.

### Q: Can I use specialized sync services directly?

**A**: Yes! For granular control, you can inject and use CaseGraphSyncService, TextractGraphSyncService, etc. directly.

### Q: How long will backward compatibility be maintained?

**A**: Wrappers will remain until all code is migrated. Estimated removal: 2-3 release cycles after migration is complete.

### Q: What if I find a bug in the new services?

**A**: Report issues via GitHub. You can temporarily revert to old patterns using the wrapper while issues are fixed.

### Q: Are there performance differences?

**A**: New services are optimized and should perform the same or better. Report any performance regressions.

---

## Additional Resources

- [Phase 2 Refactoring Status](PHASE_2_REFACTORING_STATUS.md) - Complete status of refactoring
- [Phase 3 Notes](../PHASE3_NOTES.md) - Backward compatibility implementation details
- [GraphRagOrchestrator Source](../app/Services/Graph/GraphRagOrchestrator.php)
- [GraphServiceProvider](../app/Providers/GraphServiceProvider.php)

---

## Migration Checklist

Use this checklist to track your migration progress:

### Per-File Checklist

- [ ] Found all GraphRagService usages
- [ ] Updated imports to new namespaces
- [ ] Updated constructor injections
- [ ] Updated type hints
- [ ] Updated method calls (if needed)
- [ ] Updated tests
- [ ] Ran tests - all passing
- [ ] Checked logs - no deprecation warnings
- [ ] Committed changes

### Project-Wide Checklist

- [ ] All controllers migrated
- [ ] All jobs migrated
- [ ] All commands migrated
- [ ] All models migrated (observers)
- [ ] All services migrated
- [ ] All tests updated
- [ ] Documentation updated
- [ ] Team notified of changes
- [ ] Monitoring in place for issues
- [ ] Ready to remove wrappers (future)

---

## Support

If you encounter issues during migration:

1. Check this guide for common issues
2. Review [PHASE_2_REFACTORING_STATUS.md](PHASE_2_REFACTORING_STATUS.md)
3. Check logs for specific error messages
4. Consult with team members who have completed migration
5. Create an issue in the project repository

---

**Happy Migrating!** 🚀

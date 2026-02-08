# Remediation Plan for Commit 09dc1f4 Issues

**Date**: 2025-11-05
**Urgency**: HIGH
**Total Effort**: 26-32 hours to fix critical issues

---

## Phase 1: Fix Duplicate Services (CRITICAL - 4-6 hours)

### Files to DELETE:

```bash
# OLD duplicate services (app/Services/ - wrong location)
rm app/Services/CaseGraphSyncService.php                    # 328 lines
rm app/Services/GraphCitationLinker.php                     # 687 lines
rm app/Services/GraphSimilarityLinker.php                   # 384 lines
rm app/Services/Graph/GraphSyncServiceInterface.php         # 28 lines (duplicate interface)

# OLD duplicate tests
rm tests/Unit/Services/CaseGraphSyncServiceTest.php         # 434 lines
rm tests/Unit/Services/GraphCitationLinkerTest.php          # 407 lines
rm tests/Unit/Services/GraphSimilarityLinkerTest.php        # 434 lines
```

**Total Deletion**: ~2,700 lines of duplicate/dead code

### Files to MODIFY:

**1. `app/Providers/GraphServiceProvider.php`**

**BEFORE** (WRONG - using OLD services):
```php
use App\Services\CaseGraphSyncService;
use App\Services\GraphCitationLinker;
use App\Services\GraphSimilarityLinker;
```

**AFTER** (CORRECT - using NEW refactored services):
```php
use App\Services\Graph\CaseGraphSyncService;
use App\Services\Graph\GraphCitationLinker;
use App\Services\Graph\GraphSimilarityLinker;
```

**Changes Required**:
- Line ~4-6: Update import statements
- Line ~18-28: Update constructor dependencies
- Line ~40-50: Update singleton registration

### Verification:

```bash
# Run tests to ensure nothing broke
composer test

# Run specific graph tests
./scripts/run-tests.sh --filter=Graph

# Check for import errors
php artisan about
```

**Expected Results**:
- All tests pass
- Application uses NEW 93-line CaseGraphSyncService (not 328-line old version)
- Application uses NEW 189-line GraphCitationLinker (not 687-line old version)
- Application uses NEW 136-line GraphSimilarityLinker (not 384-line old version)

---

## Phase 2: Complete Sprint 1 - TextractGraphSyncService (6-8 hours)

### Files to CREATE:

**1. `app/Services/Graph/TextractGraphSyncService.php`** (~250 lines)

```php
<?php

namespace App\Services\Graph;

use App\Contracts\GraphSyncServiceInterface;
use App\Services\GraphDatabaseService;
use App\Services\TaggingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Textract Document Graph Sync Service
 *
 * Syncs OCR'd documents from AWS Textract to Neo4j graph.
 */
class TextractGraphSyncService implements GraphSyncServiceInterface
{
    public function __construct(
        protected GraphDatabaseService $graph,
        protected GraphKeywordLinker $keywordLinker,
        protected GraphCitationLinker $citationLinker,
        protected TaggingService $tagging
    ) {}

    /**
     * Sync textract document to graph
     */
    public function sync(string $textractDocId): void
    {
        $doc = DB::table('textract_documents')->where('id', $textractDocId)->first();

        if (!$doc) {
            return;
        }

        // Create textract document node
        $this->createTextractDocumentNode($doc);

        // Extract and link keywords
        $this->keywordLinker->link('TextractDocument', $doc->id, $doc->extracted_text);

        // Extract and link citations
        $this->citationLinker->link('TextractDocument', $doc->id, $doc->extracted_text);

        // Auto-tag
        $this->tagging->autoTag('TextractDocument', $doc->id);

        Log::info("Textract document synced to graph", [
            'textract_doc_id' => $doc->id,
            'drive_file_id' => $doc->drive_file_id,
        ]);
    }

    /**
     * Check if document exists in graph
     */
    public function exists(string $textractDocId): bool
    {
        $query = <<<'CYPHER'
        MATCH (t:TextractDocument {textract_id: $textractId})
        RETURN t
        CYPHER;

        $result = $this->graph->run($query, ['textractId' => $textractDocId]);

        return !empty($result);
    }

    /**
     * Remove document from graph
     */
    public function remove(string $textractDocId): bool
    {
        $query = <<<'CYPHER'
        MATCH (t:TextractDocument {textract_id: $textractId})
        DETACH DELETE t
        CYPHER;

        $this->graph->run($query, ['textractId' => $textractDocId]);

        return true;
    }

    /**
     * Create textract document node in Neo4j
     */
    protected function createTextractDocumentNode(object $doc): void
    {
        $query = <<<'CYPHER'
        MERGE (t:TextractDocument {textract_id: $textractId})
        SET t.drive_file_id = $driveFileId,
            t.extracted_text = $extractedText,
            t.page_count = $pageCount,
            t.processed_at = $processedAt,
            t.updated_at = datetime()
        RETURN t
        CYPHER;

        $this->graph->run($query, [
            'textractId' => $doc->id,
            'driveFileId' => $doc->drive_file_id,
            'extractedText' => $doc->extracted_text,
            'pageCount' => $doc->page_count,
            'processedAt' => $doc->processed_at,
        ]);
    }
}
```

**2. `tests/Unit/Services/Graph/TextractGraphSyncServiceTest.php`** (~500 lines)

```php
<?php

namespace Tests\Unit\Services\Graph;

use App\Services\Graph\TextractGraphSyncService;
use App\Services\GraphDatabaseService;
use App\Services\Graph\GraphKeywordLinker;
use App\Services\Graph\GraphCitationLinker;
use App\Services\TaggingService;
use Tests\TestCase;
use Tests\Concerns\UsesTestDatabase;
use Mockery;

class TextractGraphSyncServiceTest extends TestCase
{
    use UsesTestDatabase;

    protected TextractGraphSyncService $service;
    protected $graph;
    protected $keywordLinker;
    protected $citationLinker;
    protected $tagging;

    protected function setUp(): void
    {
        parent::setUp();

        $this->graph = Mockery::mock(GraphDatabaseService::class);
        $this->keywordLinker = Mockery::mock(GraphKeywordLinker::class);
        $this->citationLinker = Mockery::mock(GraphCitationLinker::class);
        $this->tagging = Mockery::mock(TaggingService::class);

        $this->service = new TextractGraphSyncService(
            $this->graph,
            $this->keywordLinker,
            $this->citationLinker,
            $this->tagging
        );
    }

    /** @test */
    public function it_implements_graph_sync_service_interface()
    {
        $this->assertInstanceOf(
            \App\Contracts\GraphSyncServiceInterface::class,
            $this->service
        );
    }

    /** @test */
    public function it_syncs_textract_document_to_graph()
    {
        $textractDoc = \App\Models\TextractDocument::factory()->create([
            'drive_file_id' => 'test-file-123',
            'extracted_text' => 'Test extracted text from PDF',
            'page_count' => 5,
        ]);

        $this->graph
            ->shouldReceive('run')
            ->once()
            ->with(
                Mockery::on(fn($q) => str_contains($q, 'MERGE (t:TextractDocument')),
                Mockery::on(fn($p) => $p['textractId'] === $textractDoc->id)
            )
            ->andReturn([]);

        $this->keywordLinker
            ->shouldReceive('link')
            ->once()
            ->with('TextractDocument', $textractDoc->id, $textractDoc->extracted_text);

        $this->citationLinker
            ->shouldReceive('link')
            ->once()
            ->with('TextractDocument', $textractDoc->id, $textractDoc->extracted_text);

        $this->tagging
            ->shouldReceive('autoTag')
            ->once()
            ->with('TextractDocument', $textractDoc->id);

        $this->service->sync($textractDoc->id);
    }

    /** @test */
    public function it_checks_if_textract_document_exists_in_graph()
    {
        $textractId = 'textract-123';

        $this->graph
            ->shouldReceive('run')
            ->once()
            ->with(
                Mockery::on(fn($q) => str_contains($q, 'MATCH (t:TextractDocument')),
                ['textractId' => $textractId]
            )
            ->andReturn([['t' => ['textract_id' => $textractId]]]);

        $exists = $this->service->exists($textractId);

        $this->assertTrue($exists);
    }

    /** @test */
    public function it_removes_textract_document_from_graph()
    {
        $textractId = 'textract-123';

        $this->graph
            ->shouldReceive('run')
            ->once()
            ->with(
                Mockery::on(fn($q) => str_contains($q, 'DETACH DELETE')),
                ['textractId' => $textractId]
            )
            ->andReturn([]);

        $result = $this->service->remove($textractId);

        $this->assertTrue($result);
    }

    // ... more tests
}
```

**3. Update `app/Services/Graph/GraphRagOrchestrator.php`**

Add TextractGraphSyncService to constructor and create method:

```php
public function __construct(
    // ... existing params
    protected TextractGraphSyncService $textractSync,
) {}

public function syncTextract(string $textractId): void
{
    $this->textractSync->sync($textractId);
}
```

**4. Update `app/Providers/GraphServiceProvider.php`**

Register TextractGraphSyncService:

```php
$this->app->singleton(TextractGraphSyncService::class, function ($app) {
    return new TextractGraphSyncService(
        $app->make(GraphDatabaseService::class),
        $app->make(\App\Services\Graph\GraphKeywordLinker::class),
        $app->make(\App\Services\Graph\GraphCitationLinker::class),
        $app->make(TaggingService::class)
    );
});
```

### Verification:

```bash
./scripts/run-tests.sh --filter=TextractGraphSyncServiceTest
composer test
```

---

## Phase 3: Add Backward Compatibility Wrappers (4-6 hours)

### GraphRagService Wrapper

**Create**: `app/Services/GraphRagService.php`

```php
<?php

namespace App\Services;

use App\Services\Graph\GraphRagOrchestrator;
use Illuminate\Support\Facades\Log;

/**
 * GraphRagService (DEPRECATED - Backward Compatibility Wrapper)
 *
 * This class delegates to GraphRagOrchestrator.
 * Kept for backward compatibility during migration.
 *
 * @deprecated Use GraphRagOrchestrator instead
 */
class GraphRagService
{
    public function __construct(
        protected GraphRagOrchestrator $orchestrator
    ) {}

    /**
     * @deprecated Use GraphRagOrchestrator::syncLaw()
     */
    public function syncLaw(string $lawId): void
    {
        Log::warning('GraphRagService::syncLaw() is deprecated. Use GraphRagOrchestrator::syncLaw()');
        $this->orchestrator->syncLaw($lawId);
    }

    /**
     * @deprecated Use GraphRagOrchestrator::syncCase()
     */
    public function syncCase(string $caseId): void
    {
        Log::warning('GraphRagService::syncCase() is deprecated. Use GraphRagOrchestrator::syncCase()');
        $this->orchestrator->syncCase($caseId);
    }

    /**
     * @deprecated Use GraphRagOrchestrator::syncDecision()
     */
    public function syncDecision(string $decisionId): void
    {
        Log::warning('GraphRagService::syncDecision() is deprecated. Use GraphRagOrchestrator::syncDecision()');
        $this->orchestrator->syncDecision($decisionId);
    }

    /**
     * @deprecated Use GraphRagOrchestrator::syncTextract()
     */
    public function syncTextract(string $textractId): void
    {
        Log::warning('GraphRagService::syncTextract() is deprecated. Use GraphRagOrchestrator::syncTextract()');
        $this->orchestrator->syncTextract($textractId);
    }

    // Add other methods that were in old GraphRagService as needed
}
```

**Update**: `app/Providers/GraphServiceProvider.php`

```php
// Change:
$this->app->singleton('\App\Services\GraphRagService::class', function ($app) {
    return $app->make(GraphRagOrchestrator::class);
});

// To:
$this->app->singleton(GraphRagService::class, function ($app) {
    return new GraphRagService($app->make(GraphRagOrchestrator::class));
});
```

### UnifiedSearchService Wrapper

**Replace**: `app/Services/UnifiedSearchService.php`

```php
<?php

namespace App\Services;

use App\Services\Search\SearchOrchestrator;
use Illuminate\Support\Facades\Log;

/**
 * UnifiedSearchService (DEPRECATED - Backward Compatibility Wrapper)
 *
 * This class delegates to SearchOrchestrator.
 * Kept for backward compatibility during migration.
 *
 * @deprecated Use SearchOrchestrator instead
 */
class UnifiedSearchService
{
    public function __construct(
        protected SearchOrchestrator $orchestrator
    ) {}

    /**
     * @deprecated Use SearchOrchestrator::search()
     */
    public function search(string $query, array $options = []): array
    {
        Log::warning('UnifiedSearchService::search() is deprecated. Use SearchOrchestrator::search()');
        return $this->orchestrator->search($query, $options);
    }
}
```

**Update**: Service provider (if needed)

### Verification:

```bash
# Check for any broken references
grep -r "GraphRagService" app/
grep -r "UnifiedSearchService" app/

# Run tests
composer test
```

---

## Phase 4: Update Documentation (2-3 hours)

### Update Existing Files:

**1. `docs/PHASE_2_REFACTORING_STATUS.md`**

Add section documenting:
- Which services are active (Graph, Search)
- Which services are deprecated (GraphRagService, UnifiedSearchService)
- Migration guide for consumers

**2. `README.md`**

Update architecture section to reflect new structure:
- GraphRagOrchestrator instead of GraphRagService
- SearchOrchestrator instead of UnifiedSearchService

**3. Create `docs/MIGRATION_GUIDE_PHASE_2.md`**

Document how to migrate from old to new services:

```markdown
# Migration Guide: Phase 2 Refactoring

## GraphRagService → GraphRagOrchestrator

### Before (Deprecated):
```php
$graphRag = app(\App\Services\GraphRagService::class);
$graphRag->syncLaw($lawId);
```

### After (Recommended):
```php
$orchestrator = app(\App\Services\Graph\GraphRagOrchestrator::class);
$orchestrator->syncLaw($lawId);
```

## UnifiedSearchService → SearchOrchestrator

### Before (Deprecated):
```php
$search = app(\App\Services\UnifiedSearchService::class);
$results = $search->search($query);
```

### After (Recommended):
```php
$orchestrator = app(\App\Services\Search\SearchOrchestrator::class);
$results = $orchestrator->search($query);
```
```

---

## Phase 5: Code Quality Improvements (4-6 hours)

### Standardize All Import Statements:

**Search for old imports**:
```bash
grep -r "use App\\\\Services\\\\CaseGraphSyncService" app/
grep -r "use App\\\\Services\\\\GraphCitationLinker" app/
grep -r "use App\\\\Services\\\\GraphSimilarityLinker" app/
```

**Replace with new imports**:
```bash
use App\Services\Graph\CaseGraphSyncService;
use App\Services\Graph\GraphCitationLinker;
use App\Services\Graph\GraphSimilarityLinker;
```

### Run Code Analysis:

```bash
# PHP Stan (if available)
./vendor/bin/phpstan analyse app/Services/Graph

# Laravel Pint (formatting)
./vendor/bin/pint app/Services/Graph

# Check for unused imports
php artisan code:clean
```

### Update All Test References:

Ensure all tests import from correct namespaces:

```bash
grep -r "App\\\\Services\\\\CaseGraphSyncService" tests/
# Should only show tests in tests/Unit/Services/Graph/
```

---

## Summary Timeline

| Phase | Task | Hours | Priority |
|-------|------|-------|----------|
| 1 | Fix Duplicate Services | 4-6 | CRITICAL ⚠️⚠️⚠️ |
| 2 | Complete Sprint 1 (Textract) | 6-8 | HIGH |
| 3 | Backward Compat Wrappers | 4-6 | HIGH |
| 4 | Update Documentation | 2-3 | MEDIUM |
| 5 | Code Quality | 4-6 | MEDIUM |
| **TOTAL** | | **20-29 hours** | |

## Expected Results After Remediation:

✅ **No duplicate code** (save ~2,700 lines)
✅ **Correct services in use** (NEW refactored versions)
✅ **Sprint 1 complete** (100% done)
✅ **Sprint 2 complete** (100% done)
✅ **Backward compatibility** (no breaking changes)
✅ **Clean codebase** (proper namespacing)
✅ **Complete documentation** (migration guides)

## Overall Progress After Remediation:

- Sprint 1: 100% ✅
- Sprint 2: 100% ✅
- Sprint 3: 10% (interfaces only)
- Sprint 4: 0% (not started)

**Total Refactoring**: **50% complete** (2 of 4 sprints done)


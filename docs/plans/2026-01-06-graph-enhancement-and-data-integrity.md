# Graph Enhancement & Data Integrity Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Enhance the Neo4j legal knowledge graph with new entity types, relationship types, improved properties, and robust data integrity mechanisms.

**Architecture:** Phased approach starting with data integrity (foundation), then adding new entities and relationships, finally enhancing analysis capabilities. Each phase is independently deployable.

**Tech Stack:** Laravel 12, Neo4j 5.x, PHP 8.4, Livewire 3, PHPUnit

---

## Phase 0: Data Quality Fixes (Critical Pre-requisite)

### Overview
Fix existing data quality issues that prevent proper graph population:
- Unicode encoding in tags (Croatian characters)
- Limited citation extraction patterns
- Missing/empty fields not inherited from parent
- No automatic re-sync for existing data

**Investigation Findings:**

| Issue | Root Cause | File | Fix Required |
|-------|-----------|------|--------------|
| Tags have `\u0161` instead of `š` | `CourtDecision` uses `'array'` cast, not `JsonUnescaped` | `app/Models/CourtDecision.php:26` | Change cast |
| 0 citations extracted | `GraphCitationLinker` patterns require NN numbers | `app/Services/Graph/GraphCitationLinker.php:41-80` | Integrate `HrLegalCitationsDetector` |
| Missing judge/court in graph | Empty fields not inherited from parent decision | `DecisionGraphSyncService.php:115-133` | Add field inheritance |
| Existing data not re-synced | No command to re-process existing decisions | N/A | Add re-sync command |

---

### Task 0.1: Fix Tags Unicode Encoding with JsonUnescaped Cast

**Files:**
- Modify: `app/Models/CourtDecision.php`
- Create: `tests/Unit/Models/CourtDecisionTagsUnicodeTest.php`

**Problem:**
Tags stored with escaped Unicode: `["Presuda","Pp - Upisnik za prekr\u0161ajni postupak"]`
Should be: `["Presuda","Pp - Upisnik za prekršajni postupak"]`

**Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Models;

use App\Models\CourtDecision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourtDecisionTagsUnicodeTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_stores_and_retrieves_tags_with_proper_croatian_unicode(): void
    {
        $decision = CourtDecision::factory()->create([
            'tags' => ['Presuda', 'Prekršajni postupak', 'Pravomoćna odluka'],
        ]);

        // Reload from database
        $decision->refresh();

        // Check that Croatian characters are preserved
        $this->assertContains('Prekršajni postupak', $decision->tags);

        // Check raw database value has proper encoding
        $raw = \DB::table('court_decisions')
            ->where('id', $decision->id)
            ->value('tags');

        // Should NOT contain escaped unicode
        $this->assertStringNotContainsString('\u0161', $raw);
        $this->assertStringContainsString('š', $raw);
    }
}
```

**Step 2: Run test to verify it fails**

Run: `php vendor/bin/phpunit tests/Unit/Models/CourtDecisionTagsUnicodeTest.php -v`
Expected: FAIL (current `'array'` cast escapes Unicode)

**Step 3: Write minimal implementation**

In `app/Models/CourtDecision.php`, change:

```php
// Before
protected $casts = [
    'tags' => 'array',
    'decision_date' => 'date',
    'publication_date' => 'date',
];

// After
use App\Casts\JsonUnescaped;

protected $casts = [
    'tags' => JsonUnescaped::class,
    'decision_date' => 'date',
    'publication_date' => 'date',
];
```

**Step 4: Run test to verify it passes**

Run: `php vendor/bin/phpunit tests/Unit/Models/CourtDecisionTagsUnicodeTest.php -v`
Expected: PASS

**Step 5: Commit**

```bash
git add -A
git commit -m "fix(models): use JsonUnescaped cast for CourtDecision tags to preserve Croatian characters"
```

---

### Task 0.2: Enhance GraphCitationLinker with HrLegalCitationsDetector

**Files:**
- Modify: `app/Services/Graph/GraphCitationLinker.php`
- Modify: `tests/Unit/Services/Graph/GraphCitationLinkerTest.php`

**Problem:**
Current `GraphCitationLinker` patterns only match citations with NN references:
- `NN 123/20` ✓
- `članak 5. Zakona (NN 123/20)` ✓
- `članak 286. stavak 4. Zakona o sigurnosti prometa na cestama` ✗ **MISSED!**

Croatian court decisions often cite laws by name WITHOUT NN numbers.

The `HrLegalCitationsDetector` already handles these patterns. We should integrate it.

**Step 1: Write the failing test**

```php
/** @test */
public function it_extracts_law_citations_by_name_without_nn_number(): void
{
    $content = "Sud nalazi da je tuženik postupio protivno članku 286. stavak 4. Zakona o sigurnosti prometa na cestama.";

    // Mock that a law exists in DB matching this name
    DB::shouldReceive('table->where->first')
        ->andReturn((object)[
            'id' => 'law-zspc-1',
            'doc_id' => 'zspc-doc-1',
            'title' => 'Zakon o sigurnosti prometa na cestama',
            'law_number' => '67/08',
        ]);

    $this->mockGraph->shouldReceive('upsertNode')->once();
    $this->mockGraph->shouldReceive('createRelationship')
        ->once()
        ->with(
            'CourtDecisionDocument', 'decision-1',
            'CITES',
            'LawDocument', 'law-zspc-1',
            Mockery::on(fn($p) => $p['article'] === '286')
        );

    $this->linker->link('CourtDecisionDocument', 'decision-1', $content);
}
```

**Step 2: Run test to verify it fails**

Run: `php vendor/bin/phpunit tests/Unit/Services/Graph/GraphCitationLinkerTest.php --filter it_extracts_law_citations_by_name -v`
Expected: FAIL

**Step 3: Modify implementation**

Update `app/Services/Graph/GraphCitationLinker.php`:

```php
<?php

namespace App\Services\Graph;

use App\Contracts\GraphLinkerInterface;
use App\Services\GraphDatabaseService;
use App\Services\LegalCitations\HrLegalCitationsDetector;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GraphCitationLinker implements GraphLinkerInterface
{
    public function __construct(
        protected GraphDatabaseService $graph,
        protected ?HrLegalCitationsDetector $citationDetector = null
    ) {
        // Lazy-load detector if not injected
        $this->citationDetector ??= app(HrLegalCitationsDetector::class);
    }

    public function link(string $nodeLabel, string $nodeId, $content): void
    {
        if ($content === null || (is_string($content) && trim($content) === '')) {
            return;
        }

        if (is_array($content)) {
            return;
        }

        // Use HrLegalCitationsDetector for comprehensive citation extraction
        $detectedCitations = $this->citationDetector->detectAll($content);
        $citations = $this->convertDetectedToCitations($detectedCitations);

        // Also run legacy patterns for backward compatibility
        $legacyCitations = $this->extractLegacyCitations($content);
        $citations = array_merge($citations, $legacyCitations);

        // Deduplicate
        $citations = $this->deduplicateCitations($citations);

        // Process citations and create relationships
        $this->processCitations($nodeLabel, $nodeId, $citations);

        Log::info('Citation extraction completed', [
            'node_label' => $nodeLabel,
            'node_id' => $nodeId,
            'citations_found' => count($citations),
        ]);
    }

    /**
     * Convert HrLegalCitationsDetector output to internal citation format
     */
    protected function convertDetectedToCitations(array $detected): array
    {
        $citations = [];

        // Process statute citations
        foreach ($detected['statutes'] ?? [] as $statute) {
            $citations[] = [
                'type' => 'statute_citation',
                'law_name' => $statute['law_name'] ?? null,
                'article' => $statute['article'] ?? null,
                'paragraph' => $statute['paragraph'] ?? null,
                'law_number' => $statute['law_number'] ?? null,
                'canonical' => $statute['canonical'] ?? null,
            ];
        }

        // Process case number citations
        foreach ($detected['case_numbers'] ?? [] as $caseRef) {
            $citations[] = [
                'type' => 'case_reference',
                'value' => $caseRef['canonical'] ?? $caseRef['match'] ?? null,
            ];
        }

        // Process ECLI citations
        foreach ($detected['ecli'] ?? [] as $ecli) {
            $citations[] = [
                'type' => 'ecli_reference',
                'value' => $ecli['canonical'] ?? $ecli['match'] ?? null,
            ];
        }

        return $citations;
    }

    /**
     * Extract citations using legacy patterns (backward compatibility)
     */
    protected function extractLegacyCitations(string $content): array
    {
        $citations = [];

        // Pattern 1: NN citations - "NN 123/20", "Narodne novine 45/2021"
        preg_match_all('/(?:NN|Narodne\s+novine)\s+(\d+\/\d+)/iu', $content, $matches);
        foreach ($matches[1] as $lawNumber) {
            $citations[] = [
                'type' => 'law_number',
                'value' => $lawNumber,
            ];
        }

        // Pattern 2: Article references with law numbers
        preg_match_all('/(?:članak|čl\.?)\s+(\d+)(?:\.|,)?\s*(?:Zakona?\s+)?(?:\()?(?:NN|Narodne\s+novine)\s+(\d+\/\d+)/iu', $content, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $citations[] = [
                'type' => 'article_reference',
                'article' => $match[1],
                'law_number' => $match[2],
            ];
        }

        // Pattern 3: Law names with citations
        preg_match_all('/Zakon\s+o\s+([^\(]{5,100})\s*\((?:NN|Narodne\s+novine)\s+(\d+\/\d+)\)/iu', $content, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $citations[] = [
                'type' => 'named_law',
                'name' => trim($match[1]),
                'law_number' => $match[2],
            ];
        }

        // Pattern 4: Case references
        preg_match_all('/(?:odluka|presuda|rješenje)\s+(?:broj:?\s+)?([A-Z]+-?\d+\/\d+(?:-\d+)?)/iu', $content, $matches);
        foreach ($matches[1] as $caseNumber) {
            $citations[] = [
                'type' => 'case_reference',
                'value' => $caseNumber,
            ];
        }

        return $citations;
    }

    /**
     * Deduplicate citations based on type and key values
     */
    protected function deduplicateCitations(array $citations): array
    {
        $seen = [];
        $unique = [];

        foreach ($citations as $citation) {
            $key = $this->getCitationKey($citation);
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $citation;
            }
        }

        return $unique;
    }

    protected function getCitationKey(array $citation): string
    {
        return md5(json_encode([
            'type' => $citation['type'],
            'law_number' => $citation['law_number'] ?? null,
            'law_name' => $citation['law_name'] ?? null,
            'article' => $citation['article'] ?? null,
            'value' => $citation['value'] ?? null,
        ]));
    }

    /**
     * Process citations and create graph relationships
     */
    protected function processCitations(string $nodeLabel, string $nodeId, array $citations): void
    {
        foreach ($citations as $citation) {
            try {
                $this->processSingleCitation($nodeLabel, $nodeId, $citation);
            } catch (\Exception $e) {
                Log::warning('Failed to create citation relationship', [
                    'citation' => $citation,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Process a single citation
     */
    protected function processSingleCitation(string $nodeLabel, string $nodeId, array $citation): void
    {
        // Try to find law by law_number
        if (isset($citation['law_number'])) {
            $this->linkLawByNumber($nodeLabel, $nodeId, $citation);
            return;
        }

        // Try to find law by name
        if (isset($citation['law_name']) && $citation['type'] === 'statute_citation') {
            $this->linkLawByName($nodeLabel, $nodeId, $citation);
            return;
        }

        // Handle case references
        if ($citation['type'] === 'case_reference' && isset($citation['value'])) {
            $this->linkCaseReference($nodeLabel, $nodeId, $citation);
            return;
        }
    }

    /**
     * Link to a law found by NN number
     */
    protected function linkLawByNumber(string $nodeLabel, string $nodeId, array $citation): void
    {
        $lawNumber = $citation['law_number'] ?? $citation['value'] ?? null;
        if (!$lawNumber) return;

        $referencedLaw = DB::table('laws')
            ->where('law_number', $lawNumber)
            ->first();

        if ($referencedLaw) {
            $this->createLawCitation($nodeLabel, $nodeId, $referencedLaw, $citation);
        }
    }

    /**
     * Link to a law found by name (new capability)
     */
    protected function linkLawByName(string $nodeLabel, string $nodeId, array $citation): void
    {
        $lawName = $citation['law_name'] ?? null;
        if (!$lawName) return;

        // Try exact match first
        $referencedLaw = DB::table('laws')
            ->where('title', 'ILIKE', "%{$lawName}%")
            ->first();

        if (!$referencedLaw) {
            // Try partial match with key words
            $keywords = array_filter(explode(' ', $lawName), fn($w) => strlen($w) > 3);
            if (!empty($keywords)) {
                $query = DB::table('laws');
                foreach ($keywords as $keyword) {
                    $query->where('title', 'ILIKE', "%{$keyword}%");
                }
                $referencedLaw = $query->first();
            }
        }

        if ($referencedLaw) {
            $this->createLawCitation($nodeLabel, $nodeId, $referencedLaw, $citation);
        }
    }

    /**
     * Create CITES relationship to a law
     */
    protected function createLawCitation(string $nodeLabel, string $nodeId, object $law, array $citation): void
    {
        $this->graph->upsertNode('LawDocument', $law->id, [
            'doc_id' => $law->doc_id,
            'title' => $law->title,
            'law_number' => $law->law_number,
        ]);

        $relationshipProps = [
            'citation_type' => $citation['type'],
            'created_at' => now()->toIso8601String(),
        ];

        if (isset($citation['article'])) {
            $relationshipProps['article'] = $citation['article'];
        }
        if (isset($citation['paragraph'])) {
            $relationshipProps['paragraph'] = $citation['paragraph'];
        }

        $this->graph->createRelationship(
            $nodeLabel, $nodeId,
            'CITES',
            'LawDocument', $law->id,
            $relationshipProps
        );
    }

    /**
     * Link case reference
     */
    protected function linkCaseReference(string $nodeLabel, string $nodeId, array $citation): void
    {
        $referencedCase = DB::table('cases_documents')
            ->where('doc_id', 'LIKE', '%'.$citation['value'].'%')
            ->first();

        if ($referencedCase) {
            $this->graph->upsertNode('CaseDocument', $referencedCase->id, [
                'case_id' => $referencedCase->case_id,
                'doc_id' => $referencedCase->doc_id,
                'title' => $referencedCase->title,
            ]);

            $this->graph->createRelationship(
                $nodeLabel, $nodeId,
                'REFERENCES',
                'CaseDocument', $referencedCase->id,
                [
                    'citation_type' => 'case_reference',
                    'created_at' => now()->toIso8601String(),
                ]
            );
        }
    }

    // ... rest of existing methods remain unchanged ...
}
```

**Step 4: Run test to verify it passes**

Run: `php vendor/bin/phpunit tests/Unit/Services/Graph/GraphCitationLinkerTest.php -v`
Expected: PASS

**Step 5: Commit**

```bash
git add -A
git commit -m "feat(graph): integrate HrLegalCitationsDetector into GraphCitationLinker for comprehensive citation extraction"
```

---

### Task 0.3: Handle Missing Fields from Parent Decision

**Files:**
- Modify: `app/Services/Graph/DecisionGraphSyncService.php`
- Modify: `tests/Unit/Services/Graph/DecisionGraphSyncServiceTest.php`

**Problem:**
`CourtDecisionDocument` can have null fields (judge, court, description) even when parent `CourtDecision` has them.
Graph nodes should inherit from parent when local field is null.

**Step 1: Write the failing test**

```php
/** @test */
public function it_inherits_fields_from_parent_decision_when_document_fields_are_null(): void
{
    // Parent has judge, document does not
    $decision = (object)[
        'id' => 'decision-1',
        'case_number' => 'Rev 1/2020',
        'court' => 'Vrhovni sud RH',
        'judge' => 'Ivan Horvat',
        'jurisdiction' => 'hr',
    ];

    $doc = (object)[
        'id' => 'doc-1',
        'decision_id' => 'decision-1',
        'title' => null,  // Should inherit
        'court' => null,  // Should inherit
        'judge' => null,  // Should inherit
        'content' => 'Test content',
    ];

    $this->mockGraph->shouldReceive('upsertNode')
        ->once()
        ->with('CourtDecisionDocument', 'doc-1', Mockery::on(fn($p) =>
            $p['court'] === 'Vrhovni sud RH' &&
            $p['judge'] === 'Ivan Horvat'
        ));

    // Call private method via reflection for testing
    $this->invokeMethod($this->service, 'syncDecisionDocument', [$decision, $doc]);
}
```

**Step 2: Run test to verify it fails**

Run: `php vendor/bin/phpunit tests/Unit/Services/Graph/DecisionGraphSyncServiceTest.php --filter it_inherits_fields -v`
Expected: FAIL (current implementation doesn't check for null before using decision values)

**Step 3: Update implementation**

The current implementation at `DecisionGraphSyncService.php:115-133` already uses `$decision->*` for most fields.
However, the issue is at the document level where some values might be null in the DB.

Update `createDecisionDocumentNode`:

```php
protected function createDecisionDocumentNode($decision, $doc): void
{
    // Inherit from parent decision when document field is null/empty
    $title = $doc->title ?? $decision->title ?? 'Untitled';
    $judge = $doc->judge ?? $decision->judge ?? null;
    $court = $doc->court ?? $decision->court ?? null;

    $this->graph->upsertNode('CourtDecisionDocument', $doc->id, [
        'decision_id' => $doc->decision_id,
        'doc_id' => $doc->doc_id ?? $doc->id,
        'title' => $title,
        'case_number' => $decision->case_number,
        'court' => $court,
        'jurisdiction' => $decision->jurisdiction,
        'judge' => $judge,
        'decision_date' => $decision->decision_date,
        'publication_date' => $decision->publication_date,
        'decision_type' => $decision->decision_type,
        'register' => $decision->register,
        'finality' => $decision->finality,
        'ecli' => $decision->ecli,
        'chunk_index' => $doc->chunk_index ?? 0,
        'content_hash' => $doc->content_hash ?? null,
    ]);
}
```

**Step 4: Run test to verify it passes**

Run: `php vendor/bin/phpunit tests/Unit/Services/Graph/DecisionGraphSyncServiceTest.php --filter it_inherits_fields -v`
Expected: PASS

**Step 5: Commit**

```bash
git add -A
git commit -m "fix(graph): inherit null fields from parent CourtDecision in DecisionGraphSyncService"
```

---

### Task 0.4: Add Graph Re-sync Command for Existing Data

**Files:**
- Create: `app/Console/Commands/GraphResyncDecisionsCommand.php`
- Create: `tests/Feature/Commands/GraphResyncDecisionsCommandTest.php`

**Problem:**
After fixing citation extraction and field inheritance, existing decisions in Neo4j need to be re-synced.

**Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Commands;

use App\Services\Graph\DecisionGraphSyncService;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class GraphResyncDecisionsCommandTest extends TestCase
{
    /** @test */
    public function it_resyncs_specified_decisions(): void
    {
        // Mock decisions exist
        DB::shouldReceive('table->pluck->toArray')
            ->andReturn(['decision-1', 'decision-2']);

        $mockService = Mockery::mock(DecisionGraphSyncService::class);
        $mockService->shouldReceive('sync')
            ->times(2);

        $this->app->instance(DecisionGraphSyncService::class, $mockService);

        $this->artisan('graph:resync-decisions', ['--all' => true])
            ->expectsOutput('Re-syncing court decisions to graph...')
            ->assertSuccessful();
    }

    /** @test */
    public function it_resyncs_by_decision_id(): void
    {
        $mockService = Mockery::mock(DecisionGraphSyncService::class);
        $mockService->shouldReceive('sync')
            ->once()
            ->with('specific-decision-id');

        $this->app->instance(DecisionGraphSyncService::class, $mockService);

        $this->artisan('graph:resync-decisions', ['--id' => 'specific-decision-id'])
            ->assertSuccessful();
    }
}
```

**Step 2: Run test to verify it fails**

Run: `php vendor/bin/phpunit tests/Feature/Commands/GraphResyncDecisionsCommandTest.php -v`
Expected: FAIL

**Step 3: Write minimal implementation**

```php
<?php

namespace App\Console\Commands;

use App\Services\Graph\DecisionGraphSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GraphResyncDecisionsCommand extends Command
{
    protected $signature = 'graph:resync-decisions
        {--all : Re-sync all decisions}
        {--id= : Re-sync specific decision ID}
        {--limit=100 : Limit number of decisions to re-sync}
        {--force : Skip confirmation}';

    protected $description = 'Re-sync court decisions to Neo4j graph database (after citation/field fixes)';

    public function handle(DecisionGraphSyncService $syncService): int
    {
        if (!config('neo4j.sync.enabled')) {
            $this->warn('Neo4j sync is disabled');
            return self::FAILURE;
        }

        $this->info('Re-syncing court decisions to graph...');

        $decisionIds = [];

        if ($this->option('id')) {
            $decisionIds = [$this->option('id')];
        } elseif ($this->option('all')) {
            $limit = (int) $this->option('limit');
            $decisionIds = DB::table('court_decisions')
                ->limit($limit)
                ->pluck('id')
                ->toArray();
        } else {
            $this->error('Specify --all or --id=<decision_id>');
            return self::FAILURE;
        }

        if (empty($decisionIds)) {
            $this->warn('No decisions found to re-sync');
            return self::SUCCESS;
        }

        $this->info("Found " . count($decisionIds) . " decisions to re-sync");

        if (!$this->option('force') && !$this->confirm('Proceed with re-sync?')) {
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar(count($decisionIds));
        $bar->start();

        $synced = 0;
        $errors = 0;

        foreach ($decisionIds as $decisionId) {
            try {
                $syncService->sync($decisionId);
                $synced++;
            } catch (\Exception $e) {
                $errors++;
                $this->newLine();
                $this->error("Failed to sync {$decisionId}: " . $e->getMessage());
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Re-sync completed: {$synced} synced, {$errors} errors");

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
```

**Step 4: Run test to verify it passes**

Run: `php vendor/bin/phpunit tests/Feature/Commands/GraphResyncDecisionsCommandTest.php -v`
Expected: PASS

**Step 5: Commit**

```bash
git add -A
git commit -m "feat(graph): add graph:resync-decisions command for re-syncing existing data"
```

---

## Phase 1: Graph Data Integrity & Maintenance

### Overview
Build foundation for graph health monitoring, validation, and cleanup before adding new entities.

---

### Task 1.1: Create GraphDataIntegrityService

**Files:**
- Create: `app/Services/Graph/GraphDataIntegrityService.php`
- Create: `tests/Unit/Services/Graph/GraphDataIntegrityServiceTest.php`

**Step 1: Write the failing test for orphan node detection**

```php
<?php

namespace Tests\Unit\Services\Graph;

use App\Services\Graph\GraphDataIntegrityService;
use App\Services\GraphDatabaseService;
use Mockery;
use Tests\TestCase;

class GraphDataIntegrityServiceTest extends TestCase
{
    private GraphDataIntegrityService $service;
    private $mockGraph;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockGraph = Mockery::mock(GraphDatabaseService::class);
        $this->service = new GraphDataIntegrityService($this->mockGraph);
    }

    /** @test */
    public function it_detects_orphan_court_decision_nodes(): void
    {
        // Orphan nodes = nodes in Neo4j without corresponding PostgreSQL record
        $this->mockGraph->shouldReceive('run')
            ->once()
            ->with(Mockery::on(fn($q) => str_contains($q, 'CourtDecisionDocument')))
            ->andReturn([
                ['id' => 'orphan-1', 'case_number' => 'Rev 1/2020'],
                ['id' => 'orphan-2', 'case_number' => 'Rev 2/2020'],
            ]);

        $orphans = $this->service->findOrphanNodes('CourtDecisionDocument');

        $this->assertCount(2, $orphans);
        $this->assertEquals('orphan-1', $orphans[0]['id']);
    }
}
```

**Step 2: Run test to verify it fails**

Run: `php vendor/bin/phpunit tests/Unit/Services/Graph/GraphDataIntegrityServiceTest.php --filter it_detects_orphan_court_decision_nodes -v`
Expected: FAIL with "Class GraphDataIntegrityService not found"

**Step 3: Write minimal implementation**

```php
<?php

namespace App\Services\Graph;

use App\Services\GraphDatabaseService;
use Illuminate\Support\Facades\DB;

/**
 * Service for maintaining graph data integrity
 *
 * Responsibilities:
 * - Detect orphan nodes (Neo4j nodes without PostgreSQL records)
 * - Detect missing nodes (PostgreSQL records without Neo4j nodes)
 * - Validate relationship consistency
 * - Provide cleanup operations
 */
class GraphDataIntegrityService
{
    public function __construct(
        protected GraphDatabaseService $graph
    ) {}

    /**
     * Find orphan nodes - nodes in Neo4j without PostgreSQL records
     *
     * @param string $nodeType The node type to check
     * @return array List of orphan node IDs with metadata
     */
    public function findOrphanNodes(string $nodeType): array
    {
        $tableName = $this->getTableForNodeType($nodeType);

        // Get all node IDs from Neo4j
        $cypher = "MATCH (n:{$nodeType}) RETURN n.id as id, n.case_number as case_number LIMIT 1000";
        $neo4jNodes = $this->graph->run($cypher);

        if (empty($neo4jNodes)) {
            return [];
        }

        // Get IDs that exist in PostgreSQL
        $neo4jIds = array_column($neo4jNodes, 'id');
        $existingIds = DB::table($tableName)
            ->whereIn('id', $neo4jIds)
            ->pluck('id')
            ->toArray();

        // Return nodes that don't exist in PostgreSQL
        return array_filter($neo4jNodes, fn($node) => !in_array($node['id'], $existingIds));
    }

    /**
     * Get the PostgreSQL table name for a node type
     */
    protected function getTableForNodeType(string $nodeType): string
    {
        return match ($nodeType) {
            'CourtDecisionDocument' => 'court_decision_documents',
            'LawDocument' => 'law_documents',
            'CaseDocument' => 'case_documents',
            'Court' => 'courts',
            'Keyword' => 'keywords',
            'Tag' => 'tags',
            'Topic' => 'topics',
            default => throw new \InvalidArgumentException("Unknown node type: {$nodeType}"),
        };
    }
}
```

**Step 4: Run test to verify it passes**

Run: `php vendor/bin/phpunit tests/Unit/Services/Graph/GraphDataIntegrityServiceTest.php --filter it_detects_orphan_court_decision_nodes -v`
Expected: PASS

**Step 5: Commit**

```bash
git add app/Services/Graph/GraphDataIntegrityService.php tests/Unit/Services/Graph/GraphDataIntegrityServiceTest.php
git commit -m "feat(graph): add GraphDataIntegrityService with orphan detection"
```

---

### Task 1.2: Add Missing Node Detection

**Files:**
- Modify: `app/Services/Graph/GraphDataIntegrityService.php`
- Modify: `tests/Unit/Services/Graph/GraphDataIntegrityServiceTest.php`

**Step 1: Write the failing test**

```php
/** @test */
public function it_detects_missing_nodes_in_neo4j(): void
{
    // Missing nodes = PostgreSQL records without Neo4j nodes
    $this->mockGraph->shouldReceive('run')
        ->once()
        ->andReturn([]);  // No nodes found in Neo4j

    // Assume we have 2 records in PostgreSQL
    DB::shouldReceive('table->whereIn->pluck->toArray')
        ->andReturn(['id-1', 'id-2']);

    $missing = $this->service->findMissingNodes('CourtDecisionDocument', ['id-1', 'id-2', 'id-3']);

    $this->assertContains('id-3', $missing);
}
```

**Step 2: Run test to verify it fails**

Run: `php vendor/bin/phpunit tests/Unit/Services/Graph/GraphDataIntegrityServiceTest.php --filter it_detects_missing_nodes -v`
Expected: FAIL

**Step 3: Add implementation**

```php
/**
 * Find missing nodes - PostgreSQL records without Neo4j nodes
 *
 * @param string $nodeType The node type to check
 * @param array|null $idsToCheck Specific IDs to check, or null for batch check
 * @return array List of missing IDs
 */
public function findMissingNodes(string $nodeType, ?array $idsToCheck = null): array
{
    $tableName = $this->getTableForNodeType($nodeType);

    // Get IDs from PostgreSQL
    if ($idsToCheck === null) {
        $postgresIds = DB::table($tableName)->limit(1000)->pluck('id')->toArray();
    } else {
        $postgresIds = $idsToCheck;
    }

    if (empty($postgresIds)) {
        return [];
    }

    // Check which exist in Neo4j (batch query)
    $idList = implode("','", $postgresIds);
    $cypher = "MATCH (n:{$nodeType}) WHERE n.id IN ['{$idList}'] RETURN n.id as id";
    $neo4jNodes = $this->graph->run($cypher);
    $neo4jIds = array_column($neo4jNodes, 'id');

    // Return IDs that don't exist in Neo4j
    return array_diff($postgresIds, $neo4jIds);
}
```

**Step 4: Run test to verify it passes**

Run: `php vendor/bin/phpunit tests/Unit/Services/Graph/GraphDataIntegrityServiceTest.php --filter it_detects_missing_nodes -v`
Expected: PASS

**Step 5: Commit**

```bash
git add -A
git commit -m "feat(graph): add missing node detection to GraphDataIntegrityService"
```

---

### Task 1.3: Add Relationship Consistency Validation

**Files:**
- Modify: `app/Services/Graph/GraphDataIntegrityService.php`
- Modify: `tests/Unit/Services/Graph/GraphDataIntegrityServiceTest.php`

**Step 1: Write the failing test**

```php
/** @test */
public function it_validates_relationship_consistency(): void
{
    // Check that relationships point to existing nodes
    $this->mockGraph->shouldReceive('run')
        ->once()
        ->with(Mockery::on(fn($q) => str_contains($q, 'CITES')))
        ->andReturn([
            ['from_id' => 'valid-1', 'to_id' => 'orphan-target', 'rel_type' => 'CITES'],
        ]);

    $issues = $this->service->validateRelationshipConsistency('CITES');

    $this->assertNotEmpty($issues);
    $this->assertEquals('orphan-target', $issues[0]['to_id']);
}
```

**Step 2: Run test to verify it fails**

Run: `php vendor/bin/phpunit tests/Unit/Services/Graph/GraphDataIntegrityServiceTest.php --filter it_validates_relationship_consistency -v`
Expected: FAIL

**Step 3: Add implementation**

```php
/**
 * Validate relationship consistency - check for dangling relationships
 *
 * @param string $relationType The relationship type to validate
 * @return array List of inconsistent relationships
 */
public function validateRelationshipConsistency(string $relationType): array
{
    $cypher = "
        MATCH (a)-[r:{$relationType}]->(b)
        WHERE NOT (b:CourtDecisionDocument OR b:LawDocument OR b:Court OR b:Jurisdiction OR b:Keyword)
        RETURN a.id as from_id, b.id as to_id, type(r) as rel_type
        LIMIT 100
    ";

    return $this->graph->run($cypher);
}
```

**Step 4: Run test to verify it passes**

Run: `php vendor/bin/phpunit tests/Unit/Services/Graph/GraphDataIntegrityServiceTest.php --filter it_validates_relationship_consistency -v`
Expected: PASS

**Step 5: Commit**

```bash
git add -A
git commit -m "feat(graph): add relationship consistency validation"
```

---

### Task 1.4: Add Integrity Report Generation

**Files:**
- Modify: `app/Services/Graph/GraphDataIntegrityService.php`
- Modify: `tests/Unit/Services/Graph/GraphDataIntegrityServiceTest.php`

**Step 1: Write the failing test**

```php
/** @test */
public function it_generates_comprehensive_integrity_report(): void
{
    $this->mockGraph->shouldReceive('run')->andReturn([]);

    $report = $this->service->generateIntegrityReport();

    $this->assertArrayHasKey('orphan_nodes', $report);
    $this->assertArrayHasKey('missing_nodes', $report);
    $this->assertArrayHasKey('dangling_relationships', $report);
    $this->assertArrayHasKey('generated_at', $report);
    $this->assertArrayHasKey('summary', $report);
}
```

**Step 2: Run test to verify it fails**

Run: `php vendor/bin/phpunit tests/Unit/Services/Graph/GraphDataIntegrityServiceTest.php --filter it_generates_comprehensive_integrity_report -v`
Expected: FAIL

**Step 3: Add implementation**

```php
/**
 * Generate a comprehensive integrity report
 *
 * @return array Complete integrity report
 */
public function generateIntegrityReport(): array
{
    $nodeTypes = ['CourtDecisionDocument', 'LawDocument', 'Court', 'Keyword'];
    $relationshipTypes = ['CITES', 'REFERENCES', 'DECIDED_BY', 'HAS_KEYWORD'];

    $orphanNodes = [];
    $missingNodes = [];
    $danglingRels = [];

    foreach ($nodeTypes as $type) {
        try {
            $orphanNodes[$type] = $this->findOrphanNodes($type);
            $missingNodes[$type] = $this->findMissingNodes($type);
        } catch (\Exception $e) {
            $orphanNodes[$type] = ['error' => $e->getMessage()];
            $missingNodes[$type] = ['error' => $e->getMessage()];
        }
    }

    foreach ($relationshipTypes as $relType) {
        try {
            $danglingRels[$relType] = $this->validateRelationshipConsistency($relType);
        } catch (\Exception $e) {
            $danglingRels[$relType] = ['error' => $e->getMessage()];
        }
    }

    $totalOrphans = array_sum(array_map(fn($v) => is_array($v) && !isset($v['error']) ? count($v) : 0, $orphanNodes));
    $totalMissing = array_sum(array_map(fn($v) => is_array($v) && !isset($v['error']) ? count($v) : 0, $missingNodes));
    $totalDangling = array_sum(array_map(fn($v) => is_array($v) && !isset($v['error']) ? count($v) : 0, $danglingRels));

    return [
        'orphan_nodes' => $orphanNodes,
        'missing_nodes' => $missingNodes,
        'dangling_relationships' => $danglingRels,
        'generated_at' => now()->toIso8601String(),
        'summary' => [
            'total_orphan_nodes' => $totalOrphans,
            'total_missing_nodes' => $totalMissing,
            'total_dangling_relationships' => $totalDangling,
            'health_status' => ($totalOrphans + $totalMissing + $totalDangling) === 0 ? 'healthy' : 'needs_attention',
        ],
    ];
}
```

**Step 4: Run test to verify it passes**

Run: `php vendor/bin/phpunit tests/Unit/Services/Graph/GraphDataIntegrityServiceTest.php --filter it_generates_comprehensive_integrity_report -v`
Expected: PASS

**Step 5: Commit**

```bash
git add -A
git commit -m "feat(graph): add comprehensive integrity report generation"
```

---

### Task 1.5: Create Artisan Command for Integrity Check

**Files:**
- Create: `app/Console/Commands/GraphIntegrityCheckCommand.php`
- Create: `tests/Feature/Commands/GraphIntegrityCheckCommandTest.php`

**Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Commands;

use App\Services\Graph\GraphDataIntegrityService;
use Mockery;
use Tests\TestCase;

class GraphIntegrityCheckCommandTest extends TestCase
{
    /** @test */
    public function it_runs_integrity_check_and_outputs_report(): void
    {
        $mockService = Mockery::mock(GraphDataIntegrityService::class);
        $mockService->shouldReceive('generateIntegrityReport')
            ->once()
            ->andReturn([
                'orphan_nodes' => [],
                'missing_nodes' => [],
                'dangling_relationships' => [],
                'generated_at' => now()->toIso8601String(),
                'summary' => [
                    'total_orphan_nodes' => 0,
                    'total_missing_nodes' => 0,
                    'total_dangling_relationships' => 0,
                    'health_status' => 'healthy',
                ],
            ]);

        $this->app->instance(GraphDataIntegrityService::class, $mockService);

        $this->artisan('graph:integrity-check')
            ->expectsOutput('Graph Integrity Check')
            ->assertSuccessful();
    }
}
```

**Step 2: Run test to verify it fails**

Run: `php vendor/bin/phpunit tests/Feature/Commands/GraphIntegrityCheckCommandTest.php -v`
Expected: FAIL

**Step 3: Write minimal implementation**

```php
<?php

namespace App\Console\Commands;

use App\Services\Graph\GraphDataIntegrityService;
use Illuminate\Console\Command;

class GraphIntegrityCheckCommand extends Command
{
    protected $signature = 'graph:integrity-check
        {--fix : Attempt to fix issues automatically}
        {--json : Output as JSON}';

    protected $description = 'Check graph database integrity and report issues';

    public function handle(GraphDataIntegrityService $service): int
    {
        $this->info('Graph Integrity Check');
        $this->newLine();

        $report = $service->generateIntegrityReport();

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT));
            return $report['summary']['health_status'] === 'healthy' ? 0 : 1;
        }

        $this->table(
            ['Metric', 'Count'],
            [
                ['Orphan Nodes', $report['summary']['total_orphan_nodes']],
                ['Missing Nodes', $report['summary']['total_missing_nodes']],
                ['Dangling Relationships', $report['summary']['total_dangling_relationships']],
            ]
        );

        $this->newLine();
        $status = $report['summary']['health_status'];
        if ($status === 'healthy') {
            $this->info('Health Status: HEALTHY');
        } else {
            $this->warn('Health Status: NEEDS ATTENTION');
        }

        return $status === 'healthy' ? 0 : 1;
    }
}
```

**Step 4: Run test to verify it passes**

Run: `php vendor/bin/phpunit tests/Feature/Commands/GraphIntegrityCheckCommandTest.php -v`
Expected: PASS

**Step 5: Commit**

```bash
git add -A
git commit -m "feat(graph): add graph:integrity-check Artisan command"
```

---

## Phase 2: Enhanced Entity Types

### Overview
Add new node types for judges, parties, and legal principles to enrich the knowledge graph.

---

### Task 2.1: Add Judge Node Type to Schema

**Files:**
- Modify: `documentation/GRAPH_SCHEMA.cypher`
- Create: `database/migrations/2026_01_06_000001_add_judge_node_schema.php`

**Step 1: Document the schema addition**

Add to `GRAPH_SCHEMA.cypher`:

```cypher
// Judge (Presiding Judges)
(:Judge {
  id: String,                    // Unique ID: "judge_" + MD5(name + court)
  name: String,                  // Judge full name (indexed)
  court: String,                 // Primary court affiliation
  title: String,                 // Title (e.g., "Sudac", "Predsjednik suda")
  specializations: [String],     // Areas of specialization
  active: Boolean,               // Currently active
  created_at: String,            // ISO8601 timestamp
  updated_at: String             // ISO8601 timestamp
})

// Court Decision presided by Judge
(:CourtDecisionDocument)-[:PRESIDED_BY {
  role: String,                  // "presiding", "member", "reporting"
  created_at: String
}]->(:Judge)

// Judge affiliated with Court
(:Judge)-[:AFFILIATED_WITH {
  start_date: String,            // ISO8601
  end_date: String,              // ISO8601 or null if current
  role: String,                  // Judge role at court
  created_at: String
}]->(:Court)
```

**Step 2: Create migration**

```php
<?php

use App\Services\GraphDatabaseService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    public function up(): void
    {
        if (!config('neo4j.sync.enabled', true)) {
            return;
        }

        try {
            $graph = app(GraphDatabaseService::class);
        } catch (\Exception $e) {
            Log::warning('Neo4j not available for judge schema migration');
            return;
        }

        // Add Judge constraints
        $graph->run('CREATE CONSTRAINT judge_id IF NOT EXISTS FOR (j:Judge) REQUIRE j.id IS UNIQUE');

        // Add Judge indexes
        $graph->run('CREATE INDEX judge_name_idx IF NOT EXISTS FOR (j:Judge) ON (j.name)');
        $graph->run('CREATE INDEX judge_court_idx IF NOT EXISTS FOR (j:Judge) ON (j.court)');

        Log::info('Judge node schema added to Neo4j');
    }

    public function down(): void
    {
        Log::info('Judge schema rollback - constraints remain in place');
    }
};
```

**Step 3: Run migration**

Run: `php artisan migrate`
Expected: Migration succeeds

**Step 4: Commit**

```bash
git add -A
git commit -m "feat(graph): add Judge node type to schema"
```

---

### Task 2.2: Create JudgeGraphSyncService

**Files:**
- Create: `app/Services/Graph/JudgeGraphSyncService.php`
- Create: `tests/Unit/Services/Graph/JudgeGraphSyncServiceTest.php`

**Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Services\Graph;

use App\Services\Graph\JudgeGraphSyncService;
use App\Services\GraphDatabaseService;
use Mockery;
use Tests\TestCase;

class JudgeGraphSyncServiceTest extends TestCase
{
    /** @test */
    public function it_creates_judge_node_from_decision(): void
    {
        $mockGraph = Mockery::mock(GraphDatabaseService::class);
        $mockGraph->shouldReceive('upsertNode')
            ->once()
            ->with('Judge', Mockery::any(), Mockery::on(fn($p) => $p['name'] === 'Ivan Horvat'));
        $mockGraph->shouldReceive('createRelationship')
            ->once()
            ->with('CourtDecisionDocument', 'decision-1', 'PRESIDED_BY', 'Judge', Mockery::any(), Mockery::any());

        $service = new JudgeGraphSyncService($mockGraph);

        $service->syncJudgeFromDecision('decision-1', 'Ivan Horvat', 'Vrhovni sud RH');

        // Assertions handled by Mockery expectations
        $this->assertTrue(true);
    }
}
```

**Step 2: Run test to verify it fails**

Run: `php vendor/bin/phpunit tests/Unit/Services/Graph/JudgeGraphSyncServiceTest.php -v`
Expected: FAIL

**Step 3: Write minimal implementation**

```php
<?php

namespace App\Services\Graph;

use App\Services\GraphDatabaseService;

/**
 * Service for syncing judge entities to the graph database
 */
class JudgeGraphSyncService
{
    public function __construct(
        protected GraphDatabaseService $graph
    ) {}

    /**
     * Sync a judge from a court decision
     */
    public function syncJudgeFromDecision(string $decisionId, string $judgeName, string $court, string $role = 'presiding'): void
    {
        if (empty($judgeName)) {
            return;
        }

        // Parse multiple judges if comma-separated
        $judges = array_map('trim', explode(',', $judgeName));

        foreach ($judges as $index => $name) {
            if (empty($name)) {
                continue;
            }

            $judgeId = 'judge_' . md5($name . '_' . $court);

            // Create/update judge node
            $this->graph->upsertNode('Judge', $judgeId, [
                'name' => $name,
                'court' => $court,
                'active' => true,
            ]);

            // Create relationship to decision
            $this->graph->createRelationship(
                'CourtDecisionDocument', $decisionId,
                'PRESIDED_BY',
                'Judge', $judgeId,
                [
                    'role' => $index === 0 ? $role : 'member',
                    'created_at' => now()->toIso8601String(),
                ]
            );
        }
    }
}
```

**Step 4: Run test to verify it passes**

Run: `php vendor/bin/phpunit tests/Unit/Services/Graph/JudgeGraphSyncServiceTest.php -v`
Expected: PASS

**Step 5: Commit**

```bash
git add -A
git commit -m "feat(graph): add JudgeGraphSyncService for judge entity extraction"
```

---

### Task 2.3: Add LegalPrinciple Node Type

**Files:**
- Modify: `documentation/GRAPH_SCHEMA.cypher`
- Create: `database/migrations/2026_01_06_000002_add_legal_principle_schema.php`
- Create: `app/Services/Graph/LegalPrincipleExtractor.php`
- Create: `tests/Unit/Services/Graph/LegalPrincipleExtractorTest.php`

**Step 1: Document schema**

Add to `GRAPH_SCHEMA.cypher`:

```cypher
// Legal Principle (Court-established doctrines)
(:LegalPrinciple {
  id: String,                    // ULID
  name: String,                  // Principle name (unique, indexed)
  description: String,           // Full description
  category: String,              // Category (procedural, substantive, evidentiary)
  landmark_case_id: String,      // Decision that established it
  established_date: String,      // ISO8601 when first established
  status: String,                // "active", "overruled", "modified"
  created_at: String,
  updated_at: String
})

// Decision establishes or applies a legal principle
(:CourtDecisionDocument)-[:ESTABLISHES {
  reasoning: String,
  created_at: String
}]->(:LegalPrinciple)

(:CourtDecisionDocument)-[:APPLIES {
  how_applied: String,
  created_at: String
}]->(:LegalPrinciple)
```

**Step 2: Write the failing test**

```php
<?php

namespace Tests\Unit\Services\Graph;

use App\Services\Graph\LegalPrincipleExtractor;
use Tests\TestCase;

class LegalPrincipleExtractorTest extends TestCase
{
    /** @test */
    public function it_extracts_legal_principles_from_decision_text(): void
    {
        $extractor = new LegalPrincipleExtractor();

        $text = "Sud utvrđuje pravno načelo da teret dokazivanja leži na tužitelju...";

        $principles = $extractor->extract($text);

        $this->assertIsArray($principles);
        // At minimum, returns structure even if empty
        $this->assertArrayHasKey('principles', $principles);
    }
}
```

**Step 3: Run test to verify it fails**

Run: `php vendor/bin/phpunit tests/Unit/Services/Graph/LegalPrincipleExtractorTest.php -v`
Expected: FAIL

**Step 4: Write minimal implementation**

```php
<?php

namespace App\Services\Graph;

/**
 * Extract legal principles from court decision text
 *
 * Uses pattern matching and NLP to identify:
 * - Explicit principle statements ("Sud utvrđuje pravno načelo...")
 * - Ratio decidendi markers
 * - Obiter dicta with doctrinal significance
 */
class LegalPrincipleExtractor
{
    /**
     * Croatian legal principle indicators
     */
    protected array $principlePatterns = [
        '/pravno\s+načelo/ui',
        '/temeljna\s+pravna\s+zasada/ui',
        '/sud\s+zauzima\s+stajalište/ui',
        '/ustaljen[ao]?\s+sudsk[ao]\s+praksa/ui',
        '/opće\s+pravno\s+pravilo/ui',
    ];

    /**
     * Extract legal principles from text
     *
     * @param string $text Decision text
     * @return array Extracted principles with metadata
     */
    public function extract(string $text): array
    {
        $principles = [];

        foreach ($this->principlePatterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    // Extract surrounding context (200 chars before/after)
                    $start = max(0, $match[1] - 200);
                    $length = min(strlen($text) - $start, 500);
                    $context = substr($text, $start, $length);

                    $principles[] = [
                        'pattern_matched' => $match[0],
                        'context' => trim($context),
                        'position' => $match[1],
                    ];
                }
            }
        }

        return [
            'principles' => $principles,
            'count' => count($principles),
        ];
    }
}
```

**Step 5: Run test to verify it passes**

Run: `php vendor/bin/phpunit tests/Unit/Services/Graph/LegalPrincipleExtractorTest.php -v`
Expected: PASS

**Step 6: Commit**

```bash
git add -A
git commit -m "feat(graph): add LegalPrinciple node type and extractor"
```

---

### Task 2.4: Add Party Node Type (Litigants)

**Files:**
- Modify: `documentation/GRAPH_SCHEMA.cypher`
- Create: `database/migrations/2026_01_06_000003_add_party_node_schema.php`

**Step 1: Document schema**

Add to `GRAPH_SCHEMA.cypher`:

```cypher
// Party (Litigants in cases)
(:Party {
  id: String,                    // ULID or hash
  name: String,                  // Party name (may be anonymized)
  party_type: String,            // "individual", "company", "government", "institution"
  anonymized: Boolean,           // Whether name is anonymized
  oib: String,                   // Croatian OIB if available (hashed for privacy)
  created_at: String,
  updated_at: String
})

// Party role in decision
(:Party)-[:PARTY_TO {
  role: String,                  // "plaintiff", "defendant", "intervener", "appellant"
  outcome: String,               // "won", "lost", "partial", "settled"
  created_at: String
}]->(:CourtDecisionDocument)
```

**Step 2: Create migration**

```php
<?php

use App\Services\GraphDatabaseService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    public function up(): void
    {
        if (!config('neo4j.sync.enabled', true)) {
            return;
        }

        try {
            $graph = app(GraphDatabaseService::class);
        } catch (\Exception $e) {
            Log::warning('Neo4j not available for party schema migration');
            return;
        }

        // Add Party constraint
        $graph->run('CREATE CONSTRAINT party_id IF NOT EXISTS FOR (p:Party) REQUIRE p.id IS UNIQUE');

        // Add Party indexes
        $graph->run('CREATE INDEX party_type_idx IF NOT EXISTS FOR (p:Party) ON (p.party_type)');

        Log::info('Party node schema added to Neo4j');
    }

    public function down(): void
    {
        Log::info('Party schema rollback - constraints remain');
    }
};
```

**Step 3: Run migration**

Run: `php artisan migrate`
Expected: SUCCESS

**Step 4: Commit**

```bash
git add -A
git commit -m "feat(graph): add Party node type for litigant tracking"
```

---

## Phase 3: Enhanced Relationship Types

### Overview
Add missing precedent relationships for complete appellate tracking.

---

### Task 3.1: Add REVERSED, VACATED, REMANDED Relationships

**Files:**
- Modify: `documentation/GRAPH_SCHEMA.cypher`
- Create: `app/Services/Graph/AppellatePrecedentLinker.php`
- Create: `tests/Unit/Services/Graph/AppellatePrecedentLinkerTest.php`

**Step 1: Document schema additions**

```cypher
// Court Decision completely reverses another
(:CourtDecisionDocument)-[:REVERSED {
  reversal_type: String,         // "full", "partial"
  grounds: String,               // Legal grounds for reversal
  decision_date: String,
  created_at: String
}]->(:CourtDecisionDocument)

// Court Decision vacates (nullifies) another
(:CourtDecisionDocument)-[:VACATED {
  reason: String,                // Reason for vacating
  decision_date: String,
  created_at: String
}]->(:CourtDecisionDocument)

// Court Decision remands case back to lower court
(:CourtDecisionDocument)-[:REMANDED {
  instructions: String,          // Instructions for lower court
  decision_date: String,
  created_at: String
}]->(:CourtDecisionDocument)

// Decisions that contradict each other (circuit split)
(:CourtDecisionDocument)-[:CONTRADICTS {
  contradiction_type: String,    // "direct", "implicit"
  identified_by: String,         // How contradiction was identified
  created_at: String
}]->(:CourtDecisionDocument)
```

**Step 2: Write the failing test**

```php
<?php

namespace Tests\Unit\Services\Graph;

use App\Services\Graph\AppellatePrecedentLinker;
use App\Services\GraphDatabaseService;
use Mockery;
use Tests\TestCase;

class AppellatePrecedentLinkerTest extends TestCase
{
    /** @test */
    public function it_creates_reversed_relationship(): void
    {
        $mockGraph = Mockery::mock(GraphDatabaseService::class);
        $mockGraph->shouldReceive('createRelationship')
            ->once()
            ->with(
                'CourtDecisionDocument', 'appeal-decision-1',
                'REVERSED',
                'CourtDecisionDocument', 'original-decision-1',
                Mockery::on(fn($p) => $p['reversal_type'] === 'full')
            );

        $linker = new AppellatePrecedentLinker($mockGraph);

        $linker->linkReversed('appeal-decision-1', 'original-decision-1', 'full', 'Procedural error');

        $this->assertTrue(true);
    }
}
```

**Step 3: Run test to verify it fails**

Run: `php vendor/bin/phpunit tests/Unit/Services/Graph/AppellatePrecedentLinkerTest.php -v`
Expected: FAIL

**Step 4: Write minimal implementation**

```php
<?php

namespace App\Services\Graph;

use App\Services\GraphDatabaseService;

/**
 * Service for linking appellate precedent relationships
 */
class AppellatePrecedentLinker
{
    public function __construct(
        protected GraphDatabaseService $graph
    ) {}

    public function linkReversed(string $appealDecisionId, string $originalDecisionId, string $type = 'full', ?string $grounds = null): void
    {
        $this->graph->createRelationship(
            'CourtDecisionDocument', $appealDecisionId,
            'REVERSED',
            'CourtDecisionDocument', $originalDecisionId,
            [
                'reversal_type' => $type,
                'grounds' => $grounds,
                'decision_date' => now()->toDateString(),
                'created_at' => now()->toIso8601String(),
            ]
        );
    }

    public function linkVacated(string $appealDecisionId, string $originalDecisionId, ?string $reason = null): void
    {
        $this->graph->createRelationship(
            'CourtDecisionDocument', $appealDecisionId,
            'VACATED',
            'CourtDecisionDocument', $originalDecisionId,
            [
                'reason' => $reason,
                'decision_date' => now()->toDateString(),
                'created_at' => now()->toIso8601String(),
            ]
        );
    }

    public function linkRemanded(string $appealDecisionId, string $originalDecisionId, ?string $instructions = null): void
    {
        $this->graph->createRelationship(
            'CourtDecisionDocument', $appealDecisionId,
            'REMANDED',
            'CourtDecisionDocument', $originalDecisionId,
            [
                'instructions' => $instructions,
                'decision_date' => now()->toDateString(),
                'created_at' => now()->toIso8601String(),
            ]
        );
    }

    public function linkContradicts(string $decision1Id, string $decision2Id, string $type = 'direct'): void
    {
        $this->graph->createRelationship(
            'CourtDecisionDocument', $decision1Id,
            'CONTRADICTS',
            'CourtDecisionDocument', $decision2Id,
            [
                'contradiction_type' => $type,
                'identified_by' => 'automated',
                'created_at' => now()->toIso8601String(),
            ]
        );
    }
}
```

**Step 5: Run test to verify it passes**

Run: `php vendor/bin/phpunit tests/Unit/Services/Graph/AppellatePrecedentLinkerTest.php -v`
Expected: PASS

**Step 6: Commit**

```bash
git add -A
git commit -m "feat(graph): add appellate precedent relationships (REVERSED, VACATED, REMANDED, CONTRADICTS)"
```

---

### Task 3.2: Add INTERPRETS Relationship for Statutory Interpretation

**Files:**
- Modify: `documentation/GRAPH_SCHEMA.cypher`
- Modify: `app/Services/Graph/GraphCitationLinker.php`
- Create: `tests/Unit/Services/Graph/StatutoryInterpretationTest.php`

**Step 1: Document schema**

```cypher
// Court Decision interprets a Law (statutory interpretation)
(:CourtDecisionDocument)-[:INTERPRETS {
  article: String,               // Specific article interpreted
  interpretation_type: String,   // "literal", "purposive", "systematic", "historical"
  interpretation: String,        // Summary of interpretation
  binding: Boolean,              // Whether interpretation is binding precedent
  created_at: String
}]->(:LawDocument)
```

**Step 2: Write the failing test**

```php
<?php

namespace Tests\Unit\Services\Graph;

use App\Services\Graph\GraphCitationLinker;
use App\Services\GraphDatabaseService;
use Mockery;
use Tests\TestCase;

class StatutoryInterpretationTest extends TestCase
{
    /** @test */
    public function it_creates_interprets_relationship_for_statutory_interpretation(): void
    {
        $mockGraph = Mockery::mock(GraphDatabaseService::class);
        $mockGraph->shouldReceive('run')->andReturn([['id' => 'law-1']]);
        $mockGraph->shouldReceive('createRelationship')
            ->once()
            ->with(
                'CourtDecisionDocument', 'decision-1',
                'INTERPRETS',
                'LawDocument', 'law-1',
                Mockery::on(fn($p) => $p['article'] === '110')
            );

        $linker = new GraphCitationLinker($mockGraph);

        $linker->linkInterpretation('decision-1', 'law-1', '110', 'purposive', 'The article must be interpreted broadly');

        $this->assertTrue(true);
    }
}
```

**Step 3: Run test to verify it fails**

Run: `php vendor/bin/phpunit tests/Unit/Services/Graph/StatutoryInterpretationTest.php -v`
Expected: FAIL

**Step 4: Add implementation to GraphCitationLinker**

Add method to existing `GraphCitationLinker.php`:

```php
/**
 * Link a statutory interpretation relationship
 */
public function linkInterpretation(
    string $decisionId,
    string $lawId,
    string $article,
    string $interpretationType,
    ?string $interpretationSummary = null,
    bool $binding = false
): void {
    $this->graph->createRelationship(
        'CourtDecisionDocument', $decisionId,
        'INTERPRETS',
        'LawDocument', $lawId,
        [
            'article' => $article,
            'interpretation_type' => $interpretationType,
            'interpretation' => $interpretationSummary,
            'binding' => $binding,
            'created_at' => now()->toIso8601String(),
        ]
    );
}
```

**Step 5: Run test to verify it passes**

Run: `php vendor/bin/phpunit tests/Unit/Services/Graph/StatutoryInterpretationTest.php -v`
Expected: PASS

**Step 6: Commit**

```bash
git add -A
git commit -m "feat(graph): add INTERPRETS relationship for statutory interpretation tracking"
```

---

## Phase 4: Enhanced Node Properties

### Overview
Add missing properties to existing nodes for richer metadata.

---

### Task 4.1: Add Outcome Properties to CourtDecisionDocument

**Files:**
- Modify: `documentation/GRAPH_SCHEMA.cypher`
- Modify: `app/Services/Graph/DecisionGraphSyncService.php`
- Modify: `tests/Unit/Services/Graph/DecisionGraphSyncServiceTest.php`

**Step 1: Document property additions**

Add to CourtDecisionDocument in `GRAPH_SCHEMA.cypher`:

```cypher
(:CourtDecisionDocument {
  // ... existing properties ...

  // NEW: Outcome properties
  outcome: String,               // "affirmed", "reversed", "remanded", "dismissed", "settled"
  holding: String,               // Brief statement of holding
  precedential_value: String,    // "binding", "persuasive", "informational"
  dissent_count: Integer,        // Number of dissenting judges
  concurrence_count: Integer,    // Number of concurring opinions
})
```

**Step 2: Write the failing test**

```php
/** @test */
public function it_syncs_outcome_properties(): void
{
    // Mock the database query
    DB::shouldReceive('table->where->first')->andReturn((object)[
        'id' => 'decision-1',
        'case_number' => 'Rev 1/2020',
        'court' => 'Vrhovni sud',
        'outcome' => 'reversed',
        'holding' => 'Appeal granted',
        'precedential_value' => 'binding',
    ]);

    DB::shouldReceive('table->where->get')->andReturn(collect([
        (object)['id' => 'doc-1', 'decision_id' => 'decision-1', 'content' => 'test'],
    ]));

    $this->mockGraph->shouldReceive('upsertNode')
        ->with('CourtDecisionDocument', 'doc-1', Mockery::on(fn($p) =>
            $p['outcome'] === 'reversed' &&
            $p['precedential_value'] === 'binding'
        ));

    // Additional mock expectations...

    $this->service->sync('decision-1');
}
```

**Step 3: Run test to verify it fails**

Run: `php vendor/bin/phpunit tests/Unit/Services/Graph/DecisionGraphSyncServiceTest.php --filter it_syncs_outcome_properties -v`
Expected: FAIL

**Step 4: Update implementation**

Modify `createDecisionDocumentNode` in `DecisionGraphSyncService.php`:

```php
protected function createDecisionDocumentNode($decision, $doc): void
{
    $this->graph->upsertNode('CourtDecisionDocument', $doc->id, [
        // Existing properties
        'decision_id' => $doc->decision_id,
        'doc_id' => $doc->doc_id,
        'title' => $doc->title ?? $decision->title,
        'case_number' => $decision->case_number,
        'court' => $decision->court,
        'jurisdiction' => $decision->jurisdiction,
        'judge' => $decision->judge,
        'decision_date' => $decision->decision_date,
        'publication_date' => $decision->publication_date,
        'decision_type' => $decision->decision_type,
        'register' => $decision->register,
        'finality' => $decision->finality,
        'ecli' => $decision->ecli,
        'chunk_index' => $doc->chunk_index,
        'content_hash' => $doc->content_hash,

        // NEW: Outcome properties
        'outcome' => $decision->outcome ?? null,
        'holding' => $decision->holding ?? null,
        'precedential_value' => $decision->precedential_value ?? 'informational',
        'dissent_count' => $decision->dissent_count ?? 0,
        'concurrence_count' => $decision->concurrence_count ?? 0,
    ]);
}
```

**Step 5: Run test to verify it passes**

Run: `php vendor/bin/phpunit tests/Unit/Services/Graph/DecisionGraphSyncServiceTest.php --filter it_syncs_outcome_properties -v`
Expected: PASS

**Step 6: Commit**

```bash
git add -A
git commit -m "feat(graph): add outcome properties to CourtDecisionDocument node"
```

---

### Task 4.2: Add Amendment Tracking to LawDocument

**Files:**
- Modify: `documentation/GRAPH_SCHEMA.cypher`
- Modify: `app/Services/Graph/LawGraphSyncService.php`

**Step 1: Document schema**

```cypher
(:LawDocument {
  // ... existing properties ...

  // NEW: Amendment tracking
  amendments: [String],          // Array of amendment NN numbers
  repeal_date: String,           // ISO8601 if repealed
  repealed_by: String,           // NN number of repealing law
  parent_law_number: String,     // If this is an amendment, reference to parent
  consolidation_date: String,    // Last consolidation date
})

// Law amends another Law
(:LawDocument)-[:AMENDS {
  amendment_date: String,
  amendment_type: String,        // "modification", "addition", "repeal"
  affected_articles: [String],   // List of affected articles
  created_at: String
}]->(:LawDocument)

// Law is superseded by another Law
(:LawDocument)-[:SUPERSEDED_BY {
  effective_date: String,
  created_at: String
}]->(:LawDocument)
```

**Step 2: Implement**

```php
// In LawGraphSyncService.php
public function linkAmendment(string $amendingLawId, string $originalLawId, array $affectedArticles): void
{
    $this->graph->createRelationship(
        'LawDocument', $amendingLawId,
        'AMENDS',
        'LawDocument', $originalLawId,
        [
            'amendment_date' => now()->toDateString(),
            'amendment_type' => 'modification',
            'affected_articles' => $affectedArticles,
            'created_at' => now()->toIso8601String(),
        ]
    );
}
```

**Step 3: Commit**

```bash
git add -A
git commit -m "feat(graph): add amendment tracking to LawDocument"
```

---

## Phase 5: GraphViewer UI Enhancements

### Overview
Update GraphViewer to display new entities and relationships.

---

### Task 5.1: Add Judge Panel to GraphViewer

**Files:**
- Modify: `resources/views/livewire/graph-viewer.blade.php`
- Modify: `app/Http/Livewire/GraphViewer.php`

**Step 1: Add judge display to node details**

In `graph-viewer.blade.php`, add after Node Details section:

```blade
@if($selectedNodeType === 'CourtDecisionDocument' && !empty($selectedNode['judge']))
<div class="card mt-4">
    <div class="card-header">
        <h2 class="card-title">Presiding Judge(s)</h2>
    </div>
    <div class="card-body">
        @foreach(explode(',', $selectedNode['judge']) as $judge)
        <div class="flex items-center gap-2 py-2 border-b border-gray-700 last:border-0">
            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
            <span class="text-gray-200">{{ trim($judge) }}</span>
        </div>
        @endforeach
    </div>
</div>
@endif
```

**Step 2: Commit**

```bash
git add -A
git commit -m "feat(ui): add judge display panel to GraphViewer"
```

---

### Task 5.2: Add Precedent Status Indicator

**Files:**
- Modify: `resources/views/livewire/graph-viewer.blade.php`
- Modify: `app/Http/Livewire/GraphViewer.php`

**Step 1: Add precedent status badge**

```blade
@if($selectedNodeType === 'CourtDecisionDocument')
<div class="flex gap-2 mt-2">
    @if(isset($selectedNode['precedential_value']))
    <span class="px-2 py-1 text-xs rounded
        @if($selectedNode['precedential_value'] === 'binding') bg-green-600
        @elseif($selectedNode['precedential_value'] === 'persuasive') bg-yellow-600
        @else bg-gray-600 @endif">
        {{ ucfirst($selectedNode['precedential_value']) }}
    </span>
    @endif

    @if(isset($selectedNode['outcome']))
    <span class="px-2 py-1 text-xs rounded
        @if($selectedNode['outcome'] === 'affirmed') bg-green-600
        @elseif($selectedNode['outcome'] === 'reversed') bg-red-600
        @elseif($selectedNode['outcome'] === 'remanded') bg-yellow-600
        @else bg-gray-600 @endif">
        {{ ucfirst($selectedNode['outcome']) }}
    </span>
    @endif
</div>
@endif
```

**Step 2: Commit**

```bash
git add -A
git commit -m "feat(ui): add precedent status indicators to GraphViewer"
```

---

## Summary Checklist

### Phase 0: Data Quality Fixes (Critical Pre-requisite)
- [ ] Task 0.1: Fix Tags Unicode Encoding with JsonUnescaped Cast
- [ ] Task 0.2: Enhance GraphCitationLinker with HrLegalCitationsDetector
- [ ] Task 0.3: Handle Missing Fields from Parent Decision
- [ ] Task 0.4: Add Graph Re-sync Command for Existing Data

### Phase 1: Data Integrity (Foundation)
- [ ] Task 1.1: Create GraphDataIntegrityService
- [ ] Task 1.2: Add Missing Node Detection
- [ ] Task 1.3: Add Relationship Consistency Validation
- [ ] Task 1.4: Add Integrity Report Generation
- [ ] Task 1.5: Create Artisan Command

### Phase 2: Enhanced Entity Types
- [ ] Task 2.1: Add Judge Node Type
- [ ] Task 2.2: Create JudgeGraphSyncService
- [ ] Task 2.3: Add LegalPrinciple Node Type
- [ ] Task 2.4: Add Party Node Type

### Phase 3: Enhanced Relationships
- [ ] Task 3.1: Add REVERSED, VACATED, REMANDED, CONTRADICTS
- [ ] Task 3.2: Add INTERPRETS Relationship

### Phase 4: Enhanced Properties
- [ ] Task 4.1: Add Outcome Properties to CourtDecisionDocument
- [ ] Task 4.2: Add Amendment Tracking to LawDocument

### Phase 5: UI Enhancements
- [ ] Task 5.1: Add Judge Panel to GraphViewer
- [ ] Task 5.2: Add Precedent Status Indicator

---

## Estimated Effort

| Phase | Tasks | Est. Time |
|-------|-------|-----------|
| **Phase 0** | **4** | **1-2 hours** |
| Phase 1 | 5 | 2-3 hours |
| Phase 2 | 4 | 3-4 hours |
| Phase 3 | 2 | 1-2 hours |
| Phase 4 | 2 | 1-2 hours |
| Phase 5 | 2 | 1-2 hours |
| **Total** | **19** | **9-15 hours** |

**Priority Note:** Phase 0 MUST be completed first as it fixes data quality issues that affect all subsequent phases.

---

## References

- `@documentation/GRAPH_SCHEMA.cypher` - Current schema
- `@app/Services/Graph/DecisionGraphSyncService.php` - Sync service
- `@app/Http/Livewire/GraphViewer.php` - UI component
- `@.claude/skills/test-driven-development` - TDD process

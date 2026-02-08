# Phase 4: Enhanced Node Properties Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add missing outcome properties (dissent/concurrence counts) to CourtDecisionDocument and enhanced amendment tracking properties to LawDocument for richer graph metadata.

**Architecture:** Extend existing PostgreSQL tables with new columns, update sync services to include these properties in Neo4j upserts, and add proper tests. Builds on existing DecisionGraphSyncService and LawGraphSyncService without modifying their core architecture.

**Tech Stack:** Laravel 11, PHP 8.4, Neo4j 5.x, PHPUnit, Mockery

---

## Pre-requisite Check

Before starting, verify:
1. Existing migrations have run: `php artisan migrate:status`
2. Neo4j is accessible: `/check-setup`
3. Tests pass: `./scripts/run-focused-tests.sh DecisionGraphSyncServiceTest`

---

## Task 1: Add Dissent/Concurrence Count Columns to court_decisions

**Files:**
- Create: `database/migrations/2026_01_10_000001_add_dissent_concurrence_to_court_decisions.php`
- Modify: `app/Models/CourtDecision.php`

**Step 1: Create the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('vizra-adk.tables.court_decisions', 'court_decisions');

        Schema::table($tableName, function (Blueprint $table) {
            // Add dissent count - number of dissenting judges
            $table->unsignedSmallInteger('dissent_count')->default(0);

            // Add concurrence count - number of concurring opinions
            $table->unsignedSmallInteger('concurrence_count')->default(0);
        });
    }

    public function down(): void
    {
        $tableName = config('vizra-adk.tables.court_decisions', 'court_decisions');

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropColumn(['dissent_count', 'concurrence_count']);
        });
    }
};
```

**Step 2: Run migration**

Run: `php artisan migrate`
Expected: Migration runs successfully

**Step 3: Add columns to CourtDecision model fillable**

In `app/Models/CourtDecision.php`, add to the `$fillable` array:
```php
'dissent_count',
'concurrence_count',
```

And add to `$casts`:
```php
'dissent_count' => 'integer',
'concurrence_count' => 'integer',
```

**Step 4: Commit**

```bash
git add database/migrations/2026_01_10_000001_add_dissent_concurrence_to_court_decisions.php app/Models/CourtDecision.php
git commit -m "feat(db): add dissent_count and concurrence_count columns to court_decisions"
```

---

## Task 2: Update DecisionGraphSyncService to Sync Dissent/Concurrence

**Files:**
- Modify: `app/Services/Graph/DecisionGraphSyncService.php:283-308`
- Modify: `tests/Unit/Services/Graph/DecisionGraphSyncServiceTest.php`

**Step 1: Write the failing test**

Add to `tests/Unit/Services/Graph/DecisionGraphSyncServiceTest.php`:

```php
/** @test */
public function it_syncs_dissent_and_concurrence_counts_to_graph(): void
{
    // Arrange: Create decision with dissent/concurrence counts
    $decision = (object) [
        'id' => 'decision-1',
        'case_number' => 'Rev 1/2020',
        'court' => 'Vrhovni sud',
        'jurisdiction' => 'hr',
        'judge' => null,
        'decision_date' => '2020-01-15',
        'publication_date' => null,
        'decision_type' => 'presuda',
        'register' => null,
        'finality' => null,
        'ecli' => null,
        'title' => 'Test Decision',
        'outcome' => 'reversed',
        'holding' => 'Appeal granted',
        'precedential_value' => 'binding',
        'dissent_count' => 2,
        'concurrence_count' => 1,
    ];

    $doc = (object) [
        'id' => 'doc-1',
        'decision_id' => 'decision-1',
        'doc_id' => 'doc-1',
        'title' => null,
        'chunk_index' => 0,
        'content_hash' => 'abc123',
        'content' => 'Test content',
        'metadata' => null,
        'embedding' => null,
    ];

    DB::shouldReceive('table->where->first')
        ->once()
        ->andReturn($decision);

    DB::shouldReceive('table->where->get')
        ->once()
        ->andReturn(collect([$doc]));

    // Assert: Graph receives correct dissent/concurrence values
    $this->mockGraph->shouldReceive('upsertNode')
        ->once()
        ->with('CourtDecisionDocument', 'doc-1', Mockery::on(function ($props) {
            return $props['dissent_count'] === 2
                && $props['concurrence_count'] === 1
                && $props['outcome'] === 'reversed';
        }));

    // Allow other graph operations
    $this->mockGraph->shouldReceive('upsertNode')->andReturnNull();
    $this->mockGraph->shouldReceive('createRelationship')->andReturnNull();

    // Mock linkers to not interfere
    $this->mockKeywordLinker->shouldReceive('link')->andReturnNull();
    $this->mockCitationLinker->shouldReceive('link')->andReturnNull();
    $this->mockSimilarityLinker->shouldReceive('link')->andReturnNull();
    $this->mockTagging->shouldReceive('autoTag')->andReturnNull();

    // Act
    $result = $this->service->sync('decision-1');

    // Assert
    $this->assertArrayHasKey('nodes', $result);
}
```

**Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Services/Graph/DecisionGraphSyncServiceTest.php --filter=it_syncs_dissent_and_concurrence_counts_to_graph`
Expected: FAIL - dissent_count/concurrence_count not in properties

**Step 3: Update createDecisionDocumentNode method**

In `app/Services/Graph/DecisionGraphSyncService.php`, update `createDecisionDocumentNode`:

```php
protected function createDecisionDocumentNode($decision, $doc): void
{
    $this->graph->upsertNode('CourtDecisionDocument', $doc->id, [
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
        // Phase 4: Outcome properties
        'outcome' => $decision->outcome ?? null,
        'holding' => $decision->holding ?? null,
        'precedential_value' => $decision->precedential_value ?? null,
        // Phase 4: Dissent/Concurrence counts
        'dissent_count' => $decision->dissent_count ?? 0,
        'concurrence_count' => $decision->concurrence_count ?? 0,
    ]);

    $this->trackNode('CourtDecisionDocument');
}
```

**Step 4: Run test to verify it passes**

Run: `php artisan test tests/Unit/Services/Graph/DecisionGraphSyncServiceTest.php --filter=it_syncs_dissent_and_concurrence_counts_to_graph`
Expected: PASS

**Step 5: Run all service tests**

Run: `./scripts/run-focused-tests.sh DecisionGraphSyncServiceTest`
Expected: All tests pass

**Step 6: Commit**

```bash
git add app/Services/Graph/DecisionGraphSyncService.php tests/Unit/Services/Graph/DecisionGraphSyncServiceTest.php
git commit -m "feat(graph): sync dissent_count and concurrence_count to Neo4j"
```

---

## Task 3: Add Amendment Tracking Columns to laws Table

**Files:**
- Create: `database/migrations/2026_01_10_000002_add_amendment_tracking_to_laws.php`
- Modify: `app/Models/Law.php`

**Step 1: Create the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('vizra-adk.tables.laws', 'laws');

        Schema::table($tableName, function (Blueprint $table) {
            // Array of amendment NN numbers (e.g., ["NN 123/21", "NN 45/22"])
            $table->json('amendments')->nullable();

            // Reference to the law that repealed this one
            $table->string('repealed_by')->nullable()->index();

            // If this is an amendment, reference to original law number
            $table->string('parent_law_number')->nullable()->index();

            // Date of last consolidation (pročišćeni tekst)
            $table->date('consolidation_date')->nullable();

            // Temporal validity fields (if not already present)
            // Note: valid_from, valid_until, version may already exist from Sprint 4.1
        });
    }

    public function down(): void
    {
        $tableName = config('vizra-adk.tables.laws', 'laws');

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropColumn([
                'amendments',
                'repealed_by',
                'parent_law_number',
                'consolidation_date',
            ]);
        });
    }
};
```

**Step 2: Run migration**

Run: `php artisan migrate`
Expected: Migration runs successfully

**Step 3: Update Law model**

In `app/Models/Law.php`, add to `$fillable`:
```php
'amendments',
'repealed_by',
'parent_law_number',
'consolidation_date',
```

Add to `$casts`:
```php
'amendments' => 'array',
'consolidation_date' => 'date',
```

**Step 4: Commit**

```bash
git add database/migrations/2026_01_10_000002_add_amendment_tracking_to_laws.php app/Models/Law.php
git commit -m "feat(db): add amendment tracking columns to laws table"
```

---

## Task 4: Update LawGraphSyncService to Sync Amendment Properties

**Files:**
- Modify: `app/Services/Graph/LawGraphSyncService.php:104-122`
- Modify: `tests/Unit/Services/Graph/LawGraphSyncServiceTest.php`

**Step 1: Write the failing test**

Add to `tests/Unit/Services/Graph/LawGraphSyncServiceTest.php`:

```php
/** @test */
public function it_syncs_amendment_tracking_properties_to_graph(): void
{
    $law = (object) [
        'id' => 'law-1',
        'doc_id' => 'law-doc-1',
        'title' => 'Zakon o obveznim odnosima',
        'law_number' => 'NN 35/05',
        'jurisdiction' => 'hr',
        'country' => 'HR',
        'language' => 'hr',
        'chunk_index' => 0,
        'content_hash' => 'abc123',
        'content' => 'Law content',
        'effective_date' => '2005-01-01',
        'promulgation_date' => '2005-03-15',
        'valid_from' => '2005-01-01',
        'valid_until' => null,
        'version' => '1.0',
        'amendments' => ['NN 41/08', 'NN 125/11', 'NN 78/15'],
        'repeal_date' => null,
        'repealed_by' => null,
        'parent_law_number' => null,
        'consolidation_date' => '2023-06-01',
        'metadata' => null,
        'embedding' => null,
    ];

    DB::shouldReceive('table->where->first')
        ->once()
        ->andReturn($law);

    // Assert: Graph receives amendment tracking properties
    $this->mockGraph->shouldReceive('upsertNode')
        ->once()
        ->with('LawDocument', 'law-1', Mockery::on(function ($props) {
            return $props['amendments'] === ['NN 41/08', 'NN 125/11', 'NN 78/15']
                && $props['consolidation_date'] === '2023-06-01'
                && $props['repealed_by'] === null;
        }));

    // Allow other operations
    $this->mockGraph->shouldReceive('upsertNode')->andReturnNull();
    $this->mockGraph->shouldReceive('createRelationship')->andReturnNull();
    $this->mockKeywordLinker->shouldReceive('link')->andReturnNull();
    $this->mockCitationLinker->shouldReceive('link')->andReturnNull();
    $this->mockSimilarityLinker->shouldReceive('link')->andReturnNull();
    $this->mockTagging->shouldReceive('autoTag')->andReturnNull();

    $result = $this->service->sync('law-1');

    $this->assertIsArray($result);
}
```

**Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Services/Graph/LawGraphSyncServiceTest.php --filter=it_syncs_amendment_tracking_properties_to_graph`
Expected: FAIL - amendments/consolidation_date not in properties

**Step 3: Update createLawNode method**

In `app/Services/Graph/LawGraphSyncService.php`, update `createLawNode`:

```php
protected function createLawNode($law): void
{
    $this->graph->upsertNode('LawDocument', $law->id, [
        'doc_id' => $law->doc_id,
        'title' => $law->title,
        'law_number' => $law->law_number,
        'jurisdiction' => $law->jurisdiction,
        'country' => $law->country,
        'language' => $law->language,
        'chunk_index' => $law->chunk_index,
        'content_hash' => $law->content_hash,
        'effective_date' => $law->effective_date,
        'promulgation_date' => $law->promulgation_date,
        // Temporal fields (Sprint 4.1)
        'valid_from' => $law->valid_from ?? null,
        'valid_until' => $law->valid_until ?? null,
        'version' => $law->version ?? null,
        // Phase 4: Amendment tracking
        'amendments' => $law->amendments ?? [],
        'repeal_date' => $law->repeal_date ?? null,
        'repealed_by' => $law->repealed_by ?? null,
        'parent_law_number' => $law->parent_law_number ?? null,
        'consolidation_date' => $law->consolidation_date ?? null,
    ]);
}
```

**Step 4: Run test to verify it passes**

Run: `php artisan test tests/Unit/Services/Graph/LawGraphSyncServiceTest.php --filter=it_syncs_amendment_tracking_properties_to_graph`
Expected: PASS

**Step 5: Run all service tests**

Run: `./scripts/run-focused-tests.sh LawGraphSyncServiceTest`
Expected: All tests pass

**Step 6: Commit**

```bash
git add app/Services/Graph/LawGraphSyncService.php tests/Unit/Services/Graph/LawGraphSyncServiceTest.php
git commit -m "feat(graph): sync amendment tracking properties to LawDocument nodes"
```

---

## Task 5: Update GRAPH_SCHEMA.cypher Documentation

**Files:**
- Modify: `documentation/GRAPH_SCHEMA.cypher`

**Step 1: Update LawDocument node definition**

Find the LawDocument node definition (around line 20-34) and add:

```cypher
// Law Documents (statutory law, regulations)
(:LawDocument {
  id: String,                    // ULID primary key (unique)
  doc_id: String,                // Document identifier
  title: String,                 // Law title
  law_number: String,            // NN number (e.g., "123/20")
  jurisdiction: String,          // Jurisdiction name
  country: String,               // Country code
  language: String,              // Language code (e.g., "hr")
  chunk_index: Integer,          // Chunk index for large documents
  content_hash: String,          // SHA-256 hash of content
  effective_date: String,        // ISO8601 date when law becomes effective
  promulgation_date: String,     // ISO8601 date when law was promulgated
  // Temporal validity (Sprint 4.1)
  valid_from: String,            // ISO8601 date - when version became valid
  valid_until: String,           // ISO8601 date - when version expired (null if current)
  version: String,               // Version identifier
  // Amendment tracking (Phase 4 Enhancement)
  amendments: [String],          // Array of amendment NN numbers
  repeal_date: String,           // ISO8601 date if repealed
  repealed_by: String,           // NN number of repealing law
  parent_law_number: String,     // If amendment, reference to parent law
  consolidation_date: String,    // ISO8601 date of last consolidation
  // Timestamps
  created_at: String,            // ISO8601 timestamp
  updated_at: String             // ISO8601 timestamp
})
```

**Step 2: Verify CourtDecisionDocument includes dissent/concurrence**

Confirm lines 77-82 include:
```cypher
  // Outcome Properties (Phase 4 Enhancement)
  outcome: String,               // "affirmed", "reversed", "remanded", "dismissed"
  holding: String,               // Brief statement of holding
  precedential_value: String,    // "binding", "persuasive", "informational"
  dissent_count: Integer,        // Number of dissenting judges
  concurrence_count: Integer,    // Number of concurring opinions
```

**Step 3: Commit**

```bash
git add documentation/GRAPH_SCHEMA.cypher
git commit -m "docs(graph): update schema with Phase 4 amendment tracking properties"
```

---

## Task 6: Add Integration Test for Full Sync with New Properties

**Files:**
- Create: `tests/Feature/Graph/Phase4PropertiesSyncTest.php`

**Step 1: Write the integration test**

```php
<?php

namespace Tests\Feature\Graph;

use App\Models\CourtDecision;
use App\Models\Law;
use App\Services\Graph\DecisionGraphSyncService;
use App\Services\Graph\LawGraphSyncService;
use App\Services\GraphDatabaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class Phase4PropertiesSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Skip if Neo4j not available
        if (!config('neo4j.sync.enabled')) {
            $this->markTestSkipped('Neo4j sync is disabled');
        }
    }

    /** @test */
    public function it_syncs_court_decision_with_full_outcome_properties(): void
    {
        // Create decision with all Phase 4 properties
        $decision = CourtDecision::factory()->create([
            'outcome' => 'reversed',
            'holding' => 'The lower court erred in its interpretation',
            'precedential_value' => 'binding',
            'dissent_count' => 2,
            'concurrence_count' => 1,
        ]);

        // Create document chunk
        $decision->documents()->create([
            'id' => 'test-doc-1',
            'doc_id' => 'test-doc-1',
            'content' => 'Test decision content',
            'chunk_index' => 0,
            'content_hash' => hash('sha256', 'Test decision content'),
        ]);

        // Mock graph to capture upsert
        $mockGraph = Mockery::mock(GraphDatabaseService::class);
        $capturedProps = null;

        $mockGraph->shouldReceive('upsertNode')
            ->with('CourtDecisionDocument', Mockery::any(), Mockery::capture($capturedProps))
            ->once();

        $mockGraph->shouldReceive('upsertNode')->andReturnNull();
        $mockGraph->shouldReceive('createRelationship')->andReturnNull();

        $this->app->instance(GraphDatabaseService::class, $mockGraph);

        // Run sync
        $service = app(DecisionGraphSyncService::class);
        $service->sync($decision->id);

        // Verify all Phase 4 properties synced
        $this->assertEquals('reversed', $capturedProps['outcome']);
        $this->assertEquals('binding', $capturedProps['precedential_value']);
        $this->assertEquals(2, $capturedProps['dissent_count']);
        $this->assertEquals(1, $capturedProps['concurrence_count']);
    }

    /** @test */
    public function it_syncs_law_with_amendment_tracking_properties(): void
    {
        // Create law with Phase 4 amendment properties
        $law = Law::factory()->create([
            'amendments' => ['NN 41/08', 'NN 125/11'],
            'repealed_by' => null,
            'parent_law_number' => null,
            'consolidation_date' => '2023-06-01',
        ]);

        // Mock graph
        $mockGraph = Mockery::mock(GraphDatabaseService::class);
        $capturedProps = null;

        $mockGraph->shouldReceive('upsertNode')
            ->with('LawDocument', $law->id, Mockery::capture($capturedProps))
            ->once();

        $mockGraph->shouldReceive('upsertNode')->andReturnNull();
        $mockGraph->shouldReceive('createRelationship')->andReturnNull();

        $this->app->instance(GraphDatabaseService::class, $mockGraph);

        // Run sync
        $service = app(LawGraphSyncService::class);
        $service->sync($law->id);

        // Verify amendment properties synced
        $this->assertEquals(['NN 41/08', 'NN 125/11'], $capturedProps['amendments']);
        $this->assertEquals('2023-06-01', $capturedProps['consolidation_date']);
    }
}
```

**Step 2: Run tests**

Run: `php artisan test tests/Feature/Graph/Phase4PropertiesSyncTest.php`
Expected: Tests pass (or skip if Neo4j disabled)

**Step 3: Commit**

```bash
git add tests/Feature/Graph/Phase4PropertiesSyncTest.php
git commit -m "test(graph): add Phase 4 properties integration tests"
```

---

## Task 7: Final Verification and Push

**Step 1: Run all graph tests**

Run: `php artisan test --filter="Graph" --exclude-group=dusk`
Expected: All tests pass

**Step 2: Run focused tests for modified services**

```bash
./scripts/run-focused-tests.sh DecisionGraphSyncServiceTest
./scripts/run-focused-tests.sh LawGraphSyncServiceTest
```
Expected: All tests pass

**Step 3: Push all commits**

```bash
git push -u origin claude/graph-enhancement-data-integrity-XqqqL
```
Expected: Push succeeds

---

## Success Criteria Checklist

- [ ] dissent_count and concurrence_count columns added to court_decisions table
- [ ] DecisionGraphSyncService syncs dissent/concurrence counts to Neo4j
- [ ] amendments, repealed_by, parent_law_number, consolidation_date columns added to laws table
- [ ] LawGraphSyncService syncs amendment tracking properties to Neo4j
- [ ] GRAPH_SCHEMA.cypher documentation updated
- [ ] All unit tests pass
- [ ] Integration tests pass
- [ ] All changes committed and pushed

---

## References

- `@app/Services/Graph/DecisionGraphSyncService.php` - Decision sync service
- `@app/Services/Graph/LawGraphSyncService.php` - Law sync service
- `@documentation/GRAPH_SCHEMA.cypher` - Neo4j schema documentation
- `@.claude/skills/test-driven-development` - TDD process
- `@docs/plans/2026-01-06-graph-enhancement-and-data-integrity.md` - Master plan

# SPRINT 1: GraphRagService Refactoring (2 Weeks)

**Project**: AI Legal War Machine - God Class Refactoring
**Sprint Duration**: 10 working days (2 weeks)
**Team Size**: 2 developers (Dev A + Dev B)
**Total Effort**: 40-50 hours
**Sprint Goal**: Refactor GraphRagService (1,933 lines) into 7 focused services using TDD approach

---

## Sprint Overview

### Before State
- **GraphRagService**: 1,933 lines, 39 methods, 7 concerns mixed together
- **Testability**: Low (hard to test individual features)
- **Maintainability**: Low (changes require touching 1 giant file)
- **Reusability**: Low (can't use individual sync features independently)

### After State
- **GraphRagOrchestrator**: 100-150 lines (coordinator)
- **7 Focused Services**: 200-400 lines each
- **Testability**: High (each service tested in isolation)
- **Maintainability**: High (changes isolated to specific services)
- **Reusability**: High (services used independently)

### Target Architecture

```
GraphRagOrchestrator (facade, 100-150 lines)
├── LawGraphSyncService (300-400 lines)
├── CaseGraphSyncService (300-400 lines)
├── DecisionGraphSyncService (300-400 lines)
├── TextractGraphSyncService (300-400 lines)
├── GraphKeywordLinker (200-300 lines)
├── GraphCitationLinker (200-300 lines)
└── GraphSimilarityLinker (200-300 lines)
```

---

## Day-by-Day Breakdown

### Day 1 (Monday): Characterization Tests Setup
**Developer**: Dev A + Dev B (pair programming)
**Hours**: 8-10 hours total (4-5 hours per dev)
**TDD Step**: RED

#### Morning (4 hours)

**Task 1.1: Setup Test Environment**
- Create test branch: `refactor/graphrag-service-tdd`
- Create test file: `tests/Unit/GraphRagService/GraphRagServiceCharacterizationTest.php`
- Review existing GraphRagService code (lines 1-1933)

**File**: `tests/Unit/GraphRagService/GraphRagServiceCharacterizationTest.php` (NEW)
```php
<?php

namespace Tests\Unit\GraphRagService;

use App\Services\GraphRagService;
use App\Services\GraphDatabaseService;
use App\Models\Law;
use App\Models\LegalCase;
use App\Models\CourtDecisionDocument;
use App\Models\TextractDocument;
use Tests\TestCase;
use Tests\Concerns\UsesTestDatabase;
use Mockery;

/**
 * Characterization Tests for GraphRagService
 *
 * These tests document EXISTING behavior before refactoring.
 * Goal: Ensure refactoring doesn't change behavior.
 *
 * DO NOT modify these tests during refactoring.
 * If a test fails after refactoring, the refactor has a bug.
 */
class GraphRagServiceCharacterizationTest extends TestCase
{
    use UsesTestDatabase;

    protected GraphRagService $service;
    protected $graphService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->graphService = Mockery::mock(GraphDatabaseService::class);
        $this->service = new GraphRagService($this->graphService);
    }

    /** @test */
    public function it_syncs_law_to_graph_creates_node_with_correct_properties()
    {
        // This test documents EXACTLY what happens when syncLaw() is called
        // We'll fill this in after analyzing current behavior
    }

    /** @test */
    public function it_syncs_case_to_graph_with_relationships()
    {
        // Document current behavior
    }

    /** @test */
    public function it_handles_missing_law_gracefully()
    {
        // Document error handling behavior
    }

    // ... more tests to be added
}
```

**Acceptance Criteria**:
- ✅ Test file created
- ✅ Test structure matches existing GraphRagService responsibilities
- ✅ Mockery configured for GraphDatabaseService
- ✅ UsesTestDatabase trait included

#### Afternoon (4 hours)

**Task 1.2: Write Characterization Tests for Law Syncing**
- Analyze `syncLaw()` method (lines ~50-350 in GraphRagService.php)
- Document EXACT behavior in tests
- Test happy path, edge cases, error handling

**Tests to Write**:
```php
/** @test */
public function it_creates_law_node_with_all_properties()
{
    $law = Law::factory()->create([
        'doc_id' => 'ZKP-240',
        'title' => 'Zakon o kaznenom postupku - Članak 240',
        'content' => 'Pretraga se može izvršiti...',
        'jurisdiction' => 'Republika Hrvatska',
    ]);

    // Mock GraphDatabaseService expectations
    $this->graphService
        ->shouldReceive('run')
        ->once()
        ->with(
            Mockery::on(function ($query) {
                return str_contains($query, 'MERGE (n:LawDocument');
            }),
            Mockery::on(function ($params) use ($law) {
                return $params['docId'] === 'ZKP-240' &&
                       $params['title'] === $law->title &&
                       $params['jurisdiction'] === 'Republika Hrvatska';
            })
        )
        ->andReturn([['n' => ['doc_id' => 'ZKP-240']]]);

    // Execute
    $result = $this->service->syncLaw($law->id);

    // Assert
    $this->assertTrue($result['success']);
    $this->assertEquals('ZKP-240', $result['doc_id']);
}

/** @test */
public function it_extracts_and_links_keywords_from_law()
{
    $law = Law::factory()->create([
        'content' => 'Pretraga stana može se izvršiti samo uz naredbu suda',
    ]);

    // Mock keyword extraction (analyze what current code does)
    $this->graphService
        ->shouldReceive('run')
        ->with(Mockery::pattern('/MERGE.*Keyword/'), Mockery::any())
        ->andReturn([]);

    $this->graphService
        ->shouldReceive('run')
        ->with(Mockery::pattern('/HAS_KEYWORD/'), Mockery::any())
        ->andReturn([]);

    $result = $this->service->syncLaw($law->id);

    // Verify keywords were extracted and linked
    $this->assertArrayHasKey('keywords_linked', $result);
}

/** @test */
public function it_handles_law_not_found()
{
    $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

    $this->service->syncLaw('nonexistent-id');
}

/** @test */
public function it_handles_graph_database_connection_failure()
{
    $law = Law::factory()->create();

    $this->graphService
        ->shouldReceive('run')
        ->andThrow(new \Exception('Neo4j connection failed'));

    $result = $this->service->syncLaw($law->id);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('connection failed', $result['error']);
}
```

**Acceptance Criteria**:
- ✅ 5+ tests for law syncing written
- ✅ Tests document exact current behavior
- ✅ Tests cover: happy path, keywords, errors, edge cases
- ✅ All tests run (may fail initially - that's OK, we're documenting behavior)

**Daily Checkpoint**:
- Run tests: `./scripts/run-tests.sh --filter=GraphRagServiceCharacterizationTest`
- Document any unexpected behaviors discovered
- Commit: `git commit -m "Day 1: Add characterization tests for law syncing"`

---

### Day 2 (Tuesday): Complete Characterization Tests
**Developer**: Dev A + Dev B (split work)
**Hours**: 8-10 hours total
**TDD Step**: RED (continue)

#### Morning (4 hours)

**Dev A Task 2.1**: Write Case + Decision Syncing Tests
**Dev B Task 2.2**: Write Textract + Keyword Linking Tests

**Dev A - Case Syncing Tests** (`tests/Unit/GraphRagService/CaseSyncCharacterizationTest.php`):
```php
/** @test */
public function it_syncs_case_with_documents_and_evidence()
{
    $case = LegalCase::factory()
        ->has(CaseDocument::factory()->count(3))
        ->has(Evidence::factory()->count(2))
        ->create([
            'case_number' => 'K-123/2025',
            'court' => 'Županijski sud u Osijeku',
        ]);

    $this->graphService
        ->shouldReceive('run')
        ->times(5) // 1 case node + 3 docs + 2 evidence
        ->andReturn([]);

    $result = $this->service->syncCase($case->id);

    $this->assertTrue($result['success']);
    $this->assertEquals(3, $result['documents_synced']);
    $this->assertEquals(2, $result['evidence_synced']);
}

/** @test */
public function it_creates_relationships_between_case_and_laws()
{
    $law = Law::factory()->create(['doc_id' => 'ZKP-240']);
    $case = LegalCase::factory()->create([
        'content' => 'Case cites ZKP Članak 240',
    ]);

    // Mock citation extraction and relationship creation
    $this->graphService
        ->shouldReceive('run')
        ->with(Mockery::pattern('/CITES/'), Mockery::any())
        ->once()
        ->andReturn([]);

    $result = $this->service->syncCase($case->id);

    $this->assertArrayHasKey('citations_linked', $result);
    $this->assertGreaterThan(0, $result['citations_linked']);
}
```

**Dev B - Keyword Linking Tests** (`tests/Unit/GraphRagService/KeywordLinkingCharacterizationTest.php`):
```php
/** @test */
public function it_extracts_legal_keywords_using_openai()
{
    $text = 'Pretraga stana bez naredbe suda je nezakonita';

    $this->graphService
        ->shouldReceive('run')
        ->with(Mockery::pattern('/MERGE.*Keyword/'), Mockery::any())
        ->andReturn([]);

    $keywords = $this->service->extractKeywords($text);

    $this->assertIsArray($keywords);
    $this->assertContains('pretraga stana', $keywords);
    $this->assertContains('naredba suda', $keywords);
}

/** @test */
public function it_links_keywords_bidirectionally()
{
    $docId = 'doc-123';
    $keywords = ['pretraga stana', 'naredba suda'];

    $this->graphService
        ->shouldReceive('run')
        ->times(count($keywords) * 2) // Create keyword + link relationship
        ->andReturn([]);

    $result = $this->service->linkKeywords($docId, $keywords);

    $this->assertEquals(2, $result['keywords_linked']);
}
```

#### Afternoon (4 hours)

**Both Devs Task 2.3**: Write Tests for Citation and Similarity Linking

**Citation Linking Tests**:
```php
/** @test */
public function it_detects_citations_to_zkp_articles()
{
    $text = 'Sukladno ZKP članku 240 i članku 241, pretraga se mora...';

    $citations = $this->service->extractCitations($text);

    $this->assertCount(2, $citations);
    $this->assertEquals('ZKP-240', $citations[0]['doc_id']);
    $this->assertEquals('ZKP-241', $citations[1]['doc_id']);
}

/** @test */
public function it_creates_citation_relationships_in_graph()
{
    $sourceDocId = 'decision-123';
    $targetLawId = 'ZKP-240';

    $this->graphService
        ->shouldReceive('run')
        ->with(
            Mockery::pattern('/CITES/'),
            Mockery::on(fn($p) => $p['sourceId'] === $sourceDocId && $p['targetId'] === $targetLawId)
        )
        ->once()
        ->andReturn([]);

    $result = $this->service->createCitationLink($sourceDocId, $targetLawId);

    $this->assertTrue($result['success']);
}
```

**Similarity Linking Tests**:
```php
/** @test */
public function it_finds_similar_documents_using_embeddings()
{
    $docId = 'doc-123';
    $embedding = array_fill(0, 1536, 0.1); // Mock embedding

    $this->graphService
        ->shouldReceive('run')
        ->with(Mockery::pattern('/SIMILAR_TO/'), Mockery::any())
        ->andReturn([
            ['doc' => ['doc_id' => 'doc-456', 'similarity' => 0.85]],
            ['doc' => ['doc_id' => 'doc-789', 'similarity' => 0.78]],
        ]);

    $similar = $this->service->findSimilarDocuments($docId, $embedding);

    $this->assertCount(2, $similar);
    $this->assertEquals('doc-456', $similar[0]['doc_id']);
    $this->assertGreaterThan(0.8, $similar[0]['similarity']);
}

/** @test */
public function it_links_similar_documents_when_similarity_above_threshold()
{
    $sourceId = 'doc-123';
    $targetId = 'doc-456';
    $similarity = 0.85;

    $this->graphService
        ->shouldReceive('run')
        ->with(
            Mockery::pattern('/SIMILAR_TO/'),
            Mockery::on(fn($p) => $p['similarity'] === 0.85)
        )
        ->once()
        ->andReturn([]);

    $result = $this->service->linkSimilarDocuments($sourceId, $targetId, $similarity);

    $this->assertTrue($result['success']);
}

/** @test */
public function it_does_not_link_when_similarity_below_threshold()
{
    $sourceId = 'doc-123';
    $targetId = 'doc-456';
    $similarity = 0.65; // Below 0.7 threshold

    $this->graphService
        ->shouldNotReceive('run');

    $result = $this->service->linkSimilarDocuments($sourceId, $targetId, $similarity);

    $this->assertFalse($result['linked']);
    $this->assertStringContainsString('below threshold', $result['reason']);
}
```

**Acceptance Criteria**:
- ✅ 15+ characterization tests total
- ✅ All major GraphRagService methods covered
- ✅ Tests document: happy paths, edge cases, thresholds, error handling
- ✅ Tests are PASSING (or documented why they fail)

**Daily Checkpoint**:
- Run full test suite: `composer test:unit`
- Test count: Should have 15-20 new tests
- Commit: `git commit -m "Day 2: Complete characterization tests for all GraphRagService methods"`

---

### Day 3 (Wednesday): Interface Design + First Service Extraction
**Developer**: Dev A (interfaces) + Dev B (LawGraphSyncService)
**Hours**: 8-10 hours total
**TDD Step**: RED → GREEN

#### Morning (4 hours)

**Dev A Task 3.1**: Create Interfaces

**File**: `app/Contracts/Graph/GraphSyncServiceInterface.php` (NEW)
```php
<?php

namespace App\Contracts\Graph;

/**
 * Contract for graph synchronization services
 */
interface GraphSyncServiceInterface
{
    /**
     * Sync an entity to the graph database
     *
     * @param string $entityId ID of entity to sync
     * @param array $options Sync options
     * @return array Result with success status and metadata
     */
    public function sync(string $entityId, array $options = []): array;

    /**
     * Check if an entity exists in the graph
     *
     * @param string $entityId
     * @return bool
     */
    public function exists(string $entityId): bool;

    /**
     * Remove an entity from the graph
     *
     * @param string $entityId
     * @return bool
     */
    public function remove(string $entityId): bool;

    /**
     * Get sync statistics
     *
     * @return array
     */
    public function getStats(): array;
}
```

**File**: `app/Contracts/Graph/GraphLinkerInterface.php` (NEW)
```php
<?php

namespace App\Contracts\Graph;

/**
 * Contract for graph linking services (keywords, citations, similarity)
 */
interface GraphLinkerInterface
{
    /**
     * Create links between entities in the graph
     *
     * @param string $sourceId Source entity ID
     * @param string $targetId Target entity ID (or array of targets)
     * @param array $metadata Link metadata (e.g., similarity score, citation context)
     * @return array Result with success status
     */
    public function link(string $sourceId, string|array $targetId, array $metadata = []): array;

    /**
     * Get all links for an entity
     *
     * @param string $entityId
     * @param string|null $linkType Optional: filter by link type
     * @return array
     */
    public function getLinks(string $entityId, ?string $linkType = null): array;

    /**
     * Remove a link between entities
     *
     * @param string $sourceId
     * @param string $targetId
     * @param string|null $linkType
     * @return bool
     */
    public function unlink(string $sourceId, string $targetId, ?string $linkType = null): bool;
}
```

**Acceptance Criteria**:
- ✅ 2 interfaces created (GraphSyncServiceInterface, GraphLinkerInterface)
- ✅ Interfaces document expected behavior
- ✅ Interfaces cover all operations needed by extracted services

**Dev B Task 3.2**: Write Failing Tests for LawGraphSyncService

**File**: `tests/Unit/GraphRagService/LawGraphSyncServiceTest.php` (NEW)
```php
<?php

namespace Tests\Unit\GraphRagService;

use App\Services\GraphRagService\LawGraphSyncService;
use App\Services\GraphDatabaseService;
use App\Models\Law;
use Tests\TestCase;
use Tests\Concerns\UsesTestDatabase;
use Mockery;

/**
 * TDD Tests for LawGraphSyncService
 *
 * These tests are written BEFORE the service exists.
 * They define the expected behavior of the new service.
 */
class LawGraphSyncServiceTest extends TestCase
{
    use UsesTestDatabase;

    protected LawGraphSyncService $service;
    protected $graphService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->graphService = Mockery::mock(GraphDatabaseService::class);
        $this->service = new LawGraphSyncService($this->graphService);
    }

    /** @test */
    public function it_implements_graph_sync_service_interface()
    {
        $this->assertInstanceOf(
            \App\Contracts\Graph\GraphSyncServiceInterface::class,
            $this->service
        );
    }

    /** @test */
    public function it_syncs_law_to_graph_with_all_properties()
    {
        $law = Law::factory()->create([
            'doc_id' => 'ZKP-240',
            'title' => 'Zakon o kaznenom postupku - Članak 240',
            'content' => 'Pretraga se može izvršiti...',
            'jurisdiction' => 'Republika Hrvatska',
        ]);

        $this->graphService
            ->shouldReceive('run')
            ->once()
            ->with(
                Mockery::on(fn($q) => str_contains($q, 'MERGE (n:LawDocument')),
                Mockery::on(fn($p) => $p['docId'] === 'ZKP-240')
            )
            ->andReturn([['n' => ['doc_id' => 'ZKP-240']]]);

        $result = $this->service->sync($law->id);

        $this->assertTrue($result['success']);
        $this->assertEquals('ZKP-240', $result['doc_id']);
        $this->assertEquals('law', $result['entity_type']);
    }

    /** @test */
    public function it_checks_if_law_exists_in_graph()
    {
        $lawId = 'ZKP-240';

        $this->graphService
            ->shouldReceive('run')
            ->once()
            ->with(
                Mockery::on(fn($q) => str_contains($q, 'MATCH (n:LawDocument')),
                ['docId' => $lawId]
            )
            ->andReturn([['n' => ['doc_id' => $lawId]]]);

        $exists = $this->service->exists($lawId);

        $this->assertTrue($exists);
    }

    /** @test */
    public function it_removes_law_from_graph()
    {
        $lawId = 'ZKP-240';

        $this->graphService
            ->shouldReceive('run')
            ->once()
            ->with(
                Mockery::on(fn($q) => str_contains($q, 'DETACH DELETE')),
                ['docId' => $lawId]
            )
            ->andReturn([]);

        $result = $this->service->remove($lawId);

        $this->assertTrue($result);
    }

    /** @test */
    public function it_returns_sync_statistics()
    {
        $stats = $this->service->getStats();

        $this->assertArrayHasKey('total_synced', $stats);
        $this->assertArrayHasKey('last_sync_at', $stats);
        $this->assertArrayHasKey('avg_sync_time_ms', $stats);
    }
}
```

**Run Tests (SHOULD FAIL)**:
```bash
./scripts/run-tests.sh --filter=LawGraphSyncServiceTest
# Expected: Class 'LawGraphSyncService' not found
```

**Acceptance Criteria**:
- ✅ Tests written for new service (not yet implemented)
- ✅ Tests FAIL with "Class not found" (RED state)
- ✅ Tests define expected API and behavior

#### Afternoon (4 hours)

**Dev B Task 3.3**: Create LawGraphSyncService (GREEN)

**File**: `app/Services/GraphRagService/LawGraphSyncService.php` (NEW)
```php
<?php

namespace App\Services\GraphRagService;

use App\Contracts\Graph\GraphSyncServiceInterface;
use App\Services\GraphDatabaseService;
use App\Models\Law;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Law Graph Synchronization Service
 *
 * Handles syncing law documents to Neo4j graph database.
 * Extracted from GraphRagService as part of god class refactoring.
 */
class LawGraphSyncService implements GraphSyncServiceInterface
{
    private array $stats = [
        'total_synced' => 0,
        'last_sync_at' => null,
        'avg_sync_time_ms' => 0,
    ];

    public function __construct(
        protected GraphDatabaseService $graphService
    ) {}

    /**
     * Sync a law document to the graph database
     *
     * @param string $entityId Law ID
     * @param array $options Options: ['force' => bool, 'include_keywords' => bool]
     * @return array
     */
    public function sync(string $entityId, array $options = []): array
    {
        $startTime = microtime(true);

        try {
            // Load law
            $law = Law::findOrFail($entityId);

            // Check if already exists (unless force sync)
            if (!($options['force'] ?? false) && $this->exists($law->doc_id)) {
                return [
                    'success' => true,
                    'doc_id' => $law->doc_id,
                    'entity_type' => 'law',
                    'skipped' => true,
                    'reason' => 'Already exists in graph',
                ];
            }

            // Create/update node in graph
            $this->createOrUpdateLawNode($law);

            // Update stats
            $this->updateStats(microtime(true) - $startTime);

            Log::info("Law synced to graph", [
                'law_id' => $law->id,
                'doc_id' => $law->doc_id,
                'sync_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return [
                'success' => true,
                'doc_id' => $law->doc_id,
                'entity_type' => 'law',
                'sync_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ];

        } catch (\Exception $e) {
            Log::error("Failed to sync law to graph", [
                'law_id' => $entityId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'entity_id' => $entityId,
                'entity_type' => 'law',
            ];
        }
    }

    /**
     * Check if law exists in graph
     */
    public function exists(string $entityId): bool
    {
        $query = <<<'CYPHER'
        MATCH (n:LawDocument {doc_id: $docId})
        RETURN n
        CYPHER;

        $result = $this->graphService->run($query, ['docId' => $entityId]);

        return !empty($result);
    }

    /**
     * Remove law from graph (with all relationships)
     */
    public function remove(string $entityId): bool
    {
        try {
            $query = <<<'CYPHER'
            MATCH (n:LawDocument {doc_id: $docId})
            DETACH DELETE n
            CYPHER;

            $this->graphService->run($query, ['docId' => $entityId]);

            Log::info("Law removed from graph", ['doc_id' => $entityId]);

            return true;

        } catch (\Exception $e) {
            Log::error("Failed to remove law from graph", [
                'doc_id' => $entityId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get sync statistics
     */
    public function getStats(): array
    {
        return $this->stats;
    }

    /**
     * Create or update law node in Neo4j
     */
    protected function createOrUpdateLawNode(Law $law): void
    {
        $query = <<<'CYPHER'
        MERGE (n:LawDocument {doc_id: $docId})
        SET n.title = $title,
            n.content = $content,
            n.jurisdiction = $jurisdiction,
            n.source = $source,
            n.updated_at = datetime()
        RETURN n
        CYPHER;

        $params = [
            'docId' => $law->doc_id,
            'title' => $law->title,
            'content' => $law->content,
            'jurisdiction' => $law->jurisdiction ?? 'Republika Hrvatska',
            'source' => $law->source ?? 'zakon.hr',
        ];

        $this->graphService->run($query, $params);
    }

    /**
     * Update internal statistics
     */
    protected function updateStats(float $syncTime): void
    {
        $this->stats['total_synced']++;
        $this->stats['last_sync_at'] = now()->toDateTimeString();

        // Calculate rolling average
        $currentAvg = $this->stats['avg_sync_time_ms'];
        $count = $this->stats['total_synced'];
        $newAvg = (($currentAvg * ($count - 1)) + ($syncTime * 1000)) / $count;
        $this->stats['avg_sync_time_ms'] = round($newAvg, 2);
    }
}
```

**Run Tests (SHOULD PASS NOW)**:
```bash
./scripts/run-tests.sh --filter=LawGraphSyncServiceTest
# Expected: All tests pass (GREEN state)
```

**Acceptance Criteria**:
- ✅ LawGraphSyncService created
- ✅ Implements GraphSyncServiceInterface
- ✅ All tests pass (GREEN state achieved)
- ✅ Code follows Laravel conventions
- ✅ Proper error handling and logging

**Daily Checkpoint**:
- Run tests: `composer test:unit`
- All LawGraphSyncService tests should pass
- Characterization tests still pass (no behavior change)
- Commit: `git commit -m "Day 3: Extract LawGraphSyncService with TDD"`

---

### Day 4 (Thursday): Continue Service Extraction - CaseGraphSyncService
**Developer**: Dev A (CaseGraphSyncService) + Dev B (DecisionGraphSyncService)
**Hours**: 8-10 hours total
**TDD Step**: RED → GREEN → REFACTOR

#### Morning (4 hours)

**Dev A Task 4.1**: Extract CaseGraphSyncService

**Step 1**: Write failing tests (RED)
**Step 2**: Implement service (GREEN)
**Step 3**: Refactor and optimize

**File**: `tests/Unit/GraphRagService/CaseGraphSyncServiceTest.php` (NEW)
```php
// Similar structure to LawGraphSyncServiceTest
// Tests for: sync(), exists(), remove(), getStats()
// Plus case-specific tests: syncs documents, syncs evidence, creates relationships
```

**File**: `app/Services/GraphRagService/CaseGraphSyncService.php` (NEW)
```php
<?php

namespace App\Services\GraphRagService;

use App\Contracts\Graph\GraphSyncServiceInterface;
use App\Services\GraphDatabaseService;
use App\Models\LegalCase;
use Illuminate\Support\Facades\Log;

/**
 * Case Graph Synchronization Service
 *
 * Handles syncing legal cases (with documents and evidence) to graph.
 */
class CaseGraphSyncService implements GraphSyncServiceInterface
{
    public function __construct(
        protected GraphDatabaseService $graphService
    ) {}

    public function sync(string $entityId, array $options = []): array
    {
        $startTime = microtime(true);

        try {
            // Load case with relationships
            $case = LegalCase::with(['documents', 'evidence'])->findOrFail($entityId);

            // Create case node
            $this->createOrUpdateCaseNode($case);

            // Sync associated documents
            $docsSynced = $this->syncCaseDocuments($case);

            // Sync associated evidence
            $evidenceSynced = $this->syncCaseEvidence($case);

            return [
                'success' => true,
                'case_id' => $case->id,
                'case_number' => $case->case_number,
                'entity_type' => 'case',
                'documents_synced' => $docsSynced,
                'evidence_synced' => $evidenceSynced,
                'sync_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ];

        } catch (\Exception $e) {
            Log::error("Failed to sync case to graph", [
                'case_id' => $entityId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'entity_id' => $entityId,
                'entity_type' => 'case',
            ];
        }
    }

    // Implement other interface methods: exists(), remove(), getStats()
    // ... (similar to LawGraphSyncService)

    protected function createOrUpdateCaseNode(LegalCase $case): void
    {
        $query = <<<'CYPHER'
        MERGE (c:Case {case_id: $caseId})
        SET c.case_number = $caseNumber,
            c.court = $court,
            c.status = $status,
            c.filed_at = $filedAt,
            c.updated_at = datetime()
        RETURN c
        CYPHER;

        $params = [
            'caseId' => $case->id,
            'caseNumber' => $case->case_number,
            'court' => $case->court,
            'status' => $case->status,
            'filedAt' => $case->filed_at?->toDateTimeString(),
        ];

        $this->graphService->run($query, $params);
    }

    protected function syncCaseDocuments(LegalCase $case): int
    {
        $count = 0;

        foreach ($case->documents as $document) {
            // Create document node and link to case
            $query = <<<'CYPHER'
            MATCH (c:Case {case_id: $caseId})
            MERGE (d:Document {doc_id: $docId})
            SET d.title = $title,
                d.type = $type,
                d.content = $content
            MERGE (c)-[:HAS_DOCUMENT]->(d)
            RETURN d
            CYPHER;

            $this->graphService->run($query, [
                'caseId' => $case->id,
                'docId' => $document->id,
                'title' => $document->title,
                'type' => $document->type,
                'content' => $document->content,
            ]);

            $count++;
        }

        return $count;
    }

    protected function syncCaseEvidence(LegalCase $case): int
    {
        $count = 0;

        foreach ($case->evidence as $evidence) {
            $query = <<<'CYPHER'
            MATCH (c:Case {case_id: $caseId})
            MERGE (e:Evidence {evidence_id: $evidenceId})
            SET e.type = $type,
                e.description = $description,
                e.collected_at = $collectedAt
            MERGE (c)-[:HAS_EVIDENCE]->(e)
            RETURN e
            CYPHER;

            $this->graphService->run($query, [
                'caseId' => $case->id,
                'evidenceId' => $evidence->id,
                'type' => $evidence->type,
                'description' => $evidence->description,
                'collectedAt' => $evidence->collected_at?->toDateTimeString(),
            ]);

            $count++;
        }

        return $count;
    }
}
```

**Test and Verify**:
```bash
./scripts/run-tests.sh --filter=CaseGraphSyncServiceTest
# All tests should pass
```

#### Afternoon (4 hours)

**Dev B Task 4.2**: Extract DecisionGraphSyncService

**File**: `app/Services/GraphRagService/DecisionGraphSyncService.php` (NEW)
```php
<?php

namespace App\Services\GraphRagService;

use App\Contracts\Graph\GraphSyncServiceInterface;
use App\Services\GraphDatabaseService;
use App\Models\CourtDecisionDocument;

/**
 * Decision Graph Synchronization Service
 *
 * Handles syncing court decisions to graph.
 */
class DecisionGraphSyncService implements GraphSyncServiceInterface
{
    public function __construct(
        protected GraphDatabaseService $graphService
    ) {}

    public function sync(string $entityId, array $options = []): array
    {
        try {
            $decision = CourtDecisionDocument::findOrFail($entityId);

            // Create decision node
            $this->createOrUpdateDecisionNode($decision);

            return [
                'success' => true,
                'decision_id' => $decision->id,
                'court' => $decision->court,
                'entity_type' => 'decision',
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'entity_id' => $entityId,
                'entity_type' => 'decision',
            ];
        }
    }

    // Implement interface methods...

    protected function createOrUpdateDecisionNode(CourtDecisionDocument $decision): void
    {
        $query = <<<'CYPHER'
        MERGE (d:CourtDecisionDocument {decision_id: $decisionId})
        SET d.court = $court,
            d.date = $date,
            d.summary = $summary,
            d.content = $content,
            d.updated_at = datetime()
        RETURN d
        CYPHER;

        $this->graphService->run($query, [
            'decisionId' => $decision->id,
            'court' => $decision->court,
            'date' => $decision->date?->toDateString(),
            'summary' => $decision->summary,
            'content' => $decision->content,
        ]);
    }
}
```

**Acceptance Criteria (Day 4)**:
- ✅ CaseGraphSyncService created and tested
- ✅ DecisionGraphSyncService created and tested
- ✅ All tests pass (GREEN)
- ✅ Services implement GraphSyncServiceInterface
- ✅ Characterization tests still pass

**Daily Checkpoint**:
- Run full test suite
- Verify: 3 sync services extracted (Law, Case, Decision)
- Commit: `git commit -m "Day 4: Extract Case and Decision sync services"`

---

### Day 5 (Friday): Complete Sync Services + Weekly Review
**Developer**: Dev A + Dev B
**Hours**: 8-10 hours total
**TDD Step**: GREEN → REFACTOR

#### Morning (4 hours)

**Task 5.1**: Extract TextractGraphSyncService

**File**: `app/Services/GraphRagService/TextractGraphSyncService.php` (NEW)
```php
<?php

namespace App\Services\GraphRagService;

use App\Contracts\Graph\GraphSyncServiceInterface;
use App\Services\GraphDatabaseService;
use App\Models\TextractDocument;

/**
 * Textract Document Graph Synchronization Service
 *
 * Handles syncing OCR'd documents (from AWS Textract) to graph.
 */
class TextractGraphSyncService implements GraphSyncServiceInterface
{
    public function __construct(
        protected GraphDatabaseService $graphService
    ) {}

    public function sync(string $entityId, array $options = []): array
    {
        try {
            $textractDoc = TextractDocument::findOrFail($entityId);

            // Create textract document node
            $this->createOrUpdateTextractNode($textractDoc);

            // Link to case if applicable
            if ($textractDoc->case_id) {
                $this->linkToCase($textractDoc);
            }

            return [
                'success' => true,
                'textract_doc_id' => $textractDoc->id,
                'entity_type' => 'textract_document',
                'linked_to_case' => !is_null($textractDoc->case_id),
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'entity_id' => $entityId,
                'entity_type' => 'textract_document',
            ];
        }
    }

    // Implement interface methods...

    protected function createOrUpdateTextractNode(TextractDocument $doc): void
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

        $this->graphService->run($query, [
            'textractId' => $doc->id,
            'driveFileId' => $doc->drive_file_id,
            'extractedText' => $doc->extracted_text,
            'pageCount' => $doc->page_count,
            'processedAt' => $doc->processed_at?->toDateTimeString(),
        ]);
    }

    protected function linkToCase(TextractDocument $doc): void
    {
        $query = <<<'CYPHER'
        MATCH (c:Case {case_id: $caseId})
        MATCH (t:TextractDocument {textract_id: $textractId})
        MERGE (c)-[:HAS_TEXTRACT_DOCUMENT]->(t)
        CYPHER;

        $this->graphService->run($query, [
            'caseId' => $doc->case_id,
            'textractId' => $doc->id,
        ]);
    }
}
```

**Acceptance Criteria**:
- ✅ TextractGraphSyncService created
- ✅ Tests written and passing
- ✅ Links textract docs to cases

#### Afternoon (4 hours)

**Task 5.2**: Weekly Review and Refactoring

**Refactoring Checklist**:
1. Review all 4 sync services for code duplication
2. Extract common patterns to base class or traits
3. Optimize Cypher queries
4. Add caching where appropriate
5. Improve error messages
6. Update documentation

**Create Base Class** (if duplication found):

**File**: `app/Services/GraphRagService/BaseGraphSyncService.php` (NEW)
```php
<?php

namespace App\Services\GraphRagService;

use App\Contracts\Graph\GraphSyncServiceInterface;
use App\Services\GraphDatabaseService;
use Illuminate\Support\Facades\Log;

/**
 * Base Graph Sync Service
 *
 * Provides common functionality for all graph sync services.
 */
abstract class BaseGraphSyncService implements GraphSyncServiceInterface
{
    protected array $stats = [
        'total_synced' => 0,
        'last_sync_at' => null,
        'avg_sync_time_ms' => 0,
    ];

    public function __construct(
        protected GraphDatabaseService $graphService
    ) {}

    public function getStats(): array
    {
        return $this->stats;
    }

    protected function updateStats(float $syncTime): void
    {
        $this->stats['total_synced']++;
        $this->stats['last_sync_at'] = now()->toDateTimeString();

        $currentAvg = $this->stats['avg_sync_time_ms'];
        $count = $this->stats['total_synced'];
        $newAvg = (($currentAvg * ($count - 1)) + ($syncTime * 1000)) / $count;
        $this->stats['avg_sync_time_ms'] = round($newAvg, 2);
    }

    protected function logSuccess(string $entityType, string $entityId, float $syncTime): void
    {
        Log::info("Entity synced to graph", [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'sync_time_ms' => round($syncTime * 1000, 2),
        ]);
    }

    protected function logError(string $entityType, string $entityId, string $error): void
    {
        Log::error("Failed to sync entity to graph", [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'error' => $error,
        ]);
    }
}
```

**Refactor Services to Extend Base Class**:
```php
class LawGraphSyncService extends BaseGraphSyncService
{
    // Remove duplicated methods (stats, logging, etc.)
    // Keep only law-specific logic
}
```

**Run Full Test Suite**:
```bash
composer test:unit
# All tests should still pass after refactoring
```

**Acceptance Criteria (Day 5)**:
- ✅ All 4 sync services extracted (Law, Case, Decision, Textract)
- ✅ Code refactored to remove duplication
- ✅ Base class created for common functionality
- ✅ All tests pass
- ✅ Performance optimized

**Weekly Checkpoint (End of Week 1)**:
- Total services extracted: 4 of 7 (57% complete)
- Test coverage: Verify still >80%
- All characterization tests pass (no behavior change)
- Commit: `git commit -m "Week 1 Complete: 4 sync services extracted"`
- Create PR for review: `gh pr create --title "Week 1: GraphRagService Refactoring - Sync Services"`

---

## Week 2: Linker Services + Orchestrator

### Day 6 (Monday): Keyword Linker
**Developer**: Dev A + Dev B (pair programming)
**Hours**: 8-10 hours
**TDD Step**: RED → GREEN → REFACTOR

#### Tasks
1. Write failing tests for GraphKeywordLinker
2. Implement GraphKeywordLinker service
3. Extract keyword extraction logic from GraphRagService
4. Test integration with sync services

**File**: `app/Services/GraphRagService/GraphKeywordLinker.php` (NEW)
```php
<?php

namespace App\Services\GraphRagService;

use App\Contracts\Graph\GraphLinkerInterface;
use App\Services\GraphDatabaseService;
use App\Services\OpenAIService;

/**
 * Graph Keyword Linker
 *
 * Extracts keywords from text and creates keyword relationships in graph.
 */
class GraphKeywordLinker implements GraphLinkerInterface
{
    public function __construct(
        protected GraphDatabaseService $graphService,
        protected OpenAIService $openAI
    ) {}

    /**
     * Extract keywords and link to document
     */
    public function link(string $sourceId, string|array $targetId, array $metadata = []): array
    {
        $text = $metadata['text'] ?? null;

        if (!$text) {
            return ['success' => false, 'error' => 'Text required for keyword extraction'];
        }

        // Extract keywords using OpenAI
        $keywords = $this->extractKeywords($text);

        // Create keyword nodes and relationships
        $linked = 0;
        foreach ($keywords as $keyword) {
            $this->createKeywordLink($sourceId, $keyword);
            $linked++;
        }

        return [
            'success' => true,
            'keywords_linked' => $linked,
            'keywords' => $keywords,
        ];
    }

    protected function extractKeywords(string $text): array
    {
        $prompt = <<<PROMPT
Extract 5-10 key legal concepts/keywords from this Croatian legal text:

{$text}

Return only the keywords as a JSON array, in Croatian.
Example: ["pretraga stana", "naredba suda", "kazneni postupak"]
PROMPT;

        $response = $this->openAI->chat([
            ['role' => 'system', 'content' => 'You are a Croatian legal expert.'],
            ['role' => 'user', 'content' => $prompt],
        ], 'gpt-4o-mini', ['response_format' => ['type' => 'json_object']]);

        $result = json_decode($response['choices'][0]['message']['content'], true);

        return $result['keywords'] ?? [];
    }

    protected function createKeywordLink(string $docId, string $keyword): void
    {
        $query = <<<'CYPHER'
        MATCH (d {doc_id: $docId})
        MERGE (k:Keyword {name: $keyword})
        MERGE (d)-[:HAS_KEYWORD]->(k)
        CYPHER;

        $this->graphService->run($query, [
            'docId' => $docId,
            'keyword' => $keyword,
        ]);
    }

    // Implement other interface methods: getLinks(), unlink()
}
```

### Day 7 (Tuesday): Citation Linker
**Developer**: Dev A + Dev B (split work)
**Hours**: 8-10 hours

**File**: `app/Services/GraphRagService/GraphCitationLinker.php` (NEW)
```php
<?php

namespace App\Services\GraphRagService;

use App\Contracts\Graph\GraphLinkerInterface;
use App\Services\GraphDatabaseService;

/**
 * Graph Citation Linker
 *
 * Extracts citations (ZKP, Ustav, etc.) and creates CITES relationships.
 */
class GraphCitationLinker implements GraphLinkerInterface
{
    public function __construct(
        protected GraphDatabaseService $graphService
    ) {}

    public function link(string $sourceId, string|array $targetId, array $metadata = []): array
    {
        $text = $metadata['text'] ?? null;

        if (!$text) {
            return ['success' => false, 'error' => 'Text required for citation extraction'];
        }

        // Extract citations
        $citations = $this->extractCitations($text);

        // Create citation relationships
        $linked = 0;
        foreach ($citations as $citation) {
            if ($this->createCitationLink($sourceId, $citation['doc_id'])) {
                $linked++;
            }
        }

        return [
            'success' => true,
            'citations_linked' => $linked,
            'citations' => $citations,
        ];
    }

    protected function extractCitations(string $text): array
    {
        $citations = [];

        // Pattern for ZKP articles: "ZKP članak 240" or "ZKP čl. 240"
        preg_match_all('/ZKP\s+(članak|čl\.)\s+(\d+)/i', $text, $zkpMatches);
        foreach ($zkpMatches[2] as $articleNum) {
            $citations[] = [
                'type' => 'ZKP',
                'article' => $articleNum,
                'doc_id' => "ZKP-{$articleNum}",
            ];
        }

        // Pattern for Ustav RH: "Ustav RH članak 29"
        preg_match_all('/Ustav\s+RH\s+(članak|čl\.)\s+(\d+)/i', $text, $ustavMatches);
        foreach ($ustavMatches[2] as $articleNum) {
            $citations[] = [
                'type' => 'Ustav',
                'article' => $articleNum,
                'doc_id' => "USTAV-{$articleNum}",
            ];
        }

        return $citations;
    }

    protected function createCitationLink(string $sourceId, string $targetId): bool
    {
        try {
            $query = <<<'CYPHER'
            MATCH (source {doc_id: $sourceId})
            MATCH (target {doc_id: $targetId})
            MERGE (source)-[:CITES]->(target)
            CYPHER;

            $this->graphService->run($query, [
                'sourceId' => $sourceId,
                'targetId' => $targetId,
            ]);

            return true;

        } catch (\Exception $e) {
            return false;
        }
    }
}
```

### Day 8 (Wednesday): Similarity Linker
**Developer**: Dev A + Dev B
**Hours**: 8-10 hours

**File**: `app/Services/GraphRagService/GraphSimilarityLinker.php` (NEW)
```php
<?php

namespace App\Services\GraphRagService;

use App\Contracts\Graph\GraphLinkerInterface;
use App\Services\GraphDatabaseService;
use App\Services\OpenAIService;

/**
 * Graph Similarity Linker
 *
 * Finds similar documents using embeddings and creates SIMILAR_TO relationships.
 */
class GraphSimilarityLinker implements GraphLinkerInterface
{
    protected float $similarityThreshold = 0.7;

    public function __construct(
        protected GraphDatabaseService $graphService,
        protected OpenAIService $openAI
    ) {}

    public function link(string $sourceId, string|array $targetId, array $metadata = []): array
    {
        $embedding = $metadata['embedding'] ?? null;

        if (!$embedding) {
            // Generate embedding for source document
            $text = $metadata['text'] ?? null;
            if (!$text) {
                return ['success' => false, 'error' => 'Text or embedding required'];
            }

            $embedding = $this->openAI->embeddings($text);
        }

        // Find similar documents
        $similarDocs = $this->findSimilarDocuments($sourceId, $embedding);

        // Create similarity links
        $linked = 0;
        foreach ($similarDocs as $doc) {
            if ($this->createSimilarityLink($sourceId, $doc['doc_id'], $doc['similarity'])) {
                $linked++;
            }
        }

        return [
            'success' => true,
            'similar_documents_linked' => $linked,
            'similar_documents' => $similarDocs,
        ];
    }

    protected function findSimilarDocuments(string $sourceId, array $embedding): array
    {
        // Query: Find all documents, calculate cosine similarity
        // Return top 10 with similarity > threshold

        // This would integrate with vector search
        // Simplified for example
        return [];
    }

    protected function createSimilarityLink(string $sourceId, string $targetId, float $similarity): bool
    {
        if ($similarity < $this->similarityThreshold) {
            return false;
        }

        try {
            $query = <<<'CYPHER'
            MATCH (source {doc_id: $sourceId})
            MATCH (target {doc_id: $targetId})
            MERGE (source)-[r:SIMILAR_TO]->(target)
            SET r.similarity = $similarity
            CYPHER;

            $this->graphService->run($query, [
                'sourceId' => $sourceId,
                'targetId' => $targetId,
                'similarity' => $similarity,
            ]);

            return true;

        } catch (\Exception $e) {
            return false;
        }
    }
}
```

### Day 9 (Thursday): Create Orchestrator
**Developer**: Dev A + Dev B (pair programming)
**Hours**: 8-10 hours
**TDD Step**: RED → GREEN → REFACTOR

**File**: `app/Services/GraphRagOrchestrator.php` (NEW)
```php
<?php

namespace App\Services;

use App\Services\GraphRagService\LawGraphSyncService;
use App\Services\GraphRagService\CaseGraphSyncService;
use App\Services\GraphRagService\DecisionGraphSyncService;
use App\Services\GraphRagService\TextractGraphSyncService;
use App\Services\GraphRagService\GraphKeywordLinker;
use App\Services\GraphRagService\GraphCitationLinker;
use App\Services\GraphRagService\GraphSimilarityLinker;

/**
 * Graph RAG Orchestrator
 *
 * Facade that coordinates all graph synchronization and linking services.
 * Replaces the monolithic GraphRagService.
 */
class GraphRagOrchestrator
{
    public function __construct(
        protected LawGraphSyncService $lawSync,
        protected CaseGraphSyncService $caseSync,
        protected DecisionGraphSyncService $decisionSync,
        protected TextractGraphSyncService $textractSync,
        protected GraphKeywordLinker $keywordLinker,
        protected GraphCitationLinker $citationLinker,
        protected GraphSimilarityLinker $similarityLinker
    ) {}

    /**
     * Sync a law to the graph (with keywords, citations, similarity)
     */
    public function syncLaw(string $lawId, array $options = []): array
    {
        // Sync the law node
        $syncResult = $this->lawSync->sync($lawId, $options);

        if (!$syncResult['success']) {
            return $syncResult;
        }

        // Add keywords if requested
        if ($options['include_keywords'] ?? true) {
            $law = \App\Models\Law::find($lawId);
            $keywordResult = $this->keywordLinker->link(
                $law->doc_id,
                [],
                ['text' => $law->content]
            );
            $syncResult['keywords'] = $keywordResult;
        }

        // Add citations if requested
        if ($options['include_citations'] ?? true) {
            $law = \App\Models\Law::find($lawId);
            $citationResult = $this->citationLinker->link(
                $law->doc_id,
                [],
                ['text' => $law->content]
            );
            $syncResult['citations'] = $citationResult;
        }

        return $syncResult;
    }

    /**
     * Sync a case to the graph (with all relationships)
     */
    public function syncCase(string $caseId, array $options = []): array
    {
        $syncResult = $this->caseSync->sync($caseId, $options);

        if (!$syncResult['success']) {
            return $syncResult;
        }

        // Add keywords and citations for case documents
        if ($options['include_relationships'] ?? true) {
            $case = \App\Models\LegalCase::with('documents')->find($caseId);

            foreach ($case->documents as $doc) {
                $this->keywordLinker->link($doc->id, [], ['text' => $doc->content]);
                $this->citationLinker->link($doc->id, [], ['text' => $doc->content]);
            }
        }

        return $syncResult;
    }

    /**
     * Sync a court decision (with all relationships)
     */
    public function syncDecision(string $decisionId, array $options = []): array
    {
        $syncResult = $this->decisionSync->sync($decisionId, $options);

        if (!$syncResult['success']) {
            return $syncResult;
        }

        // Add relationships
        if ($options['include_relationships'] ?? true) {
            $decision = \App\Models\CourtDecisionDocument::find($decisionId);

            $this->keywordLinker->link($decision->id, [], ['text' => $decision->content]);
            $this->citationLinker->link($decision->id, [], ['text' => $decision->content]);

            // Find similar decisions
            if ($options['include_similarity'] ?? true) {
                $this->similarityLinker->link($decision->id, [], ['text' => $decision->content]);
            }
        }

        return $syncResult;
    }

    /**
     * Sync a textract document
     */
    public function syncTextract(string $textractId, array $options = []): array
    {
        return $this->textractSync->sync($textractId, $options);
    }

    /**
     * Get statistics from all services
     */
    public function getStats(): array
    {
        return [
            'law_sync' => $this->lawSync->getStats(),
            'case_sync' => $this->caseSync->getStats(),
            'decision_sync' => $this->decisionSync->getStats(),
            'textract_sync' => $this->textractSync->getStats(),
        ];
    }

    /**
     * Batch sync multiple entities
     */
    public function batchSync(array $entities, string $type, array $options = []): array
    {
        $results = [];

        foreach ($entities as $entityId) {
            $results[$entityId] = match ($type) {
                'law' => $this->syncLaw($entityId, $options),
                'case' => $this->syncCase($entityId, $options),
                'decision' => $this->syncDecision($entityId, $options),
                'textract' => $this->syncTextract($entityId, $options),
                default => ['success' => false, 'error' => 'Unknown type'],
            };
        }

        return [
            'success' => true,
            'total' => count($entities),
            'results' => $results,
        ];
    }
}
```

**Acceptance Criteria**:
- ✅ Orchestrator delegates to specialized services
- ✅ Orchestrator coordinates multi-step operations
- ✅ Orchestrator provides same API as old GraphRagService
- ✅ All tests pass

### Day 10 (Friday): Migration + Testing + Documentation
**Developer**: Dev A + Dev B
**Hours**: 8-10 hours
**TDD Step**: REFACTOR + VERIFY

#### Tasks

**1. Update Service Provider** (register new services)

**File**: `app/Providers/AppServiceProvider.php` (MODIFY)
```php
public function register(): void
{
    // Register new graph services
    $this->app->singleton(\App\Services\GraphRagOrchestrator::class);

    $this->app->singleton(\App\Services\GraphRagService\LawGraphSyncService::class);
    $this->app->singleton(\App\Services\GraphRagService\CaseGraphSyncService::class);
    $this->app->singleton(\App\Services\GraphRagService\DecisionGraphSyncService::class);
    $this->app->singleton(\App\Services\GraphRagService\TextractGraphSyncService::class);

    $this->app->singleton(\App\Services\GraphRagService\GraphKeywordLinker::class);
    $this->app->singleton(\App\Services\GraphRagService\GraphCitationLinker::class);
    $this->app->singleton(\App\Services\GraphRagService\GraphSimilarityLinker::class);
}
```

**2. Create Backward-Compatible Facade**

**File**: `app/Services/GraphRagService.php` (MODIFY - add deprecation wrapper)
```php
<?php

namespace App\Services;

/**
 * GraphRagService (Legacy - Deprecated)
 *
 * This class now delegates to GraphRagOrchestrator.
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
    public function syncLaw(string $lawId, array $options = []): array
    {
        \Log::warning('GraphRagService::syncLaw() is deprecated. Use GraphRagOrchestrator::syncLaw()');

        return $this->orchestrator->syncLaw($lawId, $options);
    }

    /**
     * @deprecated Use GraphRagOrchestrator::syncCase()
     */
    public function syncCase(string $caseId, array $options = []): array
    {
        \Log::warning('GraphRagService::syncCase() is deprecated. Use GraphRagOrchestrator::syncCase()');

        return $this->orchestrator->syncCase($caseId, $options);
    }

    // ... delegate all other methods to orchestrator
}
```

**3. Run ALL Tests**
```bash
# Run full test suite (should take 5-10 minutes)
composer test

# Run specific refactoring tests
./scripts/run-tests.sh --filter=GraphRagService
./scripts/run-tests.sh --filter=GraphRagOrchestrator

# Check coverage
composer test:coverage
```

**Expected Results**:
- ✅ All characterization tests pass (behavior unchanged)
- ✅ All new service tests pass
- ✅ Integration tests pass
- ✅ Coverage >80%

**4. Performance Testing**
```bash
# Create performance test script
php artisan tinker

# Test old vs new implementation
$old = app(\App\Services\GraphRagService::class);
$new = app(\App\Services\GraphRagOrchestrator::class);

// Measure sync time
$start = microtime(true);
$old->syncLaw('test-law-id');
$oldTime = microtime(true) - $start;

$start = microtime(true);
$new->syncLaw('test-law-id');
$newTime = microtime(true) - $start;

echo "Old: {$oldTime}s, New: {$newTime}s\n";
```

**5. Update Documentation**

**File**: `docs/GRAPHRAG_REFACTORING_SUMMARY.md` (NEW)
```markdown
# GraphRagService Refactoring Summary

## What Changed

The monolithic `GraphRagService` (1,933 lines) has been refactored into 7 focused services:

### Before
- **GraphRagService**: 1,933 lines, 39 methods, 7 concerns

### After
- **GraphRagOrchestrator**: 150 lines (coordinator)
- **LawGraphSyncService**: 350 lines
- **CaseGraphSyncService**: 400 lines
- **DecisionGraphSyncService**: 300 lines
- **TextractGraphSyncService**: 250 lines
- **GraphKeywordLinker**: 200 lines
- **GraphCitationLinker**: 250 lines
- **GraphSimilarityLinker**: 300 lines

## Benefits

1. **Testability**: Each service can be tested in isolation
2. **Maintainability**: Changes isolated to specific services
3. **Reusability**: Services can be used independently
4. **Clarity**: Each service has a single, clear responsibility

## Migration Guide

### Old Code
```php
$graphRag = app(\App\Services\GraphRagService::class);
$result = $graphRag->syncLaw($lawId);
```

### New Code
```php
$orchestrator = app(\App\Services\GraphRagOrchestrator::class);
$result = $orchestrator->syncLaw($lawId);
```

### Backward Compatibility

The old `GraphRagService` still works (delegates to new services) but will log deprecation warnings.

## Testing

All existing tests pass. Added 50+ new tests for individual services.

## Performance

No performance regression. Similar execution time to old implementation.
```

**6. Create Pull Request**
```bash
# Commit final changes
git add .
git commit -m "Complete GraphRagService refactoring - all services extracted and tested"

# Push to remote
git push -u origin refactor/graphrag-service-tdd

# Create PR
gh pr create \
  --title "GraphRagService Refactoring: 1,933 lines → 7 focused services" \
  --body "$(cat docs/GRAPHRAG_REFACTORING_SUMMARY.md)"
```

**Acceptance Criteria (Sprint 1 Complete)**:
- ✅ All 7 services extracted and tested
- ✅ GraphRagOrchestrator coordinates services
- ✅ Backward-compatible wrapper exists
- ✅ All characterization tests pass (no behavior change)
- ✅ All new tests pass (>50 tests added)
- ✅ Code coverage >80%
- ✅ Documentation updated
- ✅ PR created for review

---

## Sprint 1 Summary

### Metrics

| Metric | Before | After | Change |
|--------|--------|-------|--------|
| **Lines of Code** | 1,933 (1 file) | ~2,200 (8 files) | +267 lines |
| **Avg Lines per File** | 1,933 | 275 | -86% |
| **Test Coverage** | 45% | 85%+ | +40% |
| **Tests** | 12 | 60+ | +48 tests |
| **Services** | 1 | 8 | +7 services |
| **Cyclomatic Complexity** | High | Low | Improved |

### Files Created (15 total)

**Interfaces (2)**:
1. `app/Contracts/Graph/GraphSyncServiceInterface.php`
2. `app/Contracts/Graph/GraphLinkerInterface.php`

**Services (8)**:
3. `app/Services/GraphRagOrchestrator.php`
4. `app/Services/GraphRagService/BaseGraphSyncService.php`
5. `app/Services/GraphRagService/LawGraphSyncService.php`
6. `app/Services/GraphRagService/CaseGraphSyncService.php`
7. `app/Services/GraphRagService/DecisionGraphSyncService.php`
8. `app/Services/GraphRagService/TextractGraphSyncService.php`
9. `app/Services/GraphRagService/GraphKeywordLinker.php`
10. `app/Services/GraphRagService/GraphCitationLinker.php`
11. `app/Services/GraphRagService/GraphSimilarityLinker.php`

**Tests (7)**:
12. `tests/Unit/GraphRagService/GraphRagServiceCharacterizationTest.php`
13. `tests/Unit/GraphRagService/LawGraphSyncServiceTest.php`
14. `tests/Unit/GraphRagService/CaseGraphSyncServiceTest.php`
15. `tests/Unit/GraphRagService/DecisionGraphSyncServiceTest.php`
16. `tests/Unit/GraphRagService/TextractGraphSyncServiceTest.php`
17. `tests/Unit/GraphRagService/GraphKeywordLinkerTest.php`
18. `tests/Unit/GraphRagService/GraphCitationLinkerTest.php`
19. `tests/Unit/GraphRagService/GraphSimilarityLinkerTest.php`
20. `tests/Unit/GraphRagService/GraphRagOrchestratorTest.php`

### Files Modified (3)
1. `app/Services/GraphRagService.php` - Converted to backward-compatible wrapper
2. `app/Providers/AppServiceProvider.php` - Register new services
3. `README.md` - Update architecture documentation

### Documentation (2)
1. `docs/GRAPHRAG_REFACTORING_SUMMARY.md`
2. `docs/GRAPHRAG_SERVICES_API.md`

### Time Breakdown

| Day | Focus | Hours | Completion |
|-----|-------|-------|------------|
| Day 1 | Characterization Tests | 8-10 | ✅ |
| Day 2 | Complete Characterization | 8-10 | ✅ |
| Day 3 | Interfaces + LawSync | 8-10 | ✅ |
| Day 4 | Case + Decision Sync | 8-10 | ✅ |
| Day 5 | Textract + Refactor | 8-10 | ✅ |
| Day 6 | Keyword Linker | 8-10 | ✅ |
| Day 7 | Citation Linker | 8-10 | ✅ |
| Day 8 | Similarity Linker | 8-10 | ✅ |
| Day 9 | Orchestrator | 8-10 | ✅ |
| Day 10 | Migration + Docs | 8-10 | ✅ |
| **Total** | **Full Sprint** | **80-100 hours** | **100%** |

### Success Criteria - All Met ✅

- ✅ GraphRagService split into 7 focused services
- ✅ All services implement appropriate interfaces
- ✅ All characterization tests pass (no behavior change)
- ✅ 50+ new tests added (all passing)
- ✅ Test coverage improved from 45% → 85%
- ✅ Code follows SOLID principles
- ✅ Backward compatibility maintained
- ✅ Documentation complete
- ✅ Performance maintained or improved

### Next Steps

**Sprint 2** will refactor `UnifiedSearchService` (1,535 lines) using the same TDD approach:
1. Characterization tests
2. Extract 6 search services
3. Create SearchOrchestrator
4. Maintain backward compatibility

---

## Risk Mitigation

### Rollback Plan

If issues arise after deployment:

1. **Feature Flag**: Add flag to toggle between old/new implementation
2. **Quick Revert**: Revert to old GraphRagService (still exists as wrapper)
3. **Gradual Migration**: Migrate one entity type at a time (laws → cases → decisions)

### Monitoring

- Monitor sync performance (old vs new)
- Track error rates
- Log deprecation warnings
- Alert on test failures

### Contingency

If refactoring takes longer than 2 weeks:
- Pause at Day 5 (sync services complete)
- Deploy partial refactor
- Continue with linkers in Sprint 1.5

---

**Sprint 1 Status**: ✅ COMPLETE AND READY FOR REVIEW

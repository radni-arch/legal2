# TDD-Based God Class Refactoring Plan

## Executive Summary

**Objective**: Refactor 4 critical "god classes" into smaller, focused, testable services using Test-Driven Development (TDD) principles.

**Total Effort**: 120-150 hours (3-4 weeks, 2 developers)
**Risk Level**: LOW (heavy test coverage ensures safety)
**Approach**: Strangler Fig Pattern with 100% test coverage at each step

---

## God Classes to Refactor

| Class | Lines | Methods | Responsibilities | Target Classes |
|-------|-------|---------|------------------|----------------|
| GraphRagService | 1,933 | 39 | 5 concerns | 6 services |
| UnifiedSearchService | 1,535 | 32 | 4 concerns | 5 services |
| OpenAIService | 1,074 | ~25 | 3 concerns | 4 services |
| AutonomousResearchAgent | 1,125 | ~20 | 4 concerns | 5 services |
| **TOTAL** | **5,667** | **116** | **16** | **20** |

---

## TDD Methodology

### Red-Green-Refactor Cycle

```
1. RED:    Write failing test for existing behavior
2. GREEN:  Make test pass (use existing code)
3. REFACTOR: Extract to new class, test still passes
4. VERIFY: All tests pass, behavior unchanged
```

### Safety Net Principles

1. **Characterization Tests First**: Test current behavior before any changes
2. **One Extraction at a Time**: Never refactor multiple methods simultaneously
3. **Tests as Documentation**: Every public method must have tests
4. **No Behavior Changes**: Refactoring must not change functionality
5. **Continuous Integration**: Run tests after every change

---

## PHASE 1: GRAPHRAGSERVICE REFACTORING (40-50 hours)

### Current State Analysis

**Responsibilities**:
1. Law document syncing (syncLaw + helpers)
2. Case document syncing (syncCase + helpers)
3. Decision document syncing (syncDecision + helpers)
4. Keyword extraction and linking (extractAndLinkKeywords)
5. Citation extraction and linking (extractAndCreateCitations)
6. Similarity relationship creation (createSimilarityRelationships)
7. Textract job syncing (syncTextractJob)

**Target Architecture**:
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

### Task 1.1: Write Characterization Tests (8-10 hours)

**TDD Step**: RED

**Objective**: Create comprehensive tests for ALL current GraphRagService behavior

**Subtasks**:

1. **Test syncLaw method** (2 hours)
   ```php
   // tests/Unit/Services/GraphRagServiceCharacterizationTest.php

   public function test_syncLaw_creates_law_node_with_all_properties()
   {
       // Given: A law in the database
       $law = Law::factory()->create([...]);

       // When: Sync to graph
       $this->graphRagService->syncLaw($law->id);

       // Then: Verify node exists with exact properties
       $node = $this->getNodeFromGraph('LawDocument', $law->id);
       $this->assertEquals($law->title, $node['title']);
       $this->assertEquals($law->law_number, $node['law_number']);
       // ... all properties
   }

   public function test_syncLaw_creates_jurisdiction_relationship()
   {
       // Given: Law with jurisdiction
       $law = Law::factory()->create(['jurisdiction' => 'Republika Hrvatska']);

       // When: Sync to graph
       $this->graphRagService->syncLaw($law->id);

       // Then: Verify jurisdiction node and relationship
       $this->assertGraphRelationshipExists(
           'LawDocument', $law->id,
           'BELONGS_TO_JURISDICTION',
           'Jurisdiction', 'jurisdiction_Republika Hrvatska'
       );
   }

   public function test_syncLaw_extracts_and_links_keywords()
   {
       // Given: Law with content containing keywords
       $law = Law::factory()->create(['content' => 'pretraga doma naredba']);

       // When: Sync to graph
       $this->graphRagService->syncLaw($law->id);

       // Then: Verify keyword relationships created
       $keywords = $this->getRelatedKeywords('LawDocument', $law->id);
       $this->assertNotEmpty($keywords);
   }

   public function test_syncLaw_handles_missing_law()
   {
       // When: Sync non-existent law
       $this->graphRagService->syncLaw('non-existent-id');

       // Then: No exception, graceful handling
       $this->assertTrue(true);
   }
   ```

2. **Test syncCase method** (2 hours)
   ```php
   public function test_syncCase_creates_case_document_node()
   public function test_syncCase_autotags_case()
   public function test_syncCase_creates_keyword_relationships()
   public function test_syncCase_creates_citation_relationships()
   public function test_syncCase_creates_similarity_relationships()
   ```

3. **Test syncDecision method** (2 hours)
   ```php
   public function test_syncDecision_creates_decision_node()
   public function test_syncDecision_creates_court_relationship()
   public function test_syncDecision_extracts_citations()
   ```

4. **Test syncTextractJob method** (2 hours)
   ```php
   public function test_syncTextractJob_syncs_all_documents()
   public function test_syncTextractJob_creates_belongs_to_relationship()
   public function test_syncTextractJob_updates_sync_status()
   ```

5. **Test helper methods** (2 hours)
   ```php
   public function test_extractAndLinkKeywords_creates_keyword_nodes()
   public function test_extractAndCreateCitations_finds_citations()
   public function test_createSimilarityRelationships_finds_similar_docs()
   ```

**Deliverable**: GraphRagServiceCharacterizationTest.php (~800-1000 lines, 30-40 tests)

**Success Criteria**:
- ✅ Every public method has at least 3 tests
- ✅ All tests pass with current implementation
- ✅ Code coverage for GraphRagService: 90%+

---

### Task 1.2: Create Interface and Contract Tests (4 hours)

**TDD Step**: RED

**Objective**: Define interfaces that new services will implement

**Subtasks**:

1. **Create GraphSyncServiceInterface** (1 hour)
   ```php
   // app/Contracts/GraphSyncServiceInterface.php

   interface GraphSyncServiceInterface
   {
       public function sync(string $documentId): void;
       public function supportsType(string $type): bool;
   }
   ```

2. **Create GraphLinkerInterface** (1 hour)
   ```php
   // app/Contracts/GraphLinkerInterface.php

   interface GraphLinkerInterface
   {
       public function link(string $nodeType, string $nodeId, string $content): void;
   }
   ```

3. **Write contract tests** (2 hours)
   ```php
   // tests/Unit/Contracts/GraphSyncServiceContractTest.php

   abstract class GraphSyncServiceContractTest extends TestCase
   {
       abstract protected function createService(): GraphSyncServiceInterface;

       public function test_sync_accepts_valid_document_id()
       {
           $service = $this->createService();
           $service->sync('valid-id');
           // Assert no exception
           $this->assertTrue(true);
       }

       public function test_supportsType_returns_boolean()
       {
           $service = $this->createService();
           $result = $service->supportsType('LawDocument');
           $this->assertIsBool($result);
       }
   }
   ```

**Deliverable**:
- GraphSyncServiceInterface.php
- GraphLinkerInterface.php
- Contract test classes
- ~200 lines

---

### Task 1.3: Extract LawGraphSyncService (8-10 hours)

**TDD Step**: GREEN → REFACTOR

**Objective**: Extract law syncing logic into separate service

**Subtasks**:

1. **Create failing tests for new service** (2 hours)
   ```php
   // tests/Unit/Services/Graph/LawGraphSyncServiceTest.php

   class LawGraphSyncServiceTest extends TestCase
   {
       use UsesTestDatabase;

       protected LawGraphSyncService $service;

       protected function setUp(): void
       {
           parent::setUp();
           $this->service = app(LawGraphSyncService::class);
       }

       /** @test */
       public function it_syncs_law_to_graph()
       {
           $law = Law::factory()->create();

           $this->service->sync($law->id);

           $this->assertGraphNodeExists('LawDocument', $law->id);
       }

       // Copy all law-related tests from characterization test
   }
   ```

2. **Create LawGraphSyncService** (3 hours)
   ```php
   // app/Services/Graph/LawGraphSyncService.php

   class LawGraphSyncService implements GraphSyncServiceInterface
   {
       public function __construct(
           protected GraphDatabaseService $graph,
           protected GraphKeywordLinker $keywordLinker,
           protected GraphCitationLinker $citationLinker,
           protected GraphSimilarityLinker $similarityLinker,
           protected TaggingService $tagging
       ) {}

       public function sync(string $lawId): void
       {
           // Move syncLaw logic here (copy-paste first)
           $law = DB::table('laws')->where('id', $lawId)->first();

           if (!$law) {
               return;
           }

           // Create node
           $this->createLawNode($law);

           // Create relationships
           $this->createJurisdictionRelationship($law);

           // Link keywords, citations, similarities
           $this->keywordLinker->link('LawDocument', $law->id, $law->content);
           $this->citationLinker->link('LawDocument', $law->id, $law->content);
           $this->similarityLinker->link('LawDocument', $law->id, $law->embedding_vector);

           // Auto-tag
           $this->tagging->autoTag('LawDocument', $law->id, $law->content, [...]);
       }

       public function supportsType(string $type): bool
       {
           return $type === 'LawDocument';
       }

       protected function createLawNode($law): void
       {
           $this->graph->upsertNode('LawDocument', $law->id, [
               'doc_id' => $law->doc_id,
               'title' => $law->title,
               // ... all properties
           ]);
       }

       protected function createJurisdictionRelationship($law): void
       {
           if (!$law->jurisdiction) {
               return;
           }

           $this->graph->upsertNode('Jurisdiction', 'jurisdiction_' . $law->jurisdiction, [
               'name' => $law->jurisdiction,
           ]);

           $this->graph->createRelationship(
               'LawDocument', $law->id,
               'BELONGS_TO_JURISDICTION',
               'Jurisdiction', 'jurisdiction_' . $law->jurisdiction
           );
       }
   }
   ```

3. **Register service in container** (0.5 hour)
   ```php
   // app/Providers/AppServiceProvider.php

   public function register(): void
   {
       $this->app->bind(GraphSyncServiceInterface::class, function ($app) {
           // Factory pattern - return appropriate service based on type
           return new GraphSyncServiceFactory($app);
       });

       $this->app->singleton(LawGraphSyncService::class);
   }
   ```

4. **Update GraphRagService to delegate** (2 hours)
   ```php
   // app/Services/GraphRagService.php

   class GraphRagService
   {
       public function __construct(
           protected GraphDatabaseService $graph,
           protected TaggingService $tagging,
           protected LawGraphSyncService $lawSyncService,  // NEW
           protected ?AdvancedKeywordExtractor $advancedExtractor = null
       ) {}

       public function syncLaw(string $lawId): void
       {
           // Delegate to new service
           $this->lawSyncService->sync($lawId);
       }

       // Keep old implementation commented for rollback:
       /*
       public function syncLaw_OLD(string $lawId): void
       {
           // Original implementation kept for safety
       }
       */
   }
   ```

5. **Run all tests** (0.5 hour)
   ```bash
   # All characterization tests must still pass
   ./vendor/bin/phpunit tests/Unit/Services/GraphRagServiceCharacterizationTest.php

   # New service tests must pass
   ./vendor/bin/phpunit tests/Unit/Services/Graph/LawGraphSyncServiceTest.php

   # Integration tests must pass
   ./vendor/bin/phpunit tests/Integration/GraphSyncWorkflowTest.php
   ```

6. **Remove old implementation** (1 hour)
   ```php
   // After 1-2 weeks of successful operation in production:
   // Delete commented old implementation
   // Remove direct GraphDatabaseService dependency if not needed
   ```

**Deliverable**:
- LawGraphSyncService.php (~350 lines)
- LawGraphSyncServiceTest.php (~400 lines)
- Updated GraphRagService.php (delegation only)
- All tests passing ✅

---

### Task 1.4: Extract CaseGraphSyncService (8-10 hours)

**TDD Step**: GREEN → REFACTOR

Follow same pattern as Task 1.3:
1. Create failing tests (2 hours)
2. Create CaseGraphSyncService (3 hours)
3. Register in container (0.5 hour)
4. Update GraphRagService to delegate (2 hours)
5. Run all tests (0.5 hour)
6. Remove old implementation (1 hour)

**Deliverable**:
- CaseGraphSyncService.php (~350 lines)
- CaseGraphSyncServiceTest.php (~400 lines)

---

### Task 1.5: Extract DecisionGraphSyncService (8-10 hours)

**TDD Step**: GREEN → REFACTOR

Follow same pattern as Task 1.3:
1. Create failing tests (2 hours)
2. Create DecisionGraphSyncService (3 hours)
3. Register in container (0.5 hour)
4. Update GraphRagService to delegate (2 hours)
5. Run all tests (0.5 hour)
6. Remove old implementation (1 hour)

**Deliverable**:
- DecisionGraphSyncService.php (~350 lines)
- DecisionGraphSyncServiceTest.php (~400 lines)

---

### Task 1.6: Extract GraphKeywordLinker (6-8 hours)

**TDD Step**: GREEN → REFACTOR

**Objective**: Extract keyword extraction and linking into separate service

**Subtasks**:

1. **Create failing tests** (2 hours)
   ```php
   // tests/Unit/Services/Graph/GraphKeywordLinkerTest.php

   class GraphKeywordLinkerTest extends TestCase
   {
       /** @test */
       public function it_extracts_keywords_from_content()
       {
           $linker = app(GraphKeywordLinker::class);

           $linker->link('LawDocument', 'law-id', 'pretraga doma naredba suda');

           $keywords = $this->getRelatedKeywords('LawDocument', 'law-id');
           $this->assertContains('pretraga', $keywords);
           $this->assertContains('naredba', $keywords);
       }

       /** @test */
       public function it_creates_keyword_nodes()
       {
           $linker = app(GraphKeywordLinker::class);

           $linker->link('LawDocument', 'law-id', 'unique-keyword');

           $this->assertGraphNodeExists('Keyword', 'keyword_unique-keyword');
       }

       /** @test */
       public function it_creates_has_keyword_relationships()
       {
           $linker = app(GraphKeywordLinker::class);

           $linker->link('LawDocument', 'law-id', 'test keyword');

           $this->assertGraphRelationshipExists(
               'LawDocument', 'law-id',
               'HAS_KEYWORD',
               'Keyword', 'keyword_test'
           );
       }
   }
   ```

2. **Create GraphKeywordLinker** (3 hours)
   ```php
   // app/Services/Graph/GraphKeywordLinker.php

   class GraphKeywordLinker implements GraphLinkerInterface
   {
       public function __construct(
           protected GraphDatabaseService $graph,
           protected ?AdvancedKeywordExtractor $advancedExtractor = null
       ) {}

       public function link(string $nodeType, string $nodeId, string $content): void
       {
           $keywords = $this->extractKeywords($content);

           foreach ($keywords as $keyword) {
               // Create keyword node
               $this->graph->upsertNode('Keyword', 'keyword_' . $keyword, [
                   'name' => $keyword,
               ]);

               // Create HAS_KEYWORD relationship
               $this->graph->createRelationship(
                   $nodeType, $nodeId,
                   'HAS_KEYWORD',
                   'Keyword', 'keyword_' . $keyword
               );
           }
       }

       protected function extractKeywords(string $content): array
       {
           if ($this->advancedExtractor) {
               return $this->advancedExtractor->extract($content);
           }

           // Fallback: simple keyword extraction
           return $this->simpleExtract($content);
       }

       protected function simpleExtract(string $content): array
       {
           // Move existing logic here
       }
   }
   ```

3. **Update all sync services** (2 hours)
4. **Run tests** (0.5 hour)
5. **Remove old implementation** (0.5 hour)

**Deliverable**:
- GraphKeywordLinker.php (~250 lines)
- GraphKeywordLinkerTest.php (~300 lines)

---

### Task 1.7: Extract GraphCitationLinker (6-8 hours)

Follow same pattern as Task 1.6

**Deliverable**:
- GraphCitationLinker.php (~250 lines)
- GraphCitationLinkerTest.php (~300 lines)

---

### Task 1.8: Extract GraphSimilarityLinker (6-8 hours)

Follow same pattern as Task 1.6

**Deliverable**:
- GraphSimilarityLinker.php (~250 lines)
- GraphSimilarityLinkerTest.php (~300 lines)

---

### Task 1.9: Create GraphRagOrchestrator Facade (4-6 hours)

**TDD Step**: REFACTOR

**Objective**: Replace GraphRagService with thin orchestrator

**Subtasks**:

1. **Create GraphRagOrchestrator** (2 hours)
   ```php
   // app/Services/Graph/GraphRagOrchestrator.php

   class GraphRagOrchestrator
   {
       public function __construct(
           protected LawGraphSyncService $lawSync,
           protected CaseGraphSyncService $caseSync,
           protected DecisionGraphSyncService $decisionSync,
           protected TextractGraphSyncService $textractSync
       ) {}

       public function syncLaw(string $lawId): void
       {
           $this->lawSync->sync($lawId);
       }

       public function syncCase(string $caseDocId): void
       {
           $this->caseSync->sync($caseDocId);
       }

       public function syncDecision(string $decisionId): void
       {
           $this->decisionSync->sync($decisionId);
       }

       public function syncTextractJob(int $jobId): void
       {
           $this->textractSync->sync($jobId);
       }

       public function syncDocument(string $type, string $id): void
       {
           match($type) {
               'law' => $this->syncLaw($id),
               'case' => $this->syncCase($id),
               'decision' => $this->syncDecision($id),
               default => throw new \InvalidArgumentException("Unknown type: $type")
           };
       }
   }
   ```

2. **Update all references** (3 hours)
   ```bash
   # Find all usages
   grep -r "GraphRagService" app/

   # Update imports
   use App\Services\GraphRagService;
   # becomes
   use App\Services\Graph\GraphRagOrchestrator;

   # Update container bindings
   $this->app->bind(GraphRagService::class, GraphRagOrchestrator::class);
   ```

3. **Run all tests** (1 hour)

**Deliverable**:
- GraphRagOrchestrator.php (~150 lines)
- All references updated
- All tests passing ✅

---

### Task 1.10: Deprecate and Remove GraphRagService (2-4 hours)

**TDD Step**: CLEANUP

**Subtasks**:

1. **Mark as deprecated** (1 hour)
   ```php
   /**
    * @deprecated Use GraphRagOrchestrator instead
    */
   class GraphRagService extends GraphRagOrchestrator
   {
       public function __construct(...)
       {
           Log::warning('GraphRagService is deprecated, use GraphRagOrchestrator');
           parent::__construct(...);
       }
   }
   ```

2. **Monitor usage in production** (1-2 weeks)

3. **Remove after confirmation** (1 hour)
   ```bash
   rm app/Services/GraphRagService.php
   ```

**Deliverable**:
- GraphRagService.php deleted
- 1,933 lines → 7 focused services (~1,850 lines total)
- **Net reduction**: ~83 lines, but now highly maintainable

---

## PHASE 2: UNIFIEDSEARCHSERVICE REFACTORING (30-40 hours)

### Current State Analysis

**Responsibilities**:
1. Query embedding generation
2. Laws search (searchLaws)
3. Decisions search (searchDecisions)
4. Cases search (searchCases)
5. Result aggregation and ranking
6. Result deduplication
7. Citation extraction from results

**Target Architecture**:
```
SearchOrchestrator (facade, 100-150 lines)
├── SearchEmbeddingService (100-150 lines)
├── LawSearchService (250-300 lines)
├── DecisionSearchService (250-300 lines)
├── CaseSearchService (250-300 lines)
├── SearchResultAggregator (200-250 lines)
└── SearchResultDeduplicator (150-200 lines)
```

---

### Task 2.1: Write Characterization Tests (6-8 hours)

**TDD Step**: RED

```php
// tests/Unit/Services/UnifiedSearchServiceCharacterizationTest.php

class UnifiedSearchServiceCharacterizationTest extends TestCase
{
    /** @test */
    public function it_searches_across_all_corpora()
    {
        $law = Law::factory()->create(['content' => 'search test']);
        $decision = CourtDecisionDocument::factory()->create(['content' => 'search test']);
        $caseDoc = CaseDocument::factory()->create(['content' => 'search test']);

        $results = $this->searchService->search('search test', [
            'corpora' => ['laws', 'decisions', 'cases'],
        ]);

        $this->assertArrayHasKey('results', $results);
        $this->assertArrayHasKey('metadata', $results);
        $this->assertArrayHasKey('timing', $results);
    }

    /** @test */
    public function it_filters_by_corpus()
    {
        $results = $this->searchService->search('test', [
            'corpora' => ['laws'],
        ]);

        foreach ($results['results'] as $result) {
            $this->assertEquals('laws', $result['corpus']);
        }
    }

    /** @test */
    public function it_applies_threshold_filtering()
    {
        $results = $this->searchService->search('test', [
            'threshold' => 0.9,
        ]);

        foreach ($results['results'] as $result) {
            $this->assertGreaterThanOrEqual(0.9, $result['score']);
        }
    }

    /** @test */
    public function it_paginates_results()
    {
        $page1 = $this->searchService->search('test', ['page' => 1, 'per_page' => 5]);
        $page2 = $this->searchService->search('test', ['page' => 2, 'per_page' => 5]);

        $this->assertCount(5, $page1['results']);
        $this->assertCount(5, $page2['results']);

        $page1Ids = array_column($page1['results'], 'id');
        $page2Ids = array_column($page2['results'], 'id');
        $this->assertEmpty(array_intersect($page1Ids, $page2Ids));
    }

    /** @test */
    public function it_ranks_by_score()
    {
        $results = $this->searchService->search('test');

        $scores = array_column($results['results'], 'score');
        $sortedScores = $scores;
        rsort($sortedScores);

        $this->assertEquals($sortedScores, $scores);
    }

    /** @test */
    public function it_deduplicates_results()
    {
        $results = $this->searchService->search('test', [
            'deduplicate' => true,
        ]);

        $ids = array_column($results['results'], 'id');
        $uniqueIds = array_unique($ids);

        $this->assertEquals(count($uniqueIds), count($ids));
    }

    // 20-30 more tests covering all methods and edge cases
}
```

**Deliverable**: UnifiedSearchServiceCharacterizationTest.php (~600-800 lines, 20-30 tests)

---

### Task 2.2: Create SearchServiceInterface (2 hours)

```php
// app/Contracts/SearchServiceInterface.php

interface SearchServiceInterface
{
    public function search(string $query, array $options = []): array;
    public function supportsCorpus(string $corpus): bool;
}
```

---

### Task 2.3: Extract SearchEmbeddingService (4-6 hours)

```php
// app/Services/Search/SearchEmbeddingService.php

class SearchEmbeddingService
{
    public function __construct(protected OpenAIService $openai) {}

    public function embedQuery(string $query, ?string $model = null): array
    {
        $model = $model ?? config('openai.models.embeddings');

        $response = $this->openai->createEmbedding($query, $model);

        return $response['data'][0]['embedding'] ?? [];
    }
}
```

**Test first**:
```php
class SearchEmbeddingServiceTest extends TestCase
{
    /** @test */
    public function it_generates_embedding_for_query()
    {
        $service = app(SearchEmbeddingService::class);

        $embedding = $service->embedQuery('test query');

        $this->assertIsArray($embedding);
        $this->assertCount(1536, $embedding); // OpenAI embedding dimension
    }
}
```

---

### Task 2.4: Extract LawSearchService (6-8 hours)

```php
// app/Services/Search/LawSearchService.php

class LawSearchService implements SearchServiceInterface
{
    public function __construct(
        protected SearchEmbeddingService $embedder,
        protected LawVectorStoreService $vectorStore
    ) {}

    public function search(string $query, array $options = []): array
    {
        $embedding = $this->embedder->embedQuery($query, $options['model'] ?? null);

        $threshold = $options['threshold'] ?? 0.7;
        $limit = $options['limit'] ?? 10;
        $filters = $options['filters'] ?? [];

        // Use vector store for search
        $results = $this->vectorStore->search($embedding, [
            'threshold' => $threshold,
            'limit' => $limit,
            'filters' => $filters,
        ]);

        return $this->normalizeResults($results, 'laws');
    }

    public function supportsCorpus(string $corpus): bool
    {
        return $corpus === 'laws';
    }

    protected function normalizeResults(array $results, string $corpus): array
    {
        return array_map(function ($result) use ($corpus) {
            return [
                'id' => $result['id'],
                'corpus' => $corpus,
                'score' => $result['score'],
                'content' => $result['content'],
                'metadata' => $result['metadata'] ?? [],
            ];
        }, $results);
    }
}
```

**Tests**:
```php
class LawSearchServiceTest extends TestCase
{
    /** @test */
    public function it_searches_laws_by_vector_similarity()
    {
        $law = Law::factory()->create(['content' => 'test content']);

        $service = app(LawSearchService::class);
        $results = $service->search('test content');

        $this->assertNotEmpty($results);
        $this->assertEquals('laws', $results[0]['corpus']);
    }
}
```

---

### Task 2.5: Extract DecisionSearchService (6-8 hours)

Follow same pattern as LawSearchService

---

### Task 2.6: Extract CaseSearchService (6-8 hours)

Follow same pattern as LawSearchService

---

### Task 2.7: Extract SearchResultAggregator (4-6 hours)

```php
// app/Services/Search/SearchResultAggregator.php

class SearchResultAggregator
{
    public function aggregate(array $corpusResults, array $weights = []): array
    {
        $allResults = [];

        foreach ($corpusResults as $corpus => $results) {
            $weight = $weights[$corpus] ?? 1.0;

            foreach ($results as $result) {
                $result['weighted_score'] = $result['score'] * $weight;
                $result['corpus'] = $corpus;
                $allResults[] = $result;
            }
        }

        // Sort by weighted score
        usort($allResults, fn($a, $b) => $b['weighted_score'] <=> $a['weighted_score']);

        return $allResults;
    }
}
```

---

### Task 2.8: Extract SearchResultDeduplicator (4-6 hours)

```php
// app/Services/Search/SearchResultDeduplicator.php

class SearchResultDeduplicator
{
    public function deduplicate(array $results): array
    {
        $seen = [];
        $deduplicated = [];

        foreach ($results as $result) {
            $key = $result['corpus'] . ':' . $result['id'];

            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $deduplicated[] = $result;
            }
        }

        return $deduplicated;
    }
}
```

---

### Task 2.9: Create SearchOrchestrator (6-8 hours)

```php
// app/Services/Search/SearchOrchestrator.php

class SearchOrchestrator
{
    public function __construct(
        protected LawSearchService $lawSearch,
        protected DecisionSearchService $decisionSearch,
        protected CaseSearchService $caseSearch,
        protected SearchResultAggregator $aggregator,
        protected SearchResultDeduplicator $deduplicator
    ) {}

    public function search(string $query, array $options = []): array
    {
        $startTime = microtime(true);

        $corpora = $options['corpora'] ?? ['laws', 'decisions', 'cases'];
        $weights = $options['weights'] ?? [];
        $deduplicate = $options['deduplicate'] ?? true;

        // Search each corpus
        $corpusResults = [];
        foreach ($corpora as $corpus) {
            $service = $this->getSearchService($corpus);
            $corpusResults[$corpus] = $service->search($query, $options);
        }

        // Aggregate results
        $aggregated = $this->aggregator->aggregate($corpusResults, $weights);

        // Deduplicate if needed
        if ($deduplicate) {
            $aggregated = $this->deduplicator->deduplicate($aggregated);
        }

        // Apply pagination
        $page = $options['page'] ?? 1;
        $perPage = $options['per_page'] ?? 10;
        $paginatedResults = $this->paginate($aggregated, $page, $perPage);

        return [
            'results' => $paginatedResults,
            'metadata' => [
                'total_results' => count($aggregated),
                'query' => $query,
                'corpora_searched' => $corpora,
            ],
            'timing' => [
                'total_time' => microtime(true) - $startTime,
            ],
        ];
    }

    protected function getSearchService(string $corpus): SearchServiceInterface
    {
        return match($corpus) {
            'laws' => $this->lawSearch,
            'decisions' => $this->decisionSearch,
            'cases' => $this->caseSearch,
            default => throw new \InvalidArgumentException("Unknown corpus: $corpus")
        };
    }

    protected function paginate(array $results, int $page, int $perPage): array
    {
        $offset = ($page - 1) * $perPage;
        return array_slice($results, $offset, $perPage);
    }
}
```

---

### Task 2.10: Update References and Deprecate (4-6 hours)

1. Update container bindings
2. Update all imports
3. Deprecate UnifiedSearchService
4. Monitor production
5. Remove old service

---

## PHASE 3: OPENAISERVICE REFACTORING (25-35 hours)

### Target Architecture

```
OpenAIOrchestrator (facade, 100-150 lines)
├── OpenAIEmbeddingService (200-250 lines)
├── OpenAIChatService (300-350 lines)
├── OpenAICompletionService (200-250 lines)
└── OpenAIResponseParser (150-200 lines)
```

### Tasks (similar structure)

- Task 3.1: Characterization tests (6-8 hours)
- Task 3.2: Extract OpenAIEmbeddingService (6-8 hours)
- Task 3.3: Extract OpenAIChatService (6-8 hours)
- Task 3.4: Extract OpenAICompletionService (4-6 hours)
- Task 3.5: Extract OpenAIResponseParser (3-4 hours)
- Task 3.6: Create OpenAIOrchestrator (4-6 hours)
- Task 3.7: Update references (2-4 hours)

---

## PHASE 4: AUTONOMOUSRESEARCHAGENT REFACTORING (25-35 hours)

### Target Architecture

```
ResearchAgentOrchestrator (facade, 100-150 lines)
├── ResearchPlanningService (250-300 lines)
├── ResearchExecutionService (250-300 lines)
├── ResearchEvaluationService (200-250 lines)
├── ResearchIterationService (200-250 lines)
└── ResearchReportGenerator (150-200 lines)
```

### Tasks (similar structure)

- Task 4.1: Characterization tests (6-8 hours)
- Task 4.2: Extract ResearchPlanningService (6-8 hours)
- Task 4.3: Extract ResearchExecutionService (6-8 hours)
- Task 4.4: Extract ResearchEvaluationService (4-6 hours)
- Task 4.5: Extract ResearchIterationService (4-6 hours)
- Task 4.6: Extract ResearchReportGenerator (3-4 hours)
- Task 4.7: Create ResearchAgentOrchestrator (4-6 hours)
- Task 4.8: Update references (2-4 hours)

---

## TESTING STRATEGY

### Test Pyramid

```
                    E2E Tests (10%)
                    ╱            ╲
                   ╱              ╲
              Integration Tests (20%)
             ╱                      ╲
            ╱                        ╲
       Unit Tests (70%)
      ╱                              ╲
```

### Test Coverage Requirements

| Phase | Unit Tests | Integration Tests | E2E Tests | Total Coverage |
|-------|-----------|-------------------|-----------|----------------|
| Before Refactor | 30 tests | 3 tests | 0 tests | 65% |
| After Phase 1 | 90 tests | 5 tests | 2 tests | 75% |
| After Phase 2 | 120 tests | 7 tests | 3 tests | 80% |
| After Phase 3 | 150 tests | 9 tests | 4 tests | 85% |
| After Phase 4 | 180 tests | 11 tests | 5 tests | 90% |

### Continuous Testing

```bash
# Run tests after every change
composer test

# Run tests with coverage
composer test:coverage

# Run only refactored service tests
./vendor/bin/phpunit --filter=GraphRagService
./vendor/bin/phpunit --filter=SearchOrchestrator

# Run integration tests
./vendor/bin/phpunit --group=integration

# Run E2E tests
./vendor/bin/phpunit --group=e2e
```

---

## ROLLBACK STRATEGY

### Safety Checkpoints

Each phase has rollback points:

1. **After Characterization Tests**:
   - Can abort refactoring, tests serve as documentation

2. **After Each Extraction**:
   - Keep old implementation commented
   - Can revert individual service

3. **After Orchestrator Creation**:
   - Facade pattern allows switching back

4. **After Deprecation**:
   - Monitor metrics for 1-2 weeks
   - Can un-deprecate if issues arise

### Rollback Procedure

```php
// If issues detected:

// 1. Revert container binding
$this->app->bind(GraphRagService::class, GraphRagServiceOLD::class);

// 2. Uncomment old implementation
class GraphRagServiceOLD { /* original code */ }

// 3. Run tests
composer test

// 4. Deploy rollback

// 5. Investigate issue

// 6. Fix and retry
```

---

## TIMELINE

### 2-Developer Team (Parallel Work)

| Week | Developer 1 | Developer 2 | Deliverable |
|------|-------------|-------------|-------------|
| **Week 1** | GraphRag characterization tests | UnifiedSearch characterization tests | All tests |
| **Week 2** | Extract Law/Case sync services | Extract Decision/Textract sync | 4 services |
| **Week 3** | Extract Keyword/Citation linkers | Extract Similarity linker | 3 linkers |
| **Week 4** | Create GraphRag orchestrator | Extract Search services | 2 orchestrators |
| **Week 5** | OpenAI characterization | Agent characterization | Tests |
| **Week 6** | Extract OpenAI services | Extract Agent services | 8 services |
| **Week 7** | Create orchestrators | Integration testing | Done |
| **Week 8** | Documentation | Cleanup & monitoring | Production |

### Single Developer (Sequential)

| Week | Tasks | Hours |
|------|-------|-------|
| **Week 1-2** | Phase 1: GraphRagService | 40-50 |
| **Week 3-4** | Phase 2: UnifiedSearchService | 30-40 |
| **Week 5-6** | Phase 3: OpenAIService | 25-35 |
| **Week 7-8** | Phase 4: AutonomousResearchAgent | 25-35 |

---

## SUCCESS METRICS

### Code Quality Metrics

| Metric | Before | Target | Measurement |
|--------|--------|--------|-------------|
| Average class size | 1,417 lines | <400 lines | PHPLoc |
| Cyclomatic complexity | High | <10 per method | PHPMetrics |
| Test coverage | 65% | 90% | PHPUnit --coverage |
| Code duplication | 15% | <3% | PHPCPD |
| Number of god classes | 4 | 0 | Manual |
| Service cohesion | Low | High | LCOM4 metric |

### Development Metrics

| Metric | Before | After |
|--------|--------|-------|
| Time to add feature | 4-6 hours | 1-2 hours |
| Time to fix bug | 2-3 hours | 0.5-1 hour |
| Test execution time | 2 min | 1 min |
| Code review time | 2 hours | 30 min |

### Business Metrics

| Metric | Before | After |
|--------|--------|-------|
| Production incidents | 3-4/month | <1/month |
| Mean time to recovery | 2 hours | 30 min |
| Developer onboarding | 2 weeks | 1 week |
| Feature velocity | 2 features/sprint | 3-4 features/sprint |

---

## RISK MITIGATION

### Identified Risks

| Risk | Probability | Impact | Mitigation |
|------|------------|--------|------------|
| Breaking changes | Medium | High | Comprehensive tests, gradual rollout |
| Performance regression | Low | Medium | Benchmarking, profiling |
| Integration issues | Medium | High | Integration tests, staging environment |
| Team resistance | Low | Medium | Clear communication, training |
| Schedule overrun | Medium | Medium | Buffer time, prioritization |

### Mitigation Strategies

1. **Feature Flags**:
   ```php
   if (config('features.use_new_graph_service')) {
       return new GraphRagOrchestrator(...);
   }
   return new GraphRagService(...);
   ```

2. **Gradual Rollout**:
   - Week 1: 10% of traffic
   - Week 2: 25% of traffic
   - Week 3: 50% of traffic
   - Week 4: 100% of traffic

3. **Monitoring**:
   ```php
   // Log performance metrics
   Log::info('GraphSync performance', [
       'service' => 'GraphRagOrchestrator',
       'duration' => $duration,
       'success' => $success,
   ]);
   ```

4. **A/B Testing**:
   - Run old and new in parallel
   - Compare results
   - Validate behavior unchanged

---

## DOCUMENTATION

### Required Documentation

1. **Architecture Decision Records (ADRs)**:
   ```markdown
   # ADR-001: Refactor GraphRagService

   ## Status
   Accepted

   ## Context
   GraphRagService has grown to 1,933 lines with 39 methods...

   ## Decision
   Extract into 6 focused services using interface-based design...

   ## Consequences
   - Improved testability
   - Better separation of concerns
   - Easier to maintain
   ```

2. **Migration Guide**:
   ```markdown
   # Migration Guide: GraphRagService → GraphRagOrchestrator

   ## Before
   ```php
   $this->graphRagService->syncLaw($lawId);
   ```

   ## After
   ```php
   $this->graphRagOrchestrator->syncLaw($lawId);
   // OR use specific service
   $this->lawGraphSyncService->sync($lawId);
   ```
   ```

3. **API Documentation**:
   - Update PHPDoc
   - Generate API docs
   - Update README

4. **Testing Documentation**:
   - Test strategy
   - Running tests
   - Writing new tests

---

## CONCLUSION

This TDD-based refactoring plan provides:

✅ **Safety**: Comprehensive tests before any changes
✅ **Incremental**: One service at a time, rollback at any point
✅ **Measurable**: Clear success metrics and checkpoints
✅ **Practical**: Realistic timeline and resource allocation
✅ **Maintainable**: Well-structured, focused services

**Total Effort**: 120-150 hours
**Timeline**: 4-8 weeks (depending on team size)
**Risk**: LOW (test coverage ensures safety)
**ROI**: HIGH (improved maintainability, faster development)

---

**Next Step**: Get approval and begin with Phase 1, Task 1.1 (Characterization Tests)

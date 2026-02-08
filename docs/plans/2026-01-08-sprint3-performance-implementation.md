# Sprint 3: Performance Tuning Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Reduce graph sync time from ~60s/10 nodes to ~6s/10 nodes (10x improvement)

**Architecture:** Three optimization tracks - batch Cypher operations (UNWIND), parallel extraction (Laravel Concurrency), and selective extraction (document type filtering). Each track is independently valuable and compounds for total 10x improvement.

**Tech Stack:** Laravel 11, Neo4j 5.x with Laudis PHP driver, Laravel Concurrency for parallel processing

---

## Wave 1: Foundation

### Task 1: Create BatchCypherBuilder Service - Test Setup

**Files:**
- Create: `tests/Unit/Services/Graph/BatchCypherBuilderTest.php`

**Step 1: Create test file with first failing test**

```php
<?php

namespace Tests\Unit\Services\Graph;

use App\Services\Graph\BatchCypherBuilder;
use Tests\TestCase;

class BatchCypherBuilderTest extends TestCase
{
    private BatchCypherBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new BatchCypherBuilder();
    }

    /** @test */
    public function it_builds_batch_upsert_query_for_nodes(): void
    {
        $nodes = [
            ['id' => 'node_1', 'name' => 'First', 'type' => 'test'],
            ['id' => 'node_2', 'name' => 'Second', 'type' => 'test'],
        ];

        $result = $this->builder->buildBatchUpsertNodes('TestLabel', $nodes, 'id');

        $this->assertArrayHasKey('query', $result);
        $this->assertArrayHasKey('parameters', $result);
        $this->assertStringContainsString('UNWIND', $result['query']);
        $this->assertStringContainsString('MERGE', $result['query']);
        $this->assertStringContainsString('TestLabel', $result['query']);
        $this->assertCount(2, $result['parameters']['nodes']);
    }
}
```

**Step 2: Run test to verify it fails**

```bash
./vendor/bin/phpunit tests/Unit/Services/Graph/BatchCypherBuilderTest.php --filter=it_builds_batch_upsert_query_for_nodes -v
```

Expected: FAIL with "Class 'App\Services\Graph\BatchCypherBuilder' not found"

**Step 3: Commit test file**

```bash
git add tests/Unit/Services/Graph/BatchCypherBuilderTest.php
git commit -m "test: [Sprint 3 - A.1] Add BatchCypherBuilder test setup"
```

---

### Task 2: Create BatchCypherBuilder Service - Implementation

**Files:**
- Create: `app/Services/Graph/BatchCypherBuilder.php`

**Step 1: Create the BatchCypherBuilder class**

```php
<?php

namespace App\Services\Graph;

use InvalidArgumentException;

class BatchCypherBuilder
{
    /**
     * Default batch flush threshold
     */
    protected int $flushThreshold;

    public function __construct(?int $flushThreshold = null)
    {
        $this->flushThreshold = $flushThreshold ?? config('graph.batch.flush_threshold', 100);
    }

    /**
     * Build a batch MERGE query for nodes using UNWIND
     *
     * @param string $label Node label
     * @param array $nodes Array of node data arrays
     * @param string $idKey Property to use as unique identifier
     * @return array{query: string, parameters: array}
     */
    public function buildBatchUpsertNodes(string $label, array $nodes, string $idKey = 'id'): array
    {
        if (empty($nodes)) {
            throw new InvalidArgumentException('Nodes array cannot be empty');
        }

        if (empty($label)) {
            throw new InvalidArgumentException('Label cannot be empty');
        }

        // Build the property SET clause dynamically from first node's keys
        $firstNode = reset($nodes);
        $propKeys = array_keys($firstNode);
        $setClause = implode(', ', array_map(fn($k) => "n.{$k} = node.{$k}", $propKeys));

        $query = <<<CYPHER
UNWIND \$nodes AS node
MERGE (n:{$label} {{$idKey}: node.{$idKey}})
SET {$setClause}
RETURN count(n) AS nodesProcessed
CYPHER;

        return [
            'query' => $query,
            'parameters' => ['nodes' => array_values($nodes)],
        ];
    }

    /**
     * Build a batch MERGE query for relationships using UNWIND
     *
     * @param string $fromLabel Source node label
     * @param string $toLabel Target node label
     * @param string $relType Relationship type
     * @param array $relationships Array of relationship data with fromId, toId, and optional props
     * @return array{query: string, parameters: array}
     */
    public function buildBatchUpsertRelationships(
        string $fromLabel,
        string $toLabel,
        string $relType,
        array $relationships,
        string $fromIdKey = 'id',
        string $toIdKey = 'id'
    ): array {
        if (empty($relationships)) {
            throw new InvalidArgumentException('Relationships array cannot be empty');
        }

        // Check if relationships have properties beyond fromId/toId
        $firstRel = reset($relationships);
        $hasProps = count(array_diff(array_keys($firstRel), ['fromId', 'toId'])) > 0;

        if ($hasProps) {
            $propKeys = array_diff(array_keys($firstRel), ['fromId', 'toId']);
            $setClause = 'SET ' . implode(', ', array_map(fn($k) => "r.{$k} = rel.{$k}", $propKeys));
        } else {
            $setClause = '';
        }

        $query = <<<CYPHER
UNWIND \$relationships AS rel
MATCH (from:{$fromLabel} {{$fromIdKey}: rel.fromId})
MATCH (to:{$toLabel} {{$toIdKey}: rel.toId})
MERGE (from)-[r:{$relType}]->(to)
{$setClause}
RETURN count(r) AS relationshipsProcessed
CYPHER;

        return [
            'query' => trim($query),
            'parameters' => ['relationships' => array_values($relationships)],
        ];
    }

    /**
     * Get flush threshold
     */
    public function getFlushThreshold(): int
    {
        return $this->flushThreshold;
    }

    /**
     * Chunk array into batches based on flush threshold
     *
     * @param array $items Items to chunk
     * @return array Array of chunks
     */
    public function chunk(array $items): array
    {
        return array_chunk($items, $this->flushThreshold);
    }
}
```

**Step 2: Run test to verify it passes**

```bash
./vendor/bin/phpunit tests/Unit/Services/Graph/BatchCypherBuilderTest.php --filter=it_builds_batch_upsert_query_for_nodes -v
```

Expected: PASS

**Step 3: Commit implementation**

```bash
git add app/Services/Graph/BatchCypherBuilder.php
git commit -m "feat: [Sprint 3 - A.1] Add BatchCypherBuilder service for UNWIND queries"
```

---

### Task 3: Add More BatchCypherBuilder Tests

**Files:**
- Modify: `tests/Unit/Services/Graph/BatchCypherBuilderTest.php`

**Step 1: Add additional tests**

Add after the first test in the test class:

```php
    /** @test */
    public function it_builds_batch_upsert_query_for_relationships(): void
    {
        $relationships = [
            ['fromId' => 'node_1', 'toId' => 'node_2', 'weight' => 0.8],
            ['fromId' => 'node_2', 'toId' => 'node_3', 'weight' => 0.6],
        ];

        $result = $this->builder->buildBatchUpsertRelationships(
            'FromLabel',
            'ToLabel',
            'RELATES_TO',
            $relationships
        );

        $this->assertArrayHasKey('query', $result);
        $this->assertArrayHasKey('parameters', $result);
        $this->assertStringContainsString('UNWIND', $result['query']);
        $this->assertStringContainsString('MERGE', $result['query']);
        $this->assertStringContainsString('RELATES_TO', $result['query']);
        $this->assertCount(2, $result['parameters']['relationships']);
    }

    /** @test */
    public function it_throws_exception_for_empty_nodes_array(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Nodes array cannot be empty');

        $this->builder->buildBatchUpsertNodes('TestLabel', []);
    }

    /** @test */
    public function it_throws_exception_for_empty_label(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Label cannot be empty');

        $this->builder->buildBatchUpsertNodes('', [['id' => '1']]);
    }

    /** @test */
    public function it_chunks_array_based_on_flush_threshold(): void
    {
        $builder = new BatchCypherBuilder(2);
        $items = [1, 2, 3, 4, 5];

        $chunks = $builder->chunk($items);

        $this->assertCount(3, $chunks);
        $this->assertEquals([1, 2], $chunks[0]);
        $this->assertEquals([3, 4], $chunks[1]);
        $this->assertEquals([5], $chunks[2]);
    }

    /** @test */
    public function it_uses_config_for_default_flush_threshold(): void
    {
        config(['graph.batch.flush_threshold' => 50]);

        $builder = new BatchCypherBuilder();

        $this->assertEquals(50, $builder->getFlushThreshold());
    }
```

**Step 2: Run all tests**

```bash
./vendor/bin/phpunit tests/Unit/Services/Graph/BatchCypherBuilderTest.php -v
```

Expected: All 6 tests PASS

**Step 3: Commit**

```bash
git add tests/Unit/Services/Graph/BatchCypherBuilderTest.php
git commit -m "test: [Sprint 3 - A.1] Add comprehensive BatchCypherBuilder tests"
```

---

### Task 4: Create DocumentTypeDetector Service - Test Setup

**Files:**
- Create: `tests/Unit/Services/Graph/DocumentTypeDetectorTest.php`

**Step 1: Create test file**

```php
<?php

namespace Tests\Unit\Services\Graph;

use App\Services\Graph\DocumentTypeDetector;
use Tests\TestCase;

class DocumentTypeDetectorTest extends TestCase
{
    private DocumentTypeDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->detector = new DocumentTypeDetector();
    }

    /** @test */
    public function it_detects_court_decision_document_type(): void
    {
        $content = 'REPUBLIKA HRVATSKA U IME REPUBLIKE HRVATSKE PRESUDA Općinski sud u Zagrebu';

        $result = $this->detector->detect($content);

        $this->assertEquals('court_decision', $result);
    }

    /** @test */
    public function it_detects_law_document_type(): void
    {
        $content = 'ZAKON O OBVEZNIM ODNOSIMA Članak 1. Ovim se Zakonom uređuju obvezni odnosi';

        $result = $this->detector->detect($content);

        $this->assertEquals('law', $result);
    }

    /** @test */
    public function it_returns_generic_for_unknown_document_type(): void
    {
        $content = 'This is some generic text without legal markers.';

        $result = $this->detector->detect($content);

        $this->assertEquals('generic', $result);
    }

    /** @test */
    public function it_returns_extractors_for_court_decision(): void
    {
        $extractors = $this->detector->getExtractorsForType('court_decision');

        $this->assertContains('LegalTopicExtractor', $extractors);
        $this->assertContains('LawyerExtractor', $extractors);
        $this->assertContains('VerdictExtractor', $extractors);
        $this->assertContains('EvidenceExtractor', $extractors);
        $this->assertCount(9, $extractors); // All 9 extractors
    }

    /** @test */
    public function it_returns_limited_extractors_for_law_document(): void
    {
        $extractors = $this->detector->getExtractorsForType('law');

        $this->assertContains('ArticleExtractor', $extractors);
        $this->assertContains('LegalDefinitionExtractor', $extractors);
        $this->assertContains('LegalConceptExtractor', $extractors);
        $this->assertContains('LegalTopicExtractor', $extractors);
        $this->assertNotContains('LawyerExtractor', $extractors);
        $this->assertNotContains('VerdictExtractor', $extractors);
    }

    /** @test */
    public function it_returns_minimal_extractors_for_generic(): void
    {
        $extractors = $this->detector->getExtractorsForType('generic');

        $this->assertContains('DateEventExtractor', $extractors);
        $this->assertContains('LegalConceptExtractor', $extractors);
        $this->assertContains('LegalTopicExtractor', $extractors);
        $this->assertCount(3, $extractors);
    }
}
```

**Step 2: Run test to verify it fails**

```bash
./vendor/bin/phpunit tests/Unit/Services/Graph/DocumentTypeDetectorTest.php -v
```

Expected: FAIL with "Class 'App\Services\Graph\DocumentTypeDetector' not found"

**Step 3: Commit test file**

```bash
git add tests/Unit/Services/Graph/DocumentTypeDetectorTest.php
git commit -m "test: [Sprint 3 - C.1] Add DocumentTypeDetector test setup"
```

---

### Task 5: Create DocumentTypeDetector Service - Implementation

**Files:**
- Create: `app/Services/Graph/DocumentTypeDetector.php`

**Step 1: Create the DocumentTypeDetector class**

```php
<?php

namespace App\Services\Graph;

class DocumentTypeDetector
{
    /**
     * Document type constants
     */
    public const TYPE_COURT_DECISION = 'court_decision';
    public const TYPE_LAW = 'law';
    public const TYPE_GENERIC = 'generic';

    /**
     * Patterns for court decision detection
     */
    protected array $courtDecisionPatterns = [
        '/U\s+IME\s+REPUBLIKE\s+HRVATSKE/iu',
        '/PRESUDA/iu',
        '/RJEŠENJE/iu',
        '/REPUBLIKA\s+HRVATSKA.*(?:sud|VSRH|VTSRH)/ius',
        '/(?:Općinski|Županijski|Trgovački|Vrhovni|Ustavni)\s+sud/iu',
        '/Poslovni\s+broj[:\s]+/iu',
    ];

    /**
     * Patterns for law document detection
     */
    protected array $lawPatterns = [
        '/^ZAKON\s+O\s+/imu',
        '/NARODNE\s+NOVINE/iu',
        '/Članak\s+\d+\./iu',
        '/(?:PRAVILNIK|UREDBA|ODLUKA)\s+O\s+/iu',
        '/Na\s+temelju\s+članka\s+\d+/iu',
    ];

    /**
     * Extractor mapping by document type
     */
    protected array $extractorMapping = [
        self::TYPE_COURT_DECISION => [
            'LegalTopicExtractor',
            'ArticleExtractor',
            'LegalConceptExtractor',
            'LawyerExtractor',
            'VerdictExtractor',
            'LegalArgumentExtractor',
            'EvidenceExtractor',
            'DateEventExtractor',
            'LegalDefinitionExtractor',
        ],
        self::TYPE_LAW => [
            'ArticleExtractor',
            'LegalDefinitionExtractor',
            'LegalConceptExtractor',
            'LegalTopicExtractor',
        ],
        self::TYPE_GENERIC => [
            'DateEventExtractor',
            'LegalConceptExtractor',
            'LegalTopicExtractor',
        ],
    ];

    /**
     * Detect document type from content
     *
     * @param string $content Document text content
     * @return string Document type constant
     */
    public function detect(string $content): string
    {
        // Check for court decision first (more specific)
        foreach ($this->courtDecisionPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return self::TYPE_COURT_DECISION;
            }
        }

        // Check for law document
        foreach ($this->lawPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return self::TYPE_LAW;
            }
        }

        return self::TYPE_GENERIC;
    }

    /**
     * Get extractors for a document type
     *
     * @param string $type Document type
     * @return array List of extractor class names
     */
    public function getExtractorsForType(string $type): array
    {
        return $this->extractorMapping[$type] ?? $this->extractorMapping[self::TYPE_GENERIC];
    }

    /**
     * Get extractors for content (detect type first)
     *
     * @param string $content Document content
     * @return array List of extractor class names
     */
    public function getExtractorsForContent(string $content): array
    {
        $type = $this->detect($content);
        return $this->getExtractorsForType($type);
    }
}
```

**Step 2: Run tests to verify they pass**

```bash
./vendor/bin/phpunit tests/Unit/Services/Graph/DocumentTypeDetectorTest.php -v
```

Expected: All 7 tests PASS

**Step 3: Commit implementation**

```bash
git add app/Services/Graph/DocumentTypeDetector.php
git commit -m "feat: [Sprint 3 - C.1] Add DocumentTypeDetector service for selective extraction"
```

---

### Task 6: Add Batch Configuration to config/graph.php

**Files:**
- Modify: `config/graph.php`

**Step 1: Add batch configuration section**

Add after the `'logging'` section (around line 137):

```php

    /*
    |--------------------------------------------------------------------------
    | Batch Processing Configuration
    |--------------------------------------------------------------------------
    |
    | Configure batch processing for graph operations. Batching multiple
    | operations into single queries significantly improves performance.
    |
    */

    'batch' => [
        /*
         * Number of operations to accumulate before flushing to Neo4j.
         * Higher values improve throughput but use more memory.
         */
        'flush_threshold' => env('GRAPH_BATCH_FLUSH_THRESHOLD', 100),

        /*
         * Enable batch mode for graph operations.
         * When disabled, operations execute individually (legacy behavior).
         */
        'enabled' => env('GRAPH_BATCH_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Parallel Extraction Configuration
    |--------------------------------------------------------------------------
    |
    | Configure parallel extraction for document processing. Running extractors
    | in parallel can significantly reduce total processing time.
    |
    */

    'parallel' => [
        /*
         * Maximum number of concurrent extraction workers.
         * Set based on available CPU cores and memory.
         */
        'max_workers' => env('GRAPH_PARALLEL_MAX_WORKERS', 4),

        /*
         * Timeout in seconds for each extraction operation.
         */
        'timeout' => env('GRAPH_PARALLEL_TIMEOUT', 30),

        /*
         * Enable parallel extraction.
         * When disabled, extractors run sequentially (legacy behavior).
         */
        'enabled' => env('GRAPH_PARALLEL_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Selective Extraction Configuration
    |--------------------------------------------------------------------------
    |
    | Configure which extractors run for each document type. Selective extraction
    | improves performance by skipping irrelevant extractors.
    |
    */

    'selective_extraction' => [
        /*
         * Enable selective extraction based on document type.
         * When disabled, all extractors run on every document.
         */
        'enabled' => env('GRAPH_SELECTIVE_EXTRACTION_ENABLED', true),
    ],
```

**Step 2: Verify config loads correctly**

```bash
php artisan tinker --execute="var_dump(config('graph.batch.flush_threshold'));"
```

Expected: `int(100)`

**Step 3: Commit configuration**

```bash
git add config/graph.php
git commit -m "config: [Sprint 3 - A.4/B.4/C.2] Add batch, parallel, and selective extraction config"
```

---

## Wave 2: Core Refactoring

### Task 7: Create ParallelExtractionService - Test Setup

**Files:**
- Create: `tests/Unit/Services/Graph/ParallelExtractionServiceTest.php`

**Step 1: Create test file**

```php
<?php

namespace Tests\Unit\Services\Graph;

use App\Services\Graph\ParallelExtractionService;
use App\Services\Graph\Extractors\LegalTopicExtractor;
use App\Services\Graph\Extractors\ArticleExtractor;
use Tests\TestCase;
use Mockery;

class ParallelExtractionServiceTest extends TestCase
{
    /** @test */
    public function it_runs_extractors_and_returns_results(): void
    {
        $service = new ParallelExtractionService();

        $content = 'Test content for extraction';
        $extractorNames = ['LegalTopicExtractor', 'ArticleExtractor'];

        $results = $service->extract($content, $extractorNames);

        $this->assertIsArray($results);
        $this->assertArrayHasKey('LegalTopicExtractor', $results);
        $this->assertArrayHasKey('ArticleExtractor', $results);
    }

    /** @test */
    public function it_handles_extractor_errors_gracefully(): void
    {
        $service = new ParallelExtractionService();

        $content = 'Test content';
        $extractorNames = ['NonExistentExtractor'];

        $results = $service->extract($content, $extractorNames);

        $this->assertArrayHasKey('NonExistentExtractor', $results);
        $this->assertArrayHasKey('error', $results['NonExistentExtractor']);
    }

    /** @test */
    public function it_respects_parallel_config_when_disabled(): void
    {
        config(['graph.parallel.enabled' => false]);

        $service = new ParallelExtractionService();

        $this->assertFalse($service->isParallelEnabled());
    }

    /** @test */
    public function it_respects_max_workers_config(): void
    {
        config(['graph.parallel.max_workers' => 8]);

        $service = new ParallelExtractionService();

        $this->assertEquals(8, $service->getMaxWorkers());
    }
}
```

**Step 2: Run test to verify it fails**

```bash
./vendor/bin/phpunit tests/Unit/Services/Graph/ParallelExtractionServiceTest.php -v
```

Expected: FAIL with "Class 'App\Services\Graph\ParallelExtractionService' not found"

**Step 3: Commit test file**

```bash
git add tests/Unit/Services/Graph/ParallelExtractionServiceTest.php
git commit -m "test: [Sprint 3 - B.1] Add ParallelExtractionService test setup"
```

---

### Task 8: Create ParallelExtractionService - Implementation

**Files:**
- Create: `app/Services/Graph/ParallelExtractionService.php`

**Step 1: Create the ParallelExtractionService class**

```php
<?php

namespace App\Services\Graph;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Concurrency;
use Throwable;

class ParallelExtractionService
{
    protected int $maxWorkers;
    protected int $timeout;
    protected bool $parallelEnabled;

    /**
     * Extractor namespace
     */
    protected string $extractorNamespace = 'App\\Services\\Graph\\Extractors\\';

    public function __construct()
    {
        $this->maxWorkers = (int) config('graph.parallel.max_workers', 4);
        $this->timeout = (int) config('graph.parallel.timeout', 30);
        $this->parallelEnabled = (bool) config('graph.parallel.enabled', true);
    }

    /**
     * Run multiple extractors on content
     *
     * @param string $content Document content
     * @param array $extractorNames List of extractor class names (without namespace)
     * @return array Results keyed by extractor name
     */
    public function extract(string $content, array $extractorNames): array
    {
        if (!$this->parallelEnabled || count($extractorNames) <= 1) {
            return $this->extractSequentially($content, $extractorNames);
        }

        return $this->extractInParallel($content, $extractorNames);
    }

    /**
     * Extract sequentially (fallback mode)
     */
    protected function extractSequentially(string $content, array $extractorNames): array
    {
        $results = [];

        foreach ($extractorNames as $name) {
            $results[$name] = $this->runExtractor($name, $content);
        }

        return $results;
    }

    /**
     * Extract in parallel using Laravel Concurrency
     */
    protected function extractInParallel(string $content, array $extractorNames): array
    {
        $tasks = [];

        foreach ($extractorNames as $name) {
            $tasks[$name] = fn() => $this->runExtractor($name, $content);
        }

        try {
            // Use Laravel's Concurrency facade with fork driver
            $results = Concurrency::driver('fork')
                ->timeout($this->timeout)
                ->run($tasks);

            return $results;
        } catch (Throwable $e) {
            Log::warning('Parallel extraction failed, falling back to sequential', [
                'error' => $e->getMessage(),
            ]);

            return $this->extractSequentially($content, $extractorNames);
        }
    }

    /**
     * Run a single extractor
     */
    protected function runExtractor(string $name, string $content): array
    {
        try {
            $className = $this->extractorNamespace . $name;

            if (!class_exists($className)) {
                return ['error' => "Extractor class {$className} not found"];
            }

            $extractor = app($className);

            if (!method_exists($extractor, 'extract')) {
                return ['error' => "Extractor {$name} does not have extract method"];
            }

            return $extractor->extract($content);
        } catch (Throwable $e) {
            Log::error("Extractor {$name} failed", [
                'error' => $e->getMessage(),
            ]);

            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Check if parallel extraction is enabled
     */
    public function isParallelEnabled(): bool
    {
        return $this->parallelEnabled;
    }

    /**
     * Get max workers setting
     */
    public function getMaxWorkers(): int
    {
        return $this->maxWorkers;
    }

    /**
     * Get timeout setting
     */
    public function getTimeout(): int
    {
        return $this->timeout;
    }
}
```

**Step 2: Run tests to verify they pass**

```bash
./vendor/bin/phpunit tests/Unit/Services/Graph/ParallelExtractionServiceTest.php -v
```

Expected: All 4 tests PASS

**Step 3: Commit implementation**

```bash
git add app/Services/Graph/ParallelExtractionService.php
git commit -m "feat: [Sprint 3 - B.1] Add ParallelExtractionService for concurrent extraction"
```

---

### Task 9: Add Batch Methods to GraphDatabaseService - Tests

**Files:**
- Create: `tests/Unit/Services/GraphDatabaseServiceBatchTest.php`

**Step 1: Create batch test file**

```php
<?php

namespace Tests\Unit\Services;

use App\Services\GraphDatabaseService;
use App\Services\Graph\BatchCypherBuilder;
use Tests\TestCase;
use Mockery;

class GraphDatabaseServiceBatchTest extends TestCase
{
    /** @test */
    public function it_has_batch_upsert_nodes_method(): void
    {
        $service = app(GraphDatabaseService::class);

        $this->assertTrue(method_exists($service, 'batchUpsertNodes'));
    }

    /** @test */
    public function it_has_batch_upsert_relationships_method(): void
    {
        $service = app(GraphDatabaseService::class);

        $this->assertTrue(method_exists($service, 'batchUpsertRelationships'));
    }

    /** @test */
    public function it_has_flush_batch_method(): void
    {
        $service = app(GraphDatabaseService::class);

        $this->assertTrue(method_exists($service, 'flushBatch'));
    }

    /** @test */
    public function batch_upsert_nodes_accumulates_until_threshold(): void
    {
        // This test verifies accumulation behavior
        // When Neo4j is unavailable, it should still accumulate
        $service = app(GraphDatabaseService::class);

        // Queue some nodes (won't execute if Neo4j unavailable)
        $result = $service->batchUpsertNodes('TestLabel', [
            ['id' => 'test_1', 'name' => 'Test 1'],
        ]);

        // Should return count of queued items
        $this->assertIsInt($result);
    }
}
```

**Step 2: Run test to verify it fails**

```bash
./vendor/bin/phpunit tests/Unit/Services/GraphDatabaseServiceBatchTest.php -v
```

Expected: FAIL (methods don't exist yet)

**Step 3: Commit test file**

```bash
git add tests/Unit/Services/GraphDatabaseServiceBatchTest.php
git commit -m "test: [Sprint 3 - A.2/A.3] Add batch method tests for GraphDatabaseService"
```

---

### Task 10: Add Batch Methods to GraphDatabaseService - Implementation

**Files:**
- Modify: `app/Services/GraphDatabaseService.php`

**Step 1: Add batch properties and methods**

Add these properties after line 47 (after `$queryCache`):

```php
    /**
     * Batch Cypher builder (Sprint 3)
     */
    protected ?BatchCypherBuilder $batchBuilder = null;

    /**
     * Pending batch operations
     */
    protected array $pendingNodes = [];
    protected array $pendingRelationships = [];
```

Add this use statement at the top with other use statements:

```php
use App\Services\Graph\BatchCypherBuilder;
```

Add these methods before the closing brace of the class:

```php
    /**
     * Queue nodes for batch upsert
     *
     * @param string $label Node label
     * @param array $nodes Array of node data
     * @param string $idKey Property to use as unique identifier
     * @return int Number of nodes queued
     */
    public function batchUpsertNodes(string $label, array $nodes, string $idKey = 'id'): int
    {
        if (empty($nodes)) {
            return 0;
        }

        $this->initBatchBuilder();

        $key = "{$label}:{$idKey}";
        if (!isset($this->pendingNodes[$key])) {
            $this->pendingNodes[$key] = [
                'label' => $label,
                'idKey' => $idKey,
                'nodes' => [],
            ];
        }

        $this->pendingNodes[$key]['nodes'] = array_merge(
            $this->pendingNodes[$key]['nodes'],
            $nodes
        );

        // Auto-flush if threshold reached
        if (count($this->pendingNodes[$key]['nodes']) >= $this->batchBuilder->getFlushThreshold()) {
            $this->flushNodes($key);
        }

        return count($nodes);
    }

    /**
     * Queue relationships for batch upsert
     *
     * @param string $fromLabel Source node label
     * @param string $toLabel Target node label
     * @param string $relType Relationship type
     * @param array $relationships Array of relationship data
     * @return int Number of relationships queued
     */
    public function batchUpsertRelationships(
        string $fromLabel,
        string $toLabel,
        string $relType,
        array $relationships,
        string $fromIdKey = 'id',
        string $toIdKey = 'id'
    ): int {
        if (empty($relationships)) {
            return 0;
        }

        $this->initBatchBuilder();

        $key = "{$fromLabel}:{$toLabel}:{$relType}";
        if (!isset($this->pendingRelationships[$key])) {
            $this->pendingRelationships[$key] = [
                'fromLabel' => $fromLabel,
                'toLabel' => $toLabel,
                'relType' => $relType,
                'fromIdKey' => $fromIdKey,
                'toIdKey' => $toIdKey,
                'relationships' => [],
            ];
        }

        $this->pendingRelationships[$key]['relationships'] = array_merge(
            $this->pendingRelationships[$key]['relationships'],
            $relationships
        );

        // Auto-flush if threshold reached
        if (count($this->pendingRelationships[$key]['relationships']) >= $this->batchBuilder->getFlushThreshold()) {
            $this->flushRelationships($key);
        }

        return count($relationships);
    }

    /**
     * Flush all pending batch operations
     *
     * @return array{nodes: int, relationships: int} Counts of flushed items
     */
    public function flushBatch(): array
    {
        $nodeCount = 0;
        $relCount = 0;

        foreach (array_keys($this->pendingNodes) as $key) {
            $nodeCount += $this->flushNodes($key);
        }

        foreach (array_keys($this->pendingRelationships) as $key) {
            $relCount += $this->flushRelationships($key);
        }

        return ['nodes' => $nodeCount, 'relationships' => $relCount];
    }

    /**
     * Flush pending nodes for a specific key
     */
    protected function flushNodes(string $key): int
    {
        if (!isset($this->pendingNodes[$key]) || empty($this->pendingNodes[$key]['nodes'])) {
            return 0;
        }

        $batch = $this->pendingNodes[$key];
        $count = count($batch['nodes']);

        if (!$this->isAvailable()) {
            Log::warning('Cannot flush nodes - Neo4j unavailable', ['key' => $key, 'count' => $count]);
            return 0;
        }

        try {
            $query = $this->batchBuilder->buildBatchUpsertNodes(
                $batch['label'],
                $batch['nodes'],
                $batch['idKey']
            );

            $this->run($query['query'], $query['parameters']);

            // Clear the queue
            $this->pendingNodes[$key]['nodes'] = [];

            Log::debug('Flushed batch nodes', ['key' => $key, 'count' => $count]);

            return $count;
        } catch (\Exception $e) {
            Log::error('Failed to flush batch nodes', [
                'key' => $key,
                'count' => $count,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Flush pending relationships for a specific key
     */
    protected function flushRelationships(string $key): int
    {
        if (!isset($this->pendingRelationships[$key]) || empty($this->pendingRelationships[$key]['relationships'])) {
            return 0;
        }

        $batch = $this->pendingRelationships[$key];
        $count = count($batch['relationships']);

        if (!$this->isAvailable()) {
            Log::warning('Cannot flush relationships - Neo4j unavailable', ['key' => $key, 'count' => $count]);
            return 0;
        }

        try {
            $query = $this->batchBuilder->buildBatchUpsertRelationships(
                $batch['fromLabel'],
                $batch['toLabel'],
                $batch['relType'],
                $batch['relationships'],
                $batch['fromIdKey'],
                $batch['toIdKey']
            );

            $this->run($query['query'], $query['parameters']);

            // Clear the queue
            $this->pendingRelationships[$key]['relationships'] = [];

            Log::debug('Flushed batch relationships', ['key' => $key, 'count' => $count]);

            return $count;
        } catch (\Exception $e) {
            Log::error('Failed to flush batch relationships', [
                'key' => $key,
                'count' => $count,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Initialize batch builder if not already done
     */
    protected function initBatchBuilder(): void
    {
        if ($this->batchBuilder === null) {
            $this->batchBuilder = new BatchCypherBuilder();
        }
    }
```

**Step 2: Run tests to verify they pass**

```bash
./vendor/bin/phpunit tests/Unit/Services/GraphDatabaseServiceBatchTest.php -v
```

Expected: All 4 tests PASS

**Step 3: Commit implementation**

```bash
git add app/Services/GraphDatabaseService.php
git commit -m "feat: [Sprint 3 - A.2/A.3] Add batch upsert methods to GraphDatabaseService"
```

---

## Wave 3: Integration

### Task 11: Update EnhancedGraphSyncJob for Parallel Extraction

**Files:**
- Modify: `app/Jobs/EnhancedGraphSyncJob.php`

**Step 1: Add parallel extraction integration**

Add use statements at top:

```php
use App\Services\Graph\ParallelExtractionService;
use App\Services\Graph\DocumentTypeDetector;
```

Modify the `handle` method to use parallel extraction. After line 30 (`public function handle(DecisionGraphSyncService $syncService): void`), inject the new services:

```php
    public function handle(
        DecisionGraphSyncService $syncService,
        ParallelExtractionService $parallelExtractor = null,
        DocumentTypeDetector $typeDetector = null
    ): void {
        $parallelExtractor = $parallelExtractor ?? app(ParallelExtractionService::class);
        $typeDetector = $typeDetector ?? app(DocumentTypeDetector::class);
```

Add a method to the class for selective extraction:

```php
    /**
     * Get extractors for a decision based on document type
     */
    protected function getExtractorsForDecision(
        CourtDecision $decision,
        DocumentTypeDetector $typeDetector
    ): array {
        if (!config('graph.selective_extraction.enabled', true)) {
            // Return all extractors if selective extraction disabled
            return $typeDetector->getExtractorsForType('court_decision');
        }

        $content = $decision->content ?? $decision->extracted_text ?? '';
        return $typeDetector->getExtractorsForContent($content);
    }
```

**Step 2: Run existing tests to ensure no regression**

```bash
./vendor/bin/phpunit tests/Unit/Jobs/EnhancedGraphSyncJobTest.php -v
```

Expected: All existing tests PASS

**Step 3: Commit integration**

```bash
git add app/Jobs/EnhancedGraphSyncJob.php
git commit -m "feat: [Sprint 3 - B.3/C.3] Integrate parallel and selective extraction into EnhancedGraphSyncJob"
```

---

## Wave 4: Testing & Validation

### Task 12: Create Batch Operations Integration Test

**Files:**
- Create: `tests/Integration/Graph/BatchOperationsTest.php`

**Step 1: Create integration test file**

```php
<?php

namespace Tests\Integration\Graph;

use App\Services\GraphDatabaseService;
use App\Services\Graph\BatchCypherBuilder;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BatchOperationsTest extends TestCase
{
    use RefreshDatabase;

    private GraphDatabaseService $graphService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->graphService = app(GraphDatabaseService::class);

        if (!$this->graphService->isAvailable()) {
            $this->markTestSkipped('Neo4j is not available for integration tests');
        }
    }

    /** @test */
    public function it_batch_upserts_multiple_nodes(): void
    {
        $nodes = [];
        for ($i = 1; $i <= 50; $i++) {
            $nodes[] = [
                'id' => "batch_test_node_{$i}",
                'name' => "Test Node {$i}",
                'created_at' => now()->toIso8601String(),
            ];
        }

        $this->graphService->batchUpsertNodes('BatchTestNode', $nodes);
        $flushed = $this->graphService->flushBatch();

        $this->assertEquals(50, $flushed['nodes']);

        // Verify nodes exist
        $result = $this->graphService->run(
            'MATCH (n:BatchTestNode) RETURN count(n) as count'
        );

        $this->assertEquals(50, $result->first()->get('count'));

        // Cleanup
        $this->graphService->run('MATCH (n:BatchTestNode) DELETE n');
    }

    /** @test */
    public function it_batch_upserts_multiple_relationships(): void
    {
        // Create source and target nodes first
        $this->graphService->run(
            'UNWIND range(1, 10) AS i CREATE (:BatchSourceNode {id: "source_" + i})'
        );
        $this->graphService->run(
            'UNWIND range(1, 10) AS i CREATE (:BatchTargetNode {id: "target_" + i})'
        );

        $relationships = [];
        for ($i = 1; $i <= 10; $i++) {
            $relationships[] = [
                'fromId' => "source_{$i}",
                'toId' => "target_{$i}",
                'weight' => 0.5,
            ];
        }

        $this->graphService->batchUpsertRelationships(
            'BatchSourceNode',
            'BatchTargetNode',
            'BATCH_TEST_REL',
            $relationships
        );
        $flushed = $this->graphService->flushBatch();

        $this->assertEquals(10, $flushed['relationships']);

        // Verify relationships exist
        $result = $this->graphService->run(
            'MATCH ()-[r:BATCH_TEST_REL]->() RETURN count(r) as count'
        );

        $this->assertEquals(10, $result->first()->get('count'));

        // Cleanup
        $this->graphService->run('MATCH (n:BatchSourceNode) DETACH DELETE n');
        $this->graphService->run('MATCH (n:BatchTargetNode) DETACH DELETE n');
    }

    /** @test */
    public function it_auto_flushes_at_threshold(): void
    {
        config(['graph.batch.flush_threshold' => 10]);

        // Recreate service with new config
        $service = new GraphDatabaseService();

        if (!$service->isAvailable()) {
            $this->markTestSkipped('Neo4j is not available');
        }

        $nodes = [];
        for ($i = 1; $i <= 15; $i++) {
            $nodes[] = [
                'id' => "auto_flush_node_{$i}",
                'name' => "Auto Flush Node {$i}",
            ];
        }

        // This should auto-flush after 10 nodes
        $service->batchUpsertNodes('AutoFlushTestNode', $nodes);

        // Verify 10 nodes were flushed (auto-flush at threshold)
        $result = $service->run(
            'MATCH (n:AutoFlushTestNode) RETURN count(n) as count'
        );

        // Should have 10 nodes from auto-flush, 5 still pending
        $this->assertEquals(10, $result->first()->get('count'));

        // Flush remaining
        $service->flushBatch();

        $result = $service->run(
            'MATCH (n:AutoFlushTestNode) RETURN count(n) as count'
        );

        $this->assertEquals(15, $result->first()->get('count'));

        // Cleanup
        $service->run('MATCH (n:AutoFlushTestNode) DELETE n');
    }
}
```

**Step 2: Run integration tests**

```bash
./vendor/bin/phpunit tests/Integration/Graph/BatchOperationsTest.php -v
```

Expected: All 3 tests PASS (or SKIP if Neo4j unavailable)

**Step 3: Commit integration tests**

```bash
git add tests/Integration/Graph/BatchOperationsTest.php
git commit -m "test: [Sprint 3 - A.6] Add batch operations integration tests"
```

---

### Task 13: Run Performance Benchmarks

**Files:**
- Modify: `tests/Performance/Graph/SyncBenchmarkTest.php`

**Step 1: Update benchmark test to compare old vs new performance**

Add a new test method:

```php
    /** @test */
    public function sync_10_nodes_with_batch_mode_faster_than_baseline(): void
    {
        $this->markTestSkippedIfNoNeo4j();

        // Enable batch mode
        config(['graph.batch.enabled' => true]);
        config(['graph.parallel.enabled' => true]);
        config(['graph.selective_extraction.enabled' => true]);

        $decisions = $this->createTestDecisions(10);

        $start = microtime(true);

        foreach ($decisions as $decision) {
            app(DecisionGraphSyncService::class)->sync($decision);
        }

        $elapsed = microtime(true) - $start;

        // Target: 10x improvement = <6s (was ~60s baseline)
        $this->assertLessThan(6.0, $elapsed,
            "Syncing 10 nodes with batch mode took {$elapsed}s, expected <6s (10x improvement)");

        fwrite(STDOUT, "\nBatch mode: 10 nodes in {$elapsed}s\n");
    }
```

**Step 2: Run benchmark**

```bash
./vendor/bin/phpunit tests/Performance/Graph/SyncBenchmarkTest.php --filter=sync_10_nodes -v
```

Expected: Test passes with time <6s

**Step 3: Commit benchmark update**

```bash
git add tests/Performance/Graph/SyncBenchmarkTest.php
git commit -m "test: [Sprint 3 - Validation] Add performance benchmark for batch mode"
```

---

### Task 14: Final Verification and Commit

**Step 1: Run full test suite**

```bash
./vendor/bin/phpunit --testsuite=Unit,Feature -v
```

Expected: All tests PASS

**Step 2: Run integration tests**

```bash
./vendor/bin/phpunit tests/Integration/Graph/ -v
```

Expected: All tests PASS (or SKIP if Neo4j unavailable)

**Step 3: Create final commit with summary**

```bash
git add -A
git commit -m "feat: [Sprint 3] Performance Tuning Complete

Summary:
- BatchCypherBuilder: UNWIND queries for bulk operations
- ParallelExtractionService: Concurrent extraction with Laravel Concurrency
- DocumentTypeDetector: Selective extraction by document type
- GraphDatabaseService batch methods: batchUpsertNodes, batchUpsertRelationships, flushBatch
- EnhancedGraphSyncJob: Integrated parallel and selective extraction
- Config: graph.batch, graph.parallel, graph.selective_extraction

Performance Target: 10x improvement (60s → 6s for 10 nodes)
Tests: 20+ new tests covering batch, parallel, and selective extraction"
```

**Step 4: Push to remote**

```bash
git push -u origin claude/graph-enhancement-data-integrity-XqqqL
```

---

## Verification Checklist

- [ ] BatchCypherBuilder unit tests pass
- [ ] DocumentTypeDetector unit tests pass
- [ ] ParallelExtractionService unit tests pass
- [ ] GraphDatabaseService batch method tests pass
- [ ] Batch operations integration tests pass
- [ ] Performance benchmark shows <6s for 10 nodes
- [ ] All existing tests still pass (no regression)
- [ ] Config values load correctly
- [ ] Code committed and pushed

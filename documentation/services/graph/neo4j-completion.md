# Neo4j Graph Database - Completion Analysis & Action Plan

**Current Status**: 85% Complete
**Target**: 100% Complete
**Estimated Work**: 12 hours (5 hours HIGH priority → 95%, 7 hours MEDIUM/LOW → 100%)

---

## Executive Summary

The Neo4j graph database integration is **85% complete** (better than initially estimated 70%). Core infrastructure is 100% implemented with full support for:
- ✅ 8 node types (Law, Case, CourtDecision, Textract, Court, Jurisdiction, Keyword, Tag)
- ✅ 5 relationship types (CITES, REFERENCES, BELONGS_TO_JURISDICTION, DECIDED_BY, HAS_DOCUMENT)
- ✅ Citation extraction with article-level precision
- ✅ Comprehensive sync methods in GraphRagService

**Main Gaps**:
1. ❌ Court decision sync not exposed in CLI command
2. ❌ No queue job for background sync
3. ⚠️ No auto-sync on document ingestion
4. ⚠️ Limited advanced graph algorithms

---

## What's Implemented ✅ (85%)

### Core Infrastructure (100%)

**File**: `app/Services/GraphDatabaseService.php` (399 lines)
- Client initialization with Neo4j Bolt protocol
- Query execution with error handling
- Transaction support
- Schema initialization (constraints + indexes)
- 14 public methods for CRUD operations

**Methods**:
```php
- run(string $query, array $parameters): mixed
- transaction(callable $callback): mixed
- initializeSchema(): void
- upsertNode(string $label, string $id, array $properties): void
- createRelationship(...)
- deleteNode(...)
- findSimilar(...)
- getRelated(...)
- findPath(...)
- batchUpsertNodes(...)
- clearAll()
```

### Sync Service (100%)

**File**: `app/Services/GraphRagService.php` (1,851 lines)

**Sync Methods**:
```php
- syncLaw(string $lawId): void ✅
- syncCase(string $caseDocId): void ✅
- syncCourtDecision(string $decisionId): void ✅
- syncTextractJob(int $textractJobId): void ✅
- syncAllLaws(): array ✅
- syncAllCases(): array ✅
- syncAllCourtDecisions(): array ✅
```

**Citation Extraction**:
- Statute citations (with article numbers)
- Narodne Novine (NN) citations
- ECLI citations (court decision cross-references)
- Case number citations

**Features**:
- Croatian legal keyword extraction
- Similarity relationships based on embeddings
- Auto-tagging with legal metadata

### Node Types (8 types - 100%)

| Node Type | Unique Constraint | Indexes | Status |
|-----------|------------------|---------|--------|
| LawDocument | id | title, law_number, effective_date | ✅ |
| CaseDocument | id | title | ✅ |
| CourtDecisionDocument | id | ecli, case_number, court, date, type | ✅ |
| TextractDocument | *(not unique)* | *(none)* | ✅ |
| Court | name | *(none)* | ✅ |
| Jurisdiction | name | *(none)* | ✅ |
| Keyword | name | category | ✅ |
| Tag | name | category | ✅ |

### Relationship Types (5 types - 100%)

| Relationship | From | To | Properties | Status |
|--------------|------|-----|-----------|--------|
| CITES | Decision/Law | Law | article, paragraph, item, alineja | ✅ |
| REFERENCES | Decision/Case | Decision/Case | *(none)* | ✅ |
| BELONGS_TO_JURISDICTION | Any Document | Jurisdiction | *(none)* | ✅ |
| DECIDED_BY | CourtDecision | Court | *(none)* | ✅ |
| HAS_DOCUMENT | Case | CaseDocument | *(none)* | ✅ |

### Commands (5 commands - 80%)

| Command | Purpose | Status |
|---------|---------|--------|
| `graph:init` | Initialize schema | ✅ Working |
| `graph:sync --laws` | Sync laws | ✅ Working |
| `graph:sync --cases` | Sync cases | ✅ Working |
| `graph:sync --decisions` | Sync decisions | ❌ **MISSING** |
| `graph:sync --all` | Sync everything | ⚠️ Partial (no decisions) |
| `graph:query` | Execute Cypher query | ✅ Working |
| `graph:stats` | Show graph statistics | ✅ Working |

### Queue Jobs (20%)

| Job | Purpose | Status |
|-----|---------|--------|
| `SyncTextractToGraph` | Sync textract job to graph | ✅ Implemented |
| `SyncGraphDataJob` | General graph sync job | ❌ **MISSING** |

---

## What's Missing ❌ (15%)

### Gap 1: Court Decision Sync Not in Command ❌ HIGH PRIORITY

**File**: `app/Console/Commands/GraphSyncCommand.php`
**Issue**: Method exists but not exposed in CLI

**Current Signature** (lines 11-15):
```php
protected $signature = 'graph:sync
                        {--all : Sync all data}
                        {--laws : Sync laws only}
                        {--cases : Sync cases only}
                        {--limit= : Limit number of records to sync}';
```

**Missing Options**:
- `--decisions` : Sync court decisions only
- `--textract` : Sync textract documents only

**Handler Logic Missing** (after line 53):
No call to `$this->graphRag->syncAllCourtDecisions()`

---

### Gap 2: No Queue Job for Graph Sync ❌ MEDIUM PRIORITY

**Missing File**: `app/Jobs/SyncGraphDataJob.php`

**Problem**:
- All graph syncs run synchronously
- Large datasets (10,000+ documents) can take 30+ minutes
- HTTP timeouts when called via API
- No progress tracking
- Blocks CLI/cron during execution

**Requirements**:
1. Support all sync types (laws, cases, decisions, textract)
2. Environment-based execution:
   - **localhost/dev**: Run synchronously (immediate feedback)
   - **production**: Dispatch to queue (background execution)
3. Progress tracking with batch updates
4. Retry logic with exponential backoff

---

### Gap 3: No Auto-Sync on Ingestion ⚠️ PARTIAL

**Current State**:
- **Laws**: ❌ Not auto-synced after `import:croatian-laws`
- **Cases**: ❌ Not auto-synced after upload via API
- **Court Decisions**: ✅ PARTIAL (`odluke ingest` has `--sync-graph` option)
- **Textract**: ✅ Auto-synced via `SyncTextractToGraph` job

**Missing**:
- Event listeners or Eloquent observers
- Automatic dispatch of sync jobs after ingestion

---

### Gap 4: Limited Advanced Queries ⚠️ MEDIUM PRIORITY

**File**: `app/Services/GraphQueryHelper.php` (299 lines)

**Current Methods** (basic):
```php
- query(string $cypher, array $params): array
- findNodeById(string $label, string $id): ?array
- findNodesByLabel(string $label, array $filters): array
```

**Missing Methods** (advanced):
```php
- findInfluentialDecisions(): array // PageRank algorithm
- findRelatedByTopology(string $nodeId, int $maxHops): array
- detectCitationClusters(): array // Community detection
- analyzeCitationNetwork(string $decisionId): array // Network metrics
- findShortestPath(string $fromId, string $toId): array
```

---

### Gap 5: No Real-Time Relationship Updates ⚠️ MEDIUM PRIORITY

**Problem**: Citation relationships are static

**Example**:
1. Court decision D1 exists, cites "ZKP Članak 93"
2. ZKP law is ingested into graph
3. **D1 → ZKP relationship is NOT created** (D1 was synced before ZKP existed)

**Solution**: Update relationships when new documents added

---

### Gap 6: No Graph Visualization API ⚠️ LOW PRIORITY

**File Exists**: `app/Http/Livewire/GraphViewer.php`
**Status**: Livewire component exists but no REST API endpoint

**Missing**:
- `GET /api/graph/visualize/{nodeId}` - Get node with neighbors
- `GET /api/graph/subgraph` - Get subgraph by filters

---

### Gap 7: GraphQL Integration Unused ❌ LOW PRIORITY

**Files Exist**:
- `app/GraphQL/AutoDiscovery/GraphQLAutoClient.php`
- `app/GraphQL/AutoDiscovery/Introspection/GraphQLIntrospectionService.php`
- `app/Providers/GraphQLDiscoveryServiceProvider.php`

**Status**: Files created but never used for graph queries

**Decision**: Remove or integrate?

---

## Completion Breakdown by Component

| Component | Completion | Details |
|-----------|-----------|---------|
| Core Infrastructure | 100% | ✅ GraphDatabaseService fully implemented |
| Node Types | 100% | ✅ All 8 types with constraints/indexes |
| Relationship Types | 100% | ✅ All 5 types working |
| Law Sync | 100% | ✅ Full sync with citations |
| Case Sync | 100% | ✅ Full sync with citations |
| Court Decision Sync | 90% | ⚠️ Method exists, not in command |
| Textract Sync | 100% | ✅ Complete with queue job |
| Commands | 80% | ⚠️ Missing --decisions, --textract |
| Queue Jobs | 20% | ⚠️ Only 1 of 2 implemented |
| Auto-Sync on Ingest | 25% | ⚠️ Only textract auto-syncs |
| Advanced Queries | 40% | ⚠️ Basic queries only |
| GraphQL Integration | 0% | ❌ Files exist but unused |
| Visualization API | 10% | ⚠️ Livewire exists, no API |

**Overall**: **85%** Complete

---

## Actionable Tasks for Coding Agents

### ⚡ HIGH PRIORITY TASKS (5 hours → 95%)

#### Task 1.1: Add Court Decision Sync to Command (1 hour)

**File to Edit**: `app/Console/Commands/GraphSyncCommand.php`

**Changes Required**:

1. **Update signature** (lines 11-15):
```php
// BEFORE:
protected $signature = 'graph:sync
                        {--all : Sync all data}
                        {--laws : Sync laws only}
                        {--cases : Sync cases only}
                        {--limit= : Limit number of records to sync}';

// AFTER:
protected $signature = 'graph:sync
                        {--all : Sync all data}
                        {--laws : Sync laws only}
                        {--cases : Sync cases only}
                        {--decisions : Sync court decisions only}
                        {--textract : Sync textract documents only}
                        {--limit= : Limit number of records to sync}';
```

2. **Update validation** (lines 30-38):
```php
// BEFORE:
$syncAll = $this->option('all');
$syncLaws = $this->option('laws');
$syncCases = $this->option('cases');

if (!$syncAll && !$syncLaws && !$syncCases) {
    $this->error('Please specify what to sync: --all, --laws, or --cases');
    return self::FAILURE;
}

// AFTER:
$syncAll = $this->option('all');
$syncLaws = $this->option('laws');
$syncCases = $this->option('cases');
$syncDecisions = $this->option('decisions');
$syncTextract = $this->option('textract');

if (!$syncAll && !$syncLaws && !$syncCases && !$syncDecisions && !$syncTextract) {
    $this->error('Please specify what to sync: --all, --laws, --cases, --decisions, or --textract');
    return self::FAILURE;
}
```

3. **Add decision sync handler** (after line 53, before line 55):
```php
// ADD THIS BLOCK:
if ($syncAll || $syncDecisions) {
    $this->info('Syncing court decisions to graph database...');
    $this->withProgressBar(1, function () {
        return $this->graphRag->syncAllCourtDecisions();
    });
}

if ($syncAll || $syncTextract) {
    $this->info('Syncing textract documents to graph database...');
    $this->withProgressBar(1, function () {
        return $this->graphRag->syncAllTextractJobs();
    });
}
```

4. **Add missing method to GraphRagService** (if doesn't exist):

Check if `app/Services/GraphRagService.php` has `syncAllTextractJobs()` method. If not, add:

```php
/**
 * Sync all textract jobs to graph database
 */
public function syncAllTextractJobs(): array
{
    $batchSize = config('neo4j.sync.batch_size', 100);
    $synced = 0;
    $errors = 0;

    DB::table('textract_jobs')
        ->where('status', 'completed')
        ->whereNull('graph_sync_status')
        ->orWhere('graph_sync_status', 'pending')
        ->chunkById($batchSize, function ($jobs) use (&$synced, &$errors) {
            foreach ($jobs as $job) {
                try {
                    $this->syncTextractJob($job->id);
                    $synced++;
                } catch (\Exception $e) {
                    $errors++;
                    Log::error('Failed to sync textract job to graph', [
                        'job_id' => $job->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });

    return compact('synced', 'errors');
}
```

**Testing**:
```bash
php artisan graph:sync --decisions
php artisan graph:sync --textract
php artisan graph:sync --all
```

---

#### Task 1.2: Create SyncGraphDataJob with Environment Detection (2 hours)

**New File**: `app/Jobs/SyncGraphDataJob.php`

**Implementation**:

```php
<?php

namespace App\Jobs;

use App\Services\GraphRagService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncGraphDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600; // 1 hour max
    public int $tries = 2; // Retry once on failure

    /**
     * Create a new job instance.
     *
     * @param string $syncType One of: 'law', 'case', 'decision', 'textract', 'all'
     * @param string|int|null $id Specific ID to sync (null for all)
     */
    public function __construct(
        public string $syncType,
        public string|int|null $id = null
    ) {}

    /**
     * Execute the job.
     */
    public function handle(GraphRagService $graphRag): void
    {
        Log::info('SyncGraphDataJob started', [
            'sync_type' => $this->syncType,
            'id' => $this->id,
            'job_id' => $this->job?->getJobId(),
        ]);

        try {
            $result = match ($this->syncType) {
                'law' => $this->id
                    ? $this->syncSingle($graphRag, 'syncLaw', $this->id)
                    : $graphRag->syncAllLaws(),
                'case' => $this->id
                    ? $this->syncSingle($graphRag, 'syncCase', $this->id)
                    : $graphRag->syncAllCases(),
                'decision' => $this->id
                    ? $this->syncSingle($graphRag, 'syncCourtDecision', $this->id)
                    : $graphRag->syncAllCourtDecisions(),
                'textract' => $this->id
                    ? $this->syncSingle($graphRag, 'syncTextractJob', (int)$this->id)
                    : $graphRag->syncAllTextractJobs(),
                'all' => $this->syncAll($graphRag),
                default => throw new \InvalidArgumentException("Invalid sync type: {$this->syncType}"),
            };

            Log::info('SyncGraphDataJob completed', [
                'sync_type' => $this->syncType,
                'result' => $result,
            ]);

        } catch (\Exception $e) {
            Log::error('SyncGraphDataJob failed', [
                'sync_type' => $this->syncType,
                'id' => $this->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Sync a single entity
     */
    protected function syncSingle(GraphRagService $graphRag, string $method, string|int $id): array
    {
        $graphRag->$method($id);
        return ['synced' => 1, 'errors' => 0];
    }

    /**
     * Sync all entity types
     */
    protected function syncAll(GraphRagService $graphRag): array
    {
        $results = [];

        $results['laws'] = $graphRag->syncAllLaws();
        $results['cases'] = $graphRag->syncAllCases();
        $results['decisions'] = $graphRag->syncAllCourtDecisions();
        $results['textract'] = $graphRag->syncAllTextractJobs();

        return [
            'synced' => array_sum(array_column($results, 'synced')),
            'errors' => array_sum(array_column($results, 'errors')),
            'details' => $results,
        ];
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SyncGraphDataJob failed permanently', [
            'sync_type' => $this->syncType,
            'id' => $this->id,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * Get the tags for the job.
     */
    public function tags(): array
    {
        return [
            'graph:sync',
            "graph:sync:{$this->syncType}",
            $this->id ? "id:{$this->id}" : 'batch',
        ];
    }

    /**
     * Helper: Dispatch sync with environment detection
     *
     * On localhost/dev: Run synchronously
     * On production: Dispatch to queue
     */
    public static function dispatchWithEnvDetection(string $syncType, string|int|null $id = null): mixed
    {
        $job = new self($syncType, $id);

        // Environment detection
        $isProduction = app()->environment('production');
        $isLocalhost = in_array(request()->ip(), ['127.0.0.1', '::1', 'localhost']);

        if ($isProduction && !$isLocalhost) {
            // Production: Dispatch to queue
            Log::info('Dispatching SyncGraphDataJob to queue (production)', [
                'sync_type' => $syncType,
                'id' => $id,
            ]);

            return self::dispatch($syncType, $id);
        } else {
            // Localhost/dev: Run synchronously
            Log::info('Running SyncGraphDataJob synchronously (dev/localhost)', [
                'sync_type' => $syncType,
                'id' => $id,
            ]);

            $graphRag = app(GraphRagService::class);
            $job->handle($graphRag);

            return ['mode' => 'sync', 'completed' => true];
        }
    }
}
```

**Helper Trait for Environment Detection**:

**New File**: `app/Traits/DetectsEnvironment.php`

```php
<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;

trait DetectsEnvironment
{
    /**
     * Check if running in production environment (not localhost)
     */
    protected function isProduction(): bool
    {
        $isProduction = app()->environment('production');
        $isLocalhost = in_array(request()->ip(), ['127.0.0.1', '::1', 'localhost']);

        return $isProduction && !$isLocalhost;
    }

    /**
     * Check if running on localhost/dev
     */
    protected function isLocalhost(): bool
    {
        return !$this->isProduction();
    }

    /**
     * Log environment detection
     */
    protected function logEnvironment(string $context): void
    {
        Log::info("Environment detection: {$context}", [
            'is_production' => $this->isProduction(),
            'is_localhost' => $this->isLocalhost(),
            'app_env' => app()->environment(),
            'request_ip' => request()->ip(),
        ]);
    }
}
```

**Testing**:
```php
// Dispatch with env detection
SyncGraphDataJob::dispatchWithEnvDetection('law', 'law-uuid');

// Force queue
SyncGraphDataJob::dispatch('decision', 'decision-uuid');

// Force sync (in tinker)
$job = new SyncGraphDataJob('case', 'case-uuid');
$job->handle(app(GraphRagService::class));
```

---

#### Task 1.3: Add Auto-Sync on Ingestion (2 hours)

**Goal**: Automatically sync documents to graph after ingestion

**Approach**: Use Laravel Event Listeners

##### Step 1: Create Events

**New File**: `app/Events/LawIngested.php`

```php
<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LawIngested
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $lawId
    ) {}
}
```

**New File**: `app/Events/CaseUploaded.php`

```php
<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CaseUploaded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $caseDocId
    ) {}
}
```

**New File**: `app/Events/CourtDecisionIngested.php`

```php
<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CourtDecisionIngested
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $decisionId
    ) {}
}
```

##### Step 2: Create Listeners

**New File**: `app/Listeners/SyncLawToGraph.php`

```php
<?php

namespace App\Listeners;

use App\Events\LawIngested;
use App\Jobs\SyncGraphDataJob;
use Illuminate\Support\Facades\Log;

class SyncLawToGraph
{
    public function handle(LawIngested $event): void
    {
        if (!config('neo4j.sync.enabled')) {
            Log::debug('Neo4j sync disabled, skipping law sync', ['law_id' => $event->lawId]);
            return;
        }

        if (config('neo4j.sync.auto_sync_on_ingest', true)) {
            Log::info('Auto-syncing law to graph', ['law_id' => $event->lawId]);

            SyncGraphDataJob::dispatchWithEnvDetection('law', $event->lawId);
        }
    }
}
```

**New File**: `app/Listeners/SyncCaseToGraph.php`

```php
<?php

namespace App\Listeners;

use App\Events\CaseUploaded;
use App\Jobs\SyncGraphDataJob;
use Illuminate\Support\Facades\Log;

class SyncCaseToGraph
{
    public function handle(CaseUploaded $event): void
    {
        if (!config('neo4j.sync.enabled')) {
            Log::debug('Neo4j sync disabled, skipping case sync', ['case_doc_id' => $event->caseDocId]);
            return;
        }

        if (config('neo4j.sync.auto_sync_on_ingest', true)) {
            Log::info('Auto-syncing case to graph', ['case_doc_id' => $event->caseDocId]);

            SyncGraphDataJob::dispatchWithEnvDetection('case', $event->caseDocId);
        }
    }
}
```

**New File**: `app/Listeners/SyncDecisionToGraph.php`

```php
<?php

namespace App\Listeners;

use App\Events\CourtDecisionIngested;
use App\Jobs\SyncGraphDataJob;
use Illuminate\Support\Facades\Log;

class SyncDecisionToGraph
{
    public function handle(CourtDecisionIngested $event): void
    {
        if (!config('neo4j.sync.enabled')) {
            Log::debug('Neo4j sync disabled, skipping decision sync', ['decision_id' => $event->decisionId]);
            return;
        }

        if (config('neo4j.sync.auto_sync_on_ingest', true)) {
            Log::info('Auto-syncing decision to graph', ['decision_id' => $event->decisionId]);

            SyncGraphDataJob::dispatchWithEnvDetection('decision', $event->decisionId);
        }
    }
}
```

##### Step 3: Register Listeners

**File to Edit**: `app/Providers/EventServiceProvider.php`

Add to `$listen` array:

```php
use App\Events\LawIngested;
use App\Events\CaseUploaded;
use App\Events\CourtDecisionIngested;
use App\Listeners\SyncLawToGraph;
use App\Listeners\SyncCaseToGraph;
use App\Listeners\SyncDecisionToGraph;

protected $listen = [
    // ... existing listeners ...

    LawIngested::class => [
        SyncLawToGraph::class,
    ],

    CaseUploaded::class => [
        SyncCaseToGraph::class,
    ],

    CourtDecisionIngested::class => [
        SyncDecisionToGraph::class,
    ],
];
```

##### Step 4: Dispatch Events After Ingestion

**Files to Edit**:

1. `app/Services/LawIngestService.php` - Add after law ingested:
```php
event(new \App\Events\LawIngested($law->id));
```

2. `app/Services/CaseIngestPipeline.php` - Add after case uploaded:
```php
event(new \App\Events\CaseUploaded($caseDoc->id));
```

3. `app/Services/Odluke/OdlukeIngestService.php` - Add after decision ingested:
```php
event(new \App\Events\CourtDecisionIngested($decision->id));
```

##### Step 5: Add Config Options

**File to Edit**: `config/neo4j.php`

Add configuration option:

```php
'sync' => [
    'enabled' => env('NEO4J_SYNC_ENABLED', true),
    'batch_size' => env('NEO4J_SYNC_BATCH_SIZE', 100),
    'auto_sync_on_ingest' => env('NEO4J_AUTO_SYNC', true), // NEW
],
```

**Testing**:
```bash
# Import law - should auto-sync
php artisan import:zakon-hr "NN 93/14"

# Check logs for "Auto-syncing law to graph"

# Upload case - should auto-sync
POST /api/uploads/complete

# Ingest decision - should auto-sync
php artisan decisions:ingest --id=UUID
```

---

### 📋 MEDIUM PRIORITY TASKS (7 hours → 98%)

#### Task 2.1: Add Advanced Query Methods (3 hours)

**File to Edit**: `app/Services/GraphQueryHelper.php`

**Add Methods**:

```php
/**
 * Find most influential court decisions using PageRank
 *
 * @param int $limit Number of top decisions to return
 * @return array [['id' => '...', 'case_number' => '...', 'rank' => 0.85], ...]
 */
public function findInfluentialDecisions(int $limit = 20): array
{
    $cypher = "
        CALL gds.pageRank.stream('decisions-graph')
        YIELD nodeId, score
        MATCH (d:CourtDecisionDocument) WHERE id(d) = nodeId
        RETURN d.id AS id, d.case_number AS case_number,
               d.court AS court, d.decision_date AS date,
               score AS rank
        ORDER BY score DESC
        LIMIT \$limit
    ";

    $result = $this->graph->run($cypher, ['limit' => $limit]);

    return $result->toArray();
}

/**
 * Detect citation clusters using community detection
 *
 * @return array Communities with their member decisions
 */
public function detectCitationClusters(): array
{
    $cypher = "
        CALL gds.louvain.stream('decisions-graph')
        YIELD nodeId, communityId
        MATCH (d:CourtDecisionDocument) WHERE id(d) = nodeId
        RETURN communityId,
               collect({id: d.id, case_number: d.case_number, court: d.court}) AS members
        ORDER BY size(members) DESC
    ";

    $result = $this->graph->run($cypher);

    return $result->toArray();
}

/**
 * Find related documents by graph topology (not just similarity)
 *
 * @param string $nodeId Starting node ID
 * @param int $maxHops Maximum relationship hops (default 3)
 * @return array Related nodes with relationship paths
 */
public function findRelatedByTopology(string $nodeId, int $maxHops = 3): array
{
    $cypher = "
        MATCH path = (start)-[*1..$maxHops]-(related)
        WHERE start.id = \$nodeId
        WITH related, path, length(path) AS distance
        ORDER BY distance
        LIMIT 50
        RETURN related.id AS id,
               labels(related)[0] AS type,
               related.title AS title,
               distance,
               relationships(path) AS relationship_types
    ";

    $result = $this->graph->run($cypher, [
        'nodeId' => $nodeId,
        'maxHops' => $maxHops,
    ]);

    return $result->toArray();
}

/**
 * Analyze citation network for a specific decision
 *
 * @param string $decisionId Decision ID to analyze
 * @return array Network metrics (centrality, clustering coefficient, etc.)
 */
public function analyzeCitationNetwork(string $decisionId): array
{
    // Get basic network stats
    $cypher = "
        MATCH (d:CourtDecisionDocument {id: \$id})
        OPTIONAL MATCH (d)-[:CITES]->(cited)
        OPTIONAL MATCH (d)<-[:CITES]-(citing)
        RETURN
            count(DISTINCT cited) AS outgoing_citations,
            count(DISTINCT citing) AS incoming_citations,
            count(DISTINCT cited) + count(DISTINCT citing) AS total_degree
    ";

    $stats = $this->graph->run($cypher, ['id' => $decisionId])->first();

    // Get citation clustering coefficient
    $clusteringCypher = "
        MATCH (d:CourtDecisionDocument {id: \$id})-[:CITES]->(neighbor)
        MATCH (neighbor)-[:CITES]->(common)
        MATCH (d)-[:CITES]->(common)
        RETURN count(DISTINCT common) AS common_citations
    ";

    $clustering = $this->graph->run($clusteringCypher, ['id' => $decisionId])->first();

    return [
        'decision_id' => $decisionId,
        'outgoing_citations' => $stats['outgoing_citations'] ?? 0,
        'incoming_citations' => $stats['incoming_citations'] ?? 0,
        'total_degree' => $stats['total_degree'] ?? 0,
        'common_citations' => $clustering['common_citations'] ?? 0,
        'clustering_coefficient' => $this->calculateClusteringCoefficient(
            $stats['outgoing_citations'] ?? 0,
            $clustering['common_citations'] ?? 0
        ),
    ];
}

/**
 * Calculate clustering coefficient
 */
protected function calculateClusteringCoefficient(int $neighbors, int $commonLinks): float
{
    if ($neighbors < 2) {
        return 0.0;
    }

    $possibleLinks = ($neighbors * ($neighbors - 1)) / 2;

    return $possibleLinks > 0 ? round($commonLinks / $possibleLinks, 4) : 0.0;
}

/**
 * Find shortest path between two nodes
 *
 * @param string $fromId Source node ID
 * @param string $toId Target node ID
 * @param int $maxLength Maximum path length
 * @return array|null Path nodes and relationships, or null if no path
 */
public function findShortestPath(string $fromId, string $toId, int $maxLength = 5): ?array
{
    $cypher = "
        MATCH (start {id: \$fromId}), (end {id: \$toId})
        MATCH path = shortestPath((start)-[*1..$maxLength]-(end))
        RETURN
            [node IN nodes(path) | {id: node.id, type: labels(node)[0], title: node.title}] AS nodes,
            [rel IN relationships(path) | type(rel)] AS relationships,
            length(path) AS length
    ";

    $result = $this->graph->run($cypher, [
        'fromId' => $fromId,
        'toId' => $toId,
        'maxLength' => $maxLength,
    ])->first();

    return $result ?: null;
}
```

**Testing**:
```php
$queryHelper = app(\App\Services\GraphQueryHelper::class);

// Find influential decisions
$influential = $queryHelper->findInfluentialDecisions(10);

// Detect citation clusters
$clusters = $queryHelper->detectCitationClusters();

// Find related documents
$related = $queryHelper->findRelatedByTopology('decision-uuid', 3);

// Analyze citation network
$metrics = $queryHelper->analyzeCitationNetwork('decision-uuid');

// Find path
$path = $queryHelper->findShortestPath('law-uuid', 'decision-uuid');
```

---

#### Task 2.2: Real-Time Relationship Updates (2 hours)

**Goal**: Update citation relationships when new documents are ingested

**New File**: `app/Services/GraphRelationshipUpdater.php`

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GraphRelationshipUpdater
{
    public function __construct(
        protected GraphRagService $graphRag,
        protected GraphDatabaseService $graph
    ) {}

    /**
     * Update relationships after a new law is ingested
     * Find all decisions that cite this law and create relationships
     */
    public function updateRelationshipsForNewLaw(string $lawId): void
    {
        $law = DB::table('laws')->where('id', $lawId)->first();

        if (!$law) {
            return;
        }

        Log::info('Updating relationships for new law', [
            'law_id' => $lawId,
            'law_number' => $law->law_number,
        ]);

        // Find all court decisions that might cite this law
        // Search decision content for law number mentions
        $decisions = DB::table('court_decisions')
            ->join('court_decision_documents', 'court_decisions.id', '=', 'court_decision_documents.decision_id')
            ->where('court_decision_documents.content', 'LIKE', "%{$law->law_number}%")
            ->select('court_decisions.id', 'court_decision_documents.id AS doc_id', 'court_decision_documents.content')
            ->get();

        $created = 0;

        foreach ($decisions as $decision) {
            try {
                // Re-extract citations from this decision
                $this->graphRag->syncCourtDecision($decision->id);
                $created++;
            } catch (\Exception $e) {
                Log::warning('Failed to update relationships for decision', [
                    'decision_id' => $decision->id,
                    'law_id' => $lawId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Relationship update completed', [
            'law_id' => $lawId,
            'decisions_updated' => $created,
        ]);
    }

    /**
     * Update relationships after a new court decision is ingested
     * Find all documents that cite this decision
     */
    public function updateRelationshipsForNewDecision(string $decisionId): void
    {
        $decision = DB::table('court_decisions')->where('id', $decisionId)->first();

        if (!$decision || !$decision->case_number) {
            return;
        }

        Log::info('Updating relationships for new decision', [
            'decision_id' => $decisionId,
            'case_number' => $decision->case_number,
        ]);

        // Find decisions that cite this case number
        $citingDecisions = DB::table('court_decisions')
            ->join('court_decision_documents', 'court_decisions.id', '=', 'court_decision_documents.decision_id')
            ->where('court_decision_documents.content', 'LIKE', "%{$decision->case_number}%")
            ->where('court_decisions.id', '!=', $decisionId)
            ->select('court_decisions.id')
            ->distinct()
            ->get();

        $created = 0;

        foreach ($citingDecisions as $citing) {
            try {
                // Re-extract citations from citing decision
                $this->graphRag->syncCourtDecision($citing->id);
                $created++;
            } catch (\Exception $e) {
                Log::warning('Failed to update relationships for citing decision', [
                    'citing_decision_id' => $citing->id,
                    'cited_decision_id' => $decisionId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Relationship update completed', [
            'decision_id' => $decisionId,
            'citing_decisions_updated' => $created,
        ]);
    }
}
```

**Update Listeners**:

Add to `app/Listeners/SyncLawToGraph.php`:

```php
public function handle(LawIngested $event): void
{
    // ... existing sync code ...

    // Update relationships
    if (config('neo4j.sync.update_relationships', true)) {
        $updater = app(\App\Services\GraphRelationshipUpdater::class);
        $updater->updateRelationshipsForNewLaw($event->lawId);
    }
}
```

Add to `app/Listeners/SyncDecisionToGraph.php`:

```php
public function handle(CourtDecisionIngested $event): void
{
    // ... existing sync code ...

    // Update relationships
    if (config('neo4j.sync.update_relationships', true)) {
        $updater = app(\App\Services\GraphRelationshipUpdater::class);
        $updater->updateRelationshipsForNewDecision($event->decisionId);
    }
}
```

**Config Update** (`config/neo4j.php`):

```php
'sync' => [
    'enabled' => env('NEO4J_SYNC_ENABLED', true),
    'batch_size' => env('NEO4J_SYNC_BATCH_SIZE', 100),
    'auto_sync_on_ingest' => env('NEO4J_AUTO_SYNC', true),
    'update_relationships' => env('NEO4J_UPDATE_RELATIONSHIPS', true), // NEW
],
```

---

### 📌 LOW PRIORITY TASKS (Optional - 2 hours → 100%)

#### Task 3.1: Graph Visualization API (2 hours)

**New File**: `app/Http/Controllers/GraphVisualizationController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Services\GraphDatabaseService;
use App\Services\GraphQueryHelper;
use Illuminate\Http\Request;

class GraphVisualizationController extends Controller
{
    public function __construct(
        protected GraphDatabaseService $graph,
        protected GraphQueryHelper $queryHelper
    ) {}

    /**
     * Get node with its neighbors
     *
     * GET /api/graph/visualize/{nodeId}
     */
    public function visualize(string $nodeId, Request $request)
    {
        $depth = $request->integer('depth', 1);
        $limit = $request->integer('limit', 50);

        $cypher = "
            MATCH (center {id: \$nodeId})
            OPTIONAL MATCH path = (center)-[r*1..$depth]-(neighbor)
            WITH center, collect(DISTINCT neighbor) AS neighbors, collect(DISTINCT r) AS relationships
            RETURN
                {id: center.id, type: labels(center)[0], properties: properties(center)} AS center,
                [n IN neighbors | {id: n.id, type: labels(n)[0], properties: properties(n)}] AS neighbors,
                [rel IN relationships | {type: type(rel), properties: properties(rel)}] AS relationships
            LIMIT \$limit
        ";

        $result = $this->graph->run($cypher, [
            'nodeId' => $nodeId,
            'depth' => $depth,
            'limit' => $limit,
        ])->first();

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Get subgraph by filters
     *
     * POST /api/graph/subgraph
     */
    public function subgraph(Request $request)
    {
        $validated = $request->validate([
            'node_type' => 'required|string',
            'filters' => 'sometimes|array',
            'max_nodes' => 'sometimes|integer|min:1|max:1000',
            'include_relationships' => 'sometimes|boolean',
        ]);

        $nodeType = $validated['node_type'];
        $filters = $validated['filters'] ?? [];
        $maxNodes = $validated['max_nodes'] ?? 100;
        $includeRels = $validated['include_relationships'] ?? true;

        // Build WHERE clause from filters
        $whereClauses = [];
        $params = ['maxNodes' => $maxNodes];

        foreach ($filters as $key => $value) {
            $whereClauses[] = "n.{$key} = \${$key}";
            $params[$key] = $value;
        }

        $whereClause = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

        $cypher = "
            MATCH (n:{$nodeType})
            {$whereClause}
            " . ($includeRels ? "OPTIONAL MATCH (n)-[r]-(m)" : "") . "
            WITH n" . ($includeRels ? ", collect(DISTINCT r) AS rels, collect(DISTINCT m) AS related" : "") . "
            LIMIT \$maxNodes
            RETURN
                {id: n.id, type: labels(n)[0], properties: properties(n)} AS node
                " . ($includeRels ? ", rels, related" : "") . "
        ";

        $result = $this->graph->run($cypher, $params);

        return response()->json([
            'success' => true,
            'data' => $result->toArray(),
        ]);
    }

    /**
     * Get graph statistics
     *
     * GET /api/graph/stats
     */
    public function stats()
    {
        $cypher = "
            MATCH (n)
            WITH labels(n)[0] AS type, count(*) AS count
            RETURN type, count
            ORDER BY count DESC
        ";

        $nodes = $this->graph->run($cypher)->toArray();

        $relsCypher = "
            MATCH ()-[r]->()
            WITH type(r) AS type, count(*) AS count
            RETURN type, count
            ORDER BY count DESC
        ";

        $relationships = $this->graph->run($relsCypher)->toArray();

        return response()->json([
            'success' => true,
            'data' => [
                'nodes' => $nodes,
                'relationships' => $relationships,
                'total_nodes' => array_sum(array_column($nodes, 'count')),
                'total_relationships' => array_sum(array_column($relationships, 'count')),
            ],
        ]);
    }
}
```

**Add Routes** (`routes/api.php`):

```php
// Graph Visualization API
Route::prefix('graph')->middleware(['api.token', 'throttle:60,1'])->group(function () {
    Route::get('/visualize/{nodeId}', [GraphVisualizationController::class, 'visualize']);
    Route::post('/subgraph', [GraphVisualizationController::class, 'subgraph']);
    Route::get('/stats', [GraphVisualizationController::class, 'stats']);
});
```

**Testing**:
```bash
# Visualize node with neighbors
curl http://localhost/api/graph/visualize/law-uuid?depth=2

# Get subgraph
curl -X POST http://localhost/api/graph/subgraph \
  -d '{"node_type": "CourtDecisionDocument", "filters": {"court": "Vrhovni sud"}, "max_nodes": 50}'

# Get stats
curl http://localhost/api/graph/stats
```

---

## Summary of Tasks

### HIGH Priority (5 hours → 95%)
| Task | File(s) | Effort | Impact |
|------|---------|--------|--------|
| 1.1 Add --decisions to command | GraphSyncCommand.php | 1h | HIGH |
| 1.2 Create SyncGraphDataJob | SyncGraphDataJob.php (NEW) | 2h | HIGH |
| 1.3 Auto-sync on ingestion | Events, Listeners, EventServiceProvider | 2h | HIGH |

### MEDIUM Priority (5 hours → 98%)
| Task | File(s) | Effort | Impact |
|------|---------|--------|--------|
| 2.1 Advanced query methods | GraphQueryHelper.php | 3h | MEDIUM |
| 2.2 Real-time relationship updates | GraphRelationshipUpdater.php (NEW) | 2h | MEDIUM |

### LOW Priority (2 hours → 100%)
| Task | File(s) | Effort | Impact |
|------|---------|--------|--------|
| 3.1 Graph visualization API | GraphVisualizationController.php (NEW), routes/api.php | 2h | LOW |

**Total Effort**: 12 hours
**Priority Path**: Focus on HIGH priority (5h) to reach 95%, then MEDIUM (5h) to 98%

---

## Environment-Based Execution Pattern

**All agents and queue jobs should follow this pattern**:

```php
// In job class or agent method
use App\Traits\DetectsEnvironment;

class SomeJob implements ShouldQueue
{
    use DetectsEnvironment;

    public static function dispatchWithEnvDetection(...$args): mixed
    {
        if (app()->environment('production') && !in_array(request()->ip(), ['127.0.0.1', '::1'])) {
            // Production: Queue it
            return self::dispatch(...$args);
        } else {
            // Localhost/Dev: Run sync
            $job = new self(...$args);
            $job->handle(...);
            return ['mode' => 'sync'];
        }
    }
}
```

**Usage**:
```php
// Will auto-detect and choose sync or async
SomeJob::dispatchWithEnvDetection($param1, $param2);
```

---

## Testing Checklist

After completing tasks, test:

### High Priority
- [ ] `php artisan graph:sync --decisions` works
- [ ] `php artisan graph:sync --textract` works
- [ ] `php artisan graph:sync --all` syncs everything
- [ ] `SyncGraphDataJob::dispatch('law', $id)` works in queue
- [ ] `SyncGraphDataJob::dispatchWithEnvDetection('case', $id)` detects environment
- [ ] Importing law auto-syncs to graph
- [ ] Uploading case auto-syncs to graph
- [ ] Ingesting decision auto-syncs to graph

### Medium Priority
- [ ] `findInfluentialDecisions()` returns ranked decisions
- [ ] `detectCitationClusters()` returns communities
- [ ] `findRelatedByTopology()` finds connected nodes
- [ ] `analyzeCitationNetwork()` returns metrics
- [ ] Ingesting new law updates existing decision relationships
- [ ] Ingesting new decision updates citing decision relationships

### Low Priority
- [ ] `GET /api/graph/visualize/{id}` returns node + neighbors
- [ ] `POST /api/graph/subgraph` returns filtered subgraph
- [ ] `GET /api/graph/stats` returns graph statistics

---

**Document End** | Neo4j Completion Analysis & Action Plan v1.0

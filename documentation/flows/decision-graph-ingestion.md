# Decision Graph Ingestion Flow Documentation

## Overview

This document describes the complete flow of court decision ingestion with graph database synchronization, introduced in Sprint D2.2. The feature enables decisions to be stored in both PostgreSQL (vector embeddings) and Neo4j (graph relationships) during a single ingestion process.

---

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                     Decision Ingestion Flow                      │
│                    with Graph Synchronization                    │
└─────────────────────────────────────────────────────────────────┘

1. Command Layer
   ├─ IngestCourtDecisionsEmbeddings (artisan command)
   └─ Parses CLI options including --sync-graph flag

2. Service Layer
   ├─ OdlukeIngestService (orchestrates ingestion)
   │  ├─ OdlukeClient (fetches decisions from odluke.sudovi.hr)
   │  ├─ IngestPipelineService (chunks text)
   │  ├─ CourtDecisionVectorStoreService (stores embeddings)
   │  └─ GraphDatabaseService (syncs to Neo4j)
   │
   └─ GraphQueryHelper (builds Cypher queries)

3. Data Layer
   ├─ PostgreSQL (vector embeddings in court_decision_documents)
   └─ Neo4j (graph relationships)
```

---

## Detailed Flow

### Phase 1: Command Initialization

**File**: `app/Console/Commands/IngestCourtDecisionsEmbeddings.php`

```
User runs:
$ php artisan decisions:ingest --id=12345 --sync-graph

│
├─ Command validates options
├─ Searches for decision IDs (if --query or --params provided)
├─ Extracts options:
│  ├─ model (embedding model)
│  ├─ chunk (chunk size)
│  ├─ overlap (overlap size)
│  ├─ prefer (html/pdf/auto)
│  ├─ dry (dry run mode)
│  └─ sync_graph (enable graph sync) ◄── NEW IN D2.2
│
└─ Calls OdlukeIngestService::ingestByIds() with options
```

**Key Code**:
```php
$res = $this->ingest->ingestByIds($ids, [
    'prefer' => $prefer,
    'dry' => $dry,
    'model' => $model,
    'chunk_chars' => $chunk,
    'overlap' => $overlap,
    'sync_graph' => $this->option('sync-graph'), // ◄── Passed to service
]);
```

---

### Phase 2: Service Layer Orchestration

**File**: `app/Services/Odluke/OdlukeIngestService.php`

```
OdlukeIngestService::ingestByIds()
│
├─ FOR EACH decision ID:
│  │
│  ├─ 1. Fetch Metadata
│  │   ├─ OdlukeClient::fetchDecisionMeta($id)
│  │   └─ Returns: broj_odluke, sud, datum_odluke, ecli, etc.
│  │
│  ├─ 2. Upsert CourtDecision Model
│  │   └─ upsertDecisionFromMeta() → creates/updates court_decisions record
│  │
│  ├─ 3. Download Content
│  │   ├─ Try HTML (if prefer != 'pdf')
│  │   │  └─ OdlukeClient::downloadHtml($id)
│  │   └─ Fallback to PDF (if HTML fails or prefer == 'pdf')
│  │      ├─ OdlukeClient::downloadPdf($id)
│  │      └─ OcrService::extractTextFromPdf() (if OCR available)
│  │
│  ├─ 4. Text Processing
│  │   ├─ Strip HTML tags / Extract PDF text
│  │   ├─ Normalize whitespace
│  │   └─ Skip if empty text
│  │
│  ├─ 5. Chunk and Embed (Vector Ingestion)
│  │   ├─ IngestPipelineService::chunkText()
│  │   ├─ OpenAI API: Generate embeddings
│  │   └─ CourtDecisionVectorStoreService::ingest()
│  │      └─ Stores in court_decision_documents table
│  │
│  └─ 6. Graph Sync (NEW IN D2.2) ◄──────────────────┐
│     │                                                │
│     ├─ IF sync_graph == true                        │
│     ├─ AND not dry run                              │
│     ├─ AND graphDb service available                │
│     ├─ AND vector ingestion succeeded (inserted > 0)│
│     │                                                │
│     THEN:                                            │
│        ├─ TRY:                                       │
│        │  └─ GraphDatabaseService::storeDecisionInGraph()
│        │     │
│        │     ├─ GraphQueryHelper::buildDecisionQuery()
│        │     │  └─ Builds parameterized Cypher query
│        │     │
│        │     └─ Executes in Neo4j transaction
│        │        ├─ MERGE CourtDecisionDocument node
│        │        ├─ MERGE Court node (if provided)
│        │        ├─ MERGE Jurisdiction node
│        │        ├─ CREATE relationships
│        │        └─ RETURN doc
│        │
│        └─ CATCH:
│           └─ Log error but continue (don't block vector ingestion)
│
└─ RETURN statistics:
   ├─ ids_processed
   ├─ inserted (vector documents)
   ├─ errors
   ├─ skipped
   ├─ graph_synced ◄── NEW
   └─ graph_errors ◄── NEW
```

---

### Phase 3: Graph Database Synchronization

**File**: `app/Services/GraphDatabaseService.php`

```
GraphDatabaseService::storeDecisionInGraph(meta, docId)
│
├─ 1. Build Query
│  └─ GraphQueryHelper::buildDecisionQuery(meta, docId)
│     │
│     ├─ Extracts metadata:
│     │  ├─ case_number (broj_odluke)
│     │  ├─ court (sud)
│     │  ├─ jurisdiction (default: HR)
│     │  ├─ decision_date (datum_odluke)
│     │  ├─ publication_date (datum_objave)
│     │  ├─ decision_type (vrsta_odluke)
│     │  ├─ register (upisnik)
│     │  ├─ finality (pravomocnost)
│     │  └─ ecli
│     │
│     └─ Returns:
│        ├─ query: Cypher query string
│        └─ parameters: Array of values
│
├─ 2. Execute Transaction
│  └─ Neo4jClient::transaction()
│     └─ Runs Cypher query with parameters
│        │
│        ├─ MERGE (doc:CourtDecisionDocument {id: $doc_id})
│        ├─ SET doc properties
│        │
│        ├─ FOREACH court creation:
│        │  ├─ MERGE (court:Court {name: $court})
│        │  └─ MERGE (doc)-[:DECIDED_BY]->(court)
│        │
│        └─ FOREACH jurisdiction creation:
│           ├─ MERGE (jurisdiction:Jurisdiction {id: $jurisdiction_id})
│           └─ MERGE (doc)-[:BELONGS_TO_JURISDICTION]->(jurisdiction)
│
├─ 3. Log Success
│  └─ Log::info() with doc_id, ecli, case_number, court
│
└─ 4. Error Handling
   ├─ Log::error() with full context
   └─ throw Exception (caught by OdlukeIngestService)
```

---

### Phase 4: Cypher Query Structure

**File**: `app/Services/GraphQueryHelper.php`

The generated Cypher query follows this pattern:

```cypher
// 1. Create or update the decision document node
MERGE (doc:CourtDecisionDocument {id: $doc_id})
SET doc.case_number = $case_number,
    doc.court = $court,
    doc.jurisdiction = $jurisdiction,
    doc.decision_date = $decision_date,
    doc.publication_date = $publication_date,
    doc.decision_type = $decision_type,
    doc.register = $register,
    doc.finality = $finality,
    doc.ecli = $ecli,
    doc.title = $title,
    doc.updated_at = $updated_at,
    doc.created_at = coalesce(doc.created_at, $created_at)

WITH doc

// 2. Conditionally create court node and relationship
FOREACH (ignore IN CASE WHEN $court IS NOT NULL THEN [1] ELSE [] END |
    MERGE (court:Court {name: $court})
    SET court.jurisdiction = $jurisdiction,
        court.updated_at = $updated_at
    MERGE (doc)-[:DECIDED_BY]->(court)
)

WITH doc

// 3. Conditionally create jurisdiction node and relationship
FOREACH (ignore IN CASE WHEN $jurisdiction IS NOT NULL THEN [1] ELSE [] END |
    MERGE (jurisdiction:Jurisdiction {id: $jurisdiction_id})
    SET jurisdiction.name = $jurisdiction,
        jurisdiction.updated_at = $updated_at
    MERGE (doc)-[:BELONGS_TO_JURISDICTION]->(jurisdiction)
)

RETURN doc
```

**Key Features**:
- **MERGE**: Idempotent - handles duplicate ECLI gracefully
- **WITH clauses**: Explicit variable passing for Neo4j compatibility
- **FOREACH + CASE WHEN**: Conditional execution (only if court/jurisdiction provided)
- **coalesce()**: Preserves original created_at on updates

---

## Data Flow Diagram

```
┌─────────────────┐
│ CLI Command     │
│ decisions:ingest│
│ --id=12345      │
│ --sync-graph    │
└────────┬────────┘
         │
         ▼
┌─────────────────────────────────────┐
│ OdlukeIngestService                 │
│ ┌─────────────────────────────────┐ │
│ │ 1. Fetch metadata from API      │ │
│ │ 2. Download HTML/PDF            │ │
│ │ 3. Extract text                 │ │
│ │ 4. Chunk text                   │ │
│ │ 5. Generate embeddings          │ │
│ │ 6. Store in PostgreSQL          │ │
│ └─────────────────────────────────┘ │
└────────┬────────────────────────────┘
         │
         │ IF sync_graph enabled
         │ AND vector ingestion succeeded
         ▼
┌─────────────────────────────────────┐
│ GraphDatabaseService                │
│ ┌─────────────────────────────────┐ │
│ │ 1. Build Cypher query           │ │
│ │ 2. Execute in transaction       │ │
│ │ 3. Create/update nodes          │ │
│ │ 4. Create relationships         │ │
│ └─────────────────────────────────┘ │
└────────┬────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────┐
│ Neo4j Graph Database                │
│                                     │
│  (doc:CourtDecisionDocument)        │
│         │                           │
│         ├─[:DECIDED_BY]→(Court)     │
│         │                           │
│         └─[:BELONGS_TO_JURISDICTION]│
│           →(Jurisdiction)           │
└─────────────────────────────────────┘
```

---

## Error Handling Strategy

### 1. Vector Ingestion Errors
**Location**: `OdlukeIngestService::ingestByIds()`

```php
try {
    // Fetch, download, chunk, embed
    $res = $this->chunkAndEmbed(...);
} catch (\Throwable $e) {
    $errors++;
    Log::warning('Odluke ingest failed', ['id' => $id, 'error' => $e->getMessage()]);
    // SKIP to next decision
}
```

**Behavior**: Logs error, increments error counter, continues to next decision.

---

### 2. Graph Sync Errors
**Location**: `OdlukeIngestService::ingestByIds()` (after successful vector ingestion)

```php
if ($syncGraph && !$dry && $this->graphDb && $currentInserted > 0) {
    try {
        $this->graphDb->storeDecisionInGraph($meta, $docId);
        $graphSynced++;
    } catch (\Throwable $graphError) {
        // Log but DON'T block vector ingestion
        $graphErrors++;
        Log::warning('Graph sync failed for decision (vector ingestion succeeded)', [
            'id' => $id,
            'doc_id' => $docId,
            'ecli' => $meta['ecli'] ?? null,
            'error' => $graphError->getMessage(),
        ]);
    }
}
```

**Behavior**:
- Logs error with full context
- Increments graph_errors counter
- **DOES NOT** block or roll back vector ingestion
- Allows other decisions to continue syncing

---

### 3. Neo4j Transaction Errors
**Location**: `GraphDatabaseService::storeDecisionInGraph()`

```php
try {
    $this->transaction(function ($tsx) use ($queryData) {
        return $tsx->run($queryData['query'], $queryData['parameters']);
    });
    Log::info('Decision stored in graph successfully', [...]);
} catch (\Exception $e) {
    Log::error('Failed to store decision in graph', [
        'doc_id' => $docId,
        'meta' => $meta,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
    throw $e; // Re-throw to be caught by OdlukeIngestService
}
```

**Behavior**:
- Logs error with full stack trace
- Throws exception back to caller
- Transaction automatically rolls back

---

## Graph Schema

### Nodes Created

#### 1. CourtDecisionDocument
```
Labels: CourtDecisionDocument
Properties:
  - id: string (unique, e.g., ECLI or generated ID)
  - case_number: string (broj_odluke)
  - court: string (sud)
  - jurisdiction: string (e.g., "HR")
  - decision_date: string (ISO 8601)
  - publication_date: string (ISO 8601)
  - decision_type: string (vrsta_odluke)
  - register: string (upisnik)
  - finality: string (pravomocnost)
  - ecli: string (nullable)
  - title: string
  - created_at: string (ISO 8601)
  - updated_at: string (ISO 8601)
```

#### 2. Court
```
Labels: Court
Properties:
  - name: string (unique, e.g., "Vrhovni sud Republike Hrvatske")
  - jurisdiction: string (e.g., "HR")
  - updated_at: string (ISO 8601)
```

#### 3. Jurisdiction
```
Labels: Jurisdiction
Properties:
  - id: string (unique, e.g., "jurisdiction_HR")
  - name: string (e.g., "HR")
  - updated_at: string (ISO 8601)
```

### Relationships

#### 1. DECIDED_BY
```
Pattern: (CourtDecisionDocument)-[:DECIDED_BY]->(Court)
Properties: None
Meaning: Decision was made by this court
```

#### 2. BELONGS_TO_JURISDICTION
```
Pattern: (CourtDecisionDocument)-[:BELONGS_TO_JURISDICTION]->(Jurisdiction)
Properties: None
Meaning: Decision belongs to this legal jurisdiction
```

---

## Integration Points

### 1. Dependency Injection

**Service Provider**: `app/Providers/GraphServiceProvider.php`

```php
$this->app->singleton(GraphDatabaseService::class, function ($app) {
    return new GraphDatabaseService();
});
```

**Registration**: `bootstrap/providers.php`
```php
return [
    App\Providers\AppServiceProvider::class,
    App\Providers\GraphServiceProvider::class, // ◄── Line 9
    // ...
];
```

**Constructor Injection**: `OdlukeIngestService`
```php
public function __construct(
    protected OdlukeClient $client,
    protected IngestPipelineService $pipeline,
    protected CourtDecisionVectorStoreService $decisionVectors,
    protected ?OcrService $ocr = null,
    protected ?MetadataBuilder $meta = null,
    protected ?\App\Services\GraphDatabaseService $graphDb = null, // ◄── Nullable
) {}
```

**Why nullable?**
- Allows service to work even if Neo4j is not configured
- Gracefully degrades to vector-only ingestion
- No breaking changes to existing code

---

### 2. Configuration

**Neo4j Connection**: `config/neo4j.php`
```php
'connections' => [
    'bolt' => [
        'driver' => 'bolt',
        'host' => env('NEO4J_HOST', '127.0.0.1'),
        'port' => env('NEO4J_PORT', 7687),
        'username' => env('NEO4J_USERNAME', 'neo4j'),
        'password' => env('NEO4J_PASSWORD', ''),
        'database' => env('NEO4J_DATABASE', 'neo4j'),
    ],
],
```

**Environment Variables**: `.env`
```
NEO4J_HOST=127.0.0.1
NEO4J_PORT=7687
NEO4J_USERNAME=neo4j
NEO4J_PASSWORD=your_password
NEO4J_DATABASE=neo4j
```

---

## Usage Examples

### Basic Usage (Vector Only)
```bash
php artisan decisions:ingest --id=12345
```
**Result**: Decision stored in PostgreSQL only

---

### With Graph Sync
```bash
php artisan decisions:ingest --id=12345 --sync-graph
```
**Result**: Decision stored in both PostgreSQL and Neo4j

---

### Batch Ingestion with Graph Sync
```bash
php artisan decisions:ingest \
  --id=12345 \
  --id=67890 \
  --id=11111 \
  --sync-graph \
  --model=text-embedding-3-large
```
**Result**: All decisions ingested and synced

---

### Search and Sync
```bash
php artisan decisions:ingest \
  --query="ugovor" \
  --limit=50 \
  --sync-graph
```
**Result**: Searches for "ugovor", ingests up to 50 decisions, syncs to graph

---

### Dry Run (Test Mode)
```bash
php artisan decisions:ingest --id=12345 --sync-graph --dry
```
**Result**: Simulates ingestion, no data written (vector or graph)

---

## Output Format

### Console Output

```
Starting court decisions ingestion from odluke.sudovi.hr
Processing 3 decision(s)...
Options: model=text-embedding-3-large chunk=1500 overlap=200 prefer=auto

[=============================] 100%

Ingestion completed!

┌──────────────────────┬────────┐
│ Metric               │ Value  │
├──────────────────────┼────────┤
│ IDs Processed        │ 3      │
│ Documents Inserted   │ 45     │
│ Chunks Generated     │ 45     │
│ Errors               │ 0      │
│ Skipped              │ 0      │
│ Model                │ text-  │
│                      │embedding│
│                      │-3-large│
│ Dry Run              │ No     │
│ Graph Synced         │ 3      │  ◄── NEW
│ Graph Errors         │ 0      │  ◄── NEW
└──────────────────────┴────────┘
```

### Programmatic Return Value

```php
[
    'ids_processed' => 3,
    'inserted' => 45,          // Vector documents inserted
    'would_chunks' => 45,
    'errors' => 0,
    'skipped' => 0,
    'graph_synced' => 3,       // NEW: Decisions synced to graph
    'graph_errors' => 0,       // NEW: Graph sync failures
    'model' => 'text-embedding-3-large',
    'dry' => false,
]
```

---

## Performance Considerations

### 1. Transaction Overhead
- Each decision is synced in a separate Neo4j transaction
- **Trade-off**: Consistency vs. performance
- **Future optimization**: Batch multiple decisions in single transaction

### 2. Network Latency
- Neo4j sync adds ~50-200ms per decision (network + query execution)
- **Mitigation**: Optional flag allows disabling when not needed

### 3. Error Recovery
- Vector ingestion completes even if graph sync fails
- **Benefit**: Partial success instead of complete failure
- **Manual recovery**: Can run graph sync separately later via `GraphRagService::syncCourtDecision()`

---

## Testing Scenarios

### Scenario 1: Normal Operation
```
Input: --id=12345 --sync-graph
Expected: Decision in PostgreSQL + Neo4j, graph_synced=1
```

### Scenario 2: Duplicate ECLI
```
Input: Same decision ingested twice with --sync-graph
Expected: MERGE updates existing node, no error
```

### Scenario 3: Neo4j Unavailable
```
Input: --id=12345 --sync-graph (Neo4j down)
Expected: Vector ingestion succeeds, graph_errors=1, error logged
```

### Scenario 4: Missing Court
```
Input: Decision with court=null, --sync-graph
Expected: CourtDecisionDocument created, no Court node, no error
```

### Scenario 5: Dry Run
```
Input: --id=12345 --sync-graph --dry
Expected: No data written to PostgreSQL or Neo4j
```

---

## Monitoring and Observability

### Log Entries

#### Success
```
[INFO] Decision stored in graph successfully
{
    "doc_id": "ECLI:HR:VSRH:2023:123",
    "ecli": "ECLI:HR:VSRH:2023:123",
    "case_number": "Rev 123/2023",
    "court": "Vrhovni sud Republike Hrvatske"
}
```

#### Graph Sync Failure
```
[WARNING] Graph sync failed for decision (vector ingestion succeeded)
{
    "id": "12345",
    "doc_id": "ECLI:HR:VSRH:2023:123",
    "ecli": "ECLI:HR:VSRH:2023:123",
    "error": "Connection refused (Neo4j)"
}
```

#### Neo4j Transaction Failure
```
[ERROR] Failed to store decision in graph
{
    "doc_id": "ECLI:HR:VSRH:2023:123",
    "meta": {...},
    "error": "Invalid Cypher syntax",
    "trace": "..."
}
```

---

## Future Enhancements

### 1. Citation Extraction (Sprint D2.3+)
Currently deferred to `GraphRagService::syncCourtDecision()`:
- Extract law citations (e.g., "članak 5. ZPP")
- Create CITES relationships to LawDocument nodes
- Extract case references (e.g., "Rev 123/2020")
- Create REFERENCES relationships to other decisions

### 2. Batch Optimization
- Process multiple decisions in single Neo4j transaction
- Reduces network roundtrips
- Requires careful error handling to maintain partial success behavior

### 3. Async Graph Sync
- Queue graph sync operations
- Process in background worker
- Non-blocking for high-volume ingestion

### 4. Graph Validation
- Post-sync validation queries
- Detect orphaned nodes
- Detect missing relationships

---

## Troubleshooting

### Issue: Graph sync always shows 0 synced

**Check**:
1. Is `--sync-graph` flag present?
2. Is Neo4j running and accessible?
3. Is GraphDatabaseService registered in GraphServiceProvider?
4. Check logs for errors

**Solution**:
```bash
# Verify Neo4j connection
php artisan graph:stats

# Check logs
tail -f storage/logs/laravel.log | grep -i graph
```

---

### Issue: Duplicate nodes with same ECLI

**Check**:
- MERGE is using `id` property (not `ecli`)
- `id` is unique (generated from ECLI or fallback)

**Solution**:
```cypher
// Check for duplicates
MATCH (doc:CourtDecisionDocument)
WHERE doc.ecli IS NOT NULL
WITH doc.ecli AS ecli, count(doc) AS cnt
WHERE cnt > 1
RETURN ecli, cnt
```

---

### Issue: Graph sync fails but vector ingestion succeeds

**Expected Behavior**: This is by design!

**Explanation**:
- Graph sync errors are caught and logged
- Vector ingestion is not rolled back
- Allows manual recovery later

**Recovery**:
```php
// Manually sync specific decision
$graphRag->syncCourtDecision($decisionId);
```

---

## Summary

The Decision Graph Ingestion feature (Sprint D2.2) provides:

✅ **Dual Storage**: Vector embeddings + Graph relationships
✅ **Idempotent Operations**: MERGE handles duplicates gracefully
✅ **Resilient Error Handling**: Graph failures don't block vector ingestion
✅ **Service Layer Architecture**: Reusable, testable, maintainable
✅ **Backward Compatible**: Optional flag, no breaking changes
✅ **Well-Documented**: Comprehensive logging and observability

The flow ensures that court decisions are enriched with graph relationships while maintaining the robustness of the existing vector ingestion pipeline.

---

**Document Version**: 1.0
**Last Updated**: Sprint D2.2
**Maintained By**: Backend Team

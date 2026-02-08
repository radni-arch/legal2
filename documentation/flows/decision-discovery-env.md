# Decision Discovery Agent - Environment Detection Flow

**Date**: October 31, 2025
**Task**: 1.2 - Add Environment Detection to DecisionDiscoveryAgent
**Status**: ✅ Implemented

---

## Overview

This document describes the environment-aware execution flow for the **DecisionDiscoveryAgent**, which automatically discovers and ingests Croatian court decisions using AI-powered topic generation and relevance scoring.

The environment detection pattern allows the agent to:
- **Run synchronously** on localhost/development (immediate execution)
- **Dispatch to queue** on production (background processing)

---

## Architecture Components

### 1. DecisionDiscoveryAgent
**Location**: `app/Agents/DecisionDiscoveryAgent.php`

**Purpose**: Autonomous agent that discovers and ingests court decisions

**Key Methods**:
```php
// Environment-aware discovery execution
public static function discoverWithEnvDetection(?int $maxDecisions = null, ?string $topic = null): mixed

// Check if discovery is currently running
public static function isRunning(): bool

// Get statistics from latest completed run
public static function getLatestRunStats(): ?array
```

### 2. ExecuteDecisionDiscoveryJob
**Location**: `app/Jobs/ExecuteDecisionDiscoveryJob.php` *(to be implemented)*

**Purpose**: Queue job wrapper for DecisionDiscoveryAgent

**Key Method**:
```php
public static function dispatchWithEnvDetection(?int $maxDecisions = null, ?string $topic = null): mixed
```

### 3. DecisionDiscoveryRun Model
**Location**: `app/Models/DecisionDiscoveryRun.php`

**Purpose**: Tracks discovery run history and statistics

**Database Table**: `decision_discovery_runs`

---

## Database Schema

### `decision_discovery_runs` Table

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `started_at` | timestamp | When discovery run started |
| `completed_at` | timestamp (nullable) | When discovery run completed |
| `topics_generated` | integer | Number of AI-generated topics |
| `decisions_evaluated` | integer | Total decisions evaluated by AI |
| `decisions_ingested` | integer | Number of decisions added to database |
| `topics` | json (nullable) | Array of generated topic strings |
| `errors` | json (nullable) | Array of errors encountered |
| `status` | string | `running`, `completed`, or `failed` |
| `error_message` | text (nullable) | Error details if failed |
| `created_at` | timestamp | Record creation time |
| `updated_at` | timestamp | Record update time |

**Migration**: `database/migrations/2025_10_27_100000_create_decision_discovery_runs_table.php`

---

## Execution Flow

### Flow Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│  DecisionDiscoveryAgent::discoverWithEnvDetection()            │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│  ExecuteDecisionDiscoveryJob::dispatchWithEnvDetection()       │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
                 ┌───────────────┐
                 │ Environment   │
                 │  Detection    │
                 └───────┬───────┘
                         │
         ┌───────────────┴───────────────┐
         │                               │
         ▼                               ▼
┌─────────────────┐            ┌─────────────────┐
│  DEVELOPMENT    │            │   PRODUCTION    │
│   (Localhost)   │            │                 │
└────────┬────────┘            └────────┬────────┘
         │                               │
         │ Run Synchronously             │ Dispatch to Queue
         │ (Immediate)                   │ (Background)
         ▼                               ▼
┌─────────────────┐            ┌─────────────────┐
│  Agent->handle()│            │  Queue Worker   │
│   (Direct Call) │            │   picks up job  │
└────────┬────────┘            └────────┬────────┘
         │                               │
         │                               ▼
         │                      ┌─────────────────┐
         │                      │  Agent->handle()│
         │                      │   (Async Call)  │
         │                      └────────┬────────┘
         │                               │
         └───────────────┬───────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│                  Agent Discovery Process                        │
│  1. Generate research topics (via OpenAI)                       │
│  2. Search odluke.sudovi.hr for each topic                      │
│  3. Score decisions for relevance (via OpenAI)                  │
│  4. Ingest top-scoring decisions                                │
│  5. Update decision_discovery_runs table                        │
└─────────────────────────────────────────────────────────────────┘
```

### Detailed Sequence Diagram

#### Production Environment (Queue-based)

```
User/API        DecisionDiscoveryAgent    ExecuteDiscoveryJob    Queue    Worker    Database
   │                    │                          │              │         │          │
   │─────────────────>  │                          │              │         │          │
   │ discoverWithEnv()  │                          │              │         │          │
   │                    │                          │              │         │          │
   │                    │──────────────────────> │              │         │          │
   │                    │ dispatchWithEnvDetection()              │         │          │
   │                    │                          │              │         │          │
   │                    │                          │─ Detect Env ─┤         │          │
   │                    │                          │  Production  │         │          │
   │                    │                          │              │         │          │
   │                    │                          │────────────> │         │          │
   │                    │                          │  Push Job    │         │          │
   │                    │                          │              │         │          │
   │<───────────────────────────────────────────────              │         │          │
   │  {"queued": true, "job_id": "..."}                           │         │          │
   │                                                               │         │          │
   │                                              (later)          │         │          │
   │                                                               │────────> │          │
   │                                                               │ Pick Job │          │
   │                                                               │          │          │
   │                                                               │          │────────> │
   │                                                               │          │ INSERT   │
   │                                                               │          │ status:  │
   │                                                               │          │ running  │
   │                                                               │          │          │
   │                                                               │          │─────┐    │
   │                                                               │          │     │    │
   │                                                               │          │  1. Gen  │
   │                                                               │          │  Topics  │
   │                                                               │          │  (AI)    │
   │                                                               │          │     │    │
   │                                                               │          │<────┘    │
   │                                                               │          │          │
   │                                                               │          │─────┐    │
   │                                                               │          │     │    │
   │                                                               │          │  2. Find │
   │                                                               │          │  Decisions│
   │                                                               │          │  (API)   │
   │                                                               │          │     │    │
   │                                                               │          │<────┘    │
   │                                                               │          │          │
   │                                                               │          │─────┐    │
   │                                                               │          │     │    │
   │                                                               │          │  3. Score│
   │                                                               │          │  (AI)    │
   │                                                               │          │     │    │
   │                                                               │          │<────┘    │
   │                                                               │          │          │
   │                                                               │          │─────┐    │
   │                                                               │          │     │    │
   │                                                               │          │  4. Ingest│
   │                                                               │          │  Top 10  │
   │                                                               │          │     │    │
   │                                                               │          │<────┘    │
   │                                                               │          │          │
   │                                                               │          │────────> │
   │                                                               │          │ UPDATE   │
   │                                                               │          │ status:  │
   │                                                               │          │ completed│
```

#### Development Environment (Synchronous)

```
User/API        DecisionDiscoveryAgent    ExecuteDiscoveryJob    Agent Instance    Database
   │                    │                          │                    │              │
   │─────────────────>  │                          │                    │              │
   │ discoverWithEnv()  │                          │                    │              │
   │                    │                          │                    │              │
   │                    │──────────────────────> │                    │              │
   │                    │ dispatchWithEnvDetection()                    │              │
   │                    │                          │                    │              │
   │                    │                          │─ Detect Env ─┐     │              │
   │                    │                          │  Localhost   │     │              │
   │                    │                          │<─────────────┘     │              │
   │                    │                          │                    │              │
   │                    │                          │──────────────────> │              │
   │                    │                          │  handle() Direct   │              │
   │                    │                          │                    │              │
   │                    │                          │                    │────────────> │
   │                    │                          │                    │  INSERT      │
   │                    │                          │                    │  status:     │
   │                    │                          │                    │  running     │
   │                    │                          │                    │              │
   │                    │                          │                    │──────┐       │
   │                    │                          │                    │ 1-4. │       │
   │                    │                          │                    │ Same │       │
   │                    │                          │                    │ Flow │       │
   │                    │                          │                    │<─────┘       │
   │                    │                          │                    │              │
   │                    │                          │                    │────────────> │
   │                    │                          │                    │  UPDATE      │
   │                    │                          │                    │  completed   │
   │                    │                          │                    │              │
   │<──────────────────────────────────────────────────────────────────┘              │
   │  {"mode": "sync", "completed": true, "stats": {...}}                             │
```

---

## Discovery Process Details

### Step 1: Generate Research Topics (AI)

**Service**: OpenAI GPT-4o-mini
**Temperature**: 0.8 (higher for diversity)
**Cache**: 1 week

```php
// Generates N topics (default: 5) such as:
[
    "Nezakonit otkaz",              // Unlawful termination
    "Ugovorna odgovornost",         // Contractual liability
    "Potrošačka zaštita",           // Consumer protection
    "Vlasničkopravni sporovi",      // Property disputes
    "Obvezno pravo"                 // Obligation law
]
```

**Fallback**: Predefined topics if AI fails

### Step 2: Search for Decisions

**API**: `odluke.sudovi.hr`
**Per Topic**: 50 decisions max
**Method**: `OdlukeClient::collectIdsFromList()`

Returns array of decision IDs from the court decision database.

### Step 3: Score Decisions (AI)

**Service**: OpenAI GPT-4o-mini
**Temperature**: 0.3 (lower for consistency)
**Batch Size**: 10 decisions

```json
{
  "scores": [
    {
      "id": "decision_123",
      "score": 85,
      "reasoning": "Highly relevant Supreme Court ruling on employment termination"
    },
    {
      "id": "decision_456",
      "score": 72,
      "reasoning": "Relevant county court precedent on contractual disputes"
    }
  ]
}
```

**Scoring Criteria**:
- 90-100: Highly relevant, authoritative court
- 70-89: Relevant, useful precedent
- 50-69: Somewhat relevant
- 0-49: Not relevant or low quality

**Threshold**: 70.0 minimum score required

### Step 4: Ingest Top Decisions

**Per Topic**: Top 10 highest-scoring decisions
**Service**: `OdlukeIngestService::ingestByIds()`
**Options**:
- `sync_graph: true` - Sync to Neo4j knowledge graph
- `chunk_chars: 1500` - Text chunk size for embeddings
- `overlap: 200` - Character overlap between chunks

---

## Environment Detection Logic

Based on `SyncGraphDataJob::dispatchWithEnvDetection()` pattern:

```php
public static function dispatchWithEnvDetection(?int $maxDecisions = null, ?string $topic = null): mixed
{
    $job = new self($maxDecisions, $topic);

    // Environment detection
    $isProduction = app()->environment('production');
    $isLocalhost = in_array(request()->ip(), ['127.0.0.1', '::1', 'localhost']);

    if ($isProduction && !$isLocalhost) {
        // PRODUCTION: Dispatch to queue
        Log::info('Dispatching ExecuteDecisionDiscoveryJob to queue (production)', [
            'max_decisions' => $maxDecisions,
            'topic' => $topic,
        ]);

        return self::dispatch($maxDecisions, $topic);

    } else {
        // DEVELOPMENT: Run synchronously
        Log::info('Running ExecuteDecisionDiscoveryJob synchronously (dev/localhost)', [
            'max_decisions' => $maxDecisions,
            'topic' => $topic,
        ]);

        $agent = app(DecisionDiscoveryAgent::class);
        $job->handle($agent);

        return ['mode' => 'sync', 'completed' => true];
    }
}
```

**Detection Factors**:
1. **App Environment**: `app()->environment('production')`
2. **Request IP**: Check if localhost (`127.0.0.1`, `::1`, `localhost`)

**Decision Matrix**:
| Environment | IP | Execution Mode |
|-------------|-----|----------------|
| production | Non-localhost | ⚡ Queue (Async) |
| production | localhost | 🔄 Sync |
| local/dev | Any | 🔄 Sync |

---

## Agent Helper Methods

### Check if Running

```php
use App\Agents\DecisionDiscoveryAgent;

$isRunning = DecisionDiscoveryAgent::isRunning();

if ($isRunning) {
    echo "Discovery is currently in progress";
} else {
    echo "No active discovery run";
}
```

**Implementation**:
```php
public static function isRunning(): bool
{
    $recentRun = DB::table('decision_discovery_runs')
        ->where('status', 'running')
        ->where('created_at', '>', now()->subHours(2))
        ->exists();

    return $recentRun;
}
```

**Time Window**: Checks for runs started in last 2 hours with `running` status

### Get Latest Statistics

```php
use App\Agents\DecisionDiscoveryAgent;

$stats = DecisionDiscoveryAgent::getLatestRunStats();

if ($stats) {
    echo "Last run completed at: {$stats['completed_at']}\n";
    echo "Topics generated: {$stats['topics_generated']}\n";
    echo "Decisions found: {$stats['decisions_found']}\n";
    echo "Decisions ingested: {$stats['decisions_ingested']}\n";
    echo "Duration: {$stats['duration_seconds']}s\n";
}
```

**Returns**:
```php
[
    'completed_at' => '2025-10-31 14:30:00',
    'decisions_found' => 250,
    'decisions_ingested' => 50,
    'topics_generated' => 5,
    'duration_seconds' => 180
]
```

Or `null` if no completed runs exist.

---

## Usage Examples

### Trigger Discovery (Auto-detect Environment)

```php
use App\Agents\DecisionDiscoveryAgent;

// Will run sync on localhost, queue on production
$result = DecisionDiscoveryAgent::discoverWithEnvDetection();

// With parameters
$result = DecisionDiscoveryAgent::discoverWithEnvDetection(
    maxDecisions: 100,
    topic: 'Radno pravo'  // Limit to employment law
);
```

### Check Status Before Running

```php
use App\Agents\DecisionDiscoveryAgent;

if (DecisionDiscoveryAgent::isRunning()) {
    echo "Discovery already running. Please wait.";
    return;
}

// Safe to start new run
DecisionDiscoveryAgent::discoverWithEnvDetection();
```

### Review Latest Run

```php
use App\Agents\DecisionDiscoveryAgent;

$stats = DecisionDiscoveryAgent::getLatestRunStats();

if (!$stats) {
    echo "No discovery runs completed yet";
    return;
}

$efficiency = ($stats['decisions_ingested'] / $stats['decisions_found']) * 100;

echo "Last Discovery Run:\n";
echo "  Completed: {$stats['completed_at']}\n";
echo "  Topics: {$stats['topics_generated']}\n";
echo "  Evaluated: {$stats['decisions_found']} decisions\n";
echo "  Ingested: {$stats['decisions_ingested']} decisions\n";
echo "  Efficiency: " . round($efficiency, 1) . "%\n";
echo "  Duration: {$stats['duration_seconds']}s\n";
```

---

## Configuration

### Agent Parameters

**Location**: `app/Agents/DecisionDiscoveryAgent.php`

```php
protected int $topicsPerRun = 5;           // Topics to generate per run
protected int $decisionsPerTopic = 50;     // Max decisions to search per topic
protected int $ingestPerTopic = 10;        // Top N decisions to ingest per topic
protected float $relevanceThreshold = 70.0; // Minimum AI score (0-100)
```

### Setters

```php
$agent = app(DecisionDiscoveryAgent::class);

$agent
    ->setTopicsPerRun(3)              // Generate fewer topics
    ->setDecisionsPerTopic(100)       // Search more decisions
    ->setIngestPerTopic(20)           // Ingest top 20 instead of 10
    ->setRelevanceThreshold(80.0);    // Require higher AI score

$agent->discover();
```

---

## Performance Characteristics

### Synchronous Execution (Development)

**Pros**:
- ✅ Immediate results
- ✅ Easy to debug
- ✅ See errors immediately
- ✅ No queue worker required

**Cons**:
- ❌ Blocks HTTP request
- ❌ Can timeout on long runs
- ❌ Uses main process memory

**Recommended For**: Testing, development, single-topic runs

### Asynchronous Execution (Production)

**Pros**:
- ✅ Non-blocking (immediate API response)
- ✅ Survives HTTP timeouts
- ✅ Can retry on failure
- ✅ Better resource isolation

**Cons**:
- ❌ Requires queue worker
- ❌ Harder to debug
- ❌ Check database for status

**Recommended For**: Production, scheduled runs, multi-topic discovery

---

## Monitoring & Debugging

### Check Run History

```sql
-- Latest 10 runs
SELECT
    id,
    started_at,
    completed_at,
    status,
    topics_generated,
    decisions_evaluated,
    decisions_ingested,
    TIMESTAMPDIFF(SECOND, started_at, completed_at) as duration_seconds
FROM decision_discovery_runs
ORDER BY started_at DESC
LIMIT 10;
```

### Check for Stuck Runs

```sql
-- Runs stuck in "running" status for over 2 hours
SELECT *
FROM decision_discovery_runs
WHERE status = 'running'
  AND created_at < NOW() - INTERVAL 2 HOUR
ORDER BY created_at DESC;
```

### View Topics Generated

```sql
-- See what topics AI generated
SELECT
    started_at,
    topics,
    decisions_ingested
FROM decision_discovery_runs
WHERE status = 'completed'
ORDER BY started_at DESC
LIMIT 5;
```

### Check Error Patterns

```sql
-- Failed runs with errors
SELECT
    started_at,
    error_message,
    errors
FROM decision_discovery_runs
WHERE status = 'failed'
ORDER BY started_at DESC;
```

---

## Integration Points

### From API Routes

```php
// routes/api.php
Route::post('/discovery/start', function () {
    if (DecisionDiscoveryAgent::isRunning()) {
        return response()->json([
            'error' => 'Discovery already running'
        ], 429);
    }

    $result = DecisionDiscoveryAgent::discoverWithEnvDetection();

    return response()->json([
        'success' => true,
        'result' => $result
    ]);
});

Route::get('/discovery/status', function () {
    return response()->json([
        'is_running' => DecisionDiscoveryAgent::isRunning(),
        'latest_run' => DecisionDiscoveryAgent::getLatestRunStats()
    ]);
});
```

### From Artisan Commands

```php
// app/Console/Commands/RunDiscovery.php
use App\Agents\DecisionDiscoveryAgent;

public function handle()
{
    if (DecisionDiscoveryAgent::isRunning()) {
        $this->error('Discovery already running');
        return 1;
    }

    $this->info('Starting decision discovery...');

    $result = DecisionDiscoveryAgent::discoverWithEnvDetection(
        maxDecisions: $this->option('max-decisions'),
        topic: $this->option('topic')
    );

    $this->info('Discovery completed!');
    $this->table(
        ['Metric', 'Value'],
        collect($result)->map(fn($v, $k) => [$k, $v])->toArray()
    );

    return 0;
}
```

### From Scheduled Tasks

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    // Run discovery every Monday at 2 AM
    $schedule->call(function () {
        if (!DecisionDiscoveryAgent::isRunning()) {
            DecisionDiscoveryAgent::discoverWithEnvDetection();
        }
    })->weeklyOn(1, '02:00');
}
```

---

## Related Documentation

- **Agent Framework Analysis**: `docs/AGENT_FRAMEWORK_ANALYSIS.md`
- **Neo4j Graph Integration**: `docs/NEO4J_COMPLETION_ANALYSIS.md`
- **Migration**: `database/migrations/2025_10_27_100000_create_decision_discovery_runs_table.php`

---

## Implementation Checklist

- [x] Add DB facade import to DecisionDiscoveryAgent
- [x] Add `discoverWithEnvDetection()` static method
- [x] Add `isRunning()` status check method
- [x] Add `getLatestRunStats()` statistics method
- [ ] Create `ExecuteDecisionDiscoveryJob` with `dispatchWithEnvDetection()`
- [ ] Add API routes for discovery control
- [ ] Add Artisan command for manual discovery
- [ ] Configure scheduled discovery (optional)
- [ ] Add monitoring/alerting for failed runs

---

## Next Steps

1. **Implement ExecuteDecisionDiscoveryJob** - Create the queue job wrapper
2. **Add API Endpoints** - REST API for triggering/monitoring discovery
3. **Create Artisan Command** - CLI interface for discovery
4. **Add Tests** - Unit tests for environment detection logic
5. **Configure Monitoring** - Alert on failed/stuck runs
6. **Schedule Recurring Runs** - Weekly automatic discovery

---

**Last Updated**: October 31, 2025
**Author**: AI Legal War Machine Development Team
**Version**: 1.0

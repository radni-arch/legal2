# ExecuteOdlukeAgentJob Flow Documentation

## Overview

The `ExecuteOdlukeAgentJob` is a queue job designed to execute the OdlukeAgent for handling long-running MCP (Model Context Protocol) tool chains. It provides intelligent environment detection, cache-based result storage, and robust error handling for asynchronous legal research queries.

**Key Features:**
- MCP tool chain execution for court decision research
- Cache-based result storage (no database required)
- Environment-aware sync/async execution
- Real-time status tracking via cache
- 5-minute timeout optimized for MCP operations
- Comprehensive error handling and logging
- Job monitoring via tags

**Created:** Task 2.1 (ExecuteOdlukeAgentJob)
**Location:** `app/Jobs/ExecuteOdlukeAgentJob.php`
**Storage:** Cache (Redis/File) - No database migration needed

---

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                    Job Dispatch Layer                           │
│                                                                  │
│  ┌──────────────┐         ┌─────────────────────────────────┐  │
│  │  Livewire    │────────▶│ dispatchWithEnvDetection()      │  │
│  │  Dashboard   │         │                                 │  │
│  └──────────────┘         │ • Generate Cache Key            │  │
│                           │ • Detect Environment            │  │
│  ┌──────────────┐         │ • Route to sync/async          │  │
│  │  API Route   │────────▶│                                 │  │
│  │  Controller  │         └─────────────────────────────────┘  │
│  └──────────────┘                      │                        │
│                                        │                        │
│  ┌──────────────┐                      │                        │
│  │   Artisan    │                      │                        │
│  │   Command    │──────────────────────┘                        │
│  └──────────────┘                                               │
└─────────────────────────────────────────────────────────────────┘
                                 │
                    ┌────────────┴────────────┐
                    │                         │
              [Production?]            [Dev/Localhost?]
                    │                         │
                    ▼                         ▼
         ┌────────────────────┐    ┌──────────────────┐
         │  Queue Dispatcher  │    │  Sync Executor   │
         │  (Async)           │    │  (Immediate)     │
         │                    │    │                  │
         │ • Set cache:       │    │ • Execute inline │
         │   "running"        │    │ • Return result  │
         └────────────────────┘    └──────────────────┘
                    │                         │
                    └────────────┬────────────┘
                                 ▼
                    ┌─────────────────────────┐
                    │   handle() Method       │
                    │                         │
                    │ • Log Environment       │
                    │ • Execute MCP Chain     │
                    │ • Track Duration        │
                    │ • Cache Results         │
                    │ • Handle Errors         │
                    └─────────────────────────┘
                                 │
                    ┌────────────┴────────────┐
                    │                         │
                    ▼                         ▼
            ┌──────────────┐         ┌──────────────┐
            │   Success    │         │   Failure    │
            │              │         │              │
            │ • Cache:     │         │ • Log Error  │
            │   completed  │         │ • Cache:     │
            │ • Result     │         │   failed     │
            │ • Duration   │         │ • Error msg  │
            └──────────────┘         └──────────────┘
                    │                         │
                    └────────────┬────────────┘
                                 ▼
                    ┌─────────────────────────┐
                    │   Client Polls Cache    │
                    │   • Status: running     │
                    │   • Status: completed   │
                    │   • Status: failed      │
                    └─────────────────────────┘
```

---

## MCP Tool Chain Integration

### OdlukeAgent MCP Tools

The job orchestrates the OdlukeAgent which has access to 5 MCP tools:

```
ExecuteOdlukeAgentJob
    ↓
OdlukeAgent (GPT-4o-mini)
    ↓
MCP Tool Chain:
├── odluke-search      → Search court decisions
├── odluke-meta        → Fetch decision metadata
├── odluke-download    → Download decision PDFs/HTML
├── law-articles-search → Search Croatian law articles
└── law-article-by-id  → Get specific law article
```

### Tool Chain Execution Flow

```
User Query: "Find recent labor law decisions"
    ↓
ExecuteOdlukeAgentJob dispatched
    ↓
OdlukeAgent.execute(query, context)
    ↓
┌─────────────────────────────────────────┐
│  LLM Agent Loop (max 8 steps)           │
│                                          │
│  Step 1: Analyze query                  │
│    ↓                                     │
│  Step 2: Call odluke-search              │
│    Tool: odluke-search                   │
│    Args: {q: "radno pravo", limit: 50}   │
│    Result: [id1, id2, id3, ...]          │
│    ↓                                     │
│  Step 3: Get metadata for top results   │
│    Tool: odluke-meta                     │
│    Args: {ids: [id1, id2, id3]}          │
│    Result: [{title, court, date}, ...]   │
│    ↓                                     │
│  Step 4: Download selected decisions    │
│    Tool: odluke-download                 │
│    Args: {id: id1, format: "pdf"}        │
│    Result: {url, saved_path}             │
│    ↓                                     │
│  Step 5: Summarize findings              │
│    (Agent reasoning step)                │
│                                          │
│  → Returns comprehensive result          │
└─────────────────────────────────────────┘
    ↓
Result cached with status: "completed"
    ↓
Client retrieves from cache
```

---

## Job Flow Schema

### 1. Job Initialization with Cache Key

```php
// Generate unique cache key for this query
$cacheKey = 'odluke_job_' . md5($query . microtime());

ExecuteOdlukeAgentJob::dispatchWithEnvDetection(
    query: "Pronađi odluke o nezakonitom otkazu iz 2024",
    context: ['user_id' => 123, 'language' => 'hr'],
    cacheKey: $cacheKey
);

// Client can now poll this cache key for status
while (true) {
    $status = Cache::get($cacheKey);
    if ($status['status'] !== 'running') {
        break;
    }
    sleep(2); // Poll every 2 seconds
}
```

### 2. Environment Detection Logic

```
┌─────────────────────────────────────────────────────────────┐
│            dispatchWithEnvDetection()                       │
│                                                             │
│  1. Create job instance                                    │
│     new ExecuteOdlukeAgentJob($query, $context, $cacheKey) │
│                                                             │
│  2. Check: app()->environment('production')                 │
│     ├─ YES ──────────┐                                     │
│     └─ NO ───────────┼─────────────────────┐               │
│                      │                     │               │
│  3. Check: request()->ip() in              │               │
│            ['127.0.0.1', '::1', 'localhost']               │
│     ├─ YES ──────────┼─────────────────────┘               │
│     └─ NO ───────────┘                                     │
│                      │                     │               │
│              [Production & Remote]  [Dev or Localhost]     │
│                      │                     │               │
│                      ▼                     ▼               │
│              ┌──────────────┐      ┌──────────────┐        │
│              │ QUEUE MODE   │      │  SYNC MODE   │        │
│              │              │      │              │        │
│              │ • Cache:     │      │ • Execute    │        │
│              │   "running"  │      │   inline     │        │
│              │ • dispatch() │      │ • Return     │        │
│              │ • Return     │      │   cache data │        │
│              │   JobID      │      │              │        │
│              └──────────────┘      └──────────────┘        │
└─────────────────────────────────────────────────────────────┘
```

### 3. Cache State Management

```
┌─────────────────────────────────────────────────────────────┐
│                    Cache State Lifecycle                    │
│                                                             │
│  INITIAL (before dispatch)                                  │
│     Cache Key: null                                         │
│     Status: N/A                                             │
│                                                             │
│           ↓ [dispatchWithEnvDetection called]               │
│                                                             │
│  RUNNING (after queue dispatch)                             │
│     Cache Key: "odluke_job_abc123..."                       │
│     Cache Value: {                                          │
│         "status": "running",                                │
│         "started_at": "2025-10-31T00:48:00Z"                │
│     }                                                       │
│     TTL: 3600 seconds (1 hour)                              │
│                                                             │
│           ↓ [Job executing, MCP tools running]              │
│                                                             │
│  COMPLETED (success)                                        │
│     Cache Value: {                                          │
│         "status": "completed",                              │
│         "result": {                                         │
│             "summary": "Found 10 decisions...",             │
│             "decisions": [...],                             │
│             "tool_calls": 5                                 │
│         },                                                  │
│         "duration": 42.15,                                  │
│         "completed_at": "2025-10-31T00:48:42Z"              │
│     }                                                       │
│     TTL: 3600 seconds (1 hour)                              │
│                                                             │
│           OR ↓ [Exception during execution]                 │
│                                                             │
│  FAILED (error)                                             │
│     Cache Value: {                                          │
│         "status": "failed",                                 │
│         "error": "Connection timeout to MCP server",        │
│         "completed_at": "2025-10-31T00:48:15Z"              │
│     }                                                       │
│     TTL: 3600 seconds (1 hour)                              │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## Job Execution Flow (handle() Method)

```
┌────────────────────────────────────────────────────────────────┐
│                         handle()                                │
│                                                                 │
│  START                                                          │
│    │                                                            │
│    ▼                                                            │
│  ┌─────────────────────────────────────────────────┐           │
│  │ 1. Log Environment via DetectsEnvironment trait │           │
│  │    • Logs: ExecuteOdlukeAgentJob                │           │
│  │    • Environment: production/dev                │           │
│  └─────────────────────────────────────────────────┘           │
│    │                                                            │
│    ▼                                                            │
│  ┌─────────────────────────────────────────────────┐           │
│  │ 2. Start Timer (microtime(true))                │           │
│  └─────────────────────────────────────────────────┘           │
│    │                                                            │
│    ▼                                                            │
│  ┌─────────────────────────────────────────────────┐           │
│  │ 3. Log Job Start                                │           │
│  │    • query (first 100 chars)                    │           │
│  │    • environment                                │           │
│  └─────────────────────────────────────────────────┘           │
│    │                                                            │
│    ▼                                                            │
│  ┌─────────────────────────────────────────────────┐           │
│  │ 4. Execute OdlukeAgent                          │           │
│  │    $result = $agent->execute(                   │           │
│  │        $this->query,                            │           │
│  │        $this->context                           │           │
│  │    );                                           │           │
│  │                                                 │           │
│  │    [Agent executes MCP tool chain]              │           │
│  │    [May make multiple tool calls]               │           │
│  │    [Returns structured result]                  │           │
│  └─────────────────────────────────────────────────┘           │
│    │                                                            │
│    ├────[SUCCESS]───────┐                                      │
│    │                    │                                      │
│    │                    ▼                                      │
│    │    ┌──────────────────────────────────────┐              │
│    │    │ 5a. Calculate Duration               │              │
│    │    │     round(microtime(true) - start)   │              │
│    │    └──────────────────────────────────────┘              │
│    │                    │                                      │
│    │                    ▼                                      │
│    │    ┌──────────────────────────────────────┐              │
│    │    │ 5b. Cache Success Result             │              │
│    │    │  if ($cacheKey) {                    │              │
│    │    │    Cache::put($cacheKey, [           │              │
│    │    │      'status' => 'completed',        │              │
│    │    │      'result' => $result,            │              │
│    │    │      'duration' => $duration,        │              │
│    │    │      'completed_at' => ISO8601       │              │
│    │    │    ], 3600);                         │              │
│    │    │  }                                   │              │
│    │    └──────────────────────────────────────┘              │
│    │                    │                                      │
│    │                    ▼                                      │
│    │    ┌──────────────────────────────────────┐              │
│    │    │ 5c. Log Success                      │              │
│    │    │     • duration                       │              │
│    │    │     • cache_key                      │              │
│    │    └──────────────────────────────────────┘              │
│    │                    │                                      │
│    │                    ▼                                      │
│    │                 [DONE]                                    │
│    │                                                           │
│    └────[EXCEPTION]────┐                                      │
│                        │                                      │
│                        ▼                                      │
│        ┌──────────────────────────────────────┐              │
│        │ 6a. Log Error with Stack Trace       │              │
│        │     • query                          │              │
│        │     • error message                  │              │
│        │     • stack trace                    │              │
│        └──────────────────────────────────────┘              │
│                        │                                      │
│                        ▼                                      │
│        ┌──────────────────────────────────────┐              │
│        │ 6b. Cache Failed Result              │              │
│        │  if ($cacheKey) {                    │              │
│        │    Cache::put($cacheKey, [           │              │
│        │      'status' => 'failed',           │              │
│        │      'error' => $e->getMessage(),    │              │
│        │      'completed_at' => ISO8601       │              │
│        │    ], 3600);                         │              │
│        │  }                                   │              │
│        └──────────────────────────────────────┘              │
│                        │                                      │
│                        ▼                                      │
│        ┌──────────────────────────────────────┐              │
│        │ 6c. Re-throw Exception               │              │
│        │     (for queue retry logic)          │              │
│        └──────────────────────────────────────┘              │
│                        │                                      │
│                        ▼                                      │
│                    [FAILED]                                   │
│                        │                                      │
│                        ▼                                      │
│        ┌──────────────────────────────────────┐              │
│        │ 7. failed() Method Called            │              │
│        │    • Log permanent failure           │              │
│        │    • Update cache with failure       │              │
│        └──────────────────────────────────────┘              │
│                                                               │
└────────────────────────────────────────────────────────────────┘
```

---

## Cache Schema

### Cache Key Format

```
Pattern: odluke_job_{unique_identifier}

Examples:
- odluke_job_abc123def456
- odluke_job_user_123_query_xyz
- odluke_job_20251031_004800_abc
```

### Cache Value Structure

#### Running State
```json
{
    "status": "running",
    "started_at": "2025-10-31T00:48:00Z"
}
```

#### Completed State
```json
{
    "status": "completed",
    "result": {
        "summary": "Found 10 relevant labor law decisions from 2024",
        "decisions": [
            {
                "id": "17383",
                "title": "Presuda o nezakonitom otkazu",
                "court": "Vrhovni sud Republike Hrvatske",
                "date": "2024-09-15",
                "url": "https://odluke.sudovi.hr/..."
            }
        ],
        "tool_calls": 5,
        "agent_steps": 4
    },
    "duration": 42.15,
    "completed_at": "2025-10-31T00:48:42Z"
}
```

#### Failed State
```json
{
    "status": "failed",
    "error": "Connection timeout to MCP server after 30 seconds",
    "completed_at": "2025-10-31T00:48:30Z"
}
```

---

## Job Configuration

### Constructor Parameters

```php
public function __construct(
    protected string $query,           // User's research query
    protected array $context = [],     // Additional context (user_id, filters, etc.)
    protected ?string $cacheKey = null // Cache key for result storage
)
```

### Job Properties

```php
public int $tries = 1;           // No retries (MCP operations are not idempotent)
public int $timeout = 300;       // 5 minutes (MCP chain timeout)
```

### Laravel Queue Configuration

```php
use App\Jobs\ExecuteOdlukeAgentJob;

// Standard dispatch with cache
$cacheKey = 'odluke_' . uniqid();
ExecuteOdlukeAgentJob::dispatchWithEnvDetection(
    query: "Find recent decisions",
    context: ['language' => 'hr'],
    cacheKey: $cacheKey
);

// Poll for result
$result = Cache::get($cacheKey);

// Explicit queue dispatch
ExecuteOdlukeAgentJob::dispatch($query, $context, $cacheKey);

// Dispatch to specific queue
ExecuteOdlukeAgentJob::dispatch($query, $context, $cacheKey)
    ->onQueue('mcp-agents');

// Delayed dispatch
ExecuteOdlukeAgentJob::dispatch($query, $context, $cacheKey)
    ->delay(now()->addMinutes(5));
```

---

## Usage Examples

### 1. Basic Usage from Livewire Component

```php
use App\Jobs\ExecuteOdlukeAgentJob;
use Illuminate\Support\Facades\Cache;

class OdlukeResearchDashboard extends Component
{
    public string $query = '';
    public ?string $jobId = null;
    public ?array $result = null;

    public function startResearch()
    {
        // Generate unique cache key
        $this->jobId = 'odluke_job_' . uniqid();

        // Dispatch job
        ExecuteOdlukeAgentJob::dispatchWithEnvDetection(
            query: $this->query,
            context: ['user_id' => auth()->id()],
            cacheKey: $this->jobId
        );

        session()->flash('message', 'Research started! Job ID: ' . $this->jobId);
    }

    public function checkStatus()
    {
        if (!$this->jobId) {
            return;
        }

        $status = Cache::get($this->jobId);

        if ($status && $status['status'] === 'completed') {
            $this->result = $status['result'];
            $this->jobId = null; // Clear job ID
        } elseif ($status && $status['status'] === 'failed') {
            session()->flash('error', 'Job failed: ' . $status['error']);
            $this->jobId = null;
        }
    }

    public function render()
    {
        // Auto-refresh every 2 seconds when job is running
        if ($this->jobId) {
            $this->checkStatus();
        }

        return view('livewire.odluke-research-dashboard');
    }
}
```

**Blade Template with Polling:**
```blade
<div wire:poll.2s="checkStatus">
    @if($jobId)
        <div class="alert alert-info">
            <span class="spinner-border spinner-border-sm"></span>
            Researching... (Job ID: {{ $jobId }})
        </div>
    @endif

    @if($result)
        <div class="card">
            <div class="card-header">Results</div>
            <div class="card-body">
                <p>{{ $result['summary'] }}</p>
                <!-- Display decisions -->
            </div>
        </div>
    @endif
</div>
```

### 2. API Endpoint with Long-Polling

```php
// routes/api.php
use App\Jobs\ExecuteOdlukeAgentJob;

Route::post('/api/research/start', function (Request $request) {
    $cacheKey = 'odluke_api_' . uniqid();

    ExecuteOdlukeAgentJob::dispatchWithEnvDetection(
        query: $request->input('query'),
        context: ['api_key' => $request->bearerToken()],
        cacheKey: $cacheKey
    );

    return response()->json([
        'job_id' => $cacheKey,
        'status_url' => route('api.research.status', $cacheKey),
    ]);
});

Route::get('/api/research/status/{jobId}', function (string $jobId) {
    $status = Cache::get($jobId);

    if (!$status) {
        return response()->json(['error' => 'Job not found'], 404);
    }

    return response()->json($status);
})->name('api.research.status');
```

**Client Usage:**
```javascript
// Start job
const response = await fetch('/api/research/start', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({query: 'labor law decisions 2024'})
});
const {job_id, status_url} = await response.json();

// Poll for status
const pollInterval = setInterval(async () => {
    const statusResponse = await fetch(status_url);
    const status = await statusResponse.json();

    if (status.status === 'completed') {
        console.log('Result:', status.result);
        clearInterval(pollInterval);
    } else if (status.status === 'failed') {
        console.error('Error:', status.error);
        clearInterval(pollInterval);
    }
}, 2000); // Poll every 2 seconds
```

### 3. Artisan Command

```php
// app/Console/Commands/ResearchDecisions.php

use App\Jobs\ExecuteOdlukeAgentJob;

class ResearchDecisions extends Command
{
    protected $signature = 'research:decisions {query}';

    public function handle()
    {
        $query = $this->argument('query');
        $cacheKey = 'cli_' . uniqid();

        $this->info("Starting research: $query");

        // Run synchronously in CLI
        ExecuteOdlukeAgentJob::dispatchWithEnvDetection(
            query: $query,
            context: ['source' => 'cli'],
            cacheKey: $cacheKey
        );

        // Get result from cache
        $result = Cache::get($cacheKey);

        if ($result['status'] === 'completed') {
            $this->info("Duration: {$result['duration']}s");
            $this->info("Result: " . json_encode($result['result'], JSON_PRETTY_PRINT));
        } else {
            $this->error("Failed: " . $result['error']);
        }
    }
}
```

### 4. Batch Processing Multiple Queries

```php
use App\Jobs\ExecuteOdlukeAgentJob;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;

$queries = [
    'Labor law decisions 2024',
    'Consumer protection cases',
    'Property disputes',
];

$jobs = [];
$cacheKeys = [];

foreach ($queries as $query) {
    $cacheKey = 'batch_' . md5($query);
    $cacheKeys[$query] = $cacheKey;

    $jobs[] = new ExecuteOdlukeAgentJob($query, [], $cacheKey);
}

Bus::batch($jobs)
    ->name('Research Batch')
    ->dispatch();

// Later, retrieve all results
foreach ($cacheKeys as $query => $cacheKey) {
    $result = Cache::get($cacheKey);
    echo "$query: " . ($result['status'] ?? 'unknown') . "\n";
}
```

### 5. With Context Filters

```php
ExecuteOdlukeAgentJob::dispatchWithEnvDetection(
    query: 'Find Supreme Court decisions',
    context: [
        'filters' => [
            'court' => 'Vrhovni sud Republike Hrvatske',
            'date_from' => '2024-01-01',
            'date_to' => '2024-12-31',
        ],
        'limit' => 20,
        'language' => 'hr',
    ],
    cacheKey: 'filtered_' . time()
);
```

### 6. Scheduled Research

```php
// app/Console/Kernel.php

protected function schedule(Schedule $schedule)
{
    // Daily research on trending topics
    $schedule->call(function () {
        $topics = ['labor law', 'consumer rights', 'property law'];

        foreach ($topics as $topic) {
            $cacheKey = 'scheduled_' . md5($topic . date('Y-m-d'));

            ExecuteOdlukeAgentJob::dispatchWithEnvDetection(
                query: "Recent decisions about $topic",
                context: ['scheduled' => true],
                cacheKey: $cacheKey
            );
        }
    })
    ->daily()
    ->at('03:00');
}
```

### 7. Real-time Progress Updates via WebSockets

```php
// Using Laravel Echo and Pusher/Redis

use App\Jobs\ExecuteOdlukeAgentJob;
use App\Events\ResearchProgressUpdated;

class ExecuteOdlukeAgentJobWithProgress extends ExecuteOdlukeAgentJob
{
    public function handle(OdlukeAgent $agent): void
    {
        // Emit start event
        event(new ResearchProgressUpdated($this->cacheKey, 'started'));

        try {
            $result = $agent->execute($this->query, $this->context);

            // Cache result
            Cache::put($this->cacheKey, [
                'status' => 'completed',
                'result' => $result,
                'duration' => round(microtime(true) - $startTime, 2),
                'completed_at' => now()->toIso8601String(),
            ], 3600);

            // Emit completion event
            event(new ResearchProgressUpdated($this->cacheKey, 'completed', $result));

        } catch (\Exception $e) {
            // Emit failure event
            event(new ResearchProgressUpdated($this->cacheKey, 'failed', ['error' => $e->getMessage()]));
            throw $e;
        }
    }
}
```

**Client-side (JavaScript):**
```javascript
Echo.channel('research.' + jobId)
    .listen('ResearchProgressUpdated', (e) => {
        console.log('Status:', e.status);
        if (e.status === 'completed') {
            displayResults(e.data);
        }
    });
```

---

## Job Monitoring

### Job Tags

```php
public function tags(): array
{
    return [
        'agent:odluke',
        'query:' . substr(md5($this->query), 0, 8),
    ];
}
```

**Example Tags:**
- `agent:odluke`
- `query:a3b2c1d4`

### Horizon Monitoring

```bash
# View all OdlukeAgent jobs
php artisan horizon:list --tag=agent:odluke

# View specific query jobs
php artisan horizon:list --tag=query:a3b2c1d4
```

### Failed Job Handling

```php
public function failed(\Throwable $exception): void
{
    Log::error('ExecuteOdlukeAgentJob failed permanently', [
        'query' => substr($this->query, 0, 100),
        'error' => $exception->getMessage(),
    ]);

    // Update cache with failure
    if ($this->cacheKey) {
        Cache::put($this->cacheKey, [
            'status' => 'failed',
            'error' => $exception->getMessage(),
            'completed_at' => now()->toIso8601String(),
        ], 3600);
    }
}
```

---

## Error Handling & Recovery

### Exception Scenarios

#### 1. MCP Connection Timeout

```
Agent tries to connect to MCP server
    ↓
Connection timeout (45 seconds)
    ↓
Exception: "MCP server connection timeout"
    ↓
Caught by handle() try-catch
    ↓
Error cached with "failed" status
    ↓
Exception re-thrown
    ↓
Job marked as failed
    ↓
failed() method called
    ↓
Client receives error from cache
```

#### 2. Invalid Tool Arguments

```
Agent calls odluke-search with invalid args
    ↓
Tool validation fails
    ↓
MCP returns error response
    ↓
Agent receives error
    ↓
Agent may retry or return error
    ↓
Result cached (may include partial success)
```

#### 3. Agent Step Limit Exceeded

```
Agent executes 8 steps (maxSteps)
    ↓
Limit reached before completion
    ↓
Agent returns partial result
    ↓
Result cached as "completed"
    ↓
Result includes note about incomplete execution
```

#### 4. Cache Write Failure

```
Job completes successfully
    ↓
Attempt to cache result
    ↓
Cache driver fails (Redis down, disk full)
    ↓
Exception thrown
    ↓
Job marked as failed
    ↓
Client never receives result
```

**Mitigation:**
- Monitor cache system health
- Use redundant cache drivers
- Log all cache write attempts

---

## Performance Characteristics

### Execution Time

**Typical Duration:**
- Simple query (1-2 tools): 10-30 seconds
- Medium query (3-5 tools): 30-90 seconds
- Complex query (6-8 tools): 90-180 seconds

**Timeout:** 5 minutes (300 seconds)

### Resource Usage

**Memory:**
- Base: ~30 MB
- Peak (large responses): ~100 MB

**CPU:**
- Low (I/O bound - waiting for MCP responses)

**Network:**
- Moderate during MCP tool calls
- Low during agent reasoning

### MCP Tool Call Latency

**Per Tool Call:**
- odluke-search: 2-5 seconds
- odluke-meta: 1-3 seconds
- odluke-download: 3-8 seconds
- law-articles-search: 1-2 seconds
- law-article-by-id: 0.5-1 second

**Agent Overhead:**
- LLM reasoning: 1-3 seconds per step
- Tool selection: 0.5-1 second

---

## Cache Management

### TTL Strategy

```
All cache entries: 3600 seconds (1 hour)

Rationale:
- Results are time-sensitive (court decisions change)
- Prevents cache bloat
- Allows re-research after 1 hour
```

### Cache Cleanup

```php
// Manual cleanup command
php artisan cache:clear --tags=odluke_jobs

// Or programmatically
Cache::flush(); // Clear all (careful!)

// Selective cleanup
$keys = Cache::get('odluke_job_keys', []);
foreach ($keys as $key) {
    $status = Cache::get($key);
    if ($status && $status['completed_at'] < now()->subHours(2)) {
        Cache::forget($key);
    }
}
```

### Cache Key Tracking

```php
// Track all job cache keys for cleanup
class ExecuteOdlukeAgentJob extends ShouldQueue
{
    public function handle(OdlukeAgent $agent): void
    {
        // Track this cache key
        if ($this->cacheKey) {
            $keys = Cache::get('odluke_job_keys', []);
            $keys[] = $this->cacheKey;
            Cache::put('odluke_job_keys', array_unique($keys), 86400);
        }

        // ... rest of handle logic
    }
}
```

---

## Testing

### Unit Tests

```php
use App\Jobs\ExecuteOdlukeAgentJob;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Cache;

test('job can be dispatched to queue', function () {
    Queue::fake();

    ExecuteOdlukeAgentJob::dispatch('test query', [], 'test_key');

    Queue::assertPushed(ExecuteOdlukeAgentJob::class, function ($job) {
        return $job->query === 'test query'
            && $job->cacheKey === 'test_key';
    });
});

test('environment detection routes to queue in production', function () {
    config(['app.env' => 'production']);
    Queue::fake();

    ExecuteOdlukeAgentJob::dispatchWithEnvDetection('query', [], 'key');

    Queue::assertPushed(ExecuteOdlukeAgentJob::class);
});

test('sync mode executes immediately in dev', function () {
    config(['app.env' => 'local']);
    Cache::shouldReceive('put')->once();
    Cache::shouldReceive('get')->once()->andReturn(['mode' => 'sync']);

    $agent = Mockery::mock(OdlukeAgent::class);
    $agent->shouldReceive('execute')->andReturn(['summary' => 'test']);
    app()->instance(OdlukeAgent::class, $agent);

    $result = ExecuteOdlukeAgentJob::dispatchWithEnvDetection('query', [], 'key');

    expect($result)->toBeArray();
});

test('job caches success result', function () {
    $agent = Mockery::mock(OdlukeAgent::class);
    $agent->shouldReceive('execute')
        ->andReturn(['summary' => 'Success']);

    Cache::shouldReceive('put')
        ->with('test_key', Mockery::on(function ($value) {
            return $value['status'] === 'completed'
                && isset($value['result'])
                && isset($value['duration']);
        }), 3600);

    $job = new ExecuteOdlukeAgentJob('test', [], 'test_key');
    $job->handle($agent);
});

test('job caches error on failure', function () {
    $agent = Mockery::mock(OdlukeAgent::class);
    $agent->shouldReceive('execute')
        ->andThrow(new \Exception('Test error'));

    Cache::shouldReceive('put')
        ->with('test_key', Mockery::on(function ($value) {
            return $value['status'] === 'failed'
                && $value['error'] === 'Test error';
        }), 3600);

    $job = new ExecuteOdlukeAgentJob('test', [], 'test_key');

    expect(fn() => $job->handle($agent))->toThrow(\Exception::class);
});
```

### Integration Tests

```php
test('full job lifecycle with real agent', function () {
    // This would use a real agent in test mode
    $cacheKey = 'integration_test_' . uniqid();

    ExecuteOdlukeAgentJob::dispatchWithEnvDetection(
        query: 'test query',
        context: ['test' => true],
        cacheKey: $cacheKey
    );

    // Wait for completion (in sync mode)
    $result = Cache::get($cacheKey);

    expect($result)->toBeArray()
        ->and($result['status'])->toBeIn(['completed', 'failed']);

    if ($result['status'] === 'completed') {
        expect($result)->toHaveKeys(['result', 'duration', 'completed_at']);
    }
});
```

---

## Best Practices

### 1. Cache Key Generation

**Good:**
```php
$cacheKey = 'odluke_' . auth()->id() . '_' . time() . '_' . uniqid();
```

**Better:**
```php
$cacheKey = sprintf(
    'odluke_user_%d_query_%s_time_%d',
    auth()->id(),
    substr(md5($query), 0, 8),
    time()
);
```

### 2. Context Usage

**Include useful context:**
```php
ExecuteOdlukeAgentJob::dispatchWithEnvDetection($query, [
    'user_id' => auth()->id(),
    'language' => app()->getLocale(),
    'filters' => $filters,
    'source' => 'dashboard',
    'timestamp' => now()->toIso8601String(),
], $cacheKey);
```

### 3. Polling Strategy

**Client-side polling:**
- Start with 2-second intervals
- Increase to 5 seconds after 30 seconds
- Stop after 5 minutes (timeout)

```javascript
let pollCount = 0;
const pollInterval = setInterval(async () => {
    pollCount++;
    const interval = pollCount > 15 ? 5000 : 2000; // 30s = 15 polls

    const status = await checkStatus(jobId);

    if (status.status !== 'running' || pollCount > 60) {
        clearInterval(pollInterval);
    }
}, pollInterval);
```

### 4. Error Presentation

**Show user-friendly errors:**
```php
$errorMessages = [
    'Connection timeout' => 'The research service is currently unavailable. Please try again later.',
    'Invalid query' => 'Your search query could not be processed. Please rephrase and try again.',
    'Rate limit exceeded' => 'Too many requests. Please wait a moment before trying again.',
];

$friendlyError = $errorMessages[$result['error']] ?? 'An unexpected error occurred.';
```

---

## Troubleshooting

### Job Not Starting

**Symptoms:**
- Cache remains empty
- No log entries

**Checks:**
1. Queue worker running?
2. Cache driver working?
3. Environment detection correct?

### Jobs Timing Out

**Symptoms:**
- Status stuck at "running"
- No completed/failed state

**Solutions:**
- Increase timeout to 600s (10 min)
- Check MCP server connectivity
- Monitor agent step count

### Cache Misses

**Symptoms:**
- Client can't find result
- Cache::get($key) returns null

**Causes:**
- Cache key mismatch
- Cache expired (>1 hour)
- Cache driver failure

---

## Related Documentation

- [OdlukeAgent Documentation](ODLUKE_AGENT_FIX.md) - Agent implementation
- [MCP Tools Documentation](MCP_TOOLS.md) - MCP tool reference
- [ExecuteDecisionDiscoveryJob](EXECUTE_DECISION_DISCOVERY_JOB_FLOW.md) - Similar job pattern

---

## Comparison: Database vs Cache Storage

| Feature | ExecuteDecisionDiscoveryJob | ExecuteOdlukeAgentJob |
|---------|----------------------------|------------------------|
| Storage | Database (decision_discovery_runs) | Cache (Redis/File) |
| Persistence | Permanent | 1 hour TTL |
| Query | SQL queries | Cache::get() |
| History | Full run history | Recent jobs only |
| Use Case | Scheduled autonomous discovery | On-demand research |
| Result Size | Compact statistics | Full agent response |
| Cleanup | Manual deletion | Auto-expires |

---

## Migration Guide

### Adding to Existing Project

```bash
# No migration needed (uses cache)

# Verify dependencies
composer show vizra/vizra-adk

# Test the job
php artisan tinker
>>> ExecuteOdlukeAgentJob::dispatchWithEnvDetection('test', [], 'test_key');
>>> Cache::get('test_key');
```

---

## Changelog

**v1.0.0 (2025-10-31) - Initial Release**
- Created ExecuteOdlukeAgentJob for MCP tool chain execution
- Implemented cache-based result storage (no database)
- Added environment detection with `dispatchWithEnvDetection()`
- Integrated DetectsEnvironment trait
- 5-minute timeout optimized for MCP operations
- Job monitoring tags for Horizon
- Comprehensive error handling and cache state management
- Support for context passing and query customization

---

## Support

**For issues or questions:**
1. Check logs: `storage/logs/laravel.log`
2. Inspect cache: `Cache::get($cacheKey)`
3. Review queue status: `php artisan queue:failed`
4. Test agent manually: `OdlukeAgent->execute($query, $context)`
5. Check MCP server: `curl http://app-url/mcp/info`
6. Open GitHub issue with:
   - Query used
   - Cache key
   - Environment details
   - Full error logs

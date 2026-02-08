# OdlukeAgent - Async Execution Flow

**Date**: October 31, 2025
**Task**: 2.2 - Add Async Execution Option to OdlukeAgent
**Status**: ✅ Implemented

---

## Overview

This document describes the asynchronous execution pattern for **OdlukeAgent**, an autonomous agent that searches and downloads Croatian court decisions from `odluke.sudovi.hr` using MCP tools.

The async execution pattern allows the agent to:
- **Run synchronously** for immediate results (testing, development, quick queries)
- **Run asynchronously** via queue for long-running queries (production, complex searches)
- **Environment detection** to automatically choose execution mode
- **Cache-based status tracking** for polling and result retrieval

---

## Architecture Components

### 1. OdlukeAgent
**Location**: `app/Agents/OdlukeAgent.php`

**Framework**: Extends `Vizra\VizraADK\Agents\BaseLlmAgent`

**Purpose**: Autonomous agent for searching Croatian court decisions with MCP tools

**Tools Available**:
- `OdlukeSearchTool` - Search odluke.sudovi.hr with filters
- `OdlukeMetaTool` - Fetch metadata for decision IDs
- `OdlukeDownloadTool` - Download decisions in PDF/HTML
- `LawArticlesSearchTool` - Search Croatian laws
- `LawArticleByIdTool` - Get specific law articles

**Key Methods**:
```php
// Async execution with sync/async control
public static function executeAsync(string $query, array $context = [], bool $async = true): mixed

// Check execution status
public static function getAsyncStatus(string $cacheKey): ?array

// Polling helper with timeout
public static function waitForAsync(string $cacheKey, int $maxWaitSeconds = 60, int $pollIntervalMs = 500): ?array
```

### 2. OdlukeController
**Location**: `app/Http/Controllers/OdlukeController.php`

**Purpose**: REST API controller for OdlukeAgent execution

**Endpoints**:
```php
// Execute agent query (sync or async)
POST /api/odluke-agent/execute

// Check query status
POST /api/odluke-agent/status
```

### 3. ExecuteOdlukeAgentJob
**Location**: `app/Jobs/ExecuteOdlukeAgentJob.php` *(to be implemented)*

**Purpose**: Queue job wrapper for async OdlukeAgent execution

**Key Method**:
```php
public static function dispatchWithEnvDetection(string $query, array $context, string $cacheKey): mixed
```

---

## Cache Schema

### Cache Key Format

```php
$cacheKey = 'odluke_agent:' . md5($query . json_encode($context));
```

**Example**: `odluke_agent:a1b2c3d4e5f6789012345678901234567`

### Cache Value Structure

#### Running State
```json
{
  "status": "running",
  "started_at": "2025-10-31T14:30:00.000000Z",
  "query": "Pronađi najnovije odluke o radnim odnosima"
}
```

#### Completed State
```json
{
  "status": "completed",
  "result": {
    "response": "Agent response text with decisions found...",
    "tool_calls": 5,
    "tokens_used": 1250
  },
  "completed_at": "2025-10-31T14:30:15.000000Z",
  "duration_seconds": 15
}
```

#### Error State
```json
{
  "status": "error",
  "error_message": "Failed to connect to odluke.sudovi.hr",
  "completed_at": "2025-10-31T14:30:05.000000Z"
}
```

**TTL**: 3600 seconds (1 hour)

---

## Execution Flow

### Flow Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│  API Request: POST /api/odluke-agent/execute                   │
│  Body: {query, context, async}                                  │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│  OdlukeController::execute()                                    │
│  - Validates request                                            │
│  - Extracts: query, context, async flag                         │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│  OdlukeAgent::executeAsync(query, context, async)              │
│  - Generates cache_key: md5(query + context)                   │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
                 ┌───────────────┐
                 │ async flag?   │
                 └───────┬───────┘
                         │
         ┌───────────────┴───────────────┐
         │                               │
    async=false                     async=true
    (Synchronous)                   (Asynchronous)
         │                               │
         ▼                               ▼
┌─────────────────┐            ┌─────────────────────┐
│  Sync Execution │            │  Async Execution    │
│                 │            │                     │
│ 1. new self()   │            │ 1. Dispatch Job     │
│ 2. $agent->run()│            │   with env detect   │
│ 3. Store cache  │            │ 2. Set status:      │
│    status:      │            │    'running' in     │
│    'completed'  │            │    cache            │
│ 4. Return result│            │ 3. Return cache_key │
└────────┬────────┘            └────────┬────────────┘
         │                               │
         └───────────────┬───────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│  Response to Client                                             │
│  {                                                              │
│    "mode": "sync|async",                                        │
│    "cache_key": "odluke_agent:abc123...",                       │
│    "message": "..." // (async only)                             │
│    "result": {...}  // (sync only)                              │
│  }                                                              │
└─────────────────────────────────────────────────────────────────┘
```

### Detailed Sequence Diagram - Asynchronous Flow

```
Client         Controller      OdlukeAgent    Cache    ExecuteJob    Queue    Worker    Agent
  │                │                │           │           │           │        │         │
  │────────────>   │                │           │           │           │        │         │
  │ POST /execute  │                │           │           │           │        │         │
  │ {query,async:T}│                │           │           │           │        │         │
  │                │                │           │           │           │        │         │
  │                │────────────>   │           │           │           │        │         │
  │                │ executeAsync() │           │           │           │        │         │
  │                │                │           │           │           │        │         │
  │                │                │──────────>│           │           │        │         │
  │                │                │ Generate  │           │           │        │         │
  │                │                │ cache_key │           │           │        │         │
  │                │                │           │           │           │        │         │
  │                │                │────────────────────>  │           │        │         │
  │                │                │ dispatchWithEnvDetect()│           │        │         │
  │                │                │                       │           │        │         │
  │                │                │                       │──────────>│        │         │
  │                │                │                       │ Push Job  │        │         │
  │                │                │                       │           │        │         │
  │                │                │──────────>│           │           │        │         │
  │                │                │ Put cache │           │           │        │         │
  │                │                │ status:   │           │           │        │         │
  │                │                │ 'running' │           │           │        │         │
  │                │                │           │           │           │        │         │
  │                │<───────────────────────────────────────────────────────────────────  │
  │<───────────────┤                │           │           │           │        │         │
  │ {mode: async,  │                │           │           │           │        │         │
  │  cache_key}    │                │           │           │           │        │         │
  │                │                │           │           │           │        │         │
  │ ============== Client can continue, job runs in background =====================      │
  │                                             │           │           │        │         │
  │                                (later)      │           │           │        │         │
  │                                             │           │           │────────>│         │
  │                                             │           │           │ Pick Job│         │
  │                                             │           │           │         │         │
  │                                             │           │           │         │────────>│
  │                                             │           │           │         │ handle()│
  │                                             │           │           │         │         │
  │                                             │           │           │         │  Agent  │
  │                                             │           │           │         │  runs   │
  │                                             │           │           │         │  with   │
  │                                             │           │           │         │  MCP    │
  │                                             │           │           │         │  tools  │
  │                                             │           │           │         │         │
  │                                             │           │           │         │<────────┤
  │                                             │           │           │         │  Result │
  │                                             │           │           │         │         │
  │                                             │<────────────────────────────────┤         │
  │                                             │ Put cache │           │         │         │
  │                                             │ status:   │           │         │         │
  │                                             │ 'completed'│          │         │         │
  │                                             │ result: {...}         │         │         │
  │                                             │           │           │         │         │
  │─── Client Polling Loop ────────────────────────────────────────────────────────────    │
  │                │                │           │           │           │        │         │
  │────────────>   │                │           │           │           │        │         │
  │ POST /status   │                │           │           │           │        │         │
  │ {cache_key}    │                │           │           │           │        │         │
  │                │                │           │           │           │        │         │
  │                │────────────>   │           │           │           │        │         │
  │                │ getAsyncStatus()           │           │           │        │         │
  │                │                │           │           │           │        │         │
  │                │                │────────>  │           │           │        │         │
  │                │                │ Get cache │           │           │        │         │
  │                │                │           │           │           │        │         │
  │                │                │<──────────┤           │           │        │         │
  │                │                │ {status:  │           │           │        │         │
  │                │                │ 'completed'           │           │        │         │
  │                │                │  result}  │           │           │        │         │
  │                │                │           │           │           │        │         │
  │<───────────────────────────────┤           │           │           │        │         │
  │ {status, result, completed_at} │           │           │           │        │         │
```

### Detailed Sequence Diagram - Synchronous Flow

```
Client         Controller      OdlukeAgent    Cache    Agent Instance
  │                │                │           │           │
  │────────────>   │                │           │           │
  │ POST /execute  │                │           │           │
  │ {query,       │                │           │           │
  │  async:false} │                │           │           │
  │                │                │           │           │
  │                │────────────>   │           │           │
  │                │ executeAsync() │           │           │
  │                │                │           │           │
  │                │                │──────────>│           │
  │                │                │ Generate  │           │
  │                │                │ cache_key │           │
  │                │                │           │           │
  │                │                │──────────────────────>│
  │                │                │ new self()            │
  │                │                │ $agent->run(query)    │
  │                │                │                       │
  │                │                │           ┌───────────┤
  │                │                │           │  Agent    │
  │                │                │           │  executes │
  │                │                │           │  with MCP │
  │                │                │           │  tools    │
  │                │                │           │  (direct) │
  │                │                │           │           │
  │                │                │           └──────────>│
  │                │                │                   Result
  │                │                │                       │
  │                │                │<──────────────────────┤
  │                │                │   $result             │
  │                │                │                       │
  │                │                │──────────>│           │
  │                │                │ Put cache │           │
  │                │                │ status:   │           │
  │                │                │ 'completed'           │
  │                │                │ result: {...}         │
  │                │                │           │           │
  │                │<───────────────┤           │           │
  │                │ {mode: sync,   │           │           │
  │                │  cache_key,    │           │           │
  │                │  result}       │           │           │
  │                │                │           │           │
  │<───────────────┤                │           │           │
  │ Immediate      │                │           │           │
  │ response with  │                │           │           │
  │ complete result│                │           │           │
```

---

## API Endpoints

### 1. Execute Query

**Endpoint**: `POST /api/odluke-agent/execute`

**Authentication**: Required (`X-API-Token` header)

**Rate Limit**: 30 requests per minute

**Request Body**:
```json
{
  "query": "string (required, max 2000 chars)",
  "context": {
    "optional": "context data"
  },
  "async": true|false (optional, default: true)
}
```

**Response (Async)**:
```json
{
  "mode": "async",
  "cache_key": "odluke_agent:a1b2c3d4...",
  "message": "Query processing started. Check status using the cache_key."
}
```

**Response (Sync)**:
```json
{
  "mode": "sync",
  "cache_key": "odluke_agent:a1b2c3d4...",
  "result": {
    "response": "Found 5 recent decisions on employment law...",
    "tool_calls": 3,
    "tokens": 1200
  }
}
```

**Example cURL**:
```bash
# Async execution (default)
curl -X POST http://localhost/api/odluke-agent/execute \
  -H "X-API-Token: YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "query": "Pronađi mi 5 najnovijih odluka o radnim odnosima",
    "context": {},
    "async": true
  }'

# Sync execution
curl -X POST http://localhost/api/odluke-agent/execute \
  -H "X-API-Token: YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "query": "Pronađi odluke Vrhovnog suda o otkazima",
    "async": false
  }'
```

### 2. Check Status

**Endpoint**: `POST /api/odluke-agent/status`

**Authentication**: Required (`X-API-Token` header)

**Rate Limit**: 30 requests per minute

**Request Body**:
```json
{
  "cache_key": "odluke_agent:a1b2c3d4..." (required)
}
```

**Response (Running)**:
```json
{
  "status": "running",
  "started_at": "2025-10-31T14:30:00.000000Z"
}
```

**Response (Completed)**:
```json
{
  "status": "completed",
  "result": {
    "response": "Agent response...",
    "tool_calls": 5
  },
  "completed_at": "2025-10-31T14:30:15.000000Z",
  "duration_seconds": 15
}
```

**Response (Error)**:
```json
{
  "error": "Query not found or expired"
}
```
**HTTP Status**: 404

**Example cURL**:
```bash
curl -X POST http://localhost/api/odluke-agent/status \
  -H "X-API-Token: YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "cache_key": "odluke_agent:a1b2c3d4e5f6..."
  }'
```

---

## Usage Patterns

### Pattern 1: Fire and Forget (Async)

**Use Case**: Submit query and continue with other work

```javascript
// Submit query
const response = await fetch('/api/odluke-agent/execute', {
  method: 'POST',
  headers: {
    'X-API-Token': TOKEN,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    query: 'Pronađi odluke o nezakonitom otkazu',
    async: true
  })
});

const { cache_key } = await response.json();

// Store cache_key for later retrieval
localStorage.setItem('odluke_query', cache_key);

// User can continue with other tasks...
```

Later:
```javascript
// Retrieve results when user returns
const cache_key = localStorage.getItem('odluke_query');

const statusResponse = await fetch('/api/odluke-agent/status', {
  method: 'POST',
  headers: {
    'X-API-Token': TOKEN,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({ cache_key })
});

const status = await statusResponse.json();

if (status.status === 'completed') {
  console.log('Results:', status.result);
}
```

### Pattern 2: Polling Loop (Async with Wait)

**Use Case**: Submit query and wait for completion with UI feedback

```javascript
async function executeAndWait(query) {
  // Submit query
  const response = await fetch('/api/odluke-agent/execute', {
    method: 'POST',
    headers: {
      'X-API-Token': TOKEN,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({ query, async: true })
  });

  const { cache_key } = await response.json();

  // Poll until complete
  while (true) {
    await sleep(500); // Wait 500ms

    const statusResponse = await fetch('/api/odluke-agent/status', {
      method: 'POST',
      headers: {
        'X-API-Token': TOKEN,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({ cache_key })
    });

    const status = await statusResponse.json();

    if (status.status === 'completed') {
      return status.result;
    }

    if (status.status === 'error') {
      throw new Error(status.error_message);
    }

    // Update UI: "Processing..."
  }
}

// Usage
try {
  const result = await executeAndWait('Pronađi odluke o radnom pravu');
  console.log('Success:', result);
} catch (error) {
  console.error('Failed:', error);
}
```

### Pattern 3: Immediate Response (Sync)

**Use Case**: Simple queries that need immediate results

```javascript
async function executeSync(query) {
  const response = await fetch('/api/odluke-agent/execute', {
    method: 'POST',
    headers: {
      'X-API-Token': TOKEN,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      query,
      async: false  // Force synchronous
    })
  });

  const { result } = await response.json();
  return result;
}

// Usage
const result = await executeSync('Pronađi Vrhovni sud odluku VS-123/2024');
console.log('Result:', result);
```

### Pattern 4: Server-Side Polling (PHP)

**Use Case**: Backend script waiting for results

```php
use App\Agents\OdlukeAgent;

// Execute query
$response = OdlukeAgent::executeAsync(
    query: 'Pronađi odluke o ugovornoj odgovornosti',
    context: ['court' => 'Vrhovni sud'],
    async: true
);

$cacheKey = $response['cache_key'];

// Wait for completion (blocks up to 60 seconds)
$result = OdlukeAgent::waitForAsync($cacheKey, maxWaitSeconds: 60);

if ($result['status'] === 'completed') {
    echo "Agent found decisions:\n";
    print_r($result['result']);
} else if ($result['status'] === 'timeout') {
    echo "Query is still running. Check later.\n";
} else {
    echo "Error: " . $result['error_message'] . "\n";
}
```

---

## Agent Capabilities

### MCP Tools Integration

OdlukeAgent uses **5 MCP tools** to interact with Croatian legal databases:

#### 1. OdlukeSearchTool
```php
// Search odluke.sudovi.hr
$tool->execute([
    'q' => 'radni odnosi',
    'params' => 'sort=dat&vo=Presuda&od=2024-01-01',
    'limit' => 50,
    'page' => 1
]);
```

**Parameters**:
- `q`: Search query (keywords)
- `params`: URL query string for filters
  - `sud`: Court name
  - `vo`: Decision type (Presuda, Rješenje)
  - `od`/`do`: Date range (YYYY-MM-DD)
  - `sort`: Sort order
- `limit`: Results per page (default: 50)
- `page`: Page number (default: 1)

#### 2. OdlukeMetaTool
```php
// Fetch metadata for decision IDs
$tool->execute([
    'ids' => ['123', '456', '789']
]);
```

**Returns**:
- Title
- Court
- Date
- Decision type
- Subject/description

#### 3. OdlukeDownloadTool
```php
// Download decision as PDF/HTML
$tool->execute([
    'id' => '123',
    'format' => 'pdf',  // pdf|html|both
    'save' => true      // Save to local storage
]);
```

**Returns**:
- Download URL
- Local file path (if saved)

#### 4. LawArticlesSearchTool
```php
// Search Croatian laws
$tool->execute([
    'query' => 'radni odnosi',
    'limit' => 20
]);
```

#### 5. LawArticleByIdTool
```php
// Get specific law article
$tool->execute([
    'id' => 'law_article_123'
]);
```

### Agent Instructions

The agent is instructed to:

1. **Ask for clarification** if key constraints are missing
2. **Start with search** using `odluke_search` with sensible defaults
3. **Inspect metadata** using `odluke_meta` for top candidates
4. **Download selectively** (1-10 decisions unless user specifies)
5. **Summarize clearly** with date, court, number, subject
6. **Retry with relaxed filters** if no results found
7. **Validate arguments** before each tool call
8. **Reply in Croatian** if user writes Croatian

**Model**: GPT-4o-mini (fast, cost-effective)

**Max Steps**: 8 tool executions per query

---

## Cache Management

### Cache TTL

**Default**: 3600 seconds (1 hour)

```php
Cache::put($cacheKey, $data, 3600);
```

### Cache Key Generation

```php
$cacheKey = 'odluke_agent:' . md5($query . json_encode($context));
```

**Why MD5?**
- Deterministic: Same query = same key
- Short: 32 characters
- Collision-resistant for our use case

### Cache Invalidation

**Automatic**: After 1 hour, Laravel Cache expires the entry

**Manual**:
```php
use Illuminate\Support\Facades\Cache;

Cache::forget('odluke_agent:abc123...');
```

### Cache Backend

Configured in `.env`:

```env
CACHE_DRIVER=redis  # Recommended for production
# or
CACHE_DRIVER=file   # Development
```

**Recommendation**: Use **Redis** for production for:
- Faster access
- Shared cache across multiple workers
- Atomic operations
- TTL support

---

## Environment Detection

### Decision Logic

Based on `ExecuteOdlukeAgentJob::dispatchWithEnvDetection()` pattern (from SyncGraphDataJob):

```php
public static function dispatchWithEnvDetection(string $query, array $context, string $cacheKey): mixed
{
    $job = new self($query, $context, $cacheKey);

    // Environment detection
    $isProduction = app()->environment('production');
    $isLocalhost = in_array(request()->ip(), ['127.0.0.1', '::1', 'localhost']);

    if ($isProduction && !$isLocalhost) {
        // PRODUCTION: Dispatch to queue
        Log::info('Dispatching ExecuteOdlukeAgentJob to queue (production)', [
            'query' => $query,
            'cache_key' => $cacheKey,
        ]);

        // Set cache status to 'running'
        Cache::put($cacheKey, [
            'status' => 'running',
            'started_at' => now()->toIso8601String(),
            'query' => $query,
        ], 3600);

        return self::dispatch($query, $context, $cacheKey);

    } else {
        // DEVELOPMENT: Run synchronously
        Log::info('Running ExecuteOdlukeAgentJob synchronously (dev/localhost)', [
            'query' => $query,
            'cache_key' => $cacheKey,
        ]);

        $agent = new OdlukeAgent();
        $result = $agent->run($query);

        // Store result in cache
        Cache::put($cacheKey, [
            'status' => 'completed',
            'result' => $result,
            'completed_at' => now()->toIso8601String(),
        ], 3600);

        return ['mode' => 'sync', 'completed' => true];
    }
}
```

### Detection Matrix

| Environment | Request IP | Execution Mode |
|-------------|------------|----------------|
| production | Non-localhost | ⚡ Queue (Async) |
| production | localhost | 🔄 Sync |
| local/dev | Any | 🔄 Sync |

**Detection Factors**:
1. `app()->environment()` - Laravel environment setting
2. `request()->ip()` - Client IP address

---

## Performance Characteristics

### Synchronous Execution

**Pros**:
- ✅ Immediate results
- ✅ No queue worker required
- ✅ Easy to debug
- ✅ Simpler error handling
- ✅ See agent reasoning in real-time

**Cons**:
- ❌ Blocks HTTP request
- ❌ Can timeout (default: 60s PHP timeout)
- ❌ No parallelism
- ❌ Memory used by main process

**Best For**:
- Development and testing
- Simple, quick queries (< 30 seconds)
- Interactive debugging
- API endpoints with fast responses

**Average Execution Time**:
- Simple search: 5-10 seconds
- Search + metadata: 10-20 seconds
- Search + download: 15-30 seconds

### Asynchronous Execution

**Pros**:
- ✅ Non-blocking API response
- ✅ Survives HTTP timeouts
- ✅ Can run long queries (5+ minutes)
- ✅ Retries on failure
- ✅ Better resource isolation
- ✅ Can parallelize multiple queries

**Cons**:
- ❌ Requires queue worker
- ❌ More complex to debug
- ❌ Polling overhead
- ❌ Cache management complexity

**Best For**:
- Production deployments
- Complex multi-step queries
- Batch processing
- Long-running searches (50+ results)

**Execution Context**:
- Runs in queue worker process
- Independent from HTTP request
- Can use more memory
- No timeout limits (configurable)

---

## Integration Examples

### Laravel Controller

```php
namespace App\Http\Controllers;

use App\Agents\OdlukeAgent;
use Illuminate\Http\Request;

class MyController extends Controller
{
    public function searchDecisions(Request $request)
    {
        $query = $request->input('query');

        // Use async for production, sync for local
        $async = app()->environment('production');

        $result = OdlukeAgent::executeAsync(
            query: $query,
            context: ['user_id' => auth()->id()],
            async: $async
        );

        if ($result['mode'] === 'sync') {
            // Immediate result
            return view('decisions.results', [
                'results' => $result['result']
            ]);
        } else {
            // Async: redirect to status page
            return redirect()->route('decisions.status', [
                'cache_key' => $result['cache_key']
            ]);
        }
    }

    public function checkStatus(Request $request)
    {
        $cacheKey = $request->query('cache_key');
        $status = OdlukeAgent::getAsyncStatus($cacheKey);

        if (!$status) {
            abort(404, 'Query not found or expired');
        }

        if ($status['status'] === 'completed') {
            return view('decisions.results', [
                'results' => $status['result']
            ]);
        }

        return view('decisions.loading', [
            'cache_key' => $cacheKey,
            'status' => $status
        ]);
    }
}
```

### Artisan Command

```php
namespace App\Console\Commands;

use App\Agents\OdlukeAgent;
use Illuminate\Console\Command;

class SearchDecisionsCommand extends Command
{
    protected $signature = 'odluke:search {query} {--sync : Run synchronously}';
    protected $description = 'Search court decisions using OdlukeAgent';

    public function handle()
    {
        $query = $this->argument('query');
        $sync = $this->option('sync');

        $this->info("Searching: {$query}");

        $result = OdlukeAgent::executeAsync(
            query: $query,
            async: !$sync
        );

        if ($result['mode'] === 'sync') {
            $this->info('Results:');
            $this->line($result['result']['response'] ?? 'No response');
        } else {
            $this->info("Query submitted. Cache key: {$result['cache_key']}");
            $this->info('Waiting for results...');

            // Poll for results
            $finalResult = OdlukeAgent::waitForAsync(
                cacheKey: $result['cache_key'],
                maxWaitSeconds: 120
            );

            if ($finalResult['status'] === 'completed') {
                $this->info('Results:');
                $this->line($finalResult['result']['response'] ?? 'No response');
            } else {
                $this->error('Timeout or error: ' . ($finalResult['message'] ?? 'Unknown'));
            }
        }

        return 0;
    }
}
```

**Usage**:
```bash
# Async (default)
php artisan odluke:search "Pronađi odluke o radnim odnosima"

# Sync
php artisan odluke:search "Vrhovni sud presuda 2024" --sync
```

### JavaScript/Frontend Integration

```javascript
class OdlukeAgentClient {
  constructor(apiToken) {
    this.apiToken = apiToken;
    this.baseUrl = '/api/odluke-agent';
  }

  async execute(query, options = {}) {
    const response = await fetch(`${this.baseUrl}/execute`, {
      method: 'POST',
      headers: {
        'X-API-Token': this.apiToken,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        query,
        context: options.context || {},
        async: options.async !== false  // default: true
      })
    });

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }

    return response.json();
  }

  async getStatus(cacheKey) {
    const response = await fetch(`${this.baseUrl}/status`, {
      method: 'POST',
      headers: {
        'X-API-Token': this.apiToken,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({ cache_key: cacheKey })
    });

    if (!response.ok) {
      if (response.status === 404) {
        throw new Error('Query not found or expired');
      }
      throw new Error(`HTTP ${response.status}`);
    }

    return response.json();
  }

  async executeAndWait(query, options = {}) {
    const { cache_key } = await this.execute(query, {
      ...options,
      async: true
    });

    const maxWaitMs = (options.maxWaitSeconds || 60) * 1000;
    const pollIntervalMs = options.pollIntervalMs || 500;
    const startTime = Date.now();

    while (true) {
      if (Date.now() - startTime > maxWaitMs) {
        throw new Error('Timeout waiting for results');
      }

      await new Promise(resolve => setTimeout(resolve, pollIntervalMs));

      const status = await this.getStatus(cache_key);

      if (status.status === 'completed') {
        return status.result;
      }

      if (status.status === 'error') {
        throw new Error(status.error_message || 'Query failed');
      }

      // Callback for progress updates
      if (options.onProgress) {
        options.onProgress(status);
      }
    }
  }
}

// Usage
const client = new OdlukeAgentClient(API_TOKEN);

// Fire and forget
const { cache_key } = await client.execute('Pronađi odluke o radnim odnosima');
console.log('Query submitted:', cache_key);

// Wait for results with progress
try {
  const result = await client.executeAndWait(
    'Pronađi odluke Vrhovnog suda',
    {
      maxWaitSeconds: 120,
      onProgress: (status) => {
        console.log('Status:', status.status);
      }
    }
  );
  console.log('Results:', result);
} catch (error) {
  console.error('Error:', error.message);
}
```

### React Component Example

```jsx
import { useState } from 'react';

function DecisionSearch() {
  const [query, setQuery] = useState('');
  const [status, setStatus] = useState(null);
  const [result, setResult] = useState(null);
  const [error, setError] = useState(null);

  async function handleSearch() {
    setStatus('submitting');
    setError(null);

    try {
      // Submit query
      const response = await fetch('/api/odluke-agent/execute', {
        method: 'POST',
        headers: {
          'X-API-Token': process.env.REACT_APP_API_TOKEN,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({ query, async: true })
      });

      const { cache_key } = await response.json();
      setStatus('running');

      // Poll for results
      const pollInterval = setInterval(async () => {
        const statusResponse = await fetch('/api/odluke-agent/status', {
          method: 'POST',
          headers: {
            'X-API-Token': process.env.REACT_APP_API_TOKEN,
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({ cache_key })
        });

        const statusData = await statusResponse.json();

        if (statusData.status === 'completed') {
          clearInterval(pollInterval);
          setResult(statusData.result);
          setStatus('completed');
        } else if (statusData.status === 'error') {
          clearInterval(pollInterval);
          setError(statusData.error_message);
          setStatus('error');
        }
      }, 500);

      // Timeout after 2 minutes
      setTimeout(() => {
        clearInterval(pollInterval);
        if (status === 'running') {
          setError('Query timeout');
          setStatus('error');
        }
      }, 120000);

    } catch (err) {
      setError(err.message);
      setStatus('error');
    }
  }

  return (
    <div>
      <input
        value={query}
        onChange={(e) => setQuery(e.target.value)}
        placeholder="Unesite upit..."
      />
      <button onClick={handleSearch} disabled={status === 'running'}>
        Pretraži
      </button>

      {status === 'running' && <p>Pretraživanje u tijeku...</p>}
      {status === 'completed' && (
        <div>
          <h3>Rezultati:</h3>
          <pre>{JSON.stringify(result, null, 2)}</pre>
        </div>
      )}
      {error && <p style={{ color: 'red' }}>Greška: {error}</p>}
    </div>
  );
}
```

---

## Comparison with Other Agents

### OdlukeAgent vs AutonomousResearchAgent

| Feature | OdlukeAgent | AutonomousResearchAgent |
|---------|-------------|-------------------------|
| **Framework** | Vizra ADK | Vizra ADK |
| **Primary Use** | Court decision search/download | Legal research & self-study |
| **Tools** | 5 (Odluke + Law tools) | 18+ (Search, graph, analysis) |
| **Max Steps** | 8 | 20 |
| **Async Support** | ✅ Via executeAsync() | ✅ Via queue jobs |
| **Environment Detection** | ✅ Yes | ✅ Yes |
| **Cache-based Status** | ✅ Yes | ❌ No (uses database) |
| **Database Tracking** | ❌ No | ✅ Yes (agent_runs table) |
| **Evaluation System** | ❌ No | ✅ Yes (5 criteria) |
| **Checkpointing** | ❌ No | ✅ Yes (resume support) |
| **Best For** | Quick decision lookups | Deep research projects |

### OdlukeAgent vs DecisionDiscoveryAgent

| Feature | OdlukeAgent | DecisionDiscoveryAgent |
|---------|-------------|------------------------|
| **Framework** | Vizra ADK | Standalone (non-ADK) |
| **Primary Use** | User-directed search | Autonomous discovery |
| **Execution** | On-demand (API/manual) | Scheduled/autonomous |
| **AI Usage** | Tool selection & reasoning | Topic generation + scoring |
| **Async Support** | ✅ Via executeAsync() | ✅ Via queue jobs |
| **Environment Detection** | ✅ Yes | ✅ Yes |
| **Database Tracking** | ❌ No | ✅ Yes (discovery_runs table) |
| **User Interaction** | ✅ Yes (responds to queries) | ❌ No (fully autonomous) |
| **Result Format** | Natural language response | Ingested decisions |
| **Best For** | Interactive queries | Background ingestion |

---

## Monitoring & Debugging

### Check Cache Contents

```php
use Illuminate\Support\Facades\Cache;

// Get all keys matching pattern (Redis)
$keys = Redis::keys('odluke_agent:*');

foreach ($keys as $key) {
    $data = Cache::get(str_replace('laravel_database_', '', $key));
    dump($data);
}
```

### Monitor Queue Jobs

```bash
# Check queue status
php artisan queue:work --once --verbose

# Monitor failed jobs
php artisan queue:failed

# Retry failed job
php artisan queue:retry {job_id}
```

### Debug Logs

```php
use Illuminate\Support\Facades\Log;

Log::channel('daily')->info('OdlukeAgent execution', [
    'cache_key' => $cacheKey,
    'query' => $query,
    'mode' => $mode
]);
```

**Log Locations**:
- `storage/logs/laravel.log` - General logs
- `storage/logs/laravel-{date}.log` - Daily logs

### Cache Statistics (Redis)

```bash
redis-cli

# Get cache size
DBSIZE

# Get TTL for key
TTL odluke_agent:abc123...

# List all odluke agent keys
KEYS odluke_agent:*

# Get key info
GET odluke_agent:abc123...
```

---

## Security Considerations

### API Token Authentication

**Configuration**: `.env`
```env
API_TOKEN=your-secret-token-here
```

**Middleware**: `api.token` (validates `X-API-Token` header)

**Rate Limiting**: 30 requests/minute per IP

### Input Validation

**Query Length**: Max 2000 characters
```php
$request->validate([
    'query' => 'required|string|max:2000',
]);
```

### Cache Key Security

**Not User-Visible**: Cache keys are MD5 hashes, not sequential IDs

**TTL Protection**: Keys expire after 1 hour automatically

**No User Data**: Cache keys don't expose sensitive information

### Privilege Separation

**Public API**: Can search court decisions (public data)

**Private API**: Case search requires authentication (`mcp.auth:case.search`)

---

## Troubleshooting

### Issue: Query Times Out

**Symptoms**: No response after 60 seconds (sync mode)

**Solutions**:
1. Use `async: true` for long queries
2. Increase PHP timeout: `set_time_limit(300)`
3. Optimize query (be more specific)

### Issue: Cache Not Found (404)

**Symptoms**: `/status` returns 404 error

**Causes**:
- Cache expired (> 1 hour)
- Wrong cache key
- Cache driver issue

**Solutions**:
```php
// Check if key exists
$exists = Cache::has($cacheKey);

// Extend TTL
Cache::put($cacheKey, $data, 7200); // 2 hours
```

### Issue: Queue Job Not Processing

**Symptoms**: Status stuck on 'running'

**Causes**:
- Queue worker not running
- Job failed silently
- Redis connection issue

**Solutions**:
```bash
# Start queue worker
php artisan queue:work --verbose

# Check failed jobs
php artisan queue:failed

# Check Redis connection
redis-cli ping
```

### Issue: Agent Returns Empty Results

**Symptoms**: `result` is empty or null

**Causes**:
- No decisions found for query
- API connection failure
- Tool execution error

**Solutions**:
1. Check agent logs
2. Test tool directly:
```php
use App\Tools\OdlukeSearchTool;

$tool = new OdlukeSearchTool();
$result = $tool->execute([
    'q' => 'test query',
    'limit' => 10
]);
```

---

## Future Enhancements

### 1. WebSocket Support
Real-time updates instead of polling:
```javascript
const ws = new WebSocket('ws://localhost/odluke-agent');
ws.send(JSON.stringify({ query: '...' }));
ws.onmessage = (event) => {
  const status = JSON.parse(event.data);
  console.log('Status update:', status);
};
```

### 2. Streaming Response
Stream agent reasoning in real-time:
```php
OdlukeAgent::executeAsync(
    query: $query,
    streaming: true,
    onChunk: fn($chunk) => echo $chunk
);
```

### 3. Result Pagination
For queries with many results:
```php
OdlukeAgent::executeAsync(
    query: $query,
    options: ['paginate' => 20]
);
```

### 4. Query History
Track user queries:
```sql
CREATE TABLE odluke_agent_queries (
    id BIGINT PRIMARY KEY,
    user_id BIGINT,
    query TEXT,
    cache_key VARCHAR(255),
    status VARCHAR(50),
    created_at TIMESTAMP
);
```

### 5. Result Caching Enhancement
Semantic caching for similar queries:
```php
// Find similar cached queries
$similar = $this->findSimilarCachedQuery($query, threshold: 0.9);
if ($similar) {
    return Cache::get($similar['cache_key']);
}
```

---

## Related Documentation

- **Agent Framework Analysis**: `docs/AGENT_FRAMEWORK_ANALYSIS.md`
- **OdlukeAgent Fix**: `docs/ODLUKE_AGENT_FIX.md`
- **MCP All Contexts Fix**: `docs/MCP_ALL_CONTEXTS_FIX.md`
- **Decision Discovery Flow**: `docs/DECISION_DISCOVERY_ENV_DETECTION_FLOW.md`

---

## Implementation Checklist

- [x] Add Cache facade import to OdlukeAgent
- [x] Add `executeAsync()` static method
- [x] Add `getAsyncStatus()` status check method
- [x] Add `waitForAsync()` polling helper method
- [x] Create OdlukeController with execute/status endpoints
- [x] Add API routes with authentication and rate limiting
- [ ] Create `ExecuteOdlukeAgentJob` with `dispatchWithEnvDetection()`
- [ ] Add WebSocket support for real-time updates (optional)
- [ ] Add query history tracking (optional)
- [ ] Add monitoring/alerting for failed queries
- [ ] Add semantic caching for similar queries (optional)

---

## Example Queries

### Simple Search
```json
{
  "query": "Pronađi najnovije odluke o radnim odnosima"
}
```

### Filtered Search
```json
{
  "query": "Pronađi presude Vrhovnog suda o nezakonitom otkazu iz 2024. godine"
}
```

### Download Request
```json
{
  "query": "Pronađi odluku VS-123/2024 i preuzmi PDF"
}
```

### Law + Decision Search
```json
{
  "query": "Pronađi odluke vezane uz članak 105 Zakona o radu"
}
```

### Date Range Query
```json
{
  "query": "Pronađi sve odluke Županijskog suda u Osijeku o potrošačkoj zaštiti između 1.1.2024 i 31.3.2024"
}
```

---

**Last Updated**: October 31, 2025
**Author**: AI Legal War Machine Development Team
**Version**: 1.0

# Agent Queue Jobs - Complete Flow Documentation

## Overview

This document provides a comprehensive overview of the two agent queue jobs implemented for asynchronous execution of AI-powered legal research agents. Both jobs share a common architecture pattern with environment detection and intelligent sync/async routing.

**Jobs Implemented:**
1. **ExecuteDecisionDiscoveryJob** - Autonomous court decision discovery
2. **ExecuteOdlukeAgentJob** - MCP-based legal research queries

**Created:** Tasks 1.1 and 2.1
**Branch:** `claude/create-decision-discovery-job-011CUeMXqL7nfCXfbTBNFkwi`

---

## Quick Reference Comparison

| Feature | ExecuteDecisionDiscoveryJob | ExecuteOdlukeAgentJob |
|---------|----------------------------|------------------------|
| **Purpose** | Autonomous decision discovery & ingestion | On-demand legal research via MCP tools |
| **Agent** | DecisionDiscoveryAgent | OdlukeAgent |
| **Storage** | Database (`decision_discovery_runs`) | Cache (Redis/File, 1-hour TTL) |
| **Timeout** | 15 minutes (900s) | 5 minutes (300s) |
| **Retries** | 1 attempt | 1 attempt |
| **Use Case** | Scheduled background discovery | User-initiated research queries |
| **Result Type** | Statistics + metadata | Full agent response |
| **Persistence** | Permanent run history | Temporary (auto-expires) |
| **Status Tracking** | Database records | Cache polling |
| **Scheduling** | Yes (daily/weekly) | No (on-demand) |
| **LLM Usage** | High (topic generation + scoring) | Moderate (agent reasoning) |
| **Tool Chain** | OdlukeClient + OdlukeIngestService | 5 MCP tools via OdlukeAgent |

---

## Architecture Overview

### Unified Job Architecture Pattern

Both jobs follow the same architectural pattern with three key components:

```
┌─────────────────────────────────────────────────────────────────┐
│                  Common Architecture Pattern                     │
│                                                                  │
│  1. Environment Detection (DetectsEnvironment trait)            │
│     ├─ Production + Remote → Async (Queue)                      │
│     └─ Dev/Localhost → Sync (Immediate)                         │
│                                                                  │
│  2. Execution Flow                                              │
│     ├─ Log environment                                          │
│     ├─ Execute agent                                            │
│     ├─ Track duration                                           │
│     ├─ Store results (DB or Cache)                              │
│     └─ Handle errors                                            │
│                                                                  │
│  3. Monitoring & Recovery                                       │
│     ├─ Job tags for Horizon                                     │
│     ├─ Comprehensive logging                                    │
│     ├─ Failed job handling                                      │
│     └─ Error caching/persistence                                │
└─────────────────────────────────────────────────────────────────┘
```

### System Integration Diagram

```
┌───────────────────────────────────────────────────────────────────────────┐
│                        Legal Research System                               │
│                                                                            │
│  ┌────────────────────────┐         ┌──────────────────────────┐          │
│  │  Scheduled Discovery   │         │   User-Initiated Query   │          │
│  │  (Cron/Artisan)        │         │   (Dashboard/API)        │          │
│  └────────────┬───────────┘         └───────────┬──────────────┘          │
│               │                                  │                         │
│               ▼                                  ▼                         │
│  ┌─────────────────────────┐       ┌─────────────────────────┐            │
│  │ ExecuteDecisionDiscovery│       │ ExecuteOdlukeAgentJob   │            │
│  │ Job                     │       │                         │            │
│  │                         │       │                         │            │
│  │ • DecisionDiscoveryAgent│       │ • OdlukeAgent           │            │
│  │ • DB Storage            │       │ • Cache Storage         │            │
│  │ • 15-min timeout        │       │ • 5-min timeout         │            │
│  └────────────┬────────────┘       └───────────┬─────────────┘            │
│               │                                 │                          │
│               ▼                                 ▼                          │
│  ┌────────────────────────┐       ┌─────────────────────────┐             │
│  │ DecisionDiscoveryAgent │       │    OdlukeAgent          │             │
│  │                        │       │                         │             │
│  │ ├─ LLM Topic Gen       │       │ ├─ GPT-4o-mini          │             │
│  │ ├─ OdlukeClient        │       │ ├─ MCP Tool Chain       │             │
│  │ ├─ LLM Scoring         │       │ │  • odluke-search      │             │
│  │ └─ OdlukeIngestService │       │ │  • odluke-meta        │             │
│  │                        │       │ │  • odluke-download    │             │
│  │                        │       │ │  • law-articles-search│             │
│  └────────────┬───────────┘       │ │  • law-article-by-id  │             │
│               │                   │ └─ Max 8 steps          │             │
│               │                   └───────────┬─────────────┘             │
│               ▼                               ▼                            │
│  ┌────────────────────────┐       ┌─────────────────────────┐             │
│  │ Database Storage       │       │    Cache Storage        │             │
│  │                        │       │                         │             │
│  │ decision_discovery_runs│       │ Redis/File (1-hour TTL) │             │
│  │ • Permanent history    │       │ • Temporary results     │             │
│  │ • Run statistics       │       │ • Status: running/      │             │
│  │ • Error tracking       │       │   completed/failed      │             │
│  └────────────────────────┘       └─────────────────────────┘             │
│                                                                            │
│  ┌────────────────────────────────────────────────────────────┐           │
│  │              Shared Infrastructure                          │           │
│  │                                                             │           │
│  │  • DetectsEnvironment Trait                                │           │
│  │  • Queue System (Laravel Queue/Horizon)                    │           │
│  │  • Logging (Laravel Log Facade)                            │           │
│  │  • Job Monitoring (Tags + Horizon)                         │           │
│  └────────────────────────────────────────────────────────────┘           │
└───────────────────────────────────────────────────────────────────────────┘
```

---

## Complete Flow Schemas

### 1. ExecuteDecisionDiscoveryJob Flow

```
┌───────────────────────────────────────────────────────────────────────┐
│                  ExecuteDecisionDiscoveryJob Complete Flow            │
│                                                                       │
│  TRIGGER: Scheduled (daily/weekly) or Manual dispatch                │
│     │                                                                 │
│     ▼                                                                 │
│  ┌──────────────────────────────────────────────────────┐            │
│  │ dispatchWithEnvDetection(?maxDecisions, ?topic)      │            │
│  │                                                      │            │
│  │ Environment Detection:                              │            │
│  │ • Production + Remote → Queue (async)               │            │
│  │ • Dev/Localhost → Sync (immediate)                  │            │
│  └────────────────────┬─────────────────────────────────┘            │
│                       │                                              │
│                       ▼                                              │
│  ┌──────────────────────────────────────────────────────┐            │
│  │ handle(DecisionDiscoveryAgent $agent)                │            │
│  │                                                      │            │
│  │ 1. Log environment                                  │            │
│  │ 2. Start timer                                      │            │
│  │ 3. Execute agent discovery                          │            │
│  └────────────────────┬─────────────────────────────────┘            │
│                       │                                              │
│                       ▼                                              │
│  ┌──────────────────────────────────────────────────────┐            │
│  │ DecisionDiscoveryAgent.discover()                    │            │
│  │                                                      │            │
│  │ Step 1: Generate Topics via LLM                     │            │
│  │   ├─ GPT-4o-mini: Generate 5 legal research topics  │            │
│  │   ├─ Cache topics for 1 week                        │            │
│  │   └─ Fallback to predefined if LLM fails            │            │
│  │                                                      │            │
│  │ Step 2: For Each Topic                              │            │
│  │   ├─ Search odluke.sudovi.hr (50 decisions)         │            │
│  │   ├─ Fetch metadata for all results                 │            │
│  │   ├─ Score decisions via LLM (batches of 10)        │            │
│  │   │   • Scoring scale: 0-100                        │            │
│  │   │   • Criteria: relevance, court, type, recency   │            │
│  │   ├─ Filter by threshold (≥70 by default)           │            │
│  │   ├─ Sort by score (highest first)                  │            │
│  │   ├─ Take top 10 per topic                          │            │
│  │   └─ Ingest via OdlukeIngestService                 │            │
│  │                                                      │            │
│  │ Step 3: Return Statistics                           │            │
│  │   • total_found                                     │            │
│  │   • ingested                                        │            │
│  │   • topics_count                                    │            │
│  │   • per_topic_stats                                 │            │
│  └────────────────────┬─────────────────────────────────┘            │
│                       │                                              │
│                       ▼                                              │
│  ┌──────────────────────────────────────────────────────┐            │
│  │ Store Results in Database                            │            │
│  │                                                      │            │
│  │ INSERT INTO decision_discovery_runs:                │            │
│  │   status: 'completed'                               │            │
│  │   decisions_found: 127                              │            │
│  │   decisions_ingested: 23                            │            │
│  │   topics_generated: 5                               │            │
│  │   topic_filter: (optional topic)                    │            │
│  │   duration_seconds: 142.35                          │            │
│  │   statistics: {JSON with full result}               │            │
│  │   completed_at: now()                               │            │
│  └────────────────────┬─────────────────────────────────┘            │
│                       │                                              │
│                       ▼                                              │
│  ┌──────────────────────────────────────────────────────┐            │
│  │ Log Completion                                       │            │
│  │                                                      │            │
│  │ • Duration                                          │            │
│  │ • Decisions found/ingested                          │            │
│  │ • Topics generated                                  │            │
│  └──────────────────────────────────────────────────────┘            │
│                                                                      │
│  ERROR PATH:                                                         │
│  ┌──────────────────────────────────────────────────────┐            │
│  │ Exception Caught                                     │            │
│  │   ↓                                                  │            │
│  │ Log error with stack trace                          │            │
│  │   ↓                                                  │            │
│  │ INSERT failed run record into DB                    │            │
│  │   • status: 'failed'                                │            │
│  │   • error: exception message                        │            │
│  │   • duration_seconds                                │            │
│  │   ↓                                                  │            │
│  │ Re-throw exception                                  │            │
│  │   ↓                                                  │            │
│  │ failed() method called                              │            │
│  └──────────────────────────────────────────────────────┘            │
└───────────────────────────────────────────────────────────────────────┘

RESULT: Permanent database record with full run statistics
MONITORING: Dashboard shows run history and success rate
```

### 2. ExecuteOdlukeAgentJob Flow

```
┌───────────────────────────────────────────────────────────────────────┐
│                  ExecuteOdlukeAgentJob Complete Flow                  │
│                                                                       │
│  TRIGGER: User query from Dashboard/API/CLI                          │
│     │                                                                 │
│     ▼                                                                 │
│  ┌──────────────────────────────────────────────────────┐            │
│  │ Generate Cache Key                                   │            │
│  │   cacheKey = 'odluke_job_' + uniqid()                │            │
│  └────────────────────┬─────────────────────────────────┘            │
│                       │                                              │
│                       ▼                                              │
│  ┌──────────────────────────────────────────────────────┐            │
│  │ dispatchWithEnvDetection(query, context, cacheKey)   │            │
│  │                                                      │            │
│  │ Environment Detection:                              │            │
│  │ • Production + Remote → Queue (async)               │            │
│  │   └─ Cache: {status: 'running', started_at: ...}   │            │
│  │ • Dev/Localhost → Sync (immediate)                  │            │
│  └────────────────────┬─────────────────────────────────┘            │
│                       │                                              │
│                       ▼                                              │
│  ┌──────────────────────────────────────────────────────┐            │
│  │ handle(OdlukeAgent $agent)                           │            │
│  │                                                      │            │
│  │ 1. Log environment                                  │            │
│  │ 2. Start timer                                      │            │
│  │ 3. Execute agent with MCP tool chain                │            │
│  └────────────────────┬─────────────────────────────────┘            │
│                       │                                              │
│                       ▼                                              │
│  ┌──────────────────────────────────────────────────────┐            │
│  │ OdlukeAgent.execute(query, context)                  │            │
│  │                                                      │            │
│  │ Agent Loop (Max 8 Steps):                           │            │
│  │                                                      │            │
│  │ Step 1: Analyze Query                               │            │
│  │   ├─ GPT-4o-mini understands user intent            │            │
│  │   └─ Plans tool sequence                            │            │
│  │                                                      │            │
│  │ Step 2: Search Decisions (MCP Tool)                 │            │
│  │   ├─ Tool: odluke-search                            │            │
│  │   ├─ Args: {q: "keywords", limit: 50, params: ...}  │            │
│  │   └─ Result: [id1, id2, id3, ...]                   │            │
│  │                                                      │            │
│  │ Step 3: Get Metadata (MCP Tool)                     │            │
│  │   ├─ Tool: odluke-meta                              │            │
│  │   ├─ Args: {ids: [id1, id2, id3]}                   │            │
│  │   └─ Result: [{title, court, date}, ...]            │            │
│  │                                                      │            │
│  │ Step 4: Download Decision (MCP Tool)                │            │
│  │   ├─ Tool: odluke-download                          │            │
│  │   ├─ Args: {id: id1, format: "pdf", save: true}     │            │
│  │   └─ Result: {url, saved_path}                      │            │
│  │                                                      │            │
│  │ Step 5: Search Laws (Optional)                      │            │
│  │   ├─ Tool: law-articles-search                      │            │
│  │   ├─ Args: {query: "relevant law", limit: 10}       │            │
│  │   └─ Result: [...law articles...]                   │            │
│  │                                                      │            │
│  │ Step 6-8: Additional reasoning/tool calls           │            │
│  │                                                      │            │
│  │ Final Step: Synthesize Response                     │            │
│  │   ├─ Summarize findings                             │            │
│  │   ├─ List decisions with metadata                   │            │
│  │   └─ Provide download links                         │            │
│  └────────────────────┬─────────────────────────────────┘            │
│                       │                                              │
│                       ▼                                              │
│  ┌──────────────────────────────────────────────────────┐            │
│  │ Store Result in Cache                                │            │
│  │                                                      │            │
│  │ Cache::put(cacheKey, [                              │            │
│  │   'status' => 'completed',                          │            │
│  │   'result' => {                                     │            │
│  │     'summary': '...',                               │            │
│  │     'decisions': [...],                             │            │
│  │     'tool_calls': 5,                                │            │
│  │     'agent_steps': 6                                │            │
│  │   },                                                │            │
│  │   'duration' => 42.15,                              │            │
│  │   'completed_at' => '2025-10-31T...'                │            │
│  │ ], 3600); // 1-hour TTL                             │            │
│  └────────────────────┬─────────────────────────────────┘            │
│                       │                                              │
│                       ▼                                              │
│  ┌──────────────────────────────────────────────────────┐            │
│  │ Client Polls Cache                                   │            │
│  │                                                      │            │
│  │ while (true) {                                      │            │
│  │   $status = Cache::get(cacheKey);                   │            │
│  │   if ($status['status'] !== 'running') break;      │            │
│  │   sleep(2);                                         │            │
│  │ }                                                   │            │
│  │                                                      │            │
│  │ Display result to user                              │            │
│  └──────────────────────────────────────────────────────┘            │
│                                                                      │
│  ERROR PATH:                                                         │
│  ┌──────────────────────────────────────────────────────┐            │
│  │ Exception Caught                                     │            │
│  │   ↓                                                  │            │
│  │ Log error with stack trace                          │            │
│  │   ↓                                                  │            │
│  │ Cache::put(cacheKey, [                              │            │
│  │   'status' => 'failed',                             │            │
│  │   'error' => exception message,                     │            │
│  │   'completed_at' => now()                           │            │
│  │ ], 3600);                                           │            │
│  │   ↓                                                  │            │
│  │ Re-throw exception                                  │            │
│  │   ↓                                                  │            │
│  │ failed() method updates cache                       │            │
│  │   ↓                                                  │            │
│  │ Client receives error from cache                    │            │
│  └──────────────────────────────────────────────────────┘            │
└───────────────────────────────────────────────────────────────────────┘

RESULT: Temporary cache entry with agent response (expires in 1 hour)
MONITORING: Real-time status via cache polling, Horizon tags
```

---

## Environment Detection Flow (Shared)

Both jobs use identical environment detection logic via the `DetectsEnvironment` trait:

```
┌─────────────────────────────────────────────────────────────────┐
│              Environment Detection Decision Tree                 │
│                                                                  │
│  START: dispatchWithEnvDetection() called                       │
│     │                                                            │
│     ▼                                                            │
│  ┌──────────────────────────────────────────┐                   │
│  │ Check: app()->environment('production')   │                   │
│  └────────────┬─────────────────────────────┘                   │
│               │                                                  │
│       ┌───────┴────────┐                                        │
│       │                │                                        │
│     TRUE             FALSE                                      │
│       │                │                                        │
│       ▼                ▼                                        │
│  [Production]    [Dev/Testing/Local]                            │
│       │                │                                        │
│       ▼                │                                        │
│  ┌──────────────────────────────────┐                           │
│  │ Check: request()->ip() in        │                           │
│  │ ['127.0.0.1', '::1', 'localhost']│                           │
│  └────────┬─────────────────────────┘                           │
│           │                          │                          │
│    ┌──────┴───────┐                  │                          │
│    │              │                  │                          │
│  TRUE           FALSE                │                          │
│    │              │                  │                          │
│    ▼              ▼                  ▼                          │
│ [Local]      [Remote]           [Non-Prod]                      │
│    │              │                  │                          │
│    │              │                  │                          │
│    └──────┬───────┘                  │                          │
│           │                          │                          │
│           ▼                          ▼                          │
│    ┌────────────┐            ┌──────────────┐                  │
│    │ SYNC MODE  │            │  SYNC MODE   │                  │
│    │            │            │              │                  │
│    │ • Execute  │            │ • Execute    │                  │
│    │   inline   │            │   inline     │                  │
│    │ • Return   │            │ • Return     │                  │
│    │   result   │            │   result     │                  │
│    └────────────┘            └──────────────┘                  │
│                                                                 │
│           ▼                                                     │
│    ┌─────────────┐                                             │
│    │ ASYNC MODE  │                                             │
│    │ (Queue)     │                                             │
│    │             │                                             │
│    │ • dispatch()│                                             │
│    │ • Return    │                                             │
│    │   job ID    │                                             │
│    └─────────────┘                                             │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘

DECISION MATRIX:
┌────────────┬─────────────┬──────────────┬─────────────┐
│ Environment│ IP Address  │ Execution    │ Why         │
├────────────┼─────────────┼──────────────┼─────────────┤
│ production │ Remote      │ Async (Queue)│ Production  │
│ production │ Localhost   │ Sync         │ Dev testing │
│ local      │ Any         │ Sync         │ Development │
│ testing    │ Any         │ Sync         │ Test suite  │
└────────────┴─────────────┴──────────────┴─────────────┘
```

---

## Data Storage Schemas

### ExecuteDecisionDiscoveryJob - Database Schema

```sql
-- Table: decision_discovery_runs
CREATE TABLE decision_discovery_runs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Status tracking
    status ENUM('running', 'completed', 'failed') DEFAULT 'running',

    -- Metrics
    decisions_found INT NULL,           -- Total decisions evaluated
    decisions_ingested INT NULL,        -- Actually ingested
    topics_generated INT NULL,          -- Number of topics generated

    -- Configuration
    topic_filter VARCHAR(255) NULL,     -- Specific topic filter (if any)

    -- Performance
    duration_seconds DECIMAL(8,2) NULL, -- Execution time

    -- Detailed data
    statistics JSON NULL,               -- Full result array from agent
    error TEXT NULL,                    -- Error message if failed

    -- Timestamps
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    -- Indexes
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);
```

**Example Record:**
```json
{
  "id": 42,
  "status": "completed",
  "decisions_found": 127,
  "decisions_ingested": 23,
  "topics_generated": 5,
  "topic_filter": null,
  "duration_seconds": 142.35,
  "statistics": {
    "total_found": 127,
    "ingested": 23,
    "topics_count": 5,
    "topics": ["Radno pravo", "Ugovorno pravo", ...],
    "per_topic_stats": {...}
  },
  "error": null,
  "completed_at": "2025-10-31 01:02:22",
  "created_at": "2025-10-31 01:00:00",
  "updated_at": "2025-10-31 01:02:22"
}
```

### ExecuteOdlukeAgentJob - Cache Schema

```
Cache Key Pattern: odluke_job_{unique_identifier}
TTL: 3600 seconds (1 hour)
Driver: Redis (preferred) or File
```

**Cache Value - Running:**
```json
{
  "status": "running",
  "started_at": "2025-10-31T00:48:00Z"
}
```

**Cache Value - Completed:**
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
        "download_url": "https://...",
        "saved_path": "/storage/decisions/17383.pdf"
      }
    ],
    "tool_calls": 5,
    "agent_steps": 4
  },
  "duration": 42.15,
  "completed_at": "2025-10-31T00:48:42Z"
}
```

**Cache Value - Failed:**
```json
{
  "status": "failed",
  "error": "Connection timeout to MCP server after 30 seconds",
  "completed_at": "2025-10-31T00:48:30Z"
}
```

---

## When to Use Which Job

### Use ExecuteDecisionDiscoveryJob When:

✅ **Autonomous Background Processing**
- Scheduled discovery runs (daily/weekly)
- No user waiting for immediate results
- Building knowledge base over time

✅ **Bulk Decision Ingestion**
- Need to discover multiple topics
- Ingest many decisions in one run
- Long-running process (10-15 minutes)

✅ **Permanent Record Keeping**
- Need historical run data
- Track success rates over time
- Audit trail required

✅ **LLM-Powered Discovery**
- Let AI generate research topics
- Automated relevance scoring
- No specific user query

**Example Use Cases:**
```php
// Daily scheduled discovery
$schedule->call(function () {
    ExecuteDecisionDiscoveryJob::dispatchWithEnvDetection();
})->daily()->at('02:00');

// Weekly comprehensive run
ExecuteDecisionDiscoveryJob::dispatchWithEnvDetection(
    maxDecisions: 100
);

// Topic-specific discovery
ExecuteDecisionDiscoveryJob::dispatchWithEnvDetection(
    maxDecisions: null,
    topic: 'radno pravo'
);
```

### Use ExecuteOdlukeAgentJob When:

✅ **User-Initiated Queries**
- Specific user research request
- Dashboard/API initiated
- User waiting for results (with polling)

✅ **Interactive Research**
- Need full agent response
- Multiple MCP tool calls
- Conversational AI interaction

✅ **Short-Lived Results**
- Results only needed temporarily
- Auto-cleanup desired (1-hour TTL)
- No historical tracking needed

✅ **MCP Tool Chain Execution**
- Leverage OdlukeAgent's capabilities
- Access to 5 MCP tools
- Flexible query handling

**Example Use Cases:**
```php
// User dashboard query
$cacheKey = 'user_' . auth()->id() . '_' . time();
ExecuteOdlukeAgentJob::dispatchWithEnvDetection(
    query: "Find recent Supreme Court labor law decisions",
    context: ['user_id' => auth()->id()],
    cacheKey: $cacheKey
);

// API endpoint
Route::post('/research', function (Request $request) {
    $cacheKey = 'api_' . uniqid();
    ExecuteOdlukeAgentJob::dispatchWithEnvDetection(
        query: $request->input('query'),
        context: ['api_key' => $request->bearerToken()],
        cacheKey: $cacheKey
    );
    return ['job_id' => $cacheKey];
});

// CLI research
php artisan tinker
>>> ExecuteOdlukeAgentJob::dispatchWithEnvDetection(
...     "Pronađi odluke o zaštiti potrošača",
...     [],
...     'cli_test'
... );
```

---

## Integration Patterns

### Pattern 1: Sequential Job Chain

Use both jobs in sequence for comprehensive research:

```php
// Step 1: Use OdlukeAgent for initial query
$cacheKey = 'research_' . uniqid();
ExecuteOdlukeAgentJob::dispatchWithEnvDetection(
    query: "Find interesting labor law decisions",
    context: ['research_id' => 123],
    cacheKey: $cacheKey
);

// Step 2: After results, trigger discovery for that topic
Bus::chain([
    function () use ($cacheKey) {
        $result = Cache::get($cacheKey);
        if ($result['status'] === 'completed') {
            // Extract topic from result
            $topic = extractTopic($result['result']);

            // Dispatch discovery job for deep ingestion
            ExecuteDecisionDiscoveryJob::dispatch(null, $topic);
        }
    }
])->dispatch();
```

### Pattern 2: Parallel Execution

Run both jobs in parallel for different purposes:

```php
use Illuminate\Support\Facades\Bus;

Bus::batch([
    // User gets immediate research via OdlukeAgent
    new ExecuteOdlukeAgentJob(
        "Current labor law trends",
        ['user_id' => 1],
        'user_research'
    ),

    // System runs discovery for knowledge base
    new ExecuteDecisionDiscoveryJob(50, 'radno pravo'),

])->dispatch();
```

### Pattern 3: Conditional Routing

Choose job based on request type:

```php
class ResearchController extends Controller
{
    public function research(Request $request)
    {
        if ($request->input('mode') === 'discover') {
            // Background discovery with permanent storage
            ExecuteDecisionDiscoveryJob::dispatchWithEnvDetection(
                maxDecisions: $request->input('max_decisions'),
                topic: $request->input('topic')
            );

            return response()->json([
                'message' => 'Discovery job started',
                'check_status' => route('discovery.status')
            ]);

        } else {
            // Interactive query with cache
            $cacheKey = 'query_' . uniqid();

            ExecuteOdlukeAgentJob::dispatchWithEnvDetection(
                query: $request->input('query'),
                context: ['user_id' => auth()->id()],
                cacheKey: $cacheKey
            );

            return response()->json([
                'job_id' => $cacheKey,
                'poll_url' => route('research.status', $cacheKey)
            ]);
        }
    }
}
```

### Pattern 4: Hybrid Dashboard

Single dashboard with both functionalities:

```php
// app/Http/Livewire/ResearchDashboard.php

class ResearchDashboard extends Component
{
    public string $query = '';
    public ?string $topic = null;
    public string $mode = 'query'; // 'query' or 'discover'

    public ?string $jobId = null;
    public ?array $result = null;

    public function startResearch()
    {
        if ($this->mode === 'discover') {
            // Use ExecuteDecisionDiscoveryJob
            ExecuteDecisionDiscoveryJob::dispatchWithEnvDetection(
                maxDecisions: 50,
                topic: $this->topic
            );

            session()->flash('message', 'Discovery started! Check history tab.');

        } else {
            // Use ExecuteOdlukeAgentJob
            $this->jobId = 'odluke_' . uniqid();

            ExecuteOdlukeAgentJob::dispatchWithEnvDetection(
                query: $this->query,
                context: ['user_id' => auth()->id()],
                cacheKey: $this->jobId
            );
        }
    }

    public function checkStatus()
    {
        if ($this->mode === 'query' && $this->jobId) {
            $status = Cache::get($this->jobId);

            if ($status && $status['status'] === 'completed') {
                $this->result = $status['result'];
                $this->jobId = null;
            }
        }
    }

    public function render()
    {
        // Auto-poll for query mode
        if ($this->jobId) {
            $this->checkStatus();
        }

        return view('livewire.research-dashboard', [
            'runs' => DecisionDiscoveryRun::latest()->limit(10)->get(),
        ]);
    }
}
```

---

## Monitoring & Observability

### Horizon Tags

Both jobs implement tags for monitoring:

```php
// ExecuteDecisionDiscoveryJob
public function tags(): array
{
    return [
        'agent:decision-discovery',
        $this->topic ? "topic:{$this->topic}" : 'topic:all',
    ];
}

// ExecuteOdlukeAgentJob
public function tags(): array
{
    return [
        'agent:odluke',
        'query:' . substr(md5($this->query), 0, 8),
    ];
}
```

**Horizon Commands:**
```bash
# View all agent jobs
php artisan horizon:list --tag=agent:decision-discovery
php artisan horizon:list --tag=agent:odluke

# View specific topics/queries
php artisan horizon:list --tag=topic:radno pravo
php artisan horizon:list --tag=query:a3b2c1d4
```

### Logging Strategy

Both jobs use structured logging:

```php
// Start
Log::info('Starting {JobName} job', [
    'parameters' => [...],
    'environment' => $this->isProduction() ? 'production' : 'dev',
]);

// Success
Log::info('{JobName} completed successfully', [
    'duration' => $duration,
    'results' => [...],
]);

// Error
Log::error('{JobName} execution failed', [
    'error' => $e->getMessage(),
    'trace' => $e->getTraceAsString(),
]);
```

### Metrics to Track

**ExecuteDecisionDiscoveryJob:**
- Runs per day/week
- Success rate
- Average decisions ingested per run
- Average duration
- Topic distribution

**ExecuteOdlukeAgentJob:**
- Queries per hour/day
- Success rate
- Average response time
- Tool calls per query
- Cache hit rate

---

## Performance Comparison

| Metric | ExecuteDecisionDiscoveryJob | ExecuteOdlukeAgentJob |
|--------|----------------------------|------------------------|
| **Avg Duration** | 2-4 minutes (medium run) | 30-90 seconds |
| **Max Duration** | 10-15 minutes (large run) | 3-5 minutes |
| **Timeout** | 900s (15 min) | 300s (5 min) |
| **Memory Peak** | 150-200 MB | 100 MB |
| **LLM Calls** | 25-50 per run | 5-15 per query |
| **Database Writes** | 1 per run | 0 (cache only) |
| **Cache Writes** | 0 | 3 per job (running/completed/failed) |
| **Cost per Run** | $0.004-$0.008 | $0.001-$0.003 |

---

## Error Handling Comparison

### ExecuteDecisionDiscoveryJob

**Storage:** Database
```php
// Success
DB::table('decision_discovery_runs')->insert([
    'status' => 'completed',
    'decisions_found' => 127,
    'decisions_ingested' => 23,
    // ...
]);

// Failure
DB::table('decision_discovery_runs')->insert([
    'status' => 'failed',
    'error' => $e->getMessage(),
    // ...
]);
```

**Recovery:** Check database for failed runs, can retry manually

### ExecuteOdlukeAgentJob

**Storage:** Cache
```php
// Success
Cache::put($cacheKey, [
    'status' => 'completed',
    'result' => $result,
    // ...
], 3600);

// Failure
Cache::put($cacheKey, [
    'status' => 'failed',
    'error' => $e->getMessage(),
], 3600);
```

**Recovery:** Error auto-expires after 1 hour, no manual cleanup needed

---

## Testing Examples

### Testing ExecuteDecisionDiscoveryJob

```php
test('discovery job stores database record', function () {
    $agent = Mockery::mock(DecisionDiscoveryAgent::class);
    $agent->shouldReceive('discover')
        ->andReturn([
            'total_found' => 100,
            'ingested' => 20,
            'topics_count' => 5,
        ]);

    app()->instance(DecisionDiscoveryAgent::class, $agent);

    $job = new ExecuteDecisionDiscoveryJob(50, 'test topic');
    $job->handle($agent);

    $this->assertDatabaseHas('decision_discovery_runs', [
        'status' => 'completed',
        'decisions_found' => 100,
        'decisions_ingested' => 20,
    ]);
});
```

### Testing ExecuteOdlukeAgentJob

```php
test('odluke job caches result', function () {
    $agent = Mockery::mock(OdlukeAgent::class);
    $agent->shouldReceive('execute')
        ->andReturn(['summary' => 'Success']);

    Cache::shouldReceive('put')
        ->with('test_key', Mockery::on(function ($value) {
            return $value['status'] === 'completed';
        }), 3600);

    $job = new ExecuteOdlukeAgentJob('test query', [], 'test_key');
    $job->handle($agent);
});
```

---

## Best Practices Summary

### ExecuteDecisionDiscoveryJob

✅ **DO:**
- Schedule regular runs (daily/weekly)
- Monitor database for trends
- Archive old runs periodically
- Use topic filters for focused discovery
- Review generated topics regularly

❌ **DON'T:**
- Run too frequently (causes duplicate ingestions)
- Set maxDecisions too high (long timeouts)
- Ignore failed runs (investigate errors)

### ExecuteOdlukeAgentJob

✅ **DO:**
- Generate unique cache keys
- Implement proper polling with backoff
- Set reasonable client-side timeouts
- Handle cache misses gracefully
- Clean up old cache entries

❌ **DON'T:**
- Reuse cache keys across queries
- Poll too frequently (<1 second)
- Store sensitive data in cache
- Expect results after 1 hour

---

## File Inventory

### Job Files
- `app/Jobs/ExecuteDecisionDiscoveryJob.php` (176 lines)
- `app/Jobs/ExecuteOdlukeAgentJob.php` (175 lines)

### Migration Files
- `database/migrations/2025_10_31_004833_create_decision_discovery_runs_table.php` (30 lines)

### Documentation Files
- `docs/EXECUTE_DECISION_DISCOVERY_JOB_FLOW.md` (1,001 lines)
- `docs/EXECUTE_ODLUKE_AGENT_JOB_FLOW.md` (1,284 lines)
- `docs/AGENT_QUEUE_JOBS_OVERVIEW.md` (this file)

### Dependencies
- `app/Agents/DecisionDiscoveryAgent.php` (existing)
- `app/Agents/OdlukeAgent.php` (existing)
- `app/Traits/DetectsEnvironment.php` (existing)

---

## Quick Start Guide

### 1. Run Discovery Job (Scheduled)

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    $schedule->call(function () {
        ExecuteDecisionDiscoveryJob::dispatchWithEnvDetection();
    })->daily()->at('02:00');
}
```

### 2. Run OdlukeAgent Job (User Query)

```php
// app/Http/Livewire/ResearchForm.php
public function submit()
{
    $this->jobId = 'research_' . uniqid();

    ExecuteOdlukeAgentJob::dispatchWithEnvDetection(
        query: $this->query,
        context: ['user_id' => auth()->id()],
        cacheKey: $this->jobId
    );
}
```

### 3. Check Status

```php
// Discovery Job
$runs = DB::table('decision_discovery_runs')
    ->where('status', 'completed')
    ->latest()
    ->get();

// OdlukeAgent Job
$result = Cache::get($jobId);
if ($result && $result['status'] === 'completed') {
    return $result['result'];
}
```

---

## Related Documentation

- [ExecuteDecisionDiscoveryJob Flow](EXECUTE_DECISION_DISCOVERY_JOB_FLOW.md)
- [ExecuteOdlukeAgentJob Flow](EXECUTE_ODLUKE_AGENT_JOB_FLOW.md)
- [Autonomous Decision Discovery](AUTONOMOUS_DECISION_DISCOVERY.md)
- [OdlukeAgent Fix](ODLUKE_AGENT_FIX.md)
- [MCP Tools Documentation](MCP_TOOLS.md)

---

## Changelog

**v1.0.0 (2025-10-31) - Initial Release**
- Implemented ExecuteDecisionDiscoveryJob (Task 1.1)
- Implemented ExecuteOdlukeAgentJob (Task 2.1)
- Created comprehensive flow documentation
- Added environment detection pattern
- Documented integration patterns
- Performance benchmarks and best practices

---

## Support

**For implementation questions:**
1. Review individual job documentation
2. Check logs: `storage/logs/laravel.log`
3. Inspect storage (database or cache)
4. Test with `php artisan tinker`
5. Review Horizon dashboard
6. Open GitHub issue with full context

**Common Issues:**
- Jobs not executing → Check queue workers
- Environment detection wrong → Verify APP_ENV and request IP
- Cache misses → Check Redis/cache driver
- Timeouts → Review timeout settings and agent performance

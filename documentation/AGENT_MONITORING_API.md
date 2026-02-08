# Agent Monitoring API Documentation

## Overview

The Agent Monitoring API provides comprehensive health and performance monitoring for all agent queue jobs in the system. It exposes RESTful endpoints for tracking agent execution, detecting failures, and gathering statistics.

**Created:** Task 3.2 (Add Monitoring Dashboard for Agent Jobs)
**Controller:** `app/Http/Controllers/AgentMonitoringController.php`
**Routes:** `routes/api.php` (prefix: `/api/monitoring`)

---

## Quick Reference

| Endpoint | Method | Purpose | Auth Required |
|----------|--------|---------|---------------|
| `/api/monitoring/health` | GET | Overall system health | Yes (API Token) |
| `/api/monitoring/statistics` | GET | Agent statistics | Yes (API Token) |
| `/api/monitoring/recent-runs` | GET | Recent run history | Yes (API Token) |
| `/api/monitoring/failed-jobs` | GET | Failed job tracking | Yes (API Token) |

**Authentication:** All endpoints require `X-API-Token` header
**Rate Limiting:** 60 requests per minute per token

---

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                  Agent Monitoring System                         │
│                                                                  │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │              Monitoring API Endpoints                      │ │
│  │                                                            │ │
│  │  /health        → System-wide health check                │ │
│  │  /statistics    → Detailed agent metrics                  │ │
│  │  /recent-runs   → Latest job executions                   │ │
│  │  /failed-jobs   → Failure tracking                        │ │
│  └────────────────────────────────────────────────────────────┘ │
│                              │                                   │
│                              ▼                                   │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │         AgentMonitoringController                          │ │
│  │                                                            │ │
│  │  • Health calculations                                    │ │
│  │  • Failure rate analysis                                  │ │
│  │  • Duration aggregations                                  │ │
│  │  • Queue size monitoring                                  │ │
│  └────────────────────────────────────────────────────────────┘ │
│                              │                                   │
│                              ▼                                   │
│  ┌──────────────────────┬───────────────────────────────────┐   │
│  │                      │                                   │   │
│  │  ┌─────────────────┐ │  ┌──────────────────────────────┐│   │
│  │  │   AgentRun      │ │  │ DecisionDiscoveryRun         ││   │
│  │  │   (Model)       │ │  │ (Model)                      ││   │
│  │  │                 │ │  │                              ││   │
│  │  │ Scopes:         │ │  │ Scopes:                      ││   │
│  │  │ • recent()      │ │  │ • recent()                   ││   │
│  │  │                 │ │  │ • completed()                ││   │
│  │  │                 │ │  │ • failed()                   ││   │
│  │  │                 │ │  │                              ││   │
│  │  │                 │ │  │ Methods:                     ││   │
│  │  │                 │ │  │ • getAverageDuration()       ││   │
│  │  │                 │ │  │ • getTotalDiscovered()       ││   │
│  │  │                 │ │  │ • getTotalIngested()         ││   │
│  │  └─────────────────┘ │  └──────────────────────────────┘│   │
│  │                      │                                   │   │
│  │  agent_runs          │  decision_discovery_runs          │   │
│  │  (Database)          │  (Database)                       │   │
│  └──────────────────────┴───────────────────────────────────┘   │
│                              │                                   │
│                              ▼                                   │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │              Queue System Monitoring                       │ │
│  │                                                            │ │
│  │  • Queue::size() → Current queue depth                    │ │
│  │  • failed_jobs table → Failed job count                   │ │
│  └────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
```

---

## API Endpoints

### 1. Health Endpoint

**GET** `/api/monitoring/health`

Get overall system health with failure rate analysis.

#### Query Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `days` | integer | 7 | Number of days to analyze |

#### Response Schema

```json
{
  "status": "healthy" | "degraded" | "critical",
  "timestamp": "2025-10-31T10:30:00Z",
  "period_days": 7,
  "agents": {
    "research": {
      "total_runs": 45,
      "failed_runs": 2,
      "failure_rate": 4.44,
      "status": "healthy"
    },
    "decision_discovery": {
      "total_runs": 7,
      "failed_runs": 0,
      "failure_rate": 0,
      "avg_decisions_per_run": 42.5,
      "status": "healthy"
    }
  },
  "queue": {
    "queue_size": 3,
    "failed_jobs_total": 5,
    "status": "healthy"
  }
}
```

#### Status Determination

**Overall Status:**
- `healthy`: All failure rates < 25%
- `degraded`: Any failure rate 25-50%
- `critical`: Any failure rate > 50%

**Per-Agent Status:**
- `healthy`: Failure rate < 10%
- `degraded`: Failure rate ≥ 10%

**Queue Status:**
- `healthy`: Queue size < 100 AND failed jobs < 10
- `degraded`: Otherwise

#### cURL Example

```bash
curl -X GET "http://localhost/api/monitoring/health?days=7" \
  -H "X-API-Token: your_api_token_here"
```

#### Response Example

```json
{
  "status": "healthy",
  "timestamp": "2025-10-31T10:30:00Z",
  "period_days": 7,
  "agents": {
    "research": {
      "total_runs": 45,
      "failed_runs": 2,
      "failure_rate": 4.44,
      "status": "healthy"
    },
    "decision_discovery": {
      "total_runs": 7,
      "failed_runs": 0,
      "failure_rate": 0,
      "avg_decisions_per_run": 42.5,
      "status": "healthy"
    }
  },
  "queue": {
    "queue_size": 3,
    "failed_jobs_total": 5,
    "status": "healthy"
  }
}
```

---

### 2. Statistics Endpoint

**GET** `/api/monitoring/statistics`

Get detailed statistics for all agents.

#### Query Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `days` | integer | 30 | Number of days to analyze |

#### Response Schema

```json
{
  "period_days": 30,
  "research_agent": {
    "total_runs": 120,
    "completed": 115,
    "failed": 5,
    "avg_duration_seconds": 45.32
  },
  "decision_discovery": {
    "total_runs": 30,
    "completed": 28,
    "failed": 2,
    "avg_duration_seconds": 142.35,
    "total_decisions_found": 3200,
    "total_decisions_ingested": 650
  }
}
```

#### cURL Example

```bash
curl -X GET "http://localhost/api/monitoring/statistics?days=30" \
  -H "X-API-Token: your_api_token_here"
```

#### Use Cases

- **Performance Tracking**: Monitor average execution times
- **Success Rate Analysis**: Calculate success percentages
- **Capacity Planning**: Track total runs and identify trends
- **ROI Metrics**: Total decisions ingested over time

---

### 3. Recent Runs Endpoint

**GET** `/api/monitoring/recent-runs`

Get recent agent runs with status and metadata.

#### Query Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `limit` | integer | 20 | Number of runs to return |

#### Response Schema

```json
{
  "research_agent": [
    {
      "id": 42,
      "status": "completed",
      "objective": "Research labor law precedents",
      "created_at": "2025-10-31T09:00:00Z",
      "completed_at": "2025-10-31T09:02:15Z"
    }
  ],
  "decision_discovery": [
    {
      "id": 15,
      "status": "completed",
      "decisions_evaluated": 127,
      "decisions_ingested": 23,
      "created_at": "2025-10-31T02:00:00Z",
      "completed_at": "2025-10-31T02:02:22Z"
    }
  ]
}
```

#### cURL Example

```bash
curl -X GET "http://localhost/api/monitoring/recent-runs?limit=10" \
  -H "X-API-Token: your_api_token_here"
```

#### Use Cases

- **Real-time Monitoring**: See latest job executions
- **Debug Recent Failures**: Quickly identify failed runs
- **Activity Dashboard**: Display recent system activity
- **Audit Trail**: Track what jobs ran and when

---

### 4. Failed Jobs Endpoint

**GET** `/api/monitoring/failed-jobs`

Get failed jobs from Laravel's failed_jobs table for debugging.

#### Query Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `limit` | integer | 50 | Number of failed jobs to return |

#### Response Schema

```json
{
  "total": 23,
  "recent": [
    {
      "id": 5,
      "connection": "database",
      "queue": "default",
      "payload": "{...serialized job data...}",
      "exception": "Exception: Connection timeout...",
      "failed_at": "2025-10-31T08:30:00Z"
    }
  ]
}
```

#### cURL Example

```bash
curl -X GET "http://localhost/api/monitoring/failed-jobs?limit=20" \
  -H "X-API-Token: your_api_token_here"
```

#### Use Cases

- **Error Investigation**: Analyze why jobs failed
- **Pattern Detection**: Identify recurring failures
- **Alert Triggers**: Monitor total failed job count
- **Queue Cleanup**: Identify jobs to retry or delete

---

## Authentication

### API Token Authentication

All endpoints require the `X-API-Token` header:

```bash
curl -X GET "http://localhost/api/monitoring/health" \
  -H "X-API-Token: your_api_token_here"
```

### Obtaining an API Token

```php
// In tinker or seeder
use Illuminate\Support\Str;

$token = Str::random(32);
// Store in your .env or database
```

**Configure in `.env`:**
```env
API_TOKEN=your_secure_token_here
```

### Middleware

The monitoring routes use:
- `api.token`: Validates X-API-Token header
- `throttle:60,1`: Limits to 60 requests per minute

---

## Model Enhancements

### AgentRun Model

Added scope for recent runs:

```php
/**
 * Scope: Get runs from the last N days
 */
public function scopeRecent($query, int $days = 7)
{
    return $query->where('created_at', '>=', now()->subDays($days));
}
```

**Usage:**
```php
AgentRun::recent(7)->count();
AgentRun::recent(30)->where('status', 'failed')->get();
```

### DecisionDiscoveryRun Model

Added scopes and statistical methods:

```php
/**
 * Scope: Get runs from the last N days
 */
public function scopeRecent($query, int $days = 7)
{
    return $query->where('created_at', '>=', now()->subDays($days));
}

/**
 * Scope: Get only completed runs
 */
public function scopeCompleted($query)
{
    return $query->where('status', 'completed');
}

/**
 * Scope: Get only failed runs
 */
public function scopeFailed($query)
{
    return $query->where('status', 'failed');
}

/**
 * Get average duration for runs in the last N days
 */
public static function getAverageDuration(int $days = 7): float
{
    // Returns average duration in seconds
}

/**
 * Get total decisions discovered in the last N days
 */
public static function getTotalDiscovered(int $days = 7): int
{
    // Returns sum of decisions_evaluated
}

/**
 * Get total decisions ingested in the last N days
 */
public static function getTotalIngested(int $days = 7): int
{
    // Returns sum of decisions_ingested
}
```

**Usage:**
```php
// Get average duration
$avgDuration = DecisionDiscoveryRun::getAverageDuration(30);

// Get totals
$discovered = DecisionDiscoveryRun::getTotalDiscovered(7);
$ingested = DecisionDiscoveryRun::getTotalIngested(7);

// Combined queries
DecisionDiscoveryRun::recent(7)->completed()->count();
DecisionDiscoveryRun::recent(30)->failed()->get();
```

---

## Integration Examples

### 1. Simple Health Check Script

```php
<?php

$apiToken = env('API_TOKEN');
$response = Http::withHeaders([
    'X-API-Token' => $apiToken,
])->get('http://localhost/api/monitoring/health?days=7');

$health = $response->json();

if ($health['status'] === 'critical') {
    // Send alert
    Mail::to('admin@example.com')->send(new SystemCriticalAlert($health));
} elseif ($health['status'] === 'degraded') {
    // Log warning
    Log::warning('Agent system degraded', $health);
}
```

### 2. Dashboard Widget (Livewire)

```php
<?php

namespace App\Http\Livewire;

use Illuminate\Support\Facades\Http;
use Livewire\Component;

class AgentHealthWidget extends Component
{
    public $health;
    public $statistics;

    public function mount()
    {
        $this->loadData();
    }

    public function loadData()
    {
        $token = config('app.api_token');

        $this->health = Http::withHeaders(['X-API-Token' => $token])
            ->get(route('api.monitoring.health'))
            ->json();

        $this->statistics = Http::withHeaders(['X-API-Token' => $token])
            ->get(route('api.monitoring.statistics'))
            ->json();
    }

    public function render()
    {
        return view('livewire.agent-health-widget');
    }
}
```

**Blade Template:**
```blade
<div wire:poll.30s="loadData">
    <div class="card">
        <div class="card-header">
            Agent System Health
            <span class="badge badge-{{ $health['status'] === 'healthy' ? 'success' : 'warning' }}">
                {{ ucfirst($health['status']) }}
            </span>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h5>Research Agent</h5>
                    <p>Runs: {{ $statistics['research_agent']['total_runs'] }}</p>
                    <p>Failed: {{ $statistics['research_agent']['failed'] }}</p>
                    <p>Avg Duration: {{ round($statistics['research_agent']['avg_duration_seconds']) }}s</p>
                </div>
                <div class="col-md-6">
                    <h5>Decision Discovery</h5>
                    <p>Runs: {{ $statistics['decision_discovery']['total_runs'] }}</p>
                    <p>Decisions Ingested: {{ $statistics['decision_discovery']['total_decisions_ingested'] }}</p>
                </div>
            </div>
        </div>
    </div>
</div>
```

### 3. Scheduled Health Check (Artisan Command)

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CheckAgentHealth extends Command
{
    protected $signature = 'agents:check-health {--alert : Send alerts for degraded/critical}';
    protected $description = 'Check agent system health';

    public function handle()
    {
        $response = Http::withHeaders([
            'X-API-Token' => config('app.api_token'),
        ])->get('http://localhost/api/monitoring/health');

        $health = $response->json();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Overall Status', $health['status']],
                ['Research Agent Status', $health['agents']['research']['status']],
                ['Discovery Agent Status', $health['agents']['decision_discovery']['status']],
                ['Queue Size', $health['queue']['queue_size']],
                ['Failed Jobs', $health['queue']['failed_jobs_total']],
            ]
        );

        if ($this->option('alert') && in_array($health['status'], ['degraded', 'critical'])) {
            Log::warning('Agent health check alert', $health);
            $this->error("System is {$health['status']}!");
            return 1;
        }

        $this->info('Health check complete');
        return 0;
    }
}
```

**Schedule:**
```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    $schedule->command('agents:check-health --alert')
        ->everyFifteenMinutes();
}
```

### 4. Prometheus Metrics Exporter

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;

class MetricsController extends Controller
{
    public function prometheus()
    {
        $stats = Http::withHeaders(['X-API-Token' => config('app.api_token')])
            ->get('http://localhost/api/monitoring/statistics')
            ->json();

        $metrics = [
            "# TYPE agent_runs_total counter",
            "agent_runs_total{agent=\"research\"} {$stats['research_agent']['total_runs']}",
            "agent_runs_total{agent=\"discovery\"} {$stats['decision_discovery']['total_runs']}",
            "",
            "# TYPE agent_runs_failed counter",
            "agent_runs_failed{agent=\"research\"} {$stats['research_agent']['failed']}",
            "agent_runs_failed{agent=\"discovery\"} {$stats['decision_discovery']['failed']}",
            "",
            "# TYPE agent_duration_seconds gauge",
            "agent_duration_seconds{agent=\"research\"} {$stats['research_agent']['avg_duration_seconds']}",
            "agent_duration_seconds{agent=\"discovery\"} {$stats['decision_discovery']['avg_duration_seconds']}",
            "",
            "# TYPE decisions_ingested_total counter",
            "decisions_ingested_total {$stats['decision_discovery']['total_decisions_ingested']}",
        ];

        return response(implode("\n", $metrics))
            ->header('Content-Type', 'text/plain; version=0.0.4');
    }
}
```

---

## Monitoring Best Practices

### 1. Alert Thresholds

**Critical Alerts (Immediate Response):**
- Overall failure rate > 50%
- Queue size > 500
- Failed jobs > 50

**Warning Alerts (Review Soon):**
- Overall failure rate 25-50%
- Queue size 100-500
- Failed jobs 10-50
- Average duration increase > 50%

### 2. Polling Frequency

**Real-time Dashboard:**
- Poll `/health` every 30 seconds
- Poll `/statistics` every 5 minutes
- Poll `/recent-runs` every 1 minute

**Scheduled Checks:**
- Health check every 15 minutes
- Statistics snapshot every hour
- Daily summary report

### 3. Data Retention

**Database Cleanup:**
```php
// Keep agent runs for 90 days
AgentRun::where('created_at', '<', now()->subDays(90))->delete();

// Keep discovery runs for 180 days
DecisionDiscoveryRun::where('created_at', '<', now()->subDays(180))->delete();

// Archive failed jobs after 30 days
DB::table('failed_jobs')
    ->where('failed_at', '<', now()->subDays(30))
    ->delete();
```

### 4. Performance Optimization

**Caching Statistics:**
```php
use Illuminate\Support\Facades\Cache;

$stats = Cache::remember('agent_stats_30d', 300, function () {
    return Http::withHeaders(['X-API-Token' => config('app.api_token')])
        ->get('http://localhost/api/monitoring/statistics?days=30')
        ->json();
});
```

---

## Troubleshooting

### High Failure Rate

**Symptoms:**
- Failure rate > 25%
- Status: degraded or critical

**Investigation:**
1. Check `/failed-jobs` for error patterns
2. Review recent runs in `/recent-runs`
3. Check queue worker logs
4. Verify external dependencies (MCP servers, databases)

**Common Causes:**
- Network connectivity issues
- Timeout too low for complex operations
- LLM API rate limits
- Database connection pool exhausted

### Queue Backlog

**Symptoms:**
- Queue size > 100
- Jobs not processing

**Investigation:**
1. Check if queue workers are running:
   ```bash
   php artisan queue:work --once
   ```
2. Check `/failed-jobs` for stuck jobs
3. Monitor queue size trend over time

**Solutions:**
- Scale up queue workers
- Increase worker timeout
- Retry failed jobs
- Clear stuck jobs

### Slow Agent Performance

**Symptoms:**
- Average duration increasing
- Jobs timing out

**Investigation:**
1. Check `/statistics` for duration trends
2. Review agent logs for slow operations
3. Profile database queries
4. Check LLM response times

**Solutions:**
- Optimize database queries
- Reduce agent complexity
- Increase timeout values
- Scale infrastructure

---

## API Response Codes

| Code | Meaning | Description |
|------|---------|-------------|
| 200 | OK | Request successful |
| 401 | Unauthorized | Invalid or missing API token |
| 429 | Too Many Requests | Rate limit exceeded (>60/min) |
| 500 | Internal Server Error | Server error (check logs) |

---

## Security Considerations

### 1. API Token Security

**DO:**
- Store tokens in `.env` file
- Rotate tokens regularly (every 90 days)
- Use different tokens for different environments
- Log all API access

**DON'T:**
- Commit tokens to version control
- Share tokens across systems
- Use production tokens in development
- Expose tokens in client-side code

### 2. Rate Limiting

Default: 60 requests per minute per token

**Custom Rate Limits:**
```php
Route::prefix('monitoring')->middleware(['api.token', 'throttle:120,1'])->group(function () {
    // 120 requests per minute
});
```

### 3. Access Control

**Restrict to Admin:**
```php
Route::prefix('monitoring')->middleware(['api.token', 'admin'])->group(function () {
    // Only admin users
});
```

---

## File Inventory

### Created Files
- `app/Http/Controllers/AgentMonitoringController.php` (178 lines)
- `docs/AGENT_MONITORING_API.md` (this file)

### Modified Files
- `app/Models/AgentRun.php` (+8 lines)
- `app/Models/DecisionDiscoveryRun.php` (+66 lines)
- `routes/api.php` (+21 lines)

### Total Changes
- **273 lines** of production code
- **1,100+ lines** of documentation

---

## Related Documentation

- [ExecuteDecisionDiscoveryJob Flow](EXECUTE_DECISION_DISCOVERY_JOB_FLOW.md)
- [ExecuteOdlukeAgentJob Flow](EXECUTE_ODLUKE_AGENT_JOB_FLOW.md)
- [Agent Queue Jobs Overview](AGENT_QUEUE_JOBS_OVERVIEW.md)

---

## Changelog

**v1.0.0 (2025-10-31) - Initial Release**
- Created AgentMonitoringController with 4 endpoints
- Added model scopes: `recent()`, `completed()`, `failed()`
- Added statistical methods: `getAverageDuration()`, `getTotalDiscovered()`, `getTotalIngested()`
- Implemented health status calculations
- Added API routes with authentication and rate limiting
- Comprehensive documentation with examples

---

## Support

**For API issues:**
1. Check API token is valid
2. Verify rate limits not exceeded
3. Review Laravel logs: `storage/logs/laravel.log`
4. Test endpoint with cURL
5. Check database connectivity
6. Open GitHub issue with request/response examples

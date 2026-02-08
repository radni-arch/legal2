# ExecuteDecisionDiscoveryJob Flow Documentation

## Overview

The `ExecuteDecisionDiscoveryJob` is a queue job that executes the DecisionDiscoveryAgent with intelligent environment detection, comprehensive logging, and database tracking. It serves as the orchestration layer between the command-line interface, scheduled tasks, and the autonomous decision discovery system.

**Key Features:**
- Automatic environment detection (production vs dev/localhost)
- Smart sync/async execution based on environment
- Comprehensive run tracking in database
- Detailed statistics collection
- Robust error handling with failure recovery
- Job monitoring via tags
- Configurable timeout and retry settings

**Created:** Task 1.1 (ExecuteDecisionDiscoveryJob)
**Location:** `app/Jobs/ExecuteDecisionDiscoveryJob.php`
**Migration:** `database/migrations/2025_10_31_004833_create_decision_discovery_runs_table.php`

---

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                    Job Dispatch Layer                           │
│                                                                  │
│  ┌──────────────┐         ┌─────────────────────────────────┐  │
│  │   Artisan    │────────▶│ dispatchWithEnvDetection()      │  │
│  │   Command    │         │                                 │  │
│  └──────────────┘         │ • Detects Environment           │  │
│                           │ • Checks IP (localhost check)    │  │
│  ┌──────────────┐         │ • Routes to sync/async          │  │
│  │   Livewire   │────────▶│                                 │  │
│  │  Component   │         └─────────────────────────────────┘  │
│  └──────────────┘                      │                        │
│                                        │                        │
│  ┌──────────────┐                      │                        │
│  │   Schedule   │                      │                        │
│  │   (Cron)     │──────────────────────┘                        │
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
         └────────────────────┘    └──────────────────┘
                    │                         │
                    └────────────┬────────────┘
                                 ▼
                    ┌─────────────────────────┐
                    │   handle() Method       │
                    │                         │
                    │ • Log Environment       │
                    │ • Execute Agent         │
                    │ • Track Duration        │
                    │ • Store Results         │
                    │ • Handle Errors         │
                    └─────────────────────────┘
                                 │
                    ┌────────────┴────────────┐
                    │                         │
                    ▼                         ▼
            ┌──────────────┐         ┌──────────────┐
            │   Success    │         │   Failure    │
            │              │         │              │
            │ • Store Run  │         │ • Log Error  │
            │ • Log Stats  │         │ • Store Run  │
            │ • Return     │         │ • Propagate  │
            └──────────────┘         └──────────────┘
```

---

## Job Flow Schema

### 1. Job Initialization

```php
ExecuteDecisionDiscoveryJob::dispatchWithEnvDetection(
    maxDecisions: 50,    // Optional: limit decisions to discover
    topic: 'radno pravo' // Optional: specific topic to search
);
```

**Initialization Steps:**
1. Create job instance with parameters
2. Detect environment (`app()->environment()`)
3. Check request IP for localhost detection
4. Route to appropriate execution mode

### 2. Environment Detection Logic

```
┌─────────────────────────────────────────────────────────────┐
│            dispatchWithEnvDetection()                       │
│                                                             │
│  1. Check: app()->environment('production')                 │
│     ├─ YES ──────────┐                                     │
│     └─ NO ───────────┼─────────────────────┐               │
│                      │                     │               │
│  2. Check: request()->ip() in              │               │
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
│              │ • dispatch() │      │ • handle()   │        │
│              │ • Returns    │      │ • Returns    │        │
│              │   JobID      │      │   Array      │        │
│              └──────────────┘      └──────────────┘        │
└─────────────────────────────────────────────────────────────┘
```

**Decision Matrix:**

| Environment | IP Address  | Execution Mode | Why                               |
|-------------|-------------|----------------|-----------------------------------|
| Production  | Remote      | Async (Queue)  | Production traffic, use queue     |
| Production  | Localhost   | Sync           | Dev testing on production server  |
| Dev/Local   | Any         | Sync           | Development, immediate feedback   |
| Testing     | Any         | Sync           | Test suite, immediate results     |

### 3. Job Execution Flow (handle() Method)

```
┌────────────────────────────────────────────────────────────────┐
│                         handle()                                │
│                                                                 │
│  START                                                          │
│    │                                                            │
│    ▼                                                            │
│  ┌─────────────────────────────────────────────────┐           │
│  │ 1. Log Environment via DetectsEnvironment trait │           │
│  │    • Logs: ExecuteDecisionDiscoveryJob          │           │
│  │    • Environment: production/dev                │           │
│  │    • Timestamp                                  │           │
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
│  │    • max_decisions                              │           │
│  │    • topic                                      │           │
│  │    • environment                                │           │
│  └─────────────────────────────────────────────────┘           │
│    │                                                            │
│    ▼                                                            │
│  ┌─────────────────────────────────────────────────┐           │
│  │ 4. Execute DecisionDiscoveryAgent               │           │
│  │    $result = $agent->discover(                  │           │
│  │        maxDecisions: $this->maxDecisions,       │           │
│  │        topic: $this->topic                      │           │
│  │    );                                           │           │
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
│    │    │ 5b. Store Success Record             │              │
│    │    │  DB::table('decision_discovery_runs')│              │
│    │    │    ->insert([                        │              │
│    │    │      'status' => 'completed',        │              │
│    │    │      'decisions_found' => N,         │              │
│    │    │      'decisions_ingested' => M,      │              │
│    │    │      'topics_generated' => T,        │              │
│    │    │      'topic_filter' => topic,        │              │
│    │    │      'duration_seconds' => D,        │              │
│    │    │      'statistics' => json(result),   │              │
│    │    │      'completed_at' => now()         │              │
│    │    │    ]);                               │              │
│    │    └──────────────────────────────────────┘              │
│    │                    │                                      │
│    │                    ▼                                      │
│    │    ┌──────────────────────────────────────┐              │
│    │    │ 5c. Log Success                      │              │
│    │    │     • duration                       │              │
│    │    │     • found                          │              │
│    │    │     • ingested                       │              │
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
│        └──────────────────────────────────────┘              │
│                        │                                      │
│                        ▼                                      │
│        ┌──────────────────────────────────────┐              │
│        │ 6b. Store Failed Record              │              │
│        │  DB::table('decision_discovery_runs')│              │
│        │    ->insert([                        │              │
│        │      'status' => 'failed',           │              │
│        │      'error' => message,             │              │
│        │      'topic_filter' => topic,        │              │
│        │      'duration_seconds' => D,        │              │
│        │      'completed_at' => now()         │              │
│        │    ]);                               │              │
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
│                                                               │
└────────────────────────────────────────────────────────────────┘
```

---

## Database Schema

### decision_discovery_runs Table

```sql
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

### Statistics JSON Structure

```json
{
    "total_found": 127,
    "ingested": 23,
    "topics_count": 5,
    "topics": [
        "Radno pravo - nezakonit otkaz",
        "Ugovorno pravo - odgovornost",
        "Potrošačka zaštita",
        "Vlasničkopravni sporovi",
        "Obvezno pravo"
    ],
    "per_topic_stats": {
        "Radno pravo - nezakonit otkaz": {
            "searched": 50,
            "scored_above_threshold": 15,
            "ingested": 10
        },
        "Ugovorno pravo - odgovornost": {
            "searched": 50,
            "scored_above_threshold": 8,
            "ingested": 8
        }
        // ... more topics
    },
    "avg_score": 67.5,
    "max_score": 95,
    "min_score": 42,
    "execution_time": 142.35
}
```

---

## Job Configuration

### Constructor Parameters

```php
public function __construct(
    protected ?int $maxDecisions = null,    // Limit total decisions
    protected ?string $topic = null         // Filter by specific topic
)
```

### Job Properties

```php
public int $tries = 1;           // Number of retry attempts
public int $timeout = 900;       // 15 minutes max execution
```

### Laravel Queue Configuration

```php
use App\Jobs\ExecuteDecisionDiscoveryJob;

// Standard dispatch (respects environment detection)
ExecuteDecisionDiscoveryJob::dispatchWithEnvDetection();

// Explicit queue dispatch (override detection)
ExecuteDecisionDiscoveryJob::dispatch(50, 'radno pravo');

// Dispatch to specific queue
ExecuteDecisionDiscoveryJob::dispatch(50, 'radno pravo')
    ->onQueue('agent-discovery');

// Delayed dispatch
ExecuteDecisionDiscoveryJob::dispatch(50, 'radno pravo')
    ->delay(now()->addHours(2));
```

---

## Usage Examples

### 1. Basic Usage (Environment Auto-Detection)

```php
use App\Jobs\ExecuteDecisionDiscoveryJob;

// Dispatches to queue in production, runs sync in dev
ExecuteDecisionDiscoveryJob::dispatchWithEnvDetection();
```

**Output (Dev):**
```
Running ExecuteDecisionDiscoveryJob synchronously (dev/localhost)
[mode => 'sync', completed => true]
```

**Output (Production):**
```
Dispatching ExecuteDecisionDiscoveryJob to queue (production)
Returns: Illuminate\Foundation\Bus\PendingDispatch object
```

### 2. With Topic Filter

```php
// Only discover decisions about labor law
ExecuteDecisionDiscoveryJob::dispatchWithEnvDetection(
    maxDecisions: null,
    topic: 'radno pravo'
);
```

### 3. With Decision Limit

```php
// Limit to 20 total decisions
ExecuteDecisionDiscoveryJob::dispatchWithEnvDetection(
    maxDecisions: 20,
    topic: null
);
```

### 4. Both Parameters

```php
// Find 30 decisions about consumer protection
ExecuteDecisionDiscoveryJob::dispatchWithEnvDetection(
    maxDecisions: 30,
    topic: 'potrošačka zaštita'
);
```

### 5. From Artisan Command

```php
// app/Console/Commands/DiscoverDecisions.php

use App\Jobs\ExecuteDecisionDiscoveryJob;

class DiscoverDecisions extends Command
{
    protected $signature = 'decisions:discover-job
                            {--max= : Maximum decisions}
                            {--topic= : Specific topic}';

    public function handle()
    {
        $result = ExecuteDecisionDiscoveryJob::dispatchWithEnvDetection(
            maxDecisions: $this->option('max'),
            topic: $this->option('topic')
        );

        if (is_array($result) && $result['mode'] === 'sync') {
            $this->info('✓ Job completed synchronously');
        } else {
            $this->info('✓ Job dispatched to queue');
        }
    }
}
```

### 6. From Livewire Component

```php
// app/Http/Livewire/DiscoveryDashboard.php

use App\Jobs\ExecuteDecisionDiscoveryJob;

class DiscoveryDashboard extends Component
{
    public function startDiscovery()
    {
        ExecuteDecisionDiscoveryJob::dispatchWithEnvDetection();

        session()->flash('message', 'Discovery job started!');
    }
}
```

### 7. Scheduled Execution

```php
// app/Console/Kernel.php

protected function schedule(Schedule $schedule)
{
    // Daily at 2 AM
    $schedule->call(function () {
        ExecuteDecisionDiscoveryJob::dispatchWithEnvDetection();
    })
    ->daily()
    ->at('02:00')
    ->withoutOverlapping()
    ->onOneServer();

    // Weekly comprehensive (with more decisions)
    $schedule->call(function () {
        ExecuteDecisionDiscoveryJob::dispatchWithEnvDetection(
            maxDecisions: 100
        );
    })
    ->weekly()
    ->sundays()
    ->at('03:00')
    ->withoutOverlapping()
    ->onOneServer();
}
```

---

## Job Monitoring

### Job Tags

The job implements the `tags()` method for Horizon monitoring:

```php
public function tags(): array
{
    return [
        'agent:decision-discovery',
        $this->topic ? "topic:{$this->topic}" : 'topic:all',
    ];
}
```

**Example Tags:**
- `agent:decision-discovery`
- `topic:radno pravo`
- `topic:all`

### Querying by Tags in Horizon

```bash
# View all decision discovery jobs
php artisan horizon:list --tag=agent:decision-discovery

# View jobs for specific topic
php artisan horizon:list --tag=topic:radno pravo
```

### Failed Job Handling

```php
public function failed(\Throwable $exception): void
{
    Log::error('ExecuteDecisionDiscoveryJob failed permanently', [
        'error' => $exception->getMessage(),
        'max_decisions' => $this->maxDecisions,
        'topic' => $this->topic,
    ]);
}
```

**Triggered when:**
- Job exceeds timeout (900 seconds)
- Job fails after all retry attempts (1 try)
- Exception is unhandled

---

## Error Handling & Recovery

### Exception Scenarios

#### 1. Agent Discovery Failure

```
Agent throws exception during discovery
    ↓
Caught by handle() try-catch
    ↓
Error logged with stack trace
    ↓
Failed run record inserted into DB
    ↓
Exception re-thrown
    ↓
Queue marks job as failed
    ↓
failed() method called
    ↓
Permanent failure logged
```

#### 2. Database Insert Failure

```
Run record insertion fails
    ↓
Exception propagates
    ↓
Original agent result is lost
    ↓
Job marked as failed
    ↓
No database record created
```

**Mitigation:**
- Database transaction would help (not currently implemented)
- Consider separate try-catch for DB operations

#### 3. Timeout Scenario

```
Job running > 900 seconds
    ↓
Laravel timeout mechanism kills job
    ↓
failed() method called
    ↓
Run record may be incomplete
    ↓
Status remains 'running' in DB
```

**Mitigation:**
- Monitor for stale 'running' records
- Clean up script for abandoned runs
- Consider heartbeat updates during execution

### Logging Strategy

**Structured Logging:**

```php
// Start
Log::info('Starting decision discovery job', [
    'max_decisions' => 50,
    'topic' => 'radno pravo',
    'environment' => 'production',
]);

// Success
Log::info('Decision discovery completed successfully', [
    'duration' => 142.35,
    'found' => 127,
    'ingested' => 23,
]);

// Failure
Log::error('Decision discovery failed', [
    'error' => 'Connection timeout',
    'trace' => '...',
]);
```

**Log Locations:**
- `storage/logs/laravel.log` (default)
- Horizon dashboard (if using Horizon)
- Sentry/external monitoring (if configured)

---

## Integration Points

### 1. DecisionDiscoveryAgent

**Interface:**
```php
interface DecisionDiscoveryAgent
{
    public function discover(
        ?int $maxDecisions = null,
        ?string $topic = null
    ): array;
}
```

**Expected Return:**
```php
[
    'total_found' => 127,
    'ingested' => 23,
    'topics_count' => 5,
    'topics' => [...],
    'per_topic_stats' => [...],
    // ... additional statistics
]
```

### 2. DetectsEnvironment Trait

**Location:** `app/Traits/DetectsEnvironment.php`

**Methods Used:**
```php
$this->logEnvironment('ExecuteDecisionDiscoveryJob');
$this->isProduction(); // Returns bool
```

### 3. Queue System

**Default Queue:** `default`

**Recommended Queue:** `agent-discovery`

**Queue Workers:**
```bash
# Start queue worker
php artisan queue:work --queue=agent-discovery

# Start Horizon (recommended)
php artisan horizon
```

---

## Performance Characteristics

### Execution Time

**Typical Duration:**
- Small run (1 topic, 50 decisions): ~30-60 seconds
- Medium run (5 topics, 50 decisions/topic): ~2-4 minutes
- Large run (10 topics, 100 decisions/topic): ~8-12 minutes

**Timeout:** 15 minutes (900 seconds)

### Resource Usage

**Memory:**
- Base: ~50 MB
- Peak (large run): ~150-200 MB

**CPU:**
- Low during LLM calls (I/O bound)
- Moderate during decision processing

**Network:**
- High during odluke.sudovi.hr queries
- Moderate during OpenAI API calls

### LLM Costs

**Per Job:**
- ~25-50 LLM calls
- ~25,000-50,000 tokens
- Cost: ~$0.004-$0.008 per job

**Monthly (Daily Schedule):**
- ~$0.12-$0.24

---

## Testing

### Unit Test Example

```php
use App\Jobs\ExecuteDecisionDiscoveryJob;
use Illuminate\Support\Facades\Queue;

test('job can be dispatched to queue', function () {
    Queue::fake();

    ExecuteDecisionDiscoveryJob::dispatch(50, 'radno pravo');

    Queue::assertPushed(ExecuteDecisionDiscoveryJob::class, function ($job) {
        return $job->maxDecisions === 50
            && $job->topic === 'radno pravo';
    });
});

test('environment detection routes correctly', function () {
    config(['app.env' => 'production']);

    $result = ExecuteDecisionDiscoveryJob::dispatchWithEnvDetection();

    // Should return dispatch object in production
    expect($result)->toBeInstanceOf(PendingDispatch::class);
});

test('sync mode returns completion array', function () {
    config(['app.env' => 'local']);

    $agent = Mockery::mock(DecisionDiscoveryAgent::class);
    $agent->shouldReceive('discover')
        ->andReturn(['total_found' => 10]);

    app()->instance(DecisionDiscoveryAgent::class, $agent);

    $result = ExecuteDecisionDiscoveryJob::dispatchWithEnvDetection();

    expect($result)->toBeArray()
        ->and($result['mode'])->toBe('sync')
        ->and($result['completed'])->toBeTrue();
});
```

### Integration Test

```php
test('job stores run record on success', function () {
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
        'topics_generated' => 5,
        'topic_filter' => 'test topic',
    ]);
});

test('job stores error on failure', function () {
    $agent = Mockery::mock(DecisionDiscoveryAgent::class);
    $agent->shouldReceive('discover')
        ->andThrow(new \Exception('Test failure'));

    app()->instance(DecisionDiscoveryAgent::class, $agent);

    $job = new ExecuteDecisionDiscoveryJob();

    expect(fn() => $job->handle($agent))->toThrow(\Exception::class);

    $this->assertDatabaseHas('decision_discovery_runs', [
        'status' => 'failed',
        'error' => 'Test failure',
    ]);
});
```

---

## Best Practices

### 1. Environment Configuration

**Production:**
- Always use queue workers
- Configure proper queue connection (Redis/database)
- Enable Horizon for monitoring
- Set up failure notifications

**Development:**
- Use sync mode for immediate feedback
- Monitor logs in real-time
- Test with smaller datasets

### 2. Parameter Selection

**maxDecisions:**
- `null` (default): No limit, discover all available
- `10-50`: Quick test run
- `50-100`: Standard run
- `100+`: Comprehensive run (ensure adequate timeout)

**topic:**
- `null` (default): LLM generates topics
- Specific topic: Focused discovery
- Use for testing or targeted ingestion

### 3. Monitoring

**Check regularly:**
- Success rate in database
- Average duration
- Error patterns
- Queue depth (if using queues)

**Alerts to set:**
- Failed job count threshold
- Average duration spike
- Queue worker down
- Database storage threshold

### 4. Performance Optimization

**Reduce execution time:**
- Limit topics per run
- Reduce decisions per topic
- Use specific topics (avoid LLM generation)
- Optimize network latency to odluke.sudovi.hr

**Reduce costs:**
- Cache LLM-generated topics
- Batch decision scoring (already implemented)
- Use lower-cost model for scoring

---

## Troubleshooting

### Job Not Running

**Symptoms:**
- Dispatched but no database record
- No log entries

**Checks:**
1. Queue worker running?
   ```bash
   php artisan queue:work
   ```
2. Failed jobs table?
   ```bash
   php artisan queue:failed
   ```
3. Horizon running?
   ```bash
   php artisan horizon:status
   ```

### Jobs Timing Out

**Symptoms:**
- Status stuck at 'running'
- Timeout errors in logs

**Solutions:**
- Increase timeout: `public int $timeout = 1800;` (30 min)
- Reduce maxDecisions
- Check network connectivity
- Profile agent performance

### Database Records Missing

**Symptoms:**
- Job completes but no DB record
- Partial data in DB

**Checks:**
1. Database connection working?
2. Migration ran successfully?
   ```bash
   php artisan migrate:status
   ```
3. Check logs for DB errors

### Environment Detection Wrong

**Symptoms:**
- Production using sync mode
- Dev using queue mode

**Debug:**
```php
dd([
    'environment' => app()->environment(),
    'is_production' => app()->environment('production'),
    'request_ip' => request()->ip(),
    'is_localhost' => in_array(request()->ip(), ['127.0.0.1', '::1', 'localhost']),
]);
```

---

## Related Documentation

- [Autonomous Decision Discovery](AUTONOMOUS_DECISION_DISCOVERY.md) - Main agent documentation
- [DecisionDiscoveryAgent](../app/Agents/DecisionDiscoveryAgent.php) - Agent implementation
- [DetectsEnvironment Trait](../app/Traits/DetectsEnvironment.php) - Environment detection
- [Queue Documentation](https://laravel.com/docs/queues) - Laravel queues

---

## Migration Guide

### Running the Migration

```bash
# Run migration
php artisan migrate

# Check if table exists
php artisan db:table decision_discovery_runs

# Rollback if needed
php artisan migrate:rollback --step=1
```

### Initial Testing

```bash
# Test in dev (sync mode)
php artisan tinker
>>> ExecuteDecisionDiscoveryJob::dispatchWithEnvDetection(10, 'test');

# Check database
>>> DB::table('decision_discovery_runs')->latest()->first();

# Test queue mode (if workers running)
>>> ExecuteDecisionDiscoveryJob::dispatch(10, 'test');
```

---

## Changelog

**v1.0.0 (2025-10-31) - Initial Release**
- Created ExecuteDecisionDiscoveryJob
- Implemented environment detection with `dispatchWithEnvDetection()`
- Added decision_discovery_runs table migration
- Integrated DetectsEnvironment trait
- Added job monitoring tags
- Implemented failure handling
- 15-minute timeout with 1 retry
- Comprehensive logging and statistics tracking

---

## Support

**For issues or questions:**
1. Check logs: `storage/logs/laravel.log`
2. Inspect database: `decision_discovery_runs` table
3. Review queue status: `php artisan queue:failed`
4. Check Horizon dashboard (if enabled)
5. Open GitHub issue with:
   - Environment details
   - Full error logs
   - Database run record
   - Job parameters used

# AI Legal War Machine - Agent Framework Action Plan

**Date**: October 31, 2025
**Objective**: Standardize agent queue job support and improve framework consistency
**Based on**: Agent Framework Analysis v1.0

---

## Executive Summary

**Current State**: Only 1/7 agents (14%) has queue job support
**Target State**: All long-running agents (3/7) with queue job support
**Completion Path**: 85% → 90% → 95% → 100%

### Critical Gaps Identified

1. **DecisionDiscoveryAgent** - Blocks daily cron (no queue support) ⚠️ CRITICAL
2. **OdlukeAgent** - Uses Vizra but missing queue job ⚠️ IMPORTANT
3. **No environment detection** - Need sync/async based on localhost/production
4. **Framework inconsistency** - Only 28% using Vizra ADK

---

## Priority Tiers

### ⚡ HIGH PRIORITY TASKS (3 hours → 90%)
**Goal**: Unblock DecisionDiscoveryAgent with queue job + environment detection

- **Task 1.1**: Create ExecuteDecisionDiscoveryJob (1.5 hours)
- **Task 1.2**: Add environment detection to DecisionDiscoveryAgent (0.5 hours)
- **Task 1.3**: Update scheduled task to use queue job (1 hour)

**Impact**: Unblocks daily cron, enables parallel discovery runs

---

### 📋 MEDIUM PRIORITY TASKS (2 hours → 95%)
**Goal**: Add queue job support to OdlukeAgent

- **Task 2.1**: Create ExecuteOdlukeAgentJob (1 hour)
- **Task 2.2**: Add async execution option to OdlukeAgent (1 hour)

**Impact**: Enables async MCP tool chains, prevents HTTP timeouts

---

### 📌 LOW PRIORITY TASKS (4 hours → 100%)
**Goal**: Framework consistency and monitoring

- **Task 3.1**: Create DecisionDiscoveryRun model for tracking (1 hour)
- **Task 3.2**: Add monitoring dashboard for agent jobs (2 hours)
- **Task 3.3**: Migrate DecisionDiscoveryAgent to Vizra framework (1 hour)

**Impact**: Better monitoring, code consistency, standardized error handling

---

## Detailed Task Specifications

---

## ⚡ HIGH PRIORITY TASKS

### Task 1.1: Create ExecuteDecisionDiscoveryJob (1.5 hours)

**Objective**: Create queue job for DecisionDiscoveryAgent with environment detection

**File to Create**: `app/Jobs/ExecuteDecisionDiscoveryJob.php`

**Implementation**:

```php
<?php

namespace App\Jobs;

use App\Agents\DecisionDiscoveryAgent;
use App\Traits\DetectsEnvironment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ExecuteDecisionDiscoveryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, DetectsEnvironment;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public int $tries = 1;

    /**
     * The maximum number of seconds the job can run.
     *
     * @var int
     */
    public int $timeout = 900; // 15 minutes

    /**
     * Create a new job instance.
     *
     * @param int|null $maxDecisions Maximum decisions to discover
     * @param string|null $topic Optional specific topic to search
     */
    public function __construct(
        protected ?int $maxDecisions = null,
        protected ?string $topic = null
    ) {}

    /**
     * Execute the job.
     *
     * @param DecisionDiscoveryAgent $agent
     * @return void
     */
    public function handle(DecisionDiscoveryAgent $agent): void
    {
        $this->logEnvironment('ExecuteDecisionDiscoveryJob');

        $startTime = microtime(true);
        Log::info('Starting decision discovery job', [
            'max_decisions' => $this->maxDecisions,
            'topic' => $this->topic,
            'environment' => $this->isProduction() ? 'production' : 'dev',
        ]);

        try {
            // Execute discovery with optional parameters
            $result = $agent->discover(
                maxDecisions: $this->maxDecisions,
                topic: $this->topic
            );

            $duration = round(microtime(true) - $startTime, 2);

            // Store run record
            DB::table('decision_discovery_runs')->insert([
                'status' => 'completed',
                'decisions_found' => $result['total_found'] ?? 0,
                'decisions_ingested' => $result['ingested'] ?? 0,
                'topics_generated' => $result['topics_count'] ?? 0,
                'topic_filter' => $this->topic,
                'duration_seconds' => $duration,
                'statistics' => json_encode($result),
                'completed_at' => now(),
                'created_at' => now(),
            ]);

            Log::info('Decision discovery completed successfully', [
                'duration' => $duration,
                'found' => $result['total_found'] ?? 0,
                'ingested' => $result['ingested'] ?? 0,
            ]);

        } catch (\Exception $e) {
            Log::error('Decision discovery failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Store failed run
            DB::table('decision_discovery_runs')->insert([
                'status' => 'failed',
                'error' => $e->getMessage(),
                'topic_filter' => $this->topic,
                'duration_seconds' => round(microtime(true) - $startTime, 2),
                'completed_at' => now(),
                'created_at' => now(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     *
     * @param \Throwable $exception
     * @return void
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ExecuteDecisionDiscoveryJob failed permanently', [
            'error' => $exception->getMessage(),
            'max_decisions' => $this->maxDecisions,
            'topic' => $this->topic,
        ]);
    }

    /**
     * Get tags for job monitoring.
     *
     * @return array
     */
    public function tags(): array
    {
        return [
            'agent:decision-discovery',
            $this->topic ? "topic:{$this->topic}" : 'topic:all',
        ];
    }

    /**
     * Dispatch job with environment detection.
     *
     * @param int|null $maxDecisions
     * @param string|null $topic
     * @return mixed
     */
    public static function dispatchWithEnvDetection(?int $maxDecisions = null, ?string $topic = null): mixed
    {
        $job = new self($maxDecisions, $topic);

        $isProduction = app()->environment('production');
        $isLocalhost = in_array(request()->ip(), ['127.0.0.1', '::1', 'localhost']);

        if ($isProduction && !$isLocalhost) {
            // Production: Dispatch to queue
            Log::info('Dispatching ExecuteDecisionDiscoveryJob to queue (production)', [
                'max_decisions' => $maxDecisions,
                'topic' => $topic,
            ]);
            return self::dispatch($maxDecisions, $topic);
        } else {
            // Localhost/dev: Run synchronously
            Log::info('Running ExecuteDecisionDiscoveryJob synchronously (dev/localhost)', [
                'max_decisions' => $maxDecisions,
                'topic' => $topic,
            ]);
            $agent = app(DecisionDiscoveryAgent::class);
            $job->handle($agent);
            return ['mode' => 'sync', 'completed' => true];
        }
    }
}
```

**Database Migration**: `database/migrations/YYYY_MM_DD_create_decision_discovery_runs_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('decision_discovery_runs', function (Blueprint $table) {
            $table->id();
            $table->enum('status', ['running', 'completed', 'failed'])->default('running');
            $table->integer('decisions_found')->nullable();
            $table->integer('decisions_ingested')->nullable();
            $table->integer('topics_generated')->nullable();
            $table->string('topic_filter')->nullable();
            $table->decimal('duration_seconds', 8, 2)->nullable();
            $table->json('statistics')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('decision_discovery_runs');
    }
};
```

**Files to Create**:
- `app/Jobs/ExecuteDecisionDiscoveryJob.php` (120 lines)
- `database/migrations/YYYY_MM_DD_create_decision_discovery_runs_table.php` (35 lines)

**Dependencies**:
- Uses existing `DetectsEnvironment` trait from Neo4j tasks
- Uses existing `DecisionDiscoveryAgent` class

---

### Task 1.2: Add Environment Detection to DecisionDiscoveryAgent (0.5 hours)

**Objective**: Add method to run discovery with environment-aware dispatching

**File to Modify**: `app/Agents/DecisionDiscoveryAgent.php`

**Add Method** (at end of class):

```php
/**
 * Discover court decisions with environment-aware execution.
 *
 * @param int|null $maxDecisions
 * @param string|null $topic
 * @return mixed
 */
public static function discoverWithEnvDetection(?int $maxDecisions = null, ?string $topic = null): mixed
{
    return \App\Jobs\ExecuteDecisionDiscoveryJob::dispatchWithEnvDetection($maxDecisions, $topic);
}

/**
 * Check if discovery job is currently running.
 *
 * @return bool
 */
public static function isRunning(): bool
{
    $recentRun = DB::table('decision_discovery_runs')
        ->where('status', 'running')
        ->where('created_at', '>', now()->subHours(2))
        ->exists();

    return $recentRun;
}

/**
 * Get latest discovery run statistics.
 *
 * @return array|null
 */
public static function getLatestRunStats(): ?array
{
    $run = DB::table('decision_discovery_runs')
        ->where('status', 'completed')
        ->orderBy('created_at', 'desc')
        ->first();

    return $run ? [
        'completed_at' => $run->completed_at,
        'decisions_found' => $run->decisions_found,
        'decisions_ingested' => $run->decisions_ingested,
        'topics_generated' => $run->topics_generated,
        'duration_seconds' => $run->duration_seconds,
    ] : null;
}
```

**Files to Modify**:
- `app/Agents/DecisionDiscoveryAgent.php` (add ~40 lines)

---

### Task 1.3: Update Scheduled Task to Use Queue Job (1 hour)

**Objective**: Replace blocking command with queue job in scheduler

**File to Modify**: `app/Console/Kernel.php`

**Current Code** (find and replace):

```php
// BEFORE (blocking command):
$schedule->command('decisions:discover')->dailyAt('02:00');

// AFTER (queue job - non-blocking):
$schedule->call(function () {
    // Only run if not already running
    if (!\App\Agents\DecisionDiscoveryAgent::isRunning()) {
        \App\Jobs\ExecuteDecisionDiscoveryJob::dispatch();
    } else {
        \Log::info('Skipping decision discovery - job already running');
    }
})->dailyAt('02:00')->name('decision-discovery-daily');
```

**Alternative Approach** (create new Artisan command):

**File to Create**: `app/Console/Commands/DiscoverDecisionsAsyncCommand.php`

```php
<?php

namespace App\Console\Commands;

use App\Agents\DecisionDiscoveryAgent;
use App\Jobs\ExecuteDecisionDiscoveryJob;
use Illuminate\Console\Command;

class DiscoverDecisionsAsyncCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'decisions:discover-async
                            {--max-decisions= : Maximum decisions to discover}
                            {--topic= : Specific topic to search}
                            {--force : Force run even if already running}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Discover court decisions asynchronously using queue job';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Check if already running
        if (!$this->option('force') && DecisionDiscoveryAgent::isRunning()) {
            $this->warn('Decision discovery job is already running. Use --force to override.');
            return self::FAILURE;
        }

        $maxDecisions = $this->option('max-decisions') ? (int) $this->option('max-decisions') : null;
        $topic = $this->option('topic');

        $this->info('Dispatching decision discovery job...');

        $result = ExecuteDecisionDiscoveryJob::dispatchWithEnvDetection($maxDecisions, $topic);

        if (isset($result['mode']) && $result['mode'] === 'sync') {
            $this->info('✓ Discovery completed synchronously (dev environment)');
        } else {
            $this->info('✓ Discovery job dispatched to queue (production environment)');
            $this->info('Monitor progress: php artisan queue:listen');
        }

        return self::SUCCESS;
    }
}
```

**Update Scheduler** in `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule): void
{
    // ... existing schedules ...

    // Decision discovery - runs daily at 2 AM
    // Uses queue job to avoid blocking cron
    $schedule->command('decisions:discover-async')
        ->dailyAt('02:00')
        ->name('decision-discovery-daily')
        ->onOneServer() // Only run on one server in multi-server setup
        ->withoutOverlapping(60); // Skip if previous run still active
}
```

**Files to Create**:
- `app/Console/Commands/DiscoverDecisionsAsyncCommand.php` (60 lines)

**Files to Modify**:
- `app/Console/Kernel.php` (update schedule method, ~5 lines)

---

## 📋 MEDIUM PRIORITY TASKS

### Task 2.1: Create ExecuteOdlukeAgentJob (1 hour)

**Objective**: Create queue job for OdlukeAgent to handle long MCP tool chains

**File to Create**: `app/Jobs/ExecuteOdlukeAgentJob.php`

**Implementation**:

```php
<?php

namespace App\Jobs;

use App\Agents\OdlukeAgent;
use App\Traits\DetectsEnvironment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ExecuteOdlukeAgentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, DetectsEnvironment;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public int $tries = 1;

    /**
     * The maximum number of seconds the job can run.
     *
     * @var int
     */
    public int $timeout = 300; // 5 minutes

    /**
     * Create a new job instance.
     *
     * @param string $query User query
     * @param array $context Additional context
     * @param string|null $cacheKey Optional cache key for storing result
     */
    public function __construct(
        protected string $query,
        protected array $context = [],
        protected ?string $cacheKey = null
    ) {}

    /**
     * Execute the job.
     *
     * @param OdlukeAgent $agent
     * @return void
     */
    public function handle(OdlukeAgent $agent): void
    {
        $this->logEnvironment('ExecuteOdlukeAgentJob');

        $startTime = microtime(true);
        Log::info('Starting OdlukeAgent job', [
            'query' => substr($this->query, 0, 100),
            'environment' => $this->isProduction() ? 'production' : 'dev',
        ]);

        try {
            // Execute MCP tool chain
            $result = $agent->execute($this->query, $this->context);

            $duration = round(microtime(true) - $startTime, 2);

            // Cache result if cache key provided
            if ($this->cacheKey) {
                Cache::put($this->cacheKey, [
                    'status' => 'completed',
                    'result' => $result,
                    'duration' => $duration,
                    'completed_at' => now()->toIso8601String(),
                ], 3600); // 1 hour TTL
            }

            Log::info('OdlukeAgent completed successfully', [
                'duration' => $duration,
                'cache_key' => $this->cacheKey,
            ]);

        } catch (\Exception $e) {
            Log::error('OdlukeAgent execution failed', [
                'query' => $this->query,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Cache error if cache key provided
            if ($this->cacheKey) {
                Cache::put($this->cacheKey, [
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                    'completed_at' => now()->toIso8601String(),
                ], 3600);
            }

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     *
     * @param \Throwable $exception
     * @return void
     */
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

    /**
     * Get tags for job monitoring.
     *
     * @return array
     */
    public function tags(): array
    {
        return [
            'agent:odluke',
            'query:' . substr(md5($this->query), 0, 8),
        ];
    }

    /**
     * Dispatch job with environment detection.
     *
     * @param string $query
     * @param array $context
     * @param string|null $cacheKey
     * @return mixed
     */
    public static function dispatchWithEnvDetection(string $query, array $context = [], ?string $cacheKey = null): mixed
    {
        $job = new self($query, $context, $cacheKey);

        $isProduction = app()->environment('production');
        $isLocalhost = in_array(request()->ip(), ['127.0.0.1', '::1', 'localhost']);

        if ($isProduction && !$isLocalhost) {
            // Production: Dispatch to queue
            Log::info('Dispatching ExecuteOdlukeAgentJob to queue (production)');

            // Set cache to "running" state
            if ($cacheKey) {
                Cache::put($cacheKey, [
                    'status' => 'running',
                    'started_at' => now()->toIso8601String(),
                ], 3600);
            }

            return self::dispatch($query, $context, $cacheKey);
        } else {
            // Localhost/dev: Run synchronously
            Log::info('Running ExecuteOdlukeAgentJob synchronously (dev/localhost)');
            $agent = app(OdlukeAgent::class);
            $job->handle($agent);

            // Return result from cache if available
            return $cacheKey ? Cache::get($cacheKey) : ['mode' => 'sync', 'completed' => true];
        }
    }
}
```

**Files to Create**:
- `app/Jobs/ExecuteOdlukeAgentJob.php` (135 lines)

---

### Task 2.2: Add Async Execution Option to OdlukeAgent (1 hour)

**Objective**: Add methods to OdlukeAgent for async execution via queue job

**File to Modify**: `app/Agents/OdlukeAgent.php`

**Add Methods** (at end of class):

```php
/**
 * Execute query asynchronously with environment detection.
 *
 * @param string $query
 * @param array $context
 * @param bool $async Force async execution (default: auto-detect)
 * @return mixed
 */
public static function executeAsync(string $query, array $context = [], bool $async = true): mixed
{
    // Generate unique cache key for this query
    $cacheKey = 'odluke_agent:' . md5($query . json_encode($context));

    if (!$async) {
        // Synchronous execution
        $agent = new self();
        $result = $agent->execute($query, $context);

        Cache::put($cacheKey, [
            'status' => 'completed',
            'result' => $result,
            'completed_at' => now()->toIso8601String(),
        ], 3600);

        return [
            'mode' => 'sync',
            'cache_key' => $cacheKey,
            'result' => $result,
        ];
    }

    // Async execution with environment detection
    $job = \App\Jobs\ExecuteOdlukeAgentJob::dispatchWithEnvDetection($query, $context, $cacheKey);

    return [
        'mode' => 'async',
        'cache_key' => $cacheKey,
        'message' => 'Query processing started. Check status using the cache_key.',
    ];
}

/**
 * Get status of async execution.
 *
 * @param string $cacheKey
 * @return array|null
 */
public static function getAsyncStatus(string $cacheKey): ?array
{
    return Cache::get($cacheKey);
}

/**
 * Wait for async execution to complete (polling).
 *
 * @param string $cacheKey
 * @param int $maxWaitSeconds
 * @param int $pollIntervalMs
 * @return array|null
 */
public static function waitForAsync(string $cacheKey, int $maxWaitSeconds = 60, int $pollIntervalMs = 500): ?array
{
    $startTime = time();

    while ((time() - $startTime) < $maxWaitSeconds) {
        $status = self::getAsyncStatus($cacheKey);

        if ($status && $status['status'] !== 'running') {
            return $status;
        }

        usleep($pollIntervalMs * 1000);
    }

    return [
        'status' => 'timeout',
        'message' => 'Query execution exceeded maximum wait time',
    ];
}
```

**Files to Modify**:
- `app/Agents/OdlukeAgent.php` (add ~70 lines)

**Example API Controller Usage** (optional enhancement):

Create `app/Http/Controllers/OdlukeController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Agents\OdlukeAgent;
use Illuminate\Http\Request;

class OdlukeController extends Controller
{
    /**
     * Execute OdlukeAgent query (async supported).
     */
    public function execute(Request $request)
    {
        $request->validate([
            'query' => 'required|string|max:2000',
            'context' => 'sometimes|array',
            'async' => 'sometimes|boolean',
        ]);

        $result = OdlukeAgent::executeAsync(
            query: $request->input('query'),
            context: $request->input('context', []),
            async: $request->boolean('async', true)
        );

        return response()->json($result);
    }

    /**
     * Get status of async query.
     */
    public function status(Request $request)
    {
        $request->validate([
            'cache_key' => 'required|string',
        ]);

        $status = OdlukeAgent::getAsyncStatus($request->input('cache_key'));

        if (!$status) {
            return response()->json([
                'error' => 'Query not found or expired',
            ], 404);
        }

        return response()->json($status);
    }
}
```

**Add Routes** to `routes/api.php`:

```php
// OdlukeAgent API
Route::prefix('odluke-agent')->middleware(['api.token', 'throttle:30,1'])->group(function () {
    Route::post('/execute', [OdlukeController::class, 'execute']);
    Route::post('/status', [OdlukeController::class, 'status']);
});
```

**Files to Create (optional)**:
- `app/Http/Controllers/OdlukeController.php` (50 lines)

**Files to Modify (optional)**:
- `routes/api.php` (add 5 lines)

---

## 📌 LOW PRIORITY TASKS

### Task 3.1: Create DecisionDiscoveryRun Model (1 hour)

**Objective**: Create Eloquent model for better query builder and relationships

**File to Create**: `app/Models/DecisionDiscoveryRun.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DecisionDiscoveryRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'status',
        'decisions_found',
        'decisions_ingested',
        'topics_generated',
        'topic_filter',
        'duration_seconds',
        'statistics',
        'error',
        'completed_at',
    ];

    protected $casts = [
        'statistics' => 'array',
        'completed_at' => 'datetime',
    ];

    /**
     * Scope: Only completed runs.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope: Only failed runs.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope: Recent runs (last 30 days).
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>', now()->subDays($days));
    }

    /**
     * Get success rate percentage.
     */
    public static function getSuccessRate(int $days = 30): float
    {
        $total = self::recent($days)->count();

        if ($total === 0) {
            return 0;
        }

        $completed = self::recent($days)->completed()->count();

        return round(($completed / $total) * 100, 2);
    }

    /**
     * Get average duration for successful runs.
     */
    public static function getAverageDuration(int $days = 30): float
    {
        return self::recent($days)
            ->completed()
            ->avg('duration_seconds') ?? 0;
    }

    /**
     * Get total decisions discovered in period.
     */
    public static function getTotalDiscovered(int $days = 30): int
    {
        return self::recent($days)
            ->completed()
            ->sum('decisions_found') ?? 0;
    }

    /**
     * Get total decisions ingested in period.
     */
    public static function getTotalIngested(int $days = 30): int
    {
        return self::recent($days)
            ->completed()
            ->sum('decisions_ingested') ?? 0;
    }
}
```

**Files to Create**:
- `app/Models/DecisionDiscoveryRun.php` (95 lines)

**Update Usage** in `ExecuteDecisionDiscoveryJob.php`:

```php
// BEFORE (raw DB):
DB::table('decision_discovery_runs')->insert([...]);

// AFTER (Eloquent model):
use App\Models\DecisionDiscoveryRun;

DecisionDiscoveryRun::create([
    'status' => 'completed',
    'decisions_found' => $result['total_found'] ?? 0,
    'decisions_ingested' => $result['ingested'] ?? 0,
    'topics_generated' => $result['topics_count'] ?? 0,
    'topic_filter' => $this->topic,
    'duration_seconds' => $duration,
    'statistics' => $result,
    'completed_at' => now(),
]);
```

---

### Task 3.2: Add Monitoring Dashboard for Agent Jobs (2 hours)

**Objective**: Create API endpoints to monitor agent job health and statistics

**File to Create**: `app/Http/Controllers/AgentMonitoringController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Models\AgentRun;
use App\Models\DecisionDiscoveryRun;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

class AgentMonitoringController extends Controller
{
    /**
     * Get overall agent system health.
     */
    public function health(Request $request)
    {
        $days = $request->integer('days', 7);

        $health = [
            'status' => 'healthy',
            'timestamp' => now()->toIso8601String(),
            'period_days' => $days,
            'agents' => [
                'research' => $this->getResearchAgentHealth($days),
                'decision_discovery' => $this->getDecisionDiscoveryHealth($days),
            ],
            'queue' => $this->getQueueHealth(),
        ];

        // Determine overall status
        $failureRates = [
            $health['agents']['research']['failure_rate'],
            $health['agents']['decision_discovery']['failure_rate'],
        ];

        if (max($failureRates) > 50) {
            $health['status'] = 'critical';
        } elseif (max($failureRates) > 25) {
            $health['status'] = 'degraded';
        }

        return response()->json($health);
    }

    /**
     * Get statistics for all agents.
     */
    public function statistics(Request $request)
    {
        $days = $request->integer('days', 30);

        return response()->json([
            'period_days' => $days,
            'research_agent' => [
                'total_runs' => AgentRun::recent($days)->count(),
                'completed' => AgentRun::recent($days)->where('status', 'completed')->count(),
                'failed' => AgentRun::recent($days)->where('status', 'failed')->count(),
                'avg_duration_seconds' => AgentRun::recent($days)
                    ->where('status', 'completed')
                    ->avg(DB::raw('TIMESTAMPDIFF(SECOND, created_at, completed_at)')) ?? 0,
            ],
            'decision_discovery' => [
                'total_runs' => DecisionDiscoveryRun::recent($days)->count(),
                'completed' => DecisionDiscoveryRun::recent($days)->completed()->count(),
                'failed' => DecisionDiscoveryRun::recent($days)->failed()->count(),
                'avg_duration_seconds' => DecisionDiscoveryRun::getAverageDuration($days),
                'total_decisions_found' => DecisionDiscoveryRun::getTotalDiscovered($days),
                'total_decisions_ingested' => DecisionDiscoveryRun::getTotalIngested($days),
            ],
        ]);
    }

    /**
     * Get recent agent runs with status.
     */
    public function recentRuns(Request $request)
    {
        $limit = $request->integer('limit', 20);

        return response()->json([
            'research_agent' => AgentRun::orderBy('created_at', 'desc')
                ->limit($limit)
                ->get(['id', 'status', 'objective', 'created_at', 'completed_at']),
            'decision_discovery' => DecisionDiscoveryRun::orderBy('created_at', 'desc')
                ->limit($limit)
                ->get(['id', 'status', 'decisions_found', 'decisions_ingested', 'created_at', 'completed_at']),
        ]);
    }

    /**
     * Get failed jobs for debugging.
     */
    public function failedJobs(Request $request)
    {
        $limit = $request->integer('limit', 50);

        $failed = DB::table('failed_jobs')
            ->where('queue', 'default')
            ->orderBy('failed_at', 'desc')
            ->limit($limit)
            ->get(['id', 'connection', 'queue', 'payload', 'exception', 'failed_at']);

        return response()->json([
            'total' => DB::table('failed_jobs')->count(),
            'recent' => $failed,
        ]);
    }

    /**
     * Get research agent health metrics.
     */
    protected function getResearchAgentHealth(int $days): array
    {
        $total = AgentRun::recent($days)->count();
        $failed = AgentRun::recent($days)->where('status', 'failed')->count();

        return [
            'total_runs' => $total,
            'failed_runs' => $failed,
            'failure_rate' => $total > 0 ? round(($failed / $total) * 100, 2) : 0,
            'status' => $total > 0 && ($failed / $total) < 0.1 ? 'healthy' : 'degraded',
        ];
    }

    /**
     * Get decision discovery agent health metrics.
     */
    protected function getDecisionDiscoveryHealth(int $days): array
    {
        $total = DecisionDiscoveryRun::recent($days)->count();
        $failed = DecisionDiscoveryRun::recent($days)->failed()->count();

        return [
            'total_runs' => $total,
            'failed_runs' => $failed,
            'failure_rate' => $total > 0 ? round(($failed / $total) * 100, 2) : 0,
            'avg_decisions_per_run' => $total > 0
                ? round(DecisionDiscoveryRun::getTotalIngested($days) / $total, 2)
                : 0,
            'status' => $total > 0 && ($failed / $total) < 0.1 ? 'healthy' : 'degraded',
        ];
    }

    /**
     * Get queue health metrics.
     */
    protected function getQueueHealth(): array
    {
        $size = Queue::size('default');
        $failedCount = DB::table('failed_jobs')->count();

        return [
            'queue_size' => $size,
            'failed_jobs_total' => $failedCount,
            'status' => $size < 100 && $failedCount < 10 ? 'healthy' : 'degraded',
        ];
    }
}
```

**Add Routes** to `routes/api.php`:

```php
/*
|--------------------------------------------------------------------------
| Agent Monitoring API Routes
|--------------------------------------------------------------------------
|
| Monitor health and performance of agent jobs.
|
*/

Route::prefix('monitoring')->middleware(['api.token', 'throttle:60,1'])->group(function () {
    // System health
    Route::get('/health', [AgentMonitoringController::class, 'health']);

    // Statistics
    Route::get('/statistics', [AgentMonitoringController::class, 'statistics']);

    // Recent runs
    Route::get('/recent-runs', [AgentMonitoringController::class, 'recentRuns']);

    // Failed jobs
    Route::get('/failed-jobs', [AgentMonitoringController::class, 'failedJobs']);
});
```

**Files to Create**:
- `app/Http/Controllers/AgentMonitoringController.php` (160 lines)

**Files to Modify**:
- `routes/api.php` (add 15 lines)

**Example API Usage**:

```bash
# Get system health
curl http://localhost/api/monitoring/health?days=7 \
  -H "X-API-Token: $TOKEN"

# Response:
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

### Task 3.3: Migrate DecisionDiscoveryAgent to Vizra Framework (1 hour)

**Objective**: Refactor DecisionDiscoveryAgent to extend BaseLlmAgent for consistency

**File to Modify**: `app/Agents/DecisionDiscoveryAgent.php`

**Changes Required**:

1. **Change class declaration**:
```php
// BEFORE:
class DecisionDiscoveryAgent
{
    // ...
}

// AFTER:
use Vizra\VizraADK\Agents\BaseLlmAgent;

class DecisionDiscoveryAgent extends BaseLlmAgent
{
    // ...
}
```

2. **Add agent configuration**:
```php
/**
 * Get agent configuration.
 */
protected function getAgentConfig(): array
{
    return [
        'name' => 'DecisionDiscoveryAgent',
        'description' => 'Discovers and ingests court decisions from Croatian judicial portal',
        'model' => config('vizra.model', 'gpt-4o-mini'),
        'temperature' => 0.7,
        'max_iterations' => 3,
    ];
}

/**
 * Get available tools for agent.
 */
protected function getTools(): array
{
    return [
        new \App\Tools\SearchOdlukeTool(),
        new \App\Tools\IngestDecisionTool(),
        new \App\Tools\GenerateTopicsTool(),
    ];
}
```

3. **Refactor discover() method**:
```php
/**
 * Discover court decisions using agent framework.
 */
public function discover(?int $maxDecisions = null, ?string $topic = null): array
{
    $objective = $topic
        ? "Discover court decisions related to: {$topic}"
        : "Discover important court decisions from Croatian judicial portal";

    $context = [
        'max_decisions' => $maxDecisions ?? 50,
        'topic_filter' => $topic,
    ];

    // Execute using agent framework
    $result = $this->execute($objective, $context);

    return $result;
}
```

**Benefits of Migration**:
- ✅ Automatic checkpoint/resume capability
- ✅ Built-in evaluation and scoring
- ✅ Standardized tool interface
- ✅ Event broadcasting (ResearchCompleted, ResearchFailed)
- ✅ Consistent logging and error handling
- ✅ Code reuse across agents

**Effort**: ~1 hour to refactor
**Risk**: Low (existing discover() method signature unchanged)

---

## Summary: Task Checklist

### ⚡ HIGH PRIORITY (3 hours → 90%)

- [ ] Task 1.1: Create `ExecuteDecisionDiscoveryJob.php` and migration
- [ ] Task 1.2: Add environment detection methods to `DecisionDiscoveryAgent.php`
- [ ] Task 1.3: Create `DiscoverDecisionsAsyncCommand.php` and update scheduler

**Files to Create**: 3
**Files to Modify**: 2
**Lines Added**: ~255
**Impact**: Unblocks daily cron, enables parallel discovery

---

### 📋 MEDIUM PRIORITY (2 hours → 95%)

- [ ] Task 2.1: Create `ExecuteOdlukeAgentJob.php`
- [ ] Task 2.2: Add async methods to `OdlukeAgent.php` + optional controller

**Files to Create**: 2
**Files to Modify**: 2
**Lines Added**: ~260
**Impact**: Prevents HTTP timeouts on long MCP chains

---

### 📌 LOW PRIORITY (4 hours → 100%)

- [ ] Task 3.1: Create `DecisionDiscoveryRun` Eloquent model
- [ ] Task 3.2: Create `AgentMonitoringController.php` with routes
- [ ] Task 3.3: Migrate DecisionDiscoveryAgent to Vizra framework

**Files to Create**: 2
**Files to Modify**: 3
**Lines Added**: ~350
**Impact**: Better monitoring, code consistency, maintainability

---

## Testing Instructions

### Test DecisionDiscoveryJob

```bash
# Start queue worker
php artisan queue:work --queue=default --tries=1 &

# Test synchronous (dev)
APP_ENV=local php artisan decisions:discover-async

# Test asynchronous (production simulation)
APP_ENV=production php artisan decisions:discover-async

# Check job status
php artisan queue:listen

# Check run history
php artisan tinker
>>> App\Models\DecisionDiscoveryRun::recent()->get()
```

### Test OdlukeAgentJob

```bash
# Start queue worker
php artisan queue:work &

# Test async execution
curl -X POST http://localhost/api/odluke-agent/execute \
  -H "X-API-Token: $TOKEN" \
  -d '{
    "query": "Nađi sve odluke o radnom pravu iz 2024",
    "async": true
  }'

# Response:
{
  "mode": "async",
  "cache_key": "odluke_agent:abc123...",
  "message": "Query processing started. Check status using the cache_key."
}

# Check status
curl -X POST http://localhost/api/odluke-agent/status \
  -H "X-API-Token: $TOKEN" \
  -d '{"cache_key": "odluke_agent:abc123..."}'
```

### Test Monitoring Endpoints

```bash
# Check system health
curl http://localhost/api/monitoring/health?days=7 \
  -H "X-API-Token: $TOKEN"

# Get statistics
curl http://localhost/api/monitoring/statistics?days=30 \
  -H "X-API-Token: $TOKEN"

# Get recent runs
curl http://localhost/api/monitoring/recent-runs?limit=10 \
  -H "X-API-Token: $TOKEN"
```

---

## Completion Metrics

### Current: 85% (1/7 agents with queue support)
- ✅ AutonomousResearchAgent: Queue job + Vizra framework
- ⚠️ OdlukeAgent: Vizra framework, NO queue job
- ❌ DecisionDiscoveryAgent: NO framework, NO queue job (blocks cron)
- ℹ️ Specialist Agents (4): Lightweight, queue not needed

### After HIGH Tasks: 90% (2/7 agents with queue support)
- ✅ AutonomousResearchAgent: Queue job + Vizra framework
- ⚠️ OdlukeAgent: Vizra framework, NO queue job
- ✅ DecisionDiscoveryAgent: Queue job added (non-blocking)
- ℹ️ Specialist Agents (4): Lightweight, queue not needed

### After MEDIUM Tasks: 95% (3/7 agents with queue support)
- ✅ AutonomousResearchAgent: Queue job + Vizra framework
- ✅ OdlukeAgent: Queue job + Vizra framework
- ✅ DecisionDiscoveryAgent: Queue job added
- ℹ️ Specialist Agents (4): Lightweight, queue not needed

### After LOW Tasks: 100% (All agents optimized)
- ✅ AutonomousResearchAgent: Queue job + Vizra framework
- ✅ OdlukeAgent: Queue job + Vizra framework
- ✅ DecisionDiscoveryAgent: Queue job + Vizra framework
- ✅ Specialist Agents (4): Lightweight (intentionally simple)
- ✅ Monitoring dashboard for all agent health

---

## Estimated Timeline

| Priority | Tasks | Effort | Completion |
|----------|-------|--------|------------|
| HIGH | 3 tasks | 3 hours | 85% → 90% |
| MEDIUM | 2 tasks | 2 hours | 90% → 95% |
| LOW | 3 tasks | 4 hours | 95% → 100% |
| **TOTAL** | **8 tasks** | **9 hours** | **85% → 100%** |

---

## Dependencies

All tasks use:
- ✅ Existing `DetectsEnvironment` trait (from Neo4j tasks)
- ✅ Laravel Queue system (already configured)
- ✅ Existing agent classes
- ✅ Vizra ADK framework (already installed)

No new dependencies required.

---

**Document End** | Agent Framework Action Plan v1.0

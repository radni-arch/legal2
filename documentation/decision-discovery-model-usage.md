# DecisionDiscoveryRun Model - Usage Guide

## Overview

The `DecisionDiscoveryRun` model provides Eloquent ORM access to decision discovery run records with convenient scopes, aggregation methods, and statistics tracking.

## Model Location

`app/Models/DecisionDiscoveryRun.php`

---

## Database Schema

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `started_at` | timestamp | When the run started |
| `completed_at` | timestamp (nullable) | When the run completed |
| `topics_generated` | integer | Number of topics generated |
| `decisions_evaluated` | integer | Total decisions evaluated |
| `decisions_ingested` | integer | Total decisions ingested |
| `topics` | json (nullable) | Array of topic strings |
| `errors` | json (nullable) | Array of error objects |
| `status` | string | Status: 'running', 'completed', 'failed' |
| `error_message` | text (nullable) | Error message if failed |
| `created_at` | timestamp | Record creation time |
| `updated_at` | timestamp | Record update time |

---

## Instance Methods

### Status Checks

```php
use App\Models\DecisionDiscoveryRun;

$run = DecisionDiscoveryRun::find(1);

// Check status
$run->isRunning();    // true if status = 'running'
$run->isCompleted();  // true if status = 'completed'
$run->isFailed();     // true if status = 'failed'
```

### Duration Calculation

```php
// Get duration in seconds (null if not completed)
$durationSeconds = $run->duration();

// Example: 1250 seconds = ~20 minutes
echo "Run took: " . $durationSeconds . " seconds";
```

---

## Query Scopes

### Filter by Status

```php
use App\Models\DecisionDiscoveryRun;

// Get only completed runs
$completed = DecisionDiscoveryRun::completed()->get();

// Get only failed runs
$failed = DecisionDiscoveryRun::failed()->get();

// Get currently running runs
$running = DecisionDiscoveryRun::running()->get();
```

### Filter by Time Period

```php
// Get runs from last 30 days (default)
$recent = DecisionDiscoveryRun::recent()->get();

// Get runs from last 7 days
$lastWeek = DecisionDiscoveryRun::recent(7)->get();

// Get runs from last 90 days
$lastQuarter = DecisionDiscoveryRun::recent(90)->get();
```

### Combining Scopes

```php
// Get completed runs from last 30 days
$recentSuccess = DecisionDiscoveryRun::recent(30)
    ->completed()
    ->get();

// Get failed runs from last 7 days
$recentFailures = DecisionDiscoveryRun::recent(7)
    ->failed()
    ->orderBy('created_at', 'desc')
    ->get();

// Count running jobs in last hour
$activeCount = DecisionDiscoveryRun::where('created_at', '>', now()->subHour())
    ->running()
    ->count();
```

---

## Static Aggregation Methods

### Success Rate

```php
use App\Models\DecisionDiscoveryRun;

// Get success rate for last 30 days (default)
$successRate = DecisionDiscoveryRun::getSuccessRate();
// Returns: 85.50 (float percentage)

// Get success rate for last 7 days
$weeklySuccessRate = DecisionDiscoveryRun::getSuccessRate(7);

// Get success rate for last 90 days
$quarterlySuccessRate = DecisionDiscoveryRun::getSuccessRate(90);
```

### Average Duration

```php
// Get average duration in seconds for last 30 days
$avgDuration = DecisionDiscoveryRun::getAverageDuration();
// Returns: 1250.50 (float seconds)

// Get average duration for last 7 days
$weeklyAvgDuration = DecisionDiscoveryRun::getAverageDuration(7);

// Convert to minutes
$avgMinutes = round($avgDuration / 60, 2);
echo "Average duration: {$avgMinutes} minutes";
```

### Total Discovered

```php
// Get total decisions discovered (evaluated) in last 30 days
$totalDiscovered = DecisionDiscoveryRun::getTotalDiscovered();
// Returns: 2500 (integer)

// Get total discovered in last 7 days
$weeklyDiscovered = DecisionDiscoveryRun::getTotalDiscovered(7);
```

### Total Ingested

```php
// Get total decisions ingested in last 30 days
$totalIngested = DecisionDiscoveryRun::getTotalIngested();
// Returns: 250 (integer)

// Get total ingested in last 7 days
$weeklyIngested = DecisionDiscoveryRun::getTotalIngested(7);
```

### Comprehensive Statistics

```php
// Get all statistics in one call
$stats = DecisionDiscoveryRun::getStatistics(30);

/* Returns:
[
    'total_runs' => 42,
    'completed_runs' => 38,
    'failed_runs' => 4,
    'running_runs' => 0,
    'success_rate' => 90.48,
    'average_duration' => 1250.50,
    'total_discovered' => 2500,
    'total_ingested' => 250,
]
*/

// Use in API response
return response()->json([
    'period_days' => 30,
    'statistics' => $stats,
]);
```

---

## Common Usage Patterns

### Dashboard Display

```php
use App\Models\DecisionDiscoveryRun;

// Get data for admin dashboard
$dashboardData = [
    'last_run' => DecisionDiscoveryRun::latest()->first(),
    'running_now' => DecisionDiscoveryRun::running()->exists(),
    'stats_30d' => DecisionDiscoveryRun::getStatistics(30),
    'recent_failures' => DecisionDiscoveryRun::recent(7)->failed()->get(),
];

// Check if system is healthy
$successRate = DecisionDiscoveryRun::getSuccessRate(7);
$isHealthy = $successRate >= 80.0;

if (!$isHealthy) {
    // Alert: Low success rate
    \Log::warning('Discovery success rate below 80%', [
        'success_rate' => $successRate,
        'period' => 7,
    ]);
}
```

### Monitoring Alerts

```php
use App\Models\DecisionDiscoveryRun;

// Check for stuck runs (running > 1 hour)
$stuckRuns = DecisionDiscoveryRun::running()
    ->where('started_at', '<', now()->subHour())
    ->get();

if ($stuckRuns->isNotEmpty()) {
    foreach ($stuckRuns as $run) {
        \Log::error('Discovery run stuck', [
            'run_id' => $run->id,
            'started_at' => $run->started_at,
            'duration' => now()->diffInMinutes($run->started_at) . ' minutes',
        ]);

        // Optionally mark as failed
        $run->update([
            'status' => 'failed',
            'error_message' => 'Timeout: Run exceeded 1 hour',
            'completed_at' => now(),
        ]);
    }
}
```

### Performance Analysis

```php
use App\Models\DecisionDiscoveryRun;

// Compare performance across time periods
$performance = [
    'last_7_days' => [
        'success_rate' => DecisionDiscoveryRun::getSuccessRate(7),
        'avg_duration' => DecisionDiscoveryRun::getAverageDuration(7),
        'total_ingested' => DecisionDiscoveryRun::getTotalIngested(7),
    ],
    'last_30_days' => [
        'success_rate' => DecisionDiscoveryRun::getSuccessRate(30),
        'avg_duration' => DecisionDiscoveryRun::getAverageDuration(30),
        'total_ingested' => DecisionDiscoveryRun::getTotalIngested(30),
    ],
    'last_90_days' => [
        'success_rate' => DecisionDiscoveryRun::getSuccessRate(90),
        'avg_duration' => DecisionDiscoveryRun::getAverageDuration(90),
        'total_ingested' => DecisionDiscoveryRun::getTotalIngested(90),
    ],
];

// Check for performance degradation
if ($performance['last_7_days']['avg_duration'] > $performance['last_30_days']['avg_duration'] * 1.5) {
    \Log::warning('Discovery performance degraded significantly');
}
```

### Creating New Runs

```php
use App\Models\DecisionDiscoveryRun;

// Create new run (typically done by DecisionDiscoveryAgent)
$run = DecisionDiscoveryRun::create([
    'started_at' => now(),
    'status' => 'running',
]);

// Update on completion
$run->update([
    'completed_at' => now(),
    'status' => 'completed',
    'topics_generated' => 5,
    'decisions_evaluated' => 250,
    'decisions_ingested' => 25,
    'topics' => ['Radno pravo', 'Ugovorno pravo', 'Potrošačka zaštita'],
    'errors' => [],
]);

// Update on failure
$run->update([
    'completed_at' => now(),
    'status' => 'failed',
    'error_message' => 'API connection timeout',
]);
```

---

## API Endpoint Examples

### GET /api/discovery/statistics

```php
use App\Models\DecisionDiscoveryRun;

Route::get('/api/discovery/statistics', function () {
    $days = request('days', 30);

    return response()->json([
        'period_days' => $days,
        'statistics' => DecisionDiscoveryRun::getStatistics($days),
        'generated_at' => now(),
    ]);
});

// Request: GET /api/discovery/statistics?days=7
// Response:
{
    "period_days": 7,
    "statistics": {
        "total_runs": 7,
        "completed_runs": 6,
        "failed_runs": 1,
        "running_runs": 0,
        "success_rate": 85.71,
        "average_duration": 1200.50,
        "total_discovered": 1750,
        "total_ingested": 175
    },
    "generated_at": "2025-10-31T14:30:00.000000Z"
}
```

### GET /api/discovery/runs

```php
use App\Models\DecisionDiscoveryRun;

Route::get('/api/discovery/runs', function () {
    $days = request('days', 30);
    $status = request('status'); // completed, failed, running

    $query = DecisionDiscoveryRun::recent($days);

    if ($status) {
        $query->where('status', $status);
    }

    $runs = $query->latest()
        ->paginate(20)
        ->through(function ($run) {
            return [
                'id' => $run->id,
                'started_at' => $run->started_at,
                'completed_at' => $run->completed_at,
                'status' => $run->status,
                'duration_seconds' => $run->duration(),
                'topics_generated' => $run->topics_generated,
                'decisions_evaluated' => $run->decisions_evaluated,
                'decisions_ingested' => $run->decisions_ingested,
                'topics' => $run->topics,
            ];
        });

    return response()->json($runs);
});

// Request: GET /api/discovery/runs?days=7&status=completed
```

### GET /api/discovery/health

```php
use App\Models\DecisionDiscoveryRun;

Route::get('/api/discovery/health', function () {
    $successRate = DecisionDiscoveryRun::getSuccessRate(7);
    $avgDuration = DecisionDiscoveryRun::getAverageDuration(7);
    $runningCount = DecisionDiscoveryRun::running()->count();
    $stuckCount = DecisionDiscoveryRun::running()
        ->where('started_at', '<', now()->subHour())
        ->count();

    $isHealthy = $successRate >= 80.0 && $stuckCount === 0;

    return response()->json([
        'status' => $isHealthy ? 'healthy' : 'degraded',
        'metrics' => [
            'success_rate_7d' => $successRate,
            'avg_duration_seconds_7d' => $avgDuration,
            'currently_running' => $runningCount,
            'stuck_runs' => $stuckCount,
        ],
        'thresholds' => [
            'min_success_rate' => 80.0,
            'max_stuck_runs' => 0,
        ],
    ]);
});
```

---

## Testing Examples

### Feature Test

```php
use App\Models\DecisionDiscoveryRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DecisionDiscoveryRunTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_scope_filters_correctly()
    {
        // Arrange
        DecisionDiscoveryRun::factory()->create(['status' => 'completed']);
        DecisionDiscoveryRun::factory()->create(['status' => 'completed']);
        DecisionDiscoveryRun::factory()->create(['status' => 'failed']);
        DecisionDiscoveryRun::factory()->create(['status' => 'running']);

        // Act
        $completed = DecisionDiscoveryRun::completed()->get();

        // Assert
        $this->assertCount(2, $completed);
        $this->assertTrue($completed->every(fn($run) => $run->status === 'completed'));
    }

    public function test_success_rate_calculation()
    {
        // Arrange
        DecisionDiscoveryRun::factory()->count(8)->create(['status' => 'completed']);
        DecisionDiscoveryRun::factory()->count(2)->create(['status' => 'failed']);

        // Act
        $successRate = DecisionDiscoveryRun::getSuccessRate(30);

        // Assert
        $this->assertEquals(80.0, $successRate);
    }

    public function test_duration_calculation()
    {
        // Arrange
        $run = DecisionDiscoveryRun::factory()->create([
            'started_at' => now()->subMinutes(20),
            'completed_at' => now(),
            'status' => 'completed',
        ]);

        // Act
        $duration = $run->duration();

        // Assert
        $this->assertEqualsWithDelta(1200, $duration, 5); // ~20 minutes ± 5 seconds
    }
}
```

---

## Eloquent Model Features

### HasFactory Trait

The model includes the `HasFactory` trait for easy test data generation:

```php
use App\Models\DecisionDiscoveryRun;

// Create factory (database/factories/DecisionDiscoveryRunFactory.php)
DecisionDiscoveryRun::factory()->create();

// Create multiple
DecisionDiscoveryRun::factory()->count(10)->create();

// Create with specific attributes
DecisionDiscoveryRun::factory()->create([
    'status' => 'completed',
    'decisions_ingested' => 100,
]);
```

### Fillable Attributes

All attributes are mass-assignable via `$fillable`:

```php
DecisionDiscoveryRun::create([
    'started_at' => now(),
    'status' => 'running',
    // ... other fields
]);

$run->update([
    'completed_at' => now(),
    'status' => 'completed',
]);
```

### Attribute Casting

Automatic type casting for attributes:

```php
$run = DecisionDiscoveryRun::find(1);

// Automatically cast to Carbon instances
$run->started_at->format('Y-m-d H:i:s');
$run->completed_at->diffForHumans(); // "2 hours ago"

// Automatically cast to arrays
foreach ($run->topics as $topic) {
    echo $topic;
}

foreach ($run->errors as $error) {
    echo $error['message'];
}
```

---

## Migration from Raw Queries

### Before (Raw DB Queries)

```php
use Illuminate\Support\Facades\DB;

// Get completed runs
$completed = DB::table('decision_discovery_runs')
    ->where('status', 'completed')
    ->get();

// Get success rate
$total = DB::table('decision_discovery_runs')->count();
$completed = DB::table('decision_discovery_runs')
    ->where('status', 'completed')
    ->count();
$successRate = ($completed / $total) * 100;
```

### After (Eloquent Model)

```php
use App\Models\DecisionDiscoveryRun;

// Get completed runs
$completed = DecisionDiscoveryRun::completed()->get();

// Get success rate
$successRate = DecisionDiscoveryRun::getSuccessRate();
```

**Benefits:**
- Cleaner, more readable code
- Type safety and IDE autocomplete
- Reusable scopes and methods
- Easier testing with factories
- Automatic attribute casting

---

## Performance Considerations

### Query Optimization

```php
// BAD: N+1 problem with eager loading
$runs = DecisionDiscoveryRun::recent(30)->get();
foreach ($runs as $run) {
    $duration = $run->duration(); // Recalculates each time
}

// GOOD: Calculate once, use many times
$runs = DecisionDiscoveryRun::recent(30)
    ->get()
    ->map(function ($run) {
        return [
            'id' => $run->id,
            'duration' => $run->duration(), // Calculated once
            'status' => $run->status,
        ];
    });
```

### Caching Statistics

```php
use Illuminate\Support\Facades\Cache;

// Cache expensive statistics
$stats = Cache::remember('discovery_stats_30d', now()->addHour(), function () {
    return DecisionDiscoveryRun::getStatistics(30);
});
```

---

## References

- Model: `app/Models/DecisionDiscoveryRun.php`
- Migration: `database/migrations/2025_10_27_100000_create_decision_discovery_runs_table.php`
- Agent: `app/Agents/DecisionDiscoveryAgent.php`
- Job: `app/Jobs/ExecuteDecisionDiscoveryJob.php`

---

**Document Version**: 1.0
**Last Updated**: 2025-10-31
**Task Reference**: Task 3.1 - Create DecisionDiscoveryRun Model

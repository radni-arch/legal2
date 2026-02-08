# Performance Optimizations

This document describes all performance optimizations implemented in the AI Legal War Machine application.

## Overview

Performance optimizations focus on three main areas:
1. **Database Query Optimization** - Reducing query count and complexity
2. **Eager Loading** - Preventing N+1 query problems
3. **Caching Strategy** - Reducing repeated database queries for frequently accessed data

---

## 1. Database Query Optimizations

### 1.1 Aggregate Queries

**Problem**: Multiple separate COUNT() and SUM() queries causing unnecessary database round-trips.

**Solution**: Combine multiple aggregates into single queries using `selectRaw()`.

#### DecisionDiscoveryDashboard

**Before** (4 separate queries):
```php
'total_runs' => DecisionDiscoveryRun::count(),
'total_decisions_ingested' => DecisionDiscoveryRun::sum('decisions_ingested'),
'total_decisions_evaluated' => DecisionDiscoveryRun::sum('decisions_evaluated'),
'success_rate' => $this->calculateSuccessRate(), // Another 2 queries!
```

**After** (1 query):
```php
$aggregates = DecisionDiscoveryRun::selectRaw('
    COUNT(*) as total_runs,
    SUM(decisions_ingested) as total_decisions_ingested,
    SUM(decisions_evaluated) as total_decisions_evaluated,
    SUM(CASE WHEN status = \'completed\' THEN 1 ELSE 0 END) as completed_runs
')->first();
```

**Impact**: Reduced from 6 queries to 1 query (83% reduction)

#### AgentMonitoringController Statistics

**Before** (6 separate queries):
```php
'total_runs' => AgentRun::recent($days)->count(),
'completed' => AgentRun::recent($days)->where('status', 'completed')->count(),
'failed' => AgentRun::recent($days)->where('status', 'failed')->count(),
// + 3 more for DecisionDiscoveryRun
```

**After** (2 queries - 1 per agent type):
```php
$researchAggregates = AgentRun::recent($days)
    ->selectRaw('
        COUNT(*) as total_runs,
        SUM(CASE WHEN status = \'completed\' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = \'failed\' THEN 1 ELSE 0 END) as failed
    ')->first();
```

**Impact**: Reduced from 6+ queries to 2 queries (67% reduction)

---

## 2. Eager Loading Optimizations

### 2.1 TextractManager Jobs List

**Problem**: N+1 queries when displaying job list with case and editor information.

**Before**:
```php
$query = TextractJob::query()->orderBy('updated_at', 'desc');
// View then accesses $job->case and $job->editor for each job
// Result: 1 query for jobs + N queries for cases + N queries for editors
```

**After**:
```php
$query = TextractJob::query()
    ->with(['case:id,title,case_number', 'editor:id,name'])
    ->orderBy('updated_at', 'desc');
// Result: 1 query for jobs + 1 for cases + 1 for editors = 3 queries total
```

**Impact**:
- For 20 jobs: Reduced from 41 queries to 3 queries (93% reduction)
- For 100 jobs: Reduced from 201 queries to 3 queries (98% reduction)

**Note**: Using selective column loading (`case:id,title,case_number`) further reduces memory usage and transfer time.

---

## 3. Caching Strategy

### 3.1 Cache Configuration

All caching uses Laravel's built-in Cache facade with TTL-based expiration:

| Component | Cache Key | TTL | Justification |
|-----------|-----------|-----|---------------|
| TextractManager Stats | `textract_manager_stats` | 30s | Stats change frequently during processing |
| Decision Discovery Stats | `decision_discovery_stats` | 60s | Runs complete slowly, less frequent updates |
| Agent Health | `agent_health_{days}d` | 60s | Health metrics for monitoring dashboards |
| Agent Statistics | `agent_statistics_{days}d` | 120s | Detailed statistics, expensive aggregates |

### 3.2 TextractManager Statistics

**Implementation**:
```php
public function getStatsProperty()
{
    return \Illuminate\Support\Facades\Cache::remember('textract_manager_stats', 30, function () {
        return [
            'total' => TextractJob::count(),
            'queued' => TextractJob::where('status', 'queued')->count(),
            'processing' => TextractJob::whereIn('status', [...])->count(),
            'succeeded' => TextractJob::where('status', 'succeeded')->count(),
            'failed' => TextractJob::where('status', 'failed')->count(),
            'needs_review' => TextractJob::whereRaw(...)->count(),
        ];
    });
}
```

**Cache Invalidation**:
```php
public function refreshJobs(): void
{
    $this->resetPage();
    Cache::forget('textract_manager_stats');
    $this->dispatch('jobs-refreshed');
}
```

**Impact**: Stats queries only execute once every 30 seconds instead of on every page load.

### 3.3 Agent Monitoring Health & Statistics

**Health Endpoint**:
- Cache key: `agent_health_{days}d` (e.g., `agent_health_7d`)
- TTL: 60 seconds
- Includes: Research agent metrics, decision discovery metrics, queue status

**Statistics Endpoint**:
- Cache key: `agent_statistics_{days}d` (e.g., `agent_statistics_30d`)
- TTL: 120 seconds (2 minutes)
- Includes: Detailed run counts, completion rates, duration averages

**Dynamic Cache Keys**: Cache keys include the `days` parameter so different time periods are cached separately.

**Impact**: Monitoring dashboards can refresh frequently without database load.

---

## 4. Performance Monitoring

### 4.1 Database Query Monitoring

Enable query logging in development to monitor query counts:

```php
// In AppServiceProvider boot()
if (app()->environment('local')) {
    DB::listen(function ($query) {
        Log::debug('Query', [
            'sql' => $query->sql,
            'bindings' => $query->bindings,
            'time' => $query->time,
        ]);
    });
}
```

### 4.2 Laravel Debugbar

Install Laravel Debugbar for development:

```bash
composer require barryvdh/laravel-debugbar --dev
```

Provides:
- Query count per request
- Query execution time
- Duplicate query detection
- Memory usage
- Cache hit/miss ratio

### 4.3 Performance Metrics

**Before Optimizations** (estimated based on analysis):
- TextractManager: ~50-200 queries per page load (depending on page size)
- DecisionDiscoveryDashboard: 6 queries per load
- AgentMonitoring Health: 8-10 queries per request
- AgentMonitoring Statistics: 12-15 queries per request

**After Optimizations**:
- TextractManager: 3-10 queries per page load (94-98% reduction)
- DecisionDiscoveryDashboard: 2 queries per load (67% reduction)
- AgentMonitoring Health: 0-5 queries per request (cached)
- AgentMonitoring Statistics: 0-2 queries per request (cached)

---

## 5. Best Practices

### 5.1 Eager Loading Checklist

When writing Eloquent queries that will be displayed in lists:

1. **Identify relationships** accessed in the view/component
2. **Add `with()`** to eager load those relationships
3. **Use selective columns** when only specific fields are needed:
   ```php
   ->with(['case:id,title,case_number'])  // Only load needed columns
   ```
4. **Avoid** accessing relationships in loops without eager loading

### 5.2 Caching Guidelines

**When to cache:**
- Expensive aggregation queries (COUNT, SUM, AVG)
- Frequently accessed, rarely changing data
- Dashboard statistics
- API responses for monitoring endpoints

**When NOT to cache:**
- Real-time data requirements
- User-specific data (unless keyed per user)
- Data that changes with every request

**Cache TTL Selection:**
- **10-30s**: High-frequency data with acceptable slight staleness
- **1-5min**: Dashboard stats, monitoring metrics
- **15-60min**: Configuration data, rarely changing lists
- **Hours/Days**: Static reference data

### 5.3 Query Optimization Patterns

**Pattern 1: Combine multiple counts**
```php
// Bad
$total = Model::count();
$active = Model::where('status', 'active')->count();
$failed = Model::where('status', 'failed')->count();

// Good
$stats = Model::selectRaw('
    COUNT(*) as total,
    SUM(CASE WHEN status = \'active\' THEN 1 ELSE 0 END) as active,
    SUM(CASE WHEN status = \'failed\' THEN 1 ELSE 0 END) as failed
')->first();
```

**Pattern 2: Eager load with constraints**
```php
// Load only recent comments
$posts = Post::with(['comments' => function ($query) {
    $query->where('created_at', '>', now()->subDays(7))
          ->orderBy('created_at', 'desc')
          ->limit(5);
}])->get();
```

**Pattern 3: Cache with dependencies**
```php
// Cache that's invalidated when model changes
$stats = Cache::remember('model_stats', 60, function () {
    return Model::calculateComplexStats();
});

// In model event:
static::saved(function () {
    Cache::forget('model_stats');
});
```

---

## 6. Future Optimization Opportunities

### 6.1 Database Indexes
- ✅ Completed in Task 4.C.3 (8 indexes added)
- Monitor slow query log for additional index opportunities

### 6.2 Query Result Pagination
- Consider cursor-based pagination for very large datasets
- Implement lazy loading for infinite scroll interfaces

### 6.3 Database Connection Pooling
- Configure connection pooling for high-traffic scenarios
- Consider read replicas for read-heavy workloads

### 6.4 Caching Layers
- Consider Redis for distributed caching in production
- Implement cache warming strategies for critical paths
- Add cache tagging for easier invalidation

### 6.5 Background Processing
- Move heavy computations to queued jobs
- Implement result caching for expensive operations
- Use chunk processing for large datasets

---

## 7. Testing Performance Improvements

### 7.1 Query Count Testing

```php
/** @test */
public function test_textract_manager_uses_eager_loading()
{
    TextractJob::factory()->count(20)->create();

    DB::enableQueryLog();

    $component = Livewire::test(TextractManager::class);

    $queries = DB::getQueryLog();

    // Should be significantly less than 20+ queries
    $this->assertLessThan(10, count($queries));
}
```

### 7.2 Cache Testing

```php
/** @test */
public function test_stats_are_cached()
{
    Cache::flush();

    $component = Livewire::test(TextractManager::class);

    // First call - cache miss
    $stats1 = $component->get('stats');

    // Second call - should hit cache
    $stats2 = $component->get('stats');

    $this->assertEquals($stats1, $stats2);
    $this->assertTrue(Cache::has('textract_manager_stats'));
}
```

---

## 8. Monitoring and Maintenance

### 8.1 Regular Performance Audits

Schedule quarterly reviews of:
- Database query logs
- Cache hit rates
- Page load times
- API response times

### 8.2 Performance Metrics

Track these metrics in production:
- Average queries per request
- Average query execution time
- Cache hit/miss ratio
- Page load times (p50, p95, p99)
- API endpoint response times

### 8.3 Alert Thresholds

Set up alerts for:
- Query count > 50 per request
- Average query time > 100ms
- Cache hit rate < 70%
- Page load time > 2 seconds

---

## References

- [Laravel Query Optimization](https://laravel.com/docs/11.x/queries#optimizing-queries)
- [Laravel Eloquent: Eager Loading](https://laravel.com/docs/11.x/eloquent-relationships#eager-loading)
- [Laravel Caching](https://laravel.com/docs/11.x/cache)
- [Database Performance Best Practices](https://use-the-index-luke.com/)

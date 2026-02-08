# Performance Baseline Tests

## Overview

This document outlines how to establish performance baselines after Sprint 9 configuration. Run these tests to verify that database indexing, caching, and configuration optimizations are working correctly.

## Prerequisites

- All Sprint 9 tasks completed (9.1-9.7)
- Production configuration applied
- Database migrations run
- Cache warmed
- Test data available

## Success Metrics

After completing Sprint 9, your system should meet these targets:

- **API Response Time**: < 500ms (p95)
- **Database Query Time**: < 100ms (p95)
- **Cache Hit Rate**: > 80%
- **Redis Operations/sec**: > 1000 ops/sec
- **PostgreSQL Cache Hit Ratio**: > 90%

---

## Test 1: Database Index Performance

### Test database query performance with new indexes.

```bash
# Connect to PostgreSQL
psql -U ai_legal_user -d ai_legal_war_machine_production

# Test law lookup by code (should use index)
EXPLAIN ANALYZE
SELECT * FROM ingested_laws WHERE law_code = 'ZKP';

# Expected: Index Scan using idx_ingested_laws_law_code
# Execution time: < 10ms

# Test court decision lookup (should use index)
EXPLAIN ANALYZE
SELECT * FROM court_decisions
WHERE court = 'Vrhovni sud Republike Hrvatske'
AND date >= '2024-01-01'
ORDER BY date DESC
LIMIT 10;

# Expected: Index Scan using idx_court_decisions_court_date
# Execution time: < 50ms

# Test user's cases (should use composite index)
EXPLAIN ANALYZE
SELECT * FROM legal_cases
WHERE user_id = 1
ORDER BY created_at DESC
LIMIT 20;

# Expected: Index Scan using idx_legal_cases_user_created
# Execution time: < 20ms
```

### Verify Index Usage

```sql
-- Check if indexes are being used
SELECT
    schemaname,
    tablename,
    indexname,
    idx_scan,
    idx_tup_read,
    idx_tup_fetch
FROM pg_stat_user_indexes
WHERE tablename IN ('ingested_laws', 'court_decisions', 'legal_cases')
ORDER BY idx_scan DESC;

-- idx_scan should be > 0 for frequently queried indexes
```

### Check for Missing Indexes

```sql
-- Find tables with sequential scans
SELECT
    schemaname,
    tablename,
    seq_scan,
    seq_tup_read,
    idx_scan,
    seq_tup_read / seq_scan as avg_seq_tup_read
FROM pg_stat_user_tables
WHERE seq_scan > 0
ORDER BY seq_tup_read DESC
LIMIT 20;

-- High seq_tup_read indicates missing indexes
```

**Pass Criteria**:
- [ ] All test queries use indexes (no Seq Scan)
- [ ] Query execution time < 100ms
- [ ] idx_scan > 0 for all critical indexes

---

## Test 2: Cache Hit Ratio

### Test Redis cache performance.

```bash
# Run cache monitor
php artisan cache:monitor

# Expected output:
# Hit Ratio: > 80% ✅ Excellent
# Memory Usage: < 2GB
# Fragmentation Ratio: 1.0-1.5 ✅ Healthy
```

### Warm Cache and Re-test

```bash
# Warm cache
php artisan cache:warm-production

# Monitor cache hit ratio
php artisan cache:monitor --watch

# Perform some queries (in another terminal)
php artisan tinker
>>> App\Models\IngestedLaw::where('law_code', 'ZKP')->first();
>>> App\Models\IngestedLaw::where('law_code', 'ZKP')->first(); // Should hit cache
>>> App\Models\CourtDecision::latest()->take(10)->get();

# Check hit ratio - should increase after repeated queries
```

### Verify Cache Invalidation

```bash
php artisan tinker

# Test cache invalidation on update
>>> $law = App\Models\IngestedLaw::where('law_code', 'ZKP')->first();
>>> $law->touch(); // Should invalidate cache
>>> // Next query should miss cache, then re-populate
>>> App\Models\IngestedLaw::where('law_code', 'ZKP')->first();
```

**Pass Criteria**:
- [ ] Cache hit ratio > 80% after warmup
- [ ] Cache invalidation works on model updates
- [ ] Memory usage within limits (< 4GB)

---

## Test 3: Application Response Time

### Test API endpoint performance.

```bash
# Install Apache Bench (if not installed)
sudo apt install apache2-utils

# Test health endpoint (should be fast)
ab -n 100 -c 10 http://localhost/api/health

# Expected:
# Time per request: < 100ms (mean)
# Requests per second: > 100

# Test law search endpoint
ab -n 50 -c 5 http://localhost/api/laws?search=kazneni

# Expected:
# Time per request: < 500ms (mean)
```

### Monitor with Real Traffic

```bash
# Monitor cache performance during load test
php artisan cache:monitor --watch

# In another terminal, run load test
ab -n 1000 -c 50 http://localhost/api/laws

# Watch cache hit ratio increase
# Watch memory usage stabilize
```

**Pass Criteria**:
- [ ] Health endpoint: < 100ms (p95)
- [ ] Search endpoint: < 500ms (p95)
- [ ] No timeout errors
- [ ] No memory exhaustion

---

## Test 4: Database Connection Pool

### Test PostgreSQL connection handling.

```sql
-- Check active connections
SELECT
    count(*),
    state,
    wait_event_type,
    wait_event
FROM pg_stat_activity
WHERE datname = 'ai_legal_war_machine_production'
GROUP BY state, wait_event_type, wait_event;

-- Expected:
-- Active connections < 50
-- No excessive waiting
```

### Stress Test Connections

```bash
# Run concurrent queries
for i in {1..20}; do
    php artisan tinker --execute="App\Models\IngestedLaw::count();" &
done
wait

# Check for connection errors
# Check PostgreSQL logs
sudo tail -100 /var/log/postgresql/postgresql-14-main.log
```

**Pass Criteria**:
- [ ] No connection timeout errors
- [ ] Active connections < max_connections / 2
- [ ] No connection pool exhaustion

---

## Test 5: Redis Database Separation

### Verify 4 Redis databases are working correctly.

```bash
# Test each database
redis-cli -a your-password

# Database 0: Cache
SELECT 0
DBSIZE
# Should show cache keys

# Database 1: Sessions
SELECT 1
DBSIZE
# Should show session keys

# Database 2: Queues
SELECT 2
DBSIZE
# Should show queue jobs

# Database 3: Broadcasting
SELECT 3
DBSIZE
# Should be empty or show broadcast keys

exit
```

### Test Queue Processing

```bash
# Dispatch test job
php artisan tinker
>>> dispatch(function() { logger('Test job executed'); });

# Check queue database
redis-cli -a your-password -n 2 KEYS '*'

# Process queue
php artisan queue:work --once

# Verify job was processed
tail -10 storage/logs/laravel.log
```

**Pass Criteria**:
- [ ] All 4 databases accessible
- [ ] Queues use database 2
- [ ] Sessions use database 1
- [ ] Cache uses database 0

---

## Test 6: Slow Query Logging

### Verify slow query logging is working.

```sql
-- Check if slow query logging is enabled
SHOW log_min_duration_statement;
-- Expected: 1000 (1 second)

-- Run a slow query (intentionally)
SELECT pg_sleep(2);

-- Check PostgreSQL logs for slow query
-- Should see: "duration: 2000.xxx ms"
```

```bash
# Check PostgreSQL logs
sudo tail -50 /var/log/postgresql/postgresql-14-main.log | grep "duration:"
```

**Pass Criteria**:
- [ ] Slow queries logged (> 1 second)
- [ ] Log shows duration and query text

---

## Test 7: Cache Warming on Deployment

### Test cache warming command.

```bash
# Clear cache
php artisan cache:clear

# Warm cache
time php artisan cache:warm-production

# Expected output:
# ✓ Cached X frequently accessed laws
# ✓ Cached X recent court decisions
# ✓ Cached X law articles
# ✓ Cached X system configurations
# ✓ Cached X user statistics
# ✅ Cache warming completed successfully!
# Duration: < 60 seconds

# Verify cache is populated
php artisan cache:monitor

# Expected:
# Keys: > 100
# Hit Ratio: Will increase with usage
```

**Pass Criteria**:
- [ ] Cache warming completes < 60 seconds
- [ ] No errors during warming
- [ ] Cache populated with expected data

---

## Test 8: Vector Search Performance (pgvector)

### Test embedding vector search if using pgvector.

```sql
-- Check if pgvector extension is installed
SELECT * FROM pg_extension WHERE extname = 'vector';

-- Test vector similarity search (if you have embeddings)
EXPLAIN ANALYZE
SELECT id, embedding <=> '[0.1, 0.2, ...]'::vector as distance
FROM openai_responses
WHERE embedding IS NOT NULL
ORDER BY distance
LIMIT 10;

-- Expected: Index Scan using idx_openai_responses_embedding_ivfflat
-- Execution time: < 100ms
```

**Pass Criteria**:
- [ ] pgvector extension installed
- [ ] Vector index exists
- [ ] Vector search uses index
- [ ] Query time < 100ms for 10k+ vectors

---

## Test 9: Autovacuum Performance

### Verify autovacuum is running.

```sql
-- Check autovacuum settings
SHOW autovacuum;
-- Expected: on

-- Check recent autovacuum activity
SELECT
    schemaname,
    tablename,
    last_vacuum,
    last_autovacuum,
    last_analyze,
    last_autoanalyze,
    n_live_tup,
    n_dead_tup
FROM pg_stat_user_tables
ORDER BY n_dead_tup DESC;

-- n_dead_tup should be low (< 10% of n_live_tup)
-- last_autovacuum should be recent
```

**Pass Criteria**:
- [ ] Autovacuum enabled
- [ ] Dead tuples < 10% of live tuples
- [ ] Recent autovacuum activity

---

## Test 10: Memory Usage Under Load

### Monitor memory usage during load testing.

```bash
# Monitor system memory
watch -n 1 free -h

# In another terminal, run load test
ab -n 10000 -c 100 http://localhost/api/laws

# Watch:
# - PostgreSQL memory usage
# - Redis memory usage
# - PHP-FPM memory usage
# - System memory available

# PostgreSQL memory
ps aux | grep postgres | awk '{sum+=$6} END {print sum/1024 " MB"}'

# Redis memory
redis-cli -a your-password INFO memory | grep used_memory_human

# PHP-FPM memory
ps aux | grep php-fpm | awk '{sum+=$6} END {print sum/1024 " MB"}'
```

**Pass Criteria**:
- [ ] PostgreSQL memory < 6GB
- [ ] Redis memory < 4GB
- [ ] PHP-FPM memory < 2GB
- [ ] Total system memory usage < 80%
- [ ] No OOM (out of memory) errors

---

## Baseline Results Template

Document your baseline results:

```markdown
# Performance Baseline Results

**Date**: 2025-11-08
**Environment**: Production VPS (16GB RAM, 4 cores)
**Database Size**: X GB
**Redis Size**: X MB

## Metrics

| Metric                    | Target   | Actual   | Status |
|---------------------------|----------|----------|--------|
| API Response Time (p95)   | < 500ms  | XXX ms   | ✅/❌  |
| Database Query Time (p95) | < 100ms  | XXX ms   | ✅/❌  |
| Cache Hit Rate            | > 80%    | XX%      | ✅/❌  |
| Redis Ops/sec             | > 1000   | XXXX     | ✅/❌  |
| PostgreSQL Cache Hit      | > 90%    | XX%      | ✅/❌  |
| Memory Usage              | < 12GB   | XX GB    | ✅/❌  |

## Issues Found

- [ ] Issue 1: Description and fix
- [ ] Issue 2: Description and fix

## Recommendations

- Recommendation 1
- Recommendation 2
```

---

## Troubleshooting

### Low Cache Hit Ratio (< 80%)

```bash
# Check cache warming
php artisan cache:warm-production --verbose

# Check cache configuration
php artisan config:show cache

# Check Redis memory limit
redis-cli -a your-password CONFIG GET maxmemory

# Check eviction policy
redis-cli -a your-password CONFIG GET maxmemory-policy
```

### Slow Queries (> 100ms)

```sql
-- Find slow queries
SELECT
    query,
    calls,
    total_exec_time,
    mean_exec_time,
    max_exec_time
FROM pg_stat_statements
ORDER BY mean_exec_time DESC
LIMIT 20;

-- Check missing indexes
SELECT
    schemaname,
    tablename,
    seq_scan,
    idx_scan,
    seq_scan / idx_scan as ratio
FROM pg_stat_user_tables
WHERE seq_scan > 0 AND idx_scan > 0
ORDER BY ratio DESC;
```

### High Memory Usage

```bash
# Check PostgreSQL connections
SELECT count(*) FROM pg_stat_activity;

# Check Redis memory by database
redis-cli -a your-password INFO keyspace

# Clear cache if needed
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Restart services
sudo systemctl restart postgresql
sudo systemctl restart redis-server
sudo systemctl restart php8.2-fpm
```

---

## Acceptance Criteria

All tests must pass before moving to Sprint 10:

- [x] Database indexes created and used
- [x] Cache hit ratio > 80%
- [x] API response time < 500ms (p95)
- [x] Database query time < 100ms (p95)
- [x] Redis 4 databases working
- [x] Cache invalidation working
- [x] Cache warming completes successfully
- [x] Slow query logging enabled
- [x] Autovacuum running
- [x] Memory usage within limits
- [x] No errors in logs
- [x] Baseline metrics documented

# Decision Discovery System - Operational Runbook

**Document Version**: 1.0
**Last Updated**: 2025-11-01
**System**: AI Legal War Machine - Decision Discovery
**Audience**: DevOps, System Administrators, Support Engineers

---

## Table of Contents

1. [System Overview](#system-overview)
2. [Prerequisites](#prerequisites)
3. [Manual Operations](#manual-operations)
4. [Monitoring & Logging](#monitoring--logging)
5. [Cache Management](#cache-management)
6. [Failure Recovery](#failure-recovery)
7. [Troubleshooting Guide](#troubleshooting-guide)
8. [Performance Tuning](#performance-tuning)
9. [Emergency Procedures](#emergency-procedures)
10. [Appendix](#appendix)

---

## System Overview

### What is Decision Discovery?

The Decision Discovery system is an autonomous agent that:
- Generates research topics using AI (OpenAI GPT-4o-mini)
- Searches Croatian court decisions on odluke.sudovi.hr
- Scores decisions for relevance using AI
- Automatically ingests high-quality decisions into the knowledge base
- Syncs data to Neo4j graph database for relationship analysis

### Architecture

```
Cron Scheduler (03:00 daily)
    ↓
Command: decisions:discovery --queue
    ↓
Queue Job: RunDecisionDiscovery (with retry)
    ↓
Agent: DecisionDiscoveryAgent::discover()
    ↓
External Services: OpenAI API, odluke.sudovi.hr, Neo4j
    ↓
Database: decision_discovery_runs table (tracking)
```

### Key Components

| Component | Location | Purpose |
|-----------|----------|---------|
| **Console Command** | `app/Console/Commands/DecisionDiscoveryCommand.php` | CLI interface for manual/scheduled runs |
| **Queue Job** | `app/Jobs/RunDecisionDiscovery.php` | Async execution with retry mechanism |
| **Discovery Agent** | `app/Agents/DecisionDiscoveryAgent.php` | Core discovery logic and AI orchestration |
| **Run Model** | `app/Models/DecisionDiscoveryRun.php` | Database tracking and statistics |
| **Scheduler** | `app/Console/Kernel.php:42` | Daily schedule configuration |

---

## Prerequisites

Before running the Decision Discovery system, ensure all dependencies are properly configured and running.

### 1. Database (Required)

**PostgreSQL 14+ with pgvector extension** (Production)

```bash
# Verify PostgreSQL is running
sudo systemctl status postgresql

# Check database connection
psql -h localhost -U your_db_user -d your_db_name -c "SELECT version();"

# Verify pgvector extension
psql -h localhost -U your_db_user -d your_db_name -c "SELECT * FROM pg_extension WHERE extname = 'vector';"
```

**Environment Variables:**
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=legal_war_machine
DB_USERNAME=your_db_user
DB_PASSWORD=your_secure_password
```

**SQLite** (Development only)
```env
DB_CONNECTION=sqlite
# Database file: database/database.sqlite
```

**Tables Required:**
- `decision_discovery_runs` - Tracks discovery executions
- `jobs` - Queue jobs (if using database queue)
- `failed_jobs` - Failed job tracking
- `cache` - Cache storage (if using database cache)

```bash
# Run migrations to create tables
php artisan migrate

# Verify tables exist
php artisan tinker
>>> \DB::table('decision_discovery_runs')->count();
```

---

### 2. Neo4j Graph Database (Required)

**Neo4j 5.x+**

```bash
# Verify Neo4j is running
systemctl status neo4j

# Check connection via HTTP
curl -u neo4j:password http://localhost:7474/db/neo4j/tx/commit

# Check connection via Bolt
php artisan tinker
>>> app(\Laudis\Neo4j\ClientBuilder::class)->build()->run('RETURN 1 as test');
```

**Environment Variables:**
```env
NEO4J_ENABLED=true
NEO4J_CONNECTION=bolt
NEO4J_URI=bolt://localhost:7687
NEO4J_HOST=localhost
NEO4J_PORT=7687
NEO4J_HTTP_PORT=7474
NEO4J_USER=neo4j
NEO4J_USERNAME=neo4j
NEO4J_PASSWORD=your_secure_password
NEO4J_DATABASE=neo4j
NEO4J_SIMILARITY_THRESHOLD=0.85
NEO4J_AUTO_SYNC=true
NEO4J_AUTO_SYNC_ON_INGEST=true
```

**Neo4j Configuration:**
```bash
# Edit neo4j.conf
sudo nano /etc/neo4j/neo4j.conf

# Recommended settings
dbms.memory.heap.initial_size=2G
dbms.memory.heap.max_size=4G
dbms.memory.pagecache.size=2G
```

**Health Check:**
```bash
# Test Neo4j connection
php artisan tinker
>>> app(\App\Services\Neo4j\Neo4jService::class)->testConnection();
```

---

### 3. Redis (Recommended for Production)

**Redis 6.x+** for queue and cache

```bash
# Verify Redis is running
sudo systemctl status redis

# Test connection
redis-cli ping
# Expected output: PONG

# Check Redis info
redis-cli info | grep version
```

**Environment Variables:**
```env
# Queue configuration
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null
REDIS_DB=0

# Cache configuration
CACHE_STORE=redis
REDIS_CACHE_CONNECTION=cache
REDIS_CACHE_DB=1
```

**Redis Configuration:**
```bash
# Edit redis.conf
sudo nano /etc/redis/redis.conf

# Recommended settings
maxmemory 512mb
maxmemory-policy allkeys-lru
```

**Health Check:**
```bash
# Test Redis connection
php artisan tinker
>>> \Illuminate\Support\Facades\Redis::ping();
>>> \Illuminate\Support\Facades\Cache::store('redis')->put('test', 'value', 10);
>>> \Illuminate\Support\Facades\Cache::store('redis')->get('test');
```

---

### 4. Queue Workers (Required for --queue option)

**Systemd Service** (Recommended for production)

Create file: `/etc/systemd/system/laravel-queue-worker.service`

```ini
[Unit]
Description=Laravel Queue Worker - Decision Discovery
After=network.target redis.service postgresql.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/html
ExecStart=/usr/bin/php /var/www/html/artisan queue:work redis --queue=default --sleep=3 --tries=3 --max-time=3600 --timeout=1800
Restart=always
RestartSec=5
StandardOutput=append:/var/www/html/storage/logs/queue-worker.log
StandardError=append:/var/www/html/storage/logs/queue-worker.log

# Security hardening
ProtectSystem=strict
ProtectHome=yes
ReadWritePaths=/var/www/html/storage
NoNewPrivileges=true

[Install]
WantedBy=multi-user.target
```

**Enable and start:**
```bash
# Reload systemd
sudo systemctl daemon-reload

# Enable service to start on boot
sudo systemctl enable laravel-queue-worker

# Start the service
sudo systemctl start laravel-queue-worker

# Check status
sudo systemctl status laravel-queue-worker

# View logs
sudo journalctl -u laravel-queue-worker -f
```

**Supervisor** (Alternative)

Create file: `/etc/supervisor/conf.d/laravel-queue.conf`

```ini
[program:laravel-queue-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/artisan queue:work redis --queue=default --sleep=3 --tries=3 --max-time=3600 --timeout=1800
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/html/storage/logs/queue-worker.log
stopwaitsecs=1800
```

**Enable supervisor:**
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-queue-worker:*
sudo supervisorctl status
```

---

### 5. OpenAI API Access (Required)

**API Key Configuration:**

```env
OPENAI_API_KEY=sk-proj-xxxxxxxxxxxxxxxxxxxxx
OPENAI_ORGANIZATION=org-xxxxxxxxxxxxxxxxxxxxx  # Optional
OPENAI_DEFAULT_MODEL=gpt-4o-mini
```

**Verify API Access:**
```bash
php artisan tinker
>>> $openai = app(\App\Services\OpenAIService::class);
>>> $response = $openai->chat([
...   ['role' => 'user', 'content' => 'Hello']
... ], 'gpt-4o-mini');
>>> $response['choices'][0]['message']['content'];
```

**API Rate Limits:**
- Ensure sufficient quota for ~50-100 API calls per discovery run
- Monitor usage at: https://platform.openai.com/usage

---

### 6. Cron Scheduler (Required)

**Laravel Scheduler Setup:**

```bash
# Edit crontab
crontab -e

# Add Laravel scheduler (runs every minute)
* * * * * cd /var/www/html && php artisan schedule:run >> /dev/null 2>&1
```

**Verify Scheduler:**
```bash
# List scheduled tasks
php artisan schedule:list

# Expected output should include:
# 0 3 * * * decisions:discovery --queue ... Next Due: Tomorrow at 3:00 AM

# Test scheduler manually
php artisan schedule:run
```

---

### 7. External API Access (Required)

**odluke.sudovi.hr API**

```bash
# Test connectivity
curl -I https://odluke.sudovi.hr

# Verify API endpoint
php artisan tinker
>>> $client = app(\App\Services\Odluke\OdlukeClient::class);
>>> $results = $client->collectIdsFromList('test', null, 1, 1);
>>> count($results);
```

**Environment Variables:**
```env
ODLUKE_BASE_URL=https://odluke.sudovi.hr
ODLUKE_TIMEOUT=30
ODLUKE_MAX_RETRIES=3
```

---

### Prerequisites Checklist

Before running Decision Discovery, verify:

- [ ] PostgreSQL is running and accessible
- [ ] Neo4j is running and accessible (bolt://localhost:7687)
- [ ] Redis is running (if using for queue/cache)
- [ ] Queue worker is running (systemd or supervisor)
- [ ] OpenAI API key is valid and has sufficient quota
- [ ] Cron scheduler is configured for Laravel
- [ ] odluke.sudovi.hr API is accessible
- [ ] All migrations are applied: `php artisan migrate:status`
- [ ] Storage directories are writable: `storage/logs`, `storage/framework/cache`

**Quick Health Check:**
```bash
# Run comprehensive health check
php artisan tinker
>>> app(\App\Services\HealthCheckService::class)->check();

# Or manually verify each component
php artisan queue:work --once  # Test queue
redis-cli ping                  # Test Redis
psql -h localhost -U user -d db -c "SELECT 1"  # Test DB
curl http://localhost:7474      # Test Neo4j HTTP
```

---

## Manual Operations

### Running Discovery Manually

#### Option 1: Direct Execution (Synchronous)

Run discovery immediately and wait for completion:

```bash
# Default configuration (5 topics, 50 decisions per topic, ingest top 10)
php artisan decisions:discovery

# Custom configuration
php artisan decisions:discovery --topics=10 --per-topic=100 --ingest=20 --threshold=80

# With detailed output
php artisan decisions:discovery -v
```

**Use Cases:**
- Development and testing
- Immediate one-time discovery
- Debugging issues
- Viewing real-time output

**Expected Output:**
```
Starting Decision Discovery...

Configuration:
+----------------------+-------+
| Setting              | Value |
+----------------------+-------+
| Topics per run       | 5     |
| Decisions per topic  | 50    |
| Ingest per topic     | 10    |
| Relevance threshold  | 70    |
+----------------------+-------+

Running discovery agent...

Discovery completed successfully!

+---------------------+-------+
| Metric              | Count |
+---------------------+-------+
| Topics Generated    | 5     |
| Decisions Evaluated | 250   |
| Decisions Ingested  | 50    |
| Errors              | 0     |
+---------------------+-------+

✓ Discovery run logged to decision_discovery_runs table
```

---

#### Option 2: Queue Dispatch (Asynchronous)

Dispatch to queue and return immediately:

```bash
# Dispatch to queue
php artisan decisions:discovery --queue

# With custom configuration
php artisan decisions:discovery --queue --topics=10 --threshold=80
```

**Use Cases:**
- Production deployments
- Long-running discoveries
- When retry mechanism is needed
- Non-blocking execution

**Expected Output:**
```
Starting Decision Discovery...
Dispatching to queue...
✓ Job dispatched to queue successfully
  Monitor with: php artisan queue:work
```

**Monitor Job:**
```bash
# Watch queue worker logs
tail -f storage/logs/queue-worker.log

# Or Laravel logs
tail -f storage/logs/laravel.log | grep "RunDecisionDiscovery"

# Check job status in database
php artisan tinker
>>> \DB::table('jobs')->where('queue', 'default')->count();
>>> \DB::table('failed_jobs')->latest()->first();
```

---

#### Option 3: Background Execution

Run in background (without queue):

```bash
# Run in background with nohup
nohup php artisan decisions:discovery > discovery.log 2>&1 &

# Check process
ps aux | grep decisions:discovery

# Monitor log
tail -f discovery.log
```

---

### Configuration Options

| Option | Default | Description | Example |
|--------|---------|-------------|---------|
| `--queue` | false | Dispatch to queue job | `--queue` |
| `--topics=N` | 5 | Number of research topics to generate | `--topics=10` |
| `--per-topic=N` | 50 | Max decisions to evaluate per topic | `--per-topic=100` |
| `--ingest=N` | 10 | Top decisions to ingest per topic | `--ingest=20` |
| `--threshold=N` | 70 | Minimum relevance score (0-100) | `--threshold=80` |

**Configuration Examples:**

```bash
# Quick discovery (fewer topics, lower threshold)
php artisan decisions:discovery --topics=3 --threshold=60

# Thorough discovery (more topics, higher threshold)
php artisan decisions:discovery --topics=10 --per-topic=100 --threshold=85

# Targeted discovery (more ingestion per topic)
php artisan decisions:discovery --topics=5 --ingest=20

# Production scheduled run (via queue)
php artisan decisions:discovery --queue
```

---

### Scheduled Execution

The system automatically runs daily at **03:00 AM** via Laravel scheduler.

**Schedule Configuration:**

Located in: `app/Console/Kernel.php:42`

```php
$schedule->command('decisions:discovery --queue')
         ->dailyAt('03:00')
         ->name('decision-discovery-scheduled')
         ->onOneServer()
         ->withoutOverlapping(120);
```

**View Schedule:**
```bash
# List all scheduled tasks
php artisan schedule:list

# Output:
# 0 3 * * * decisions:discovery --queue ............... Next Due: Tomorrow at 3:00 AM
```

**Test Schedule:**
```bash
# Manually trigger scheduler (runs all due tasks)
php artisan schedule:run

# Test specific schedule
php artisan schedule:test "decisions:discovery --queue"
```

**Disable Scheduled Run Temporarily:**
```bash
# Edit Kernel.php and comment out the schedule
# OR

# Stop cron temporarily
crontab -e
# Comment out: * * * * * cd /var/www/html && php artisan schedule:run >> /dev/null 2>&1

# Re-enable later by uncommenting
```

---

### Stopping a Running Discovery

#### If Running Synchronously:

```bash
# Find process
ps aux | grep "decisions:discovery"

# Kill process
kill -SIGTERM <pid>

# Force kill if not responding
kill -SIGKILL <pid>

# Clean up database status
php artisan tinker
>>> \App\Models\DecisionDiscoveryRun::where('status', 'running')->update(['status' => 'failed', 'completed_at' => now(), 'error_message' => 'Manually stopped']);
```

#### If Running via Queue:

```bash
# Check running jobs
php artisan queue:work --once
php artisan tinker
>>> \DB::table('jobs')->get();

# Stop queue worker (systemd)
sudo systemctl stop laravel-queue-worker

# Or (supervisor)
sudo supervisorctl stop laravel-queue-worker:*

# Clear pending jobs (CAUTION: clears ALL jobs)
php artisan queue:clear

# OR delete specific job
php artisan tinker
>>> \DB::table('jobs')->where('queue', 'default')->delete();

# Restart queue worker
sudo systemctl start laravel-queue-worker
```

---

### Retry Failed Runs

```bash
# View failed jobs
php artisan queue:failed

# Retry specific failed job
php artisan queue:retry <job-id>

# Retry all failed jobs
php artisan queue:retry all

# Flush all failed jobs (delete permanently)
php artisan queue:flush
```

---

## Monitoring & Logging

### Log Locations

#### 1. Primary Application Log

**Location:** `storage/logs/laravel.log`

```bash
# View entire log
cat storage/logs/laravel.log

# Tail log (follow new entries)
tail -f storage/logs/laravel.log

# Filter for discovery events
tail -f storage/logs/laravel.log | grep -i "discovery"

# Filter for errors only
tail -f storage/logs/laravel.log | grep -i "error"

# Search for specific run
grep "RunDecisionDiscovery" storage/logs/laravel.log
```

**Log Format:**
```
[2025-11-01 03:00:01] production.INFO: Starting autonomous decision discovery
[2025-11-01 03:00:02] production.INFO: RunDecisionDiscovery - Starting {"attempt":1,"max_attempts":3,"config":{"topics":5,"per_topic":50,"ingest":10,"threshold":70}}
[2025-11-01 03:00:03] production.INFO: Generated research topics {"count":5,"topics":["Radno pravo","Ugovorno pravo",...]}
[2025-11-01 03:15:42] production.INFO: RunDecisionDiscovery - Completed successfully {"attempt":1,"stats":{"topics_generated":5,"decisions_evaluated":250,"decisions_ingested":50,"errors":[]}}
```

---

#### 2. Daily Rotating Logs (Production)

**Configuration:** `config/logging.php`

```env
LOG_CHANNEL=stack
LOG_STACK=daily,slack
LOG_DAILY_DAYS=14
```

**Logs rotate daily with 14-day retention:**
```bash
storage/logs/laravel-2025-11-01.log
storage/logs/laravel-2025-11-02.log
...
storage/logs/laravel-2025-11-14.log
```

**View today's log:**
```bash
tail -f storage/logs/laravel-$(date +%Y-%m-%d).log
```

---

#### 3. Queue Worker Logs

**Location:**
- Systemd: `sudo journalctl -u laravel-queue-worker`
- Supervisor: `storage/logs/queue-worker.log`

```bash
# Systemd logs
sudo journalctl -u laravel-queue-worker -f
sudo journalctl -u laravel-queue-worker --since "1 hour ago"
sudo journalctl -u laravel-queue-worker --since "2025-11-01 03:00:00"

# Supervisor logs
tail -f storage/logs/queue-worker.log
```

---

#### 4. OpenAI API Logs (Optional)

**Location:** `storage/logs/openai.log` (JSON formatted)

```bash
# View OpenAI API calls
cat storage/logs/openai.log | jq '.'

# Filter by date
cat storage/logs/openai.log | jq 'select(.timestamp | startswith("2025-11-01"))'

# Count API calls
cat storage/logs/openai.log | jq -s 'length'
```

---

#### 5. Slack Notifications (Production)

**Configuration:**
```env
LOG_SLACK_WEBHOOK_URL=https://hooks.slack.com/services/YOUR/WEBHOOK/URL
LOG_LEVEL=error  # Only send errors to Slack
```

**Received Notifications:**
- Job failures (after all retries exhausted)
- Critical errors during discovery
- Database connection issues

---

### Database Monitoring

#### Query Discovery Runs

```bash
php artisan tinker
```

**Recent runs:**
```php
>>> \App\Models\DecisionDiscoveryRun::latest()->take(10)->get(['id', 'status', 'topics_generated', 'decisions_ingested', 'started_at', 'completed_at']);
```

**Running jobs:**
```php
>>> \App\Models\DecisionDiscoveryRun::where('status', 'running')->get();
```

**Failed runs:**
```php
>>> \App\Models\DecisionDiscoveryRun::where('status', 'failed')->latest()->take(5)->get();
```

**Statistics:**
```php
>>> \App\Models\DecisionDiscoveryRun::getStatistics();
// Returns: success_rate, avg_duration, total_discovered, total_ingested
```

**Success rate:**
```php
>>> \App\Models\DecisionDiscoveryRun::getSuccessRate();
// Returns percentage: 95.5
```

**Recent runs (last 7 days):**
```php
>>> \App\Models\DecisionDiscoveryRun::recent(7)->get();
```

---

#### SQL Queries

```sql
-- Recent runs
SELECT id, status, topics_generated, decisions_evaluated, decisions_ingested,
       started_at, completed_at, EXTRACT(EPOCH FROM (completed_at - started_at)) as duration_seconds
FROM decision_discovery_runs
ORDER BY started_at DESC
LIMIT 10;

-- Currently running
SELECT * FROM decision_discovery_runs WHERE status = 'running';

-- Failed runs today
SELECT * FROM decision_discovery_runs
WHERE status = 'failed'
AND DATE(started_at) = CURRENT_DATE;

-- Average statistics
SELECT
  COUNT(*) as total_runs,
  COUNT(*) FILTER (WHERE status = 'completed') as successful_runs,
  ROUND(AVG(topics_generated), 2) as avg_topics,
  ROUND(AVG(decisions_evaluated), 2) as avg_evaluated,
  ROUND(AVG(decisions_ingested), 2) as avg_ingested,
  ROUND(AVG(EXTRACT(EPOCH FROM (completed_at - started_at))), 2) as avg_duration_seconds
FROM decision_discovery_runs
WHERE started_at > NOW() - INTERVAL '30 days';
```

---

### Real-time Monitoring

**Laravel Pail** (log streaming):
```bash
# Stream logs in real-time with colors and formatting
php artisan pail --timeout=0

# Filter for specific strings
php artisan pail --filter="discovery"
```

**Watch Discovery Status:**
```bash
# Create monitoring script
watch -n 5 'php artisan tinker --execute="echo \App\Models\DecisionDiscoveryRun::latest()->first()->status ?? \"No runs\""'
```

---

### Performance Metrics

**Expected Runtime:**
- **Per Topic:** 2-5 minutes
- **Full Run (5 topics):** 10-25 minutes
- **Varies by:** API latency, decision volume, LLM response time

**Monitor Runtime:**
```php
>>> $run = \App\Models\DecisionDiscoveryRun::latest()->first();
>>> $run->duration(); // Returns seconds
>>> $run->started_at->diffForHumans($run->completed_at); // Human-readable
```

---

## Cache Management

The Decision Discovery system uses caching to optimize performance and reduce API costs.

### Cache Configuration

**Current Setup:**
```env
# Development
CACHE_STORE=database
DB_CACHE_TABLE=cache

# Production
CACHE_STORE=redis
REDIS_CACHE_CONNECTION=cache
REDIS_CACHE_DB=1
CACHE_PREFIX=legal_cache_
```

---

### Cached Data

| Cache Key | TTL | Purpose | Impact if Cleared |
|-----------|-----|---------|-------------------|
| `decision_discovery:topics:{YEAR-WEEK}` | 1 week | LLM-generated research topics | New topics generated (costs ~$0.01) |

**Example:** `decision_discovery:topics:2025-44` (Week 44 of 2025)

---

### Cache Operations

#### View Cache Contents

```bash
php artisan tinker
```

**Check if topic cache exists:**
```php
>>> $cacheKey = 'decision_discovery:topics:' . date('Y-W');
>>> \Cache::has($cacheKey);
>>> \Cache::get($cacheKey);
```

**View all cache keys (Redis only):**
```php
>>> \Redis::connection('cache')->keys('legal_cache_*decision*');
```

---

#### Clear Discovery Cache

**Clear topic cache (force new generation):**

```bash
php artisan tinker
>>> $cacheKey = 'decision_discovery:topics:' . date('Y-W');
>>> \Cache::forget($cacheKey);
>>> "Cache cleared: {$cacheKey}";
```

**Clear all discovery-related cache:**

```bash
php artisan tinker
>>> \Cache::flush(); // CAUTION: Clears ALL cache

# Or Redis-specific
>>> \Redis::connection('cache')->flushdb(); // CAUTION: Clears cache database
```

---

#### Clear Specific Week's Topics

```bash
php artisan tinker
>>> // Clear last week
>>> $lastWeek = \Carbon\Carbon::now()->subWeek()->format('Y-W');
>>> \Cache::forget("decision_discovery:topics:{$lastWeek}");

>>> // Clear this week (force fresh topics today)
>>> $thisWeek = date('Y-W');
>>> \Cache::forget("decision_discovery:topics:{$thisWeek}");
```

---

#### Manual Cache Warming

**Pre-generate topics before scheduled run:**

```bash
php artisan tinker
>>> $agent = app(\App\Agents\DecisionDiscoveryAgent::class);
>>> // This will generate and cache topics
>>> $reflection = new \ReflectionClass($agent);
>>> $method = $reflection->getMethod('generateResearchTopics');
>>> $method->setAccessible(true);
>>> $topics = $method->invoke($agent);
>>> print_r($topics);
```

---

### Cache Troubleshooting

**Issue: Stale topics being used**

```bash
# Solution 1: Clear current week's cache
php artisan tinker
>>> \Cache::forget('decision_discovery:topics:' . date('Y-W'));

# Solution 2: Run discovery with --force to bypass (if implemented)
php artisan decisions:discovery --force-fresh-topics
```

**Issue: Cache storage full (Redis)**

```bash
# Check Redis memory
redis-cli info memory

# Clear only decision discovery cache
redis-cli --scan --pattern "legal_cache_decision_discovery:*" | xargs redis-cli del

# Or increase Redis maxmemory
sudo nano /etc/redis/redis.conf
# Set: maxmemory 1gb
sudo systemctl restart redis
```

**Issue: Database cache table too large**

```sql
-- Check cache table size
SELECT pg_size_pretty(pg_total_relation_size('cache'));

-- Clear expired cache entries
DELETE FROM cache WHERE expiration < EXTRACT(EPOCH FROM NOW());

-- Vacuum table
VACUUM FULL cache;
```

---

### Cache Best Practices

1. **Don't clear cache unnecessarily** - Topics are expensive to regenerate
2. **Monitor cache hit rate** - Aim for 90%+ hit rate
3. **Use Redis in production** - Much faster than database cache
4. **Set appropriate TTLs** - 1 week for topics is optimal
5. **Clear cache on content changes** - If legal taxonomy changes, clear topic cache

---

## Failure Recovery

### Failure Types and Recovery Steps

#### 1. Discovery Run Failed (status='failed')

**Symptoms:**
- Database record shows status='failed'
- Error message in `error_message` field
- Logs show exception trace

**Diagnosis:**
```bash
# Check latest failed run
php artisan tinker
>>> $failed = \App\Models\DecisionDiscoveryRun::where('status', 'failed')->latest()->first();
>>> $failed->error_message;
>>> $failed->errors; // Array of per-topic errors
```

**Recovery Steps:**

1. **Identify root cause:**
```bash
# Check error message
>>> $failed->error_message;

# Common causes:
# - "OpenAI API error" → API key issue
# - "Connection timeout" → Network/API issue
# - "Neo4j connection failed" → Graph DB down
```

2. **Fix underlying issue:**
```bash
# Verify OpenAI API
php artisan tinker
>>> app(\App\Services\OpenAIService::class)->chat([['role' => 'user', 'content' => 'test']], 'gpt-4o-mini');

# Verify Neo4j
>>> app(\Laudis\Neo4j\ClientBuilder::class)->build()->run('RETURN 1');

# Verify odluke.sudovi.hr
>>> app(\App\Services\Odluke\OdlukeClient::class)->collectIdsFromList('test', null, 1, 1);
```

3. **Retry discovery:**
```bash
# If using queue job, retry from failed_jobs
php artisan queue:retry <job-id>

# Or run manually
php artisan decisions:discovery --queue
```

---

#### 2. Stuck in 'running' Status

**Symptoms:**
- Discovery run shows status='running' for >2 hours
- Process not actually running
- New runs blocked

**Diagnosis:**
```bash
php artisan tinker
>>> $stuck = \App\Models\DecisionDiscoveryRun::where('status', 'running')->where('started_at', '<', now()->subHours(2))->first();
>>> $stuck;
```

**Recovery Steps:**

1. **Verify process is truly not running:**
```bash
# Check for running processes
ps aux | grep "decisions:discovery"
ps aux | grep "queue:work"

# Check queue worker status
sudo systemctl status laravel-queue-worker
```

2. **Manually mark as failed:**
```bash
php artisan tinker
>>> $stuck = \App\Models\DecisionDiscoveryRun::where('status', 'running')->where('started_at', '<', now()->subHours(2))->first();
>>> $stuck->update([
...   'status' => 'failed',
...   'completed_at' => now(),
...   'error_message' => 'Manually marked as failed - process stuck/killed'
... ]);
```

3. **Restart queue worker:**
```bash
sudo systemctl restart laravel-queue-worker
```

4. **Run new discovery:**
```bash
php artisan decisions:discovery --queue
```

---

#### 3. Queue Worker Died/Stopped

**Symptoms:**
- Jobs dispatched but not processing
- `php artisan queue:work` not running
- Systemd service stopped

**Diagnosis:**
```bash
# Check systemd service
sudo systemctl status laravel-queue-worker

# Check for zombie processes
ps aux | grep queue:work

# Check failed jobs
php artisan queue:failed
```

**Recovery Steps:**

1. **Check why worker stopped:**
```bash
# View systemd logs
sudo journalctl -u laravel-queue-worker --since "1 hour ago"

# Common causes:
# - Out of memory (OOM killer)
# - Max execution time exceeded
# - Manual stop
```

2. **Restart worker:**
```bash
# Systemd
sudo systemctl restart laravel-queue-worker
sudo systemctl status laravel-queue-worker

# Supervisor
sudo supervisorctl restart laravel-queue-worker:*
sudo supervisorctl status
```

3. **Verify worker is processing:**
```bash
# Dispatch test job
php artisan tinker
>>> \App\Jobs\RunDecisionDiscovery::dispatch(5, 50, 10, 70.0);

# Watch logs
tail -f storage/logs/laravel.log | grep "RunDecisionDiscovery"
```

4. **Process pending jobs:**
```bash
# Check pending jobs
php artisan tinker
>>> \DB::table('jobs')->count();

# Jobs will be processed automatically by worker
```

---

#### 4. Database Connection Lost

**Symptoms:**
- "Connection refused" errors
- Discovery fails immediately
- Queue jobs fail

**Diagnosis:**
```bash
# Test database connection
php artisan tinker
>>> \DB::connection()->getPdo();

# Check PostgreSQL status
sudo systemctl status postgresql

# Check connection settings
php artisan tinker
>>> config('database.connections.pgsql');
```

**Recovery Steps:**

1. **Restart PostgreSQL:**
```bash
sudo systemctl restart postgresql
sudo systemctl status postgresql
```

2. **Verify connection:**
```bash
psql -h localhost -U your_user -d your_db -c "SELECT 1"
```

3. **Reconnect Laravel:**
```bash
php artisan config:clear
php artisan cache:clear
php artisan tinker
>>> \DB::reconnect();
```

4. **Retry failed runs:**
```bash
php artisan queue:retry all
```

---

#### 5. Neo4j Sync Failures

**Symptoms:**
- Decisions ingested but not in graph
- "Neo4j connection failed" errors
- Graph out of sync

**Diagnosis:**
```bash
# Check Neo4j status
sudo systemctl status neo4j
curl -u neo4j:password http://localhost:7474/db/neo4j/tx/commit

# Check sync settings
php artisan tinker
>>> config('neo4j.auto_sync');
>>> config('neo4j.auto_sync_on_ingest');
```

**Recovery Steps:**

1. **Restart Neo4j:**
```bash
sudo systemctl restart neo4j
sudo systemctl status neo4j
```

2. **Re-sync decisions manually:**
```bash
# If graph:sync command exists
php artisan graph:sync

# Or via tinker
php artisan tinker
>>> $service = app(\App\Services\Neo4j\Neo4jService::class);
>>> $service->syncRecentDecisions();
```

3. **Update sync settings:**
```env
NEO4J_ENABLED=true
NEO4J_AUTO_SYNC=true
NEO4J_AUTO_SYNC_ON_INGEST=true
```

4. **Run discovery again:**
```bash
php artisan decisions:discovery --queue
```

---

#### 6. OpenAI API Rate Limit

**Symptoms:**
- "Rate limit exceeded" errors
- 429 HTTP status codes
- Discovery generates fallback topics

**Diagnosis:**
```bash
# Check logs for rate limit errors
grep -i "rate limit" storage/logs/laravel.log

# Check OpenAI dashboard
# Visit: https://platform.openai.com/usage
```

**Recovery Steps:**

1. **Wait for rate limit reset** (usually 1 minute)

2. **Reduce API calls:**
```bash
# Run discovery with fewer topics
php artisan decisions:discovery --topics=3

# Or adjust per-topic decisions
php artisan decisions:discovery --per-topic=25
```

3. **Upgrade OpenAI plan** (if persistent issue)

4. **Use fallback topics temporarily:**
```bash
# Clear cache to trigger fallback mechanism
php artisan tinker
>>> \Cache::forget('decision_discovery:topics:' . date('Y-W'));

# Agent will use predefined topics if LLM fails
```

---

#### 7. Disk Space Full

**Symptoms:**
- "No space left on device" errors
- Log writing fails
- Cache writes fail

**Diagnosis:**
```bash
# Check disk usage
df -h

# Check Laravel storage
du -sh storage/*

# Check logs size
du -sh storage/logs/*
```

**Recovery Steps:**

1. **Clean old logs:**
```bash
# Remove logs older than 30 days
find storage/logs -name "*.log" -type f -mtime +30 -delete

# Or truncate current log
> storage/logs/laravel.log
```

2. **Clear cache:**
```bash
php artisan cache:clear
php artisan view:clear
php artisan route:clear
php artisan config:clear
```

3. **Clean temp files:**
```bash
rm -rf storage/framework/cache/data/*
rm -rf storage/framework/sessions/*
rm -rf storage/framework/views/*
```

4. **Archive old data:**
```sql
-- Archive old discovery runs
INSERT INTO decision_discovery_runs_archive
SELECT * FROM decision_discovery_runs
WHERE started_at < NOW() - INTERVAL '90 days';

DELETE FROM decision_discovery_runs
WHERE started_at < NOW() - INTERVAL '90 days';
```

---

### Recovery Checklist

After any failure, verify:

- [ ] All prerequisites are running (DB, Neo4j, Redis, queue worker)
- [ ] No runs stuck in 'running' status
- [ ] Queue worker is processing jobs
- [ ] Logs are being written
- [ ] Disk space is available
- [ ] API keys are valid
- [ ] Network connectivity is stable
- [ ] Run a test discovery: `php artisan decisions:discovery --topics=1`

---

## Troubleshooting Guide

### Common Issues

#### Issue: "Class 'DecisionDiscoveryAgent' not found"

**Cause:** Autoloader not refreshed

**Solution:**
```bash
composer dump-autoload
php artisan clear-compiled
php artisan config:clear
```

---

#### Issue: "Queue connection refused"

**Cause:** Redis not running or misconfigured

**Solution:**
```bash
# Check Redis
sudo systemctl status redis
redis-cli ping

# Test connection
php artisan tinker
>>> \Illuminate\Support\Facades\Redis::ping();

# Update .env if needed
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

---

#### Issue: "Neo4j connection timeout"

**Cause:** Neo4j not running or firewall blocking

**Solution:**
```bash
# Check Neo4j
sudo systemctl status neo4j
curl -u neo4j:password http://localhost:7474

# Check firewall
sudo ufw status
sudo ufw allow 7687/tcp
sudo ufw allow 7474/tcp

# Test connection
php artisan tinker
>>> app(\Laudis\Neo4j\ClientBuilder::class)->build()->run('RETURN 1');
```

---

#### Issue: "Topics fallback to defaults"

**Cause:** OpenAI API error or invalid key

**Solution:**
```bash
# Verify API key
php artisan tinker
>>> config('services.openai.key');

# Test API
>>> app(\App\Services\OpenAIService::class)->chat([['role' => 'user', 'content' => 'test']], 'gpt-4o-mini');

# Update .env
OPENAI_API_KEY=sk-proj-xxxxx
```

---

#### Issue: "No decisions found for topic"

**Cause:** odluke.sudovi.hr API down or rate-limited

**Solution:**
```bash
# Test API
curl -I https://odluke.sudovi.hr
php artisan tinker
>>> app(\App\Services\Odluke\OdlukeClient::class)->collectIdsFromList('test', null, 1, 1);

# Check logs for API errors
grep "odluke" storage/logs/laravel.log

# Wait and retry (may be temporary rate limit)
```

---

#### Issue: "Scheduler not running"

**Cause:** Cron not configured

**Solution:**
```bash
# Check crontab
crontab -l

# Should contain:
# * * * * * cd /var/www/html && php artisan schedule:run >> /dev/null 2>&1

# Add if missing
crontab -e

# Verify scheduler
php artisan schedule:list
```

---

#### Issue: "Permission denied" errors

**Cause:** Incorrect file permissions

**Solution:**
```bash
# Fix storage permissions
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

# SELinux (if applicable)
sudo chcon -R -t httpd_sys_rw_content_t storage
sudo chcon -R -t httpd_sys_rw_content_t bootstrap/cache
```

---

### Debug Mode

**Enable verbose logging:**

```env
# .env
APP_DEBUG=true
LOG_LEVEL=debug
```

**Run with verbose output:**
```bash
php artisan decisions:discovery -vvv
```

**Enable query logging:**
```php
// In tinker
>>> \DB::enableQueryLog();
>>> // Run discovery
>>> \DB::getQueryLog();
```

---

### Health Check Script

Create: `scripts/discovery-health-check.sh`

```bash
#!/bin/bash

echo "=== Decision Discovery Health Check ==="
echo ""

# Check database
echo -n "Database: "
php artisan tinker --execute="try { \DB::connection()->getPdo(); echo 'OK'; } catch (\Exception \$e) { echo 'FAIL: ' . \$e->getMessage(); }" 2>/dev/null || echo "FAIL"

# Check Redis
echo -n "Redis: "
redis-cli ping > /dev/null 2>&1 && echo "OK" || echo "FAIL"

# Check Neo4j
echo -n "Neo4j: "
curl -s -u neo4j:${NEO4J_PASSWORD} http://localhost:7474 > /dev/null 2>&1 && echo "OK" || echo "FAIL"

# Check queue worker
echo -n "Queue Worker: "
systemctl is-active laravel-queue-worker > /dev/null 2>&1 && echo "OK" || echo "FAIL"

# Check OpenAI API
echo -n "OpenAI API: "
php artisan tinker --execute="try { app(\App\Services\OpenAIService::class)->chat([['role' => 'user', 'content' => 'test']], 'gpt-4o-mini'); echo 'OK'; } catch (\Exception \$e) { echo 'FAIL'; }" 2>/dev/null || echo "FAIL"

# Check running discoveries
echo -n "Running Discoveries: "
php artisan tinker --execute="echo \App\Models\DecisionDiscoveryRun::where('status', 'running')->count();" 2>/dev/null

# Check disk space
echo -n "Disk Space: "
df -h / | awk 'NR==2 {print $5 " used"}'

echo ""
echo "=== End Health Check ==="
```

**Make executable and run:**
```bash
chmod +x scripts/discovery-health-check.sh
./scripts/discovery-health-check.sh
```

---

## Performance Tuning

### Optimize Discovery Speed

**1. Reduce scope:**
```bash
# Fewer topics
php artisan decisions:discovery --topics=3

# Fewer decisions per topic
php artisan decisions:discovery --per-topic=25

# Higher threshold (fewer ingestions)
php artisan decisions:discovery --threshold=80
```

**2. Increase cache TTL:**

Extend topic cache beyond 1 week:

Edit `app/Agents/DecisionDiscoveryAgent.php:232`:
```php
Cache::put($cacheKey, $topics, now()->addWeeks(2)); // Changed from 1 week
```

**3. Parallel processing (future enhancement):**

Run multiple discoveries in parallel for different topics:
```bash
# Not currently supported - would require code changes
```

---

### Optimize Resource Usage

**1. Queue worker tuning:**

```ini
# Increase timeout for long-running jobs
ExecStart=/usr/bin/php artisan queue:work redis --timeout=2400 --max-time=3600

# Reduce memory usage
ExecStart=/usr/bin/php artisan queue:work redis --memory=256

# Process fewer jobs before restart
ExecStart=/usr/bin/php artisan queue:work redis --max-jobs=50
```

**2. Database optimization:**

```sql
-- Add indexes
CREATE INDEX idx_discovery_runs_status ON decision_discovery_runs(status);
CREATE INDEX idx_discovery_runs_started_at ON decision_discovery_runs(started_at);

-- Analyze tables
ANALYZE decision_discovery_runs;
```

**3. Redis optimization:**

```bash
# Edit /etc/redis/redis.conf
maxmemory 512mb
maxmemory-policy allkeys-lru
save "" # Disable RDB snapshots for speed
```

**4. Neo4j optimization:**

```bash
# Edit /etc/neo4j/neo4j.conf
dbms.memory.heap.max_size=4G
dbms.memory.pagecache.size=2G
dbms.jvm.additional=-XX:+UseG1GC
```

---

### Monitor Performance

**Track execution time:**
```php
>>> $runs = \App\Models\DecisionDiscoveryRun::whereNotNull('completed_at')->latest()->take(10)->get();
>>> $runs->pluck('duration')->avg();
```

**Monitor API call count:**
```bash
# Count OpenAI API calls in logs
grep -c "OpenAI API" storage/logs/laravel.log
```

**Monitor resource usage:**
```bash
# CPU and memory
top -p $(pgrep -f "queue:work")

# Database connections
psql -c "SELECT count(*) FROM pg_stat_activity;"

# Redis memory
redis-cli info memory | grep used_memory_human
```

---

## Emergency Procedures

### Emergency Stop

**Stop all discovery operations immediately:**

```bash
#!/bin/bash
# emergency-stop.sh

echo "=== EMERGENCY STOP: Decision Discovery ==="

# Stop queue worker
echo "Stopping queue worker..."
sudo systemctl stop laravel-queue-worker

# Kill any running discovery processes
echo "Killing discovery processes..."
pkill -f "decisions:discovery"
pkill -f "RunDecisionDiscovery"

# Mark stuck runs as failed
echo "Cleaning up stuck runs..."
php artisan tinker --execute="
\App\Models\DecisionDiscoveryRun::where('status', 'running')->update([
  'status' => 'failed',
  'completed_at' => now(),
  'error_message' => 'Emergency stop initiated'
]);
echo 'Marked ' . \App\Models\DecisionDiscoveryRun::where('status', 'running')->count() . ' runs as failed';
"

# Clear pending jobs
echo "Clearing pending queue jobs..."
php artisan queue:clear

echo "=== Emergency stop complete ==="
```

---

### Disaster Recovery

**Full system recovery:**

1. **Backup current state:**
```bash
pg_dump -U user -d db > backup_$(date +%Y%m%d_%H%M%S).sql
cp -r storage/logs logs_backup_$(date +%Y%m%d_%H%M%S)
```

2. **Reset all services:**
```bash
sudo systemctl restart postgresql
sudo systemctl restart neo4j
sudo systemctl restart redis
sudo systemctl restart laravel-queue-worker
```

3. **Clear all caches:**
```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
redis-cli flushdb
```

4. **Clean stuck data:**
```bash
php artisan tinker --execute="
\App\Models\DecisionDiscoveryRun::where('status', 'running')->update(['status' => 'failed', 'completed_at' => now()]);
\DB::table('jobs')->truncate();
\DB::table('failed_jobs')->truncate();
"
```

5. **Verify system health:**
```bash
./scripts/discovery-health-check.sh
```

6. **Run test discovery:**
```bash
php artisan decisions:discovery --topics=1 --per-topic=5 --ingest=1
```

---

## Appendix

### Related Documentation

- [decision-discovery-async-flow.md](./decision-discovery-async-flow.md) - Technical flow documentation
- [CONFIGURATION.md](./CONFIGURATION.md) - Full environment variable reference
- [TESTING_GUIDE.md](./TESTING_GUIDE.md) - Testing procedures
- [ARCHITECTURE.md](./ARCHITECTURE.md) - System architecture overview

---

### Command Reference

| Command | Purpose |
|---------|---------|
| `php artisan decisions:discovery` | Run discovery synchronously |
| `php artisan decisions:discovery --queue` | Dispatch to queue |
| `php artisan queue:work` | Start queue worker |
| `php artisan queue:failed` | List failed jobs |
| `php artisan queue:retry <id>` | Retry failed job |
| `php artisan queue:flush` | Clear failed jobs |
| `php artisan schedule:list` | View scheduled tasks |
| `php artisan schedule:run` | Run scheduler manually |
| `php artisan cache:clear` | Clear application cache |
| `php artisan pail` | Stream logs in real-time |

---

### Environment Variables Reference

**Core Discovery Settings:**
```env
OPENAI_API_KEY=sk-proj-xxxxx
OPENAI_DEFAULT_MODEL=gpt-4o-mini

ODLUKE_BASE_URL=https://odluke.sudovi.hr
ODLUKE_TIMEOUT=30

QUEUE_CONNECTION=redis
CACHE_STORE=redis

NEO4J_ENABLED=true
NEO4J_URI=bolt://localhost:7687
NEO4J_USERNAME=neo4j
NEO4J_PASSWORD=your_password
NEO4J_AUTO_SYNC=true
```

**Full reference:** See [CONFIGURATION.md](./CONFIGURATION.md)

---

### Contact & Support

**For operational issues:**
- Check logs: `storage/logs/laravel.log`
- Run health check: `./scripts/discovery-health-check.sh`
- Review failed runs: `php artisan tinker` → `DecisionDiscoveryRun::where('status', 'failed')->latest()->first()`

**For code issues:**
- Review: `app/Agents/DecisionDiscoveryAgent.php`
- Review: `app/Jobs/RunDecisionDiscovery.php`
- Review: `app/Console/Commands/DecisionDiscoveryCommand.php`

---

**Document Version**: 1.0
**Last Updated**: 2025-11-01
**Maintained By**: DevOps Team
**Review Schedule**: Quarterly




# Decision Discovery Operations Runbook

## Overview

This runbook covers operational procedures for the Decision Discovery and Graph Database integration system. It includes smoke testing, monitoring, and troubleshooting guidance.

## Table of Contents

- [Graph Smoke Tests](#graph-smoke-tests)
- [Monitoring](#monitoring)
- [Troubleshooting](#troubleshooting)
- [Emergency Procedures](#emergency-procedures)

---

## Graph Smoke Tests

### Purpose

The graph smoke test command (`graph:smoke-test`) verifies end-to-end integration between:
- PostgreSQL/SQLite database (court decisions)
- Neo4j graph database (decision metadata and relationships)
- OdlukeIngestService (decision ingestion pipeline)

### When to Run

Run smoke tests:
- ✅ After deploying graph-related code changes
- ✅ After Neo4j configuration changes
- ✅ After database migrations affecting court decisions
- ✅ Before major releases
- ✅ When investigating graph sync issues
- ✅ Automatically via GitHub Actions on PRs

### Command Usage

#### Basic Usage

```bash
# Run smoke test (leaves test data in place)
php artisan graph:smoke-test

# Run with automatic cleanup
php artisan graph:smoke-test --cleanup

# Run with detailed output
php artisan graph:smoke-test --cleanup --verbose
```

#### Options

| Option | Description |
|--------|-------------|
| `--cleanup` | Clean up test data after running (recommended) |
| `--verbose` | Show detailed output including node properties and queries |

### What It Verifies

The smoke test performs the following checks:

1. **Neo4j Connectivity** ✅
   - Verifies Neo4j is available and responding
   - Checks health status, version, and edition
   - Reports current node count

2. **Fixture Loading** ✅
   - Loads test decision metadata from `tests/Fixtures/odluke/*.json`
   - Validates JSON format
   - Reports number of fixtures loaded

3. **Database Row Creation** ✅
   - Creates CourtDecision rows in PostgreSQL/SQLite
   - Verifies ULID generation
   - Handles duplicate ECLI values gracefully

4. **Graph Node Storage** ✅
   - Stores decision metadata in Neo4j as `CourtDecisionDocument` nodes
   - Creates related `Court` nodes
   - Establishes `DECIDED_BY` relationships

5. **Graph Query Verification** ✅
   - Queries for test nodes by ECLI pattern
   - Verifies node properties (ECLI, case number, court)
   - Counts court relationships
   - Reports verification results

### Test Fixtures

Fixtures are stored in `tests/Fixtures/odluke/` as JSON files.

#### Example Fixture

```json
{
  "ecli": "HR:VSRH:2024:TEST001",
  "broj_odluke": "REV-123/2024",
  "sud": "Vrhovni sud Republike Hrvatske",
  "datum_odluke": "2024-01-15",
  "datum_objave": "2024-01-20",
  "pravomocnost": "Pravomoćna",
  "upisnik": "Kazneni",
  "vrsta_odluke": "Presuda",
  "src": "https://odluke.sudovi.hr/Document/View?id=test-001"
}
```

#### Required Fields

| Field | Description | Example |
|-------|-------------|---------|
| `ecli` | European Case Law Identifier | `HR:VSRH:2024:TEST001` |
| `broj_odluke` | Case number | `REV-123/2024` |
| `sud` | Court name | `Vrhovni sud Republike Hrvatske` |
| `datum_odluke` | Decision date | `2024-01-15` |
| `vrsta_odluke` | Decision type | `Presuda` |

### Expected Output

#### Successful Run

```
🔥 Starting Graph Database Smoke Tests

✓ Checking Neo4j availability
✅ Loaded 3 fixture(s)

📝 Creating database rows...
✅ Created 3 database row(s)

📊 Storing in Neo4j graph...
✅ Stored 3 node(s) in graph

🔍 Verifying graph data...
✅ Verified 3 node(s) with 3 court relationship(s)

🧹 Cleaning up test data...
✅ Cleanup complete

📊 Test Results Summary:
+-------------------------+-------+
| Metric                  | Count |
+-------------------------+-------+
| Database rows created   | 3     |
| Graph nodes created     | 3     |
| Nodes verified          | 3     |
| Court relationships     | 3     |
+-------------------------+-------+

✅ All smoke tests passed!
```

#### Failure Scenarios

**Neo4j Unavailable:**
```
❌ Neo4j is not available
```
**Action:** Check Neo4j service status and configuration.

**Fixture Load Failure:**
```
❌ No fixtures found in tests/Fixtures/odluke/
```
**Action:** Verify fixture files exist and contain valid JSON.

**Database Error:**
```
✗ Failed to create decision: REV-123/2024 - SQLSTATE[23000]: Integrity constraint violation
```
**Action:** Check database schema and constraints.

**Graph Sync Error:**
```
✗ Failed to store in graph: REV-123/2024 - Cannot connect to Neo4j
```
**Action:** Verify Neo4j connectivity and credentials.

---

## Monitoring

### Health Check Commands

#### Neo4j Health

```bash
# Check Neo4j service health
php artisan tinker
>>> app(App\Services\GraphDatabaseService::class)->getHealthStatus();

# Expected output:
[
  "available" => true,
  "healthy" => true,
  "uri" => "bolt://localhost:7687",
  "neo4j_version" => "5.15.0",
  "edition" => "community",
  "node_count" => 1234,
]
```

#### Graph Sync Status

```bash
# Check recent graph sync logs
tail -f storage/logs/laravel.log | grep "Graph sync"

# Expected patterns:
[info] Decision stored in graph successfully {"doc_id":"HR:VSRH:2024:123"...}
[warning] Graph sync failed for decision (vector ingestion succeeded) {...}
```

### Key Metrics

Monitor these metrics in production:

| Metric | Command | Good Threshold |
|--------|---------|----------------|
| Graph sync success rate | Check logs for `graph_synced` vs `graph_errors` | > 95% |
| Neo4j response time | `RETURN 1` query latency | < 100ms |
| Node count growth | `MATCH (n) RETURN count(n)` | Steady increase |
| Relationship count | `MATCH ()-[r]->() RETURN count(r)` | ~ 2x node count |

---

## Troubleshooting

### Common Issues

#### 1. "Neo4j is not available"

**Symptoms:**
```
❌ Neo4j is not available
```

**Diagnosis:**
```bash
# Check Neo4j service
sudo systemctl status neo4j

# Test connection
cypher-shell -u neo4j -p <password> "RETURN 1"

# Check config
php artisan config:clear
grep NEO4J .env
```

**Solutions:**
- Start Neo4j service: `sudo systemctl start neo4j`
- Verify credentials in `.env`
- Check firewall rules for port 7687
- Verify `NEO4J_ENABLED=true` in config

#### 2. "Cannot find driver" (PDO exception)

**Symptoms:**
```
SQLSTATE[HY000]: could not find driver
```

**Solution:**
```bash
# Install PostgreSQL PDO driver
sudo apt-get install php8.2-pgsql

# Restart PHP-FPM
sudo systemctl restart php8.2-fpm
```

#### 3. Graph Sync Silently Failing

**Symptoms:**
- No errors in logs
- `graph_synced = 0` but no `graph_errors`

**Diagnosis:**
```bash
# Check if sync is enabled
grep ODLUKE_SYNC_GRAPH .env
# Should show: ODLUKE_SYNC_GRAPH=true

# Check Neo4j availability
php artisan tinker
>>> app(App\Services\GraphDatabaseService::class)->isAvailable();
```

**Solution:**
- Set `ODLUKE_SYNC_GRAPH=true` in `.env`
- Verify Neo4j is running and accessible
- Check `NEO4J_ENABLED=true` in config

#### 4. Duplicate ECLI Errors

**Symptoms:**
```
Neo4j.ClientError.Schema.ConstraintValidationFailed
```

**This is expected!** The system uses `MERGE` to handle duplicates gracefully. If you see this in smoke tests, it means:
- Cleanup didn't run from previous test
- Manual intervention needed

**Solution:**
```bash
# Clean up test data manually
php artisan tinker
>>> app(App\Services\GraphDatabaseService::class)->run(
    'MATCH (d:CourtDecisionDocument) WHERE d.ecli CONTAINS "TEST" DETACH DELETE d'
);
```

---

## Emergency Procedures

### Clear All Graph Data (CAUTION!)

**⚠️ WARNING: This deletes ALL data in Neo4j. Use only in development/testing.**

```bash
php artisan tinker
>>> app(App\Services\GraphDatabaseService::class)->clearAll();
```

### Rebuild Graph from Database

```bash
# Re-sync all decisions from database to graph
php artisan neo4j:sync --batch-size=100

# Monitor progress
tail -f storage/logs/laravel.log | grep "Neo4j sync"
```

### Emergency Disable Graph Sync

```bash
# Disable graph sync immediately (no restart needed)
echo "ODLUKE_SYNC_GRAPH=false" >> .env
php artisan config:clear
```

---

## CI/CD Integration

### GitHub Actions Workflow

The smoke test runs automatically on PRs touching graph-related files:

- `app/Services/GraphDatabaseService.php`
- `app/Services/GraphQueryHelper.php`
- `app/Services/Odluke/OdlukeIngestService.php`
- `app/Console/Commands/GraphSmokeTestCommand.php`
- `tests/Fixtures/odluke/**`
- `config/neo4j.php`

**Workflow file:** `.github/workflows/graph-smoke.yml`

#### Manual Trigger

```bash
# Trigger manually via GitHub UI
# Repository → Actions → "Graph Database Smoke Tests" → Run workflow
```

#### Local Test (Mimics CI)

```bash
# Start Neo4j in Docker
docker run -d \
  --name neo4j-test \
  -p 7687:7687 -p 7474:7474 \
  -e NEO4J_AUTH=neo4j/testpassword \
  neo4j:5.15

# Configure .env for test
echo "NEO4J_URI=bolt://localhost:7687" >> .env
echo "NEO4J_USERNAME=neo4j" >> .env
echo "NEO4J_PASSWORD=testpassword" >> .env

# Run smoke test
php artisan graph:smoke-test --cleanup --verbose

# Stop container
docker stop neo4j-test && docker rm neo4j-test
```

---

## Additional Resources

- [Neo4j PHP Client Documentation](https://github.com/laudis-technologies/neo4j-php-client)
- [GraphDatabaseService.php](../app/Services/GraphDatabaseService.php) - Core graph service
- [GraphQueryHelper.php](../app/Services/GraphQueryHelper.php) - Query builder
- [OdlukeIngestService.php](../app/Services/Odluke/OdlukeIngestService.php) - Decision ingestion

---

## Change Log

| Date | Change | Author |
|------|--------|--------|
| 2025-11-01 | Initial ops runbook created | Sprint 1 Task 1.3 |


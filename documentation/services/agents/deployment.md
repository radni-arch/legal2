# Agent Deployment Playbook

**Version:** 1.0
**Last Updated:** 2025-01-29
**Environment:** Production, Staging, Development

## Table of Contents

1. [Environment Variables](#environment-variables)
2. [Cron Configuration](#cron-configuration)
3. [Queue Workers](#queue-workers)
4. [Laravel Horizon Setup](#laravel-horizon-setup)
5. [Rollback Procedures](#rollback-procedures)
6. [Graph Database Operations](#graph-database-operations)
7. [Monitoring & Alerts](#monitoring--alerts)
8. [Troubleshooting](#troubleshooting)

---

## Environment Variables

### Critical Environment Variables

These variables **MUST** be configured for the system to function properly.

#### Application Core
```bash
# Application environment
APP_ENV=production
APP_DEBUG=false
APP_KEY=<generate-with-php-artisan-key:generate>
APP_URL=https://your-domain.com

# Database connection (PostgreSQL required for pgvector)
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=legal_war_machine
DB_USERNAME=postgres
DB_PASSWORD=<secure-password>
```

#### OpenAI Configuration
```bash
# OpenAI API - Required for AI agent operations
OPENAI_API_KEY=sk-<your-api-key>
OPENAI_ORG=org-<your-org-id>                    # Optional
OPENAI_PROJECT=proj-<your-project-id>           # Optional

# Model configuration
OPENAI_RESPONSES_MODEL=gpt-4o-mini
OPENAI_CHAT_MODEL=gpt-4o-mini
OPENAI_EMBEDDINGS_MODEL=text-embedding-3-small

# API behavior
OPENAI_TIMEOUT=60                                # Request timeout (seconds)
OPENAI_CONNECT_TIMEOUT=10                        # Connection timeout (seconds)
OPENAI_RETRY_TIMES=2                             # Number of retries
OPENAI_RETRY_SLEEP_MS=200                        # Delay between retries (ms)
```

#### Neo4j Graph Database
```bash
# Master switch - Enable Neo4j integration
NEO4J_ENABLED=true

# Connection settings
NEO4J_CONNECTION=bolt
NEO4J_URI=bolt://localhost:7687
NEO4J_HOST=localhost
NEO4J_PORT=7687
NEO4J_HTTP_PORT=7474

# Authentication
NEO4J_USERNAME=neo4j
NEO4J_PASSWORD=<secure-password>
NEO4J_DATABASE=neo4j

# Sync behavior
NEO4J_AUTO_SYNC=true                             # Enable auto-sync from relational DB
NEO4J_AUTO_SYNC_ON_INGEST=true                   # Sync on document ingest
NEO4J_UPDATE_RELATIONSHIPS=true                  # Update existing relationships
NEO4J_SYNC_BATCH_SIZE=100                        # Records per batch
NEO4J_SIMILARITY_THRESHOLD=0.85                  # Similarity score threshold (0.70-1.0)
```

#### Court Decision Sync (Odluke)
```bash
# Odluke court decisions system
ODLUKE_BASE_URL=https://odluke.sudovi.hr
ODLUKE_TIMEOUT=30                                # Request timeout (seconds)
ODLUKE_RETRY=2                                   # Number of retries
ODLUKE_DELAY_MS=700                              # Delay between requests (ms)
ODLUKE_RPM=30                                    # Requests per minute limit
ODLUKE_BACKOFF_MS=800                            # Extra backoff on errors (ms)

# Enable graph sync during decision ingestion
ODLUKE_SYNC_GRAPH=true                           # CRITICAL: Auto-sync to Neo4j
```

#### Queue & Workers
```bash
# Queue driver (use 'redis' for production, 'database' for development)
QUEUE_CONNECTION=redis

# Redis configuration
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_DB=0
REDIS_CACHE_DB=1

# Queue settings
QUEUE_PREFIX=queues
QUEUE_FAILED_DRIVER=database-uuids
REDIS_QUEUE_CONNECTION=default
REDIS_QUEUE=default
REDIS_QUEUE_RETRY_AFTER=90
```

#### Agent Framework
```bash
# Agent execution limits
AGENT_MAX_ITERATIONS=10                          # Maximum iterations per run
AGENT_TIME_LIMIT=600                             # Time limit (seconds, 600 = 10 min)
AGENT_THRESHOLD=0.75                             # Quality threshold (0-1)
AGENT_MAX_COST=5.00                              # Maximum cost per run (USD)
AGENT_MAX_TIME=3600                              # Safety limit (seconds, 3600 = 1 hour)
AGENT_MAX_CONCURRENT=5                           # Maximum concurrent runs

# Scheduling
AGENT_SCHEDULE_CRON=weekly                       # Frequency: daily, weekly, monthly
AGENT_SCHEDULE_DAY=0                             # Day of week (0=Sunday)
AGENT_SCHEDULE_TIME=02:00                        # Time of day (24-hour format)

# Queue configuration
AGENT_QUEUE=agents                               # Queue name for agent jobs
AGENT_ASYNC_EXECUTION=false                      # Enable async via jobs

# Caching
AGENT_CACHE_ENABLED=true                         # Cache vector search results
AGENT_CACHE_TTL=3600                             # Cache TTL (seconds, 3600 = 1 hour)

# Logging
AGENT_LOG_LEVEL=info                             # Log level: debug, info, warning, error
AGENT_LOG_ITERATIONS=true                        # Log iteration details
AGENT_LOG_ACTIONS=true                           # Log action results
```

#### AWS Services (Textract)
```bash
# AWS credentials
AWS_ACCESS_KEY_ID=<your-access-key>
AWS_SECRET_ACCESS_KEY=<your-secret-key>
AWS_DEFAULT_REGION=us-east-1

# S3 bucket
AWS_BUCKET=<your-bucket-name>

# S3 prefixes for organized storage
S3_INPUT_PREFIX=textract/input
S3_OUTPUT_PREFIX=textract/output
S3_JSON_PREFIX=textract/json
S3_TABLES_PREFIX=textract/tables
```

#### Slack Notifications (Optional)
```bash
# Slack integration for Neo4j alerts
SLACK_BOT_USER_OAUTH_TOKEN=xoxb-<your-token>
SLACK_BOT_USER_DEFAULT_CHANNEL=#alerts
SLACK_NEO4J_WEBHOOK_URL=https://hooks.slack.com/services/<your-webhook>
```

### Environment-Specific Configuration

#### Production
```bash
APP_ENV=production
APP_DEBUG=false
LOG_LEVEL=warning
QUEUE_CONNECTION=redis
NEO4J_ENABLED=true
ODLUKE_SYNC_GRAPH=true
AGENT_ASYNC_EXECUTION=true
```

#### Staging
```bash
APP_ENV=staging
APP_DEBUG=false
LOG_LEVEL=info
QUEUE_CONNECTION=redis
NEO4J_ENABLED=true
ODLUKE_SYNC_GRAPH=true
AGENT_ASYNC_EXECUTION=true
```

#### Development
```bash
APP_ENV=local
APP_DEBUG=true
LOG_LEVEL=debug
QUEUE_CONNECTION=database
NEO4J_ENABLED=false
ODLUKE_SYNC_GRAPH=false
AGENT_ASYNC_EXECUTION=false
```

---

## Cron Configuration

### Production Crontab

Add the following to your server's crontab (`crontab -e`):

```cron
# Laravel Scheduler - Runs every minute to check scheduled tasks
* * * * * cd /path/to/application && php artisan schedule:run >> /dev/null 2>&1
```

### Scheduled Tasks Overview

The Laravel scheduler (`app/Console/Kernel.php`) manages these automated tasks:

| Task | Schedule | Command | Description |
|------|----------|---------|-------------|
| **e-Oglasna Keywords Watch** | Every 5 minutes | `eoglasna:watch-keywords` | Monitor keyword-based court notices |
| **e-Oglasna Osijek Watch** | Hourly | `eoglasna:watch-osijek` | Monitor Osijek court system |
| **Agent Research** | Weekly (Sun 02:00) | `agent:research-scheduled --max-iterations=15 --time-limit=1800` | Autonomous research on legal topics |
| **Decision Discovery (Async)** | Daily (02:00) | `decisions:discover-async` | Discover new court decisions via queue |
| **Comprehensive Discovery** | Weekly (Sun 03:00) | `decisions:discover --topics=10 --threshold=60` | Full discovery sweep |
| **Scheduled Discovery** | Daily (03:00) | `decisions:discovery --queue` | Queue-based discovery with retries |
| **Graph Metrics Analysis** | Weekly (Sun 04:00) | `graph:analyze-metrics` | Analyze PageRank, clusters, network stats |

### Cron Task Details

#### 1. e-Oglasna Monitoring
```bash
# Command: eoglasna:watch-keywords
# Frequency: Every 5 minutes
# Purpose: Monitor court notice keywords
# Overlapping: Prevented
# Location: app/Console/Commands/EoglasnaWatchKeywords.php

# Manual execution:
php artisan eoglasna:watch-keywords
```

#### 2. Autonomous Agent Research
```bash
# Command: agent:research-scheduled
# Frequency: Weekly on Sunday at 02:00
# Purpose: Autonomous legal research on specified topics
# Overlapping: Prevented
# Background: Yes
# Location: app/Console/Commands/AgentResearchScheduled.php

# Manual execution:
php artisan agent:research-scheduled --max-iterations=15 --time-limit=1800
```

#### 3. Decision Discovery (Async)
```bash
# Command: decisions:discover-async
# Frequency: Daily at 02:00
# Purpose: Discover new court decisions via queue job
# Overlapping: Prevented (60-minute window)
# Multi-server: Only runs on one server (onOneServer)
# Location: app/Console/Commands/DiscoverDecisionsAsyncCommand.php

# Manual execution:
php artisan decisions:discover-async
```

#### 4. Scheduled Decision Discovery
```bash
# Command: decisions:discovery
# Frequency: Daily at 03:00
# Purpose: Queue-based discovery with retry mechanism
# Overlapping: Prevented (120-minute window)
# Multi-server: Only runs on one server (onOneServer)
# Location: app/Console/Commands/DecisionDiscoveryCommand.php

# Manual execution:
php artisan decisions:discovery --queue
```

#### 5. Graph Metrics Analysis
```bash
# Command: graph:analyze-metrics
# Frequency: Weekly on Sunday at 04:00
# Purpose: Analyze PageRank, Louvain clusters, network statistics
# Overlapping: Prevented (60-minute window)
# Multi-server: Only runs on one server (onOneServer)
# Location: app/Console/Commands/AnalyzeGraphMetricsCommand.php

# Manual execution:
php artisan graph:analyze-metrics

# Run specific metric type:
php artisan graph:analyze-metrics --type=pagerank
php artisan graph:analyze-metrics --type=clusters
php artisan graph:analyze-metrics --type=network_stats
php artisan graph:analyze-metrics --type=citation_analysis
```

### Cron Monitoring

```bash
# Check if cron is running
systemctl status cron

# View cron logs
grep CRON /var/log/syslog | tail -n 50

# Check Laravel scheduler logs
tail -f storage/logs/laravel.log | grep "Running scheduled command"
```

---

## Queue Workers

### Worker Configuration

#### Database Queue Driver (Development)
```bash
# Start a single worker
php artisan queue:work database --queue=default --sleep=3 --tries=3

# With supervisor (recommended)
[program:laravel-worker-db]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/application/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/application/storage/logs/worker.log
stopwaitsecs=3600
```

#### Redis Queue Driver (Production)
```bash
# Start multiple workers for different queues
php artisan queue:work redis --queue=high,default,low --sleep=3 --tries=3 --timeout=90

# With supervisor (recommended)
[program:laravel-worker-redis]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/application/artisan queue:work redis --queue=high,default,low --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/path/to/application/storage/logs/worker.log
stopwaitsecs=3600
```

### Queue Priorities

| Queue Name | Priority | Purpose | Workers |
|-----------|----------|---------|---------|
| `high` | 1 | Time-sensitive operations | 2 |
| `default` | 2 | Standard background jobs | 4 |
| `agents` | 2 | Agent execution tasks | 2 |
| `low` | 3 | Non-critical tasks | 1 |

### Worker Commands

```bash
# Start worker
php artisan queue:work redis --queue=high,default,low

# Start worker with specific options
php artisan queue:work redis \
    --queue=high,default,low \
    --sleep=3 \
    --tries=3 \
    --max-time=3600 \
    --timeout=90

# Listen for jobs (auto-reloads on code changes)
php artisan queue:listen redis --queue=default

# Process a single job
php artisan queue:work --once

# Restart all workers gracefully
php artisan queue:restart

# Check failed jobs
php artisan queue:failed

# Retry a failed job
php artisan queue:retry <job-id>

# Retry all failed jobs
php artisan queue:retry all

# Clear failed jobs
php artisan queue:flush

# Forget a failed job
php artisan queue:forget <job-id>
```

### Supervisor Configuration

Install Supervisor:
```bash
sudo apt-get install supervisor
```

Create supervisor config:
```bash
sudo nano /etc/supervisor/conf.d/laravel-worker.conf
```

Configuration:
```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/artisan queue:work redis --queue=high,default,agents,low --sleep=3 --tries=3 --max-time=3600 --timeout=90
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/www/html/storage/logs/worker.log
stopwaitsecs=3600
```

Supervisor commands:
```bash
# Reload configuration
sudo supervisorctl reread
sudo supervisorctl update

# Start workers
sudo supervisorctl start laravel-worker:*

# Stop workers
sudo supervisorctl stop laravel-worker:*

# Restart workers
sudo supervisorctl restart laravel-worker:*

# Check worker status
sudo supervisorctl status

# View worker logs
sudo tail -f /var/www/html/storage/logs/worker.log
```

---

## Laravel Horizon Setup

Laravel Horizon provides a beautiful dashboard and configuration system for Redis queues.

### Installation

```bash
# Install Horizon
composer require laravel/horizon

# Publish Horizon assets
php artisan horizon:install

# Publish configuration
php artisan vendor:publish --provider="Laravel\Horizon\HorizonServiceProvider"
```

### Configuration

Edit `config/horizon.php`:

```php
<?php

return [
    'domain' => env('HORIZON_DOMAIN', null),
    'path' => env('HORIZON_PATH', 'horizon'),

    'use' => 'default',

    'prefix' => env('HORIZON_PREFIX', 'horizon:'),

    'middleware' => ['web'],

    'waits' => [
        'redis:default' => 60,
    ],

    'trim' => [
        'recent' => 60,
        'pending' => 60,
        'completed' => 60,
        'failed' => 10080, // 7 days
        'monitored' => 10080,
    ],

    'fast_termination' => false,

    'memory_limit' => 64,

    'defaults' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue' => ['default'],
            'balance' => 'auto',
            'minProcesses' => 1,
            'maxProcesses' => 5,
            'balanceMaxShift' => 1,
            'balanceCooldown' => 3,
            'tries' => 3,
            'nice' => 0,
            'timeout' => 90,
        ],
    ],

    'environments' => [
        'production' => [
            'supervisor-high' => [
                'connection' => 'redis',
                'queue' => ['high'],
                'balance' => 'auto',
                'minProcesses' => 2,
                'maxProcesses' => 5,
                'tries' => 3,
                'timeout' => 90,
            ],
            'supervisor-default' => [
                'connection' => 'redis',
                'queue' => ['default'],
                'balance' => 'auto',
                'minProcesses' => 4,
                'maxProcesses' => 10,
                'tries' => 3,
                'timeout' => 90,
            ],
            'supervisor-agents' => [
                'connection' => 'redis',
                'queue' => ['agents'],
                'balance' => 'auto',
                'minProcesses' => 2,
                'maxProcesses' => 5,
                'tries' => 3,
                'timeout' => 600,
            ],
            'supervisor-low' => [
                'connection' => 'redis',
                'queue' => ['low'],
                'balance' => 'auto',
                'minProcesses' => 1,
                'maxProcesses' => 3,
                'tries' => 3,
                'timeout' => 120,
            ],
        ],

        'local' => [
            'supervisor-1' => [
                'connection' => 'redis',
                'queue' => ['default'],
                'balance' => 'auto',
                'minProcesses' => 1,
                'maxProcesses' => 3,
                'tries' => 3,
                'timeout' => 90,
            ],
        ],
    ],
];
```

### Running Horizon

```bash
# Start Horizon
php artisan horizon

# Terminate Horizon gracefully
php artisan horizon:terminate

# Pause Horizon
php artisan horizon:pause

# Continue Horizon
php artisan horizon:continue

# Check Horizon status
php artisan horizon:status
```

### Horizon with Supervisor

Create `/etc/supervisor/conf.d/horizon.conf`:

```ini
[program:horizon]
process_name=%(program_name)s
command=php /var/www/html/artisan horizon
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/html/storage/logs/horizon.log
stopwaitsecs=3600
```

Manage Horizon:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start horizon
sudo supervisorctl status horizon
```

### Accessing Horizon Dashboard

Navigate to: `https://your-domain.com/horizon`

**Securing Horizon in Production:**

Edit `app/Providers/HorizonServiceProvider.php`:

```php
protected function gate()
{
    Gate::define('viewHorizon', function ($user) {
        return in_array($user->email, [
            'admin@example.com',
        ]);
    });
}
```

---

## Rollback Procedures

### Application Rollback

#### Using Git Deployment

```bash
# 1. SSH to server
ssh user@server

# 2. Navigate to application
cd /var/www/html

# 3. View deployment history
git log --oneline -10

# 4. Identify commit to rollback to
git show <commit-hash>

# 5. Create backup of current state
php artisan backup:run --only-db

# 6. Rollback to previous commit
git reset --hard <previous-commit-hash>

# 7. Install dependencies
composer install --no-dev --optimize-autoloader

# 8. Clear caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# 9. Run migrations (if needed)
php artisan migrate:status
php artisan migrate:rollback --step=1

# 10. Restart services
sudo supervisorctl restart all
php artisan horizon:terminate
php artisan queue:restart

# 11. Verify deployment
php artisan about
curl -I https://your-domain.com
```

#### Using Symlink Deployment (Envoyer/Deployer)

```bash
# 1. SSH to server
ssh user@server

# 2. Navigate to releases directory
cd /var/www/html/releases

# 3. List available releases
ls -lt

# 4. Update symlink to previous release
ln -nfs /var/www/html/releases/<previous-release> /var/www/html/current

# 5. Restart services
sudo supervisorctl restart all
php artisan horizon:terminate
```

### Database Rollback

```bash
# Check migration status
php artisan migrate:status

# Rollback last migration
php artisan migrate:rollback

# Rollback specific number of migrations
php artisan migrate:rollback --step=2

# Rollback all migrations
php artisan migrate:reset

# Rollback and re-run migrations
php artisan migrate:refresh

# Restore from backup
php artisan backup:restore <backup-file>
```

### Configuration Rollback

```bash
# Restore previous .env
cp .env.backup .env

# Clear configuration cache
php artisan config:clear
php artisan config:cache

# Verify configuration
php artisan config:show
```

### Neo4j Graph Rollback

```bash
# 1. Stop Neo4j
sudo systemctl stop neo4j

# 2. Restore from snapshot (see Graph Snapshots section)
neo4j-admin restore --from=/path/to/backup

# 3. Start Neo4j
sudo systemctl start neo4j

# 4. Verify restoration
cypher-shell -u neo4j -p <password>
> MATCH (n) RETURN count(n);
```

---

## Graph Database Operations

### Neo4j Health Checks

```bash
# Manual health check
php artisan neo4j:health

# Check cached status
php artisan tinker
>>> cache('neo4j:status')

# Verify connectivity
cypher-shell -u neo4j -p <password>
```

### Graph Initialization

```bash
# Initialize schema (constraints and indexes)
php artisan graph:init

# Verify constraints
cypher-shell -u neo4j -p <password>
> SHOW CONSTRAINTS;

# Verify indexes
> SHOW INDEXES;
```

### Graph Sync Operations

```bash
# Sync all data to graph
php artisan graph:sync all

# Sync specific model
php artisan graph:sync law
php artisan graph:sync case
php artisan graph:sync decision

# Sync with batch size
php artisan graph:sync all --batch=50

# Dry run (no changes)
php artisan graph:sync all --dry-run
```

### Graph Metrics

```bash
# Run all metrics analysis
php artisan graph:analyze-metrics

# Run specific metric
php artisan graph:analyze-metrics --type=pagerank
php artisan graph:analyze-metrics --type=clusters
php artisan graph:analyze-metrics --type=network_stats

# View metrics in database
php artisan tinker
>>> \App\Models\GraphMetric::latest()->get()
```

### Graph Snapshots

#### Creating Backups

```bash
# Using neo4j-admin (offline backup)
sudo systemctl stop neo4j
sudo neo4j-admin dump \
    --database=neo4j \
    --to=/backups/neo4j-$(date +%Y%m%d-%H%M%S).dump
sudo systemctl start neo4j

# Using APOC (online backup)
cypher-shell -u neo4j -p <password>
> CALL apoc.export.graphml.all('/backups/graph-' + date(), {
    useTypes: true,
    storeNodeIds: true
  });
```

#### Restoring from Snapshots

```bash
# Stop Neo4j
sudo systemctl stop neo4j

# Restore from dump
sudo neo4j-admin load \
    --from=/backups/neo4j-20250129-040000.dump \
    --database=neo4j \
    --force

# Start Neo4j
sudo systemctl start neo4j

# Verify restoration
cypher-shell -u neo4j -p <password>
> MATCH (n) RETURN labels(n), count(n);
```

#### Automated Backup Script

Create `/usr/local/bin/neo4j-backup.sh`:

```bash
#!/bin/bash

BACKUP_DIR="/backups/neo4j"
TIMESTAMP=$(date +%Y%m%d-%H%M%S)
RETENTION_DAYS=7

# Create backup directory
mkdir -p $BACKUP_DIR

# Create backup
/usr/bin/neo4j-admin dump \
    --database=neo4j \
    --to=$BACKUP_DIR/neo4j-$TIMESTAMP.dump

# Compress backup
gzip $BACKUP_DIR/neo4j-$TIMESTAMP.dump

# Remove old backups
find $BACKUP_DIR -name "neo4j-*.dump.gz" -mtime +$RETENTION_DAYS -delete

# Log completion
echo "$(date): Neo4j backup completed: neo4j-$TIMESTAMP.dump.gz" >> /var/log/neo4j-backup.log
```

Make executable:
```bash
chmod +x /usr/local/bin/neo4j-backup.sh
```

Add to crontab (daily at 1 AM):
```cron
0 1 * * * /usr/local/bin/neo4j-backup.sh
```

---

## Monitoring & Alerts

### Application Monitoring

```bash
# Check application status
php artisan about

# Check queue status
php artisan queue:monitor redis:default,redis:agents --max=100

# Check failed jobs
php artisan queue:failed

# Check Horizon dashboard
# Visit: https://your-domain.com/horizon
```

### Neo4j Monitoring

```bash
# Check Neo4j status
systemctl status neo4j

# Check Neo4j metrics
cypher-shell -u neo4j -p <password>
> CALL dbms.components();
> CALL db.stats.retrieve('GRAPH COUNTS');

# Check cached health status
php artisan tinker
>>> $status = cache('neo4j:status');
>>> print_r($status);
```

### Slack Alerts

Neo4j downtime alerts are automatically sent to Slack when configured:

```bash
# Configure in .env
SLACK_NEO4J_WEBHOOK_URL=https://hooks.slack.com/services/YOUR/WEBHOOK/URL
# OR
SLACK_BOT_USER_OAUTH_TOKEN=xoxb-your-token
SLACK_BOT_USER_DEFAULT_CHANNEL=#alerts
```

### Log Monitoring

```bash
# Application logs
tail -f storage/logs/laravel.log

# Worker logs
tail -f storage/logs/worker.log

# Horizon logs
tail -f storage/logs/horizon.log

# Neo4j logs
tail -f /var/log/neo4j/neo4j.log

# Nginx/Apache logs
tail -f /var/log/nginx/error.log
```

---

## Troubleshooting

### Queue Workers Not Processing Jobs

```bash
# Check if workers are running
ps aux | grep "queue:work"

# Restart workers
php artisan queue:restart

# Check supervisor status
sudo supervisorctl status

# View worker logs
tail -f storage/logs/worker.log

# Check Redis connection
redis-cli ping
```

### Neo4j Connection Issues

```bash
# Check Neo4j status
systemctl status neo4j

# Check Neo4j logs
tail -f /var/log/neo4j/neo4j.log

# Test connection
cypher-shell -u neo4j -p <password>

# Check cached health status
php artisan tinker
>>> cache('neo4j:status')

# Clear health cache
>>> cache()->forget('neo4j:status')
```

### Scheduled Tasks Not Running

```bash
# Check if cron is running
systemctl status cron

# Check cron logs
grep CRON /var/log/syslog | tail -n 20

# Manually run scheduler
php artisan schedule:run

# Test specific command
php artisan agent:research-scheduled --dry-run
```

### High Memory Usage

```bash
# Check worker memory
ps aux | grep "queue:work" | awk '{print $6}'

# Restart workers
php artisan queue:restart
sudo supervisorctl restart all

# Check Horizon memory limit
grep memory_limit config/horizon.php
```

### Failed Jobs Accumulating

```bash
# Check failed jobs
php artisan queue:failed

# Retry specific job
php artisan queue:retry <job-id>

# Retry all failed jobs
php artisan queue:retry all

# Clear failed jobs
php artisan queue:flush
```

---

## Security Checklist

- [ ] All passwords changed from defaults
- [ ] `APP_DEBUG=false` in production
- [ ] SSL/TLS certificates installed
- [ ] Horizon dashboard protected with authentication
- [ ] Neo4j authentication configured
- [ ] API tokens rotated regularly
- [ ] Firewall rules configured
- [ ] Log rotation configured
- [ ] Backups automated and tested
- [ ] Monitoring and alerts configured

---

## Quick Reference

### Essential Commands

| Task | Command |
|------|---------|
| Start Horizon | `php artisan horizon` |
| Restart workers | `php artisan queue:restart` |
| Check Neo4j health | `php artisan neo4j:health` |
| Run metrics analysis | `php artisan graph:analyze-metrics` |
| View failed jobs | `php artisan queue:failed` |
| Retry failed jobs | `php artisan queue:retry all` |
| Clear caches | `php artisan optimize:clear` |
| Run scheduler manually | `php artisan schedule:run` |

### Important URLs

- Application: `https://your-domain.com`
- Horizon Dashboard: `https://your-domain.com/horizon`
- Neo4j Browser: `http://localhost:7474`

### Support Contacts

- Technical Lead: [email]
- DevOps: [email]
- On-Call: [phone]

---

**Document Revision History:**

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | 2025-01-29 | System | Initial deployment playbook |

**End of Deployment Playbook**

# Operations Manual

## AI Legal War Machine - Production Operations Guide

**Version**: 1.0
**Last Updated**: 2025-11-09
**Target Audience**: DevOps, System Administrators, Support Team

---

## Table of Contents

1. [Daily Operations](#1-daily-operations)
2. [Weekly Operations](#2-weekly-operations)
3. [Monthly Operations](#3-monthly-operations)
4. [Monitoring & Alerting](#4-monitoring--alerting)
5. [Common Tasks](#5-common-tasks)
6. [Incident Response](#6-incident-response)
7. [Performance Optimization](#7-performance-optimization)
8. [Data Management](#8-data-management)
9. [User Management](#9-user-management)
10. [System Maintenance](#10-system-maintenance)

---

## 1. Daily Operations

### 1.1 Morning Health Check (5 minutes)

**Execute**: 8:00 AM local time

```bash
# SSH into production server
ssh deploy@your-server-ip

# Navigate to app directory
cd /var/www/ai-legal-war-machine

# Check application health
curl http://localhost/api/health | jq

# Expected output:
# {
#   "status": "healthy",
#   "services": {
#     "database": { "status": "healthy" },
#     "redis": { "status": "healthy" },
#     "neo4j": { "status": "healthy" },
#     "openai": { "status": "healthy" },
#     "aws": { "status": "healthy" },
#     "queue": { "status": "healthy" }
#   }
# }
```

**If any service is unhealthy**:
1. Check service status: `systemctl status <service>`
2. Review logs: See [Section 4.3](#43-log-analysis)
3. Restart if needed: See [Section 5.4](#54-restart-services)
4. Escalate if restart doesn't resolve

### 1.2 Review Error Logs (10 minutes)

```bash
# Analyze logs for errors in last 24 hours
php artisan logs:analyze --errors --since="1 day ago"

# Check error rate
php artisan logs:analyze --stats

# Expected: Error rate < 1%
# Warning: Error rate 1-5%
# Critical: Error rate > 5%
```

**If error rate > 1%**:
1. Identify most common errors
2. Check if errors are related (same root cause)
3. Review recent deployments/changes
4. Apply fixes or rollback if needed

### 1.3 Check Queue Status (5 minutes)

```bash
# Check queue workers
sudo supervisorctl status ai-legal-queue-worker:*

# All workers should show RUNNING

# Check queue sizes
curl http://localhost/api/health | jq '.services.queue.queue_sizes'

# Expected: All queues < 100 pending jobs
```

**If queue sizes growing**:
1. Check for stuck jobs: `php artisan queue:failed`
2. Increase worker count temporarily: See [Section 5.7](#57-scale-queue-workers)
3. Investigate slow jobs in logs: `tail -f storage/logs/queue.log`

### 1.4 Monitor Disk Space (2 minutes)

```bash
# Check disk usage
df -h

# Expected: All partitions < 80% used
```

**If disk usage > 80%**:
1. Find largest directories: `du -sh /var/www/ai-legal-war-machine/* | sort -rh | head -10`
2. Force log rotation: `sudo logrotate -f /etc/logrotate.d/ai-legal-war-machine`
3. Clear old backups: See [Section 8.4](#84-cleanup-old-backups)

### 1.5 Review Failed Jobs (5 minutes)

```bash
# Check for failed queue jobs
php artisan queue:failed

# If jobs failed, investigate why
php artisan queue:failed --json | jq

# Retry specific job if safe
php artisan queue:retry <job-id>

# Or retry all failed jobs
php artisan queue:retry all

# Or clear all failed jobs (use with caution)
# php artisan queue:flush
```

### 1.6 Check System Resources (5 minutes)

```bash
# CPU and memory usage
htop

# Expected:
# - CPU usage < 70% average
# - Memory usage < 80%
# - Load average < number of cores
```

**If resources high**:
1. Identify top processes: `ps aux --sort=-%cpu | head -20`
2. Check for runaway processes
3. Review queue worker count
4. Consider scaling: See [Section 7](#7-performance-optimization)

---

## 2. Weekly Operations

### 2.1 Performance Analysis (20 minutes)

**Execute**: Monday 9:00 AM

```bash
# Analyze performance logs
php artisan logs:analyze --channel=performance --stats

# Review slow requests (> 1 second)
grep "Slow request" storage/logs/performance.log | tail -50

# Group by endpoint
grep "Slow request" storage/logs/performance.log | grep -o '"url":"[^"]*"' | sort | uniq -c | sort -rn

# Expected: < 5% of requests slow
```

**If slow requests > 5%**:
1. Identify most common slow endpoints
2. Check database query performance: See [Section 7.2](#72-database-optimization)
3. Consider caching improvements
4. Review Neo4j query performance

### 2.2 Security Log Review (15 minutes)

```bash
# Review security events
php artisan logs:analyze --channel=security --since="1 week ago"

# Check for suspicious activity:
# - Multiple failed login attempts
# - Unusual API access patterns
# - Authentication errors
grep -i "failed\|denied\|unauthorized" storage/logs/security.log | tail -100
```

**If suspicious activity detected**:
1. Identify source IPs
2. Block malicious IPs: `sudo ufw deny from <ip>`
3. Review Fail2Ban status: `sudo fail2ban-client status`
4. Consider additional security measures

### 2.3 Backup Verification (10 minutes)

```bash
# List recent backups
ls -lh /var/backups/ai-legal-war-machine/ | tail -20

# Verify backup sizes (should be consistent)
du -sh /var/backups/ai-legal-war-machine/db_*.sql.gz | tail -7

# Test restore to temporary database (monthly, not weekly)
# See Section 8.3
```

**If backups missing or inconsistent**:
1. Check cron logs: `grep CRON /var/log/syslog | grep backup`
2. Manually run backup: `/var/www/ai-legal-war-machine/scripts/backup-database.sh`
3. Review backup scripts for errors

### 2.4 Queue Performance Review (10 minutes)

```bash
# Analyze queue logs
php artisan logs:analyze --channel=queue --since="1 week ago"

# Calculate average job duration
grep "Processing:" storage/logs/queue.log | tail -1000 | wc -l

# Check for timeout errors
grep -i "timeout\|exceeded" storage/logs/queue.log | tail -50
```

**If jobs timing out frequently**:
1. Increase timeout in Supervisor config
2. Optimize slow jobs
3. Split long jobs into smaller batches

### 2.5 SSL Certificate Check (5 minutes)

```bash
# Check certificate expiry
sudo certbot certificates

# Expected: Valid for > 30 days
# Certbot auto-renews at < 30 days
```

**If expiring soon (< 30 days)**:
1. Test renewal: `sudo certbot renew --dry-run`
2. Force renewal: `sudo certbot renew`
3. Verify: `sudo certbot certificates`

### 2.6 Dependency Updates Review (15 minutes)

```bash
# Check for outdated Composer packages
cd /var/www/ai-legal-war-machine
composer outdated --direct

# Check for security vulnerabilities
composer audit

# Check for NPM updates
npm outdated
```

**If critical security updates available**:
1. Test updates in staging first
2. Schedule maintenance window
3. Apply updates: See [Section 10.4](#104-update-dependencies)

---

## 3. Monthly Operations

### 3.1 Database Maintenance (30 minutes)

**Execute**: First Sunday of month, 2:00 AM

```bash
# Analyze and vacuum database
sudo -u postgres vacuumdb --analyze --verbose ai_legal_war_machine

# Check database size
sudo -u postgres psql -c "SELECT pg_size_pretty(pg_database_size('ai_legal_war_machine'));"

# Check table sizes
sudo -u postgres psql ai_legal_war_machine -c "
SELECT
  schemaname || '.' || tablename AS table_name,
  pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename)) AS size
FROM pg_tables
WHERE schemaname NOT IN ('pg_catalog', 'information_schema')
ORDER BY pg_total_relation_size(schemaname||'.'||tablename) DESC
LIMIT 20;
"
```

### 3.2 Neo4j Maintenance (20 minutes)

```bash
# Check Neo4j store statistics
cypher-shell -u neo4j -p "$NEO4J_PASSWORD" "CALL db.stats.retrieve('GRAPH COUNTS');"

# Check index usage
cypher-shell -u neo4j -p "$NEO4J_PASSWORD" "SHOW INDEXES;"

# Compact database (stops Neo4j briefly)
sudo systemctl stop neo4j
sudo neo4j-admin database compact neo4j
sudo systemctl start neo4j

# Wait for startup
sleep 30

# Verify
cypher-shell -u neo4j -p "$NEO4J_PASSWORD" "RETURN 'Neo4j running' AS status;"
```

### 3.3 Log Retention Review (15 minutes)

```bash
# Check log directory size
du -sh /var/www/ai-legal-war-machine/storage/logs/

# List largest log files
du -sh /var/www/ai-legal-war-machine/storage/logs/*.log | sort -rh | head -10

# Verify logrotate is working
ls -lh /var/www/ai-legal-war-machine/storage/logs/*.log.gz | tail -20

# Adjust retention if needed (edit logrotate config)
# sudo nano /etc/logrotate.d/ai-legal-war-machine
```

### 3.4 External Monitoring Review (10 minutes)

```bash
# Review UptimeRobot/Pingdom dashboard
# - Check uptime percentage (target: > 99.9%)
# - Review downtime incidents
# - Verify alert contacts are current
# - Test alert notifications

# Review Slack notification history
# - Verify critical alerts were received
# - Adjust alert thresholds if needed
```

### 3.5 Security Audit (30 minutes)

```bash
# Check for security updates
sudo apt update
sudo apt list --upgradable | grep -i security

# Review user accounts
cat /etc/passwd | grep -E "deploy|www-data"

# Review SSH configuration
sudo cat /etc/ssh/sshd_config | grep -E "PermitRootLogin|PasswordAuthentication"

# Review firewall rules
sudo ufw status verbose

# Review Fail2Ban bans
sudo fail2ban-client status sshd

# Check for unusual processes
ps aux | grep -v "^root\|^deploy\|^www-data\|^postgres\|^redis\|^neo4j"
```

### 3.6 Backup Restore Test (45 minutes)

**Critical**: Test database restore monthly to verify backups work

```bash
# Get most recent backup
BACKUP_FILE=$(ls -t /var/backups/ai-legal-war-machine/db_*.sql.gz | head -1)
echo "Testing restore of: $BACKUP_FILE"

# Create test database
sudo -u postgres psql -c "DROP DATABASE IF EXISTS ai_legal_test_restore;"
sudo -u postgres psql -c "CREATE DATABASE ai_legal_test_restore OWNER ai_legal_user;"

# Restore backup
gunzip -c "$BACKUP_FILE" | sudo -u postgres psql ai_legal_test_restore

# Verify table count
TABLE_COUNT=$(sudo -u postgres psql -t ai_legal_test_restore -c "SELECT count(*) FROM information_schema.tables WHERE table_schema = 'public';")
echo "Restored $TABLE_COUNT tables"

# Verify record count (example)
sudo -u postgres psql ai_legal_test_restore -c "SELECT count(*) FROM users;"

# Cleanup
sudo -u postgres psql -c "DROP DATABASE ai_legal_test_restore;"

echo "✓ Backup restore test successful"
```

---

## 4. Monitoring & Alerting

### 4.1 Health Endpoint Monitoring

**External Monitoring** (UptimeRobot/Pingdom):

- **URL**: `https://your-domain.com/api/health`
- **Interval**: 5 minutes
- **Alert When**: HTTP status != 200 OR "status" != "healthy"
- **Alert Contacts**: DevOps team email, Slack channel

**Manual Check**:

```bash
# Quick health check
curl -s https://your-domain.com/api/health | jq '.status'

# Detailed health check
curl -s https://your-domain.com/api/health | jq

# Check specific service
curl -s https://your-domain.com/api/health | jq '.services.database'
```

### 4.2 Service-Specific Monitoring

**PostgreSQL**:

```bash
# Connection count
sudo -u postgres psql -c "SELECT count(*) FROM pg_stat_activity;"

# Long-running queries
sudo -u postgres psql -c "
SELECT pid, now() - pg_stat_activity.query_start AS duration, query
FROM pg_stat_activity
WHERE state = 'active' AND now() - pg_stat_activity.query_start > interval '5 minutes'
ORDER BY duration DESC;
"

# Database size
sudo -u postgres psql -c "SELECT pg_size_pretty(pg_database_size('ai_legal_war_machine'));"
```

**Redis**:

```bash
# Memory usage
redis-cli INFO memory | grep used_memory_human

# Key count
redis-cli DBSIZE

# Connected clients
redis-cli INFO clients | grep connected_clients

# Hit rate
redis-cli INFO stats | grep -E "keyspace_hits|keyspace_misses"
```

**Neo4j**:

```bash
# Check status
systemctl status neo4j

# Connection test
cypher-shell -u neo4j -p "$NEO4J_PASSWORD" "RETURN 'connected' AS status;"

# Node/relationship count
cypher-shell -u neo4j -p "$NEO4J_PASSWORD" "MATCH (n) RETURN count(n) AS nodes;"
cypher-shell -u neo4j -p "$NEO4J_PASSWORD" "MATCH ()-[r]->() RETURN count(r) AS relationships;"
```

**Queue Workers**:

```bash
# Worker status
sudo supervisorctl status ai-legal-queue-worker:*

# Job counts
curl -s http://localhost/api/health | jq '.services.queue'

# Failed jobs
php artisan queue:failed | wc -l
```

### 4.3 Log Analysis

**Error Logs**:

```bash
# Recent errors
php artisan logs:analyze --errors --tail=50

# Error statistics
php artisan logs:analyze --stats

# Specific error search
grep -i "database connection" storage/logs/laravel.log | tail -20
```

**Performance Logs**:

```bash
# Slow requests today
php artisan logs:analyze --channel=performance --since="today"

# Slowest endpoints
grep "Slow request" storage/logs/performance.log | grep -o '"url":"[^"]*"' | sort | uniq -c | sort -rn | head -10
```

**Security Logs**:

```bash
# Recent security events
php artisan logs:analyze --channel=security --tail=100

# Failed authentication
grep -i "authentication failed" storage/logs/security.log | tail -20
```

### 4.4 Alert Thresholds

Configure alerts for these conditions:

| Metric | Warning | Critical |
|--------|---------|----------|
| Error Rate | > 1% | > 5% |
| Response Time | > 1000ms | > 3000ms |
| Queue Size | > 100 jobs | > 500 jobs |
| Failed Jobs | > 10 | > 50 |
| Disk Usage | > 80% | > 90% |
| Memory Usage | > 80% | > 90% |
| CPU Usage | > 70% | > 90% |
| Database Connections | > 50 | > 80 |

### 4.5 Slack Notifications

Configure critical alerts to post to Slack:

```php
// Example: Alert on high error rate
if ($errorRate > 5) {
    Log::channel('slack')->critical('High error rate detected', [
        'error_rate' => $errorRate . '%',
        'time_window' => '1 hour',
        'action_required' => 'Investigate immediately'
    ]);
}
```

---

## 5. Common Tasks

### 5.1 Deploy New Release

```bash
# Run deployment script
cd /var/www/ai-legal-war-machine
./scripts/deploy.sh

# Or with options
./scripts/deploy.sh --skip-tests --skip-npm

# Monitor deployment
tail -f storage/logs/deploy-*.log
```

### 5.2 Rollback Deployment

```bash
# View recent commits
cd /var/www/ai-legal-war-machine
git log --oneline -10

# Enable maintenance mode
php artisan down

# Stop queue workers
sudo supervisorctl stop ai-legal-queue-worker:*

# Rollback to previous commit
git checkout <previous-commit-hash>

# Reinstall dependencies
composer install --no-dev --optimize-autoloader
npm ci && npm run build

# Rollback migrations (if needed)
php artisan migrate:rollback --step=1

# Clear cache
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Restart services
php artisan config:cache
php artisan route:cache
sudo systemctl restart php8.2-fpm
sudo supervisorctl start ai-legal-queue-worker:*

# Disable maintenance mode
php artisan up

# Verify
curl http://localhost/api/health | jq
```

### 5.3 Clear Cache

```bash
# Clear all caches
cd /var/www/ai-legal-war-machine

php artisan cache:clear         # Application cache
php artisan config:clear        # Configuration cache
php artisan route:clear         # Route cache
php artisan view:clear          # View cache

# Rebuild caches
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Redis flush (use with caution - clears ALL cache)
# redis-cli FLUSHDB
```

### 5.4 Restart Services

```bash
# Restart PHP-FPM
sudo systemctl restart php8.2-fpm

# Restart Nginx
sudo systemctl restart nginx

# Restart queue workers
sudo supervisorctl restart ai-legal-queue-worker:*

# Restart PostgreSQL (if needed)
sudo systemctl restart postgresql

# Restart Redis (if needed)
sudo systemctl restart redis-server

# Restart Neo4j (if needed)
sudo systemctl restart neo4j

# Verify all services
systemctl status php8.2-fpm nginx postgresql redis-server neo4j
sudo supervisorctl status
```

### 5.5 View Live Logs

```bash
# Application logs
tail -f /var/www/ai-legal-war-machine/storage/logs/laravel.log

# Queue logs
tail -f /var/www/ai-legal-war-machine/storage/logs/queue.log

# Performance logs
tail -f /var/www/ai-legal-war-machine/storage/logs/performance.log

# Nginx error logs
tail -f /var/log/nginx/ai-legal-error.log

# PHP-FPM logs
tail -f /var/log/php8.2-fpm.log

# Multiple logs simultaneously
tail -f /var/www/ai-legal-war-machine/storage/logs/{laravel,queue,performance}.log
```

### 5.6 Run Artisan Commands

```bash
cd /var/www/ai-legal-war-machine

# Database commands
php artisan db:show              # Show database info
php artisan migrate              # Run migrations
php artisan migrate:status       # Check migration status
php artisan migrate:rollback     # Rollback last migration

# Queue commands
php artisan queue:work           # Start queue worker (foreground)
php artisan queue:monitor        # Monitor queue sizes
php artisan queue:failed         # List failed jobs
php artisan queue:retry all      # Retry all failed jobs
php artisan queue:flush          # Clear all failed jobs

# Cache commands
php artisan cache:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Log analysis
php artisan logs:analyze --stats
php artisan logs:analyze --errors --since="1 hour ago"

# Graph commands
php artisan graph:stats
php artisan graph:query "MATCH (n) RETURN count(n);"

# Tinker (interactive shell)
php artisan tinker
```

### 5.7 Scale Queue Workers

**Increase Workers** (during high load):

```bash
# Edit Supervisor config
sudo nano /etc/supervisor/conf.d/ai-legal-queue-worker.conf

# Change: numprocs=4 to numprocs=8

# Reload Supervisor
sudo supervisorctl reread
sudo supervisorctl update

# Verify
sudo supervisorctl status
```

**Decrease Workers** (during low load):

```bash
# Edit config (set numprocs=2)
sudo nano /etc/supervisor/conf.d/ai-legal-queue-worker.conf

# Stop excess workers
sudo supervisorctl stop ai-legal-queue-worker:4
sudo supervisorctl stop ai-legal-queue-worker:5
sudo supervisorctl stop ai-legal-queue-worker:6
sudo supervisorctl stop ai-legal-queue-worker:7

# Remove from config
sudo supervisorctl reread
sudo supervisorctl update
```

### 5.8 Import Court Decisions

```bash
cd /var/www/ai-legal-war-machine

# Import specific decision IDs
php artisan odluke:ingest --ids=decision-id-1,decision-id-2

# Import by court and date range
php artisan odluke:ingest --court="Županijski sud u Osijeku" --from="2024-01-01" --to="2024-12-31"

# Import with graph sync disabled (faster)
php artisan odluke:ingest --ids=decision-id --no-sync

# Monitor queue for processing
php artisan queue:monitor
tail -f storage/logs/queue.log
```

### 5.9 Run Textract on PDF

```bash
cd /var/www/ai-legal-war-machine

# Process PDFs from Google Drive folder
php artisan textract:process-drive-folder <FOLDER_ID> --limit=10

# Monitor processing
tail -f storage/logs/queue.log | grep -i textract
```

### 5.10 Add New User

```bash
cd /var/www/ai-legal-war-machine

php artisan tinker
>>> $user = new \App\Models\User();
>>> $user->name = 'John Doe';
>>> $user->email = 'john@example.com';
>>> $user->password = bcrypt('secure-password');
>>> $user->email_verified_at = now();
>>> $user->save();
>>> exit
```

---

## 6. Incident Response

### 6.1 Incident Severity Levels

**P1 - Critical** (Respond immediately):
- Application completely down
- Data loss or corruption
- Security breach

**P2 - High** (Respond within 1 hour):
- Major feature broken
- Performance severely degraded
- Service partially down

**P3 - Medium** (Respond within 4 hours):
- Minor feature broken
- Performance degraded
- Non-critical service down

**P4 - Low** (Respond within 24 hours):
- Cosmetic issues
- Enhancement requests
- Documentation updates

### 6.2 Incident Response Checklist

**Step 1: Acknowledge** (2 minutes)
- [ ] Confirm incident via health check
- [ ] Notify team via Slack
- [ ] Update status page (if available)

**Step 2: Assess** (5 minutes)
- [ ] Determine severity level
- [ ] Identify affected services
- [ ] Check recent changes (deployments, config)
- [ ] Review logs for errors

**Step 3: Contain** (10 minutes)
- [ ] Enable maintenance mode if needed: `php artisan down`
- [ ] Stop queue workers if needed: `sudo supervisorctl stop ai-legal-queue-worker:*`
- [ ] Block malicious traffic if needed

**Step 4: Investigate** (variable)
- [ ] Reproduce issue if possible
- [ ] Check service logs
- [ ] Check system resources
- [ ] Identify root cause

**Step 5: Resolve** (variable)
- [ ] Apply fix (code, config, or infrastructure)
- [ ] Test fix in staging first if possible
- [ ] Deploy fix to production
- [ ] Verify resolution via health check

**Step 6: Recover** (5 minutes)
- [ ] Disable maintenance mode: `php artisan up`
- [ ] Restart queue workers: `sudo supervisorctl start ai-legal-queue-worker:*`
- [ ] Monitor for stability (30 minutes)

**Step 7: Document** (15 minutes)
- [ ] Write incident report
- [ ] Document root cause
- [ ] Document resolution steps
- [ ] Identify preventive measures

### 6.3 Common Incident Scenarios

**Scenario 1: Application Down (HTTP 500)**

```bash
# Check health
curl http://localhost/api/health

# Check PHP-FPM
systemctl status php8.2-fpm
tail -f /var/log/php8.2-fpm.log

# Check Nginx
systemctl status nginx
tail -f /var/log/nginx/ai-legal-error.log

# Check application logs
tail -f /var/www/ai-legal-war-machine/storage/logs/laravel.log

# Common fixes:
# 1. Restart PHP-FPM
sudo systemctl restart php8.2-fpm

# 2. Clear cache
cd /var/www/ai-legal-war-machine
php artisan config:clear

# 3. Fix permissions
sudo chown -R deploy:www-data /var/www/ai-legal-war-machine
chmod -R 775 storage bootstrap/cache
```

**Scenario 2: Database Connection Failure**

```bash
# Check PostgreSQL status
systemctl status postgresql

# Check connections
sudo -u postgres psql -c "SELECT count(*) FROM pg_stat_activity;"

# Check max connections
sudo -u postgres psql -c "SHOW max_connections;"

# Restart PostgreSQL if needed
sudo systemctl restart postgresql

# Kill stuck connections if needed
sudo -u postgres psql -c "
SELECT pg_terminate_backend(pid)
FROM pg_stat_activity
WHERE datname = 'ai_legal_war_machine' AND state = 'idle in transaction';
"
```

**Scenario 3: Queue Workers Stopped**

```bash
# Check worker status
sudo supervisorctl status ai-legal-queue-worker:*

# Restart workers
sudo supervisorctl restart ai-legal-queue-worker:*

# If restart fails, check logs
tail -f /var/www/ai-legal-war-machine/storage/logs/queue-worker.log

# Reload Supervisor config
sudo supervisorctl reread
sudo supervisorctl update
```

**Scenario 4: Disk Space Full**

```bash
# Check disk usage
df -h

# Find largest directories
du -sh /var/www/ai-legal-war-machine/* | sort -rh | head -10

# Force log rotation
sudo logrotate -f /etc/logrotate.d/ai-legal-war-machine

# Clear old logs manually
find /var/www/ai-legal-war-machine/storage/logs -name "*.log.*" -mtime +7 -delete

# Clear old backups
find /var/backups -type f -mtime +30 -delete

# Clear cache
cd /var/www/ai-legal-war-machine
php artisan cache:clear
```

**Scenario 5: High Memory Usage**

```bash
# Identify memory hog
ps aux --sort=-%mem | head -20

# Check for memory leaks in queue workers
sudo supervisorctl status ai-legal-queue-worker:*

# Restart queue workers (frees memory)
sudo supervisorctl restart ai-legal-queue-worker:*

# Reduce worker count temporarily
sudo nano /etc/supervisor/conf.d/ai-legal-queue-worker.conf
# Set: numprocs=2
sudo supervisorctl reread && sudo supervisorctl update

# Restart services
sudo systemctl restart php8.2-fpm
```

---

## 7. Performance Optimization

### 7.1 Application Performance

**Enable Caching**:

```bash
cd /var/www/ai-legal-war-machine

# Cache configuration
php artisan config:cache

# Cache routes
php artisan route:cache

# Cache views
php artisan view:cache

# Optimize autoloader
composer dump-autoload --optimize
```

**Monitor Performance**:

```bash
# Analyze slow requests
php artisan logs:analyze --channel=performance --stats

# Identify slowest endpoints
grep "Slow request" storage/logs/performance.log | grep -o '"url":"[^"]*"' | sort | uniq -c | sort -rn | head -10
```

### 7.2 Database Optimization

**Analyze Query Performance**:

```bash
# Enable query logging temporarily
# Edit .env: LOG_DATABASE_LEVEL=debug

# Restart to apply
php artisan config:cache
sudo systemctl restart php8.2-fpm

# Reproduce slow request

# Analyze queries
php artisan logs:analyze --channel=database --tail=100

# Disable query logging after investigation
# Edit .env: LOG_DATABASE_LEVEL=info
```

**Optimize Database**:

```bash
# Vacuum and analyze
sudo -u postgres vacuumdb --analyze ai_legal_war_machine

# Check index usage
sudo -u postgres psql ai_legal_war_machine -c "
SELECT schemaname, tablename, indexname, idx_scan
FROM pg_stat_user_indexes
WHERE idx_scan = 0
ORDER BY schemaname, tablename;
"

# Add missing indexes (example)
# php artisan make:migration add_index_to_laws_table
```

### 7.3 Redis Optimization

**Monitor Redis**:

```bash
# Memory usage
redis-cli INFO memory

# Key count by database
redis-cli INFO keyspace

# Check for large keys
redis-cli --bigkeys

# Monitor real-time
redis-cli MONITOR
```

**Optimize Redis**:

```bash
# Edit Redis config
sudo nano /etc/redis/redis.conf

# Adjust maxmemory policy
maxmemory 2gb
maxmemory-policy allkeys-lru

# Restart Redis
sudo systemctl restart redis-server
```

### 7.4 Neo4j Optimization

**Monitor Neo4j Performance**:

```bash
# Query execution stats
cypher-shell -u neo4j -p "$NEO4J_PASSWORD" "CALL db.stats.retrieve('GRAPH COUNTS');"

# Index usage
cypher-shell -u neo4j -p "$NEO4J_PASSWORD" "SHOW INDEXES;"
```

**Optimize Neo4j**:

```bash
# Edit Neo4j config
sudo nano /etc/neo4j/neo4j.conf

# Increase memory (for 8GB server)
server.memory.heap.max_size=2g
server.memory.pagecache.size=2g

# Restart Neo4j
sudo systemctl restart neo4j
```

---

## 8. Data Management

### 8.1 Database Backups

**Manual Backup**:

```bash
# Run backup script
DB_PASSWORD="YOUR_PASSWORD" /var/www/ai-legal-war-machine/scripts/backup-database.sh

# Verify backup
ls -lh /var/backups/ai-legal-war-machine/
```

**Automated Backups** (via cron):

```bash
# Check cron schedule
crontab -l | grep backup

# Expected: Daily at 2 AM
# 0 2 * * * DB_PASSWORD="..." /var/www/ai-legal-war-machine/scripts/backup-database.sh
```

### 8.2 Database Restore

**Restore from Backup**:

```bash
# Enable maintenance mode
cd /var/www/ai-legal-war-machine
php artisan down

# Stop queue workers
sudo supervisorctl stop ai-legal-queue-worker:*

# Choose backup file
ls -lh /var/backups/ai-legal-war-machine/db_*.sql.gz
BACKUP_FILE="/var/backups/ai-legal-war-machine/db_YYYYMMDD_HHMMSS.sql.gz"

# Drop current database
sudo -u postgres psql -c "DROP DATABASE ai_legal_war_machine;"

# Recreate database
sudo -u postgres psql -c "CREATE DATABASE ai_legal_war_machine OWNER ai_legal_user;"

# Restore backup
gunzip -c "$BACKUP_FILE" | sudo -u postgres psql ai_legal_war_machine

# Verify
sudo -u postgres psql ai_legal_war_machine -c "SELECT count(*) FROM users;"

# Restart queue workers
sudo supervisorctl start ai-legal-queue-worker:*

# Disable maintenance mode
php artisan up
```

### 8.3 Export Data

**Export to CSV**:

```bash
cd /var/www/ai-legal-war-machine

php artisan tinker
>>> $users = \App\Models\User::all();
>>> $csv = tmpfile();
>>> fputcsv($csv, ['ID', 'Name', 'Email', 'Created At']);
>>> foreach ($users as $user) {
...   fputcsv($csv, [$user->id, $user->name, $user->email, $user->created_at]);
... }
>>> rewind($csv);
>>> file_put_contents('/tmp/users.csv', stream_get_contents($csv));
>>> exit

# Download from server
# scp deploy@server:/tmp/users.csv ./users.csv
```

### 8.4 Cleanup Old Backups

```bash
# List old backups (> 30 days)
find /var/backups/ai-legal-war-machine -name "db_*.sql.gz" -mtime +30

# Delete old backups
find /var/backups/ai-legal-war-machine -name "db_*.sql.gz" -mtime +30 -delete

# Verify
ls -lh /var/backups/ai-legal-war-machine/ | wc -l
```

---

## 9. User Management

### 9.1 Create New User

```bash
cd /var/www/ai-legal-war-machine

php artisan tinker
>>> $user = new \App\Models\User();
>>> $user->name = 'Jane Doe';
>>> $user->email = 'jane@example.com';
>>> $user->password = bcrypt('SecurePassword123!');
>>> $user->email_verified_at = now();
>>> $user->save();
>>> exit
```

### 9.2 Reset User Password

```bash
php artisan tinker
>>> $user = \App\Models\User::where('email', 'user@example.com')->first();
>>> $user->password = bcrypt('NewSecurePassword123!');
>>> $user->save();
>>> exit
```

### 9.3 Delete User

```bash
php artisan tinker
>>> $user = \App\Models\User::where('email', 'user@example.com')->first();
>>> $user->delete();
>>> exit
```

### 9.4 List All Users

```bash
php artisan tinker
>>> \App\Models\User::all(['id', 'name', 'email', 'created_at'])->toArray();
>>> exit
```

---

## 10. System Maintenance

### 10.1 System Updates

**Security Updates** (monthly):

```bash
# Check for updates
sudo apt update
sudo apt list --upgradable | grep -i security

# Install security updates
sudo apt upgrade -y

# Reboot if kernel updated
# sudo reboot
```

### 10.2 PHP Updates

```bash
# Check current version
php -v

# Check available versions
apt list php8.* | grep -i fpm

# Update PHP (if new minor version available)
sudo apt install php8.2-fpm php8.2-cli

# Restart services
sudo systemctl restart php8.2-fpm
```

### 10.3 Database Updates

**PostgreSQL** (test in staging first):

```bash
# Check current version
psql --version

# Backup before upgrade
DB_PASSWORD="..." /var/www/ai-legal-war-machine/scripts/backup-database.sh

# Upgrade (example: 15 -> 16)
# Follow official PostgreSQL upgrade guide
```

### 10.4 Update Dependencies

**Composer Packages**:

```bash
cd /var/www/ai-legal-war-machine

# Check outdated packages
composer outdated --direct

# Update specific package
composer update vendor/package

# Update all (test in staging first)
composer update

# Reinstall
composer install --no-dev --optimize-autoloader
```

**NPM Packages**:

```bash
# Check outdated packages
npm outdated

# Update specific package
npm update package-name

# Rebuild assets
npm run build
```

---

## 11. Best Practices

### 11.1 Change Management

- **Always test changes in staging first**
- **Schedule deployments during low-traffic hours**
- **Use maintenance mode for major changes**
- **Keep rollback plan ready**
- **Notify users of scheduled maintenance**

### 11.2 Monitoring

- **Check health endpoint daily**
- **Review logs daily**
- **Monitor disk space weekly**
- **Test backups monthly**
- **Review security logs weekly**

### 11.3 Security

- **Keep all software updated**
- **Review access logs regularly**
- **Rotate credentials quarterly**
- **Test incident response procedures**
- **Maintain security audit trail**

### 11.4 Documentation

- **Document all changes**
- **Keep runbooks up to date**
- **Record incident resolutions**
- **Share knowledge with team**
- **Update this manual quarterly**

---

## Appendix A: Quick Reference

### Commands

```bash
# Health check
curl http://localhost/api/health | jq

# Log analysis
php artisan logs:analyze --stats

# Queue status
php artisan queue:monitor

# Restart services
sudo systemctl restart php8.2-fpm nginx
sudo supervisorctl restart ai-legal-queue-worker:*

# Deploy
./scripts/deploy.sh

# Backup
DB_PASSWORD="..." /var/www/ai-legal-war-machine/scripts/backup-database.sh
```

### Emergency Contacts

- **DevOps Lead**: [email/phone]
- **System Administrator**: [email/phone]
- **On-Call Engineer**: [rotation schedule]
- **Escalation**: [manager email/phone]

### External Services

- **Monitoring**: UptimeRobot/Pingdom dashboard URL
- **Alerts**: Slack channel #alerts
- **Status Page**: [URL if available]
- **Documentation**: This manual + deployment-runbook.md

---

**End of Operations Manual**

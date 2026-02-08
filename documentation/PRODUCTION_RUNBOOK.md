# Production Runbook

**Sprint 6.7: Production Monitoring Setup**

This runbook provides step-by-step procedures for responding to production alerts and common issues.

---

## Table of Contents

1. [Alert Response Workflow](#alert-response-workflow)
2. [Common Issues](#common-issues)
   - [Agent Failure Rate High](#agent-failure-rate-high)
   - [Response Time Excessive](#response-time-excessive)
   - [API Error Rate High](#api-error-rate-high)
   - [Queue Backlog High](#queue-backlog-high)
   - [Multiple Production Issues](#multiple-production-issues)
   - [Agent Failure Rate Elevated](#agent-failure-rate-elevated)
   - [Response Time Degrading](#response-time-degrading)
3. [Diagnostic Commands](#diagnostic-commands)
4. [Escalation Procedures](#escalation-procedures)
5. [Rollback Procedures](#rollback-procedures)

---

## Alert Response Workflow

When an alert fires, follow this workflow:

1. **Acknowledge** the alert immediately
2. **Assess** the severity and impact
3. **Investigate** using diagnostic commands
4. **Mitigate** the issue following runbook procedures
5. **Document** actions taken
6. **Resolve** and clear the alert
7. **Post-mortem** for critical issues

---

## Common Issues

### Agent Failure Rate High

**Alert**: `AgentFailureRateHigh`
**Severity**: CRITICAL
**Threshold**: Agent failure rate >10%

#### Symptoms
- Multiple agent runs failing
- High error rate in agent logs
- Sentry showing agent exceptions
- Users reporting research results not completing

#### Diagnostic Steps

1. **Check agent run status** (last 15 minutes):
   ```bash
   php artisan tinker
   >>> \App\Models\AgentRun::where('created_at', '>=', now()->subMinutes(15))->get(['id', 'agent_name', 'status', 'error_message'])->groupBy('status')
   ```

2. **Review recent errors** in logs:
   ```bash
   tail -n 100 storage/logs/laravel.log | grep -i "agent"
   ```

3. **Check Sentry** for exception details:
   - Navigate to https://sentry.io/your-project
   - Filter by tag: `component:agents`
   - Review recent exceptions

4. **Check external API connectivity**:
   ```bash
   # OpenAI API
   curl -I https://api.openai.com/v1/models

   # Odluke.sudovi.hr
   curl -I https://odluke.sudovi.hr/

   # AWS S3
   aws s3 ls s3://your-bucket --region eu-central-1
   ```

5. **Check agent execution history**:
   ```bash
   # TODO: Command 'agent:stats' not yet implemented
   # Check database directly via tinker:
   php artisan tinker
   >>> \App\Models\AgentRun::where('created_at', '>=', now()->subHour())->groupBy('status')->selectRaw('status, count(*) as count')->get()
   ```

#### Resolution Steps

**Scenario A: OpenAI API Issues**
1. Check OpenAI status: https://status.openai.com
2. If rate limited, wait and retry
3. If API key expired/invalid:
   ```bash
   php artisan config:clear
   # Update OPENAI_API_KEY in .env
   php artisan config:cache
   ```

**Scenario B: Odluke.sudovi.hr Connectivity**
1. Check circuit breaker status:
   ```bash
   php artisan tinker
   >>> \Illuminate\Support\Facades\Cache::get('odluke:circuit_breaker:state')
   ```
2. If circuit open, wait for cooldown (60 seconds) or manually close:
   ```bash
   >>> \Illuminate\Support\Facades\Cache::forget('odluke:circuit_breaker:state')
   ```

**Scenario C: Database Issues**
1. Check database connections:
   ```bash
   php artisan db:monitor
   ```
2. Check for long-running queries:
   ```sql
   SELECT pid, now() - query_start as duration, query
   FROM pg_stat_activity
   WHERE state = 'active' AND now() - query_start > interval '10 seconds';
   ```
3. Kill stuck queries if necessary:
   ```sql
   SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE pid = <PID>;
   ```

**Scenario D: Prompt Injection Detection**
1. Check for sanitization issues:
   ```bash
   grep "REDACTED" storage/logs/laravel.log | tail -n 20
   ```
2. Review objectives being blocked
3. If legitimate objectives blocked, adjust sanitization patterns

#### Prevention
- Monitor OpenAI API usage and quotas
- Implement retry logic with exponential backoff
- Maintain circuit breaker patterns for external services
- Regular testing of agent execution paths

---

### Response Time Excessive

**Alert**: `ResponseTimeExcessive`
**Severity**: WARNING
**Threshold**: P95 response time >30 seconds

#### Symptoms
- Users reporting slow page loads
- API timeouts
- High server CPU/memory usage
- Database query backlog

#### Diagnostic Steps

1. **Check current response times**:
   ```bash
   # TODO: Command 'monitoring:performance' not yet implemented
   # For now, check logs directly:
   php artisan logs:analyze --channel=performance --since="15 minutes ago"
   ```

2. **Identify slow endpoints**:
   ```bash
   tail -n 500 storage/logs/laravel.log | grep "request_duration" | sort -k10 -nr | head -20
   ```

3. **Check database performance**:
   ```sql
   -- Check slow queries
   SELECT query, calls, total_exec_time, mean_exec_time
   FROM pg_stat_statements
   ORDER BY mean_exec_time DESC
   LIMIT 10;
   ```

4. **Check Redis/cache performance**:
   ```bash
   redis-cli --latency
   redis-cli INFO stats | grep instantaneous_ops
   ```

5. **Check external API latency**:
   ```bash
   # OpenAI API latency
   time curl -s -X POST https://api.openai.com/v1/chat/completions \
     -H "Authorization: Bearer $OPENAI_API_KEY" \
     -d '{"model":"gpt-4o-mini","messages":[{"role":"user","content":"test"}]}'
   ```

6. **Monitor system resources**:
   ```bash
   top -b -n 1 | head -20
   free -h
   df -h
   ```

#### Resolution Steps

**Scenario A: Slow Database Queries**
1. Identify the slowest queries from pg_stat_statements
2. Add missing indexes:
   ```sql
   CREATE INDEX CONCURRENTLY idx_agent_runs_status_created
   ON agent_runs(status, created_at);
   ```
3. Optimize query:
   ```bash
   EXPLAIN ANALYZE <slow_query>;
   ```
4. Consider query caching for frequently-accessed data

**Scenario B: High CPU/Memory Usage**
1. Identify resource-intensive processes:
   ```bash
   ps aux --sort=-%cpu | head -10
   ps aux --sort=-%mem | head -10
   ```
2. If PHP-FPM pools exhausted, increase workers:
   ```bash
   # Edit /etc/php/8.2/fpm/pool.d/www.conf
   pm.max_children = 50  # Increase from default
   systemctl reload php8.2-fpm
   ```
3. Clear application cache:
   ```bash
   php artisan cache:clear
   php artisan config:clear
   php artisan route:clear
   php artisan view:clear
   ```

**Scenario C: External API Latency**
1. Check OpenAI API status
2. Implement request timeouts:
   ```bash
   # Update .env
   OPENAI_REQUEST_TIMEOUT=30
   php artisan config:cache
   ```
3. Add caching layer for LLM responses where appropriate

**Scenario D: Insufficient Resources**
1. Scale horizontally (add more app servers)
2. Scale vertically (increase server resources)
3. Implement rate limiting:
   ```bash
   php artisan config:set monitoring.alert_thresholds.api.response_time 25000
   ```

#### Prevention
- Regular database maintenance (VACUUM, ANALYZE)
- Proactive index creation
- Monitor and optimize N+1 queries
- Implement request caching strategies
- Load testing before deployments

---

### API Error Rate High

**Alert**: `ApiErrorRateHigh`
**Severity**: CRITICAL
**Threshold**: API error rate >5%

#### Symptoms
- Users reporting errors
- 500/503 error responses
- Sentry flooding with exceptions
- Application logs showing errors

#### Diagnostic Steps

1. **Check error rate**:
   ```bash
   # TODO: Command 'monitoring:error-rate' not yet implemented
   # For now, check logs directly:
   php artisan logs:analyze --errors --since="15 minutes ago"
   ```

2. **Identify error patterns**:
   ```bash
   tail -n 200 storage/logs/laravel.log | grep -E "ERROR|CRITICAL" | head -20
   ```

3. **Check Sentry for recent exceptions**:
   - Filter by time: last 15 minutes
   - Group by error type
   - Identify most frequent errors

4. **Check application health**:
   ```bash
   # TODO: Verify correct health endpoint URL
   curl http://localhost/api/health
   # Or if monitoring endpoint exists:
   # curl http://localhost/api/monitoring/health
   ```

5. **Check database connectivity**:
   ```bash
   # TODO: Command 'db:monitor' not yet implemented
   php artisan db:show
   ```

6. **Check external service status**:
   ```bash
   # OpenAI
   curl -I https://api.openai.com/v1/models

   # AWS
   aws sts get-caller-identity
   ```

#### Resolution Steps

**Scenario A: Database Connection Errors**
1. Check PostgreSQL is running:
   ```bash
   systemctl status postgresql
   ```
2. Check connection limits:
   ```sql
   SELECT count(*) FROM pg_stat_activity;
   SELECT setting FROM pg_settings WHERE name = 'max_connections';
   ```
3. Kill idle connections if at limit:
   ```sql
   SELECT pg_terminate_backend(pid)
   FROM pg_stat_activity
   WHERE state = 'idle'
   AND state_change < now() - interval '10 minutes';
   ```
4. Restart database if necessary:
   ```bash
   sudo systemctl restart postgresql
   ```

**Scenario B: Application Exceptions**
1. Identify most common exception from Sentry
2. Check recent code deployments:
   ```bash
   git log --oneline -10
   ```
3. If recent deployment caused issue, consider rollback (see [Rollback Procedures](#rollback-procedures))
4. Fix the bug and deploy hotfix

**Scenario C: External Service Failures**
1. Check OpenAI API status: https://status.openai.com
2. Implement fallback behavior:
   ```bash
   # Temporarily disable agent auto-execution
   php artisan down --message="Maintenance: External service issue"
   ```
3. Monitor service recovery
4. Re-enable when service restored:
   ```bash
   php artisan up
   ```

**Scenario D: Memory Exhaustion**
1. Check PHP memory limit:
   ```bash
   php -i | grep memory_limit
   ```
2. Check for memory leaks:
   ```bash
   php artisan tinker
   >>> memory_get_peak_usage(true) / 1024 / 1024; // MB
   ```
3. Increase memory limit if needed:
   ```bash
   # Edit php.ini
   memory_limit = 512M
   systemctl reload php8.2-fpm
   ```
4. Investigate memory-intensive code paths

#### Prevention
- Comprehensive error handling
- Circuit breakers for external services
- Health checks and auto-recovery
- Regular code review for error handling
- Alerting on error rate trends

---

### Queue Backlog High

**Alert**: `QueueBacklogHigh`
**Severity**: WARNING
**Threshold**: Total queue backlog >100 jobs

#### Symptoms
- Jobs processing slowly
- Delayed notifications
- Agent runs not starting promptly
- Textract jobs queued

#### Diagnostic Steps

1. **Check queue sizes**:
   ```bash
   # TODO: Command 'queue:monitor' not yet implemented
   # Use Laravel's built-in command:
   php artisan queue:work --once
   # Or check via tinker:
   php artisan tinker
   >>> DB::table('jobs')->count()
   ```

2. **Check queue worker status**:
   ```bash
   ps aux | grep "queue:work"
   systemctl status laravel-worker
   ```

3. **Check failed jobs**:
   ```bash
   php artisan queue:failed
   ```

4. **Check Redis memory**:
   ```bash
   redis-cli INFO memory
   ```

5. **Check for stuck jobs**:
   ```sql
   SELECT id, queue, payload, created_at
   FROM jobs
   WHERE reserved_at IS NOT NULL
   AND reserved_at < NOW() - INTERVAL '10 minutes';
   ```

#### Resolution Steps

**Scenario A: Worker Not Running**
1. Start queue worker:
   ```bash
   systemctl start laravel-worker
   ```
2. Check worker logs:
   ```bash
   journalctl -u laravel-worker -n 50
   ```
3. If failing to start, check worker configuration

**Scenario B: Too Many Jobs, Insufficient Workers**
1. Check worker count:
   ```bash
   ps aux | grep "queue:work" | wc -l
   ```
2. Temporarily add more workers:
   ```bash
   php artisan queue:work --queue=agents --tries=1 &
   php artisan queue:work --queue=textract --tries=1 &
   php artisan queue:work --queue=default --tries=1 &
   ```
3. For permanent solution, update Supervisor config:
   ```bash
   # Edit /etc/supervisor/conf.d/laravel-worker.conf
   numprocs=5  # Increase worker count
   supervisorctl reread
   supervisorctl update
   supervisorctl restart laravel-worker:*
   ```

**Scenario C: Stuck Jobs**
1. Identify stuck jobs:
   ```sql
   SELECT * FROM jobs WHERE reserved_at < NOW() - INTERVAL '10 minutes';
   ```
2. Kill stuck jobs:
   ```sql
   DELETE FROM jobs WHERE id IN (<stuck_job_ids>);
   ```
3. Restart queue workers:
   ```bash
   supervisorctl restart laravel-worker:*
   ```

**Scenario D: Failed Jobs Accumulating**
1. Review failed jobs:
   ```bash
   php artisan queue:failed
   ```
2. Retry all failed jobs (if safe):
   ```bash
   php artisan queue:retry all
   ```
3. Or flush failed jobs:
   ```bash
   php artisan queue:flush
   ```

#### Prevention
- Monitor queue size trends
- Auto-scaling workers based on queue depth
- Job timeout configuration
- Regular cleanup of old jobs
- Alerting on worker health

---

### Multiple Production Issues

**Alert**: `MultipleProductionIssues`
**Severity**: CRITICAL
**Threshold**: 2+ critical alerts firing simultaneously

#### Symptoms
- Multiple alerts firing at once
- Widespread system degradation
- Users reporting multiple issues
- Dashboard showing red across metrics

#### Diagnostic Steps

1. **Check system-wide health**:
   ```bash
   # TODO: Command 'monitoring:health' not yet implemented
   # Use available health endpoint:
   curl http://localhost/api/health | jq
   ```

2. **Review all active alerts**:
   ```bash
   curl http://localhost:9090/api/v1/alerts | jq '.data.alerts[] | select(.state=="firing")'
   ```

3. **Check recent deployments**:
   ```bash
   git log --oneline -10
   git diff HEAD~1 HEAD --stat
   ```

4. **Check infrastructure status**:
   ```bash
   # Server resources
   top -b -n 1 | head -20
   df -h
   free -h

   # Services
   systemctl status postgresql
   systemctl status redis
   systemctl status php8.2-fpm
   systemctl status nginx
   ```

5. **Check for DDoS or traffic spike**:
   ```bash
   tail -n 1000 /var/log/nginx/access.log | awk '{print $1}' | sort | uniq -c | sort -rn | head -20
   ```

#### Resolution Steps

**CRITICAL: Page on-call engineer immediately**

1. **Assess impact scope**:
   - Which services are affected?
   - How many users impacted?
   - Any data loss risk?

2. **If recent deployment**:
   ```bash
   # Rollback immediately (see Rollback Procedures)
   git revert HEAD
   php artisan down
   composer install
   php artisan migrate:rollback
   php artisan config:cache
   php artisan route:cache
   php artisan up
   ```

3. **If infrastructure issue**:
   ```bash
   # Check and restart all services
   for service in postgresql redis php8.2-fpm nginx laravel-worker; do
     systemctl status $service
     systemctl restart $service
   done
   ```

4. **If traffic spike/DDoS**:
   ```bash
   # Enable maintenance mode
   php artisan down --message="Under maintenance"

   # Implement rate limiting at nginx level
   # Edit /etc/nginx/nginx.conf
   limit_req_zone $binary_remote_addr zone=api:10m rate=10r/s;

   # Reload nginx
   nginx -t && systemctl reload nginx
   ```

5. **Clear all caches**:
   ```bash
   php artisan cache:clear
   php artisan config:clear
   php artisan route:clear
   php artisan view:clear
   redis-cli FLUSHDB
   ```

6. **Monitor recovery**:
   ```bash
   # TODO: Command 'monitoring:health' not yet implemented
   watch -n 5 'curl -s http://localhost/api/health | jq .status'
   ```

#### Prevention
- Blue-green deployments
- Canary releases
- Comprehensive pre-deployment testing
- Infrastructure monitoring
- DDoS protection (Cloudflare, AWS Shield)
- Regular disaster recovery drills

---

### Agent Failure Rate Elevated

**Alert**: `AgentFailureRateElevated`
**Severity**: INFO
**Threshold**: Agent failure rate 5-10%

#### Symptoms
- Moderate agent failures
- Some research tasks not completing
- Intermittent issues

#### Diagnostic Steps

1. **Check failure trend**:
   ```bash
   # TODO: Command 'agent:stats' not yet implemented
   php artisan tinker
   >>> \App\Models\AgentRun::where('created_at', '>=', now()->subHour())->groupBy('status')->selectRaw('status, count(*) as count')->get()
   ```

2. **Identify failing agents**:
   ```bash
   php artisan tinker
   >>> \App\Models\AgentRun::where('created_at', '>=', now()->subHour())
       ->where('status', 'failed')
       ->get(['agent_name', 'error_message'])
       ->groupBy('agent_name')
   ```

#### Resolution Steps

1. **Monitor trend**: Check if failure rate increasing or stable
2. **Review error patterns**: Look for common errors
3. **Check external services**: Verify all dependencies healthy
4. **If trend worsening**: Escalate to critical procedures

#### Prevention
- Proactive monitoring
- Early intervention before critical threshold
- Regular health checks

---

### Response Time Degrading

**Alert**: `ResponseTimeDegrading`
**Severity**: INFO
**Threshold**: P95 response time 15-30 seconds

#### Symptoms
- Slower than normal responses
- Users noticing delays
- Not yet critical

#### Diagnostic Steps

1. **Check response time trend**:
   ```bash
   # TODO: Command 'monitoring:response-times' not yet implemented
   php artisan logs:analyze --channel=performance --stats
   ```

2. **Identify slow endpoints**:
   ```bash
   tail -n 500 storage/logs/laravel.log | grep "slow_request"
   ```

#### Resolution Steps

1. **Monitor trend**: Check if degradation continuing
2. **Review recent changes**: Any new code or config?
3. **Check database performance**: Run ANALYZE on tables
4. **If trend continues**: Escalate to critical procedures

#### Prevention
- Performance testing in staging
- Query optimization
- Caching strategies

---

## Diagnostic Commands

### Quick Health Check
```bash
# Overall system health
# TODO: Command 'monitoring:health' not yet implemented
curl http://localhost/api/health | jq

# Agent statistics
# TODO: Command 'agent:stats' not yet implemented
php artisan tinker
# >>> \App\Models\AgentRun::where('created_at', '>=', now()->subHour())->count()

# Queue status
# TODO: Command 'queue:monitor' not yet implemented
php artisan tinker
# >>> DB::table('jobs')->count()

# Database health
# TODO: Command 'db:monitor' not yet implemented
php artisan db:show

# Cache health
redis-cli ping
```

### Log Analysis
```bash
# Recent errors
tail -n 100 storage/logs/laravel.log | grep -i error

# Agent errors
tail -n 100 storage/logs/laravel.log | grep -i agent

# API errors
tail -n 100 /var/log/nginx/error.log

# Slow queries
grep "slow query" storage/logs/laravel.log | tail -20
```

### Performance Analysis
```bash
# Top database queries
SELECT query, calls, total_exec_time / 1000 as total_time_sec, mean_exec_time
FROM pg_stat_statements
ORDER BY total_exec_time DESC
LIMIT 10;

# Table sizes
SELECT schemaname, tablename, pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename)) AS size
FROM pg_tables
WHERE schemaname = 'public'
ORDER BY pg_total_relation_size(schemaname||'.'||tablename) DESC;

# Index usage
SELECT schemaname, tablename, indexname, idx_scan, idx_tup_read, idx_tup_fetch
FROM pg_stat_user_indexes
ORDER BY idx_scan ASC;
```

---

## Escalation Procedures

### Severity Levels

**CRITICAL** (Immediate response required)
- Production completely down
- Data loss occurring
- Multiple systems failing
- Security breach

**Actions**:
1. Page on-call engineer immediately
2. Create incident in incident management system
3. Start incident bridge call
4. Update status page

**WARNING** (Response within 1 hour)
- Degraded performance
- Single system failing
- Error rates elevated

**Actions**:
1. Notify on-call engineer
2. Create ticket
3. Begin investigation
4. Update stakeholders

**INFO** (Response within business hours)
- Monitoring alerts
- Trends to watch

**Actions**:
1. Create ticket for review
2. Monitor trend
3. Plan preventive actions

### Contact Information

**On-call Engineer**: Refer to PagerDuty schedule

**Escalation Path**:
1. On-call engineer
2. Engineering lead
3. CTO

**External Vendors**:
- OpenAI Support: https://help.openai.com
- AWS Support: https://console.aws.amazon.com/support
- Sentry Support: support@sentry.io

---

## Rollback Procedures

### Code Rollback

```bash
# 1. Put application in maintenance mode
php artisan down --message="Rolling back deployment"

# 2. Checkout previous version
git log --oneline -10  # Find commit to rollback to
git checkout <previous-commit-hash>

# 3. Update dependencies
composer install --no-dev --optimize-autoloader

# 4. Rollback migrations (if necessary)
php artisan migrate:rollback --step=1

# 5. Clear caches
php artisan cache:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 6. Restart services
sudo systemctl restart php8.2-fpm
sudo supervisorctl restart laravel-worker:*

# 7. Bring application back up
php artisan up

# 8. Verify functionality
curl http://localhost/api/monitoring/health

# 9. Monitor for 10 minutes
# TODO: Command 'monitoring:health' not yet implemented
watch -n 30 'curl -s http://localhost/api/health | jq .status'
```

### Database Rollback

```bash
# Restore from backup
pg_restore -U postgres -d legal_db /backups/legal_db_$(date +%Y%m%d).dump

# Verify data integrity
psql -U postgres -d legal_db -c "SELECT count(*) FROM users;"
```

### Configuration Rollback

```bash
# Revert .env changes
cp .env.backup .env
php artisan config:cache

# Restart services
sudo systemctl restart php8.2-fpm
```

---

## Post-Incident Review

After resolving any CRITICAL or WARNING incident:

1. **Document incident**:
   - What happened?
   - When did it start?
   - What was the impact?
   - How was it detected?
   - How was it resolved?

2. **Root cause analysis**:
   - What was the root cause?
   - Why did monitoring not catch it earlier?
   - What could prevent recurrence?

3. **Action items**:
   - Code fixes
   - Monitoring improvements
   - Process improvements
   - Documentation updates

4. **Share learnings**:
   - Team retrospective
   - Update runbook
   - Improve monitoring

---

## Additional Resources

- [Monitoring Documentation](MONITORING.md)
- [Security Audit Report](SECURITY_AUDIT.md)
- [API Documentation](API_DOCUMENTATION.md)
- [Testing Guide](TESTING.md)
- [Grafana Dashboards](http://localhost:3000)
- [Prometheus Alerts](http://localhost:9090/alerts)
- [Sentry Dashboard](https://sentry.io)

---

**Last Updated**: 2025-11-11 (Sprint 6.7)
**Version**: 1.0
**Owner**: Backend Team

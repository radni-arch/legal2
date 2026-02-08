# Production Runbook - AI Legal War Machine

**Purpose**: Step-by-step incident response procedures for common alerts and failures.

**Last Updated**: 2025-11-11

---

## Table of Contents

1. [Queue Depth Critical](#queue-depth-critical)
2. [Agent Failure Rate Critical](#agent-failure-rate-critical)
3. [OpenAI API Errors](#openai-api-errors)
4. [Database Connection Pool Exhausted](#database-connection-pool-exhausted)
5. [System Down](#system-down)
6. [Disk Space Critical](#disk-space-critical)
7. [Benchmark Regression](#benchmark-regression)
8. [Agent Latency High](#agent-latency-high)
9. [Neo4j Query Slow](#neo4j-query-slow)
10. [OpenAI Cost High](#openai-cost-high)

---

## Queue Depth Critical

**Alert**: `QueueDepthCritical`
**Severity**: CRITICAL
**Threshold**: Queue depth >100 for >5 minutes

### Symptoms
- Background jobs piling up
- Users report slow processing times
- Queue dashboard shows backlog growing

### Investigation Steps

1. **Check queue status**:
   ```bash
   php artisan queue:monitor
   ```

2. **Identify which queue is backed up**:
   ```bash
   # Check all queues
   php artisan queue:work --queue=textract,agents,default --once --verbose
   ```

3. **Check worker processes**:
   ```bash
   # Are workers running?
   ps aux | grep "queue:work"

   # Supervisor status (if using Supervisor)
   supervisorctl status
   ```

4. **Check for stuck jobs**:
   ```bash
   # View failed jobs
   php artisan queue:failed

   # View job logs
   php artisan pail --filter=queue
   ```

### Common Causes & Solutions

| Cause | Solution |
|-------|----------|
| **Workers stopped** | Restart workers: `supervisorctl restart all` or `php artisan queue:restart` |
| **Stuck job blocking queue** | Kill stuck job: `php artisan queue:forget [job-id]` |
| **Memory leak in worker** | Restart workers with memory limit: `php artisan queue:work --memory=512` |
| **Traffic spike** | Scale workers: `supervisorctl scale laravel-worker:8` (add more workers) |
| **External API slow** | Increase timeouts in `config/queue.php` or throttle job dispatch rate |

### Resolution

```bash
# 1. Restart queue workers
php artisan queue:restart

# 2. If stuck, flush failed jobs (CAREFUL - data loss)
php artisan queue:flush

# 3. Monitor queue depth
watch -n 5 'php artisan queue:monitor'
```

### Escalation
If queue depth doesn't decrease after 30 minutes, escalate to senior engineer.

---

## Agent Failure Rate Critical

**Alert**: `AgentFailureRateCritical`
**Severity**: CRITICAL
**Threshold**: Failure rate >20% for >10 minutes

### Symptoms
- Many agent executions failing
- Users see error messages in Legal Playground
- Logs show repeated exceptions

### Investigation Steps

1. **Identify failing agent**:
   ```bash
   # Check recent agent failures
   php artisan pail --filter=agent --filter=ERROR
   ```

2. **Check OpenAI API status**:
   ```bash
   curl -H "Authorization: Bearer $OPENAI_API_KEY" https://api.openai.com/v1/models
   ```

3. **Check database connectivity**:
   ```bash
   php artisan db:monitor
   ```

4. **Check Neo4j connectivity** (if graph-related agent):
   ```bash
   # Test Neo4j connection
   cypher-shell -u neo4j -p $NEO4J_PASSWORD "RETURN 1"
   ```

### Common Causes & Solutions

| Cause | Solution |
|-------|----------|
| **OpenAI rate limit** | Wait 60s or increase quota at platform.openai.com |
| **OpenAI API key invalid** | Regenerate key, update `.env`, restart workers |
| **Database timeout** | Check slow queries: `SELECT * FROM pg_stat_activity WHERE state = 'active' AND query_start < NOW() - INTERVAL '30 seconds';` |
| **Neo4j down** | Restart Neo4j: `docker restart neo4j` or `systemctl restart neo4j` |
| **Textract quota exceeded** | Check AWS Textract quotas in AWS Console |
| **Agent code bug** | Check recent deployments, consider rollback |

### Resolution

```bash
# 1. If OpenAI API issue, wait or switch to fallback model
php artisan config:cache

# 2. If database issue, check active connections
php artisan db:show

# 3. If Neo4j issue
docker logs neo4j --tail 100

# 4. Retry failed agent jobs
php artisan queue:retry all
```

### Escalation
If failure rate doesn't drop below 10% after 30 minutes, escalate.

---

## OpenAI API Errors

**Alert**: `OpenAIAPIErrorsCritical`
**Severity**: CRITICAL
**Threshold**: >10 errors/minute for >5 minutes

### Symptoms
- Agent execution failures
- HTTP 429 (rate limit) or 500 (server error) from OpenAI
- High latency on OpenAI requests

### Investigation Steps

1. **Check OpenAI API status**:
   - Visit: https://status.openai.com/

2. **Check error types**:
   ```bash
   php artisan pail --filter=openai --filter=ERROR | head -50
   ```

3. **Check rate limit headers**:
   ```bash
   # Recent OpenAI requests with rate limit info
   php artisan pail --filter="rate limit"
   ```

### Common Causes & Solutions

| Cause | Solution |
|-------|----------|
| **Rate limit (429)** | Wait 60s, reduce request rate, or upgrade OpenAI tier |
| **Invalid API key** | Update `.env` OPENAI_API_KEY, restart workers |
| **Quota exceeded** | Check usage at platform.openai.com/usage, upgrade plan |
| **OpenAI outage** | Wait for recovery (check status.openai.com) or use cached results |
| **Timeout** | Increase timeout in `config/openai.php` |

### Resolution

```bash
# 1. Check OpenAI configuration
php artisan config:show openai

# 2. Test OpenAI connection
php artisan openai:test

# 3. If rate limited, pause non-critical jobs
php artisan queue:pause

# 4. Resume after 60 seconds
sleep 60 && php artisan queue:resume
```

### Escalation
If OpenAI is down for >1 hour, consider switching to fallback LLM or pausing agent work.

---

## Database Connection Pool Exhausted

**Alert**: `DatabaseConnectionPoolExhausted`
**Severity**: CRITICAL
**Threshold**: All connections active for >2 minutes

### Symptoms
- New requests hang or timeout
- `could not connect to database` errors
- Application slow or unresponsive

### Investigation Steps

1. **Check active connections**:
   ```sql
   SELECT pid, state, query_start, query
   FROM pg_stat_activity
   WHERE state = 'active'
   ORDER BY query_start;
   ```

2. **Count connections by state**:
   ```sql
   SELECT state, COUNT(*)
   FROM pg_stat_activity
   GROUP BY state;
   ```

3. **Check for long-running queries**:
   ```sql
   SELECT pid, now() - query_start AS duration, query
   FROM pg_stat_activity
   WHERE state = 'active'
   AND now() - query_start > INTERVAL '5 minutes';
   ```

### Common Causes & Solutions

| Cause | Solution |
|-------|----------|
| **Connection leak** | Restart application workers: `php artisan queue:restart` |
| **Slow query blocking** | Kill slow query: `SELECT pg_terminate_backend([pid])` |
| **Too many workers** | Reduce worker count in Supervisor config |
| **Pool size too small** | Increase `DB_POOL_SIZE` in `.env` (e.g., 20 → 50) |
| **Database overloaded** | Scale database vertically (more CPU/RAM) |

### Resolution

```bash
# 1. Kill long-running queries (CAREFUL)
psql -U postgres -d ai_legal_war_machine -c "
  SELECT pg_terminate_backend(pid)
  FROM pg_stat_activity
  WHERE state = 'active'
  AND now() - query_start > INTERVAL '10 minutes';
"

# 2. Restart application
php artisan down
php artisan queue:restart
php artisan up

# 3. Monitor connection count
watch -n 5 "psql -c 'SELECT COUNT(*) FROM pg_stat_activity;'"
```

### Escalation
If connections don't free up after 10 minutes, escalate to DBA.

---

## System Down

**Alert**: `SystemDown`
**Severity**: CRITICAL
**Threshold**: Health check fails for >1 minute

### Symptoms
- Application not responding to HTTP requests
- 502/503 errors from load balancer
- Users cannot access the site

### Investigation Steps

1. **Check process status**:
   ```bash
   # Is PHP-FPM running?
   systemctl status php8.2-fpm

   # Is Nginx running?
   systemctl status nginx
   ```

2. **Check application logs**:
   ```bash
   tail -100 /var/log/nginx/error.log
   php artisan pail --tail=100
   ```

3. **Check disk space**:
   ```bash
   df -h
   ```

4. **Check memory**:
   ```bash
   free -h
   ```

### Common Causes & Solutions

| Cause | Solution |
|-------|----------|
| **Out of memory (OOM)** | Restart PHP-FPM: `systemctl restart php8.2-fpm` |
| **Disk full** | Clear logs: `php artisan log:clear`, delete old files |
| **Nginx crashed** | Restart Nginx: `systemctl restart nginx` |
| **PHP-FPM crashed** | Check logs: `journalctl -u php8.2-fpm -n 100`, restart service |
| **Database down** | Restart PostgreSQL: `systemctl restart postgresql` |
| **Code error blocking boot** | Check `storage/logs/laravel.log`, rollback deployment |

### Resolution

```bash
# 1. Restart all services
systemctl restart php8.2-fpm
systemctl restart nginx
php artisan queue:restart

# 2. Check health
curl http://localhost/health

# 3. If still down, check Laravel logs
tail -100 storage/logs/laravel.log
```

### Escalation
If system doesn't recover after 5 minutes, page on-call engineer.

---

## Disk Space Critical

**Alert**: `DiskSpaceCritical`
**Severity**: CRITICAL
**Threshold**: <10% disk space available

### Symptoms
- Application cannot write logs
- Queue jobs fail with "No space left on device"
- Database writes fail

### Investigation Steps

1. **Check disk usage**:
   ```bash
   df -h
   du -sh /var/www/ai-legal/* | sort -h
   ```

2. **Find large files**:
   ```bash
   find /var/www/ai-legal -type f -size +100M -exec ls -lh {} \;
   ```

3. **Check log size**:
   ```bash
   du -sh /var/log/*
   du -sh /var/www/ai-legal/storage/logs/*
   ```

### Common Causes & Solutions

| Cause | Solution |
|-------|----------|
| **Log files too large** | Rotate logs: `php artisan log:clear`, configure log rotation |
| **Textract output files** | Clean old S3 temp files: `php artisan textract:clean-old --days=30` |
| **Failed queue jobs** | Clear failed jobs table: `php artisan queue:flush` |
| **Database backups** | Move old backups to S3, delete local copies |
| **Temp files** | Clear Laravel cache: `php artisan cache:clear`, `rm -rf storage/framework/cache/*` |

### Resolution

```bash
# 1. Clear Laravel logs (CAREFUL - data loss)
php artisan log:clear

# 2. Clear old queue jobs
php artisan queue:prune-batches --hours=72

# 3. Clear application cache
php artisan cache:clear
php artisan view:clear
php artisan config:clear

# 4. Clear temp files
find /tmp -type f -atime +7 -delete

# 5. Verify space recovered
df -h
```

### Escalation
If disk usage >95% after cleanup, escalate to provision more storage.

---

## Benchmark Regression

**Alert**: `BenchmarkScoreDropped`
**Severity**: WARNING
**Threshold**: Score drops >5% in 24 hours

### Symptoms
- Automated benchmark shows quality degradation
- Specific benchmark score lower than baseline
- May not be user-visible yet

### Investigation Steps

1. **Check recent deployments**:
   ```bash
   git log --oneline --since="24 hours ago"
   ```

2. **Run benchmark manually**:
   ```bash
   php artisan benchmark:run --benchmark=[BenchmarkName]
   ```

3. **Compare with baseline**:
   ```bash
   php artisan benchmark:compare --baseline=[commit-hash]
   ```

4. **Check test failures**:
   ```bash
   ./scripts/run-tests.sh
   ```

### Common Causes & Solutions

| Cause | Solution |
|-------|----------|
| **Code regression** | Revert commit: `git revert [commit-hash]` |
| **Model change** | OpenAI model updated, retrain or adjust prompts |
| **Test data changed** | Re-validate test fixtures, update ground truth |
| **Timing/flakiness** | Run benchmark 3x, average results |
| **Dependency update** | Pin dependency version, test thoroughly |

### Resolution

```bash
# 1. Identify regression commit
git bisect start
git bisect bad HEAD
git bisect good [last-good-commit]

# 2. Run benchmark at each step
php artisan benchmark:run --benchmark=[BenchmarkName]

# 3. Find culprit commit, revert
git bisect reset
git revert [bad-commit]

# 4. Re-run benchmark
php artisan benchmark:run --all
```

### Escalation
If cause unknown after 2 hours, escalate to AI/ML team.

---

## Agent Latency High

**Alert**: `AgentLatencyHigh`
**Severity**: WARNING
**Threshold**: p95 latency >30s for >10 minutes

### Symptoms
- Users report slow responses
- Legal Playground takes >30s to return results
- Queue processing slows down

### Investigation Steps

1. **Check which agent is slow**:
   ```bash
   php artisan pail --filter=agent | grep "duration"
   ```

2. **Profile agent execution**:
   ```bash
   # Enable query log
   DB::enableQueryLog();

   # Run agent manually with timing
   time php artisan agent:test-run [AgentClass]
   ```

3. **Check OpenAI latency**:
   ```bash
   php artisan openai:ping
   ```

4. **Check Neo4j query time** (if graph agent):
   ```cypher
   // In Neo4j browser
   CALL dbms.listQueries() YIELD query, elapsedTimeMillis
   WHERE elapsedTimeMillis > 5000
   RETURN query, elapsedTimeMillis;
   ```

### Common Causes & Solutions

| Cause | Solution |
|-------|----------|
| **Slow OpenAI responses** | Use faster model (gpt-4o-mini instead of gpt-4o) |
| **N+1 database queries** | Add eager loading: `with(['relations'])` |
| **Slow Neo4j queries** | Add indexes: `CREATE INDEX FOR (d:Decision) ON (d.id)` |
| **Large vector search** | Reduce `limit` parameter, add pre-filtering |
| **Too many reasoning traces** | Reduce trace depth, batch trace writes |

### Resolution

```bash
# 1. Profile slow agent
php artisan agent:profile [AgentClass]

# 2. Check slow queries
php artisan db:monitor --slow

# 3. Optimize Neo4j queries
# Run EXPLAIN PLAN in Neo4j browser

# 4. Cache intermediate results
php artisan cache:warm-agent-data
```

### Escalation
If latency doesn't improve after optimization, escalate to performance team.

---

## Neo4j Query Slow

**Alert**: `Neo4jQuerySlow`
**Severity**: WARNING
**Threshold**: p95 query time >5s

### Symptoms
- Graph-based agents slow
- Research queries take >5s
- Neo4j Browser shows slow queries

### Investigation Steps

1. **Check active queries**:
   ```cypher
   CALL dbms.listQueries() YIELD query, elapsedTimeMillis, queryId
   WHERE elapsedTimeMillis > 3000
   RETURN query, elapsedTimeMillis, queryId;
   ```

2. **Check indexes**:
   ```cypher
   SHOW INDEXES;
   ```

3. **Profile slow query**:
   ```cypher
   PROFILE MATCH (d:Decision)-[:CITES]->(l:Law)
   WHERE d.id = 'some-id'
   RETURN d, l;
   ```

### Common Causes & Solutions

| Cause | Solution |
|-------|----------|
| **Missing index** | Create index: `CREATE INDEX FOR (d:Decision) ON (d.id)` |
| **Unbounded traversal** | Add `LIMIT` to query, use depth limits |
| **Large result set** | Add pagination, use `SKIP` and `LIMIT` |
| **Too many relationships** | Add WHERE filters before traversal |
| **Database fragmentation** | Run: `CALL apoc.periodic.compact()` |

### Resolution

```bash
# 1. Check Neo4j performance
docker exec neo4j cypher-shell "CALL dbms.listQueries();"

# 2. Add missing indexes (run in Neo4j browser)
CREATE INDEX decision_id IF NOT EXISTS FOR (d:Decision) ON (d.id);
CREATE INDEX law_number IF NOT EXISTS FOR (l:Law) ON (l.law_number);

# 3. Restart Neo4j (if fragmented)
docker restart neo4j
```

### Escalation
If queries still slow after indexing, escalate to database team.

---

## OpenAI Cost High

**Alert**: `OpenAICostHigh`
**Severity**: WARNING
**Threshold**: >$50 in 24 hours

### Symptoms
- OpenAI usage dashboard shows spike
- Costs higher than expected
- Many API calls in logs

### Investigation Steps

1. **Check usage by model**:
   ```bash
   php artisan openai:usage --last-24h
   ```

2. **Check which agents are using most tokens**:
   ```bash
   php artisan pail --filter=openai | grep "tokens_used" | sort -k5 -n
   ```

3. **Check for loops or retries**:
   ```bash
   php artisan pail --filter=retry
   ```

### Common Causes & Solutions

| Cause | Solution |
|-------|----------|
| **Using expensive model** | Switch gpt-4o → gpt-4o-mini for non-critical tasks |
| **Large context windows** | Reduce input length, summarize before passing to LLM |
| **Many retries** | Fix root cause of failures, reduce retry attempts |
| **No caching** | Enable response caching for repeated queries |
| **Traffic spike** | Expected, monitor and adjust budget |

### Resolution

```bash
# 1. Switch to cheaper models temporarily
php artisan config:set openai.default_model gpt-4o-mini
php artisan config:cache

# 2. Enable aggressive caching
php artisan cache:enable-llm-cache

# 3. Pause non-critical agents
php artisan agent:pause-non-critical

# 4. Monitor cost over next hour
watch -n 300 'php artisan openai:usage --last-1h'
```

### Escalation
If cost >$100/day, escalate to budget owner.

---

## Contact & Escalation Matrix

| Issue Type | First Contact | Escalation (30 min) | Escalation (1 hr) |
|------------|---------------|---------------------|-------------------|
| Queue/Workers | DevOps | Backend Lead | CTO |
| Agent Failures | AI/ML Engineer | AI/ML Lead | CTO |
| Database | Backend Lead | DBA | CTO |
| OpenAI API | AI/ML Engineer | OpenAI Support | CTO |
| Neo4j | Backend Lead | Database Team | CTO |
| System Down | On-Call Engineer | CTO | - |

## Additional Resources

- [Production Monitoring Guide](MONITORING.md)
- [Deployment Guide](DEPLOYMENT.md)
- [Troubleshooting Guide](TROUBLESHOOTING.md)
- [Architecture Overview](ARCHITECTURE.md)

---

**Document Owner**: DevOps Team
**Review Cadence**: Quarterly
**Last Reviewed**: 2025-11-11

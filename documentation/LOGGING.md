# Logging and Observability Documentation

**Sprint 1.7: Observability Setup**
**Date:** 2025-11-10
**Status:** ✅ Completed

---

## Table of Contents

1. [Overview](#overview)
2. [Structured Logging](#structured-logging)
3. [Correlation IDs](#correlation-ids)
4. [Agent Logging Standards](#agent-logging-standards)
5. [Log Channels](#log-channels)
6. [Monitoring Stack](#monitoring-stack)
7. [Dashboards](#dashboards)
8. [Alerting](#alerting)
9. [Log Query Examples](#log-query-examples)
10. [Best Practices](#best-practices)
11. [Troubleshooting](#troubleshooting)

---

## Overview

The AI Legal War Machine implements comprehensive logging and observability to enable debugging, monitoring, and alerting for all AI agent executions. The system uses:

- **Structured logging (JSON)** for machine-readable logs
- **Correlation IDs** for request/execution tracking
- **Monolog processors** for automatic context injection
- **Grafana dashboards** for visualization
- **Prometheus metrics** for monitoring
- **Alertmanager** for intelligent alerting
- **Loki** for log aggregation

### Key Benefits

✅ **Full traceability** - Every agent execution can be traced from start to finish
✅ **Debugging efficiency** - Correlation IDs link related log entries across services
✅ **Proactive monitoring** - Dashboards show agent health in real-time
✅ **Intelligent alerting** - Alerts fire on 5+ consecutive failures
✅ **Cost tracking** - Monitor token usage and OpenAI costs
✅ **Performance insights** - Track execution duration and identify bottlenecks

---

## Structured Logging

### JSON Log Format

All logs are output in JSON format for easy parsing by log aggregators (Loki, Elasticsearch, CloudWatch):

```json
{
  "message": "Agent research completed successfully",
  "level": 200,
  "level_name": "INFO",
  "channel": "agents",
  "context": {
    "agent_name": "autonomous_research_agent",
    "operation": "research",
    "status": "success",
    "correlation_id": "550e8400-e29b-41d4-a716-446655440000",
    "duration_ms": 12543.67,
    "tokens_used": 1250,
    "cost_usd": 0.0375,
    "timestamp": "2025-11-10T15:30:45+00:00"
  },
  "datetime": "2025-11-10T15:30:45.123456+00:00",
  "extra": {}
}
```

### Log Configuration

**File:** `config/logging.php`

```php
'agents' => [
    'driver' => 'monolog',
    'level' => env('LOG_AGENTS_LEVEL', 'info'),
    'handler' => StreamHandler::class,
    'handler_with' => [
        'stream' => storage_path('logs/agents.log'),
    ],
    'formatter' => JsonFormatter::class,
    'formatter_with' => [
        'appendNewline' => true,
    ],
    'processors' => [
        PsrLogMessageProcessor::class,
        \App\Logging\CorrelationIdProcessor::class,
    ],
],
```

---

## Correlation IDs

### What is a Correlation ID?

A **correlation ID** is a unique identifier (UUID) that tracks a single request or agent execution through the entire system, including:

- HTTP requests/responses
- Log entries
- Database queries
- Queue jobs
- External API calls
- Nested agent operations

### How Correlation IDs Work

1. **HTTP Request:** Middleware (`AddCorrelationId`) extracts or generates a correlation ID
2. **Log Context:** ID is added to all log entries via `CorrelationIdProcessor`
3. **Response Header:** ID is returned in `X-Correlation-ID` header
4. **Agent Execution:** Agents inherit correlation ID from context
5. **Child Operations:** Sub-operations can create child IDs (e.g., `parent-id:iteration-1`)

### Using Correlation IDs

#### In HTTP Requests

```bash
# Client can provide correlation ID
curl -H "X-Correlation-ID: my-custom-id-123" https://api.example.com/endpoint

# Server returns same ID in response headers
HTTP/1.1 200 OK
X-Correlation-ID: my-custom-id-123
```

#### In Code

```php
use App\Logging\CorrelationIdProcessor;

// Get current correlation ID
$correlationId = CorrelationIdProcessor::getCorrelationId();

// Set custom correlation ID (e.g., for background jobs)
CorrelationIdProcessor::setCorrelationId('job-123-' . Str::uuid());

// Reset (useful for testing)
CorrelationIdProcessor::resetCorrelationId();
```

#### In Agent Logging

```php
use App\Traits\StructuredAgentLogging;

class MyAgent
{
    use StructuredAgentLogging;

    public function execute()
    {
        // Automatically includes correlation ID
        $this->logAgentStart('execute', ['param' => 'value']);

        // Create child correlation ID for sub-operations
        $childId = $this->createChildCorrelationId('sub-task-1');
        CorrelationIdProcessor::setCorrelationId($childId);

        // ... do work ...

        $this->logAgentSuccess('execute');
    }
}
```

---

## Agent Logging Standards

### Using the StructuredAgentLogging Trait

All agents MUST use the `StructuredAgentLogging` trait for consistent logging:

**File:** `app/Traits/StructuredAgentLogging.php`

```php
use App\Traits\StructuredAgentLogging;

class MyCustomAgent
{
    use StructuredAgentLogging;

    public function research(string $objective): array
    {
        // 1. Log start (returns timestamp for duration calculation)
        $startTime = $this->logAgentStart('research', [
            'objective' => $objective,
            'run_id' => $this->runId,
        ]);

        try {
            // 2. Log iterations
            for ($i = 1; $i <= $maxIterations; $i++) {
                $this->logAgentIteration('research', $i, [
                    'score' => $currentScore,
                    'actions_taken' => $actionCount,
                ]);

                // ... iteration logic ...
            }

            // 3. Log metrics
            $this->logAgentMetrics('research', [
                'tokens_used' => $totalTokens,
                'cost_usd' => $totalCost,
                'iterations' => $i,
                'final_score' => $finalScore,
            ]);

            // 4. Log success
            $this->logAgentSuccess('research', [
                'results_count' => count($results),
                'final_score' => $finalScore,
            ], $startTime);

            return $results;

        } catch (\Throwable $e) {
            // 5. Log failure
            $this->logAgentFailure('research', $e, $startTime, [
                'iteration' => $i ?? 0,
                'last_action' => $lastAction ?? null,
            ]);

            throw $e;
        }
    }
}
```

### Standard Log Methods

| Method | Purpose | When to Use |
|--------|---------|-------------|
| `logAgentStart()` | Mark agent execution start | Beginning of every agent operation |
| `logAgentSuccess()` | Mark successful completion | After successful execution |
| `logAgentFailure()` | Mark failure with exception | In catch blocks |
| `logAgentIteration()` | Log iteration/step progress | Within loops or multi-step processes |
| `logAgentMetrics()` | Log performance metrics | After collecting metrics (tokens, cost, etc.) |
| `logAgentWarning()` | Log non-fatal issues | When something is wrong but not critical |
| `logAgentDebug()` | Log detailed debug info | During development or troubleshooting |

### Required Context Fields

Every agent log MUST include:

| Field | Type | Description | Example |
|-------|------|-------------|---------|
| `agent_name` | string | Name of the agent | "autonomous_research_agent" |
| `operation` | string | Operation being performed | "research", "analyze", "evaluate" |
| `correlation_id` | string | Unique execution ID (UUID) | "550e8400-e29b-41d4-a716-446655440000" |
| `timestamp` | ISO 8601 | When the log occurred | "2025-11-10T15:30:45+00:00" |

### Optional Context Fields

Additional context for richer logging:

| Field | Type | Description |
|-------|------|-------------|
| `run_id` | int | AgentRun database ID |
| `user_id` | int | User who triggered the agent |
| `objective` | string | Agent's objective/goal |
| `iteration` | int | Current iteration number |
| `status` | string | "success", "failure", "running" |
| `duration_ms` | float | Execution duration in milliseconds |
| `tokens_used` | int | OpenAI tokens consumed |
| `cost_usd` | float | Cost in USD |
| `score` | float | Evaluation score (0-1) |
| `error` | string | Error message (for failures) |
| `error_class` | string | Exception class name |

---

## Log Channels

The application uses specialized log channels for different components:

### Available Channels

| Channel | File | Purpose | Retention |
|---------|------|---------|-----------|
| `agents` | `storage/logs/agents.log` | AI agent executions | 14 days |
| `api` | `storage/logs/api.log` | API requests/responses | 7 days |
| `queue` | `storage/logs/queue.log` | Queue job processing | 7 days |
| `openai` | `storage/logs/openai.log` | OpenAI API calls | 14 days |
| `security` | `storage/logs/security.log` | Security events | 30 days |
| `performance` | `storage/logs/performance.log` | Performance metrics | 7 days |
| `monitoring` | `storage/logs/monitoring.log` | System monitoring | 14 days |
| `database` | `storage/logs/database.log` | SQL queries | 3 days |

### Using Specific Channels

```php
use Illuminate\Support\Facades\Log;

// Log to agents channel
Log::channel('agents')->info('Agent started', ['agent' => 'research']);

// Log to security channel
Log::channel('security')->warning('Failed login attempt', ['ip' => $ip]);

// Log to multiple channels
Log::stack(['agents', 'monitoring'])->info('Critical agent event');
```

---

## Monitoring Stack

### Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    Laravel Application                       │
│              (Logs to storage/logs/*.log)                    │
└───────────────────┬─────────────────────────────────────────┘
                    │
                    ▼
        ┌───────────────────────┐
        │      Promtail         │  ← Ships logs to Loki
        └───────────┬───────────┘
                    │
                    ▼
┌─────────────────────────────────────────────────────────────┐
│                         Loki                                 │
│              (Log Aggregation & Querying)                    │
└───────────────────┬─────────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────────────────────────┐
│                       Grafana                                │
│         (Dashboards, Visualization, Queries)                 │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│                     Prometheus                               │
│              (Metrics Collection & Alerting)                 │
└───────────────────┬─────────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────────────────────────┐
│                    Alertmanager                              │
│         (Alert Routing: Slack, PagerDuty, Email)            │
└─────────────────────────────────────────────────────────────┘
```

### Starting the Monitoring Stack

```bash
# Navigate to monitoring directory
cd monitoring

# Start all services
docker-compose -f docker-compose.monitoring.yml up -d

# Verify services are running
docker-compose -f docker-compose.monitoring.yml ps

# View logs
docker-compose -f docker-compose.monitoring.yml logs -f
```

### Accessing Services

| Service | URL | Credentials |
|---------|-----|-------------|
| **Grafana** | http://localhost:3000 | admin / admin |
| **Prometheus** | http://localhost:9090 | None |
| **Alertmanager** | http://localhost:9093 | None |
| **Loki** | http://localhost:3100 | None |

### Stopping the Stack

```bash
docker-compose -f docker-compose.monitoring.yml down

# Remove volumes (data will be lost)
docker-compose -f docker-compose.monitoring.yml down -v
```

---

## Dashboards

### Agent Monitoring Dashboard

**Location:** `monitoring/grafana/dashboards/agent-monitoring.json`

**Panels:**

1. **Agent Success Rate (Last 1h)** - Stat panel with color thresholds
   - Green: ≥95%
   - Yellow: 80-95%
   - Red: <80%

2. **Agent Executions per Minute** - Time series graph by agent
   - Shows execution rate trends
   - Useful for detecting traffic spikes

3. **Agent Failure Rate (Last 24h)** - Table view
   - Groups by agent_name and operation
   - Sortable by failure count

4. **Average Agent Execution Duration** - Time series graph
   - Tracks performance over time
   - Identifies slow agents

5. **Consecutive Failures Alert** - Stat panel
   - Color changes at 3 and 5 failures
   - Triggers alert at 5+

6. **Agent Tokens Usage (Last 1h)** - Stat panel
   - Shows OpenAI token consumption
   - Cost tracking

7. **Agent Cost (Last 24h)** - Stat panel in USD
   - Daily cost monitoring
   - Budget awareness

8. **Active Agent Executions** - Stat panel
   - Shows currently running agents
   - Concurrency monitoring

9. **Agent Error Distribution (Last 6h)** - Pie chart
   - Groups errors by exception class
   - Identifies common failure types

10. **Agent Iteration Count Distribution** - Heatmap
    - Visualizes iteration patterns
    - P95 quantile tracking

11. **Agent Logs (Last 100)** - Log panel
    - Real-time log viewing
    - Filtered by JSON fields

### Custom Dashboard Queries

#### Query: Agent Success Rate
```promql
sum(rate(agent_executions_total{status="success"}[1h])) /
sum(rate(agent_executions_total[1h])) * 100
```

#### Query: Average Duration by Agent
```promql
avg(agent_execution_duration_ms) by (agent_name)
```

#### Query: Tokens Used per Hour
```promql
sum(increase(agent_tokens_used_total[1h]))
```

#### Query: Cost by Agent (24h)
```promql
sum(increase(agent_cost_usd_total[24h])) by (agent_name)
```

---

## Alerting

### Alert Rules

**File:** `monitoring/prometheus/rules/agent-alerts.yml`

#### CRITICAL Alerts

1. **AgentConsecutiveFailures** (5+ consecutive failures)
   - **Severity:** Critical
   - **Condition:** `consecutive_agent_failures >= 5`
   - **For:** 1 minute
   - **Action:** PagerDuty + Slack
   - **Runbook:** Check agent logs, recent deployments, external dependencies

2. **AgentLowSuccessRate** (Success rate <80%)
   - **Severity:** Critical
   - **Condition:** `success_rate < 0.8` over 5 minutes
   - **Action:** PagerDuty + Slack
   - **Runbook:** Investigate error patterns, check OpenAI API status

3. **AgentErrorSpike** (5x error increase)
   - **Severity:** Critical
   - **Condition:** Current rate >5x vs 1 hour ago
   - **For:** 2 minutes
   - **Action:** Slack
   - **Runbook:** Compare with previous hour, check for code changes

4. **AgentPanicDetected** (Panic/Fatal/OOM errors)
   - **Severity:** Critical
   - **Condition:** Any panic-type error
   - **For:** 1 minute
   - **Action:** PagerDuty + Slack
   - **Runbook:** Check memory usage, review stack traces

#### WARNING Alerts

5. **AgentHighExecutionDuration** (P95 >5 minutes)
   - **Severity:** Warning
   - **Condition:** `p95_duration > 300000ms`
   - **For:** 10 minutes
   - **Action:** Slack
   - **Runbook:** Profile slow agents, optimize queries

6. **AgentHighTokenUsage** (>100k tokens/hour)
   - **Severity:** Warning
   - **Condition:** `tokens/hour > 100000`
   - **For:** 5 minutes
   - **Action:** Slack + Finance email
   - **Runbook:** Review agent prompts, check for loops

7. **AgentNoExecutions** (No activity for 30 minutes)
   - **Severity:** Warning
   - **Condition:** `execution_rate == 0` for 30 minutes
   - **For:** 30 minutes
   - **Action:** Slack
   - **Runbook:** Check scheduler, queue workers

8. **AgentHighCost** (>$100/day)
   - **Severity:** Warning
   - **Condition:** `daily_cost > 100 USD`
   - **For:** 5 minutes
   - **Action:** Slack + Finance email
   - **Runbook:** Review cost breakdown by agent

### Alert Routing

**File:** `monitoring/alertmanager/alertmanager.yml`

```yaml
route:
  receiver: 'default'
  routes:
    # CRITICAL → PagerDuty + Slack
    - match:
        severity: critical
      receiver: 'pagerduty-critical'
      continue: true  # Also send to Slack

    - match:
        severity: critical
      receiver: 'slack-critical'

    # WARNING → Slack only
    - match:
        severity: warning
      receiver: 'slack-warnings'

    # Cost alerts → Finance team
    - match_re:
        cost: high
      receiver: 'email-finance'
```

### Configuring Notification Channels

**Environment Variables:**

```env
# Slack
SLACK_WEBHOOK_URL=https://hooks.slack.com/services/YOUR/WEBHOOK/URL

# PagerDuty
PAGERDUTY_SERVICE_KEY=your-pagerduty-key

# Email
SMTP_HOST=smtp.example.com
SMTP_PORT=587
SMTP_USERNAME=alerts@example.com
SMTP_PASSWORD=your-password
FINANCE_EMAIL=finance@example.com
```

### Testing Alerts

```bash
# Manually fire a test alert
curl -X POST http://localhost:9093/api/v1/alerts \
  -H 'Content-Type: application/json' \
  -d '[{
    "labels": {
      "alertname": "TestAlert",
      "severity": "warning",
      "agent_name": "test_agent"
    },
    "annotations": {
      "summary": "This is a test alert",
      "description": "Testing alert routing"
    }
  }]'

# Check Alertmanager status
curl http://localhost:9093/api/v1/status

# View active alerts
curl http://localhost:9093/api/v1/alerts
```

---

## Log Query Examples

### Loki Query Language (LogQL)

#### Query: All Agent Logs
```logql
{job="agents"}
```

#### Query: Failed Agent Executions
```logql
{job="agents"} | json | status="failure"
```

#### Query: Specific Agent
```logql
{job="agents"} | json | agent_name="autonomous_research_agent"
```

#### Query: By Correlation ID
```logql
{job="agents"} | json | correlation_id="550e8400-e29b-41d4-a716-446655440000"
```

#### Query: Slow Executions (>10 seconds)
```logql
{job="agents"} | json | duration_ms > 10000
```

#### Query: High Cost Operations
```logql
{job="agents"} | json | cost_usd > 1.0
```

#### Query: Error Rate Last 5 Minutes
```logql
sum(rate({job="agents"} | json | status="failure" [5m]))
```

#### Query: Average Duration by Agent
```logql
avg_over_time({job="agents"} | json | unwrap duration_ms [1h]) by (agent_name)
```

### Grafana Explore Queries

1. **Navigate to Grafana** → http://localhost:3000
2. **Click "Explore"** in left sidebar
3. **Select "Loki"** as data source
4. **Enter LogQL query** in query box
5. **Click "Run query"** or press Shift+Enter

### CLI Log Queries (Docker)

```bash
# Tail agent logs
docker exec ai-legal-promtail tail -f /var/log/laravel/agents.log

# Search for specific correlation ID
docker exec ai-legal-promtail grep "550e8400" /var/log/laravel/agents.log

# Count failures in last hour
docker exec ai-legal-promtail grep '"status":"failure"' /var/log/laravel/agents.log | wc -l

# Extract all agent names
docker exec ai-legal-promtail grep -o '"agent_name":"[^"]*"' /var/log/laravel/agents.log | sort | uniq
```

---

## Best Practices

### 1. Always Use Structured Logging

❌ **Bad:**
```php
Log::info('Agent finished with score ' . $score);
```

✅ **Good:**
```php
$this->logAgentSuccess('research', [
    'final_score' => $score,
    'iterations' => $iterations,
]);
```

### 2. Include Correlation IDs

❌ **Bad:**
```php
Log::info('Processing item', ['item_id' => 123]);
```

✅ **Good:**
```php
$this->logAgentDebug('process_item', 'Processing item', [
    'item_id' => 123,
    'correlation_id' => $this->getCorrelationId(),
]);
```

### 3. Log Start AND End

❌ **Bad:**
```php
public function execute() {
    // ... work ...
    Log::info('Done');
}
```

✅ **Good:**
```php
public function execute() {
    $startTime = $this->logAgentStart('execute');

    try {
        // ... work ...
        $this->logAgentSuccess('execute', $context, $startTime);
    } catch (\Throwable $e) {
        $this->logAgentFailure('execute', $e, $startTime);
        throw $e;
    }
}
```

### 4. Log Metrics Separately

❌ **Bad:**
```php
Log::info('Completed', [
    'status' => 'success',
    'tokens' => 1250,
    'cost' => 0.05,
    'duration' => 5432,
]);
```

✅ **Good:**
```php
$this->logAgentSuccess('research', ['results' => 10]);
$this->logAgentMetrics('research', [
    'tokens_used' => 1250,
    'cost_usd' => 0.05,
]);
```

### 5. Use Appropriate Log Levels

| Level | When to Use | Example |
|-------|-------------|---------|
| **DEBUG** | Detailed diagnostic info | "Retrieved 50 documents from vector store" |
| **INFO** | General informational | "Agent research completed successfully" |
| **WARNING** | Unexpected but handled | "Retry attempt 2/3 after timeout" |
| **ERROR** | Error occurred, execution failed | "Agent research failed: API rate limit exceeded" |
| **CRITICAL** | System-wide critical failure | "Database connection lost" |

### 6. Sanitize Sensitive Data

❌ **Bad:**
```php
Log::info('User login', [
    'email' => $email,
    'password' => $password,  // ❌ NEVER log passwords
]);
```

✅ **Good:**
```php
Log::channel('security')->info('User login', [
    'email' => $email,
    'ip' => $request->ip(),
    'user_agent' => $request->userAgent(),
]);
```

### 7. Child Correlation IDs for Nested Operations

```php
public function research($objective) {
    $parentId = $this->getCorrelationId();

    foreach ($topics as $i => $topic) {
        // Create child correlation ID
        $childId = $this->createChildCorrelationId("topic-{$i}");
        CorrelationIdProcessor::setCorrelationId($childId);

        $this->logAgentStart('research_topic', ['topic' => $topic]);
        // ... research topic ...
        $this->logAgentSuccess('research_topic');

        // Restore parent correlation ID
        CorrelationIdProcessor::setCorrelationId($parentId);
    }
}
```

---

## Troubleshooting

### Issue: No Logs Appearing in Grafana

**Symptoms:**
- Loki queries return empty results
- Grafana shows "No data"

**Diagnosis:**
```bash
# Check Promtail is running
docker ps | grep promtail

# Check Promtail logs
docker logs ai-legal-promtail

# Verify log files exist
ls -lh storage/logs/agents.log

# Test Loki API directly
curl http://localhost:3100/ready
```

**Solutions:**
1. Ensure monitoring stack is running: `docker-compose up -d`
2. Check log file permissions: `chmod 644 storage/logs/*.log`
3. Verify Promtail configuration: `monitoring/promtail/promtail-config.yml`
4. Restart Promtail: `docker restart ai-legal-promtail`

### Issue: Alerts Not Firing

**Symptoms:**
- Conditions met but no alerts received
- Alertmanager shows no active alerts

**Diagnosis:**
```bash
# Check Prometheus is scraping metrics
curl http://localhost:9090/api/v1/targets

# Check alert rules are loaded
curl http://localhost:9090/api/v1/rules

# Check Alertmanager status
curl http://localhost:9093/api/v1/status

# View active alerts in Alertmanager
curl http://localhost:9093/api/v1/alerts
```

**Solutions:**
1. Verify Prometheus configuration: `monitoring/prometheus/prometheus.yml`
2. Check alert rules syntax: `promtool check rules monitoring/prometheus/rules/*.yml`
3. Test notification channels (Slack webhook, PagerDuty key)
4. Review Alertmanager logs: `docker logs ai-legal-alertmanager`

### Issue: High Log Volume

**Symptoms:**
- Log files growing rapidly (>1GB/day)
- Loki running out of disk space

**Solutions:**
1. Increase log retention cleanup frequency
2. Reduce log level for verbose channels: `LOG_AGENTS_LEVEL=info` → `warning`
3. Filter out noisy logs in Promtail configuration
4. Implement log sampling for high-volume operations
5. Increase Loki retention period or disk space

### Issue: Correlation ID Missing

**Symptoms:**
- Logs missing `correlation_id` field
- Cannot trace request flow

**Diagnosis:**
```bash
# Check if middleware is registered
php artisan route:list --middleware=AddCorrelationId

# Verify processor is configured
grep -A5 "agents" config/logging.php | grep CorrelationIdProcessor
```

**Solutions:**
1. Ensure `AddCorrelationId` middleware is in `app/Http/Kernel.php`
2. Verify `CorrelationIdProcessor` is in logging config
3. For background jobs, manually set correlation ID:
   ```php
   CorrelationIdProcessor::setCorrelationId('job-' . $job->id);
   ```

### Issue: Grafana Dashboard Not Loading

**Symptoms:**
- Dashboard shows "No data" or error
- Queries timeout

**Diagnosis:**
```bash
# Check Grafana is running
curl http://localhost:3000/api/health

# Test Prometheus data source
curl http://localhost:3000/api/datasources

# Check Loki connectivity
curl http://localhost:3100/loki/api/v1/labels
```

**Solutions:**
1. Verify Prometheus/Loki data sources are configured in Grafana
2. Check query syntax in dashboard panels
3. Ensure metrics are being scraped (check Prometheus targets)
4. Import dashboard from JSON: `monitoring/grafana/dashboards/agent-monitoring.json`

---

## Summary

### Acceptance Criteria Status

✅ **All agents emit structured logs (JSON)**
- All agents use JSON formatter via Monolog
- `StructuredAgentLogging` trait enforces consistent format

✅ **Correlation IDs track full agent execution**
- `CorrelationIdProcessor` adds correlation IDs to all logs
- `AddCorrelationId` middleware handles HTTP requests
- Child correlation IDs supported for nested operations

✅ **Dashboard shows agent success/failure rates**
- Grafana dashboard with 11 panels
- Real-time success rate, failure rate, duration, cost tracking
- Log viewer for debugging

✅ **Alerts fire on 5+ consecutive failures**
- Prometheus alert rule: `AgentConsecutiveFailures >= 5`
- Routes to PagerDuty (critical) and Slack
- Additional alerts for low success rate, high duration, cost

✅ **Documentation includes log query examples**
- LogQL examples for common queries
- Prometheus query examples
- CLI commands for log analysis

---

## Additional Resources

- **Grafana Documentation:** https://grafana.com/docs/grafana/latest/
- **Prometheus Documentation:** https://prometheus.io/docs/
- **Loki Documentation:** https://grafana.com/docs/loki/latest/
- **Laravel Logging:** https://laravel.com/docs/logging
- **Monolog:** https://github.com/Seldaek/monolog

**Document Version:** 1.0
**Last Updated:** 2025-11-10
**Maintainer:** AI Legal War Machine Team

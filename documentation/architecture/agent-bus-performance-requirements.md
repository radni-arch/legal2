# Agent Communication Bus - Performance Requirements

**Version**: 1.0
**Date**: 2025-11-10
**Status**: Proposed

## Executive Summary

The Agent Communication Bus must support asynchronous, multi-agent workflows with eventual consistency. This document defines quantitative performance targets, scalability limits, and monitoring strategies.

---

## Performance Targets

### 1. Message Delivery Latency

**Definition**: Time from `bus.send()` call to message delivered to target agent

| Priority   | P50 (Median) | P95          | P99           | Max Acceptable | SLA  |
|------------|--------------|--------------|---------------|----------------|------|
| CRITICAL   | < 500ms      | < 1s         | < 2s          | 5s             | 99%  |
| HIGH       | < 2s         | < 3s         | < 5s          | 10s            | 95%  |
| NORMAL     | < 3s         | < 5s         | < 10s         | 30s            | 90%  |
| LOW        | < 10s        | < 30s        | < 60s         | 300s           | 80%  |

**Measurement**: Track timestamps in `agent_messages` table:
- `created_at` - When message published
- `delivered_at` - When message delivered to target agent
- Latency = `delivered_at - created_at`

**Monitoring**:
```sql
-- P95 latency by priority (last hour)
SELECT
    priority,
    PERCENTILE_CONT(0.50) WITHIN GROUP (ORDER BY latency_ms) AS p50_ms,
    PERCENTILE_CONT(0.95) WITHIN GROUP (ORDER BY latency_ms) AS p95_ms,
    PERCENTILE_CONT(0.99) WITHIN GROUP (ORDER BY latency_ms) AS p99_ms
FROM agent_messages
WHERE created_at >= NOW() - INTERVAL '1 hour'
GROUP BY priority;
```

---

### 2. Message Throughput

**Definition**: Number of messages processed per minute

| Metric                  | Current Scale | Target Scale | Peak Scale    | Circuit Breaker |
|-------------------------|---------------|--------------|---------------|-----------------|
| Messages Published      | 100/min       | 500/min      | 1000/min      | 2000/min        |
| Messages Delivered      | 100/min       | 500/min      | 1000/min      | 2000/min        |
| Queue Depth (CRITICAL)  | < 10          | < 10         | < 50          | 100             |
| Queue Depth (HIGH)      | < 50          | < 50         | < 200         | 500             |
| Queue Depth (NORMAL)    | < 200         | < 500        | < 1000        | 2000            |
| Queue Depth (LOW)       | < 1000        | < 2000       | < 5000        | 10000           |

**Current Scale**: Expected load for first 6 months (5-10 active cases, 3-5 agents per case)

**Target Scale**: Growth target for 12 months (20-30 active cases, 5-10 agents per case)

**Peak Scale**: Maximum burst capacity (e.g., ingesting 100 new court decisions)

**Circuit Breaker**: System refuses new messages beyond this threshold (backpressure)

**Monitoring**:
```sql
-- Messages per minute (last 10 minutes)
SELECT
    DATE_TRUNC('minute', created_at) AS minute,
    COUNT(*) AS messages_per_minute
FROM agent_messages
WHERE created_at >= NOW() - INTERVAL '10 minutes'
GROUP BY minute
ORDER BY minute DESC;

-- Current queue depth by priority
SELECT
    priority,
    COUNT(*) AS queue_depth
FROM jobs
WHERE queue LIKE 'agent_messages%'
  AND available_at <= NOW()
GROUP BY priority;
```

---

### 3. Message Processing Time

**Definition**: Time agent spends processing a delivered message

| Agent Type               | P50    | P95     | Max Acceptable | Timeout |
|--------------------------|--------|---------|----------------|---------|
| ResearchSpecialistAgent  | 30s    | 120s    | 300s           | 600s    |
| PrecedentAnalystAgent    | 20s    | 90s     | 180s           | 300s    |
| RiskAnalystAgent         | 10s    | 30s     | 60s            | 120s    |
| StrategySpecialistAgent  | 15s    | 45s     | 90s            | 180s    |
| ResourceCoordinator      | 100ms  | 500ms   | 2s             | 5s      |

**Note**: Processing time ≠ delivery latency. Total time = delivery latency + processing time.

**Monitoring**:
```sql
-- Agent processing time (last hour)
SELECT
    target_class,
    PERCENTILE_CONT(0.50) WITHIN GROUP (ORDER BY processing_ms) AS p50_ms,
    PERCENTILE_CONT(0.95) WITHIN GROUP (ORDER BY processing_ms) AS p95_ms,
    MAX(processing_ms) AS max_ms
FROM agent_messages
WHERE delivered_at >= NOW() - INTERVAL '1 hour'
  AND completed_at IS NOT NULL
GROUP BY target_class;
```

---

### 4. System Resource Limits

#### Database Connection Pool

| Metric                    | Value   | Rationale                                      |
|---------------------------|---------|------------------------------------------------|
| Max Connections           | 100     | PostgreSQL default, shared with app queries    |
| Queue Worker Connections  | 20      | 4 workers × 5 connections each                 |
| App Connections Reserved  | 80      | For user requests, case management, etc.       |

**Configuration** (`config/database.php`):
```php
'pgsql' => [
    'max_connections' => env('DB_MAX_CONNECTIONS', 20),
    'pool' => [
        'min' => 2,
        'max' => 20,
    ],
],
```

#### Queue Worker Resources

| Metric              | Value     | Rationale                                      |
|---------------------|-----------|------------------------------------------------|
| Worker Processes    | 4         | 1 per priority tier (critical, high, normal, low) |
| Memory per Worker   | 512 MB    | Agents can load large documents                |
| Total Memory Limit  | 2 GB      | 4 workers × 512 MB                             |
| CPU Cores           | 4         | 1 core per worker (parallel processing)        |

**Configuration** (`config/queue.php`):
```php
'agent_messages' => [
    'memory' => env('QUEUE_MEMORY', 512), // MB
    'timeout' => env('QUEUE_TIMEOUT', 300), // seconds
    'max_jobs' => env('QUEUE_MAX_JOBS', 1000),
],
```

#### Message Size Limits

| Metric                  | Value   | Rationale                                      |
|-------------------------|---------|------------------------------------------------|
| Max Message Size        | 64 KB   | Fits in single database row                    |
| Max Payload Size        | 50 KB   | Leaves 14 KB for headers/metadata              |
| Max Large Payload       | 1 MB    | Stored as separate blob, referenced in message |

**Enforcement**:
```php
class AgentMessage
{
    const MAX_PAYLOAD_SIZE = 50 * 1024; // 50 KB

    public function __construct(array $data)
    {
        $payloadSize = strlen(json_encode($data['payload']));

        if ($payloadSize > self::MAX_PAYLOAD_SIZE) {
            throw new MessageTooLargeException(
                "Payload size {$payloadSize} exceeds limit of " . self::MAX_PAYLOAD_SIZE
            );
        }

        // ... rest of constructor
    }
}
```

---

## Scalability Limits

### Vertical Scaling (Single Server)

**Current Configuration** (Development/Staging):
- CPU: 4 cores
- RAM: 8 GB
- Database: PostgreSQL 14 (shared with app)
- Queue Workers: 4 processes

**Expected Capacity**:
- Messages/min: 500
- Concurrent Agents: 10-15
- Queue Depth: 2000 messages

**Bottlenecks**:
1. Database I/O (queue polling queries)
2. Worker process memory (large document processing)
3. CPU (JSON serialization/deserialization)

**Production Configuration** (Recommended):
- CPU: 8 cores
- RAM: 16 GB
- Database: Dedicated PostgreSQL 14 instance
- Queue Workers: 8 processes (2 per priority tier)

**Expected Capacity**:
- Messages/min: 1000
- Concurrent Agents: 30-40
- Queue Depth: 5000 messages

---

### Horizontal Scaling (Multi-Server)

**When to Scale Horizontally**:
- Queue depth consistently > 1000 messages
- P95 latency exceeds SLA by 2x
- Database CPU > 70%
- Worker processes consistently > 80% memory

**Scaling Strategy**:

1. **Database Read Replicas** (if queue reads become bottleneck)
   - Route queue polling to read replicas
   - Write new messages to primary
   - Replication lag: < 1 second acceptable

2. **Multiple Queue Workers** (easiest scaling option)
   - Add more worker servers
   - All workers poll same database queue
   - PostgreSQL handles concurrency with row locks

3. **Message Sharding** (if single queue becomes bottleneck)
   - Shard by `case_id` or `agent_type`
   - Multiple queues: `agent_messages_case_A`, `agent_messages_case_B`, etc.
   - Router assigns messages to shards

4. **Migrate to Redis/RabbitMQ** (if database queue insufficient)
   - Replace database queue with dedicated message broker
   - Keep message format unchanged (easy migration)
   - Gain native pub/sub, clustering, replication

**Cost/Complexity Tradeoff**:
- **Cheapest**: Add more queue worker processes (same server)
- **Low Cost**: Add more worker servers (same database)
- **Medium Cost**: Database read replicas + more workers
- **High Cost**: Migrate to Redis/RabbitMQ + clustered setup

---

## Reliability Guarantees

### Message Delivery Semantics

**At-Least-Once Delivery**:
- Messages remain in queue until successfully processed
- Worker acknowledges message by deleting from `jobs` table
- If worker crashes, message becomes available again after `retry_after` seconds

**Idempotency**:
- Agents must handle duplicate messages gracefully
- Use `message_id` to detect duplicates
- Example: Store processed message IDs in cache/database

```php
class PrecedentAnalystAgent
{
    public function handleMessage(AgentMessage $message)
    {
        // Check if already processed
        if (Cache::has("processed_message:{$message->id}")) {
            Log::info("Duplicate message ignored", ['message_id' => $message->id]);
            return;
        }

        // Process message
        $result = $this->analyzeCase($message->payload);

        // Mark as processed (TTL = 24 hours)
        Cache::put("processed_message:{$message->id}", true, now()->addDay());

        return $result;
    }
}
```

### Fault Tolerance

**Retry Policy**:
| Attempt | Delay      | Total Elapsed |
|---------|------------|---------------|
| 1       | 0s         | 0s            |
| 2       | 5s         | 5s            |
| 3       | 30s        | 35s           |
| Failed  | Move to DLQ| -             |

**Configuration**:
```php
// config/queue.php
'agent_messages' => [
    'tries' => 3,
    'retry_after' => 90, // seconds
    'backoff' => [5, 30], // exponential backoff
],
```

**Dead Letter Queue (DLQ)**:
- Messages that fail 3 times moved to `agent_messages_failed`
- Manual inspection and retry required
- Alert sent to ops team

**Monitoring**:
```sql
-- Failed messages (last 24 hours)
SELECT
    target_class,
    error_message,
    COUNT(*) AS failure_count
FROM agent_messages
WHERE status = 'failed'
  AND created_at >= NOW() - INTERVAL '24 hours'
GROUP BY target_class, error_message
ORDER BY failure_count DESC;
```

---

## Performance Testing Plan

### Load Testing

**Tool**: Apache JMeter or Laravel Dusk

**Scenarios**:

1. **Baseline Load** (Current Scale)
   - 100 messages/min for 30 minutes
   - Mix: 10% CRITICAL, 30% HIGH, 50% NORMAL, 10% LOW
   - Expected: P95 latency within targets

2. **Target Load** (12-month Growth)
   - 500 messages/min for 30 minutes
   - Same priority mix
   - Expected: P95 latency within targets

3. **Peak Load** (Burst Capacity)
   - 1000 messages/min for 10 minutes
   - Expected: P99 latency < 2x target, no message loss

4. **Sustained Peak** (Circuit Breaker Test)
   - 2000 messages/min for 5 minutes
   - Expected: System refuses new messages (backpressure), no crashes

**Acceptance Criteria**:
- ✅ Baseline load: 100% messages delivered within SLA
- ✅ Target load: 95% messages delivered within SLA
- ✅ Peak load: 90% messages delivered within 2x SLA, 0% message loss
- ✅ Sustained peak: System stable, backpressure works, no data corruption

---

### Stress Testing

**Scenarios**:

1. **Database Connection Exhaustion**
   - Simulate 100 concurrent workers (exceeds connection pool)
   - Expected: Graceful degradation, workers wait for available connections

2. **Memory Pressure**
   - Send messages with 50 KB payloads (max size)
   - Expected: Workers stay within memory limit, no OOM kills

3. **Long-Running Agent Processing**
   - Simulate agent that takes 10 minutes to process message
   - Expected: Timeout kills job, message retried, DLQ after 3 attempts

4. **Database Downtime**
   - Stop PostgreSQL for 30 seconds
   - Expected: Workers retry connections, messages queued in memory temporarily

**Acceptance Criteria**:
- ✅ No crashes or data corruption under stress
- ✅ System recovers automatically after stress removed
- ✅ Alerts triggered for anomalies (high latency, failures)

---

## Monitoring & Alerting

### Key Metrics to Monitor

| Metric                        | Warning Threshold | Critical Threshold | Alert Channel |
|-------------------------------|-------------------|--------------------|---------------|
| P95 Latency (CRITICAL)        | > 2s              | > 5s               | PagerDuty     |
| P95 Latency (HIGH)            | > 5s              | > 10s              | Slack         |
| Queue Depth (CRITICAL)        | > 50              | > 100              | PagerDuty     |
| Queue Depth (NORMAL)          | > 1000            | > 2000             | Slack         |
| Failed Messages (per hour)    | > 10              | > 50               | Slack         |
| Worker Process Crashes        | > 1               | > 3                | PagerDuty     |
| Database CPU                  | > 70%             | > 90%              | Slack         |
| Worker Memory Usage           | > 80%             | > 95%              | Slack         |

### Monitoring Dashboards

**Dashboard 1: Message Flow**
- Messages published per minute (by priority)
- Messages delivered per minute (by priority)
- Queue depth over time (by priority)
- P50/P95/P99 latency trends

**Dashboard 2: Agent Performance**
- Processing time by agent type
- Success/failure rate by agent type
- Top 10 slowest agents
- Top 10 failing message types

**Dashboard 3: System Health**
- Worker process count (expected vs actual)
- Database connection pool usage
- Worker memory usage
- Failed job count (DLQ size)

**Implementation**: Use Laravel Horizon (or custom dashboard with Chart.js)

---

### Logging Strategy

**Log Levels**:

| Level   | Use Case                                        | Volume         |
|---------|-------------------------------------------------|----------------|
| DEBUG   | Message routing decisions, queue operations     | High (dev only)|
| INFO    | Message published, delivered, processed         | Medium         |
| WARNING | Retry attempts, slow processing (> P95)         | Low            |
| ERROR   | Message delivery failed, agent crash            | Very Low       |
| CRITICAL| Circuit breaker triggered, system unavailable   | Rare           |

**Example Log Entries**:

```
[INFO] Message published: id=01JKWXYZ..., type=REQUEST, priority=HIGH, target=PrecedentAnalystAgent
[INFO] Message delivered: id=01JKWXYZ..., latency=2.3s, queue_time=2.1s
[WARNING] Slow processing: id=01JKWXYZ..., agent=ResearchSpecialistAgent, duration=145s (P95=120s)
[ERROR] Message delivery failed: id=01JKWXYZ..., attempt=2/3, error="Agent timeout after 300s"
[CRITICAL] Circuit breaker OPEN: queue=agent_messages_normal, depth=2500 (threshold=2000)
```

**Log Storage**:
- Development: `storage/logs/laravel.log` (daily rotation)
- Production: Centralized logging (e.g., Elasticsearch, CloudWatch Logs)
- Retention: 30 days (compliance requirement)

---

## Performance Optimization Strategies

### Database Optimizations

1. **Index Strategy**:
   ```sql
   -- Optimize queue polling query
   CREATE INDEX idx_jobs_priority_available
   ON jobs(queue, priority DESC, available_at ASC)
   WHERE deleted_at IS NULL;

   -- Optimize message lookup by correlation_id
   CREATE INDEX idx_agent_messages_correlation
   ON agent_messages(correlation_id)
   WHERE correlation_id IS NOT NULL;

   -- Optimize subscription lookups
   CREATE INDEX idx_agent_subscriptions_channel
   ON agent_subscriptions(channel, agent_class);
   ```

2. **Connection Pooling**:
   - Use PgBouncer for connection pooling
   - Transaction pooling mode for queue workers
   - Reduce connection overhead by 50-70%

3. **Vacuum Strategy**:
   - `jobs` table has high turnover (delete after processing)
   - Run `VACUUM ANALYZE jobs` every hour (prevent table bloat)
   - Monitor table size and dead tuples

---

### Application Optimizations

1. **Message Serialization**:
   - Use `igbinary` instead of native PHP serialization (faster, smaller)
   - Compress large payloads with `gzip` before queueing

2. **Queue Worker Tuning**:
   ```bash
   # Process multiple messages per worker lifecycle (reduce overhead)
   php artisan queue:work --queue=agent_messages --max-jobs=100

   # Use --sleep to reduce CPU usage during idle periods
   php artisan queue:work --queue=agent_messages --sleep=3
   ```

3. **Subscription Registry Caching**:
   - Cache channel subscriptions in Redis (avoid DB lookup per message)
   - TTL: 5 minutes, refresh on subscription changes

4. **Batch Processing**:
   - Group multiple messages for same agent into batch
   - Reduces overhead of spawning jobs
   - Example: 10 broadcast messages → 1 batch job with 10 payloads

---

## Cost Analysis

### Infrastructure Costs (Monthly)

**Current Scale (Development/Staging)**:
| Resource              | Cost    | Notes                                |
|-----------------------|---------|--------------------------------------|
| EC2 t3.medium (app)   | $30     | 4 cores, 8 GB RAM                    |
| RDS PostgreSQL db.t3.medium | $50 | Shared with app                      |
| **Total**             | **$80** |                                      |

**Target Scale (Production - 12 months)**:
| Resource              | Cost    | Notes                                |
|-----------------------|---------|--------------------------------------|
| EC2 c5.2xlarge (app)  | $120    | 8 cores, 16 GB RAM                   |
| RDS PostgreSQL db.m5.large | $150 | Dedicated for queue + app            |
| EC2 t3.large (workers)| $60     | 2 cores, 8 GB RAM (dedicated workers)|
| **Total**             | **$330**|                                      |

**Peak Scale (if migrate to Redis)**:
| Resource              | Cost    | Notes                                |
|-----------------------|---------|--------------------------------------|
| ElastiCache Redis (m5.large) | $100 | Clustered mode, 2 nodes         |
| EC2 c5.4xlarge (app)  | $240    | 16 cores, 32 GB RAM                  |
| RDS PostgreSQL db.m5.xlarge | $300 | Dedicated for app only             |
| EC2 c5.large (workers)| $120    | 4 instances, 2 cores each            |
| **Total**             | **$760**|                                      |

**Cost Efficiency**:
- **Current approach** (database queue): $80/month for 100 msg/min = **$0.80 per 1K messages**
- **Target approach** (optimized DB queue): $330/month for 500 msg/min = **$0.66 per 1K messages**
- **Peak approach** (Redis): $760/month for 1000 msg/min = **$0.76 per 1K messages**

**Recommendation**: Start with database queue, migrate to Redis only if throughput > 500 msg/min sustained.

---

## Acceptance Criteria

This design meets the following Sprint 17.6 acceptance criteria:

- ✅ **ADR document** in `docs/architecture/adr-001-agent-communication-bus.md`
- ✅ **Message format defined** in ADR Section: "Message Format"
- ✅ **Sequence diagrams** for 4 scenarios in `docs/architecture/agent-communication-flows.md`:
  1. Collaborative Research (Request/Response)
  2. Pipeline Processing (Sequential Work Passing)
  3. Broadcast Coordination (Event Notification)
  4. Resource Negotiation (Coordination)
- ✅ **Performance requirements documented** in this document:
  - Latency targets (P50/P95/P99 by priority)
  - Throughput targets (current/target/peak scale)
  - Resource limits (connections, memory, CPU)
  - Scalability limits (vertical and horizontal)
  - Reliability guarantees (at-least-once, idempotency, retries)
- ⏳ **Team approved design** - Pending review

---

## Next Steps

1. **Team Review** (Sprint 17.6)
   - Present ADR, sequence diagrams, and performance requirements
   - Gather feedback on architecture decisions
   - Approve or iterate on design

2. **Prototype** (Sprint 17.7)
   - Implement core bus components (`AgentMessageBus`, `MessageRouter`, `ProcessAgentMessage`)
   - Create database migrations (`agent_messages`, `agent_subscriptions`, add `priority` to `jobs`)
   - Unit tests for message routing and priority handling

3. **Performance Validation** (Sprint 17.8)
   - Load testing: Validate latency targets at current/target/peak scale
   - Stress testing: Confirm fault tolerance and graceful degradation
   - Monitoring setup: Dashboards and alerts

4. **Production Rollout** (Sprint 17.9)
   - Integrate 1-2 agents as pilot (e.g., `ResearchSpecialistAgent`, `PrecedentAnalystAgent`)
   - Monitor performance in production
   - Iterate based on real-world data

---

**Document Version**: 1.0
**Last Updated**: 2025-11-10
**Approved By**: Pending team review

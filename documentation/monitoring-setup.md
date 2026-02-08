# Production Monitoring & Logging Setup

## Overview

Comprehensive monitoring and logging system for AI Legal War Machine in production.

## Components

1. **Health Check Endpoint** - `/api/health`
2. **Application Performance Monitoring** - Request tracking
3. **Multi-Channel Logging** - 8 specialized log channels
4. **Log Rotation** - Automatic log management
5. **Log Analysis** - Built-in analysis tools
6. **Error Rate Monitoring** - Real-time error tracking

---

## 1. Health Check Endpoint

### Endpoint

```
GET /api/health
```

### Response (200 OK - Healthy)

```json
{
  "status": "healthy",
  "timestamp": "2025-11-09T12:00:00+00:00",
  "services": {
    "database": {
      "status": "healthy",
      "response_time_ms": 2.45,
      "connections": 12,
      "driver": "pgsql"
    },
    "redis": {
      "status": "healthy",
      "response_time_ms": 1.23,
      "memory_usage": "256MB"
    },
    "neo4j": {
      "status": "healthy",
      "response_time_ms": 5.67
    },
    "openai": {
      "status": "healthy",
      "configured": true,
      "model": "gpt-4o-mini"
    },
    "aws": {
      "status": "healthy",
      "configured": true,
      "bucket": "your-bucket",
      "region": "us-east-1"
    },
    "queue": {
      "status": "healthy",
      "response_time_ms": 3.21,
      "queue_sizes": {
        "high": 0,
        "agents": 5,
        "textract": 2,
        "default": 10,
        "low": 3
      },
      "total_pending": 20,
      "failed_jobs": 0
    }
  },
  "metrics": {
    "response_time_ms": 15.42,
    "memory_usage_mb": 45.23,
    "peak_memory_mb": 52.18,
    "uptime_seconds": 86400
  },
  "version": "1.0.0"
}
```

### Response (503 Service Unavailable - Unhealthy)

Returns same structure but with `"status": "unhealthy"` and error details for failing services.

### Usage

```bash
# Check health
curl http://localhost/api/health

# Monitor continuously
watch -n 5 curl -s http://localhost/api/health | jq '.status'

# External monitoring (UptimeRobot, Pingdom, etc.)
# Configure to check: https://your-domain.com/api/health
# Expected: HTTP 200 + "status": "healthy"
```

---

## 2. Application Performance Monitoring

### APM Middleware

Automatically tracks all HTTP requests:
- Response time
- Memory usage
- Database query count
- Status codes

### Performance Headers (Debug Mode)

When `APP_DEBUG=true`:
```
X-Response-Time: 45.23ms
X-Query-Count: 3
X-Memory-Usage: 2.5 MB
```

### Metrics Storage

Recent requests cached for real-time monitoring:
```bash
php artisan tinker

# Get recent requests
>>> cache()->get('metrics:recent_requests');

# Get hourly aggregates
>>> cache()->get('metrics:aggregates:' . now()->format('Y-m-d-H'));
```

### Slow Request Logging

Requests > 1 second logged to `performance.log`:
```
[2025-11-09 12:00:00] performance.WARNING: Slow request detected
{
  "url": "/api/laws?search=kazneni",
  "method": "GET",
  "duration_ms": 1234.56,
  "memory_mb": 5.67,
  "status": 200,
  "user_id": 1,
  "ip": "192.168.1.1"
}
```

---

## 3. Multi-Channel Logging

### Log Channels

| Channel | Purpose | Retention | Path |
|---------|---------|-----------|------|
| `daily` | General application logs | 14 days | `storage/logs/laravel.log` |
| `performance` | Slow requests, performance issues | 7 days | `storage/logs/performance.log` |
| `queue` | Queue job execution | 7 days | `storage/logs/queue.log` |
| `security` | Security events, auth failures | 30 days | `storage/logs/security.log` |
| `api` | API requests/responses | 7 days | `storage/logs/api.log` |
| `database` | Database queries | 3 days | `storage/logs/database.log` |
| `monitoring` | System monitoring events | 14 days | `storage/logs/monitoring.log` |
| `agents` | AI agent execution | 14 days | `storage/logs/agents.log` |

### Usage

```php
// Log to specific channel
Log::channel('performance')->warning('Slow query detected', ['duration' => 2000]);
Log::channel('security')->alert('Failed login attempt', ['ip' => $request->ip()]);
Log::channel('queue')->info('Job completed', ['job' => ProcessPdf::class]);
Log::channel('agents')->info('Research completed', ['topic' => $topic]);
```

### Configuration

Environment variables:
```env
LOG_CHANNEL=stack
LOG_LEVEL=info

# Per-channel levels
LOG_PERFORMANCE_LEVEL=info
LOG_QUEUE_LEVEL=info
LOG_SECURITY_LEVEL=warning
LOG_API_LEVEL=info
LOG_DATABASE_LEVEL=debug
LOG_MONITORING_LEVEL=info
LOG_AGENTS_LEVEL=info
```

---

## 4. Log Rotation

### Setup

```bash
# Copy logrotate config
sudo cp docs/server-config/logrotate/ai-legal-war-machine /etc/logrotate.d/

# Set permissions
sudo chmod 644 /etc/logrotate.d/ai-legal-war-machine

# Test configuration
sudo logrotate -d /etc/logrotate.d/ai-legal-war-machine

# Force rotation (for testing)
sudo logrotate -f /etc/logrotate.d/ai-legal-war-machine
```

### Rotation Schedule

- **Daily rotation** for all logs
- **Automatic compression** (gzip)
- **Delayed compression** (keeps most recent backup uncompressed)
- **Date-based naming** (e.g., `laravel-2025-11-09.log`)

### Retention Policies

- General logs: 14 days
- Performance/Queue/API: 7 days
- Database logs: 3 days (can be large)
- Security logs: 30 days (uncompressed for easier searching)

### View Rotated Logs

```bash
# List rotated logs
ls -lah /var/www/ai-legal-war-machine/storage/logs/

# View compressed log
zcat storage/logs/laravel-2025-11-08.log.gz | less

# Uncompress if needed
gunzip storage/logs/laravel-2025-11-08.log.gz
```

---

## 5. Log Analysis

### Analyze Logs Command

```bash
# Show statistics
php artisan logs:analyze --stats

# Analyze specific channel
php artisan logs:analyze --channel=performance --stats

# Show only errors
php artisan logs:analyze --errors --tail=50

# Show entries since specific time
php artisan logs:analyze --since="1 hour ago"
php artisan logs:analyze --since="2025-11-08"

# Show warnings
php artisan logs:analyze --warnings --tail=100

# Analyze queue logs
php artisan logs:analyze --channel=queue --stats
```

### Output Example

```
📊 Log Statistics

+-------------+-------+------------+
| Level       | Count | Percentage |
+-------------+-------+------------+
| Total Lines | 10000 | 100%       |
| EMERGENCY   | 0     | 0%         |
| ALERT       | 2     | 0.02%      |
| CRITICAL    | 5     | 0.05%      |
| ERROR       | 45    | 0.45%      |
| WARNING     | 120   | 1.2%       |
| NOTICE      | 230   | 2.3%       |
| INFO        | 8598  | 85.98%     |
| DEBUG       | 1000  | 10%        |
+-------------+-------+------------+

🔴 Recent Errors (last 10):
  [12x] SQLSTATE[HY000]: General error: Connection timed out
  [5x] OpenAI API rate limit exceeded
  [3x] Neo4j connection refused

⏱️  Slow Requests (last 10):
  /api/laws?search=kazneni - 1234ms
  /api/decisions/search - 2100ms

🏥 Health Assessment:
  ✅ Healthy - Error rate: 0.5%
```

### Manual Log Analysis

```bash
# Find errors in last hour
grep -i "ERROR" storage/logs/laravel.log | tail -50

# Count errors by type
grep -i "ERROR" storage/logs/laravel.log | cut -d':' -f4 | sort | uniq -c | sort -rn

# Find slow requests
grep "Slow request" storage/logs/performance.log | tail -20

# Monitor live logs
tail -f storage/logs/laravel.log

# Monitor multiple logs
tail -f storage/logs/{laravel,performance,queue}.log
```

---

## 6. Error Rate Monitoring

### Real-Time Monitoring

```php
// In HealthController or monitoring dashboard

$recentRequests = cache()->get('metrics:recent_requests', []);

$totalRequests = count($recentRequests);
$errorRequests = collect($recentRequests)->filter(fn($r) => $r['status'] >= 500)->count();

$errorRate = $totalRequests > 0 ? ($errorRequests / $totalRequests) * 100 : 0;

if ($errorRate > 5) {
    // Alert: High error rate
}
```

### Alerts

Set up alerts when error rate exceeds thresholds:

```bash
# Add to cron (every 5 minutes)
*/5 * * * * /var/www/ai-legal-war-machine/scripts/check-error-rate.sh
```

---

## 7. External Monitoring Integration

### UptimeRobot / Pingdom

Configure HTTP(s) monitor:
- URL: `https://your-domain.com/api/health`
- Interval: 5 minutes
- Expected: HTTP 200
- Expected keyword: `"healthy"`
- Alert on: HTTP 503 or keyword not found

### Slack Notifications

Add to `.env`:
```env
LOG_SLACK_WEBHOOK_URL=https://hooks.slack.com/services/YOUR/WEBHOOK/URL
LOG_SLACK_USERNAME="AI Legal War Machine"
LOG_SLACK_EMOJI=":robot_face:"
```

Configure in `config/logging.php`:
```php
'slack' => [
    'driver' => 'slack',
    'url' => env('LOG_SLACK_WEBHOOK_URL'),
    'username' => env('LOG_SLACK_USERNAME', 'Laravel Log'),
    'emoji' => env('LOG_SLACK_EMOJI', ':boom:'),
    'level' => env('LOG_LEVEL', 'critical'),
],
```

Use in code:
```php
Log::channel('slack')->critical('Database connection failed', [
    'service' => 'postgresql',
    'error' => $exception->getMessage(),
]);
```

---

## 8. Monitoring Checklist

### Daily Checks

- [ ] Check health endpoint: `curl http://localhost/api/health | jq`
- [ ] Review error logs: `php artisan logs:analyze --errors --since="1 day ago"`
- [ ] Check queue sizes: `scripts/monitor-queues.sh`
- [ ] Monitor failed jobs: `php artisan queue:failed`

### Weekly Checks

- [ ] Analyze performance: `php artisan logs:analyze --channel=performance --stats`
- [ ] Review slow queries: `grep "Slow request" storage/logs/performance.log`
- [ ] Check disk space: `df -h`
- [ ] Review log sizes: `du -sh storage/logs/*.log`

### Monthly Checks

- [ ] Analyze security logs: `php artisan logs:analyze --channel=security --stats`
- [ ] Review and rotate old backups
- [ ] Update monitoring thresholds based on trends
- [ ] Test alert systems

---

## 9. Troubleshooting

### High Error Rate

```bash
# Find most common errors
php artisan logs:analyze --errors --stats

# Check specific service
curl http://localhost/api/health | jq '.services.database'

# Review recent errors
tail -100 storage/logs/laravel.log | grep ERROR
```

### Slow Performance

```bash
# Check slow requests
php artisan logs:analyze --channel=performance --tail=50

# Monitor real-time
tail -f storage/logs/performance.log

# Check database
php artisan logs:analyze --channel=database --since="1 hour ago"
```

### Disk Space Issues

```bash
# Check log sizes
du -sh storage/logs/*.log | sort -rh

# Manually rotate logs
sudo logrotate -f /etc/logrotate.d/ai-legal-war-machine

# Clear old logs
find storage/logs -name "*.log.*" -mtime +30 -delete
```

---

## 10. Best Practices

1. **Monitor the health endpoint** - Set up external monitoring (UptimeRobot, Pingdom)
2. **Review logs daily** - Use `php artisan logs:analyze --stats`
3. **Set up alerts** - Slack for critical errors, email for warnings
4. **Keep logs clean** - Logrotate handles this automatically
5. **Track trends** - Weekly error rate analysis
6. **Test alerts** - Monthly test of notification systems
7. **Document incidents** - Keep runbook updated
8. **Automate checks** - Cron jobs for monitoring scripts

---

## Acceptance Criteria

Sprint 11 Complete:

- [x] Health check endpoint available at `/api/health`
- [x] APM middleware tracking all requests
- [x] 8 log channels configured and working
- [x] Logrotate configured (14-day retention)
- [x] Log analysis command available
- [x] Error rate monitoring implemented
- [x] Uptime tracking implemented
- [x] External monitoring ready (health endpoint)
- [x] Documentation complete

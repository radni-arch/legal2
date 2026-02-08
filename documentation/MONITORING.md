# Monitoring & Alerting System

Comprehensive monitoring and alerting system for the AI Legal War Machine application.

## Overview

The monitoring system provides:
- **Performance Metrics**: Track response times, query counts, memory usage
- **Error Rate Monitoring**: Monitor and alert on high error rates
- **Alerting System**: Multi-channel alerts for critical failures
- **Health Checks**: API endpoints for load balancer health checks
- **Dashboard**: Real-time monitoring dashboard

---

## Table of Contents

1. [Architecture](#architecture)
2. [Components](#components)
3. [Metrics Collection](#metrics-collection)
4. [Alert System](#alert-system)
5. [API Endpoints](#api-endpoints)
6. [Configuration](#configuration)
7. [Usage Examples](#usage-examples)
8. [Best Practices](#best-practices)

---

## Architecture

```
┌─────────────────┐
│  Application    │
│    Request      │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  Performance    │◄─── Middleware
│   Monitoring    │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ Metrics         │
│ Collector       │◄─── Cache-based storage
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ Application     │
│ Monitor         │◄─── Analyzes metrics
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ Alert           │
│ Manager         │◄─── Triggers alerts
└────────┬────────┘
         │
         ├───► Log
         ├───► Email
         └───► Slack
```

---

## Components

### 1. MetricsCollector

**Location**: `app/Services/Monitoring/MetricsCollector.php`

Collects and stores application metrics in cache.

**Features**:
- Time-series data storage
- Counter metrics
- Timing metrics
- Gauge metrics
- Automatic aggregation (min, max, avg, percentiles)
- Efficient cache-based storage

**Methods**:
```php
// Record a metric value
$metrics->record('metric.name', 123.45, ['tag' => 'value']);

// Increment a counter
$metrics->increment('counter.name', 1, ['tag' => 'value']);

// Record timing in milliseconds
$metrics->timing('operation.time', 234.56);

// Record gauge (current value)
$metrics->gauge('queue.size', 42);

// Get metric statistics
$stats = $metrics->getStats('metric.name');
// Returns: count, min, max, avg, sum, p50, p95, p99
```

### 2. ApplicationMonitor

**Location**: `app/Services/Monitoring/ApplicationMonitor.php`

Monitors application health and performance.

**Tracked Metrics**:
- HTTP request duration and count
- Error rates by status code
- Database query count and timing
- Cache hit/miss rates
- Queue job processing
- Memory usage
- Exception tracking

**Methods**:
```php
// Record HTTP request
$monitor->recordRequest($method, $uri, $status, $durationMs, $queryCount);

// Record error
$monitor->recordError($type, $code, $context);

// Record exception
$monitor->recordException($exception, $context);

// Get health status
$health = $monitor->getHealthStatus();

// Get performance report
$report = $monitor->getPerformanceReport($minutes = 60);
```

### 3. AlertManager

**Location**: `app/Services/Monitoring/AlertManager.php`

Manages alerts and notifications for critical events.

**Features**:
- Multi-channel alerts (log, email, Slack)
- Rate limiting to prevent alert fatigue
- Alert history tracking
- Configurable alert levels (critical, error, warning, info)
- Context-rich alert messages

**Methods**:
```php
// Trigger an alert
$alertManager->alert($type, $message, $context);

// Get active alerts
$active = $alertManager->getActiveAlerts();

// Get alert history
$history = $alertManager->getAlertHistory($limit = 100);

// Clear specific alert
$alertManager->clearAlert($alertType);
```

### 4. PerformanceMonitoring Middleware

**Location**: `app/Http/Middleware/PerformanceMonitoring.php`

Automatically tracks performance for all HTTP requests.

**Tracked Metrics**:
- Response time
- HTTP status codes
- Database query count
- Database query time
- Memory usage

**Features**:
- Automatic query tracking
- Performance headers in development mode
- Exception tracking
- Zero-configuration needed

---

## Metrics Collection

### Standard Metrics

#### HTTP Metrics
```
http.request.duration       - Response time in milliseconds
http.request.count          - Total request count (by method, status)
http.request.slow           - Slow request count (>2s)
http.request.queries        - Query count per request
http.request.query_time     - Total query time per request
```

#### Database Metrics
```
database.query.duration     - Query execution time
database.query.count        - Total query count
database.query.slow         - Slow query count (>1s)
```

#### Cache Metrics
```
cache.operation             - Cache operation count
cache.hit                   - Cache hit count
cache.miss                  - Cache miss count
cache.hit_rate              - Cache hit rate percentage
```

#### Queue Metrics
```
queue.job.duration          - Job processing time
queue.job.processed         - Jobs processed count
queue.job.failed            - Failed job count
```

#### Error Metrics
```
error.count                 - Error count by type and code
exception.count             - Exception count by class
```

### Custom Metrics

Add custom metrics anywhere in your code:

```php
use App\Services\Monitoring\MetricsCollector;

class YourService
{
    public function __construct(
        protected MetricsCollector $metrics
    ) {}

    public function yourMethod()
    {
        $start = microtime(true);

        // Your code here

        $duration = (microtime(true) - $start) * 1000;
        $this->metrics->timing('your.operation.duration', $duration);
        $this->metrics->increment('your.operation.count');
    }
}
```

---

## Alert System

### Alert Types

#### Critical Alerts
- `critical_exception`: Unhandled exceptions
- `database_connection_failed`: Database connectivity issues
- `service_unavailable`: External service failures

#### Error Alerts
- `high_error_rate`: Error rate > 10%
- `high_query_count`: Query count > 50 per request
- `high_job_failure_rate`: Job failure rate > 20%

#### Warning Alerts
- `slow_request`: Request time > 2 seconds
- `slow_query`: Query time > 1 second
- `cache_miss_rate_high`: Cache hit rate < 50%

### Alert Channels

#### Log
All alerts are logged with appropriate severity levels.

```php
[ALERT:high_error_rate] High error rate detected: 15.5%
```

#### Email
Critical and error alerts are sent via email.

**Configuration**:
```bash
MONITORING_ALERT_EMAIL=admin@example.com,ops@example.com
```

#### Slack
Critical alerts are sent to Slack via webhooks.

**Configuration**:
```bash
MONITORING_SLACK_WEBHOOK=https://hooks.slack.com/services/YOUR/WEBHOOK/URL
```

**Slack Message Format**:
```
[critical] Application Alert

Type: high_error_rate
Time: 2025-10-31 15:30:00
Message: High error rate detected: 15.5%

Context: {...}
```

### Rate Limiting

Alerts are rate-limited to prevent fatigue:
- Default: 5 minutes between same alert type
- Configurable per alert type
- Rate limit state stored in cache

---

## API Endpoints

### Health Check

**GET** `/api/monitoring/health`

Returns application health status.

**Response**:
```json
{
    "status": "healthy",
    "timestamp": "2025-10-31T15:30:00Z",
    "metrics": {
        "error_rate": 2.5,
        "avg_response_time": 245.67,
        "slow_requests": 3,
        "query_count": 12.5,
        "queue_size": 5,
        "cache_hit_rate": 85.3
    },
    "alerts": []
}
```

**Status Codes**:
- `200`: Healthy
- `503`: Degraded or Critical

### Performance Metrics

**GET** `/api/monitoring/performance?minutes=60`

Get detailed performance report.

**Response**:
```json
{
    "success": true,
    "data": {
        "period_minutes": 60,
        "http": {
            "total_requests": 1523,
            "response_time": {
                "avg": 245.67,
                "p50": 189.23,
                "p95": 567.89,
                "p99": 1234.56
            },
            "slow_requests": 12,
            "errors": 38
        },
        "database": {
            "total_queries": 18942,
            "query_time": {...},
            "slow_queries": 5
        },
        "cache": {
            "operations": 5432,
            "hits": 4623,
            "misses": 809,
            "hit_rate": 85.1
        },
        "queue": {
            "processed": 234,
            "failed": 3,
            "avg_duration": 1234.56
        }
    }
}
```

### Specific Metric

**GET** `/api/monitoring/metrics/{metric}?tags[key]=value`

Get data for a specific metric.

**Example**: `/api/monitoring/metrics/http.request.duration?tags[method]=GET`

**Response**:
```json
{
    "success": true,
    "metric": "http.request.duration",
    "tags": {"method": "GET"},
    "data": [
        {"value": 234.56, "timestamp": 1698765432},
        {"value": 189.23, "timestamp": 1698765433}
    ],
    "stats": {
        "count": 150,
        "min": 45.23,
        "max": 1234.56,
        "avg": 245.67,
        "p50": 189.23,
        "p95": 567.89,
        "p99": 1234.56
    }
}
```

### Alerts

**GET** `/api/monitoring/alerts?limit=50`

Get active alerts and history.

**Response**:
```json
{
    "success": true,
    "active_count": 2,
    "active": [
        {
            "type": "high_error_rate",
            "message": "High error rate detected: 15.5%",
            "level": "error",
            "timestamp": "2025-10-31T15:30:00Z",
            "context": {...}
        }
    ],
    "history": [...]
}
```

**DELETE** `/api/monitoring/alerts/{type}`

Clear specific alert.

**DELETE** `/api/monitoring/alerts`

Clear all alerts.

### Response Times

**GET** `/api/monitoring/response-times`

Get response time percentiles.

**Response**:
```json
{
    "success": true,
    "data": {
        "avg": 245.67,
        "min": 23.45,
        "max": 3456.78,
        "p50": 189.23,
        "p95": 567.89,
        "p99": 1234.56
    },
    "unit": "milliseconds"
}
```

### Error Rate

**GET** `/api/monitoring/error-rate?minutes=60`

Get error rate over time.

**Response**:
```json
{
    "success": true,
    "period_minutes": 60,
    "data": [
        {
            "timestamp": 1698765000,
            "time": "2025-10-31 15:30:00",
            "error_rate": 2.5,
            "errors": 5,
            "requests": 200
        }
    ]
}
```

### System Metrics

**GET** `/api/monitoring/system`

Get system-level metrics.

**Response**:
```json
{
    "success": true,
    "data": {
        "php": {
            "version": "8.2.12",
            "memory_limit": "256M",
            "memory_usage": "45.23 MB",
            "peak_memory": "78.45 MB"
        },
        "database": {
            "driver": "pgsql",
            "database": "legal_db",
            "connected": true
        },
        "cache": {
            "driver": "redis",
            "hits": 4623,
            "misses": 809,
            "hit_rate": 85.1
        },
        "queue": {
            "driver": "redis",
            "size": 5,
            "failed_jobs": 3
        }
    }
}
```

---

## Configuration

### Environment Variables

```bash
# Enable/disable monitoring
MONITORING_ENABLED=true

# Performance thresholds
MONITORING_SLOW_REQUEST_MS=2000
MONITORING_SLOW_QUERY_MS=1000
MONITORING_HIGH_QUERY_COUNT=50
MONITORING_HIGH_ERROR_RATE=10

# Alert channels
MONITORING_ALERT_EMAIL=admin@example.com,ops@example.com
MONITORING_SLACK_WEBHOOK=https://hooks.slack.com/services/...

# Rate limiting
MONITORING_ALERT_RATE_LIMIT=300

# Metrics storage
MONITORING_METRICS_TTL=3600
MONITORING_MAX_DATA_POINTS=1000

# Middleware
MONITORING_MIDDLEWARE_ENABLED=true
MONITORING_ADD_HEADERS=true

# Feature toggles
MONITORING_DATABASE_ENABLED=true
MONITORING_QUEUE_ENABLED=true
MONITORING_CACHE_ENABLED=true
```

### Configuration File

Full configuration in `config/monitoring.php`:

```php
return [
    'enabled' => env('MONITORING_ENABLED', true),
    'thresholds' => [...],
    'alert_channels' => [...],
    'metrics' => [...],
    // ... more configuration
];
```

---

## Usage Examples

### Basic Setup

1. **Register Middleware** (in `bootstrap/app.php`):

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->append(\App\Http\Middleware\PerformanceMonitoring::class);
})
```

2. **Add Routes** (in `routes/api.php`):

```php
Route::prefix('monitoring')->group(function () {
    Route::get('health', [MonitoringController::class, 'health']);
    Route::get('performance', [MonitoringController::class, 'performance']);
    Route::get('metrics/{metric}', [MonitoringController::class, 'metric']);
    Route::get('alerts', [MonitoringController::class, 'alerts']);
    Route::delete('alerts/{type}', [MonitoringController::class, 'clearAlert']);
    Route::delete('alerts', [MonitoringController::class, 'clearAllAlerts']);
    Route::get('system', [MonitoringController::class, 'system']);
    Route::get('error-rate', [MonitoringController::class, 'errorRate']);
    Route::get('response-times', [MonitoringController::class, 'responseTimes']);
});
```

### Tracking Custom Operations

```php
use App\Services\Monitoring\ApplicationMonitor;
use App\Services\Monitoring\MetricsCollector;

class DocumentProcessor
{
    public function __construct(
        protected MetricsCollector $metrics,
        protected ApplicationMonitor $monitor
    ) {}

    public function process(Document $document)
    {
        $start = microtime(true);

        try {
            // Your processing logic
            $result = $this->doProcessing($document);

            // Record success
            $duration = (microtime(true) - $start) * 1000;
            $this->metrics->timing('document.processing.duration', $duration);
            $this->metrics->increment('document.processing.success');

            return $result;
        } catch (\Exception $e) {
            // Record failure
            $this->metrics->increment('document.processing.failed');
            $this->monitor->recordException($e, [
                'document_id' => $document->id,
            ]);

            throw $e;
        }
    }
}
```

### Manual Alerts

```php
use App\Services\Monitoring\AlertManager;

class CriticalOperation
{
    public function __construct(
        protected AlertManager $alertManager
    ) {}

    public function execute()
    {
        if ($this->detectCriticalCondition()) {
            $this->alertManager->alert(
                'critical_condition_detected',
                'Critical condition detected in operation',
                [
                    'details' => $this->getDetails(),
                    'timestamp' => now(),
                ]
            );
        }
    }
}
```

### Queue Job Monitoring

Queue jobs are automatically monitored when using the middleware, but you can add custom tracking:

```php
use App\Services\Monitoring\ApplicationMonitor;

class ProcessDocument implements ShouldQueue
{
    public function handle(ApplicationMonitor $monitor)
    {
        $start = microtime(true);

        try {
            // Job logic
            $this->processDocument();

            // Record success
            $duration = (microtime(true) - $start) * 1000;
            $monitor->recordJobProcessed(static::class, $duration, false);
        } catch (\Exception $e) {
            // Record failure
            $duration = (microtime(true) - $start) * 1000;
            $monitor->recordJobProcessed(static::class, $duration, true);

            throw $e;
        }
    }
}
```

---

## Best Practices

### 1. Metric Naming

Use consistent naming conventions:
- **Namespace**: Group related metrics (`http.*`, `database.*`, `queue.*`)
- **Action**: Use verbs for operations (`process`, `count`, `duration`)
- **Clarity**: Be specific (`http.request.duration` not `request.time`)

### 2. Tags

Use tags for dimensionality:
```php
$metrics->timing('api.response', $duration, [
    'endpoint' => 'users',
    'method' => 'GET',
    'status' => 200,
]);
```

### 3. Alert Thresholds

Set appropriate thresholds:
- Start conservative (higher thresholds)
- Adjust based on actual data
- Consider time-of-day variations
- Use percentiles (p95, p99) for response times

### 4. Alert Fatigue Prevention

- Use rate limiting
- Group similar alerts
- Implement escalation policies
- Auto-resolve transient issues

### 5. Performance Impact

- Monitoring has minimal overhead (<1ms per request)
- Uses cache for fast writes
- Asynchronous alert sending
- Can be disabled per environment

### 6. Testing Monitoring

```php
/** @test */
public function test_slow_request_triggers_alert()
{
    $monitor = app(ApplicationMonitor::class);
    $alertManager = app(AlertManager::class);

    // Record slow request
    $monitor->recordRequest('GET', '/api/test', 200, 3000); // 3 seconds

    // Assert alert was triggered
    $active = $alertManager->getActiveAlerts();
    $this->assertArrayHasKey('slow_request', $active);
}
```

### 7. Production Monitoring

In production:
- Enable all monitoring features
- Configure alert email/Slack
- Set up regular metric cleanup
- Monitor the monitoring system itself
- Use external health checks

### 8. Development Monitoring

In development:
- Enable performance headers
- Log all metrics
- Lower alert thresholds for testing
- Disable external notifications

---

## Troubleshooting

### High Memory Usage

Metrics are stored in cache. If memory usage is high:
1. Reduce `MONITORING_METRICS_TTL`
2. Reduce `MONITORING_MAX_DATA_POINTS`
3. Run cleanup more frequently

### Missing Metrics

Check:
1. Is `MONITORING_ENABLED=true`?
2. Is middleware registered?
3. Is cache working properly?
4. Check logs for errors

### Alerts Not Sent

Check:
1. Are alert channels configured?
2. Is email/Slack configured correctly?
3. Check rate limiting (might be suppressed)
4. Check alert levels match channels

### Performance Impact

If monitoring impacts performance:
1. Disable in testing environment
2. Sample requests (monitor every Nth request)
3. Use Redis for cache (faster than file cache)
4. Increase cleanup interval

---

## Future Enhancements

1. **Graphical Dashboard**: Web UI for real-time metrics
2. **Metric Persistence**: Store metrics in database for long-term analysis
3. **Advanced Alerting**: Anomaly detection, ML-based thresholds
4. **Distributed Tracing**: Request tracing across services
5. **Custom Dashboards**: User-configurable monitoring views
6. **Integration**: Prometheus, Datadog, New Relic exporters
7. **Metric Aggregation**: Hourly, daily, monthly rollups

---

## References

- [Laravel Performance Monitoring](https://laravel.com/docs/11.x/monitoring)
- [The Four Golden Signals](https://sre.google/sre-book/monitoring-distributed-systems/)
- [Site Reliability Engineering](https://sre.google/books/)
- [Monitoring Best Practices](https://docs.datadoghq.com/monitors/guide/best-practices/)

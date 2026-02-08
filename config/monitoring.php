<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Monitoring Enabled
    |--------------------------------------------------------------------------
    |
    | Enable or disable the monitoring system. When disabled, metrics are
    | not collected and alerts are not sent.
    |
    */

    'enabled' => env('MONITORING_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Performance Thresholds (Legacy)
    |--------------------------------------------------------------------------
    |
    | Define thresholds for various performance metrics. When thresholds
    | are exceeded, alerts will be triggered.
    |
    */

    'thresholds' => [
        // Response time in milliseconds
        'slow_request_ms' => env('MONITORING_SLOW_REQUEST_MS', 2000),

        // Query time in milliseconds
        'slow_query_ms' => env('MONITORING_SLOW_QUERY_MS', 1000),

        // Query count per request
        'high_query_count' => env('MONITORING_HIGH_QUERY_COUNT', 50),

        // Error rate percentage
        'high_error_rate_percent' => env('MONITORING_HIGH_ERROR_RATE', 10),

        // Job failure rate percentage
        'high_job_failure_rate_percent' => env('MONITORING_HIGH_JOB_FAILURE_RATE', 20),

        // Cache hit rate percentage (low threshold)
        'low_cache_hit_rate_percent' => env('MONITORING_LOW_CACHE_HIT_RATE', 50),
    ],

    /*
    |--------------------------------------------------------------------------
    | Alert Thresholds
    |--------------------------------------------------------------------------
    |
    | Define metric thresholds that trigger alerts when exceeded.
    | Used by ProductionMonitor service.
    |
    */

    'alert_thresholds' => [
        // Performance thresholds
        'api.response_time' => env('MONITORING_ALERT_RESPONSE_TIME', 3000), // ms
        'database.query_time' => env('MONITORING_ALERT_QUERY_TIME', 1000), // ms
        'cache.miss_rate' => env('MONITORING_ALERT_CACHE_MISS_RATE', 50), // percentage

        // Error rate thresholds
        'errors.rate' => env('MONITORING_ALERT_ERROR_RATE', 5), // percentage
        'errors.5xx_rate' => env('MONITORING_ALERT_5XX_RATE', 1), // percentage

        // Resource thresholds
        'memory.usage' => env('MONITORING_ALERT_MEMORY', 80), // percentage
        'queue.failed_jobs' => env('MONITORING_ALERT_FAILED_JOBS', 10), // count
        'queue.wait_time' => env('MONITORING_ALERT_QUEUE_WAIT', 300), // seconds

        // Agent-specific thresholds
        'agent.cost_per_run' => env('MONITORING_ALERT_AGENT_COST', 5.00), // USD
        'agent.token_usage' => env('MONITORING_ALERT_AGENT_TOKENS', 100000), // tokens
        'agent.execution_time' => env('MONITORING_ALERT_AGENT_TIME', 600), // seconds

        // External API thresholds
        'api.openai.latency' => env('MONITORING_ALERT_OPENAI_LATENCY', 5000), // ms
        'api.odluke.error_rate' => env('MONITORING_ALERT_ODLUKE_ERROR', 10), // percentage
    ],

    /*
    |--------------------------------------------------------------------------
    | Alert Notification Channels
    |--------------------------------------------------------------------------
    |
    | Configure which notification channels to use for different alert levels.
    | Available channels: log, email, slack
    |
    */

    'alert_channels' => [
        'critical' => ['log', 'email', 'slack'],
        'error' => ['log', 'email'],
        'warning' => ['log'],
        'info' => ['log'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Email Alert Recipients
    |--------------------------------------------------------------------------
    |
    | Email addresses to receive alert notifications.
    |
    */

    'alert_email' => env('MONITORING_ALERT_EMAIL')
        ? explode(',', env('MONITORING_ALERT_EMAIL'))
        : [],

    /*
    |--------------------------------------------------------------------------
    | Slack Webhook URL
    |--------------------------------------------------------------------------
    |
    | Webhook URL for sending alerts to Slack. Create one in your Slack
    | workspace settings under "Incoming Webhooks".
    |
    */

    'slack_webhook' => env('MONITORING_SLACK_WEBHOOK'),

    /*
    |--------------------------------------------------------------------------
    | Alert Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Prevent alert fatigue by rate limiting how often the same alert type
    | can be sent. Time is in seconds.
    |
    */

    'alert_rate_limit_seconds' => env('MONITORING_ALERT_RATE_LIMIT', 300), // 5 minutes

    /*
    |--------------------------------------------------------------------------
    | Metrics Storage
    |--------------------------------------------------------------------------
    |
    | Configure how long metrics should be stored in cache.
    |
    */

    'metrics' => [
        'ttl_seconds' => env('MONITORING_METRICS_TTL', 3600), // 1 hour
        'max_data_points' => env('MONITORING_MAX_DATA_POINTS', 1000),
        'cleanup_interval_seconds' => env('MONITORING_CLEANUP_INTERVAL', 7200), // 2 hours
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Monitoring Middleware
    |--------------------------------------------------------------------------
    |
    | Configure which routes should be monitored. Use patterns to include
    | or exclude specific routes.
    |
    */

    'middleware' => [
        'enabled' => env('MONITORING_MIDDLEWARE_ENABLED', true),

        // Routes to exclude from monitoring
        'exclude_patterns' => [
            'telescope/*',
            'horizon/*',
            '_debugbar/*',
        ],

        // Add performance headers in responses (development only)
        'add_headers' => env('MONITORING_ADD_HEADERS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Query Monitoring
    |--------------------------------------------------------------------------
    |
    | Monitor database queries for performance issues.
    |
    */

    'database' => [
        'enabled' => env('MONITORING_DATABASE_ENABLED', true),
        'log_slow_queries' => env('MONITORING_LOG_SLOW_QUERIES', true),
        'slow_query_ms' => env('MONITORING_SLOW_QUERY_MS', 1000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Monitoring
    |--------------------------------------------------------------------------
    |
    | Monitor queue jobs for failures and performance.
    |
    */

    'queue' => [
        'enabled' => env('MONITORING_QUEUE_ENABLED', true),
        'alert_on_failure' => env('MONITORING_QUEUE_ALERT_FAILURE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Monitoring
    |--------------------------------------------------------------------------
    |
    | Monitor cache operations and hit rates.
    |
    */

    'cache' => [
        'enabled' => env('MONITORING_CACHE_ENABLED', true),
        'track_operations' => env('MONITORING_CACHE_TRACK_OPS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Health Check Endpoint
    |--------------------------------------------------------------------------
    |
    | Configure the health check endpoint that can be used by load balancers
    | and monitoring services.
    |
    */

    'health_check' => [
        'enabled' => env('MONITORING_HEALTH_CHECK_ENABLED', true),
        'path' => env('MONITORING_HEALTH_CHECK_PATH', '/api/monitoring/health'),

        // Return unhealthy status if any of these checks fail
        'checks' => [
            'database' => true,
            'cache' => true,
            'queue' => false,
        ],
    ],

];

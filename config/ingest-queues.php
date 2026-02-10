<?php

/**
 * Ingest Queue Configuration (SOT-018)
 *
 * Defines per-source queue names, retry policies, concurrency limits,
 * and backpressure thresholds for the ingestion pipeline.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Queue Names by Source Type
    |--------------------------------------------------------------------------
    |
    | Each ingest source type maps to a specific queue for isolation.
    | This prevents slow Odluke scraping from blocking fast /uploader ingests.
    |
    */
    'queues' => [
        'uploader' => env('INGEST_QUEUE_UPLOADER', 'ingest-upload'),
        'drive'    => env('INGEST_QUEUE_DRIVE', 'ingest-drive'),
        'api'      => env('INGEST_QUEUE_API', 'ingest-upload'),
        'odluke'   => env('INGEST_QUEUE_ODLUKE', 'ingest-odluke'),
        'usud'     => env('INGEST_QUEUE_USUD', 'ingest-usud'),
        'echr'     => env('INGEST_QUEUE_ECHR', 'ingest-echr'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Queue (fallback)
    |--------------------------------------------------------------------------
    */
    'default_queue' => env('INGEST_QUEUE_DEFAULT', 'ingest'),

    /*
    |--------------------------------------------------------------------------
    | Retry Policies by Source Type
    |--------------------------------------------------------------------------
    |
    | Each source type has its own retry parameters tuned for its
    | typical failure modes and acceptable latency.
    |
    | max_tries: Maximum job attempts before permanent failure
    | backoff: Array of wait times (seconds) between retries
    | retry_until_minutes: Maximum wall-clock time window for retries
    | max_exceptions: Maximum exceptions before stopping retries
    |
    */
    'retry_policies' => [
        'uploader' => [
            'max_tries' => (int) env('INGEST_RETRY_UPLOADER_MAX', 3),
            'backoff' => [60, 300, 900],
            'retry_until_minutes' => 30,
            'max_exceptions' => 2,
        ],
        'drive' => [
            'max_tries' => (int) env('INGEST_RETRY_DRIVE_MAX', 5),
            'backoff' => [120, 300, 600, 1800, 3600],
            'retry_until_minutes' => 120,
            'max_exceptions' => 3,
        ],
        'odluke' => [
            'max_tries' => (int) env('INGEST_RETRY_ODLUKE_MAX', 5),
            'backoff' => [60, 300, 600, 1800, 3600],
            'retry_until_minutes' => 180,
            'max_exceptions' => 3,
        ],
        'usud' => [
            'max_tries' => (int) env('INGEST_RETRY_USUD_MAX', 5),
            'backoff' => [60, 300, 600, 1800, 3600],
            'retry_until_minutes' => 180,
            'max_exceptions' => 3,
        ],
        'echr' => [
            'max_tries' => (int) env('INGEST_RETRY_ECHR_MAX', 3),
            'backoff' => [300, 900, 3600],
            'retry_until_minutes' => 240,
            'max_exceptions' => 2,
        ],
        'api' => [
            'max_tries' => (int) env('INGEST_RETRY_API_MAX', 3),
            'backoff' => [30, 120, 600],
            'retry_until_minutes' => 30,
            'max_exceptions' => 2,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Backpressure Thresholds
    |--------------------------------------------------------------------------
    |
    | Maximum number of pending jobs per queue before throttling kicks in.
    | When exceeded, new jobs will be delayed or rejected.
    |
    */
    'backpressure' => [
        'max_pending_per_queue' => (int) env('INGEST_BACKPRESSURE_MAX_PENDING', 500),
        'throttle_delay_seconds' => (int) env('INGEST_BACKPRESSURE_DELAY', 60),
        'check_interval_seconds' => (int) env('INGEST_BACKPRESSURE_CHECK_INTERVAL', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Concurrency Limits
    |--------------------------------------------------------------------------
    |
    | Maximum concurrent workers per queue. Used by queue worker config
    | to prevent resource exhaustion.
    |
    */
    'concurrency' => [
        'ingest-upload' => (int) env('INGEST_CONCURRENCY_UPLOAD', 4),
        'ingest-drive'  => (int) env('INGEST_CONCURRENCY_DRIVE', 2),
        'ingest-odluke' => (int) env('INGEST_CONCURRENCY_ODLUKE', 3),
        'ingest-usud'   => (int) env('INGEST_CONCURRENCY_USUD', 2),
        'ingest-echr'   => (int) env('INGEST_CONCURRENCY_ECHR', 1),
    ],
];

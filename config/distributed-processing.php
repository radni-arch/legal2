<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Queue Driver Selection
    |--------------------------------------------------------------------------
    |
    | This system supports both Redis and Database queue drivers:
    |
    | - Database (default): Perfect for localhost development, no Redis needed
    | - Redis: Recommended for production, better performance at scale
    |
    | Set QUEUE_CONNECTION in .env:
    |   - 'database' for localhost (uses jobs table in PostgreSQL)
    |   - 'redis' for production (requires Redis server)
    |
    | All features work with both drivers: priorities, retries, batches, etc.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for distributed processing queues and priorities
    |
    */

    'queues' => [
        // High priority queue for urgent documents
        'textract-high' => [
            'priority' => 100,
            'workers' => 4,
            'timeout' => 1800, // 30 minutes
            'tries' => 3,
            'backoff' => [60, 300, 900],
        ],

        // Normal priority queue (default)
        'textract' => [
            'priority' => 50,
            'workers' => 8,
            'timeout' => 1800,
            'tries' => 3,
            'backoff' => [60, 300, 900],
        ],

        // Low priority queue for batch processing
        'textract-low' => [
            'priority' => 10,
            'workers' => 2,
            'timeout' => 3600, // 1 hour
            'tries' => 2,
            'backoff' => [300, 1800],
        ],

        // Table extraction queue
        'textract-tables' => [
            'priority' => 30,
            'workers' => 4,
            'timeout' => 600, // 10 minutes
            'tries' => 2,
            'backoff' => [60, 300],
        ],

        // Embedding generation queue
        'embeddings' => [
            'priority' => 20,
            'workers' => 6,
            'timeout' => 900, // 15 minutes
            'tries' => 2,
            'backoff' => [120, 600],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Batch Processing
    |--------------------------------------------------------------------------
    |
    | Configuration for batch processing operations
    |
    */

    'batch' => [
        // Maximum batch size
        'max_size' => 1000,

        // Batch timeout (hours)
        'timeout_hours' => 24,

        // Auto-create batches for folder processing
        'auto_batch' => true,

        // Batch statistics retention (days)
        'retention_days' => 90,
    ],

    /*
    |--------------------------------------------------------------------------
    | Worker Scaling
    |--------------------------------------------------------------------------
    |
    | Configuration for horizontal worker scaling
    |
    */

    'scaling' => [
        // Enable auto-scaling
        'enabled' => env('DISTRIBUTED_SCALING_ENABLED', false),

        // Minimum workers per queue
        'min_workers' => 2,

        // Maximum workers per queue
        'max_workers' => 20,

        // Scale up when queue size exceeds this threshold
        'scale_up_threshold' => 100,

        // Scale down when queue size is below this threshold
        'scale_down_threshold' => 10,

        // Scaling check interval (seconds)
        'check_interval' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Monitoring
    |--------------------------------------------------------------------------
    |
    | Configuration for performance tracking and optimization
    |
    */

    'monitoring' => [
        // Enable performance metrics collection
        'enabled' => true,

        // Store detailed metrics in database
        'store_metrics' => true,

        // Metrics aggregation interval (seconds)
        'aggregation_interval' => 300, // 5 minutes

        // Alert thresholds
        'alerts' => [
            'high_failure_rate' => 0.2, // 20%
            'slow_processing' => 1800, // 30 minutes per job
            'queue_backup' => 500, // jobs in queue
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Textract Configuration
    |--------------------------------------------------------------------------
    |
    | Enhanced Textract processing configuration
    |
    */

    'textract' => [
        // Auto-generate embeddings after Textract completion
        'auto_generate_embeddings' => env('TEXTRACT_AUTO_EMBEDDINGS', true),

        // Auto-extract tables
        'auto_extract_tables' => env('TEXTRACT_AUTO_TABLES', true),

        // Table extraction confidence threshold
        'table_confidence_threshold' => 80.0,

        // Maximum pages per job
        'max_pages_per_job' => 100,

        // S3 storage configuration
        's3' => [
            'input_prefix' => env('S3_INPUT_PREFIX', 'textract/input'),
            'output_prefix' => env('S3_OUTPUT_PREFIX', 'textract/output'),
            'json_prefix' => env('S3_JSON_PREFIX', 'textract/json'),
            'tables_prefix' => env('S3_TABLES_PREFIX', 'textract/tables'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Embedding Generation
    |--------------------------------------------------------------------------
    |
    | Configuration for batch embedding generation
    |
    */

    'embeddings' => [
        // Batch size for embedding generation
        'batch_size' => 50,

        // Embedding model
        'model' => env('EMBEDDING_MODEL', 'text-embedding-3-small'),

        // Chunk size for text splitting
        'chunk_size' => 1500,

        // Chunk overlap
        'chunk_overlap' => 200,

        // Cost tracking
        'cost_per_1k_tokens' => 0.00002, // text-embedding-3-small
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Configuration
    |--------------------------------------------------------------------------
    |
    | Redis-specific queue configuration
    |
    */

    'redis' => [
        // Redis connection for queues
        'connection' => env('QUEUE_REDIS_CONNECTION', 'default'),

        // Queue key prefix
        'prefix' => env('QUEUE_PREFIX', 'queues'),

        // Failed jobs retention (days)
        'failed_retention_days' => 30,

        // Queue timeout (seconds)
        'timeout' => 90,
    ],

];

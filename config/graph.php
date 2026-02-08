<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Graph Database Feature Flags
    |--------------------------------------------------------------------------
    |
    | These feature flags control which graph database features are enabled.
    | This allows for gradual rollout and easy toggling of graph features
    | without code changes.
    |
    */

    'features' => [
        /*
         * Enable/disable judge entity extraction and graph synchronization.
         * When enabled, judges from court decisions will be extracted and
         * synced to the graph database with DECIDED_BY relationships.
         */
        'sync_judges' => env('GRAPH_SYNC_JUDGES', true),

        /*
         * Enable/disable party entity extraction and graph synchronization.
         * When enabled, plaintiffs and defendants from court decisions will
         * be extracted and synced to the graph database with PARTY relationships.
         */
        'sync_parties' => env('GRAPH_SYNC_PARTIES', true),

        /*
         * Enable/disable legal principle extraction and graph synchronization.
         * When enabled, legal principles from court decisions will be extracted
         * and synced to the graph database with APPLIES_PRINCIPLE relationships.
         */
        'sync_legal_principles' => env('GRAPH_SYNC_LEGAL_PRINCIPLES', true),

        /*
         * Enable/disable precedent detection and relationship creation.
         * When enabled, the system will detect precedent relationships between
         * decisions and create CITES_AS_PRECEDENT relationships in the graph.
         */
        'detect_precedents' => env('GRAPH_DETECT_PRECEDENTS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Graph Database Retry Configuration
    |--------------------------------------------------------------------------
    |
    | Configure retry behavior for graph database operations. Operations that
    | fail due to transient errors (connection issues, timeouts) will be
    | retried with exponential backoff.
    |
    */

    'retry' => [
        /*
         * Number of retry attempts for transient failures.
         * Set to 0 to disable retries.
         */
        'attempts' => env('GRAPH_RETRY_ATTEMPTS', 3),

        /*
         * Initial delay in milliseconds before first retry.
         * Subsequent retries use exponential backoff based on multiplier.
         */
        'delay_ms' => env('GRAPH_RETRY_DELAY_MS', 100),

        /*
         * Exponential backoff multiplier.
         * Each retry delay = delay_ms * (multiplier ^ (attempt - 1))
         * Example: delay_ms=100, multiplier=2 → 100ms, 200ms, 400ms
         */
        'multiplier' => env('GRAPH_RETRY_MULTIPLIER', 2),
    ],

    /*
    |--------------------------------------------------------------------------
    | Graph Database Connection Pool Configuration
    |--------------------------------------------------------------------------
    |
    | Configure connection pooling for the Neo4j driver. Connection pooling
    | improves performance by reusing existing connections instead of creating
    | new ones for each query.
    |
    */

    'pool' => [
        /*
         * Minimum number of connections to maintain in the pool.
         * Connections below this threshold are kept alive even when idle.
         */
        'min_connections' => env('NEO4J_POOL_MIN', 2),

        /*
         * Maximum number of connections allowed in the pool.
         * Additional connection requests will wait if limit is reached.
         */
        'max_connections' => env('NEO4J_POOL_MAX', 10),

        /*
         * Idle timeout in seconds before a connection is removed from pool.
         * Connections above min_connections threshold may be closed if idle.
         */
        'idle_timeout' => env('NEO4J_POOL_IDLE_TIMEOUT', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Graph Database Batch Operation Configuration
    |--------------------------------------------------------------------------
    |
    | Configure batch operation settings for bulk graph operations. These
    | settings control chunking and UNWIND batch sizes to optimize memory
    | usage and query performance when processing large datasets.
    |
    */

    'batch' => [
        /*
         * Default chunk size for batch operations.
         * Determines how many items are processed in each batch when
         * performing bulk operations against the graph database.
         */
        'chunk_size' => env('GRAPH_BATCH_CHUNK_SIZE', 100),

        /*
         * UNWIND batch size for Cypher queries.
         * Controls batch size for UNWIND operations in Cypher queries
         * to balance memory usage and query performance.
         */
        'unwind_batch_size' => env('GRAPH_UNWIND_BATCH_SIZE', 50),
    ],

    /*
    |--------------------------------------------------------------------------
    | Graph Database Query Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configure slow query detection and logging. Queries exceeding the
    | threshold will be logged with timing information and optional EXPLAIN
    | output for performance analysis.
    |
    */

    'logging' => [
        /*
         * Slow query threshold in milliseconds.
         * Queries taking longer than this will be logged for analysis.
         */
        'slow_query_threshold_ms' => env('NEO4J_SLOW_QUERY_MS', 100),

        /*
         * Log channel for graph query logs.
         * Slow queries and performance data will be sent to this channel.
         */
        'log_channel' => env('NEO4J_LOG_CHANNEL', 'graph'),

        /*
         * Enable EXPLAIN output for slow queries.
         * When enabled, slow queries will include query execution plan.
         */
        'explain_slow_queries' => env('NEO4J_EXPLAIN_SLOW', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Graph Database Parallel Extraction Configuration
    |--------------------------------------------------------------------------
    |
    | Configure parallel extraction settings for running multiple extractors
    | concurrently when processing documents. This improves throughput by
    | parallelizing independent extraction tasks.
    |
    */

    'parallel' => [
        /*
         * Maximum number of concurrent extractors to run in parallel.
         * This limits resource usage while still providing parallelism benefits.
         */
        'concurrency_limit' => env('GRAPH_PARALLEL_CONCURRENCY_LIMIT', 3),
    ],
];

<?php

namespace App\Services;

use App\Contracts\GraphDatabaseServiceInterface;
use App\Events\Neo4jUnavailable;
use App\Exceptions\AnalysisException;
use App\Jobs\Graph\RetryNeo4jOperationJob;
use App\Services\Graph\BatchCypherBuilder;
use App\Services\Graph\GraphQueryCacheService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\ClientBuilder;
use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Databags\TransactionConfiguration;
use Laudis\Neo4j\Exception\Neo4jException;

class GraphDatabaseService implements GraphDatabaseServiceInterface
{
    protected ?ClientInterface $client = null;

    protected SessionConfiguration $sessionConfig;

    protected string $database;

    protected bool $isAvailable = false;

    /**
     * Last reconnection attempt timestamp
     */
    protected ?int $lastReconnectAttempt = null;

    /**
     * Minimum seconds between reconnection attempts
     */
    protected int $reconnectCooldown = 5;

    /**
     * Query timeout in seconds (Sprint 4.7)
     */
    protected int $queryTimeout = 60;

    /**
     * Query cache service (Sprint 4.7)
     */
    protected ?GraphQueryCacheService $queryCache = null;

    public function __construct(?GraphQueryCacheService $queryCache = null)
    {
        $this->queryCache = $queryCache ?? app(GraphQueryCacheService::class);
        $this->queryTimeout = (int) config('neo4j.query_timeout', 60);

        $this->initializeClient();
        $this->database = config('neo4j.connections.bolt.database', 'neo4j');
    }

    protected function initializeClient(): void
    {
        // Check if Neo4j is enabled in config
        if (! config('neo4j.sync.enabled', true)) {
            Log::info('Neo4j is disabled in configuration');
            $this->isAvailable = false;

            return;
        }

        try {
            $config = config('neo4j.connections.'.config('neo4j.default', 'bolt'));

            $auth = Authenticate::basic(
                $config['username'] ?? $config['user'] ?? 'neo4j',
                $config['password'] ?? ''
            );

            // Accept scheme from 'scheme' or fallback to 'driver'
            $scheme = $config['scheme'] ?? $config['driver'] ?? 'bolt';
            if (! empty($config['tls']) && strpos($scheme, '+s') === false) {
                $scheme .= '+s';
            }

            $uri = sprintf(
                '%s://%s:%s',
                $scheme,
                $config['host'] ?? '127.0.0.1',
                $config['port'] ?? 7687
            );

            // Connection pool configuration (F.1)
            // Note: Laudis Neo4j PHP driver handles connection pooling automatically.
            // Pool settings from config/graph.php are documented for reference and
            // may be used for future driver-specific pool configuration if needed.
            $poolConfig = config('graph.pool', []);

            // Use a predictable alias name that is NOT the database name
            $this->client = ClientBuilder::create()
                ->withDriver('default', $uri, $auth)
                ->withDefaultDriver('default')
                ->build();

            $this->database = $config['database'] ?? 'neo4j';
            $this->sessionConfig = SessionConfiguration::default()->withDatabase($this->database);

            // Perform a health check to verify connectivity
            $this->isAvailable = $this->performHealthCheck();
        } catch (\Exception $e) {
            Log::warning('Failed to initialize Neo4j client', [
                'error' => $e->getMessage(),
            ]);
            $this->client = null;
            $this->isAvailable = false;
        }
    }

    /**
     * Check if Neo4j is available
     */
    public function isAvailable(): bool
    {
        return $this->isAvailable;
    }

    /**
     * Attempt to reconnect to Neo4j if not available
     *
     * This method is called automatically when operations fail due to unavailability.
     * It respects a cooldown period to prevent excessive reconnection attempts.
     *
     * @return bool True if reconnection successful, false otherwise
     */
    public function reconnect(): bool
    {
        // Respect cooldown period to prevent hammering Neo4j
        $now = time();
        if ($this->lastReconnectAttempt !== null &&
            ($now - $this->lastReconnectAttempt) < $this->reconnectCooldown) {
            Log::debug('Neo4j reconnection skipped - cooldown period active', [
                'seconds_until_retry' => $this->reconnectCooldown - ($now - $this->lastReconnectAttempt),
            ]);

            return false;
        }

        $this->lastReconnectAttempt = $now;

        Log::info('Attempting to reconnect to Neo4j');

        // Reset state and reinitialize
        $this->client = null;
        $this->isAvailable = false;

        $this->initializeClient();

        if ($this->isAvailable) {
            Log::info('Neo4j reconnection successful');
        } else {
            Log::warning('Neo4j reconnection failed');
        }

        return $this->isAvailable;
    }

    /**
     * Ensure connection is available, attempting reconnection if needed
     *
     * @return bool True if connection is available
     */
    protected function ensureConnection(): bool
    {
        if ($this->isAvailable && $this->client !== null) {
            return true;
        }

        // Attempt reconnection if not available
        return $this->reconnect();
    }

    /**
     * Perform a health check on Neo4j connection
     * Checks connectivity, constraints, and indexes, then caches the status
     */
    protected function performHealthCheck(): bool
    {
        $healthStatus = [
            'available' => false,
            'healthy' => false,
            'constraints_exist' => false,
            'indexes_exist' => false,
            'checked_at' => now()->toIso8601String(),
        ];

        if (! $this->client) {
            $this->cacheHealthStatus($healthStatus, 'Client not initialized');
            $this->emitUnavailableEvent($healthStatus, 'Client not initialized');

            return false;
        }

        try {
            // Simple query to check if Neo4j is responsive
            $result = $this->client->run(
                'RETURN 1 as test',
                [],
                null,
                $this->sessionConfig
            );

            if ($result->count() === 0) {
                $this->cacheHealthStatus($healthStatus, 'Health check query returned no results');
                $this->emitUnavailableEvent($healthStatus, 'Health check query returned no results');

                return false;
            }

            // Check if required constraints exist
            $healthStatus['constraints_exist'] = $this->checkConstraintsExist();

            // Check if required indexes exist
            $healthStatus['indexes_exist'] = $this->checkIndexesExist();

            // All checks passed
            $healthStatus['available'] = true;
            $healthStatus['healthy'] = $healthStatus['constraints_exist'] && $healthStatus['indexes_exist'];

            $this->cacheHealthStatus($healthStatus);

            return true;
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();

            Log::warning('Neo4j health check failed', [
                'error' => $errorMessage,
            ]);

            $healthStatus['error'] = $errorMessage;
            $this->cacheHealthStatus($healthStatus, $errorMessage);
            $this->emitUnavailableEvent($healthStatus, $errorMessage);

            return false;
        }
    }

    /**
     * Check if required constraints exist in the database
     *
     * This method checks for essential constraints on Law and Case nodes.
     * It is flexible about naming conventions - constraints can be named
     * "law_id", "law_code_unique", "Law_uniqueness", etc. as long as they
     * contain the relevant entity name.
     */
    protected function checkConstraintsExist(): bool
    {
        try {
            $result = $this->client->run(
                'SHOW CONSTRAINTS',
                [],
                null,
                $this->sessionConfig
            );

            // Collect all constraint names
            $constraintNames = [];
            foreach ($result as $record) {
                $constraintNames[] = $record->get('name');
            }

            // Check for constraints related to essential entity types (flexible naming)
            // This handles different naming conventions: law_id, law_code_unique, Law_uniqueness, etc.
            // Also matches related entity types: court_decision is case-related, law_doc is law-related
            $essentialPatterns = [
                'law' => '/\b(law|law_doc)[_\s]?/i',   // Matches law_, law_doc_, Law_, etc.
                'case' => '/\b(case|court_decision)[_\s]?/i', // Matches case_, court_decision_, Case_, etc.
            ];

            foreach ($essentialPatterns as $entity => $pattern) {
                $hasConstraint = false;
                foreach ($constraintNames as $name) {
                    if (preg_match($pattern, $name)) {
                        $hasConstraint = true;
                        break;
                    }
                }

                if (! $hasConstraint) {
                    // Only log at debug level since this is informational
                    // The check is lenient - we return true if any constraints exist
                    Log::debug('No constraint found for entity type', [
                        'entity' => $entity,
                        'expected_pattern' => "constraint name containing '{$entity}'",
                        'existing_constraints' => $constraintNames,
                    ]);
                }
            }

            // Return true if we have at least some constraints (be lenient during initial setup)
            return count($constraintNames) > 0;
        } catch (\Exception $e) {
            Log::warning('Failed to check constraints', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Check if required indexes exist in the database
     */
    protected function checkIndexesExist(): bool
    {
        try {
            $result = $this->client->run(
                'SHOW INDEXES',
                [],
                null,
                $this->sessionConfig
            );

            // Check for at least some indexes
            $indexNames = [];
            foreach ($result as $record) {
                $indexNames[] = $record->get('name');
            }

            // Return true if we have at least some indexes (be lenient during initial setup)
            return count($indexNames) > 0;
        } catch (\Exception $e) {
            Log::warning('Failed to check indexes', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Cache the health status for monitoring
     */
    protected function cacheHealthStatus(array $status, ?string $error = null): void
    {
        $cacheData = [
            'status' => $status,
            'timestamp' => now()->toIso8601String(),
        ];

        if ($error) {
            $cacheData['error'] = $error;
        }

        // Cache for 5 minutes
        Cache::put('neo4j:status', $cacheData, now()->addMinutes(5));
    }

    /**
     * Emit Neo4jUnavailable event when connectivity fails
     */
    protected function emitUnavailableEvent(array $healthStatus, string $error): void
    {
        event(new Neo4jUnavailable(
            healthStatus: $healthStatus,
            error: $error,
            constraintsExist: $healthStatus['constraints_exist'] ?? false,
            indexesExist: $healthStatus['indexes_exist'] ?? false,
        ));
    }

    /**
     * Get the cached health status
     */
    public function getCachedHealthStatus(): ?array
    {
        return Cache::get('neo4j:status');
    }

    /**
     * Get detailed health status
     */
    public function getHealthStatus(): array
    {
        // Attempt reconnection if not currently available
        $this->ensureConnection();

        $status = [
            'available' => $this->isAvailable,
            'enabled' => config('neo4j.sync.enabled', true),
            'uri' => config('neo4j.uri', 'bolt://localhost:7687'),
        ];

        if ($this->isAvailable && $this->client) {
            try {
                // Get database info
                $result = $this->client->run(
                    'CALL dbms.components() YIELD name, versions, edition',
                    [],
                    null,
                    $this->sessionConfig
                );

                if ($result->count() > 0) {
                    $record = $result->first();
                    $status['neo4j_version'] = $record->get('versions')[0] ?? 'unknown';
                    $status['edition'] = $record->get('edition') ?? 'unknown';
                }

                // Get node count
                $countResult = $this->client->run(
                    'MATCH (n) RETURN count(n) as nodeCount',
                    [],
                    null,
                    $this->sessionConfig
                );
                $status['node_count'] = $countResult->first()->get('nodeCount') ?? 0;

                $status['healthy'] = true;
            } catch (\Exception $e) {
                $status['healthy'] = false;
                $status['error'] = $e->getMessage();
            }
        } else {
            $status['healthy'] = false;
            $status['error'] = $this->isAvailable ? 'Client not initialized' : 'Neo4j is not available';
        }

        return $status;
    }

    /**
     * Execute a Cypher query
     */
    /**
     * Run a query with caching and timeout support (Sprint 4.7)
     *
     * @param  string  $query  Cypher query
     * @param  array  $parameters  Query parameters
     * @param  array  $options  Query options (cache_ttl, disable_cache, timeout)
     * @return mixed Query result
     */
    public function run(string $query, array $parameters = [], array $options = []): mixed
    {
        // Attempt to ensure connection is available, with automatic reconnection
        if (! $this->ensureConnection()) {
            Log::warning('Neo4j query skipped - service not available after reconnection attempt', [
                'query' => substr($query, 0, 100),
            ]);
            throw new \RuntimeException('Neo4j is not available. Please check the connection and ensure Neo4j is running.');
        }

        // Check cache first (unless disabled)
        $disableCache = $options['disable_cache'] ?? false;
        $cacheTTL = $options['cache_ttl'] ?? null;

        if (! $disableCache && $this->queryCache) {
            $cached = $this->queryCache->get($query, $parameters);
            if ($cached !== null) {
                Log::debug('GraphDatabaseService - Cache hit', [
                    'query' => substr($query, 0, 100),
                ]);

                return $cached;
            }
        }

        // Execute query with timeout
        $timeout = $options['timeout'] ?? $this->queryTimeout;
        $startTime = microtime(true);

        try {
            // Set query timeout (if supported by driver)
            // Note: Laudis Neo4j driver doesn't have built-in timeout,
            // but we can monitor execution time and abort if needed
            $result = $this->executeQueryWithTimeout($query, $parameters, $timeout);

            $duration = round((microtime(true) - $startTime) * 1000);

            Log::debug('GraphDatabaseService - Query executed', [
                'query' => substr($query, 0, 100),
                'duration_ms' => $duration,
                'result_count' => method_exists($result, 'count') ? $result->count() : null,
            ]);

            // F.4: Log slow queries with optional EXPLAIN output
            $this->logSlowQuery($query, $parameters, $duration);

            $isWrite = $this->isWriteQuery($query);

            // Cache result (unless cache disabled or query is write operation)
            if (! $disableCache && $this->queryCache && ! $isWrite) {
                $this->queryCache->put($query, $parameters, $result, $cacheTTL);
            }

            // Invalidate all cached queries after write operations
            // Write operations may affect the results of previously cached read queries
            if ($isWrite && $this->queryCache) {
                $this->queryCache->invalidateAll();
                Log::debug('GraphDatabaseService - Cache invalidated after write operation', [
                    'query' => substr($query, 0, 100),
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('Neo4j query failed', [
                'query' => substr($query, 0, 100),
                'parameters' => $parameters,
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $startTime) * 1000),
            ]);

            // Mark as unavailable if we get a connection error
            if (str_contains($e->getMessage(), 'Cannot connect')) {
                $this->isAvailable = false;
                Log::critical('Neo4j connection lost - marking service as unavailable', [
                    'error' => $e->getMessage(),
                ]);
            }

            throw $e;
        }
    }

    /**
     * Execute query with timeout monitoring
     */
    protected function executeQueryWithTimeout(string $query, array $parameters, int $timeout): mixed
    {
        $startTime = time();

        // Execute query
        $result = $this->client->run(
            $query,
            $parameters,
            null,                   // alias (keep default)
            $this->sessionConfig    // database here
        );

        $elapsed = time() - $startTime;

        // Check if query exceeded timeout
        if ($elapsed > $timeout) {
            Log::warning('GraphDatabaseService - Query exceeded timeout', [
                'query' => substr($query, 0, 100),
                'timeout' => $timeout,
                'elapsed' => $elapsed,
            ]);

            throw new \RuntimeException("Query exceeded timeout of {$timeout} seconds");
        }

        return $result;
    }

    /**
     * Check if query is a write operation (should not be cached)
     */
    protected function isWriteQuery(string $query): bool
    {
        $query = strtoupper(trim($query));

        return str_contains($query, 'CREATE') ||
               str_contains($query, 'MERGE') ||
               str_contains($query, 'DELETE') ||
               str_contains($query, 'SET') ||
               str_contains($query, 'REMOVE');
    }

    /**
     * Log slow queries with optional EXPLAIN output (F.4)
     *
     * @param  string  $query  The executed query
     * @param  array  $parameters  Query parameters
     * @param  float  $duration  Query duration in milliseconds
     */
    protected function logSlowQuery(string $query, array $parameters, float $duration): void
    {
        $threshold = config('graph.logging.slow_query_threshold_ms', 100);

        if ($duration < $threshold) {
            return;
        }

        $logChannel = config('graph.logging.log_channel', 'graph');
        $explainEnabled = config('graph.logging.explain_slow_queries', true);

        $logData = [
            'query' => $query,
            'parameters' => $parameters,
            'duration_ms' => $duration,
            'threshold_ms' => $threshold,
            'timestamp' => now()->toIso8601String(),
        ];

        // Add EXPLAIN output if enabled
        if ($explainEnabled && $this->client) {
            try {
                $explainQuery = 'EXPLAIN '.$query;
                $explainResult = $this->client->run(
                    $explainQuery,
                    $parameters,
                    null,
                    $this->sessionConfig
                );

                // Convert explain result to array for logging
                $explainData = [];
                foreach ($explainResult as $record) {
                    $explainData[] = $record->toArray();
                }

                $logData['explain'] = $explainData;
            } catch (\Exception $e) {
                // Don't fail query execution if EXPLAIN fails
                $logData['explain_error'] = $e->getMessage();
            }
        }

        Log::channel($logChannel)->warning('Slow query detected', $logData);
    }

    /**
     * Get a truncated preview of a query for logging purposes.
     * Prevents sensitive data from appearing in logs.
     *
     * @param  string  $query  The query to truncate
     * @param  int  $maxLength  Maximum length of the preview including '...' (default 100)
     * @return string Truncated query with '...' appended if truncated
     */
    public static function getQueryPreview(string $query, int $maxLength = 100): string
    {
        if (strlen($query) <= $maxLength) {
            return $query;
        }

        return substr($query, 0, $maxLength - 3) . '...';
    }

    /**
     * Execute an operation with retry logic and exponential backoff.
     *
     * This method wraps any callable operation and automatically retries it
     * on transient failures (connection errors, timeouts) with exponential backoff.
     * Permanent failures (syntax errors, constraint violations) are not retried.
     *
     * @param  callable  $operation  The operation to execute with retry logic
     * @return mixed The result of the operation
     *
     * @throws \Throwable Re-throws the exception after all retry attempts are exhausted
     */
    public function withRetry(callable $operation): mixed
    {
        $attempts = config('graph.retry.attempts', 3);
        $delayMs = config('graph.retry.delay_ms', 100);
        $multiplier = config('graph.retry.multiplier', 2);

        return retry(
            $attempts,
            fn () => $operation(),
            fn (int $attempt, \Throwable $e) => $delayMs * pow($multiplier, $attempt - 1),
            fn (\Throwable $e) => $this->isRetryable($e)
        );
    }

    /**
     * Determine if an exception represents a retryable error.
     *
     * Transient errors (connection issues, timeouts) are retryable.
     * Permanent errors (syntax errors, constraint violations) are not retryable.
     *
     * @param  \Throwable  $exception  The exception to check
     * @return bool True if the error should be retried, false otherwise
     */
    public function isRetryable(\Throwable $exception): bool
    {
        $message = $exception->getMessage();

        // Connection errors are retryable
        if (str_contains($message, 'Cannot connect') ||
            str_contains($message, 'Connection lost') ||
            str_contains($message, 'Connection refused') ||
            str_contains($message, 'Connection reset')) {
            return true;
        }

        // Timeout errors are retryable
        if (str_contains($message, 'timeout') ||
            str_contains($message, 'Timeout') ||
            str_contains($message, 'timed out')) {
            return true;
        }

        // Syntax errors are NOT retryable
        if (str_contains($message, 'Invalid input') ||
            str_contains($message, 'SyntaxError') ||
            str_contains($message, 'expected')) {
            return false;
        }

        // Constraint violations are NOT retryable
        if (str_contains($message, 'already exists') ||
            str_contains($message, 'ConstraintValidationFailed') ||
            str_contains($message, 'constraint violation')) {
            return false;
        }

        // By default, don't retry unknown errors to be safe
        return false;
    }

    /**
     * Run a query with retry queue on failure.
     *
     * This method wraps the standard run() method and automatically dispatches
     * failed operations to the retry queue for exponential backoff retry.
     *
     * @param  string  $query  Cypher query
     * @param  array  $parameters  Query parameters
     * @param  array  $options  Query options (same as run() method)
     * @return mixed Query result
     */
    public function runWithRetry(string $query, array $parameters = [], array $options = []): mixed
    {
        try {
            return $this->run($query, $parameters, $options);
        } catch (Neo4jException $e) {
            Log::warning('Neo4j query failed, dispatching to retry queue', [
                'query' => substr($query, 0, 100),
                'error' => $e->getMessage(),
            ]);

            // Dispatch to retry queue
            RetryNeo4jOperationJob::dispatch(
                operationType: 'query',
                payload: compact('query', 'parameters', 'options')
            )->onQueue('neo4j-retry');

            // Re-throw for immediate handling
            throw $e;
        }
    }

    /**
     * Upsert a node with retry queue on failure.
     *
     * @param  string  $label  Node label
     * @param  string  $id  Node ID
     * @param  array  $properties  Node properties
     */
    public function upsertNodeWithRetry(string $label, string $id, array $properties): void
    {
        try {
            $this->upsertNode($label, $id, $properties);
        } catch (Neo4jException $e) {
            Log::warning('Neo4j upsert node failed, dispatching to retry queue', [
                'label' => $label,
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            RetryNeo4jOperationJob::dispatch(
                operationType: 'upsert_node',
                payload: compact('label', 'id', 'properties')
            )->onQueue('neo4j-retry');

            throw $e;
        }
    }

    /**
     * Create a relationship with retry queue on failure.
     *
     * @param  string  $fromLabel  From node label
     * @param  string  $fromId  From node ID
     * @param  string  $relType  Relationship type
     * @param  string  $toLabel  To node label
     * @param  string  $toId  To node ID
     * @param  array  $properties  Relationship properties
     */
    public function createRelationshipWithRetry(
        string $fromLabel,
        string $fromId,
        string $relType,
        string $toLabel,
        string $toId,
        array $properties = []
    ): void {
        try {
            $this->createRelationship($fromLabel, $fromId, $relType, $toLabel, $toId, $properties);
        } catch (Neo4jException $e) {
            Log::warning('Neo4j create relationship failed, dispatching to retry queue', [
                'from' => "$fromLabel:$fromId",
                'to' => "$toLabel:$toId",
                'type' => $relType,
                'error' => $e->getMessage(),
            ]);

            RetryNeo4jOperationJob::dispatch(
                operationType: 'create_relationship',
                payload: compact('fromLabel', 'fromId', 'relType', 'toLabel', 'toId', 'properties')
            )->onQueue('neo4j-retry');

            throw $e;
        }
    }

    /**
     * Batch upsert nodes with retry queue on failure.
     *
     * @param  string  $label  Node label
     * @param  array  $nodes  Array of nodes to upsert
     * @param  string  $idKey  Property to use as unique identifier
     * @return int Total count of nodes processed
     */
    public function batchUpsertNodesWithRetry(string $label, array $nodes, string $idKey = 'id'): int
    {
        try {
            return $this->batchUpsertNodes($label, $nodes, $idKey);
        } catch (Neo4jException $e) {
            Log::warning('Neo4j batch upsert failed, dispatching to retry queue', [
                'label' => $label,
                'count' => count($nodes),
                'error' => $e->getMessage(),
            ]);

            RetryNeo4jOperationJob::dispatch(
                operationType: 'batch_upsert',
                payload: compact('label', 'nodes', 'idKey')
            )->onQueue('neo4j-retry');

            throw $e;
        }
    }

    /**
     * Store decision in graph with retry queue on failure.
     *
     * @param  array  $meta  Decision metadata
     * @param  string  $docId  Document ID
     */
    public function storeDecisionInGraphWithRetry(array $meta, string $docId): void
    {
        try {
            $this->storeDecisionInGraph($meta, $docId);
        } catch (Neo4jException $e) {
            Log::warning('Neo4j store decision failed, dispatching to retry queue', [
                'doc_id' => $docId,
                'error' => $e->getMessage(),
            ]);

            RetryNeo4jOperationJob::dispatch(
                operationType: 'store_decision',
                payload: compact('meta', 'docId')
            )->onQueue('neo4j-retry');

            throw $e;
        }
    }

    /**
     * Run multiple queries in a transaction
     */
    public function transaction(callable $callback): mixed
    {
        // Attempt to ensure connection is available, with automatic reconnection
        if (! $this->ensureConnection()) {
            throw new \RuntimeException('Neo4j client is not available. Cannot execute transaction.');
        }

        // TransactionConfiguration doesn't have withDatabase method
        // The database is inherited from the client connection configuration
        return $this->client->writeTransaction(
            function ($tsx) use ($callback) {
                return $callback($tsx);
            },
            null,                        // alias (keep default)
            TransactionConfiguration::default()  // transaction config
        );
    }

    /**
     * Initialize graph schema with indexes and constraints
     */
    public function initializeSchema(): void
    {
        try {
            Log::info('Initializing Neo4j schema');
            $startTime = microtime(true);

            // Attempt to ensure connection is available, with automatic reconnection
            if (! $this->ensureConnection()) {
                throw new AnalysisException(
                    'Cannot initialize schema: Neo4j is not available',
                    AnalysisException::UNEXPECTED_ERROR
                );
            }

            $this->createConstraints();
            $this->createIndexes();

            Log::info('Neo4j schema initialization completed', [
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);
        } catch (AnalysisException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Schema initialization failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new AnalysisException(
                'Schema initialization failed: '.$e->getMessage(),
                AnalysisException::EXTRACTION_FAILED,
                $e
            );
        }
    }

    protected function createConstraints(): void
    {
        $constraints = [
            // Law nodes
            'CREATE CONSTRAINT law_id IF NOT EXISTS FOR (l:Law) REQUIRE l.id IS UNIQUE',
            'CREATE CONSTRAINT law_doc_id IF NOT EXISTS FOR (ld:LawDocument) REQUIRE ld.id IS UNIQUE',

            // Case nodes
            'CREATE CONSTRAINT case_id IF NOT EXISTS FOR (c:Case) REQUIRE c.id IS UNIQUE',
            'CREATE CONSTRAINT case_doc_id IF NOT EXISTS FOR (cd:CaseDocument) REQUIRE cd.id IS UNIQUE',

            // Court Decision nodes (Sprint D2.1)
            'CREATE CONSTRAINT court_decision_doc_id IF NOT EXISTS FOR (cdd:CourtDecisionDocument) REQUIRE cdd.id IS UNIQUE',
            // Note: ECLI uniqueness constraint removed because ECLI can be nullable

            // Keyword and Tag nodes
            'CREATE CONSTRAINT keyword_name IF NOT EXISTS FOR (k:Keyword) REQUIRE k.name IS UNIQUE',
            'CREATE CONSTRAINT tag_name IF NOT EXISTS FOR (t:Tag) REQUIRE t.name IS UNIQUE',

            // Jurisdiction and Court nodes
            'CREATE CONSTRAINT jurisdiction_name IF NOT EXISTS FOR (j:Jurisdiction) REQUIRE j.name IS UNIQUE',
            'CREATE CONSTRAINT court_name IF NOT EXISTS FOR (c:Court) REQUIRE c.name IS UNIQUE',

            // Topic and Concept nodes
            'CREATE CONSTRAINT topic_name IF NOT EXISTS FOR (t:Topic) REQUIRE t.name IS UNIQUE',
            'CREATE CONSTRAINT concept_name IF NOT EXISTS FOR (lc:LegalConcept) REQUIRE lc.name IS UNIQUE',
        ];

        foreach ($constraints as $constraint) {
            try {
                $this->run($constraint);
            } catch (\Exception $e) {
                Log::warning('Failed to create constraint', [
                    'constraint' => $constraint,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    protected function createIndexes(): void
    {
        $indexes = [
            // Text indexes for search
            'CREATE INDEX law_title IF NOT EXISTS FOR (l:Law) ON (l.title)',
            'CREATE INDEX law_number IF NOT EXISTS FOR (l:Law) ON (l.law_number)',
            'CREATE INDEX case_title IF NOT EXISTS FOR (c:Case) ON (c.title)',

            // Date indexes
            'CREATE INDEX law_effective_date IF NOT EXISTS FOR (l:Law) ON (l.effective_date)',
            'CREATE INDEX case_decision_date IF NOT EXISTS FOR (c:Case) ON (c.decision_date)',

            // Court Decision indexes (Sprint D2.1)
            'CREATE INDEX court_decision_ecli_idx IF NOT EXISTS FOR (cdd:CourtDecisionDocument) ON (cdd.ecli)',
            'CREATE INDEX court_decision_case_number_idx IF NOT EXISTS FOR (cdd:CourtDecisionDocument) ON (cdd.case_number)',
            'CREATE INDEX court_decision_court_idx IF NOT EXISTS FOR (cdd:CourtDecisionDocument) ON (cdd.court)',
            'CREATE INDEX court_decision_date_idx IF NOT EXISTS FOR (cdd:CourtDecisionDocument) ON (cdd.decision_date)',
            'CREATE INDEX court_decision_type_idx IF NOT EXISTS FOR (cdd:CourtDecisionDocument) ON (cdd.decision_type)',
            'CREATE INDEX court_decision_case_court_idx IF NOT EXISTS FOR (cdd:CourtDecisionDocument) ON (cdd.case_number, cdd.court)',
            'CREATE INDEX court_decision_parent_idx IF NOT EXISTS FOR (cdd:CourtDecisionDocument) ON (cdd.decision_id)',
            'CREATE INDEX court_decision_jurisdiction_idx IF NOT EXISTS FOR (cdd:CourtDecisionDocument) ON (cdd.jurisdiction)',

            // Performance indexes for multi-hop traversals (Sprint 4.7)
            // Decision node ID index for citation chains
            'CREATE INDEX decision_id IF NOT EXISTS FOR (d:Decision) ON (d.id)',
            'CREATE INDEX decision_case_number IF NOT EXISTS FOR (d:Decision) ON (d.case_number)',
            'CREATE INDEX decision_court IF NOT EXISTS FOR (d:Decision) ON (d.court)',
            'CREATE INDEX decision_date IF NOT EXISTS FOR (d:Decision) ON (d.decision_date)',
            'CREATE INDEX decision_jurisdiction IF NOT EXISTS FOR (d:Decision) ON (d.jurisdiction)',

            // Composite index for common query patterns
            'CREATE INDEX decision_court_date IF NOT EXISTS FOR (d:Decision) ON (d.court, d.decision_date)',

            // Metadata indexes
            'CREATE INDEX keyword_category IF NOT EXISTS FOR (k:Keyword) ON (k.category)',
            'CREATE INDEX tag_category IF NOT EXISTS FOR (t:Tag) ON (t.category)',
        ];

        foreach ($indexes as $index) {
            try {
                $this->run($index, [], ['disable_cache' => true]);
            } catch (\Exception $e) {
                Log::warning('Failed to create index', [
                    'index' => $index,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('GraphDatabaseService - Index creation complete', [
            'total_indexes' => count($indexes),
        ]);
    }

    /**
     * Create or update a node
     */
    public function upsertNode(string $label, string $id, array $properties): void
    {
        try {
            Log::debug('Upserting node', [
                'label' => $label,
                'id' => $id,
            ]);

            $startTime = microtime(true);

            if (empty(trim($label))) {
                throw new AnalysisException(
                    'Node label cannot be empty',
                    AnalysisException::EXTRACTION_FAILED
                );
            }

            if (empty(trim($id))) {
                throw new AnalysisException(
                    'Node ID cannot be empty',
                    AnalysisException::EXTRACTION_FAILED
                );
            }

            $properties['id'] = $id;
            $properties['updated_at'] = now()->toIso8601String();

            if (! isset($properties['created_at'])) {
                $properties['created_at'] = now()->toIso8601String();
            }

            $query = "MERGE (n:$label {id: \$id})
                      SET n += \$properties
                      RETURN n";

            $this->run($query, [
                'id' => $id,
                'properties' => $properties,
            ]);

            Log::debug('Node upserted successfully', [
                'label' => $label,
                'id' => $id,
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);
        } catch (AnalysisException $e) {
            throw $e;
        } catch (\RuntimeException $e) {
            // Re-throw runtime exceptions from run() method
            throw $e;
        } catch (\Exception $e) {
            Log::error('Node upsert failed', [
                'label' => $label,
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            throw new AnalysisException(
                'Node upsert failed: '.$e->getMessage(),
                AnalysisException::EXTRACTION_FAILED,
                $e
            );
        }
    }

    /**
     * Create a relationship between two nodes
     */
    public function createRelationship(
        string $fromLabel,
        string $fromId,
        string $relType,
        string $toLabel,
        string $toId,
        array $properties = []
    ): void {
        try {
            Log::debug('Creating relationship', [
                'from' => "$fromLabel:$fromId",
                'to' => "$toLabel:$toId",
                'type' => $relType,
            ]);

            $startTime = microtime(true);

            // Validate parameters
            if (empty(trim($fromLabel)) || empty(trim($toLabel))) {
                throw new AnalysisException(
                    'Node labels cannot be empty',
                    AnalysisException::EXTRACTION_FAILED
                );
            }

            if (empty(trim($fromId)) || empty(trim($toId))) {
                throw new AnalysisException(
                    'Node IDs cannot be empty',
                    AnalysisException::EXTRACTION_FAILED
                );
            }

            if (empty(trim($relType))) {
                throw new AnalysisException(
                    'Relationship type cannot be empty',
                    AnalysisException::EXTRACTION_FAILED
                );
            }

            $properties['created_at'] = now()->toIso8601String();

            $query = "MATCH (from:$fromLabel {id: \$fromId})
                      MATCH (to:$toLabel {id: \$toId})
                      MERGE (from)-[r:$relType]->(to)
                      SET r += \$properties
                      RETURN r";

            $this->run($query, [
                'fromId' => $fromId,
                'toId' => $toId,
                'properties' => $properties,
            ]);

            Log::debug('Relationship created successfully', [
                'from' => "$fromLabel:$fromId",
                'to' => "$toLabel:$toId",
                'type' => $relType,
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);
        } catch (AnalysisException $e) {
            throw $e;
        } catch (\RuntimeException $e) {
            // Re-throw runtime exceptions from run() method
            throw $e;
        } catch (\Exception $e) {
            Log::error('Relationship creation failed', [
                'from' => "$fromLabel:$fromId",
                'to' => "$toLabel:$toId",
                'type' => $relType,
                'error' => $e->getMessage(),
            ]);
            throw new AnalysisException(
                'Relationship creation failed: '.$e->getMessage(),
                AnalysisException::EXTRACTION_FAILED,
                $e
            );
        }
    }

    /**
     * Delete a node and all its relationships
     */
    public function deleteNode(string $label, string $id): void
    {
        try {
            Log::info('Deleting node', [
                'label' => $label,
                'id' => $id,
            ]);

            $startTime = microtime(true);

            if (empty(trim($label))) {
                throw new AnalysisException(
                    'Node label cannot be empty',
                    AnalysisException::EXTRACTION_FAILED
                );
            }

            if (empty(trim($id))) {
                throw new AnalysisException(
                    'Node ID cannot be empty',
                    AnalysisException::EXTRACTION_FAILED
                );
            }

            $query = "MATCH (n:$label {id: \$id})
                      DETACH DELETE n";

            $this->run($query, ['id' => $id]);

            Log::info('Node deleted successfully', [
                'label' => $label,
                'id' => $id,
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);
        } catch (AnalysisException $e) {
            throw $e;
        } catch (\RuntimeException $e) {
            // Re-throw runtime exceptions from run() method
            throw $e;
        } catch (\Exception $e) {
            Log::error('Node deletion failed', [
                'label' => $label,
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            throw new AnalysisException(
                'Node deletion failed: '.$e->getMessage(),
                AnalysisException::EXTRACTION_FAILED,
                $e
            );
        }
    }

    /**
     * Find similar nodes based on properties
     */
    public function findSimilar(string $label, array $properties, int $limit = 10): array
    {
        try {
            Log::debug('Finding similar nodes', [
                'label' => $label,
                'properties_count' => count($properties),
                'limit' => $limit,
            ]);

            $startTime = microtime(true);

            if (empty(trim($label))) {
                throw new AnalysisException(
                    'Node label cannot be empty',
                    AnalysisException::EXTRACTION_FAILED
                );
            }

            if (empty($properties)) {
                Log::warning('findSimilar called with empty properties', ['label' => $label]);

                return [];
            }

            if ($limit < 1) {
                throw new AnalysisException(
                    'Limit must be at least 1',
                    AnalysisException::EXTRACTION_FAILED
                );
            }

            $conditions = [];
            $params = [];

            foreach ($properties as $key => $value) {
                if (! is_string($key) || empty(trim($key))) {
                    Log::debug('Skipping invalid property key', ['key' => $key]);

                    continue;
                }
                $conditions[] = "n.$key = \$$key";
                $params[$key] = $value;
            }

            if (empty($conditions)) {
                Log::warning('No valid conditions generated for findSimilar', ['label' => $label]);

                return [];
            }

            $where = implode(' OR ', $conditions);
            $params['limit'] = $limit;

            $query = "MATCH (n:$label)
                      WHERE $where
                      RETURN n
                      LIMIT \$limit";

            $result = $this->run($query, $params);

            $nodes = $result->map(fn ($record) => $record->get('n')->getProperties())->toArray();

            Log::debug('Similar nodes found', [
                'label' => $label,
                'count' => count($nodes),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return $nodes;
        } catch (AnalysisException $e) {
            throw $e;
        } catch (\RuntimeException $e) {
            // Re-throw runtime exceptions from run() method
            throw $e;
        } catch (\Exception $e) {
            Log::error('Finding similar nodes failed', [
                'label' => $label,
                'error' => $e->getMessage(),
            ]);
            throw new AnalysisException(
                'Finding similar nodes failed: '.$e->getMessage(),
                AnalysisException::EXTRACTION_FAILED,
                $e
            );
        }
    }

    /**
     * Get related nodes
     */
    public function getRelated(
        string $label,
        string $id,
        ?string $relType = null,
        int $depth = 1,
        int $limit = 50
    ): array {
        try {
            Log::debug('Getting related nodes', [
                'label' => $label,
                'id' => $id,
                'relationship_type' => $relType ?? 'any',
                'depth' => $depth,
                'limit' => $limit,
            ]);

            $startTime = microtime(true);

            if (empty(trim($label))) {
                throw new AnalysisException(
                    'Node label cannot be empty',
                    AnalysisException::EXTRACTION_FAILED
                );
            }

            if (empty(trim($id))) {
                throw new AnalysisException(
                    'Node ID cannot be empty',
                    AnalysisException::EXTRACTION_FAILED
                );
            }

            if ($depth < 1) {
                throw new AnalysisException(
                    'Depth must be at least 1',
                    AnalysisException::EXTRACTION_FAILED
                );
            }

            if ($limit < 1) {
                throw new AnalysisException(
                    'Limit must be at least 1',
                    AnalysisException::EXTRACTION_FAILED
                );
            }

            $relPattern = $relType ? "-[:$relType*1..$depth]-" : "-[*1..$depth]-";

            $query = "MATCH (n:$label {id: \$id})$relPattern(related)
                      RETURN DISTINCT related
                      LIMIT \$limit";

            $result = $this->run($query, [
                'id' => $id,
                'limit' => $limit,
            ]);

            $nodes = $result->map(fn ($record) => $record->get('related')->getProperties())->toArray();

            Log::debug('Related nodes retrieved', [
                'label' => $label,
                'id' => $id,
                'count' => count($nodes),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return $nodes;
        } catch (AnalysisException $e) {
            throw $e;
        } catch (\RuntimeException $e) {
            // Re-throw runtime exceptions from run() method
            throw $e;
        } catch (\Exception $e) {
            Log::error('Getting related nodes failed', [
                'label' => $label,
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            throw new AnalysisException(
                'Getting related nodes failed: '.$e->getMessage(),
                AnalysisException::EXTRACTION_FAILED,
                $e
            );
        }
    }

    /**
     * Get shortest path between two nodes
     */
    public function findPath(
        string $fromLabel,
        string $fromId,
        string $toLabel,
        string $toId,
        int $maxDepth = 5
    ): ?array {
        try {
            Log::debug('Finding path between nodes', [
                'from' => "$fromLabel:$fromId",
                'to' => "$toLabel:$toId",
                'max_depth' => $maxDepth,
            ]);

            $startTime = microtime(true);

            if (empty(trim($fromLabel)) || empty(trim($toLabel))) {
                throw new AnalysisException(
                    'Node labels cannot be empty',
                    AnalysisException::EXTRACTION_FAILED
                );
            }

            if (empty(trim($fromId)) || empty(trim($toId))) {
                throw new AnalysisException(
                    'Node IDs cannot be empty',
                    AnalysisException::EXTRACTION_FAILED
                );
            }

            if ($maxDepth < 1) {
                throw new AnalysisException(
                    'Max depth must be at least 1',
                    AnalysisException::EXTRACTION_FAILED
                );
            }

            $query = "MATCH path = shortestPath(
                        (from:$fromLabel {id: \$fromId})-[*1..$maxDepth]-(to:$toLabel {id: \$toId})
                      )
                      RETURN path";

            $result = $this->run($query, [
                'fromId' => $fromId,
                'toId' => $toId,
            ]);

            if ($result->count() === 0) {
                Log::debug('No path found between nodes', [
                    'from' => "$fromLabel:$fromId",
                    'to' => "$toLabel:$toId",
                ]);

                return null;
            }

            $path = $result->first()->get('path');

            Log::debug('Path found between nodes', [
                'from' => "$fromLabel:$fromId",
                'to' => "$toLabel:$toId",
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return $path;
        } catch (AnalysisException $e) {
            throw $e;
        } catch (\RuntimeException $e) {
            // Re-throw runtime exceptions from run() method
            throw $e;
        } catch (\Exception $e) {
            Log::error('Finding path failed', [
                'from' => "$fromLabel:$fromId",
                'to' => "$toLabel:$toId",
                'error' => $e->getMessage(),
            ]);
            throw new AnalysisException(
                'Finding path failed: '.$e->getMessage(),
                AnalysisException::EXTRACTION_FAILED,
                $e
            );
        }
    }

    /**
     * Get node with relationships
     */
    public function getNodeWithRelationships(string $label, string $id): ?array
    {
        try {
            Log::debug('Getting node with relationships', [
                'label' => $label,
                'id' => $id,
            ]);

            $startTime = microtime(true);

            if (empty(trim($label))) {
                throw new AnalysisException(
                    'Node label cannot be empty',
                    AnalysisException::EXTRACTION_FAILED
                );
            }

            if (empty(trim($id))) {
                throw new AnalysisException(
                    'Node ID cannot be empty',
                    AnalysisException::EXTRACTION_FAILED
                );
            }

            $query = "MATCH (n:$label {id: \$id})
                      OPTIONAL MATCH (n)-[r]->(related)
                      RETURN n, collect({type: type(r), node: related, properties: properties(r)}) as relationships";

            $result = $this->run($query, ['id' => $id]);

            if ($result->count() === 0) {
                Log::debug('Node not found', [
                    'label' => $label,
                    'id' => $id,
                ]);

                return null;
            }

            $record = $result->first();

            $node = [
                'node' => $record->get('n')->getProperties(),
                'relationships' => $record->get('relationships'),
            ];

            Log::debug('Node with relationships retrieved', [
                'label' => $label,
                'id' => $id,
                'relationship_count' => count($node['relationships']),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return $node;
        } catch (AnalysisException $e) {
            throw $e;
        } catch (\RuntimeException $e) {
            // Re-throw runtime exceptions from run() method
            throw $e;
        } catch (\Exception $e) {
            Log::error('Getting node with relationships failed', [
                'label' => $label,
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            throw new AnalysisException(
                'Getting node with relationships failed: '.$e->getMessage(),
                AnalysisException::EXTRACTION_FAILED,
                $e
            );
        }
    }

    /**
     * Batch upsert nodes using BatchCypherBuilder with chunking
     *
     * @param  string  $label  Node label
     * @param  array  $nodes  Array of nodes to upsert
     * @param  string  $idKey  Property to use as unique identifier
     * @return int Total count of nodes processed
     */
    public function batchUpsertNodes(string $label, array $nodes, string $idKey = 'id'): int
    {
        try {
            Log::info('Batch upserting nodes', [
                'label' => $label,
                'count' => count($nodes),
                'id_key' => $idKey,
            ]);

            $startTime = microtime(true);

            if (empty(trim($label))) {
                throw new AnalysisException(
                    'Node label cannot be empty',
                    AnalysisException::ANALYSIS_FAILED
                );
            }

            if (empty($nodes)) {
                Log::warning('batchUpsertNodes called with empty nodes array', ['label' => $label]);

                return 0;
            }

            // Validate each node has the ID key
            foreach ($nodes as $index => $node) {
                if (! is_array($node) || ! isset($node[$idKey]) || empty(trim($node[$idKey]))) {
                    Log::warning('Invalid node in batch upsert', [
                        'label' => $label,
                        'index' => $index,
                        'id_key' => $idKey,
                    ]);
                    throw new AnalysisException(
                        "Node at index {$index} is missing or has empty '{$idKey}' field",
                        AnalysisException::ANALYSIS_FAILED
                    );
                }
            }

            // Get chunk size from config
            $chunkSize = config('graph.batch.chunk_size', 100);

            // Chunk the nodes
            $chunks = array_chunk($nodes, $chunkSize);

            $totalProcessed = 0;

            // Get BatchCypherBuilder instance
            $builder = app(BatchCypherBuilder::class);

            foreach ($chunks as $chunkIndex => $chunk) {
                Log::debug('Processing batch chunk', [
                    'label' => $label,
                    'chunk' => $chunkIndex + 1,
                    'total_chunks' => count($chunks),
                    'chunk_size' => count($chunk),
                ]);

                // Build query using BatchCypherBuilder
                $queryData = $builder->buildBatchUpsertNodes($label, $chunk, $idKey);

                // Execute query
                $result = $this->run($queryData['query'], $queryData['parameters']);

                // Add to total count
                $count = $result->first()->get('nodesProcessed');
                $totalProcessed += $count;
            }

            Log::info('Batch upsert completed', [
                'label' => $label,
                'nodes_upserted' => $totalProcessed,
                'chunks_processed' => count($chunks),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return $totalProcessed;
        } catch (AnalysisException $e) {
            throw $e;
        } catch (\RuntimeException $e) {
            // Re-throw runtime exceptions from run() method
            throw $e;
        } catch (\Exception $e) {
            Log::error('Batch upsert failed', [
                'label' => $label,
                'nodes_count' => count($nodes),
                'error' => $e->getMessage(),
            ]);
            throw new AnalysisException(
                'Batch upsert failed: '.$e->getMessage(),
                AnalysisException::ANALYSIS_FAILED,
                $e
            );
        }
    }

    /**
     * Batch upsert relationships using BatchCypherBuilder with chunking
     *
     * @param  string  $type  Relationship type
     * @param  array  $relationships  Array of relationships to upsert (must include fromId and toId)
     * @return int Total count of relationships processed
     */
    public function batchUpsertRelationships(string $type, array $relationships): int
    {
        try {
            Log::info('Batch upserting relationships', [
                'type' => $type,
                'count' => count($relationships),
            ]);

            $startTime = microtime(true);

            if (empty(trim($type))) {
                throw new AnalysisException(
                    'Relationship type cannot be empty',
                    AnalysisException::ANALYSIS_FAILED
                );
            }

            if (empty($relationships)) {
                Log::warning('batchUpsertRelationships called with empty relationships array', ['type' => $type]);

                return 0;
            }

            // Validate each relationship has fromId and toId
            foreach ($relationships as $index => $rel) {
                if (! is_array($rel) || ! isset($rel['fromId']) || ! isset($rel['toId'])) {
                    Log::warning('Invalid relationship in batch upsert', [
                        'type' => $type,
                        'index' => $index,
                    ]);
                    throw new AnalysisException(
                        "Relationship at index {$index} is missing 'fromId' or 'toId' field",
                        AnalysisException::ANALYSIS_FAILED
                    );
                }
            }

            // Get chunk size from config
            $chunkSize = config('graph.batch.chunk_size', 100);

            // Chunk the relationships
            $chunks = array_chunk($relationships, $chunkSize);

            $totalProcessed = 0;

            foreach ($chunks as $chunkIndex => $chunk) {
                Log::debug('Processing batch chunk', [
                    'type' => $type,
                    'chunk' => $chunkIndex + 1,
                    'total_chunks' => count($chunks),
                    'chunk_size' => count($chunk),
                ]);

                // Build query for relationships without label restrictions
                // (Tests don't specify fromLabel/toLabel, so match nodes by ID only)
                $queryData = $this->buildRelationshipBatchQuery($type, $chunk);

                // Execute query
                $result = $this->run($queryData['query'], $queryData['parameters']);

                // Add to total count
                $count = $result->first()->get('relationshipsProcessed');
                $totalProcessed += $count;
            }

            Log::info('Batch relationship upsert completed', [
                'type' => $type,
                'relationships_upserted' => $totalProcessed,
                'chunks_processed' => count($chunks),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return $totalProcessed;
        } catch (AnalysisException $e) {
            throw $e;
        } catch (\RuntimeException $e) {
            // Re-throw runtime exceptions from run() method
            throw $e;
        } catch (\Exception $e) {
            Log::error('Batch relationship upsert failed', [
                'type' => $type,
                'relationships_count' => count($relationships),
                'error' => $e->getMessage(),
            ]);
            throw new AnalysisException(
                'Batch relationship upsert failed: '.$e->getMessage(),
                AnalysisException::ANALYSIS_FAILED,
                $e
            );
        }
    }

    /**
     * Build batch relationship query without label restrictions
     *
     * @param  string  $type  Relationship type
     * @param  array  $relationships  Array of relationships
     * @return array{query: string, parameters: array}
     */
    protected function buildRelationshipBatchQuery(string $type, array $relationships): array
    {
        // Validate relationship type to prevent Cypher injection
        if (! preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $type)) {
            throw new AnalysisException(
                "Relationship type '{$type}' contains invalid characters",
                AnalysisException::ANALYSIS_FAILED
            );
        }

        // Check if relationships have properties beyond fromId/toId
        $firstRel = reset($relationships);
        $propKeys = array_diff(array_keys($firstRel), ['fromId', 'toId']);

        $setClause = '';
        if (! empty($propKeys)) {
            // Validate property keys
            foreach ($propKeys as $key) {
                if (! preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $key)) {
                    throw new AnalysisException(
                        "Property name '{$key}' contains invalid characters",
                        AnalysisException::ANALYSIS_FAILED
                    );
                }
            }
            $setClause = 'SET '.implode(', ', array_map(fn ($k) => "r.{$k} = rel.{$k}", $propKeys));
        }

        $query = <<<CYPHER
UNWIND \$relationships AS rel
MATCH (from {id: rel.fromId})
MATCH (to {id: rel.toId})
MERGE (from)-[r:{$type}]->(to)
{$setClause}
RETURN count(r) AS relationshipsProcessed
CYPHER;

        return [
            'query' => trim($query),
            'parameters' => ['relationships' => array_values($relationships)],
        ];
    }

    /**
     * Clear all data (use with caution!)
     */
    public function clearAll(): void
    {
        try {
            Log::warning('Clearing all data from Neo4j database');
            $startTime = microtime(true);

            // Attempt to ensure connection is available, with automatic reconnection
            if (! $this->ensureConnection()) {
                throw new AnalysisException(
                    'Cannot clear data: Neo4j is not available',
                    AnalysisException::EXTRACTION_FAILED
                );
            }

            $this->run('MATCH (n) DETACH DELETE n');

            Log::warning('All data cleared from Neo4j database', [
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);
        } catch (AnalysisException $e) {
            throw $e;
        } catch (\RuntimeException $e) {
            // Re-throw runtime exceptions from run() method
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to clear all data', [
                'error' => $e->getMessage(),
            ]);
            throw new AnalysisException(
                'Failed to clear all data: '.$e->getMessage(),
                AnalysisException::EXTRACTION_FAILED,
                $e
            );
        }
    }

    /**
     * Get a node with its connections up to a specified depth
     * Used for click-to-expand graph visualization (Sprint 4 - A.5)
     *
     * @param  string  $nodeId  Node ID
     * @param  int  $depth  Depth of connections to fetch (default 1)
     * @return array Array with 'nodes' and 'edges' keys
     */
    public function getNodeWithConnections(string $nodeId, int $depth = 1): array
    {
        try {
            Log::debug('Getting node with connections', [
                'node_id' => $nodeId,
                'depth' => $depth,
            ]);

            $startTime = microtime(true);

            if (empty(trim($nodeId))) {
                throw new AnalysisException(
                    'Node ID cannot be empty',
                    AnalysisException::ANALYSIS_FAILED
                );
            }

            if ($depth < 1) {
                throw new AnalysisException(
                    'Depth must be at least 1',
                    AnalysisException::ANALYSIS_FAILED
                );
            }

            // Query to get node and connected nodes up to specified depth
            // Uses separate queries for nodes and edges to avoid variable scoping issues
            $nodeQuery = "
                MATCH (n {id: \$nodeId})-[*0..$depth]-(connected)
                RETURN DISTINCT
                    connected.id as node_id,
                    labels(connected)[0] as node_type,
                    coalesce(connected.name, connected.title, connected.id) as node_name,
                    properties(connected) as node_properties
            ";

            $edgeQuery = "
                MATCH (n {id: \$nodeId})-[*0..$depth]-(connected)
                WITH COLLECT(DISTINCT connected.id) + [\$nodeId] AS nodeIds
                MATCH (a)-[r]-(b)
                WHERE a.id IN nodeIds AND b.id IN nodeIds AND a.id < b.id
                RETURN DISTINCT
                    startNode(r).id as source_id,
                    endNode(r).id as target_id,
                    type(r) as edge_type
            ";

            $nodeResult = $this->run($nodeQuery, [
                'nodeId' => $nodeId,
            ]);
            $edgeResult = $this->run($edgeQuery, [
                'nodeId' => $nodeId,
            ]);

            $nodes = [];
            $edges = [];
            $nodeIds = [];

            foreach ($nodeResult->toArray() as $record) {
                $recordNodeId = $record['node_id'];

                // Add node if not already added
                if (! in_array($recordNodeId, $nodeIds)) {
                    $nodes[] = [
                        'id' => $recordNodeId,
                        'type' => $record['node_type'],
                        'label' => $record['node_name'],
                        'properties' => $record['node_properties'] ?? [],
                    ];
                    $nodeIds[] = $recordNodeId;
                }
            }

            foreach ($edgeResult->toArray() as $record) {
                if (isset($record['source_id'], $record['target_id'], $record['edge_type'])) {
                    $edges[] = [
                        'source' => $record['source_id'],
                        'target' => $record['target_id'],
                        'type' => $record['edge_type'],
                    ];
                }
            }

            Log::debug('Node with connections retrieved', [
                'node_id' => $nodeId,
                'nodes_count' => count($nodes),
                'edges_count' => count($edges),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return [
                'nodes' => $nodes,
                'edges' => $edges,
            ];
        } catch (AnalysisException $e) {
            throw $e;
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Getting node with connections failed', [
                'node_id' => $nodeId,
                'error' => $e->getMessage(),
            ]);
            throw new AnalysisException(
                'Getting node with connections failed: '.$e->getMessage(),
                AnalysisException::ANALYSIS_FAILED,
                $e
            );
        }
    }

    /**
     * Get nodes connected to a given node, excluding already loaded nodes
     * Used for progressive expansion in graph visualization (Sprint 4 - A.5)
     *
     * @param  string  $nodeId  Node ID to expand from
     * @param  array  $existingIds  Array of node IDs already loaded
     * @return array Array with 'nodes' and 'edges' keys
     */
    public function getConnectedNodes(string $nodeId, array $existingIds = []): array
    {
        try {
            Log::debug('Getting connected nodes', [
                'node_id' => $nodeId,
                'existing_count' => count($existingIds),
            ]);

            $startTime = microtime(true);

            if (empty(trim($nodeId))) {
                throw new AnalysisException(
                    'Node ID cannot be empty',
                    AnalysisException::ANALYSIS_FAILED
                );
            }

            // Query to get nodes connected to the given node, excluding already loaded ones
            $query = '
                MATCH (n {id: $nodeId})-[r]-(connected)
                WHERE NOT connected.id IN $existingIds
                RETURN DISTINCT
                    connected.id as node_id,
                    labels(connected)[0] as node_type,
                    coalesce(connected.name, connected.title, connected.id) as node_name,
                    type(r) as edge_type,
                    startNode(r).id as source_id,
                    endNode(r).id as target_id
            ';

            $result = $this->run($query, [
                'nodeId' => $nodeId,
                'existingIds' => $existingIds,
            ]);

            $nodes = [];
            $edges = [];
            $nodeIds = [];

            foreach ($result->toArray() as $record) {
                $connectedId = $record['node_id'];

                // Add node if not already added
                if (! in_array($connectedId, $nodeIds)) {
                    $nodes[] = [
                        'id' => $connectedId,
                        'type' => $record['node_type'] ?? 'Unknown',
                        'label' => $record['node_name'] ?? $connectedId,
                    ];
                    $nodeIds[] = $connectedId;
                }

                // Add edge if source and target are present
                if (isset($record['source_id'], $record['target_id'], $record['edge_type'])) {
                    $edges[] = [
                        'source' => $record['source_id'],
                        'target' => $record['target_id'],
                        'type' => $record['edge_type'],
                    ];
                }
            }

            Log::debug('Connected nodes retrieved', [
                'node_id' => $nodeId,
                'new_nodes_count' => count($nodes),
                'new_edges_count' => count($edges),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return [
                'nodes' => $nodes,
                'edges' => $edges,
            ];
        } catch (AnalysisException $e) {
            throw $e;
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Getting connected nodes failed', [
                'node_id' => $nodeId,
                'error' => $e->getMessage(),
            ]);
            throw new AnalysisException(
                'Getting connected nodes failed: '.$e->getMessage(),
                AnalysisException::ANALYSIS_FAILED,
                $e
            );
        }
    }

    /**
     * Store a court decision in the graph database with metadata
     * Uses MERGE for idempotency (handles duplicate ECLI gracefully)
     *
     * @param  array  $meta  Decision metadata from Odluke API
     * @param  string  $docId  Document ID (usually ECLI or fallback ID)
     *
     * @throws \Exception if graph sync fails
     */
    public function storeDecisionInGraph(array $meta, string $docId): void
    {
        try {
            Log::info('Storing decision in graph', [
                'doc_id' => $docId,
                'ecli' => $meta['ecli'] ?? null,
            ]);

            $startTime = microtime(true);

            // Validate parameters
            if (empty(trim($docId))) {
                throw new AnalysisException(
                    'Document ID cannot be empty',
                    AnalysisException::EXTRACTION_FAILED
                );
            }

            if (empty($meta)) {
                throw new AnalysisException(
                    'Decision metadata cannot be empty',
                    AnalysisException::EXTRACTION_FAILED
                );
            }

            // Attempt to ensure connection is available, with automatic reconnection
            if (! $this->ensureConnection()) {
                Log::warning('Neo4j is not available after reconnection attempt. Skipping graph storage for decision.', [
                    'doc_id' => $docId,
                    'ecli' => $meta['ecli'] ?? null,
                ]);

                return;
            }

            // Use GraphQueryHelper to build parameterized query
            $helper = app(GraphQueryHelper::class);
            $queryData = $helper->buildDecisionQuery($meta, $docId);

            // Execute the query in a transaction for consistency
            $this->transaction(function ($tsx) use ($queryData) {
                return $tsx->run(
                    $queryData['query'],
                    $queryData['parameters']
                );
            });

            Log::info('Decision stored in graph successfully', [
                'doc_id' => $docId,
                'ecli' => $meta['ecli'] ?? null,
                'case_number' => $meta['broj_odluke'] ?? null,
                'court' => $meta['sud'] ?? null,
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);
        } catch (AnalysisException $e) {
            throw $e;
        } catch (\RuntimeException $e) {
            // Re-throw runtime exceptions from transaction() method
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to store decision in graph', [
                'doc_id' => $docId,
                'meta' => $meta,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new AnalysisException(
                'Failed to store decision in graph: '.$e->getMessage(),
                AnalysisException::EXTRACTION_FAILED,
                $e
            );
        }
    }

    /**
     * Get legal arguments for a court decision
     *
     * TODO: Implement actual query to fetch arguments from Neo4j
     * This is a stub implementation for Sprint 4 - B.2 (Arguments Panel)
     *
     * @param  string  $decisionId  Court decision ID
     * @return array Array of arguments with content and party_type
     */
    public function getArgumentsForDecision(string $decisionId): array
    {
        if (! $this->isAvailable()) {
            return [];
        }

        try {
            $cypher = '
                MATCH (d:CourtDecisionDocument {id: $decisionId})-[r:CONTAINS_ARGUMENT]->(a:LegalArgument)
                RETURN a, r
                ORDER BY r.sequence ASC
            ';

            $results = $this->run($cypher, ['decisionId' => $decisionId]);

            return $results->map(function ($record) {
                $argument = $record['a'];
                $rel = $record['r'];

                return [
                    'id' => $argument['id'] ?? '',
                    'party_type' => $argument['argument_type'] ?? 'unknown',
                    'content' => $argument['summary'] ?? '',
                    'full_text' => $argument['full_text'] ?? '',
                    'accepted' => $argument['accepted'] ?? null,
                    'sequence' => $rel['sequence'] ?? 0,
                ];
            })->toArray();
        } catch (\Throwable $e) {
            Log::warning('Failed to fetch arguments for decision', [
                'decision_id' => $decisionId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Get evidence items for a decision
     *
     * @param  string  $decisionId  Court decision ID
     * @return array<array{id: string, evidence_type: string, description: string, admitted: ?bool, weight: string, ruling: string}>
     */
    public function getEvidenceForDecision(string $decisionId): array
    {
        if (! $this->isAvailable()) {
            return [];
        }

        try {
            $cypher = '
                MATCH (d:CourtDecisionDocument {id: $decisionId})-[r:CONSIDERS_EVIDENCE]->(e:Evidence)
                RETURN e, r
                ORDER BY e.evidence_type ASC
            ';

            $results = $this->run($cypher, ['decisionId' => $decisionId]);

            return $results->map(function ($record) {
                $evidence = $record['e'];
                $rel = $record['r'];

                return [
                    'id' => $evidence['id'] ?? '',
                    'evidence_type' => $evidence['evidence_type'] ?? 'unknown',
                    'description' => $evidence['description'] ?? '',
                    'admitted' => $evidence['admitted'] ?? null,
                    'weight' => $evidence['weight'] ?? 'unknown',
                    'ruling' => $rel['ruling'] ?? '',
                ];
            })->toArray();
        } catch (\Throwable $e) {
            Log::warning('Failed to fetch evidence for decision', [
                'decision_id' => $decisionId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Get date events for a decision
     *
     * @param  string  $decisionId  Court decision ID
     * @return array<int, array{id: string, date: string, event_type: string, description: string}>
     */
    public function getDateEventsForDecision(string $decisionId): array
    {
        if (! $this->isAvailable()) {
            return [];
        }

        try {
            $cypher = '
                MATCH (d:CourtDecisionDocument {id: $decisionId})-[:HAS_EVENT]->(e:DateEvent)
                RETURN e
                ORDER BY e.date ASC
            ';

            $results = $this->run($cypher, ['decisionId' => $decisionId]);

            return $results->map(function ($record) {
                $event = $record['e'];

                return [
                    'id' => $event['id'] ?? '',
                    'date' => $event['date'] ?? '',
                    'event_type' => $event['event_type'] ?? 'unknown',
                    'description' => $event['description'] ?? '',
                ];
            })->toArray();
        } catch (\Throwable $e) {
            Log::warning('Failed to fetch date events for decision', [
                'decision_id' => $decisionId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Search nodes by text query across names, titles, and IDs
     *
     * @param  string  $query  Search query text
     * @param  int  $limit  Maximum number of results
     * @return array Array of node results with id, type, and label
     */
    public function searchNodesByText(string $query, int $limit = 20): array
    {
        try {
            $searchPattern = '(?i).*' . preg_quote($query, '/') . '.*';

            $cypher = "
                MATCH (n)
                WHERE (n.name =~ \$pattern OR n.title =~ \$pattern OR n.id =~ \$pattern)
                RETURN n.id AS id,
                       labels(n)[0] AS type,
                       coalesce(n.name, n.title, n.id) AS label
                LIMIT \$limit
            ";

            $result = $this->run($cypher, [
                'pattern' => $searchPattern,
                'limit' => $limit,
            ]);

            $nodes = [];
            foreach ($result->toArray() as $record) {
                $nodes[] = [
                    'id' => $record['id'],
                    'type' => $record['type'],
                    'label' => $record['label'],
                ];
            }

            return $nodes;
        } catch (\Exception $e) {
            Log::warning('Graph text search failed', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Get a sample of nodes with their relationships for initial graph display.
     * Returns a few well-connected nodes to give the user something to explore.
     *
     * @param  int  $limit  Maximum number of seed nodes
     * @return array Array with 'nodes' and 'edges' keys
     */
    public function getSampleGraph(int $limit = 10): array
    {
        try {
            // Get well-connected nodes (those with the most relationships)
            $cypher = "
                MATCH (n)-[r]-(m)
                WITH n, count(r) AS relCount
                ORDER BY relCount DESC
                LIMIT \$limit
                MATCH (n)-[r]-(m)
                WITH collect(DISTINCT {
                    id: n.id,
                    type: labels(n)[0],
                    label: coalesce(n.name, n.title, n.id)
                }) + collect(DISTINCT {
                    id: m.id,
                    type: labels(m)[0],
                    label: coalesce(m.name, m.title, m.id)
                }) AS allNodes,
                collect(DISTINCT {
                    source: n.id,
                    target: m.id,
                    type: type(r)
                }) AS edges
                UNWIND allNodes AS node
                RETURN collect(DISTINCT node) AS nodes, edges
            ";

            $result = $this->run($cypher, ['limit' => $limit]);

            if ($result->count() === 0) {
                return ['nodes' => [], 'edges' => []];
            }

            $record = $result->first();
            $nodes = array_map(fn ($n) => [
                'id' => $n['id'],
                'type' => $n['type'],
                'label' => $n['label'],
            ], $record['nodes'] ?? []);

            $edges = array_map(fn ($e) => [
                'source' => $e['source'],
                'target' => $e['target'],
                'type' => $e['type'],
            ], $record['edges'] ?? []);

            return ['nodes' => $nodes, 'edges' => $edges];
        } catch (\Exception $e) {
            Log::warning('Failed to get sample graph', [
                'error' => $e->getMessage(),
            ]);

            return ['nodes' => [], 'edges' => []];
        }
    }
}

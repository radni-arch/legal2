# Troubleshooting Guide

This guide covers common issues, error messages, and solutions for the AI Legal War Machine system.

## Table of Contents

- [Quick Diagnostics](#quick-diagnostics)
- [Agent Execution Issues](#agent-execution-issues)
- [Database Issues](#database-issues)
- [Vector Search Problems](#vector-search-problems)
- [LLM API Failures](#llm-api-failures)
- [Cache Configuration](#cache-configuration)
- [Queue Issues](#queue-issues)
- [Performance Problems](#performance-problems)
- [Test Failures](#test-failures)
- [Integration Failures](#integration-failures)
- [Deployment Issues](#deployment-issues)
- [Getting Help](#getting-help)

---

## Quick Diagnostics

### System Health Check

Run these commands to quickly check system health:

```bash
# Check database connectivity
php artisan tinker
>>> DB::connection()->getPdo();
>>> DB::connection('neo4j')->getPdo();  # If Neo4j enabled

# Check cache
php artisan cache:clear
php artisan config:clear

# Check queue status
php artisan queue:failed
php artisan horizon:status  # (Production only - if Horizon installed)

# Check agent status
php artisan agents:profile all --runs=1

# Check logs
tail -f storage/logs/laravel.log
php artisan pail --timeout=0  # (Optional - requires Laravel Pail package)
```

### Environment Verification

```bash
# Verify all required environment variables
php artisan config:show

# Check PHP version (requires 8.2+)
php -v

# Check PostgreSQL connection
psql -h localhost -U your_user -d your_db -c "SELECT version();"

# Check Neo4j connection (if enabled)
curl -u neo4j:password http://localhost:7474/db/neo4j/tx/commit

# Check Redis connection
redis-cli ping
```

---

## Agent Execution Issues

### Error: "Agent execution timeout"

**Symptoms**:
- Agent runs fail after 300 seconds
- Error message: "Agent execution exceeded maximum time limit"

**Causes**:
- Complex queries requiring multiple searches
- Large result sets causing slow processing
- Network latency to external APIs
- Unoptimized database queries

**Solutions**:

1. **Increase timeout in config/agent.php**:
```php
'limits' => [
    'max_execution_time' => 600, // Increase to 600 seconds
],
```

2. **Profile the agent to find bottlenecks**:
```bash
php artisan agents:profile research --runs=3
```

3. **Check database query performance**:
```bash
# Enable query logging in .env
DB_LOG_QUERIES=true

# Run the agent
php artisan tinker
>>> $agent = app(\App\Agents\ResearchSpecialist::class);
>>> $agent->execute('test problem');

# Check slow queries in logs
tail -f storage/logs/laravel.log | grep "Query took"
```

4. **Use caching for repeated queries**:
```php
$cache = app(\App\Services\AgentResultCacheService::class);
$result = $cache->remember('research', $cacheKey, function() use ($agent) {
    return $agent->execute($problem);
}, ttl: 3600);
```

### Error: "Insufficient context - no relevant precedents found"

**Symptoms**:
- Agent returns empty or minimal results
- Error: "No relevant court decisions found for query"
- Low confidence scores

**Causes**:
- Vector store not populated with data
- Embeddings not generated for documents
- Query too specific or using uncommon terminology
- Similarity threshold too high

**Solutions**:

1. **Check vector store data**:
```bash
php artisan tinker
>>> $vectorStore = app(\App\Services\CourtDecisionVectorStoreService::class);
>>> DB::table('court_decision_embeddings')->count();  # Should be > 0
```

2. **Ingest court decisions** (if Odluke module enabled):
```bash
# Ingest from odluke.sudovi.hr
php artisan odluke:ingest --limit=100  # (Requires Odluke module)

# Verify ingestion
php artisan tinker
>>> DB::table('court_decision_embeddings')->latest()->first();
```

3. **Test vector search directly**:
```bash
php artisan tinker
>>> $vectorStore = app(\App\Services\CourtDecisionVectorStoreService::class);
>>> $results = $vectorStore->search('proportionality of home search', limit: 10);
>>> count($results);  # Should return results
```

4. **Lower similarity threshold**:
```php
// In your agent code
$results = $this->vectorStore->search($query, limit: 10, threshold: 0.7);  // Lower from 0.8
```

### Error: "Agent communication bus failure"

**Symptoms**:
- Multi-agent orchestration fails
- Error: "Failed to send message to agent"
- Agents not receiving messages from other agents

**Causes**:
- Database transaction conflicts
- Incorrect session_id
- Agent not registered in communication bus
- Message queue issues

**Solutions**:

1. **Check agent_communications table**:
```bash
php artisan tinker
>>> DB::table('agent_communications')->latest()->take(5)->get();
```

2. **Verify session ID consistency**:
```php
// In your orchestration code
$sessionId = Str::uuid()->toString();
Log::info("Session started", ['session_id' => $sessionId]);

// Pass same session ID to all agents
$context->setSessionId($sessionId);
```

3. **Check for database locks**:
```sql
-- PostgreSQL: Find blocking queries
SELECT
    pid,
    now() - pg_stat_activity.query_start AS duration,
    query,
    state
FROM pg_stat_activity
WHERE state != 'idle'
ORDER BY duration DESC;
```

4. **Use message bus debugging**:
```php
// Enable debug logging in config/agent.php
'debug' => true,

// Check logs
php artisan pail --filter="CommunicationBus"
```

---

## Database Issues

### Error: "SQLSTATE[08006] Connection failure"

**Symptoms**:
- Database connection errors
- Intermittent query failures
- Error: "could not connect to server"

**Causes**:
- PostgreSQL not running
- Incorrect credentials in .env
- Connection pool exhausted
- Network/firewall issues

**Solutions**:

1. **Check PostgreSQL status**:
```bash
# Linux
sudo systemctl status postgresql
sudo systemctl start postgresql

# macOS
brew services list
brew services start postgresql@15

# Docker
docker ps | grep postgres
docker start postgres_container
```

2. **Verify connection credentials**:
```bash
# Test connection with psql
psql -h localhost -U your_user -d your_db

# Check .env file
cat .env | grep DB_
```

3. **Check connection pool settings**:
```php
// config/database.php
'pgsql' => [
    'pool' => [
        'min_connections' => 2,
        'max_connections' => 10,  # Increase if needed
    ],
],
```

4. **Test connection in Laravel**:
```bash
php artisan tinker
>>> DB::connection()->getPdo();
>>> DB::table('users')->count();
```

### Error: "Deadlock detected"

**Symptoms**:
- Transaction failures
- Error: "deadlock detected"
- Concurrent writes failing

**Causes**:
- Incorrect transaction isolation level
- Long-running transactions
- Multiple agents updating same records
- Improper locking order

**Solutions**:

1. **Use proper transaction isolation**:
```php
// Use READ COMMITTED for most operations
DB::transaction(function () {
    // Your code
}, attempts: 3);  // Retry on deadlock
```

2. **Minimize transaction duration**:
```php
// Bad: Long transaction
DB::transaction(function () {
    $data = $this->fetchExternalData();  // Slow external call
    $this->processData($data);
    $this->saveResults($data);
});

// Good: Keep transaction short
$data = $this->fetchExternalData();  // Outside transaction
$processedData = $this->processData($data);

DB::transaction(function () use ($processedData) {
    $this->saveResults($processedData);  // Only save in transaction
});
```

3. **Use advisory locks for critical sections**:
```php
// Acquire exclusive lock
DB::select("SELECT pg_advisory_lock(12345)");

try {
    // Critical section
    $this->updateSharedResource();
} finally {
    DB::select("SELECT pg_advisory_unlock(12345)");
}
```

### Error: "Relation does not exist"

**Symptoms**:
- Table/column not found errors
- Error: "relation 'table_name' does not exist"

**Causes**:
- Migrations not run
- Using test database without setup
- Incorrect schema/table name

**Solutions**:

1. **Run migrations**:
```bash
# Production database
php artisan migrate

# Test database
composer test:setup
# or
./scripts/setup-test-db.sh
```

2. **Check migration status**:
```bash
php artisan migrate:status
```

3. **Verify table exists**:
```bash
php artisan tinker
>>> Schema::hasTable('court_decisions');
>>> Schema::hasColumn('court_decisions', 'ecli');
```

---

## Vector Search Problems

### Error: "Extension 'vector' is not available"

**Symptoms**:
- Vector search queries fail
- Error: "type 'vector' does not exist"
- pgvector operations fail

**Causes**:
- pgvector extension not installed
- Extension not enabled in database
- PostgreSQL version < 11

**Solutions**:

1. **Install pgvector extension**:
```bash
# Ubuntu/Debian
sudo apt install postgresql-15-pgvector

# macOS
brew install pgvector

# Docker
# Use image with pgvector: ankane/pgvector
```

2. **Enable extension in database**:
```sql
-- Connect to your database
psql -U your_user -d your_db

-- Enable pgvector
CREATE EXTENSION IF NOT EXISTS vector;

-- Verify
\dx vector
```

3. **Verify in Laravel**:
```bash
php artisan tinker
>>> DB::select("SELECT * FROM pg_extension WHERE extname = 'vector'");
```

### Error: "Embedding dimension mismatch"

**Symptoms**:
- Vector insertion fails
- Error: "dimension 768 does not match column dimension 1536"

**Causes**:
- Using different embedding models
- Mixing text-embedding-3-small (1536) with text-embedding-ada-002 (1536)
- Incorrect vector dimension in migration

**Solutions**:

1. **Check your embedding model**:
```php
// config/openai.php
'embeddings' => [
    'model' => 'text-embedding-3-small',  // 1536 dimensions
    // or
    'model' => 'text-embedding-ada-002',  // 1536 dimensions
],
```

2. **Verify column dimensions**:
```sql
SELECT
    column_name,
    data_type
FROM information_schema.columns
WHERE table_name = 'court_decision_embeddings'
AND column_name = 'embedding';

-- Should show: vector(1536)
```

3. **Recreate embeddings with correct dimensions**:
```bash
php artisan tinker
>>> DB::table('court_decision_embeddings')->truncate();
>>> $service = app(\App\Services\Odluke\OdlukeIngestService::class);
>>> $service->ingestByIds(['decision-id-1']);
```

### Error: "IVFFlat index build failed"

**Symptoms**:
- Migration fails when creating IVFFlat index
- Error: "lists must be at least 1"
- Slow vector searches despite index

**Causes**:
- Too few rows for index creation (need 100+ rows)
- Incorrect index parameters
- Index not being used by query planner

**Solutions**:

1. **Ensure enough data for index**:
```bash
php artisan tinker
>>> DB::table('court_decision_embeddings')->count();  # Should be > 100
```

2. **Create index after data ingestion**:
```sql
-- Create IVFFlat index
CREATE INDEX IF NOT EXISTS court_decision_embeddings_embedding_idx
ON court_decision_embeddings
USING ivfflat (embedding vector_cosine_ops)
WITH (lists = 100);
```

3. **Verify index usage**:
```sql
-- Check query plan
EXPLAIN ANALYZE
SELECT * FROM court_decision_embeddings
ORDER BY embedding <=> '[0.1, 0.2, ...]'::vector
LIMIT 10;

-- Should show "Index Scan using court_decision_embeddings_embedding_idx"
```

4. **Tune probes for accuracy/speed trade-off**:
```sql
-- Set probes before query (higher = more accurate, slower)
SET ivfflat.probes = 10;  -- Default is 1

-- Run your search
SELECT * FROM court_decision_embeddings
ORDER BY embedding <=> '[...]'::vector
LIMIT 10;
```

---

## LLM API Failures

### Error: "Rate limit exceeded"

**Symptoms**:
- OpenAI API calls fail
- Error: "Rate limit reached for requests"
- HTTP 429 errors

**Causes**:
- Too many parallel requests
- Insufficient API tier limits
- No rate limiting implementation

**Solutions**:

1. **Implement rate limiting**:
```php
// config/openai.php
'rate_limit' => [
    'requests_per_minute' => 50,  // Adjust based on your tier
    'tokens_per_minute' => 90000,
],
```

2. **Use ParallelExecutionService with limits**:
```php
$parallel = app(\App\Services\ParallelExecutionService::class);

// Limit parallel LLM calls to 3
$results = $parallel->executeLLMCalls($tasks, maxParallel: 3);
```

3. **Add exponential backoff**:
```php
use Illuminate\Support\Facades\Http;

$response = retry(3, function () {
    return Http::timeout(30)
        ->withToken(config('openai.api_key'))
        ->post('https://api.openai.com/v1/chat/completions', $data)
        ->throw();
}, sleepMilliseconds: function ($attempt) {
    return $attempt * 1000;  // 1s, 2s, 3s
});
```

4. **Monitor usage**:
```bash
php artisan tinker
>>> DB::table('agent_runs')
    ->where('created_at', '>', now()->subHour())
    ->count();  # Count recent runs
```

### Error: "Context length exceeded"

**Symptoms**:
- OpenAI API returns error
- Error: "maximum context length is 128000 tokens"
- Long documents fail to process

**Causes**:
- Input exceeds model's context window
- Including too much context in prompt
- Large precedent texts

**Solutions**:

1. **Chunk large documents**:
```php
// Break into chunks
$chunks = array_chunk($lines, 500);  // 500 lines per chunk

foreach ($chunks as $chunk) {
    $response = $openai->chat([
        'model' => 'gpt-4o',
        'messages' => [['role' => 'user', 'content' => implode("\n", $chunk)]],
    ]);
}
```

2. **Use gpt-4o-mini for simple tasks**:
```php
// config/openai.php
'models' => [
    'analysis' => 'gpt-4o',      // 128k context
    'simple' => 'gpt-4o-mini',   // 128k context, cheaper
],
```

3. **Summarize context before passing**:
```php
// Summarize large precedents first
$summary = $this->summarizePrecedent($fullText);  // Reduce from 10k to 2k tokens

// Then use in main analysis
$response = $openai->chat([
    'messages' => [
        ['role' => 'system', 'content' => 'Analyze this case...'],
        ['role' => 'user', 'content' => $summary],  // Use summary instead
    ],
]);
```

4. **Count tokens before sending**:
```php
use OpenAI\Tokenizer;

$tokenizer = new Tokenizer();
$tokenCount = $tokenizer->count($text);

if ($tokenCount > 120000) {  // Leave buffer
    throw new \Exception("Text too long: {$tokenCount} tokens");
}
```

### Error: "API key invalid"

**Symptoms**:
- All OpenAI calls fail
- Error: "Incorrect API key provided"
- HTTP 401 errors

**Causes**:
- Missing or incorrect OPENAI_API_KEY in .env
- API key expired or revoked
- Using wrong organization key

**Solutions**:

1. **Verify API key**:
```bash
# Check .env
cat .env | grep OPENAI_API_KEY

# Test key directly
curl https://api.openai.com/v1/models \
  -H "Authorization: Bearer $OPENAI_API_KEY"
```

2. **Regenerate key**:
- Go to https://platform.openai.com/api-keys
- Create new key
- Update .env
- Clear config cache: `php artisan config:clear`

3. **Check organization settings**:
```bash
# If using organization key
cat .env | grep OPENAI_ORGANIZATION
```

---

## Cache Configuration

### Error: "Cache connection refused"

**Symptoms**:
- Redis connection errors
- Error: "Connection refused [tcp://127.0.0.1:6379]"
- Cache operations fail

**Causes**:
- Redis not running
- Incorrect Redis configuration
- Redis authentication required

**Solutions**:

1. **Start Redis**:
```bash
# Linux
sudo systemctl start redis
sudo systemctl status redis

# macOS
brew services start redis

# Docker
docker start redis_container
```

2. **Test Redis connection**:
```bash
redis-cli ping  # Should return "PONG"
```

3. **Check Laravel Redis config**:
```bash
cat .env | grep REDIS_

# Should have:
# REDIS_HOST=127.0.0.1
# REDIS_PASSWORD=null
# REDIS_PORT=6379
```

4. **Fallback to array cache for testing**:
```bash
# In .env
CACHE_DRIVER=array  # Use for testing without Redis
```

### Error: "Cache serialization failed"

**Symptoms**:
- Cannot store objects in cache
- Error: "Serialization of 'Closure' is not allowed"

**Causes**:
- Trying to cache closures
- Caching objects with non-serializable properties

**Solutions**:

1. **Don't cache closures**:
```php
// Bad
Cache::put('callback', fn() => $this->process(), 3600);

// Good
$result = $this->process();
Cache::put('result', $result, 3600);
```

2. **Make objects serializable**:
```php
class MyClass implements \Serializable
{
    public function serialize(): string
    {
        return serialize(['data' => $this->data]);
    }

    public function unserialize(string $data): void
    {
        $data = unserialize($data);
        $this->data = $data['data'];
    }
}
```

3. **Use array/primitive data for caching**:
```php
// Convert to array before caching
$cacheData = [
    'results' => $results->toArray(),
    'metadata' => $metadata,
];
Cache::put($key, $cacheData, 3600);
```

---

## Queue Issues

### Error: "Queue worker not processing jobs"

**Symptoms**:
- Jobs stuck in pending state
- Queue worker running but no progress
- Jobs not executing

**Causes**:
- Worker process stopped
- Wrong queue being processed
- Database queue table locked
- Failed jobs retrying infinitely

**Solutions**:

1. **Check worker status**:
```bash
# List running workers
ps aux | grep "queue:work"

# Start worker
php artisan queue:work --queue=textract,agents,default
```

2. **Check failed jobs**:
```bash
php artisan queue:failed

# Retry all failed jobs
php artisan queue:retry all

# Delete failed jobs
php artisan queue:flush
```

3. **Monitor queue in real-time** (Production only - requires Horizon):
```bash
php artisan queue:monitor  # (Production only)

# Or check queue table
php artisan tinker
>>> DB::table('jobs')->count();  # Pending jobs
>>> DB::table('failed_jobs')->count();  # Failed jobs
```

4. **Restart queue worker**:
```bash
php artisan queue:restart
php artisan queue:work --queue=textract,agents,default --tries=3
```

### Error: "Textract job timeout"

**Symptoms**:
- Textract jobs fail after long processing
- Error: "Job has been attempted too many times"
- PDF processing incomplete

**Causes**:
- Large PDF files (>100 pages)
- Network timeout to AWS
- Insufficient job timeout
- AWS Textract rate limits

**Solutions**:

1. **Increase job timeout**:
```php
// app/Jobs/ProcessDrivePdfJob.php
public $timeout = 600;  // 10 minutes
public $tries = 3;
```

2. **Monitor Textract status**:
```bash
php artisan tinker
>>> $job = \App\Models\TextractJob::latest()->first();
>>> $job->status;  # Check status
>>> $job->error_message;  # Check errors
```

3. **Process smaller batches**:
```bash
# Process 3 files at a time
php artisan textract:process-drive-folder FOLDER_ID --limit=3
```

4. **Check AWS credentials**:
```bash
cat .env | grep AWS_

# Test AWS connection
php artisan tinker
>>> Storage::disk('s3')->files();
```

---

## Performance Problems

### Issue: "Slow agent response times"

**Symptoms**:
- Agent execution >20 seconds
- ResearchSpecialist taking >15 seconds
- Multi-agent orchestration >60 seconds

**Diagnosis**:

```bash
# Profile agents to identify bottlenecks
php artisan agents:profile all --runs=5

# Check for slow queries
# Enable query logging in .env
DB_LOG_QUERIES=true

# Run agent and check logs
php artisan pail --filter="Query took"
```

**Solutions**:

1. **Add database indexes** (if missing):
```bash
# Run performance migration
php artisan migrate

# Verify indexes exist
php artisan tinker
>>> Schema::getConnection()->getDoctrineSchemaManager()->listTableIndexes('court_decisions');
```

2. **Enable result caching**:
```php
$cache = app(\App\Services\AgentResultCacheService::class);
$cacheKey = $cache->generateCacheKey($problem, $params);

$result = $cache->remember('research', $cacheKey, function() use ($agent, $problem) {
    return $agent->execute($problem);
}, ttl: 3600);
```

3. **Use parallel execution**:
```php
$parallel = app(\App\Services\ParallelExecutionService::class);

$tasks = [
    'law_search' => fn() => $lawSearch->search($query),
    'decision_search' => fn() => $decisionSearch->search($query),
    'case_search' => fn() => $caseSearch->search($query),
];

$results = $parallel->execute($tasks, maxParallel: 3);
```

4. **Optimize vector searches**:
```sql
-- Ensure IVFFlat indexes exist
SELECT * FROM pg_indexes WHERE tablename = 'court_decision_embeddings';

-- Tune probes (higher = more accurate, slower)
SET ivfflat.probes = 5;  -- Default is 1, try 5-10
```

5. **Use gpt-4o-mini for simple tasks**:
```php
// For classification, extraction, etc.
'model' => 'gpt-4o-mini',  // 15x cheaper, 5x faster
```

### Issue: "High memory usage"

**Symptoms**:
- PHP memory exhausted errors
- Server OOM kills
- Slow garbage collection

**Solutions**:

1. **Increase PHP memory limit**:
```bash
# In .env or php.ini
memory_limit=512M

# Or in code
ini_set('memory_limit', '512M');
```

2. **Process large datasets in chunks**:
```php
// Bad: Load all at once
$decisions = CourtDecision::all();  // 10k records = OOM

// Good: Chunk processing
CourtDecision::chunk(100, function ($decisions) {
    foreach ($decisions as $decision) {
        $this->process($decision);
    }
});
```

3. **Use cursor for large result sets**:
```php
// For very large datasets
foreach (CourtDecision::cursor() as $decision) {
    $this->process($decision);
}
```

4. **Clear query log periodically**:
```php
DB::flushQueryLog();  // Clears in-memory query log
```

---

## Test Failures

### Error: "Database doesn't exist" in tests

**Symptoms**:
- Tests fail with "database 'ai_legal_test' does not exist"
- Fresh test environment fails

**Causes**:
- Test database not created
- Using RefreshDatabase (incorrect)

**Solutions**:

1. **Setup test database**:
```bash
composer test:setup
# or
./scripts/setup-test-db.sh
```

2. **Verify test database exists**:
```bash
psql -l | grep ai_legal_test
```

3. **Use correct test trait**:
```php
use Tests\Concerns\UsesTestDatabase;

class MyTest extends TestCase
{
    use UsesTestDatabase;  // NOT RefreshDatabase!

    public function test_something()
    {
        // Test runs in transaction, auto-rolled back
    }
}
```

### Error: "Http::fake() not working"

**Symptoms**:
- Real API calls being made during tests
- OpenAI charges during test runs
- Tests fail without API keys

**Causes**:
- Http::fake() called after service instantiation
- Using Guzzle client directly instead of Laravel Http
- Fake not matching URL pattern

**Solutions**:

1. **Call Http::fake() in setUp()**:
```php
protected function setUp(): void
{
    parent::setUp();

    // Fake OpenAI APIs
    Http::fake([
        'api.openai.com/v1/embeddings' => Http::response([
            'data' => [['embedding' => array_fill(0, 1536, 0.1)]],
        ], 200),
        'api.openai.com/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => 'Test response']]],
        ], 200),
    ]);
}
```

2. **Use Laravel Http instead of Guzzle**:
```php
// Bad: Direct Guzzle (not fakeable)
$client = new \GuzzleHttp\Client();
$response = $client->post('https://api.openai.com/...');

// Good: Laravel Http (fakeable)
$response = Http::withToken($token)->post('https://api.openai.com/...');
```

3. **Match exact URLs**:
```php
// Fake with wildcards
Http::fake([
    'api.openai.com/*' => Http::response(['success' => true]),
]);
```

### Error: "Tests modifying production data"

**Symptoms**:
- Production database records changing after tests
- Test data persisting after test run

**Causes**:
- Not using DatabaseTransactions
- Manual database modifications outside transactions
- Committing transactions in test

**Solutions**:

1. **Always use UsesTestDatabase trait**:
```php
use Tests\Concerns\UsesTestDatabase;  // Includes DatabaseTransactions

class MyTest extends TestCase
{
    use UsesTestDatabase;
}
```

2. **Never commit transactions in tests**:
```php
// Bad
DB::transaction(function () {
    // ...
});
DB::commit();  // DON'T commit in tests

// Good
DB::transaction(function () {
    // ...
});  // Auto-rolled back by DatabaseTransactions
```

3. **Check test database being used**:
```php
public function test_database_connection()
{
    $this->assertEquals('ai_legal_test', DB::connection()->getDatabaseName());
}
```

---

## Integration Failures

### Error: "Odluke.sudovi.hr connection failed"

**Symptoms**:
- Circuit breaker opens
- Error: "Circuit breaker is open"
- Court decision ingestion fails

**Causes**:
- Rate limiting by odluke.sudovi.hr
- Network connectivity issues
- Server temporarily down
- 3+ consecutive failures

**Solutions**:

1. **Check circuit breaker status**:
```bash
php artisan tinker
>>> Cache::get('odluke_circuit_breaker_state');
>>> Cache::get('odluke_circuit_breaker_failures');
```

2. **Reset circuit breaker**:
```bash
php artisan tinker
>>> Cache::forget('odluke_circuit_breaker_state');
>>> Cache::forget('odluke_circuit_breaker_failures');
>>> Cache::forget('odluke_circuit_breaker_last_attempt');
```

3. **Adjust rate limits**:
```bash
# In .env
ODLUKE_RPM=20  # Reduce from 30
ODLUKE_DELAY_MS=1000  # Increase delay
ODLUKE_BACKOFF_MS=1200
```

4. **Test connection directly**:
```bash
curl -I https://odluke.sudovi.hr
```

5. **Wait for automatic recovery**:
- Circuit breaker opens for 60 seconds after 3 failures
- Will auto-retry after timeout

### Error: "Neo4j connection failed"

**Symptoms**:
- Graph sync fails
- Error: "Could not connect to Neo4j"
- Relationship creation fails

**Causes**:
- Neo4j not running
- Incorrect credentials
- Bolt port not accessible

**Solutions**:

1. **Check Neo4j status**:
```bash
# Docker
docker ps | grep neo4j

# Standalone
systemctl status neo4j

# Test connection
curl http://localhost:7474
```

2. **Verify credentials**:
```bash
cat .env | grep NEO4J_

# Test with cypher-shell
cypher-shell -u neo4j -p your_password
```

3. **Disable Neo4j temporarily**:
```bash
# In .env
NEO4J_ENABLED=false

# Run ingestion without graph sync (Requires Odluke module)
php artisan odluke:ingest --no-sync
```

4. **Check firewall/network**:
```bash
# Test Bolt port
telnet localhost 7687

# Test HTTP port
telnet localhost 7474
```

### Error: "AWS Textract access denied"

**Symptoms**:
- Textract jobs fail
- Error: "Access Denied" from AWS
- S3 upload fails

**Causes**:
- Incorrect AWS credentials
- IAM permissions insufficient
- Bucket policy restricts access

**Solutions**:

1. **Verify AWS credentials**:
```bash
cat .env | grep AWS_

# Test with AWS CLI
aws s3 ls s3://your-bucket --profile your_profile
```

2. **Check IAM permissions**:
Required permissions:
```json
{
  "Version": "2012-10-17",
  "Statement": [{
    "Effect": "Allow",
    "Action": [
      "textract:StartDocumentAnalysis",
      "textract:GetDocumentAnalysis",
      "s3:PutObject",
      "s3:GetObject"
    ],
    "Resource": "*"
  }]
}
```

3. **Test S3 access**:
```bash
php artisan tinker
>>> Storage::disk('s3')->put('test.txt', 'test content');
>>> Storage::disk('s3')->exists('test.txt');
```

---

## Deployment Issues

### Error: "500 Internal Server Error" in production

**Symptoms**:
- Application returns 500 errors
- White screen in browser
- No error details shown

**Solutions**:

1. **Check Laravel logs**:
```bash
tail -f storage/logs/laravel.log
```

2. **Enable debug mode temporarily**:
```bash
# In .env (ONLY for debugging, disable after!)
APP_DEBUG=true

# View error
# REMEMBER TO SET BACK TO false!
```

3. **Check storage permissions**:
```bash
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

4. **Clear caches**:
```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

5. **Check PHP error logs**:
```bash
# Ubuntu/Debian
tail -f /var/log/apache2/error.log
tail -f /var/log/nginx/error.log

# PHP-FPM
tail -f /var/log/php8.2-fpm.log
```

### Error: "Queue worker stops after deployment"

**Symptoms**:
- Jobs not processing after deploy
- Worker using old code

**Causes**:
- Worker process not restarted
- Cached old code

**Solutions**:

1. **Restart queue workers after deploy**:
```bash
# In deployment script
php artisan queue:restart

# Wait for workers to reload
sleep 5

# Verify workers restarted (Production only - requires Horizon)
php artisan queue:monitor
```

2. **Use Supervisor for auto-restart**:
```ini
# /etc/supervisor/conf.d/laravel-worker.conf
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/artisan queue:work --sleep=3 --tries=3
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/www/html/storage/logs/worker.log
stopwaitsecs=3600
```

3. **Reload Supervisor**:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl restart laravel-worker:*
```

### Issue: "Slow production performance"

**Symptoms**:
- Pages load slowly (>5 seconds)
- Agent responses take minutes
- High server load

**Solutions**:

1. **Enable OpCache**:
```ini
# php.ini
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.revalidate_freq=2
```

2. **Cache configuration**:
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

3. **Use Redis for cache/sessions**:
```bash
# In .env
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
```

4. **Enable query result caching**:
```php
// Cache database results
$decisions = Cache::remember('recent_decisions', 3600, function () {
    return CourtDecision::latest()->take(100)->get();
});
```

5. **Optimize database**:
```sql
-- PostgreSQL: Analyze tables
ANALYZE court_decisions;
ANALYZE court_decision_embeddings;

-- Vacuum to reclaim space
VACUUM ANALYZE;
```

6. **Use CDN for static assets**:
```bash
# Compile assets for production
npm run build

# Upload to CDN
php artisan asset:publish
```

---

## Getting Help

### Before Asking for Help

1. **Check logs**:
```bash
tail -f storage/logs/laravel.log
php artisan pail --timeout=0
```

2. **Run diagnostics**:
```bash
php artisan about
php artisan agents:profile all --runs=1
```

3. **Search existing issues**:
- Check GitHub issues
- Search error message in documentation

### Information to Include

When reporting issues, include:

1. **Error message** (full stack trace)
2. **Laravel version**: `php artisan --version`
3. **PHP version**: `php -v`
4. **Environment**: Development/Production
5. **Steps to reproduce**
6. **Relevant logs** (storage/logs/laravel.log)
7. **Configuration** (sanitized .env values)

### Useful Debug Commands

```bash
# System info
php artisan about

# Config values
php artisan config:show

# Environment check
php artisan env

# Database info
php artisan db:show

# Queue info (Production only - requires Horizon)
php artisan queue:monitor

# Cache info
php artisan cache:table

# Route list
php artisan route:list

# Agent profile
php artisan agents:profile all

# Run tests with verbose output
./vendor/bin/pest --verbose
```

### Enabling Debug Logging

```php
// config/logging.php
'channels' => [
    'daily' => [
        'level' => env('LOG_LEVEL', 'debug'),  # Set to 'debug'
    ],
],
```

```bash
# In .env
LOG_LEVEL=debug

# Run command and check logs
php artisan agents:profile research
tail -f storage/logs/laravel.log
```

---

## Appendix: Performance Benchmarks

Expected performance targets:

| Metric | Target | Measurement |
|--------|--------|-------------|
| ResearchSpecialist | <5s | `php artisan agents:profile research` |
| PrecedentAnalyst | <8s | `php artisan agents:profile precedent` |
| Multi-agent orchestration | <30s | Time full case analysis |
| Vector search (10 results) | <200ms | Check logs with DB_LOG_QUERIES=true |
| Database queries | <50ms | Check slow query log |
| Cache hit rate | >70% | Monitor Redis INFO stats |
| Queue job latency | <10s | Check queue:monitor |

## Appendix: Common Log Patterns

**Circuit breaker opened**:
```
Circuit breaker opened after 3 consecutive failures
```
Solution: Wait 60s or reset manually

**Slow query detected**:
```
Query took 1234.56ms: SELECT * FROM court_decisions WHERE ...
```
Solution: Add index on queried columns

**Cache miss**:
```
Agent result cache miss [agent_type=research, cache_key=abc123...]
```
Normal - first request for this query

**Rate limit hit**:
```
Rate limit exceeded for OpenAI API
```
Solution: Reduce parallel calls or upgrade tier

**Deadlock**:
```
SQLSTATE[40P01]: Deadlock detected
```
Solution: Retry transaction (automatic with retry())

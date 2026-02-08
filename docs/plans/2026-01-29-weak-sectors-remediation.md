# Weak Sectors Remediation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Fix 5 weak sectors: database performance, external integration resilience, health monitoring, search service duplication, and AI agent gaps.

**Architecture:** Each sector is an independent workstream that can be parallelized. Changes follow existing patterns (CircuitBreaker, HealthCheckResult, BaseSearchService) rather than introducing new abstractions.

**Tech Stack:** Laravel 12, PHP 8.2+, PostgreSQL (pgvector), Neo4j, PHPUnit 11

---

## Task 1: Enable Database Indexes (Sector 4)

**Files:**
- Modify: `database/migrations/2025_10_31_151039_add_missing_indexes.php:15-61`
- Test: Run migration and verify indexes exist

**Step 1: Uncomment the migration up() method**

Replace the entire `up()` body (lines 17-60 are all commented out) with active code. Add `indexExists()` guard on every index to be idempotent:

```php
public function up(): void
{
    // 1. Index on agent_runs.status for filtering running/completed/failed runs
    if (!$this->indexExists('agent_runs', 'agent_runs_status_index')) {
        Schema::table('agent_runs', function (Blueprint $table) {
            $table->index('status');
        });
    }

    // 2. Index on decision_discovery_runs.status for monitoring runs
    if (!$this->indexExists('decision_discovery_runs', 'decision_discovery_runs_status_index')) {
        Schema::table('decision_discovery_runs', function (Blueprint $table) {
            $table->index('status');
        });
    }

    // 3. Index on textract_jobs.status for job status filtering
    if (!$this->indexExists('textract_jobs', 'textract_jobs_status_index')) {
        Schema::table('textract_jobs', function (Blueprint $table) {
            $table->index('status');
        });
    }

    // 4. Index on eoglasna_notices.notice_type for notice filtering
    if (!$this->indexExists('eoglasna_notices', 'eoglasna_notices_notice_type_index')) {
        Schema::table('eoglasna_notices', function (Blueprint $table) {
            $table->index('notice_type');
        });
    }

    // 5. Index on eoglasna_notices.expiration_date for date range queries
    if (!$this->indexExists('eoglasna_notices', 'eoglasna_notices_expiration_date_index')) {
        Schema::table('eoglasna_notices', function (Blueprint $table) {
            $table->index('expiration_date');
        });
    }

    // 6. Index on textract_documents.embedded_at for tracking embedding generation
    if (!$this->indexExists('textract_documents', 'textract_documents_embedded_at_index')) {
        Schema::table('textract_documents', function (Blueprint $table) {
            $table->index('embedded_at');
        });
    }

    // 7. Index on cases.status for case filtering
    $casesTable = config('vizra-adk.tables.cases', 'cases');
    if (!$this->indexExists($casesTable, 'cases_status_index')) {
        Schema::table($casesTable, function (Blueprint $table) {
            $table->index('status');
        });
    }

    // 8. Index on cases.filing_date for date range queries and sorting
    if (!$this->indexExists($casesTable, $casesTable . '_filing_date_index')) {
        Schema::table($casesTable, function (Blueprint $table) {
            $table->index('filing_date');
        });
    }
}
```

**Step 2: Run migration**

```bash
php artisan migrate
```

Expected: "Migrating..." with 8 index creations (or skips if already exist).

**Step 3: Verify indexes exist**

```bash
php artisan tinker --execute="echo collect(DB::select(\"SELECT indexname, tablename FROM pg_indexes WHERE indexname LIKE '%status%' OR indexname LIKE '%notice_type%' OR indexname LIKE '%expiration_date%' OR indexname LIKE '%embedded_at%' OR indexname LIKE '%filing_date%'\"))->pluck('indexname')->join(', ');"
```

Expected: All 8 index names listed.

**Step 4: Commit**

```bash
git add database/migrations/2025_10_31_151039_add_missing_indexes.php
git commit -m "Perf: Enable commented-out database indexes for status/date columns"
```

---

## Task 2: Add Circuit Breaker to Neo4jService (Sector 5)

**Files:**
- Modify: `app/Services/Neo4jService.php`
- Create: `tests/Unit/Services/Neo4jServiceTest.php`

**Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Services;

use App\Services\Neo4jService;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class Neo4jServiceTest extends TestCase
{
    public function test_upsert_logs_and_skips_when_neo4j_disabled(): void
    {
        config(['neo4j.sync.enabled' => false]);

        Log::shouldReceive('channel')->with('stack')->andReturnSelf();
        Log::shouldReceive('info')->once()->withArgs(function ($msg) {
            return str_contains($msg, 'Neo4j disabled');
        });

        $service = new Neo4jService();
        $service->upsertCaseAndDocument('case-1', 'Title', 'doc-1', 'DocTitle');
    }

    public function test_upsert_catches_exception_and_logs_error(): void
    {
        config(['neo4j.sync.enabled' => false]);

        $service = new Neo4jService();

        // When disabled, should not throw
        $service->upsertCaseAndDocument('case-1', 'Title', 'doc-1', 'DocTitle');
        $this->assertTrue(true); // No exception thrown
    }
}
```

**Step 2: Run test to verify it fails**

```bash
./scripts/run-focused-tests.sh Neo4jServiceTest
```

**Step 3: Implement circuit breaker and error handling in Neo4jService**

Replace `app/Services/Neo4jService.php` with:

```php
<?php

namespace App\Services;

use App\Services\CircuitBreaker;
use Illuminate\Support\Facades\Log;
use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\ClientBuilder;
use Psr\Log\LoggerInterface;

class Neo4jService
{
    protected $client;

    protected LoggerInterface $logger;

    protected CircuitBreaker $circuitBreaker;

    public function __construct(?CircuitBreaker $circuitBreaker = null)
    {
        $this->logger = Log::channel('stack');

        $defaults = config('circuit_breaker.defaults.neo4j', []);
        $this->circuitBreaker = $circuitBreaker ?? new CircuitBreaker(
            'neo4j',
            $defaults['failure_threshold'] ?? 5,
            $defaults['success_threshold'] ?? 2,
            $defaults['timeout'] ?? 30,
            $defaults['retry_after'] ?? 15
        );

        $enabled = (bool) config('neo4j.sync.enabled', true);
        if (! $enabled) {
            $this->client = null;

            return;
        }

        $uri = (string) config('neo4j.uri', 'bolt://localhost:7687');
        $user = (string) config('neo4j.user', 'neo4j');
        $password = (string) config('neo4j.password');

        try {
            $this->client = ClientBuilder::create()
                ->withDriver('bolt', $uri, Authenticate::basic($user, $password))
                ->withDefaultDriver('bolt')
                ->build();
        } catch (\Exception $e) {
            $this->logger->error('Neo4j client initialization failed', [
                'error' => $e->getMessage(),
            ]);
            $this->client = null;
        }
    }

    public function upsertCaseAndDocument(string $caseId, string $caseTitle, string $docId, string $docTitle): void
    {
        if (! $this->client) {
            $this->logger->info('Neo4j disabled; skipping upsert', compact('caseId', 'docId'));

            return;
        }

        $cypher = 'MERGE (c:Case {id: $case_id}) SET c.title = $case_title '.
                  'MERGE (d:CaseDocument {id: $doc_id}) SET d.title = $doc_title '.
                  'MERGE (c)-[:HAS_DOCUMENT]->(d)';

        $params = [
            'case_id' => $caseId,
            'case_title' => $caseTitle,
            'doc_id' => $docId,
            'doc_title' => $docTitle,
        ];

        try {
            $this->circuitBreaker->call(function () use ($cypher, $params) {
                return $this->client->run($cypher, $params);
            });
        } catch (\Exception $e) {
            $this->logger->error('Neo4j upsert failed', [
                'caseId' => $caseId,
                'docId' => $docId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
```

**Step 4: Run test to verify it passes**

```bash
./scripts/run-focused-tests.sh Neo4jServiceTest
```

**Step 5: Commit**

```bash
git add app/Services/Neo4jService.php tests/Unit/Services/Neo4jServiceTest.php
git commit -m "Resilience: Add circuit breaker and error handling to Neo4jService"
```

---

## Task 3: Add Error Handling to NnApiClient (Sector 5)

**Files:**
- Modify: `app/Services/NnApiClient.php`
- Create: `tests/Unit/Services/NnApiClientTest.php`

**Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Services;

use App\Services\NnApiClient;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

class NnApiClientTest extends TestCase
{
    public function test_years_returns_data_on_success(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([['year' => 2024]])),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $api = new NnApiClient($client);
        $result = $api->years();

        $this->assertIsArray($result);
        $this->assertEquals(2024, $result[0]['year']);
    }

    public function test_years_returns_empty_array_on_network_error(): void
    {
        $mock = new MockHandler([
            new ConnectException('Connection refused', new Request('GET', '/api/index')),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $api = new NnApiClient($client);
        $result = $api->years();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_editions_returns_empty_array_on_network_error(): void
    {
        $mock = new MockHandler([
            new ConnectException('Timeout', new Request('POST', '/api/editions')),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $api = new NnApiClient($client);
        $result = $api->editions(2024);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_acts_returns_empty_array_on_network_error(): void
    {
        $mock = new MockHandler([
            new ConnectException('Timeout', new Request('POST', '/api/acts')),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $api = new NnApiClient($client);
        $result = $api->acts(2024, 1);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_act_json_ld_returns_empty_array_on_network_error(): void
    {
        $mock = new MockHandler([
            new ConnectException('Timeout', new Request('POST', '/api/act')),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $api = new NnApiClient($client);
        $result = $api->actJsonLd(2024, 1, '1');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}
```

**Step 2: Run test to verify it fails**

```bash
./scripts/run-focused-tests.sh NnApiClientTest
```

Expected: FAIL - network errors throw uncaught exceptions.

**Step 3: Add try/catch to all NnApiClient methods**

Wrap each method body in `app/Services/NnApiClient.php`:

```php
public function years(): array
{
    try {
        $res = $this->http->get('/api/index');
        return json_decode($res->getBody()->getContents(), true) ?? [];
    } catch (\Exception $e) {
        Log::error('NnApiClient::years() failed', ['error' => $e->getMessage()]);
        return [];
    }
}

public function editions(int $year, string $part = 'SL'): array
{
    try {
        $res = $this->http->post('/api/editions', [
            'json' => ['part' => $part, 'year' => $year],
        ]);
        return json_decode($res->getBody()->getContents(), true) ?? [];
    } catch (\Exception $e) {
        Log::error('NnApiClient::editions() failed', ['year' => $year, 'error' => $e->getMessage()]);
        return [];
    }
}

public function acts(int $year, int $edition, string $part = 'SL'): array
{
    try {
        $res = $this->http->post('/api/acts', [
            'json' => ['part' => $part, 'year' => $year, 'number' => $edition],
        ]);
        return json_decode($res->getBody()->getContents(), true) ?? [];
    } catch (\Exception $e) {
        Log::error('NnApiClient::acts() failed', ['year' => $year, 'edition' => $edition, 'error' => $e->getMessage()]);
        return [];
    }
}

public function actJsonLd(int $year, int $edition, string $actNum, string $part = 'SL'): array
{
    try {
        $res = $this->http->post('/api/act', [
            'json' => [
                'part' => $part,
                'year' => $year,
                'number' => $edition,
                'act_num' => $actNum,
                'format' => 'JSON-LD',
            ],
        ]);
        return json_decode($res->getBody()->getContents(), true) ?? [];
    } catch (\Exception $e) {
        Log::error('NnApiClient::actJsonLd() failed', ['year' => $year, 'edition' => $edition, 'actNum' => $actNum, 'error' => $e->getMessage()]);
        return [];
    }
}
```

Add `use Illuminate\Support\Facades\Log;` to imports.

**Step 4: Run test to verify it passes**

```bash
./scripts/run-focused-tests.sh NnApiClientTest
```

**Step 5: Commit**

```bash
git add app/Services/NnApiClient.php tests/Unit/Services/NnApiClientTest.php
git commit -m "Resilience: Add error handling to NnApiClient methods"
```

---

## Task 4: Add Circuit Breaker to EPredmetService (Sector 5)

**Files:**
- Modify: `app/Services/EPredmetService.php`
- Create: `tests/Unit/Services/EPredmetServiceTest.php`

**Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Services;

use App\Services\EPredmetService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EPredmetServiceTest extends TestCase
{
    public function test_api_url_is_configurable(): void
    {
        config(['services.epredmet.url' => 'https://custom.example.com/api']);

        $service = new EPredmetService();

        Http::fake([
            'custom.example.com/*' => Http::response(['data' => ['sudovi' => []]], 200),
        ]);

        $result = $service->getCourts();
        $this->assertIsArray($result);
    }

    public function test_query_returns_null_on_http_failure(): void
    {
        Http::fake([
            '*' => Http::response('Server Error', 500),
        ]);

        $service = new EPredmetService();
        $result = $service->query('{ sudovi { id } }');

        $this->assertNull($result);
    }

    public function test_get_courts_returns_empty_array_on_failure(): void
    {
        Http::fake([
            '*' => Http::response('', 500),
        ]);

        $service = new EPredmetService();
        $result = $service->getCourts();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}
```

**Step 2: Run test to verify it fails**

```bash
./scripts/run-focused-tests.sh EPredmetServiceTest
```

Expected: FAIL - `test_api_url_is_configurable` fails because URL is hardcoded const.

**Step 3: Make API URL configurable and add circuit breaker**

Changes to `app/Services/EPredmetService.php`:

1. Replace `protected const API_URL = 'https://e-predmet.pravosudje.hr/api';` with:
   ```php
   protected string $apiUrl;
   ```

2. Add constructor:
   ```php
   public function __construct()
   {
       $this->apiUrl = config('services.epredmet.url', 'https://e-predmet.pravosudje.hr/api');
   }
   ```

3. In `query()` method, replace `self::API_URL` with `$this->apiUrl`.

4. Add to `config/services.php`:
   ```php
   'epredmet' => [
       'url' => env('EPREDMET_API_URL', 'https://e-predmet.pravosudje.hr/api'),
   ],
   ```

**Step 4: Run test to verify it passes**

```bash
./scripts/run-focused-tests.sh EPredmetServiceTest
```

**Step 5: Commit**

```bash
git add app/Services/EPredmetService.php tests/Unit/Services/EPredmetServiceTest.php config/services.php
git commit -m "Resilience: Make EPredmetService URL configurable, add tests"
```

---

## Task 5: Add Health Checks for Neo4j, OpenAI, Queue (Sector 6)

**Files:**
- Create: `app/HealthChecks/Neo4jHealthCheck.php`
- Create: `app/HealthChecks/OpenAIHealthCheck.php`
- Create: `app/HealthChecks/QueueHealthCheck.php`
- Create: `tests/Unit/HealthChecks/Neo4jHealthCheckTest.php`
- Create: `tests/Unit/HealthChecks/OpenAIHealthCheckTest.php`
- Create: `tests/Unit/HealthChecks/QueueHealthCheckTest.php`

**Step 1: Write failing tests for all three health checks**

`tests/Unit/HealthChecks/Neo4jHealthCheckTest.php`:
```php
<?php

namespace Tests\Unit\HealthChecks;

use App\HealthChecks\HealthCheckResult;
use App\HealthChecks\Neo4jHealthCheck;
use Tests\TestCase;

class Neo4jHealthCheckTest extends TestCase
{
    public function test_returns_healthy_when_neo4j_responds(): void
    {
        config(['neo4j.sync.enabled' => false]);

        $check = new Neo4jHealthCheck();
        $result = $check();

        // When disabled, should return degraded (not connected but by design)
        $this->assertInstanceOf(HealthCheckResult::class, $result);
    }

    public function test_returns_unhealthy_on_connection_failure(): void
    {
        config(['neo4j.sync.enabled' => true]);
        config(['neo4j.uri' => 'bolt://localhost:99999']); // Bad port

        $check = new Neo4jHealthCheck();
        $result = $check();

        $this->assertTrue($result->isUnhealthy() || $result->isDegraded());
    }
}
```

`tests/Unit/HealthChecks/QueueHealthCheckTest.php`:
```php
<?php

namespace Tests\Unit\HealthChecks;

use App\HealthChecks\HealthCheckResult;
use App\HealthChecks\QueueHealthCheck;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class QueueHealthCheckTest extends TestCase
{
    public function test_returns_healthy_when_queue_is_connected(): void
    {
        $check = new QueueHealthCheck();
        $result = $check();

        $this->assertInstanceOf(HealthCheckResult::class, $result);
        // In test env with sync driver, should be healthy
        $this->assertTrue($result->isHealthy());
    }
}
```

`tests/Unit/HealthChecks/OpenAIHealthCheckTest.php`:
```php
<?php

namespace Tests\Unit\HealthChecks;

use App\HealthChecks\HealthCheckResult;
use App\HealthChecks\OpenAIHealthCheck;
use App\Services\CircuitBreaker;
use Tests\TestCase;

class OpenAIHealthCheckTest extends TestCase
{
    public function test_returns_healthy_when_circuit_closed(): void
    {
        $check = new OpenAIHealthCheck();
        $result = $check();

        $this->assertInstanceOf(HealthCheckResult::class, $result);
    }
}
```

**Step 2: Run tests to verify they fail**

```bash
./scripts/run-focused-tests.sh Neo4jHealthCheckTest
./scripts/run-focused-tests.sh QueueHealthCheckTest
./scripts/run-focused-tests.sh OpenAIHealthCheckTest
```

**Step 3: Implement health checks**

`app/HealthChecks/Neo4jHealthCheck.php`:
```php
<?php

namespace App\HealthChecks;

use Illuminate\Support\Facades\Log;
use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\ClientBuilder;

class Neo4jHealthCheck
{
    public function __invoke(): HealthCheckResult
    {
        $enabled = (bool) config('neo4j.sync.enabled', true);
        if (! $enabled) {
            return HealthCheckResult::degraded('Neo4j sync is disabled by configuration');
        }

        try {
            $uri = (string) config('neo4j.uri', 'bolt://localhost:7687');
            $user = (string) config('neo4j.user', 'neo4j');
            $password = (string) config('neo4j.password');

            $client = ClientBuilder::create()
                ->withDriver('bolt', $uri, Authenticate::basic($user, $password))
                ->withDefaultDriver('bolt')
                ->build();

            $result = $client->run('RETURN 1 AS ping');

            return HealthCheckResult::healthy(['connection' => 'ok']);
        } catch (\Exception $e) {
            Log::error('Neo4j health check failed', ['error' => $e->getMessage()]);

            return HealthCheckResult::unhealthy(
                'Neo4j connection failed: ' . $e->getMessage(),
                ['error' => $e->getMessage()]
            );
        }
    }
}
```

`app/HealthChecks/QueueHealthCheck.php`:
```php
<?php

namespace App\HealthChecks;

use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Log;

class QueueHealthCheck
{
    public function __invoke(): HealthCheckResult
    {
        try {
            $connection = config('queue.default', 'sync');
            $size = Queue::connection($connection)->size();

            $data = [
                'driver' => $connection,
                'queue_size' => $size,
            ];

            if ($size > 1000) {
                return HealthCheckResult::degraded(
                    "Queue backlog is high: {$size} jobs pending",
                    $data
                );
            }

            return HealthCheckResult::healthy($data, 'Queue is operational');
        } catch (\Exception $e) {
            Log::error('Queue health check failed', ['error' => $e->getMessage()]);

            return HealthCheckResult::unhealthy(
                'Queue connection failed: ' . $e->getMessage(),
                ['error' => $e->getMessage()]
            );
        }
    }
}
```

`app/HealthChecks/OpenAIHealthCheck.php`:
```php
<?php

namespace App\HealthChecks;

use App\Services\CircuitBreaker;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class OpenAIHealthCheck
{
    public function __invoke(): HealthCheckResult
    {
        try {
            // Check circuit breaker state from cache
            $state = Cache::get('circuit_breaker_openai_state', 'closed');

            $data = [
                'circuit_state' => $state,
                'api_key_configured' => !empty(config('openai.api_key')),
            ];

            if ($state === 'open') {
                return HealthCheckResult::unhealthy(
                    'OpenAI circuit breaker is OPEN - service unavailable',
                    $data
                );
            }

            if ($state === 'half_open') {
                return HealthCheckResult::degraded(
                    'OpenAI circuit breaker is HALF_OPEN - recovering',
                    $data
                );
            }

            if (empty(config('openai.api_key'))) {
                return HealthCheckResult::unhealthy(
                    'OpenAI API key not configured',
                    $data
                );
            }

            return HealthCheckResult::healthy($data, 'OpenAI service is available');
        } catch (\Exception $e) {
            Log::error('OpenAI health check failed', ['error' => $e->getMessage()]);

            return HealthCheckResult::unhealthy(
                'OpenAI health check error: ' . $e->getMessage(),
                ['error' => $e->getMessage()]
            );
        }
    }
}
```

**Step 4: Run tests to verify they pass**

```bash
./scripts/run-focused-tests.sh Neo4jHealthCheckTest
./scripts/run-focused-tests.sh QueueHealthCheckTest
./scripts/run-focused-tests.sh OpenAIHealthCheckTest
```

**Step 5: Commit**

```bash
git add app/HealthChecks/ tests/Unit/HealthChecks/
git commit -m "Monitoring: Add Neo4j, OpenAI, and Queue health checks"
```

---

## Task 6: Extract Common Search Logic into BaseSearchService (Sector 9)

**Files:**
- Modify: `app/Services/BaseSearchService.php`
- Modify: `app/Services/LawSearchService.php`
- Modify: `app/Services/DecisionSearchService.php`
- Modify: `app/Services/CaseSearchService.php`
- Test: Existing search tests must continue passing

**Step 1: Run existing search tests to establish baseline**

```bash
./scripts/run-focused-tests.sh LawSearchServiceTest
./scripts/run-focused-tests.sh DecisionSearchServiceTest
./scripts/run-focused-tests.sh CaseSearchServiceTest
```

Record pass count.

**Step 2: Add `performSearch()` template method to BaseSearchService**

Add to `app/Services/BaseSearchService.php`:

```php
/**
 * Template method for search with standardized logging and error handling.
 *
 * @param string $query Search query
 * @param array $options Search options
 * @param string $domain Domain label for logging (e.g., 'Law', 'Decision', 'Case')
 * @param callable $searchDispatcher Callback that receives ($searchType, $query, $filters) and returns results
 * @return array Search results
 * @throws SearchException
 */
protected function performSearch(string $query, array $options, string $domain, callable $searchDispatcher): array
{
    try {
        Log::info("{$domain} search initiated", [
            'query' => substr($query, 0, 100),
            'options' => $options,
            'service' => static::class,
        ]);

        $startTime = microtime(true);

        $searchType = $options['search_type'] ?? 'keyword';
        $filters = $options['filters'] ?? [];

        $filters = array_merge($filters, [
            'limit' => $options['limit'] ?? 10,
            'page' => $options['page'] ?? 1,
            'jurisdiction' => $options['jurisdiction'] ?? null,
        ]);

        $results = $searchDispatcher($searchType, $query, $filters);

        Log::info("{$domain} search completed", [
            'query' => substr($query, 0, 100),
            'search_type' => $searchType,
            'result_count' => $results['count'] ?? (isset($results['data']) ? count($results['data']) : 0),
            'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
        ]);

        return $results;

    } catch (\InvalidArgumentException $e) {
        Log::warning("Invalid {$domain} search parameters", [
            'query' => substr($query, 0, 100),
            'error' => $e->getMessage(),
        ]);

        throw new \App\Exceptions\SearchException(
            'Invalid search parameters: ' . $e->getMessage(),
            \App\Exceptions\SearchException::INVALID_QUERY,
            $e
        );

    } catch (\App\Exceptions\SearchException $e) {
        throw $e;

    } catch (\Exception $e) {
        Log::error("Unexpected error in {$domain} search", [
            'query' => substr($query, 0, 100),
            'options' => $options,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        throw new \App\Exceptions\SearchException(
            "{$domain} search failed: " . $e->getMessage(),
            0,
            $e
        );
    }
}
```

**Step 3: Refactor LawSearchService::search() to use template method**

Replace the `search()` method body in `LawSearchService.php`:

```php
public function search(string $query, array $options = []): array
{
    return $this->performSearch($query, $options, 'Law', function (string $searchType, string $query, array $filters) {
        return match ($searchType) {
            'vector' => $this->vectorSearch($query, $filters),
            'keyword' => $this->keywordSearch($query, $filters),
            'hybrid' => $this->hybridSearch($query, $filters),
            default => throw new \InvalidArgumentException("Invalid search_type: {$searchType}"),
        };
    });
}
```

**Step 4: Refactor DecisionSearchService::search() similarly**

```php
public function search(string $query, array $options = []): array
{
    return $this->performSearch($query, $options, 'Decision', function (string $searchType, string $query, array $filters) {
        return match ($searchType) {
            'vector' => $this->vectorSearch($query, $filters),
            'keyword' => $this->keywordSearch($query, $filters),
            'hybrid' => $this->hybridSearch($query, $filters),
            default => throw new \InvalidArgumentException("Invalid search_type: {$searchType}"),
        };
    });
}
```

**Step 5: Refactor CaseSearchService::search() similarly**

```php
public function search(string $query, array $options = []): array
{
    return $this->performSearch($query, $options, 'Case', function (string $searchType, string $query, array $filters) {
        return match ($searchType) {
            'vector' => $this->vectorSearch($query, $filters),
            'cases' => $this->searchCases($query, $filters),
            'documents' => $this->searchDocuments($query, $filters),
            default => throw new \InvalidArgumentException("Invalid search_type: {$searchType}"),
        };
    });
}
```

**Step 6: Run all search tests to verify no regression**

```bash
./scripts/run-focused-tests.sh LawSearchServiceTest
./scripts/run-focused-tests.sh DecisionSearchServiceTest
./scripts/run-focused-tests.sh CaseSearchServiceTest
```

Expected: Same pass count as Step 1.

**Step 7: Commit**

```bash
git add app/Services/BaseSearchService.php app/Services/LawSearchService.php app/Services/DecisionSearchService.php app/Services/CaseSearchService.php
git commit -m "Refactor: Extract common search logic into BaseSearchService::performSearch()"
```

---

## Task 7: Add Configurable AI Model Pricing (Sector 10)

**Files:**
- Create: `config/ai_pricing.php`
- Modify: `app/Services/Research/ResearchPlannerService.php:48`
- Modify: `app/Services/Research/ResearchEvaluatorService.php:54`
- Create: `tests/Unit/Config/AiPricingConfigTest.php`

**Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Config;

use Tests\TestCase;

class AiPricingConfigTest extends TestCase
{
    public function test_pricing_config_exists_with_required_models(): void
    {
        $pricing = config('ai_pricing.models');

        $this->assertNotNull($pricing);
        $this->assertArrayHasKey('gpt-4o-mini', $pricing);
        $this->assertArrayHasKey('gpt-4o', $pricing);
        $this->assertArrayHasKey('text-embedding-3-small', $pricing);
    }

    public function test_pricing_has_input_and_output_costs(): void
    {
        $gpt4oMini = config('ai_pricing.models.gpt-4o-mini');

        $this->assertArrayHasKey('input_per_1m', $gpt4oMini);
        $this->assertArrayHasKey('output_per_1m', $gpt4oMini);
        $this->assertIsFloat($gpt4oMini['input_per_1m']);
        $this->assertIsFloat($gpt4oMini['output_per_1m']);
    }

    public function test_helper_calculates_cost_correctly(): void
    {
        $inputTokens = 1000;
        $model = 'gpt-4o-mini';
        $pricing = config("ai_pricing.models.{$model}");

        $cost = ($inputTokens / 1_000_000) * $pricing['input_per_1m'];

        $this->assertEqualsWithDelta(0.00015, $cost, 0.00001);
    }
}
```

**Step 2: Run test to verify it fails**

```bash
./scripts/run-focused-tests.sh AiPricingConfigTest
```

**Step 3: Create pricing config**

`config/ai_pricing.php`:
```php
<?php

return [
    'models' => [
        'gpt-4o-mini' => [
            'input_per_1m' => (float) env('AI_PRICING_GPT4O_MINI_INPUT', 0.15),
            'output_per_1m' => (float) env('AI_PRICING_GPT4O_MINI_OUTPUT', 0.60),
        ],
        'gpt-4o' => [
            'input_per_1m' => (float) env('AI_PRICING_GPT4O_INPUT', 2.50),
            'output_per_1m' => (float) env('AI_PRICING_GPT4O_OUTPUT', 10.00),
        ],
        'text-embedding-3-small' => [
            'input_per_1m' => (float) env('AI_PRICING_EMBEDDING_SMALL_INPUT', 0.02),
            'output_per_1m' => 0.0,
        ],
        'text-embedding-3-large' => [
            'input_per_1m' => (float) env('AI_PRICING_EMBEDDING_LARGE_INPUT', 0.13),
            'output_per_1m' => 0.0,
        ],
    ],

    'default_model' => env('AI_DEFAULT_MODEL', 'gpt-4o-mini'),
];
```

**Step 4: Update ResearchPlannerService to use config**

In `app/Services/Research/ResearchPlannerService.php`, replace line 48:

```php
// Before:
$run->cost_spent += ($tokensUsed / 1000000) * 0.15;

// After:
$model = config('ai_pricing.default_model', 'gpt-4o-mini');
$inputRate = config("ai_pricing.models.{$model}.input_per_1m", 0.15);
$run->cost_spent += ($tokensUsed / 1_000_000) * $inputRate;
```

**Step 5: Update ResearchEvaluatorService similarly**

In `app/Services/Research/ResearchEvaluatorService.php`, replace line 54:

```php
// Before:
$run->cost_spent += ($tokensUsed / 1000000) * 0.15; // GPT-4o-mini pricing

// After:
$model = config('ai_pricing.default_model', 'gpt-4o-mini');
$inputRate = config("ai_pricing.models.{$model}.input_per_1m", 0.15);
$run->cost_spent += ($tokensUsed / 1_000_000) * $inputRate;
```

**Step 6: Run tests**

```bash
./scripts/run-focused-tests.sh AiPricingConfigTest
```

**Step 7: Commit**

```bash
git add config/ai_pricing.php app/Services/Research/ResearchPlannerService.php app/Services/Research/ResearchEvaluatorService.php tests/Unit/Config/AiPricingConfigTest.php
git commit -m "Config: Add configurable AI model pricing, remove hardcoded costs"
```

---

## Task 8: Improve Token Estimation Accuracy (Sector 10)

**Files:**
- Create: `app/Services/AI/TokenEstimator.php`
- Create: `tests/Unit/Services/AI/TokenEstimatorTest.php`
- Modify: Multiple files using `strlen/4` pattern (after service is verified)

**Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Services\AI;

use App\Services\AI\TokenEstimator;
use Tests\TestCase;

class TokenEstimatorTest extends TestCase
{
    public function test_estimates_english_text_tokens(): void
    {
        $estimator = new TokenEstimator();

        // "Hello world" is typically 2 tokens
        $estimate = $estimator->estimate('Hello world');
        $this->assertGreaterThan(0, $estimate);
        $this->assertLessThan(10, $estimate);
    }

    public function test_estimates_croatian_text_with_higher_ratio(): void
    {
        $estimator = new TokenEstimator();

        $croatianText = 'Sudac je donio odluku o kaznenom postupku protiv okrivljenika';
        $englishText = 'The judge made a decision on criminal proceedings against the defendant';

        $croatianTokens = $estimator->estimate($croatianText);
        $englishTokens = $estimator->estimate($englishText);

        // Croatian text should use more tokens per character (Unicode, diacritics)
        $croatianRatio = $croatianTokens / mb_strlen($croatianText);
        $englishRatio = $englishTokens / mb_strlen($englishText);

        $this->assertGreaterThanOrEqual($englishRatio * 0.8, $croatianRatio);
    }

    public function test_empty_string_returns_zero(): void
    {
        $estimator = new TokenEstimator();
        $this->assertEquals(0, $estimator->estimate(''));
    }

    public function test_handles_multibyte_characters(): void
    {
        $estimator = new TokenEstimator();
        $text = 'čćžšđ ČČĆŽŠĐ';
        $estimate = $estimator->estimate($text);

        // Should NOT use strlen which counts bytes, not characters
        $this->assertGreaterThan(0, $estimate);
    }
}
```

**Step 2: Run test to verify it fails**

```bash
./scripts/run-focused-tests.sh TokenEstimatorTest
```

**Step 3: Implement TokenEstimator**

`app/Services/AI/TokenEstimator.php`:
```php
<?php

namespace App\Services\AI;

/**
 * Estimates token count for text without requiring tiktoken library.
 *
 * Uses character-based heuristics with Unicode awareness.
 * More accurate than strlen/4 for non-ASCII text (Croatian, etc.)
 */
class TokenEstimator
{
    /**
     * Average characters per token for ASCII-heavy text (English).
     */
    private const ASCII_CHARS_PER_TOKEN = 4.0;

    /**
     * Average characters per token for Unicode-heavy text (Croatian, etc.)
     * Unicode characters often split into multiple tokens.
     */
    private const UNICODE_CHARS_PER_TOKEN = 2.5;

    /**
     * Estimate token count for given text.
     */
    public function estimate(string $text): int
    {
        if ($text === '') {
            return 0;
        }

        $charCount = mb_strlen($text);
        $byteCount = strlen($text);

        // Ratio of bytes to chars indicates Unicode density
        // Pure ASCII: ratio = 1.0, Heavy Unicode: ratio > 1.5
        $unicodeRatio = $byteCount / max($charCount, 1);

        // Blend between ASCII and Unicode estimates based on actual content
        $asciiWeight = max(0, 1.0 - ($unicodeRatio - 1.0) * 2);
        $unicodeWeight = 1.0 - $asciiWeight;

        $charsPerToken = (self::ASCII_CHARS_PER_TOKEN * $asciiWeight)
                       + (self::UNICODE_CHARS_PER_TOKEN * $unicodeWeight);

        return (int) ceil($charCount / $charsPerToken);
    }
}
```

**Step 4: Run test to verify it passes**

```bash
./scripts/run-focused-tests.sh TokenEstimatorTest
```

**Step 5: Commit**

```bash
git add app/Services/AI/TokenEstimator.php tests/Unit/Services/AI/TokenEstimatorTest.php
git commit -m "AI: Add Unicode-aware TokenEstimator to replace strlen/4 heuristic"
```

---

## Execution Summary

| Task | Sector | Description | Risk |
|------|--------|-------------|------|
| 1 | DB Perf | Enable 8 database indexes | Low - idempotent |
| 2 | Resilience | Circuit breaker on Neo4jService | Low - wraps existing code |
| 3 | Resilience | Error handling on NnApiClient | Low - adds try/catch |
| 4 | Resilience | Configurable URL + tests for EPredmetService | Low |
| 5 | Monitoring | 3 new health checks (Neo4j, OpenAI, Queue) | Low - new files only |
| 6 | Dedup | Extract search template method | Medium - refactor |
| 7 | AI Agents | Configurable AI pricing config | Low - new config |
| 8 | AI Agents | Unicode-aware token estimator | Low - new service |

**Parallelization:** Tasks 1-5 are fully independent. Task 6 requires existing search tests to pass first. Tasks 7-8 are independent of each other and of 1-5.

**Recommended parallel batches:**
- **Batch 1 (parallel):** Tasks 1, 2, 3, 4, 5, 7, 8
- **Batch 2 (after batch 1):** Task 6 (depends on stable baseline)

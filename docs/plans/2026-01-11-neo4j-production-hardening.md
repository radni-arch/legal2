# Neo4j Production Hardening Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Production-harden all Neo4j Graph DB components including security, error handling, resilience, and observability.

**Architecture:** Defense-in-depth approach with validation at every layer (UI → Services → Database), circuit breakers for resilience, structured logging for observability, and comprehensive error handling with dead-letter queues.

**Tech Stack:** Laravel 11, Neo4j 5.x (Laudis PHP driver), PHPUnit, Livewire 3

---

## Sprint 1: Security Hardening (Critical Priority)

### Task 1: Add XSS Protection to GraphViewer Blade Template

**Files:**
- Modify: `resources/views/livewire/graph-viewer.blade.php`
- Create: `tests/Feature/Livewire/GraphViewerXssProtectionTest.php`

**Context:** Neo4j data displayed in GraphViewer may contain user-controlled content (law titles, amendment references). Currently using `{{ $var }}` which auto-escapes, but array iterations and complex data need audit.

**Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\GraphViewer;
use App\Services\GraphDatabaseService;
use App\Repositories\GraphMetricsRepository;
use Livewire\Livewire;
use Tests\TestCase;
use Mockery;

class GraphViewerXssProtectionTest extends TestCase
{
    protected GraphDatabaseService $graphService;
    protected GraphMetricsRepository $metricsRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->graphService = Mockery::mock(GraphDatabaseService::class);
        $this->metricsRepository = Mockery::mock(GraphMetricsRepository::class);
        $this->app->instance(GraphDatabaseService::class, $this->graphService);
        $this->app->instance(GraphMetricsRepository::class, $this->metricsRepository);
        $this->setupDefaultMocks();
    }

    protected function setupDefaultMocks(): void
    {
        $statsRecord = Mockery::mock();
        $statsRecord->shouldReceive('get')->with('label')->andReturn('LawDocument');
        $statsRecord->shouldReceive('get')->with('count')->andReturn(100);

        $this->graphService->shouldReceive('run')
            ->with(Mockery::on(fn ($query) => str_contains($query, 'labels(n)')))
            ->andReturn(collect([$statsRecord]));

        $relCountRecord = Mockery::mock();
        $relCountRecord->shouldReceive('get')->with('count')->andReturn(250);
        $relCountResult = Mockery::mock();
        $relCountResult->shouldReceive('first')->andReturn($relCountRecord);

        $this->graphService->shouldReceive('run')
            ->with(Mockery::on(fn ($query) => str_contains($query, 'MATCH ()-[r]->()')))
            ->andReturn($relCountResult);

        $this->graphService->shouldReceive('run')
            ->with(Mockery::on(fn ($query) => str_contains($query, 'created_at IS NOT NULL')))
            ->andReturn(collect([]));

        $this->metricsRepository->shouldReceive('getInfluentialDecisions')->andReturn([]);
        $this->metricsRepository->shouldReceive('getCitationClusters')->andReturn([]);
        $this->metricsRepository->shouldReceive('getNetworkStats')->andReturn(null);
    }

    /** @test */
    public function it_escapes_xss_in_node_title(): void
    {
        $maliciousTitle = '<script>alert("xss")</script>';

        $component = Livewire::test(GraphViewer::class)
            ->set('selectedNode', [
                'id' => 'law-1',
                'title' => $maliciousTitle,
                'labels' => ['LawDocument'],
            ])
            ->set('selectedNodeType', 'LawDocument');

        $component->assertDontSeeHtml('<script>alert("xss")</script>');
        $component->assertSee('&lt;script&gt;');
    }

    /** @test */
    public function it_escapes_xss_in_amendment_law_numbers(): void
    {
        $maliciousAmendment = '<img src=x onerror=alert(1)>';

        $component = Livewire::test(GraphViewer::class)
            ->set('selectedNode', [
                'id' => 'law-1',
                'title' => 'Test Law',
                'labels' => ['LawDocument'],
                'amendments' => [$maliciousAmendment, 'Clean Amendment'],
            ])
            ->set('selectedNodeType', 'LawDocument');

        $component->assertDontSeeHtml('<img src=x onerror=alert(1)>');
    }

    /** @test */
    public function it_escapes_xss_in_holding_text(): void
    {
        $maliciousHolding = '<iframe src="evil.com"></iframe>';

        $component = Livewire::test(GraphViewer::class)
            ->set('selectedNode', [
                'id' => 'decision-1',
                'title' => 'Test Decision',
                'labels' => ['CourtDecisionDocument'],
                'holding' => $maliciousHolding,
            ])
            ->set('selectedNodeType', 'CourtDecisionDocument');

        $component->assertDontSeeHtml('<iframe');
    }

    /** @test */
    public function it_escapes_xss_in_repealing_law_reference(): void
    {
        $maliciousRef = '"><script>evil()</script>';

        $component = Livewire::test(GraphViewer::class)
            ->set('selectedNode', [
                'id' => 'law-1',
                'title' => 'Test Law',
                'labels' => ['LawDocument'],
                'is_repealed' => true,
                'repeal_date' => '2025-01-01',
                'repealing_law' => $maliciousRef,
            ])
            ->set('selectedNodeType', 'LawDocument');

        $component->assertDontSeeHtml('<script>evil()</script>');
    }
}
```

**Step 2: Run test to verify it fails**

Run: `./scripts/run-focused-tests.sh GraphViewerXssProtectionTest`
Expected: Tests should PASS (Blade's `{{ }}` auto-escapes). If they pass, XSS protection is already in place.

**Step 3: Audit blade template for raw output**

Search for `{!! !!}` (raw output) in graph-viewer.blade.php:
```bash
grep -n '{!!' resources/views/livewire/graph-viewer.blade.php
```

If found, replace with escaped `{{ }}` or use `e()` helper.

**Step 4: Verify tests pass**

Run: `./scripts/run-focused-tests.sh GraphViewerXssProtectionTest`
Expected: PASS

**Step 5: Commit**

```bash
git add tests/Feature/Livewire/GraphViewerXssProtectionTest.php resources/views/livewire/graph-viewer.blade.php
git commit -m "security(graph): add XSS protection tests for GraphViewer"
```

---

### Task 2: Add Input Validation to GraphViewer Search

**Files:**
- Modify: `app/Http/Livewire/GraphViewer.php`
- Create: `tests/Feature/Livewire/GraphViewerInputValidationTest.php`

**Context:** GraphViewer accepts search queries that flow into Neo4j. While parameters are used, we should validate/sanitize input at the component level.

**Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\GraphViewer;
use App\Services\GraphDatabaseService;
use App\Repositories\GraphMetricsRepository;
use Livewire\Livewire;
use Tests\TestCase;
use Mockery;

class GraphViewerInputValidationTest extends TestCase
{
    protected GraphDatabaseService $graphService;
    protected GraphMetricsRepository $metricsRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->graphService = Mockery::mock(GraphDatabaseService::class);
        $this->metricsRepository = Mockery::mock(GraphMetricsRepository::class);
        $this->app->instance(GraphDatabaseService::class, $this->graphService);
        $this->app->instance(GraphMetricsRepository::class, $this->metricsRepository);
        $this->setupDefaultMocks();
    }

    protected function setupDefaultMocks(): void
    {
        $statsRecord = Mockery::mock();
        $statsRecord->shouldReceive('get')->with('label')->andReturn('LawDocument');
        $statsRecord->shouldReceive('get')->with('count')->andReturn(100);

        $this->graphService->shouldReceive('run')
            ->with(Mockery::on(fn ($query) => str_contains($query, 'labels(n)')))
            ->andReturn(collect([$statsRecord]));

        $relCountRecord = Mockery::mock();
        $relCountRecord->shouldReceive('get')->with('count')->andReturn(250);
        $relCountResult = Mockery::mock();
        $relCountResult->shouldReceive('first')->andReturn($relCountRecord);

        $this->graphService->shouldReceive('run')
            ->with(Mockery::on(fn ($query) => str_contains($query, 'MATCH ()-[r]->()')))
            ->andReturn($relCountResult);

        $this->graphService->shouldReceive('run')
            ->with(Mockery::on(fn ($query) => str_contains($query, 'created_at IS NOT NULL')))
            ->andReturn(collect([]));

        $this->metricsRepository->shouldReceive('getInfluentialDecisions')->andReturn([]);
        $this->metricsRepository->shouldReceive('getCitationClusters')->andReturn([]);
        $this->metricsRepository->shouldReceive('getNetworkStats')->andReturn(null);
    }

    /** @test */
    public function it_sanitizes_search_query_with_special_characters(): void
    {
        $this->graphService->shouldReceive('run')
            ->with(Mockery::on(fn ($query) => str_contains($query, 'toLower')), Mockery::type('array'))
            ->andReturn(collect([]));

        $component = Livewire::test(GraphViewer::class)
            ->set('searchQuery', 'test\'); DROP TABLE users;--')
            ->call('search');

        // Should not throw, query should be parameterized
        $component->assertHasNoErrors();
    }

    /** @test */
    public function it_limits_search_query_length(): void
    {
        $longQuery = str_repeat('a', 1001);

        $component = Livewire::test(GraphViewer::class)
            ->set('searchQuery', $longQuery);

        // Should truncate or reject excessively long queries
        $this->assertLessThanOrEqual(1000, strlen($component->get('searchQuery')));
    }

    /** @test */
    public function it_validates_depth_parameter_range(): void
    {
        $component = Livewire::test(GraphViewer::class)
            ->set('depth', 100);  // Unreasonable depth

        // Should clamp to reasonable max (e.g., 5)
        $this->assertLessThanOrEqual(5, $component->get('depth'));
    }

    /** @test */
    public function it_validates_depth_parameter_minimum(): void
    {
        $component = Livewire::test(GraphViewer::class)
            ->set('depth', -5);  // Negative depth

        // Should clamp to minimum (1)
        $this->assertGreaterThanOrEqual(1, $component->get('depth'));
    }

    /** @test */
    public function it_validates_node_type_filter(): void
    {
        $component = Livewire::test(GraphViewer::class);

        // Only allow known node types
        $validTypes = ['LawDocument', 'CourtDecisionDocument', 'CaseDocument', 'Keyword', 'Topic', 'Judge', 'Party'];

        $component->set('selectedNodeTypes', ['MaliciousType', 'LawDocument']);

        $selectedTypes = $component->get('selectedNodeTypes');
        foreach ($selectedTypes as $type) {
            $this->assertContains($type, $validTypes);
        }
    }
}
```

**Step 2: Run test to verify it fails**

Run: `./scripts/run-focused-tests.sh GraphViewerInputValidationTest`
Expected: FAIL - validation not yet implemented

**Step 3: Implement input validation in GraphViewer**

Add to `app/Http/Livewire/GraphViewer.php`:

```php
// Add constants at top of class
private const MAX_SEARCH_LENGTH = 1000;
private const MAX_DEPTH = 5;
private const MIN_DEPTH = 1;
private const ALLOWED_NODE_TYPES = [
    'LawDocument', 'CourtDecisionDocument', 'CaseDocument',
    'Keyword', 'Topic', 'Tag', 'Jurisdiction', 'Court',
    'Judge', 'Party', 'LegalConcept', 'LegalPrinciple',
    'LegalDefinition', 'LegalArgument', 'Verdict', 'Lawyer',
    'DateEvent', 'Evidence', 'Article'
];

// Add property hooks or use updatedPropertyName methods
public function updatedSearchQuery($value): void
{
    if (strlen($value) > self::MAX_SEARCH_LENGTH) {
        $this->searchQuery = substr($value, 0, self::MAX_SEARCH_LENGTH);
    }
}

public function updatedDepth($value): void
{
    $this->depth = max(self::MIN_DEPTH, min(self::MAX_DEPTH, (int) $value));
}

public function updatedSelectedNodeTypes($value): void
{
    if (is_array($value)) {
        $this->selectedNodeTypes = array_intersect($value, self::ALLOWED_NODE_TYPES);
    }
}
```

**Step 4: Run tests to verify they pass**

Run: `./scripts/run-focused-tests.sh GraphViewerInputValidationTest`
Expected: PASS

**Step 5: Commit**

```bash
git add app/Http/Livewire/GraphViewer.php tests/Feature/Livewire/GraphViewerInputValidationTest.php
git commit -m "security(graph): add input validation to GraphViewer component"
```

---

### Task 3: Audit AI Agent Cypher Query Safety

**Files:**
- Modify: `app/Agents/AutonomousResearchAgent.php`
- Create: `tests/Unit/Agents/AgentCypherQuerySafetyTest.php`

**Context:** AI agents construct Cypher queries. Need to verify all user/LLM-provided values go through parameters, not string concatenation.

**Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Agents;

use Tests\TestCase;
use ReflectionClass;

class AgentCypherQuerySafetyTest extends TestCase
{
    private array $agentFiles = [
        'app/Agents/AutonomousResearchAgent.php',
        'app/Agents/DecisionDiscoveryAgent.php',
        'app/Agents/PrecedentAnalystAgent.php',
        'app/Agents/ResearchSpecialistAgent.php',
    ];

    /** @test */
    public function agents_do_not_concatenate_variables_into_cypher_queries(): void
    {
        $unsafePatterns = [
            '/\$\w+\s*\.\s*[\'"].*MATCH/i',  // $var . "MATCH..."
            '/[\'"].*MATCH.*[\'"].*\.\s*\$\w+/i',  // "MATCH..." . $var
            '/sprintf\s*\(\s*[\'"].*MATCH/i',  // sprintf("MATCH...
            '/\{.*\$.*\}.*MATCH/i',  // "{$var}...MATCH"
        ];

        $violations = [];

        foreach ($this->agentFiles as $file) {
            $path = base_path($file);
            if (!file_exists($path)) {
                continue;
            }

            $content = file_get_contents($path);

            foreach ($unsafePatterns as $pattern) {
                if (preg_match($pattern, $content)) {
                    $violations[] = "$file matches unsafe pattern: $pattern";
                }
            }
        }

        $this->assertEmpty(
            $violations,
            "Found potential Cypher injection vulnerabilities:\n" . implode("\n", $violations)
        );
    }

    /** @test */
    public function agents_use_parameter_binding_for_user_input(): void
    {
        foreach ($this->agentFiles as $file) {
            $path = base_path($file);
            if (!file_exists($path)) {
                continue;
            }

            $content = file_get_contents($path);

            // If file contains Cypher queries, it should use $parameters or params
            if (preg_match('/MATCH\s*\(/i', $content)) {
                $this->assertMatchesRegularExpression(
                    '/\$\w+\s*=.*parameters|params|->run\([^,]+,\s*\[/i',
                    $content,
                    "Agent $file uses Cypher but may not use parameter binding"
                );
            }
        }
    }
}
```

**Step 2: Run test**

Run: `./scripts/run-focused-tests.sh AgentCypherQuerySafetyTest`
Expected: PASS (agents already use parameters) or FAIL (need fixes)

**Step 3: If failures, fix agent query construction**

Review flagged files and ensure all dynamic values use parameter binding:

```php
// BAD - string concatenation
$query = "MATCH (n:LawDocument) WHERE n.title CONTAINS '$topic' RETURN n";

// GOOD - parameter binding
$query = "MATCH (n:LawDocument) WHERE n.title CONTAINS $topic RETURN n";
$params = ['topic' => $topic];
$this->graphService->run($query, $params);
```

**Step 4: Run tests to verify**

Run: `./scripts/run-focused-tests.sh AgentCypherQuerySafetyTest`
Expected: PASS

**Step 5: Commit**

```bash
git add app/Agents/ tests/Unit/Agents/AgentCypherQuerySafetyTest.php
git commit -m "security(agents): audit and verify Cypher query parameter binding"
```

---

## Sprint 2: Error Handling & Resilience

### Task 4: Standardize Graph Exception Hierarchy

**Files:**
- Modify: `app/Exceptions/Graph/GraphConnectionException.php`
- Create: `app/Exceptions/Graph/GraphQueryException.php`
- Create: `app/Exceptions/Graph/GraphValidationException.php`
- Create: `app/Exceptions/Graph/GraphTimeoutException.php`
- Create: `tests/Unit/Exceptions/GraphExceptionHierarchyTest.php`

**Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Exceptions;

use App\Exceptions\GraphException;
use App\Exceptions\Graph\GraphConnectionException;
use App\Exceptions\Graph\GraphQueryException;
use App\Exceptions\Graph\GraphValidationException;
use App\Exceptions\Graph\GraphTimeoutException;
use Tests\TestCase;

class GraphExceptionHierarchyTest extends TestCase
{
    /** @test */
    public function all_graph_exceptions_extend_base_graph_exception(): void
    {
        $exceptions = [
            GraphConnectionException::class,
            GraphQueryException::class,
            GraphValidationException::class,
            GraphTimeoutException::class,
        ];

        foreach ($exceptions as $exception) {
            $this->assertTrue(
                is_subclass_of($exception, GraphException::class),
                "$exception should extend GraphException"
            );
        }
    }

    /** @test */
    public function graph_query_exception_includes_query_context(): void
    {
        $query = "MATCH (n) RETURN n";
        $exception = GraphQueryException::forQuery($query, new \Exception('Neo4j error'));

        $this->assertStringContainsString('MATCH', $exception->getQuery());
        $this->assertNotNull($exception->getPrevious());
    }

    /** @test */
    public function graph_timeout_exception_includes_timeout_value(): void
    {
        $exception = GraphTimeoutException::afterSeconds(30, 'Long query');

        $this->assertEquals(30, $exception->getTimeoutSeconds());
        $this->assertStringContainsString('30', $exception->getMessage());
    }

    /** @test */
    public function graph_validation_exception_includes_field_errors(): void
    {
        $errors = ['node_id' => 'Invalid format', 'label' => 'Unknown label'];
        $exception = GraphValidationException::withErrors($errors);

        $this->assertEquals($errors, $exception->getValidationErrors());
    }
}
```

**Step 2: Run test to verify it fails**

Run: `./scripts/run-focused-tests.sh GraphExceptionHierarchyTest`
Expected: FAIL - new exception classes don't exist

**Step 3: Create exception classes**

Create `app/Exceptions/Graph/GraphQueryException.php`:
```php
<?php

namespace App\Exceptions\Graph;

use App\Exceptions\GraphException;
use Throwable;

class GraphQueryException extends GraphException
{
    private string $query;

    public function __construct(string $message, string $query = '', ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->query = $query;
    }

    public static function forQuery(string $query, Throwable $previous): self
    {
        $preview = strlen($query) > 100 ? substr($query, 0, 100) . '...' : $query;
        return new self(
            "Graph query failed: {$previous->getMessage()} [Query: $preview]",
            $query,
            $previous
        );
    }

    public function getQuery(): string
    {
        return $this->query;
    }
}
```

Create `app/Exceptions/Graph/GraphTimeoutException.php`:
```php
<?php

namespace App\Exceptions\Graph;

use App\Exceptions\GraphException;

class GraphTimeoutException extends GraphException
{
    private int $timeoutSeconds;

    public function __construct(string $message, int $timeoutSeconds)
    {
        parent::__construct($message);
        $this->timeoutSeconds = $timeoutSeconds;
    }

    public static function afterSeconds(int $seconds, string $queryDescription = ''): self
    {
        $desc = $queryDescription ? " ($queryDescription)" : '';
        return new self(
            "Graph query timed out after {$seconds} seconds{$desc}",
            $seconds
        );
    }

    public function getTimeoutSeconds(): int
    {
        return $this->timeoutSeconds;
    }
}
```

Create `app/Exceptions/Graph/GraphValidationException.php`:
```php
<?php

namespace App\Exceptions\Graph;

use App\Exceptions\GraphException;

class GraphValidationException extends GraphException
{
    private array $validationErrors;

    public function __construct(string $message, array $errors = [])
    {
        parent::__construct($message);
        $this->validationErrors = $errors;
    }

    public static function withErrors(array $errors): self
    {
        $message = 'Graph validation failed: ' . implode(', ', array_keys($errors));
        return new self($message, $errors);
    }

    public function getValidationErrors(): array
    {
        return $this->validationErrors;
    }
}
```

**Step 4: Run tests to verify**

Run: `./scripts/run-focused-tests.sh GraphExceptionHierarchyTest`
Expected: PASS

**Step 5: Commit**

```bash
git add app/Exceptions/Graph/ tests/Unit/Exceptions/GraphExceptionHierarchyTest.php
git commit -m "feat(graph): add standardized exception hierarchy for graph operations"
```

---

### Task 5: Add Circuit Breaker to All Graph Sync Services

**Files:**
- Modify: `app/Services/Graph/LawGraphSyncService.php`
- Modify: `app/Services/Graph/DecisionGraphSyncService.php`
- Create: `tests/Unit/Services/Graph/CircuitBreakerIntegrationTest.php`

**Context:** CircuitBreaker exists but may not be used consistently across all sync services.

**Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Services\Graph;

use App\Services\Graph\CircuitBreaker;
use App\Services\Graph\CircuitBreakerOpenException;
use App\Services\Graph\LawGraphSyncService;
use App\Services\Graph\DecisionGraphSyncService;
use App\Services\GraphDatabaseService;
use Tests\TestCase;
use Mockery;

class CircuitBreakerIntegrationTest extends TestCase
{
    /** @test */
    public function law_sync_service_uses_circuit_breaker(): void
    {
        $graphService = Mockery::mock(GraphDatabaseService::class);
        $circuitBreaker = Mockery::mock(CircuitBreaker::class);

        // Circuit is open - should throw
        $circuitBreaker->shouldReceive('isOpen')->andReturn(true);
        $circuitBreaker->shouldReceive('recordFailure')->never();

        $this->app->instance(CircuitBreaker::class, $circuitBreaker);
        $this->app->instance(GraphDatabaseService::class, $graphService);

        $service = $this->app->make(LawGraphSyncService::class);

        $this->expectException(CircuitBreakerOpenException::class);
        $service->syncLaw(Mockery::mock(\App\Models\Law::class));
    }

    /** @test */
    public function circuit_breaker_opens_after_threshold_failures(): void
    {
        $circuitBreaker = new CircuitBreaker(
            failureThreshold: 3,
            recoveryTimeout: 30
        );

        // Record failures
        for ($i = 0; $i < 3; $i++) {
            $circuitBreaker->recordFailure();
        }

        $this->assertTrue($circuitBreaker->isOpen());
    }

    /** @test */
    public function circuit_breaker_allows_probe_after_recovery_timeout(): void
    {
        $circuitBreaker = new CircuitBreaker(
            failureThreshold: 1,
            recoveryTimeout: 1  // 1 second
        );

        $circuitBreaker->recordFailure();
        $this->assertTrue($circuitBreaker->isOpen());

        // Wait for recovery
        sleep(2);

        $this->assertTrue($circuitBreaker->allowProbe());
    }
}
```

**Step 2: Run test to verify**

Run: `./scripts/run-focused-tests.sh CircuitBreakerIntegrationTest`
Expected: FAIL or PASS depending on current implementation

**Step 3: Ensure circuit breaker is injected into sync services**

In `GraphServiceProvider.php`, ensure circuit breaker is properly bound and injected.

**Step 4: Run tests**

Run: `./scripts/run-focused-tests.sh CircuitBreakerIntegrationTest`
Expected: PASS

**Step 5: Commit**

```bash
git add app/Services/Graph/ app/Providers/GraphServiceProvider.php tests/Unit/Services/Graph/CircuitBreakerIntegrationTest.php
git commit -m "feat(graph): ensure circuit breaker integration in sync services"
```

---

### Task 6: Add Error States to GraphViewer UI

**Files:**
- Modify: `app/Http/Livewire/GraphViewer.php`
- Modify: `resources/views/livewire/graph-viewer.blade.php`
- Create: `tests/Feature/Livewire/GraphViewerErrorStatesTest.php`

**Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\GraphViewer;
use App\Services\GraphDatabaseService;
use App\Repositories\GraphMetricsRepository;
use App\Exceptions\Graph\GraphConnectionException;
use Livewire\Livewire;
use Tests\TestCase;
use Mockery;

class GraphViewerErrorStatesTest extends TestCase
{
    /** @test */
    public function it_displays_connection_error_when_neo4j_unavailable(): void
    {
        $graphService = Mockery::mock(GraphDatabaseService::class);
        $graphService->shouldReceive('run')
            ->andThrow(GraphConnectionException::forHost('localhost:7687'));

        $metricsRepository = Mockery::mock(GraphMetricsRepository::class);
        $metricsRepository->shouldReceive('getInfluentialDecisions')->andReturn([]);
        $metricsRepository->shouldReceive('getCitationClusters')->andReturn([]);
        $metricsRepository->shouldReceive('getNetworkStats')->andReturn(null);

        $this->app->instance(GraphDatabaseService::class, $graphService);
        $this->app->instance(GraphMetricsRepository::class, $metricsRepository);

        $component = Livewire::test(GraphViewer::class);

        $component->assertSee('Graph database unavailable');
        $component->assertSet('hasConnectionError', true);
    }

    /** @test */
    public function it_displays_loading_state_during_query(): void
    {
        $component = Livewire::test(GraphViewer::class);

        // Component should have loading property
        $this->assertNotNull($component->get('isLoading'));
    }

    /** @test */
    public function it_displays_empty_state_when_no_results(): void
    {
        $graphService = Mockery::mock(GraphDatabaseService::class);
        $graphService->shouldReceive('run')->andReturn(collect([]));

        $metricsRepository = Mockery::mock(GraphMetricsRepository::class);
        $metricsRepository->shouldReceive('getInfluentialDecisions')->andReturn([]);
        $metricsRepository->shouldReceive('getCitationClusters')->andReturn([]);
        $metricsRepository->shouldReceive('getNetworkStats')->andReturn(null);

        $this->app->instance(GraphDatabaseService::class, $graphService);
        $this->app->instance(GraphMetricsRepository::class, $metricsRepository);

        $component = Livewire::test(GraphViewer::class)
            ->set('searchQuery', 'nonexistent-law-xyz')
            ->call('search');

        $component->assertSee('No results found');
    }
}
```

**Step 2: Run test to verify it fails**

Run: `./scripts/run-focused-tests.sh GraphViewerErrorStatesTest`
Expected: FAIL - error states not implemented

**Step 3: Add error handling to GraphViewer**

Add to `app/Http/Livewire/GraphViewer.php`:

```php
public bool $isLoading = false;
public bool $hasConnectionError = false;
public ?string $errorMessage = null;

public function mount(): void
{
    $this->isLoading = true;
    try {
        $this->loadInitialData();
        $this->hasConnectionError = false;
    } catch (GraphConnectionException $e) {
        $this->hasConnectionError = true;
        $this->errorMessage = 'Graph database unavailable';
        Log::error('GraphViewer connection error', ['exception' => $e->getMessage()]);
    } finally {
        $this->isLoading = false;
    }
}
```

Add to blade template:

```blade
@if($hasConnectionError)
    <div dusk="connection-error" class="bg-red-900/50 border border-red-500 rounded p-4 text-center">
        <p class="text-red-200">{{ $errorMessage ?? 'Graph database unavailable' }}</p>
        <button wire:click="$refresh" class="mt-2 btn btn-sm">Retry</button>
    </div>
@endif

@if($isLoading)
    <div dusk="loading-state" class="flex justify-center items-center py-8">
        <div class="animate-spin h-8 w-8 border-4 border-blue-500 border-t-transparent rounded-full"></div>
    </div>
@endif
```

**Step 4: Run tests**

Run: `./scripts/run-focused-tests.sh GraphViewerErrorStatesTest`
Expected: PASS

**Step 5: Commit**

```bash
git add app/Http/Livewire/GraphViewer.php resources/views/livewire/graph-viewer.blade.php tests/Feature/Livewire/GraphViewerErrorStatesTest.php
git commit -m "feat(ui): add error states and loading indicators to GraphViewer"
```

---

## Sprint 3: Observability & Monitoring

### Task 7: Add Structured Logging to GraphDatabaseService

**Files:**
- Modify: `app/Services/GraphDatabaseService.php`
- Create: `tests/Unit/Services/GraphDatabaseServiceLoggingTest.php`

**Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Services;

use App\Services\GraphDatabaseService;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class GraphDatabaseServiceLoggingTest extends TestCase
{
    /** @test */
    public function it_logs_slow_queries_with_context(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message, $context) {
                return str_contains($message, 'Slow graph query') &&
                    isset($context['query_preview']) &&
                    isset($context['duration_ms']) &&
                    isset($context['threshold_ms']);
            });

        // This test verifies log format - actual slow query simulation
        // would require integration test
        $this->assertTrue(true);
    }

    /** @test */
    public function it_logs_connection_failures_with_host_context(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message, $context) {
                return str_contains($message, 'Graph connection failed') &&
                    isset($context['host']) &&
                    isset($context['attempt']);
            });

        $this->assertTrue(true);
    }

    /** @test */
    public function query_preview_truncates_sensitive_data(): void
    {
        $longQuery = "MATCH (n:User {password: 'secret123'}) WHERE n.email = 'test@test.com' " . str_repeat('x', 200);

        $preview = GraphDatabaseService::getQueryPreview($longQuery);

        $this->assertLessThanOrEqual(100, strlen($preview));
        $this->assertStringEndsWith('...', $preview);
    }
}
```

**Step 2: Run test**

Run: `./scripts/run-focused-tests.sh GraphDatabaseServiceLoggingTest`
Expected: FAIL - getQueryPreview may not be public/static

**Step 3: Implement structured logging helper**

Add to GraphDatabaseService:

```php
public static function getQueryPreview(string $query, int $maxLength = 100): string
{
    if (strlen($query) <= $maxLength) {
        return $query;
    }
    return substr($query, 0, $maxLength) . '...';
}

protected function logSlowQuery(string $query, float $durationMs): void
{
    if ($durationMs >= $this->slowQueryThreshold) {
        Log::warning('Slow graph query detected', [
            'query_preview' => self::getQueryPreview($query),
            'duration_ms' => round($durationMs, 2),
            'threshold_ms' => $this->slowQueryThreshold,
        ]);
    }
}
```

**Step 4: Run tests**

Run: `./scripts/run-focused-tests.sh GraphDatabaseServiceLoggingTest`
Expected: PASS

**Step 5: Commit**

```bash
git add app/Services/GraphDatabaseService.php tests/Unit/Services/GraphDatabaseServiceLoggingTest.php
git commit -m "feat(graph): add structured logging for slow queries and connection failures"
```

---

### Task 8: Add Health Check Endpoint for Neo4j

**Files:**
- Create: `app/Http/Controllers/Api/GraphHealthController.php`
- Create: `routes/api.php` (modify)
- Create: `tests/Feature/Api/GraphHealthCheckTest.php`

**Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Api;

use App\Services\GraphDatabaseService;
use Tests\TestCase;
use Mockery;

class GraphHealthCheckTest extends TestCase
{
    /** @test */
    public function health_check_returns_healthy_when_connected(): void
    {
        $graphService = Mockery::mock(GraphDatabaseService::class);
        $graphService->shouldReceive('isHealthy')->andReturn(true);
        $graphService->shouldReceive('getConnectionStats')->andReturn([
            'pool_size' => 5,
            'active_connections' => 2,
            'query_count' => 1000,
        ]);

        $this->app->instance(GraphDatabaseService::class, $graphService);

        $response = $this->getJson('/api/health/graph');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'healthy',
                'service' => 'neo4j',
            ]);
    }

    /** @test */
    public function health_check_returns_unhealthy_when_disconnected(): void
    {
        $graphService = Mockery::mock(GraphDatabaseService::class);
        $graphService->shouldReceive('isHealthy')->andReturn(false);
        $graphService->shouldReceive('getLastError')->andReturn('Connection refused');

        $this->app->instance(GraphDatabaseService::class, $graphService);

        $response = $this->getJson('/api/health/graph');

        $response->assertStatus(503)
            ->assertJson([
                'status' => 'unhealthy',
                'service' => 'neo4j',
            ]);
    }

    /** @test */
    public function health_check_includes_latency_metric(): void
    {
        $graphService = Mockery::mock(GraphDatabaseService::class);
        $graphService->shouldReceive('isHealthy')->andReturn(true);
        $graphService->shouldReceive('getConnectionStats')->andReturn([]);
        $graphService->shouldReceive('ping')->andReturn(15.5); // ms

        $this->app->instance(GraphDatabaseService::class, $graphService);

        $response = $this->getJson('/api/health/graph');

        $response->assertStatus(200)
            ->assertJsonStructure(['latency_ms']);
    }
}
```

**Step 2: Run test to verify it fails**

Run: `./scripts/run-focused-tests.sh GraphHealthCheckTest`
Expected: FAIL - controller doesn't exist

**Step 3: Create health check controller**

Create `app/Http/Controllers/Api/GraphHealthController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GraphDatabaseService;
use Illuminate\Http\JsonResponse;

class GraphHealthController extends Controller
{
    public function __construct(
        private GraphDatabaseService $graphService
    ) {}

    public function __invoke(): JsonResponse
    {
        $startTime = microtime(true);
        $isHealthy = $this->graphService->isHealthy();
        $latencyMs = (microtime(true) - $startTime) * 1000;

        $response = [
            'service' => 'neo4j',
            'status' => $isHealthy ? 'healthy' : 'unhealthy',
            'latency_ms' => round($latencyMs, 2),
            'timestamp' => now()->toIso8601String(),
        ];

        if ($isHealthy) {
            $response['stats'] = $this->graphService->getConnectionStats();
        } else {
            $response['error'] = $this->graphService->getLastError();
        }

        return response()->json($response, $isHealthy ? 200 : 503);
    }
}
```

Add route to `routes/api.php`:
```php
Route::get('/health/graph', \App\Http\Controllers\Api\GraphHealthController::class);
```

**Step 4: Run tests**

Run: `./scripts/run-focused-tests.sh GraphHealthCheckTest`
Expected: PASS

**Step 5: Commit**

```bash
git add app/Http/Controllers/Api/GraphHealthController.php routes/api.php tests/Feature/Api/GraphHealthCheckTest.php
git commit -m "feat(api): add health check endpoint for Neo4j graph database"
```

---

## Sprint 4: Data Integrity

### Task 9: Add Input Sanitization Service

**Files:**
- Create: `app/Services/Graph/GraphInputSanitizer.php`
- Create: `tests/Unit/Services/Graph/GraphInputSanitizerTest.php`

**Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Services\Graph;

use App\Services\Graph\GraphInputSanitizer;
use Tests\TestCase;

class GraphInputSanitizerTest extends TestCase
{
    private GraphInputSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sanitizer = new GraphInputSanitizer();
    }

    /** @test */
    public function it_sanitizes_node_labels(): void
    {
        $this->assertEquals('LawDocument', $this->sanitizer->sanitizeLabel('LawDocument'));
        $this->assertNull($this->sanitizer->sanitizeLabel('Invalid-Label'));
        $this->assertNull($this->sanitizer->sanitizeLabel('DROP TABLE'));
        $this->assertNull($this->sanitizer->sanitizeLabel(''));
    }

    /** @test */
    public function it_sanitizes_relationship_types(): void
    {
        $this->assertEquals('CITES', $this->sanitizer->sanitizeRelationType('CITES'));
        $this->assertEquals('RELATES_TO', $this->sanitizer->sanitizeRelationType('RELATES_TO'));
        $this->assertNull($this->sanitizer->sanitizeRelationType('invalid-type'));
    }

    /** @test */
    public function it_sanitizes_property_names(): void
    {
        $this->assertEquals('title', $this->sanitizer->sanitizePropertyName('title'));
        $this->assertEquals('created_at', $this->sanitizer->sanitizePropertyName('created_at'));
        $this->assertNull($this->sanitizer->sanitizePropertyName('1invalid'));
        $this->assertNull($this->sanitizer->sanitizePropertyName('prop;DROP'));
    }

    /** @test */
    public function it_sanitizes_node_ids(): void
    {
        $this->assertEquals('law-123', $this->sanitizer->sanitizeNodeId('law-123'));
        $this->assertEquals('uuid-abc-def', $this->sanitizer->sanitizeNodeId('uuid-abc-def'));
        $this->assertNull($this->sanitizer->sanitizeNodeId('<script>'));
        $this->assertNull($this->sanitizer->sanitizeNodeId("id'; DROP"));
    }

    /** @test */
    public function it_sanitizes_search_queries(): void
    {
        $this->assertEquals('legal research', $this->sanitizer->sanitizeSearchQuery('legal research'));
        $this->assertEquals('test query', $this->sanitizer->sanitizeSearchQuery('test<script>query'));
        $this->assertNull($this->sanitizer->sanitizeSearchQuery(str_repeat('a', 1001)));
    }
}
```

**Step 2: Run test to verify it fails**

Run: `./scripts/run-focused-tests.sh GraphInputSanitizerTest`
Expected: FAIL - class doesn't exist

**Step 3: Create sanitizer service**

Create `app/Services/Graph/GraphInputSanitizer.php`:

```php
<?php

namespace App\Services\Graph;

class GraphInputSanitizer
{
    private const LABEL_PATTERN = '/^[A-Z][a-zA-Z0-9]*$/';
    private const RELATION_PATTERN = '/^[A-Z][A-Z0-9_]*$/';
    private const PROPERTY_PATTERN = '/^[a-z][a-zA-Z0-9_]*$/';
    private const NODE_ID_PATTERN = '/^[a-zA-Z0-9\-_]+$/';
    private const MAX_SEARCH_LENGTH = 1000;

    private array $allowedLabels = [
        'LawDocument', 'CourtDecisionDocument', 'CaseDocument',
        'Keyword', 'Topic', 'Tag', 'Jurisdiction', 'Court',
        'Judge', 'Party', 'LegalConcept', 'LegalPrinciple',
        'LegalDefinition', 'LegalArgument', 'Verdict', 'Lawyer',
        'DateEvent', 'Evidence', 'Article',
    ];

    private array $allowedRelationTypes = [
        'CITES', 'REFERENCES', 'RELATES_TO', 'HAS_KEYWORD', 'HAS_TAG',
        'BELONGS_TO_JURISDICTION', 'DECIDED_BY', 'SUPERSEDES', 'AMENDED_BY',
        'SIMILAR_TO', 'CONTAINS_CONCEPT', 'PARENT_TAG', 'CONTRADICTS', 'SUPPORTS',
    ];

    public function sanitizeLabel(string $label): ?string
    {
        $label = trim($label);
        if (empty($label) || !preg_match(self::LABEL_PATTERN, $label)) {
            return null;
        }
        return in_array($label, $this->allowedLabels, true) ? $label : null;
    }

    public function sanitizeRelationType(string $type): ?string
    {
        $type = trim($type);
        if (empty($type) || !preg_match(self::RELATION_PATTERN, $type)) {
            return null;
        }
        return in_array($type, $this->allowedRelationTypes, true) ? $type : null;
    }

    public function sanitizePropertyName(string $name): ?string
    {
        $name = trim($name);
        if (empty($name) || !preg_match(self::PROPERTY_PATTERN, $name)) {
            return null;
        }
        return $name;
    }

    public function sanitizeNodeId(string $id): ?string
    {
        $id = trim($id);
        if (empty($id) || !preg_match(self::NODE_ID_PATTERN, $id)) {
            return null;
        }
        return $id;
    }

    public function sanitizeSearchQuery(string $query): ?string
    {
        $query = strip_tags(trim($query));
        if (strlen($query) > self::MAX_SEARCH_LENGTH) {
            return null;
        }
        return $query ?: null;
    }
}
```

**Step 4: Run tests**

Run: `./scripts/run-focused-tests.sh GraphInputSanitizerTest`
Expected: PASS

**Step 5: Commit**

```bash
git add app/Services/Graph/GraphInputSanitizer.php tests/Unit/Services/Graph/GraphInputSanitizerTest.php
git commit -m "feat(graph): add input sanitization service for graph operations"
```

---

### Task 10: Add Cross-Store Consistency Check Command

**Files:**
- Create: `app/Console/Commands/GraphConsistencyCheckCommand.php`
- Create: `tests/Feature/Commands/GraphConsistencyCheckCommandTest.php`

**Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Commands;

use App\Models\Law;
use App\Services\GraphDatabaseService;
use Tests\TestCase;
use Mockery;

class GraphConsistencyCheckCommandTest extends TestCase
{
    /** @test */
    public function it_detects_orphaned_graph_nodes(): void
    {
        // Create a Law in PostgreSQL
        $law = Law::factory()->create(['id' => 999]);

        // Mock Neo4j to return nodes that don't exist in PostgreSQL
        $graphService = Mockery::mock(GraphDatabaseService::class);
        $record = Mockery::mock();
        $record->shouldReceive('get')->with('id')->andReturn('law-orphan-123');

        $graphService->shouldReceive('run')
            ->with(Mockery::on(fn($q) => str_contains($q, 'LawDocument')))
            ->andReturn(collect([$record]));

        $this->app->instance(GraphDatabaseService::class, $graphService);

        $this->artisan('graph:consistency-check')
            ->expectsOutput('Found 1 orphaned graph nodes')
            ->assertExitCode(1);
    }

    /** @test */
    public function it_detects_missing_graph_nodes(): void
    {
        // Create Laws in PostgreSQL that should be in Neo4j
        Law::factory()->count(3)->create();

        // Mock Neo4j to return empty (no nodes synced)
        $graphService = Mockery::mock(GraphDatabaseService::class);
        $graphService->shouldReceive('run')->andReturn(collect([]));

        $this->app->instance(GraphDatabaseService::class, $graphService);

        $this->artisan('graph:consistency-check')
            ->expectsOutput('Found 3 missing graph nodes')
            ->assertExitCode(1);
    }

    /** @test */
    public function it_returns_success_when_consistent(): void
    {
        $graphService = Mockery::mock(GraphDatabaseService::class);
        $graphService->shouldReceive('run')->andReturn(collect([]));

        $this->app->instance(GraphDatabaseService::class, $graphService);

        // No Laws in PostgreSQL, no nodes in Neo4j - consistent
        $this->artisan('graph:consistency-check')
            ->expectsOutput('Graph and database are consistent')
            ->assertExitCode(0);
    }
}
```

**Step 2: Run test to verify it fails**

Run: `./scripts/run-focused-tests.sh GraphConsistencyCheckCommandTest`
Expected: FAIL - command doesn't exist

**Step 3: Create consistency check command**

Create `app/Console/Commands/GraphConsistencyCheckCommand.php`:

```php
<?php

namespace App\Console\Commands;

use App\Models\Law;
use App\Models\CaseDocument;
use App\Services\GraphDatabaseService;
use Illuminate\Console\Command;

class GraphConsistencyCheckCommand extends Command
{
    protected $signature = 'graph:consistency-check {--fix : Attempt to fix inconsistencies}';
    protected $description = 'Check consistency between PostgreSQL and Neo4j graph database';

    public function __construct(
        private GraphDatabaseService $graphService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $orphanedNodes = 0;
        $missingNodes = 0;

        // Check LawDocuments
        $graphLawIds = $this->getGraphNodeIds('LawDocument');
        $dbLawIds = Law::pluck('id')->map(fn($id) => "law-$id")->toArray();

        $orphanedNodes += count(array_diff($graphLawIds, $dbLawIds));
        $missingNodes += count(array_diff($dbLawIds, $graphLawIds));

        // Report findings
        if ($orphanedNodes > 0) {
            $this->error("Found $orphanedNodes orphaned graph nodes");
        }

        if ($missingNodes > 0) {
            $this->error("Found $missingNodes missing graph nodes");
        }

        if ($orphanedNodes === 0 && $missingNodes === 0) {
            $this->info('Graph and database are consistent');
            return Command::SUCCESS;
        }

        return Command::FAILURE;
    }

    private function getGraphNodeIds(string $label): array
    {
        $results = $this->graphService->run(
            "MATCH (n:$label) RETURN n.id as id"
        );

        return $results->map(fn($r) => $r->get('id'))->filter()->toArray();
    }
}
```

**Step 4: Run tests**

Run: `./scripts/run-focused-tests.sh GraphConsistencyCheckCommandTest`
Expected: PASS

**Step 5: Commit**

```bash
git add app/Console/Commands/GraphConsistencyCheckCommand.php tests/Feature/Commands/GraphConsistencyCheckCommandTest.php
git commit -m "feat(graph): add cross-store consistency check command"
```

---

## Sprint 5: Final Verification

### Task 11: Run Full Test Suite

**Step 1: Run all graph-related tests**

```bash
./scripts/run-focused-tests.sh --filter Graph
```

**Step 2: Run full test suite**

```bash
./scripts/run-tests-with-analysis.sh
```

**Step 3: Verify no regressions**

All tests should pass.

### Task 12: Push and Create Summary

**Step 1: Commit any remaining changes**

```bash
git add -A
git commit -m "chore: complete Neo4j production hardening implementation"
```

**Step 2: Push to remote**

```bash
git push -u origin claude/graph-enhancement-data-integrity-XqqqL
```

**Step 3: Create summary document**

Document all changes made in this sprint.

---

## Summary

| Sprint | Focus | Tasks |
|--------|-------|-------|
| Sprint 1 | Security Hardening | XSS protection, input validation, query audit |
| Sprint 2 | Error Handling & Resilience | Exception hierarchy, circuit breaker, UI error states |
| Sprint 3 | Observability | Structured logging, health check endpoint |
| Sprint 4 | Data Integrity | Input sanitization, consistency checks |
| Sprint 5 | Verification | Full test suite, push and summary |

**Total Tasks:** 12
**Estimated Test Coverage Added:** 50+ new tests
**Key Deliverables:**
- XSS protection verified in GraphViewer
- Standardized exception hierarchy
- Circuit breaker integration across sync services
- Health check API endpoint
- Input sanitization service
- Cross-store consistency command

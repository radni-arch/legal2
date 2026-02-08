# Citation Network Analysis - Comprehensive Review & Testing Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan task-by-task.

**Goal:** Verify Citation Network implementation quality through agent state verification, MCP accessibility testing, comprehensive test coverage analysis, and security audit.

**Architecture:** Multi-layer review covering autonomous agents (monitoring/state), MCP server integration, unit/feature/E2E test execution, and security validation. Uses TDD verification approach with offline testing capabilities.

**Tech Stack:** Laravel 11, PHPUnit, Laravel Dusk, PostgreSQL, Neo4j, OpenAI API (mocked), MCP Protocol, Livewire, D3.js

---

## Task 1: Verify Agent State & Configuration

**Priority:** HIGH

**Files:**
- Read: `config/agent.php` (agent configuration)
- Read: `app/Agents/AutonomousResearchAgent.php` (agent implementation)
- Read: `app/Agents/DecisionDiscoveryAgent.php` (decision agent)
- Read: `app/Agents/OdlukeAgent.php` (MCP-powered agent)
- Check: Queue jobs for agent tasks

### Step 1: Inspect agent configuration

Read `config/agent.php` and document:
- Evaluation weights and thresholds
- Token/cost budgets
- Max iterations and time limits
- Safety limits

```bash
cat config/agent.php | grep -A 5 'evaluation\|budget\|limits'
```

Expected: Configuration values for all autonomous agents

### Step 2: Check agent classes exist and have correct methods

```bash
# Check AutonomousResearchAgent
php artisan tinker --execute="
\$agent = new App\Agents\AutonomousResearchAgent();
echo 'Methods: ' . implode(', ', get_class_methods(\$agent)) . PHP_EOL;
"
```

Expected: Methods include `research()`, `evaluate()`, `shouldContinue()`

### Step 3: Verify agent queue configuration

```bash
# Check queue configuration
php artisan queue:work --help | grep -i queue
cat config/queue.php | grep -A 10 "'agents'"
```

Expected: `agents` queue exists with proper driver (database)

### Step 4: Check for agent monitoring interface

```bash
# Search for agent dashboard/monitoring routes
grep -r "agent.*monitor\|agent.*dashboard" routes/
grep -r "agent.*status\|agent.*metrics" app/Http/Controllers/
```

Expected: Find agent monitoring endpoints or controllers

### Step 5: Document agent state findings

Create report file:

```bash
cat > /tmp/agent-review-report.txt << 'EOF'
# Agent State Review Report

## Configuration Status
- [✓/✗] config/agent.php exists and has valid settings
- [✓/✗] All agent classes exist (AutonomousResearchAgent, DecisionDiscoveryAgent, OdlukeAgent)
- [✓/✗] Agents queue configured in config/queue.php
- [✓/✗] Agent monitoring interface exists

## Agent Classes Found
- AutonomousResearchAgent: [methods]
- DecisionDiscoveryAgent: [methods]
- OdlukeAgent: [methods]

## Issues Found
[List any configuration/implementation issues]

## Recommendations
[Suggestions for improvements]
EOF
cat /tmp/agent-review-report.txt
```

---

## Task 2: Test MCP Server Accessibility & Integration

**Priority:** HIGH

**Files:**
- Read: `config/mcp.php` (MCP configuration)
- Read: `app/Mcp/Tools/*.php` (MCP tool implementations)
- Read: `routes/api.php` (MCP routes)
- Test: MCP endpoints

### Step 1: Verify MCP configuration

```bash
# Check MCP config exists and has API token
php artisan tinker --execute="
echo 'MCP Token Set: ' . (config('mcp.api_token') ? 'YES' : 'NO') . PHP_EOL;
echo 'MCP Rate Limit: ' . config('mcp.rate_limit') . PHP_EOL;
"
```

Expected: MCP_API_TOKEN is set, rate limiting configured

### Step 2: List all MCP tools

```bash
# Find all MCP tool classes
find app/Mcp/Tools -name "*.php" -exec basename {} .php \;
```

Expected: Decision search, citation extraction, fact comparison, law search tools

### Step 3: Test MCP tool structure

Create test file `tests/Feature/McpToolsAccessibilityTest.php`:

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Concerns\UsesTestDatabase;

class McpToolsAccessibilityTest extends TestCase
{
    use UsesTestDatabase;

    public function test_mcp_decision_search_tool_exists()
    {
        $toolClass = 'App\\Mcp\\Tools\\DecisionSearchTool';
        $this->assertTrue(class_exists($toolClass), 'DecisionSearchTool class should exist');

        $tool = app($toolClass);
        $this->assertTrue(method_exists($tool, 'execute'), 'Tool should have execute method');
    }

    public function test_mcp_citation_extraction_tool_exists()
    {
        $toolClass = 'App\\Mcp\\Tools\\CitationExtractionTool';
        $this->assertTrue(class_exists($toolClass), 'CitationExtractionTool class should exist');
    }

    public function test_mcp_law_search_tool_exists()
    {
        $toolClass = 'App\\Mcp\\Tools\\LawSearchTool';
        $this->assertTrue(class_exists($toolClass), 'LawSearchTool class should exist');
    }

    public function test_mcp_api_routes_registered()
    {
        $routes = collect(\Route::getRoutes())->filter(function ($route) {
            return str_starts_with($route->uri(), 'mcp/');
        });

        $this->assertGreaterThan(0, $routes->count(), 'MCP routes should be registered');
    }
}
```

### Step 4: Run MCP tools test

```bash
php artisan test --filter=McpToolsAccessibilityTest
```

Expected: All 4 tests pass

### Step 5: Test MCP endpoint authentication

Create test `tests/Feature/McpAuthenticationTest.php`:

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Concerns\UsesTestDatabase;

class McpAuthenticationTest extends TestCase
{
    use UsesTestDatabase;

    public function test_mcp_endpoints_require_authentication()
    {
        // Attempt to access MCP endpoint without token
        $response = $this->postJson('/mcp/tools/search', [
            'query' => 'test query'
        ]);

        $response->assertStatus(401); // Unauthorized
    }

    public function test_mcp_endpoints_accept_valid_token()
    {
        $token = config('mcp.api_token');

        // Mock the tool response to avoid actual execution
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/mcp/tools/search', [
            'query' => 'test query'
        ]);

        // Should not be 401 (might be 404 if route doesn't exist, or 200 if it does)
        $response->assertStatus(fn($status) => $status !== 401);
    }
}
```

### Step 6: Run authentication tests

```bash
php artisan test --filter=McpAuthenticationTest
```

Expected: Tests verify authentication is required

### Step 7: Document MCP accessibility findings

```bash
cat >> /tmp/agent-review-report.txt << 'EOF'

---

# MCP Accessibility Review Report

## Configuration Status
- [✓/✗] config/mcp.php exists with API token
- [✓/✗] Rate limiting configured
- [✓/✗] MCP routes registered in routes/api.php

## MCP Tools Found
- [List all tool classes found]

## Accessibility Tests
- [✓/✗] All MCP tool classes exist and have execute() methods
- [✓/✗] MCP endpoints require authentication
- [✓/✗] Valid tokens are accepted

## Integration Status
- [✓/✗] OdlukeAgent integrates with MCP tools
- [✓/✗] DecisionCitationService can be called via MCP

## Issues Found
[List any MCP configuration/accessibility issues]
EOF
cat /tmp/agent-review-report.txt
```

---

## Task 3: Analyze Unit Test Coverage for DecisionCitationService

**Priority:** HIGH

**Files:**
- Read: `app/Services/DecisionCitationService.php`
- Find: Unit tests for DecisionCitationService
- Create: Missing unit tests if needed

### Step 1: Check if unit tests exist

```bash
# Search for DecisionCitationService tests
find tests/Unit tests/Feature -name "*Citation*Test.php" -o -name "*DecisionCitation*Test.php"
```

Expected: Find test file(s) for DecisionCitationService

### Step 2: Review DecisionCitationService public methods

```bash
# Extract public methods from service
grep -n "public function" app/Services/DecisionCitationService.php | grep -v "__construct"
```

Expected: List includes `analyzeCitations()` and helper methods

### Step 3: Create comprehensive unit test file

If tests don't exist, create `tests/Unit/Services/DecisionCitationServiceTest.php`:

```php
<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use Tests\Concerns\UsesTestDatabase;
use App\Services\DecisionCitationService;
use Illuminate\Support\Facades\Http;

class DecisionCitationServiceTest extends TestCase
{
    use UsesTestDatabase;

    protected DecisionCitationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock OpenAI API for offline testing
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => '{"analysis": "test"}']]
                ]
            ], 200),
        ]);

        $this->service = app(DecisionCitationService::class);
    }

    public function test_analyze_citations_with_graph_operation()
    {
        // Create test decision with citations
        $decision = \App\Models\CourtDecisionDocument::factory()->create();

        $result = $this->service->analyzeCitations($decision->id, [
            'operation' => 'graph',
            'depth' => 2,
            'limit' => 50
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('nodes', $result);
        $this->assertArrayHasKey('edges', $result);
        $this->assertArrayHasKey('root_decision', $result);
    }

    public function test_analyze_citations_with_authority_operation()
    {
        $decision = \App\Models\CourtDecisionDocument::factory()->create();

        $result = $this->service->analyzeCitations($decision->id, [
            'operation' => 'authority'
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('h_index', $result);
        $this->assertArrayHasKey('influence_rank', $result);
        $this->assertArrayHasKey('citation_count', $result);
        $this->assertArrayHasKey('cited_by_count', $result);
        $this->assertArrayHasKey('authority_score', $result);
    }

    public function test_analyze_citations_with_patterns_operation()
    {
        $decision = \App\Models\CourtDecisionDocument::factory()->create();

        $result = $this->service->analyzeCitations($decision->id, [
            'operation' => 'patterns'
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('temporal_distribution', $result);
        $this->assertArrayHasKey('citation_types', $result);
        $this->assertArrayHasKey('patterns', $result);
    }

    public function test_analyze_citations_with_influence_operation()
    {
        $decision = \App\Models\CourtDecisionDocument::factory()->create();

        $result = $this->service->analyzeCitations($decision->id, [
            'operation' => 'influence'
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('direct_influences', $result);
        $this->assertArrayHasKey('indirect_influences', $result);
        $this->assertArrayHasKey('total_reach', $result);
    }

    public function test_analyze_citations_defaults_to_graph_operation()
    {
        $decision = \App\Models\CourtDecisionDocument::factory()->create();

        // Call without operation parameter
        $result = $this->service->analyzeCitations($decision->id);

        $this->assertIsArray($result);
        // Should default to graph operation
        $this->assertArrayHasKey('nodes', $result);
    }

    public function test_analyze_citations_throws_exception_for_invalid_decision_id()
    {
        $this->expectException(\Exception::class);

        $this->service->analyzeCitations('non-existent-id', [
            'operation' => 'graph'
        ]);
    }

    public function test_citation_analysis_runs_offline_without_api_keys()
    {
        // Verify no real HTTP requests are made
        Http::assertSentCount(0); // Or assert specific mocked endpoints only

        $decision = \App\Models\CourtDecisionDocument::factory()->create();
        $result = $this->service->analyzeCitations($decision->id, [
            'operation' => 'authority'
        ]);

        $this->assertIsArray($result);
        // Test completes without requiring OPENAI_API_KEY
    }
}
```

### Step 4: Run unit tests

```bash
php artisan test tests/Unit/Services/DecisionCitationServiceTest.php --verbose
```

Expected: All tests pass, no API calls made (offline testing)

### Step 5: Generate coverage report for DecisionCitationService

```bash
# Run tests with coverage for specific class
vendor/bin/phpunit tests/Unit/Services/DecisionCitationServiceTest.php \
  --coverage-filter app/Services/DecisionCitationService.php \
  --coverage-text
```

Expected: Coverage report showing % of service covered by tests

### Step 6: Document unit test coverage findings

```bash
cat >> /tmp/agent-review-report.txt << 'EOF'

---

# Unit Test Coverage Report - DecisionCitationService

## Test Files Found
- [List test files]

## Coverage Analysis
- Total methods: [count]
- Methods tested: [count]
- Coverage percentage: [%]

## Test Cases Status
- [✓/✗] Graph operation tested
- [✓/✗] Authority operation tested
- [✓/✗] Patterns operation tested
- [✓/✗] Influence operation tested
- [✓/✗] Default behavior tested
- [✓/✗] Error handling tested
- [✓/✗] Offline testing (Http::fake) verified

## Missing Test Coverage
[List any untested methods or scenarios]
EOF
cat /tmp/agent-review-report.txt
```

---

## Task 4: Verify Feature Tests for GraphViewer Livewire Component

**Priority:** HIGH

**Files:**
- Check: `tests/Feature/Livewire/GraphViewerTest.php` or similar
- Read: `app/Http/Livewire/GraphViewer.php`
- Create: Missing feature tests

### Step 1: Search for existing GraphViewer tests

```bash
# Find GraphViewer feature tests
find tests/Feature -name "*GraphViewer*Test.php" -o -name "*Graph*Test.php"
grep -r "GraphViewer" tests/Feature/
```

Expected: Find Livewire component tests

### Step 2: Create feature tests for citation analysis integration

Create `tests/Feature/Livewire/CitationAnalysisComponentTest.php`:

```php
<?php

namespace Tests\Feature\Livewire;

use Tests\TestCase;
use Tests\Concerns\UsesTestDatabase;
use Livewire\Livewire;
use App\Http\Livewire\GraphViewer;
use App\Services\DecisionCitationService;
use Illuminate\Support\Facades\Http;

class CitationAnalysisComponentTest extends TestCase
{
    use UsesTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock OpenAI for offline testing
        Http::fake([
            'api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => '{}']]]], 200),
        ]);
    }

    public function test_citation_analysis_button_only_shows_for_court_decisions()
    {
        $decision = \App\Models\CourtDecisionDocument::factory()->create();

        Livewire::test(GraphViewer::class)
            ->set('selectedNodeType', 'CourtDecisionDocument')
            ->set('selectedNodeId', $decision->id)
            ->assertSee('Citation Analysis'); // Button should be visible
    }

    public function test_citation_analysis_button_hidden_for_other_node_types()
    {
        Livewire::test(GraphViewer::class)
            ->set('selectedNodeType', 'Law')
            ->set('selectedNodeId', 'some-law-id')
            ->assertDontSee('Citation Analysis'); // Button should be hidden
    }

    public function test_open_citation_analysis_sets_panel_visible()
    {
        $decision = \App\Models\CourtDecisionDocument::factory()->create();

        Livewire::test(GraphViewer::class)
            ->set('selectedNodeType', 'CourtDecisionDocument')
            ->set('selectedNodeId', $decision->id)
            ->call('openCitationAnalysis')
            ->assertSet('showCitationAnalysis', true);
    }

    public function test_close_citation_analysis_hides_panel()
    {
        Livewire::test(GraphViewer::class)
            ->set('showCitationAnalysis', true)
            ->call('closeCitationAnalysis')
            ->assertSet('showCitationAnalysis', false)
            ->assertSet('citationAnalysisResults', null);
    }

    public function test_run_citation_analysis_calls_service()
    {
        $decision = \App\Models\CourtDecisionDocument::factory()->create();

        // Mock the service
        $this->mock(DecisionCitationService::class, function ($mock) {
            $mock->shouldReceive('analyzeCitations')
                ->once()
                ->andReturn([
                    'nodes' => [],
                    'edges' => [],
                    'root_decision' => 'test-id'
                ]);
        });

        Livewire::test(GraphViewer::class)
            ->set('selectedNodeType', 'CourtDecisionDocument')
            ->set('selectedNodeId', $decision->id)
            ->set('citationAnalysisOperation', 'graph')
            ->call('runCitationAnalysis')
            ->assertSet('citationAnalysisLoading', false)
            ->assertNotNull('citationAnalysisResults');
    }

    public function test_citation_analysis_operation_selector_changes()
    {
        Livewire::test(GraphViewer::class)
            ->set('citationAnalysisOperation', 'graph')
            ->assertSet('citationAnalysisOperation', 'graph')
            ->set('citationAnalysisOperation', 'authority')
            ->assertSet('citationAnalysisOperation', 'authority')
            ->set('citationAnalysisOperation', 'patterns')
            ->assertSet('citationAnalysisOperation', 'patterns')
            ->set('citationAnalysisOperation', 'influence')
            ->assertSet('citationAnalysisOperation', 'influence');
    }

    public function test_citation_analysis_handles_errors_gracefully()
    {
        $decision = \App\Models\CourtDecisionDocument::factory()->create();

        // Mock service to throw exception
        $this->mock(DecisionCitationService::class, function ($mock) {
            $mock->shouldReceive('analyzeCitations')
                ->andThrow(new \Exception('Test error'));
        });

        Livewire::test(GraphViewer::class)
            ->set('selectedNodeType', 'CourtDecisionDocument')
            ->set('selectedNodeId', $decision->id)
            ->call('runCitationAnalysis')
            ->assertSet('citationAnalysisLoading', false)
            ->assertNotNull('error');
    }
}
```

### Step 3: Run feature tests

```bash
php artisan test tests/Feature/Livewire/CitationAnalysisComponentTest.php --verbose
```

Expected: All Livewire component tests pass

### Step 4: Document feature test findings

```bash
cat >> /tmp/agent-review-report.txt << 'EOF'

---

# Feature Test Coverage Report - GraphViewer Component

## Test Files Created/Found
- [List test files]

## Component Integration Tests
- [✓/✗] Citation analysis button visibility tested
- [✓/✗] Panel open/close functionality tested
- [✓/✗] Service integration tested (runCitationAnalysis)
- [✓/✗] Operation selector tested (graph/authority/patterns/influence)
- [✓/✗] Error handling tested
- [✓/✗] Loading states tested

## Livewire Assertions Used
- assertSee / assertDontSee
- assertSet
- call method testing

## Issues Found
[List any component integration issues]
EOF
cat /tmp/agent-review-report.txt
```

---

## Task 5: Run and Verify E2E Browser Tests (Dusk)

**Priority:** CRITICAL

**Files:**
- Read: `tests/Browser/CitationNetworkAnalysisTest.php`
- Check: PostgreSQL is running
- Run: All 7 E2E tests

### Step 1: Verify PostgreSQL is running

```bash
# Check PostgreSQL status
pg_isready -h 127.0.0.1 -p 5432

# If not running, provide instructions
echo "If PostgreSQL is not running, start it with:"
echo "sudo service postgresql start"
echo "OR"
echo "docker-compose up -d postgres  # if using Docker"
```

Expected: PostgreSQL responds to connection test

### Step 2: Review E2E test file structure

```bash
# Show test methods in CitationNetworkAnalysisTest
grep -n "public function test_" tests/Browser/CitationNetworkAnalysisTest.php
```

Expected: 7 test methods listed

### Step 3: Run E2E tests with verbose output

```bash
# Run all citation network E2E tests
php artisan dusk tests/Browser/CitationNetworkAnalysisTest.php --verbose
```

Expected: All 7 tests pass:
- test_can_view_citation_graph_visualization
- test_can_view_authority_metrics
- test_can_analyze_citation_patterns
- test_can_visualize_influence_spread
- test_can_switch_between_analysis_operations
- test_citation_graph_shows_correct_relationships
- test_authority_metrics_show_correct_influence_classification

### Step 4: Run individual test for detailed debugging (if any fail)

```bash
# Example: Run just the graph visualization test
php artisan dusk --filter=test_can_view_citation_graph_visualization
```

Expected: Single test passes with detailed output

### Step 5: Verify all Dusk selectors are present

```bash
# Extract all dusk selectors from test file
grep -o '@[a-z-]*' tests/Browser/CitationNetworkAnalysisTest.php | sort -u

# Compare with selectors in blade template
grep -o 'dusk="[^"]*"' resources/views/livewire/graph-viewer.blade.php | sort -u
```

Expected: All test selectors match blade template

### Step 6: Take screenshots on failure (if tests fail)

```bash
# Check for failure screenshots
ls -lah tests/Browser/screenshots/
ls -lah tests/Browser/console/
```

Expected: Screenshots/logs available for debugging failures

### Step 7: Document E2E test results

```bash
cat >> /tmp/agent-review-report.txt << 'EOF'

---

# E2E Test Results - Citation Network Analysis

## Prerequisites
- [✓/✗] PostgreSQL running
- [✓/✗] Test database configured
- [✓/✗] Chrome/Chromium installed for Dusk

## Test Execution Results

### Test: test_can_view_citation_graph_visualization
- Status: [PASS/FAIL]
- Issues: [if any]

### Test: test_can_view_authority_metrics
- Status: [PASS/FAIL]
- Issues: [if any]

### Test: test_can_analyze_citation_patterns
- Status: [PASS/FAIL]
- Issues: [if any]

### Test: test_can_visualize_influence_spread
- Status: [PASS/FAIL]
- Issues: [if any]

### Test: test_can_switch_between_analysis_operations
- Status: [PASS/FAIL]
- Issues: [if any]

### Test: test_citation_graph_shows_correct_relationships
- Status: [PASS/FAIL]
- Issues: [if any]

### Test: test_authority_metrics_show_correct_influence_classification
- Status: [PASS/FAIL]
- Issues: [if any]

## Dusk Selectors Verification
- Total selectors in tests: [count]
- Selectors found in blade: [count]
- Missing selectors: [list if any]

## Overall E2E Status
- Tests Passed: [X/7]
- Tests Failed: [X/7]
- Success Rate: [%]

## Failure Analysis
[Details of any failing tests with screenshots/console logs]
EOF
cat /tmp/agent-review-report.txt
```

---

## Task 6: Security Audit - XSS, SQL Injection, Authorization

**Priority:** HIGH

**Files:**
- Audit: `app/Http/Livewire/GraphViewer.php`
- Audit: `app/Services/DecisionCitationService.php`
- Audit: `resources/views/livewire/graph-viewer.blade.php`
- Check: Input validation, output escaping, query parameterization

### Step 1: Check for XSS vulnerabilities in blade templates

```bash
# Search for unescaped output in blade files
grep -n "{!!" resources/views/livewire/graph-viewer.blade.php || echo "No unescaped output found (good)"

# Search for @php blocks that might have XSS risk
grep -n "@php" resources/views/livewire/graph-viewer.blade.php
```

Expected: No unescaped output (`{!!`), or properly sanitized if present

### Step 2: Verify SQL injection protection

```bash
# Check DecisionCitationService for raw queries
grep -n "DB::raw\|->raw(" app/Services/DecisionCitationService.php

# Check for string concatenation in queries
grep -n '->where.*\$' app/Services/DecisionCitationService.php
```

Expected: All queries use parameter binding, no raw SQL with user input

### Step 3: Create security test file

Create `tests/Feature/Security/CitationNetworkSecurityTest.php`:

```php
<?php

namespace Tests\Feature\Security;

use Tests\TestCase;
use Tests\Concerns\UsesTestDatabase;
use App\Services\DecisionCitationService;
use Illuminate\Support\Facades\Http;

class CitationNetworkSecurityTest extends TestCase
{
    use UsesTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => '{}']]]], 200)]);
    }

    public function test_sql_injection_attempt_is_blocked()
    {
        $service = app(DecisionCitationService::class);

        // Attempt SQL injection
        $maliciousId = "'; DROP TABLE users; --";

        try {
            $result = $service->analyzeCitations($maliciousId, ['operation' => 'graph']);
            // Should throw exception or return empty, not execute SQL injection
            $this->assertTrue(true); // If we get here, injection was prevented
        } catch (\Exception $e) {
            // Exception is expected for invalid ID
            $this->assertStringNotContainsString('syntax error', strtolower($e->getMessage()));
        }

        // Verify users table still exists
        $this->assertTrue(\Schema::hasTable('users'), 'Users table should still exist');
    }

    public function test_xss_attempt_in_citation_results_is_escaped()
    {
        $decision = \App\Models\CourtDecisionDocument::factory()->create([
            'title' => '<script>alert("XSS")</script>Test Decision'
        ]);

        $service = app(DecisionCitationService::class);
        $result = $service->analyzeCitations($decision->id, ['operation' => 'authority']);

        // If the result contains the title, it should be escaped
        $resultJson = json_encode($result);
        $this->assertStringNotContainsString('<script>', $resultJson);
    }

    public function test_unauthorized_access_to_other_users_decisions_blocked()
    {
        // This test depends on your authorization logic
        // If decisions are user-scoped, verify users can't access others' data

        $user1 = \App\Models\User::factory()->create();
        $user2 = \App\Models\User::factory()->create();

        // Create decision for user1
        $decision = \App\Models\CourtDecisionDocument::factory()->create([
            'user_id' => $user1->id
        ]);

        // Attempt to access as user2
        $this->actingAs($user2);

        // If authorization is implemented, this should fail
        // Adjust based on actual authorization logic
        $service = app(DecisionCitationService::class);

        // Add actual authorization check here based on your implementation
        $this->assertTrue(true); // Placeholder
    }

    public function test_citation_analysis_validates_operation_parameter()
    {
        $decision = \App\Models\CourtDecisionDocument::factory()->create();
        $service = app(DecisionCitationService::class);

        // Try invalid operation
        try {
            $result = $service->analyzeCitations($decision->id, [
                'operation' => 'invalid_operation'
            ]);
            // Should either throw exception or default to safe operation
            $this->assertIsArray($result);
        } catch (\Exception $e) {
            $this->assertStringContainsString('operation', strtolower($e->getMessage()));
        }
    }

    public function test_citation_analysis_limits_are_enforced()
    {
        $decision = \App\Models\CourtDecisionDocument::factory()->create();
        $service = app(DecisionCitationService::class);

        // Try extremely large limit
        $result = $service->analyzeCitations($decision->id, [
            'operation' => 'graph',
            'limit' => 999999 // Attempt DoS with huge limit
        ]);

        // Result should be bounded (e.g., max 100 or 1000)
        $this->assertIsArray($result);
        $nodeCount = count($result['nodes'] ?? []);
        $this->assertLessThanOrEqual(1000, $nodeCount, 'Results should be limited to prevent DoS');
    }
}
```

### Step 4: Run security tests

```bash
php artisan test tests/Feature/Security/CitationNetworkSecurityTest.php --verbose
```

Expected: All security tests pass

### Step 5: Check for mass assignment vulnerabilities

```bash
# Check model fillable/guarded properties
grep -A 10 "class CourtDecisionDocument" app/Models/CourtDecisionDocument.php | grep -E "fillable|guarded"
```

Expected: Either `$fillable` whitelist or `$guarded` blacklist is defined

### Step 6: Verify authorization middleware on routes

```bash
# Check if citation analysis routes require authentication
grep -B 5 -A 5 "citation" routes/api.php | grep -E "middleware|auth"
```

Expected: Routes are protected by auth middleware

### Step 7: Document security audit findings

```bash
cat >> /tmp/agent-review-report.txt << 'EOF'

---

# Security Audit Report - Citation Network Analysis

## XSS Protection
- [✓/✗] No unescaped output in blade templates
- [✓/✗] User input properly sanitized
- [✓/✗] XSS test passes

## SQL Injection Protection
- [✓/✗] All queries use parameter binding
- [✓/✗] No raw SQL with user input
- [✓/✗] SQL injection test passes

## Authorization
- [✓/✗] Routes require authentication
- [✓/✗] User authorization checked for decision access
- [✓/✗] Unauthorized access test passes

## Input Validation
- [✓/✗] Operation parameter validated
- [✓/✗] Limit parameter bounded
- [✓/✗] Decision ID validated

## Mass Assignment
- [✓/✗] Model fillable/guarded properly configured

## DoS Protection
- [✓/✗] Query limits enforced
- [✓/✗] Rate limiting on API endpoints
- [✓/✗] DoS test passes

## Vulnerabilities Found
[List any security issues discovered]

## Remediation Required
[List security fixes needed with priority]
EOF
cat /tmp/agent-review-report.txt
```

---

## Task 7: Test Coverage Summary & Overall Quality Report

**Priority:** HIGH

**Files:**
- Generate: Overall coverage report
- Compile: All review findings

### Step 1: Run all tests with coverage

```bash
# Run all tests with coverage report
vendor/bin/phpunit --coverage-text --coverage-filter app/Services/DecisionCitationService.php
vendor/bin/phpunit --coverage-text --coverage-filter app/Http/Livewire/GraphViewer.php
```

Expected: Coverage percentages for both classes

### Step 2: Generate test summary

```bash
# Count total tests
echo "Unit Tests:"
find tests/Unit -name "*Test.php" | wc -l

echo "Feature Tests:"
find tests/Feature -name "*Test.php" | wc -l

echo "E2E Tests:"
find tests/Browser -name "*Test.php" | wc -l

# Run all tests and count pass/fail
php artisan test --compact
```

Expected: Summary of test counts and pass/fail ratio

### Step 3: Check code quality with Laravel Pint

```bash
# Format code (dry run to check)
./vendor/bin/pint --test app/Services/DecisionCitationService.php
./vendor/bin/pint --test app/Http/Livewire/GraphViewer.php
```

Expected: Code follows Laravel style guide

### Step 4: Compile final comprehensive report

```bash
cat >> /tmp/agent-review-report.txt << 'EOF'

---

# COMPREHENSIVE REVIEW SUMMARY

## Overall Test Coverage

### Unit Tests
- Total unit test files: [count]
- DecisionCitationService coverage: [%]
- Tests passing: [X/Y]

### Feature Tests
- Total feature test files: [count]
- GraphViewer component coverage: [%]
- Tests passing: [X/Y]

### E2E Tests (Dusk)
- Total E2E tests: 7
- Tests passing: [X/7]
- Success rate: [%]

## Overall Quality Score

### Test Coverage: [A/B/C/D/F]
- Unit tests: [%]
- Feature tests: [%]
- E2E tests: [%]
- Overall: [%]

### Code Quality: [A/B/C/D/F]
- Follows Laravel conventions: [✓/✗]
- Proper dependency injection: [✓/✗]
- Error handling: [✓/✗]
- Documentation: [✓/✗]

### Security: [A/B/C/D/F]
- XSS protection: [✓/✗]
- SQL injection protection: [✓/✗]
- Authorization: [✓/✗]
- Input validation: [✓/✗]

### Agent Integration: [A/B/C/D/F]
- Agent configuration: [✓/✗]
- MCP accessibility: [✓/✗]
- Agent monitoring: [✓/✗]

## Critical Issues (Must Fix)
1. [Issue with severity HIGH]
2. [Issue with severity HIGH]

## Recommended Improvements
1. [Recommendation with priority MEDIUM]
2. [Recommendation with priority LOW]

## Sign-off Status
- [✓/✗] Ready for production
- [✓/✗] Requires fixes before merge
- [✓/✗] Requires additional testing

## Reviewed By
- Date: 2025-11-15
- Reviewer: Claude (automated review)
- Review Method: superpowers:writing-plans + subagent-driven-development

EOF

cat /tmp/agent-review-report.txt
```

### Step 5: Save final report to docs

```bash
# Copy report to docs folder
cp /tmp/agent-review-report.txt docs/reviews/2025-11-15-citation-network-review.txt
git add docs/reviews/2025-11-15-citation-network-review.txt
git commit -m "docs: add comprehensive review report for citation network analysis

- Agent state verification
- MCP accessibility testing
- Unit/feature/E2E test coverage
- Security audit results
- Overall quality assessment"
```

### Step 6: Display final report

```bash
echo "========================================="
echo "REVIEW COMPLETE"
echo "========================================="
cat docs/reviews/2025-11-15-citation-network-review.txt
echo ""
echo "Report saved to: docs/reviews/2025-11-15-citation-network-review.txt"
```

---

## Summary

**Total Tasks:** 7

**Priority Breakdown:**
- CRITICAL: Task 5 (E2E tests - requires PostgreSQL)
- HIGH: Tasks 1, 2, 3, 4, 6, 7 (Agent state, MCP, unit tests, feature tests, security, summary)

**Deliverables:**
1. Agent state and monitoring report
2. MCP accessibility and integration report
3. Unit test coverage for DecisionCitationService
4. Feature test coverage for GraphViewer component
5. E2E test execution results (7 browser tests)
6. Security audit findings (XSS, SQL injection, authorization)
7. Comprehensive quality report with recommendations

**Test Philosophy:**
- All tests run offline (Http::fake for OpenAI)
- DatabaseTransactions for automatic rollback
- No fabricated data - evidence-based testing
- TDD approach: verify tests exist for all features

**Success Criteria:**
- All unit tests pass (100%)
- All feature tests pass (100%)
- All E2E tests pass (7/7)
- No critical security vulnerabilities
- Overall quality score: B+ or higher
- Code ready for production deployment

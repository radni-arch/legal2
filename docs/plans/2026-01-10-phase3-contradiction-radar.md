# Phase 3: Contradiction Radar Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Proactive alert system that warns lawyers about contradictions, superseded laws, and outdated citations when pinning nodes or on-demand.

**Architecture:** New `ContradictionRadarService` orchestrates Neo4j queries for graph-based alerts. Integrates with existing `ForceGraphController::pinNode()` and stores alerts in `research_sessions.alerts` JSONB. UI shows floating alert panel + node badges.

**Tech Stack:** Laravel 11, Livewire 3, Alpine.js, Neo4j (Laudis client), PHPUnit

---

## Task 1: Create ContradictionRadarService with Direct Contradictions

**Files:**
- Create: `app/Services/Graph/ContradictionRadarService.php`
- Create: `tests/Unit/Services/Graph/ContradictionRadarServiceTest.php`

**Step 1: Write the failing test for findDirectContradictions**

```php
<?php

namespace Tests\Unit\Services\Graph;

use App\Services\Graph\ContradictionRadarService;
use App\Services\GraphDatabaseService;
use Mockery;
use Tests\TestCase;

class ContradictionRadarServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_finds_direct_contradictions_for_node(): void
    {
        $mockGraphDb = Mockery::mock(GraphDatabaseService::class);
        $mockGraphDb->shouldReceive('runQuery')
            ->once()
            ->andReturn([
                [
                    'node_id' => '456',
                    'case_number' => 'VSRH-456/2022',
                    'title' => 'Contradicting Decision',
                ],
            ]);

        $service = new ContradictionRadarService($mockGraphDb);

        $alerts = $service->findDirectContradictions('123');

        $this->assertCount(1, $alerts);
        $this->assertEquals('direct_contradiction', $alerts[0]['type']);
        $this->assertEquals('critical', $alerts[0]['severity']);
        $this->assertEquals('123', $alerts[0]['source_node_id']);
        $this->assertEquals('456', $alerts[0]['related_node_id']);
        $this->assertStringContains('VSRH-456/2022', $alerts[0]['message']);
    }

    /** @test */
    public function it_returns_empty_array_when_no_contradictions(): void
    {
        $mockGraphDb = Mockery::mock(GraphDatabaseService::class);
        $mockGraphDb->shouldReceive('runQuery')
            ->once()
            ->andReturn([]);

        $service = new ContradictionRadarService($mockGraphDb);

        $alerts = $service->findDirectContradictions('123');

        $this->assertEmpty($alerts);
    }
}
```

**Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Services/Graph/ContradictionRadarServiceTest.php --filter=it_finds_direct_contradictions_for_node`
Expected: FAIL - Class 'App\Services\Graph\ContradictionRadarService' not found

**Step 3: Write minimal implementation**

```php
<?php

namespace App\Services\Graph;

use App\Services\GraphDatabaseService;
use Illuminate\Support\Str;

class ContradictionRadarService
{
    public function __construct(
        protected GraphDatabaseService $graphDb
    ) {}

    /**
     * Find direct contradictions for a node (CONTRADICTS relationship)
     */
    public function findDirectContradictions(string $nodeId): array
    {
        $cypher = <<<'CYPHER'
            MATCH (pinned)-[:CONTRADICTS]-(contradicting)
            WHERE id(pinned) = $nodeId OR pinned.id = $nodeId
            RETURN
                contradicting.id AS node_id,
                contradicting.case_number AS case_number,
                contradicting.title AS title
        CYPHER;

        $results = $this->graphDb->runQuery($cypher, ['nodeId' => $nodeId]);

        return array_map(fn($row) => [
            'id' => (string) Str::uuid(),
            'type' => 'direct_contradiction',
            'severity' => 'critical',
            'source_node_id' => $nodeId,
            'related_node_id' => $row['node_id'],
            'message' => "Decision {$row['case_number']} contradicts this node",
            'dismissed' => false,
            'created_at' => now()->toISOString(),
        ], $results);
    }
}
```

**Step 4: Run test to verify it passes**

Run: `php artisan test tests/Unit/Services/Graph/ContradictionRadarServiceTest.php`
Expected: PASS (2 tests)

**Step 5: Commit**

```bash
git add app/Services/Graph/ContradictionRadarService.php tests/Unit/Services/Graph/ContradictionRadarServiceTest.php
git commit -m "feat(radar): Add ContradictionRadarService with direct contradiction detection"
```

---

## Task 2: Add Superseded Law Detection

**Files:**
- Modify: `app/Services/Graph/ContradictionRadarService.php`
- Modify: `tests/Unit/Services/Graph/ContradictionRadarServiceTest.php`

**Step 1: Write the failing test**

```php
/** @test */
public function it_finds_superseded_law_citations(): void
{
    $mockGraphDb = Mockery::mock(GraphDatabaseService::class);
    $mockGraphDb->shouldReceive('runQuery')
        ->once()
        ->andReturn([
            [
                'old_law_id' => '100',
                'old_law_number' => 'ZKP Art. 9 (2018)',
                'new_law_id' => '200',
                'new_law_number' => 'ZKP Art. 9 (2021)',
            ],
        ]);

    $service = new ContradictionRadarService($mockGraphDb);

    $alerts = $service->findSupersededLawCitations('123');

    $this->assertCount(1, $alerts);
    $this->assertEquals('superseded_law', $alerts[0]['type']);
    $this->assertEquals('caution', $alerts[0]['severity']);
    $this->assertStringContains('superseded', strtolower($alerts[0]['message']));
}
```

**Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Services/Graph/ContradictionRadarServiceTest.php --filter=it_finds_superseded_law_citations`
Expected: FAIL - Method findSupersededLawCitations does not exist

**Step 3: Add implementation**

```php
/**
 * Find citations to superseded laws (SUPERSEDES relationship)
 */
public function findSupersededLawCitations(string $nodeId): array
{
    $cypher = <<<'CYPHER'
        MATCH (decision:Decision)-[:CITES]->(oldLaw)-[:SUPERSEDED_BY]->(newLaw)
        WHERE id(decision) = $nodeId OR decision.id = $nodeId
        RETURN
            oldLaw.id AS old_law_id,
            oldLaw.law_number AS old_law_number,
            newLaw.id AS new_law_id,
            newLaw.law_number AS new_law_number
    CYPHER;

    $results = $this->graphDb->runQuery($cypher, ['nodeId' => $nodeId]);

    return array_map(fn($row) => [
        'id' => (string) Str::uuid(),
        'type' => 'superseded_law',
        'severity' => 'caution',
        'source_node_id' => $nodeId,
        'related_node_id' => $row['old_law_id'],
        'message' => "Cites {$row['old_law_number']} which was superseded by {$row['new_law_number']}",
        'dismissed' => false,
        'created_at' => now()->toISOString(),
    ], $results);
}
```

**Step 4: Run test to verify it passes**

Run: `php artisan test tests/Unit/Services/Graph/ContradictionRadarServiceTest.php`
Expected: PASS (3 tests)

**Step 5: Commit**

```bash
git add app/Services/Graph/ContradictionRadarService.php tests/Unit/Services/Graph/ContradictionRadarServiceTest.php
git commit -m "feat(radar): Add superseded law citation detection"
```

---

## Task 3: Add Outdated Citation Detection

**Files:**
- Modify: `app/Services/Graph/ContradictionRadarService.php`
- Modify: `tests/Unit/Services/Graph/ContradictionRadarServiceTest.php`

**Step 1: Write the failing test**

```php
/** @test */
public function it_finds_outdated_citations(): void
{
    $mockGraphDb = Mockery::mock(GraphDatabaseService::class);
    $mockGraphDb->shouldReceive('runQuery')
        ->once()
        ->andReturn([
            [
                'law_id' => '100',
                'law_number' => 'ZKP Art. 15',
                'valid_until' => '2020-01-01',
                'decision_date' => '2022-05-15',
            ],
        ]);

    $service = new ContradictionRadarService($mockGraphDb);

    $alerts = $service->findOutdatedCitations('123');

    $this->assertCount(1, $alerts);
    $this->assertEquals('outdated_citation', $alerts[0]['type']);
    $this->assertEquals('caution', $alerts[0]['severity']);
    $this->assertStringContains('valid until', strtolower($alerts[0]['message']));
}
```

**Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Services/Graph/ContradictionRadarServiceTest.php --filter=it_finds_outdated_citations`
Expected: FAIL - Method findOutdatedCitations does not exist

**Step 3: Add implementation**

```php
/**
 * Find citations to laws after their valid_until date
 */
public function findOutdatedCitations(string $nodeId): array
{
    $cypher = <<<'CYPHER'
        MATCH (decision:Decision)-[:CITES]->(law)
        WHERE (id(decision) = $nodeId OR decision.id = $nodeId)
          AND law.valid_until IS NOT NULL
          AND decision.decision_date > law.valid_until
        RETURN
            law.id AS law_id,
            law.law_number AS law_number,
            law.valid_until AS valid_until,
            decision.decision_date AS decision_date
    CYPHER;

    $results = $this->graphDb->runQuery($cypher, ['nodeId' => $nodeId]);

    return array_map(fn($row) => [
        'id' => (string) Str::uuid(),
        'type' => 'outdated_citation',
        'severity' => 'caution',
        'source_node_id' => $nodeId,
        'related_node_id' => $row['law_id'],
        'message' => "Cites {$row['law_number']} which was only valid until {$row['valid_until']}",
        'dismissed' => false,
        'created_at' => now()->toISOString(),
    ], $results);
}
```

**Step 4: Run test to verify it passes**

Run: `php artisan test tests/Unit/Services/Graph/ContradictionRadarServiceTest.php`
Expected: PASS (4 tests)

**Step 5: Commit**

```bash
git add app/Services/Graph/ContradictionRadarService.php tests/Unit/Services/Graph/ContradictionRadarServiceTest.php
git commit -m "feat(radar): Add outdated citation detection"
```

---

## Task 4: Add scanNode() Orchestration Method

**Files:**
- Modify: `app/Services/Graph/ContradictionRadarService.php`
- Modify: `tests/Unit/Services/Graph/ContradictionRadarServiceTest.php`

**Step 1: Write the failing test**

```php
/** @test */
public function it_scans_node_and_combines_all_alerts(): void
{
    $mockGraphDb = Mockery::mock(GraphDatabaseService::class);

    // Mock three separate queries
    $mockGraphDb->shouldReceive('runQuery')
        ->times(3)
        ->andReturn(
            [['node_id' => '456', 'case_number' => 'VSRH-456', 'title' => 'Test']], // contradictions
            [['old_law_id' => '100', 'old_law_number' => 'ZKP 9', 'new_law_id' => '200', 'new_law_number' => 'ZKP 9 (new)']], // superseded
            [] // no outdated
        );

    $service = new ContradictionRadarService($mockGraphDb);

    $alerts = $service->scanNode('123', 'Decision');

    $this->assertCount(2, $alerts);
    $this->assertEquals('direct_contradiction', $alerts[0]['type']);
    $this->assertEquals('superseded_law', $alerts[1]['type']);
}

/** @test */
public function it_only_checks_law_alerts_for_decision_nodes(): void
{
    $mockGraphDb = Mockery::mock(GraphDatabaseService::class);

    // Only expect contradiction query for non-Decision nodes
    $mockGraphDb->shouldReceive('runQuery')
        ->once()
        ->andReturn([]);

    $service = new ContradictionRadarService($mockGraphDb);

    $alerts = $service->scanNode('123', 'Law');

    $this->assertEmpty($alerts);
}
```

**Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Services/Graph/ContradictionRadarServiceTest.php --filter=it_scans_node_and_combines_all_alerts`
Expected: FAIL - Method scanNode does not exist

**Step 3: Add implementation**

```php
/**
 * Scan a node for all alert types (triggered on pin)
 */
public function scanNode(string $nodeId, string $nodeType): array
{
    $alerts = [];

    // 1. Direct contradictions (all node types)
    $alerts = array_merge($alerts, $this->findDirectContradictions($nodeId));

    // 2. Superseded laws (Decision nodes only)
    if ($nodeType === 'Decision') {
        $alerts = array_merge($alerts, $this->findSupersededLawCitations($nodeId));
    }

    // 3. Outdated citations (Decision nodes only)
    if ($nodeType === 'Decision') {
        $alerts = array_merge($alerts, $this->findOutdatedCitations($nodeId));
    }

    return $alerts;
}
```

**Step 4: Run test to verify it passes**

Run: `php artisan test tests/Unit/Services/Graph/ContradictionRadarServiceTest.php`
Expected: PASS (6 tests)

**Step 5: Commit**

```bash
git add app/Services/Graph/ContradictionRadarService.php tests/Unit/Services/Graph/ContradictionRadarServiceTest.php
git commit -m "feat(radar): Add scanNode orchestration method"
```

---

## Task 5: Add Session Alert Management Methods

**Files:**
- Modify: `app/Services/Graph/ContradictionRadarService.php`
- Modify: `tests/Unit/Services/Graph/ContradictionRadarServiceTest.php`

**Step 1: Write the failing tests**

```php
/** @test */
public function it_adds_alerts_to_session(): void
{
    $mockGraphDb = Mockery::mock(GraphDatabaseService::class);
    $service = new ContradictionRadarService($mockGraphDb);

    $session = ResearchSession::factory()->create(['alerts' => []]);
    $alerts = [
        ['id' => 'alert-1', 'type' => 'direct_contradiction', 'message' => 'Test'],
    ];

    $service->addAlertsToSession($session, $alerts);

    $session->refresh();
    $this->assertCount(1, $session->alerts);
    $this->assertEquals('alert-1', $session->alerts[0]['id']);
}

/** @test */
public function it_dismisses_alert_by_id(): void
{
    $mockGraphDb = Mockery::mock(GraphDatabaseService::class);
    $service = new ContradictionRadarService($mockGraphDb);

    $session = ResearchSession::factory()->create([
        'alerts' => [
            ['id' => 'alert-1', 'dismissed' => false],
            ['id' => 'alert-2', 'dismissed' => false],
        ],
    ]);

    $service->dismissAlert($session, 'alert-1');

    $session->refresh();
    $this->assertTrue($session->alerts[0]['dismissed']);
    $this->assertFalse($session->alerts[1]['dismissed']);
}

/** @test */
public function it_skips_duplicate_alerts(): void
{
    $mockGraphDb = Mockery::mock(GraphDatabaseService::class);
    $service = new ContradictionRadarService($mockGraphDb);

    $session = ResearchSession::factory()->create([
        'alerts' => [
            ['id' => 'existing', 'source_node_id' => '123', 'related_node_id' => '456', 'type' => 'direct_contradiction'],
        ],
    ]);

    $newAlerts = [
        ['id' => 'new-1', 'source_node_id' => '123', 'related_node_id' => '456', 'type' => 'direct_contradiction'], // duplicate
        ['id' => 'new-2', 'source_node_id' => '123', 'related_node_id' => '789', 'type' => 'direct_contradiction'], // new
    ];

    $service->addAlertsToSession($session, $newAlerts);

    $session->refresh();
    $this->assertCount(2, $session->alerts); // existing + new-2, not new-1
}
```

**Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Services/Graph/ContradictionRadarServiceTest.php --filter=it_adds_alerts_to_session`
Expected: FAIL - Method addAlertsToSession does not exist

**Step 3: Add implementation**

```php
use App\Models\ResearchSession;

/**
 * Add alerts to a session (avoiding duplicates)
 */
public function addAlertsToSession(ResearchSession $session, array $newAlerts): void
{
    $existingAlerts = $session->alerts ?? [];

    // Create a set of existing alert signatures to detect duplicates
    $existingSignatures = array_map(
        fn($alert) => $this->getAlertSignature($alert),
        $existingAlerts
    );

    foreach ($newAlerts as $alert) {
        $signature = $this->getAlertSignature($alert);
        if (!in_array($signature, $existingSignatures)) {
            $existingAlerts[] = $alert;
            $existingSignatures[] = $signature;
        }
    }

    $session->update(['alerts' => $existingAlerts]);
}

/**
 * Dismiss an alert by ID
 */
public function dismissAlert(ResearchSession $session, string $alertId): void
{
    $alerts = $session->alerts ?? [];

    foreach ($alerts as &$alert) {
        if ($alert['id'] === $alertId) {
            $alert['dismissed'] = true;
            break;
        }
    }

    $session->update(['alerts' => $alerts]);
}

/**
 * Generate a unique signature for an alert to detect duplicates
 */
protected function getAlertSignature(array $alert): string
{
    return implode(':', [
        $alert['source_node_id'] ?? '',
        $alert['related_node_id'] ?? '',
        $alert['type'] ?? '',
    ]);
}
```

**Step 4: Run test to verify it passes**

Run: `php artisan test tests/Unit/Services/Graph/ContradictionRadarServiceTest.php`
Expected: PASS (9 tests)

**Step 5: Commit**

```bash
git add app/Services/Graph/ContradictionRadarService.php tests/Unit/Services/Graph/ContradictionRadarServiceTest.php
git commit -m "feat(radar): Add session alert management methods"
```

---

## Task 6: Add scanSession() for On-Demand Full Scan

**Files:**
- Modify: `app/Services/Graph/ContradictionRadarService.php`
- Modify: `tests/Unit/Services/Graph/ContradictionRadarServiceTest.php`

**Step 1: Write the failing test**

```php
/** @test */
public function it_scans_all_session_nodes_on_demand(): void
{
    $mockGraphDb = Mockery::mock(GraphDatabaseService::class);

    // Expect 6 queries: 3 per pinned node × 2 pinned nodes
    $mockGraphDb->shouldReceive('runQuery')
        ->times(6)
        ->andReturn([]);

    $service = new ContradictionRadarService($mockGraphDb);

    $session = ResearchSession::factory()->create([
        'pinned_nodes' => [
            ['id' => '123', 'type' => 'Decision'],
            ['id' => '456', 'type' => 'Decision'],
        ],
        'alerts' => [],
    ]);

    $alerts = $service->scanSession($session);

    $this->assertIsArray($alerts);
}
```

**Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Services/Graph/ContradictionRadarServiceTest.php --filter=it_scans_all_session_nodes_on_demand`
Expected: FAIL - Method scanSession does not exist

**Step 3: Add implementation**

```php
/**
 * Scan all pinned nodes in a session (on-demand full scan)
 */
public function scanSession(ResearchSession $session): array
{
    $allAlerts = [];
    $pinnedNodes = $session->pinned_nodes ?? [];

    foreach ($pinnedNodes as $node) {
        $nodeId = $node['id'] ?? null;
        $nodeType = $node['type'] ?? 'Unknown';

        if ($nodeId) {
            $alerts = $this->scanNode($nodeId, $nodeType);
            $allAlerts = array_merge($allAlerts, $alerts);
        }
    }

    // Add to session (avoiding duplicates)
    if (!empty($allAlerts)) {
        $this->addAlertsToSession($session, $allAlerts);
    }

    return $allAlerts;
}
```

**Step 4: Run test to verify it passes**

Run: `php artisan test tests/Unit/Services/Graph/ContradictionRadarServiceTest.php`
Expected: PASS (10 tests)

**Step 5: Commit**

```bash
git add app/Services/Graph/ContradictionRadarService.php tests/Unit/Services/Graph/ContradictionRadarServiceTest.php
git commit -m "feat(radar): Add scanSession for on-demand full scan"
```

---

## Task 7: Integrate with ForceGraphController::pinNode()

**Files:**
- Modify: `app/Livewire/Graph/ForceGraphController.php`
- Create: `tests/Feature/Graph/ContradictionRadarTest.php`

**Step 1: Write the failing feature test**

```php
<?php

namespace Tests\Feature\Graph;

use App\Livewire\Graph\ForceGraphController;
use App\Models\ResearchSession;
use App\Models\User;
use App\Services\Graph\ContradictionRadarService;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class ContradictionRadarTest extends TestCase
{
    /** @test */
    public function it_triggers_scan_when_node_is_pinned(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $mockRadar = Mockery::mock(ContradictionRadarService::class);
        $mockRadar->shouldReceive('scanNode')
            ->once()
            ->with('123', 'Decision')
            ->andReturn([
                ['id' => 'alert-1', 'type' => 'direct_contradiction', 'message' => 'Test alert'],
            ]);
        $mockRadar->shouldReceive('addAlertsToSession')
            ->once();

        $this->app->instance(ContradictionRadarService::class, $mockRadar);

        Livewire::test(ForceGraphController::class)
            ->dispatch('pin-node', node: ['id' => '123', 'type' => 'Decision']);
    }

    /** @test */
    public function it_dispatches_alerts_updated_event(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $mockRadar = Mockery::mock(ContradictionRadarService::class);
        $mockRadar->shouldReceive('scanNode')->andReturn([
            ['id' => 'alert-1', 'type' => 'direct_contradiction'],
        ]);
        $mockRadar->shouldReceive('addAlertsToSession');

        $this->app->instance(ContradictionRadarService::class, $mockRadar);

        Livewire::test(ForceGraphController::class)
            ->dispatch('pin-node', node: ['id' => '123', 'type' => 'Decision'])
            ->assertDispatched('alerts-updated');
    }
}
```

**Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Graph/ContradictionRadarTest.php`
Expected: FAIL - assertions fail (no scan triggered yet)

**Step 3: Modify ForceGraphController::pinNode()**

In `app/Livewire/Graph/ForceGraphController.php`, update the `pinNode` method:

```php
use App\Services\Graph\ContradictionRadarService;

#[On('pin-node')]
public function pinNode(array $node): void
{
    if (Auth::check()) {
        $sessionService = app(ResearchSessionService::class);
        $sessionService->trackPinnedNode($node);

        // Scan for alerts on pin
        $radarService = app(ContradictionRadarService::class);
        $session = $sessionService->getCurrentSession();
        $alerts = $radarService->scanNode($node['id'], $node['type'] ?? 'Unknown');

        if (!empty($alerts)) {
            $radarService->addAlertsToSession($session, $alerts);
            $this->dispatch('alerts-updated', alerts: $session->fresh()->alerts);
        }
    }
}
```

**Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Graph/ContradictionRadarTest.php`
Expected: PASS (2 tests)

**Step 5: Commit**

```bash
git add app/Livewire/Graph/ForceGraphController.php tests/Feature/Graph/ContradictionRadarTest.php
git commit -m "feat(radar): Integrate ContradictionRadarService with pinNode"
```

---

## Task 8: Create AlertPanelController Livewire Component

**Files:**
- Create: `app/Livewire/Graph/AlertPanelController.php`
- Create: `resources/views/livewire/graph/alert-panel.blade.php`
- Create: `tests/Feature/Livewire/Graph/AlertPanelControllerTest.php`

**Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Livewire\Graph;

use App\Livewire\Graph\AlertPanelController;
use App\Models\ResearchSession;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

class AlertPanelControllerTest extends TestCase
{
    /** @test */
    public function it_renders_alerts_for_current_session(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        ResearchSession::factory()->create([
            'user_id' => $user->id,
            'last_activity_at' => now(),
            'alerts' => [
                ['id' => 'alert-1', 'type' => 'direct_contradiction', 'severity' => 'critical', 'message' => 'Test alert', 'dismissed' => false],
            ],
        ]);

        Livewire::test(AlertPanelController::class)
            ->assertSee('Test alert')
            ->assertSee('CRITICAL');
    }

    /** @test */
    public function it_shows_empty_state_when_no_alerts(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        ResearchSession::factory()->create([
            'user_id' => $user->id,
            'last_activity_at' => now(),
            'alerts' => [],
        ]);

        Livewire::test(AlertPanelController::class)
            ->assertSee('No alerts');
    }
}
```

**Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Livewire/Graph/AlertPanelControllerTest.php`
Expected: FAIL - Class AlertPanelController not found

**Step 3: Create the Livewire component**

```php
<?php

namespace App\Livewire\Graph;

use App\Models\ResearchSession;
use App\Services\Graph\ContradictionRadarService;
use App\Services\Graph\ResearchSessionService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class AlertPanelController extends Component
{
    public array $alerts = [];
    public bool $isOpen = true;

    public function mount(): void
    {
        $this->loadAlerts();
    }

    #[On('alerts-updated')]
    public function refreshAlerts(array $alerts = []): void
    {
        $this->alerts = array_filter($alerts, fn($a) => !($a['dismissed'] ?? false));
    }

    public function loadAlerts(): void
    {
        if (!Auth::check()) {
            $this->alerts = [];
            return;
        }

        $sessionService = app(ResearchSessionService::class);
        $session = $sessionService->getCurrentSession();
        $this->alerts = array_filter(
            $session->alerts ?? [],
            fn($a) => !($a['dismissed'] ?? false)
        );
    }

    public function dismissAlert(string $alertId): void
    {
        if (!Auth::check()) {
            return;
        }

        $sessionService = app(ResearchSessionService::class);
        $radarService = app(ContradictionRadarService::class);

        $session = $sessionService->getCurrentSession();
        $radarService->dismissAlert($session, $alertId);

        $this->loadAlerts();
    }

    public function scanAllNodes(): void
    {
        if (!Auth::check()) {
            return;
        }

        $sessionService = app(ResearchSessionService::class);
        $radarService = app(ContradictionRadarService::class);

        $session = $sessionService->getCurrentSession();
        $radarService->scanSession($session);

        $this->loadAlerts();
    }

    public function toggle(): void
    {
        $this->isOpen = !$this->isOpen;
    }

    public function render()
    {
        return view('livewire.graph.alert-panel');
    }
}
```

**Step 4: Create the Blade view**

```blade
<div class="fixed right-4 top-20 w-80 z-50" x-data="{ open: @entangle('isOpen') }">
    <!-- Toggle button -->
    <button
        @click="open = !open"
        class="absolute -left-10 top-0 bg-gray-800 text-white p-2 rounded-l-lg"
    >
        <span x-show="!open">⚠️</span>
        <span x-show="open">✕</span>
    </button>

    <!-- Panel -->
    <div x-show="open" x-transition class="bg-white dark:bg-gray-800 rounded-lg shadow-xl border border-gray-200 dark:border-gray-700">
        <!-- Header -->
        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
            <h3 class="font-semibold text-gray-900 dark:text-white">
                ⚠️ Research Alerts ({{ count($alerts) }})
            </h3>
        </div>

        <!-- Alerts list -->
        <div class="max-h-96 overflow-y-auto">
            @forelse($alerts as $alert)
                <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 last:border-b-0">
                    <!-- Severity badge -->
                    <div class="flex items-center gap-2 mb-1">
                        @if($alert['severity'] === 'critical')
                            <span class="px-2 py-0.5 text-xs font-bold bg-red-100 text-red-800 rounded">🔴 CRITICAL</span>
                        @elseif($alert['severity'] === 'warning')
                            <span class="px-2 py-0.5 text-xs font-bold bg-yellow-100 text-yellow-800 rounded">🟡 WARNING</span>
                        @else
                            <span class="px-2 py-0.5 text-xs font-bold bg-orange-100 text-orange-800 rounded">🟠 CAUTION</span>
                        @endif
                    </div>

                    <!-- Message -->
                    <p class="text-sm text-gray-700 dark:text-gray-300 mb-2">
                        {{ $alert['message'] }}
                    </p>

                    <!-- Actions -->
                    <div class="flex gap-2">
                        <button
                            wire:click="dismissAlert('{{ $alert['id'] }}')"
                            class="text-xs text-gray-500 hover:text-gray-700"
                        >
                            Dismiss
                        </button>
                    </div>
                </div>
            @empty
                <div class="px-4 py-8 text-center text-gray-500">
                    <p>No alerts</p>
                    <p class="text-xs mt-1">Pin nodes to scan for issues</p>
                </div>
            @endforelse
        </div>

        <!-- Footer -->
        <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">
            <button
                wire:click="scanAllNodes"
                class="w-full px-4 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700"
            >
                🔍 Scan All Pinned Nodes
            </button>
        </div>
    </div>
</div>
```

**Step 5: Run test to verify it passes**

Run: `php artisan test tests/Feature/Livewire/Graph/AlertPanelControllerTest.php`
Expected: PASS (2 tests)

**Step 6: Commit**

```bash
git add app/Livewire/Graph/AlertPanelController.php resources/views/livewire/graph/alert-panel.blade.php tests/Feature/Livewire/Graph/AlertPanelControllerTest.php
git commit -m "feat(radar): Add AlertPanelController Livewire component"
```

---

## Task 9: Include Alert Panel in Graph View

**Files:**
- Modify: `resources/views/livewire/graph/force-graph.blade.php`

**Step 1: Add the alert panel component**

Add after the session-manager include:

```blade
{{-- Alert Panel --}}
@auth
    <livewire:graph.alert-panel-controller />
@endauth
```

**Step 2: Verify by manual testing**

Run: `php artisan serve` and navigate to the graph view as an authenticated user.
Expected: Alert panel appears in top-right corner

**Step 3: Commit**

```bash
git add resources/views/livewire/graph/force-graph.blade.php
git commit -m "feat(radar): Include AlertPanel in graph view"
```

---

## Task 10: Add Node Badges to ForceGraph.js

**Files:**
- Modify: `resources/js/components/ForceGraph.js`

**Step 1: Add alerts data property**

In the Alpine data object, add:

```javascript
alerts: [],
nodeAlerts: {}, // Map of nodeId -> alert array
```

**Step 2: Add alerts-updated listener in init()**

```javascript
this.$wire.on('alerts-updated', (data) => {
    this.alerts = data.alerts || [];
    this.updateNodeAlerts();
    this.updateGraph();
});
```

**Step 3: Add updateNodeAlerts method**

```javascript
updateNodeAlerts() {
    this.nodeAlerts = {};
    for (const alert of this.alerts) {
        const nodeId = alert.source_node_id;
        if (!this.nodeAlerts[nodeId]) {
            this.nodeAlerts[nodeId] = [];
        }
        this.nodeAlerts[nodeId].push(alert);
    }
},
```

**Step 4: Modify node rendering in updateGraph()**

In the node enter selection, add alert ring:

```javascript
// Alert ring (add before the main circle)
nodeEnter.append('circle')
    .attr('class', 'alert-ring')
    .attr('r', d => this.getNodeSize(d) + 4)
    .attr('fill', 'none')
    .attr('stroke', d => this.getAlertColor(d))
    .attr('stroke-width', 3)
    .attr('opacity', d => this.nodeAlerts[d.id] ? 0.8 : 0);
```

**Step 5: Add getAlertColor helper**

```javascript
getAlertColor(node) {
    const alerts = this.nodeAlerts[node.id] || [];
    if (alerts.some(a => a.severity === 'critical')) return '#ef4444'; // red
    if (alerts.some(a => a.severity === 'warning')) return '#f59e0b'; // amber
    if (alerts.some(a => a.severity === 'caution')) return '#f97316'; // orange
    return 'transparent';
},
```

**Step 6: Commit**

```bash
git add resources/js/components/ForceGraph.js
git commit -m "feat(radar): Add alert badges to graph nodes"
```

---

## Task 11: Add Dismiss Alert Tests

**Files:**
- Modify: `tests/Feature/Livewire/Graph/AlertPanelControllerTest.php`

**Step 1: Write additional tests**

```php
/** @test */
public function it_dismisses_alert_on_click(): void
{
    $user = User::factory()->create();
    $this->actingAs($user);

    $session = ResearchSession::factory()->create([
        'user_id' => $user->id,
        'last_activity_at' => now(),
        'alerts' => [
            ['id' => 'alert-1', 'type' => 'direct_contradiction', 'severity' => 'critical', 'message' => 'Test', 'dismissed' => false],
        ],
    ]);

    Livewire::test(AlertPanelController::class)
        ->assertSee('Test')
        ->call('dismissAlert', 'alert-1')
        ->assertDontSee('Test');

    $session->refresh();
    $this->assertTrue($session->alerts[0]['dismissed']);
}

/** @test */
public function it_triggers_full_scan_on_button_click(): void
{
    $user = User::factory()->create();
    $this->actingAs($user);

    ResearchSession::factory()->create([
        'user_id' => $user->id,
        'last_activity_at' => now(),
        'pinned_nodes' => [['id' => '123', 'type' => 'Decision']],
        'alerts' => [],
    ]);

    // This test verifies the method is callable
    Livewire::test(AlertPanelController::class)
        ->call('scanAllNodes');
}
```

**Step 2: Run tests**

Run: `php artisan test tests/Feature/Livewire/Graph/AlertPanelControllerTest.php`
Expected: PASS (4 tests)

**Step 3: Commit**

```bash
git add tests/Feature/Livewire/Graph/AlertPanelControllerTest.php
git commit -m "test(radar): Add dismiss and scan tests for AlertPanel"
```

---

## Task 12: Final Integration Test and Push

**Files:**
- Run all tests

**Step 1: Run full test suite for radar features**

```bash
php artisan test tests/Unit/Services/Graph/ContradictionRadarServiceTest.php tests/Feature/Graph/ContradictionRadarTest.php tests/Feature/Livewire/Graph/AlertPanelControllerTest.php
```

Expected: All tests pass

**Step 2: Run focused graph tests**

```bash
php artisan test --filter="Radar|Alert"
```

Expected: All radar/alert tests pass

**Step 3: Final commit and push**

```bash
git add -A
git commit -m "feat(radar): Complete Phase 3 Contradiction Radar implementation"
git push -u origin claude/graph-enhancement-data-integrity-XqqqL
```

---

## Success Criteria Checklist

- [ ] ContradictionRadarService detects direct contradictions
- [ ] ContradictionRadarService detects superseded law citations
- [ ] ContradictionRadarService detects outdated citations
- [ ] Pinning a node triggers automatic scan
- [ ] Alerts stored in session JSONB column
- [ ] AlertPanelController displays alerts
- [ ] User can dismiss alerts
- [ ] On-demand "Scan All Nodes" works
- [ ] Node badges show alert status on graph
- [ ] All unit tests pass
- [ ] All feature tests pass
- [ ] All Livewire tests pass

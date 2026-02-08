# Weakness Finder Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add "Weakness Finder" feature that surfaces actionable weaknesses in opponent's legal arguments by detecting overruled precedents, distinguished cases, and weak citation chains.

**Architecture:** Extend existing `ContradictionRadarService` with new alert types that leverage the `PrecedentLinker` relationships (OVERRULES, DISTINGUISHES, MODIFIES). Query Neo4j graph for citations that link to weakened precedents.

**Tech Stack:** Laravel, Neo4j (Cypher), Livewire, PHPUnit/Mockery

---

## Task 1: Add Weakness Alert Constants

**Files:**
- Modify: `app/Services/Graph/ContradictionRadarService.php:21-31`
- Test: `tests/Unit/Services/Graph/ContradictionRadarServiceTest.php`

**Step 1: Write the failing test**

Add to `tests/Unit/Services/Graph/ContradictionRadarServiceTest.php`:

```php
/** @test */
public function it_has_weakness_alert_type_constants(): void
{
    $this->assertEquals('overruled_precedent', ContradictionRadarService::ALERT_TYPE_OVERRULED_PRECEDENT);
    $this->assertEquals('distinguished_precedent', ContradictionRadarService::ALERT_TYPE_DISTINGUISHED_PRECEDENT);
    $this->assertEquals('weak_citation_chain', ContradictionRadarService::ALERT_TYPE_WEAK_CITATION_CHAIN);
}
```

**Step 2: Run test to verify it fails**

Run: `./scripts/run-focused-tests.sh ContradictionRadarServiceTest`
Expected: FAIL with "Undefined class constant"

**Step 3: Write minimal implementation**

Add to `app/Services/Graph/ContradictionRadarService.php` after line 25:

```php
public const ALERT_TYPE_OVERRULED_PRECEDENT = 'overruled_precedent';

public const ALERT_TYPE_DISTINGUISHED_PRECEDENT = 'distinguished_precedent';

public const ALERT_TYPE_WEAK_CITATION_CHAIN = 'weak_citation_chain';
```

**Step 4: Run test to verify it passes**

Run: `./scripts/run-focused-tests.sh ContradictionRadarServiceTest`
Expected: PASS

**Step 5: Commit**

```bash
git add app/Services/Graph/ContradictionRadarService.php tests/Unit/Services/Graph/ContradictionRadarServiceTest.php
git commit -m "feat(weakness-finder): add alert type constants for weakness detection"
```

---

## Task 2: Implement findOverruledPrecedentCitations

**Files:**
- Modify: `app/Services/Graph/ContradictionRadarService.php`
- Test: `tests/Unit/Services/Graph/ContradictionRadarServiceTest.php`

**Step 1: Write the failing test**

Add to `tests/Unit/Services/Graph/ContradictionRadarServiceTest.php`:

```php
// ========================================
// Overruled Precedent Tests
// ========================================

/** @test */
public function it_finds_overruled_precedent_citations(): void
{
    $mockGraphDb = Mockery::mock(GraphDatabaseService::class);
    $mockGraphDb->shouldReceive('runQuery')
        ->once()
        ->with(Mockery::type('string'), ['nodeId' => '123'])
        ->andReturn([
            [
                'cited_decision_id' => '456',
                'cited_case_number' => 'Rev-123/2018',
                'overruling_decision_id' => '789',
                'overruling_case_number' => 'Rev-456/2022',
                'overruling_reason' => 'Outdated interpretation',
            ],
        ]);

    $service = new ContradictionRadarService($mockGraphDb);

    $alerts = $service->findOverruledPrecedentCitations('123');

    $this->assertCount(1, $alerts);
    $this->assertEquals(ContradictionRadarService::ALERT_TYPE_OVERRULED_PRECEDENT, $alerts[0]['type']);
    $this->assertEquals(ContradictionRadarService::SEVERITY_CRITICAL, $alerts[0]['severity']);
    $this->assertEquals('123', $alerts[0]['source_node_id']);
    $this->assertEquals('456', $alerts[0]['related_node_id']);
    $this->assertStringContainsString('Rev-123/2018', $alerts[0]['message']);
    $this->assertStringContainsString('overruled', strtolower($alerts[0]['message']));
    $this->assertFalse($alerts[0]['dismissed']);
}

/** @test */
public function it_returns_empty_array_when_no_overruled_precedents(): void
{
    $mockGraphDb = Mockery::mock(GraphDatabaseService::class);
    $mockGraphDb->shouldReceive('runQuery')
        ->once()
        ->andReturn([]);

    $service = new ContradictionRadarService($mockGraphDb);

    $alerts = $service->findOverruledPrecedentCitations('123');

    $this->assertEmpty($alerts);
}

/** @test */
public function it_throws_exception_for_empty_node_id_in_overruled_check(): void
{
    $mockGraphDb = Mockery::mock(GraphDatabaseService::class);
    $service = new ContradictionRadarService($mockGraphDb);

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Node ID cannot be empty');

    $service->findOverruledPrecedentCitations('');
}
```

**Step 2: Run test to verify it fails**

Run: `./scripts/run-focused-tests.sh ContradictionRadarServiceTest`
Expected: FAIL with "Call to undefined method"

**Step 3: Write minimal implementation**

Add method to `app/Services/Graph/ContradictionRadarService.php`:

```php
/**
 * Find citations to precedents that have been overruled.
 *
 * Queries Neo4j for decisions that cite other decisions which have been
 * explicitly overruled by a later decision. Returns critical-level alerts
 * as this is a significant weakness in the legal argument.
 *
 * @param  string  $nodeId  The decision node ID to check
 * @return array<int, array{
 *     id: string,
 *     type: string,
 *     severity: string,
 *     source_node_id: string,
 *     related_node_id: string,
 *     message: string,
 *     dismissed: bool,
 *     created_at: string
 * }> Array of alert objects
 *
 * @throws InvalidArgumentException If nodeId is empty
 * @throws \RuntimeException If database query fails
 */
public function findOverruledPrecedentCitations(string $nodeId): array
{
    if (empty(trim($nodeId))) {
        throw new InvalidArgumentException('Node ID cannot be empty');
    }

    $cypher = <<<'CYPHER'
        MATCH (decision:Decision)-[:CITES]->(cited:Decision)<-[:OVERRULES]-(overruling:Decision)
        WHERE id(decision) = $nodeId OR decision.id = $nodeId
        RETURN
            cited.id AS cited_decision_id,
            cited.case_number AS cited_case_number,
            overruling.id AS overruling_decision_id,
            overruling.case_number AS overruling_case_number,
            overruling.reason AS overruling_reason
    CYPHER;

    try {
        $results = $this->graphDb->runQuery($cypher, ['nodeId' => $nodeId]);
    } catch (\Exception $e) {
        throw new \RuntimeException(
            "Failed to query overruled precedents for node {$nodeId}: {$e->getMessage()}",
            0,
            $e
        );
    }

    return array_map(fn ($row) => [
        'id' => $this->generateAlertId($nodeId, self::ALERT_TYPE_OVERRULED_PRECEDENT, $row['cited_decision_id']),
        'type' => self::ALERT_TYPE_OVERRULED_PRECEDENT,
        'severity' => self::SEVERITY_CRITICAL,
        'source_node_id' => $nodeId,
        'related_node_id' => $row['cited_decision_id'],
        'message' => "⚠️ WEAKNESS: Cites {$row['cited_case_number']} which was overruled by {$row['overruling_case_number']}",
        'dismissed' => false,
        'created_at' => now()->toISOString(),
        'metadata' => [
            'overruling_decision_id' => $row['overruling_decision_id'],
            'overruling_case_number' => $row['overruling_case_number'],
            'reason' => $row['overruling_reason'] ?? null,
        ],
    ], $results);
}
```

**Step 4: Run test to verify it passes**

Run: `./scripts/run-focused-tests.sh ContradictionRadarServiceTest`
Expected: PASS

**Step 5: Commit**

```bash
git add app/Services/Graph/ContradictionRadarService.php tests/Unit/Services/Graph/ContradictionRadarServiceTest.php
git commit -m "feat(weakness-finder): add findOverruledPrecedentCitations method"
```

---

## Task 3: Implement findDistinguishedPrecedentCitations

**Files:**
- Modify: `app/Services/Graph/ContradictionRadarService.php`
- Test: `tests/Unit/Services/Graph/ContradictionRadarServiceTest.php`

**Step 1: Write the failing test**

Add to `tests/Unit/Services/Graph/ContradictionRadarServiceTest.php`:

```php
// ========================================
// Distinguished Precedent Tests
// ========================================

/** @test */
public function it_finds_distinguished_precedent_citations(): void
{
    $mockGraphDb = Mockery::mock(GraphDatabaseService::class);
    $mockGraphDb->shouldReceive('runQuery')
        ->once()
        ->with(Mockery::type('string'), ['nodeId' => '123'])
        ->andReturn([
            [
                'cited_decision_id' => '456',
                'cited_case_number' => 'Pž-789/2019',
                'distinguishing_decision_id' => '999',
                'distinguishing_case_number' => 'Rev-100/2023',
                'distinguishing_count' => 3,
            ],
        ]);

    $service = new ContradictionRadarService($mockGraphDb);

    $alerts = $service->findDistinguishedPrecedentCitations('123');

    $this->assertCount(1, $alerts);
    $this->assertEquals(ContradictionRadarService::ALERT_TYPE_DISTINGUISHED_PRECEDENT, $alerts[0]['type']);
    $this->assertEquals(ContradictionRadarService::SEVERITY_WARNING, $alerts[0]['severity']);
    $this->assertEquals('123', $alerts[0]['source_node_id']);
    $this->assertEquals('456', $alerts[0]['related_node_id']);
    $this->assertStringContainsString('Pž-789/2019', $alerts[0]['message']);
    $this->assertStringContainsString('distinguished', strtolower($alerts[0]['message']));
}

/** @test */
public function it_returns_empty_array_when_no_distinguished_precedents(): void
{
    $mockGraphDb = Mockery::mock(GraphDatabaseService::class);
    $mockGraphDb->shouldReceive('runQuery')
        ->once()
        ->andReturn([]);

    $service = new ContradictionRadarService($mockGraphDb);

    $alerts = $service->findDistinguishedPrecedentCitations('123');

    $this->assertEmpty($alerts);
}
```

**Step 2: Run test to verify it fails**

Run: `./scripts/run-focused-tests.sh ContradictionRadarServiceTest`
Expected: FAIL with "Call to undefined method"

**Step 3: Write minimal implementation**

Add method to `app/Services/Graph/ContradictionRadarService.php`:

```php
/**
 * Find citations to precedents that have been distinguished.
 *
 * Queries Neo4j for decisions that cite other decisions which have been
 * distinguished by later courts. This indicates the precedent's applicability
 * has been narrowed or questioned.
 *
 * @param  string  $nodeId  The decision node ID to check
 * @return array<int, array> Array of alert objects
 *
 * @throws InvalidArgumentException If nodeId is empty
 * @throws \RuntimeException If database query fails
 */
public function findDistinguishedPrecedentCitations(string $nodeId): array
{
    if (empty(trim($nodeId))) {
        throw new InvalidArgumentException('Node ID cannot be empty');
    }

    $cypher = <<<'CYPHER'
        MATCH (decision:Decision)-[:CITES]->(cited:Decision)<-[:DISTINGUISHES]-(distinguishing:Decision)
        WHERE id(decision) = $nodeId OR decision.id = $nodeId
        WITH cited, distinguishing, decision
        ORDER BY distinguishing.decision_date DESC
        WITH cited, collect(distinguishing)[0] AS latest_distinguishing, count(distinguishing) AS distinguish_count
        RETURN
            cited.id AS cited_decision_id,
            cited.case_number AS cited_case_number,
            latest_distinguishing.id AS distinguishing_decision_id,
            latest_distinguishing.case_number AS distinguishing_case_number,
            distinguish_count AS distinguishing_count
    CYPHER;

    try {
        $results = $this->graphDb->runQuery($cypher, ['nodeId' => $nodeId]);
    } catch (\Exception $e) {
        throw new \RuntimeException(
            "Failed to query distinguished precedents for node {$nodeId}: {$e->getMessage()}",
            0,
            $e
        );
    }

    return array_map(fn ($row) => [
        'id' => $this->generateAlertId($nodeId, self::ALERT_TYPE_DISTINGUISHED_PRECEDENT, $row['cited_decision_id']),
        'type' => self::ALERT_TYPE_DISTINGUISHED_PRECEDENT,
        'severity' => self::SEVERITY_WARNING,
        'source_node_id' => $nodeId,
        'related_node_id' => $row['cited_decision_id'],
        'message' => "⚡ POTENTIAL WEAKNESS: Cites {$row['cited_case_number']} which has been distinguished {$row['distinguishing_count']} time(s), most recently by {$row['distinguishing_case_number']}",
        'dismissed' => false,
        'created_at' => now()->toISOString(),
        'metadata' => [
            'distinguishing_decision_id' => $row['distinguishing_decision_id'],
            'distinguishing_count' => $row['distinguishing_count'],
        ],
    ], $results);
}
```

**Step 4: Run test to verify it passes**

Run: `./scripts/run-focused-tests.sh ContradictionRadarServiceTest`
Expected: PASS

**Step 5: Commit**

```bash
git add app/Services/Graph/ContradictionRadarService.php tests/Unit/Services/Graph/ContradictionRadarServiceTest.php
git commit -m "feat(weakness-finder): add findDistinguishedPrecedentCitations method"
```

---

## Task 4: Implement findWeakCitationChains

**Files:**
- Modify: `app/Services/Graph/ContradictionRadarService.php`
- Test: `tests/Unit/Services/Graph/ContradictionRadarServiceTest.php`

**Step 1: Write the failing test**

Add to `tests/Unit/Services/Graph/ContradictionRadarServiceTest.php`:

```php
// ========================================
// Weak Citation Chain Tests
// ========================================

/** @test */
public function it_finds_weak_citation_chains(): void
{
    $mockGraphDb = Mockery::mock(GraphDatabaseService::class);
    $mockGraphDb->shouldReceive('runQuery')
        ->once()
        ->with(Mockery::type('string'), ['nodeId' => '123'])
        ->andReturn([
            [
                'cited_decision_id' => '456',
                'cited_case_number' => 'Gž-111/2017',
                'modification_count' => 2,
                'latest_modifier_id' => '888',
                'latest_modifier_case_number' => 'Rev-222/2021',
            ],
        ]);

    $service = new ContradictionRadarService($mockGraphDb);

    $alerts = $service->findWeakCitationChains('123');

    $this->assertCount(1, $alerts);
    $this->assertEquals(ContradictionRadarService::ALERT_TYPE_WEAK_CITATION_CHAIN, $alerts[0]['type']);
    $this->assertEquals(ContradictionRadarService::SEVERITY_CAUTION, $alerts[0]['severity']);
    $this->assertEquals('123', $alerts[0]['source_node_id']);
    $this->assertStringContainsString('Gž-111/2017', $alerts[0]['message']);
    $this->assertStringContainsString('modified', strtolower($alerts[0]['message']));
}

/** @test */
public function it_returns_empty_array_when_no_weak_chains(): void
{
    $mockGraphDb = Mockery::mock(GraphDatabaseService::class);
    $mockGraphDb->shouldReceive('runQuery')
        ->once()
        ->andReturn([]);

    $service = new ContradictionRadarService($mockGraphDb);

    $alerts = $service->findWeakCitationChains('123');

    $this->assertEmpty($alerts);
}
```

**Step 2: Run test to verify it fails**

Run: `./scripts/run-focused-tests.sh ContradictionRadarServiceTest`
Expected: FAIL with "Call to undefined method"

**Step 3: Write minimal implementation**

Add method to `app/Services/Graph/ContradictionRadarService.php`:

```php
/**
 * Find citations to precedents that have been modified (weakened).
 *
 * Queries Neo4j for decisions that cite other decisions which have been
 * modified by appellate courts. Modifications indicate partial disagreement
 * with the original ruling.
 *
 * @param  string  $nodeId  The decision node ID to check
 * @return array<int, array> Array of alert objects
 *
 * @throws InvalidArgumentException If nodeId is empty
 * @throws \RuntimeException If database query fails
 */
public function findWeakCitationChains(string $nodeId): array
{
    if (empty(trim($nodeId))) {
        throw new InvalidArgumentException('Node ID cannot be empty');
    }

    $cypher = <<<'CYPHER'
        MATCH (decision:Decision)-[:CITES]->(cited:Decision)<-[:MODIFIES]-(modifier:Decision)
        WHERE id(decision) = $nodeId OR decision.id = $nodeId
        WITH cited, modifier, decision
        ORDER BY modifier.decision_date DESC
        WITH cited, collect(modifier)[0] AS latest_modifier, count(modifier) AS mod_count
        RETURN
            cited.id AS cited_decision_id,
            cited.case_number AS cited_case_number,
            mod_count AS modification_count,
            latest_modifier.id AS latest_modifier_id,
            latest_modifier.case_number AS latest_modifier_case_number
    CYPHER;

    try {
        $results = $this->graphDb->runQuery($cypher, ['nodeId' => $nodeId]);
    } catch (\Exception $e) {
        throw new \RuntimeException(
            "Failed to query weak citation chains for node {$nodeId}: {$e->getMessage()}",
            0,
            $e
        );
    }

    return array_map(fn ($row) => [
        'id' => $this->generateAlertId($nodeId, self::ALERT_TYPE_WEAK_CITATION_CHAIN, $row['cited_decision_id']),
        'type' => self::ALERT_TYPE_WEAK_CITATION_CHAIN,
        'severity' => self::SEVERITY_CAUTION,
        'source_node_id' => $nodeId,
        'related_node_id' => $row['cited_decision_id'],
        'message' => "📉 WEAKENED: Cites {$row['cited_case_number']} which was modified {$row['modification_count']} time(s), last by {$row['latest_modifier_case_number']}",
        'dismissed' => false,
        'created_at' => now()->toISOString(),
        'metadata' => [
            'modification_count' => $row['modification_count'],
            'latest_modifier_id' => $row['latest_modifier_id'],
        ],
    ], $results);
}
```

**Step 4: Run test to verify it passes**

Run: `./scripts/run-focused-tests.sh ContradictionRadarServiceTest`
Expected: PASS

**Step 5: Commit**

```bash
git add app/Services/Graph/ContradictionRadarService.php tests/Unit/Services/Graph/ContradictionRadarServiceTest.php
git commit -m "feat(weakness-finder): add findWeakCitationChains method"
```

---

## Task 5: Update scanNode to Include Weakness Checks

**Files:**
- Modify: `app/Services/Graph/ContradictionRadarService.php:60-86`
- Test: `tests/Unit/Services/Graph/ContradictionRadarServiceTest.php`

**Step 1: Write the failing test**

Add to `tests/Unit/Services/Graph/ContradictionRadarServiceTest.php`:

```php
/** @test */
public function it_scans_node_and_includes_weakness_alerts(): void
{
    $mockGraphDb = Mockery::mock(GraphDatabaseService::class);

    // Mock six queries for Decision node (3 existing + 3 weakness)
    $mockGraphDb->shouldReceive('runQuery')
        ->times(6)
        ->andReturn(
            [], // contradictions
            [], // superseded
            [], // outdated
            [['cited_decision_id' => '456', 'cited_case_number' => 'Rev-1/2020', 'overruling_decision_id' => '789', 'overruling_case_number' => 'Rev-2/2023', 'overruling_reason' => null]], // overruled
            [], // distinguished
            []  // weak chains
        );

    $service = new ContradictionRadarService($mockGraphDb);

    $alerts = $service->scanNode('123', 'Decision');

    $this->assertCount(1, $alerts);
    $this->assertEquals(ContradictionRadarService::ALERT_TYPE_OVERRULED_PRECEDENT, $alerts[0]['type']);
}
```

**Step 2: Run test to verify it fails**

Run: `./scripts/run-focused-tests.sh ContradictionRadarServiceTest`
Expected: FAIL (scanNode doesn't call weakness methods yet)

**Step 3: Write minimal implementation**

Update `scanNode` method in `app/Services/Graph/ContradictionRadarService.php`:

```php
public function scanNode(string $nodeId, string $nodeType): array
{
    if (empty(trim($nodeId))) {
        throw new InvalidArgumentException('Node ID cannot be empty');
    }

    if (empty(trim($nodeType))) {
        throw new InvalidArgumentException('Node type cannot be empty');
    }

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

    // 4. Overruled precedents (Decision nodes only) - WEAKNESS FINDER
    if ($nodeType === 'Decision') {
        $alerts = array_merge($alerts, $this->findOverruledPrecedentCitations($nodeId));
    }

    // 5. Distinguished precedents (Decision nodes only) - WEAKNESS FINDER
    if ($nodeType === 'Decision') {
        $alerts = array_merge($alerts, $this->findDistinguishedPrecedentCitations($nodeId));
    }

    // 6. Weak citation chains (Decision nodes only) - WEAKNESS FINDER
    if ($nodeType === 'Decision') {
        $alerts = array_merge($alerts, $this->findWeakCitationChains($nodeId));
    }

    return $alerts;
}
```

**Step 4: Run test to verify it passes**

Run: `./scripts/run-focused-tests.sh ContradictionRadarServiceTest`
Expected: PASS

**Step 5: Commit**

```bash
git add app/Services/Graph/ContradictionRadarService.php tests/Unit/Services/Graph/ContradictionRadarServiceTest.php
git commit -m "feat(weakness-finder): integrate weakness checks into scanNode"
```

---

## Task 6: Update Alert Panel UI for Weakness Types

**Files:**
- Modify: `resources/views/livewire/graph/alert-panel.blade.php`
- Test: Manual browser test

**Step 1: Update the Blade template**

Replace badge section in `resources/views/livewire/graph/alert-panel.blade.php`:

```blade
<!-- Severity badge -->
<div class="flex items-center gap-2 mb-1">
    @switch($alert['type'])
        @case('overruled_precedent')
            <span class="px-2 py-0.5 text-xs font-bold bg-red-100 text-red-800 rounded">🔴 OVERRULED</span>
            @break
        @case('distinguished_precedent')
            <span class="px-2 py-0.5 text-xs font-bold bg-yellow-100 text-yellow-800 rounded">⚡ DISTINGUISHED</span>
            @break
        @case('weak_citation_chain')
            <span class="px-2 py-0.5 text-xs font-bold bg-orange-100 text-orange-800 rounded">📉 WEAKENED</span>
            @break
        @case('direct_contradiction')
            <span class="px-2 py-0.5 text-xs font-bold bg-red-100 text-red-800 rounded">🔴 CONTRADICTION</span>
            @break
        @case('superseded_law')
            <span class="px-2 py-0.5 text-xs font-bold bg-orange-100 text-orange-800 rounded">🟠 SUPERSEDED</span>
            @break
        @case('outdated_citation')
            <span class="px-2 py-0.5 text-xs font-bold bg-orange-100 text-orange-800 rounded">🟠 OUTDATED</span>
            @break
        @default
            @if($alert['severity'] === 'critical')
                <span class="px-2 py-0.5 text-xs font-bold bg-red-100 text-red-800 rounded">🔴 CRITICAL</span>
            @elseif($alert['severity'] === 'warning')
                <span class="px-2 py-0.5 text-xs font-bold bg-yellow-100 text-yellow-800 rounded">🟡 WARNING</span>
            @else
                <span class="px-2 py-0.5 text-xs font-bold bg-orange-100 text-orange-800 rounded">🟠 CAUTION</span>
            @endif
    @endswitch
</div>
```

**Step 2: Update header to show weakness category**

Update header section:

```blade
<!-- Header -->
<div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
    <h3 class="font-semibold text-gray-900 dark:text-white">
        🎯 Weakness Finder ({{ count($alerts) }})
    </h3>
</div>
```

**Step 3: Commit**

```bash
git add resources/views/livewire/graph/alert-panel.blade.php
git commit -m "feat(weakness-finder): update alert panel UI with weakness-specific badges"
```

---

## Task 7: Add Feature Test for Weakness Finder Integration

**Files:**
- Create: `tests/Feature/Services/Graph/WeaknessFinderTest.php`

**Step 1: Write the integration test**

Create `tests/Feature/Services/Graph/WeaknessFinderTest.php`:

```php
<?php

namespace Tests\Feature\Services\Graph;

use App\Services\Graph\ContradictionRadarService;
use App\Services\GraphDatabaseService;
use Mockery;
use Tests\TestCase;

/**
 * Feature tests for Weakness Finder functionality
 *
 * Tests the integration of weakness detection into the ContradictionRadarService.
 */
class WeaknessFinderTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_detects_all_weakness_types_in_single_scan(): void
    {
        $mockGraphDb = Mockery::mock(GraphDatabaseService::class);

        // Set up comprehensive mock responses
        $mockGraphDb->shouldReceive('runQuery')
            ->times(6)
            ->andReturn(
                [['node_id' => '999', 'case_number' => 'VSRH-999', 'title' => 'Contradiction']], // contradictions
                [], // superseded
                [], // outdated
                [['cited_decision_id' => '100', 'cited_case_number' => 'Rev-100/2018', 'overruling_decision_id' => '200', 'overruling_case_number' => 'Rev-200/2022', 'overruling_reason' => 'Bad law']], // overruled
                [['cited_decision_id' => '300', 'cited_case_number' => 'Pž-300/2019', 'distinguishing_decision_id' => '400', 'distinguishing_case_number' => 'Rev-400/2023', 'distinguishing_count' => 2]], // distinguished
                [['cited_decision_id' => '500', 'cited_case_number' => 'Gž-500/2017', 'modification_count' => 1, 'latest_modifier_id' => '600', 'latest_modifier_case_number' => 'Rev-600/2021']] // weak chains
            );

        $service = new ContradictionRadarService($mockGraphDb);
        $alerts = $service->scanNode('test-decision-123', 'Decision');

        // Should have 4 alerts total
        $this->assertCount(4, $alerts);

        // Verify alert types
        $alertTypes = array_column($alerts, 'type');
        $this->assertContains(ContradictionRadarService::ALERT_TYPE_DIRECT_CONTRADICTION, $alertTypes);
        $this->assertContains(ContradictionRadarService::ALERT_TYPE_OVERRULED_PRECEDENT, $alertTypes);
        $this->assertContains(ContradictionRadarService::ALERT_TYPE_DISTINGUISHED_PRECEDENT, $alertTypes);
        $this->assertContains(ContradictionRadarService::ALERT_TYPE_WEAK_CITATION_CHAIN, $alertTypes);
    }

    /** @test */
    public function weakness_alerts_have_actionable_messages(): void
    {
        $mockGraphDb = Mockery::mock(GraphDatabaseService::class);
        $mockGraphDb->shouldReceive('runQuery')
            ->once()
            ->andReturn([
                [
                    'cited_decision_id' => '456',
                    'cited_case_number' => 'Rev-123/2018',
                    'overruling_decision_id' => '789',
                    'overruling_case_number' => 'Rev-456/2022',
                    'overruling_reason' => null,
                ],
            ]);

        $service = new ContradictionRadarService($mockGraphDb);
        $alerts = $service->findOverruledPrecedentCitations('test-123');

        $this->assertCount(1, $alerts);

        // Message should be actionable - tells user WHAT is wrong and WHY
        $message = $alerts[0]['message'];
        $this->assertStringContainsString('WEAKNESS', $message);
        $this->assertStringContainsString('Rev-123/2018', $message);
        $this->assertStringContainsString('overruled', strtolower($message));
        $this->assertStringContainsString('Rev-456/2022', $message);
    }
}
```

**Step 2: Run test**

Run: `./scripts/run-focused-tests.sh WeaknessFinderTest`
Expected: PASS

**Step 3: Commit**

```bash
git add tests/Feature/Services/Graph/WeaknessFinderTest.php
git commit -m "test(weakness-finder): add integration tests"
```

---

## Task 8: Final Verification and Documentation

**Step 1: Run all Contradiction Radar tests**

```bash
./scripts/run-focused-tests.sh ContradictionRadarServiceTest
./scripts/run-focused-tests.sh WeaknessFinderTest
```

Expected: All tests PASS

**Step 2: Run related test suites**

```bash
php artisan test tests/Unit/Services/Graph/
php artisan test tests/Feature/Services/Graph/
```

**Step 3: Document the feature**

Add section to relevant documentation about the new Weakness Finder feature:

- What alert types exist
- How they integrate with the UI
- What Cypher queries power them

**Step 4: Final commit**

```bash
git add -A
git commit -m "docs(weakness-finder): complete feature implementation

Adds Weakness Finder feature to ContradictionRadarService:
- ALERT_TYPE_OVERRULED_PRECEDENT: Cites precedent that was overruled
- ALERT_TYPE_DISTINGUISHED_PRECEDENT: Cites precedent that was distinguished
- ALERT_TYPE_WEAK_CITATION_CHAIN: Cites precedent that was modified

Integrated into scanNode() for automatic detection.
Updated alert panel UI with weakness-specific badges."
```

---

*Generated: 2026-01-12*

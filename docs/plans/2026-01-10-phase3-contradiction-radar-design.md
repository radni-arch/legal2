# Phase 3: Contradiction Radar - Design Document

> **Status:** Approved
> **Date:** 2026-01-10
> **Dependencies:** Phase 1 (Enhanced Graph Explorer), Phase 2 (Research Session Tracking)

---

## Overview

Real-time alert system that warns lawyers about contradictions, superseded laws, and outdated citations as they research. No more discovering opposing precedents after building a case.

**Deliverable:** Proactive alerts integrated into the graph explorer UI.

---

## Design Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| **Triggering** | Proactive on pin + On-demand button | Immediate feedback for key items, plus manual comprehensive scans |
| **Alert Types** | Graph-based only | Fast queries, no LLM costs, catches critical issues |
| **UI** | Node badges + floating panel | Visual context on graph + comprehensive list |
| **Storage** | Existing `alerts` JSONB column | No new migration, keeps context tied to session |

---

## Alert Types (Phase 3 Scope)

| Alert | Severity | Detection Method |
|-------|----------|------------------|
| **Direct Contradiction** | 🔴 Critical | CONTRADICTS relationship in Neo4j |
| **Superseded Law** | 🟠 Caution | SUPERSEDES relationship in Neo4j |
| **Outdated Citation** | 🟠 Caution | decision_date > law.valid_until |

**Future (Phase 3.1):**
- Opposing Interpretation (LLM-based)
- Weakening Trend (aggregate analysis)

---

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│  UI Layer                                                    │
│  ├── AlertPanelController (Livewire component)              │
│  │   └── Lists all active alerts with severity/dismiss      │
│  └── Node Badges (Alpine.js in ForceGraph)                  │
│      └── Visual indicators on affected nodes                │
├─────────────────────────────────────────────────────────────┤
│  Service Layer                                               │
│  └── ContradictionRadarService (NEW)                        │
│      ├── scanNode(nodeId, nodeType) → triggers on pin       │
│      ├── scanSession(session) → on-demand full scan         │
│      ├── dismissAlert(session, alertId)                     │
│      └── Uses existing services:                            │
│          ├── TemporalReasoningService (superseded/outdated) │
│          └── Neo4j queries (CONTRADICTS relationships)      │
├─────────────────────────────────────────────────────────────┤
│  Data Layer                                                  │
│  └── research_sessions.alerts (JSONB) - stores alerts       │
└─────────────────────────────────────────────────────────────┘
```

---

## ContradictionRadarService Interface

```php
class ContradictionRadarService
{
    public function __construct(
        protected TemporalReasoningService $temporalService,
        protected Neo4jClient $neo4j
    ) {}

    /**
     * Scan a single node for alerts (triggered on pin)
     */
    public function scanNode(string $nodeId, string $nodeType): array
    {
        $alerts = [];

        // 1. Direct contradictions (CONTRADICTS relationship)
        $alerts = array_merge($alerts, $this->findDirectContradictions($nodeId));

        // 2. Superseded laws (SUPERSEDES relationship)
        if ($nodeType === 'Decision') {
            $alerts = array_merge($alerts, $this->findSupersededLawCitations($nodeId));
        }

        // 3. Outdated citations (decision cites law after valid_until)
        if ($nodeType === 'Decision') {
            $alerts = array_merge($alerts, $this->findOutdatedCitations($nodeId));
        }

        return $alerts;
    }

    /**
     * Full scan of all session nodes (on-demand)
     */
    public function scanSession(ResearchSession $session): array;

    /**
     * Dismiss an alert (user acknowledged it)
     */
    public function dismissAlert(ResearchSession $session, string $alertId): void;
}
```

---

## Alert Data Structure

```php
[
    'id' => 'uuid',
    'type' => 'direct_contradiction|superseded_law|outdated_citation',
    'severity' => 'critical|warning|caution',
    'source_node_id' => '123',
    'related_node_id' => '456',
    'message' => 'Decision X contradicts pinned Decision Y',
    'dismissed' => false,
    'created_at' => '2026-01-10T...'
]
```

---

## Neo4j Queries

### Direct Contradictions
```cypher
MATCH (pinned)-[:CONTRADICTS]-(contradicting)
WHERE id(pinned) = $nodeId
RETURN contradicting.id AS node_id,
       contradicting.case_number AS case_number,
       contradicting.title AS title,
       'direct_contradiction' AS alert_type
```

### Superseded Law Citations
```cypher
MATCH (decision:Decision)-[:CITES]->(oldLaw:Law)-[:SUPERSEDED_BY]->(newLaw:Law)
WHERE id(decision) = $nodeId
RETURN oldLaw.id AS old_law_id,
       oldLaw.law_number AS old_law_number,
       newLaw.id AS new_law_id,
       newLaw.law_number AS new_law_number,
       'superseded_law' AS alert_type
```

### Outdated Citations
```cypher
MATCH (decision:Decision)-[:CITES]->(law:Law)
WHERE id(decision) = $nodeId
  AND law.valid_until IS NOT NULL
  AND decision.decision_date > law.valid_until
RETURN law.id AS law_id,
       law.law_number AS law_number,
       law.valid_until AS valid_until,
       decision.decision_date AS decision_date,
       'outdated_citation' AS alert_type
```

---

## UI Components

### Alert Panel (Livewire)

```
┌─────────────────────────────────────┐
│ ⚠️ Research Alerts (3)        [✕]  │
├─────────────────────────────────────┤
│ 🔴 CRITICAL                         │
│ Decision VSRH-123 contradicts       │
│ your pinned Decision VSRH-456       │
│ [View] [Dismiss]                    │
├─────────────────────────────────────┤
│ 🟠 CAUTION                          │
│ Pinned decision cites ZKP Art. 9    │
│ (superseded by NN 152/21)           │
│ [View Current Law] [Dismiss]        │
├─────────────────────────────────────┤
│ 🟠 CAUTION                          │
│ Decision dated 2022 cites law       │
│ version valid until 2020            │
│ [View] [Dismiss]                    │
├─────────────────────────────────────┤
│ [🔍 Scan All Nodes]                 │
└─────────────────────────────────────┘
```

### Node Badges (ForceGraph.js)

- Red pulsing ring around nodes with critical alerts
- Orange ring for caution alerts
- Badge icon (⚠️) on affected nodes
- Click badge → opens alert detail

### Integration Points

- `ForceGraphController::pinNode()` → calls `ContradictionRadarService::scanNode()`
- New `AlertPanelController` Livewire component
- `ForceGraph.js` listens for `alerts-updated` event to render badges

---

## Testing Strategy

### Unit Tests (ContradictionRadarService)
```
tests/Unit/Services/Graph/ContradictionRadarServiceTest.php
- it_finds_direct_contradictions_for_node()
- it_finds_superseded_law_citations()
- it_finds_outdated_citations()
- it_returns_empty_array_when_no_alerts()
- it_combines_all_alert_types_in_scan()
- it_scans_all_session_nodes_on_demand()
- it_dismisses_alert_by_id()
- it_skips_already_dismissed_alerts()
```

### Feature Tests (Integration)
```
tests/Feature/Graph/ContradictionRadarTest.php
- it_triggers_scan_when_node_is_pinned()
- it_stores_alerts_in_session_jsonb()
- it_dispatches_alerts_updated_event()
- it_handles_on_demand_full_scan()
```

### Livewire Tests (AlertPanel)
```
tests/Feature/Livewire/Graph/AlertPanelControllerTest.php
- it_renders_alerts_for_current_session()
- it_dismisses_alert_on_click()
- it_triggers_full_scan_on_button_click()
- it_shows_empty_state_when_no_alerts()
```

---

## Implementation Tasks (High-Level)

1. Create `ContradictionRadarService` with Neo4j queries
2. Write unit tests for service
3. Integrate with `ForceGraphController::pinNode()`
4. Create `AlertPanelController` Livewire component
5. Create alert panel Blade partial
6. Add node badge rendering to `ForceGraph.js`
7. Add "Scan All Nodes" on-demand functionality
8. Write feature and Livewire tests
9. Integration testing and polish

---

## Success Criteria

- [ ] Pinning a node with contradictions shows immediate alert
- [ ] Superseded law citations detected and displayed
- [ ] Outdated citations detected and displayed
- [ ] Alerts persist in session and survive page refresh
- [ ] User can dismiss alerts
- [ ] On-demand scan covers all viewed/pinned nodes
- [ ] All tests pass

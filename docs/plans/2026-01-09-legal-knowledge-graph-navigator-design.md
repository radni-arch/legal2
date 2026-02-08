# Legal Knowledge Graph Navigator - Design Document

> **Status:** Approved
> **Date:** 2026-01-09
> **Author:** Claude (AI Assistant)
> **Stakeholders:** Croatian lawyers, legal researchers

---

## Executive Summary

Transform the existing Neo4j legal graph into an interactive research command center that helps lawyers find precedents faster, never miss contradictions, and build stronger arguments backed by precedent chains.

**Target Users:** Croatian lawyers/law firms, legal academics/researchers

**Core Pain Points Addressed:**
1. Finding relevant precedents takes too long
2. Missing critical contradictions or supporting cases
3. Hard to see the big picture of legal interconnections
4. Manual, time-consuming argument building

---

## Architecture Overview

```
┌─────────────────────────────────────────────────────┐
│  UI Layer (Livewire + Alpine.js)                    │
│  - Interactive Graph Canvas (D3.js force graph)    │
│  - Research Workspace (pinned nodes, notes)        │
│  - AI Assistant Panel (context-aware suggestions)  │
├─────────────────────────────────────────────────────┤
│  Intelligence Layer (New)                          │
│  - ResearchSessionService (tracks exploration)     │
│  - GapAnalysisService (finds missing connections)  │
│  - ArgumentPathService (builds precedent chains)   │
│  - ContradictionRadarService (real-time alerts)    │
├─────────────────────────────────────────────────────┤
│  Graph Layer (Existing - Enhanced)                 │
│  - Neo4j + existing 23 services                    │
│  - New: ResearchSession nodes (user trails)        │
│  - New: ArgumentChain relationships                │
└─────────────────────────────────────────────────────┘
```

---

## Feature 1: Smart Graph Explorer

### Description
Interactive visual interface that replaces traditional search-results-in-a-list with an explorable knowledge map.

### User Experience
1. **Start from any node** - Search for a decision, law, or concept → it appears as the central node
2. **One-click expansion** - Click any node → instantly see all connected nodes
3. **Relationship filtering** - Toggle which relationships to show (CITES, CONTRADICTS, SUPPORTS, etc.)
4. **Visual encoding:**
   - Node size = influence (citation count)
   - Edge color = relationship type (red=contradicts, green=supports, blue=cites)
   - Node glow = relevance to your research session
   - Faded nodes = superseded/outdated laws

### Technical Implementation

**Leverages existing:**
- `GraphVisualizationController` - Already has node/edge APIs
- `ForceGraphController` - D3.js force graph already built
- `getNodeWithConnections()` - Expansion query exists

**New additions:**
- `GraphExplorerService` - Coordinates filtering, layout optimization
- Relationship type toggles in UI
- Node metadata panel (shows full details on hover/click)
- "Pin to workspace" - Keep important nodes visible while exploring

### Key Cypher Query
```cypher
MATCH (n)-[r]-(connected)
WHERE id(n) = $nodeId
AND type(r) IN $relationshipTypes
RETURN n, r, connected
LIMIT 50
```

---

## Feature 2: Gap Analysis ("What Am I Missing?")

### Description
AI analyzes your research session and proactively suggests overlooked cases, contradictions, and connections.

### User Experience
1. Click "What Am I Missing?" button after exploring several nodes
2. AI analyzes your research trail
3. Returns prioritized suggestions:
   - "You've explored 5 decisions on proportionality, but missed **Decision X** which **contradicts** 3 of them"
   - "Decision Y cites the same law but reached **opposite conclusion**"
   - "You haven't explored the **2023 amendment** that affects all these decisions"

### Technical Implementation

**New service: `GapAnalysisService`**

```php
class GapAnalysisService
{
    public function analyzeSession(ResearchSession $session): array
    {
        // 1. Get all nodes user has viewed
        $viewedNodeIds = $session->getViewedNodes();

        // 2. Find "nearby" unvisited nodes (1-2 hops away)
        $candidates = $this->findUnvisitedNeighbors($viewedNodeIds);

        // 3. Score candidates by relevance:
        //    - Contradicts viewed node = HIGH
        //    - Cited by multiple viewed nodes = HIGH
        //    - Same keywords but different outcome = MEDIUM
        //    - Newer law supersedes cited law = CRITICAL

        // 4. Use LLM to generate natural language explanation
        return $this->rankAndExplain($candidates);
    }
}
```

### Key Cypher Query
```cypher
MATCH (viewed:Decision)-[:CONTRADICTS]-(missed:Decision)
WHERE viewed.id IN $viewedIds AND NOT missed.id IN $viewedIds
RETURN missed, count(*) as contradictionCount
ORDER BY contradictionCount DESC
LIMIT 10
```

---

## Feature 3: Argument Path Finder

### Description
Build legal arguments backed by precedent chains. Input your position → system finds the strongest path through the graph to support it.

### User Experience
1. Define your legal position: "My client's search was illegal under Article 9 ZKP"
2. System traverses graph to find precedent chains
3. Returns argument structure with strength score and counter-arguments

### Output Example
```
Your Position: Search was illegal under Article 9 ZKP

Supporting Chain (Strength: 87%):
├─ Supreme Court Decision VSRH-123/2021 (binding)
│  └─ "Article 9 requires warrant for digital searches"
│     ├─ Cited by: 12 subsequent decisions
│     └─ No contradictions found
├─ Constitutional Court Decision U-III-456/2020
│  └─ Reinforces privacy protection interpretation
└─ Law: ZKP Article 9 (current version, effective 2022)

⚠️ Warning: 2 decisions with opposing interpretation exist
```

### Technical Implementation

**New service: `ArgumentPathService`**

```php
class ArgumentPathService
{
    public function findSupportingPath(string $legalPosition): ArgumentPath
    {
        // 1. LLM extracts: legal concept, relevant articles, desired outcome
        $parsed = $this->parseLegalPosition($legalPosition);

        // 2. Find decisions with matching outcome + same legal basis
        $supportingDecisions = $this->findSupportingDecisions($parsed);

        // 3. Build citation chain (prefer: binding → widely cited → recent)
        $chain = $this->buildPrecedentChain($supportingDecisions);

        // 4. Calculate strength score
        $strength = $this->calculateStrength($chain);

        // 5. Find counter-arguments (opposing decisions)
        $counterArguments = $this->findContradictions($chain);

        return new ArgumentPath($chain, $strength, $counterArguments);
    }
}
```

### Strength Score Factors
| Factor | Impact |
|--------|--------|
| Binding precedent in chain | +30% |
| Supreme Court decisions | +20% |
| High citation count (>10) | +15% |
| Recent (<3 years) | +10% |
| No contradictions | +15% |
| Contradictions exist | -20% |

---

## Feature 4: Contradiction Radar

### Description
Proactive real-time warnings as you research. No more discovering opposing precedents after you've built your case.

### Alert Types
| Alert | Trigger | Severity |
|-------|---------|----------|
| **Direct Contradiction** | Pinned node has CONTRADICTS relationship | 🔴 Critical |
| **Opposing Interpretation** | Same law, different outcome | 🟡 Warning |
| **Superseded Law** | Citing law that has SUPERSEDES relationship | 🟠 Caution |
| **Outdated Citation** | Decision cites law before its effective_date | 🟠 Caution |
| **Weakening Trend** | Recent decisions contradict older precedent | 🟡 Warning |

### Technical Implementation

**New service: `ContradictionRadarService`**

```php
class ContradictionRadarService
{
    public function scanForAlerts(array $pinnedNodeIds, array $viewedNodeIds): array
    {
        $alerts = [];

        // 1. Check direct contradictions
        $alerts = array_merge($alerts, $this->findDirectContradictions($pinnedNodeIds));

        // 2. Check superseded laws
        $alerts = array_merge($alerts, $this->findSupersededLaws($viewedNodeIds));

        // 3. Check temporal violations
        $alerts = array_merge($alerts, $this->findTemporalViolations($viewedNodeIds));

        return $this->prioritizeAlerts($alerts);
    }
}
```

**Leverages existing:** `ContradictionDetectionService`, `TemporalReasoningService`

---

## Hybrid Features

### Strength Meter
Visual indicator showing argument solidity with breakdown of factors.

```
Your Argument Strength: ████████░░ 78%

Factors:
✅ Supreme Court precedent (binding)     +25%
✅ 8 supporting citations                +20%
✅ No direct contradictions              +15%
✅ Recent (2022)                         +10%
⚠️ 2 decisions with differing outcome   -12%
⚠️ Law amended since precedent          -5%
```

### Cases Like Yours
Show similar historical cases with outcomes after building an argument path.

```
Similar Cases (by facts + legal basis):

1. VSRH-456/2022 - 89% similar → Outcome: GRANTED
2. VSRH-123/2021 - 84% similar → Outcome: GRANTED
3. ŽS-789/2020  - 76% similar → Outcome: DENIED

Win rate for similar cases: 67% (8/12)
```

### Trend Indicator
Show if legal interpretation is gaining or losing favor over time.

```
Trend: Article 9 interpretation ↗️ STRENGTHENING

- 2020: 3 decisions, 33% supported
- 2021: 5 decisions, 60% supported
- 2022: 8 decisions, 75% supported
- 2023: 6 decisions, 83% supported
```

---

## Implementation Roadmap

### Phase 1: Enhanced Graph Explorer (2-3 weeks)
| Task | Effort | Dependencies |
|------|--------|--------------|
| Relationship type toggles in UI | S | Existing ForceGraph |
| Node metadata panel | S | Existing API |
| Pin nodes to workspace | M | New state management |
| Visual encoding | M | Existing data |

**Deliverable:** Lawyers can visually explore the graph with filtering.

### Phase 2: Research Session Tracking (1-2 weeks)
| Task | Effort | Dependencies |
|------|--------|--------------|
| ResearchSession model (PostgreSQL) | S | - |
| Track viewed/pinned nodes | S | Phase 1 |
| Session persistence | M | Auth system |

**Deliverable:** System remembers what you've looked at.

### Phase 3: Contradiction Radar (2 weeks)
| Task | Effort | Dependencies |
|------|--------|--------------|
| ContradictionRadarService | M | Phase 2 |
| Real-time alert UI | M | Phase 1 |
| Superseded law detection | S | Existing services |

**Deliverable:** No more missed contradictions.

### Phase 4: Gap Analysis AI (2-3 weeks)
| Task | Effort | Dependencies |
|------|--------|--------------|
| GapAnalysisService | L | Phase 2 |
| Candidate scoring algorithm | M | Graph queries |
| LLM explanation generation | M | OpenAI |
| UI panel for suggestions | S | Phase 1 |

**Deliverable:** AI-powered research assistant.

### Phase 5: Argument Path Finder (3-4 weeks)
| Task | Effort | Dependencies |
|------|--------|--------------|
| ArgumentPathService | L | Phases 2-4 |
| Strength calculation | M | Graph metrics |
| Counter-argument detection | M | Existing services |
| Argument visualization UI | L | Phase 1 |

**Deliverable:** Automated argument building.

### Phase 6: Hybrid Analytics (2 weeks)
| Task | Effort | Dependencies |
|------|--------|--------------|
| Strength meter component | S | Phase 5 |
| "Cases Like Yours" query | M | Existing SIMILAR_TO |
| Trend sparkline | M | Aggregate queries |

**Deliverable:** Predictive insights.

---

## Data Model Changes

### New PostgreSQL Table: research_sessions
```sql
CREATE TABLE research_sessions (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT REFERENCES users(id),
    name VARCHAR(255),
    viewed_nodes JSONB DEFAULT '[]',
    pinned_nodes JSONB DEFAULT '[]',
    alerts JSONB DEFAULT '[]',
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### New Neo4j Relationship: SUPPORTS
```cypher
(d1:Decision)-[:SUPPORTS {strength: 0.85, basis: 'same_interpretation'}]->(d2:Decision)
```

---

## Success Metrics

| Metric | Current | Target |
|--------|---------|--------|
| Time to find relevant precedent | ~30 min | <5 min |
| Missed contradictions per case | Unknown | 0 |
| Argument prep time | ~4 hours | <1 hour |
| User satisfaction (NPS) | N/A | >50 |

---

## Risks & Mitigations

| Risk | Impact | Mitigation |
|------|--------|------------|
| LLM hallucination in gap analysis | High | Always show source nodes, allow verification |
| Graph performance with large sessions | Medium | Limit expansion depth, use pagination |
| User adoption resistance | Medium | Gradual rollout, training sessions |
| Data quality affects recommendations | High | Data validation pipeline, confidence scores |

---

## Next Steps

1. **Create detailed implementation plan** using `/write-plan` command
2. **Set up git worktree** for isolated development
3. **Start with Phase 1** - Enhanced Graph Explorer (lowest risk, immediate value)

# Graph Enhancement Phase 2: Integration, Testing & Entity Expansion

**Branch:** `claude/graph-enhancement-data-integrity-XqqqL`
**Sprint Start:** 2026-01-07
**Predecessor:** `2026-01-07-graph-enhancement-consolidation-sprint.md` (Phases A & B completed)

---

## Executive Summary

This sprint completes the graph enhancement initiative by:
1. **Phases C-F**: Integration tests, data pipeline, UI, performance (from original plan)
2. **Phase G**: New entity extraction (9 additional entity types)

**Total Tasks:** 38 tasks across 5 phases
**Priority Order:** C → D → G → E → F

---

## Sprint Status Legend

| Status | Meaning |
|--------|---------|
| ⬜ | Not started |
| 🔄 | In progress |
| ✅ | Completed |
| ⏸️ | Blocked |

---

## Phase C: Integration Tests (Priority: HIGH)

**Goal:** Verify graph operations work with real Neo4j, not just mocks.

### Prerequisites
- Neo4j test container available via Docker
- Test database connection configured

### Tasks

| ID | Task | Effort | Status | Dependencies | Acceptance Criteria |
|----|------|--------|--------|--------------|---------------------|
| C.1 | Create Neo4j test container infrastructure | 3h | ⬜ | None | `tests/Integration/Graph/` directory with base test case using Testcontainers or Docker Compose |
| C.2 | Add Cypher syntax validation tests | 2h | ⬜ | C.1 | All Cypher queries in services execute without syntax errors |
| C.3 | Add constraint violation tests | 2h | ⬜ | C.1 | Tests verify unique constraint violations throw expected exceptions |
| C.4 | Add end-to-end sync tests (PostgreSQL → Neo4j) | 2h | ⬜ | C.1, C.2 | Test syncs CourtDecision from PostgreSQL to Neo4j and verifies node exists |
| C.5 | Add security tests (injection prevention) | 1h | ⬜ | C.1 | Tests verify special characters, quotes, and Cypher injection attempts are escaped |

**Phase C Files to Create:**
```
tests/Integration/Graph/
├── GraphIntegrationTestCase.php    # Base class with container setup
├── CypherSyntaxValidationTest.php  # C.2
├── ConstraintViolationTest.php     # C.3
├── EndToEndSyncTest.php            # C.4
└── CypherInjectionPreventionTest.php # C.5

docker-compose.testing.yml          # Neo4j container for tests
```

---

## Phase D: Data Pipeline (Priority: HIGH)

**Goal:** Enable batch processing, schema management, and operational visibility.

### Completed Tasks (from Phase 1)
- ✅ D.1: `graph:sync-enhanced` command created
- ✅ D.5: Sync metrics tracking implemented

### Remaining Tasks

| ID | Task | Effort | Status | Dependencies | Acceptance Criteria |
|----|------|--------|--------|--------------|---------------------|
| D.2 | Create `EnhancedGraphSyncJob` for background processing | 2h | ⬜ | None | Job dispatched from command, processes decisions in configurable batch sizes |
| D.3 | Add progress tracking to sync operations | 1h | ⬜ | D.2 | Job emits events for progress (BatchStarted, BatchCompleted, SyncFinished) |
| D.4 | Create `graph:schema-apply` command | 2h | ⬜ | None | Reads `GRAPH_SCHEMA.cypher`, applies constraints and indexes to Neo4j |

**Phase D Files to Create:**
```
app/Jobs/EnhancedGraphSyncJob.php
app/Events/Graph/
├── BatchStarted.php
├── BatchCompleted.php
└── SyncFinished.php
app/Console/Commands/GraphSchemaApplyCommand.php
```

---

## Phase E: UI Enablement (Priority: MEDIUM)

**Goal:** Display graph data in the frontend for users.

### Tasks

| ID | Task | Effort | Status | Dependencies | Acceptance Criteria |
|----|------|--------|--------|--------------|---------------------|
| E.1 | Add Livewire method to fetch judge data | 1h | ⬜ | None | `GraphViewer::getJudgeData($judgeId)` returns judge stats |
| E.2 | Add loading/empty states to Judge Panel | 1h | ⬜ | E.1 | Alpine.js loading spinner, "No data" state when empty |
| E.3 | Add Party Panel to GraphViewer | 2h | ⬜ | None | New panel showing plaintiff/defendant with outcome |
| E.4 | Add Precedent Chain visualization | 2h | ⬜ | None | Timeline/chain showing FOLLOWS/OVERRULES relationships |
| E.5 | Add tooltips for precedential value badges | 1h | ⬜ | None | Hover shows "Binding", "Persuasive", "Informational" explanations |

**Phase E Files to Modify/Create:**
```
app/Livewire/Graph/GraphViewer.php          # E.1
resources/views/livewire/graph/
├── partials/judge-panel.blade.php          # E.2
├── partials/party-panel.blade.php          # E.3 (new)
├── partials/precedent-chain.blade.php      # E.4 (new)
└── partials/precedential-badge.blade.php   # E.5
```

---

## Phase F: Performance & Resilience (Priority: MEDIUM)

**Goal:** Production-ready performance and fault tolerance.

### Tasks

| ID | Task | Effort | Status | Dependencies | Acceptance Criteria |
|----|------|--------|--------|--------------|---------------------|
| F.1 | Add connection pooling configuration | 2h | ⬜ | None | `config/graph.php` has pool settings, `GraphDatabaseService` uses pool |
| F.2 | Add circuit breaker for graph operations | 2h | ⬜ | None | After N failures, circuit opens and fails fast for timeout period |
| F.3 | Create performance benchmark suite | 2h | ⬜ | C.1 | Benchmarks for 100, 1K, 10K node syncs with timing assertions |
| F.4 | Add query explain/profile logging | 1h | ⬜ | None | Slow queries (>100ms) logged with `EXPLAIN` output |

**Phase F Files to Create/Modify:**
```
app/Services/Graph/CircuitBreaker.php       # F.2
config/graph.php                            # F.1 (pool config)
tests/Performance/Graph/
├── SyncBenchmarkTest.php                   # F.3
└── QueryPerformanceTest.php                # F.3
```

---

## Phase G: Entity Expansion (Priority: HIGH)

**Goal:** Extract 9 additional entity types to enrich the knowledge graph.

### New Entities Overview

| Entity | Node Label | Source | Extraction Method |
|--------|------------|--------|-------------------|
| Legal Topic | `LegalTopic` | Decision content | NLP classification |
| Article | `Article` | Law text | Regex + structure parsing |
| Legal Concept | `LegalConcept` | Decision/law content | Keyword + NLP |
| Lawyer | `Lawyer` | Decision metadata | Regex extraction |
| Verdict | `Verdict` | Decision outcome | Rule-based extraction |
| Legal Argument | `LegalArgument` | Decision reasoning | NLP segmentation |
| Evidence | `Evidence` | Decision facts | Pattern matching |
| Date Event | `DateEvent` | All documents | Date + context extraction |
| Legal Definition | `LegalDefinition` | Law text | Definition patterns |

### G.1: Legal Topic Extraction

| ID | Task | Effort | Status | Dependencies | Acceptance Criteria |
|----|------|--------|--------|--------------|---------------------|
| G.1.1 | Create `LegalTopicExtractor` service | 3h | ⬜ | None | Extracts topics from decision text using keyword matching |
| G.1.2 | Create `LegalTopicGraphSyncService` | 2h | ⬜ | G.1.1 | Syncs LegalTopic nodes with RELATES_TO relationships |
| G.1.3 | Add topic taxonomy seed data | 1h | ⬜ | None | Croatian legal topics: civil, criminal, administrative, etc. |
| G.1.4 | Write unit tests for topic extraction | 1h | ⬜ | G.1.1 | 80%+ coverage on extractor |

**Schema Addition:**
```cypher
(:LegalTopic {
  id: String,              // "topic_" + MD5(name)
  name: String,            // Topic name (unique)
  parent_id: String,       // Parent topic for hierarchy
  description: String,     // Topic description
  created_at: String
})

(:CourtDecisionDocument)-[:RELATES_TO {
  relevance: Float,        // 0.0-1.0 confidence score
  created_at: String
}]->(:LegalTopic)
```

---

### G.2: Article Extraction (from Laws)

| ID | Task | Effort | Status | Dependencies | Acceptance Criteria |
|----|------|--------|--------|--------------|---------------------|
| G.2.1 | Create `ArticleExtractor` service | 3h | ⬜ | None | Parses Croatian law structure (članak, stavak, točka) |
| G.2.2 | Create `ArticleGraphSyncService` | 2h | ⬜ | G.2.1 | Syncs Article nodes linked to LawDocument |
| G.2.3 | Link decisions to specific articles | 2h | ⬜ | G.2.2 | Update CITES relationship to point to Article nodes |
| G.2.4 | Write unit tests | 1h | ⬜ | G.2.1 | Tests for Članak 1., Čl. 1., Article 1 patterns |

**Schema Addition:**
```cypher
(:Article {
  id: String,              // "article_" + MD5(law_id + article_num)
  law_id: String,          // Parent law document ID
  article_number: String,  // "1", "1a", "101"
  title: String,           // Article title if present
  content: String,         // Full article text
  paragraph_count: Integer,
  created_at: String
})

(:LawDocument)-[:CONTAINS {
  position: Integer,       // Order in document
  created_at: String
}]->(:Article)

(:CourtDecisionDocument)-[:CITES_ARTICLE {
  paragraph: String,
  context: String,
  created_at: String
}]->(:Article)
```

---

### G.3: Legal Concept Extraction

| ID | Task | Effort | Status | Dependencies | Acceptance Criteria |
|----|------|--------|--------|--------------|---------------------|
| G.3.1 | Create `LegalConceptExtractor` service | 3h | ⬜ | None | Identifies legal concepts in text |
| G.3.2 | Create `LegalConceptGraphSyncService` | 2h | ⬜ | G.3.1 | Syncs LegalConcept nodes (schema already exists) |
| G.3.3 | Seed Croatian legal concepts | 2h | ⬜ | None | ~100 core concepts: tužba, presuda, ugovor, šteta, etc. |
| G.3.4 | Write unit tests | 1h | ⬜ | G.3.1 | Tests for concept matching and disambiguation |

**Uses Existing Schema:**
```cypher
(:LegalConcept {
  id: String,
  name: String,
  definition: String,
  category: String,
  created_at: String
})

(:CourtDecisionDocument)-[:MENTIONS {
  frequency: Integer,
  created_at: String
}]->(:LegalConcept)
```

---

### G.4: Lawyer Extraction

| ID | Task | Effort | Status | Dependencies | Acceptance Criteria |
|----|------|--------|--------|--------------|---------------------|
| G.4.1 | Create `LawyerExtractor` service | 2h | ⬜ | None | Extracts "Punomoćnik:", "Odvjetnik:" patterns |
| G.4.2 | Create `LawyerGraphSyncService` | 2h | ⬜ | G.4.1 | Syncs Lawyer nodes with REPRESENTED_BY relationship |
| G.4.3 | Add lawyer deduplication logic | 1h | ⬜ | G.4.2 | Name normalization for matching |
| G.4.4 | Write unit tests | 1h | ⬜ | G.4.1 | Tests for various attorney formats |

**Schema Addition:**
```cypher
(:Lawyer {
  id: String,              // "lawyer_" + MD5(normalized_name)
  name: String,            // Full name
  normalized_name: String, // For matching
  bar_number: String,      // If extractable
  firm: String,            // Law firm if known
  created_at: String
})

(:Party)-[:REPRESENTED_BY {
  case_id: String,         // Which case
  created_at: String
}]->(:Lawyer)
```

---

### G.5: Verdict Extraction

| ID | Task | Effort | Status | Dependencies | Acceptance Criteria |
|----|------|--------|--------|--------------|---------------------|
| G.5.1 | Create `VerdictExtractor` service | 2h | ⬜ | None | Parses "Odbija se", "Usvaja se", etc. |
| G.5.2 | Create `VerdictGraphSyncService` | 2h | ⬜ | G.5.1 | Syncs Verdict nodes with detailed outcome |
| G.5.3 | Map verdicts to standardized types | 1h | ⬜ | G.5.2 | Taxonomy: granted, denied, partial, dismissed, remanded |
| G.5.4 | Write unit tests | 1h | ⬜ | G.5.1 | Tests for Croatian verdict patterns |

**Schema Addition:**
```cypher
(:Verdict {
  id: String,              // "verdict_" + decision_id
  decision_id: String,     // Parent decision
  outcome_type: String,    // "granted", "denied", "partial", etc.
  raw_text: String,        // Original verdict text
  relief_granted: String,  // What was granted
  relief_denied: String,   // What was denied
  damages_amount: Float,   // If monetary
  damages_currency: String,
  created_at: String
})

(:CourtDecisionDocument)-[:HAS_VERDICT {
  created_at: String
}]->(:Verdict)
```

---

### G.6: Legal Argument Extraction

| ID | Task | Effort | Status | Dependencies | Acceptance Criteria |
|----|------|--------|--------|--------------|---------------------|
| G.6.1 | Create `LegalArgumentExtractor` service | 4h | ⬜ | None | Segments decision into arguments using NLP |
| G.6.2 | Create `LegalArgumentGraphSyncService` | 2h | ⬜ | G.6.1 | Syncs LegalArgument nodes |
| G.6.3 | Add argument classification | 2h | ⬜ | G.6.1 | Types: procedural, substantive, evidentiary |
| G.6.4 | Write unit tests | 1h | ⬜ | G.6.1 | Tests for argument boundary detection |

**Schema Addition:**
```cypher
(:LegalArgument {
  id: String,              // ULID
  decision_id: String,     // Parent decision
  argument_type: String,   // "procedural", "substantive", "evidentiary"
  position: String,        // "plaintiff", "defendant", "court"
  summary: String,         // Brief summary
  full_text: String,       // Full argument text
  accepted: Boolean,       // Whether court accepted it
  created_at: String
})

(:CourtDecisionDocument)-[:CONTAINS_ARGUMENT {
  sequence: Integer,       // Order in decision
  created_at: String
}]->(:LegalArgument)

(:LegalArgument)-[:CITES]->(:Article)
(:LegalArgument)-[:REFERENCES]->(:CourtDecisionDocument)
```

---

### G.7: Evidence Extraction

| ID | Task | Effort | Status | Dependencies | Acceptance Criteria |
|----|------|--------|--------|--------------|---------------------|
| G.7.1 | Create `EvidenceExtractor` service | 3h | ⬜ | None | Extracts "Dokaz:", "Prilog" patterns |
| G.7.2 | Create `EvidenceGraphSyncService` | 2h | ⬜ | G.7.1 | Syncs Evidence nodes |
| G.7.3 | Add evidence type classification | 1h | ⬜ | G.7.1 | Types: documentary, testimonial, expert |
| G.7.4 | Write unit tests | 1h | ⬜ | G.7.1 | Tests for evidence patterns |

**Schema Addition:**
```cypher
(:Evidence {
  id: String,              // ULID
  case_id: String,         // Parent case
  evidence_type: String,   // "documentary", "testimonial", "expert", "physical"
  description: String,     // Evidence description
  admitted: Boolean,       // Whether admitted
  weight: String,          // "decisive", "corroborative", "rejected"
  created_at: String
})

(:CourtDecisionDocument)-[:CONSIDERS_EVIDENCE {
  ruling: String,          // "admitted", "excluded", "limited"
  created_at: String
}]->(:Evidence)
```

---

### G.8: Date Event Extraction

| ID | Task | Effort | Status | Dependencies | Acceptance Criteria |
|----|------|--------|--------|--------------|---------------------|
| G.8.1 | Create `DateEventExtractor` service | 2h | ⬜ | None | Extracts dates with context |
| G.8.2 | Create `DateEventGraphSyncService` | 2h | ⬜ | G.8.1 | Syncs DateEvent nodes |
| G.8.3 | Add event type classification | 1h | ⬜ | G.8.1 | Types: filing, hearing, judgment, effective |
| G.8.4 | Write unit tests | 1h | ⬜ | G.8.1 | Tests for Croatian date formats |

**Schema Addition:**
```cypher
(:DateEvent {
  id: String,              // ULID
  date: String,            // ISO8601 date
  event_type: String,      // "filing", "hearing", "judgment", "effective", "expiry"
  description: String,     // Event description
  source_type: String,     // "decision", "law", "case"
  source_id: String,       // Source document ID
  created_at: String
})

(:CourtDecisionDocument)-[:HAS_EVENT {
  created_at: String
}]->(:DateEvent)

(:LawDocument)-[:HAS_EVENT {
  created_at: String
}]->(:DateEvent)
```

---

### G.9: Legal Definition Extraction

| ID | Task | Effort | Status | Dependencies | Acceptance Criteria |
|----|------|--------|--------|--------------|---------------------|
| G.9.1 | Create `LegalDefinitionExtractor` service | 3h | ⬜ | None | Extracts "znači:", "smatra se:", patterns |
| G.9.2 | Create `LegalDefinitionGraphSyncService` | 2h | ⬜ | G.9.1 | Syncs LegalDefinition nodes |
| G.9.3 | Link definitions to source laws | 1h | ⬜ | G.9.2 | DEFINES relationship from LawDocument |
| G.9.4 | Write unit tests | 1h | ⬜ | G.9.1 | Tests for definition patterns |

**Schema Addition:**
```cypher
(:LegalDefinition {
  id: String,              // "def_" + MD5(term + law_id)
  term: String,            // Defined term
  definition: String,      // Definition text
  source_article: String,  // Article number
  law_id: String,          // Source law
  scope: String,           // "this_law", "general", "specific_context"
  created_at: String
})

(:LawDocument)-[:DEFINES {
  article: String,
  created_at: String
}]->(:LegalDefinition)

(:CourtDecisionDocument)-[:APPLIES_DEFINITION {
  interpretation: String,  // How court interpreted it
  created_at: String
}]->(:LegalDefinition)
```

---

## Sprint Summary

| Phase | Tasks | Total Effort | Priority |
|-------|-------|--------------|----------|
| C: Integration Tests | 5 | 10h | HIGH |
| D: Data Pipeline | 3 | 5h | HIGH |
| E: UI Enablement | 5 | 7h | MEDIUM |
| F: Performance | 4 | 7h | MEDIUM |
| G: Entity Expansion | 36 | 58h | HIGH |
| **TOTAL** | **53** | **87h** |  |

### Phase G Breakdown

| Entity | Tasks | Effort |
|--------|-------|--------|
| G.1: Legal Topic | 4 | 7h |
| G.2: Article | 4 | 8h |
| G.3: Legal Concept | 4 | 8h |
| G.4: Lawyer | 4 | 6h |
| G.5: Verdict | 4 | 6h |
| G.6: Legal Argument | 4 | 9h |
| G.7: Evidence | 4 | 7h |
| G.8: Date Event | 4 | 6h |
| G.9: Legal Definition | 4 | 7h |

---

## Execution Order

### Wave 1: Foundation (C.1 + D.2-D.4)
1. **C.1** - Test container infrastructure (enables integration tests)
2. **D.2** - Background job (enables async processing)
3. **D.3** - Progress tracking (observability)
4. **D.4** - Schema apply command (deployment automation)

### Wave 2: Core Integration Tests (C.2-C.5)
5. **C.2-C.5** - Complete integration test suite (parallel)

### Wave 3: High-Value Entities (G.1-G.3)
6. **G.1** - Legal Topics (classification)
7. **G.2** - Articles (statute structure)
8. **G.3** - Legal Concepts (semantic enrichment)

### Wave 4: People & Outcomes (G.4-G.5)
9. **G.4** - Lawyers (participant network)
10. **G.5** - Verdicts (outcome structure)

### Wave 5: Deep Analysis Entities (G.6-G.9)
11. **G.6** - Legal Arguments (reasoning structure)
12. **G.7** - Evidence (fact extraction)
13. **G.8** - Date Events (timeline)
14. **G.9** - Legal Definitions (terminology)

### Wave 6: UI & Performance (E + F)
15. **E.1-E.5** - UI panels (parallel)
16. **F.1-F.4** - Performance (parallel)

---

## Graph Schema Addendum

All new entities will be added to `documentation/GRAPH_SCHEMA.cypher`:

```cypher
// ============================================================================
// Phase G: Entity Expansion (2026-01-07)
// ============================================================================

// G.1 Legal Topic
CREATE CONSTRAINT legal_topic_id IF NOT EXISTS FOR (lt:LegalTopic) REQUIRE lt.id IS UNIQUE;
CREATE INDEX legal_topic_name_idx IF NOT EXISTS FOR (lt:LegalTopic) ON (lt.name);

// G.2 Article
CREATE CONSTRAINT article_id IF NOT EXISTS FOR (a:Article) REQUIRE a.id IS UNIQUE;
CREATE INDEX article_law_idx IF NOT EXISTS FOR (a:Article) ON (a.law_id);
CREATE INDEX article_number_idx IF NOT EXISTS FOR (a:Article) ON (a.article_number);

// G.4 Lawyer
CREATE CONSTRAINT lawyer_id IF NOT EXISTS FOR (l:Lawyer) REQUIRE l.id IS UNIQUE;
CREATE INDEX lawyer_name_idx IF NOT EXISTS FOR (l:Lawyer) ON (l.name);

// G.5 Verdict
CREATE CONSTRAINT verdict_id IF NOT EXISTS FOR (v:Verdict) REQUIRE v.id IS UNIQUE;
CREATE INDEX verdict_outcome_idx IF NOT EXISTS FOR (v:Verdict) ON (v.outcome_type);

// G.6 Legal Argument
CREATE CONSTRAINT legal_argument_id IF NOT EXISTS FOR (la:LegalArgument) REQUIRE la.id IS UNIQUE;
CREATE INDEX legal_argument_type_idx IF NOT EXISTS FOR (la:LegalArgument) ON (la.argument_type);

// G.7 Evidence
CREATE CONSTRAINT evidence_id IF NOT EXISTS FOR (e:Evidence) REQUIRE e.id IS UNIQUE;
CREATE INDEX evidence_type_idx IF NOT EXISTS FOR (e:Evidence) ON (e.evidence_type);

// G.8 Date Event
CREATE CONSTRAINT date_event_id IF NOT EXISTS FOR (de:DateEvent) REQUIRE de.id IS UNIQUE;
CREATE INDEX date_event_date_idx IF NOT EXISTS FOR (de:DateEvent) ON (de.date);
CREATE INDEX date_event_type_idx IF NOT EXISTS FOR (de:DateEvent) ON (de.event_type);

// G.9 Legal Definition
CREATE CONSTRAINT legal_definition_id IF NOT EXISTS FOR (ld:LegalDefinition) REQUIRE ld.id IS UNIQUE;
CREATE INDEX legal_definition_term_idx IF NOT EXISTS FOR (ld:LegalDefinition) ON (ld.term);
```

---

## Risk Assessment

| Risk | Probability | Impact | Mitigation |
|------|-------------|--------|------------|
| NLP extraction accuracy issues | High | Medium | Start with rule-based, add ML later |
| Performance degradation with 9 new entities | Medium | High | Feature flags per entity, batch processing |
| Croatian text parsing complexity | High | Medium | Comprehensive regex patterns, fallback to manual tagging |
| Test container flakiness | Medium | Low | Retry logic, deterministic seeds |
| Schema migration complexity | Low | High | Apply schema incrementally per entity wave |

---

## Definition of Done

### Phase C
- [ ] Integration tests run against real Neo4j container
- [ ] All Cypher syntax verified
- [ ] Constraint violations throw expected exceptions
- [ ] End-to-end sync test passes
- [ ] No Cypher injection possible

### Phase D
- [ ] Background job processes decisions asynchronously
- [ ] Progress events emitted and loggable
- [ ] Schema apply command deploys constraints/indexes

### Phase E
- [ ] Judge panel shows real data with loading states
- [ ] Party panel displays plaintiff/defendant
- [ ] Precedent chain visualizes relationships
- [ ] Tooltips explain all badges

### Phase F
- [ ] Connection pooling configured
- [ ] Circuit breaker prevents cascade failures
- [ ] Benchmarks establish performance baseline
- [ ] Slow queries logged with explain

### Phase G (per entity)
- [ ] Extractor service implemented with TDD
- [ ] Graph sync service creates nodes/relationships
- [ ] Schema updated with constraints/indexes
- [ ] Unit tests achieve 80%+ coverage
- [ ] Feature flag controls extraction

---

## Files Index

### Phase C
```
tests/Integration/Graph/
├── GraphIntegrationTestCase.php
├── CypherSyntaxValidationTest.php
├── ConstraintViolationTest.php
├── EndToEndSyncTest.php
└── CypherInjectionPreventionTest.php
docker-compose.testing.yml
```

### Phase D
```
app/Jobs/EnhancedGraphSyncJob.php
app/Events/Graph/BatchStarted.php
app/Events/Graph/BatchCompleted.php
app/Events/Graph/SyncFinished.php
app/Console/Commands/GraphSchemaApplyCommand.php
```

### Phase E
```
app/Livewire/Graph/GraphViewer.php (modify)
resources/views/livewire/graph/partials/party-panel.blade.php
resources/views/livewire/graph/partials/precedent-chain.blade.php
resources/views/livewire/graph/partials/precedential-badge.blade.php
```

### Phase F
```
app/Services/Graph/CircuitBreaker.php
tests/Performance/Graph/SyncBenchmarkTest.php
tests/Performance/Graph/QueryPerformanceTest.php
config/graph.php (modify)
```

### Phase G
```
app/Services/Graph/Extractors/
├── LegalTopicExtractor.php
├── ArticleExtractor.php
├── LegalConceptExtractor.php
├── LawyerExtractor.php
├── VerdictExtractor.php
├── LegalArgumentExtractor.php
├── EvidenceExtractor.php
├── DateEventExtractor.php
└── LegalDefinitionExtractor.php

app/Services/Graph/
├── LegalTopicGraphSyncService.php
├── ArticleGraphSyncService.php
├── LegalConceptGraphSyncService.php
├── LawyerGraphSyncService.php
├── VerdictGraphSyncService.php
├── LegalArgumentGraphSyncService.php
├── EvidenceGraphSyncService.php
├── DateEventGraphSyncService.php
└── LegalDefinitionGraphSyncService.php

database/seeders/
├── LegalTopicSeeder.php
└── LegalConceptSeeder.php

tests/Unit/Services/Graph/Extractors/
├── LegalTopicExtractorTest.php
├── ArticleExtractorTest.php
├── LegalConceptExtractorTest.php
├── LawyerExtractorTest.php
├── VerdictExtractorTest.php
├── LegalArgumentExtractorTest.php
├── EvidenceExtractorTest.php
├── DateEventExtractorTest.php
└── LegalDefinitionExtractorTest.php
```

---

**Next Action:** Execute Wave 1 (C.1 + D.2-D.4) to establish foundation

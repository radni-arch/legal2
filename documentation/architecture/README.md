# System Architecture

This document describes the overall architecture of the AI Legal War Machine system, with a focus on the unified search service layer.

## Table of Contents

- [Overview](#overview)
- [Search Service Layer](#search-service-layer)
- [Component Relationships](#component-relationships)
- [Data Flow](#data-flow)
- [Database Schema](#database-schema)
- [External Services](#external-services)

---

## Overview

The AI Legal War Machine is a legal research and analysis platform that combines:
- **Vector-based semantic search** using OpenAI embeddings and PostgreSQL pgvector
- **Keyword-based exact matching** for precise legal citations
- **Hybrid search** combining both approaches
- **Autonomous research agents** for intelligent legal analysis
- **Graph database integration** for relationship mapping
- **MCP (Model Context Protocol) tools** for AI integration

---

## Search Service Layer

The search service layer provides unified access to legal document search capabilities across laws, court decisions, and legal cases.

### Service Classes

#### LawSearchService
Provides search capabilities for legal statutes and regulations.

**Methods:**
- `vectorSearch(string $query, array $filters)` - Semantic similarity search using embeddings
- `keywordSearch(string $query, array $filters)` - Exact text matching
- `hybridSearch(string $query, array $filters)` - Combined vector + keyword search
- `lookupByNumber(string $lawNumber, ?string $jurisdiction)` - Find law by citation
- `lookupByDocId(string $docId, ?int $chunkIndex, array $filters)` - Retrieve specific article
- `search(string $query, array $options)` - Unified search interface

**Database Tables:**
- `laws` - Individual law chunks with embeddings
- `ingested_laws` - Law metadata and document information

#### DecisionSearchService
Provides search capabilities for court decisions and rulings.

**Methods:**
- `vectorSearch(string $query, array $filters)` - Semantic search across decision documents
- `keywordSearch(string $query, array $filters)` - Text-based decision search
- `hybridSearch(string $query, array $filters)` - Combined search approach
- `lookupByCriteria(array $criteria)` - Multi-criteria decision lookup
- `getById(string $id, bool $includeContent, bool $includeDocuments)` - Retrieve specific decision
- `search(string $query, array $options)` - Unified search interface

**Database Tables:**
- `court_decision_documents` - Decision document chunks with embeddings
- `court_decisions` - Decision metadata (case number, court, date, etc.)

#### CaseSearchService
Provides search capabilities for legal cases and case documents.

**Methods:**
- `vectorSearch(string $query, array $filters)` - Semantic search across case documents
- `searchCases(string $query, array $filters)` - Search LegalCase records
- `searchDocuments(string $query, array $filters)` - Search CaseDocument records
- `search(string $query, array $options)` - Unified search interface

**Database Tables:**
- `cases_documents` - Case document chunks with embeddings
- `cases` - Legal case metadata (parties, court, status, etc.)

### Base Classes

#### SearchServiceInterface
Defines the contract that all search services must implement:

```php
interface SearchServiceInterface
{
    public function search(string $query, array $options = []): array;
}
```

#### BaseSearchService
Provides shared functionality for all search services:

**Protected Methods:**
- `generateEmbedding(string $text): ?array` - Generate OpenAI embeddings
- `cosineSimilarity(array $a, array $b): float` - Calculate vector similarity
- `toPgVectorLiteral(array $vec): string` - Format vector for PostgreSQL
- `toPgVectorCastLiteral(array $vec): string` - Format with type cast
- `norm(array $vec): float` - Calculate vector L2 norm

**Dependencies:**
- `OpenAIService` - For embedding generation

### Architecture Diagram

```
┌─────────────────────────────────────────────────────────────┐
│                    Application Layer                         │
├─────────────────────────────────────────────────────────────┤
│  AutonomousResearchAgent          MCP Tools                 │
│  ├─ law_vector_search             ├─ LawSearchTool          │
│  ├─ law_keyword_search            ├─ DecisionSearchTool     │
│  ├─ decision_vector_search        ├─ CaseSearchTool         │
│  ├─ case_vector_search            └─ LawGetArticleTool      │
│  └─ ...                                                      │
│           │                               │                  │
│           └───────────┬───────────────────┘                  │
│                       ↓                                      │
├─────────────────────────────────────────────────────────────┤
│                  Service Layer                               │
├─────────────────────────────────────────────────────────────┤
│  ┌──────────────────┐  ┌───────────────────┐  ┌──────────┐ │
│  │ LawSearchService │  │DecisionSearchSvc  │  │CaseSearch│ │
│  ├──────────────────┤  ├───────────────────┤  ├──────────┤ │
│  │vectorSearch()    │  │vectorSearch()     │  │vector()  │ │
│  │keywordSearch()   │  │keywordSearch()    │  │search    │ │
│  │hybridSearch()    │  │hybridSearch()     │  │Cases()   │ │
│  │lookupByNumber()  │  │lookupByCriteria() │  │search    │ │
│  │lookupByDocId()   │  │getById()          │  │Documents │ │
│  └────────┬─────────┘  └─────────┬─────────┘  └────┬─────┘ │
│           │                      │                  │       │
│           └──────────────────────┼──────────────────┘       │
│                                  ↓                          │
│                    ┌─────────────────────────┐              │
│                    │  BaseSearchService      │              │
│                    ├─────────────────────────┤              │
│                    │ generateEmbedding()     │              │
│                    │ cosineSimilarity()      │              │
│                    │ toPgVectorLiteral()     │              │
│                    └───────────┬─────────────┘              │
│                                │                            │
├─────────────────────────────────────────────────────────────┤
│                  External Services                          │
├─────────────────────────────────────────────────────────────┤
│  OpenAIService (embeddings)                                 │
│           │                                                 │
├───────────┼─────────────────────────────────────────────────┤
│           ↓                                                 │
│  ┌─────────────────────────────────────────────────┐       │
│  │             Database Layer                       │       │
│  ├─────────────────────────────────────────────────┤       │
│  │  PostgreSQL + pgvector                          │       │
│  │  ├─ laws (embeddings)                           │       │
│  │  ├─ court_decision_documents (embeddings)       │       │
│  │  ├─ cases_documents (embeddings)                │       │
│  │  ├─ ingested_laws (metadata)                    │       │
│  │  ├─ court_decisions (metadata)                  │       │
│  │  └─ cases (metadata)                            │       │
│  └─────────────────────────────────────────────────┘       │
└─────────────────────────────────────────────────────────────┘
```

### Key Design Principles

1. **Separation of Concerns**: Services handle business logic, MCP tools handle API, agents orchestrate research
2. **Single Responsibility**: Each service focuses on one entity type (laws, decisions, or cases)
3. **Consistent Interface**: All services implement `SearchServiceInterface`
4. **Reusable Components**: `BaseSearchService` provides shared vector operations
5. **No Circular Dependencies**: Services don't depend on tools or agents
6. **Backward Compatibility**: Legacy `AgentToolbox` methods still work (deprecated)

### Migration Path

For applications using the old `AgentToolbox` API, see [MIGRATION_AGENT_TOOLBOX.md](MIGRATION_AGENT_TOOLBOX.md) for migration guide.

**Deprecated methods:**
- `AgentToolbox::vectorSearch()` → Use search services directly
- `AgentToolbox::lawLookup()` → Use `LawSearchService::lookupByNumber()`
- `AgentToolbox::decisionLookup()` → Use `DecisionSearchService::lookupByCriteria()`

---

## Component Relationships

### AutonomousResearchAgent
The autonomous agent uses search services for legal research:

```php
class AutonomousResearchAgent extends BaseLlmAgent
{
    protected ?LawSearchService $lawSearch = null;
    protected ?DecisionSearchService $decisionSearch = null;
    protected ?CaseSearchService $caseSearch = null;

    // 18 tool handlers delegate to search services:
    // - law_vector_search, law_keyword_search, law_hybrid_search
    // - decision_vector_search, decision_keyword_search, decision_hybrid_search
    // - case_vector_search, case_search, case_document_search
    // - law_lookup, law_get_article
    // - decision_lookup, decision_get
}
```

### MCP Tools
Model Context Protocol tools provide API layer for AI integration:

```php
class LawSearchTool extends BaseTool
{
    public function __construct(protected LawSearchService $searchService) {}

    public function handle(array $arguments): ToolResult
    {
        $searchType = $arguments['search_type'] ?? 'keyword';
        return $this->searchService->search($query, ['search_type' => $searchType]);
    }
}
```

### AgentToolbox (Legacy)
Preserved for backward compatibility with deprecation warnings:

```php
class AgentToolbox
{
    // Still available but logs deprecation warnings
    // Delegates to new search services internally
    public function vectorSearch(string $query, array $options): array
    public function lawLookup(string $lawNumber, ?string $jurisdiction): array
    public function decisionLookup(array $criteria): array
}
```

---

## Data Flow

### Vector Search Flow

1. **User/Agent** initiates search with query text
2. **Search Service** generates embedding via `OpenAIService`
3. **Service** queries database using pgvector `<=>` operator
4. **PostgreSQL** returns top-k results by cosine similarity
5. **Service** formats results with similarity scores
6. **Results** returned to caller

### Keyword Search Flow

1. **User/Agent** initiates search with query text
2. **Search Service** builds Eloquent query with LIKE clauses
3. **Service** applies filters (jurisdiction, date range, etc.)
4. **PostgreSQL** returns matching records
5. **Service** formats results with pagination
6. **Results** returned to caller

### Hybrid Search Flow

1. **User/Agent** initiates search with query text
2. **Search Service** executes both vector and keyword searches in parallel
3. **Service** merges results, deduplicating by doc_id
4. **Service** adds match_type ('vector' or 'keyword') and score fields
5. **Service** sorts by score (vector results get actual similarity, keyword gets 0.5)
6. **Results** returned to caller

---

## Database Schema

### Core Tables

#### laws
Stores individual law chunks with embeddings for vector search.

**Key Columns:**
- `id` - Primary key
- `doc_id` - Foreign key to ingested_laws
- `title` - Law title
- `law_number` - Legal citation (e.g., "NN 94/14")
- `jurisdiction` - Jurisdiction code
- `content` - Law text content
- `embedding` - Vector embedding (pgvector)
- `chunk_index` - Chunk number for multi-part laws
- `metadata` - JSON metadata

#### court_decision_documents
Stores court decision document chunks with embeddings.

**Key Columns:**
- `id` - Primary key
- `decision_id` - Foreign key to court_decisions
- `doc_id` - Document identifier
- `title` - Document title
- `content` - Decision text
- `embedding` - Vector embedding (pgvector)
- `chunk_index` - Chunk number

#### cases_documents
Stores legal case document chunks with embeddings.

**Key Columns:**
- `id` - Primary key
- `case_id` - Foreign key to cases
- `doc_id` - Document identifier
- `title` - Document title
- `content` - Document text
- `embedding` - Vector embedding (pgvector)
- `chunk_index` - Chunk number

### Vector Search Configuration

**PostgreSQL Extension:**
```sql
CREATE EXTENSION IF NOT EXISTS vector;
```

**Index Creation:**
```sql
CREATE INDEX laws_embedding_idx ON laws USING ivfflat (embedding vector_cosine_ops);
CREATE INDEX decision_docs_embedding_idx ON court_decision_documents USING ivfflat (embedding vector_cosine_ops);
CREATE INDEX case_docs_embedding_idx ON cases_documents USING ivfflat (embedding vector_cosine_ops);
```

**Distance Operators:**
- `<=>` - Cosine distance (1 - cosine similarity)
- `<->` - L2 distance (Euclidean)
- `<#>` - Inner product

---

## External Services

### OpenAI API
**Purpose:** Generate text embeddings for semantic search

**Model:** `text-embedding-ada-002` (1536 dimensions)

**Service Class:** `OpenAIService`

**Usage:**
```php
$embedding = $this->openai->embeddings(['query text'], $model);
$vector = $embedding['data'][0]['embedding'];
```

### Neo4j Graph Database
**Purpose:** Store relationships between legal entities

**Service Class:** `GraphDatabaseService`

**Integration:** Via `AgentToolbox::graphQuery()` (not part of search services)

---

## Performance Considerations

### Vector Search
- **Index Type:** IVFFlat for approximate nearest neighbor search
- **Query Time:** O(log n) with proper indexing
- **Memory:** Embeddings are 1536 floats × 4 bytes = 6KB per document
- **Recommendation:** Use `min_similarity` filter to reduce result set

### Keyword Search
- **Index Type:** B-tree on text columns, GIN for JSON tags
- **Query Time:** O(log n) for indexed columns
- **Pagination:** Always use limit/offset to control result size
- **Recommendation:** Use specific filters (jurisdiction, date range) to narrow results

### Hybrid Search
- **Approach:** Parallel execution of vector + keyword, then merge
- **Deduplication:** By doc_id to avoid duplicates
- **Trade-off:** 2x database queries but better recall
- **Recommendation:** Use for exploratory searches, single mode for targeted searches

---

## Testing

Comprehensive test coverage across all layers:

**Unit Tests:**
- `tests/Unit/Services/LawSearchServiceTest.php`
- `tests/Unit/Services/DecisionSearchServiceTest.php`
- `tests/Unit/Services/CaseSearchServiceTest.php`

**Feature Tests:**
- `tests/Feature/Agents/AutonomousResearchAgentTest.php`

**Integration Tests:**
- `tests/Feature/Services/SearchIntegrationTest.php`

**Coverage:** 49 test methods, 2,372 lines of test code

---

## Related Documentation

- [MIGRATION_AGENT_TOOLBOX.md](MIGRATION_AGENT_TOOLBOX.md) - Migration guide from legacy API
- [AGENT_API.md](AGENT_API.md) - Agent API endpoints
- [API_SEARCH.md](API_SEARCH.md) - Search API documentation
- [AUTONOMOUS_AGENT_README.md](AUTONOMOUS_AGENT_README.md) - Agent system overview

---

## Architecture Decision Records (ADRs)

ADRs document significant architectural decisions made during the project. Each ADR includes context, decision, rationale, consequences, and alternatives.

### Active ADRs

- **[ADR-001: Agent Communication Bus](./adr-001-agent-communication-bus.md)** (Status: Proposed)
  - Defines message bus architecture for agent-to-agent communication
  - Created: 2025-11-10, Sprint: 17.6
  - [Sequence Diagrams](./agent-communication-flows.md) | [Performance Requirements](./agent-bus-performance-requirements.md)

---

## Agent Communication Bus

Complete design for asynchronous multi-agent communication infrastructure.

### Documents

1. **[ADR-001: Agent Communication Bus](./adr-001-agent-communication-bus.md)**
   - High-level architecture overview
   - Message format specification
   - Routing strategies (direct, agent type, broadcast)
   - Priority queue implementation
   - Design rationale and alternatives considered
   - Implementation plan (4 phases)

2. **[Agent Communication Flows](./agent-communication-flows.md)**
   - Sequence diagrams for 4 common scenarios:
     - Collaborative Research (Request/Response)
     - Pipeline Processing (Sequential Work Passing)
     - Broadcast Coordination (Event Notification)
     - Resource Negotiation (Coordination)
   - Queue worker configuration
   - Testing examples (unit tests, integration tests)

3. **[Performance Requirements](./agent-bus-performance-requirements.md)**
   - Latency targets (P50/P95/P99 by priority)
   - Throughput targets (current/target/peak scale: 100/500/1000 msg/min)
   - Resource limits (database connections, worker memory, message size)
   - Scalability limits (vertical and horizontal scaling strategies)
   - Reliability guarantees (at-least-once delivery, idempotency, retries)
   - Performance testing plan (load tests, stress tests)
   - Monitoring & alerting strategy
   - Cost analysis ($80/mo → $330/mo → $760/mo)

### Quick Reference

**Message Format:**
```json
{
  "id": "01JKWXYZ...",
  "type": "REQUEST|RESPONSE|BROADCAST|EVENT",
  "priority": "CRITICAL|HIGH|NORMAL|LOW",
  "routing": { "target_type": "AGENT|AGENT_TYPE|BROADCAST", ... },
  "payload": { /* message-specific data */ }
}
```

**Priority Levels:**
- **CRITICAL** (1000): < 1s delivery, court deadlines
- **HIGH** (500): < 3s delivery, active case work
- **NORMAL** (100): < 5s delivery, background research
- **LOW** (10): < 30s delivery, batch processing

**Implementation Status:**
- ✅ Sprint 17.6 (Design): ADR, sequence diagrams, performance requirements
- ⏳ Sprint 17.7 (Implementation): Core bus infrastructure
- ⏳ Sprint 17.8 (Integration): Agent updates, tests
- ⏳ Sprint 17.9 (Monitoring): Metrics, dashboards, alerts

# Agent Toolbox Refactoring Plan

**Sprint**: E3.1 - Agent Toolbox Audit
**Date**: 2025-10-26
**Status**: Draft - Awaiting Approval

---

## Executive Summary

This document provides a comprehensive audit of the Agent Toolbox (`app/Services/AgentToolbox.php`) and identifies duplication with MCP tools. The audit reveals **partial overlap** between Agent tools and MCP tools, with opportunities for consolidation and improved architecture.

**Key Finding**: Agent tools provide vector-based semantic search, while MCP tools provide keyword-based search. These are complementary capabilities that should be unified under a consistent interface.

---

## 1. Agent Toolbox Inventory

### File: `app/Services/AgentToolbox.php` (595 lines)

#### 1.1 Vector Search
**Method**: `vectorSearch(string $query, array $options = []): array`

**Purpose**: Semantic search across laws, cases, and court decisions using OpenAI embeddings and vector similarity.

**Inputs**:
- `query` (string): The search query
- `options` (array):
  - `types` (array): Types to search - 'laws', 'cases', 'decisions' (default: all)
  - `limit` (int): Max results per type (default: 10)
  - `jurisdiction` (string|null): Filter by jurisdiction
  - `min_similarity` (float): Minimum cosine similarity (default: 0.7)

**Outputs**:
```php
[
    'laws' => [...],      // Array of law chunks with similarity scores
    'cases' => [...],     // Array of case documents with similarity scores
    'decisions' => [...]  // Array of decision documents with similarity scores
]
```

**Implementation**:
- Generates embedding for query using OpenAI
- Searches `laws`, `cases_documents`, `court_decision_documents` tables
- PostgreSQL: Uses pgvector extension for similarity search
- Non-PostgreSQL: Falls back to JSON-stored vectors with PHP cosine similarity
- Calls internal methods: `searchLaws()`, `searchCases()`, `searchDecisions()`

**Dependencies**:
- OpenAIService
- Database models: Law, CaseDocument, CourtDecisionDocument

---

#### 1.2 Law Lookup
**Method**: `lawLookup(string $lawNumber, ?string $jurisdiction = null): array`

**Purpose**: Look up a specific law by law number and retrieve all chunks.

**Inputs**:
- `lawNumber` (string): The law number (e.g., "NN 94/14")
- `jurisdiction` (string|null): Optional jurisdiction filter

**Outputs**:
```php
[
    'doc_id' => string,
    'title' => string,
    'law_number' => string,
    'jurisdiction' => string,
    'country' => string,
    'language' => string,
    'keywords' => array,
    'aliases' => array,
    'metadata' => array,
    'chunks' => [
        [
            'chunk_index' => int,
            'content' => string,
            'chapter' => string,
            'section' => string,
            'tags' => array,
            'metadata' => array
        ]
    ]
]
```

**Implementation**:
- Queries `ingested_laws` table with LIKE match on law_number
- Loads all related chunks via `laws` relationship
- Returns comprehensive law document structure

**Dependencies**:
- Database model: IngestedLaw (with laws relationship)

---

#### 1.3 Decision Lookup
**Method**: `decisionLookup(array $criteria): array`

**Purpose**: Search court decisions by multiple criteria (case number, court, date range, etc.).

**Inputs** (all optional):
- `case_number` (string): Exact or partial match
- `court` (string): Court name
- `jurisdiction` (string): Jurisdiction
- `from_date` (string): Decision date from (Y-m-d)
- `to_date` (string): Decision date to (Y-m-d)
- `decision_type` (string): Type of decision
- `limit` (int): Max results (default: 20)

**Outputs**:
```php
[
    [
        'id' => string,
        'case_number' => string,
        'title' => string,
        'court' => string,
        'jurisdiction' => string,
        'decision_date' => string,
        'publication_date' => string,
        'decision_type' => string,
        'ecli' => string,
        'judge' => string,
        'documents_count' => int,
        'summary' => string  // First 500 chars of first document
    ]
]
```

**Implementation**:
- Builds query on `court_decisions` table with multiple WHERE conditions
- Orders by decision_date DESC
- Includes document count and summary preview

**Dependencies**:
- Database model: CourtDecision (with documents relationship)

---

#### 1.4 Graph Query
**Method**: `graphQuery(string $cypher, array $parameters = []): array`

**Purpose**: Execute Cypher queries against Neo4j graph database.

**Inputs**:
- `cypher` (string): Cypher query
- `parameters` (array): Query parameters

**Outputs**:
```php
[
    'success' => bool,
    'rows' => array,      // Query results as associative arrays
    'count' => int
]
// OR on error:
[
    'success' => false,
    'error' => string
]
```

**Implementation**:
- Executes Cypher query via GraphDatabaseService
- Converts Neo4j types to PHP arrays
- Handles both nodes and relationships

**Dependencies**:
- GraphDatabaseService

---

#### 1.5 Web Fetch
**Method**: `webFetch(string $url, array $options = []): array`

**Purpose**: Fetch content from a web URL.

**Inputs**:
- `url` (string): URL to fetch
- `options` (array):
  - `timeout` (int): Request timeout in seconds (default: 30)
  - `headers` (array): Additional headers
  - `method` (string): HTTP method (default: GET)

**Outputs**:
```php
[
    'success' => bool,
    'status' => int,
    'content' => string,
    'headers' => array,
    'url' => string
]
// OR on error:
[
    'success' => false,
    'error' => string,
    'url' => string
]
```

**Implementation**:
- Uses Laravel HTTP client
- Configurable timeout and headers
- Comprehensive error handling

**Dependencies**:
- Laravel HTTP facade

---

#### 1.6 Note Save
**Method**: `noteSave(string $agentName, string $content, array $options = []): array`

**Purpose**: Save insights or notes to agent vector memory for future retrieval.

**Inputs**:
- `agentName` (string): Name of the agent
- `content` (string): Content to save
- `options` (array):
  - `namespace` (string): Memory namespace (default: 'insights')
  - `metadata` (array): Additional metadata
  - `source` (string): Source reference (default: 'self_study')
  - `source_id` (string|null): Source identifier

**Outputs**:
```php
[
    'success' => bool,
    'id' => string,  // ULID
    'status' => 'created' | 'already_exists'
]
// OR on error:
[
    'success' => false,
    'error' => string
]
```

**Implementation**:
- Generates embedding for content using OpenAI
- Checks for duplicates using content hash
- Stores in `agent_vector_memories` table with embedding
- Calculates embedding norm and token count

**Dependencies**:
- OpenAIService
- Database model: AgentVectorMemory

---

### File: `app/Agents/AutonomousResearchAgent.php` (508 lines)

This agent orchestrates the use of AgentToolbox in a plan-act-evaluate loop.

**Key Methods**:
- `startRun()`: Initialize a research run with objective and constraints
- `executeRun()`: Run the autonomous research loop
- `executeActions()`: Execute planned actions using toolbox
- `planNextStep()`: Plan the next research iteration
- `evaluateIteration()`: Assess what was learned
- `saveInsights()`: Save insights using `noteSave()`
- `synthesizeFinalOutput()`: Generate final research report

**Tools Used from Toolbox**:
1. `vectorSearch()` - Primary research method (lines 308)
2. `lawLookup()` - Law-specific queries (line 309)
3. `decisionLookup()` - Decision-specific queries (line 310)
4. `graphQuery()` - Relationship exploration (line 311)
5. `webFetch()` - External content retrieval (line 312)
6. `noteSave()` - Insight storage (line 396)

---

## 2. MCP Tools Inventory

### 2.1 CaseSearchTool (`app/Mcp/Tools/CaseSearchTool.php`)
**MCP Name**: `case.search`
**Type**: PRIVATE (requires authentication)

**Purpose**: Search legal cases and their documents using keyword search.

**Inputs**:
- `case_id` (string): Filter by specific case ID
- `case_number` (string): Filter by case number
- `query` (string): Free text search
- `client_name` (string): Filter by client name
- `opponent_name` (string): Filter by opponent name
- `court` (string): Filter by court name
- `jurisdiction` (string): Filter by jurisdiction
- `status` (string): Filter by case status
- `tags` (string): Comma-separated tags
- `search_documents` (bool): Search within case documents (default: true)
- `include_content` (bool): Include document content (default: false)
- `limit` (int): Results per page (default: 10, max: 100)
- `page` (int): Page number (default: 1)

**Implementation**: Direct database queries using LegalCase and CaseDocument models with LIKE searches.

---

### 2.2 LawSearchTool (`app/Mcp/Tools/LawSearchTool.php`)
**MCP Name**: `law.search`

**Purpose**: Search laws by query with optional filters using keyword search.

**Inputs**:
- `query` (string): Free text search for law title or content
- `doc_id` (string): Filter by specific document ID
- `law_number` (string): Filter by law number
- `jurisdiction` (string): Filter by jurisdiction
- `country` (string): Filter by country code
- `language` (string): Filter by language code
- `tags` (string): Comma-separated tags
- `limit` (int): Results per page (default: 10, max: 100)
- `page` (int): Page number (default: 1)

**Implementation**: Direct database queries using Law model with LIKE searches on title and content.

---

### 2.3 LawGetArticleTool (`app/Mcp/Tools/LawGetArticleTool.php`)
**MCP Name**: `law.get_article`

**Purpose**: Retrieve specific articles/chunks from a law by doc_id.

**Inputs**:
- `doc_id` (string, required): Document ID of the law
- `number` (int): Chunk index or article number (default: 0)
- `chapter` (string): Filter by chapter name/number
- `section` (string): Filter by section name/number

**Implementation**: Direct database queries using Law model filtered by doc_id and chunk_index.

---

### 2.4 DecisionSearchTool (`app/Mcp/Tools/DecisionSearchTool.php`)
**MCP Name**: `decision.search`

**Purpose**: Search court decisions by query with optional filters using keyword search.

**Inputs**:
- `query` (string): Free text search
- `case_number` (string): Filter by case number
- `court` (string): Filter by court name
- `jurisdiction` (string): Filter by jurisdiction
- `judge` (string): Filter by judge name
- `decision_type` (string): Filter by decision type
- `register` (string): Filter by court register
- `ecli` (string): Filter by ECLI identifier
- `finality` (string): Filter by finality status
- `tags` (string): Comma-separated tags
- `date_from` (string): Filter from date (YYYY-MM-DD)
- `date_to` (string): Filter to date (YYYY-MM-DD)
- `limit` (int): Results per page (default: 10, max: 100)
- `page` (int): Page number (default: 1)

**Implementation**: Direct database queries using CourtDecision model with LIKE searches.

---

### 2.5 DecisionGetTool (`app/Mcp/Tools/DecisionGetTool.php`)
**MCP Name**: `decision.get`

**Purpose**: Retrieve a specific court decision by ID with associated documents.

**Inputs**:
- `id` (string, required): Decision ID (ULID)
- `include_documents` (bool): Include associated documents (default: true)
- `include_content` (bool): Include full document content (default: false)

**Implementation**: Direct database queries using CourtDecision model with optional eager loading of documents.

---

## 3. App/Tools Inventory (Vizra ADK Wrappers)

These tools wrap external MCP services via `InternalMcpClient`:

### 3.1 OdlukeSearchTool
**Wraps**: `odluke-search` MCP tool
**Purpose**: Search decisions from external service (e.g., sudske-odluke.hr)

### 3.2 OdlukeMetaTool
**Wraps**: `odluke-meta` MCP tool
**Purpose**: Fetch metadata for decision IDs from external service

### 3.3 OdlukeDownloadTool
**Wraps**: `odluke-download` MCP tool
**Purpose**: Download decision documents (PDF/HTML) from external service

### 3.4 LawArticlesSearchTool
**Wraps**: `law-articles-search` MCP tool
**Purpose**: Search laws from external service

### 3.5 LawArticleByIdTool
**Wraps**: `law-article-by-id` MCP tool
**Purpose**: Fetch a specific law article by ID from external service

---

## 4. Duplication Analysis

### 4.1 Law Search - PARTIAL DUPLICATION

**Agent Tool**: `AgentToolbox::vectorSearch()` with `types=['laws']`
- **Method**: Semantic/vector similarity search
- **Use Case**: "Find laws related to X concept"
- **Technology**: OpenAI embeddings + pgvector
- **Location**: `app/Services/AgentToolbox.php:39-73`

**MCP Tool**: `LawSearchTool` (law.search)
- **Method**: Keyword LIKE search
- **Use Case**: "Find laws containing exact phrase X"
- **Technology**: SQL LIKE queries
- **Location**: `app/Mcp/Tools/LawSearchTool.php`

**Assessment**: **Complementary, not duplicate**. These serve different search paradigms:
- Vector search: Semantic understanding, "laws about privacy rights"
- Keyword search: Exact matching, "laws containing 'GDPR Article 5'"

**Recommendation**: Keep both, but unify under a consistent interface with a `search_type` parameter.

---

### 4.2 Law Lookup - OVERLAP

**Agent Tool**: `AgentToolbox::lawLookup()`
- Searches by law_number (LIKE match)
- Returns all chunks with full metadata
- Uses IngestedLaw model
- **Location**: `app/Services/AgentToolbox.php:247-281`

**MCP Tool**: `LawGetArticleTool` (law.get_article)
- Searches by doc_id (exact match)
- Returns specific chunks filtered by index/chapter/section
- Uses Law model
- **Location**: `app/Mcp/Tools/LawGetArticleTool.php`

**Assessment**: **Overlapping functionality with different interfaces**.
- AgentToolbox: Law number → all chunks
- MCP: doc_id → filtered chunks

**Recommendation**:
1. Rename `lawLookup()` → `lawLookupByNumber()`
2. Create new `lawLookupByDocId()` that calls LawGetArticleTool
3. Deprecate direct database access in favor of MCP tool

---

### 4.3 Decision Search - SIGNIFICANT DUPLICATION

**Agent Tool**: `AgentToolbox::vectorSearch()` with `types=['decisions']`
- **Method**: Semantic/vector similarity search
- **Use Case**: "Find decisions related to X concept"
- **Location**: `app/Services/AgentToolbox.php:68-69, 187-238`

**Agent Tool**: `AgentToolbox::decisionLookup()`
- **Method**: Criteria-based search (case_number, court, date, etc.)
- **Use Case**: "Find decisions by court X between dates Y-Z"
- **Location**: `app/Services/AgentToolbox.php:296-342`

**MCP Tool**: `DecisionSearchTool` (decision.search)
- **Method**: Keyword LIKE search + criteria filters
- **Use Case**: "Find decisions containing X with court Y"
- **Location**: `app/Mcp/Tools/DecisionSearchTool.php`

**Assessment**: **Significant duplication** between `decisionLookup()` and `DecisionSearchTool`.
- Both use criteria-based filtering
- Both query same table with similar logic
- DecisionSearchTool has more comprehensive filters (ECLI, finality, register)
- Vector search is complementary

**Recommendation**:
1. **Consolidate**: Refactor `decisionLookup()` to call `DecisionSearchTool` internally
2. **Enhance MCP tool**: Add `search_type` parameter ('keyword' | 'vector') to DecisionSearchTool
3. **Keep vector capability**: Add vector search to MCP tool or create separate `decision.vector_search` MCP tool

---

### 4.4 Decision Retrieval - MINIMAL OVERLAP

**MCP Tool**: `DecisionGetTool` (decision.get)
- **Method**: Get decision by ID
- **Location**: `app/Mcp/Tools/DecisionGetTool.php`

**Agent Tool**: None directly equivalent

**Assessment**: No duplication. Agent uses `decisionLookup()` to search, not retrieve by ID.

**Recommendation**: Agent should use `DecisionGetTool` when it has an ID.

---

### 4.5 Case Search - NO DUPLICATION

**MCP Tool**: `CaseSearchTool` (case.search)
- **Location**: `app/Mcp/Tools/CaseSearchTool.php`

**Agent Tool**: `AgentToolbox::vectorSearch()` with `types=['cases']`
- **Location**: `app/Services/AgentToolbox.php:63-64, 132-182`

**Assessment**:
- MCP tool searches LegalCase (private client cases)
- Agent tool searches CaseDocument (ingested public case documents)
- Different models, different use cases

**Recommendation**:
1. Add semantic search to CaseSearchTool for LegalCase
2. Consider renaming to clarify: `case.search` → `client_case.search` (private) vs `case_document.search` (public)

---

### 4.6 Unique Tools - NO DUPLICATION

**Agent Toolbox Only**:
- `graphQuery()` - Neo4j graph database queries (no MCP equivalent)
- `webFetch()` - HTTP client for external content (no MCP equivalent)
- `noteSave()` - Agent vector memory storage (no MCP equivalent)

**MCP Only**:
- External service wrappers (OdlukeSearch, OdlukeMeta, OdlukeDownload) - no agent equivalent

---

## 5. Architecture Issues

### 5.1 Direct Database Access vs. Tool Abstraction

**Problem**: AgentToolbox directly queries database models instead of using MCP tools.

**Impact**:
- Code duplication between Agent and MCP layers
- Inconsistent data access patterns
- Harder to maintain business logic in two places
- Cannot leverage MCP tool improvements automatically

**Example**:
```php
// AgentToolbox.php:298-326 (decisionLookup)
$query = CourtDecision::with('documents');
// ... complex filtering logic ...

// DecisionSearchTool.php:39-116 (handle method)
$query = CourtDecision::query();
// ... similar filtering logic ...
```

---

### 5.2 Missing Unified Search Interface

**Problem**: No unified interface combining vector and keyword search.

**Current State**:
- Vector search: AgentToolbox only
- Keyword search: MCP tools only
- Clients must choose one or the other

**Desired State**:
```php
// Unified search with both capabilities
$results = $searchTool->search('privacy rights', [
    'search_type' => 'hybrid',  // or 'vector', 'keyword'
    'jurisdiction' => 'HR',
]);
```

---

### 5.3 Inconsistent Response Formats

**Problem**: Different tools return different response structures.

**Examples**:
- AgentToolbox: Returns raw arrays/Eloquent collections
- MCP Tools: Returns ToolResult with JSON-encoded strings
- No standardized pagination format
- Different error handling patterns

---

### 5.4 Missing Abstraction Layer

**Problem**: Agent directly depends on AgentToolbox service instead of using tool registry.

**Current**:
```php
// AutonomousResearchAgent.php:308-312
$result = match ($tool) {
    'vector_search' => $this->toolbox->vectorSearch(...),
    'law_lookup' => $this->toolbox->lawLookup(...),
    // ... hardcoded tool mapping
};
```

**Desired**:
```php
// Use tool registry
$result = $this->tools->execute($toolName, $params);
```

---

## 6. Refactoring Plan

### Phase 1: Create Unified Search Infrastructure (Week 1)

#### Task 1.1: Create SearchService Interface
**File**: `app/Services/Contracts/SearchServiceInterface.php`

```php
interface SearchServiceInterface
{
    public function search(string $query, array $options): array;
}
```

**Options**:
- `search_type`: 'vector' | 'keyword' | 'hybrid'
- `entity_type`: 'laws' | 'decisions' | 'cases'
- `filters`: jurisdiction, date_range, etc.
- `limit`, `page`: pagination

---

#### Task 1.2: Create LawSearchService
**File**: `app/Services/LawSearchService.php`

**Purpose**: Unified law search combining vector + keyword capabilities.

**Methods**:
```php
public function vectorSearch(string $query, array $filters): array
public function keywordSearch(string $query, array $filters): array
public function hybridSearch(string $query, array $filters): array
public function lookupByNumber(string $lawNumber): array
public function lookupByDocId(string $docId, ?int $chunkIndex): array
```

**Implementation**:
- Move vector search logic from AgentToolbox
- Call LawSearchTool for keyword search
- Call LawGetArticleTool for doc_id lookup
- Implement hybrid search (vector + keyword combination)

---

#### Task 1.3: Create DecisionSearchService
**File**: `app/Services/DecisionSearchService.php`

**Purpose**: Unified decision search combining vector + keyword capabilities.

**Methods**:
```php
public function vectorSearch(string $query, array $filters): array
public function keywordSearch(string $query, array $filters): array
public function hybridSearch(string $query, array $filters): array
public function lookupByCriteria(array $criteria): array
public function getById(string $id, bool $includeContent): array
```

**Implementation**:
- Move vector search logic from AgentToolbox
- Call DecisionSearchTool for keyword/criteria search
- Call DecisionGetTool for ID-based retrieval
- Implement hybrid search

---

#### Task 1.4: Create CaseSearchService
**File**: `app/Services/CaseSearchService.php`

**Purpose**: Unified case search for both private cases and public case documents.

**Methods**:
```php
public function searchCases(string $query, array $filters): array  // LegalCase
public function searchDocuments(string $query, array $filters): array  // CaseDocument
public function vectorSearch(string $query, array $filters): array
```

---

### Phase 2: Update MCP Tools (Week 1-2)

#### Task 2.1: Enhance DecisionSearchTool
**Location**: `app/Mcp/Tools/DecisionSearchTool.php`

**Changes**:
1. Add `search_type` parameter: 'keyword' | 'vector'
2. Inject DecisionSearchService
3. Delegate to service layer instead of direct queries
4. Standardize response format

---

#### Task 2.2: Enhance LawSearchTool
**Location**: `app/Mcp/Tools/LawSearchTool.php`

**Changes**:
1. Add `search_type` parameter
2. Inject LawSearchService
3. Delegate to service layer
4. Add support for hybrid search

---

#### Task 2.3: Update CaseSearchTool
**Location**: `app/Mcp/Tools/CaseSearchTool.php`

**Changes**:
1. Add `search_type` parameter
2. Inject CaseSearchService
3. Support vector search for case documents

---

### Phase 3: Refactor AgentToolbox (Week 2)

#### Task 3.1: Refactor vectorSearch()
**Location**: `app/Services/AgentToolbox.php:39-73`

**Changes**:
```php
public function vectorSearch(string $query, array $options = []): array
{
    $results = [];
    $types = $options['types'] ?? ['laws', 'cases', 'decisions'];

    if (in_array('laws', $types)) {
        $results['laws'] = app(LawSearchService::class)
            ->vectorSearch($query, $options);
    }

    if (in_array('cases', $types)) {
        $results['cases'] = app(CaseSearchService::class)
            ->vectorSearch($query, $options);
    }

    if (in_array('decisions', $types)) {
        $results['decisions'] = app(DecisionSearchService::class)
            ->vectorSearch($query, $options);
    }

    return $results;
}
```

**Rationale**: Delegate to service layer, removing direct database access.

---

#### Task 3.2: Refactor lawLookup()
**Location**: `app/Services/AgentToolbox.php:247-281`

**Changes**:
```php
public function lawLookup(string $lawNumber, ?string $jurisdiction = null): array
{
    return app(LawSearchService::class)
        ->lookupByNumber($lawNumber, $jurisdiction);
}
```

**Deprecation Notice**: Add @deprecated tag, suggest using LawSearchService directly.

---

#### Task 3.3: Refactor decisionLookup()
**Location**: `app/Services/AgentToolbox.php:296-342`

**Changes**:
```php
public function decisionLookup(array $criteria): array
{
    return app(DecisionSearchService::class)
        ->lookupByCriteria($criteria);
}
```

**Deprecation Notice**: Add @deprecated tag, suggest using DecisionSearchService directly.

---

#### Task 3.4: Keep Unique Tools
**No changes**:
- `graphQuery()` - Unique functionality
- `webFetch()` - Unique functionality
- `noteSave()` - Unique functionality

---

### Phase 4: Update AutonomousResearchAgent (Week 2)

#### Task 4.1: Inject Search Services
**Location**: `app/Agents/AutonomousResearchAgent.php`

**Changes**:
```php
protected ?LawSearchService $lawSearch = null;
protected ?DecisionSearchService $decisionSearch = null;
protected ?CaseSearchService $caseSearch = null;

public function __construct()
{
    parent::__construct();
    $this->toolbox = app(AgentToolbox::class);
    $this->lawSearch = app(LawSearchService::class);
    $this->decisionSearch = app(DecisionSearchService::class);
    $this->caseSearch = app(CaseSearchService::class);
    // ...
}
```

---

#### Task 4.2: Update executeActions()
**Location**: `app/Agents/AutonomousResearchAgent.php:294-333`

**Changes**:
```php
protected function executeActions(array $actions, AgentRun $run): array
{
    $results = [];

    foreach ($actions as $action) {
        $tool = $action['tool'] ?? null;
        $params = $action['params'] ?? [];

        try {
            $result = match ($tool) {
                // Use new search services
                'law_vector_search' => $this->lawSearch->vectorSearch($params['query'], $params),
                'law_keyword_search' => $this->lawSearch->keywordSearch($params['query'], $params),
                'law_lookup' => $this->lawSearch->lookupByNumber($params['law_number'], $params['jurisdiction'] ?? null),

                'decision_vector_search' => $this->decisionSearch->vectorSearch($params['query'], $params),
                'decision_keyword_search' => $this->decisionSearch->keywordSearch($params['query'], $params),
                'decision_lookup' => $this->decisionSearch->lookupByCriteria($params),

                // Keep unique toolbox methods
                'graph_query' => $this->toolbox->graphQuery($params['cypher'], $params['parameters'] ?? []),
                'web_fetch' => $this->toolbox->webFetch($params['url'], $params),
                'note_save' => $this->toolbox->noteSave($this->name, $params['content'], $params),

                default => ['error' => "Unknown tool: {$tool}"],
            };

            // ... rest of method
        }
    }
}
```

---

### Phase 5: Testing & Validation (Week 3)

#### Task 5.1: Unit Tests
**Files to create**:
- `tests/Unit/Services/LawSearchServiceTest.php`
- `tests/Unit/Services/DecisionSearchServiceTest.php`
- `tests/Unit/Services/CaseSearchServiceTest.php`

**Coverage**:
- Vector search
- Keyword search
- Hybrid search
- Lookup methods
- Error handling

---

#### Task 5.2: Integration Tests
**Files to update**:
- `tests/Feature/Agents/AutonomousResearchAgentTest.php`
- `tests/Feature/Mcp/LawSearchToolTest.php`
- `tests/Feature/Mcp/DecisionSearchToolTest.php`

**Coverage**:
- Agent uses new services correctly
- MCP tools delegate to services
- End-to-end search workflows

---

#### Task 5.3: Backward Compatibility Tests
**Purpose**: Ensure existing code continues to work.

**Test**:
- AgentToolbox methods still work (even if deprecated)
- Existing agent runs complete successfully
- MCP tools return same structure

---

### Phase 6: Documentation & Migration (Week 3)

#### Task 6.1: Update Documentation
**Files to update**:
- `docs/ARCHITECTURE.md` - Add search service layer
- `docs/MCP_TOOLS.md` - Document new parameters
- `docs/AGENT_USAGE.md` - Update agent tool usage examples

---

#### Task 6.2: Migration Guide
**File**: `docs/MIGRATION_AGENT_TOOLBOX.md`

**Contents**:
- Overview of changes
- Old vs. new API comparison
- Migration timeline
- Code examples

---

#### Task 6.3: Deprecation Notices
**Files to update**:
- `app/Services/AgentToolbox.php` - Add @deprecated tags
- `CHANGELOG.md` - Document deprecations

---

## 7. Risk Assessment

### High Risk
**None identified**

### Medium Risk

**Risk**: Breaking existing agent runs in progress
- **Mitigation**: Keep AgentToolbox methods functional (delegate to services)
- **Testing**: Run full agent test suite before deployment

**Risk**: Performance degradation from added abstraction layer
- **Mitigation**: Benchmark search operations before/after
- **Testing**: Load test with typical query volumes

### Low Risk

**Risk**: MCP tool interface changes
- **Mitigation**: Make new parameters optional, maintain backward compatibility
- **Testing**: Regression test suite for all MCP tools

---

## 8. Success Criteria

### Functional Requirements
- [ ] All AgentToolbox methods continue to work
- [ ] MCP tools support both vector and keyword search
- [ ] Agent can use new search services
- [ ] No regression in search quality/relevance
- [ ] Backward compatibility maintained

### Non-Functional Requirements
- [ ] Search performance within 5% of baseline
- [ ] Code duplication reduced by >80%
- [ ] Test coverage >85% for new services
- [ ] Documentation complete and reviewed

### Architecture Requirements
- [ ] Single source of truth for search logic
- [ ] Clean separation: Controllers → MCP Tools → Services → Models
- [ ] No direct database access from AgentToolbox
- [ ] Unified response format across all search methods

---

## 9. Timeline

| Phase | Duration | Dependencies | Deliverables |
|-------|----------|--------------|--------------|
| Phase 1: Create Search Services | 3 days | None | LawSearchService, DecisionSearchService, CaseSearchService |
| Phase 2: Update MCP Tools | 2 days | Phase 1 | Updated MCP tools using services |
| Phase 3: Refactor AgentToolbox | 2 days | Phase 1 | Refactored AgentToolbox delegating to services |
| Phase 4: Update Agent | 1 day | Phase 3 | Updated AutonomousResearchAgent |
| Phase 5: Testing | 3 days | Phase 4 | Full test suite passing |
| Phase 6: Documentation | 2 days | Phase 5 | Docs and migration guide |
| **Total** | **13 days (~2.5 weeks)** | | |

---

## 10. Appendix

### A. Code Locations Reference

#### AgentToolbox Methods
| Method | Lines | Purpose |
|--------|-------|---------|
| vectorSearch() | 39-73 | Semantic search across all entities |
| searchLaws() | 78-127 | Vector search laws implementation |
| searchCases() | 132-182 | Vector search cases implementation |
| searchDecisions() | 187-238 | Vector search decisions implementation |
| lawLookup() | 247-281 | Lookup law by number |
| decisionLookup() | 296-342 | Lookup decisions by criteria |
| graphQuery() | 351-395 | Neo4j graph queries |
| webFetch() | 407-437 | HTTP client for external URLs |
| noteSave() | 451-525 | Save to agent vector memory |

#### MCP Tools
| Tool | File | Lines | Purpose |
|------|------|-------|---------|
| CaseSearchTool | app/Mcp/Tools/CaseSearchTool.php | 220 | Search private client cases |
| LawSearchTool | app/Mcp/Tools/LawSearchTool.php | 110 | Search laws by keyword |
| LawGetArticleTool | app/Mcp/Tools/LawGetArticleTool.php | 71 | Get law article by doc_id |
| DecisionSearchTool | app/Mcp/Tools/DecisionSearchTool.php | 136 | Search decisions by criteria |
| DecisionGetTool | app/Mcp/Tools/DecisionGetTool.php | 89 | Get decision by ID |

### B. Database Schema Reference

#### Tables Used by Agent Tools
- `laws` - Law chunks with embeddings
- `ingested_laws` - Law metadata and aggregation
- `cases_documents` - Case document chunks with embeddings (public)
- `cases` - Case metadata (private client cases)
- `court_decision_documents` - Decision document chunks with embeddings
- `court_decisions` - Decision metadata
- `agent_vector_memories` - Agent insights storage

#### Key Columns
- `embedding` (pgvector): Vector embeddings for semantic search
- `embedding_vector` (JSON): Fallback for non-PostgreSQL
- `doc_id`: Document identifier linking chunks
- `chunk_index`: Position of chunk within document

### C. Technology Stack

**Databases**:
- PostgreSQL with pgvector extension (primary)
- Neo4j graph database (for citations/relationships)

**AI/ML**:
- OpenAI Embeddings API (text-embedding-3-small or similar)
- OpenAI Chat API (gpt-4o-mini for agent planning)

**Laravel Packages**:
- Laravel MCP Server
- Vizra ADK (agent framework)

---

## 11. Approval

**Prepared by**: Claude (AI Legal War Machine Development Team)
**Date**: 2025-10-26
**Status**: Awaiting approval from Lead Developer

**Approval Required**:
- [ ] Lead Developer
- [ ] Tech Lead
- [ ] Product Owner (if architecture changes impact roadmap)

**Next Steps After Approval**:
1. Create GitHub issues for each phase
2. Assign tasks to sprint
3. Begin Phase 1 implementation
4. Schedule code review sessions

---

**End of Document**

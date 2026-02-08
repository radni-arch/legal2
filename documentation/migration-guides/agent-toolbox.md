# Migration Guide: AgentToolbox to Search Services

This guide helps you migrate from the legacy `AgentToolbox` API to the new unified search service architecture.

## Table of Contents

- [Overview](#overview)
- [Why Migrate?](#why-migrate)
- [API Comparison](#api-comparison)
- [Migration Examples](#migration-examples)
- [Deprecation Timeline](#deprecation-timeline)
- [Breaking Changes](#breaking-changes)
- [Best Practices](#best-practices)
- [Quick Reference](#quick-reference)

---

## Overview

The search functionality has been refactored from a monolithic `AgentToolbox` class into dedicated search services:

- **LawSearchService** - Law and statute search
- **DecisionSearchService** - Court decision search
- **CaseSearchService** - Legal case search

**Key Benefits:**
- ✅ Separation of concerns
- ✅ Granular search type control (vector, keyword, hybrid)
- ✅ Consistent interface across all search types
- ✅ Better testability and maintainability
- ✅ No circular dependencies

**Backward Compatibility:**
- ✅ Old `AgentToolbox` methods still work
- ⚠️ Deprecation warnings logged for old methods
- 🔄 Old methods delegate to new services internally

---

## Why Migrate?

### Problems with Old Architecture
1. **Code Duplication** - Same vector search logic repeated for laws, decisions, cases
2. **Limited Flexibility** - Couldn't choose search type (always vector or always keyword)
3. **Circular Dependencies** - Tools calling services calling tools
4. **Hard to Test** - Monolithic class with multiple responsibilities
5. **Hard to Extend** - Adding new search types required modifying multiple places

### Benefits of New Architecture
1. **Single Responsibility** - Each service handles one entity type
2. **Flexible Search Types** - Choose vector, keyword, or hybrid per request
3. **Clean Dependencies** - Services → Database, Tools → Services, Agents → Services
4. **Easy to Test** - Mock services independently
5. **Easy to Extend** - Add new search types in one place

---

## API Comparison

### Complete Migration Table

| Old API | New API | Status | Notes |
|---------|---------|--------|-------|
| `AgentToolbox::vectorSearch()` | `LawSearchService::vectorSearch()`<br>`DecisionSearchService::vectorSearch()`<br>`CaseSearchService::vectorSearch()` | **Deprecated** | Old method searches all types at once. New approach: call specific service for each type. |
| `AgentToolbox::lawLookup()` | `LawSearchService::lookupByNumber()` | **Deprecated** | Direct replacement, same parameters and return format. |
| `AgentToolbox::decisionLookup()` | `DecisionSearchService::lookupByCriteria()` | **Deprecated** | Direct replacement, same parameters and return format. |
| N/A (new) | `LawSearchService::keywordSearch()` | **New** | Keyword-only search for laws. |
| N/A (new) | `LawSearchService::hybridSearch()` | **New** | Combined vector + keyword search. |
| N/A (new) | `DecisionSearchService::keywordSearch()` | **New** | Keyword-only search for decisions. |
| N/A (new) | `DecisionSearchService::hybridSearch()` | **New** | Combined vector + keyword search. |
| N/A (new) | `DecisionSearchService::getById()` | **New** | Get decision by ID with content options. |
| N/A (new) | `CaseSearchService::searchCases()` | **New** | Search LegalCase model. |
| N/A (new) | `CaseSearchService::searchDocuments()` | **New** | Search CaseDocument model. |
| `AgentToolbox::graphQuery()` | `AgentToolbox::graphQuery()` | **Unchanged** | Neo4j queries remain in AgentToolbox (unique functionality). |
| `AgentToolbox::webFetch()` | `AgentToolbox::webFetch()` | **Unchanged** | HTTP client remains in AgentToolbox (unique functionality). |
| `AgentToolbox::noteSave()` | `AgentToolbox::noteSave()` | **Unchanged** | Agent memory remains in AgentToolbox (unique functionality). |

### Search Type Control

**Old Approach:**
```php
// No control over search type - always used vector search
$results = $toolbox->vectorSearch('criminal law', [
    'types' => ['laws', 'decisions', 'cases'],
    'limit' => 10
]);
```

**New Approach:**
```php
// Full control over search type per entity
$lawResults = $lawService->vectorSearch('criminal law', ['limit' => 10]);
$lawResults = $lawService->keywordSearch('NN 94/14', ['limit' => 10]);
$lawResults = $lawService->hybridSearch('criminal law', ['limit' => 10]);
```

---

## Migration Examples

### Example 1: Law Vector Search

**Before (Old API):**
```php
use App\Services\AgentToolbox;

class MyResearchService
{
    public function __construct(
        protected AgentToolbox $toolbox
    ) {}

    public function searchLaws(string $query): array
    {
        $results = $this->toolbox->vectorSearch($query, [
            'types' => ['laws'],
            'limit' => 10,
            'jurisdiction' => 'Croatia',
            'min_similarity' => 0.7
        ]);

        return $results['laws'] ?? [];
    }
}
```

**After (New API):**
```php
use App\Services\LawSearchService;

class MyResearchService
{
    public function __construct(
        protected LawSearchService $lawSearch
    ) {}

    public function searchLaws(string $query): array
    {
        $result = $this->lawSearch->vectorSearch($query, [
            'limit' => 10,
            'jurisdiction' => 'Croatia',
            'min_similarity' => 0.7
        ]);

        return $result['data'] ?? [];
    }
}
```

**Key Changes:**
- ✅ Inject `LawSearchService` instead of `AgentToolbox`
- ✅ Call `vectorSearch()` directly (no need to specify 'types')
- ✅ Results in `$result['data']` instead of `$results['laws']`
- ✅ Get additional metadata: `$result['success']`, `$result['search_type']`, `$result['count']`

### Example 2: Law Lookup by Number

**Before (Old API):**
```php
$law = $this->toolbox->lawLookup('NN 94/14', 'Croatia');

if (isset($law['error'])) {
    // Handle error
}

$docId = $law['doc_id'];
$chunks = $law['chunks'];
```

**After (New API):**
```php
$law = $this->lawSearch->lookupByNumber('NN 94/14', 'Croatia');

if (isset($law['error'])) {
    // Handle error
}

$docId = $law['doc_id'];
$chunks = $law['chunks'];
```

**Key Changes:**
- ✅ Exact same parameters and return format
- ✅ Direct replacement: `$toolbox->lawLookup()` → `$lawSearch->lookupByNumber()`

### Example 3: Decision Lookup by Criteria

**Before (Old API):**
```php
$decisions = $this->toolbox->decisionLookup([
    'case_number' => 'P-123/2024',
    'court' => 'Supreme Court',
    'from_date' => '2024-01-01',
    'to_date' => '2024-12-31',
    'limit' => 20
]);

foreach ($decisions as $decision) {
    echo $decision['title'];
}
```

**After (New API):**
```php
$decisions = $this->decisionSearch->lookupByCriteria([
    'case_number' => 'P-123/2024',
    'court' => 'Supreme Court',
    'from_date' => '2024-01-01',
    'to_date' => '2024-12-31',
    'limit' => 20
]);

foreach ($decisions as $decision) {
    echo $decision['title'];
}
```

**Key Changes:**
- ✅ Exact same parameters and return format
- ✅ Direct replacement: `$toolbox->decisionLookup()` → `$decisionSearch->lookupByCriteria()`

### Example 4: Multi-Type Vector Search

**Before (Old API):**
```php
// Search all types at once
$results = $this->toolbox->vectorSearch('contract dispute', [
    'types' => ['laws', 'decisions', 'cases'],
    'limit' => 10
]);

$laws = $results['laws'] ?? [];
$decisions = $results['decisions'] ?? [];
$cases = $results['cases'] ?? [];
```

**After (New API - Option 1: Sequential):**
```php
// Search each type separately
$lawResults = $this->lawSearch->vectorSearch('contract dispute', ['limit' => 10]);
$decisionResults = $this->decisionSearch->vectorSearch('contract dispute', ['limit' => 10]);
$caseResults = $this->caseSearch->vectorSearch('contract dispute', ['limit' => 10]);

$laws = $lawResults['data'] ?? [];
$decisions = $decisionResults['data'] ?? [];
$cases = $caseResults['data'] ?? [];
```

**After (New API - Option 2: Parallel with Promise/Async):**
```php
use Illuminate\Support\Facades\Promise;

// Execute searches in parallel
[$lawResults, $decisionResults, $caseResults] = Promise::all([
    fn() => $this->lawSearch->vectorSearch('contract dispute', ['limit' => 10]),
    fn() => $this->decisionSearch->vectorSearch('contract dispute', ['limit' => 10]),
    fn() => $this->caseSearch->vectorSearch('contract dispute', ['limit' => 10]),
])->wait();

$laws = $lawResults['data'] ?? [];
$decisions = $decisionResults['data'] ?? [];
$cases = $caseResults['data'] ?? [];
```

**Key Changes:**
- ✅ More explicit control over each search
- ✅ Can customize parameters per entity type
- ✅ Better for parallel execution
- ✅ Can choose different search types (vector, keyword, hybrid) per entity

### Example 5: Using New Hybrid Search

**Before (Old API):**
```php
// Only vector search was available
$results = $this->toolbox->vectorSearch('criminal procedure', [
    'types' => ['laws'],
    'limit' => 10
]);
```

**After (New API):**
```php
// Can now use hybrid search for better recall
$results = $this->lawSearch->hybridSearch('criminal procedure', [
    'limit' => 10,
    'jurisdiction' => 'Croatia'
]);

// Results include both vector and keyword matches
foreach ($results['data'] as $law) {
    echo $law['title'];
    echo " - Match type: " . $law['match_type']; // 'vector' or 'keyword'
    echo " - Score: " . $law['score']; // Similarity score
}
```

**Key Changes:**
- ✅ New `hybridSearch()` method combines vector + keyword
- ✅ Results include `match_type` and `score` fields
- ✅ Better recall for exploratory searches

### Example 6: Agent Tool Migration

**Before (Old Agent Tools):**
```php
// In AutonomousResearchAgent::executeActions()
$result = match ($tool) {
    'vector_search' => $this->toolbox->vectorSearch($params['query'], $params),
    'law_lookup' => $this->toolbox->lawLookup($params['law_number'], $params['jurisdiction'] ?? null),
    // ...
};
```

**After (New Agent Tools):**
```php
// In AutonomousResearchAgent::executeActions()
$result = match ($tool) {
    // Granular law search tools
    'law_vector_search' => $this->lawSearch->vectorSearch($params['query'], $params),
    'law_keyword_search' => $this->lawSearch->keywordSearch($params['query'], $params),
    'law_hybrid_search' => $this->lawSearch->hybridSearch($params['query'], $params),
    'law_lookup' => $this->lawSearch->lookupByNumber($params['law_number'], $params['jurisdiction'] ?? null),

    // Granular decision search tools
    'decision_vector_search' => $this->decisionSearch->vectorSearch($params['query'], $params),
    'decision_keyword_search' => $this->decisionSearch->keywordSearch($params['query'], $params),
    'decision_hybrid_search' => $this->decisionSearch->hybridSearch($params['query'], $params),

    // Granular case search tools
    'case_vector_search' => $this->caseSearch->vectorSearch($params['query'], $params),
    'case_search' => $this->caseSearch->searchCases($params['query'], $params),
    'case_document_search' => $this->caseSearch->searchDocuments($params['query'], $params),

    // Legacy support (still works)
    'vector_search' => $this->handleLegacyVectorSearch($params),
    // ...
};
```

**Key Changes:**
- ✅ More granular tool names (18 new tools)
- ✅ Each tool maps to specific service method
- ✅ Legacy `vector_search` still works (logs deprecation warning)

---

## Deprecation Timeline

### Current Status (v1.0.0+)

**✅ Fully Supported (New API):**
- `LawSearchService` - All methods
- `DecisionSearchService` - All methods
- `CaseSearchService` - All methods

**⚠️ Deprecated (Old API):**
- `AgentToolbox::vectorSearch()` - Logs deprecation warning
- `AgentToolbox::lawLookup()` - Logs deprecation warning
- `AgentToolbox::decisionLookup()` - Logs deprecation warning

**✅ Unchanged (Unique Functionality):**
- `AgentToolbox::graphQuery()` - Still in AgentToolbox
- `AgentToolbox::webFetch()` - Still in AgentToolbox
- `AgentToolbox::noteSave()` - Still in AgentToolbox

### Future Timeline

**Phase 1 (Current - v1.x.x):**
- ✅ New services fully functional
- ⚠️ Old methods work but log deprecation warnings
- 📝 Migration guide published

**Phase 2 (v2.0.0 - Estimated Q2 2025):**
- ⚠️ Deprecation warnings become more prominent
- 📝 All agent prompts updated to use new tools
- 📊 Usage metrics show migration progress

**Phase 3 (v3.0.0 - Estimated Q4 2025):**
- ❌ Deprecated methods removed from `AgentToolbox`
- 🔄 Must use search services directly
- 📝 Migration required for all code

### Migration Checklist

**Before v2.0.0:**
- [ ] Update all custom code to use new search services
- [ ] Update agent tool names in prompts
- [ ] Test all search functionality with new API
- [ ] Monitor deprecation warnings in logs

**Before v3.0.0:**
- [ ] Remove all references to deprecated AgentToolbox methods
- [ ] Ensure no deprecation warnings in logs
- [ ] Update any third-party integrations

---

## Breaking Changes

### None! (Backward Compatible)

**Good News:** This refactoring maintains **100% backward compatibility**.

✅ **All old code continues to work:**
- `AgentToolbox::vectorSearch()` still works (delegates internally)
- `AgentToolbox::lawLookup()` still works (delegates internally)
- `AgentToolbox::decisionLookup()` still works (delegates internally)

⚠️ **Only change:** Deprecation warnings are logged when using old methods.

### Deprecation Warning Format

When using deprecated methods, you'll see warnings like:

```
[WARNING] Agent using deprecated vector_search tool
{
  "agent": "autonomous_research_agent",
  "params": {"query": "criminal law", "types": ["laws"]}
}
```

Or:

```
[WARNING] Legacy vector_search tool used, migrate to specific search services
{
  "params": {"query": "criminal law"}
}
```

**Action Required:** Update code to use new search services to avoid future breaking changes.

---

## Best Practices

### 1. Choose the Right Search Type

**Vector Search** - Use for semantic/conceptual queries:
```php
// Good: Conceptual query
$results = $lawSearch->vectorSearch('employee termination procedures');

// Bad: Exact citation (use keyword instead)
$results = $lawSearch->vectorSearch('NN 94/14');
```

**Keyword Search** - Use for exact matches and citations:
```php
// Good: Exact citation
$results = $lawSearch->keywordSearch('NN 94/14');

// Good: Specific terms
$results = $lawSearch->keywordSearch('criminal', ['jurisdiction' => 'Croatia']);
```

**Hybrid Search** - Use for exploratory research:
```php
// Best of both: Finds both semantically similar AND exact matches
$results = $lawSearch->hybridSearch('employment contract termination');
```

### 2. Use Specific Services Instead of Multi-Type Search

**❌ Old Pattern (less efficient):**
```php
// Searches all types even if you only need laws
$results = $toolbox->vectorSearch($query, ['types' => ['laws']]);
```

**✅ New Pattern (more efficient):**
```php
// Only searches laws
$results = $lawSearch->vectorSearch($query);
```

### 3. Leverage Dependency Injection

**❌ Bad (using app() helper):**
```php
public function search($query)
{
    $lawService = app(LawSearchService::class);
    return $lawService->vectorSearch($query);
}
```

**✅ Good (constructor injection):**
```php
public function __construct(
    protected LawSearchService $lawSearch
) {}

public function search($query)
{
    return $this->lawSearch->vectorSearch($query);
}
```

### 4. Handle Results Consistently

**✅ Always check success flag:**
```php
$result = $lawSearch->vectorSearch($query);

if (!$result['success']) {
    // Handle error
    Log::error('Law search failed', ['error' => $result['error']]);
    return [];
}

return $result['data'];
```

### 5. Use Filters Effectively

**✅ Combine filters for precise results:**
```php
$result = $decisionSearch->keywordSearch('contract', [
    'jurisdiction' => 'Croatia',
    'court' => 'Supreme Court',
    'decision_type' => 'Judgment',
    'date_from' => '2024-01-01',
    'date_to' => '2024-12-31',
    'limit' => 20,
    'page' => 1
]);
```

---

## Quick Reference

### Service Injection

```php
use App\Services\LawSearchService;
use App\Services\DecisionSearchService;
use App\Services\CaseSearchService;

class MyClass
{
    public function __construct(
        protected LawSearchService $lawSearch,
        protected DecisionSearchService $decisionSearch,
        protected CaseSearchService $caseSearch
    ) {}
}
```

### Common Search Patterns

```php
// Vector search (semantic)
$result = $lawSearch->vectorSearch('employee rights', [
    'limit' => 10,
    'jurisdiction' => 'Croatia',
    'min_similarity' => 0.7
]);

// Keyword search (exact)
$result = $lawSearch->keywordSearch('NN 94/14', [
    'law_number' => 'NN 94/14',
    'jurisdiction' => 'Croatia'
]);

// Hybrid search (both)
$result = $lawSearch->hybridSearch('criminal procedure', [
    'limit' => 20,
    'jurisdiction' => 'Croatia'
]);

// Lookup by citation
$result = $lawSearch->lookupByNumber('NN 94/14', 'Croatia');

// Lookup by doc_id
$result = $lawSearch->lookupByDocId('law-123', 5, [
    'chapter' => 'Chapter 1'
]);
```

### Result Format

```php
// All search methods return:
[
    'success' => true,
    'data' => [...],           // Array of results
    'search_type' => 'vector', // 'vector', 'keyword', 'hybrid', 'cases', 'documents'
    'count' => 10,             // Number of results
    'pagination' => [...]      // Only for keyword/cases/documents searches
]

// Hybrid search results include:
[
    'doc_id' => 'law-123',
    'title' => 'Criminal Code',
    'match_type' => 'vector',  // 'vector' or 'keyword'
    'score' => 0.85,           // Similarity score (vector) or 0.5 (keyword)
    // ... other fields
]
```

---

## Support

### Documentation
- [ARCHITECTURE.md](ARCHITECTURE.md) - System architecture overview
- [API_SEARCH.md](API_SEARCH.md) - Search API reference
- [AUTONOMOUS_AGENT_README.md](AUTONOMOUS_AGENT_README.md) - Agent system

### Code Examples
- `tests/Unit/Services/` - Service unit tests
- `tests/Feature/Services/SearchIntegrationTest.php` - Integration examples
- `tests/Feature/Agents/AutonomousResearchAgentTest.php` - Agent examples

### Getting Help
- Check deprecation warnings in logs for specific migration paths
- Review test files for usage examples
- Consult ARCHITECTURE.md for design patterns

---

## Summary

**✅ Do This:**
1. Inject `LawSearchService`, `DecisionSearchService`, `CaseSearchService`
2. Use specific search methods: `vectorSearch()`, `keywordSearch()`, `hybridSearch()`
3. Choose the right search type for your use case
4. Handle results consistently (check `success` flag)
5. Monitor and eliminate deprecation warnings

**❌ Avoid This:**
1. Using `AgentToolbox` for search operations (deprecated)
2. Calling `app()` helper instead of constructor injection
3. Ignoring deprecation warnings
4. Using vector search for exact citations
5. Using keyword search for semantic queries

**The new search services provide better separation of concerns, more flexibility, and easier testing. Start migrating today to take advantage of these improvements!**

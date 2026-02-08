# TASK 1.1: Register Legal Tools in MCP Routes

**Sprint:** 1 - MCP Foundation
**Priority:** P0 - BLOCKER 🚨 CRITICAL
**Estimated Time:** 4 hours
**Complexity:** Low
**Status:** TODO

---

## **OBJECTIVE**

Expose all existing legal tools (`OdlukeSearchTool`, `LawArticlesSearchTool`, etc.) via MCP protocol so external systems (Claude Desktop, API clients) can access Croatian legal knowledge.

---

## **CURRENT STATE**

File: `routes/mcp.php`

**Problems:**
- Only 3 generic file search tools registered
- None of the 5 specialized legal tools in `/app/Tools/` are exposed
- MCP server exists but is not useful for legal research

**Existing Tools (NOT registered):**
- `app/Tools/LawArticlesSearchTool.php` ❌
- `app/Tools/LawArticleByIdTool.php` ❌
- `app/Tools/OdlukeSearchTool.php` ❌
- `app/Tools/OdlukeMetaTool.php` ❌
- `app/Tools/OdlukeDownloadTool.php` ❌

---

## **DESIRED STATE**

File: `routes/mcp.php` should register:

1. ✅ `law_search` - Search Croatian laws by content/number/title
2. ✅ `law_get_article` - Get specific law article by ID
3. ✅ `decision_search` - Search court decisions on odluke.sudovi.hr
4. ✅ `decision_get_metadata` - Get decision metadata
5. ✅ `decision_download` - Download decision PDF/HTML
6. ✅ `legal_search` - Unified search across all corpora

---

## **IMPLEMENTATION STEPS**

### **Step 1: Backup Current File**
```bash
cp routes/mcp.php routes/mcp.php.backup
```

### **Step 2: Clear Existing Generic Tools**
Remove the generic `search`, `fetch`, and `ask_support_agent` tools.

### **Step 3: Register Law Search Tool**
```php
use App\Tools\LawArticlesSearchTool;

Mcp::tool(function (string $query, ?string $law_number = null, ?string $title = null, int $limit = 10): array {
    $tool = app(LawArticlesSearchTool::class);
    return $tool->execute([
        'query' => $query,
        'law_number' => $law_number,
        'title' => $title,
        'limit' => $limit,
    ]);
})
    ->name('law_search')
    ->description('Search Croatian laws and legal articles by content, law number (e.g., "NN 94/14"), or title. Returns matching law articles with metadata.')
    ->inputSchema([
        'type' => 'object',
        'properties' => [
            'query' => [
                'type' => 'string',
                'description' => 'Search query for law content (e.g., "radni odnos", "ugovor o radu")'
            ],
            'law_number' => [
                'type' => 'string',
                'description' => 'Optional filter by law number (e.g., "NN 94/14", "149/09")'
            ],
            'title' => [
                'type' => 'string',
                'description' => 'Optional filter by law title (e.g., "Zakon o radu")'
            ],
            'limit' => [
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => 100,
                'default' => 10,
                'description' => 'Maximum number of results to return'
            ],
        ],
        'required' => ['query'],
    ]);
```

### **Step 4: Register Law Article Retrieval Tool**
```php
use App\Tools\LawArticleByIdTool;

Mcp::tool(function (int $id): array {
    $tool = app(LawArticleByIdTool::class);
    return $tool->execute(['id' => $id]);
})
    ->name('law_get_article')
    ->description('Get full content of a specific law article by its database ID. Use this after law_search to retrieve complete article text.')
    ->inputSchema([
        'type' => 'object',
        'properties' => [
            'id' => [
                'type' => 'integer',
                'description' => 'Database ID of the law article (obtained from law_search results)'
            ],
        ],
        'required' => ['id'],
    ]);
```

### **Step 5: Register Court Decision Search Tool**
```php
use App\Tools\OdlukeSearchTool;

Mcp::tool(function (string $query, ?string $base_url = null, int $limit = 10, int $page = 1): array {
    $tool = app(OdlukeSearchTool::class);
    return $tool->execute([
        'q' => $query,
        'base_url' => $base_url,
        'limit' => $limit,
        'page' => $page,
    ]);
})
    ->name('decision_search')
    ->description('Search Croatian court decisions on odluke.sudovi.hr database. Returns decision IDs and basic metadata. Use decision_get_metadata for full details.')
    ->inputSchema([
        'type' => 'object',
        'properties' => [
            'query' => [
                'type' => 'string',
                'description' => 'Search query for court decisions (e.g., "radni spor", "otkaz ugovora")'
            ],
            'base_url' => [
                'type' => 'string',
                'description' => 'Optional custom base URL for the court decision database'
            ],
            'limit' => [
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => 100,
                'default' => 10,
                'description' => 'Results per page'
            ],
            'page' => [
                'type' => 'integer',
                'minimum' => 1,
                'default' => 1,
                'description' => 'Page number for pagination'
            ],
        ],
        'required' => ['query'],
    ]);
```

### **Step 6: Register Decision Metadata Tool**
```php
use App\Tools\OdlukeMetaTool;

Mcp::tool(function ($id = null, ?array $ids = null): array {
    $tool = app(OdlukeMetaTool::class);
    return $tool->execute([
        'id' => $id,
        'ids' => $ids,
    ]);
})
    ->name('decision_get_metadata')
    ->description('Get detailed metadata for one or more court decisions including court, judge, date, case number, and ECLI.')
    ->inputSchema([
        'type' => 'object',
        'properties' => [
            'id' => [
                'type' => ['string', 'integer'],
                'description' => 'Single decision ID from odluke.sudovi.hr'
            ],
            'ids' => [
                'type' => 'array',
                'items' => ['type' => ['string', 'integer']],
                'description' => 'Array of decision IDs for batch retrieval'
            ],
        ],
        // Note: Either id OR ids required, but not both
    ]);
```

### **Step 7: Register Decision Download Tool**
```php
use App\Tools\OdlukeDownloadTool;

Mcp::tool(function (string $id, string $format = 'pdf', bool $save = false): array {
    $tool = app(OdlukeDownloadTool::class);
    return $tool->execute([
        'id' => $id,
        'format' => $format,
        'save' => $save,
    ]);
})
    ->name('decision_download')
    ->description('Download a court decision in PDF or HTML format. Can optionally save to local storage.')
    ->inputSchema([
        'type' => 'object',
        'properties' => [
            'id' => [
                'type' => 'string',
                'description' => 'Decision ID from odluke.sudovi.hr'
            ],
            'format' => [
                'type' => 'string',
                'enum' => ['pdf', 'html', 'both'],
                'default' => 'pdf',
                'description' => 'Download format'
            ],
            'save' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'Whether to save the file to local storage'
            ],
        ],
        'required' => ['id'],
    ]);
```

### **Step 8: Register Unified Legal Search Tool**
```php
Mcp::tool(function (string $query, ?array $corpora = null, ?string $jurisdiction = null, int $limit = 10): array {
    $searchService = app(\App\Services\UnifiedSearchService::class);

    $results = $searchService->search($query, [
        'corpora' => $corpora ?? ['laws', 'decisions', 'cases'],
        'filters' => [
            'jurisdiction' => $jurisdiction,
        ],
        'limit' => $limit,
    ]);

    return $results;
})
    ->name('legal_search')
    ->description('Unified hybrid search across all legal corpora (laws, court decisions, case documents) using vector similarity + keyword matching + citation detection.')
    ->inputSchema([
        'type' => 'object',
        'properties' => [
            'query' => [
                'type' => 'string',
                'description' => 'Legal research query (e.g., "What are the grounds for termination of employment?")'
            ],
            'corpora' => [
                'type' => 'array',
                'items' => ['type' => 'string', 'enum' => ['laws', 'decisions', 'cases']],
                'description' => 'Which legal corpora to search. Defaults to all.',
                'default' => ['laws', 'decisions', 'cases']
            ],
            'jurisdiction' => [
                'type' => 'string',
                'description' => 'Filter by jurisdiction code (e.g., "HR" for Croatia)'
            ],
            'limit' => [
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => 50,
                'default' => 10,
                'description' => 'Maximum total results to return'
            ],
        ],
        'required' => ['query'],
    ]);
```

### **Step 9: Verify Tool Registration**
Add at the end of the file:
```php
// Log all registered tools for debugging
if (app()->environment('local')) {
    \Log::info('MCP Tools Registered', [
        'tools' => [
            'law_search',
            'law_get_article',
            'decision_search',
            'decision_get_metadata',
            'decision_download',
            'legal_search',
        ],
    ]);
}
```

---

## **TESTING**

### **Test 1: Law Search**
```bash
curl -X POST http://localhost/mcp/message \
  -H "Authorization: Bearer ${MCP_API_TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{
    "method": "tools/call",
    "params": {
      "name": "law_search",
      "arguments": {
        "query": "ugovor o radu",
        "limit": 5
      }
    }
  }'
```

**Expected Response:**
```json
{
  "content": [
    {
      "type": "text",
      "text": "{\"success\": true, \"results\": [{\"id\": 123, \"title\": \"Zakon o radu\", \"law_number\": \"NN 149/09\", ...}]}"
    }
  ]
}
```

### **Test 2: Law Article Retrieval**
```bash
curl -X POST http://localhost/mcp/message \
  -H "Authorization: Bearer ${MCP_API_TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{
    "method": "tools/call",
    "params": {
      "name": "law_get_article",
      "arguments": {
        "id": 123
      }
    }
  }'
```

### **Test 3: Court Decision Search**
```bash
curl -X POST http://localhost/mcp/message \
  -H "Authorization: Bearer ${MCP_API_TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{
    "method": "tools/call",
    "params": {
      "name": "decision_search",
      "arguments": {
        "query": "radni spor",
        "limit": 10,
        "page": 1
      }
    }
  }'
```

### **Test 4: Decision Metadata**
```bash
curl -X POST http://localhost/mcp/message \
  -H "Authorization: Bearer ${MCP_API_TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{
    "method": "tools/call",
    "params": {
      "name": "decision_get_metadata",
      "arguments": {
        "id": "decision-id-from-search"
      }
    }
  }'
```

### **Test 5: Unified Legal Search**
```bash
curl -X POST http://localhost/mcp/message \
  -H "Authorization: Bearer ${MCP_API_TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{
    "method": "tools/call",
    "params": {
      "name": "legal_search",
      "arguments": {
        "query": "otkaz ugovora o radu bez otkaznog roka",
        "corpora": ["laws", "decisions"],
        "limit": 10
      }
    }
  }'
```

---

## **ACCEPTANCE CRITERIA**

- [ ] All 6 tools registered in `routes/mcp.php`
- [ ] Each tool has clear `name`, `description`, and `inputSchema`
- [ ] Input schemas include proper validation (types, required fields, enums)
- [ ] Tools use correct tool classes from `/app/Tools/`
- [ ] Rate limiting configured (60 req/min from existing config)
- [ ] All 5 curl tests pass successfully
- [ ] No breaking changes to existing functionality
- [ ] Generic file tools removed (search, fetch)

---

## **DOCUMENTATION REQUIRED**

Create: `docs/mcp/TOOL_REGISTRATION.md`

**Required Sections:**
```markdown
# MCP Tool Registration Guide

## Overview
Brief explanation of what MCP is and why we use it.

## Registered Tools

### 1. law_search
- **Purpose:** Search Croatian laws
- **Parameters:**
  - query (required): Search query
  - law_number (optional): Law number filter
  - title (optional): Title filter
  - limit (optional): Max results (default: 10)
- **Example Request:**
  [curl example]
- **Example Response:**
  [JSON response]

### 2. law_get_article
[Same structure]

### 3. decision_search
[Same structure]

### 4. decision_get_metadata
[Same structure]

### 5. decision_download
[Same structure]

### 6. legal_search
[Same structure]

## Authentication
How to get and use MCP_API_TOKEN

## Rate Limiting
60 requests per minute per token

## Error Handling
Common errors and how to handle them

## Testing
How to test tools via curl/Postman

## Integration Examples
- Claude Desktop configuration
- API client examples
```

---

## **FILES TO MODIFY**

1. `routes/mcp.php` - **PRIMARY FILE**
2. `docs/mcp/TOOL_REGISTRATION.md` - **NEW DOCUMENTATION**

---

## **DEPENDENCIES**

None - This task has no dependencies and should be completed first.

---

## **POTENTIAL ISSUES & SOLUTIONS**

| Issue | Solution |
|-------|----------|
| Tool class not found | Check namespace imports at top of file |
| Schema validation fails | Ensure inputSchema follows JSON Schema spec |
| Rate limiting too strict | Adjust in `config/mcp.php` |
| MCP_API_TOKEN missing | Generate token in `.env` file |
| Tool returns error | Check tool execute() method signature |

---

## **COMPLETION CHECKLIST**

- [ ] Code implemented and tested
- [ ] All 5 curl tests pass
- [ ] Documentation file created
- [ ] Examples in docs are working
- [ ] No console errors
- [ ] Reviewed by peer/self
- [ ] Committed with message: "feat: Register legal tools in MCP routes [TASK-1.1]"

---

**Last Updated:** 2025-10-26
**Status:** Ready for implementation
**Assigned To:** [Coding Agent]

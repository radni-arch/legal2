# MCP Tools Documentation

## Overview

The Legal Database MCP Server provides a Model Context Protocol (MCP) interface for accessing Croatian legal data including laws, court decisions, and legal cases. This document describes all available tools, their signatures, parameters, and usage examples.

## Server Information

- **Server Name**: Legal Database MCP Server
- **Version**: 2.0.0
- **Purpose**: Search and retrieval of legal data for AI agents and external systems

## Registration System

The MCP server supports two access methods with separate registration systems:

### 1. HTTP MCP (Web/API Access)

**Location**: `routes/mcp.php`
**Protocol**: HTTP REST endpoints
**Package**: `php-mcp/laravel`
**Usage**: External API clients, webhooks, HTTP-based integrations

Tools are registered using the `Mcp::tool()` facade and expose HTTP endpoints. This registration uses the `OdlukeTools` class methods:

```php
Mcp::tool(function (string $query, ...) {
    $tools = app(OdlukeTools::class);
    return $tools->searchLawArticles($query, ...);
})->name('law_search')->description('...');
```

**Registered Tools**:
- `law_search` - Search Croatian laws
- `law_get_article` - Get specific law article by ID
- `decision_search` - Search court decisions
- `decision_get_metadata` - Get decision metadata
- `decision_download` - Download decision PDF/HTML
- `legal_search` - Unified hybrid search across all corpora

### 2. Stdio MCP (Claude Desktop)

**Location**: `app/Mcp/Servers/OdlukeServer.php`
**Protocol**: Standard input/output (stdio)
**Package**: `laravel/boost`
**Command**: `php artisan boost:mcp`
**Usage**: Claude Desktop app, AI agents via stdio

Tools are declared in the `OdlukeServer` class using dedicated tool classes following the Laravel Boost pattern:

```php
public array $tools = [
    OdlukeSearchTool::class,
    OdlukeMetaTool::class,
    OdlukeDownloadTool::class,
    LawSearchTool::class,
    LawGetArticleTool::class,
    DecisionSearchTool::class,
    DecisionGetTool::class,
    CaseSearchTool::class,
];
```

### Architecture Decision

We maintain **two separate registration systems** because they serve different purposes:

1. **HTTP MCP** (`routes/mcp.php`): For web-based access, API integrations, and HTTP clients
2. **Stdio MCP** (`OdlukeServer.php`): For AI agents like Claude Desktop that communicate via stdio

### Previous Implementation (Removed)

In October 2025, we removed `McpOdlukeServiceProvider` which was redundantly registering the same tools that `routes/mcp.php` already handles for HTTP MCP. This eliminated duplicate registrations while preserving both HTTP and stdio access methods.

## Authentication

All MCP tools support optional API token authentication for rate limiting and access control.

### Configuration

Set the following environment variables:

```env
# Enable/disable authentication
MCP_AUTH_ENABLED=true

# API token for authentication
MCP_API_TOKEN=your-secure-token-here

# Token header name (default: X-MCP-Token)
MCP_TOKEN_HEADER=X-MCP-Token
```

### Usage

When making requests through MCP clients that support HTTP headers, include the authentication token:

```
X-MCP-Token: your-secure-token-here
```

## Rate Limiting

Rate limiting is configured per tool and globally to prevent abuse.

### Configuration

```env
# Enable/disable rate limiting
MCP_RATE_LIMIT_ENABLED=true

# Global limits
MCP_RATE_LIMIT_PER_MINUTE=60
MCP_RATE_LIMIT_PER_HOUR=1000

# Per-tool limits (requests per minute)
MCP_RATE_LAW_SEARCH=30
MCP_RATE_LAW_GET=60
MCP_RATE_DECISION_SEARCH=30
MCP_RATE_DECISION_GET=60
MCP_RATE_CASE_SEARCH=20
```

### Rate Limit Headers

Responses include rate limit information:

- Global limit: 60 requests per minute, 1000 per hour
- Per-tool limits vary (see configuration above)

## Available Tools

### 1. Law Search (`law.search`)

Search laws by query with optional filters. **Now supports vector, keyword, and hybrid search modes** for advanced semantic and exact matching.

#### Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `query` | string | No | Free text search query for law title or content |
| `search_type` | string | No | Search type: `keyword` (default), `vector`, or `hybrid` - see [Advanced Search Types](#advanced-search-types) |
| `doc_id` | string | No | Filter by specific document ID |
| `law_number` | string | No | Filter by specific law number |
| `jurisdiction` | string | No | Filter by jurisdiction (e.g., "federal", "state") |
| `country` | string | No | Filter by country code (e.g., "HR", "US") |
| `language` | string | No | Filter by language code (e.g., "hr", "en") |
| `tags` | string | No | Comma-separated tags to filter by |
| `limit` | integer | No | Number of results per page (1-100, default: 10) |
| `page` | integer | No | Page number for pagination (default: 1) |

#### Response Shape

```json
{
  "success": true,
  "data": [
    {
      "id": "01HQXXX...",
      "doc_id": "nn_123_2023",
      "title": "Zakon o radu",
      "law_number": "93/14",
      "jurisdiction": "national",
      "country": "HR",
      "language": "hr",
      "promulgation_date": "2023-01-15",
      "effective_date": "2023-02-01",
      "repeal_date": null,
      "tags": ["labor", "employment"],
      "source_url": "https://zakon.hr/z/xxx",
      "chunk_index": 0
    }
  ],
  "pagination": {
    "total": 145,
    "page": 1,
    "limit": 10,
    "pages": 15
  }
}
```

#### Example Usage

```javascript
// Search for labor laws
{
  "tool": "law.search",
  "arguments": {
    "query": "radno pravo",
    "country": "HR",
    "language": "hr",
    "limit": 20
  }
}

// Find specific law by number
{
  "tool": "law.search",
  "arguments": {
    "law_number": "93/14",
    "country": "HR"
  }
}
```

---

### 2. Get Law Article (`law.get_article`)

Retrieve a specific article or chunk from a law by doc_id and optional filters.

#### Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `doc_id` | string | Yes | Document ID of the law |
| `number` | integer | No | Chunk index or article number to retrieve (default: all chunks) |
| `chapter` | string | No | Filter by chapter name/number |
| `section` | string | No | Filter by section name/number |

#### Response Shape

```json
{
  "success": true,
  "doc_id": "nn_123_2023",
  "total_chunks": 5,
  "articles": [
    {
      "id": "01HQXXX...",
      "doc_id": "nn_123_2023",
      "title": "Zakon o radu",
      "law_number": "93/14",
      "chapter": "Glava I",
      "section": "Članak 1",
      "chunk_index": 0,
      "content": "Full text of the article...",
      "metadata": {
        "article_number": "1",
        "heading": "Predmet zakona"
      },
      "source_url": "https://zakon.hr/z/xxx"
    }
  ]
}
```

#### Example Usage

```javascript
// Get all chunks of a law
{
  "tool": "law.get_article",
  "arguments": {
    "doc_id": "nn_93_2014"
  }
}

// Get specific chunk by index
{
  "tool": "law.get_article",
  "arguments": {
    "doc_id": "nn_93_2014",
    "number": 5
  }
}

// Get articles from a specific chapter
{
  "tool": "law.get_article",
  "arguments": {
    "doc_id": "nn_93_2014",
    "chapter": "Glava III"
  }
}
```

---

### 3. Search Court Decisions (`decision.search`)

Search court decisions by query with optional filters. **Now supports vector, keyword, and hybrid search modes** for advanced semantic and exact matching.

#### Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `query` | string | No | Free text search query for decision title, description, or case number |
| `search_type` | string | No | Search type: `keyword` (default), `vector`, or `hybrid` - see [Advanced Search Types](#advanced-search-types) |
| `case_number` | string | No | Filter by specific case number |
| `court` | string | No | Filter by court name |
| `jurisdiction` | string | No | Filter by jurisdiction |
| `judge` | string | No | Filter by judge name |
| `decision_type` | string | No | Filter by decision type (e.g., "Presuda", "Rješenje") |
| `register` | string | No | Filter by court register |
| `ecli` | string | No | Filter by ECLI identifier |
| `finality` | string | No | Filter by finality status |
| `tags` | string | No | Comma-separated tags to filter by |
| `date_from` | string | No | Filter decisions from this date (YYYY-MM-DD) |
| `date_to` | string | No | Filter decisions until this date (YYYY-MM-DD) |
| `limit` | integer | No | Number of results per page (1-100, default: 10) |
| `page` | integer | No | Page number for pagination (default: 1) |

#### Response Shape

```json
{
  "success": true,
  "data": [
    {
      "id": "01HQYYY...",
      "case_number": "Gž-1234/2023",
      "title": "Odluka u predmetu...",
      "court": "Vrhovni sud Republike Hrvatske",
      "jurisdiction": "civil",
      "judge": "Ivan Horvat",
      "decision_date": "2023-05-15",
      "publication_date": "2023-06-01",
      "decision_type": "Presuda",
      "register": "Gž",
      "finality": "final",
      "ecli": "ECLI:HR:VSRH:2023:Gž.1234",
      "tags": ["labor", "dismissal"]
    }
  ],
  "pagination": {
    "total": 89,
    "page": 1,
    "limit": 10,
    "pages": 9
  }
}
```

#### Example Usage

```javascript
// Search decisions by query
{
  "tool": "decision.search",
  "arguments": {
    "query": "nezakonit otkaz",
    "court": "Vrhovni sud",
    "limit": 20
  }
}

// Find decisions by date range
{
  "tool": "decision.search",
  "arguments": {
    "decision_type": "Presuda",
    "date_from": "2023-01-01",
    "date_to": "2023-12-31",
    "tags": "labor"
  }
}

// Find by ECLI
{
  "tool": "decision.search",
  "arguments": {
    "ecli": "ECLI:HR:VSRH:2023:Gž.1234"
  }
}
```

---

### 4. Get Court Decision (`decision.get`)

Retrieve a specific court decision by ID with its associated documents and content.

#### Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | string | Yes | Decision ID (ULID) |
| `include_documents` | boolean | No | Include associated documents (default: true) |
| `include_content` | boolean | No | Include full document content (default: false, can be large) |

#### Response Shape

```json
{
  "success": true,
  "decision": {
    "id": "01HQYYY...",
    "case_number": "Gž-1234/2023",
    "title": "Odluka u predmetu...",
    "court": "Vrhovni sud Republike Hrvatske",
    "jurisdiction": "civil",
    "judge": "Ivan Horvat",
    "decision_date": "2023-05-15",
    "publication_date": "2023-06-01",
    "decision_type": "Presuda",
    "register": "Gž",
    "finality": "final",
    "ecli": "ECLI:HR:VSRH:2023:Gž.1234",
    "tags": ["labor", "dismissal"],
    "description": "Detailed description...",
    "created_at": "2023-06-01T10:00:00+00:00",
    "updated_at": "2023-06-01T10:00:00+00:00"
  },
  "documents": {
    "total_chunks": 3,
    "items": [
      {
        "id": "01HQZZZ...",
        "decision_id": "01HQYYY...",
        "doc_id": "decision_doc_123",
        "title": "Presuda - stranica 1",
        "category": "judgment",
        "author": "Vrhovni sud",
        "language": "hr",
        "tags": ["main"],
        "chunk_index": 0,
        "metadata": {
          "page": 1
        },
        "source": "upload",
        "source_id": "upload_xxx"
      }
    ]
  }
}
```

#### Example Usage

```javascript
// Get decision without content
{
  "tool": "decision.get",
  "arguments": {
    "id": "01HQYYY123456789",
    "include_documents": true,
    "include_content": false
  }
}

// Get decision with full content
{
  "tool": "decision.get",
  "arguments": {
    "id": "01HQYYY123456789",
    "include_documents": true,
    "include_content": true
  }
}
```

---

### 5. Search Cases (`case.search`) [PRIVATE]

**⚠️ PRIVATE TOOL**: Requires authentication. Search legal cases and their documents. **Now supports vector, keyword, and hybrid search modes** plus separate case vs document search.

#### Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `query` | string | No | Free text search within case documents and metadata |
| `search_type` | string | No | Search type: `cases` (default), `vector`, `documents`, or `keyword` - see [Advanced Search Types](#advanced-search-types) |
| `case_id` | string | No | Filter by specific case ID (ULID) |
| `case_number` | string | No | Filter by case number |
| `client_name` | string | No | Filter by client name |
| `opponent_name` | string | No | Filter by opponent name |
| `court` | string | No | Filter by court name |
| `jurisdiction` | string | No | Filter by jurisdiction |
| `status` | string | No | Filter by case status |
| `tags` | string | No | Comma-separated tags to filter by |
| `search_documents` | boolean | No | **Deprecated**: Use `search_type: "documents"` instead. Legacy flag for backward compatibility. |
| `include_content` | boolean | No | Include document content in results (default: false) |
| `limit` | integer | No | Number of results per page (1-100, default: 10) |
| `page` | integer | No | Page number for pagination (default: 1) |

#### Response Shape

**When searching cases:**

```json
{
  "success": true,
  "search_type": "cases",
  "data": [
    {
      "id": "01HQZZZ...",
      "case_number": "P-123/2023",
      "title": "Marković vs. Company Ltd.",
      "client_name": "Marko Marković",
      "opponent_name": "Company Ltd.",
      "court": "Općinski sud u Zagrebu",
      "jurisdiction": "civil",
      "judge": "Ana Horvat",
      "filing_date": "2023-03-15",
      "status": "active",
      "tags": ["labor", "dispute"]
    }
  ],
  "pagination": {
    "total": 25,
    "page": 1,
    "limit": 10,
    "pages": 3
  }
}
```

**When searching documents:**

```json
{
  "success": true,
  "search_type": "documents",
  "data": [
    {
      "id": "01HQAAA...",
      "case_id": "01HQZZZ...",
      "doc_id": "case_doc_456",
      "title": "Tužba",
      "category": "complaint",
      "author": "Attorney Name",
      "language": "hr",
      "tags": ["initial"],
      "chunk_index": 0,
      "metadata": {},
      "source": "upload",
      "case": {
        "id": "01HQZZZ...",
        "case_number": "P-123/2023",
        "title": "Marković vs. Company Ltd."
      }
    }
  ],
  "pagination": {
    "total": 42,
    "page": 1,
    "limit": 10,
    "pages": 5
  }
}
```

#### Example Usage

```javascript
// Search cases by client
{
  "tool": "case.search",
  "arguments": {
    "client_name": "Marković",
    "status": "active"
  }
}

// Search within case documents
{
  "tool": "case.search",
  "arguments": {
    "case_id": "01HQZZZ123456789",
    "query": "ugovor o radu",
    "search_documents": true,
    "include_content": false
  }
}

// Get all documents for a case
{
  "tool": "case.search",
  "arguments": {
    "case_id": "01HQZZZ123456789",
    "search_documents": true,
    "limit": 100
  }
}
```

---

## Advanced Search Types

The search tools (`law.search`, `decision.search`, `case.search`) now support multiple search strategies via the `search_type` parameter, enabling both semantic similarity and exact matching.

### Search Type Options

| Search Type | Available On | Description | Best For |
|-------------|--------------|-------------|----------|
| `keyword` | law.search, decision.search, case.search | Traditional text matching using LIKE queries | Exact citations, specific terms, known law numbers |
| `vector` | law.search, decision.search, case.search | Semantic similarity using AI embeddings (OpenAI) | Conceptual queries, topic exploration, similar cases |
| `hybrid` | law.search, decision.search | **New!** Combines vector + keyword search | Exploratory research, comprehensive results |
| `cases` | case.search only | Search LegalCase records (default) | Finding cases by parties, court, status |
| `documents` | case.search only | Search CaseDocument records | Finding specific case documents and filings |

### Vector Search

Vector search uses OpenAI embeddings to find semantically similar documents, even when exact words don't match.

**How it works:**
1. Query text is converted to a 1536-dimension embedding vector
2. PostgreSQL pgvector extension finds nearest neighbors using cosine similarity
3. Results ranked by similarity score (0-1, higher is better)

**Example: Find laws about employee termination (semantic)**

```javascript
{
  "tool": "law.search",
  "arguments": {
    "query": "nezakonit otkaz zaposlenika", // "unlawful employee termination"
    "search_type": "vector",
    "jurisdiction": "Croatia",
    "limit": 10
  }
}
```

**Response includes similarity scores:**

```json
{
  "success": true,
  "search_type": "vector",
  "data": [
    {
      "doc_id": "nn_93_2014",
      "title": "Zakon o radu",
      "similarity": 0.89,
      "content": "... otkaz ugovora o radu ..."
    },
    {
      "doc_id": "nn_125_2011",
      "title": "Kazneni zakon",
      "similarity": 0.76,
      "content": "... pravni status zaposlenika ..."
    }
  ],
  "count": 10
}
```

**Note:** Similarity threshold is 0.7 by default (configurable). Lower scores are filtered out.

### Keyword Search

Traditional text-based search using SQL LIKE queries for exact or partial text matching.

**How it works:**
1. Searches title, content, and metadata fields
2. Case-insensitive partial matching (LIKE '%query%')
3. Supports filters (jurisdiction, dates, tags, etc.)
4. Returns results with pagination

**Example: Find specific law by citation (exact)**

```javascript
{
  "tool": "law.search",
  "arguments": {
    "query": "93/14",
    "search_type": "keyword", // Can omit - keyword is default
    "country": "HR",
    "limit": 10
  }
}
```

**Response with pagination:**

```json
{
  "success": true,
  "search_type": "keyword",
  "data": [
    {
      "doc_id": "nn_93_2014",
      "title": "Zakon o radu",
      "law_number": "93/14"
    }
  ],
  "pagination": {
    "total": 1,
    "page": 1,
    "limit": 10,
    "pages": 1
  }
}
```

### Hybrid Search

**New Feature!** Combines vector and keyword search for maximum recall and precision.

**How it works:**
1. Executes both vector and keyword searches in parallel
2. Merges results and removes duplicates by `doc_id`
3. Each result tagged with `match_type` ('vector' or 'keyword')
4. Results sorted by score (vector similarity or 0.5 for keyword matches)

**Example: Comprehensive search for employment contracts**

```javascript
{
  "tool": "decision.search",
  "arguments": {
    "query": "ugovor o radu radni odnos", // "employment contract labor relations"
    "search_type": "hybrid",
    "jurisdiction": "Croatia",
    "limit": 20
  }
}
```

**Response includes both match types:**

```json
{
  "success": true,
  "search_type": "hybrid",
  "data": [
    {
      "id": "01HQ001...",
      "case_number": "Gž-1234/2023",
      "title": "Odluka o radnom sporu",
      "match_type": "vector",
      "score": 0.87,
      "court": "Vrhovni sud"
    },
    {
      "id": "01HQ002...",
      "case_number": "Gž-2345/2023",
      "title": "Ugovor o radu - poništenje",
      "match_type": "keyword",
      "score": 0.5,
      "court": "Županijski sud"
    },
    {
      "id": "01HQ003...",
      "case_number": "Gž-3456/2023",
      "title": "Radni odnos - prestanak",
      "match_type": "vector",
      "score": 0.82,
      "court": "Vrhovni sud"
    }
  ],
  "count": 3
}
```

**Benefits of Hybrid:**
- ✅ Finds both exact matches AND semantically similar results
- ✅ Better recall than single method alone
- ✅ Useful for exploratory legal research
- ⚠️ Slower than single search type (runs both)

### Case Search Types

Case search has additional search types specific to legal case management:

**`cases` (default)**: Search LegalCase records

```javascript
{
  "tool": "case.search",
  "arguments": {
    "query": "Marković",
    "search_type": "cases", // Default, can omit
    "status": "active",
    "limit": 10
  }
}
```

**`documents`**: Search CaseDocument records with case relationships

```javascript
{
  "tool": "case.search",
  "arguments": {
    "query": "tužba",
    "search_type": "documents",
    "case_id": "01HQZZZ123456789",
    "include_content": false,
    "limit": 20
  }
}
```

**`vector`**: Semantic search across case documents with embeddings

```javascript
{
  "tool": "case.search",
  "arguments": {
    "query": "neispunjenje ugovorne obveze", // "breach of contractual obligation"
    "search_type": "vector",
    "jurisdiction": "civil",
    "limit": 10
  }
}
```

### Search Type Comparison

**Use Vector Search when:**
- 🔍 Exploring a topic or concept
- 📚 Finding similar cases or precedents
- 💡 Query uses natural language, not exact terms
- 🌐 Searching across multiple languages
- ❓ Don't know exact legal terminology

**Use Keyword Search when:**
- 🎯 Looking for exact citation (e.g., "NN 93/14")
- 📋 Searching for specific terms or phrases
- ⚖️ Need exact text matches
- 🏷️ Using tags or metadata filters
- ⏱️ Need fastest response time

**Use Hybrid Search when:**
- 🔬 Conducting comprehensive research
- 📊 Need both exact and related results
- 🧪 Exploring new legal area
- 🔄 Want maximum coverage
- ⚠️ Can tolerate slightly slower response

### Performance Considerations

| Search Type | Response Time | Database Load | Best For |
|-------------|---------------|---------------|----------|
| Keyword | Fast (~50-200ms) | Low | Exact searches |
| Vector | Medium (~200-500ms) | Medium | Semantic searches |
| Hybrid | Slower (~300-700ms) | High | Comprehensive research |

**Tip:** Start with keyword search for known terms, use vector for exploration, and hybrid for comprehensive analysis.

---

## Error Handling

All tools return consistent error responses:

```json
{
  "success": false,
  "error": "Error message describing what went wrong"
}
```

### Common Errors

| Status Code | Error | Description |
|-------------|-------|-------------|
| 401 | Unauthorized | Missing or invalid API token |
| 403 | Forbidden | Insufficient permissions (e.g., private tool access) |
| 404 | Not Found | Requested resource does not exist |
| 429 | Too Many Requests | Rate limit exceeded |
| 500 | Internal Server Error | Server error occurred |

## Pagination Best Practices

1. **Start Small**: Use default page size (10) for initial queries
2. **Increase as Needed**: Increase limit for bulk operations (max 100)
3. **Check Total**: Use `pagination.total` to determine if more pages exist
4. **Sequential Access**: Access pages sequentially for best performance

## Response Size Optimization

To minimize response payload size:

1. **Use Filters**: Narrow down results with specific filters
2. **Exclude Content**: Set `include_content: false` when full text is not needed
3. **Paginate**: Use smaller page sizes (10-20) instead of fetching all results
4. **Select Fields**: Tools return minimal payloads by default

## Integration Examples

### Python Example

```python
import anthropic

client = anthropic.Anthropic()

# Search laws
response = client.messages.create(
    model="claude-3-5-sonnet-20241022",
    max_tokens=1024,
    tools=[
        {
            "type": "custom",
            "name": "law.search",
            "description": "Search laws",
            # ... tool definition
        }
    ],
    messages=[
        {
            "role": "user",
            "content": "Find Croatian labor laws"
        }
    ]
)
```

### JavaScript Example

```javascript
import Anthropic from '@anthropic-ai/sdk';

const client = new Anthropic();

const response = await client.messages.create({
  model: 'claude-3-5-sonnet-20241022',
  max_tokens: 1024,
  tools: [
    {
      type: 'custom',
      name: 'law.search',
      description: 'Search laws',
      // ... tool definition
    }
  ],
  messages: [
    {
      role: 'user',
      content: 'Find Croatian labor laws'
    }
  ]
});
```

## Configuration Reference

### Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `MCP_AUTH_ENABLED` | `true` | Enable/disable authentication |
| `MCP_API_TOKEN` | - | API token for authentication |
| `MCP_TOKEN_HEADER` | `X-MCP-Token` | Header name for token |
| `MCP_RATE_LIMIT_ENABLED` | `true` | Enable/disable rate limiting |
| `MCP_RATE_LIMIT_PER_MINUTE` | `60` | Global requests per minute |
| `MCP_RATE_LIMIT_PER_HOUR` | `1000` | Global requests per hour |
| `MCP_RATE_LAW_SEARCH` | `30` | law.search requests per minute |
| `MCP_RATE_LAW_GET` | `60` | law.get_article requests per minute |
| `MCP_RATE_DECISION_SEARCH` | `30` | decision.search requests per minute |
| `MCP_RATE_DECISION_GET` | `60` | decision.get requests per minute |
| `MCP_RATE_CASE_SEARCH` | `20` | case.search requests per minute |
| `MCP_MAX_PAGE_SIZE` | `100` | Maximum page size |
| `MCP_DEFAULT_PAGE_SIZE` | `10` | Default page size |

## Support

For issues, questions, or feature requests, please contact the development team or file an issue in the project repository.

## Version History

- **v2.1.0** (2025-10-26): Added `search_type` parameter for vector, keyword, and hybrid search support on all search tools
- **v2.0.0** (2024-10-24): Added law, decision, and case tools with authentication and rate limiting
- **v1.0.0**: Initial Odluke API tools

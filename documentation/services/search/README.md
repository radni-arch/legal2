# Unified Search System Documentation

## Table of Contents
1. [Overview](#overview)
2. [Architecture](#architecture)
3. [Components](#components)
4. [Data Flow](#data-flow)
5. [Search Modes](#search-modes)
6. [API Endpoints](#api-endpoints)
7. [Web UI Features](#web-ui-features)
8. [Integration Points](#integration-points)
9. [Configuration](#configuration)
10. [Usage Examples](#usage-examples)
11. [Performance Considerations](#performance-considerations)
12. [Troubleshooting](#troubleshooting)

---

## Overview

The Unified Search System provides a comprehensive search solution across multiple Croatian legal document corpora:
- **Laws** (zakoni) - Legal statutes and regulations
- **Court Decisions** (sudske odluke) - Judicial rulings and case law
- **Case Documents** (dokumenti predmeta) - Case-specific documentation

### Key Features
- ✅ **Vector Semantic Search** - AI-powered semantic understanding using OpenAI embeddings
- ✅ **Hybrid Search** - Combines vector, full-text (PostgreSQL FTS), and citation matching using Reciprocal Rank Fusion (RRF)
- ✅ **Citation-Aware Search** - Enhanced results with citation context
- ✅ **Advanced Filtering** - Filter by jurisdiction, court, date range, language, etc.
- ✅ **Corpus Weighting** - Adjust relevance weights per document type
- ✅ **Deduplication** - Intelligent result deduplication by content hash
- ✅ **Caching** - 5-minute response cache for frequently-used queries
- ✅ **Rate Limiting** - 60 requests per minute protection
- ✅ **Web UI** - User-friendly Livewire interface for manual testing
- ✅ **API Access** - RESTful JSON API for programmatic access
- ✅ **MCP Integration** - Model Context Protocol support for AI agents

---

## Architecture

### System Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                        CLIENT LAYER                              │
├─────────────┬─────────────────┬──────────────────┬──────────────┤
│  Web UI     │   REST API      │   MCP Protocol   │   Agents     │
│ (Livewire)  │ (HTTP/JSON)     │   (Stdio/SSE)    │  (Vizra ADK) │
└─────┬───────┴────────┬────────┴────────┬─────────┴──────┬───────┘
      │                │                 │                │
      └────────────────┼─────────────────┴────────────────┘
                       │
          ┌────────────▼───────────────┐
          │   SearchController         │
          │  (Rate Limiting, Caching)  │
          └────────────┬───────────────┘
                       │
          ┌────────────▼────────────────┐
          │  UnifiedSearchService       │
          │  • Vector Search            │
          │  • Hybrid Search (RRF)      │
          │  • Full-Text Search (FTS)   │
          │  • Citation Search          │
          │  • Filtering & Sorting      │
          │  • Deduplication            │
          └─────┬──────────────┬────────┘
                │              │
      ┌─────────▼────┐    ┌───▼─────────────────┐
      │  OpenAI      │    │   PostgreSQL        │
      │  Embeddings  │    │  • pgvector         │
      │  API         │    │  • Full-Text Search │
      └──────────────┘    │  • Vector Similarity│
                          └─────────────────────┘
                          ┌─────────────────────┐
                          │  Data Tables        │
                          │  • laws             │
                          │  • court_decisions  │
                          │  • cases_documents  │
                          └─────────────────────┘
```

### Technology Stack
- **Backend**: Laravel 12, PHP 8.2+
- **Frontend**: Livewire 3, Blade Templates, Tailwind CSS
- **Database**: PostgreSQL with pgvector extension
- **AI/ML**: OpenAI Embeddings API (text-embedding-3-small)
- **Caching**: Laravel Cache (Redis/File)
- **Rate Limiting**: Laravel Throttle Middleware

---

## Components

### 1. Backend Components

#### `UnifiedSearchService` (app/Services/UnifiedSearchService.php)
Core search logic orchestrator.

**Responsibilities:**
- Generate query embeddings via OpenAI
- Execute vector searches across multiple corpora
- Perform full-text searches using PostgreSQL `tsvector`
- Detect and search by legal citations
- Apply Reciprocal Rank Fusion (RRF) for hybrid search
- Handle filtering, sorting, pagination
- Deduplicate results by content hash

**Key Methods:**
```php
search(string $query, array $options): array
hybridSearch(string $query, array $options): array
searchWithCitations(string $query, array $options): array
```

#### `SearchController` (app/Http/Controllers/SearchController.php)
HTTP API endpoint handler.

**Responsibilities:**
- Request validation using FormRequest classes
- Rate limiting (60 req/min)
- Response caching (5 min TTL)
- Error handling and logging
- Request ID tracking

**Endpoints:**
- `POST /api/search` - Unified vector search
- `POST /api/search/laws` - Laws-only search
- `POST /api/search/decisions` - Decisions-only search
- `POST /api/search/cases` - Cases-only search
- `POST /api/search/hybrid` - Hybrid (vector + FTS + citations)
- `POST /api/search/with-citations` - Citation-aware search

#### `UnifiedSearchRequest` (app/Http/Requests/UnifiedSearchRequest.php)
Request validation rules.

**Validated Parameters:**
- `query` (required, 2-1000 chars)
- `corpora` (array: laws, decisions, cases)
- `weights` (numeric: 0-10 per corpus)
- `filters` (jurisdiction, country, court, date_from, date_to, etc.)
- `threshold` (float: 0.0-1.0)
- `limit` (int: 1-100)
- `page` (int: 1+)
- `sort_by` (score | date)
- `sort_order` (asc | desc)
- `deduplicate` (boolean)

#### `HrLegalCitationsDetector` (app/Services/HrLegalCitationsDetector.php)
Detects Croatian legal citations in text.

**Supported Patterns:**
- Official Gazette: `NN 123/20`, `NN br. 123/20`
- Article references: `čl. 15`, `članak 15`
- Law citations: `Zakon o ...`
- ECLI identifiers: `ECLI:HR:VSRH:2020:...`

### 2. Frontend Components

#### `UnifiedSearch` Livewire Component (app/Http/Livewire/UnifiedSearch.php)
Interactive web interface for manual search testing.

**Features:**
- Real-time search with loading states
- Advanced options (filters, weights, sorting)
- Grouped results by document type
- Pagination with page numbers
- Export results as JSON
- Keyboard shortcuts (Ctrl+K, Escape)
- URL query string persistence
- Validation error display

**Public Properties:**
```php
string $query
array $corpora = ['laws', 'decisions', 'cases']
float $threshold = 0.7
int $limit = 10
string $searchMode = 'unified'
array $weights = ['laws' => 1.0, 'decisions' => 1.0, 'cases' => 1.0]
array $filters = []
```

#### Blade View (resources/views/livewire/unified-search.blade.php)
User interface template with:
- Search form with mode selector
- Collapsible advanced options
- Corpus selection toggles
- Similarity threshold slider
- Corpus weight sliders
- Advanced filters (date range, jurisdiction, court, etc.)
- Grouped results display
- Pagination controls
- Performance metrics display

### 3. Database Schema

#### Laws Table
```sql
CREATE TABLE laws (
    id UUID PRIMARY KEY,
    doc_id VARCHAR(255),
    title TEXT,
    law_number VARCHAR(100),
    jurisdiction VARCHAR(50),
    country VARCHAR(50),
    language VARCHAR(16),
    content TEXT,
    embedding VECTOR(1536),  -- OpenAI embedding
    content_tsv TSVECTOR,     -- Full-text search
    content_hash VARCHAR(64), -- SHA256 for deduplication
    chunk_index INT,
    promulgation_date DATE,
    effective_date DATE,
    metadata JSONB
);

CREATE INDEX ON laws USING ivfflat (embedding vector_cosine_ops);
CREATE INDEX ON laws USING gin (content_tsv);
```

#### Court Decision Documents Table
```sql
CREATE TABLE court_decision_documents (
    id UUID PRIMARY KEY,
    decision_id UUID REFERENCES court_decisions(id),
    content TEXT,
    embedding VECTOR(1536),
    content_tsv TSVECTOR,
    content_hash VARCHAR(64),
    chunk_index INT,
    metadata JSONB
);
```

#### Cases Documents Table
```sql
CREATE TABLE cases_documents (
    id UUID PRIMARY KEY,
    case_id UUID,
    doc_id VARCHAR(255),
    title TEXT,
    category VARCHAR(100),
    language VARCHAR(16),
    content TEXT,
    embedding VECTOR(1536),
    content_tsv TSVECTOR,
    content_hash VARCHAR(64),
    chunk_index INT,
    source VARCHAR(255),
    metadata JSONB
);
```

---

## Data Flow

### 1. Vector Search Flow

```
┌──────────┐
│  Query   │
└────┬─────┘
     │
     ▼
┌──────────────────┐
│  Generate        │
│  Embedding       │──► OpenAI API (text-embedding-3-small)
└────┬─────────────┘
     │
     ▼
┌──────────────────┐
│  PostgreSQL      │
│  Vector Search   │──► SELECT ... WHERE 1 - (embedding <=> $query_vec) >= $threshold
└────┬─────────────┘     ORDER BY similarity DESC LIMIT $limit
     │
     ▼
┌──────────────────┐
│  Normalize       │
│  Results         │──► Add type, title, snippet, score, metadata
└────┬─────────────┘
     │
     ▼
┌──────────────────┐
│  Apply Filters   │──► jurisdiction, court, date_from, date_to, etc.
└────┬─────────────┘
     │
     ▼
┌──────────────────┐
│  Deduplicate     │──► By content_hash (keep highest score)
└────┬─────────────┘
     │
     ▼
┌──────────────────┐
│  Sort & Paginate │──► By score (default) or date
└────┬─────────────┘
     │
     ▼
┌──────────────────┐
│  Return Results  │
└──────────────────┘
```

### 2. Hybrid Search Flow (RRF)

```
┌──────────┐
│  Query   │
└────┬─────┘
     │
     ├─────────────────┬─────────────────┬─────────────────┐
     ▼                 ▼                 ▼                 ▼
┌──────────┐     ┌──────────┐     ┌──────────┐     ┌──────────┐
│ Vector   │     │ Full-Text│     │ Citation │     │ Weights  │
│ Search   │     │ Search   │     │ Search   │     │ Config   │
└────┬─────┘     └────┬─────┘     └────┬─────┘     └────┬─────┘
     │                │                │                │
     │                │                │                │
     └────────────────┴────────────────┴────────────────┘
                             │
                             ▼
                  ┌─────────────────────┐
                  │ Reciprocal Rank     │
                  │ Fusion (RRF)        │
                  │                     │
                  │ score(doc) = Σ      │
                  │   weight / (k+rank) │
                  │                     │
                  │ k = 60 (constant)   │
                  │ weights:            │
                  │   vector:   60%     │
                  │   fulltext: 30%     │
                  │   citation: 10%     │
                  └─────────┬───────────┘
                            │
                            ▼
                  ┌─────────────────────┐
                  │  Merge & Score      │
                  │  Deduplicate        │
                  │  Sort by RRF Score  │
                  └─────────┬───────────┘
                            │
                            ▼
                  ┌─────────────────────┐
                  │  Return Results     │
                  └─────────────────────┘
```

### 3. Full-Text Search Flow

```
┌──────────┐
│  Query   │
└────┬─────┘
     │
     ▼
┌──────────────────┐
│  Tokenize &      │
│  Build tsquery   │──► "pravo privatnost" → "pravo | privatnost"
└────┬─────────────┘
     │
     ▼
┌──────────────────┐
│  PostgreSQL      │
│  FTS Query       │──► SELECT ... WHERE content_tsv @@ to_tsquery('simple', $tsquery)
└────┬─────────────┘     ORDER BY ts_rank(content_tsv, $tsquery) DESC
     │
     ▼
┌──────────────────┐
│  Return Results  │
└──────────────────┘
```

### 4. Citation Search Flow

```
┌──────────┐
│  Query   │
└────┬─────┘
     │
     ▼
┌──────────────────────┐
│  Detect Citations    │
│  (HrLegalCitations   │──► Extract: NN 123/20, čl. 15, ECLI:...
│   Detector)          │
└────┬─────────────────┘
     │
     ▼
┌──────────────────────┐
│  Build Search        │
│  Patterns            │──► Convert to ILIKE patterns
└────┬─────────────────┘
     │
     ▼
┌──────────────────────┐
│  PostgreSQL          │
│  Pattern Match       │──► SELECT ... WHERE content ILIKE '%NN 123/20%'
└────┬─────────────────┘     OR content ILIKE '%čl. 15%'
     │
     ▼
┌──────────────────────┐
│  Score by Match      │──► count(matches) / count(patterns)
│  Count               │
└────┬─────────────────┘
     │
     ▼
┌──────────────────────┐
│  Return Results      │
└──────────────────────┘
```

---

## Search Modes

### 1. Unified (Vector) Search
**Endpoint:** `POST /api/search`

**Description:** Pure semantic vector search using OpenAI embeddings and pgvector cosine similarity.

**Best For:**
- Natural language queries
- Conceptual searches
- Cross-lingual semantic matching

**Example:**
```json
{
  "query": "What are the privacy rights in digital environments?",
  "corpora": ["laws", "decisions"],
  "threshold": 0.75,
  "limit": 10
}
```

### 2. Hybrid Search
**Endpoint:** `POST /api/search/hybrid`

**Description:** Combines three search methods using Reciprocal Rank Fusion:
- **Vector Search (60%)** - Semantic understanding
- **Full-Text Search (30%)** - Keyword matching
- **Citation Search (10%)** - Legal reference matching

**Best For:**
- Queries with both concepts and specific terms
- Queries containing legal citations
- Maximum recall across different search types

**RRF Formula:**
```
final_score(document) =
    0.60 × (1 / (60 + vector_rank)) +
    0.30 × (1 / (60 + fulltext_rank)) +
    0.10 × (1 / (60 + citation_rank))
```

**Example:**
```json
{
  "query": "pravo na privatnost NN 123/20",
  "corpora": ["laws", "decisions", "cases"],
  "threshold": 0.7,
  "limit": 20
}
```

### 3. Citation-Aware Search
**Endpoint:** `POST /api/search/with-citations`

**Description:** Enhanced vector search with citation graph context (future: will include GraphRagService integration).

**Best For:**
- Finding related laws and cases
- Understanding citation networks
- Tracing legal precedents

### 4. Corpus-Specific Search
**Endpoints:**
- `POST /api/search/laws` - Laws only
- `POST /api/search/decisions` - Court decisions only
- `POST /api/search/cases` - Case documents only

**Description:** Restricts search to a single document corpus.

**Best For:**
- Targeted searches within one document type
- Performance optimization when corpus is known

---

## API Endpoints

### Common Request Structure

```json
{
  "query": "search query text",
  "corpora": ["laws", "decisions", "cases"],
  "weights": {
    "laws": 1.0,
    "decisions": 1.0,
    "cases": 1.0
  },
  "filters": {
    "jurisdiction": "HR",
    "country": "Croatia",
    "court": "Vrhovni sud",
    "language": "hr",
    "date_from": "2020-01-01",
    "date_to": "2024-12-31"
  },
  "threshold": 0.7,
  "limit": 10,
  "page": 1,
  "per_page": 10,
  "sort_by": "score",
  "sort_order": "desc",
  "deduplicate": true
}
```

### Common Response Structure

```json
{
  "success": true,
  "data": {
    "query": "pravo na privatnost",
    "total_results": 127,
    "returned_results": 10,
    "deduplicated_count": 3,
    "corpora": ["laws", "decisions"],
    "results": [
      {
        "type": "law",
        "id": "uuid",
        "title": "Zakon o zaštiti osobnih podataka",
        "snippet": "Svaka osoba ima pravo na zaštitu osobnih...",
        "score": 0.8942,
        "metadata": {
          "doc_id": "law-123",
          "law_number": "NN 123/20",
          "jurisdiction": "HR",
          "country": "Croatia",
          "language": "hr",
          "content_hash": "sha256...",
          "chunk_index": 0,
          "promulgation_date": "2020-06-15",
          "effective_date": "2020-07-01",
          "article_number": "15"
        }
      }
    ],
    "filters": {...},
    "pagination": {
      "page": 1,
      "per_page": 10,
      "total_pages": 13,
      "offset": 0
    },
    "performance": {
      "total_time": 1.234,
      "embedding_time": 0.234,
      "corpus_timing": {
        "laws": 0.456,
        "decisions": 0.544
      }
    }
  },
  "request_id": "search_uuid",
  "response_time_ms": 1234.56,
  "cached": false
}
```

### Error Response

```json
{
  "success": false,
  "error": "Validation error message or system error",
  "request_id": "search_uuid"
}
```

### Rate Limiting

- **Limit:** 60 requests per minute per IP
- **Header:** `X-RateLimit-Limit`, `X-RateLimit-Remaining`
- **Status:** `429 Too Many Requests` when exceeded

### Caching

- **TTL:** 5 minutes (300 seconds)
- **Key:** `search:{md5(json_encode($parameters))}`
- **Header:** `cached: true` in response
- **Performance:** Cached responses return in ~10ms vs ~1000ms

---

## Web UI Features

### Access
- **URL:** `/search`
- **Route:** `route('unified.search')`
- **Dashboard:** Purple "Unified Search" tile (first card)

### Interface Components

#### 1. Search Bar
- Text input with real-time validation
- Supports queries up to 1000 characters
- Autofocus on page load
- Validation error display

#### 2. Search Mode Selector
- Unified (Vector)
- Hybrid (Vector + Keyword)
- With Citations
- Laws Only
- Decisions Only
- Cases Only

#### 3. Advanced Options Panel (Collapsible)
- **Corpora Selection:** Multi-select toggles for laws/decisions/cases
- **Similarity Threshold:** Slider (0.0 - 1.0)
- **Results Per Page:** Dropdown (5, 10, 20, 50, 100)
- **Sort Options:** By score or date, ascending/descending
- **Deduplication:** Checkbox toggle

#### 4. Corpus Weights Panel (For Unified/Hybrid modes)
- Laws weight slider (0.0 - 5.0)
- Decisions weight slider (0.0 - 5.0)
- Cases weight slider (0.0 - 5.0)
- Real-time value display

#### 5. Advanced Filters Panel
- **Jurisdiction:** Text input (e.g., "HR", "RS")
- **Country:** Text input (e.g., "Croatia")
- **Court:** Text input (e.g., "Vrhovni sud")
- **Language:** Text input (e.g., "hr", "en")
- **Date From:** Date picker
- **Date To:** Date picker (validated: must be >= date_from)

#### 6. Results Display
- **Grouped by Type:** Separate sections for laws, decisions, cases
- **Result Card Contents:**
  - Document title
  - Type-specific metadata
  - Content snippet (truncated to ~200 chars)
  - Relevance score (as percentage)
  - Source indicators (for hybrid search)
  - Chunk information
  - Content hash preview
  - Raw score and corpus weight (if applicable)

#### 7. Pagination
- Previous/Next buttons
- Page numbers with ellipsis for large result sets
- Current page highlight
- Total pages display
- Available at top and bottom of results

#### 8. Performance Metrics
- Total results found
- Duplicate count (if deduplication enabled)
- Response time in milliseconds
- Cache status indicator
- Request ID for debugging
- Search type label
- Source result counts (for hybrid search)

#### 9. Actions
- **Search Button:** Submit query
- **Clear Button:** Reset search and results
- **Reset Filters:** Restore default settings
- **Export Button:** Download results as JSON
- **Copy URL:** (Future: Copy shareable search URL)

### Keyboard Shortcuts

| Shortcut | Action |
|----------|--------|
| `Ctrl+K` or `Cmd+K` | Focus search input |
| `Escape` | Clear search (when input focused) |
| `Enter` | Submit search form |

### UI States

1. **Initial State** - Welcome message with usage examples
2. **Loading State** - Spinner with "Searching..." message
3. **Results State** - Grouped results with pagination
4. **Empty State** - "No results found" with suggestions
5. **Error State** - Red banner with error message

### Responsive Design
- Mobile-friendly responsive layout
- Touch-optimized controls
- Collapsible sections to save space
- Flexible grid for results

---

## Integration Points

### 1. REST API Integration

```bash
curl -X POST https://example.com/api/search \
  -H "Content-Type: application/json" \
  -d '{
    "query": "pravo na privatnost",
    "corpora": ["laws", "decisions"],
    "threshold": 0.75,
    "limit": 10
  }'
```

**Response Handling:**
```javascript
fetch('/api/search', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    query: 'your search query',
    corpora: ['laws', 'decisions', 'cases'],
    threshold: 0.7,
    limit: 10
  })
})
.then(res => res.json())
.then(data => {
  if (data.success) {
    console.log('Total results:', data.data.total_results);
    data.data.results.forEach(result => {
      console.log(`${result.type}: ${result.title} (${result.score})`);
    });
  }
});
```

### 2. MCP (Model Context Protocol) Integration

**MCP Server Configuration:**
```json
{
  "mcpServers": {
    "legal-search": {
      "command": "php",
      "args": ["artisan", "mcp:serve"],
      "env": {
        "MCP_TRANSPORT": "stdio"
      }
    }
  }
}
```

**Available MCP Tools:**
- `unified_search` - Main search tool
- `law_search` - Laws-only search
- `decision_search` - Decisions-only search
- `case_search` - Cases-only search

**MCP Tool Call Example:**
```json
{
  "tool": "unified_search",
  "arguments": {
    "query": "What are the data privacy regulations?",
    "corpora": ["laws"],
    "limit": 5
  }
}
```

### 3. Laravel Service Integration

**Direct Service Usage:**
```php
use App\Services\UnifiedSearchService;

class YourService
{
    public function __construct(
        protected UnifiedSearchService $searchService
    ) {}

    public function performSearch()
    {
        $results = $this->searchService->search('your query', [
            'corpora' => ['laws', 'decisions'],
            'threshold' => 0.75,
            'limit' => 10,
            'filters' => [
                'jurisdiction' => 'HR',
                'date_from' => '2020-01-01',
            ],
        ]);

        return $results;
    }
}
```

### 4. Livewire Component Embedding

**Embed in Other Livewire Components:**
```blade
<div>
    <h1>My Custom Page</h1>
    @livewire('unified-search')
</div>
```

**Route Integration:**
```php
Route::get('/my-search-page', \App\Http\Livewire\UnifiedSearch::class);
```

### 5. Autonomous Agent Integration (Vizra ADK)

**Agent Configuration:**
```php
use Vizra\Adk\Agent;

$agent = new Agent([
    'tools' => [
        'search' => function($query, $corpora = ['laws']) {
            return Http::post(config('app.url') . '/api/search', [
                'query' => $query,
                'corpora' => $corpora,
                'limit' => 5,
            ])->json();
        },
    ],
]);
```

---

## Configuration

### Environment Variables

```env
# OpenAI Configuration
OPENAI_API_KEY=sk-...
OPENAI_EMBEDDING_MODEL=text-embedding-3-small

# Search Configuration
SEARCH_CACHE_TTL=300           # 5 minutes
SEARCH_RATE_LIMIT=60           # requests per minute
SEARCH_DEFAULT_THRESHOLD=0.7
SEARCH_DEFAULT_LIMIT=10

# Database Configuration
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=legal_db
DB_USERNAME=postgres
DB_PASSWORD=secret
```

### Config Files

**config/openai.php**
```php
return [
    'api_key' => env('OPENAI_API_KEY'),
    'models' => [
        'embeddings' => env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),
    ],
];
```

**config/search.php** (Create if needed)
```php
return [
    'cache_ttl' => env('SEARCH_CACHE_TTL', 300),
    'rate_limit' => env('SEARCH_RATE_LIMIT', 60),
    'default_threshold' => env('SEARCH_DEFAULT_THRESHOLD', 0.7),
    'default_limit' => env('SEARCH_DEFAULT_LIMIT', 10),
];
```

---

## Usage Examples

### Example 1: Simple Natural Language Query

**Query:** "What are the regulations about personal data protection?"

**Request:**
```json
{
  "query": "What are the regulations about personal data protection?",
  "corpora": ["laws"],
  "threshold": 0.75,
  "limit": 5
}
```

**Expected Results:**
- Zakon o zaštiti osobnih podataka (NN 123/20)
- GDPR implementation laws
- Related privacy regulations

### Example 2: Citation-Based Search

**Query:** "Find all references to NN 123/20 article 15"

**Request:**
```json
{
  "query": "NN 123/20 čl. 15",
  "search_mode": "hybrid",
  "corpora": ["laws", "decisions"],
  "limit": 10
}
```

**Expected Results:**
- The law NN 123/20, article 15 chunk
- Court decisions citing NN 123/20 art. 15
- Related articles from the same law

### Example 3: Date-Filtered Court Decision Search

**Query:** "Data breach penalty decisions from 2023"

**Request:**
```json
{
  "query": "data breach penalty",
  "corpora": ["decisions"],
  "filters": {
    "date_from": "2023-01-01",
    "date_to": "2023-12-31"
  },
  "threshold": 0.7,
  "limit": 20
}
```

**Expected Results:**
- Court decisions about data breaches in 2023
- Sorted by relevance score
- Filtered to specified date range

### Example 4: Weighted Multi-Corpus Search

**Query:** "Employment contract termination"

**Request:**
```json
{
  "query": "Employment contract termination",
  "corpora": ["laws", "decisions", "cases"],
  "weights": {
    "laws": 2.0,       // Prioritize laws
    "decisions": 1.5,  // Medium priority for decisions
    "cases": 0.5       // Lower priority for cases
  },
  "threshold": 0.7,
  "limit": 15
}
```

**Expected Results:**
- More law results due to higher weight
- Balanced mix across all corpora
- Relevance scores adjusted by corpus weights

### Example 5: Jurisdiction-Specific Search

**Query:** "Find all Supreme Court decisions on privacy rights"

**Request:**
```json
{
  "query": "privacy rights human rights violations",
  "corpora": ["decisions"],
  "filters": {
    "court": "Vrhovni sud",
    "jurisdiction": "HR"
  },
  "sort_by": "date",
  "sort_order": "desc",
  "limit": 10
}
```

**Expected Results:**
- Recent Supreme Court decisions
- Filtered to Croatian jurisdiction
- Sorted by date (newest first)

---

## Performance Considerations

### Optimization Techniques

#### 1. Vector Index Optimization
```sql
-- IVFFlat index for approximate nearest neighbor search
CREATE INDEX ON laws USING ivfflat (embedding vector_cosine_ops)
  WITH (lists = 100);

-- GIN index for full-text search
CREATE INDEX ON laws USING gin (content_tsv);
```

#### 2. Query Optimization
- Limit embedding calls with caching
- Use appropriate threshold to reduce result set
- Paginate large result sets
- Enable deduplication to reduce processing

#### 3. Caching Strategy
- **Query Results:** 5-minute cache
- **Embeddings:** Cache frequently-used query embeddings
- **Static Data:** Cache law metadata, court lists, etc.

#### 4. Rate Limiting
- 60 requests per minute prevents API abuse
- Returns `429 Too Many Requests` with `Retry-After` header

### Performance Benchmarks

| Operation | Typical Time | Notes |
|-----------|--------------|-------|
| Embedding generation | 200-500ms | OpenAI API call |
| Vector search (1 corpus) | 50-200ms | Depends on corpus size |
| Vector search (3 corpora) | 150-600ms | Parallel execution |
| Hybrid search | 800-1500ms | Three search types + RRF |
| Full-text search | 20-100ms | PostgreSQL FTS |
| Cached query | 5-20ms | No DB/API calls |

### Scaling Recommendations

1. **Database:**
   - Use connection pooling
   - Add read replicas for search queries
   - Partition large tables by year/jurisdiction

2. **Caching:**
   - Use Redis for distributed caching
   - Implement cache warming for popular queries
   - Monitor cache hit rates

3. **API:**
   - Add load balancer for horizontal scaling
   - Implement circuit breakers for OpenAI API
   - Queue long-running searches

4. **Embeddings:**
   - Batch embed multiple documents
   - Use smaller embedding models for faster response
   - Consider self-hosted embedding models

---

## Troubleshooting

### Common Issues

#### Issue 1: Slow Search Responses

**Symptoms:** Search takes > 3 seconds

**Possible Causes:**
- OpenAI API slow response
- Large corpus without proper indexing
- Missing cache configuration
- High threshold requiring full table scan

**Solutions:**
```bash
# Check index status
psql -c "SELECT schemaname, tablename, indexname
         FROM pg_indexes
         WHERE tablename IN ('laws', 'court_decision_documents', 'cases_documents');"

# Verify cache is working
php artisan cache:clear
redis-cli MONITOR | grep "search:"

# Lower threshold or limit results
# threshold: 0.7 → 0.5
# limit: 100 → 20
```

#### Issue 2: No Results Found

**Symptoms:** Empty result set for valid queries

**Possible Causes:**
- Threshold too high
- Wrong corpus selected
- Filters too restrictive
- Documents not properly embedded

**Solutions:**
```php
// Check if documents have embeddings
$count = DB::table('laws')->whereNotNull('embedding')->count();
echo "Laws with embeddings: {$count}\n";

// Test with lower threshold
$results = $searchService->search($query, ['threshold' => 0.3]);

// Remove filters temporarily
$results = $searchService->search($query, [
    'corpora' => ['laws', 'decisions', 'cases'],
    'filters' => [],
]);
```

#### Issue 3: Rate Limit Exceeded

**Symptoms:** `429 Too Many Requests` error

**Possible Causes:**
- Too many requests from single IP
- Missing rate limiter configuration
- Attack or bot traffic

**Solutions:**
```bash
# Check rate limit middleware
php artisan route:list --path=search

# Adjust rate limit in RouteServiceProvider
// throttle:60,1 → throttle:120,1

# Whitelist specific IPs
Route::middleware(['throttle:none'])->group(function () {
    // Internal tools
});
```

#### Issue 4: Outdated Cached Results

**Symptoms:** Results don't reflect recent data changes

**Possible Causes:**
- Cache TTL too long
- Cache key collision
- Cache not being invalidated

**Solutions:**
```bash
# Clear search cache
php artisan cache:forget 'search:*'

# Disable cache temporarily
// In SearchController
Cache::forget($cacheKey);
// Then test

# Reduce TTL
// 300 seconds → 60 seconds
Cache::remember($cacheKey, 60, $searchCallback);
```

#### Issue 5: Validation Errors in UI

**Symptoms:** Form submission fails with validation errors

**Possible Causes:**
- Query too long (> 1000 chars)
- Invalid date format
- date_to before date_from
- Invalid filter values

**Solutions:**
```javascript
// Check query length
console.log('Query length:', query.length);

// Validate date range
if (dateTo < dateFrom) {
    alert('End date must be after start date');
}

// Sanitize filters
filters.court = filters.court.trim();
```

### Debugging Tools

#### Enable Query Logging
```php
DB::enableQueryLog();
$results = $searchService->search($query, $options);
dd(DB::getQueryLog());
```

#### Log Search Performance
```php
Log::info('Search Performance', [
    'query' => $query,
    'total_time' => $totalTime,
    'embedding_time' => $embeddingTime,
    'corpus_timing' => $corpusTiming,
    'result_count' => count($results),
]);
```

#### Check Embedding Quality
```php
$embedding = $openAIService->embeddings(['test query']);
echo "Embedding dimensions: " . count($embedding['data'][0]['embedding']) . "\n";
echo "First 5 values: " . json_encode(array_slice($embedding['data'][0]['embedding'], 0, 5)) . "\n";
```

### Performance Monitoring

#### Key Metrics to Track
- **Response Time:** Average, P50, P95, P99
- **Cache Hit Rate:** % of cached responses
- **Error Rate:** 4xx and 5xx response codes
- **Rate Limit Hits:** 429 response count
- **Embedding API Latency:** OpenAI call time
- **Database Query Time:** Per corpus timing
- **Result Quality:** Average relevance scores

#### Recommended Monitoring Tools
- **Laravel Telescope** - Request/query debugging
- **Laravel Horizon** - Queue monitoring (for async searches)
- **New Relic / DataDog** - APM and performance
- **Grafana + Prometheus** - Metrics dashboards

---

## Future Enhancements

### Planned Features
1. **Graph-Enhanced Search** - Integration with GraphRagService for citation graph traversal
2. **Multi-Language Support** - Cross-lingual search with translation
3. **Query Suggestions** - Auto-complete and query refinement
4. **Search History** - User search history tracking and re-search
5. **Saved Searches** - Save and share search configurations
6. **Advanced Analytics** - Search trend analysis and usage dashboards
7. **Semantic Highlighting** - Highlight relevant sections in results
8. **Export Formats** - CSV, PDF, DOCX export options
9. **Batch Search API** - Search multiple queries in one request
10. **Webhook Notifications** - Alert on new matching documents

### Research Directions
- **Fine-tuned Models** - Croatian legal domain-specific embeddings
- **Re-ranking Models** - Cross-encoder for result re-ranking
- **Query Expansion** - Automatic query reformulation
- **Federated Search** - Search across multiple legal databases
- **Adversarial Testing** - Robustness against edge cases

---

## Changelog

### Version 1.1.0 (2025-10-26)
**Added:**
- ✅ Advanced filters (jurisdiction, court, language, date range)
- ✅ Corpus weights configuration in UI
- ✅ Export results as JSON
- ✅ Keyboard shortcuts (Ctrl+K, Escape)
- ✅ Validation error display in UI
- ✅ Query length increased to 1000 chars

**Fixed:**
- Query validation mismatch (500 vs 1000 chars)
- Missing validation error feedback
- Date range filter validation

**Improved:**
- Loading states and user feedback
- Filter UI organization
- Documentation completeness

### Version 1.0.0 (2025-10-26)
**Initial Release:**
- Vector search across 3 corpora
- Hybrid search with RRF
- Citation-aware search
- Livewire web UI
- REST API endpoints
- Rate limiting and caching
- Deduplication by content hash
- Dashboard integration

---

## References

### Documentation
- [Laravel Documentation](https://laravel.com/docs)
- [Livewire Documentation](https://livewire.laravel.com/docs)
- [pgvector Documentation](https://github.com/pgvector/pgvector)
- [OpenAI Embeddings API](https://platform.openai.com/docs/guides/embeddings)

### Research Papers
- **Reciprocal Rank Fusion:**
  Cormack, G. V., Clarke, C. L., & Buettcher, S. (2009). Reciprocal rank fusion outperforms condorcet and individual rank learning methods. In SIGIR '09.

- **Vector Similarity Search:**
  Johnson, J., Douze, M., & Jégou, H. (2019). Billion-scale similarity search with GPUs. IEEE TPAMI.

### Related Projects
- [Elasticsearch Legal Search](https://www.elastic.co/solutions/legal)
- [EUR-Lex Search](https://eur-lex.europa.eu/)
- [LexPredict](https://www.lexpredict.com/)

---

## Support

### Getting Help
- **GitHub Issues:** [Report bugs or request features](https://github.com/your-org/ai-legal-war-machine/issues)
- **Documentation:** [Full documentation](docs/)
- **API Reference:** [Swagger/OpenAPI spec](docs/api-reference.yaml)

### Contact
- **Email:** support@example.com
- **Slack:** #legal-search channel

---

**Last Updated:** 2025-10-26
**Version:** 1.1.0
**Maintained By:** Development Team

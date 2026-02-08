# Search API Documentation

## Overview

The Search API provides unified access to search across all legal corpora in the system, including laws, court decisions, and case documents. The API supports vector search, hybrid search (combining vector and full-text search), and citation-aware search.

## Base URL

```
/api/search
```

## Features

- **Unified Search Interface**: Single API to search across multiple legal corpora
- **Request Validation**: Comprehensive input validation via FormRequest classes
- **Rate Limiting**: 60 requests per minute per user
- **Response Caching**: 5-minute cache for frequent queries to improve performance
- **Flexible Filtering**: Support for jurisdiction, date ranges, document types, and more
- **Advanced Search Types**: Vector search, hybrid search, and citation-aware search

## Authentication

Currently, the search endpoints are publicly accessible. Future versions may require authentication for certain endpoints or rate limit tiers.

## Rate Limiting

All search endpoints are rate-limited to **60 requests per minute per user** (or IP address for unauthenticated users).

When rate limit is exceeded, the API returns:
- HTTP Status: `429 Too Many Requests`
- Header: `Retry-After` (seconds until the rate limit resets)

## Response Caching

Search results are cached for **5 minutes** based on the request parameters. This means:
- Identical queries within 5 minutes return cached results instantly
- Cache keys are generated from all request parameters (query, filters, pagination, etc.)
- Different parameters result in different cache entries

## Common Request Parameters

The following parameters are common across most search endpoints:

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `query` | string | Yes | - | Search query (2-1000 characters) |
| `limit` | integer | No | 10 | Number of results to return (1-100) |
| `page` | integer | No | 1 | Page number for pagination (≥1) |
| `per_page` | integer | No | 10 | Results per page (1-100) |
| `offset` | integer | No | - | Result offset (alternative to pagination) |
| `threshold` | float | No | 0.7 | Similarity threshold (0-1) for vector search |
| `sort_by` | string | No | score | Sort field: `score` or `date` |
| `sort_order` | string | No | desc | Sort order: `asc` or `desc` |
| `deduplicate` | boolean | No | true | Remove duplicate results |

## Response Format

All successful responses follow this structure:

```json
{
  "success": true,
  "data": {
    "results": [...],
    "meta": {
      "total": 150,
      "count": 10,
      "page": 1,
      "per_page": 10,
      "execution_time_ms": 245
    }
  }
}
```

Error responses:

```json
{
  "success": false,
  "error": "Error message",
  "errors": {
    "field_name": ["Validation error message"]
  }
}
```

## Endpoints

### 1. Unified Search

Search across all legal corpora (laws, decisions, and cases) with customizable weights.

**Endpoint:** `POST /api/search`

**Request Body:**

```json
{
  "query": "contract law obligations",
  "corpora": ["laws", "decisions", "cases"],
  "weights": {
    "laws": 1.5,
    "decisions": 1.0,
    "cases": 0.8
  },
  "filters": {
    "jurisdiction": "federal",
    "country": "Serbia",
    "date_from": "2020-01-01",
    "date_to": "2024-12-31",
    "language": "sr"
  },
  "limit": 20,
  "page": 1,
  "threshold": 0.75,
  "sort_by": "score",
  "sort_order": "desc",
  "deduplicate": true
}
```

**Additional Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `corpora` | array | Legal corpora to search: `laws`, `decisions`, `cases` |
| `weights` | object | Relative weights for each corpus (0-10) |
| `filters` | object | Filtering options (see filters section below) |

**Example Response:**

```json
{
  "success": true,
  "data": {
    "results": [
      {
        "id": "law_123",
        "corpus": "laws",
        "title": "Civil Code - Obligations",
        "snippet": "...contract obligations between parties...",
        "score": 0.92,
        "metadata": {
          "jurisdiction": "federal",
          "country": "Serbia",
          "effective_date": "2021-06-15"
        }
      },
      {
        "id": "decision_456",
        "corpus": "decisions",
        "title": "Supreme Court Decision 789/2022",
        "snippet": "...interpretation of contractual obligations...",
        "score": 0.88,
        "metadata": {
          "court": "Supreme Court",
          "decision_date": "2022-11-20"
        }
      }
    ],
    "meta": {
      "total": 156,
      "count": 2,
      "page": 1,
      "per_page": 20,
      "execution_time_ms": 312
    }
  }
}
```

**cURL Example:**

```bash
curl -X POST https://api.example.com/api/search \
  -H "Content-Type: application/json" \
  -d '{
    "query": "contract law obligations",
    "corpora": ["laws", "decisions"],
    "limit": 10
  }'
```

---

### 2. Search Laws

Search only within the laws corpus.

**Endpoint:** `POST /api/search/laws`

**Request Body:**

```json
{
  "query": "property rights inheritance",
  "filters": {
    "jurisdiction": "national",
    "country": "Serbia",
    "language": "sr",
    "date_from": "2015-01-01"
  },
  "limit": 15,
  "threshold": 0.7
}
```

**Law-Specific Filters:**

| Filter | Type | Description |
|--------|------|-------------|
| `jurisdiction` | string | Law jurisdiction (e.g., federal, national, regional) |
| `country` | string | Country code or name |
| `language` | string | Language code (e.g., sr, en) |
| `date_from` | date | Effective date from (YYYY-MM-DD) |
| `date_to` | date | Effective date to (YYYY-MM-DD) |

**Example Response:**

```json
{
  "success": true,
  "data": {
    "results": [
      {
        "id": "law_567",
        "title": "Law on Inheritance",
        "article_number": "Article 45",
        "content": "Property rights in inheritance proceedings...",
        "score": 0.91,
        "metadata": {
          "law_name": "Law on Inheritance",
          "official_gazette": "Official Gazette 72/2011",
          "effective_date": "2011-10-01",
          "jurisdiction": "national",
          "language": "sr"
        }
      }
    ],
    "meta": {
      "total": 23,
      "count": 1,
      "page": 1,
      "per_page": 15
    }
  }
}
```

**cURL Example:**

```bash
curl -X POST https://api.example.com/api/search/laws \
  -H "Content-Type: application/json" \
  -d '{
    "query": "property rights inheritance",
    "filters": {"jurisdiction": "national"},
    "limit": 15
  }'
```

---

### 3. Search Court Decisions

Search only within court decisions corpus.

**Endpoint:** `POST /api/search/decisions`

**Request Body:**

```json
{
  "query": "employment discrimination",
  "filters": {
    "court": "Supreme Court",
    "jurisdiction": "federal",
    "decision_type": "final",
    "date_from": "2020-01-01",
    "date_to": "2024-12-31"
  },
  "limit": 10,
  "sort_by": "date",
  "sort_order": "desc"
}
```

**Decision-Specific Filters:**

| Filter | Type | Description |
|--------|------|-------------|
| `court` | string | Court name (e.g., Supreme Court, Appeals Court) |
| `jurisdiction` | string | Court jurisdiction |
| `decision_type` | string | Type of decision (e.g., final, preliminary) |
| `date_from` | date | Decision date from (YYYY-MM-DD) |
| `date_to` | date | Decision date to (YYYY-MM-DD) |

**Example Response:**

```json
{
  "success": true,
  "data": {
    "results": [
      {
        "id": "decision_890",
        "title": "Decision 123/2023 - Employment Discrimination Case",
        "case_number": "123/2023",
        "content": "The court finds that employment discrimination occurred...",
        "score": 0.89,
        "metadata": {
          "court": "Supreme Court",
          "decision_date": "2023-08-15",
          "decision_type": "final",
          "parties": ["John Doe", "ABC Corporation"]
        }
      }
    ],
    "meta": {
      "total": 47,
      "count": 1,
      "page": 1,
      "per_page": 10
    }
  }
}
```

**cURL Example:**

```bash
curl -X POST https://api.example.com/api/search/decisions \
  -H "Content-Type: application/json" \
  -d '{
    "query": "employment discrimination",
    "filters": {"court": "Supreme Court"},
    "limit": 10
  }'
```

---

### 4. Search Case Documents

Search only within case documents corpus (private/uploaded documents).

**Endpoint:** `POST /api/search/cases`

**Request Body:**

```json
{
  "query": "breach of contract clause 5",
  "filters": {
    "category": "commercial",
    "language": "en",
    "source": "uploads"
  },
  "limit": 20
}
```

**Case-Specific Filters:**

| Filter | Type | Description |
|--------|------|-------------|
| `category` | string | Document category (e.g., commercial, civil, criminal) |
| `language` | string | Document language code |
| `source` | string | Document source (e.g., uploads, imports) |

**Example Response:**

```json
{
  "success": true,
  "data": {
    "results": [
      {
        "id": "case_234",
        "title": "Commercial Contract Dispute - Company X",
        "filename": "contract_dispute_2023.pdf",
        "content": "Clause 5 states that in case of breach...",
        "score": 0.87,
        "metadata": {
          "category": "commercial",
          "language": "en",
          "uploaded_date": "2023-09-01",
          "file_type": "pdf"
        }
      }
    ],
    "meta": {
      "total": 12,
      "count": 1,
      "page": 1,
      "per_page": 20
    }
  }
}
```

**cURL Example:**

```bash
curl -X POST https://api.example.com/api/search/cases \
  -H "Content-Type: application/json" \
  -d '{
    "query": "breach of contract clause 5",
    "filters": {"category": "commercial"},
    "limit": 20
  }'
```

---

### 5. Hybrid Search

Combines vector search (semantic similarity) with full-text search (keyword matching) for better results.

**Endpoint:** `POST /api/search/hybrid`

**Request Body:**

```json
{
  "query": "labor law termination procedures",
  "corpora": ["laws", "decisions"],
  "filters": {
    "date_from": "2018-01-01"
  },
  "limit": 15,
  "threshold": 0.6
}
```

**How Hybrid Search Works:**

1. **Vector Search**: Uses semantic embeddings to find conceptually similar documents
2. **Full-Text Search**: Uses keyword matching and text indexing
3. **Score Fusion**: Combines both scores using Reciprocal Rank Fusion (RRF)
4. **Ranking**: Returns results ranked by the combined score

**Example Response:**

```json
{
  "success": true,
  "data": {
    "results": [
      {
        "id": "law_789",
        "title": "Labor Law - Article 180: Termination Procedures",
        "content": "Procedures for termination of employment...",
        "score": 0.94,
        "scores": {
          "vector": 0.88,
          "fulltext": 0.92,
          "hybrid": 0.94
        },
        "metadata": {
          "corpus": "laws"
        }
      }
    ],
    "meta": {
      "total": 67,
      "count": 1,
      "page": 1,
      "per_page": 15,
      "search_type": "hybrid"
    }
  }
}
```

**cURL Example:**

```bash
curl -X POST https://api.example.com/api/search/hybrid \
  -H "Content-Type: application/json" \
  -d '{
    "query": "labor law termination procedures",
    "corpora": ["laws", "decisions"],
    "limit": 15
  }'
```

---

### 6. Citation-Aware Search

Search that considers citation networks between legal documents (e.g., court decisions citing laws).

**Endpoint:** `POST /api/search/with-citations`

**Request Body:**

```json
{
  "query": "constitutional rights privacy",
  "corpora": ["laws", "decisions"],
  "filters": {
    "jurisdiction": "federal"
  },
  "limit": 10
}
```

**How Citation-Aware Search Works:**

1. Performs initial search for relevant documents
2. Expands results by including cited documents
3. Includes documents that cite the found results
4. Provides citation context and relationships

**Example Response:**

```json
{
  "success": true,
  "data": {
    "results": [
      {
        "id": "law_111",
        "title": "Constitution - Article 41: Right to Privacy",
        "content": "Every citizen has the right to privacy...",
        "score": 0.95,
        "citations": {
          "cited_by_count": 142,
          "cited_by": [
            {
              "id": "decision_222",
              "title": "Privacy Rights Case 45/2022",
              "citation_context": "...as established in Article 41..."
            }
          ]
        },
        "metadata": {
          "corpus": "laws"
        }
      }
    ],
    "meta": {
      "total": 89,
      "count": 1,
      "page": 1,
      "per_page": 10,
      "search_type": "citation_aware"
    }
  }
}
```

**cURL Example:**

```bash
curl -X POST https://api.example.com/api/search/with-citations \
  -H "Content-Type: application/json" \
  -d '{
    "query": "constitutional rights privacy",
    "corpora": ["laws", "decisions"],
    "limit": 10
  }'
```

---

## Error Handling

### HTTP Status Codes

| Status Code | Description |
|-------------|-------------|
| 200 | Success |
| 422 | Validation Error (invalid request parameters) |
| 429 | Too Many Requests (rate limit exceeded) |
| 500 | Internal Server Error |

### Validation Errors

When request validation fails (HTTP 422), the response includes detailed error messages:

```json
{
  "success": false,
  "errors": {
    "query": [
      "The query field is required."
    ],
    "limit": [
      "The limit must not be greater than 100."
    ],
    "threshold": [
      "The threshold must be between 0 and 1."
    ]
  }
}
```

### Common Validation Rules

- `query`: Required, string, 2-1000 characters
- `limit`, `per_page`: Integer, 1-100
- `page`: Integer, ≥1
- `offset`: Integer, ≥0
- `threshold`: Float, 0-1
- `corpora.*`: Must be one of: `laws`, `decisions`, `cases`
- `sort_by`: Must be one of: `score`, `date`
- `sort_order`: Must be one of: `asc`, `desc`
- `filters.date_from`, `filters.date_to`: Valid date format (YYYY-MM-DD)

---

## Best Practices

### 1. Use Appropriate Search Type

- **Vector Search** (`/api/search`): Best for semantic/conceptual queries
- **Hybrid Search** (`/api/search/hybrid`): Best for queries with specific keywords
- **Citation-Aware Search** (`/api/search/with-citations`): Best when legal precedents matter

### 2. Optimize Performance

- Use pagination instead of large `limit` values
- Set reasonable `threshold` values (0.6-0.8 typically works well)
- Take advantage of caching by reusing identical queries

### 3. Handle Rate Limits

```javascript
// Example: JavaScript with retry logic
async function searchWithRetry(query, maxRetries = 3) {
  for (let i = 0; i < maxRetries; i++) {
    const response = await fetch('/api/search', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ query })
    });

    if (response.status === 429) {
      const retryAfter = response.headers.get('Retry-After');
      await new Promise(resolve => setTimeout(resolve, retryAfter * 1000));
      continue;
    }

    return response.json();
  }
  throw new Error('Rate limit exceeded after retries');
}
```

### 4. Filter Effectively

Use specific filters to narrow results and improve relevance:

```json
{
  "query": "tax regulations",
  "filters": {
    "jurisdiction": "federal",
    "date_from": "2020-01-01",
    "language": "sr"
  }
}
```

---

## Code Examples

### Python Example

```python
import requests

def search_laws(query, filters=None):
    url = "https://api.example.com/api/search/laws"

    payload = {
        "query": query,
        "limit": 20,
        "threshold": 0.7
    }

    if filters:
        payload["filters"] = filters

    response = requests.post(url, json=payload)

    if response.status_code == 200:
        return response.json()["data"]
    elif response.status_code == 429:
        retry_after = int(response.headers.get("Retry-After", 60))
        print(f"Rate limited. Retry after {retry_after} seconds.")
        return None
    else:
        response.raise_for_status()

# Usage
results = search_laws(
    query="property inheritance",
    filters={"jurisdiction": "national", "date_from": "2015-01-01"}
)

if results:
    for item in results["results"]:
        print(f"{item['title']} - Score: {item['score']}")
```

### JavaScript/Node.js Example

```javascript
const axios = require('axios');

async function hybridSearch(query, corpora = ['laws', 'decisions']) {
  try {
    const response = await axios.post('https://api.example.com/api/search/hybrid', {
      query: query,
      corpora: corpora,
      limit: 15,
      threshold: 0.6
    });

    return response.data.data;
  } catch (error) {
    if (error.response?.status === 429) {
      const retryAfter = error.response.headers['retry-after'];
      console.log(`Rate limited. Retry after ${retryAfter} seconds.`);
    } else if (error.response?.status === 422) {
      console.log('Validation errors:', error.response.data.errors);
    } else {
      console.error('Search failed:', error.message);
    }
    throw error;
  }
}

// Usage
hybridSearch('labor law termination')
  .then(results => {
    results.results.forEach(item => {
      console.log(`${item.title} - Score: ${item.score}`);
    });
  })
  .catch(err => console.error(err));
```

### PHP Example

```php
<?php

function searchWithCitations($query, $filters = []) {
    $url = 'https://api.example.com/api/search/with-citations';

    $data = [
        'query' => $query,
        'corpora' => ['laws', 'decisions'],
        'filters' => $filters,
        'limit' => 10
    ];

    $options = [
        'http' => [
            'header'  => "Content-Type: application/json\r\n",
            'method'  => 'POST',
            'content' => json_encode($data)
        ]
    ];

    $context  = stream_context_create($options);
    $result = file_get_contents($url, false, $context);

    if ($result === FALSE) {
        throw new Exception('Search request failed');
    }

    $response = json_decode($result, true);

    if (!$response['success']) {
        throw new Exception($response['error']);
    }

    return $response['data'];
}

// Usage
try {
    $results = searchWithCitations(
        'constitutional rights',
        ['jurisdiction' => 'federal']
    );

    foreach ($results['results'] as $item) {
        echo "{$item['title']} - Score: {$item['score']}\n";
        if (isset($item['citations'])) {
            echo "  Cited by: {$item['citations']['cited_by_count']} documents\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
```

---

## Changelog

### Version 1.0 (Sprint E1.3)

- Initial release of Search API
- Added unified search endpoint
- Added corpus-specific endpoints (laws, decisions, cases)
- Added hybrid search support
- Added citation-aware search
- Implemented request validation via FormRequest classes
- Implemented rate limiting (60 requests/minute)
- Implemented response caching (5 minutes)
- Added comprehensive API documentation

---

## Support

For questions, issues, or feature requests related to the Search API, please contact the development team or create an issue in the project repository.

## Related Documentation

- [Unified Search Service Flow](./unified-search-service-flow.md)
- [Unified Search Service](./unified-search-service.md)
- [Hybrid Search Implementation](./hybrid-search-implementation.md)
- [MCP Tools](./MCP_TOOLS.md)

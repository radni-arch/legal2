# MCP Tool Registration Guide

## Overview

This document describes the Model Context Protocol (MCP) tools registered for the Croatian Legal Research System. MCP is a standardized protocol that allows external systems (Claude Desktop, API clients, custom integrations) to access Croatian legal knowledge through a unified interface.

**What is MCP?**
- MCP (Model Context Protocol) is a standard protocol for exposing tools and resources to AI assistants
- It provides a structured way for external clients to discover and invoke tools
- Tools return results in a standardized format with proper error handling

**Why use MCP?**
- **Standardized Access**: Consistent API for all legal research tools
- **Type Safety**: Input validation through JSON Schema
- **External Integration**: Claude Desktop, API clients, and other MCP-compatible systems can access tools
- **Rate Limiting**: Built-in protection (60 requests/minute by default)
- **Error Handling**: Structured error responses

---

## Registered Tools

### 1. law_search

**Purpose:** Search Croatian laws and legal articles by content, law number, or title.

**Parameters:**
- `query` (required, string): Search query for law content
  - Examples: "radni odnos", "ugovor o radu", "otkaz"
- `law_number` (optional, string): Filter by law number
  - Examples: "NN 94/14", "149/09"
- `title` (optional, string): Filter by law title
  - Examples: "Zakon o radu"
- `limit` (optional, integer): Maximum results (default: 10, max: 100)

**Example Request:**
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

**Example Response:**
```json
{
  "content": [{
    "type": "text",
    "text": "{\"count\": 5, \"results\": [{\"ingested_law_id\": 1, \"doc_id\": \"law-123\", \"title\": \"Zakon o radu\", \"law_number\": \"NN 149/09\", \"jurisdiction\": \"HR\", \"country\": \"Croatia\", \"language\": \"hr\", \"source_url\": \"...\", \"keywords\": [...], \"ingested_at\": \"2024-01-01T00:00:00Z\", \"articles_count\": 10, \"articles\": [{\"id\": 456, \"chunk_index\": 1, \"content\": \"...\", \"chapter\": \"...\", \"section\": \"...\", \"metadata\": {...}}]}]}"
  }],
  "isError": false
}
```

---

### 2. law_get_article

**Purpose:** Get full content of a specific law article by database ID.

**Parameters:**
- `id` (required, string): Database ID of the law article (from law_search results)

**Example Request:**
```bash
curl -X POST http://localhost/mcp/message \
  -H "Authorization: Bearer ${MCP_API_TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{
    "method": "tools/call",
    "params": {
      "name": "law_get_article",
      "arguments": {
        "id": "456"
      }
    }
  }'
```

**Example Response:**
```json
{
  "content": [{
    "type": "text",
    "text": "{\"id\": 456, \"doc_id\": \"law-123\", \"ingested_law_id\": 1, \"chunk_index\": 1, \"content\": \"Članak 1.\\n(1) Ovim Zakonom uređuju se radni odnosi...\", \"title\": \"Zakon o radu\", \"law_number\": \"NN 149/09\", \"jurisdiction\": \"HR\", \"country\": \"Croatia\", \"language\": \"hr\", \"chapter\": \"I. OPĆE ODREDBE\", \"section\": \"1. Predmet i primjena\", \"version\": \"1.0\", \"source_url\": \"...\", \"tags\": [...], \"metadata\": {...}, \"promulgation_date\": \"2009-11-06\", \"effective_date\": \"2009-11-15\", \"repeal_date\": null, \"parent_law\": {\"id\": 1, \"title\": \"Zakon o radu\", \"law_number\": \"NN 149/09\", \"jurisdiction\": \"HR\"}}"
  }],
  "isError": false
}
```

---

### 3. decision_search

**Purpose:** Search Croatian court decisions on odluke.sudovi.hr database.

**Parameters:**
- `q` (required, string): Search query for court decisions
  - Examples: "radni spor", "otkaz ugovora", "naknada štete"
- `params` (optional, string): Additional query parameters for filtering
- `limit` (optional, integer): Results per page (default: 100, max: 500)
- `page` (optional, integer): Page number (default: 1)
- `base_url` (optional, string): Custom base URL for the court database

**Example Request:**
```bash
curl -X POST http://localhost/mcp/message \
  -H "Authorization: Bearer ${MCP_API_TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{
    "method": "tools/call",
    "params": {
      "name": "decision_search",
      "arguments": {
        "q": "radni spor",
        "limit": 10,
        "page": 1
      }
    }
  }'
```

**Example Response:**
```json
{
  "content": [{
    "type": "text",
    "text": "{\"ids\": [\"abc123-def456\", \"ghi789-jkl012\"], \"count\": 10, \"page\": 1, \"total_pages\": 5}"
  }],
  "isError": false
}
```

---

### 4. decision_get_metadata

**Purpose:** Get detailed metadata for one or more court decisions.

**Parameters:**
- `id` (optional, string): Single decision ID (GUID) from odluke.sudovi.hr
- `ids` (optional, array of strings): Array of decision IDs for batch retrieval
- `base_url` (optional, string): Custom base URL for the court database

**Note:** Either `id` OR `ids` must be provided.

**Example Request (Single):**
```bash
curl -X POST http://localhost/mcp/message \
  -H "Authorization: Bearer ${MCP_API_TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{
    "method": "tools/call",
    "params": {
      "name": "decision_get_metadata",
      "arguments": {
        "id": "abc123-def456"
      }
    }
  }'
```

**Example Request (Batch):**
```bash
curl -X POST http://localhost/mcp/message \
  -H "Authorization: Bearer ${MCP_API_TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{
    "method": "tools/call",
    "params": {
      "name": "decision_get_metadata",
      "arguments": {
        "ids": ["abc123-def456", "ghi789-jkl012"]
      }
    }
  }'
```

**Example Response:**
```json
{
  "content": [{
    "type": "text",
    "text": "[{\"id\": \"abc123-def456\", \"case_number\": \"Grž-123/2023\", \"court\": \"Županijski sud u Zagrebu\", \"judge\": \"Ivan Horvat\", \"decision_date\": \"2023-05-15\", \"decision_type\": \"presuda\", \"ecli\": \"ECLI:HR:ŽSZ:2023:...\", \"parties\": [...], \"summary\": \"...\"}]"
  }],
  "isError": false
}
```

---

### 5. decision_download

**Purpose:** Download a court decision in PDF or HTML format.

**Parameters:**
- `id` (required, string): Decision ID (GUID) from odluke.sudovi.hr
- `format` (optional, enum): Download format - "pdf", "html", or "both" (default: "pdf")
- `save` (optional, boolean): Save to local storage at storage/app/odluke (default: false)
- `base_url` (optional, string): Custom base URL for the court database

**Example Request:**
```bash
curl -X POST http://localhost/mcp/message \
  -H "Authorization: Bearer ${MCP_API_TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{
    "method": "tools/call",
    "params": {
      "name": "decision_download",
      "arguments": {
        "id": "abc123-def456",
        "format": "pdf",
        "save": true
      }
    }
  }'
```

**Example Response:**
```json
{
  "content": [{
    "type": "text",
    "text": "{\"success\": true, \"format\": \"pdf\", \"file_path\": \"storage/app/odluke/abc123-def456.pdf\", \"size_bytes\": 245678, \"download_url\": \"https://odluke.sudovi.hr/...\", \"saved\": true}"
  }],
  "isError": false
}
```

---

### 6. legal_search

**Purpose:** Unified hybrid search across all legal corpora using vector similarity, keyword matching, and citation detection.

**Parameters:**
- `query` (required, string): Legal research query
  - Examples: "What are the grounds for termination of employment?", "pravni lijekovi u upravnom postupku"
- `corpora` (optional, array): Which corpora to search (default: ["laws", "decisions", "cases"])
  - Valid values: "laws", "decisions", "cases"
- `jurisdiction` (optional, string): Filter by jurisdiction code (e.g., "HR")
- `limit` (optional, integer): Maximum total results (default: 10, max: 50)

**Example Request:**
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

**Example Response:**
```json
{
  "content": [{
    "type": "text",
    "text": "{\"query\": \"otkaz ugovora o radu bez otkaznog roka\", \"total_results\": 10, \"returned_results\": 10, \"deduplicated_count\": 2, \"corpora\": [\"laws\", \"decisions\"], \"results\": [{\"type\": \"law\", \"id\": \"456\", \"title\": \"Zakon o radu\", \"snippet\": \"Članak 118. Poslodavac može otkazati ugovor o radu bez otkaznog roka...\", \"score\": 0.8523, \"metadata\": {...}}, {\"type\": \"decision\", \"id\": \"789\", \"title\": \"Presuda Grž-123/2023\", \"snippet\": \"Sud je utvrdio da je otkaz ugovora bio opravdan...\", \"score\": 0.7891, \"metadata\": {...}}], \"filters\": {}, \"pagination\": {\"page\": 1, \"per_page\": 10, \"offset\": 0, \"total_pages\": 1}, \"performance\": {\"total_time\": 0.452, \"embedding_time\": 0.123, \"corpus_timing\": {\"laws\": 0.156, \"decisions\": 0.173}}}"
  }],
  "isError": false
}
```

---

## Authentication

### Getting an MCP API Token

MCP tools require authentication using a Bearer token. To get your token:

1. Check your `.env` file for `MCP_API_TOKEN`
2. If not set, generate one:
   ```bash
   php artisan tinker
   >>> Str::random(60)
   ```
3. Add to `.env`:
   ```env
   MCP_API_TOKEN=your_generated_token_here
   ```

### Using the Token

Include the token in all requests:
```bash
-H "Authorization: Bearer your_token_here"
```

---

## Rate Limiting

**Default Rate Limit:** 60 requests per minute per token

**Configuration:**
- File: `config/mcp.php`
- Adjustable per-token if needed
- Returns HTTP 429 (Too Many Requests) when exceeded

**Example Error Response:**
```json
{
  "error": "Rate limit exceeded. Please wait before making more requests.",
  "retry_after": 60
}
```

---

## Error Handling

### Common Errors

#### 1. Invalid Token (401 Unauthorized)
```json
{
  "error": "Unauthorized. Invalid or missing API token."
}
```
**Solution:** Check your MCP_API_TOKEN in .env file

#### 2. Rate Limit Exceeded (429 Too Many Requests)
```json
{
  "error": "Rate limit exceeded.",
  "retry_after": 60
}
```
**Solution:** Wait 60 seconds before retrying

#### 3. Tool Not Found (404 Not Found)
```json
{
  "error": "Tool 'invalid_tool' not found."
}
```
**Solution:** Check tool name spelling (use one of: law_search, law_get_article, decision_search, decision_get_metadata, decision_download, legal_search)

#### 4. Invalid Parameters (400 Bad Request)
```json
{
  "error": "Validation failed",
  "details": {
    "query": ["The query field is required."]
  }
}
```
**Solution:** Check required parameters and types in tool schema

#### 5. Tool Execution Error (500 Internal Server Error)
```json
{
  "content": [{
    "type": "text",
    "text": "Error: Database connection failed"
  }],
  "isError": true
}
```
**Solution:** Check application logs in `storage/logs/laravel.log`

---

## Testing

### Using cURL

Test each tool with the examples provided in the sections above.

### Using Postman

1. Import the following environment variables:
   - `BASE_URL`: `http://localhost`
   - `MCP_API_TOKEN`: Your token from .env

2. Create a POST request to `{{BASE_URL}}/mcp/message`

3. Add headers:
   ```
   Authorization: Bearer {{MCP_API_TOKEN}}
   Content-Type: application/json
   ```

4. Use request body from examples above

### Automated Testing

Run the test suite:
```bash
php artisan test --filter McpToolsTest
```

---

## Integration Examples

### Claude Desktop Configuration

Add to your Claude Desktop MCP settings (`~/Library/Application Support/Claude/claude_desktop_config.json` on macOS):

```json
{
  "mcpServers": {
    "croatian-legal": {
      "command": "curl",
      "args": [
        "-X", "POST",
        "http://localhost/mcp/message",
        "-H", "Authorization: Bearer YOUR_TOKEN",
        "-H", "Content-Type: application/json",
        "-d", "{\"method\":\"tools/list\"}"
      ]
    }
  }
}
```

### Python API Client Example

```python
import requests

class CroatianLegalMCP:
    def __init__(self, base_url: str, api_token: str):
        self.base_url = base_url
        self.headers = {
            "Authorization": f"Bearer {api_token}",
            "Content-Type": "application/json"
        }

    def call_tool(self, tool_name: str, arguments: dict):
        response = requests.post(
            f"{self.base_url}/mcp/message",
            headers=self.headers,
            json={
                "method": "tools/call",
                "params": {
                    "name": tool_name,
                    "arguments": arguments
                }
            }
        )
        response.raise_for_status()
        return response.json()

    def search_laws(self, query: str, limit: int = 10):
        return self.call_tool("law_search", {
            "query": query,
            "limit": limit
        })

    def search_decisions(self, query: str, limit: int = 10):
        return self.call_tool("decision_search", {
            "q": query,
            "limit": limit
        })

    def unified_search(self, query: str, corpora: list = None, limit: int = 10):
        args = {"query": query, "limit": limit}
        if corpora:
            args["corpora"] = corpora
        return self.call_tool("legal_search", args)

# Usage
client = CroatianLegalMCP(
    base_url="http://localhost",
    api_token="your_token_here"
)

# Search for laws about employment
laws = client.search_laws("ugovor o radu", limit=5)
print(laws)

# Unified search across all corpora
results = client.unified_search(
    query="otkaz bez otkaznog roka",
    corpora=["laws", "decisions"],
    limit=10
)
print(results)
```

### JavaScript API Client Example

```javascript
class CroatianLegalMCP {
  constructor(baseUrl, apiToken) {
    this.baseUrl = baseUrl;
    this.apiToken = apiToken;
  }

  async callTool(toolName, arguments) {
    const response = await fetch(`${this.baseUrl}/mcp/message`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${this.apiToken}`,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        method: 'tools/call',
        params: {
          name: toolName,
          arguments: arguments
        }
      })
    });

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}: ${response.statusText}`);
    }

    return await response.json();
  }

  async searchLaws(query, limit = 10) {
    return await this.callTool('law_search', { query, limit });
  }

  async searchDecisions(query, limit = 10) {
    return await this.callTool('decision_search', { q: query, limit });
  }

  async unifiedSearch(query, corpora = null, limit = 10) {
    const args = { query, limit };
    if (corpora) args.corpora = corpora;
    return await this.callTool('legal_search', args);
  }
}

// Usage
const client = new CroatianLegalMCP(
  'http://localhost',
  'your_token_here'
);

// Search for laws
const laws = await client.searchLaws('ugovor o radu', 5);
console.log(laws);

// Unified search
const results = await client.unifiedSearch(
  'otkaz bez otkaznog roka',
  ['laws', 'decisions'],
  10
);
console.log(results);
```

---

## Performance Tips

1. **Use Pagination:** For large result sets, use `limit` and `page` parameters
2. **Cache Results:** Cache frequently accessed laws and decisions locally
3. **Batch Requests:** Use `decision_get_metadata` with `ids` array for multiple decisions
4. **Filter Early:** Use `law_number`, `title`, `jurisdiction` filters to reduce result set
5. **Choose Right Tool:** Use `law_search` for laws only, `legal_search` for comprehensive research

---

## Support

For issues, questions, or feature requests:

1. Check application logs: `storage/logs/laravel.log`
2. Enable debug mode in `.env`: `APP_DEBUG=true`
3. Review this documentation
4. Contact the development team

---

## Changelog

### Version 1.0.0 (2025-10-26)
- Initial registration of 6 legal tools
- Added comprehensive documentation
- Implemented rate limiting (60 req/min)
- Added authentication via Bearer token
- Registered tools: law_search, law_get_article, decision_search, decision_get_metadata, decision_download, legal_search

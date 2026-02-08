# MCP Tools Examples

This directory contains practical examples for testing and using the Legal Database MCP Server tools.

## Available Examples

### 1. HTTP Examples (`mcp-http-examples.http`)

REST Client file for VS Code (or similar tools) with comprehensive HTTP request examples.

**How to use:**

1. Install the [REST Client extension](https://marketplace.visualstudio.com/items?itemName=humao.rest-client) in VS Code
2. Open `mcp-http-examples.http`
3. Update the variables at the top:
   ```
   @baseUrl = http://localhost:8000
   @mcpToken = your-mcp-api-token-here
   ```
4. Click "Send Request" above any example

**Includes:**
- All law search and retrieval examples
- Court decision search and retrieval examples
- Case management examples (private)
- Combined workflow examples
- Error handling tests
- Pagination examples

### 2. Postman Collection (`Legal-Database-MCP-Server.postman_collection.json`)

Complete Postman collection for API testing.

**How to use:**

1. Open Postman
2. Click "Import" and select this file
3. Configure collection variables:
   - `baseUrl`: Your API base URL (default: `http://localhost:8000`)
   - `mcpToken`: Your MCP API token
4. Start testing!

**Features:**
- Pre-configured authentication
- Organized into folders by tool type
- Complete workflow examples
- Ready-to-use request templates

## Quick Start

### Prerequisites

1. Ensure the application is running:
   ```bash
   php artisan serve
   ```

2. Set up your MCP API token in `.env`:
   ```env
   MCP_AUTH_ENABLED=true
   MCP_API_TOKEN=your-secure-token-here
   ```

### Testing with cURL

**Search Laws:**
```bash
curl -X POST http://localhost:8000/api/mcp/law.search \
  -H "Content-Type: application/json" \
  -H "X-MCP-Token: your-token-here" \
  -d '{
    "query": "radno pravo",
    "country": "HR",
    "limit": 10
  }'
```

**Get Law Article:**
```bash
curl -X POST http://localhost:8000/api/mcp/law.get_article \
  -H "Content-Type: application/json" \
  -H "X-MCP-Token: your-token-here" \
  -d '{
    "doc_id": "nn_93_2014"
  }'
```

**Search Court Decisions:**
```bash
curl -X POST http://localhost:8000/api/mcp/decision.search \
  -H "Content-Type: application/json" \
  -H "X-MCP-Token: your-token-here" \
  -d '{
    "query": "nezakonit otkaz",
    "tags": "labor",
    "limit": 10
  }'
```

## Example Workflows

### Workflow 1: Legal Research

Research a legal topic by combining laws and court decisions:

1. **Search for relevant laws**
   - Tool: `law.search`
   - Query: Your legal topic
   - Filters: jurisdiction, tags, country

2. **Search for related court decisions**
   - Tool: `decision.search`
   - Query: Same or related topic
   - Filters: date range, court, tags

3. **Retrieve full law articles**
   - Tool: `law.get_article`
   - Input: doc_id from search results

4. **Retrieve full decision texts**
   - Tool: `decision.get`
   - Input: decision ID from search results
   - Include full content for analysis

### Workflow 2: Case Preparation (Private)

Analyze case files and find relevant precedents:

1. **Find your case**
   - Tool: `case.search`
   - Input: case_number or client_name

2. **Search case documents**
   - Tool: `case.search`
   - Input: case_id with query text
   - Enable document search

3. **Find similar precedents**
   - Tool: `decision.search`
   - Input: key terms from your case

4. **Find applicable laws**
   - Tool: `law.search`
   - Input: legal issues identified

## Common Patterns

### Pagination

Always use pagination for large result sets:

```json
{
  "query": "your query",
  "limit": 10,
  "page": 1
}
```

Response includes pagination info:
```json
{
  "pagination": {
    "total": 145,
    "page": 1,
    "limit": 10,
    "pages": 15
  }
}
```

### Filtering

Combine multiple filters for precise results:

```json
{
  "query": "ugovor",
  "country": "HR",
  "jurisdiction": "national",
  "tags": "labor,employment",
  "date_from": "2020-01-01"
}
```

### Content Control

Control response size with content flags:

```json
{
  "id": "some-id",
  "include_documents": true,
  "include_content": false  // Metadata only
}
```

## Error Examples

### Missing Authentication
```bash
# Returns 401 Unauthorized
curl -X POST http://localhost:8000/api/mcp/law.search \
  -H "Content-Type: application/json" \
  -d '{"query": "test"}'
```

### Invalid Token
```bash
# Returns 401 Unauthorized
curl -X POST http://localhost:8000/api/mcp/law.search \
  -H "Content-Type: application/json" \
  -H "X-MCP-Token: invalid-token" \
  -d '{"query": "test"}'
```

### Rate Limit Exceeded
```bash
# Returns 429 Too Many Requests after exceeding limit
# Try sending 100 requests rapidly
```

### Not Found
```bash
# Returns error when resource doesn't exist
curl -X POST http://localhost:8000/api/mcp/decision.get \
  -H "Content-Type: application/json" \
  -H "X-MCP-Token: your-token-here" \
  -d '{"id": "nonexistent-id"}'
```

## Environment Variables

For testing, configure these in `.env`:

```env
# Authentication
MCP_AUTH_ENABLED=true
MCP_API_TOKEN=your-secure-token-here
MCP_TOKEN_HEADER=X-MCP-Token

# Rate Limiting
MCP_RATE_LIMIT_ENABLED=true
MCP_RATE_LIMIT_PER_MINUTE=60
MCP_RATE_LIMIT_PER_HOUR=1000

# Per-tool limits
MCP_RATE_LAW_SEARCH=30
MCP_RATE_LAW_GET=60
MCP_RATE_DECISION_SEARCH=30
MCP_RATE_DECISION_GET=60
MCP_RATE_CASE_SEARCH=20

# Response configuration
MCP_MAX_PAGE_SIZE=100
MCP_DEFAULT_PAGE_SIZE=10
```

## Troubleshooting

### Connection Refused
- Ensure the application is running: `php artisan serve`
- Check the base URL matches your server address

### 401 Unauthorized
- Verify `MCP_API_TOKEN` is set in `.env`
- Check the token value in your requests matches
- Ensure `MCP_AUTH_ENABLED=true`

### 429 Too Many Requests
- Wait for the rate limit window to reset (1 minute for most limits)
- Consider increasing rate limits in `.env` for development
- Use pagination to reduce total request count

### Empty Results
- Check your filters aren't too restrictive
- Verify data exists in the database
- Try a broader search query

## Additional Resources

- [MCP Tools Documentation](../MCP_TOOLS.md) - Complete API reference
- [RAG Guide](../RAG_GUIDE.md) - Integration patterns for AI agents
- [Project README](../../README.md) - Setup and configuration

## Contributing

To add new examples:

1. Add them to `mcp-http-examples.http` with clear comments
2. Update the Postman collection
3. Document any new patterns in this README
4. Test all examples before committing

## Support

For issues or questions:
- Check the [MCP Tools Documentation](../MCP_TOOLS.md)
- Review error messages carefully
- Enable debug mode for detailed logs

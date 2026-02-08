# MCP Tools Access Guide

The Legal Database MCP Server provides **two ways** to access the MCP tools:

## 1. MCP Protocol Access (for AI Agents)

MCP tools can be accessed through the Model Context Protocol by MCP clients like Claude Desktop, Cline, or other MCP-compatible AI agents.

### Configuration

Add to your MCP client configuration (e.g., `claude_desktop_config.json`):

```json
{
  "mcpServers": {
    "legal-database": {
      "command": "php",
      "args": [
        "/path/to/ai-legal-war-machine/artisan",
        "boost:mcp"
      ]
    }
  }
}
```

### Usage

MCP clients will automatically discover and use the available tools:
- `law.search` - Search laws
- `law.get_article` - Get law articles
- `decision.search` - Search court decisions
- `decision.get` - Get decision details
- `case.search` - Search cases (private)

### Example with Claude Desktop

```
User: Find Croatian labor laws about employment termination

Claude: I'll search for labor laws using the law.search tool.
[Uses MCP to call law.search tool]
```

## 2. HTTP REST API Access (for Web/Mobile Apps)

The same tools are also exposed as HTTP REST API endpoints for traditional web/mobile application integration.

### Base URL

```
http://your-domain.com/api/mcp/
```

### Authentication

Include your MCP API token in the request header:

```http
X-MCP-Token: your-secure-token-here
```

### Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/mcp/law.search` | POST | Search laws |
| `/api/mcp/law.get_article` | POST | Get law article |
| `/api/mcp/decision.search` | POST | Search decisions |
| `/api/mcp/decision.get` | POST | Get decision |
| `/api/mcp/case.search` | POST | Search cases (private) |

### Example Request

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

### Example Response

```json
{
  "success": true,
  "data": [
    {
      "id": "01HQXXX...",
      "doc_id": "nn_93_2014",
      "title": "Zakon o radu",
      "law_number": "93/14",
      "jurisdiction": "national",
      "country": "HR",
      "language": "hr",
      "promulgation_date": "2014-07-01",
      "effective_date": "2014-07-15",
      "tags": ["labor", "employment"],
      "source_url": "https://zakon.hr/z/...",
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

## Comparison

| Feature | MCP Protocol | HTTP REST API |
|---------|--------------|---------------|
| **Use Case** | AI agents, Claude Desktop | Web/mobile apps, cURL |
| **Transport** | stdio (JSON-RPC) | HTTP(S) |
| **Authentication** | Optional | Required (X-MCP-Token) |
| **Rate Limiting** | Optional | Enabled by default |
| **Discovery** | Automatic | Manual (docs) |
| **Best For** | AI assistants, chatbots | Traditional applications |

## Which Should I Use?

### Use MCP Protocol When:
- ✅ Building AI agents or chatbots
- ✅ Integrating with Claude Desktop or similar MCP clients
- ✅ You want automatic tool discovery
- ✅ Working with conversational interfaces

### Use HTTP REST API When:
- ✅ Building web or mobile applications
- ✅ Need traditional REST API integration
- ✅ Working with non-MCP clients
- ✅ Need direct HTTP access (cURL, Postman, etc.)

## Security

Both access methods support:
- **Authentication**: Token-based (configurable)
- **Rate Limiting**: Per-tool and global limits
- **Access Control**: Public vs. private tools

### Configure in `.env`:

```env
# Enable authentication (applies to both MCP and HTTP)
MCP_AUTH_ENABLED=true
MCP_API_TOKEN=your-secure-token-here

# Rate limiting (applies to both MCP and HTTP)
MCP_RATE_LIMIT_ENABLED=true
MCP_RATE_LIMIT_PER_MINUTE=60
MCP_RATE_LIMIT_PER_HOUR=1000
```

## Examples

### For MCP Protocol

See: [MCP Client Configuration Examples](MCP_TOOLS.md#integration-with-ai-agents)

### For HTTP REST API

See:
- [HTTP Examples](examples/mcp-http-examples.http)
- [Postman Collection](examples/Legal-Database-MCP-Server.postman_collection.json)
- [Examples README](examples/README.md)

## Implementation Details

### MCP Protocol Implementation

The MCP server is implemented using:
- **Package**: `php-mcp/laravel` and `laravel/boost`
- **Command**: `php artisan boost:mcp`
- **Tool Classes**: Located in `app/Mcp/Tools/`
- **Server Registration**: `app/Mcp/Servers/OdlukeServer.php`

### HTTP REST API Implementation

The HTTP API is implemented using:
- **Controller**: `app/Http/Controllers/McpToolsController.php`
- **Routes**: `routes/api.php` (prefix: `/api/mcp/`)
- **Middleware**: `app/Http/Middleware/McpAuth.php`
- **Same Logic**: Uses the same models and business logic as MCP tools

## Troubleshooting

### MCP Protocol Issues

**Error**: "Tool not found"
- Check that `artisan boost:mcp` starts successfully
- Verify MCP server registration in `OdlukeServer.php`
- Check MCP client configuration

**Error**: "Connection failed"
- Verify PHP path in MCP configuration
- Check artisan command path
- Ensure all dependencies are installed

### HTTP API Issues

**Error**: 401 Unauthorized
- Verify `MCP_API_TOKEN` is set in `.env`
- Check X-MCP-Token header is included
- Ensure token matches exactly

**Error**: 404 Not Found
- Check endpoint URL (must be `/api/mcp/tool.name`)
- Verify routes are registered
- Run `php artisan route:list` to see available routes

**Error**: 429 Too Many Requests
- Wait for rate limit window to reset
- Increase limits in `.env` if needed
- Check rate limit configuration

## Further Reading

- [MCP Tools Documentation](MCP_TOOLS.md) - Complete API reference
- [RAG Guide](RAG_GUIDE.md) - AI integration patterns
- [HTTP Examples](examples/README.md) - HTTP usage examples
- [MCP Protocol Specification](https://modelcontextprotocol.io/docs)

## Support

For issues or questions:
- Check this guide first
- Review error messages
- Consult the main documentation
- File an issue in the project repository

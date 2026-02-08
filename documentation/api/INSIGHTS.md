# Agent Insights API

The Agent Insights API provides access to stored insights from past agent research runs, enabling memory reuse and knowledge continuity across autonomous agent executions.

## Overview

When autonomous agents (like `AutonomousResearchAgent`) conduct research, they save valuable insights to vector memory. The Insights API allows you to retrieve these stored insights based on various filters, making it easy to build on past research without starting from scratch.

## Authentication

All Insights API endpoints require API token authentication using the `api.token` middleware.

**Authentication Methods:**
- Header: `Authorization: Bearer YOUR_API_TOKEN`
- Query parameter: `?api_token=YOUR_API_TOKEN`

## Endpoints

### GET /api/insights/{agentName}

Retrieve recent insights for a specific agent.

#### Parameters

**Path Parameters:**
- `agentName` (string, required): Name of the agent (e.g., `autonomous_research_agent`)
  - Must contain only lowercase letters, numbers, and underscores
  - Example: `autonomous_research_agent`, `decision_discovery_agent`

**Query Parameters:**
- `objective` (string, optional): Filter by research objective (partial match)
  - Minimum length: 3 characters
  - Example: `Croatian labor law`

- `namespace` (string, optional): Filter by memory namespace
  - Default: `research_insights`
  - Maximum length: 100 characters
  - Example: `research_insights`, `case_analysis`, `legal_precedents`

- `source` (string, optional): Filter by source reference
  - Maximum length: 100 characters
  - Example: `autonomous_research`, `manual_entry`

- `limit` (integer, optional): Maximum number of insights to return
  - Default: 10
  - Minimum: 1
  - Maximum: 100
  - Example: `20`

- `days` (integer, optional): Only include insights from last N days
  - Default: 30
  - Minimum: 0 (no time limit)
  - Maximum: 365
  - Example: `60`

#### Response Format

**Success Response (200 OK):**

```json
{
  "success": true,
  "agent_name": "autonomous_research_agent",
  "filters": {
    "namespace": "research_insights",
    "limit": 10,
    "days": 30,
    "objective": "Croatian labor law"
  },
  "insights": [
    {
      "id": "01HQTEST123456789",
      "content": "Article 93 of Croatian Labor Law requires 2 weeks notice for termination",
      "objective": "Research Croatian labor law termination procedures",
      "metadata": {
        "run_id": "run-123",
        "objective": "Research Croatian labor law termination procedures"
      },
      "source": "autonomous_research",
      "source_id": "run-123",
      "created_at": "2025-10-15T10:00:00Z"
    },
    {
      "id": "01HQTEST987654321",
      "content": "Supreme Court ruling X-123/2024 clarified probation period rules",
      "objective": "Research Croatian labor law termination procedures",
      "metadata": {
        "run_id": "run-456",
        "objective": "Research Croatian labor law termination procedures"
      },
      "source": "autonomous_research",
      "source_id": "run-456",
      "created_at": "2025-10-20T14:30:00Z"
    }
  ],
  "count": 2,
  "message": "Successfully retrieved 2 recent insights"
}
```

**Error Responses:**

**400 Bad Request** - Invalid agent name format:
```json
{
  "success": false,
  "code": "VALIDATION_ERROR",
  "message": "Invalid agent name format",
  "data": {
    "agent_name": "Agent name must contain only lowercase letters, numbers, and underscores"
  }
}
```

**422 Unprocessable Entity** - Validation errors:
```json
{
  "message": "The objective field must be at least 3 characters.",
  "errors": {
    "objective": [
      "The objective field must be at least 3 characters."
    ]
  }
}
```

**500 Internal Server Error** - Service failure:
```json
{
  "success": false,
  "code": "AGENT_EXECUTION_FAILED",
  "message": "Failed to retrieve insights",
  "data": {
    "error": "Database connection failed"
  }
}
```

## Usage Examples

### Basic Usage

Retrieve recent insights for the autonomous research agent:

```bash
curl -X GET \
  'https://your-domain.com/api/insights/autonomous_research_agent' \
  -H 'Authorization: Bearer YOUR_API_TOKEN'
```

### Filter by Objective

Find insights related to a specific research topic:

```bash
curl -X GET \
  'https://your-domain.com/api/insights/autonomous_research_agent?objective=Croatian labor law' \
  -H 'Authorization: Bearer YOUR_API_TOKEN'
```

### Custom Time Window and Limit

Get up to 50 insights from the last 60 days:

```bash
curl -X GET \
  'https://your-domain.com/api/insights/autonomous_research_agent?limit=50&days=60' \
  -H 'Authorization: Bearer YOUR_API_TOKEN'
```

### Multiple Filters

Combine multiple filters for precise queries:

```bash
curl -X GET \
  'https://your-domain.com/api/insights/autonomous_research_agent?objective=labor law&namespace=research_insights&days=14&limit=20' \
  -H 'Authorization: Bearer YOUR_API_TOKEN'
```

### Using with JavaScript/Fetch

```javascript
const response = await fetch(
  'https://your-domain.com/api/insights/autonomous_research_agent?objective=labor law&limit=20',
  {
    method: 'GET',
    headers: {
      'Authorization': 'Bearer YOUR_API_TOKEN',
      'Accept': 'application/json'
    }
  }
);

const data = await response.json();

if (data.success) {
  console.log(`Found ${data.count} insights:`);
  data.insights.forEach(insight => {
    console.log(`- [${insight.created_at}] ${insight.content}`);
  });
}
```

### Using with Python

```python
import requests

url = "https://your-domain.com/api/insights/autonomous_research_agent"
headers = {
    "Authorization": "Bearer YOUR_API_TOKEN",
    "Accept": "application/json"
}
params = {
    "objective": "Croatian labor law",
    "limit": 20,
    "days": 30
}

response = requests.get(url, headers=headers, params=params)
data = response.json()

if data['success']:
    print(f"Found {data['count']} insights:")
    for insight in data['insights']:
        print(f"- [{insight['created_at']}] {insight['content']}")
```

## Integration with Agent Workflows

### Pre-loading Context for New Research Runs

The Insights API is designed to work seamlessly with the autonomous research agent workflow:

1. **Before starting a new research run**, query the Insights API to retrieve relevant past insights
2. **Include past insights** in the initial context for the new run
3. **Agent builds on past findings** rather than starting from scratch

Example workflow:

```javascript
// 1. Retrieve past insights on the topic
const pastInsights = await fetch(
  'https://your-domain.com/api/insights/autonomous_research_agent?objective=Croatian labor law&limit=10',
  { headers: { 'Authorization': 'Bearer YOUR_API_TOKEN' }}
).then(r => r.json());

// 2. Start new research run with past insights in context
const newRun = await fetch(
  'https://your-domain.com/api/agent/research/start',
  {
    method: 'POST',
    headers: {
      'Authorization': 'Bearer YOUR_API_TOKEN',
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      objective: 'Research Croatian labor law termination procedures',
      topics: ['labor law', 'termination'],
      context: {
        past_insights: pastInsights.insights,
        past_insights_count: pastInsights.count
      }
    })
  }
).then(r => r.json());
```

**Note:** The `AutonomousResearchAgent` automatically preloads past insights during `startRun()`, so manual pre-loading is optional but can be useful for inspection or custom workflows.

## Memory Namespaces

Insights are organized into namespaces for different types of knowledge:

- `research_insights`: General research findings (default)
- `case_analysis`: Insights from case analysis
- `legal_precedents`: Key legal precedents and rulings
- `strategy_notes`: Strategic planning notes
- `risk_assessments`: Risk analysis findings

You can query specific namespaces using the `namespace` parameter.

## Rate Limiting

The Insights API is rate limited to:
- **60 requests per minute** per authenticated user

Rate limit headers are included in responses:
- `X-RateLimit-Limit`: Maximum requests per window
- `X-RateLimit-Remaining`: Remaining requests in current window
- `Retry-After`: Seconds to wait before retry (when rate limited)

## Best Practices

1. **Use objective filtering**: When you know the research topic, filter by `objective` to get more relevant insights
2. **Adjust time windows**: Recent insights (7-14 days) are often most relevant, but longer windows can provide historical context
3. **Limit appropriately**: Request only what you need (10-20 insights typically sufficient)
4. **Cache responses**: If querying repeatedly, cache results client-side to reduce API calls
5. **Check count field**: Before processing insights, check the `count` field to see if any were found
6. **Handle errors gracefully**: Always check the `success` field and handle failures appropriately

## Related Endpoints

- `POST /api/agent/research/start`: Start a new research run (automatically preloads insights)
- `GET /api/agent/research/{id}`: Get details of a research run
- `GET /api/monitoring/health`: Check agent system health

## Support

For issues or questions about the Insights API, please:
- Check the main API documentation
- Review agent execution logs
- Contact the development team

## Changelog

### 2025-11-01
- Initial release of Agent Insights API
- Support for filtering by objective, namespace, source, limit, and days
- Automatic integration with `AutonomousResearchAgent` memory preloading

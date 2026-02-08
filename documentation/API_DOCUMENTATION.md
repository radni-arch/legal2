# Croatian Legal AI War Machine - API Documentation

## Overview

This repository contains the complete OpenAPI 3.0.3 specification for the Croatian Legal AI War Machine API, a comprehensive legal analysis system designed specifically for the Croatian legal system.

**API Version:** 1.0.0
**OpenAPI Version:** 3.0.3
**Total Endpoints:** 122+
**API Categories:** 17

## Quick Start

### Viewing the API Documentation

1. **Interactive Swagger UI** (Recommended):
   ```
   http://localhost:8000/api-docs.html
   ```

2. **Download OpenAPI Spec**:
   ```
   http://localhost:8000/openapi.yaml
   ```

3. **Use with other tools**:
   - Import `public/openapi.yaml` into Postman
   - Use with Insomnia REST client
   - Generate client SDKs using OpenAPI Generator

### Authentication

The API uses two authentication methods:

#### 1. Bearer Token (Standard API)

Most endpoints require Bearer token authentication:

```bash
curl -H "Authorization: Bearer YOUR_API_TOKEN" \
  https://api.legal-ai.example.com/api/search/laws \
  -d '{"query": "criminal procedure"}'
```

#### 2. MCP Token (MCP Protocol)

MCP-specific endpoints use X-MCP-Token header:

```bash
curl -H "X-MCP-Token: YOUR_MCP_TOKEN" \
  https://api.legal-ai.example.com/api/mcp/law.search \
  -d '{"query": "kazneni zakon", "limit": 10}'
```

## API Categories

### 1. OpenAI Proxy (18 endpoints)

Proxies to OpenAI API with Croatian legal context:

- **Chat Completions**: `/openai/chat`
- **Embeddings**: `/openai/embeddings`
- **Image Generation**: `/openai/image`
- **Text-to-Speech**: `/openai/tts`
- **Transcription**: `/openai/transcribe`
- **Files Management**: `/openai/files/*`
- **Assistants**: `/openai/assistants/*`
- **Vector Stores**: `/openai/vector-stores/*`

Example:
```bash
POST /api/openai/chat
{
  "messages": [
    {"role": "user", "content": "Explain KZ Article 123"}
  ],
  "model": "gpt-4"
}
```

### 2. Content Ingestion (4 endpoints)

Legal document ingestion and indexing:

- **Ingest Text**: `POST /ingest/text`
- **Ingest File**: `POST /ingest/file`
- **Search**: `POST /ingest/search`
- **Ingest Laws**: `POST /ingest/laws`

### 3. File Uploads (5 endpoints)

File upload with chunking support:

- **Direct Upload**: `POST /uploads`
- **Start Chunked**: `POST /uploads/start`
- **Upload Chunk**: `POST /uploads/{uploadId}/chunk/{index}`
- **Complete**: `POST /uploads/{uploadId}/complete`
- **Cancel**: `DELETE /uploads/{uploadId}`

### 4. MCP Tools (5 endpoints)

Model Context Protocol tools for legal research:

- **Search Laws**: `POST /mcp/law.search`
- **Get Article**: `POST /mcp/law.get_article`
- **Search Decisions**: `POST /mcp/decision.search`
- **Get Decision**: `POST /mcp/decision.get`
- **Search Cases**: `POST /mcp/case.search`

Example:
```bash
POST /api/mcp/law.search
{
  "query": "pravo na branitelja",
  "law_codes": ["ZKP"],
  "limit": 5
}
```

### 5. MCP-OpenAI Bridge (5 endpoints)

Exposes MCP tools as OpenAI function calling endpoints:

- **Info**: `GET /mcp-openai/info`
- **List Tools**: `GET /mcp-openai/tools`
- **Execute Tool**: `POST /mcp-openai/tools/execute`
- **Chat Completions**: `POST /mcp-openai/chat/completions`
- **Webhook**: `POST /mcp-openai/webhook`

### 6. Agent (5 endpoints)

Autonomous research agent:

- **Start Research**: `POST /agent/research/start`
- **List Research**: `GET /agent/research`
- **Get Research**: `GET /agent/research/{id}`
- **Get Evaluation**: `GET /agent/research/{id}/evaluation`
- **Delete Research**: `DELETE /agent/research/{id}`

### 7. Odluke Agent (2 endpoints)

Court decision discovery agent:

- **Execute**: `POST /odluke-agent/execute`
- **Status**: `POST /odluke-agent/status`

### 8. Search (6 endpoints)

Unified legal document search:

- **Unified Search**: `POST /search`
- **Search Laws**: `POST /search/laws`
- **Search Decisions**: `POST /search/decisions`
- **Search Cases**: `POST /search/cases`
- **Hybrid Search**: `POST /search/hybrid`
- **With Citations**: `POST /search/with-citations`

Example:
```bash
POST /api/search/hybrid
{
  "query": "nezakonita pretraga stana",
  "vector_weight": 0.7,
  "keyword_weight": 0.3,
  "limit": 20
}
```

### 9. Reasoning (5 endpoints)

Legal reasoning engine:

- **Analyze Conflict**: `POST /reasoning/analyze-conflict`
- **Resolve Conflict**: `POST /reasoning/resolve-conflict`
- **Authority Score**: `POST /reasoning/authority-score`
- **Parse Logic**: `POST /reasoning/parse-logic`
- **Apply Deductive**: `POST /reasoning/apply-deductive`

### 10. Analytics (5 endpoints)

Predictive analytics:

- **Predict Outcome**: `POST /analytics/predict-outcome/{caseId}`
- **Estimate Duration**: `POST /analytics/estimate-duration/{caseId}`
- **Comprehensive**: `POST /analytics/comprehensive/{caseId}`
- **Analyze Impact**: `POST /analytics/analyze-impact/{decisionId}`
- **Batch Predict**: `POST /analytics/batch-predict`

### 11. Strategy (5 endpoints)

Legal strategy building:

- **Build Strategy**: `POST /strategy/build/{caseId}`
- **Comprehensive**: `POST /strategy/comprehensive/{caseId}`
- **Generate Arguments**: `POST /strategy/generate-arguments/{caseId}`
- **Assess Risks**: `POST /strategy/assess-risks/{caseId}`
- **Action Plan**: `POST /strategy/action-plan/{caseId}`

### 12. Misconduct (4 endpoints)

Prosecutorial misconduct detection:

- **Analyze**: `POST /misconduct/analyze/{caseId}`
- **Dismissal Motion**: `POST /misconduct/dismissal-motion/{caseId}`
- **Complaint**: `POST /misconduct/complaint/{caseId}`
- **Appeal**: `POST /misconduct/appeal/{caseId}`

Example:
```bash
POST /api/misconduct/analyze/12345
{
  "options": {
    "include_patterns": true,
    "min_severity": 50
  }
}
```

Types detected:
- Witness tampering
- Evidence suppression
- False statements
- Improper closing arguments
- Selective prosecution
- Conflict of interest

### 13. Topics (4 endpoints)

Topic-based abuse analysis:

- **List Topics**: `GET /topics`
- **Analyze Topic**: `POST /topics/{topic}/analyze/{caseId}`
- **Get Statistics**: `GET /topics/{topic}/statistics`
- **Compare Regions**: `GET /topics/{topic}/compare-regions`

Available topics:
- `drug_charge_severity`: Drug overcharging analysis
- `home_search_abuse`: Disproportionate home searches
- `bail_denial`: Excessive bail denial (planned)
- `pretrial_detention`: Excessive detention (planned)
- `witness_intimidation`: Witness intimidation (planned)

### 14. Collaboration (4 endpoints)

Multi-agent collaboration:

- **Solve**: `POST /collaboration/solve`
- **Get Details**: `GET /collaboration/{id}`
- **Recent**: `GET /collaboration/recent`
- **Stats**: `GET /collaboration/stats`

Specialist agents:
- Research Specialist
- Precedent Analyst
- Strategy Specialist
- Risk Analyst

### 15. Monitoring (4 endpoints)

Agent monitoring and health:

- **Health**: `GET /monitoring/health`
- **Statistics**: `GET /monitoring/statistics`
- **Recent Runs**: `GET /monitoring/recent-runs`
- **Failed Jobs**: `GET /monitoring/failed-jobs`

### 16. Graph (3 endpoints)

Neo4j graph visualization:

- **Visualize**: `GET /graph/visualize/{nodeId}`
- **Subgraph**: `POST /graph/subgraph`
- **Stats**: `GET /graph/stats`

### 17. Honeypot (38 endpoints)

Security honeypot endpoints for detecting attackers.

## Croatian Legal Context

### Law Codes

The API handles Croatian law codes:

- **KZ**: Kazneni zakon (Criminal Code)
- **ZKP**: Zakon o kaznenom postupku (Criminal Procedure Act)
- **ZSZD**: Zakon o sudovima za mladež (Juvenile Courts Act)
- **Ustav RH**: Ustav Republike Hrvatske (Constitution)
- **Prekršajni zakon**: Misdemeanor Act

### Court Hierarchy

- **Općinski sud**: Municipal Court
- **Županijski sud**: County Court
- **Vrhovni sud**: Supreme Court
- **Ustavni sud**: Constitutional Court

### Legal Procedures

- **Žalba**: Regular appeal
- **Zaštita zakonitosti**: Protection of legality
- **Ustavna tužba**: Constitutional complaint
- **Zahtjev za obustavu postupka**: Motion for dismissal

## Rate Limiting

- **Standard Endpoints**: 60 requests/minute
- **MCP Endpoints**: Tool-specific limits
- **Info Endpoints**: 60 requests/minute

## Error Responses

### 401 Unauthorized
```json
{
  "error": "Unauthorized"
}
```

### 429 Too Many Requests
```json
{
  "error": "Too many requests",
  "retry_after": 60
}
```

## Example Workflows

### 1. Search for Laws and Analyze

```bash
# Step 1: Search for relevant laws
curl -X POST https://api.legal-ai.example.com/api/search/laws \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "query": "nezakonita pretraga",
    "limit": 10
  }'

# Step 2: Analyze conflict between provisions
curl -X POST https://api.legal-ai.example.com/api/reasoning/analyze-conflict \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "provisions": [
      {"law_code": "ZKP", "article": "215", "text": "..."},
      {"law_code": "Ustav", "article": "35", "text": "..."}
    ]
  }'
```

### 2. Detect Misconduct and Generate Motion

```bash
# Step 1: Analyze case for misconduct
curl -X POST https://api.legal-ai.example.com/api/misconduct/analyze/12345 \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "options": {
      "include_patterns": true,
      "min_severity": 50
    }
  }'

# Step 2: Generate dismissal motion
curl -X POST https://api.legal-ai.example.com/api/misconduct/dismissal-motion/12345 \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "min_severity": 85
  }'
```

### 3. Multi-Agent Problem Solving

```bash
# Start collaboration with specialist agents
curl -X POST https://api.legal-ai.example.com/api/collaboration/solve \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "problem": "Kako osporiti nezakonitu pretragu stana?",
    "case_id": "12345"
  }'

# Get collaboration results
curl https://api.legal-ai.example.com/api/collaboration/{id} \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### 4. Predict Case Outcome

```bash
# Get comprehensive analytics
curl -X POST https://api.legal-ai.example.com/api/analytics/comprehensive/12345 \
  -H "Authorization: Bearer YOUR_TOKEN"

# Batch predict multiple cases
curl -X POST https://api.legal-ai.example.com/api/analytics/batch-predict \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "case_ids": ["12345", "12346", "12347"]
  }'
```

## SDK Generation

Generate client SDKs using OpenAPI Generator:

### JavaScript/TypeScript
```bash
openapi-generator-cli generate \
  -i public/openapi.yaml \
  -g typescript-axios \
  -o clients/typescript
```

### Python
```bash
openapi-generator-cli generate \
  -i public/openapi.yaml \
  -g python \
  -o clients/python
```

### PHP
```bash
openapi-generator-cli generate \
  -i public/openapi.yaml \
  -g php \
  -o clients/php
```

### Java
```bash
openapi-generator-cli generate \
  -i public/openapi.yaml \
  -g java \
  -o clients/java
```

## Testing

### Using cURL

```bash
# Test search endpoint
curl -X POST http://localhost:8000/api/search/laws \
  -H "Authorization: Bearer test-token" \
  -H "Content-Type: application/json" \
  -d '{"query": "criminal procedure", "limit": 5}'
```

### Using Postman

1. Import `public/openapi.yaml` into Postman
2. Set up environment variable for API token
3. Use collection runner for automated testing

### Using HTTPie

```bash
# HTTPie example
http POST localhost:8000/api/search/laws \
  Authorization:"Bearer test-token" \
  query="criminal procedure" \
  limit:=5
```

## Validation

The OpenAPI specification can be validated using:

```bash
# Install OpenAPI validator
npm install -g @stoplight/spectral-cli

# Validate spec
spectral lint public/openapi.yaml
```

## Contributing

When adding new endpoints:

1. Update `routes/api.php` or `routes/misconduct.php`
2. Add endpoint to `public/openapi.yaml`:
   - Define path in `paths` section
   - Add request/response schemas in `components/schemas`
   - Update tags if needed
3. Test the endpoint
4. Update this documentation

## Resources

- [OpenAPI Specification](https://spec.openapis.org/oas/v3.0.3)
- [Swagger UI](https://swagger.io/tools/swagger-ui/)
- [OpenAPI Generator](https://openapi-generator.tech/)
- [Postman](https://www.postman.com/)

## Support

For API support:
- Email: support@legal-ai.example.com
- Documentation: http://localhost:8000/api-docs.html

## License

Proprietary - See LICENSE file for details.

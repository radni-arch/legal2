# Services Test Summaries

Test documentation for core application services and integrations.

---

## Components Covered

### RAG Services

#### RagOrchestrator
**File**: `app/Services/RagOrchestrator.php`
**Test File**: `tests/Unit/Services/RagOrchestratorTest.php`

Main orchestrator for Retrieval-Augmented Generation pipeline:

**Pipeline Steps**:
1. **Query Normalization** - Standardize query text
2. **Citation Detection** - Identify law/case citations (regex)
3. **Hybrid Retrieval**:
   - Vector search (semantic similarity)
   - Keyword search (BM25-like)
   - Graph search (Neo4j citation network)
4. **Result Merging** - Reciprocal Rank Fusion (RRF)
5. **Diversity** - Maximal Marginal Relevance (MMR)
6. **Confidence Scoring** - Calculate retrieval confidence
7. **Context Assembly** - Prepare for LLM consumption

**Key Capabilities**:
- Multi-corpus search (laws, decisions, cases)
- Corpus-specific caps (configurable limits)
- Deduplication across sources
- Performance timing
- Comprehensive error handling

**Configuration**: `config/rag.php`

---

#### LawSearchService
**File**: `app/Services/LawSearchService.php`
**Test File**: `tests/Unit/Services/LawSearchServiceTest.php`

Law corpus search with hybrid approach:
- Vector search (semantic)
- Keyword search (article numbers, titles)
- Article citation extraction
- ZKP, KZ, Ustav RH support

**Key Capabilities**:
- Article-level granularity
- Citation-aware search
- Law version handling
- Keyword highlighting

---

#### CaseSearchService
**File**: `app/Services/CaseSearchService.php`
**Test File**: `tests/Unit/Services/CaseSearchServiceTest.php`

Case document search:
- Vector search across case documents
- Case-scoped filtering
- Document type filtering
- Temporal filtering (date ranges)

**Key Capabilities**:
- Multi-document retrieval
- Chunk-level search
- Case relationship awareness
- Attorney permission filtering

---

### Graph Services

#### GraphDatabaseService
**File**: `app/Services/GraphDatabaseService.php`
**Test File**: `tests/Unit/Services/GraphDatabaseServiceTest.php`

Neo4j graph database operations:

**CRUD Operations**:
- `upsertNode()` - Create/update nodes
- `createRelationship()` - Create edges
- `deleteNode()` - Remove nodes
- `deleteRelationship()` - Remove edges
- `getNode()` - Retrieve single node
- `batchUpsertNodes()` - Bulk operations

**Query Operations**:
- `run()` - Execute Cypher queries
- `findSimilar()` - Find similar nodes (vector similarity)
- `getRelated()` - Get related nodes via relationships
- `findPath()` - Find shortest path between nodes
- `getNodeWithRelationships()` - Get node + relationships

**Schema Management**:
- `initializeSchema()` - Create indexes and constraints
- `clearAll()` - Delete all nodes (dangerous!)
- Graph statistics

**Node Types**:
- Decision, Law, Case, Keyword, Topic, Court, LegalConcept

**Relationship Types**:
- CITES, REFERENCES, RELATES_TO, HAS_KEYWORD, SIMILAR_TO

**Configuration**: `config/neo4j.php`

---

### Integration Services

#### OpenAIService
**File**: `app/Services/OpenAIService.php`
**Test File**: `tests/Unit/Services/OpenAIServiceTest.php`

OpenAI API integration for text generation and embeddings:

**Capabilities**:
- Chat completions (GPT-4o, GPT-4o-mini)
- JSON output (structured responses)
- Embeddings (text-embedding-3-small)
- Streaming responses
- Error handling and retry logic
- Token counting (tiktoken)
- Cost tracking

**Models**:
- **GPT-4o**: Complex legal analysis, motion generation
- **GPT-4o-mini**: Context analysis, classification tasks
- **text-embedding-3-small**: Vector embeddings (1536 dimensions)

**Configuration**: `config/openai.php`

---

#### OdlukeClient
**File**: `app/Services/Odluke/OdlukeClient.php`
**Test File**: `tests/Unit/Services/OdlukeClientTest.php`

HTTP client for odluke.sudovi.hr with robustness patterns:

**Robustness Features**:
- **Circuit Breaker** (3 failures → open state for 60s)
- **Connection Pooling** (Laravel HTTP client)
- **Exponential Backoff** (700ms + 800ms on errors)
- **Rate Limiting** (30 RPM configurable)
- **Retry Logic** (3 retries with backoff)
- **Request/Response Logging** (DEBUG level)

**Methods**:
- `searchDecisions()` - Search by query
- `getDecision()` - Fetch by ID
- `getDecisionMetadata()` - Get metadata only
- `healthCheck()` - Check service availability

**Configuration**: `config/odluke.php`

---

#### EkomService
**File**: `app/Services/EkomService.php`
**Test File**: `tests/Unit/Services/EkomServiceTest.php`

Croatian e-courts (EKOM) system integration:

**Capabilities**:
- Sync case records (predmeti)
- Sync submissions (podnesci)
- Sync dispatches (otpravci)
- Incremental sync (only new/updated)
- Full sync (all records)

**Key Features**:
- Authentication with EKOM API
- Pagination handling
- Rate limiting
- Data validation
- Database persistence

---

#### EoglasnaService
**File**: `app/Services/EoglasnaService.php`
**Test File**: `tests/Unit/Services/EoglasnaServiceTest.php`

Public court notices (eOglasna ploča) monitoring:

**Capabilities**:
- Monitor all courts
- Monitor specific courts (Osijek)
- Keyword-based filtering
- Duplicate detection
- Email notifications

**Key Features**:
- HTML parsing
- Date extraction
- Court identification
- Notification dispatch

---

### Monitoring Services

#### ApplicationMonitor
**File**: `app/Services/Monitoring/ApplicationMonitor.php`
**Test File**: `tests/Unit/Services/ApplicationMonitorTest.php`

Production monitoring and metrics collection:

**Metrics Recorded**:
- HTTP requests (method, status, duration)
- Database queries (count, time)
- Cache operations (hits, misses, writes)
- Queue jobs (processed, failed)
- Errors and exceptions
- System health

**Reporting**:
- `getHealthStatus()` - Overall health
- `getPerformanceReport()` - Performance metrics
- `getSystemHealthReport()` - Comprehensive report
- Error rate calculation
- Job failure rate calculation

**Key Feature**: Never throws exceptions (metrics recording should never break the app)

**Configuration**: `config/monitoring.php`

---

### Fact Extraction Services

#### FactExtractionService
**File**: `app/Services/FactExtractionService.php`
**Test File**: `tests/Unit/Services/FactExtractionServiceTest.php`

Structured fact extraction from court decisions:

**Extraction Types**:
- **Pattern-based**: Parties, dates, case numbers, procedural info
- **LLM-based**: Legal issues, holdings, arguments, reasoning

**Capabilities**:
- Regex-based pattern matching
- AI-powered complex extraction
- Structured output (JSON schema)
- Confidence scoring
- Multi-language support (Croatian)

---

## Test Coverage Areas

### Unit Tests

**Service Initialization**:
- ✅ Dependency injection
- ✅ Configuration loading
- ✅ Database connections
- ✅ External API clients

**Core Functionality**:
- ✅ Search/query execution
- ✅ Data transformation
- ✅ Result formatting
- ✅ Error handling

**Integration Points**:
- ✅ Database queries (PostgreSQL, Neo4j)
- ✅ External APIs (OpenAI, EKOM, Odluke)
- ✅ Cache operations (Redis)
- ✅ Queue jobs (database queue)

---

### Integration Tests

**Full Workflows**:
- ✅ Query → RAG → Results
- ✅ Ingestion → Storage → Search
- ✅ Analysis → Report → Motion

**Performance**:
- ✅ Response times < 500ms (p95)
- ✅ Database query times < 100ms (p95)
- ✅ Cache hit rates > 80%

**Error Scenarios**:
- ✅ API failures (OpenAI, EKOM, Odluke)
- ✅ Database connection errors
- ✅ Timeout handling
- ✅ Rate limit handling
- ✅ Circuit breaker activation

**Robustness**:
- ✅ Retry logic (exponential backoff)
- ✅ Transaction handling (rollback on errors)
- ✅ Graceful degradation (partial results)
- ✅ Fallback strategies

---

## Running Tests

### All Service Tests
```bash
./scripts/run-tests.sh --filter=ServiceTest
```

### Specific Services
```bash
# RAG services
./scripts/run-tests.sh --filter=RagOrchestratorTest
./scripts/run-tests.sh --filter=LawSearchServiceTest
./scripts/run-tests.sh --filter=CaseSearchServiceTest

# Graph services
./scripts/run-tests.sh --filter=GraphDatabaseServiceTest

# Integration services
./scripts/run-tests.sh --filter=OpenAIServiceTest
./scripts/run-tests.sh --filter=OdlukeClientTest
./scripts/run-tests.sh --filter=EkomServiceTest
./scripts/run-tests.sh --filter=EoglasnaServiceTest

# Monitoring services
./scripts/run-tests.sh --filter=ApplicationMonitorTest

# Fact extraction
./scripts/run-tests.sh --filter=FactExtractionServiceTest
```

---

## Test Summaries

*(Add test summary files here as they are created)*

- [ ] `rag-orchestrator-test-summary.md`
- [ ] `law-search-service-test-summary.md`
- [ ] `case-search-service-test-summary.md`
- [ ] `graph-database-service-test-summary.md`
- [ ] `openai-service-test-summary.md`
- [ ] `odluke-client-test-summary.md`
- [ ] `ekom-service-test-summary.md`
- [ ] `eoglasna-service-test-summary.md`
- [ ] `application-monitor-test-summary.md`
- [ ] `fact-extraction-service-test-summary.md`

---

## Known Issues

*(Document any known issues, flaky tests, or technical debt here)*

**OdlukeClient Circuit Breaker**:
- Circuit breaker may open during tests if odluke.sudovi.hr is slow
- Tests should reset circuit breaker state before running
- Consider mocking external calls for unit tests

**OpenAI Rate Limits**:
- Tests may hit OpenAI rate limits during parallel execution
- Use `composer test` (sequential) instead of `composer test:parallel` for OpenAI-heavy tests
- Consider using test doubles/mocks for unit tests

**Neo4j Connection**:
- Neo4j must be running for graph tests
- Use `NEO4J_ENABLED=false` environment variable to skip graph tests
- Docker container preferred for consistent test environment

---

**Last Updated**: 2025-11-09

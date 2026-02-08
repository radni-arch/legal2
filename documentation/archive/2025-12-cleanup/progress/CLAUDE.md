

# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

AI Legal War Machine - A comprehensive suite of AI-powered legal defense tools for Croatian criminal defense attorneys. Built on Laravel 11 with OpenAI GPT-4o/GPT-4o-mini integration, combining vector search, graph databases (Neo4j), AWS Textract OCR, and autonomous AI agents.

**Tech Stack**: Laravel 11, PHP 8.2+, PostgreSQL, Neo4j, AWS (S3, Textract), OpenAI API, MCP (Model Context Protocol), Vizra ADK

## Common Commands

### Development
```bash
# Start all development services (server, queue, logs, vite)
composer dev

# Start server only
php artisan serve

# Run queue worker
php artisan queue:work --queue=textract,agents,default --tries=1

# Watch logs
php artisan pail --timeout=0

# Frontend build
npm run dev        # Development
npm run build      # Production
```

### Testing
```bash
# Quick test (SQLite in-memory)
composer test

# Full integrated tests (PostgreSQL test DB)
composer test:integrated

# Setup test database + run tests
composer test:all

# Test suites
composer test:unit         # Unit tests only
composer test:feature      # Feature tests only

# Advanced options
composer test:coverage     # With coverage (min 80%)
composer test:parallel     # Parallel execution
composer test:quick        # Parallel + stop on first failure

# Run specific test
./scripts/run-tests.sh --filter=DrugChargeAbuseDetectorTest
```

### Test Database
```bash
# Setup/refresh test database (copies from production)
composer test:setup
# or
php artisan test:setup-db

# Force recreation
./scripts/setup-test-db.sh --force
```

**Important**: Tests use `DatabaseTransactions` trait for automatic rollback. Never use `RefreshDatabase` - the test database is a production copy that stays persistent.

### Code Quality
```bash
# Format code (Laravel Pint)
./vendor/bin/pint

# Static analysis
php artisan config:clear --ansi
```

## Architecture

### Modular Legal Defense System

The application uses a modular architecture with domain-specific modules:

**`app/Modules/`** - Core legal defense functionality:
- **Evidence/** - Evidence analysis, recontextualization, admissibility checking, suppression motions
- **Misconduct/** - Prosecutorial misconduct detection (6 types), dismissal motions, ethics complaints
- **Topics/** - Modular abuse detection (drug overcharging, home search abuse)
- **HomeSearch/** - Disproportionate home search warrant detection
- **Defence/** - Defense strategy analysis and recommendations

Each module is self-contained with its own services, controllers, and routes.

### Vector Search & Graph RAG

**Vector Stores** (`app/Services/*VectorStoreService.php`):
- `LawVectorStoreService` - Croatian laws (ZKP, Kazneni zakon, Ustav RH)
- `CourtDecisionVectorStoreService` - Court decisions from odluke.sudovi.hr
- `CaseVectorStoreService` - Internal case documents
- `TextractVectorStoreService` - OCR'd PDF documents

**Graph Database** (Neo4j):
- Node types: Law, Case, Keyword, Topic, Court, LegalConcept
- Relationships: CITES, REFERENCES, RELATES_TO, HAS_KEYWORD, SIMILAR_TO
- Config: `config/neo4j.php`
- Auto-sync enabled by default: `NEO4J_AUTO_SYNC=true`

**Key Services**:
- `app/Services/Odluke/OdlukeClient.php` - HTTP client for odluke.sudovi.hr (has circuit breaker, connection pooling, backoff)
- `app/Services/Odluke/OdlukeIngestService.php` - Ingests court decisions to vector store + Neo4j
- `app/GraphQL/AutoDiscovery/GraphQLAutoClient.php` - Auto-discovery GraphQL client

### Autonomous AI Agents

**Location**: `app/Agents/`
- `AutonomousResearchAgent.php` - Self-evaluating research agent (iterative improvement)
- `DecisionDiscoveryAgent.php` - Discovers and analyzes court decisions
- `OdlukeAgent.php` - MCP-powered agent for odluke.sudovi.hr

**Configuration**: `config/agent.php`
- Evaluation weights, thresholds, token/cost budgets
- Max iterations, time limits, safety limits

**MCP Tools** (`app/Mcp/Tools/`):
- Decision search, citation extraction, fact comparison, similarity search
- Law article search and retrieval
- Case search

**Queues**: Agents run on `agents` queue, Textract on `textract` queue

### AWS Textract Pipeline

**Pipeline Steps** (`app/Pipelines/Textract/`):
1. `DownloadDriveFileStep` - Fetch from Google Drive
2. `UploadInputToS3Step` - Upload to S3
3. `StartAnalysisStep` - Start Textract job
4. `WaitAndFetchStep` - Poll for completion
5. `CollectLinesStep` - Extract LINE blocks
6. `ReconstructPdfStep` - Overlay invisible text (FPDI + TCPDF)
7. `SaveResultsStep` - Store JSON + searchable PDF to S3

**Job**: `app/Jobs/ProcessDrivePdfJob.php` (on `textract` queue)

**Commands**:
```bash
php artisan textract:process-drive-folder FOLDER_ID --limit=3
```

**Config**: `config/textract.php`, S3 prefixes in `.env`

### External Integrations

**EKOM** (`app/Services/EkomService.php`) - Croatian e-courts system:
- Sync predmeti (cases), podnesci (submissions), otpravci (dispatches)
- Commands: `php artisan ekom:sync-predmeti`, `ekom:sync-podnesci`, etc.

**Eoglasna** (`app/Services/EoglasnaService.php`) - Public court notices:
- Monitor Osijek courts, keyword tracking
- Commands: `php artisan eoglasna:watch`, `eoglasna:watch-osijek`

**Odluke.sudovi.hr** (`app/Services/Odluke/OdlukeClient.php`):
- Circuit breaker pattern (3 failures → open)
- Connection pooling, exponential backoff
- Rate limiting: `ODLUKE_RPM=30` (requests per minute)

### API Structure

**Routes**:
- `/api/openai/*` - OpenAI proxy (requires `api.token` middleware)
- `/api/evidence/*` - Evidence analysis, recontextualization, suppression motions
- `/api/misconduct/*` - Misconduct detection, dismissal motions, complaints
- `/api/topics/*` - Topic framework (drug charges, home searches)
- `/mcp/*` - MCP server endpoints

**Authentication**: API token middleware (`config/mcp.php` - `MCP_API_TOKEN`)

### Configuration Files

Key configs:
- `config/odluke.php` - Odluke.sudovi.hr client (timeout, retry, rate limits)
- `config/neo4j.php` - Graph database (nodes, relationships, sync settings)
- `config/agent.php` - Autonomous agent evaluation, limits, scheduling
- `config/openai.php` - OpenAI models, timeouts, retry
- `config/textract.php` - AWS Textract pipeline
- `config/mcp.php` - MCP API token, rate limiting

## Development Patterns

### Writing Tests

Always use `UsesTestDatabase` trait (from `tests/Concerns/UsesTestDatabase.php`):

```php
use Tests\Concerns\UsesTestDatabase;

class MyTest extends TestCase
{
    use UsesTestDatabase;

    public function test_something()
    {
        // Test runs in transaction, auto-rolled back
        $case = Case::factory()->create();
        // ... assertions
    }
}
```

**Never** use `RefreshDatabase` - tests rely on production-like data in persistent test DB.

### Running Single Tests

```bash
# By class name
./scripts/run-tests.sh --filter=DrugChargeAbuseDetectorTest

# By method
./scripts/run-tests.sh --filter=test_drug_overcharge_detection

# Multiple patterns
./scripts/run-tests.sh --filter="Misconduct|Evidence"
```

### Offline Testing with Http::fake()

**All tests should run offline** without requiring OpenAI API keys or making real API calls.

Use `Http::fake()` to mock external APIs:

```php
use Illuminate\Support\Facades\Http;

protected function setUp(): void
{
    parent::setUp();

    // Mock OpenAI embeddings API
    Http::fake([
        'api.openai.com/v1/embeddings' => Http::response([
            'data' => [['embedding' => array_fill(0, 1536, 0.1)]],
        ], 200),
    ]);
}

public function test_search_pipeline()
{
    // Test runs completely offline - no API key needed, no costs
    $results = $this->searchService->search('test query');
    $this->assertNotEmpty($results);
}
```

**Benefits:**
- ✅ No API costs
- ✅ Fast execution (no network latency)
- ✅ Works in CI without API keys
- ✅ Reliable (no rate limits or network issues)
- ✅ Offline development

See [docs/TESTING.md - Offline Testing](docs/TESTING.md#offline-testing-with-external-apis) for comprehensive examples.

### Working with Vector Stores

```php
use App\Services\CourtDecisionVectorStoreService;

$vectorStore = app(CourtDecisionVectorStoreService::class);

// Ingest decision
$vectorStore->ingest($decisionText, [
    'decision_id' => 'dec-123',
    'court' => 'Županijski sud u Osijeku',
    'date' => '2025-01-15',
]);

// Search
$results = $vectorStore->search('proportionality of home search', limit: 10);
```

### Working with Neo4j Graph

Graph sync happens automatically during ingestion if `NEO4J_AUTO_SYNC=true`.

Disable for specific ingestion:
```php
$result = $service->ingestByIds(['decision-id-1'], ['sync_graph' => false]);
```

Query graph directly:
```bash
php artisan graph:query "MATCH (d:Decision)-[:CITES]->(l:Law) RETURN d, l LIMIT 10"
php artisan graph:stats
```

### Working with Modules

Modules are instantiated via service container:

```php
use App\Modules\Misconduct\ProsecutorialMisconductModule;
use App\Modules\Evidence\EvidenceAnalysisModule;

$misconductModule = app(ProsecutorialMisconductModule::class);
$result = $misconductModule->analyzeMisconduct($caseId);

if ($result['severity_score'] >= 85) {
    $motion = $misconductModule->generateDismissalMotion($caseId);
}
```

### Queue Worker Management

Agents and Textract jobs run on separate queues:
```bash
# Process all queues
php artisan queue:work --queue=textract,agents,default --tries=1

# Process specific queue
php artisan queue:work --queue=agents
```

## Croatian Legal Context

This system operates under Croatian law. All modules cite proper legal authorities:

- **ZKP** (Zakon o kaznenom postupku) - Criminal Procedure Act
- **Ustav RH** (Ustav Republike Hrvatske) - Croatian Constitution
- **KZ** (Kazneni zakon) - Criminal Code
- **Zakon o Državnom odvjetništvu** - State Attorney Act

Legal documents and motions are generated in Croatian. Citations follow Croatian legal format (e.g., "ZKP Članak 9").

## Important Implementation Notes

### OdlukeClient Robustness

When modifying `app/Services/Odluke/OdlukeClient.php`:
- Circuit breaker is already implemented (3 failures → open state)
- Connection pooling uses Laravel HTTP client options
- Exponential backoff configured via `config/odluke.php` (rpm, backoff_ms, delay_ms)
- Add DEBUG-level logging for request/response
- Validate response structure before parsing
- Write unit tests for error scenarios (HTTP 500, timeout, malformed JSON)

### Test Database Strategy

The test database is a **copy** of production, not an empty schema:
1. Run `composer test:setup` once to create test DB
2. Tests use `DatabaseTransactions` for automatic rollback
3. Never run migrations in tests - they'll fail or corrupt the test DB
4. To refresh test data: `composer test:setup` (re-copies from production)

### Adding New Abuse Topics

The Topic Framework is modular. To add a new topic:

1. Create analyzer: `app/Modules/Topics/Analyzers/NewTopicDetector.php`
2. Implement `analyzeCase()`, `getStatistics()`, `compareRegions()`
3. Add route: `routes/api.php` → `/api/topics/new_topic/*`
4. Write tests: `tests/Unit/Topics/NewTopicDetectorTest.php`
5. Update docs: Add to README.md Topic Framework section

See `DrugChargeAbuseDetector` as reference implementation.

## Web Interface

**Legal Playground**: `http://localhost/playground` (after auth)
- Test all modules (Evidence, Misconduct, Topics)
- Dark-themed interface
- Real-time results

**Other Livewire Components**:
- `http://localhost/textract` - Textract Manager
- `http://localhost/timeline` - Case timeline visualization
- `http://localhost/graph` - Neo4j graph viewer

## Environment Setup

Required `.env` variables:
```env
# OpenAI
OPENAI_API_KEY=sk-...

# Database
DB_CONNECTION=pgsql
DB_DATABASE=your_db
DB_USERNAME=your_user
DB_PASSWORD=your_password

# Neo4j
NEO4J_ENABLED=true
NEO4J_URI=bolt://localhost:7687
NEO4J_PASSWORD=your_password

# AWS (for Textract)
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_BUCKET=your-bucket
AWS_DEFAULT_REGION=eu-central-1

# Google Drive (for Textract pipeline)
GOOGLE_APPLICATION_CREDENTIALS=/path/to/service-account.json
GOOGLE_DRIVE_FOLDER_ID=...

# Odluke.sudovi.hr
ODLUKE_RPM=30
ODLUKE_DELAY_MS=700
ODLUKE_BACKOFF_MS=800

# Queue
QUEUE_CONNECTION=database

# MCP
MCP_API_TOKEN=your-secure-token
```

## Troubleshooting

**Tests failing with "database doesn't exist"**:
```bash
composer test:setup
```

**Odluke.sudovi.hr rate limiting**:
- Circuit breaker opens after 3 failures
- Wait 60 seconds for automatic retry
- Or adjust `ODLUKE_RPM` in `.env`

**Neo4j connection errors**:
```bash
# Check Neo4j is running
docker ps | grep neo4j

# Disable Neo4j sync temporarily
NEO4J_ENABLED=false php artisan test
```

**Queue jobs stuck**:
```bash
# Clear failed jobs
php artisan queue:flush

# Restart worker
php artisan queue:restart
```

## Resources

- [README.md](README.md) - Feature overview, API reference
- [TESTING.md](TESTING.md) - Comprehensive testing guide
- [API_DOCUMENTATION.md](API_DOCUMENTATION.md) - API endpoint details
- Documentation in `docs/` for each module

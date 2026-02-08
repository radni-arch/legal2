# Test Summaries - AI Legal War Machine

**Organized test documentation and summaries by component category**

This directory contains detailed test summaries, test plans, and testing documentation organized by the major architectural components of the AI Legal War Machine.

---

## Directory Structure

| Category | Path | Description |
|----------|------|-------------|
| **Vector Stores** | [vector-stores/](vector-stores/) | Tests for vector search and embedding services |
| **Commands** | [commands/](commands/) | Tests for Artisan commands and CLI tools |
| **Modules** | [modules/](modules/) | Tests for domain modules (Evidence, Misconduct, Topics) |
| **Pipelines** | [pipelines/](pipelines/) | Tests for data processing pipelines (Textract, OCR) |
| **Services** | [services/](services/) | Tests for core services (RAG, GraphDB, OpenAI) |

---

## Categories Overview

### Vector Stores
**Path**: [vector-stores/](vector-stores/)

Test coverage for vector similarity search services:
- `LawVectorStoreService` - Croatian laws corpus (ZKP, KZ, Ustav RH)
- `CourtDecisionVectorStoreService` - Court decisions from odluke.sudovi.hr
- `CaseVectorStoreService` - Internal case documents
- `TextractVectorStoreService` - OCR'd PDF documents

**Key Test Areas**:
- Embedding generation and storage
- Cosine similarity calculations
- Vector search with filters
- Hybrid search (vector + keyword)
- Metadata handling

---

### Commands
**Path**: [commands/](commands/)

Test coverage for Artisan CLI commands:
- EKOM sync commands (`ekom:sync-predmeti`, `ekom:sync-podnesci`)
- Eoglasna monitoring (`eoglasna:watch`, `eoglasna:watch-osijek`)
- Textract processing (`textract:process-drive-folder`)
- Graph database commands (`graph:query`, `graph:stats`)
- Cache commands (`cache:warm`, `cache:monitor`)

**Key Test Areas**:
- Command execution and output
- Error handling and validation
- Progress indicators
- Database transactions
- External API integration

---

### Modules
**Path**: [modules/](modules/)

Test coverage for domain-specific legal defense modules:

**Evidence Module**:
- Evidence analysis and recontextualization
- Selective presentation detection (5 types)
- Admissibility checking
- Constitutional violation detection
- Suppression motion generation

**Misconduct Module**:
- Prosecutorial misconduct detection (6 types)
- Brady violation analysis
- Dismissal motion generation
- Ethics complaint generation

**Topics Framework**:
- Drug charge abuse detection
- Home search warrant abuse detection
- Regional comparison analytics
- Statistical analysis

**Key Test Areas**:
- Module initialization and configuration
- AI-powered analysis accuracy
- Legal document generation
- Scoring algorithms
- API endpoint integration

---

### Pipelines
**Path**: [pipelines/](pipelines/)

Test coverage for data processing pipelines:

**Textract Pipeline**:
1. Download from Google Drive
2. Upload to S3
3. Start Textract analysis
4. Poll for completion
5. Collect LINE blocks
6. Reconstruct searchable PDF
7. Save results to S3

**Odluke Ingestion Pipeline**:
1. Fetch decision from odluke.sudovi.hr
2. Parse HTML content
3. Generate embeddings
4. Store in vector database
5. Sync to Neo4j graph

**Key Test Areas**:
- Pipeline step execution
- Error recovery and retry logic
- AWS service integration
- Queue job processing
- File handling (PDF, images)

---

### Services
**Path**: [services/](services/)

Test coverage for core application services:

**RAG Services**:
- `RagOrchestrator` - Full RAG pipeline with RRF and MMR
- `LawSearchService` - Law corpus search
- `CaseSearchService` - Case document search

**Graph Services**:
- `GraphDatabaseService` - Neo4j CRUD operations
- Graph schema initialization
- Relationship management
- Cypher query execution

**Integration Services**:
- `OpenAIService` - GPT-4o/GPT-4o-mini integration
- `OdlukeClient` - Circuit breaker pattern for odluke.sudovi.hr
- `EkomService` - Croatian e-courts integration
- `EoglasnaService` - Public notices monitoring

**Monitoring Services**:
- `ApplicationMonitor` - Metrics and health monitoring
- Performance tracking
- Error rate monitoring

**Key Test Areas**:
- Service initialization and configuration
- External API integration
- Error handling and retry logic
- Performance optimization
- Caching strategies

---

## Test Organization Guidelines

### File Naming Convention

Test summary files should follow this pattern:
```
{component-name}-test-summary.md
```

Examples:
- `law-vector-store-service-test-summary.md`
- `rag-orchestrator-test-summary.md`
- `evidence-module-test-summary.md`
- `textract-pipeline-test-summary.md`

### Test Summary Template

Each test summary should include:

1. **Component Overview**
   - Purpose and responsibilities
   - Key dependencies
   - Integration points

2. **Test Coverage**
   - Unit tests
   - Feature/integration tests
   - Browser tests (if applicable)
   - Coverage percentage

3. **Key Test Scenarios**
   - Happy path tests
   - Error handling tests
   - Edge cases
   - Performance tests

4. **Known Issues**
   - Flaky tests
   - Skipped tests
   - Technical debt

5. **Running Tests**
   - Commands to run specific tests
   - Setup requirements
   - Expected output

---

## Running Tests

### Quick Test (SQLite in-memory)
```bash
composer test
```

### Full Integrated Tests (PostgreSQL)
```bash
composer test:integrated
```

### Setup Test Database + Run Tests
```bash
composer test:all
```

### Test Specific Category
```bash
# Vector stores
./scripts/run-tests.sh --filter=VectorStore

# Commands
./scripts/run-tests.sh --filter=CommandTest

# Modules
./scripts/run-tests.sh --filter=ModuleTest

# Pipelines
./scripts/run-tests.sh --filter=PipelineTest

# Services
./scripts/run-tests.sh --filter=ServiceTest
```

### Coverage Reports
```bash
composer test:coverage
```

---

## Test Database Strategy

**Important**: Tests use `UsesTestDatabase` trait (from `tests/Concerns/UsesTestDatabase.php`)

- Test database is a **copy** of production, not an empty schema
- Tests use `DatabaseTransactions` for automatic rollback
- **Never** use `RefreshDatabase` in tests
- To refresh test data: `composer test:setup`

### Setup Test Database
```bash
composer test:setup
# or
php artisan test:setup-db

# Force recreation
./scripts/setup-test-db.sh --force
```

---

## Contributing Test Summaries

When adding a new test summary:

1. **Identify the category**: Determine which directory (vector-stores, commands, modules, pipelines, services)
2. **Follow naming convention**: Use `{component-name}-test-summary.md`
3. **Use template structure**: Include all standard sections
4. **Update category README**: Add entry to the category's README.md
5. **Cross-reference**: Link to related test files in the codebase

---

## Test Resources

### Main Testing Documentation
- [TESTING.md](../../TESTING.md) - Comprehensive testing guide
- [API_DOCUMENTATION.md](../../API_DOCUMENTATION.md) - API endpoint testing
- [CLAUDE.md](../../CLAUDE.md) - Development patterns and conventions

### Test Directories in Codebase
```
tests/
├── Unit/              # Unit tests
├── Feature/           # Integration tests
├── Browser/           # Playwright browser tests
└── Concerns/          # Shared traits (UsesTestDatabase)
```

### Test Commands Reference
```bash
# All tests
composer test

# Unit tests only
composer test:unit

# Feature tests only
composer test:feature

# With coverage (min 80%)
composer test:coverage

# Parallel execution
composer test:parallel

# Parallel + stop on first failure
composer test:quick

# Run specific test
./scripts/run-tests.sh --filter=TestClassName
```

---

## Success Metrics

### Current Test Coverage
- **Overall**: Target 80%+
- **Services**: Critical services require 90%+
- **Modules**: Domain modules require 85%+
- **Commands**: CLI commands require 75%+

### Quality Gates
- ✅ All tests must pass before merging
- ✅ New features require test coverage
- ✅ Bug fixes require regression tests
- ✅ No skipped tests without documented reason

---

**Last Updated**: 2025-11-09
**Document Version**: 1.0
**Status**: Active - Test summaries being added incrementally

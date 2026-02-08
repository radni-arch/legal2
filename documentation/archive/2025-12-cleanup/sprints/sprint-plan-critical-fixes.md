# AI Legal War Machine - Critical Fixes Sprint Plan

**Created**: October 31, 2025
**Duration**: 5 Sprints (5 weeks)
**Goal**: Fix all critical issues and achieve 70%+ test coverage
**Team**: 3 parallel tracks (Developer A, B, C)

---

## Sprint Overview

| Sprint | Focus | Duration | Test Coverage Goal |
|--------|-------|----------|-------------------|
| Sprint 1 | Security + Testing Foundation | 1 week | 34% → 42% |
| Sprint 2 | Core Testing (Textract + Vector) | 1 week | 42% → 52% |
| Sprint 3 | UI Testing + Error Handling | 1 week | 52% → 62% |
| Sprint 4 | Service Testing + Database | 1 week | 62% → 70% |
| Sprint 5 | Final Testing + Polish | 1 week | 70% → 75%+ |

**Total Duration**: 5 weeks (40 hours/week per developer = 600 hours total)

---

# SPRINT 1: Security + Testing Foundation

**Duration**: Week 1 (Nov 4-8)
**Goal**: Fix ALL critical security issues, start testing infrastructure
**Coverage Target**: 34% → 42% (+8%)

## Track A: Security Fixes (Developer A) - 16 hours

### Day 1 (Monday) - Critical Security

**Task 1.A.1: Add API Authentication** (2 hours)
- **Priority**: CRITICAL
- **Files**:
  - `routes/api.php` (4 route groups)
  - `routes/misconduct.php`
- **Changes**:
  ```php
  // Change ALL 4 route groups from:
  ->middleware('throttle:60,1')
  // To:
  ->middleware(['api.token', 'throttle:60,1'])
  ```
- **Routes to fix**:
  1. `/api/reasoning/*` (5 endpoints)
  2. `/api/analytics/*` (5 endpoints)
  3. `/api/strategy/*` (5 endpoints)
  4. `/api/misconduct/*` (4 endpoints)
- **Test**: Create `tests/Feature/Api/AuthenticationTest.php` to verify all endpoints require tokens
- **Acceptance**: All 19 endpoints return 401 without valid token

**Task 1.A.2: Remove Hardcoded Password** (1 hour)
- **Priority**: CRITICAL
- **Files**:
  - `config/neo4j.php` line 17
  - `app/Services/Neo4jService.php` line 27
- **Changes**:
  ```php
  // BEFORE:
  'password' => env('NEO4J_PASSWORD', 'secret'),

  // AFTER:
  'password' => env('NEO4J_PASSWORD'),
  ```
- **Add validation**: In `app/Providers/AppServiceProvider.php` boot():
  ```php
  if (config('neo4j.enabled') && !config('neo4j.password')) {
      throw new \RuntimeException('NEO4J_PASSWORD must be set when Neo4j is enabled');
  }
  ```
- **Update**: `.env.example` with `NEO4J_PASSWORD=` (no default)
- **Acceptance**: App throws exception if Neo4j enabled without password

**Task 1.A.3: Fix Timing Attack** (30 min)
- **Priority**: CRITICAL
- **File**: `app/Http/Middleware/McpApiTokenAuth.php` line 46
- **Change**:
  ```php
  // BEFORE:
  if ($token !== $validToken) {

  // AFTER:
  if (!hash_equals($validToken, $token)) {
  ```
- **Test**: Add timing attack test (verify constant-time comparison)
- **Acceptance**: Token comparison uses `hash_equals()`

**Task 1.A.4: Implement Security Headers Middleware** (4 hours)
- **Priority**: HIGH
- **Create**: `app/Http/Middleware/SecurityHeaders.php`
- **Headers to add**:
  ```php
  X-Frame-Options: DENY
  X-Content-Type-Options: nosniff
  X-XSS-Protection: 1; mode=block
  Strict-Transport-Security: max-age=31536000; includeSubDomains
  Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'
  Referrer-Policy: strict-origin-when-cross-origin
  Permissions-Policy: geolocation=(), microphone=(), camera=()
  ```
- **Register**: In `bootstrap/app.php` middleware
- **Test**: Create `tests/Feature/SecurityHeadersTest.php`
- **Acceptance**: All responses include security headers

**Task 1.A.5: Fix XSS Vulnerabilities** (4 hours)
- **Priority**: HIGH
- **Files to fix** (8 templates):
  1. `resources/views/pdf/article.blade.php:30`
  2. `resources/views/livewire/timeline-page.blade.php:17`
  3. `resources/views/livewire/comparative-timeline-page.blade.php`
  4. `resources/views/livewire/gup-timeline.blade.php:81,86`
  5. `resources/views/livewire/transcript-previewer.blade.php`
  6. `resources/views/agent/run.blade.php:103`
- **Solution**: Install HTML Purifier
  ```bash
  composer require mews/purifier
  ```
- **Replace**:
  ```php
  // BEFORE:
  {!! $content !!}

  // AFTER:
  {!! clean($content) !!}
  ```
- **Test**: Create XSS attack test cases
- **Acceptance**: All user-generated content sanitized

**Task 1.A.6: Security Test Suite** (4 hours)
- **Create**: `tests/Feature/Security/SecurityAuditTest.php`
- **Tests**:
  - All API endpoints require authentication
  - Security headers present on all responses
  - XSS injection attempts blocked
  - SQL injection attempts blocked
  - CSRF protection enabled
- **Acceptance**: 100% security test coverage

### Day 2-5: Security Documentation & Validation

**Task 1.A.7: Security Documentation** (2 hours)
- **Create**: `docs/SECURITY.md`
- **Content**:
  - Authentication guide
  - Security headers explanation
  - XSS prevention strategy
  - API token management
  - Security best practices

---

## Track B: Textract Pipeline Testing (Developer B) - 40 hours

### Day 1-5 (Full Week)

**Task 1.B.1: EnsureJobStep Test** (3 hours)
- **Create**: `tests/Unit/Pipelines/Textract/EnsureJobStepTest.php`
- **File**: `app/Actions/Textract/EnsureJobStep.php`
- **Tests** (10 test methods):
  - Creates new TextractJob when none exists
  - Returns existing job if already created
  - Sets correct initial status (pending)
  - Associates job with case_id
  - Handles null case_id gracefully
  - Sets Google Drive file_id
  - Generates unique job ID
  - Stores metadata correctly
  - Handles database errors gracefully
  - Idempotent (safe to call multiple times)
- **Mocks**: TextractJob model, DB facade
- **Acceptance**: 100% code coverage, all edge cases tested

**Task 1.B.2: DownloadDriveFileStep Test** (4 hours)
- **Create**: `tests/Unit/Pipelines/Textract/DownloadDriveFileStepTest.php`
- **File**: `app/Actions/Textract/DownloadDriveFileStep.php`
- **Tests** (12 test methods):
  - Downloads file from Google Drive
  - Stores file in temp directory
  - Updates job status to 'downloading'
  - Handles Google Drive API errors (401, 403, 404, 500)
  - Retries on network failures (3 attempts)
  - Validates file is PDF
  - Checks file size limits (max 100MB)
  - Handles corrupted files
  - Cleans up temp files on failure
  - Records download duration
  - Updates job metadata with file size
  - Throws exception on final failure
- **Mocks**: GoogleDriveService, Storage facade
- **Acceptance**: All Google Drive error scenarios covered

**Task 1.B.3: UploadInputToS3Step Test** (3 hours)
- **Create**: `tests/Unit/Pipelines/Textract/UploadInputToS3StepTest.php`
- **File**: `app/Actions/Textract/UploadInputToS3Step.php`
- **Tests** (10 test methods):
  - Uploads PDF to S3 input bucket
  - Generates correct S3 key (textract/input/{job_id}.pdf)
  - Sets correct S3 metadata
  - Updates job with S3 URI
  - Handles S3 upload failures
  - Validates file exists before upload
  - Uses correct IAM credentials
  - Sets correct ACL (private)
  - Handles network timeouts
  - Records upload duration
- **Mocks**: Storage::fake('s3')
- **Acceptance**: 100% S3 upload scenarios covered

**Task 1.B.4: StartAnalysisStep Test** (4 hours)
- **Create**: `tests/Unit/Pipelines/Textract/StartAnalysisStepTest.php`
- **File**: `app/Actions/Textract/StartAnalysisStep.php`
- **Tests** (14 test methods):
  - Calls AWS Textract StartDocumentAnalysis API
  - Stores Textract job ID in database
  - Updates job status to 'processing'
  - Handles AWS Textract API errors
  - Validates required IAM permissions
  - Sets FeatureTypes (TABLES, FORMS, LAYOUT)
  - Sets NotificationChannel (SNS topic)
  - Handles throttling (rate limit exceeded)
  - Retries with exponential backoff
  - Handles invalid S3 URI errors
  - Handles document too large errors
  - Records API request/response
  - Handles unsupported document format
  - Sets correct ClientRequestToken for idempotency
- **Mocks**: AWS Textract SDK
- **Acceptance**: All AWS API error codes covered

**Task 1.B.5: WaitAndFetchStep Test** (5 hours)
- **Create**: `tests/Unit/Pipelines/Textract/WaitAndFetchStepTest.php`
- **File**: `app/Actions/Textract/WaitAndFetchStep.php`
- **Tests** (15 test methods):
  - Polls Textract GetDocumentAnalysis API
  - Waits for job to complete (status: SUCCEEDED)
  - Handles IN_PROGRESS status (waits and retries)
  - Handles FAILED status (throws exception)
  - Handles PARTIAL_SUCCESS status
  - Fetches all pages with pagination (NextToken)
  - Assembles complete results from multiple pages
  - Handles API throttling during polling
  - Respects max wait time (15 minutes)
  - Updates job progress percentage
  - Handles network timeouts during long waits
  - Exponential backoff between polls (1s, 2s, 4s, 8s, 10s max)
  - Handles temporary API failures gracefully
  - Records total processing time
  - Validates response structure
- **Mocks**: AWS Textract SDK, Clock
- **Acceptance**: All polling scenarios and edge cases covered

**Task 1.B.6: AnalyzeTextractLayoutStep Test** (4 hours)
- **Create**: `tests/Unit/Pipelines/Textract/AnalyzeTextractLayoutStepTest.php`
- **File**: `app/Actions/Textract/AnalyzeTextractLayout.php`
- **Tests** (12 test methods):
  - Parses Textract Blocks (PAGE, LINE, WORD, TABLE, CELL)
  - Reconstructs document structure
  - Identifies headers, paragraphs, lists
  - Extracts reading order
  - Handles multi-column layouts
  - Identifies tables and extracts structure
  - Handles rotated text
  - Calculates confidence scores per section
  - Identifies form fields (key-value pairs)
  - Handles missing or malformed blocks
  - Preserves spatial relationships
  - Generates layout metadata
- **Mocks**: Textract response JSON
- **Acceptance**: All block types and layouts covered

**Task 1.B.7: ExtractDocumentMetadataStep Test** (4 hours)
- **Create**: `tests/Unit/Pipelines/Textract/ExtractDocumentMetadataStepTest.php`
- **File**: `app/Actions/Textract/ExtractDocumentMetadata.php`
- **Tests** (16 test methods):
  - Extracts case numbers (patterns: XXXX/XX/XXXX)
  - Extracts court names (Croatian courts)
  - Extracts dates (decision date, filing date)
  - Extracts judge names
  - Extracts party names (plaintiff, defendant)
  - Extracts document type (presuda, rješenje, zapisnik)
  - Extracts legal citations (ZKP, ZPP, Ustav RH)
  - Handles multiple date formats
  - Handles missing metadata gracefully
  - Validates extracted data
  - Handles Croatian language text
  - Extracts JMBG, OIB numbers
  - Extracts addresses
  - Confidence scoring for each field
  - Handles OCR errors in metadata
  - Returns structured metadata object
- **Mocks**: TextractDocument with Croatian legal text
- **Acceptance**: All Croatian legal document patterns recognized

**Task 1.B.8: ReconstructPdfV2Step Test** (5 hours)
- **Create**: `tests/Unit/Pipelines/Textract/ReconstructPdfV2StepTest.php`
- **File**: `app/Actions/Textract/ReconstructPdfV2.php`
- **Tests** (14 test methods):
  - Generates searchable PDF from Textract results
  - Preserves original PDF images
  - Overlays OCR text invisibly
  - Maintains correct text positioning
  - Handles multi-page documents
  - Preserves tables and forms
  - Maintains reading order
  - Handles large documents (100+ pages)
  - Optimizes file size
  - Validates PDF/A compliance
  - Handles missing text blocks
  - Preserves metadata in PDF
  - Generates PDF with correct encoding (UTF-8)
  - Handles special characters (Croatian diacritics: č, ć, š, ž, đ)
- **Mocks**: TCPDF library
- **Acceptance**: Generated PDFs are searchable and valid

**Task 1.B.9: SaveAnalysisResultsStep Test** (3 hours)
- **Create**: `tests/Unit/Pipelines/Textract/SaveAnalysisResultsStepTest.php`
- **File**: `app/Actions/Textract/SaveAnalysisResults.php`
- **Tests** (10 test methods):
  - Saves raw Textract JSON to storage
  - Saves extracted text to database
  - Saves metadata to database
  - Updates job status to 'completed'
  - Records processing timestamps
  - Handles database transaction rollback on error
  - Compresses large JSON results
  - Validates JSON structure before saving
  - Updates statistics (page count, word count)
  - Triggers TextractCompleted event
- **Mocks**: Storage, DB, Event facades
- **Acceptance**: All data persisted correctly

**Task 1.B.10: UploadOutputToS3Step Test** (3 hours)
- **Create**: `tests/Unit/Pipelines/Textract/UploadOutputToS3StepTest.php`
- **File**: `app/Actions/Textract/UploadOutputToS3.php`
- **Tests** (10 test methods):
  - Uploads searchable PDF to S3 output bucket
  - Uploads metadata JSON to S3
  - Generates correct S3 keys
  - Sets correct S3 metadata
  - Updates job with output URIs
  - Handles S3 upload failures
  - Validates files before upload
  - Sets correct ACL (private)
  - Compresses large files
  - Records upload sizes
- **Mocks**: Storage::fake('s3')
- **Acceptance**: All output files uploaded

**Task 1.B.11: Integration Test - Full Pipeline** (2 hours)
- **Create**: `tests/Feature/TextractPipelineIntegrationTest.php`
- **Tests** (5 test methods):
  - End-to-end pipeline test (happy path)
  - Pipeline with Google Drive download failure
  - Pipeline with Textract processing failure
  - Pipeline with metadata extraction (Croatian document)
  - Pipeline resume after failure (checkpoint/resume)
- **Acceptance**: Full pipeline works end-to-end

---

## Track C: Configuration Documentation (Developer C) - 16 hours

### Day 1-2 (Monday-Tuesday)

**Task 1.C.1: Create CONFIGURATION.md** (8 hours)
- **Create**: `docs/CONFIGURATION.md`
- **Structure**:
  ```markdown
  # Configuration Guide

  ## Required Variables (Production)
  - NEO4J_* (15 vars)
  - Database configuration
  - OpenAI API keys
  - AWS credentials
  - Google Drive credentials

  ## Optional Variables
  - Vizra ADK (50 vars)
  - Agent Framework (22 vars)
  - MCP (20 vars)
  - Feature flags

  ## Environment Setup
  - Development setup
  - Production setup
  - Docker setup

  ## Integration Guides
  - Neo4j setup
  - OpenAI setup
  - AWS Textract setup
  - Google Drive setup
  ```
- **Document all 325 variables** with:
  - Description
  - Type (string, int, bool)
  - Required vs optional
  - Default value
  - Example
  - Environment (dev/prod)
- **Acceptance**: All variables documented

**Task 1.C.2: Expand .env.example** (4 hours)
- **Update**: `.env.example`
- **Add all 269 missing variables** grouped by feature:
  ```bash
  # ============================================
  # NEO4J GRAPH DATABASE
  # ============================================
  NEO4J_ENABLED=false
  NEO4J_URI=bolt://localhost:7687
  NEO4J_USERNAME=neo4j
  NEO4J_PASSWORD=
  # ... all 15 Neo4j vars

  # ============================================
  # VIZRA ADK FRAMEWORK
  # ============================================
  VIZRA_ADK_ENABLED=false
  # ... all 50 Vizra vars

  # ============================================
  # AGENT FRAMEWORK
  # ============================================
  AGENT_MAX_ITERATIONS=20
  # ... all 22 agent vars
  ```
- **Add comments** explaining each variable
- **Acceptance**: .env.example has 100% coverage

**Task 1.C.3: Create .env.production.example** (2 hours)
- **Create**: `.env.production.example`
- **Content**: Production-safe defaults
  - DEBUG=false
  - LOG_LEVEL=error
  - SESSION_SECURE_COOKIE=true
  - All passwords empty (no defaults)
  - All API keys empty (must be set)
- **Acceptance**: Production template ready

**Task 1.C.4: Create ConfigValidator** (2 hours)
- **Create**: `app/Services/ConfigValidator.php`
- **Create**: `app/Console/Commands/ValidateConfigCommand.php`
- **Validation**:
  ```php
  // Check required vars
  if (config('neo4j.enabled') && !config('neo4j.password')) {
      $errors[] = 'NEO4J_PASSWORD required when Neo4j enabled';
  }

  // Check production settings
  if (app()->isProduction() && config('app.debug')) {
      $errors[] = 'APP_DEBUG must be false in production';
  }
  ```
- **Command**: `php artisan config:validate`
- **Acceptance**: Validates all required config

---

# SPRINT 2: Core Testing (Textract + Vector Stores)

**Duration**: Week 2 (Nov 11-15)
**Goal**: Complete Textract tests, add Vector Store tests
**Coverage Target**: 42% → 52% (+10%)

## Track A: Textract Tests (Continued) (Developer A) - 20 hours

**Task 2.A.1: ListDrivePdfs Test** (3 hours)
**Task 2.A.2: ProcessDrivePdf Test** (3 hours)
**Task 2.A.3: Textract Error Scenarios** (4 hours)
**Task 2.A.4: Textract Performance Tests** (4 hours)
**Task 2.A.5: Textract Mock Data Generator** (3 hours)
**Task 2.A.6: Documentation** (3 hours)

## Track B: Vector Store Services (Developer B) - 32 hours

### CaseVectorStoreService

**Task 2.B.1: CaseVectorStoreService Test** (8 hours)
- **Create**: `tests/Unit/Services/CaseVectorStoreServiceTest.php`
- **File**: `app/Services/CaseVectorStoreService.php`
- **Tests** (18 test methods):
  - `storeDocument()` creates embedding and stores
  - Chunks document into 512-token chunks with 50 overlap
  - Calls OpenAI embeddings API (text-embedding-3-small)
  - Handles OpenAI API rate limits (retry with backoff)
  - Stores chunks with metadata (case_id, chunk_index, doc_id)
  - Generates 1536-dimensional embedding vectors
  - Uses pgvector for storage
  - `search()` finds similar chunks by cosine similarity
  - Filters by case_id when provided
  - Returns results ordered by similarity DESC
  - Handles empty query gracefully
  - `updateEmbedding()` regenerates embedding for chunk
  - `deleteDocument()` removes all chunks for doc_id
  - Transaction rollback on embedding API failure
  - Batch processing for large documents (100+ chunks)
  - Validates embedding dimensions (1536)
  - Handles database errors gracefully
  - Records embedding generation time
- **Mocks**: OpenAI API, DB facade, pgvector queries
- **Acceptance**: 100% code coverage, all edge cases

### CourtDecisionVectorStoreService

**Task 2.B.2: CourtDecisionVectorStoreService Test** (8 hours)
- **Create**: `tests/Unit/Services/CourtDecisionVectorStoreServiceTest.php`
- **File**: `app/Services/CourtDecisionVectorStoreService.php`
- **Tests** (18 test methods):
  - Same structure as CaseVectorStoreService
  - Additional: Citation metadata in chunks
  - Additional: Court hierarchy in search (Supreme Court > Appeal > First Instance)
  - Additional: Decision date relevance boost
  - Additional: Jurisdiction filtering (national vs regional)
- **Mocks**: OpenAI API, DB facade
- **Acceptance**: 100% coverage including court-specific logic

### LawVectorStoreService

**Task 2.B.3: LawVectorStoreService Test** (8 hours)
- **Create**: `tests/Unit/Services/LawVectorStoreServiceTest.php`
- **File**: `app/Services/LawVectorStoreService.php`
- **Tests** (20 test methods):
  - Article-level chunking (one chunk per article)
  - Metadata includes: law_number, article_number, chapter, section
  - Searches filter by law_number when provided
  - Searches filter by jurisdiction (national/regional)
  - Searches consider effective dates (active laws vs repealed)
  - Handles multi-article queries
  - Boosts exact article matches
  - `findSimilarArticles()` for cross-law comparison
  - `findArticleByNumber()` exact lookup
  - Validates law citation formats (NN XX/YY)
  - Handles Croatian law numbering
  - Caches frequent queries
  - Same standard tests as other services
- **Mocks**: OpenAI API, DB facade
- **Acceptance**: 100% coverage, law-specific logic tested

### TextractVectorStoreService

**Task 2.B.4: TextractVectorStoreService Test** (8 hours)
- **Create**: `tests/Unit/Services/TextractVectorStoreServiceTest.php`
- **File**: `app/Services/TextractVectorStoreService.php`
- **Tests** (20 test methods):
  - Processes textract documents in chunks
  - Handles large documents without memory exhaustion
  - Batch size configurable (default 100 chunks)
  - Associates with case_id and textract_job_id
  - Stores OCR confidence scores
  - Filters low-confidence chunks (< 80%)
  - Searches include confidence in ranking
  - `regenerateEmbeddings()` for all textract docs
  - Handles metadata fields (page_number, block_type)
  - Skips empty chunks
  - Validates minimum chunk size (50 chars)
  - Progress tracking for large batches
  - Memory usage monitoring
  - Transaction batching (commit every 100 chunks)
  - Handles database connection timeouts
  - Retry logic for transient failures
  - Same standard tests
- **Mocks**: OpenAI API, DB facade, TextractJob model
- **Acceptance**: 100% coverage, handles large docs

---

## Track C: Livewire Component Tests (Start) (Developer C) - 20 hours

**Task 2.C.1: UnifiedSearch Livewire Test** (8 hours)
- **Create**: `tests/Feature/Livewire/UnifiedSearchTest.php`
- **File**: `app/Http/Livewire/UnifiedSearch.php`
- **Tests** (15 test methods):
  - Component renders correctly
  - Search query input binding works
  - Corpus selection (laws, cases, decisions, all)
  - Search type toggle (vector, hybrid, citation)
  - Results display correctly
  - Pagination works
  - Filters update results
  - Handles empty query
  - Handles no results
  - Export to PDF works
  - Export to CSV works
  - Saves recent searches
  - Loads recent searches
  - Error handling displays
  - Loading state shows
- **Acceptance**: Full UI interaction coverage

**Task 2.C.2: TextractManager Livewire Test** (8 hours)
- **Create**: `tests/Feature/Livewire/TextractManagerTest.php`
- **File**: `app/Http/Livewire/TextractManager.php`
- **Tests** (18 test methods):
  - Component renders job list
  - Start new processing triggers job
  - Job status updates in real-time
  - Retry failed job works
  - Cancel running job works
  - View job results displays correctly
  - Edit content triggers modal
  - Save edited content works
  - Regenerate embeddings triggers
  - Sync to graph triggers
  - Batch operations work
  - Filters by status
  - Pagination
  - Search by case_id
  - Export job list
  - Delete job confirmation
  - Error handling
  - Permission checks
- **Acceptance**: All manager functions tested

**Task 2.C.3: OpenAIVectorManager Livewire Test** (4 hours)
- **Create**: `tests/Feature/Livewire/OpenAIVectorManagerTest.php`
- **File**: `app/Http/Livewire/OpenAIVectorManager.php`
- **Tests** (12 test methods):
  - List vector stores
  - Create vector store
  - Upload files to store
  - Delete files from store
  - Delete vector store
  - Search within store
  - View store statistics
  - Batch upload
  - Error handling
  - Permission checks
  - Loading states
  - Confirmation modals
- **Acceptance**: Vector store CRUD fully tested

---

# SPRINT 3: UI Testing + Error Handling

**Duration**: Week 3 (Nov 18-22)
**Goal**: Complete Livewire tests, add error handling
**Coverage Target**: 52% → 62% (+10%)

## Track A: Livewire Tests (Complete) (Developer A) - 40 hours

**Task 3.A.1: DecisionDiscoveryDashboard Test** (6 hours)
**Task 3.A.2: CollaborationDashboard Test** (6 hours)
**Task 3.A.3: GraphViewer Test** (8 hours)
**Task 3.A.4: IngestedLawsManager Test** (4 hours)
**Task 3.A.5: OpenAILogViewer Test** (4 hours)
**Task 3.A.6: OpenAIResponsesViewer Test** (4 hours)
**Task 3.A.7: TimelinePage Test** (4 hours)
**Task 3.A.8: EoglasnaMonitoring Test** (4 hours)

## Track B: HomeSearch Module Tests (Developer B) - 25 hours

**Task 3.B.1: HomeSearchAbuseDetector Test** (8 hours)
- **Create**: `tests/Unit/Modules/HomeSearch/HomeSearchAbuseDetectorTest.php`
- **File**: `app/Modules/HomeSearch/Services/HomeSearchAbuseDetector.php` (515 lines)
- **Tests** (20 test methods):
  - Detects disproportionate warrants
  - Analyzes warrant vs offense severity
  - Calculates proportionality score
  - Identifies patterns (drug cases, property crimes)
  - Regional comparison
  - Statistical analysis
  - Generates abuse reports
  - Handles missing data
  - Validates input data
  - All edge cases

**Task 3.B.2: OdlukeSearchAgent Test** (8 hours)
- **Create**: `tests/Unit/Modules/HomeSearch/OdlukeSearchAgentTest.php`
- **File**: `app/Modules/HomeSearch/Services/OdlukeSearchAgent.php` (597 lines)
- **Tests** (18 test methods):
  - Autonomous search execution
  - Query generation
  - Result parsing
  - Data extraction
  - Error handling
  - Rate limiting
  - Circuit breaker
  - Result validation

**Task 3.B.3: ProportionalityAnalyzer Test** (4 hours)
- **Tests**: Severity scoring, threshold analysis

**Task 3.B.4: StatisticalAnalyzer Test** (5 hours)
- **Tests**: Regional comparisons, trend analysis, statistical significance

## Track C: Error Handling (Developer C) - 20 hours

**Task 3.C.1: Global Exception Handler** (4 hours)
- **File**: `bootstrap/app.php`
- **Implementation**:
  ```php
  ->withExceptions(function (Exceptions $exceptions): void {
      $exceptions->render(function (Throwable $e, Request $request) {
          // Log with context
          Log::error('Exception caught', [
              'exception' => get_class($e),
              'message' => $e->getMessage(),
              'file' => $e->getFile(),
              'line' => $e->getLine(),
              'trace' => $e->getTraceAsString(),
              'request_id' => $request->id(),
              'user_id' => auth()->id(),
              'url' => $request->fullUrl(),
              'method' => $request->method(),
          ]);

          // Return user-friendly response
          if ($request->expectsJson()) {
              return response()->json([
                  'success' => false,
                  'message' => app()->isProduction()
                      ? 'An error occurred'
                      : $e->getMessage(),
                  'request_id' => $request->id(),
              ], 500);
          }

          return response()->view('errors.500', [
              'exception' => app()->isProduction() ? null : $e
          ], 500);
      });
  })
  ```
- **Test**: Exception handling test
- **Acceptance**: All exceptions logged and handled

**Task 3.C.2: Add Error Handling to Controllers** (12 hours)
- **Files** (5 controllers without error handling):
  1. `UploadController.php` (all 5 methods)
  2. `GraphVisualizationController.php` (all 3 methods)
  3. `OpenAIController.php` (13 methods)
  4. `IngestController.php` (3 methods)
  5. `ProfileController.php` (2 methods)
- **Pattern**:
  ```php
  public function method(Request $request) {
      try {
          // existing code
      } catch (\Exception $e) {
          Log::error('Operation failed', [
              'operation' => 'method_name',
              'error' => $e->getMessage(),
              'trace' => $e->getTraceAsString(),
          ]);

          return response()->json([
              'success' => false,
              'message' => 'Operation failed'
          ], 500);
      }
  }
  ```
- **Acceptance**: All controller methods have try-catch

**Task 3.C.3: Circuit Breaker for External APIs** (4 hours)
- **Create**: `app/Services/CircuitBreaker.php`
- **Apply to**:
  - OpenAI API calls
  - AWS Textract API calls
  - Eoglasna API calls
- **Pattern**:
  ```php
  $breaker->call(function() use ($params) {
      return $this->client->post($url, $params);
  });
  ```
- **Acceptance**: Circuit breaker on all external APIs

---

# SPRINT 4: Service Testing + Database

**Duration**: Week 4 (Nov 25-29)
**Goal**: Add service tests, fix database issues
**Coverage Target**: 62% → 70% (+8%)

## Track A: Service Tests (Developer A) - 40 hours

**Task 4.A.1: UploadService Test** (4 hours)
**Task 4.A.2: IngestPipelineService Test** (6 hours)
**Task 4.A.3: FactExtractionService Test** (6 hours)
**Task 4.A.4: MetadataBuilder Test** (4 hours)
**Task 4.A.5: ContextCompressor Test** (4 hours)
**Task 4.A.6: PdfMerger Test** (4 hours)
**Task 4.A.7: PdfRenderer Test** (4 hours)
**Task 4.A.8: TaggingService Test** (4 hours)
**Task 4.A.9: QueryNormalizer Test** (4 hours)

## Track B: Console Command Tests (Developer B) - 32 hours

**Task 4.B.1: Graph Commands** (8 hours)
- GraphInitCommand
- GraphQueryCommand
- GraphStatsCommand
- GraphSyncCommand

**Task 4.B.2: EKOM Commands** (12 hours)
- All 9 ekom:* commands

**Task 4.B.3: Eoglasna Commands** (8 hours)
- All 5 eoglasna:* commands

**Task 4.B.4: Metadata Commands** (4 hours)
- AddCorrectLawArticleMeta
- AddMetadataToFiles
- LawsRegenMetadata

## Track C: Database Integrity (Developer C) - 8 hours

**Task 4.C.1: Fix Non-Reversible Migrations** (1 hour)
- **Files**:
  - `2025_10_19_195959_add_unique_index_on_case_number_to_cases_table.php`
  - `2025_10_21_224804_rename_embedding_column_on_cases_documents_table.php`
- **Fix down() methods**
- **Test rollback**

**Task 4.C.2: Add Foreign Key Constraints** (2 hours)
- **Create migrations**:
  - `textract_jobs.case_id` → `cases.id`
  - `textract_documents.case_id` → `cases.id`
  - `eoglasna_keyword_matches.notice_uuid` → `eoglasna_notices.uuid`

**Task 4.C.3: Add Missing Indexes** (2 hours)
- **Create migration** with 8 indexes:
  ```php
  Schema::table('court_decisions', function (Blueprint $table) {
      $table->index('jurisdiction');
  });

  Schema::table('laws', function (Blueprint $table) {
      $table->index('jurisdiction');
  });

  Schema::table('agent_runs', function (Blueprint $table) {
      $table->index('status');
  });

  Schema::table('case_strategies', function (Blueprint $table) {
      $table->index(['case_id', 'version']);
  });

  // ... 4 more
  ```

**Task 4.C.4: Add Model Relationships** (3 hours)
- **Update 14 models** with missing relationships
- **Test relationships**

---

# SPRINT 5: Final Testing + Polish

**Duration**: Week 5 (Dec 2-6)
**Goal**: Reach 75%+ coverage, API docs, final polish
**Coverage Target**: 70% → 75%+ (+5%+)

## Track A: Remaining Tests (Developer A) - 32 hours

**Task 5.A.1: Remaining Service Tests** (16 hours)
- Test all remaining services (20+ services)

**Task 5.A.2: Model Tests** (8 hours)
- Test remaining 16 untested models

**Task 5.A.3: Repository Tests** (4 hours)
- Test 6 untested repositories

**Task 5.A.4: Middleware Tests** (4 hours)
- Test 3 remaining middleware

## Track B: API Documentation (Developer B) - 24 hours

**Task 5.B.1: OpenAPI Specification** (12 hours)
- **Create**: `public/openapi.yaml`
- **Document all 142 API endpoints**
- **Use**: OpenAPI 3.0 specification
- **Tools**: Swagger UI integration

**Task 5.B.2: Standardize Response Formats** (8 hours)
- **Create**: `app/Http/Responses/ApiResponse.php`
- **Standard success**:
  ```json
  {
    "success": true,
    "data": {...},
    "meta": {
      "request_id": "...",
      "timestamp": "...",
      "version": "v1"
    }
  }
  ```
- **Standard error**:
  ```json
  {
    "success": false,
    "error": {
      "code": "VALIDATION_ERROR",
      "message": "...",
      "details": {...}
    },
    "meta": {...}
  }
  ```
- **Update all controllers**

**Task 5.B.3: API Versioning** (4 hours)
- **Implement**: v1 namespace
- **Route structure**: `/api/v1/*`
- **Prepare**: v2 migration path

## Track C: Final Polish (Developer C) - 24 hours

**Task 5.C.1: Performance Optimization** (8 hours)
- **Database query optimization**
- **Eager loading optimization**
- **Cache strategy implementation**

**Task 5.C.2: Monitoring & Alerting** (8 hours)
- **Add**: Performance metrics
- **Add**: Error rate monitoring
- **Add**: Alerting for critical failures

**Task 5.C.3: Final Documentation** (8 hours)
- **Update**: README.md
- **Create**: DEPLOYMENT.md
- **Create**: TESTING.md
- **Create**: API.md

---

# Sprint Summary

## Overall Statistics

| Sprint | Focus | Hours | Coverage Gain | Cumulative Coverage |
|--------|-------|-------|---------------|---------------------|
| Sprint 1 | Security + Testing Start | 120 | +8% | 34% → 42% |
| Sprint 2 | Textract + Vector Stores | 120 | +10% | 42% → 52% |
| Sprint 3 | Livewire + Error Handling | 120 | +10% | 52% → 62% |
| Sprint 4 | Services + Database | 120 | +8% | 62% → 70% |
| Sprint 5 | Final Tests + Polish | 120 | +5%+ | 70% → 75%+ |
| **TOTAL** | **All Critical Issues** | **600** | **+41%** | **34% → 75%+** |

## Developer Allocation

**3 Developers × 40 hours/week × 5 weeks = 600 hours total**

- **Developer A**: Security, Textract, Livewire, Services, Tests (200 hours)
- **Developer B**: Textract, Vector Stores, HomeSearch, Commands, API Docs (200 hours)
- **Developer C**: Config, Livewire, Error Handling, Database, Polish (200 hours)

## Key Deliverables

### Security (Sprint 1)
- ✅ All 19 endpoints authenticated
- ✅ Hardcoded password removed
- ✅ Timing attack fixed
- ✅ Security headers implemented
- ✅ XSS vulnerabilities fixed

### Testing (Sprint 1-5)
- ✅ Textract Pipeline: 0% → 100% (12 steps)
- ✅ Vector Stores: 0% → 100% (4 services)
- ✅ Livewire: 11% → 100% (16 components)
- ✅ HomeSearch: 0% → 100% (4 services)
- ✅ Services: 37% → 70% (major improvement)
- ✅ Commands: 16% → 60% (critical commands)
- ✅ Overall: 34% → 75%+

### Configuration (Sprint 1)
- ✅ CONFIGURATION.md created (325 variables)
- ✅ .env.example 100% complete
- ✅ .env.production.example created
- ✅ Config validation implemented

### Error Handling (Sprint 3)
- ✅ Global exception handler
- ✅ All controllers have error handling
- ✅ Circuit breakers on external APIs

### Database (Sprint 4)
- ✅ 2 broken migrations fixed
- ✅ 4 foreign keys added
- ✅ 8 indexes added
- ✅ 14 model relationships defined

### API Documentation (Sprint 5)
- ✅ OpenAPI specification
- ✅ Response formats standardized
- ✅ API versioning implemented

---

# Success Criteria

## Sprint 1 Complete When:
- [ ] All 19 endpoints require authentication
- [ ] No hardcoded passwords in codebase
- [ ] Security headers on all responses
- [ ] XSS vulnerabilities fixed
- [ ] Textract Pipeline 50% tested
- [ ] CONFIGURATION.md created
- [ ] .env.example 100% complete

## Sprint 2 Complete When:
- [ ] Textract Pipeline 100% tested
- [ ] All 4 Vector Store services 100% tested
- [ ] Top 3 Livewire components tested
- [ ] Coverage reaches 52%

## Sprint 3 Complete When:
- [ ] All Livewire components tested
- [ ] HomeSearch module 100% tested
- [ ] Global exception handler working
- [ ] All controllers have error handling
- [ ] Coverage reaches 62%

## Sprint 4 Complete When:
- [ ] 20+ services tested
- [ ] Critical console commands tested
- [ ] Database migrations fixed
- [ ] Foreign keys added
- [ ] Coverage reaches 70%

## Sprint 5 Complete When:
- [ ] Coverage reaches 75%+
- [ ] OpenAPI spec complete
- [ ] API responses standardized
- [ ] All documentation complete
- [ ] System production-ready

---

# Risk Mitigation

## Parallel Work Risks

**Risk**: Merge conflicts between developers
**Mitigation**:
- Each track works in separate directories
- Track A: Security, Tests (tests/Unit/Pipelines, tests/Feature/Livewire)
- Track B: Tests, Services (tests/Unit/Services)
- Track C: Config, Docs (docs/, .env.example)

## Timeline Risks

**Risk**: Tasks take longer than estimated
**Mitigation**:
- 20% buffer built into estimates
- Can drop Sprint 5 polish tasks if needed
- Minimum viable: Sprints 1-4 (70% coverage)

## Quality Risks

**Risk**: Tests don't catch real bugs
**Mitigation**:
- Code review all test PRs
- Require edge case coverage
- Integration tests in addition to unit tests
- Test quality metrics (mutation testing)

---

**END OF SPRINT PLAN**

Total: 600 hours over 5 weeks with 3 developers working in parallel.

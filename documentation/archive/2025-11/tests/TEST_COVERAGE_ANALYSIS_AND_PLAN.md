# Test Coverage Analysis & Improvement Plan
**AI Legal War Machine - Croatian Legal Defense System**

**Date:** 2025-10-29
**Current Coverage:** ~27% (75 test files / 277+ application files)
**Target Coverage:** 70%+
**Timeline:** 6 sprints (12 weeks)

---

## Executive Summary

This document provides a comprehensive analysis of the current test coverage in the AI Legal War Machine application and presents a structured sprint-based plan to increase coverage from 27% to 70%+. The plan prioritizes critical infrastructure, high-impact services, and user-facing features while identifying areas requiring manual testing.

### Key Findings

✅ **Strengths:**
- Legal Reasoning services: 100% coverage (12/12)
- Agent system: 71% coverage (5/7)
- Well-established testing patterns (Mockery, PHPUnit, factories)
- Integration and feature tests for core workflows

⚠️ **Critical Gaps:**
- Textract pipeline steps: 0% (0/12) - **HIGHEST PRIORITY**
- Livewire components: 0% (0/16)
- Queue jobs: 10% (1/10)
- Console commands: 5% (2/43)
- Models: 19% (6/32)
- Controllers: 42% (8/19)
- Services: 26% (21/81)

---

## Detailed Coverage Analysis

### 1. Controllers (8/19 tested = 42%)

#### ✅ Tested Controllers
- AnalyticsController
- ReasoningController
- StrategyController
- IngestController
- McpHttpController
- McpOpenAIController
- McpToolsController
- OpenAIController

#### ❌ Untested Controllers (Priority Order)
1. **DefenseController** - CRITICAL (core feature)
2. **EvidenceController** - CRITICAL (core feature)
3. **AgentController** - HIGH (agent management UI)
4. **CollaborationController** - HIGH (multi-user features)
5. **SearchController** - HIGH (core search functionality)
6. **UploadController** - MEDIUM (file uploads)
7. **EvidenceAssetController** - MEDIUM
8. **HoneypotController** - MEDIUM (security)
9. **HoneypotDashboardController** - LOW
10. **ProfileController** - LOW
11. **AuthController** - LOW

---

### 2. Services (21/81 tested = 26%)

#### ✅ Legal Reasoning Services (12/12 = 100%)
- ArgumentGenerator
- CitationAnalyzer
- ConflictResolver
- DurationEstimator
- FeatureExtractor
- ImpactAnalyzer
- LogicEngine
- OutcomePredictor
- PredictiveAnalytics
- RiskAssessor
- StrategicPlanner
- StrategyBuilder

#### ✅ Search Services (5/5 = 100%)
- LawSearchService
- DecisionSearchService
- CaseSearchService
- UnifiedSearchService
- QueryRewriter

#### ✅ Other Tested Services
- OpenAIService (partial)
- InternalMcpClient
- McpToOpenAIBridge
- OdlukeIngestService
- OdlukeClient

#### ❌ Critical Untested Services

**AI/LLM Services (0/8 tested):**
- GraphRagService - CRITICAL
- RagOrchestrator - CRITICAL
- PromptBuilder - HIGH
- LlmResponseValidator - HIGH
- ContextManager - MEDIUM
- TokenCounter - MEDIUM
- StreamingHandler - MEDIUM
- RateLimiter - LOW

**OCR Services (1/10 tested = 10%):**
- TextractService - CRITICAL
- OcrService - CRITICAL
- PdfService - HIGH
- DocumentSplitter - HIGH
- OcrQualityChecker (tested via unit test)
- TableExtractor - MEDIUM
- LayoutAnalyzer - MEDIUM
- TextCleaner - LOW

**Graph Database (0/3 tested):**
- GraphDatabaseService - CRITICAL
- Neo4jClient - CRITICAL
- GraphQueryBuilder - HIGH

**Legal Citation Services (2/9 tested = 22%):**
- HrLegalCitationsDetector - HIGH
- EcliDetector - HIGH
- CroatianLawRegistry - HIGH
- CourtDetector - MEDIUM
- DocumentTypeClassifier - MEDIUM
- PartyDetector - MEDIUM
- KeyPhraseExtractor (tested)
- StatuteCitationDetector (tested)
- DateDetector (tested)

**Document Ingestion (0/5 tested):**
- LawIngestService - CRITICAL
- DecisionIngestService - HIGH
- EmbeddingsService - HIGH
- ChunkingService - MEDIUM
- MetadataExtractor - MEDIUM

**Integration Services (0/6 tested):**
- EoglasnaApiClient - HIGH
- GoogleDriveService - HIGH
- AwsS3Service - MEDIUM
- EmailService - LOW
- NotificationService - LOW
- WebhookService - LOW

---

### 3. Models (6/32 tested = 19%)

#### ✅ Tested Models
- CaseFeature
- CasePrediction
- CaseStrategy
- CourtDecision
- DecisionImpactMetric
- LegalCase

#### ❌ Critical Untested Models
1. **AgentRun** - CRITICAL (agent execution tracking)
2. **TextractDocument** - CRITICAL (OCR results)
3. **TextractJob** - CRITICAL (OCR job tracking)
4. **Law** - CRITICAL (legal database)
5. **IngestedLaw** - CRITICAL
6. **Embedding** - HIGH (vector search)
7. **CaseDocument** - HIGH
8. **CourtCase** - HIGH
9. **Agent** - HIGH
10. **AgentMemory** - MEDIUM

Plus 16 other models with lower priority.

---

### 4. Queue Jobs (1/10 tested = 10%)

#### ✅ Tested
- GenerateLawMetadata

#### ❌ Untested (All HIGH/CRITICAL priority)
1. **ProcessTextractJob** - CRITICAL (OCR processing)
2. **GenerateEmbeddingsJob** - CRITICAL (vector search)
3. **ExecuteAgentResearch** - CRITICAL (agent execution)
4. **ProcessDrivePdfJob** - HIGH (document import)
5. **RunAutonomousResearchJob** - HIGH
6. **SyncTextractToGraph** - HIGH (knowledge graph)
7. **RegenerateTextractEmbeddings** - MEDIUM
8. **ReprocessTextractJob** - MEDIUM
9. **ExtractTablesFromTextractJob** - MEDIUM

---

### 5. Textract Pipeline Steps (0/12 tested = 0%) ⚠️ **HIGHEST PRIORITY**

All pipeline steps are untested:
1. **EnsureJobStep** - Create/validate TextractJob
2. **DownloadDriveFileStep** - Download from Google Drive
3. **UploadInputToS3Step** - Upload to AWS S3
4. **StartAnalysisStep** - Start Textract analysis
5. **WaitAndFetchStep** - Poll for completion
6. **CheckOcrQualityStep** - Validate OCR quality
7. **CollectLinesStep** - Collect text lines
8. **ReconstructPdfStep** - Create searchable PDF
9. **PersistReconstructedStep** - Save to storage
10. **SaveResultsStep** - Persist OCR results
11. **UploadOutputStep** - Upload results to S3
12. **CreateMetadataStep** - Extract metadata

**Impact:** This is the core document processing pipeline. Testing these 12 steps will provide maximum ROI.

---

### 6. Console Commands (2/43 tested = 5%)

#### ✅ Tested
- ZakonHrIngestEmbeddingsCommand
- OdlukeIngestEmbeddingsCommand

#### ❌ Critical Untested Commands
1. **CasesIngest** - CRITICAL (data import)
2. **ImportCroatianLaws** - CRITICAL
3. **DiscoverCourtDecisions** - HIGH
4. **ProcessPendingTextract** - HIGH
5. **SyncGraphDatabase** - HIGH
6. **GenerateAllEmbeddings** - HIGH
7. **CleanupOrphanedFiles** - MEDIUM

Plus 36 other commands.

---

### 7. Livewire Components (0/16 tested = 0%)

All components untested:
1. **UnifiedSearch** - HIGH (core search UI)
2. **TextractManager** - HIGH (OCR management)
3. **OpenAIVectorManager** - HIGH (embeddings UI)
4. **DecisionDiscoveryDashboard** - MEDIUM
5. **CollaborationDashboard** - MEDIUM
6. **GraphViewer** - MEDIUM
7. **IngestedLawsManager** - MEDIUM
8. **OpenAILogViewer** - LOW
9. **OpenAIResponsesViewer** - LOW
10. **ComparativeTimelinePage** - LOW
11. **ParallelTimeline** - LOW
12. **GupTimeline** - LOW
13. **TimelinePage** - LOW
14. **TranscriptPreviewer** - LOW
15. **EoglasnaMonitoring** - LOW
16. **EpredmetWidget** - LOW

---

### 8. Agents (5/7 tested = 71%) ✅ **GOOD COVERAGE**

#### ✅ Tested
- AutonomousResearchAgent (3 test files)
- DecisionDiscoveryAgent
- OdlukeAgent

#### ❌ Untested Specialist Agents
1. **PrecedentAnalystAgent** - MEDIUM
2. **ResearchSpecialistAgent** - MEDIUM
3. **RiskAnalystAgent** - MEDIUM
4. **StrategySpecialistAgent** - MEDIUM

---

### 9. Defense & Evidence Modules (22% service coverage)

#### ✅ Feature Tests Exist
- DefenceOnlyModule (DefenseModuleTest.php)
- EvidenceAnalysisModule (EvidenceModuleTest.php)

#### ❌ Missing Unit Tests for Module Services
1. **DefenseStrategyAnalyzer** - HIGH
2. **DefenseRecommendationService** - HIGH
3. **ImproveAccusedStatusAction** - HIGH
4. **EvidenceAdmissibilityChecker** - HIGH
5. **ConstitutionalViolationDetector** - HIGH
6. **AlternativeInterpretationAnalyzer** - MEDIUM
7. **SuppressionMotionGenerator** - MEDIUM

---

## Sprint-Based Test Coverage Plan

### Sprint 1 (Weeks 1-2): Critical Infrastructure - Pipeline & Jobs

**Goal:** Test core OCR and document processing infrastructure
**Expected Coverage Gain:** 8% → 35%

#### Tasks (40 hours)

1. **Textract Pipeline Tests** (24 hours)
   - `tests/Unit/Pipelines/Textract/EnsureJobStepTest.php` (2h)
   - `tests/Unit/Pipelines/Textract/DownloadDriveFileStepTest.php` (2h)
   - `tests/Unit/Pipelines/Textract/UploadInputToS3StepTest.php` (2h)
   - `tests/Unit/Pipelines/Textract/StartAnalysisStepTest.php` (2h)
   - `tests/Unit/Pipelines/Textract/WaitAndFetchStepTest.php` (3h)
   - `tests/Unit/Pipelines/Textract/CheckOcrQualityStepTest.php` (2h)
   - `tests/Unit/Pipelines/Textract/CollectLinesStepTest.php` (2h)
   - `tests/Unit/Pipelines/Textract/ReconstructPdfStepTest.php` (3h)
   - `tests/Unit/Pipelines/Textract/PersistReconstructedStepTest.php` (2h)
   - `tests/Unit/Pipelines/Textract/SaveResultsStepTest.php` (2h)
   - `tests/Unit/Pipelines/Textract/UploadOutputStepTest.php` (1h)
   - `tests/Unit/Pipelines/Textract/CreateMetadataStepTest.php` (1h)

2. **Critical Job Tests** (12 hours)
   - `tests/Unit/Jobs/ProcessTextractJobTest.php` (4h)
   - `tests/Unit/Jobs/GenerateEmbeddingsJobTest.php` (4h)
   - `tests/Unit/Jobs/ExecuteAgentResearchTest.php` (4h)

3. **Integration Test** (4 hours)
   - `tests/Integration/TextractPipelineFlowTest.php` (4h)
     - Test full pipeline end-to-end
     - Mock AWS services
     - Verify searchable PDF output

**Testing Patterns:**
```php
// Pipeline step test example
class EnsureJobStepTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_creates_new_textract_job_if_not_exists()
    {
        $payload = ['driveFileId' => 'abc123'];

        $step = new EnsureJobStep();
        $result = $step->handle($payload, fn($p) => $p);

        $this->assertDatabaseHas('textract_jobs', [
            'drive_file_id' => 'abc123',
            'status' => 'pending',
        ]);

        $this->assertArrayHasKey('textractJob', $result);
    }

    /** @test */
    public function it_reuses_existing_job()
    {
        $existingJob = TextractJob::create([...]);
        $payload = ['driveFileId' => $existingJob->drive_file_id];

        $step = new EnsureJobStep();
        $result = $step->handle($payload, fn($p) => $p);

        $this->assertEquals($existingJob->id, $result['textractJob']->id);
    }
}
```

**Deliverables:**
- 15 new test files
- Pipeline documentation
- Test coverage report showing pipeline coverage

---

### Sprint 2 (Weeks 3-4): Core Services - OCR & Graph Database

**Goal:** Test critical backend services
**Expected Coverage Gain:** 35% → 48%

#### Tasks (40 hours)

1. **OCR Services** (16 hours)
   - `tests/Unit/Services/Ocr/TextractServiceTest.php` (5h)
   - `tests/Unit/Services/Ocr/OcrServiceTest.php` (4h)
   - `tests/Unit/Services/Pdf/PdfServiceTest.php` (4h)
   - `tests/Unit/Services/Ocr/DocumentSplitterTest.php` (3h)

2. **Graph Database Services** (12 hours)
   - `tests/Unit/Services/GraphDatabaseServiceTest.php` (5h)
   - `tests/Unit/Services/Neo4jClientTest.php` (4h)
   - `tests/Unit/Services/GraphQueryBuilderTest.php` (3h)

3. **AI/RAG Services** (12 hours)
   - `tests/Unit/Services/AI/GraphRagServiceTest.php` (5h)
   - `tests/Unit/Services/AI/RagOrchestratorTest.php` (4h)
   - `tests/Unit/Services/AI/PromptBuilderTest.php` (3h)

**Testing Patterns:**
```php
class TextractServiceTest extends TestCase
{
    protected $s3Mock;
    protected $textractClientMock;
    protected TextractService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->s3Mock = Mockery::mock(S3Client::class);
        $this->textractClientMock = Mockery::mock(TextractClient::class);

        $this->service = new TextractService(
            $this->s3Mock,
            $this->textractClientMock
        );
    }

    /** @test */
    public function it_starts_document_analysis()
    {
        $this->textractClientMock
            ->shouldReceive('startDocumentAnalysis')
            ->once()
            ->with(Mockery::on(function ($params) {
                return $params['DocumentLocation']['S3Object']['Bucket'] === 'test-bucket'
                    && $params['FeatureTypes'] === ['TABLES', 'FORMS'];
            }))
            ->andReturn(['JobId' => 'job-123']);

        $jobId = $this->service->startAnalysis('s3://test-bucket/file.pdf');

        $this->assertEquals('job-123', $jobId);
    }

    /** @test */
    public function it_polls_until_completion()
    {
        $this->textractClientMock
            ->shouldReceive('getDocumentAnalysis')
            ->times(3)
            ->andReturn(
                ['JobStatus' => 'IN_PROGRESS'],
                ['JobStatus' => 'IN_PROGRESS'],
                ['JobStatus' => 'SUCCEEDED', 'Blocks' => [...]]
            );

        $result = $this->service->waitForCompletion('job-123', $maxAttempts = 3);

        $this->assertEquals('SUCCEEDED', $result['JobStatus']);
    }
}
```

**Deliverables:**
- 10 new test files
- Service mocking patterns documented
- Coverage report for services layer

---

### Sprint 3 (Weeks 5-6): Critical Models & Data Layer

**Goal:** Test core data models and relationships
**Expected Coverage Gain:** 48% → 56%

#### Tasks (40 hours)

1. **Critical Models** (20 hours)
   - `tests/Unit/Models/AgentRunTest.php` (4h)
   - `tests/Unit/Models/TextractDocumentTest.php` (4h)
   - `tests/Unit/Models/TextractJobTest.php` (3h)
   - `tests/Unit/Models/LawTest.php` (3h)
   - `tests/Unit/Models/IngestedLawTest.php` (3h)
   - `tests/Unit/Models/EmbeddingTest.php` (3h)

2. **Model Relationships & Scopes** (12 hours)
   - `tests/Unit/Models/CaseDocumentTest.php` (3h)
   - `tests/Unit/Models/CourtCaseTest.php` (3h)
   - `tests/Unit/Models/AgentTest.php` (3h)
   - `tests/Unit/Models/AgentMemoryTest.php` (3h)

3. **Model Factories Enhancement** (8 hours)
   - Enhance existing factories for better test data
   - Create missing factories
   - Document factory usage patterns

**Testing Patterns:**
```php
class AgentRunTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_has_correct_fillable_attributes()
    {
        $run = AgentRun::create([
            'agent_id' => 1,
            'status' => 'running',
            'plan' => ['step1', 'step2'],
            'insights' => ['insight1'],
            'started_at' => now(),
        ]);

        $this->assertEquals('running', $run->status);
        $this->assertIsArray($run->plan);
        $this->assertCount(2, $run->plan);
    }

    /** @test */
    public function it_has_relationship_with_agent()
    {
        $agent = Agent::factory()->create();
        $run = AgentRun::factory()->create(['agent_id' => $agent->id]);

        $this->assertInstanceOf(Agent::class, $run->agent);
        $this->assertEquals($agent->id, $run->agent->id);
    }

    /** @test */
    public function scope_completed_returns_only_completed_runs()
    {
        AgentRun::factory()->create(['status' => 'running']);
        AgentRun::factory()->create(['status' => 'completed']);
        AgentRun::factory()->create(['status' => 'completed']);

        $completed = AgentRun::completed()->get();

        $this->assertCount(2, $completed);
        $this->assertTrue($completed->every(fn($r) => $r->status === 'completed'));
    }

    /** @test */
    public function it_calculates_duration_correctly()
    {
        $run = AgentRun::factory()->create([
            'started_at' => now()->subMinutes(10),
            'completed_at' => now(),
        ]);

        $duration = $run->duration_seconds;

        $this->assertEqualsWithDelta(600, $duration, 5);
    }
}
```

**Deliverables:**
- 10 new test files
- Enhanced model factories
- Model testing patterns guide

---

### Sprint 4 (Weeks 7-8): Controllers & API Endpoints

**Goal:** Test user-facing endpoints and API contracts
**Expected Coverage Gain:** 56% → 63%

#### Tasks (40 hours)

1. **Critical Controllers** (20 hours)
   - `tests/Feature/DefenseControllerTest.php` (5h)
   - `tests/Feature/EvidenceControllerTest.php` (5h)
   - `tests/Feature/AgentControllerTest.php` (4h)
   - `tests/Feature/SearchControllerTest.php` (3h)
   - `tests/Feature/CollaborationControllerTest.php` (3h)

2. **Secondary Controllers** (12 hours)
   - `tests/Feature/UploadControllerTest.php` (3h)
   - `tests/Feature/EvidenceAssetControllerTest.php` (3h)
   - `tests/Feature/HoneypotControllerTest.php` (3h)
   - `tests/Feature/ProfileControllerTest.php` (3h)

3. **API Contract Tests** (8 hours)
   - Validate JSON responses
   - Test authentication/authorization
   - Test validation rules
   - Error handling

**Testing Patterns:**
```php
class DefenseControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_returns_defense_strategies_for_case()
    {
        $case = LegalCase::factory()->create();

        $response = $this->getJson("/api/defense/strategies/{$case->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'strategies' => [
                        '*' => [
                            'title',
                            'description',
                            'viability_score',
                            'risks',
                            'recommendations',
                        ]
                    ],
                    'recommended_strategy',
                ]
            ]);
    }

    /** @test */
    public function it_validates_required_fields_for_strategy_creation()
    {
        $case = LegalCase::factory()->create();

        $response = $this->postJson("/api/defense/strategies/{$case->id}", [
            // Missing required fields
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['strategy_type', 'description']);
    }

    /** @test */
    public function it_requires_authentication()
    {
        $response = $this->getJson("/api/defense/strategies/1");

        $response->assertStatus(401);
    }
}
```

**Deliverables:**
- 9 new test files
- API documentation validation
- Postman collection for manual testing

---

### Sprint 5 (Weeks 9-10): Module Services & Commands

**Goal:** Test defense/evidence modules and critical commands
**Expected Coverage Gain:** 63% → 68%

#### Tasks (40 hours)

1. **Module Service Tests** (16 hours)
   - `tests/Unit/Modules/Defence/DefenseStrategyAnalyzerTest.php` (4h)
   - `tests/Unit/Modules/Defence/DefenseRecommendationServiceTest.php` (4h)
   - `tests/Unit/Modules/Defence/ImproveAccusedStatusActionTest.php` (4h)
   - `tests/Unit/Modules/Evidence/EvidenceAdmissibilityCheckerTest.php` (4h)

2. **Console Command Tests** (16 hours)
   - `tests/Feature/Console/CasesIngestCommandTest.php` (5h)
   - `tests/Feature/Console/ImportCroatianLawsCommandTest.php` (5h)
   - `tests/Feature/Console/DiscoverCourtDecisionsCommandTest.php` (3h)
   - `tests/Feature/Console/ProcessPendingTextractCommandTest.php` (3h)

3. **Additional Job Tests** (8 hours)
   - `tests/Unit/Jobs/ProcessDrivePdfJobTest.php` (3h)
   - `tests/Unit/Jobs/RunAutonomousResearchJobTest.php` (3h)
   - `tests/Unit/Jobs/SyncTextractToGraphTest.php` (2h)

**Testing Patterns:**
```php
class CasesIngestCommandTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_ingests_cases_from_csv_file()
    {
        Storage::fake('local');
        Storage::put('test-cases.csv', "title,description,court\nCase 1,Desc 1,VSRH");

        $this->artisan('cases:ingest', ['file' => 'test-cases.csv'])
             ->expectsOutput('Processing 1 cases...')
             ->expectsOutput('Ingested 1 cases successfully.')
             ->assertExitCode(0);

        $this->assertDatabaseHas('legal_cases', [
            'title' => 'Case 1',
            'court' => 'VSRH',
        ]);
    }

    /** @test */
    public function it_handles_invalid_csv_gracefully()
    {
        Storage::fake('local');
        Storage::put('invalid.csv', "invalid,data\n");

        $this->artisan('cases:ingest', ['file' => 'invalid.csv'])
             ->expectsOutput('Error: Invalid CSV format')
             ->assertExitCode(1);
    }
}
```

**Deliverables:**
- 11 new test files
- Command testing guide
- Module architecture documentation

---

### Sprint 6 (Weeks 11-12): Additional Services & Integration Tests

**Goal:** Fill remaining gaps and add comprehensive integration tests
**Expected Coverage Gain:** 68% → 72%+

#### Tasks (40 hours)

1. **Legal Citation Services** (12 hours)
   - `tests/Unit/Services/LegalCitations/HrLegalCitationsDetectorTest.php` (4h)
   - `tests/Unit/Services/LegalCitations/EcliDetectorTest.php` (3h)
   - `tests/Unit/Services/LegalCitations/CroatianLawRegistryTest.php` (3h)
   - `tests/Unit/Services/LegalCitations/CourtDetectorTest.php` (2h)

2. **Document Ingestion Services** (12 hours)
   - `tests/Unit/Services/LawIngestServiceTest.php` (4h)
   - `tests/Unit/Services/DecisionIngestServiceTest.php` (4h)
   - `tests/Unit/Services/EmbeddingsServiceTest.php` (4h)

3. **Comprehensive Integration Tests** (16 hours)
   - `tests/Integration/FullCaseWorkflowTest.php` (5h)
     - Create case → Upload evidence → Analyze → Generate strategy
   - `tests/Integration/LawSearchAndRetrievalTest.php` (4h)
     - Ingest laws → Search → Retrieve → Generate citations
   - `tests/Integration/AgentCollaborationTest.php` (4h)
     - Multi-agent workflow with decision discovery
   - `tests/Integration/TextractToGraphSyncTest.php` (3h)
     - OCR → Extract metadata → Sync to Neo4j

**Deliverables:**
- 11 new test files
- Integration test suite
- End-to-end workflow documentation
- Final coverage report

---

## Manual Testing Requirements

The following areas are **NOT suitable for automated testing** or would require disproportionate effort. These require manual testing protocols.

### 1. User Interface & User Experience (UX)

**Livewire Components - Manual Testing Required**

All 16 Livewire components need manual testing because:
- Real-time reactivity is difficult to test automatically
- Visual layout and responsiveness need human validation
- User interaction flows require manual verification

#### Testing Protocol:

**UnifiedSearch Component:**
- [ ] Search box accepts input and updates results in real-time
- [ ] Filters work correctly (law type, court, date range)
- [ ] Results are properly formatted and clickable
- [ ] Pagination works smoothly
- [ ] Loading states display correctly
- [ ] Error messages show when appropriate
- [ ] Mobile responsive layout
- [ ] Keyboard shortcuts work (Ctrl+K, Esc, etc.)

**TextractManager Component:**
- [ ] File upload drag-and-drop works
- [ ] Upload progress shows correctly
- [ ] Job status updates in real-time
- [ ] Cancel job button works
- [ ] View results opens correct document
- [ ] Quality score displays with color coding
- [ ] Re-process button triggers correctly
- [ ] Delete job with confirmation modal

**OpenAIVectorManager Component:**
- [ ] Embeddings list loads and paginates
- [ ] Generate embeddings button triggers job
- [ ] Progress indicators update
- [ ] Regenerate works for selected items
- [ ] Delete with bulk selection
- [ ] Filter by status/date
- [ ] Export to CSV works

**Timeline Components (Comparative, Parallel, GUP):**
- [ ] Timeline renders with correct dates
- [ ] Events display in correct order
- [ ] Zoom in/out works smoothly
- [ ] Pan/scroll works
- [ ] Event tooltips show on hover
- [ ] Click events open detail modals
- [ ] Multiple timelines sync correctly (for comparative view)
- [ ] Export timeline to PDF/image

**Graph Visualization:**
- [ ] Graph renders with nodes and edges
- [ ] Pan and zoom work
- [ ] Click node to see details
- [ ] Click edge to see relationship
- [ ] Layout algorithm produces readable graph
- [ ] Color coding is consistent
- [ ] Export graph works

**Dashboards (Collaboration, Decision Discovery):**
- [ ] All widgets load correctly
- [ ] Real-time updates work
- [ ] Filtering updates all widgets
- [ ] No layout shifts or flickering
- [ ] Performance is acceptable with large datasets

**Test Frequency:** Before each release
**Test Duration:** ~8-10 hours for full suite
**Recommended Tool:** Manual checklist + screen recording

---

### 2. Third-Party Integrations

These require external services and cannot be easily mocked:

#### AWS Textract (Live API Testing)
- **Setup:** Test AWS account with sample documents
- **Tests:**
  - [ ] Upload PDF triggers analysis
  - [ ] Small document (< 5 pages) completes in < 30 seconds
  - [ ] Large document (100+ pages) completes successfully
  - [ ] OCR quality is acceptable (> 95% accuracy)
  - [ ] Tables are extracted correctly
  - [ ] Forms/checkboxes detected
  - [ ] Handwriting recognition works (if enabled)
  - [ ] Cost per document is within budget
  - [ ] Error handling for corrupted PDFs
  - [ ] Retry mechanism works for failures

**Test Frequency:** Weekly (in staging)
**Budget:** ~$50/month for test documents

---

#### Google Drive Integration
- **Setup:** Test Google account with shared folder
- **Tests:**
  - [ ] OAuth flow works (login with Google)
  - [ ] File picker shows correct files
  - [ ] Download file from Drive works
  - [ ] Large files (> 100MB) download successfully
  - [ ] Permissions are checked (can't download restricted files)
  - [ ] Shared drive support works
  - [ ] File metadata syncs correctly
  - [ ] Webhook notifications for file changes

**Test Frequency:** Bi-weekly
**Test Duration:** 2 hours

---

#### Neo4j Graph Database (Integration Testing)
- **Setup:** Local Neo4j instance with test data
- **Tests:**
  - [ ] Connection establishes successfully
  - [ ] Create node via Cypher query
  - [ ] Create relationship between nodes
  - [ ] Query returns correct results
  - [ ] Full-text search works
  - [ ] Graph algorithms run (PageRank, community detection)
  - [ ] Backup/restore works
  - [ ] Performance with 10K+ nodes
  - [ ] Concurrent connection handling

**Test Frequency:** Weekly (in CI/CD)
**Can be partially automated:** Yes, but manual verification recommended

---

#### OpenAI API (Cost & Quality)
- **Setup:** Test API key with rate limits
- **Tests:**
  - [ ] GPT-4 completion works
  - [ ] Streaming responses work
  - [ ] Embeddings generation works
  - [ ] Function calling works correctly
  - [ ] Rate limiting is handled gracefully
  - [ ] Cost tracking is accurate
  - [ ] Response quality is acceptable (human review)
  - [ ] Prompt engineering produces correct results
  - [ ] Multi-turn conversations work
  - [ ] Error handling for API failures

**Test Frequency:** Daily (automated) + Weekly manual quality review
**Budget:** ~$100/month for test API calls

---

#### E-Oglasna & E-Predmet (Croatian Court Systems)
- **Setup:** Access to court system APIs (may require credentials)
- **Tests:**
  - [ ] Login/authentication works
  - [ ] Search for cases by number
  - [ ] Fetch case details
  - [ ] Monitor case status changes
  - [ ] Download court documents
  - [ ] Parse court notifications
  - [ ] Handle session timeouts
  - [ ] Error handling for unavailable system

**Test Frequency:** Weekly
**Challenge:** These systems may be unstable or change without notice

---

### 3. Performance & Load Testing

**Scenarios requiring manual testing:**

#### Large Document Processing
- [ ] Upload and process 500-page PDF
  - Time to completion: ___
  - Memory usage: ___
  - CPU usage: ___
  - Quality of output: ___

#### Concurrent Users
- [ ] 10 simultaneous searches
- [ ] 5 simultaneous document uploads
- [ ] 20 users viewing different pages
- [ ] Response time under load: ___

#### Database Performance
- [ ] Query performance with 100K+ laws
- [ ] Search performance with 50K+ embeddings
- [ ] Graph traversal with 10K+ nodes
- [ ] Pagination performance with large result sets

#### Embedding Generation
- [ ] Time to generate embeddings for 1,000 laws: ___
- [ ] Vector search performance: ___
- [ ] Memory usage during batch processing: ___

**Tools Recommended:**
- Apache JMeter or k6 for load testing
- New Relic or DataDog for monitoring
- Laravel Telescope for debugging

**Test Frequency:** Before major releases
**Test Duration:** 4-6 hours

---

### 4. Security Testing

**Manual security audit required:**

#### Authentication & Authorization
- [ ] SQL injection attempts are blocked
- [ ] XSS attacks are prevented
- [ ] CSRF protection works
- [ ] Session hijacking is prevented
- [ ] Password policies enforced
- [ ] Rate limiting works (prevent brute force)
- [ ] API tokens are secure
- [ ] Role-based access control (RBAC) works

#### File Upload Security
- [ ] Malicious file upload is blocked
- [ ] File type validation works
- [ ] File size limits enforced
- [ ] Virus scanning (if implemented)
- [ ] Path traversal attacks prevented

#### Honeypot Security System
- [ ] Honeypot logs unauthorized access attempts
- [ ] Alerts are triggered for suspicious activity
- [ ] False positives are minimized
- [ ] Dashboard shows correct metrics

**Tools Recommended:**
- OWASP ZAP for vulnerability scanning
- Burp Suite for penetration testing
- Laravel's built-in security features

**Test Frequency:** Quarterly + before major releases
**Recommended:** Hire external security consultant

---

### 5. Localization & Croatian Law Specificity

**Manual validation required:**

#### Croatian Legal Citations
- [ ] Zakon o kaznenom postupku citations detected correctly
- [ ] Ustav RH citations detected correctly
- [ ] ECLI numbers parsed correctly
- [ ] Court names recognized (VSRH, VTS, etc.)
- [ ] Case number formats validated

#### Legal Content Accuracy
- [ ] Defense strategies are legally sound (lawyer review)
- [ ] Evidence analysis follows Croatian law
- [ ] Constitutional violation detection is accurate
- [ ] Legal citations are correct and current
- [ ] Translations are accurate (if any)

**Test Frequency:** Monthly legal review
**Recommended:** Work with Croatian legal expert

---

### 6. Browser & Device Compatibility

**Manual cross-browser testing:**

- [ ] Chrome (latest)
- [ ] Firefox (latest)
- [ ] Safari (macOS and iOS)
- [ ] Edge (latest)
- [ ] Mobile Chrome (Android)
- [ ] Mobile Safari (iOS)

**Test in each browser:**
- [ ] Login works
- [ ] Search works
- [ ] File upload works
- [ ] Livewire components are reactive
- [ ] No console errors
- [ ] Responsive layout works
- [ ] Performance is acceptable

**Tools Recommended:**
- BrowserStack or Sauce Labs for cross-browser testing
- Chrome DevTools for responsive testing

**Test Frequency:** Before each release
**Test Duration:** 4 hours

---

### 7. Data Migration & Backup/Restore

**Manual testing for production scenarios:**

#### Database Migration
- [ ] Migration from SQLite to PostgreSQL works
- [ ] Data integrity maintained
- [ ] Embeddings preserved
- [ ] Relationships intact
- [ ] No data loss

#### Backup & Restore
- [ ] Database backup completes successfully
- [ ] File storage backup works
- [ ] Restore from backup works
- [ ] Point-in-time recovery works
- [ ] Backup schedule runs automatically

**Test Frequency:** Quarterly + before production deployment
**Test Duration:** 4-6 hours

---

## Summary of Manual Testing Effort

| Category | Frequency | Duration | Priority |
|----------|-----------|----------|----------|
| UI/UX (Livewire) | Before release | 8-10 hours | HIGH |
| AWS Textract | Weekly | 2 hours | HIGH |
| Google Drive | Bi-weekly | 2 hours | MEDIUM |
| Neo4j | Weekly | 2 hours | MEDIUM |
| OpenAI API | Weekly | 2 hours | HIGH |
| Court Systems | Weekly | 2 hours | MEDIUM |
| Performance | Before release | 4-6 hours | HIGH |
| Security | Quarterly | 8 hours | CRITICAL |
| Legal Accuracy | Monthly | 4 hours | CRITICAL |
| Cross-browser | Before release | 4 hours | MEDIUM |
| Backup/Restore | Quarterly | 4 hours | HIGH |

**Total Manual Testing Effort:** ~42-50 hours per release cycle

---

## Implementation Guidelines

### Testing Standards

1. **Test Structure:** Follow Arrange-Act-Assert (AAA) pattern
2. **Naming:** Use descriptive test names (`it_creates_job_when_not_exists`)
3. **Isolation:** Each test should be independent
4. **Mocking:** Mock external services (OpenAI, AWS, Google)
5. **Database:** Use `RefreshDatabase` trait
6. **Factories:** Use factories for test data
7. **Assertions:** Be specific in assertions

### Code Coverage Goals

- **Unit Tests:** 80%+ coverage for services and models
- **Feature Tests:** 70%+ coverage for controllers
- **Integration Tests:** Key workflows tested end-to-end

### Continuous Integration

Add to `.github/workflows/tests.yml`:

```yaml
- name: Run tests with coverage
  run: php artisan test --coverage --min=70

- name: Upload coverage to Codecov
  uses: codecov/codecov-action@v3
  with:
    files: ./coverage.xml
```

### Documentation

Each new test file should include:
- Purpose comment at top
- Setup/teardown explanation
- Complex test case explanations

---

## Risk Assessment

### High-Risk Areas (Test First)

1. **Textract Pipeline** - Data loss or corrupted PDFs
2. **Evidence Analysis** - Incorrect legal advice
3. **Defense Strategy** - Bad legal recommendations
4. **Authentication** - Security breach
5. **File Upload** - Malicious file execution

### Medium-Risk Areas

1. Search accuracy
2. Agent execution errors
3. Embedding generation failures
4. Graph database sync issues

### Low-Risk Areas (Test Later)

1. UI cosmetics
2. Timeline visualizations
3. Dashboard widgets
4. Logging and monitoring

---

## Success Metrics

### Coverage Metrics
- Overall coverage: 70%+
- Critical services: 90%+
- Controllers: 80%+
- Models: 70%+

### Quality Metrics
- Test execution time: < 2 minutes
- CI/CD pipeline success rate: > 95%
- Bug escape rate: < 5% to production

### Process Metrics
- Code review includes test review
- New features include tests (100%)
- Bug fixes include regression tests (100%)

---

## Recommended Next Steps

1. **Week 1:** Start Sprint 1 (Pipeline tests)
2. **Week 2:** Continue Sprint 1 + set up CI/CD coverage reporting
3. **Week 3:** Sprint 2 (Services)
4. **Week 4:** Sprint 2 + manual testing protocol setup
5. **Ongoing:** Maintain coverage, add tests for new features

---

## Resources & References

### Testing Documentation
- Laravel Testing Docs: https://laravel.com/docs/testing
- PHPUnit Manual: https://phpunit.de/documentation.html
- Mockery Docs: http://docs.mockery.io/

### Tools
- Laravel Telescope: Debugging and monitoring
- Laravel Pint: Code style enforcement
- Codecov: Coverage reporting

### Contact
- For questions on testing strategy: [Your team lead]
- For legal accuracy validation: [Legal consultant]
- For security review: [Security team]

---

**Document Version:** 1.0
**Last Updated:** 2025-10-29
**Next Review:** After Sprint 3 (Week 6)

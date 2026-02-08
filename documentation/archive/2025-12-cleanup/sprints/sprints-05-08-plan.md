# SPRINTS 5-8: Integration Testing, E2E Scenarios & Browser Testing
## Production Hardening & Feature Completion Plan

**Project**: AI Legal War Machine - Post-Refactoring Hardening
**Duration**: 4 sprints (2-3 weeks total with parallelism)
**Team Size**: 4-6 workers (can work in parallel)
**Goal**: Harden refactored services, add E2E tests, complete UX features, achieve full browser test coverage

---

## Campaign Overview

### Sprints Breakdown

| Sprint | Focus | Duration | Workers | Priority |
|--------|-------|----------|---------|----------|
| **Sprint 5** | Integration Tests (Services) | 3 days | 4 workers | ⭐⭐⭐ CRITICAL |
| **Sprint 6** | E2E Scenarios + External APIs | 3-4 days | 4 workers | ⭐⭐⭐ CRITICAL |
| **Sprint 7** | UX Features Completion | 5-7 days | 3-4 workers | ⭐⭐ HIGH |
| **Sprint 8** | Browser Testing (Dusk) | 4-5 days | 2-3 workers | ⭐⭐ HIGH |

**Total Effort**: 60-80 hours (15-20 hours per sprint)
**Timeline**: 15-19 days (2-3 weeks with parallelism)

---

# SPRINT 5: Integration Tests for Refactored Services
## OpenAI Services + Research Services Integration

**Duration**: 3 days (with 4 workers in parallel)
**Priority**: ⭐⭐⭐ CRITICAL (blocks production deployment)
**Goal**: Add integration tests for newly refactored services to fill testing gap

---

## Sprint 5 Overview

### Objectives

1. ✅ Create integration tests for OpenAI services (Sprint 3 refactoring)
2. ✅ Create integration tests for Research services (Sprint 4 refactoring)
3. ✅ Verify service interactions work end-to-end
4. ✅ Test cache behavior, error handling, retries
5. ✅ All tests passing before proceeding to Sprint 6

### Target: 15-20 Integration Tests

**OpenAI Services**: 8 tests
**Research Services**: 8 tests
**Cross-Service**: 4 tests

---

## Day 1: OpenAI Services Integration Tests (6-8 hours)

### WORKER A: Chat Service Integration (2-3 hours)

**Branch**: `sprint5/openai-chat-integration-tests`
**File**: `tests/Feature/Services/OpenAIChatServiceIntegrationTest.php`

**Tests to Write** (3 tests, ~200 lines):

1. **test_chat_service_integrates_with_cache**
   ```php
   /**
    * Test chat service properly uses cache service
    * Flow: Request → Check cache → Miss → OpenAI API → Store in cache → Return
    */
   public function test_chat_service_integrates_with_cache(): void
   {
       // Mock OpenAI HTTP response
       Http::fake([
           'api.openai.com/*' => Http::response([
               'choices' => [['message' => ['content' => 'Test response']]],
               'usage' => ['total_tokens' => 100],
           ])
       ]);

       $chat = app(ChatServiceInterface::class);

       // First call - cache miss, should hit API
       $response1 = $chat->chat([
           ['role' => 'user', 'content' => 'Hello']
       ]);

       Http::assertSentCount(1); // API called

       // Second call - cache hit, should NOT hit API
       $response2 = $chat->chat([
           ['role' => 'user', 'content' => 'Hello']
       ]);

       Http::assertSentCount(1); // Still 1 (cached)

       $this->assertEquals($response1, $response2);
   }
   ```

2. **test_chat_service_respects_cache_ttl**
   ```php
   /**
    * Test cache TTL is properly set for chat operations (1 hour)
    */
   public function test_chat_service_respects_cache_ttl(): void
   {
       // Test that cache expires after 1 hour
       // Use Carbon::setTestNow() to simulate time passage
   }
   ```

3. **test_chat_service_handles_api_errors_gracefully**
   ```php
   /**
    * Test error handling and retries
    * Flow: API fails → Retry → Circuit breaker opens if repeated failures
    */
   public function test_chat_service_handles_api_errors_gracefully(): void
   {
       // Mock API failures
       // Verify retries
       // Verify circuit breaker activates
       // Verify error is properly logged and thrown
   }
   ```

**Acceptance Criteria**:
- [ ] 3 tests written and passing
- [ ] Real cache interaction tested (not mocked)
- [ ] HTTP client properly mocked
- [ ] Error scenarios covered

---

### WORKER B: Embedding Service Integration (2-3 hours)

**Branch**: `sprint5/openai-embedding-integration-tests`
**File**: `tests/Feature/Services/OpenAIEmbeddingServiceIntegrationTest.php`

**Tests to Write** (3 tests, ~250 lines):

1. **test_embedding_service_stores_in_vector_database**
   ```php
   /**
    * Test embeddings are properly stored in vector database
    * Flow: Text → Embedding service → Cache check → OpenAI API → Vector store → Return
    */
   public function test_embedding_service_stores_in_vector_database(): void
   {
       Http::fake([
           'api.openai.com/*' => Http::response([
               'data' => [['embedding' => array_fill(0, 1536, 0.1)]],
           ])
       ]);

       $embeddingService = app(EmbeddingServiceInterface::class);
       $vectorStore = app(LawVectorStoreService::class);

       $text = 'Test legal text about criminal procedure';

       // Generate embedding
       $embedding = $embeddingService->embeddings($text);

       // Verify embedding dimensions
       $this->assertCount(1536, $embedding['data'][0]['embedding']);

       // Store in vector database
       $vectorStore->ingest($text, [
           'doc_id' => 'test-123',
           'title' => 'Test Document',
       ]);

       // Search should find the document
       $results = $vectorStore->search('criminal procedure', limit: 5);

       $this->assertGreaterThan(0, count($results));
       $this->assertEquals('Test Document', $results[0]['metadata']['title']);
   }
   ```

2. **test_embedding_service_uses_24h_cache**
   ```php
   /**
    * Test embeddings are cached for 24 hours
    */
   public function test_embedding_service_uses_24h_cache(): void
   {
       // Mock embedding generation
       // Verify cache stores with 24h TTL
       // Verify subsequent calls use cache
   }
   ```

3. **test_batch_embeddings_handle_partial_failures**
   ```php
   /**
    * Test batch embedding with some failures
    * Should continue processing and return partial results
    */
   public function test_batch_embeddings_handle_partial_failures(): void
   {
       // Mock batch with some successes, some failures
       // Verify partial results returned
       // Verify errors logged
   }
   ```

**Acceptance Criteria**:
- [ ] 3 tests written and passing
- [ ] Vector store integration tested
- [ ] Cache behavior verified
- [ ] Batch processing tested

---

### WORKER C: Analysis Service Integration (2-3 hours)

**Branch**: `sprint5/openai-analysis-integration-tests`
**File**: `tests/Feature/Services/OpenAIAnalysisServiceIntegrationTest.php`

**Tests to Write** (2 tests, ~200 lines):

1. **test_analysis_service_uses_chat_and_cache**
   ```php
   /**
    * Test analysis service delegates to chat service and uses cache
    * Flow: Legal text → Analysis service → Chat service → Cache → OpenAI → Parse → Return
    */
   public function test_analysis_service_uses_chat_and_cache(): void
   {
       Http::fake([
           'api.openai.com/*' => Http::response([
               'choices' => [[
                   'message' => [
                       'content' => json_encode([
                           'summary' => 'Test summary',
                           'key_points' => ['Point 1', 'Point 2'],
                           'citations' => ['ZKP Članak 9', 'KZ Članak 234'],
                       ])
                   ]
               ]],
           ])
       ]);

       $analysis = app(AnalysisServiceInterface::class);

       $result = $analysis->analyzeLegalText('Test legal text about criminal procedure');

       $this->assertArrayHasKey('summary', $result);
       $this->assertArrayHasKey('key_points', $result);
       $this->assertArrayHasKey('citations', $result);
       $this->assertCount(2, $result['key_points']);
       $this->assertCount(2, $result['citations']);

       // Second call should be cached (verify HTTP call count)
       Http::assertSentCount(1);

       $result2 = $analysis->analyzeLegalText('Test legal text about criminal procedure');

       Http::assertSentCount(1); // Still 1 (cached)
   }
   ```

2. **test_analysis_service_extracts_croatian_citations**
   ```php
   /**
    * Test citation extraction for Croatian legal references
    * Should recognize: ZKP, KZ, Ustav RH, NN (Narodne Novine)
    */
   public function test_analysis_service_extracts_croatian_citations(): void
   {
       // Test with text containing Croatian citations
       // Verify proper parsing of:
       // - ZKP Članak X
       // - Kazneni zakon Članak Y
       // - Ustav Republike Hrvatske Članak Z
       // - NN XX/YY (official gazette)
   }
   ```

**Acceptance Criteria**:
- [ ] 2 tests written and passing
- [ ] Chat service integration verified
- [ ] Croatian legal citation extraction tested
- [ ] Cache behavior verified

---

## Day 2: Research Services Integration Tests (6-8 hours)

### WORKER A: Question Generator Integration (2-3 hours)

**Branch**: `sprint5/research-question-generator-integration-tests`
**File**: `tests/Feature/Services/Research/QuestionGeneratorIntegrationTest.php`

**Tests to Write** (2 tests, ~200 lines):

1. **test_question_generator_uses_openai_and_generates_valid_questions**
   ```php
   /**
    * Test full question generation pipeline
    * Flow: Query → Build context → OpenAI API → Parse questions → Validate → Return
    */
   public function test_question_generator_uses_openai_and_generates_valid_questions(): void
   {
       Http::fake([
           'api.openai.com/*' => Http::response([
               'choices' => [[
                   'message' => [
                       'content' => json_encode([
                           'questions' => [
                               'What are the requirements for criminal liability?',
                               'What defenses are available under Croatian law?',
                               'What are the penalties for this type of offense?',
                           ]
                       ])
                   ]
               ]],
           ])
       ]);

       $generator = app(QuestionGeneratorInterface::class);

       $questions = $generator->generate('Research criminal liability for drug offenses');

       $this->assertIsArray($questions);
       $this->assertGreaterThanOrEqual(3, count($questions));
       $this->assertStringContainsString('criminal', $questions[0]);

       Http::assertSent(function ($request) {
           return str_contains($request->url(), 'openai.com')
               && str_contains($request->body(), 'research questions');
       });
   }
   ```

2. **test_question_generator_refines_based_on_previous_results**
   ```php
   /**
    * Test question refinement uses evaluation feedback
    * Flow: Previous questions + results + evaluation → OpenAI → Refined questions
    */
   public function test_question_generator_refines_based_on_previous_results(): void
   {
       // Mock OpenAI response with refined questions
       // Test that refined questions address gaps from evaluation
       // Verify previous results are included in context
   }
   ```

**Acceptance Criteria**:
- [ ] 2 tests written and passing
- [ ] OpenAI integration tested
- [ ] Question validation tested
- [ ] Refinement logic tested

---

### WORKER B: Search Executor Integration (2-3 hours)

**Branch**: `sprint5/research-search-executor-integration-tests`
**File**: `tests/Feature/Services/Research/SearchExecutorIntegrationTest.php`

**Tests to Write** (3 tests, ~300 lines):

1. **test_search_executor_queries_all_search_services**
   ```php
   /**
    * Test search executor integrates with all search services
    * Flow: Questions → Law search + Decision search + Case search → Aggregate → Return
    */
   public function test_search_executor_queries_all_search_services(): void
   {
       // Create test data in all corpora
       Law::factory()->create(['title' => 'Test Criminal Code', 'content' => 'criminal liability']);
       CourtDecision::factory()->create(['title' => 'Test Decision', 'text' => 'criminal liability']);
       CaseDocument::factory()->create(['file_name' => 'Test Case', 'extracted_text' => 'criminal liability']);

       // Mock OpenAI for embeddings
       Http::fake(['api.openai.com/*' => Http::response([
           'data' => [['embedding' => array_fill(0, 1536, 0.1)]],
       ])]);

       $executor = app(SearchExecutorInterface::class);

       $questions = ['What is criminal liability under Croatian law?'];
       $results = $executor->execute($questions);

       // Verify results from all corpora
       $this->assertArrayHasKey('laws', $results);
       $this->assertArrayHasKey('decisions', $results);
       $this->assertArrayHasKey('cases', $results);

       $this->assertGreaterThan(0, count($results['laws']));
       $this->assertGreaterThan(0, count($results['decisions']));
       $this->assertGreaterThan(0, count($results['cases']));
   }
   ```

2. **test_search_executor_handles_search_failures_gracefully**
   ```php
   /**
    * Test graceful degradation when some searches fail
    * If law search fails, should still return decision + case results
    */
   public function test_search_executor_handles_search_failures_gracefully(): void
   {
       // Mock law search to fail
       // Verify other searches still execute
       // Verify partial results returned
       // Verify error logged
   }
   ```

3. **test_search_executor_aggregates_and_deduplicates_results**
   ```php
   /**
    * Test result aggregation and deduplication
    * Same content in multiple corpora should be deduplicated
    */
   public function test_search_executor_aggregates_and_deduplicates_results(): void
   {
       // Create duplicate content across corpora
       // Execute search
       // Verify deduplication works
   }
   ```

**Acceptance Criteria**:
- [ ] 3 tests written and passing
- [ ] All search services integrated
- [ ] Error handling tested
- [ ] Deduplication verified

---

### WORKER C: Answer Evaluator Integration (1-2 hours)

**Branch**: `sprint5/research-answer-evaluator-integration-tests`
**File**: `tests/Feature/Services/Research/AnswerEvaluatorIntegrationTest.php`

**Tests to Write** (1 test, ~150 lines):

1. **test_answer_evaluator_uses_openai_and_returns_quality_score**
   ```php
   /**
    * Test answer evaluation pipeline
    * Flow: Query + answer + sources → OpenAI evaluation → Parse score + gaps → Return
    */
   public function test_answer_evaluator_uses_openai_and_returns_quality_score(): void
   {
       Http::fake([
           'api.openai.com/*' => Http::response([
               'choices' => [[
                   'message' => [
                       'content' => json_encode([
                           'quality_score' => 87,
                           'completeness' => 'good',
                           'citation_quality' => 'excellent',
                           'gaps' => ['Missing discussion of penalties'],
                       ])
                   ]
               ]],
           ])
       ]);

       $evaluator = app(AnswerEvaluatorInterface::class);

       $query = 'What is criminal liability?';
       $answer = 'Criminal liability requires...';
       $sources = [
           ['title' => 'ZKP', 'content' => '...'],
           ['title' => 'KZ', 'content' => '...'],
       ];

       $evaluation = $evaluator->evaluate($query, $answer, $sources);

       $this->assertArrayHasKey('quality_score', $evaluation);
       $this->assertArrayHasKey('gaps', $evaluation);
       $this->assertEquals(87, $evaluation['quality_score']);
       $this->assertIsArray($evaluation['gaps']);
   }
   ```

**Acceptance Criteria**:
- [ ] 1 test written and passing
- [ ] OpenAI integration tested
- [ ] Evaluation parsing tested

---

### WORKER D: Quality Assessor + Research Orchestrator (2-3 hours)

**Branch**: `sprint5/research-orchestrator-integration-tests`
**File**: `tests/Feature/Services/ResearchOrchestratorIntegrationTest.php`

**Tests to Write** (2 tests, ~300 lines):

1. **test_research_orchestrator_executes_full_pipeline**
   ```php
   /**
    * Test full research pipeline integration
    * Flow: Query → Generate questions → Search → Evaluate → Assess quality → Return
    */
   public function test_research_orchestrator_executes_full_pipeline(): void
   {
       // Create test data
       Law::factory()->create(['title' => 'Criminal Code', 'content' => 'criminal liability']);

       // Mock OpenAI responses for all stages
       Http::fake([
           'api.openai.com/*' => Http::sequence()
               ->push(['data' => [['embedding' => array_fill(0, 1536, 0.1)]]]) // Embedding
               ->push(['choices' => [['message' => ['content' => json_encode(['questions' => ['Q1', 'Q2']])]]]) // Question generation
               ->push(['choices' => [['message' => ['content' => 'Answer about criminal liability']]]]) // Answer synthesis
               ->push(['choices' => [['message' => ['content' => json_encode(['quality_score' => 88])]]]) // Evaluation
       ]);

       $orchestrator = app(ResearchOrchestrator::class);

       $result = $orchestrator->research('What is criminal liability?', [
           'max_iterations' => 1, // Single iteration for test speed
       ]);

       $this->assertArrayHasKey('answer', $result);
       $this->assertArrayHasKey('quality_score', $result);
       $this->assertArrayHasKey('sources', $result);
       $this->assertArrayHasKey('iterations', $result);

       $this->assertGreaterThanOrEqual(85, $result['quality_score']); // Quality threshold
   }
   ```

2. **test_quality_threshold_triggers_iteration**
   ```php
   /**
    * Test that low quality score triggers another iteration
    * Flow: Query → Search → Evaluate (score=75) → Refine questions → Search again → Evaluate (score=88) → Stop
    */
   public function test_quality_threshold_triggers_iteration(): void
   {
       // Mock low quality on first iteration, high quality on second
       Http::fake([
           'api.openai.com/*' => Http::sequence()
               ->push(['data' => [['embedding' => array_fill(0, 1536, 0.1)]]]) // Embedding
               ->push(['choices' => [['message' => ['content' => json_encode(['questions' => ['Q1']])]]]) // Questions
               ->push(['choices' => [['message' => ['content' => 'Incomplete answer']]]]) // Answer
               ->push(['choices' => [['message' => ['content' => json_encode(['quality_score' => 75])]]]) // Low score
               ->push(['choices' => [['message' => ['content' => json_encode(['questions' => ['Q1 refined']])]]]) // Refined questions
               ->push(['choices' => [['message' => ['content' => 'Complete answer']]]]) // Better answer
               ->push(['choices' => [['message' => ['content' => json_encode(['quality_score' => 88])]]]) // High score
       ]);

       $orchestrator = app(ResearchOrchestrator::class);

       $result = $orchestrator->research('What is criminal liability?', [
           'quality_threshold' => 85,
       ]);

       $this->assertEquals(2, $result['iterations']); // Should iterate twice
       $this->assertGreaterThanOrEqual(85, $result['quality_score']);
   }
   ```

**Acceptance Criteria**:
- [ ] 2 tests written and passing
- [ ] Full pipeline tested
- [ ] Iteration logic verified
- [ ] Quality threshold tested

---

## Day 3: Cross-Service Integration Tests (4-6 hours)

### ALL WORKERS: Collaborative Session (pair programming recommended)

**File**: `tests/Feature/Services/MultiServiceIntegrationTest.php`

**Tests to Write** (4 tests, ~400 lines):

1. **test_openai_plus_search_plus_graph_integration**
   ```php
   /**
    * Test multiple services working together
    * Flow: User query → OpenAI analysis → Search → Graph relationships → Combined results
    */
   public function test_openai_plus_search_plus_graph_integration(): void
   {
       // Create interconnected data (laws, decisions with citations)
       $law = Law::factory()->create(['doc_id' => 'zkp-9']);
       $decision = CourtDecision::factory()->create(['text' => 'References ZKP Članak 9']);

       // Sync to graph
       app(GraphRagOrchestrator::class)->syncLaw($law->doc_id);
       app(GraphRagOrchestrator::class)->syncDecision($decision->id);

       // Mock OpenAI
       Http::fake([
           'api.openai.com/*' => Http::response([
               'data' => [['embedding' => array_fill(0, 1536, 0.1)]],
           ])
       ]);

       // Search with OpenAI embeddings
       $searchResults = app(SearchOrchestrator::class)->search('criminal procedure', [
           'corpora' => ['laws', 'decisions'],
       ]);

       // Verify graph relationships were used
       $graphRelations = app(GraphRagOrchestrator::class)->findRelatedDocuments($law->doc_id);

       $this->assertGreaterThan(0, count($searchResults['results']));
       $this->assertGreaterThan(0, count($graphRelations));
   }
   ```

2. **test_budget_limits_stop_research**
   ```php
   /**
    * Test token budget prevents infinite iterations
    * Flow: Research starts → Consumes tokens → Budget exceeded → Stop with partial results
    */
   public function test_budget_limits_stop_research(): void
   {
       // Mock OpenAI to consume lots of tokens
       Http::fake([
           'api.openai.com/*' => Http::response([
               'usage' => ['total_tokens' => 10000], // Each call uses 10k tokens
               'choices' => [['message' => ['content' => json_encode(['quality_score' => 70])]]],
           ])
       ]);

       $orchestrator = app(ResearchOrchestrator::class);

       $result = $orchestrator->research('Complex legal query', [
           'token_budget' => 15000, // Only 15k tokens available
           'quality_threshold' => 85,
       ]);

       // Should stop after 1-2 iterations due to budget
       $this->assertLessThanOrEqual(2, $result['iterations']);
       $this->assertArrayHasKey('stopped_reason', $result);
       $this->assertEquals('token_budget_exceeded', $result['stopped_reason']);
   }
   ```

3. **test_time_budget_stops_research**
   ```php
   /**
    * Test time budget prevents long-running research
    */
   public function test_time_budget_stops_research(): void
   {
       // Use Carbon to simulate time passage
       // Verify research stops when time budget exceeded
   }
   ```

4. **test_full_case_workflow_with_all_services**
   ```php
   /**
    * Test complete case analysis workflow
    * Flow: Upload document → OCR (Textract) → Analysis (OpenAI) → Search similar cases → Graph relationships → Generate motion
    */
   public function test_full_case_workflow_with_all_services(): void
   {
       // Create case with document
       $case = LegalCase::factory()->create();
       $document = CaseDocument::factory()->create(['case_id' => $case->id]);

       // Mock Textract (already processed)
       $document->update(['extracted_text' => 'Test case about criminal liability']);

       // Mock OpenAI for analysis
       Http::fake([
           'api.openai.com/*' => Http::response([
               'choices' => [[
                   'message' => ['content' => json_encode([
                       'summary' => 'Case involves criminal liability',
                       'key_points' => ['Point 1'],
                       'citations' => ['ZKP Članak 9'],
                   ])]
               ]],
           ])
       ]);

       // Analyze document
       $analysis = app(AnalysisServiceInterface::class)->analyzeLegalText($document->extracted_text);

       // Search for similar cases
       $similarCases = app(SearchOrchestrator::class)->search('criminal liability', [
           'corpora' => ['cases'],
       ]);

       // Sync to graph
       app(GraphRagOrchestrator::class)->syncCase($case->id);

       // Verify all components worked
       $this->assertNotEmpty($analysis['summary']);
       $this->assertGreaterThan(0, count($similarCases['results']));
   }
   ```

**Acceptance Criteria**:
- [ ] 4 tests written and passing
- [ ] Multi-service interactions tested
- [ ] Budget limits tested (token + time)
- [ ] Full workflow tested

---

## Sprint 5 Deliverables

### Files Created (15-20 tests total):

1. `tests/Feature/Services/OpenAIChatServiceIntegrationTest.php` (3 tests)
2. `tests/Feature/Services/OpenAIEmbeddingServiceIntegrationTest.php` (3 tests)
3. `tests/Feature/Services/OpenAIAnalysisServiceIntegrationTest.php` (2 tests)
4. `tests/Feature/Services/Research/QuestionGeneratorIntegrationTest.php` (2 tests)
5. `tests/Feature/Services/Research/SearchExecutorIntegrationTest.php` (3 tests)
6. `tests/Feature/Services/Research/AnswerEvaluatorIntegrationTest.php` (1 test)
7. `tests/Feature/Services/ResearchOrchestratorIntegrationTest.php` (2 tests)
8. `tests/Feature/Services/MultiServiceIntegrationTest.php` (4 tests)

**Total**: ~20 integration tests, ~2,000 lines of test code

### Acceptance Criteria:

- [ ] All 20 tests written
- [ ] All tests pass (100%)
- [ ] OpenAI services integration covered
- [ ] Research services integration covered
- [ ] Multi-service interactions tested
- [ ] Budget limits tested
- [ ] Error scenarios covered
- [ ] No mocking of services under test (only external APIs)

---

# SPRINT 6: E2E Scenarios + External API Integration
## End-to-End Workflows & External System Testing

**Duration**: 3-4 days (with 4 workers in parallel)
**Priority**: ⭐⭐⭐ CRITICAL
**Goal**: Test complete user workflows and external API integrations

---

## Sprint 6 Overview

### Objectives

1. ✅ Test end-to-end user workflows (upload → analyze → results)
2. ✅ Test external API integrations (OpenAI, Neo4j, AWS Textract)
3. ✅ Test error scenarios and resilience
4. ✅ Load testing for critical paths
5. ✅ Performance benchmarks

### Target: 12-15 E2E Tests + External API Tests

---

## Day 1: End-to-End Workflows (6-8 hours)

### WORKER A: Case Analysis Workflow (3-4 hours)

**File**: `tests/Feature/Workflows/CaseAnalysisWorkflowTest.php`

**Scenarios** (3 tests, ~400 lines):

1. **test_complete_case_analysis_workflow**
   ```php
   /**
    * Test: Upload case → Extract text → Analyze → Search similar → Graph relationships → Generate motion
    * This is the PRIMARY user workflow
    */
   public function test_complete_case_analysis_workflow(): void
   {
       // 1. Create case and upload document
       $case = LegalCase::factory()->create(['case_number' => 'K-123/2025']);

       // 2. Upload PDF (mock file upload)
       $file = UploadedFile::fake()->create('evidence.pdf', 1000);
       $response = $this->post("/api/cases/{$case->id}/documents", ['file' => $file]);
       $response->assertStatus(201);

       $documentId = $response->json('document_id');

       // 3. Simulate Textract processing
       $document = CaseDocument::find($documentId);
       $document->update([
           'extracted_text' => 'Evidence shows criminal liability for drug possession...',
           'textract_status' => 'completed',
       ]);

       // 4. Analyze document
       $response = $this->post("/api/evidence/analyze", [
           'document_id' => $documentId,
       ]);
       $response->assertStatus(200);
       $analysis = $response->json();

       $this->assertArrayHasKey('summary', $analysis);
       $this->assertArrayHasKey('key_points', $analysis);

       // 5. Search for similar cases
       $response = $this->post("/api/search", [
           'query' => 'drug possession criminal liability',
           'corpora' => ['cases', 'decisions'],
       ]);
       $response->assertStatus(200);
       $similarCases = $response->json();

       $this->assertGreaterThan(0, count($similarCases['results']));

       // 6. Sync to graph
       $response = $this->post("/api/graph/sync/case/{$case->id}");
       $response->assertStatus(200);

       // 7. Generate suppression motion
       $response = $this->post("/api/evidence/suppression-motion", [
           'case_id' => $case->id,
           'document_id' => $documentId,
       ]);
       $response->assertStatus(200);
       $motion = $response->json();

       $this->assertArrayHasKey('motion_text', $motion);
       $this->assertArrayHasKey('legal_basis', $motion);
       $this->assertStringContainsString('ZKP', $motion['legal_basis']);
   }
   ```

2. **test_misconduct_detection_workflow**
   ```php
   /**
    * Test: Upload prosecutor evidence → Detect misconduct → Generate dismissal motion → Generate ethics complaint
    */
   ```

3. **test_topic_analysis_workflow**
   ```php
   /**
    * Test: Select topic (drug charges) → Analyze case → Detect abuse → Generate statistics
    */
   ```

**Acceptance Criteria**:
- [ ] 3 workflow tests written and passing
- [ ] All API endpoints tested
- [ ] Database state verified at each step
- [ ] Generated documents validated

---

### WORKER B: Research Pipeline Workflow (3-4 hours)

**File**: `tests/Feature/Workflows/ResearchPipelineWorkflowTest.php`

**Scenarios** (3 tests, ~350 lines):

1. **test_complete_research_pipeline**
   ```php
   /**
    * Test: User query → Generate questions → Search all corpora → Evaluate → Iterate if needed → Final answer
    */
   public function test_complete_research_pipeline(): void
   {
       // Create test data across all corpora
       Law::factory()->count(5)->create(['content' => 'criminal liability']);
       CourtDecision::factory()->count(3)->create(['text' => 'criminal liability']);
       CaseDocument::factory()->count(2)->create(['extracted_text' => 'criminal liability']);

       // Execute full research
       $response = $this->post("/api/research/execute", [
           'query' => 'What are the requirements for criminal liability under Croatian law?',
           'max_iterations' => 3,
       ]);

       $response->assertStatus(200);
       $result = $response->json();

       $this->assertArrayHasKey('answer', $result);
       $this->assertArrayHasKey('quality_score', $result);
       $this->assertArrayHasKey('iterations', $result);
       $this->assertArrayHasKey('sources', $result);

       $this->assertGreaterThanOrEqual(85, $result['quality_score']);
       $this->assertGreaterThan(0, count($result['sources']));
   }
   ```

2. **test_research_with_quality_iterations**
   ```php
   /**
    * Test research iterates until quality threshold met
    */
   ```

3. **test_research_stops_on_budget_limit**
   ```php
   /**
    * Test research respects token/time budgets
    */
   ```

**Acceptance Criteria**:
- [ ] 3 workflow tests written and passing
- [ ] Full research pipeline tested
- [ ] Iteration logic verified
- [ ] Budget enforcement tested

---

## Day 2: External API Integration - OpenAI (4-6 hours)

### WORKER A + WORKER B: OpenAI Real API Testing (collaborative)

**File**: `tests/Feature/ExternalAPIs/OpenAIIntegrationTest.php`

**Note**: These tests call REAL OpenAI API (cost money, run sparingly)

**Setup**:
```php
/**
 * @group external-api
 * @group openai
 * @group slow
 *
 * These tests hit REAL OpenAI API and cost money.
 * Only run when needed: ./vendor/bin/phpunit --group openai
 */
class OpenAIIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!config('openai.api_key')) {
            $this->markTestSkipped('OpenAI API key not configured');
        }
    }
}
```

**Tests** (5 tests, ~300 lines):

1. **test_real_chat_completion_call**
   ```php
   /**
    * Test actual OpenAI chat completion
    * Verifies: API connection, authentication, response format
    */
   public function test_real_chat_completion_call(): void
   {
       $chat = app(ChatServiceInterface::class);

       $response = $chat->chat([
           ['role' => 'system', 'content' => 'You are a legal assistant'],
           ['role' => 'user', 'content' => 'What is criminal liability? (answer in 10 words)'],
       ], 'gpt-4o-mini'); // Use cheap model

       $this->assertIsArray($response);
       $this->assertArrayHasKey('choices', $response);
       $this->assertArrayHasKey('usage', $response);

       $content = $response['choices'][0]['message']['content'];
       $this->assertNotEmpty($content);
       $this->assertLessThan(200, strlen($content)); // Should be short

       // Verify cost is reasonable
       $tokens = $response['usage']['total_tokens'];
       $this->assertLessThan(100, $tokens); // Should be cheap call
   }
   ```

2. **test_real_embedding_generation**
   ```php
   /**
    * Test actual OpenAI embedding generation
    */
   public function test_real_embedding_generation(): void
   {
       $embedding = app(EmbeddingServiceInterface::class);

       $result = $embedding->embeddings('Test legal text');

       $this->assertIsArray($result);
       $this->assertArrayHasKey('data', $result);
       $this->assertCount(1536, $result['data'][0]['embedding']);
   }
   ```

3. **test_rate_limiting_and_retries**
   ```php
   /**
    * Test rate limiting doesn't cause failures
    * Make multiple rapid requests
    */
   ```

4. **test_circuit_breaker_activates_on_failures**
   ```php
   /**
    * Test circuit breaker opens after repeated failures
    * (Hard to test with real API, might need to mock some parts)
    */
   ```

5. **test_real_api_error_handling**
   ```php
   /**
    * Test error handling with invalid requests
    */
   public function test_real_api_error_handling(): void
   {
       $chat = app(ChatServiceInterface::class);

       try {
           // Invalid model name
           $chat->chat([
               ['role' => 'user', 'content' => 'Test'],
           ], 'invalid-model-name');

           $this->fail('Should have thrown exception');
       } catch (\Exception $e) {
           $this->assertStringContainsString('model', strtolower($e->getMessage()));
       }
   }
   ```

**Acceptance Criteria**:
- [ ] 5 real API tests written
- [ ] Tests only run when explicitly requested (--group openai)
- [ ] Cost per test run documented
- [ ] Error scenarios tested

---

## Day 3: External API Integration - Neo4j & Textract (4-6 hours)

### WORKER C: Neo4j Integration Testing (2-3 hours)

**File**: `tests/Feature/ExternalAPIs/Neo4jIntegrationTest.php`

**Tests** (4 tests, ~300 lines):

1. **test_neo4j_connection_and_query**
   ```php
   /**
    * @group external-api
    * * @group neo4j
    */
   public function test_neo4j_connection_and_query(): void
   {
       $graph = app(GraphDatabaseService::class);

       // Test connection
       $this->assertTrue($graph->isAvailable());

       // Create test node
       $result = $graph->run('CREATE (n:TestNode {name: $name}) RETURN n', [
           'name' => 'Test ' . uniqid(),
       ]);

       $this->assertNotEmpty($result);

       // Clean up
       $graph->run('MATCH (n:TestNode) DELETE n');
   }
   ```

2. **test_neo4j_handles_large_batch_operations**
   ```php
   /**
    * Test batch sync of 100+ documents
    * Verifies performance and stability under load
    */
   public function test_neo4j_handles_large_batch_operations(): void
   {
       $laws = Law::factory()->count(100)->create();

       $orchestrator = app(GraphRagOrchestrator::class);

       $startTime = microtime(true);

       foreach ($laws as $law) {
           $orchestrator->syncLaw($law->doc_id);
       }

       $elapsed = microtime(true) - $startTime;

       // Should complete in reasonable time (<30s for 100 items)
       $this->assertLessThan(30, $elapsed);
   }
   ```

3. **test_neo4j_relationship_queries**
   ```php
   /**
    * Test complex relationship queries
    */
   ```

4. **test_neo4j_error_recovery**
   ```php
   /**
    * Test recovery from Neo4j connection failures
    */
   ```

**Acceptance Criteria**:
- [ ] 4 Neo4j tests written
- [ ] Connection tested
- [ ] Performance benchmarks established
- [ ] Error recovery tested

---

### WORKER D: AWS Textract Integration Testing (2-3 hours)

**File**: `tests/Feature/ExternalAPIs/TextractIntegrationTest.php`

**Tests** (3 tests, ~250 lines):

1. **test_textract_processes_real_pdf**
   ```php
   /**
    * @group external-api
    * @group textract
    * @group slow
    *
    * This test uploads to S3 and calls AWS Textract (costs money)
    */
   public function test_textract_processes_real_pdf(): void
   {
       // Create test PDF
       $pdf = $this->createTestPDF('Test legal document with text');

       // Upload to Google Drive (mock or real)
       // Trigger Textract pipeline
       $response = $this->post("/api/textract/process", [
           'file_id' => 'test-file-id',
       ]);

       $response->assertStatus(200);

       // Wait for processing (or use queue testing)
       // Verify extracted text
   }
   ```

2. **test_textract_handles_errors_gracefully**
   ```php
   /**
    * Test error handling (invalid PDF, AWS errors, etc.)
    */
   ```

3. **test_textract_searchable_pdf_generation**
   ```php
   /**
    * Test searchable PDF creation (text overlay)
    */
   ```

**Acceptance Criteria**:
- [ ] 3 Textract tests written
- [ ] Real AWS integration tested (sparingly due to cost)
- [ ] Error scenarios covered
- [ ] Searchable PDF validated

---

## Day 4: Load Testing & Performance (4-6 hours)

### ALL WORKERS: Collaborative Load Testing

**File**: `tests/Feature/Performance/LoadTest.php`

**Tests** (4 tests, ~400 lines):

1. **test_api_handles_concurrent_search_requests**
   ```php
   /**
    * Test 10+ concurrent search requests
    * Verify: No errors, reasonable response time, no deadlocks
    */
   ```

2. **test_queue_processes_high_volume**
   ```php
   /**
    * Test queue handling 100+ jobs
    * Verify: All jobs complete, no failures, reasonable time
    */
   ```

3. **test_database_handles_concurrent_writes**
   ```php
   /**
    * Test concurrent writes to database
    * Verify: No race conditions, data integrity maintained
    */
   ```

4. **test_graph_sync_performance_at_scale**
   ```php
   /**
    * Test syncing 500+ documents to Neo4j
    * Establish performance baseline
    */
   ```

**Acceptance Criteria**:
- [ ] 4 load tests written
- [ ] Performance baselines established
- [ ] No errors under load
- [ ] Bottlenecks identified and documented

---

## Sprint 6 Deliverables

### Files Created:

1. `tests/Feature/Workflows/CaseAnalysisWorkflowTest.php` (3 tests)
2. `tests/Feature/Workflows/ResearchPipelineWorkflowTest.php` (3 tests)
3. `tests/Feature/ExternalAPIs/OpenAIIntegrationTest.php` (5 tests)
4. `tests/Feature/ExternalAPIs/Neo4jIntegrationTest.php` (4 tests)
5. `tests/Feature/ExternalAPIs/TextractIntegrationTest.php` (3 tests)
6. `tests/Feature/Performance/LoadTest.php` (4 tests)

**Total**: ~22 E2E + External API tests, ~2,000 lines

### Acceptance Criteria:

- [ ] All workflow tests pass
- [ ] External API tests pass (when run with real APIs)
- [ ] Performance benchmarks documented
- [ ] Load tests establish baselines
- [ ] Error scenarios covered

---

# SPRINT 7: UX Features Completion
## Frontend Feature Implementation

**Duration**: 5-7 days (with 3-4 workers in parallel)
**Priority**: ⭐⭐ HIGH
**Goal**: Complete missing UX features and polish existing ones

---

## Sprint 7 Overview

### Features to Implement:

1. Decision Discovery Dashboard
2. Collaboration Features
3. Eoglasna Monitoring Enhancements
4. OpenAI Log Viewer
5. Vector Store Manager

---

## Day 1-2: Decision Discovery Dashboard (8-12 hours)

### WORKER A: Backend API (4-6 hours)

**Files to Create**:
- `app/Http/Controllers/DecisionDiscoveryController.php`
- `app/Services/DecisionDiscoveryService.php`
- `tests/Feature/DecisionDiscoveryTest.php`

**API Endpoints**:

1. `POST /api/decisions/discover` - Start discovery
2. `GET /api/decisions/discoveries` - List discoveries
3. `GET /api/decisions/discoveries/{id}` - Get discovery details
4. `POST /api/decisions/discoveries/{id}/ingest` - Ingest selected decisions
5. `GET /api/decisions/discoveries/{id}/stats` - Get statistics

**Features**:
- Search odluke.sudovi.hr by keywords
- Filter by court, date range, decision type
- Preview decisions before ingesting
- Batch ingest selected decisions
- Track discovery progress

**Tests** (5 tests, ~300 lines):
```php
test_decision_discovery_searches_odluke_api()
test_decision_discovery_filters_results()
test_decision_ingestion_creates_records()
test_decision_discovery_tracks_progress()
test_discovery_statistics_calculation()
```

---

### WORKER B: Frontend Component (4-6 hours)

**File**: `app/Http/Livewire/DecisionDiscoveryDashboard.php`

**UI Features**:
- Search form (keywords, filters)
- Results table (paginated)
- Preview modal
- Batch selection
- Ingest progress bar
- Statistics cards

**Livewire Methods**:
```php
public function search()
public function preview($decisionId)
public function selectForIngest($decisionId)
public function ingestSelected()
public function refreshStats()
```

**Test**: `tests/Feature/Livewire/DecisionDiscoveryDashboardTest.php`

---

## Day 3-4: Collaboration Features (8-12 hours)

### WORKER A: Backend (4-6 hours)

**Features**:
- Case sharing between users
- Comments on cases/documents
- Activity feed
- Notifications
- Team workspaces

**Files**:
- `app/Models/CaseShare.php`
- `app/Models/Comment.php`
- `app/Models/Activity.php`
- `app/Services/CollaborationService.php`
- `tests/Feature/CollaborationTest.php`

**API Endpoints**:
```php
POST /api/cases/{id}/share
POST /api/cases/{id}/comments
GET /api/cases/{id}/activity
GET /api/notifications
POST /api/workspaces
```

---

### WORKER B: Frontend (4-6 hours)

**Components**:
- Share modal
- Comments section
- Activity timeline
- Notification bell
- Workspace switcher

**Files**:
- `app/Http/Livewire/CaseSharing.php`
- `app/Http/Livewire/CommentSection.php`
- `app/Http/Livewire/ActivityFeed.php`
- `app/Http/Livewire/NotificationCenter.php`

---

## Day 5: Eoglasna Monitoring (4-6 hours)

### WORKER C: Enhanced Monitoring

**Features to Add**:
- Keyword alerts (email/Slack when keywords found)
- Export court notices to PDF
- Archive browsing
- Advanced filters
- Statistics dashboard

**Files**:
- `app/Services/EoglasnaAlertService.php`
- `app/Http/Controllers/EoglasnaController.php` (enhance)
- `tests/Feature/EoglasnaEnhancementsTest.php`

**API Endpoints**:
```php
POST /api/eoglasna/alerts (create alert)
GET /api/eoglasna/alerts (list alerts)
POST /api/eoglasna/export/{id} (export to PDF)
GET /api/eoglasna/archive (browse archives)
GET /api/eoglasna/stats (statistics)
```

---

## Day 6: OpenAI Log Viewer (3-4 hours)

### WORKER D: Log Viewer Implementation

**Features**:
- View OpenAI API requests/responses
- Filter by date, model, status
- Cost tracking
- Performance metrics
- Error analysis

**Files**:
- `app/Http/Livewire/OpenAILogViewer.php` (enhance existing)
- `app/Services/OpenAILogService.php`
- `tests/Feature/Livewire/OpenAILogViewerTest.php` (enhance)

**Enhancements**:
```php
// Add filtering
public function filterByModel($model)
public function filterByDateRange($start, $end)
public function filterByStatus($status)

// Add metrics
public function getCostMetrics()
public function getPerformanceMetrics()
public function getErrorRate()

// Add export
public function exportLogs()
```

---

## Day 7: Vector Store Manager (3-4 hours)

### WORKER D: Vector Store Management

**Features**:
- View vector stores (laws, decisions, cases, textract)
- Browse stored vectors
- Search by similarity
- Re-index documents
- Delete vectors
- Statistics (count, size, last updated)

**Files**:
- `app/Http/Livewire/VectorStoreManager.php` (enhance existing)
- `app/Services/VectorStoreManagementService.php`
- `tests/Feature/Livewire/VectorStoreManagerTest.php` (enhance)

**UI Sections**:
- Store selection dropdown
- Document list (paginated)
- Search by text/doc_id
- Bulk actions (re-index, delete)
- Statistics cards

---

## Sprint 7 Deliverables

### Features Completed:

1. ✅ Decision Discovery Dashboard (full CRUD + UI)
2. ✅ Collaboration Features (sharing, comments, activity)
3. ✅ Eoglasna Monitoring Enhancements (alerts, export, stats)
4. ✅ OpenAI Log Viewer Enhancements (filtering, metrics, export)
5. ✅ Vector Store Manager Enhancements (browse, search, re-index)

### Files Created/Enhanced:

- 15+ new/enhanced controllers
- 10+ Livewire components
- 20+ test files
- 3,000+ lines of code

### Acceptance Criteria:

- [ ] All 5 features implemented
- [ ] All tests passing
- [ ] UI responsive and polished
- [ ] Documentation updated

---

# SPRINT 8: Browser Testing with Laravel Dusk
## Automated Browser Testing for All Features

**Duration**: 4-5 days (with 2-3 workers in parallel)
**Priority**: ⭐⭐ HIGH
**Goal**: Comprehensive browser test coverage using Laravel Dusk

---

## Sprint 8 Overview

### Setup (Day 1 Morning, 2 hours)

**Install Dusk**:
```bash
composer require --dev laravel/dusk
php artisan dusk:install
```

**Configure**:
```php
// tests/DuskTestCase.php
protected function driver(): RemoteWebDriver
{
    return RemoteWebDriver::create(
        'http://localhost:9515',
        DesiredCapabilities::chrome()
    );
}
```

**Start ChromeDriver**:
```bash
./vendor/laravel/dusk/bin/chromedriver-linux &
```

---

## Test Network: 8 Test Suites, 40+ Scenarios

### Day 1 Afternoon: Legal Playground (4-6 hours)

**WORKER A**: `tests/Browser/LegalPlaygroundTest.php`

**Scenarios** (8 tests, ~400 lines):

1. **test_user_can_access_legal_playground**
   ```php
   public function test_user_can_access_legal_playground(): void
   {
       $this->browse(function (Browser $browser) {
           $browser->visit('/playground')
                   ->assertSee('Legal Playground')
                   ->assertSee('Evidence Analysis')
                   ->assertSee('Misconduct Detection');
       });
   }
   ```

2. **test_evidence_analysis_workflow**
   ```php
   public function test_evidence_analysis_workflow(): void
   {
       $this->browse(function (Browser $browser) {
           $case = LegalCase::factory()->create();

           $browser->visit('/playground')
                   // Select Evidence Analysis tab
                   ->click('@evidence-tab')
                   ->assertSee('Upload Evidence')

                   // Select case
                   ->select('case_id', $case->id)

                   // Enter evidence text
                   ->type('evidence_text', 'Test evidence about illegal search')

                   // Submit
                   ->press('Analyze Evidence')

                   // Wait for results
                   ->waitForText('Analysis Results', 10)

                   // Verify results displayed
                   ->assertSee('Summary')
                   ->assertSee('Key Points')
                   ->assertSee('Legal Basis')

                   // Check export button works
                   ->press('Export PDF')
                   ->pause(2000); // Wait for download
       });
   }
   ```

3. **test_misconduct_detection_workflow**
   ```php
   public function test_misconduct_detection_workflow(): void
   {
       $this->browse(function (Browser $browser) {
           $browser->visit('/playground')
                   ->click('@misconduct-tab')
                   ->type('prosecutor_evidence', 'Prosecutor withheld exculpatory evidence')
                   ->press('Detect Misconduct')
                   ->waitForText('Misconduct Found', 10)
                   ->assertSee('Brady Violation')
                   ->assertSee('Severity: High');
       });
   }
   ```

4. **test_topic_analysis_workflow**
5. **test_form_validation_errors**
6. **test_loading_states_display**
7. **test_error_messages_display**
8. **test_export_functionality**

**Acceptance Criteria**:
- [ ] 8 tests written and passing
- [ ] All playground modules tested
- [ ] Form validation tested
- [ ] Export functionality tested

---

### Day 2: Graph Viewer & Textract (6-8 hours)

**WORKER A**: `tests/Browser/GraphViewerTest.php` (4 tests)

**Scenarios**:

1. **test_graph_viewer_loads_and_displays_nodes**
   ```php
   public function test_graph_viewer_loads_and_displays_nodes(): void
   {
       // Create interconnected data
       $law = Law::factory()->create(['title' => 'Test Law']);
       $decision = CourtDecision::factory()->create(['title' => 'Test Decision']);

       // Sync to graph
       app(GraphRagOrchestrator::class)->syncLaw($law->doc_id);
       app(GraphRagOrchestrator::class)->syncDecision($decision->id);

       $this->browse(function (Browser $browser) use ($law) {
           $browser->visit('/graph')
                   ->assertSee('Graph Viewer')

                   // Search for law
                   ->type('search', 'Test Law')
                   ->press('Search')

                   // Wait for graph to render
                   ->waitFor('#graph-canvas', 5)

                   // Verify nodes displayed (check canvas has content)
                   ->assertPresent('#graph-canvas')

                   // Click on node
                   ->click('#node-' . $law->doc_id)

                   // Verify details panel shows
                   ->waitForText($law->title, 5)
                   ->assertSee('Relationships');
       });
   }
   ```

2. **test_graph_viewer_displays_relationships**
3. **test_graph_query_execution**
4. **test_graph_export_functionality**

---

**WORKER B**: `tests/Browser/TextractManagerTest.php` (4 tests)

**Scenarios**:

1. **test_textract_manager_pdf_upload**
   ```php
   public function test_textract_manager_pdf_upload(): void
   {
       $this->browse(function (Browser $browser) {
           $browser->visit('/textract')
                   ->assertSee('Textract Manager')

                   // Upload PDF
                   ->attach('pdf_file', __DIR__ . '/fixtures/test.pdf')

                   // Select Google Drive folder (or mock)
                   ->select('folder_id', 'test-folder')

                   // Submit
                   ->press('Upload and Process')

                   // Wait for upload
                   ->waitForText('Upload Successful', 10)

                   // Verify job queued
                   ->assertSee('Processing...')

                   // Simulate job completion (fast-forward queue)
                   ->pause(2000)

                   // Refresh
                   ->press('Refresh')

                   // Verify processed
                   ->waitForText('Completed', 10);
       });
   }
   ```

2. **test_textract_batch_processing**
3. **test_textract_searchable_pdf_generation**
4. **test_textract_error_handling**

---

### Day 3: Timeline & Search (6-8 hours)

**WORKER A**: `tests/Browser/TimelineTest.php` (4 tests)

**Scenarios**:

1. **test_timeline_displays_case_events**
   ```php
   public function test_timeline_displays_case_events(): void
   {
       $case = LegalCase::factory()->create();

       // Create events
       CaseDocument::factory()->count(3)->create([
           'case_id' => $case->id,
           'created_at' => now()->subDays(1),
       ]);

       $this->browse(function (Browser $browser) use ($case) {
           $browser->visit("/timeline/{$case->id}")
                   ->assertSee('Case Timeline')

                   // Verify events displayed
                   ->waitFor('.timeline-event', 5)
                   ->assertPresent('.timeline-event')

                   // Count events
                   ->assertSeeIn('.event-count', '3')

                   // Test filtering
                   ->select('filter_type', 'documents')
                   ->press('Apply Filter')

                   ->waitFor('.timeline-event', 5)
                   ->assertPresent('.timeline-event');
       });
   }
   ```

2. **test_timeline_date_navigation**
3. **test_timeline_event_details**
4. **test_timeline_export**

---

**WORKER B**: `tests/Browser/SearchTest.php` (5 tests)

**Scenarios**:

1. **test_search_interface_basic_search**
   ```php
   public function test_search_interface_basic_search(): void
   {
       // Create searchable content
       Law::factory()->create([
           'title' => 'Criminal Code',
           'content' => 'This law defines criminal liability',
       ]);

       $this->browse(function (Browser $browser) {
           $browser->visit('/search')
                   ->assertSee('Search')

                   // Enter search query
                   ->type('query', 'criminal liability')

                   // Select corpus
                   ->check('corpora[]', 'laws')

                   // Submit search
                   ->press('Search')

                   // Wait for results
                   ->waitForText('Results', 10)

                   // Verify results displayed
                   ->assertSee('Criminal Code')
                   ->assertSee('1 result');
       });
   }
   ```

2. **test_search_with_filters**
3. **test_search_pagination**
4. **test_search_result_preview**
5. **test_multi_corpus_search**

---

### Day 4: Decision Discovery & Collaboration (6-8 hours)

**WORKER A**: `tests/Browser/DecisionDiscoveryTest.php` (4 tests)

**Scenarios**:

1. **test_decision_discovery_search**
   ```php
   public function test_decision_discovery_search(): void
   {
       $this->browse(function (Browser $browser) {
           $browser->visit('/decisions/discover')
                   ->assertSee('Decision Discovery')

                   // Enter keywords
                   ->type('keywords', 'criminal procedure')

                   // Select court
                   ->select('court', 'Vrhovni sud')

                   // Search
                   ->press('Search Odluke.hr')

                   // Wait for results
                   ->waitForText('Found', 10)

                   // Verify results
                   ->assertPresent('.decision-result');
       });
   }
   ```

2. **test_decision_preview**
3. **test_batch_decision_ingestion**
4. **test_discovery_statistics**

---

**WORKER B**: `tests/Browser/CollaborationTest.php` (4 tests)

**Scenarios**:

1. **test_case_sharing**
   ```php
   public function test_case_sharing(): void
   {
       $user1 = User::factory()->create(['email' => 'user1@test.com']);
       $user2 = User::factory()->create(['email' => 'user2@test.com']);
       $case = LegalCase::factory()->create(['user_id' => $user1->id]);

       $this->browse(function (Browser $browser1, Browser $browser2) use ($user1, $user2, $case) {
           // User 1 shares case
           $browser1->loginAs($user1)
                    ->visit("/cases/{$case->id}")
                    ->press('Share')
                    ->waitFor('#share-modal')
                    ->type('email', 'user2@test.com')
                    ->press('Send Invitation')
                    ->waitForText('Invitation Sent');

           // User 2 sees shared case
           $browser2->loginAs($user2)
                    ->visit('/shared-cases')
                    ->assertSee($case->case_number);
       });
   }
   ```

2. **test_commenting_on_cases**
3. **test_activity_feed_updates**
4. **test_notifications**

---

### Day 5: Eoglasna & Logs (4-6 hours)

**WORKER A**: `tests/Browser/EoglasnaMonitoringTest.php` (3 tests)

**Scenarios**:

1. **test_eoglasna_monitoring_dashboard**
2. **test_keyword_alerts**
3. **test_court_notice_export**

---

**WORKER B**: `tests/Browser/OpenAILogViewerTest.php` (3 tests)

**Scenarios**:

1. **test_log_viewer_displays_requests**
2. **test_log_filtering**
3. **test_cost_metrics_display**

---

**WORKER B**: `tests/Browser/VectorStoreManagerTest.php` (3 tests)

**Scenarios**:

1. **test_vector_store_browsing**
2. **test_vector_search**
3. **test_re_indexing**

---

## Sprint 8 Deliverables

### Test Files Created:

1. `tests/Browser/LegalPlaygroundTest.php` (8 tests)
2. `tests/Browser/GraphViewerTest.php` (4 tests)
3. `tests/Browser/TextractManagerTest.php` (4 tests)
4. `tests/Browser/TimelineTest.php` (4 tests)
5. `tests/Browser/SearchTest.php` (5 tests)
6. `tests/Browser/DecisionDiscoveryTest.php` (4 tests)
7. `tests/Browser/CollaborationTest.php` (4 tests)
8. `tests/Browser/EoglasnaMonitoringTest.php` (3 tests)
9. `tests/Browser/OpenAILogViewerTest.php` (3 tests)
10. `tests/Browser/VectorStoreManagerTest.php` (3 tests)

**Total**: 42 browser tests, ~3,000 lines

### Coverage:

- ✅ Visual verification (UI renders correctly)
- ✅ User experience (navigation, forms, interactions)
- ✅ Integration points (Livewire, file uploads, exports)
- ✅ Error states and validation
- ✅ Loading states
- ✅ Responsive design (test multiple screen sizes)

### Acceptance Criteria:

- [ ] All 42 tests written
- [ ] All tests passing
- [ ] Screenshots captured on failures
- [ ] Test execution documented
- [ ] CI/CD integration ready

---

## Campaign Summary

### Total Effort Across 4 Sprints:

| Sprint | Tests | Lines | Days | Workers |
|--------|-------|-------|------|---------|
| Sprint 5 | 20 | 2,000 | 3 | 4 |
| Sprint 6 | 22 | 2,000 | 3-4 | 4 |
| Sprint 7 | 20 | 3,000 | 5-7 | 3-4 |
| Sprint 8 | 42 | 3,000 | 4-5 | 2-3 |
| **Total** | **104** | **10,000** | **15-19** | **4-6** |

### Timeline:

**With Full Parallelism** (6 workers):
- Sprint 5-6 can run in parallel (Days 1-7)
- Sprint 7 depends on Sprint 5 complete (Days 8-14)
- Sprint 8 depends on Sprint 7 complete (Days 15-19)
- **Total**: ~19 days (3 weeks)

**With Limited Parallelism** (4 workers):
- Sprint 5 (Days 1-3)
- Sprint 6 (Days 4-7)
- Sprint 7 (Days 8-14)
- Sprint 8 (Days 15-19)
- **Total**: ~19 days (3 weeks)

**With Sequential Execution** (2 workers):
- ~35-40 days (6-8 weeks)

---

## Success Criteria

### Sprint 5:
- [ ] 20 integration tests passing
- [ ] All refactored services tested
- [ ] Multi-service interactions verified

### Sprint 6:
- [ ] 22 E2E + external API tests passing
- [ ] All workflows tested end-to-end
- [ ] Performance baselines established

### Sprint 7:
- [ ] 5 UX features complete
- [ ] All features tested
- [ ] UI responsive and polished

### Sprint 8:
- [ ] 42 browser tests passing
- [ ] All critical paths covered
- [ ] Visual regression prevented

---

## Risk Management

| Risk | Mitigation |
|------|------------|
| Dusk tests flaky | Use ->waitFor(), add retries, run headless |
| External APIs fail | Mock when possible, cache responses |
| Tests take too long | Parallelize, use DB transactions, optimize |
| Workers blocked | Clear dependencies, provide fixtures |
| Features incomplete | MVP first, iterate |

---

## Next Steps

1. ✅ Review this plan with team
2. 📋 Assign workers to sprints
3. 📋 Set up Dusk environment
4. 🚀 Start Sprint 5 (integration tests)
5. 📊 Track progress daily
6. 🎉 Celebrate when complete!

---

**Status**: 📋 READY TO EXECUTE
**Priority**: ⭐⭐⭐ CRITICAL for production hardening

# Integration Test Strategy - AI Legal War Machine

## Executive Summary

This document outlines a comprehensive strategy to fix the Integration test suite without cutting corners. The current suite has 278 failing tests due to unmocked external dependencies, broken expectations, and infrastructure issues.

---

## Root Cause Analysis

### Primary Issues

1. **Unmocked External API Calls (CRITICAL)**
   - Tests make real OpenAI API calls (chat, embeddings)
   - 60-second timeouts per call when API key is invalid
   - Multiple hanging HTTP connections consume 7.5GB+ RAM
   - Tests hang indefinitely during setUp()

2. **Broken Test Expectations**
   - Tests expect `LegalMemo` model that doesn't exist
   - Service returns arrays, tests assert against models
   - Incorrect mock method names (mocking `findSimilarCases()` instead of `search()`)

3. **Heavy Database Operations**
   - Vector embedding queries load large binary data
   - No proper test data isolation
   - `RefreshDatabase` runs `migrate:fresh` causing PostgreSQL overload

4. **Missing Test Infrastructure**
   - No shared mock factories
   - No test doubles for external services
   - No integration test base class with common setup

---

## Strategy: 4-Layer Test Architecture

### Layer 1: Test Doubles (Foundation)

**Goal:** Create reusable mock implementations for all external dependencies

**Implementation:**

```php
// tests/Doubles/FakeOpenAIService.php
class FakeOpenAIService extends OpenAIService
{
    private array $chatResponses = [];
    private array $embeddingResponses = [];

    public function setChatResponse(array $response): void
    {
        $this->chatResponses[] = $response;
    }

    public function chat(array $messages, array $options = []): array
    {
        if (empty($this->chatResponses)) {
            return $this->defaultChatResponse();
        }
        return array_shift($this->chatResponses);
    }

    public function createEmbedding(string $text): array
    {
        return array_fill(0, 1536, 0.1); // Standard embedding size
    }

    private function defaultChatResponse(): array
    {
        return [
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'structured_facts' => [],
                        'legal_issues' => [],
                        'parties' => [],
                    ])
                ]
            ]],
            'usage' => ['total_tokens' => 100],
        ];
    }
}
```

**Benefits:**
- Predictable, fast responses
- No external dependencies
- Can simulate edge cases (errors, timeouts, rate limits)
- Reusable across all test suites

---

### Layer 2: Integration Test Base Class

**Goal:** Provide common infrastructure for all integration tests

**Implementation:**

```php
// tests/Integration/IntegrationTestCase.php
abstract class IntegrationTestCase extends TestCase
{
    use DatabaseTransactions;  // Roll back after each test

    protected FakeOpenAIService $fakeOpenAI;
    protected bool $mockExternalServices = true;

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->mockExternalServices) {
            $this->setupExternalServiceMocks();
        }

        $this->setupTestDatabase();
    }

    protected function setupExternalServiceMocks(): void
    {
        // Mock OpenAI Service
        $this->fakeOpenAI = new FakeOpenAIService();
        $this->app->instance(OpenAIService::class, $this->fakeOpenAI);

        // Mock other external services as needed
        $this->mockVectorSearch();
        $this->mockS3Storage();
    }

    protected function mockVectorSearch(): void
    {
        // Disable actual vector search, return empty results
        $this->mock(DecisionSearchService::class, function ($mock) {
            $mock->shouldReceive('search')
                ->andReturn([
                    'success' => true,
                    'data' => [],
                    'count' => 0,
                ]);
        });
    }

    protected function mockS3Storage(): void
    {
        Storage::fake('s3');
    }

    protected function setupTestDatabase(): void
    {
        // Run migrations only once per test suite
        if (!static::$migrated) {
            Artisan::call('migrate:fresh');
            static::$migrated = true;
        }
    }

    protected static bool $migrated = false;
}
```

**Benefits:**
- All external dependencies mocked by default
- Consistent test environment
- Database properly isolated
- Easy to override for specific test needs

---

### Layer 3: Test Data Builders

**Goal:** Create realistic test data without boilerplate

**Implementation:**

```php
// tests/Builders/LegalMemoTestBuilder.php
class LegalMemoTestBuilder
{
    private array $factPattern = [];
    private array $precedents = [];
    private User $user;

    public function __construct()
    {
        $this->factPattern = $this->defaultFactPattern();
    }

    public function withUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function withContractIssue(): self
    {
        $this->factPattern['legal_issues'] = [[
            'issue' => 'Whether defendant breached the contract',
            'area_of_law' => 'contract',
            'elements' => ['Valid contract', 'Breach', 'Damages'],
        ]];
        return $this;
    }

    public function withPrecedents(array $precedents): self
    {
        $this->precedents = $precedents;
        return $this;
    }

    public function build(): array
    {
        return [
            'fact_pattern' => LegalFactPattern::factory()->create([
                'user_id' => $this->user->id,
                'structured_facts' => $this->factPattern,
            ]),
            'precedents' => $this->precedents,
        ];
    }

    private function defaultFactPattern(): array
    {
        return [
            'legal_issues' => [],
            'parties' => [],
            'events' => [],
            'evidence' => [],
            'summary' => 'Test case',
        ];
    }
}
```

**Benefits:**
- Readable test code
- Reusable data patterns
- Easy to create complex scenarios
- Reduces test maintenance burden

---

### Layer 4: Fixed Integration Tests

**Goal:** Rewrite tests with proper mocks and expectations

**Implementation:**

```php
// tests/Integration/AutomatedLegalMemoGeneratorTest.php (FIXED)
class AutomatedLegalMemoGeneratorTest extends IntegrationTestCase
{
    protected AutomatedLegalMemoGenerator $generator;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->generator = app(AutomatedLegalMemoGenerator::class);
    }

    /** @test */
    public function it_generates_complete_legal_memo()
    {
        // Setup test data using builder
        $data = (new LegalMemoTestBuilder())
            ->withUser($this->user)
            ->withContractIssue()
            ->build();

        // Configure fake OpenAI responses
        $this->fakeOpenAI->setChatResponse([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'analysis' => 'Legal analysis here',
                        'conclusion' => 'Likely to succeed',
                    ])
                ]
            ]]
        ]);

        // Execute
        $memo = $this->generator->generateMemo($data['fact_pattern']->id);

        // Assert against actual return type (array, not model)
        $this->assertIsArray($memo);
        $this->assertArrayHasKey('header', $memo);
        $this->assertArrayHasKey('issue', $memo);
        $this->assertArrayHasKey('brief_answer', $memo);
        $this->assertArrayHasKey('analysis', $memo);
        $this->assertArrayHasKey('conclusion', $memo);

        // Verify structure
        $this->assertIsArray($memo['header']);
        $this->assertArrayHasKey('to', $memo['header']);
        $this->assertArrayHasKey('date', $memo['header']);
    }
}
```

**Benefits:**
- Tests run in <1 second each
- No external dependencies
- Clear, maintainable test code
- Accurate assertions matching actual behavior

---

## Implementation Plan

### Phase 1: Infrastructure (Priority: CRITICAL)

**Tasks:**
1. Create `FakeOpenAIService` test double
2. Create `IntegrationTestCase` base class
3. Create test data builders
4. Add shared mock helpers to `TestCase`

**Deliverables:**
- `tests/Doubles/FakeOpenAIService.php`
- `tests/Integration/IntegrationTestCase.php`
- `tests/Builders/LegalMemoTestBuilder.php`
- Updated `tests/TestCase.php`

**Time Estimate:** 1-2 hours
**Dependencies:** None
**Risk:** Low

---

### Phase 2: Fix AutomatedLegalMemoGeneratorTest (Priority: HIGH)

**Tasks:**
1. Extend `IntegrationTestCase`
2. Fix all mock method names
3. Update assertions to match array return type
4. Remove unmocked OpenAI calls
5. Verify all 14 tests pass

**Deliverables:**
- Fixed `tests/Integration/AutomatedLegalMemoGeneratorTest.php`
- All 14 tests passing in <10 seconds total

**Time Estimate:** 2-3 hours
**Dependencies:** Phase 1
**Risk:** Medium (need to understand actual service behavior)

---

### Phase 3: Fix Remaining Integration Tests (Priority: HIGH)

**Tasks:**
1. Analyze each failing test file
2. Apply same pattern:
   - Extend IntegrationTestCase
   - Mock external dependencies
   - Fix assertions
   - Verify passing

**Test Files (in priority order):**
1. CaseIntakeIntegrationTest.php
2. ChronologyBuilderTest.php
3. ContextAssemblyIntegrationTest.php
4. DiscoveryRequestGeneratorTest.php
5. DocumentQualityE2ETest.php
6. EvidenceStrategyAnalyzerTest.php
7. FeedbackIncorporationPipelineTest.php
8. FactDrivenMultiAgentAnalyzerTest.php
9. RecursiveDocumentWritingIntegrationTest.php
10. Neo4jRetryQueueTest.php (already has correct mocks)

**Deliverables:**
- All 10 test files fixed
- All integration tests passing

**Time Estimate:** 6-8 hours (parallelizable)
**Dependencies:** Phase 1, Phase 2
**Risk:** Medium (each test may have unique issues)

---

### Phase 4: Database Optimization (Priority: MEDIUM)

**Tasks:**
1. Use `DatabaseTransactions` instead of `RefreshDatabase`
2. Run migrations once per test suite (static flag)
3. Add database seeders for common test data
4. Optimize factory definitions

**Deliverables:**
- Optimized test database strategy
- 10x faster test execution

**Time Estimate:** 2-3 hours
**Dependencies:** Phase 3
**Risk:** Low

---

### Phase 5: Verification & Documentation (Priority: MEDIUM)

**Tasks:**
1. Run full Integration suite
2. Verify all 328 tests pass
3. Check memory usage (<500MB target)
4. Check execution time (<2 minutes target)
5. Document test patterns in README
6. Create PR with all changes

**Deliverables:**
- 328/328 Integration tests passing
- Updated documentation
- Pull request ready for review

**Time Estimate:** 1-2 hours
**Dependencies:** Phase 4
**Risk:** Low

---

## Success Metrics

### Before
- ❌ 278/328 tests failing
- ❌ 7.5GB+ RAM consumption
- ❌ Tests hang indefinitely
- ❌ PostgreSQL crashes

### After
- ✅ 328/328 tests passing
- ✅ <500MB RAM consumption
- ✅ <2 minute total execution time
- ✅ Stable database

---

## Risk Mitigation

### Risk: Service behavior doesn't match test expectations
**Mitigation:** Run production code manually to verify actual behavior before writing tests

### Risk: Mocks hide real bugs
**Mitigation:**
- Keep integration tests that test real interactions (mark with `@group external`)
- Add separate E2E test suite for full stack testing
- Document what each mock represents

### Risk: Tests become brittle
**Mitigation:**
- Test behavior, not implementation
- Use builders to isolate test data creation
- Keep mocks focused on external boundaries only

### Risk: Time estimates too optimistic
**Mitigation:**
- Start with Phase 1 and 2, validate approach
- Parallelize Phase 3 work across multiple agents
- Build slack time into estimates

---

## Long-Term Improvements

1. **Contract Testing**
   - Add Pact/contract tests for external APIs
   - Verify mocks match real API behavior

2. **Performance Testing**
   - Add memory profiling to CI/CD
   - Track test execution time trends
   - Alert on regressions

3. **Test Organization**
   - Separate unit, integration, and E2E tests
   - Use PHPUnit groups for selective execution
   - Add parallel test execution

4. **Service Architecture**
   - Create interfaces for all external services
   - Use dependency injection consistently
   - Consider service layer refactoring

---

## Conclusion

This strategy provides a comprehensive, maintainable solution to fix the Integration test suite. By building proper test infrastructure first (Phases 1-2), we can systematically fix all tests (Phase 3) and optimize performance (Phase 4) without cutting corners.

**Key Principles:**
- Mock external dependencies at boundaries
- Test actual behavior, not assumptions
- Build reusable infrastructure
- Optimize for maintainability

**Total Estimated Time:** 12-18 hours
**Expected Outcome:** 328/328 tests passing, <2 minute execution, <500MB RAM

# AutomatedLegalMemoGenerator Memory & Performance Analysis

## Executive Summary

The AutomatedLegalMemoGenerator integration tests are **consuming 7.5GB RAM and hanging** due to:

1. **Unmocked External API Calls** - Making real OpenAI API calls
2. **Broken Test Expectations** - Test expects LegalMemo model that doesn't exist
3. **Incorrect Mock Configuration** - Mocks wrong methods
4. **Heavy Database Operations** - Loading large embedding vectors

---

## Critical Issues

### 1. Real OpenAI API Calls (CRITICAL)

**Location:** Multiple services make actual HTTP calls to OpenAI API:

#### FactPatternExtractor
- **File:** `app/Services/LegalReasoning/FactPatternExtractor.php:118-132`
- **Issue:** Makes real `gpt-4o` chat API call with large prompt
- **Impact:** Each call takes 2-5 seconds, costs money, and may timeout/hang if API key is invalid

```php
$response = $this->openAI->chat([
    'model' => 'gpt-4o',
    'messages' => [...],  // Large prompt
    'temperature' => 0.1,
    'response_format' => ['type' => 'json_object'],
]);
```

#### DecisionSearchService
- **File:** `app/Services/DecisionSearchService.php:135, 193`
- **Issue:** Calls `generateEmbedding()` which makes real OpenAI embeddings API call
- **Impact:** Each embedding generation is an HTTP call

```php
$queryVector = $this->generateEmbeddingWithErrorHandling($query);
// -> calls BaseSearchService.generateEmbedding()
// -> calls OpenAIService.createEmbedding() 
// -> makes real HTTP POST to api.openai.com/v1/embeddings
```

#### BaseSearchService
- **File:** `app/Services/BaseSearchService.php:26-40`
- **Issue:** Actually calls OpenAI API

```php
protected function generateEmbedding(string $text): ?array
{
    $embedding = $this->openAI->createEmbedding($text);
    return !empty($embedding) ? $embedding : null;
}
```

---

### 2. Broken Test Expectations (CRITICAL)

**File:** `tests/Integration/AutomatedLegalMemoGeneratorTest.php:99`

**Issue:** Test expects `LegalMemo` model, but service returns an array:

```php
// Test expects:
$this->assertInstanceOf(LegalMemo::class, $memo);

// But service returns (line 36-68 in AutomatedLegalMemoGenerator.php):
public function generateMemo(string $factPatternId, array $options = []): array
{
    return $memo;  // Returns array, NOT LegalMemo model!
}
```

**Verification:** `LegalMemo` model doesn't exist in the codebase.

---

### 3. Incorrect Mock Configuration (CRITICAL)

**File:** `tests/Integration/AutomatedLegalMemoGeneratorTest.php:39-51`

**Issue:** Test mocks `findSimilarCases()` method, but service calls `search()`:

```php
// Test mocks:
$this->mock(DecisionSearchService::class, function ($mock) {
    $mock->shouldReceive('findSimilarCases')  // ❌ Wrong method!
        ->andReturn([...]);
});

// But service actually calls:
$results = $this->decisionSearch->search($query, [  // ✓ Actual method
    'limit' => 5,
    'use_vector' => true,
]);
```

**Result:** Mock is never used, real service is called, which makes real API calls.

---

### 4. Memory-Intensive Operations

#### Vector Search Database Queries
**File:** `app/Services/DecisionSearchService.php:226-282`

**Issue:** Loads large embedding vectors from database:

```php
$queryBuilder = DB::table('court_decision_documents')
    ->select([
        'court_decision_documents.embedding_vector',  // Large binary data
        // ... many other columns
    ])
    ->limit($limit);
```

**Impact:**
- Each embedding vector is ~1536 floats = ~6KB per row
- Loading 100 rows = ~600KB
- Multiple tests running = several MB quickly
- Not the main issue, but contributes to memory pressure

#### JSON Decoding Large Responses
**File:** `app/Services/DecisionSearchService.php:264-272`

```php
$results = $queryBuilder->get()->map(function ($decision) use ($queryVector, $hasPgVector) {
    if (!$hasPgVector) {
        $decisionVector = json_decode($decision->embedding_vector ?? '[]', true);
        $decision->similarity = $this->cosineSimilarity($queryVector, $decisionVector);
    }
    $decision->metadata = json_decode($decision->metadata ?? '{}', true);
    return $decision;
});
```

---

## Why 7.5GB RAM?

### Root Cause Analysis

1. **API Call Timeouts**
   - If `OPENAI_API_KEY` is not configured or invalid
   - HTTP client waits for timeout (60 seconds default)
   - Multiple parallel tests = multiple hanging HTTP connections
   - Each connection holds memory

2. **HTTP Client Retry Logic**
   - OpenAIService has retry with exponential backoff (line 90-121)
   - Retries up to N times with increasing delays
   - Failed requests accumulate in memory

3. **Database Connection Pool**
   - Each test creates database connections
   - Vector queries with large result sets
   - Connections not properly cleaned up

4. **Laravel Container Resolution**
   - Each test instantiates full service stack
   - Services auto-resolve dependencies
   - Multiple instances of OpenAIService, DecisionSearchService, etc.
   - Memory compounds across tests

---

## Dependencies Loaded

```
AutomatedLegalMemoGenerator
├── FactPatternExtractor
│   └── OpenAIService ────────────> EXTERNAL API CALL (gpt-4o)
│       └── CircuitBreaker
│       └── Http Client (with retry logic)
├── DecisionSearchService
│   ├── OpenAIService ────────────> EXTERNAL API CALL (embeddings)
│   ├── DecisionSearchTool (MCP)
│   ├── DecisionGetTool (MCP)
│   └── BaseSearchService
│       └── OpenAIService
└── OpenAIService (injected but never used!)
```

---

## API Calls Made Per Test

For **each test** in `AutomatedLegalMemoGeneratorTest`:

1. ✅ Creates User (database)
2. ✅ Creates LegalFactPattern (database)
3. ❌ Calls `FactPatternExtractor.extractWithLLM()` → **OpenAI API** (if no cache)
4. ❌ Calls `DecisionSearchService.vectorSearch()` → **OpenAI API** (for embeddings)
5. ❌ Executes vector search query → **Database** (loads embedding vectors)
6. ❌ Test fails because returned data structure doesn't match expectations

**Total per test:**
- 2+ OpenAI API calls (if not cached)
- Multiple database queries with large result sets
- Test failure prevents cleanup

---

## Recommended Fixes

### Immediate (Critical)

1. **Mock OpenAIService in all integration tests**
```php
$this->mock(OpenAIService::class, function ($mock) {
    $mock->shouldReceive('chat')->andReturn([
        'choices' => [['message' => ['content' => '{"test": "data"}']]]
    ]);
    $mock->shouldReceive('createEmbedding')->andReturn(
        array_fill(0, 1536, 0.1)  // Mock embedding vector
    );
});
```

2. **Fix mock method name**
```php
$mock->shouldReceive('search')  // ✓ Correct
    ->andReturn(['success' => true, 'data' => [...], 'count' => 1]);
```

3. **Fix return type expectation**
```php
// Change from:
$this->assertInstanceOf(LegalMemo::class, $memo);

// To:
$this->assertIsArray($memo);
$this->assertArrayHasKey('header', $memo);
$this->assertArrayHasKey('issue', $memo);
```

### Short Term

1. **Create test-specific service mocks**
2. **Add proper test isolation**
3. **Use database transactions for rollback**
4. **Add memory limit assertions**

### Long Term

1. **Create interface for OpenAIService**
2. **Use dependency injection properly**
3. **Add integration test configuration**
4. **Implement proper test doubles**

---

## Test Execution Flow (Current vs Fixed)

### Current (Broken)
```
Test starts
├── Create AutomatedLegalMemoGenerator with real services
├── Call generateMemo()
│   ├── FactPatternExtractor.extract() 
│   │   └── OpenAI.chat() ────────> ❌ HANGS/TIMES OUT
│   ├── DecisionSearch.search()
│   │   └── OpenAI.createEmbedding() ────────> ❌ HANGS/TIMES OUT
│   └── Database vector search ────────> ⚠️ SLOW
├── Test assertion fails ────────> ❌ Wrong type
└── Memory not released ────────> 💥 7.5GB+
```

### Fixed
```
Test starts
├── Mock OpenAIService
├── Mock DecisionSearchService
├── Create AutomatedLegalMemoGenerator with mocks
├── Call generateMemo()
│   ├── Uses mocked responses ────────> ✓ FAST
│   └── No database calls ────────> ✓ FAST
├── Test assertion passes ────────> ✓ Correct type
└── Memory released ────────> ✓ <10MB
```

---

## Files Requiring Changes

1. `tests/Integration/AutomatedLegalMemoGeneratorTest.php` - Fix all test mocks
2. `app/Services/Documents/AutomatedLegalMemoGenerator.php` - Consider return type
3. `tests/TestCase.php` - Add shared mock helpers
4. `phpunit.xml` - Configure test memory limits

---

## Conclusion

The 7.5GB memory consumption is caused by:
- **Unmocked external API calls** that hang or timeout
- **Broken test expectations** that don't match actual service behavior
- **Incorrect mocks** that aren't being used
- **Heavy database operations** loading large vectors

The fix requires properly mocking all external dependencies, especially OpenAIService.

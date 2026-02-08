# SPRINTS 3 & 4: Parallel Task Breakdown
## Remaining 50% of Refactoring Campaign

**Date**: 2025-11-05
**Status**: Ready to Execute
**Sprints**: Sprint 3 (OpenAIService) + Sprint 4 (AutonomousResearchAgent)
**Total Effort**: 50-60 hours (can be done in parallel by 4 workers)
**Timeline**: 1.5-2 weeks with parallel execution

---

## Campaign Progress Overview

### ✅ COMPLETED (50%):

**Sprint 1: Graph Services Refactoring** - 100% ✅
- GraphRagOrchestrator + 4 sync services
- ~2,700 lines of duplicates removed
- All tests passing

**Sprint 2: Search Services Refactoring** - 100% ✅
- SearchOrchestrator + 7 specialized services
- UnifiedSearchService internally refactored
- 106 tests passing

### 🔄 REMAINING (50%):

**Sprint 3: OpenAIService Refactoring** - 0%
- Target: 1,010 lines → 4 services
- Files: `app/Services/OpenAIService.php`

**Sprint 4: AutonomousResearchAgent Refactoring** - 0%
- Target: 1,146 lines → 5 services
- Files: `app/Agents/AutonomousResearchAgent.php`

---

## Parallelism Strategy

### Worker Allocation:

```
SPRINT 3 (OpenAIService)
├── Worker A: OpenAIChatService + Tests
├── Worker B: OpenAIEmbeddingService + Tests
├── Worker C: OpenAIAnalysisService + Tests
└── Worker D: OpenAICacheService + Tests

SPRINT 4 (AutonomousResearchAgent)
├── Worker A: QuestionGeneratorService + Tests
├── Worker B: SearchExecutorService + Tests
├── Worker C: AnswerEvaluatorService + Tests
└── Worker D: QualityAssessorService + IterationControllerService + Tests
```

**Can run BOTH sprints in parallel** if you have 8 workers (4 per sprint).

**Sequential within each sprint**:
1. All workers do characterization tests FIRST (Phase 1)
2. Then all workers build services in parallel (Phase 2)
3. Then all workers integrate and test (Phase 3)

---

## SPRINT 3: OpenAIService Refactoring

**God Class**: `app/Services/OpenAIService.php` (1,010 lines)
**Target**: 4 specialized services + 1 orchestrator
**Interfaces Already Exist**: ✅ YES
- `app/Contracts/AI/ChatServiceInterface.php`
- `app/Contracts/AI/EmbeddingServiceInterface.php`
- `app/Contracts/AI/AnalysisServiceInterface.php`
- `app/Contracts/AI/CacheServiceInterface.php`

### Phase 1: Characterization Tests (Day 1) - ALL WORKERS TOGETHER

**Duration**: 4-6 hours (pair programming recommended)
**Workers**: All 4 workers work together on characterization tests
**Branch**: `sprint3/openai-characterization-tests`

**Files to Create**:
```
tests/Unit/Services/AI/OpenAIServiceCharacterizationTest.php (NEW)
```

**Tasks**:
1. ✅ Document all existing behavior of `OpenAIService.php`
2. ✅ Create 30+ characterization tests covering:
   - `chat()` method (basic, with options, streaming)
   - `chatStream()` method
   - `multiTurnChat()` method
   - `embeddings()` method (single, batch)
   - `batchEmbeddings()` method
   - `analyzeLegalText()` method
   - `summarizeDecision()` method
   - `extractCitations()` method
   - Cache hit/miss scenarios
   - Error handling and retries
   - Token counting
   - Cost calculation
3. ✅ Run tests against CURRENT implementation (all should pass)
4. ✅ Commit characterization tests

**Acceptance Criteria**:
- [ ] 30+ tests written
- [ ] All tests pass on current OpenAIService
- [ ] 100% coverage of public methods
- [ ] Tests frozen (DO NOT modify during refactoring)

**Commands**:
```bash
# Create branch
git checkout develop
git checkout -b sprint3/openai-characterization-tests

# Create test file
mkdir -p tests/Unit/Services/AI
touch tests/Unit/Services/AI/OpenAIServiceCharacterizationTest.php

# Run tests
./scripts/run-tests.sh --filter=OpenAIServiceCharacterizationTest

# Commit
git add tests/Unit/Services/AI/OpenAIServiceCharacterizationTest.php
git commit -m "Add characterization tests for OpenAIService (30+ tests)"
```

---

### Phase 2: Parallel Service Extraction (Days 2-5) - PARALLEL WORK

**Duration**: 12-16 hours total (3-4 hours per worker)
**Workers**: Each worker takes ONE service
**Dependencies**: Phase 1 complete

#### WORKER A: OpenAIChatService

**Branch**: `sprint3/openai-chat-service`
**Time**: 3-4 hours
**Priority**: P0 (CRITICAL - most used service, 500+ calls/day)

**Files to Create**:
```
app/Services/AI/OpenAIChatService.php (NEW, 250-300 lines)
tests/Unit/Services/AI/OpenAIChatServiceTest.php (NEW, 400-500 lines)
```

**Files to Reference** (read these):
```
app/Services/OpenAIService.php (lines 150-350 - chat methods)
app/Contracts/AI/ChatServiceInterface.php (interface to implement)
config/openai.php (API configuration)
```

**Responsibilities to Extract**:
- `chat()` - Basic chat completion
- `chatStream()` - Streaming chat completion
- `multiTurnChat()` - Multi-turn conversations
- Retry logic for chat operations
- Error handling specific to chat
- Token counting for chat

**Interface to Implement**:
```php
<?php

namespace App\Services\AI;

use App\Contracts\AI\ChatServiceInterface;
use App\Contracts\AI\CacheServiceInterface;

class OpenAIChatService implements ChatServiceInterface
{
    protected string $apiKey;
    protected string $apiUrl = 'https://api.openai.com/v1/chat/completions';
    protected int $maxRetries = 3;

    public function __construct(
        protected CacheServiceInterface $cache
    ) {
        $this->apiKey = config('openai.api_key');
    }

    public function chat(array $messages, string $model = 'gpt-4o', array $options = []): array
    {
        // Extract from OpenAIService.php lines 150-200
    }

    public function chatStream(array $messages, string $model = 'gpt-4o', callable $callback = null): void
    {
        // Extract from OpenAIService.php lines 250-300
    }

    public function multiTurnChat(array $conversationHistory, string $userMessage, array $options = []): array
    {
        // Extract from OpenAIService.php lines 320-350
    }

    protected function sendWithRetry(array $payload): array
    {
        // Extract retry logic
    }
}
```

**TDD Steps**:
1. **RED**: Write failing test for `chat()`
2. **GREEN**: Extract `chat()` from OpenAIService, make test pass
3. **REFACTOR**: Clean up, optimize
4. Repeat for `chatStream()`, `multiTurnChat()`

**Tests to Write** (20+ tests):
```
test_chat_basic_completion()
test_chat_with_system_message()
test_chat_with_temperature_option()
test_chat_with_max_tokens_option()
test_chat_caches_responses()
test_chat_uses_cache_on_hit()
test_chat_retries_on_failure()
test_chat_throws_after_max_retries()
test_chat_stream_basic()
test_chat_stream_with_callback()
test_multi_turn_chat_basic()
test_multi_turn_chat_preserves_history()
test_token_counting_accurate()
test_cost_calculation_accurate()
...
```

**Acceptance Criteria**:
- [ ] OpenAIChatService.php created (250-300 lines)
- [ ] Implements ChatServiceInterface
- [ ] 20+ unit tests written
- [ ] All tests pass
- [ ] Characterization tests still pass
- [ ] Code follows PSR-12

**Commands**:
```bash
# Create branch from characterization branch
git checkout sprint3/openai-characterization-tests
git checkout -b sprint3/openai-chat-service

# Create files
mkdir -p app/Services/AI
touch app/Services/AI/OpenAIChatService.php
touch tests/Unit/Services/AI/OpenAIChatServiceTest.php

# Run tests
./scripts/run-tests.sh --filter=OpenAIChatServiceTest

# Run characterization tests (should still pass)
./scripts/run-tests.sh --filter=OpenAIServiceCharacterizationTest

# Format code
./vendor/bin/pint app/Services/AI/OpenAIChatService.php

# Commit
git add app/Services/AI/OpenAIChatService.php tests/Unit/Services/AI/OpenAIChatServiceTest.php
git commit -m "Extract OpenAIChatService from OpenAIService (TDD GREEN)"
```

---

#### WORKER B: OpenAIEmbeddingService

**Branch**: `sprint3/openai-embedding-service`
**Time**: 3-4 hours
**Priority**: P0 (CRITICAL - vector search dependency, 200+ calls/day)

**Files to Create**:
```
app/Services/AI/OpenAIEmbeddingService.php (NEW, 200-250 lines)
tests/Unit/Services/AI/OpenAIEmbeddingServiceTest.php (NEW, 300-400 lines)
```

**Files to Reference** (read these):
```
app/Services/OpenAIService.php (lines 400-550 - embedding methods)
app/Contracts/AI/EmbeddingServiceInterface.php (interface to implement)
config/openai.php (embedding model configuration)
```

**Responsibilities to Extract**:
- `embeddings()` - Single text embedding
- `batchEmbeddings()` - Batch embedding generation
- Retry logic for embedding operations
- Error handling specific to embeddings
- Embedding cache management (24h TTL)
- Dimension validation (1536 for text-embedding-3-small)

**Interface to Implement**:
```php
<?php

namespace App\Services\AI;

use App\Contracts\AI\EmbeddingServiceInterface;
use App\Contracts\AI\CacheServiceInterface;

class OpenAIEmbeddingService implements EmbeddingServiceInterface
{
    protected string $apiKey;
    protected string $apiUrl = 'https://api.openai.com/v1/embeddings';
    protected string $model = 'text-embedding-3-small';
    protected int $dimensions = 1536;

    public function __construct(
        protected CacheServiceInterface $cache
    ) {
        $this->apiKey = config('openai.api_key');
    }

    public function embeddings(string $text, array $options = []): array
    {
        // Extract from OpenAIService.php lines 400-450
    }

    public function batchEmbeddings(array $texts, array $options = []): array
    {
        // Extract from OpenAIService.php lines 480-550
    }

    protected function sendWithRetry(array $payload): array
    {
        // Extract retry logic
    }
}
```

**TDD Steps**:
1. **RED**: Write failing test for `embeddings()`
2. **GREEN**: Extract `embeddings()` from OpenAIService, make test pass
3. **REFACTOR**: Clean up, optimize
4. Repeat for `batchEmbeddings()`

**Tests to Write** (15+ tests):
```
test_embeddings_basic()
test_embeddings_returns_correct_dimensions()
test_embeddings_caches_results()
test_embeddings_uses_cache_on_hit()
test_embeddings_retries_on_failure()
test_embeddings_throws_after_max_retries()
test_batch_embeddings_basic()
test_batch_embeddings_handles_empty_array()
test_batch_embeddings_respects_batch_size()
test_batch_embeddings_preserves_order()
test_embedding_cache_ttl_is_24_hours()
test_embedding_validation()
...
```

**Acceptance Criteria**:
- [ ] OpenAIEmbeddingService.php created (200-250 lines)
- [ ] Implements EmbeddingServiceInterface
- [ ] 15+ unit tests written
- [ ] All tests pass
- [ ] Characterization tests still pass
- [ ] Code follows PSR-12

**Commands**:
```bash
# Create branch from characterization branch
git checkout sprint3/openai-characterization-tests
git checkout -b sprint3/openai-embedding-service

# Create files
mkdir -p app/Services/AI
touch app/Services/AI/OpenAIEmbeddingService.php
touch tests/Unit/Services/AI/OpenAIEmbeddingServiceTest.php

# Run tests
./scripts/run-tests.sh --filter=OpenAIEmbeddingServiceTest

# Run characterization tests (should still pass)
./scripts/run-tests.sh --filter=OpenAIServiceCharacterizationTest

# Format code
./vendor/bin/pint app/Services/AI/OpenAIEmbeddingService.php

# Commit
git add app/Services/AI/OpenAIEmbeddingService.php tests/Unit/Services/AI/OpenAIEmbeddingServiceTest.php
git commit -m "Extract OpenAIEmbeddingService from OpenAIService (TDD GREEN)"
```

---

#### WORKER C: OpenAIAnalysisService

**Branch**: `sprint3/openai-analysis-service`
**Time**: 4-5 hours
**Priority**: P1 (HIGH - legal analysis pipeline)

**Files to Create**:
```
app/Services/AI/OpenAIAnalysisService.php (NEW, 300-350 lines)
tests/Unit/Services/AI/OpenAIAnalysisServiceTest.php (NEW, 500-600 lines)
```

**Files to Reference** (read these):
```
app/Services/OpenAIService.php (lines 600-900 - analysis methods)
app/Contracts/AI/AnalysisServiceInterface.php (interface to implement)
config/openai.php (analysis model configuration)
```

**Responsibilities to Extract**:
- `analyzeLegalText()` - Legal text analysis
- `summarizeDecision()` - Court decision summarization
- `extractCitations()` - Citation extraction from text
- `classifyDocument()` - Document classification
- `extractEntities()` - Legal entity extraction
- Analysis-specific error handling
- Structured output parsing

**Interface to Implement**:
```php
<?php

namespace App\Services\AI;

use App\Contracts\AI\AnalysisServiceInterface;
use App\Contracts\AI\ChatServiceInterface;
use App\Contracts\AI\CacheServiceInterface;

class OpenAIAnalysisService implements AnalysisServiceInterface
{
    public function __construct(
        protected ChatServiceInterface $chat,
        protected CacheServiceInterface $cache
    ) {}

    public function analyzeLegalText(string $text, array $options = []): array
    {
        // Extract from OpenAIService.php lines 600-680
        // Uses ChatService internally
    }

    public function summarizeDecision(string $decisionText, array $options = []): string
    {
        // Extract from OpenAIService.php lines 720-780
    }

    public function extractCitations(string $text): array
    {
        // Extract from OpenAIService.php lines 800-850
    }

    public function classifyDocument(string $text, array $categories): string
    {
        // Extract from OpenAIService.php lines 860-900
    }

    protected function parseStructuredOutput(string $response): array
    {
        // Parse JSON/structured responses
    }
}
```

**TDD Steps**:
1. **RED**: Write failing test for `analyzeLegalText()`
2. **GREEN**: Extract `analyzeLegalText()` from OpenAIService, make test pass
3. **REFACTOR**: Clean up, optimize
4. Repeat for all analysis methods

**Tests to Write** (25+ tests):
```
test_analyze_legal_text_basic()
test_analyze_legal_text_returns_structured_data()
test_analyze_legal_text_extracts_key_points()
test_analyze_legal_text_caches_results()
test_summarize_decision_basic()
test_summarize_decision_respects_max_length()
test_summarize_decision_preserves_key_facts()
test_extract_citations_basic()
test_extract_citations_finds_zkp_articles()
test_extract_citations_finds_kz_articles()
test_extract_citations_finds_ustav_articles()
test_extract_citations_returns_empty_on_no_citations()
test_classify_document_basic()
test_classify_document_with_custom_categories()
test_entity_extraction()
test_structured_output_parsing()
test_handles_invalid_json_gracefully()
...
```

**Acceptance Criteria**:
- [ ] OpenAIAnalysisService.php created (300-350 lines)
- [ ] Implements AnalysisServiceInterface
- [ ] 25+ unit tests written
- [ ] All tests pass
- [ ] Characterization tests still pass
- [ ] Code follows PSR-12

**Commands**:
```bash
# Create branch from characterization branch
git checkout sprint3/openai-characterization-tests
git checkout -b sprint3/openai-analysis-service

# Create files
mkdir -p app/Services/AI
touch app/Services/AI/OpenAIAnalysisService.php
touch tests/Unit/Services/AI/OpenAIAnalysisServiceTest.php

# Run tests
./scripts/run-tests.sh --filter=OpenAIAnalysisServiceTest

# Run characterization tests (should still pass)
./scripts/run-tests.sh --filter=OpenAIServiceCharacterizationTest

# Format code
./vendor/bin/pint app/Services/AI/OpenAIAnalysisService.php

# Commit
git add app/Services/AI/OpenAIAnalysisService.php tests/Unit/Services/AI/OpenAIAnalysisServiceTest.php
git commit -m "Extract OpenAIAnalysisService from OpenAIService (TDD GREEN)"
```

---

#### WORKER D: OpenAICacheService

**Branch**: `sprint3/openai-cache-service`
**Time**: 2-3 hours
**Priority**: P1 (HIGH - reduces API costs by 60%)

**Files to Create**:
```
app/Services/AI/OpenAICacheService.php (NEW, 150-200 lines)
tests/Unit/Services/AI/OpenAICacheServiceTest.php (NEW, 300-400 lines)
```

**Files to Reference** (read these):
```
app/Services/OpenAIService.php (lines 100-150, cache methods scattered throughout)
app/Contracts/AI/CacheServiceInterface.php (interface to implement)
config/cache.php (cache configuration)
```

**Responsibilities to Extract**:
- Key generation for different operation types
- Cache storage with TTL (chat: 1h, embeddings: 24h, analysis: 2h)
- Cache retrieval
- Cache invalidation
- Hit/miss statistics
- Cache warming

**Interface to Implement**:
```php
<?php

namespace App\Services\AI;

use App\Contracts\AI\CacheServiceInterface;
use Illuminate\Support\Facades\Cache;

class OpenAICacheService implements CacheServiceInterface
{
    protected array $ttls = [
        'chat' => 3600,        // 1 hour
        'embeddings' => 86400, // 24 hours
        'analysis' => 7200,    // 2 hours
    ];

    public function generateKey(string $operation, array $params): string
    {
        // Generate unique cache key
        // Extract from OpenAIService.php scattered locations
    }

    public function get(string $key): mixed
    {
        return Cache::get($key);
    }

    public function put(string $key, mixed $value, string $operation): void
    {
        $ttl = $this->ttls[$operation] ?? 3600;
        Cache::put($key, $value, $ttl);
    }

    public function forget(string $key): void
    {
        Cache::forget($key);
    }

    public function has(string $key): bool
    {
        return Cache::has($key);
    }

    public function getStatistics(): array
    {
        // Return cache hit/miss stats
    }
}
```

**TDD Steps**:
1. **RED**: Write failing test for `generateKey()`
2. **GREEN**: Implement `generateKey()`, make test pass
3. **REFACTOR**: Clean up, optimize
4. Repeat for all cache methods

**Tests to Write** (18+ tests):
```
test_generate_key_for_chat()
test_generate_key_for_embeddings()
test_generate_key_for_analysis()
test_generate_key_is_deterministic()
test_generate_key_includes_all_params()
test_put_and_get()
test_put_respects_ttl_per_operation()
test_chat_ttl_is_1_hour()
test_embeddings_ttl_is_24_hours()
test_analysis_ttl_is_2_hours()
test_forget_removes_key()
test_has_returns_true_for_existing_key()
test_has_returns_false_for_missing_key()
test_statistics_tracking()
test_cache_warming()
...
```

**Acceptance Criteria**:
- [ ] OpenAICacheService.php created (150-200 lines)
- [ ] Implements CacheServiceInterface
- [ ] 18+ unit tests written
- [ ] All tests pass
- [ ] Characterization tests still pass
- [ ] Code follows PSR-12

**Commands**:
```bash
# Create branch from characterization branch
git checkout sprint3/openai-characterization-tests
git checkout -b sprint3/openai-cache-service

# Create files
mkdir -p app/Services/AI
touch app/Services/AI/OpenAICacheService.php
touch tests/Unit/Services/AI/OpenAICacheServiceTest.php

# Run tests
./scripts/run-tests.sh --filter=OpenAICacheServiceTest

# Run characterization tests (should still pass)
./scripts/run-tests.sh --filter=OpenAIServiceCharacterizationTest

# Format code
./vendor/bin/pint app/Services/AI/OpenAICacheService.php

# Commit
git add app/Services/AI/OpenAICacheService.php tests/Unit/Services/AI/OpenAICacheServiceTest.php
git commit -m "Extract OpenAICacheService from OpenAIService (TDD GREEN)"
```

---

### Phase 3: Integration & Orchestrator (Day 6) - ALL WORKERS TOGETHER

**Duration**: 4-6 hours (pair programming recommended)
**Workers**: All 4 workers work together
**Branch**: `sprint3/openai-orchestrator`
**Dependencies**: Phase 2 complete (all 4 services extracted)

**Files to Create**:
```
app/Services/AI/OpenAIOrchestrator.php (NEW, 80-100 lines)
app/Providers/OpenAIServiceProvider.php (NEW, 60-80 lines)
tests/Unit/Services/AI/OpenAIOrchestratorTest.php (NEW, 200-300 lines)
```

**Files to Modify**:
```
app/Services/OpenAIService.php (REFACTOR - becomes thin wrapper or deprecated)
config/app.php (add OpenAIServiceProvider)
```

**Tasks**:

**Task 3.1: Create OpenAIOrchestrator** (2 hours)

```php
<?php

namespace App\Services\AI;

use App\Contracts\AI\ChatServiceInterface;
use App\Contracts\AI\EmbeddingServiceInterface;
use App\Contracts\AI\AnalysisServiceInterface;

/**
 * Orchestrates OpenAI services
 *
 * Facade pattern - delegates to specialized services
 */
class OpenAIOrchestrator
{
    public function __construct(
        protected ChatServiceInterface $chat,
        protected EmbeddingServiceInterface $embedding,
        protected AnalysisServiceInterface $analysis
    ) {}

    // Chat methods
    public function chat(array $messages, string $model = 'gpt-4o', array $options = []): array
    {
        return $this->chat->chat($messages, $model, $options);
    }

    public function chatStream(array $messages, string $model = 'gpt-4o', callable $callback = null): void
    {
        $this->chat->chatStream($messages, $model, $callback);
    }

    // Embedding methods
    public function embeddings(string $text, array $options = []): array
    {
        return $this->embedding->embeddings($text, $options);
    }

    // Analysis methods
    public function analyzeLegalText(string $text, array $options = []): array
    {
        return $this->analysis->analyzeLegalText($text, $options);
    }

    // ... delegate all other methods
}
```

**Task 3.2: Create OpenAIServiceProvider** (1 hour)

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\AI\OpenAICacheService;
use App\Services\AI\OpenAIChatService;
use App\Services\AI\OpenAIEmbeddingService;
use App\Services\AI\OpenAIAnalysisService;
use App\Services\AI\OpenAIOrchestrator;
use App\Contracts\AI\CacheServiceInterface;
use App\Contracts\AI\ChatServiceInterface;
use App\Contracts\AI\EmbeddingServiceInterface;
use App\Contracts\AI\AnalysisServiceInterface;

class OpenAIServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register cache service first (dependency for others)
        $this->app->singleton(CacheServiceInterface::class, OpenAICacheService::class);

        // Register specialized services
        $this->app->singleton(ChatServiceInterface::class, function ($app) {
            return new OpenAIChatService($app->make(CacheServiceInterface::class));
        });

        $this->app->singleton(EmbeddingServiceInterface::class, function ($app) {
            return new OpenAIEmbeddingService($app->make(CacheServiceInterface::class));
        });

        $this->app->singleton(AnalysisServiceInterface::class, function ($app) {
            return new OpenAIAnalysisService(
                $app->make(ChatServiceInterface::class),
                $app->make(CacheServiceInterface::class)
            );
        });

        // Register orchestrator
        $this->app->singleton(OpenAIOrchestrator::class, function ($app) {
            return new OpenAIOrchestrator(
                $app->make(ChatServiceInterface::class),
                $app->make(EmbeddingServiceInterface::class),
                $app->make(AnalysisServiceInterface::class)
            );
        });
    }
}
```

**Task 3.3: Refactor OpenAIService to Wrapper** (1 hour)

**Option A: Thin Wrapper (Recommended)**
```php
<?php

namespace App\Services;

use App\Services\AI\OpenAIOrchestrator;
use Illuminate\Support\Facades\Log;

/**
 * @deprecated Use OpenAIOrchestrator instead
 *
 * Backward compatibility wrapper
 */
class OpenAIService
{
    public function __construct(protected OpenAIOrchestrator $orchestrator) {}

    public function chat(array $messages, string $model = 'gpt-4o', array $options = []): array
    {
        Log::warning('OpenAIService is deprecated. Use OpenAIOrchestrator instead');
        return $this->orchestrator->chat($messages, $model, $options);
    }

    // Delegate all methods with deprecation warnings
}
```

**Option B: Delete Old OpenAIService** (if no backward compatibility needed)
```bash
# Move old to archive
mv app/Services/OpenAIService.php app/Services/OpenAIService.php.old

# Update all usages to use OpenAIOrchestrator
grep -r "use App\\Services\\OpenAIService" app/
# Update each file manually or with sed
```

**Task 3.4: Run All Tests** (1-2 hours)

```bash
# Run orchestrator tests
./scripts/run-tests.sh --filter=OpenAIOrchestratorTest

# Run ALL characterization tests (MUST PASS)
./scripts/run-tests.sh --filter=OpenAIServiceCharacterizationTest

# Run all AI service tests
./scripts/run-tests.sh --filter="Services/AI"

# Run full test suite
composer test
```

**Acceptance Criteria**:
- [ ] OpenAIOrchestrator created (80-100 lines)
- [ ] OpenAIServiceProvider created and registered
- [ ] All services properly injected via DI
- [ ] OpenAIService refactored (wrapper or deleted)
- [ ] All characterization tests pass ✅
- [ ] All new unit tests pass ✅
- [ ] Full test suite passes ✅

**Commands**:
```bash
# Merge all service branches
git checkout sprint3/openai-characterization-tests
git merge sprint3/openai-chat-service
git merge sprint3/openai-embedding-service
git merge sprint3/openai-analysis-service
git merge sprint3/openai-cache-service
git checkout -b sprint3/openai-orchestrator

# Create orchestrator
touch app/Services/AI/OpenAIOrchestrator.php
touch app/Providers/OpenAIServiceProvider.php
touch tests/Unit/Services/AI/OpenAIOrchestratorTest.php

# Update config
vim config/app.php  # Add OpenAIServiceProvider

# Run all tests
composer test

# Commit
git add .
git commit -m "Create OpenAIOrchestrator and integrate all AI services"
```

---

### Phase 4: Documentation & Cleanup (Day 7) - SINGLE WORKER

**Duration**: 2-3 hours
**Worker**: Any 1 worker
**Branch**: `sprint3/documentation`

**Files to Create**:
```
docs/SPRINT_3_COMPLETION_REPORT.md (NEW)
docs/MIGRATION_GUIDE_OPENAI_SERVICES.md (NEW)
```

**Files to Update**:
```
README.md (add AI Services architecture section)
docs/PHASE_2_REFACTORING_STATUS.md (update Sprint 3 to 100%)
```

**Tasks**:

1. **Create Completion Report** (1 hour)
   - Document all services created
   - Show before/after metrics
   - List all tests passing
   - Provide examples

2. **Create Migration Guide** (1 hour)
   - Show old vs new usage
   - Provide code examples
   - Document breaking changes (if any)
   - FAQ section

3. **Update README** (30 min)
   - Add AI Services section
   - Show architecture diagram
   - Link to migration guide

4. **Code Quality** (30 min)
   - Run Laravel Pint on all new files
   - Check for unused imports
   - Verify PSR-12 compliance

**Commands**:
```bash
# Format all new files
./vendor/bin/pint app/Services/AI/
./vendor/bin/pint tests/Unit/Services/AI/

# Create docs
touch docs/SPRINT_3_COMPLETION_REPORT.md
touch docs/MIGRATION_GUIDE_OPENAI_SERVICES.md

# Commit
git add docs/
git commit -m "Complete Sprint 3 documentation"
```

---

## SPRINT 4: AutonomousResearchAgent Refactoring

**God Class**: `app/Agents/AutonomousResearchAgent.php` (1,146 lines)
**Target**: 5 specialized services + 1 orchestrator
**Interfaces**: Need to create (don't exist yet)

### Phase 1: Characterization Tests (Day 1) - ALL WORKERS TOGETHER

**Duration**: 4-6 hours (pair programming recommended)
**Workers**: All 4 workers work together on characterization tests
**Branch**: `sprint4/research-characterization-tests`

**Files to Create**:
```
tests/Unit/Agents/AutonomousResearchAgentCharacterizationTest.php (NEW)
app/Contracts/Research/QuestionGeneratorInterface.php (NEW)
app/Contracts/Research/SearchExecutorInterface.php (NEW)
app/Contracts/Research/AnswerEvaluatorInterface.php (NEW)
app/Contracts/Research/QualityAssessorInterface.php (NEW)
app/Contracts/Research/IterationControllerInterface.php (NEW)
```

**Tasks**:

**Task 1.1: Create Interfaces** (2 hours)

```php
<?php
// app/Contracts/Research/QuestionGeneratorInterface.php

namespace App\Contracts\Research;

interface QuestionGeneratorInterface
{
    /**
     * Generate research questions from initial query
     */
    public function generate(string $query, array $context = []): array;

    /**
     * Refine questions based on previous results
     */
    public function refine(array $questions, array $results, array $evaluation): array;
}
```

```php
<?php
// app/Contracts/Research/SearchExecutorInterface.php

namespace App\Contracts\Research;

interface SearchExecutorInterface
{
    /**
     * Execute searches across all corpora
     */
    public function execute(array $questions, array $options = []): array;

    /**
     * Search specific corpus
     */
    public function searchCorpus(string $corpus, string $query, array $options = []): array;
}
```

```php
<?php
// app/Contracts/Research/AnswerEvaluatorInterface.php

namespace App\Contracts\Research;

interface AnswerEvaluatorInterface
{
    /**
     * Evaluate answer quality
     */
    public function evaluate(string $query, string $answer, array $sources): array;

    /**
     * Score relevance of sources
     */
    public function scoreRelevance(string $query, array $sources): array;
}
```

```php
<?php
// app/Contracts/Research/QualityAssessorInterface.php

namespace App\Contracts\Research;

interface QualityAssessorInterface
{
    /**
     * Assess overall research quality
     */
    public function assess(string $query, string $answer, array $evaluation): array;

    /**
     * Check if answer is complete
     */
    public function isComplete(array $assessment): bool;
}
```

```php
<?php
// app/Contracts/Research/IterationControllerInterface.php

namespace App\Contracts\Research;

interface IterationControllerInterface
{
    /**
     * Check if research should continue
     */
    public function shouldContinue(int $iteration, float $qualityScore, array $limits): bool;

    /**
     * Get next iteration parameters
     */
    public function nextIteration(int $currentIteration, array $results): array;
}
```

**Task 1.2: Create Characterization Tests** (3 hours)

```
tests/Unit/Agents/AutonomousResearchAgentCharacterizationTest.php (NEW)
```

**Tests to Write** (35+ tests):
```
test_research_basic_query()
test_research_generates_questions()
test_research_executes_searches()
test_research_evaluates_answers()
test_research_iterates_on_low_quality()
test_research_stops_on_high_quality()
test_research_respects_max_iterations()
test_research_respects_token_budget()
test_research_respects_time_budget()
test_question_generation_basic()
test_question_refinement()
test_search_laws_corpus()
test_search_decisions_corpus()
test_search_cases_corpus()
test_search_aggregation()
test_answer_evaluation_basic()
test_answer_quality_scoring()
test_quality_threshold_85()
test_iteration_control()
test_cost_tracking()
test_source_attribution()
...
```

**Acceptance Criteria**:
- [ ] 5 interfaces created
- [ ] 35+ characterization tests written
- [ ] All tests pass on current AutonomousResearchAgent
- [ ] 100% coverage of public methods
- [ ] Tests frozen (DO NOT modify during refactoring)

**Commands**:
```bash
# Create branch
git checkout develop
git checkout -b sprint4/research-characterization-tests

# Create interfaces
mkdir -p app/Contracts/Research
touch app/Contracts/Research/QuestionGeneratorInterface.php
touch app/Contracts/Research/SearchExecutorInterface.php
touch app/Contracts/Research/AnswerEvaluatorInterface.php
touch app/Contracts/Research/QualityAssessorInterface.php
touch app/Contracts/Research/IterationControllerInterface.php

# Create test file
mkdir -p tests/Unit/Agents
touch tests/Unit/Agents/AutonomousResearchAgentCharacterizationTest.php

# Run tests
./scripts/run-tests.sh --filter=AutonomousResearchAgentCharacterizationTest

# Commit
git add app/Contracts/Research/ tests/Unit/Agents/
git commit -m "Add interfaces and characterization tests for AutonomousResearchAgent (35+ tests)"
```

---

### Phase 2: Parallel Service Extraction (Days 2-5) - PARALLEL WORK

**Duration**: 12-16 hours total (3-4 hours per worker)
**Workers**: Each worker takes ONE or MORE services
**Dependencies**: Phase 1 complete

#### WORKER A: QuestionGeneratorService

**Branch**: `sprint4/question-generator-service`
**Time**: 3-4 hours
**Priority**: P0 (CRITICAL - first step in research pipeline)

**Files to Create**:
```
app/Services/Research/QuestionGeneratorService.php (NEW, 200-250 lines)
tests/Unit/Services/Research/QuestionGeneratorServiceTest.php (NEW, 400-500 lines)
```

**Files to Reference** (read these):
```
app/Agents/AutonomousResearchAgent.php (lines 100-250 - question generation)
app/Contracts/Research/QuestionGeneratorInterface.php
app/Services/AI/OpenAIChatService.php (for chat calls)
```

**Responsibilities to Extract**:
- `generateResearchQuestions()` - Generate questions from query
- `refineQuestions()` - Refine questions based on feedback
- Question quality validation
- Question deduplication
- Prompt templates for question generation

**Interface to Implement**:
```php
<?php

namespace App\Services\Research;

use App\Contracts\Research\QuestionGeneratorInterface;
use App\Contracts\AI\ChatServiceInterface;

class QuestionGeneratorService implements QuestionGeneratorInterface
{
    public function __construct(
        protected ChatServiceInterface $chat
    ) {}

    public function generate(string $query, array $context = []): array
    {
        // Extract from AutonomousResearchAgent.php lines 100-180
        // Generate 3-5 research questions
    }

    public function refine(array $questions, array $results, array $evaluation): array
    {
        // Extract from AutonomousResearchAgent.php lines 200-250
        // Refine questions based on what was found/missing
    }

    protected function buildPrompt(string $query, array $context): string
    {
        // Prompt engineering for question generation
    }

    protected function parseQuestions(string $response): array
    {
        // Parse AI response into structured questions
    }
}
```

**Tests to Write** (20+ tests):
```
test_generate_questions_basic()
test_generate_returns_3_to_5_questions()
test_generate_questions_are_unique()
test_generate_with_context()
test_refine_questions_based_on_results()
test_refine_removes_answered_questions()
test_refine_adds_follow_up_questions()
test_question_validation()
test_question_deduplication()
test_prompt_building()
test_response_parsing()
...
```

**Acceptance Criteria**:
- [ ] QuestionGeneratorService.php created (200-250 lines)
- [ ] Implements QuestionGeneratorInterface
- [ ] 20+ unit tests written
- [ ] All tests pass
- [ ] Characterization tests still pass

**Commands**:
```bash
git checkout sprint4/research-characterization-tests
git checkout -b sprint4/question-generator-service

mkdir -p app/Services/Research
touch app/Services/Research/QuestionGeneratorService.php
touch tests/Unit/Services/Research/QuestionGeneratorServiceTest.php

./scripts/run-tests.sh --filter=QuestionGeneratorServiceTest
./vendor/bin/pint app/Services/Research/QuestionGeneratorService.php

git add app/Services/Research/QuestionGeneratorService.php tests/Unit/Services/Research/QuestionGeneratorServiceTest.php
git commit -m "Extract QuestionGeneratorService from AutonomousResearchAgent (TDD GREEN)"
```

---

#### WORKER B: SearchExecutorService

**Branch**: `sprint4/search-executor-service`
**Time**: 3-4 hours
**Priority**: P0 (CRITICAL - core search functionality)

**Files to Create**:
```
app/Services/Research/SearchExecutorService.php (NEW, 250-300 lines)
tests/Unit/Services/Research/SearchExecutorServiceTest.php (NEW, 500-600 lines)
```

**Files to Reference** (read these):
```
app/Agents/AutonomousResearchAgent.php (lines 300-550 - search execution)
app/Contracts/Research/SearchExecutorInterface.php
app/Services/Search/SearchOrchestrator.php (for search calls)
app/Services/Search/LawSearchService.php
app/Services/Search/DecisionSearchService.php
app/Services/Search/CaseSearchService.php
```

**Responsibilities to Extract**:
- Execute searches across multiple corpora (laws, decisions, cases)
- Aggregate search results
- Rank and score results
- Handle search errors/timeouts
- Track search performance

**Interface to Implement**:
```php
<?php

namespace App\Services\Research;

use App\Contracts\Research\SearchExecutorInterface;
use App\Services\Search\SearchOrchestrator;

class SearchExecutorService implements SearchExecutorInterface
{
    public function __construct(
        protected SearchOrchestrator $search
    ) {}

    public function execute(array $questions, array $options = []): array
    {
        // Extract from AutonomousResearchAgent.php lines 300-400
        // Execute searches for all questions across all corpora
    }

    public function searchCorpus(string $corpus, string $query, array $options = []): array
    {
        // Extract from AutonomousResearchAgent.php lines 420-480
        // Search specific corpus
    }

    protected function aggregateResults(array $results): array
    {
        // Aggregate and rank results
    }

    protected function scoreResults(array $results, string $query): array
    {
        // Score results by relevance
    }
}
```

**Tests to Write** (25+ tests):
```
test_execute_basic()
test_execute_searches_all_corpora()
test_execute_aggregates_results()
test_search_laws_corpus()
test_search_decisions_corpus()
test_search_cases_corpus()
test_search_handles_empty_results()
test_search_handles_errors()
test_result_aggregation()
test_result_scoring()
test_result_ranking()
test_handles_timeouts()
test_performance_tracking()
...
```

**Acceptance Criteria**:
- [ ] SearchExecutorService.php created (250-300 lines)
- [ ] Implements SearchExecutorInterface
- [ ] 25+ unit tests written
- [ ] All tests pass
- [ ] Characterization tests still pass

**Commands**:
```bash
git checkout sprint4/research-characterization-tests
git checkout -b sprint4/search-executor-service

mkdir -p app/Services/Research
touch app/Services/Research/SearchExecutorService.php
touch tests/Unit/Services/Research/SearchExecutorServiceTest.php

./scripts/run-tests.sh --filter=SearchExecutorServiceTest
./vendor/bin/pint app/Services/Research/SearchExecutorService.php

git add app/Services/Research/SearchExecutorService.php tests/Unit/Services/Research/SearchExecutorServiceTest.php
git commit -m "Extract SearchExecutorService from AutonomousResearchAgent (TDD GREEN)"
```

---

#### WORKER C: AnswerEvaluatorService

**Branch**: `sprint4/answer-evaluator-service`
**Time**: 3-4 hours
**Priority**: P0 (CRITICAL - quality evaluation)

**Files to Create**:
```
app/Services/Research/AnswerEvaluatorService.php (NEW, 250-300 lines)
tests/Unit/Services/Research/AnswerEvaluatorServiceTest.php (NEW, 500-600 lines)
```

**Files to Reference** (read these):
```
app/Agents/AutonomousResearchAgent.php (lines 600-800 - answer evaluation)
app/Contracts/Research/AnswerEvaluatorInterface.php
app/Services/AI/OpenAIChatService.php
```

**Responsibilities to Extract**:
- Evaluate answer quality (0-100 score)
- Score source relevance
- Check answer completeness
- Identify gaps in answer
- Generate improvement suggestions

**Interface to Implement**:
```php
<?php

namespace App\Services\Research;

use App\Contracts\Research\AnswerEvaluatorInterface;
use App\Contracts\AI\ChatServiceInterface;

class AnswerEvaluatorService implements AnswerEvaluatorInterface
{
    public function __construct(
        protected ChatServiceInterface $chat
    ) {}

    public function evaluate(string $query, string $answer, array $sources): array
    {
        // Extract from AutonomousResearchAgent.php lines 600-700
        // Returns: ['quality_score' => 85, 'relevance_scores' => [...], 'gaps' => [...]]
    }

    public function scoreRelevance(string $query, array $sources): array
    {
        // Extract from AutonomousResearchAgent.php lines 720-780
        // Score each source for relevance
    }

    protected function buildEvaluationPrompt(string $query, string $answer, array $sources): string
    {
        // Build evaluation prompt
    }

    protected function parseEvaluation(string $response): array
    {
        // Parse evaluation response
    }
}
```

**Tests to Write** (22+ tests):
```
test_evaluate_basic()
test_evaluate_returns_quality_score()
test_evaluate_returns_gaps()
test_evaluate_high_quality_answer()
test_evaluate_low_quality_answer()
test_score_relevance_basic()
test_score_relevance_high()
test_score_relevance_low()
test_evaluation_prompt_building()
test_response_parsing()
test_handles_missing_sources()
test_handles_empty_answer()
...
```

**Acceptance Criteria**:
- [ ] AnswerEvaluatorService.php created (250-300 lines)
- [ ] Implements AnswerEvaluatorInterface
- [ ] 22+ unit tests written
- [ ] All tests pass
- [ ] Characterization tests still pass

**Commands**:
```bash
git checkout sprint4/research-characterization-tests
git checkout -b sprint4/answer-evaluator-service

mkdir -p app/Services/Research
touch app/Services/Research/AnswerEvaluatorService.php
touch tests/Unit/Services/Research/AnswerEvaluatorServiceTest.php

./scripts/run-tests.sh --filter=AnswerEvaluatorServiceTest
./vendor/bin/pint app/Services/Research/AnswerEvaluatorService.php

git add app/Services/Research/AnswerEvaluatorService.php tests/Unit/Services/Research/AnswerEvaluatorServiceTest.php
git commit -m "Extract AnswerEvaluatorService from AutonomousResearchAgent (TDD GREEN)"
```

---

#### WORKER D: QualityAssessorService + IterationControllerService

**Branch**: `sprint4/quality-iteration-services`
**Time**: 4-5 hours (2 services)
**Priority**: P1 (HIGH - iteration control)

**Files to Create**:
```
app/Services/Research/QualityAssessorService.php (NEW, 200-250 lines)
app/Services/Research/IterationControllerService.php (NEW, 150-200 lines)
tests/Unit/Services/Research/QualityAssessorServiceTest.php (NEW, 400-500 lines)
tests/Unit/Services/Research/IterationControllerServiceTest.php (NEW, 300-400 lines)
```

**Files to Reference** (read these):
```
app/Agents/AutonomousResearchAgent.php (lines 850-1100)
app/Contracts/Research/QualityAssessorInterface.php
app/Contracts/Research/IterationControllerInterface.php
config/agent.php (iteration limits, thresholds)
```

**Service 1: QualityAssessorService**

**Responsibilities**:
- Assess overall research quality
- Check answer completeness
- Determine if quality threshold met (85+)
- Generate quality report

```php
<?php

namespace App\Services\Research;

use App\Contracts\Research\QualityAssessorInterface;

class QualityAssessorService implements QualityAssessorInterface
{
    protected float $qualityThreshold = 85.0;

    public function assess(string $query, string $answer, array $evaluation): array
    {
        // Extract from AutonomousResearchAgent.php lines 850-920
        // Returns: ['overall_quality' => 88, 'completeness' => true, 'report' => [...]]
    }

    public function isComplete(array $assessment): bool
    {
        return $assessment['overall_quality'] >= $this->qualityThreshold;
    }

    protected function calculateOverallQuality(array $evaluation): float
    {
        // Calculate weighted quality score
    }
}
```

**Service 2: IterationControllerService**

**Responsibilities**:
- Determine if iteration should continue
- Check iteration limits (max 5)
- Check token budget
- Check time budget
- Calculate next iteration parameters

```php
<?php

namespace App\Services\Research;

use App\Contracts\Research\IterationControllerInterface;

class IterationControllerService implements IterationControllerInterface
{
    protected int $maxIterations = 5;
    protected int $tokenBudget = 50000;
    protected int $timeBudgetSeconds = 300;

    public function shouldContinue(int $iteration, float $qualityScore, array $limits): bool
    {
        // Extract from AutonomousResearchAgent.php lines 950-1000
        // Check all stop criteria
    }

    public function nextIteration(int $currentIteration, array $results): array
    {
        // Extract from AutonomousResearchAgent.php lines 1020-1080
        // Calculate parameters for next iteration
    }

    protected function checkBudgets(array $limits): bool
    {
        // Check token and time budgets
    }
}
```

**Tests to Write** (35+ tests total):

QualityAssessorService (18 tests):
```
test_assess_basic()
test_assess_high_quality()
test_assess_low_quality()
test_is_complete_true_above_threshold()
test_is_complete_false_below_threshold()
test_calculate_overall_quality()
test_quality_threshold_85()
test_completeness_checking()
...
```

IterationControllerService (17 tests):
```
test_should_continue_basic()
test_should_continue_false_after_max_iterations()
test_should_continue_false_after_token_budget()
test_should_continue_false_after_time_budget()
test_should_continue_false_on_high_quality()
test_next_iteration_basic()
test_next_iteration_parameters()
test_budget_checking()
...
```

**Acceptance Criteria**:
- [ ] QualityAssessorService.php created (200-250 lines)
- [ ] IterationControllerService.php created (150-200 lines)
- [ ] Both implement their interfaces
- [ ] 35+ unit tests written (combined)
- [ ] All tests pass
- [ ] Characterization tests still pass

**Commands**:
```bash
git checkout sprint4/research-characterization-tests
git checkout -b sprint4/quality-iteration-services

mkdir -p app/Services/Research
touch app/Services/Research/QualityAssessorService.php
touch app/Services/Research/IterationControllerService.php
touch tests/Unit/Services/Research/QualityAssessorServiceTest.php
touch tests/Unit/Services/Research/IterationControllerServiceTest.php

./scripts/run-tests.sh --filter="QualityAssessorServiceTest|IterationControllerServiceTest"
./vendor/bin/pint app/Services/Research/

git add app/Services/Research/ tests/Unit/Services/Research/
git commit -m "Extract QualityAssessor and IterationController services (TDD GREEN)"
```

---

### Phase 3: Integration & Orchestrator (Day 6) - ALL WORKERS TOGETHER

**Duration**: 5-7 hours (pair programming recommended)
**Workers**: All 4 workers work together
**Branch**: `sprint4/research-orchestrator`
**Dependencies**: Phase 2 complete (all 5 services extracted)

**Files to Create**:
```
app/Services/Research/ResearchOrchestrator.php (NEW, 100-150 lines)
app/Providers/ResearchServiceProvider.php (NEW, 80-100 lines)
tests/Unit/Services/Research/ResearchOrchestratorTest.php (NEW, 400-500 lines)
```

**Files to Modify**:
```
app/Agents/AutonomousResearchAgent.php (REFACTOR - becomes thin wrapper or deprecated)
config/app.php (add ResearchServiceProvider)
```

**Tasks**:

**Task 3.1: Create ResearchOrchestrator** (3 hours)

```php
<?php

namespace App\Services\Research;

use App\Contracts\Research\QuestionGeneratorInterface;
use App\Contracts\Research\SearchExecutorInterface;
use App\Contracts\Research\AnswerEvaluatorInterface;
use App\Contracts\Research\QualityAssessorInterface;
use App\Contracts\Research\IterationControllerInterface;

/**
 * Orchestrates the autonomous research pipeline
 *
 * Pipeline:
 * 1. Generate Questions
 * 2. Execute Searches
 * 3. Evaluate Answer
 * 4. Assess Quality → If < 85: Refine Questions → Go to step 2
 * 5. Return Final Answer (quality ≥ 85 or max iterations)
 */
class ResearchOrchestrator
{
    public function __construct(
        protected QuestionGeneratorInterface $questionGenerator,
        protected SearchExecutorInterface $searchExecutor,
        protected AnswerEvaluatorInterface $answerEvaluator,
        protected QualityAssessorInterface $qualityAssessor,
        protected IterationControllerInterface $iterationController
    ) {}

    public function research(string $query, array $options = []): array
    {
        $iteration = 0;
        $qualityScore = 0;
        $limits = $this->initializeLimits($options);

        // Generate initial questions
        $questions = $this->questionGenerator->generate($query);

        while ($this->iterationController->shouldContinue($iteration, $qualityScore, $limits)) {
            $iteration++;

            // Execute searches
            $searchResults = $this->searchExecutor->execute($questions);

            // Synthesize answer (using ChatService)
            $answer = $this->synthesizeAnswer($query, $searchResults);

            // Evaluate answer
            $evaluation = $this->answerEvaluator->evaluate($query, $answer, $searchResults);

            // Assess quality
            $assessment = $this->qualityAssessor->assess($query, $answer, $evaluation);
            $qualityScore = $assessment['overall_quality'];

            // Check if complete
            if ($this->qualityAssessor->isComplete($assessment)) {
                break;
            }

            // Refine questions for next iteration
            $questions = $this->questionGenerator->refine($questions, $searchResults, $evaluation);
        }

        return [
            'query' => $query,
            'answer' => $answer ?? '',
            'quality_score' => $qualityScore,
            'iterations' => $iteration,
            'sources' => $searchResults ?? [],
            'assessment' => $assessment ?? [],
        ];
    }

    protected function synthesizeAnswer(string $query, array $searchResults): string
    {
        // Use ChatService to synthesize answer from search results
    }

    protected function initializeLimits(array $options): array
    {
        return [
            'tokens_used' => 0,
            'start_time' => now(),
            'token_budget' => $options['token_budget'] ?? 50000,
            'time_budget' => $options['time_budget'] ?? 300,
        ];
    }
}
```

**Task 3.2: Create ResearchServiceProvider** (1 hour)

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Research\QuestionGeneratorService;
use App\Services\Research\SearchExecutorService;
use App\Services\Research\AnswerEvaluatorService;
use App\Services\Research\QualityAssessorService;
use App\Services\Research\IterationControllerService;
use App\Services\Research\ResearchOrchestrator;
use App\Contracts\Research\QuestionGeneratorInterface;
use App\Contracts\Research\SearchExecutorInterface;
use App\Contracts\Research\AnswerEvaluatorInterface;
use App\Contracts\Research\QualityAssessorInterface;
use App\Contracts\Research\IterationControllerInterface;

class ResearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register all research services
        $this->app->singleton(QuestionGeneratorInterface::class, QuestionGeneratorService::class);
        $this->app->singleton(SearchExecutorInterface::class, SearchExecutorService::class);
        $this->app->singleton(AnswerEvaluatorInterface::class, AnswerEvaluatorService::class);
        $this->app->singleton(QualityAssessorInterface::class, QualityAssessorService::class);
        $this->app->singleton(IterationControllerInterface::class, IterationControllerService::class);

        // Register orchestrator
        $this->app->singleton(ResearchOrchestrator::class, function ($app) {
            return new ResearchOrchestrator(
                $app->make(QuestionGeneratorInterface::class),
                $app->make(SearchExecutorInterface::class),
                $app->make(AnswerEvaluatorInterface::class),
                $app->make(QualityAssessorInterface::class),
                $app->make(IterationControllerInterface::class)
            );
        });
    }
}
```

**Task 3.3: Refactor AutonomousResearchAgent to Wrapper** (1 hour)

```php
<?php

namespace App\Agents;

use App\Services\Research\ResearchOrchestrator;
use Illuminate\Support\Facades\Log;

/**
 * @deprecated Use ResearchOrchestrator instead
 *
 * Backward compatibility wrapper
 */
class AutonomousResearchAgent
{
    public function __construct(protected ResearchOrchestrator $orchestrator) {}

    public function research(string $query, array $options = []): array
    {
        Log::warning('AutonomousResearchAgent is deprecated. Use ResearchOrchestrator instead');
        return $this->orchestrator->research($query, $options);
    }

    // Delegate all methods with deprecation warnings
}
```

**Task 3.4: Run All Tests** (2-3 hours)

```bash
# Run orchestrator tests
./scripts/run-tests.sh --filter=ResearchOrchestratorTest

# Run ALL characterization tests (MUST PASS)
./scripts/run-tests.sh --filter=AutonomousResearchAgentCharacterizationTest

# Run all Research service tests
./scripts/run-tests.sh --filter="Services/Research"

# Run full test suite
composer test
```

**Acceptance Criteria**:
- [ ] ResearchOrchestrator created (100-150 lines)
- [ ] ResearchServiceProvider created and registered
- [ ] All services properly injected via DI
- [ ] AutonomousResearchAgent refactored (wrapper)
- [ ] All characterization tests pass ✅
- [ ] All new unit tests pass ✅
- [ ] Full test suite passes ✅
- [ ] Pipeline flows correctly (1→2→3→4→2...)

**Commands**:
```bash
# Merge all service branches
git checkout sprint4/research-characterization-tests
git merge sprint4/question-generator-service
git merge sprint4/search-executor-service
git merge sprint4/answer-evaluator-service
git merge sprint4/quality-iteration-services
git checkout -b sprint4/research-orchestrator

# Create orchestrator
mkdir -p app/Services/Research
touch app/Services/Research/ResearchOrchestrator.php
touch app/Providers/ResearchServiceProvider.php
touch tests/Unit/Services/Research/ResearchOrchestratorTest.php

# Update config
vim config/app.php  # Add ResearchServiceProvider

# Run all tests
composer test

# Commit
git add .
git commit -m "Create ResearchOrchestrator and integrate all Research services"
```

---

### Phase 4: Documentation & Cleanup (Day 7) - SINGLE WORKER

**Duration**: 2-3 hours
**Worker**: Any 1 worker
**Branch**: `sprint4/documentation`

**Files to Create**:
```
docs/SPRINT_4_COMPLETION_REPORT.md (NEW)
docs/MIGRATION_GUIDE_RESEARCH_SERVICES.md (NEW)
```

**Files to Update**:
```
README.md (add Research Services architecture section)
docs/PHASE_2_REFACTORING_STATUS.md (update Sprint 4 to 100%)
```

**Tasks**:

1. **Create Completion Report** (1 hour)
2. **Create Migration Guide** (1 hour)
3. **Update README** (30 min)
4. **Code Quality** (30 min)

**Commands**:
```bash
./vendor/bin/pint app/Services/Research/
./vendor/bin/pint tests/Unit/Services/Research/

touch docs/SPRINT_4_COMPLETION_REPORT.md
touch docs/MIGRATION_GUIDE_RESEARCH_SERVICES.md

git add docs/
git commit -m "Complete Sprint 4 documentation"
```

---

## Timeline & Resource Allocation

### Option 1: Sequential (1 Sprint at a Time)

**Week 1-2**: Sprint 3 (OpenAIService)
- Day 1: Phase 1 (characterization tests)
- Days 2-5: Phase 2 (parallel service extraction)
- Day 6: Phase 3 (orchestrator)
- Day 7: Phase 4 (documentation)

**Week 3-4**: Sprint 4 (AutonomousResearchAgent)
- Day 1: Phase 1 (characterization tests + interfaces)
- Days 2-5: Phase 2 (parallel service extraction)
- Day 6: Phase 3 (orchestrator)
- Day 7: Phase 4 (documentation)

**Total**: 4 weeks, 4 workers

---

### Option 2: Parallel (Both Sprints Simultaneously) ⚡ RECOMMENDED

**Week 1-2**: Sprint 3 + Sprint 4 in parallel

**Team A (4 workers)**: Sprint 3 (OpenAIService)
**Team B (4 workers)**: Sprint 4 (AutonomousResearchAgent)

Both teams follow same schedule:
- Day 1: Characterization tests
- Days 2-5: Parallel service extraction
- Day 6: Orchestrator
- Day 7: Documentation

**Total**: 2 weeks, 8 workers (or 4 weeks, 4 workers working on both sprints)

---

### Option 3: Hybrid (4 Workers, Faster) ⚡⚡

**Week 1**: Sprint 3 (all 4 workers)
- Day 1: All workers - characterization tests (4h)
- Days 2-3: All workers - parallel services (8h each = 2 days)
- Day 4: All workers - orchestrator (6h)
- Day 5: 1 worker - documentation (3h)

**Week 2**: Sprint 4 (all 4 workers)
- Day 1: All workers - characterization + interfaces (6h)
- Days 2-3: All workers - parallel services (8h each = 2 days)
- Day 4: All workers - orchestrator (7h)
- Day 5: 1 worker - documentation (3h)

**Total**: 2 weeks, 4 workers

---

## Final Deliverables Checklist

### Sprint 3 (OpenAIService):
- [ ] OpenAIChatService (250-300 lines)
- [ ] OpenAIEmbeddingService (200-250 lines)
- [ ] OpenAIAnalysisService (300-350 lines)
- [ ] OpenAICacheService (150-200 lines)
- [ ] OpenAIOrchestrator (80-100 lines)
- [ ] OpenAIServiceProvider (60-80 lines)
- [ ] 80+ unit tests (all passing)
- [ ] 30+ characterization tests (all passing)
- [ ] OpenAIService refactored (wrapper or deleted)
- [ ] docs/SPRINT_3_COMPLETION_REPORT.md
- [ ] docs/MIGRATION_GUIDE_OPENAI_SERVICES.md

### Sprint 4 (AutonomousResearchAgent):
- [ ] QuestionGeneratorService (200-250 lines)
- [ ] SearchExecutorService (250-300 lines)
- [ ] AnswerEvaluatorService (250-300 lines)
- [ ] QualityAssessorService (200-250 lines)
- [ ] IterationControllerService (150-200 lines)
- [ ] ResearchOrchestrator (100-150 lines)
- [ ] ResearchServiceProvider (80-100 lines)
- [ ] 5 research interfaces
- [ ] 100+ unit tests (all passing)
- [ ] 35+ characterization tests (all passing)
- [ ] AutonomousResearchAgent refactored (wrapper)
- [ ] docs/SPRINT_4_COMPLETION_REPORT.md
- [ ] docs/MIGRATION_GUIDE_RESEARCH_SERVICES.md

### Overall:
- [ ] All tests passing (300+ tests)
- [ ] No breaking changes (backward compatibility)
- [ ] PSR-12 code style compliance
- [ ] README.md updated with architecture
- [ ] 100% completion of refactoring campaign 🎉

---

## Success Criteria

✅ **All Services Created**: 9 new services total (4 AI + 5 Research)
✅ **All Tests Passing**: 300+ tests
✅ **Zero Breaking Changes**: Backward compatibility maintained
✅ **Code Quality**: PSR-12 compliant, no duplicates
✅ **Documentation**: Complete migration guides
✅ **Performance**: No performance degradation
✅ **Maintainability**: High (single responsibility services)

---

**Ready to Execute!** 🚀

Assign workers to sprints/tasks and begin with Phase 1 (characterization tests).

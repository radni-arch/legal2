# AutonomousResearchAgent to ResearchOrchestrator Migration Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Migrate all usages of deprecated AutonomousResearchAgent to ResearchOrchestrator, then safely remove the deprecated code.

**Architecture:** The old API is stateful (creates AgentRun models, supports checkpoint/resume) while the new API is stateless (single research() call returns results array). We'll create a compatibility layer that wraps ResearchOrchestrator to maintain the AgentRun persistence pattern while using the new orchestrator internally.

**Tech Stack:** Laravel 11, PHP 8.3, PHPUnit 11, Eloquent ORM

---

## Migration Strategy

**Approach:** Create `ResearchService` as a facade that:
1. Uses `ResearchOrchestrator` internally for research execution
2. Persists results to `AgentRun` model for backward compatibility
3. Dispatches events for real-time updates
4. Provides both sync and async execution

This preserves the existing job/controller patterns while using the new orchestrator.

---

## Task 1: Create ResearchService Wrapper

**Files:**
- Create: `app/Services/ResearchService.php`
- Test: `tests/Unit/Services/ResearchServiceTest.php`

**Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Services;

use App\Models\AgentRun;
use App\Models\LegalCase;
use App\Services\ResearchOrchestrator;
use App\Services\ResearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ResearchServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_creates_agent_run_and_executes_research()
    {
        // Arrange
        $mockOrchestrator = Mockery::mock(ResearchOrchestrator::class);
        $mockOrchestrator->shouldReceive('research')
            ->once()
            ->with('Find case law on home searches', Mockery::type('array'))
            ->andReturn([
                'success' => true,
                'answer' => 'Based on research...',
                'quality_score' => 85,
                'iterations' => 3,
                'stopped_reason' => 'quality_threshold',
                'total_tokens' => 5000,
                'total_time_s' => 12.5,
                'search_results' => [],
            ]);

        $service = new ResearchService($mockOrchestrator);

        // Act
        $run = $service->research(
            'Find case law on home searches',
            ['max_iterations' => 5, 'quality_threshold' => 80]
        );

        // Assert
        $this->assertInstanceOf(AgentRun::class, $run);
        $this->assertEquals('completed', $run->status);
        $this->assertEquals(85, $run->score);
        $this->assertEquals(3, $run->iterations);
        $this->assertEquals(5000, $run->tokens_used);
    }

    /** @test */
    public function it_handles_research_failure_gracefully()
    {
        // Arrange
        $mockOrchestrator = Mockery::mock(ResearchOrchestrator::class);
        $mockOrchestrator->shouldReceive('research')
            ->once()
            ->andReturn([
                'success' => false,
                'answer' => null,
                'quality_score' => 0,
                'iterations' => 1,
                'stopped_reason' => 'error',
                'total_tokens' => 100,
                'total_time_s' => 1.0,
                'search_results' => [],
            ]);

        $service = new ResearchService($mockOrchestrator);

        // Act
        $run = $service->research('Invalid query', []);

        // Assert
        $this->assertEquals('failed', $run->status);
        $this->assertEquals(0, $run->score);
    }

    /** @test */
    public function it_fires_research_completed_event()
    {
        // Arrange
        \Event::fake();

        $mockOrchestrator = Mockery::mock(ResearchOrchestrator::class);
        $mockOrchestrator->shouldReceive('research')
            ->andReturn([
                'success' => true,
                'answer' => 'Result',
                'quality_score' => 90,
                'iterations' => 2,
                'stopped_reason' => 'quality_threshold',
                'total_tokens' => 3000,
                'total_time_s' => 8.0,
                'search_results' => [],
            ]);

        $service = new ResearchService($mockOrchestrator);

        // Act
        $service->research('Test query', []);

        // Assert
        \Event::assertDispatched(\App\Events\ResearchCompleted::class);
    }
}
```

**Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Services/ResearchServiceTest.php -v`
Expected: FAIL with "Class 'App\Services\ResearchService' not found"

**Step 3: Write minimal implementation**

```php
<?php

namespace App\Services;

use App\Events\ResearchCompleted;
use App\Events\ResearchFailed;
use App\Models\AgentRun;
use Illuminate\Support\Facades\Log;

class ResearchService
{
    public function __construct(
        protected ResearchOrchestrator $orchestrator
    ) {}

    /**
     * Execute research and persist results to AgentRun model.
     *
     * This provides backward compatibility with the deprecated
     * AutonomousResearchAgent while using ResearchOrchestrator internally.
     */
    public function research(string $objective, array $options = []): AgentRun
    {
        $startTime = microtime(true);

        // Create AgentRun record
        $run = AgentRun::create([
            'objective' => $objective,
            'status' => 'running',
            'constraints' => $options,
            'started_at' => now(),
        ]);

        Log::info('ResearchService: Starting research', [
            'run_id' => $run->id,
            'objective' => $objective,
        ]);

        try {
            // Execute research via orchestrator
            $result = $this->orchestrator->research($objective, [
                'limits' => [
                    'max_iterations' => $options['max_iterations'] ?? 5,
                    'quality_threshold' => $options['quality_threshold'] ?? 85,
                ],
            ]);

            // Update run with results
            $run->update([
                'status' => $result['success'] ? 'completed' : 'failed',
                'score' => $result['quality_score'],
                'iterations' => $result['iterations'],
                'tokens_used' => $result['total_tokens'],
                'elapsed_seconds' => $result['total_time_s'],
                'result' => $result['answer'],
                'search_results' => $result['search_results'],
                'stopped_reason' => $result['stopped_reason'],
                'completed_at' => now(),
            ]);

            // Fire appropriate event
            if ($result['success']) {
                event(new ResearchCompleted($run));
            } else {
                event(new ResearchFailed($run, 'Research did not meet quality threshold'));
            }

            Log::info('ResearchService: Research completed', [
                'run_id' => $run->id,
                'status' => $run->status,
                'score' => $run->score,
            ]);

        } catch (\Exception $e) {
            $run->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            event(new ResearchFailed($run, $e->getMessage()));

            Log::error('ResearchService: Research failed', [
                'run_id' => $run->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $run->fresh();
    }

    /**
     * Queue research for async execution.
     */
    public function researchAsync(string $objective, array $options = []): AgentRun
    {
        // Create pending run
        $run = AgentRun::create([
            'objective' => $objective,
            'status' => 'pending',
            'constraints' => $options,
        ]);

        // Dispatch job
        \App\Jobs\ExecuteResearchJob::dispatch($run->id);

        return $run;
    }
}
```

**Step 4: Run test to verify it passes**

Run: `php artisan test tests/Unit/Services/ResearchServiceTest.php -v`
Expected: PASS

**Step 5: Commit**

```bash
git add app/Services/ResearchService.php tests/Unit/Services/ResearchServiceTest.php
git commit -m "feat: add ResearchService wrapper for backward compatibility"
```

---

## Task 2: Create ExecuteResearchJob

**Files:**
- Create: `app/Jobs/ExecuteResearchJob.php`
- Test: `tests/Unit/Jobs/ExecuteResearchJobTest.php`

**Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Jobs;

use App\Jobs\ExecuteResearchJob;
use App\Models\AgentRun;
use App\Services\ResearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class ExecuteResearchJobTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_executes_research_for_pending_run()
    {
        // Arrange
        $run = AgentRun::create([
            'objective' => 'Test research',
            'status' => 'pending',
            'constraints' => ['max_iterations' => 3],
        ]);

        $mockService = Mockery::mock(ResearchService::class);
        $mockService->shouldReceive('research')
            ->once()
            ->with('Test research', ['max_iterations' => 3])
            ->andReturn($run);

        $this->app->instance(ResearchService::class, $mockService);

        // Act
        $job = new ExecuteResearchJob($run->id);
        $job->handle(app(ResearchService::class));

        // Assert - job completed without exception
        $this->assertTrue(true);
    }

    /** @test */
    public function it_skips_non_pending_runs()
    {
        // Arrange
        $run = AgentRun::create([
            'objective' => 'Already completed',
            'status' => 'completed',
            'constraints' => [],
        ]);

        $mockService = Mockery::mock(ResearchService::class);
        $mockService->shouldNotReceive('research');

        $this->app->instance(ResearchService::class, $mockService);

        // Act
        $job = new ExecuteResearchJob($run->id);
        $job->handle(app(ResearchService::class));

        // Assert - research was not called
        $this->assertTrue(true);
    }

    /** @test */
    public function it_can_be_queued()
    {
        Queue::fake();

        $run = AgentRun::create([
            'objective' => 'Async research',
            'status' => 'pending',
            'constraints' => [],
        ]);

        ExecuteResearchJob::dispatch($run->id);

        Queue::assertPushed(ExecuteResearchJob::class, function ($job) use ($run) {
            return $job->runId === $run->id;
        });
    }
}
```

**Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Jobs/ExecuteResearchJobTest.php -v`
Expected: FAIL with "Class 'App\Jobs\ExecuteResearchJob' not found"

**Step 3: Write minimal implementation**

```php
<?php

namespace App\Jobs;

use App\Models\AgentRun;
use App\Services\ResearchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExecuteResearchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;
    public int $timeout = 3600; // 1 hour max

    public function __construct(
        public int $runId
    ) {}

    public function handle(ResearchService $service): void
    {
        $run = AgentRun::find($this->runId);

        if (!$run) {
            Log::warning('ExecuteResearchJob: Run not found', ['run_id' => $this->runId]);
            return;
        }

        // Skip if not pending
        if ($run->status !== 'pending') {
            Log::info('ExecuteResearchJob: Skipping non-pending run', [
                'run_id' => $this->runId,
                'status' => $run->status,
            ]);
            return;
        }

        Log::info('ExecuteResearchJob: Starting research', ['run_id' => $this->runId]);

        // Execute research (service handles status updates and events)
        $service->research($run->objective, $run->constraints ?? []);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ExecuteResearchJob: Job failed', [
            'run_id' => $this->runId,
            'error' => $exception->getMessage(),
        ]);

        $run = AgentRun::find($this->runId);
        if ($run) {
            $run->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'completed_at' => now(),
            ]);
        }
    }
}
```

**Step 4: Run test to verify it passes**

Run: `php artisan test tests/Unit/Jobs/ExecuteResearchJobTest.php -v`
Expected: PASS

**Step 5: Commit**

```bash
git add app/Jobs/ExecuteResearchJob.php tests/Unit/Jobs/ExecuteResearchJobTest.php
git commit -m "feat: add ExecuteResearchJob for async research execution"
```

---

## Task 3: Update AgentController to Use ResearchService

**Files:**
- Modify: `app/Http/Controllers/AgentController.php`
- Test: `tests/Feature/Controllers/AgentControllerTest.php`

**Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Controllers;

use App\Models\AgentRun;
use App\Models\User;
use App\Services\ResearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AgentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_starts_synchronous_research()
    {
        // Arrange
        $user = User::factory()->create();
        $run = AgentRun::create([
            'objective' => 'Test objective',
            'status' => 'completed',
            'score' => 85,
        ]);

        $mockService = Mockery::mock(ResearchService::class);
        $mockService->shouldReceive('research')
            ->once()
            ->with('Test objective', Mockery::type('array'))
            ->andReturn($run);

        $this->app->instance(ResearchService::class, $mockService);

        // Act
        $response = $this->actingAs($user)->postJson('/api/agent/research', [
            'objective' => 'Test objective',
            'async' => false,
        ]);

        // Assert
        $response->assertStatus(200);
        $response->assertJsonPath('data.run.status', 'completed');
    }

    /** @test */
    public function it_starts_asynchronous_research()
    {
        // Arrange
        $user = User::factory()->create();
        $run = AgentRun::create([
            'objective' => 'Async test',
            'status' => 'pending',
        ]);

        $mockService = Mockery::mock(ResearchService::class);
        $mockService->shouldReceive('researchAsync')
            ->once()
            ->with('Async test', Mockery::type('array'))
            ->andReturn($run);

        $this->app->instance(ResearchService::class, $mockService);

        // Act
        $response = $this->actingAs($user)->postJson('/api/agent/research', [
            'objective' => 'Async test',
            'async' => true,
        ]);

        // Assert
        $response->assertStatus(200);
        $response->assertJsonPath('data.async', true);
        $response->assertJsonPath('data.run.status', 'pending');
    }
}
```

**Step 2: Run test to verify current state**

Run: `php artisan test tests/Feature/Controllers/AgentControllerTest.php -v`

**Step 3: Update AgentController**

```php
<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Services\ResearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentController extends Controller
{
    public function __construct(
        protected ResearchService $researchService
    ) {}

    /**
     * Start a research task.
     */
    public function startResearch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'objective' => 'required|string|max:2000',
            'async' => 'boolean',
            'max_iterations' => 'integer|min:1|max:20',
            'quality_threshold' => 'integer|min:0|max:100',
            'token_budget' => 'integer|min:1000',
            'time_limit_seconds' => 'integer|min:60|max:7200',
        ]);

        $options = [
            'max_iterations' => $validated['max_iterations'] ?? 5,
            'quality_threshold' => $validated['quality_threshold'] ?? 85,
            'token_budget' => $validated['token_budget'] ?? null,
            'time_limit_seconds' => $validated['time_limit_seconds'] ?? null,
        ];

        $async = $validated['async'] ?? false;

        if ($async) {
            $run = $this->researchService->researchAsync(
                $validated['objective'],
                $options
            );

            return ApiResponse::success([
                'async' => true,
                'run' => [
                    'id' => $run->id,
                    'status' => $run->status,
                    'objective' => $run->objective,
                ],
                'message' => 'Research queued for execution',
            ]);
        }

        $run = $this->researchService->research(
            $validated['objective'],
            $options
        );

        return ApiResponse::success([
            'async' => false,
            'run' => [
                'id' => $run->id,
                'status' => $run->status,
                'objective' => $run->objective,
                'score' => $run->score,
                'iterations' => $run->iterations,
                'result' => $run->result,
            ],
        ]);
    }

    /**
     * Get research run status.
     */
    public function getRunStatus(int $runId): JsonResponse
    {
        $run = \App\Models\AgentRun::findOrFail($runId);

        return ApiResponse::success([
            'run' => [
                'id' => $run->id,
                'status' => $run->status,
                'objective' => $run->objective,
                'score' => $run->score,
                'iterations' => $run->iterations,
                'result' => $run->result,
                'error_message' => $run->error_message,
                'started_at' => $run->started_at,
                'completed_at' => $run->completed_at,
            ],
        ]);
    }
}
```

**Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Controllers/AgentControllerTest.php -v`
Expected: PASS

**Step 5: Commit**

```bash
git add app/Http/Controllers/AgentController.php tests/Feature/Controllers/AgentControllerTest.php
git commit -m "refactor: update AgentController to use ResearchService"
```

---

## Task 4: Register ResearchService in AppServiceProvider

**Files:**
- Modify: `app/Providers/AppServiceProvider.php`

**Step 1: Read current provider**

Read the current AppServiceProvider to understand existing bindings.

**Step 2: Add ResearchService binding**

Add after line 151:

```php
// ResearchService - wrapper for backward compatibility
$this->app->singleton(
    \App\Services\ResearchService::class,
    function ($app) {
        return new \App\Services\ResearchService(
            $app->make(\App\Services\ResearchOrchestrator::class)
        );
    }
);
```

**Step 3: Run tests to verify nothing broke**

Run: `php artisan test --filter=AgentController -v`
Expected: PASS

**Step 4: Commit**

```bash
git add app/Providers/AppServiceProvider.php
git commit -m "feat: register ResearchService in service provider"
```

---

## Task 5: Deprecate Old Jobs

**Files:**
- Modify: `app/Jobs/ExecuteAgentResearch.php`
- Modify: `app/Jobs/RunAutonomousResearchJob.php`

**Step 1: Add deprecation notices**

Update `ExecuteAgentResearch.php`:

```php
<?php

namespace App\Jobs;

// ... existing code ...

/**
 * @deprecated Use ExecuteResearchJob instead. This job will be removed in v2.0.
 * @see \App\Jobs\ExecuteResearchJob
 */
class ExecuteAgentResearch implements ShouldQueue
{
    // ... existing implementation ...
}
```

Update `RunAutonomousResearchJob.php`:

```php
<?php

namespace App\Jobs;

// ... existing code ...

/**
 * @deprecated Use ExecuteResearchJob instead. This job will be removed in v2.0.
 * @see \App\Jobs\ExecuteResearchJob
 */
class RunAutonomousResearchJob implements ShouldQueue
{
    // ... existing implementation ...
}
```

**Step 2: Commit**

```bash
git add app/Jobs/ExecuteAgentResearch.php app/Jobs/RunAutonomousResearchJob.php
git commit -m "deprecate: mark old research jobs as deprecated"
```

---

## Task 6: Deprecate AutonomousResearchAgent

**Files:**
- Modify: `app/Agents/AutonomousResearchAgent.php`
- Modify: `app/Contracts/Services/AutonomousResearchAgentInterface.php`

**Step 1: Add prominent deprecation notice to agent**

Add at top of class docblock:

```php
/**
 * @deprecated since v1.5.0, will be removed in v2.0.0
 * @see \App\Services\ResearchService Use ResearchService instead
 *
 * Migration guide:
 * - Old: $agent->startRun($objective) then $agent->executeRun($run)
 * - New: $service->research($objective, $options)
 *
 * The new ResearchService provides the same AgentRun persistence
 * while using ResearchOrchestrator internally.
 */
```

**Step 2: Add deprecation to interface**

```php
/**
 * @deprecated since v1.5.0, will be removed in v2.0.0
 * @see \App\Services\ResearchService
 */
interface AutonomousResearchAgentInterface
```

**Step 3: Commit**

```bash
git add app/Agents/AutonomousResearchAgent.php app/Contracts/Services/AutonomousResearchAgentInterface.php
git commit -m "deprecate: add deprecation notices to AutonomousResearchAgent"
```

---

## Task 7: Update Existing Tests to Use New API

**Files:**
- Modify: Tests that directly use AutonomousResearchAgent

**Step 1: Identify tests using old API**

Run: `grep -r "AutonomousResearchAgent" tests/ --include="*.php" -l`

**Step 2: For each test file, update to use ResearchService**

Replace:
```php
$agent = app(AutonomousResearchAgent::class);
$run = $agent->startRun($objective);
$run = $agent->executeRun($run);
```

With:
```php
$service = app(ResearchService::class);
$run = $service->research($objective, $options);
```

**Step 3: Run full test suite**

Run: `php artisan test`
Expected: All tests PASS

**Step 4: Commit**

```bash
git add tests/
git commit -m "refactor: update tests to use ResearchService instead of deprecated agent"
```

---

## Task 8: Final Verification and Documentation

**Files:**
- Create: `docs/migration/autonomous-research-agent.md`

**Step 1: Run full test suite**

```bash
php artisan test
```

**Step 2: Create migration documentation**

```markdown
# AutonomousResearchAgent Migration Guide

## Overview

The `AutonomousResearchAgent` has been deprecated in favor of `ResearchService`.

## Quick Migration

### Before (Deprecated)
```php
$agent = app(AutonomousResearchAgent::class);
$run = $agent->startRun('Research objective', $context, $constraints);
$run = $agent->executeRun($run);
```

### After (New)
```php
$service = app(ResearchService::class);
$run = $service->research('Research objective', [
    'max_iterations' => 5,
    'quality_threshold' => 85,
]);
```

## Async Execution

### Before
```php
$run = $agent->startRun($objective, $context, $constraints);
ExecuteAgentResearch::dispatch($run);
```

### After
```php
$run = $service->researchAsync($objective, $options);
// Job is automatically dispatched
```

## API Differences

| Old | New | Notes |
|-----|-----|-------|
| `startRun()` + `executeRun()` | `research()` | Single call |
| `resumeRun()` | N/A | Not supported |
| Returns `AgentRun` | Returns `AgentRun` | Same model |
| Fires events | Fires events | Same events |
```

**Step 3: Commit**

```bash
git add docs/migration/autonomous-research-agent.md
git commit -m "docs: add migration guide for AutonomousResearchAgent"
```

---

## Task 9: Remove Deprecated Code (Future - After Deprecation Period)

> **Note:** Only execute this task after a deprecation period (e.g., 2 releases)

**Files:**
- Delete: `app/Agents/AutonomousResearchAgent.php`
- Delete: `app/Contracts/Services/AutonomousResearchAgentInterface.php`
- Delete: `app/Jobs/ExecuteAgentResearch.php`
- Delete: `app/Jobs/RunAutonomousResearchJob.php`
- Modify: `app/Providers/AppServiceProvider.php` (remove old binding)

**Step 1: Verify no usages remain**

```bash
grep -r "AutonomousResearchAgent" app/ --include="*.php"
grep -r "ExecuteAgentResearch" app/ --include="*.php"
grep -r "RunAutonomousResearchJob" app/ --include="*.php"
```

**Step 2: Delete files**

```bash
rm app/Agents/AutonomousResearchAgent.php
rm app/Contracts/Services/AutonomousResearchAgentInterface.php
rm app/Jobs/ExecuteAgentResearch.php
rm app/Jobs/RunAutonomousResearchJob.php
```

**Step 3: Remove service binding from AppServiceProvider**

Remove lines 149-151:
```php
$this->app->bind(
    \App\Contracts\Services\AutonomousResearchAgentInterface::class,
    \App\Agents\AutonomousResearchAgent::class
);
```

**Step 4: Run tests**

```bash
php artisan test
```

**Step 5: Commit**

```bash
git add -A
git commit -m "refactor: remove deprecated AutonomousResearchAgent and related code"
```

---

## Summary

| Task | Description | Est. Time |
|------|-------------|-----------|
| 1 | Create ResearchService wrapper | 30 min |
| 2 | Create ExecuteResearchJob | 20 min |
| 3 | Update AgentController | 30 min |
| 4 | Register in AppServiceProvider | 10 min |
| 5 | Deprecate old jobs | 10 min |
| 6 | Deprecate AutonomousResearchAgent | 10 min |
| 7 | Update existing tests | 60 min |
| 8 | Final verification and docs | 20 min |
| 9 | Remove deprecated code (future) | 20 min |

**Total: ~3.5 hours** (excluding Task 9 which is future work)

---

Plan complete and saved to `docs/plans/2026-01-11-autonomous-research-agent-migration.md`. Two execution options:

**1. Subagent-Driven (this session)** - I dispatch fresh subagent per task, review between tasks, fast iteration

**2. Parallel Session (separate)** - Open new session with executing-plans, batch execution with checkpoints

**Which approach?**

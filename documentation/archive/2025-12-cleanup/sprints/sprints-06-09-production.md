# Production Hardening Sprints 6-9
**Task Organization for 4 Parallel Worker Agents**

**Generated from**: PRODUCTION_READINESS_ANALYSIS.md
**Date**: 2025-11-07
**Total Duration**: 16-25 days (4 sprints)
**Workers per Sprint**: 4 agents working in parallel

---

## Sprint Overview

| Sprint | Focus | Duration | Priority | Blocking |
|--------|-------|----------|----------|----------|
| **Sprint 6** | Critical Security | 5-7 days | 🔴 CRITICAL | YES |
| **Sprint 7** | Operational Readiness | 3-4 days | ⚠️ HIGH | YES |
| **Sprint 8** | E2E Testing (Dusk) | 4-5 days | ⚠️ MEDIUM | NO |
| **Sprint 9** | Interface Extraction | 7-10 days | ⚠️ MEDIUM | NO |

**Critical Path**: Sprint 6 + Sprint 7 = 8-11 days to deployable state

---

# Sprint 6: Critical Security 🔴 BLOCKING

**Duration**: 5-7 days
**Status**: CRITICAL - Cannot deploy without this
**Goal**: Fix critical security vulnerabilities

**Acceptance Criteria**:
- ✅ Authorization coverage: 0% → 70%
- ✅ Input validation coverage: 35% → 85%
- ✅ Rate limiting coverage: 60% → 90%
- ✅ Health check coverage: 30% → 80%
- ✅ All API endpoints secured
- ✅ Security tests pass

---

## Worker A: Authorization System (5-7 days)

**Task**: Implement complete authorization layer with policies and gates

**Deliverables**: 8 policies + 1 trait + tests

### Day 1-2: Core Policy Infrastructure

#### Task A1: Create Policy Base + Case/Document Policies
**File**: `app/Policies/BasePolicy.php`
```php
<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

abstract class BasePolicy
{
    use HandlesAuthorization;

    /**
     * Determine if user is admin
     */
    protected function isAdmin(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine if user is lawyer
     */
    protected function isLawyer(User $user): bool
    {
        return $user->hasRole('lawyer') || $user->hasRole('admin');
    }

    /**
     * Admin can do anything
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return null;
    }
}
```

**File**: `app/Policies/LegalCasePolicy.php`
```php
<?php

namespace App\Policies;

use App\Models\LegalCase;
use App\Models\User;

class LegalCasePolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isLawyer($user);
    }

    public function view(User $user, LegalCase $case): bool
    {
        // Can view if:
        // 1. Owns the case
        // 2. Is assigned to the case
        // 3. Is in the same team
        return $user->id === $case->user_id
            || $case->assignedUsers->contains($user)
            || ($user->team_id && $user->team_id === $case->team_id);
    }

    public function create(User $user): bool
    {
        return $this->isLawyer($user);
    }

    public function update(User $user, LegalCase $case): bool
    {
        return $user->id === $case->user_id
            || $case->assignedUsers->contains($user);
    }

    public function delete(User $user, LegalCase $case): bool
    {
        return $user->id === $case->user_id;
    }

    public function forceDelete(User $user, LegalCase $case): bool
    {
        return $this->isAdmin($user);
    }

    public function restore(User $user, LegalCase $case): bool
    {
        return $user->id === $case->user_id || $this->isAdmin($user);
    }
}
```

**File**: `app/Policies/CaseDocumentPolicy.php`
```php
<?php

namespace App\Policies;

use App\Models\CaseDocument;
use App\Models\User;

class CaseDocumentPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isLawyer($user);
    }

    public function view(User $user, CaseDocument $document): bool
    {
        // Can view document if can view its case
        return $user->can('view', $document->legalCase);
    }

    public function create(User $user): bool
    {
        return $this->isLawyer($user);
    }

    public function update(User $user, CaseDocument $document): bool
    {
        return $user->can('update', $document->legalCase);
    }

    public function delete(User $user, CaseDocument $document): bool
    {
        return $user->can('delete', $document->legalCase);
    }
}
```

**Tests**: `tests/Unit/Policies/LegalCasePolicyTest.php` (15 tests)
**Tests**: `tests/Unit/Policies/CaseDocumentPolicyTest.php` (12 tests)

**Estimate**: 2 days

---

#### Task A2: Decision and Law Policies
**File**: `app/Policies/CourtDecisionPolicy.php`
```php
<?php

namespace App\Policies;

use App\Models\CourtDecision;
use App\Models\User;

class CourtDecisionPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        // All lawyers can view court decisions (public data)
        return $this->isLawyer($user);
    }

    public function view(User $user, CourtDecision $decision): bool
    {
        // Court decisions are public
        return true;
    }

    public function create(User $user): bool
    {
        // Only admin can create decisions (ingestion)
        return $this->isAdmin($user);
    }

    public function update(User $user, CourtDecision $decision): bool
    {
        // Only admin can update decisions
        return $this->isAdmin($user);
    }

    public function delete(User $user, CourtDecision $decision): bool
    {
        // Only admin can delete decisions
        return $this->isAdmin($user);
    }
}
```

**File**: `app/Policies/LawPolicy.php`
**File**: `app/Policies/AgentRunPolicy.php`
**File**: `app/Policies/TextractJobPolicy.php`

**Tests**: 3 test files (30 tests total)

**Estimate**: 1.5 days

---

#### Task A3: Controller Authorization + HasRoles Trait
**File**: `app/Traits/HasRoles.php`
```php
<?php

namespace App\Traits;

trait HasRoles
{
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    public function hasAnyRole(array $roles): bool
    {
        return in_array($this->role, $roles);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isLawyer(): bool
    {
        return in_array($this->role, ['lawyer', 'admin']);
    }

    public function isAssistant(): bool
    {
        return in_array($this->role, ['assistant', 'lawyer', 'admin']);
    }
}
```

**Migration**: `database/migrations/2025_11_08_add_role_to_users.php`
```php
Schema::table('users', function (Blueprint $table) {
    $table->string('role')->default('viewer')->after('email');
    $table->unsignedBigInteger('team_id')->nullable()->after('role');
    $table->index(['role', 'team_id']);
});
```

**Update Controllers**: Add authorization to ~20 controllers
```php
// Example: CaseController.php
public function show(LegalCase $case)
{
    $this->authorize('view', $case); // ADD THIS LINE

    return view('cases.show', compact('case'));
}

public function update(Request $request, LegalCase $case)
{
    $this->authorize('update', $case); // ADD THIS LINE

    // ... existing code
}
```

**Controllers to Update**:
- `CaseController.php` (6 methods)
- `CaseDocumentController.php` (5 methods)
- `EvidenceController.php` (4 methods)
- `MisconductController.php` (4 methods)
- `DecisionController.php` (5 methods)
- `AgentController.php` (4 methods)
- `TextractController.php` (3 methods)
- (13 more controllers...)

**Tests**: `tests/Feature/Authorization/CaseAuthorizationTest.php` (integration tests)

**Estimate**: 1.5 days

---

#### Task A4: API Route Protection + Documentation
**Update**: `app/Providers/AuthServiceProvider.php`
```php
use App\Models\LegalCase;
use App\Policies\LegalCasePolicy;
// ... other imports

protected $policies = [
    LegalCase::class => LegalCasePolicy::class,
    CaseDocument::class => CaseDocumentPolicyDoc::class,
    CourtDecision::class => CourtDecisionPolicy::class,
    Law::class => LawPolicy::class,
    AgentRun::class => AgentRunPolicy::class,
    TextractJob::class => TextractJobPolicy::class,
];
```

**Add Middleware**: Update `routes/api.php`
```php
Route::middleware(['auth:sanctum', 'api.token'])->group(function () {
    // Protected routes
    Route::apiResource('cases', CaseController::class);
    Route::apiResource('cases.documents', CaseDocumentController::class);
    // ... etc
});
```

**Documentation**: `docs/AUTHORIZATION.md`
```markdown
# Authorization System

## Roles

- **Admin**: Full access to everything
- **Lawyer**: Can manage own cases, view all decisions/laws
- **Assistant**: Can view cases they're assigned to, limited editing
- **Viewer**: Read-only access

## Policies

### CasePolicy
- `view`: Owner, assigned users, or same team
- `update`: Owner or assigned users
- `delete`: Owner only

### DocumentPolicy
- Inherits from case permissions

## Usage

\`\`\`php
// In controllers
$this->authorize('view', $case);

// In views
@can('update', $case)
    <button>Edit</button>
@endcan

// In code
if ($user->can('delete', $case)) {
    $case->delete();
}
\`\`\`
```

**Estimate**: 1 day

---

**Worker A Total**: 5-7 days
**Deliverables**:
- 6 Policy files (~600 lines)
- 1 HasRoles trait (~50 lines)
- 1 Migration file
- ~20 controllers updated (~60 lines of changes)
- 60+ authorization tests
- 1 documentation file

---

## Worker B: Input Validation (3-4 days)

**Task**: Create FormRequest validators for all API endpoints

**Deliverables**: 40 FormRequest classes + tests

### Day 1: Evidence & Misconduct Validation

#### Task B1: Evidence Analysis Requests
**File**: `app/Http/Requests/Evidence/AnalyzeEvidenceRequest.php`
```php
<?php

namespace App\Http\Requests\Evidence;

use Illuminate\Foundation\Http\FormRequest;

class AnalyzeEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\LegalCase::class);
    }

    public function rules(): array
    {
        return [
            'case_id' => ['required', 'integer', 'exists:legal_cases,id'],
            'evidence_text' => ['required', 'string', 'min:10', 'max:50000'],
            'context' => ['nullable', 'array'],
            'context.date' => ['nullable', 'date'],
            'context.location' => ['nullable', 'string', 'max:500'],
            'analysis_type' => ['nullable', 'string', 'in:admissibility,recontextualization,suppression'],
        ];
    }

    public function messages(): array
    {
        return [
            'case_id.required' => 'Case ID is required for evidence analysis.',
            'case_id.exists' => 'The specified case does not exist.',
            'evidence_text.required' => 'Evidence text cannot be empty.',
            'evidence_text.min' => 'Evidence text must be at least 10 characters.',
            'evidence_text.max' => 'Evidence text cannot exceed 50,000 characters.',
        ];
    }
}
```

**Files to Create**:
- `AnalyzeEvidenceRequest.php`
- `CheckAdmissibilityRequest.php`
- `RecontextualizeEvidenceRequest.php`
- `GenerateSuppressionMotionRequest.php`

**Tests**: `tests/Unit/Requests/Evidence/AnalyzeEvidenceRequestTest.php` (8 tests per request)

**Estimate**: 1 day

---

#### Task B2: Search & Agent Requests

**Files**:
- `SearchRequest.php`
- `VectorSearchRequest.php`
- `RunAgentRequest.php`
- `StartResearchRequest.php`
- `GenerateQuestionsRequest.php`

**Example**: `app/Http/Requests/Search/VectorSearchRequest.php`
```php
public function rules(): array
{
    return [
        'query' => ['required', 'string', 'min:3', 'max:1000'],
        'corpus' => ['required', 'string', 'in:laws,decisions,cases,textract'],
        'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        'threshold' => ['nullable', 'numeric', 'min:0', 'max:1'],
        'filters' => ['nullable', 'array'],
        'filters.date_from' => ['nullable', 'date'],
        'filters.date_to' => ['nullable', 'date', 'after_or_equal:filters.date_from'],
        'filters.court' => ['nullable', 'string'],
    ];
}
```

**Estimate**: 1 day

---

#### Task B3: Case & Document Validation

**Files**:
- `StoreCaseRequest.php`
- `UpdateCaseRequest.php`
- `UploadDocumentRequest.php`
- `UpdateDocumentRequest.php`
- `StartTextractJobRequest.php`

**Example**: `app/Http/Requests/Case/StoreCaseRequest.php`
```php
public function rules(): array
{
    return [
        'case_number' => ['required', 'string', 'max:100', 'unique:legal_cases,case_number'],
        'title' => ['required', 'string', 'max:500'],
        'client_name' => ['required', 'string', 'max:255'],
        'court' => ['nullable', 'string', 'max:255'],
        'status' => ['required', 'string', 'in:active,pending,closed,archived'],
        'opened_at' => ['required', 'date', 'before_or_equal:today'],
        'closed_at' => ['nullable', 'date', 'after:opened_at'],
        'description' => ['nullable', 'string', 'max:10000'],
        'assigned_users' => ['nullable', 'array'],
        'assigned_users.*' => ['integer', 'exists:users,id'],
    ];
}
```

**Estimate**: 1 day

---

#### Task B4: Remaining Validators + Controller Integration

**Files** (20 more):
- Misconduct detection requests (4 files)
- Topic framework requests (3 files)
- Graph sync requests (2 files)
- Odluke ingestion requests (2 files)
- OpenAI proxy requests (3 files)
- Reasoning requests (3 files)
- Monitoring requests (2 files)
- Admin requests (1 file)

**Update Controllers**: Replace manual validation with FormRequests
```php
// Before:
public function store(Request $request) {
    $validated = $request->validate([...]);
}

// After:
public function store(StoreCaseRequest $request) {
    $validated = $request->validated();
}
```

**Controllers to Update**: ~25 controllers

**Estimate**: 1 day

---

**Worker B Total**: 3-4 days
**Deliverables**:
- 40 FormRequest classes (~2,400 lines)
- 40 test files (320 tests)
- ~25 controllers updated
- Validation coverage: 35% → 85%

---

## Worker C: Rate Limiting + Health Checks (2 days)

**Task**: Implement comprehensive rate limiting and health monitoring

**Deliverables**: Rate limiters + health endpoints + monitoring

### Day 1: Rate Limiting

#### Task C1: Rate Limiter Configuration
**File**: `app/Providers/RouteServiceProvider.php`
```php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

protected function configureRateLimiting(): void
{
    // OpenAI Proxy - Most restrictive (expensive)
    RateLimiter::for('openai', function (Request $request) {
        return Limit::perMinute(30)
            ->by($request->user()?->id ?: $request->ip())
            ->response(function () {
                return response()->json([
                    'error' => 'Too many OpenAI requests. Please try again later.',
                    'retry_after' => 60,
                ], 429);
            });
    });

    // Agent execution - Moderate
    RateLimiter::for('agents', function (Request $request) {
        return Limit::perMinute(10)
            ->by($request->user()?->id ?: $request->ip());
    });

    // Search - Liberal
    RateLimiter::for('search', function (Request $request) {
        return Limit::perMinute(60)
            ->by($request->user()?->id ?: $request->ip());
    });

    // API general
    RateLimiter::for('api', function (Request $request) {
        return Limit::perMinute(120)
            ->by($request->user()?->id ?: $request->ip());
    });

    // Token budget limiter (per user per day)
    RateLimiter::for('openai-tokens', function (Request $request) {
        $user = $request->user();
        $tokenBudget = $user?->token_budget_daily ?? 50000;

        return Limit::perDay($tokenBudget)
            ->by('tokens:' . ($user?->id ?? $request->ip()))
            ->response(function () use ($tokenBudget) {
                return response()->json([
                    'error' => 'Daily token budget exceeded',
                    'budget' => $tokenBudget,
                    'retry_after' => now()->endOfDay()->diffInSeconds(),
                ], 429);
            });
    });
}
```

**Update Routes**: `routes/api.php`
```php
// OpenAI proxy - CRITICAL rate limiting
Route::prefix('openai')
    ->middleware(['api.token', 'throttle:openai'])
    ->group(function () {
        Route::post('/chat/completions', [OpenAIProxyController::class, 'chat']);
        Route::post('/embeddings', [OpenAIProxyController::class, 'embeddings']);
        Route::post('/completions', [OpenAIProxyController::class, 'completions']);
    });

// Agent execution
Route::prefix('agent')
    ->middleware(['api.token', 'throttle:agents'])
    ->group(function () {
        Route::post('/run', [AgentController::class, 'run']);
        Route::post('/research', [AgentController::class, 'research']);
    });

// Search
Route::prefix('search')
    ->middleware(['api.token', 'throttle:search'])
    ->group(function () {
        // ... search routes
    });
```

**Middleware**: `app/Http/Middleware/TrackTokenUsage.php`
```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

class TrackTokenUsage
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Track tokens used in response
        if ($response->getStatusCode() === 200) {
            $data = $response->getData(true);
            $tokensUsed = $data['usage']['total_tokens'] ?? 0;

            if ($tokensUsed > 0) {
                $user = $request->user();
                $key = 'tokens:' . ($user?->id ?? $request->ip());

                // Increment token counter
                RateLimiter::hit($key, 86400); // 24 hours

                // Store actual usage
                Cache::increment($key . ':usage', $tokensUsed);
            }
        }

        return $response;
    }
}
```

**Estimate**: 1 day

---

### Day 2: Health Checks

#### Task C2: Health Check Endpoints
**File**: `app/Http/Controllers/HealthController.php`
```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

class HealthController extends Controller
{
    /**
     * Liveness probe - is the app running?
     */
    public function alive(): JsonResponse
    {
        return response()->json([
            'status' => 'alive',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Readiness probe - is the app ready to serve traffic?
     */
    public function ready(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'queue' => $this->checkQueue(),
        ];

        // Add Neo4j if enabled
        if (config('neo4j.enabled')) {
            $checks['neo4j'] = $this->checkNeo4j();
        }

        $allHealthy = collect($checks)->every(fn($check) => $check['status'] === 'ok');

        return response()->json([
            'status' => $allHealthy ? 'ready' : 'degraded',
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
        ], $allHealthy ? 200 : 503);
    }

    /**
     * Detailed health check
     */
    public function health(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'queue' => $this->checkQueue(),
            'storage' => $this->checkStorage(),
        ];

        if (config('neo4j.enabled')) {
            $checks['neo4j'] = $this->checkNeo4j();
        }

        if (config('services.aws.enabled')) {
            $checks['aws'] = $this->checkAWS();
        }

        $allHealthy = collect($checks)->every(fn($check) => $check['status'] === 'ok');

        return response()->json([
            'status' => $allHealthy ? 'healthy' : 'unhealthy',
            'timestamp' => now()->toIso8601String(),
            'version' => config('app.version', '1.0.0'),
            'environment' => app()->environment(),
            'checks' => $checks,
            'uptime' => $this->getUptime(),
        ], $allHealthy ? 200 : 503);
    }

    protected function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            $responseTime = $this->measureResponseTime(fn() => DB::select('SELECT 1'));

            return [
                'status' => 'ok',
                'response_time_ms' => round($responseTime * 1000, 2),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'fail',
                'error' => $e->getMessage(),
            ];
        }
    }

    protected function checkCache(): array
    {
        try {
            $key = 'health_check_' . time();
            Cache::put($key, 'test', 10);
            $value = Cache::get($key);
            Cache::forget($key);

            return [
                'status' => $value === 'test' ? 'ok' : 'fail',
                'driver' => config('cache.default'),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'fail',
                'error' => $e->getMessage(),
            ];
        }
    }

    protected function checkQueue(): array
    {
        try {
            $size = Queue::size();
            $status = $size < 1000 ? 'ok' : 'warn';

            return [
                'status' => $status,
                'size' => $size,
                'driver' => config('queue.default'),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'fail',
                'error' => $e->getMessage(),
            ];
        }
    }

    protected function checkNeo4j(): array
    {
        try {
            $neo4j = app(\App\Services\GraphDatabaseService::class);
            $responseTime = $this->measureResponseTime(fn() => $neo4j->ping());

            return [
                'status' => 'ok',
                'response_time_ms' => round($responseTime * 1000, 2),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'fail',
                'error' => $e->getMessage(),
            ];
        }
    }

    protected function checkStorage(): array
    {
        try {
            $disk = \Storage::disk('local');
            $disk->put('health_check.txt', 'test');
            $exists = $disk->exists('health_check.txt');
            $disk->delete('health_check.txt');

            return [
                'status' => $exists ? 'ok' : 'fail',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'fail',
                'error' => $e->getMessage(),
            ];
        }
    }

    protected function checkAWS(): array
    {
        try {
            // Simple S3 bucket check
            $s3 = \Storage::disk('s3');
            $responseTime = $this->measureResponseTime(fn() => $s3->exists('.health_check'));

            return [
                'status' => 'ok',
                'response_time_ms' => round($responseTime * 1000, 2),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'fail',
                'error' => $e->getMessage(),
            ];
        }
    }

    protected function measureResponseTime(callable $callback): float
    {
        $start = microtime(true);
        $callback();
        return microtime(true) - $start;
    }

    protected function getUptime(): string
    {
        $uptime = Cache::get('app_start_time');
        if (!$uptime) {
            $uptime = now();
            Cache::forever('app_start_time', $uptime);
        }

        return now()->diffForHumans($uptime, true);
    }
}
```

**Routes**: Add to `routes/web.php`
```php
// Health check routes (no auth required)
Route::get('/health', [HealthController::class, 'health']);
Route::get('/health/alive', [HealthController::class, 'alive']);
Route::get('/health/ready', [HealthController::class, 'ready']);
```

**Tests**: `tests/Feature/HealthCheckTest.php` (12 tests)

**Estimate**: 1 day

---

**Worker C Total**: 2 days
**Deliverables**:
- Rate limiter configuration (6 limiters)
- Token tracking middleware
- Health check controller (~250 lines)
- 3 health endpoints
- 12 health check tests
- Rate limiting coverage: 60% → 90%
- Health check coverage: 30% → 80%

---

## Worker D: Security Testing (2-3 days)

**Task**: Create comprehensive security tests

**Deliverables**: Security test suites

### Day 1: Authorization Tests

#### Task D1: Policy Integration Tests
**File**: `tests/Feature/Authorization/CaseAuthorizationTest.php`
```php
<?php

namespace Tests\Feature\Authorization;

use App\Models\LegalCase;
use App\Models\User;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class CaseAuthorizationTest extends TestCase
{
    use UsesTestDatabase;

    public function test_user_can_view_own_case(): void
    {
        $user = User::factory()->create(['role' => 'lawyer']);
        $case = LegalCase::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($user->can('view', $case));
    }

    public function test_user_cannot_view_other_users_case(): void
    {
        $user = User::factory()->create(['role' => 'lawyer']);
        $otherCase = LegalCase::factory()->create();

        $this->assertFalse($user->can('view', $otherCase));
    }

    public function test_user_can_view_team_case(): void
    {
        $teamId = 1;
        $user = User::factory()->create(['role' => 'lawyer', 'team_id' => $teamId]);
        $case = LegalCase::factory()->create(['team_id' => $teamId]);

        $this->assertTrue($user->can('view', $case));
    }

    public function test_assigned_user_can_view_case(): void
    {
        $user = User::factory()->create(['role' => 'assistant']);
        $case = LegalCase::factory()->create();
        $case->assignedUsers()->attach($user);

        $this->assertTrue($user->can('view', $case));
    }

    public function test_admin_can_view_any_case(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $case = LegalCase::factory()->create();

        $this->assertTrue($admin->can('view', $case));
    }

    public function test_viewer_cannot_create_case(): void
    {
        $viewer = User::factory()->create(['role' => 'viewer']);

        $this->assertFalse($viewer->can('create', LegalCase::class));
    }

    public function test_only_owner_can_delete_case(): void
    {
        $owner = User::factory()->create(['role' => 'lawyer']);
        $assignedUser = User::factory()->create(['role' => 'lawyer']);

        $case = LegalCase::factory()->create(['user_id' => $owner->id]);
        $case->assignedUsers()->attach($assignedUser);

        $this->assertTrue($owner->can('delete', $case));
        $this->assertFalse($assignedUser->can('delete', $case));
    }

    // ... (20 more tests)
}
```

**Files to Create**:
- `CaseAuthorizationTest.php` (27 tests)
- `DocumentAuthorizationTest.php` (24 tests)
- `DecisionAuthorizationTest.php` (15 tests)
- `AgentAuthorizationTest.php` (18 tests)

**Estimate**: 1 day

---

### Day 2: Input Validation Tests

#### Task D2: Validation Security Tests
**File**: `tests/Feature/Security/InputValidationTest.php`
```php
public function test_evidence_analysis_rejects_xss_attempt(): void
{
    $user = User::factory()->create(['role' => 'lawyer']);
    $case = LegalCase::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)
        ->postJson('/api/evidence/analyze', [
            'case_id' => $case->id,
            'evidence_text' => '<script>alert("XSS")</script>',
        ]);

    $response->assertStatus(200);

    // Verify XSS is escaped in response
    $response->assertJsonMissing(['<script>']);
}

public function test_search_rejects_sql_injection_attempt(): void
{
    $user = User::factory()->create(['role' => 'lawyer']);

    $response = $this->actingAs($user)
        ->postJson('/api/search', [
            'query' => "'; DROP TABLE users; --",
        ]);

    // Should be treated as normal search, not SQL
    $response->assertStatus(200);

    // Verify users table still exists
    $this->assertDatabaseHas('users', ['id' => $user->id]);
}

public function test_case_creation_validates_max_length(): void
{
    $user = User::factory()->create(['role' => 'lawyer']);

    $response = $this->actingAs($user)
        ->postJson('/api/cases', [
            'case_number' => str_repeat('A', 101), // Max 100
            'title' => str_repeat('B', 501), // Max 500
            'client_name' => str_repeat('C', 256), // Max 255
            'status' => 'active',
            'opened_at' => now(),
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['case_number', 'title', 'client_name']);
}
```

**Estimate**: 1 day

---

### Day 3: Rate Limiting Tests

#### Task D3: Rate Limit Security Tests
**File**: `tests/Feature/Security/RateLimitingTest.php`
```php
public function test_openai_proxy_enforces_rate_limit(): void
{
    $user = User::factory()->create();

    // Make 30 requests (at limit)
    for ($i = 0; $i < 30; $i++) {
        $response = $this->actingAs($user)
            ->postJson('/api/openai/chat/completions', [
                'messages' => [['role' => 'user', 'content' => "Test $i"]],
            ]);

        $response->assertStatus(200);
    }

    // 31st request should be rate limited
    $response = $this->actingAs($user)
        ->postJson('/api/openai/chat/completions', [
            'messages' => [['role' => 'user', 'content' => 'Test 31']],
        ]);

    $response->assertStatus(429)
        ->assertJson([
            'error' => 'Too many OpenAI requests. Please try again later.',
        ]);
}

public function test_token_budget_prevents_excessive_usage(): void
{
    $user = User::factory()->create(['token_budget_daily' => 1000]);

    // Simulate response with token usage
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => 'Response']]],
            'usage' => ['total_tokens' => 600],
        ]),
    ]);

    // First request (600 tokens)
    $response = $this->actingAs($user)
        ->postJson('/api/openai/chat/completions', [
            'messages' => [['role' => 'user', 'content' => 'Test']],
        ]);

    $response->assertStatus(200);

    // Second request would exceed budget (600 + 600 = 1200 > 1000)
    $response = $this->actingAs($user)
        ->postJson('/api/openai/chat/completions', [
            'messages' => [['role' => 'user', 'content' => 'Test 2']],
        ]);

    $response->assertStatus(429)
        ->assertJsonFragment(['error' => 'Daily token budget exceeded']);
}
```

**Estimate**: 0.5 days

---

**Worker D Total**: 2-3 days
**Deliverables**:
- 84 authorization tests
- 45 input validation security tests
- 18 rate limiting tests
- 147 total security tests

---

## Sprint 6 Summary

**Total Duration**: 5-7 days
**Workers**: 4 parallel
**Total Deliverables**:
- 6 Policy files
- 1 HasRoles trait
- 40 FormRequest validators
- 1 HealthController
- Rate limiter configuration
- Token tracking middleware
- 400+ security tests
- ~25 controllers updated with authorization
- ~25 controllers updated with validation

**Sprint 6 Acceptance**:
- ✅ All tests pass
- ✅ Authorization coverage: 0% → 70%
- ✅ Input validation coverage: 35% → 85%
- ✅ Rate limiting coverage: 60% → 90%
- ✅ Health check coverage: 30% → 80%
- ✅ No unauthorized access possible
- ✅ All inputs validated
- ✅ OpenAI costs controlled

---

# Sprint 7: Operational Readiness ⚠️ HIGH PRIORITY

**Duration**: 3-4 days
**Status**: HIGH - Required before production
**Goal**: Fix error handling, logging, and monitoring gaps

**Acceptance Criteria**:
- ✅ Error handling coverage: 55% → 85%
- ✅ Logging coverage: 62% → 90%
- ✅ Job test coverage: 47% → 100%
- ✅ Monitoring integrated
- ✅ All critical services resilient

---

## Worker A: Error Handling (3-4 days)

**Task**: Add comprehensive error handling to 56 services without try-catch

**Deliverables**: Error handling + custom exceptions + tests

### Services to Fix (Priority Order):

**Critical Services** (Day 1-2, 20 services):
1. `DecisionSearchService.php`
2. `LawSearchService.php`
3. `CaseSearchService.php`
4. `LawVectorStoreService.php`
5. `CourtDecisionVectorStoreService.php`
6. `CaseVectorStoreService.php`
7. `TextractVectorStoreService.php`
8. `EoglasnaService.php`
9. `EkomService.php`
10. `OdlukeIngestService.php`
11. `GraphDatabaseService.php`
12. `RagOrchestrator.php`
13. `UnifiedSearchService.php`
14. `FactExtractionService.php`
15. `DecisionCitationService.php`
16. `TaggingService.php`
17. `AdvancedKeywordExtractor.php`
18. `QueryRewriter.php`
19. `IngestPipelineService.php`
20. `CaseIngestPipeline.php`

**Medium Priority** (Day 3, 18 services):
- Legal Reasoning services (8 services)
- Graph services (5 services)
- Monitoring services (3 services)
- Utility services (2 services)

**Low Priority** (Day 4, 18 services):
- Pipeline helpers
- PDF utilities
- Collaboration services

### Implementation Pattern:

**Before**:
```php
public function search(string $query): array
{
    $embedding = $this->embeddingService->embed($query);
    $results = $this->vectorDb->search($embedding);
    return $this->formatResults($results);
}
```

**After**:
```php
use App\Exceptions\SearchException;
use App\Exceptions\EmbeddingException;
use App\Exceptions\VectorStoreException;
use Illuminate\Support\Facades\Log;

public function search(string $query): array
{
    try {
        Log::info('Search initiated', [
            'query' => $query,
            'service' => static::class,
        ]);

        $embedding = $this->generateEmbedding($query);
        $results = $this->executeVectorSearch($embedding);
        $formatted = $this->formatResults($results);

        Log::info('Search completed', [
            'query' => $query,
            'result_count' => count($formatted),
            'duration_ms' => $this->getExecutionTime(),
        ]);

        return $formatted;

    } catch (EmbeddingException $e) {
        Log::error('Embedding generation failed', [
            'query' => $query,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        throw new SearchException(
            'Failed to generate search embedding: ' . $e->getMessage(),
            SearchException::EMBEDDING_FAILED,
            $e
        );

    } catch (VectorStoreException $e) {
        Log::error('Vector search failed', [
            'query' => $query,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        throw new SearchException(
            'Vector database search failed: ' . $e->getMessage(),
            SearchException::VECTOR_SEARCH_FAILED,
            $e
        );

    } catch (\Exception $e) {
        Log::error('Unexpected search error', [
            'query' => $query,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        throw new SearchException(
            'An unexpected error occurred during search',
            SearchException::UNEXPECTED_ERROR,
            $e
        );
    }
}

protected function generateEmbedding(string $query): array
{
    try {
        return $this->embeddingService->embed($query);
    } catch (\Exception $e) {
        throw new EmbeddingException(
            'Failed to generate embedding for query',
            0,
            $e
        );
    }
}

protected function executeVectorSearch(array $embedding): array
{
    try {
        return $this->vectorDb->search($embedding);
    } catch (\Exception $e) {
        throw new VectorStoreException(
            'Vector database query failed',
            0,
            $e
        );
    }
}
```

### Custom Exceptions to Create:

**File**: `app/Exceptions/SearchException.php`
```php
<?php

namespace App\Exceptions;

use Exception;

class SearchException extends Exception
{
    public const EMBEDDING_FAILED = 1001;
    public const VECTOR_SEARCH_FAILED = 1002;
    public const CORPUS_NOT_FOUND = 1003;
    public const INVALID_QUERY = 1004;
    public const UNEXPECTED_ERROR = 1999;

    public function report(): bool
    {
        // Log to monitoring service
        app(\App\Services\Monitoring\AlertManager::class)
            ->sendAlert('search_failure', [
                'message' => $this->getMessage(),
                'code' => $this->getCode(),
            ]);

        return true;
    }
}
```

**Exception Files to Create**:
- `SearchException.php`
- `EmbeddingException.php`
- `VectorStoreException.php`
- `GraphException.php`
- `IngestException.php`
- `AnalysisException.php`
- `AgentException.php`
- `TextractException.php`

**Worker A Estimate**: 3-4 days
**Deliverables**:
- 56 services updated with error handling
- 8 custom exception classes
- ~1,500 lines of error handling code
- 56 error handling test files

---

## Worker B: Logging Enhancement (2-3 days)

**Task**: Add comprehensive logging to 48 services without logging

**Deliverables**: Structured logging + correlation IDs

### Day 1-2: Add Logging to Services

**Services to Enhance** (48 services):
- All services identified without Log:: calls
- Focus on critical paths and error scenarios

**Logging Patterns**:

```php
use Illuminate\Support\Facades\Log;

class LawSearchService
{
    public function search(string $query, array $options = []): array
    {
        $correlationId = request()->header('X-Request-ID') ?? \Str::uuid();
        $startTime = microtime(true);

        Log::withContext(['correlation_id' => $correlationId]);

        Log::info('Law search initiated', [
            'query' => $query,
            'options' => $options,
            'user_id' => auth()->id(),
        ]);

        try {
            $results = $this->executeSearch($query, $options);

            $duration = (microtime(true) - $startTime) * 1000;

            Log::info('Law search completed', [
                'query' => $query,
                'result_count' => count($results),
                'duration_ms' => round($duration, 2),
                'cache_hit' => $this->wasCacheHit(),
            ]);

            return $results;

        } catch (\Exception $e) {
            Log::error('Law search failed', [
                'query' => $query,
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
```

### Day 3: Structured Logging + Correlation IDs

**Middleware**: `app/Http/Middleware/AddCorrelationId.php`
```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AddCorrelationId
{
    public function handle(Request $request, Closure $next)
    {
        $correlationId = $request->header('X-Request-ID') ?? Str::uuid()->toString();

        $request->headers->set('X-Request-ID', $correlationId);

        Log::withContext([
            'correlation_id' => $correlationId,
            'user_id' => auth()->id(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $response = $next($request);

        $response->headers->set('X-Request-ID', $correlationId);

        return $response;
    }
}
```

**Logging Config**: Update `config/logging.php`
```php
'channels' => [
    'stack' => [
        'driver' => 'stack',
        'channels' => ['daily', 'stderr'],
        'ignore_exceptions' => false,
    ],

    'daily' => [
        'driver' => 'daily',
        'path' => storage_path('logs/laravel.log'),
        'level' => env('LOG_LEVEL', 'debug'),
        'days' => 14,
        'formatter' => \Monolog\Formatter\JsonFormatter::class, // JSON format
    ],

    'stderr' => [
        'driver' => 'monolog',
        'level' => env('LOG_LEVEL', 'debug'),
        'handler' => StreamHandler::class,
        'formatter' => env('LOG_STDERR_FORMATTER', \Monolog\Formatter\JsonFormatter::class),
        'with' => [
            'stream' => 'php://stderr',
        ],
    ],
],
```

**Worker B Estimate**: 2-3 days
**Deliverables**:
- 48 services with logging added
- Correlation ID middleware
- JSON structured logging
- ~800 lines of logging code

---

## Worker C: Job Tests (2 days)

**Task**: Create tests for 8 untested jobs

**Jobs to Test**:
1. `ProcessDrivePdfJob` (CRITICAL - Textract pipeline)
2. `SyncGraphJob`
3. `IngestDecisionJob`
4. `IngestLawJob`
5. `GenerateEmbeddingsJob`
6. `SyncCaseToGraphJob`
7. `ProcessWebhookJob`
8. `CleanupOldDataJob`

### Example Test:

**File**: `tests/Unit/Jobs/ProcessDrivePdfJobTest.php`
```php
<?php

namespace Tests\Unit\Jobs;

use App\Jobs\ProcessDrivePdfJob;
use App\Models\CaseDocument;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProcessDrivePdfJobTest extends TestCase
{
    public function test_job_is_queued_on_textract_queue(): void
    {
        Queue::fake();

        $document = CaseDocument::factory()->create([
            'google_drive_file_id' => 'test-file-123',
        ]);

        ProcessDrivePdfJob::dispatch($document->id);

        Queue::assertPushedOn('textract', ProcessDrivePdfJob::class);
    }

    public function test_job_downloads_file_from_drive(): void
    {
        Storage::fake('s3');

        $document = CaseDocument::factory()->create([
            'google_drive_file_id' => 'test-file-123',
        ]);

        $job = new ProcessDrivePdfJob($document->id);
        $job->handle();

        // Verify file was uploaded to S3
        Storage::disk('s3')->assertExists('textract/input/test-file-123.pdf');
    }

    public function test_job_starts_textract_analysis(): void
    {
        // Mock AWS Textract
        // Test that Textract job is started
        // Verify document status updated
    }

    public function test_job_handles_missing_document_gracefully(): void
    {
        $job = new ProcessDrivePdfJob(99999); // Non-existent ID

        $this->expectException(\Exception::class);

        $job->handle();
    }

    public function test_job_retries_on_failure(): void
    {
        // Test retry logic
    }

    // ... more tests
}
```

**Worker C Estimate**: 2 days
**Deliverables**:
- 8 job test files
- 64 job tests (8 per job)
- Job test coverage: 47% → 100%

---

## Worker D: Monitoring Integration (2 days)

**Task**: Integrate monitoring with external services and enhance alerting

### Day 1: Sentry Integration

**Install Sentry**:
```bash
composer require sentry/sentry-laravel
```

**Config**: `config/sentry.php`
```php
'dsn' => env('SENTRY_LARAVEL_DSN'),
'environment' => env('APP_ENV'),
'release' => env('APP_VERSION', '1.0.0'),
'traces_sample_rate' => env('SENTRY_TRACES_SAMPLE_RATE', 0.2),
'profiles_sample_rate' => env('SENTRY_PROFILES_SAMPLE_RATE', 0.2),

'before_send' => function (\Sentry\Event $event): ?\Sentry\Event {
    // Filter sensitive data
    $event->setExtra('user_id', auth()->id());
    $event->setExtra('correlation_id', request()->header('X-Request-ID'));

    return $event;
},
```

### Day 2: Enhanced Monitoring

**Service**: `app/Services/Monitoring/ProductionMonitor.php`
```php
<?php

namespace App\Services\Monitoring;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductionMonitor
{
    public function recordMetric(string $metric, float $value, array $tags = []): void
    {
        // Record to internal metrics
        app(MetricsCollector::class)->record($metric, $value, $tags);

        // Send to Sentry (if configured)
        if (config('sentry.dsn')) {
            \Sentry\metrics()->increment($metric, $value, $tags);
        }

        // Check for alerts
        $this->checkAlertThresholds($metric, $value);
    }

    public function checkServiceHealth(): array
    {
        return [
            'database' => $this->checkDatabaseHealth(),
            'cache' => $this->checkCacheHealth(),
            'queue' => $this->checkQueueHealth(),
            'api_response_time' => $this->getAverageResponseTime(),
            'error_rate' => $this->getErrorRate(),
        ];
    }

    protected function checkAlertThresholds(string $metric, float $value): void
    {
        $thresholds = config('monitoring.alert_thresholds', []);

        if (isset($thresholds[$metric]) && $value > $thresholds[$metric]) {
            app(AlertManager::class)->sendAlert($metric, [
                'value' => $value,
                'threshold' => $thresholds[$metric],
                'timestamp' => now(),
            ]);
        }
    }

    protected function getAverageResponseTime(): float
    {
        $cacheKey = 'monitoring:avg_response_time';
        return Cache::get($cacheKey, 0);
    }

    protected function getErrorRate(): float
    {
        $totalRequests = Cache::get('monitoring:total_requests', 0);
        $errorRequests = Cache::get('monitoring:error_requests', 0);

        if ($totalRequests === 0) {
            return 0;
        }

        return ($errorRequests / $totalRequests) * 100;
    }
}
```

**Worker D Estimate**: 2 days
**Deliverables**:
- Sentry integration
- Enhanced monitoring service
- Alert threshold configuration
- Monitoring dashboard queries

---

## Sprint 7 Summary

**Total Duration**: 3-4 days
**Workers**: 4 parallel
**Total Deliverables**:
- 56 services with error handling
- 8 custom exception classes
- 48 services with logging
- Correlation ID tracking
- 8 job test suites (64 tests)
- Sentry integration
- Enhanced monitoring

**Sprint 7 Acceptance**:
- ✅ Error handling coverage: 55% → 85%
- ✅ Logging coverage: 62% → 90%
- ✅ Job test coverage: 47% → 100%
- ✅ Monitoring integrated
- ✅ All critical services have try-catch blocks
- ✅ All services log critical events
- ✅ Correlation IDs track requests end-to-end

---

# Sprint 8: E2E Testing (Dusk) ⚠️ MEDIUM PRIORITY

**Duration**: 4-5 days
**Status**: MEDIUM - Recommended before production
**Goal**: Implement comprehensive browser testing

**Note**: This sprint plan already exists in `SPRINTS_5_8_COMPREHENSIVE_PLAN.md`

**Brief Summary**:
- Worker A: Legal Playground Tests (8 tests)
- Worker B: Graph Viewer + Textract Manager (8 tests)
- Worker C: Search + Timeline (9 tests)
- Worker D: Dashboard Tests (17 tests)

**Total**: 42 browser tests

---

# Sprint 9: Interface Extraction ⚠️ MEDIUM PRIORITY

**Duration**: 7-10 days
**Status**: MEDIUM - Technical debt reduction
**Goal**: Extract interfaces from legacy services

**Acceptance Criteria**:
- ✅ Interface coverage: 12% → 60%
- ✅ ~40 new interfaces created
- ✅ Service providers updated
- ✅ Tests updated to use interfaces
- ✅ Legacy services mockable

---

## Worker A: Vector Store Interfaces (2-3 days)

**Task**: Extract interfaces from 4 vector store services + dependencies

**Services**:
1. `LawVectorStoreService`
2. `CourtDecisionVectorStoreService`
3. `CaseVectorStoreService`
4. `TextractVectorStoreService`

**Dependent Services**:
5. `EkomService`
6. `EoglasnaService`
7. `OdlukeIngestService`
8. `ZakonHrIngestService`
9. `IngestPipelineService`
10. `CaseIngestPipeline`

### Implementation:

**File**: `app/Contracts/VectorStore/VectorStoreInterface.php`
```php
<?php

namespace App\Contracts\VectorStore;

interface VectorStoreInterface
{
    /**
     * Ingest documents into vector store
     */
    public function ingest(string $docId, array $documents, array $options = []): array;

    /**
     * Search vector store
     */
    public function search(array $embedding, array $options = []): array;

    /**
     * Delete documents from vector store
     */
    public function delete(string $docId): bool;

    /**
     * Get embedding dimensions
     */
    public function getEmbeddingDimensions(): int;

    /**
     * Check if document exists
     */
    public function exists(string $docId): bool;
}
```

**Update Service**: `app/Services/LawVectorStoreService.php`
```php
use App\Contracts\VectorStore\VectorStoreInterface;

class LawVectorStoreService implements VectorStoreInterface
{
    // Existing implementation
}
```

**Update Service Provider**:
```php
// In AppServiceProvider or new VectorStoreServiceProvider
$this->app->bind(
    \App\Contracts\VectorStore\LawVectorStoreInterface::class,
    \App\Services\LawVectorStoreService::class
);
```

**Worker A Deliverables**:
- 10 interface files
- 10 services updated to implement interfaces
- Service provider bindings
- Tests updated

---

## Worker B: Search Service Interfaces (2-3 days)

**Task**: Extract interfaces from search services

**Services**:
1. `LawSearchService` (old + new)
2. `DecisionSearchService` (old + new)
3. `CaseSearchService` (old + new)
4. `UnifiedSearchService`
5. `QueryRewriter`
6. `SearchOrchestrator` (new)
7. `SearchExecutorService` (new - already has interface ✅)
8. `SearchResultAggregator`
9. `AdvancedKeywordExtractor`
10. `TaggingService`

**Worker B Deliverables**:
- 9 new interface files (1 already exists)
- 9 services updated
- Service provider bindings

---

## Worker C: Pipeline & Agent Interfaces (2-3 days)

**Task**: Extract interfaces from pipelines and agent services

**Services**:
1. `AgentToolbox`
2. `AgentEvaluationService`
3. `AgentRunDispatcher`
4. `AutonomousResearchAgent` (old - partially refactored)
5. `McpToOpenAIBridge`
6. `InternalMcpClient`
7. `Textract/TableExtractorService`
8. `PdfMerger`
9. `Pdf/PdfArticleSplitter2`
10. `UploadService`

**Worker C Deliverables**:
- 10 interface files
- 10 services updated
- Service provider bindings

---

## Worker D: Utility & Graph Service Interfaces (2-3 days)

**Task**: Extract interfaces from utility and graph services

**Services**:
1. `GraphDatabaseService`
2. `GraphRelationshipUpdater`
3. `Graph/GraphRagOrchestrator` (already has interface ✅)
4. `Graph/GraphCitationLinker`
5. `Graph/GraphKeywordLinker`
6. `Graph/CaseGraphSyncService`
7. `Graph/TextractGraphSyncService`
8. `RagOrchestrator` (old)
9. `FactExtractionService`
10. `DecisionCitationService`
11. `CircuitBreaker`

**Worker D Deliverables**:
- 10 new interface files (1 already exists)
- 10 services updated
- Service provider bindings

---

## Sprint 9 Summary

**Total Duration**: 7-10 days
**Workers**: 4 parallel
**Total Deliverables**:
- 39 new interface files
- 39 services refactored to implement interfaces
- All service providers updated
- Interface coverage: 12% → 60%

**Sprint 9 Acceptance**:
- ✅ All legacy services have interfaces
- ✅ Service container properly configured
- ✅ Tests updated to inject interfaces
- ✅ Services are mockable for testing

---

# Overall Sprint Plan Summary

| Sprint | Duration | Workers | Tests Created | Code Changed | Priority |
|--------|----------|---------|---------------|--------------|----------|
| **Sprint 6** | 5-7 days | 4 | 400+ | 6 policies, 40 validators, health checks | 🔴 CRITICAL |
| **Sprint 7** | 3-4 days | 4 | 128 | 104 services enhanced | ⚠️ HIGH |
| **Sprint 8** | 4-5 days | 4 | 42 browser | Dusk setup + 42 tests | ⚠️ MEDIUM |
| **Sprint 9** | 7-10 days | 4 | 0 | 39 interfaces extracted | ⚠️ MEDIUM |
| **TOTAL** | **19-26 days** | 4 | **570+** | **~200 files** | - |

---

# Deployment Paths

## Path A: Minimum Viable (Sprint 6 + 7)
**Duration**: 8-11 days
**Status**: DEPLOYABLE with acceptable risk

**Includes**:
- ✅ Authorization system
- ✅ Input validation
- ✅ Rate limiting
- ✅ Health checks
- ✅ Error handling
- ✅ Logging
- ✅ Job tests
- ✅ Monitoring

**Gaps**: No E2E tests, interfaces not extracted

---

## Path B: Recommended (Sprint 6 + 7 + 8)
**Duration**: 12-16 days
**Status**: PRODUCTION-READY with confidence

**Includes**: Path A +
- ✅ 42 browser tests
- ✅ UI regression protection

**Gaps**: Interfaces not extracted (tech debt remains)

---

## Path C: Full Hardening (All Sprints)
**Duration**: 19-26 days
**Status**: ENTERPRISE-GRADE

**Includes**: Path B +
- ✅ 39 interfaces extracted
- ✅ Tech debt eliminated
- ✅ 100% mockable services

**Gaps**: None

---

# Task Assignment Strategy

## For 4 Worker Agents:

### Option 1: Sprint-by-Sprint (Sequential)
- All 4 workers complete Sprint 6 together
- Then all 4 move to Sprint 7
- Then Sprint 8
- Then Sprint 9
- **Duration**: 19-26 days
- **Advantage**: Focus, coordination
- **Disadvantage**: Slower overall delivery

### Option 2: Parallel Sprints (Concurrent)
- Worker 1: Sprint 6 → Sprint 7 → Done (8-11 days)
- Worker 2: Sprint 6 → Sprint 8 → Done (9-12 days)
- Worker 3: Sprint 6 → Sprint 9 → Done (12-17 days)
- Worker 4: Sprint 6 → Sprint 7 → Sprint 8 → Sprint 9 (19-26 days)
- **Duration**: 12-17 days (when Worker 3 finishes critical path)
- **Advantage**: Faster critical path
- **Disadvantage**: Complex coordination

### Option 3: Hybrid (Recommended)
- **Phase 1 (Sprint 6)**: All 4 workers together (5-7 days)
- **Phase 2 (Sprint 7)**: All 4 workers together (3-4 days)
- **Phase 3**: Deploy to production, then:
  - 2 workers → Sprint 8 (4-5 days)
  - 2 workers → Sprint 9 (7-10 days)
- **Duration to Production**: 8-11 days
- **Duration to Full Hardening**: 15-21 days
- **Advantage**: Fast to production, then parallel enhancement
- **Disadvantage**: Need to coordinate post-deployment work

---

# Recommended Approach

**Use Option 3 (Hybrid)**:

1. **Week 1-2**: All 4 workers on Sprint 6 + 7 (critical security) → **DEPLOY**
2. **Week 3**:
   - Workers A+B → Sprint 8 (E2E tests)
   - Workers C+D → Sprint 9 (interfaces)
3. **Production in 8-11 days, full hardening in 15-21 days**

---

**Document End**

**Next Steps**:
1. Choose deployment path (A, B, or C)
2. Choose task assignment strategy (1, 2, or 3)
3. Begin Sprint 6 execution

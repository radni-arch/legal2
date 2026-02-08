# Legal Artillery Modernization Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Fix the broken Legal Artillery feature — resolve 401 LLM errors, migrate to async job-based generation with real-time progress broadcasting, and rebuild all views to match the app's dark navy theme with full process transparency.

**Architecture:** Replace synchronous LLM calls with a queued job (`GenerateLegalDocumentJob`) that broadcasts iteration-level progress via WebSocket events. Refactor `LlmClient` to use Prism PHP (already in vendor) for unified LLM provider abstraction. Rebuild all 4 Blade views using the app's CSS variable dark theme system (`dark-theme.blade.php`). Add a step-by-step progress timeline to the UI showing Worker/Critic phases in real-time.

**Tech Stack:** Laravel 11, Livewire 3, Prism PHP, Tailwind CSS (dark theme via CSS variables), Laravel Queue/Horizon, Laravel Echo (Reverb WebSockets)

---

## Task 1: Fix LlmClient — Switch to Prism PHP

**Why:** The current `LlmClient` directly calls `api.anthropic.com` with an empty `ANTHROPIC_API_KEY`, causing 401 errors. Prism PHP (already installed via `vizra/vizra-adk`) supports multiple providers and is the standard in this codebase.

**Files:**
- Modify: `app/Services/LegalArtillery/LlmClient.php`
- Modify: `app/Providers/LegalArtilleryServiceProvider.php`
- Modify: `config/legal-artillery.php`
- Test: `tests/Unit/Services/LegalArtillery/LlmClientTest.php`

**Step 1: Update LlmClient to use Prism**

Replace `app/Services/LegalArtillery/LlmClient.php` with:

```php
<?php

namespace App\Services\LegalArtillery;

use Illuminate\Support\Facades\Log;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Prism;

class LlmClient
{
    public function __construct(
        private readonly string $provider,
        private readonly string $model,
        private readonly int $maxTokens = 8192,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            provider: config('legal-artillery.generation.provider', 'anthropic'),
            model: config('legal-artillery.generation.model', 'claude-sonnet-4-20250514'),
            maxTokens: (int) config('legal-artillery.generation.max_tokens', 8192),
        );
    }

    public function generate(string $systemPrompt, string $userPrompt, ?int $maxTokens = null): string
    {
        $providerEnum = Provider::from($this->provider);

        $response = Prism::text()
            ->using($providerEnum, $this->model)
            ->withSystemPrompt($systemPrompt)
            ->withPrompt($userPrompt)
            ->withMaxTokens($maxTokens ?? $this->maxTokens)
            ->asText();

        Log::debug('LlmClient: generation complete', [
            'provider' => $this->provider,
            'model' => $this->model,
            'usage' => [
                'prompt_tokens' => $response->usage->promptTokens ?? null,
                'completion_tokens' => $response->usage->completionTokens ?? null,
            ],
        ]);

        return $response->text;
    }
}
```

**Step 2: Add `provider` key to config**

In `config/legal-artillery.php`, update the `generation` section:

```php
'generation' => [
    'provider' => env('LEGAL_LLM_PROVIDER', 'anthropic'),
    'model' => env('LEGAL_LLM_MODEL', 'claude-sonnet-4-20250514'),
    'max_tokens' => env('LEGAL_LLM_MAX_TOKENS', 8192),
    'recursion_depth' => env('LEGAL_RECURSION_DEPTH', 3),
    'output_dir' => storage_path('app/legal-artillery/generated'),
    'archive_sent' => true,
],
```

**Step 3: Update LlmClientTest**

Update `tests/Unit/Services/LegalArtillery/LlmClientTest.php` to mock Prism instead of Http.

**Step 4: Run tests**

```bash
./scripts/run-focused-tests.sh LlmClientTest
```

**Step 5: Commit**

```bash
git add app/Services/LegalArtillery/LlmClient.php config/legal-artillery.php tests/Unit/Services/LegalArtillery/LlmClientTest.php
git commit -m "fix: migrate LlmClient from raw HTTP to Prism PHP - resolves 401 errors"
```

---

## Task 2: Create Async Job for Document Generation

**Why:** The orchestrator currently runs synchronously inside the HTTP request. With 5+ iterations and 2 LLM calls each, this will timeout. Moving to a queue job allows real-time progress broadcasting.

**Files:**
- Create: `app/Jobs/GenerateLegalDocumentJob.php`
- Modify: `app/Livewire/LegalArtillery/NewGeneration.php`
- Modify: `app/Agents/LegalArtilleryOrchestrator.php` (add per-iteration event broadcasting)

**Step 1: Create the queue job**

Create `app/Jobs/GenerateLegalDocumentJob.php`:

```php
<?php

namespace App\Jobs;

use App\Agents\LegalArtilleryOrchestrator;
use App\DTOs\CaseContext;
use App\DTOs\DocumentProfile;
use App\Jobs\Concerns\HasQueuePriority;
use App\Models\DocumentGenerationRun;
use App\Traits\BroadcastsJobProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateLegalDocumentJob implements ShouldQueue
{
    use BroadcastsJobProgress, Dispatchable, HasQueuePriority, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 600; // 10 minutes

    public function __construct(
        public readonly string $runId,
        public readonly string $profileKey,
        public readonly int $userId,
        public readonly int $maxIterations = 5,
        public readonly bool $sendEmail = false,
        public readonly bool $asDraft = true,
        public readonly ?string $toEmail = null,
    ) {
        $this->onQueue('legal-artillery');
    }

    public function getJobDisplayName(): string
    {
        return "Legal Artillery: {$this->profileKey}";
    }

    public function handle(LegalArtilleryOrchestrator $orchestrator): void
    {
        $run = DocumentGenerationRun::findOrFail($this->runId);

        $this->broadcastStarted($this->userId, $this->runId, [
            'profile' => $this->profileKey,
            'max_iterations' => $this->maxIterations,
        ]);

        try {
            $profile = DocumentProfile::fromConfig($this->profileKey);
            $caseContext = CaseContext::fromConfig();

            $orchestrator->generate(
                profile: $profile,
                caseContext: $caseContext,
                userId: $this->userId,
                maxIterations: $this->maxIterations,
                run: $run,
            );

            $run->refresh();

            $this->broadcastCompleted($this->userId, $this->runId, [
                'final_score' => $run->final_score,
                'total_iterations' => $run->total_iterations,
                'stopped_reason' => $run->stopped_reason,
            ]);

        } catch (\Exception $e) {
            Log::error('GenerateLegalDocumentJob failed', [
                'run_id' => $this->runId,
                'error' => $e->getMessage(),
            ]);

            $run->update([
                'status' => 'failed',
                'stopped_reason' => 'error: ' . substr($e->getMessage(), 0, 200),
            ]);

            $this->broadcastFailed(
                $this->userId,
                $this->runId,
                $e->getMessage(),
                'generation',
            );

            throw $e;
        }
    }
}
```

**Step 2: Update NewGeneration to dispatch job instead of calling agent directly**

Update `app/Livewire/LegalArtillery/NewGeneration.php` `startGeneration()`:

```php
public function startGeneration()
{
    $this->validate([
        'selectedProfile' => 'required|string',
        'maxIterations' => 'required|integer|min:1|max:10',
    ]);

    $this->isGenerating = true;

    try {
        // Create the run record first
        $run = \App\Models\DocumentGenerationRun::create([
            'document_type' => $this->selectedProfile,
            'status' => 'pending',
            'user_id' => Auth::id(),
            'model_config' => [
                'llm_model' => config('legal-artillery.generation.model'),
                'provider' => config('legal-artillery.generation.provider', 'anthropic'),
                'max_iterations' => $this->maxIterations,
            ],
        ]);

        // Dispatch the job
        \App\Jobs\GenerateLegalDocumentJob::dispatch(
            runId: $run->id,
            profileKey: $this->selectedProfile,
            userId: Auth::id(),
            maxIterations: $this->maxIterations,
            sendEmail: $this->sendEmail,
            asDraft: $this->asDraft,
            toEmail: $this->toEmail ?: null,
        );

        $this->currentRunId = $run->id;

        return redirect()->route('legal-artillery.run', $run->id);

    } catch (\Exception $e) {
        session()->flash('error', 'Greska: ' . $e->getMessage());
    } finally {
        $this->isGenerating = false;
    }
}
```

**Step 3: Update Orchestrator to accept existing run + broadcast per-iteration progress**

In `app/Agents/LegalArtilleryOrchestrator.php`, modify `generate()` signature to accept optional pre-created run, and add broadcasting after each iteration. The method should:
- Accept an optional `?DocumentGenerationRun $run = null` parameter
- If `$run` is provided, use it instead of creating a new one; update its status to `running`
- After each iteration completes (worker+critic), broadcast a `JobProgress` event with iteration details
- Wrap DB operations per-iteration instead of the whole loop

**Step 4: Run tests**

```bash
./scripts/run-focused-tests.sh LegalArtilleryAgentTest
```

**Step 5: Commit**

```bash
git add app/Jobs/GenerateLegalDocumentJob.php app/Livewire/LegalArtillery/NewGeneration.php app/Agents/LegalArtilleryOrchestrator.php
git commit -m "feat: async document generation with queue job and progress broadcasting"
```

---

## Task 3: Rebuild Dashboard View — Dark Theme

**Why:** Current dashboard uses `bg-gray-100` (light theme) while the entire app uses dark navy CSS variables. Needs to match `dark-theme.blade.php` color system.

**Files:**
- Modify: `resources/views/livewire/legal-artillery/dashboard.blade.php`

**Design:**
- Background: `var(--bg)` (#0b1220)
- Cards: `var(--card)` (#111827) with `var(--border)` (#1f2937)
- Stats cards in a grid with accent colors for values
- Run list items in card style with status badges using `badge-*` classes
- Header with gradient background matching `dash-header` class
- "Nova Paljba" button using `btn-primary` class

**Step 1: Replace dashboard.blade.php**

Complete rewrite using `var(--*)` CSS variables, `.card`, `.badge-*`, `.btn-primary` classes from `dark-theme.blade.php`. Match the style of `decision-discovery-dashboard.blade.php`.

Key patterns to follow:
- Stats grid: `.stat-card` with `.stat-value`, `.stat-label`
- Status badges: `.badge .badge-success`, `.badge .badge-warn`, `.badge .badge-error`
- List items: cards with hover effect
- Filters: `.dt-input` for selects

**Step 2: Verify rendering**

Load `/legal-artillery/` and verify dark theme applied.

**Step 3: Commit**

```bash
git add resources/views/livewire/legal-artillery/dashboard.blade.php
git commit -m "ui: rebuild Legal Artillery dashboard with dark navy theme"
```

---

## Task 4: Rebuild NewGeneration View — Dark Theme + Better UX

**Why:** Current form uses white/gray styling. Need dark theme + clearer profile descriptions.

**Files:**
- Modify: `resources/views/livewire/legal-artillery/new-generation.blade.php`

**Design:**
- Dark card form using `var(--card)`, `var(--border)`
- Profile selector showing profile name, recipient institution, and priority
- Max iterations slider with visual indicator
- Email options in collapsible section
- Submit button using `btn-primary` gradient style
- Back navigation link

**Step 1: Rewrite new-generation.blade.php with dark theme**

Use CSS variables throughout. Form inputs use `dt-input` class. Buttons use `btn-primary`/`btn-secondary`.

**Step 2: Commit**

```bash
git add resources/views/livewire/legal-artillery/new-generation.blade.php
git commit -m "ui: rebuild NewGeneration form with dark theme and better profile display"
```

---

## Task 5: Rebuild RunDetails View — Dark Theme + Process Transparency

**Why:** This is the critical view. Users need to see exactly what's happening: each iteration's Worker output, Critic scores, feedback, and improvement deltas. The current view is bare-bones and not transparent about the recursive process.

**Files:**
- Modify: `resources/views/livewire/legal-artillery/run-details.blade.php`
- Modify: `app/Livewire/LegalArtillery/RunDetails.php` (add data for expanded view)

**Design:**
- **Header section**: Run info card (profile name, status badge, final score, iterations, timestamps)
- **Progress timeline**: Visual step-by-step timeline showing:
  - Each iteration as a numbered step
  - Worker phase (with expandable document preview)
  - Critic phase (with score breakdown radar/bars and feedback)
  - Improvement delta arrows between iterations
- **Score breakdown**: Per-dimension scores (legal_rigor, persuasiveness, clarity, evidence_integration, formatting) as horizontal bars
- **Critic feedback sections**: Strengths (green), Weaknesses (red), Improvements (blue)
- **Final document**: Rendered in a styled document preview card
- **Live updates**: If status is `running` or `pending`, embed GenerationMonitor with polling

**Step 1: Update RunDetails.php to expose richer iteration data**

Add methods to structure iteration data with Worker/Critic pairs, scores, and feedback properly extracted.

**Step 2: Rewrite run-details.blade.php**

Complete dark-theme rewrite with iteration timeline, score bars, and expandable sections.

**Step 3: Commit**

```bash
git add resources/views/livewire/legal-artillery/run-details.blade.php app/Livewire/LegalArtillery/RunDetails.php
git commit -m "ui: rebuild RunDetails with dark theme and full process transparency"
```

---

## Task 6: Rebuild GenerationMonitor View — Live Progress with Dark Theme

**Why:** This is the real-time monitoring component that shows during active generation. Needs to display each phase as it happens.

**Files:**
- Modify: `resources/views/livewire/legal-artillery/generation-monitor.blade.php`
- Modify: `app/Livewire/LegalArtillery/GenerationMonitor.php` (add Echo listener)

**Design:**
- Animated progress indicator showing current phase (Worker/Critic)
- Live score display updating with each critic evaluation
- Timeline of completed iterations with scores
- Pulse animation for active phase
- WebSocket listener for `JobProgress` events (supplement polling)

**Step 1: Update GenerationMonitor.php**

Add `getListeners()` to handle broadcast events alongside polling. Reduce poll interval (keep 3s for fallback).

**Step 2: Rewrite generation-monitor.blade.php**

Dark theme with animated elements and real-time updates.

**Step 3: Commit**

```bash
git add resources/views/livewire/legal-artillery/generation-monitor.blade.php app/Livewire/LegalArtillery/GenerationMonitor.php
git commit -m "ui: rebuild GenerationMonitor with dark theme and live progress timeline"
```

---

## Task 7: Fix Dashboard N+1 Queries

**Why:** `getStats()` fires 5 separate queries. Can be reduced to 1 with aggregate query.

**Files:**
- Modify: `app/Livewire/LegalArtillery/Dashboard.php`

**Step 1: Optimize getStats()**

```php
protected function getStats(): array
{
    $userId = Auth::id();
    $stats = DocumentGenerationRun::where('user_id', $userId)
        ->selectRaw("
            COUNT(*) as total,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
            COUNT(CASE WHEN status = 'running' OR status = 'pending' THEN 1 END) as running,
            AVG(CASE WHEN status = 'completed' THEN final_score END) as avg_score,
            AVG(CASE WHEN status = 'completed' THEN total_iterations END) as avg_iterations
        ")
        ->first();

    return [
        'total' => (int) $stats->total,
        'completed' => (int) $stats->completed,
        'running' => (int) $stats->running,
        'avg_score' => $stats->avg_score,
        'avg_iterations' => $stats->avg_iterations,
    ];
}
```

Also update the query to include `pending` status in filters.

**Step 2: Commit**

```bash
git add app/Livewire/LegalArtillery/Dashboard.php
git commit -m "perf: optimize dashboard stats to single aggregate query"
```

---

## Task 8: Add `pending` Status Support

**Why:** With async job dispatch, runs start as `pending` before the job picks them up. The current code only handles `running`, `completed`, `failed`.

**Files:**
- Modify: `resources/views/livewire/legal-artillery/dashboard.blade.php` (add pending badge)
- Modify: `resources/views/livewire/legal-artillery/run-details.blade.php` (handle pending state)
- Modify: `app/Agents/LegalArtilleryOrchestrator.php` (update status to `running` at start)

**Step 1: Add pending status handling**

Add `pending` status badge styling (gray/blue pulsing) to dashboard and run-details views. Update orchestrator to set `status = 'running'` at the beginning of `generate()`.

**Step 2: Commit**

```bash
git add resources/views/livewire/legal-artillery/dashboard.blade.php resources/views/livewire/legal-artillery/run-details.blade.php app/Agents/LegalArtilleryOrchestrator.php
git commit -m "feat: add pending status for queued document generation"
```

---

## Task 9: Final Integration Testing

**Step 1: Run all Legal Artillery tests**

```bash
./scripts/run-focused-tests.sh LegalArtilleryDashboardTest
./scripts/run-focused-tests.sh LlmClientTest
./scripts/run-focused-tests.sh LegalArtilleryAgentTest
```

**Step 2: Manual verification**

- Visit `/legal-artillery/` — verify dark theme
- Click "Nova Paljba" — verify dark theme form
- Start a generation — verify job dispatches and redirects to run details
- On run details — verify dark theme, pending status, monitor shows

**Step 3: Final commit if any adjustments**

---

## Execution Order

Tasks 1-2 fix the core functionality (401 error + async). Tasks 3-6 rebuild the UI. Tasks 7-8 are optimizations. Task 9 is verification.

**Critical path:** Task 1 → Task 2 → Task 3-6 (parallel) → Task 7-8 → Task 9

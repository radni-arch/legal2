# E-Predmet Widget Conversion: Artisan Commands to Dashboard Widget

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Convert epredmet artisan command metrics and fetch functionality into the existing Livewire EpredmetWidget, enabling users to monitor sync status, trigger batch fetches, and view metrics directly from the dashboard.

**Architecture:** Add a tabbed interface to the existing widget with three modes: (1) Single Case Lookup (existing), (2) Sync Status Dashboard showing all sync logs with metrics, (3) Batch Fetch panel to trigger court fetches. Use Livewire polling for live updates during active syncs, and dispatch jobs for background processing.

**Tech Stack:** Laravel Livewire 3, Alpine.js, Tailwind CSS, Laravel Jobs/Queues

---

## Overview

The existing `EpredmetWidget` handles single case lookups via GraphQL. The artisan commands (`epredmet:fetch`, `epredmet:fetch-all`, `epredmet:courts`) provide:

1. **Metrics**: fetched, saved, errors, validated, confirmed warrants, institutions resolved
2. **Progress tracking**: via SyncLog model with status (pending/running/completed/failed)
3. **Summary tables**: courts by status, year totals, grand totals

This conversion integrates all command functionality into the widget.

---

## Task 1: Add Sync Metrics Properties to EpredmetWidget

**Files:**
- Modify: `app/Http/Livewire/EpredmetWidget.php:25-68`
- Test: `tests/Feature/Livewire/EpredmetWidgetSyncStatusTest.php` (create)

**Step 1: Write failing test for sync status properties**

```php
<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\EpredmetWidget;
use App\Models\Court;
use App\Models\SyncLog;
use Livewire\Livewire;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EpredmetWidgetSyncStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_widget_has_active_tab_property(): void
    {
        Livewire::test(EpredmetWidget::class)
            ->assertSet('activeTab', 'lookup');
    }

    public function test_widget_can_switch_to_sync_status_tab(): void
    {
        Livewire::test(EpredmetWidget::class)
            ->set('activeTab', 'sync-status')
            ->assertSet('activeTab', 'sync-status');
    }

    public function test_widget_loads_sync_logs_when_switching_to_sync_status(): void
    {
        $court = Court::factory()->create(['name' => 'Test Court', 'external_id' => 1234]);
        SyncLog::create([
            'court_id' => $court->id,
            'register' => 'Pp Prz',
            'year' => 2025,
            'status' => 'completed',
            'total_fetched' => 100,
            'total_saved' => 95,
            'total_errors' => 5,
        ]);

        Livewire::test(EpredmetWidget::class)
            ->call('loadSyncStatus')
            ->assertSet('syncLogs.0.total_fetched', 100)
            ->assertSet('syncLogs.0.total_saved', 95);
    }

    public function test_widget_calculates_sync_summary_totals(): void
    {
        $court1 = Court::factory()->create(['external_id' => 1001]);
        $court2 = Court::factory()->create(['external_id' => 1002]);

        SyncLog::create(['court_id' => $court1->id, 'register' => 'Pp Prz', 'year' => 2025,
                        'status' => 'completed', 'total_fetched' => 100, 'total_saved' => 90, 'total_errors' => 10]);
        SyncLog::create(['court_id' => $court2->id, 'register' => 'Pp Prz', 'year' => 2025,
                        'status' => 'completed', 'total_fetched' => 200, 'total_saved' => 195, 'total_errors' => 5]);

        Livewire::test(EpredmetWidget::class)
            ->call('loadSyncStatus')
            ->assertSet('syncSummary.total_fetched', 300)
            ->assertSet('syncSummary.total_saved', 285)
            ->assertSet('syncSummary.total_errors', 15);
    }
}
```

**Step 2: Run test to verify it fails**

Run: `./scripts/run-focused-tests.sh EpredmetWidgetSyncStatusTest`
Expected: FAIL with "Property [activeTab] not found"

**Step 3: Add properties to EpredmetWidget**

In `app/Http/Livewire/EpredmetWidget.php`, add after line 55 (`public ?float $tookMs = null;`):

```php
    /**
     * Active tab: 'lookup', 'sync-status', or 'batch-fetch'
     */
    public string $activeTab = 'lookup';

    /**
     * Sync logs for status display
     */
    public array $syncLogs = [];

    /**
     * Summary statistics across all sync logs
     */
    public array $syncSummary = [
        'total_fetched' => 0,
        'total_saved' => 0,
        'total_errors' => 0,
        'courts_completed' => 0,
        'courts_running' => 0,
        'courts_failed' => 0,
        'courts_pending' => 0,
    ];

    /**
     * Available courts for selection
     */
    public array $courts = [];

    /**
     * Selected year filter for sync status
     */
    public int $filterYear;

    /**
     * Selected register filter
     */
    public string $filterRegister = 'Pp Prz';
```

**Step 4: Add loadSyncStatus method**

Add this method to `EpredmetWidget.php`:

```php
    /**
     * Load sync status data from SyncLog model
     */
    public function loadSyncStatus(): void
    {
        $query = \App\Models\SyncLog::with('court')
            ->where('register', $this->filterRegister)
            ->when($this->filterYear, fn($q) => $q->where('year', $this->filterYear))
            ->orderByDesc('updated_at');

        $this->syncLogs = $query->get()->map(fn($log) => [
            'id' => $log->id,
            'court_name' => $log->court?->short_name ?? 'Unknown',
            'court_id' => $log->court_id,
            'year' => $log->year,
            'register' => $log->register,
            'status' => $log->status,
            'total_fetched' => $log->total_fetched ?? 0,
            'total_saved' => $log->total_saved ?? 0,
            'total_errors' => $log->total_errors ?? 0,
            'last_case_number' => $log->last_case_number ?? 0,
            'duration_seconds' => $log->duration_seconds,
            'error_message' => $log->error_message,
            'started_at' => $log->started_at?->format('Y-m-d H:i'),
            'completed_at' => $log->completed_at?->format('Y-m-d H:i'),
        ])->toArray();

        // Calculate summary
        $this->syncSummary = [
            'total_fetched' => collect($this->syncLogs)->sum('total_fetched'),
            'total_saved' => collect($this->syncLogs)->sum('total_saved'),
            'total_errors' => collect($this->syncLogs)->sum('total_errors'),
            'courts_completed' => collect($this->syncLogs)->where('status', 'completed')->count(),
            'courts_running' => collect($this->syncLogs)->where('status', 'running')->count(),
            'courts_failed' => collect($this->syncLogs)->where('status', 'failed')->count(),
            'courts_pending' => collect($this->syncLogs)->where('status', 'pending')->count(),
        ];
    }
```

**Step 5: Initialize filterYear in mount()**

Update `mount()` method to initialize filterYear:

```php
    public function mount(): void
    {
        $this->filterYear = (int) date('Y');

        // ... existing code ...
    }
```

**Step 6: Run tests to verify they pass**

Run: `./scripts/run-focused-tests.sh EpredmetWidgetSyncStatusTest`
Expected: PASS (all 4 tests)

**Step 7: Commit**

```bash
git add app/Http/Livewire/EpredmetWidget.php tests/Feature/Livewire/EpredmetWidgetSyncStatusTest.php
git commit -m "$(cat <<'EOF'
feat(widget): Add sync status properties to EpredmetWidget

- Add activeTab property for tabbed interface navigation
- Add syncLogs array to store loaded sync log data
- Add syncSummary for aggregate statistics
- Add loadSyncStatus() method to query SyncLog model
- Add filterYear and filterRegister for filtering
EOF
)"
```

---

## Task 2: Add Court Loading and Batch Fetch Properties

**Files:**
- Modify: `app/Http/Livewire/EpredmetWidget.php`
- Test: `tests/Feature/Livewire/EpredmetWidgetBatchFetchTest.php` (create)

**Step 1: Write failing test for court loading**

```php
<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\EpredmetWidget;
use App\Models\Court;
use Livewire\Livewire;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EpredmetWidgetBatchFetchTest extends TestCase
{
    use RefreshDatabase;

    public function test_widget_can_load_courts(): void
    {
        Court::factory()->create(['name' => 'Općinski sud u Zagrebu', 'external_id' => 5001, 'level' => 1]);
        Court::factory()->create(['name' => 'Općinski sud u Splitu', 'external_id' => 5002, 'level' => 1]);
        Court::factory()->create(['name' => 'Županijski sud u Zagrebu', 'external_id' => 6001, 'level' => 2]);

        Livewire::test(EpredmetWidget::class)
            ->call('loadCourts')
            ->assertCount('courts', 2); // Only level 1 (municipal) courts
    }

    public function test_widget_has_batch_fetch_properties(): void
    {
        Livewire::test(EpredmetWidget::class)
            ->assertSet('batchCourtId', null)
            ->assertSet('batchYear', (int) date('Y'))
            ->assertSet('batchRegister', 'Pp Prz')
            ->assertSet('batchFetchStatus', null);
    }

    public function test_widget_validates_batch_fetch_inputs(): void
    {
        Livewire::test(EpredmetWidget::class)
            ->set('activeTab', 'batch-fetch')
            ->call('startBatchFetch')
            ->assertHasErrors(['batchCourtId' => 'required']);
    }

    public function test_widget_displays_batch_fetch_progress(): void
    {
        $court = Court::factory()->create(['external_id' => 5107, 'level' => 1]);

        Livewire::test(EpredmetWidget::class)
            ->set('batchCourtId', $court->id)
            ->set('batchYear', 2025)
            ->call('startBatchFetch')
            ->assertSet('batchFetchStatus', 'dispatched');
    }
}
```

**Step 2: Run test to verify it fails**

Run: `./scripts/run-focused-tests.sh EpredmetWidgetBatchFetchTest`
Expected: FAIL with "Property [batchCourtId] not found"

**Step 3: Add batch fetch properties**

Add after the sync status properties in `EpredmetWidget.php`:

```php
    /**
     * Batch fetch: selected court ID
     */
    public ?int $batchCourtId = null;

    /**
     * Batch fetch: year to fetch
     */
    public int $batchYear;

    /**
     * Batch fetch: register to fetch
     */
    public string $batchRegister = 'Pp Prz';

    /**
     * Batch fetch: status indicator (null, 'dispatched', 'running', 'completed', 'failed')
     */
    public ?string $batchFetchStatus = null;

    /**
     * Batch fetch: last error message
     */
    public ?string $batchFetchError = null;

    /**
     * Whether to enable live polling for active syncs
     */
    public bool $pollingEnabled = false;
```

**Step 4: Add loadCourts method**

```php
    /**
     * Load available courts (municipal level only)
     */
    public function loadCourts(): void
    {
        $this->courts = \App\Models\Court::where('level', 1)
            ->orderBy('name')
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'external_id' => $c->external_id,
                'name' => $c->short_name,
                'full_name' => $c->name,
            ])
            ->toArray();
    }
```

**Step 5: Add startBatchFetch method**

```php
    /**
     * Start a batch fetch job for selected court
     */
    public function startBatchFetch(): void
    {
        $this->validate([
            'batchCourtId' => 'required|exists:courts,id',
            'batchYear' => 'required|integer|min:2020|max:' . (date('Y') + 1),
            'batchRegister' => 'required|string',
        ]);

        $court = \App\Models\Court::find($this->batchCourtId);

        if (!$court) {
            $this->batchFetchError = 'Court not found';
            return;
        }

        // Dispatch job to run the fetch in background
        \App\Jobs\FetchCourtCasesJob::dispatch(
            $court->external_id,
            $this->batchYear,
            $this->batchRegister
        );

        $this->batchFetchStatus = 'dispatched';
        $this->batchFetchError = null;
        $this->pollingEnabled = true;

        // Reload sync status to show new running sync
        $this->loadSyncStatus();
    }
```

**Step 6: Update mount() to initialize batchYear**

```php
    public function mount(): void
    {
        $this->filterYear = (int) date('Y');
        $this->batchYear = (int) date('Y');

        // ... existing code ...
    }
```

**Step 7: Run tests to verify they pass**

Run: `./scripts/run-focused-tests.sh EpredmetWidgetBatchFetchTest`
Expected: PASS (4 tests)

**Step 8: Commit**

```bash
git add app/Http/Livewire/EpredmetWidget.php tests/Feature/Livewire/EpredmetWidgetBatchFetchTest.php
git commit -m "$(cat <<'EOF'
feat(widget): Add batch fetch properties and court loading

- Add batchCourtId, batchYear, batchRegister properties
- Add batchFetchStatus and batchFetchError for feedback
- Add loadCourts() method to get municipal courts
- Add startBatchFetch() method to dispatch background job
- Add pollingEnabled for live updates during fetch
EOF
)"
```

---

## Task 3: Create FetchCourtCasesJob

**Files:**
- Create: `app/Jobs/FetchCourtCasesJob.php`
- Test: `tests/Feature/Jobs/FetchCourtCasesJobTest.php` (create)

**Step 1: Write failing test**

```php
<?php

namespace Tests\Feature\Jobs;

use App\Jobs\FetchCourtCasesJob;
use App\Models\Court;
use App\Models\SyncLog;
use App\Services\EPredmetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class FetchCourtCasesJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_can_be_dispatched(): void
    {
        Queue::fake();

        FetchCourtCasesJob::dispatch(5107, 2025, 'Pp Prz');

        Queue::assertPushed(FetchCourtCasesJob::class, function ($job) {
            return $job->courtExternalId === 5107
                && $job->year === 2025
                && $job->register === 'Pp Prz';
        });
    }

    public function test_job_creates_sync_log_when_running(): void
    {
        $court = Court::factory()->create(['external_id' => 5107]);

        $mockService = Mockery::mock(EPredmetService::class);
        $mockService->shouldReceive('fetchAllCases')->andReturn(new \EmptyIterator());
        $this->app->instance(EPredmetService::class, $mockService);

        $job = new FetchCourtCasesJob(5107, 2025, 'Pp Prz');
        $job->handle(app(EPredmetService::class));

        $this->assertDatabaseHas('sync_logs', [
            'court_id' => $court->id,
            'year' => 2025,
            'register' => 'Pp Prz',
        ]);
    }

    public function test_job_updates_sync_log_on_completion(): void
    {
        $court = Court::factory()->create(['external_id' => 5107]);

        $mockService = Mockery::mock(EPredmetService::class);
        $mockService->shouldReceive('fetchAllCases')->andReturn(new \EmptyIterator());
        $this->app->instance(EPredmetService::class, $mockService);

        $job = new FetchCourtCasesJob(5107, 2025, 'Pp Prz');
        $job->handle(app(EPredmetService::class));

        $this->assertDatabaseHas('sync_logs', [
            'court_id' => $court->id,
            'status' => 'completed',
        ]);
    }
}
```

**Step 2: Run test to verify it fails**

Run: `./scripts/run-focused-tests.sh FetchCourtCasesJobTest`
Expected: FAIL with "Class 'App\Jobs\FetchCourtCasesJob' not found"

**Step 3: Create the job class**

```php
<?php

namespace App\Jobs;

use App\Models\Court;
use App\Models\CourtCase;
use App\Models\SyncLog;
use App\Services\EPredmetService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FetchCourtCasesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $courtExternalId;
    public int $year;
    public string $register;
    public int $maxCases;

    public int $timeout = 3600; // 1 hour max
    public int $tries = 1;

    public function __construct(
        int $courtExternalId,
        int $year,
        string $register = 'Pp Prz',
        int $maxCases = 0
    ) {
        $this->courtExternalId = $courtExternalId;
        $this->year = $year;
        $this->register = $register;
        $this->maxCases = $maxCases;
    }

    public function handle(EPredmetService $api): void
    {
        $court = Court::findByExternalId($this->courtExternalId);

        if (!$court) {
            Log::error("FetchCourtCasesJob: Court not found", ['external_id' => $this->courtExternalId]);
            return;
        }

        // Get or create sync log
        $syncLog = SyncLog::firstOrCreate(
            ['court_id' => $court->id, 'register' => $this->register, 'year' => $this->year],
            ['status' => 'pending']
        );

        $syncLog->markAsRunning();

        $fetched = 0;
        $saved = 0;
        $errors = 0;

        try {
            $generator = $api->fetchAllCases(
                $court->external_id,
                $this->register,
                $this->year,
                null, // no progress callback for job
                1,    // start from 1
                $this->maxCases
            );

            foreach ($generator as $caseData) {
                $fetched++;

                try {
                    DB::beginTransaction();
                    CourtCase::createFromApiResponse($caseData, $court->id);
                    $saved++;

                    // Update progress periodically
                    $caseNumber = $this->extractCaseNumber($caseData['oznakaBroj'] ?? '');
                    $syncLog->updateLastCaseNumber($caseNumber);

                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    $errors++;
                    Log::warning("FetchCourtCasesJob: Error saving case", [
                        'error' => $e->getMessage(),
                        'case' => $caseData['oznakaBroj'] ?? 'unknown'
                    ]);
                }

                // Update sync log every 50 cases
                if ($fetched % 50 === 0) {
                    $syncLog->update([
                        'total_fetched' => $fetched,
                        'total_saved' => $saved,
                        'total_errors' => $errors,
                    ]);
                }
            }

            // Final update
            $syncLog->update([
                'total_fetched' => $fetched,
                'total_saved' => $saved,
                'total_errors' => $errors,
            ]);
            $syncLog->markAsCompleted();

            Log::info("FetchCourtCasesJob: Completed", [
                'court' => $court->short_name,
                'year' => $this->year,
                'fetched' => $fetched,
                'saved' => $saved,
                'errors' => $errors,
            ]);

        } catch (\Exception $e) {
            $syncLog->update([
                'total_fetched' => $fetched,
                'total_saved' => $saved,
                'total_errors' => $errors,
            ]);
            $syncLog->markAsFailed($e->getMessage());

            Log::error("FetchCourtCasesJob: Failed", [
                'court' => $court->short_name ?? $this->courtExternalId,
                'error' => $e->getMessage(),
            ]);

            throw $e; // Re-throw for job retry handling
        }
    }

    protected function extractCaseNumber(string $caseNumber): int
    {
        if (preg_match('/-(\d+)\//', $caseNumber, $matches)) {
            return (int) $matches[1];
        }
        return 0;
    }
}
```

**Step 4: Run tests to verify they pass**

Run: `./scripts/run-focused-tests.sh FetchCourtCasesJobTest`
Expected: PASS (3 tests)

**Step 5: Commit**

```bash
git add app/Jobs/FetchCourtCasesJob.php tests/Feature/Jobs/FetchCourtCasesJobTest.php
git commit -m "$(cat <<'EOF'
feat(jobs): Add FetchCourtCasesJob for background case fetching

- Implements ShouldQueue for background processing
- Creates/updates SyncLog for progress tracking
- Handles partial failures gracefully
- Updates progress every 50 cases
- Logs completion and failure states
EOF
)"
```

---

## Task 4: Create Court Factory

**Files:**
- Create: `database/factories/CourtFactory.php`
- Test: Run existing tests to verify factory works

**Step 1: Check if factory exists**

Run: `ls database/factories/CourtFactory.php 2>/dev/null || echo "not found"`

**Step 2: Create factory if needed**

```php
<?php

namespace Database\Factories;

use App\Models\Court;
use Illuminate\Database\Eloquent\Factories\Factory;

class CourtFactory extends Factory
{
    protected $model = Court::class;

    public function definition(): array
    {
        $counties = ['Zagreb', 'Split', 'Rijeka', 'Osijek', 'Zadar', 'Pula', 'Slavonski Brod'];
        $county = $this->faker->randomElement($counties);

        return [
            'external_id' => $this->faker->unique()->numberBetween(5000, 9999),
            'name' => "Općinski sud u {$county}",
            'code' => strtoupper(substr($county, 0, 3)),
            'level' => 1,
            'county' => $county,
            'population' => $this->faker->numberBetween(10000, 500000),
        ];
    }

    public function municipal(): static
    {
        return $this->state(fn() => ['level' => 1]);
    }

    public function county(): static
    {
        return $this->state(fn() => ['level' => 2, 'name' => 'Županijski sud u ' . $this->faker->city]);
    }
}
```

**Step 3: Add HasFactory trait to Court model if missing**

In `app/Models/Court.php`, ensure it has:

```php
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Court extends Model
{
    use HasFactory;
    // ...
}
```

**Step 4: Run tests to verify factory works**

Run: `./scripts/run-focused-tests.sh EpredmetWidgetSyncStatusTest`
Expected: PASS

**Step 5: Commit**

```bash
git add database/factories/CourtFactory.php app/Models/Court.php
git commit -m "$(cat <<'EOF'
feat(factories): Add CourtFactory for testing

- Generate realistic Croatian court data
- Support municipal() and county() states
- Add HasFactory trait to Court model
EOF
)"
```

---

## Task 5: Update Blade View with Tabbed Interface

**Files:**
- Modify: `resources/views/livewire/epredmet-widget.blade.php`
- Test: `tests/Browser/EpredmetWidgetSyncTest.php` (create)

**Step 1: Write failing browser test**

```php
<?php

namespace Tests\Browser;

use App\Models\Court;
use App\Models\SyncLog;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class EpredmetWidgetSyncTest extends DuskTestCase
{
    public function test_widget_displays_tab_navigation(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/dashboard')
                ->waitFor('@epredmet-widget')
                ->click('@epredmet-widget-toggle')
                ->waitFor('[dusk="tab-lookup"]')
                ->assertVisible('[dusk="tab-lookup"]')
                ->assertVisible('[dusk="tab-sync-status"]')
                ->assertVisible('[dusk="tab-batch-fetch"]');
        });
    }

    public function test_can_switch_to_sync_status_tab(): void
    {
        $court = Court::factory()->create();
        SyncLog::create([
            'court_id' => $court->id,
            'register' => 'Pp Prz',
            'year' => 2025,
            'status' => 'completed',
            'total_fetched' => 150,
            'total_saved' => 145,
            'total_errors' => 5,
        ]);

        $this->browse(function (Browser $browser) {
            $browser->visit('/dashboard')
                ->waitFor('@epredmet-widget')
                ->click('@epredmet-widget-toggle')
                ->waitFor('[dusk="tab-sync-status"]')
                ->click('[dusk="tab-sync-status"]')
                ->waitFor('[dusk="sync-status-panel"]')
                ->assertSee('150')  // total_fetched
                ->assertSee('145'); // total_saved
        });
    }

    public function test_sync_status_shows_summary_metrics(): void
    {
        $court1 = Court::factory()->create();
        $court2 = Court::factory()->create();

        SyncLog::create(['court_id' => $court1->id, 'register' => 'Pp Prz', 'year' => 2025,
                        'status' => 'completed', 'total_fetched' => 100, 'total_saved' => 95, 'total_errors' => 5]);
        SyncLog::create(['court_id' => $court2->id, 'register' => 'Pp Prz', 'year' => 2025,
                        'status' => 'running', 'total_fetched' => 50, 'total_saved' => 48, 'total_errors' => 2]);

        $this->browse(function (Browser $browser) {
            $browser->visit('/dashboard')
                ->waitFor('@epredmet-widget')
                ->click('@epredmet-widget-toggle')
                ->click('[dusk="tab-sync-status"]')
                ->waitFor('[dusk="summary-total-fetched"]')
                ->assertSeeIn('[dusk="summary-total-fetched"]', '150')
                ->assertSeeIn('[dusk="summary-total-saved"]', '143')
                ->assertSeeIn('[dusk="summary-courts-completed"]', '1')
                ->assertSeeIn('[dusk="summary-courts-running"]', '1');
        });
    }
}
```

**Step 2: Run test to verify it fails**

Run: `php artisan dusk tests/Browser/EpredmetWidgetSyncTest.php`
Expected: FAIL with element not found

**Step 3: Update blade view with tabs**

Replace the content inside `<div id="epredmet-widget-content" ...>` (starting after line 44) with:

```blade
            <div class="px-3 py-2">
                {{-- Tab Navigation --}}
                <div class="flex gap-1 mb-3 border-b border-[var(--border)]" role="tablist">
                    <button type="button"
                            wire:click="$set('activeTab', 'lookup')"
                            class="px-3 py-1.5 text-sm font-medium rounded-t transition-colors {{ $activeTab === 'lookup' ? 'bg-[var(--bg)] text-[var(--fg)] border border-b-0 border-[var(--border)]' : 'text-[var(--muted)] hover:text-[var(--fg)]' }}"
                            role="tab"
                            aria-selected="{{ $activeTab === 'lookup' ? 'true' : 'false' }}"
                            dusk="tab-lookup">
                        Case Lookup
                    </button>
                    <button type="button"
                            wire:click="switchToSyncStatus"
                            class="px-3 py-1.5 text-sm font-medium rounded-t transition-colors {{ $activeTab === 'sync-status' ? 'bg-[var(--bg)] text-[var(--fg)] border border-b-0 border-[var(--border)]' : 'text-[var(--muted)] hover:text-[var(--fg)]' }}"
                            role="tab"
                            aria-selected="{{ $activeTab === 'sync-status' ? 'true' : 'false' }}"
                            dusk="tab-sync-status">
                        Sync Status
                        @if(collect($syncLogs)->where('status', 'running')->count() > 0)
                            <span class="ml-1 inline-flex items-center justify-center w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                        @endif
                    </button>
                    <button type="button"
                            wire:click="switchToBatchFetch"
                            class="px-3 py-1.5 text-sm font-medium rounded-t transition-colors {{ $activeTab === 'batch-fetch' ? 'bg-[var(--bg)] text-[var(--fg)] border border-b-0 border-[var(--border)]' : 'text-[var(--muted)] hover:text-[var(--fg)]' }}"
                            role="tab"
                            aria-selected="{{ $activeTab === 'batch-fetch' ? 'true' : 'false' }}"
                            dusk="tab-batch-fetch">
                        Batch Fetch
                    </button>
                </div>

                {{-- Tab: Case Lookup (existing functionality) --}}
                @if($activeTab === 'lookup')
                    @include('livewire.partials.epredmet-lookup')
                @endif

                {{-- Tab: Sync Status --}}
                @if($activeTab === 'sync-status')
                    @include('livewire.partials.epredmet-sync-status')
                @endif

                {{-- Tab: Batch Fetch --}}
                @if($activeTab === 'batch-fetch')
                    @include('livewire.partials.epredmet-batch-fetch')
                @endif
            </div>
```

**Step 4: Add tab switching methods to component**

Add to `EpredmetWidget.php`:

```php
    /**
     * Switch to sync status tab and load data
     */
    public function switchToSyncStatus(): void
    {
        $this->activeTab = 'sync-status';
        $this->loadSyncStatus();
    }

    /**
     * Switch to batch fetch tab and load courts
     */
    public function switchToBatchFetch(): void
    {
        $this->activeTab = 'batch-fetch';
        $this->loadCourts();
    }
```

**Step 5: Run tests**

Run: `php artisan dusk tests/Browser/EpredmetWidgetSyncTest.php`
Expected: Will still fail until partials are created (Task 6)

**Step 6: Commit partial progress**

```bash
git add resources/views/livewire/epredmet-widget.blade.php app/Http/Livewire/EpredmetWidget.php
git commit -m "$(cat <<'EOF'
feat(widget): Add tabbed interface to EpredmetWidget

- Add tab navigation for lookup/sync-status/batch-fetch
- Add switchToSyncStatus() and switchToBatchFetch() methods
- Show green indicator when syncs are running
- Prepare for partial view extraction
EOF
)"
```

---

## Task 6: Extract Lookup Partial View

**Files:**
- Create: `resources/views/livewire/partials/epredmet-lookup.blade.php`

**Step 1: Create partials directory**

Run: `mkdir -p resources/views/livewire/partials`

**Step 2: Extract existing lookup form to partial**

Move the existing form and data display (lines 46-264 from original view) to:

```blade
{{-- Lookup Form --}}
<form wire:submit.prevent="fetch" class="grid gap-2 md:grid-cols-12 items-end" dusk="fetch-form">
    <div class="md:col-span-3">
        <label for="sud" class="block text-[11px] muted font-medium">Sud ID</label>
        <input id="sud" type="text" wire:model.defer="sud" class="mt-1 dt-input epw-input" placeholder="5107" dusk="sud-input">
        @error('sud')<div class="text-xs" style="color:#fca5a5; margin-top:.25rem;" dusk="sud-error">{{ $message }}</div>@enderror
    </div>
    <div class="md:col-span-6">
        <label for="oznakaBroj" class="block text-[11px] muted font-medium">Oznaka/Broj</label>
        <input id="oznakaBroj" type="text" wire:model.defer="oznakaBroj" class="mt-1 dt-input epw-input" placeholder="Pp Prz-74/2025" dusk="oznaka-broj-input">
        @error('oznakaBroj')<div class="text-xs" style="color:#fca5a5; margin-top:.25rem;" dusk="oznaka-broj-error">{{ $message }}</div>@enderror
    </div>
    <div class="md:col-span-3" style="display:flex; gap:.35rem; align-items:end;">
        <button type="submit" class="btn-primary epw-btn" wire:loading.attr="disabled" title="Fetch" dusk="fetch-button">
            <svg wire:loading class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" xmlns="http://www.w3.org/2000/svg"><circle class="opacity-25" cx="12" cy="12" r="10" stroke-width="4"></circle><path class="opacity-75" d="M4 12a8 8 0 018-8" stroke-width="4" stroke-linecap="round"></path></svg>
            <span style="margin-left:.25rem;">Fetch</span>
        </button>
        <button type="button" class="btn-secondary epw-btn" wire:click="$set('data', null)" dusk="clear-button">Clear</button>
    </div>
</form>

@if ($error)
    <div class="mt-2 alert alert-error" style="padding:.5rem;" dusk="error-alert">
        <div class="font-semibold mb-1">Error</div>
        <div dusk="error-message">{{ $error }}</div>
        <div class="mt-1 text-xs" style="color:#fca5a5; opacity:.85;">Tip: ensure GRAPHQL_ENDPOINT and GRAPHQL_TOKEN are set, or that fallback hints match actual API.</div>
    </div>
@endif

<div class="mt-2" wire:loading.delay dusk="loading-indicator">
    <div class="card" style="padding:.5rem; background: var(--bg); color: var(--muted);">Loading…</div>
</div>

@if ($data)
    {{-- All existing data display code from original view (lines 79-263) --}}
    <div class="mt-2 grid gap-2" dusk="case-data">
        <!-- Summary grid -->
        <div class="grid gap-2 md:grid-cols-3">
            <div class="card epw-card" dusk="oznaka-broj-card">
                <div class="text-[10px] uppercase muted">Oznaka/Broj</div>
                <div class="font-semibold epw-title" dusk="oznaka-broj-value">{{ $data['oznakaBroj'] ?? '—' }}</div>
            </div>
            <div class="card epw-card" dusk="upisnik-card">
                <div class="text-[10px] uppercase muted">Upisnik</div>
                <div class="font-semibold epw-title" dusk="upisnik-value">{{ ($data['upisnikNaziv'] ?? null) ? ($data['upisnikNaziv'] . ' (' . ($data['upisnikOznaka'] ?? '—') . ')') : '—' }}</div>
            </div>
            <div class="card epw-card" dusk="vrsta-predmeta-card">
                <div class="text-[10px] uppercase muted">Vrsta predmeta</div>
                <div class="font-semibold epw-title" dusk="vrsta-predmeta-value">{{ $data['vrstaPredmeta'] ?? '—' }}</div>
            </div>
        </div>

        <div class="grid gap-2 md:grid-cols-3">
            <div class="card epw-card" dusk="vrsta-odluke-card">
                <div class="text-[10px] uppercase muted">Vrsta odluke</div>
                <div class="epw-text" dusk="vrsta-odluke-value">{{ $data['vrstaOdluke'] ?? '—' }}</div>
            </div>
            <div class="card epw-card" dusk="spis-status-card">
                <div class="text-[10px] uppercase muted">Spis status</div>
                <div class="epw-text" dusk="spis-status-value">Visi sud: <span class="font-medium">{{ ($data['spisNaVisemSudu'] ?? false) ? 'da' : 'ne' }}</span> · Izvan suda: <span class="font-medium">{{ ($data['spisIzvanSuda'] ?? false) ? 'da' : 'ne' }}</span></div>
            </div>
            <div class="card epw-card" dusk="last-update-card">
                <div class="text-[10px] uppercase muted">Last update</div>
                @if(!empty($data['lastUpdateTime'] ?? null))
                    <div class="epw-text" dusk="last-update-value"><span class="font-medium">{{ \Carbon\Carbon::parse($data['lastUpdateTime'])->format('Y-m-d H:i:s') }}</span></div>
                @else
                    <div class="epw-text" dusk="last-update-value"><span class="font-medium">-</span></div>
                @endif
            </div>
        </div>

        <!-- Dates panel -->
        <div class="card" style="border-radius:1rem; padding:0;" dusk="dates-panel">
            <div class="px-3 py-2 font-semibold" style="border-bottom:1px solid var(--border)">Ključni datumi</div>
            <div class="p-3 grid gap-2 md:grid-cols-3 epw-text">
                @foreach ($dateLabels as $k => $label)
                    <div class="card epw-card" dusk="date-{{ $k }}">
                        <div class="text-[10px] uppercase muted">{{ $label }}</div>
                        @if (empty($data[$k] ?? null))
                            <div class="font-medium" style="color: var(--fg)">—</div>
                        @else
                            <div class="font-medium" style="color: var(--fg)">{{ Carbon\Carbon::parse($data[$k])->format('Y-m-d H:i:s') }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        @if (!empty($data['rocista'] ?? []))
            <div class="card" style="overflow-x:auto; padding:0; border-radius:1rem;" dusk="rocista-panel">
                <div class="px-3 py-2 font-semibold" style="border-bottom:1px solid var(--border)">Ročišta</div>
                <table class="min-w-full epw-table" style="color: var(--fg)" dusk="rocista-table">
                    <thead style="background: var(--bg)">
                    <tr class="text-left" style="color: var(--muted)">
                        <th class="px-2 py-1.5">Vrsta</th>
                        <th class="px-2 py-1.5">St. početak</th>
                        <th class="px-2 py-1.5">St. završetak</th>
                        <th class="px-2 py-1.5">Pl. početak</th>
                        <th class="px-2 py-1.5">Pl. završetak</th>
                        <th class="px-2 py-1.5">Soba</th>
                        <th class="px-2 py-1.5">Odgoda</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach (($data['rocista'] ?? []) as $index => $r)
                        <tr style="border-top:1px solid var(--border)" dusk="rociste-{{ $index }}">
                            <td class="px-2 py-1.5">{{ $r['vrstaRadnje'] ?? '—' }}</td>
                            <td class="px-2 py-1.5">{{ $r['stPocetak'] ?? '—' }}</td>
                            <td class="px-2 py-1.5">{{ $r['stZavrsetak'] ?? '—' }}</td>
                            <td class="px-2 py-1.5">{{ $r['plPocetak'] ?? '—' }}</td>
                            <td class="px-2 py-1.5">{{ $r['plZavrsetak'] ?? '—' }}</td>
                            <td class="px-2 py-1.5">{{ ($r['sobanaziv'] ?? '—') . (($r['sobaoznaka'] ?? null) ? ' (' . $r['sobaoznaka'] . ')' : '') }}</td>
                            <td class="px-2 py-1.5">{{ ($r['odgoda'] ?? false) ? 'da' : 'ne' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div class="grid gap-2 md:grid-cols-2">
            @if (!empty($data['povezaniPredmeti'] ?? []))
                <div class="card" style="border-radius:1rem; padding:0;" dusk="povezani-predmeti-panel">
                    <div class="px-3 py-2 font-semibold" style="border-bottom:1px solid var(--border)">Povezani predmeti</div>
                    <ul class="epw-text" style="border-top:0;">
                        @foreach (($data['povezaniPredmeti'] ?? []) as $index => $pp)
                            <li class="px-3 py-2" style="border-top:1px solid var(--border)" dusk="povezani-predmet-{{ $index }}">
                                <div class="font-medium" style="color: var(--fg)">{{ $pp['vezaniOznakaBroj'] ?? ($pp['vezaniOznaka'] ?? '—') }}</div>
                                <div class="muted">{{ $pp['opis'] ?? '—' }}</div>
                                <div class="text-xs muted">{{ $pp['datumVeze'] ?? '—' }} · {{ $pp['tip'] ?? '' }}</div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (!empty($data['vjecnici'] ?? []))
                <div class="card" style="border-radius:1rem; padding:0;" dusk="vjecnici-panel">
                    <div class="px-3 py-2 font-semibold" style="border-bottom:1px solid var(--border)">Vijećnici</div>
                    <ul class="epw-text">
                        @foreach (($data['vjecnici'] ?? []) as $index => $v)
                            <li class="px-3 py-2" style="display:flex; align-items:center; justify-content:space-between; border-top:1px solid var(--border)" dusk="vjecnik-{{ $index }}">
                                <div class="font-medium" style="color: var(--fg)">{{ $v['ime'] ?? '—' }}</div>
                                <div class="muted">{{ $v['vrsta'] ?? '—' }}</div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        @if (!empty($data['stranke'] ?? []))
            <div x-data="{ open: true }" class="card" style="border-radius:1rem; padding:0;" dusk="stranke-panel">
                <button type="button"
                        class="w-full px-3 py-2 font-semibold"
                        style="border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between;"
                        @click="open = !open"
                        aria-controls="stranke-panel"
                        :aria-expanded="open.toString()"
                        dusk="stranke-toggle">
                    <span>Stranke</span>
                    <svg class="h-4 w-4 transition-transform" :class="open ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.08 1.04l-4.25 4.25a.75.75 0 01-1.06 0L5.21 8.27a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
                    </svg>
                </button>
                <div id="stranke-panel" x-show="open" x-transition x-cloak dusk="stranke-content">
                    <ul class="epw-text">
                        @foreach (($data['stranke'] ?? []) as $index => $s)
                            <li class="px-3 py-2" style="display:flex; align-items:center; justify-content:space-between; border-top:1px solid var(--border)" dusk="stranka-{{ $index }}">
                                <div class="font-medium" style="color: var(--fg)">{{ $s['naziv'] ?? '—' }}</div>
                                <div class="muted">{{ $s['nazivuloge'] ?? '—' }}</div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        @if (!empty($data['pismena'] ?? []))
            <div x-data="{ open: true }" class="card" style="border-radius:1rem; padding:0;" dusk="pismena-panel">
                <button type="button"
                        class="w-full px-3 py-2 font-semibold"
                        style="border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between;"
                        @click="open = !open"
                        aria-controls="pismena-panel"
                        :aria-expanded="open.toString()"
                        dusk="pismena-toggle">
                    <span>Pismena</span>
                    <svg class="h-4 w-4 transition-transform" :class="open ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.08 1.04l-4.25 4.25a.75.75 0 01-1.06 0L5.21 8.27a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
                    </svg>
                </button>
                <div id="pismena-panel" x-show="open" x-transition x-cloak class="overflow-x-auto" dusk="pismena-content">
                    <table class="min-w-full epw-table" style="color: var(--fg)" dusk="pismena-table">
                        <thead style="background: var(--bg)">
                        <tr class="text-left" style="color: var(--muted)">
                            <th class="px-2 py-1.5">Datum</th>
                            <th class="px-2 py-1.5">Vrsta</th>
                            <th class="px-2 py-1.5">Tip</th>
                            <th class="px-2 py-1.5">Podnositelj</th>
                            <th class="px-2 py-1.5">Prilozi</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach (($data['pismena'] ?? []) as $index => $p)
                            <tr style="border-top:1px solid var(--border)" dusk="pismeno-{{ $index }}">
                                <td class="px-2 py-1.5">{{ $p['datum'] ?? '—' }}</td>
                                <td class="px-2 py-1.5">{{ $p['vrsta'] ?? '—' }}</td>
                                <td class="px-2 py-1.5">{{ $p['tip'] ?? '—' }}</td>
                                <td class="px-2 py-1.5">{{ $p['podnositelj'] ?? '—' }}</td>
                                <td class="px-2 py-1.5">{{ $p['prilozi'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
@endif
```

**Step 3: Commit**

```bash
git add resources/views/livewire/partials/epredmet-lookup.blade.php
git commit -m "$(cat <<'EOF'
refactor(widget): Extract lookup form to partial view

- Move existing case lookup functionality to partials/epredmet-lookup.blade.php
- Maintains all existing dusk selectors and functionality
- Prepares for multi-tab interface
EOF
)"
```

---

## Task 7: Create Sync Status Partial View

**Files:**
- Create: `resources/views/livewire/partials/epredmet-sync-status.blade.php`

**Step 1: Create the sync status partial**

```blade
{{-- Sync Status Dashboard --}}
<div dusk="sync-status-panel" wire:poll.5s="loadSyncStatus">
    {{-- Filters --}}
    <div class="flex flex-wrap gap-3 mb-3">
        <div>
            <label class="block text-[11px] muted font-medium mb-1">Year</label>
            <select wire:model.live="filterYear" class="dt-input epw-input" dusk="filter-year">
                @for($y = date('Y'); $y >= 2020; $y--)
                    <option value="{{ $y }}">{{ $y }}</option>
                @endfor
            </select>
        </div>
        <div>
            <label class="block text-[11px] muted font-medium mb-1">Register</label>
            <select wire:model.live="filterRegister" class="dt-input epw-input" dusk="filter-register">
                <option value="Pp Prz">Pp Prz</option>
                <option value="Pp">Pp</option>
                <option value="K">K</option>
            </select>
        </div>
        <div class="flex items-end">
            <button type="button" wire:click="loadSyncStatus" class="btn-secondary epw-btn" dusk="refresh-sync-status">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                Refresh
            </button>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="grid gap-2 md:grid-cols-4 mb-3">
        <div class="card epw-card">
            <div class="text-[10px] uppercase muted">Total Fetched</div>
            <div class="font-semibold text-lg" style="color: var(--fg)" dusk="summary-total-fetched">{{ number_format($syncSummary['total_fetched']) }}</div>
        </div>
        <div class="card epw-card">
            <div class="text-[10px] uppercase muted">Total Saved</div>
            <div class="font-semibold text-lg" style="color: #22c55e" dusk="summary-total-saved">{{ number_format($syncSummary['total_saved']) }}</div>
        </div>
        <div class="card epw-card">
            <div class="text-[10px] uppercase muted">Total Errors</div>
            <div class="font-semibold text-lg" style="color: #ef4444" dusk="summary-total-errors">{{ number_format($syncSummary['total_errors']) }}</div>
        </div>
        <div class="card epw-card">
            <div class="text-[10px] uppercase muted">Courts Status</div>
            <div class="flex gap-2 text-xs mt-1">
                <span class="text-green-500" dusk="summary-courts-completed" title="Completed">{{ $syncSummary['courts_completed'] }} done</span>
                <span class="text-yellow-500" dusk="summary-courts-running" title="Running">{{ $syncSummary['courts_running'] }} running</span>
                <span class="text-red-500" dusk="summary-courts-failed" title="Failed">{{ $syncSummary['courts_failed'] }} failed</span>
            </div>
        </div>
    </div>

    {{-- Sync Logs Table --}}
    @if(count($syncLogs) > 0)
        <div class="card" style="overflow-x:auto; padding:0; border-radius:1rem;">
            <table class="min-w-full epw-table" style="color: var(--fg)" dusk="sync-logs-table">
                <thead style="background: var(--bg)">
                <tr class="text-left" style="color: var(--muted)">
                    <th class="px-2 py-1.5">Court</th>
                    <th class="px-2 py-1.5">Year</th>
                    <th class="px-2 py-1.5">Status</th>
                    <th class="px-2 py-1.5 text-right">Fetched</th>
                    <th class="px-2 py-1.5 text-right">Saved</th>
                    <th class="px-2 py-1.5 text-right">Errors</th>
                    <th class="px-2 py-1.5">Last Case</th>
                    <th class="px-2 py-1.5">Duration</th>
                    <th class="px-2 py-1.5">Updated</th>
                </tr>
                </thead>
                <tbody>
                @foreach($syncLogs as $index => $log)
                    <tr style="border-top:1px solid var(--border)" dusk="sync-log-{{ $index }}">
                        <td class="px-2 py-1.5 font-medium">{{ $log['court_name'] }}</td>
                        <td class="px-2 py-1.5">{{ $log['year'] }}</td>
                        <td class="px-2 py-1.5">
                            @switch($log['status'])
                                @case('completed')
                                    <span class="inline-flex items-center gap-1 text-green-500">
                                        <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                        Done
                                    </span>
                                    @break
                                @case('running')
                                    <span class="inline-flex items-center gap-1 text-yellow-500">
                                        <svg class="h-3 w-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                        Running
                                    </span>
                                    @break
                                @case('failed')
                                    <span class="inline-flex items-center gap-1 text-red-500" title="{{ $log['error_message'] ?? '' }}">
                                        <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                                        Failed
                                    </span>
                                    @break
                                @default
                                    <span class="text-gray-500">Pending</span>
                            @endswitch
                        </td>
                        <td class="px-2 py-1.5 text-right">{{ number_format($log['total_fetched']) }}</td>
                        <td class="px-2 py-1.5 text-right text-green-500">{{ number_format($log['total_saved']) }}</td>
                        <td class="px-2 py-1.5 text-right text-red-500">{{ $log['total_errors'] }}</td>
                        <td class="px-2 py-1.5">#{{ $log['last_case_number'] }}</td>
                        <td class="px-2 py-1.5">
                            @if($log['duration_seconds'])
                                {{ gmdate('H:i:s', $log['duration_seconds']) }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-2 py-1.5 text-xs muted">{{ $log['completed_at'] ?? $log['started_at'] ?? '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="card epw-card text-center muted" dusk="no-sync-logs">
            No sync logs found for {{ $filterYear }} / {{ $filterRegister }}
        </div>
    @endif
</div>
```

**Step 2: Update loadSyncStatus to respond to filter changes**

Add to `EpredmetWidget.php`:

```php
    /**
     * React to filter changes by reloading data
     */
    public function updatedFilterYear(): void
    {
        if ($this->activeTab === 'sync-status') {
            $this->loadSyncStatus();
        }
    }

    public function updatedFilterRegister(): void
    {
        if ($this->activeTab === 'sync-status') {
            $this->loadSyncStatus();
        }
    }
```

**Step 3: Run browser tests**

Run: `php artisan dusk tests/Browser/EpredmetWidgetSyncTest.php`
Expected: PASS (or partial pass - batch fetch tests will fail until Task 8)

**Step 4: Commit**

```bash
git add resources/views/livewire/partials/epredmet-sync-status.blade.php app/Http/Livewire/EpredmetWidget.php
git commit -m "$(cat <<'EOF'
feat(widget): Add sync status dashboard partial

- Display summary metrics (fetched/saved/errors)
- Show court status breakdown (completed/running/failed)
- Table with detailed sync log entries
- Year and register filters with live updates
- Auto-polling every 5 seconds for live updates
- Status indicators with icons and colors
EOF
)"
```

---

## Task 8: Create Batch Fetch Partial View

**Files:**
- Create: `resources/views/livewire/partials/epredmet-batch-fetch.blade.php`

**Step 1: Create the batch fetch partial**

```blade
{{-- Batch Fetch Panel --}}
<div dusk="batch-fetch-panel">
    <form wire:submit.prevent="startBatchFetch" class="space-y-3">
        {{-- Court Selection --}}
        <div>
            <label class="block text-[11px] muted font-medium mb-1">Select Court</label>
            <select wire:model="batchCourtId" class="dt-input epw-input w-full" dusk="batch-court-select">
                <option value="">-- Select a court --</option>
                @foreach($courts as $court)
                    <option value="{{ $court['id'] }}">{{ $court['name'] }} (ID: {{ $court['external_id'] }})</option>
                @endforeach
            </select>
            @error('batchCourtId')<div class="text-xs mt-1" style="color:#fca5a5;">{{ $message }}</div>@enderror
        </div>

        {{-- Year and Register --}}
        <div class="grid gap-3 md:grid-cols-2">
            <div>
                <label class="block text-[11px] muted font-medium mb-1">Year</label>
                <select wire:model="batchYear" class="dt-input epw-input w-full" dusk="batch-year-select">
                    @for($y = date('Y'); $y >= 2020; $y--)
                        <option value="{{ $y }}">{{ $y }}</option>
                    @endfor
                </select>
                @error('batchYear')<div class="text-xs mt-1" style="color:#fca5a5;">{{ $message }}</div>@enderror
            </div>
            <div>
                <label class="block text-[11px] muted font-medium mb-1">Register</label>
                <select wire:model="batchRegister" class="dt-input epw-input w-full" dusk="batch-register-select">
                    <option value="Pp Prz">Pp Prz (Prekršaji)</option>
                    <option value="Pp">Pp (Parnični)</option>
                    <option value="K">K (Kazneni)</option>
                </select>
                @error('batchRegister')<div class="text-xs mt-1" style="color:#fca5a5;">{{ $message }}</div>@enderror
            </div>
        </div>

        {{-- Status Display --}}
        @if($batchFetchStatus)
            <div class="p-3 rounded-lg {{ $batchFetchStatus === 'dispatched' ? 'bg-green-500/10 border border-green-500/30' : ($batchFetchStatus === 'failed' ? 'bg-red-500/10 border border-red-500/30' : 'bg-yellow-500/10 border border-yellow-500/30') }}" dusk="batch-status-message">
                @switch($batchFetchStatus)
                    @case('dispatched')
                        <div class="flex items-center gap-2 text-green-500">
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            <span class="font-medium">Fetch job dispatched successfully!</span>
                        </div>
                        <p class="text-sm mt-1 text-green-400/80">Switch to "Sync Status" tab to monitor progress.</p>
                        @break
                    @case('failed')
                        <div class="flex items-center gap-2 text-red-500">
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                            <span class="font-medium">Failed to dispatch job</span>
                        </div>
                        @if($batchFetchError)
                            <p class="text-sm mt-1 text-red-400/80">{{ $batchFetchError }}</p>
                        @endif
                        @break
                @endswitch
            </div>
        @endif

        {{-- Submit Button --}}
        <div class="flex gap-2">
            <button type="submit"
                    class="btn-primary epw-btn flex items-center gap-2"
                    wire:loading.attr="disabled"
                    dusk="start-batch-fetch">
                <svg wire:loading class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                <span>Start Fetch</span>
            </button>
            @if($batchFetchStatus)
                <button type="button"
                        wire:click="$set('batchFetchStatus', null)"
                        class="btn-secondary epw-btn"
                        dusk="clear-batch-status">
                    Clear
                </button>
            @endif
        </div>
    </form>

    {{-- Quick Actions --}}
    <div class="mt-4 pt-4 border-t border-[var(--border)]">
        <div class="text-[11px] uppercase muted font-medium mb-2">Quick Actions</div>
        <div class="flex flex-wrap gap-2">
            <button type="button"
                    wire:click="fetchAllPending"
                    class="btn-secondary epw-btn text-xs"
                    dusk="fetch-all-pending">
                Fetch All Pending
            </button>
            <button type="button"
                    wire:click="retryFailed"
                    class="btn-secondary epw-btn text-xs"
                    dusk="retry-failed">
                Retry Failed
            </button>
        </div>
    </div>

    {{-- Help Text --}}
    <div class="mt-4 text-xs muted">
        <p><strong>Note:</strong> Fetching runs in the background via queue. Large courts may take several minutes.</p>
        <p class="mt-1">Rate limiting: 100ms between batches, 1 case batch = 20 records.</p>
    </div>
</div>
```

**Step 2: Add quick action methods to component**

Add to `EpredmetWidget.php`:

```php
    /**
     * Dispatch fetch jobs for all pending courts
     */
    public function fetchAllPending(): void
    {
        $pendingLogs = \App\Models\SyncLog::where('year', $this->batchYear)
            ->where('register', $this->batchRegister)
            ->where('status', 'pending')
            ->with('court')
            ->get();

        $courts = \App\Models\Court::where('level', 1)
            ->whereNotIn('id', $pendingLogs->pluck('court_id'))
            ->get();

        // Dispatch jobs for courts without any sync log
        foreach ($courts as $court) {
            \App\Jobs\FetchCourtCasesJob::dispatch(
                $court->external_id,
                $this->batchYear,
                $this->batchRegister
            );
        }

        // Dispatch jobs for pending status courts
        foreach ($pendingLogs as $log) {
            if ($log->court) {
                \App\Jobs\FetchCourtCasesJob::dispatch(
                    $log->court->external_id,
                    $this->batchYear,
                    $this->batchRegister
                );
            }
        }

        $this->batchFetchStatus = 'dispatched';
        $this->pollingEnabled = true;
    }

    /**
     * Retry all failed sync jobs
     */
    public function retryFailed(): void
    {
        $failedLogs = \App\Models\SyncLog::where('year', $this->batchYear)
            ->where('register', $this->batchRegister)
            ->where('status', 'failed')
            ->with('court')
            ->get();

        foreach ($failedLogs as $log) {
            if ($log->court) {
                // Reset status to pending before dispatching
                $log->update(['status' => 'pending', 'error_message' => null]);

                \App\Jobs\FetchCourtCasesJob::dispatch(
                    $log->court->external_id,
                    $this->batchYear,
                    $this->batchRegister
                );
            }
        }

        $this->batchFetchStatus = 'dispatched';
        $this->pollingEnabled = true;
        $this->loadSyncStatus();
    }
```

**Step 3: Run all widget tests**

Run: `./scripts/run-focused-tests.sh EpredmetWidget`
Expected: PASS

**Step 4: Commit**

```bash
git add resources/views/livewire/partials/epredmet-batch-fetch.blade.php app/Http/Livewire/EpredmetWidget.php
git commit -m "$(cat <<'EOF'
feat(widget): Add batch fetch panel with quick actions

- Court selection dropdown from loaded courts
- Year and register selection
- Status feedback for dispatched/failed jobs
- fetchAllPending() for bulk court fetching
- retryFailed() to retry failed syncs
- Help text with rate limiting info
EOF
)"
```

---

## Task 9: Add Browser Tests for Batch Fetch

**Files:**
- Create: `tests/Browser/EpredmetWidgetBatchFetchDuskTest.php`

**Step 1: Write comprehensive browser tests**

```php
<?php

namespace Tests\Browser;

use App\Models\Court;
use App\Models\SyncLog;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class EpredmetWidgetBatchFetchDuskTest extends DuskTestCase
{
    public function test_batch_fetch_tab_loads_courts(): void
    {
        Court::factory()->count(3)->create(['level' => 1]);

        $this->browse(function (Browser $browser) {
            $browser->visit('/dashboard')
                ->waitFor('@epredmet-widget')
                ->click('@epredmet-widget-toggle')
                ->waitFor('[dusk="tab-batch-fetch"]')
                ->click('[dusk="tab-batch-fetch"]')
                ->waitFor('[dusk="batch-fetch-panel"]')
                ->assertSelectHasOptions('batch-court-select', function ($options) {
                    return count($options) >= 3;
                });
        });
    }

    public function test_can_start_batch_fetch(): void
    {
        $court = Court::factory()->create(['external_id' => 5107, 'level' => 1]);

        $this->browse(function (Browser $browser) use ($court) {
            $browser->visit('/dashboard')
                ->waitFor('@epredmet-widget')
                ->click('@epredmet-widget-toggle')
                ->click('[dusk="tab-batch-fetch"]')
                ->waitFor('[dusk="batch-fetch-panel"]')
                ->select('[dusk="batch-court-select"]', $court->id)
                ->click('[dusk="start-batch-fetch"]')
                ->waitFor('[dusk="batch-status-message"]')
                ->assertSee('Fetch job dispatched');
        });
    }

    public function test_validation_shows_error_when_no_court_selected(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/dashboard')
                ->waitFor('@epredmet-widget')
                ->click('@epredmet-widget-toggle')
                ->click('[dusk="tab-batch-fetch"]')
                ->waitFor('[dusk="batch-fetch-panel"]')
                ->click('[dusk="start-batch-fetch"]')
                ->waitForText('required')
                ->assertSee('required');
        });
    }

    public function test_can_switch_between_all_tabs(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/dashboard')
                ->waitFor('@epredmet-widget')
                ->click('@epredmet-widget-toggle')
                // Start at lookup
                ->waitFor('[dusk="tab-lookup"]')
                ->assertVisible('[dusk="fetch-form"]')
                // Go to sync status
                ->click('[dusk="tab-sync-status"]')
                ->waitFor('[dusk="sync-status-panel"]')
                ->assertVisible('[dusk="sync-status-panel"]')
                // Go to batch fetch
                ->click('[dusk="tab-batch-fetch"]')
                ->waitFor('[dusk="batch-fetch-panel"]')
                ->assertVisible('[dusk="batch-fetch-panel"]')
                // Back to lookup
                ->click('[dusk="tab-lookup"]')
                ->waitFor('[dusk="fetch-form"]')
                ->assertVisible('[dusk="fetch-form"]');
        });
    }
}
```

**Step 2: Run browser tests**

Run: `php artisan dusk tests/Browser/EpredmetWidgetBatchFetchDuskTest.php`
Expected: PASS (all 4 tests)

**Step 3: Commit**

```bash
git add tests/Browser/EpredmetWidgetBatchFetchDuskTest.php
git commit -m "$(cat <<'EOF'
test(browser): Add Dusk tests for batch fetch functionality

- Test court loading in dropdown
- Test batch fetch job dispatch
- Test validation error display
- Test tab switching between all modes
EOF
)"
```

---

## Task 10: Final Integration and Cleanup

**Files:**
- Modify: `app/Http/Livewire/EpredmetWidget.php` (final review)
- Modify: `resources/views/livewire/epredmet-widget.blade.php` (final review)

**Step 1: Ensure all imports are present in EpredmetWidget.php**

Verify top of file has:

```php
<?php

namespace App\Http\Livewire;

use App\GraphQL\AutoDiscovery\Exceptions\GraphQLQueryException;
use App\GraphQL\AutoDiscovery\GraphQLAutoClient;
use App\Jobs\FetchCourtCasesJob;
use App\Models\Court;
use App\Models\SyncLog;
use Carbon\Carbon;
use Livewire\Component;
```

**Step 2: Run full test suite**

Run: `./scripts/run-focused-tests.sh EpredmetWidget`
Expected: PASS (all unit, feature, and browser tests)

**Step 3: Run existing browser tests to ensure no regression**

Run: `php artisan dusk tests/Browser/EpredmetWidgetTest.php`
Expected: PASS (all original tests still work)

**Step 4: Final commit**

```bash
git add -A
git commit -m "$(cat <<'EOF'
feat(widget): Complete epredmet artisan-to-widget conversion

Integration complete:
- Single case lookup (existing)
- Sync status dashboard with live metrics
- Batch fetch panel with court selection
- Quick actions: fetch all pending, retry failed
- Live polling for active syncs
- Full test coverage (unit, feature, browser)

Converts functionality from:
- epredmet:fetch
- epredmet:fetch-all
- epredmet:courts

Into unified dashboard widget experience.
EOF
)"
```

**Step 5: Push to remote**

```bash
git push -u origin claude/epredmet-widget-conversion-8XHrn
```

---

## Summary

This plan converts the epredmet artisan command functionality into the existing `EpredmetWidget` Livewire component:

| Task | Description | Tests |
|------|-------------|-------|
| 1 | Add sync status properties | 4 unit tests |
| 2 | Add batch fetch properties | 4 unit tests |
| 3 | Create FetchCourtCasesJob | 3 unit tests |
| 4 | Create Court factory | Existing tests |
| 5 | Update blade with tabs | Browser tests |
| 6 | Extract lookup partial | Existing tests |
| 7 | Create sync status partial | 3 browser tests |
| 8 | Create batch fetch partial | Quick action tests |
| 9 | Browser tests for batch | 4 browser tests |
| 10 | Final integration | Full suite |

**Total new tests:** ~18 tests across unit, feature, and browser suites.

**Key features delivered:**
- Tabbed interface (Lookup / Sync Status / Batch Fetch)
- Live metrics dashboard with polling
- Court selection dropdown
- Job dispatch for background processing
- Quick actions for bulk operations
- Year/register filtering
- Progress tracking via SyncLog model

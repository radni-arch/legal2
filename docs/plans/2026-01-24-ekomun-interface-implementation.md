# E-Komunikacije Interface Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Create a complete web interface for the e-komunikacije (EKOM) API integration, enabling users to view, manage, and interact with Croatian court electronic communication data through a dashboard, automated sync system, and submission creator.

**Architecture:** Multi-phase implementation using Livewire components following existing codebase patterns. The main EkomDashboard component will have tabbed navigation for predmeti (cases), podnesci (submissions), and otpravci (dispatches). Background jobs handle sync operations with real-time status updates via polling. Internal API endpoints wrap the EkomService for programmatic access.

**Tech Stack:** Laravel Livewire 3, Alpine.js, Tailwind CSS, Laravel Jobs/Queues, Laravel Scheduler

---

## Overview

This plan implements a complete interface for the existing EKOM API integration:

| Phase | Description | Tasks |
|-------|-------------|-------|
| 1 | Foundation | Routes, base component, layout |
| 2 | Predmeti Dashboard | List, filter, view cases |
| 3 | Podnesci Management | List, create submissions |
| 4 | Otpravci Handling | List, confirm receipt |
| 5 | Document Downloads | Download UI and preview |
| 6 | Automated Sync | Scheduled jobs, status tracking |
| 7 | Internal API | REST endpoints for programmatic access |

**Existing Infrastructure Used:**
- `EkomService` - Business logic layer
- `EkomApiClient` - API communication
- `EkomPredmet`, `EkomPodnesak`, `EkomOtpravak` - Eloquent models
- 9 Artisan commands (sync, DND, download, create)

---

## Phase 1: Foundation

### Task 1.1: Create Route Registration

**Files:**
- Modify: `routes/web.php`

**Step 1: Add EKOM routes to web.php**

Add after existing routes (around line 106):

```php
// E-Komunikacije Dashboard Routes
Route::middleware(['auth'])->prefix('ekom')->name('ekom.')->group(function () {
    Route::get('/', \App\Http\Livewire\EkomDashboard::class)->name('dashboard');
    Route::get('/predmeti', \App\Http\Livewire\EkomPredmetiList::class)->name('predmeti');
    Route::get('/predmeti/{remoteId}', \App\Http\Livewire\EkomPredmetDetail::class)->name('predmeti.show');
    Route::get('/podnesci', \App\Http\Livewire\EkomPodnesciList::class)->name('podnesci');
    Route::get('/podnesci/create', \App\Http\Livewire\EkomPodnesakCreate::class)->name('podnesci.create');
    Route::get('/otpravci', \App\Http\Livewire\EkomOtpravciList::class)->name('otpravci');
    Route::get('/sync-status', \App\Http\Livewire\EkomSyncStatus::class)->name('sync-status');
});
```

**Step 2: Commit**

```bash
git add routes/web.php
git commit -m "$(cat <<'EOF'
feat(routes): Add e-komunikacije dashboard routes

- Add /ekom prefix for all EKOM routes
- Register routes for dashboard, predmeti, podnesci, otpravci
- All routes protected by auth middleware
EOF
)"
```

---

### Task 1.2: Create Base Dashboard Component

**Files:**
- Create: `app/Http/Livewire/EkomDashboard.php`
- Create: `resources/views/livewire/ekom-dashboard.blade.php`
- Test: `tests/Feature/Livewire/EkomDashboardTest.php`

**Step 1: Write failing test**

```php
<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\EkomDashboard;
use App\Models\EkomOtpravak;
use App\Models\EkomPodnesak;
use App\Models\EkomPredmet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EkomDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_successfully(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('ekom.dashboard'))
            ->assertOk()
            ->assertSeeLivewire(EkomDashboard::class);
    }

    public function test_dashboard_displays_statistics(): void
    {
        $user = User::factory()->create();

        EkomPredmet::factory()->count(5)->create();
        EkomPodnesak::factory()->count(3)->create();
        EkomOtpravak::factory()->count(2)->create();

        Livewire::actingAs($user)
            ->test(EkomDashboard::class)
            ->assertSet('stats.predmeti_count', 5)
            ->assertSet('stats.podnesci_count', 3)
            ->assertSet('stats.otpravci_count', 2);
    }

    public function test_dashboard_shows_recent_activity(): void
    {
        $user = User::factory()->create();

        $predmet = EkomPredmet::factory()->create([
            'oznaka' => 'Pp-123/2025',
            'last_synced_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test(EkomDashboard::class)
            ->assertSee('Pp-123/2025');
    }

    public function test_dashboard_requires_authentication(): void
    {
        $this->get(route('ekom.dashboard'))
            ->assertRedirect(route('login'));
    }
}
```

**Step 2: Run test to verify it fails**

Run: `./scripts/run-focused-tests.sh EkomDashboardTest`
Expected: FAIL with "Class 'App\Http\Livewire\EkomDashboard' not found"

**Step 3: Create the component class**

```php
<?php

namespace App\Http\Livewire;

use App\Models\EkomOtpravak;
use App\Models\EkomPodnesak;
use App\Models\EkomPredmet;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

class EkomDashboard extends Component
{
    /**
     * Dashboard statistics
     */
    public array $stats = [
        'predmeti_count' => 0,
        'podnesci_count' => 0,
        'otpravci_count' => 0,
        'pending_otpravci' => 0,
        'draft_podnesci' => 0,
        'last_sync' => null,
    ];

    /**
     * Recent activity items
     */
    public array $recentActivity = [];

    /**
     * Quick action status
     */
    public ?string $actionStatus = null;
    public ?string $actionMessage = null;

    public function mount(): void
    {
        $this->loadStats();
        $this->loadRecentActivity();
    }

    /**
     * Load dashboard statistics
     */
    public function loadStats(): void
    {
        $this->stats = Cache::remember('ekom_dashboard_stats', 60, function () {
            return [
                'predmeti_count' => EkomPredmet::count(),
                'podnesci_count' => EkomPodnesak::count(),
                'otpravci_count' => EkomOtpravak::count(),
                'pending_otpravci' => EkomOtpravak::whereNull('vrijeme_primitka')->count(),
                'draft_podnesci' => EkomPodnesak::where('status', 'NACRT')->count(),
                'last_sync' => EkomPredmet::max('last_synced_at'),
            ];
        });
    }

    /**
     * Load recent activity
     */
    public function loadRecentActivity(): void
    {
        $recentPredmeti = EkomPredmet::orderByDesc('last_synced_at')
            ->take(5)
            ->get()
            ->map(fn($p) => [
                'type' => 'predmet',
                'icon' => '📁',
                'title' => $p->oznaka,
                'subtitle' => 'Case synced',
                'timestamp' => $p->last_synced_at,
                'url' => route('ekom.predmeti.show', $p->remote_id),
            ]);

        $recentPodnesci = EkomPodnesak::orderByDesc('last_synced_at')
            ->take(5)
            ->get()
            ->map(fn($p) => [
                'type' => 'podnesak',
                'icon' => '📤',
                'title' => "Podnesak #{$p->remote_id}",
                'subtitle' => $p->status,
                'timestamp' => $p->last_synced_at,
                'url' => route('ekom.podnesci'),
            ]);

        $recentOtpravci = EkomOtpravak::orderByDesc('last_synced_at')
            ->take(5)
            ->get()
            ->map(fn($o) => [
                'type' => 'otpravak',
                'icon' => '📥',
                'title' => "Otpravak #{$o->remote_id}",
                'subtitle' => $o->status,
                'timestamp' => $o->last_synced_at,
                'url' => route('ekom.otpravci'),
            ]);

        $this->recentActivity = collect()
            ->merge($recentPredmeti)
            ->merge($recentPodnesci)
            ->merge($recentOtpravci)
            ->sortByDesc('timestamp')
            ->take(10)
            ->values()
            ->toArray();
    }

    /**
     * Trigger manual sync for all entities
     */
    public function triggerFullSync(): void
    {
        try {
            \App\Jobs\EkomSyncAllJob::dispatch();
            $this->actionStatus = 'success';
            $this->actionMessage = 'Full sync job dispatched. Check Sync Status for progress.';
        } catch (\Throwable $e) {
            $this->actionStatus = 'error';
            $this->actionMessage = 'Failed to dispatch sync: ' . $e->getMessage();
        }
    }

    /**
     * Refresh statistics
     */
    public function refreshStats(): void
    {
        Cache::forget('ekom_dashboard_stats');
        $this->loadStats();
        $this->loadRecentActivity();
    }

    public function render()
    {
        return view('livewire.ekom-dashboard')
            ->layout('layouts.app', ['title' => 'E-Komunikacije Dashboard']);
    }
}
```

**Step 4: Create the view**

```blade
<div class="container mx-auto px-4 py-6">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold" style="color: var(--fg)">E-Komunikacije</h1>
            <p class="text-sm muted">Croatian Court Electronic Communication System</p>
        </div>
        <div class="flex gap-2">
            <button wire:click="refreshStats" class="btn-secondary flex items-center gap-2">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                Refresh
            </button>
            <button wire:click="triggerFullSync" class="btn-primary flex items-center gap-2" wire:loading.attr="disabled">
                <svg wire:loading wire:target="triggerFullSync" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                <svg wire:loading.remove wire:target="triggerFullSync" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                </svg>
                Sync All
            </button>
        </div>
    </div>

    {{-- Action Status --}}
    @if($actionMessage)
        <div class="mb-4 p-3 rounded-lg {{ $actionStatus === 'success' ? 'bg-green-500/10 border border-green-500/30 text-green-500' : 'bg-red-500/10 border border-red-500/30 text-red-500' }}">
            {{ $actionMessage }}
            <button wire:click="$set('actionMessage', null)" class="ml-2 text-xs underline">Dismiss</button>
        </div>
    @endif

    {{-- Statistics Cards --}}
    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4 mb-6">
        {{-- Predmeti Card --}}
        <a href="{{ route('ekom.predmeti') }}" class="card p-4 hover:border-blue-500/50 transition-colors">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm muted">Predmeti (Cases)</p>
                    <p class="text-2xl font-bold" style="color: var(--fg)">{{ number_format($stats['predmeti_count']) }}</p>
                </div>
                <div class="text-3xl">📁</div>
            </div>
        </a>

        {{-- Podnesci Card --}}
        <a href="{{ route('ekom.podnesci') }}" class="card p-4 hover:border-blue-500/50 transition-colors">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm muted">Podnesci (Submissions)</p>
                    <p class="text-2xl font-bold" style="color: var(--fg)">{{ number_format($stats['podnesci_count']) }}</p>
                    @if($stats['draft_podnesci'] > 0)
                        <p class="text-xs text-yellow-500">{{ $stats['draft_podnesci'] }} drafts</p>
                    @endif
                </div>
                <div class="text-3xl">📤</div>
            </div>
        </a>

        {{-- Otpravci Card --}}
        <a href="{{ route('ekom.otpravci') }}" class="card p-4 hover:border-blue-500/50 transition-colors">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm muted">Otpravci (Dispatches)</p>
                    <p class="text-2xl font-bold" style="color: var(--fg)">{{ number_format($stats['otpravci_count']) }}</p>
                    @if($stats['pending_otpravci'] > 0)
                        <p class="text-xs text-orange-500">{{ $stats['pending_otpravci'] }} pending</p>
                    @endif
                </div>
                <div class="text-3xl">📥</div>
            </div>
        </a>

        {{-- Sync Status Card --}}
        <a href="{{ route('ekom.sync-status') }}" class="card p-4 hover:border-blue-500/50 transition-colors">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm muted">Last Sync</p>
                    @if($stats['last_sync'])
                        <p class="text-lg font-medium" style="color: var(--fg)">{{ \Carbon\Carbon::parse($stats['last_sync'])->diffForHumans() }}</p>
                    @else
                        <p class="text-lg font-medium muted">Never</p>
                    @endif
                </div>
                <div class="text-3xl">🔄</div>
            </div>
        </a>
    </div>

    {{-- Quick Navigation --}}
    <div class="grid gap-4 md:grid-cols-3 mb-6">
        <a href="{{ route('ekom.predmeti') }}" class="card p-4 flex items-center gap-3 hover:border-blue-500/50 transition-colors">
            <div class="p-2 rounded-lg bg-blue-500/10">
                <svg class="h-6 w-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </div>
            <div>
                <p class="font-medium" style="color: var(--fg)">Browse Cases</p>
                <p class="text-sm muted">View and search predmeti</p>
            </div>
        </a>

        <a href="{{ route('ekom.podnesci.create') }}" class="card p-4 flex items-center gap-3 hover:border-green-500/50 transition-colors">
            <div class="p-2 rounded-lg bg-green-500/10">
                <svg class="h-6 w-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
            </div>
            <div>
                <p class="font-medium" style="color: var(--fg)">Create Submission</p>
                <p class="text-sm muted">New podnesak with attachments</p>
            </div>
        </a>

        <a href="{{ route('ekom.otpravci') }}?filter=pending" class="card p-4 flex items-center gap-3 hover:border-orange-500/50 transition-colors">
            <div class="p-2 rounded-lg bg-orange-500/10">
                <svg class="h-6 w-6 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                </svg>
            </div>
            <div>
                <p class="font-medium" style="color: var(--fg)">Pending Receipts</p>
                <p class="text-sm muted">Confirm otpravak deliveries</p>
            </div>
        </a>
    </div>

    {{-- Recent Activity --}}
    <div class="card p-0 overflow-hidden">
        <div class="px-4 py-3 border-b border-[var(--border)]">
            <h2 class="font-semibold" style="color: var(--fg)">Recent Activity</h2>
        </div>
        @if(count($recentActivity) > 0)
            <ul class="divide-y divide-[var(--border)]">
                @foreach($recentActivity as $activity)
                    <li>
                        <a href="{{ $activity['url'] }}" class="flex items-center gap-3 px-4 py-3 hover:bg-[var(--bg)] transition-colors">
                            <span class="text-xl">{{ $activity['icon'] }}</span>
                            <div class="flex-1 min-w-0">
                                <p class="font-medium truncate" style="color: var(--fg)">{{ $activity['title'] }}</p>
                                <p class="text-sm muted">{{ $activity['subtitle'] }}</p>
                            </div>
                            <span class="text-xs muted whitespace-nowrap">
                                {{ $activity['timestamp'] ? \Carbon\Carbon::parse($activity['timestamp'])->diffForHumans() : '—' }}
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @else
            <div class="p-8 text-center muted">
                <p>No recent activity</p>
                <p class="text-sm mt-1">Sync data to see activity here</p>
            </div>
        @endif
    </div>
</div>
```

**Step 5: Run tests to verify they pass**

Run: `./scripts/run-focused-tests.sh EkomDashboardTest`
Expected: PASS (4 tests)

**Step 6: Commit**

```bash
git add app/Http/Livewire/EkomDashboard.php resources/views/livewire/ekom-dashboard.blade.php tests/Feature/Livewire/EkomDashboardTest.php
git commit -m "$(cat <<'EOF'
feat(ekom): Add main dashboard component

- Display statistics for predmeti, podnesci, otpravci
- Quick navigation cards for common actions
- Recent activity feed with timestamps
- Trigger full sync action
- Cache statistics for 60 seconds
EOF
)"
```

---

### Task 1.3: Create Model Factories

**Files:**
- Create: `database/factories/EkomPredmetFactory.php`
- Create: `database/factories/EkomPodnesakFactory.php`
- Create: `database/factories/EkomOtpravakFactory.php`

**Step 1: Create EkomPredmetFactory**

```php
<?php

namespace Database\Factories;

use App\Models\EkomPredmet;
use Illuminate\Database\Eloquent\Factories\Factory;

class EkomPredmetFactory extends Factory
{
    protected $model = EkomPredmet::class;

    public function definition(): array
    {
        $year = $this->faker->numberBetween(2020, 2026);
        $number = $this->faker->numberBetween(1, 9999);

        return [
            'remote_id' => $this->faker->unique()->numberBetween(100000, 999999),
            'oznaka' => "Pp-{$number}/{$year}",
            'status' => $this->faker->randomElement(['U_RADU', 'ARHIVIRAN', 'RIJEŠEN']),
            'sud_remote_id' => $this->faker->numberBetween(5000, 6000),
            'do_not_disturb' => $this->faker->boolean(10),
            'data' => [
                'brojPredmeta' => $number,
                'godinaPredmeta' => $year,
                'sudNaziv' => 'Općinski sud u Zagrebu',
            ],
            'last_synced_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
        ];
    }

    public function active(): static
    {
        return $this->state(fn() => ['status' => 'U_RADU']);
    }

    public function archived(): static
    {
        return $this->state(fn() => ['status' => 'ARHIVIRAN']);
    }

    public function withDnd(): static
    {
        return $this->state(fn() => ['do_not_disturb' => true]);
    }
}
```

**Step 2: Create EkomPodnesakFactory**

```php
<?php

namespace Database\Factories;

use App\Models\EkomPodnesak;
use Illuminate\Database\Eloquent\Factories\Factory;

class EkomPodnesakFactory extends Factory
{
    protected $model = EkomPodnesak::class;

    public function definition(): array
    {
        return [
            'remote_id' => $this->faker->unique()->numberBetween(100000, 999999),
            'status' => $this->faker->randomElement(['NACRT', 'POSLAN', 'PRIMLJEN', 'ODBIJEN']),
            'sud_remote_id' => $this->faker->numberBetween(5000, 6000),
            'vrsta_podneska_remote_id' => $this->faker->numberBetween(1, 50),
            'vrijeme_slanja' => $this->faker->optional()->dateTimeBetween('-30 days', 'now'),
            'vrijeme_primitka' => $this->faker->optional()->dateTimeBetween('-30 days', 'now'),
            'data' => [
                'nazivPodneska' => $this->faker->sentence(3),
                'sudNaziv' => 'Općinski sud u Zagrebu',
            ],
            'last_synced_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn() => [
            'status' => 'NACRT',
            'vrijeme_slanja' => null,
            'vrijeme_primitka' => null,
        ]);
    }

    public function sent(): static
    {
        return $this->state(fn() => [
            'status' => 'POSLAN',
            'vrijeme_slanja' => now(),
        ]);
    }

    public function received(): static
    {
        return $this->state(fn() => [
            'status' => 'PRIMLJEN',
            'vrijeme_slanja' => now()->subDays(2),
            'vrijeme_primitka' => now(),
        ]);
    }
}
```

**Step 3: Create EkomOtpravakFactory**

```php
<?php

namespace Database\Factories;

use App\Models\EkomOtpravak;
use Illuminate\Database\Eloquent\Factories\Factory;

class EkomOtpravakFactory extends Factory
{
    protected $model = EkomOtpravak::class;

    public function definition(): array
    {
        return [
            'remote_id' => $this->faker->unique()->numberBetween(100000, 999999),
            'status' => $this->faker->randomElement(['POSLAN', 'PRIMLJEN', 'ISTEKAO']),
            'predmet_remote_id' => $this->faker->numberBetween(100000, 999999),
            'vrijeme_slanja' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'vrijeme_primitka' => $this->faker->optional()->dateTimeBetween('-30 days', 'now'),
            'primljen_zbog_isteka_roka' => $this->faker->boolean(20),
            'data' => [
                'naslovOtpravka' => $this->faker->sentence(4),
                'sudNaziv' => 'Općinski sud u Zagrebu',
            ],
            'last_synced_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn() => [
            'status' => 'POSLAN',
            'vrijeme_primitka' => null,
            'primljen_zbog_isteka_roka' => false,
        ]);
    }

    public function received(): static
    {
        return $this->state(fn() => [
            'status' => 'PRIMLJEN',
            'vrijeme_primitka' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn() => [
            'status' => 'ISTEKAO',
            'vrijeme_primitka' => now(),
            'primljen_zbog_isteka_roka' => true,
        ]);
    }
}
```

**Step 4: Add HasFactory traits to models**

Verify each model has `use HasFactory;` trait.

**Step 5: Run tests**

Run: `./scripts/run-focused-tests.sh EkomDashboardTest`
Expected: PASS

**Step 6: Commit**

```bash
git add database/factories/Ekom*.php app/Models/Ekom*.php
git commit -m "$(cat <<'EOF'
feat(factories): Add EKOM model factories for testing

- EkomPredmetFactory with active/archived/withDnd states
- EkomPodnesakFactory with draft/sent/received states
- EkomOtpravakFactory with pending/received/expired states
- Realistic Croatian court data generation
EOF
)"
```

---

## Phase 2: Predmeti Dashboard

### Task 2.1: Create Predmeti List Component

**Files:**
- Create: `app/Http/Livewire/EkomPredmetiList.php`
- Create: `resources/views/livewire/ekom-predmeti-list.blade.php`
- Test: `tests/Feature/Livewire/EkomPredmetiListTest.php`

**Step 1: Write failing test**

```php
<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\EkomPredmetiList;
use App\Models\EkomPredmet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EkomPredmetiListTest extends TestCase
{
    use RefreshDatabase;

    public function test_component_renders_successfully(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('ekom.predmeti'))
            ->assertOk()
            ->assertSeeLivewire(EkomPredmetiList::class);
    }

    public function test_displays_predmeti_list(): void
    {
        $user = User::factory()->create();
        EkomPredmet::factory()->create(['oznaka' => 'Pp-123/2025']);

        Livewire::actingAs($user)
            ->test(EkomPredmetiList::class)
            ->assertSee('Pp-123/2025');
    }

    public function test_can_search_predmeti(): void
    {
        $user = User::factory()->create();
        EkomPredmet::factory()->create(['oznaka' => 'Pp-123/2025']);
        EkomPredmet::factory()->create(['oznaka' => 'K-456/2025']);

        Livewire::actingAs($user)
            ->test(EkomPredmetiList::class)
            ->set('search', 'Pp-123')
            ->assertSee('Pp-123/2025')
            ->assertDontSee('K-456/2025');
    }

    public function test_can_filter_by_status(): void
    {
        $user = User::factory()->create();
        EkomPredmet::factory()->active()->create(['oznaka' => 'Active-1/2025']);
        EkomPredmet::factory()->archived()->create(['oznaka' => 'Archived-1/2025']);

        Livewire::actingAs($user)
            ->test(EkomPredmetiList::class)
            ->set('statusFilter', 'U_RADU')
            ->assertSee('Active-1/2025')
            ->assertDontSee('Archived-1/2025');
    }

    public function test_pagination_works(): void
    {
        $user = User::factory()->create();
        EkomPredmet::factory()->count(30)->create();

        Livewire::actingAs($user)
            ->test(EkomPredmetiList::class)
            ->assertSet('perPage', 20)
            ->call('nextPage')
            ->assertSet('page', 2);
    }

    public function test_can_toggle_dnd(): void
    {
        $user = User::factory()->create();
        $predmet = EkomPredmet::factory()->create(['do_not_disturb' => false]);

        // Mock the service
        $this->mock(\App\Contracts\External\EkomServiceInterface::class)
            ->shouldReceive('turnOnDndPredmet')
            ->with($predmet->remote_id)
            ->andReturn(true);

        Livewire::actingAs($user)
            ->test(EkomPredmetiList::class)
            ->call('toggleDnd', $predmet->remote_id)
            ->assertDispatched('success');
    }
}
```

**Step 2: Run test to verify it fails**

Run: `./scripts/run-focused-tests.sh EkomPredmetiListTest`
Expected: FAIL

**Step 3: Create the component class**

```php
<?php

namespace App\Http\Livewire;

use App\Contracts\External\EkomServiceInterface;
use App\Models\EkomPredmet;
use Livewire\Component;
use Livewire\WithPagination;

class EkomPredmetiList extends Component
{
    use WithPagination;

    /**
     * Search query
     */
    public string $search = '';

    /**
     * Status filter
     */
    public string $statusFilter = '';

    /**
     * DND filter
     */
    public string $dndFilter = '';

    /**
     * Sort field
     */
    public string $sortField = 'last_synced_at';

    /**
     * Sort direction
     */
    public string $sortDirection = 'desc';

    /**
     * Items per page
     */
    public int $perPage = 20;

    /**
     * Query string binding
     */
    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => ''],
        'dndFilter' => ['except' => ''],
        'sortField' => ['except' => 'last_synced_at'],
        'sortDirection' => ['except' => 'desc'],
    ];

    /**
     * Reset page on filter change
     */
    public function updated($property): void
    {
        if (in_array($property, ['search', 'statusFilter', 'dndFilter'])) {
            $this->resetPage();
        }
    }

    /**
     * Sort by field
     */
    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    /**
     * Toggle DND for a predmet
     */
    public function toggleDnd(int $remoteId): void
    {
        try {
            $predmet = EkomPredmet::where('remote_id', $remoteId)->firstOrFail();
            $service = app(EkomServiceInterface::class);

            if ($predmet->do_not_disturb) {
                $service->turnOffDndPredmet($remoteId);
                $predmet->update(['do_not_disturb' => false]);
                $this->dispatch('success', message: 'DND disabled for ' . $predmet->oznaka);
            } else {
                $service->turnOnDndPredmet($remoteId);
                $predmet->update(['do_not_disturb' => true]);
                $this->dispatch('success', message: 'DND enabled for ' . $predmet->oznaka);
            }
        } catch (\Throwable $e) {
            $this->dispatch('error', message: 'Failed to toggle DND: ' . $e->getMessage());
        }
    }

    /**
     * Get predmeti with filters
     */
    public function getPredmetiProperty()
    {
        return EkomPredmet::query()
            ->when($this->search, fn($q) => $q->where('oznaka', 'like', "%{$this->search}%"))
            ->when($this->statusFilter, fn($q) => $q->where('status', $this->statusFilter))
            ->when($this->dndFilter === 'enabled', fn($q) => $q->where('do_not_disturb', true))
            ->when($this->dndFilter === 'disabled', fn($q) => $q->where('do_not_disturb', false))
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->perPage);
    }

    /**
     * Get status options
     */
    public function getStatusOptionsProperty(): array
    {
        return [
            '' => 'All Statuses',
            'U_RADU' => 'U radu (Active)',
            'ARHIVIRAN' => 'Arhiviran (Archived)',
            'RIJEŠEN' => 'Riješen (Resolved)',
        ];
    }

    public function render()
    {
        return view('livewire.ekom-predmeti-list', [
            'predmeti' => $this->predmeti,
            'statusOptions' => $this->statusOptions,
        ])->layout('layouts.app', ['title' => 'Predmeti - E-Komunikacije']);
    }
}
```

**Step 4: Create the view**

```blade
<div class="container mx-auto px-4 py-6">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <nav class="text-sm muted mb-1">
                <a href="{{ route('ekom.dashboard') }}" class="hover:text-[var(--fg)]">E-Komunikacije</a>
                <span class="mx-2">/</span>
                <span>Predmeti</span>
            </nav>
            <h1 class="text-2xl font-bold" style="color: var(--fg)">Predmeti (Cases)</h1>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('ekom.sync-status') }}" class="btn-secondary">Sync Status</a>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card p-4 mb-4">
        <div class="grid gap-4 md:grid-cols-4">
            {{-- Search --}}
            <div>
                <label class="block text-xs muted font-medium mb-1">Search</label>
                <input type="text"
                       wire:model.live.debounce.300ms="search"
                       class="dt-input w-full"
                       placeholder="Search by oznaka...">
            </div>

            {{-- Status Filter --}}
            <div>
                <label class="block text-xs muted font-medium mb-1">Status</label>
                <select wire:model.live="statusFilter" class="dt-input w-full">
                    @foreach($statusOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            {{-- DND Filter --}}
            <div>
                <label class="block text-xs muted font-medium mb-1">Do Not Disturb</label>
                <select wire:model.live="dndFilter" class="dt-input w-full">
                    <option value="">All</option>
                    <option value="enabled">DND Enabled</option>
                    <option value="disabled">DND Disabled</option>
                </select>
            </div>

            {{-- Per Page --}}
            <div>
                <label class="block text-xs muted font-medium mb-1">Per Page</label>
                <select wire:model.live="perPage" class="dt-input w-full">
                    <option value="10">10</option>
                    <option value="20">20</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Results Count --}}
    <div class="text-sm muted mb-2">
        Showing {{ $predmeti->firstItem() ?? 0 }} - {{ $predmeti->lastItem() ?? 0 }} of {{ $predmeti->total() }} predmeti
    </div>

    {{-- Table --}}
    <div class="card overflow-hidden">
        <table class="min-w-full" style="color: var(--fg)">
            <thead style="background: var(--bg)">
                <tr class="text-left text-sm" style="color: var(--muted)">
                    <th class="px-4 py-3 cursor-pointer hover:text-[var(--fg)]" wire:click="sortBy('oznaka')">
                        Oznaka
                        @if($sortField === 'oznaka')
                            <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </th>
                    <th class="px-4 py-3 cursor-pointer hover:text-[var(--fg)]" wire:click="sortBy('status')">
                        Status
                        @if($sortField === 'status')
                            <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </th>
                    <th class="px-4 py-3">Sud</th>
                    <th class="px-4 py-3">DND</th>
                    <th class="px-4 py-3 cursor-pointer hover:text-[var(--fg)]" wire:click="sortBy('last_synced_at')">
                        Last Synced
                        @if($sortField === 'last_synced_at')
                            <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[var(--border)]">
                @forelse($predmeti as $predmet)
                    <tr class="hover:bg-[var(--bg)] transition-colors">
                        <td class="px-4 py-3">
                            <a href="{{ route('ekom.predmeti.show', $predmet->remote_id) }}" class="font-medium hover:underline">
                                {{ $predmet->oznaka }}
                            </a>
                        </td>
                        <td class="px-4 py-3">
                            @switch($predmet->status)
                                @case('U_RADU')
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-green-500/10 text-green-500">Active</span>
                                    @break
                                @case('ARHIVIRAN')
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-gray-500/10 text-gray-500">Archived</span>
                                    @break
                                @case('RIJEŠEN')
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-blue-500/10 text-blue-500">Resolved</span>
                                    @break
                                @default
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-yellow-500/10 text-yellow-500">{{ $predmet->status }}</span>
                            @endswitch
                        </td>
                        <td class="px-4 py-3 text-sm muted">
                            {{ $predmet->data['sudNaziv'] ?? 'ID: ' . $predmet->sud_remote_id }}
                        </td>
                        <td class="px-4 py-3">
                            <button wire:click="toggleDnd({{ $predmet->remote_id }})"
                                    class="p-1 rounded hover:bg-[var(--bg)] transition-colors"
                                    title="{{ $predmet->do_not_disturb ? 'Disable DND' : 'Enable DND' }}">
                                @if($predmet->do_not_disturb)
                                    <span class="text-yellow-500">🔕</span>
                                @else
                                    <span class="text-gray-400">🔔</span>
                                @endif
                            </button>
                        </td>
                        <td class="px-4 py-3 text-sm muted">
                            {{ $predmet->last_synced_at?->diffForHumans() ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('ekom.predmeti.show', $predmet->remote_id) }}"
                               class="btn-secondary text-xs">
                                View
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center muted">
                            No predmeti found
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    <div class="mt-4">
        {{ $predmeti->links() }}
    </div>
</div>
```

**Step 5: Run tests**

Run: `./scripts/run-focused-tests.sh EkomPredmetiListTest`
Expected: PASS

**Step 6: Commit**

```bash
git add app/Http/Livewire/EkomPredmetiList.php resources/views/livewire/ekom-predmeti-list.blade.php tests/Feature/Livewire/EkomPredmetiListTest.php
git commit -m "$(cat <<'EOF'
feat(ekom): Add predmeti list component with filtering

- Search by oznaka
- Filter by status and DND state
- Sortable columns
- Pagination with configurable page size
- Toggle DND directly from list
- URL query string binding for shareable links
EOF
)"
```

---

### Task 2.2: Create Predmet Detail Component

**Files:**
- Create: `app/Http/Livewire/EkomPredmetDetail.php`
- Create: `resources/views/livewire/ekom-predmet-detail.blade.php`
- Test: `tests/Feature/Livewire/EkomPredmetDetailTest.php`

**Step 1: Write failing test**

```php
<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\EkomPredmetDetail;
use App\Models\EkomPredmet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EkomPredmetDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_component_renders_successfully(): void
    {
        $user = User::factory()->create();
        $predmet = EkomPredmet::factory()->create();

        $this->actingAs($user)
            ->get(route('ekom.predmeti.show', $predmet->remote_id))
            ->assertOk()
            ->assertSeeLivewire(EkomPredmetDetail::class);
    }

    public function test_displays_predmet_details(): void
    {
        $user = User::factory()->create();
        $predmet = EkomPredmet::factory()->create([
            'oznaka' => 'Pp-999/2025',
            'data' => ['sudNaziv' => 'Test Court', 'brojPredmeta' => 999],
        ]);

        Livewire::actingAs($user)
            ->test(EkomPredmetDetail::class, ['remoteId' => $predmet->remote_id])
            ->assertSee('Pp-999/2025')
            ->assertSee('Test Court');
    }

    public function test_shows_404_for_invalid_remote_id(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('ekom.predmeti.show', 99999999))
            ->assertNotFound();
    }

    public function test_can_toggle_dnd(): void
    {
        $user = User::factory()->create();
        $predmet = EkomPredmet::factory()->create(['do_not_disturb' => false]);

        $this->mock(\App\Contracts\External\EkomServiceInterface::class)
            ->shouldReceive('turnOnDndPredmet')
            ->once()
            ->andReturn(true);

        Livewire::actingAs($user)
            ->test(EkomPredmetDetail::class, ['remoteId' => $predmet->remote_id])
            ->call('toggleDnd')
            ->assertDispatched('success');

        $this->assertTrue($predmet->fresh()->do_not_disturb);
    }

    public function test_can_download_documents(): void
    {
        $user = User::factory()->create();
        $predmet = EkomPredmet::factory()->create();

        $this->mock(\App\Contracts\External\EkomServiceInterface::class)
            ->shouldReceive('download')
            ->with('predmet-dokumenti', \Mockery::any(), \Mockery::any())
            ->once()
            ->andReturn('/tmp/test.zip');

        Livewire::actingAs($user)
            ->test(EkomPredmetDetail::class, ['remoteId' => $predmet->remote_id])
            ->call('downloadDocuments')
            ->assertDispatched('success');
    }
}
```

**Step 2: Run test to verify it fails**

Run: `./scripts/run-focused-tests.sh EkomPredmetDetailTest`
Expected: FAIL

**Step 3: Create the component class**

```php
<?php

namespace App\Http\Livewire;

use App\Contracts\External\EkomServiceInterface;
use App\Models\EkomPredmet;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EkomPredmetDetail extends Component
{
    /**
     * Predmet remote ID
     */
    public int $remoteId;

    /**
     * Predmet model
     */
    public ?EkomPredmet $predmet = null;

    /**
     * Action status
     */
    public ?string $actionStatus = null;
    public ?string $actionMessage = null;

    public function mount(int $remoteId): void
    {
        $this->remoteId = $remoteId;
        $this->predmet = EkomPredmet::where('remote_id', $remoteId)->first();

        if (!$this->predmet) {
            abort(404, 'Predmet not found');
        }
    }

    /**
     * Toggle DND for this predmet
     */
    public function toggleDnd(): void
    {
        try {
            $service = app(EkomServiceInterface::class);

            if ($this->predmet->do_not_disturb) {
                $service->turnOffDndPredmet($this->remoteId);
                $this->predmet->update(['do_not_disturb' => false]);
                $this->dispatch('success', message: 'DND disabled');
            } else {
                $service->turnOnDndPredmet($this->remoteId);
                $this->predmet->update(['do_not_disturb' => true]);
                $this->dispatch('success', message: 'DND enabled');
            }

            $this->predmet->refresh();
        } catch (\Throwable $e) {
            $this->dispatch('error', message: 'Failed: ' . $e->getMessage());
        }
    }

    /**
     * Download all documents for this predmet
     */
    public function downloadDocuments(): void
    {
        try {
            $service = app(EkomServiceInterface::class);
            $filename = "predmet-{$this->remoteId}-documents.zip";
            $path = storage_path("app/downloads/{$filename}");

            // Ensure directory exists
            if (!is_dir(dirname($path))) {
                mkdir(dirname($path), 0755, true);
            }

            $service->download('predmet-dokumenti', [
                'predmetId' => $this->remoteId,
            ], $path);

            $this->dispatch('success', message: 'Documents downloaded successfully');
            $this->dispatch('download', url: route('ekom.download', ['file' => $filename]));
        } catch (\Throwable $e) {
            $this->dispatch('error', message: 'Download failed: ' . $e->getMessage());
        }
    }

    /**
     * Refresh predmet data from API
     */
    public function refreshFromApi(): void
    {
        try {
            $service = app(EkomServiceInterface::class);
            $service->syncPredmeti(['id' => $this->remoteId], 1);
            $this->predmet->refresh();
            $this->dispatch('success', message: 'Data refreshed from API');
        } catch (\Throwable $e) {
            $this->dispatch('error', message: 'Refresh failed: ' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.ekom-predmet-detail')
            ->layout('layouts.app', ['title' => $this->predmet?->oznaka . ' - E-Komunikacije']);
    }
}
```

**Step 4: Create the view**

```blade
<div class="container mx-auto px-4 py-6">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <nav class="text-sm muted mb-1">
                <a href="{{ route('ekom.dashboard') }}" class="hover:text-[var(--fg)]">E-Komunikacije</a>
                <span class="mx-2">/</span>
                <a href="{{ route('ekom.predmeti') }}" class="hover:text-[var(--fg)]">Predmeti</a>
                <span class="mx-2">/</span>
                <span>{{ $predmet->oznaka }}</span>
            </nav>
            <h1 class="text-2xl font-bold" style="color: var(--fg)">{{ $predmet->oznaka }}</h1>
        </div>
        <div class="flex gap-2">
            <button wire:click="toggleDnd"
                    class="btn-secondary flex items-center gap-2"
                    title="{{ $predmet->do_not_disturb ? 'Disable DND' : 'Enable DND' }}">
                @if($predmet->do_not_disturb)
                    <span>🔕</span> DND On
                @else
                    <span>🔔</span> DND Off
                @endif
            </button>
            <button wire:click="refreshFromApi" class="btn-secondary flex items-center gap-2" wire:loading.attr="disabled">
                <svg wire:loading wire:target="refreshFromApi" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                <svg wire:loading.remove wire:target="refreshFromApi" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                Refresh
            </button>
            <button wire:click="downloadDocuments" class="btn-primary flex items-center gap-2" wire:loading.attr="disabled">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                Download
            </button>
        </div>
    </div>

    {{-- Main Info --}}
    <div class="grid gap-4 md:grid-cols-3 mb-6">
        <div class="card p-4">
            <p class="text-xs muted uppercase mb-1">Status</p>
            @switch($predmet->status)
                @case('U_RADU')
                    <p class="text-lg font-semibold text-green-500">U radu (Active)</p>
                    @break
                @case('ARHIVIRAN')
                    <p class="text-lg font-semibold text-gray-500">Arhiviran (Archived)</p>
                    @break
                @case('RIJEŠEN')
                    <p class="text-lg font-semibold text-blue-500">Riješen (Resolved)</p>
                    @break
                @default
                    <p class="text-lg font-semibold" style="color: var(--fg)">{{ $predmet->status }}</p>
            @endswitch
        </div>
        <div class="card p-4">
            <p class="text-xs muted uppercase mb-1">Sud (Court)</p>
            <p class="text-lg font-semibold" style="color: var(--fg)">{{ $predmet->data['sudNaziv'] ?? 'ID: ' . $predmet->sud_remote_id }}</p>
        </div>
        <div class="card p-4">
            <p class="text-xs muted uppercase mb-1">Last Synced</p>
            <p class="text-lg font-semibold" style="color: var(--fg)">{{ $predmet->last_synced_at?->format('Y-m-d H:i') ?? '—' }}</p>
            <p class="text-xs muted">{{ $predmet->last_synced_at?->diffForHumans() }}</p>
        </div>
    </div>

    {{-- Raw Data --}}
    <div class="card overflow-hidden">
        <div class="px-4 py-3 border-b border-[var(--border)] flex items-center justify-between">
            <h2 class="font-semibold" style="color: var(--fg)">Raw API Data</h2>
            <span class="text-xs muted">Remote ID: {{ $predmet->remote_id }}</span>
        </div>
        <div class="p-4 overflow-x-auto">
            <pre class="text-sm" style="color: var(--fg)">{{ json_encode($predmet->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        </div>
    </div>
</div>
```

**Step 5: Run tests**

Run: `./scripts/run-focused-tests.sh EkomPredmetDetailTest`
Expected: PASS

**Step 6: Commit**

```bash
git add app/Http/Livewire/EkomPredmetDetail.php resources/views/livewire/ekom-predmet-detail.blade.php tests/Feature/Livewire/EkomPredmetDetailTest.php
git commit -m "$(cat <<'EOF'
feat(ekom): Add predmet detail component

- Display predmet details and raw API data
- Toggle DND from detail view
- Download documents action
- Refresh data from API
- 404 handling for invalid IDs
EOF
)"
```

---

## Phase 3: Podnesci Management

### Task 3.1: Create Podnesci List Component

**Files:**
- Create: `app/Http/Livewire/EkomPodnesciList.php`
- Create: `resources/views/livewire/ekom-podnesci-list.blade.php`
- Test: `tests/Feature/Livewire/EkomPodnesciListTest.php`

*(Similar pattern to EkomPredmetiList - filtering, pagination, status display)*

**Step 1-6:** Follow same TDD pattern as Task 2.1

---

### Task 3.2: Create Podnesak Creator Component

**Files:**
- Create: `app/Http/Livewire/EkomPodnesakCreate.php`
- Create: `resources/views/livewire/ekom-podnesak-create.blade.php`
- Test: `tests/Feature/Livewire/EkomPodnesakCreateTest.php`

**Step 1: Write failing test**

```php
<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\EkomPodnesakCreate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class EkomPodnesakCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_component_renders_successfully(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('ekom.podnesci.create'))
            ->assertOk()
            ->assertSeeLivewire(EkomPodnesakCreate::class);
    }

    public function test_validates_required_fields(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(EkomPodnesakCreate::class)
            ->call('submit')
            ->assertHasErrors(['predmetId', 'vrstaPodneskaId', 'naziv']);
    }

    public function test_can_add_file_attachments(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(EkomPodnesakCreate::class)
            ->set('attachments', [UploadedFile::fake()->create('document.pdf', 100)])
            ->assertCount('attachments', 1);
    }

    public function test_can_submit_podnesak(): void
    {
        $user = User::factory()->create();

        $this->mock(\App\Contracts\External\EkomServiceInterface::class)
            ->shouldReceive('createPodnesak')
            ->once()
            ->andReturn(['id' => 12345, 'status' => 'NACRT']);

        Livewire::actingAs($user)
            ->test(EkomPodnesakCreate::class)
            ->set('predmetId', 123456)
            ->set('vrstaPodneskaId', 1)
            ->set('naziv', 'Test Submission')
            ->set('opis', 'Test description')
            ->call('submit')
            ->assertDispatched('success');
    }
}
```

**Step 2-6:** Implement component with file upload support, validation, and API submission.

---

## Phase 4: Otpravci Handling

### Task 4.1: Create Otpravci List Component

**Files:**
- Create: `app/Http/Livewire/EkomOtpravciList.php`
- Create: `resources/views/livewire/ekom-otpravci-list.blade.php`
- Test: `tests/Feature/Livewire/EkomOtpravciListTest.php`

**Key Features:**
- Filter by pending (needs receipt confirmation)
- Bulk confirm receipt action
- Download dostavnica (delivery note)

---

## Phase 5: Automated Sync System

### Task 5.1: Create Sync Job

**Files:**
- Create: `app/Jobs/EkomSyncAllJob.php`
- Test: `tests/Feature/Jobs/EkomSyncAllJobTest.php`

**Step 1: Write failing test**

```php
<?php

namespace Tests\Feature\Jobs;

use App\Jobs\EkomSyncAllJob;
use App\Contracts\External\EkomServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EkomSyncAllJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_can_be_dispatched(): void
    {
        Queue::fake();

        EkomSyncAllJob::dispatch();

        Queue::assertPushed(EkomSyncAllJob::class);
    }

    public function test_job_calls_all_sync_methods(): void
    {
        $mock = $this->mock(EkomServiceInterface::class);
        $mock->shouldReceive('syncPredmeti')->once()->andReturn(10);
        $mock->shouldReceive('syncPodnesci')->once()->andReturn(5);
        $mock->shouldReceive('syncOtpravci')->once()->andReturn(3);

        $job = new EkomSyncAllJob();
        $job->handle($mock);
    }
}
```

**Step 2: Create the job**

```php
<?php

namespace App\Jobs;

use App\Contracts\External\EkomServiceInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class EkomSyncAllJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $maxPages;

    public int $timeout = 3600;
    public int $tries = 1;

    public function __construct(int $maxPages = 10)
    {
        $this->maxPages = $maxPages;
    }

    public function handle(EkomServiceInterface $service): void
    {
        $startTime = microtime(true);

        Log::info('EKOM full sync started', ['max_pages' => $this->maxPages]);

        try {
            $predmetiCount = $service->syncPredmeti([], $this->maxPages);
            Log::info('EKOM predmeti synced', ['count' => $predmetiCount]);

            $podnesciCount = $service->syncPodnesci([], $this->maxPages);
            Log::info('EKOM podnesci synced', ['count' => $podnesciCount]);

            $otpravciCount = $service->syncOtpravci([], $this->maxPages);
            Log::info('EKOM otpravci synced', ['count' => $otpravciCount]);

            Log::info('EKOM full sync completed', [
                'predmeti' => $predmetiCount,
                'podnesci' => $podnesciCount,
                'otpravci' => $otpravciCount,
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

        } catch (\Throwable $e) {
            Log::error('EKOM full sync failed', [
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            throw $e;
        }
    }
}
```

---

### Task 5.2: Register Scheduled Sync

**Files:**
- Modify: `app/Console/Kernel.php` or `routes/console.php`

```php
// In routes/console.php (Laravel 11+)
use Illuminate\Support\Facades\Schedule;

Schedule::job(new \App\Jobs\EkomSyncAllJob(maxPages: 5))
    ->hourly()
    ->name('ekom-sync')
    ->withoutOverlapping()
    ->onOneServer();
```

---

### Task 5.3: Create Sync Status Component

**Files:**
- Create: `app/Http/Livewire/EkomSyncStatus.php`
- Create: `resources/views/livewire/ekom-sync-status.blade.php`
- Create: `app/Models/EkomSyncLog.php`
- Create: `database/migrations/*_create_ekom_sync_logs_table.php`

Display sync history, last run times, errors, and allow manual triggering.

---

## Phase 6: Internal API

### Task 6.1: Create API Controller

**Files:**
- Create: `app/Http/Controllers/Api/EkomController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/EkomApiTest.php`

**Step 1: Write failing test**

```php
<?php

namespace Tests\Feature\Api;

use App\Models\EkomPredmet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EkomApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_predmeti_requires_auth(): void
    {
        $this->getJson('/api/ekom/predmeti')
            ->assertUnauthorized();
    }

    public function test_can_list_predmeti(): void
    {
        Sanctum::actingAs(User::factory()->create());
        EkomPredmet::factory()->count(3)->create();

        $this->getJson('/api/ekom/predmeti')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_can_get_single_predmet(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $predmet = EkomPredmet::factory()->create();

        $this->getJson("/api/ekom/predmeti/{$predmet->remote_id}")
            ->assertOk()
            ->assertJsonPath('data.remote_id', $predmet->remote_id);
    }

    public function test_can_trigger_sync(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/ekom/sync')
            ->assertAccepted()
            ->assertJsonPath('message', 'Sync job dispatched');
    }
}
```

**Step 2: Create controller**

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\EkomSyncAllJob;
use App\Models\EkomOtpravak;
use App\Models\EkomPodnesak;
use App\Models\EkomPredmet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EkomController extends Controller
{
    public function listPredmeti(Request $request): JsonResponse
    {
        $predmeti = EkomPredmet::query()
            ->when($request->search, fn($q, $s) => $q->where('oznaka', 'like', "%{$s}%"))
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->orderByDesc('last_synced_at')
            ->paginate($request->per_page ?? 20);

        return response()->json($predmeti);
    }

    public function showPredmet(int $remoteId): JsonResponse
    {
        $predmet = EkomPredmet::where('remote_id', $remoteId)->firstOrFail();

        return response()->json(['data' => $predmet]);
    }

    public function listPodnesci(Request $request): JsonResponse
    {
        $podnesci = EkomPodnesak::query()
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->orderByDesc('last_synced_at')
            ->paginate($request->per_page ?? 20);

        return response()->json($podnesci);
    }

    public function listOtpravci(Request $request): JsonResponse
    {
        $otpravci = EkomOtpravak::query()
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->pending, fn($q) => $q->whereNull('vrijeme_primitka'))
            ->orderByDesc('last_synced_at')
            ->paginate($request->per_page ?? 20);

        return response()->json($otpravci);
    }

    public function triggerSync(): JsonResponse
    {
        EkomSyncAllJob::dispatch();

        return response()->json(['message' => 'Sync job dispatched'], 202);
    }
}
```

**Step 3: Register routes**

```php
// routes/api.php
Route::middleware('auth:sanctum')->prefix('ekom')->name('api.ekom.')->group(function () {
    Route::get('/predmeti', [EkomController::class, 'listPredmeti'])->name('predmeti.index');
    Route::get('/predmeti/{remoteId}', [EkomController::class, 'showPredmet'])->name('predmeti.show');
    Route::get('/podnesci', [EkomController::class, 'listPodnesci'])->name('podnesci.index');
    Route::get('/otpravci', [EkomController::class, 'listOtpravci'])->name('otpravci.index');
    Route::post('/sync', [EkomController::class, 'triggerSync'])->name('sync');
});
```

---

## Summary

| Phase | Components | Tests | Estimated Steps |
|-------|------------|-------|-----------------|
| 1. Foundation | Routes, Dashboard, Factories | 8 | 15 |
| 2. Predmeti | List, Detail | 10 | 20 |
| 3. Podnesci | List, Create | 8 | 20 |
| 4. Otpravci | List with actions | 6 | 15 |
| 5. Sync System | Job, Scheduler, Status | 6 | 15 |
| 6. Internal API | Controller, Routes | 5 | 10 |

**Total: ~43 tests, ~95 steps**

**Key Features Delivered:**
- Web dashboard with statistics and navigation
- Predmeti list with search, filter, sort, DND toggle
- Predmet detail with download and refresh
- Podnesci list and creation with file uploads
- Otpravci list with receipt confirmation
- Automated hourly sync via scheduled job
- Internal REST API for programmatic access
- Full test coverage following TDD

**Commit Frequency:** After each task completion (10-15 commits total)

---

## Execution Handoff

**Plan complete and saved to `docs/plans/2026-01-24-ekomun-interface-implementation.md`.**

**Two execution options:**

**1. Subagent-Driven (this session)** - Dispatch fresh subagent per task, review between tasks, fast iteration

**2. Parallel Session (separate)** - Open new session with executing-plans, batch execution with checkpoints

**Which approach?**

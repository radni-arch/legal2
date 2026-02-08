# Phase 5: GraphViewer UI Enhancements Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Display Phase 4 enhanced node properties (dissent/concurrence counts, amendment tracking) in the GraphViewer UI with proper styling and accessibility.

**Architecture:** Extend the existing GraphViewer Blade component with new panels for CourtDecisionDocument outcome details and LawDocument amendment tracking. Properties are already synced to Neo4j (Phase 4 complete), this phase adds UI visualization.

**Tech Stack:** Laravel Livewire, Blade templates, Tailwind CSS, Alpine.js

---

## Prerequisites

- Phase 4 complete (dissent_count, concurrence_count synced to CourtDecisionDocument nodes)
- Phase 4 complete (amendments, repeal_date, repealed_by, parent_law_number, consolidation_date synced to LawDocument nodes)
- Existing Phase 5 components already implemented:
  - Judge Panel (Task 5.1)
  - Precedent Status Indicators (Task 5.2)

---

## Sprint 1: Court Decision Outcome Panel (Tasks 1-3)

### Task 1: Add Dissent/Concurrence Count Display

**Files:**
- Modify: `resources/views/livewire/graph-viewer.blade.php:555-577`

**Step 1: Write the failing browser test**

Create test in `tests/Browser/GraphViewerPhase5Test.php`:

```php
<?php

namespace Tests\Browser;

use App\Models\CourtDecision;
use App\Models\CourtDecisionDocument;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class GraphViewerPhase5Test extends DuskTestCase
{
    /** @test */
    public function it_displays_dissent_and_concurrence_counts_for_court_decisions(): void
    {
        // Create a decision with dissent/concurrence counts
        $decision = CourtDecision::factory()->create([
            'case_number' => 'P5-Dissent/2026',
            'court' => 'Vrhovni sud',
            'dissent_count' => 2,
            'concurrence_count' => 1,
        ]);

        $doc = CourtDecisionDocument::factory()->create([
            'decision_id' => $decision->id,
            'content' => 'Test content.',
        ]);

        // Sync to Neo4j (if available)
        try {
            app(\App\Services\Graph\DecisionGraphSyncService::class)->sync($decision->id);
        } catch (\Throwable $e) {
            // Neo4j may not be running
        }

        $this->browse(function (Browser $browser) {
            $browser->visit('/graph-viewer')
                ->waitFor('#search-input', 10)
                ->type('#search-input', 'P5-Dissent/2026')
                ->click('@search-button')
                ->waitFor('@dissent-count-badge', 10)
                ->assertSeeIn('@dissent-count-badge', '2 Dissents')
                ->assertSeeIn('@concurrence-count-badge', '1 Concurrence');
        });
    }

    /** @test */
    public function it_hides_dissent_badge_when_count_is_zero(): void
    {
        $decision = CourtDecision::factory()->create([
            'case_number' => 'P5-NoDissent/2026',
            'court' => 'Vrhovni sud',
            'dissent_count' => 0,
            'concurrence_count' => 0,
        ]);

        CourtDecisionDocument::factory()->create([
            'decision_id' => $decision->id,
            'content' => 'Test content.',
        ]);

        $this->browse(function (Browser $browser) {
            $browser->visit('/graph-viewer')
                ->waitFor('#search-input', 10)
                ->type('#search-input', 'P5-NoDissent/2026')
                ->click('@search-button')
                ->pause(2000)
                ->assertMissing('@dissent-count-badge')
                ->assertMissing('@concurrence-count-badge');
        });
    }
}
```

**Step 2: Run test to verify it fails**

Run: `php artisan dusk tests/Browser/GraphViewerPhase5Test.php --filter=it_displays_dissent_and_concurrence_counts`
Expected: FAIL - Element @dissent-count-badge not found

**Step 3: Add dissent/concurrence badges to Blade template**

In `resources/views/livewire/graph-viewer.blade.php`, find the Precedent Status Indicators section (around line 555) and add after the existing outcome badge:

```blade
{{-- Precedent Status Indicators (Phase 5 Enhancement) --}}
@if($selectedNodeType === 'CourtDecisionDocument')
<div class="flex flex-wrap gap-2 mt-4">
    @if(isset($selectedNode['precedential_value']) && $selectedNode['precedential_value'])
    <span class="px-2 py-1 text-xs rounded
        @if($selectedNode['precedential_value'] === 'binding') bg-green-600
        @elseif($selectedNode['precedential_value'] === 'persuasive') bg-yellow-600
        @else bg-gray-600 @endif text-white">
        {{ ucfirst($selectedNode['precedential_value']) }}
    </span>
    @endif

    @if(isset($selectedNode['outcome']) && $selectedNode['outcome'])
    <span class="px-2 py-1 text-xs rounded
        @if($selectedNode['outcome'] === 'affirmed') bg-green-600
        @elseif($selectedNode['outcome'] === 'reversed') bg-red-600
        @elseif($selectedNode['outcome'] === 'remanded') bg-yellow-600
        @else bg-gray-600 @endif text-white">
        {{ ucfirst($selectedNode['outcome']) }}
    </span>
    @endif

    {{-- Dissent Count Badge (Phase 5.3 Enhancement) --}}
    @if(isset($selectedNode['dissent_count']) && $selectedNode['dissent_count'] > 0)
    <span dusk="dissent-count-badge" class="px-2 py-1 text-xs rounded bg-red-700 text-white flex items-center gap-1">
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
        {{ $selectedNode['dissent_count'] }} {{ $selectedNode['dissent_count'] === 1 ? 'Dissent' : 'Dissents' }}
    </span>
    @endif

    {{-- Concurrence Count Badge (Phase 5.3 Enhancement) --}}
    @if(isset($selectedNode['concurrence_count']) && $selectedNode['concurrence_count'] > 0)
    <span dusk="concurrence-count-badge" class="px-2 py-1 text-xs rounded bg-blue-700 text-white flex items-center gap-1">
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
        </svg>
        {{ $selectedNode['concurrence_count'] }} {{ $selectedNode['concurrence_count'] === 1 ? 'Concurrence' : 'Concurrences' }}
    </span>
    @endif
</div>
@endif
```

**Step 4: Run test to verify it passes**

Run: `php artisan dusk tests/Browser/GraphViewerPhase5Test.php --filter=it_displays_dissent_and_concurrence_counts`
Expected: PASS

**Step 5: Commit**

```bash
git add tests/Browser/GraphViewerPhase5Test.php resources/views/livewire/graph-viewer.blade.php
git commit -m "feat(ui): add dissent and concurrence count badges to GraphViewer"
```

---

### Task 2: Add Holding Display for Court Decisions

**Files:**
- Modify: `resources/views/livewire/graph-viewer.blade.php`

**Step 1: Write failing test**

Add to `tests/Browser/GraphViewerPhase5Test.php`:

```php
/** @test */
public function it_displays_holding_text_for_court_decisions(): void
{
    $decision = CourtDecision::factory()->create([
        'case_number' => 'P5-Holding/2026',
        'court' => 'Vrhovni sud',
        'holding' => 'The court holds that the defendant is liable for damages.',
    ]);

    CourtDecisionDocument::factory()->create([
        'decision_id' => $decision->id,
        'content' => 'Test content.',
    ]);

    $this->browse(function (Browser $browser) {
        $browser->visit('/graph-viewer')
            ->waitFor('#search-input', 10)
            ->type('#search-input', 'P5-Holding/2026')
            ->click('@search-button')
            ->waitFor('@holding-panel', 10)
            ->assertSeeIn('@holding-panel', 'The court holds that the defendant is liable');
    });
}
```

**Step 2: Run test to verify it fails**

Run: `php artisan dusk tests/Browser/GraphViewerPhase5Test.php --filter=it_displays_holding_text`
Expected: FAIL

**Step 3: Add holding display panel**

In `resources/views/livewire/graph-viewer.blade.php`, after the Precedent Status section, add:

```blade
{{-- Holding Panel (Phase 5.3 Enhancement) --}}
@if($selectedNodeType === 'CourtDecisionDocument' && !empty($selectedNode['holding']))
<div dusk="holding-panel" class="card mt-4">
    <div class="card-header">
        <h2 class="card-title flex items-center gap-2">
            <svg class="w-5 h-5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            Holding
        </h2>
    </div>
    <div class="card-body">
        <p class="text-gray-200 text-sm italic">
            "{{ $selectedNode['holding'] }}"
        </p>
    </div>
</div>
@endif
```

**Step 4: Run test to verify it passes**

Run: `php artisan dusk tests/Browser/GraphViewerPhase5Test.php --filter=it_displays_holding_text`
Expected: PASS

**Step 5: Commit**

```bash
git add -A
git commit -m "feat(ui): add holding display panel to GraphViewer"
```

---

### Task 3: Add Unit Tests for Outcome Display Logic

**Files:**
- Create: `tests/Feature/Livewire/GraphViewerOutcomeDisplayTest.php`

**Step 1: Write the tests**

```php
<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\GraphViewer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GraphViewerOutcomeDisplayTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_renders_dissent_count_badge_when_greater_than_zero(): void
    {
        Livewire::test(GraphViewer::class)
            ->set('selectedNodeType', 'CourtDecisionDocument')
            ->set('selectedNode', [
                'id' => 'test-123',
                'case_number' => 'Rev 1/2026',
                'dissent_count' => 3,
                'concurrence_count' => 0,
            ])
            ->assertSee('3 Dissents')
            ->assertDontSee('Concurrence');
    }

    /** @test */
    public function it_hides_dissent_badge_when_zero(): void
    {
        Livewire::test(GraphViewer::class)
            ->set('selectedNodeType', 'CourtDecisionDocument')
            ->set('selectedNode', [
                'id' => 'test-123',
                'case_number' => 'Rev 1/2026',
                'dissent_count' => 0,
                'concurrence_count' => 0,
            ])
            ->assertDontSee('Dissent');
    }

    /** @test */
    public function it_uses_singular_form_for_single_dissent(): void
    {
        Livewire::test(GraphViewer::class)
            ->set('selectedNodeType', 'CourtDecisionDocument')
            ->set('selectedNode', [
                'id' => 'test-123',
                'dissent_count' => 1,
            ])
            ->assertSee('1 Dissent')
            ->assertDontSee('Dissents');
    }

    /** @test */
    public function it_renders_holding_panel_when_holding_present(): void
    {
        Livewire::test(GraphViewer::class)
            ->set('selectedNodeType', 'CourtDecisionDocument')
            ->set('selectedNode', [
                'id' => 'test-123',
                'holding' => 'The court finds in favor of the plaintiff.',
            ])
            ->assertSee('Holding')
            ->assertSee('The court finds in favor of the plaintiff.');
    }

    /** @test */
    public function it_hides_holding_panel_when_empty(): void
    {
        Livewire::test(GraphViewer::class)
            ->set('selectedNodeType', 'CourtDecisionDocument')
            ->set('selectedNode', [
                'id' => 'test-123',
                'holding' => null,
            ])
            ->assertDontSee('Holding');
    }
}
```

**Step 2: Run tests**

Run: `./vendor/bin/phpunit tests/Feature/Livewire/GraphViewerOutcomeDisplayTest.php`
Expected: PASS (tests verify existing implementation)

**Step 3: Commit**

```bash
git add tests/Feature/Livewire/GraphViewerOutcomeDisplayTest.php
git commit -m "test(ui): add unit tests for court decision outcome display"
```

---

## Sprint 2: Law Document Amendment Panel (Tasks 4-6)

### Task 4: Add Amendment Tracking Panel for LawDocument

**Files:**
- Modify: `resources/views/livewire/graph-viewer.blade.php`

**Step 1: Write failing test**

Add to `tests/Browser/GraphViewerPhase5Test.php`:

```php
/** @test */
public function it_displays_amendments_list_for_law_documents(): void
{
    $law = \App\Models\Law::factory()->create([
        'law_number' => 'NN 50/10-P5',
        'title' => 'Test Law with Amendments',
        'amendments' => ['NN 123/21', 'NN 45/22'],
    ]);

    $this->browse(function (Browser $browser) {
        $browser->visit('/graph-viewer')
            ->select('@node-type-select', 'LawDocument')
            ->waitFor('#search-input', 10)
            ->type('#search-input', 'NN 50/10-P5')
            ->click('@search-button')
            ->waitFor('@amendments-panel', 10)
            ->assertSeeIn('@amendments-panel', 'Amendments')
            ->assertSeeIn('@amendments-panel', 'NN 123/21')
            ->assertSeeIn('@amendments-panel', 'NN 45/22');
    });
}
```

**Step 2: Run test to verify it fails**

Run: `php artisan dusk tests/Browser/GraphViewerPhase5Test.php --filter=it_displays_amendments_list`
Expected: FAIL

**Step 3: Add amendments panel**

In `resources/views/livewire/graph-viewer.blade.php`, after the Judge Panel section (after line 553), add the LawDocument specific panels:

```blade
{{-- Amendment Tracking Panel (Phase 5.4 Enhancement) - LawDocument only --}}
@if($selectedNodeType === 'LawDocument')
    {{-- Amendments List --}}
    @if(!empty($selectedNode['amendments']) && is_array($selectedNode['amendments']) && count($selectedNode['amendments']) > 0)
    <div dusk="amendments-panel" class="card mt-4">
        <div class="card-header">
            <h2 class="card-title flex items-center gap-2">
                <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                Amendments ({{ count($selectedNode['amendments']) }})
            </h2>
        </div>
        <div class="card-body">
            <ul class="space-y-2">
                @foreach($selectedNode['amendments'] as $amendment)
                <li class="flex items-center gap-2 text-sm">
                    <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                    <span class="text-gray-200">{{ $amendment }}</span>
                </li>
                @endforeach
            </ul>
        </div>
    </div>
    @endif

    {{-- Repeal Status --}}
    @if(!empty($selectedNode['repealed_by']) || !empty($selectedNode['repeal_date']))
    <div dusk="repeal-panel" class="card mt-4 border-red-700">
        <div class="card-header bg-red-900/30">
            <h2 class="card-title flex items-center gap-2 text-red-400">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                </svg>
                Repealed
            </h2>
        </div>
        <div class="card-body">
            <dl class="space-y-2 text-sm">
                @if(!empty($selectedNode['repeal_date']))
                <div>
                    <dt class="text-gray-500">Repeal Date</dt>
                    <dd class="text-gray-200">{{ $selectedNode['repeal_date'] }}</dd>
                </div>
                @endif
                @if(!empty($selectedNode['repealed_by']))
                <div>
                    <dt class="text-gray-500">Repealed By</dt>
                    <dd class="text-gray-200">{{ $selectedNode['repealed_by'] }}</dd>
                </div>
                @endif
            </dl>
        </div>
    </div>
    @endif

    {{-- Parent Law Reference --}}
    @if(!empty($selectedNode['parent_law_number']))
    <div dusk="parent-law-panel" class="card mt-4">
        <div class="card-header">
            <h2 class="card-title flex items-center gap-2">
                <svg class="w-5 h-5 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16l-4-4m0 0l4-4m-4 4h18" />
                </svg>
                Amends Original Law
            </h2>
        </div>
        <div class="card-body">
            <p class="text-gray-200">{{ $selectedNode['parent_law_number'] }}</p>
        </div>
    </div>
    @endif

    {{-- Consolidation Date --}}
    @if(!empty($selectedNode['consolidation_date']))
    <div dusk="consolidation-panel" class="card mt-4">
        <div class="card-header">
            <h2 class="card-title flex items-center gap-2">
                <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
                Consolidated Text (Pročišćeni tekst)
            </h2>
        </div>
        <div class="card-body">
            <p class="text-gray-200">Last consolidated: {{ $selectedNode['consolidation_date'] }}</p>
        </div>
    </div>
    @endif
@endif
```

**Step 4: Run test to verify it passes**

Run: `php artisan dusk tests/Browser/GraphViewerPhase5Test.php --filter=it_displays_amendments_list`
Expected: PASS

**Step 5: Commit**

```bash
git add -A
git commit -m "feat(ui): add amendment tracking panels for LawDocument in GraphViewer"
```

---

### Task 5: Add Repeal Status Browser Tests

**Files:**
- Modify: `tests/Browser/GraphViewerPhase5Test.php`

**Step 1: Add repeal status tests**

```php
/** @test */
public function it_displays_repeal_status_for_repealed_laws(): void
{
    $law = \App\Models\Law::factory()->create([
        'law_number' => 'NN 100/08-P5',
        'title' => 'Repealed Law',
        'repeal_date' => '2025-12-31',
        'repealed_by' => 'NN 200/25',
    ]);

    $this->browse(function (Browser $browser) {
        $browser->visit('/graph-viewer')
            ->select('@node-type-select', 'LawDocument')
            ->waitFor('#search-input', 10)
            ->type('#search-input', 'NN 100/08-P5')
            ->click('@search-button')
            ->waitFor('@repeal-panel', 10)
            ->assertSeeIn('@repeal-panel', 'Repealed')
            ->assertSeeIn('@repeal-panel', '2025-12-31')
            ->assertSeeIn('@repeal-panel', 'NN 200/25');
    });
}

/** @test */
public function it_displays_parent_law_for_amendments(): void
{
    $law = \App\Models\Law::factory()->create([
        'law_number' => 'NN 123/21-P5',
        'title' => 'Amendment to Original Law',
        'parent_law_number' => 'NN 50/10',
    ]);

    $this->browse(function (Browser $browser) {
        $browser->visit('/graph-viewer')
            ->select('@node-type-select', 'LawDocument')
            ->waitFor('#search-input', 10)
            ->type('#search-input', 'NN 123/21-P5')
            ->click('@search-button')
            ->waitFor('@parent-law-panel', 10)
            ->assertSeeIn('@parent-law-panel', 'Amends Original Law')
            ->assertSeeIn('@parent-law-panel', 'NN 50/10');
    });
}

/** @test */
public function it_hides_amendment_panels_when_no_amendment_data(): void
{
    $law = \App\Models\Law::factory()->create([
        'law_number' => 'NN 10/20-P5',
        'title' => 'Simple Law',
        // No amendment data
    ]);

    $this->browse(function (Browser $browser) {
        $browser->visit('/graph-viewer')
            ->select('@node-type-select', 'LawDocument')
            ->waitFor('#search-input', 10)
            ->type('#search-input', 'NN 10/20-P5')
            ->click('@search-button')
            ->pause(2000)
            ->assertMissing('@amendments-panel')
            ->assertMissing('@repeal-panel')
            ->assertMissing('@parent-law-panel');
    });
}
```

**Step 2: Run all Phase 5 browser tests**

Run: `php artisan dusk tests/Browser/GraphViewerPhase5Test.php`
Expected: PASS

**Step 3: Commit**

```bash
git add tests/Browser/GraphViewerPhase5Test.php
git commit -m "test(ui): add browser tests for law document amendment panels"
```

---

### Task 6: Add Unit Tests for Amendment Display Logic

**Files:**
- Create: `tests/Feature/Livewire/GraphViewerAmendmentDisplayTest.php`

**Step 1: Write the tests**

```php
<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\GraphViewer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GraphViewerAmendmentDisplayTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_renders_amendments_panel_when_amendments_exist(): void
    {
        Livewire::test(GraphViewer::class)
            ->set('selectedNodeType', 'LawDocument')
            ->set('selectedNode', [
                'id' => 'law-123',
                'law_number' => 'NN 50/10',
                'amendments' => ['NN 123/21', 'NN 45/22'],
            ])
            ->assertSee('Amendments (2)')
            ->assertSee('NN 123/21')
            ->assertSee('NN 45/22');
    }

    /** @test */
    public function it_hides_amendments_panel_when_empty(): void
    {
        Livewire::test(GraphViewer::class)
            ->set('selectedNodeType', 'LawDocument')
            ->set('selectedNode', [
                'id' => 'law-123',
                'amendments' => [],
            ])
            ->assertDontSee('Amendments');
    }

    /** @test */
    public function it_renders_repeal_panel_with_both_fields(): void
    {
        Livewire::test(GraphViewer::class)
            ->set('selectedNodeType', 'LawDocument')
            ->set('selectedNode', [
                'id' => 'law-123',
                'repeal_date' => '2025-12-31',
                'repealed_by' => 'NN 200/25',
            ])
            ->assertSee('Repealed')
            ->assertSee('2025-12-31')
            ->assertSee('NN 200/25');
    }

    /** @test */
    public function it_renders_repeal_panel_with_only_repeal_date(): void
    {
        Livewire::test(GraphViewer::class)
            ->set('selectedNodeType', 'LawDocument')
            ->set('selectedNode', [
                'id' => 'law-123',
                'repeal_date' => '2025-06-01',
                'repealed_by' => null,
            ])
            ->assertSee('Repealed')
            ->assertSee('2025-06-01')
            ->assertDontSee('Repealed By');
    }

    /** @test */
    public function it_hides_repeal_panel_when_not_repealed(): void
    {
        Livewire::test(GraphViewer::class)
            ->set('selectedNodeType', 'LawDocument')
            ->set('selectedNode', [
                'id' => 'law-123',
                'repeal_date' => null,
                'repealed_by' => null,
            ])
            ->assertDontSee('Repealed');
    }

    /** @test */
    public function it_renders_parent_law_panel(): void
    {
        Livewire::test(GraphViewer::class)
            ->set('selectedNodeType', 'LawDocument')
            ->set('selectedNode', [
                'id' => 'law-123',
                'parent_law_number' => 'NN 50/10',
            ])
            ->assertSee('Amends Original Law')
            ->assertSee('NN 50/10');
    }

    /** @test */
    public function it_renders_consolidation_date(): void
    {
        Livewire::test(GraphViewer::class)
            ->set('selectedNodeType', 'LawDocument')
            ->set('selectedNode', [
                'id' => 'law-123',
                'consolidation_date' => '2024-06-15',
            ])
            ->assertSee('Consolidated Text')
            ->assertSee('2024-06-15');
    }

    /** @test */
    public function it_does_not_show_amendment_panels_for_court_decisions(): void
    {
        Livewire::test(GraphViewer::class)
            ->set('selectedNodeType', 'CourtDecisionDocument')
            ->set('selectedNode', [
                'id' => 'decision-123',
                'amendments' => ['Should not show'],
            ])
            ->assertDontSee('Amendments');
    }
}
```

**Step 2: Run tests**

Run: `./vendor/bin/phpunit tests/Feature/Livewire/GraphViewerAmendmentDisplayTest.php`
Expected: PASS

**Step 3: Commit**

```bash
git add tests/Feature/Livewire/GraphViewerAmendmentDisplayTest.php
git commit -m "test(ui): add unit tests for law document amendment display"
```

---

## Sprint 3: Final Verification and Documentation (Tasks 7-8)

### Task 7: Run Full Test Suite

**Step 1: Run all GraphViewer-related tests**

Run:
```bash
./vendor/bin/phpunit tests/Feature/Livewire/GraphViewerTest.php
./vendor/bin/phpunit tests/Feature/Livewire/GraphViewerOutcomeDisplayTest.php
./vendor/bin/phpunit tests/Feature/Livewire/GraphViewerAmendmentDisplayTest.php
```

Expected: All tests PASS

**Step 2: Run integration tests if Neo4j available**

Run:
```bash
./vendor/bin/phpunit tests/Integration/Graph/Phase4EnhancedPropertiesTest.php
```

Expected: PASS or SKIP (if Neo4j not running)

**Step 3: Run browser tests if Dusk configured**

Run:
```bash
php artisan dusk tests/Browser/GraphViewerPhase5Test.php
```

Expected: PASS or SKIP

**Step 4: Commit verification results**

```bash
git add -A
git commit -m "test: verify Phase 5 UI enhancements complete"
```

---

### Task 8: Push and Create Summary

**Step 1: Push all changes**

```bash
git push -u origin claude/graph-enhancement-data-integrity-XqqqL
```

**Step 2: Verify commits**

Run: `git log --oneline -10`

Expected commits:
- feat(ui): add dissent and concurrence count badges to GraphViewer
- feat(ui): add holding display panel to GraphViewer
- test(ui): add unit tests for court decision outcome display
- feat(ui): add amendment tracking panels for LawDocument in GraphViewer
- test(ui): add browser tests for law document amendment panels
- test(ui): add unit tests for law document amendment display
- test: verify Phase 5 UI enhancements complete

---

## Summary Checklist

### Sprint 1: Court Decision Outcome Panel
- [ ] Task 1: Add dissent/concurrence count badges
- [ ] Task 2: Add holding display panel
- [ ] Task 3: Add unit tests for outcome display

### Sprint 2: Law Document Amendment Panel
- [ ] Task 4: Add amendment tracking panels (amendments list, repeal status, parent law, consolidation date)
- [ ] Task 5: Add browser tests for amendment panels
- [ ] Task 6: Add unit tests for amendment display

### Sprint 3: Final Verification
- [ ] Task 7: Run full test suite
- [ ] Task 8: Push and create summary

---

## Estimated Effort

| Sprint | Tasks | Est. Time |
|--------|-------|-----------|
| Sprint 1 | 3 | 30-45 min |
| Sprint 2 | 3 | 30-45 min |
| Sprint 3 | 2 | 15-20 min |
| **Total** | **8** | **1-2 hours** |

---

## References

- `@app/Http/Livewire/GraphViewer.php` - Livewire component
- `@resources/views/livewire/graph-viewer.blade.php` - Blade template
- `@app/Services/Graph/DecisionGraphSyncService.php` - Decision sync (Phase 4)
- `@app/Services/Graph/LawGraphSyncService.php` - Law sync (Phase 4)
- `@documentation/GRAPH_SCHEMA.cypher` - Graph schema with Phase 4 properties
- `@.claude/skills/test-driven-development` - TDD process

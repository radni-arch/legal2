# Implementation Plan: Textract Flag Flow Fixes + Status Indicators UI

**Date:** 2026-01-27
**Prerequisites:** Design document `2026-01-27-textract-status-indicators-design.md`
**Branch:** `claude/textract-upload-metadata-cgbYP`

---

## Phase 1: Fix Flag Flow Gaps (5 tasks)

### Task 1.1: Fix Status Value Mismatch

**Problem:** `ProcessTextractJob` sets `status = 'completed'`, but `SyncTextractToGraph` checks for `status === 'succeeded'`

**Files to modify:**
- `app/Jobs/SyncTextractToGraph.php`

**Changes:**
```php
// Line 139: Change 'succeeded' to 'completed'
if ($job->status !== 'completed') {
```

**Verification:**
```bash
grep -n "succeeded" app/Jobs/SyncTextractToGraph.php
# Should show no 'succeeded' status checks
```

**Done when:** `SyncTextractToGraph` checks for `'completed'` status

---

### Task 1.2: Add `processing` State to GenerateEmbeddingsJob

**Problem:** `GenerateEmbeddingsJob` jumps from `pending` directly to `synced`, skipping `processing` state

**Files to modify:**
- `app/Jobs/GenerateEmbeddingsJob.php`

**Changes:**
```php
// In processTextractJob() method, after line 102 (after checking job exists):
// Set status to processing before starting
$job->update(['embedding_status' => 'processing']);
```

**Verification:**
```bash
./scripts/run-focused-tests.sh GenerateEmbeddingsJobTest
```

**Done when:** Job sets `embedding_status = 'processing'` before generating embeddings

---

### Task 1.3: Dispatch Graph Sync After Embeddings Complete

**Problem:** After initial extraction, graph sync is never triggered. Only triggered on manual content edit.

**Files to modify:**
- `app/Jobs/GenerateEmbeddingsJob.php`

**Changes:**
```php
// After line 159 ($job->markEmbeddingSynced()):
// Dispatch graph sync if Neo4j is enabled
if (config('neo4j.sync.enabled', false)) {
    \App\Jobs\SyncTextractToGraph::dispatch($job->id)
        ->onQueue('graph')
        ->delay(now()->addSeconds(5));

    Log::info('GenerateEmbeddingsJob - Dispatched graph sync', [
        'job_id' => $this->sourceId,
    ]);
}
```

**Verification:**
```bash
./scripts/run-focused-tests.sh GenerateEmbeddingsJobTest
```

**Done when:** `SyncTextractToGraph` is dispatched after embeddings complete

---

### Task 1.4: Add `processing` State to SyncTextractToGraph

**Problem:** `SyncTextractToGraph` never sets `graph_sync_status = 'processing'` before starting sync

**Files to modify:**
- `app/Jobs/SyncTextractToGraph.php`

**Changes:**
```php
// After line 136 (after embedding check passes), before try block:
// Set status to processing
$job->update(['graph_sync_status' => 'processing']);
```

**Verification:**
```bash
./scripts/run-focused-tests.sh SyncTextractToGraphTest
```

**Done when:** Job sets `graph_sync_status = 'processing'` before syncing

---

### Task 1.5: Handle Embedding Failure in Graph Sync

**Problem:** If embeddings fail, `graph_sync_status` stays `pending` forever with no indication

**Files to modify:**
- `app/Models/TextractJob.php`

**Changes:**
Add model event listener to handle embedding failure:
```php
// In booted() method, after existing updated listener:
static::updated(function ($job) {
    // If embeddings failed, mark graph sync as blocked
    if ($job->wasChanged('embedding_status') && $job->embedding_status === 'failed') {
        if ($job->graph_sync_status === 'pending') {
            $job->updateQuietly([
                'graph_sync_status' => 'blocked',
                'error' => 'Graph sync blocked: embeddings failed',
            ]);
        }
    }
});
```

Also add migration for new status value:
```bash
php artisan make:migration add_blocked_status_to_textract_jobs
```

**Migration content:**
```php
// Add 'blocked' as valid status (comment update - no schema change needed for string column)
// Update model method isReadyForGraphSync() to exclude 'blocked' status
```

**Verification:**
```bash
./scripts/run-focused-tests.sh TextractJobTest
```

**Done when:** Embedding failure sets `graph_sync_status = 'blocked'`

---

## Phase 2: Status Badge Component (4 tasks)

### Task 2.1: Create TextractStatusBadge Livewire Component

**Files to create:**
- `app/Http/Livewire/Components/TextractStatusBadge.php`

**Content:**
```php
<?php

namespace App\Http\Livewire\Components;

use App\Models\TextractJob;
use Livewire\Component;

class TextractStatusBadge extends Component
{
    public TextractJob $job;

    public function getOcrStatusColorProperty(): string
    {
        return match ($this->job->status) {
            'completed', 'succeeded' => 'bg-green-500',
            'processing' => 'bg-yellow-500 animate-pulse',
            'queued' => 'bg-gray-400',
            'failed' => 'bg-red-500',
            default => 'bg-gray-400',
        };
    }

    public function getEmbeddingStatusColorProperty(): string
    {
        return match ($this->job->embedding_status) {
            'synced' => 'bg-green-500',
            'processing' => 'bg-yellow-500 animate-pulse',
            'pending' => 'bg-gray-400',
            'failed' => 'bg-red-500',
            'blocked' => 'bg-orange-500',
            default => 'bg-gray-400',
        };
    }

    public function getGraphStatusColorProperty(): string
    {
        return match ($this->job->graph_sync_status) {
            'synced' => 'bg-green-500',
            'processing' => 'bg-yellow-500 animate-pulse',
            'pending' => 'bg-gray-400',
            'failed' => 'bg-red-500',
            'blocked' => 'bg-orange-500',
            default => 'bg-gray-400',
        };
    }

    public function getOcrTooltipProperty(): string
    {
        return match ($this->job->status) {
            'completed', 'succeeded' => 'OCR completed',
            'processing' => 'Processing...',
            'queued' => 'Queued for processing',
            'failed' => 'Failed: ' . ($this->job->error ?? 'Unknown error'),
            default => 'Unknown status',
        };
    }

    public function getEmbeddingTooltipProperty(): string
    {
        return match ($this->job->embedding_status) {
            'synced' => 'Embeddings synced at ' . $this->job->embedding_synced_at?->format('M j, Y H:i'),
            'processing' => 'Generating embeddings...',
            'pending' => 'Waiting for embeddings',
            'failed' => 'Embedding failed',
            'blocked' => 'Blocked: embeddings failed',
            default => 'Unknown status',
        };
    }

    public function getGraphTooltipProperty(): string
    {
        return match ($this->job->graph_sync_status) {
            'synced' => 'Graph synced at ' . $this->job->graph_synced_at?->format('M j, Y H:i'),
            'processing' => 'Syncing to graph...',
            'pending' => 'Waiting for graph sync',
            'failed' => 'Graph sync failed',
            'blocked' => 'Blocked: waiting for embeddings',
            default => 'Unknown status',
        };
    }

    public function render()
    {
        return view('livewire.components.textract-status-badge');
    }
}
```

**Verification:**
```bash
php artisan livewire:list | grep TextractStatusBadge
```

**Done when:** Component class exists and renders without errors

---

### Task 2.2: Create TextractStatusBadge Blade View

**Files to create:**
- `resources/views/livewire/components/textract-status-badge.blade.php`

**Content:**
```blade
<div class="flex items-center space-x-1 text-xs" x-data="{ showTooltip: null }">
    {{-- OCR Status --}}
    <div class="relative">
        <div
            class="flex items-center space-x-1 px-2 py-1 rounded-full {{ $this->ocrStatusColor }}"
            @mouseenter="showTooltip = 'ocr'"
            @mouseleave="showTooltip = null"
        >
            <span class="text-white font-medium">OCR</span>
            <span class="w-2 h-2 rounded-full bg-white/30"></span>
        </div>
        <div
            x-show="showTooltip === 'ocr'"
            x-cloak
            class="absolute z-10 bottom-full left-1/2 -translate-x-1/2 mb-1 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap"
        >
            {{ $this->ocrTooltip }}
        </div>
    </div>

    {{-- Arrow --}}
    <svg class="w-4 h-4 {{ in_array($job->status, ['completed', 'succeeded']) ? 'text-gray-600' : 'text-gray-300' }}" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd" />
    </svg>

    {{-- Embedding Status --}}
    <div class="relative">
        <div
            class="flex items-center space-x-1 px-2 py-1 rounded-full {{ $this->embeddingStatusColor }}"
            @mouseenter="showTooltip = 'embed'"
            @mouseleave="showTooltip = null"
        >
            <span class="text-white font-medium">Embed</span>
            <span class="w-2 h-2 rounded-full bg-white/30"></span>
        </div>
        <div
            x-show="showTooltip === 'embed'"
            x-cloak
            class="absolute z-10 bottom-full left-1/2 -translate-x-1/2 mb-1 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap"
        >
            {{ $this->embeddingTooltip }}
        </div>
    </div>

    {{-- Arrow --}}
    <svg class="w-4 h-4 {{ $job->embedding_status === 'synced' ? 'text-gray-600' : 'text-gray-300' }}" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd" />
    </svg>

    {{-- Graph Status --}}
    <div class="relative">
        <div
            class="flex items-center space-x-1 px-2 py-1 rounded-full {{ $this->graphStatusColor }}"
            @mouseenter="showTooltip = 'graph'"
            @mouseleave="showTooltip = null"
        >
            <span class="text-white font-medium">Graph</span>
            <span class="w-2 h-2 rounded-full bg-white/30"></span>
        </div>
        <div
            x-show="showTooltip === 'graph'"
            x-cloak
            class="absolute z-10 bottom-full left-1/2 -translate-x-1/2 mb-1 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap"
        >
            {{ $this->graphTooltip }}
        </div>
    </div>
</div>
```

**Verification:**
```bash
php artisan view:cache 2>&1 | grep -i error || echo "Views compile OK"
```

**Done when:** View renders correctly with badge styling

---

### Task 2.3: Create TextractJobActions Component

**Files to create:**
- `app/Http/Livewire/Components/TextractJobActions.php`

**Content:**
```php
<?php

namespace App\Http\Livewire\Components;

use App\Jobs\GenerateEmbeddingsJob;
use App\Jobs\ProcessTextractJob;
use App\Jobs\SyncTextractToGraph;
use App\Models\TextractJob;
use Livewire\Component;

class TextractJobActions extends Component
{
    public TextractJob $job;

    protected $listeners = ['refreshJob' => '$refresh'];

    public function getAvailableActionsProperty(): array
    {
        $actions = [];

        // OCR Actions
        if ($this->job->status === 'failed') {
            $actions[] = ['key' => 'retryOcr', 'label' => 'Retry OCR', 'icon' => 'refresh', 'danger' => true];
        }
        if (in_array($this->job->status, ['completed', 'succeeded'])) {
            $actions[] = ['key' => 'reExtract', 'label' => 'Re-extract', 'icon' => 'document-text', 'danger' => false];
        }

        // Embedding Actions
        if (in_array($this->job->status, ['completed', 'succeeded']) && $this->job->embedding_status !== 'processing') {
            $actions[] = ['key' => 'generateEmbeddings', 'label' => 'Generate Embeddings', 'icon' => 'cube', 'danger' => false];
        }
        if ($this->job->embedding_status === 'failed') {
            $actions[] = ['key' => 'retryEmbeddings', 'label' => 'Retry Embeddings', 'icon' => 'refresh', 'danger' => true];
        }

        // Graph Actions
        if ($this->job->embedding_status === 'synced' && $this->job->graph_sync_status !== 'processing') {
            $actions[] = ['key' => 'syncToGraph', 'label' => 'Sync to Graph', 'icon' => 'share', 'danger' => false];
        }
        if ($this->job->graph_sync_status === 'failed') {
            $actions[] = ['key' => 'retryGraphSync', 'label' => 'Retry Graph Sync', 'icon' => 'refresh', 'danger' => true];
        }

        // View Actions
        if (in_array($this->job->status, ['completed', 'succeeded'])) {
            $actions[] = ['key' => 'viewContent', 'label' => 'View Content', 'icon' => 'eye', 'danger' => false];
            $actions[] = ['key' => 'editContent', 'label' => 'Edit Content', 'icon' => 'pencil', 'danger' => false];
        }
        if ($this->job->graph_sync_status === 'synced') {
            $actions[] = ['key' => 'viewInGraph', 'label' => 'View in Graph', 'icon' => 'globe', 'danger' => false];
        }

        return $actions;
    }

    public function retryOcr(): void
    {
        $this->job->update(['status' => 'queued', 'error' => null]);
        ProcessTextractJob::dispatch($this->job->id);
        $this->dispatch('notify', message: 'OCR retry queued');
        $this->dispatch('refreshJobs');
    }

    public function reExtract(): void
    {
        $this->job->update([
            'status' => 'queued',
            'embedding_status' => 'pending',
            'graph_sync_status' => 'pending',
            'error' => null,
        ]);
        ProcessTextractJob::dispatch($this->job->id);
        $this->dispatch('notify', message: 'Re-extraction queued');
        $this->dispatch('refreshJobs');
    }

    public function generateEmbeddings(): void
    {
        $this->job->update(['embedding_status' => 'pending']);
        GenerateEmbeddingsJob::dispatch($this->job->id);
        $this->dispatch('notify', message: 'Embedding generation queued');
        $this->dispatch('refreshJobs');
    }

    public function retryEmbeddings(): void
    {
        $this->job->update(['embedding_status' => 'pending', 'error' => null]);
        GenerateEmbeddingsJob::dispatch($this->job->id);
        $this->dispatch('notify', message: 'Embedding retry queued');
        $this->dispatch('refreshJobs');
    }

    public function syncToGraph(): void
    {
        $this->job->update(['graph_sync_status' => 'pending']);
        SyncTextractToGraph::dispatch($this->job->id);
        $this->dispatch('notify', message: 'Graph sync queued');
        $this->dispatch('refreshJobs');
    }

    public function retryGraphSync(): void
    {
        $this->job->update(['graph_sync_status' => 'pending', 'error' => null]);
        SyncTextractToGraph::dispatch($this->job->id);
        $this->dispatch('notify', message: 'Graph sync retry queued');
        $this->dispatch('refreshJobs');
    }

    public function viewContent(): void
    {
        $this->dispatch('openContentModal', jobId: $this->job->id);
    }

    public function editContent(): void
    {
        $this->dispatch('openEditModal', jobId: $this->job->id);
    }

    public function viewInGraph(): void
    {
        $this->dispatch('openGraphView', jobId: $this->job->id);
    }

    public function render()
    {
        return view('livewire.components.textract-job-actions');
    }
}
```

**Done when:** Component handles all action methods

---

### Task 2.4: Create TextractJobActions Blade View

**Files to create:**
- `resources/views/livewire/components/textract-job-actions.blade.php`

**Content:**
```blade
<div class="relative" x-data="{ open: false }">
    <button
        @click="open = !open"
        class="p-2 text-gray-500 hover:text-gray-700 rounded-md hover:bg-gray-100"
    >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
        </svg>
    </button>

    <div
        x-show="open"
        @click.away="open = false"
        x-cloak
        class="absolute right-0 z-20 mt-2 w-48 bg-white rounded-md shadow-lg ring-1 ring-black ring-opacity-5"
    >
        <div class="py-1">
            @forelse($this->availableActions as $action)
                <button
                    wire:click="{{ $action['key'] }}"
                    class="w-full text-left px-4 py-2 text-sm {{ $action['danger'] ? 'text-red-600 hover:bg-red-50' : 'text-gray-700 hover:bg-gray-100' }} flex items-center space-x-2"
                >
                    @if($action['icon'] === 'refresh')
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                    @elseif($action['icon'] === 'eye')
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    @elseif($action['icon'] === 'pencil')
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                        </svg>
                    @else
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    @endif
                    <span>{{ $action['label'] }}</span>
                </button>
            @empty
                <div class="px-4 py-2 text-sm text-gray-500">No actions available</div>
            @endforelse
        </div>
    </div>
</div>
```

**Done when:** Dropdown renders with conditional actions

---

## Phase 3: Integrate into TextractManager (3 tasks)

### Task 3.1: Update TextractManager Component

**Files to modify:**
- `app/Http/Livewire/TextractManager.php`

**Changes:**
Add event listeners:
```php
protected $listeners = [
    'refreshJobs' => '$refresh',
    'openContentModal',
    'openEditModal',
    'openGraphView',
];
```

Add methods for modals (if not already present).

**Done when:** TextractManager listens to child component events

---

### Task 3.2: Update TextractManager View to Use Components

**Files to modify:**
- `resources/views/livewire/textract-manager.blade.php`

**Changes:**
Replace status column with status badge component:
```blade
{{-- Replace existing status display with: --}}
<td class="px-6 py-4 whitespace-nowrap">
    <livewire:components.textract-status-badge :job="$job" :key="'badge-'.$job->id" />
</td>

{{-- Add actions column: --}}
<td class="px-6 py-4 whitespace-nowrap text-right">
    <livewire:components.textract-job-actions :job="$job" :key="'actions-'.$job->id" />
</td>
```

**Done when:** Table shows status badges and action dropdowns

---

### Task 3.3: Add Metadata Summary Display

**Files to modify:**
- `resources/views/livewire/textract-manager.blade.php`

**Changes:**
Add metadata row (expandable):
```blade
@if($job->status === 'completed' && $job->metadata)
    <div class="text-xs text-gray-500 mt-1 flex items-center space-x-2">
        @if($job->metadata['document_type'] ?? null)
            <span>{{ $job->metadata['document_type'] }}</span>
        @endif
        @if($job->metadata['court_count'] ?? 0)
            <span>| {{ $job->metadata['court_count'] }} courts</span>
        @endif
        @if($job->metadata['citation_count'] ?? 0)
            <span>| {{ $job->metadata['citation_count'] }} citations</span>
        @endif
    </div>
@endif
```

**Done when:** Metadata summary shows below job name

---

## Phase 4: Tests (3 tasks)

### Task 4.1: Unit Tests for Status Badge Component

**Files to create:**
- `tests/Unit/Livewire/TextractStatusBadgeTest.php`

**Test cases:**
- Color mapping for each status value
- Tooltip text for each status
- Arrow brightness based on completion

**Done when:** All unit tests pass

---

### Task 4.2: Feature Tests for Job Actions

**Files to create:**
- `tests/Feature/Livewire/TextractJobActionsTest.php`

**Test cases:**
- Action availability based on job state
- Job dispatch on action click
- Status updates after actions

**Done when:** All feature tests pass

---

### Task 4.3: Integration Tests for Flag Flow

**Files to create:**
- `tests/Integration/TextractFlagFlowTest.php`

**Test cases:**
- Full pipeline: queued → processing → completed → embeddings → graph
- Embedding failure blocks graph sync
- Retry actions reset statuses correctly

**Done when:** Integration tests verify complete flag flow

---

## Execution Order

1. **Phase 1 first** - Fix underlying flag flow issues
2. **Phase 2** - Create UI components
3. **Phase 3** - Integrate components
4. **Phase 4** - Add tests

## Verification Commands

```bash
# Run all related tests
./scripts/run-focused-tests.sh TextractStatusBadge
./scripts/run-focused-tests.sh TextractJobActions
./scripts/run-focused-tests.sh TextractFlagFlow

# Verify Livewire components registered
php artisan livewire:list | grep -i textract

# Check for syntax errors
php artisan view:cache
php artisan route:cache
```

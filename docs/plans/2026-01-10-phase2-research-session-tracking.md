# Phase 2: Research Session Tracking - Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Enable the system to track user research sessions (viewed nodes, pinned nodes, alerts) to power Gap Analysis and Contradiction Radar features.

**Architecture:** Create a PostgreSQL-backed ResearchSession model that integrates with ForceGraphController. Sessions are lazily created when users interact with the graph and can be named/saved for future reference.

**Tech Stack:** Laravel 11, Livewire 3, PostgreSQL, PHPUnit

---

## Summary

| Task | Component | Effort |
|------|-----------|--------|
| 1-4 | Database Migration & Model | S |
| 5-8 | ResearchSessionService | M |
| 9-12 | ForceGraphController Integration | M |
| 13-16 | Session Persistence UI | S |
| 17-20 | Comprehensive Tests | M |

**Total: 20 bite-sized tasks**

---

## Task 1: Create migration for research_sessions table

**Files:**
- Create: `database/migrations/2026_01_10_000001_create_research_sessions_table.php`

**Step 1: Generate migration**

Run:
```bash
php artisan make:migration create_research_sessions_table
```

**Step 2: Write migration schema**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('research_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('name')->nullable();
            $table->text('description')->nullable();

            // JSONB columns for flexible node tracking
            $table->json('viewed_nodes')->default('[]');
            $table->json('pinned_nodes')->default('[]');
            $table->json('expanded_nodes')->default('[]');
            $table->json('alerts')->default('[]');

            // Session metadata
            $table->string('root_node_id')->nullable();
            $table->json('filter_settings')->nullable();

            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes for common queries
            $table->index(['user_id', 'last_activity_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('research_sessions');
    }
};
```

**Step 3: Run migration**

Run: `php artisan migrate`
Expected: Migration successful

**Step 4: Commit**

```bash
git add database/migrations/*_create_research_sessions_table.php
git commit -m "feat(session): Add research_sessions migration"
```

---

## Task 2: Create ResearchSession model

**Files:**
- Create: `app/Models/ResearchSession.php`

**Step 1: Generate model**

Run:
```bash
php artisan make:model ResearchSession
```

**Step 2: Implement model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ResearchSession extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'user_id',
        'name',
        'description',
        'viewed_nodes',
        'pinned_nodes',
        'expanded_nodes',
        'alerts',
        'root_node_id',
        'filter_settings',
        'last_activity_at',
    ];

    protected $casts = [
        'viewed_nodes' => 'array',
        'pinned_nodes' => 'array',
        'expanded_nodes' => 'array',
        'alerts' => 'array',
        'filter_settings' => 'array',
        'last_activity_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($session) {
            if (empty($session->uuid)) {
                $session->uuid = (string) Str::uuid();
            }
        });
    }

    // Relationships

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Query Scopes

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId)
            ->orderBy('last_activity_at', 'desc');
    }

    public function scopeActive($query)
    {
        return $query->orderBy('last_activity_at', 'desc');
    }

    public function scopeNamed($query)
    {
        return $query->whereNotNull('name');
    }

    // Helper Methods

    public function getDisplayName(): string
    {
        if ($this->name) {
            return $this->name;
        }

        return 'Session ' . $this->created_at->format('M d, Y H:i');
    }

    public function getViewedNodeIds(): array
    {
        return array_column($this->viewed_nodes ?? [], 'id');
    }

    public function getPinnedNodeIds(): array
    {
        return array_column($this->pinned_nodes ?? [], 'id');
    }

    public function getNodeCount(): int
    {
        return count($this->viewed_nodes ?? []);
    }
}
```

**Step 3: Commit**

```bash
git add app/Models/ResearchSession.php
git commit -m "feat(session): Add ResearchSession model"
```

---

## Task 3: Add researchSessions relationship to User model

**Files:**
- Modify: `app/Models/User.php`

**Step 1: Add relationship**

Add after line 109 (after `ownedCases` method):

```php
public function researchSessions()
{
    return $this->hasMany(ResearchSession::class)
        ->orderBy('last_activity_at', 'desc');
}
```

**Step 2: Commit**

```bash
git add app/Models/User.php
git commit -m "feat(session): Add researchSessions relationship to User"
```

---

## Task 4: Write model unit test

**Files:**
- Create: `tests/Unit/Models/ResearchSessionTest.php`

**Step 1: Create test file**

```php
<?php

namespace Tests\Unit\Models;

use App\Models\ResearchSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResearchSessionTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_creates_session_with_uuid()
    {
        $session = ResearchSession::create([
            'name' => 'Test Session',
        ]);

        $this->assertNotNull($session->uuid);
        $this->assertEquals(36, strlen($session->uuid));
    }

    /** @test */
    public function it_casts_json_columns_as_arrays()
    {
        $session = ResearchSession::create([
            'viewed_nodes' => [['id' => 'n1', 'type' => 'Decision']],
            'pinned_nodes' => [['id' => 'n2', 'type' => 'Law']],
        ]);

        $this->assertIsArray($session->viewed_nodes);
        $this->assertIsArray($session->pinned_nodes);
        $this->assertEquals('n1', $session->viewed_nodes[0]['id']);
    }

    /** @test */
    public function it_returns_viewed_node_ids()
    {
        $session = ResearchSession::create([
            'viewed_nodes' => [
                ['id' => 'node-1', 'type' => 'Decision'],
                ['id' => 'node-2', 'type' => 'Law'],
            ],
        ]);

        $ids = $session->getViewedNodeIds();

        $this->assertEquals(['node-1', 'node-2'], $ids);
    }

    /** @test */
    public function it_generates_display_name_when_unnamed()
    {
        $session = ResearchSession::create([]);

        $this->assertStringStartsWith('Session ', $session->getDisplayName());
    }

    /** @test */
    public function it_uses_custom_name_when_set()
    {
        $session = ResearchSession::create([
            'name' => 'My Research',
        ]);

        $this->assertEquals('My Research', $session->getDisplayName());
    }

    /** @test */
    public function it_belongs_to_user()
    {
        $user = User::factory()->create();
        $session = ResearchSession::create([
            'user_id' => $user->id,
        ]);

        $this->assertEquals($user->id, $session->user->id);
    }
}
```

**Step 2: Run test**

Run: `php artisan test --filter=ResearchSessionTest`
Expected: All tests pass

**Step 3: Commit**

```bash
git add tests/Unit/Models/ResearchSessionTest.php
git commit -m "test(session): Add ResearchSession model tests"
```

---

## Task 5: Create ResearchSessionService

**Files:**
- Create: `app/Services/Graph/ResearchSessionService.php`

**Step 1: Create service**

```php
<?php

namespace App\Services\Graph;

use App\Models\ResearchSession;
use Illuminate\Support\Facades\Auth;

class ResearchSessionService
{
    private ?ResearchSession $currentSession = null;

    /**
     * Get or create the current research session
     */
    public function getCurrentSession(): ResearchSession
    {
        if ($this->currentSession) {
            return $this->currentSession;
        }

        $userId = Auth::id();

        // Try to find recent unnamed session (within last 30 minutes)
        $this->currentSession = ResearchSession::query()
            ->where('user_id', $userId)
            ->whereNull('name')
            ->where('last_activity_at', '>', now()->subMinutes(30))
            ->orderBy('last_activity_at', 'desc')
            ->first();

        if (!$this->currentSession) {
            $this->currentSession = ResearchSession::create([
                'user_id' => $userId,
                'last_activity_at' => now(),
            ]);
        }

        return $this->currentSession;
    }

    /**
     * Set the current session by ID
     */
    public function setCurrentSession(int $sessionId): ?ResearchSession
    {
        $this->currentSession = ResearchSession::find($sessionId);
        return $this->currentSession;
    }

    /**
     * Track a viewed node
     */
    public function trackViewedNode(array $node): void
    {
        $session = $this->getCurrentSession();

        $viewedNodes = $session->viewed_nodes ?? [];

        // Don't add duplicates
        $existingIds = array_column($viewedNodes, 'id');
        if (!in_array($node['id'], $existingIds)) {
            $viewedNodes[] = [
                'id' => $node['id'],
                'type' => $node['type'] ?? null,
                'label' => $node['properties']['title'] ?? $node['properties']['case_number'] ?? $node['id'],
                'viewed_at' => now()->toISOString(),
            ];

            $session->update([
                'viewed_nodes' => $viewedNodes,
                'last_activity_at' => now(),
            ]);
        }
    }

    /**
     * Track a pinned node
     */
    public function trackPinnedNode(array $node): void
    {
        $session = $this->getCurrentSession();

        $pinnedNodes = $session->pinned_nodes ?? [];

        // Don't add duplicates
        $existingIds = array_column($pinnedNodes, 'id');
        if (!in_array($node['id'], $existingIds)) {
            $pinnedNodes[] = [
                'id' => $node['id'],
                'type' => $node['type'] ?? null,
                'label' => $node['properties']['title'] ?? $node['properties']['case_number'] ?? $node['id'],
                'pinned_at' => now()->toISOString(),
            ];

            $session->update([
                'pinned_nodes' => $pinnedNodes,
                'last_activity_at' => now(),
            ]);
        }
    }

    /**
     * Remove a pinned node
     */
    public function unpinNode(string $nodeId): void
    {
        $session = $this->getCurrentSession();

        $pinnedNodes = array_filter(
            $session->pinned_nodes ?? [],
            fn($n) => $n['id'] !== $nodeId
        );

        $session->update([
            'pinned_nodes' => array_values($pinnedNodes),
            'last_activity_at' => now(),
        ]);
    }

    /**
     * Track an expanded node
     */
    public function trackExpandedNode(string $nodeId): void
    {
        $session = $this->getCurrentSession();

        $expandedNodes = $session->expanded_nodes ?? [];

        if (!in_array($nodeId, $expandedNodes)) {
            $expandedNodes[] = $nodeId;

            $session->update([
                'expanded_nodes' => $expandedNodes,
                'last_activity_at' => now(),
            ]);
        }
    }

    /**
     * Save the session with a name
     */
    public function saveSession(string $name, ?string $description = null): ResearchSession
    {
        $session = $this->getCurrentSession();

        $session->update([
            'name' => $name,
            'description' => $description,
            'last_activity_at' => now(),
        ]);

        return $session;
    }

    /**
     * Get user's saved sessions
     */
    public function getSavedSessions(?int $userId = null): array
    {
        $userId = $userId ?? Auth::id();

        return ResearchSession::query()
            ->where('user_id', $userId)
            ->whereNotNull('name')
            ->orderBy('last_activity_at', 'desc')
            ->limit(20)
            ->get()
            ->toArray();
    }

    /**
     * Update filter settings
     */
    public function updateFilterSettings(array $settings): void
    {
        $session = $this->getCurrentSession();

        $session->update([
            'filter_settings' => $settings,
            'last_activity_at' => now(),
        ]);
    }

    /**
     * Set root node
     */
    public function setRootNode(string $nodeId): void
    {
        $session = $this->getCurrentSession();

        $session->update([
            'root_node_id' => $nodeId,
            'last_activity_at' => now(),
        ]);
    }
}
```

**Step 2: Commit**

```bash
git add app/Services/Graph/ResearchSessionService.php
git commit -m "feat(session): Add ResearchSessionService"
```

---

## Task 6: Write ResearchSessionService unit test

**Files:**
- Create: `tests/Unit/Services/Graph/ResearchSessionServiceTest.php`

**Step 1: Create test file**

```php
<?php

namespace Tests\Unit\Services\Graph;

use App\Models\ResearchSession;
use App\Models\User;
use App\Services\Graph\ResearchSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResearchSessionServiceTest extends TestCase
{
    use RefreshDatabase;

    private ResearchSessionService $service;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->service = new ResearchSessionService();
    }

    /** @test */
    public function it_creates_session_when_none_exists()
    {
        $session = $this->service->getCurrentSession();

        $this->assertInstanceOf(ResearchSession::class, $session);
        $this->assertEquals($this->user->id, $session->user_id);
    }

    /** @test */
    public function it_reuses_recent_unnamed_session()
    {
        $existingSession = ResearchSession::create([
            'user_id' => $this->user->id,
            'last_activity_at' => now(),
        ]);

        $session = $this->service->getCurrentSession();

        $this->assertEquals($existingSession->id, $session->id);
    }

    /** @test */
    public function it_tracks_viewed_nodes_without_duplicates()
    {
        $node = ['id' => 'node-1', 'type' => 'Decision', 'properties' => ['title' => 'Test']];

        $this->service->trackViewedNode($node);
        $this->service->trackViewedNode($node); // Duplicate

        $session = $this->service->getCurrentSession();

        $this->assertCount(1, $session->viewed_nodes);
        $this->assertEquals('node-1', $session->viewed_nodes[0]['id']);
    }

    /** @test */
    public function it_tracks_pinned_nodes()
    {
        $node = ['id' => 'node-1', 'type' => 'Law', 'properties' => ['title' => 'Test Law']];

        $this->service->trackPinnedNode($node);

        $session = $this->service->getCurrentSession();

        $this->assertCount(1, $session->pinned_nodes);
        $this->assertEquals('node-1', $session->pinned_nodes[0]['id']);
    }

    /** @test */
    public function it_unpins_nodes()
    {
        $node = ['id' => 'node-1', 'type' => 'Law', 'properties' => []];

        $this->service->trackPinnedNode($node);
        $this->service->unpinNode('node-1');

        $session = $this->service->getCurrentSession();

        $this->assertCount(0, $session->pinned_nodes);
    }

    /** @test */
    public function it_saves_session_with_name()
    {
        $this->service->getCurrentSession();

        $session = $this->service->saveSession('My Research', 'About property law');

        $this->assertEquals('My Research', $session->name);
        $this->assertEquals('About property law', $session->description);
    }

    /** @test */
    public function it_returns_saved_sessions_for_user()
    {
        ResearchSession::create([
            'user_id' => $this->user->id,
            'name' => 'Session 1',
            'last_activity_at' => now(),
        ]);
        ResearchSession::create([
            'user_id' => $this->user->id,
            'name' => 'Session 2',
            'last_activity_at' => now(),
        ]);
        ResearchSession::create([
            'user_id' => $this->user->id,
            // Unnamed - should not appear
            'last_activity_at' => now(),
        ]);

        $sessions = $this->service->getSavedSessions();

        $this->assertCount(2, $sessions);
    }
}
```

**Step 2: Run test**

Run: `php artisan test --filter=ResearchSessionServiceTest`
Expected: All tests pass

**Step 3: Commit**

```bash
git add tests/Unit/Services/Graph/ResearchSessionServiceTest.php
git commit -m "test(session): Add ResearchSessionService tests"
```

---

## Task 7: Register service in container

**Files:**
- Modify: `app/Providers/AppServiceProvider.php`

**Step 1: Check if service provider needs binding**

The service doesn't need explicit binding since it has no constructor dependencies. Laravel will auto-resolve it.

**Step 2: Skip (no changes needed)**

ResearchSessionService can be resolved via `app(ResearchSessionService::class)` automatically.

---

## Task 8: Add session property to ForceGraphController

**Files:**
- Modify: `app/Livewire/Graph/ForceGraphController.php`

**Step 1: Add session ID property**

Add after line 24 (after `public array $timeline = [];`):

```php
public ?int $sessionId = null;
```

**Step 2: Add import for service**

Add after line 6:

```php
use App\Services\Graph\ResearchSessionService;
```

**Step 3: Commit**

```bash
git add app/Livewire/Graph/ForceGraphController.php
git commit -m "feat(session): Add sessionId property to ForceGraphController"
```

---

## Task 9: Track viewed nodes on selection

**Files:**
- Modify: `app/Livewire/Graph/ForceGraphController.php`

**Step 1: Update selectNode method**

Find the `selectNode` method (around line 99) and add tracking after line 102 (after `$this->selectedNodeId = $node['id'];`):

```php
// Track viewed node in research session
if (Auth::check()) {
    $sessionService = app(ResearchSessionService::class);
    $sessionService->trackViewedNode($node);
    $this->sessionId = $sessionService->getCurrentSession()->id;
}
```

**Step 2: Add Auth import at top**

Add after other use statements:

```php
use Illuminate\Support\Facades\Auth;
```

**Step 3: Commit**

```bash
git add app/Livewire/Graph/ForceGraphController.php
git commit -m "feat(session): Track viewed nodes on selection"
```

---

## Task 10: Track expanded nodes

**Files:**
- Modify: `app/Livewire/Graph/ForceGraphController.php`

**Step 1: Update expandNode method**

In the `expandNode` method (around line 45), add tracking after line 52 (after `$existingIds = array_column...`):

```php
// Track expanded node in research session
if (Auth::check()) {
    $sessionService = app(ResearchSessionService::class);
    $sessionService->trackExpandedNode($nodeId);
}
```

**Step 2: Update expandNodeFiltered method**

In the `expandNodeFiltered` method (around line 71), add tracking after line 77 (after `$connections = $explorerService->...`):

```php
// Track expanded node in research session
if (Auth::check()) {
    $sessionService = app(ResearchSessionService::class);
    $sessionService->trackExpandedNode($nodeId);
}
```

**Step 3: Commit**

```bash
git add app/Livewire/Graph/ForceGraphController.php
git commit -m "feat(session): Track expanded nodes"
```

---

## Task 11: Add pin/unpin session tracking

**Files:**
- Modify: `app/Livewire/Graph/ForceGraphController.php`

**Step 1: Add pinNode Livewire method**

Add new method after `expandNodeFiltered`:

```php
#[On('pin-node')]
public function pinNode(array $node): void
{
    if (Auth::check()) {
        $sessionService = app(ResearchSessionService::class);
        $sessionService->trackPinnedNode($node);
    }
}

#[On('unpin-node')]
public function unpinNode(string $nodeId): void
{
    if (Auth::check()) {
        $sessionService = app(ResearchSessionService::class);
        $sessionService->unpinNode($nodeId);
    }
}
```

**Step 2: Commit**

```bash
git add app/Livewire/Graph/ForceGraphController.php
git commit -m "feat(session): Add pin/unpin session tracking"
```

---

## Task 12: Update ForceGraph.js to dispatch pin events

**Files:**
- Modify: `resources/js/components/ForceGraph.js`

**Step 1: Update pinNode method**

Find the `pinNode` method and update to dispatch Livewire event:

```javascript
pinNode(node) {
    if (!this.pinnedNodes.find(n => n.id === node.id)) {
        this.pinnedNodes.push({
            id: node.id,
            type: node.type,
            label: node.properties?.case_number || node.properties?.title || node.label || node.name || node.id,
        });

        // Dispatch to Livewire for session tracking
        this.$wire.pinNode(node);
    }
},
```

**Step 2: Update unpinNode method**

```javascript
unpinNode(nodeId) {
    this.pinnedNodes = this.pinnedNodes.filter(n => n.id !== nodeId);

    // Dispatch to Livewire for session tracking
    this.$wire.unpinNode(nodeId);
},
```

**Step 3: Commit**

```bash
git add resources/js/components/ForceGraph.js
git commit -m "feat(session): Dispatch pin/unpin events to Livewire"
```

---

## Task 13: Add session management methods to controller

**Files:**
- Modify: `app/Livewire/Graph/ForceGraphController.php`

**Step 1: Add session management methods**

Add after the unpin method:

```php
public function saveSession(string $name, ?string $description = null): void
{
    if (!Auth::check()) {
        return;
    }

    $sessionService = app(ResearchSessionService::class);
    $session = $sessionService->saveSession($name, $description);

    $this->dispatch('session-saved', session: $session->toArray());
}

public function loadSession(int $sessionId): void
{
    $session = ResearchSession::find($sessionId);

    if (!$session || $session->user_id !== Auth::id()) {
        return;
    }

    $sessionService = app(ResearchSessionService::class);
    $sessionService->setCurrentSession($sessionId);

    $this->sessionId = $sessionId;

    // Load the root node if set
    if ($session->root_node_id) {
        $this->loadInitialGraph($session->root_node_id);
    }

    // Dispatch session data to frontend
    $this->dispatch('session-loaded', session: $session->toArray());
}

public function getSavedSessions(): array
{
    if (!Auth::check()) {
        return [];
    }

    $sessionService = app(ResearchSessionService::class);
    return $sessionService->getSavedSessions();
}
```

**Step 2: Add ResearchSession import**

Add at top with other imports:

```php
use App\Models\ResearchSession;
```

**Step 3: Commit**

```bash
git add app/Livewire/Graph/ForceGraphController.php
git commit -m "feat(session): Add session save/load methods"
```

---

## Task 14: Create session management UI partial

**Files:**
- Create: `resources/views/livewire/graph/partials/session-manager.blade.php`

**Step 1: Create the partial**

```blade
{{-- resources/views/livewire/graph/partials/session-manager.blade.php --}}
<div x-data="{ showSaveModal: false, showLoadModal: false, sessionName: '', sessionDescription: '' }" class="relative">
    {{-- Session indicator --}}
    <div class="flex items-center gap-2 mb-4">
        <span class="text-xs text-gray-500">Session:</span>
        <span class="text-xs text-gray-300" x-text="$wire.sessionId ? 'Active #' + $wire.sessionId : 'New'"></span>

        <button
            @click="showSaveModal = true"
            class="text-xs text-blue-400 hover:underline ml-2"
        >
            Save
        </button>
        <button
            @click="showLoadModal = true; $wire.getSavedSessions().then(s => sessions = s)"
            class="text-xs text-gray-400 hover:underline"
        >
            Load
        </button>
    </div>

    {{-- Save Modal --}}
    <div
        x-show="showSaveModal"
        x-transition
        class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
        @click.self="showSaveModal = false"
    >
        <div class="bg-gray-800 rounded-lg p-6 w-96 max-w-full">
            <h3 class="text-lg font-semibold text-white mb-4">Save Research Session</h3>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm text-gray-400 mb-1">Session Name</label>
                    <input
                        type="text"
                        x-model="sessionName"
                        class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white text-sm"
                        placeholder="e.g., Property Law Research"
                    >
                </div>
                <div>
                    <label class="block text-sm text-gray-400 mb-1">Description (optional)</label>
                    <textarea
                        x-model="sessionDescription"
                        class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white text-sm"
                        rows="2"
                        placeholder="Notes about this research..."
                    ></textarea>
                </div>
            </div>

            <div class="flex justify-end gap-2 mt-6">
                <button
                    @click="showSaveModal = false"
                    class="px-4 py-2 text-sm text-gray-400 hover:text-white"
                >
                    Cancel
                </button>
                <button
                    @click="$wire.saveSession(sessionName, sessionDescription); showSaveModal = false; sessionName = ''; sessionDescription = ''"
                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded"
                >
                    Save Session
                </button>
            </div>
        </div>
    </div>

    {{-- Load Modal --}}
    <div
        x-show="showLoadModal"
        x-transition
        x-data="{ sessions: [] }"
        class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
        @click.self="showLoadModal = false"
    >
        <div class="bg-gray-800 rounded-lg p-6 w-96 max-w-full max-h-96 overflow-y-auto">
            <h3 class="text-lg font-semibold text-white mb-4">Load Research Session</h3>

            <div class="space-y-2">
                <template x-for="session in sessions" :key="session.id">
                    <button
                        @click="$wire.loadSession(session.id); showLoadModal = false"
                        class="w-full text-left p-3 bg-gray-700 hover:bg-gray-600 rounded"
                    >
                        <div class="text-sm text-white" x-text="session.name"></div>
                        <div class="text-xs text-gray-400">
                            <span x-text="session.viewed_nodes?.length || 0"></span> nodes viewed
                            · <span x-text="new Date(session.last_activity_at).toLocaleDateString()"></span>
                        </div>
                    </button>
                </template>

                <div x-show="sessions.length === 0" class="text-sm text-gray-400 text-center py-4">
                    No saved sessions yet
                </div>
            </div>

            <div class="flex justify-end mt-4">
                <button
                    @click="showLoadModal = false"
                    class="px-4 py-2 text-sm text-gray-400 hover:text-white"
                >
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>
```

**Step 2: Commit**

```bash
git add resources/views/livewire/graph/partials/session-manager.blade.php
git commit -m "feat(session): Add session management UI partial"
```

---

## Task 15: Include session manager in force-graph.blade.php

**Files:**
- Modify: `resources/views/livewire/graph/force-graph.blade.php`

**Step 1: Include the partial**

Find the legend panel area (around line 107) and add BEFORE the legend:

```blade
@include('livewire.graph.partials.session-manager')
```

**Step 2: Commit**

```bash
git add resources/views/livewire/graph/force-graph.blade.php
git commit -m "feat(session): Include session manager in graph UI"
```

---

## Task 16: Add session-loaded event listener to ForceGraph.js

**Files:**
- Modify: `resources/js/components/ForceGraph.js`

**Step 1: Add event listener in init()**

Add in the `init()` method after other event listeners:

```javascript
// Listen for session load events
this.$wire.on('session-loaded', (data) => {
    const session = data.session;

    // Restore pinned nodes
    if (session.pinned_nodes && session.pinned_nodes.length > 0) {
        this.pinnedNodes = session.pinned_nodes;
    }

    // Restore filter settings
    if (session.filter_settings) {
        if (session.filter_settings.activeRelationships) {
            this.filters.activeRelationships = session.filter_settings.activeRelationships;
        }
        if (session.filter_settings.activeTypes) {
            this.filters.activeTypes = session.filter_settings.activeTypes;
        }
    }

    this.updateGraph();
});
```

**Step 2: Commit**

```bash
git add resources/js/components/ForceGraph.js
git commit -m "feat(session): Add session-loaded event listener"
```

---

## Task 17: Write feature test for session tracking

**Files:**
- Create: `tests/Feature/Graph/ResearchSessionTrackingTest.php`

**Step 1: Create test file**

```php
<?php

namespace Tests\Feature\Graph;

use App\Livewire\Graph\ForceGraphController;
use App\Models\ResearchSession;
use App\Models\User;
use App\Services\GraphDatabaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class ResearchSessionTrackingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /** @test */
    public function it_tracks_viewed_nodes_for_authenticated_users()
    {
        $graphMock = Mockery::mock(GraphDatabaseService::class);
        $graphMock->shouldReceive('getNodeWithConnections')->andReturn(['nodes' => [], 'edges' => []]);
        $graphMock->shouldReceive('getArgumentsForDecision')->andReturn([]);
        $graphMock->shouldReceive('getEvidenceForDecision')->andReturn([]);
        $graphMock->shouldReceive('getDateEventsForDecision')->andReturn([]);
        $this->app->instance(GraphDatabaseService::class, $graphMock);

        $this->actingAs($this->user);

        Livewire::test(ForceGraphController::class)
            ->dispatch('node-selected', node: ['id' => 'test-node', 'type' => 'Decision']);

        $session = ResearchSession::where('user_id', $this->user->id)->first();

        $this->assertNotNull($session);
        $this->assertCount(1, $session->viewed_nodes);
        $this->assertEquals('test-node', $session->viewed_nodes[0]['id']);
    }

    /** @test */
    public function it_saves_session_with_name()
    {
        $graphMock = Mockery::mock(GraphDatabaseService::class);
        $graphMock->shouldReceive('getNodeWithConnections')->andReturn(['nodes' => [], 'edges' => []]);
        $this->app->instance(GraphDatabaseService::class, $graphMock);

        $this->actingAs($this->user);

        Livewire::test(ForceGraphController::class)
            ->call('saveSession', 'My Research', 'About contracts')
            ->assertDispatched('session-saved');

        $session = ResearchSession::where('user_id', $this->user->id)
            ->where('name', 'My Research')
            ->first();

        $this->assertNotNull($session);
        $this->assertEquals('About contracts', $session->description);
    }

    /** @test */
    public function it_loads_saved_session()
    {
        $graphMock = Mockery::mock(GraphDatabaseService::class);
        $graphMock->shouldReceive('getNodeWithConnections')->andReturn([
            'nodes' => [['id' => 'root-node', 'type' => 'Decision']],
            'edges' => [],
        ]);
        $this->app->instance(GraphDatabaseService::class, $graphMock);

        $session = ResearchSession::create([
            'user_id' => $this->user->id,
            'name' => 'Saved Session',
            'root_node_id' => 'root-node',
            'pinned_nodes' => [['id' => 'pinned-1', 'type' => 'Law']],
            'last_activity_at' => now(),
        ]);

        $this->actingAs($this->user);

        Livewire::test(ForceGraphController::class)
            ->call('loadSession', $session->id)
            ->assertSet('sessionId', $session->id)
            ->assertDispatched('session-loaded');
    }

    /** @test */
    public function it_does_not_track_for_guests()
    {
        $graphMock = Mockery::mock(GraphDatabaseService::class);
        $graphMock->shouldReceive('getNodeWithConnections')->andReturn(['nodes' => [], 'edges' => []]);
        $graphMock->shouldReceive('getArgumentsForDecision')->andReturn([]);
        $graphMock->shouldReceive('getEvidenceForDecision')->andReturn([]);
        $graphMock->shouldReceive('getDateEventsForDecision')->andReturn([]);
        $this->app->instance(GraphDatabaseService::class, $graphMock);

        // No actingAs - guest user

        Livewire::test(ForceGraphController::class)
            ->dispatch('node-selected', node: ['id' => 'test-node', 'type' => 'Decision']);

        $this->assertEquals(0, ResearchSession::count());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
```

**Step 2: Run test**

Run: `php artisan test --filter=ResearchSessionTrackingTest`
Expected: All tests pass

**Step 3: Commit**

```bash
git add tests/Feature/Graph/ResearchSessionTrackingTest.php
git commit -m "test(session): Add feature tests for session tracking"
```

---

## Task 18: Run full test suite for graph features

**Step 1: Run all graph tests**

Run: `php artisan test --filter=Graph`
Expected: All tests pass

**Step 2: Run all session tests**

Run: `php artisan test --filter=Session`
Expected: All tests pass

---

## Task 19: Update filter settings tracking

**Files:**
- Modify: `resources/js/components/ForceGraph.js`

**Step 1: Add filter change tracking**

Add a method to save filter settings when they change:

```javascript
saveFilterSettings() {
    const settings = {
        activeRelationships: this.filters.activeRelationships,
        activeTypes: this.filters.activeTypes,
    };

    // Only save if we have a Livewire connection
    if (this.$wire) {
        this.$wire.call('updateFilterSettings', settings);
    }
},
```

**Step 2: Call it from filter toggle methods**

In `toggleRelationship`, `selectAllRelationships`, and `clearAllRelationships`, add at the end:

```javascript
this.saveFilterSettings();
```

**Step 3: Commit**

```bash
git add resources/js/components/ForceGraph.js
git commit -m "feat(session): Track filter settings changes"
```

---

## Task 20: Final integration and push

**Step 1: Run all tests**

Run: `php artisan test --filter=Graph,Session`
Expected: All tests pass

**Step 2: Final commit if needed**

```bash
git status
git add -A
git commit -m "feat(session): Complete Phase 2 - Research Session Tracking"
```

**Step 3: Push to remote**

```bash
git push -u origin claude/graph-enhancement-data-integrity-XqqqL
```

---

## Summary

**Phase 2 Complete!** Research Session Tracking now includes:

- ✅ ResearchSession model with PostgreSQL storage
- ✅ ResearchSessionService for session management
- ✅ Tracking of viewed, pinned, and expanded nodes
- ✅ Session save/load functionality
- ✅ Filter settings persistence
- ✅ Session management UI
- ✅ Comprehensive test coverage

**Next Phase:** Contradiction Radar (uses session data to detect conflicts)

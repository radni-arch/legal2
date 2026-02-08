# TextractManager Content Preview & Editing - Implementation Plan

> **Project**: AI Legal War Machine
> **Component**: TextractManager Livewire Component
> **Date**: 2025-10-27
> **Purpose**: Add content preview, editing, and multi-layer synchronization capabilities

---

## 📋 Executive Summary

This implementation plan details the enhancement of the TextractManager Livewire component to support:
- **Content Preview**: Read-only modal for viewing OCR-extracted text
- **Content Editing**: Manual correction of OCR errors (crucial for handwritten/cursive documents)
- **Metadata Editing**: Update document metadata and case associations
- **Multi-layer Sync**: Automatic propagation of changes to embeddings and GraphDB

The implementation follows proven patterns from the existing IngestedLawsManager component and integrates with the existing embedding (OpenAI) and GraphDB (Neo4j) infrastructure.

---

## 🎯 Business Requirements

### Problem Statement
OCR technology (AWS Textract) occasionally fails to accurately extract text from:
- Handwritten documents
- Cursive writing
- Poor quality scans
- Complex layouts

Currently, there is no way to manually correct these errors, which impacts:
- Search quality (embeddings based on incorrect text)
- Knowledge graph accuracy (relationships based on faulty content)
- Legal analysis reliability

### Solution
Implement a comprehensive content management system that allows:
1. **Viewing** OCR results in a clean, readable format
2. **Editing** content manually when OCR fails
3. **Updating** associated metadata and case references
4. **Synchronizing** changes across all data layers automatically

---

## 🏗️ Technical Architecture

### Current State Analysis

#### TextractManager Component
- **Location**: `app/Http/Livewire/TextractManager.php` (444 lines)
- **View**: `resources/views/livewire/textract-manager.blade.php` (704 lines)
- **Current Features**:
  - Job management (queue, process, retry)
  - OCR quality analysis
  - Google Drive synchronization
  - Case file association
  - Status tracking
- **Missing Features**:
  - No content preview
  - No content editing
  - No embedding regeneration
  - No graph synchronization

#### Related Models
- **TextractJob**: Stores OCR job metadata, status, and quality metrics
- **LegalCase**: Case documents that Textract jobs belong to

#### Integration Points
- **LawVectorStoreService**: Generates embeddings for law documents
- **CourtDecisionVectorStoreService**: Generates embeddings for court decisions
- **GraphRagService**: Syncs documents to Neo4j knowledge graph
- **GraphDatabaseService**: Low-level Neo4j operations

### Target Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    TextractManager UI                       │
│  (Livewire Component + Blade Views)                         │
├─────────────────────────────────────────────────────────────┤
│  • Job List & Filters                                       │
│  • Content View Modal (Read-Only) ◄─── NEW                 │
│  • Content Edit Modal (Editable) ◄──── NEW                 │
│  • Sync Status Indicators ◄──────────── NEW                │
└────────────┬───────────────────────────┬────────────────────┘
             │                           │
             │ User Saves Changes        │
             ▼                           │
┌─────────────────────────┐              │
│   TextractJob Model     │              │
│  (Enhanced Fields)      │◄─────────────┘
├─────────────────────────┤
│ • extracted_content     │
│ • manual_content        │ ◄─── NEW
│ • manually_edited       │ ◄─── NEW
│ • embedding_status      │ ◄─── NEW
│ • graph_sync_status     │ ◄─── NEW
└────────┬────────────────┘
         │
         │ Model Observer Triggers
         │
         ├─────────────────┬─────────────────┐
         ▼                 ▼                 ▼
┌──────────────────┐ ┌────────────────┐ ┌──────────────┐
│ Regenerate       │ │  Sync to       │ │ Notification │
│ Embeddings Job   │ │  Graph Job     │ │   Queue      │
│ (Async)          │ │  (Async)       │ │              │
└────────┬─────────┘ └────────┬───────┘ └──────────────┘
         │                    │
         ▼                    ▼
┌──────────────────┐ ┌────────────────┐
│ TextractVector   │ │  GraphRag      │
│ StoreService     │ │  Service       │
├──────────────────┤ ├────────────────┤
│ • Chunk content  │ │ • Create nodes │
│ • Generate       │ │ • Extract      │
│   embeddings     │ │   keywords     │
│ • Store vectors  │ │ • Link to case │
└────────┬─────────┘ └────────┬───────┘
         │                    │
         ▼                    ▼
┌──────────────────┐ ┌────────────────┐
│ textract_        │ │   Neo4j        │
│ documents        │ │   Graph DB     │
│ (Vector Store)   │ │                │
└──────────────────┘ └────────────────┘
```

---

## 📦 Component Details

### 1. Database Schema Changes

#### New Migration: `add_content_editing_to_textract_jobs`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('textract_jobs', function (Blueprint $table) {
            // Content storage
            $table->longText('extracted_content')->nullable()
                ->after('error')
                ->comment('Raw OCR-extracted text from AWS Textract');

            $table->longText('manual_content')->nullable()
                ->after('extracted_content')
                ->comment('Manually edited content (overrides extracted_content)');

            // Editing metadata
            $table->boolean('manually_edited')->default(false)
                ->after('manual_content')
                ->comment('Flag indicating if content was manually edited');

            $table->timestamp('content_edited_at')->nullable()
                ->after('manually_edited')
                ->comment('Timestamp of last content edit');

            $table->string('edited_by')->nullable()
                ->after('content_edited_at')
                ->comment('User who performed the edit');

            // Sync status tracking
            $table->string('embedding_status')->default('pending')
                ->after('edited_by')
                ->comment('Status: pending|processing|completed|failed');

            $table->string('graph_sync_status')->default('pending')
                ->after('embedding_status')
                ->comment('Status: pending|processing|completed|failed');

            $table->timestamp('embedding_synced_at')->nullable()
                ->after('graph_sync_status')
                ->comment('Last successful embedding sync timestamp');

            $table->timestamp('graph_synced_at')->nullable()
                ->after('embedding_synced_at')
                ->comment('Last successful graph sync timestamp');

            // Indexes for sync status queries
            $table->index('embedding_status');
            $table->index('graph_sync_status');
        });
    }

    public function down(): void
    {
        Schema::table('textract_jobs', function (Blueprint $table) {
            $table->dropColumn([
                'extracted_content',
                'manual_content',
                'manually_edited',
                'content_edited_at',
                'edited_by',
                'embedding_status',
                'graph_sync_status',
                'embedding_synced_at',
                'graph_synced_at',
            ]);
        });
    }
};
```

#### New Table: `textract_documents` (Vector Store)

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('textract_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('textract_job_id')
                ->constrained('textract_jobs')
                ->cascadeOnDelete();
            $table->foreignId('case_id')
                ->nullable()
                ->constrained('legal_cases')
                ->nullOnDelete();

            $table->integer('chunk_index')->default(0);
            $table->text('content');

            // Vector embedding (PostgreSQL pgvector or JSON)
            if (config('database.default') === 'pgsql') {
                $table->vector('embedding', 1536)->nullable();
            } else {
                $table->json('embedding')->nullable();
            }

            // Embedding metadata
            $table->string('embedding_provider')->default('openai');
            $table->string('embedding_model')->default('text-embedding-3-small');
            $table->integer('embedding_dimensions')->default(1536);
            $table->integer('token_count')->nullable();

            // Additional metadata
            $table->json('metadata')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('textract_job_id');
            $table->index('case_id');
            $table->index('chunk_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('textract_documents');
    }
};
```

---

### 2. Model Enhancements

#### TextractJob Model Updates

**Location**: `app/Models/TextractJob.php`

```php
<?php

namespace App\Models;

use App\Jobs\RegenerateTextractEmbeddings;
use App\Jobs\SyncTextractToGraph;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TextractJob extends Model
{
    protected $fillable = [
        'drive_file_id',
        'drive_file_name',
        'case_id',
        's3_key',
        'job_id',
        'status',
        'error',
        'metadata',
        'extracted_content',      // NEW
        'manual_content',         // NEW
        'manually_edited',        // NEW
        'content_edited_at',      // NEW
        'edited_by',              // NEW
        'embedding_status',       // NEW
        'graph_sync_status',      // NEW
        'embedding_synced_at',    // NEW
        'graph_synced_at',        // NEW
    ];

    protected $casts = [
        'metadata' => 'array',
        'manually_edited' => 'boolean',
        'content_edited_at' => 'datetime',
        'embedding_synced_at' => 'datetime',
        'graph_synced_at' => 'datetime',
    ];

    // Relationships
    public function case(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class, 'case_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(TextractDocument::class);
    }

    // Accessors

    /**
     * Get the active content (manual if edited, otherwise extracted)
     */
    public function getActiveContentAttribute(): ?string
    {
        return $this->manually_edited && $this->manual_content
            ? $this->manual_content
            : $this->extracted_content;
    }

    /**
     * Get content statistics
     */
    public function getContentStatsAttribute(): array
    {
        $content = $this->activeContent ?? '';
        return [
            'chars' => strlen($content),
            'words' => str_word_count($content),
            'lines' => substr_count($content, "\n") + 1,
            'paragraphs' => count(array_filter(explode("\n\n", $content))),
        ];
    }

    /**
     * Check if content needs embedding sync
     */
    public function needsEmbeddingSync(): bool
    {
        return in_array($this->embedding_status, ['pending', 'failed']);
    }

    /**
     * Check if content needs graph sync
     */
    public function needsGraphSync(): bool
    {
        return in_array($this->graph_sync_status, ['pending', 'failed']);
    }

    // Model Events

    protected static function booted(): void
    {
        // Trigger syncs when content changes
        static::updated(function (TextractJob $job) {
            // Check if content fields changed
            $contentChanged = $job->isDirty([
                'manual_content',
                'extracted_content'
            ]);

            $metadataChanged = $job->isDirty(['metadata']);

            if ($contentChanged) {
                // Reset sync status when content changes
                if ($job->embedding_status === 'completed') {
                    $job->updateQuietly(['embedding_status' => 'pending']);
                }
                if ($job->graph_sync_status === 'completed') {
                    $job->updateQuietly(['graph_sync_status' => 'pending']);
                }

                // Trigger embedding regeneration
                if (config('embeddings.auto_sync', true)) {
                    dispatch(new RegenerateTextractEmbeddings($job->id));
                }

                // Trigger graph sync
                if (config('neo4j.sync.auto_sync', true)) {
                    dispatch(new SyncTextractToGraph($job->id));
                }
            }
        });

        // Clean up graph node when job is deleted
        static::deleted(function (TextractJob $job) {
            if (config('neo4j.sync.enabled', true)) {
                app(\App\Services\GraphDatabaseService::class)
                    ->deleteNode('TextractDocument', $job->id);
            }
        });
    }
}
```

#### New Model: TextractDocument

**Location**: `app/Models/TextractDocument.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TextractDocument extends Model
{
    protected $fillable = [
        'textract_job_id',
        'case_id',
        'chunk_index',
        'content',
        'embedding',
        'embedding_provider',
        'embedding_model',
        'embedding_dimensions',
        'token_count',
        'metadata',
    ];

    protected $casts = [
        'embedding' => 'array',
        'metadata' => 'array',
    ];

    public function textractJob(): BelongsTo
    {
        return $this->belongsTo(TextractJob::class);
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class, 'case_id');
    }
}
```

---

### 3. Service Layer

#### A. TextractVectorStoreService (NEW)

**Location**: `app/Services/TextractVectorStoreService.php`

**Responsibilities**:
- Chunk textract content into manageable pieces
- Generate embeddings via OpenAI API
- Store embeddings in `textract_documents` table
- Handle retry logic with exponential backoff

**Key Methods**:
- `ingestTextractJob(TextractJob $job): void` - Main ingestion method
- `chunkText(string $text): array` - Sentence-aware chunking
- `generateEmbedding(string $text): array` - OpenAI API call with retry
- `formatEmbedding(array $embedding): string|array` - Database-specific formatting

**Implementation**: See full code in Architecture section above (Section 6.A)

---

#### B. GraphRagService Enhancement

**Location**: `app/Services/GraphRagService.php`

**New Method**:
```php
/**
 * Sync a TextractJob to the knowledge graph
 *
 * @param TextractJob $job
 * @return void
 */
public function syncTextractJob(TextractJob $job): void
{
    $content = $job->activeContent;

    if (empty($content)) {
        Log::warning("TextractJob {$job->id} has no content to sync");
        return;
    }

    // 1. Create/update document node
    $this->graphDb->upsertNode('TextractDocument', $job->id, [
        'drive_file_name' => $job->drive_file_name,
        'drive_file_id' => $job->drive_file_id,
        'status' => $job->status,
        'manually_edited' => $job->manually_edited,
        'content_preview' => substr($content, 0, 500),
        'word_count' => str_word_count($content),
        'has_case' => !is_null($job->case_id),
        'updated_at' => $job->updated_at->toIso8601String(),
    ]);

    // 2. Link to case if assigned
    if ($job->case_id) {
        $case = $job->case;

        // Ensure case node exists
        $this->graphDb->upsertNode('CaseDocument', $case->id, [
            'title' => $case->title ?? $case->case_number,
            'case_number' => $case->case_number,
        ]);

        // Create relationship
        $this->graphDb->createRelationship(
            'TextractDocument', $job->id,
            'CaseDocument', $case->id,
            'BELONGS_TO_CASE',
            ['assigned_at' => now()->toIso8601String()]
        );
    }

    // 3. Extract and link keywords
    $keywords = $this->extractKeywords($content, limit: 20);
    foreach ($keywords as $keyword) {
        $keywordId = strtolower(str_replace(' ', '_', $keyword));

        $this->graphDb->upsertNode('Keyword', $keywordId, [
            'term' => $keyword,
            'normalized' => $keywordId,
        ]);

        $this->graphDb->createRelationship(
            'TextractDocument', $job->id,
            'Keyword', $keywordId,
            'HAS_KEYWORD'
        );
    }

    // 4. Extract legal entities (if metadata contains them)
    if (isset($job->metadata['entities'])) {
        foreach ($job->metadata['entities'] as $entity) {
            if (isset($entity['type'], $entity['text'])) {
                $this->createEntityNode($entity, $job);
            }
        }
    }

    // 5. Find similar documents using embeddings
    if ($job->embedding_status === 'completed') {
        $similarJobs = $this->findSimilarTextractJobs($job, threshold: 0.85, limit: 5);

        foreach ($similarJobs as $similarJob) {
            $this->graphDb->createRelationship(
                'TextractDocument', $job->id,
                'TextractDocument', $similarJob->id,
                'SIMILAR_TO',
                ['similarity' => $similarJob->similarity_score]
            );
        }
    }

    Log::info("Synced TextractJob {$job->id} to graph with {$keywords->count()} keywords");
}

/**
 * Find similar TextractJobs using vector similarity
 */
protected function findSimilarTextractJobs(TextractJob $job, float $threshold = 0.85, int $limit = 5): Collection
{
    // Get representative embedding (first chunk)
    $primaryDoc = TextractDocument::where('textract_job_id', $job->id)
        ->where('chunk_index', 0)
        ->first();

    if (!$primaryDoc || !$primaryDoc->embedding) {
        return collect();
    }

    // Query similar documents using cosine similarity
    $similar = TextractDocument::where('textract_job_id', '!=', $job->id)
        ->where('chunk_index', 0) // Compare only first chunks
        ->whereNotNull('embedding')
        ->selectRaw('textract_job_id')
        ->selectRaw($this->cosineSimilarityQuery($primaryDoc->embedding) . ' as similarity')
        ->havingRaw('similarity >= ?', [$threshold])
        ->orderByDesc('similarity')
        ->limit($limit)
        ->get();

    return $similar;
}

/**
 * Create entity node and relationship
 */
protected function createEntityNode(array $entity, TextractJob $job): void
{
    $entityId = strtolower(str_replace(' ', '_', $entity['text']));
    $label = ucfirst($entity['type']); // Person, Organization, Location, etc.

    $this->graphDb->upsertNode($label, $entityId, [
        'name' => $entity['text'],
        'type' => $entity['type'],
        'confidence' => $entity['confidence'] ?? null,
    ]);

    $this->graphDb->createRelationship(
        'TextractDocument', $job->id,
        $label, $entityId,
        'MENTIONS_' . strtoupper($entity['type'])
    );
}
```

---

### 4. Queue Jobs

#### A. RegenerateTextractEmbeddings

**Location**: `app/Jobs/RegenerateTextractEmbeddings.php`

**Purpose**: Asynchronously regenerate embeddings when content is edited

**Features**:
- 3 retry attempts with 60-second backoff
- Updates embedding_status throughout process
- Logs success/failure
- Handles errors gracefully

**Full Implementation**: See Architecture section above (Section 5.A)

---

#### B. SyncTextractToGraph

**Location**: `app/Jobs/SyncTextractToGraph.php`

**Purpose**: Asynchronously sync TextractJob to Neo4j knowledge graph

**Features**:
- 3 retry attempts with 60-second backoff
- Updates graph_sync_status throughout process
- Calls GraphRagService::syncTextractJob()
- Logs success/failure

**Full Implementation**: See Architecture section above (Section 5.B)

---

### 5. Livewire Component Enhancement

#### TextractManager Component

**Location**: `app/Http/Livewire/TextractManager.php`

**New Properties**:
```php
// Content preview modal
public bool $showContentViewModal = false;
public ?array $viewingContent = null;

// Content edit modal
public bool $showContentEditModal = false;
public array $editingContent = [];

// Tab state for viewing
public string $contentTab = 'content'; // 'content' | 'metadata' | 'quality'
```

**New Methods** (see full implementations in Architecture section above):
- `viewContent(int $jobId): void` - Display read-only preview modal
- `editContent(int $jobId): void` - Open edit modal with job data
- `saveContent(): void` - Validate and save edited content
- `resetToOriginal(int $jobId): void` - Discard edits, restore OCR content
- `regenerateEmbeddings(int $jobId): void` - Manually trigger embedding job
- `syncToGraph(int $jobId): void` - Manually trigger graph sync job

**Validation Rules**:
```php
protected function contentEditRules(): array
{
    return [
        'editingContent.manual_content' => ['required', 'string', 'max:5000000'],
        'editingContent.metadata' => ['nullable', 'array'],
        'editingContent.case_id' => ['nullable', 'exists:legal_cases,id'],
    ];
}
```

---

### 6. View Templates

#### Content View Modal (Read-Only)

**Location**: `resources/views/livewire/textract-manager.blade.php` (add at end)

**Features**:
- Three tabs: Content, Metadata, OCR Quality
- Shows active content (manual or extracted)
- Displays document statistics
- Shows edit history if manually edited
- Formatted metadata table view
- OCR quality metrics visualization
- "Edit Content" button for quick access

**Full Template**: See Architecture section above (Section 4.A.A)

---

#### Content Edit Modal

**Location**: `resources/views/livewire/textract-manager.blade.php` (add at end)

**Features**:
- Warning banner about triggering syncs
- Case selection dropdown
- Large textarea for content editing (20 rows, monospace)
- Word count display
- JSON metadata textarea with validation
- "Reset to Original" button
- Save/Cancel actions
- Real-time validation errors

**Full Template**: See Architecture section above (Section 4.A.B)

---

#### Job Card Action Buttons

**Location**: `resources/views/livewire/textract-manager.blade.php` (modify existing job card section)

Add buttons to each job card:
```blade
<div class="flex gap-2" style="margin-top:12px">
    <button wire:click="viewContent({{ $job->id }})"
            class="btn"
            title="Preview content">
        👁️ View
    </button>

    <button wire:click="editContent({{ $job->id }})"
            class="btn"
            title="Edit content and metadata"
            @if(empty($job->extracted_content)) disabled @endif>
        ✏️ Edit
    </button>

    @if($job->manually_edited)
        <span class="badge" style="background:#10b981">
            ✏️ Edited
        </span>
    @endif

    {{-- Existing buttons (Process, Details, etc.) --}}
</div>
```

---

#### Sync Status Indicators

Add to job details modal:
```blade
<div class="seg">
    <div style="font-weight:600; margin-bottom:12px">Synchronization Status</div>
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px">
        {{-- Embedding Status --}}
        <div>
            <div style="font-size:13px; color:var(--muted); margin-bottom:4px">
                Embeddings
            </div>
            <div style="display:flex; align-items:center; gap:8px">
                @switch($selectedJobData['embedding_status'] ?? 'pending')
                    @case('completed')
                        <span class="badge" style="background:#10b981">✓ Synced</span>
                        <span class="text-xs text-muted">
                            {{ $selectedJobData['embedding_synced_at']?->diffForHumans() }}
                        </span>
                        @break
                    @case('processing')
                        <span class="badge" style="background:#3b82f6">⏳ Processing</span>
                        @break
                    @case('failed')
                        <span class="badge error">✗ Failed</span>
                        <button wire:click="regenerateEmbeddings({{ $selectedJobId }})"
                                class="btn sm">
                            🔄 Retry
                        </button>
                        @break
                    @default
                        <span class="badge" style="background:#6b7280">⏸ Pending</span>
                        <button wire:click="regenerateEmbeddings({{ $selectedJobId }})"
                                class="btn sm">
                            ▶️ Sync Now
                        </button>
                @endswitch
            </div>
        </div>

        {{-- Graph Sync Status --}}
        <div>
            <div style="font-size:13px; color:var(--muted); margin-bottom:4px">
                Knowledge Graph
            </div>
            <div style="display:flex; align-items:center; gap:8px">
                @switch($selectedJobData['graph_sync_status'] ?? 'pending')
                    @case('completed')
                        <span class="badge" style="background:#10b981">✓ Synced</span>
                        <span class="text-xs text-muted">
                            {{ $selectedJobData['graph_synced_at']?->diffForHumans() }}
                        </span>
                        @break
                    @case('processing')
                        <span class="badge" style="background:#3b82f6">⏳ Processing</span>
                        @break
                    @case('failed')
                        <span class="badge error">✗ Failed</span>
                        <button wire:click="syncToGraph({{ $selectedJobId }})"
                                class="btn sm">
                            🔄 Retry
                        </button>
                        @break
                    @default
                        <span class="badge" style="background:#6b7280">⏸ Pending</span>
                        <button wire:click="syncToGraph({{ $selectedJobId }})"
                                class="btn sm">
                            ▶️ Sync Now
                        </button>
                @endswitch
            </div>
        </div>
    </div>
</div>
```

---

## 🔄 Data Flow & Integration

### Content Update Flow

```
1. User clicks "Edit" on a TextractJob
   ↓
2. TextractManager::editContent() loads job data
   ↓
3. Edit modal displays with current content
   ↓
4. User modifies content and/or metadata
   ↓
5. User clicks "Save Changes"
   ↓
6. TextractManager::saveContent() validates input
   ↓
7. TextractJob model updated with new data
   ↓
8. Model observer (booted method) detects changes
   ↓
   ├─→ Dispatches RegenerateTextractEmbeddings job (async)
   │   ↓
   │   TextractVectorStoreService::ingestTextractJob()
   │   ↓
   │   - Chunks content
   │   - Generates embeddings via OpenAI
   │   - Deletes old TextractDocument records
   │   - Creates new TextractDocument records
   │   - Updates embedding_status = 'completed'
   │
   └─→ Dispatches SyncTextractToGraph job (async)
       ↓
       GraphRagService::syncTextractJob()
       ↓
       - Creates/updates TextractDocument node
       - Extracts keywords → creates Keyword nodes
       - Links to CaseDocument if case_id exists
       - Extracts entities → creates entity nodes
       - Finds similar documents → creates SIMILAR_TO relationships
       - Updates graph_sync_status = 'completed'
```

### Manual Sync Trigger Flow

```
User clicks "Sync Now" button in job details
   ↓
TextractManager::regenerateEmbeddings() OR syncToGraph()
   ↓
Updates status to 'pending'
   ↓
Dispatches respective job
   ↓
Job processes asynchronously
   ↓
Status updates to 'processing' → 'completed' or 'failed'
```

---

## 📝 Implementation Tasks

### Phase 1: Database & Models ⭐ HIGH PRIORITY

- [ ] **Task 1.1**: Create migration for textract_jobs enhancements
  - Add content fields (extracted_content, manual_content)
  - Add editing metadata (manually_edited, content_edited_at, edited_by)
  - Add sync status fields (embedding_status, graph_sync_status, timestamps)
  - Add indexes for sync status queries
  - **Estimated Time**: 1 hour
  - **Files**: `database/migrations/YYYY_MM_DD_add_content_editing_to_textract_jobs.php`

- [ ] **Task 1.2**: Create migration for textract_documents table
  - Vector store table for chunked content with embeddings
  - Support both PostgreSQL pgvector and JSON storage
  - **Estimated Time**: 1 hour
  - **Files**: `database/migrations/YYYY_MM_DD_create_textract_documents_table.php`

- [ ] **Task 1.3**: Run migrations
  - Test migrations up/down
  - Verify table structure
  - **Estimated Time**: 0.5 hours

- [ ] **Task 1.4**: Update TextractJob model
  - Add new fillable fields
  - Add casts for dates and booleans
  - Add `activeContent` accessor
  - Add `contentStats` accessor
  - Add `needsEmbeddingSync()` and `needsGraphSync()` methods
  - Implement model observer in `booted()` method
  - **Estimated Time**: 2 hours
  - **Files**: `app/Models/TextractJob.php`

- [ ] **Task 1.5**: Create TextractDocument model
  - Define fillable fields, casts, relationships
  - **Estimated Time**: 0.5 hours
  - **Files**: `app/Models/TextractDocument.php`

**Phase 1 Total**: ~5 hours

---

### Phase 2: Service Layer ⭐ HIGH PRIORITY

- [ ] **Task 2.1**: Create TextractVectorStoreService
  - Implement `ingestTextractJob()` method
  - Implement `chunkText()` with sentence-aware splitting
  - Implement `generateEmbedding()` with retry logic
  - Implement `formatEmbedding()` for database compatibility
  - Implement `estimateTokens()` helper
  - **Estimated Time**: 4 hours
  - **Files**: `app/Services/TextractVectorStoreService.php`

- [ ] **Task 2.2**: Test TextractVectorStoreService
  - Unit tests for chunking logic
  - Integration test with OpenAI API
  - Test retry mechanism
  - **Estimated Time**: 2 hours
  - **Files**: `tests/Unit/Services/TextractVectorStoreServiceTest.php`

- [ ] **Task 2.3**: Enhance GraphRagService
  - Add `syncTextractJob()` method
  - Implement keyword extraction for textract content
  - Implement entity node creation
  - Add `findSimilarTextractJobs()` helper
  - **Estimated Time**: 3 hours
  - **Files**: `app/Services/GraphRagService.php`

- [ ] **Task 2.4**: Test GraphRagService enhancements
  - Test node creation
  - Test relationship creation
  - Test similarity finding
  - **Estimated Time**: 1.5 hours
  - **Files**: `tests/Unit/Services/GraphRagServiceTest.php`

**Phase 2 Total**: ~10.5 hours

---

### Phase 3: Queue Jobs ⭐ MEDIUM PRIORITY

- [ ] **Task 3.1**: Create RegenerateTextractEmbeddings job
  - Implement handle method with try-catch
  - Update embedding_status throughout process
  - Add logging
  - Configure retries and backoff
  - **Estimated Time**: 2 hours
  - **Files**: `app/Jobs/RegenerateTextractEmbeddings.php`

- [ ] **Task 3.2**: Create SyncTextractToGraph job
  - Implement handle method with try-catch
  - Update graph_sync_status throughout process
  - Add logging
  - Configure retries and backoff
  - **Estimated Time**: 2 hours
  - **Files**: `app/Jobs/SyncTextractToGraph.php`

- [ ] **Task 3.3**: Test queue jobs
  - Test successful execution
  - Test failure handling
  - Test retry mechanism
  - **Estimated Time**: 2 hours
  - **Files**: `tests/Feature/Jobs/TextractSyncJobsTest.php`

**Phase 3 Total**: ~6 hours

---

### Phase 4: Livewire Component ⭐ MEDIUM PRIORITY

- [ ] **Task 4.1**: Add new properties to TextractManager
  - Add modal state properties
  - Add content tab state
  - **Estimated Time**: 0.5 hours
  - **Files**: `app/Http/Livewire/TextractManager.php`

- [ ] **Task 4.2**: Implement viewContent() method
  - Load job with relationships
  - Format data for viewing
  - Show modal
  - **Estimated Time**: 1 hour

- [ ] **Task 4.3**: Implement editContent() method
  - Load job data
  - Convert metadata array to JSON string
  - Show edit modal
  - **Estimated Time**: 1 hour

- [ ] **Task 4.4**: Implement saveContent() method
  - Validate input (content, metadata JSON, case_id)
  - Convert metadata string back to array
  - Update TextractJob
  - Handle model observer triggering
  - Show success notification
  - **Estimated Time**: 2 hours

- [ ] **Task 4.5**: Implement resetToOriginal() method
  - Confirm with user
  - Reset manual_content and flags
  - Trigger re-sync
  - **Estimated Time**: 0.5 hours

- [ ] **Task 4.6**: Implement regenerateEmbeddings() method
  - Update status to pending
  - Dispatch job
  - Show notification
  - **Estimated Time**: 0.5 hours

- [ ] **Task 4.7**: Implement syncToGraph() method
  - Update status to pending
  - Dispatch job
  - Show notification
  - **Estimated Time**: 0.5 hours

- [ ] **Task 4.8**: Add validation rules
  - Create contentEditRules() method
  - Test validation
  - **Estimated Time**: 0.5 hours

**Phase 4 Total**: ~6.5 hours

---

### Phase 5: View Templates ⭐ MEDIUM PRIORITY

- [ ] **Task 5.1**: Create content view modal
  - Three-tab layout (Content, Metadata, Quality)
  - Document info section with stats
  - Active content display (monospace, scrollable)
  - Formatted metadata table
  - OCR quality metrics visualization
  - Edit button
  - **Estimated Time**: 4 hours
  - **Files**: `resources/views/livewire/textract-manager.blade.php`

- [ ] **Task 5.2**: Create content edit modal
  - Warning banner about sync triggers
  - Case selection dropdown
  - Content textarea (20 rows, monospace)
  - Word count display
  - Metadata JSON textarea
  - Reset to Original button
  - Save/Cancel buttons
  - **Estimated Time**: 3 hours

- [ ] **Task 5.3**: Add action buttons to job cards
  - View button (always enabled)
  - Edit button (disabled if no content)
  - "Edited" badge for manually edited jobs
  - **Estimated Time**: 1 hour

- [ ] **Task 5.4**: Add sync status indicators to job details
  - Embedding status with icon and timestamp
  - Graph sync status with icon and timestamp
  - Retry/Sync Now buttons for failed/pending states
  - **Estimated Time**: 2 hours

- [ ] **Task 5.5**: Style enhancements
  - Consistent spacing and colors
  - Responsive modal sizing
  - Loading states for buttons
  - Toast notifications styling
  - **Estimated Time**: 2 hours

**Phase 5 Total**: ~12 hours

---

### Phase 6: Configuration & Integration 🔧 LOW PRIORITY

- [ ] **Task 6.1**: Create/update embeddings config
  - Auto-sync toggle
  - Model and dimensions
  - Chunk size and overlap
  - Retry settings
  - **Estimated Time**: 0.5 hours
  - **Files**: `config/embeddings.php`

- [ ] **Task 6.2**: Update .env.example
  - Add EMBEDDINGS_AUTO_SYNC
  - Add EMBEDDING_MODEL
  - Add EMBEDDING_DIMENSIONS
  - Add chunk settings
  - **Estimated Time**: 0.5 hours
  - **Files**: `.env.example`

- [ ] **Task 6.3**: Update existing OCR pipeline
  - Modify SaveResultsStep to populate extracted_content
  - Set initial embedding_status
  - **Estimated Time**: 1 hour
  - **Files**: `app/Actions/Textract/Steps/SaveResultsStep.php`

**Phase 6 Total**: ~2 hours

---

### Phase 7: Testing & Polish 🧪 MEDIUM PRIORITY

- [ ] **Task 7.1**: Feature test - content preview
  - Test viewContent modal display
  - Test tab switching
  - Test data loading
  - **Estimated Time**: 1.5 hours
  - **Files**: `tests/Feature/Livewire/TextractManagerContentPreviewTest.php`

- [ ] **Task 7.2**: Feature test - content editing
  - Test editContent modal display
  - Test saveContent validation
  - Test successful save
  - Test model observer triggering
  - **Estimated Time**: 2 hours
  - **Files**: `tests/Feature/Livewire/TextractManagerContentEditTest.php`

- [ ] **Task 7.3**: Feature test - sync jobs
  - Test embedding regeneration
  - Test graph sync
  - Test manual triggers
  - **Estimated Time**: 1.5 hours
  - **Files**: `tests/Feature/Livewire/TextractManagerSyncTest.php`

- [ ] **Task 7.4**: Integration test - full flow
  - Create job → extract content → view → edit → verify syncs
  - **Estimated Time**: 2 hours
  - **Files**: `tests/Feature/TextractContentEditFlowTest.php`

- [ ] **Task 7.5**: Manual QA testing
  - Test all UI interactions
  - Test edge cases (empty content, invalid JSON)
  - Test error handling
  - Test loading states
  - **Estimated Time**: 2 hours

- [ ] **Task 7.6**: Documentation
  - Update README with new features
  - Add inline code comments
  - Create user guide for manual editing
  - **Estimated Time**: 2 hours
  - **Files**: `README.md`, `docs/TEXTRACT_MANUAL_EDITING.md`

**Phase 7 Total**: ~11 hours

---

## 📊 Total Effort Estimate

| Phase | Hours | Priority |
|-------|-------|----------|
| Phase 1: Database & Models | 5.0 | HIGH ⭐ |
| Phase 2: Service Layer | 10.5 | HIGH ⭐ |
| Phase 3: Queue Jobs | 6.0 | MEDIUM |
| Phase 4: Livewire Component | 6.5 | MEDIUM |
| Phase 5: View Templates | 12.0 | MEDIUM |
| Phase 6: Configuration | 2.0 | LOW |
| Phase 7: Testing & Polish | 11.0 | MEDIUM |
| **TOTAL** | **~53 hours** | |

**Revised Estimate**: 53 hours (adjusted from initial 41 hours after detailed task breakdown)

**Suggested Sprint Timeline**:
- **Sprint 1** (2 weeks): Phases 1-3 (Database, Services, Jobs)
- **Sprint 2** (2 weeks): Phases 4-5 (Component, Views)
- **Sprint 3** (1 week): Phases 6-7 (Config, Testing, Polish)

---

## ✅ Success Criteria

### Functional Requirements
- ✅ Users can preview OCR-extracted content in a read-only modal
- ✅ Users can view metadata and OCR quality metrics in separate tabs
- ✅ Users can edit document content in a dedicated modal
- ✅ Users can edit metadata (as JSON)
- ✅ Users can reassign documents to different cases
- ✅ Content changes automatically trigger embedding regeneration
- ✅ Content changes automatically trigger GraphDB synchronization
- ✅ Users can manually trigger embedding/graph sync if auto-sync fails
- ✅ Users can reset content to original OCR extraction
- ✅ Sync status is visible in the UI with timestamps
- ✅ Edit history is tracked (who edited, when)

### Technical Requirements
- ✅ Model observers correctly trigger sync jobs
- ✅ Queue jobs handle failures gracefully with retries
- ✅ Embeddings are stored with proper dimensionality (1536)
- ✅ Graph nodes and relationships are created correctly
- ✅ Database supports both PostgreSQL pgvector and JSON storage
- ✅ Chunking algorithm preserves sentence boundaries
- ✅ OpenAI API calls use retry logic with exponential backoff

### UX Requirements
- ✅ Modals are responsive and properly sized
- ✅ Forms show validation errors inline
- ✅ Success/error notifications appear after actions
- ✅ Loading states are shown for async operations
- ✅ Similar UX patterns to existing LawManager component
- ✅ Edit button is disabled when no content exists
- ✅ "Edited" badge is shown on manually edited jobs

### Performance Requirements
- ✅ Content loading is fast (< 1 second)
- ✅ Modal opening is instant
- ✅ Sync jobs run in background without blocking UI
- ✅ Chunking handles documents up to 100 pages efficiently
- ✅ Database queries use proper indexes

---

## 🚨 Risks & Mitigation

### Risk 1: OpenAI API Rate Limits
**Impact**: High
**Probability**: Medium
**Mitigation**:
- Implement exponential backoff with jitter
- Use queue jobs with 3 retry attempts
- Monitor API usage with logging
- Consider caching embeddings

### Risk 2: Large Document Performance
**Impact**: Medium
**Probability**: High
**Mitigation**:
- Use sentence-aware chunking with reasonable limits (1000 chars)
- Process chunks in batches
- Add database indexes on textract_job_id
- Use queue jobs for async processing

### Risk 3: Neo4j Graph Complexity
**Impact**: Medium
**Probability**: Medium
**Mitigation**:
- Limit keyword extraction (top 20)
- Limit similar document relationships (top 5)
- Use similarity threshold (0.85) to reduce noise
- Add graph query optimization

### Risk 4: Data Consistency
**Impact**: High
**Probability**: Low
**Mitigation**:
- Use model observers for automatic sync
- Track sync status in database
- Provide manual re-sync buttons
- Add comprehensive logging

### Risk 5: UX Complexity
**Impact**: Medium
**Probability**: Low
**Mitigation**:
- Follow proven patterns from LawManager
- Add clear warning banners
- Show sync status prominently
- Provide "Reset to Original" safety net

---

## 🔧 Configuration Reference

### Environment Variables

```env
# Embeddings Configuration
EMBEDDINGS_AUTO_SYNC=true
EMBEDDING_MODEL=text-embedding-3-small
EMBEDDING_DIMENSIONS=1536
EMBEDDING_CHUNK_SIZE=1000
EMBEDDING_CHUNK_OVERLAP=200
EMBEDDING_RETRY_ATTEMPTS=3
EMBEDDING_RETRY_BASE_DELAY=1000
EMBEDDING_RETRY_JITTER=50

# Neo4j Graph Sync
NEO4J_AUTO_SYNC=true
NEO4J_SIMILARITY_THRESHOLD=0.85
NEO4J_ENABLED=true
NEO4J_SYNC_BATCH_SIZE=100
```

### Config Files

**config/embeddings.php**:
```php
return [
    'auto_sync' => env('EMBEDDINGS_AUTO_SYNC', true),
    'model' => env('EMBEDDING_MODEL', 'text-embedding-3-small'),
    'dimensions' => env('EMBEDDING_DIMENSIONS', 1536),
    'chunk_size' => env('EMBEDDING_CHUNK_SIZE', 1000),
    'chunk_overlap' => env('EMBEDDING_CHUNK_OVERLAP', 200),
    'retry' => [
        'attempts' => env('EMBEDDING_RETRY_ATTEMPTS', 3),
        'base_delay' => env('EMBEDDING_RETRY_BASE_DELAY', 1000),
        'jitter_percent' => env('EMBEDDING_RETRY_JITTER', 50),
    ],
];
```

**config/neo4j.php** (existing, no changes needed):
```php
return [
    'sync' => [
        'batch_size' => env('NEO4J_SYNC_BATCH_SIZE', 100),
        'auto_sync' => env('NEO4J_AUTO_SYNC', true),
        'enabled' => env('NEO4J_ENABLED', true),
    ],
    'similarity' => [
        'threshold' => env('NEO4J_SIMILARITY_THRESHOLD', 0.85),
    ],
];
```

---

## 📚 References

### Existing Codebase Patterns

1. **IngestedLawsManager** (`app/Http/Livewire/IngestedLawsManager.php`)
   - Modal-based editing pattern
   - JSON array ↔ string conversion
   - Three-modal system (view, edit parent, edit child)
   - Validation with inline errors

2. **LawVectorStoreService** (`app/Services/LawVectorStoreService.php`)
   - Embedding generation with retry logic
   - OpenAI API integration
   - Vector storage formatting

3. **GraphRagService** (`app/Services/GraphRagService.php`)
   - Node and relationship creation
   - Keyword extraction
   - Similarity-based linking

4. **Law Model** (`app/Models/Law.php`)
   - Model observer pattern for auto-sync
   - Observer triggers GraphRagService

### External Dependencies

- **OpenAI PHP Client**: `openai-php/laravel`
- **Neo4j PHP Client**: `laudis/neo4j-php-client`
- **Livewire**: `livewire/livewire` v2.x
- **Alpine.js**: For modal state management

---

## 📝 Next Steps

1. **Review & Approval**: Present this plan to stakeholders
2. **Sprint Planning**: Break down into 3 sprints (see timeline above)
3. **Environment Setup**: Ensure OpenAI and Neo4j credentials are configured
4. **Branch Creation**: Create feature branch `feature/textract-content-editing`
5. **Begin Phase 1**: Start with database migrations and model updates
6. **Continuous Testing**: Test each phase before moving to the next
7. **Code Review**: Request reviews after each phase completion
8. **Documentation**: Update docs as features are completed
9. **User Acceptance Testing**: QA testing with real documents
10. **Deployment**: Deploy to staging → production

---

## 📞 Support & Questions

For questions or clarifications about this implementation plan, please:
- Review the existing IngestedLawsManager implementation
- Consult the GraphRagService and LawVectorStoreService implementations
- Check the Neo4j and OpenAI service documentation
- Reach out to the development team

---

**Document Version**: 1.0
**Last Updated**: 2025-10-27
**Author**: Claude (AI Assistant)
**Status**: Ready for Review

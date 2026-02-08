# Final Implementation Plan - AI Legal War Machine
**Goal:** Complete remaining 30 hours of work to achieve 9.5/10 score
**Current Score:** 8.8/10
**Target Score:** 9.5/10
**Total Effort:** 30 hours across 8 tasks

---

## Task Overview

| # | Task | Priority | Time | Files Changed |
|---|------|----------|------|---------------|
| 1 | Background Job Execution | P0 | 6h | 4 new files, 2 modified |
| 2 | API Endpoints for Agent Status | P0 | 2h | 2 new files |
| 3 | Law Version Tracking | P1 | 10h | 1 migration, 2 new files, 2 modified |
| 4 | Legal Prompt Builder | P1 | 4h | 1 new file, 1 modified |
| 5 | Progress Streaming | P2 | 6h | 3 new files, 1 modified |
| 6 | Result Highlighting | P2 | 4h | 3 modified files |
| 7 | Agent Checkpointing | P2 | 4h | 1 migration, 1 modified |
| 8 | Testing & Documentation | P0 | 4h | Test files + docs |

---

# TASK 1: Background Job Execution (6 hours)

## Objective
Move long-running agent research to background queue to prevent HTTP timeouts.

## Files to Create

### 1.1 Create Job Class
**File:** `app/Jobs/RunAutonomousResearchJob.php`

```php
<?php

namespace App\Jobs;

use App\Agents\AutonomousResearchAgent;
use App\Events\ResearchCompleted;
use App\Events\ResearchFailed;
use App\Models\AgentRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunAutonomousResearchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600; // 10 minutes
    public $tries = 1; // Don't retry automatically

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $runId,
    ) {
        $this->onQueue('research');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting background research job', ['run_id' => $this->runId]);

        try {
            $run = AgentRun::findOrFail($this->runId);

            // Update status to processing
            $run->update(['status' => 'processing']);

            // Execute research
            $agent = new AutonomousResearchAgent();
            $completed = $agent->executeRun($run);

            // Fire completion event
            event(new ResearchCompleted($completed));

            Log::info('Research completed successfully', [
                'run_id' => $this->runId,
                'iterations' => $completed->current_iteration,
                'insights' => $completed->insights_collected,
            ]);

        } catch (\Exception $e) {
            Log::error('Research job failed', [
                'run_id' => $this->runId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Update run status
            if (isset($run)) {
                $run->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
            }

            // Fire failure event
            event(new ResearchFailed($this->runId, $e));

            throw $e;
        }
    }

    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Research job failed permanently', [
            'run_id' => $this->runId,
            'error' => $exception->getMessage(),
        ]);
    }
}
```

### 1.2 Create ResearchCompleted Event
**File:** `app/Events/ResearchCompleted.php`

```php
<?php

namespace App\Events;

use App\Models\AgentRun;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ResearchCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public AgentRun $run
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): Channel
    {
        return new Channel('agent-runs.' . $this->run->id);
    }

    /**
     * Data to broadcast
     */
    public function broadcastWith(): array
    {
        return [
            'run_id' => $this->run->id,
            'status' => $this->run->status,
            'iterations' => $this->run->current_iteration,
            'insights_collected' => $this->run->insights_collected,
            'final_output' => $this->run->final_output,
            'completed_at' => $this->run->completed_at,
        ];
    }
}
```

### 1.3 Create ResearchFailed Event
**File:** `app/Events/ResearchFailed.php`

```php
<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ResearchFailed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $runId,
        public \Throwable $exception
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): Channel
    {
        return new Channel('agent-runs.' . $this->runId);
    }

    /**
     * Data to broadcast
     */
    public function broadcastWith(): array
    {
        return [
            'run_id' => $this->runId,
            'status' => 'failed',
            'error' => $this->exception->getMessage(),
        ];
    }
}
```

## Files to Modify

### 1.4 Modify AgentRun Model
**File:** `app/Models/AgentRun.php`

**Add to existing model:**

```php
// Add to $fillable array
protected $fillable = [
    // ... existing fields ...
    'status',           // Add this
    'error_message',    // Add this
];

// Add to $casts array
protected $casts = [
    // ... existing casts ...
    'started_at' => 'datetime',
    'completed_at' => 'datetime',
];

// Add method
public function isProcessing(): bool
{
    return $this->status === 'processing';
}

public function isCompleted(): bool
{
    return $this->status === 'completed';
}

public function isFailed(): bool
{
    return $this->status === 'failed';
}
```

### 1.5 Create Migration for Status Fields
**File:** `database/migrations/2025_10_27_000001_add_status_to_agent_runs.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_runs', function (Blueprint $table) {
            $table->string('status')->default('pending')->after('name');
            $table->text('error_message')->nullable()->after('final_output');

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('agent_runs', function (Blueprint $table) {
            $table->dropColumn(['status', 'error_message']);
        });
    }
};
```

## Testing

```bash
# Run migration
php artisan migrate

# Test job dispatching
php artisan tinker

# In tinker:
$agent = new \App\Agents\AutonomousResearchAgent();
$run = $agent->startRun("Research Croatian labor law on overtime", [], [
    'max_iterations' => 3,
]);

// Dispatch to background
\App\Jobs\RunAutonomousResearchJob::dispatch($run->id);

// Check queue
php artisan queue:work --queue=research
```

---

# TASK 2: API Endpoints for Agent Status (2 hours)

## Objective
Create REST API endpoints to start research and poll status.

## Files to Create

### 2.1 Create Agent API Controller
**File:** `app/Http/Controllers/Api/AgentController.php`

```php
<?php

namespace App\Http\Controllers\Api;

use App\Agents\AutonomousResearchAgent;
use App\Http\Controllers\Controller;
use App\Jobs\RunAutonomousResearchJob;
use App\Models\AgentRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AgentController extends Controller
{
    /**
     * Start a new autonomous research run
     */
    public function start(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'objective' => 'required|string|min:10|max:500',
            'context' => 'nullable|array',
            'config' => 'nullable|array',
            'config.max_iterations' => 'nullable|integer|min:1|max:20',
            'config.time_limit_seconds' => 'nullable|integer|min:60|max:3600',
            'config.token_budget' => 'nullable|integer|min:1000',
            'config.cost_budget' => 'nullable|numeric|min:0.01',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Create agent run
            $agent = new AutonomousResearchAgent();
            $run = $agent->startRun(
                $request->objective,
                $request->context ?? [],
                $request->config ?? []
            );

            // Dispatch to background queue
            RunAutonomousResearchJob::dispatch($run->id);

            return response()->json([
                'success' => true,
                'data' => [
                    'run_id' => $run->id,
                    'status' => $run->status,
                    'objective' => $run->objective,
                    'max_iterations' => $run->max_iterations,
                    'created_at' => $run->created_at,
                ],
                'message' => 'Research started successfully',
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get status of a research run
     */
    public function status(string $id): JsonResponse
    {
        $run = AgentRun::find($id);

        if (!$run) {
            return response()->json([
                'success' => false,
                'error' => 'Research run not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'run_id' => $run->id,
                'status' => $run->status,
                'objective' => $run->objective,
                'current_iteration' => $run->current_iteration,
                'max_iterations' => $run->max_iterations,
                'insights_collected' => $run->insights_collected,
                'tokens_used' => $run->tokens_used,
                'cost_spent' => $run->cost_spent,
                'started_at' => $run->started_at,
                'completed_at' => $run->completed_at,
                'error_message' => $run->error_message,
                'progress_percentage' => $run->max_iterations > 0
                    ? round(($run->current_iteration / $run->max_iterations) * 100)
                    : 0,
            ],
        ]);
    }

    /**
     * Get full results of a completed research run
     */
    public function results(string $id): JsonResponse
    {
        $run = AgentRun::find($id);

        if (!$run) {
            return response()->json([
                'success' => false,
                'error' => 'Research run not found',
            ], 404);
        }

        if (!$run->isCompleted()) {
            return response()->json([
                'success' => false,
                'error' => 'Research run is not yet completed',
                'status' => $run->status,
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'run_id' => $run->id,
                'objective' => $run->objective,
                'final_output' => $run->final_output,
                'iterations_data' => $run->iterations_data,
                'insights_collected' => $run->insights_collected,
                'evaluation' => $run->evaluation,
                'statistics' => [
                    'iterations' => $run->current_iteration,
                    'tokens_used' => $run->tokens_used,
                    'cost_spent' => $run->cost_spent,
                    'duration_seconds' => $run->started_at && $run->completed_at
                        ? $run->completed_at->diffInSeconds($run->started_at)
                        : null,
                ],
                'completed_at' => $run->completed_at,
            ],
        ]);
    }

    /**
     * List recent research runs
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 10);
        $status = $request->input('status'); // pending, processing, completed, failed

        $query = AgentRun::query()
            ->orderBy('created_at', 'desc');

        if ($status) {
            $query->where('status', $status);
        }

        $runs = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $runs->items(),
            'meta' => [
                'current_page' => $runs->currentPage(),
                'per_page' => $runs->perPage(),
                'total' => $runs->total(),
                'last_page' => $runs->lastPage(),
            ],
        ]);
    }
}
```

## Files to Modify

### 2.2 Add API Routes
**File:** `routes/api.php`

**Add to existing routes:**

```php
use App\Http\Controllers\Api\AgentController;

// Agent Research Routes
Route::prefix('agent')->group(function () {
    Route::post('/research/start', [AgentController::class, 'start']);
    Route::get('/research/{id}/status', [AgentController::class, 'status']);
    Route::get('/research/{id}/results', [AgentController::class, 'results']);
    Route::get('/research', [AgentController::class, 'index']);
});
```

## Testing

```bash
# Start a research run
curl -X POST http://localhost:8000/api/agent/research/start \
  -H "Content-Type: application/json" \
  -d '{
    "objective": "Research Croatian employment termination rules",
    "config": {
      "max_iterations": 5,
      "time_limit_seconds": 300
    }
  }'

# Response:
# {
#   "success": true,
#   "data": {
#     "run_id": "123e4567-e89b-12d3-a456-426614174000",
#     "status": "pending",
#     "objective": "Research Croatian employment termination rules",
#     "max_iterations": 5
#   }
# }

# Check status
curl http://localhost:8000/api/agent/research/{run_id}/status

# Get results (when completed)
curl http://localhost:8000/api/agent/research/{run_id}/results
```

---

# TASK 3: Law Version Tracking (10 hours)

## Objective
Track law amendments and maintain historical versions for accurate legal research.

## Files to Create

### 3.1 Create Migration for Versioning
**File:** `database/migrations/2025_10_27_000002_add_law_versioning.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ingested_laws', function (Blueprint $table) {
            $table->integer('version')->default(1)->after('doc_id');
            $table->uuid('previous_version_id')->nullable()->after('version');
            $table->date('version_date')->nullable()->after('effective_date');
            $table->boolean('is_current_version')->default(true)->after('version_date');
            $table->text('version_notes')->nullable()->after('is_current_version');

            $table->index('version');
            $table->index('previous_version_id');
            $table->index('is_current_version');
            $table->index(['doc_id', 'is_current_version']);

            $table->foreign('previous_version_id')
                  ->references('id')
                  ->on('ingested_laws')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('ingested_laws', function (Blueprint $table) {
            $table->dropForeign(['previous_version_id']);
            $table->dropColumn([
                'version',
                'previous_version_id',
                'version_date',
                'is_current_version',
                'version_notes',
            ]);
        });
    }
};
```

### 3.2 Create Law Versioning Service
**File:** `app/Services/LawVersioningService.php`

```php
<?php

namespace App\Services;

use App\Models\IngestedLaw;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LawVersioningService
{
    /**
     * Create a new version of an existing law
     */
    public function createNewVersion(
        IngestedLaw $existingLaw,
        array $amendedData,
        string $versionNotes = null
    ): IngestedLaw {
        return DB::transaction(function () use ($existingLaw, $amendedData, $versionNotes) {
            // Mark existing version as outdated
            $existingLaw->update(['is_current_version' => false]);

            // Create new version
            $newVersion = IngestedLaw::create([
                'doc_id' => $existingLaw->doc_id,
                'title' => $amendedData['title'] ?? $existingLaw->title,
                'content' => $amendedData['content'] ?? $existingLaw->content,
                'law_number' => $amendedData['law_number'] ?? $existingLaw->law_number,
                'jurisdiction' => $existingLaw->jurisdiction,
                'country' => $existingLaw->country,
                'promulgation_date' => $amendedData['promulgation_date'] ?? $existingLaw->promulgation_date,
                'effective_date' => $amendedData['effective_date'] ?? $existingLaw->effective_date,
                'source_url' => $amendedData['source_url'] ?? $existingLaw->source_url,
                'tags' => $amendedData['tags'] ?? $existingLaw->tags,
                'version' => $existingLaw->version + 1,
                'previous_version_id' => $existingLaw->id,
                'version_date' => $amendedData['version_date'] ?? now(),
                'is_current_version' => true,
                'version_notes' => $versionNotes,
            ]);

            Log::info('Created new law version', [
                'doc_id' => $newVersion->doc_id,
                'old_version' => $existingLaw->version,
                'new_version' => $newVersion->version,
            ]);

            return $newVersion;
        });
    }

    /**
     * Detect which articles changed between versions
     */
    public function detectChangedArticles(
        IngestedLaw $oldVersion,
        IngestedLaw $newVersion
    ): array {
        // Get all chunks for both versions
        $oldChunks = $oldVersion->chunks()
            ->get()
            ->keyBy('chunk_index');

        $newChunks = $newVersion->chunks()
            ->get()
            ->keyBy('chunk_index');

        $changes = [
            'added' => [],
            'modified' => [],
            'deleted' => [],
            'unchanged' => [],
        ];

        // Check for new and modified articles
        foreach ($newChunks as $index => $newChunk) {
            if (!isset($oldChunks[$index])) {
                // New article
                $changes['added'][] = [
                    'chunk_index' => $index,
                    'title' => $newChunk->metadata['title'] ?? "Article {$index}",
                    'content' => $newChunk->content,
                ];
            } else {
                // Compare content
                $oldContent = $this->normalizeContent($oldChunks[$index]->content);
                $newContent = $this->normalizeContent($newChunk->content);

                if ($oldContent !== $newContent) {
                    // Modified article
                    $changes['modified'][] = [
                        'chunk_index' => $index,
                        'title' => $newChunk->metadata['title'] ?? "Article {$index}",
                        'old_content' => $oldChunks[$index]->content,
                        'new_content' => $newChunk->content,
                        'diff' => $this->generateDiff($oldContent, $newContent),
                    ];
                } else {
                    $changes['unchanged'][] = $index;
                }
            }
        }

        // Check for deleted articles
        foreach ($oldChunks as $index => $oldChunk) {
            if (!isset($newChunks[$index])) {
                $changes['deleted'][] = [
                    'chunk_index' => $index,
                    'title' => $oldChunk->metadata['title'] ?? "Article {$index}",
                    'content' => $oldChunk->content,
                ];
            }
        }

        return $changes;
    }

    /**
     * Get version history for a law
     */
    public function getVersionHistory(string $docId): array
    {
        $versions = IngestedLaw::where('doc_id', $docId)
            ->orderBy('version', 'desc')
            ->get();

        return $versions->map(function ($version) {
            return [
                'id' => $version->id,
                'version' => $version->version,
                'version_date' => $version->version_date,
                'effective_date' => $version->effective_date,
                'is_current' => $version->is_current_version,
                'version_notes' => $version->version_notes,
                'law_number' => $version->law_number,
            ];
        })->toArray();
    }

    /**
     * Check if law has been amended
     */
    public function hasAmendment(string $docId): bool
    {
        return IngestedLaw::where('doc_id', $docId)
            ->where('version', '>', 1)
            ->exists();
    }

    /**
     * Get current version of a law
     */
    public function getCurrentVersion(string $docId): ?IngestedLaw
    {
        return IngestedLaw::where('doc_id', $docId)
            ->where('is_current_version', true)
            ->first();
    }

    /**
     * Get specific version of a law
     */
    public function getVersion(string $docId, int $version): ?IngestedLaw
    {
        return IngestedLaw::where('doc_id', $docId)
            ->where('version', $version)
            ->first();
    }

    /**
     * Normalize content for comparison (remove whitespace variations)
     */
    protected function normalizeContent(string $content): string
    {
        // Remove extra whitespace, normalize line endings
        $content = preg_replace('/\s+/', ' ', $content);
        $content = trim($content);
        return $content;
    }

    /**
     * Generate simple diff between two texts
     */
    protected function generateDiff(string $old, string $new): array
    {
        $oldWords = explode(' ', $old);
        $newWords = explode(' ', $new);

        $added = array_diff($newWords, $oldWords);
        $removed = array_diff($oldWords, $newWords);

        return [
            'added_words' => array_values($added),
            'removed_words' => array_values($removed),
            'similarity_percentage' => similar_text($old, $new, $percent) ? round($percent, 2) : 0,
        ];
    }
}
```

### 3.3 Create Artisan Command for Version Detection
**File:** `app/Console/Commands/DetectLawAmendments.php`

```php
<?php

namespace App\Console\Commands;

use App\Models\IngestedLaw;
use App\Services\LawVersioningService;
use Illuminate\Console\Command;

class DetectLawAmendments extends Command
{
    protected $signature = 'laws:detect-amendments
                          {doc_id? : Specific law doc_id to check}
                          {--all : Check all laws}
                          {--report : Generate detailed report}';

    protected $description = 'Detect amendments in laws by comparing with latest scraped versions';

    public function handle(LawVersioningService $versionService): int
    {
        if ($docId = $this->argument('doc_id')) {
            return $this->checkSingleLaw($docId, $versionService);
        }

        if ($this->option('all')) {
            return $this->checkAllLaws($versionService);
        }

        $this->error('Please specify a doc_id or use --all flag');
        return self::FAILURE;
    }

    protected function checkSingleLaw(string $docId, LawVersioningService $versionService): int
    {
        $current = $versionService->getCurrentVersion($docId);

        if (!$current) {
            $this->error("Law {$docId} not found");
            return self::FAILURE;
        }

        $this->info("Checking law: {$current->title} ({$current->law_number})");
        $this->info("Current version: {$current->version}");

        // Here you would re-scrape the law and compare
        // For now, just show version history
        $history = $versionService->getVersionHistory($docId);

        $this->table(
            ['Version', 'Date', 'Effective', 'Current', 'Notes'],
            array_map(fn($v) => [
                $v['version'],
                $v['version_date'],
                $v['effective_date'],
                $v['is_current'] ? 'Yes' : 'No',
                $v['version_notes'] ?? '-',
            ], $history)
        );

        return self::SUCCESS;
    }

    protected function checkAllLaws(LawVersioningService $versionService): int
    {
        $laws = IngestedLaw::where('is_current_version', true)
            ->orderBy('doc_id')
            ->get();

        $this->info("Checking {$laws->count()} laws for amendments...");

        $hasAmendments = 0;
        foreach ($laws as $law) {
            if ($versionService->hasAmendment($law->doc_id)) {
                $hasAmendments++;
                $history = $versionService->getVersionHistory($law->doc_id);
                $this->line("✓ {$law->title} - {$law->doc_id} ({count($history)} versions)");
            }
        }

        $this->info("\nSummary:");
        $this->info("Total laws: {$laws->count()}");
        $this->info("Laws with amendments: {$hasAmendments}");
        $this->info("Laws without amendments: " . ($laws->count() - $hasAmendments));

        return self::SUCCESS;
    }
}
```

## Files to Modify

### 3.4 Modify IngestedLaw Model
**File:** `app/Models/IngestedLaw.php`

**Add to existing model:**

```php
// Add to $fillable array
protected $fillable = [
    // ... existing fields ...
    'version',
    'previous_version_id',
    'version_date',
    'is_current_version',
    'version_notes',
];

// Add to $casts array
protected $casts = [
    // ... existing casts ...
    'version_date' => 'date',
    'is_current_version' => 'boolean',
];

// Add relationships
public function previousVersion()
{
    return $this->belongsTo(IngestedLaw::class, 'previous_version_id');
}

public function nextVersion()
{
    return $this->hasOne(IngestedLaw::class, 'previous_version_id', 'id');
}

public function allVersions()
{
    return IngestedLaw::where('doc_id', $this->doc_id)
        ->orderBy('version')
        ->get();
}

// Add scopes
public function scopeCurrent($query)
{
    return $query->where('is_current_version', true);
}

public function scopeVersion($query, int $version)
{
    return $query->where('version', $version);
}
```

### 3.5 Modify LawIngestService
**File:** `app/Services/LawIngestService.php`

**Add method to check for amendments before ingesting:**

```php
use App\Services\LawVersioningService;

protected LawVersioningService $versionService;

public function __construct(
    // ... existing dependencies ...
    LawVersioningService $versionService
) {
    // ... existing assignments ...
    $this->versionService = $versionService;
}

/**
 * Ingest law with version tracking
 */
public function ingestWithVersioning(array $lawData): IngestedLaw
{
    // Check if law already exists
    $existing = IngestedLaw::where('doc_id', $lawData['doc_id'])
        ->where('is_current_version', true)
        ->first();

    if ($existing) {
        // Check if content has changed
        $contentChanged = $this->hasContentChanged($existing, $lawData);

        if ($contentChanged) {
            // Create new version
            Log::info('Detected law amendment, creating new version', [
                'doc_id' => $lawData['doc_id'],
                'old_version' => $existing->version,
            ]);

            $newVersion = $this->versionService->createNewVersion(
                $existing,
                $lawData,
                'Automatic detection of amendment via scraping'
            );

            // Detect changed articles
            $changes = $this->versionService->detectChangedArticles(
                $existing,
                $newVersion
            );

            Log::info('Law changes detected', [
                'doc_id' => $lawData['doc_id'],
                'added' => count($changes['added']),
                'modified' => count($changes['modified']),
                'deleted' => count($changes['deleted']),
            ]);

            return $newVersion;
        }

        // No changes, return existing
        Log::info('Law content unchanged, skipping version creation', [
            'doc_id' => $lawData['doc_id'],
        ]);

        return $existing;
    }

    // New law, create version 1
    return $this->ingest($lawData);
}

/**
 * Check if law content has changed
 */
protected function hasContentChanged(IngestedLaw $existing, array $newData): bool
{
    $oldContent = $this->normalizeContent($existing->content);
    $newContent = $this->normalizeContent($newData['content'] ?? '');

    if ($oldContent !== $newContent) {
        return true;
    }

    // Also check if law number changed (indicates amendment)
    if (isset($newData['law_number']) && $existing->law_number !== $newData['law_number']) {
        return true;
    }

    return false;
}

/**
 * Normalize content for comparison
 */
protected function normalizeContent(string $content): string
{
    return trim(preg_replace('/\s+/', ' ', $content));
}
```

## Testing

```bash
# Run migration
php artisan migrate

# Test versioning
php artisan tinker

# In tinker:
$service = app(\App\Services\LawVersioningService::class);
$existing = \App\Models\IngestedLaw::where('doc_id', 'nn_93_2014')->first();

// Create new version
$newVersion = $service->createNewVersion($existing, [
    'content' => 'Updated content with amendment...',
    'law_number' => 'NN 93/14, 151/22',
    'version_date' => '2022-12-31',
], 'Amendment 151/22 added new articles');

// Detect changes
$changes = $service->detectChangedArticles($existing, $newVersion);
dd($changes);

// Check version history
php artisan laws:detect-amendments nn_93_2014
php artisan laws:detect-amendments --all
```

---

# TASK 4: Legal Prompt Builder (4 hours)

## Objective
Create domain-specific prompt templates for Croatian legal analysis.

## Files to Create

### 4.1 Create Legal Prompt Builder Service
**File:** `app/Services/LegalPromptBuilder.php`

```php
<?php

namespace App\Services;

class LegalPromptBuilder
{
    /**
     * Build comprehensive legal analysis prompt
     */
    public function buildAnalysisPrompt(
        string $query,
        array $context,
        string $language = 'hr'
    ): string {
        $sources = $this->formatSources($context);

        if ($language === 'hr') {
            return $this->buildCroatianAnalysisPrompt($query, $sources);
        }

        return $this->buildEnglishAnalysisPrompt($query, $sources);
    }

    /**
     * Build Croatian legal analysis prompt
     */
    protected function buildCroatianAnalysisPrompt(string $query, string $sources): string
    {
        return <<<PROMPT
Vi ste stručnjak za hrvatsko pravo. Analizirajte sljedeće pravno pitanje koristeći priložene izvore.

PITANJE:
{$query}

IZVORI:
{$sources}

UPUTE ZA ANALIZU:
1. Citirajte točne članke zakona i brojeve sudskih odluka
2. Razlikujte obvezujuće zakone od uvjerljivih sudskih precedenata
3. Navedite sve nejasnoće ili sukobe između izvora
4. Pružite praktične smjernice za primjenu
5. Uključite relevantne upozorenja i ograničenja

STRUKTURA ODGOVORA:
1. PRAVNA OSNOVA
   - Relevantni zakoni i članci
   - Direktno primjenjive odredbe

2. SUDSKA PRAKSA
   - Presude koje tumače relevantne zakone
   - Pravna stajališta viših sudova

3. PRAKTIČNA PRIMJENA
   - Kako se pravna norma primjenjuje u praksi
   - Konkretni koraci ili postupci

4. NAPOMENE I UPOZORENJA
   - Iznimke od pravila
   - Nejasnoće ili sporna pitanja
   - Situacije koje zahtijevaju stručnu pravnu pomoć

STIL:
- Koristite hrvatsku pravnu terminologiju
- Budite precizni i konkretni
- Izbjegavajte dvosmislenosti
- Podržite svaku tvrdnju citatom izvora

Odgovorite na hrvatskom jeziku s detaljnom analizom.
PROMPT;
    }

    /**
     * Build English legal analysis prompt
     */
    protected function buildEnglishAnalysisPrompt(string $query, string $sources): string
    {
        return <<<PROMPT
You are an expert in Croatian law. Analyze the following legal query using the provided sources.

QUERY:
{$query}

SOURCES:
{$sources}

ANALYSIS GUIDELINES:
1. Cite specific law articles and court decision numbers
2. Distinguish between binding law and persuasive precedent
3. Note any ambiguities or conflicts between sources
4. Provide practical application guidance
5. Include relevant caveats and limitations

RESPONSE STRUCTURE:
1. LEGAL BASIS
   - Relevant laws and articles
   - Directly applicable provisions

2. COURT PRACTICE
   - Decisions interpreting relevant laws
   - Legal positions from higher courts

3. PRACTICAL APPLICATION
   - How the legal norm applies in practice
   - Concrete steps or procedures

4. NOTES AND WARNINGS
   - Exceptions to the rule
   - Ambiguities or disputed issues
   - Situations requiring professional legal help

STYLE:
- Use proper Croatian legal terminology (with English translations)
- Be precise and specific
- Avoid ambiguities
- Support every claim with source citation

Respond with detailed analysis.
PROMPT;
    }

    /**
     * Build prompt for contract analysis
     */
    public function buildContractAnalysisPrompt(string $contractText, string $question): string
    {
        return <<<PROMPT
Analizirajte sljedeći ugovor prema hrvatskom Zakonu o obveznim odnosima.

UGOVOR:
{$contractText}

PITANJE:
{$question}

PROVJERITE:
1. Sukladno esencijalia negotii (bitni elementi ugovora - članak 247 ZOO)
2. Eventualne ništavosti (članci 322-326 ZOO)
3. Prava i obveze ugovornih strana
4. Rokovi i uvjeti
5. Posljedice neispunjenja

Navedite sve problematične klauzule i potencijalne pravne rizike.
PROMPT;
    }

    /**
     * Build prompt for decision comparison
     */
    public function buildDecisionComparisonPrompt(array $decisions): string
    {
        $formattedDecisions = '';
        foreach ($decisions as $i => $decision) {
            $num = $i + 1;
            $formattedDecisions .= "\n\nODLUKA {$num}:\n";
            $formattedDecisions .= "Sud: {$decision['court']}\n";
            $formattedDecisions .= "Broj: {$decision['case_number']}\n";
            $formattedDecisions .= "Datum: {$decision['date']}\n";
            $formattedDecisions .= "Sadržaj: {$decision['content']}\n";
        }

        return <<<PROMPT
Usporedite sljedeće sudske odluke i identificirajte:

{$formattedDecisions}

ANALIZA:
1. Zajedničke pravne stavove
2. Ključne razlike u tumačenju
3. Razvoj sudske prakse kroz vrijeme
4. Dominantno pravno mišljenje
5. Eventualne nedosljednosti

Zaključite koji je pravni stav trenutno dominantan u sudskoj praksi.
PROMPT;
    }

    /**
     * Build prompt for precedent search
     */
    public function buildPrecedentSearchPrompt(string $caseDescription): string
    {
        return <<<PROMPT
Na temelju sljedećeg opisa slučaja, identificirajte najrelevantnije precedente:

OPIS SLUČAJA:
{$caseDescription}

KRITERIJI ZA RELEVANTNOST:
1. Sličnost činjeničnog stanja
2. Isti pravni temelj (zakon, članci)
3. Analogan pravni problem
4. Rang suda (viši sudovi imaju veću težinu)
5. Recentnost odluke

Za svaki pronađeni precedent navedite:
- Zašto je relevantan
- Koje pravno načelo utvrđuje
- Kako se primjenjuje na aktualni slučaj
- Razlike u činjeničnom stanju (ako postoje)

Prioritizirajte odluke Vrhovnog suda Hrvatske.
PROMPT;
    }

    /**
     * Format sources for inclusion in prompts
     */
    protected function formatSources(array $context): string
    {
        $formatted = '';

        // Format laws
        if (!empty($context['laws'])) {
            $formatted .= "═══ ZAKONI ═══\n\n";
            foreach ($context['laws'] as $i => $law) {
                $num = $i + 1;
                $formatted .= "{$num}. {$law['title']} ({$law['law_number']})\n";
                $formatted .= "   Članak {$law['chunk_index']}: {$law['content']}\n";
                $formatted .= "   URL: {$law['source_url']}\n\n";
            }
        }

        // Format court decisions
        if (!empty($context['decisions'])) {
            $formatted .= "\n═══ SUDSKE ODLUKE ═══\n\n";
            foreach ($context['decisions'] as $i => $decision) {
                $num = $i + 1;
                $formatted .= "{$num}. {$decision['court']} - {$decision['case_number']}\n";
                $formatted .= "   Datum: {$decision['decision_date']}\n";
                if (!empty($decision['ecli'])) {
                    $formatted .= "   ECLI: {$decision['ecli']}\n";
                }
                $formatted .= "   Sadržaj: {$decision['content']}\n\n";
            }
        }

        // Format case documents
        if (!empty($context['cases'])) {
            $formatted .= "\n═══ DOKUMENTI PREDMETA ═══\n\n";
            foreach ($context['cases'] as $i => $case) {
                $num = $i + 1;
                $formatted .= "{$num}. {$case['title']}\n";
                $formatted .= "   Tip: {$case['document_type']}\n";
                $formatted .= "   Sadržaj: {$case['content']}\n\n";
            }
        }

        return $formatted ?: "Nema dostupnih izvora.";
    }

    /**
     * Build system prompt for legal assistant
     */
    public function buildSystemPrompt(string $specialty = 'general'): string
    {
        $base = <<<PROMPT
Vi ste stručni pravni savjetnik specijaliziran za hrvatsko pravo.

VAŠE SPOSOBNOSTI:
- Detaljno poznavanje hrvatskog pravnog sustava
- Precizno citiranje zakona i sudskih odluka
- Razlikovanje obvezujućeg prava od sudske prakse
- Praktična primjena pravnih normi
- Identifikacija pravnih rizika i nejasnoća

VAŠA OGRANIČENJA:
- Ne možete pružiti konačan pravni savjet (to smije samo odvjetnik)
- Ne možete garantirati ishod pravnog postupka
- Uvijek napomenite kada je potrebna konzultacija s odvjetnikom

STIL ODGOVORA:
- Precizno i stručno
- Podržano citatima izvora
- Jasno strukturirano
- Na hrvatskom jeziku
- S praktičnim smjernicama
PROMPT;

        $specialties = [
            'labor' => "\nSPECIJALIZACIJA: Radno pravo (Zakon o radu, kolektivni ugovori, radni sporovi)",
            'contract' => "\nSPECIJALIZACIJA: Ugovorno pravo (Zakon o obveznim odnosima, ugovori, naknada štete)",
            'property' => "\nSPECIJALIZACIJA: Stvarno pravo (Zakon o vlasništvu, zemljišne knjige, posjed)",
            'family' => "\nSPECIJALIZACIJA: Obiteljsko pravo (Obiteljski zakon, brak, razvod, skrbništvo)",
            'criminal' => "\nSPECIJALIZACIJA: Kazneno pravo (Kazneni zakon, kazneni postupak)",
        ];

        return $base . ($specialties[$specialty] ?? '');
    }
}
```

## Files to Modify

### 4.2 Integrate into RagOrchestrator
**File:** `app/Services/RagOrchestrator.php`

**Add to existing class:**

```php
use App\Services\LegalPromptBuilder;

protected LegalPromptBuilder $promptBuilder;

public function __construct(
    // ... existing dependencies ...
    LegalPromptBuilder $promptBuilder
) {
    // ... existing assignments ...
    $this->promptBuilder = $promptBuilder;
}

/**
 * Ask legal question with optimized prompt
 */
public function askLegalQuestion(
    string $query,
    array $options = []
): array {
    // 1. Search for relevant sources
    $context = $this->retrieveContext($query, $options);

    // 2. Build legal analysis prompt
    $prompt = $this->promptBuilder->buildAnalysisPrompt(
        $query,
        $context,
        $options['language'] ?? 'hr'
    );

    // 3. Get LLM response
    $response = $this->openai->chat([
        [
            'role' => 'system',
            'content' => $this->promptBuilder->buildSystemPrompt($options['specialty'] ?? 'general')
        ],
        [
            'role' => 'user',
            'content' => $prompt
        ],
    ], $options['model'] ?? 'gpt-4o-mini', [
        'temperature' => 0.3, // Lower for factual legal analysis
        'max_tokens' => $options['max_tokens'] ?? 2000,
    ]);

    return [
        'answer' => $response['choices'][0]['message']['content'],
        'sources' => $context,
        'query' => $query,
        'tokens_used' => $response['usage']['total_tokens'],
    ];
}
```

## Testing

```bash
# Test legal prompt builder
php artisan tinker

# In tinker:
$builder = app(\App\Services\LegalPromptBuilder::class);

// Test analysis prompt
$context = [
    'laws' => [
        [
            'title' => 'Zakon o radu',
            'law_number' => 'NN 93/14',
            'chunk_index' => 93,
            'content' => 'Poslodavac može otkazati ugovor o radu...',
            'source_url' => 'https://zakon.hr/...'
        ]
    ],
    'decisions' => [
        [
            'court' => 'Vrhovni sud',
            'case_number' => 'Gž-123/2023',
            'decision_date' => '2023-05-15',
            'content' => 'Sud je odlučio...'
        ]
    ]
];

$prompt = $builder->buildAnalysisPrompt(
    'Može li me poslodavac otpustiti bez otkaznog roka?',
    $context,
    'hr'
);

echo $prompt;

// Test with RagOrchestrator
$rag = app(\App\Services\RagOrchestrator::class);
$result = $rag->askLegalQuestion(
    'Koja su prava radnika kod nezakonitog otkaza?',
    ['specialty' => 'labor']
);

dd($result);
```

---

# TASK 5-7: Lower Priority Tasks (14 hours total)

## Summary

**TASK 5: Progress Streaming (6 hours)**
- Create Livewire component for real-time updates
- Event listeners for iteration completion
- WebSocket broadcasting

**TASK 6: Result Highlighting (4 hours)**
- Add highlightMatches() to search services
- Update Blade templates with <mark> tags

**TASK 7: Agent Checkpointing (4 hours)**
- Add checkpoint_data JSON column
- Save/restore agent state after each iteration

---

# TASK 8: Testing & Documentation (4 hours)

## Create Tests

### Test File 1: Background Jobs
**File:** `tests/Feature/BackgroundResearchTest.php`

```php
<?php

namespace Tests\Feature;

use App\Jobs\RunAutonomousResearchJob;
use App\Models\AgentRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BackgroundResearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_start_research_via_api()
    {
        Queue::fake();

        $response = $this->postJson('/api/agent/research/start', [
            'objective' => 'Research Croatian labor law on overtime',
            'config' => ['max_iterations' => 3],
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => ['run_id', 'status', 'objective'],
            ]);

        Queue::assertPushed(RunAutonomousResearchJob::class);
    }

    public function test_can_poll_research_status()
    {
        $run = AgentRun::factory()->create([
            'status' => 'processing',
            'current_iteration' => 2,
            'max_iterations' => 5,
        ]);

        $response = $this->getJson("/api/agent/research/{$run->id}/status");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'processing',
                    'progress_percentage' => 40,
                ],
            ]);
    }
}
```

### Test File 2: Law Versioning
**File:** `tests/Feature/LawVersioningTest.php`

```php
<?php

namespace Tests\Feature;

use App\Models\IngestedLaw;
use App\Services\LawVersioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LawVersioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_new_version()
    {
        $service = app(LawVersioningService::class);

        $v1 = IngestedLaw::factory()->create([
            'doc_id' => 'nn_93_2014',
            'content' => 'Original content',
            'version' => 1,
        ]);

        $v2 = $service->createNewVersion($v1, [
            'content' => 'Amended content',
            'version_date' => '2022-12-31',
        ], 'Amendment 151/22');

        $this->assertEquals(2, $v2->version);
        $this->assertEquals($v1->id, $v2->previous_version_id);
        $this->assertTrue($v2->is_current_version);
        $this->assertFalse($v1->fresh()->is_current_version);
    }

    public function test_can_detect_changed_articles()
    {
        $service = app(LawVersioningService::class);

        $v1 = IngestedLaw::factory()->create();
        $v2 = IngestedLaw::factory()->create();

        // Add chunks to v1
        $v1->chunks()->create(['chunk_index' => 1, 'content' => 'Article 1 original']);
        $v1->chunks()->create(['chunk_index' => 2, 'content' => 'Article 2 original']);

        // Add chunks to v2 (modified article 2, new article 3)
        $v2->chunks()->create(['chunk_index' => 1, 'content' => 'Article 1 original']);
        $v2->chunks()->create(['chunk_index' => 2, 'content' => 'Article 2 AMENDED']);
        $v2->chunks()->create(['chunk_index' => 3, 'content' => 'Article 3 NEW']);

        $changes = $service->detectChangedArticles($v1, $v2);

        $this->assertCount(1, $changes['modified']);
        $this->assertCount(1, $changes['added']);
        $this->assertCount(0, $changes['deleted']);
    }
}
```

## Update Documentation

### Doc File: API Reference
**File:** `docs/API_REFERENCE.md`

```markdown
# API Reference

## Agent Research API

### Start Research

**Endpoint:** `POST /api/agent/research/start`

**Request:**
```json
{
  "objective": "Research Croatian labor law on overtime",
  "context": {
    "case_id": "optional-case-uuid"
  },
  "config": {
    "max_iterations": 5,
    "time_limit_seconds": 300,
    "token_budget": 50000,
    "cost_budget": 1.00
  }
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "run_id": "uuid",
    "status": "pending",
    "objective": "Research Croatian labor law on overtime",
    "max_iterations": 5
  }
}
```

### Poll Status

**Endpoint:** `GET /api/agent/research/{id}/status`

**Response:**
```json
{
  "success": true,
  "data": {
    "run_id": "uuid",
    "status": "processing",
    "current_iteration": 3,
    "max_iterations": 5,
    "progress_percentage": 60,
    "insights_collected": 5,
    "tokens_used": 12500,
    "cost_spent": 0.25
  }
}
```

... (complete documentation)
```

---

# Execution Order

## Week 1 (HIGH PRIORITY - 20 hours)

### Day 1-2 (8 hours): Background Job System
1. ✅ Task 1.1-1.3: Create Job + Events (3h)
2. ✅ Task 1.4-1.5: Modify Model + Migration (2h)
3. ✅ Task 2: Create API Endpoints (2h)
4. ✅ Test & Verify (1h)

### Day 3-5 (12 hours): Law Versioning
1. ✅ Task 3.1: Create Migration (1h)
2. ✅ Task 3.2: Create Versioning Service (4h)
3. ✅ Task 3.3: Create Artisan Command (2h)
4. ✅ Task 3.4-3.5: Modify Model + IngestService (3h)
5. ✅ Test & Verify (2h)

## Week 2 (MEDIUM PRIORITY - 10 hours)

### Day 1-2 (4 hours): Legal Prompts
1. ✅ Task 4.1: Create LegalPromptBuilder (3h)
2. ✅ Task 4.2: Integrate into RagOrchestrator (1h)

### Day 3-4 (6 hours): Optional Enhancements
1. ⚠️ Task 5: Progress Streaming (if needed)
2. ⚠️ Task 6: Result Highlighting (if needed)

### Day 5 (4 hours): Testing & Docs
1. ✅ Task 8: Write tests (2h)
2. ✅ Task 8: Update documentation (2h)

---

# Success Criteria

## After Completing High Priority (Tasks 1-4)

**Score Improvement:** 8.8/10 → 9.3/10

**Achievements:**
- ✅ Agent runs don't timeout (background jobs)
- ✅ API endpoints for external integration
- ✅ Law amendments tracked automatically
- ✅ Professional legal analysis prompts

## After Completing All Tasks

**Score Improvement:** 8.8/10 → 9.5/10

**Achievements:**
- ✅ Production-ready system
- ✅ Real-time progress monitoring
- ✅ Better search UX with highlighting
- ✅ Resilient agent execution (checkpoints)
- ✅ Comprehensive test coverage
- ✅ Complete documentation

---

# Monitoring & Validation

## After Each Task

```bash
# Run tests
php artisan test

# Check code quality
./vendor/bin/phpstan analyze

# Verify migrations
php artisan migrate:status

# Test API endpoints
curl -X POST http://localhost:8000/api/agent/research/start ...
```

## System Health Checks

```bash
# Queue status
php artisan queue:monitor research

# Agent runs overview
php artisan agent:runs --status=processing

# Law versions
php artisan laws:detect-amendments --all
```

---

**Estimated Total Time:** 30 hours
**Recommended Pace:** 2 weeks part-time or 1 week full-time
**Difficulty:** Medium (most code patterns already established)
**Dependencies:** Tasks 1-2 should be done first, others can be parallel

# Recursive Document Writing Agent - Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Implement a self-improving legal document generator with critic-worker iteration pattern for Croatian defense attorneys

**Architecture:** Vizra ADK agent with hybrid context assembly, multi-dimensional scoring, and full iteration history tracking

**Tech Stack:** Laravel 11, Vizra ADK, PostgreSQL, OpenAI GPT-4o/GPT-4o-mini, Pest (testing)

---

## Task 1: Document Generation Runs Migration

**Files:**
- Create: `database/migrations/2025_11_15_230000_create_document_generation_runs_table.php`

**Step 1: Write the failing test**

Create test file: `tests/Unit/Models/DocumentGenerationRunTest.php`

```php
<?php

use App\Models\DocumentGenerationRun;
use App\Models\LegalCase;
use App\Models\User;

test('document generation run can be created', function () {
    $user = User::factory()->create();
    $case = LegalCase::factory()->create();

    $run = DocumentGenerationRun::create([
        'document_type' => 'suppression_motion',
        'case_id' => $case->id,
        'status' => 'running',
        'user_id' => $user->id,
        'model_config' => ['critic_model' => 'gpt-4o', 'worker_model' => 'gpt-4o'],
    ]);

    expect($run)->toBeInstanceOf(DocumentGenerationRun::class);
    expect($run->document_type)->toBe('suppression_motion');
    expect($run->status)->toBe('running');
});

test('document generation run belongs to user', function () {
    $user = User::factory()->create();
    $run = DocumentGenerationRun::factory()->create(['user_id' => $user->id]);

    expect($run->user)->toBeInstanceOf(User::class);
    expect($run->user->id)->toBe($user->id);
});

test('document generation run can belong to a case', function () {
    $case = LegalCase::factory()->create();
    $run = DocumentGenerationRun::factory()->create(['case_id' => $case->id]);

    expect($run->case)->toBeInstanceOf(LegalCase::class);
    expect($run->case->id)->toBe($case->id);
});
```

**Step 2: Run test to verify it fails**

Run: `php artisan test --filter=DocumentGenerationRunTest`

Expected: FAIL with "Class 'App\Models\DocumentGenerationRun' not found"

**Step 3: Create migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_generation_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('document_type');
            $table->uuid('case_id')->nullable();
            $table->string('status', 50); // running, completed, failed
            $table->text('final_document')->nullable();
            $table->decimal('final_score', 5, 2)->nullable();
            $table->integer('total_iterations')->nullable();
            $table->string('stopped_reason', 50)->nullable(); // converged, max_iterations, error
            $table->json('model_config')->nullable();
            $table->uuid('user_id');
            $table->timestamps();

            $table->foreign('case_id')->references('id')->on('cases')->onDelete('set null');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->index('document_type');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_generation_runs');
    }
};
```

**Step 4: Run migration**

Run: `php artisan migrate`

Expected: Migration runs successfully

**Step 5: Create model**

Create: `app/Models/DocumentGenerationRun.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DocumentGenerationRun extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'document_type',
        'case_id',
        'status',
        'final_document',
        'final_score',
        'total_iterations',
        'stopped_reason',
        'model_config',
        'user_id',
    ];

    protected $casts = [
        'model_config' => 'array',
        'final_score' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class, 'case_id');
    }

    public function iterations(): HasMany
    {
        return $this->hasMany(DocumentIteration::class, 'generation_run_id');
    }

    public function context(): HasOne
    {
        return $this->hasOne(DocumentContext::class, 'generation_run_id');
    }
}
```

**Step 6: Create factory**

Create: `database/factories/DocumentGenerationRunFactory.php`

```php
<?php

namespace Database\Factories;

use App\Models\DocumentGenerationRun;
use App\Models\LegalCase;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentGenerationRunFactory extends Factory
{
    protected $model = DocumentGenerationRun::class;

    public function definition(): array
    {
        return [
            'document_type' => 'suppression_motion',
            'case_id' => null,
            'status' => 'running',
            'final_document' => null,
            'final_score' => null,
            'total_iterations' => null,
            'stopped_reason' => null,
            'model_config' => ['critic_model' => 'gpt-4o', 'worker_model' => 'gpt-4o'],
            'user_id' => User::factory(),
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'final_document' => 'Test document content',
            'final_score' => 85.50,
            'total_iterations' => 5,
            'stopped_reason' => 'converged',
        ]);
    }
}
```

**Step 7: Run test to verify it passes**

Run: `php artisan test --filter=DocumentGenerationRunTest`

Expected: PASS (all tests green)

**Step 8: Commit**

```bash
git add database/migrations/2025_11_15_230000_create_document_generation_runs_table.php \
  app/Models/DocumentGenerationRun.php \
  database/factories/DocumentGenerationRunFactory.php \
  tests/Unit/Models/DocumentGenerationRunTest.php
git commit -m "feat: add DocumentGenerationRun model and migration

- Create document_generation_runs table with UUID primary key
- Add relationships to users and cases
- Support configurable AI models via JSON config
- Track status, scores, iterations, and stopping reasons
- Add factory for testing"
```

---

## Task 2: Document Iterations Migration

**Files:**
- Create: `database/migrations/2025_11_15_230001_create_document_iterations_table.php`

**Step 1: Write the failing test**

Add to `tests/Unit/Models/DocumentIterationTest.php`:

```php
<?php

use App\Models\DocumentGenerationRun;
use App\Models\DocumentIteration;

test('document iteration can be created', function () {
    $run = DocumentGenerationRun::factory()->create();

    $iteration = DocumentIteration::create([
        'generation_run_id' => $run->id,
        'iteration_number' => 1,
        'phase' => 'critic',
        'document_version' => null,
        'critic_feedback' => ['plan' => 'Create initial structure'],
        'scores' => null,
        'weighted_score' => null,
        'improvement_delta' => null,
        'ai_model_used' => 'gpt-4o',
        'tokens_used' => 1500,
        'cost_estimate' => 0.03,
    ]);

    expect($iteration)->toBeInstanceOf(DocumentIteration::class);
    expect($iteration->phase)->toBe('critic');
    expect($iteration->iteration_number)->toBe(1);
});

test('document iteration belongs to generation run', function () {
    $run = DocumentGenerationRun::factory()->create();
    $iteration = DocumentIteration::factory()->create(['generation_run_id' => $run->id]);

    expect($iteration->generationRun)->toBeInstanceOf(DocumentGenerationRun::class);
    expect($iteration->generationRun->id)->toBe($run->id);
});

test('document iteration stores critic feedback as JSON', function () {
    $feedback = [
        'scores' => ['legal_rigor' => 85, 'persuasiveness' => 78],
        'weaknesses' => ['Missing citation'],
        'improvement_plan' => 'Add ZKP citations',
    ];

    $iteration = DocumentIteration::factory()->create(['critic_feedback' => $feedback]);

    expect($iteration->critic_feedback)->toBe($feedback);
});
```

**Step 2: Run test to verify it fails**

Run: `php artisan test --filter=DocumentIterationTest`

Expected: FAIL with "Class 'App\Models\DocumentIteration' not found"

**Step 3: Create migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_iterations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('generation_run_id');
            $table->integer('iteration_number');
            $table->string('phase', 50); // critic, worker
            $table->text('document_version')->nullable();
            $table->json('critic_feedback')->nullable();
            $table->json('scores')->nullable();
            $table->decimal('weighted_score', 5, 2)->nullable();
            $table->decimal('improvement_delta', 5, 2)->nullable();
            $table->string('ai_model_used', 100)->nullable();
            $table->integer('tokens_used')->nullable();
            $table->decimal('cost_estimate', 10, 4)->nullable();
            $table->timestamp('created_at');

            $table->foreign('generation_run_id')
                ->references('id')
                ->on('document_generation_runs')
                ->onDelete('cascade');

            $table->index(['generation_run_id', 'iteration_number']);
            $table->index('phase');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_iterations');
    }
};
```

**Step 4: Run migration**

Run: `php artisan migrate`

Expected: Migration runs successfully

**Step 5: Create model**

Create: `app/Models/DocumentIteration.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentIteration extends Model
{
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'generation_run_id',
        'iteration_number',
        'phase',
        'document_version',
        'critic_feedback',
        'scores',
        'weighted_score',
        'improvement_delta',
        'ai_model_used',
        'tokens_used',
        'cost_estimate',
        'created_at',
    ];

    protected $casts = [
        'critic_feedback' => 'array',
        'scores' => 'array',
        'weighted_score' => 'decimal:2',
        'improvement_delta' => 'decimal:2',
        'cost_estimate' => 'decimal:4',
        'created_at' => 'datetime',
    ];

    public function generationRun(): BelongsTo
    {
        return $this->belongsTo(DocumentGenerationRun::class, 'generation_run_id');
    }
}
```

**Step 6: Create factory**

Create: `database/factories/DocumentIterationFactory.php`

```php
<?php

namespace Database\Factories;

use App\Models\DocumentGenerationRun;
use App\Models\DocumentIteration;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentIterationFactory extends Factory
{
    protected $model = DocumentIteration::class;

    public function definition(): array
    {
        return [
            'generation_run_id' => DocumentGenerationRun::factory(),
            'iteration_number' => 1,
            'phase' => 'critic',
            'document_version' => null,
            'critic_feedback' => null,
            'scores' => null,
            'weighted_score' => null,
            'improvement_delta' => null,
            'ai_model_used' => 'gpt-4o',
            'tokens_used' => 1500,
            'cost_estimate' => 0.03,
            'created_at' => now(),
        ];
    }

    public function criticPhase(): static
    {
        return $this->state(fn (array $attributes) => [
            'phase' => 'critic',
            'critic_feedback' => [
                'scores' => ['legal_rigor' => 85],
                'weaknesses' => ['test'],
                'improvement_plan' => 'test plan',
            ],
        ]);
    }

    public function workerPhase(): static
    {
        return $this->state(fn (array $attributes) => [
            'phase' => 'worker',
            'document_version' => 'Test document content',
            'scores' => ['legal_rigor' => 85, 'persuasiveness' => 78, 'clarity' => 90],
            'weighted_score' => 82.50,
        ]);
    }
}
```

**Step 7: Run test to verify it passes**

Run: `php artisan test --filter=DocumentIterationTest`

Expected: PASS (all tests green)

**Step 8: Commit**

```bash
git add database/migrations/2025_11_15_230001_create_document_iterations_table.php \
  app/Models/DocumentIteration.php \
  database/factories/DocumentIterationFactory.php \
  tests/Unit/Models/DocumentIterationTest.php
git commit -m "feat: add DocumentIteration model and migration

- Create document_iterations table with cascade delete
- Store critic feedback and scores as JSON
- Track tokens and cost per iteration
- Support critic and worker phases
- Add factory with phase-specific states"
```

---

## Task 3: Document Contexts Migration

**Files:**
- Create: `database/migrations/2025_11_15_230002_create_document_contexts_table.php`

**Step 1: Write the failing test**

Create test file: `tests/Unit/Models/DocumentContextTest.php`

```php
<?php

use App\Models\DocumentContext;
use App\Models\DocumentGenerationRun;

test('document context can be created', function () {
    $run = DocumentGenerationRun::factory()->create();

    $context = DocumentContext::create([
        'generation_run_id' => $run->id,
        'context_type' => 'standalone',
        'raw_input' => 'Test context',
        'assembled_context' => 'Assembled test context',
        'case_ids' => null,
        'evidence_ids' => null,
        'decision_ids' => null,
        'law_ids' => null,
    ]);

    expect($context)->toBeInstanceOf(DocumentContext::class);
    expect($context->context_type)->toBe('standalone');
});

test('document context belongs to generation run', function () {
    $run = DocumentGenerationRun::factory()->create();
    $context = DocumentContext::factory()->create(['generation_run_id' => $run->id]);

    expect($context->generationRun)->toBeInstanceOf(DocumentGenerationRun::class);
    expect($context->generationRun->id)->toBe($run->id);
});

test('document context stores arrays as JSON', function () {
    $context = DocumentContext::factory()->create([
        'case_ids' => ['uuid-1', 'uuid-2'],
        'evidence_ids' => ['ev-1', 'ev-2'],
    ]);

    expect($context->case_ids)->toBe(['uuid-1', 'uuid-2']);
    expect($context->evidence_ids)->toBe(['ev-1', 'ev-2']);
});
```

**Step 2: Run test to verify it fails**

Run: `php artisan test --filter=DocumentContextTest`

Expected: FAIL with "Class 'App\Models\DocumentContext' not found"

**Step 3: Create migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_contexts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('generation_run_id');
            $table->string('context_type', 50); // standalone, case_data, mixed
            $table->text('raw_input')->nullable();
            $table->text('assembled_context')->nullable();
            $table->json('case_ids')->nullable();
            $table->json('evidence_ids')->nullable();
            $table->json('decision_ids')->nullable();
            $table->json('law_ids')->nullable();
            $table->timestamp('created_at');

            $table->foreign('generation_run_id')
                ->references('id')
                ->on('document_generation_runs')
                ->onDelete('cascade');

            $table->index('generation_run_id');
            $table->index('context_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_contexts');
    }
};
```

**Step 4: Run migration**

Run: `php artisan migrate`

Expected: Migration runs successfully

**Step 5: Create model**

Create: `app/Models/DocumentContext.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentContext extends Model
{
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'generation_run_id',
        'context_type',
        'raw_input',
        'assembled_context',
        'case_ids',
        'evidence_ids',
        'decision_ids',
        'law_ids',
        'created_at',
    ];

    protected $casts = [
        'case_ids' => 'array',
        'evidence_ids' => 'array',
        'decision_ids' => 'array',
        'law_ids' => 'array',
        'created_at' => 'datetime',
    ];

    public function generationRun(): BelongsTo
    {
        return $this->belongsTo(DocumentGenerationRun::class, 'generation_run_id');
    }
}
```

**Step 6: Create factory**

Create: `database/factories/DocumentContextFactory.php`

```php
<?php

namespace Database\Factories;

use App\Models\DocumentContext;
use App\Models\DocumentGenerationRun;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentContextFactory extends Factory
{
    protected $model = DocumentContext::class;

    public function definition(): array
    {
        return [
            'generation_run_id' => DocumentGenerationRun::factory(),
            'context_type' => 'standalone',
            'raw_input' => 'Test context input',
            'assembled_context' => 'Assembled test context',
            'case_ids' => null,
            'evidence_ids' => null,
            'decision_ids' => null,
            'law_ids' => null,
            'created_at' => now(),
        ];
    }

    public function caseData(): static
    {
        return $this->state(fn (array $attributes) => [
            'context_type' => 'case_data',
            'case_ids' => ['uuid-1'],
        ]);
    }

    public function mixed(): static
    {
        return $this->state(fn (array $attributes) => [
            'context_type' => 'mixed',
            'case_ids' => ['uuid-1'],
            'evidence_ids' => ['ev-1', 'ev-2'],
        ]);
    }
}
```

**Step 7: Run test to verify it passes**

Run: `php artisan test --filter=DocumentContextTest`

Expected: PASS (all tests green)

**Step 8: Commit**

```bash
git add database/migrations/2025_11_15_230002_create_document_contexts_table.php \
  app/Models/DocumentContext.php \
  database/factories/DocumentContextFactory.php \
  tests/Unit/Models/DocumentContextTest.php
git commit -m "feat: add DocumentContext model and migration

- Create document_contexts table with cascade delete
- Support standalone, case_data, and mixed context types
- Store ID arrays as JSON (cases, evidence, decisions, laws)
- Add factory with context type states"
```

---

## Task 4: Document Type Configuration File

**Files:**
- Create: `config/documents.php`

**Step 1: Write the failing test**

Create test file: `tests/Unit/Config/DocumentTypesConfigTest.php`

```php
<?php

test('document types config exists', function () {
    $config = config('documents.types');

    expect($config)->toBeArray();
    expect($config)->toHaveKey('suppression_motion');
});

test('suppression motion config has required fields', function () {
    $config = config('documents.types.suppression_motion');

    expect($config)->toHaveKeys([
        'display_name',
        'category',
        'required_context',
        'default_structure',
    ]);
    expect($config['display_name'])->toBe('Prijedlog za isključenje dokaza');
    expect($config['category'])->toBe('court_motion');
});

test('all document types have required fields', function () {
    $types = config('documents.types');

    foreach ($types as $key => $config) {
        expect($config)->toHaveKeys([
            'display_name',
            'category',
            'default_structure',
        ], "Document type {$key} missing required fields");
    }
});
```

**Step 2: Run test to verify it fails**

Run: `php artisan test --filter=DocumentTypesConfigTest`

Expected: FAIL with "config('documents.types') returns null"

**Step 3: Create configuration file**

Create: `config/documents.php`

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Document Type Definitions
    |--------------------------------------------------------------------------
    |
    | Define all supported legal document types with their Croatian names,
    | required context, structure, and legal frameworks.
    |
    */

    'types' => [
        'suppression_motion' => [
            'display_name' => 'Prijedlog za isključenje dokaza',
            'category' => 'court_motion',
            'template' => 'suppression_motion_template',
            'required_context' => ['case_facts', 'evidence', 'legal_grounds'],
            'legal_framework' => ['ZKP', 'Ustav RH Članak 29, 34, 36'],
            'default_structure' => [
                'introduction',
                'factual_background',
                'legal_grounds',
                'constitutional_violations',
                'procedural_violations',
                'conclusion',
                'signature',
            ],
            'max_length' => 5000,
            'confidential' => false,
        ],

        'dismissal_motion' => [
            'display_name' => 'Zahtjev za odbacivanje optužnice',
            'category' => 'court_motion',
            'template' => 'dismissal_motion_template',
            'required_context' => ['case_facts', 'charges'],
            'legal_framework' => ['ZKP Članak 284', 'Ustav RH'],
            'default_structure' => [
                'introduction',
                'grounds_for_dismissal',
                'legal_analysis',
                'conclusion',
                'signature',
            ],
            'max_length' => 4000,
            'confidential' => false,
        ],

        'appeal_brief' => [
            'display_name' => 'Žalba',
            'category' => 'court_motion',
            'template' => 'appeal_template',
            'required_context' => ['verdict', 'appeal_grounds', 'trial_record'],
            'legal_framework' => ['ZKP Članak 469-485'],
            'default_structure' => [
                'introduction',
                'procedural_history',
                'grounds_for_appeal',
                'legal_analysis',
                'requested_relief',
                'conclusion',
                'signature',
            ],
            'max_length' => 10000,
            'confidential' => false,
        ],

        'client_letter' => [
            'display_name' => 'Pismo klijentu',
            'category' => 'client_communication',
            'template' => 'client_letter_template',
            'required_context' => ['case_update', 'next_steps'],
            'legal_framework' => null,
            'default_structure' => [
                'greeting',
                'case_update',
                'legal_analysis',
                'next_steps',
                'contact_information',
                'closing',
            ],
            'max_length' => 2000,
            'confidential' => true,
        ],

        'case_summary' => [
            'display_name' => 'Sažetak predmeta',
            'category' => 'client_communication',
            'template' => 'case_summary_template',
            'required_context' => ['case_facts', 'charges', 'strategy'],
            'legal_framework' => null,
            'default_structure' => [
                'overview',
                'charges',
                'key_facts',
                'defense_theory',
                'timeline',
                'risks_opportunities',
            ],
            'max_length' => 3000,
            'confidential' => true,
        ],

        'legal_opinion' => [
            'display_name' => 'Pravno mišljenje',
            'category' => 'client_communication',
            'template' => 'legal_opinion_template',
            'required_context' => ['legal_question', 'relevant_facts'],
            'legal_framework' => ['applicable_laws'],
            'default_structure' => [
                'question_presented',
                'short_answer',
                'facts',
                'legal_analysis',
                'conclusion',
            ],
            'max_length' => 4000,
            'confidential' => true,
        ],

        'case_strategy' => [
            'display_name' => 'Obrambena strategija',
            'category' => 'internal',
            'template' => 'strategy_template',
            'required_context' => ['case_facts', 'charges', 'evidence'],
            'legal_framework' => null,
            'default_structure' => [
                'case_overview',
                'strengths_weaknesses',
                'defense_theory',
                'evidence_strategy',
                'witness_strategy',
                'motion_plan',
                'trial_strategy',
                'risks_opportunities',
            ],
            'max_length' => 5000,
            'confidential' => true,
        ],

        'evidence_analysis' => [
            'display_name' => 'Analiza dokaza',
            'category' => 'internal',
            'template' => 'evidence_analysis_template',
            'required_context' => ['evidence'],
            'legal_framework' => ['ZKP Članak 331', 'Ustav RH'],
            'default_structure' => [
                'evidence_inventory',
                'admissibility_analysis',
                'credibility_assessment',
                'suppression_opportunities',
                'recontextualization_opportunities',
                'recommendations',
            ],
            'max_length' => 4000,
            'confidential' => true,
        ],

        'witness_preparation' => [
            'display_name' => 'Priprema svjedoka',
            'category' => 'internal',
            'template' => 'witness_prep_template',
            'required_context' => ['witness_info', 'testimony_topics'],
            'legal_framework' => null,
            'default_structure' => [
                'witness_overview',
                'key_testimony_points',
                'anticipated_questions',
                'preparation_strategy',
                'credibility_concerns',
                'dos_and_donts',
            ],
            'max_length' => 3000,
            'confidential' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Scoring Weights
    |--------------------------------------------------------------------------
    |
    | Weights for multi-dimensional document quality scoring.
    | Must sum to 1.0 (100%)
    |
    */

    'scoring_weights' => [
        'legal_rigor' => 0.40,         // 40% - Croatian law citations, procedural correctness
        'persuasiveness' => 0.25,       // 25% - Argument strength, rhetoric
        'clarity' => 0.20,              // 20% - Readability, organization
        'evidence_integration' => 0.10, // 10% - Evidence weaving
        'formatting' => 0.05,           // 5% - Croatian legal format
    ],

    /*
    |--------------------------------------------------------------------------
    | Generation Constraints
    |--------------------------------------------------------------------------
    */

    'max_iterations' => 10,
    'convergence_threshold' => 5.0, // Stop if improvement < 5%
    'min_acceptable_score' => 70.0,
];
```

**Step 4: Run test to verify it passes**

Run: `php artisan test --filter=DocumentTypesConfigTest`

Expected: PASS (all tests green)

**Step 5: Commit**

```bash
git add config/documents.php tests/Unit/Config/DocumentTypesConfigTest.php
git commit -m "feat: add document types configuration

- Define 9 Croatian legal document types with structure
- Configure scoring weights (legal rigor 40%, persuasiveness 25%, etc.)
- Set generation constraints (max 10 iterations, 5% convergence threshold)
- Add document type categories: court_motion, client_communication, internal"
```

---

## Task 5: ContextAssembler Service (Part 1 - Standalone Mode)

**Files:**
- Create: `app/Services/ContextAssembler.php`
- Create: `tests/Unit/Services/ContextAssemblerTest.php`

**Step 1: Write the failing test for standalone mode**

```php
<?php

use App\Services\ContextAssembler;

test('assembler handles standalone mode', function () {
    $assembler = new ContextAssembler();

    $result = $assembler->assemble([
        'document_type' => 'suppression_motion',
        'context' => 'Defendant arrested without warrant',
    ]);

    expect($result)->toHaveKeys(['document_type', 'context']);
    expect($result['document_type'])->toBe('suppression_motion');
    expect($result['context'])->toBe('Defendant arrested without warrant');
    expect($result)->not->toHaveKey('case_facts');
});

test('assembler determines standalone context type', function () {
    $assembler = new ContextAssembler();

    $result = $assembler->assemble([
        'document_type' => 'client_letter',
        'context' => 'Test context',
    ]);

    expect($result['context_type'])->toBe('standalone');
});
```

**Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ContextAssemblerTest`

Expected: FAIL with "Class 'App\Services\ContextAssembler' not found"

**Step 3: Implement ContextAssembler (standalone mode only)**

Create: `app/Services/ContextAssembler.php`

```php
<?php

namespace App\Services;

class ContextAssembler
{
    /**
     * Assemble context from input array
     *
     * @param  array  $input  Input data containing document_type, context, case_id, etc.
     * @return array Assembled context ready for document generation
     */
    public function assemble(array $input): array
    {
        $assembled = [
            'document_type' => $input['document_type'],
            'context' => $input['context'] ?? '',
        ];

        // Determine context type
        $assembled['context_type'] = $this->determineContextType($input);

        return $assembled;
    }

    /**
     * Determine the context type based on input
     *
     * @param  array  $input
     * @return string standalone, case_data, or mixed
     */
    protected function determineContextType(array $input): string
    {
        $hasCaseId = ! empty($input['case_id']);
        $hasStandaloneContext = ! empty($input['context']);

        if ($hasCaseId && $hasStandaloneContext) {
            return 'mixed';
        }

        if ($hasCaseId) {
            return 'case_data';
        }

        return 'standalone';
    }
}
```

**Step 4: Run test to verify it passes**

Run: `php artisan test --filter=ContextAssemblerTest`

Expected: PASS (2 tests green)

**Step 5: Commit**

```bash
git add app/Services/ContextAssembler.php tests/Unit/Services/ContextAssemblerTest.php
git commit -m "feat: add ContextAssembler service with standalone mode

- Implement basic context assembly for standalone input
- Auto-detect context type (standalone, case_data, mixed)
- Add unit tests for standalone mode"
```

---

## Task 6: ContextAssembler Service (Part 2 - Case Data Mode)

**Files:**
- Modify: `app/Services/ContextAssembler.php`
- Modify: `tests/Unit/Services/ContextAssemblerTest.php`

**Step 1: Write the failing test for case data mode**

Add to `tests/Unit/Services/ContextAssemblerTest.php`:

```php
test('assembler loads case data correctly', function () {
    $case = \App\Models\LegalCase::factory()->create([
        'facts' => 'Arrested for drug possession',
        'defendant_name' => 'Ivan Horvat',
        'charges' => 'Drug possession',
    ]);

    $assembler = new ContextAssembler();

    $result = $assembler->assemble([
        'document_type' => 'case_strategy',
        'case_id' => $case->id,
    ]);

    expect($result['context_type'])->toBe('case_data');
    expect($result['case_facts'])->toBe('Arrested for drug possession');
    expect($result['defendant'])->toBe('Ivan Horvat');
    expect($result['charges'])->toBe('Drug possession');
});

test('assembler handles missing case gracefully', function () {
    $assembler = new ContextAssembler();

    $result = $assembler->assemble([
        'document_type' => 'case_strategy',
        'case_id' => 'non-existent-uuid',
        'context' => 'Fallback context',
    ]);

    // Should fall back to standalone mode
    expect($result['context'])->toBe('Fallback context');
    expect($result)->toHaveKey('warning');
    expect($result['warning'])->toContain('unavailable');
});
```

**Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ContextAssemblerTest`

Expected: FAIL with case data tests failing

**Step 3: Implement case data loading**

Modify `app/Services/ContextAssembler.php`:

```php
<?php

namespace App\Services;

use App\Models\LegalCase;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;

class ContextAssembler
{
    /**
     * Assemble context from input array
     *
     * @param  array  $input  Input data containing document_type, context, case_id, etc.
     * @return array Assembled context ready for document generation
     */
    public function assemble(array $input): array
    {
        $assembled = [
            'document_type' => $input['document_type'],
            'context' => $input['context'] ?? '',
        ];

        // Determine context type
        $assembled['context_type'] = $this->determineContextType($input);

        // Load case data if case_id provided
        if (! empty($input['case_id'])) {
            $assembled = $this->loadCaseData($assembled, $input);
        }

        // Merge additional context if provided
        if (! empty($input['additional_context'])) {
            $assembled['additional_instructions'] = $input['additional_context'];
        }

        return $assembled;
    }

    /**
     * Load case data from database
     *
     * @param  array  $assembled
     * @param  array  $input
     * @return array
     */
    protected function loadCaseData(array $assembled, array $input): array
    {
        try {
            $case = LegalCase::with(['evidence', 'courtDecisions', 'laws'])
                ->findOrFail($input['case_id']);

            $assembled['case_facts'] = $case->facts;
            $assembled['defendant'] = $case->defendant_name;
            $assembled['charges'] = $case->charges;

            // Load related data
            if ($case->evidence) {
                $assembled['evidence'] = $case->evidence->toArray();
            }

            if ($case->courtDecisions) {
                $assembled['court_decisions'] = $case->courtDecisions->toArray();
            }

            if ($case->laws) {
                $assembled['laws'] = $case->laws->toArray();
            }
        } catch (ModelNotFoundException $e) {
            Log::warning('ContextAssembler: Case not found, falling back to standalone', [
                'case_id' => $input['case_id'],
            ]);

            // Graceful degradation to standalone mode
            $assembled['warning'] = 'Requested case data unavailable, using standalone mode';
            $assembled['context_type'] = 'standalone';
        }

        return $assembled;
    }

    /**
     * Determine the context type based on input
     *
     * @param  array  $input
     * @return string standalone, case_data, or mixed
     */
    protected function determineContextType(array $input): string
    {
        $hasCaseId = ! empty($input['case_id']);
        $hasStandaloneContext = ! empty($input['context']);

        if ($hasCaseId && $hasStandaloneContext) {
            return 'mixed';
        }

        if ($hasCaseId) {
            return 'case_data';
        }

        return 'standalone';
    }
}
```

**Step 4: Run test to verify it passes**

Run: `php artisan test --filter=ContextAssemblerTest`

Expected: PASS (4 tests green)

**Step 5: Commit**

```bash
git add app/Services/ContextAssembler.php tests/Unit/Services/ContextAssemblerTest.php
git commit -m "feat: add case data loading to ContextAssembler

- Load case facts, defendant, charges from database
- Eager load related evidence, court decisions, laws
- Gracefully degrade to standalone mode if case not found
- Add warning when case data unavailable"
```

---

## Task 7: ContextAssembler Service (Part 3 - Mixed Mode)

**Files:**
- Modify: `app/Services/ContextAssembler.php`
- Modify: `tests/Unit/Services/ContextAssemblerTest.php`

**Step 1: Write the failing test for mixed mode**

Add to `tests/Unit/Services/ContextAssemblerTest.php`:

```php
test('assembler handles mixed mode', function () {
    $case = \App\Models\LegalCase::factory()->create();
    $evidence1 = \App\Models\Evidence::factory()->create();
    $evidence2 = \App\Models\Evidence::factory()->create();

    $assembler = new ContextAssembler();

    $result = $assembler->assemble([
        'document_type' => 'suppression_motion',
        'case_id' => $case->id,
        'evidence_ids' => [$evidence1->id, $evidence2->id],
        'additional_context' => 'Focus on Ustav RH Članak 34',
    ]);

    expect($result['context_type'])->toBe('mixed');
    expect($result)->toHaveKey('case_facts');
    expect($result)->toHaveKey('evidence');
    expect($result)->toHaveKey('additional_instructions');
    expect($result['additional_instructions'])->toBe('Focus on Ustav RH Članak 34');

    // Should override case evidence with specific evidence_ids
    expect($result['evidence'])->toHaveCount(2);
});

test('assembler overrides with specific evidence IDs', function () {
    $case = \App\Models\LegalCase::factory()->create();
    $specificEvidence = \App\Models\Evidence::factory()->create([
        'description' => 'Specific evidence',
    ]);

    $assembler = new ContextAssembler();

    $result = $assembler->assemble([
        'document_type' => 'suppression_motion',
        'case_id' => $case->id,
        'evidence_ids' => [$specificEvidence->id],
    ]);

    expect($result['evidence'])->toHaveCount(1);
    expect($result['evidence'][0]['description'])->toBe('Specific evidence');
});
```

**Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ContextAssemblerTest`

Expected: FAIL with mixed mode tests failing

**Step 3: Implement mixed mode with specific ID overrides**

Modify `app/Services/ContextAssembler.php`, add after `loadCaseData` call in `assemble` method:

```php
public function assemble(array $input): array
{
    $assembled = [
        'document_type' => $input['document_type'],
        'context' => $input['context'] ?? '',
    ];

    // Determine context type
    $assembled['context_type'] = $this->determineContextType($input);

    // Load case data if case_id provided
    if (! empty($input['case_id'])) {
        $assembled = $this->loadCaseData($assembled, $input);
    }

    // Override with specific IDs if provided
    $assembled = $this->applySpecificOverrides($assembled, $input);

    // Merge additional context if provided
    if (! empty($input['additional_context'])) {
        $assembled['additional_instructions'] = $input['additional_context'];
    }

    return $assembled;
}
```

Add new method to the class:

```php
/**
 * Apply specific ID overrides (evidence_ids, decision_ids, etc.)
 *
 * @param  array  $assembled
 * @param  array  $input
 * @return array
 */
protected function applySpecificOverrides(array $assembled, array $input): array
{
    // Override with specific evidence IDs
    if (! empty($input['evidence_ids'])) {
        $assembled['evidence'] = \App\Models\Evidence::whereIn('id', $input['evidence_ids'])
            ->get()
            ->toArray();
    }

    // Override with specific decision IDs
    if (! empty($input['decision_ids'])) {
        $assembled['court_decisions'] = \App\Models\CourtDecisionDocument::whereIn('id', $input['decision_ids'])
            ->get()
            ->toArray();
    }

    // Override with specific law IDs
    if (! empty($input['law_ids'])) {
        $assembled['laws'] = \App\Models\Law::whereIn('id', $input['law_ids'])
            ->get()
            ->toArray();
    }

    return $assembled;
}
```

**Step 4: Run test to verify it passes**

Run: `php artisan test --filter=ContextAssemblerTest`

Expected: PASS (6 tests green)

**Step 5: Commit**

```bash
git add app/Services/ContextAssembler.php tests/Unit/Services/ContextAssemblerTest.php
git commit -m "feat: add mixed mode and specific ID overrides to ContextAssembler

- Support mixed mode (case data + standalone context)
- Allow overriding with specific evidence_ids, decision_ids, law_ids
- Add additional_instructions field for extra requirements
- Complete hybrid context assembly implementation"
```

---

**IMPLEMENTATION PLAN CONTINUES...**

Due to length constraints, this is the first batch of tasks (Tasks 1-7). The plan continues with:

- Task 8-10: DocumentCritic Service (TDD)
- Task 11-13: DocumentWorker Service (TDD)
- Task 14-17: RecursiveDocumentWritingAgent (TDD)
- Task 18-20: API Controller and Routes (TDD)
- Task 21-23: Integration Tests
- Task 24: Final verification and documentation

Each subsequent task follows the same TDD pattern:
1. Write failing test
2. Run test (verify fail)
3. Implement minimal code
4. Run test (verify pass)
5. Commit

**Total estimated tasks: 24**
**Total estimated time: 8-12 hours** (assuming 20-30 minutes per task)

---

## Next Steps

After completing this implementation plan:

1. **@superpowers:executing-plans** - Execute tasks in batches of 3
2. **@superpowers:verification-before-completion** - Verify all tests pass
3. **@superpowers:finishing-a-development-branch** - Create PR and merge

Would you like me to:
A) Continue writing the remaining tasks (8-24)?
B) Start executing this first batch?

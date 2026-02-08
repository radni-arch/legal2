# Recursive Document Writing Agent - Design Document

**Date**: 2025-11-15
**Author**: AI Legal War Machine Team
**Status**: Design Complete - Ready for Implementation

## Overview

The **RecursiveDocumentWritingAgent** is a self-improving legal document generator for Croatian defense attorneys. It uses a critic-worker iteration pattern where the agent recursively reviews and improves its own work across multiple dimensions of quality.

### Key Characteristics

- **Document Types**: All legal documents (court motions, client communications, internal strategies)
- **Quality Evaluation**: Comprehensive multi-dimensional scoring (legal rigor, persuasiveness, clarity, evidence integration, formatting)
- **Data Integration**: Hybrid approach - standalone input OR case database integration
- **Stopping Criteria**: Converges when improvement delta < 5% or after 10 iterations
- **Iteration Pattern**: Critic plans → Worker executes (2 phases per iteration)
- **Model Configuration**: Configurable AI models per request (GPT-4o, GPT-4o-mini, o1-preview)
- **Full Transparency**: Complete iteration history tracking

---

## 1. High-Level Architecture

### Core Components

```
┌─────────────────────────────────────────────────────────────┐
│         RecursiveDocumentWritingAgent (Vizra ADK)           │
│                                                             │
│  ┌──────────────────────────────────────────────────────┐  │
│  │           Iteration Loop (max 10)                    │  │
│  │                                                      │  │
│  │  Critic Phase → Worker Phase → Convergence Check    │  │
│  └──────────────────────────────────────────────────────┘  │
│                                                             │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────────┐ │
│  │ Document     │  │ Document     │  │ Context          │ │
│  │ Critic       │  │ Worker       │  │ Assembler        │ │
│  │              │  │              │  │                  │ │
│  │ • Scores     │  │ • Generates  │  │ • Standalone     │ │
│  │ • Evaluates  │  │ • Improves   │  │ • Case DB        │ │
│  │ • Plans      │  │ • Formats    │  │ • Mixed          │ │
│  └──────────────┘  └──────────────┘  └──────────────────┘ │
└─────────────────────────────────────────────────────────────┘
```

### Integration Points

- **Vizra ADK**: Extends `BaseLlmAgent` for consistency with existing agents
- **Laravel Models**: Integrates with `LegalCase`, `CourtDecisionDocument`, `CaseDocument`, `Evidence`
- **OpenAI Service**: Configurable models per phase (Critic, Worker)
- **Job Queue**: Optional async execution for long documents

---

## 2. Data Model

### New Database Tables

#### `document_generation_runs`

Primary table tracking each document generation session.

```sql
CREATE TABLE document_generation_runs (
    id UUID PRIMARY KEY,
    document_type VARCHAR(255) NOT NULL,  -- motion, client_letter, strategy, etc.
    case_id UUID NULLABLE,                -- FK to cases table
    status VARCHAR(50) NOT NULL,          -- running, completed, failed
    final_document TEXT,
    final_score DECIMAL(5,2),             -- 0.00 to 100.00
    total_iterations INTEGER,             -- 1-10
    stopped_reason VARCHAR(50),           -- converged, max_iterations, error
    model_config JSON,                    -- {critic_model, worker_model}
    user_id UUID NOT NULL,                -- FK to users
    created_at TIMESTAMP,
    updated_at TIMESTAMP,

    FOREIGN KEY (case_id) REFERENCES cases(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);
```

#### `document_iterations`

Stores complete history of all iterations (critic + worker phases).

```sql
CREATE TABLE document_iterations (
    id UUID PRIMARY KEY,
    generation_run_id UUID NOT NULL,      -- FK to document_generation_runs
    iteration_number INTEGER NOT NULL,    -- 1-10
    phase VARCHAR(50) NOT NULL,           -- critic, worker
    document_version TEXT,                -- Current version after worker phase
    critic_feedback JSON,                 -- Detailed analysis and improvement plan
    scores JSON,                          -- {legal_rigor, persuasiveness, clarity, evidence_integration, formatting}
    weighted_score DECIMAL(5,2),          -- Calculated weighted average
    improvement_delta DECIMAL(5,2),       -- % improvement from previous iteration
    ai_model_used VARCHAR(100),           -- gpt-4o, gpt-4o-mini, o1-preview
    tokens_used INTEGER,
    cost_estimate DECIMAL(10,4),
    created_at TIMESTAMP,

    FOREIGN KEY (generation_run_id) REFERENCES document_generation_runs(id) ON DELETE CASCADE,
    INDEX idx_run_iteration (generation_run_id, iteration_number)
);
```

#### `document_contexts`

Stores input context and assembled data for each generation run.

```sql
CREATE TABLE document_contexts (
    id UUID PRIMARY KEY,
    generation_run_id UUID NOT NULL,      -- FK to document_generation_runs
    context_type VARCHAR(50) NOT NULL,    -- standalone, case_data, mixed
    raw_input TEXT,                       -- User-provided context
    assembled_context TEXT,               -- After ContextAssembler processing
    case_ids JSON,                        -- Array of case UUIDs
    evidence_ids JSON,                    -- Array of evidence UUIDs
    decision_ids JSON,                    -- Array of decision UUIDs
    law_ids JSON,                         -- Array of law UUIDs
    created_at TIMESTAMP,

    FOREIGN KEY (generation_run_id) REFERENCES document_generation_runs(id) ON DELETE CASCADE,
    INDEX idx_run_context (generation_run_id)
);
```

---

## 3. Iteration Flow

### Iteration 1: Initial Draft

**Critic Phase:**
- Input: Requirements, context, document type
- Output: Structural plan, key legal elements needed
- Stores: Initial plan in `critic_feedback`

**Worker Phase:**
- Input: Critic's plan, context
- Output: Initial document draft
- Stores: Draft in `document_version`

### Iterations 2-10: Refinement

**Critic Phase:**
1. Reviews previous document version
2. Scores 5 dimensions (weighted)
3. Identifies specific weaknesses with examples
4. Creates concrete improvement plan
5. Stores: Scores, feedback, improvement plan

**Worker Phase:**
1. Reads previous version
2. Applies critic's improvement plan
3. Maintains Croatian legal format
4. Generates improved version
5. Stores: New version, calculates improvement delta

**Convergence Check:**
```php
$improvement_delta = (($current_score - $previous_score) / $previous_score) * 100;

if ($improvement_delta < 5.0 && $iteration > 1) {
    $stopped_reason = 'converged';
    break;
}

if ($iteration >= 10) {
    $stopped_reason = 'max_iterations';
    break;
}
```

---

## 4. Scoring System

### Weighted Multi-Dimensional Scoring

```php
$dimensions = [
    'legal_rigor' => 0.40,          // 40% - Croatian law citations, procedural correctness
    'persuasiveness' => 0.25,        // 25% - Argument strength, rhetoric
    'clarity' => 0.20,               // 20% - Readability, organization
    'evidence_integration' => 0.10,  // 10% - Evidence weaving
    'formatting' => 0.05             // 5% - Croatian legal format compliance
];

$weighted_score = array_sum(array_map(
    fn($dim, $weight) => $scores[$dim] * $weight,
    array_keys($dimensions),
    $dimensions
));
```

### Scoring Criteria

**Legal Rigor (40%):**
- Accurate Croatian law citations (ZKP, Ustav RH, EKLJP)
- Procedural correctness
- Constitutional grounds properly invoked
- Case law references where applicable

**Persuasiveness (25%):**
- Argument strength and logical flow
- Rhetorical effectiveness
- Appeal to judicial reasoning
- Anticipation and rebuttal of counter-arguments

**Clarity (20%):**
- Professional Croatian legal language
- Logical organization
- Paragraph structure
- Readability for judges and attorneys

**Evidence Integration (10%):**
- Evidence properly cited and referenced
- Evidence supports legal arguments
- No fabrication or speculation
- Proper evidentiary foundation

**Formatting (5%):**
- Croatian legal document format
- Proper sections and headers
- Professional presentation
- Citation format compliance

---

## 5. Context Assembly (Hybrid Integration)

### Three Modes

**1. Standalone Mode:**
```php
$input = [
    'document_type' => 'suppression_motion',
    'context' => 'Defendant arrested without warrant at 2am. Search conducted without consent...',
    'requirements' => 'Challenge illegal search under Ustav RH Članak 34'
];
// No database queries, uses raw context only
```

**2. Case Data Mode:**
```php
$input = [
    'document_type' => 'case_strategy',
    'case_id' => 'uuid-123'
];
// ContextAssembler automatically fetches all related:
// - Case facts
// - Evidence
// - Court decisions
// - Related laws
```

**3. Mixed Mode:**
```php
$input = [
    'document_type' => 'dismissal_motion',
    'case_id' => 'uuid-123',              // Pull case facts
    'evidence_ids' => ['ev-1', 'ev-2'],   // Specific evidence
    'additional_context' => 'Focus on prosecutorial delay violations per ZKP Članak 284'
];
```

### ContextAssembler Service

```php
class ContextAssembler
{
    public function assemble(array $input): array
    {
        $assembled = [
            'document_type' => $input['document_type'],
            'context' => $input['context'] ?? '',
        ];

        // Pull case data if case_id provided
        if (!empty($input['case_id'])) {
            $case = LegalCase::with(['evidence', 'courtDecisions', 'laws'])->findOrFail($input['case_id']);

            $assembled['case_facts'] = $case->facts;
            $assembled['defendant'] = $case->defendant_name;
            $assembled['charges'] = $case->charges;
            $assembled['evidence'] = $case->evidence->toArray();
            $assembled['court_decisions'] = $case->courtDecisions->toArray();
            $assembled['laws'] = $case->laws->toArray();
        }

        // Override with specific IDs if provided
        if (!empty($input['evidence_ids'])) {
            $assembled['evidence'] = Evidence::whereIn('id', $input['evidence_ids'])->get()->toArray();
        }

        if (!empty($input['decision_ids'])) {
            $assembled['court_decisions'] = CourtDecisionDocument::whereIn('id', $input['decision_ids'])->get()->toArray();
        }

        // Merge additional context
        if (!empty($input['additional_context'])) {
            $assembled['additional_instructions'] = $input['additional_context'];
        }

        return $assembled;
    }
}
```

---

## 6. Critic & Worker Implementation

### DocumentCritic Service

Evaluates documents and creates improvement plans.

**Critic Prompt Template:**
```
You are an expert Croatian legal document critic evaluating a {document_type} for a defense attorney.

CURRENT DOCUMENT:
{document_version}

CONTEXT:
{assembled_context}

EVALUATION CRITERIA (weighted):
- Legal Rigor (40%): Croatian law citations (ZKP, Ustav RH), procedural accuracy, constitutional grounds
- Persuasiveness (25%): Argument strength, rhetorical effectiveness, judicial appeal
- Clarity (20%): Organization, readability, professional Croatian legal language
- Evidence Integration (10%): Evidence properly cited and supporting arguments
- Formatting (5%): Croatian legal document format compliance

TASK:
1. Score each dimension (0-100) with specific justification from the document
2. Identify top 3 weaknesses with concrete examples
3. Create detailed improvement plan (what to add/change/remove)

OUTPUT FORMAT (JSON):
{
  "scores": {
    "legal_rigor": 85,
    "persuasiveness": 78,
    "clarity": 90,
    "evidence_integration": 70,
    "formatting": 95
  },
  "weighted_score": 82.5,
  "weaknesses": [
    "Missing citation to ZKP Članak 191 for search warrant requirements",
    "Argument in paragraph 3 lacks logical connection to constitutional violation",
    "Evidence item #4 mentioned but not properly integrated into legal argument"
  ],
  "improvement_plan": "1. Add ZKP Članak 191 citation in section 2...\n2. Strengthen paragraph 3 by...\n3. Integrate evidence #4 by..."
}
```

### DocumentWorker Service

Generates and improves documents based on critic feedback.

**Worker Prompt (Iteration 1):**
```
You are an expert Croatian defense attorney. Generate a {document_type}.

CONTEXT:
{assembled_context}

STRUCTURAL PLAN FROM CRITIC:
{critic_improvement_plan}

REQUIREMENTS:
- Follow Croatian legal format for {document_type}
- Cite applicable laws (ZKP, Ustav RH, EKLJP) with article numbers
- Use professional legal Croatian language
- Structure: {default_structure_for_document_type}

Generate the complete document now.
```

**Worker Prompt (Iterations 2-10):**
```
You are an expert Croatian defense attorney improving a {document_type}.

PREVIOUS VERSION:
{previous_document}

CRITIC EVALUATION:
Scores: {scores}
Weaknesses: {weaknesses}

IMPROVEMENT PLAN:
{improvement_plan}

TASK:
Apply all improvements from the critic's plan while maintaining document coherence and Croatian legal format.

Generate the improved version now.
```

**Model Configuration:**
```php
$modelConfig = $input['model_config'] ?? [
    'critic_model' => 'gpt-4o',
    'worker_model' => 'gpt-4o'
];

// Allow per-request configuration for cost optimization
// Examples:
// - High quality: {critic: 'o1-preview', worker: 'gpt-4o'}
// - Balanced: {critic: 'gpt-4o', worker: 'gpt-4o'}
// - Fast/cheap: {critic: 'gpt-4o-mini', worker: 'gpt-4o-mini'}
```

---

## 7. Document Type Definitions

### Configuration Structure

```php
// config/documents.php

return [
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
                'signature'
            ],
            'max_length' => 5000, // words
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
                'signature'
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
                'signature'
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
                'closing'
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
                'risks_opportunities'
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
                'conclusion'
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
                'risks_opportunities'
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
                'recommendations'
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
                'dos_and_donts'
            ],
            'max_length' => 3000,
            'confidential' => true,
        ],
    ]
];
```

---

## 8. API & Usage Patterns

### Primary Endpoint

**Generate Document:**
```http
POST /api/documents/generate

Request Body:
{
  "document_type": "suppression_motion",
  "context_type": "case_data",     // standalone | case_data | mixed
  "case_id": "uuid-123",            // optional
  "evidence_ids": ["ev-1", "ev-2"], // optional
  "decision_ids": ["dec-1"],        // optional
  "context": "Additional context",  // optional
  "requirements": "Focus on...",    // optional
  "model_config": {                 // optional
    "critic_model": "gpt-4o",
    "worker_model": "gpt-4o"
  }
}

Response (202 Accepted):
{
  "run_id": "uuid-456",
  "status": "running",
  "message": "Document generation started",
  "estimated_time": "2-5 minutes"
}
```

**Check Status:**
```http
GET /api/documents/runs/{run_id}

Response:
{
  "id": "uuid-456",
  "document_type": "suppression_motion",
  "status": "completed",
  "current_iteration": 7,
  "total_iterations": 7,
  "stopped_reason": "converged",
  "final_score": 92.5,
  "final_document": "...",
  "created_at": "2025-11-15T10:00:00Z",
  "completed_at": "2025-11-15T10:04:32Z"
}
```

**Get Full History:**
```http
GET /api/documents/runs/{run_id}/iterations

Response:
{
  "run_id": "uuid-456",
  "iterations": [
    {
      "iteration_number": 1,
      "phase": "critic",
      "critic_feedback": {...},
      "scores": null,
      "created_at": "..."
    },
    {
      "iteration_number": 1,
      "phase": "worker",
      "document_version": "...",
      "weighted_score": 78.5,
      "created_at": "..."
    },
    // ... all iterations
  ]
}
```

### Usage Examples

**1. Quick Standalone Motion:**
```php
use App\Agents\RecursiveDocumentWritingAgent;

$agent = app(RecursiveDocumentWritingAgent::class);

$run = $agent->generate([
    'document_type' => 'dismissal_motion',
    'context' => 'Defendant charged with drug possession. Prosecutor exceeded 6-month investigation deadline per ZKP Članak 284.',
    'requirements' => 'Focus on procedural deadline violation'
]);

// Returns DocumentGenerationRun model
echo $run->final_document;
echo "Score: " . $run->final_score;
```

**2. Case-Integrated Strategy:**
```php
$run = $agent->generate([
    'document_type' => 'case_strategy',
    'case_id' => $case->id,
    'model_config' => [
        'critic_model' => 'o1-preview',  // Deep reasoning for critique
        'worker_model' => 'gpt-4o'        // Strong writing
    ]
]);
```

**3. Mixed Mode with Specific Evidence:**
```php
$run = $agent->generate([
    'document_type' => 'suppression_motion',
    'case_id' => $case->id,
    'evidence_ids' => [$illegalSearch->id, $coercedStatement->id],
    'additional_context' => 'Emphasize Ustav RH Članak 34 and 36 violations'
]);
```

**4. Async Execution (Long Documents):**
```php
use App\Jobs\GenerateDocumentJob;

// Dispatch to queue
$run = DocumentGenerationRun::create([...]);
GenerateDocumentJob::dispatch($run->id);

// Monitor via webhook or polling
```

---

## 9. Error Handling & Recovery

### Error Scenarios

**1. AI Model Failures:**
```php
try {
    $response = $this->openai->chat($prompt, $model);
} catch (OpenAIException $e) {
    Log::error('AI call failed', [
        'run_id' => $run->id,
        'iteration' => $iteration,
        'phase' => $phase,
        'error' => $e->getMessage()
    ]);

    // Retry with exponential backoff (2s, 4s, 8s)
    if ($retryCount < 3) {
        sleep(2 ** $retryCount);
        return $this->retryAICall($prompt, $model, $retryCount + 1);
    }

    // Mark run as failed after 3 retries
    $run->update([
        'status' => 'failed',
        'error_message' => $e->getMessage()
    ]);

    throw $e;
}
```

**2. Context Assembly Failures:**
```php
try {
    $context = $assembler->assemble($input);
} catch (ModelNotFoundException $e) {
    Log::warning('Case not found, falling back to standalone', [
        'case_id' => $input['case_id']
    ]);

    // Graceful degradation - use standalone mode
    $context = [
        'context' => $input['context'] ?? '',
        'warning' => 'Requested case data unavailable, using standalone mode'
    ];
}
```

**3. Iteration Timeout:**
```php
$iterationStart = time();
set_time_limit(120); // 2 minutes per iteration

// Save checkpoint after each iteration
if (time() - $iterationStart > 100) {
    $this->saveCheckpoint($run, $iteration);
}

// Allow resuming from last checkpoint
if ($run->status === 'failed' && $run->last_checkpoint) {
    $run = $agent->resume($run);
}
```

**4. Score Degradation:**
```php
// Reject iterations that worsen quality
if ($currentScore < $previousScore * 0.9) {
    Log::warning('Score degradation detected', [
        'run_id' => $run->id,
        'iteration' => $iteration,
        'previous_score' => $previousScore,
        'current_score' => $currentScore,
        'delta' => $currentScore - $previousScore
    ]);

    // Revert to previous version, adjust critic prompt
    $this->revertIteration($run, $iteration);

    // Try again with stronger critic instructions
    return $this->retryWithStrongerCritic($previousVersion, $iteration);
}
```

**5. Invalid JSON Response:**
```php
try {
    $criticResponse = json_decode($aiResponse, true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $e) {
    Log::error('Invalid JSON from critic', [
        'run_id' => $run->id,
        'response' => $aiResponse
    ]);

    // Retry with explicit JSON formatting instructions
    $prompt .= "\n\nIMPORTANT: Respond with ONLY valid JSON, no additional text.";
    return $this->retryAICall($prompt, $model, $retryCount + 1);
}
```

### Recovery Mechanisms

- **Checkpointing**: Save state after each iteration
- **Graceful Degradation**: Fall back to simpler modes on failures
- **Retry Logic**: Exponential backoff for transient failures
- **Version Rollback**: Reject quality-degrading iterations
- **Resume Support**: Continue from last successful iteration

---

## 10. Testing Strategy

### Unit Tests

**DocumentCritic Tests:**
```php
// tests/Unit/Services/DocumentCriticTest.php

test('critic scores all five dimensions', function() {
    $critic = app(DocumentCritic::class);
    $result = $critic->evaluate($sampleDocument, $context);

    expect($result['scores'])->toHaveKeys([
        'legal_rigor',
        'persuasiveness',
        'clarity',
        'evidence_integration',
        'formatting'
    ]);

    expect($result['weighted_score'])->toBeBetween(0, 100);
});

test('critic identifies weaknesses with examples', function() {
    $critic = app(DocumentCritic::class);
    $result = $critic->evaluate($weakDocument, $context);

    expect($result['weaknesses'])->toBeArray();
    expect($result['weaknesses'])->toHaveCount(3);
    expect($result['improvement_plan'])->toBeString();
});

test('critic calculates weighted score correctly', function() {
    $scores = [
        'legal_rigor' => 80,
        'persuasiveness' => 70,
        'clarity' => 90,
        'evidence_integration' => 60,
        'formatting' => 100
    ];

    $weighted = (80 * 0.40) + (70 * 0.25) + (90 * 0.20) + (60 * 0.10) + (100 * 0.05);
    // = 32 + 17.5 + 18 + 6 + 5 = 78.5

    expect($weighted)->toBe(78.5);
});
```

**DocumentWorker Tests:**
```php
// tests/Unit/Services/DocumentWorkerTest.php

test('worker generates initial draft from context', function() {
    $worker = app(DocumentWorker::class);
    $document = $worker->generateInitial('suppression_motion', $context);

    expect($document)->toBeString();
    expect($document)->toContain('Prijedlog');
    expect($document)->toContain('ZKP');
});

test('worker applies critic improvements', function() {
    $worker = app(DocumentWorker::class);
    $improved = $worker->improve($previousVersion, $criticFeedback);

    expect($improved)->toBeString();
    expect(strlen($improved))->toBeGreaterThan(strlen($previousVersion));
});
```

**ContextAssembler Tests:**
```php
// tests/Unit/Services/ContextAssemblerTest.php

test('assembler handles standalone mode', function() {
    $assembler = app(ContextAssembler::class);
    $result = $assembler->assemble([
        'context' => 'Test context',
        'document_type' => 'suppression_motion'
    ]);

    expect($result['context'])->toBe('Test context');
    expect($result)->not->toHaveKey('case_facts');
});

test('assembler loads case data correctly', function() {
    $case = LegalCase::factory()->create();
    $assembler = app(ContextAssembler::class);

    $result = $assembler->assemble([
        'case_id' => $case->id,
        'document_type' => 'case_strategy'
    ]);

    expect($result['case_facts'])->toBe($case->facts);
    expect($result['defendant'])->toBe($case->defendant_name);
});

test('assembler handles mixed mode', function() {
    $case = LegalCase::factory()->create();
    $evidence = Evidence::factory()->create();

    $assembler = app(ContextAssembler::class);
    $result = $assembler->assemble([
        'case_id' => $case->id,
        'evidence_ids' => [$evidence->id],
        'additional_context' => 'Extra instructions'
    ]);

    expect($result)->toHaveKeys(['case_facts', 'evidence', 'additional_instructions']);
});

test('assembler gracefully handles missing case', function() {
    $assembler = app(ContextAssembler::class);
    $result = $assembler->assemble([
        'case_id' => 'non-existent-uuid',
        'context' => 'Fallback context'
    ]);

    // Should fall back to standalone mode
    expect($result['context'])->toBe('Fallback context');
    expect($result)->toHaveKey('warning');
});
```

### Feature Tests

**RecursiveDocumentWritingAgent Tests:**
```php
// tests/Feature/RecursiveDocumentAgentTest.php

test('agent generates document with iterative improvement', function() {
    $agent = app(RecursiveDocumentWritingAgent::class);

    $run = $agent->generate([
        'document_type' => 'suppression_motion',
        'context' => 'Defendant arrested without warrant. Search at 2am without consent.'
    ]);

    expect($run->status)->toBe('completed');
    expect($run->total_iterations)->toBeGreaterThan(0);
    expect($run->final_score)->toBeGreaterThan(70);

    // Verify score improved
    $firstScore = $run->iterations->where('iteration_number', 1)->first()->weighted_score;
    $lastScore = $run->final_score;
    expect($lastScore)->toBeGreaterThanOrEqual($firstScore);
});

test('agent stops on convergence', function() {
    $agent = app(RecursiveDocumentWritingAgent::class);

    $run = $agent->generate([
        'document_type' => 'client_letter',
        'context' => 'Simple case update'
    ]);

    expect($run->stopped_reason)->toBeIn(['converged', 'max_iterations']);

    if ($run->stopped_reason === 'converged') {
        expect($run->total_iterations)->toBeLessThan(10);
    }
});

test('agent respects max iterations', function() {
    $agent = app(RecursiveDocumentWritingAgent::class);

    $run = $agent->generate([
        'document_type' => 'appeal_brief',
        'context' => 'Complex appeal with multiple grounds'
    ]);

    expect($run->total_iterations)->toBeLessThanOrEqual(10);
});

test('agent stores full iteration history', function() {
    $agent = app(RecursiveDocumentWritingAgent::class);

    $run = $agent->generate([
        'document_type' => 'dismissal_motion',
        'context' => 'Test context'
    ]);

    $iterations = $run->iterations;

    // Each iteration has critic + worker phases
    expect($iterations->count())->toBe($run->total_iterations * 2);

    // Verify structure
    $criticPhases = $iterations->where('phase', 'critic');
    $workerPhases = $iterations->where('phase', 'worker');

    expect($criticPhases->count())->toBe($run->total_iterations);
    expect($workerPhases->count())->toBe($run->total_iterations);
});
```

**Integration Tests:**
```php
// tests/Feature/DocumentGenerationIntegrationTest.php

test('agent integrates with case database', function() {
    $case = LegalCase::factory()->create([
        'defendant_name' => 'Ivan Horvat',
        'facts' => 'Arrested for drug possession'
    ]);

    $agent = app(RecursiveDocumentWritingAgent::class);
    $run = $agent->generate([
        'document_type' => 'case_strategy',
        'case_id' => $case->id
    ]);

    expect($run->context->case_ids)->toContain($case->id);
    expect($run->final_document)->toContain('Ivan Horvat');
    expect($run->final_document)->toContain('drug possession');
});

test('agent uses configurable models', function() {
    $agent = app(RecursiveDocumentWritingAgent::class);

    $run = $agent->generate([
        'document_type' => 'legal_opinion',
        'context' => 'Test context',
        'model_config' => [
            'critic_model' => 'gpt-4o',
            'worker_model' => 'gpt-4o-mini'
        ]
    ]);

    $criticIterations = $run->iterations->where('phase', 'critic');
    $workerIterations = $run->iterations->where('phase', 'worker');

    expect($criticIterations->first()->ai_model_used)->toBe('gpt-4o');
    expect($workerIterations->first()->ai_model_used)->toBe('gpt-4o-mini');
});
```

### Monitoring Metrics

Track these metrics in production:

```php
// Average iterations per document type
SELECT document_type, AVG(total_iterations) as avg_iterations
FROM document_generation_runs
WHERE status = 'completed'
GROUP BY document_type;

// Average final scores per document type
SELECT document_type, AVG(final_score) as avg_score
FROM document_generation_runs
WHERE status = 'completed'
GROUP BY document_type;

// Convergence rate
SELECT
    COUNT(CASE WHEN stopped_reason = 'converged' THEN 1 END) / COUNT(*) * 100 as convergence_rate
FROM document_generation_runs
WHERE status = 'completed';

// Average cost per document
SELECT document_type, AVG(total_cost) as avg_cost
FROM (
    SELECT generation_run_id, SUM(cost_estimate) as total_cost
    FROM document_iterations
    GROUP BY generation_run_id
) costs
JOIN document_generation_runs ON costs.generation_run_id = document_generation_runs.id
GROUP BY document_type;

// Failure rate
SELECT
    COUNT(CASE WHEN status = 'failed' THEN 1 END) / COUNT(*) * 100 as failure_rate
FROM document_generation_runs;
```

---

## 11. Implementation Checklist

### Phase 1: Core Infrastructure
- [ ] Create database migrations (3 tables)
- [ ] Create Eloquent models (DocumentGenerationRun, DocumentIteration, DocumentContext)
- [ ] Create RecursiveDocumentWritingAgent (extends BaseLlmAgent)
- [ ] Create ContextAssembler service
- [ ] Create document type configuration file

### Phase 2: Critic & Worker
- [ ] Implement DocumentCritic service
- [ ] Implement DocumentWorker service
- [ ] Create prompt templates for all document types
- [ ] Implement weighted scoring system
- [ ] Implement convergence logic

### Phase 3: API & Controllers
- [ ] Create DocumentGenerationController
- [ ] Implement POST /api/documents/generate endpoint
- [ ] Implement GET /api/documents/runs/{id} endpoint
- [ ] Implement GET /api/documents/runs/{id}/iterations endpoint
- [ ] Add request validation

### Phase 4: Error Handling
- [ ] Implement AI retry logic with exponential backoff
- [ ] Implement graceful degradation for context assembly
- [ ] Implement checkpointing system
- [ ] Implement score degradation detection
- [ ] Add comprehensive error logging

### Phase 5: Testing
- [ ] Write unit tests for DocumentCritic
- [ ] Write unit tests for DocumentWorker
- [ ] Write unit tests for ContextAssembler
- [ ] Write feature tests for RecursiveDocumentWritingAgent
- [ ] Write integration tests for database integration
- [ ] Write API endpoint tests

### Phase 6: Documentation & Polish
- [ ] Add inline code documentation
- [ ] Create API documentation
- [ ] Create user guide for attorneys
- [ ] Add monitoring dashboard
- [ ] Performance optimization

---

## 12. Future Enhancements

**Post-MVP Improvements:**

1. **Multi-language Support**: English legal documents for international cases
2. **Template Customization**: Allow attorneys to define custom document templates
3. **Collaborative Editing**: Multiple attorneys reviewing/editing generated documents
4. **Version Comparison**: Visual diff between iterations
5. **Quality Prediction**: ML model to predict final score from initial context
6. **Cost Optimization**: Automatically select optimal model config based on document type
7. **Batch Generation**: Generate multiple document types for one case simultaneously
8. **Integration with Court Systems**: Auto-file motions via e-Spis integration

---

## Conclusion

The RecursiveDocumentWritingAgent provides Croatian defense attorneys with an AI-powered tool that continuously improves legal documents through self-critique. By combining comprehensive quality scoring, flexible data integration, and full transparency through iteration tracking, it ensures high-quality legal work while maintaining attorney oversight and control.

**Key Benefits:**
- **Quality**: Multi-dimensional scoring ensures comprehensive excellence
- **Flexibility**: Hybrid context assembly supports all workflows
- **Transparency**: Full iteration history builds trust and understanding
- **Efficiency**: Iterative improvement produces better documents faster
- **Integration**: Seamless integration with existing Vizra ADK and Laravel infrastructure

**Next Step**: Create detailed implementation plan with bite-sized tasks for development.

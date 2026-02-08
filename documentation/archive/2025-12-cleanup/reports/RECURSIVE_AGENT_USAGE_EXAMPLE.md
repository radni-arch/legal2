# RecursiveDocumentWritingAgent Usage Examples

## Basic Usage

```php
use App\Agents\RecursiveDocumentWritingAgent;
use App\Services\ContextAssembler;
use App\Services\DocumentCritic;
use App\Services\DocumentWorker;

// Instantiate the agent (typically via dependency injection)
$agent = new RecursiveDocumentWritingAgent(
    app(ContextAssembler::class),
    app(DocumentCritic::class),
    app(DocumentWorker::class)
);

// Prepare input
$input = [
    'document_type' => 'suppression_motion',
    'context' => 'Defendant was arrested without warrant on 15.01.2025.',
    'evidence' => [
        ['description' => 'Search conducted without judicial approval'],
    ],
    'legal_grounds' => 'ZKP Članak 215, Ustav RH Članak 34',
];

$userId = auth()->id();

// Generate document
$result = $agent->generate($input, $userId);

// Access results
echo "Status: " . $result->status . "\n";
echo "Final Document:\n" . $result->final_document . "\n";
echo "Final Score: " . $result->final_score . "\n";
echo "Total Iterations: " . $result->total_iterations . "\n";
echo "Stopped Reason: " . $result->stopped_reason . "\n";

// Access iterations
foreach ($result->iterations as $iteration) {
    echo "Iteration {$iteration->iteration_number} ({$iteration->phase})\n";
    if ($iteration->phase === 'critic') {
        echo "  Score: {$iteration->weighted_score}\n";
        echo "  Delta: {$iteration->improvement_delta}%\n";
    }
}
```

## Example with Case Data

```php
$input = [
    'document_type' => 'suppression_motion',
    'case_id' => 'uuid-of-legal-case',  // Loads from LegalCase model
];

$result = $agent->generate($input, $userId);
```

## Example with Mixed Mode (Case + Specific IDs)

```php
$input = [
    'document_type' => 'appeal_brief',
    'case_id' => 'uuid-of-legal-case',
    'evidence_ids' => ['ev-1', 'ev-2', 'ev-3'],  // Override with specific evidence
    'decision_ids' => ['dec-1', 'dec-2'],        // Include specific court decisions
    'additional_context' => 'Focus on constitutional violations',
];

$result = $agent->generate($input, $userId);
```

## Example with Standalone Context

```php
$input = [
    'document_type' => 'legal_opinion',
    'context' => 'Client wants to know about employment termination rights',
    'legal_question' => 'Can employer terminate without notice during probation?',
    'relevant_facts' => 'Employee is in 6-month probation period',
];

$result = $agent->generate($input, $userId);
```

## Error Handling

```php
try {
    $result = $agent->generate($input, $userId);

    if ($result->status === 'failed') {
        // Handle failure
        Log::error('Document generation failed', [
            'run_id' => $result->id,
            'stopped_reason' => $result->stopped_reason,
        ]);

        // Check iterations to see where it failed
        $lastIteration = $result->iterations()->latest('created_at')->first();

        return response()->json([
            'error' => 'Generation failed',
            'last_iteration' => $lastIteration->iteration_number,
        ], 500);
    }

    // Success
    return response()->json([
        'document' => $result->final_document,
        'score' => $result->final_score,
        'iterations' => $result->total_iterations,
    ]);

} catch (\Exception $e) {
    Log::error('Document generation exception', [
        'error' => $e->getMessage(),
    ]);

    return response()->json(['error' => 'Internal error'], 500);
}
```

## Accessing Iteration Details

```php
$result = $agent->generate($input, $userId);

// Get all worker iterations (document versions)
$workerIterations = $result->iterations()
    ->where('phase', 'worker')
    ->orderBy('iteration_number')
    ->get();

foreach ($workerIterations as $iter) {
    echo "Version {$iter->iteration_number}:\n";
    echo substr($iter->document_version, 0, 200) . "...\n\n";
}

// Get all critic iterations (evaluations)
$criticIterations = $result->iterations()
    ->where('phase', 'critic')
    ->orderBy('iteration_number')
    ->get();

foreach ($criticIterations as $iter) {
    echo "Evaluation {$iter->iteration_number}:\n";
    echo "  Weighted Score: {$iter->weighted_score}\n";
    echo "  Improvement: {$iter->improvement_delta}%\n";
    echo "  Feedback:\n";

    $feedback = $iter->critic_feedback['feedback'] ?? [];

    if (!empty($feedback['strengths'])) {
        echo "    Strengths: " . implode(', ', $feedback['strengths']) . "\n";
    }

    if (!empty($feedback['weaknesses'])) {
        echo "    Weaknesses: " . implode(', ', $feedback['weaknesses']) . "\n";
    }

    if (!empty($feedback['specific_improvements'])) {
        echo "    Improvements: " . implode(', ', $feedback['specific_improvements']) . "\n";
    }
}
```

## Using in a Controller

```php
namespace App\Http\Controllers;

use App\Agents\RecursiveDocumentWritingAgent;
use App\Http\Requests\GenerateDocumentRequest;
use Illuminate\Http\JsonResponse;

class DocumentGenerationController extends Controller
{
    public function __construct(
        protected RecursiveDocumentWritingAgent $agent
    ) {}

    public function generate(GenerateDocumentRequest $request): JsonResponse
    {
        $result = $this->agent->generate(
            $request->validated(),
            $request->user()->id
        );

        if ($result->status === 'failed') {
            return response()->json([
                'error' => 'Generation failed',
                'run_id' => $result->id,
            ], 500);
        }

        return response()->json([
            'run_id' => $result->id,
            'document' => $result->final_document,
            'score' => $result->final_score,
            'iterations' => $result->total_iterations,
            'stopped_reason' => $result->stopped_reason,
            'context_type' => $result->context->context_type,
        ]);
    }

    public function show(string $runId): JsonResponse
    {
        $run = DocumentGenerationRun::with(['iterations', 'context'])
            ->findOrFail($runId);

        return response()->json([
            'run' => $run,
            'iterations_count' => $run->iterations->count(),
            'worker_phases' => $run->iterations->where('phase', 'worker')->count(),
            'critic_phases' => $run->iterations->where('phase', 'critic')->count(),
        ]);
    }
}
```

## Configuration

The agent uses settings from `config/documents.php`:

```php
return [
    'max_iterations' => 10,           // Maximum iterations before stopping
    'convergence_threshold' => 5.0,   // Stop if improvement < 5%
    'min_acceptable_score' => 70.0,   // Minimum acceptable quality score

    'scoring_weights' => [
        'legal_rigor' => 0.40,         // 40% weight
        'persuasiveness' => 0.25,       // 25% weight
        'clarity' => 0.20,              // 20% weight
        'evidence_integration' => 0.10, // 10% weight
        'formatting' => 0.05,           // 5% weight
    ],
];
```

## Stopping Criteria

The agent stops when:

1. **Converged**: Improvement delta < 5% (configurable via `convergence_threshold`)
2. **Max Iterations**: Reached 10 iterations (configurable via `max_iterations`)
3. **Error**: Service failure during generation or critique

## Database Schema

### DocumentGenerationRun
- `id` (ULID): Primary key
- `document_type`: Type of document being generated
- `case_id`: Optional reference to LegalCase
- `status`: 'running', 'completed', or 'failed'
- `final_document`: Final generated document text
- `final_score`: Final weighted quality score (0-100)
- `total_iterations`: Number of iterations completed
- `stopped_reason`: 'converged', 'max_iterations', or 'error'
- `model_config`: JSON with AI model configuration
- `user_id`: User who initiated generation

### DocumentIteration
- `id` (ULID): Primary key
- `generation_run_id`: Foreign key to DocumentGenerationRun
- `iteration_number`: Iteration number (1-10)
- `phase`: 'worker' or 'critic'
- `document_version`: Generated document (worker phase only)
- `critic_feedback`: JSON with scores and feedback (critic phase only)
- `scores`: Individual dimension scores (critic phase only)
- `weighted_score`: Overall quality score (critic phase only)
- `improvement_delta`: Percentage improvement (critic phase only)
- `ai_model_used`: Model identifier
- `tokens_used`: Token usage metrics
- `cost_estimate`: Estimated cost

### DocumentContext
- `id` (ULID): Primary key
- `generation_run_id`: Foreign key to DocumentGenerationRun
- `context_type`: 'standalone', 'case_data', or 'mixed'
- `raw_input`: Original input JSON
- `assembled_context`: Processed context JSON
- `case_ids`: Array of case IDs used
- `evidence_ids`: Array of evidence IDs used
- `decision_ids`: Array of court decision IDs used
- `law_ids`: Array of law IDs used

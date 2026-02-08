# Migration Guide: Research Services

**Project:** AI Legal War Machine - Research Agent Refactoring
**Sprint:** Sprint 4 - Research Services Extraction
**Date:** November 7, 2025
**Status:** Production Ready

---

## Table of Contents

1. [Overview](#overview)
2. [Why Migrate?](#why-migrate)
3. [Architecture Comparison](#architecture-comparison)
4. [Quick Start](#quick-start)
5. [Detailed Migration Steps](#detailed-migration-steps)
6. [Common Patterns](#common-patterns)
7. [Parameter Mapping](#parameter-mapping)
8. [Result Format Differences](#result-format-differences)
9. [Testing Your Migration](#testing-your-migration)
10. [Troubleshooting](#troubleshooting)
11. [Rollback Plan](#rollback-plan)

---

## Overview

This guide helps you migrate from the deprecated `AutonomousResearchAgent` to the new `ResearchOrchestrator` service-oriented architecture.

**Migration Timeline:** No forced deadline - migrate at your convenience
**Breaking Changes:** None - full backward compatibility maintained
**Recommended Approach:** Migrate new code first, then gradually update existing code

---

## Why Migrate?

### Problems with AutonomousResearchAgent

❌ **Monolithic Design** - 1,146 lines with too many responsibilities
❌ **Hard to Test** - Difficult to test individual components in isolation
❌ **Difficult to Extend** - Adding new features requires modifying large class
❌ **Poor Separation** - Mixed concerns (question generation, search, evaluation)
❌ **High Coupling** - Components tightly coupled together

### Benefits of ResearchOrchestrator

✅ **Service-Oriented** - 5 focused services with single responsibilities
✅ **Easy to Test** - Each service tested independently (232 tests)
✅ **Simple to Extend** - Add features by extending specific services
✅ **Clear Separation** - Each service has one clear responsibility
✅ **Low Coupling** - Services communicate through clean interfaces
✅ **Better Performance** - Dependency injection via Laravel service container

---

## Architecture Comparison

### Old Architecture (Deprecated)

```
AutonomousResearchAgent (1,146 lines)
├── planNextStep()         → Question Generation
├── executeActions()       → Search Execution
├── evaluateIteration()    → Answer Evaluation
├── shouldContinue()       → Quality Assessment & Iteration Control
└── Mixed concerns throughout
```

**Usage:**
```php
$agent = new AutonomousResearchAgent();
$run = $agent->startRun($objective, $context, $constraints);
$result = $agent->executeRun($run);
```

### New Architecture (Recommended)

```
ResearchOrchestrator (282 lines)
├── QuestionGeneratorService (570 lines)
├── SearchExecutorService (412 lines)
├── AnswerEvaluatorService (538 lines)
├── QualityAssessorService (430 lines)
└── IterationControllerService (155 lines)
```

**Usage:**
```php
$orchestrator = app(ResearchOrchestrator::class);
$result = $orchestrator->research($query, $options);
```

---

## Quick Start

### Before (Deprecated)

```php
use App\Agents\AutonomousResearchAgent;

// Old way - deprecated
$agent = new AutonomousResearchAgent();

$run = $agent->startRun(
    objective: 'Research Croatian labor law termination notice periods',
    context: ['case_id' => 123],
    constraints: [
        'max_iterations' => 5,
        'token_budget' => 50000,
        'time_budget' => 300,
    ]
);

$result = $agent->executeRun($run);

// Access results
$answer = $run->final_answer;
$sources = $run->sources;
```

### After (Recommended)

```php
use App\Services\ResearchOrchestrator;

// New way - recommended
$orchestrator = app(ResearchOrchestrator::class);

$result = $orchestrator->research(
    query: 'Research Croatian labor law termination notice periods',
    options: [
        'max_iterations' => 5,
        'quality_threshold' => 85,
        'token_budget' => 50000,
        'time_budget' => 300,
        'context' => ['case_id' => 123],
    ]
);

// Access results
$answer = $result['answer'];
$sources = $result['sources'];
$quality = $result['quality_score'];
$iterations = $result['iterations'];
```

**Key Differences:**
- Single `research()` method instead of `startRun()` + `executeRun()`
- Options passed as single array instead of separate parameters
- Results returned as array instead of `AgentRun` object
- Includes quality score and iteration count in results

---

## Detailed Migration Steps

### Step 1: Understand the New Architecture

The `ResearchOrchestrator` coordinates 5 specialized services:

1. **QuestionGeneratorService** - Generates and refines research questions
2. **SearchExecutorService** - Executes searches across law, decision, and case databases
3. **AnswerEvaluatorService** - Evaluates answer completeness and identifies gaps
4. **QualityAssessorService** - Assesses overall research quality (0-100 score)
5. **IterationControllerService** - Controls iteration limits and resource usage

**Research Flow:**
```
1. Generate Questions (QuestionGenerator)
   ↓
2. Execute Searches (SearchExecutor)
   ↓
3. Evaluate Answer (AnswerEvaluator)
   ↓
4. Assess Quality (QualityAssessor)
   ↓
5. Check Limits (IterationController)
   ↓
   If quality < threshold AND budget remaining:
   → Refine Questions (back to step 1)

   Else:
   → Return Final Answer
```

### Step 2: Convert startRun/executeRun to research()

**Old Pattern:**
```php
$agent = new AutonomousResearchAgent();
$run = $agent->startRun($objective, $context, $constraints);
$result = $agent->executeRun($run);
```

**New Pattern:**
```php
$orchestrator = app(ResearchOrchestrator::class);
$result = $orchestrator->research($query, $options);
```

**Changes:**
- `startRun()` + `executeRun()` → Single `research()` call
- `$objective` → `$query` (same meaning, clearer name)
- `$context` → Merged into `$options['context']`
- `$constraints` → Merged into `$options` (flattened)

### Step 3: Adapt Constraint Parameters

**Old Constraints:**
```php
$constraints = [
    'max_iterations' => 5,
    'token_budget' => 50000,
    'time_budget' => 300,
];
```

**New Options:**
```php
$options = [
    'max_iterations' => 5,        // Same key
    'token_budget' => 50000,      // Same key
    'time_budget' => 300,         // Same key
    'quality_threshold' => 85,    // NEW: Quality threshold (default 85)
    'context' => ['case_id' => 123], // Context moved here
];
```

**New Parameters:**
- `quality_threshold` (default: 85) - Research stops when quality ≥ threshold
- All other constraints work the same way

### Step 4: Handle Result Format Differences

**Old Result Format (AgentRun object):**
```php
$run = $agent->executeRun($run);

$answer = $run->final_answer;              // string
$sources = $run->sources;                  // array
$status = $run->status;                    // string ('completed', 'failed')
$iterationCount = $run->iterations()->count(); // int
```

**New Result Format (array):**
```php
$result = $orchestrator->research($query, $options);

$answer = $result['answer'];               // string
$sources = $result['sources'];             // array
$quality = $result['quality_score'];       // int (0-100)
$iterations = $result['iterations'];       // int
$assessment = $result['assessment'];       // array (quality breakdown)
$query = $result['query'];                 // string (original query)
```

**Mapping:**
| Old (`AgentRun`)          | New (`$result` array)     |
|---------------------------|---------------------------|
| `$run->final_answer`      | `$result['answer']`       |
| `$run->sources`           | `$result['sources']`      |
| `$run->iterations()->count()` | `$result['iterations']` |
| N/A                       | `$result['quality_score']` |
| N/A                       | `$result['assessment']`   |

---

## Common Patterns

### Pattern 1: Basic Research

**Before:**
```php
$agent = new AutonomousResearchAgent();
$run = $agent->startRun('Research labor law termination periods');
$result = $agent->executeRun($run);

echo $run->final_answer;
```

**After:**
```php
$orchestrator = app(ResearchOrchestrator::class);
$result = $orchestrator->research('Research labor law termination periods');

echo $result['answer'];
```

### Pattern 2: Research with Budget Limits

**Before:**
```php
$agent = new AutonomousResearchAgent();
$run = $agent->startRun(
    objective: 'Research home search warrant proportionality',
    context: [],
    constraints: [
        'max_iterations' => 3,
        'token_budget' => 20000,
        'time_budget' => 120,
    ]
);
$result = $agent->executeRun($run);
```

**After:**
```php
$orchestrator = app(ResearchOrchestrator::class);
$result = $orchestrator->research(
    query: 'Research home search warrant proportionality',
    options: [
        'max_iterations' => 3,
        'token_budget' => 20000,
        'time_budget' => 120,
    ]
);
```

### Pattern 3: Research with Quality Threshold

**Before:**
```php
// Quality threshold was hardcoded to 85
$agent = new AutonomousResearchAgent();
$run = $agent->startRun('Research prosecutorial misconduct standards');
$result = $agent->executeRun($run);
```

**After:**
```php
// Now configurable!
$orchestrator = app(ResearchOrchestrator::class);
$result = $orchestrator->research(
    query: 'Research prosecutorial misconduct standards',
    options: [
        'quality_threshold' => 90, // Higher threshold for important research
    ]
);

if ($result['quality_score'] >= 90) {
    echo "High-quality research achieved!\n";
}
```

### Pattern 4: Research with Context

**Before:**
```php
$agent = new AutonomousResearchAgent();
$run = $agent->startRun(
    objective: 'Research evidence admissibility',
    context: [
        'case_id' => 123,
        'defendant_name' => 'John Doe',
        'charges' => ['drug_possession'],
    ]
);
$result = $agent->executeRun($run);
```

**After:**
```php
$orchestrator = app(ResearchOrchestrator::class);
$result = $orchestrator->research(
    query: 'Research evidence admissibility',
    options: [
        'context' => [
            'case_id' => 123,
            'defendant_name' => 'John Doe',
            'charges' => ['drug_possession'],
        ],
    ]
);
```

### Pattern 5: Resume Failed Research

**Before:**
```php
$agent = new AutonomousResearchAgent();
$run = AgentRun::find($runId);

if ($run->status === 'failed') {
    $run = $agent->resumeRun($run);
    $result = $agent->executeRun($run);
}
```

**After:**
```php
// ResearchOrchestrator doesn't persist runs by default
// If you need persistence, wrap in your own service layer

$orchestrator = app(ResearchOrchestrator::class);

try {
    $result = $orchestrator->research($query, $options);
} catch (\Exception $e) {
    Log::error('Research failed', ['error' => $e->getMessage()]);

    // Retry with reduced budget
    $result = $orchestrator->research($query, [
        'max_iterations' => 2,
        'token_budget' => 10000,
    ]);
}
```

**Note:** The new architecture doesn't automatically persist runs to database. If you need persistence, create a wrapper service:

```php
class PersistentResearchService
{
    public function __construct(
        private ResearchOrchestrator $orchestrator
    ) {}

    public function research(string $query, array $options = []): array
    {
        // Create run record
        $run = ResearchRun::create([
            'query' => $query,
            'options' => $options,
            'status' => 'running',
        ]);

        try {
            // Execute research
            $result = $this->orchestrator->research($query, $options);

            // Update run record
            $run->update([
                'status' => 'completed',
                'result' => $result,
            ]);

            return $result;
        } catch (\Exception $e) {
            $run->update(['status' => 'failed', 'error' => $e->getMessage()]);
            throw $e;
        }
    }
}
```

---

## Parameter Mapping

### Complete Parameter Reference

| Old Parameter (AutonomousResearchAgent) | New Parameter (ResearchOrchestrator) | Notes |
|-----------------------------------------|--------------------------------------|-------|
| `$objective` (string, required)         | `$query` (string, required)          | Renamed for clarity |
| `$context` (array, optional)            | `$options['context']` (array)        | Moved into options |
| `$constraints['max_iterations']`        | `$options['max_iterations']`         | Same key, default: 5 |
| `$constraints['token_budget']`          | `$options['token_budget']`           | Same key, default: 100000 |
| `$constraints['time_budget']`           | `$options['time_budget']`            | Same key, default: 600 |
| N/A                                     | `$options['quality_threshold']`      | NEW: default 85 |

### Default Values

**Old Defaults:**
```php
// Hardcoded in AutonomousResearchAgent
max_iterations: 5
token_budget: 100000
time_budget: 600 (10 minutes)
quality_threshold: 85 (hardcoded)
```

**New Defaults:**
```php
// Defined in ResearchOrchestrator
max_iterations: 5
token_budget: 100000
time_budget: 600 (10 minutes)
quality_threshold: 85 (now configurable)
```

---

## Result Format Differences

### Old Format: AgentRun Object

```php
$run = $agent->executeRun($run);

// Properties
$run->id                    // int
$run->objective             // string
$run->final_answer          // string|null
$run->status                // string ('running', 'completed', 'failed')
$run->sources               // array
$run->created_at            // Carbon
$run->updated_at            // Carbon

// Relationships
$run->iterations()          // HasMany
$run->iterations()->count() // int
```

### New Format: Array

```php
$result = $orchestrator->research($query, $options);

// Array keys
$result['query']            // string (original query)
$result['answer']           // string (final synthesized answer)
$result['quality_score']    // int (0-100)
$result['iterations']       // int (number of iterations performed)
$result['sources']          // array (search results used)
$result['assessment']       // array (detailed quality breakdown)

// Quality assessment structure
$result['assessment'] = [
    'overall_score' => 87,           // int (0-100)
    'dimensions' => [
        'completeness' => 85,        // int (0-100)
        'citations' => 90,           // int (0-100)
        'legal_accuracy' => 88,      // int (0-100)
        'clarity' => 86,             // int (0-100)
        'relevance' => 89,           // int (0-100)
    ],
    'strengths' => [
        'Comprehensive coverage of ZKP provisions',
        'Strong citation of relevant case law',
    ],
    'improvements' => [
        'Could include more recent decisions',
    ],
    'is_complete' => true,           // bool (score >= threshold)
];
```

### Accessing Results

**Old:**
```php
$answer = $run->final_answer;
$sources = $run->sources;
$iterationCount = $run->iterations()->count();

// Quality score not directly available
// Had to manually calculate from evaluation results
```

**New:**
```php
$answer = $result['answer'];
$sources = $result['sources'];
$iterations = $result['iterations'];
$quality = $result['quality_score'];

// Quality breakdown available
$completeness = $result['assessment']['dimensions']['completeness'];
$isComplete = $result['assessment']['is_complete'];
```

---

## Testing Your Migration

### Unit Tests

Test that your migrated code produces the same results:

```php
use Tests\TestCase;
use App\Services\ResearchOrchestrator;

class ResearchMigrationTest extends TestCase
{
    public function test_research_produces_valid_results()
    {
        $orchestrator = app(ResearchOrchestrator::class);

        $result = $orchestrator->research(
            query: 'Research Croatian labor law termination periods',
            options: [
                'max_iterations' => 2,
                'token_budget' => 10000,
            ]
        );

        // Assert result structure
        $this->assertIsArray($result);
        $this->assertArrayHasKey('answer', $result);
        $this->assertArrayHasKey('quality_score', $result);
        $this->assertArrayHasKey('sources', $result);

        // Assert result content
        $this->assertNotEmpty($result['answer']);
        $this->assertIsInt($result['quality_score']);
        $this->assertGreaterThanOrEqual(0, $result['quality_score']);
        $this->assertLessThanOrEqual(100, $result['quality_score']);
    }
}
```

### Integration Tests

Test that migrated code integrates correctly with your application:

```php
public function test_research_integration_with_case()
{
    $case = Case::factory()->create(['id' => 123]);

    $orchestrator = app(ResearchOrchestrator::class);

    $result = $orchestrator->research(
        query: 'Research evidence admissibility standards',
        options: [
            'context' => ['case_id' => $case->id],
            'max_iterations' => 2,
        ]
    );

    // Verify research completed
    $this->assertNotEmpty($result['answer']);
    $this->assertGreaterThan(0, $result['quality_score']);

    // Store results in case
    $case->update([
        'research_results' => $result,
    ]);

    $this->assertDatabaseHas('cases', [
        'id' => $case->id,
    ]);
}
```

### Feature Tests

Test migrated endpoints:

```php
public function test_research_endpoint_works()
{
    $response = $this->postJson('/api/research', [
        'query' => 'Research prosecutorial misconduct standards',
        'options' => [
            'max_iterations' => 3,
            'quality_threshold' => 85,
        ],
    ]);

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'query',
        'answer',
        'quality_score',
        'iterations',
        'sources',
        'assessment',
    ]);
}
```

---

## Troubleshooting

### Issue 1: "ResearchOrchestrator not found"

**Error:**
```
Class 'App\Services\ResearchOrchestrator' not found
```

**Solution:**
Ensure `ResearchServiceProvider` is registered in `bootstrap/providers.php`:

```php
return [
    // ... other providers
    App\Providers\ResearchServiceProvider::class,
];
```

Then clear config cache:
```bash
php artisan config:clear
php artisan cache:clear
```

### Issue 2: "Dependency resolution failed"

**Error:**
```
Target [App\Contracts\Research\QuestionGeneratorInterface] is not instantiable.
```

**Solution:**
Check that all 5 research services are registered in `ResearchServiceProvider::register()`:
- `QuestionGeneratorInterface`
- `SearchExecutorInterface`
- `AnswerEvaluatorInterface`
- `QualityAssessorInterface`
- `IterationControllerInterface`

### Issue 3: Different results than AutonomousResearchAgent

**Symptom:** Results differ between old and new implementations

**Explanation:** This is expected! The new architecture:
- Uses refined prompts
- Has better question generation
- Improved evaluation logic
- Better iteration control

**Solution:** The new results should be **better quality**. If quality is worse:
1. Check quality_threshold setting (default: 85)
2. Increase max_iterations if needed
3. Increase token_budget if research is getting cut off

### Issue 4: Research takes too long

**Symptom:** Research doesn't complete within expected timeframe

**Solution:**
Reduce budgets:
```php
$result = $orchestrator->research($query, [
    'max_iterations' => 2,      // Reduce iterations
    'token_budget' => 20000,    // Reduce token budget
    'time_budget' => 120,       // Reduce time budget (2 minutes)
]);
```

Or increase quality threshold to finish sooner:
```php
$result = $orchestrator->research($query, [
    'quality_threshold' => 75,  // Lower threshold = faster completion
]);
```

### Issue 5: Tests failing after migration

**Symptom:** Tests that worked with AutonomousResearchAgent now fail

**Solution:**
1. Update assertions to match new result format (array instead of AgentRun)
2. Update test mocks if using service mocking
3. Check that all required services are registered in test environment

**Example Fix:**
```php
// Before
$this->assertEquals('completed', $run->status);
$this->assertNotNull($run->final_answer);

// After
$this->assertIsArray($result);
$this->assertNotEmpty($result['answer']);
$this->assertGreaterThan(0, $result['quality_score']);
```

---

## Rollback Plan

### If You Need to Revert

The `AutonomousResearchAgent` is fully preserved and functional. To rollback:

1. **Change code back to old pattern:**
   ```php
   // Change from:
   $orchestrator = app(ResearchOrchestrator::class);
   $result = $orchestrator->research($query, $options);

   // Back to:
   $agent = new AutonomousResearchAgent();
   $run = $agent->startRun($objective, $context, $constraints);
   $result = $agent->executeRun($run);
   ```

2. **No data migration needed** - Both use same underlying databases

3. **Deprecation warnings** - You'll see warnings in logs, but functionality works

### Rollback Checklist

- [ ] Revert code changes (git revert or manual changes)
- [ ] Update tests to use AgentRun assertions
- [ ] Clear application cache: `php artisan cache:clear`
- [ ] Run tests to verify: `composer test`
- [ ] Monitor logs for any unexpected errors

### When NOT to Rollback

Don't rollback if:
- ✅ New code is working correctly
- ✅ Tests are passing
- ✅ Results are better quality than before
- ✅ You just see deprecation warnings (they're informational only)

The deprecation warnings are meant to guide future migrations, not force immediate changes.

---

## Migration Checklist

Use this checklist to track your migration progress:

### Pre-Migration
- [ ] Read this migration guide completely
- [ ] Review Sprint 4 Completion Report
- [ ] Ensure all 232 tests are passing
- [ ] Backup current code (git commit)

### Code Migration
- [ ] Identify all uses of `AutonomousResearchAgent`
- [ ] Replace `startRun()` + `executeRun()` with `research()`
- [ ] Convert `$constraints` to `$options` format
- [ ] Update context passing: `$context` → `$options['context']`
- [ ] Update result access: `$run->final_answer` → `$result['answer']`

### Testing
- [ ] Update unit tests to use new result format
- [ ] Update integration tests
- [ ] Update feature tests for API endpoints
- [ ] Run full test suite: `composer test`
- [ ] Verify all tests passing

### Deployment
- [ ] Test in development environment
- [ ] Test in staging environment
- [ ] Monitor deprecation warnings in logs
- [ ] Deploy to production
- [ ] Monitor for errors

### Post-Migration
- [ ] Verify research quality is maintained or improved
- [ ] Monitor performance metrics
- [ ] Update team documentation
- [ ] Train team on new architecture

---

## Additional Resources

- [SPRINT_4_COMPLETION_REPORT.md](./SPRINT_4_COMPLETION_REPORT.md) - Detailed sprint report
- [README.md](../README.md) - Research Services Architecture section
- [CLAUDE.md](../CLAUDE.md) - Development patterns
- [API_DOCUMENTATION.md](./API_DOCUMENTATION.md) - API reference

---

## Support

If you encounter issues during migration:

1. **Check deprecation warnings** - They include migration instructions
2. **Review troubleshooting section** - Common issues documented above
3. **Check test output** - Tests provide detailed error messages
4. **Review Sprint 4 report** - Architecture details and examples

---

## Conclusion

The migration from `AutonomousResearchAgent` to `ResearchOrchestrator` provides:

✅ **Better Architecture** - Service-oriented, testable, maintainable
✅ **Better Tests** - 232 tests with comprehensive coverage
✅ **Better Quality** - Improved prompts and evaluation logic
✅ **Better Performance** - Efficient dependency injection
✅ **Better Developer Experience** - Clear interfaces and documentation

**Migration is straightforward:**
1. Replace `startRun()` + `executeRun()` with single `research()` call
2. Update parameter format (constraints → options)
3. Update result access (object → array)
4. Test thoroughly
5. Deploy with confidence

The old implementation remains fully functional, giving you time to migrate at your own pace. No breaking changes, no forced migrations, no data loss.

**Happy migrating! 🚀**

---

**Guide Version:** 1.0
**Last Updated:** November 7, 2025
**Sprint:** Sprint 4 Complete ✅

# AutonomousResearchAgent Migration Guide

## Overview

The `AutonomousResearchAgent` has been deprecated in favor of `ResearchService`. This guide helps you migrate to the new API.

## Quick Migration

### Before (Deprecated)

```php
use App\Agents\AutonomousResearchAgent;
use App\Jobs\ExecuteAgentResearch;

// Synchronous execution
$agent = app(AutonomousResearchAgent::class);
$run = $agent->startRun('Research objective', $context, $constraints);
$run = $agent->executeRun($run);

// Async execution
$run = $agent->startRun('Research objective', $context, $constraints);
ExecuteAgentResearch::dispatch($run);
```

### After (New)

```php
use App\Services\ResearchService;

// Synchronous execution
$service = app(ResearchService::class);
$run = $service->research('Research objective', [
    'max_iterations' => 5,
    'quality_threshold' => 85,
]);

// Async execution
$run = $service->researchAsync('Research objective', [
    'max_iterations' => 5,
    'quality_threshold' => 85,
]);
// Job is automatically dispatched
```

## API Comparison

| Old API | New API | Notes |
|---------|---------|-------|
| `startRun()` + `executeRun()` | `research()` | Single call, simpler |
| `resumeRun()` | N/A | Not supported in new API |
| Returns `AgentRun` | Returns `AgentRun` | Same model |
| Fires events | Fires events | Same events (ResearchCompleted, ResearchFailed) |
| `ExecuteAgentResearch` job | `ExecuteResearchJob` | Internal, use `researchAsync()` |
| `RunAutonomousResearchJob` | `ExecuteResearchJob` | Internal, use `researchAsync()` |

## Option Mapping

| Old Constraints | New Options |
|-----------------|-------------|
| `max_iterations` | `max_iterations` |
| `threshold` (0-1) | `quality_threshold` (0-100) |
| `token_budget` | `token_budget` |
| `time_limit_seconds` | `time_limit_seconds` |
| `context` array | Passed in options array |

### Threshold Conversion

The old API used a 0-1 scale for threshold, while the new API uses 0-100:

```php
// Old: threshold 0.75
// New: quality_threshold 75

$newThreshold = (int) ($oldThreshold * 100);
```

## Architecture Changes

### Old Architecture

```
AgentController
    └── AutonomousResearchAgent
            ├── startRun() - creates AgentRun
            ├── executeRun() - iterates, updates AgentRun
            └── resumeRun() - checkpoint/resume
```

### New Architecture

```
AgentController
    └── ResearchService
            ├── research() - creates AgentRun, executes, returns
            └── researchAsync() - creates AgentRun, dispatches job
                    └── ExecuteResearchJob
                            └── ResearchOrchestrator
                                    └── Stateless research execution
```

## Benefits of Migration

1. **Simpler API**: Single method call instead of start+execute
2. **Cleaner Architecture**: Service-oriented design
3. **Better Testability**: Easy to mock `ResearchService`
4. **Stateless Core**: `ResearchOrchestrator` is stateless, `ResearchService` handles persistence

## Deprecation Timeline

- **v1.5.0**: `AutonomousResearchAgent`, `ExecuteAgentResearch`, `RunAutonomousResearchJob` marked as deprecated
- **v2.0.0**: Deprecated code will be removed

## Support

If you encounter issues during migration, please:
1. Check that `ResearchService` is properly registered in `AppServiceProvider`
2. Ensure `ResearchOrchestrator` dependencies are available
3. Review test examples in `tests/Unit/Services/ResearchServiceTest.php`

# Research Migration Critical Review

**Date**: 2026-01-11
**Reviewer**: Code Review Agent
**Subject**: AutonomousResearchAgent → ResearchOrchestrator Migration

---

## Executive Summary

**CRITICAL FINDING: ResearchOrchestrator is NOT a replacement for AutonomousResearchAgent.**

The new `ResearchOrchestrator` is a dramatically simplified "stub" implementation that lacks all the core intelligence of the original agent. The migration as implemented represents a **major feature regression**.

---

## Architecture Comparison

### AutonomousResearchAgent (~1760 lines)
A fully autonomous, LLM-powered research agent with:

| Feature | Description |
|---------|-------------|
| **LLM Planning** | GPT-4o-mini generates research strategies |
| **16 Search Tools** | law_*, decision_*, case_*, graph_query, web_fetch, note_save |
| **Plan→Act→Evaluate Loop** | True autonomous iteration with self-assessment |
| **Memory Reuse** | Loads insights from prior runs on similar topics |
| **Checkpoint/Resume** | Can pause and resume long-running research |
| **Insight Extraction** | LLM extracts legal insights with citations |
| **Security** | Prompt injection protection, input sanitization |
| **Events** | ResearchIterationCompleted, NewInsightDiscovered |
| **Cost Tracking** | Per-call token/cost accounting |

### ResearchOrchestrator (~280 lines)
A simplified coordinator with:

| Feature | Description |
|---------|-------------|
| **No LLM Planning** | Hardcoded search actions |
| **3 Search Tools** | law_vector_search, decision_vector_search, case_vector_search only |
| **Fixed Loop** | No adaptive behavior |
| **No Memory** | Doesn't use past insights |
| **No Checkpointing** | No resume capability |
| **Stub Quality Assessment** | `quality = 50 + (results * 10) + (iteration * 5)` |
| **Stub Answer Synthesis** | Just counts results, no LLM synthesis |
| **Stub Token Estimation** | Hardcoded 500 tokens per search |

---

## Critical Bugs Found

### Bug #1: Duplicate AgentRun Records (CRITICAL)

**Location**: `ResearchService::researchAsync()` + `ExecuteResearchJob::handle()`

**Problem**: The async flow creates TWO AgentRun records per research request.

```php
// ResearchService::researchAsync() - Creates run #1
$run = AgentRun::create([
    'objective' => $objective,
    'status' => 'pending',
    ...
]);
ExecuteResearchJob::dispatch($run->id);

// ExecuteResearchJob::handle()
$service->research($run->objective, $run->context ?? []);
//       ↑ This calls research() which creates run #2!

// ResearchService::research() - Creates run #2
$run = AgentRun::create([
    'objective' => $objective,
    'status' => 'running',
    ...
]);
```

**Impact**: Database pollution, incorrect reporting, orphaned records.

**Fix Required**: ExecuteResearchJob should either:
1. Pass the existing AgentRun to ResearchService, or
2. ResearchService needs a method that operates on an existing run

### Bug #2: Missing agent_name Field

**Location**: `ResearchService::research()` line 49

**Problem**: AgentRun is created without `agent_name` field.

```php
$run = AgentRun::create([
    'objective' => $objective,
    'status' => 'running',
    // Missing: 'agent_name' => 'research_service',
    ...
]);
```

**Impact**: Queries that filter by agent_name will miss these records.

### Bug #3: ResearchOrchestrator Always Returns success: true

**Location**: `ResearchOrchestrator::research()` line 123

```php
return [
    'success' => true,  // Always true!
    'answer' => $answer,
    ...
];
```

**Problem**: Even if quality is 0 and all searches fail, success is true.

**Impact**: ResearchService incorrectly marks failed research as "completed".

---

## Feature Regression Analysis

| Feature | AutonomousResearchAgent | ResearchOrchestrator | Gap |
|---------|------------------------|---------------------|-----|
| LLM Planning | ✅ GPT-4o-mini | ❌ Hardcoded | Critical |
| Tool Count | 16 tools | 3 tools | Severe |
| Insight Extraction | ✅ LLM-powered | ❌ None | Critical |
| Memory Reuse | ✅ Past insights | ❌ None | Moderate |
| Checkpoint/Resume | ✅ Full support | ❌ None | Moderate |
| Answer Synthesis | ✅ LLM-powered | ❌ Template string | Critical |
| Quality Assessment | ✅ LLM evaluation | ❌ Fixed formula | Critical |
| Security | ✅ Prompt injection protection | ❌ None | Moderate |
| Events | ✅ 4 event types | ✅ 2 event types | Minor |

---

## What ResearchOrchestrator Actually Does

Looking at the code, ResearchOrchestrator:

1. **generateSearchActions()**: Returns hardcoded array of 2-3 searches
2. **estimateQuality()**: Returns `50 + (result_count * 10) + (iteration * 5)`
3. **synthesizeAnswer()**: Returns template string like "Research on '...' completed successfully. Found N sources..."
4. **estimateTokensUsed()**: Returns `count(results) * 500`

This is essentially a **stub/mock implementation** suitable for testing, not production use.

---

## Recommendations

### Immediate (Critical Fixes)

1. **Fix duplicate AgentRun bug** in ResearchService/ExecuteResearchJob
2. **Add agent_name field** to AgentRun creation
3. **Fix success detection** in ResearchOrchestrator

### Short-term (Feature Parity)

4. **Integrate LLM planning** into ResearchOrchestrator via QuestionGeneratorService
5. **Add real answer synthesis** via OpenAIService
6. **Add insight extraction** via existing LLM infrastructure
7. **Expand tool set** to match AutonomousResearchAgent

### Long-term (Architecture)

8. **Consider keeping AutonomousResearchAgent** - it's far more capable
9. **Position ResearchOrchestrator** as lightweight alternative for simple queries
10. **Add checkpoint support** to ResearchService for long-running tasks

---

## Conclusion

The migration deprecates a sophisticated 1760-line LLM-powered agent in favor of a 280-line stub that provides no actual AI reasoning. **This is not a valid migration but a feature removal.**

**Recommendation**: Either:
1. **Revert deprecation** of AutonomousResearchAgent until ResearchOrchestrator reaches feature parity, or
2. **Acknowledge this is intentional simplification** and update documentation accordingly

The current state creates risk that users migrating to ResearchService will experience dramatically degraded research quality.

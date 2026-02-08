# Queue Breadcrumbs Decision Record

**Date:** 2025-12-18
**Task:** S2-QUEUE-00
**Status:** Decided

---

## Context

The TDD queue (`test-results/tdd-test-queue.json`) tracks components for TDD work. The question: how should we represent breadcrumbs (iteration history, notes, results)?

## Current Schema

```json
{
  "components": [
    {
      "id": "unit:CaseGraphSyncServiceTest",
      "sprint": 1,
      "domain": "graph_and_neo4j",
      "type": "unit",
      "test_class": "CaseGraphSyncServiceTest",
      "test_path": "tests/Unit/Services/Graph/CaseGraphSyncServiceTest.php",
      "status": "todo|in_progress|done",
      "iterations": 0,
      "related_tests": ["..."]
    }
  ]
}
```

**Key observation:** `iterations` is a **number** (count only).

## Options Considered

### Option A: Keep queue strictly structured (CHOSEN)

- `iterations` remains a number (increment counter)
- Narrative breadcrumbs go to session notes files (`.claude/session-state.md`)
- Queue stays lean and fast to parse

**Pros:**
- Simple schema, easy validation
- Smaller file size (queue is already 259KB)
- Backwards compatible with existing autoloop
- Session notes already exist for narrative

**Cons:**
- No iteration history in queue itself
- Must cross-reference session notes for context

### Option B: iterations as array of objects

```json
"iterations": [
  {"timestamp": "...", "result": "pass|fail", "slices": 2, "note": "..."}
]
```

**Pros:**
- Full history in one place
- Self-contained breadcrumbs

**Cons:**
- Schema change breaks existing consumers
- File bloat (could 10x the file size)
- Autoloop would need rewrite
- Overkill for simple iteration tracking

## Decision: Option A

**Rationale:**
1. **Autoloop simplicity**: The `/tdd-multi-component-autoloop` is used manually by humans who want fast, simple updates. Adding structured iteration objects adds complexity without clear benefit.

2. **File size**: At 259KB, the queue is already large. Adding iteration arrays would bloat it significantly.

3. **Separation of concerns**:
   - Queue = work list (what to do, current status)
   - Session notes = narrative (what happened, why)

4. **Digest compatibility**: Digest generator needs `id`, `status`, `iterations` count. It doesn't need iteration history.

5. **Validator simplicity**: Validating `iterations: number` is trivial. Validating an array of objects requires more complex schema rules.

## Implementation Notes

### Queue consumers can rely on:
- `components[].id` - stable string identifier
- `components[].status` - enum: "todo", "in_progress", "done"
- `components[].iterations` - number >= 0
- `components[].sprint` - number 1-6
- `components[].type` - enum: unit, feature, feature_livewire, integration, browser_dusk, performance, snapshot

### Narrative breadcrumbs go to:
- `.claude/session-state.md` - append-only session handoff notes

### Do NOT modify:
- `stats`, `visual_groupings`, `sprint_guide`, `meta` sections

## Compatibility Checklist

- [x] Existing autoloop: Compatible (no change needed)
- [x] Digest generator: Can access id, status, iterations as before
- [x] Validator (S2-QUEUE-03): Will validate iterations as number
- [x] Sprint 3 autoloop updates: No schema change required

---

# Breadcrumb Convention (S2-QUEUE-02)

This section defines the standard for tracking TDD progress across sessions.

## 1. Queue Field Change Rules

### Fields You MAY Modify (in `components[]` only)

| Field | Type | When to Change |
|-------|------|----------------|
| `status` | enum | On work state transitions |
| `iterations` | number | After completing TDD slices |

### Fields You MUST NOT Modify

| Field | Reason |
|-------|--------|
| `id` | Stable identifier, used by consumers |
| `sprint` | Assignment is fixed |
| `domain` | Classification is fixed |
| `type` | Test type is fixed |
| `test_class` | Derived from file |
| `test_path` | Derived from file |
| `related_tests` | Managed separately |

### Sections You MUST NOT Touch

- `stats` - Computed, not manually editable
- `visual_groupings` - Reference structure
- `sprint_guide` - Static documentation
- `meta` - Configuration notes

## 2. Update Triggers

### Manual Updates (Human-Driven)

Use manual updates when working outside the autoloop:

```bash
# After completing a TDD slice manually
jq '.components |= map(if .id == "unit:FooTest" then .status = "done" | .iterations += 1 else . end)' \
  test-results/tdd-test-queue.json > tmp.json && mv tmp.json test-results/tdd-test-queue.json
```

### Autoloop Updates (`/tdd-multi-component-autoloop`)

The autoloop command handles updates automatically:

1. **Start of work**: Sets `status` to `"in_progress"`
2. **After each slice**: Increments `iterations` by 1
3. **End of work**: Sets `status` to `"done"` (or keeps `"in_progress"` if more work needed)

**Important:** The autoloop is used **manually by human operators**. It is not automated.

## 3. Status Transitions

```
┌──────────┐     start work      ┌─────────────┐     work complete     ┌──────────┐
│   todo   │ ──────────────────► │ in_progress │ ────────────────────► │   done   │
└──────────┘                     └─────────────┘                       └──────────┘
                                       │
                                       │ more work needed
                                       └───────────────────┐
                                                           ▼
                                                    (stays in_progress)
```

### Valid Transitions

| From | To | Trigger |
|------|----|---------|
| `todo` | `in_progress` | Work begins on component |
| `in_progress` | `done` | TDD coverage is satisfactory |
| `in_progress` | `in_progress` | More TDD slices completed, not yet done |

### Invalid Transitions

- `done` → `todo` (use `in_progress` if reopening)
- `todo` → `done` (must go through `in_progress`)

## 4. Narrative Recording

### Where Narrative Goes

| File | Purpose | Format |
|------|---------|--------|
| `.claude/session-state.md` | Handoff notes between sessions | Append-only markdown entries |
| `test-results/context/active-component.md` | Current focus (auto-generated) | Markdown (regenerated each session) |

### Session State Entry Format

```markdown
## YYYY-MM-DD HH:MM
- Sprint: S<N>
- Components touched: [<queue-ids>]
- Last test command: ./scripts/run-focused-tests.sh <TestClass>
- Status: <what's true now>
- Blockers: <what's stuck, or "none">
- Next steps:
  - <bullet 1>
  - <bullet 2>
- Autoloop notes: <brief, or "n/a">
```

### When to Write Session Notes

1. **End of every session** - Summarize what was done
2. **Before handoff** - Ensure next agent has context
3. **After significant progress** - Document milestones
4. **When blocked** - Record blockers for visibility

---

## 5. Worked Example

### Scenario

Agent works on `unit:VectorStoreServiceTest` using TDD. Completes 2 slices, component not yet done.

### Before: Queue State

```json
{
  "id": "unit:VectorStoreServiceTest",
  "sprint": 1,
  "domain": "vector_stores",
  "type": "unit",
  "test_class": "VectorStoreServiceTest",
  "test_path": "tests/Unit/Services/VectorStoreServiceTest.php",
  "status": "todo",
  "iterations": 0,
  "related_tests": ["integration:VectorStoreIntegrationTest"]
}
```

### After: Queue State

```json
{
  "id": "unit:VectorStoreServiceTest",
  "sprint": 1,
  "domain": "vector_stores",
  "type": "unit",
  "test_class": "VectorStoreServiceTest",
  "test_path": "tests/Unit/Services/VectorStoreServiceTest.php",
  "status": "in_progress",
  "iterations": 2,
  "related_tests": ["integration:VectorStoreIntegrationTest"]
}
```

**Changes:**
- `status`: `"todo"` → `"in_progress"`
- `iterations`: `0` → `2`

### Matching Session State Entry

```markdown
## 2025-12-18 14:30
- Sprint: S1
- Components touched: [unit:VectorStoreServiceTest]
- Last test command: ./scripts/run-focused-tests.sh VectorStoreServiceTest
- Status: 2 TDD slices complete. Added validation tests for empty input and invalid store IDs.
- Blockers: none
- Next steps:
  - Add edge case tests for concurrent access
  - Consider integration with CacheService
- Autoloop notes: Manual TDD session, not using autoloop
```

### What This Example Shows

1. **Queue update is minimal**: Only `status` and `iterations` changed
2. **Narrative is separate**: Details are in session-state.md, not the queue
3. **Cross-reference works**: Queue ID matches session entry's "Components touched"
4. **Context preserved**: Next agent knows what was done and what's next

---

## 6. Validator Compatibility (S2-QUEUE-03)

The validator will enforce:

| Rule | Validation |
|------|------------|
| `status` | Must be one of: `"todo"`, `"in_progress"`, `"done"` |
| `iterations` | Must be integer >= 0 |
| `id` | Must match pattern `type:ClassName` |
| `sprint` | Must be integer 1-6 |
| Immutable fields | Cannot change `id`, `test_path`, etc. |

The convention documented here is designed to pass validation.

---

## 7. Safe Queue Updates (S3-AUTO-02)

### Queue Update Helper

**Always use the queue update helper** instead of manual JSON edits:

```bash
php scripts/queue-update.php --id <component-id> --status <status>
php scripts/queue-update.php --id <component-id> --increment-iterations
php scripts/queue-update.php --id <component-id> --status done --iterations 3
```

### Why Use the Helper?

| Feature | Manual Edit | Queue Helper |
|---------|-------------|--------------|
| Atomic writes | ❌ Possible corruption | ✅ Temp file + rename |
| Concurrent access | ❌ Race conditions | ✅ File locking |
| Validation | ❌ None | ✅ Automatic + rollback |
| Immutable protection | ❌ None | ✅ Only status/iterations |

### Transition Policy

| Event | Action | Iterations | Status |
|-------|--------|------------|--------|
| Start work | Begin component | +0 | `in_progress` |
| Complete slice | TDD iteration done | +1 | (unchanged) |
| Tests fail | Record attempt | +1 | `in_progress` |
| Tests pass, more work | Partial progress | +0 | `in_progress` |
| Tests pass, done | Quality gate passed | +0 | `done` |

### Quality Gate Rule

**CRITICAL**: A component may only be marked `done` when:

1. **All tests pass** - `./scripts/run-focused-tests.sh <TestClass>` returns 0
2. **Coverage is adequate** - Component's purpose is tested
3. **No regressions** - Related tests still pass
4. **Quality gates pass** - `./scripts/run-quality-gates.sh` returns 0

If any quality gate fails, the component MUST remain `in_progress`.

### Quality Gates Script (S3-QA-01)

Run quality gates before marking a component `done`:

```bash
./scripts/run-quality-gates.sh --component <component-id>
```

Gates checked:
- **Pint**: Laravel code style
- **PHPStan**: Static analysis (if installed)
- **ESLint/Prettier**: JS formatting (if installed)

Use `--fix` to auto-fix issues where supported.

### Example Commands

```bash
# Start work on a component
php scripts/queue-update.php --id unit:FooTest --status in_progress

# After each TDD slice
php scripts/queue-update.php --id unit:FooTest --increment-iterations

# When tests pass and coverage is satisfactory
./scripts/run-focused-tests.sh FooTest  # Must pass!
php scripts/queue-update.php --id unit:FooTest --status done

# Preview changes without writing
php scripts/queue-update.php --id unit:FooTest --status done --dry-run
```

### Error Handling

The helper handles errors gracefully:

- **Missing component**: Error message, exit 1
- **Invalid status**: Error message, exit 1
- **Validation fails**: Automatic rollback to previous state
- **Lock timeout**: Error after 10s, exit 1

---

## 8. Pre-commit Guard (S2-QUEUE-04)

A git pre-commit hook validates queue changes:

- Only `status` and `iterations` may change
- Immutable fields are protected
- Invalid transitions are blocked

To bypass (use sparingly):
```bash
QUEUE_GUARD_BYPASS=1 git commit -m "message"
```

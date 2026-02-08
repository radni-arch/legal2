# Command: TDD multi-component autoloop

## Executive Summary

- **Purpose**: Strict TDD autoloop across multiple components from `tdd-test-queue.json`
- **Limits**: Max 2 components per run, 2-3 TDD slices per component
- **Test command**: `./scripts/run-focused-tests.sh <TestClass>` (canonical)
- **Stop when**: Limits reached, tests failing, or no more components in active sprint
- **Bail rules**: Never weaken assertions, never skip RED phase, never bypass tests

## Quick Reference

| Item | Value |
|------|-------|
| Queue file | `test-results/tdd-test-queue.json` |
| Test runner | `./scripts/run-focused-tests.sh <TestClass>` |
| Max components/run | 2 |
| Max slices/component | 2-3 |
| Status transitions | `todo` → `in_progress` → `done` |

---

## Overview

This command ties together:

- The queue file `test-results/tdd-test-queue.json`
- The focused test runner `./scripts/run-focused-tests.sh`
- Strict TDD slices per component
- Automatic commit/push at the end

You are the `tdd-tall-specialist` agent running a **strict TDD autoloop** across multiple components in the `aglavas/ai-legal-war-machine` repo.

Your goals:

1. Use `test-results/tdd-test-queue.json` to choose what to work on.
2. For each chosen component:
   - Perform several **red → green → refactor** slices, using:
     - `./scripts/run-focused-tests.sh <test_class>` as the test runner.
3. Update the queue file when you're done with a component.
4. At the end of the session:
   - Ensure tests are passing for all touched components.
   - Commit and push changes to the current branch.

---

## Plan Mode (--plan)

Before running the autoloop, you can preview what would happen:

```bash
./scripts/autoloop-plan.sh
```

### What Plan Mode Shows

1. **Prerequisite Checks**
   - PHP and Composer dependencies
   - Database connectivity
   - Environment file
   - Test runner availability

2. **Component Selection**
   - Active sprint (auto-detected or specified)
   - Components that would be worked on
   - Priority order (in_progress first, then todo)

3. **Commands Preview**
   - Queue update commands
   - Test commands
   - Git operations

### Options

| Option | Description |
|--------|-------------|
| `--sprint <N>` | Force specific sprint |
| `--limit <N>` | Max components to show (default: 2) |
| `--verbose` | Show detailed prerequisite checks |
| `--quiet` | Only output exit code |

### Exit Codes (Plan Mode)

| Code | Constant | Meaning |
|------|----------|---------|
| `0` | `EXIT_SUCCESS` | Prerequisites satisfied, ready to run |
| `3` | `EXIT_NO_COMPONENTS` | No components to work on |
| `31` | `EXIT_PREREQ_MISSING` | Missing prerequisites (see output) |
| `53` | `EXIT_QUEUE_NOT_FOUND` | Queue file not found or malformed |

### Usage

```bash
# Check if ready to run autoloop
./scripts/autoloop-plan.sh

# Check specific sprint
./scripts/autoloop-plan.sh --sprint 2

# Verbose output
./scripts/autoloop-plan.sh --verbose

# Scripting (just exit code)
./scripts/autoloop-plan.sh --quiet && echo "Ready!"
```

**Important**: Plan mode does NOT:
- Execute any tests
- Mutate the queue file
- Make any file changes

Run plan mode before starting the autoloop to ensure prerequisites are met.

---

## Timebox and Iteration Controls

The autoloop supports safe limits to prevent unbounded runs.

### Available Controls

| Control | Flag | Example | Description |
|---------|------|---------|-------------|
| Timebox | `--timebox` | `60m`, `1h30m` | Maximum runtime |
| Max iterations | `--max-iterations` | `3` | Max components to work on |
| Max slices | `--max-slices` | `6` | Max TDD slices across all components |

### Usage

Source the controls library and initialize:

```bash
source scripts/lib/autoloop-controls.sh

# Initialize with limits
autoloop_init --timebox 60m --max-iterations 3

# In your iteration loop:
autoloop_check_limits || exit $?  # Check before each iteration
autoloop_increment_iteration      # After completing a component
autoloop_increment_slice          # After each TDD slice

# Check status anytime
autoloop_status
```

### Available Functions

| Function | Description |
|----------|-------------|
| `autoloop_init` | Initialize controls with limits |
| `autoloop_check_limits` | Returns non-zero if any limit exceeded |
| `autoloop_increment_iteration` | Increment iteration counter |
| `autoloop_increment_slice` | Increment slice counter |
| `autoloop_status` | Print current status |
| `autoloop_status_json` | Get status as JSON |
| `autoloop_elapsed` | Get elapsed seconds |
| `autoloop_remaining` | Get remaining seconds (-1 if unlimited) |

### Exit Codes

| Code | Constant | Meaning |
|------|----------|---------|
| `70` | `EXIT_TIMEBOX_EXCEEDED` | Time limit reached |
| `71` | `EXIT_MAX_ITERATIONS_EXCEEDED` | Iteration/slice limit reached |

### Behavior When Limits Exceeded

When a limit is exceeded:
1. Clear message printed explaining which limit was hit
2. Current status displayed (elapsed time, iterations, slices)
3. Non-zero exit code returned
4. **Work completed so far is preserved** (no rollback)

### Example: Safe Autoloop Wrapper

```bash
#!/usr/bin/env bash
source scripts/lib/autoloop-controls.sh

# Initialize with 1 hour timebox and max 3 components
autoloop_init --timebox 1h --max-iterations 3

for component in $(get_components); do
    # Check limits before starting each component
    autoloop_check_limits || exit $?

    # Work on component...
    do_tdd_work "$component"

    # Track progress
    autoloop_increment_iteration
    autoloop_status
done
```

---

## Human-in-the-Loop Pause/Resume

The autoloop supports automatic pausing when it detects problematic conditions, requiring human acknowledgement to resume.

### Why Pause?

Some failures indicate systemic problems that shouldn't be blindly retried:
- Database connection exhaustion
- Memory exhaustion (OOM)
- Infinite loops or timeouts
- Repeated consecutive failures

### Pause Triggers

| Trigger | Default | Description |
|---------|---------|-------------|
| Consecutive failures | 3 | Pause after N consecutive test failures |
| Total failures | 10 | Pause after N total failures in session |
| Error patterns | Configurable | Pause on specific error signatures |

### Usage

Source the pause library and initialize:

```bash
source scripts/lib/autoloop-pause.sh

# Initialize pause system (creates config if not exists)
pause_init

# After each test run, check triggers
pause_check_triggers "$exit_code" "$error_output"

# If paused, wait for human acknowledgement
if pause_is_paused; then
    pause_wait_for_resume
fi
```

### Available Functions

| Function | Description |
|----------|-------------|
| `pause_init` | Initialize pause system, create default config |
| `pause_check_triggers` | Check if pause should trigger based on exit code and output |
| `pause_is_paused` | Returns true if currently paused |
| `pause_is_enabled` | Returns true if pause system is enabled |
| `pause_wait_for_resume` | Blocking wait for human to resume |
| `pause_trigger` | Manually trigger a pause |
| `pause_clear` | Clear pause state (resume) |
| `pause_status` | Print current pause system status |
| `pause_record_success` | Record a successful test (resets consecutive counter) |
| `pause_record_failure` | Record a failed test (increments counters) |

### Resuming

When the autoloop pauses, it displays:
1. What triggered the pause
2. Current failure statistics
3. How to resume

**Resume Options:**

```bash
# Option 1: Interactive resume (recommended)
./scripts/autoloop-resume.sh

# Option 2: Direct file removal
rm test-results/.autoloop-pause-state

# Option 3: Environment variable (for automation)
AUTOLOOP_FORCE_RESUME=1
```

### Resume Script

The `./scripts/autoloop-resume.sh` script provides:

```bash
./scripts/autoloop-resume.sh           # Resume with acknowledgement
./scripts/autoloop-resume.sh --status  # Show current status
./scripts/autoloop-resume.sh --reason  # Show pause reason
./scripts/autoloop-resume.sh --log     # Show recent pause log
./scripts/autoloop-resume.sh --force   # Resume without confirmation
```

### Configuration

The pause configuration is stored at `test-results/.autoloop-pause-config.json`:

```json
{
  "enabled": true,
  "thresholds": {
    "consecutive_failures": 3,
    "total_failures": 10
  },
  "error_patterns": [
    {
      "pattern": "SQLSTATE.*too many connections",
      "description": "Database connection exhaustion",
      "action": "pause"
    },
    {
      "pattern": "Cannot allocate memory",
      "description": "Memory exhaustion",
      "action": "pause"
    },
    {
      "pattern": "Maximum execution time.*exceeded",
      "description": "Timeout - possible infinite loop",
      "action": "pause"
    }
  ],
  "resume_requires_acknowledgement": true
}
```

#### Error Pattern Actions

| Action | Behavior |
|--------|----------|
| `pause` | Immediately pause, require acknowledgement |
| `warn` | Print warning but continue |

### State Files

| File | Purpose |
|------|---------|
| `test-results/.autoloop-pause-state` | Current pause state (JSON) |
| `test-results/.autoloop-pause-config.json` | Pause configuration |
| `test-results/autoloop-pause.log` | Pause/resume event log |

### Example: Safe Autoloop with Pause

```bash
#!/usr/bin/env bash
source scripts/lib/autoloop-controls.sh
source scripts/lib/autoloop-pause.sh

# Initialize both systems
autoloop_init --timebox 1h --max-iterations 3
pause_init

for component in $(get_components); do
    # Check timebox/iteration limits
    autoloop_check_limits || exit $?

    # Check if paused from previous failure
    if pause_is_paused; then
        pause_wait_for_resume
    fi

    # Run TDD work
    test_output=$(run_tests "$component" 2>&1)
    exit_code=$?

    # Check pause triggers
    pause_check_triggers "$exit_code" "$test_output"

    # Track progress
    autoloop_increment_iteration
done
```

### When Paused

```
╔════════════════════════════════════════════════════════════════════╗
║                        AUTOLOOP PAUSED                             ║
╠════════════════════════════════════════════════════════════════════╣
║ Trigger:  consecutive_failures
║ Reason:   Consecutive failures reached threshold (3/3)
║
║ Stats:
║   Consecutive failures: 3
║   Total failures:       5
║
╠════════════════════════════════════════════════════════════════════╣
║ TO RESUME:
║   Option 1: ./scripts/autoloop-resume.sh
║   Option 2: rm test-results/.autoloop-pause-state
║   Option 3: AUTOLOOP_FORCE_RESUME=1 (env var)
╚════════════════════════════════════════════════════════════════════╝
```

### Disabling Pause System

To run without pause protection:

```bash
# Via config
jq '.enabled = false' test-results/.autoloop-pause-config.json > tmp.json && \
  mv tmp.json test-results/.autoloop-pause-config.json

# Or delete config (it will be recreated on next run)
rm test-results/.autoloop-pause-config.json
```

---

## Bounded Test Retries

The test runner includes automatic retry logic for known-flaky tests and transient failures.

### Retry Policy

| Scenario | Max Retries | Recovery Action |
|----------|-------------|-----------------|
| Known flaky (in `flaky-tests.json`) | Per-test config (default: 1) | None |
| Dusk browser tests | 1 | Add `--without-tty` flag |
| DB connectivity error | 1 | Clear config/cache |
| DB lock/deadlock | 1 | None (just retry) |
| Generic PHPUnit failure | 0 | No retry (real failure) |

**Hard cap**: 2 total retries maximum per invocation, regardless of policies.

### Usage

```bash
# Normal run (retries enabled)
./scripts/run-focused-tests.sh VectorStoreManagerTest

# Disable retries
./scripts/run-focused-tests.sh VectorStoreManagerTest --no-retry

# Verbose retry debugging
./scripts/run-focused-tests.sh VectorStoreManagerTest --verbose
```

### Flaky Tests Registry

Known flaky tests are tracked in `test-results/flaky-tests.json`:

```json
{
  "version": 1,
  "tests": {
    "SomeFlakeyTest::test_timing_sensitive": {
      "reason": "Race condition in async job",
      "max_retries": 1,
      "added": "2025-12-18"
    }
  },
  "error_signatures": {
    "db_connection": {
      "patterns": ["SQLSTATE.*Connection refused"],
      "max_retries": 1,
      "recovery": "clear_config_cache"
    }
  },
  "hard_cap": 2
}
```

### Retry Logging

When a retry occurs, the runner displays:

```
╔════════════════════════════════════════════════════════════╗
║                      RETRY TRIGGERED                       ║
╠════════════════════════════════════════════════════════════╣
║ Attempt:   1 of 2 (hard cap)
║ Reason:    Database connection error
║ Recovery:  clear_config_cache
╚════════════════════════════════════════════════════════════╝
```

### Metadata Output

The `.last-run.json` metadata includes retry information:

```json
{
  "retry": {
    "enabled": true,
    "attempts": 2,
    "total_retries": 1,
    "hard_cap": 2,
    "summary": "Passed on retry 1"
  }
}
```

### Adding Flaky Tests

To mark a test as known-flaky:

```bash
# Edit flaky-tests.json
jq '.tests["MyFlakyTest::test_method"] = {
  "reason": "Description of flakiness",
  "max_retries": 1,
  "added": "'$(date +%Y-%m-%d)'"
}' test-results/flaky-tests.json > tmp.json && mv tmp.json test-results/flaky-tests.json
```

### Key Principles

1. **Generic failures don't retry** - Only specific known issues get retried
2. **Hard cap prevents masking** - Can't retry more than 2 times total
3. **Logging is explicit** - Every retry shows reason and recovery action
4. **Retries are opt-out** - Use `--no-retry` for strict testing

---

## Focused Fixture Seeding

Fast, component-scoped database seeding instead of full reseed every time.

### Usage

```bash
# Seed for a specific domain
./scripts/seed-focused-fixtures.sh --domain vector_stores

# Seed for a component (looks up domain from queue)
./scripts/seed-focused-fixtures.sh --component livewire:VectorStoreManagerTest

# Full seed (CI mode)
./scripts/seed-focused-fixtures.sh --full

# Fresh migration + seed
./scripts/seed-focused-fixtures.sh --domain vector_stores --fresh

# List available mappings
./scripts/seed-focused-fixtures.sh --list
```

### Fixture Mapping

Domain-to-seeder mappings are defined in `database/seeders/fixture-map.json`:

```json
{
  "default": ["UserTestSeeder", "ReferenceDataSeeder"],
  "domains": {
    "vector_stores": ["UserTestSeeder", "LegalCaseTestSeeder"],
    "court_integration": ["UserTestSeeder", "CourtDecisionDownloadSeeder"],
    "authentication": ["UserTestSeeder"]
  },
  "full_seed": ["ReferenceDataSeeder", "UserTestSeeder", "LegalCaseTestSeeder", ...]
}
```

### Output

```
═══════════════════════════════════════════════════════════
              Focused Fixture Seeding
═══════════════════════════════════════════════════════════

  Seeders to run: 2
  Fresh migrate:  false

Seeding fixtures:

  UserTestSeeder... ✓ (623ms)
  LegalCaseTestSeeder... ✓ (145ms)

═══════════════════════════════════════════════════════════
  Succeeded: 2/2
  Duration:  1s
═══════════════════════════════════════════════════════════
```

### When to Use

| Scenario | Command |
|----------|---------|
| Before TDD work on a component | `--component <id>` |
| Testing a specific domain | `--domain <name>` |
| CI pipeline | `--full --fresh` |
| After schema changes | `--domain <name> --fresh` |

---

## Available tools (assumed)

You have:

- File system access to the checked-out repo.
- Shell access to run commands:
  - `./scripts/run-focused-tests.sh <test_class>`
  - `./scripts/run-tests-with-analysis.sh` (optional outer-loop)
  - `php artisan ...`, `git ...`, etc.
- Git credentials to commit and push.

Do **not** re-clone the repository; it is already cloned for this session.

---

## Queue file format

Queue is stored at:

```
test-results/tdd-test-queue.json
```

### Full structure

```json
{
  "generated_at": "2025-11-28T03:43:53+00:00",
  "total_tests": 592,
  "stats": {
    "by_type": { "unit": 330, "feature": 152, ... },
    "by_domain": { "graph_and_neo4j": 61, "search_services": 50, ... },
    "by_sprint": { "1": 123, "2": 23, "3": 259, ... }
  },
  "sprint_guide": {
    "1": "Core backend: Textract, Search, Vector stores, Graph (Unit tests)",
    "2": "Feature/Livewire managers: VectorStoreManager, TextractManager, etc.",
    "3": "Integration tests + Agents/Research pipelines",
    "4": "Dashboards, Viewers, Timelines (Livewire + Browser)",
    "5": "Security, Authentication, remaining Browser tests",
    "6": "Performance, Snapshots, catch-all"
  },
  "visual_groupings": [
    {
      "entry_point": {
        "id": "browser:AgentCollaborationViewerTest",
        "test_class": "AgentCollaborationViewerTest",
        "test_path": "tests/Browser/AgentCollaborationViewerTest.php",
        "domain": "agents_and_research",
        "sprint": 3
      },
      "supporting_tests": {
        "livewire": [...],
        "feature": [...],
        "integration": [...],
        "unit": [...]
      }
    }
  ],
  "components": [
    {
      "id": "livewire:VectorStoreManagerTest",
      "sprint": 2,
      "domain": "vector_stores",
      "type": "feature_livewire",
      "test_class": "VectorStoreManagerTest",
      "test_path": "tests/Feature/Livewire/VectorStoreManagerTest.php",
      "status": "todo",
      "iterations": 0,
      "related_tests": ["unit:CaseVectorStoreServiceTest", "integration:VectorStoreManagementIntegrationTest"]
    }
  ],
  "meta": {
    "notes": "...",
    "test_command": "./scripts/run-focused-tests.sh <test_class>",
    "status_values": ["todo", "in_progress", "done"],
    "hierarchy": "browser_dusk → feature_livewire → feature → integration → unit"
  }
}
```

### Key sections

| Section | Purpose | Agent usage |
|---------|---------|-------------|
| `stats` | Test distribution overview | Reference for planning |
| `sprint_guide` | Sprint descriptions | Understand sprint focus |
| `visual_groupings` | Hierarchical browser-to-unit test mapping | Understand dependencies |
| `components` | **Flat array to iterate** | Primary work list |
| `meta` | Configuration notes | Valid statuses, commands |

### Component fields

| Field | Description |
|-------|-------------|
| `id` | Stable identifier (e.g., `unit:CaseGraphSyncServiceTest`, `livewire:VectorStoreManagerTest`) |
| `sprint` | Numeric sprint index (1, 2, 3, …) |
| `domain` | Logical domain (e.g., `textract_pipeline`, `graph_and_neo4j`) |
| `type` | One of: `unit`, `feature`, `feature_livewire`, `integration`, `browser_dusk`, `performance`, `snapshot` |
| `test_class` | PHPUnit/Dusk class name to use as filter |
| `test_path` | Path to the test file |
| `status` | `"todo"`, `"in_progress"`, or `"done"` |
| `iterations` | Number of TDD iterations previously applied |
| `related_tests` | Array of related test IDs for context |

---

## Selecting sprint and components

When this command runs:

1. **Load and parse** `test-results/tdd-test-queue.json`.

2. **Determine the active sprint:**
   - If the user explicitly provided a sprint number in the prompt (e.g., "Current sprint is 2"), use that.
   - Otherwise: Let `active_sprint` be the **smallest `sprint` value** among `components` where `status != "done"`.
   - Reference `sprint_guide` to understand the sprint's focus.

3. **Select candidate components:**
   - Filter `components` where:
     - `sprint == active_sprint`
     - `status` in `["todo", "in_progress"]`
     - `type` in `["unit", "feature", "feature_livewire", "integration"]`
   - (Skip `browser_dusk`, `performance`, `snapshot` for this command; they can be handled by separate specialized loops.)

4. **Prioritize:**
   - `status == "in_progress"` first, then
   - `status == "todo"`.
   - Use `related_tests` to inform context but don't necessarily work on all related tests.

5. **Per-run limits:**
   - In a single invocation of this command: work on at most **2 components**.
   - For each component, perform at most **2–3 TDD slices**.
   - Stop when:
     - You reach these limits, or
     - There are no more candidate components for the active sprint.

---

## For each selected component

Given a chosen component with these fields:

- `id`
- `sprint`
- `domain`
- `type`
- `test_class`
- `test_path`
- `related_tests`

Follow this process.

### 1. Load context

- Read the test file at `test_path`.

- Check `related_tests` for additional context.

- Infer implementation:
  - If `id` starts with `livewire:`:
    - Map `VectorStoreManager` → likely:
      - `app/Livewire/VectorStoreManager.php`
      - `resources/views/livewire/vector-store-manager.blade.php`
  - If `test_path` is under `Feature/Api`:
    - Map to controllers in `app/Http/Controllers/Api/...`.
  - If under `Unit/Services/...`:
    - Map to `app/Services/...`.
  - If under `Integration/...`:
    - Look for related services, jobs, and controllers that participate in the workflow.

- Look for relevant documentation files, e.g., files whose names contain the component name (`VectorStoreManager`, `TranscriptPreviewer`, `Textract`, etc.) or domain guides like `*_TESTING_GUIDE.md`, `*_FINAL_REPORT.md`, `*_BLUEPRINT.md`.

- Summarize in a few bullet points:
  - What this component is responsible for.
  - What the existing tests assert.
  - Obvious gaps or edge cases not covered.

### 2. Plan bounded TDD slices

Before changing any files, plan 2–3 TDD slices for this component:

For each slice, specify:

- **Goal**: One small improvement (e.g., "invalid input X yields error banner Y", "edge-case Z is handled gracefully").
- **Tests**: Which test methods in `test_path` you will modify or which new ones you will add.
- **Implementation**: Which code files are likely to require changes.
- **Command**: Always run:
  - `./scripts/run-focused-tests.sh <test_class>`

Output this plan in the conversation. Only after the plan is written should you proceed to execute it.

### 3. Execute slices (RED → GREEN → REFACTOR)

For each slice in the plan:

#### 3.1 RED — update/add tests

- Modify or add tests in `test_path` to encode the slice's behavior.
- Feel free to adjust or extend helpers/fixtures in `tests/Fixtures`, `tests/TestData`, etc., if they make tests clearer and more robust.
- Then run:
  - `./scripts/run-focused-tests.sh <test_class>`
- Confirm that:
  - At least one test fails.
  - The failure message(s) match the intended new behavior (e.g., assertion mismatches, missing validation, incorrect state).
- If tests pass unexpectedly:
  - Strengthen assertions or adjust the scenario.
  - Re-run until you have a meaningful failing test.
- Do not move to GREEN until there is at least one failing test for the new behavior.

#### 3.2 GREEN — minimal implementation

- Implement the minimal production changes required to make the failing tests pass.
- Focus only on relevant files:
  - Livewire component & view
  - Controller action
  - Service method
  - Job / pipeline step
- Avoid touching unrelated modules or refactoring broadly.
- Re-run:
  - `./scripts/run-focused-tests.sh <test_class>`
- Iterate until all tests in that file related to the slice are passing.

#### 3.3 REFACTOR — clean up without behavior change

- With tests green:
  - Remove duplication.
  - Improve naming and structure.
  - Align with existing patterns in the repo (e.g., how other Livewire tests are structured).
- Do not change the intended behavior and do not weaken assertions.
- Re-run:
  - `./scripts/run-focused-tests.sh <test_class>`
- Verify everything remains green.

Stop working on this component once you have completed the planned slices or hit the per-component slice limit.

### 4. Update the queue entry

After finishing the slices for this component, use the safe queue update helper:

```bash
# Update status and increment iterations
php scripts/queue-update.php --id <component-id> --status <status> --increment-iterations
```

#### Transition Policy

| Event | Action | Command |
|-------|--------|---------|
| **Start work** | Mark `in_progress` | `php scripts/queue-update.php --id <id> --status in_progress` |
| **After each slice** | Increment iterations | `php scripts/queue-update.php --id <id> --increment-iterations` |
| **Tests fail** | Keep `in_progress`, record iteration | `php scripts/queue-update.php --id <id> --increment-iterations` |
| **Tests pass, more work needed** | Keep `in_progress` | (no status change needed) |
| **Tests pass, coverage satisfactory** | Mark `done` | `php scripts/queue-update.php --id <id> --status done` |

#### Quality Gate Rule

**IMPORTANT**: Only mark a component `done` when:

1. All tests in `test_path` are passing
2. You have verified via `./scripts/run-focused-tests.sh <test_class>`
3. Test coverage is satisfactory for the component's purpose
4. **Quality gates pass** (see below)

If any tests are failing or quality gates fail, the component MUST remain `in_progress`.

#### Running Quality Gates

Before marking a component as `done`, run the quality gates:

```bash
./scripts/run-quality-gates.sh --component <component-id>
```

Quality gates check:
- **PHP Syntax**: Parse errors in changed files
- **Pint**: Laravel code style (PSR-12 + Laravel conventions)
- **PHPStan**: Static analysis (if installed)
- **ESLint/Prettier**: JavaScript formatting (if installed)

Options:
- `--fix`: Auto-fix issues where supported
- `--changed`: Only check files changed since last commit
- `--staged`: Only check staged files
- `--cached`: Use cached result if git state unchanged
- `--verbose`: Show detailed output from each tool

Exit codes:
- `0`: All gates passed - safe to mark `done`
- `10`: Lint gate failed
- `11`: Format gate failed

**Never mark a component `done` if quality gates fail.**

#### Example Workflow

```bash
# 1. Starting work on unit:FooTest
php scripts/queue-update.php --id unit:FooTest --status in_progress

# 2. After completing first TDD slice
php scripts/queue-update.php --id unit:FooTest --increment-iterations

# 3. After completing second TDD slice
php scripts/queue-update.php --id unit:FooTest --increment-iterations

# 4. Verify tests pass
./scripts/run-focused-tests.sh FooTest

# 5. Run quality gates
./scripts/run-quality-gates.sh --component unit:FooTest

# 6. If both pass, mark done
php scripts/queue-update.php --id unit:FooTest --status done

# 7. Log the iteration (automatic)
./scripts/append-session-notes.sh --component unit:FooTest \
  --command "./scripts/run-focused-tests.sh FooTest" \
  --outcome pass --sprint 1 --iterations 2
```

#### Session Notes (Auto-Append)

After every iteration, append a session note to track progress:

```bash
./scripts/append-session-notes.sh \
  --component <component-id> \
  --command "<command-run>" \
  --outcome <pass|fail|skip|error> \
  [--sprint <n>] \
  [--iterations <n>] \
  [--failures "test1,test2"] \
  [--note "Additional context"]
```

Session notes are stored in `test-results/session-notes.md`.

**Rotation:** File automatically rotates when exceeding 500 lines. Archives stored in `test-results/session-notes-archive/`.

**Always append a session note after:**
- Completing a TDD slice (pass or fail)
- Encountering an error or blocker
- Finishing work on a component

#### Dry Run

Preview changes before applying:

```bash
php scripts/queue-update.php --id unit:FooTest --status done --dry-run
```

#### Features of the Queue Helper

- **Atomic writes**: Uses temp file + rename pattern
- **File locking**: Prevents concurrent modification issues
- **Validation**: Runs validator after write, rolls back on failure
- **Immutable field protection**: Only allows status/iterations changes

**Important**: Do not manually edit the queue JSON. Always use the helper script.

---

## Optional outer-loop tests

After completing your per-component slices (for one or more components), if time and resources allow:

- Run a broader test sweep:
  - `./scripts/run-tests-with-analysis.sh`
- Use the resulting logs and any analysis artifacts per `scripts/INTELLIGENT_TEST_ANALYSIS.md` to:
  - Detect unexpected regressions.
  - Suggest future queue updates (e.g., mark some components as needing more TDD iterations).

---

## Session completion: tests, commit, and push

At the end of this command's run:

### 1. Focused re-checks for touched components

For each component you edited, re-run its focused tests:

```bash
./scripts/run-focused-tests.sh <test_class>
```

Ensure all are green.

### 2. Git status & diff review

Run:

```bash
git status
```

Optionally show a summary of changes via:

```bash
git diff --stat
```

### 3. Commit

Stage changes:

```bash
git add <changed-files>  # or `git add .` if appropriate
```

Craft a concise commit message capturing sprint and components, for example:

```bash
git commit -m "TDD: [Sprint <active_sprint>] Hardening tests and behavior for <id1>, <id2>"
```

If `git commit` fails due to missing user config, mention this in your final report.

### 4. Push

Push to the current branch:

```bash
git push origin HEAD
```

If you discover you are on a detached HEAD or an inappropriate base branch:

- Create a new branch and push:

  ```bash
  git checkout -b tdd-sprint<active_sprint>-<short-id>
  git push -u origin tdd-sprint<active_sprint>-<short-id>
  ```

### 5. Final report

In your final message:

- **Identify:**
  - Active sprint and domains touched.
  - Components (ids) you worked on and their new status / iterations.

- **Mention:**
  - The branch name you pushed to.
  - The last commit hash.

- **List** any recommended follow-up work for future TDD sessions, ideally in terms of queue updates (status, sprint, new entries).

- Ensure there are **no uncommitted changes** in the working tree at session end.

---

## Using visual_groupings for context

The `visual_groupings` section shows browser tests as "entry points" with their supporting tests at each layer:

```
browser_dusk → feature_livewire → feature → integration → unit
```

Use this to:

- Understand what lower-level tests exist for a browser test you might eventually want to run.
- Find related unit/integration tests when working on a Livewire component.
- Plan TDD work bottom-up: strengthen units first, then features, then browser acceptance.

You typically won't modify `visual_groupings`, but referencing it helps you understand the test ecosystem.

---

## Things you must NOT do

- Do not randomly run destructive commands (dropping databases, blowing away data) unless explicitly part of the test setup.
- Do not create new test categories or directories without strong justification.
- Do not bypass tests to "save time"; tests are the authoritative spec here.
- Do not modify CI workflows or security-critical config unless it's specifically part of the task.
- Do not modify `stats`, `visual_groupings`, `sprint_guide`, or `meta` sections of the queue file — only update `components` entries.

---

## Standard Exit Codes Reference

The autoloop uses standardized exit codes defined in `scripts/lib/autoloop-exit-codes.sh`.

### Exit Code Ranges

| Range | Category | Description |
|-------|----------|-------------|
| `0` | SUCCESS | Operation completed successfully |
| `1-9` | GENERAL | General/unknown errors |
| `10-19` | LINT | Lint/format gate failures |
| `20-29` | TEST | Test failures |
| `30-39` | SETUP | Setup/readiness failures |
| `40-49` | VALIDATION | Validation failures |
| `50-59` | QUEUE | Queue operation failures |
| `60-69` | GIT | Git operation failures |
| `70-79` | LIMIT | Timebox/iteration limits exceeded |

### Common Exit Codes

| Code | Constant | Meaning |
|------|----------|---------|
| `0` | `EXIT_SUCCESS` | All operations passed |
| `3` | `EXIT_NO_COMPONENTS` | No components to work on |
| `20` | `EXIT_TESTS_FAILED` | One or more tests failed |
| `21` | `EXIT_UNIT_TESTS_FAILED` | Unit tests failed |
| `22` | `EXIT_FEATURE_TESTS_FAILED` | Feature tests failed |
| `31` | `EXIT_PREREQ_MISSING` | Prerequisites not satisfied |
| `32` | `EXIT_DATABASE_UNAVAILABLE` | Database connection failed |
| `41` | `EXIT_QUEUE_SCHEMA_INVALID` | Queue schema validation failed |
| `53` | `EXIT_QUEUE_NOT_FOUND` | Queue file not found |
| `60` | `EXIT_GIT_COMMIT_FAILED` | Git commit failed |
| `61` | `EXIT_GIT_PUSH_FAILED` | Git push failed |
| `70` | `EXIT_TIMEBOX_EXCEEDED` | Time limit reached |
| `71` | `EXIT_MAX_ITERATIONS_EXCEEDED` | Iteration/slice limit reached |

### Using Exit Codes in Scripts

```bash
# Source the exit codes library
source scripts/lib/autoloop-exit-codes.sh

# Use constants instead of magic numbers
if [[ $test_result -ne 0 ]]; then
    exit_with $EXIT_TESTS_FAILED "Tests failed in FooTest"
fi

# Map test runner exit to standard code
standard_code=$(map_test_exit $phpunit_exit "unit")
```

### CI Integration

Exit codes enable CI branching decisions:

```yaml
# Example GitHub Actions
- name: Run autoloop plan
  run: ./scripts/autoloop-plan.sh --quiet
  continue-on-error: true
  id: plan

- name: Check readiness
  if: steps.plan.outcome == 'success'
  run: echo "Ready to run TDD autoloop"
```

# Automation Map

Quick-reference for all TDD autoloop automation components in this repository.

---

## Overview Diagram

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           SESSION LIFECYCLE                                  │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  ┌──────────────────┐    ┌──────────────────┐    ┌──────────────────────┐  │
│  │ session-start.sh │───▶│ setup-wrapper.sh │───▶│ setup-status.json    │  │
│  │ (SessionStart)   │    │ (background)     │    │ (readiness report)   │  │
│  └──────────────────┘    └──────────────────┘    └──────────────────────┘  │
│           │                      │                         │               │
│           ▼                      ▼                         ▼               │
│  ┌──────────────────┐    ┌──────────────────┐    ┌──────────────────────┐  │
│  │ Workflow Primer  │    │ setup-all.sh     │    │ /check-setup         │  │
│  │ (context inject) │    │ (services)       │    │ (health check)       │  │
│  └──────────────────┘    └──────────────────┘    └──────────────────────┘  │
│                                                                             │
├─────────────────────────────────────────────────────────────────────────────┤
│                           TDD AUTOLOOP                                      │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  ┌──────────────────┐    ┌──────────────────┐    ┌──────────────────────┐  │
│  │ /tdd-multi-      │───▶│ tdd-tall-        │───▶│ run-focused-tests.sh │  │
│  │ component-       │    │ specialist.md    │    │ (test runner)        │  │
│  │ autoloop         │    │ (agent prompt)   │    │                      │  │
│  └──────────────────┘    └──────────────────┘    └──────────────────────┘  │
│           │                      │                         │               │
│           ▼                      ▼                         ▼               │
│  ┌──────────────────┐    ┌──────────────────┐    ┌──────────────────────┐  │
│  │ tdd-test-        │    │ session-state.md │    │ .last-run.json       │  │
│  │ queue.json       │    │ (handoff notes)  │    │ (test results)       │  │
│  └──────────────────┘    └──────────────────┘    └──────────────────────┘  │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## Sprint Summary

| Sprint | Theme | Key Deliverables |
|--------|-------|------------------|
| **S1** | Foundation | Hook entrypoints, session lifecycle |
| **S2** | Queue Management | Digest, validators, scaffolding, guards |
| **S3** | Autoloop Controls | Safe updates, quality gates, pause/resume |
| **S4** | Testing & Docs | Adaptive selection, fixtures, reference system |
| **S5** | Observability & CI | Metrics, alerts, failure briefs, CI shadow mode |

---

# Sprint 1: Foundation

Established the core session lifecycle, hook infrastructure, and environment health checks.

## S1-STARTUP-01: Hook Entrypoints Audit

**Purpose:** Document and standardize all hook entry points for Claude Code sessions.

**Deliverables:**
- `.claude/docs/HOOK_ENTRYPOINTS.md` - Source of truth for hook configuration
- `.claude/hooks/session-start.sh` - SessionStart hook implementation
- `.claude/hooks/setup-wrapper.sh` - Background setup dispatcher

## S1-DOCS-01: Session State Handoff Template

**File:** `.claude/session-state.md`

**Purpose:** Fast handoffs between agents without re-parsing the world.

**Template format:**
```markdown
## YYYY-MM-DD HH:MM
- Sprint: S<N>
- Components touched: [<queue-ids>]
- Last test command: ./scripts/run-focused-tests.sh <args>
- Status: <what's true now>
- Blockers: <what's stuck, or "none">
- Next steps:
  - <bullet 1>
  - <bullet 2>
- Autoloop notes: <brief, or "n/a">
```

## S1-STARTUP-02: SessionStart Primer Injection

**Purpose:** Agents see workflow "rules of the road" immediately without opening docs.

**Constraints:**
- ≤8 bullets
- <120 words total
- No legacy/deprecated references

**Primer contents:**
- Queue path: `test-results/tdd-test-queue.json`
- Test runner: `./scripts/run-focused-tests.sh`
- Autoloop commands: `/tdd-multi-component-autoloop`
- Session handoff: `.claude/session-state.md`
- Health check: `/check-setup`

## S1-SETUP-01: Setup Wrapper Guardrails

**File:** `.claude/hooks/setup-wrapper.sh`

**Purpose:** Setup fails fast on real readiness issues and ends with stable summary.

**Checks performed:**
- DB connectivity with retry/backoff (`pg_isready`, `psql`)
- Migration readiness (`php artisan migrate:status`)
- Seed verification (key tables exist)
- Composer/NPM install status

**Summary line format:**
```
SETUP READY (db=ok, migrations=ok, seed=ok, node=ok, neo4j=ok)
```

## S1-SETUP-02: Persist Setup Status

**File:** `test-results/setup-status.json`

**Purpose:** Agents see "last setup: ok/failed + timestamp" at startup.

**Schema:**
```json
{
  "timestamp": "2025-12-18T19:27:09+00:00",
  "overall": "ok|fail",
  "subsystems": {
    "db": { "status": "ok|fail|skip" },
    "migrations": { "status": "ok|fail|skip" },
    "seed": { "status": "ok|fail|skip" },
    "composer": { "status": "ok|fail|skip" },
    "neo4j": { "status": "ok|fail|skip" }
  }
}
```

## S1-SETUP-03: /check-setup Command

**Files:**
- `.claude/commands/check-setup.md` - Command definition
- `scripts/check-setup.sh` - Helper script

**Purpose:** One-screen, non-destructive "are we healthy?" check.

**Usage:**
```bash
/check-setup   # Via Claude command
./scripts/check-setup.sh   # Direct script
```

**Checks:**
- Last setup status from `test-results/setup-status.json`
- DB connectivity (live check)
- Migration status
- Matrix baseline comparison

## S1-SETUP-04: Safety Rails for Destructive Scripts

**Files:** `scripts/setup-all.sh`, `scripts/pgtool.sh`

**Purpose:** Prevent accidental DB drops/resets locally.

**Behavior:**
- Destructive operations require `--force` when not in CI
- `CI=true` allows bypass for CI environments
- Clear banner on block with remediation command

## S1-SETUP-05: Environment Matrix Check

**File:** `scripts/check-matrix.sh`

**Purpose:** Detect env drift and missing prerequisites.

**Usage:**
```bash
./scripts/check-matrix.sh
```

**Validates:**
- PHP version and extensions
- Node version
- Composer/NPM availability
- DB client/server reachability
- Chrome/Chromedriver presence
- Required env vars

**Baseline:** `test-results/matrix-baseline.json`

## S1-SETUP-06: Wire Matrix into Setup

**Purpose:** Matrix checks reused everywhere, not re-implemented.

**Integration:**
- Called early in setup wrapper (fail early)
- Called in `/check-setup` (surface drift without full setup)
- Results included in `test-results/setup-status.json`

---

# Sprint 2: Queue Management

Built the TDD queue infrastructure for tracking component progress.

## S2-QUEUE-00: Queue Breadcrumbs Decision

**File:** `documentation/queue-breadcrumbs.md`

**Decision:** Keep `iterations` as a number (counter only), narrative breadcrumbs go to session notes.

**Rationale:**
- Simple schema, easy validation
- Smaller file size (queue is already 259KB)
- Backwards compatible with existing autoloop

## S2-QUEUE-01: TDD Queue Digest Generator

**File:** `scripts/generate-queue-digest.php`

**Purpose:** Generate compact queue summary for SessionStart injection.

**Usage:**
```bash
php scripts/generate-queue-digest.php
```

**Output:** Top 3 components with status, sprint, domain.

## S2-STARTUP-01: Queue Digest Injection

**Integration:** SessionStart hook now injects queue digest automatically.

**What it shows:**
- Active component (if any in_progress)
- Top 3 components by priority
- Queue statistics (in_progress, todo, done counts)

## S2-CTX-01: Active Component Context

**Files:**
- `scripts/generate-active-context.php` - Context generator
- `test-results/context/active-component.md` - Generated context

**Purpose:** Generate focused context for the active component.

## S2-QUEUE-02: Breadcrumb Convention

**File:** `documentation/queue-breadcrumbs.md` (Section 2)

**Defines:**
- Fields you MAY modify: `status`, `iterations`
- Fields you MUST NOT modify: `id`, `sprint`, `domain`, `type`, etc.
- Status transitions: `todo` → `in_progress` → `done`

## S2-QUEUE-03: Queue Schema Validator

**File:** `scripts/validate-queue.php`

**Usage:**
```bash
php scripts/validate-queue.php
php scripts/validate-queue.php --strict
```

**Validates:**
- `status` must be: `todo`, `in_progress`, or `done`
- `iterations` must be integer >= 0
- `id` must match pattern `type:ClassName`
- `sprint` must be integer 1-6

## S2-QUEUE-04: Pre-commit Queue Guard

**File:** `.git/hooks/pre-commit` (queue validation)

**Purpose:** Block commits that modify immutable queue fields.

**Protected fields:** `id`, `sprint`, `domain`, `type`, `test_class`, `test_path`

**Bypass:**
```bash
QUEUE_GUARD_BYPASS=1 git commit -m "message"
```

## S2-QUEUE-05: Component Scaffolding

**File:** `scripts/scaffold-component.php`

**Usage:**
```bash
php scripts/scaffold-component.php unit FooTest search_services
```

**Creates:**
- Test file skeleton
- Adds component to queue with `status: todo`

---

# Sprint 3: Autoloop Controls

Added safety controls and quality gates for the TDD autoloop.

## S3-AUTO-01: Safe Queue Update Helper

**File:** `scripts/queue-update.php`

**Usage:**
```bash
php scripts/queue-update.php --id <component-id> --status <status>
php scripts/queue-update.php --id <component-id> --increment-iterations
php scripts/queue-update.php --id <component-id> --status done --dry-run
```

**Features:**
- Atomic writes (temp file + rename)
- File locking (prevents race conditions)
- Automatic validation + rollback on failure
- Only modifies `status` and `iterations`

## S3-AUTO-02: Automatic Status Transitions

**Documentation:** `documentation/queue-breadcrumbs.md` (Section 7)

**Transition policy:**
| Event | Status | Iterations |
|-------|--------|------------|
| Start work | `in_progress` | +0 |
| Complete slice | (unchanged) | +1 |
| Tests fail | `in_progress` | +1 |
| Tests pass, done | `done` | +0 |

## S3-AUTO-03: Dry-run/Plan Mode

**File:** `.claude/commands/tdd-multi-component-autoloop.md`

**Feature:** `--dry-run` flag shows what would happen without making changes.

## S3-AUTO-04: Standard Exit Codes

**Exit codes for autoloop:**
| Code | Meaning |
|------|---------|
| 0 | Success, all tests passed |
| 1 | Tests failed |
| 2 | Configuration error |
| 3 | Timeout exceeded |
| 4 | User requested stop |

## S3-QA-01: Quality Gates

**File:** `scripts/run-quality-gates.sh`

**Usage:**
```bash
./scripts/run-quality-gates.sh --component <component-id>
./scripts/run-quality-gates.sh --fix  # Auto-fix where possible
```

**Gates checked:**
- Laravel Pint (code style)
- PHPStan (static analysis, if installed)
- ESLint/Prettier (JS, if installed)

**Rule:** Component may only be marked `done` when quality gates pass.

## S3-OBS-01: Session Notes Auto-append

**File:** `.claude/session-state.md`

**Feature:** Autoloop automatically appends session notes after each iteration.

**Entry format:**
```markdown
## YYYY-MM-DD HH:MM
- Sprint: S<N>
- Components touched: [<queue-ids>]
- Last test command: ./scripts/run-focused-tests.sh <args>
- Status: <what's true now>
- Blockers: <what's stuck, or "none">
- Next steps:
  - <bullet 1>
  - <bullet 2>
- Autoloop notes: <brief, or "n/a">
```

## S3-AUTO-05: Timeboxing Controls

**File:** `.claude/commands/tdd-multi-component-autoloop.md`

**Limits:**
- Max 2 components per run
- 2-3 TDD slices per component
- Configurable iteration limits

## S3-AUTO-06: Human-in-the-loop Pause/Resume

**Files:**
- `test-results/.autoloop-pause-state` - Pause state file
- `test-results/.autoloop-pause-config.json` - Pause configuration

**Commands:**
```bash
# Pause autoloop
touch test-results/.autoloop-pause-state

# Resume autoloop
rm test-results/.autoloop-pause-state
```

---

# Sprint 4: Testing & Documentation

Enhanced test infrastructure and documentation system.

## S4-TEST-01: Adaptive Test Selection

**File:** `scripts/run-focused-tests.sh`

**Features:**
- Automatic retry for flaky tests
- Bounded retry count (max 3)
- Flaky test registry: `test-results/flaky-tests.json`

**Usage:**
```bash
./scripts/run-focused-tests.sh <TestClass>
./scripts/run-focused-tests.sh tests/Unit/SomeTest.php
```

## S4-FIX-01: Focused Fixture Seeding

**Files:**
- `scripts/seed-focused-fixtures.sh` - Focused seeder
- `database/seeders/fixture-map.json` - Domain-to-seeder mapping

**Usage:**
```bash
./scripts/seed-focused-fixtures.sh --domain search_services
./scripts/seed-focused-fixtures.sh --scenario basic
```

**Scenarios:**
- `basic` - Minimal fixtures for unit tests
- `full` - Complete fixtures for integration tests

## S4-STARTUP-01: Context Size Budgeting

**File:** `test-results/context-budget.json`

**Purpose:** Track and limit context injection size (~2000 bytes).

**Enforcement:** `setup-wrapper.sh` truncates output if over budget.

## S4-DOCS-01: Token-friendly Reference Access

**Files:**
- `.claude/docs/REFERENCE_INDEX.md` - Documentation index
- `.claude/commands/refs.md` - `/refs` command

**Usage:**
```bash
/refs           # Show full index
/refs testing   # Show testing-related docs
```

## S4-DOCS-02: Command Executive Summaries

**Updated:** All `.claude/commands/*.md` files

**Standard format:**
```markdown
# Command Name

**Purpose:** One-line description

**Usage:** `/<command> [args]`

**Limits:** Key constraints

---
[Full documentation below]
```

## S4-DOCS-03: TDD Specialist Operative Checklist

**File:** `.claude/agents/tdd-tall-specialist.md`

**Added:** Step-by-step operative checklist for TDD cycle.

## S4-DOCS-04: Commands/Skills Formatting

**File:** `.claude/docs/COMMANDS_SKILLS_STYLE_GUIDE.md`

**Standards:**
- Consistent header format
- Required sections: Purpose, Usage, Examples
- Exit code documentation

## S4-DOCS-05: Core Documentation

**Files created:**
- `documentation/automation-map.md` - This file
- `documentation/recovery-playbook.md` - Recovery commands
- `.claude/docs/AGENT_AUTOLOOP_ETIQUETTE.md` - Agent rules

---

# Sprint 5: Observability & CI

Comprehensive monitoring, alerting, and CI integration.

## Architecture

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                          OBSERVABILITY STACK                                │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  ┌──────────────────┐    ┌──────────────────┐    ┌──────────────────────┐  │
│  │ run-focused-     │───▶│ metrics-sink.sh  │───▶│ autoloop.jsonl       │  │
│  │ tests.sh         │    │ (append metrics) │    │ (per-run data)       │  │
│  └──────────────────┘    └──────────────────┘    └──────────────────────┘  │
│           │                      │                         │               │
│           ▼                      ▼                         ▼               │
│  ┌──────────────────┐    ┌──────────────────┐    ┌──────────────────────┐  │
│  │ alerts.sh        │    │ failure-report.sh│    │ failures/*.md        │  │
│  │ (threshold check)│    │ (parse failures) │    │ (briefs + RCA)       │  │
│  └──────────────────┘    └──────────────────┘    └──────────────────────┘  │
│           │                                                │               │
│           ▼                                                ▼               │
│  ┌──────────────────┐                            ┌──────────────────────┐  │
│  │ make-support-    │                            │ rca-latest.md        │  │
│  │ bundle.sh        │◀───────────────────────────│ (escalation draft)   │  │
│  └──────────────────┘                            └──────────────────────┘  │
│                                                                             │
├─────────────────────────────────────────────────────────────────────────────┤
│                          VISUALIZATION                                      │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  ┌──────────────────┐    ┌──────────────────┐    ┌──────────────────────┐  │
│  │ generate-queue-  │───▶│ badges/*.svg     │    │ dashboard.md         │  │
│  │ badge.sh         │    │ (progress badge) │    │ (jq recipes)         │  │
│  └──────────────────┘    └──────────────────┘    └──────────────────────┘  │
│                                                           │                 │
│                                                           ▼                 │
│                                                  ┌──────────────────────┐  │
│                                                  │ metrics-dashboard.   │  │
│                                                  │ html (optional)      │  │
│                                                  └──────────────────────┘  │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

## S5-OBS-01: Structured Iteration Logs

**Files:**
- `scripts/lib/iteration-logger.sh` - Logging library
- `test-results/logs/iterations.jsonl` - Append-only iteration log
- `test-results/logs/autoloop-summary.json` - Component summaries

**Log format:**
```json
{
  "timestamp": "2025-12-18T19:30:00+00:00",
  "component_id": "unit:FooTest",
  "iteration": 1,
  "test_outcome": "pass|fail",
  "lint_status": "pass|fail|skip",
  "files_modified": ["app/Services/Foo.php"],
  "notes": "Added validation tests"
}
```

## S5-OBS-02: Metrics Sink

**Files:**
- `scripts/lib/metrics-sink.sh` - Metrics recording library
- `test-results/metrics/autoloop.jsonl` - Append-only metrics (JSONL) **[Not yet created - reserved for future use]**

**Usage:**
```bash
source scripts/lib/metrics-sink.sh
record_test_run "unit:FooTest" "pass" 12 "search_services" 0 "" 5 3
```

**Metrics record format:**
```json
{
  "timestamp": "2025-12-18T19:30:00+00:00",
  "date": "2025-12-18",
  "component_id": "unit:FooTest",
  "outcome": "pass|fail|error|skip",
  "duration_seconds": 12,
  "domain": "search_services",
  "retry_count": 0,
  "retry_reason": "",
  "tests_run": 5,
  "assertions": 12
}
```

## S5-OBS-03: Alert Thresholds

**Files:**
- `scripts/lib/alerts.sh` - Threshold checking library
- `test-results/alert-thresholds.json` - Configurable thresholds

**Thresholds:**
| Alert | Default | Trigger |
|-------|---------|---------|
| Consecutive failures | 3 | Same component fails 3+ times in a row |
| Component streak | 5 | Component fails 5+ times total |
| Duration spike | 300s | Single run exceeds 5 minutes |
| Hourly failure rate | 50% | >50% of runs in past hour failed |

**Usage:**
```bash
source scripts/lib/alerts.sh
check_consecutive_failures "unit:FooTest"  # Returns 0 if OK, 1 if alert
check_all_thresholds "unit:FooTest"        # Runs all checks
```

## S5-OBS-04: Failure Briefs & RCA

**Files:**
- `scripts/post-run-failure-report.sh` - Failure brief generator
- `test-results/failures/<component>.md` - Per-component failure briefs
- `test-results/failures/rca-latest.md` - RCA escalation draft (on threshold)

**Usage:**
```bash
./scripts/post-run-failure-report.sh "unit:FooTest" 1 "Error message here"
```

**Failure brief contains:**
- Component ID and timestamp
- Test output summary
- Recent history from metrics
- Suggested investigation steps

**RCA draft generated when:**
- Same component fails 3+ consecutive times
- Contains structured template for root cause analysis

## S5-OBS-05: Support Bundle

**Files:**
- `scripts/make-support-bundle.sh` - One-click debug bundle generator
- `test-results/support-bundles/*.tar.gz` - Generated bundles

**Usage:**
```bash
./scripts/make-support-bundle.sh "investigating FooTest failures"
```

**Bundle contains:**
- Recent logs (iterations.jsonl, autoloop.jsonl last 1000 lines)
- Queue snapshot (tdd-test-queue.json)
- Session notes (session-state.md)
- Failure briefs (failures/*.md)
- Matrix output (.last-run.json)
- Environment info (PHP version, etc.)

**Auto-generation:**
Alerts.sh automatically creates a support bundle when consecutive failure threshold is exceeded.

## S5-CI-01: CI Shadow Mode

**File:** `.github/workflows/autoloop-shadow.yml`

**Purpose:** Nightly autoloop run in read-only mode (no commits, no push)

**Schedule:** 2:00 AM UTC daily

**What it does:**
1. Sets up environment (PHP, Node, PostgreSQL, Neo4j)
2. Runs 1-2 components from TDD queue
3. Collects all artifacts
4. Uploads for human review

**Artifacts uploaded:**
| Artifact | Contents |
|----------|----------|
| `iteration-logs` | `test-results/logs/iterations.jsonl` |
| `metrics` | `test-results/metrics/autoloop.jsonl` |
| `queue-snapshot` | `test-results/tdd-test-queue.json` |
| `failure-briefs` | `test-results/failures/*.md` |
| `support-bundle` | `test-results/support-bundles/*` |
| `test-logs` | `test-logs/*` |

**Manual trigger:**
```bash
gh workflow run autoloop-shadow.yml
```

## S5-CI-02: CI Enforcement

**Purpose:** Fail fast when env drift or queue schema drift occurs.

**Integration:** Added to CI workflows and shadow workflow.

**Checks:**
```bash
./scripts/check-matrix.sh              # Environment validation
php scripts/validate-test-queue.php --strict   # Queue schema
php scripts/validate-test-queue.php --diff-check  # PR context
```

**Behavior:**
- Blocks broken environments immediately
- Blocks queue schema drift
- Logs clearly indicate what failed and remediation

## S5-VIS-01: Queue Progress Badge

**Files:**
- `scripts/generate-queue-badge.sh` - SVG badge generator
- `documentation/badges/queue-progress.svg` - Compact badge
- `documentation/badges/queue-progress-detailed.svg` - Detailed badge

**Usage:**
```bash
./scripts/generate-queue-badge.sh
./scripts/generate-queue-badge.sh --detailed
```

**Badge shows:**
- Done/Total count
- Completion percentage
- Color coding (red <25%, orange <50%, yellow <75%, green ≥75%)

## S5-VIS-02: Dashboard

**Files:**
- `documentation/dashboard.md` - jq recipes for local viewing
- `scripts/generate-dashboard-html.sh` - Optional HTML dashboard
- `documentation/metrics-dashboard.html` - Generated HTML

**jq recipes for:**
- Queue status breakdown
- Sprint burndown
- Domain progress
- Failure hotspots
- Duration trends
- Retry analysis

**See:** `documentation/dashboard.md` for complete recipe collection

---

# Human Oversight

## Tracked Artifacts

Execution artifacts are committed to git for human oversight and review:

| Location | Contents | Purpose |
|----------|----------|---------|
| `test-results/logs/*.jsonl` | Iteration logs, summaries | Execution history |
| `test-results/metrics/*.jsonl` | Per-run metrics | Trend analysis |
| `test-results/failures/*.md` | Failure briefs, RCA drafts | Issue investigation |
| `test-results/support-bundles/*` | Debug bundles | Escalation artifacts |
| `test-logs/*` | Raw test output | Detailed debugging |

**Rationale:**
- Enables human review of AI-generated work
- Provides audit trail for TDD progress
- Supports debugging and RCA without live session
- Can be relaxed once trust is established

## Session End Protocol

Every session should commit:
1. Code changes (tests, implementations)
2. Queue updates (status, iterations)
3. Execution artifacts (logs, metrics, failure briefs)

---

# Quick Reference

## Commands

| Command | Description |
|---------|-------------|
| `/check-setup` | Check environment health |
| `/refs` | Show reference index |
| `/tdd-multi-component-autoloop` | Start TDD autoloop |
| `./scripts/run-focused-tests.sh X` | Run focused tests |
| `./scripts/seed-focused-fixtures.sh --domain X` | Seed fixtures for domain |

## Supporting Files

| File | Purpose |
|------|---------|
| `test-results/tdd-test-queue.json` | Component queue with status |
| `test-results/flaky-tests.json` | Flaky test registry + retry policies |
| `test-results/context-budget.json` | Context budget tracking |
| `test-results/matrix-baseline.json` | Test matrix baseline |
| `database/seeders/fixture-map.json` | Domain-to-seeder mapping |

---

## Related Documentation

- `.claude/docs/HOOK_ENTRYPOINTS.md` - Hook configuration details
- `.claude/docs/AGENT_AUTOLOOP_ETIQUETTE.md` - Agent rules and etiquette
- `.claude/docs/REFERENCE_INDEX.md` - Full documentation index
- `documentation/recovery-playbook.md` - Recovery commands for failures
- `documentation/dashboard.md` - Local metrics viewing (jq recipes)
- `documentation/queue-breadcrumbs.md` - Queue schema decisions

---

# Script Reference

## Shell Scripts (TDD Automation)

| Script | Purpose | When to Use |
|--------|---------|-------------|
| `scripts/run-focused-tests.sh` | Run tests for specific component | Primary test runner for TDD |
| `scripts/check-setup.sh` | Non-destructive health check | Verify environment before work |
| `scripts/check-matrix.sh` | Environment matrix validation | Detect env drift, missing prereqs |
| `scripts/setup-all.sh` | Full environment setup | Initial setup, recovery |
| `scripts/setup-neo4j.sh` | Neo4j database setup | Graph database initialization |
| `scripts/seed-focused-fixtures.sh` | Seed fixtures by domain | Before running domain-specific tests |
| `scripts/run-quality-gates.sh` | Lint/format checks | Before marking component done |
| `scripts/tdd-queue-digest.sh` | Generate queue summary | View current queue status |
| `scripts/generate-active-component-context.sh` | Generate active context | SessionStart injection |
| `scripts/autoloop-plan.sh` | Dry-run autoloop planning | Preview what autoloop would do |
| `scripts/autoloop-resume.sh` | Resume paused autoloop | Continue after pause |
| `scripts/append-session-notes.sh` | Append session notes | Auto-called by autoloop |

## Shell Scripts (Observability)

| Script | Purpose | When to Use |
|--------|---------|-------------|
| `scripts/post-run-failure-report.sh` | Generate failure briefs | After test failures |
| `scripts/make-support-bundle.sh` | Create debug bundle | For escalation/debugging |
| `scripts/generate-queue-badge.sh` | Generate SVG badge | Update progress visualization |
| `scripts/generate-dashboard-html.sh` | Generate HTML dashboard | Local metrics viewing |
| `scripts/iteration-log-query.sh` | Query iteration logs | Analyze test history |
| `scripts/validate-agent-logs.sh` | Validate agent logging | After dispatching parallel agents |

## Shell Script Libraries (`scripts/lib/`)

| Library | Purpose | How to Use |
|---------|---------|------------|
| `scripts/lib/metrics-sink.sh` | Record test metrics | `source` and call `record_test_run` |
| `scripts/lib/alerts.sh` | Threshold checking | `source` and call `check_*` functions |
| `scripts/lib/iteration-log.sh` | Log iteration data | `source` and call logging functions |
| `scripts/lib/autoloop-exit-codes.sh` | Standard exit codes | `source` for exit code constants |
| `scripts/lib/autoloop-controls.sh` | Autoloop control logic | `source` for control functions |
| `scripts/lib/autoloop-pause.sh` | Pause/resume controls | `source` for pause state management |

## PHP Scripts

| Script | Purpose | When to Use |
|--------|---------|-------------|
| `scripts/validate-test-queue.php` | Validate queue schema | Before commits, CI checks |
| `scripts/queue-update.php` | Safe queue updates | Update component status/iterations |
| `scripts/add-queue-component.php` | Scaffold new component | Adding new test components |
| `scripts/metrics-summary.php` | Metrics analysis | View trends: `--since 7d` |
| `scripts/generate-tdd-queue.php` | Generate queue digest | Internal queue processing |
| `scripts/build-tdd-queue.php` | Build queue structure | Initial queue creation |

## Claude Commands (`.claude/commands/`)

| Command | Purpose | When to Use |
|---------|---------|-------------|
| `/check-setup` | Environment health check | Start of session, troubleshooting |
| `/refs` | Show documentation index | Find relevant documentation |
| `/tdd-multi-component-autoloop` | Multi-component TDD session | Main TDD workflow (human-operated) |
| `/tdd-vector-store-manager-autoloop` | VectorStoreManager TDD | Focused VectorStore TDD |
| `/resume` | Resume interrupted work | Continue from previous session |
| `/brainstorm` | Interactive design refinement | Before implementing features |
| `/write-plan` | Create implementation plan | Planning complex features |
| `/execute-plan` | Execute plan with checkpoints | Step-by-step implementation |

## Agent Prompts (`.claude/agents/`)

| Agent | Purpose | When to Use |
|-------|---------|-------------|
| `tdd-tall-specialist.md` | Strict TDD for TALL stack | Dispatched by autoloop command |
| `code-reviewer.md` | Senior code review | After completing major features |

---

# Task Index

Quick lookup of all sprint tasks by ID:

## Sprint 1: Foundation
- S1-STARTUP-01: Hook entrypoints audit
- S1-DOCS-01: Session state handoff template
- S1-STARTUP-02: SessionStart primer injection
- S1-SETUP-01: Setup wrapper guardrails
- S1-SETUP-02: Persist setup status
- S1-SETUP-03: /check-setup command
- S1-SETUP-04: Safety rails for destructive scripts
- S1-SETUP-05: Environment matrix check
- S1-SETUP-06: Wire matrix into setup

## Sprint 2: Queue Management
- S2-QUEUE-00: Queue breadcrumbs decision
- S2-QUEUE-01: TDD queue digest generator
- S2-STARTUP-01: Queue digest injection
- S2-CTX-01: Active component context
- S2-QUEUE-02: Breadcrumb convention
- S2-QUEUE-03: Queue schema validator
- S2-QUEUE-04: Pre-commit queue guard
- S2-QUEUE-05: Component scaffolding

## Sprint 3: Autoloop Controls
- S3-AUTO-01: Safe queue update helper
- S3-AUTO-02: Automatic status transitions
- S3-AUTO-03: Dry-run/plan mode
- S3-AUTO-04: Standard exit codes
- S3-QA-01: Quality gates
- S3-OBS-01: Session notes auto-append
- S3-AUTO-05: Timeboxing controls
- S3-AUTO-06: Human-in-the-loop pause/resume

## Sprint 4: Testing & Documentation
- S4-TEST-01: Adaptive test selection
- S4-FIX-01: Focused fixture seeding
- S4-STARTUP-01: Context size budgeting
- S4-DOCS-01: Token-friendly reference access
- S4-DOCS-02: Command executive summaries
- S4-DOCS-03: TDD specialist operative checklist
- S4-DOCS-04: Commands/skills formatting
- S4-DOCS-05: Core documentation

## Sprint 5: Observability & CI
- S5-OBS-01: Structured iteration logs
- S5-OBS-02: Metrics sink
- S5-OBS-03: Alert thresholds
- S5-OBS-04: Failure briefs & RCA
- S5-OBS-05: Support bundle
- S5-CI-01: CI shadow mode
- S5-CI-02: CI enforcement
- S5-VIS-01: Queue progress badge
- S5-VIS-02: Dashboard

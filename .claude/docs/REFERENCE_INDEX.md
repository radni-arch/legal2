# Reference Index

Quick navigation to all reference documentation. Open these when you need full details.

## Quick Commands

| Command | Purpose |
|---------|---------|
| `/refs` | Print this index |
| `/check-setup` | Check environment status |
| `/resume` | Resume from previous session |
| `/brainstorm` | Interactive design refinement |
| `/write-plan` | Create implementation plan |
| `/execute-plan` | Execute plan with checkpoints |

## Core References

| Document | Purpose | When to Read |
|----------|---------|--------------|
| `.claude/CLAUDE.md` | Project overview, standards | Starting new work |
| `.claude/Agents.md` | Agent configurations | Dispatching subagents |
| `.claude/session-state.md` | Session handoff notes | Session start/end |

## Autoloop Documentation

| Document | Purpose |
|----------|---------|
| `documentation/automation-map.md` | System overview with all moving pieces |
| `documentation/dashboard.md` | jq recipes for local metrics viewing |
| `.claude/docs/AGENT_AUTOLOOP_ETIQUETTE.md` | Agent rules and queue editing |
| `.claude/commands/tdd-multi-component-autoloop.md` | Full TDD autoloop reference |
| `documentation/recovery-playbook.md` | Copy/paste recovery commands |

Key sections in autoloop command:
- Queue file format and component fields
- TDD slice workflow (RED → GREEN → REFACTOR)
- Timebox and iteration controls
- Pause/resume system
- Bounded test retries
- Focused fixture seeding
- Exit codes reference

## Agent Prompts

| Agent | File | Purpose |
|-------|------|---------|
| code-reviewer | `.claude/agents/code-reviewer.md` | Code review after major changes |
| tdd-tall-specialist | `.claude/agents/tdd-tall-specialist.md` | TDD autoloop agent |
| tall-specialist | `.claude/agents/tall-specialist.md` | Livewire/Alpine expert |
| performance-specialist | `.claude/agents/performance-specialist.md` | Performance optimization |

## Skills

Skills are in `.claude/skills/<name>/SKILL.md`. Key skills:

| Skill | Purpose |
|-------|---------|
| `brainstorming` | Refine ideas into designs |
| `test-driven-development` | RED → GREEN → REFACTOR |
| `systematic-debugging` | Four-phase debugging framework |
| `verification-before-completion` | Evidence before assertions |
| `writing-plans` | Create implementation plans |
| `executing-plans` | Execute plans with checkpoints |

## Scripts Reference

| Script | Purpose |
|--------|---------|
| `scripts/run-focused-tests.sh` | TDD test runner with retries |
| `scripts/iteration-log-query.sh` | Query iteration logs (what failed last?) |
| `scripts/metrics-summary.php` | Metrics trends: `php scripts/metrics-summary.php --since 7d` |
| `scripts/post-run-failure-report.sh` | Generate failure briefs + RCA drafts |
| `scripts/make-support-bundle.sh` | Create debug bundle for escalations |
| `scripts/generate-queue-badge.sh` | Generate SVG badge showing queue progress |
| `scripts/generate-dashboard-html.sh` | Generate HTML metrics dashboard |
| `scripts/seed-focused-fixtures.sh` | Component-scoped seeding |
| `scripts/autoloop-resume.sh` | Resume paused autoloop |
| `scripts/run-quality-gates.sh` | Lint/format gates |
| `scripts/append-session-notes.sh` | Session note logging |

## Test Infrastructure

| File | Purpose |
|------|---------|
| `test-results/tdd-test-queue.json` | Component work queue |
| `test-results/logs/iterations.jsonl` | Structured iteration logs (JSONL) |
| `test-results/logs/autoloop-summary.json` | Rollup summary per component |
| `test-results/metrics/autoloop.jsonl` | Append-only metrics for trend analysis |
| `test-results/failures/<component>.md` | Per-component failure briefs |
| `test-results/failures/rca-latest.md` | RCA draft (on threshold) |
| `test-results/support-bundles/*.zip` | Debug bundles for escalations |
| `test-results/alert-thresholds.json` | Alert thresholds config (see below) |
| `test-results/flaky-tests.json` | Known flaky test registry |
| `database/seeders/fixture-map.json` | Domain-to-seeder mapping |

## Telemetry Alerts

Alerts trigger on threshold violations. Configure in `test-results/alert-thresholds.json`:

| Threshold | Default | Env Override |
|-----------|---------|--------------|
| Consecutive failures | 3 | `ALERT_CONSECUTIVE_FAILURES` |
| Component streak | 2 | `ALERT_COMPONENT_STREAK` |
| Duration multiplier | 3.0x | `ALERT_DURATION_MULTIPLIER` |
| Hourly failure rate | 50% | `ALERT_HOURLY_FAILURE_RATE` |

## CI Workflows

| Workflow | Purpose |
|----------|---------|
| `.github/workflows/autoloop-shadow.yml` | Nightly shadow autoloop (no commits, artifacts only) |
| `.github/workflows/tests.yml` | Unit + Feature tests on push/PR |
| `.github/workflows/e2e-tests.yml` | End-to-end browser tests |

## Hook Configuration

| File | Purpose |
|------|---------|
| `.claude/hooks/setup-wrapper.sh` | SessionStart hook |
| `.claude/settings.json` | Claude Code settings |

---

**Note:** This index is kept compact. Open the linked files for full documentation.

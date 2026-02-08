# Agent Autoloop Etiquette

Rules and conventions for agents running in the TDD autoloop system.

---

## Quick Reference

| Item | Path/Command |
|------|--------------|
| Test runner | `./scripts/run-focused-tests.sh <TestClass>` |
| Queue file | `test-results/tdd-test-queue.json` |
| Last run | `test-results/.last-run.json` |
| Agent logs | `.claude/logs/agents/YYYY-MM-DD/` |
| Session state | `.claude/session-state.md` |
| Setup status | `test-results/setup-status.json` |

---

## 1. Test Execution Rules

### Always Use the Canonical Runner

```bash
./scripts/run-focused-tests.sh <TestClass>
```

**Never use directly:**
- `php artisan test` (bypasses logging)
- `vendor/bin/phpunit` (no retry logic)

### Retry Policy

The runner applies retries based on `test-results/flaky-tests.json`:
- Known flaky tests: 1 retry
- Dusk tests: 1 retry with stabilization flags
- DB connection errors: 1 retry with cache clear
- Hard cap: 2 retries maximum

### Log Locations

| Type | Location |
|------|----------|
| Test output | `test-logs/<TestClass>-<timestamp>.log` |
| Last run summary | `test-results/.last-run.json` |
| Full session log | `/tmp/setup-all-*.log` |

---

## 2. Queue Editing Rules

### Mutable Fields

Agents MAY modify these fields in `components[]`:

```json
{
  "status": "todo|in_progress|done",
  "iterations": <number>
}
```

### Immutable Fields

Agents MUST NOT modify:
- `id`
- `sprint`
- `domain`
- `type`
- `test_class`
- `test_path`
- `related_tests`

### Immutable Sections

Agents MUST NOT modify these top-level sections:
- `stats`
- `sprint_guide`
- `visual_groupings`
- `meta`

### Status Transitions

```
todo → in_progress (when starting work)
in_progress → done (when TDD complete)
in_progress → todo (if abandoning without completion)
```

### Iterations

- Increment by number of TDD slices completed
- Never decrement
- Track even partial progress

---

## 3. Startup Hook Expectations

### What Agents Receive

The SessionStart hook injects:

```
Setup: OK @ <timestamp>
db:ok mig:ok seed:ok comp:ok neo4j:ok
Active: **<component_id>** [<status>]
TDD Queue (top 3 of X in_progress + Y todo, Z done):
...
```

### Interpreting Readiness

| Status | Meaning | Action |
|--------|---------|--------|
| `ok` | Service ready | Proceed normally |
| `fail` | Service failed | Check logs, may need recovery |
| `skip` | Not required | Ignore |

### Exit Codes

| Code | Meaning |
|------|---------|
| `0` | Setup successful, proceed |
| `1` | Setup failed, check `/check-setup` |

### Environment Not Ready?

If `fail` status appears:
1. Run `/check-setup` for details
2. Check `/tmp/setup-all-*.log`
3. See `documentation/recovery-playbook.md` for fixes

---

## 4. Metrics and Notes Locations

### Test Results

```
test-results/
├── tdd-test-queue.json      # Queue state
├── .last-run.json           # Last test execution
├── setup-status.json        # Environment readiness
├── flaky-tests.json         # Flaky test registry
├── context-budget.json      # Context size tracking
└── matrix-baseline.json     # Test matrix baseline
```

### Agent Logs

```
.claude/logs/agents/
└── YYYY-MM-DD/
    ├── tdd-tall-specialist-<session>.log
    ├── code-reviewer-<session>.log
    └── summary.json
```

### Log Entry Format

```json
{
  "timestamp": "2025-12-18T10:30:00Z",
  "agent_type": "tdd-tall-specialist",
  "session_id": "abc123",
  "phase": "execution",
  "action": "RED: Added validation test",
  "reasoning": "Edge case not covered",
  "outcome": "success",
  "next_steps": "Implement minimal fix"
}
```

---

## 5. TDD Cycle Protocol

### RED Phase

1. Modify/add tests in `test_path`
2. Run: `./scripts/run-focused-tests.sh <test_class>`
3. Verify: At least one test fails
4. If passes: Strengthen assertions

### GREEN Phase

1. Write minimum code to pass
2. Run: `./scripts/run-focused-tests.sh <test_class>`
3. Iterate until green

### REFACTOR Phase

1. Clean up code (tests must stay green)
2. Run tests again to verify
3. No behavior changes

### After Each Slice

1. Update queue: increment `iterations`
2. Update `status` if appropriate
3. Log completion

---

## 6. Session Handoff

### Before Ending Session

1. Update `.claude/session-state.md`:
   - Current component
   - Progress (slices completed)
   - Blockers or notes
   - Suggested next steps

2. Commit queue changes:
   ```bash
   git add test-results/tdd-test-queue.json
   git commit -m "TDD: Update queue state"
   ```

3. Push to branch:
   ```bash
   git push -u origin <branch>
   ```

### Session State Format

```markdown
# Session State

## Current Work
- Component: unit:SomeTest
- Sprint: 1
- Progress: 2/3 slices complete

## Last Action
- Completed RED phase for validation test
- Tests failing as expected

## Blockers
- None

## Next Steps
1. Implement GREEN phase
2. Run refactor pass
3. Update queue status to done
```

---

## 7. Prohibitions

Agents MUST NOT:

- Write production code before failing test
- Weaken assertions to pass tests
- Delete or comment out tests
- Modify CI workflows without instruction
- Skip RED phase verification
- Claim completion without running tests
- Modify immutable queue fields/sections
- Push to main/master branch

---

## Related Documentation

- `documentation/automation-map.md` - System overview
- `.claude/agents/tdd-tall-specialist.md` - TDD agent prompt
- `.claude/docs/HOOK_ENTRYPOINTS.md` - Hook configuration
- `documentation/recovery-playbook.md` - Recovery commands

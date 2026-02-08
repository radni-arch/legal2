---
description: Resume TDD work from previous session
---

# /resume

## Quick Reference

| Item | Path |
|------|------|
| Session state | `.claude/session-state.md` |
| Queue file | `test-results/tdd-test-queue.json` |
| Last run | `test-results/.last-run.json` |
| Setup status | `test-results/setup-status.json` |

## Resume Flow

Execute these steps to resume work from a previous session:

### Step 1: Check Git Status

```bash
git status
git log --oneline -5
```

Verify:
- Working tree is clean (or understand uncommitted changes)
- You're on the correct branch
- Recent commits match expected state

### Step 2: Check Environment Setup

```bash
cat test-results/setup-status.json | jq '.overall, .subsystems | to_entries[] | "\(.key): \(.value.status)"'
```

Or use the command: `/check-setup`

Expected: All subsystems `ok`. If any show `fail`, see recovery playbook.

### Step 3: Read Session State

```bash
cat .claude/session-state.md
```

Understand:
- What component was being worked on
- Progress (slices completed)
- Any blockers or notes
- Suggested next steps

### Step 4: Check Queue Status

```bash
cat test-results/tdd-test-queue.json | jq '.components[] | select(.status == "in_progress") | {id, status, iterations}'
```

Find any `in_progress` components to continue.

### Step 5: Rerun Last Focused Test

```bash
# Get last test target
cat test-results/.last-run.json | jq -r '.target'

# Rerun it
./scripts/run-focused-tests.sh <target>
```

Verify:
- Tests pass (if you left them green)
- Or tests fail (if mid-RED phase)
- Environment is working

### Step 6: Continue Work

Based on session state and queue, either:
- Continue current component's TDD cycle
- Start next `todo` component
- Use `/tdd-multi-component-autoloop` for structured work

## Quick Resume Checklist

1. [ ] `git status` - Working tree state
2. [ ] `/check-setup` - Environment health
3. [ ] Read `.claude/session-state.md` - Context
4. [ ] Check `in_progress` queue items
5. [ ] Rerun last test - Verify state
6. [ ] Continue or start fresh

## Common Issues

| Issue | Solution |
|-------|----------|
| Setup failed | See `documentation/recovery-playbook.md` |
| Uncommitted changes | Review, commit or stash |
| Tests failing unexpectedly | Check for environment drift, reseed |
| Queue state unclear | Read session-state.md, check git log |

## Related Commands

- `/check-setup` - Environment health check
- `/tdd-multi-component-autoloop` - Start TDD autoloop
- `/refs` - Documentation index

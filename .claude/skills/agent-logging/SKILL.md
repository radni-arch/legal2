---
name: agent-logging
description: Use when you are a subagent - MANDATORY protocol for logging progress and reasoning during agent execution. All subagents MUST use this skill.
---

# Agent Logging Protocol

**ALL SUBAGENTS MUST LOG THEIR PROGRESS AND REASONING.**

This is not optional. Logging enables:
- Orchestrator visibility into your execution
- Debugging when things go wrong
- Knowledge transfer between sessions
- Audit trail for decisions

## Setup

At the START of your session, create your log file:

```bash
# Create log directory and file
mkdir -p .claude/logs/agents/$(date +%Y-%m-%d)
```

Generate a session ID (use timestamp + random):
- Example: `1734350000-abc123`

Your log file: `.claude/logs/agents/YYYY-MM-DD/{agent-type}-{session-id}.log`

## Log Entry Format

Each log entry is a **single JSON line** appended to your log file:

```json
{"timestamp":"2025-12-16T10:30:00Z","agent_type":"code-reviewer","session_id":"abc123","phase":"investigation","action":"Reading VectorStoreManager.php","reasoning":"Need to understand current implementation before comparing to plan","outcome":"success","context":{"files_read":["app/Livewire/VectorStoreManager.php"]}}
```

### Required Fields

| Field | Description |
|-------|-------------|
| `timestamp` | ISO 8601 format |
| `agent_type` | Your agent type (code-reviewer, tdd-tall-specialist, etc.) |
| `session_id` | Unique session identifier |
| `phase` | Current phase (see below) |
| `action` | What you are doing |
| `reasoning` | WHY you are doing it |
| `outcome` | success, failure, or blocked |

### Optional Fields

| Field | Description |
|-------|-------------|
| `context` | Object with files_read, files_modified, tools_used |
| `findings` | What you discovered |
| `next_steps` | What comes next |
| `duration_ms` | How long the action took |

## Required Phases

You MUST log at each phase boundary:

### 1. initialization
Log when you start:
```json
{"phase":"initialization","action":"Starting code review session","reasoning":"User requested review of VectorStoreManager against plan","outcome":"success"}
```

### 2. investigation
Log each significant read/analysis:
```json
{"phase":"investigation","action":"Reading test file","reasoning":"Need to understand test coverage before reviewing implementation","outcome":"success","context":{"files_read":["tests/Feature/VectorStoreManagerTest.php"]}}
```

### 3. planning
Log your approach decisions:
```json
{"phase":"planning","action":"Focusing review on validation logic","reasoning":"Plan specifies strict validation requirements; this is highest risk area","outcome":"success"}
```

### 4. execution
Log each significant action:
```json
{"phase":"execution","action":"Identified missing null check","reasoning":"Line 45 accesses property without null guard, plan requires defensive coding","outcome":"success","context":{"issue_severity":"important"}}
```

### 5. verification
Log validation activities:
```json
{"phase":"verification","action":"Running focused tests","reasoning":"Confirming changes don't break existing functionality","outcome":"success","context":{"test_command":"./scripts/run-focused-tests.sh VectorStoreManager"}}
```

### 6. completion
Log final summary:
```json
{"phase":"completion","action":"Review complete","reasoning":"All planned areas reviewed, issues documented","outcome":"success","context":{"issues_found":{"critical":1,"important":3,"suggestions":5}}}
```

## Writing Logs

Use the **Write tool** or **Bash** to append entries:

### Via Write Tool
Read the existing log file (if any), append your entry, write back.

### Via Bash
```bash
echo '{"timestamp":"'$(date -Iseconds)'","agent_type":"code-reviewer","session_id":"abc123","phase":"investigation","action":"Reading file","reasoning":"Need context","outcome":"success"}' >> .claude/logs/agents/$(date +%Y-%m-%d)/code-reviewer-abc123.log
```

## What to Log (Examples)

### Decisions
```json
{"phase":"planning","action":"Decision: Using batch processing over streaming","reasoning":"Dataset size is 10k records; batch is more efficient. Streaming would be better for >100k.","outcome":"success","context":{"alternatives_considered":["streaming","single-record"]}}
```

### Blockers
```json
{"phase":"execution","action":"BLOCKED: Cannot find plan document","reasoning":"Orchestrator specified plan at docs/plans/vector-store.md but file doesn't exist","outcome":"blocked","context":{"resolution_needed":"Orchestrator must provide correct plan path"}}
```

### Tool Usage
```json
{"phase":"investigation","action":"Used Grep to find usages","reasoning":"Need to understand how VectorStoreManager is called throughout codebase","outcome":"success","context":{"tools_used":["Grep"],"pattern":"VectorStoreManager","matches":15}}
```

## Checklist

Before ending your session:

- [ ] Log file created with correct path
- [ ] initialization phase logged
- [ ] All investigation actions logged with reasoning
- [ ] Planning decisions logged
- [ ] All execution actions logged
- [ ] Verification results logged
- [ ] completion phase logged with summary
- [ ] Outcome clearly stated

## Anti-Patterns

**DON'T:**
- Skip logging "simple" actions
- Log WHAT without WHY
- Forget to log blockers
- Wait until end to log everything
- Use vague reasoning ("for context")

**DO:**
- Log at every phase transition
- Explain reasoning in detail
- Log blockers immediately
- Log as you go
- Be specific about WHY

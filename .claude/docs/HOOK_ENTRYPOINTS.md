# Hook Entrypoints and Command Triggers - Source of Truth

This document establishes the canonical paths for Claude Code hooks, setup scripts, and command triggers in the AI Legal War Machine project. Use these paths as the authoritative reference.

---

## Hook Configuration Overview

**Configuration file:** `.claude/hooks/hooks.json`

The hooks.json file defines hooks for five event types:
- **SessionStart** - Fires when session starts/resumes
- **PreToolUse** - Fires before a tool executes (can modify input)
- **PostToolUse** - Fires after a tool executes (can log usage)
- **SubagentStop** - Fires when a Task tool subagent completes
- **SessionEnd** - Fires when session terminates

---

## Logging System Architecture

The project implements a comprehensive logging system with two modes:

| Mode | Description | Hooks Used |
|------|-------------|------------|
| **Option A** (boundary-only) | Log session start/end only | SessionStart + SessionEnd |
| **Option D** (full audit) | Log every tool usage | All hooks (default) |

**To switch from Option D to Option A:** Remove the PostToolUse hook from hooks.json.

```
┌─────────────────────────────────────────────────────────────────┐
│                    FULL AUDIT LOGGING FLOW                      │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  SessionStart                                                   │
│  ├── orchestrator-init.sh → Initialize orchestrator log         │
│  │                                                              │
│  During Session                                                 │
│  ├── PostToolUse → Log each tool usage                          │
│  ├── PreToolUse (Task) → Inject logging into subagent prompts   │
│  └── SubagentStop → Validate subagent logs                      │
│                                                                 │
│  SessionEnd                                                     │
│  └── session-end.sh → Finalize logs, validate, commit to git    │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Log Artifacts

```
.claude/logs/
├── orchestrator/        # Main session logs
│   └── YYYY-MM-DD/
│       ├── orchestrator-{session-id}.log   # Session log (JSONL)
│       └── summary.json                     # Daily summary
└── agents/              # Subagent logs
    └── YYYY-MM-DD/
        ├── code-reviewer-{session-id}.log
        └── summary.json
```

**Logs are automatically committed to git on session end.**

---

## SessionStart Hook Configuration

The hooks.json file defines THREE SessionStart hooks that run on `startup|resume|clear|compact`:

### Hook 1: Superpowers Plugin Hook

| Property | Value |
|----------|-------|
| **Path** | `${CLAUDE_PLUGIN_ROOT}/hooks/session-start.sh` |
| **Actual file** | `.claude/hooks/session-start.sh` |
| **Purpose** | Injects superpowers skill content into session context |
| **Output** | JSON with `hookSpecificOutput.additionalContext` containing using-superpowers skill |

### Hook 2: Project Setup Hook

| Property | Value |
|----------|-------|
| **Path** | `${CLAUDE_PROJECT_DIR}/.claude/hooks/setup-wrapper.sh` |
| **Actual file** | `.claude/hooks/setup-wrapper.sh` |
| **Purpose** | Launches environment setup in background |
| **Output** | JSON with setup status message |
| **Calls** | `scripts/setup-all.sh` |

### Hook 3: Orchestrator Logging Init

| Property | Value |
|----------|-------|
| **Path** | `${CLAUDE_PROJECT_DIR}/.claude/hooks/orchestrator-init.sh` |
| **Purpose** | Initialize orchestrator session logging |
| **Output** | JSON with log file path |
| **Library** | Sources `.claude/lib/orchestrator-logging.sh` |

---

## PreToolUse Hook Configuration

Fires before a tool executes. Can modify input, allow, deny, or request permission.

### PreToolUse Hook: Task Logging Injection

| Property | Value |
|----------|-------|
| **Path** | `${CLAUDE_PROJECT_DIR}/.claude/hooks/pre-task-logging.sh` |
| **Matcher** | `Task` (only intercepts Task tool calls) |
| **Purpose** | Injects mandatory logging instructions into every subagent prompt |
| **Output** | JSON with `updatedInput` containing modified prompt |

**Behavior:**
- Intercepts all Task tool invocations
- Extracts original prompt and agent type
- Appends mandatory agent-logging protocol instructions
- Returns modified prompt with logging requirements
- Agent type placeholder replaced with actual type (e.g., `code-reviewer`)

**This is ENFORCEMENT, not just validation** - every subagent gets logging requirements injected automatically.

---

## PostToolUse Hook Configuration

Fires after a tool completes execution. Used for audit logging.

### PostToolUse Hook: Tool Usage Logging

| Property | Value |
|----------|-------|
| **Path** | `${CLAUDE_PROJECT_DIR}/.claude/hooks/post-tool-logging.sh` |
| **Matcher** | All tools (no matcher = all) |
| **Purpose** | Log every tool usage to orchestrator log |
| **Output** | JSON confirming logging |
| **Library** | Sources `.claude/lib/orchestrator-logging.sh` |

**Behavior:**
- Fires after every tool execution
- Extracts tool name and relevant target (file path, command, pattern, etc.)
- Logs to orchestrator session log
- Skips noisy tools (BashOutput, KillShell)

**This is what makes it "Option D" (full audit).** To switch to Option A, remove this hook from hooks.json.

---

## SubagentStop Hook Configuration

Fires when a subagent (Task tool) completes. Used to validate agent logging.

### SubagentStop Hook: Agent Log Validation

| Property | Value |
|----------|-------|
| **Path** | `${CLAUDE_PROJECT_DIR}/.claude/hooks/subagent-stop.sh` |
| **Purpose** | Validates that the completing agent created a log file |
| **Output** | JSON with logging status and warnings |

**Behavior:**
- Checks for recent log files (within 5 minutes)
- Warns if no logs found after agent completes
- Reports incomplete logs (missing completion phase)
- Throttled to avoid duplicate warnings (30 second cooldown)

---

## SessionEnd Hook Configuration

Fires when a Claude Code session terminates. Finalizes logging and validates.

### SessionEnd Hook: Finalize and Validate

| Property | Value |
|----------|-------|
| **Path** | `${CLAUDE_PROJECT_DIR}/.claude/hooks/session-end.sh` |
| **Purpose** | Finalize orchestrator log, validate all logs, commit to git |
| **Output** | JSON summary of all logging activity |
| **Library** | Sources `.claude/lib/orchestrator-logging.sh` |

**Behavior:**
1. Finalizes orchestrator logging (writes completion entry)
2. Validates all agent logs for the session
3. Validates orchestrator logs
4. Commits all logs to git automatically
5. Reports summary with counts and issues

---

## Logging Libraries

### Orchestrator Logging Library

| Property | Value |
|----------|-------|
| **Path** | `.claude/lib/orchestrator-logging.sh` |
| **Purpose** | Shared functions for orchestrator session logging |

**Functions:**
- `init_orchestrator_logging` - Create session log file
- `log_tool_use` - Log a tool usage
- `log_subagent_dispatch` - Log subagent dispatch
- `log_orchestrator_decision` - Log a decision
- `finalize_orchestrator_logging` - Close log and commit to git
- `get_orchestrator_session_info` - Get current session info

### Agent Logging Library

| Property | Value |
|----------|-------|
| **Path** | `.claude/lib/agent-logging.sh` |
| **Purpose** | Shared functions for subagent logging |

**Functions:**
- `init_agent_logging` - Create agent log file
- `log_phase` - Log a phase transition
- `log_decision` - Log a decision
- `log_blocker` - Log a blocker
- `finalize_agent_logging` - Close log

---

## Setup Scripts

### Primary Setup Wrapper

| Property | Value |
|----------|-------|
| **Canonical path** | `.claude/hooks/setup-wrapper.sh` |
| **Called by** | SessionStart hook (hooks.json) |
| **Calls** | `scripts/setup-all.sh` |
| **Log file** | `/tmp/setup-all-hook.log` |
| **Lock file** | `/tmp/setup-all.lock` |

**Note:** There is NO `scripts/setup-wrapper.sh` file. The actual setup wrapper lives at `.claude/hooks/setup-wrapper.sh`.

### Main Setup Script

| Property | Value |
|----------|-------|
| **Canonical path** | `scripts/setup-all.sh` |
| **Called by** | `.claude/hooks/setup-wrapper.sh` |
| **Purpose** | Sets up PostgreSQL, Neo4j, Composer, migrations, Chrome |
| **Log file** | `/tmp/setup-all-*.log` |

---

## Command Triggers

Commands are defined in `.claude/commands/` as Markdown files. The trigger name is the filename without extension, prefixed with `/`.

### Available Commands

| Trigger | File | Description |
|---------|------|-------------|
| `/brainstorm` | `.claude/commands/brainstorm.md` | Interactive design refinement using Socratic method |
| `/check-setup` | `.claude/commands/check-setup.md` | Check environment setup status |
| `/execute-plan` | `.claude/commands/execute-plan.md` | Execute plan in batches with review checkpoints |
| `/write-plan` | `.claude/commands/write-plan.md` | Create detailed implementation plan with bite-sized tasks |
| `/tdd-multi-component-autoloop` | `.claude/commands/tdd-multi-component-autoloop.md` | TDD autoloop across multiple components |
| `/tdd-vector-store-manager-autoloop` | `.claude/commands/tdd-vector-store-manager-autoloop.md` | TDD autoloop for VectorStoreManager component |

---

## Test Runners

| Script | Purpose | Usage |
|--------|---------|-------|
| `scripts/run-focused-tests.sh` | Run focused PHPUnit tests | `./scripts/run-focused-tests.sh <TestClass>` |
| `scripts/run-tests-with-analysis.sh` | Full suite with analysis | `./scripts/run-tests-with-analysis.sh` |

---

## File Inventory

### Hook Files (`.claude/hooks/`)

```
.claude/hooks/
├── hooks.json            # Hook configuration (all hook types)
├── session-start.sh      # SessionStart: Superpowers plugin hook
├── setup-wrapper.sh      # SessionStart: Project setup wrapper
├── orchestrator-init.sh  # SessionStart: Initialize orchestrator logging
├── pre-task-logging.sh   # PreToolUse: Inject logging into Task prompts
├── post-tool-logging.sh  # PostToolUse: Log every tool usage
├── subagent-stop.sh      # SubagentStop: Agent log validation
└── session-end.sh        # SessionEnd: Finalize logs and commit
```

### Library Files (`.claude/lib/`)

```
.claude/lib/
├── orchestrator-logging.sh  # Orchestrator session logging functions
└── agent-logging.sh         # Subagent logging functions
```

### Log Files (`.claude/logs/`)

```
.claude/logs/
├── orchestrator/            # Main session logs (committed to git)
│   └── YYYY-MM-DD/
│       ├── orchestrator-{session}.log
│       └── summary.json
└── agents/                  # Subagent logs (committed to git)
    └── YYYY-MM-DD/
        ├── {agent-type}-{session}.log
        └── summary.json
```

---

## Quick Reference Table

| Purpose | Canonical Path |
|---------|----------------|
| Hook configuration | `.claude/hooks/hooks.json` |
| SessionStart hook (superpowers) | `.claude/hooks/session-start.sh` |
| Setup wrapper | `.claude/hooks/setup-wrapper.sh` |
| Orchestrator init hook | `.claude/hooks/orchestrator-init.sh` |
| PreToolUse hook (Task logging injection) | `.claude/hooks/pre-task-logging.sh` |
| PostToolUse hook (tool usage logging) | `.claude/hooks/post-tool-logging.sh` |
| SubagentStop hook (agent validation) | `.claude/hooks/subagent-stop.sh` |
| SessionEnd hook (finalize and commit) | `.claude/hooks/session-end.sh` |
| Orchestrator logging library | `.claude/lib/orchestrator-logging.sh` |
| Agent logging library | `.claude/lib/agent-logging.sh` |
| Agent log validation script | `scripts/validate-agent-logs.sh` |
| Main setup script | `scripts/setup-all.sh` |
| Focused test runner | `scripts/run-focused-tests.sh` |
| TDD queue file | `test-results/tdd-test-queue.json` |

---

## Deprecation Notes

- **No `scripts/setup-wrapper.sh`**: This file does not exist. The setup wrapper is at `.claude/hooks/setup-wrapper.sh`.
- **No directory-based hooks**: SessionStart hooks are script-based (`.sh` files), not directory-based.

---

*Last updated: 2025-12-20*
*Audit task: S1-STARTUP-01*
*Agent logging enforcement: S5-OBS-03*
*Orchestrator logging: Option D full audit*

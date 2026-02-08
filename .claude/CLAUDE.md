# AI Legal War Machine - Claude Code System Prompt

You are an AI orchestrator for the **AI Legal War Machine** project - a sophisticated Laravel TALL stack (Tailwind, Alpine.js, Livewire, Laravel) application for AI-powered legal research, document analysis, and evidence management.

---

## Project Overview

This application provides:
- **Document Processing**: AWS Textract integration for OCR and document extraction
- **Knowledge Management**: Vector stores, embeddings, and semantic search via Meilisearch
- **Graph Database**: Neo4j integration for relationship mapping and case analysis
- **AI Agents**: Research agents, legal reasoning, decision discovery
- **Real-time Features**: WebSocket-powered chat, live updates via Laravel Reverb
- **Court Integration**: External API integrations for court data

---

## Mandatory Skill Usage

Before ANY task, you MUST:

1. **Check Available Skills** - Review the skills listed in `.claude/skills/`
2. **Use Relevant Skills** - If a skill matches your task, use it via the `Skill` tool
3. **Announce Usage** - State which skill you're using before proceeding
4. **Follow Exactly** - Skills are battle-tested processes; don't shortcut them

### Core Skills

| Skill | When to Use |
|-------|-------------|
| `brainstorming` | Before coding any new feature - refine ideas into designs |
| `writing-plans` | Create implementation plans with bite-sized tasks |
| `executing-plans` | Execute plans in batches with review checkpoints |
| `test-driven-development` | RED → GREEN → REFACTOR for all implementations |
| `systematic-debugging` | Any bug, failure, or unexpected behavior |
| `verification-before-completion` | Before claiming work is done |
| `requesting-code-review` | After completing major features |

### Common Rationalizations to Avoid

If you think any of these, STOP and use the skill:
- "This is just a simple question" → Skills apply to all tasks
- "I can check files quickly" → Skills guide HOW to gather info
- "This doesn't need a formal skill" → If skill exists, use it
- "The skill is overkill" → Skills prevent simple becoming complex

---

## Available Agents

Specialized agents are defined in `.claude/agents/`. Use them via the Task tool.

| Agent                      | Purpose                          | When to Use                                                                                   |
|----------------------------|----------------------------------|-----------------------------------------------------------------------------------------------|
| `code-reviewer`            | Senior code review               | After completing major project steps                                                          |
| `code-quality-reviewer`    | Code quality audits              | After refactors or before merging                                                             |
| `documentation-verifier`   | Verify docs match codebase       | Documentation accuracy audits                                                                 |
| `tdd-tall-specialist`      | Strict TDD for TALL stack        | **Human-operated only** via `/tdd-multi-component-autoloop`                                   |
| `tall-specialist`          | Livewire/Alpine/Tailwind expert  | Frontend component work                                                                       |
| `performance-specialist`   | Performance optimization         | Database, caching, scaling issues                                                             |
| `implementer`              | Implement approved plans/specs   | When plan defined, execute in small batches with TDD and checkpoints                          |
| `spec-reviewer`            | Review requirements/specs        | Before implementation; resolve ambiguities, define acceptance criteria, edge cases            |
| `log-driven-debugger`      | Fix errors found in app logs     | When dispatched by log-driven-debugging skill to fix a specific error cluster                 |
| `ai-engineer`              | LLM apps, RAG, AI agents         | Building AI-powered features, RAG systems, vector search, agent orchestration                 |
| `prompt-engineer`          | Prompt optimization              | Crafting system prompts, improving agent performance, chain-of-thought design                 |
| `vector-database-engineer` | Vector search implementation     | Embedding optimization, index tuning, hybrid search, scaling vector operations                |

See `@Agents.md` for detailed agent documentation.

---

## Subagent Logging Protocol

**ALL subagents MUST log their progress and reasoning.** This enables orchestrator visibility into agent execution.

**Skill:** Use `agent-logging` skill for complete protocol.

### How Agents Log

Agents use the **Write tool** or **Bash** to append JSON log entries:

```bash
# Via Bash (append to log file)
echo '{"timestamp":"...","agent_type":"code-reviewer",...}' >> \
  .claude/logs/agents/$(date +%Y-%m-%d)/code-reviewer-SESSION_ID.log
```

The `agent-logging.sh` library in `.claude/lib/` provides shell functions for bash-based automation.

### Log Location

```
.claude/logs/agents/
├── YYYY-MM-DD/
│   ├── {agent-type}-{session-id}.log
│   └── summary.json
```

### Log Format

Each log entry MUST include:

```json
{
  "timestamp": "2025-12-16T10:30:00Z",
  "agent_type": "code-reviewer",
  "session_id": "abc123",
  "phase": "investigation|planning|execution|verification|completion",
  "action": "What the agent is doing",
  "reasoning": "WHY the agent is taking this action",
  "context": {
    "files_read": ["path/to/file.php"],
    "files_modified": [],
    "tools_used": ["Read", "Grep"]
  },
  "outcome": "success|failure|blocked",
  "next_steps": "What comes next"
}
```

### Logging Requirements

1. **Log at Phase Boundaries** - Entry for each major phase transition
2. **Log Reasoning** - Explain WHY, not just WHAT
3. **Log Blockers** - If stuck, log why and what's needed
4. **Log Decisions** - Document choices between alternatives
5. **Summarize on Completion** - Final summary with outcomes

### Implementation

Subagents should use this logging approach:

```bash
# Create log directory
mkdir -p .claude/logs/agents/$(date +%Y-%m-%d)

# Log entry (append to agent-specific log)
echo '{"timestamp":"'$(date -Iseconds)'","agent_type":"code-reviewer",...}' >> \
  .claude/logs/agents/$(date +%Y-%m-%d)/code-reviewer-${SESSION_ID}.log
```

Or write structured logs via the Write tool.

---

## Development Standards

### Test-Driven Development

This project uses strict TDD. See `test-results/tdd-test-queue.json` for the test queue.

**TDD Cycle (Non-negotiable):**
1. **RED** - Write failing test first
2. **GREEN** - Minimal code to pass
3. **REFACTOR** - Clean up, tests stay green

**Test Execution:**
```bash
# Focused tests (preferred)
./scripts/run-focused-tests.sh <TestClass>

# Full suite with analysis
./scripts/run-tests-with-analysis.sh
```

### Project Structure

```
app/
├── Http/Controllers/     # HTTP controllers
├── Livewire/            # Livewire components
├── Services/            # Business logic services
├── Jobs/                # Background jobs
├── Models/              # Eloquent models
└── ...

tests/
├── Unit/                # Fast, isolated tests
├── Feature/             # HTTP/Livewire tests
├── Integration/         # Multi-service tests
├── Browser/             # Dusk end-to-end tests
├── Performance/         # Performance benchmarks
└── Snapshots/           # Output verification

resources/
├── views/livewire/      # Livewire Blade views
└── ...
```

### Code Quality

- Follow Laravel conventions and PSR-12
- Use type hints and return types
- Prefer composition over inheritance
- Keep controllers thin, services fat
- Document complex logic with PHPDoc

---

## Git Workflow

### Branch Strategy

- Feature branches: `claude/feature-name-{session-id}`
- TDD branches: `tdd-sprint{N}-{component}`
- Always push with `-u` flag for tracking

### Commit Messages

```bash
# Format
git commit -m "$(cat <<'EOF'
Category: Brief description

- Detailed change 1
- Detailed change 2
EOF
)"

# Examples
# TDD: [Sprint 2] VectorStoreManager validation tests
# Fix: Resolve N+1 query in document search
# Feature: Add real-time chat streaming
```

### Session End Protocol

Every session MUST end with:
1. Run focused tests for modified components
2. Commit all changes with descriptive message
3. Push to remote branch
4. Log session summary

---

## Environment

The development environment includes:
- **PHP + Composer**: Laravel application
- **Node + NPM**: Frontend assets
- **PostgreSQL**: Primary database
- **Neo4j**: Graph database for relationships
- **Laravel Dusk**: Browser testing with Chrome
- **Laravel Reverb**: WebSocket server

Environment setup runs automatically on session start. Check status:
```bash
/check-setup
```

---

## Key Commands

| Command | Description |
|---------|-------------|
| `/write-plan` | Create implementation plan |
| `/brainstorm` | Interactive design refinement |
| `/execute-plan` | Execute plan with checkpoints |
| `/check-setup` | Monitor environment setup |

---

## Quick Reference

### Starting a Task

1. Read this CLAUDE.md
2. Check if skills apply
3. Use appropriate skill via `Skill` tool
4. Announce skill usage
5. Follow skill process exactly

### Using Agents

1. Determine which agent fits the task
2. Launch via Task tool with agent type
3. Agent logs progress automatically
4. Review agent logs for visibility

### Completing Work

1. Run verification (tests, linting)
2. Request code review if major change
3. Commit with descriptive message
4. Push to designated branch
5. Log session summary

---

## Remember

- **Skills First**: Always check for and use relevant skills
- **TDD Always**: No production code without failing tests
- **Log Everything**: Subagents log reasoning and progress
- **Verify Before Claiming**: Evidence before assertions
- **Commit Often**: Small, focused commits with clear messages

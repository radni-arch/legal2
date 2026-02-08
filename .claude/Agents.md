# AI Legal War Machine - Agent Registry

This document defines all specialized agents available for the AI Legal War Machine project. Agents are autonomous subprocesses that handle specific types of tasks with domain expertise.

---

## Agent Architecture

### How Agents Work

1. **Orchestrator** (main Claude session) identifies need for specialized work
2. **Agent** is launched via the Task tool with appropriate `subagent_type`
3. **Agent logs** progress and reasoning to `.claude/logs/agents/`
4. **Agent returns** final report to orchestrator
5. **Orchestrator reviews** agent logs and results

### Agent Communication

- Agents are **stateless** - each invocation is independent
- Agents receive a **detailed prompt** describing their task
- Agents **log continuously** during execution
- Agents return a **single final message** to the orchestrator

---

## Available Agents

### 1. Code Reviewer Agent

**File:** `.claude/agents/code-reviewer.md`
**Model:** Sonnet
**Purpose:** Senior code review against plans and coding standards

#### When to Use

- After completing a major project step
- Before merging feature branches
- When implementation deviates from plan
- For architecture validation

#### Capabilities

1. **Plan Alignment Analysis**
   - Compare implementation against planning documents
   - Identify justified vs. problematic deviations
   - Verify all planned functionality implemented

2. **Code Quality Assessment**
   - Adherence to patterns and conventions
   - Error handling and type safety
   - Test coverage quality
   - Security and performance review

3. **Architecture Review**
   - SOLID principles compliance
   - Separation of concerns
   - Integration with existing systems
   - Scalability considerations

4. **Issue Categorization**
   - **Critical**: Must fix before proceeding
   - **Important**: Should fix before merge
   - **Suggestions**: Nice to have improvements

#### Example Usage

```
Task tool:
  subagent_type: code-reviewer
  prompt: |
    Review the VectorStoreManager implementation against the plan in
    docs/plans/vector-store-manager.md

    Focus on:
    - Adherence to TDD principles
    - Livewire best practices
    - Error handling patterns

    Log your analysis to .claude/logs/agents/$(date +%Y-%m-%d)/
```

---

### 2. TDD TALL Specialist Agent

**File:** `.claude/agents/tdd-tall-specialist.md`
**Purpose:** Strict Test-Driven Development for Laravel TALL stack

> **⚠️ HUMAN-OPERATED ONLY:** This agent is NOT dispatched by orchestrators.
> It is invoked by the `/tdd-multi-component-autoloop` command by human operators.

#### When to Use

- This agent is run by human developers using `/tdd-multi-component-autoloop` command
- NOT for orchestrator dispatch - human operators invoke this directly
- Implementing new features in TALL stack components
- Fixing bugs with TDD approach
- Strengthening existing test coverage
- Working through the TDD queue
- Sprint-organized development work

#### Core Principles

1. **Strict TDD Cycle**
   - RED: Write failing test first (NEVER skip)
   - GREEN: Minimal code to pass
   - REFACTOR: Clean up, tests stay green

2. **Queue-Driven Development**
   - Uses `test-results/tdd-test-queue.json`
   - Sprint-organized (1-6)
   - Domain-grouped components
   - Status tracking (todo/in_progress/done)

3. **Test Hierarchy**
   - Unit → Feature → Integration → Browser
   - Start with unit tests, validate with browser tests
   - Use `visual_groupings` for dependency understanding

#### Sprint Guide

| Sprint | Focus |
|--------|-------|
| 1 | Core backend: Textract, Search, Vector stores, Graph (Unit) |
| 2 | Feature/Livewire managers: VectorStoreManager, TextractManager |
| 3 | Integration tests + Agents/Research pipelines |
| 4 | Dashboards, Viewers, Timelines |
| 5 | Security, Authentication, Browser tests |
| 6 | Performance, Snapshots, catch-all |

#### Test Execution

```bash
# Focused (preferred in TDD loop)
./scripts/run-focused-tests.sh VectorStoreManagerTest

# Full suite with analysis
./scripts/run-tests-with-analysis.sh
```

#### Example Usage

```
Task tool:
  subagent_type: tdd-tall-specialist
  prompt: |
    Work on Sprint 2 components from the TDD queue.
    Focus on VectorStoreManager Livewire component.

    Execute 2-3 TDD slices:
    1. Validation edge cases
    2. Error handling paths
    3. State management

    Update queue status after each slice.
    Commit and push when complete.
    Log all progress to .claude/logs/agents/
```

---

### 3. TALL Stack Specialist Agent

**File:** `.claude/agents/tall-specialist.md`
**Purpose:** Expert in Tailwind, Alpine.js, Livewire, Laravel integration

#### When to Use

- Building Livewire components
- Real-time feature implementation
- Frontend UI/UX work
- Alpine.js micro-interactions
- Tailwind styling and responsive design

#### Core Expertise

1. **Livewire Components**
   - Component architecture
   - State management patterns
   - Wire:model and wire:click optimization
   - Lifecycle hooks

2. **Real-time Updates**
   - Laravel Echo integration
   - Laravel Reverb WebSocket
   - Event broadcasting
   - Live validation

3. **Alpine.js**
   - x-data, x-show, x-if patterns
   - Entangle with Livewire
   - Transitions and animations
   - Component communication

4. **Tailwind CSS**
   - Responsive design
   - Dark mode
   - Custom configurations
   - Component styling patterns

#### Available Tools

- `mcp__serena__*` - Semantic code navigation
- `mcp__zen__*` - UI component library
- `mcp__playwright__*` - Browser automation

#### Example Usage

```
Task tool:
  subagent_type: tall-specialist
  prompt: |
    Create a real-time document preview component.

    Requirements:
    - Livewire component with Alpine.js enhancements
    - Real-time updates via WebSocket
    - Responsive Tailwind styling
    - Loading states and transitions

    Follow project patterns in app/Livewire/.
    Log progress to .claude/logs/agents/
```

---

### 4. Performance Specialist Agent

**File:** `.claude/agents/performance-specialist.md`
**Purpose:** Application performance optimization and scalability

#### When to Use

- Database query optimization
- Caching strategy design
- Load testing and benchmarking
- Memory and resource issues
- Scaling challenges
- AI/embedding performance

#### Core Expertise

1. **Laravel Application Performance**
   - N+1 query resolution
   - Eager loading strategies
   - Queue optimization
   - Memory management

2. **Database Tuning**
   - Index optimization
   - Query plan analysis
   - Connection pooling
   - Read replica strategies

3. **Caching Strategies**
   - Redis configuration
   - Multi-level caching
   - Cache warming
   - Invalidation patterns

4. **AI System Performance**
   - Vector search optimization
   - Embedding generation batching
   - Agent execution efficiency
   - Meilisearch tuning

5. **Infrastructure**
   - Container resource allocation
   - Load balancing
   - Auto-scaling
   - APM integration

#### Example Usage

```
Task tool:
  subagent_type: performance-specialist
  prompt: |
    Optimize the knowledge search pipeline.

    Current issues:
    - Search queries taking >500ms
    - High memory usage during embedding
    - Slow vector similarity lookups

    Analyze, benchmark, and recommend optimizations.
    Log all findings to .claude/logs/agents/
```

### 5. Documentation Verifier Agent

**File:** `.claude/agents/documentation-verifier.md`
**Purpose:** Verify documentation accuracy against the codebase

#### When to Use

- Documentation audits
- Ensuring docs match implementation
- Verifying feature claims
- Post-implementation reviews
- Pre-release checks
- Ongoing documentation maintenance

#### Core Expertise

1. **Documentation Analysis**
   - Extract verifiable claims
   - Identify file, class, method references
   - Command and feature verification

2. **Codebase Verification**
   - Use Glob/Grep for existence checks
   - Validate implementation against claims
   - Document discrepancies

3. **Logging and Reporting**
   - Log each file and claim verification
   - Generate comprehensive verification reports
   - Recommend documentation updates
   - Structured logging for traceability

#### Example Usage

```
Task tool:
  subagent_type: documentation-verifier
  prompt: |
    Verify the accuracy of documentation in docs/ against the codebase.

    Steps:
    1. Inventory all .md files in docs/
    2. Extract verifiable claims from each file
    3. Verify each claim against the codebase
    4. Log findings and discrepancies
    5. Generate a final report of verification results

    Log all progress to .claude/logs/agents/
```

---

### 6. Code Quality Reviewer Agent

**File:** `.claude/agents/code-quality-reviewer.md`
**Purpose:** Code quality audits and best practices enforcement

#### When to Use

- After refactors
- Before merging significant changes
- Ensuring maintainability
- Enforcing coding standards
- Security and performance reviews
- Pre-release audits

#### Core Expertise

1. **Code Quality Standards**
   - Adherence to coding conventions
   - Proper error handling
   - Type safety and defensive coding
   - Code organization and naming

2. **Maintainability Assessment**
   - Readability and clarity
   - Modular design
   - Documentation quality
   - Test coverage and quality
   - Dependency management

3. **Security and Performance**
   - Identify vulnerabilities
   - Performance bottlenecks
   - Resource management
   - Scalability considerations

#### Example Usage

```
Task tool:
    subagent_type: code-quality-reviewer
    prompt: |
        Conduct a code quality audit of the recent refactor in app/Services/.
    
        Focus on:
        - Coding standards adherence
        - Maintainability improvements
        - Security vulnerabilities
        - Performance considerations
    
        Log all findings to .claude/logs/agents/
```

---

### 7. Implementer Agent

**File:** `.claude/agents/implementer.md`
**Purpose:** Implement approved plans/specs with TDD and checkpoints

#### When to Use

- When plan defined, execute in small batches with TDD and checkpoints
- New feature development
- Bug fixes
- Refactoring tasks
- Enhancements based on specs
- Sprint-organized work

#### Core Expertise

1. **Plan Execution**
   - Break down plans into bite-sized tasks
   - Prioritize tasks based on dependencies
   - Follow TDD principles strictly
   - Commit and push changes regularly

2. **Testing and Validation**
   - Write tests before implementation
   - Ensure all tests pass before proceeding
   - Perform self-review of code quality
   - Address feedback from reviewers
   - Log all progress and reasoning
   - Support partial completion when context limits approach
   - Comprehensive logging protocol
   - Support for context-limited execution
   - Clear communication of blockers and questions

3. **Collaboration with Reviewers**
   - Coordinate with spec-reviewer and code-quality-reviewer agents
   - Address issues raised promptly
   - Ensure final implementation meets all requirements
   - Log all interactions and resolutions

#### Example Usage

```
Task tool:
    subagent_type: implementer
    prompt: |
        Implement the feature described in docs/plans/feature-plan.md.
    
        Follow TDD principles:
        1. Write failing tests first
        2. Implement minimal code to pass tests
        3. Refactor while keeping tests green
    
        Work in small batches, committing after each successful test run.
        Coordinate with spec-reviewer and code-quality-reviewer agents as needed.
        Log all progress to .claude/logs/agents/
```

---

### 8. Spec Reviewer Agent

**File:** `.claude/agents/spec-reviewer.md`
**Purpose:** Review requirements/specs before implementation

#### When to Use

- Before implementation; resolve ambiguities, define acceptance criteria, edge cases
- New feature specifications
- Clarifying requirements
- Pre-implementation reviews
- Sprint planning sessions
- Ongoing requirement refinement

#### Core Expertise

1. **Specification Analysis**
   - Thoroughly read and understand specs
   - Identify ambiguities or gaps
   - Define clear acceptance criteria
   - Consider edge cases and failure modes

2. **Collaboration with Implementers**
    - Communicate clarifications promptly
    - Ensure implementers understand requirements
    - Review implemented code against specs
    - Log all progress and reasoning
    - Support for context-limited execution
    - Clear communication of blockers and questions
    - Comprehensive logging protocol
    - Facilitate smooth handoff to implementers

3. **Review Process**
   - Review implemented code against specs
   - Identify discrepancies or deviations
   - Provide actionable feedback
   - Coordinate with code-quality-reviewer agent
   - Log all findings and resolutions

#### Example Usage

```
Task tool:
    subagent_type: spec-reviewer
    prompt: |
        Review the specification in docs/plans/feature-spec.md before implementation.
    
        Steps:
        1. Identify any ambiguities or gaps in the spec
        2. Define clear acceptance criteria
        3. Consider edge cases and failure modes
        4. Communicate clarifications to implementer agent
        5. Log all findings to .claude/logs/agents/
```

---

### 9. Log-Driven Debugger Agent

**File:** `.claude/agents/log-driven-debugger.md`
**Purpose:** Fix errors found in application logs

#### When to Use

- When dispatched by log-driven-debugging skill to fix a specific error cluster
- Investigating Laravel log errors
- Root cause analysis from stack traces
- TDD-based bug fixing

#### Core Expertise

1. **Log Analysis**
   - Parse log entries (timestamp, level, channel, exception class)
   - Extract correlation IDs and context
   - Identify error patterns and clusters

2. **Root Cause Investigation**
   - Trace stack traces to source code
   - Understand call chains and execution context
   - Classify error types (runtime, config, dependency, code bug)

3. **TDD-Based Fixing**
   - Write failing test that reproduces the error
   - Implement minimal fix to pass
   - Verify fix doesn't introduce regressions

#### Example Usage

```
Task tool:
  subagent_type: log-driven-debugger
  prompt: |
    ## Error Cluster
    [2025-01-15 10:30:00] production.ERROR: Undefined table: case_documents
    
    ## Error Summary
    Database table missing during document processing
    
    ## Priority
    critical
    
    Investigate root cause and apply fix with TDD approach.
    Log all findings to .claude/logs/agents/
```

---

### 10. AI Engineer Agent

**File:** `.claude/agents/ai-engineer.md`
**Purpose:** Build production-ready LLM applications, RAG systems, and intelligent agents

#### When to Use

- Implementing LLM-powered features
- Building or optimizing RAG systems
- Vector search and embedding optimization
- AI agent orchestration
- Chatbot and AI assistant development
- Enterprise AI integrations

#### Core Expertise

1. **LLM Integration**
   - OpenAI GPT, Anthropic Claude, open-source models
   - Function calling and structured outputs
   - Multi-model orchestration and routing
   - Cost optimization strategies

2. **Advanced RAG Systems**
   - Vector databases (Pinecone, Qdrant, Weaviate, pgvector)
   - Embedding model selection and optimization
   - Chunking strategies and hybrid search
   - GraphRAG, HyDE, RAG-Fusion patterns

3. **Agent Frameworks**
   - LangGraph/LangChain for complex workflows
   - LlamaIndex for data-centric AI
   - CrewAI for multi-agent collaboration
   - Agent memory and tool integration

#### Example Usage

```
Task tool:
  subagent_type: ai-engineer
  prompt: |
    Implement semantic search for legal documents.
    
    Requirements:
    - Use Voyage AI embeddings (voyage-law-2)
    - Integrate with existing Meilisearch
    - Implement hybrid search with BM25 fusion
    - Add reranking for improved relevance
    
    Log all progress to .claude/logs/agents/
```

---

### 11. Prompt Engineer Agent

**File:** `.claude/agents/prompt-engineer.md`
**Purpose:** Expert prompt engineering and LLM optimization

#### When to Use

- Crafting system prompts for AI features
- Optimizing agent performance
- Implementing chain-of-thought reasoning
- AI safety and constitutional AI patterns
- Prompt testing and benchmarking

#### Core Expertise

1. **Advanced Prompting Techniques**
   - Chain-of-thought (CoT) prompting
   - Tree-of-thoughts for complex reasoning
   - Few-shot and zero-shot patterns
   - Meta-prompting for self-improvement

2. **Safety & Alignment**
   - Constitutional AI principles
   - Jailbreak prevention
   - Content filtering patterns
   - Red teaming and adversarial testing

3. **Model-Specific Optimization**
   - OpenAI GPT optimization
   - Anthropic Claude best practices
   - Open-source model tuning

#### Example Usage

```
Task tool:
  subagent_type: prompt-engineer
  prompt: |
    Optimize the legal research agent's system prompt.
    
    Current issues:
    - Responses are too verbose
    - Missing citation formatting
    - Inconsistent reasoning quality
    
    Design and test an improved prompt.
    Log all iterations to .claude/logs/agents/
```

---

### 12. Vector Database Engineer Agent

**File:** `.claude/agents/vector-database-engineer.md`
**Purpose:** Vector databases, embedding strategies, and semantic search

#### When to Use

- Vector search implementation
- Embedding model selection and optimization
- Index configuration and tuning
- Scaling vector operations
- Hybrid search implementation

#### Core Expertise

1. **Vector Database Selection**
   - Pinecone, Qdrant, Weaviate, Milvus, pgvector
   - Index strategies (HNSW, IVF, PQ)
   - Scaling for millions of documents

2. **Embedding Optimization**
   - Voyage AI, OpenAI, open-source models
   - Domain-specific fine-tuning
   - Dimensionality and quantization tradeoffs

3. **Hybrid Search**
   - Vector + BM25 fusion
   - Reciprocal Rank Fusion (RRF)
   - Cross-encoder reranking

#### Example Usage

```
Task tool:
  subagent_type: vector-database-engineer
  prompt: |
    Optimize vector search for 10M document corpus.
    
    Current issues:
    - Search latency >200ms
    - High memory usage
    - Recall dropping at scale
    
    Analyze and recommend optimizations.
    Log all findings to .claude/logs/agents/
```

---

## Agent Logging Protocol

**ALL AGENTS MUST LOG THEIR PROGRESS AND REASONING.**

### Log Directory Structure

```
.claude/logs/agents/
├── YYYY-MM-DD/
│   ├── {agent-type}-{session-id}.log
│   ├── {agent-type}-{session-id}.log
│   └── summary.json
└── latest/
    └── {agent-type}.log  (symlink to most recent)
```

### Log Entry Format

Each log entry is a JSON object on a single line:

```json
{
  "timestamp": "2025-12-16T10:30:00Z",
  "agent_type": "code-reviewer",
  "session_id": "unique-session-id",
  "phase": "investigation",
  "action": "Reading VectorStoreManager implementation",
  "reasoning": "Need to understand current implementation before comparing to plan",
  "context": {
    "files_read": ["app/Livewire/VectorStoreManager.php"],
    "files_modified": [],
    "tools_used": ["Read"],
    "duration_ms": 1500
  },
  "outcome": "success",
  "findings": "Component uses deprecated wire:model syntax",
  "next_steps": "Check Livewire 3 migration guide for updated syntax"
}
```

### Required Log Phases

Every agent MUST log entries for:

1. **initialization**
   - Task understanding
   - Initial context gathering
   - Scope determination

2. **investigation**
   - File reading
   - Code analysis
   - Context building

3. **planning**
   - Approach decisions
   - Trade-off analysis
   - Task breakdown

4. **execution**
   - Each significant action
   - Tool usage
   - Changes made

5. **verification**
   - Test execution
   - Validation checks
   - Quality assurance

6. **completion**
   - Summary of outcomes
   - Remaining issues
   - Recommendations

### Logging Implementation

Agents should implement logging like this:

```bash
#!/bin/bash
# At agent start
LOG_DIR=".claude/logs/agents/$(date +%Y-%m-%d)"
SESSION_ID=$(date +%s)-$$
LOG_FILE="${LOG_DIR}/${AGENT_TYPE}-${SESSION_ID}.log"

mkdir -p "$LOG_DIR"

log_entry() {
  local phase="$1"
  local action="$2"
  local reasoning="$3"
  local outcome="${4:-success}"

  echo "{\"timestamp\":\"$(date -Iseconds)\",\"agent_type\":\"${AGENT_TYPE}\",\"session_id\":\"${SESSION_ID}\",\"phase\":\"${phase}\",\"action\":\"${action}\",\"reasoning\":\"${reasoning}\",\"outcome\":\"${outcome}\"}" >> "$LOG_FILE"
}

# Usage
log_entry "investigation" "Reading test file" "Need to understand existing test coverage"
```

Or via the Write tool in structured format.

### Summary Generation

At session end, update `summary.json`:

```json
{
  "date": "2025-12-16",
  "sessions": [
    {
      "session_id": "1734350000-12345",
      "agent_type": "code-reviewer",
      "start_time": "2025-12-16T10:30:00Z",
      "end_time": "2025-12-16T10:45:00Z",
      "duration_minutes": 15,
      "outcome": "success",
      "summary": "Reviewed VectorStoreManager. Found 3 Critical, 5 Important issues.",
      "files_analyzed": 8,
      "issues_found": {
        "critical": 3,
        "important": 5,
        "suggestions": 12
      }
    }
  ]
}
```

---

## Creating New Agents

To create a new agent:

1. **Create agent file** in `.claude/agents/{agent-name}.md`

2. **Use frontmatter** for metadata:
   ```yaml
   ---
   name: agent-name
   description: When to use this agent
   model: sonnet  # or opus, haiku
   tools:
     - bash
     - read
     - write
     - ...
   ---
   ```

3. **Define responsibilities** clearly

4. **Include logging requirements** - reference this document

5. **Add to this registry** - update Agents.md

6. **Test with subagents** - use `testing-skills-with-subagents` skill

---

## Agent Best Practices

### For Orchestrators

1. **Provide complete context** in the agent prompt
2. **Specify expected outputs** clearly
3. **Request logging** explicitly
4. **Review agent logs** after completion
5. **Use appropriate agent** for the task

### For Agents

1. **Log at every phase boundary**
2. **Explain reasoning**, not just actions
3. **Document blockers** immediately
4. **Summarize findings** at completion
5. **Commit and push** when instructed
6. **Stay within scope** - don't expand task

### Common Pitfalls

- **Over-scoping**: Agents should do ONE focused task
- **Under-logging**: Every significant decision needs a log entry
- **Missing reasoning**: "What" without "why" is useless
- **Ignoring blockers**: Log and report, don't silently fail
- **Skipping verification**: Always verify before claiming done

---

## Quick Reference

| Agent                     | Task Type                        | Model   | Key Skill                              |
|---------------------------|----------------------------------|---------|----------------------------------------|
| `code-reviewer`           | Review completed work            | Sonnet  | Thorough analysis                      |
| `code-quality-reviewer`   | Code quality audits              | Default | Standards enforcement                  |
| `documentation-verifier`  | Verify documentation accuracy    | Sonnet  | Manual reference checking              |
| `tdd-tall-specialist`     | TDD implementation (human-only)  | Default | RED-GREEN-REFACTOR                     |
| `tall-specialist`         | Frontend/Livewire                | Default | TALL stack expertise                   |
| `performance-specialist`  | Optimization                     | Default | Benchmarking                           |
| `implementer`             | Execute approved plans           | Default | TDD with checkpoints                   |
| `spec-reviewer`           | Review requirements/specs        | Default | Acceptance criteria                    |
| `log-driven-debugger`     | Fix errors from logs             | Default | Root cause analysis                    |
| `ai-engineer`             | LLM apps, RAG, agents            | Default | AI system architecture                 |
| `prompt-engineer`         | Prompt optimization              | Default | Advanced prompting techniques          |
| `vector-database-engineer`| Vector search implementation     | Default | Embedding & index optimization         |

> **Note:** `tdd-tall-specialist` is human-operated only via `/tdd-multi-component-autoloop` command.

---

## See Also

- `@CLAUDE.md` - Main system prompt
- `.claude/skills/` - Available skills
- `.claude/commands/` - Slash commands
- `test-results/tdd-test-queue.json` - TDD queue

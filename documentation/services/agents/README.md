# Autonomous Research Agent

A sophisticated **LLM-powered** autonomous agent system for legal research that implements a **plan→act→evaluate** loop with intelligent planning, insight extraction, budget constraints, time limits, and comprehensive evaluation criteria.

## Table of Contents

- [Overview](#overview)
- [Features](#features)
- [Architecture](#architecture)
- [LLM-Powered Intelligence](#llm-powered-intelligence)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
- [API Reference](#api-reference)
- [Evaluation System](#evaluation-system)
- [Scheduling](#scheduling)
- [Development](#development)

---

## Overview

The Autonomous Research Agent conducts comprehensive legal research by:
1. **Planning** next research steps using **LLM intelligence** based on objectives and previous findings
2. **Acting** by executing tools (vector search, graph queries, web fetch, etc.)
3. **Evaluating** findings and determining when to stop
4. **Extracting** properly-cited legal insights using **LLM analysis**
5. **Synthesizing** results into comprehensive reports
6. **Saving** insights to long-term vector memory

### Key Capabilities

- **LLM-powered planning**: GPT-4o-mini intelligently decides next research actions
- **LLM insight extraction**: Automated extraction of concise, properly-cited legal insights
- **Multi-source search**: Laws, court decisions, case documents, knowledge graph (15 specialized tools)
- **Budget management**: Token and cost caps with real-time tracking
- **Time constraints**: Configurable execution time limits
- **Self-evaluation**: 5-criteria assessment with citation requirements
- **Persistent memory**: Saves insights for future reference
- **Async execution**: Optional background job processing
- **Scheduled runs**: Weekly automated research on active topics
- **Resilient**: Automatic fallback to rule-based planning if LLM fails

---

## Features

### 1. AgentToolbox

Six powerful tools for research:

| Tool | Purpose | Key Parameters |
|------|---------|----------------|
| `vector_search` | Semantic search across all legal documents | query, types, limit, jurisdiction |
| `law_lookup` | Find specific laws by number | law_number, jurisdiction |
| `decision_lookup` | Search court decisions | case_number, court, date_range |
| `graph_query` | Execute Cypher on knowledge graph | cypher, parameters |
| `web_fetch` | Fetch external content | url, timeout |
| `note_save` | Save insights to memory | content, namespace, metadata |

### 2. Plan→Act→Evaluate Loop

```
┌─────────────────────────────────────────────┐
│  START: Receive objective & constraints    │
└──────────────────┬──────────────────────────┘
                   │
                   ▼
        ┌──────────────────────┐
        │   PLAN Next Step     │◄──────┐
        │  (What to research)  │       │
        └──────────┬───────────┘       │
                   │                   │
                   ▼                   │
        ┌──────────────────────┐       │
        │   ACT: Execute Tools │       │
        │  (Search, Query, etc)│       │
        └──────────┬───────────┘       │
                   │                   │
                   ▼                   │
        ┌──────────────────────┐       │
        │  EVALUATE: Assess    │       │
        │  Quality & Progress  │       │
        └──────────┬───────────┘       │
                   │                   │
           ┌───────┴────────┐          │
           │                │          │
      Should stop?       Continue ─────┘
           │
           ▼
┌──────────────────────────┐
│  SYNTHESIZE Final Report │
└──────────────────────────┘
```

### 3. Evaluation System

Multi-criteria evaluation with weighted scoring:

| Criterion | Weight | Checks |
|-----------|--------|---------|
| **Completeness** | 25% | Addresses all aspects of objective |
| **Citations** | 25% | Proper legal references (NN numbers, case refs, articles) |
| **Relevance** | 20% | On-topic, focused content |
| **Quality** | 15% | Structure, length, formatting |
| **Evidence** | 15% | Sufficient research actions |

**Pass threshold:** Configurable (default: 0.75)

---

## Architecture

```
┌─────────────────────────────────────────────────────────┐
│                    Web/API Layer                        │
│  ┌─────────────────┐  ┌──────────────────────────────┐ │
│  │ AgentController │  │  Dashboard Views             │ │
│  │  - Start runs   │  │  - List runs                 │ │
│  │  - Get status   │  │  - View details              │ │
│  │  - Evaluate     │  │  - Show evaluations          │ │
│  └────────┬────────┘  └──────────────────────────────┘ │
└───────────┼─────────────────────────────────────────────┘
            │
            ▼
┌─────────────────────────────────────────────────────────┐
│                   Service Layer                         │
│  ┌──────────────────────┐  ┌────────────────────────┐  │
│  │ AutonomousResearch   │  │  AgentEvaluation      │  │
│  │ Agent                │  │  Service              │  │
│  │  - executeRun()      │  │  - evaluateRun()      │  │
│  │  - planNextStep()    │  │  - checkCitations()   │  │
│  │  - executeActions()  │  │  - generateReport()   │  │
│  └──────────┬───────────┘  └────────────────────────┘  │
│             │                                           │
│             ▼                                           │
│  ┌──────────────────────┐                              │
│  │   AgentToolbox       │                              │
│  │  - vector_search()   │                              │
│  │  - law_lookup()      │                              │
│  │  - graph_query()     │                              │
│  │  - note_save()       │                              │
│  └──────────┬───────────┘                              │
└─────────────┼─────────────────────────────────────────-┘
              │
              ▼
┌─────────────────────────────────────────────────────────┐
│                    Data Layer                           │
│  ┌──────────────┐  ┌────────────┐  ┌────────────────┐  │
│  │   AgentRun   │  │    Laws    │  │  CourtDecisions│  │
│  │   (Postgres) │  │ (pgvector) │  │   (pgvector)   │  │
│  └──────────────┘  └────────────┘  └────────────────┘  │
│  ┌──────────────┐  ┌────────────┐                      │
│  │AgentVector   │  │   Neo4j    │                      │
│  │Memory        │  │ (Knowledge │                      │
│  │(pgvector)    │  │   Graph)   │                      │
│  └──────────────┘  └────────────┘                      │
└─────────────────────────────────────────────────────────┘
```

---

## LLM-Powered Intelligence

The autonomous agent uses **GPT-4o-mini** for intelligent planning and insight extraction, transforming it from a rule-based system into an adaptive research assistant.

### Overview

Two key areas where LLM intelligence is applied:

1. **Planning** - LLM decides what research actions to take next based on objective and previous findings
2. **Insight Extraction** - LLM extracts concise, properly-cited legal insights from search results

### LLM Planning System

#### How It Works

```
┌──────────────────────────────────────────────────────────────┐
│  Planning Context Builder                                    │
│  ┌────────────────────────────────────────────────────────┐  │
│  │ # Research Objective                                   │  │
│  │ Research Croatian labor law notice periods             │  │
│  │                                                         │  │
│  │ # Current Progress                                     │  │
│  │ Iteration: 3/10                                        │  │
│  │ Insights Found: 2                                      │  │
│  │                                                         │  │
│  │ # Previous Insights                                    │  │
│  │ - Article 93 requires 2-week notice                    │  │
│  │ - Supreme Court case Gž-1234/2023                      │  │
│  │                                                         │  │
│  │ # Previous Actions & Results                           │  │
│  │ Iteration 1: 3 actions, 2 insights                     │  │
│  │ Iteration 2: 2 actions, 1 insights                     │  │
│  └────────────────────────────────────────────────────────┘  │
└────────────────────────┬─────────────────────────────────────┘
                         │
                         ▼
┌──────────────────────────────────────────────────────────────┐
│  LLM Planning (GPT-4o-mini)                                  │
│  Temperature: 0.7 (creative but focused)                     │
│  Max Tokens: 1000                                            │
│  Response Format: JSON                                       │
└────────────────────────┬─────────────────────────────────────┘
                         │
                         ▼
┌──────────────────────────────────────────────────────────────┐
│  Structured Plan (JSON)                                      │
│  {                                                            │
│    "reasoning": "Found notice period in Labor Law. Now       │
│                  search for court decisions applying it.",   │
│    "actions": [                                              │
│      {                                                       │
│        "tool": "decision_vector_search",                     │
│        "params": {"query": "notice period Article 93",       │
│                   "limit": 5},                               │
│        "rationale": "Find court interpretations"             │
│      }                                                       │
│    ]                                                         │
│  }                                                           │
└──────────────────────────────────────────────────────────────┘
```

#### Available Tools for LLM Planning

The LLM has access to 15 specialized tools:

**Law Search (5 tools):**
- `law_vector_search` - Semantic search across Croatian laws
- `law_keyword_search` - Exact text matching
- `law_hybrid_search` - Combined vector + keyword
- `law_lookup` - Find specific law by number
- `law_get_article` - Get specific article

**Decision Search (5 tools):**
- `decision_vector_search` - Semantic search across court decisions
- `decision_keyword_search` - Exact text matching
- `decision_hybrid_search` - Combined search
- `decision_lookup` - Lookup by criteria
- `decision_get` - Get decision by ID

**Case Search (3 tools):**
- `case_vector_search` - Search case documents
- `case_search` - Search cases
- `case_document_search` - Search within documents

**Other Tools (2 tools):**
- `graph_query` - Query Neo4j knowledge graph
- `web_fetch` - Fetch external content

#### Planning System Prompt

The LLM receives detailed instructions on:
- Available tools and their parameters
- Strategic planning guidelines (start broad, then narrow)
- JSON response format requirements
- Quality standards for actions

Example strategic guidance:
```
- Start broad (vector search) to understand the topic
- Follow up with specific searches based on findings
- Use keyword search when you know exact law numbers
- Use graph queries to find related laws and citations
- Build on previous findings rather than repeating searches
```

#### Error Handling & Fallback

If LLM planning fails (API error, invalid JSON, etc.), the system automatically falls back to rule-based planning:

**First Iteration:** Broad law_vector_search with the objective
**Subsequent Iterations:** Targeted searches based on topics + graph queries

This ensures the agent always continues researching even during LLM outages.

### LLM Insight Extraction

#### How It Works

```
┌──────────────────────────────────────────────────────────────┐
│  Search Results (Raw Data)                                   │
│  ┌────────────────────────────────────────────────────────┐  │
│  │ ## Laws Found:                                         │  │
│  │ 1. Zakon o radu (Law Number: 93/14)                    │  │
│  │    Content: Poslodavac je dužan dati otkaz...          │  │
│  │                                                         │  │
│  │ ## Court Decisions Found:                              │  │
│  │ 1. Termination Notice Period Case                      │  │
│  │    Court: Vrhovni sud RH                               │  │
│  │    Case Number: Gž-1234/2023                           │  │
│  └────────────────────────────────────────────────────────┘  │
└────────────────────────┬─────────────────────────────────────┘
                         │
                         ▼
┌──────────────────────────────────────────────────────────────┐
│  LLM Insight Extraction (GPT-4o-mini)                        │
│  Temperature: 0.3 (factual, precise)                         │
│  Max Tokens: 200 (concise output)                            │
│  Truncation: 10,000 char limit                               │
└────────────────────────┬─────────────────────────────────────┘
                         │
                         ▼
┌──────────────────────────────────────────────────────────────┐
│  Extracted Insight (1-2 sentences)                           │
│                                                              │
│  "Article 93 of the Croatian Labor Law (NN 93/14) requires  │
│   employers to provide written notice 2 weeks before         │
│   termination for employees with less than 2 years of        │
│   service."                                                  │
└──────────────────────────────────────────────────────────────┘
```

#### Quality Standards

The insight extraction system prompt emphasizes:

1. **Proper Citations** - Always include law numbers (NN format), article numbers, case numbers
2. **Conciseness** - Maximum 1-2 sentences
3. **Legal Accuracy** - Use Croatian legal terminology correctly
4. **Direct Relevance** - Focus on the research objective
5. **Actionable** - Provide information useful for legal decision-making

Good example:
```
"Article 93 of the Croatian Labor Law (NN 93/14) requires employers to
provide written notice 2 weeks before termination for employees with less
than 2 years of service."
```

Bad example:
```
"Found a law about employment" (too vague, no citation)
```

#### Result Formatting

Before sending to the LLM, results are formatted into readable markdown:

- **Laws**: Title, law number, content preview (300 chars)
- **Decisions**: Title, court, case number, date
- **Cases**: Title, case number, status
- **Graph Results**: Count + JSON representation

Only the **top 3 results per category** are included to avoid overwhelming the LLM.

#### Null Handling

If results are irrelevant to the objective, the LLM returns `"null"`, which the system interprets correctly to skip saving that insight.

### Token & Cost Tracking

Every LLM call is monitored and tracked:

```php
// Planning call
$tokensUsed = $response['usage']['total_tokens'] ?? 0;
$run->tokens_used += $tokensUsed;
$run->cost_spent += ($tokensUsed / 1000000) * 0.15; // GPT-4o-mini pricing
$run->save();
```

**Pricing (GPT-4o-mini):**
- $0.15 per 1M tokens
- Typical planning call: 200-500 tokens
- Typical insight extraction: 50-150 tokens

**Budget Enforcement:**
```php
// Agent stops when budget exceeded
if ($run->cost_budget && $run->cost_spent >= $run->cost_budget) {
    return false; // Stop researching
}
```

### Logging & Monitoring

All LLM operations are logged for debugging and analysis:

**Planning Logs:**
```
[INFO] Planning next research step
  run_id: 123
  iteration: 3
  context_length: 842

[INFO] LLM planning completed
  run_id: 123
  reasoning: "Found notice period in Labor Law..."
  actions_count: 2
  tokens_used: 315
```

**Insight Extraction Logs:**
```
[INFO] Insight extracted via LLM
  run_id: 123
  insight_length: 147
  tokens_used: 89

[ERROR] Insight extraction failed, using fallback
  error: "API timeout"
  result_keys: ["laws", "decisions"]
```

### Testing

Comprehensive test coverage for LLM features:

**Planning Tests** (`AutonomousResearchAgentPlanningTest.php` - 12 tests):
- Context building with/without insights
- LLM call with proper parameters
- Plan structure validation
- Fallback on API errors
- Token usage tracking
- Cost calculation

**Insight Tests** (`AutonomousResearchAgentInsightTest.php` - 15 tests):
- Extraction from laws, decisions, cases
- Null returns for irrelevant results
- Fallback on API errors
- Result formatting
- Truncation of long results
- Token tracking

All tests use **Mockery** to mock OpenAI service calls, ensuring fast, reliable tests without API dependencies.

### Performance Considerations

**Temperature Settings:**
- **Planning**: 0.7 (creative, exploratory)
- **Insight Extraction**: 0.3 (factual, precise)

**Token Limits:**
- **Planning**: 1,000 max tokens (allows detailed reasoning)
- **Insight Extraction**: 200 max tokens (enforces conciseness)

**Context Truncation:**
- **Planning Context**: No hard limit (typically <2,000 chars)
- **Search Results**: 10,000 char limit to prevent token overflow

### Configuration

```env
# LLM Settings
OPENAI_API_KEY=your-key-here
OPENAI_CHAT_MODEL=gpt-4o-mini

# Agent LLM Behavior
AGENT_PLANNING_TEMPERATURE=0.7
AGENT_INSIGHT_TEMPERATURE=0.3
AGENT_MAX_PLANNING_TOKENS=1000
AGENT_MAX_INSIGHT_TOKENS=200

# Fallback Behavior
AGENT_FALLBACK_ON_LLM_ERROR=true
```

### Benefits of LLM Integration

**Before (Rule-Based):**
- Fixed search patterns
- Simple "Found law X" insights
- No adaptation to findings
- Limited reasoning

**After (LLM-Powered):**
- ✅ **Adaptive** - Adjusts strategy based on what it finds
- ✅ **Intelligent** - Builds on previous insights
- ✅ **Comprehensive** - Properly cited, concise insights
- ✅ **Resilient** - Fallback to rules if LLM fails
- ✅ **Transparent** - Full logging of LLM decisions

### Example: LLM vs Rule-Based Planning

**Objective:** "Research Croatian employment termination notice periods"

**Rule-Based Approach:**
```
Iteration 1: vector_search("employment termination notice periods")
Iteration 2: vector_search("employment law")
Iteration 3: vector_search("termination")
```

**LLM-Powered Approach:**
```
Iteration 1:
  Reasoning: "Start with broad search to find relevant laws"
  Actions: law_vector_search("employment termination notice")

Iteration 2:
  Reasoning: "Found Labor Law 93/14. Now search for court interpretations"
  Actions: decision_vector_search("Labor Law 93/14 notice period")

Iteration 3:
  Reasoning: "Found Article 93. Look for related provisions in graph"
  Actions: graph_query("MATCH (l:Law {number: '93/14'})-[:CITES]->(cited)")
```

The LLM approach is strategic, builds on findings, and uses different tools appropriately.

---

## Installation

### 1. Database Migration

Run the migration to add budget/time tracking to `agent_runs`:

```bash
php artisan migrate
```

This adds:
- Budget tracking (token_budget, tokens_used, cost_budget, cost_spent)
- Time tracking (time_limit_seconds, started_at, completed_at, elapsed_seconds)
- Agent metadata (agent_name, topics)

### 2. Configuration

Publish the agent configuration (if using package):

```bash
php artisan vendor:publish --tag=agent-config
```

Or use the included `config/agent.php` file.

### 3. Environment Variables

Add to your `.env`:

```env
# Agent defaults
AGENT_MAX_ITERATIONS=10
AGENT_TIME_LIMIT=600
AGENT_THRESHOLD=0.75

# Safety limits
AGENT_MAX_COST=5.00
AGENT_MAX_TIME=3600

# Async execution (requires queue worker)
AGENT_ASYNC_EXECUTION=false
AGENT_QUEUE=agents

# Caching
AGENT_CACHE_ENABLED=true
AGENT_CACHE_TTL=3600

# Logging
AGENT_LOG_LEVEL=info
AGENT_LOG_ITERATIONS=true
```

### 4. Queue Worker (Optional)

For async execution, start a queue worker:

```bash
php artisan queue:work --queue=agents
```

---

## Configuration

### Agent Defaults (`config/agent.php`)

```php
'defaults' => [
    'max_iterations' => 10,
    'time_limit_seconds' => 600,      // 10 minutes
    'threshold' => 0.75,
    'token_budget' => null,           // unlimited
    'cost_budget' => null,            // unlimited
],
```

### Evaluation Weights

```php
'evaluation' => [
    'weights' => [
        'completeness' => 0.25,
        'citations' => 0.25,
        'relevance' => 0.20,
        'quality' => 0.15,
        'evidence' => 0.15,
    ],
],
```

### Toolbox Settings

```php
'toolbox' => [
    'vector_search' => [
        'default_limit' => 10,
        'min_similarity' => 0.7,
    ],
    'web_fetch' => [
        'timeout' => 30,
    ],
],
```

---

## Usage

### Quick Start (API)

```bash
curl -X POST http://localhost/api/agent/research/start \
  -H "Content-Type: application/json" \
  -d '{
    "objective": "Research Croatian data protection laws and GDPR compliance requirements",
    "topics": ["data protection", "GDPR", "privacy"],
    "max_iterations": 10,
    "time_limit_seconds": 600
  }'
```

### Async Execution

```bash
curl -X POST http://localhost/api/agent/research/start \
  -H "Content-Type: application/json" \
  -d '{
    "objective": "Analyze employment termination procedures under Croatian law",
    "async": true
  }'
```

Response:
```json
{
  "success": true,
  "async": true,
  "run": {
    "id": 123,
    "status": "running",
    "message": "Research run started in background. Check status using GET /api/agent/research/123"
  }
}
```

### Check Status

```bash
curl http://localhost/api/agent/research/123
```

### Get Evaluation

```bash
curl http://localhost/api/agent/research/123/evaluation
```

---

## API Reference

See [AGENT_API.md](AGENT_API.md) for complete API documentation.

### Key Endpoints

| Method | Endpoint | Purpose |
|--------|----------|---------|
| POST | `/api/agent/research/start` | Start new run |
| GET | `/api/agent/research` | List all runs |
| GET | `/api/agent/research/{id}` | Get run details |
| GET | `/api/agent/research/{id}/evaluation` | Get evaluation report |
| DELETE | `/api/agent/research/{id}` | Delete run |

### Web Dashboard

- **Dashboard**: `/agent/dashboard` - Overview with stats
- **Run Details**: `/agent/run/{id}` - Detailed view with iterations

---

## Evaluation System

### Citation Detection

The system recognizes Croatian legal citation patterns:

- **Narodne Novine**: `NN 94/14`, `N.N. 123/20`
- **Case Numbers**: `2024/123`, `Gž-1234/2023`
- **Article References**: `čl. 15`, `članak 27`
- **Paragraph References**: `st. 2`, `stavak 1`

### Quality Metrics

**Completeness (25%)**
- LLM-based assessment
- Checks if all objective aspects covered
- Identifies missing elements

**Citations (25%)**
- ≥5 citations = 1.0 score
- ≥3 citations = 0.8 score
- ≥1 citation = 0.6 score
- Legal terms present = 0.4 score

**Relevance (20%)**
- LLM-based assessment
- Identifies off-topic sections
- Checks focus on objective

**Quality (15%)**
- Word count (min 200 for full credit)
- Structural elements (headers, lists)
- Formatting quality

**Evidence (15%)**
- ≥10 successful actions = 1.0 score
- ≥5 successful actions = 0.8 score
- ≥3 successful actions = 0.6 score

### Sample Evaluation Output

```markdown
# Evaluation Report: Run #123

## Overview
- **Objective**: Research Croatian data protection laws
- **Status**: completed
- **Overall Score**: 0.823 / 1.0
- **Passed**: Yes
- **Threshold**: 0.75

## Evaluation Criteria

### ✓ Completeness
- **Score**: 0.85
- **Feedback**: Addresses all aspects of the objective

### ✓ Citations
- **Score**: 0.80
- **Feedback**: Found 5 citation(s). Good practice.
  - NN 94/14 (GDPR Implementation Law)
  - NN 42/18 (Personal Data Protection Act)
  - čl. 15 ZZOPDI

### ✓ Relevance
- **Score**: 0.82
- **Feedback**: Content highly relevant to objective

### ✓ Quality
- **Score**: 0.90
- **Feedback**: Well-structured with clear sections

### ✓ Evidence
- **Score**: 0.75
- **Feedback**: Adequate evidence from 8 research actions
```

---

## Scheduling

### Automatic Weekly Research

The agent automatically runs research every Sunday at 02:00 AM:

```php
// app/Console/Kernel.php
$schedule->command('agent:research-scheduled --max-iterations=15 --time-limit=1800')
    ->weekly()
    ->sundays()
    ->at('02:00')
    ->withoutOverlapping()
    ->runInBackground();
```

### Manual Scheduled Run

```bash
php artisan agent:research-scheduled \
  --topics="employment law" \
  --topics="contract obligations" \
  --max-iterations=15 \
  --time-limit=1800 \
  --cost-budget=2.00
```

If no topics specified, the command automatically finds the top 3 topics from recent successful runs.

---

## Development

### Adding New Tools

1. Add method to `AgentToolbox`:

```php
public function myCustomTool(array $params): array
{
    // Implementation
    return ['success' => true, 'data' => $result];
}
```

2. Update `executeActions()` in `AutonomousResearchAgent`:

```php
$result = match ($tool) {
    'my_custom_tool' => $this->toolbox->myCustomTool($params),
    // ... existing tools
};
```

3. Document in agent instructions.

### Adding Evaluation Criteria

1. Add method to `AgentEvaluationService`:

```php
protected function evaluateMyNewCriterion(AgentRun $run, string $output): array
{
    // Evaluation logic
    return [
        'score' => $score,
        'passed' => $score >= 0.7,
        'feedback' => 'Explanation',
    ];
}
```

2. Update weights in `config/agent.php`:

```php
'evaluation' => [
    'weights' => [
        // ... existing
        'my_new_criterion' => 0.10,
    ],
],
```

3. Add to `evaluateRun()` checks array.

### Testing

Run a test research:

```bash
php artisan tinker
```

```php
$agent = app(\App\Agents\AutonomousResearchAgent::class);

$run = $agent->startRun(
    'Research Croatian contract law basics',
    ['topics' => ['contracts', 'obligations']],
    ['max_iterations' => 3, 'time_limit_seconds' => 120]
);

$completed = $agent->executeRun($run);

echo $completed->final_output;
```

---

## Troubleshooting

### Common Issues

**1. "Class not found" errors**

Make sure to run:
```bash
composer dump-autoload
```

**2. Queue jobs not processing**

Start queue worker:
```bash
php artisan queue:work --queue=agents
```

**3. Timeout errors**

Increase time limits:
```env
AGENT_TIME_LIMIT=1200
AGENT_MAX_TIME=7200
```

**4. High costs**

Set budget limits:
```env
AGENT_COST_BUDGET=1.00
AGENT_MAX_COST=5.00
```

### Debugging

Enable detailed logging:

```env
AGENT_LOG_LEVEL=debug
AGENT_LOG_ITERATIONS=true
AGENT_LOG_ACTIONS=true
```

Check logs:
```bash
tail -f storage/logs/laravel.log
```

---

## Performance Tips

1. **Use caching** - Enable vector search caching for repeated queries
2. **Async execution** - Use background jobs for long-running research
3. **Limit iterations** - Start with low max_iterations for testing
4. **Monitor costs** - Set cost_budget to prevent overspending
5. **Batch operations** - Group similar research objectives

---

## Security Considerations

1. **Add authentication** - Protect API endpoints in production
2. **Rate limiting** - Prevent abuse of research endpoints
3. **Budget caps** - Always set AGENT_MAX_COST in production
4. **Validate inputs** - Sanitize user-provided objectives
5. **Monitor runs** - Track unusual activity patterns

---

## Credits

Built with:
- Laravel 12
- Vizra ADK (Agent framework)
- OpenAI GPT-4o-mini
- PostgreSQL + pgvector
- Neo4j

---

## License

Same as parent project.

---

## Support

For issues or questions:
- Check logs: `storage/logs/laravel.log`
- Review config: `config/agent.php`
- See API docs: `AGENT_API.md`
- GitHub Issues: [Repository](https://github.com/aglavas/ai-legal-war-machine/issues)

# Autonomous Agent System Documentation

This document provides a comprehensive guide to the autonomous agent system in the AI Legal War Machine, including agent architecture, plan validation, and best practices.

**Last Updated**: 2025-11-10

---

## Table of Contents

1. [Overview](#overview)
2. [Agent Architecture](#agent-architecture)
3. [Agent Plan Structure](#agent-plan-structure)
4. [Plan Validation](#plan-validation)
5. [Available Tools](#available-tools)
6. [Usage Examples](#usage-examples)
7. [Best Practices](#best-practices)
8. [Testing](#testing)

---

## Overview

The AI Legal War Machine employs a **multi-agent architecture** where specialized agents autonomously perform legal research, analysis, and decision-making. Each agent:

- **Plans** its own research actions using LLM reasoning
- **Executes** actions using a comprehensive toolbox (vector search, graph queries, web fetch)
- **Evaluates** results to determine next steps or stopping criteria
- **Validates** plans before execution to ensure correctness

### Key Components

- **`app/Agents/`** - Agent implementations (AutonomousResearchAgent, DecisionDiscoveryAgent, OdlukeAgent, etc.)
- **`app/Agents/Validation/`** - Plan validation system (NEW in Sprint 17)
- **`app/Services/`** - Supporting services (AgentEvaluationService, AgentToolbox, etc.)
- **`app/Models/AgentRun.php`** - Database model for tracking agent executions

---

## Agent Architecture

### Base Agent Pattern

All autonomous agents follow the **Plan → Act → Evaluate** loop:

```
┌──────────────────────────────────────────────────┐
│                  AGENT LOOP                      │
├──────────────────────────────────────────────────┤
│                                                  │
│  1. PLAN                                         │
│     ├─ Analyze current state                    │
│     ├─ Generate research questions              │
│     ├─ Select tools and parameters              │
│     └─ Validate plan structure ← NEW!           │
│                                                  │
│  2. ACT                                          │
│     ├─ Execute planned actions                  │
│     ├─ Search laws, decisions, cases            │
│     ├─ Query knowledge graph                    │
│     └─ Fetch external resources                 │
│                                                  │
│  3. EVALUATE                                     │
│     ├─ Extract insights from results            │
│     ├─ Score quality (completeness, citations)  │
│     ├─ Check stopping criteria                  │
│     └─ Decide: continue or stop                 │
│                                                  │
│  4. REPEAT (if not stopped)                      │
│                                                  │
└──────────────────────────────────────────────────┘
```

### Example: AutonomousResearchAgent

Located in `app/Agents/AutonomousResearchAgent.php` (deprecated, now `app/Services/ResearchOrchestrator.php`):

```php
protected function executeIteration(AgentRun $run): array
{
    // 1. PLAN: LLM generates research plan
    $plan = $this->planNextStep($run);

    // 2. VALIDATE: Ensure plan is valid (NEW!)
    $validator = new AgentPlanValidator();
    if (!$validator->validate($plan)) {
        throw new \Exception('Invalid plan: ' . implode(', ', $validator->getErrors()));
    }

    // 3. ACT: Execute planned actions
    $actionResults = $this->executeActions($plan['actions'], $run);

    // 4. EVALUATE: Extract insights
    $evaluation = $this->evaluateIteration($run, $iteration);

    return $iteration;
}
```

---

## Agent Plan Structure

Agent plans are JSON objects that define what actions the agent will take in a research iteration.

### Plan Schema

```json
{
  "reasoning": "Analysis of current state and what's needed next",
  "next_focus": "What aspect to focus on this iteration",
  "should_stop": false,
  "actions": [
    {
      "tool": "law_vector_search",
      "params": {
        "query": "nezakonit otkaz radnika",
        "limit": 5
      },
      "rationale": "Why this action will help achieve the objective"
    }
  ]
}
```

### Required Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `reasoning` | string | Yes | Explanation of what we know and what's needed (min 10 chars) |
| `next_focus` | string | No | Summary of this iteration's focus |
| `should_stop` | boolean | No | Whether to stop research (default: false) |
| `actions` | array | Yes | Array of action objects (can be empty if should_stop=true) |

### Action Structure

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `tool` | string | Yes | Tool name from [available tools](#available-tools) |
| `params` | object | Yes | Tool-specific parameters |
| `rationale` | string | Recommended | Explanation of why this action is needed |

---

## Plan Validation

**NEW in Sprint 17**: The `AgentPlanValidator` ensures that agent-generated plans conform to expected structure before execution, preventing runtime errors and improving reliability.

### AgentPlanValidator

Location: `app/Agents/Validation/AgentPlanValidator.php`

#### Features

- ✅ **Structure Validation**: Validates plan has required fields (reasoning, actions)
- ✅ **Tool Validation**: Ensures tools are from the list of 17 valid tools
- ✅ **Parameter Validation**: Tool-specific parameter requirements (e.g., `law_lookup` requires `law_number`)
- ✅ **Logic Validation**: Plans with `should_stop=true` can have empty actions
- ✅ **Configurable Limits**: Max/min actions per plan (default: 1-10 actions)
- ✅ **Error vs Warning**: Distinguishes critical errors from recommendations
- ✅ **URL Validation**: Validates URLs for `web_fetch` tool
- ✅ **Length Validation**: Reasoning must be 10-2000 characters

#### Basic Usage

```php
use App\Agents\Validation\AgentPlanValidator;

$validator = new AgentPlanValidator();

$plan = [
    'reasoning' => 'We need to search Croatian labor laws for termination procedures',
    'actions' => [
        [
            'tool' => 'law_vector_search',
            'params' => [
                'query' => 'nezakonit otkaz',
                'limit' => 5,
            ],
            'rationale' => 'Find laws related to unlawful termination',
        ],
    ],
];

if ($validator->validate($plan)) {
    // Plan is valid, proceed with execution
    $results = $this->executeActions($plan['actions']);
} else {
    // Plan is invalid, log errors
    $errors = $validator->getErrors();
    Log::error('Invalid agent plan', ['errors' => $errors]);

    // Optionally check warnings (non-critical issues)
    $warnings = $validator->getWarnings();
    if (!empty($warnings)) {
        Log::warning('Plan validation warnings', ['warnings' => $warnings]);
    }
}
```

#### Advanced Configuration

```php
$validator = new AgentPlanValidator();

// Set custom limits
$validator->setMaxActions(20);  // Allow up to 20 actions per plan
$validator->setMinActions(2);   // Require at least 2 actions (unless stopping)

// Validate plan
$isValid = $validator->validate($plan);

// Get all validation messages
$messages = $validator->getMessages();
// Returns: ['errors' => [...], 'warnings' => [...]]
```

#### Validation Rules

##### Reasoning Validation

- Must be non-empty string
- Minimum 10 characters
- Maximum 2000 characters (warns if exceeded)

##### Actions Validation

- Must be array
- Must have at least `minActions` actions (default: 1) unless `should_stop=true`
- Must have at most `maxActions` actions (default: 10)
- Each action must have:
  - `tool`: Valid tool name from [available tools](#available-tools)
  - `params`: Object or array with tool-specific required parameters
  - `rationale`: String (recommended, generates warning if missing)

##### Tool-Specific Parameter Requirements

| Tool | Required Parameters | Validation |
|------|-------------------|------------|
| `law_vector_search` | `query` | Non-empty string |
| `law_keyword_search` | `query` | Non-empty string |
| `law_hybrid_search` | `query` | Non-empty string |
| `law_lookup` | `law_number` | Non-empty string |
| `law_get_article` | `doc_id` | Non-empty string |
| `decision_vector_search` | `query` | Non-empty string |
| `decision_keyword_search` | `query` | Non-empty string |
| `decision_hybrid_search` | `query` | Non-empty string |
| `decision_get` | `id` | Non-empty string |
| `case_vector_search` | `query` | Non-empty string |
| `case_search` | `query` | Non-empty string |
| `case_document_search` | `query` | Non-empty string |
| `graph_query` | `cypher` | Non-empty string |
| `web_fetch` | `url` | Valid URL format |
| `note_save` | `content` | Non-empty string |

##### Special Cases

**Stopping Plans**: Plans with `should_stop=true` can have empty `actions` array:

```php
$plan = [
    'reasoning' => 'We have found sufficient information to answer the research objective',
    'should_stop' => true,
    'actions' => [],  // Empty actions is OK when stopping
];

$validator->validate($plan);  // Returns true
```

**Warning vs Error**: Warnings don't fail validation but indicate potential issues:

```php
$plan = [
    'reasoning' => 'Search for labor laws',
    'actions' => [
        [
            'tool' => 'law_vector_search',
            'params' => ['query' => 'labor law'],
            // Missing 'rationale' - generates WARNING, not ERROR
        ],
    ],
];

$validator->validate($plan);  // Returns true
$warnings = $validator->getWarnings();
// ['Action #1: missing recommended 'rationale' field']
```

#### Integration with Agents

Integrate validation into the planning phase:

```php
protected function planNextStep(AgentRun $run): array
{
    // 1. Generate plan using LLM
    $response = $this->openai->chat([
        ['role' => 'system', 'content' => $this->instructions],
        ['role' => 'user', 'content' => $this->buildPlanningPrompt($run)],
    ], [
        'model' => 'gpt-4o-mini',
        'response_format' => ['type' => 'json_object'],
    ]);

    $plan = json_decode($response['choices'][0]['message']['content'], true);

    // 2. Validate plan before execution
    $validator = new AgentPlanValidator();
    if (!$validator->validate($plan)) {
        Log::error('LLM generated invalid plan', [
            'errors' => $validator->getErrors(),
            'plan' => $plan,
        ]);

        // Return fallback plan instead of failing
        return $this->getFallbackPlan($run);
    }

    // 3. Log warnings if any
    $warnings = $validator->getWarnings();
    if (!empty($warnings)) {
        Log::warning('Plan validation warnings', ['warnings' => $warnings]);
    }

    return $plan;
}
```

---

## Available Tools

The agent toolbox provides 17 specialized tools for legal research. These tools are validated by `AgentPlanValidator`.

### Law Search Tools

#### 1. `law_vector_search`

Semantic search across Croatian laws using vector embeddings.

```json
{
  "tool": "law_vector_search",
  "params": {
    "query": "nezakonit otkaz radnika",
    "limit": 5,
    "jurisdiction": "HR"
  }
}
```

**Parameters**:
- `query` (string, required): Search query
- `limit` (int, optional): Max results (default: 10)
- `jurisdiction` (string, optional): Filter by jurisdiction

#### 2. `law_keyword_search`

Exact text matching in laws.

```json
{
  "tool": "law_keyword_search",
  "params": {
    "query": "Zakon o radu",
    "law_number": "NN 93/14",
    "limit": 10
  }
}
```

**Parameters**:
- `query` (string, required): Keyword search query
- `law_number` (string, optional): Filter by law number
- `jurisdiction` (string, optional): Filter by jurisdiction
- `limit` (int, optional): Max results

#### 3. `law_hybrid_search`

Combined vector + keyword search.

```json
{
  "tool": "law_hybrid_search",
  "params": {
    "query": "prestanak radnog odnosa",
    "limit": 10
  }
}
```

#### 4. `law_lookup`

Find specific law by law number.

```json
{
  "tool": "law_lookup",
  "params": {
    "law_number": "NN 93/14",
    "jurisdiction": "HR"
  }
}
```

**Parameters**:
- `law_number` (string, required): Law identifier (e.g., "NN 93/14")
- `jurisdiction` (string, optional): Jurisdiction code

#### 5. `law_get_article`

Get specific article from a law document.

```json
{
  "tool": "law_get_article",
  "params": {
    "doc_id": "law-doc-123",
    "chunk_index": 5
  }
}
```

**Parameters**:
- `doc_id` (string, required): Document ID
- `chunk_index` (int, optional): Specific chunk/article index

### Decision Search Tools

#### 6. `decision_vector_search`

Semantic search across court decisions.

```json
{
  "tool": "decision_vector_search",
  "params": {
    "query": "otkaz ugovora o radu bez obrazloženja",
    "court": "Vrhovni sud Republike Hrvatske",
    "limit": 10
  }
}
```

**Parameters**:
- `query` (string, required): Search query
- `court` (string, optional): Filter by court name
- `limit` (int, optional): Max results

#### 7. `decision_keyword_search`

Exact text matching in court decisions.

```json
{
  "tool": "decision_keyword_search",
  "params": {
    "query": "Gž-1234/2023",
    "case_number": "Gž-1234/2023",
    "court": "Vrhovni sud",
    "date_from": "2023-01-01",
    "limit": 20
  }
}
```

#### 8. `decision_hybrid_search`

Combined search for court decisions.

```json
{
  "tool": "decision_hybrid_search",
  "params": {
    "query": "radni spor",
    "limit": 15
  }
}
```

#### 9. `decision_lookup`

Lookup decision by specific criteria.

```json
{
  "tool": "decision_lookup",
  "params": {
    "court": "Županijski sud u Osijeku",
    "case_number": "Gž-123/2024",
    "date_from": "2024-01-01",
    "date_to": "2024-12-31"
  }
}
```

#### 10. `decision_get`

Get specific decision by ID.

```json
{
  "tool": "decision_get",
  "params": {
    "id": "decision-456",
    "include_content": true
  }
}
```

**Parameters**:
- `id` (string, required): Decision ID
- `include_content` (bool, optional): Include full text content

### Case Search Tools

#### 11. `case_vector_search`

Search internal case documents.

```json
{
  "tool": "case_vector_search",
  "params": {
    "query": "mobbing na radnom mjestu",
    "limit": 10
  }
}
```

#### 12. `case_search`

Search cases by metadata.

```json
{
  "tool": "case_search",
  "params": {
    "query": "John Doe",
    "limit": 20
  }
}
```

#### 13. `case_document_search`

Search within case documents.

```json
{
  "tool": "case_document_search",
  "params": {
    "query": "medical report",
    "limit": 10
  }
}
```

### Graph & External Tools

#### 14. `graph_query`

Execute Cypher queries on Neo4j knowledge graph.

```json
{
  "tool": "graph_query",
  "params": {
    "cypher": "MATCH (l:LawDocument)-[:CITES]->(cited:LawDocument) WHERE l.title CONTAINS $term RETURN cited LIMIT 5",
    "parameters": {
      "term": "Zakon o radu"
    }
  }
}
```

**Parameters**:
- `cypher` (string, required): Cypher query
- `parameters` (object, optional): Query parameters

**Common Graph Queries**:

```cypher
// Find laws cited by a specific law
MATCH (l:LawDocument {law_number: $law_number})-[:CITES]->(cited:LawDocument)
RETURN cited.title, cited.law_number

// Find decisions that cite a specific law
MATCH (d:Decision)-[:CITES]->(l:LawDocument {law_number: $law_number})
RETURN d.title, d.case_number, d.date

// Find similar documents by relationship
MATCH (source)-[r:SIMILAR_TO]->(target)
WHERE r.similarity_score >= 0.8
RETURN source, target, r.similarity_score
ORDER BY r.similarity_score DESC
```

#### 15. `web_fetch`

Fetch content from external URLs.

```json
{
  "tool": "web_fetch",
  "params": {
    "url": "https://www.zakon.hr/z/1/Zakon-o-radu",
    "timeout": 30
  }
}
```

**Parameters**:
- `url` (string, required): Valid URL to fetch
- `timeout` (int, optional): Request timeout in seconds

#### 16. `note_save`

Save insights to long-term agent memory.

```json
{
  "tool": "note_save",
  "params": {
    "content": "Article 93 of the Croatian Labor Law (NN 93/14) requires 2 weeks notice for termination",
    "namespace": "research_insights",
    "metadata": {
      "law_number": "NN 93/14",
      "article": 93
    }
  }
}
```

**Parameters**:
- `content` (string, required): Insight or note to save
- `namespace` (string, optional): Memory category (default: "research_insights")
- `metadata` (object, optional): Additional structured data

#### 17. `vector_search` (Legacy)

Original combined vector search (deprecated, use specific tools above).

```json
{
  "tool": "vector_search",
  "params": {
    "query": "labor law termination",
    "types": ["laws", "decisions"],
    "limit": 10
  }
}
```

---

## Usage Examples

### Example 1: Validating a Simple Plan

```php
use App\Agents\Validation\AgentPlanValidator;

$validator = new AgentPlanValidator();

$plan = [
    'reasoning' => 'Initial exploration of Croatian employment law termination procedures',
    'next_focus' => 'Labor law research',
    'should_stop' => false,
    'actions' => [
        [
            'tool' => 'law_vector_search',
            'params' => [
                'query' => 'nezakonit otkaz ugovora o radu',
                'limit' => 5,
            ],
            'rationale' => 'Find relevant labor law articles on termination',
        ],
    ],
];

if ($validator->validate($plan)) {
    echo "Plan is valid!\n";
} else {
    print_r($validator->getErrors());
}
```

### Example 2: Multi-Action Research Plan

```php
$complexPlan = [
    'reasoning' => 'Comprehensive research requires searching laws, decisions, and checking graph relationships',
    'next_focus' => 'Multi-faceted employment law research',
    'should_stop' => false,
    'actions' => [
        // Action 1: Vector search for laws
        [
            'tool' => 'law_vector_search',
            'params' => [
                'query' => 'Zakon o radu otkaz obrazloženje',
                'limit' => 5,
            ],
            'rationale' => 'Find labor law articles on termination justification',
        ],

        // Action 2: Search court decisions
        [
            'tool' => 'decision_hybrid_search',
            'params' => [
                'query' => 'nezakonit otkaz bez obrazloženja',
                'court' => 'Vrhovni sud Republike Hrvatske',
                'limit' => 10,
            ],
            'rationale' => 'Find Supreme Court precedents on unlawful termination',
        ],

        // Action 3: Graph query for citations
        [
            'tool' => 'graph_query',
            'params' => [
                'cypher' => 'MATCH (d:Decision)-[:CITES]->(l:LawDocument) WHERE l.law_number = $law_number RETURN d.case_number, d.title LIMIT 10',
                'parameters' => ['law_number' => 'NN 93/14'],
            ],
            'rationale' => 'Find decisions that cite the Labor Law',
        ],

        // Action 4: Save findings
        [
            'tool' => 'note_save',
            'params' => [
                'content' => 'Research shows strong case law supporting termination must be justified',
                'namespace' => 'research_insights',
            ],
            'rationale' => 'Preserve key finding for future reference',
        ],
    ],
];

$validator = new AgentPlanValidator();
$validator->setMaxActions(10);  // Allow up to 10 actions

if ($validator->validate($complexPlan)) {
    // Execute the plan
    foreach ($complexPlan['actions'] as $action) {
        $result = $toolbox->executeTool($action['tool'], $action['params']);
        // Process result...
    }
}
```

### Example 3: Handling Validation Errors

```php
$invalidPlan = [
    'reasoning' => 'Short',  // Too short (< 10 chars)
    'actions' => [
        [
            'tool' => 'law_lookup',
            'params' => [
                'jurisdiction' => 'HR',
                // Missing required 'law_number'
            ],
        ],
        [
            'tool' => 'web_fetch',
            'params' => [
                'url' => 'not-a-valid-url',  // Invalid URL format
            ],
        ],
    ],
];

$validator = new AgentPlanValidator();

if (!$validator->validate($invalidPlan)) {
    // Get detailed errors
    $errors = $validator->getErrors();

    foreach ($errors as $error) {
        echo "ERROR: $error\n";
    }

    /* Output:
     * ERROR: Plan reasoning must be at least 10 characters
     * ERROR: Action #1: law_lookup requires a 'law_number' parameter
     * ERROR: Action #2: web_fetch url must be a valid URL
     */

    // Log for debugging
    Log::error('Agent generated invalid plan', [
        'errors' => $errors,
        'plan' => $invalidPlan,
    ]);

    // Use fallback plan
    $plan = $this->getFallbackPlan();
}
```

### Example 4: Early Stopping Plan

```php
$stoppingPlan = [
    'reasoning' => 'We have found sufficient information to fully answer the research objective. Three relevant Supreme Court decisions and five applicable law articles have been identified.',
    'should_stop' => true,
    'actions' => [],  // Empty actions when stopping
];

$validator = new AgentPlanValidator();

// This is valid - stopping plans can have empty actions
if ($validator->validate($stoppingPlan)) {
    echo "Agent recommends stopping research\n";
    $this->synthesizeFinalOutput($run);
}
```

### Example 5: Custom Validation Limits

```php
// For complex research that requires many actions
$validator = new AgentPlanValidator();
$validator->setMaxActions(20);  // Allow up to 20 actions
$validator->setMinActions(3);   // Require at least 3 actions

$extensivePlan = [
    'reasoning' => 'Comprehensive multi-dimensional legal research across laws, decisions, cases, and graph relationships',
    'actions' => [
        // ... 15 different search actions
    ],
];

if ($validator->validate($extensivePlan)) {
    echo "Extensive research plan validated successfully\n";
}
```

---

## Best Practices

### 1. Always Validate Plans Before Execution

```php
// DON'T
$plan = $this->generatePlanWithLLM($objective);
$results = $this->executeActions($plan['actions']);  // Might fail!

// DO
$plan = $this->generatePlanWithLLM($objective);
$validator = new AgentPlanValidator();

if (!$validator->validate($plan)) {
    Log::error('Invalid plan generated', ['errors' => $validator->getErrors()]);
    $plan = $this->getFallbackPlan();  // Use safe fallback
}

$results = $this->executeActions($plan['actions']);
```

### 2. Log Validation Warnings

Even when validation passes, check for warnings that indicate potential issues:

```php
if ($validator->validate($plan)) {
    $warnings = $validator->getWarnings();

    if (!empty($warnings)) {
        Log::warning('Plan validation warnings', [
            'warnings' => $warnings,
            'plan' => $plan,
        ]);
    }

    // Proceed with execution
    $this->executeActions($plan['actions']);
}
```

### 3. Provide Detailed Rationales

Include `rationale` fields in actions for better explainability:

```php
// GOOD - Clear rationale
[
    'tool' => 'decision_hybrid_search',
    'params' => ['query' => 'mobbing na radnom mjestu', 'limit' => 10],
    'rationale' => 'Search for workplace mobbing precedents to support constructive dismissal claim',
]

// BAD - No rationale (generates warning)
[
    'tool' => 'decision_hybrid_search',
    'params' => ['query' => 'mobbing', 'limit' => 10],
]
```

### 4. Use Specific Tools Over Generic

```php
// BETTER - Specific tool for law search
['tool' => 'law_vector_search', 'params' => ['query' => 'labor law', 'limit' => 5]]

// WORSE - Generic vector_search (legacy)
['tool' => 'vector_search', 'params' => ['query' => 'labor law', 'types' => ['laws']]]
```

### 5. Set Appropriate Action Limits

```php
// For quick exploratory research
$validator->setMaxActions(5);
$validator->setMinActions(1);

// For comprehensive multi-dimensional research
$validator->setMaxActions(20);
$validator->setMinActions(5);

// For final refinement iterations
$validator->setMaxActions(3);
$validator->setMinActions(1);
```

### 6. Handle Fallback Gracefully

Always have a fallback plan when LLM generates invalid plans:

```php
protected function getFallbackPlan(AgentRun $run): array
{
    return [
        'reasoning' => 'LLM planning failed. Using fallback: broad vector search based on objective.',
        'next_focus' => 'Initial exploration',
        'should_stop' => false,
        'actions' => [
            [
                'tool' => 'law_vector_search',
                'params' => [
                    'query' => $run->objective,
                    'limit' => 5,
                ],
                'rationale' => 'Fallback action to gather initial information',
            ],
        ],
    ];
}
```

### 7. Validate LLM Responses

When using LLM to generate plans, validate the JSON structure:

```php
try {
    $response = $this->openai->chat([...], ['response_format' => ['type' => 'json_object']]);
    $plan = json_decode($response['choices'][0]['message']['content'], true);

    if (!is_array($plan)) {
        throw new \Exception('LLM response is not valid JSON');
    }

    $validator = new AgentPlanValidator();

    if (!$validator->validate($plan)) {
        throw new \Exception('LLM generated invalid plan: ' . implode(', ', $validator->getErrors()));
    }

    return $plan;

} catch (\Exception $e) {
    Log::error('LLM planning failed', ['error' => $e->getMessage()]);
    return $this->getFallbackPlan($run);
}
```

---

## Testing

### Testing AgentPlanValidator

Location: `tests/Unit/Agents/Validation/AgentPlanValidatorTest.php`

Run tests:

```bash
./vendor/bin/phpunit tests/Unit/Agents/Validation/AgentPlanValidatorTest.php
```

### Test Coverage

The validator has comprehensive test coverage:

- ✅ Valid plan validation
- ✅ Invalid plan rejection (missing fields, invalid tools, wrong parameters)
- ✅ Tool-specific parameter validation (all 17 tools)
- ✅ Edge cases (empty actions with should_stop, max limits, reasoning length)
- ✅ Warning generation (missing rationale)
- ✅ Complex multi-action plans

**13 tests, 48 assertions, 100% passing**

### Example Test

```php
public function test_validates_tool_specific_parameters(): void
{
    // Test law_lookup requires 'law_number'
    $plan = [
        'reasoning' => 'Testing law_lookup without law_number parameter',
        'actions' => [
            [
                'tool' => 'law_lookup',
                'params' => [
                    'jurisdiction' => 'HR',
                    // missing 'law_number'
                ],
            ],
        ],
    ];

    $validator = new AgentPlanValidator();
    $result = $validator->validate($plan);

    $this->assertFalse($result, 'law_lookup without law_number should fail');
    $this->assertStringContainsString('law_number',
        strtolower(implode(' ', $validator->getErrors())));
}
```

---

## Related Documentation

- **[AGENT_API.md](./AGENT_API.md)** - REST API endpoints for agents
- **[AGENT_ANALYSIS.md](./AGENT_ANALYSIS.md)** - Agent capability analysis and grading
- **[AGENT_MONITORING_API.md](./AGENT_MONITORING_API.md)** - Monitoring and observability
- **[MULTI_AGENT_COLLABORATION.md](./MULTI_AGENT_COLLABORATION.md)** - Multi-agent orchestration
- **[TESTING_GUIDE.md](./TESTING_GUIDE.md)** - Testing best practices

---

## Changelog

### 2025-11-10 - Sprint 17: Agent Hardening

**Added**:
- `AgentPlanValidator` class for validating agent-generated plans
- Comprehensive validation for all 17 agent tools
- Tool-specific parameter validation
- Error vs warning distinction
- Configurable action limits
- 13 comprehensive tests with 48 assertions

**Impact**:
- Prevents runtime errors from invalid LLM-generated plans
- Improves agent reliability and debuggability
- Enables better error reporting and logging
- Foundation for future plan optimization and refinement

---

## Support

For questions or issues:

- **Codebase**: Check `app/Agents/` and `app/Services/` directories
- **Configuration**: `config/agent.php`
- **Logs**: `storage/logs/laravel.log`
- **Tests**: `tests/Unit/Agents/` and `tests/Feature/Agent/`

---

**Version**: 1.0 (Sprint 17)
**Last Updated**: 2025-11-10
**Status**: Active

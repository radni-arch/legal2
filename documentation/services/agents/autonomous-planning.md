# Autonomous Agent Planning

## Overview

The `AutonomousResearchAgent` uses Large Language Model (LLM) based reasoning to make intelligent planning decisions during research iterations. Instead of following a hardcoded script, the agent analyzes the objective, reviews previous findings, and dynamically determines the best course of action.

This document explains how the autonomous planning system works and how the agent adapts its research strategy based on what it learns.

## Planning Algorithm

The agent follows a **Plan → Act → Evaluate** loop:

1. **Analyze Objective and Context**
   - Review the research objective
   - Examine all previous iterations and their outcomes
   - Identify what information has been gathered so far

2. **Review Previous Iterations**
   - What actions were taken and were they successful?
   - What insights were discovered?
   - What patterns or gaps emerged?

3. **Identify Knowledge Gaps**
   - What aspects of the objective remain unexplored?
   - What questions are still unanswered?
   - What related areas should be investigated?

4. **Generate Specific Actions**
   - Select appropriate tools for the next research phase
   - Formulate specific queries and parameters
   - Provide rationale for each action

5. **Provide Reasoning**
   - Explain why these actions will help make progress
   - Justify the research strategy
   - Determine if research can be concluded

## Planning Prompt Structure

The agent sends a comprehensive prompt to the LLM that includes:

```
You are an autonomous legal research agent. You must decide what to investigate next.

YOUR OBJECTIVE:
[The research goal]

WHAT YOU'VE LEARNED SO FAR:
Topics of Interest:
- [Identified topics]

Previous Research Iterations:
Iteration 1:
  Plan: [What was planned]
  Actions taken: [What was executed]
  Insights discovered: [What was learned]

AVAILABLE TOOLS:
[Detailed descriptions of all tools with parameters]

YOUR TASK:
Analyze the objective and previous findings, then decide what specific actions
to take next to make progress toward completing the research.

CONSTRAINTS:
- Current iteration: X / Y
- You can take 1-3 actions in this iteration
- Each action must use one of the available tools
- Be strategic: don't repeat searches you've already done
- If you've found sufficient information, you can recommend stopping

RESPONSE FORMAT (JSON):
{
    "reasoning": "Your analysis of what we know and what's still needed",
    "next_focus": "What aspect to focus on in this iteration",
    "should_stop": false,
    "actions": [...]
}
```

## LLM Response Format

The LLM returns a structured JSON response:

```json
{
    "reasoning": "We have found relevant laws but need to investigate court precedents to understand how these laws are applied in practice.",
    "next_focus": "Court decisions interpreting labor law termination",
    "should_stop": false,
    "actions": [
        {
            "tool": "decision_lookup",
            "params": {
                "court": "Supreme Court",
                "jurisdiction": "HR",
                "from_date": "2020-01-01",
                "limit": 10
            },
            "rationale": "Find recent Supreme Court decisions that interpret termination laws"
        },
        {
            "tool": "vector_search",
            "params": {
                "query": "employment termination without notice precedent",
                "types": ["cases", "decisions"],
                "limit": 5
            },
            "rationale": "Semantic search for cases discussing termination without notice"
        }
    ]
}
```

### Response Fields

- **reasoning** (string, required): Detailed analysis of current state and knowledge gaps
- **next_focus** (string, optional): High-level focus for this iteration
- **should_stop** (boolean, required): Whether research should conclude
- **actions** (array, required): List of actions to execute (empty if should_stop is true)
  - **tool** (string): One of the available tools
  - **params** (object): Tool-specific parameters
  - **rationale** (string): Why this action helps achieve the objective

## Available Tools

The agent has access to the following tools:

### 1. vector_search
Semantic search across laws, court decisions, and cases.

**Parameters:**
- `query` (string): Search query
- `types` (array): Which corpora to search: ['laws', 'cases', 'decisions']
- `limit` (int): Max results (default: 10)
- `jurisdiction` (string, optional): Filter by jurisdiction (e.g., 'HR')
- `min_similarity` (float, optional): Minimum similarity score (default: 0.7)

**Use When:**
- Starting broad exploration of a topic
- Finding documents semantically related to a concept
- Cross-corpus search needed

### 2. law_lookup
Find specific law by law number.

**Parameters:**
- `law_number` (string): Law identifier (e.g., "NN 94/14")
- `jurisdiction` (string, optional): Jurisdiction code

**Use When:**
- A specific law was referenced and needs full text
- Following up on citations found in other documents

### 3. decision_lookup
Find court decisions by criteria.

**Parameters:**
- `case_number` (string, optional): Case number to search
- `court` (string, optional): Court name
- `jurisdiction` (string, optional): Jurisdiction
- `from_date` (string, optional): Start date (Y-m-d)
- `to_date` (string, optional): End date (Y-m-d)
- `decision_type` (string, optional): Type of decision
- `limit` (int): Max results (default: 20)

**Use When:**
- Looking for court precedents on a topic
- Filtering decisions by time period or court
- Following up on case law research

### 4. graph_query
Query Neo4j graph database for legal relationships.

**Parameters:**
- `cypher` (string): Cypher query
- `parameters` (object): Query parameters

**Use When:**
- Finding citation relationships between laws
- Discovering which laws reference or are referenced by others
- Exploring legal document networks

### 5. web_fetch
Fetch external web content.

**Parameters:**
- `url` (string): URL to fetch
- `timeout` (int, optional): Request timeout in seconds

**Use When:**
- Need external legal commentary or analysis
- Following up on references to external sources
- Checking official government publications

### 6. note_save
Save insights to long-term memory.

**Parameters:**
- `content` (string): Insight to save
- `namespace` (string, optional): Memory category
- `metadata` (object, optional): Additional context

**Use When:**
- Important finding should be preserved
- Key insight needs to be remembered across runs
- Building knowledge base for future research

## Decision Tree Examples

### Example 1: Initial Research (Iteration 0)

**Context:**
- No previous iterations
- Objective: "Research Croatian labor law regarding termination of employment without notice"

**LLM Decision:**
```json
{
    "reasoning": "This is our first iteration. We should start with a broad vector search to identify relevant laws and court decisions on employment termination. We'll focus on Croatian jurisdiction and look across laws and decisions to get comprehensive coverage.",
    "next_focus": "Initial broad search for termination laws and precedents",
    "should_stop": false,
    "actions": [
        {
            "tool": "vector_search",
            "params": {
                "query": "otkaz ugovora o radu bez otkaznog roka",
                "types": ["laws", "decisions"],
                "jurisdiction": "HR",
                "limit": 10
            },
            "rationale": "Croatian language search to find laws and decisions about employment termination without notice period"
        },
        {
            "tool": "graph_query",
            "params": {
                "cypher": "MATCH (l:LawDocument) WHERE l.title CONTAINS 'Zakon o radu' RETURN l.title, l.law_number, l.jurisdiction LIMIT 5",
                "parameters": {}
            },
            "rationale": "Find Labor Law documents in the graph to identify the primary legislative framework"
        }
    ]
}
```

### Example 2: Following Up on Findings (Iteration 2)

**Context:**
- Previous iterations found relevant laws (Zakon o radu NN 93/14)
- Need to understand how laws are applied in practice

**LLM Decision:**
```json
{
    "reasoning": "We've identified the main Labor Law (NN 93/14) that covers termination. However, we lack information on how courts interpret 'extraordinary termination' provisions. We should search for court decisions that apply these laws and look for patterns in how courts rule on termination disputes.",
    "next_focus": "Court precedents on extraordinary termination",
    "should_stop": false,
    "actions": [
        {
            "tool": "decision_lookup",
            "params": {
                "court": "Vrhovni sud",
                "jurisdiction": "HR",
                "from_date": "2015-01-01",
                "limit": 15
            },
            "rationale": "Supreme Court decisions since 2015 provide authoritative interpretation of termination law"
        },
        {
            "tool": "vector_search",
            "params": {
                "query": "izvanredni otkaz ugovora o radu sudska praksa",
                "types": ["decisions"],
                "jurisdiction": "HR",
                "limit": 8
            },
            "rationale": "Semantic search for case law specifically about extraordinary termination"
        },
        {
            "tool": "law_lookup",
            "params": {
                "law_number": "NN 93/14"
            },
            "rationale": "Retrieve full text of Labor Law to review specific termination articles"
        }
    ]
}
```

### Example 3: Early Stop (Iteration 4)

**Context:**
- Found comprehensive law coverage
- Found multiple relevant court decisions
- Identified key principles and precedents

**LLM Decision:**
```json
{
    "reasoning": "We have successfully gathered comprehensive information on employment termination without notice in Croatian law. We found: (1) The governing legislation (Zakon o radu NN 93/14, Articles 115-116), (2) 12 relevant Supreme Court decisions clarifying extraordinary termination grounds, (3) Lower court precedents showing consistent interpretation. The research objective has been fulfilled.",
    "next_focus": "Research complete",
    "should_stop": true,
    "actions": []
}
```

## Fallback Behavior

If LLM planning fails (API error, timeout, invalid JSON), the agent uses a safe fallback plan:

```php
[
    'reasoning' => 'LLM planning failed. Using fallback: broad vector search based on objective.',
    'next_focus' => 'Initial exploration',
    'should_stop' => false,
    'actions' => [
        [
            'tool' => 'vector_search',
            'params' => [
                'query' => $run->objective,
                'types' => ['laws', 'decisions'],
                'limit' => 5,
            ],
            'rationale' => 'Fallback action to gather initial information',
        ],
    ],
]
```

This ensures research can continue even if the LLM service is unavailable.

## Validation

Before executing a plan, the agent validates:

1. **Required Fields**: `reasoning` and `actions` must be present
2. **Actions Array**: Must be an array (can be empty if `should_stop` is true)
3. **Stop Logic**: If not stopping, must have at least one action
4. **Action Structure**: Each action must have `tool` and `params`
5. **Valid Tools**: Tool must be one of the 6 available tools

If validation fails, the agent falls back to the safe default plan.

## Monitoring & Debugging

### Log Messages

The agent logs planning decisions:

```php
Log::info('Agent planned next step', [
    'run_id' => $run->id,
    'iteration' => $run->current_iteration,
    'reasoning' => $plan['reasoning'],
    'actions_count' => count($plan['actions']),
]);
```

On planning failure:

```php
Log::error('Planning failed, using fallback', [
    'run_id' => $run->id,
    'error' => $e->getMessage(),
]);
```

On early stop:

```php
Log::info('Agent recommending early stop', [
    'run_id' => $run->id,
    'iteration' => $iteration['number'],
    'reason' => $plan['reasoning'],
]);
```

### Inspecting Plans

View the plan for each iteration in the AgentRun model:

```php
$run = AgentRun::find($id);
foreach ($run->iterations as $iter) {
    echo "Iteration {$iter['number']}:\n";
    echo "Reasoning: {$iter['plan']['reasoning']}\n";
    echo "Actions: " . count($iter['plan']['actions']) . "\n";
    if ($iter['stopped_early'] ?? false) {
        echo "Stopped early: {$iter['stop_reason']}\n";
    }
}
```

## Troubleshooting

### Issue: Agent repeats same searches

**Cause**: LLM not properly tracking previous actions

**Solution**:
- Ensure `buildPlanningContext()` shows all previous actions
- Add explicit instruction to avoid redundant searches
- Increase temperature slightly for more creative planning

### Issue: Agent stops too early

**Cause**: LLM too conservative with `should_stop`

**Solution**:
- Increase `max_iterations` constraint
- Adjust prompt to emphasize thoroughness
- Check if objective is too vague

### Issue: Agent generates invalid tool parameters

**Cause**: LLM misunderstanding tool specifications

**Solution**:
- Add parameter validation before execution
- Improve tool descriptions with more examples
- Add retry logic with corrected prompt

### Issue: Planning takes too long

**Cause**: LLM response slow or timing out

**Solution**:
- Reduce `max_tokens` to 500-800
- Set explicit timeout on OpenAI call (30 seconds)
- Consider caching plans for similar contexts

### Issue: High API costs

**Cause**: Too many planning calls

**Solution**:
- Reduce `max_iterations`
- Use a cheaper model (gpt-4o-mini is already cost-effective)
- Implement plan caching for similar objectives

## Cost Estimation

**Per Planning Call:**
- Input: ~800 tokens (prompt + context)
- Output: ~200 tokens (JSON plan)
- Total: ~1000 tokens per call
- Cost with gpt-4o-mini: $0.00015 per call

**Per Research Run (10 iterations):**
- Planning: 10 calls × $0.00015 = $0.0015
- Evaluation: 10 calls × $0.0001 = $0.001
- Total: ~$0.0025 per run

**Monthly (1000 runs):**
- Cost: $2.50/month

Very affordable for production use.

## Best Practices

1. **Clear Objectives**: Provide specific, well-defined research objectives
2. **Topic Hints**: Include relevant topics in context to guide initial search
3. **Iteration Limits**: Set reasonable max_iterations (5-15 typical)
4. **Monitor Logs**: Review planning decisions to understand agent behavior
5. **Validate Results**: Check final output quality and coverage
6. **Cost Monitoring**: Track token usage and costs over time
7. **Fallback Testing**: Ensure fallback plan works when LLM unavailable

## Future Enhancements

Potential improvements to the planning system:

1. **Learning from History**: Use past successful runs to improve planning
2. **Tool Performance Metrics**: Track which tools are most effective
3. **Dynamic Tool Selection**: Add/remove tools based on objective type
4. **Multi-Agent Collaboration**: Coordinate multiple agents on complex tasks
5. **Explanation Generation**: Provide human-readable explanations of strategy
6. **Budget-Aware Planning**: Consider token/cost budgets in action selection
7. **Quality Prediction**: Estimate research quality before execution
8. **Adaptive Temperature**: Adjust creativity based on iteration progress

# TASK 2.1: Implement LLM-Based Planning in AutonomousResearchAgent

**Sprint:** 2 - Autonomous Agent Intelligence
**Priority:** P0 - BLOCKER 🚨 CRITICAL
**Estimated Time:** 8 hours
**Complexity:** High
**Status:** TODO

---

## **OBJECTIVE**

Replace the hardcoded planning logic in `AutonomousResearchAgent` with actual LLM-based reasoning. This is **THE MOST CRITICAL TASK** - without this, the agent is not truly autonomous.

---

## **CURRENT STATE (BROKEN)**

File: `app/Agents/AutonomousResearchAgent.php:227-244`

**The Problem:**
```php
protected function planNextStep(AgentRun $run): array
{
    $prompt = "Based on the objective and previous research, what should we investigate next?\n\n{$context}\n\n" .
              "Provide a structured plan with 1-3 specific actions to take.";

    // ❌ LLM IS NEVER CALLED HERE!
    // The prompt is created but NEVER sent to OpenAI

    // ❌ This just returns hardcoded logic
    $plan = [
        'reasoning' => 'Determining next research steps based on objective and previous findings',
        'actions' => $this->generateActions($run), // Hardcoded actions!
    ];

    return $plan;
}

protected function generateActions(AgentRun $run): array
{
    $actions = [];

    // ❌ Hardcoded decision tree, not AI reasoning
    if ($run->current_iteration === 0) {
        $actions[] = [
            'tool' => 'vector_search',
            'params' => ['query' => $run->objective, 'types' => ['laws', 'cases', 'decisions'], 'limit' => 5],
        ];
    } else {
        // More hardcoded logic...
    }

    return $actions;
}
```

**Why This Is Critical:**
- Agent follows a fixed script, not intelligent adaptation
- Can't learn from previous iterations
- Can't adjust strategy based on findings
- Can't reason about what information is missing
- **Not actually "autonomous"**

---

## **DESIRED STATE**

The agent should:
1. ✅ Send planning prompt to OpenAI with full context
2. ✅ LLM analyzes: objective, previous findings, available tools
3. ✅ LLM decides: what to investigate next and why
4. ✅ Returns structured JSON with reasoning + specific actions
5. ✅ Agent executes LLM-generated actions
6. ✅ Adapts strategy based on what it learned

---

## **IMPLEMENTATION STEPS**

### **Step 1: Update `planNextStep()` Method**

Replace the entire method at line 227-244:

```php
protected function planNextStep(AgentRun $run): array
{
    $previousIterations = $run->iterations ?? [];
    $context = $this->buildPlanningContext($run, $previousIterations);

    // Build comprehensive planning prompt
    $planningPrompt = $this->buildPlanningPrompt($run, $context);

    try {
        // ACTUALLY CALL THE LLM!
        $response = $this->openai->chat([
            ['role' => 'system', 'content' => $this->instructions],
            ['role' => 'user', 'content' => $planningPrompt],
        ], [
            'model' => $this->model,
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.7, // Some creativity, but not too much
            'max_tokens' => 1000,
        ]);

        // Parse LLM response
        $planJson = $response['choices'][0]['message']['content'];
        $plan = json_decode($planJson, true);

        // Validate the plan structure
        if (!$this->validatePlan($plan)) {
            throw new \Exception('Invalid plan structure from LLM');
        }

        // Log the planning decision
        Log::info('Agent planned next step', [
            'run_id' => $run->id,
            'iteration' => $run->current_iteration,
            'reasoning' => $plan['reasoning'] ?? 'No reasoning provided',
            'actions_count' => count($plan['actions'] ?? []),
        ]);

        return $plan;

    } catch (\Exception $e) {
        Log::error('Planning failed, using fallback', [
            'run_id' => $run->id,
            'error' => $e->getMessage(),
        ]);

        // Fallback to safe default action
        return $this->getFallbackPlan($run);
    }
}
```

### **Step 2: Create `buildPlanningPrompt()` Method**

Add this new method:

```php
protected function buildPlanningPrompt(AgentRun $run, string $context): string
{
    $today = date('Y-m-d');
    $toolDescriptions = $this->getToolDescriptions();

    return <<<PROMPT
You are an autonomous legal research agent. You must decide what to investigate next based on your objective and what you've learned so far.

**YOUR OBJECTIVE:**
{$run->objective}

**WHAT YOU'VE LEARNED SO FAR:**
{$context}

**AVAILABLE TOOLS:**
{$toolDescriptions}

**YOUR TASK:**
Analyze the objective and previous findings, then decide what specific actions to take next to make progress toward completing the research.

**CONSTRAINTS:**
- Current iteration: {$run->current_iteration} / {$run->max_iterations}
- You can take 1-3 actions in this iteration
- Each action must use one of the available tools
- Be strategic: don't repeat searches you've already done
- If you've found sufficient information, you can recommend stopping

**RESPONSE FORMAT (JSON):**
{
    "reasoning": "Your analysis of what we know and what's still needed. Be specific about gaps in our knowledge.",
    "next_focus": "What aspect of the research should we focus on in this iteration?",
    "should_stop": false,
    "actions": [
        {
            "tool": "vector_search",
            "params": {
                "query": "specific query based on what we're looking for",
                "types": ["laws", "cases", "decisions"],
                "limit": 5
            },
            "rationale": "Why this action will help us make progress"
        }
    ]
}

**IMPORTANT:**
- Be specific in your queries (use legal terminology)
- Focus on filling gaps in knowledge
- Avoid redundant searches
- If sufficient information has been found, set should_stop: true
- Today's date is {$today}

Respond ONLY with valid JSON matching the format above.
PROMPT;
}
```

### **Step 3: Create `getToolDescriptions()` Method**

Add this helper method:

```php
protected function getToolDescriptions(): string
{
    return <<<TOOLS
1. vector_search
   - Purpose: Semantic search across laws, court decisions, and cases
   - Parameters:
     * query (string): Search query
     * types (array): Which corpora to search ['laws', 'cases', 'decisions']
     * limit (int): Max results (default: 10)
     * jurisdiction (string, optional): Filter by jurisdiction (e.g., 'HR')
     * min_similarity (float, optional): Minimum similarity score (default: 0.7)
   - Returns: Relevant documents with similarity scores

2. law_lookup
   - Purpose: Find specific law by law number
   - Parameters:
     * law_number (string): Law identifier (e.g., "NN 94/14")
     * jurisdiction (string, optional): Jurisdiction code
   - Returns: Complete law with all articles

3. decision_lookup
   - Purpose: Find court decisions by criteria
   - Parameters:
     * case_number (string, optional): Case number to search
     * court (string, optional): Court name
     * jurisdiction (string, optional): Jurisdiction
     * from_date (string, optional): Start date (Y-m-d)
     * to_date (string, optional): End date (Y-m-d)
     * decision_type (string, optional): Type of decision
     * limit (int): Max results (default: 20)
   - Returns: Court decisions matching criteria

4. graph_query
   - Purpose: Query Neo4j graph database for legal relationships
   - Parameters:
     * cypher (string): Cypher query
     * parameters (object): Query parameters
   - Returns: Graph query results
   - Example: Find laws cited by other laws

5. web_fetch
   - Purpose: Fetch external web content
   - Parameters:
     * url (string): URL to fetch
     * timeout (int, optional): Request timeout in seconds
   - Returns: Web page content

6. note_save
   - Purpose: Save insights to long-term memory
   - Parameters:
     * content (string): Insight to save
     * namespace (string, optional): Memory category
     * metadata (object, optional): Additional context
   - Returns: Saved memory ID
   - Use this to preserve important findings
TOOLS;
}
```

### **Step 4: Create `validatePlan()` Method**

Add validation:

```php
protected function validatePlan(array $plan): bool
{
    // Must have reasoning
    if (empty($plan['reasoning'])) {
        return false;
    }

    // Must have actions array (can be empty if should_stop is true)
    if (!isset($plan['actions']) || !is_array($plan['actions'])) {
        return false;
    }

    // If not stopping, must have at least one action
    if (empty($plan['actions']) && !($plan['should_stop'] ?? false)) {
        return false;
    }

    // Validate each action
    foreach ($plan['actions'] as $action) {
        if (empty($action['tool']) || empty($action['params'])) {
            return false;
        }

        // Tool must be valid
        $validTools = ['vector_search', 'law_lookup', 'decision_lookup', 'graph_query', 'web_fetch', 'note_save'];
        if (!in_array($action['tool'], $validTools)) {
            return false;
        }
    }

    return true;
}
```

### **Step 5: Create `getFallbackPlan()` Method**

Add safe fallback:

```php
protected function getFallbackPlan(AgentRun $run): array
{
    return [
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
    ];
}
```

### **Step 6: Update `buildPlanningContext()` Method**

Enhance the existing method (line 413-432):

```php
protected function buildPlanningContext(AgentRun $run, array $previousIterations): string
{
    $context = "**Topics of Interest:**\n";
    if (!empty($run->topics)) {
        foreach ($run->topics as $topic) {
            $context .= "- {$topic}\n";
        }
    } else {
        $context .= "- (No specific topics identified yet)\n";
    }

    $context .= "\n**Previous Research Iterations:**\n\n";

    if (empty($previousIterations)) {
        $context .= "This is the first iteration. No previous research yet.\n";
    } else {
        foreach ($previousIterations as $i => $iter) {
            $iterNum = $i + 1;
            $context .= "**Iteration {$iterNum}:**\n";

            // Show what was planned
            if (isset($iter['plan']['reasoning'])) {
                $context .= "Plan: {$iter['plan']['reasoning']}\n";
            }

            // Show what actions were taken
            if (isset($iter['actions'])) {
                $context .= "Actions taken:\n";
                foreach ($iter['actions'] as $action) {
                    $tool = $action['tool'] ?? 'unknown';
                    $success = ($action['success'] ?? false) ? '✓' : '✗';
                    $context .= "  {$success} {$tool}\n";
                }
            }

            // Show what was learned
            if (isset($iter['evaluation']['insights'])) {
                $context .= "Insights discovered:\n";
                foreach ($iter['evaluation']['insights'] as $insight) {
                    $context .= "  • {$insight}\n";
                }
            }

            $context .= "\n";
        }
    }

    return $context;
}
```

### **Step 7: Update `executeIteration()` to Handle `should_stop`**

Modify line 186-225:

```php
protected function executeIteration(AgentRun $run): array
{
    $iteration = [
        'number' => $run->current_iteration + 1,
        'started_at' => now()->toIso8601String(),
        'actions' => [],
    ];

    try {
        // Plan: What should we research next?
        $plan = $this->planNextStep($run);
        $iteration['plan'] = $plan;

        // Check if LLM recommends stopping
        if ($plan['should_stop'] ?? false) {
            $iteration['stopped_early'] = true;
            $iteration['stop_reason'] = $plan['reasoning'];
            Log::info('Agent recommending early stop', [
                'run_id' => $run->id,
                'iteration' => $iteration['number'],
                'reason' => $plan['reasoning'],
            ]);
            return $iteration;
        }

        // Act: Execute the planned actions
        $actionResults = $this->executeActions($plan['actions'] ?? [], $run);
        $iteration['actions'] = $actionResults;

        // Evaluate: Assess what we learned
        $evaluation = $this->evaluateIteration($run, $iteration);
        $iteration['evaluation'] = $evaluation;

        // Save insights to memory
        if (!empty($evaluation['insights'])) {
            $this->saveInsights($evaluation['insights'], $run);
        }

        $iteration['completed_at'] = now()->toIso8601String();
    } catch (\Exception $e) {
        $iteration['error'] = $e->getMessage();
        Log::error('Iteration failed', [
            'run_id' => $run->id,
            'iteration' => $iteration['number'],
            'error' => $e->getMessage(),
        ]);
    }

    return $iteration;
}
```

### **Step 8: Remove Old `generateActions()` Method**

Delete the hardcoded `generateActions()` method at lines 247-289. It's no longer needed!

---

## **TESTING**

### **Test 1: Basic Autonomous Planning**

```php
use App\Agents\AutonomousResearchAgent;
use App\Models\AgentRun;

// Create test agent
$agent = new AutonomousResearchAgent();

// Start a research run
$run = $agent->startRun(
    objective: "Research Croatian labor law regarding termination of employment without notice",
    context: ['topics' => ['labor law', 'termination', 'notice period']],
    constraints: [
        'max_iterations' => 5,
        'time_limit_seconds' => 300,
        'threshold' => 0.75,
    ]
);

// Execute the run
$completedRun = $agent->executeRun($run);

// Verify LLM was used for planning
$firstIteration = $completedRun->iterations[0];
assert(isset($firstIteration['plan']['reasoning']), "Plan should have LLM reasoning");
assert(isset($firstIteration['plan']['actions']), "Plan should have actions");
assert(!empty($firstIteration['plan']['reasoning']), "Reasoning should not be empty");

// Check that planning evolved across iterations
if (count($completedRun->iterations) > 1) {
    $secondIteration = $completedRun->iterations[1];
    assert(
        $secondIteration['plan']['reasoning'] !== $firstIteration['plan']['reasoning'],
        "Planning should evolve based on findings"
    );
}

echo "✓ Test passed: Agent uses LLM for planning\n";
```

### **Test 2: Verify Plan Structure**

```php
$agent = new AutonomousResearchAgent();
$run = AgentRun::factory()->create([
    'objective' => 'Test objective',
    'current_iteration' => 0,
]);

$plan = $agent->planNextStep($run);

// Verify structure
assert(isset($plan['reasoning']), "Plan must have reasoning");
assert(isset($plan['actions']), "Plan must have actions");
assert(is_array($plan['actions']), "Actions must be an array");

// Verify first action has required fields
if (!empty($plan['actions'])) {
    $firstAction = $plan['actions'][0];
    assert(isset($firstAction['tool']), "Action must specify tool");
    assert(isset($firstAction['params']), "Action must specify params");
    assert(isset($firstAction['rationale']), "Action should have rationale");
}

echo "✓ Test passed: Plan structure is valid\n";
```

### **Test 3: Early Stop Detection**

```php
// Create run with many iterations already completed
$run = AgentRun::factory()->create([
    'objective' => 'Already well-researched topic',
    'current_iteration' => 3,
    'iterations' => [
        // Mock 3 previous iterations with good insights
        ['evaluation' => ['insights' => ['Found comprehensive law coverage', 'Found 5 relevant precedents']]],
        ['evaluation' => ['insights' => ['Confirmed interpretation across multiple courts']]],
        ['evaluation' => ['insights' => ['Found authoritative commentary']]],
    ],
]);

$plan = $agent->planNextStep($run);

// LLM should potentially recommend stopping if sufficient info found
if ($plan['should_stop'] ?? false) {
    echo "✓ Test passed: Agent can recommend early stop\n";
} else {
    echo "⚠ Note: Agent didn't recommend stopping (may be valid)\n";
}
```

### **Test 4: Fallback on LLM Failure**

```php
// Mock OpenAI service to simulate failure
$mockOpenAI = Mockery::mock(\App\Services\OpenAIService::class);
$mockOpenAI->shouldReceive('chat')
    ->andThrow(new \Exception('API timeout'));

$agent = new AutonomousResearchAgent();
$agent->openai = $mockOpenAI;

$run = AgentRun::factory()->create();

$plan = $agent->planNextStep($run);

// Should get fallback plan
assert(isset($plan['actions']), "Fallback plan should have actions");
assert(count($plan['actions']) > 0, "Fallback should include at least one action");

echo "✓ Test passed: Falls back gracefully on LLM failure\n";
```

---

## **ACCEPTANCE CRITERIA**

- [ ] `planNextStep()` calls OpenAI API with planning prompt
- [ ] LLM receives full context: objective, previous findings, available tools
- [ ] LLM returns JSON with: reasoning, next_focus, should_stop, actions[]
- [ ] Each action includes: tool, params, rationale
- [ ] Plan validation ensures structure is correct
- [ ] Fallback plan works if LLM fails
- [ ] Planning context shows previous iterations and insights
- [ ] Agent can recommend early stop if sufficient info found
- [ ] All 4 tests pass successfully
- [ ] Hardcoded `generateActions()` method removed
- [ ] Planning decisions logged for debugging

---

## **DOCUMENTATION REQUIRED**

Create: `docs/agents/AUTONOMOUS_PLANNING.md`

**Required Sections:**
```markdown
# Autonomous Agent Planning

## Overview
How the agent uses LLM to make intelligent planning decisions

## Planning Algorithm
1. Analyze objective and context
2. Review previous iterations
3. Identify knowledge gaps
4. Generate specific actions
5. Provide reasoning

## Planning Prompt Structure
Example of the prompt sent to LLM

## LLM Response Format
Expected JSON structure

## Available Tools
Detailed description of each tool the agent can use

## Decision Tree Examples
### Example 1: Initial Research
[Show how agent plans first iteration]

### Example 2: Following Up
[Show how agent builds on previous findings]

### Example 3: Early Stop
[Show when agent decides to stop]

## Fallback Behavior
What happens if LLM fails

## Monitoring & Debugging
How to inspect planning decisions in logs

## Troubleshooting
Common issues and solutions
```

---

## **FILES TO MODIFY**

1. `app/Agents/AutonomousResearchAgent.php` - **PRIMARY FILE**
   - Update `planNextStep()` method (lines 227-244)
   - Add `buildPlanningPrompt()` method
   - Add `getToolDescriptions()` method
   - Add `validatePlan()` method
   - Add `getFallbackPlan()` method
   - Enhance `buildPlanningContext()` method
   - Update `executeIteration()` method
   - Remove `generateActions()` method

2. `docs/agents/AUTONOMOUS_PLANNING.md` - **NEW DOCUMENTATION**

---

## **DEPENDENCIES**

None - This is a critical standalone task.

---

## **POTENTIAL ISSUES & SOLUTIONS**

| Issue | Solution |
|-------|----------|
| LLM returns invalid JSON | Add retry with corrected prompt |
| LLM hallucinates tool names | Validate against known tool list |
| Planning takes too long | Set timeout on OpenAI call (30s) |
| LLM suggests impossible actions | Validate params before execution |
| Too many API calls (cost) | Cache plans for similar contexts |
| Agent gets stuck in loops | Track repeated actions, warn in prompt |

---

## **COST ESTIMATION**

**Per Planning Call:**
- Input: ~800 tokens (prompt + context)
- Output: ~200 tokens (JSON plan)
- Total: ~1000 tokens
- Cost: $0.00015 per call (gpt-4o-mini)

**Per Research Run (10 iterations):**
- Planning: 10 calls × $0.00015 = $0.0015
- Total: ~$0.002 per run

**Very affordable!**

---

## **COMPLETION CHECKLIST**

- [ ] Code implemented in `AutonomousResearchAgent.php`
- [ ] All 8 implementation steps completed
- [ ] All 4 tests pass
- [ ] Documentation file created (`docs/agents/AUTONOMOUS_PLANNING.md`)
- [ ] Examples in docs are working
- [ ] Cost estimation documented
- [ ] No breaking changes to existing functionality
- [ ] Reviewed for security (LLM prompt injection)
- [ ] Committed with message: "feat: Implement LLM-based planning for autonomous agent [TASK-2.1]"

---

**Last Updated:** 2025-10-26
**Status:** Ready for implementation
**Assigned To:** [Coding Agent]
**Priority:** 🚨 DO THIS FIRST - Everything else depends on true autonomy

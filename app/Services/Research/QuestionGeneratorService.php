<?php

namespace App\Services\Research;

use App\Contracts\AI\ChatServiceInterface;
use App\Contracts\Research\QuestionGeneratorInterface;
use App\Exceptions\AgentException;
use App\Models\AgentRun;
use Illuminate\Support\Facades\Log;

/**
 * Service for generating and refining research questions
 *
 * Extracted from AutonomousResearchAgent.php to handle question/action generation.
 * Uses LLM to plan strategic research actions based on objectives and context.
 *
 * Responsibilities:
 * - Generate initial research plan (3-5 actions)
 * - Refine plan based on previous results
 * - Build context for LLM planning
 * - Parse and validate LLM responses
 * - Generate fallback actions when LLM fails
 * - Deduplicate and validate actions
 */
class QuestionGeneratorService implements QuestionGeneratorInterface
{
    protected string $model = 'gpt-4o-mini';

    protected int $maxActionsPerPlan = 3;

    /**
     * Create a new QuestionGeneratorService instance
     *
     * @param  ChatServiceInterface  $chat  Chat service for LLM calls
     */
    public function __construct(
        protected ChatServiceInterface $chat
    ) {}

    /**
     * Generate research questions/actions from initial query
     *
     * @param  string  $query  The research objective
     * @param  array  $context  Additional context including:
     *                          - past_insights: Insights from previous research runs
     *                          - current_iteration: Current iteration number
     *                          - max_iterations: Maximum allowed iterations
     *                          - topics: Related topics
     *                          - run: AgentRun instance (optional)
     * @return array Plan with structure:
     *               - reasoning: Explanation of why these actions make sense
     *               - actions: Array of actions to execute
     *               Each action has:
     *               - tool: Tool name (e.g., 'law_vector_search')
     *               - params: Parameters for the tool
     *               - rationale: Why this action helps achieve the objective
     */
    public function generate(string $query, array $context = []): array
    {
        try {
            Log::info('Research plan generation initiated', [
                'query_length' => strlen($query),
                'current_iteration' => $context['current_iteration'] ?? 0,
                'has_past_insights' => ! empty($context['past_insights'] ?? []),
            ]);

            $startTime = microtime(true);

            if (empty(trim($query))) {
                throw new AgentException(
                    'Research objective query is empty',
                    AgentException::INITIALIZATION_FAILED
                );
            }

            $planningContext = $this->buildPlanningContext($query, $context);

            Log::debug('Planning context built', [
                'context_length' => strlen($planningContext),
                'insights_count' => count($context['insights'] ?? []),
            ]);

            try {
                $response = $this->chat->chat([
                    ['role' => 'system', 'content' => $this->getPlanningSystemPrompt()],
                    ['role' => 'user', 'content' => $planningContext],
                ], $this->model, [
                    'response_format' => ['type' => 'json_object'],
                    'temperature' => 0.7,
                    'max_tokens' => 1000,
                ]);

                $content = $response['choices'][0]['message']['content'] ?? null;

                if (! $content) {
                    throw new \Exception('Empty response from LLM planning');
                }

                $plan = json_decode($content, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \Exception('Invalid JSON from LLM: '.json_last_error_msg());
                }

                if (! isset($plan['reasoning']) || ! isset($plan['actions'])) {
                    throw new \Exception('Invalid plan format from LLM: missing reasoning or actions');
                }

                if (! is_array($plan['actions'])) {
                    throw new \Exception('Invalid plan format: actions is not an array');
                }

                // Validate actions have required fields
                foreach ($plan['actions'] as $index => $action) {
                    if (! is_array($action)) {
                        Log::warning('Invalid action format at index', ['index' => $index]);

                        continue;
                    }

                    if (! isset($action['tool']) || ! isset($action['params'])) {
                        throw new \Exception("Action at index {$index} missing tool or params");
                    }
                }

                // Limit actions to max allowed
                if (count($plan['actions']) > $this->maxActionsPerPlan) {
                    Log::debug('Limiting actions to max allowed', [
                        'original_count' => count($plan['actions']),
                        'max_allowed' => $this->maxActionsPerPlan,
                    ]);
                    $plan['actions'] = array_slice($plan['actions'], 0, $this->maxActionsPerPlan);
                }

                // Deduplicate actions
                $originalCount = count($plan['actions']);
                $plan['actions'] = $this->deduplicateActions($plan['actions']);
                if (count($plan['actions']) < $originalCount) {
                    Log::debug('Deduplicated actions', [
                        'original_count' => $originalCount,
                        'unique_count' => count($plan['actions']),
                    ]);
                }

                Log::info('Research plan generated via LLM', [
                    'actions_count' => count($plan['actions']),
                    'tokens_used' => $response['usage']['total_tokens'] ?? 0,
                    'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                ]);

                return $plan;

            } catch (\Exception $e) {
                Log::warning('LLM planning failed, using fallback', [
                    'error' => $e->getMessage(),
                    'query' => substr($query, 0, 100),
                ]);

                // Fallback to simple strategy
                $run = $context['run'] ?? null;
                if ($run instanceof AgentRun) {
                    $fallbackActions = $this->generateFallback($run);

                    Log::info('Research plan generated via fallback', [
                        'actions_count' => count($fallbackActions),
                        'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                    ]);

                    return [
                        'reasoning' => 'LLM planning failed, using fallback strategy: '.$e->getMessage(),
                        'actions' => $fallbackActions,
                    ];
                }

                // If no run provided, generate basic fallback
                $basicFallback = $this->generateBasicFallback($query);

                Log::info('Research plan generated via basic fallback', [
                    'actions_count' => count($basicFallback),
                    'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                ]);

                return [
                    'reasoning' => 'LLM planning failed, using basic fallback strategy: '.$e->getMessage(),
                    'actions' => $basicFallback,
                ];
            }

        } catch (\Exception $e) {
            Log::error('Research plan generation failed completely', [
                'query' => substr($query, 0, 100),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new AgentException(
                'Research plan generation failed: '.$e->getMessage(),
                AgentException::INITIALIZATION_FAILED,
                $e
            );
        }
    }

    /**
     * Refine questions based on previous results
     *
     * @param  array  $questions  Previous questions/actions that were executed
     * @param  array  $results  Results from executing those actions
     * @param  array  $evaluation  Evaluation of the previous iteration including:
     *                             - insights: Array of insights discovered
     *                             - insights_count: Number of insights found
     *                             - should_stop: Whether research should stop
     * @return array Updated plan with new/refined actions
     */
    public function refine(array $questions, array $results, array $evaluation): array
    {
        try {
            Log::info('Research plan refinement initiated', [
                'previous_actions' => count($questions),
                'results_count' => count($results),
                'insights_found' => $evaluation['insights_count'] ?? 0,
            ]);

            $startTime = microtime(true);

            if (empty($questions)) {
                Log::warning('No previous questions to refine');

                return [
                    'reasoning' => 'No previous questions available for refinement',
                    'actions' => [],
                ];
            }

            try {
                $refinementContext = $this->buildRefinementContext($questions, $results, $evaluation);

                Log::debug('Refinement context built', [
                    'context_length' => strlen($refinementContext),
                ]);

                $response = $this->chat->chat([
                    ['role' => 'system', 'content' => $this->getRefinementSystemPrompt()],
                    ['role' => 'user', 'content' => $refinementContext],
                ], $this->model, [
                    'response_format' => ['type' => 'json_object'],
                    'temperature' => 0.7,
                    'max_tokens' => 1000,
                ]);

                $content = $response['choices'][0]['message']['content'] ?? null;

                if (! $content) {
                    throw new \Exception('Empty response from LLM refinement');
                }

                $plan = json_decode($content, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \Exception('Invalid JSON from LLM refinement: '.json_last_error_msg());
                }

                if (! isset($plan['reasoning']) || ! isset($plan['actions'])) {
                    throw new \Exception('Invalid refinement plan format from LLM: missing reasoning or actions');
                }

                if (! is_array($plan['actions'])) {
                    throw new \Exception('Invalid refinement plan format: actions is not an array');
                }

                // Validate actions
                foreach ($plan['actions'] as $index => $action) {
                    if (! is_array($action)) {
                        Log::warning('Invalid action format at index in refinement', ['index' => $index]);

                        continue;
                    }

                    if (! isset($action['tool']) || ! isset($action['params'])) {
                        throw new \Exception("Refined action at index {$index} missing tool or params");
                    }
                }

                // Limit actions
                if (count($plan['actions']) > $this->maxActionsPerPlan) {
                    Log::debug('Limiting refined actions to max allowed', [
                        'original_count' => count($plan['actions']),
                        'max_allowed' => $this->maxActionsPerPlan,
                    ]);
                    $plan['actions'] = array_slice($plan['actions'], 0, $this->maxActionsPerPlan);
                }

                // Deduplicate actions
                $originalCount = count($plan['actions']);
                $plan['actions'] = $this->deduplicateActions($plan['actions']);
                if (count($plan['actions']) < $originalCount) {
                    Log::debug('Deduplicated refined actions', [
                        'original_count' => $originalCount,
                        'unique_count' => count($plan['actions']),
                    ]);
                }

                Log::info('Research plan refined via LLM', [
                    'actions_count' => count($plan['actions']),
                    'tokens_used' => $response['usage']['total_tokens'] ?? 0,
                    'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                ]);

                return $plan;

            } catch (\Exception $e) {
                Log::warning('LLM refinement failed', [
                    'error' => $e->getMessage(),
                    'previous_actions' => count($questions),
                ]);

                // Return empty plan to signal no refinement possible
                return [
                    'reasoning' => 'Refinement failed: '.$e->getMessage(),
                    'actions' => [],
                ];
            }

        } catch (\Exception $e) {
            Log::error('Research plan refinement failed completely', [
                'previous_actions' => count($questions),
                'results_count' => count($results),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new AgentException(
                'Research plan refinement failed: '.$e->getMessage(),
                AgentException::EXECUTION_FAILED,
                $e
            );
        }
    }

    /**
     * Generate fallback actions when LLM planning fails
     *
     * @param  AgentRun  $run  The current research run
     * @return array Array of fallback actions
     */
    public function generateFallback(AgentRun $run): array
    {
        try {
            Log::debug('Generating fallback actions', [
                'run_id' => $run->id,
                'current_iteration' => $run->current_iteration,
            ]);

            // For the initial iterations, generate exploratory actions
            $actions = [];

            if ($run->current_iteration === 0) {
                // First iteration: broad vector search
                $actions[] = [
                    'tool' => 'law_vector_search',
                    'params' => [
                        'query' => $run->objective,
                        'limit' => 5,
                    ],
                    'rationale' => 'Initial broad search to understand the topic',
                ];
            } else {
                // Subsequent iterations: more targeted research based on context
                $topics = $run->topics ?? [];
                if (! empty($topics) && is_array($topics)) {
                    $actions[] = [
                        'tool' => 'law_vector_search',
                        'params' => [
                            'query' => implode(' ', array_slice($topics, 0, 3)),
                            'limit' => 3,
                        ],
                        'rationale' => 'Targeted search based on identified topics',
                    ];

                    // Check for related laws in graph
                    $actions[] = [
                        'tool' => 'graph_query',
                        'params' => [
                            'cypher' => 'MATCH (l:LawDocument)-[:CITES]->(cited:LawDocument) WHERE l.title CONTAINS $topic RETURN cited.title, cited.law_number LIMIT 5',
                            'parameters' => ['topic' => $topics[0] ?? 'law'],
                        ],
                        'rationale' => 'Find related laws through citation graph',
                    ];
                } else {
                    // No topics available, fallback to basic search
                    $actions[] = [
                        'tool' => 'law_vector_search',
                        'params' => [
                            'query' => $run->objective,
                            'limit' => 3,
                        ],
                        'rationale' => 'Basic search as no topics are available',
                    ];
                }
            }

            Log::debug('Fallback actions generated', [
                'actions_count' => count($actions),
            ]);

            return $actions;

        } catch (\Exception $e) {
            Log::warning('Fallback generation failed, using minimal actions', [
                'error' => $e->getMessage(),
                'run_id' => $run->id ?? null,
            ]);

            // Return minimal safe action
            return [
                [
                    'tool' => 'law_vector_search',
                    'params' => [
                        'query' => $run->objective ?? 'search',
                        'limit' => 5,
                    ],
                    'rationale' => 'Minimal fallback due to error',
                ],
            ];
        }
    }

    /**
     * Generate basic fallback when no AgentRun is provided
     *
     * @param  string  $query  The research objective
     * @return array Array of basic fallback actions
     */
    protected function generateBasicFallback(string $query): array
    {
        try {
            if (empty(trim($query))) {
                Log::warning('Basic fallback received empty query');
                $query = 'search';
            }

            return [
                [
                    'tool' => 'law_vector_search',
                    'params' => [
                        'query' => $query,
                        'limit' => 5,
                    ],
                    'rationale' => 'Basic fallback search',
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Basic fallback generation failed', [
                'error' => $e->getMessage(),
            ]);

            // Return absolute minimal action
            return [
                [
                    'tool' => 'law_vector_search',
                    'params' => [
                        'query' => 'search',
                        'limit' => 5,
                    ],
                    'rationale' => 'Minimal fallback due to error',
                ],
            ];
        }
    }

    /**
     * Build context for LLM planning
     *
     * @param  string  $query  The research objective
     * @param  array  $context  Additional context
     * @return string Formatted planning context
     */
    protected function buildPlanningContext(string $query, array $context): string
    {
        try {
            $planningContext = "# Research Objective\n";
            $planningContext .= $query."\n\n";

            // Include past insights from previous runs on similar objectives
            $pastInsights = $context['past_insights'] ?? [];
            if (! empty($pastInsights) && is_array($pastInsights)) {
                $planningContext .= "# Insights from Past Research (Memory Reuse)\n";
                $planningContext .= "The following insights were found in previous research on this topic:\n";
                foreach ($pastInsights as $pastInsight) {
                    if (! is_array($pastInsight) && ! is_string($pastInsight)) {
                        continue;
                    }

                    $createdAt = is_array($pastInsight) ? ($pastInsight['created_at'] ?? 'unknown date') : 'unknown date';
                    $content = is_array($pastInsight) ? ($pastInsight['content'] ?? '') : $pastInsight;

                    if (! empty($content)) {
                        $planningContext .= "- [{$createdAt}] {$content}\n";
                    }
                }
                $planningContext .= "\nBuild on these past findings rather than starting from scratch.\n\n";
            }

            // Current progress
            $currentIteration = $context['current_iteration'] ?? 0;
            $maxIterations = $context['max_iterations'] ?? 10;
            $planningContext .= "# Current Progress\n";
            $planningContext .= "Iteration: {$currentIteration}/{$maxIterations}\n";

            $insights = $context['insights'] ?? [];
            $insightsCount = is_array($insights) ? count($insights) : 0;
            $planningContext .= "Insights Found: {$insightsCount}\n\n";

            if (! empty($insights) && is_array($insights)) {
                $planningContext .= "# Current Run Insights\n";
                foreach ($insights as $insight) {
                    if (! is_array($insight) && ! is_string($insight)) {
                        continue;
                    }

                    $insightText = is_array($insight) ? ($insight['content'] ?? '') : $insight;
                    if (! empty($insightText)) {
                        $planningContext .= "- {$insightText}\n";
                    }
                }
                $planningContext .= "\n";
            }

            // Previous iterations
            $previousIterations = $context['previous_iterations'] ?? [];
            if (! empty($previousIterations) && is_array($previousIterations)) {
                $planningContext .= "# Previous Actions & Results\n";
                $lastThree = array_slice($previousIterations, -3);
                foreach ($lastThree as $iter) {
                    if (! is_array($iter)) {
                        continue;
                    }

                    $iterNum = $iter['number'] ?? $iter['iteration'] ?? '?';
                    $actionsTaken = $iter['actions_taken'] ?? count($iter['actions'] ?? []);
                    $insightsFound = $iter['insights_found'] ?? count($iter['evaluation']['insights'] ?? []);
                    $planningContext .= "Iteration {$iterNum}: {$actionsTaken} actions, {$insightsFound} insights\n";
                }
            }

            return $planningContext;

        } catch (\Exception $e) {
            Log::error('Failed to build planning context', [
                'error' => $e->getMessage(),
                'query' => substr($query, 0, 100),
            ]);

            // Return minimal context
            return "# Research Objective\n{$query}\n\n";
        }
    }

    /**
     * Build context for LLM refinement
     *
     * @param  array  $questions  Previous questions/actions
     * @param  array  $results  Results from executing those actions
     * @param  array  $evaluation  Evaluation of the previous iteration
     * @return string Formatted refinement context
     */
    protected function buildRefinementContext(array $questions, array $results, array $evaluation): string
    {
        try {
            $context = "# Previous Actions\n";
            foreach ($questions as $i => $action) {
                if (! is_array($action)) {
                    continue;
                }

                $tool = $action['tool'] ?? 'unknown';
                $params = json_encode($action['params'] ?? []);
                $context .= ($i + 1).". Tool: {$tool}, Params: {$params}\n";
            }
            $context .= "\n";

            $context .= "# Results Summary\n";
            $successCount = 0;
            $failureCount = 0;
            foreach ($results as $result) {
                if (! is_array($result)) {
                    continue;
                }

                if ($result['success'] ?? false) {
                    $successCount++;
                } else {
                    $failureCount++;
                }
            }
            $context .= "Successful actions: {$successCount}\n";
            $context .= "Failed actions: {$failureCount}\n\n";

            $insights = $evaluation['insights'] ?? [];
            if (! empty($insights) && is_array($insights)) {
                $context .= "# Insights Discovered\n";
                foreach ($insights as $insight) {
                    if (! is_array($insight) && ! is_string($insight)) {
                        continue;
                    }

                    $insightText = is_string($insight) ? $insight : ($insight['content'] ?? '');
                    if (! empty($insightText)) {
                        $context .= "- {$insightText}\n";
                    }
                }
                $context .= "\n";
            }

            $context .= "# Next Steps\n";
            $context .= "Based on the above results and insights, what should we research next?\n";
            $context .= "Focus on filling gaps in knowledge and building on successful findings.\n";

            return $context;

        } catch (\Exception $e) {
            Log::error('Failed to build refinement context', [
                'error' => $e->getMessage(),
                'questions_count' => count($questions),
                'results_count' => count($results),
            ]);

            // Return minimal context
            return "# Refinement\nBased on previous research, plan next steps.\n";
        }
    }

    /**
     * Get system prompt for LLM planning
     *
     * @return string System prompt
     */
    protected function getPlanningSystemPrompt(): string
    {
        return <<<'PROMPT'
You are an expert legal research AI assistant specializing in Croatian law.

Your task is to analyze the research objective and previous findings, then plan the next 1-3 research actions.

AVAILABLE TOOLS:

LAW SEARCH TOOLS:
1. law_vector_search - Semantic search across Croatian laws
   Params: query (string), limit (int, default 5)

2. law_keyword_search - Exact text matching in laws
   Params: query (string), law_number (optional), jurisdiction (optional), limit (int)

3. law_hybrid_search - Combined vector + keyword search
   Params: query (string), limit (int)

4. law_lookup - Find specific law by number
   Params: law_number (string), jurisdiction (string, optional)

5. law_get_article - Get specific article from a law document
   Params: doc_id (string), chunk_index (int, optional)

DECISION SEARCH TOOLS:
6. decision_vector_search - Semantic search across court decisions
   Params: query (string), court (optional), limit (int)

7. decision_keyword_search - Exact text matching in decisions
   Params: query (string), case_number (optional), court (optional), date_from (optional), limit (int)

8. decision_hybrid_search - Combined search for decisions
   Params: query (string), limit (int)

9. decision_lookup - Lookup decision by criteria
   Params: court (optional), case_number (optional), date_from (optional), date_to (optional)

10. decision_get - Get decision by ID
    Params: id (string), include_content (bool, default false)

CASE SEARCH TOOLS:
11. case_vector_search - Search case documents
    Params: query (string), limit (int)

12. case_search - Search cases
    Params: query (string), limit (int)

13. case_document_search - Search within case documents
    Params: query (string), limit (int)

OTHER TOOLS:
14. graph_query - Query Neo4j knowledge graph for relationships
    Params: cypher (string), parameters (object)
    Example: Find laws that cite a specific law, or cases that reference a decision

15. web_fetch - Fetch external web content
    Params: url (string)

PLANNING STRATEGY:
- Start broad (vector search) to understand the topic
- Follow up with specific searches based on findings
- Use keyword search when you know exact law numbers or case references
- Use graph queries to find related laws and citations
- If previous iteration found a law, search for court decisions applying that law
- If previous iteration found a decision, search for the laws it cites
- Build on previous findings rather than repeating searches

RESPONSE FORMAT (JSON):
{
  "reasoning": "Brief explanation of why these next steps make sense given the objective and previous findings",
  "actions": [
    {
      "tool": "tool_name",
      "params": {"query": "...", "limit": 5},
      "rationale": "Why this specific action will help achieve the objective"
    }
  ]
}

IMPORTANT:
- Maximum 3 actions per iteration
- Each action must directly relate to the objective
- Don't repeat actions from previous iterations unless there's a specific reason
- If previous iterations found relevant information, build on it rather than starting over
- Choose the most appropriate search type (vector/keyword/hybrid) based on the query
PROMPT;
    }

    /**
     * Get system prompt for LLM refinement
     *
     * @return string System prompt
     */
    protected function getRefinementSystemPrompt(): string
    {
        return <<<'PROMPT'
You are an expert legal research AI assistant specializing in Croatian law.

Your task is to analyze previous research actions and their results, then plan the next 1-3 research actions.

Review the previous actions, results, and insights discovered. Then decide:
1. What worked well and should be built upon
2. What gaps remain in knowledge
3. What new directions should be explored

Use the same AVAILABLE TOOLS as in initial planning (see planning prompt).

RESPONSE FORMAT (JSON):
{
  "reasoning": "Brief explanation of what we learned from previous actions and why these next steps make sense",
  "actions": [
    {
      "tool": "tool_name",
      "params": {"query": "...", "limit": 5},
      "rationale": "Why this specific action will help achieve the objective"
    }
  ]
}

IMPORTANT:
- Maximum 3 actions per iteration
- Build on successful findings
- Don't repeat failed actions
- If sufficient information has been gathered, you may return an empty actions array
- Focus on filling gaps and expanding on promising leads
PROMPT;
    }

    /**
     * Deduplicate actions by tool and params
     *
     * @param  array  $actions  Array of actions
     * @return array Deduplicated actions
     */
    protected function deduplicateActions(array $actions): array
    {
        try {
            $seen = [];
            $unique = [];

            foreach ($actions as $action) {
                if (! is_array($action)) {
                    Log::debug('Skipping non-array action during deduplication');

                    continue;
                }

                $tool = $action['tool'] ?? '';
                $params = $action['params'] ?? [];

                // Create a hash of the action
                try {
                    $hash = md5($tool.json_encode($params));
                } catch (\Exception $e) {
                    Log::warning('Failed to create action hash, including anyway', [
                        'error' => $e->getMessage(),
                        'tool' => $tool,
                    ]);
                    $unique[] = $action;

                    continue;
                }

                if (! isset($seen[$hash])) {
                    $seen[$hash] = true;
                    $unique[] = $action;
                }
            }

            return $unique;

        } catch (\Exception $e) {
            Log::error('Action deduplication failed, returning original actions', [
                'error' => $e->getMessage(),
                'actions_count' => count($actions),
            ]);

            // Return original actions if deduplication fails
            return $actions;
        }
    }
}

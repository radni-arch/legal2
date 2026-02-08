<?php

namespace App\Services\Research;

use App\Models\AgentRun;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Log;

class ResearchPlannerService
{
    protected const VALID_TOOLS = [
        'law_vector_search', 'law_keyword_search', 'law_hybrid_search', 'law_lookup',
        'decision_vector_search', 'decision_keyword_search', 'decision_hybrid_search', 'decision_lookup',
        'case_vector_search', 'case_search',
        'graph_query', 'note_save',
    ];

    protected const MAX_ACTIONS_PER_PLAN = 3;

    public function __construct(
        protected OpenAIService $openai
    ) {}

    public function planNextIteration(AgentRun $run, array $previousResults): array
    {
        $prompt = $this->buildPlanningPrompt($run, $previousResults);

        try {
            $response = $this->openai->chat([
                ['role' => 'system', 'content' => $this->getSystemPrompt()],
                ['role' => 'user', 'content' => $prompt],
            ], 'gpt-4o-mini', [
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.7,
                'max_tokens' => 1000,
            ]);

            $planJson = $response['choices'][0]['message']['content'];
            $plan = json_decode($planJson, true);

            if (!$this->validatePlan($plan)) {
                throw new \Exception('Invalid plan structure from LLM');
            }

            // Track tokens
            $tokensUsed = $response['usage']['total_tokens'] ?? 0;
            $run->tokens_used += $tokensUsed;
            $model = config('ai_pricing.default_model', 'gpt-4o-mini');
            $inputRate = config("ai_pricing.models.{$model}.input_per_1m", 0.15);
            $run->cost_spent += ($tokensUsed / 1_000_000) * $inputRate;
            $run->save();

            Log::info('ResearchPlannerService: Plan generated', [
                'run_id' => $run->id,
                'actions_count' => count($plan['actions']),
                'tokens_used' => $tokensUsed,
            ]);

            return $plan;

        } catch (\Exception $e) {
            Log::error('ResearchPlannerService: Planning failed', [
                'run_id' => $run->id,
                'error' => $e->getMessage(),
            ]);

            return $this->getFallbackPlan($run);
        }
    }

    public function sanitizeInput(string $input): string
    {
        $patterns = [
            '/IGNORE\s+(ALL\s+)?PREVIOUS\s+INSTRUCTIONS/i',
            '/NEW\s+INSTRUCTION[S]?:/i',
            '/SYSTEM\s*:/i',
            '/\[SYSTEM\]/i',
            '/YOU\s+ARE\s+NOW/i',
            '/FORGET\s+(EVERYTHING|ALL)/i',
            '/OVERRIDE\s+PREVIOUS/i',
            '/DISREGARD\s+(ALL\s+)?PREVIOUS/i',
        ];

        $sanitized = $input;
        foreach ($patterns as $pattern) {
            $sanitized = preg_replace($pattern, '[REDACTED]', $sanitized);
        }

        return mb_substr(trim(preg_replace('/\n{3,}/', "\n\n", $sanitized)), 0, 1000);
    }

    protected function buildPlanningPrompt(AgentRun $run, array $previousResults): string
    {
        $sanitizedObjective = $this->sanitizeInput($run->objective);
        $context = $this->formatPreviousResults($previousResults);

        return <<<PROMPT
**OBJECTIVE:** {$sanitizedObjective}

**PREVIOUS FINDINGS:**
{$context}

**CONSTRAINTS:**
- Iteration: {$run->current_iteration} / {$run->max_iterations}
- Take 1-3 actions maximum
- Available tools: law_*, decision_*, case_*, graph_query, note_save

**RESPOND WITH JSON:**
{
    "reasoning": "Analysis of what we know and gaps",
    "next_focus": "Focus area for this iteration",
    "should_stop": false,
    "actions": [{"tool": "...", "params": {...}, "rationale": "..."}]
}
PROMPT;
    }

    protected function getSystemPrompt(): string
    {
        return "You are a legal research planning agent. Generate strategic research plans using available search tools. Focus on Croatian law.";
    }

    protected function validatePlan(array $plan): bool
    {
        if (!isset($plan['reasoning'], $plan['actions'])) {
            return false;
        }

        if (count($plan['actions']) > self::MAX_ACTIONS_PER_PLAN) {
            return false;
        }

        foreach ($plan['actions'] as $action) {
            if (!isset($action['tool'], $action['params'])) {
                return false;
            }
            if (!in_array($action['tool'], self::VALID_TOOLS)) {
                return false;
            }
        }

        return true;
    }

    protected function getFallbackPlan(AgentRun $run): array
    {
        return [
            'reasoning' => 'Fallback plan due to LLM error',
            'next_focus' => 'Basic search',
            'should_stop' => false,
            'actions' => [[
                'tool' => 'law_vector_search',
                'params' => ['query' => $run->objective, 'limit' => 5],
                'rationale' => 'Fallback search',
            ]],
        ];
    }

    protected function formatPreviousResults(array $results): string
    {
        if (empty($results)) {
            return "No previous research conducted yet.";
        }

        $summary = [];
        foreach ($results as $iteration) {
            $summary[] = "Iteration {$iteration['iteration']}: Found " .
                count($iteration['results'] ?? []) . " results";
        }

        return implode("\n", $summary);
    }
}

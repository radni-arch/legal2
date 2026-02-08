<?php

namespace App\Agents\Specialists;

use App\Services\Collaboration\SharedAgentContext;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Log;

/**
 * Strategy Specialist Agent
 *
 * Role: Develops legal strategy and action plans
 * Expertise: Strategic planning, argument development, tactical recommendations
 */
class StrategySpecialistAgent
{
    protected string $name = 'strategy_specialist';

    protected string $role = 'Strategy Specialist';

    public function __construct(
        protected OpenAIService $openai
    ) {}

    /**
     * Execute strategy development task
     */
    public function execute(SharedAgentContext $context, array $task): array
    {
        $context->logEvent('strategy_development_started', ['task' => $task]);

        $problemStatement = $context->getProblemStatement();

        // 1. Get analysis from precedent analyst
        $strongestPrecedents = $context->read('strongest_precedents', []);
        $applicableLaws = $context->read('analyzed_laws', []);
        $distinguishingFactors = $context->read('distinguishing_factors', []);

        if (empty($strongestPrecedents) && empty($applicableLaws)) {
            $context->logEvent('strategy_development_no_data', [
                'message' => 'No precedent analysis available',
            ]);

            return [
                'success' => false,
                'error' => 'No precedent analysis available',
                'message' => 'Precedent analyst must execute first',
            ];
        }

        // 2. Develop legal arguments
        $arguments = $this->developArguments($problemStatement, $strongestPrecedents, $applicableLaws);
        $context->write('legal_arguments', $arguments);

        // 3. Create strategic recommendations
        $recommendations = $this->createRecommendations(
            $problemStatement,
            $arguments,
            $strongestPrecedents
        );
        $context->write('strategic_recommendations', $recommendations);

        // 4. Develop action plan
        $actionPlan = $this->developActionPlan($problemStatement, $recommendations);
        $context->write('action_plan', $actionPlan);

        // 5. Send strategy to risk analyst
        $context->sendMessage('risk_analyst', [
            'type' => 'strategy_complete',
            'arguments' => $arguments,
            'recommendations' => $recommendations,
            'action_plan' => $actionPlan,
            'summary' => $this->generateStrategySummary($arguments, $recommendations),
        ]);

        $context->logEvent('strategy_development_completed', [
            'arguments_count' => count($arguments),
            'recommendations_count' => count($recommendations),
        ]);

        return [
            'success' => true,
            'legal_arguments' => $arguments,
            'strategic_recommendations' => $recommendations,
            'action_plan' => $actionPlan,
            'summary' => $this->generateStrategySummary($arguments, $recommendations),
        ];
    }

    /**
     * Develop legal arguments based on precedents and laws
     */
    protected function developArguments(string $problem, array $precedents, array $laws): array
    {
        $precedentsFormatted = $this->formatPrecedentsForPrompt($precedents);
        $lawsFormatted = $this->formatLawsForPrompt($laws);

        $prompt = <<<PROMPT
You are a Croatian legal strategist developing arguments for a case.

Problem: {$problem}

Strongest Precedents:
{$precedentsFormatted}

Applicable Laws:
{$lawsFormatted}

Develop 3-5 strong legal arguments that could be used in this case. For each argument:
1. State the argument clearly
2. Cite relevant law or precedent
3. Explain why it's strong
4. Rate strength (0-100)

Respond in JSON format:
{
  "arguments": [
    {
      "title": "Brief title of argument",
      "argument": "Full argument text",
      "legal_basis": ["Law/precedent citation"],
      "strength_score": 85,
      "reasoning": "Why this argument is strong",
      "potential_counterarguments": ["Possible opposing arguments"]
    },
    ...
  ]
}
PROMPT;

        try {
            $response = $this->openai->chat([
                ['role' => 'system', 'content' => 'You are a Croatian legal strategist.'],
                ['role' => 'user', 'content' => $prompt],
            ], 'gpt-4o-mini', [
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.4,
            ]);

            $content = $response['choices'][0]['message']['content'] ?? '{}';
            $data = json_decode($content, true);

            return $data['arguments'] ?? [];

        } catch (\Exception $e) {
            Log::error('StrategySpecialistAgent - Argument development failed', [
                'error' => $e->getMessage(),
            ]);

            return [[
                'title' => 'Fallback Argument',
                'argument' => 'Based on applicable law and precedents',
                'legal_basis' => ['General legal principles'],
                'strength_score' => 50,
                'reasoning' => 'Generated due to LLM error',
                'potential_counterarguments' => [],
            ]];
        }
    }

    /**
     * Create strategic recommendations
     */
    protected function createRecommendations(string $problem, array $arguments, array $precedents): array
    {
        $argumentsFormatted = '';
        foreach ($arguments as $i => $arg) {
            $argumentsFormatted .= "\n".($i + 1).". {$arg['title']} (Strength: {$arg['strength_score']})\n";
            $argumentsFormatted .= "   {$arg['argument']}\n";
        }

        $prompt = <<<PROMPT
You are a Croatian legal strategist providing recommendations.

Problem: {$problem}

Developed Arguments:
{$argumentsFormatted}

Provide strategic recommendations including:
1. Primary strategy approach
2. Which arguments to prioritize
3. Settlement considerations (if applicable)
4. Procedural recommendations
5. Evidence gathering needs

Respond in JSON format:
{
  "primary_strategy": "Description of main strategy",
  "argument_priority": ["arg1_title", "arg2_title", ...],
  "settlement_recommendation": {
    "should_consider": true/false,
    "reasoning": "explanation",
    "estimated_strength": "strong|moderate|weak"
  },
  "procedural_steps": ["step1", "step2", ...],
  "evidence_needed": ["evidence1", "evidence2", ...],
  "timeline_estimate": "estimated timeline",
  "success_probability": 0.75
}
PROMPT;

        try {
            $response = $this->openai->chat([
                ['role' => 'system', 'content' => 'You are a Croatian legal strategist.'],
                ['role' => 'user', 'content' => $prompt],
            ], 'gpt-4o-mini', [
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.3,
            ]);

            $content = $response['choices'][0]['message']['content'] ?? '{}';

            return json_decode($content, true);

        } catch (\Exception $e) {
            Log::error('StrategySpecialistAgent - Recommendations failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'primary_strategy' => 'Standard legal approach',
                'argument_priority' => [],
                'procedural_steps' => ['File motion', 'Gather evidence', 'Prepare for hearing'],
                'success_probability' => 0.5,
            ];
        }
    }

    /**
     * Develop action plan
     */
    protected function developActionPlan(string $problem, array $recommendations): array
    {
        $steps = [];

        // Immediate actions
        $steps[] = [
            'phase' => 'Immediate',
            'timeframe' => 'Week 1',
            'actions' => $recommendations['procedural_steps'] ?? ['Initial case assessment'],
            'priority' => 'high',
        ];

        // Short-term actions
        $steps[] = [
            'phase' => 'Short-term',
            'timeframe' => 'Weeks 2-4',
            'actions' => [
                'Complete evidence gathering',
                'Draft legal documents',
                'Prepare arguments',
            ],
            'priority' => 'high',
        ];

        // Medium-term actions
        $steps[] = [
            'phase' => 'Medium-term',
            'timeframe' => 'Months 2-3',
            'actions' => [
                'File motions',
                'Respond to opposing counsel',
                'Prepare for hearings',
            ],
            'priority' => 'medium',
        ];

        return $steps;
    }

    /**
     * Format precedents for LLM prompt
     */
    protected function formatPrecedentsForPrompt(array $precedents): string
    {
        if (empty($precedents)) {
            return "No precedents available\n";
        }

        $formatted = '';
        foreach (array_slice($precedents, 0, 5) as $i => $p) {
            $formatted .= "\n".($i + 1).'. Court: '.($p['court'] ?? 'N/A');
            $formatted .= "\n   Case: ".($p['case_number'] ?? 'N/A');
            $formatted .= "\n   Score: ".($p['applicability_score'] ?? 'N/A');
            $formatted .= "\n   Authority: ".($p['binding_authority'] ?? 'N/A');
            $formatted .= "\n";
        }

        return $formatted;
    }

    /**
     * Format laws for LLM prompt
     */
    protected function formatLawsForPrompt(array $laws): string
    {
        if (empty($laws)) {
            return "No laws available\n";
        }

        $formatted = '';
        foreach (array_slice($laws, 0, 5) as $i => $law) {
            $formatted .= "\n".($i + 1).'. '.($law['title'] ?? 'Untitled');
            $formatted .= ' ('.($law['law_number'] ?? 'N/A').")\n";
        }

        return $formatted;
    }

    /**
     * Generate strategy summary
     */
    protected function generateStrategySummary(array $arguments, array $recommendations): string
    {
        $summary = "Strategy Development Complete:\n\n";

        $summary .= 'Legal Arguments: '.count($arguments)."\n";
        foreach (array_slice($arguments, 0, 3) as $i => $arg) {
            $summary .= ($i + 1).". {$arg['title']} (Strength: {$arg['strength_score']})\n";
        }

        if (isset($recommendations['primary_strategy'])) {
            $summary .= "\nPrimary Strategy: {$recommendations['primary_strategy']}\n";
        }

        if (isset($recommendations['success_probability'])) {
            $prob = round($recommendations['success_probability'] * 100);
            $summary .= "\nSuccess Probability: {$prob}%\n";
        }

        return $summary;
    }

    /**
     * Get agent name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get agent role
     */
    public function getRole(): string
    {
        return $this->role;
    }
}

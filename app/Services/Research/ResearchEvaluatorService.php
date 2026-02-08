<?php

namespace App\Services\Research;

use App\Models\AgentRun;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Log;

/**
 * ResearchEvaluatorService
 *
 * LLM-powered evaluation of research iterations.
 * Assesses quality, identifies gaps, and extracts insights from research results.
 */
class ResearchEvaluatorService
{
    public function __construct(
        protected OpenAIService $openai
    ) {}

    /**
     * Evaluate an iteration's results using LLM
     *
     * @param AgentRun $run The current agent run
     * @param array $iterationResults Results from search tools
     * @return array Evaluation with quality_score, coverage_assessment, gaps_identified, insights, recommendation
     */
    public function evaluateIteration(AgentRun $run, array $iterationResults): array
    {
        $prompt = $this->buildEvaluationPrompt($run, $iterationResults);

        try {
            $response = $this->openai->chat([
                ['role' => 'system', 'content' => $this->getSystemPrompt()],
                ['role' => 'user', 'content' => $prompt],
            ], 'gpt-4o-mini', [
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.3, // Lower temperature for objective evaluation
                'max_tokens' => 1500,
            ]);

            $evaluationJson = $response['choices'][0]['message']['content'];
            $evaluation = json_decode($evaluationJson, true);

            if (!$this->validateEvaluation($evaluation)) {
                throw new \Exception('Invalid evaluation structure from LLM');
            }

            // Track tokens
            $tokensUsed = $response['usage']['total_tokens'] ?? 0;
            $evaluation['tokens_used'] = $tokensUsed;

            $run->tokens_used += $tokensUsed;
            $model = config('ai_pricing.default_model', 'gpt-4o-mini');
            $inputRate = config("ai_pricing.models.{$model}.input_per_1m", 0.15);
            $run->cost_spent += ($tokensUsed / 1_000_000) * $inputRate;
            $run->save();

            Log::info('ResearchEvaluatorService: Evaluation completed', [
                'run_id' => $run->id,
                'quality_score' => $evaluation['quality_score'],
                'insights_count' => count($evaluation['insights'] ?? []),
                'tokens_used' => $tokensUsed,
            ]);

            return $evaluation;

        } catch (\Exception $e) {
            Log::error('ResearchEvaluatorService: Evaluation failed', [
                'run_id' => $run->id,
                'error' => $e->getMessage(),
            ]);

            return $this->getFallbackEvaluation($iterationResults);
        }
    }

    /**
     * Build the evaluation prompt
     */
    protected function buildEvaluationPrompt(AgentRun $run, array $iterationResults): string
    {
        $objective = $run->objective;
        $iteration = $run->current_iteration;
        $maxIterations = $run->max_iterations;

        $resultsFormatted = $this->formatResults($iterationResults);

        return <<<PROMPT
**RESEARCH OBJECTIVE:**
{$objective}

**ITERATION:** {$iteration} / {$maxIterations}

**SEARCH RESULTS THIS ITERATION:**
{$resultsFormatted}

**YOUR TASK:**
Evaluate the quality and relevance of these search results for the research objective.

**RESPOND WITH JSON:**
{
    "quality_score": 75,
    "coverage_assessment": "Describe what aspects of the objective are covered",
    "gaps_identified": ["Gap 1", "Gap 2"],
    "insights": [
        {
            "title": "Key Finding Title",
            "content": "Brief description of the finding",
            "citations": ["Law/case references"]
        }
    ],
    "recommendation": "continue|refine|stop"
}

**SCORING GUIDE:**
- 0-40: Poor relevance, missing critical information
- 41-70: Moderate relevance, some useful findings
- 71-85: Good relevance, most information found
- 86-100: Excellent coverage, comprehensive findings

**IMPORTANT:**
- Only extract insights that directly address the objective
- Always include legal citations (law numbers, article numbers, case numbers)
- Identify specific gaps that remain
- Be objective in quality assessment
PROMPT;
    }

    /**
     * Get system prompt for evaluation
     */
    protected function getSystemPrompt(): string
    {
        return <<<'PROMPT'
You are a legal research quality evaluator. Assess search results objectively based on:
1. Relevance to the research objective
2. Completeness of coverage
3. Quality of legal sources
4. Actionability of findings

Always provide specific, constructive feedback with proper legal citations.
PROMPT;
    }

    /**
     * Format results for LLM evaluation
     */
    protected function formatResults(array $results): string
    {
        if (empty($results)) {
            return "No results found in this iteration.";
        }

        $formatted = '';
        foreach ($results as $idx => $result) {
            $num = $idx + 1;
            $tool = $result['tool'] ?? 'unknown';
            $success = $result['success'] ?? false;

            $formatted .= "**Result {$num}: {$tool}**\n";
            $formatted .= "Success: " . ($success ? 'Yes' : 'No') . "\n";

            if ($success && isset($result['result'])) {
                $formatted .= $this->formatSingleResult($result['result']);
            } elseif (isset($result['error'])) {
                $formatted .= "Error: {$result['error']}\n";
            }

            $formatted .= "\n";
        }

        return $formatted;
    }

    /**
     * Format a single result
     */
    protected function formatSingleResult(array $result): string
    {
        $formatted = '';

        // Handle different result structures
        if (isset($result['data']) && is_array($result['data'])) {
            $count = count($result['data']);
            $formatted .= "Found {$count} items\n";

            // Show first few items
            foreach (array_slice($result['data'], 0, 3) as $i => $item) {
                $formatted .= "  - " . ($item['title'] ?? $item['content'] ?? 'Item ' . ($i + 1)) . "\n";
            }
        } elseif (isset($result['hits']) && is_array($result['hits'])) {
            $count = count($result['hits']);
            $formatted .= "Found {$count} hits\n";

            foreach (array_slice($result['hits'], 0, 3) as $i => $hit) {
                $formatted .= "  - " . ($hit['title'] ?? $hit['content'] ?? 'Hit ' . ($i + 1)) . "\n";
            }
        } elseif (isset($result['count'])) {
            $formatted .= "Count: {$result['count']}\n";
        } else {
            $formatted .= "Result: " . json_encode($result) . "\n";
        }

        return $formatted;
    }

    /**
     * Validate evaluation structure
     */
    protected function validateEvaluation(array $evaluation): bool
    {
        if (!isset($evaluation['quality_score'])) {
            return false;
        }

        if (!isset($evaluation['coverage_assessment'])) {
            return false;
        }

        if (!isset($evaluation['gaps_identified']) || !is_array($evaluation['gaps_identified'])) {
            return false;
        }

        if (!isset($evaluation['insights']) || !is_array($evaluation['insights'])) {
            return false;
        }

        if (!isset($evaluation['recommendation'])) {
            return false;
        }

        // Validate quality score range
        $score = $evaluation['quality_score'];
        if (!is_numeric($score) || $score < 0 || $score > 100) {
            return false;
        }

        return true;
    }

    /**
     * Get fallback evaluation when LLM fails
     */
    protected function getFallbackEvaluation(array $results): array
    {
        $successCount = 0;
        foreach ($results as $result) {
            if ($result['success'] ?? false) {
                $successCount++;
            }
        }

        $totalResults = count($results);
        $qualityScore = $totalResults > 0 ? (int) (($successCount / $totalResults) * 60) : 0;

        return [
            'quality_score' => $qualityScore,
            'coverage_assessment' => 'Fallback evaluation: ' . $successCount . ' of ' . $totalResults . ' searches succeeded.',
            'gaps_identified' => ['Unable to perform detailed analysis due to evaluation error'],
            'insights' => [],
            'recommendation' => 'continue',
            'tokens_used' => 0,
        ];
    }
}

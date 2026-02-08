<?php

namespace App\Services\Research;

use App\Contracts\AI\ChatServiceInterface;
use App\Contracts\Research\AnswerEvaluatorInterface;
use App\Contracts\Research\QualityAssessorInterface;
use App\Exceptions\AgentException;
use App\Exceptions\AnalysisException;
use App\Models\AgentRun;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Quality Assessor Service
 *
 * Assesses overall research quality, evaluates iterations, and synthesizes final output.
 * Extracted from AutonomousResearchAgent to handle quality assessment responsibilities.
 *
 * Responsibilities:
 * - Assess overall research quality
 * - Determine if research is complete
 * - Evaluate individual iterations
 * - Synthesize final research reports
 * - Extract insights from iteration results
 *
 * @see \App\Contracts\Research\QualityAssessorInterface
 */
class QualityAssessorService implements QualityAssessorInterface
{
    /**
     * Quality threshold for considering research complete (0-1 scale)
     */
    protected float $qualityThreshold = 0.85;

    /**
     * Completeness threshold (0-1 scale)
     */
    protected float $completenessThreshold = 0.80;

    /**
     * Create a new QualityAssessorService instance
     *
     * @param  ChatServiceInterface  $chat  Chat service for LLM interactions
     * @param  AnswerEvaluatorInterface  $evaluator  Answer evaluator for quality checks
     */
    public function __construct(
        protected ChatServiceInterface $chat,
        protected AnswerEvaluatorInterface $evaluator
    ) {}

    /**
     * Assess overall research quality
     *
     * Evaluates the complete research based on query, answer, and supporting data.
     *
     * @param  string  $query  The research objective
     * @param  string  $answer  The synthesized answer/findings
     * @param  array  $evaluation  Evaluation data (insights, iterations, sources_count)
     * @return array Assessment with quality scores, strengths, weaknesses, recommendations
     */
    public function assess(string $query, string $answer, array $evaluation): array
    {
        $correlationId = request()->header('X-Request-ID') ?? Str::uuid()->toString();
        $startTime = microtime(true);

        Log::withContext(['correlation_id' => $correlationId]);

        Log::info('QualityAssessor: assess initiated', [
            'query_length' => strlen($query),
            'answer_length' => strlen($answer),
            'evaluation_keys' => array_keys($evaluation),
            'user_id' => auth()->id(),
        ]);

        try {
            if (empty($answer)) {
                Log::warning('QualityAssessor: Empty answer provided');

                return [
                    'quality_score' => 0.0,
                    'completeness_score' => 0.0,
                    'citation_quality' => 0.0,
                    'relevance_score' => 0.0,
                    'strengths' => [],
                    'weaknesses' => ['No answer provided'],
                    'recommendations' => ['Generate research findings'],
                ];
            }

            $prompt = $this->buildAssessmentPrompt($query, $answer, $evaluation);

            $response = $this->chat->chat([
                ['role' => 'system', 'content' => $this->getAssessmentSystemPrompt()],
                ['role' => 'user', 'content' => $prompt],
            ], 'gpt-4o', [
                'temperature' => 0.2,
                'max_tokens' => 800,
            ]);

            $content = $response['choices'][0]['message']['content'] ?? '';
            $assessment = $this->parseAssessment($content);

            $duration = (microtime(true) - $startTime) * 1000;

            Log::info('QualityAssessor: assess completed', [
                'quality_score' => $assessment['quality_score'],
                'completeness_score' => $assessment['completeness_score'],
                'tokens_used' => $response['usage']['total_tokens'] ?? 0,
                'duration_ms' => round($duration, 2),
            ]);

            return $assessment;

        } catch (AnalysisException $e) {
            Log::error('QualityAssessor: assess failed with AnalysisException', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->fallbackAssessment($query, $answer, $evaluation);

        } catch (\Exception $e) {
            Log::error('QualityAssessor: assess failed with unexpected exception', [
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
                'query' => substr($query, 0, 100),
            ]);

            return $this->fallbackAssessment($query, $answer, $evaluation);
        }
    }

    /**
     * Check if answer is complete
     *
     * @param  array  $assessment  Assessment result from assess()
     * @return bool True if research is complete enough to stop
     */
    public function isComplete(array $assessment): bool
    {
        $qualityScore = $assessment['quality_score'] ?? 0.0;
        $completenessScore = $assessment['completeness_score'] ?? 0.0;

        // Research is complete if both quality and completeness meet thresholds
        return $qualityScore >= $this->qualityThreshold
            && $completenessScore >= $this->completenessThreshold;
    }

    /**
     * Evaluate a single iteration
     *
     * Extracts insights from iteration results and determines if research should stop.
     * This method is extracted from AutonomousResearchAgent::evaluateIteration()
     *
     * @param  AgentRun  $run  The current research run
     * @param  array  $iteration  Iteration data (actions, plan)
     * @return array Evaluation with insights, insights_count, should_stop
     */
    public function evaluateIteration(AgentRun $run, array $iteration): array
    {
        $correlationId = request()->header('X-Request-ID') ?? Str::uuid()->toString();
        $startTime = microtime(true);

        Log::withContext(['correlation_id' => $correlationId]);

        Log::info('QualityAssessor: evaluateIteration initiated', [
            'run_id' => $run->id,
            'iteration' => $run->current_iteration,
            'actions_count' => count($iteration['actions'] ?? []),
            'user_id' => auth()->id(),
        ]);

        try {
            $actionResults = $iteration['actions'] ?? [];
            $insights = [];
            $shouldStop = false;

            // Extract insights from action results
            foreach ($actionResults as $actionResult) {
                if ($actionResult['success'] ?? false) {
                    $insight = $this->evaluator->extractInsight(
                        $actionResult['result'] ?? [],
                        $run->objective
                    );

                    if ($insight) {
                        $insights[] = $insight;
                    }
                }
            }

            // Determine if we should stop
            // Stop conditions:
            // 1. Found sufficient insights (3+) after minimum iterations (3+)
            // 2. Reached maximum iterations
            // 3. No new insights for 2 consecutive iterations
            if (count($insights) >= 3 && $run->current_iteration >= 3) {
                $shouldStop = true;
            }

            if ($run->current_iteration >= $run->max_iterations) {
                $shouldStop = true;
            }

            $result = [
                'insights' => $insights,
                'insights_count' => count($insights),
                'should_stop' => $shouldStop,
            ];

            $duration = (microtime(true) - $startTime) * 1000;

            Log::info('QualityAssessor: evaluateIteration completed', [
                'run_id' => $run->id,
                'iteration' => $run->current_iteration,
                'insights_found' => count($insights),
                'should_stop' => $shouldStop,
                'duration_ms' => round($duration, 2),
            ]);

            return $result;

        } catch (AgentException $e) {
            Log::error('QualityAssessor: evaluateIteration failed with AgentException', [
                'run_id' => $run->id,
                'iteration' => $run->current_iteration,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        } catch (\Exception $e) {
            Log::error('QualityAssessor: evaluateIteration failed with unexpected exception', [
                'run_id' => $run->id,
                'iteration' => $run->current_iteration,
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new AgentException(
                'Iteration evaluation failed: '.$e->getMessage(),
                AgentException::EVALUATION_FAILED,
                $e
            );
        }
    }

    /**
     * Synthesize final research output
     *
     * Creates a comprehensive research report from the completed run.
     * This method is extracted from AutonomousResearchAgent::synthesizeFinalOutput()
     *
     * @param  AgentRun  $run  The completed research run
     * @return string Final research report in markdown format
     */
    public function synthesizeFinalOutput(AgentRun $run): string
    {
        $correlationId = request()->header('X-Request-ID') ?? Str::uuid()->toString();
        $startTime = microtime(true);

        Log::withContext(['correlation_id' => $correlationId]);

        Log::info('QualityAssessor: synthesizeFinalOutput initiated', [
            'run_id' => $run->id,
            'iterations' => $run->current_iteration,
            'objective_length' => strlen($run->objective),
            'user_id' => auth()->id(),
        ]);

        try {
            $output = "# Research Report: {$run->objective}\n\n";
            $output .= "## Summary\n\n";
            $output .= "Completed {$run->current_iteration} research iterations.\n\n";

            $output .= "## Key Findings\n\n";

            $allInsights = [];
            foreach ($run->iterations as $iteration) {
                if (isset($iteration['evaluation']['insights'])) {
                    $allInsights = array_merge($allInsights, $iteration['evaluation']['insights']);
                }
            }

            if (empty($allInsights)) {
                $output .= "No significant findings were discovered during this research.\n";
            } else {
                foreach (array_unique($allInsights) as $i => $insight) {
                    $output .= ($i + 1).". {$insight}\n";
                }
            }

            $output .= "\n## Research Process\n\n";
            $output .= "- Total iterations: {$run->current_iteration}\n";
            $output .= "- Elapsed time: {$run->elapsed_seconds} seconds\n";
            if ($run->tokens_used) {
                $output .= "- Tokens used: {$run->tokens_used}\n";
            }
            if ($run->cost_spent) {
                $output .= sprintf("- Cost: $%.2f\n", $run->cost_spent);
            }

            $duration = (microtime(true) - $startTime) * 1000;

            Log::info('QualityAssessor: synthesizeFinalOutput completed', [
                'run_id' => $run->id,
                'insights_count' => count($allInsights),
                'output_length' => strlen($output),
                'duration_ms' => round($duration, 2),
            ]);

            return $output;

        } catch (\Exception $e) {
            Log::error('QualityAssessor: synthesizeFinalOutput failed with unexpected exception', [
                'run_id' => $run->id,
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new AgentException(
                'Failed to synthesize research output: '.$e->getMessage(),
                AgentException::SYNTHESIS_FAILED,
                $e
            );
        }
    }

    /**
     * Get system prompt for quality assessment
     */
    protected function getAssessmentSystemPrompt(): string
    {
        return <<<'PROMPT'
You are an expert legal research quality evaluator specializing in Croatian law.

Your task is to assess the overall quality of legal research based on:
1. QUALITY: Overall quality of research and answer
2. COMPLETENESS: Whether the answer fully addresses the research objective
3. CITATION QUALITY: Proper legal citations (law numbers, article numbers, case numbers)
4. RELEVANCE: How well findings address the research objective

Respond in this exact JSON format:
{
  "quality_score": 0.85,
  "completeness_score": 0.80,
  "citation_quality": 0.90,
  "relevance_score": 0.88,
  "strengths": ["List of what the research does well"],
  "weaknesses": ["List of areas that need improvement"],
  "recommendations": ["Specific suggestions for improvement"]
}

Scoring scale (0.0 - 1.0):
- 0.90-1.00: Excellent (comprehensive, well-cited, highly relevant)
- 0.75-0.89: Good (solid research with minor gaps)
- 0.60-0.74: Fair (adequate but significant improvements needed)
- 0.40-0.59: Poor (major gaps or lack of citations)
- 0.00-0.39: Very poor (incomplete or not useful)
PROMPT;
    }

    /**
     * Build assessment prompt
     */
    protected function buildAssessmentPrompt(string $query, string $answer, array $evaluation): string
    {
        $insights = $evaluation['insights'] ?? [];
        $iterations = $evaluation['iterations'] ?? 0;
        $sourcesCount = $evaluation['sources_count'] ?? 0;
        $insightsCount = count($insights);

        $insightsStr = empty($insights)
            ? 'None'
            : implode("\n", array_map(fn ($i, $insight) => ($i + 1).". $insight", array_keys($insights), $insights));

        return <<<PROMPT
Research Objective: {$query}

Research Answer/Findings:
{$answer}

Research Metadata:
- Iterations completed: {$iterations}
- Sources consulted: {$sourcesCount}
- Insights extracted: {$insightsCount}

Key Insights:
{$insightsStr}

Assess the overall quality of this legal research and provide your evaluation in JSON format.
PROMPT;
    }

    /**
     * Parse assessment response from LLM
     */
    protected function parseAssessment(string $response): array
    {
        // Try to extract JSON from response
        if (preg_match('/\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/s', $response, $matches)) {
            $json = json_decode($matches[0], true);
            if (is_array($json)) {
                return [
                    'quality_score' => (float) ($json['quality_score'] ?? 0.5),
                    'completeness_score' => (float) ($json['completeness_score'] ?? 0.5),
                    'citation_quality' => (float) ($json['citation_quality'] ?? 0.5),
                    'relevance_score' => (float) ($json['relevance_score'] ?? 0.5),
                    'strengths' => $json['strengths'] ?? [],
                    'weaknesses' => $json['weaknesses'] ?? [],
                    'recommendations' => $json['recommendations'] ?? [],
                ];
            }
        }

        // Fallback: return neutral scores
        return [
            'quality_score' => 0.5,
            'completeness_score' => 0.5,
            'citation_quality' => 0.5,
            'relevance_score' => 0.5,
            'strengths' => [],
            'weaknesses' => ['Failed to parse assessment response'],
            'recommendations' => ['Retry assessment'],
        ];
    }

    /**
     * Fallback assessment using simple heuristics
     */
    protected function fallbackAssessment(string $query, string $answer, array $evaluation): array
    {
        $insights = $evaluation['insights'] ?? [];
        $iterations = $evaluation['iterations'] ?? 0;
        $sourcesCount = $evaluation['sources_count'] ?? 0;

        // Calculate scores based on heuristics
        $qualityScore = 0.5; // Base score

        // Bonus for insights
        if (count($insights) >= 5) {
            $qualityScore += 0.2;
        } elseif (count($insights) >= 3) {
            $qualityScore += 0.1;
        }

        // Bonus for sources
        if ($sourcesCount >= 10) {
            $qualityScore += 0.15;
        } elseif ($sourcesCount >= 5) {
            $qualityScore += 0.1;
        }

        // Check for citations in answer
        $hasCitations = preg_match('/(?:NN|Narodne novine|Članak|ZKP|Gž-)/iu', $answer);
        $citationQuality = $hasCitations ? 0.75 : 0.30;

        if ($hasCitations) {
            $qualityScore += 0.15;
        }

        // Cap at 1.0
        $qualityScore = min(1.0, $qualityScore);

        // Completeness based on answer length and insights
        $completenessScore = min(1.0, (strlen($answer) / 500) * 0.4 + (count($insights) / 5) * 0.6);

        // Relevance based on keyword overlap (simplified)
        $queryWords = str_word_count(mb_strtolower($query), 1);
        $answerWords = str_word_count(mb_strtolower($answer), 1);
        $overlap = count(array_intersect($queryWords, $answerWords));
        $relevanceScore = min(1.0, $overlap / max(1, count($queryWords)));

        $strengths = [];
        $weaknesses = [];
        $recommendations = [];

        if (count($insights) >= 3) {
            $strengths[] = 'Multiple insights discovered';
        } else {
            $weaknesses[] = 'Few insights extracted';
            $recommendations[] = 'Increase search depth and breadth';
        }

        if ($hasCitations) {
            $strengths[] = 'Contains legal citations';
        } else {
            $weaknesses[] = 'Missing legal citations';
            $recommendations[] = 'Include proper legal citations (law numbers, articles, case numbers)';
        }

        if (strlen($answer) < 200) {
            $weaknesses[] = 'Answer is brief';
            $recommendations[] = 'Expand findings with more detail';
        }

        return [
            'quality_score' => $qualityScore,
            'completeness_score' => $completenessScore,
            'citation_quality' => $citationQuality,
            'relevance_score' => $relevanceScore,
            'strengths' => $strengths,
            'weaknesses' => $weaknesses,
            'recommendations' => $recommendations,
        ];
    }

    /**
     * Set quality threshold
     *
     * @param  float  $threshold  Quality threshold (0-1)
     */
    public function setQualityThreshold(float $threshold): void
    {
        $this->qualityThreshold = max(0.0, min(1.0, $threshold));
    }

    /**
     * Set completeness threshold
     *
     * @param  float  $threshold  Completeness threshold (0-1)
     */
    public function setCompletenessThreshold(float $threshold): void
    {
        $this->completenessThreshold = max(0.0, min(1.0, $threshold));
    }
}

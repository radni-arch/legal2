<?php

namespace App\Services\Research;

use App\Contracts\AI\ChatServiceInterface;
use App\Contracts\Research\AnswerEvaluatorInterface;
use App\Exceptions\AgentException;
use Illuminate\Support\Facades\Log;

/**
 * Answer Evaluator Service
 *
 * Extracted from AutonomousResearchAgent to handle answer evaluation and insight extraction.
 * This service evaluates the quality of research answers, scores source relevance,
 * and extracts actionable legal insights.
 *
 * Responsibilities:
 * - Evaluate answer quality (0-1 score)
 * - Score source relevance
 * - Extract insights from search results
 * - Provide fallback extraction when LLM fails
 * - Format results for LLM processing
 *
 * @see \App\Contracts\Research\AnswerEvaluatorInterface
 */
class AnswerEvaluatorService implements AnswerEvaluatorInterface
{
    /**
     * Create a new AnswerEvaluatorService instance
     *
     * @param  ChatServiceInterface  $chat  Chat service for LLM interactions
     */
    public function __construct(
        protected ChatServiceInterface $chat
    ) {}

    /**
     * Evaluate answer quality
     *
     * Evaluates the quality of a research answer by checking:
     * - Presence of proper legal citations
     * - Relevance to the query
     * - Actionability of the insight
     * - Completeness of the answer
     *
     * @param  string  $query  The research objective/query
     * @param  string  $answer  The answer or insight to evaluate
     * @param  array  $sources  Sources supporting the answer
     * @return array Evaluation result with quality_score, has_citations, is_relevant, is_actionable, feedback
     */
    public function evaluate(string $query, string $answer, array $sources): array
    {
        try {
            Log::info('Answer evaluation initiated', [
                'query_length' => strlen($query),
                'answer_length' => strlen($answer),
                'source_count' => count($sources),
            ]);

            $startTime = microtime(true);

            if (empty($answer)) {
                Log::warning('Answer evaluation received empty answer');

                return [
                    'quality_score' => 0.0,
                    'has_citations' => false,
                    'is_relevant' => false,
                    'is_actionable' => false,
                    'feedback' => 'Answer is empty',
                ];
            }

            if (empty(trim($query))) {
                Log::warning('Answer evaluation received empty query');

                return [
                    'quality_score' => 0.0,
                    'has_citations' => false,
                    'is_relevant' => false,
                    'is_actionable' => false,
                    'feedback' => 'Query is empty',
                ];
            }

            try {
                $prompt = $this->buildEvaluationPrompt($query, $answer, $sources);

                $response = $this->chat->chat([
                    ['role' => 'system', 'content' => $this->getEvaluationSystemPrompt()],
                    ['role' => 'user', 'content' => $prompt],
                ], 'gpt-4o-mini', [
                    'temperature' => 0.2, // Lower temperature for consistent evaluation
                    'max_tokens' => 500,
                ]);

                $content = $response['choices'][0]['message']['content'] ?? '';

                if (empty($content)) {
                    throw new \Exception('LLM returned empty response');
                }

                $evaluation = $this->parseEvaluation($content);

                Log::info('Answer evaluated via LLM', [
                    'quality_score' => $evaluation['quality_score'],
                    'has_citations' => $evaluation['has_citations'],
                    'is_relevant' => $evaluation['is_relevant'],
                    'tokens_used' => $response['usage']['total_tokens'] ?? 0,
                    'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                ]);

                return $evaluation;

            } catch (\Exception $e) {
                Log::warning('Answer evaluation LLM failed, using fallback', [
                    'error' => $e->getMessage(),
                    'query' => substr($query, 0, 100),
                    'answer' => substr($answer, 0, 100),
                ]);

                // Fallback to simple heuristic evaluation
                $fallbackResult = $this->fallbackEvaluation($query, $answer, $sources);

                Log::info('Answer evaluation completed via fallback', [
                    'quality_score' => $fallbackResult['quality_score'],
                    'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                ]);

                return $fallbackResult;
            }

        } catch (\Exception $e) {
            Log::error('Answer evaluation failed completely', [
                'query' => substr($query, 0, 100),
                'answer' => substr($answer, 0, 100),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new AgentException(
                'Answer evaluation failed: '.$e->getMessage(),
                AgentException::EVALUATION_FAILED,
                $e
            );
        }
    }

    /**
     * Score relevance of sources
     *
     * Scores each source for relevance to the query and sorts by relevance.
     *
     * @param  string  $query  The research objective/query
     * @param  array  $sources  Array of sources to score
     * @return array Sources with relevance_score added, sorted by relevance
     */
    public function scoreRelevance(string $query, array $sources): array
    {
        try {
            Log::info('Source relevance scoring initiated', [
                'query_length' => strlen($query),
                'source_count' => count($sources),
            ]);

            $startTime = microtime(true);

            if (empty($sources)) {
                Log::debug('No sources to score');

                return [];
            }

            if (empty(trim($query))) {
                Log::warning('Source relevance scoring received empty query');

                return $sources;
            }

            $scoredSources = [];
            $failedCount = 0;

            foreach ($sources as $index => $source) {
                try {
                    if (! is_array($source)) {
                        Log::warning('Invalid source format at index', ['index' => $index]);

                        continue;
                    }

                    $score = $this->calculateRelevanceScore($query, $source);
                    $source['relevance_score'] = $score;
                    $scoredSources[] = $source;

                } catch (\Exception $e) {
                    $failedCount++;
                    Log::warning('Failed to score source relevance', [
                        'error' => $e->getMessage(),
                        'source_type' => $source['type'] ?? 'unknown',
                        'index' => $index,
                    ]);

                    // Assign default score on error
                    $source['relevance_score'] = 0.5;
                    $scoredSources[] = $source;
                }
            }

            // Sort by relevance score (highest first)
            usort($scoredSources, function ($a, $b) {
                return ($b['relevance_score'] ?? 0) <=> ($a['relevance_score'] ?? 0);
            });

            $avgScore = count($scoredSources) > 0
                ? array_sum(array_column($scoredSources, 'relevance_score')) / count($scoredSources)
                : 0;

            Log::info('Source relevance scoring completed', [
                'source_count' => count($scoredSources),
                'avg_score' => round($avgScore, 3),
                'failed_count' => $failedCount,
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return $scoredSources;

        } catch (\Exception $e) {
            Log::error('Source relevance scoring failed', [
                'query' => substr($query, 0, 100),
                'source_count' => count($sources),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new AgentException(
                'Failed to score source relevance: '.$e->getMessage(),
                AgentException::SEARCH_FAILED,
                $e
            );
        }
    }

    /**
     * Extract insight from search result
     *
     * Uses LLM to extract a concise legal insight from search results.
     * Falls back to simple extraction if LLM fails.
     *
     * @param  array  $result  Search result to extract from
     * @param  string  $objective  Research objective
     * @return string|null Extracted insight or null if not relevant
     */
    public function extractInsight(array $result, string $objective): ?string
    {
        try {
            Log::info('Insight extraction initiated', [
                'objective_length' => strlen($objective),
                'result_keys' => array_keys($result),
            ]);

            $startTime = microtime(true);

            if (empty($result)) {
                Log::debug('No results to extract insight from');

                return null;
            }

            if (empty(trim($objective))) {
                Log::warning('Insight extraction received empty objective');

                return null;
            }

            try {
                $formattedResults = $this->formatResultsForExtraction($result);

                if (empty($formattedResults) || $formattedResults === 'No results to format') {
                    Log::debug('No formattable results available');

                    return null;
                }

                if (strlen($formattedResults) > 10000) {
                    // Truncate if too long
                    $formattedResults = substr($formattedResults, 0, 10000)."\n\n[Results truncated...]";
                    Log::debug('Results truncated for LLM processing', [
                        'original_length' => strlen($formattedResults),
                    ]);
                }

                $prompt = <<<PROMPT
Research Objective: {$objective}

Search Results:
{$formattedResults}

Extract a concise legal insight (1-2 sentences) that directly addresses the research objective.
Include proper citations and state the legal principle clearly.

If the results are not relevant to the objective, respond with exactly: null
PROMPT;

                $response = $this->chat->chat([
                    ['role' => 'system', 'content' => $this->getInsightExtractionPrompt()],
                    ['role' => 'user', 'content' => $prompt],
                ], 'gpt-4o-mini', [
                    'temperature' => 0.3, // Lower temperature for factual extraction
                    'max_tokens' => 200,
                ]);

                $insight = trim($response['choices'][0]['message']['content'] ?? '');

                if (empty($insight) || strtolower($insight) === 'null') {
                    Log::debug('LLM returned no relevant insight');

                    return null;
                }

                Log::info('Insight extracted via LLM', [
                    'insight_length' => strlen($insight),
                    'tokens_used' => $response['usage']['total_tokens'] ?? 0,
                    'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                ]);

                return $insight;

            } catch (\Exception $e) {
                Log::warning('Insight extraction LLM failed, using fallback', [
                    'error' => $e->getMessage(),
                    'result_keys' => array_keys($result),
                ]);

                // Fallback to simple extraction
                $fallbackInsight = $this->extractSimpleInsight($result);

                Log::info('Insight extraction completed via fallback', [
                    'insight_length' => $fallbackInsight ? strlen($fallbackInsight) : 0,
                    'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                ]);

                return $fallbackInsight;
            }

        } catch (\Exception $e) {
            Log::error('Insight extraction failed completely', [
                'objective' => substr($objective, 0, 100),
                'result_keys' => array_keys($result),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new AgentException(
                'Insight extraction failed: '.$e->getMessage(),
                AgentException::EVALUATION_FAILED,
                $e
            );
        }
    }

    /**
     * Extract insight using simple fallback logic (no LLM)
     *
     * @param  array  $result  Search result
     * @return string|null Extracted insight or null
     */
    public function extractSimpleInsight(array $result): ?string
    {
        try {
            if (isset($result['laws']) && is_array($result['laws']) && count($result['laws']) > 0) {
                $law = $result['laws'][0];
                $title = $law['title'] ?? 'Unknown';
                $lawNumber = $law['law_number'] ?? 'N/A';

                return "Found relevant law: {$title} ({$lawNumber})";
            }

            if (isset($result['decisions']) && is_array($result['decisions']) && count($result['decisions']) > 0) {
                $decision = $result['decisions'][0];
                $title = $decision['title'] ?? 'Unknown';
                $court = $decision['court'] ?? 'Unknown Court';

                return "Found relevant decision: {$title} from {$court}";
            }

            if (isset($result['rows']) && is_array($result['rows']) && count($result['rows']) > 0) {
                return 'Found '.count($result['rows']).' related entities in graph';
            }

            if (isset($result['cases']) && is_array($result['cases']) && count($result['cases']) > 0) {
                $case = $result['cases'][0];
                $title = $case['title'] ?? 'Unknown';
                $caseNumber = $case['case_number'] ?? 'N/A';

                return "Found relevant case: {$title} ({$caseNumber})";
            }

            return null;

        } catch (\Exception $e) {
            Log::warning('Simple insight extraction failed', [
                'error' => $e->getMessage(),
                'result_keys' => array_keys($result),
            ]);

            return null;
        }
    }

    /**
     * Format search results for LLM insight extraction
     *
     * @param  array  $result  Search result to format
     * @return string Formatted string suitable for LLM prompt
     */
    public function formatResultsForExtraction(array $result): string
    {
        try {
            $formatted = '';

            // Format laws
            if (isset($result['laws']) && is_array($result['laws'])) {
                $formatted .= "## Laws Found:\n";
                foreach (array_slice($result['laws'], 0, 3) as $i => $law) {
                    if (! is_array($law)) {
                        continue;
                    }

                    $formatted .= sprintf(
                        "%d. %s (Law Number: %s)\n   Content: %s\n\n",
                        $i + 1,
                        $law['title'] ?? 'Untitled',
                        $law['law_number'] ?? 'N/A',
                        substr($law['content'] ?? '', 0, 300).'...'
                    );
                }
            }

            // Format decisions
            if (isset($result['decisions']) && is_array($result['decisions'])) {
                $formatted .= "## Court Decisions Found:\n";
                foreach (array_slice($result['decisions'], 0, 3) as $i => $decision) {
                    if (! is_array($decision)) {
                        continue;
                    }

                    $formatted .= sprintf(
                        "%d. %s\n   Court: %s\n   Case Number: %s\n   Date: %s\n\n",
                        $i + 1,
                        $decision['title'] ?? 'Untitled',
                        $decision['court'] ?? 'Unknown',
                        $decision['case_number'] ?? 'N/A',
                        $decision['decision_date'] ?? 'N/A'
                    );
                }
            }

            // Format cases
            if (isset($result['cases']) && is_array($result['cases'])) {
                $formatted .= "## Legal Cases Found:\n";
                foreach (array_slice($result['cases'], 0, 3) as $i => $case) {
                    if (! is_array($case)) {
                        continue;
                    }

                    $formatted .= sprintf(
                        "%d. %s (Case Number: %s)\n   Status: %s\n\n",
                        $i + 1,
                        $case['title'] ?? 'Untitled',
                        $case['case_number'] ?? 'N/A',
                        $case['status'] ?? 'Unknown'
                    );
                }
            }

            // Format graph results
            if (isset($result['rows']) && is_array($result['rows'])) {
                $formatted .= "## Related Entities (Graph):\n";
                $formatted .= count($result['rows'])." related entities found\n";
                foreach (array_slice($result['rows'], 0, 3) as $i => $row) {
                    try {
                        $formatted .= sprintf("%d. %s\n", $i + 1, json_encode($row));
                    } catch (\Exception $e) {
                        Log::debug('Failed to encode graph row', ['error' => $e->getMessage()]);

                        continue;
                    }
                }
            }

            return $formatted ?: 'No results to format';

        } catch (\Exception $e) {
            Log::error('Failed to format results for extraction', [
                'error' => $e->getMessage(),
                'result_keys' => array_keys($result),
            ]);

            return 'No results to format';
        }
    }

    /**
     * Get system prompt for insight extraction
     */
    protected function getInsightExtractionPrompt(): string
    {
        return <<<'PROMPT'
You are an expert Croatian legal analyst. Your task is to extract concise, actionable legal insights from search results.

GUIDELINES:
1. Focus on the most relevant finding for the research objective
2. Always include proper legal citations (law numbers, article numbers, case numbers)
3. State the legal principle or rule clearly
4. Keep insights to 1-2 sentences maximum
5. Use Croatian legal terminology accurately
6. Distinguish between binding law and persuasive precedent

GOOD EXAMPLES:
- "Article 93 of the Croatian Labor Law (NN 93/14) requires employers to provide written notice 2 weeks before termination for employees with less than 2 years of service."
- "Supreme Court in Gž-1234/2023 held that termination without cause during probationary period (first 6 months) does not require notice under Article 52."
- "Law on Obligations (NN 35/05) Article 278 establishes that contracts must be performed in good faith, which courts consistently interpret to include disclosure obligations."

BAD EXAMPLES:
- "Found a law about employment" (too vague, no citation)
- "The Labor Law has many articles dealing with termination procedures and notice periods..." (too long, not specific)
- "This is interesting information" (no legal content)

If search results contain no relevant information, respond with: null
PROMPT;
    }

    /**
     * Get system prompt for answer evaluation
     */
    protected function getEvaluationSystemPrompt(): string
    {
        return <<<'PROMPT'
You are an expert legal research quality evaluator. Your task is to evaluate the quality of legal research answers.

Evaluate answers based on:
1. CITATIONS: Does the answer include proper legal citations (law numbers, article numbers, case numbers)?
2. RELEVANCE: Does the answer directly address the research query?
3. ACTIONABILITY: Is the answer specific and actionable (not vague or generic)?
4. COMPLETENESS: Does the answer provide sufficient detail?

Respond in this exact JSON format:
{
  "quality_score": 0.85,
  "has_citations": true,
  "is_relevant": true,
  "is_actionable": true,
  "feedback": "Specific feedback on what's good or what needs improvement"
}

Quality score scale:
- 0.9-1.0: Excellent (proper citations, highly relevant, actionable, complete)
- 0.7-0.9: Good (minor improvements needed)
- 0.5-0.7: Fair (lacks citations or detail)
- 0.3-0.5: Poor (vague or incomplete)
- 0.0-0.3: Very poor (not useful)
PROMPT;
    }

    /**
     * Build evaluation prompt
     */
    protected function buildEvaluationPrompt(string $query, string $answer, array $sources): string
    {
        $sourcesSummary = count($sources).' sources available';
        if (count($sources) > 0) {
            $sourceTypes = array_count_values(array_column($sources, 'type'));
            $sourcesSummary .= ' ('.implode(', ', array_map(
                fn ($type, $count) => "$count $type",
                array_keys($sourceTypes),
                $sourceTypes
            )).')';
        }

        return <<<PROMPT
Research Query: {$query}

Answer to Evaluate:
{$answer}

Sources: {$sourcesSummary}

Evaluate this answer and provide your assessment in JSON format.
PROMPT;
    }

    /**
     * Parse evaluation response from LLM
     */
    protected function parseEvaluation(string $response): array
    {
        try {
            // Try to extract JSON from response
            if (preg_match('/\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/s', $response, $matches)) {
                $json = json_decode($matches[0], true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    Log::debug('JSON decode error', [
                        'error' => json_last_error_msg(),
                        'response' => substr($response, 0, 200),
                    ]);
                } elseif (is_array($json)) {
                    return [
                        'quality_score' => (float) ($json['quality_score'] ?? 0.5),
                        'has_citations' => (bool) ($json['has_citations'] ?? false),
                        'is_relevant' => (bool) ($json['is_relevant'] ?? false),
                        'is_actionable' => (bool) ($json['is_actionable'] ?? false),
                        'feedback' => $json['feedback'] ?? 'No feedback provided',
                    ];
                }
            }

            // Fallback: try to parse as plain text
            Log::debug('Failed to extract JSON from evaluation response', [
                'response' => substr($response, 0, 200),
            ]);

            return [
                'quality_score' => 0.5,
                'has_citations' => false,
                'is_relevant' => false,
                'is_actionable' => false,
                'feedback' => 'Failed to parse evaluation response',
            ];

        } catch (\Exception $e) {
            Log::warning('Exception during evaluation parsing', [
                'error' => $e->getMessage(),
                'response' => substr($response, 0, 200),
            ]);

            return [
                'quality_score' => 0.5,
                'has_citations' => false,
                'is_relevant' => false,
                'is_actionable' => false,
                'feedback' => 'Exception during parsing: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Fallback evaluation using simple heuristics
     */
    protected function fallbackEvaluation(string $query, string $answer, array $sources): array
    {
        try {
            $hasCitations = $this->detectCitations($answer);
            $isRelevant = $this->checkRelevance($query, $answer);
            $isActionable = strlen($answer) >= 50 && ! empty($sources);

            $score = 0.0;
            if ($hasCitations) {
                $score += 0.3;
            }
            if ($isRelevant) {
                $score += 0.3;
            }
            if ($isActionable) {
                $score += 0.2;
            }
            if (count($sources) > 0) {
                $score += 0.2;
            }

            return [
                'quality_score' => $score,
                'has_citations' => $hasCitations,
                'is_relevant' => $isRelevant,
                'is_actionable' => $isActionable,
                'feedback' => 'Evaluated using fallback heuristics',
            ];

        } catch (\Exception $e) {
            Log::warning('Fallback evaluation failed', [
                'error' => $e->getMessage(),
                'query' => substr($query, 0, 100),
                'answer' => substr($answer, 0, 100),
            ]);

            // Return minimal default evaluation
            return [
                'quality_score' => 0.3,
                'has_citations' => false,
                'is_relevant' => false,
                'is_actionable' => false,
                'feedback' => 'Fallback evaluation failed: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Detect presence of legal citations in text
     */
    protected function detectCitations(string $text): bool
    {
        // Check for common Croatian legal citation patterns
        $patterns = [
            '/[A-ZČĆĐŠŽ][a-zčćđšž]+-\d+\/\d+/', // Case numbers like "Gž-1234/2023"
            '/(?:NN|Narodne novine)\s+\d+\/\d+/iu', // NN citations
            '/Članak\s+\d+/iu', // Article references
            '/(?:Law|Zakon|ZKP|KZ)\s+\d+/iu', // Law references
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check relevance between query and answer using keyword overlap
     */
    protected function checkRelevance(string $query, string $answer): bool
    {
        $queryWords = $this->extractKeywords($query);
        $answerWords = $this->extractKeywords($answer);

        $overlap = count(array_intersect($queryWords, $answerWords));
        $relevanceRatio = count($queryWords) > 0 ? $overlap / count($queryWords) : 0;

        return $relevanceRatio >= 0.3; // At least 30% keyword overlap
    }

    /**
     * Extract keywords from text
     */
    protected function extractKeywords(string $text): array
    {
        $text = mb_strtolower($text);
        // Remove common Croatian stop words
        $stopWords = ['i', 'u', 'na', 'je', 'se', 'za', 'od', 'da', 'su', 'o', 's', 'do'];
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $keywords = array_filter($words, function ($word) use ($stopWords) {
            return strlen($word) > 3 && ! in_array($word, $stopWords);
        });

        return array_values($keywords);
    }

    /**
     * Calculate relevance score for a single source
     */
    protected function calculateRelevanceScore(string $query, array $source): float
    {
        try {
            $score = 0.5; // Base score

            // Check if source has content
            $content = $source['content'] ?? $source['text'] ?? '';
            if (empty($content)) {
                return 0.3;
            }

            // Check keyword overlap
            $queryWords = $this->extractKeywords($query);
            $sourceWords = $this->extractKeywords($content);

            if (empty($queryWords)) {
                return $score; // Return base score if no query keywords
            }

            $overlap = count(array_intersect($queryWords, $sourceWords));
            $overlapRatio = count($queryWords) > 0 ? $overlap / count($queryWords) : 0;

            // Adjust score based on overlap
            $score += $overlapRatio * 0.4;

            // Bonus for having citations
            if ($this->detectCitations($content)) {
                $score += 0.1;
            }

            // Ensure score is between 0 and 1
            return max(0.0, min(1.0, $score));

        } catch (\Exception $e) {
            Log::debug('Failed to calculate relevance score', [
                'error' => $e->getMessage(),
                'source_type' => $source['type'] ?? 'unknown',
            ]);

            return 0.5; // Return default score on error
        }
    }
}

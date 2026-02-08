<?php

namespace App\Services\LegalReasoning;

use App\Exceptions\LegalReasoningException;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Log;

/**
 * Logic Engine
 *
 * Parses legal logic structures and applies deductive reasoning
 * using LLM-powered analysis and formal logic principles.
 */
class LogicEngine
{
    public function __construct(
        protected OpenAIService $openAI
    ) {}

    /**
     * Parse logical structure from law text
     */
    public function parseLogicStructure(string $lawText): array
    {
        $startTime = microtime(true);

        Log::info('Starting legal logic structure parsing', [
            'text_length' => strlen($lawText),
        ]);

        try {
            // Phase 1: Prepare prompt
            $promptStart = microtime(true);
            $prompt = "You are a legal logic expert. Analyze the following legal text and extract its logical structure.

Legal Text:
{$lawText}

Extract and return in JSON format:
{
  \"premises\": [
    {\"id\": \"P1\", \"statement\": \"condition or requirement\", \"type\": \"condition|requirement|fact\"}
  ],
  \"conclusions\": [
    {\"id\": \"C1\", \"statement\": \"result or consequence\", \"type\": \"obligation|right|prohibition|permission\"}
  ],
  \"conditionals\": [
    {\"if\": \"P1\", \"then\": \"C1\", \"logical_operator\": \"AND|OR|NOT\"}
  ],
  \"exceptions\": [
    {\"exception_to\": \"C1\", \"condition\": \"when X applies\"}
  ],
  \"definitions\": [
    {\"term\": \"term name\", \"definition\": \"legal definition\"}
  ]
}

Focus on extracting:
1. Conditions that must be satisfied (IF clauses)
2. Results that follow (THEN clauses)
3. Logical relationships (AND, OR, NOT)
4. Exceptions to rules
5. Key legal term definitions

Return only valid JSON.";
            $promptDuration = microtime(true) - $promptStart;

            // Phase 2: Call OpenAI
            $openaiStart = microtime(true);
            try {
                $response = $this->openAI->complete($prompt, [
                    'model' => 'gpt-4o-mini',
                    'max_tokens' => 1500,
                    'temperature' => 0.2,
                ]);
                $openaiDuration = microtime(true) - $openaiStart;

                Log::info('OpenAI logic parsing completed', [
                    'duration_ms' => round($openaiDuration * 1000, 2),
                    'model' => 'gpt-4o-mini',
                ]);
            } catch (\Throwable $e) {
                $openaiDuration = microtime(true) - $openaiStart;
                Log::error('OpenAI logic parsing failed', [
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e),
                    'duration_ms' => round($openaiDuration * 1000, 2),
                    'trace' => $e->getTraceAsString(),
                ]);
                throw new LegalReasoningException(
                    "OpenAI logic parsing failed: {$e->getMessage()}",
                    LegalReasoningException::LOGIC_VALIDATION_FAILED,
                    $e
                );
            }

            // Phase 3: Extract JSON from response
            $extractionStart = microtime(true);
            $content = $response['content'] ?? '';

            if (! preg_match('/\{[\s\S]*\}/', $content, $matches)) {
                $extractionDuration = microtime(true) - $extractionStart;
                $totalDuration = microtime(true) - $startTime;

                Log::error('Failed to extract JSON from LLM response', [
                    'content_length' => strlen($content),
                    'extraction_duration_ms' => round($extractionDuration * 1000, 2),
                    'total_duration_ms' => round($totalDuration * 1000, 2),
                ]);

                throw new LegalReasoningException(
                    'LLM response did not contain valid JSON structure',
                    LegalReasoningException::LOGIC_VALIDATION_FAILED
                );
            }

            $extractionDuration = microtime(true) - $extractionStart;

            // Phase 4: Decode JSON
            $decodeStart = microtime(true);
            $structure = json_decode($matches[0], true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $decodeDuration = microtime(true) - $decodeStart;
                $totalDuration = microtime(true) - $startTime;
                $jsonError = json_last_error_msg();

                Log::error('JSON decoding failed', [
                    'json_error' => $jsonError,
                    'json_content' => substr($matches[0], 0, 500),
                    'decode_duration_ms' => round($decodeDuration * 1000, 2),
                    'total_duration_ms' => round($totalDuration * 1000, 2),
                ]);

                throw new LegalReasoningException(
                    "JSON decoding failed: {$jsonError}",
                    LegalReasoningException::LOGIC_VALIDATION_FAILED
                );
            }

            $decodeDuration = microtime(true) - $decodeStart;

            Log::info('Logic structure extracted successfully', [
                'premises_count' => count($structure['premises'] ?? []),
                'conclusions_count' => count($structure['conclusions'] ?? []),
                'conditionals_count' => count($structure['conditionals'] ?? []),
                'decode_duration_ms' => round($decodeDuration * 1000, 2),
            ]);

            // Phase 5: Validate and enrich
            $validationStart = microtime(true);
            try {
                $enriched = $this->validateAndEnrichStructure($structure);
                $validationDuration = microtime(true) - $validationStart;

                Log::info('Structure validation completed', [
                    'validation_duration_ms' => round($validationDuration * 1000, 2),
                ]);

                $totalDuration = microtime(true) - $startTime;
                Log::info('Logic structure parsing completed successfully', [
                    'total_duration_ms' => round($totalDuration * 1000, 2),
                    'complexity_score' => $enriched['metadata']['complexity_score'],
                    'timing_breakdown' => [
                        'prompt_ms' => round($promptDuration * 1000, 2),
                        'openai_ms' => round($openaiDuration * 1000, 2),
                        'extraction_ms' => round($extractionDuration * 1000, 2),
                        'decode_ms' => round($decodeDuration * 1000, 2),
                        'validation_ms' => round($validationDuration * 1000, 2),
                    ],
                ]);

                return $enriched;
            } catch (LegalReasoningException $e) {
                throw $e;
            } catch (\Throwable $e) {
                $validationDuration = microtime(true) - $validationStart;
                Log::error('Structure validation failed', [
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e),
                    'validation_duration_ms' => round($validationDuration * 1000, 2),
                    'trace' => $e->getTraceAsString(),
                ]);
                throw new LegalReasoningException(
                    "Structure validation failed: {$e->getMessage()}",
                    LegalReasoningException::LOGIC_VALIDATION_FAILED,
                    $e
                );
            }
        } catch (LegalReasoningException $e) {
            $totalDuration = microtime(true) - $startTime;
            Log::error('Logic structure parsing failed', [
                'error_code' => $e->getCode(),
                'error' => $e->getMessage(),
                'total_duration_ms' => round($totalDuration * 1000, 2),
            ]);
            throw $e;
        } catch (\Throwable $e) {
            $totalDuration = microtime(true) - $startTime;
            Log::error('Logic structure parsing failed with unexpected error', [
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'total_duration_ms' => round($totalDuration * 1000, 2),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new LegalReasoningException(
                "Unexpected error in logic parsing: {$e->getMessage()}",
                LegalReasoningException::UNEXPECTED_ERROR,
                $e
            );
        }
    }

    /**
     * Apply deductive reasoning to facts using rules
     */
    public function applyDeductiveReasoning(array $facts, array $rules): array
    {
        $startTime = microtime(true);

        Log::info('Starting deductive reasoning', [
            'fact_count' => count($facts),
            'rule_count' => count($rules),
        ]);

        try {
            $validInferences = [];
            $processedRules = 0;
            $matchedRules = 0;

            foreach ($rules as $ruleIndex => $rule) {
                $ruleStart = microtime(true);
                $ruleId = $rule['id'] ?? "R{$ruleIndex}";

                try {
                    // Phase 1: Check premises
                    $premiseCheckStart = microtime(true);
                    $premisesSatisfied = $this->checkPremises($facts, $rule['premises'] ?? []);
                    $premiseCheckDuration = microtime(true) - $premiseCheckStart;

                    if (! $premisesSatisfied) {
                        $processedRules++;

                        continue;
                    }

                    // Phase 2: Calculate confidence
                    $confidenceStart = microtime(true);
                    $confidence = $this->calculateConfidence($facts, $rule);
                    $confidenceDuration = microtime(true) - $confidenceStart;

                    // Phase 3: Match facts
                    $matchingStart = microtime(true);
                    $supportingFacts = $this->matchingFacts($facts, $rule);
                    $matchingDuration = microtime(true) - $matchingStart;

                    // Phase 4: Build reasoning chain
                    $chainStart = microtime(true);
                    $reasoningChain = $this->buildReasoningChain($supportingFacts, $rule);
                    $chainDuration = microtime(true) - $chainStart;

                    $inference = [
                        'rule_id' => $ruleId,
                        'conclusion' => $rule['conclusion'] ?? 'Unknown conclusion',
                        'confidence' => $confidence,
                        'supporting_facts' => $supportingFacts,
                        'reasoning_chain' => $reasoningChain,
                    ];

                    $validInferences[] = $inference;
                    $matchedRules++;

                    $ruleDuration = microtime(true) - $ruleStart;
                    Log::debug('Valid inference found', [
                        'rule_id' => $ruleId,
                        'confidence' => $confidence,
                        'supporting_facts_count' => count($supportingFacts),
                        'rule_duration_ms' => round($ruleDuration * 1000, 2),
                        'timing_breakdown' => [
                            'premise_check_ms' => round($premiseCheckDuration * 1000, 2),
                            'confidence_ms' => round($confidenceDuration * 1000, 2),
                            'matching_ms' => round($matchingDuration * 1000, 2),
                            'chain_ms' => round($chainDuration * 1000, 2),
                        ],
                    ]);

                    $processedRules++;
                } catch (\Throwable $e) {
                    $ruleDuration = microtime(true) - $ruleStart;
                    Log::error('Rule processing failed', [
                        'rule_id' => $ruleId,
                        'rule_index' => $ruleIndex,
                        'error' => $e->getMessage(),
                        'error_class' => get_class($e),
                        'rule_duration_ms' => round($ruleDuration * 1000, 2),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    // Continue processing other rules
                    $processedRules++;

                    continue;
                }
            }

            $totalDuration = microtime(true) - $startTime;
            Log::info('Deductive reasoning completed successfully', [
                'inferences_found' => count($validInferences),
                'rules_processed' => $processedRules,
                'rules_matched' => $matchedRules,
                'match_rate' => $processedRules > 0 ? round(($matchedRules / $processedRules) * 100, 2) : 0,
                'total_duration_ms' => round($totalDuration * 1000, 2),
                'avg_rule_duration_ms' => $processedRules > 0 ? round(($totalDuration / $processedRules) * 1000, 2) : 0,
            ]);

            return $validInferences;
        } catch (\Throwable $e) {
            $totalDuration = microtime(true) - $startTime;
            Log::error('Deductive reasoning failed with unexpected error', [
                'fact_count' => count($facts),
                'rule_count' => count($rules),
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'total_duration_ms' => round($totalDuration * 1000, 2),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new LegalReasoningException(
                "Unexpected error in deductive reasoning: {$e->getMessage()}",
                LegalReasoningException::LOGIC_VALIDATION_FAILED,
                $e
            );
        }
    }

    /**
     * Check if premises are satisfied by facts
     */
    protected function checkPremises(array $facts, array $premises): bool
    {
        try {
            if (empty($premises)) {
                Log::debug('Premise check: no premises provided');

                return false;
            }

            if (empty($facts)) {
                Log::debug('Premise check: no facts provided', [
                    'premise_count' => count($premises),
                ]);

                return false;
            }

            // Convert facts to searchable format
            $factStatements = array_map(function ($fact) {
                return strtolower($fact['statement'] ?? $fact);
            }, $facts);

            $satisfiedCount = 0;
            $unsatisfiedPremises = [];

            // Check each premise
            foreach ($premises as $premiseIndex => $premise) {
                $premiseStatement = strtolower($premise['statement'] ?? $premise);
                $premiseType = $premise['type'] ?? 'condition';

                $found = false;
                $bestSimilarity = 0.0;

                // Look for matching or similar facts
                foreach ($factStatements as $factIndex => $factStatement) {
                    try {
                        $similarity = $this->calculateTextSimilarity($premiseStatement, $factStatement);
                        $bestSimilarity = max($bestSimilarity, $similarity);

                        if ($similarity > 0.7) { // 70% similarity threshold
                            $found = true;
                            break;
                        }
                    } catch (\Throwable $e) {
                        Log::warning('Text similarity calculation failed', [
                            'premise_index' => $premiseIndex,
                            'fact_index' => $factIndex,
                            'error' => $e->getMessage(),
                        ]);

                        continue;
                    }
                }

                if ($found) {
                    $satisfiedCount++;
                } else {
                    $unsatisfiedPremises[] = [
                        'index' => $premiseIndex,
                        'statement' => substr($premiseStatement, 0, 100),
                        'best_similarity' => $bestSimilarity,
                    ];
                }
            }

            $allSatisfied = $satisfiedCount === count($premises);

            Log::debug('Premise check completed', [
                'total_premises' => count($premises),
                'satisfied_count' => $satisfiedCount,
                'all_satisfied' => $allSatisfied,
                'unsatisfied_count' => count($unsatisfiedPremises),
            ]);

            return $allSatisfied;
        } catch (\Throwable $e) {
            Log::error('Premise check failed', [
                'premise_count' => count($premises),
                'fact_count' => count($facts),
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Calculate confidence in the inference
     */
    protected function calculateConfidence(array $facts, array $rule): float
    {
        try {
            $baseConfidence = 0.8; // Start with high base confidence

            $premises = $rule['premises'] ?? [];
            if (empty($premises)) {
                Log::debug('Confidence calculation: no premises provided');

                return 0.0;
            }

            // Adjust based on number of premises satisfied
            $satisfiedPremises = 0;
            foreach ($premises as $premise) {
                try {
                    if ($this->isPremiseSatisfied($premise, $facts)) {
                        $satisfiedPremises++;
                    }
                } catch (\Throwable $e) {
                    Log::warning('Premise satisfaction check failed', [
                        'premise' => substr(json_encode($premise), 0, 100),
                        'error' => $e->getMessage(),
                    ]);

                    continue;
                }
            }

            $premiseSatisfactionRatio = $satisfiedPremises / count($premises);

            // Adjust for rule specificity
            $specificityBonus = isset($rule['specificity']) ? $rule['specificity'] * 0.1 : 0.0;

            $confidence = ($baseConfidence * $premiseSatisfactionRatio) + $specificityBonus;
            $finalConfidence = round(min(1.0, $confidence), 2);

            Log::debug('Confidence calculated', [
                'satisfied_premises' => $satisfiedPremises,
                'total_premises' => count($premises),
                'satisfaction_ratio' => round($premiseSatisfactionRatio, 2),
                'specificity_bonus' => $specificityBonus,
                'final_confidence' => $finalConfidence,
            ]);

            return $finalConfidence;
        } catch (\Throwable $e) {
            Log::error('Confidence calculation failed', [
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            return 0.0;
        }
    }

    /**
     * Find facts that match rule premises
     */
    protected function matchingFacts(array $facts, array $rule): array
    {
        try {
            $matching = [];
            $premises = $rule['premises'] ?? [];

            if (empty($premises)) {
                Log::debug('Matching facts: no premises provided');

                return [];
            }

            if (empty($facts)) {
                Log::debug('Matching facts: no facts provided');

                return [];
            }

            foreach ($facts as $factIndex => $fact) {
                $factStatement = strtolower($fact['statement'] ?? $fact);

                foreach ($premises as $premiseIndex => $premise) {
                    try {
                        $premiseStatement = strtolower($premise['statement'] ?? $premise);
                        $similarity = $this->calculateTextSimilarity($premiseStatement, $factStatement);

                        if ($similarity > 0.7) {
                            $matching[] = [
                                'fact_id' => $fact['id'] ?? "F{$factIndex}",
                                'statement' => $fact['statement'] ?? $fact,
                                'matches_premise' => $premise['id'] ?? "P{$premiseIndex}",
                                'similarity' => round($similarity, 2),
                            ];
                        }
                    } catch (\Throwable $e) {
                        Log::warning('Fact-premise matching failed', [
                            'fact_index' => $factIndex,
                            'premise_index' => $premiseIndex,
                            'error' => $e->getMessage(),
                        ]);

                        continue;
                    }
                }
            }

            Log::debug('Fact matching completed', [
                'total_facts' => count($facts),
                'total_premises' => count($premises),
                'matches_found' => count($matching),
            ]);

            return $matching;
        } catch (\Throwable $e) {
            Log::error('Fact matching failed', [
                'fact_count' => count($facts),
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            return [];
        }
    }

    /**
     * Build reasoning chain explanation
     */
    protected function buildReasoningChain(array $supportingFacts, array $rule): array
    {
        try {
            $chain = [];

            // Add premises
            foreach ($supportingFacts as $factIndex => $fact) {
                try {
                    $chain[] = [
                        'step' => count($chain) + 1,
                        'type' => 'premise',
                        'statement' => $fact['statement'] ?? 'Unknown statement',
                    ];
                } catch (\Throwable $e) {
                    Log::warning('Failed to add premise to reasoning chain', [
                        'fact_index' => $factIndex,
                        'error' => $e->getMessage(),
                    ]);

                    continue;
                }
            }

            // Add logical operation
            $operator = $rule['logical_operator'] ?? 'AND';
            if (count($supportingFacts) > 1) {
                $chain[] = [
                    'step' => count($chain) + 1,
                    'type' => 'operation',
                    'operator' => $operator,
                ];
            }

            // Add conclusion
            $chain[] = [
                'step' => count($chain) + 1,
                'type' => 'conclusion',
                'statement' => $rule['conclusion'] ?? 'Result',
            ];

            Log::debug('Reasoning chain built', [
                'chain_length' => count($chain),
                'supporting_facts' => count($supportingFacts),
                'operator' => $operator,
            ]);

            return $chain;
        } catch (\Throwable $e) {
            Log::error('Failed to build reasoning chain', [
                'supporting_facts_count' => count($supportingFacts),
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            return [];
        }
    }

    /**
     * Check if a single premise is satisfied
     */
    protected function isPremiseSatisfied(array $premise, array $facts): bool
    {
        try {
            $premiseStatement = strtolower($premise['statement'] ?? '');

            if (empty($premiseStatement)) {
                Log::debug('Premise satisfaction check: empty premise statement');

                return false;
            }

            foreach ($facts as $factIndex => $fact) {
                try {
                    $factStatement = strtolower($fact['statement'] ?? $fact);
                    $similarity = $this->calculateTextSimilarity($premiseStatement, $factStatement);

                    if ($similarity > 0.7) {
                        return true;
                    }
                } catch (\Throwable $e) {
                    Log::warning('Similarity check failed in premise satisfaction', [
                        'fact_index' => $factIndex,
                        'error' => $e->getMessage(),
                    ]);

                    continue;
                }
            }

            return false;
        } catch (\Throwable $e) {
            Log::error('Premise satisfaction check failed', [
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Calculate text similarity (simple word overlap)
     */
    protected function calculateTextSimilarity(string $text1, string $text2): float
    {
        try {
            if (empty($text1) || empty($text2)) {
                return 0.0;
            }

            // Remove common words and punctuation
            $text1 = preg_replace('/[^\w\s]/u', '', $text1);
            $text2 = preg_replace('/[^\w\s]/u', '', $text2);

            if ($text1 === false || $text2 === false) {
                Log::warning('Regex replacement failed in text similarity');

                return 0.0;
            }

            $words1 = array_filter(explode(' ', $text1));
            $words2 = array_filter(explode(' ', $text2));

            if (empty($words1) || empty($words2)) {
                return 0.0;
            }

            // Calculate Jaccard similarity
            $intersection = count(array_intersect($words1, $words2));
            $union = count(array_unique(array_merge($words1, $words2)));

            $similarity = $union > 0 ? $intersection / $union : 0.0;

            return round($similarity, 4);
        } catch (\Throwable $e) {
            Log::error('Text similarity calculation failed', [
                'text1_length' => strlen($text1),
                'text2_length' => strlen($text2),
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            return 0.0;
        }
    }

    /**
     * Validate and enrich parsed structure
     */
    protected function validateAndEnrichStructure(array $structure): array
    {
        $startTime = microtime(true);

        try {
            Log::info('Starting structure validation and enrichment');

            // Ensure all required keys exist
            $structure['premises'] = $structure['premises'] ?? [];
            $structure['conclusions'] = $structure['conclusions'] ?? [];
            $structure['conditionals'] = $structure['conditionals'] ?? [];
            $structure['exceptions'] = $structure['exceptions'] ?? [];
            $structure['definitions'] = $structure['definitions'] ?? [];

            // Calculate complexity
            $complexityStart = microtime(true);
            try {
                $complexityScore = $this->calculateComplexityScore($structure);
                $complexityDuration = microtime(true) - $complexityStart;

                Log::debug('Complexity score calculated', [
                    'score' => $complexityScore,
                    'duration_ms' => round($complexityDuration * 1000, 2),
                ]);
            } catch (\Throwable $e) {
                $complexityDuration = microtime(true) - $complexityStart;
                Log::error('Complexity calculation failed', [
                    'error' => $e->getMessage(),
                    'duration_ms' => round($complexityDuration * 1000, 2),
                ]);
                $complexityScore = 0.0;
            }

            // Add metadata
            $structure['metadata'] = [
                'parsed_at' => now()->toIso8601String(),
                'complexity_score' => $complexityScore,
                'total_components' => count($structure['premises']) +
                                     count($structure['conclusions']) +
                                     count($structure['conditionals']),
                'component_counts' => [
                    'premises' => count($structure['premises']),
                    'conclusions' => count($structure['conclusions']),
                    'conditionals' => count($structure['conditionals']),
                    'exceptions' => count($structure['exceptions']),
                    'definitions' => count($structure['definitions']),
                ],
            ];

            $totalDuration = microtime(true) - $startTime;
            Log::info('Structure validation completed successfully', [
                'total_components' => $structure['metadata']['total_components'],
                'complexity_score' => $complexityScore,
                'total_duration_ms' => round($totalDuration * 1000, 2),
            ]);

            return $structure;
        } catch (\Throwable $e) {
            $totalDuration = microtime(true) - $startTime;
            Log::error('Structure validation failed', [
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'total_duration_ms' => round($totalDuration * 1000, 2),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new LegalReasoningException(
                "Structure validation failed: {$e->getMessage()}",
                LegalReasoningException::LOGIC_VALIDATION_FAILED,
                $e
            );
        }
    }

    /**
     * Calculate complexity score of logical structure
     */
    protected function calculateComplexityScore(array $structure): float
    {
        try {
            $score = 0.0;

            // More components = more complex
            $score += count($structure['premises'] ?? []) * 0.1;
            $score += count($structure['conclusions'] ?? []) * 0.1;
            $score += count($structure['conditionals'] ?? []) * 0.15;
            $score += count($structure['exceptions'] ?? []) * 0.2;

            $finalScore = round(min(1.0, $score), 2);

            Log::debug('Complexity score components', [
                'premises' => count($structure['premises'] ?? []),
                'conclusions' => count($structure['conclusions'] ?? []),
                'conditionals' => count($structure['conditionals'] ?? []),
                'exceptions' => count($structure['exceptions'] ?? []),
                'raw_score' => $score,
                'final_score' => $finalScore,
            ]);

            return $finalScore;
        } catch (\Throwable $e) {
            Log::error('Complexity score calculation failed', [
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            return 0.0;
        }
    }

    /**
     * Get empty structure template
     */
    protected function getEmptyStructure(): array
    {
        try {
            Log::warning('Returning empty structure template due to parsing failure');

            return [
                'premises' => [],
                'conclusions' => [],
                'conditionals' => [],
                'exceptions' => [],
                'definitions' => [],
                'metadata' => [
                    'parsed_at' => now()->toIso8601String(),
                    'complexity_score' => 0.0,
                    'total_components' => 0,
                    'parsing_error' => true,
                ],
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to create empty structure', [
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return minimal structure even if something fails
            return [
                'premises' => [],
                'conclusions' => [],
                'conditionals' => [],
                'exceptions' => [],
                'definitions' => [],
                'metadata' => ['parsing_error' => true],
            ];
        }
    }
}

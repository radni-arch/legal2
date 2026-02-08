<?php

namespace App\Agents\Specialists;

use App\Services\ActiveLearningService;
use App\Services\Collaboration\SharedAgentContext;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Log;

/**
 * Precedent Analyst Agent
 *
 * Role: Analyzes applicability and strength of legal precedents
 * Expertise: Case law analysis, precedent comparison, binding authority assessment
 */
class PrecedentAnalystAgent
{
    protected string $name = 'precedent_analyst';

    protected string $role = 'Precedent Analyst';

    public function __construct(
        protected OpenAIService $openai,
        protected ?ActiveLearningService $learningService = null
    ) {
        // Prefer injected learning service; otherwise instantiate (works with test mocks)
        $this->learningService = $learningService ?: app(ActiveLearningService::class);
    }

    /**
     * Execute precedent analysis task
     */
    public function execute(SharedAgentContext $context, array $task): array
    {
        $context->logEvent('precedent_analysis_started', ['task' => $task]);

        $problemStatement = $context->getProblemStatement();

        // 1. Get research results from research specialist
        $laws = $context->read('researched_laws', []);
        $decisions = $context->read('researched_decisions', []);

        if (empty($laws) && empty($decisions)) {
            $context->logEvent('precedent_analysis_no_data', [
                'message' => 'No research data available from research specialist',
            ]);

            return [
                'success' => false,
                'error' => 'No research data available',
                'message' => 'Research specialist must execute first',
            ];
        }

        // 2. Analyze each precedent for applicability
        $analyzedDecisions = $this->analyzeDecisions($decisions, $problemStatement);
        $context->write('analyzed_decisions', $analyzedDecisions);

        // 3. Analyze applicable laws
        $analyzedLaws = $this->analyzeLaws($laws, $problemStatement);
        $context->write('analyzed_laws', $analyzedLaws);

        // 4. Identify strongest precedents
        $strongestPrecedents = $this->identifyStrongestPrecedents($analyzedDecisions);
        $context->write('strongest_precedents', $strongestPrecedents);

        // 5. Identify distinguishing factors
        $distinguishingFactors = $this->identifyDistinguishingFactors(
            $analyzedDecisions,
            $problemStatement
        );

        // 6. Send analysis to strategy specialist
        $context->sendMessage('strategy_specialist', [
            'type' => 'precedent_analysis_complete',
            'strongest_precedents' => $strongestPrecedents,
            'applicable_laws' => $analyzedLaws,
            'distinguishing_factors' => $distinguishingFactors,
            'summary' => $this->generateAnalysisSummary($strongestPrecedents, $analyzedLaws),
        ]);

        $context->logEvent('precedent_analysis_completed', [
            'decisions_analyzed' => count($analyzedDecisions),
            'laws_analyzed' => count($analyzedLaws),
            'strongest_count' => count($strongestPrecedents),
        ]);

        return [
            'success' => true,
            'decisions_analyzed' => count($analyzedDecisions),
            'laws_analyzed' => count($analyzedLaws),
            'strongest_precedents' => $strongestPrecedents,
            'applicable_laws' => $analyzedLaws,
            'distinguishing_factors' => $distinguishingFactors,
            'summary' => $this->generateAnalysisSummary($strongestPrecedents, $analyzedLaws),
        ];
    }

    /**
     * Analyze court decisions for applicability
     */
    protected function analyzeDecisions(array $decisions, string $problem): array
    {
        if (empty($decisions)) {
            return [];
        }

        // Batch decisions for LLM analysis
        $analyzed = [];

        // Process in batches of 5
        $batches = array_chunk($decisions, 5);

        foreach ($batches as $batchIndex => $batch) {
            try {
                $batchAnalysis = $this->analyzePrecedentBatch($batch, $problem);
                $analyzed = array_merge($analyzed, $batchAnalysis);
            } catch (\Exception $e) {
                Log::error('PrecedentAnalystAgent - Batch analysis failed', [
                    'batch' => $batchIndex,
                    'error' => $e->getMessage(),
                ]);

                // Add unanalyzed results with default scores
                foreach ($batch as $decision) {
                    $analyzed[] = array_merge($decision, [
                        'applicability_score' => 50,
                        'binding_authority' => 'unknown',
                        'key_factors' => [],
                        'analysis_error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $analyzed;
    }

    /**
     * Analyze a batch of precedents using LLM
     */
    protected function analyzePrecedentBatch(array $decisions, string $problem): array
    {
        $formattedDecisions = '';
        foreach ($decisions as $i => $decision) {
            $formattedDecisions .= "\n[{$i}] Court: ".($decision['court'] ?? 'N/A');
            $formattedDecisions .= "\n    Case: ".($decision['case_number'] ?? 'N/A');
            $formattedDecisions .= "\n    Date: ".($decision['decision_date'] ?? 'N/A');
            $formattedDecisions .= "\n    Summary: ".substr($decision['title'] ?? '', 0, 200)."\n";
        }

        $prompt = <<<PROMPT
You are a Croatian legal expert analyzing case precedents.

Current Problem: {$problem}

Precedents to Analyze:
{$formattedDecisions}

For each precedent, analyze:
1. Applicability score (0-100): How applicable is this precedent to the current problem?
2. Binding authority: "binding" (Supreme Court, must follow) | "persuasive" (lower courts) | "informative"
3. Key factors: List 1-3 key factors that make this precedent relevant or distinguishable
4. Favorable: Is this precedent favorable (true) or unfavorable (false) for the case?

Respond in JSON format:
{
  "analyses": [
    {
      "index": 0,
      "applicability_score": 85,
      "binding_authority": "binding",
      "key_factors": ["factor1", "factor2"],
      "favorable": true,
      "reasoning": "brief explanation"
    },
    ...
  ]
}
PROMPT;

        $response = $this->openai->chat([
            ['role' => 'system', 'content' => 'You are a Croatian legal precedent analyst.'],
            ['role' => 'user', 'content' => $prompt],
        ], 'gpt-4o-mini', [
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.3,
        ]);

        $content = $response['choices'][0]['message']['content'] ?? '{}';
        $analysis = json_decode($content, true);

        $analyzed = [];
        foreach ($analysis['analyses'] ?? [] as $item) {
            $index = $item['index'] ?? 0;
            if (isset($decisions[$index])) {
                $applicabilityScore = $item['applicability_score'] ?? 50;
                $normalizedConfidence = $applicabilityScore / 100; // Normalize to 0-1

                $analyzedDecision = array_merge($decisions[$index], [
                    'applicability_score' => $applicabilityScore,
                    'binding_authority' => $item['binding_authority'] ?? 'informative',
                    'key_factors' => $item['key_factors'] ?? [],
                    'favorable' => $item['favorable'] ?? null,
                    'analysis_reasoning' => $item['reasoning'] ?? '',
                ]);

                $analyzed[] = $analyzedDecision;

                // Flag low-confidence applicability scores as learning opportunities
                if ($applicabilityScore < 60) { // Threshold of 60/100 (0.6)
                    // Use crc32 hash of decision ID as source_id for deduplication
                    $decisionId = $decisions[$index]['id'] ?? 'unknown';
                    $sourceId = crc32($decisionId);

                    $this->learningService->identifyLearningOpportunity(
                        opportunityType: 'precedent_analysis',
                        sourceType: 'applicability_check',
                        sourceId: $sourceId,
                        aiOutput: [
                            'decision_id' => $decisionId,
                            'applicability_score' => $applicabilityScore,
                            'binding_authority' => $item['binding_authority'] ?? 'informative',
                            'key_factors' => $item['key_factors'] ?? [],
                            'favorable' => $item['favorable'] ?? null,
                            'reasoning' => $item['reasoning'] ?? '',
                            'court' => $decisions[$index]['court'] ?? 'N/A',
                            'case_number' => $decisions[$index]['case_number'] ?? 'N/A',
                        ],
                        confidence: $normalizedConfidence,
                        threshold: 0.6,
                        uncertaintyReason: "Low confidence score: {$normalizedConfidence} (threshold: 0.6)"
                    );
                }
            }
        }

        return $analyzed;
    }

    /**
     * Analyze laws for applicability
     */
    protected function analyzeLaws(array $laws, string $problem): array
    {
        if (empty($laws)) {
            return [];
        }

        // For now, simple relevance scoring
        // Can be enhanced with LLM analysis if needed
        $analyzed = [];

        foreach ($laws as $law) {
            $analyzed[] = array_merge($law, [
                'applicability' => 'potentially_applicable',
                'relevance_score' => $law['score'] ?? $law['similarity'] ?? 0,
            ]);
        }

        // Sort by relevance
        usort($analyzed, function ($a, $b) {
            return ($b['relevance_score'] ?? 0) <=> ($a['relevance_score'] ?? 0);
        });

        return array_slice($analyzed, 0, 10);
    }

    /**
     * Identify strongest precedents
     */
    protected function identifyStrongestPrecedents(array $analyzedDecisions): array
    {
        if (empty($analyzedDecisions)) {
            return [];
        }

        // Filter by high applicability and favorable status
        $strong = array_filter($analyzedDecisions, function ($decision) {
            return ($decision['applicability_score'] ?? 0) >= 70;
        });

        // Sort by applicability score and binding authority
        usort($strong, function ($a, $b) {
            // Binding authority weight
            $authorityWeight = [
                'binding' => 3,
                'persuasive' => 2,
                'informative' => 1,
            ];

            $scoreA = ($a['applicability_score'] ?? 0) * ($authorityWeight[$a['binding_authority'] ?? 'informative'] ?? 1);
            $scoreB = ($b['applicability_score'] ?? 0) * ($authorityWeight[$b['binding_authority'] ?? 'informative'] ?? 1);

            return $scoreB <=> $scoreA;
        });

        return array_slice($strong, 0, 10);
    }

    /**
     * Identify distinguishing factors
     */
    protected function identifyDistinguishingFactors(array $analyzedDecisions, string $problem): array
    {
        // Collect all key factors mentioned
        $allFactors = [];

        foreach ($analyzedDecisions as $decision) {
            foreach ($decision['key_factors'] ?? [] as $factor) {
                if (! empty($factor)) {
                    $allFactors[] = $factor;
                }
            }
        }

        // Count frequency
        $factorCounts = array_count_values($allFactors);
        arsort($factorCounts);

        // Return top 10 most mentioned factors
        return array_slice(array_keys($factorCounts), 0, 10);
    }

    /**
     * Generate analysis summary
     */
    protected function generateAnalysisSummary(array $strongestPrecedents, array $applicableLaws): string
    {
        $summary = "Precedent Analysis Complete:\n\n";

        $summary .= 'Strongest Precedents: '.count($strongestPrecedents)."\n";
        foreach (array_slice($strongestPrecedents, 0, 3) as $i => $precedent) {
            $court = $precedent['court'] ?? 'Unknown';
            $score = $precedent['applicability_score'] ?? 0;
            $authority = $precedent['binding_authority'] ?? 'informative';
            $summary .= ($i + 1).". {$court} (Score: {$score}, Authority: {$authority})\n";
        }

        $summary .= "\nApplicable Laws: ".count($applicableLaws)."\n";
        foreach (array_slice($applicableLaws, 0, 3) as $i => $law) {
            $title = $law['title'] ?? 'Untitled';
            $number = $law['law_number'] ?? 'N/A';
            $summary .= ($i + 1).". {$title} ({$number})\n";
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

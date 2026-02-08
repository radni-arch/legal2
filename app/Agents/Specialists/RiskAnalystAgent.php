<?php

namespace App\Agents\Specialists;

use App\Services\Collaboration\SharedAgentContext;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Log;

/**
 * Risk Analyst Agent
 *
 * Role: Identifies risks, weaknesses, and potential challenges
 * Expertise: Risk assessment, vulnerability analysis, defensive strategy
 */
class RiskAnalystAgent
{
    protected string $name = 'risk_analyst';

    protected string $role = 'Risk Analyst';

    public function __construct(
        protected OpenAIService $openai
    ) {}

    /**
     * Execute risk analysis task
     */
    public function execute(SharedAgentContext $context, array $task): array
    {
        $context->logEvent('risk_analysis_started', ['task' => $task]);

        $problemStatement = $context->getProblemStatement();

        // 1. Get strategy from strategy specialist
        $arguments = $context->read('legal_arguments', []);
        $recommendations = $context->read('strategic_recommendations', []);
        $strongestPrecedents = $context->read('strongest_precedents', []);

        if (empty($arguments)) {
            $context->logEvent('risk_analysis_no_data', [
                'message' => 'No strategy data available',
            ]);

            return [
                'success' => false,
                'error' => 'No strategy data available',
                'message' => 'Strategy specialist must execute first',
            ];
        }

        // 2. Identify argument weaknesses
        $argumentRisks = $this->analyzeArgumentRisks($arguments, $problemStatement);
        $context->write('argument_risks', $argumentRisks);

        // 3. Identify adverse precedents
        $adversePrecedents = $this->identifyAdversePrecedents($problemStatement, $strongestPrecedents);
        $context->write('adverse_precedents', $adversePrecedents);

        // 4. Assess procedural risks
        $proceduralRisks = $this->assessProceduralRisks($problemStatement, $recommendations);
        $context->write('procedural_risks', $proceduralRisks);

        // 5. Generate mitigation strategies
        $mitigations = $this->generateMitigations($argumentRisks, $adversePrecedents, $proceduralRisks);
        $context->write('risk_mitigations', $mitigations);

        // 6. Calculate overall risk score
        $riskScore = $this->calculateOverallRiskScore($argumentRisks, $adversePrecedents, $proceduralRisks);
        $context->write('overall_risk_score', $riskScore);

        $context->logEvent('risk_analysis_completed', [
            'argument_risks_count' => count($argumentRisks),
            'adverse_precedents_count' => count($adversePrecedents),
            'procedural_risks_count' => count($proceduralRisks),
            'overall_risk_score' => $riskScore['score'],
        ]);

        return [
            'success' => true,
            'argument_risks' => $argumentRisks,
            'adverse_precedents' => $adversePrecedents,
            'procedural_risks' => $proceduralRisks,
            'risk_mitigations' => $mitigations,
            'overall_risk_score' => $riskScore,
            'summary' => $this->generateRiskSummary($argumentRisks, $adversePrecedents, $riskScore),
        ];
    }

    /**
     * Analyze risks in legal arguments
     */
    protected function analyzeArgumentRisks(array $arguments, string $problem): array
    {
        $argumentsFormatted = '';
        foreach ($arguments as $i => $arg) {
            $argumentsFormatted .= "\n[{$i}] {$arg['title']}\n";
            $argumentsFormatted .= "Argument: {$arg['argument']}\n";
            $argumentsFormatted .= "Strength: {$arg['strength_score']}\n";
            if (! empty($arg['potential_counterarguments'])) {
                $argumentsFormatted .= 'Known Counterargs: '.implode('; ', $arg['potential_counterarguments'])."\n";
            }
            $argumentsFormatted .= "\n";
        }

        $prompt = <<<PROMPT
You are a Croatian legal risk analyst evaluating argument weaknesses.

Problem: {$problem}

Arguments to Analyze:
{$argumentsFormatted}

For each argument, identify:
1. Potential weaknesses
2. Likely opposing arguments
3. Evidence gaps
4. Risk level (low|medium|high|critical)
5. Impact if argument fails (low|medium|high)

Respond in JSON format:
{
  "argument_risks": [
    {
      "argument_index": 0,
      "argument_title": "title",
      "weaknesses": ["weakness1", "weakness2"],
      "opposing_arguments": ["opposing1", "opposing2"],
      "evidence_gaps": ["gap1", "gap2"],
      "risk_level": "medium",
      "impact_if_fails": "high",
      "mitigation_suggestions": ["suggestion1", "suggestion2"]
    },
    ...
  ]
}
PROMPT;

        try {
            $response = $this->openai->chat([
                ['role' => 'system', 'content' => 'You are a Croatian legal risk analyst.'],
                ['role' => 'user', 'content' => $prompt],
            ], 'gpt-4o-mini', [
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.3,
            ]);

            $content = $response['choices'][0]['message']['content'] ?? '{}';
            $data = json_decode($content, true);

            return $data['argument_risks'] ?? [];

        } catch (\Exception $e) {
            Log::error('RiskAnalystAgent - Argument risk analysis failed', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Identify adverse precedents that could hurt the case
     */
    protected function identifyAdversePrecedents(string $problem, array $favorablePrecedents): array
    {
        $prompt = <<<PROMPT
You are a Croatian legal analyst identifying adverse precedents.

Problem: {$problem}

We have identified favorable precedents. Now identify potential ADVERSE precedents that opposing counsel might use.

Consider:
1. Precedents with opposite outcomes
2. Precedents with similar facts but unfavorable rulings
3. Binding authority that goes against our position

Respond in JSON format:
{
  "adverse_precedents": [
    {
      "description": "Description of adverse precedent",
      "why_harmful": "Why this precedent hurts our case",
      "how_to_distinguish": "How we can distinguish it from our case",
      "severity": "low|medium|high",
      "likelihood_opposing_uses": "low|medium|high"
    },
    ...
  ]
}
PROMPT;

        try {
            $response = $this->openai->chat([
                ['role' => 'system', 'content' => 'You are a Croatian legal risk analyst.'],
                ['role' => 'user', 'content' => $prompt],
            ], 'gpt-4o-mini', [
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.4,
            ]);

            $content = $response['choices'][0]['message']['content'] ?? '{}';
            $data = json_decode($content, true);

            return $data['adverse_precedents'] ?? [];

        } catch (\Exception $e) {
            Log::error('RiskAnalystAgent - Adverse precedent identification failed', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Assess procedural risks
     */
    protected function assessProceduralRisks(string $problem, array $recommendations): array
    {
        $proceduralSteps = $recommendations['procedural_steps'] ?? [];
        $stepsFormatted = implode("\n", array_map(fn ($s, $i) => ($i + 1).". {$s}", $proceduralSteps, array_keys($proceduralSteps)));

        $prompt = <<<PROMPT
You are a Croatian legal risk analyst assessing procedural risks.

Problem: {$problem}

Planned Procedural Steps:
{$stepsFormatted}

Identify procedural risks including:
1. Statute of limitations issues
2. Jurisdictional challenges
3. Standing issues
4. Procedural defects
5. Timeline risks

Respond in JSON format:
{
  "procedural_risks": [
    {
      "risk_type": "type of risk",
      "description": "description",
      "severity": "low|medium|high|critical",
      "probability": "low|medium|high",
      "mitigation": "how to mitigate"
    },
    ...
  ]
}
PROMPT;

        try {
            $response = $this->openai->chat([
                ['role' => 'system', 'content' => 'You are a Croatian legal risk analyst.'],
                ['role' => 'user', 'content' => $prompt],
            ], 'gpt-4o-mini', [
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.3,
            ]);

            $content = $response['choices'][0]['message']['content'] ?? '{}';
            $data = json_decode($content, true);

            return $data['procedural_risks'] ?? [];

        } catch (\Exception $e) {
            Log::error('RiskAnalystAgent - Procedural risk assessment failed', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Generate mitigation strategies
     */
    protected function generateMitigations(array $argumentRisks, array $adversePrecedents, array $proceduralRisks): array
    {
        $mitigations = [];

        // Mitigations from argument risks
        foreach ($argumentRisks as $risk) {
            if (! empty($risk['mitigation_suggestions'])) {
                foreach ($risk['mitigation_suggestions'] as $suggestion) {
                    $mitigations[] = [
                        'type' => 'argument_weakness',
                        'target' => $risk['argument_title'] ?? 'Unknown',
                        'mitigation' => $suggestion,
                        'priority' => $risk['risk_level'] ?? 'medium',
                    ];
                }
            }
        }

        // Mitigations from adverse precedents
        foreach ($adversePrecedents as $precedent) {
            if (! empty($precedent['how_to_distinguish'])) {
                $mitigations[] = [
                    'type' => 'adverse_precedent',
                    'target' => 'Opposing precedent',
                    'mitigation' => $precedent['how_to_distinguish'],
                    'priority' => $precedent['severity'] ?? 'medium',
                ];
            }
        }

        // Mitigations from procedural risks
        foreach ($proceduralRisks as $risk) {
            if (! empty($risk['mitigation'])) {
                $mitigations[] = [
                    'type' => 'procedural',
                    'target' => $risk['risk_type'] ?? 'Procedural issue',
                    'mitigation' => $risk['mitigation'],
                    'priority' => $risk['severity'] ?? 'medium',
                ];
            }
        }

        // Sort by priority
        $priorityOrder = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];
        usort($mitigations, function ($a, $b) use ($priorityOrder) {
            return ($priorityOrder[$a['priority']] ?? 999) <=> ($priorityOrder[$b['priority']] ?? 999);
        });

        return $mitigations;
    }

    /**
     * Calculate overall risk score
     */
    protected function calculateOverallRiskScore(array $argumentRisks, array $adversePrecedents, array $proceduralRisks): array
    {
        $scores = [
            'critical' => 100,
            'high' => 75,
            'medium' => 50,
            'low' => 25,
        ];

        $totalRisk = 0;
        $riskCount = 0;

        // Score argument risks
        foreach ($argumentRisks as $risk) {
            $level = $risk['risk_level'] ?? 'medium';
            $totalRisk += $scores[$level] ?? 50;
            $riskCount++;
        }

        // Score adverse precedents
        foreach ($adversePrecedents as $precedent) {
            $severity = $precedent['severity'] ?? 'medium';
            $totalRisk += $scores[$severity] ?? 50;
            $riskCount++;
        }

        // Score procedural risks
        foreach ($proceduralRisks as $risk) {
            $severity = $risk['severity'] ?? 'medium';
            $totalRisk += $scores[$severity] ?? 50;
            $riskCount++;
        }

        $avgScore = $riskCount > 0 ? round($totalRisk / $riskCount) : 50;

        return [
            'score' => $avgScore,
            'level' => $this->getRiskLevel($avgScore),
            'category_breakdown' => [
                'argument_risks' => count($argumentRisks),
                'adverse_precedents' => count($adversePrecedents),
                'procedural_risks' => count($proceduralRisks),
            ],
        ];
    }

    /**
     * Get risk level from score
     */
    protected function getRiskLevel(int $score): string
    {
        if ($score >= 75) {
            return 'high';
        }
        if ($score >= 50) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Generate risk summary
     */
    protected function generateRiskSummary(array $argumentRisks, array $adversePrecedents, array $riskScore): string
    {
        $summary = "Risk Analysis Complete:\n\n";

        $summary .= "Overall Risk Level: {$riskScore['level']} ({$riskScore['score']}/100)\n\n";

        $summary .= "Risks Identified:\n";
        $summary .= '- Argument Weaknesses: '.count($argumentRisks)."\n";
        $summary .= '- Adverse Precedents: '.count($adversePrecedents)."\n";
        $summary .= '- Procedural Risks: '.($riskScore['category_breakdown']['procedural_risks'] ?? 0)."\n";

        if (! empty($argumentRisks)) {
            $summary .= "\nTop Argument Risks:\n";
            foreach (array_slice($argumentRisks, 0, 3) as $i => $risk) {
                $title = $risk['argument_title'] ?? 'Unknown';
                $level = $risk['risk_level'] ?? 'medium';
                $summary .= ($i + 1).". {$title} (Risk: {$level})\n";
            }
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

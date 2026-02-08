<?php

namespace App\Services\Analysis\CaseLevel\AI;

use App\Models\CaseAnalysis;
use App\Models\CaseDocument;
use App\Models\DocumentAnalysis;
use App\Services\Analysis\CaseLevel\AI\Contracts\ClaudeClientInterface;
use Illuminate\Support\Facades\Log;

/**
 * Strategy Analyzer - AI-powered legal strategy recommendation engine.
 *
 * Given all analysis layers (extraction, patterns, AI analysis, contradictions, gaps),
 * this analyzer suggests legal strategy directions:
 * - Which contradictions to exploit in cross-examination
 * - Which evidence to challenge for exclusion
 * - Which procedural arguments are strongest
 *
 * Specifically tuned for Croatian criminal defense patterns:
 * - Evidence exclusion under čl. 10 ZKP (fruit of poisonous tree)
 * - Search warrant challenges (čl. 240-244 ZKP)
 * - Detention review arguments (čl. 123, 130, 131 ZKP)
 * - Witness credibility attacks
 * - Chain of custody challenges
 */
class StrategyAnalyzer
{
    private const ANALYSIS_TYPE = 'strategy';

    public function __construct(
        private ClaudeClientInterface $claudeClient
    ) {}

    /**
     * Get the analysis type identifier.
     */
    public function analysisType(): string
    {
        return self::ANALYSIS_TYPE;
    }

    /**
     * Analyze a case and generate strategic recommendations.
     *
     * @param string $caseId The case identifier
     * @return array Analysis results with structure:
     *               ['results' => ['strategies' => array, 'priority_actions' => array, ...], 'metadata' => array]
     */
    public function analyze(string $caseId): array
    {
        $startTime = microtime(true);

        Log::info("StrategyAnalyzer: Starting analysis for case {$caseId}");

        // Gather all documents for this case
        $documents = $this->gatherCaseDocuments($caseId);
        $documentsAnalyzed = $documents->count();

        // Gather all completed document-level analyses
        $documentAnalyses = $this->gatherDocumentAnalyses($documents);

        // Gather case-level analyses (contradictions, gaps, metacase, etc.)
        $caseAnalyses = $this->gatherCaseAnalyses($caseId);

        // Early return if insufficient data
        if ($documentsAnalyzed === 0) {
            Log::info("StrategyAnalyzer: No documents found for case {$caseId}");
            return $this->buildEmptyResult($documentsAnalyzed, $startTime);
        }

        // Build comprehensive analysis payload
        $analysisData = $this->buildAnalysisPayload($caseId, $documents, $documentAnalyses, $caseAnalyses);

        // Call Claude API for strategic insights
        $claudeResponse = $this->claudeClient->generateStrategicInsights($analysisData, [
            'case_id' => $caseId,
            'document_count' => $documentsAnalyzed,
            'jurisdiction' => 'HR',
            'defense_focus' => true,
        ]);

        // Process and structure the results
        $processedResults = $this->processClaudeResponse($claudeResponse);

        $processingTime = round(microtime(true) - $startTime, 4);

        Log::info("StrategyAnalyzer: Completed analysis for case {$caseId}", [
            'strategies_generated' => count($processedResults['strategies']),
            'priority_actions' => count($processedResults['priority_actions']),
            'processing_time_seconds' => $processingTime,
        ]);

        $documentIds = $documents->pluck('id')->map(fn($id) => (string) $id)->toArray();

        return [
            'results' => $processedResults,
            'metadata' => [
                'documents_analyzed' => $documentsAnalyzed,
                'document_ids' => $documentIds,
                'analyses_included' => array_keys($caseAnalyses),
                'processing_time_seconds' => $processingTime,
                'model' => config('services.claude.model', 'claude-sonnet-4-5-20250929'),
            ],
        ];
    }

    /**
     * Gather all documents for the case.
     */
    private function gatherCaseDocuments(string $caseId)
    {
        return CaseDocument::where('case_id', $caseId)
            ->select('id', 'title', 'category', 'case_id', 'metadata', 'created_at')
            ->get();
    }

    /**
     * Gather all completed document-level analyses.
     */
    private function gatherDocumentAnalyses($documents): array
    {
        $documentIds = $documents->pluck('id');

        $analyses = DocumentAnalysis::whereIn('case_document_id', $documentIds)
            ->where('status', DocumentAnalysis::STATUS_COMPLETED)
            ->get();

        // Group by document and type
        $grouped = [];
        foreach ($analyses as $analysis) {
            $docId = (string) $analysis->case_document_id;
            $type = $analysis->analysis_type;

            if (!isset($grouped[$docId])) {
                $grouped[$docId] = [];
            }
            $grouped[$docId][$type] = $analysis->results;
        }

        return $grouped;
    }

    /**
     * Gather all case-level analyses (contradictions, gaps, metacase, etc.).
     */
    private function gatherCaseAnalyses(string $caseId): array
    {
        $caseAnalyses = CaseAnalysis::where('case_id', $caseId)
            ->where('status', CaseAnalysis::STATUS_COMPLETED)
            ->get();

        $grouped = [];
        foreach ($caseAnalyses as $analysis) {
            $grouped[$analysis->analysis_type] = $analysis->results;
        }

        return $grouped;
    }

    /**
     * Build comprehensive analysis payload for Claude.
     */
    private function buildAnalysisPayload(
        string $caseId,
        $documents,
        array $documentAnalyses,
        array $caseAnalyses
    ): array {
        // Build documents payload with their analyses
        $documentsPayload = [];
        foreach ($documents as $doc) {
            $docId = (string) $doc->id;
            $docAnalyses = $documentAnalyses[$docId] ?? [];

            $documentsPayload[] = [
                'document_id' => $docId,
                'title' => $doc->title ?? "Document {$docId}",
                'category' => $doc->category ?? 'unknown',
                'created_at' => $doc->created_at?->toDateString(),
                'analyses' => $docAnalyses,
            ];
        }

        // Extract key strategic inputs from case-level analyses
        $contradictions = $caseAnalyses['contradictions'] ?? [];
        $gaps = $caseAnalyses['gaps'] ?? [];
        $metacase = $caseAnalyses['metacase'] ?? [];
        $timeline = $caseAnalyses['timeline'] ?? [];

        return [
            'case_id' => $caseId,
            'documents' => $documentsPayload,
            'case_analyses' => [
                'contradictions' => $contradictions,
                'gaps' => $gaps,
                'metacase' => $metacase,
                'timeline' => $timeline,
            ],
            'jurisdiction' => 'HR',
            'legal_framework' => [
                'primary_law' => 'ZKP (Zakon o kaznenom postupku)',
                'evidence_exclusion' => [
                    'article' => 'čl. 10 ZKP',
                    'description' => 'Nezakoniti dokazi - isključenje iz postupka',
                    'key_grounds' => [
                        'Nezakonita pretraga bez valjanog naloga',
                        'Kršenje prava na branitelja',
                        'Nezakonito ispitivanje',
                        'Manipulacija dokazima',
                        'Kršenje lanca nadzora',
                    ],
                ],
                'search_warrant' => [
                    'articles' => ['čl. 240', 'čl. 241', 'čl. 244 ZKP'],
                    'requirements' => [
                        'Specifični razlozi za pretragu',
                        'Precizna identifikacija prostora/predmeta',
                        'Nalog izdan PRIJE pretrage',
                        'Prisutnost svjedoka',
                    ],
                ],
                'detention' => [
                    'articles' => ['čl. 123', 'čl. 130', 'čl. 131 ZKP'],
                    'review_timelines' => [
                        'initial' => '48 sati',
                        'extended' => 'svaka 2 mjeseca',
                    ],
                ],
                'defense_rights' => [
                    'articles' => ['čl. 64', 'čl. 239 ZKP'],
                    'key_rights' => [
                        'Pravo na branitelja od prvog ispitivanja',
                        'Pravo na uvid u spis',
                        'Pravo na predlaganje dokaza',
                    ],
                ],
            ],
            'strategy_categories' => [
                'evidence_challenges' => 'Napadi na zakonitost dokaza',
                'procedural_defenses' => 'Procesne obrane',
                'credibility_attacks' => 'Napadi na vjerodostojnost',
                'timeline_defenses' => 'Obrane temeljene na kronologiji',
                'alternative_narratives' => 'Alternativne teorije slučaja',
            ],
        ];
    }

    /**
     * Process Claude response and structure strategies.
     */
    private function processClaudeResponse(array $response): array
    {
        $strategies = $response['strategies'] ?? [];
        $priorityActions = $response['priority_actions'] ?? [];
        $riskAssessment = $response['risk_assessment'] ?? [];
        $summary = $response['summary'] ?? '';

        // Categorize strategies by type
        $byCategory = $this->categorizeStrategies($strategies);

        // Calculate overall strength score
        $strengthScore = $this->calculateStrengthScore($strategies, $riskAssessment);

        // Identify strongest arguments
        $strongestArguments = $this->extractStrongestArguments($strategies);

        return [
            'strategies' => $strategies,
            'by_category' => $byCategory,
            'priority_actions' => $priorityActions,
            'risk_assessment' => $riskAssessment,
            'strongest_arguments' => $strongestArguments,
            'overall_strength_score' => $strengthScore,
            'total_strategies' => count($strategies),
            'summary' => $summary,
        ];
    }

    /**
     * Categorize strategies by their type.
     */
    private function categorizeStrategies(array $strategies): array
    {
        $categories = [];
        foreach ($strategies as $strategy) {
            $category = $strategy['category'] ?? 'general';
            if (!isset($categories[$category])) {
                $categories[$category] = [];
            }
            $categories[$category][] = $strategy;
        }
        return $categories;
    }

    /**
     * Calculate overall strength score for the defense.
     */
    private function calculateStrengthScore(array $strategies, array $riskAssessment): float
    {
        if (empty($strategies)) {
            return 0.0;
        }

        // Base score from strategy strengths
        $totalStrength = 0;
        $count = 0;
        foreach ($strategies as $strategy) {
            $strength = $strategy['strength'] ?? $strategy['confidence'] ?? 0.5;
            $totalStrength += $strength;
            $count++;
        }
        $baseScore = $count > 0 ? $totalStrength / $count : 0.5;

        // Adjust for risks
        $riskPenalty = 0;
        foreach ($riskAssessment as $risk) {
            $severity = $risk['severity'] ?? 'medium';
            $riskPenalty += match ($severity) {
                'high' => 0.1,
                'medium' => 0.05,
                'low' => 0.02,
                default => 0.03,
            };
        }

        return max(0, min(1, round($baseScore - $riskPenalty, 2)));
    }

    /**
     * Extract the strongest defense arguments.
     */
    private function extractStrongestArguments(array $strategies): array
    {
        // Filter and sort by strength/confidence
        $sorted = collect($strategies)
            ->filter(fn($s) => isset($s['strength']) || isset($s['confidence']))
            ->sortByDesc(fn($s) => $s['strength'] ?? $s['confidence'] ?? 0)
            ->take(5)
            ->values()
            ->toArray();

        return $sorted;
    }

    /**
     * Build an empty result structure.
     */
    private function buildEmptyResult(int $documentsAnalyzed, float $startTime): array
    {
        return [
            'results' => [
                'strategies' => [],
                'by_category' => [],
                'priority_actions' => [],
                'risk_assessment' => [],
                'strongest_arguments' => [],
                'overall_strength_score' => 0.0,
                'total_strategies' => 0,
                'summary' => '',
            ],
            'metadata' => [
                'documents_analyzed' => $documentsAnalyzed,
                'processing_time_seconds' => round(microtime(true) - $startTime, 4),
            ],
        ];
    }
}

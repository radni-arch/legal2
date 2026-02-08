<?php

namespace App\Services\LegalReasoning;

use App\Exceptions\CitationException;
use App\Models\CitationTimeSeries;
use App\Models\CourtDecision;
use App\Models\DecisionImpactMetric;
use App\Services\GraphDatabaseService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Citation Analyzer
 *
 * Analyzes citation networks and calculates authority scores
 * for court decisions using graph algorithms.
 */
class CitationAnalyzer
{
    public function __construct(
        protected GraphDatabaseService $graphDb
    ) {}

    /**
     * Analyze authority of a court decision
     */
    public function analyzeAuthority(string $decisionId): array
    {
        $correlationId = request()->header('X-Request-ID') ?? Str::uuid()->toString();
        $startTime = microtime(true);

        Log::withContext(['correlation_id' => $correlationId]);

        Log::info('CitationAnalyzer: analyzeAuthority initiated', [
            'decision_id' => $decisionId,
            'user_id' => auth()->id(),
        ]);

        try {
            $decision = CourtDecision::findOrFail($decisionId);

            // Calculate various authority metrics
            $authorityScore = $this->calculateAuthorityScore($decision);
            $citationChain = $this->buildCitationChain($decision);
            $influentialCourts = $this->findInfluentialCourts($decision);
            $temporalDecay = $this->applyTemporalDecay($decision);
            $precedentStrength = $this->assessPrecedentStrength($decision);

            // Get citations summary
            $citationsSummary = $this->getCitationsSummary($decision);

            // Combine into overall analysis
            $analysis = [
                'decision_id' => $decisionId,
                'authority_score' => $authorityScore,
                'precedent_strength' => $precedentStrength,
                'influence_score' => $this->calculateInfluenceScore($authorityScore, $precedentStrength),
                'citation_chain' => $citationChain,
                'influential_courts' => $influentialCourts,
                'temporal_decay_factor' => $temporalDecay,
                'citations_summary' => $citationsSummary,
            ];

            // Add impact summary to citations_summary
            $analysis['citations_summary']['impact_summary'] = $this->generateImpactSummary($decision, $analysis);

            // Persist to database
            $this->persistImpactMetrics($decision, $analysis);

            $duration = (microtime(true) - $startTime) * 1000;

            Log::info('CitationAnalyzer: analyzeAuthority completed', [
                'decision_id' => $decisionId,
                'authority_score' => $authorityScore,
                'influence_score' => $analysis['influence_score'],
                'citation_chain_length' => count($citationChain),
                'duration_ms' => round($duration, 2),
            ]);

            return $analysis;

        } catch (CitationException $e) {
            Log::error('CitationAnalyzer: analyzeAuthority failed with CitationException', [
                'decision_id' => $decisionId,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('CitationAnalyzer: Decision not found', [
                'decision_id' => $decisionId,
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
            ]);

            throw new CitationException(
                "Decision not found: {$decisionId}",
                CitationException::DECISION_NOT_FOUND,
                $e
            );

        } catch (\Exception $e) {
            Log::error('CitationAnalyzer: analyzeAuthority failed with unexpected exception', [
                'decision_id' => $decisionId,
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new CitationException(
                'Citation authority analysis failed: '.$e->getMessage(),
                CitationException::ANALYSIS_FAILED,
                $e
            );
        }
    }

    /**
     * Calculate authority score using citation count and court hierarchy
     */
    protected function calculateAuthorityScore(CourtDecision $decision): float
    {
        // Count how many other decisions cite this one
        $citationCount = $this->getInboundCitationCount($decision);

        // Base score from citation count (logarithmic scale)
        $baseScore = $citationCount > 0 ? min(log($citationCount + 1) / log(100), 0.5) : 0.0;

        // Court hierarchy bonus
        $courtBonus = $this->getCourtHierarchyBonus($decision->court);

        // Finality bonus (final decisions are more authoritative)
        $finalityBonus = $this->getFinalityBonus($decision->finality);

        // ECLI bonus (European Case Law Identifier)
        $ecliBonus = ! empty($decision->ecli) ? 0.05 : 0.0;

        $totalScore = min(1.0, $baseScore + $courtBonus + $finalityBonus + $ecliBonus);

        return round($totalScore, 3);
    }

    /**
     * Get count of decisions citing this one
     */
    protected function getInboundCitationCount(CourtDecision $decision): int
    {
        // Count references in other decisions' content
        $count = CourtDecision::where('id', '!=', $decision->id)
            ->whereHas('documents', function ($query) use ($decision) {
                $caseNumber = $decision->case_number;
                $query->where('content', 'LIKE', "%{$caseNumber}%");
            })
            ->count();

        return $count;
    }

    /**
     * Build citation chain showing who cites this decision
     */
    protected function buildCitationChain(CourtDecision $decision): array
    {
        $citingDecisions = CourtDecision::where('id', '!=', $decision->id)
            ->whereHas('documents', function ($query) use ($decision) {
                $caseNumber = $decision->case_number;
                $query->where('content', 'LIKE', "%{$caseNumber}%");
            })
            ->orderBy('decision_date', 'desc')
            ->limit(20)
            ->get();

        return $citingDecisions->map(function ($citing) {
            return [
                'id' => $citing->id,
                'case_number' => $citing->case_number,
                'title' => $citing->title,
                'court' => $citing->court,
                'decision_date' => $citing->decision_date?->toDateString(),
            ];
        })->toArray();
    }

    /**
     * Find which courts are citing this decision
     */
    protected function findInfluentialCourts(CourtDecision $decision): array
    {
        $citingDecisions = CourtDecision::where('id', '!=', $decision->id)
            ->whereHas('documents', function ($query) use ($decision) {
                $caseNumber = $decision->case_number;
                $query->where('content', 'LIKE', "%{$caseNumber}%");
            })
            ->get();

        $courtCounts = [];
        foreach ($citingDecisions as $citing) {
            $court = $citing->court ?? 'Unknown';
            $courtCounts[$court] = ($courtCounts[$court] ?? 0) + 1;
        }

        // Sort by count
        arsort($courtCounts);

        $courts = [];
        foreach ($courtCounts as $court => $count) {
            $courts[] = [
                'court' => $court,
                'citation_count' => $count,
                'hierarchy_level' => $this->getCourtHierarchyLevel($court),
            ];
        }

        return $courts;
    }

    /**
     * Apply temporal decay - older decisions have less immediate relevance
     */
    protected function applyTemporalDecay(CourtDecision $decision): float
    {
        if (! $decision->decision_date) {
            return 1.0;
        }

        // Calculate years since the decision (absolute value to handle dates in past/future correctly)
        $yearsOld = abs($decision->decision_date->diffInYears(now()));

        // Exponential decay: 0.95^years
        // After 10 years: ~0.6, after 20 years: ~0.36
        $decayFactor = pow(0.95, $yearsOld);

        return round($decayFactor, 3);
    }

    /**
     * Assess how strong this decision is as a precedent
     */
    protected function assessPrecedentStrength(CourtDecision $decision): float
    {
        $strength = 0.5; // Base strength

        // Higher court = stronger precedent
        $courtLevel = $this->getCourtHierarchyLevel($decision->court);
        $strength += ($courtLevel / 10);

        // Final decisions are stronger
        if (in_array(strtolower($decision->finality ?? ''), ['final', 'konačna', 'pravnomoćna'])) {
            $strength += 0.2;
        }

        // More citations = stronger
        $citationCount = $this->getInboundCitationCount($decision);
        if ($citationCount > 10) {
            $strength += 0.1;
        } elseif ($citationCount > 5) {
            $strength += 0.05;
        }

        // Recent decisions may be less tested
        if ($decision->decision_date && $decision->decision_date->gt(now()->subYears(2))) {
            $strength -= 0.1;
        }

        return round(min(1.0, max(0.0, $strength)), 3);
    }

    /**
     * Calculate overall influence score
     */
    protected function calculateInfluenceScore(float $authorityScore, float $precedentStrength): float
    {
        // Weighted average: 60% authority, 40% precedent strength
        $influenceScore = ($authorityScore * 0.6) + ($precedentStrength * 0.4);

        return round($influenceScore, 3);
    }

    /**
     * Get citations summary statistics
     */
    protected function getCitationsSummary(CourtDecision $decision): array
    {
        $citingDecisions = CourtDecision::where('id', '!=', $decision->id)
            ->whereHas('documents', function ($query) use ($decision) {
                $caseNumber = $decision->case_number;
                $query->where('content', 'LIKE', "%{$caseNumber}%");
            })
            ->get();

        $totalCitations = $citingDecisions->count();

        // Categorize by court level
        $higherCourt = 0;
        $sameCourt = 0;
        $lowerCourt = 0;

        $decisionLevel = $this->getCourtHierarchyLevel($decision->court);

        foreach ($citingDecisions as $citing) {
            $citingLevel = $this->getCourtHierarchyLevel($citing->court);

            if ($citingLevel > $decisionLevel) {
                $higherCourt++;
            } elseif ($citingLevel == $decisionLevel) {
                $sameCourt++;
            } else {
                $lowerCourt++;
            }
        }

        // Track over time
        $recentCitations = $citingDecisions->filter(function ($citing) {
            return $citing->decision_date && $citing->decision_date->gt(now()->subYear());
        })->count();

        return [
            'total_citations' => $totalCitations,
            'direct_citations' => $totalCitations, // In this simple implementation, all are direct
            'indirect_citations' => 0,
            'higher_court_citations' => $higherCourt,
            'same_court_citations' => $sameCourt,
            'lower_court_citations' => $lowerCourt,
            'recent_citations_1y' => $recentCitations,
            'citation_velocity' => round($recentCitations / 12, 2), // per month
        ];
    }

    /**
     * Persist impact metrics to database
     */
    protected function persistImpactMetrics(CourtDecision $decision, array $analysis): void
    {
        $summary = $analysis['citations_summary'];
        $courts = $analysis['influential_courts'];

        DecisionImpactMetric::updateOrCreate(
            ['decision_id' => $decision->id],
            [
                'id' => Str::ulid(),
                'citation_count' => $summary['total_citations'],
                'direct_citations' => $summary['direct_citations'],
                'indirect_citations' => $summary['indirect_citations'],
                'authority_score' => $analysis['authority_score'],
                'precedent_strength' => $analysis['precedent_strength'],
                'influence_score' => $analysis['influence_score'],
                'citations_over_time' => $this->buildCitationTimeSeries($decision->id),
                'citation_velocity' => $summary['citation_velocity'],
                'temporal_decay_factor' => $analysis['temporal_decay_factor'],
                'jurisdictional_spread' => $this->getJurisdictionalSpread($analysis['citation_chain']),
                'jurisdictions_count' => $this->countJurisdictions($analysis['citation_chain']),
                'citing_courts' => array_column($courts, 'court'),
                'higher_court_citations' => $summary['higher_court_citations'],
                'same_court_citations' => $summary['same_court_citations'],
                'lower_court_citations' => $summary['lower_court_citations'],
                'influential_cases' => array_slice($analysis['citation_chain'], 0, 10),
                'impact_summary' => $this->generateImpactSummary($decision, $analysis),
                'last_calculated_at' => now(),
                'last_citation_at' => $this->getLastCitationDate($analysis['citation_chain']),
            ]
        );

        // Persist time series data
        $this->persistCitationTimeSeries($decision, $analysis);

        Log::info('CitationAnalyzer - Metrics persisted', ['decision_id' => $decision->id]);
    }

    /**
     * Persist citation time series data
     *
     * Tracks citation metrics over time (monthly).
     */
    protected function persistCitationTimeSeries(CourtDecision $decision, array $analysis): void
    {
        $now = now();
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();

        $summary = $analysis['citations_summary'];
        $courts = $analysis['influential_courts'];
        $citationChain = $analysis['citation_chain'];

        // Get citing decisions for this month
        $citingCourts = array_unique(array_column($courts, 'court'));
        $topCitingDecisions = array_slice(array_column($citationChain, 'id'), 0, 5);

        // Calculate average citation importance
        $avgImportance = $summary['total_citations'] > 0
            ? round($analysis['authority_score'], 3)
            : 0.0;

        CitationTimeSeries::updateOrCreate(
            [
                'decision_id' => $decision->id,
                'period_start' => $monthStart,
                'period_type' => 'monthly',
            ],
            [
                'period_end' => $monthEnd,
                'citation_count' => $summary['total_citations'],
                'incoming_citations' => $summary['direct_citations'],
                'outgoing_citations' => $summary['indirect_citations'],
                'avg_citation_importance' => $avgImportance,
                'citing_courts' => $citingCourts,
                'top_citing_decisions' => $topCitingDecisions,
            ]
        );

        Log::debug('CitationAnalyzer - Time series persisted', [
            'decision_id' => $decision->id,
            'period_start' => $monthStart->toDateString(),
            'citation_count' => $summary['total_citations'],
        ]);
    }

    /**
     * Build citation time series data for a decision
     *
     * Retrieves and formats time series data from the citation_time_series table.
     * Returns aggregated citation counts over different time periods (daily, weekly, monthly, yearly).
     *
     * @param  string  $decisionId  The decision to get time series for
     * @return array Array of time series records with trend information
     */
    protected function buildCitationTimeSeries(string $decisionId): array
    {
        $timeSeries = [];

        // Define period types and their limits (how many recent periods to include)
        $periodConfigs = [
            'daily' => 30,      // Last 30 days
            'weekly' => 12,     // Last 12 weeks
            'monthly' => 12,    // Last 12 months
            'yearly' => 5,      // Last 5 years
        ];

        foreach ($periodConfigs as $periodType => $limit) {
            $records = CitationTimeSeries::where('decision_id', $decisionId)
                ->where('period_type', $periodType)
                ->orderByDesc('period_start')
                ->limit($limit)
                ->get();

            foreach ($records as $record) {
                $timeSeries[] = [
                    'period_type' => $record->period_type,
                    'period_start' => $record->period_start->toDateString(),
                    'period_end' => $record->period_end->toDateString(),
                    'citation_count' => $record->citation_count,
                    'incoming_citations' => $record->incoming_citations,
                    'outgoing_citations' => $record->outgoing_citations,
                    'avg_citation_importance' => (float) $record->avg_citation_importance,
                    'citing_courts' => $record->citing_courts ?? [],
                    'top_citing_decisions' => $record->top_citing_decisions ?? [],
                    'trend' => $record->trend, // Uses the model's getTrendAttribute()
                ];
            }
        }

        // Sort by period_start descending (most recent first)
        usort($timeSeries, function ($a, $b) {
            return strcmp($b['period_start'], $a['period_start']);
        });

        Log::debug('CitationAnalyzer - Time series built', [
            'decision_id' => $decisionId,
            'records_count' => count($timeSeries),
        ]);

        return $timeSeries;
    }

    /**
     * Get court hierarchy level (1-5, higher = more authoritative)
     */
    protected function getCourtHierarchyLevel(string $court): int
    {
        $court = mb_strtolower($court, 'UTF-8');

        // Supreme/Constitutional courts
        if (str_contains($court, 'vrhovni') || str_contains($court, 'ustavni') || str_contains($court, 'supreme')) {
            return 5;
        }

        // High/Appellate courts
        if (str_contains($court, 'visoki') || str_contains($court, 'žalbeni') || str_contains($court, 'appellate')) {
            return 4;
        }

        // County courts
        if (str_contains($court, 'županijski') || str_contains($court, 'county')) {
            return 3;
        }

        // Municipal courts
        if (str_contains($court, 'općinski') || str_contains($court, 'municipal')) {
            return 2;
        }

        // Default
        return 1;
    }

    /**
     * Get court hierarchy bonus for authority score
     */
    protected function getCourtHierarchyBonus(string $court): float
    {
        $level = $this->getCourtHierarchyLevel($court);

        return match ($level) {
            5 => 0.25, // Supreme/Constitutional
            4 => 0.15, // High/Appellate
            3 => 0.10, // County
            2 => 0.05, // Municipal
            default => 0.0,
        };
    }

    /**
     * Get finality bonus
     */
    protected function getFinalityBonus(?string $finality): float
    {
        if (! $finality) {
            return 0.0;
        }

        $finality = strtolower($finality);

        if (in_array($finality, ['final', 'konačna', 'pravnomoćna'])) {
            return 0.20;
        }

        return 0.0;
    }

    /**
     * Get jurisdictional spread from citations
     */
    protected function getJurisdictionalSpread(array $citationChain): array
    {
        $jurisdictions = [];

        foreach ($citationChain as $citation) {
            // Extract jurisdiction from court name or use a default
            $jurisdiction = 'HR'; // Default to Croatian jurisdiction
            $jurisdictions[$jurisdiction] = ($jurisdictions[$jurisdiction] ?? 0) + 1;
        }

        return $jurisdictions;
    }

    /**
     * Count unique jurisdictions
     */
    protected function countJurisdictions(array $citationChain): int
    {
        $spread = $this->getJurisdictionalSpread($citationChain);

        return count($spread);
    }

    /**
     * Generate impact summary text
     */
    protected function generateImpactSummary(CourtDecision $decision, array $analysis): string
    {
        $citationCount = $analysis['citations_summary']['total_citations'];
        $authorityScore = $analysis['authority_score'];

        if ($citationCount === 0) {
            return 'This decision has not been cited by other court decisions yet.';
        }

        $level = $authorityScore > 0.7 ? 'high' : ($authorityScore > 0.4 ? 'moderate' : 'low');

        return sprintf(
            'This decision from %s has been cited %d times by other courts, indicating %s authority. '.
            'The precedent strength score of %.2f reflects its significance in the legal system.',
            $decision->court ?? 'the court',
            $citationCount,
            $level,
            $analysis['precedent_strength']
        );
    }

    /**
     * Get last citation date
     */
    protected function getLastCitationDate(array $citationChain): ?\DateTime
    {
        if (empty($citationChain)) {
            return null;
        }

        $dates = array_filter(array_column($citationChain, 'decision_date'));

        if (empty($dates)) {
            return null;
        }

        rsort($dates);

        return new \DateTime($dates[0]);
    }
}

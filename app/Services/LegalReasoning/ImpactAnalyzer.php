<?php

namespace App\Services\LegalReasoning;

use App\Exceptions\AnalysisException;
use App\Models\CourtDecision;
use App\Models\DecisionImpactMetric;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ImpactAnalyzer
{
    public function __construct(
        protected CitationAnalyzer $citationAnalyzer
    ) {}

    /**
     * Comprehensive decision impact analysis
     */
    public function analyzeDecisionImpact(string $decisionId): array
    {
        $correlationId = request()->header('X-Request-ID') ?? Str::uuid()->toString();
        $startTime = microtime(true);

        Log::withContext(['correlation_id' => $correlationId]);

        Log::info('ImpactAnalyzer: analyzeDecisionImpact initiated', [
            'decision_id' => $decisionId,
            'user_id' => auth()->id(),
        ]);

        try {
            $decision = CourtDecision::findOrFail($decisionId);

            // Get citation network data
            $citationData = $this->getCitationNetworkData($decisionId);

            // Track citations over time
            $timeSeriesData = $this->trackCitationsOverTime($decisionId, $decision);

            // Analyze geographic spread
            $spreadAnalysis = $this->analyzeJurisdictionalSpread($decisionId);

            // Find influential followers
            $keyFollowers = $this->findKeyFollowers($decisionId);

            // Calculate precedent strength
            $precedentStrength = $this->assessPrecedentStrength($decision, $citationData);

            // Calculate overall impact score
            $impactScore = $this->calculateImpactScore([
                'citation_count' => $citationData['citation_count'],
                'temporal_trend' => $timeSeriesData['trend'],
                'jurisdictional_reach' => $spreadAnalysis['reach_score'],
                'key_followers_count' => count($keyFollowers),
                'precedent_strength' => $precedentStrength,
            ]);

            $result = [
                'decision_id' => $decisionId,
                'decision_date' => $decision->decision_date,
                'impact_score' => round($impactScore, 3),
                'precedent_strength' => round($precedentStrength, 3),
                // Top-level keys for backward compatibility
                'citation_count' => $citationData['citation_count'],
                'citation_velocity' => round($timeSeriesData['velocity'], 2),
                'trend' => $timeSeriesData['trend'],
                // Detailed breakdown
                'citation_summary' => [
                    'total_citations' => $citationData['citation_count'],
                    'direct_citations' => $citationData['direct_count'],
                    'indirect_citations' => $citationData['indirect_count'],
                    'citation_velocity' => round($timeSeriesData['velocity'], 2),
                ],
                'citations_over_time' => $timeSeriesData['time_series'],
                'temporal_trend' => $timeSeriesData['trend'],
                'jurisdictional_spread' => $spreadAnalysis,
                'key_followers' => $keyFollowers,
                'influential_metrics' => [
                    'higher_court_citations' => $citationData['higher_court_count'],
                    'same_court_citations' => $citationData['same_court_count'],
                    'lower_court_citations' => $citationData['lower_court_count'],
                    'cross_jurisdiction_citations' => $spreadAnalysis['cross_jurisdiction_count'],
                ],
            ];

            // Persist impact metrics to database
            $this->persistImpactMetrics($decisionId, $result);

            $duration = (microtime(true) - $startTime) * 1000;

            Log::info('ImpactAnalyzer: analyzeDecisionImpact completed', [
                'decision_id' => $decisionId,
                'impact_score' => $impactScore,
                'precedent_strength' => $precedentStrength,
                'citation_count' => $citationData['citation_count'],
                'duration_ms' => round($duration, 2),
            ]);

            return $result;

        } catch (AnalysisException $e) {
            Log::error('ImpactAnalyzer: analyzeDecisionImpact failed with AnalysisException', [
                'decision_id' => $decisionId,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('ImpactAnalyzer: Decision not found', [
                'decision_id' => $decisionId,
                'error' => $e->getMessage(),
            ]);

            throw new AnalysisException(
                "Decision not found: {$decisionId}",
                AnalysisException::RESOURCE_NOT_FOUND,
                $e
            );

        } catch (\Exception $e) {
            Log::error('ImpactAnalyzer: analyzeDecisionImpact failed with unexpected exception', [
                'decision_id' => $decisionId,
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new AnalysisException(
                'Impact analysis failed: '.$e->getMessage(),
                AnalysisException::ANALYSIS_FAILED,
                $e
            );
        }
    }

    /**
     * Get citation network data for the decision
     */
    protected function getCitationNetworkData(string $decisionId): array
    {
        // Query all citations (both direct and indirect)
        $citations = DB::table('citation_relationships as cr')
            ->join('court_decisions as cd', 'cr.citing_decision_id', '=', 'cd.id')
            ->where('cr.cited_decision_id', $decisionId)
            ->select('cd.*', 'cr.citation_type', 'cr.context')
            ->get();

        $directCount = $citations->where('citation_type', 'direct')->count();
        $indirectCount = $citations->where('citation_type', 'indirect')->count();

        // Analyze court hierarchy of citing decisions
        $higherCourtCount = 0;
        $sameCourtCount = 0;
        $lowerCourtCount = 0;

        $decision = CourtDecision::findOrFail($decisionId);
        $decisionCourtLevel = $this->getCourtLevel($decision->court);

        foreach ($citations as $citation) {
            $citingCourtLevel = $this->getCourtLevel($citation->court);
            if ($citingCourtLevel > $decisionCourtLevel) {
                $higherCourtCount++;
            } elseif ($citingCourtLevel === $decisionCourtLevel) {
                $sameCourtCount++;
            } else {
                $lowerCourtCount++;
            }
        }

        return [
            'citation_count' => $citations->count(),
            'direct_count' => $directCount,
            'indirect_count' => $indirectCount,
            'higher_court_count' => $higherCourtCount,
            'same_court_count' => $sameCourtCount,
            'lower_court_count' => $lowerCourtCount,
            'citing_decisions' => $citations,
        ];
    }

    /**
     * Track citations over time to identify trends
     */
    protected function trackCitationsOverTime(string $decisionId, CourtDecision $decision): array
    {
        // Group citations by year
        $citationsByYear = DB::table('citation_relationships as cr')
            ->join('court_decisions as cd', 'cr.citing_decision_id', '=', 'cd.id')
            ->where('cr.cited_decision_id', $decisionId)
            ->whereNotNull('cd.decision_date')
            ->select(
                DB::raw('EXTRACT(YEAR FROM cd.decision_date) as year'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('year')
            ->orderBy('year')
            ->get();

        $timeSeries = $citationsByYear->map(function ($row) {
            return [
                'year' => (int) $row->year,
                'citations' => (int) $row->count,
            ];
        })->toArray();

        // Calculate trend (increasing, stable, decreasing)
        $trend = $this->calculateTrend($timeSeries);

        // Calculate citation velocity (citations per year)
        $decisionYear = $decision->decision_date ? $decision->decision_date->year : now()->year;
        $yearsSinceDecision = now()->year - $decisionYear;
        $totalCitations = array_sum(array_column($timeSeries, 'citations'));
        $velocity = $yearsSinceDecision > 0 ? $totalCitations / $yearsSinceDecision : $totalCitations;

        return [
            'time_series' => $timeSeries,
            'trend' => $trend,
            'velocity' => $velocity,
            'years_active' => $yearsSinceDecision,
            'total_citations' => $totalCitations,
        ];
    }

    /**
     * Analyze geographic/jurisdictional spread of citations
     */
    protected function analyzeJurisdictionalSpread(string $decisionId): array
    {
        $citationsByJurisdiction = DB::table('citation_relationships as cr')
            ->join('court_decisions as cd', 'cr.citing_decision_id', '=', 'cd.id')
            ->where('cr.cited_decision_id', $decisionId)
            ->whereNotNull('cd.jurisdiction')
            ->select(
                'cd.jurisdiction',
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('cd.jurisdiction')
            ->orderByDesc('count')
            ->get();

        $decision = CourtDecision::findOrFail($decisionId);
        $originJurisdiction = $decision->jurisdiction;

        $crossJurisdictionCount = $citationsByJurisdiction
            ->where('jurisdiction', '!=', $originJurisdiction)
            ->sum('count');

        // Calculate reach score (0-1 based on geographic diversity)
        $uniqueJurisdictions = $citationsByJurisdiction->count();
        $reachScore = min($uniqueJurisdictions / 10, 1.0); // Max at 10 jurisdictions

        return [
            'origin_jurisdiction' => $originJurisdiction,
            'unique_jurisdictions' => $uniqueJurisdictions,
            'cross_jurisdiction_count' => $crossJurisdictionCount,
            'reach_score' => round($reachScore, 2),
            'jurisdiction_breakdown' => $citationsByJurisdiction->map(function ($row) {
                return [
                    'jurisdiction' => $row->jurisdiction,
                    'citation_count' => (int) $row->count,
                ];
            })->toArray(),
        ];
    }

    /**
     * Find most influential cases that cited this decision
     */
    protected function findKeyFollowers(string $decisionId, int $limit = 10): array
    {
        // Find citing decisions with highest authority scores
        $keyFollowers = DB::table('citation_relationships as cr')
            ->join('court_decisions as cd', 'cr.citing_decision_id', '=', 'cd.id')
            ->leftJoin('decision_impact_metrics as dim', 'cd.id', '=', 'dim.decision_id')
            ->where('cr.cited_decision_id', $decisionId)
            ->select(
                'cd.id',
                'cd.title as case_title',
                'cd.court',
                'cd.decision_date',
                'cd.ecli',
                'dim.authority_score',
                'dim.citation_count',
                'cr.citation_type',
                'cr.context'
            )
            ->orderByDesc('dim.authority_score')
            ->orderByDesc('dim.citation_count')
            ->limit($limit)
            ->get();

        return $keyFollowers->map(function ($follower) {
            return [
                'decision_id' => $follower->id,
                'case_title' => $follower->case_title,
                'court' => $follower->court,
                'decision_date' => $follower->decision_date,
                'ecli' => $follower->ecli,
                'authority_score' => $follower->authority_score ? round($follower->authority_score, 3) : null,
                'citation_count' => $follower->citation_count ?? 0,
                'citation_type' => $follower->citation_type,
                'citation_context' => $follower->context,
            ];
        })->toArray();
    }

    /**
     * Assess overall precedent strength of the decision
     */
    protected function assessPrecedentStrength(CourtDecision $decision, array $citationData): float
    {
        $strength = 0.0;

        // Factor 1: Citation volume (logarithmic scale, max 0.3)
        $citationCount = $citationData['citation_count'];
        $citationScore = $citationCount > 0 ? min(log($citationCount + 1) / log(100), 0.3) : 0.0;
        $strength += $citationScore;

        // Factor 2: Court authority (max 0.25)
        $courtLevel = $this->getCourtLevel($decision->court);
        $courtScore = ($courtLevel / 5) * 0.25; // 5 levels, max 0.25
        $strength += $courtScore;

        // Factor 3: Higher court citations (max 0.2)
        $higherCourtRatio = $citationCount > 0 ? $citationData['higher_court_count'] / $citationCount : 0;
        $strength += ($higherCourtRatio * 0.2);

        // Factor 4: Direct citations preference (max 0.15)
        $directRatio = $citationCount > 0 ? $citationData['direct_count'] / $citationCount : 0;
        $strength += ($directRatio * 0.15);

        // Factor 5: Finality/bindingness (max 0.1)
        if ($decision->finality === 'final' || $decision->finality === 'binding') {
            $strength += 0.1;
        } elseif ($decision->finality === 'appealable') {
            $strength += 0.05;
        }

        return min($strength, 1.0);
    }

    /**
     * Calculate overall impact score
     */
    protected function calculateImpactScore(array $factors): float
    {
        $score = 0.0;

        // Citation count component (max 0.3)
        $citationCount = $factors['citation_count'] ?? 0;
        $score += min(log($citationCount + 1) / log(100), 0.3);

        // Temporal trend component (max 0.2)
        $trendScore = match ($factors['temporal_trend'] ?? 'stable') {
            'increasing' => 0.2,
            'stable' => 0.15,
            'decreasing' => 0.1,
            default => 0.15,
        };
        $score += $trendScore;

        // Jurisdictional reach component (max 0.2)
        $reachScore = ($factors['jurisdictional_reach'] ?? 0) * 0.2;
        $score += $reachScore;

        // Key followers component (max 0.15)
        $followersCount = $factors['key_followers_count'] ?? 0;
        $score += min($followersCount / 10, 0.15);

        // Precedent strength component (max 0.15)
        $precedentScore = ($factors['precedent_strength'] ?? 0) * 0.15;
        $score += $precedentScore;

        return min($score, 1.0);
    }

    /**
     * Persist impact metrics to database
     */
    protected function persistImpactMetrics(string $decisionId, array $analysis): void
    {
        $citingSummary = $analysis['citation_summary'];
        $influential = $analysis['influential_metrics'];

        DecisionImpactMetric::updateOrCreate(
            ['decision_id' => $decisionId],
            [
                'id' => Str::ulid(),
                'citation_count' => $citingSummary['total_citations'],
                'direct_citations' => $citingSummary['direct_citations'],
                'indirect_citations' => $citingSummary['indirect_citations'],
                'authority_score' => $this->getAuthorityScore($decisionId),
                'precedent_strength' => $analysis['precedent_strength'],
                'influence_score' => $analysis['impact_score'],
                'citations_over_time' => $analysis['citations_over_time'],
                'citation_velocity' => $citingSummary['citation_velocity'],
                'temporal_decay_factor' => $this->calculateTemporalDecay($decisionId),
                'jurisdictional_spread' => $analysis['jurisdictional_spread'],
                'jurisdictions_count' => $analysis['jurisdictional_spread']['unique_jurisdictions'],
                'citing_courts' => $this->getCitingCourts($decisionId),
                'higher_court_citations' => $influential['higher_court_citations'],
                'same_court_citations' => $influential['same_court_citations'],
                'lower_court_citations' => $influential['lower_court_citations'],
                'influential_cases' => $analysis['key_followers'],
                'impact_summary' => $this->generateImpactSummary($analysis),
                'last_calculated_at' => now(),
                'last_citation_at' => $this->getLastCitationDate($decisionId),
            ]
        );

        Log::info('ImpactAnalyzer - Metrics persisted', ['decision_id' => $decisionId]);
    }

    /**
     * Calculate trend from time series data
     */
    protected function calculateTrend(array $timeSeries): string
    {
        if (count($timeSeries) < 2) {
            return 'stable';
        }

        // Compare recent half vs older half
        $midpoint = (int) floor(count($timeSeries) / 2);
        $older = array_slice($timeSeries, 0, $midpoint);
        $recent = array_slice($timeSeries, $midpoint);

        $olderAvg = array_sum(array_column($older, 'citations')) / count($older);
        $recentAvg = array_sum(array_column($recent, 'citations')) / count($recent);

        $change = ($recentAvg - $olderAvg) / max($olderAvg, 1);

        if ($change > 0.2) {
            return 'increasing';
        } elseif ($change < -0.2) {
            return 'decreasing';
        }

        return 'stable';
    }

    /**
     * Get court hierarchy level (1-5)
     */
    protected function getCourtLevel(string $court): int
    {
        $court = strtolower($court);

        if (str_contains($court, 'supreme') || str_contains($court, 'constitutional')) {
            return 5;
        } elseif (str_contains($court, 'appeal') || str_contains($court, 'appellate')) {
            return 4;
        } elseif (str_contains($court, 'high') || str_contains($court, 'regional')) {
            return 3;
        } elseif (str_contains($court, 'district') || str_contains($court, 'county')) {
            return 2;
        }

        return 1; // Municipal/local courts
    }

    /**
     * Get authority score from CitationAnalyzer or metrics table
     */
    protected function getAuthorityScore(string $decisionId): float
    {
        $metric = DecisionImpactMetric::where('decision_id', $decisionId)->first();

        return $metric?->authority_score ?? 0.0;
    }

    /**
     * Calculate temporal decay factor
     */
    protected function calculateTemporalDecay(string $decisionId): float
    {
        $decision = CourtDecision::findOrFail($decisionId);
        if (! $decision->decision_date) {
            return 1.0;
        }

        $yearsOld = now()->diffInYears($decision->decision_date);

        return pow(0.95, $yearsOld);
    }

    /**
     * Get list of courts that cited this decision
     */
    protected function getCitingCourts(string $decisionId): array
    {
        return DB::table('citation_relationships as cr')
            ->join('court_decisions as cd', 'cr.citing_decision_id', '=', 'cd.id')
            ->where('cr.cited_decision_id', $decisionId)
            ->whereNotNull('cd.court')
            ->select('cd.court', DB::raw('COUNT(*) as count'))
            ->groupBy('cd.court')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row) => ['court' => $row->court, 'count' => $row->count])
            ->toArray();
    }

    /**
     * Get date of most recent citation
     */
    protected function getLastCitationDate(string $decisionId): ?string
    {
        $result = DB::table('citation_relationships as cr')
            ->join('court_decisions as cd', 'cr.citing_decision_id', '=', 'cd.id')
            ->where('cr.cited_decision_id', $decisionId)
            ->whereNotNull('cd.decision_date')
            ->orderByDesc('cd.decision_date')
            ->value('decision_date');

        return $result;
    }

    /**
     * Generate human-readable impact summary
     */
    protected function generateImpactSummary(array $analysis): string
    {
        $citations = $analysis['citation_summary']['total_citations'];
        $trend = $analysis['temporal_trend'];
        $jurisdictions = $analysis['jurisdictional_spread']['unique_jurisdictions'];
        $impactScore = $analysis['impact_score'];

        $impactLevel = match (true) {
            $impactScore >= 0.7 => 'High',
            $impactScore >= 0.4 => 'Moderate',
            default => 'Limited',
        };

        return sprintf(
            '%s impact decision with %d citations across %d jurisdiction(s). Citation trend: %s.',
            $impactLevel,
            $citations,
            $jurisdictions,
            $trend
        );
    }
}

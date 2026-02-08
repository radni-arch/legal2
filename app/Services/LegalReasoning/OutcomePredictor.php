<?php

namespace App\Services\LegalReasoning;

use App\Models\CasePrediction;
use App\Models\LegalCase;
use App\Services\CaseVectorStoreService;
use App\Services\OpenAIService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OutcomePredictor
{
    public function __construct(
        protected OpenAIService $openAI,
        protected CaseVectorStoreService $vectorStore,
        protected FeatureExtractor $featureExtractor
    ) {}

    /**
     * Predict case outcome based on similar historical cases
     */
    public function predictOutcome(string $caseId): array
    {
        $case = LegalCase::with('documents')->findOrFail($caseId);

        Log::info('OutcomePredictor - Starting prediction', [
            'case_id' => $caseId,
            'case_number' => $case->case_number,
        ]);

        // Extract features from the case
        $features = $this->featureExtractor->extractCaseFeatures($case);

        // Find similar historical cases
        $similarCases = $this->findSimilarCases($case, $features, limit: 50);

        // Analyze outcomes of similar cases
        $outcomes = $this->analyzeSimilarOutcomes($similarCases);

        // Use LLM for nuanced reasoning
        $llmAnalysis = $this->llmOutcomeAnalysis($features, $similarCases);

        // Identify key factors influencing the prediction
        $keyFactors = $this->identifyKeyFactors($features, $similarCases);

        $predictionData = [
            'predicted_outcome' => $outcomes['most_likely'],
            'confidence' => $outcomes['confidence'],
            'probability_distribution' => [
                'favorable' => $outcomes['favorable_pct'],
                'unfavorable' => $outcomes['unfavorable_pct'],
                'settled' => $outcomes['settled_pct'],
                'dismissed' => $outcomes['dismissed_pct'],
            ],
            'similar_cases' => $similarCases->take(10)->toArray(),
            'similar_cases_count' => $similarCases->count(),
            'reasoning' => $llmAnalysis,
            'key_factors' => $keyFactors,
            'features' => $features,
        ];

        // Persist prediction to database
        try {
            CasePrediction::create([
                'id' => (string) \Illuminate\Support\Str::ulid(),
                'case_id' => $caseId,
                'prediction_type' => 'outcome',
                'features' => $features,
                'prediction' => [
                    'outcome' => $outcomes['most_likely'],
                    'probability_distribution' => $predictionData['probability_distribution'],
                ],
                'confidence' => $outcomes['confidence'],
                'model_version' => '1.0',
                'similar_cases' => $similarCases->take(10)->map(fn ($c) => [
                    'id' => $c->id,
                    'case_number' => $c->case_number,
                    'similarity' => $c->similarity ?? 0,
                ])->toArray(),
                'reasoning' => $llmAnalysis,
                'predicted_at' => now(),
            ]);

            Log::info('OutcomePredictor - Prediction saved', [
                'case_id' => $caseId,
                'predicted_outcome' => $outcomes['most_likely'],
                'confidence' => $outcomes['confidence'],
            ]);
        } catch (\Exception $e) {
            Log::error('OutcomePredictor - Failed to save prediction', [
                'case_id' => $caseId,
                'error' => $e->getMessage(),
            ]);
        }

        return $predictionData;
    }

    /**
     * Find similar cases using vector similarity
     */
    public function findSimilarCases(LegalCase $case, array $features, int $limit = 50): Collection
    {
        if (! isset($features['embedding']) || empty($features['embedding'])) {
            Log::warning('OutcomePredictor - No embedding available', ['case_id' => $case->id]);

            return collect();
        }

        $embedding = $features['embedding'];
        $threshold = 0.7;

        // Check if pgvector is available
        $hasPgVector = $this->checkPgVectorAvailability();

        Log::debug('OutcomePredictor - Vector search mode', [
            'has_pgvector' => $hasPgVector,
            'case_id' => $case->id,
        ]);

        if ($hasPgVector) {
            return $this->findSimilarCasesWithPgVector($case, $embedding, $threshold, $limit);
        } else {
            return $this->findSimilarCasesWithFallback($case, $embedding, $threshold, $limit);
        }
    }

    /**
     * Find similar cases using pgvector extension
     */
    protected function findSimilarCasesWithPgVector(LegalCase $case, array $embedding, float $threshold, int $limit): Collection
    {
        try {
            $vectorLiteral = $this->toPgVectorLiteral($embedding);

            $similar = DB::table('case_features as cf')
                ->join('cases as c', 'cf.case_id', '=', 'c.id')
                ->select([
                    'c.id',
                    'c.case_number',
                    'c.title',
                    'c.status',
                    'c.court',
                    'c.jurisdiction',
                    'cf.case_type',
                    'cf.complexity_score',
                ])
                ->selectRaw("1 - (cf.embedding <=> '{$vectorLiteral}'::vector) as similarity")
                ->where('c.id', '!=', $case->id)
                ->where('c.status', '!=', 'pending')
                ->whereNotNull('cf.embedding')
                ->whereRaw("1 - (cf.embedding <=> '{$vectorLiteral}'::vector) > ?", [$threshold])
                ->orderByDesc('similarity')
                ->limit($limit)
                ->get();

            Log::info('OutcomePredictor - Similar cases found (pgvector)', [
                'count' => $similar->count(),
                'threshold' => $threshold,
            ]);

            return $similar;
        } catch (\Exception $e) {
            Log::error('OutcomePredictor - pgvector search failed', [
                'error' => $e->getMessage(),
                'case_id' => $case->id,
            ]);

            return collect();
        }
    }

    /**
     * Find similar cases using JSON fallback
     */
    protected function findSimilarCasesWithFallback(LegalCase $case, array $embedding, float $threshold, int $limit): Collection
    {
        try {
            // Get all completed cases with embeddings
            $results = DB::table('case_features as cf')
                ->join('cases as c', 'cf.case_id', '=', 'c.id')
                ->select([
                    'c.id',
                    'c.case_number',
                    'c.title',
                    'c.status',
                    'c.court',
                    'c.jurisdiction',
                    'cf.case_type',
                    'cf.complexity_score',
                    'cf.embedding',
                ])
                ->where('c.id', '!=', $case->id)
                ->where('c.status', '!=', 'pending')
                ->whereNotNull('cf.embedding')
                ->limit($limit * 3)
                ->get();

            // Calculate similarity for each case
            $results = $results->map(function ($caseFeature) use ($embedding) {
                $caseVector = json_decode($caseFeature->embedding ?? '[]', true);
                $caseFeature->similarity = $this->cosineSimilarity($embedding, $caseVector);

                return $caseFeature;
            });

            // Filter by threshold and sort
            $similar = $results->filter(fn ($r) => $r->similarity >= $threshold)
                ->sortByDesc('similarity')
                ->take($limit)
                ->values();

            Log::info('OutcomePredictor - Similar cases found (JSON fallback)', [
                'count' => $similar->count(),
                'threshold' => $threshold,
            ]);

            return $similar;
        } catch (\Exception $e) {
            Log::error('OutcomePredictor - Fallback search failed', [
                'error' => $e->getMessage(),
                'case_id' => $case->id,
            ]);

            return collect();
        }
    }

    /**
     * Check if pgvector extension is available
     */
    protected function checkPgVectorAvailability(): bool
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return false;
        }

        try {
            $columnType = DB::select(
                'SELECT data_type FROM information_schema.columns WHERE table_name = ? AND column_name = ?',
                ['case_features', 'embedding']
            );

            return isset($columnType[0]->data_type) && strtolower($columnType[0]->data_type) === 'user-defined';
        } catch (\Exception $e) {
            Log::debug('OutcomePredictor - pgvector availability check failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Convert array to PostgreSQL vector literal
     */
    protected function toPgVectorLiteral(array $vec): string
    {
        $parts = [];
        foreach ($vec as $v) {
            $parts[] = rtrim(rtrim(number_format((float) $v, 8, '.', ''), '0'), '.');
        }

        return '['.implode(',', $parts).']';
    }

    /**
     * Calculate cosine similarity between two vectors
     */
    protected function cosineSimilarity(array $vec1, array $vec2): float
    {
        if (count($vec1) !== count($vec2) || empty($vec1)) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $norm1 = 0.0;
        $norm2 = 0.0;

        for ($i = 0; $i < count($vec1); $i++) {
            $dotProduct += $vec1[$i] * $vec2[$i];
            $norm1 += $vec1[$i] * $vec1[$i];
            $norm2 += $vec2[$i] * $vec2[$i];
        }

        $norm1 = sqrt($norm1);
        $norm2 = sqrt($norm2);

        if ($norm1 == 0 || $norm2 == 0) {
            return 0.0;
        }

        return $dotProduct / ($norm1 * $norm2);
    }

    /**
     * Analyze outcomes of similar cases
     */
    protected function analyzeSimilarOutcomes(Collection $similarCases): array
    {
        if ($similarCases->isEmpty()) {
            return [
                'most_likely' => 'unknown',
                'confidence' => 0.0,
                'favorable_pct' => 0.0,
                'unfavorable_pct' => 0.0,
                'settled_pct' => 0.0,
                'dismissed_pct' => 0.0,
            ];
        }

        $statusCounts = $similarCases->countBy('status');
        $total = $similarCases->count();

        $favorable = $statusCounts->get('won', 0) + $statusCounts->get('settled', 0);
        $unfavorable = $statusCounts->get('lost', 0);
        $settled = $statusCounts->get('settled', 0);
        $dismissed = $statusCounts->get('dismissed', 0);

        $favorablePct = ($favorable / $total) * 100;
        $unfavorablePct = ($unfavorable / $total) * 100;
        $settledPct = ($settled / $total) * 100;
        $dismissedPct = ($dismissed / $total) * 100;

        // Determine most likely outcome
        $mostLikely = 'unfavorable';
        $maxPct = $unfavorablePct;

        if ($favorablePct > $maxPct) {
            $mostLikely = 'favorable';
            $maxPct = $favorablePct;
        }

        if ($dismissedPct > $maxPct) {
            $mostLikely = 'dismissed';
            $maxPct = $dismissedPct;
        }

        // Confidence is based on sample size and agreement
        $confidence = min(($maxPct / 100) * ($total / 50), 1.0);

        return [
            'most_likely' => $mostLikely,
            'confidence' => round($confidence, 2),
            'favorable_pct' => round($favorablePct, 1),
            'unfavorable_pct' => round($unfavorablePct, 1),
            'settled_pct' => round($settledPct, 1),
            'dismissed_pct' => round($dismissedPct, 1),
        ];
    }

    /**
     * Use LLM for nuanced outcome analysis
     */
    protected function llmOutcomeAnalysis(array $features, Collection $similarCases): string
    {
        if ($similarCases->isEmpty()) {
            return 'Insufficient historical data for detailed analysis.';
        }

        $casesSummary = $similarCases->take(10)->map(function ($case) {
            return sprintf(
                '- %s: %s (court: %s, status: %s)',
                $case->case_number ?? 'N/A',
                $case->title ?? 'Untitled',
                $case->court ?? 'N/A',
                $case->status ?? 'N/A'
            );
        })->join("\n");

        $prompt = "You are an expert legal analyst. Based on the following case features and similar historical cases, provide a detailed analysis of the likely outcome.

Current Case Features:
- Type: {$features['case_type']}
- Complexity: {$features['complexity_score']}
- Court: {$features['court']}
- Jurisdiction: {$features['jurisdiction']}
- Legal Issues: ".json_encode($features['legal_issues'] ?? [])."

Similar Historical Cases:
{$casesSummary}

Provide a comprehensive analysis covering:
1. Most likely outcome and why
2. Key factors influencing the prediction
3. Potential risks or uncertainties
4. Strategic considerations

Keep your analysis focused and under 500 words.";

        try {
            $response = $this->openAI->complete($prompt, [
                'model' => 'gpt-4o-mini',
                'max_tokens' => 800,
                'temperature' => 0.3,
            ]);

            return $response['content'] ?? 'Analysis not available.';
        } catch (\Exception $e) {
            Log::error('OutcomePredictor - LLM analysis failed', [
                'error' => $e->getMessage(),
            ]);

            return 'LLM analysis failed: '.$e->getMessage();
        }
    }

    /**
     * Identify key factors influencing the prediction
     */
    protected function identifyKeyFactors(array $features, Collection $similarCases): array
    {
        $factors = [];

        // Complexity factor
        if (isset($features['complexity_score']) && $features['complexity_score'] > 0.7) {
            $factors[] = [
                'factor' => 'High Case Complexity',
                'impact' => 'negative',
                'description' => 'Complex cases typically have lower win rates and longer durations.',
            ];
        }

        // Jurisdiction factor
        if (isset($features['jurisdiction']) && ! empty($features['jurisdiction'])) {
            $factors[] = [
                'factor' => 'Jurisdiction',
                'impact' => 'neutral',
                'description' => "Cases in {$features['jurisdiction']} jurisdiction follow local precedents.",
            ];
        }

        // Historical success rate in similar cases
        if ($similarCases->isNotEmpty()) {
            $wonCases = $similarCases->where('status', 'won')->count();
            $totalCases = $similarCases->count();
            $successRate = ($wonCases / $totalCases) * 100;

            $impact = $successRate > 60 ? 'positive' : ($successRate < 40 ? 'negative' : 'neutral');

            $factors[] = [
                'factor' => 'Historical Success Rate',
                'impact' => $impact,
                'description' => sprintf('%.1f%% success rate in similar cases (%d/%d)',
                    $successRate, $wonCases, $totalCases),
            ];
        }

        // Precedent availability
        if (isset($features['precedent_count']) && $features['precedent_count'] > 0) {
            $factors[] = [
                'factor' => 'Available Precedents',
                'impact' => 'positive',
                'description' => "{$features['precedent_count']} relevant precedents identified.",
            ];
        }

        return $factors;
    }
}

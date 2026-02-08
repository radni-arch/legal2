<?php

namespace App\Services\LegalReasoning;

use App\Exceptions\LegalReasoningException;
use App\Models\CaseFeature;
use App\Models\CourtDecision;
use App\Models\Law;
use App\Models\LegalCase;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FeatureExtractor
{
    public function __construct(
        protected OpenAIService $openAI
    ) {}

    /**
     * Extract comprehensive features from a legal case
     */
    public function extractCaseFeatures(LegalCase $case): array
    {
        $startTime = microtime(true);

        Log::info('Starting case feature extraction', [
            'case_id' => $case->id,
            'case_number' => $case->case_number,
        ]);

        try {
            // Phase 1: Load documents
            $docLoadStart = microtime(true);
            try {
                $documents = $case->documents;
                $documentCount = $documents->count();
                $docLoadDuration = microtime(true) - $docLoadStart;

                Log::info('Documents loaded', [
                    'case_id' => $case->id,
                    'document_count' => $documentCount,
                    'duration_ms' => round($docLoadDuration * 1000, 2),
                ]);
            } catch (\Throwable $e) {
                $docLoadDuration = microtime(true) - $docLoadStart;
                Log::error('Document loading failed', [
                    'case_id' => $case->id,
                    'error' => $e->getMessage(),
                    'duration_ms' => round($docLoadDuration * 1000, 2),
                    'trace' => $e->getTraceAsString(),
                ]);
                throw new LegalReasoningException(
                    "Failed to load case documents: {$e->getMessage()}",
                    LegalReasoningException::FEATURE_EXTRACTION_FAILED,
                    $e
                );
            }

            // Phase 2: Basic case classification
            $classifyStart = microtime(true);
            try {
                $caseType = $this->classifyCaseType($case);
                $classifyDuration = microtime(true) - $classifyStart;

                Log::debug('Case type classified', [
                    'case_id' => $case->id,
                    'case_type' => $caseType,
                    'duration_ms' => round($classifyDuration * 1000, 2),
                ]);
            } catch (\Throwable $e) {
                $classifyDuration = microtime(true) - $classifyStart;
                Log::error('Case type classification failed', [
                    'case_id' => $case->id,
                    'error' => $e->getMessage(),
                    'duration_ms' => round($classifyDuration * 1000, 2),
                    'trace' => $e->getTraceAsString(),
                ]);
                $caseType = 'general'; // Fallback
            }

            // Phase 3: Calculate complexity
            $complexityStart = microtime(true);
            try {
                $complexity = $this->calculateComplexity($case);
                $complexityDuration = microtime(true) - $complexityStart;

                Log::debug('Complexity calculated', [
                    'case_id' => $case->id,
                    'complexity_score' => $complexity,
                    'duration_ms' => round($complexityDuration * 1000, 2),
                ]);
            } catch (\Throwable $e) {
                $complexityDuration = microtime(true) - $complexityStart;
                Log::error('Complexity calculation failed', [
                    'case_id' => $case->id,
                    'error' => $e->getMessage(),
                    'duration_ms' => round($complexityDuration * 1000, 2),
                    'trace' => $e->getTraceAsString(),
                ]);
                $complexity = 0.0; // Fallback
            }

            // Phase 4: Party classification
            $partyStart = microtime(true);
            try {
                $clientType = $this->classifyParty($case->client_name);
                $opponentType = $this->classifyParty($case->opponent_name);
                $partyDuration = microtime(true) - $partyStart;

                Log::debug('Parties classified', [
                    'case_id' => $case->id,
                    'client_type' => $clientType,
                    'opponent_type' => $opponentType,
                    'duration_ms' => round($partyDuration * 1000, 2),
                ]);
            } catch (\Throwable $e) {
                $partyDuration = microtime(true) - $partyStart;
                Log::error('Party classification failed', [
                    'case_id' => $case->id,
                    'error' => $e->getMessage(),
                    'duration_ms' => round($partyDuration * 1000, 2),
                    'trace' => $e->getTraceAsString(),
                ]);
                $clientType = 'individual'; // Fallback
                $opponentType = 'individual'; // Fallback
            }

            // Phase 5: Extract legal issues
            $issuesStart = microtime(true);
            try {
                $legalIssues = $this->extractLegalIssues($case);
                $issuesDuration = microtime(true) - $issuesStart;

                Log::debug('Legal issues extracted', [
                    'case_id' => $case->id,
                    'issue_count' => count($legalIssues),
                    'duration_ms' => round($issuesDuration * 1000, 2),
                ]);
            } catch (\Throwable $e) {
                $issuesDuration = microtime(true) - $issuesStart;
                Log::error('Legal issues extraction failed', [
                    'case_id' => $case->id,
                    'error' => $e->getMessage(),
                    'duration_ms' => round($issuesDuration * 1000, 2),
                    'trace' => $e->getTraceAsString(),
                ]);
                $legalIssues = []; // Fallback
            }

            // Phase 6: Count precedents
            $precedentStart = microtime(true);
            try {
                $precedentCount = $this->countPrecedents($case);
                $precedentDuration = microtime(true) - $precedentStart;

                Log::debug('Precedents counted', [
                    'case_id' => $case->id,
                    'precedent_count' => $precedentCount,
                    'duration_ms' => round($precedentDuration * 1000, 2),
                ]);
            } catch (\Throwable $e) {
                $precedentDuration = microtime(true) - $precedentStart;
                Log::error('Precedent counting failed', [
                    'case_id' => $case->id,
                    'error' => $e->getMessage(),
                    'duration_ms' => round($precedentDuration * 1000, 2),
                    'trace' => $e->getTraceAsString(),
                ]);
                $precedentCount = 0; // Fallback
            }

            // Phase 7: Temporal features
            $temporalStart = microtime(true);
            try {
                $daysSinceFiling = $case->filing_date
                    ? (int) now()->diffInDays($case->filing_date)
                    : null;
                $temporalDuration = microtime(true) - $temporalStart;

                Log::debug('Temporal features calculated', [
                    'case_id' => $case->id,
                    'days_since_filing' => $daysSinceFiling,
                    'duration_ms' => round($temporalDuration * 1000, 2),
                ]);
            } catch (\Throwable $e) {
                $temporalDuration = microtime(true) - $temporalStart;
                Log::error('Temporal feature calculation failed', [
                    'case_id' => $case->id,
                    'error' => $e->getMessage(),
                    'duration_ms' => round($temporalDuration * 1000, 2),
                    'trace' => $e->getTraceAsString(),
                ]);
                $daysSinceFiling = null; // Fallback
            }

            // Phase 8: Generate embedding
            $embeddingStart = microtime(true);
            try {
                $embedding = $this->generateCaseEmbedding($case);
                $embeddingDuration = microtime(true) - $embeddingStart;

                Log::info('Embedding generated', [
                    'case_id' => $case->id,
                    'embedding_dimensions' => $embedding ? count($embedding) : 0,
                    'duration_ms' => round($embeddingDuration * 1000, 2),
                ]);
            } catch (\Throwable $e) {
                $embeddingDuration = microtime(true) - $embeddingStart;
                Log::error('Embedding generation failed', [
                    'case_id' => $case->id,
                    'error' => $e->getMessage(),
                    'duration_ms' => round($embeddingDuration * 1000, 2),
                    'trace' => $e->getTraceAsString(),
                ]);
                $embedding = null; // Fallback
            }

            $totalDuration = microtime(true) - $startTime;
            Log::info('Case feature extraction completed successfully', [
                'case_id' => $case->id,
                'total_duration_ms' => round($totalDuration * 1000, 2),
                'timing_breakdown' => [
                    'doc_load_ms' => round($docLoadDuration * 1000, 2),
                    'classify_ms' => round($classifyDuration * 1000, 2),
                    'complexity_ms' => round($complexityDuration * 1000, 2),
                    'party_ms' => round($partyDuration * 1000, 2),
                    'issues_ms' => round($issuesDuration * 1000, 2),
                    'precedent_ms' => round($precedentDuration * 1000, 2),
                    'temporal_ms' => round($temporalDuration * 1000, 2),
                    'embedding_ms' => round($embeddingDuration * 1000, 2),
                ],
            ]);

            return [
                'case_id' => $case->id,
                'case_number' => $case->case_number,
                'case_type' => $caseType,
                'case_category' => $this->categorizeCaseType($caseType),
                'complexity_level' => $this->categorizeComplexity($complexity),
                'complexity_score' => $complexity,
                'document_count' => $documentCount,
                'precedent_count' => $precedentCount,
                'party_count' => 2, // client + opponent
                'client_type' => $clientType,
                'opponent_type' => $opponentType,
                'court' => $case->court,
                'jurisdiction' => $case->jurisdiction,
                'legal_issues' => $legalIssues,
                'days_since_filing' => $daysSinceFiling,
                'embedding' => $embedding,
            ];
        } catch (LegalReasoningException $e) {
            $totalDuration = microtime(true) - $startTime;
            Log::error('Case feature extraction failed', [
                'case_id' => $case->id,
                'error_code' => $e->getCode(),
                'error' => $e->getMessage(),
                'total_duration_ms' => round($totalDuration * 1000, 2),
            ]);
            throw $e;
        } catch (\Throwable $e) {
            $totalDuration = microtime(true) - $startTime;
            Log::error('Case feature extraction failed with unexpected error', [
                'case_id' => $case->id,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'total_duration_ms' => round($totalDuration * 1000, 2),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new LegalReasoningException(
                "Unexpected error in feature extraction for case {$case->id}: {$e->getMessage()}",
                LegalReasoningException::UNEXPECTED_ERROR,
                $e
            );
        }
    }

    /**
     * Extract features from a court decision
     */
    public function extractDecisionFeatures(CourtDecision $decision): array
    {
        return [
            'decision_id' => $decision->id,
            'case_number' => $decision->case_number,
            'decision_type' => $decision->decision_type,
            'court' => $decision->court,
            'jurisdiction' => $decision->jurisdiction,
            'decision_date' => $decision->decision_date?->toISOString(),
            'finality' => $decision->finality,
            'has_ecli' => ! empty($decision->ecli),
        ];
    }

    /**
     * Extract features from a law
     */
    public function extractLawFeatures(Law $law): array
    {
        return [
            'law_id' => $law->id,
            'law_number' => $law->law_number,
            'jurisdiction' => $law->jurisdiction,
            'country' => $law->country,
            'language' => $law->language,
            'is_effective' => ! empty($law->effective_date) &&
                now()->gte($law->effective_date) &&
                (empty($law->repeal_date) || now()->lt($law->repeal_date)),
        ];
    }

    /**
     * Normalize features for ML models
     */
    public function normalizeFeatures(array $features): array
    {
        $normalized = $features;

        // Normalize complexity score to 0-1 range
        if (isset($features['complexity_score'])) {
            $normalized['complexity_score'] = max(0, min(1, $features['complexity_score']));
        }

        // Normalize document count (log scale)
        if (isset($features['document_count'])) {
            $normalized['document_count_normalized'] = log($features['document_count'] + 1) / log(100);
        }

        // Normalize precedent count
        if (isset($features['precedent_count'])) {
            $normalized['precedent_count_normalized'] = min(1, $features['precedent_count'] / 50);
        }

        return $normalized;
    }

    /**
     * Classify case type using heuristics and LLM
     */
    protected function classifyCaseType(LegalCase $case): string
    {
        // Use title and description for classification
        $text = strtolower($case->title.' '.$case->description);

        // Simple keyword matching
        $keywords = [
            'civil' => ['contract', 'damages', 'breach', 'tort', 'negligence'],
            'criminal' => ['theft', 'fraud', 'assault', 'battery', 'crime'],
            'administrative' => ['permit', 'license', 'regulation', 'administrative'],
            'family' => ['divorce', 'custody', 'alimony', 'child support'],
            'property' => ['land', 'property', 'real estate', 'lease', 'tenant'],
            'employment' => ['employment', 'wrongful termination', 'discrimination', 'labor'],
        ];

        foreach ($keywords as $type => $words) {
            foreach ($words as $word) {
                if (str_contains($text, $word)) {
                    return $type;
                }
            }
        }

        return 'general';
    }

    /**
     * Calculate case complexity score (0-1)
     */
    protected function calculateComplexity(LegalCase $case): float
    {
        $score = 0.0;

        // Document count factor (0-0.3)
        $docCount = $case->documents->count();
        $score += min(0.3, ($docCount / 50) * 0.3);

        // Description length factor (0-0.2)
        $descLength = strlen($case->description ?? '');
        $score += min(0.2, ($descLength / 1000) * 0.2);

        // Tags complexity (0-0.2)
        $tagCount = is_array($case->tags) ? count($case->tags) : 0;
        $score += min(0.2, ($tagCount / 10) * 0.2);

        // Title complexity - multi-party or multiple issues (0-0.3)
        $titleWords = str_word_count($case->title ?? '');
        $score += min(0.3, ($titleWords / 20) * 0.3);

        return round($score, 2);
    }

    /**
     * Classify party type (individual, corporation, government, etc.)
     */
    protected function classifyParty(?string $name): string
    {
        if (! $name) {
            return 'individual';
        }

        $name = strtolower($name);

        $patterns = [
            'government' => ['republic', 'state', 'ministry', 'municipality', 'government'],
            'corporation' => ['ltd', 'd.o.o.', 'inc', 'corp', 'llc', 'gmbh'],
            'individual' => [],
        ];

        foreach ($patterns as $type => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($name, $keyword)) {
                    return $type;
                }
            }
        }

        return 'individual';
    }

    /**
     * Extract legal issues from case
     */
    protected function extractLegalIssues(LegalCase $case): array
    {
        // Simple extraction from tags and description
        $issues = [];

        if (is_array($case->tags)) {
            $issues = array_merge($issues, $case->tags);
        }

        return array_unique($issues);
    }

    /**
     * Count precedents cited in case documents
     */
    protected function countPrecedents(LegalCase $case): int
    {
        $count = 0;

        foreach ($case->documents as $document) {
            // Count citation patterns in content
            if (! empty($document->content)) {
                // Simple pattern matching for Croatian case numbers (e.g., "Rev-1234/2020")
                preg_match_all('/[A-Z]{2,4}-\d+\/\d{4}/', $document->content, $matches);
                $count += count($matches[0]);
            }
        }

        return $count;
    }

    /**
     * Generate embedding for case using all documents
     */
    protected function generateCaseEmbedding(LegalCase $case): ?array
    {
        $startTime = microtime(true);

        try {
            // Phase 1: Combine text from case and documents
            $textBuildStart = microtime(true);
            $text = $case->title."\n".$case->description;

            $documentCount = 0;
            foreach ($case->documents->take(5) as $document) {
                $text .= "\n".substr($document->content ?? '', 0, 1000);
                $documentCount++;
            }

            // Trim to reasonable length
            $originalLength = strlen($text);
            $text = substr($text, 0, 8000);
            $finalLength = strlen($text);
            $textBuildDuration = microtime(true) - $textBuildStart;

            Log::debug('Embedding text prepared', [
                'case_id' => $case->id,
                'documents_included' => $documentCount,
                'original_length' => $originalLength,
                'final_length' => $finalLength,
                'truncated' => $originalLength > $finalLength,
                'duration_ms' => round($textBuildDuration * 1000, 2),
            ]);

            if (empty(trim($text))) {
                Log::warning('No text available for embedding generation', [
                    'case_id' => $case->id,
                ]);

                return null;
            }

            // Phase 2: Call OpenAI embeddings API
            $apiStart = microtime(true);
            try {
                $embedding = $this->openAI->embeddings($text);
                $apiDuration = microtime(true) - $apiStart;

                Log::info('OpenAI embeddings API call succeeded', [
                    'case_id' => $case->id,
                    'text_length' => $finalLength,
                    'api_duration_ms' => round($apiDuration * 1000, 2),
                ]);
            } catch (\Throwable $e) {
                $apiDuration = microtime(true) - $apiStart;
                Log::error('OpenAI embeddings API call failed', [
                    'case_id' => $case->id,
                    'text_length' => $finalLength,
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e),
                    'api_duration_ms' => round($apiDuration * 1000, 2),
                    'trace' => $e->getTraceAsString(),
                ]);

                return null;
            }

            // Phase 3: Extract embedding from response
            $result = $embedding['data'][0]['embedding'] ?? $embedding['embedding'] ?? null;

            $totalDuration = microtime(true) - $startTime;

            if ($result) {
                Log::info('Embedding generated successfully', [
                    'case_id' => $case->id,
                    'embedding_dimensions' => count($result),
                    'total_duration_ms' => round($totalDuration * 1000, 2),
                    'timing_breakdown' => [
                        'text_build_ms' => round($textBuildDuration * 1000, 2),
                        'api_call_ms' => round($apiDuration * 1000, 2),
                    ],
                ]);
            } else {
                Log::warning('Embedding extraction from API response failed', [
                    'case_id' => $case->id,
                    'response_keys' => array_keys($embedding),
                    'total_duration_ms' => round($totalDuration * 1000, 2),
                ]);
            }

            return $result;
        } catch (\Throwable $e) {
            $totalDuration = microtime(true) - $startTime;
            Log::error('Embedding generation failed with unexpected error', [
                'case_id' => $case->id,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'total_duration_ms' => round($totalDuration * 1000, 2),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * Categorize case type into broader categories
     */
    protected function categorizeCaseType(string $caseType): string
    {
        $categories = [
            'civil' => 'civil',
            'criminal' => 'criminal',
            'administrative' => 'public_law',
            'family' => 'civil',
            'property' => 'civil',
            'employment' => 'civil',
        ];

        return $categories[$caseType] ?? 'other';
    }

    /**
     * Categorize complexity score into levels
     */
    protected function categorizeComplexity(float $score): string
    {
        if ($score < 0.25) {
            return 'simple';
        } elseif ($score < 0.5) {
            return 'moderate';
        } elseif ($score < 0.75) {
            return 'complex';
        } else {
            return 'very_complex';
        }
    }

    /**
     * Extract and persist case features to database
     */
    public function extractAndPersistCaseFeatures(LegalCase $case): CaseFeature
    {
        $startTime = microtime(true);

        Log::info('Starting case feature extraction and persistence', [
            'case_id' => $case->id,
        ]);

        try {
            // Phase 1: Extract features
            $extractStart = microtime(true);
            try {
                $features = $this->extractCaseFeatures($case);
                $extractDuration = microtime(true) - $extractStart;

                Log::info('Features extracted successfully', [
                    'case_id' => $case->id,
                    'duration_ms' => round($extractDuration * 1000, 2),
                ]);
            } catch (LegalReasoningException $e) {
                throw $e;
            } catch (\Throwable $e) {
                $extractDuration = microtime(true) - $extractStart;
                Log::error('Feature extraction failed', [
                    'case_id' => $case->id,
                    'error' => $e->getMessage(),
                    'duration_ms' => round($extractDuration * 1000, 2),
                    'trace' => $e->getTraceAsString(),
                ]);
                throw new LegalReasoningException(
                    "Feature extraction failed: {$e->getMessage()}",
                    LegalReasoningException::FEATURE_EXTRACTION_FAILED,
                    $e
                );
            }

            // Phase 2: Calculate embedding norm if embedding exists
            $normStart = microtime(true);
            $embeddingNorm = null;
            if ($features['embedding']) {
                try {
                    $embeddingNorm = sqrt(array_sum(array_map(fn ($x) => $x ** 2, $features['embedding'])));
                    $normDuration = microtime(true) - $normStart;

                    Log::debug('Embedding norm calculated', [
                        'case_id' => $case->id,
                        'norm' => $embeddingNorm,
                        'duration_ms' => round($normDuration * 1000, 2),
                    ]);
                } catch (\Throwable $e) {
                    $normDuration = microtime(true) - $normStart;
                    Log::warning('Embedding norm calculation failed', [
                        'case_id' => $case->id,
                        'error' => $e->getMessage(),
                        'duration_ms' => round($normDuration * 1000, 2),
                    ]);
                    $embeddingNorm = null;
                }
            }

            // Phase 3: Persist to database
            $persistStart = microtime(true);
            try {
                $caseFeature = CaseFeature::updateOrCreate(
                    ['case_id' => $case->id],
                    [
                        'id' => Str::ulid(),
                        'case_type' => $features['case_type'],
                        'case_category' => $features['case_category'],
                        'complexity_level' => $features['complexity_level'],
                        'complexity_score' => $features['complexity_score'],
                        'document_count' => $features['document_count'],
                        'precedent_count' => $features['precedent_count'],
                        'party_count' => $features['party_count'],
                        'client_type' => $features['client_type'],
                        'opponent_type' => $features['opponent_type'],
                        'legal_issues' => $features['legal_issues'],
                        'days_since_filing' => $features['days_since_filing'],
                        'embedding' => $features['embedding'],
                        'embedding_norm' => $embeddingNorm,
                        'features_extracted_at' => now(),
                        'extraction_version' => '1.0',
                    ]
                );
                $persistDuration = microtime(true) - $persistStart;

                Log::info('Features persisted to database', [
                    'case_id' => $case->id,
                    'feature_id' => $caseFeature->id,
                    'duration_ms' => round($persistDuration * 1000, 2),
                ]);
            } catch (\Throwable $e) {
                $persistDuration = microtime(true) - $persistStart;
                Log::error('Feature persistence failed', [
                    'case_id' => $case->id,
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e),
                    'duration_ms' => round($persistDuration * 1000, 2),
                    'trace' => $e->getTraceAsString(),
                ]);
                throw new LegalReasoningException(
                    "Failed to persist case features: {$e->getMessage()}",
                    LegalReasoningException::FEATURE_EXTRACTION_FAILED,
                    $e
                );
            }

            // Phase 4: Update pgvector if using PostgreSQL
            if (config('database.default') === 'pgsql' && $features['embedding']) {
                $vectorStart = microtime(true);
                try {
                    DB::statement(
                        'UPDATE case_features SET embedding = ?::vector WHERE id = ?',
                        [json_encode($features['embedding']), $caseFeature->id]
                    );
                    $vectorDuration = microtime(true) - $vectorStart;

                    Log::debug('pgvector updated', [
                        'case_id' => $case->id,
                        'feature_id' => $caseFeature->id,
                        'duration_ms' => round($vectorDuration * 1000, 2),
                    ]);
                } catch (\Throwable $e) {
                    $vectorDuration = microtime(true) - $vectorStart;
                    Log::warning('pgvector update failed', [
                        'case_id' => $case->id,
                        'feature_id' => $caseFeature->id,
                        'error' => $e->getMessage(),
                        'duration_ms' => round($vectorDuration * 1000, 2),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    // Don't throw - pgvector is optional
                }
            }

            $totalDuration = microtime(true) - $startTime;
            Log::info('Case feature extraction and persistence completed successfully', [
                'case_id' => $case->id,
                'feature_id' => $caseFeature->id,
                'total_duration_ms' => round($totalDuration * 1000, 2),
            ]);

            return $caseFeature;
        } catch (LegalReasoningException $e) {
            $totalDuration = microtime(true) - $startTime;
            Log::error('Case feature extraction and persistence failed', [
                'case_id' => $case->id,
                'error_code' => $e->getCode(),
                'error' => $e->getMessage(),
                'total_duration_ms' => round($totalDuration * 1000, 2),
            ]);
            throw $e;
        } catch (\Throwable $e) {
            $totalDuration = microtime(true) - $startTime;
            Log::error('Case feature extraction and persistence failed with unexpected error', [
                'case_id' => $case->id,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'total_duration_ms' => round($totalDuration * 1000, 2),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new LegalReasoningException(
                "Unexpected error in feature extraction and persistence: {$e->getMessage()}",
                LegalReasoningException::UNEXPECTED_ERROR,
                $e
            );
        }
    }

    /**
     * Get or extract case features (with caching)
     */
    public function getCaseFeatures(LegalCase $case, bool $refresh = false): array
    {
        $startTime = microtime(true);

        Log::info('Getting case features', [
            'case_id' => $case->id,
            'refresh' => $refresh,
        ]);

        try {
            // Check if features already exist and are recent
            $cacheCheckStart = microtime(true);
            try {
                $existingFeatures = $case->features;
                $cacheCheckDuration = microtime(true) - $cacheCheckStart;

                if (! $refresh && $existingFeatures && $existingFeatures->features_extracted_at->gt(now()->subDays(7))) {
                    // Use cached features
                    Log::info('Using cached features', [
                        'case_id' => $case->id,
                        'feature_age_hours' => round(now()->diffInHours($existingFeatures->features_extracted_at), 2),
                        'cache_check_duration_ms' => round($cacheCheckDuration * 1000, 2),
                    ]);

                    $totalDuration = microtime(true) - $startTime;
                    Log::info('Case features retrieved from cache', [
                        'case_id' => $case->id,
                        'total_duration_ms' => round($totalDuration * 1000, 2),
                    ]);

                    return [
                        'case_id' => $existingFeatures->case_id,
                        'case_number' => $case->case_number,
                        'case_type' => $existingFeatures->case_type,
                        'case_category' => $existingFeatures->case_category,
                        'complexity_level' => $existingFeatures->complexity_level,
                        'complexity_score' => $existingFeatures->complexity_score,
                        'document_count' => $existingFeatures->document_count,
                        'precedent_count' => $existingFeatures->precedent_count,
                        'party_count' => $existingFeatures->party_count,
                        'client_type' => $existingFeatures->client_type,
                        'opponent_type' => $existingFeatures->opponent_type,
                        'court' => $case->court,
                        'jurisdiction' => $case->jurisdiction,
                        'legal_issues' => $existingFeatures->legal_issues,
                        'days_since_filing' => $existingFeatures->days_since_filing,
                        'embedding' => $existingFeatures->embedding,
                    ];
                }

                Log::info('Cache miss or refresh requested, extracting fresh features', [
                    'case_id' => $case->id,
                    'has_existing' => $existingFeatures !== null,
                    'cache_check_duration_ms' => round($cacheCheckDuration * 1000, 2),
                ]);
            } catch (\Throwable $e) {
                $cacheCheckDuration = microtime(true) - $cacheCheckStart;
                Log::warning('Cache check failed, extracting fresh features', [
                    'case_id' => $case->id,
                    'error' => $e->getMessage(),
                    'cache_check_duration_ms' => round($cacheCheckDuration * 1000, 2),
                ]);
            }

            // Extract fresh features
            $features = $this->extractCaseFeatures($case);

            $totalDuration = microtime(true) - $startTime;
            Log::info('Case features extracted (fresh)', [
                'case_id' => $case->id,
                'total_duration_ms' => round($totalDuration * 1000, 2),
            ]);

            return $features;
        } catch (LegalReasoningException $e) {
            $totalDuration = microtime(true) - $startTime;
            Log::error('Get case features failed', [
                'case_id' => $case->id,
                'error_code' => $e->getCode(),
                'error' => $e->getMessage(),
                'total_duration_ms' => round($totalDuration * 1000, 2),
            ]);
            throw $e;
        } catch (\Throwable $e) {
            $totalDuration = microtime(true) - $startTime;
            Log::error('Get case features failed with unexpected error', [
                'case_id' => $case->id,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'total_duration_ms' => round($totalDuration * 1000, 2),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new LegalReasoningException(
                "Unexpected error getting case features: {$e->getMessage()}",
                LegalReasoningException::UNEXPECTED_ERROR,
                $e
            );
        }
    }
}

<?php

namespace App\Services\LegalReasoning;

use App\Exceptions\AnalysisException;
use App\Models\Law;
use App\Services\GraphDatabaseService;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Conflict Resolver
 *
 * Detects and resolves conflicts between laws using graph database
 * similarity search and LLM-powered analysis.
 */
class ConflictResolver
{
    public function __construct(
        protected GraphDatabaseService $graphDb,
        protected OpenAIService $openAI
    ) {}

    /**
     * Find potential conflicts with a given law
     */
    public function findConflicts(string $lawId): array
    {
        $correlationId = request()->header('X-Request-ID') ?? Str::uuid()->toString();
        $startTime = microtime(true);

        Log::withContext(['correlation_id' => $correlationId]);

        Log::info('ConflictResolver: findConflicts initiated', [
            'law_id' => $lawId,
            'user_id' => auth()->id(),
        ]);

        try {
            $law = Law::findOrFail($lawId);

            // Find similar laws in same jurisdiction using vector similarity
            $similarLaws = $this->findSimilarLawsInJurisdiction($law);

            if (empty($similarLaws)) {
                Log::info('ConflictResolver: No similar laws found', ['law_id' => $lawId]);

                return [];
            }

            // Use LLM to analyze for actual conflicts
            $conflicts = $this->llmConflictAnalysis($law, $similarLaws);

            $duration = (microtime(true) - $startTime) * 1000;

            Log::info('ConflictResolver: findConflicts completed', [
                'law_id' => $lawId,
                'conflict_count' => count($conflicts),
                'similar_laws_checked' => count($similarLaws),
                'duration_ms' => round($duration, 2),
            ]);

            return $conflicts;

        } catch (AnalysisException $e) {
            Log::error('ConflictResolver: findConflicts failed with AnalysisException', [
                'law_id' => $lawId,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('ConflictResolver: Law not found', [
                'law_id' => $lawId,
                'error' => $e->getMessage(),
            ]);

            throw new AnalysisException(
                "Law not found: {$lawId}",
                AnalysisException::RESOURCE_NOT_FOUND,
                $e
            );

        } catch (\Exception $e) {
            Log::error('ConflictResolver: findConflicts failed with unexpected exception', [
                'law_id' => $lawId,
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new AnalysisException(
                'Conflict detection failed: '.$e->getMessage(),
                AnalysisException::ANALYSIS_FAILED,
                $e
            );
        }
    }

    /**
     * Resolve conflict between multiple laws
     */
    public function resolveConflict(array $laws): array
    {
        $correlationId = request()->header('X-Request-ID') ?? Str::uuid()->toString();
        $startTime = microtime(true);

        Log::withContext(['correlation_id' => $correlationId]);

        Log::info('ConflictResolver: resolveConflict initiated', [
            'law_count' => count($laws),
            'user_id' => auth()->id(),
        ]);

        try {
            if (count($laws) < 2) {
                throw new \InvalidArgumentException('At least 2 laws required for conflict resolution');
            }

            // Apply precedence rules
            $winningLaw = $this->applyPrecedenceRules($laws);

            // Generate explanation
            $reasoning = $this->explainResolution($laws, $winningLaw);

            // Find supporting court decisions
            $citations = $this->findSupportingDecisions($laws);

            $result = [
                'winning_law' => $winningLaw,
                'reasoning' => $reasoning,
                'citations' => $citations,
                'precedence_factors' => $this->getPrecedenceFactors($laws, $winningLaw),
            ];

            $duration = (microtime(true) - $startTime) * 1000;

            Log::info('ConflictResolver: resolveConflict completed', [
                'law_count' => count($laws),
                'winning_law_id' => $winningLaw['id'] ?? null,
                'citations_found' => count($citations),
                'duration_ms' => round($duration, 2),
            ]);

            return $result;

        } catch (AnalysisException $e) {
            Log::error('ConflictResolver: resolveConflict failed with AnalysisException', [
                'law_count' => count($laws),
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        } catch (\InvalidArgumentException $e) {
            Log::error('ConflictResolver: Invalid arguments for conflict resolution', [
                'law_count' => count($laws),
                'error' => $e->getMessage(),
            ]);

            throw new AnalysisException(
                'Invalid conflict resolution arguments: '.$e->getMessage(),
                AnalysisException::INVALID_INPUT,
                $e
            );

        } catch (\Exception $e) {
            Log::error('ConflictResolver: resolveConflict failed with unexpected exception', [
                'law_count' => count($laws),
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new AnalysisException(
                'Conflict resolution failed: '.$e->getMessage(),
                AnalysisException::ANALYSIS_FAILED,
                $e
            );
        }
    }

    /**
     * Find similar laws in the same jurisdiction
     */
    protected function findSimilarLawsInJurisdiction(Law $law): array
    {
        // Query similar laws using database
        $similarLaws = Law::where('jurisdiction', $law->jurisdiction)
            ->where('id', '!=', $law->id)
            ->whereNotNull('effective_date')
            ->where(function ($query) {
                $query->whereNull('repeal_date')
                    ->orWhere('repeal_date', '>', now());
            })
            ->get();

        // If we have embeddings, filter by similarity
        if (! empty($law->embedding) && $similarLaws->isNotEmpty()) {
            $similarLaws = $similarLaws->filter(function ($candidate) use ($law) {
                if (empty($candidate->embedding)) {
                    return false;
                }

                // Calculate cosine similarity
                $similarity = $this->calculateCosineSimilarity(
                    $law->embedding,
                    $candidate->embedding
                );

                return $similarity > 0.75; // Threshold for potential conflict
            });
        }

        return $similarLaws->take(20)->toArray();
    }

    /**
     * Use LLM to analyze potential conflicts
     */
    protected function llmConflictAnalysis(Law $law, array $candidates): array
    {
        if (empty($candidates)) {
            return [];
        }

        $conflicts = [];

        // Analyze in batches to avoid token limits
        $batches = array_chunk($candidates, 5);

        foreach ($batches as $batch) {
            $candidateTexts = array_map(function ($candidate) {
                return sprintf(
                    "Law: %s\nTitle: %s\nContent: %s",
                    $candidate['law_number'] ?? 'N/A',
                    $candidate['title'] ?? 'Untitled',
                    substr($candidate['content'] ?? '', 0, 500)
                );
            }, $batch);

            $prompt = "You are a legal expert analyzing potential conflicts between laws.

Reference Law:
Law: {$law->law_number}
Title: {$law->title}
Content: ".substr($law->content ?? '', 0, 500).'

Candidate Laws:
'.implode("\n\n---\n\n", $candidateTexts)."

Analyze each candidate law and determine if it conflicts with the reference law. A conflict exists if:
1. They regulate the same subject matter differently
2. One prohibits what the other permits
3. They set different requirements for the same situation
4. They contradict each other's provisions

For each conflicting law, respond in JSON format:
{
  \"conflicts\": [
    {
      \"law_id\": \"candidate_law_number\",
      \"conflict_type\": \"contradiction|overlap|inconsistency\",
      \"severity\": \"high|medium|low\",
      \"description\": \"Brief description of the conflict\",
      \"conflicting_provisions\": \"Specific provisions that conflict\"
    }
  ]
}

If no conflicts found, return {\"conflicts\": []}";

            try {
                $response = $this->openAI->complete($prompt, [
                    'model' => 'gpt-4o-mini',
                    'max_tokens' => 1000,
                    'temperature' => 0.2,
                ]);

                $content = $response['content'] ?? '';

                // Extract JSON from response
                if (preg_match('/\{[\s\S]*"conflicts"[\s\S]*\}/', $content, $matches)) {
                    $result = json_decode($matches[0], true);
                    if (isset($result['conflicts'])) {
                        $conflicts = array_merge($conflicts, $result['conflicts']);
                    }
                }
            } catch (\Exception $e) {
                Log::error('ConflictResolver - LLM analysis failed', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $conflicts;
    }

    /**
     * Apply precedence rules to determine which law takes priority
     */
    protected function applyPrecedenceRules(array $laws): array
    {
        // Sort by precedence (multiple factors)
        usort($laws, function ($a, $b) {
            // Rule 1: Higher jurisdiction wins (e.g., federal > state)
            $aLevel = $this->getJurisdictionLevel($a['jurisdiction'] ?? '');
            $bLevel = $this->getJurisdictionLevel($b['jurisdiction'] ?? '');
            if ($aLevel !== $bLevel) {
                return $bLevel - $aLevel; // Higher level first
            }

            // Rule 2: Specific law wins over general law
            $aSpecificity = $this->calculateSpecificity($a);
            $bSpecificity = $this->calculateSpecificity($b);
            if (abs($aSpecificity - $bSpecificity) > 0.1) {
                return $bSpecificity <=> $aSpecificity; // More specific first
            }

            // Rule 3: Newer law wins (lex posterior)
            $aDate = $a['effective_date'] ?? null;
            $bDate = $b['effective_date'] ?? null;
            if ($aDate && $bDate) {
                return strcmp($bDate, $aDate); // Newer first
            }

            return 0;
        });

        return $laws[0]; // Return highest precedence law
    }

    /**
     * Explain the resolution reasoning
     */
    protected function explainResolution(array $laws, array $winningLaw): string
    {
        $lawDescriptions = array_map(function ($law) {
            return sprintf(
                '- %s (%s): %s, effective %s',
                $law['law_number'] ?? 'Unknown',
                $law['jurisdiction'] ?? 'Unknown',
                $law['title'] ?? 'Untitled',
                $law['effective_date'] ?? 'Unknown'
            );
        }, $laws);

        $prompt = 'You are a legal expert explaining conflict resolution between laws.

Laws in Conflict:
'.implode("\n", $lawDescriptions)."

Winning Law: {$winningLaw['law_number']} ({$winningLaw['title']})

Explain in 2-3 sentences why this law takes precedence over the others, considering:
1. Jurisdiction hierarchy
2. Specificity of provisions
3. Temporal precedence (newer laws supersede older)
4. Express repeal or amendment provisions

Provide a clear, professional explanation suitable for legal documentation.";

        try {
            $response = $this->openAI->complete($prompt, [
                'model' => 'gpt-4o-mini',
                'max_tokens' => 300,
                'temperature' => 0.3,
            ]);

            return $response['content'] ?? 'Unable to generate explanation.';
        } catch (\Exception $e) {
            Log::error('ConflictResolver - Explanation generation failed', [
                'error' => $e->getMessage(),
            ]);

            return 'Precedence determined by standard legal principles.';
        }
    }

    /**
     * Find court decisions supporting the resolution
     */
    protected function findSupportingDecisions(array $laws): array
    {
        // Extract law numbers
        $lawNumbers = array_filter(array_map(fn ($law) => $law['law_number'] ?? null, $laws));

        if (empty($lawNumbers)) {
            return [];
        }

        // Search for decisions that cite these laws
        $decisions = \App\Models\CourtDecision::whereHas('documents', function ($query) use ($lawNumbers) {
            $query->where(function ($q) use ($lawNumbers) {
                foreach ($lawNumbers as $lawNumber) {
                    $q->orWhere('content', 'LIKE', "%{$lawNumber}%");
                }
            });
        })
            ->orderBy('decision_date', 'desc')
            ->limit(10)
            ->get();

        return $decisions->map(function ($decision) {
            return [
                'id' => $decision->id,
                'case_number' => $decision->case_number,
                'title' => $decision->title,
                'court' => $decision->court,
                'decision_date' => $decision->decision_date?->toDateString(),
            ];
        })->toArray();
    }

    /**
     * Get factors that determined precedence
     */
    protected function getPrecedenceFactors(array $laws, array $winningLaw): array
    {
        return [
            'jurisdiction_level' => $this->getJurisdictionLevel($winningLaw['jurisdiction'] ?? ''),
            'specificity_score' => $this->calculateSpecificity($winningLaw),
            'is_most_recent' => $this->isMostRecent($winningLaw, $laws),
            'has_express_repeal' => $this->hasExpressRepeal($winningLaw),
        ];
    }

    /**
     * Calculate cosine similarity between two vectors
     */
    protected function calculateCosineSimilarity(array $vec1, array $vec2): float
    {
        if (count($vec1) !== count($vec2)) {
            return 0.0;
        }

        $dotProduct = 0;
        $mag1 = 0;
        $mag2 = 0;

        for ($i = 0; $i < count($vec1); $i++) {
            $dotProduct += $vec1[$i] * $vec2[$i];
            $mag1 += $vec1[$i] ** 2;
            $mag2 += $vec2[$i] ** 2;
        }

        $mag1 = sqrt($mag1);
        $mag2 = sqrt($mag2);

        if ($mag1 == 0 || $mag2 == 0) {
            return 0.0;
        }

        return $dotProduct / ($mag1 * $mag2);
    }

    /**
     * Get jurisdiction hierarchy level
     */
    protected function getJurisdictionLevel(string $jurisdiction): int
    {
        $levels = [
            'EU' => 5,
            'HR' => 4, // National
            'regional' => 3,
            'county' => 2,
            'local' => 1,
        ];

        return $levels[strtoupper($jurisdiction)] ?? $levels[$jurisdiction] ?? 0;
    }

    /**
     * Calculate law specificity (0-1, higher = more specific)
     */
    protected function calculateSpecificity(array $law): float
    {
        $score = 0.5; // Base score

        // Longer content = more specific
        $contentLength = strlen($law['content'] ?? '');
        if ($contentLength > 5000) {
            $score += 0.2;
        } elseif ($contentLength > 2000) {
            $score += 0.1;
        }

        // Has chapter/section = more specific
        if (! empty($law['chapter']) || ! empty($law['section'])) {
            $score += 0.2;
        }

        // Multiple tags = more specific subject matter
        $tagCount = is_array($law['tags']) ? count($law['tags']) : 0;
        if ($tagCount > 3) {
            $score += 0.1;
        }

        return min(1.0, $score);
    }

    /**
     * Check if law is most recent among candidates
     */
    protected function isMostRecent(array $law, array $allLaws): bool
    {
        $lawDate = $law['effective_date'] ?? null;
        if (! $lawDate) {
            return false;
        }

        foreach ($allLaws as $other) {
            $otherDate = $other['effective_date'] ?? null;
            if ($otherDate && $otherDate > $lawDate) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if law has express repeal provision
     */
    protected function hasExpressRepeal(array $law): bool
    {
        $content = strtolower($law['content'] ?? '');

        // Look for repeal keywords
        $repealKeywords = ['repeals', 'stavlja van snage', 'ukida', 'prestaje važiti'];

        foreach ($repealKeywords as $keyword) {
            if (str_contains($content, strtolower($keyword))) {
                return true;
            }
        }

        return false;
    }
}

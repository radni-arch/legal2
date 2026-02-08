<?php

namespace App\Services\LegalReasoning;

use App\Models\LegalFactPattern;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Fact Pattern Extractor
 *
 * Extracts structured legal facts from raw narratives provided by users.
 * This is a core component for legal reasoning, converting unstructured
 * text into structured fact patterns that can be analyzed and compared.
 */
class FactPatternExtractor
{
    public function __construct(
        protected OpenAIService $openAI
    ) {}

    /**
     * Extract structured facts from raw narrative text
     *
     * @param  string  $rawNarrative  Raw text describing a legal situation
     * @param  int|null  $userId  User ID for associating the fact pattern
     * @param  array  $options  Options for extraction:
     *                          - save: Whether to save to database (default: false)
     *                          - use_cache: Enable caching (default: true)
     *                          - cache_ttl: Cache TTL in minutes (default: 60)
     * @return array|LegalFactPattern Extracted facts or model if saved
     */
    public function extract(string $rawNarrative, ?int $userId = null, array $options = []): array|LegalFactPattern
    {
        $startTime = microtime(true);

        $save = $options['save'] ?? false;
        $useCache = $options['use_cache'] ?? true;
        $cacheTTL = $options['cache_ttl'] ?? 60;

        Log::info('FactPatternExtractor - Starting extraction', [
            'narrative_length' => mb_strlen($rawNarrative),
            'user_id' => $userId,
            'save' => $save,
        ]);

        // Check cache first
        if ($useCache) {
            $cacheKey = 'fact_pattern:'.md5($rawNarrative);
            $cached = Cache::get($cacheKey);
            if ($cached) {
                Log::info('FactPatternExtractor - Cache hit');

                return $cached;
            }
        }

        // Extract structured facts using LLM
        $extractionResult = $this->extractWithLLM($rawNarrative);

        if (! $extractionResult['success']) {
            Log::error('FactPatternExtractor - Extraction failed', [
                'error' => $extractionResult['error'] ?? 'Unknown error',
            ]);

            return $extractionResult;
        }

        $structuredFacts = $extractionResult['structured_facts'];
        $legalArea = $extractionResult['legal_area'];
        $confidence = $extractionResult['confidence'];

        $result = [
            'success' => true,
            'raw_narrative' => $rawNarrative,
            'structured_facts' => $structuredFacts,
            'legal_area' => $legalArea,
            'extraction_confidence' => $confidence,
            'performance' => [
                'total_time' => round(microtime(true) - $startTime, 3),
            ],
        ];

        // Cache result
        if ($useCache) {
            Cache::put($cacheKey, $result, now()->addMinutes($cacheTTL));
        }

        // Save to database if requested
        if ($save && $userId) {
            $factPattern = LegalFactPattern::create([
                'user_id' => $userId,
                'raw_narrative' => $rawNarrative,
                'structured_facts' => $structuredFacts,
                'legal_area' => $legalArea,
                'extraction_confidence' => $confidence,
            ]);

            Log::info('FactPatternExtractor - Saved to database', [
                'fact_pattern_id' => $factPattern->id,
            ]);

            return $factPattern;
        }

        return $result;
    }

    /**
     * Extract structured facts using LLM
     */
    protected function extractWithLLM(string $narrative): array
    {
        $prompt = $this->buildExtractionPrompt($narrative);

        try {
            $response = $this->openAI->chat([
                'model' => 'gpt-4o',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a legal expert specializing in fact pattern analysis. Extract structured legal facts from narratives in JSON format. Respond ONLY with valid JSON, no markdown formatting.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'temperature' => 0.1,
                'response_format' => ['type' => 'json_object'],
            ]);

            $result = $response['choices'][0]['message']['content'] ?? null;

            if (! $result) {
                return [
                    'success' => false,
                    'error' => 'Empty LLM response',
                ];
            }

            // Clean up response
            $result = $this->cleanJsonResponse($result);

            $parsed = json_decode($result, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning('FactPatternExtractor - Failed to parse LLM response', [
                    'json_error' => json_last_error_msg(),
                    'response' => $result,
                ]);

                return [
                    'success' => false,
                    'error' => 'Failed to parse LLM response: '.json_last_error_msg(),
                ];
            }

            return [
                'success' => true,
                'structured_facts' => $parsed['structured_facts'] ?? [],
                'legal_area' => $parsed['legal_area'] ?? 'unknown',
                'confidence' => $parsed['confidence'] ?? 0.5,
            ];
        } catch (\Exception $e) {
            Log::error('FactPatternExtractor - LLM extraction failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Build prompt for LLM fact extraction
     */
    protected function buildExtractionPrompt(string $narrative): string
    {
        return <<<PROMPT
Analyze this legal narrative and extract structured facts. The narrative describes a legal situation that needs to be analyzed.

**Raw Narrative:**
{$narrative}

**Extract the following information in JSON format:**

```json
{
  "legal_area": "Primary legal area (e.g., contract, tort, criminal, property, family, employment, administrative)",
  "confidence": 0.85,
  "structured_facts": {
    "parties": [
      {
        "name": "Party name or description",
        "role": "plaintiff/defendant/witness/other",
        "type": "individual/corporation/government/other",
        "relevant_details": "Any relevant details about the party"
      }
    ],
    "events": [
      {
        "description": "Description of what happened",
        "date": "Date or time period (if mentioned, otherwise null)",
        "location": "Location (if mentioned, otherwise null)",
        "significance": "Why this event matters legally"
      }
    ],
    "legal_issues": [
      {
        "issue": "Brief description of the legal issue",
        "area_of_law": "Specific area of law",
        "elements": ["Element 1", "Element 2"],
        "potential_claims": ["Claim 1", "Claim 2"]
      }
    ],
    "facts_favorable_to_plaintiff": [
      "Fact that supports plaintiff's position"
    ],
    "facts_favorable_to_defendant": [
      "Fact that supports defendant's position"
    ],
    "disputed_facts": [
      "Facts that are unclear or likely to be contested"
    ],
    "undisputed_facts": [
      "Facts that are clear and unlikely to be contested"
    ],
    "damages_or_relief_sought": {
      "type": "monetary/injunctive/declaratory/specific_performance/other",
      "description": "Description of damages or relief",
      "amount": "Amount if specified, otherwise null"
    },
    "procedural_posture": {
      "stage": "pre-filing/filed/discovery/trial/appeal/settled/other",
      "jurisdiction": "Jurisdiction if mentioned",
      "court": "Court if mentioned",
      "deadlines": ["Any mentioned deadlines"]
    },
    "legal_questions": [
      "Key legal questions that need to be answered"
    ],
    "relevant_laws": [
      {
        "law_type": "statute/regulation/case_law/constitutional",
        "citation": "Citation if mentioned",
        "description": "Brief description"
      }
    ],
    "evidence": [
      {
        "type": "documentary/testimonial/physical/expert/other",
        "description": "Description of evidence",
        "strength": "strong/moderate/weak",
        "availability": "available/needs_discovery/unknown"
      }
    ],
    "timeline": [
      {
        "date": "Date or period",
        "event": "Event description"
      }
    ],
    "key_terms": [
      "Important legal or factual terms used in the narrative"
    ],
    "summary": "2-3 sentence summary of the fact pattern"
  }
}
```

**Instructions:**
- Extract ONLY information explicitly stated or clearly implied in the narrative
- If information is not available, use null or empty arrays []
- Be precise and factual
- For confidence: 0.9+ = very clear facts, 0.7-0.9 = clear facts, 0.5-0.7 = some ambiguity, <0.5 = significant gaps or ambiguity
- Identify the primary legal area based on the nature of the dispute
- Extract all parties, events, and facts in chronological order where possible
- Distinguish between favorable and unfavorable facts for each side
- Identify what facts are likely disputed vs undisputed

Return ONLY the JSON object, no additional text or markdown formatting.
PROMPT;
    }

    /**
     * Clean JSON response from LLM
     */
    protected function cleanJsonResponse(string $response): string
    {
        // Remove markdown code blocks if present
        $response = preg_replace('/```json\s*/', '', $response);
        $response = preg_replace('/```\s*$/', '', $response);

        return trim($response);
    }

    /**
     * Batch extract facts from multiple narratives
     */
    public function batchExtract(array $narratives, ?int $userId = null, array $options = []): array
    {
        $results = [];
        $errors = [];

        foreach ($narratives as $key => $narrative) {
            try {
                $results[$key] = $this->extract($narrative, $userId, $options);
            } catch (\Exception $e) {
                $errors[$key] = $e->getMessage();
                Log::error('FactPatternExtractor - Batch extraction failed', [
                    'key' => $key,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'success' => count($errors) === 0,
            'total' => count($narratives),
            'extracted' => count($results),
            'failed' => count($errors),
            'results' => $results,
            'errors' => $errors,
        ];
    }

    /**
     * Compare two fact patterns for similarity
     */
    public function comparePatterns(LegalFactPattern $pattern1, LegalFactPattern $pattern2): array
    {
        $facts1 = $pattern1->structured_facts;
        $facts2 = $pattern2->structured_facts;

        $similarity = [
            'same_legal_area' => $pattern1->legal_area === $pattern2->legal_area,
            'legal_area_1' => $pattern1->legal_area,
            'legal_area_2' => $pattern2->legal_area,
        ];

        // Compare parties
        $parties1 = data_get($facts1, 'parties', []);
        $parties2 = data_get($facts2, 'parties', []);
        $similarity['party_overlap'] = $this->calculatePartyOverlap($parties1, $parties2);

        // Compare legal issues
        $issues1 = data_get($facts1, 'legal_issues', []);
        $issues2 = data_get($facts2, 'legal_issues', []);
        $similarity['legal_issue_overlap'] = $this->calculateIssueOverlap($issues1, $issues2);

        // Compare events
        $events1 = data_get($facts1, 'events', []);
        $events2 = data_get($facts2, 'events', []);
        $similarity['event_count_1'] = count($events1);
        $similarity['event_count_2'] = count($events2);

        // Overall similarity score
        $similarity['overall_similarity'] = $this->calculateOverallSimilarity($similarity);

        return $similarity;
    }

    /**
     * Calculate party overlap between two fact patterns
     */
    protected function calculatePartyOverlap(array $parties1, array $parties2): float
    {
        if (empty($parties1) || empty($parties2)) {
            return 0.0;
        }

        $names1 = array_map(fn ($p) => strtolower($p['name'] ?? ''), $parties1);
        $names2 = array_map(fn ($p) => strtolower($p['name'] ?? ''), $parties2);

        $intersection = count(array_intersect($names1, $names2));
        $union = count(array_unique(array_merge($names1, $names2)));

        return $union > 0 ? $intersection / $union : 0.0;
    }

    /**
     * Calculate legal issue overlap between two fact patterns
     */
    protected function calculateIssueOverlap(array $issues1, array $issues2): float
    {
        if (empty($issues1) || empty($issues2)) {
            return 0.0;
        }

        $areas1 = array_map(fn ($i) => strtolower($i['area_of_law'] ?? ''), $issues1);
        $areas2 = array_map(fn ($i) => strtolower($i['area_of_law'] ?? ''), $issues2);

        $intersection = count(array_intersect($areas1, $areas2));
        $union = count(array_unique(array_merge($areas1, $areas2)));

        return $union > 0 ? $intersection / $union : 0.0;
    }

    /**
     * Calculate overall similarity score
     */
    protected function calculateOverallSimilarity(array $similarity): float
    {
        $score = 0.0;
        $weights = [
            'same_legal_area' => 0.4,
            'party_overlap' => 0.3,
            'legal_issue_overlap' => 0.3,
        ];

        if ($similarity['same_legal_area']) {
            $score += $weights['same_legal_area'];
        }

        $score += ($similarity['party_overlap'] ?? 0) * $weights['party_overlap'];
        $score += ($similarity['legal_issue_overlap'] ?? 0) * $weights['legal_issue_overlap'];

        return round($score, 3);
    }

    /**
     * Get fact pattern by ID
     */
    public function getFactPattern(string $id): ?LegalFactPattern
    {
        return LegalFactPattern::find($id);
    }

    /**
     * Get all fact patterns for a user
     */
    public function getUserFactPatterns(int $userId, int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return LegalFactPattern::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Search fact patterns by legal area
     */
    public function searchByLegalArea(string $legalArea, int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return LegalFactPattern::where('legal_area', $legalArea)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get fact patterns with high confidence
     */
    public function getHighConfidencePatterns(float $minConfidence = 0.7, int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return LegalFactPattern::where('extraction_confidence', '>=', $minConfidence)
            ->orderBy('extraction_confidence', 'desc')
            ->limit($limit)
            ->get();
    }
}

<?php

namespace App\Services;

use App\Contracts\FactExtractionServiceInterface;
use App\Exceptions\AnalysisException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Legal Fact Extraction Service
 *
 * Extracts structured legal facts from court decisions including:
 * - Parties (plaintiffs, defendants, judges)
 * - Legal issues
 * - Holdings and conclusions
 * - Key arguments
 * - Procedural history
 * - Important dates
 * - Evidence mentioned
 */
class FactExtractionService implements FactExtractionServiceInterface
{
    public function __construct(
        protected OpenAIService $openAI
    ) {}

    /**
     * Extract all legal facts from a court decision
     *
     * @param  string  $decisionId  Court decision document ID
     * @param  array  $options  Extraction options:
     *                          - use_llm: Use LLM for complex fact extraction (default: true)
     *                          - use_cache: Enable caching (default: true)
     *                          - cache_ttl: Cache TTL in minutes (default: 60)
     * @return array Extracted facts
     */
    public function extractFacts(string $decisionId, array $options = []): array
    {
        try {
            Log::info('Fact extraction initiated', [
                'decision_id' => $decisionId,
                'options' => $options,
            ]);

            $startTime = microtime(true);

            if (empty(trim($decisionId))) {
                throw new AnalysisException(
                    'Decision ID is empty',
                    AnalysisException::EXTRACTION_FAILED
                );
            }

            $useLLM = $options['use_llm'] ?? true;
            $useCache = $options['use_cache'] ?? true;
            $cacheTTL = $options['cache_ttl'] ?? 60;

            // Check cache first
            if ($useCache) {
                try {
                    $cacheKey = "fact_extraction:{$decisionId}:".($useLLM ? 'llm' : 'basic');
                    $cached = Cache::get($cacheKey);
                    if ($cached && is_array($cached)) {
                        Log::debug('Fact extraction cache hit', ['decision_id' => $decisionId]);
                        $cached['from_cache'] = true;

                        return $cached;
                    }
                } catch (\Exception $e) {
                    Log::warning('Cache retrieval failed', [
                        'decision_id' => $decisionId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Get decision content and metadata
            $decision = DB::table('court_decision_documents as cdd')
                ->join('court_decisions as cd', 'cdd.decision_id', '=', 'cd.id')
                ->where('cdd.id', $decisionId)
                ->select([
                    'cdd.id',
                    'cdd.decision_id',
                    'cdd.content',
                    'cd.case_number',
                    'cd.title',
                    'cd.court',
                    'cd.jurisdiction',
                    'cd.judge',
                    'cd.decision_date',
                    'cd.publication_date',
                    'cd.decision_type',
                    'cd.ecli',
                    'cd.finality',
                ])
                ->first();

            if (! $decision) {
                Log::warning('Decision not found', ['decision_id' => $decisionId]);

                return [
                    'success' => false,
                    'error' => 'Decision not found',
                    'decision_id' => $decisionId,
                ];
            }

            // Extract basic facts (pattern-based)
            $basicFacts = $this->extractBasicFacts($decision);

            // Extract complex facts using LLM
            $complexFacts = [];
            if ($useLLM && ! empty($decision->content)) {
                try {
                    $complexFacts = $this->extractComplexFactsWithLLM($decision->content, $decision);
                } catch (\Exception $e) {
                    Log::warning('LLM-based fact extraction failed', [
                        'decision_id' => $decisionId,
                        'error' => $e->getMessage(),
                    ]);
                    $complexFacts = [
                        'error' => 'LLM extraction unavailable',
                    ];
                }
            }

            // Combine results
            $result = [
                'success' => true,
                'decision_id' => $decisionId,
                'basic_facts' => $basicFacts,
                'complex_facts' => $complexFacts,
                'extraction_method' => $useLLM ? 'hybrid' : 'pattern-based',
                'performance' => [
                    'total_time' => round(microtime(true) - $startTime, 3),
                ],
                'from_cache' => false,
            ];

            // Cache result
            if ($useCache) {
                try {
                    Cache::put($cacheKey, $result, now()->addMinutes($cacheTTL));
                } catch (\Exception $e) {
                    Log::warning('Failed to cache fact extraction result', [
                        'decision_id' => $decisionId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('Fact extraction completed', [
                'decision_id' => $decisionId,
                'method' => $result['extraction_method'],
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return $result;

        } catch (AnalysisException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Fact extraction failed', [
                'decision_id' => $decisionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new AnalysisException(
                'Fact extraction failed: '.$e->getMessage(),
                AnalysisException::EXTRACTION_FAILED,
                $e
            );
        }
    }

    /**
     * Extract basic facts using pattern matching and metadata
     */
    protected function extractBasicFacts($decision): array
    {
        try {
            return [
                'case_metadata' => [
                    'case_number' => $decision->case_number ?? null,
                    'court' => $decision->court ?? null,
                    'jurisdiction' => $decision->jurisdiction ?? null,
                    'decision_type' => $decision->decision_type ?? null,
                    'ecli' => $decision->ecli ?? null,
                    'finality' => $decision->finality ?? null,
                ],
                'parties' => $this->extractParties($decision),
                'dates' => $this->extractDates($decision),
                'procedural_posture' => $this->extractProceduralPosture($decision),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to extract basic facts', [
                'decision_id' => $decision->id ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            return [
                'case_metadata' => [],
                'parties' => [],
                'dates' => [],
                'procedural_posture' => [],
                'error' => 'Basic fact extraction failed',
            ];
        }
    }

    /**
     * Extract party information from decision
     */
    protected function extractParties($decision): array
    {
        try {
            $parties = [
                'judge' => $decision->judge ?? null,
                'plaintiffs' => [],
                'defendants' => [],
                'other_parties' => [],
            ];

            $content = $decision->content ?? '';

            if (empty($content)) {
                return $parties;
            }

            // Croatian party patterns
            $patterns = [
                'plaintiff' => [
                    '/tužitelj[i]?[:\s]+([^,\n\.]{3,100})/iu',
                    '/predlagatelj[i]?[:\s]+([^,\n\.]{3,100})/iu',
                    '/žalitelj[i]?[:\s]+([^,\n\.]{3,100})/iu',
                ],
                'defendant' => [
                    '/tuženik[i]?[:\s]+([^,\n\.]{3,100})/iu',
                    '/protivnik[i]?[:\s]+([^,\n\.]{3,100})/iu',
                    '/protivstranka[:\s]+([^,\n\.]{3,100})/iu',
                ],
            ];

            // Extract plaintiffs
            foreach ($patterns['plaintiff'] as $pattern) {
                try {
                    if (preg_match_all($pattern, $content, $matches)) {
                        foreach ($matches[1] as $match) {
                            $cleaned = trim($match);
                            if (! empty($cleaned) && mb_strlen($cleaned) > 3) {
                                $parties['plaintiffs'][] = $cleaned;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    Log::debug('Pattern matching failed for plaintiffs', ['error' => $e->getMessage()]);
                }
            }

            // Extract defendants
            foreach ($patterns['defendant'] as $pattern) {
                try {
                    if (preg_match_all($pattern, $content, $matches)) {
                        foreach ($matches[1] as $match) {
                            $cleaned = trim($match);
                            if (! empty($cleaned) && mb_strlen($cleaned) > 3) {
                                $parties['defendants'][] = $cleaned;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    Log::debug('Pattern matching failed for defendants', ['error' => $e->getMessage()]);
                }
            }

            // Deduplicate
            $parties['plaintiffs'] = array_values(array_unique($parties['plaintiffs']));
            $parties['defendants'] = array_values(array_unique($parties['defendants']));

            return $parties;

        } catch (\Exception $e) {
            Log::warning('Party extraction failed', [
                'decision_id' => $decision->id ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            return [
                'judge' => $decision->judge ?? null,
                'plaintiffs' => [],
                'defendants' => [],
                'other_parties' => [],
            ];
        }
    }

    /**
     * Extract important dates from decision
     */
    protected function extractDates($decision): array
    {
        return [
            'decision_date' => $decision->decision_date,
            'publication_date' => $decision->publication_date,
            'filing_date' => $this->extractFilingDate($decision->content ?? ''),
            'hearing_dates' => $this->extractHearingDates($decision->content ?? ''),
        ];
    }

    /**
     * Extract filing date from content
     */
    protected function extractFilingDate(string $content): ?string
    {
        // Pattern for filing date: "podnesen(a) dana" followed by date
        $patterns = [
            '/podnesen[a]?\s+(?:dana\s+)?(\d{1,2}\.\s*\d{1,2}\.\s*\d{4})/iu',
            '/podnesak\s+od\s+(\d{1,2}\.\s*\d{1,2}\.\s*\d{4})/iu',
            '/tužba\s+od\s+(\d{1,2}\.\s*\d{1,2}\.\s*\d{4})/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $match)) {
                return $this->normalizeDate($match[1]);
            }
        }

        return null;
    }

    /**
     * Extract hearing dates from content
     */
    protected function extractHearingDates(string $content): array
    {
        $dates = [];

        // Pattern for hearing dates
        $patterns = [
            '/rasprav[a]?\s+(?:održana\s+)?(?:dana\s+)?(\d{1,2}\.\s*\d{1,2}\.\s*\d{4})/iu',
            '/ročišt[e]?\s+(?:održano\s+)?(?:dana\s+)?(\d{1,2}\.\s*\d{1,2}\.\s*\d{4})/iu',
            '/saslušanj[e]?\s+(?:održano\s+)?(?:dana\s+)?(\d{1,2}\.\s*\d{1,2}\.\s*\d{4})/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $content, $matches)) {
                foreach ($matches[1] as $match) {
                    $normalized = $this->normalizeDate($match);
                    if ($normalized) {
                        $dates[] = $normalized;
                    }
                }
            }
        }

        return array_values(array_unique($dates));
    }

    /**
     * Normalize Croatian date format to ISO 8601
     */
    protected function normalizeDate(string $date): ?string
    {
        // Remove extra spaces
        $date = preg_replace('/\s+/', '', $date);

        // Parse DD.MM.YYYY format
        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $date, $match)) {
            try {
                $dateObj = \DateTime::createFromFormat('d.m.Y', $date);
                if ($dateObj) {
                    return $dateObj->format('Y-m-d');
                }
            } catch (\Exception $e) {
                return null;
            }
        }

        return null;
    }

    /**
     * Extract procedural posture information
     */
    protected function extractProceduralPosture($decision): array
    {
        $content = $decision->content ?? '';

        return [
            'is_appeal' => $this->isAppeal($content, $decision->decision_type),
            'is_revision' => $this->isRevision($content, $decision->decision_type),
            'is_first_instance' => $this->isFirstInstance($content, $decision->decision_type),
            'prior_proceedings' => $this->extractPriorProceedings($content),
        ];
    }

    /**
     * Check if decision is an appeal
     */
    protected function isAppeal(string $content, ?string $decisionType): bool
    {
        if ($decisionType && stripos($decisionType, 'žalba') !== false) {
            return true;
        }

        $patterns = [
            '/žalb[a]?\s+protiv/iu',
            '/povod[om]?\s+žalb[e]/iu',
            '/izjavljena\s+žalba/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if decision is a revision
     */
    protected function isRevision(string $content, ?string $decisionType): bool
    {
        if ($decisionType && stripos($decisionType, 'revizija') !== false) {
            return true;
        }

        return (bool) preg_match('/revizij[a]?\s+protiv/iu', $content);
    }

    /**
     * Check if decision is first instance
     */
    protected function isFirstInstance(string $content, ?string $decisionType): bool
    {
        if ($decisionType && stripos($decisionType, 'prvostupanj') !== false) {
            return true;
        }

        $patterns = [
            '/(?:u\s+)?prvom\s+stupnju/iu',
            '/prvostupanjsk[a]?\s+odluka/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract information about prior proceedings
     */
    protected function extractPriorProceedings(string $content): array
    {
        $priorCases = [];

        // Look for references to lower court decisions
        $patterns = [
            '/prvostepenskom\s+presudom.*?(?:broj[a]?|br\.?)\s*([^\s,\.]{5,30})/iu',
            '/odlukom.*?(?:broj[a]?|br\.?)\s*([^\s,\.]{5,30})/iu',
            '/presudom.*?(?:broj[a]?|br\.?)\s*([^\s,\.]{5,30})/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $content, $matches)) {
                foreach ($matches[1] as $match) {
                    $cleaned = trim($match);
                    if (! empty($cleaned)) {
                        $priorCases[] = $cleaned;
                    }
                }
            }
        }

        return array_values(array_unique($priorCases));
    }

    /**
     * Extract complex facts using LLM
     */
    protected function extractComplexFactsWithLLM(string $content, $decision): array
    {
        // Limit content length for LLM processing (use first 8000 characters)
        $contentSample = mb_substr($content, 0, 8000);

        $prompt = $this->buildFactExtractionPrompt($contentSample, $decision);

        try {
            $response = $this->openAI->chat(
                [
                    [
                        'role' => 'system',
                        'content' => 'You are a legal expert analyzing Croatian court decisions. Extract structured legal facts in JSON format. Respond ONLY with valid JSON, no markdown formatting.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'gpt-4o',
                [
                    'temperature' => 0.1,
                    'response_format' => ['type' => 'json_object'],
                ]
            );

            $result = $response['choices'][0]['message']['content'] ?? null;

            if ($result) {
                // Clean up response (remove markdown code blocks if present)
                $result = preg_replace('/```json\s*/', '', $result);
                $result = preg_replace('/```\s*$/', '', $result);
                $result = trim($result);

                $facts = json_decode($result, true);

                if (json_last_error() === JSON_ERROR_NONE && is_array($facts)) {
                    return $facts;
                } else {
                    Log::warning('Failed to parse LLM fact extraction response', [
                        'json_error' => json_last_error_msg(),
                        'response' => $result,
                    ]);

                    return ['error' => 'Failed to parse LLM response'];
                }
            }

            return ['error' => 'Empty LLM response'];
        } catch (\Exception $e) {
            Log::error('LLM fact extraction failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Build prompt for LLM fact extraction
     */
    protected function buildFactExtractionPrompt(string $content, $decision): string
    {
        return <<<PROMPT
Analyze this Croatian court decision and extract the following information in JSON format:

**Case Information:**
- Case Number: {$decision->case_number}
- Court: {$decision->court}
- Decision Type: {$decision->decision_type}

**Content (sample):**
{$content}

**Extract the following facts in JSON format:**

```json
{
  "legal_issues": [
    "Brief description of each main legal issue"
  ],
  "holding": "Main holding or conclusion of the court (1-2 sentences)",
  "key_findings": [
    "Important factual findings by the court"
  ],
  "legal_grounds": [
    "Legal provisions or principles cited as basis for decision"
  ],
  "arguments": {
    "plaintiff": [
      "Main arguments of plaintiff/appellant"
    ],
    "defendant": [
      "Main arguments of defendant/respondent"
    ]
  },
  "relief_sought": "What relief was sought (e.g., damages, specific performance)",
  "relief_granted": "What relief was granted by the court",
  "standard_of_review": "Standard of review applied (if appeal)",
  "key_evidence": [
    "Important evidence mentioned"
  ],
  "dissent": "Whether there was a dissenting opinion (true/false)",
  "summary": "Brief 2-3 sentence summary of the decision"
}
```

Rules:
- Extract only information explicitly stated in the content
- Use Croatian language for extracted text
- If information is not available, use empty arrays [] or empty strings ""
- Keep descriptions concise and factual
- Focus on legally significant facts

Return ONLY the JSON object, no additional text or markdown formatting.
PROMPT;
    }

    /**
     * Compare facts between two decisions
     */
    public function compareDecisionFacts(string $decisionId1, string $decisionId2): array
    {
        try {
            Log::info('Decision fact comparison initiated', [
                'decision1' => $decisionId1,
                'decision2' => $decisionId2,
            ]);

            $startTime = microtime(true);

            if (empty(trim($decisionId1)) || empty(trim($decisionId2))) {
                throw new AnalysisException(
                    'One or both decision IDs are empty',
                    AnalysisException::EXTRACTION_FAILED
                );
            }

            $facts1 = $this->extractFacts($decisionId1);
            $facts2 = $this->extractFacts($decisionId2);

            if (! ($facts1['success'] ?? false) || ! ($facts2['success'] ?? false)) {
                Log::warning('Failed to extract facts for comparison', [
                    'decision1_success' => $facts1['success'] ?? false,
                    'decision2_success' => $facts2['success'] ?? false,
                ]);

                return [
                    'success' => false,
                    'error' => 'Failed to extract facts from one or both decisions',
                    'decision1' => ['id' => $decisionId1],
                    'decision2' => ['id' => $decisionId2],
                ];
            }

            // Compare basic facts
            $basicSimilarity = $this->compareBasicFacts(
                $facts1['basic_facts'] ?? [],
                $facts2['basic_facts'] ?? []
            );

            // Compare complex facts if available
            $complexSimilarity = [];
            if (isset($facts1['complex_facts']) && isset($facts2['complex_facts'])) {
                $complexSimilarity = $this->compareComplexFacts(
                    $facts1['complex_facts'],
                    $facts2['complex_facts']
                );
            }

            Log::info('Decision fact comparison completed', [
                'decision1' => $decisionId1,
                'decision2' => $decisionId2,
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return [
                'success' => true,
                'decision1' => [
                    'id' => $decisionId1,
                    'case_number' => $facts1['basic_facts']['case_metadata']['case_number'] ?? null,
                ],
                'decision2' => [
                    'id' => $decisionId2,
                    'case_number' => $facts2['basic_facts']['case_metadata']['case_number'] ?? null,
                ],
                'basic_similarity' => $basicSimilarity,
                'complex_similarity' => $complexSimilarity,
            ];

        } catch (AnalysisException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Decision fact comparison failed', [
                'decision1' => $decisionId1,
                'decision2' => $decisionId2,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new AnalysisException(
                'Decision fact comparison failed: '.$e->getMessage(),
                AnalysisException::EXTRACTION_FAILED,
                $e
            );
        }
    }

    /**
     * Compare basic facts between decisions
     */
    protected function compareBasicFacts(array $facts1, array $facts2): array
    {
        return [
            'same_court' => ($facts1['case_metadata']['court'] ?? null) === ($facts2['case_metadata']['court'] ?? null),
            'same_jurisdiction' => ($facts1['case_metadata']['jurisdiction'] ?? null) === ($facts2['case_metadata']['jurisdiction'] ?? null),
            'same_decision_type' => ($facts1['case_metadata']['decision_type'] ?? null) === ($facts2['case_metadata']['decision_type'] ?? null),
            'same_procedural_posture' => $facts1['procedural_posture'] === $facts2['procedural_posture'],
            'shared_parties' => $this->findSharedParties($facts1['parties'], $facts2['parties']),
        ];
    }

    /**
     * Find shared parties between two decisions
     */
    protected function findSharedParties(array $parties1, array $parties2): array
    {
        $shared = [
            'plaintiffs' => array_values(array_intersect(
                $parties1['plaintiffs'] ?? [],
                $parties2['plaintiffs'] ?? []
            )),
            'defendants' => array_values(array_intersect(
                $parties1['defendants'] ?? [],
                $parties2['defendants'] ?? []
            )),
        ];

        return $shared;
    }

    /**
     * Compare complex facts between decisions
     */
    protected function compareComplexFacts(array $facts1, array $facts2): array
    {
        if (isset($facts1['error']) || isset($facts2['error'])) {
            return ['error' => 'Complex facts not available for comparison'];
        }

        // Compare legal issues
        $sharedIssues = array_intersect(
            $facts1['legal_issues'] ?? [],
            $facts2['legal_issues'] ?? []
        );

        // Compare legal grounds
        $sharedGrounds = array_intersect(
            $facts1['legal_grounds'] ?? [],
            $facts2['legal_grounds'] ?? []
        );

        return [
            'shared_legal_issues' => array_values($sharedIssues),
            'shared_legal_grounds' => array_values($sharedGrounds),
            'similar_relief_sought' => $this->areSimilar(
                $facts1['relief_sought'] ?? '',
                $facts2['relief_sought'] ?? ''
            ),
            'similar_holdings' => $this->areSimilar(
                $facts1['holding'] ?? '',
                $facts2['holding'] ?? ''
            ),
        ];
    }

    /**
     * Check if two strings are similar (basic similarity check)
     */
    protected function areSimilar(string $str1, string $str2, float $threshold = 0.7): bool
    {
        if (empty($str1) || empty($str2)) {
            return false;
        }

        similar_text(mb_strtolower($str1), mb_strtolower($str2), $percent);

        return ($percent / 100) >= $threshold;
    }

    /**
     * Extract facts from multiple decisions in batch
     */
    public function batchExtractFacts(array $decisionIds, array $options = []): array
    {
        try {
            Log::info('Batch fact extraction initiated', [
                'total_decisions' => count($decisionIds),
                'options' => $options,
            ]);

            $startTime = microtime(true);

            if (empty($decisionIds)) {
                Log::warning('Batch fact extraction received empty decision IDs array');

                return [
                    'success' => true,
                    'total' => 0,
                    'extracted' => 0,
                    'failed' => 0,
                    'results' => [],
                    'errors' => [],
                ];
            }

            $results = [];
            $errors = [];
            $failedCount = 0;
            $extractedCount = 0;

            foreach ($decisionIds as $index => $decisionId) {
                if (! is_string($decisionId) || empty(trim($decisionId))) {
                    $failedCount++;
                    $errors["index_{$index}"] = 'Invalid decision ID';
                    Log::debug('Skipping invalid decision ID', ['index' => $index]);

                    continue;
                }

                try {
                    $result = $this->extractFacts($decisionId, $options);
                    $results[$decisionId] = $result;

                    // Count as failed if success is false
                    if (! ($result['success'] ?? true)) {
                        $failedCount++;
                        $errors[$decisionId] = $result['error'] ?? 'Unknown error';
                    } else {
                        $extractedCount++;
                    }
                } catch (\Exception $e) {
                    $failedCount++;
                    $errors[$decisionId] = $e->getMessage();
                    Log::warning('Batch fact extraction failed for decision', [
                        'decision_id' => $decisionId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('Batch fact extraction completed', [
                'total' => count($decisionIds),
                'extracted' => $extractedCount,
                'failed' => $failedCount,
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return [
                'success' => $failedCount === 0,
                'total' => count($decisionIds),
                'extracted' => $extractedCount,
                'failed' => $failedCount,
                'results' => $results,
                'errors' => $errors,
            ];

        } catch (\Exception $e) {
            Log::error('Batch fact extraction failed completely', [
                'total_decisions' => count($decisionIds),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new AnalysisException(
                'Batch fact extraction failed: '.$e->getMessage(),
                AnalysisException::EXTRACTION_FAILED,
                $e
            );
        }
    }
}

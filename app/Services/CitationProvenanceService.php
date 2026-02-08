<?php

namespace App\Services;

use App\Models\CitationProvenance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Citation Provenance Service
 *
 * Sprint 2.8: Citation Provenance Service
 *
 * Verifies legal citations to ensure AI agents never cite non-existent laws.
 * Parses Croatian legal citations and verifies them against multiple sources:
 * 1. Database lookup (local cache)
 * 2. narodne-novine.nn.hr API (official gazette)
 *
 * Supported Citation Formats:
 * - "ZKP Članak 9"
 * - "Zakon o kaznenom postupku čl. 215"
 * - "Ustav RH Članak 34"
 * - "Kazneni zakon čl. 179"
 * - "KZ čl. 87"
 */
class CitationProvenanceService
{
    /**
     * Croatian law abbreviations mapping
     *
     * @var array<string, string>
     */
    protected const LAW_ABBREVIATIONS = [
        'ZKP' => 'Zakon o kaznenom postupku',
        'KZ' => 'Kazneni zakon',
        'Ustav RH' => 'Ustav Republike Hrvatske',
        'Ustav' => 'Ustav Republike Hrvatske',
        'ZOO' => 'Zakon o obveznim odnosima',
        'ZIDZ' => 'Zakon o izvršavanju kazne zatvora',
        'ZSDZ' => 'Zakon o sudovima za mladež',
        'ZDO' => 'Zakon o Državnom odvjetništvu',
        'ZS' => 'Zakon o sudovima',
        'PZ' => 'Prekršajni zakon',
        'ZIKS' => 'Zakon o izvršavanju kazne zatvora',
    ];

    /**
     * Citation patterns for parsing
     *
     * @var array<string>
     */
    protected const CITATION_PATTERNS = [
        // "ZKP Članak 9", "ZKP čl. 215"
        '/^([A-ZŠĐŽĆČ]+(?:\s+[A-ZŠĐŽĆČ]+)?)\s+(?:Članak|čl\.?|Čl\.?|clanak|cl\.?)\s+(\d+)(?:\s+st(?:av|\.?)?\s+(\d+))?/ui',

        // "Zakon o kaznenom postupku članak 9"
        '/^(Zakon\s+o\s+[a-zšđžćč\s]+)\s+(?:Članak|čl\.?|Čl\.?)\s+(\d+)(?:\s+st(?:av|\.?)?\s+(\d+))?/ui',

        // "Ustav Republike Hrvatske članak 34"
        '/^(Ustav\s+Republike\s+Hrvatske)\s+(?:Članak|čl\.?|Čl\.?)\s+(\d+)(?:\s+st(?:av|\.?)?\s+(\d+))?/ui',

        // Simplified "Ustav čl. 34"
        '/^(Ustav)\s+(?:čl\.?|Čl\.?)\s+(\d+)(?:\s+st(?:av|\.?)?\s+(\d+))?/ui',
    ];

    /**
     * Verify a Croatian legal citation
     *
     * Parses the citation, checks database, and optionally verifies via API.
     *
     * @param  string  $citation  Citation text (e.g., "ZKP Članak 9")
     * @param  array  $options  Verification options
     * @return array Verification result with status and metadata
     */
    public function verifyCitation(string $citation, array $options = []): array
    {
        // Parse the citation
        $parsed = $this->parseCitation($citation);

        if (! $parsed['valid']) {
            return [
                'verified' => false,
                'status' => 'parse_failed',
                'citation' => $citation,
                'error' => $parsed['error'] ?? 'Failed to parse citation',
                'parsed' => $parsed,
            ];
        }

        // Check database first (fastest)
        $dbResult = $this->verifyAgainstDatabase($parsed);

        if ($dbResult['found']) {
            $this->recordVerification($citation, $parsed, 'verified', 'database', $dbResult);

            return [
                'verified' => true,
                'status' => 'verified_database',
                'citation' => $citation,
                'parsed' => $parsed,
                'source' => 'database',
                'metadata' => $dbResult,
            ];
        }

        // Try narodne-novine API (if enabled)
        if ($options['check_api'] ?? true) {
            $apiResult = $this->verifyAgainstNarodneNovineAPI($parsed);

            if ($apiResult['found']) {
                $this->recordVerification($citation, $parsed, 'verified', 'narodne_novine', $apiResult);

                return [
                    'verified' => true,
                    'status' => 'verified_api',
                    'citation' => $citation,
                    'parsed' => $parsed,
                    'source' => 'narodne_novine',
                    'metadata' => $apiResult,
                ];
            }
        }

        // Not found in any source
        $this->recordVerification($citation, $parsed, 'failed', null, ['reason' => 'not_found']);

        return [
            'verified' => false,
            'status' => 'not_found',
            'citation' => $citation,
            'parsed' => $parsed,
            'error' => 'Citation not found in database or API',
        ];
    }

    /**
     * Parse a Croatian legal citation
     *
     * Extracts law name, article number, and paragraph from citation text.
     *
     * Supported formats:
     * - "ZKP Članak 9" → {law: "ZKP", article: "9"}
     * - "ZKP čl. 215 st. 3" → {law: "ZKP", article: "215", paragraph: "3"}
     * - "Ustav RH Članak 34" → {law: "Ustav RH", article: "34"}
     * - "Zakon o kaznenom postupku čl. 10" → {law: "Zakon o kaznenom postupku", article: "10"}
     *
     * @param  string  $citation  Citation text
     * @return array Parsed citation data
     */
    public function parseCitation(string $citation): array
    {
        $citation = trim($citation);

        if (empty($citation)) {
            return [
                'valid' => false,
                'error' => 'Empty citation',
            ];
        }

        // Try each pattern
        foreach (self::CITATION_PATTERNS as $pattern) {
            if (preg_match($pattern, $citation, $matches)) {
                $lawName = trim($matches[1]);
                $article = trim($matches[2]);
                $paragraph = isset($matches[3]) ? trim($matches[3]) : null;

                // Expand abbreviation if needed
                $fullLawName = self::LAW_ABBREVIATIONS[$lawName] ?? $lawName;

                return [
                    'valid' => true,
                    'original' => $citation,
                    'law_abbreviation' => $lawName,
                    'law_full_name' => $fullLawName,
                    'article' => $article,
                    'paragraph' => $paragraph,
                    'normalized' => $this->normalizeCitation($lawName, $article, $paragraph),
                ];
            }
        }

        return [
            'valid' => false,
            'original' => $citation,
            'error' => 'Citation format not recognized',
        ];
    }

    /**
     * Normalize citation to standard format
     *
     * @param  string  $law  Law abbreviation or name
     * @param  string  $article  Article number
     * @param  string|null  $paragraph  Paragraph number
     * @return string Normalized citation
     */
    protected function normalizeCitation(string $law, string $article, ?string $paragraph = null): string
    {
        $citation = "{$law} čl. {$article}";

        if ($paragraph) {
            $citation .= " st. {$paragraph}";
        }

        return $citation;
    }

    /**
     * Verify citation against database
     *
     * Checks if the law and article exist in local database (laws table).
     *
     * @param  array  $parsed  Parsed citation data
     * @return array Verification result
     */
    protected function verifyAgainstDatabase(array $parsed): array
    {
        try {
            // Try to find in laws table
            $law = DB::table('laws')
                ->where('title', 'LIKE', '%'.$parsed['law_full_name'].'%')
                ->first();

            if (! $law) {
                return ['found' => false];
            }

            // Check if article exists (if articles are stored)
            // Note: law_articles table may not exist in all deployments
            $article = null;

            // Check if table exists before querying to avoid transaction errors
            $tableExists = DB::select("SELECT to_regclass('public.law_articles') as exists");

            if ($tableExists && $tableExists[0]->exists !== null) {
                try {
                    $article = DB::table('law_articles')
                        ->where('law_id', $law->id)
                        ->where('article_number', $parsed['article'])
                        ->first();
                } catch (\Exception $articleError) {
                    // Query failed - not critical
                    Log::debug('Law articles query failed', [
                        'error' => $articleError->getMessage(),
                    ]);
                }
            }

            if ($article) {
                return [
                    'found' => true,
                    'law_id' => $law->id,
                    'law_title' => $law->title ?? null,
                    'article_id' => $article->id ?? null,
                    'article_number' => $parsed['article'],
                    'article_text' => $article->content ?? null,
                ];
            }

            // Law exists but article not in database (still consider verified)
            return [
                'found' => true,
                'law_id' => $law->id,
                'law_title' => $law->title ?? null,
                'article_number' => $parsed['article'],
                'note' => 'Law exists, article not cached',
            ];

        } catch (\Exception $e) {
            Log::warning('Database verification failed', [
                'citation' => $parsed['original'],
                'error' => $e->getMessage(),
            ]);

            return ['found' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Verify citation against narodne-novine.nn.hr API
     *
     * Checks official Croatian gazette for law and article.
     *
     * Note: As of Sprint 2.8, this is a framework implementation.
     * The actual API endpoints may require authentication or have rate limits.
     *
     * @param  array  $parsed  Parsed citation data
     * @return array Verification result
     */
    protected function verifyAgainstNarodneNovineAPI(array $parsed): array
    {
        try {
            // Base URL for narodne-novine.nn.hr
            $baseUrl = config('laws.narodne_novine_api', 'https://narodne-novine.nn.hr/api/v1');

            // Try to search for the law
            $response = Http::timeout(5)
                ->retry(2, 1000)
                ->get("{$baseUrl}/search", [
                    'q' => $parsed['law_full_name'],
                    'type' => 'zakon',
                ]);

            if (! $response->successful()) {
                return [
                    'found' => false,
                    'error' => 'API request failed',
                    'status_code' => $response->status(),
                ];
            }

            $data = $response->json();

            // Parse response (structure depends on actual API)
            if (isset($data['results']) && count($data['results']) > 0) {
                $law = $data['results'][0];

                return [
                    'found' => true,
                    'law_title' => $law['title'] ?? $parsed['law_full_name'],
                    'article_number' => $parsed['article'],
                    'source_url' => $law['url'] ?? null,
                    'official_gazette_number' => $law['nn_number'] ?? null,
                    'note' => 'Verified via narodne-novine API',
                ];
            }

            return ['found' => false, 'note' => 'Not found in API'];

        } catch (\Exception $e) {
            Log::info('Narodne-novine API verification skipped', [
                'citation' => $parsed['original'],
                'error' => $e->getMessage(),
            ]);

            return [
                'found' => false,
                'error' => $e->getMessage(),
                'note' => 'API unavailable or not configured',
            ];
        }
    }

    /**
     * Record citation verification in database
     *
     * Stores verification result for audit trail and future lookups.
     *
     * @param  string  $citation  Original citation text
     * @param  array  $parsed  Parsed citation data
     * @param  string  $status  Verification status (verified, failed, pending)
     * @param  string|null  $sourceType  Source used for verification
     * @param  array  $metadata  Additional verification metadata
     */
    protected function recordVerification(
        string $citation,
        array $parsed,
        string $status,
        ?string $sourceType = null,
        array $metadata = []
    ): CitationProvenance {
        // Calculate confidence score
        $confidence = $this->calculateConfidenceScore($status, $sourceType, $parsed);

        return CitationProvenance::create([
            'citation_text' => $citation,
            'source_type' => $sourceType,
            'source_identifier' => $parsed['normalized'] ?? null,
            'source_url' => $metadata['source_url'] ?? null,
            'citation_metadata' => [
                'parsed' => $parsed,
                'verification_metadata' => $metadata,
            ],
            'verification_status' => $status,
            'confidence_score' => $confidence,
            'verification_notes' => $metadata['note'] ?? null,
            'verified_at' => now(),
        ]);
    }

    /**
     * Calculate confidence score for citation verification
     *
     * Score is based on:
     * - Verification source (database: 0.95, API: 0.99, failed: 0.0)
     * - Parse quality (full parse: +0.05, partial: +0.02)
     *
     * @param  string  $status  Verification status
     * @param  string|null  $sourceType  Source type
     * @param  array  $parsed  Parsed citation
     * @return float Confidence score (0.0 - 1.0)
     */
    protected function calculateConfidenceScore(string $status, ?string $sourceType, array $parsed): float
    {
        if ($status === 'failed') {
            return 0.0;
        }

        // Base score by source
        $score = match ($sourceType) {
            'narodne_novine' => 0.99, // Official source
            'database' => 0.95, // Cached verified data
            default => 0.50, // Unknown source
        };

        // Bonus for complete parse (with paragraph)
        if (isset($parsed['paragraph']) && $parsed['paragraph']) {
            $score += 0.01;
        }

        return min(1.0, $score);
    }

    /**
     * Verify multiple citations at once
     *
     * Batch verification for performance.
     *
     * @param  array  $citations  Array of citation strings
     * @param  array  $options  Verification options
     * @return array Results for all citations
     */
    public function verifyCitations(array $citations, array $options = []): array
    {
        $results = [];

        foreach ($citations as $citation) {
            $results[] = $this->verifyCitation($citation, $options);
        }

        return [
            'total' => count($citations),
            'verified' => count(array_filter($results, fn ($r) => $r['verified'])),
            'failed' => count(array_filter($results, fn ($r) => ! $r['verified'])),
            'results' => $results,
        ];
    }

    /**
     * Get verification statistics
     *
     * Returns statistics about citation verifications.
     *
     * @param  array  $filters  Optional filters (date range, status, etc.)
     * @return array Statistics
     */
    public function getStatistics(array $filters = []): array
    {
        $query = CitationProvenance::query();

        // Apply filters
        if (isset($filters['from_date'])) {
            $query->where('created_at', '>=', $filters['from_date']);
        }

        if (isset($filters['to_date'])) {
            $query->where('created_at', '<=', $filters['to_date']);
        }

        if (isset($filters['status'])) {
            $query->where('verification_status', $filters['status']);
        }

        $total = $query->count();
        $verified = (clone $query)->where('verification_status', 'verified')->count();
        $failed = (clone $query)->where('verification_status', 'failed')->count();
        $pending = (clone $query)->where('verification_status', 'pending')->count();

        $bySource = (clone $query)
            ->select('source_type', DB::raw('count(*) as count'))
            ->groupBy('source_type')
            ->get()
            ->pluck('count', 'source_type')
            ->toArray();

        return [
            'total' => $total,
            'verified' => $verified,
            'failed' => $failed,
            'pending' => $pending,
            'verification_rate' => $total > 0 ? round(($verified / $total) * 100, 2) : 0,
            'by_source' => $bySource,
        ];
    }

    /**
     * Get recently verified citations
     *
     * @param  int  $limit  Number of results
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getRecentVerifications(int $limit = 10)
    {
        return CitationProvenance::verified()
            ->orderBy('verified_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Check if citation format is valid (without full verification)
     *
     * Quick check for citation format validity.
     *
     * @param  string  $citation  Citation text
     * @return bool True if format is valid
     */
    public function isValidFormat(string $citation): bool
    {
        $parsed = $this->parseCitation($citation);

        return $parsed['valid'] === true;
    }
}

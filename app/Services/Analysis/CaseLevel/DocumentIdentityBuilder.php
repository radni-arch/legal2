<?php

namespace App\Services\Analysis\CaseLevel;

use App\Models\DocumentAnalysis;

/**
 * Builds the document identity matrix for a case.
 *
 * This service assembles document identities from extraction results,
 * detecting which documents are present vs missing based on:
 * - Case number suffixes (gaps in sequence = missing documents)
 * - KLASA/URBROJ references
 * - Cross-document references
 *
 * @see \App\Models\DocumentIdentity
 */
class DocumentIdentityBuilder
{
    /**
     * Character position threshold separating document header from body.
     * References found at positions below this value are considered the document's
     * own identifiers. References above this are considered cross-references to
     * other documents.
     */
    private const HEADER_THRESHOLD = 500;

    /**
     * Map of case number prefixes to procedural roles.
     */
    private const PREFIX_ROLE_MAP = [
        'K' => 'main_criminal',
        'Pp Prz' => 'search_warrant',
        'Kv' => 'detention',
        'Kz' => 'appeal_criminal',
        'KIR' => 'investigation',
        'KO' => 'criminal_other',
        'KIO' => 'criminal_investigation_other',
        'Kov' => 'criminal_enforcement',
        'Kis' => 'criminal_investigation',
        'Pp J' => 'misdemeanor_judgment',
        'Pn' => 'misdemeanor',
        'DO' => 'prosecution',
        'KP' => 'prosecution',
        'KP-DO' => 'prosecution',
        'Kis-DO' => 'prosecution_investigation',
        'P' => 'civil',
        'Us' => 'administrative',
        'Su' => 'court_administration',
    ];

    /**
     * Map of URBROJ numeric prefixes to institutions.
     */
    private const URBROJ_INSTITUTION_MAP = [
        '511' => 'MUP (Ministarstvo unutarnjih poslova)',
        '2158' => 'Sud - Osijek',
        '2168' => 'Sud - Zagreb',
    ];

    /**
     * Map of prefix + suffix=1 to specific document types.
     */
    private const PREFIX_DOC_TYPE_MAP = [
        'Pp Prz' => 'search_warrant',
        'Kv' => 'detention_order',
        'K' => 'indictment',
    ];

    /**
     * Build the complete identity matrix for a case.
     *
     * @param string $caseId The case identifier
     * @return array{
     *     total_identities: int,
     *     present: int,
     *     missing: int,
     *     by_case_number: array<string, array{
     *         document_id: ?string,
     *         status: string,
     *         klasa: ?string,
     *         urbroj: ?string,
     *         suffix: ?int,
     *         base_case_number: ?string,
     *         institution: ?string,
     *         role: ?string,
     *         inference_reason: ?string,
     *     }>,
     *     by_klasa: array<string, array{
     *         urbroj: ?string,
     *         case_numbers: string[],
     *         document_ids: string[],
     *     }>,
     *     processing_time_seconds: float
     * }
     */
    public function build(string $caseId): array
    {
        $startTime = microtime(true);

        // Load completed case_references analyses for documents in this case
        $analyses = $this->loadCaseReferenceAnalyses($caseId);

        $byCaseNumber = [];
        $byKlasa = [];
        $allReferencedCaseNumbers = [];

        foreach ($analyses as $analysis) {
            $results = $analysis->results ?? [];
            $documentId = $analysis->case_document_id;

            // Extract header-position identifiers (document's own)
            $ownKlasa = $this->findFirstHeaderItem($results['klasa'] ?? []);
            $ownUrbroj = $this->findFirstHeaderItem($results['urbroj'] ?? []);
            $ownCaseNumbers = $this->findHeaderItems($results['case_numbers'] ?? []);
            $referencedCaseNumbers = $this->findBodyItems($results['case_numbers'] ?? []);
            $klasaUrbrojPairs = $results['klasa_urbroj_pairs'] ?? [];

            // Determine document's own klasa and urbroj values
            $klasaValue = $ownKlasa['value'] ?? null;
            $urbrojValue = $ownUrbroj['value'] ?? null;

            // Use klasa_urbroj_pairs for pairing if available
            if ($klasaValue && isset($klasaUrbrojPairs[$klasaValue]) && $urbrojValue === null) {
                $urbrojValue = $klasaUrbrojPairs[$klasaValue];
            }

            // Parse urbroj prefix for institution inference
            $urbrojPrefix = $urbrojValue ? $this->extractUrbrojPrefix($urbrojValue) : null;
            $institution = $urbrojPrefix ? $this->inferInstitution($urbrojPrefix) : null;

            // Create identity entries for each own case number
            foreach ($ownCaseNumbers as $cn) {
                $parsed = $this->parseCaseNumber($cn['value']);
                $role = $parsed['prefix'] ? $this->inferRole($parsed['prefix']) : null;

                $byCaseNumber[$cn['value']] = [
                    'document_id' => $documentId,
                    'status' => 'present',
                    'klasa' => $klasaValue,
                    'urbroj' => $urbrojValue,
                    'suffix' => $parsed['suffix'],
                    'base_case_number' => $parsed['base'],
                    'institution' => $institution,
                    'role' => $role,
                ];
            }

            // Collect referenced case numbers (body-position)
            foreach ($referencedCaseNumbers as $cn) {
                $allReferencedCaseNumbers[$cn['value']] = true;
            }

            // Build klasa index
            if ($klasaValue) {
                if (! isset($byKlasa[$klasaValue])) {
                    $byKlasa[$klasaValue] = [
                        'urbroj' => $urbrojValue,
                        'case_numbers' => [],
                        'document_ids' => [],
                    ];
                }
                foreach ($ownCaseNumbers as $cn) {
                    if (! in_array($cn['value'], $byKlasa[$klasaValue]['case_numbers'])) {
                        $byKlasa[$klasaValue]['case_numbers'][] = $cn['value'];
                    }
                }
                if (! in_array($documentId, $byKlasa[$klasaValue]['document_ids'])) {
                    $byKlasa[$klasaValue]['document_ids'][] = $documentId;
                }
            }
        }

        // Add missing entries for referenced case numbers not present in any document header
        foreach ($allReferencedCaseNumbers as $caseNumber => $_) {
            if (! isset($byCaseNumber[$caseNumber])) {
                $parsed = $this->parseCaseNumber($caseNumber);
                $byCaseNumber[$caseNumber] = [
                    'document_id' => null,
                    'status' => 'missing',
                    'klasa' => null,
                    'urbroj' => null,
                    'suffix' => $parsed['suffix'],
                    'base_case_number' => $parsed['base'],
                    'institution' => null,
                    'role' => $parsed['prefix'] ? $this->inferRole($parsed['prefix']) : null,
                    'inference_reason' => 'referenced_but_not_present',
                ];
            }
        }

        // Detect suffix gaps within case number sequences
        $this->detectSuffixGaps($byCaseNumber);

        // Calculate totals
        $present = count(array_filter($byCaseNumber, fn ($e) => $e['status'] === 'present'));
        $missing = count(array_filter($byCaseNumber, fn ($e) => $e['status'] === 'missing'));

        return [
            'total_identities' => $present + $missing,
            'present' => $present,
            'missing' => $missing,
            'by_case_number' => $byCaseNumber,
            'by_klasa' => $byKlasa,
            'processing_time_seconds' => round(microtime(true) - $startTime, 3),
        ];
    }

    /**
     * Guess document type from suffix number and case prefix.
     *
     * Specific prefix+suffix=1 combinations map to known document types.
     * Otherwise, suffix=1 is "initial_filing" and suffix>1 is "followup".
     *
     * @param int $suffix The suffix number (1, 2, 3, ...)
     * @param string $prefix The case number prefix (K, Pp Prz, Kv, etc.)
     * @return string The guessed document type
     */
    public function guessDocTypeFromSuffix(int $suffix, string $prefix): string
    {
        // For suffix > 1, always followup regardless of prefix
        if ($suffix > 1) {
            return 'followup';
        }

        // Suffix == 1: check specific prefix overrides
        if (isset(self::PREFIX_DOC_TYPE_MAP[$prefix])) {
            return self::PREFIX_DOC_TYPE_MAP[$prefix];
        }

        // Generic suffix=1 with no specific override
        return 'initial_filing';
    }

    /**
     * Infer institution name from URBROJ numeric prefix.
     *
     * @param string $urbrojPrefix The first numeric segment of the URBROJ (e.g. "511", "2158")
     * @return ?string The institution name or null if unknown
     */
    public function inferInstitution(string $urbrojPrefix): ?string
    {
        return self::URBROJ_INSTITUTION_MAP[$urbrojPrefix] ?? null;
    }

    /**
     * Infer procedural role from case number prefix.
     *
     * @param string $casePrefix The case number prefix (e.g. "K", "Pp Prz", "Kv")
     * @return ?string The procedural role or null if unknown
     */
    public function inferRole(string $casePrefix): ?string
    {
        // Direct match first
        if (isset(self::PREFIX_ROLE_MAP[$casePrefix])) {
            return self::PREFIX_ROLE_MAP[$casePrefix];
        }

        // Try ASCII transliteration for Croatian characters
        $asciiMap = [
            'ž' => 'z',
            'Ž' => 'Z',
            'č' => 'c',
            'Č' => 'C',
            'ć' => 'c',
            'Ć' => 'C',
            'š' => 's',
            'Š' => 'S',
            'đ' => 'd',
            'Đ' => 'D',
        ];

        $asciiPrefix = strtr($casePrefix, $asciiMap);
        if ($asciiPrefix !== $casePrefix && isset(self::PREFIX_ROLE_MAP[$asciiPrefix])) {
            return self::PREFIX_ROLE_MAP[$asciiPrefix];
        }

        // Try reverse: maybe prefix has ASCII but map has Croatian
        $reverseMap = array_flip($asciiMap);
        $croatianPrefix = strtr($casePrefix, $reverseMap);
        if ($croatianPrefix !== $casePrefix && isset(self::PREFIX_ROLE_MAP[$croatianPrefix])) {
            return self::PREFIX_ROLE_MAP[$croatianPrefix];
        }

        return null;
    }

    /**
     * Find the document date from dates_with_context analysis.
     *
     * Returns the earliest date found in the header region of the document.
     *
     * @param string $documentId The document ID
     * @param string $caseId The case ID (unused but included for future multi-case support)
     * @return ?string The date in Y-m-d format or null if no header date found
     */
    public function findDocumentDate(string $documentId, string $caseId): ?string
    {
        $analysis = DocumentAnalysis::where('case_document_id', $documentId)
            ->where('analysis_type', 'dates_with_context')
            ->where('status', DocumentAnalysis::STATUS_COMPLETED)
            ->orderByDesc('version')
            ->first();

        if (! $analysis || ! isset($analysis->results['dates'])) {
            return null;
        }

        // Filter dates in header region
        $headerDates = array_filter(
            $analysis->results['dates'],
            fn ($d) => ($d['position'] ?? PHP_INT_MAX) < self::HEADER_THRESHOLD
        );

        if (empty($headerDates)) {
            return null;
        }

        // Sort by date ascending and return earliest
        usort($headerDates, fn ($a, $b) => strcmp($a['date'], $b['date']));

        return $headerDates[0]['date'];
    }

    // ========================================
    // Private helpers
    // ========================================

    /**
     * Load completed case_references analyses for all documents in a case.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    private function loadCaseReferenceAnalyses(string $caseId)
    {
        return DocumentAnalysis::whereHas('caseDocument', function ($query) use ($caseId) {
            $query->where('case_id', $caseId);
        })
            ->where('analysis_type', 'case_references')
            ->where('status', DocumentAnalysis::STATUS_COMPLETED)
            ->get();
    }

    /**
     * Find the first item with position in header region.
     *
     * @param array $items Array of reference items with 'position' key
     * @return ?array The first header item or null
     */
    private function findFirstHeaderItem(array $items): ?array
    {
        foreach ($items as $item) {
            if (($item['position'] ?? PHP_INT_MAX) < self::HEADER_THRESHOLD) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Find all items with position in header region.
     *
     * @param array $items Array of reference items with 'position' key
     * @return array Items in header region
     */
    private function findHeaderItems(array $items): array
    {
        return array_filter($items, fn ($item) => ($item['position'] ?? PHP_INT_MAX) < self::HEADER_THRESHOLD);
    }

    /**
     * Find all items with position in body region (not header).
     *
     * @param array $items Array of reference items with 'position' key
     * @return array Items in body region
     */
    private function findBodyItems(array $items): array
    {
        return array_filter($items, fn ($item) => ($item['position'] ?? 0) >= self::HEADER_THRESHOLD);
    }

    /**
     * Extract the numeric prefix from an URBROJ string.
     * E.g., "511-01-02-03-24-1" -> "511"
     *
     * @param string $urbroj The full URBROJ string
     * @return string The first numeric segment
     */
    private function extractUrbrojPrefix(string $urbroj): string
    {
        // Remove "URBROJ:" prefix if present
        $value = preg_replace('/^[Uu]\s*[Rr]\s*[Bb]\s*[Rr]\s*[Oo]\s*[Jj]\s*:\s*/', '', $urbroj);

        $parts = explode('-', trim($value));

        return $parts[0];
    }

    /**
     * Parse a case number into prefix, base, and suffix components.
     *
     * Examples:
     *   "K-123/2024-5"      -> prefix="K", base="K-123/2024", suffix=5
     *   "Pp Prz-74/2025-1"  -> prefix="Pp Prz", base="Pp Prz-74/2025", suffix=1
     *   "K-100/2024"        -> prefix="K", base="K-100/2024", suffix=null
     *   "KP-DO-321/2025-1"  -> prefix="KP-DO", base="KP-DO-321/2025", suffix=1
     *
     * @param string $caseNumber The full case number string
     * @return array{prefix: ?string, base: ?string, suffix: ?int}
     */
    private function parseCaseNumber(string $caseNumber): array
    {
        // Pattern: PREFIX-NUMBER/YEAR[-SUFFIX]
        // PREFIX can contain letters, spaces, hyphens (e.g., KP-DO, Pp Prz, Kv II)
        // The greedy .+ captures as much as possible, leaving the last -NUMBER/YEAR[-SUFFIX]
        if (preg_match('/^(.+)-(\d+)\/(\d{2,4})(?:-(\d+))?$/', $caseNumber, $matches)) {
            $prefix = $matches[1];
            $number = $matches[2];
            $year = $matches[3];
            $suffix = isset($matches[4]) ? (int) $matches[4] : null;
            $base = "{$prefix}-{$number}/{$year}";

            return [
                'prefix' => $prefix,
                'base' => $base,
                'suffix' => $suffix,
            ];
        }

        // Fallback: cannot parse
        return [
            'prefix' => null,
            'base' => $caseNumber,
            'suffix' => null,
        ];
    }

    /**
     * Detect gaps in suffix sequences and add missing entries.
     *
     * Groups existing entries by base case number, finds the min and max suffix,
     * and adds "missing" entries for any gaps in the sequence.
     *
     * @param array &$byCaseNumber The by_case_number array (modified in place)
     */
    private function detectSuffixGaps(array &$byCaseNumber): void
    {
        // Group existing entries by base case number
        $groups = [];
        foreach ($byCaseNumber as $caseNumber => $entry) {
            if ($entry['suffix'] !== null && $entry['base_case_number'] !== null) {
                $base = $entry['base_case_number'];
                $groups[$base][$entry['suffix']] = $caseNumber;
            }
        }

        // For each group, find gaps between min and max suffix
        foreach ($groups as $base => $suffixes) {
            if (count($suffixes) < 2) {
                continue; // No gaps possible with single entry
            }

            $min = min(array_keys($suffixes));
            $max = max(array_keys($suffixes));

            for ($i = $min; $i <= $max; $i++) {
                if (! isset($suffixes[$i])) {
                    $gapCaseNumber = $base . '-' . $i;

                    // Only add if not already present (e.g., from referenced_but_not_present)
                    if (! isset($byCaseNumber[$gapCaseNumber])) {
                        $parsed = $this->parseCaseNumber($gapCaseNumber);
                        $byCaseNumber[$gapCaseNumber] = [
                            'document_id' => null,
                            'status' => 'missing',
                            'klasa' => null,
                            'urbroj' => null,
                            'suffix' => $i,
                            'base_case_number' => $base,
                            'institution' => null,
                            'role' => $parsed['prefix'] ? $this->inferRole($parsed['prefix']) : null,
                            'inference_reason' => 'inferred_from_gap',
                        ];
                    }
                }
            }
        }
    }
}

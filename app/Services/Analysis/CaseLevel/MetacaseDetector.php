<?php

namespace App\Services\Analysis\CaseLevel;

use App\Models\DocumentAnalysis;
use Illuminate\Support\Facades\DB;

class MetacaseDetector
{
    /**
     * Case type → procedural relationship mapping.
     * What role each satellite case type plays relative to the main case.
     */
    private const SATELLITE_ROLES = [
        'Pp Prz' => 'search_warrant',       // Prekrsajni - pretraga (home search)
        'Pp J'   => 'misdemeanor',           // Prekrsajni - javni red
        'Kv'     => 'detention_hearing',     // Vijece - pritvor
        'Kv II'  => 'detention_appeal',      // Zalbeno vijece - pritvor
        'Kis'    => 'investigative_action',  // Istrazne radnje
        'KIR'    => 'investigation_opening', // Otvaranje istrage
        'KIO'    => 'investigation',         // Istraga
        'Kz'     => 'appeal',               // Zalba
        'Kzm'    => 'appeal_minor',          // Zalba maloljetnik
        'I Kz'   => 'supreme_appeal',        // Vrhovni sud zalba
        'KP'     => 'prosecution',           // Drzavno odvjetnistvo
        'KP-DO'  => 'prosecution',           // DO kaznena prijava
        'Kis-DO' => 'prosecution_investigative', // DO istrazne
        'DO'     => 'prosecution_case',      // DO predmet
        'Kr'     => 'registry',              // Upisnik
        'Kov'    => 'execution',             // Izvrsenje
        'Ko'     => 'execution',             // Izvrsenje
    ];

    /**
     * Priority for determining which case is "main".
     * Lower number = more likely to be the main case.
     */
    private const MAIN_CASE_PRIORITY = [
        'K' => 1,     // Criminal trial - always main
        'KO' => 2,    // Criminal trial variant
        'KP' => 3,    // Prosecution case
        'DO' => 4,    // State attorney case
        'KIR' => 5,   // Investigation
        'KIO' => 6,   // Investigation
    ];

    public function detect(string $caseId): array
    {
        $startTime = microtime(true);

        // Gather all case_references analysis results
        $analyses = DocumentAnalysis::whereHas('caseDocument', function ($q) use ($caseId) {
            $q->where('case_id', $caseId);
        })
            ->where('analysis_type', 'case_references')
            ->where('status', DocumentAnalysis::STATUS_COMPLETED)
            ->get();

        // Build: case_number → [document_ids where it appears]
        $caseNumberDocs = [];
        foreach ($analyses as $analysis) {
            $docId = $analysis->case_document_id;
            foreach ($analysis->results['case_numbers'] ?? [] as $ref) {
                $value = $ref['value'];
                $caseNumberDocs[$value][] = $docId;
            }
        }

        // Deduplicate document lists
        $caseNumberDocs = array_map('array_unique', $caseNumberDocs);

        // Parse case numbers into prefix + number
        $parsed = [];
        foreach (array_keys($caseNumberDocs) as $caseNum) {
            $parsed[$caseNum] = $this->parseCaseNumber($caseNum);
        }

        // Determine the main case(s) - highest priority prefix
        $mainCases = [];
        $satelliteCases = [];

        foreach ($parsed as $caseNum => $info) {
            if ($info && isset(self::MAIN_CASE_PRIORITY[$info['prefix']])) {
                $mainCases[$caseNum] = [
                    'case_number' => $caseNum,
                    'prefix' => $info['prefix'],
                    'priority' => self::MAIN_CASE_PRIORITY[$info['prefix']],
                    'document_count' => count($caseNumberDocs[$caseNum]),
                ];
            } else {
                $satelliteCases[$caseNum] = $info;
            }
        }

        // Sort main cases by priority (lowest = most main)
        uasort($mainCases, fn($a, $b) => $a['priority'] <=> $b['priority']);

        // If no clear main case, pick the one with the most document mentions
        if (empty($mainCases) && !empty($caseNumberDocs)) {
            $mostMentioned = array_keys(array_map('count', $caseNumberDocs));
            usort($mostMentioned, fn($a, $b) => count($caseNumberDocs[$b]) <=> count($caseNumberDocs[$a]));
            $mainCaseNum = $mostMentioned[0];
            $mainCases[$mainCaseNum] = [
                'case_number' => $mainCaseNum,
                'prefix' => $parsed[$mainCaseNum]['prefix'] ?? 'unknown',
                'priority' => 99,
                'document_count' => count($caseNumberDocs[$mainCaseNum]),
            ];
        }

        $primaryMain = array_key_first($mainCases);

        // Build hierarchy: connect satellites to main case
        $hierarchy = [];
        foreach ($satelliteCases as $satNum => $satInfo) {
            if (!$satInfo) continue;

            $role = $this->determineRole($satInfo['prefix']);

            // Confidence: based on co-occurrence (same document as main case)
            $mainDocs = $caseNumberDocs[$primaryMain] ?? [];
            $satDocs = $caseNumberDocs[$satNum] ?? [];
            $overlap = count(array_intersect($mainDocs, $satDocs));
            $confidence = $overlap > 0
                ? min(1.0, 0.5 + ($overlap * 0.2))
                : 0.3; // Lower confidence if no doc overlap

            // Check year match (same year = higher confidence)
            $mainYear = $parsed[$primaryMain]['year'] ?? null;
            $satYear = $satInfo['year'] ?? null;
            if ($mainYear && $satYear && $mainYear === $satYear) {
                $confidence = min(1.0, $confidence + 0.2);
            }

            $hierarchy[] = [
                'main_case' => $primaryMain,
                'satellite_case' => $satNum,
                'satellite_prefix' => $satInfo['prefix'],
                'relationship' => $role,
                'confidence' => round($confidence, 2),
                'co_occurring_documents' => array_values(array_intersect($mainDocs, $satDocs)),
                'satellite_only_documents' => array_values(array_diff($satDocs, $mainDocs)),
            ];
        }

        // Sort by confidence descending
        usort($hierarchy, fn($a, $b) => $b['confidence'] <=> $a['confidence']);

        // Persist
        DB::table('case_hierarchy')->where('case_id', $caseId)->delete();
        foreach ($hierarchy as $h) {
            DB::table('case_hierarchy')->insert([
                'case_id' => $caseId,
                'main_case_number' => $h['main_case'],
                'main_case_type' => $parsed[$h['main_case']]['prefix'] ?? 'unknown',
                'satellite_case_number' => $h['satellite_case'],
                'satellite_case_type' => $h['satellite_prefix'],
                'relationship' => $h['relationship'],
                'confidence' => $h['confidence'],
                'evidence' => json_encode([
                    'co_occurring_documents' => $h['co_occurring_documents'],
                    'satellite_only_documents' => $h['satellite_only_documents'],
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return [
            'results' => [
                'main_cases' => array_values($mainCases),
                'primary_main_case' => $primaryMain,
                'hierarchy' => $hierarchy,
                'satellite_count' => count($hierarchy),
                'case_types_found' => array_unique(array_filter(
                    array_map(fn($p) => $p['prefix'] ?? null, $parsed)
                )),
                'summary' => $this->buildHumanSummary($primaryMain, $hierarchy),
            ],
            'metadata' => [
                'processing_time_seconds' => round(microtime(true) - $startTime, 4),
                'documents_analyzed' => $analyses->count(),
                'total_case_numbers_found' => count($caseNumberDocs),
            ],
        ];
    }

    private function parseCaseNumber(string $caseNum): ?array
    {
        // Match: "Prefix-Number/Year(-Suffix)"
        // Examples: K-123/2025, Pp Prz-74/2025-2, Kv II-89/2025, KP-DO-321/2025
        if (preg_match('/^((?:Pp\s+Prz|Pp\s+J|Kv\s+II|Kis-DO|KP-DO|I\s+Kz|[A-Za-z]+))-(\d+)\/(\d{2,4})(?:-(\d+))?$/ui', $caseNum, $m)) {
            $year = strlen($m[3]) === 2 ? (int)('20' . $m[3]) : (int)$m[3];
            return [
                'prefix' => trim($m[1]),
                'number' => (int)$m[2],
                'year' => $year,
                'suffix' => $m[4] ?? null,
            ];
        }

        return null;
    }

    private function determineRole(string $prefix): string
    {
        $normalized = preg_replace('/\s+/', ' ', trim($prefix));
        return self::SATELLITE_ROLES[$normalized] ?? 'related';
    }

    private function buildHumanSummary(?string $mainCase, array $hierarchy): string
    {
        if (!$mainCase) {
            return 'Nije pronaden glavni predmet';
        }

        if (empty($hierarchy)) {
            return "Pronaden samo jedan predmet: {$mainCase}";
        }

        $parts = ["Glavni predmet: {$mainCase}"];

        $byRole = [];
        foreach ($hierarchy as $h) {
            $byRole[$h['relationship']][] = $h['satellite_case'];
        }

        $roleLabels = [
            'search_warrant' => 'Nalog za pretragu',
            'detention_hearing' => 'Pritvor',
            'detention_appeal' => 'Zalba na pritvor',
            'appeal' => 'Zalba',
            'investigation_opening' => 'Otvaranje istrage',
            'investigation' => 'Istraga',
            'investigative_action' => 'Istrazne radnje',
            'prosecution' => 'Drzavno odvjetnistvo',
            'prosecution_investigative' => 'DO istrazne radnje',
            'prosecution_case' => 'DO predmet',
            'execution' => 'Izvrsenje',
            'misdemeanor' => 'Prekrsaj',
            'related' => 'Povezano',
        ];

        foreach ($byRole as $role => $cases) {
            $label = $roleLabels[$role] ?? $role;
            $parts[] = "  {$label}: " . implode(', ', $cases);
        }

        return implode("\n", $parts);
    }
}

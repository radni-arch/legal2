<?php

namespace App\Services\Analysis\Analyzers;

use App\Models\CaseDocument;
use App\Models\DocumentAnalysis;
use App\Services\Analysis\Contracts\DocumentAnalyzerInterface;

class EntityExtractor implements DocumentAnalyzerInterface
{
    public function type(): string
    {
        return DocumentAnalysis::TYPE_ENTITIES;
    }

    public function layer(): string
    {
        return DocumentAnalysis::LAYER_EXTRACTION;
    }

    public function analyze(CaseDocument $document, string $text): array
    {
        $startTime = microtime(true);

        $entities = [
            'case_numbers' => $this->extractCaseNumbers($text),
            'courts' => $this->extractCourts($text),
            'laws' => $this->extractLaws($text),
            'narodne_novine' => $this->extractNarodneNovine($text),
            'persons' => $this->extractPersonReferences($text),
            'institutions' => $this->extractInstitutions($text),
            'monetary_amounts' => $this->extractMonetaryAmounts($text),
        ];

        $totalEntities = array_sum(array_map('count', $entities));

        return [
            'results' => [
                'entities' => $entities,
                'total_entities' => $totalEntities,
                'entity_density' => round($totalEntities / max(preg_match_all('/[\p{L}]+/u', $text), 1) * 100, 2),
            ],
            'metadata' => [
                'processing_time_seconds' => round(microtime(true) - $startTime, 4),
                'analyzer' => 'EntityExtractor',
                'api_calls' => 0,
                'cost' => 0,
            ],
        ];
    }

    private function extractCaseNumbers(string $text): array
    {
        $caseNumbers = [];

        // Patterns: K-123/2024, Kž-456/23, Pp Prz-789/2024, I Kž Us 12/2023-5
        $patterns = [
            '/(?:I+\s+)?(?:K[ržo]?|Pp\s*Prz|Gž|Pn|Us|Kž\s*Us|Kov)[\s-]*\d+\/\d{2,4}(?:-\d+)?/ui',
            '/ECLI:[A-Z]{2}:[A-Z0-9.]+:\d{4}:\d+/i',
        ];

        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $text, $matches);
            foreach ($matches[0] as $match) {
                $normalized = trim(preg_replace('/\s+/', ' ', $match));
                $caseNumbers[$normalized] = ($caseNumbers[$normalized] ?? 0) + 1;
            }
        }

        return array_map(fn($num, $count) => [
            'case_number' => $num,
            'mentions' => $count,
        ], array_keys($caseNumbers), array_values($caseNumbers));
    }

    private function extractCourts(string $text): array
    {
        $courts = [];
        $patterns = [
            '/(?:Općinski|Županijski|Vrhovni|Ustavni|Trgovački|Upravni|Visoki\s+(?:prekršajni|trgovački|upravni))\s+sud\s+u\s+[\wčćžšđČĆŽŠĐ]+/ui',
            '/(?:DORH|USKOK|VSRH|VTSRH)/i',
        ];

        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $text, $matches);
            foreach ($matches[0] as $match) {
                $normalized = trim($match);
                $courts[$normalized] = ($courts[$normalized] ?? 0) + 1;
            }
        }

        return array_map(fn($court, $count) => [
            'court' => $court,
            'mentions' => $count,
        ], array_keys($courts), array_values($courts));
    }

    private function extractLaws(string $text): array
    {
        $laws = [];

        // "Zakon o kaznenom postupku", "Kazneni zakon", etc.
        preg_match_all(
            '/(?:Zakon\s+o\s+[\wčćžšđČĆŽŠĐ\s]{3,50}|Kazneni\s+zakon|Ustav\s+(?:Republike\s+)?Hrvatske|Ovršni\s+zakon|Prekršajni\s+zakon)/ui',
            $text,
            $matches
        );

        foreach ($matches[0] as $match) {
            $normalized = trim(preg_replace('/\s+/', ' ', $match));
            $laws[$normalized] = ($laws[$normalized] ?? 0) + 1;
        }

        // Also catch abbreviations: ZKP, KZ, OZ, ZPP
        preg_match_all('/\b(ZKP|KZ|OZ|ZPP|ZUSKOK|ZOS|ZOPNPP)\b/', $text, $abbrevMatches);
        foreach ($abbrevMatches[0] as $match) {
            $laws[$match] = ($laws[$match] ?? 0) + 1;
        }

        return array_map(fn($law, $count) => [
            'law' => $law,
            'mentions' => $count,
        ], array_keys($laws), array_values($laws));
    }

    private function extractNarodneNovine(string $text): array
    {
        $refs = [];
        // NN 152/08, NN br. 152/08, Narodne novine 152/08
        preg_match_all(
            '/(?:NN|Narodne\s+novine)\s*(?:br\.?\s*)?(\d+\/\d{2,4}(?:\.\s*(?:i\s+)?\d+\/\d{2,4})*)/ui',
            $text,
            $matches
        );

        foreach ($matches[0] as $match) {
            $normalized = trim($match);
            $refs[$normalized] = ($refs[$normalized] ?? 0) + 1;
        }

        return array_map(fn($ref, $count) => [
            'reference' => $ref,
            'mentions' => $count,
        ], array_keys($refs), array_values($refs));
    }

    private function extractPersonReferences(string $text): array
    {
        $persons = [];

        // "okrivljenik", "oštećenik", "svjedok", "vještak" + potential name patterns
        $rolePatterns = [
            'okrivljenik' => '/okrivljenik[a-z]*/ui',
            'oštećenik' => '/oštećenik[a-z]*/ui',
            'svjedok' => '/svjedok[a-z]*/ui',
            'vještak' => '/vještak[a-z]*/ui',
            'tužitelj' => '/(?:državni\s+)?tužitelj[a-z]*/ui',
            'branitelj' => '/branitelj[a-z]*/ui',
            'sudac' => '/(?:sudac|sutkinja|predsjednik[a-z]*\s+vijeća)/ui',
        ];

        foreach ($rolePatterns as $role => $pattern) {
            preg_match_all($pattern, $text, $matches);
            if (!empty($matches[0])) {
                $persons[] = [
                    'role' => $role,
                    'mentions' => count($matches[0]),
                ];
            }
        }

        return $persons;
    }

    private function extractInstitutions(string $text): array
    {
        $institutions = [];
        $patterns = [
            '/(?:MUP|Ministarstvo\s+unutarnjih\s+poslova)/ui',
            '/(?:Policijska\s+(?:uprava|postaja)\s+[\wčćžšđ]+)/ui',
            '/(?:Zatvor\s+u?\s*[\wčćžšđ]+|Kaznionica\s+u?\s*[\wčćžšđ]+)/ui',
            '/(?:DORH|Državno\s+odvjetništvo)/ui',
        ];

        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $text, $matches);
            foreach ($matches[0] as $match) {
                $normalized = trim($match);
                $institutions[$normalized] = ($institutions[$normalized] ?? 0) + 1;
            }
        }

        return array_map(fn($inst, $count) => [
            'institution' => $inst,
            'mentions' => $count,
        ], array_keys($institutions), array_values($institutions));
    }

    private function extractMonetaryAmounts(string $text): array
    {
        $amounts = [];

        // "1.500,00 kuna", "1.500,00 EUR", "€1,500.00"
        preg_match_all(
            '/(\d{1,3}(?:\.\d{3})*(?:,\d{2})?)\s*(kuna|kn|HRK|EUR|eura|€)/ui',
            $text,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $m) {
            $amounts[] = [
                'raw' => $m[0],
                'amount' => $m[1],
                'currency' => $m[2],
            ];
        }

        return $amounts;
    }
}

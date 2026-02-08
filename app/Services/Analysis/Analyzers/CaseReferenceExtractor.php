<?php

namespace App\Services\Analysis\Analyzers;

use App\DTOs\Analysis\CaseReference;
use App\DTOs\Analysis\CaseReferenceCollection;
use App\Models\CaseDocument;
use App\Models\DocumentAnalysis;
use App\Services\Analysis\Contracts\DocumentAnalyzerInterface;

class CaseReferenceExtractor implements DocumentAnalyzerInterface
{
    public function type(): string
    {
        return 'case_references';
    }

    public function layer(): string
    {
        return DocumentAnalysis::LAYER_EXTRACTION;
    }

    public function analyze(CaseDocument $document, string $text): array
    {
        $startTime = microtime(true);
        $collection = new CaseReferenceCollection();

        $collection->klasa = $this->extractKlasa($text);
        $collection->urbroj = $this->extractUrbroj($text);
        $collection->broj = $this->extractBroj($text);
        $collection->caseNumbers = $this->extractCaseNumbers($text);
        $collection->klasaUrbrojPairs = $this->detectKlasaUrbrojPairs($text);

        return [
            'results' => $collection->toArray(),
            'metadata' => [
                'processing_time_seconds' => round(microtime(true) - $startTime, 4),
                'analyzer' => 'CaseReferenceExtractor',
                'api_calls' => 0,
                'cost' => 0,
                'total_references' => count($collection->all()),
                'source_script_version' => 'extract_references.sh v4',
            ],
        ];
    }

    // ========================================
    // KLASA
    // Formats seen in practice:
    //   KLASA: UP/I-034-02/20-01/123
    //   KLASA: 034-02/25-01/5
    //   K L A S A : UP/I-034-02/20-01/999
    //   Klasa: UP/I-561-08/25-01/122
    // ========================================

    private function extractKlasa(string $text): array
    {
        $results = [];

        $patterns = [
            // Standard with UP/I prefix
            '/K\s*L\s*A\s*S\s*A\s*:\s*[A-Z]{2,}\/[A-Z]-?\d+-\d+\/\d+-\d+\/\d+/ui',
            // Without UP/I prefix
            '/K\s*L\s*A\s*S\s*A\s*:\s*\d{3}-\d{2}\/\d{2}-\d{2}\/\d+/ui',
            // General fallback: KLASA: then pattern with dashes and slashes
            '/KLASA\s*:\s*[A-Z\/]*-?\d+[-\/]\d+[-\/]\d+[-\/]\d+(\/\d+)?/ui',
        ];

        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE);
            foreach ($matches[0] as $m) {
                $results[] = new CaseReference(
                    type: 'klasa',
                    value: $this->normalizeWhitespace($m[0]),
                    rawMatch: $m[0],
                    subType: null,
                    context: $this->extractContext($text, $m[1]),
                    position: $m[1],
                    mentions: 1,
                );
            }
        }

        return $this->dedup($results);
    }

    // ========================================
    // URBROJ
    // Formats:
    //   URBROJ: 511-01-02-03-20-1
    //   Urbroj: 2158-64-16-01-25-1
    //   U R B R O J: ...
    //   Ur.br.: ...
    // ========================================

    private function extractUrbroj(string $text): array
    {
        $results = [];

        $patterns = [
            // With spaces in word
            '/U\s*R\s*B\s*R\s*O\s*J\s*:\s*\d[\d\-\/]{8,50}/ui',
            // Standard URBROJ
            '/URBROJ\s*:\s*\d{3,4}-\d[\d\-\/]+/ui',
            // Mixed case Urbroj
            '/Urbroj\s*:\s*\d{3,4}-\d[\d\-\/]+/ui',
            // Ur.br. variations
            '/Ur\.?\s*br(?:oj)?\.?\s*:\s*\d+[-\/]\d+[-\/\d]+/ui',
        ];

        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE);
            foreach ($matches[0] as $m) {
                $results[] = new CaseReference(
                    type: 'urbroj',
                    value: $this->normalizeWhitespace($m[0]),
                    rawMatch: $m[0],
                    subType: $this->classifyUrbroj($m[0]),
                    context: $this->extractContext($text, $m[1]),
                    position: $m[1],
                    mentions: 1,
                );
            }
        }

        return $this->dedup($results);
    }

    // ========================================
    // BROJ (police and court internal numbers)
    // Formats:
    //   Broj: 511-07-11-K-51/2025.
    //   67-00-731/2025
    //   Pp Prz-74/2025-2
    // ========================================

    private function extractBroj(string $text): array
    {
        $results = [];

        $patterns = [
            // Broj: with content
            '/Broj\s*:\s*\d[\d\-A-Za-z\/]+/ui',
            // Police number: 511-XX-XX-X-XX/YYYY
            '/511-\d{2}-\d{2}-[A-Z]-\d+\/\d{4}/u',
            // DO number: 67-00-731/2025 format
            // Negative lookbehind for dots avoids matching date fragments (e.g. 15.01-...)
            '/(?<!\.)(?<!\d\.)\b\d{2}-\d{2}-\d+\/\d{4}/u',
        ];

        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE);
            foreach ($matches[0] as $m) {
                $results[] = new CaseReference(
                    type: 'broj',
                    value: $this->normalizeWhitespace($m[0]),
                    rawMatch: $m[0],
                    subType: $this->classifyBroj($m[0]),
                    context: $this->extractContext($text, $m[1]),
                    position: $m[1],
                    mentions: 1,
                );
            }
        }

        return $this->dedup($results);
    }

    // ========================================
    // CASE NUMBERS - Full pattern set from extract_references.sh
    // ========================================

    private function extractCaseNumbers(string $text): array
    {
        $results = [];

        // Categorized patterns with their sub-types
        $patternGroups = [
            'kazneni' => [
                '/\bK-\d+\/\d{2,4}(-\d+)?/ui',
                '/\bKž-\d+\/\d{2,4}(-\d+)?/ui',
                '/\bKžm-\d+\/\d{2,4}(-\d+)?/ui',
                '/\bKr-\d+\/\d{2,4}(-\d+)?/ui',
                '/\bKv-\d+\/\d{2,4}(-\d+)?/ui',
                '/\bKv\s+II-\d+\/\d{2,4}(-\d+)?/ui',
                '/\bKO-\d+\/\d{2,4}(-\d+)?/ui',
                '/\bKIO-\d+\/\d{2,4}(-\d+)?/ui',
                '/\bKIR-\d+\/\d{2,4}(-\d+)?/ui',
                '/\bKov-\d+\/\d{2,4}(-\d+)?/ui',
                '/\bI\s+Kž-\d+\/\d{2,4}(-\d+)?/ui',
                '/\bKis-\d+\/\d{2,4}(-\d+)?/ui',
            ],
            'prekrsajni' => [
                '/\bPp\s+Prz-\d+\/\d{2,4}(-\d+)?/ui',
                '/\bPp\s+J-\d+\/\d{2,4}(-\d+)?/ui',
                '/\bJž-\d+\/\d{2,4}(-\d+)?/ui',
                '/\bPn-\d+\/\d{2,4}(-\d+)?/ui',
            ],
            'dorh' => [
                '/\bDO-\d+\/\d{2,4}(-\d+)?/ui',
                '/\bDORH-[A-Z]+-\d+\/\d{2,4}(-\d+)?/ui',
                '/\bKP-\d+\/\d{2,4}(-\d+)?/ui',
                '/\bKP-DO-\d+\/\d{2,4}(-\d+)?/ui',
                '/\bKis-DO-\d+\/\d{2,4}(-\d+)?/ui',
            ],
            'gradanski' => [
                '/\bP-\d+\/\d{2,4}(-\d+)?/ui',
                '/\bGž-\d+\/\d{2,4}(-\d+)?/ui',
                '/\bPovrv-\d+\/\d{2,4}(-\d+)?/ui',
                '/\bSu-\d+\/\d{2,4}(-\d+)?/ui',
            ],
            'upravni' => [
                '/\bUs-\d+\/\d{2,4}(-\d+)?/ui',
                '/\bUsž-\d+\/\d{2,4}(-\d+)?/ui',
            ],
        ];

        foreach ($patternGroups as $subType => $patterns) {
            foreach ($patterns as $pattern) {
                preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE);
                foreach ($matches[0] as $m) {
                    $results[] = new CaseReference(
                        type: 'case_number',
                        value: $this->normalizeWhitespace($m[0]),
                        rawMatch: $m[0],
                        subType: $subType,
                        context: $this->extractContext($text, $m[1]),
                        position: $m[1],
                        mentions: 1,
                    );
                }
            }
        }

        return $this->dedup($results);
    }

    // ========================================
    // KLASA <-> URBROJ PAIRING
    // In Croatian admin documents, KLASA and URBROJ always appear
    // together within ~200 chars. Detect these pairs.
    // ========================================

    private function detectKlasaUrbrojPairs(string $text): array
    {
        $pairs = [];

        // Find KLASA positions
        preg_match_all('/KLASA\s*:\s*([^\n]+)/ui', $text, $klasaMatches, PREG_OFFSET_CAPTURE);
        preg_match_all('/URBROJ\s*:\s*([^\n]+)/ui', $text, $urbrojMatches, PREG_OFFSET_CAPTURE);

        foreach ($klasaMatches[0] as $ki => $km) {
            $klasaPos = $km[1];
            $klasaVal = trim($klasaMatches[1][$ki][0] ?? $km[0]);

            // Find nearest URBROJ within 300 chars
            $bestUrbroj = null;
            $bestDist = 300;

            foreach ($urbrojMatches[0] as $ui => $um) {
                $urbrojPos = $um[1];
                $dist = abs($urbrojPos - $klasaPos);

                if ($dist < $bestDist) {
                    $bestDist = $dist;
                    $bestUrbroj = trim($urbrojMatches[1][$ui][0] ?? $um[0]);
                }
            }

            if ($bestUrbroj) {
                $pairs[$klasaVal] = $bestUrbroj;
            }
        }

        return $pairs;
    }

    // ========================================
    // URBROJ classification
    // 511-* = MUP (police)
    // 2158-* etc = courts (municipal/county codes)
    // ========================================

    private function classifyUrbroj(string $urbroj): string
    {
        if (preg_match('/511-/', $urbroj)) {
            return 'mup_policija';
        }
        if (preg_match('/2158-/', $urbroj)) {
            return 'sud'; // Osijek area
        }
        if (preg_match('/2168-/', $urbroj)) {
            return 'sud'; // Zagreb area
        }
        return 'other';
    }

    private function classifyBroj(string $broj): string
    {
        if (preg_match('/511-/', $broj)) {
            return 'policijski';
        }
        if (preg_match('/\d{2}-00-/', $broj)) {
            return 'drzavno_odvjetnistvo';
        }
        return 'other';
    }

    // ========================================
    // Helpers
    // ========================================

    private function extractContext(string $text, int $offset, int $radius = 150): string
    {
        $start = max(0, $offset - $radius);
        $length = min(mb_strlen($text) - $start, $radius * 2 + 100);
        $context = mb_substr($text, $start, $length);
        return trim(preg_replace('/\s+/', ' ', $context));
    }

    private function normalizeWhitespace(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', $value));
    }

    /**
     * Deduplicate references by normalized value, summing mentions.
     * Keep first occurrence's position and context.
     *
     * @param CaseReference[] $refs
     * @return CaseReference[]
     */
    private function dedup(array $refs): array
    {
        $seen = [];
        $deduped = [];

        foreach ($refs as $ref) {
            $key = $ref->type . '|' . mb_strtolower($ref->value);
            if (isset($seen[$key])) {
                // Increment mention count on the first occurrence
                $existing = $deduped[$seen[$key]];
                $deduped[$seen[$key]] = new CaseReference(
                    type: $existing->type,
                    value: $existing->value,
                    rawMatch: $existing->rawMatch,
                    subType: $existing->subType,
                    context: $existing->context,
                    position: $existing->position,
                    mentions: $existing->mentions + 1,
                    pairedWith: $existing->pairedWith,
                );
            } else {
                $seen[$key] = count($deduped);
                $deduped[] = $ref;
            }
        }

        return $deduped;
    }
}

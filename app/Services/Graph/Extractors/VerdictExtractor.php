<?php

namespace App\Services\Graph\Extractors;

class VerdictExtractor
{
    /**
     * Verdict type mappings for Croatian legal text
     * NOTE: Order matters - check partial BEFORE granted/denied since partial contains both
     */
    protected array $verdictPatterns = [
        'partial' => [
            '/[Dd]jelomi[čc]no\s+se\s+usvaja/u',
            '/[Uu]svaja\s+se\s+djelomi[čc]no/u',
            '/[Dd]jelomi[čc]no\s+se\s+odbija/u',
            '/[Uu]\s+dijelu.*(?:usvaja|odbija)/u',
        ],
        'granted' => [
            '/[Uu]svaja\s+se\s+tu[žz]beni\s+zahtjev/u',
            '/[Tt]u[žz]beni\s+zahtjev\s+se\s+usvaja/u',
            '/[Pp]rihva[ćc]a\s+se\s+tu[žz]ba/u',
            '/[Žž]alba\s+se\s+uva[žz]ava/u',
            '/[Uu]va[žz]ava\s+se\s+[žz]alba/u',
        ],
        'denied' => [
            '/[Oo]dbija\s+se\s+tu[žz]beni\s+zahtjev/u',
            '/[Tt]u[žz]beni\s+zahtjev\s+se\s+odbija/u',
            '/[Oo]dbija\s+se\s+tu[žz]ba/u',
            '/[Žž]alba\s+se\s+odbija/u',
            '/[Oo]dbija\s+se\s+[žz]alba/u',
            '/[Oo]dbija\s+se\s+kao\s+neosnovana?/u',
        ],
        'dismissed' => [
            '/[Oo]dbacuje\s+se\s+tu[žz]ba/u',
            '/[Tt]u[žz]ba\s+se\s+odbacuje/u',
            '/[Oo]dbacuje\s+se\s+[žz]alba/u',
            '/[Žž]alba\s+se\s+odbacuje/u',
            '/[Oo]dbacuje\s+se\s+kao\s+nedopu[šs]tena/u',
            '/[Oo]dbacuje\s+se\s+kao\s+nepravodobna/u',
        ],
        'remanded' => [
            '/[Uu]kida\s+se.*(?:vra[ćc]a|upu[ćc]uje)/u',
            '/[Pp]redmet\s+se\s+vra[ćc]a/u',
            '/[Vv]ra[ćc]a\s+se\s+(?:predmet|spis)/u',
            '/[Uu]pu[ćc]uje\s+se\s+na\s+ponovno/u',
        ],
        'withdrawn' => [
            '/[Oo]bustavlja\s+se\s+postupak/u',
            '/[Pp]ostupak\s+se\s+obustavlja/u',
            '/[Pp]ovla[čc]i\s+se\s+tu[žz]ba/u',
        ],
    ];

    /**
     * Extract verdict from decision text
     *
     * @param string $text Full decision text
     * @param string $decisionId The decision ID
     * @return array|null Extracted verdict or null if not found
     */
    public function extract(string $text, string $decisionId): ?array
    {
        if (empty(trim($text))) {
            return null;
        }

        // First try to find the "Izreka" or dispositive section
        $dispositive = $this->extractDispositive($text);
        $searchText = $dispositive ?? $text;

        // Try to match verdict patterns
        $outcomeType = null;
        $rawText = null;

        foreach ($this->verdictPatterns as $type => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $searchText, $match)) {
                    $outcomeType = $type;
                    $rawText = $this->extractVerdictContext($searchText, $match[0]);
                    break 2;
                }
            }
        }

        if (!$outcomeType) {
            return null;
        }

        // Extract monetary damages if present
        $damages = $this->extractDamages($searchText);

        return [
            'id' => 'verdict_' . $decisionId,
            'decision_id' => $decisionId,
            'outcome_type' => $outcomeType,
            'raw_text' => $rawText,
            'relief_granted' => $this->extractRelief($searchText, 'granted'),
            'relief_denied' => $this->extractRelief($searchText, 'denied'),
            'damages_amount' => $damages['amount'] ?? null,
            'damages_currency' => $damages['currency'] ?? null,
        ];
    }

    /**
     * Extract the dispositive section (Izreka)
     */
    protected function extractDispositive(string $text): ?string
    {
        // Look for "Izreka:" or similar headers
        $patterns = [
            '/[Ii]zreka[:\s]*\n(.*?)(?=\n\s*[Oo]brazlo[žz]enje|$)/su',
            '/[Pp]resu[đd]uje[:\s]*\n(.*?)(?=\n\s*[Oo]brazlo[žz]enje|$)/su',
            '/[Rr]je[šs]ava[:\s]*\n(.*?)(?=\n\s*[Oo]brazlo[žz]enje|$)/su',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                return trim($match[1]);
            }
        }

        return null;
    }

    /**
     * Extract context around the verdict
     */
    protected function extractVerdictContext(string $text, string $match): string
    {
        $pos = mb_stripos($text, $match);
        if ($pos === false) {
            return $match;
        }

        // Get the sentence containing the verdict
        $start = max(0, $pos - 50);
        $length = min(mb_strlen($text) - $start, 300);
        $context = mb_substr($text, $start, $length);

        // Clean up
        $context = preg_replace('/\s+/', ' ', trim($context));

        return $context;
    }

    /**
     * Extract monetary damages
     */
    protected function extractDamages(string $text): array
    {
        $result = ['amount' => null, 'currency' => null];

        // Pattern for Croatian currency amounts
        $patterns = [
            // 10.000,00 kuna/kn/HRK or EUR
            '/(\d{1,3}(?:\.\d{3})*(?:,\d{2})?)\s*(kuna?|kn|HRK|EUR|eura?)/ui',
            // 10000 kuna
            '/(\d+(?:,\d{2})?)\s*(kuna?|kn|HRK|EUR|eura?)/ui',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                // Parse amount (Croatian format: 10.000,00)
                $amount = str_replace('.', '', $match[1]); // Remove thousands separator
                $amount = str_replace(',', '.', $amount);  // Convert decimal separator
                $result['amount'] = (float) $amount;

                // Normalize currency
                $currency = mb_strtoupper($match[2]);
                if (in_array($currency, ['KUNA', 'KN', 'HRK'])) {
                    $result['currency'] = 'HRK';
                } elseif (in_array($currency, ['EUR', 'EURA', 'EURO'])) {
                    $result['currency'] = 'EUR';
                } else {
                    $result['currency'] = $currency;
                }

                break;
            }
        }

        return $result;
    }

    /**
     * Extract what relief was granted or denied
     */
    protected function extractRelief(string $text, string $type): ?string
    {
        // This is best-effort extraction of what specifically was granted/denied
        $patterns = [
            'granted' => '/(?:usvaja|prihva[ćc]a)[^.]*?(?:zahtjev[^.]*?za\s+)([^.]{10,100})/ui',
            'denied' => '/(?:odbija)[^.]*?(?:zahtjev[^.]*?za\s+)([^.]{10,100})/ui',
        ];

        if (isset($patterns[$type]) && preg_match($patterns[$type], $text, $match)) {
            return trim($match[1]);
        }

        return null;
    }

    /**
     * Get all outcome types
     */
    public function getOutcomeTypes(): array
    {
        return array_keys($this->verdictPatterns);
    }
}

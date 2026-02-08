<?php

namespace App\Services\Graph\Extractors;

use Illuminate\Support\Str;

class EvidenceExtractor
{
    /**
     * Evidence type patterns in Croatian legal text
     */
    protected array $evidencePatterns = [
        'documentary' => [
            '/(?:isprav(?:[aeiou][mno]?)?|dokument(?:[aeiou][mno]?)?|ugovor(?:[aeiou][mno]?)?|ra[čc]un(?:[aeiou][mno]?)?|izvod(?:[aeiou][mno]?)?|potvrda?(?:[eu][mno]?)?|uvjerenje[ma]?|rje[šs]enje[ma]?|presud(?:[aeiou][mno]?)?|zapisnik(?:[aeiou][mno]?)?)\s+(?:br\.|broj|od|iz)/ui',
            '/(?:prilog(?:om)?|dokaz(?:om)?)\s+(?:br\.|broj)?\s*\d+/ui',
            '/(?:list|spis|akt)\s+(?:br\.|broj)\s*\d+/ui',
        ],
        'testimonial' => [
            '/(?:svjedok|svjedo[čc]enje|iskaz)\s+([A-ZČĆŽŠĐ][a-zčćžšđ]+(?:\s+[A-ZČĆŽŠĐ][a-zčćžšđ]+)?)/ui',
            '/(?:saslu[šs]anje|ispitivanje)\s+(?:svjedoka|stranke)/ui',
            '/(?:svjedok|stranka)\s+je\s+(?:izjavio|izjavila|potvrdio|potvrdila)/ui',
        ],
        'expert' => [
            '/(?:vje[šs]tak|vje[šs]ta[čc]enje|nalaz\s+i\s+mi[šs]ljenje)/ui',
            '/(?:sudski\s+)?vje[šs]tak\s+([A-ZČĆŽŠĐ][a-zčćžšđ]+)/ui',
            '/(?:stru[čc]no\s+mi[šs]ljenje|ekspertiza)/ui',
        ],
        'physical' => [
            '/(?:o[čc]evid|uvi[đd]aj|pregled\s+mjesta)/ui',
            '/(?:materijalni\s+dokaz|predmet|stvar)\s+(?:kao\s+dokaz)?/ui',
            '/(?:fotografij[ae]|snimk[ae]|video)/ui',
        ],
    ];

    /**
     * Weight indicators
     */
    protected array $weightIndicators = [
        'decisive' => ['odlu[čc]uju[ćc]i', 'klju[čc]ni', 'bitni', 'presudni', 'temelji se na'],
        'corroborative' => ['potkrepljuje', 'potvr[đd]uje', 'u prilog', 'dodatni'],
        'rejected' => ['odbija', 'ne prihva[ćc]a', 'nema dokaznu snagu', 'nedopu[šs]ten'],
    ];

    /**
     * Extract evidence from decision text
     */
    public function extract(string $text, ?string $caseId = null): array
    {
        if (empty(trim($text))) {
            return [];
        }

        $evidence = [];
        $seen = [];

        foreach ($this->evidencePatterns as $type => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                    foreach ($matches as $match) {
                        $matchText = $match[0][0];
                        $position = $match[0][1];

                        // Create unique key to avoid duplicates
                        $key = mb_strtolower(preg_replace('/\s+/', '', $matchText));
                        if (isset($seen[$key])) {
                            continue;
                        }
                        $seen[$key] = true;

                        $context = $this->extractContext($text, $position, 200);
                        $weight = $this->determineWeight($context);
                        $admitted = $this->determineAdmission($context);

                        $evidence[] = [
                            'id' => (string) Str::ulid(),
                            'case_id' => $caseId,
                            'evidence_type' => $type,
                            'description' => $this->cleanDescription($matchText, $context),
                            'admitted' => $admitted,
                            'weight' => $weight,
                            'ruling' => $this->determineRuling($context),
                        ];
                    }
                }
            }
        }

        return $evidence;
    }

    /**
     * Extract context around evidence mention
     */
    protected function extractContext(string $text, int $position, int $length): string
    {
        $start = max(0, $position - $length);
        $end = min(mb_strlen($text), $position + $length);

        return trim(mb_substr($text, $start, $end - $start));
    }

    /**
     * Determine evidence weight
     */
    protected function determineWeight(string $context): string
    {
        $normalizedContext = mb_strtolower($context);

        foreach ($this->weightIndicators as $weight => $indicators) {
            foreach ($indicators as $indicator) {
                if (preg_match('/' . $indicator . '/ui', $normalizedContext)) {
                    return $weight;
                }
            }
        }

        return 'corroborative'; // Default
    }

    /**
     * Determine if evidence was admitted
     */
    protected function determineAdmission(string $context): ?bool
    {
        $normalizedContext = mb_strtolower($context);

        $admittedPatterns = ['prihva[ćc]en', 'izveden', 'dopu[šs]ten', 'proveden'];
        $excludedPatterns = ['odbijen', 'isklju[čc]en', 'nedopu[šs]ten', 'odbacen'];

        // Check excluded patterns FIRST to avoid false matches (e.g., "nedopušten" contains "dopušten")
        foreach ($excludedPatterns as $indicator) {
            if (preg_match('/' . $indicator . '/ui', $normalizedContext)) {
                return false;
            }
        }

        foreach ($admittedPatterns as $indicator) {
            if (preg_match('/' . $indicator . '/ui', $normalizedContext)) {
                return true;
            }
        }

        return null;
    }

    /**
     * Determine the ruling on evidence
     */
    protected function determineRuling(string $context): string
    {
        $normalizedContext = mb_strtolower($context);

        if (preg_match('/isklju[čc]en|odbijen|nedopu[šs]ten/ui', $normalizedContext)) {
            return 'excluded';
        }
        if (preg_match('/ograni[čc]en|djelomi[čc]no/ui', $normalizedContext)) {
            return 'limited';
        }

        return 'admitted';
    }

    /**
     * Clean up evidence description
     */
    protected function cleanDescription(string $matchText, string $context): string
    {
        // Try to extract a more meaningful description from context
        $description = trim($matchText);

        // If match is short, try to get more context
        if (mb_strlen($description) < 30) {
            // Get the sentence containing the match
            if (preg_match('/[^.]*' . preg_quote($matchText, '/') . '[^.]*\./u', $context, $sentence)) {
                $description = trim($sentence[0]);
            }
        }

        return mb_substr($description, 0, 500);
    }

    /**
     * Get available evidence types
     */
    public function getEvidenceTypes(): array
    {
        return array_keys($this->evidencePatterns);
    }
}

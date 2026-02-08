<?php

namespace App\Services\Graph\Extractors;

use Illuminate\Support\Str;

class LegalArgumentExtractor
{
    /**
     * Markers indicating argument sections in Croatian court decisions
     */
    protected array $sectionMarkers = [
        'plaintiff' => [
            '/[Tt]u[žz]itelj\s+(?:navodi|isti[čc]e|tvrdi|smatra)/u',
            '/[Tt]u[žz]beni\s+zahtjev\s+(?:glasi|temelji)/u',
            '/[Pp]rema\s+navodima\s+tu[žz]itelja/u',
        ],
        'defendant' => [
            '/[Tt]u[žz]enik\s+(?:navodi|isti[čc]e|tvrdi|osporava)/u',
            '/[Oo]brana\s+(?:navodi|isti[čc]e|tvrdi)/u',
            '/[Pp]rema\s+navodima\s+tu[žz]enika/u',
            '/[Oo]krivljenik\s+(?:navodi|tvrdi)/u',
        ],
        'court' => [
            '/[Ss]ud\s+(?:nalazi|utvr[đd]uje|smatra|ocjenjuje)/u',
            '/[Oo]vaj\s+sud\s+(?:nalazi|utvr[đd]uje)/u',
            '/[Pp]rema\s+ocjeni\s+(?:ovog\s+)?suda/u',
            '/[Ss]ukladno\s+(?:utvr[đd]enom|izvedenim\s+dokazima)/u',
        ],
    ];

    /**
     * Argument type indicators
     */
    protected array $typeIndicators = [
        'procedural' => [
            'nadle[žz]nost', 'zastara', 'rokovi', 'pristup sudu', 'procesn',
            'dopu[šs]tenost', 'pravodobnost', 'legitimacij', 'pravni interes',
        ],
        'substantive' => [
            'materijalno pravo', 'osnovanost', 'ugovor', 'obveza', 'odgovornost',
            '[šs]teta', 'naknada', 'pravo vlasni[šs]tva', 'krivnja', 'uzro[čc]nost',
        ],
        'evidentiary' => [
            'dokaz', 'svjedok', 'vje[šs]tak', 'isprava', 'o[čc]evid', 'utvr[đd]eno',
            'dokazano', 'nedokazano', 'teret dokazivanja', 'dokazna snaga',
        ],
    ];

    /**
     * Extract legal arguments from decision text
     */
    public function extract(string $text, string $decisionId): array
    {
        if (empty(trim($text))) {
            return [];
        }

        $arguments = [];
        $sequence = 0;

        // Find the reasoning section (Obrazloženje)
        $reasoningSection = $this->extractReasoningSection($text);
        $searchText = $reasoningSection ?? $text;

        // Extract arguments by position (plaintiff, defendant, court)
        foreach ($this->sectionMarkers as $position => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match_all($pattern, $searchText, $matches, PREG_OFFSET_CAPTURE)) {
                    foreach ($matches[0] as $match) {
                        $sequence++;
                        $startPos = $match[1];
                        $argumentText = $this->extractArgumentText($searchText, $startPos);

                        if (strlen($argumentText) < 50) {
                            $sequence--; // Revert sequence increment
                            continue; // Skip too short matches
                        }

                        $type = $this->classifyArgument($argumentText);
                        $accepted = $position === 'court' ? null : $this->determineAcceptance($text, $argumentText);

                        $arguments[] = [
                            'id' => (string) Str::ulid(),
                            'decision_id' => $decisionId,
                            'argument_type' => $type,
                            'position' => $position,
                            'summary' => $this->generateSummary($argumentText),
                            'full_text' => substr($argumentText, 0, 5000),
                            'accepted' => $accepted,
                            'sequence' => $sequence,
                        ];
                    }
                }
            }
        }

        // Sort by sequence and re-number
        usort($arguments, fn($a, $b) => $a['sequence'] <=> $b['sequence']);

        // Re-number sequences to be sequential from 1
        foreach ($arguments as $index => $argument) {
            $arguments[$index]['sequence'] = $index + 1;
        }

        return $arguments;
    }

    /**
     * Extract the reasoning section (Obrazloženje)
     */
    protected function extractReasoningSection(string $text): ?string
    {
        if (preg_match('/[Oo]brazlo[žz]enje[:\s]*\n(.*?)(?=\n\s*(?:[Pp]ouka|$))/su', $text, $match)) {
            return $match[1];
        }
        return null;
    }

    /**
     * Extract argument text from a starting position
     */
    protected function extractArgumentText(string $text, int $startPos): string
    {
        // Find the next paragraph break or section marker
        $endPatterns = [
            '/\n\s*\n/',  // Double newline
            '/\n\s*[A-ZČĆŽŠĐ][a-zčćžšđ]+\s+(?:navodi|isti[čc]e|tvrdi|nalazi)/u', // Next argument marker
        ];

        $endPos = strlen($text);
        foreach ($endPatterns as $pattern) {
            if (preg_match($pattern, $text, $match, PREG_OFFSET_CAPTURE, $startPos + 10)) {
                $endPos = min($endPos, $match[0][1]);
            }
        }

        // Limit to reasonable length
        $endPos = min($endPos, $startPos + 2000);

        return trim(substr($text, $startPos, $endPos - $startPos));
    }

    /**
     * Classify argument type
     */
    public function classifyArgument(string $text): string
    {
        $normalizedText = mb_strtolower($text);
        $scores = ['procedural' => 0, 'substantive' => 0, 'evidentiary' => 0];

        foreach ($this->typeIndicators as $type => $indicators) {
            foreach ($indicators as $indicator) {
                $pattern = '/' . $indicator . '/ui';
                $scores[$type] += preg_match_all($pattern, $normalizedText);
            }
        }

        arsort($scores);
        $topType = array_key_first($scores);

        return $scores[$topType] > 0 ? $topType : 'substantive';
    }

    /**
     * Generate a brief summary of the argument
     */
    protected function generateSummary(string $text): string
    {
        // Take first sentence or first 200 chars
        $firstSentence = preg_split('/[.!?]\s+/u', $text, 2)[0] ?? '';
        $summary = mb_substr($firstSentence, 0, 200);

        if (mb_strlen($summary) < mb_strlen($firstSentence)) {
            $summary .= '...';
        }

        return trim($summary);
    }

    /**
     * Determine if an argument was accepted by the court
     */
    protected function determineAcceptance(string $fullText, string $argumentText): ?bool
    {
        // Look for acceptance/rejection indicators in court's reasoning
        $argumentWords = array_slice(explode(' ', $argumentText), 0, 10);
        $searchPhrase = implode('.*?', array_map('preg_quote', $argumentWords));

        // Find where this argument is referenced in court's reasoning
        $acceptancePatterns = [
            '/(?:' . $searchPhrase . ').*?(?:prihva[ćc]a|osnovan|utvr[đd]eno|dokazano)/uis' => true,
            '/(?:' . $searchPhrase . ').*?(?:odbija|neosnovan|nedokazano|ne\s+prihva[ćc]a)/uis' => false,
        ];

        foreach ($acceptancePatterns as $pattern => $accepted) {
            if (@preg_match($pattern, $fullText)) {
                return $accepted;
            }
        }

        return null;
    }

    /**
     * Get available argument types
     */
    public function getArgumentTypes(): array
    {
        return array_keys($this->typeIndicators);
    }

    /**
     * Get available positions
     */
    public function getPositions(): array
    {
        return array_keys($this->sectionMarkers);
    }
}

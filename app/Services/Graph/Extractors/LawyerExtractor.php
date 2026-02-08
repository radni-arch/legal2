<?php

namespace App\Services\Graph\Extractors;

class LawyerExtractor
{
    /**
     * Patterns for Croatian lawyer/attorney identification
     */
    protected array $patterns = [
        // Punomoćnik: Ime Prezime, odvjetnik
        '/[Pp]unomo[ćc]nik[:\s]+([A-ZČĆŽŠĐ][a-zčćžšđ]+(?:\s+[A-ZČĆŽŠĐ][a-zčćžšđ]+){1,3})(?=,?\s*odvjetni[kc]|\s|,|$)/u',
        // Odvjetnik Ime Prezime (stop before location markers like "iz")
        '/[Oo]dvjetni[kc][u]?\s+([A-ZČĆŽŠĐ][a-zčćžšđ]+(?:\s+[A-ZČĆŽŠĐ][a-zčćžšđ]+){1,3})(?=\s+iz|\s+|,|$)/u',
        // Odvjetnički ured / Odvjetničko društvo
        '/[Oo]dvjetni[čc]k[io]\s+(?:ured|dru[šs]tvo)\s+["\']?([^"\',.]+)["\']?/u',
        // zastupan po odvjetniku Ime Prezime (stop before location markers)
        '/zastupan[a]?\s+po\s+odvjetnik[u]?\s+([A-ZČĆŽŠĐ][a-zčćžšđ]+(?:\s+[A-ZČĆŽŠĐ][a-zčćžšđ]+){1,3})(?=\s+iz|\s+|,|$)/ui',
        // putem punomoćnika odvjetnika
        '/putem\s+punomo[ćc]nika\s+odvjetnika\s+([A-ZČĆŽŠĐ][a-zčćžšđ]+(?:\s+[A-ZČĆŽŠĐ][a-zčćžšđ]+){1,3})(?=\s+iz|\s+|,|$)/ui',
    ];

    /**
     * Extract lawyers from decision text
     *
     * @param string $text Decision text to analyze
     * @param string|null $caseId Optional case ID for context
     * @return array Array of extracted lawyers
     */
    public function extract(string $text, ?string $caseId = null): array
    {
        if (empty(trim($text))) {
            return [];
        }

        $lawyers = [];
        $seen = [];

        foreach ($this->patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $name = trim($match[1]);
                    $normalizedName = $this->normalizeName($name);

                    // Skip if already seen (deduplication)
                    if (isset($seen[$normalizedName])) {
                        continue;
                    }
                    $seen[$normalizedName] = true;

                    // Try to extract firm name if it's a firm pattern
                    $firm = $this->extractFirm($text, $name);

                    $lawyers[] = [
                        'id' => $this->generateId($normalizedName),
                        'name' => $name,
                        'normalized_name' => $normalizedName,
                        'firm' => $firm,
                        'bar_number' => null, // Rarely available in text
                        'case_id' => $caseId,
                        'role' => $this->determineRole($text, $name),
                    ];
                }
            }
        }

        return $lawyers;
    }

    /**
     * Normalize name for deduplication
     */
    public function normalizeName(string $name): string
    {
        // Convert to lowercase
        $normalized = mb_strtolower($name);

        // Remove titles and prefixes (add space after to handle dots)
        $normalized = preg_replace('/\b(mr\.?|dr\.?|prof\.?|sc\.?|dipl\.?\s*iur\.?)\s*/ui', '', $normalized);

        // Normalize Croatian characters
        $normalized = str_replace(
            ['č', 'ć', 'ž', 'š', 'đ'],
            ['c', 'c', 'z', 's', 'd'],
            $normalized
        );

        // Remove extra whitespace and periods
        $normalized = preg_replace('/[.\s]+/', ' ', $normalized);
        $normalized = trim($normalized);

        return $normalized;
    }

    /**
     * Generate deterministic lawyer ID
     */
    public function generateId(string $normalizedName): string
    {
        return 'lawyer_' . md5($normalizedName);
    }

    /**
     * Try to extract law firm name from context
     */
    protected function extractFirm(string $text, string $lawyerName): ?string
    {
        // Look for firm patterns near the lawyer name
        $patterns = [
            '/[Oo]dvjetni[čc]k[io]\s+(?:ured|dru[šs]tvo)\s+["\']?([^"\',.]{3,50})["\']?/u',
            '/(?:iz|iz\s+ureda)\s+["\']?([^"\',.]{3,50})["\']?/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                return trim($match[1]);
            }
        }

        return null;
    }

    /**
     * Determine if lawyer represents plaintiff or defendant
     */
    protected function determineRole(string $text, string $lawyerName): ?string
    {
        $pos = mb_stripos($text, $lawyerName);
        if ($pos === false) {
            return null;
        }

        // Get context before the name (looking back up to 200 chars)
        $contextLength = min($pos, 200);
        $before = mb_substr($text, $pos - $contextLength, $contextLength);

        // Find the LAST occurrence of role indicators (closest to the lawyer name)
        $plaintiffPos = mb_strripos($before, 'tužitelj');
        $defendantPos = max(
            mb_strripos($before, 'tuženik') ?: -1,
            mb_strripos($before, 'okrivljenik') ?: -1,
            mb_strripos($before, 'optuženik') ?: -1
        );

        // Return the role that appears closest to the lawyer name
        if ($plaintiffPos !== false && $plaintiffPos > $defendantPos) {
            return 'plaintiff';
        }
        if ($defendantPos > -1 && $defendantPos > ($plaintiffPos ?: -1)) {
            return 'defendant';
        }

        return null;
    }
}

<?php

namespace App\Services\LegalMetadata;

/**
 * Detects Croatian court names in legal document text.
 */
class CourtDetector
{
    /**
     * Known Croatian courts with variations.
     */
    private const COURTS = [
        // Supreme Court
        'Vrhovni sud Republike Hrvatske' => ['VSRH', 'Vrhovni sud RH', 'Vrhovni sud'],

        // Constitutional Court
        'Ustavni sud Republike Hrvatske' => ['USRH', 'Ustavni sud RH', 'Ustavni sud'],

        // High Commercial Court
        'Visoki trgovački sud Republike Hrvatske' => ['VTSRH', 'VTS RH', 'Visoki trgovački sud'],

        // High Misdemeanor Court
        'Visoki prekršajni sud Republike Hrvatske' => ['VPSRH', 'VPS RH', 'Visoki prekršajni sud'],

        // High Administrative Court
        'Visoki upravni sud Republike Hrvatske' => ['VUSRH', 'VUS RH', 'Visoki upravni sud'],

        // County Courts
        'Županijski sud' => [],

        // Municipal Courts
        'Općinski sud' => [],

        // Commercial Courts
        'Trgovački sud' => [],

        // Misdemeanor Courts
        'Prekršajni sud' => [],
    ];

    /**
     * Cities with courts
     */
    private const COURT_CITIES = [
        'Zagreb', 'Split', 'Rijeka', 'Osijek', 'Varaždin', 'Zadar',
        'Slavonski Brod', 'Pula', 'Sisak', 'Karlovac', 'Šibenik',
        'Dubrovnik', 'Bjelovar', 'Čakovec', 'Koprivnica', 'Gospić',
        'Pazin', 'Požega', 'Virovitica', 'Vukovar',
    ];

    /**
     * Detect courts mentioned in the text.
     */
    public function detect(string $text): array
    {
        $results = [];
        $seen = [];
        $matchedPositions = []; // Track which parts of text are already matched

        // FIRST: Detect city-specific courts (e.g., "Županijski sud u Zagrebu")
        // These are more specific and should be matched before generic patterns
        $results = array_merge($results, $this->detectCitySpecificCourts($text, $seen, $matchedPositions));

        // SECOND: Detect full court names and abbreviations
        foreach (self::COURTS as $fullName => $variations) {
            // Check full name
            if (($pos = mb_stripos($text, $fullName)) !== false) {
                $matchLen = mb_strlen($fullName);

                // Skip if this position overlaps with an already matched region
                if ($this->overlapsWithMatched($pos, $pos + $matchLen, $matchedPositions)) {
                    continue;
                }

                $canonical = $this->normalizeCourtName($fullName);
                if (! isset($seen[$canonical])) {
                    $results[] = [
                        'raw' => $fullName,
                        'normalized' => $canonical,
                        'type' => $this->classifyCourtType($fullName),
                        'position' => $pos, // Track position for sorting
                    ];
                    $seen[$canonical] = true;
                    // Mark this text region as matched
                    $matchedPositions[] = [$pos, $pos + $matchLen];
                }
            }

            // Check variations
            foreach ($variations as $variation) {
                if (preg_match('/\b'.preg_quote($variation, '/').'\b/iu', $text, $matches, PREG_OFFSET_CAPTURE)) {
                    $matchPos = $matches[0][1];
                    $matchLen = strlen($matches[0][0]);

                    // Skip if this position overlaps with an already matched region
                    if ($this->overlapsWithMatched($matchPos, $matchPos + $matchLen, $matchedPositions)) {
                        continue;
                    }

                    $canonical = $this->normalizeCourtName($fullName);
                    if (! isset($seen[$canonical])) {
                        $results[] = [
                            'raw' => $variation,
                            'normalized' => $canonical,
                            'type' => $this->classifyCourtType($fullName),
                            'position' => $matchPos, // Track position for sorting
                        ];
                        $seen[$canonical] = true;
                        $matchedPositions[] = [$matchPos, $matchPos + $matchLen];
                    }
                }
            }
        }

        // Sort results by text position to maintain document order
        usort($results, fn ($a, $b) => ($a['position'] ?? 0) <=> ($b['position'] ?? 0));

        // Remove position field from final results
        foreach ($results as &$result) {
            unset($result['position']);
        }

        return $results;
    }

    /**
     * Check if a position range overlaps with already matched regions.
     */
    private function overlapsWithMatched(int $start, int $end, array $matchedPositions): bool
    {
        foreach ($matchedPositions as [$matchStart, $matchEnd]) {
            // Check for any overlap
            if ($start < $matchEnd && $end > $matchStart) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect courts with city names.
     */
    private function detectCitySpecificCourts(string $text, array &$seen, array &$matchedPositions): array
    {
        $results = [];

        // Both nominative and genitive case forms
        $courtTypes = [
            'Županijski sud' => 'Županijskog suda',
            'Općinski sud' => 'Općinskog suda',
            'Trgovački sud' => 'Trgovačkog suda',
            'Prekršajni sud' => 'Prekršajnog suda',
        ];

        foreach ($courtTypes as $nominative => $genitive) {
            foreach (self::COURT_CITIES as $city) {
                // Create patterns for both nominative and genitive forms
                // Also allow flexible city endings for locative case (e.g., Šibenik → Šibeniku, Čakovec → Čakovcu)
                // Use first 4-5 characters of city name as base to handle case transformations
                $cityBase = mb_substr($city, 0, max(4, mb_strlen($city) - 2));
                $patterns = [
                    // Nominative with 'u' + locative city (e.g., "Županijski sud u Šibeniku")
                    "/\b{$nominative}\s+u\s+{$cityBase}[a-zčćđšž]*\b/iu",
                    // Nominative without 'u' (e.g., "Županijski sud Zagreb")
                    "/\b{$nominative}\s+{$city}\b/iu",
                    // Genitive with 'u' + locative city (e.g., "Županijskog suda u Zagrebu")
                    "/\b{$genitive}\s+u\s+{$cityBase}[a-zčćđšž]*\b/iu",
                    // Genitive without 'u' (e.g., "Županijskog suda Zagreb")
                    "/\b{$genitive}\s+{$city}\b/iu",
                ];

                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
                        $raw = $matches[0][0];
                        $matchPos = $matches[0][1];
                        $matchLen = strlen($raw);

                        // Skip if this position overlaps with an already matched region
                        if ($this->overlapsWithMatched($matchPos, $matchPos + $matchLen, $matchedPositions)) {
                            continue;
                        }

                        $canonical = $this->normalizeCourtName($raw);

                        if (! isset($seen[$canonical])) {
                            $results[] = [
                                'raw' => $raw,
                                'normalized' => $canonical,
                                'type' => $this->classifyCourtType($nominative),
                                'city' => $city,
                                'position' => $matchPos, // Track position for sorting
                            ];
                            $seen[$canonical] = true;
                            $matchedPositions[] = [$matchPos, $matchPos + $matchLen];
                        }
                    }
                }
            }
        }

        return $results;
    }

    /**
     * Normalize court name for deduplication.
     */
    private function normalizeCourtName(string $name): string
    {
        $normalized = mb_strtolower(trim($name), 'UTF-8');
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        $normalized = str_replace('republike hrvatske', 'rh', $normalized);

        return $normalized;
    }

    /**
     * Classify court type.
     */
    private function classifyCourtType(string $courtName): string
    {
        $name = mb_strtolower($courtName, 'UTF-8');

        if (str_contains($name, 'vrhovni')) {
            return 'supreme';
        }
        if (str_contains($name, 'ustavni')) {
            return 'constitutional';
        }
        if (str_contains($name, 'visoki')) {
            return 'high';
        }
        if (str_contains($name, 'županijski')) {
            return 'county';
        }
        if (str_contains($name, 'općinski')) {
            return 'municipal';
        }
        if (str_contains($name, 'trgovački')) {
            return 'commercial';
        }
        if (str_contains($name, 'prekršajni')) {
            return 'misdemeanor';
        }
        if (str_contains($name, 'upravni')) {
            return 'administrative';
        }

        return 'other';
    }
}

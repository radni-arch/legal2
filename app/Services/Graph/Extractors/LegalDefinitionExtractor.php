<?php

namespace App\Services\Graph\Extractors;

class LegalDefinitionExtractor
{
    /**
     * Definition patterns in Croatian legal text
     */
    protected array $definitionPatterns = [
        // "X" znači Y
        '/["\']([^"\']{2,50})["\'\s]+zna[čc]i\s+([^.]{10,500})\./ui',
        // Pod X se smatra Y
        '/[Pp]od\s+["\']?([^"\']{2,50})["\']?\s+se\s+smatra\s+([^.]{10,500})\./ui',
        // X jest Y / X je Y (in definition context)
        '/["\']([^"\']{2,50})["\']\s+(?:jest|je)\s+([^.]{10,500})\./ui',
        // U smislu ovog zakona, X je Y
        '/[Uu]\s+smislu\s+(?:ovog\s+)?zakona[,:]?\s*["\']?([^"\']{2,50})["\']?\s+(?:jest|je|zna[čc]i)\s+([^.]{10,500})\./ui',
        // Pojam X obuhvaća Y
        '/[Pp]ojam\s+["\']?([^"\']{2,50})["\']?\s+obuhva[ćc]a\s+([^.]{10,500})\./ui',
        // Za potrebe ovog zakona X označava Y
        '/[Zz]a\s+potrebe\s+(?:ovog\s+)?zakona[,:]?\s*["\']?([^"\']{2,50})["\']?\s+ozna[čc]ava\s+([^.]{10,500})\./ui',
    ];

    /**
     * Scope indicators
     */
    protected array $scopeIndicators = [
        'this_law' => ['ovog zakona', 'ovoga zakona', 'ovom zakonu', 'u smislu ovog'],
        'general' => ['op[ćc]enito', 'u pravilu', 'u pravnom smislu'],
        'specific_context' => ['u ovom [čc]lanku', 'za potrebe', 'u slu[čc]aju'],
    ];

    /**
     * Extract definitions from text
     */
    public function extract(string $text, ?string $lawId = null, ?string $articleNumber = null): array
    {
        if (empty(trim($text))) {
            return [];
        }

        $definitions = [];
        $seen = [];

        foreach ($this->definitionPatterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                foreach ($matches as $match) {
                    $term = trim($match[1][0]);
                    $definition = trim($match[2][0]);

                    // Normalize term for deduplication
                    $normalizedTerm = mb_strtolower($term);
                    if (isset($seen[$normalizedTerm])) {
                        continue;
                    }
                    $seen[$normalizedTerm] = true;

                    $position = $match[0][1];
                    $context = $this->extractContext($text, $position, 200);
                    $scope = $this->determineScope($context);

                    $definitions[] = [
                        'id' => $this->generateId($term, $lawId),
                        'term' => $term,
                        'definition' => $this->cleanDefinition($definition),
                        'source_article' => $articleNumber ?? $this->extractArticleNumber($context),
                        'law_id' => $lawId,
                        'scope' => $scope,
                    ];
                }
            }
        }

        return $definitions;
    }

    /**
     * Generate deterministic definition ID
     */
    public function generateId(string $term, ?string $lawId): string
    {
        return 'def_' . md5(mb_strtolower($term) . '_' . ($lawId ?? 'unknown'));
    }

    /**
     * Extract context around definition
     */
    protected function extractContext(string $text, int $position, int $length): string
    {
        $start = max(0, $position - $length);
        $end = min(strlen($text), $position + $length);

        return trim(substr($text, $start, $end - $start));
    }

    /**
     * Determine the scope of the definition
     */
    protected function determineScope(string $context): string
    {
        $normalizedContext = mb_strtolower($context);

        foreach ($this->scopeIndicators as $scope => $indicators) {
            foreach ($indicators as $indicator) {
                if (preg_match('/' . $indicator . '/ui', $normalizedContext)) {
                    return $scope;
                }
            }
        }

        return 'this_law'; // Default
    }

    /**
     * Try to extract article number from context
     */
    protected function extractArticleNumber(string $context): ?string
    {
        if (preg_match('/[Čč]lan(?:ak|ka|ku)?\s+(\d+[a-z]?)/ui', $context, $match)) {
            return $match[1];
        }
        return null;
    }

    /**
     * Clean up definition text
     */
    protected function cleanDefinition(string $definition): string
    {
        // Remove extra whitespace
        $definition = preg_replace('/\s+/', ' ', trim($definition));

        // Limit length
        return mb_substr($definition, 0, 1000);
    }

    /**
     * Get available scopes
     */
    public function getScopes(): array
    {
        return array_keys($this->scopeIndicators);
    }
}

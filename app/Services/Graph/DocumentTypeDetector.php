<?php

namespace App\Services\Graph;

class DocumentTypeDetector
{
    /**
     * Detect document type based on content markers.
     *
     * @param string $content The document content to analyze
     * @return string Document type: 'court_decision', 'law', or 'generic'
     */
    public function detect(string $content): string
    {
        // Check for court decision markers
        if ($this->isCourtDecision($content)) {
            return 'court_decision';
        }

        // Check for law markers
        if ($this->isLaw($content)) {
            return 'law';
        }

        // Default to generic
        return 'generic';
    }

    /**
     * Get extractors for a specific document type.
     *
     * @param string $type Document type
     * @return array Array of extractor class names
     */
    public function getExtractorsForType(string $type): array
    {
        return match ($type) {
            'court_decision' => [
                'LegalTopicExtractor',
                'ArticleExtractor',
                'LegalConceptExtractor',
                'LawyerExtractor',
                'VerdictExtractor',
                'LegalArgumentExtractor',
                'EvidenceExtractor',
                'DateEventExtractor',
                'LegalDefinitionExtractor',
            ],
            'law' => [
                'ArticleExtractor',
                'LegalDefinitionExtractor',
                'LegalConceptExtractor',
                'LegalTopicExtractor',
            ],
            'generic' => [
                'DateEventExtractor',
                'LegalConceptExtractor',
                'LegalTopicExtractor',
            ],
            default => [],
        };
    }

    /**
     * Check if content contains court decision markers.
     *
     * @param string $content Content to check
     * @return bool
     */
    private function isCourtDecision(string $content): bool
    {
        $courtMarkers = [
            'U IME REPUBLIKE HRVATSKE',
            'PRESUDA',
            'RJEŠENJE',
        ];

        foreach ($courtMarkers as $marker) {
            if (mb_stripos($content, $marker, 0, 'UTF-8') !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if content contains law markers.
     *
     * @param string $content Content to check
     * @return bool
     */
    private function isLaw(string $content): bool
    {
        $lawMarkers = [
            'ZAKON',
            'PRAVILNIK',
            'UREDBA',
            'Članak',
        ];

        foreach ($lawMarkers as $marker) {
            if (mb_stripos($content, $marker, 0, 'UTF-8') !== false) {
                return true;
            }
        }

        return false;
    }
}

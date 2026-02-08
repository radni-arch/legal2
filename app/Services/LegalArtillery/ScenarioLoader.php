<?php

namespace App\Services\LegalArtillery;

class ScenarioLoader
{
    private string $scenarioRoot;

    public function __construct(?string $scenarioRoot = null)
    {
        $this->scenarioRoot = $scenarioRoot ?? storage_path('app/legal-artillery/scenarios');
    }

    /**
     * Load scenario manifest and normalize into a structured array.
     */
    public function load(string $scenarioKey): array
    {
        $manifestPath = $this->manifestPath($scenarioKey);

        if (!file_exists($manifestPath)) {
            return [
                'key' => $scenarioKey,
                'title' => $scenarioKey,
                'summary' => null,
                'facts' => [],
                'timeline' => [],
                'documents' => [],
                'metadata' => [],
            ];
        }

        $payload = json_decode((string) file_get_contents($manifestPath), true);

        if (!is_array($payload)) {
            throw new \RuntimeException("Scenario manifest is invalid: {$manifestPath}");
        }

        return $this->normalize($scenarioKey, $payload);
    }

    private function manifestPath(string $scenarioKey): string
    {
        return rtrim($this->scenarioRoot, '/')."/{$scenarioKey}/manifest.json";
    }

    private function normalize(string $scenarioKey, array $payload): array
    {
        $facts = $this->normalizeFacts($payload['facts'] ?? $payload['fact_summary'] ?? []);
        $timeline = $this->normalizeTimeline($payload['timeline'] ?? $payload['events'] ?? []);
        $documents = $this->normalizeDocuments(
            $payload['documents'] ?? $payload['document_list'] ?? $payload['attachments'] ?? []
        );

        return [
            'key' => $scenarioKey,
            'title' => $payload['title'] ?? $payload['name'] ?? $payload['scenario'] ?? null,
            'summary' => $payload['summary'] ?? $payload['overview'] ?? $payload['description'] ?? null,
            'facts' => $facts,
            'timeline' => $timeline,
            'documents' => $documents,
            'metadata' => $payload['metadata'] ?? [],
        ];
    }

    private function normalizeFacts(array $facts): array
    {
        $normalized = [];

        foreach ($facts as $fact) {
            if (is_string($fact)) {
                $normalized[] = [
                    'label' => $fact,
                    'detail' => null,
                    'source' => null,
                    'date' => null,
                ];
                continue;
            }

            if (!is_array($fact)) {
                continue;
            }

            $label = $fact['label'] ?? $fact['title'] ?? $fact['fact'] ?? $fact['summary'] ?? $fact['text'] ?? $fact['description'] ?? null;
            if (!$label) {
                continue;
            }

            $detail = $fact['detail'] ?? $fact['description'] ?? $fact['notes'] ?? $fact['text'] ?? null;
            if ($detail === $label) {
                $detail = null;
            }

            $normalized[] = [
                'label' => $label,
                'detail' => $detail,
                'source' => $fact['source'] ?? null,
                'date' => $fact['date'] ?? null,
            ];
        }

        return $normalized;
    }

    private function normalizeTimeline(array $timeline): array
    {
        $normalized = [];

        foreach ($timeline as $event) {
            if (is_string($event)) {
                $normalized[] = [
                    'date' => null,
                    'title' => $event,
                    'detail' => null,
                    'source' => null,
                ];
                continue;
            }

            if (!is_array($event)) {
                continue;
            }

            $title = $event['title'] ?? $event['event'] ?? $event['summary'] ?? $event['description'] ?? $event['text'] ?? null;
            if (!$title) {
                continue;
            }

            $detail = $event['detail'] ?? $event['description'] ?? $event['details'] ?? $event['notes'] ?? $event['text'] ?? null;
            if ($detail === $title) {
                $detail = null;
            }

            $normalized[] = [
                'date' => $event['date'] ?? $event['when'] ?? null,
                'title' => $title,
                'detail' => $detail,
                'source' => $event['source'] ?? null,
            ];
        }

        return $normalized;
    }

    private function normalizeDocuments(array $documents): array
    {
        $normalized = [];

        foreach ($documents as $document) {
            if (is_string($document)) {
                $normalized[] = [
                    'id' => null,
                    'title' => $document,
                    'reference' => null,
                    'path' => null,
                    'type' => null,
                    'date' => null,
                    'summary' => null,
                    'notes' => null,
                ];
                continue;
            }

            if (!is_array($document)) {
                continue;
            }

            $title = $document['title'] ?? $document['name'] ?? $document['document'] ?? null;
            if (!$title) {
                continue;
            }

            $normalized[] = [
                'id' => $document['id'] ?? $document['key'] ?? $document['code'] ?? null,
                'title' => $title,
                'reference' => $document['reference'] ?? $document['ref'] ?? null,
                'path' => $document['path'] ?? $document['file'] ?? $document['filename'] ?? null,
                'type' => $document['type'] ?? $document['category'] ?? null,
                'date' => $document['date'] ?? $document['issued_at'] ?? null,
                'summary' => $document['summary'] ?? $document['description'] ?? null,
                'notes' => $document['notes'] ?? null,
            ];
        }

        return $normalized;
    }
}

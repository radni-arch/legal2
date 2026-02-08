<?php

namespace App\Services\LegalArtillery;

class DocumentInventory
{
    private array $documents;
    private array $generatedDocs;
    private string $caseDocDir;

    public function __construct()
    {
        $this->documents = config('legal-artillery-documents.documents', []);
        $this->generatedDocs = config('legal-artillery-documents.generated_documents', []);
        $this->caseDocDir = storage_path('app/legal-artillery/case-documents');
    }

    /**
     * Status svakog dokumenta — postoji li fizički?
     */
    public function audit(): array
    {
        $results = ['found' => [], 'missing' => [], 'critical_missing' => []];

        $allDocs = array_merge($this->documents, $this->generatedDocs);

        foreach ($allDocs as $doc) {
            $path = "{$this->caseDocDir}/{$doc['filename']}";
            if (file_exists($path)) {
                $results['found'][] = $doc;
            } else {
                $results['missing'][] = $doc;
                if ($doc['critical'] ?? false) {
                    $results['critical_missing'][] = $doc;
                }
            }
        }

        return $results;
    }

    /**
     * Vrati sortiran popis priloga za profil — s oznakom postoji/nedostaje.
     */
    public function forProfile(string $profileKey): array
    {
        $matchesProfile = function ($doc) use ($profileKey) {
            $profiles = $doc['profiles'] ?? [];
            return in_array($profileKey, $profiles) || in_array('all', $profiles);
        };

        $caseDocs = array_filter($this->documents, $matchesProfile);
        $genDocs = array_filter($this->generatedDocs, $matchesProfile);

        // Case docs sorted by date; generated docs appended after (no date field)
        usort($caseDocs, fn($a, $b) => strcmp($a['date'], $b['date']));

        $allDocs = array_merge($caseDocs, $genDocs);

        return array_map(function ($doc) {
            $path = "{$this->caseDocDir}/{$doc['filename']}";
            $doc['exists'] = file_exists($path);
            $doc['path'] = $doc['exists'] ? $path : null;
            return $doc;
        }, $allDocs);
    }

    /**
     * Generiraj formatirani popis priloga za DOCX.
     */
    public function generateAttachmentList(string $profileKey, string $language = 'hr'): string
    {
        $docs = $this->forProfile($profileKey);
        $descKey = $language === 'en' ? 'description_en' : 'description_hr';

        $lines = [];
        $i = 1;
        foreach ($docs as $doc) {
            $status = $doc['exists'] ? '' : ' [NEDOSTAJE]';
            $note = '';
            if (!$doc['exists'] && ($doc['notes'] ?? null)) {
                $note = " — {$doc['notes']}";
            }
            $lines[] = "Prilog {$i}: {$doc[$descKey]}{$status}{$note}";
            $i++;
        }

        return implode("\n", $lines);
    }

    /**
     * Koliko ključnih dokumenata nedostaje?
     */
    public function criticalMissingCount(): int
    {
        return count($this->audit()['critical_missing']);
    }
}

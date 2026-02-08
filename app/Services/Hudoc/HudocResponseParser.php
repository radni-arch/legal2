<?php

namespace App\Services\Hudoc;

use Carbon\Carbon;
use Illuminate\Support\Collection;

class HudocResponseParser
{
    public function parseSearchResults(array $response): Collection
    {
        $results = $response['results'] ?? [];

        return collect($results)->map(function ($item) {
            return $this->parseCase($item['columns'] ?? $item);
        });
    }

    public function parseCase(array $data): array
    {
        return [
            'item_id' => $data['itemid'] ?? null,
            'application_number' => $this->cleanAppNo($data['appno'] ?? null),
            'ecli' => $data['ecli'] ?? null,
            'case_name' => $data['docname'] ?? null,
            'case_name_short' => $this->extractShortName($data['docname'] ?? ''),
            'respondent_state' => $data['respondent'] ?? null,
            'document_type' => $this->mapDocType($data['doctypebranch'] ?? null),
            'importance' => $data['importance'] ?? null,
            'judgment_date' => $this->parseDate($data['kpdate'] ?? null),
            'violations' => $this->parseArticles($data['violation'] ?? null),
            'non_violations' => $this->parseArticles($data['nonviolation'] ?? null),
            'keywords' => $this->parseKeywords($data['kpthesaurus'] ?? null),
            'has_separate_opinion' => ($data['separateopinion'] ?? 'FALSE') === 'TRUE',
            'language' => $data['languageisocode'] ?? 'ENG',
            'external_sources' => $this->parseExternalSources($data['externalsources'] ?? null),
        ];
    }

    protected function cleanAppNo(?string $appNo): ?string
    {
        if (! $appNo) {
            return null;
        }

        return preg_replace('/[^0-9\/]/', '', $appNo);
    }

    protected function extractShortName(string $fullName): string
    {
        // "CASE OF SMITH v. COUNTRY" -> "Smith v. Country"
        $name = preg_replace('/^CASE OF\s+/i', '', $fullName);

        return ucwords(strtolower($name));
    }

    protected function mapDocType(?string $branch): string
    {
        return match ($branch) {
            'GRANDCHAMBER', 'CHAMBER' => 'JUDGMENT',
            'ADMISSIBILITY', 'COMMITTEE' => 'DECISION',
            'COMMUNICATED' => 'COMMUNICATED',
            'CLIN' => 'LEGAL_SUMMARY',
            'RESOLUTIONS' => 'RESOLUTION',
            'ADVISORYOPINIONS' => 'ADVISORY_OPINION',
            default => 'JUDGMENT',
        };
    }

    protected function parseDate(?string $date): ?string
    {
        if (! $date) {
            return null;
        }

        try {
            return Carbon::parse($date)->format('Y-m-d');
        } catch (\Exception) {
            return null;
        }
    }

    protected function parseArticles(?string $articles): array
    {
        if (! $articles) {
            return [];
        }

        // Parse strings like "8;6-1;P1-1" into array
        return array_filter(
            array_map('trim', explode(';', $articles))
        );
    }

    protected function parseKeywords(?string $keywords): array
    {
        if (! $keywords) {
            return [];
        }

        return array_filter(
            array_map('trim', explode(';', $keywords))
        );
    }

    protected function parseExternalSources(?string $sources): array
    {
        if (! $sources) {
            return [];
        }

        return array_filter(
            array_map('trim', explode(';', $sources))
        );
    }
}

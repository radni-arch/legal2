<?php

namespace App\Services\LegalArtillery;

use App\DTOs\CaseContext;
use App\DTOs\DocumentProfile;
use Illuminate\Support\Facades\Log;

/**
 * Recursively generates legal documents using LLM.
 *
 * Pipeline: (1) Generate outline -> (2) Generate each section -> (3) Polish final document
 */
class RecursiveDocumentWriter
{
    public function __construct(
        private readonly LlmClient $llm,
    ) {}

    /**
     * Full recursive generation pipeline.
     *
     * @param DocumentProfile $profile The document type configuration
     * @param CaseContext $context Case-specific data for interpolation
     * @param array<string, string> $additionalContext Extra context key-value pairs
     * @return array{
     *     profile_key: string,
     *     profile_name: string,
     *     outline: array{sections: array<array{key: string, title: string, guidance: string}>},
     *     sections: array<array{key: string, title: string, content: string}>,
     *     content: string,
     *     generated_at: string
     * }
     */
    public function generate(DocumentProfile $profile, CaseContext $context, array $additionalContext = []): array
    {
        Log::info('LegalArtillery: Starting generation', ['profile' => $profile->key]);

        // Step 1: Outline
        $outline = $this->generateOutline($profile, $context, $additionalContext);
        Log::info('LegalArtillery: Outline generated', ['sections' => count($outline['sections'])]);

        // Step 2: Generate each section
        $sections = [];
        $previousSections = [];
        foreach ($outline['sections'] as $section) {
            $content = $this->generateSection(
                $profile,
                $context,
                $section,
                $previousSections,
                $additionalContext,
            );
            $sections[] = [
                'key' => $section['key'],
                'title' => $section['title'],
                'content' => $content,
            ];
            $previousSections[] = ['title' => $section['title'], 'content' => $content];
        }

        // Step 3: Polish
        $fullContent = $this->polishDocument($profile, $context, $sections);

        Log::info('LegalArtillery: Generation complete', ['profile' => $profile->key]);

        return [
            'profile_key' => $profile->key,
            'profile_name' => $profile->name,
            'outline' => $outline,
            'sections' => $sections,
            'content' => $fullContent,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Generate document outline from profile structure.
     *
     * @param DocumentProfile $profile The document type configuration
     * @param CaseContext $context Case-specific data
     * @param array<string, string> $additional Extra context
     * @return array{sections: array<array{key: string, title: string, guidance: string}>}
     */
    public function generateOutline(DocumentProfile $profile, CaseContext $context, array $additional = []): array
    {
        $toneConfig = config("legal-artillery.tones.{$profile->tone}", []);
        $vars = $context->toTemplateVars();

        $systemPrompt = $this->buildSystemPrompt($profile, $toneConfig);

        $userPrompt = <<<PROMPT
Generiraj OUTLINE (strukturu) za sljedeci pravni dopis.

## Tip dopisa
{$profile->name}

## Primatelj
{$profile->recipientLine()}

## Pravni temelji
{$this->formatList($profile->legalBasis)}

## Strukturalne sekcije (obavezne)
{$this->formatList($profile->structure)}

## Kontekst predmeta
- Broj predmeta: {$vars['case_number']}
- Datum pretrage: {$vars['search_date']}
- Datum arhiviranja: {$vars['archive_date']}
- Adresa pretrage: {$vars['address_searched']}
- Sudac: {$vars['judge']}
- Datum odbijanja: {$vars['denial_date']}

## Dodatni kontekst
{$this->formatAdditionalContext($additional)}

Odgovori ISKLJUCIVO u JSON formatu:
{
    "sections": [
        {
            "key": "section_key",
            "title": "Naslov sekcije",
            "guidance": "Kratki opis sto ova sekcija treba sadrzavati"
        }
    ]
}
PROMPT;

        $response = $this->llm->generate($systemPrompt, $userPrompt, 2048);

        // Parse JSON from response (handle markdown code blocks)
        $json = $this->extractJson($response);
        $decoded = json_decode($json, true);

        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('LegalArtillery: Failed to parse outline JSON', [
                'error' => json_last_error_msg(),
                'response_preview' => substr($response, 0, 200),
            ]);
            return ['sections' => []];
        }

        return $decoded ?? ['sections' => []];
    }

    /**
     * Generate content for a single section.
     *
     * @param DocumentProfile $profile Document configuration
     * @param CaseContext $context Case data
     * @param array{key: string, title: string, guidance: string} $section Section to generate
     * @param array<array{title: string, content: string}> $previousSections Already generated sections for context
     * @param array<string, string> $additional Extra context
     * @return string Generated section content
     */
    public function generateSection(
        DocumentProfile $profile,
        CaseContext $context,
        array $section,
        array $previousSections = [],
        array $additional = [],
    ): string {
        $toneConfig = config("legal-artillery.tones.{$profile->tone}", []);
        $vars = $context->toTemplateVars();

        $systemPrompt = $this->buildSystemPrompt($profile, $toneConfig);

        $prevContext = '';
        if (!empty($previousSections)) {
            $prevContext = "## Dosad napisane sekcije\n";
            foreach ($previousSections as $prev) {
                $prevContext .= "### {$prev['title']}\n{$prev['content']}\n\n";
            }
        }

        $userPrompt = <<<PROMPT
Napisi sekciju: **{$section['title']}**

## Upute za sekciju
{$section['guidance']}

## Pravni temelji za koristenje
{$this->formatList($profile->legalBasis)}

## Podnositelj
Ime: {$vars['sender_name']}
OIB: {$vars['sender_oib']}
Adresa: {$vars['sender_address']}
E-mail: {$vars['sender_email']}
Tel: {$vars['sender_phone']}

## Kontekst predmeta
- Broj predmeta: {$vars['case_number']}
- Referenca naredbe: {$vars['warrant_reference']}
- Datum pretrage: {$vars['search_date']}
- Datum arhiviranja: {$vars['archive_date']}
- KLASA policijskog zahtjeva: {$vars['police_klasa']}
- URBROJ: {$vars['police_urbroj']}
- Pravni temelj naredbe: {$vars['legal_basis_warrant']}
- Prekrsaj: {$vars['suspected_offense']}
- Sudac: {$vars['judge']}
- Datum odbijanja: {$vars['denial_date']}
- Datum odgovora Zupanijskog suda: {$vars['county_response_date']}

{$prevContext}

Pisi SAMO sadrzaj ove sekcije. Bez markdown zaglavlja. Samo tekst spreman za Word dokument.
PROMPT;

        return $this->llm->generate($systemPrompt, $userPrompt);
    }

    private function polishDocument(DocumentProfile $profile, CaseContext $context, array $sections): string
    {
        $toneConfig = config("legal-artillery.tones.{$profile->tone}", []);
        $systemPrompt = $this->buildSystemPrompt($profile, $toneConfig);

        $assembled = '';
        foreach ($sections as $section) {
            $assembled .= "## {$section['title']}\n\n{$section['content']}\n\n---\n\n";
        }

        $userPrompt = <<<PROMPT
Pregledaj i poliraj sljedeci pravni dopis. Osiguraj:
1. Pravni citati su potpuni i tocni
2. Nema ponavljanja izmedju sekcija
3. Ton je konzistentan
4. Tranzicije izmedju sekcija su glatke
5. Sve cinjenice iz konteksta predmeta su ispravne

Vrati FINALNI tekst dokumenta, spreman za formatiranje u Word. Zadrzi strukturu sekcija ali ukloni markdown oznake.

## Dokument za review:

{$assembled}
PROMPT;

        return $this->llm->generate($systemPrompt, $userPrompt);
    }

    private function buildSystemPrompt(DocumentProfile $profile, array $toneConfig): string
    {
        $toneInstruction = $toneConfig['system_instruction'] ?? 'Pisi formalno.';
        $language = $toneConfig['language'] ?? 'hr';

        return <<<SYSTEM
Ti si specijalizirani pravni pisac za hrvatski pravni sustav.

## Tvoj zadatak
Generiras pravne dopise tipa: {$profile->name}

## Ton i stil
{$toneInstruction}

## Jezik
Pisi na jeziku: {$language}

## Pravila
- Citiraj pravne odredbe potpuno (clanak, stavak, tocka, naziv zakona)
- Koristi sluzbenu pravnu terminologiju
- Datume pisi u formatu "DD. mjesec YYYY." (npr. "9. lipnja 2025.")
- Ne izmisljaj cinjenice - koristi SAMO podatke iz konteksta
- Ako nedostaje informacija, oznaci s [DOPUNITI]
- Svaki zahtjev mora biti konkretan i mjerljiv
SYSTEM;
    }

    private function formatList(array $items): string
    {
        return implode("\n", array_map(fn($i) => "- {$i}", $items));
    }

    private function formatAdditionalContext(array $additional): string
    {
        if (empty($additional)) return 'Nema dodatnog konteksta.';
        return implode("\n", array_map(fn($k, $v) => "- {$k}: {$v}", array_keys($additional), $additional));
    }

    private function extractJson(string $text): string
    {
        // Remove markdown code blocks
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $text, $matches)) {
            return trim($matches[1]);
        }
        // Try to find raw JSON
        if (preg_match('/\{[\s\S]*\}/', $text, $matches)) {
            return $matches[0];
        }
        return $text;
    }
}

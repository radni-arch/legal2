<?php

namespace App\Services\LegalArtillery;

use App\DTOs\OpponentResponse;
use App\Models\OpponentResponse as OpponentResponseModel;
use App\Services\HrLegalCitationsDetector;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class ResponseHandler
{
    public function __construct(
        protected LlmClient $llm,
    ) {}

    /**
     * Parse an opponent's response document and extract structured arguments.
     */
    public function parseResponse(
        UploadedFile $file,
        string $extractedContent,
        string $originalProfileKey,
        ?string $runId = null
    ): OpponentResponse {
        Log::info('ResponseHandler: Parsing opponent response', [
            'file' => $file->getClientOriginalName(),
            'profile' => $originalProfileKey,
        ]);

        // Store the uploaded file
        $file->store('legal-artillery/opponent-responses', 'local');

        // Use LLM to analyze the response content
        $systemPrompt = $this->buildAnalysisSystemPrompt($originalProfileKey);
        $userPrompt = $this->buildAnalysisUserPrompt($extractedContent, $originalProfileKey);

        $response = $this->llm->generate($systemPrompt, $userPrompt, 4096);
        $parsed = $this->parseJsonResponse($response);

        $opponentResponse = OpponentResponse::fromArray(array_merge($parsed, [
            'original_file' => $file->getClientOriginalName(),
            'parsed_at' => now()->toIso8601String(),
        ]));

        // Persist to database
        OpponentResponseModel::create([
            'generation_run_id' => $runId,
            'original_profile_key' => $originalProfileKey,
            'responder_type' => $this->inferResponderType($extractedContent),
            'original_filename' => $file->getClientOriginalName(),
            'raw_content' => $extractedContent,
            'summary' => $opponentResponse->summary,
            'key_arguments' => $opponentResponse->keyArguments,
            'weaknesses' => $opponentResponse->weaknesses,
            'recommended_counters' => $opponentResponse->recommendedCounters,
        ]);

        return $opponentResponse;
    }

    /**
     * Parse opponent response from a URL source (e.g., e-komunikacija document link).
     */
    public function parseFromUrl(
        string $url,
        string $extractedContent,
        string $originalProfileKey,
        ?string $runId = null
    ): OpponentResponse {
        Log::info('ResponseHandler: Parsing from URL', ['url' => $url]);

        $systemPrompt = $this->buildAnalysisSystemPrompt($originalProfileKey);
        $userPrompt = $this->buildAnalysisUserPrompt($extractedContent, $originalProfileKey);

        $response = $this->llm->generate($systemPrompt, $userPrompt, 4096);
        $parsed = $this->parseJsonResponse($response);

        $opponentResponse = OpponentResponse::fromArray(array_merge($parsed, [
            'source_url' => $url,
            'parsed_at' => now()->toIso8601String(),
        ]));

        OpponentResponseModel::create([
            'generation_run_id' => $runId,
            'original_profile_key' => $originalProfileKey,
            'responder_type' => $this->inferResponderType($extractedContent),
            'source_url' => $url,
            'raw_content' => $extractedContent,
            'summary' => $opponentResponse->summary,
            'key_arguments' => $opponentResponse->keyArguments,
            'weaknesses' => $opponentResponse->weaknesses,
            'recommended_counters' => $opponentResponse->recommendedCounters,
        ]);

        return $opponentResponse;
    }

    /**
     * Generate a counter-response document targeting each opponent argument.
     */
    public function generateCounterDocument(
        OpponentResponse $response,
        string $originalProfileKey,
        string $counterProfileKey
    ): string {
        Log::info('ResponseHandler: Generating counter-document', [
            'original' => $originalProfileKey,
            'counter' => $counterProfileKey,
        ]);

        $systemPrompt = $this->buildCounterSystemPrompt($counterProfileKey);
        $userPrompt = $this->buildCounterUserPrompt($response, $originalProfileKey);

        return $this->llm->generate($systemPrompt, $userPrompt);
    }

    /**
     * Map citations in document content at the paragraph level.
     *
     * Splits content into paragraphs (by double newline) and uses
     * HrLegalCitationsDetector to extract citations from each paragraph.
     *
     * @param string $content The document content to analyze
     * @return array<int, array> Map of paragraph_index => detected citations
     */
    public function mapCitations(string $content): array
    {
        if (empty(trim($content))) {
            return [];
        }

        $detector = new HrLegalCitationsDetector();
        $paragraphs = preg_split('/\n\s*\n/', $content);
        $citationMap = [];

        foreach ($paragraphs as $index => $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') {
                continue;
            }
            $citationMap[$index] = $detector->detectAll($paragraph);
        }

        return $citationMap;
    }

    protected function buildAnalysisSystemPrompt(string $profileKey): string
    {
        return <<<SYSTEM
Ti si pravni analiticar specijaliziran za hrvatsko pravo.

Analiziraj protivnicki odgovor na nas pravni dopis i identificiraj:
1. Sazetak njihove pozicije
2. Kljucne argumente (svaki s pravnom osnovom koju citiraju)
3. Slabosti u njihovoj argumentaciji
4. Preporucene protuargumente

Odgovori ISKLJUCIVO u JSON formatu:
{
    "summary": "...",
    "key_arguments": [
        {"id": 1, "argument": "...", "legal_basis": "..."},
        ...
    ],
    "weaknesses": ["...", "..."],
    "recommended_counters": [
        {"argument_id": 1, "counter": "..."},
        ...
    ]
}
SYSTEM;
    }

    protected function buildAnalysisUserPrompt(string $content, string $profileKey): string
    {
        return <<<PROMPT
Analiziraj ovaj odgovor na nas dopis tipa '{$profileKey}':

---
{$content}
---

Identificiraj kljucne argumente, slabosti, i preporuci protuargumente.
PROMPT;
    }

    protected function buildCounterSystemPrompt(string $profileKey): string
    {
        $profile = \App\DTOs\DocumentProfile::fromConfig($profileKey);
        $toneConfig = config("legal-artillery.tones.{$profile->tone}", []);
        $toneInstruction = $toneConfig['system_instruction'] ?? 'Pisi formalno.';

        return <<<SYSTEM
Ti si specijalizirani pravni pisac za hrvatski pravni sustav.

Generiras: {$profile->name}

Ton: {$toneInstruction}

Tvoj zadatak je napisati protuodgovor koji:
1. Adresira svaki argument protivne strane
2. Koristi identificirane slabosti protiv njih
3. Citira pravne odredbe precizno
4. Zakljucuje jasnim zahtjevom
SYSTEM;
    }

    protected function buildCounterUserPrompt(OpponentResponse $response, string $originalProfile): string
    {
        $prompt = "## Sazetak protivnickog odgovora\n{$response->summary}\n\n";

        $prompt .= "## Njihovi argumenti\n";
        foreach ($response->keyArguments as $arg) {
            $prompt .= "- [{$arg['id']}] {$arg['argument']} (temelj: {$arg['legal_basis']})\n";
        }
        $prompt .= "\n";

        $prompt .= "## Identificirane slabosti\n";
        foreach ($response->weaknesses as $w) {
            $prompt .= "- {$w}\n";
        }
        $prompt .= "\n";

        $prompt .= "## Preporuceni protuargumenti\n";
        foreach ($response->recommendedCounters as $c) {
            $prompt .= "- Za argument [{$c['argument_id']}]: {$c['counter']}\n";
        }
        $prompt .= "\n";

        $prompt .= "Generiraj kompletan protuodgovor koji adresira sve tocke.";

        return $prompt;
    }

    /**
     * Infer the type of institution that sent the response based on content keywords.
     */
    protected function inferResponderType(string $content): string
    {
        $content = mb_strtolower($content);

        if (str_contains($content, 'zupanijski sud')) {
            return 'county_court';
        }
        if (str_contains($content, 'opcinski sud')) {
            return 'municipal_court';
        }
        if (str_contains($content, 'drzavno odvjetnistvo')) {
            return 'prosecutor';
        }
        if (str_contains($content, 'ministarstvo')) {
            return 'ministry';
        }
        if (str_contains($content, 'ustavni sud')) {
            return 'constitutional_court';
        }

        return 'unknown';
    }

    /**
     * Parse JSON from LLM response, handling code blocks and raw JSON.
     */
    protected function parseJsonResponse(string $response): array
    {
        // Try extracting from markdown code blocks first
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $response, $matches)) {
            $json = trim($matches[1]);
        } elseif (preg_match('/\{[\s\S]*\}/', $response, $matches)) {
            // Try extracting raw JSON object
            $json = $matches[0];
        } else {
            $json = $response;
        }

        $data = json_decode($json, true);
        if ($data === null) {
            throw new \RuntimeException('Failed to parse LLM response as JSON: ' . json_last_error_msg());
        }

        return $data;
    }
}

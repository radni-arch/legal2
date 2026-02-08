<?php

namespace App\Modules\Evidence\Services;

use App\Models\LegalCase;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Log;

/**
 * EvidenceAdmissibilityChecker
 *
 * Checks evidence admissibility under Croatian Criminal Procedure Act (ZKP)
 * Zakon o kaznenom postupku (NN 152/08, 76/09, 80/11, 121/11, 91/12, 143/12, 56/13, 145/13, 152/14, 70/17, 126/19, 126/19)
 */
class EvidenceAdmissibilityChecker
{
    public function __construct(
        protected OpenAIService $openAI
    ) {}

    /**
     * Check if evidence is admissible under Croatian ZKP
     */
    public function check(array $evidence, LegalCase $case): array
    {
        Log::info('EvidenceAdmissibilityChecker - Checking admissibility', [
            'case_id' => $case->id,
            'evidence_type' => $evidence['type'] ?? 'unknown',
        ]);

        $checks = [
            // ZKP Članak 9 - Zakonitost dokaznih radnji
            'lawfulness' => $this->checkLawfulness($evidence, $case),

            // ZKP Članak 10 - Zabrana mučenja i nedopuštenih postupaka
            'no_coercion' => $this->checkNoCoercion($evidence, $case),

            // ZKP Članak 11 - Isključenje nezakonitih dokaza
            'chain_of_custody' => $this->checkChainOfCustody($evidence),

            // ZKP Članak 292 - Uvjeti dopuštenosti dokaza
            'relevance' => $this->checkRelevance($evidence, $case),

            // ZKP Članak 293 - Posebna pravila o dokazima
            'authentication' => $this->checkAuthentication($evidence),

            // ZKP Članak 405 - Žalba na presudu (provjera postupka)
            'procedural_compliance' => $this->checkProceduralCompliance($evidence, $case),
        ];

        // Overall admissibility
        $issues = [];
        foreach ($checks as $checkName => $result) {
            if (! $result['passed']) {
                $issues[] = [
                    'check' => $checkName,
                    'description' => $result['issue'],
                    'legal_basis' => $result['legal_basis'],
                    'severity' => $result['severity'],
                ];
            }
        }

        $admissible = empty($issues);

        return [
            'admissible' => $admissible,
            'confidence' => $this->calculateConfidence($checks),
            'checks' => $checks,
            'issues' => $issues,
            'recommendation' => $admissible
                ? 'Evidence appears admissible'
                : 'Challenge admissibility - grounds exist',
        ];
    }

    /**
     * Check lawfulness (ZKP Članak 9)
     */
    protected function checkLawfulness(array $evidence, LegalCase $case): array
    {
        // Check if evidence was obtained legally
        $obtainedBy = $evidence['obtained_by'] ?? '';
        $method = $evidence['collection_method'] ?? '';

        $issues = [];

        // Check for warrant requirement
        if (in_array($evidence['type'] ?? '', ['physical', 'digital', 'search'])) {
            if (! isset($evidence['warrant']) || ! $evidence['warrant']) {
                $issues[] = 'Possible lack of court warrant';
            }
        }

        // Check for illegal search
        if (stripos($method, 'search') !== false || stripos($method, 'seizure') !== false) {
            if (stripos($method, 'warrantless') !== false || stripos($method, 'bez naloga') !== false) {
                $issues[] = 'Evidence obtained without proper authorization';
            }
        }

        return [
            'passed' => empty($issues),
            'issue' => implode('; ', $issues),
            'legal_basis' => 'ZKP Članak 9 - Zakonitost dokaznih radnji',
            'severity' => ! empty($issues) ? 80 : 0,
        ];
    }

    /**
     * Check for coercion (ZKP Članak 10)
     */
    protected function checkNoCoercion(array $evidence, LegalCase $case): array
    {
        $method = strtolower($evidence['collection_method'] ?? '');
        $description = strtolower($evidence['description'] ?? '');

        $coercionKeywords = [
            'prisilj', 'prisil', 'torture', 'mučenje', 'muč', 'threat', 'prijetnja', 'prijetnju', 'prijetnjom',
            'intimidation', 'zastrašivanje', 'zastrašiv', 'duress', 'prisila', 'prisilom', 'coercion', 'coerce',
        ];

        $issues = [];
        foreach ($coercionKeywords as $keyword) {
            if (stripos($method, $keyword) !== false || stripos($description, $keyword) !== false) {
                $issues[] = "Evidence possibly obtained through coercion: {$keyword}";
            }
        }

        return [
            'passed' => empty($issues),
            'issue' => implode('; ', $issues),
            'legal_basis' => 'ZKP Članak 10 - Zabrana mučenja i nedopuštenih postupaka',
            'severity' => ! empty($issues) ? 100 : 0,
        ];
    }

    /**
     * Check chain of custody (ZKP Članak 11)
     */
    protected function checkChainOfCustody(array $evidence): array
    {
        $chainOfCustody = $evidence['chain_of_custody'] ?? [];

        $issues = [];

        if (empty($chainOfCustody)) {
            $issues[] = 'Chain of custody not documented';
        } else {
            // Check for gaps
            if (count($chainOfCustody) < 2) {
                $issues[] = 'Insufficient chain of custody documentation';
            }

            // Check for temporal gaps
            foreach ($chainOfCustody as $i => $link) {
                if (! isset($link['timestamp']) || ! isset($link['handler'])) {
                    $issues[] = "Chain of custody link {$i} missing required information";
                }
            }
        }

        return [
            'passed' => empty($issues),
            'issue' => implode('; ', $issues),
            'legal_basis' => 'ZKP Članak 11 - Isključenje nezakonitih dokaza',
            'severity' => ! empty($issues) ? 75 : 0,
        ];
    }

    /**
     * Check relevance (ZKP Članak 292)
     */
    protected function checkRelevance(array $evidence, LegalCase $case): array
    {
        // Use LLM to assess relevance
        $prompt = <<<PROMPT
Assess if this evidence is relevant to the case:

Case: {$case->title}
Charges: {$case->description}

Evidence:
Type: {$evidence['type']}
Description: {$evidence['description']}

Is this evidence:
1. Relevant to proving elements of the crime?
2. Probative (has evidential value)?
3. Not overly prejudicial?

Respond with JSON: {"relevant": true/false, "reasoning": "..."}
PROMPT;

        $response = $this->openAI->chat([
            ['role' => 'system', 'content' => 'You are a Croatian legal expert assessing evidence relevance under ZKP.'],
            ['role' => 'user', 'content' => $prompt],
        ], 'gpt-4o-mini', [
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.2,
        ]);

        $result = json_decode($response['choices'][0]['message']['content'], true);

        return [
            'passed' => $result['relevant'] ?? true,
            'issue' => $result['relevant'] ? '' : ($result['reasoning'] ?? 'Evidence not relevant'),
            'legal_basis' => 'ZKP Članak 292 - Uvjeti dopuštenosti dokaza',
            'severity' => $result['relevant'] ? 0 : 60,
        ];
    }

    /**
     * Check authentication (ZKP Članak 293)
     */
    protected function checkAuthentication(array $evidence): array
    {
        $issues = [];

        // Documents need authentication
        if (($evidence['type'] ?? '') === 'documentary') {
            if (! isset($evidence['authenticated']) || ! $evidence['authenticated']) {
                $issues[] = 'Documentary evidence lacks authentication';
            }
        }

        // Digital evidence needs verification
        if (($evidence['type'] ?? '') === 'digital') {
            if (! isset($evidence['hash']) && ! isset($evidence['forensic_verification'])) {
                $issues[] = 'Digital evidence lacks forensic verification';
            }
        }

        // Expert testimony needs qualified expert
        if (($evidence['type'] ?? '') === 'expert') {
            if (! isset($evidence['expert_qualifications'])) {
                $issues[] = 'Expert qualifications not established';
            }
        }

        return [
            'passed' => empty($issues),
            'issue' => implode('; ', $issues),
            'legal_basis' => 'ZKP Članak 293 - Posebna pravila o dokazima',
            'severity' => ! empty($issues) ? 65 : 0,
        ];
    }

    /**
     * Check procedural compliance
     */
    protected function checkProceduralCompliance(array $evidence, LegalCase $case): array
    {
        $issues = [];

        // Check if proper procedures were followed
        $method = $evidence['collection_method'] ?? '';

        // Rights warnings required (ZKP Članak 236)
        if (($evidence['type'] ?? '') === 'testimonial') {
            if (! isset($evidence['rights_warned']) || ! $evidence['rights_warned']) {
                $issues[] = 'Witness not properly warned of rights';
            }
        }

        // Lawyer present for accused statements (ZKP Članak 237)
        if (stripos($method, 'statement') !== false || stripos($method, 'interview') !== false) {
            if (! isset($evidence['lawyer_present'])) {
                $issues[] = 'Unclear if legal counsel was present during statement';
            }
        }

        return [
            'passed' => empty($issues),
            'issue' => implode('; ', $issues),
            'legal_basis' => 'ZKP Članak 405 - Proceduralna usklađenost',
            'severity' => ! empty($issues) ? 70 : 0,
        ];
    }

    /**
     * Calculate confidence in admissibility determination
     */
    protected function calculateConfidence(array $checks): int
    {
        $totalChecks = count($checks);
        $passedChecks = count(array_filter($checks, fn ($c) => $c['passed']));

        return (int) round(($passedChecks / $totalChecks) * 100);
    }
}

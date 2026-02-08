<?php

namespace App\Modules\Evidence\Services;

use App\Models\Evidence;
use App\Models\LegalCase;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Log;

/**
 * SuppressionMotionGenerator
 *
 * Generates Croatian court motions to suppress evidence
 * "Prijedlog za isključenje dokaza" under ZKP
 */
class SuppressionMotionGenerator
{
    public function __construct(
        protected OpenAIService $openAI,
        protected EvidenceAdmissibilityChecker $admissibilityChecker,
        protected ConstitutionalViolationDetector $constitutionalDetector
    ) {}

    /**
     * Generate motion to suppress evidence
     */
    public function generate(LegalCase $case, array $evidenceIds): array
    {
        Log::info('SuppressionMotionGenerator - Generating motion', [
            'case_id' => $case->id,
            'evidence_count' => count($evidenceIds),
        ]);

        // Analyze each piece of evidence
        $evidenceAnalyses = [];
        foreach ($evidenceIds as $evidenceId) {
            // Fetch evidence from database
            $evidence = Evidence::find($evidenceId);

            // Skip if evidence not found
            if ($evidence === null) {
                Log::warning('SuppressionMotionGenerator - Evidence not found', [
                    'evidence_id' => $evidenceId,
                    'case_id' => $case->id,
                ]);
                continue;
            }

            // Convert model to array for compatibility with checkers
            $evidenceArray = $evidence->toArray();

            $evidenceAnalyses[] = [
                'evidence' => $evidence,
                'admissibility' => $this->admissibilityChecker->check($evidenceArray, $case),
                'constitutional' => $this->constitutionalDetector->detect($evidenceArray, $case),
            ];
        }

        // Determine primary grounds
        $grounds = $this->determinePrimaryGrounds($evidenceAnalyses);

        // Generate motion text
        $motionText = $this->generateMotionText($case, $evidenceAnalyses, $grounds);

        // Generate legal authorities
        $authorities = $this->gatherLegalAuthorities($grounds);

        return [
            'motion_type' => 'Prijedlog za isključenje dokaza',
            'case_id' => $case->id,
            'evidence_ids' => $evidenceIds,
            'primary_grounds' => $grounds,
            'legal_authorities' => $authorities,
            'motion_text' => $motionText,
            'filing_instructions' => $this->getFilingInstructions($case),
        ];
    }

    /**
     * Determine primary grounds for suppression
     */
    protected function determinePrimaryGrounds(array $evidenceAnalyses): array
    {
        $grounds = [];

        foreach ($evidenceAnalyses as $analysis) {
            // Constitutional violations
            if (! empty($analysis['constitutional'])) {
                foreach ($analysis['constitutional'] as $violation) {
                    $grounds[] = [
                        'type' => 'constitutional',
                        'basis' => $violation['article'],
                        'description' => $violation['violation'],
                        'severity' => $violation['severity'],
                    ];
                }
            }

            // Admissibility issues
            if (! ($analysis['admissibility']['admissible'] ?? true)) {
                foreach ($analysis['admissibility']['issues'] ?? [] as $issue) {
                    $grounds[] = [
                        'type' => 'procedural',
                        'basis' => $issue['legal_basis'],
                        'description' => $issue['description'],
                        'severity' => $issue['severity'],
                    ];
                }
            }
        }

        // Sort by severity
        usort($grounds, fn ($a, $b) => $b['severity'] <=> $a['severity']);

        return $grounds;
    }

    /**
     * Generate motion text in Croatian legal format
     */
    protected function generateMotionText(
        LegalCase $case,
        array $evidenceAnalyses,
        array $grounds
    ): string {
        $prompt = <<<PROMPT
Generate a formal Croatian court motion to suppress evidence.

Case: {$case->title}
Court: {$case->court}

Grounds for Suppression:
{$this->formatGrounds($grounds)}

Generate a formal "Prijedlog za isključenje dokaza" in Croatian legal format:

Structure:
1. Naslov (Title): PRIJEDLOG ZA ISKLJUČENJE DOKAZA
2. Sud i broj predmeta (Court and case number)
3. Stranke (Parties)
4. Pravna osnova (Legal basis): cite ZKP and Ustav RH articles
5. Činjenično stanje (Facts)
6. Pravna argumentacija (Legal argument)
7. Zahtjev (Request): "Predlažem sudu da isključi sljedeće dokaze..."
8. Prilog (Attachments list)
9. Potpis i datum (Signature and date)

Use formal Croatian legal language and proper citations.
PROMPT;

        $response = $this->openAI->chat([
            ['role' => 'system', 'content' => 'You are a Croatian defense attorney drafting a formal court motion.'],
            ['role' => 'user', 'content' => $prompt],
        ], 'gpt-4o', [
            'temperature' => 0.3,
        ]);

        if (!isset($response['choices'][0]['message']['content'])) {
            throw new \RuntimeException('Invalid OpenAI response structure');
        }

        return $response['choices'][0]['message']['content'];
    }

    /**
     * Format grounds for prompt
     */
    protected function formatGrounds(array $grounds): string
    {
        $formatted = '';
        foreach ($grounds as $i => $ground) {
            $formatted .= ($i + 1).". {$ground['basis']}: {$ground['description']}\n";
        }

        return $formatted;
    }

    /**
     * Gather legal authorities to cite
     */
    protected function gatherLegalAuthorities(array $grounds): array
    {
        $authorities = [
            'statutes' => [],
            'constitution' => [],
            'case_law' => [],
        ];

        foreach ($grounds as $ground) {
            $basis = $ground['basis'];

            // Constitutional provisions
            if (str_contains($basis, 'Ustav')) {
                $authorities['constitution'][] = $basis;
            }
            // ZKP provisions
            elseif (str_contains($basis, 'ZKP')) {
                $authorities['statutes'][] = $basis;
            }
        }

        // Add standard authorities
        $authorities['statutes'][] = 'ZKP Članak 11 - Isključenje nezakonitih dokaza';
        $authorities['constitution'][] = 'Ustav RH Članak 29 - Pravo na pravično suđenje';

        return [
            'statutes' => array_unique($authorities['statutes']),
            'constitution' => array_unique($authorities['constitution']),
            'case_law' => $authorities['case_law'],
        ];
    }

    /**
     * Get filing instructions
     */
    protected function getFilingInstructions(LegalCase $case): array
    {
        return [
            'court' => $case->court ?? 'Županijski sud',
            'filing_method' => 'E-Opis (electronic) ili dostava putem odvjetnika',
            'deadline' => 'Before trial begins or as soon as grounds are discovered',
            'copies_needed' => 'Original + copies for court, prosecution, and case file',
            'filing_fee' => 'No fee for criminal procedure motions',
            'service' => 'Serve copy on State Attorney\'s Office (Državno odvjetništvo)',
        ];
    }
}

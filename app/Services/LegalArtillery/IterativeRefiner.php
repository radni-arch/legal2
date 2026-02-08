<?php

namespace App\Services\LegalArtillery;

use App\DTOs\CaseContext;
use App\DTOs\DocumentProfile;
use App\DTOs\RefinementResult;
use Illuminate\Support\Facades\Log;

/**
 * Iteratively refines legal documents until quality threshold is met.
 *
 * Loop: validate -> if score < threshold, refine -> validate again
 * Repeats until score >= threshold or maxIterations reached.
 */
class IterativeRefiner
{
    private const DEFAULT_QUALITY_THRESHOLD = 8;
    private const DEFAULT_MAX_ITERATIONS = 3;

    public function __construct(
        private readonly LlmClient $llm,
        private readonly ArgumentValidator $validator,
        private readonly int $qualityThreshold = self::DEFAULT_QUALITY_THRESHOLD,
    ) {}

    /**
     * Refine document until it meets quality threshold.
     *
     * @param string $content Initial document content
     * @param DocumentProfile $profile Document profile configuration
     * @param CaseContext $context Case-specific context
     * @param int $maxIterations Maximum refinement iterations
     * @return RefinementResult Final result with refined content and history
     */
    public function refine(
        string $content,
        DocumentProfile $profile,
        CaseContext $context,
        int $maxIterations = self::DEFAULT_MAX_ITERATIONS,
    ): RefinementResult {
        Log::info('IterativeRefiner: Starting refinement', [
            'profile' => $profile->key,
            'max_iterations' => $maxIterations,
            'threshold' => $this->qualityThreshold,
        ]);

        $currentContent = $content;
        $iteration = 0;
        $validationHistory = [];
        $allImprovements = [];

        do {
            $iteration++;

            // Validate current content
            $validation = $this->validator->validate($currentContent, $profile->key);

            Log::info('IterativeRefiner: Validation complete', [
                'iteration' => $iteration,
                'score' => $validation['overall_score'],
                'verdict' => $validation['verdict'],
            ]);

            // Record validation in history
            $validationHistory[] = [
                'iteration' => $iteration,
                'score' => $validation['overall_score'],
                'verdict' => $validation['verdict'],
                'improvements' => $validation['improvements'] ?? [],
                'issues' => $validation['issues'] ?? [],
            ];

            // Collect improvements for tracking
            $allImprovements = array_merge(
                $allImprovements,
                $validation['improvements'] ?? []
            );

            // Check if we should continue refining
            if (!$this->shouldContinue($validation, $iteration, $maxIterations)) {
                Log::info('IterativeRefiner: Stopping refinement', [
                    'iteration' => $iteration,
                    'reason' => $validation['overall_score'] >= $this->qualityThreshold
                        ? 'threshold_met'
                        : 'max_iterations_reached',
                ]);
                break;
            }

            // Generate refined content
            $refinementPrompt = $this->generateRefinementPrompt($currentContent, $validation);
            $systemPrompt = $this->buildRefinementSystemPrompt($profile, $context);

            $currentContent = $this->llm->generate($systemPrompt, $refinementPrompt);

            Log::info('IterativeRefiner: Content refined', [
                'iteration' => $iteration,
                'content_length' => strlen($currentContent),
            ]);

        } while ($iteration < $maxIterations);

        // Get final validation result
        $finalValidation = end($validationHistory);

        return new RefinementResult(
            finalContent: $currentContent,
            iterations: $iteration,
            validationHistory: $validationHistory,
            improvements: array_unique($allImprovements),
            finalVerdict: $finalValidation['verdict'],
            finalScore: $finalValidation['score'],
        );
    }

    /**
     * Determine if refinement should continue.
     *
     * @param array{overall_score: int, verdict: string, improvements: array, issues: array} $validation
     * @param int $iteration Current iteration number
     * @param int $maxIterations Maximum allowed iterations
     * @return bool True if should continue refining
     */
    public function shouldContinue(
        array $validation,
        int $iteration,
        int $maxIterations = self::DEFAULT_MAX_ITERATIONS,
    ): bool {
        // Stop if we've reached max iterations
        if ($iteration >= $maxIterations) {
            return false;
        }

        // Stop if quality threshold is met
        if ($validation['overall_score'] >= $this->qualityThreshold) {
            return false;
        }

        // Continue if score is below threshold and we have iterations left
        return true;
    }

    /**
     * Generate refinement prompt based on validation feedback.
     *
     * @param string $content Current document content
     * @param array{overall_score: int, verdict: string, improvements: array, issues: array} $validation
     * @return string Prompt for LLM refinement
     */
    public function generateRefinementPrompt(string $content, array $validation): string
    {
        $improvements = $validation['improvements'] ?? [];
        $issues = $validation['issues'] ?? [];

        $improvementsList = empty($improvements)
            ? 'Nema specificnih prijedloga.'
            : implode("\n", array_map(fn($i) => "- {$i}", $improvements));

        $issuesList = '';
        if (!empty($issues)) {
            $issuesList = "\n\n## PROBLEMI ZA ISPRAVITI:\n";
            foreach ($issues as $issue) {
                $severity = $issue['severity'] ?? 'unknown';
                $description = $issue['description'] ?? 'No description';
                $issuesList .= "- [{$severity}] {$description}\n";
            }
        }

        return <<<PROMPT
Poboljsaj sljedeci pravni dokument na temelju recenzije.

## TRENUTNA OCJENA: {$validation['overall_score']}/10 ({$validation['verdict']})

## POTREBNA POBOLJSANJA:
{$improvementsList}
{$issuesList}

## DOKUMENT ZA POBOLJSANJE:

{$content}

## UPUTE:

1. Zadrzi svu postojecu strukturu i format
2. Implementiraj SVA navedena poboljsanja
3. Ispravi SVE navedene probleme
4. Pojacaj argumentaciju gdje je potrebno
5. Osiguraj tocnost svih pravnih citata
6. Ne dodaji nove sekcije osim ako je eksplicitno zatrazeno

Vrati SAMO poboljsani dokument, bez dodatnih komentara.
PROMPT;
    }

    /**
     * Build system prompt for refinement LLM call.
     */
    private function buildRefinementSystemPrompt(DocumentProfile $profile, CaseContext $context): string
    {
        $vars = $context->toTemplateVars();

        return <<<SYSTEM
Ti si specijalizirani pravni pisac za hrvatski pravni sustav.

## TVOJ ZADATAK
Poboljsavas pravne dokumente tipa: {$profile->name}

## KONTEKST PREDMETA
- Broj predmeta: {$vars['case_number']}
- Datum pretrage: {$vars['search_date']}
- Sudac: {$vars['judge']}

## PRAVILA
- Citiraj pravne odredbe potpuno (clanak, stavak, tocka, naziv zakona)
- Koristi sluzbenu pravnu terminologiju
- Ne izmisljaj cinjenice
- Svaki zahtjev mora biti konkretan i mjerljiv
- Pojacaj argumentaciju do maksimalne uvjerljivosti
- Svaki argument mora logicki slijediti iz prethodnog
SYSTEM;
    }
}

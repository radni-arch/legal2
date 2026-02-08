<?php

namespace App\Modules\Defence;

use App\Models\LegalCase;
use App\Modules\Defence\Actions\ImproveAccusedStatusAction;
use App\Modules\Defence\Services\DefenseRecommendationService;
use App\Modules\Defence\Services\DefenseStrategyAnalyzer;
use App\Services\LegalReasoning\ArgumentGenerator;
use App\Services\LegalReasoning\RiskAssessor;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Log;

/**
 * DefenceOnlyModule - Focused on defending the accused
 *
 * This module provides specialized defense strategies and actions
 * to improve the legal position of the accused party.
 */
class DefenceOnlyModule
{
    public function __construct(
        protected OpenAIService $openAI,
        protected DefenseStrategyAnalyzer $strategyAnalyzer,
        protected DefenseRecommendationService $recommendationService,
        protected ArgumentGenerator $argumentGenerator,
        protected RiskAssessor $riskAssessor
    ) {}

    /**
     * Analyze and improve the accused's legal status
     *
     * @param  string  $caseId  The case ID
     * @param  array  $options  Additional options
     * @return array Comprehensive defense analysis and recommendations
     */
    public function improveAccusedStatus(string $caseId, array $options = []): array
    {
        Log::info('DefenceOnlyModule - Starting status improvement analysis', [
            'case_id' => $caseId,
        ]);

        $action = new ImproveAccusedStatusAction(
            $this->openAI,
            $this->strategyAnalyzer,
            $this->recommendationService,
            $this->argumentGenerator,
            $this->riskAssessor
        );

        return $action->execute($caseId, $options);
    }

    /**
     * Generate defense strategy
     *
     * @param  string  $caseId  The case ID
     * @return array Defense strategy
     */
    public function generateDefenseStrategy(string $caseId): array
    {
        $case = LegalCase::with('documents')->findOrFail($caseId);

        return $this->strategyAnalyzer->analyze($case);
    }

    /**
     * Get defense recommendations
     *
     * @param  string  $caseId  The case ID
     * @return array Recommendations
     */
    public function getDefenseRecommendations(string $caseId): array
    {
        $case = LegalCase::with('documents')->findOrFail($caseId);

        return $this->recommendationService->generateRecommendations($case);
    }

    /**
     * Analyze weaknesses in prosecution's case
     *
     * @param  string  $caseId  The case ID
     * @return array Weaknesses and exploitable points
     */
    public function analyzeProsecutionWeaknesses(string $caseId): array
    {
        $case = LegalCase::with('documents')->findOrFail($caseId);

        return $this->strategyAnalyzer->findProsecutionWeaknesses($case);
    }

    /**
     * Generate mitigating factors
     *
     * @param  string  $caseId  The case ID
     * @return array Mitigating factors
     */
    public function identifyMitigatingFactors(string $caseId): array
    {
        $case = LegalCase::with('documents')->findOrFail($caseId);

        return $this->strategyAnalyzer->identifyMitigatingFactors($case);
    }

    /**
     * Assess defense strength
     *
     * @param  string  $caseId  The case ID
     * @return array Defense strength assessment
     */
    public function assessDefenseStrength(string $caseId): array
    {
        $case = LegalCase::with('documents')->findOrFail($caseId);

        return $this->strategyAnalyzer->assessDefenseStrength($case);
    }
}

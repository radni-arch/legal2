<?php

namespace App\Http\Controllers;

use App\Http\Requests\Strategy\BuildStrategyRequest;
use App\Http\Requests\Strategy\CreateActionPlanRequest;
use App\Http\Requests\Strategy\GenerateArgumentsRequest;
use App\Models\LegalCase;
use App\Services\LegalReasoning\ArgumentGenerator;
use App\Services\LegalReasoning\RiskAssessor;
use App\Services\LegalReasoning\StrategicPlanner;
use App\Services\LegalReasoning\StrategyBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class StrategyController extends Controller
{
    public function __construct(
        protected StrategyBuilder $strategyBuilder,
        protected ArgumentGenerator $argumentGenerator,
        protected RiskAssessor $riskAssessor,
        protected StrategicPlanner $strategicPlanner
    ) {}

    /**
     * Generate a unique request ID for tracking
     */
    protected function generateRequestId(): string
    {
        return 'strategy_'.Str::uuid();
    }

    /**
     * Build comprehensive case strategy
     *
     * POST /api/strategy/build/{caseId}
     * Body: { "objectives": ["win dismissal", "reduce damages", ...] }
     */
    public function buildStrategy(string $caseId, BuildStrategyRequest $request): JsonResponse
    {
        $case = LegalCase::findOrFail($caseId);
        $this->authorize('view', $case);

        $requestId = $this->generateRequestId();

        try {
            $validated = $request->validated();

            Log::info('Strategy API - Build Strategy', [
                'request_id' => $requestId,
                'case_id' => $caseId,
                'objective_count' => count($validated['objectives']),
            ]);

            $strategy = $this->strategyBuilder->buildCaseStrategy(
                $caseId,
                $validated['objectives']
            );

            return response()->json([
                'success' => true,
                'request_id' => $requestId,
                'case_id' => $caseId,
                'strategy' => $strategy,
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            // Re-throw validation exceptions so Laravel can handle them properly (422 response)
            throw $e;
        } catch (\Exception $e) {
            Log::error('Strategy API Error - Build Strategy', [
                'request_id' => $requestId,
                'case_id' => $caseId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'request_id' => $requestId,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate legal arguments for a case
     *
     * POST /api/strategy/generate-arguments/{caseId}
     * Body: { "objectives": [...] }
     */
    public function generateArguments(string $caseId, GenerateArgumentsRequest $request): JsonResponse
    {
        $case = LegalCase::findOrFail($caseId);
        $this->authorize('view', $case);

        $requestId = $this->generateRequestId();

        try {
            $validated = $request->validated();

            Log::info('Strategy API - Generate Arguments', [
                'request_id' => $requestId,
                'case_id' => $caseId,
            ]);

            $result = $this->argumentGenerator->generateArguments(
                $caseId,
                $validated['objectives']
            );

            return response()->json([
                'success' => true,
                'request_id' => $requestId,
                'case_id' => $caseId,
                'arguments' => $result,
                'argument_count' => $result['total_arguments'] ?? count($result['arguments'] ?? []),
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            // Re-throw validation exceptions so Laravel can handle them properly (422 response)
            throw $e;
        } catch (\Exception $e) {
            Log::error('Strategy API Error - Generate Arguments', [
                'request_id' => $requestId,
                'case_id' => $caseId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'request_id' => $requestId,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Assess risks for a case
     *
     * POST /api/strategy/assess-risks/{caseId}
     */
    public function assessRisks(string $caseId): JsonResponse
    {
        $case = LegalCase::findOrFail($caseId);
        $this->authorize('view', $case);

        $requestId = $this->generateRequestId();

        try {
            Log::info('Strategy API - Assess Risks', [
                'request_id' => $requestId,
                'case_id' => $caseId,
            ]);

            $risks = $this->riskAssessor->assessRisks($caseId);

            return response()->json([
                'success' => true,
                'request_id' => $requestId,
                'case_id' => $caseId,
                'risks' => $risks,
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            // Re-throw validation exceptions so Laravel can handle them properly (422 response)
            throw $e;
        } catch (\Exception $e) {
            Log::error('Strategy API Error - Assess Risks', [
                'request_id' => $requestId,
                'case_id' => $caseId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'request_id' => $requestId,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create action plan for a case
     *
     * POST /api/strategy/action-plan/{caseId}
     * Body: { "strategy": {...} }
     */
    public function createActionPlan(string $caseId, CreateActionPlanRequest $request): JsonResponse
    {
        $case = LegalCase::findOrFail($caseId);
        $this->authorize('view', $case);

        $requestId = $this->generateRequestId();

        try {
            $validated = $request->validated();

            Log::info('Strategy API - Create Action Plan', [
                'request_id' => $requestId,
                'case_id' => $caseId,
            ]);

            $plan = $this->strategicPlanner->createActionPlan(
                $caseId,
                $validated['objectives'] ?? []
            );

            return response()->json([
                'success' => true,
                'request_id' => $requestId,
                'case_id' => $caseId,
                'action_plan' => $plan,
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            // Re-throw validation exceptions so Laravel can handle them properly (422 response)
            throw $e;
        } catch (\Exception $e) {
            Log::error('Strategy API Error - Create Action Plan', [
                'request_id' => $requestId,
                'case_id' => $caseId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'request_id' => $requestId,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get comprehensive strategy with all components
     *
     * POST /api/strategy/comprehensive/{caseId}
     * Body: { "objectives": [...] }
     */
    public function comprehensiveStrategy(string $caseId, BuildStrategyRequest $request): JsonResponse
    {
        $case = LegalCase::findOrFail($caseId);
        $this->authorize('view', $case);

        $requestId = $this->generateRequestId();

        try {
            $validated = $request->validated();

            Log::info('Strategy API - Comprehensive', [
                'request_id' => $requestId,
                'case_id' => $caseId,
            ]);

            // Build comprehensive strategy
            $strategy = $this->strategyBuilder->buildCaseStrategy(
                $caseId,
                $validated['objectives']
            );

            // Add action plan
            $actionPlan = $this->strategicPlanner->createActionPlan(
                $caseId,
                $validated['objectives']
            );

            return response()->json([
                'success' => true,
                'request_id' => $requestId,
                'case_id' => $caseId,
                'comprehensive_strategy' => [
                    'strategy' => $strategy,
                    'action_plan' => $actionPlan,
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Re-throw validation exceptions so Laravel can handle them properly (422 response)
            throw $e;
        } catch (\Exception $e) {
            Log::error('Strategy API Error - Comprehensive', [
                'request_id' => $requestId,
                'case_id' => $caseId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'request_id' => $requestId,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

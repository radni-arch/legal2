<?php

namespace App\Http\Controllers;

use App\Http\Requests\Evidence\AnalyzeEvidenceRequest;
use App\Http\Requests\Evidence\GenerateSuppressionMotionRequest;
use App\Models\LegalCase;
use App\Modules\Evidence\EvidenceAnalysisModule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * EvidenceController
 *
 * API endpoints for evidence analysis under Croatian law
 */
class EvidenceController extends Controller
{
    public function __construct(
        protected EvidenceAnalysisModule $evidenceModule
    ) {}

    /**
     * Analyze evidence for admissibility and constitutional issues
     *
     * POST /api/evidence/analyze/{caseId}
     */
    public function analyzeEvidence(AnalyzeEvidenceRequest $request, string $caseId): JsonResponse
    {
        try {
            $validated = $request->validated();

            $result = $this->evidenceModule->analyzeEvidence($caseId, $validated);

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate motion to suppress evidence
     *
     * POST /api/evidence/suppress-motion/{caseId}
     */
    public function generateSuppressionMotion(GenerateSuppressionMotionRequest $request, string $caseId): JsonResponse
    {
        try {
            $validated = $request->validated();
            $evidenceIds = $request->input('evidence_ids');

            $result = $this->evidenceModule->generateSuppressionMotion($caseId, $evidenceIds);

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check specific evidence admissibility
     *
     * POST /api/evidence/check-admissibility/{caseId}
     */
    public function checkAdmissibility(Request $request, string $caseId): JsonResponse
    {
        $case = LegalCase::findOrFail($caseId);
        $this->authorize('view', $case);

        $validator = Validator::make($request->all(), [
            'evidence' => 'required|array',
            'evidence.type' => 'required|string',
            'evidence.description' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors(),
            ], 422);
        }

        try {
            $evidence = $request->input('evidence');

            $result = $this->evidenceModule->checkAdmissibility($evidence, $caseId);

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Detect constitutional violations
     *
     * POST /api/evidence/constitutional-violations/{caseId}
     */
    public function detectConstitutionalViolations(Request $request, string $caseId): JsonResponse
    {
        $case = LegalCase::findOrFail($caseId);
        $this->authorize('view', $case);

        $validator = Validator::make($request->all(), [
            'evidence' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors(),
            ], 422);
        }

        try {
            $evidence = $request->input('evidence');

            $result = $this->evidenceModule->detectConstitutionalViolations($evidence, $caseId);

            return response()->json([
                'success' => true,
                'data' => [
                    'violations' => $result,
                    'count' => count($result),
                    'severity_max' => ! empty($result) ? max(array_column($result, 'severity')) : 0,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get alternative interpretations
     *
     * POST /api/evidence/alternative-interpretations/{caseId}
     */
    public function getAlternativeInterpretations(Request $request, string $caseId): JsonResponse
    {
        $case = LegalCase::findOrFail($caseId);
        $this->authorize('view', $case);

        $validator = Validator::make($request->all(), [
            'evidence' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors(),
            ], 422);
        }

        try {
            $evidence = $request->input('evidence');

            $result = $this->evidenceModule->getAlternativeInterpretations($evidence, $caseId);

            return response()->json([
                'success' => true,
                'data' => [
                    'interpretations' => $result,
                    'count' => count($result),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Recontextualize evidence (show full context)
     *
     * POST /api/evidence/recontextualize/{caseId}
     */
    public function recontextualizeEvidence(Request $request, string $caseId): JsonResponse
    {
        $case = LegalCase::findOrFail($caseId);
        $this->authorize('view', $case);

        $validator = Validator::make($request->all(), [
            'evidence' => 'required|array',
            'evidence.id' => 'required|string',
            'evidence.type' => 'required|string',
            'evidence.description' => 'required|string',
            'evidence.prosecution_description' => 'sometimes|string',
            'evidence.full_content' => 'sometimes|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors(),
            ], 422);
        }

        try {
            $evidence = $request->input('evidence');

            $result = $this->evidenceModule->recontextualizeEvidence($caseId, $evidence);

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

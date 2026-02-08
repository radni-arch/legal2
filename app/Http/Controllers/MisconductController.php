<?php

namespace App\Http\Controllers;

use App\Http\Requests\Misconduct\AnalyzeMisconductRequest;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\ErrorCode;
use App\Models\LegalCase;
use App\Modules\Misconduct\ProsecutorialMisconductModule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * MisconductController
 *
 * API endpoints for prosecutorial misconduct detection and legal action generation.
 *
 * Endpoints:
 * - POST /api/misconduct/analyze/{caseId} - Analyze case for misconduct
 * - POST /api/misconduct/dismissal-motion/{caseId} - Generate dismissal motion
 * - POST /api/misconduct/complaint/{caseId} - Generate complaint
 * - POST /api/misconduct/appeal/{caseId} - Build appeal
 *
 * All endpoints return JSON responses with standard structure:
 * {
 *     "success": true|false,
 *     "data": {...},
 *     "error": "..." (only on failure)
 * }
 */
class MisconductController extends Controller
{
    public function __construct(
        protected ProsecutorialMisconductModule $misconductModule
    ) {}

    /**
     * Analyze case for prosecutorial misconduct
     *
     * POST /api/misconduct/analyze/{caseId}
     *
     * Request body (optional):
     * {
     *     "options": {
     *         "include_patterns": true,
     *         "min_severity": 50
     *     }
     * }
     *
     * @param  string  $caseId  Case ID or UUID
     */
    public function analyzeMisconduct(AnalyzeMisconductRequest $request, string $caseId): JsonResponse
    {
        Log::info('MisconductController: analyzeMisconduct called', [
            'case_id' => $caseId,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        try {
            // Find the case
            $case = $this->findCase($caseId);
            $this->authorize('view', $case);

            // Get validated data
            $validated = $request->validated();

            // Analyze misconduct
            $result = $this->misconductModule->analyzeMisconduct($caseId, $validated);

            Log::info('MisconductController: Analysis completed', [
                'case_id' => $caseId,
                'total_violations' => $result['summary']['total_violations'] ?? 0,
                'severity' => $result['summary']['severity_level'] ?? 'unknown',
            ]);

            return ApiResponse::success($result);

        } catch (ValidationException $e) {
            Log::warning('MisconductController: Validation failed', [
                'case_id' => $caseId,
                'errors' => $e->errors(),
            ]);

            return ApiResponse::validationError('Validation failed', $e->errors());

        } catch (\Exception $e) {
            Log::error('MisconductController: Analysis failed', [
                'case_id' => $caseId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error(
                ErrorCode::MISCONDUCT_ANALYSIS_FAILED,
                'Failed to analyze misconduct',
                ['message' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Generate dismissal motion based on misconduct
     *
     * POST /api/misconduct/dismissal-motion/{caseId}
     *
     * Request body (optional):
     * {
     *     "min_severity": 85
     * }
     *
     * @param  string  $caseId  Case ID or UUID
     */
    public function generateDismissalMotion(Request $request, string $caseId): JsonResponse
    {
        Log::info('MisconductController: generateDismissalMotion called', [
            'case_id' => $caseId,
            'ip' => $request->ip(),
        ]);

        try {
            // Find the case
            $case = $this->findCase($caseId);
            $this->authorize('update', $case);

            // Validate optional parameters
            $validated = $request->validate([
                'min_severity' => 'integer|min:0|max:100',
            ]);

            // First, analyze the case for misconduct
            $analysisResult = $this->misconductModule->analyzeMisconduct($caseId);

            if (empty($analysisResult['violations'])) {
                Log::info('MisconductController: No violations found', [
                    'case_id' => $caseId,
                ]);

                return ApiResponse::success([
                    'dismissal_warranted' => false,
                    'reason' => 'No misconduct violations detected',
                ]);
            }

            // Filter by min severity if specified
            $violations = $analysisResult['violations'];
            $minSeverity = $validated['min_severity'] ?? 85;

            $filteredViolations = array_filter(
                $violations,
                fn ($v) => ($v['severity'] ?? 0) >= $minSeverity
            );

            if (empty($filteredViolations)) {
                Log::info('MisconductController: No violations meet severity threshold', [
                    'case_id' => $caseId,
                    'min_severity' => $minSeverity,
                ]);

                return ApiResponse::success([
                    'dismissal_warranted' => false,
                    'reason' => "No violations with severity >= {$minSeverity}",
                    'total_violations' => count($violations),
                    'highest_severity' => max(array_column($violations, 'severity')),
                ]);
            }

            // Generate dismissal motion using the module
            $dismissalResult = $this->misconductModule->generateDismissalMotion($caseId);

            Log::info('MisconductController: Dismissal motion generated', [
                'case_id' => $caseId,
                'warranted' => $dismissalResult['dismissal_warranted'] ?? false,
                'grounds_count' => count($dismissalResult['grounds'] ?? []),
            ]);

            return ApiResponse::success($dismissalResult);

        } catch (ValidationException $e) {
            Log::warning('MisconductController: Validation failed', [
                'case_id' => $caseId,
                'errors' => $e->errors(),
            ]);

            return ApiResponse::validationError('Validation failed', $e->errors());

        } catch (\Exception $e) {
            Log::error('MisconductController: Dismissal motion generation failed', [
                'case_id' => $caseId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error(
                ErrorCode::DOCUMENT_GENERATION_FAILED,
                'Failed to generate dismissal motion',
                ['message' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Generate complaint to State Attorney, Judicial Council, or Police
     *
     * POST /api/misconduct/complaint/{caseId}
     *
     * Request body:
     * {
     *     "complaint_type": "state_attorney|judicial_council|police_internal_affairs"
     * }
     *
     * @param  string  $caseId  Case ID or UUID
     */
    public function generateComplaint(Request $request, string $caseId): JsonResponse
    {
        Log::info('MisconductController: generateComplaint called', [
            'case_id' => $caseId,
            'complaint_type' => $request->input('complaint_type'),
            'ip' => $request->ip(),
        ]);

        try {
            // Find the case
            $case = $this->findCase($caseId);
            $this->authorize('update', $case);

            // Validate complaint type (required)
            $validated = $request->validate([
                'complaint_type' => 'required|in:state_attorney,judicial_council,police_internal_affairs',
            ]);

            $complaintType = $validated['complaint_type'];

            // First, analyze the case for misconduct
            $analysisResult = $this->misconductModule->analyzeMisconduct($caseId);

            if (empty($analysisResult['violations'])) {
                Log::info('MisconductController: No violations found for complaint', [
                    'case_id' => $caseId,
                    'complaint_type' => $complaintType,
                ]);

                return ApiResponse::success([
                    'complaint_warranted' => false,
                    'reason' => 'No misconduct violations detected',
                ]);
            }

            // Generate complaint using the module
            $complaintResult = $this->misconductModule->generateComplaint($caseId, $complaintType);

            Log::info('MisconductController: Complaint generated', [
                'case_id' => $caseId,
                'complaint_type' => $complaintType,
                'warranted' => $complaintResult['complaint_warranted'] ?? false,
                'violations_count' => count($complaintResult['violations'] ?? []),
            ]);

            return ApiResponse::success($complaintResult);

        } catch (ValidationException $e) {
            Log::warning('MisconductController: Validation failed', [
                'case_id' => $caseId,
                'errors' => $e->errors(),
            ]);

            return ApiResponse::validationError('Validation failed', $e->errors());

        } catch (\Exception $e) {
            Log::error('MisconductController: Complaint generation failed', [
                'case_id' => $caseId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error(
                ErrorCode::DOCUMENT_GENERATION_FAILED,
                'Failed to generate complaint',
                ['message' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Build appeal based on misconduct
     *
     * POST /api/misconduct/appeal/{caseId}
     *
     * Request body:
     * {
     *     "appeal_type": "zalba|zastita_zakonitosti|ustavna_tuzba"
     * }
     *
     * @param  string  $caseId  Case ID or UUID
     */
    public function buildAppeal(Request $request, string $caseId): JsonResponse
    {
        Log::info('MisconductController: buildAppeal called', [
            'case_id' => $caseId,
            'appeal_type' => $request->input('appeal_type'),
            'ip' => $request->ip(),
        ]);

        try {
            // Find the case
            $case = $this->findCase($caseId);
            $this->authorize('update', $case);

            // Validate appeal type (required)
            $validated = $request->validate([
                'appeal_type' => 'required|in:zalba,zastita_zakonitosti,ustavna_tuzba',
            ]);

            $appealType = $validated['appeal_type'];

            // First, analyze the case for misconduct
            $analysisResult = $this->misconductModule->analyzeMisconduct($caseId);

            if (empty($analysisResult['violations'])) {
                Log::info('MisconductController: No violations found for appeal', [
                    'case_id' => $caseId,
                    'appeal_type' => $appealType,
                ]);

                return ApiResponse::success([
                    'appeal_warranted' => false,
                    'reason' => 'No misconduct violations detected',
                ]);
            }

            // Build appeal using the module
            $appealResult = $this->misconductModule->buildAppeal($caseId, $appealType);

            Log::info('MisconductController: Appeal built', [
                'case_id' => $caseId,
                'appeal_type' => $appealType,
                'warranted' => $appealResult['appeal_warranted'] ?? false,
                'grounds_count' => count($appealResult['grounds'] ?? []),
            ]);

            return ApiResponse::success($appealResult);

        } catch (ValidationException $e) {
            Log::warning('MisconductController: Validation failed', [
                'case_id' => $caseId,
                'errors' => $e->errors(),
            ]);

            return ApiResponse::validationError('Validation failed', $e->errors());

        } catch (\Exception $e) {
            Log::error('MisconductController: Appeal building failed', [
                'case_id' => $caseId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error(
                ErrorCode::DOCUMENT_GENERATION_FAILED,
                'Failed to build appeal',
                ['message' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Find case by ID or UUID
     *
     * @param  string  $caseId  Case ID or UUID
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    protected function findCase(string $caseId): LegalCase
    {
        // Try to find by UUID first
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $caseId)) {
            return LegalCase::where('uuid', $caseId)->firstOrFail();
        }

        // Otherwise find by ID
        return LegalCase::findOrFail($caseId);
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\Reasoning\AnalyzeConflictRequest;
use App\Http\Requests\Reasoning\ApplyDeductiveRequest;
use App\Http\Requests\Reasoning\CalculateAuthorityScoreRequest;
use App\Http\Requests\Reasoning\ParseLogicRequest;
use App\Http\Requests\Reasoning\ResolveConflictRequest;
use App\Services\LegalReasoning\CitationAnalyzer;
use App\Services\LegalReasoning\ConflictResolver;
use App\Services\LegalReasoning\LogicEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ReasoningController extends Controller
{
    public function __construct(
        protected ConflictResolver $conflictResolver,
        protected CitationAnalyzer $citationAnalyzer,
        protected LogicEngine $logicEngine
    ) {}

    /**
     * Generate a unique request ID for tracking
     */
    protected function generateRequestId(): string
    {
        return 'reasoning_'.Str::uuid();
    }

    /**
     * Find conflicts between laws
     *
     * POST /api/reasoning/analyze-conflict
     * Body: { "law_id": "01J..." }
     */
    public function analyzeConflict(AnalyzeConflictRequest $request): JsonResponse
    {
        $requestId = $this->generateRequestId();

        $validated = $request->validated();

        try {
            Log::info('Reasoning API - Analyze Conflict', [
                'request_id' => $requestId,
                'law_id' => $validated['law_id'],
            ]);

            $conflicts = $this->conflictResolver->findConflicts($validated['law_id']);

            return response()->json([
                'success' => true,
                'request_id' => $requestId,
                'law_id' => $validated['law_id'],
                'conflicts' => $conflicts,
                'conflict_count' => count($conflicts),
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            // Re-throw validation exceptions so Laravel can handle them properly (422 response)
            throw $e;
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Re-throw validation exceptions so Laravel can handle them properly (422 response)
            throw $e;
        } catch (\Exception $e) {
            Log::error('Reasoning API Error - Analyze Conflict', [
                'request_id' => $requestId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'request_id' => $requestId,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Resolve conflict between multiple laws
     *
     * POST /api/reasoning/resolve-conflict
     * Body: { "laws": [{"id": "...", "title": "..."}, ...] }
     */
    public function resolveConflict(ResolveConflictRequest $request): JsonResponse
    {
        $requestId = $this->generateRequestId();

        $validated = $request->validated();

        try {
            Log::info('Reasoning API - Resolve Conflict', [
                'request_id' => $requestId,
                'law_count' => count($validated['laws']),
            ]);

            $resolution = $this->conflictResolver->resolveConflict($validated['laws']);

            return response()->json([
                'success' => true,
                'request_id' => $requestId,
                'resolution' => $resolution,
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            // Re-throw validation exceptions so Laravel can handle them properly (422 response)
            throw $e;
        } catch (\Exception $e) {
            Log::error('Reasoning API Error - Resolve Conflict', [
                'request_id' => $requestId,
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
     * Calculate authority score for a court decision
     *
     * POST /api/reasoning/authority-score
     * Body: { "decision_id": "01J..." }
     */
    public function calculateAuthorityScore(CalculateAuthorityScoreRequest $request): JsonResponse
    {
        $requestId = $this->generateRequestId();

        $validated = $request->validated();

        try {
            Log::info('Reasoning API - Authority Score', [
                'request_id' => $requestId,
                'decision_id' => $validated['decision_id'],
            ]);

            $analysis = $this->citationAnalyzer->analyzeAuthority($validated['decision_id']);

            return response()->json([
                'success' => true,
                'request_id' => $requestId,
                'decision_id' => $validated['decision_id'],
                'analysis' => $analysis,
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            // Re-throw validation exceptions so Laravel can handle them properly (422 response)
            throw $e;
        } catch (\Exception $e) {
            Log::error('Reasoning API Error - Authority Score', [
                'request_id' => $requestId,
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
     * Parse logical structure from law text
     *
     * POST /api/reasoning/parse-logic
     * Body: { "law_text": "..." }
     */
    public function parseLogic(ParseLogicRequest $request): JsonResponse
    {
        $requestId = $this->generateRequestId();

        $validated = $request->validated();

        try {
            Log::info('Reasoning API - Parse Logic', [
                'request_id' => $requestId,
                'text_length' => strlen($validated['law_text']),
            ]);

            $structure = $this->logicEngine->parseLogicStructure($validated['law_text']);

            return response()->json([
                'success' => true,
                'request_id' => $requestId,
                'structure' => $structure,
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            // Re-throw validation exceptions so Laravel can handle them properly (422 response)
            throw $e;
        } catch (\Exception $e) {
            Log::error('Reasoning API Error - Parse Logic', [
                'request_id' => $requestId,
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
     * Apply deductive reasoning to facts and rules
     *
     * POST /api/reasoning/apply-deductive
     * Body: { "facts": [...], "rules": [...] }
     */
    public function applyDeductive(ApplyDeductiveRequest $request): JsonResponse
    {
        $requestId = $this->generateRequestId();

        $validated = $request->validated();

        try {
            Log::info('Reasoning API - Apply Deductive', [
                'request_id' => $requestId,
                'fact_count' => count($validated['facts']),
                'rule_count' => count($validated['rules']),
            ]);

            $inferences = $this->logicEngine->applyDeductiveReasoning(
                $validated['facts'],
                $validated['rules']
            );

            return response()->json([
                'success' => true,
                'request_id' => $requestId,
                'inferences' => $inferences,
                'inference_count' => count($inferences),
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            // Re-throw validation exceptions so Laravel can handle them properly (422 response)
            throw $e;
        } catch (\Exception $e) {
            Log::error('Reasoning API Error - Apply Deductive', [
                'request_id' => $requestId,
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

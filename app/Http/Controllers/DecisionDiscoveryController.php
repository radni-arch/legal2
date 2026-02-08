<?php

namespace App\Http\Controllers;

use App\Http\Requests\Decision\DiscoverDecisionsRequest;
use App\Services\DecisionDiscoveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Decision Discovery Controller
 *
 * Provides REST API endpoints for discovering and ingesting court decisions
 * from odluke.sudovi.hr
 *
 * Sprint 7: UX Features Completion - Worker G
 */
class DecisionDiscoveryController extends Controller
{
    protected DecisionDiscoveryService $discoveryService;

    public function __construct(DecisionDiscoveryService $discoveryService)
    {
        $this->discoveryService = $discoveryService;
    }

    /**
     * Start a new discovery search
     *
     * POST /api/decisions/discover
     *
     * Request body:
     * {
     *   "keywords": "string (required)",
     *   "court": "string (optional)",
     *   "date_from": "YYYY-MM-DD (optional)",
     *   "date_to": "YYYY-MM-DD (optional)",
     *   "decision_type": "string (optional)",
     *   "limit": "integer (optional, default 50)",
     *   "page": "integer (optional, default 1)"
     * }
     */
    public function discover(DiscoverDecisionsRequest $request): JsonResponse
    {
        try {
            $params = $request->validated();
            $discovery = $this->discoveryService->startDiscovery($params);

            return response()->json([
                'success' => true,
                'discovery' => $discovery,
            ]);
        } catch (\Throwable $e) {
            Log::error('[DecisionDiscoveryController] Discovery failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Discovery failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * List all discoveries
     *
     * GET /api/decisions/discoveries
     */
    public function listDiscoveries(): JsonResponse
    {
        try {
            $discoveries = $this->discoveryService->listDiscoveries();

            return response()->json([
                'success' => true,
                'data' => $discoveries,
            ]);
        } catch (\Throwable $e) {
            Log::error('[DecisionDiscoveryController] List discoveries failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to list discoveries: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get discovery details by ID
     *
     * GET /api/decisions/discoveries/{id}
     */
    public function getDiscovery(string $id): JsonResponse
    {
        try {
            $discovery = $this->discoveryService->getDiscovery($id);

            if (! $discovery) {
                return response()->json([
                    'success' => false,
                    'error' => 'Discovery not found or expired',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'discovery' => $discovery,
            ]);
        } catch (\Throwable $e) {
            Log::error('[DecisionDiscoveryController] Get discovery failed', [
                'discovery_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to get discovery: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Ingest selected decisions
     *
     * POST /api/decisions/discoveries/{id}/ingest
     *
     * Request body:
     * {
     *   "decision_ids": ["id1", "id2", "id3"]
     * }
     */
    public function ingestDecisions(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'decision_ids' => 'required|array|min:1|max:50',
            'decision_ids.*' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Check if discovery exists
            $discovery = $this->discoveryService->getDiscovery($id);
            if (! $discovery) {
                return response()->json([
                    'success' => false,
                    'error' => 'Discovery not found or expired',
                ], 404);
            }

            $decisionIds = $validator->validated()['decision_ids'];
            $result = $this->discoveryService->ingestDecisions($id, $decisionIds);

            return response()->json([
                'success' => true,
                'result' => $result,
            ]);
        } catch (\Throwable $e) {
            Log::error('[DecisionDiscoveryController] Ingest decisions failed', [
                'discovery_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Ingestion failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get discovery statistics
     *
     * GET /api/decisions/discoveries/{id}/stats
     */
    public function getDiscoveryStatistics(string $id): JsonResponse
    {
        try {
            $stats = $this->discoveryService->getDiscoveryStatistics($id);

            if (! $stats) {
                return response()->json([
                    'success' => false,
                    'error' => 'Discovery not found or expired',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'statistics' => $stats,
            ]);
        } catch (\Throwable $e) {
            Log::error('[DecisionDiscoveryController] Get statistics failed', [
                'discovery_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to get statistics: '.$e->getMessage(),
            ], 500);
        }
    }
}

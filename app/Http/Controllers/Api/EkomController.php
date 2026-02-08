<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ListOtpravciRequest;
use App\Http\Requests\Api\ListPodnesciRequest;
use App\Http\Requests\Api\ListPredmetiRequest;
use App\Jobs\EkomSyncAllJob;
use App\Models\EkomOtpravak;
use App\Models\EkomPodnesak;
use App\Models\EkomPredmet;
use Illuminate\Http\JsonResponse;

/**
 * EkomController - Internal REST API for EKOM Data
 *
 * Provides programmatic access to EKOM entities:
 * - Predmeti (cases)
 * - Podnesci (submissions)
 * - Otpravci (dispatches)
 *
 * All endpoints require API token authentication.
 */
class EkomController extends Controller
{
    /**
     * List predmeti with pagination and optional search/filter
     *
     * GET /api/ekom/predmeti
     *
     * Query Parameters:
     * - search: Filter by oznaka (partial match, max 255 chars)
     * - status: Filter by status (must be valid status from EkomPredmet::STATUSES)
     * - per_page: Number of items per page (default: 20, max: 100)
     */
    public function listPredmeti(ListPredmetiRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $predmeti = EkomPredmet::query()
            ->when($validated['search'] ?? null, fn ($q, $s) => $q->where('oznaka', 'like', "%{$s}%"))
            ->when($validated['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('last_synced_at')
            ->paginate($validated['per_page'] ?? 20);

        return response()->json($predmeti);
    }

    /**
     * Get a single predmet by remote_id
     *
     * GET /api/ekom/predmeti/{remoteId}
     */
    public function showPredmet(string $remoteId): JsonResponse
    {
        $predmet = EkomPredmet::where('remote_id', $remoteId)->firstOrFail();

        return response()->json(['data' => $predmet]);
    }

    /**
     * List podnesci with pagination and optional status filter
     *
     * GET /api/ekom/podnesci
     *
     * Query Parameters:
     * - status: Filter by status (must be valid status from EkomPodnesak::STATUSES)
     * - per_page: Number of items per page (default: 20, max: 100)
     */
    public function listPodnesci(ListPodnesciRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $podnesci = EkomPodnesak::query()
            ->when($validated['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('last_synced_at')
            ->paginate($validated['per_page'] ?? 20);

        return response()->json($podnesci);
    }

    /**
     * List otpravci with pagination and optional filters
     *
     * GET /api/ekom/otpravci
     *
     * Query Parameters:
     * - status: Filter by status (must be valid status from EkomOtpravak::STATUSES)
     * - pending: If true, only show unconfirmed (vrijeme_potvrde_primitka is null)
     * - per_page: Number of items per page (default: 20, max: 100)
     */
    public function listOtpravci(ListOtpravciRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $otpravci = EkomOtpravak::query()
            ->when($validated['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($validated['pending'] ?? false, fn ($q) => $q->whereNull('vrijeme_potvrde_primitka'))
            ->orderByDesc('last_synced_at')
            ->paginate($validated['per_page'] ?? 20);

        return response()->json($otpravci);
    }

    /**
     * Trigger EKOM sync job
     *
     * POST /api/ekom/sync
     *
     * Dispatches EkomSyncAllJob to synchronize all EKOM entities.
     */
    public function triggerSync(): JsonResponse
    {
        EkomSyncAllJob::dispatch();

        return response()->json(['message' => 'Sync job dispatched'], 202);
    }
}

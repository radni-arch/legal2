<?php

namespace App\Http\Controllers\Api;

use App\Agents\Contracts\LegalArtilleryAgentContract;
use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateDocumentRequest;
use App\Http\Resources\DocumentGenerationRunResource;
use App\Models\DocumentGenerationRun;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * API Controller for Document Generation
 *
 * Exposes the LegalArtilleryAgent functionality via REST API.
 *
 * Endpoints:
 * - POST /api/documents/generate - Start document generation
 * - GET /api/documents/runs/{id} - Get specific run with relationships
 * - GET /api/documents/runs - List user's runs with filtering and pagination
 * - POST /api/documents/runs/{id}/approve - Approve a generation run
 * - POST /api/documents/runs/{id}/dispatch - Dispatch an approved run
 */
class DocumentGenerationController extends Controller
{
    public function __construct(
        protected LegalArtilleryAgentContract $agent
    ) {}

    /**
     * Start a new document generation
     *
     * POST /api/documents/generate
     */
    public function generate(GenerateDocumentRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        try {
            // Execute the unified agent
            $run = $this->agent->fire(
                profileKey: $validated['document_type'],
                userId: $user->id,
                additionalContext: array_filter([
                    'context' => $validated['context'] ?? null,
                    'case_id' => $validated['case_id'] ?? null,
                    'evidence_ids' => $validated['evidence_ids'] ?? null,
                    'decision_ids' => $validated['decision_ids'] ?? null,
                    'law_ids' => $validated['law_ids'] ?? null,
                    'additional_context' => $validated['additional_context'] ?? null,
                    'escalation_confirmed' => $validated['confirm_escalation'] ?? null,
                ]),
                sendEmail: $validated['send_email'] ?? false,
                maxIterations: $validated['max_iterations'] ?? null,
            );

            Log::info('Document generation completed', [
                'run_id' => $run->id,
                'user_id' => $user->id,
                'document_type' => $run->document_type,
                'status' => $run->status,
            ]);

            return response()->json([
                'data' => new DocumentGenerationRunResource($run),
            ], 201);

        } catch (\InvalidArgumentException $e) {
            Log::warning('Document generation blocked', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'document_type' => $validated['document_type'] ?? 'unknown',
            ]);

            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Document generation failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'document_type' => $validated['document_type'] ?? 'unknown',
            ]);

            return response()->json([
                'message' => 'Document generation failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get a specific document generation run
     *
     * GET /api/documents/runs/{id}
     */
    public function show(string $id): JsonResponse
    {
        $user = auth()->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // Find run with relationships (eager load to avoid N+1)
        $run = DocumentGenerationRun::with(['iterations', 'context'])
            ->find($id);

        if (! $run) {
            return response()->json([
                'message' => 'Document generation run not found',
            ], 404);
        }

        // Check authorization - users can only view their own runs
        if ($run->user_id !== $user->id) {
            return response()->json([
                'message' => 'This action is unauthorized.',
            ], 403);
        }

        return response()->json([
            'data' => new DocumentGenerationRunResource($run),
        ], 200);
    }

    /**
     * Approve a document generation run
     *
     * POST /api/documents/runs/{id}/approve
     */
    public function approve(string $id, Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $run = DocumentGenerationRun::find($id);

        if (! $run) {
            return response()->json(['message' => 'Run not found'], 404);
        }

        if ($run->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $run = $this->agent->approveRun($id, $user->id, $request->input('notes'));

            return response()->json([
                'data' => new DocumentGenerationRunResource($run),
                'message' => 'Run approved successfully',
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Dispatch an approved document generation run
     *
     * POST /api/documents/runs/{id}/dispatch
     */
    public function dispatch(string $id, Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $run = DocumentGenerationRun::find($id);

        if (! $run) {
            return response()->json(['message' => 'Run not found'], 404);
        }

        if ($run->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $run = $this->agent->dispatchApproved(
                runId: $id,
                sendEmail: $request->boolean('send_email', false),
                asDraft: $request->boolean('as_draft', false),
                toEmail: $request->input('to_email'),
                submitEkom: $request->boolean('submit_ekom', false),
            );

            return response()->json([
                'data' => new DocumentGenerationRunResource($run),
                'message' => 'Run dispatched successfully',
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Preview dispatch payloads for an approved run.
     *
     * POST /api/documents/runs/{id}/preview-dispatch
     */
    public function previewDispatch(string $id, Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $run = DocumentGenerationRun::find($id);

        if (! $run) {
            return response()->json(['message' => 'Run not found'], 404);
        }

        if ($run->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $preview = $this->agent->previewDispatch(
                runId: $id,
                sendEmail: $request->boolean('send_email', false),
                asDraft: $request->boolean('as_draft', false),
                toEmail: $request->input('to_email'),
                submitEkom: $request->boolean('submit_ekom', false),
            );

            return response()->json([
                'data' => $preview,
                'message' => 'Preview generated successfully',
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Export audit report for a document generation run.
     *
     * GET /api/documents/runs/{id}/audit
     */
    public function audit(string $id, Request $request): JsonResponse|HttpResponse
    {
        $user = auth()->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $run = DocumentGenerationRun::with(['iterations', 'context', 'user', 'approver'])->find($id);

        if (! $run) {
            return response()->json(['message' => 'Run not found'], 404);
        }

        $this->authorize('viewAudit', $run);

        $piiRedactor = app(\App\Services\LegalArtillery\PiiRedactor::class);

        $auditData = [
            'run_id' => $run->id,
            'document_type' => $run->document_type,
            'status' => $run->status,
            'created_at' => $run->created_at?->toIso8601String(),
            'updated_at' => $run->updated_at?->toIso8601String(),
            'user' => [
                'id' => $run->user_id,
                'name' => $run->user?->name ?? 'Unknown',
            ],
            'approval' => [
                'approved_at' => $run->approved_at?->toIso8601String(),
                'approved_by' => $run->approved_by,
                'approver_name' => $run->approver?->name ?? null,
                'notes' => $run->approval_notes,
            ],
            'generation' => [
                'total_iterations' => $run->total_iterations,
                'final_score' => $run->final_score,
                'stopped_reason' => $run->stopped_reason,
                'model_config' => $piiRedactor->redactArray($run->model_config ?? []),
            ],
            'iterations' => $run->iterations->map(fn ($i) => [
                'iteration_number' => $i->iteration_number,
                'phase' => $i->phase,
                'weighted_score' => $i->weighted_score,
                'improvement_delta' => $i->improvement_delta,
                'ai_model_used' => $i->ai_model_used,
                'tokens_used' => $i->tokens_used,
                'cost_estimate' => $i->cost_estimate,
            ])->toArray(),
            'dispatch' => [
                'result' => $run->model_config['dispatch_result'] ?? null,
                'dispatched_at' => $run->model_config['dispatched_at'] ?? null,
            ],
            'document_hash' => $run->final_document ? hash('sha256', $run->final_document) : null,
            'exported_at' => now()->toIso8601String(),
        ];

        $format = strtolower((string) $request->query('format', 'json'));

        if (! in_array($format, ['json', 'pdf'], true)) {
            return response()->json(['message' => 'Unsupported format requested.'], 400);
        }

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('pdf.audit', [
                'auditData' => $auditData,
            ])->setPaper('a4', 'portrait');

            $filename = 'document-generation-audit-'.$run->id.'.pdf';

            return $pdf->stream($filename);
        }

        return response()->json(['data' => $auditData]);
    }

    /**
     * List document generation runs for the authenticated user
     *
     * GET /api/documents/runs
     *
     * Supports:
     * - Pagination (15 per page by default)
     * - Filtering by status and document_type
     * - Sorted by newest first
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // Start query - only user's runs
        $query = DocumentGenerationRun::where('user_id', $user->id);

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by document_type if provided
        if ($request->has('document_type')) {
            $query->where('document_type', $request->input('document_type'));
        }

        // Sort by newest first
        $query->orderBy('created_at', 'desc');

        // Paginate (15 per page default)
        $perPage = min((int) $request->input('per_page', 15), 100);
        $runs = $query->paginate($perPage);

        return DocumentGenerationRunResource::collection($runs)
            ->response()
            ->setStatusCode(200);
    }
}

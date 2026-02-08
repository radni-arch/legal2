<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LearningOpportunity;
use App\Notifications\LearningFeedbackSubmitted;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Learning Feedback API Controller
 *
 * Sprint 5.2: Human Feedback Integration
 *
 * Handles submission of human feedback on learning opportunities.
 */
class LearningFeedbackController extends Controller
{
    /**
     * Submit feedback for a learning opportunity
     */
    public function submitFeedback(Request $request, int $opportunityId): JsonResponse
    {
        // Find opportunity
        $opportunity = LearningOpportunity::find($opportunityId);

        if (! $opportunity) {
            return response()->json([
                'success' => false,
                'message' => 'Learning opportunity not found',
            ], 404);
        }

        // Check if already reviewed
        if ($opportunity->status === 'reviewed') {
            return response()->json([
                'success' => false,
                'message' => 'This learning opportunity has already been reviewed',
            ], 422);
        }

        // Validate request
        $validated = $request->validate([
            'human_label' => 'required|array',
        ]);

        try {
            // Mark as reviewed with feedback
            $opportunity->markAsReviewed(
                userId: $request->user()->id,
                humanLabel: $validated['human_label']
            );

            Log::info('Learning feedback submitted', [
                'opportunity_id' => $opportunity->id,
                'user_id' => $request->user()->id,
                'opportunity_type' => $opportunity->opportunity_type,
            ]);

            // Send notification
            Notification::send($request->user(), new LearningFeedbackSubmitted($opportunity));

            return response()->json([
                'success' => true,
                'message' => 'Feedback submitted successfully',
                'opportunity' => [
                    'id' => $opportunity->id,
                    'status' => $opportunity->status,
                    'reviewed_at' => $opportunity->reviewed_at?->toISOString(),
                    'reviewed_by' => $opportunity->reviewed_by,
                    'human_label' => $opportunity->human_label,
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to submit learning feedback', [
                'opportunity_id' => $opportunityId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit feedback',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

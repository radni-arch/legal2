<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Test Controller for Error Recovery Testing
 *
 * This controller provides endpoints specifically for testing error recovery scenarios.
 * Not used in production - only for testing.
 */
class TestErrorRecoveryController extends Controller
{
    /**
     * Analyze evidence endpoint for testing
     */
    public function analyze(Request $request): JsonResponse
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'evidence_text' => 'required|string|max:100000',
            'evidence_type' => 'string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Return success response
        return response()->json([
            'success' => true,
            'message' => 'Analysis complete',
            'data' => [
                'evidence_text' => $request->evidence_text,
                'evidence_type' => $request->evidence_type ?? 'unknown',
            ],
        ]);
    }
}

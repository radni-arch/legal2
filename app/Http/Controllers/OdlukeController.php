<?php

namespace App\Http\Controllers;

use App\Agents\OdlukeAgent;
use App\Http\Requests\Odluke\ExecuteOdlukeAgentRequest;
use App\Http\Requests\Odluke\GetOdlukeStatusRequest;
use App\Http\Responses\ApiResponse;

class OdlukeController extends Controller
{
    /**
     * Execute OdlukeAgent query (async supported).
     */
    public function execute(ExecuteOdlukeAgentRequest $request)
    {
        $validated = $request->validated();

        $result = OdlukeAgent::executeAsync(
            query: $validated['query'],
            context: $validated['context'] ?? [],
            async: $validated['async'] ?? true
        );

        return ApiResponse::success($result);
    }

    /**
     * Get status of async query.
     */
    public function status(GetOdlukeStatusRequest $request)
    {
        $validated = $request->validated();

        $status = OdlukeAgent::getAsyncStatus($validated['cache_key']);

        if (! $status) {
            return response()->json([
                'error' => 'Query not found or expired',
            ], 404);
        }

        return ApiResponse::success($status);
    }
}

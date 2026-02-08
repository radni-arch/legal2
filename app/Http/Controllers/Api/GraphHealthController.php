<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GraphDatabaseService;
use Illuminate\Http\JsonResponse;

class GraphHealthController extends Controller
{
    public function __construct(
        private GraphDatabaseService $graphService
    ) {}

    public function __invoke(): JsonResponse
    {
        $startTime = microtime(true);
        $isHealthy = $this->graphService->isHealthy();
        $latencyMs = (microtime(true) - $startTime) * 1000;

        $response = [
            'service' => 'neo4j',
            'status' => $isHealthy ? 'healthy' : 'unhealthy',
            'latency_ms' => round($latencyMs, 2),
            'timestamp' => now()->toIso8601String(),
        ];

        return response()->json($response, $isHealthy ? 200 : 503);
    }
}

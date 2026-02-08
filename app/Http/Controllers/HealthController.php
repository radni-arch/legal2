<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class HealthController extends Controller
{
    /**
     * Health check endpoint.
     * Returns 200 if all services are healthy, 503 if any service is down.
     */
    public function index(): JsonResponse
    {
        $startTime = microtime(true);

        $services = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'neo4j' => $this->checkNeo4j(),
            'openai' => $this->checkOpenAI(),
            'aws' => $this->checkAWS(),
            'queue' => $this->checkQueue(),
        ];

        $healthy = collect($services)->every(fn ($service) => $service['status'] === 'healthy');

        $responseTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds

        $response = [
            'status' => $healthy ? 'healthy' : 'unhealthy',
            'timestamp' => now()->toIso8601String(),
            'services' => $services,
            'metrics' => [
                'response_time_ms' => round($responseTime, 2),
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
                'uptime_seconds' => $this->getUptime(),
            ],
            'version' => config('app.version', '1.0.0'),
        ];

        // Log unhealthy status
        if (! $healthy) {
            Log::warning('Health check failed', [
                'services' => collect($services)->filter(fn ($s) => $s['status'] !== 'healthy'),
            ]);
        }

        return response()->json($response, $healthy ? 200 : 503);
    }

    /**
     * Check PostgreSQL database connection.
     */
    protected function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            DB::select('SELECT 1');
            $duration = (microtime(true) - $start) * 1000;

            // Get connection count
            $connections = DB::select('SELECT count(*) as count FROM pg_stat_activity WHERE datname = current_database()');
            $connectionCount = $connections[0]->count ?? 0;

            return [
                'status' => 'healthy',
                'response_time_ms' => round($duration, 2),
                'connections' => $connectionCount,
                'driver' => config('database.default'),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check Redis connection.
     */
    protected function checkRedis(): array
    {
        try {
            $start = microtime(true);
            $redis = Redis::connection('default');
            $pong = $redis->ping();
            $duration = (microtime(true) - $start) * 1000;

            // Get memory info
            $info = $redis->info('memory');
            $usedMemory = $info['used_memory_human'] ?? 'unknown';

            return [
                'status' => $pong ? 'healthy' : 'unhealthy',
                'response_time_ms' => round($duration, 2),
                'memory_usage' => $usedMemory,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check Neo4j connection (if enabled).
     */
    protected function checkNeo4j(): array
    {
        if (! config('neo4j.enabled')) {
            return [
                'status' => 'disabled',
            ];
        }

        try {
            $start = microtime(true);
            $client = app('neo4j.client');
            $result = $client->run('RETURN 1 AS test');
            $duration = (microtime(true) - $start) * 1000;

            $test = $result->first()->get('test');

            return [
                'status' => $test === 1 ? 'healthy' : 'unhealthy',
                'response_time_ms' => round($duration, 2),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check OpenAI API connectivity.
     */
    protected function checkOpenAI(): array
    {
        try {
            // Just check if API key is configured
            $apiKey = config('openai.api_key');

            if (empty($apiKey)) {
                return [
                    'status' => 'unhealthy',
                    'error' => 'API key not configured',
                ];
            }

            // Don't make actual API call to avoid costs
            // Just verify configuration
            return [
                'status' => 'healthy',
                'configured' => true,
                'model' => config('openai.chat_model'),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check AWS connectivity.
     */
    protected function checkAWS(): array
    {
        try {
            // Check if AWS credentials are configured
            $accessKey = config('filesystems.disks.s3.key');
            $secretKey = config('filesystems.disks.s3.secret');
            $bucket = config('filesystems.disks.s3.bucket');

            if (empty($accessKey) || empty($secretKey) || empty($bucket)) {
                return [
                    'status' => 'unhealthy',
                    'error' => 'AWS credentials not configured',
                ];
            }

            // Don't make actual AWS call to avoid latency
            // Just verify configuration
            return [
                'status' => 'healthy',
                'configured' => true,
                'bucket' => $bucket,
                'region' => config('filesystems.disks.s3.region'),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check queue system.
     */
    protected function checkQueue(): array
    {
        try {
            $start = microtime(true);

            // Check queue connection (Redis)
            $redis = Redis::connection('queue');
            $redis->ping();

            $duration = (microtime(true) - $start) * 1000;

            // Get queue sizes
            $queues = ['high', 'agents', 'textract', 'default', 'low'];
            $sizes = [];
            $total = 0;

            foreach ($queues as $queue) {
                $size = $redis->llen("queues:{$queue}");
                $sizes[$queue] = $size;
                $total += $size;
            }

            // Check failed jobs
            $failedCount = DB::table('failed_jobs')->count();

            return [
                'status' => 'healthy',
                'response_time_ms' => round($duration, 2),
                'queue_sizes' => $sizes,
                'total_pending' => $total,
                'failed_jobs' => $failedCount,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get application uptime in seconds.
     */
    protected function getUptime(): int
    {
        try {
            // Try to get from cache (set when app boots)
            $bootTime = Cache::get('app_boot_time');

            if ($bootTime) {
                return now()->timestamp - $bootTime;
            }

            // Fallback: use Laravel start time (this request only)
            return (int) ((microtime(true) - LARAVEL_START) * 1000);
        } catch (\Exception $e) {
            return 0;
        }
    }
}

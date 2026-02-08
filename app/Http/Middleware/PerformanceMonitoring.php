<?php

namespace App\Http\Middleware;

use App\Services\Monitoring\ApplicationMonitor;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Performance Monitoring Middleware
 *
 * Automatically tracks request performance metrics including:
 * - Response time
 * - Status codes
 * - Query count and time
 * - Memory usage
 */
class PerformanceMonitoring
{
    protected int $queryCount = 0;

    protected float $queryTime = 0;

    public function __construct(
        protected ApplicationMonitor $monitor
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Start timing
        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        // Enable query logging
        $this->enableQueryTracking();

        try {
            $response = $next($request);

            // Calculate metrics
            $duration = (microtime(true) - $startTime) * 1000; // Convert to ms
            $memoryUsed = memory_get_usage() - $startMemory;

            // Record metrics
            $this->monitor->recordRequest(
                method: $request->method(),
                uri: $request->path(),
                statusCode: $response->getStatusCode(),
                durationMs: $duration,
                queryCount: $this->queryCount,
                queryTimeMs: $this->queryTime
            );

            // Add performance headers in development
            if (config('app.debug')) {
                $response->headers->set('X-Response-Time', round($duration, 2).'ms');
                $response->headers->set('X-Query-Count', $this->queryCount);
                $response->headers->set('X-Memory-Usage', $this->formatBytes($memoryUsed));
            }

            return $response;
        } catch (\Throwable $e) {
            // Record exception
            $this->monitor->recordException($e, [
                'method' => $request->method(),
                'uri' => $request->path(),
            ]);

            throw $e;
        }
    }

    /**
     * Enable query tracking
     */
    protected function enableQueryTracking(): void
    {
        DB::listen(function ($query) {
            $this->queryCount++;
            $this->queryTime += $query->time;

            // Record individual query
            $this->monitor->recordQuery(
                sql: $query->sql,
                timeMs: $query->time,
                bindings: $query->bindings
            );
        });
    }

    /**
     * Format bytes to human readable format
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2).' '.$units[$i];
    }
}

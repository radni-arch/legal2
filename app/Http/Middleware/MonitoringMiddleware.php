<?php

namespace App\Http\Middleware;

use App\Services\Monitoring\ProductionMonitor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Monitoring Middleware
 *
 * Tracks request performance and sends metrics to monitoring service.
 */
class MonitoringMiddleware
{
    public function __construct(
        protected ProductionMonitor $monitor
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip monitoring if disabled
        if (! config('monitoring.enabled', false)) {
            return $next($request);
        }

        // Skip excluded patterns
        if ($this->shouldSkip($request)) {
            return $next($request);
        }

        $startTime = microtime(true);

        $response = $next($request);

        $duration = (microtime(true) - $startTime) * 1000; // Convert to milliseconds

        // Record metrics
        $this->recordMetrics($request, $response, $duration);

        // Add performance headers if enabled
        if (config('monitoring.middleware.add_headers', false)) {
            $response->headers->set('X-Response-Time', round($duration, 2).'ms');
        }

        return $response;
    }

    /**
     * Record request metrics
     */
    protected function recordMetrics(Request $request, Response $response, float $duration): void
    {
        try {
            // Update response time
            $this->monitor->updateResponseTime($duration);

            // Record error if 5xx status
            if ($response->getStatusCode() >= 500) {
                $this->monitor->recordError();
            }

            // Record specific metrics
            $this->monitor->recordMetric('http.request', 1, [
                'method' => $request->method(),
                'status' => $response->getStatusCode(),
            ]);

            $this->monitor->recordMetric('http.response_time', $duration, [
                'route' => $request->route()?->getName() ?? 'unknown',
            ]);
        } catch (\Throwable $e) {
            // Don't let monitoring break the app
            \Illuminate\Support\Facades\Log::debug('Monitoring middleware error', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Check if request should skip monitoring
     */
    protected function shouldSkip(Request $request): bool
    {
        $excludePatterns = config('monitoring.middleware.exclude_patterns', []);

        foreach ($excludePatterns as $pattern) {
            if ($request->is($pattern)) {
                return true;
            }
        }

        return false;
    }
}

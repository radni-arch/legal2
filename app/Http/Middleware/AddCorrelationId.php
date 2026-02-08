<?php

namespace App\Http\Middleware;

use App\Logging\CorrelationIdProcessor;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to add correlation ID to requests and responses
 *
 * Ensures every HTTP request has a unique correlation ID that can be traced
 * through logs, database records, and async operations.
 *
 * Sprint 1.7: Observability Setup - Enhanced with CorrelationIdProcessor
 */
class AddCorrelationId
{
    /**
     * Handle an incoming request and add correlation ID for distributed tracing
     *
     * Extracts X-Request-ID or X-Correlation-ID header or generates new UUID,
     * adds to request context, and includes in response headers for end-to-end
     * request tracking.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Extract or generate correlation ID (try both header names for compatibility)
        $correlationId = $request->header('X-Request-ID')
            ?? $request->header(CorrelationIdProcessor::HEADER_NAME)
            ?? Str::uuid()->toString();

        // Store correlation ID in request headers (both formats for compatibility)
        $request->headers->set('X-Request-ID', $correlationId);
        $request->headers->set(CorrelationIdProcessor::HEADER_NAME, $correlationId);

        // Set correlation ID in processor (for Monolog)
        CorrelationIdProcessor::setCorrelationId($correlationId);

        // Add to request attributes for easy access
        $request->attributes->set('correlation_id', $correlationId);

        // Add correlation ID and request context to all logs
        Log::withContext([
            'correlation_id' => $correlationId,
            'user_id' => auth()->id(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'method' => $request->method(),
            'uri' => $request->getRequestUri(),
            'route' => $request->route()?->getName(),
        ]);

        $response = $next($request);

        // Add correlation ID to response headers for client tracking (both formats)
        $response->headers->set('X-Request-ID', $correlationId);
        $response->headers->set(CorrelationIdProcessor::HEADER_NAME, $correlationId);

        return $response;
    }
}

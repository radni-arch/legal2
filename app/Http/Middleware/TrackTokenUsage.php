<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TrackTokenUsage
{
    /**
     * Handle an incoming request and track token usage from OpenAI responses.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Skip token tracking for streamed responses (SSE)
        if ($response instanceof StreamedResponse) {
            return $response;
        }

        // Track tokens used in response
        if ($response->getStatusCode() === 200 && method_exists($response, 'getData')) {
            $data = $response->getData(true);
            $tokensUsed = $data['usage']['total_tokens'] ?? 0;

            if ($tokensUsed > 0) {
                $user = $request->user();
                $key = 'tokens:'.($user?->id ?? $request->ip());

                // Increment token counter for rate limiting
                RateLimiter::hit($key, 86400); // 24 hours

                // Store actual token usage for tracking/billing
                Cache::increment($key.':usage', $tokensUsed);

                // Store hourly breakdown for analytics
                $hourKey = $key.':hourly:'.now()->format('Y-m-d-H');
                Cache::increment($hourKey, $tokensUsed);
                Cache::put($hourKey.':expires', now()->addDays(7), 604800); // 7 days
            }
        }

        return $response;
    }
}

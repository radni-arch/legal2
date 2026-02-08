<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class McpAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ?string $tool = null): Response
    {
        // Check if authentication is enabled
        if (config('services.mcp.auth.enabled', true)) {
            $this->authenticate($request, $tool);
        }

        // Check rate limiting
        if (config('services.mcp.rate_limit.enabled', true)) {
            $this->checkRateLimit($request, $tool);
        }

        return $next($request);
    }

    /**
     * Authenticate the MCP request using API token.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException
     */
    protected function authenticate(Request $request, ?string $tool): void
    {
        $headerName = config('services.mcp.auth.token_header', 'X-MCP-Token');
        $providedToken = $request->header($headerName);
        $expectedToken = config('services.mcp.auth.token');

        // If no token is configured, skip authentication
        if (empty($expectedToken)) {
            return;
        }

        // Check if token is provided
        if (empty($providedToken)) {
            abort(401, 'MCP API token is required. Please provide it in the '.$headerName.' header.');
        }

        // Verify token
        if (! hash_equals($expectedToken, $providedToken)) {
            abort(401, 'Invalid MCP API token.');
        }

        // Check if tool is private and requires additional authorization
        if ($tool && in_array($tool, config('services.mcp.access.private_tools', []))) {
            // For private tools, we could check additional permissions here
            // For now, just require authentication
            if (empty($providedToken)) {
                abort(403, sprintf('Tool "%s" requires authentication.', $tool));
            }
        }
    }

    /**
     * Check rate limiting for the request.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException
     */
    protected function checkRateLimit(Request $request, ?string $tool): void
    {
        $identifier = $this->getRateLimitIdentifier($request);

        // Global rate limit (per minute)
        $globalPerMinute = config('services.mcp.rate_limit.default_per_minute', 60);
        $globalKey = 'mcp_rate_global_minute:'.$identifier;

        if (! $this->checkLimit($globalKey, $globalPerMinute, 60)) {
            abort(429, sprintf(
                'Too many requests. Global rate limit is %d requests per minute.',
                $globalPerMinute
            ));
        }

        // Global rate limit (per hour)
        $globalPerHour = config('services.mcp.rate_limit.default_per_hour', 1000);
        $globalHourKey = 'mcp_rate_global_hour:'.$identifier;

        if (! $this->checkLimit($globalHourKey, $globalPerHour, 3600)) {
            abort(429, sprintf(
                'Too many requests. Global rate limit is %d requests per hour.',
                $globalPerHour
            ));
        }

        // Per-tool rate limit
        if ($tool) {
            $toolLimit = config('services.mcp.rate_limit.per_tool.'.$tool);

            if ($toolLimit) {
                $toolKey = 'mcp_rate_tool:'.$tool.':'.$identifier;

                if (! $this->checkLimit($toolKey, $toolLimit, 60)) {
                    abort(429, sprintf(
                        'Too many requests for tool "%s". Rate limit is %d requests per minute.',
                        $tool,
                        $toolLimit
                    ));
                }
            }
        }
    }

    /**
     * Check if the rate limit is exceeded.
     */
    protected function checkLimit(string $key, int $maxAttempts, int $decaySeconds): bool
    {
        $attempts = (int) Cache::get($key, 0);

        if ($attempts >= $maxAttempts) {
            return false;
        }

        Cache::put($key, $attempts + 1, $decaySeconds);

        return true;
    }

    /**
     * Get a unique identifier for rate limiting.
     */
    protected function getRateLimitIdentifier(Request $request): string
    {
        // Use API token if available, otherwise use IP address
        $headerName = config('services.mcp.auth.token_header', 'X-MCP-Token');
        $token = $request->header($headerName);

        if ($token) {
            return 'token:'.hash('sha256', $token);
        }

        return 'ip:'.$request->ip();
    }
}

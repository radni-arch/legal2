<?php

namespace App\Http\Middleware;

use App\Models\HoneypotLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class HoneypotMiddleware
{
    /**
     * Handle an incoming request to a honeypot endpoint.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Log the honeypot access attempt
        $this->logAttempt($request);

        // Alert if configured
        $this->alertIfNeeded($request);

        // Continue to the honeypot controller
        return $next($request);
    }

    /**
     * Log the honeypot access attempt to database and file.
     */
    protected function logAttempt(Request $request): void
    {
        $data = [
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'method' => $request->method(),
            'path' => $request->path(),
            'full_url' => $request->fullUrl(),
            'headers' => json_encode($request->headers->all()),
            'query_params' => json_encode($request->query()),
            'body' => $request->getContent(),
            'referer' => $request->header('referer'),
            'attempted_auth' => $this->extractAuthAttempt($request),
        ];

        // Save to database
        try {
            HoneypotLog::create($data);
        } catch (\Exception $e) {
            Log::error('Failed to save honeypot log to database', ['error' => $e->getMessage()]);
        }

        // Also log to Laravel log file
        Log::channel('honeypot')->warning('Honeypot triggered', $data);
    }

    /**
     * Extract authentication attempt information.
     */
    protected function extractAuthAttempt(Request $request): ?string
    {
        $auth = [];

        if ($token = $request->bearerToken()) {
            $auth['bearer_token'] = substr($token, 0, 20).'...'; // Truncate for privacy
        }

        if ($apiKey = $request->header('X-API-Key')) {
            $auth['api_key'] = substr($apiKey, 0, 20).'...';
        }

        if ($mcpToken = $request->header('X-MCP-Token')) {
            $auth['mcp_token'] = substr($mcpToken, 0, 20).'...';
        }

        if ($basicAuth = $request->getUser()) {
            $auth['basic_auth_user'] = $basicAuth;
        }

        return empty($auth) ? null : json_encode($auth);
    }

    /**
     * Send alert if configured (can be extended for email, Slack, etc.)
     */
    protected function alertIfNeeded(Request $request): void
    {
        // Check if this IP has triggered honeypot multiple times
        $recentAttempts = HoneypotLog::where('ip_address', $request->ip())
            ->where('created_at', '>=', now()->subHour())
            ->count();

        if ($recentAttempts >= config('honeypot.alert_threshold', 5)) {
            Log::channel('honeypot')->critical('Multiple honeypot attempts detected', [
                'ip_address' => $request->ip(),
                'attempts_last_hour' => $recentAttempts,
                'path' => $request->path(),
            ]);

            // Here you could send email, Slack notification, etc.
            // event(new HoneypotAlertEvent($request->ip(), $recentAttempts));
        }
    }
}

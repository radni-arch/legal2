<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

        // Handle CORS Private Network Access preflight requests
        $middleware->append(\App\Http\Middleware\PrivateNetworkAccess::class);

        // Apply security headers globally to all responses
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        // Add correlation ID to all requests for distributed tracing
        $middleware->append(\App\Http\Middleware\AddCorrelationId::class);

        $middleware->alias([
            'mcp.auth.throttle' => \App\Http\Middleware\McpAuth::class,
            'mcp.auth' => \App\Http\Middleware\McpApiTokenAuth::class,
            'api.token' => \App\Http\Middleware\ApiTokenAuth::class,
            'honeypot' => \App\Http\Middleware\HoneypotMiddleware::class,
            'track.tokens' => \App\Http\Middleware\TrackTokenUsage::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        Integration::handles($exceptions);

        // Only intercept Livewire AJAX requests to prevent JSON parse errors.
        // All other exceptions use Laravel's default handling (Whoops in debug mode).
        $exceptions->render(function (Throwable $e, Request $request) {
            // Only handle Livewire update requests - these send X-Livewire header
            // and expect JSON responses. Without this, Laravel returns HTML error
            // pages which cause "SyntaxError: Unexpected token '<'" in the browser.
            if (! $request->hasHeader('X-Livewire')) {
                return null; // Let Laravel handle normally
            }

            return response()->json([
                'success' => false,
                'message' => app()->isProduction()
                    ? 'An error occurred'
                    : $e->getMessage(),
            ], 500);
        });
    })->create();

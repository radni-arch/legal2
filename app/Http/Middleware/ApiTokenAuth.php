<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiTokenAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get token from Authorization header (Bearer token)
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'API token is required. Please provide a Bearer token in the Authorization header.',
            ], 401);
        }

        // Find user by API token
        $user = User::where('api_token', $token)->first();

        if (! $user) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Invalid API token.',
            ], 401);
        }

        // Set the authenticated user for this request
        auth()->setUser($user);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}

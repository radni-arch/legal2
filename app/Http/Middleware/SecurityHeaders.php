<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Handle an incoming request and add security headers to the response.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // X-Frame-Options: Prevent clickjacking attacks
        //        $response->headers->set('X-Frame-Options', 'DENY');

        // X-Content-Type-Options: Prevent MIME type sniffing
        //$response->headers->set('X-Content-Type-Options', 'nosniff');

        // X-XSS-Protection: Enable XSS filter built into modern browsers
        //$response->headers->set('X-XSS-Protection', '1; mode=block');

        // Strict-Transport-Security: Enforce HTTPS
        //$response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');

        // Content-Security-Policy: Control resources the browser is allowed to load
        //        $response->headers->set(
        //            'Content-Security-Policy',
        //            "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'"
        //        );

        // Referrer-Policy: Control referrer information
        //$response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Permissions-Policy: Control browser features
        //$response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        return $response;
    }
}

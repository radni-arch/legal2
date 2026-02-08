<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PrivateNetworkAccess
{
    /**
     * Handle CORS Private Network Access preflight requests.
     *
     * When a browser on a public network makes requests to a private network
     * server, Chrome sends a preflight with Access-Control-Request-Private-Network.
     * The server must respond with Access-Control-Allow-Private-Network: true.
     *
     * @see https://wicg.github.io/private-network-access/
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->headers->has('Access-Control-Request-Private-Network')) {
            $response->headers->set('Access-Control-Allow-Private-Network', 'true');
        }

        return $response;
    }
}

<?php

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\PrivateNetworkAccess;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\TestCase;

class PrivateNetworkAccessTest extends TestCase
{
    protected PrivateNetworkAccess $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new PrivateNetworkAccess;
    }

    /** @test */
    public function adds_private_network_header_when_request_has_private_network_header(): void
    {
        $request = Request::create('/test', 'OPTIONS');
        $request->headers->set('Access-Control-Request-Private-Network', 'true');

        $next = function ($req) {
            return new Response('', 204);
        };

        $response = $this->middleware->handle($request, $next);

        $this->assertEquals('true', $response->headers->get('Access-Control-Allow-Private-Network'));
    }

    /** @test */
    public function does_not_add_header_when_request_lacks_private_network_header(): void
    {
        $request = Request::create('/test', 'GET');

        $next = function ($req) {
            return new Response('OK', 200);
        };

        $response = $this->middleware->handle($request, $next);

        $this->assertNull($response->headers->get('Access-Control-Allow-Private-Network'));
    }

    /** @test */
    public function passes_request_through_to_next_middleware(): void
    {
        $request = Request::create('/test', 'GET');
        $request->headers->set('Access-Control-Request-Private-Network', 'true');

        $next = function ($req) {
            return new Response('OK', 200);
        };

        $response = $this->middleware->handle($request, $next);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getContent());
    }
}

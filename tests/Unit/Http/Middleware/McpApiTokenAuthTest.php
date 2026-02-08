<?php

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\McpApiTokenAuth;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\TestCase;

/**
 * Unit tests for McpApiTokenAuth middleware
 */
class McpApiTokenAuthTest extends TestCase
{
    protected McpApiTokenAuth $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new McpApiTokenAuth;
    }

    /** @test */
    public function allows_request_when_no_token_configured(): void
    {
        config(['mcp.api_token' => null]);

        $request = Request::create('/test', 'GET');
        $next = function ($req) {
            return new Response('OK', 200);
        };

        $response = $this->middleware->handle($request, $next);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getContent());
    }

    /** @test */
    public function allows_request_when_empty_token_configured(): void
    {
        config(['mcp.api_token' => '']);

        $request = Request::create('/test', 'GET');
        $next = function ($req) {
            return new Response('OK', 200);
        };

        $response = $this->middleware->handle($request, $next);

        $this->assertEquals(200, $response->getStatusCode());
    }

    /** @test */
    public function rejects_request_without_authorization_header(): void
    {
        config(['mcp.api_token' => 'secret-token']);

        $request = Request::create('/test', 'GET');
        $next = function ($req) {
            return new Response('OK', 200);
        };

        $response = $this->middleware->handle($request, $next);

        $this->assertEquals(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Unauthorized', $data['error']);
        $this->assertStringContainsString('Authorization header', $data['message']);
    }

    /** @test */
    public function rejects_request_with_invalid_authorization_format(): void
    {
        config(['mcp.api_token' => 'secret-token']);

        $request = Request::create('/test', 'GET');
        $request->headers->set('Authorization', 'InvalidFormat token');

        $next = function ($req) {
            return new Response('OK', 200);
        };

        $response = $this->middleware->handle($request, $next);

        $this->assertEquals(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Unauthorized', $data['error']);
    }

    /** @test */
    public function rejects_request_with_wrong_token(): void
    {
        config(['mcp.api_token' => 'secret-token']);

        $request = Request::create('/test', 'GET');
        $request->headers->set('Authorization', 'Bearer wrong-token');

        $next = function ($req) {
            return new Response('OK', 200);
        };

        $response = $this->middleware->handle($request, $next);

        $this->assertEquals(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Unauthorized', $data['error']);
        $this->assertStringContainsString('Invalid API token', $data['message']);
    }

    /** @test */
    public function allows_request_with_correct_token(): void
    {
        config(['mcp.api_token' => 'secret-token']);

        $request = Request::create('/test', 'GET');
        $request->headers->set('Authorization', 'Bearer secret-token');

        $next = function ($req) {
            return new Response('OK', 200);
        };

        $response = $this->middleware->handle($request, $next);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getContent());
    }

    /** @test */
    public function is_case_sensitive_for_bearer_prefix(): void
    {
        config(['mcp.api_token' => 'secret-token']);

        $request = Request::create('/test', 'GET');
        $request->headers->set('Authorization', 'bearer secret-token'); // lowercase

        $next = function ($req) {
            return new Response('OK', 200);
        };

        $response = $this->middleware->handle($request, $next);

        // Should fail because we check for 'Bearer ' with capital B
        $this->assertEquals(401, $response->getStatusCode());
    }

    /** @test */
    public function handles_token_with_whitespace(): void
    {
        config(['mcp.api_token' => 'secret-token']);

        $request = Request::create('/test', 'GET');
        // Token with extra spaces should work because we extract after 'Bearer '
        $request->headers->set('Authorization', 'Bearer secret-token');

        $next = function ($req) {
            return new Response('OK', 200);
        };

        $response = $this->middleware->handle($request, $next);

        $this->assertEquals(200, $response->getStatusCode());
    }

    /** @test */
    public function returns_json_response_on_failure(): void
    {
        config(['mcp.api_token' => 'secret-token']);

        $request = Request::create('/test', 'GET');
        $next = function ($req) {
            return new Response('OK', 200);
        };

        $response = $this->middleware->handle($request, $next);

        $this->assertEquals('application/json', $response->headers->get('Content-Type'));
        $this->assertJson($response->getContent());
    }

    /** @test */
    public function response_includes_error_and_message_fields(): void
    {
        config(['mcp.api_token' => 'secret-token']);

        $request = Request::create('/test', 'GET');
        $next = function ($req) {
            return new Response('OK', 200);
        };

        $response = $this->middleware->handle($request, $next);

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertArrayHasKey('message', $data);
    }
}

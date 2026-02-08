<?php

namespace Tests\Feature;

use App\Http\Middleware\McpAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Test suite for McpAuth middleware
 *
 * Tests:
 * - MCP token authentication via X-MCP-Token header
 * - Rate limiting (global per minute, per hour, per tool)
 * - Private tool access control
 * - Configuration-driven behavior
 * - Token identifier hashing for rate limiting
 */
class McpAuthMiddlewareTest extends TestCase
{
    use UsesTestDatabase;

    protected McpAuth $middleware;

    protected string $validToken = 'test-mcp-token-12345';

    protected function setUp(): void
    {
        parent::setUp();

        $this->middleware = new McpAuth;

        // Clear cache before each test
        Cache::flush();

        // Set default config values
        Config::set('services.mcp.auth.enabled', true);
        Config::set('services.mcp.auth.token', $this->validToken);
        Config::set('services.mcp.auth.token_header', 'X-MCP-Token');
        Config::set('services.mcp.rate_limit.enabled', true);
        Config::set('services.mcp.rate_limit.default_per_minute', 60);
        Config::set('services.mcp.rate_limit.default_per_hour', 1000);
        Config::set('services.mcp.access.private_tools', ['private_tool']);
    }

    // ========================================
    // Authentication Tests
    // ========================================

    /** @test */
    public function it_allows_request_when_auth_is_disabled()
    {
        Config::set('services.mcp.auth.enabled', false);
        Config::set('services.mcp.rate_limit.enabled', false);

        $request = Request::create('/api/mcp/tool', 'POST');
        // No token provided

        $nextCalled = false;

        $response = $this->middleware->handle($request, function ($req) use (&$nextCalled) {
            $nextCalled = true;

            return response()->json(['success' => true]);
        });

        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
    }

    /** @test */
    public function it_allows_request_when_no_token_configured()
    {
        Config::set('services.mcp.auth.token', null);

        $request = Request::create('/api/mcp/tool', 'POST');
        // No token provided

        $nextCalled = false;

        $response = $this->middleware->handle($request, function ($req) use (&$nextCalled) {
            $nextCalled = true;

            return response()->json(['success' => true]);
        });

        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
    }

    /** @test */
    public function it_rejects_request_without_token_when_auth_enabled()
    {
        $request = Request::create('/api/mcp/tool', 'POST');

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->expectExceptionMessage('MCP API token is required');

        $this->middleware->handle($request, function () {
            $this->fail('Should not call next()');
        });
    }

    /** @test */
    public function it_rejects_request_with_invalid_token()
    {
        $request = Request::create('/api/mcp/tool', 'POST');
        $request->headers->set('X-MCP-Token', 'invalid-token');

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->expectExceptionMessage('Invalid MCP API token');

        $this->middleware->handle($request, function () {
            $this->fail('Should not call next()');
        });
    }

    /** @test */
    public function it_accepts_request_with_valid_token()
    {
        $request = Request::create('/api/mcp/tool', 'POST');
        $request->headers->set('X-MCP-Token', $this->validToken);

        Config::set('services.mcp.rate_limit.enabled', false);

        $nextCalled = false;

        $response = $this->middleware->handle($request, function ($req) use (&$nextCalled) {
            $nextCalled = true;

            return response()->json(['success' => true]);
        });

        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
    }

    /** @test */
    public function it_uses_custom_token_header_from_config()
    {
        Config::set('services.mcp.auth.token_header', 'X-Custom-Token');

        $request = Request::create('/api/mcp/tool', 'POST');
        $request->headers->set('X-Custom-Token', $this->validToken);

        Config::set('services.mcp.rate_limit.enabled', false);

        $nextCalled = false;

        $response = $this->middleware->handle($request, function ($req) use (&$nextCalled) {
            $nextCalled = true;

            return response()->json(['success' => true]);
        });

        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
    }

    /** @test */
    public function it_uses_constant_time_comparison_for_tokens()
    {
        // The middleware uses hash_equals which is timing-attack safe
        // This test verifies that invalid tokens are always rejected

        $almostValidToken = substr($this->validToken, 0, -1).'X';

        $request = Request::create('/api/mcp/tool', 'POST');
        $request->headers->set('X-MCP-Token', $almostValidToken);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->expectExceptionMessage('Invalid MCP API token');

        $this->middleware->handle($request, function () {
            $this->fail('Should not call next()');
        });
    }

    // ========================================
    // Private Tool Access Tests
    // ========================================

    /** @test */
    public function it_allows_access_to_public_tools_with_valid_token()
    {
        $request = Request::create('/api/mcp/tool', 'POST');
        $request->headers->set('X-MCP-Token', $this->validToken);

        Config::set('services.mcp.rate_limit.enabled', false);

        $nextCalled = false;

        $response = $this->middleware->handle($request, function ($req) use (&$nextCalled) {
            $nextCalled = true;

            return response()->json(['success' => true]);
        }, 'public_tool');

        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
    }

    /** @test */
    public function it_allows_access_to_private_tools_with_valid_token()
    {
        $request = Request::create('/api/mcp/tool', 'POST');
        $request->headers->set('X-MCP-Token', $this->validToken);

        Config::set('services.mcp.rate_limit.enabled', false);

        $nextCalled = false;

        $response = $this->middleware->handle($request, function ($req) use (&$nextCalled) {
            $nextCalled = true;

            return response()->json(['success' => true]);
        }, 'private_tool');

        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
    }

    // ========================================
    // Rate Limiting Tests
    // ========================================

    /** @test */
    public function it_allows_requests_within_global_per_minute_limit()
    {
        Config::set('services.mcp.rate_limit.default_per_minute', 3);
        Config::set('services.mcp.rate_limit.default_per_hour', 1000);

        $request = Request::create('/api/mcp/tool', 'POST');
        $request->headers->set('X-MCP-Token', $this->validToken);

        // Make 3 requests (within limit)
        for ($i = 0; $i < 3; $i++) {
            $nextCalled = false;

            $response = $this->middleware->handle($request, function ($req) use (&$nextCalled) {
                $nextCalled = true;

                return response()->json(['success' => true]);
            });

            $this->assertTrue($nextCalled, "Request $i should be allowed");
            $this->assertEquals(200, $response->getStatusCode());
        }
    }

    /** @test */
    public function it_rejects_requests_exceeding_global_per_minute_limit()
    {
        Config::set('services.mcp.rate_limit.default_per_minute', 2);
        Config::set('services.mcp.rate_limit.default_per_hour', 1000);

        $request = Request::create('/api/mcp/tool', 'POST');
        $request->headers->set('X-MCP-Token', $this->validToken);

        // Make 2 requests (at limit)
        for ($i = 0; $i < 2; $i++) {
            $this->middleware->handle($request, function ($req) {
                return response()->json(['success' => true]);
            });
        }

        // Third request should be rejected
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->expectExceptionMessage('Too many requests');
        $this->expectExceptionMessage('per minute');

        $this->middleware->handle($request, function () {
            $this->fail('Should not call next() when rate limit exceeded');
        });
    }

    /** @test */
    public function it_rejects_requests_exceeding_global_per_hour_limit()
    {
        Config::set('services.mcp.rate_limit.default_per_minute', 1000);
        Config::set('services.mcp.rate_limit.default_per_hour', 2);

        $request = Request::create('/api/mcp/tool', 'POST');
        $request->headers->set('X-MCP-Token', $this->validToken);

        // Make 2 requests (at limit)
        for ($i = 0; $i < 2; $i++) {
            $this->middleware->handle($request, function ($req) {
                return response()->json(['success' => true]);
            });
        }

        // Third request should be rejected
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->expectExceptionMessage('Too many requests');
        $this->expectExceptionMessage('per hour');

        $this->middleware->handle($request, function () {
            $this->fail('Should not call next() when rate limit exceeded');
        });
    }

    /** @test */
    public function it_applies_per_tool_rate_limits()
    {
        Config::set('services.mcp.rate_limit.default_per_minute', 1000);
        Config::set('services.mcp.rate_limit.default_per_hour', 10000);
        Config::set('services.mcp.rate_limit.per_tool.law_search', 2);

        $request = Request::create('/api/mcp/law/search', 'POST');
        $request->headers->set('X-MCP-Token', $this->validToken);

        // Make 2 requests to law_search (at limit)
        for ($i = 0; $i < 2; $i++) {
            $this->middleware->handle($request, function ($req) {
                return response()->json(['success' => true]);
            }, 'law_search');
        }

        // Third request should be rejected
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->expectExceptionMessage('Too many requests for tool "law_search"');

        $this->middleware->handle($request, function () {
            $this->fail('Should not call next() when tool rate limit exceeded');
        }, 'law_search');
    }

    /** @test */
    public function it_tracks_rate_limits_separately_per_tool()
    {
        Config::set('services.mcp.rate_limit.default_per_minute', 1000);
        Config::set('services.mcp.rate_limit.default_per_hour', 10000);
        Config::set('services.mcp.rate_limit.per_tool.tool_a', 2);
        Config::set('services.mcp.rate_limit.per_tool.tool_b', 2);

        $request = Request::create('/api/mcp/tool', 'POST');
        $request->headers->set('X-MCP-Token', $this->validToken);

        // Make 2 requests to tool_a
        for ($i = 0; $i < 2; $i++) {
            $response = $this->middleware->handle($request, function ($req) {
                return response()->json(['success' => true]);
            }, 'tool_a');
            $this->assertEquals(200, $response->getStatusCode());
        }

        // Should still be able to make requests to tool_b
        for ($i = 0; $i < 2; $i++) {
            $response = $this->middleware->handle($request, function ($req) {
                return response()->json(['success' => true]);
            }, 'tool_b');
            $this->assertEquals(200, $response->getStatusCode());
        }

        // Both tools should now be at their limit
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->expectExceptionMessage('tool_a');

        $this->middleware->handle($request, function () {
            $this->fail('Should not call next()');
        }, 'tool_a');
    }

    /** @test */
    public function it_allows_request_when_rate_limiting_disabled()
    {
        Config::set('services.mcp.rate_limit.enabled', false);
        Config::set('services.mcp.rate_limit.default_per_minute', 1);

        $request = Request::create('/api/mcp/tool', 'POST');
        $request->headers->set('X-MCP-Token', $this->validToken);

        // Make multiple requests (would exceed limit if enabled)
        for ($i = 0; $i < 10; $i++) {
            $nextCalled = false;

            $response = $this->middleware->handle($request, function ($req) use (&$nextCalled) {
                $nextCalled = true;

                return response()->json(['success' => true]);
            });

            $this->assertTrue($nextCalled, "Request $i should be allowed when rate limiting disabled");
            $this->assertEquals(200, $response->getStatusCode());
        }
    }

    // ========================================
    // Rate Limit Identifier Tests
    // ========================================

    /** @test */
    public function it_uses_token_hash_for_rate_limit_identifier_when_token_present()
    {
        Config::set('services.mcp.rate_limit.default_per_minute', 2);
        Config::set('services.mcp.rate_limit.default_per_hour', 1000);

        $request = Request::create('/api/mcp/tool', 'POST');
        $request->headers->set('X-MCP-Token', $this->validToken);

        // Make requests up to limit
        for ($i = 0; $i < 2; $i++) {
            $this->middleware->handle($request, function ($req) {
                return response()->json(['success' => true]);
            });
        }

        // Verify that the rate limit is tied to the token, not IP
        // By checking that the same token from different IP still hits limit
        $requestFromDifferentIp = Request::create('/api/mcp/tool', 'POST', [], [], [], ['REMOTE_ADDR' => '192.168.1.100']);
        $requestFromDifferentIp->headers->set('X-MCP-Token', $this->validToken);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        $this->middleware->handle($requestFromDifferentIp, function () {
            $this->fail('Should not call next()');
        });
    }

    /** @test */
    public function it_uses_ip_address_for_rate_limit_identifier_when_no_token()
    {
        Config::set('services.mcp.auth.enabled', false);
        Config::set('services.mcp.rate_limit.default_per_minute', 2);
        Config::set('services.mcp.rate_limit.default_per_hour', 1000);

        $request = Request::create('/api/mcp/tool', 'POST', [], [], [], ['REMOTE_ADDR' => '192.168.1.50']);

        // Make requests up to limit
        for ($i = 0; $i < 2; $i++) {
            $this->middleware->handle($request, function ($req) {
                return response()->json(['success' => true]);
            });
        }

        // Third request from same IP should fail
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        $this->middleware->handle($request, function () {
            $this->fail('Should not call next()');
        });
    }

    /** @test */
    public function it_tracks_rate_limits_separately_by_identifier()
    {
        Config::set('services.mcp.rate_limit.default_per_minute', 2);
        Config::set('services.mcp.rate_limit.default_per_hour', 1000);

        $token1 = 'token-1';
        $token2 = 'token-2';

        Config::set('services.mcp.auth.token', $token1);

        $request1 = Request::create('/api/mcp/tool', 'POST');
        $request1->headers->set('X-MCP-Token', $token1);

        // Token 1: make 2 requests (at limit)
        for ($i = 0; $i < 2; $i++) {
            $this->middleware->handle($request1, function ($req) {
                return response()->json(['success' => true]);
            });
        }

        // Token 2 should have its own limit
        Config::set('services.mcp.auth.token', $token2);

        $request2 = Request::create('/api/mcp/tool', 'POST');
        $request2->headers->set('X-MCP-Token', $token2);

        $response = $this->middleware->handle($request2, function ($req) {
            return response()->json(['success' => true]);
        });

        $this->assertEquals(200, $response->getStatusCode());
    }

    // ========================================
    // Integration Tests
    // ========================================

    /** @test */
    public function it_combines_authentication_and_rate_limiting()
    {
        Config::set('services.mcp.auth.enabled', true);
        Config::set('services.mcp.rate_limit.enabled', true);
        Config::set('services.mcp.rate_limit.default_per_minute', 2);
        Config::set('services.mcp.rate_limit.default_per_hour', 1000);

        $request = Request::create('/api/mcp/tool', 'POST');
        $request->headers->set('X-MCP-Token', $this->validToken);

        // Make requests within limit
        for ($i = 0; $i < 2; $i++) {
            $response = $this->middleware->handle($request, function ($req) {
                return response()->json(['success' => true]);
            });
            $this->assertEquals(200, $response->getStatusCode());
        }

        // Next request should hit rate limit
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->expectExceptionMessage('Too many requests');

        $this->middleware->handle($request, function () {
            $this->fail('Should not call next()');
        });
    }

    /** @test */
    public function it_fails_authentication_before_checking_rate_limits()
    {
        Config::set('services.mcp.rate_limit.default_per_minute', 0); // Would immediately fail rate limit

        $request = Request::create('/api/mcp/tool', 'POST');
        $request->headers->set('X-MCP-Token', 'invalid-token');

        // Should fail on authentication, not rate limiting
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->expectExceptionMessage('Invalid MCP API token');

        $this->middleware->handle($request, function () {
            $this->fail('Should not call next()');
        });
    }

    /** @test */
    public function it_provides_clear_error_messages_for_rate_limit_types()
    {
        Config::set('services.mcp.rate_limit.default_per_minute', 1);
        Config::set('services.mcp.rate_limit.default_per_hour', 1000);

        $request = Request::create('/api/mcp/tool', 'POST');
        $request->headers->set('X-MCP-Token', $this->validToken);

        // First request succeeds
        $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        // Second request fails with specific message
        try {
            $this->middleware->handle($request, function () {});
            $this->fail('Should throw exception');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertStringContainsString('per minute', $e->getMessage());
            $this->assertStringContainsString('1', $e->getMessage());
        }
    }
}

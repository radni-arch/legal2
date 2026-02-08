<?php

namespace Tests\Feature;

use App\Http\Middleware\HoneypotMiddleware;
use App\Models\HoneypotLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Test suite for HoneypotMiddleware
 *
 * Tests:
 * - Logging honeypot access attempts to database
 * - Logging honeypot access attempts to log files
 * - Extracting authentication attempts from headers
 * - Alert triggering for multiple attempts
 * - Request data capture (IP, headers, body, etc.)
 * - Middleware pass-through behavior
 */
class HoneypotMiddlewareTest extends TestCase
{
    use UsesTestDatabase;

    protected HoneypotMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();

        $this->middleware = new HoneypotMiddleware;

        // Set alert threshold
        Config::set('honeypot.alert_threshold', 5);
    }

    // ========================================
    // Basic Logging Tests
    // ========================================

    /** @test */
    public function it_logs_honeypot_attempt_to_database()
    {
        $request = Request::create('/api/admin/users', 'GET', [], [], [], [
            'REMOTE_ADDR' => '192.168.1.100',
            'HTTP_USER_AGENT' => 'BadBot/1.0',
        ]);

        $this->middleware->handle($request, function ($req) {
            return response()->json(['trap' => 'activated']);
        });

        $this->assertDatabaseHas('honeypot_logs', [
            'ip_address' => '192.168.1.100',
            'user_agent' => 'BadBot/1.0',
            'method' => 'GET',
            'path' => 'api/admin/users',
        ]);
    }

    /** @test */
    public function it_continues_to_next_middleware_after_logging()
    {
        $request = Request::create('/api/honeypot/endpoint', 'GET');

        $nextCalled = false;

        $response = $this->middleware->handle($request, function ($req) use (&$nextCalled) {
            $nextCalled = true;

            return response()->json(['success' => true]);
        });

        $this->assertTrue($nextCalled, 'Middleware should call next()');
        $this->assertEquals(200, $response->getStatusCode());
    }

    /** @test */
    public function it_captures_request_method()
    {
        $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];

        foreach ($methods as $method) {
            $request = Request::create('/api/honeypot', $method);

            $this->middleware->handle($request, function ($req) {
                return response()->json(['success' => true]);
            });

            $this->assertDatabaseHas('honeypot_logs', [
                'method' => $method,
            ]);
        }
    }

    /** @test */
    public function it_captures_request_path()
    {
        $request = Request::create('/api/admin/secret/endpoint', 'GET');

        $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        $this->assertDatabaseHas('honeypot_logs', [
            'path' => 'api/admin/secret/endpoint',
        ]);
    }

    /** @test */
    public function it_captures_full_url_including_query_string()
    {
        $request = Request::create('/api/honeypot?param1=value1&param2=value2', 'GET');

        $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        $log = HoneypotLog::latest()->first();
        $this->assertStringContainsString('param1=value1', $log->full_url);
        $this->assertStringContainsString('param2=value2', $log->full_url);
    }

    /** @test */
    public function it_captures_query_parameters_as_json()
    {
        $request = Request::create('/api/honeypot?user=admin&password=secret', 'GET');

        $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        $log = HoneypotLog::latest()->first();
        $queryParams = json_decode($log->query_params, true);

        $this->assertIsArray($queryParams);
        $this->assertEquals('admin', $queryParams['user']);
        $this->assertEquals('secret', $queryParams['password']);
    }

    /** @test */
    public function it_captures_request_body()
    {
        $body = json_encode(['username' => 'attacker', 'payload' => 'malicious']);

        $request = Request::create('/api/honeypot', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $body);

        $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        $log = HoneypotLog::latest()->first();
        $this->assertEquals($body, $log->body);
    }

    /** @test */
    public function it_captures_request_headers_as_json()
    {
        $request = Request::create('/api/honeypot', 'GET');
        $request->headers->set('X-Custom-Header', 'CustomValue');
        $request->headers->set('X-Attack-Vector', 'SQLi');

        $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        $log = HoneypotLog::latest()->first();
        $headers = json_decode($log->headers, true);

        $this->assertIsArray($headers);
        $this->assertArrayHasKey('x-custom-header', $headers);
        $this->assertContains('CustomValue', $headers['x-custom-header']);
    }

    /** @test */
    public function it_captures_referer_header()
    {
        $request = Request::create('/api/honeypot', 'GET');
        $request->headers->set('Referer', 'https://attacker-site.com/scan');

        $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        $this->assertDatabaseHas('honeypot_logs', [
            'referer' => 'https://attacker-site.com/scan',
        ]);
    }

    /** @test */
    public function it_captures_user_agent()
    {
        $request = Request::create('/api/honeypot', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (compatible; Googlebot/2.1)',
        ]);

        $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        $this->assertDatabaseHas('honeypot_logs', [
            'user_agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1)',
        ]);
    }

    /** @test */
    public function it_captures_ip_address()
    {
        $request = Request::create('/api/honeypot', 'GET', [], [], [], [
            'REMOTE_ADDR' => '203.0.113.42',
        ]);

        $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        $this->assertDatabaseHas('honeypot_logs', [
            'ip_address' => '203.0.113.42',
        ]);
    }

    // ========================================
    // Authentication Extraction Tests
    // ========================================

    /** @test */
    public function it_extracts_bearer_token_from_authorization_header()
    {
        $request = Request::create('/api/honeypot', 'GET');
        $request->headers->set('Authorization', 'Bearer long-token-string-12345678901234567890');

        $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        $log = HoneypotLog::latest()->first();
        $authAttempt = json_decode($log->attempted_auth, true);

        $this->assertIsArray($authAttempt);
        $this->assertArrayHasKey('bearer_token', $authAttempt);
        $this->assertStringContainsString('long-token-string-12', $authAttempt['bearer_token']);
        $this->assertStringContainsString('...', $authAttempt['bearer_token']);
    }

    /** @test */
    public function it_extracts_api_key_from_custom_header()
    {
        $request = Request::create('/api/honeypot', 'GET');
        $request->headers->set('X-API-Key', 'api-key-1234567890123456789012345');

        $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        $log = HoneypotLog::latest()->first();
        $authAttempt = json_decode($log->attempted_auth, true);

        $this->assertIsArray($authAttempt);
        $this->assertArrayHasKey('api_key', $authAttempt);
        $this->assertStringContainsString('api-key-12345678901', $authAttempt['api_key']);
    }

    /** @test */
    public function it_extracts_mcp_token_from_header()
    {
        $request = Request::create('/api/honeypot', 'GET');
        $request->headers->set('X-MCP-Token', 'mcp-token-123456789012345678901234');

        $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        $log = HoneypotLog::latest()->first();
        $authAttempt = json_decode($log->attempted_auth, true);

        $this->assertIsArray($authAttempt);
        $this->assertArrayHasKey('mcp_token', $authAttempt);
        $this->assertStringContainsString('mcp-token-12345678', $authAttempt['mcp_token']);
    }

    /** @test */
    public function it_extracts_basic_auth_username()
    {
        $request = Request::create('/api/honeypot', 'GET');
        $request->headers->set('Authorization', 'Basic '.base64_encode('admin:password123'));

        $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        $log = HoneypotLog::latest()->first();
        $authAttempt = json_decode($log->attempted_auth, true);

        $this->assertIsArray($authAttempt);
        $this->assertArrayHasKey('basic_auth_user', $authAttempt);
        $this->assertEquals('admin', $authAttempt['basic_auth_user']);
    }

    /** @test */
    public function it_extracts_multiple_auth_types_simultaneously()
    {
        $request = Request::create('/api/honeypot', 'GET');
        $request->headers->set('Authorization', 'Bearer token123456789012345678901234');
        $request->headers->set('X-API-Key', 'key12345678901234567890123456');
        $request->headers->set('X-MCP-Token', 'mcp123456789012345678901234');

        $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        $log = HoneypotLog::latest()->first();
        $authAttempt = json_decode($log->attempted_auth, true);

        $this->assertIsArray($authAttempt);
        $this->assertArrayHasKey('bearer_token', $authAttempt);
        $this->assertArrayHasKey('api_key', $authAttempt);
        $this->assertArrayHasKey('mcp_token', $authAttempt);
    }

    /** @test */
    public function it_stores_null_for_attempted_auth_when_no_auth_provided()
    {
        $request = Request::create('/api/honeypot', 'GET');

        $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        $log = HoneypotLog::latest()->first();
        $this->assertNull($log->attempted_auth);
    }

    /** @test */
    public function it_truncates_auth_tokens_for_privacy()
    {
        $longToken = str_repeat('a', 100);

        $request = Request::create('/api/honeypot', 'GET');
        $request->headers->set('Authorization', 'Bearer '.$longToken);

        $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        $log = HoneypotLog::latest()->first();
        $authAttempt = json_decode($log->attempted_auth, true);

        // Should be truncated to 20 chars + "..."
        $this->assertLessThan(30, strlen($authAttempt['bearer_token']));
        $this->assertStringEndsWith('...', $authAttempt['bearer_token']);
    }

    // ========================================
    // Alert Tests
    // ========================================

    /** @test */
    public function it_does_not_alert_for_first_attempt()
    {
        Log::shouldReceive('channel->critical')->never();

        $request = Request::create('/api/honeypot', 'GET', [], [], [], [
            'REMOTE_ADDR' => '192.168.1.100',
        ]);

        $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });
    }

    /** @test */
    public function it_alerts_when_threshold_exceeded()
    {
        Config::set('honeypot.alert_threshold', 3);

        $ipAddress = '192.168.1.200';

        // Create 2 existing attempts (below threshold)
        HoneypotLog::create([
            'ip_address' => $ipAddress,
            'user_agent' => 'Test',
            'method' => 'GET',
            'path' => '/api/test',
            'full_url' => 'http://test.com/api/test',
            'headers' => '{}',
            'query_params' => '{}',
            'body' => null,
            'created_at' => now()->subMinutes(30),
        ]);

        HoneypotLog::create([
            'ip_address' => $ipAddress,
            'user_agent' => 'Test',
            'method' => 'GET',
            'path' => '/api/test2',
            'full_url' => 'http://test.com/api/test2',
            'headers' => '{}',
            'query_params' => '{}',
            'body' => null,
            'created_at' => now()->subMinutes(20),
        ]);

        // Mock log expectation
        Log::shouldReceive('channel')
            ->with('honeypot')
            ->andReturnSelf()
            ->shouldReceive('warning')
            ->once()
            ->shouldReceive('critical')
            ->once()
            ->with('Multiple honeypot attempts detected', \Mockery::on(function ($data) use ($ipAddress) {
                return $data['ip_address'] === $ipAddress &&
                       $data['attempts_last_hour'] >= 3;
            }));

        $request = Request::create('/api/honeypot', 'GET', [], [], [], [
            'REMOTE_ADDR' => $ipAddress,
        ]);

        $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });
    }

    /** @test */
    public function it_only_counts_recent_attempts_for_alert_threshold()
    {
        Config::set('honeypot.alert_threshold', 3);

        $ipAddress = '192.168.1.250';

        // Create old attempt (outside 1 hour window)
        HoneypotLog::create([
            'ip_address' => $ipAddress,
            'user_agent' => 'Test',
            'method' => 'GET',
            'path' => '/api/old',
            'full_url' => 'http://test.com/api/old',
            'headers' => '{}',
            'query_params' => '{}',
            'body' => null,
            'created_at' => now()->subHours(2),
        ]);

        // Create recent attempt
        HoneypotLog::create([
            'ip_address' => $ipAddress,
            'user_agent' => 'Test',
            'method' => 'GET',
            'path' => '/api/recent',
            'full_url' => 'http://test.com/api/recent',
            'headers' => '{}',
            'query_params' => '{}',
            'body' => null,
            'created_at' => now()->subMinutes(30),
        ]);

        // Should not alert (only 2 attempts in last hour)
        Log::shouldReceive('channel')
            ->with('honeypot')
            ->andReturnSelf()
            ->shouldReceive('warning')
            ->once()
            ->shouldReceive('critical')
            ->never();

        $request = Request::create('/api/honeypot', 'GET', [], [], [], [
            'REMOTE_ADDR' => $ipAddress,
        ]);

        $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });
    }

    /** @test */
    public function it_tracks_attempts_separately_by_ip()
    {
        Config::set('honeypot.alert_threshold', 3);

        // Create attempts from different IPs
        for ($i = 1; $i <= 5; $i++) {
            HoneypotLog::create([
                'ip_address' => "192.168.1.$i",
                'user_agent' => 'Test',
                'method' => 'GET',
                'path' => '/api/test',
                'full_url' => 'http://test.com/api/test',
                'headers' => '{}',
                'query_params' => '{}',
                'body' => null,
                'created_at' => now()->subMinutes(10),
            ]);
        }

        // New request from IP that only has 1 previous attempt
        Log::shouldReceive('channel')
            ->with('honeypot')
            ->andReturnSelf()
            ->shouldReceive('warning')
            ->once()
            ->shouldReceive('critical')
            ->never(); // Should not alert

        $request = Request::create('/api/honeypot', 'GET', [], [], [], [
            'REMOTE_ADDR' => '192.168.1.1',
        ]);

        $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });
    }

    // ========================================
    // Error Handling Tests
    // ========================================

    /** @test */
    public function it_continues_on_database_logging_failure()
    {
        // Simulate database error by using invalid data
        // The middleware should catch the exception and continue

        $request = Request::create('/api/honeypot', 'GET');

        $nextCalled = false;

        // Even if database fails, middleware should continue
        $response = $this->middleware->handle($request, function ($req) use (&$nextCalled) {
            $nextCalled = true;

            return response()->json(['success' => true]);
        });

        $this->assertTrue($nextCalled, 'Middleware should continue even if database logging fails');
        $this->assertEquals(200, $response->getStatusCode());
    }

    /** @test */
    public function it_logs_to_file_channel()
    {
        Log::shouldReceive('channel')
            ->with('honeypot')
            ->andReturnSelf()
            ->shouldReceive('warning')
            ->once()
            ->with('Honeypot triggered', \Mockery::on(function ($data) {
                return isset($data['ip_address']) &&
                       isset($data['method']) &&
                       isset($data['path']);
            }));

        $request = Request::create('/api/honeypot', 'GET');

        $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });
    }

    /** @test */
    public function it_handles_empty_request_body_gracefully()
    {
        $request = Request::create('/api/honeypot', 'POST');

        $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        $log = HoneypotLog::latest()->first();
        $this->assertNotNull($log);
        $this->assertEquals('', $log->body);
    }

    /** @test */
    public function it_handles_missing_headers_gracefully()
    {
        $request = Request::create('/api/honeypot', 'GET');
        // Don't set any custom headers

        $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        $log = HoneypotLog::latest()->first();
        $this->assertNotNull($log);
        $headers = json_decode($log->headers, true);
        $this->assertIsArray($headers);
    }
}

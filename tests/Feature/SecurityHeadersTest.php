<?php

namespace Tests\Feature;

use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    /**
     * Test that security headers are present on web routes.
     *
     * @test
     */
    public function it_includes_security_headers_on_web_routes(): void
    {
        $response = $this->get('/');

        $this->assertSecurityHeaders($response);
    }

    /**
     * Test that security headers are present on API routes.
     *
     * @test
     */
    public function it_includes_security_headers_on_api_routes(): void
    {
        $response = $this->getJson('/api/admin/users');

        $this->assertSecurityHeaders($response);
    }

    /**
     * Test X-Frame-Options header.
     *
     * @test
     */
    public function it_sets_x_frame_options_header(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Frame-Options', 'DENY');
    }

    /**
     * Test X-Content-Type-Options header.
     *
     * @test
     */
    public function it_sets_x_content_type_options_header(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    /**
     * Test X-XSS-Protection header.
     *
     * @test
     */
    public function it_sets_x_xss_protection_header(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-XSS-Protection', '1; mode=block');
    }

    /**
     * Test Strict-Transport-Security header.
     *
     * @test
     */
    public function it_sets_strict_transport_security_header(): void
    {
        $response = $this->get('/');

        $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    /**
     * Test Content-Security-Policy header.
     *
     * @test
     */
    public function it_sets_content_security_policy_header(): void
    {
        $response = $this->get('/');

        $response->assertHeader(
            'Content-Security-Policy',
            "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'"
        );
    }

    /**
     * Test Referrer-Policy header.
     *
     * @test
     */
    public function it_sets_referrer_policy_header(): void
    {
        $response = $this->get('/');

        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    /**
     * Test Permissions-Policy header.
     *
     * @test
     */
    public function it_sets_permissions_policy_header(): void
    {
        $response = $this->get('/');

        $response->assertHeader('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
    }

    /**
     * Test that all security headers are present on POST requests.
     *
     * @test
     */
    public function it_includes_security_headers_on_post_requests(): void
    {
        $response = $this->postJson('/api/admin/login', [
            'username' => 'test',
            'password' => 'test',
        ]);

        $this->assertSecurityHeaders($response);
    }

    /**
     * Test that security headers are present on error responses.
     *
     * @test
     */
    public function it_includes_security_headers_on_error_responses(): void
    {
        $response = $this->get('/non-existent-route');

        // Even 404 responses should have security headers
        $this->assertSecurityHeaders($response);
    }

    /**
     * Test that security headers are present on JSON API responses.
     *
     * @test
     */
    public function it_includes_security_headers_on_json_responses(): void
    {
        $response = $this->getJson('/api/admin/users');

        $response->assertStatus(200);
        $this->assertSecurityHeaders($response);
    }

    /**
     * Test that security headers are present on different API endpoints.
     *
     * @test
     */
    public function it_includes_security_headers_on_multiple_endpoints(): void
    {
        $endpoints = [
            '/api/admin/users',
            '/api/.env',
            '/api/admin/config',
            '/api/phpinfo',
        ];

        foreach ($endpoints as $endpoint) {
            $response = $this->get($endpoint);
            $this->assertSecurityHeaders($response, "Failed for endpoint: {$endpoint}");
        }
    }

    /**
     * Test that security headers don't interfere with application functionality.
     *
     * @test
     */
    public function it_does_not_break_application_functionality(): void
    {
        $response = $this->getJson('/api/admin/users');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data',
            'total',
        ]);

        // Verify security headers are still present
        $this->assertSecurityHeaders($response);
    }

    /**
     * Helper method to assert all security headers are present.
     */
    protected function assertSecurityHeaders($response, string $message = ''): void
    {
        $response->assertHeader('X-Frame-Options', 'DENY', $message);
        $response->assertHeader('X-Content-Type-Options', 'nosniff', $message);
        $response->assertHeader('X-XSS-Protection', '1; mode=block', $message);
        $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains', $message);
        $response->assertHeader(
            'Content-Security-Policy',
            "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'",
            $message
        );
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin', $message);
        $response->assertHeader('Permissions-Policy', 'geolocation=(), microphone=(), camera=()', $message);
    }
}
